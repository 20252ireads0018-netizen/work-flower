<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Support\Pix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Página pública de uma mini loja (/loja/{slug}): vitrine, carrinho, pedido e Pix na chave do dono.
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
        if (! $loja->pixPronto()) {
            return back()->with('erro', 'Esta loja ainda não está recebendo pedidos.');
        }
        $d = $request->validate([
            'nome'     => ['required', 'string', 'max:120'],
            'contato'  => ['required', 'string', 'max:120'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'obs'      => ['nullable', 'string', 'max:500'],
            'itens'    => ['required', 'string', 'max:4000'],
        ], [], ['contato' => 'WhatsApp ou e-mail', 'endereco' => 'endereço']);

        $pedidos = collect(json_decode($d['itens'], true) ?: [])
            ->filter(fn ($i) => is_array($i) && isset($i['id'], $i['qtd']))
            ->map(fn ($i) => ['id' => (int) $i['id'], 'qtd' => max(1, min(99, (int) $i['qtd']))])
            ->groupBy('id')->map(fn ($g, $id) => ['id' => (int) $id, 'qtd' => min(99, $g->sum('qtd'))])
            ->values()->take(self::MAX_ITENS);
        if ($pedidos->isEmpty()) {
            throw ValidationException::withMessages(['itens' => 'O carrinho está vazio.']);
        }

        $pedido = DB::transaction(function () use ($loja, $pedidos, $d) {
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

            $pedido = new LojaPedido([
                'codigo'           => 'LJ' . strtoupper(Str::random(14)),
                'cliente_nome'     => trim($d['nome']),
                'cliente_contato'  => trim($d['contato']),
                'cliente_endereco' => trim((string) ($d['endereco'] ?? '')) ?: null,
                'observacao'       => trim((string) ($d['obs'] ?? '')) ?: null,
                'itens'            => $itens,
                'total'            => round($total, 2),
                'status'           => 'aguardando',
            ]);
            $pedido->loja_id = $loja->id;
            $pedido->save();

            return $pedido;
        });

        return redirect()->route('loja.pedido', [$loja->slug, $pedido->codigo]);
    }

    /** Pedido feito: QR Code e "copia e cola" do Pix na chave do dono da loja. O código do link é o segredo. */
    public function pedido(Request $request, string $slug, string $codigo): View
    {
        $loja = $this->loja($request, $slug);
        $pedido = LojaPedido::where('loja_id', $loja->id)->where('codigo', $codigo)->firstOrFail();

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
