<?php

namespace App\Http\Controllers;

use App\Support\WorkflowsProntos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** "Workflows prontos" do painel: cria em um clique uma área temática com abas, cartões e ambiente (WorkflowsProntos). */
class WorkflowsProntosController extends Controller
{
    public function criar(Request $request, string $tema): RedirectResponse|JsonResponse
    {
        try {
            $area = WorkflowsProntos::criar($request, $request->user(), $tema);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                throw $e;
            }

            return back()->with('erro', collect($e->errors())->flatten()->first());
        }

        if ($request->wantsJson()) {
            return response()->json(['area' => $area]);
        }

        return redirect($area['url'])->with('ok', 'Workflow "' . $area['nome'] . '" criado. Toque no botão de música para ouvir o ambiente.');
    }

    /** POST /workflows-gerar { tema }: workflow montado a partir do tema escrito pela pessoa (GeradorWorkflow). */
    public function gerar(Request $request): RedirectResponse|JsonResponse
    {
        $d = $request->validate(['tema' => ['required', 'string', 'min:3', 'max:80']], ['tema.required' => 'Escreva o tema do workflow.', 'tema.min' => 'Escreva um tema com pelo menos 3 letras.']);
        try {
            @set_time_limit(90);
            $area = \App\Support\GeradorWorkflow::gerar($request, $request->user(), $d['tema']);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) throw $e;

            return back()->with('erro', collect($e->errors())->flatten()->first());
        }
        if ($request->wantsJson()) return response()->json(['area' => $area]);

        return redirect($area['url'])->with('ok', 'Workflow "' . $area['nome'] . '" criado a partir do seu tema' . ($area['imagens'] ? ', com imagens de bancos livres (créditos na Visão geral).' : '.'));
    }
}
