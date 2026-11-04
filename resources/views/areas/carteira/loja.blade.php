@extends('layouts.app')

@section('titulo', 'Loja')
@section('subtitulo', 'Negócios · sua loja virtual: produtos, cupons, entrega, aparência, pagamento e pedidos.')

@section('conteudo')
@php
    use App\Models\LojaPedido;
    use App\Support\Pix;
    $v = $loja?->visual() ?? [];
    $img = fn ($a) => $a && $loja ? route('loja.img', [$loja->slug, $a]) : null;
    $limite = $loja?->limiteProdutos();
    $brl = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
    $abas = ['pedidos' => 'Pedidos', 'produtos' => 'Produtos', 'cupons' => 'Cupons', 'entrega' => 'Entrega', 'aparencia' => 'Aparência', 'pix' => 'Pagamento'];
@endphp
<style>
    .lj-abas { display: flex; flex-wrap: wrap; gap: .35rem; border-bottom: 1px solid var(--linha); margin-bottom: 1rem; }
    .lj-aba { padding: .55rem .9rem; font-size: .875rem; font-weight: 600; color: var(--tinta-2); border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .lj-aba:hover { color: var(--tinta); }
    .lj-aba.ativa { color: var(--prim-forte); border-color: var(--prim); }
    .lj-grade { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
    .lj-campo { display: block; font-size: .85rem; }
    .lj-campo > span { display: block; color: var(--tinta-2); font-size: .75rem; margin-bottom: .2rem; }
    .lj-foto { width: 4rem; height: 4rem; border-radius: .5rem; object-fit: cover; background: var(--superficie-2); border: 1px solid var(--linha); flex-shrink: 0; }
    .lj-st { font-size: .72rem; padding: .1rem .5rem; border-radius: 9999px; border: 1px solid var(--linha); white-space: nowrap; }
    .lj-st.aguardando { color: #fbbf24; } .lj-st.informado { color: #38bdf8; } .lj-st.pago { color: #4ade80; } .lj-st.enviado { color: #a78bfa; } .lj-st.cancelado { color: var(--tinta-2); }
    .lj-cor { width: 2.5rem; height: 2rem; padding: 0; border: 1px solid var(--linha); border-radius: .4rem; background: none; cursor: pointer; }
</style>

<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('areas.negocios') }}">← Negócios</a>
        @if ($loja)
            <a class="btn !py-1.5 !px-3 text-sm" href="{{ route('loja.ver', $loja->slug) }}" target="_blank" rel="noopener">{{ $loja->publicada ? 'Ver a loja' : 'Ver prévia' }} ↗</a>
            <span class="lj-st {{ $loja->publicada ? 'pago' : 'aguardando' }}">{{ $loja->publicada ? 'Aberta ao público' : 'Fechada (só você vê)' }}</span>
            <span class="text-xs texto-2 break-all">{{ route('loja.ver', $loja->slug) }}</span>
            @if (! $loja->recebePedidos())
                <a href="{{ route('carteira.loja', ['aba' => 'pix']) }}" class="lj-st aguardando">Falta a forma de pagamento (Pix ou Mercado Pago): sem ela a loja não recebe pedidos</a>
            @endif
        @endif
    </div>

    @if ($errors->any())
        <div class="card p-3 text-sm" role="alert" style="color:#ef4444">{{ $errors->first() }}</div>
    @endif

    @if ($loja)
        <nav class="lj-abas" aria-label="Partes da loja">
            @foreach ($abas as $k => $nome)
                <a href="{{ route('carteira.loja', ['aba' => $k]) }}" class="lj-aba {{ $aba === $k ? 'ativa' : '' }}" @if ($aba === $k) aria-current="page" @endif>
                    {{ $nome }}
                    @if ($k === 'pedidos' && ($contagem['informado'] ?? 0)) <span class="lj-st informado">{{ $contagem['informado'] }}</span> @endif
                    @if ($k === 'produtos') <span class="texto-2 font-normal">({{ $produtos->count() }}{{ $limite !== null ? ' de ' . $limite : '' }})</span> @endif
                </a>
            @endforeach
        </nav>
    @endif

    {{-- ===================== Pedidos ===================== --}}
    @if ($loja && $aba === 'pedidos')
        <nav class="flex flex-wrap gap-2" aria-label="Filtrar pedidos">
            <a href="{{ route('carteira.loja', ['aba' => 'pedidos']) }}" class="btn-sec !py-1 {{ ! $status ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">Todos</a>
            @foreach (LojaPedido::STATUS as $k => $nome)
                <a href="{{ route('carteira.loja', ['aba' => 'pedidos', 'status' => $k]) }}" class="btn-sec !py-1 {{ $status === $k ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">{{ $nome }} ({{ $contagem[$k] ?? 0 }})</a>
            @endforeach
        </nav>
        @forelse ($pedidos as $p)
            @php $zap = preg_replace('/\D/', '', $p->cliente_contato); @endphp
            <article class="card p-4 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="lj-st {{ $p->status }}">{{ LojaPedido::STATUS[$p->status] ?? $p->status }}</span>
                    <b>{{ $brl($p->total) }}</b>
                    <span class="texto-2 text-xs">pedido <span class="font-mono">{{ $p->codigo }}</span> · {{ $p->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i') }}</span>
                </div>
                <p class="text-sm">
                    <b>{{ $p->cliente_nome }}</b> ·
                    @if (strlen($zap) >= 10 && ! str_contains($p->cliente_contato, '@'))
                        <a class="underline" href="https://wa.me/{{ strlen($zap) <= 11 ? '55' . $zap : $zap }}?text={{ rawurlencode('Olá! Sobre o seu pedido ' . $p->codigo . ' na ' . $loja->nome) }}" target="_blank" rel="noopener">{{ $p->cliente_contato }} (WhatsApp)</a>
                    @elseif (filter_var($p->cliente_contato, FILTER_VALIDATE_EMAIL))
                        <a class="underline" href="mailto:{{ $p->cliente_contato }}">{{ $p->cliente_contato }}</a>
                    @else
                        {{ $p->cliente_contato }}
                    @endif
                </p>
                @if ($p->cliente_endereco) <p class="text-sm texto-2 whitespace-pre-line">{{ $p->cliente_endereco }}</p> @endif
                @if ($p->observacao) <p class="text-sm texto-2">Obs.: {{ $p->observacao }}</p> @endif
                <ul class="text-sm list-disc pl-5">
                    @foreach ($p->itens as $i)
                        <li>{{ $i['qtd'] }} × {{ $i['nome'] }} <span class="texto-2">({{ $brl($i['preco']) }})</span></li>
                    @endforeach
                </ul>
                @if ((float) $p->desconto > 0 || $p->frete_nome)
                    <p class="text-xs texto-2">
                        @if ($p->subtotal !== null) Produtos {{ $brl($p->subtotal) }} @endif
                        @if ((float) $p->desconto > 0) · cupom {{ $p->cupom }} −{{ $brl($p->desconto) }} @endif
                        @if ($p->frete_nome) · {{ $p->frete_nome }} {{ (float) $p->frete > 0 ? $brl($p->frete) : 'grátis' }} @endif
                    </p>
                @endif
                @if ($p->pagamento === 'mp') <p class="text-xs texto-2">Pagamento por Mercado Pago{{ $p->mp_pagamento ? ' (aprovado, nº ' . $p->mp_pagamento . ')' : ': aguardando o cliente concluir' }}.</p> @endif
                @if ($p->informado_em) <p class="text-xs texto-2">O cliente avisou que pagou em {{ $p->informado_em->tz('America/Sao_Paulo')->format('d/m H:i') }}. Confira no extrato do banco.</p> @endif
                <div class="flex flex-wrap gap-2">
                    @foreach (['pago' => 'Marcar como pago', 'enviado' => 'Marcar como enviado', 'cancelado' => 'Cancelar pedido', 'aguardando' => 'Voltar para aguardando'] as $st => $rot)
                        @continue($p->status === $st || ($st === 'aguardando' && $p->status !== 'cancelado'))
                        <form method="POST" action="{{ route('carteira.loja.pedidos.status', $p) }}" @if ($st === 'cancelado') onsubmit="return confirm('Cancelar este pedido? O estoque dos produtos volta.')" @endif>
                            @csrf
                            <input type="hidden" name="status" value="{{ $st }}">
                            <button type="submit" class="{{ $st === 'pago' ? 'btn' : 'btn-sec' }} !py-1 text-sm">{{ $rot }}</button>
                        </form>
                    @endforeach
                </div>
            </article>
        @empty
            <p class="texto-2 text-sm">Nenhum pedido por aqui ainda. Divulgue o endereço da loja para começar a vender.</p>
        @endforelse
        {{ $pedidos->links() }}
    @endif

    {{-- ===================== Produtos ===================== --}}
    @if ($loja && $aba === 'produtos')
        @if ($limite !== null)
            <p class="text-xs texto-2">O plano Gratuito mostra até {{ $limite }} produtos. <a class="underline" href="{{ route('planos') }}">Assine o Básico</a> para ter produtos sem limite e tirar a marca do Work Flower do rodapé.</p>
        @endif

        <details class="card p-4" @if ($produtos->isEmpty() || old('nome')) open @endif>
            <summary class="cursor-pointer font-semibold">+ Novo produto</summary>
            @include('areas.carteira.partials.loja-produto', ['p' => null, 'img' => $img])
        </details>

        @foreach ($produtos as $p)
            <details class="card p-4">
                <summary class="cursor-pointer flex items-center gap-3 list-none">
                    @if ($p->imagem) <img src="{{ $img($p->imagem) }}" alt="" class="lj-foto"> @else <span class="lj-foto"></span> @endif
                    <span class="min-w-0 flex-1">
                        <b class="block truncate">{{ $p->nome }}</b>
                        <span class="text-sm">{{ $brl($p->preco) }}</span>
                        @if ($p->preco_antigo) <s class="text-xs texto-2">{{ $brl($p->preco_antigo) }}</s> @endif
                        <span class="text-xs texto-2">· {{ $p->estoque === null ? 'sem controle de estoque' : $p->estoque . ' em estoque' }}{{ $p->ativo ? '' : ' · escondido' }}</span>
                    </span>
                    <span class="text-xs texto-2">Editar</span>
                </summary>
                @include('areas.carteira.partials.loja-produto', ['p' => $p, 'img' => $img])
                <form method="POST" action="{{ route('carteira.loja.produtos.remover', $p) }}" class="mt-2" onsubmit="return confirm('Remover este produto da loja?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs underline" style="color:#ef4444">Remover produto</button>
                </form>
            </details>
        @endforeach
    @endif

    {{-- ===================== Cupons ===================== --}}
    @if ($loja && $aba === 'cupons')
        <p class="text-sm texto-2">O cliente digita o código no carrinho. O desconto é conferido de novo na hora do pedido (validade, limite de usos e pedido mínimo).</p>
        @foreach ([null, ...$cupons] as $c)
            <form method="POST" action="{{ $c ? route('carteira.loja.cupons.salvar', $c) : route('carteira.loja.cupons.criar') }}" class="card p-4 space-y-3">
                @csrf
                <div class="flex flex-wrap items-center gap-2">
                    <b>{{ $c ? $c->codigo : '+ Novo cupom' }}</b>
                    @if ($c) <span class="lj-st {{ $c->erro(PHP_FLOAT_MAX) ? 'cancelado' : 'pago' }}">{{ $c->rotulo() }}</span>
                        <span class="text-xs texto-2">usado {{ $c->usos }}{{ $c->usos_max ? ' de ' . $c->usos_max : '' }} {{ $c->usos === 1 ? 'vez' : 'vezes' }}</span> @endif
                </div>
                <div class="lj-grade">
                    <label class="lj-campo"><span>Código</span><input name="codigo" class="campo uppercase" maxlength="30" required value="{{ $c?->codigo }}" placeholder="Ex.: BEMVINDO10"></label>
                    <label class="lj-campo"><span>Tipo</span>
                        <select name="tipo" class="campo"><option value="pct" @selected(($c->tipo ?? 'pct') === 'pct')>Porcentagem (%)</option><option value="valor" @selected(($c->tipo ?? '') === 'valor')>Valor fixo (R$)</option></select>
                    </label>
                    <label class="lj-campo"><span>Desconto</span><input name="valor" type="number" step="0.01" min="0.01" class="campo" required value="{{ $c ? (float) $c->valor : '' }}"></label>
                    <label class="lj-campo"><span>Pedido mínimo (R$, opcional)</span><input name="minimo" type="number" step="0.01" min="0" class="campo" value="{{ $c && $c->minimo !== null ? (float) $c->minimo : '' }}"></label>
                    <label class="lj-campo"><span>Limite de usos (opcional)</span><input name="usos_max" type="number" min="1" class="campo" value="{{ $c?->usos_max }}"></label>
                    <label class="lj-campo"><span>Vale até (opcional)</span><input name="validade" type="date" class="campo" value="{{ $c?->validade?->format('Y-m-d') }}"></label>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="ativo" value="1" @checked($c ? $c->ativo : true)> Valendo</label>
                    <button type="submit" class="btn !py-1 text-sm">{{ $c ? 'Salvar' : 'Criar cupom' }}</button>
                    @if ($c)
                        <button type="submit" form="tirar-cupom-{{ $c->id }}" class="text-xs underline" style="color:#ef4444">Apagar</button>
                    @endif
                </div>
            </form>
            @if ($c)
                <form method="POST" action="{{ route('carteira.loja.cupons.remover', $c) }}" id="tirar-cupom-{{ $c->id }}" onsubmit="return confirm('Apagar o cupom {{ $c->codigo }}?')" hidden>@csrf @method('DELETE')</form>
            @endif
        @endforeach
    @endif

    {{-- ===================== Entrega ===================== --}}
    @if ($loja && $aba === 'entrega')
        @php $fr = $loja->fretes(); @endphp
        <form method="POST" action="{{ route('carteira.loja.entrega') }}" class="card p-4 space-y-3">
            @csrf
            <p class="text-sm texto-2">Cada linha vira uma opção que o cliente escolhe no carrinho, com o preço somado ao pedido. Deixe o nome em branco para tirar a linha. Sem nenhuma opção, o cliente não escolhe entrega.</p>
            <div class="space-y-2">
                @for ($i = 0; $i < \App\Models\Loja::MAX_FRETES; $i++)
                    @php $x = $fr[$i] ?? null; @endphp
                    <div class="grid gap-2 sm:grid-cols-3" @if (! $x && $i > count($fr)) data-extra hidden @endif>
                        <input name="fretes[{{ $i }}][nome]" class="campo" maxlength="60" value="{{ $x['nome'] ?? '' }}" placeholder="{{ ['Retirada na loja', 'Entrega no bairro', 'Correios (PAC)'][$i] ?? 'Outra opção' }}" aria-label="Nome da opção {{ $i + 1 }}">
                        <input name="fretes[{{ $i }}][preco]" type="number" step="0.01" min="0" class="campo" value="{{ $x ? $x['preco'] : '' }}" placeholder="Preço (R$)" aria-label="Preço da opção {{ $i + 1 }}">
                        <input name="fretes[{{ $i }}][gratis_acima]" type="number" step="0.01" min="0" class="campo" value="{{ $x['gratis_acima'] ?? '' }}" placeholder="Grátis a partir de (R$, opcional)" aria-label="Grátis a partir de, opção {{ $i + 1 }}">
                    </div>
                @endfor
            </div>
            <button type="button" class="btn-sec !py-1 text-sm" onclick="const l = this.form.querySelector('[data-extra][hidden]'); if (l) l.hidden = false; else this.disabled = true">+ Mais uma opção</button>
            <label class="lj-campo"><span>Aviso sobre entrega e retirada (aparece no carrinho)</span><input name="entrega" class="campo" maxlength="300" value="{{ $v['entrega'] ?? '' }}" placeholder="Ex.: Entregamos de terça a sábado, das 9h às 18h"></label>
            <button type="submit" class="btn">Salvar entrega</button>
        </form>
    @endif

    {{-- ===================== Aparência (e criação da loja) ===================== --}}
    @if (! $loja || $aba === 'aparencia')
        @unless ($loja)
            <div class="card p-4 text-sm space-y-1">
                <p class="font-semibold">Monte sua loja virtual</p>
                <p class="texto-2">Escolha o nome e o endereço, cadastre os produtos e a chave Pix. Os clientes pagam direto na sua conta e os pedidos aparecem aqui.</p>
            </div>
        @endunless
        <form method="POST" action="{{ route('carteira.loja.salvar') }}" enctype="multipart/form-data" class="card p-4 space-y-4">
            @csrf
            <div class="lj-grade">
                <label class="lj-campo"><span>Nome da loja</span><input name="nome" class="campo" maxlength="80" required value="{{ old('nome', $loja->nome ?? auth()->user()->name) }}"></label>
                <label class="lj-campo"><span>Endereço: {{ url('/loja') }}/…</span><input name="slug" class="campo" maxlength="40" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $loja->slug ?? $sugestao) }}" title="Letras minúsculas, números e hífen"></label>
                <label class="lj-campo"><span>Frase do topo</span><input name="chamada" class="campo" maxlength="160" value="{{ old('chamada', $loja->chamada ?? '') }}" placeholder="Ex.: Doces caseiros feitos na hora"></label>
                <label class="lj-campo"><span>Texto do botão de compra</span><input name="botao" class="campo" maxlength="20" value="{{ old('botao', $v['botao'] ?? 'Comprar') }}"></label>
            </div>
            <label class="lj-campo"><span>Sobre a loja (aparece no fim da página)</span><textarea name="sobre" class="campo" rows="3" maxlength="3000">{{ old('sobre', $loja->sobre ?? '') }}</textarea></label>


            <div class="lj-grade">
                @foreach (['cor' => ['Cor principal', '#2f6fc7'], 'fundo' => ['Fundo', '#f7f5f0'], 'texto' => ['Texto', '#1f2328'], 'cartao' => ['Cartões dos produtos', '#ffffff']] as $k => [$rot, $pad])
                    <label class="lj-campo" style="display:flex;align-items:center;gap:.5rem"><input type="color" name="{{ $k }}" class="lj-cor" value="{{ old($k, $v[$k] ?? $pad) }}"> <span class="!mb-0">{{ $rot }}</span></label>
                @endforeach
            </div>
            <div class="lj-grade">
                <label class="lj-campo"><span>Letra</span>
                    <select name="fonte" class="campo">@foreach (\App\Models\Loja::FONTES as $k => $n)<option value="{{ $k }}" @selected(old('fonte', $v['fonte'] ?? 'sans') === $k)>{{ $n }}</option>@endforeach</select>
                </label>
                <label class="lj-campo"><span>Vitrine</span>
                    <select name="layout" class="campo"><option value="grade" @selected(($v['layout'] ?? 'grade') === 'grade')>Grade de cartões</option><option value="lista" @selected(($v['layout'] ?? '') === 'lista')>Lista</option></select>
                </label>
                <label class="lj-campo"><span>Cantos</span>
                    <select name="cantos" class="campo">@foreach (['retos' => 'Retos', 'suaves' => 'Suaves', 'redondos' => 'Bem redondos'] as $k => $n)<option value="{{ $k }}" @selected(($v['cantos'] ?? 'suaves') === $k)>{{ $n }}</option>@endforeach</select>
                </label>
            </div>
            <div class="lj-grade">
                <label class="lj-campo"><span>WhatsApp da loja (com DDD)</span><input name="whatsapp" class="campo" maxlength="20" value="{{ old('whatsapp', $v['whatsapp'] ?? '') }}" inputmode="tel"></label>
                <label class="lj-campo"><span>Instagram</span><input name="instagram" class="campo" maxlength="40" value="{{ old('instagram', $v['instagram'] ?? '') }}" placeholder="@sualoja"></label>
            </div>
            <div class="lj-grade">
                <div class="lj-campo"><span>Logo (JPG, PNG ou WebP, até 3 MB)</span>
                    @if (! empty($v['logo'])) <div class="flex items-center gap-2 mb-1"><img src="{{ $img($v['logo']) }}" alt="Logo atual" class="lj-foto"><label class="text-xs"><input type="checkbox" name="tirar_logo" value="1"> tirar</label></div> @endif
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="text-sm">
                </div>
                <div class="lj-campo"><span>Imagem do topo (banner)</span>
                    @if (! empty($v['banner'])) <div class="flex items-center gap-2 mb-1"><img src="{{ $img($v['banner']) }}" alt="Banner atual" class="lj-foto" style="width:8rem"><label class="text-xs"><input type="checkbox" name="tirar_banner" value="1"> tirar</label></div> @endif
                    <input type="file" name="banner" accept="image/jpeg,image/png,image/webp" class="text-sm">
                </div>
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" name="estoque" value="1" @checked(old('estoque', $v['estoque'] ?? true))> Mostrar quantas unidades restam</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="publicada" value="1" @checked(old('publicada', $loja->publicada ?? false))> <b>Loja aberta ao público</b></label>
            </div>
            <button type="submit" class="btn">{{ $loja ? 'Salvar aparência' : 'Criar minha loja' }}</button>
        </form>
    @endif

    {{-- ===================== Pix ===================== --}}
    @if ($loja && $aba === 'pix')
        <form method="POST" action="{{ route('carteira.loja.pix') }}" class="card p-4 grid sm:grid-cols-2 gap-3">
            @csrf
            <p class="sm:col-span-2 text-sm texto-2"><b>Pix na sua chave</b> (sem taxa): os clientes pagam por Pix direto nesta chave: o dinheiro vai para a sua conta, sem passar pelo Work Flower. Confira os pagamentos no extrato do seu banco e marque o pedido como pago.</p>
            <label class="lj-campo"><span>Tipo de chave</span>
                <select name="tipo" class="campo">@foreach (Pix::TIPOS as $k => $n)<option value="{{ $k }}" @selected(old('tipo', $loja->pix_tipo) === $k)>{{ $n }}</option>@endforeach</select>
            </label>
            <label class="lj-campo"><span>Chave</span><input name="chave" class="campo" maxlength="77" required autocomplete="off" value="{{ old('chave', $loja->pix_chave) }}"></label>
            <label class="lj-campo"><span>Nome do titular (aparece no app do banco)</span><input name="nome" class="campo" maxlength="60" required value="{{ old('nome', $loja->pix_nome) }}"></label>
            <label class="lj-campo"><span>Cidade</span><input name="cidade" class="campo" maxlength="40" required value="{{ old('cidade', $loja->pix_cidade) }}"></label>
            <div class="sm:col-span-2"><button type="submit" class="btn">Salvar chave Pix</button></div>
        </form>

        <section class="card p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold">Cartão pelo Mercado Pago</h3>
                <span class="lj-st {{ $loja->mpPronto() ? 'pago' : 'cancelado' }}">{{ $loja->mpPronto() ? 'Conectado' . ($loja->mp_conta ? ': ' . $loja->mp_conta : '') : 'Não conectado' }}</span>
            </div>
            <p class="text-sm texto-2">Com o Mercado Pago, o cliente paga com cartão de crédito (até em parcelas), débito, Pix ou boleto na página deles, e o pedido é marcado como pago sozinho. O dinheiro cai na sua conta do Mercado Pago, com as taxas deles.</p>
            @if ($loja->mpPronto())
                <form method="POST" action="{{ route('carteira.loja.mp.sair') }}" onsubmit="return confirm('Desconectar o Mercado Pago? Os clientes voltam a pagar só por Pix.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-sec !py-1 text-sm">Desconectar</button>
                </form>
            @else
                <ol class="text-sm list-decimal pl-5 space-y-1">
                    <li>Entre em <a class="underline" href="https://www.mercadopago.com.br/developers/panel/app" target="_blank" rel="noopener">mercadopago.com.br/developers/panel/app</a> com a sua conta e crie uma aplicação (tipo “Pagamentos on-line”, produto “Checkout Pro”).</li>
                    <li>Na aplicação, abra <b>Credenciais de produção</b> e copie o <b>Access Token</b> (começa com APP_USR-).</li>
                    <li>Cole abaixo e salve. O Work Flower confere o token com o Mercado Pago e guarda criptografado.</li>
                </ol>
                <form method="POST" action="{{ route('carteira.loja.mp') }}" class="flex flex-wrap gap-2">
                    @csrf
                    <input name="token" type="password" class="campo flex-1 min-w-[16rem]" maxlength="200" required autocomplete="off" placeholder="APP_USR-..." aria-label="Access Token do Mercado Pago">
                    <button type="submit" class="btn">Conectar</button>
                </form>
            @endif
        </section>
    @endif
</div>
@endsection
