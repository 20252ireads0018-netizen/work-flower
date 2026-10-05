<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TemaController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'cor_primaria'  => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cor_cabecalho' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'regex' => 'Escolha uma cor válida.',
        ]);

        $request->user()->update([
            'cor_primaria'  => strtolower($dados['cor_primaria']),
            'cor_cabecalho' => strtolower($dados['cor_cabecalho']),
        ]);

        return back()->with('ok', 'Cores salvas.');
    }
}
