<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Compartilhar;
use App\Support\PedacosBanco;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorpoController extends Controller
{
    /** Grava (ou substitui) uma chave de dados do usuário. O valor chega como texto JSON. */
    public function salvar(Request $request, string $chave): JsonResponse
    {
        $this->chavePermitida($chave);

        // as linhas do cartão Banco de dados (sqlz.*) são gzip em base64, não JSON (Sincronia::valorInvalido confere)
        $dados = $request->validate([
            'valor' => ['required', 'string', ...(PedacosBanco::eh($chave) ? [] : ['json']), 'max:6000000'],
        ]);

        return Sincronia::gravar($request, 'corpo', Compartilhar::dono($request, 'corpo'), $chave, $dados['valor']);
    }

    public function remover(Request $request, string $chave): JsonResponse
    {
        $this->chavePermitida($chave);
        if (PedacosBanco::eh($chave)) return PedacosBanco::apagar($request, 'corpo', $chave);

        CorpoDado::where('user_id', Compartilhar::dono($request, 'corpo'))
            ->where('chave', $chave)
            ->delete();

        return response()->json(['ok' => true]);
    }

    /** Um pedaço do cartão Banco de dados (vem do Diverso; PedacosBanco). */
    public function ler(Request $request, string $chave): JsonResponse
    {
        return PedacosBanco::ler($request, 'corpo', $chave);
    }

    /** O JS só usa "estado", "img.<id do bloco>" e "sqlz.<id do bloco>.*" (Banco de dados); qualquer outra chave é recusada. */
    private function chavePermitida(string $chave): void
    {
        abort_unless($chave === 'estado' || str_starts_with($chave, 'img.') || PedacosBanco::eh($chave), 404);
    }
}