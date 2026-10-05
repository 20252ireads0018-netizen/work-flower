<?php

namespace App\Http\Controllers;

use App\Models\Tarefa;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function index(Request $request): View
    {
        $usuarioId = $request->user()->id;

        $resumo = Tarefa::where('user_id', $usuarioId)
            ->selectRaw("area, count(*) as total, sum(case when status = 'concluida' then 1 else 0 end) as feitas")
            ->groupBy('area')
            ->get()
            ->keyBy('area');

        // Tarefas ainda não concluídas, agrupadas por área, com as de prazo mais próximo primeiro
        $pendentes = Tarefa::where('user_id', $usuarioId)
            ->where('status', '!=', 'concluida')
            ->orderByRaw('prazo is null')
            ->orderBy('prazo')
            ->get()
            ->groupBy('area');

        return view('painel.index', [
            'areas'     => Tarefa::AREAS,
            'resumo'    => $resumo,
            'pendentes' => $pendentes,
        ]);
    }
}