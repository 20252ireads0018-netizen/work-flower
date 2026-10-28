<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Compartilhar;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorpoController extends Controller
{
    /** Grava (ou substitui) uma chave de dados do usuário. O valor chega como texto JSON. */
    public function salvar(Request $request, string $chave): JsonResponse
    {
        $this->chavePermitida($chave);

        $dados = $request->validate([
            'valor' => ['required', 'string', 'json', 'max:6000000'],
        ]);

        return Sincronia::gravar($request, 'corpo', Compartilhar::dono($request, 'corpo'), $chave, $dados['valor']);
    }

    public function remover(Request $request, string $chave): JsonResponse
    {
        $this->chavePermitida($chave);

        CorpoDado::where('user_id', Compartilhar::dono($request, 'corpo'))
            ->where('chave', $chave)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** O JS só usa "estado" e "img.<id do bloco>"; qualquer outra chave é recusada. */
    private function chavePermitida(string $chave): void
    {
        abort_unless($chave === 'estado' || str_starts_with($chave, 'img.'), 404);
    }
}