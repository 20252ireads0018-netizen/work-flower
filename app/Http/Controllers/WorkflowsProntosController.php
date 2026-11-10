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
}
