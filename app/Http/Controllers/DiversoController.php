<?php

namespace App\Http\Controllers;

use App\Models\DiversoDado;
use App\Models\Tarefa;
use App\Support\Compartilhar;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Área Diverso: abas criadas pelo usuário, cada uma um quadro livre.
 * Guarda numa tabela só: "estado" (abas, blocos, layout, lixeira) e "img.{id}" (imagens dos blocos).
 * O cartão "Banco de dados" guarda as linhas em pedaços "sqlz.{id}.{versão}.{tabela}.{n}" (gzip em base64,
 * nunca SQL cru: o mod_security da hospedagem recusa com 406). Esses pedaços não vão junto com a página:
 * o navegador busca cada um quando abre a tabela (ler).
 */
class DiversoController extends Controller
{
    private const SLUG = 'diverso';

    /** Prefixo dos pedaços do cartão Banco de dados. */
    public const PEDACO = 'sqlz.';

    /** Área criada pela conta: a mesma página, só com as abas daquela área (estado.abas[].grupo = id). */
    public function extra(Request $request, string $id): View
    {
        $x = \App\Support\AreasConta::extra($request->user(), $id);
        abort_unless($x, 404);

        return $this->show($request, $x);
    }

    public function show(Request $request, ?array $extra = null): View
    {
        $id = Compartilhar::dono($request, self::SLUG);
        $tarefas = Tarefa::daArea($id, self::SLUG)->get();
        $dados = static::dados($id);
        $area = Tarefa::AREAS[self::SLUG];
        if ($extra) {
            $area = ['nome' => $extra['nome'], 'descricao' => 'Área criada por você: abas com quadros livres.', 'icone' => \App\Support\AreasConta::ICONE_EXTRA] + $area;
        }

        return view('areas.diverso.index', [
            'slug'         => self::SLUG,
            'area'         => $area,
            // áreas criadas (abas com grupo): qual está aberta, quais existem e se mostra todas (workflow de outra pessoa)
            'diversoGrupo' => $extra['id'] ?? null,
            'diversoGrupos' => array_map(fn ($x) => ['id' => $x['id'], 'nome' => $x['nome']], \App\Support\AreasConta::config($request->user())['extras']),
            'diversoTodas' => $id !== $request->user()->id,
            'tarefas'      => $tarefas,
            'tarefasJs'    => $tarefas->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo, 'status' => $t->status])->values(),
            'diversoDados' => $dados,
            'wfVersao'      => Sincronia::versao($dados['estado'] ?? null),
            'wfSincronizar' => Compartilhar::temOutrasPessoas($id, self::SLUG, $request->user()->id),
        ]);
    }

    public static function dados(int $usuarioId)
    {
        // os pedaços do Banco de dados (sqlz.*) podem somar dezenas de MB: ficam fora da página
        return DiversoDado::where('user_id', $usuarioId)->where('chave', 'not like', self::PEDACO . '%')->pluck('valor', 'chave');
    }

    public function salvar(Request $request, string $chave): JsonResponse
    {
        $data = $request->validate(['valor' => ['nullable', 'string', 'max:6000000']]);
        // pedaço do Banco de dados: só base64 (gzip); texto cru não entra
        if (str_starts_with($chave, self::PEDACO) && isset($data['valor']) && ! preg_match('~^[A-Za-z0-9+/=]*$~', $data['valor'])) {
            return response()->json(['message' => 'Pedaço do banco em formato inválido.'], 422);
        }

        return Sincronia::gravar($request, self::SLUG, Compartilhar::dono($request, self::SLUG), $chave, $data['valor'] ?? null);
    }

    /** Um pedaço do Banco de dados (só chaves sqlz.*; o resto já vem com a página). */
    public function ler(Request $request, string $chave): JsonResponse
    {
        abort_unless(str_starts_with($chave, self::PEDACO), 404);
        $valor = DiversoDado::where('user_id', Compartilhar::dono($request, self::SLUG))->where('chave', $chave)->value('valor');
        abort_if($valor === null, 404);

        return response()->json(['valor' => $valor]);
    }

    public function apagar(Request $request, string $chave): JsonResponse
    {
        $q = DiversoDado::where('user_id', Compartilhar::dono($request, self::SLUG));
        if (str_starts_with($chave, self::PEDACO)) {
            // sqlz.{id} apaga todos os pedaços do cartão; sqlz.{id}.{versão} só os daquela leitura
            // "_" é curinga no LIKE (sqlz.a_c pegaria sqlz.abc.*): o LIKE só pré-filtra, o prefixo exato é conferido aqui
            $chaves = (clone $q)->where('chave', 'like', $chave . '.%')->pluck('chave')
                ->filter(fn ($c) => str_starts_with($c, $chave . '.'))->push($chave)->all();
            $q->whereIn('chave', $chaves);
        } else {
            $q->where('chave', $chave);
        }
        $q->delete();

        return response()->json(['ok' => true]);
    }
}

