<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use App\Support\Compartilhar;
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

    public function update(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($this->podeMexer($request, $tarefa), 403);

        $dados = $request->validate([
            'status' => ['required', Rule::in(array_keys(Tarefa::STATUS))],
        ]);

        $tarefa->update($dados);

        return back()->with('ok', 'Status atualizado.');
    }

    public function destroy(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($this->podeMexer($request, $tarefa), 403);

        $tarefa->delete();

        return back()->with('ok', 'Tarefa excluída.');
    }
}

