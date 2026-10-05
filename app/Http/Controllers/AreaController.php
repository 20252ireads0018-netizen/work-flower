<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Models\Tarefa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function show(Request $request, string $area): View
    {
        abort_unless(array_key_exists($area, Tarefa::AREAS), 404);

        $usuarioId = $request->user()->id;
        $tarefas   = Tarefa::daArea($usuarioId, $area)->get();

        $dados = [
            'slug'    => $area,
            'area'    => Tarefa::AREAS[$area],
            'tarefas' => $tarefas,
        ];

        // Corpo e Mente permitem vincular tarefas aos blocos.
        if (in_array($area, ['corpo', 'mente'], true)) {
            $dados['tarefasJs'] = $tarefas
                ->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo, 'status' => $t->status])
                ->values();
        }

        if ($area === 'corpo') {
            $dados['corpoDados'] = CorpoDado::where('user_id', $usuarioId)->pluck('valor', 'chave');
        }

        // A área Mente guarda estado, imagens e PDFs (ver MenteController).
        if ($area === 'mente') {
            $dados['menteDados'] = MenteController::dados($usuarioId);
        }

        return view()->first(["areas.$area.index", 'areas.show'], $dados);
    }

    public function store(Request $request, string $area): RedirectResponse
    {
        abort_unless(array_key_exists($area, Tarefa::AREAS), 404);

        $dados = $request->validate([
            'titulo'    => ['required', 'string', 'max:150'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'prazo'     => ['nullable', 'date'],
        ], [
            'titulo.required' => 'Dê um nome à tarefa.',
            'prazo.date'      => 'Informe uma data válida.',
        ]);

        Tarefa::create($dados + ['user_id' => $request->user()->id, 'area' => $area]);

        return back()->with('ok', 'Tarefa adicionada.');
    }
}