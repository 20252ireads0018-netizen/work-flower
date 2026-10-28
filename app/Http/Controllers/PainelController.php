<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
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

        // Tutorial abre sozinho no primeiro acesso da conta: nunca visto e nada salvo ainda
        $prefs = PreferenciasController::de($usuarioId);
        $tutorialAuto = empty($prefs['tutorialVisto']) && $resumo->isEmpty() && $this->semDados($usuarioId);

        return view('painel.index', [
            'areas'        => Tarefa::AREAS,
            'resumo'       => $resumo,
            'pendentes'    => $pendentes,
            'tutorialAuto' => $tutorialAuto,
        ]);
    }

    /** true se a conta ainda não guardou nada nas áreas (tema, fundo e preferências não contam). */
    private function semDados(int $usuarioId): bool
    {
        $corpo = CorpoDado::where('user_id', $usuarioId)
            ->where('chave', 'not like', 'fundo%')
            ->where('chave', '!=', PreferenciasController::CHAVE)
            ->exists();

        return ! $corpo
            && ! CarteiraDado::where('user_id', $usuarioId)->exists()
            && ! DiversoDado::where('user_id', $usuarioId)->exists();
    }
}
