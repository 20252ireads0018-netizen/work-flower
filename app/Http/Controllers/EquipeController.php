<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use App\Models\WorkflowAtividade;
use App\Models\WorkflowMembro;
use App\Support\Compartilhar;
use App\Support\Equipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aba Equipe (área Negócios): o dono vê as pessoas dos workflows dele, o que cada uma está fazendo,
 * o histórico de mudanças e as tarefas delegadas, libera ou tira a edição e bloqueia ou desbloqueia o acesso.
 * Tudo é sempre sobre as áreas da conta logada (não sobre o workflow aberto).
 */
class EquipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $eu = $request->user();
        $vinculos = WorkflowMembro::with('membro:id,name,email')->where('dono_id', $eu->id)->orderBy('created_at')->get();
        $ativos = Compartilhar::convidadosAtivos($eu);
        $ids = $vinculos->pluck('membro_id')->unique()->values()->all();
        $online = Equipe::online($eu->id, $ids, Compartilhar::areas());
        $nomeArea = fn ($a) => Tarefa::AREAS[$a]['nome'] ?? $a;

        $tarefas = Tarefa::where('user_id', $eu->id)->whereIn('responsavel_id', $ids)->where('status', '!=', 'concluida')
            ->orderByRaw('prazo is null')->orderBy('prazo')->get(['id', 'titulo', 'area', 'status', 'prazo', 'responsavel_id'])
            ->groupBy('responsavel_id');
        $ultimas = WorkflowAtividade::where('dono_id', $eu->id)->whereIn('user_id', $ids)
            ->selectRaw('user_id, max(created_at) as em')->groupBy('user_id')->pluck('em', 'user_id');

        $pessoas = $vinculos->groupBy('membro_id')->map(function ($lista, $id) use ($ativos, $online, $nomeArea, $tarefas, $ultimas) {
            $m = $lista->first()->membro;
            $agora = collect($online)->map(fn ($porPessoa, $area) => isset($porPessoa[$id]) ? ['area' => $nomeArea($area), 'aba' => $porPessoa[$id]['aba'], 'em' => $porPessoa[$id]['em']] : null)->filter()->values();

            return [
                'id'      => (int) $id,
                'nome'    => $m?->name,
                'email'   => $m?->email,
                'plano'   => in_array((int) $id, $ativos, true), // false = o plano do dono não comporta essa pessoa agora
                'areas'   => $lista->map(fn ($v) => ['area' => $v->area, 'nome' => $nomeArea($v->area), 'pode_editar' => $v->pode_editar, 'bloqueado' => $v->bloqueado])->values(),
                'online'  => $agora,
                'ultima'  => isset($ultimas[$id]) ? \Illuminate\Support\Carbon::parse($ultimas[$id])->toIso8601String() : null,
                'tarefas' => ($tarefas[$id] ?? collect())->map(fn ($t) => [
                    'id' => $t->id, 'titulo' => $t->titulo, 'area' => $nomeArea($t->area), 'status' => Tarefa::STATUS[$t->status] ?? $t->status,
                    'prazo' => $t->prazo?->format('d/m'), 'url' => url('/areas/' . $t->area),
                ])->values(),
            ];
        })->values();

        $atividades = WorkflowAtividade::with('user:id,name')->where('dono_id', $eu->id)->latest('id')->limit(80)->get()
            ->map(fn ($a) => [
                'quem' => $a->user_id === $eu->id ? 'Você' : ($a->user?->name ?? 'Alguém'),
                'acao' => $a->acao, 'area' => $nomeArea($a->area), 'aba' => $a->aba,
                'em' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'pessoas'    => $pessoas,
            'atividades' => $atividades,
            'areas'      => collect(Compartilhar::areas())->map(fn ($a) => ['id' => $a, 'nome' => $nomeArea($a)])->values(),
            'limite'     => Compartilhar::limitePessoas($eu),
        ]);
    }

    /** PATCH /equipe/{membro}/{area} { pode_editar?, bloqueado? }: o dono muda o que a pessoa pode fazer naquele workflow. */
    public function alterar(Request $request, int $membro, string $area): JsonResponse
    {
        abort_unless(in_array($area, Compartilhar::areas(), true), 404);
        $d = $request->validate(['pode_editar' => ['sometimes', 'boolean'], 'bloqueado' => ['sometimes', 'boolean']]);
        $v = WorkflowMembro::where(['dono_id' => $request->user()->id, 'membro_id' => $membro, 'area' => $area])->firstOrFail();
        $v->fill($d)->save();

        return response()->json(['ok' => true, 'pode_editar' => $v->pode_editar, 'bloqueado' => $v->bloqueado]);
    }
}
