<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Support\Pix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carteira › Loja: a pessoa monta a própria mini loja (aparência, Pix, produtos) e acompanha os pedidos.
 * A loja é da conta logada (não vale workflow compartilhado). Página pública: LojaPublicaController.
 */
class LojaController extends Controller
{
    public const MAX_IMG_KB = 3072;
    private const ABAS = ['pedidos', 'produtos', 'aparencia', 'pix'];

    public function show(Request $request): View
    {
        $loja = $this->loja($request);
        $aba = in_array($request->query('aba'), self::ABAS, true) ? $request->query('aba') : ($loja ? 'pedidos' : 'aparencia');
        $status = is_string($s = $request->query('status')) && isset(LojaPedido::STATUS[$s]) ? $s : null;

        return view('areas.carteira.loja', [
            'loja'     => $loja,
            'aba'      => $aba,
            'status'   => $status,
            'produtos' => $loja ? $loja->produtos()->get() : collect(),
            'pedidos'  => $loja ? $loja->pedidos()->when($status, fn ($q) => $q->where('status', $status))->paginate(25)->withQueryString() : null,
            'contagem' => $loja ? $loja->pedidos()->reorder()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status') : collect(),
            'sugestao' => $loja ? null : $this->slugLivre(Str::slug($request->user()->name) ?: 'minha-loja'),
        ]);
    }

    /** Nome, endereço, textos e aparência (cria a loja na primeira vez). */
    public function salvar(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        $d = $request->validate([
            'nome'      => ['required', 'string', 'max:80'],
            'slug'      => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Loja::RESERVADOS),
                Rule::unique('lojas', 'slug')->ignore($loja?->id)],
            'chamada'   => ['nullable', 'string', 'max:160'],
            'sobre'     => ['nullable', 'string', 'max:3000'],
            'cor'       => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fundo'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'texto'     => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cartao'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'fonte'     => ['nullable', Rule::in(array_keys(Loja::FONTES))],
            'layout'    => ['nullable', Rule::in(['grade', 'lista'])],
            'cantos'    => ['nullable', Rule::in(['retos', 'suaves', 'redondos'])],
            'whatsapp'  => ['nullable', 'string', 'max:20', 'regex:/^[0-9 ()+\-]*$/'],
            'instagram' => ['nullable', 'string', 'max:40', 'regex:/^@?[A-Za-z0-9._]*$/'],
            'entrega'   => ['nullable', 'string', 'max:300'],
            'botao'     => ['nullable', 'string', 'max:20'],
            'estoque'   => ['nullable', 'boolean'],
            'publicada' => ['nullable', 'boolean'],
            'logo'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMG_KB],
            'banner'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMG_KB],
            'tirar_logo'   => ['nullable', 'boolean'],
            'tirar_banner' => ['nullable', 'boolean'],
        ], [], ['slug' => 'endereço da loja']);

        $loja ??= new Loja();
        $loja->user_id = $request->user()->id;
        $visual = $loja->exists ? $loja->visual() : [];
        foreach (['cor', 'fundo', 'texto', 'cartao', 'fonte', 'layout', 'cantos', 'entrega', 'botao'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null) {
                $visual[$k] = is_string($d[$k]) ? (str_starts_with($d[$k], '#') ? strtolower($d[$k]) : trim($d[$k])) : $d[$k];
            }
        }
        $visual['whatsapp'] = preg_replace('/\D/', '', (string) ($d['whatsapp'] ?? '')) ?: null;
        $visual['instagram'] = ltrim((string) ($d['instagram'] ?? ''), '@') ?: null;
        $visual['estoque'] = $request->boolean('estoque');
        $visual['botao'] = trim((string) ($d['botao'] ?? '')) ?: 'Comprar';

        $loja->fill([
            'nome'      => trim($d['nome']),
            'slug'      => $d['slug'],
            'chamada'   => trim((string) ($d['chamada'] ?? '')) ?: null,
            'sobre'     => trim((string) ($d['sobre'] ?? '')) ?: null,
            'publicada' => $request->boolean('publicada'),
        ]);
        $loja->save(); // precisa do id para a pasta das imagens

        foreach (['logo', 'banner'] as $k) {
            if ($request->boolean('tirar_' . $k) && ! empty($visual[$k])) {
                $this->apagarImagem($loja, $visual[$k]);
                $visual[$k] = null;
            }
            if ($request->hasFile($k)) {
                if (! empty($visual[$k])) {
                    $this->apagarImagem($loja, $visual[$k]);
                }
                $visual[$k] = $this->guardarImagem($loja, $request->file($k));
            }
        }
        $loja->visual = $visual;
        $loja->save();

        return redirect()->route('carteira.loja', ['aba' => 'aparencia'])->with('ok', 'Loja salva.' . ($loja->publicada ? '' : ' Ela ainda não está publicada: marque "Loja aberta ao público" quando quiser mostrar.'));
    }

    /** Chave Pix que recebe os pagamentos da loja (o dinheiro vai direto para a pessoa). */
    public function pix(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $d = $request->validate([
            'tipo'   => ['required', Rule::in(array_keys(Pix::TIPOS))],
            'chave'  => ['required', 'string', 'max:77'],
            'nome'   => ['required', 'string', 'max:60'],
            'cidade' => ['required', 'string', 'max:40'],
        ], [], ['chave' => 'chave Pix', 'nome' => 'nome do titular']);
        $d['chave'] = Pix::normalizarChave($d['tipo'], $d['chave']);
        if ($erro = Pix::erroChave($d['tipo'], $d['chave'])) {
            return back()->withErrors(['chave' => $erro])->withInput();
        }
        if (! preg_match('/[A-Za-z0-9]/', Str::ascii($d['nome']))) {
            return back()->withErrors(['nome' => 'Use o nome do titular com letras.'])->withInput();
        }
        $loja->forceFill(['pix_tipo' => $d['tipo'], 'pix_chave' => $d['chave'], 'pix_nome' => trim($d['nome']), 'pix_cidade' => trim($d['cidade'])])->save();

        return redirect()->route('carteira.loja', ['aba' => 'pix'])->with('ok', 'Chave Pix salva. Os próximos pedidos já usam estes dados.');
    }

    public function salvarProduto(Request $request, ?LojaProduto $produto = null): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        if ($produto) {
            abort_unless($produto->loja_id === $loja->id, 404);
        } else {
            $limite = $loja->limiteProdutos();
            if ($limite !== null && $loja->produtos()->count() >= $limite) {
                return back()->withErrors(['nome' => "O plano Gratuito mostra até {$limite} produtos na loja. Assine o Básico para ter produtos sem limite."])->withInput();
            }
        }
        $d = $request->validate([
            'nome'         => ['required', 'string', 'max:120'],
            'descricao'    => ['nullable', 'string', 'max:2000'],
            'preco'        => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'preco_antigo' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'estoque'      => ['nullable', 'integer', 'min:0', 'max:999999'],
            'ordem'        => ['nullable', 'integer', 'min:0', 'max:9999'],
            'ativo'        => ['nullable', 'boolean'],
            'imagem'       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_IMG_KB],
            'tirar_imagem' => ['nullable', 'boolean'],
        ], [], ['preco' => 'preço', 'preco_antigo' => 'preço antigo']);

        $produto ??= new LojaProduto(['loja_id' => $loja->id]);
        $produto->loja_id = $loja->id;
        $antigo = isset($d['preco_antigo']) && (float) $d['preco_antigo'] > (float) $d['preco'] ? round((float) $d['preco_antigo'], 2) : null;
        $produto->fill([
            'nome'         => trim($d['nome']),
            'descricao'    => trim((string) ($d['descricao'] ?? '')) ?: null,
            'preco'        => round((float) $d['preco'], 2),
            'preco_antigo' => $antigo,
            'estoque'      => $request->filled('estoque') ? (int) $d['estoque'] : null,
            'ordem'        => (int) ($d['ordem'] ?? 0),
            'ativo'        => $request->boolean('ativo'),
        ]);
        if ($request->boolean('tirar_imagem') && $produto->imagem) {
            $this->apagarImagem($loja, $produto->imagem);
            $produto->imagem = null;
        }
        if ($request->hasFile('imagem')) {
            if ($produto->imagem) {
                $this->apagarImagem($loja, $produto->imagem);
            }
            $produto->imagem = $this->guardarImagem($loja, $request->file('imagem'));
        }
        $produto->save();

        return redirect()->route('carteira.loja', ['aba' => 'produtos'])->with('ok', 'Produto salvo.');
    }

    public function removerProduto(Request $request, LojaProduto $produto): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja && $produto->loja_id === $loja->id, 404);
        if ($produto->imagem) {
            $this->apagarImagem($loja, $produto->imagem);
        }
        $produto->delete();

        return redirect()->route('carteira.loja', ['aba' => 'produtos'])->with('ok', 'Produto removido. Os pedidos antigos continuam com o nome e o preço dele.');
    }

    /** Marcar o pedido como pago, enviado ou cancelado (cancelar devolve o estoque). */
    public function statusPedido(Request $request, LojaPedido $pedido): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja && $pedido->loja_id === $loja->id, 404);
        $d = $request->validate(['status' => ['required', Rule::in(['pago', 'enviado', 'cancelado', 'aguardando'])]]);

        DB::transaction(function () use ($pedido, $d) {
            $p = LojaPedido::whereKey($pedido->id)->lockForUpdate()->first();
            if ($p->status === $d['status']) {
                return;
            }
            $devolver = $d['status'] === 'cancelado' && $p->status !== 'cancelado';
            $tirar = $p->status === 'cancelado' && $d['status'] !== 'cancelado';
            foreach ($devolver || $tirar ? $p->itens : [] as $i) {
                $q = LojaProduto::where('id', $i['id'] ?? 0)->where('loja_id', $p->loja_id)->whereNotNull('estoque');
                $devolver ? $q->increment('estoque', (int) $i['qtd']) : $q->update(['estoque' => DB::raw('CASE WHEN estoque > ' . (int) $i['qtd'] . ' THEN estoque - ' . (int) $i['qtd'] . ' ELSE 0 END')]);
            }
            $p->status = $d['status'];
            $p->save();
        });

        return back()->with('ok', 'Pedido ' . $pedido->codigo . ': ' . LojaPedido::STATUS[$d['status']] . '.');
    }

    /* ---------- apoio ---------- */

    private function loja(Request $request): ?Loja
    {
        return Loja::where('user_id', $request->user()->id)->first();
    }

    private function slugLivre(string $base): string
    {
        $base = substr(trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($base)), '-'), 0, 32) ?: 'minha-loja';
        if (strlen($base) < 3) {
            $base .= '-loja';
        }
        $slug = $base;
        for ($i = 2; Loja::where('slug', $slug)->exists() || in_array($slug, Loja::RESERVADOS, true); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    /** Guarda a imagem com nome aleatório (a extensão vem do tipo real do arquivo, não do nome enviado). */
    private function guardarImagem(Loja $loja, UploadedFile $arq): string
    {
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$arq->getMimeType()] ?? 'jpg';
        $nome = Str::random(24) . '.' . $ext;
        $arq->storeAs('lojas/' . $loja->id, $nome, 'local');

        return $nome;
    }

    private function apagarImagem(Loja $loja, string $nome): void
    {
        if (preg_match('/^[A-Za-z0-9]{24}\.(jpg|png|webp)$/', $nome)) {
            Storage::disk('local')->delete('lojas/' . $loja->id . '/' . $nome);
        }
    }
}
