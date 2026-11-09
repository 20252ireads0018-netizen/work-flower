<?php

namespace App\Http\Controllers;

use App\Support\Mercado;
use App\Support\SimuladorInvest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Carteira › Investimentos: dados de mercado (FIIs, ações, ETFs…), screener e simulador.
 * Dados da brapi.dev com cache no servidor (App\Support\Mercado). Nada aqui é recomendação de investimento.
 */
class InvestimentosController extends Controller
{
    /** GET /carteira/mercado/ativos?tickers=MXRF11,PETR4 → { ativos: { MXRF11: {...}|null }, token } */
    public function ativos(Request $request): JsonResponse
    {
        $tickers = collect(explode(',', (string) $request->query('tickers')))
            ->map(fn ($t) => Mercado::ticker($t))->filter()->unique()->take(30)->values()->all();

        return response()->json(['ativos' => (object) Mercado::ativos($tickers), 'token' => Mercado::temToken()]);
    }

    /** GET /carteira/mercado/lista?tipo=fii&setor=&liq_min=&dy_min=&dy_max=&pvp_min=&pvp_max=&busca=&ordem=&limite= */
    public function lista(Request $request): JsonResponse
    {
        $f = $request->validate([
            'tipo' => ['nullable', 'in:todos,' . implode(',', array_keys(Mercado::TIPOS))],
            'setor' => ['nullable', 'string', 'max:80'],
            'busca' => ['nullable', 'string', 'max:40'],
            'liq_min' => ['nullable', 'numeric', 'min:0'],
            'dy_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dy_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pvp_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pvp_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ordem' => ['nullable', 'in:liquidez,dy,pvp,preco,variacao'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $f['tipo'] ??= 'fii';

        return response()->json(Mercado::filtrar($f));
    }

    /**
     * POST /carteira/mercado/simular
     * { inicial, mensal, meses, reinvestir, alvo, ativos: [{ ticker?, nome?, dy?, valorizacao? }] } (até 4 ativos)
     * Ativo com ticker e sem DY usa o DY dos últimos 12 meses.
     */
    public function simular(Request $request): JsonResponse
    {
        $d = $request->validate([
            'inicial' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'mensal' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'meses' => ['required', 'integer', 'min:1', 'max:600'],
            'reinvestir' => ['boolean'],
            'alvo' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'ativos' => ['required', 'array', 'min:1', 'max:4'],
            'ativos.*.ticker' => ['nullable', 'string', 'max:12'],
            'ativos.*.nome' => ['nullable', 'string', 'max:60'],
            'ativos.*.dy' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ativos.*.valorizacao' => ['nullable', 'numeric', 'min:-50', 'max:100'],
        ]);

        $tickers = collect($d['ativos'])->filter(fn ($a) => ! isset($a['dy']) || $a['dy'] === '')->map(fn ($a) => Mercado::ticker($a['ticker'] ?? null))->filter()->all();
        $dados = $tickers ? Mercado::ativos($tickers) : [];

        $params = [
            'inicial' => (float) ($d['inicial'] ?? 0),
            'mensal' => (float) ($d['mensal'] ?? 0),
            'meses' => (int) $d['meses'],
            'reinvestir' => (bool) ($d['reinvestir'] ?? true),
            'alvo' => (float) ($d['alvo'] ?? 0),
        ];

        $resultados = [];
        $erros = [];
        foreach ($d['ativos'] as $i => $a) {
            $t = Mercado::ticker($a['ticker'] ?? null);
            $nome = $t ?: (trim((string) ($a['nome'] ?? '')) ?: 'Cenário ' . ($i + 1));
            $dy = isset($a['dy']) && $a['dy'] !== '' ? (float) $a['dy'] : ($t ? ($dados[$t]['dy'] ?? null) : null);
            if ($dy === null) {
                $erros[] = $t ? "Sem DY para $t agora: informe o DY manualmente." : "Informe o DY de \"$nome\".";

                continue;
            }
            $resultados[] = SimuladorInvest::rodar($params, ['nome' => $nome, 'dy' => $dy, 'valorizacao' => (float) ($a['valorizacao'] ?? 0)]);
        }

        if (! $resultados) {
            return response()->json(['erro' => $erros[0] ?? 'Nada para simular.', 'erros' => $erros], 422);
        }

        return response()->json(['resultados' => $resultados, 'erros' => $erros]);
    }
}
