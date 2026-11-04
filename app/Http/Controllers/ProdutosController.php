<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Negócios › Produtos: catálogo de produtos e serviços da conta logada. É a mesma tabela da mini loja (loja_produtos):
 * com loja, o produto marcado "Mostrar na loja" aparece na vitrine. Ao lançar uma venda em Negócios com um produto,
 * o preço, o custo e a margem vêm daqui e o estoque baixa (venda); apagar a venda devolve o estoque (estorno).
 * Pedidos pagos da loja também contam como vendas (vendasDaLoja).
 * O catálogo é sempre da conta logada, como a loja (num workflow compartilhado, cada pessoa vê o próprio).
 */
class ProdutosController extends Controller
{
    public const MAX_PRODUTOS = 2000;

    public function lista(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $produtos = LojaProduto::with('loja')->where('user_id', $uid)->orderBy('nome')->orderBy('id')->limit(self::MAX_PRODUTOS)->get();

        return response()->json([
            'produtos'   => $produtos->map->paraJs()->values(),
            'categorias' => $produtos->pluck('categoria')->filter()->unique()->sort()->values(),
            'loja'       => (bool) $this->loja($request),
        ]);
    }

    public function salvar(Request $request, ?LojaProduto $produto = null): JsonResponse
    {
        $uid = $request->user()->id;
        if ($produto) {
            abort_unless($produto->user_id === $uid, 404);
        } elseif (LojaProduto::where('user_id', $uid)->count() >= self::MAX_PRODUTOS) {
            return response()->json(['erro' => 'Limite de ' . self::MAX_PRODUTOS . ' produtos no catálogo.'], 422);
        }
        $d = $request->validate([
            'nome'       => ['required', 'string', 'max:120'],
            'sku'        => ['nullable', 'string', 'max:40', Rule::unique('loja_produtos', 'sku')->where('user_id', $uid)->ignore($produto?->id)],
            'tipo'       => ['nullable', Rule::in(array_keys(LojaProduto::TIPOS))],
            'categoria'  => ['nullable', 'string', 'max:60'],
            'descricao'  => ['nullable', 'string', 'max:2000'],
            'preco'      => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'custo'      => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'estoque'    => ['nullable', 'integer', 'min:0', 'max:999999'],
            'na_loja'    => ['nullable', 'boolean'],
            'foto'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . LojaController::MAX_IMG_KB],
            'tirar_foto' => ['nullable', 'boolean'],
        ], ['sku.unique' => 'Já existe um produto com este SKU.'], ['preco' => 'preço', 'sku' => 'SKU']);

        $loja = $this->loja($request);
        $produto ??= new LojaProduto();
        $produto->user_id = $uid;
        if ($loja && ! $produto->loja_id) $produto->loja_id = $loja->id;
        $produto->fill([
            'nome'      => trim($d['nome']),
            'sku'       => trim((string) ($d['sku'] ?? '')) ?: null,
            'tipo'      => $d['tipo'] ?? 'produto',
            'categoria' => trim((string) ($d['categoria'] ?? '')) ?: null,
            'descricao' => trim((string) ($d['descricao'] ?? '')) ?: null,
            'preco'     => round((float) $d['preco'], 2),
            'custo'     => $request->filled('custo') ? round((float) $d['custo'], 2) : null,
            'estoque'   => $request->filled('estoque') ? (int) $d['estoque'] : null,
            'ativo'     => $request->boolean('na_loja'),
        ]);
        if ($produto->preco_antigo !== null && (float) $produto->preco_antigo <= (float) $produto->preco) {
            $produto->preco_antigo = null; // "de R$ X por R$ Y" só faz sentido com o antigo maior
        }
        if (($request->boolean('tirar_foto') || $request->hasFile('foto')) && $produto->imagem) {
            $this->apagarFoto($produto);
            $produto->imagem = null;
        }
        if ($request->hasFile('foto')) {
            $produto->imagem = $this->guardarFoto($produto, $request->file('foto'));
        }
        $produto->save();

        return response()->json(['ok' => true, 'produto' => $produto->fresh()->paraJs()]);
    }

    public function remover(Request $request, LojaProduto $produto): JsonResponse
    {
        abort_unless($produto->user_id === $request->user()->id, 404);
        $this->apagarFoto($produto);
        $produto->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Venda lançada em Negócios com este produto: devolve preço, custo e margem calculados aqui e baixa o estoque.
     * Sem estoque suficiente, 422 com a mensagem (a venda não é lançada).
     */
    public function venda(Request $request, LojaProduto $produto): JsonResponse
    {
        abort_unless($produto->user_id === $request->user()->id, 404);
        $qtd = (int) $request->validate(['qtd' => ['required', 'integer', 'min:1', 'max:99999']])['qtd'];

        return DB::transaction(function () use ($produto, $qtd) {
            $p = LojaProduto::whereKey($produto->id)->lockForUpdate()->first();
            if ($p->estoque !== null && $p->estoque < $qtd) {
                return response()->json(['erro' => $p->estoque > 0 ? "Só há {$p->estoque} de \"{$p->nome}\" em estoque." : "\"{$p->nome}\" está sem estoque."], 422);
            }
            if ($p->estoque !== null) {
                $p->decrement('estoque', $qtd);
            }
            $preco = (float) $p->preco;
            $custo = $p->custo !== null ? (float) $p->custo : null;
            $total = round($preco * $qtd, 2);
            $custoTotal = $custo !== null ? round($custo * $qtd, 2) : 0.0;

            return response()->json([
                'ok' => true, 'produto_id' => $p->id, 'nome' => $p->nome, 'sku' => $p->sku, 'qtd' => $qtd,
                'preco' => $preco, 'custo' => $custo, 'total' => $total, 'custo_total' => $custoTotal,
                'lucro' => round($total - $custoTotal, 2), 'margem' => $p->margem(), 'estoque' => $p->fresh()->estoque,
            ]);
        });
    }

    /** A venda com este produto foi apagada em Negócios: o estoque volta. */
    public function estorno(Request $request, LojaProduto $produto): JsonResponse
    {
        abort_unless($produto->user_id === $request->user()->id, 404);
        $qtd = (int) $request->validate(['qtd' => ['required', 'integer', 'min:1', 'max:99999']])['qtd'];
        LojaProduto::whereKey($produto->id)->whereNotNull('estoque')->increment('estoque', $qtd);

        return response()->json(['ok' => true, 'estoque' => $produto->fresh()->estoque]);
    }

    /** Foto do produto para o catálogo (só o dono; a vitrine pública usa /loja/{slug}/img). */
    public function foto(Request $request, LojaProduto $produto, string $arquivo): BinaryFileResponse
    {
        abort_unless($produto->user_id === $request->user()->id && $produto->imagem === $arquivo, 404);
        $caminho = $produto->caminhoImagem();
        abort_unless($caminho, 404);

        return response()->file(Storage::disk('local')->path($caminho), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Pedidos pagos da loja (pago ou enviado) do último ano, para somar às vendas de um negócio. */
    public function vendasDaLoja(Request $request): JsonResponse
    {
        $loja = Loja::withTrashed()->where('user_id', $request->user()->id)->first();
        if (! $loja) {
            return response()->json(['loja' => null, 'vendas' => []]);
        }
        $pedidos = LojaPedido::where('loja_id', $loja->id)->whereIn('status', ['pago', 'enviado'])
            ->where('created_at', '>=', now()->subYear())->latest('id')->limit(3000)->get();
        $custos = LojaProduto::where('loja_id', $loja->id)->whereNotNull('custo')->pluck('custo', 'id')->all();

        return response()->json([
            'loja'   => $loja->trashed() ? null : $loja->nome,
            'vendas' => $pedidos->map(fn (LojaPedido $p) => [
                'id'    => 'lj' . $p->id,
                'd'     => ($p->confirmado_em ?? $p->created_at)->tz('America/Sao_Paulo')->toDateString(),
                'item'  => 'Pedido ' . $p->codigo . ' · ' . $p->cliente_nome,
                'valor' => round((float) $p->total, 2),
                'custo' => $p->custoItens($custos),
                'canal' => 'Loja virtual',
            ])->values(),
        ]);
    }

    /* ---------- apoio ---------- */

    private function loja(Request $request): ?Loja
    {
        return Loja::where('user_id', $request->user()->id)->first();
    }

    /** Fotos ficam na pasta da loja (a vitrine serve de lá) ou, sem loja, na pasta do dono. */
    private function guardarFoto(LojaProduto $p, UploadedFile $arq): string
    {
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$arq->getMimeType()] ?? 'jpg';
        $nome = Str::random(24) . '.' . $ext;
        $arq->storeAs($p->loja_id ? 'lojas/' . $p->loja_id : 'produtos/' . $p->user_id, $nome, 'local');

        return $nome;
    }

    private function apagarFoto(LojaProduto $p): void
    {
        if ($caminho = $p->caminhoImagem()) {
            Storage::disk('local')->delete($caminho);
        }
    }
}
