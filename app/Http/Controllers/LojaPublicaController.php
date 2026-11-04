<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\LojaCupom;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Support\MercadoPago;
use App\Support\Pix;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Página pública de uma mini loja (/loja/{slug}): vitrine, carrinho, entrega, cupom, pedido e pagamento
 * (Pix na chave do dono ou cartão no Mercado Pago do dono).
 * Os preços e o estoque são sempre conferidos aqui no servidor (o carrinho do navegador só diz o que e quanto).
 */
class LojaPublicaController extends Controller
{
    private const MAX_ITENS = 30;

    public function ver(Request $request, string $slug): View
    {
        $loja = $this->loja($request, $slug);

        return view('loja.publica', [
            'loja'     => $loja,
            'v'        => $loja->visual(),
            'produtos' => $this->vitrine($loja),
            'previa'   => ! $loja->publicada,
        ]);
    }

    public function pedir(Request $request, string $slug): RedirectResponse
    {
        $loja = $this->loja($request, $slug);
        abort_unless($loja->publicada, 404);
        if (! $loja->recebePedidos()) {
            return back()->with('erro', 'Esta loja ainda não está recebendo pedidos.');
        }
        $d = $request->validate([
            'nome'     => ['required', 'string', 'max:120'],
            'contato'  => ['required', 'string', 'max:120'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'obs'      => ['nullable', 'string', 'max:500'],
            'itens'    => ['required', 'string', 'max:4000'],
            'frete'    => ['nullable', 'integer', 'min:0', 'max:' . (Loja::MAX_FRETES - 1)],
            'cupom'    => ['nullable', 'string', 'max:30'],
            'pagamento' => ['nullable', 'in:pix,mp'],
        ], [], ['contato' => 'WhatsApp ou e-mail', 'endereco' => 'endereço']);
        $pagamento = ($d['pagamento'] ?? null) === 'mp' && $loja->mpPronto() ? 'mp' : ($loja->pixPronto() ? 'pix' : 'mp');
        $fretes = $loja->fretes();
        if ($fretes && ! isset($fretes[(int) ($d['frete'] ?? -1)])) {
            throw ValidationException::withMessages(['frete' => 'Escolha como quer receber o pedido.']);
        }

        $pedidos = collect(json_decode($d['itens'], true) ?: [])
            ->filter(fn ($i) => is_array($i) && isset($i['id'], $i['qtd']))
            ->map(fn ($i) => ['id' => (int) $i['id'], 'qtd' => max(1, min(99, (int) $i['qtd']))])
            ->groupBy('id')->map(fn ($g, $id) => ['id' => (int) $id, 'qtd' => min(99, $g->sum('qtd'))])
            ->values()->take(self::MAX_ITENS);
        if ($pedidos->isEmpty()) {
            throw ValidationException::withMessages(['itens' => 'O carrinho está vazio.']);
        }

        $pedido = DB::transaction(function () use ($loja, $pedidos, $d, $fretes, $pagamento) {
            $vitrine = $this->vitrine($loja)->keyBy('id');
            $produtos = LojaProduto::whereIn('id', $pedidos->pluck('id'))->where('loja_id', $loja->id)->lockForUpdate()->get()->keyBy('id');
            $itens = [];
            $total = 0;
            foreach ($pedidos as $p) {
                $prod = $produtos[$p['id']] ?? null;
                if (! $prod || ! $vitrine->has($prod->id)) {
                    throw ValidationException::withMessages(['itens' => 'Um dos produtos do carrinho não está mais à venda. Atualize a página.']);
                }
                if ($prod->estoque !== null && $prod->estoque < $p['qtd']) {
                    throw ValidationException::withMessages(['itens' => $prod->estoque > 0
                        ? "Só há {$prod->estoque} de \"{$prod->nome}\" em estoque."
                        : "\"{$prod->nome}\" esgotou."]);
                }
                $itens[] = ['id' => $prod->id, 'nome' => $prod->nome, 'preco' => (float) $prod->preco, 'qtd' => $p['qtd']];
                $total += (float) $prod->preco * $p['qtd'];
                if ($prod->estoque !== null) {
                    $prod->decrement('estoque', $p['qtd']); // reserva; cancelar o pedido devolve
                }
            }

            // cupom e entrega são conferidos aqui, com o subtotal real (o navegador só mostra a prévia)
            $subtotal = round($total, 2);
            $desconto = 0.0;
            $cupom = null;
            if ($codigo = LojaCupom::normalizar($d['cupom'] ?? '')) {
                $cupom = LojaCupom::where('loja_id', $loja->id)->where('codigo', $codigo)->lockForUpdate()->first();
                if (! $cupom) throw ValidationException::withMessages(['cupom' => 'Cupom não encontrado.']);
                if ($erro = $cupom->erro($subtotal)) throw ValidationException::withMessages(['cupom' => $erro]);
                $desconto = $cupom->desconto($subtotal);
                $cupom->increment('usos');
            }
            $frete = $fretes ? $fretes[(int) $d['frete']] : null;
            $valorFrete = $frete ? Loja::precoFrete($frete, $subtotal) : 0.0;
            $total = max(0, $subtotal - $desconto) + $valorFrete;
            if ($total < 0.01) throw ValidationException::withMessages(['cupom' => 'O total do pedido precisa ser maior que zero.']);

            $pedido = new LojaPedido([
                'codigo'           => 'LJ' . strtoupper(Str::random(14)),
                'cliente_nome'     => trim($d['nome']),
                'cliente_contato'  => trim($d['contato']),
                'cliente_endereco' => trim((string) ($d['endereco'] ?? '')) ?: null,
                'observacao'       => trim((string) ($d['obs'] ?? '')) ?: null,
                'itens'            => $itens,
                'total'            => round($total, 2),
                'subtotal'         => $subtotal,
                'desconto'         => $desconto,
                'frete'            => $valorFrete,
                'frete_nome'       => $frete['nome'] ?? null,
                'cupom'            => $cupom?->codigo,
                'pagamento'        => $pagamento,
                'status'           => 'aguardando',
            ]);
            $pedido->loja_id = $loja->id;
            $pedido->save();

            return $pedido;
        });

        if ($pagamento === 'mp') {
            if ($url = MercadoPago::checkout($loja, $pedido)) return redirect()->away($url);
            $aviso = $loja->pixPronto() ? 'O Mercado Pago não respondeu agora. Você pode pagar com Pix abaixo ou tentar o cartão de novo.' : 'O Mercado Pago não respondeu agora. Tente o botão de pagar de novo em instantes.';

            return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo])->with('ok', $aviso);
        }

        return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo]);
    }

    /** Prévia do cupom no carrinho (o valor de verdade é calculado de novo ao fazer o pedido). */
    public function cupom(Request $request, string $slug): JsonResponse
    {
        $loja = $this->loja($request, $slug);
        $codigo = LojaCupom::normalizar((string) $request->query('codigo'));
        $subtotal = max(0, min(9999999, (float) $request->query('subtotal')));
        $cupom = $codigo ? LojaCupom::where('loja_id', $loja->id)->where('codigo', $codigo)->first() : null;
        if (! $cupom) return response()->json(['ok' => false, 'erro' => 'Cupom não encontrado.']);
        if ($erro = $cupom->erro($subtotal)) return response()->json(['ok' => false, 'erro' => $erro]);

        return response()->json(['ok' => true, 'codigo' => $cupom->codigo, 'rotulo' => $cupom->rotulo(), 'tipo' => $cupom->tipo,
            'valor' => (float) $cupom->valor, 'minimo' => $cupom->minimo !== null ? (float) $cupom->minimo : null]);
    }

    /** Pagar (ou tentar de novo) com cartão no Mercado Pago um pedido ainda aberto. */
    public function pagarMp(Request $request, string $slug, string $codigo): RedirectResponse
    {
        $loja = $this->loja($request, $slug);
        $pedido = LojaPedido::where('loja_id', $loja->id)->where('codigo', $codigo)->firstOrFail();
        if (! $pedido->aberto() || ! $loja->mpPronto()) return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo]);
        if ($url = MercadoPago::checkout($loja, $pedido)) return redirect()->away($url);

        return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo])->with('ok', 'O Mercado Pago não respondeu agora. Tente de novo em instantes.');
    }

    /** Aviso (webhook) do Mercado Pago: só traz o id; o pagamento é lido de novo na API com o token da loja. */
    public function avisoMp(Request $request, string $slug): JsonResponse
    {
        $loja = Loja::where('slug', $slug)->first();
        $id = (string) ($request->input('data.id') ?? $request->query('data_id') ?? ($request->query('topic') === 'payment' ? $request->query('id') : ''));
        $tipo = (string) ($request->input('type') ?? $request->query('type') ?? $request->query('topic'));
        if ($loja && $tipo === 'payment' && $id !== '') MercadoPago::conferir($loja, $id);

        return response()->json(['ok' => true]); // sempre 200, para o Mercado Pago não ficar repetindo
    }

    /** Pedido feito: QR Code e "copia e cola" do Pix na chave do dono da loja. O código do link é o segredo. */
    public function pedido(Request $request, string $slug, string $codigo): View
    {
        $loja = $this->loja($request, $slug);
        $pedido = LojaPedido::where('loja_id', $loja->id)->where('codigo', $codigo)->firstOrFail();
        // volta do Mercado Pago: confere o pagamento pelo id do endereço (na API, não pelo que veio no link)
        $voltaMp = (string) ($request->query('payment_id') ?: $request->query('collection_id'));
        if ($voltaMp !== '' && $pedido->aberto()) {
            $st = MercadoPago::conferir($loja, $voltaMp);
            $pedido->refresh();
            if (in_array($st, ['pending', 'in_process'], true)) session()->now('ok', 'Pagamento em análise no Mercado Pago. Esta página mostra quando for aprovado.');
            elseif (in_array($st, ['rejected', 'cancelled'], true)) session()->now('ok', 'O Mercado Pago não aprovou o pagamento. Você pode tentar de novo.');
        }

        return view('loja.pedido', [
            'loja'   => $loja,
            'v'      => $loja->visual(),
            'pedido' => $pedido,
            'codigo' => $pedido->aberto() && $loja->pixPronto() ? Pix::copiaECola((float) $pedido->total, $pedido->codigo, $loja->pixConfig()) : null,
        ]);
    }

    public function paguei(Request $request, string $slug, string $codigo): RedirectResponse
    {
        $loja = $this->loja($request, $slug);
        $pedido = LojaPedido::where('loja_id', $loja->id)->where('codigo', $codigo)->firstOrFail();
        if ($pedido->status === 'aguardando') {
            $pedido->forceFill(['status' => 'informado', 'informado_em' => now()])->save();
        }

        return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo])->with('ok', 'Obrigado! A loja vai conferir o pagamento e falar com você.');
    }

    /** Logo, banner e fotos dos produtos (só arquivos desta loja, com nome no formato que a própria loja gerou). */
    public function imagem(string $slug, string $arquivo): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{24}\.(jpg|png|webp)$/', $arquivo, $m), 404);
        $loja = Loja::where('slug', $slug)->firstOrFail();
        $caminho = 'lojas/' . $loja->id . '/' . $arquivo;
        abort_unless(Storage::disk('local')->exists($caminho), 404);

        return response()->file(Storage::disk('local')->path($caminho), [
            'Content-Type'  => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$m[1]],
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /* ---------- apoio ---------- */

    /** Loja pelo endereço. Fechada ao público: só o dono vê (como prévia). */
    private function loja(Request $request, string $slug): Loja
    {
        $loja = Loja::with('user')->where('slug', $slug)->firstOrFail();
        abort_unless($loja->publicada || $request->user()?->id === $loja->user_id, 404);

        return $loja;
    }

    /** Produtos ativos que aparecem, respeitando o limite do plano do dono. */
    private function vitrine(Loja $loja)
    {
        $limite = $loja->limiteProdutos();

        return $loja->produtos()->where('ativo', true)->when($limite !== null, fn ($q) => $q->limit($limite))->get();
    }
}
