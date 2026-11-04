<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Models\LojaCupom;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Support\MercadoPago;
use App\Models\LojaResgate;
use App\Models\LojaSaldoMovimento;
use App\Support\Pix;
use App\Support\SaldoLoja;
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
    private const ABAS = ['pedidos', 'produtos', 'cupons', 'entrega', 'aparencia', 'pix'];

    public function show(Request $request): View
    {
        $loja = $this->loja($request);
        $aba = in_array($request->query('aba'), self::ABAS, true) ? $request->query('aba') : ($loja ? 'pedidos' : 'aparencia');
        $status = is_string($s = $request->query('status')) && isset(LojaPedido::STATUS[$s]) ? $s : null;
        $uid = $request->user()->id;
        $antiga = $loja ? null : Loja::onlyTrashed()->where('user_id', $uid)->first();

        return view('areas.carteira.loja', [
            'saldo'       => SaldoLoja::resumo($uid),
            'movimentos'  => $aba === 'pix' || ! $loja ? LojaSaldoMovimento::where('user_id', $uid)->latest('id')->limit(50)->get() : collect(),
            'resgates'    => $aba === 'pix' || ! $loja ? LojaResgate::where('user_id', $uid)->latest('id')->limit(20)->get() : collect(),
            'chaveResgate' => $loja ?? $antiga,
            'antiga'      => $antiga,
            'pixPlataforma' => Pix::pronto(),
            'loja'     => $loja,
            'aba'      => $aba,
            'status'   => $status,
            'produtos' => $loja ? $loja->produtos()->get() : collect(),
            'cupons'   => $loja ? $loja->cupons()->get() : collect(),
            'pedidos'  => $loja ? $loja->pedidos()->when($status, fn ($q) => $q->where('status', $status))->paginate(25)->withQueryString() : null,
            'contagem' => $loja ? $loja->pedidos()->reorder()->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status') : collect(),
            'sugestao' => $loja ? null : $this->slugLivre(Str::slug($request->user()->name) ?: 'minha-loja'),
        ]);
    }

    /** Nome, endereço, textos e aparência (cria a loja na primeira vez). */
    public function salvar(Request $request): RedirectResponse
    {
        // loja excluída antes: volta com o mesmo histórico (pedidos, produtos e saldo)
        $loja = $this->loja($request) ?? Loja::onlyTrashed()->where('user_id', $request->user()->id)->first();
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
        $nova = ! $loja->exists || $loja->trashed();
        if ($loja->trashed()) $loja->deleted_at = null;
        $loja->save(); // precisa do id para a pasta das imagens
        if ($nova) {
            // produtos do catálogo de Negócios entram na loja nova (aparecem os marcados "Mostrar na loja")
            LojaProduto::where('user_id', $loja->user_id)->whereNull('loja_id')->update(['loja_id' => $loja->id]);
        }

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

    /**
     * Chave Pix do dono para receber os RESGATES do saldo. Os clientes pagam na chave da plataforma (SaldoLoja);
     * esta chave só é usada quando o dono resgata (e nos pedidos antigos, feitos antes da mudança).
     */
    public function pix(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $d = $this->validarChave($request);
        if (! is_array($d)) return $d;
        $loja->forceFill(['pix_tipo' => $d['tipo'], 'pix_chave' => $d['chave'], 'pix_nome' => $d['nome'], 'pix_cidade' => $d['cidade']])->save();

        return redirect()->route('carteira.loja', ['aba' => 'pix'])->with('ok', 'Chave para resgates salva.');
    }

    /** "Resgatar": tira do saldo e pede o Pix para a chave do dono, pago pela plataforma em até 7 dias úteis. */
    public function resgatar(Request $request): RedirectResponse
    {
        $loja = Loja::withTrashed()->where('user_id', $request->user()->id)->first();
        abort_unless($loja, 404);
        $request->validate(['valor' => ['required', 'numeric', 'min:' . SaldoLoja::RESGATE_MINIMO, 'max:9999999']], [], ['valor' => 'valor do resgate']);
        $d = $this->validarChave($request);
        if (! is_array($d)) return $d;
        // a chave usada fica guardada para os próximos resgates
        $loja->forceFill(['pix_tipo' => $d['tipo'], 'pix_chave' => $d['chave'], 'pix_nome' => $d['nome'], 'pix_cidade' => $d['cidade']])->save();

        $r = SaldoLoja::pedirResgate($request->user()->id, (float) $request->input('valor'), $d);
        if (is_string($r)) return back()->withErrors(['valor' => $r])->withInput();

        return redirect()->route('carteira.loja', ['aba' => 'pix'])->with('ok', 'Resgate de R$ ' . number_format((float) $r->valor, 2, ',', '.')
            . ' pedido. O Pix cai na sua chave até ' . $r->prazo->format('d/m/Y') . ' (7 dias úteis).');
    }

    /**
     * Excluir a loja: a pessoa digita o nome da loja para confirmar. A página pública sai do ar, o endereço fica livre,
     * cupons, Mercado Pago, logo e banner são apagados. Pedidos, produtos (que continuam no catálogo de Negócios)
     * e o saldo ficam guardados; criar a loja de novo reaproveita o histórico.
     */
    public function excluir(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $request->validate(['confirmar_nome' => ['required', 'string', 'max:80']], [], ['confirmar_nome' => 'nome da loja']);
        if (mb_strtolower(trim($request->input('confirmar_nome'))) !== mb_strtolower(trim($loja->nome))) {
            return back()->withErrors(['confirmar_nome' => 'Digite o nome da loja exatamente como está: ' . $loja->nome])->withInput();
        }
        $abertos = $loja->pedidos()->whereIn('status', ['aguardando', 'informado'])->count();
        if ($abertos) {
            return back()->withErrors(['confirmar_nome' => "A loja tem {$abertos} " . ($abertos === 1 ? 'pedido' : 'pedidos') . ' esperando pagamento. Cancele ou conclua antes de excluir: o cliente perde o link do pedido quando a loja sai do ar.'])->withInput();
        }

        DB::transaction(function () use ($loja) {
            $v = $loja->visual();
            foreach (['logo', 'banner'] as $k) {
                if (! empty($v[$k])) $this->apagarImagem($loja, $v[$k]);
            }
            $loja->cupons()->delete();
            $visual = is_array($loja->visual) ? $loja->visual : [];
            unset($visual['logo'], $visual['banner']);
            $loja->forceFill([
                'publicada' => false, 'mp_token' => null, 'mp_conta' => null, 'visual' => $visual,
                'slug' => 'x-' . $loja->id . '-' . strtolower(Str::random(8)), // libera o endereço para outra loja
            ])->save();
            $loja->delete();
        });

        return redirect()->route('areas.negocios')->with('ok', 'Loja "' . $loja->nome . '" excluída. Os pedidos e o saldo ficam guardados; os produtos continuam em Negócios › Produtos.');
    }

    /** @return array|RedirectResponse */
    private function validarChave(Request $request)
    {
        $d = $request->validate([
            'tipo'   => ['required', Rule::in(array_keys(Pix::TIPOS))],
            'chave'  => ['required', 'string', 'max:77'],
            'nome'   => ['required', 'string', 'max:60'],
            'cidade' => ['nullable', 'string', 'max:40'],
        ], [], ['chave' => 'chave Pix', 'nome' => 'nome do titular']);
        $d['chave'] = Pix::normalizarChave($d['tipo'], $d['chave']);
        if ($erro = Pix::erroChave($d['tipo'], $d['chave'])) {
            return back()->withErrors(['chave' => $erro])->withInput();
        }
        if (! preg_match('/[A-Za-z0-9]/', Str::ascii($d['nome']))) {
            return back()->withErrors(['nome' => 'Use o nome do titular com letras.'])->withInput();
        }
        $d['nome'] = trim($d['nome']);
        $d['cidade'] = trim((string) ($d['cidade'] ?? ''));

        return $d;
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
            'sku'          => ['nullable', 'string', 'max:40'],
            'custo'        => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'categoria'    => ['nullable', 'string', 'max:60'],
        ], [], ['preco' => 'preço', 'preco_antigo' => 'preço antigo']);

        $produto ??= new LojaProduto(['loja_id' => $loja->id]);
        $produto->loja_id = $loja->id;
        $produto->user_id = $loja->user_id;
        // campos do catálogo (Negócios › Produtos): só mudam se vierem no formulário
        foreach (['sku', 'categoria'] as $k) {
            if ($request->has($k)) $produto->{$k} = trim((string) ($d[$k] ?? '')) ?: null;
        }
        if ($request->has('custo')) $produto->custo = $request->filled('custo') ? round((float) $d['custo'], 2) : null;
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
        // Pix na chave da plataforma: só a administração confirma (é ela que vê o extrato) e o valor vira saldo
        if ($pedido->pixDaPlataforma()) {
            $pagoJa = in_array($pedido->status, ['pago', 'enviado'], true);
            if ($d['status'] === 'pago' && ! $pagoJa) {
                return back()->withErrors(['status' => 'Este Pix caiu na conta do Work Flower: a confirmação é feita pela plataforma, normalmente no mesmo dia útil. Quando confirmar, o pedido aparece como pago e o valor entra no seu saldo.']);
            }
            if ($d['status'] === 'enviado' && ! $pagoJa) {
                return back()->withErrors(['status' => 'Espere o pagamento ser confirmado antes de marcar como enviado.']);
            }
            if ($d['status'] === 'cancelado' && $pagoJa) {
                return back()->withErrors(['status' => 'Este pedido já foi pago e o valor entrou no seu saldo. Para devolver o dinheiro ao cliente, fale com o suporte do Work Flower.']);
            }
        }

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

    /** Cria ou edita um cupom de desconto. */
    public function salvarCupom(Request $request, ?LojaCupom $cupom = null): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        if ($cupom) abort_unless($cupom->loja_id === $loja->id, 404);
        elseif ($loja->cupons()->count() >= 100) return back()->withErrors(['codigo' => 'Limite de 100 cupons por loja. Apague os antigos.']);
        $request->merge(['codigo' => LojaCupom::normalizar($request->input('codigo'))]);
        $d = $request->validate([
            'codigo'   => ['required', 'string', 'min:3', 'max:30', Rule::unique('loja_cupons', 'codigo')->where('loja_id', $loja->id)->ignore($cupom?->id)],
            'tipo'     => ['required', Rule::in(['pct', 'valor'])],
            'valor'    => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'minimo'   => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'usos_max' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'validade' => ['nullable', 'date'],
            'ativo'    => ['nullable', 'boolean'],
        ], [], ['codigo' => 'código do cupom', 'usos_max' => 'limite de usos']);
        if ($d['tipo'] === 'pct' && (float) $d['valor'] > 100) return back()->withErrors(['valor' => 'A porcentagem vai até 100.'])->withInput();

        $cupom ??= new LojaCupom();
        $cupom->loja_id = $loja->id;
        $cupom->fill([
            'codigo' => $d['codigo'], 'tipo' => $d['tipo'], 'valor' => round((float) $d['valor'], 2),
            'minimo' => $request->filled('minimo') ? round((float) $d['minimo'], 2) : null,
            'usos_max' => $request->filled('usos_max') ? (int) $d['usos_max'] : null,
            'validade' => $d['validade'] ?? null, 'ativo' => $request->boolean('ativo'),
        ])->save();

        return redirect()->route('carteira.loja', ['aba' => 'cupons'])->with('ok', 'Cupom ' . $cupom->codigo . ' salvo.');
    }

    public function removerCupom(Request $request, LojaCupom $cupom): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja && $cupom->loja_id === $loja->id, 404);
        $cupom->delete();

        return redirect()->route('carteira.loja', ['aba' => 'cupons'])->with('ok', 'Cupom removido.');
    }

    /** Opções de entrega: nome, preço e "grátis a partir de" (linhas em branco são ignoradas). */
    public function entrega(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $d = $request->validate([
            'fretes'                => ['nullable', 'array', 'max:' . Loja::MAX_FRETES],
            'fretes.*.nome'         => ['nullable', 'string', 'max:60'],
            'fretes.*.preco'        => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'fretes.*.gratis_acima' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'entrega'               => ['nullable', 'string', 'max:300'],
        ]);
        $v = is_array($loja->visual) ? $loja->visual : [];
        $v['fretes'] = array_values(array_filter(array_map(fn ($f) => trim((string) ($f['nome'] ?? '')) === '' ? null : [
            'nome' => trim($f['nome']), 'preco' => round((float) ($f['preco'] ?? 0), 2),
            'gratis_acima' => ($f['gratis_acima'] ?? '') !== '' && $f['gratis_acima'] !== null ? round((float) $f['gratis_acima'], 2) : null,
        ], $d['fretes'] ?? [])));
        $v['entrega'] = trim((string) ($d['entrega'] ?? '')) ?: null;
        $loja->visual = $v;
        $loja->save();

        return redirect()->route('carteira.loja', ['aba' => 'entrega'])->with('ok', $v['fretes'] ? 'Opções de entrega salvas.' : 'Sem opções de entrega: o cliente não escolhe frete.');
    }

    /** Conecta a conta do Mercado Pago da pessoa pelo Access Token de produção (conferido na API antes de salvar). */
    public function mercadoPago(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $d = $request->validate(['token' => ['required', 'string', 'min:20', 'max:200', 'regex:/^(APP_USR|TEST)-[A-Za-z0-9-]+$/']],
            ['token.regex' => 'Cole o Access Token inteiro: ele começa com APP_USR- (produção) ou TEST- (teste).'], ['token' => 'Access Token']);
        $conta = MercadoPago::conta(trim($d['token']));
        if (! $conta) return back()->withErrors(['token' => 'O Mercado Pago não aceitou este Access Token. Confira se copiou o de produção inteiro.']);
        $loja->forceFill(['mp_token' => trim($d['token']), 'mp_conta' => mb_substr($conta['nome'], 0, 120)])->save();

        return redirect()->route('carteira.loja', ['aba' => 'pix'])->with('ok', 'Mercado Pago conectado (' . $conta['nome'] . '). Os clientes já podem pagar com cartão.');
    }

    public function desconectarMp(Request $request): RedirectResponse
    {
        $loja = $this->loja($request);
        abort_unless($loja, 404);
        $loja->forceFill(['mp_token' => null, 'mp_conta' => null])->save();

        return redirect()->route('carteira.loja', ['aba' => 'pix'])->with('ok', 'Mercado Pago desconectado. A loja volta a receber só por Pix.');
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
        for ($i = 2; Loja::withTrashed()->where('slug', $slug)->exists() || in_array($slug, Loja::RESERVADOS, true); $i++) {
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
