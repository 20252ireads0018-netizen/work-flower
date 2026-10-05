<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TarefaController extends Controller
{
    public function update(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($tarefa->user_id === $request->user()->id, 403);

        $dados = $request->validate([
            'status' => ['required', Rule::in(array_keys(Tarefa::STATUS))],
        ]);

        $tarefa->update($dados);

        return back()->with('ok', 'Status atualizado.');
    }

    public function destroy(Request $request, Tarefa $tarefa): RedirectResponse
    {
        abort_unless($tarefa->user_id === $request->user()->id, 403);

        $tarefa->delete();

        return back()->with('ok', 'Tarefa excluída.');
    }
}
