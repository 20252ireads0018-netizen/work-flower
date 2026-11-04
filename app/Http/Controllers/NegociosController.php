<?php

namespace App\Http\Controllers;

use App\Models\CarteiraDado;
use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\NegocioDado;
use App\Models\Tarefa;
use App\Support\Compartilhar;
use App\Support\Sincronia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Área Negócios: vendas e caixa dos negócios, clientes, documentos, atalhos da loja e dos marketplaces e a equipe.
 * Usa a mesma página da Carteira (areas/carteira/index, modo "negocios"), com dados e compartilhamento próprios
 * (tabela negocio_dados). Na primeira vez, o que já existia nas abas Negócios e Documentos da Carteira vem para cá.
 */
class NegociosController extends Controller
{
    private const SLUG = 'negocios';

    public function show(Request $request): View
    {
        $donoId = Compartilhar::dono($request, self::SLUG);
        self::trazerDaCarteira($donoId);

        $dados = NegocioDado::where('user_id', $donoId)->pluck('valor', 'chave');
        $tarefas = Tarefa::daArea($donoId, self::SLUG)->get();
        $loja = Loja::where('user_id', $request->user()->id)->first();

        return view('areas.carteira.index', [
            'modo'          => self::SLUG,
            'slug'          => self::SLUG,
            'area'          => Tarefa::AREAS[self::SLUG],
            'tarefas'       => $tarefas,
            'tarefasJs'     => $tarefas->map(fn ($t) => ['id' => $t->id, 'titulo' => $t->titulo])->values(),
            'dados'         => $dados,
            'lojaResumo'    => $loja ? [
                'produtos'  => $loja->produtos()->count(),
                'abertos'   => LojaPedido::where('loja_id', $loja->id)->whereIn('status', ['aguardando', 'informado'])->count(),
                'publicada' => $loja->publicada,
            ] : null,
            'wfVersao'      => Sincronia::versao($dados['estado'] ?? null),
            'wfSincronizar' => Compartilhar::temOutrasPessoas($donoId, self::SLUG, $request->user()->id),
        ]);
    }

    public function salvar(Request $request, string $chave): JsonResponse
    {
        $data = $request->validate(['valor' => ['nullable', 'string', 'max:6000000']]);

        return Sincronia::gravar($request, self::SLUG, Compartilhar::dono($request, self::SLUG), $chave, $data['valor'] ?? null);
    }

    public function remover(Request $request, string $chave): JsonResponse
    {
        NegocioDado::where('user_id', Compartilhar::dono($request, self::SLUG))->where('chave', $chave)->delete();

        return response()->json(['ok' => true]);
    }

    /** Clientes que já compraram na loja da conta: nome, contato, quantos pedidos e quanto gastaram (sem os cancelados). */
    public function clientesDaLoja(Request $request): JsonResponse
    {
        $loja = Loja::where('user_id', $request->user()->id)->first();
        if (! $loja) {
            return response()->json(['clientes' => []]);
        }
        $clientes = LojaPedido::where('loja_id', $loja->id)->where('status', '!=', 'cancelado')
            ->get(['cliente_nome', 'cliente_contato', 'total'])
            ->groupBy(fn ($p) => mb_strtolower(preg_replace('/\s+/', '', $p->cliente_contato)))
            ->map(fn ($g) => [
                'nome'    => $g->last()->cliente_nome,
                'contato' => $g->last()->cliente_contato,
                'pedidos' => $g->count(),
                'total'   => round((float) $g->sum('total'), 2),
            ])->values()->take(1000);

        return response()->json(['clientes' => $clientes]);
    }

    /**
     * Primeira abertura: copia da Carteira as abas Negócios e Documentos (com os cartões e imagens delas).
     * A Carteira não perde nada: os dados antigos continuam lá, só deixam de aparecer.
     */
    public static function trazerDaCarteira(int $donoId): void
    {
        if (NegocioDado::where('user_id', $donoId)->where('chave', 'estado')->exists()) {
            return;
        }
        $bruto = CarteiraDado::where('user_id', $donoId)->where('chave', 'estado')->value('valor');
        $e = is_string($bruto) ? json_decode($bruto, true) : null;
        $e = is_array($e) ? $e : [];
        $secoes = ['negocios', 'docs'];
        $blocos = array_values(array_filter($e['blocos'] ?? [], fn ($b) => is_array($b) && in_array($b['secao'] ?? null, $secoes, true)));
        $novo = [
            'layout'         => array_intersect_key(is_array($e['layout'] ?? null) ? $e['layout'] : [], array_flip($secoes)),
            'blocos'         => $blocos,
            'negocios'       => $e['negocios'] ?? [],
            'negAtivo'       => $e['negAtivo'] ?? null,
            'docs'           => $e['docs'] ?? [],
            'docAtivo'       => $e['docAtivo'] ?? null,
            'clientes'       => [],
            'veioDaCarteira' => now()->toDateString(),
        ];
        if (! $novo['layout']) {
            $novo['layout'] = new \stdClass(); // JSON {} (o quadro espera objeto)
        }

        DB::transaction(function () use ($donoId, $novo, $blocos) {
            NegocioDado::firstOrCreate(['user_id' => $donoId, 'chave' => 'estado'], ['valor' => json_encode($novo, JSON_UNESCAPED_UNICODE)]);
            foreach ($blocos as $b) {
                $id = (string) ($b['id'] ?? '');
                if (! preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
                    continue;
                }
                CarteiraDado::where('user_id', $donoId)
                    ->where(fn ($q) => $q->where('chave', 'img.' . $id)->orWhere('chave', 'like', 'img.' . $id . '.%'))
                    ->get(['chave', 'valor'])
                    ->each(fn ($l) => NegocioDado::firstOrCreate(['user_id' => $donoId, 'chave' => $l->chave], ['valor' => $l->valor]));
            }
        });
    }
}
