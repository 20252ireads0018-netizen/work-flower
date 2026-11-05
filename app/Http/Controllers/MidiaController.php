<?php

namespace App\Http\Controllers;

use App\Support\Midia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Envio em pedaços de imagens e GIFs grandes (fundos da área, das abas e dos cartões). Ver App\Support\Midia. */
class MidiaController extends Controller
{
    public function iniciar(Request $request): JsonResponse
    {
        $d = $request->validate(['tipo' => ['required', 'string', 'max:40'], 'tamanho' => ['required', 'integer', 'min:1']]);
        $uid = $request->user()->id;
        Midia::limparParados($uid);

        return response()->json(['ok' => true, 'token' => Midia::iniciar($uid, $d['tipo'], (int) $d['tamanho']), 'pedaco' => 4 * 1024 * 1024]);
    }

    public function pedaco(Request $request, string $token, int $n): JsonResponse
    {
        $d = $request->validate(['dados' => ['required', 'string', 'max:5600000']]);
        Midia::pedaco($request->user()->id, $token, $n, $d['dados']);

        return response()->json(['ok' => true]);
    }

    public function concluir(Request $request, string $token): JsonResponse
    {
        $m = Midia::concluir($request->user()->id, $token);

        return response()->json(['ok' => true, 'token' => $m['token'], 'url' => route('midia.ver', $m['token'])]);
    }

    public function ver(string $token): BinaryFileResponse
    {
        return Midia::resposta($token);
    }

    public function apagar(Request $request, string $token): JsonResponse
    {
        Midia::apagar($request->user()->id, $token);

        return response()->json(['ok' => true]);
    }
}
