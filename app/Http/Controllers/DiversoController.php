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
 */
class DiversoController extends Controller
{
    private const SLUG = 'diverso';

    public function show(Request $request): View
    {
        $id = Compartilhar::dono($request, self::SLUG);
        $tarefas = Tarefa::daArea($id, self::SLUG)->get();

        return view('areas.diverso.index', [
            'slug'         => self::SLUG,
            'area'         => Tarefa::AREAS[self::SLUG],
            'tarefas'      => $tarefas,
            'tarefasJs'    => $tarefas->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo, 'status' => $t->status])->values(),
            'diversoDados' => static::dados($id),
            'wfVersao'      => Sincronia::versaoAtual(self::SLUG, $id),
            'wfSincronizar' => Compartilhar::temOutrasPessoas($id, self::SLUG, $request->user()->id),
        ]);
    }

    public static function dados(int $usuarioId)
    {
        return DiversoDado::where('user_id', $usuarioId)->pluck('valor', 'chave');
    }

    public function salvar(Request $request, string $chave): JsonResponse
    {
        $data = $request->validate(['valor' => ['nullable', 'string', 'max:6000000']]);

        return Sincronia::gravar($request, self::SLUG, Compartilhar::dono($request, self::SLUG), $chave, $data['valor'] ?? null);
    }

    public function apagar(Request $request, string $chave): JsonResponse
    {
        DiversoDado::where('user_id', Compartilhar::dono($request, self::SLUG))->where('chave', $chave)->delete();

        return response()->json(['ok' => true]);
    }
}

