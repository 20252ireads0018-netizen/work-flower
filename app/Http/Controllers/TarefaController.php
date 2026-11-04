<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use App\Support\Compartilhar;
use App\Support\Discord;
use App\Support\Equipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TarefaController extends Controller
{
    /** Dono da tarefa ou alguém com acesso ao workflow (área) dela. */
    private function podeMexer(Request $request, Tarefa $tarefa): bool
    {
        return Compartilhar::podeAcessar($request->user()->id, (int) $tarefa->user_id, (string) $tarefa->area);
    }

    /**
     * Muda a tarefa no quadro: coluna (status), cor, categoria, responsável, título, descrição e prazo.
     * Quem só pode ver o workflow mexe apenas na coluna das tarefas delegadas a ele.
     */
    public function update(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($this->podeMexer($request, $tarefa), 403);
        $eu = $request->user()->id;
        $dono = (int) $tarefa->user_id;
        $edita = Compartilhar::podeEditar($eu, $dono, (string) $tarefa->area);

        $dados = $request->validate([
            'status'         => ['sometimes', 'required', Rule::in(array_keys(Tarefa::STATUS))],
            'titulo'         => ['sometimes', 'required', 'string', 'max:150'],
            'descricao'      => ['sometimes', 'nullable', 'string', 'max:1000'],
            'prazo'          => ['sometimes', 'nullable', 'date'],
            'categoria'      => ['sometimes', 'nullable', 'string', 'max:40'],
            'cor'            => ['sometimes', 'nullable', Rule::in(Tarefa::CORES)],
            'responsavel_id' => ['sometimes', 'nullable', 'integer', Rule::in(array_keys(Compartilhar::pessoas($dono, (string) $tarefa->area)))],
        ], ['titulo.required' => 'Dê um nome à tarefa.', 'responsavel_id.in' => 'Essa pessoa não está neste workflow.']);

        if (! $edita) {
            // só visualização: pode andar com a tarefa que é dela pelo quadro, e nada mais
            abort_unless($tarefa->responsavel_id === $eu && array_keys($dados) === ['status'], 403, 'O dono deste workflow liberou só a visualização para você.');
        }
        if (array_key_exists('categoria', $dados)) {
            $dados['categoria'] = trim((string) $dados['categoria']) ?: null;
        }

        $antes = $tarefa->status;
        $antesResp = $tarefa->responsavel_id;
        $tarefa->update($dados);
        $quem = $request->user()->name;

        if (isset($dados['status']) && $dados['status'] !== $antes) {
            Equipe::registrar($dono, (string) $tarefa->area, $eu, 'moveu "' . mb_substr($tarefa->titulo, 0, 80) . '" para ' . Tarefa::STATUS[$dados['status']], 'Tarefas');
            Discord::avisarTarefa('tarefa_movida', $tarefa, $quem, '➡️ Tarefa movida para ' . Tarefa::STATUS[$dados['status']]);
        } elseif (array_key_exists('responsavel_id', $dados) && $tarefa->responsavel_id) {
            Equipe::registrar($dono, (string) $tarefa->area, $eu, 'delegou "' . mb_substr($tarefa->titulo, 0, 80) . '" a ' . ($tarefa->responsavel?->name ?? 'alguém'), 'Tarefas');
        }
        if (array_key_exists('responsavel_id', $dados) && $tarefa->responsavel_id && $tarefa->responsavel_id !== $antesResp) {
            Discord::avisarTarefa('tarefa_delegada', $tarefa, $quem, '👤 Tarefa delegada a ' . ($tarefa->responsavel?->name ?? 'alguém'));
        }

        return back()->with('ok', 'Tarefa atualizada.');
    }

    public function destroy(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($this->podeMexer($request, $tarefa), 403);
        abort_unless(Compartilhar::podeEditar($request->user()->id, (int) $tarefa->user_id, (string) $tarefa->area), 403,
            'O dono deste workflow liberou só a visualização para você.');

        Equipe::registrar((int) $tarefa->user_id, (string) $tarefa->area, $request->user()->id, 'excluiu a tarefa "' . mb_substr($tarefa->titulo, 0, 80) . '"', 'Tarefas');
        $tarefa->delete();

        return back()->with('ok', 'Tarefa excluída.');
    }
}
