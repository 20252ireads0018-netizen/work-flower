<?php

namespace App\Http\Controllers;

use App\Support\AreasConta;
use App\Support\MoverCartoes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tela "Áreas" (engrenagem › Áreas e botão no painel, public/js/areas-config.js):
 * esconder/mostrar, renomear e reordenar as áreas, criar e excluir áreas próprias e levar tudo de uma área
 * para outra. "Mover para outra área" dos cartões e abas (clique direito, quadro.js) usa mover/destinos.
 * Tudo vale só para os dados da própria conta.
 */
class AreasContaController extends Controller
{
    private function resposta(Request $request, array $extra = []): JsonResponse
    {
        return response()->json($extra + ['areas' => array_values(AreasConta::lista($request->user()))]);
    }

    public function index(Request $request): JsonResponse
    {
        return $this->resposta($request);
    }

    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ordem'     => ['sometimes', 'array', 'max:40'],
            'ordem.*'   => ['string', 'max:20'],
            'ocultas'   => ['sometimes', 'array', 'max:40'],
            'ocultas.*' => ['string', 'max:20'],
            'nomes'     => ['sometimes', 'array', 'max:40'],
            'nomes.*'   => ['nullable', 'string', 'max:60'],
        ]);
        AreasConta::salvar($request->user(), $dados);

        return $this->resposta($request);
    }

    public function criar(Request $request): JsonResponse
    {
        $dados = $request->validate(['nome' => ['required', 'string', 'max:60']]);
        $x = AreasConta::criarExtra($request->user(), $dados['nome']);

        return $this->resposta($request, ['criada' => $x + ['url' => route('areas.extra', $x['id'])]]);
    }

    /** Exclui uma área criada: as abas voltam para o Diverso (destino=diverso) ou são apagadas (destino=apagar). */
    public function excluir(Request $request, string $id): JsonResponse
    {
        abort_unless(AreasConta::extra($request->user(), $id), 404);
        $dados = $request->validate(['destino' => ['required', 'in:diverso,apagar']]);
        $n = MoverCartoes::soltarGrupo($request, $request->user(), $id, $dados['destino'] === 'apagar');
        AreasConta::removerExtra($request->user(), $id);

        return $this->resposta($request, ['abas' => $n]);
    }

    public function destinos(Request $request): JsonResponse
    {
        return response()->json(['areas' => MoverCartoes::destinos($request->user())]);
    }

    /** Move cartões de uma aba (todos ou só "ids") para outra área. */
    public function mover(Request $request): JsonResponse
    {
        $d = $request->validate([
            'de'       => ['required', 'string', 'max:20'],
            'aba'      => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'ids'      => ['nullable', 'array', 'max:500'],
            'ids.*'    => ['string', 'max:60'],
            'para'     => ['required', 'string', 'max:20'],
            'para_aba' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'nome'     => ['nullable', 'string', 'max:60'],
        ]);
        $r = MoverCartoes::mover($request, $request->user(), $d['de'], $d['aba'], $d['ids'] ?? null, $d['para'], $d['para_aba'] ?? null, $d['nome'] ?? null);

        return response()->json($r + ['url' => $this->urlArea($request, $d['para'])]);
    }

    /** Leva tudo de uma área para o Diverso ou uma área criada; "esconder" tira a área de origem do menu. */
    public function moverTudo(Request $request): JsonResponse
    {
        $d = $request->validate([
            'de'       => ['required', 'string', 'max:20'],
            'para'     => ['required', 'string', 'max:20'],
            'esconder' => ['sometimes', 'boolean'],
        ]);
        $r = MoverCartoes::moverTudo($request, $request->user(), $d['de'], $d['para']);
        if (! empty($d['esconder'])) AreasConta::esconder($request->user(), $d['de']);

        return $this->resposta($request, $r + ['url' => $this->urlArea($request, $d['para'])]);
    }

    private function urlArea(Request $request, string $slug): ?string
    {
        return AreasConta::lista($request->user())[$slug]['url'] ?? null;
    }
}
