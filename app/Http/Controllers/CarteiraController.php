<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\Tarefa;
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

        $dados = CarteiraDado::where('user_id', $user->id)->pluck('valor', 'chave');

        // Ajuste aqui se o seu model Tarefa usar outros nomes de coluna.
        $tarefas = Tarefa::where('user_id', $user->id)
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
        ]);
    }

    /** PUT /carteira/dados/{chave}  body: { valor: "..." } */
    public function salvar(Request $request, string $chave): JsonResponse
    {
        $data = $request->validate([
            'valor' => ['nullable', 'string', 'max:6000000'], // imagens já chegam reduzidas pelo JS
        ]);

        CarteiraDado::updateOrCreate(
            ['user_id' => $request->user()->id, 'chave' => $chave],
            ['valor' => $data['valor'] ?? null],
        );

        return response()->json(['ok' => true]);
    }

    /** DELETE /carteira/dados/{chave} */
    public function remover(Request $request, string $chave): JsonResponse
    {
        CarteiraDado::where('user_id', $request->user()->id)->where('chave', $chave)->delete();

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
            $preco = Cache::remember("cotacao.$t", 300, fn () => $this->buscarPreco($t));
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

            return is_numeric($p) ? (float) $p : null;
        } catch (\Throwable) {
            return null;
        }
    }
}