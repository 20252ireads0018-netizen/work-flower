<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\Tarefa;
use App\Support\Compartilhar;
use App\Support\PedacosBanco;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CarteiraController extends Controller
{
    private const SLUG = 'carteira';

    public function show(Request $request)
    {
        $user = $request->user();
        $donoId = Compartilhar::dono($request, self::SLUG); // workflow compartilhado: dados do dono
        // abas retiradas (Trabalho e Mapa mental): os cartões delas vão para o Quadro livre antes de ler o estado
        \App\Support\AbasRetiradas::migrarConta(self::SLUG, $donoId);

        // as linhas do cartão Banco de dados (sqlz.*, PedacosBanco) ficam fora da página
        $dados = CarteiraDado::where('user_id', $donoId)->where('chave', 'not like', PedacosBanco::PREFIXO . '%')->pluck('valor', 'chave');

        // Ajuste aqui se o seu model Tarefa usar outros nomes de coluna.
        $tarefas = Tarefa::where('user_id', $donoId)
            ->where('area', self::SLUG)
            ->latest()
            ->get();

        $tarefasJs = $tarefas->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo])->values();

        return view('areas.carteira.index', [
            'slug'      => self::SLUG,
            'area'      => Tarefa::AREAS[self::SLUG],
            'tarefas'   => $tarefas,
            'tarefasJs' => $tarefasJs,
            'dados'     => $dados,
            'wfVersao'      => Sincronia::versao($dados['estado'] ?? null),
            'wfSincronizar' => Compartilhar::temOutrasPessoas($donoId, self::SLUG, $user->id),
        ]);
    }

    /** PUT /carteira/dados/{chave}  body: { valor: "..." } */
    public function salvar(Request $request, string $chave): JsonResponse
    {
        $data = $request->validate([
            'valor' => ['nullable', 'string', 'max:6000000'], // imagens já chegam reduzidas pelo JS
        ]);

        return Sincronia::gravar($request, self::SLUG, Compartilhar::dono($request, self::SLUG), $chave, $data['valor'] ?? null);
    }

    /** GET /carteira/dados/sqlz.* : um pedaço do cartão Banco de dados (vem do Diverso; PedacosBanco) */
    public function ler(Request $request, string $chave): JsonResponse
    {
        return PedacosBanco::ler($request, self::SLUG, $chave);
    }

    /** DELETE /carteira/dados/{chave} */
    public function remover(Request $request, string $chave): JsonResponse
    {
        if (PedacosBanco::eh($chave)) return PedacosBanco::apagar($request, self::SLUG, $chave);
        CarteiraDado::where('user_id', Compartilhar::dono($request, self::SLUG))->where('chave', $chave)->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /carteira/cotacao?tickers=PETR4,MXRF11 → { "PETR4": 38.12, ... } */
    public function cotacao(Request $request): JsonResponse
    {
        $tickers = collect(explode(',', (string) $request->query('tickers')))
            ->map(fn ($t) => strtoupper(trim($t)))
            ->filter(fn ($t) => preg_match('/^[A-Z0-9]{3,10}$/', $t))
            ->unique()->take(30)->values();

        $saida = [];
        foreach ($tickers as $t) {
            // falha também fica guardada (como 0, por 2 minutos): códigos inventados não viram uma chamada externa cada
            $preco = Cache::get("cotacao.$t");
            if ($preco === null) {
                $preco = $this->buscarPreco($t) ?? 0.0;
                Cache::put("cotacao.$t", $preco, $preco ? 300 : 120);
            }
            if ($preco) {
                $saida[$t] = $preco;
            }
        }

        return response()->json((object) $saida);
    }

    private function buscarPreco(string $ticker): ?float
    {
        try {
            $r = Http::timeout(6)->acceptJson()->get("https://brapi.dev/api/quote/{$ticker}", [
                'token' => config('services.brapi.token'),
            ]);
            $p = $r->ok() ? data_get($r->json(), 'results.0.regularMarketPrice') : null;
        } catch (\Throwable) {
            $p = null;
        }
        // sem token a brapi só cota alguns tickers: o resto sai da lista aberta (App\Support\Mercado, cache de 1 h)
        $p = is_numeric($p) ? $p : (\App\Support\Mercado::daLista($ticker)['p'] ?? null);

        return is_numeric($p) && $p > 0 ? (float) $p : null;
    }
}