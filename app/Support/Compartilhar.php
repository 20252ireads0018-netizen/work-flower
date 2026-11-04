<?php

namespace App\Support;

use App\Models\Tarefa;
use App\Models\User;
use App\Models\WorkflowMembro;
use Illuminate\Http\Request;

/**
 * Workflows compartilhados: o dono de uma área (Corpo, Mente, Carteira, Diverso) dá acesso a outras contas.
 *
 * - O limite é de PESSOAS NA CONTA do dono, contando o próprio dono (Planos::limite($plano, 'usuarios')):
 *   no Gratuito só ele; no Básico, ele e mais 4. Quem foi convidado para várias áreas conta uma vez só.
 * - Se o plano do dono cair, os convidados mais recentes ficam sem acesso até o dono voltar ao plano
 *   (os convites continuam guardados).
 * - Qual workflow está aberto vem do cabeçalho X-WF-Dono (pedidos do JS), do campo wf_dono (formulários)
 *   ou de ?de= (abrir a página). Sem nada disso, é o workflow do próprio usuário.
 */
class Compartilhar
{
    /** Áreas que podem ser compartilhadas. */
    public static function areas(): array
    {
        return array_keys(Tarefa::AREAS);
    }

    /** Quantas pessoas cabem na conta do dono (com ele). null = sem limite. */
    public static function limitePessoas(User $dono): ?int
    {
        return Planos::limite(Planos::slugDe($dono), 'usuarios');
    }

    /** Convidados da conta do dono (sem repetir), do mais antigo para o mais novo. */
    public static function convidados(int $donoId): array
    {
        return WorkflowMembro::where('dono_id', $donoId)
            ->selectRaw('membro_id, min(created_at) as desde, min(id) as primeiro')
            ->groupBy('membro_id')
            ->orderBy('desde')->orderBy('primeiro')
            ->pluck('membro_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Convidados que têm acesso agora (cabem no plano do dono). */
    public static function convidadosAtivos(User $dono): array
    {
        $todos = self::convidados($dono->id);
        $limite = self::limitePessoas($dono);

        return $limite === null ? $todos : array_slice($todos, 0, max(0, $limite - 1));
    }

    /** true se $membroId pode abrir a área $area do dono agora. */
    public static function podeAcessar(int $membroId, int $donoId, string $area): bool
    {
        return $membroId === $donoId || self::donoSeTemAcesso($membroId, $donoId, $area) !== null;
    }

    /** O dono, se o convidado $membroId pode abrir a área dele agora; null se não pode (ou se o dono o bloqueou). */
    private static function donoSeTemAcesso(int $membroId, int $donoId, string $area): ?User
    {
        return self::acesso($membroId, $donoId, $area)[0] ?? null;
    }

    /** [dono, vínculo] se o convidado pode abrir a área agora; null se não pode. */
    private static function acesso(int $membroId, int $donoId, string $area): ?array
    {
        $vinculo = WorkflowMembro::where(['dono_id' => $donoId, 'membro_id' => $membroId, 'area' => $area])->first();
        if (! $vinculo || $vinculo->bloqueado) {
            return null;
        }
        $dono = User::find($donoId);

        return $dono && in_array($membroId, self::convidadosAtivos($dono), true) ? [$dono, $vinculo] : null;
    }

    /**
     * true se $userId pode mudar o conteúdo da área do dono: o próprio dono, ou um convidado com a edição liberada.
     * Sem a edição, o convidado só vê (e mexe só nas tarefas que foram delegadas a ele).
     */
    public static function podeEditar(int $userId, int $donoId, string $area): bool
    {
        if ($userId === $donoId) {
            return true;
        }
        $a = self::acesso($userId, $donoId, $area);

        return $a !== null && $a[1]->pode_editar;
    }

    /** Pessoas que podem receber tarefas desta área: o dono e os convidados com acesso agora. [id => nome] */
    public static function pessoas(int $donoId, string $area): array
    {
        $dono = User::find($donoId);
        if (! $dono) {
            return [];
        }
        $ativos = self::convidadosAtivos($dono);
        $ids = WorkflowMembro::where(['dono_id' => $donoId, 'area' => $area, 'bloqueado' => false])->pluck('membro_id')
            ->filter(fn ($id) => in_array((int) $id, $ativos, true))->all();

        return [$dono->id => $dono->name] + User::whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Id do dono pedido na requisição (ou null = o próprio usuário). */
    public static function donoPedido(Request $request): ?int
    {
        // o primeiro valor preenchido vale (cabeçalho vazio não esconde o ?de= do endereço)
        foreach ([$request->header('X-WF-Dono'), $request->input('wf_dono'), $request->query('de')] as $v) {
            if (is_numeric($v) && (int) $v > 0) return (int) $v;
        }

        return null;
    }

    /**
     * De quem são os dados que esta requisição lê/grava na área: do próprio usuário ou do dono que o convidou.
     * Guarda o resultado em $request->attributes ('wf_dono', 'wf_dono_nome', 'wf_area') para o layout.
     * Sem acesso: 403.
     */
    public static function dono(Request $request, string $area): int
    {
        $usuario = $request->user();
        $pedido = self::donoPedido($request);
        $request->attributes->set('wf_area', $area);

        if (! $pedido || $pedido === $usuario->id) {
            return $usuario->id;
        }

        $acesso = self::acesso($usuario->id, $pedido, $area);
        abort_unless($acesso, 403,
            'Você não tem (ou não tem mais) acesso a este workflow. O dono pode ter removido, bloqueado você ou mudado de plano.');
        [$dono, $vinculo] = $acesso;

        $request->attributes->set('wf_dono', $pedido);
        $request->attributes->set('wf_dono_nome', (string) ($dono->name ?? 'Outra conta'));

        // o dono liberou só a visualização: abre e lê, mas o servidor recusa qualquer gravação
        if (! $vinculo->pode_editar) {
            $request->attributes->set('wf_so_ver', true);
            abort_unless($request->isMethodSafe(), 403, 'O dono deste workflow liberou só a visualização para você.');
        }

        return $pedido;
    }

    /** true se o workflow aberto tem mais de uma pessoa (liga a sincronização no navegador). */
    public static function temOutrasPessoas(int $donoId, string $area, int $usuarioId): bool
    {
        if ($donoId !== $usuarioId) {
            return true;
        }

        return WorkflowMembro::where(['dono_id' => $donoId, 'area' => $area])->exists();
    }
}
