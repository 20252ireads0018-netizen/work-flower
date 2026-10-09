@extends('layouts.app')

@section('titulo', $area['nome'])
@section('subtitulo', $area['descricao'])

@section('conteudo')
@php
    $cfg = [
        'url'     => url('/carteira/dados'),
        'cotacao' => route('carteira.cotacao'),
        'dados'   => (object) $dados->all(),
        'tarefas' => $tarefasJs,
    ];
@endphp

<style>
    .wf-quadro { position: relative; width: 100%; isolation: isolate; }
    .wf-item {
        position: absolute; left: var(--x); top: var(--y); width: var(--w); height: var(--h);
        display: flex; flex-direction: column; overflow: hidden;
        background: var(--superficie); border: 1px solid var(--linha); border-top: 2px solid var(--wc);
        border-radius: .75rem; transition: box-shadow .2s;
    }
    .wf-item.arrastando { box-shadow: 0 14px 36px rgba(0,0,0,.5); user-select: none; }
    .wf-topo { display: flex; align-items: center; gap: .5rem; padding: .55rem .75rem; background: var(--superficie-2); border-bottom: 1px solid var(--linha); cursor: grab; touch-action: none; }
    .wf-item.arrastando .wf-topo { cursor: grabbing; }
    .wf-corpo { flex: 1; min-height: 0; overflow: auto; padding: 1rem; }
    .wf-redim {
        position: absolute; right: 3px; bottom: 3px; width: 16px; height: 16px; cursor: nwse-resize; touch-action: none; opacity: .7;
        background: linear-gradient(135deg, transparent 52%, var(--tinta-2) 52%, var(--tinta-2) 60%, transparent 60%, transparent 72%, var(--tinta-2) 72%, var(--tinta-2) 80%, transparent 80%);
    }
    .wf-cor { width: 1.1rem; height: 1.1rem; padding: 0; border: 0; border-radius: 9999px; background: none; cursor: pointer; overflow: hidden; flex-shrink: 0; }
    .wf-cor::-webkit-color-swatch-wrapper { padding: 0; }
    .wf-cor::-webkit-color-swatch { border: 0; border-radius: 9999px; }
    .wf-cor::-moz-color-swatch { border: 0; border-radius: 9999px; }
    .wf-titulo { background: transparent; border: 0; min-width: 0; color: var(--tinta); font-weight: 600; font-family: 'Bricolage Grotesque','DM Sans',sans-serif; padding: .15rem .3rem; border-radius: .375rem; }
    .wf-titulo:hover { background: var(--superficie); }
    .wf-titulo:focus { outline: none; background: var(--superficie); box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-cel { width: 100%; min-width: 5rem; background: transparent; color: var(--tinta); border: 0; padding: .35rem .4rem; font-size: .85rem; border-radius: .375rem; }
    .wf-cel:focus { outline: none; background: var(--superficie-2); box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-tab { width: 100%; border-collapse: collapse; }
    .wf-tab th, .wf-tab td { border: 1px solid var(--linha); padding: 0; }
    .wf-tab th { background: var(--superficie-2); text-align: left; }
    .wf-link-sel { padding: .25rem 2rem .25rem .5rem; font-size: .75rem; width: auto; }

    .chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .6rem; border-radius: 9999px; font-size: .75rem; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta-2); transition: border-color .15s, color .15s, background-color .15s; }
    .chip:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .chip.ativo { background: var(--prim); border-color: var(--prim); color: var(--on-prim); }

    /* mapa mental */
    .wf-mapa { height: 100%; overflow: auto; }
    .wf-no { position: absolute; width: 188px; height: 38px; display: flex; align-items: center; gap: .15rem; padding: 0 .3rem; background: var(--superficie-2); border: 1px solid var(--no); border-radius: .5rem; }
    .wf-no-grip { cursor: grab; color: var(--tinta-2); padding: 0 .2rem; touch-action: none; user-select: none; }
    .wf-no-t { flex: 1; min-width: 0; background: transparent; border: 0; color: var(--tinta); font-size: .8rem; padding: .2rem; border-radius: .25rem; }
    .wf-no-t:focus { outline: none; background: var(--superficie); }
    .wf-ponto { width: .65rem; height: .65rem; border-radius: 9999px; background: var(--no); flex-shrink: 0; }

    /* Carteira */
    .wf-stat { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .6rem; padding: .75rem 1rem; }
    .wf-conta { border: 1px solid var(--linha); border-left: 4px solid var(--c); border-radius: .6rem; padding: .6rem .8rem; background: var(--superficie-2); }
    .wf-hbar { height: .5rem; border-radius: 9999px; background: var(--superficie-2); overflow: hidden; }
    .wf-hbar > span { display: block; height: 100%; background: var(--prim); border-radius: 9999px; }
    .wf-linha { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .5rem .75rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); font-size: .85rem; }
    .wf-tabela { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .wf-tabela th { text-align: left; font-weight: 500; color: var(--tinta-2); padding: .4rem .5rem; border-bottom: 1px solid var(--linha); white-space: nowrap; }
    .wf-tabela td { padding: .45rem .5rem; border-bottom: 1px solid var(--linha); vertical-align: middle; }
    .wf-num { width: 6rem; padding: .3rem .5rem; }
    .wf-grafico { display: flex; align-items: flex-end; gap: .4rem; height: 7rem; }
    .wf-grafico .col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; gap: .25rem; }
    .wf-grafico .col > div { width: 100%; min-height: 2px; border-radius: .25rem .25rem 0 0; background: var(--prim); }
    .wf-grafico .col > div.neg { background: #f87171; }
    .wf-grafico small { font-size: .65rem; color: var(--tinta-2); }
    .wf-kanban { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; height: 100%; }
    .wf-col-k { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .6rem; padding: .6rem; display: flex; flex-direction: column; gap: .5rem; overflow: auto; }
    .wf-proj { background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem .6rem; }
    .wf-previa { background: #fff; border-radius: .5rem; overflow: auto; height: calc(100% - 2.5rem); }

    /* tabela estilo planilha (js/tabela.js) */
    .tb-wrap { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 0; }
    .tb-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .tb-btn { display: inline-flex; align-items: center; justify-content: center; padding: .2rem .5rem; font-size: .75rem; line-height: 1.2; border: 1px solid var(--linha); border-radius: .375rem; background: var(--superficie-2); color: var(--tinta-2); transition: border-color .15s, color .15s; }
    .tb-btn:hover:not(:disabled) { border-color: var(--prim-linha); color: var(--prim-forte); }
    .tb-btn:disabled { opacity: .4; cursor: default; }
    .tb-btn.perigo:hover:not(:disabled) { border-color: #f87171; color: #f87171; }
    .tb-sep { width: 1px; align-self: stretch; background: var(--linha); margin: 0 .15rem; }
    .tb-formula { display: flex; align-items: center; gap: .4rem; }
    .tb-ref { min-width: 3.5rem; text-align: center; font-size: .75rem; padding: .3rem .4rem; border: 1px solid var(--linha); border-radius: .375rem; background: var(--superficie-2); color: var(--tinta); }
    .tb-scroll { flex: 1; min-height: 8rem; overflow: auto; border: 1px solid var(--linha); border-radius: .5rem; }
    .tb { border-collapse: separate; border-spacing: 0; table-layout: fixed; font-size: .85rem; }
    .tb th, .tb td { border-right: 1px solid var(--linha); border-bottom: 1px solid var(--linha); padding: 0; }
    .tb thead th { position: sticky; top: 0; z-index: 2; background: var(--superficie-2); text-align: left; }
    .tb thead th.sel, .tb .tb-num.sel { background: var(--prim-linha); }
    .tb .tb-num { position: sticky; left: 0; z-index: 1; width: 44px; text-align: center; background: var(--superficie-2); color: var(--tinta-2); font-size: .7rem; font-weight: 400; cursor: pointer; user-select: none; }
    .tb thead .tb-canto { z-index: 3; }
    .tb-cab { position: relative; display: flex; align-items: center; }
    .tb-letra { padding: 0 .4rem; font-size: .7rem; color: var(--tinta-2); cursor: pointer; user-select: none; }
    .tb-cab-in { flex: 1; min-width: 0; background: transparent; border: 0; color: var(--tinta); font-weight: 600; font-size: .8rem; padding: .35rem .3rem; }
    .tb-cab-in:focus { outline: none; background: var(--superficie); }
    .tb-res { position: absolute; right: -3px; top: 0; bottom: 0; width: 7px; cursor: col-resize; touch-action: none; }
    .tb-res:hover { background: var(--prim); opacity: .5; }
    .tb-cel { width: 100%; background: transparent; border: 0; color: var(--tinta); padding: .35rem .45rem; font-size: .85rem; }
    .tb-cel:focus { outline: 2px solid var(--prim); outline-offset: -2px; }
    .tb td.sel { background: var(--prim-linha); }
    .tb-rod { padding: .35rem .45rem !important; font-weight: 600; text-align: right; background: var(--superficie-2); }

    /* tela cheia (js/tela-cheia.js) */
    html.wf-fs-pagina { overflow: hidden; }
    .wf-quadro.wf-fs-ativo { isolation: auto; }
    .wf-item.wf-fs {
        position: fixed !important; left: 0 !important; top: 0 !important; right: 0 !important; bottom: 0 !important;
        width: 100% !important; height: 100% !important; max-width: none !important; max-height: none !important;
        z-index: 1000; border-radius: 0; background: var(--superficie);
    }
    .wf-item.wf-fs .wf-topo { cursor: default; touch-action: auto; }
    .wf-item.wf-fs .wf-redim { display: none; }
    .wf-item.wf-fs .wf-corpo { overflow: auto; }
    .wf-item.wf-fs .tb-scroll { max-height: none; }

    @media (max-width: 899px) {
        .wf-quadro { display: flex; flex-direction: column; gap: 1rem; min-height: 0 !important; height: auto !important; }
        .wf-item { position: static; width: auto; height: auto; }
        .wf-corpo { overflow: visible; }
        .wf-topo { cursor: default; touch-action: auto; }
        .wf-redim { display: none; }
        .wf-kanban { grid-template-columns: 1fr; }
        .wf-mapa { height: 24rem; }
        .tb-scroll { max-height: 70vh; }
        /* em tela cheia o cartão volta a cobrir a janela, mesmo no celular */
        .wf-item.wf-fs { position: fixed !important; width: 100% !important; height: 100% !important; }
        .wf-item.wf-fs .wf-corpo { overflow: auto; }
    }
</style>

<script type="application/json" id="carteira-cfg">{!! json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
{{-- Precisam vir antes do Alpine iniciar (o Alpine do layout usa defer).
     Ordem: quadro → documentos → carteira → tabela (envolve carteiraAbas) → tela cheia → arquivos --}}
<script src="{{ asset('js/quadro.js') }}?v={{ @filemtime(public_path('js/quadro.js')) }}"></script>
<script src="{{ asset('js/documentos.js') }}?v={{ @filemtime(public_path('js/documentos.js')) }}"></script>
<script src="{{ asset('js/carteira.js') }}?v={{ @filemtime(public_path('js/carteira.js')) }}"></script>
<script src="{{ asset('js/tabela.js') }}?v={{ @filemtime(public_path('js/tabela.js')) }}"></script>
<script src="{{ asset('js/tela-cheia.js') }}?v={{ @filemtime(public_path('js/tela-cheia.js')) }}"></script>
<script src="{{ asset('js/arquivos.js') }}?v={{ @filemtime(public_path('js/arquivos.js')) }}"></script>

<div x-data="carteiraAbas()" class="space-y-6">

    <datalist id="wf-canais">
        <template x-for="c in canais()" :key="c"><option :value="c"></option></template>
    </datalist>
    <datalist id="wf-conexoes">
        <option value="Mercado Livre"></option><option value="Shopee"></option><option value="Amazon"></option>
        <option value="Magalu"></option><option value="Meta Ads"></option><option value="Google Ads"></option>
        <option value="TikTok Ads"></option><option value="Pinterest Ads"></option>
    </datalist>

    {{-- Mini cards circulares --}}
    <nav class="flex flex-wrap gap-x-5 gap-y-4" aria-label="Seções de Carteira">
        <template x-for="a in abas" :key="a.id">
            <button type="button" class="circ" :class="{ 'ativo': aba === a.id }" @click="aba = a.id" :aria-pressed="aba === a.id" :title="a.nome">
                <span class="circ-bola">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="a.icone"></svg>
                </span>
                <span class="circ-rotulo" x-text="a.nome"></span>
            </button>
        </template>
    </nav>

    {{-- Barra de ferramentas --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs texto-2" x-text="rotuloSalvo()" aria-live="polite"></p>
        <div class="flex items-center gap-2">
            <button type="button" class="btn-sec" @click="reorganizar()">Reorganizar</button>
            <div class="relative" @click.outside="menuBloco = false" @keydown.escape.window="menuBloco = false">
                <button type="button" class="btn" @click="menuBloco = !menuBloco" aria-haspopup="menu" :aria-expanded="menuBloco">+ Bloco</button>
                <div x-show="menuBloco" x-cloak class="menu-pop absolute right-0 top-full mt-2 w-48 p-1.5 z-40" role="menu">
                    <template x-for="t in tiposBloco" :key="t.id">
                        <button type="button" class="menu-item" role="menuitem" @click="novoBloco(aba, t.id)" x-text="t.nome"></button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <p x-show="msg" x-cloak class="aviso-ok" role="status" x-text="msg"></p>

    <div x-ref="area" class="w-full">

        {{-- ================= CARTEIRA INDIVIDUAL ================= --}}
        <section class="wf-quadro" x-show="aba === 'carteira'" x-cloak :style="{ minHeight: alturaQuadro('carteira') + 'px' }">

            <x-carteira.item sec="'carteira'" bid="'cart-resumo'" titulo="Visão geral" :minw="320" :minh="120">
                <div x-data="{ get t() { return mesTotais() } }" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="wf-stat"><p class="text-xs texto-2">Saldo em todas as contas</p><p class="titulo text-2xl" x-text="brl(saldoTotal())"></p></div>
                    <div class="wf-stat"><p class="text-xs texto-2">Ganhos do mês</p><p class="titulo text-xl" style="color:#4ade80" x-text="brl(t.ganhos)"></p></div>
                    <div class="wf-stat"><p class="text-xs texto-2">Gastos do mês</p><p class="titulo text-xl" style="color:#f87171" x-text="brl(t.gastos)"></p></div>
                    <div class="wf-stat"><p class="text-xs texto-2">Resultado do mês</p><p class="titulo text-xl" :style="{ color: cor(t.saldo) }" x-text="brl(t.saldo)"></p></div>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'carteira'" bid="'cart-contas'" titulo="Contas e bancos" :minw="280" :minh="260">
                <div class="space-y-4">
                    <p x-show="!estado.contas.length" class="text-sm texto-2">Nenhuma conta ainda.</p>
                    <ul class="space-y-2">
                        <template x-for="c in estado.contas" :key="c.id">
                            <li class="wf-conta" :style="`--c:${c.cor}`">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate" x-text="c.nome"></p>
                                        <p class="text-xs texto-2 truncate" x-text="c.banco || 'Sem banco'"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-medium text-sm" :style="{ color: cor(saldoConta(c)) }" x-text="brl(saldoConta(c))"></span>
                                        <button type="button" class="mini-btn perigo" title="Excluir conta" @click="remConta(c)">✕</button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                    <div class="pt-3 space-y-2" style="border-top:1px solid var(--linha)">
                        <input class="campo" x-model="cc.nome" placeholder="Nome (ex.: Conta principal)">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="campo" x-model="cc.banco" placeholder="Banco (Nubank, Itaú…)">
                            <input type="number" step="0.01" class="campo" x-model="cc.inicial" placeholder="Saldo inicial">
                        </div>
                        <p class="erro" x-text="ccErro"></p>
                        <button type="button" class="btn" @click="addConta()">Adicionar conta</button>
                    </div>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'carteira'" bid="'cart-novo'" titulo="Novo lançamento" :minw="280" :minh="320">
                <div class="space-y-3">
                    <div class="flex gap-1.5">
                        <button type="button" class="chip" :class="{ 'ativo': ln.tipo === 'gasto' }" @click="ln.tipo = 'gasto'">Gasto</button>
                        <button type="button" class="chip" :class="{ 'ativo': ln.tipo === 'ganho' }" @click="ln.tipo = 'ganho'">Ganho</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="rotulo">Valor (R$)</label><input type="number" min="0" step="0.01" class="campo" x-model="ln.valor" @keydown.enter.prevent="registrar()"></div>
                        <div><label class="rotulo">Data</label><input type="date" class="campo" x-model="ln.d"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="rotulo">Categoria</label>
                            <select class="campo" x-model="ln.cat"><option value="">Outros</option>
                                <template x-for="c in estado.cats" :key="c"><option :value="c" x-text="c"></option></template>
                            </select></div>
                        <div><label class="rotulo">Conta</label>
                            <select class="campo" x-model="ln.conta"><option value="">Sem conta</option>
                                <template x-for="c in estado.contas" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                            </select></div>
                    </div>
                    <div><label class="rotulo">Descrição</label><input class="campo" x-model="ln.desc" placeholder="Opcional" @keydown.enter.prevent="registrar()"></div>
                    <p class="erro" x-text="lnErro"></p>
                    <button type="button" class="btn w-full" @click="registrar()">Registrar</button>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'carteira'" bid="'cart-cats'" titulo="Gastos por categoria" :minw="280" :minh="260">
                <div class="space-y-3">
                    <p x-show="!porCategoria().length" class="text-sm texto-2">Sem gastos no filtro do relatório.</p>
                    <template x-for="x in porCategoria()" :key="x.c">
                        <div>
                            <div class="flex justify-between text-sm"><span x-text="x.c"></span><span class="texto-2" x-text="brl(x.v)"></span></div>
                            <div class="wf-hbar mt-1"><span :style="`width:${x.pct}%`"></span></div>
                        </div>
                    </template>
                    <div class="pt-3 flex gap-2" style="border-top:1px solid var(--linha)">
                        <input class="campo" x-model="novaCat" placeholder="Nova categoria" @keydown.enter.prevent="addCat()">
                        <button type="button" class="btn-sec shrink-0" @click="addCat()">Criar</button>
                    </div>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'carteira'" bid="'cart-relatorio'" titulo="Relatório de gastos" :minw="420" :minh="260">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <div><label class="rotulo">Período</label>
                            <select class="campo" x-model="fl.per">
                                <option value="mes">Este mês</option><option value="30d">Últimos 30 dias</option>
                                <option value="ano">Este ano</option><option value="tudo">Tudo</option>
                            </select></div>
                        <div><label class="rotulo">Tipo</label>
                            <select class="campo" x-model="fl.tipo">
                                <option value="todos">Todos</option><option value="gasto">Gastos</option><option value="ganho">Ganhos</option>
                            </select></div>
                        <div><label class="rotulo">Categoria</label>
                            <select class="campo" x-model="fl.cat"><option value="">Todas</option>
                                <template x-for="c in estado.cats" :key="c"><option :value="c" x-text="c"></option></template>
                            </select></div>
                        <div><label class="rotulo">Conta</label>
                            <select class="campo" x-model="fl.conta"><option value="">Todas</option>
                                <template x-for="c in estado.contas" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                            </select></div>
                    </div>
                    <div x-data="{ get t() { return totais(filtrados()) } }" class="grid grid-cols-3 gap-3">
                        <div class="wf-stat"><p class="text-xs texto-2">Ganhos</p><p class="font-medium" style="color:#4ade80" x-text="brl(t.ganhos)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Gastos</p><p class="font-medium" style="color:#f87171" x-text="brl(t.gastos)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Saldo</p><p class="font-medium" :style="{ color: cor(t.saldo) }" x-text="brl(t.saldo)"></p></div>
                    </div>
                    <p x-show="!filtrados().length" class="text-sm texto-2">Nenhum lançamento com esses filtros.</p>
                    <ul class="space-y-1.5">
                        <template x-for="l in filtrados()" :key="l.id">
                            <li class="wf-linha">
                                <div class="min-w-0">
                                    <p class="truncate"><span x-text="l.cat"></span><span class="texto-2" x-show="l.desc" x-text="' · ' + l.desc"></span></p>
                                    <p class="text-xs texto-2" x-text="dataBR(l.d) + ' · ' + contaNome(l.conta)"></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="font-medium" :style="{ color: l.tipo === 'ganho' ? '#4ade80' : '#f87171' }"
                                          x-text="(l.tipo === 'ganho' ? '+ ' : '− ') + brl(l.valor)"></span>
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remLanc(l.id)">✕</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-carteira.item>

            @include('areas.carteira.partials.blocos', ['sec' => 'carteira'])
        </section>

        {{-- ================= INVESTIMENTOS ================= --}}
        <section class="wf-quadro" x-show="aba === 'invest'" x-cloak :style="{ minHeight: alturaQuadro('invest') + 'px' }">

            <x-carteira.item sec="'invest'" bid="'inv-resumo'" titulo="Resultado dos investimentos" :minw="360" :minh="160">
                <x-slot name="acoes"><button type="button" class="btn-sec" @click="atualizarCotacoes()">Atualizar cotações</button></x-slot>
                <div x-data="{ get t() { return totalInvest() }, get gp() { return ganhosPerdas() } }" class="grid gap-4 md:grid-cols-2">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="wf-stat"><p class="text-xs texto-2">Investido</p><p class="titulo text-lg" x-text="brl(t.investido)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Valor atual</p><p class="titulo text-lg" x-text="brl(t.valor)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Ganhos</p><p class="font-medium" style="color:#4ade80" x-text="brl(gp.ganhos)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Perdas</p><p class="font-medium" style="color:#f87171" x-text="brl(gp.perdas)"></p></div>
                        <div class="wf-stat col-span-2"><p class="text-xs texto-2">Resultado total</p>
                            <p class="titulo text-xl" :style="{ color: cor(t.res) }" x-text="brl(t.res) + ' (' + pc(t.pct) + ')'"></p></div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs texto-2">Alocação por tipo</p>
                        <p x-show="!alocacao().length" class="text-sm texto-2">Adicione ativos para ver a distribuição.</p>
                        <template x-for="a in alocacao()" :key="a.t">
                            <div>
                                <div class="flex justify-between text-sm"><span x-text="a.t"></span><span class="texto-2" x-text="pc(a.pct)"></span></div>
                                <div class="wf-hbar mt-1"><span :style="`width:${a.pct}%`"></span></div>
                            </div>
                        </template>
                    </div>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'invest'" bid="'inv-novo'" titulo="Novo ativo" :minw="280" :minh="320">
                <div class="space-y-3">
                    <div><label class="rotulo">Ativo</label><input class="campo" x-model="iv.nome" placeholder="PETR4, MXRF11, Tesouro Selic…"></div>
                    <div><label class="rotulo">Tipo</label>
                        <select class="campo" x-model="iv.tipo"><template x-for="t in tiposInvest" :key="t"><option :value="t" x-text="t"></option></template></select></div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="rotulo">Qtd.</label><input type="number" min="0" step="any" class="campo" x-model="iv.qtd"></div>
                        <div><label class="rotulo">Preço médio</label><input type="number" min="0" step="any" class="campo" x-model="iv.pm"></div>
                        <div><label class="rotulo">Preço atual</label><input type="number" min="0" step="any" class="campo" x-model="iv.atual"></div>
                    </div>
                    <p class="erro" x-text="ivErro"></p>
                    <button type="button" class="btn w-full" @click="addInvest()">Adicionar à carteira</button>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'invest'" bid="'inv-lista'" titulo="Meus ativos" :minw="420" :minh="220">
                <p x-show="!estado.invest.length" class="text-sm texto-2">Nenhum investimento cadastrado.</p>
                <div class="overflow-auto" x-show="estado.invest.length">
                    <table class="wf-tabela">
                        <thead><tr><th>Ativo</th><th>Qtd.</th><th>P. médio</th><th>Atual</th><th>Valor</th><th>Resultado</th><th></th><th></th></tr></thead>
                        <tbody>
                            <template x-for="a in estado.invest" :key="a.id">
                                <tr>
                                    <td><p class="font-medium" x-text="a.nome"></p><p class="text-xs texto-2" x-text="a.tipo"></p></td>
                                    <td x-text="a.qtd"></td>
                                    <td x-text="brl(a.pm)"></td>
                                    <td><input type="number" min="0" step="any" class="campo wf-num" :value="a.atual" @change="setAtual(a, $event.target.value)" aria-label="Preço atual"></td>
                                    <td x-text="brl(valorAtivo(a))"></td>
                                    <td :style="{ color: cor(resAtivo(a)) }"><span x-text="brl(resAtivo(a))"></span><br><small x-text="pc(resPct(a))"></small></td>
                                    <td><svg x-show="pontosAtivo(a)" viewBox="0 0 100 30" class="w-16 h-6" aria-hidden="true"><polyline :points="pontosAtivo(a)" fill="none" stroke="var(--prim)" stroke-width="2" vector-effect="non-scaling-stroke"/></svg></td>
                                    <td><button type="button" class="mini-btn perigo" title="Remover" @click="remInvest(a)">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'invest'" bid="'inv-radar'" titulo="Radar: oportunidades para acompanhar" :minw="360" :minh="220">
                <div class="space-y-4">
                    <div class="grid gap-2 md:grid-cols-[8rem_1fr_8rem_auto]">
                        <input class="campo" x-model="rd.ticker" placeholder="Ticker">
                        <input class="campo" x-model="rd.tese" placeholder="Por que é um bom investimento? (tese)">
                        <input type="number" min="0" step="any" class="campo" x-model="rd.alvo" placeholder="Preço-alvo">
                        <button type="button" class="btn" @click="addRadar()">Adicionar</button>
                    </div>
                    <p x-show="!estado.radar.length" class="text-sm texto-2">Anote aqui os ativos que você quer comprar e o preço em que faria sentido.</p>
                    <ul class="grid gap-2 md:grid-cols-2">
                        <template x-for="x in estado.radar" :key="x.id">
                            <li class="wf-linha">
                                <div class="min-w-0">
                                    <p class="font-medium" x-text="x.ticker"></p>
                                    <p class="text-xs texto-2" x-text="x.tese || 'Sem tese'"></p>
                                    <p class="text-xs texto-2" x-show="x.alvo > 0" x-text="'Alvo: ' + brl(x.alvo)"></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="chip" x-text="radarTxt(x)"></span>
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remRadar(x.id)">✕</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-carteira.item>

            @include('areas.carteira.partials.blocos', ['sec' => 'invest'])
        </section>

        {{-- ================= NEGÓCIOS ================= --}}
        <section class="wf-quadro" x-show="aba === 'negocios'" x-cloak :style="{ minHeight: alturaQuadro('negocios') + 'px' }">

            <x-carteira.item sec="'negocios'" bid="'neg-negocios'" titulo="Meus negócios" :minw="280" :minh="300">
                <div class="space-y-4">
                    <p x-show="!estado.negocios.length" class="text-sm texto-2">Crie seu primeiro negócio abaixo.</p>
                    <ul class="space-y-2">
                        <template x-for="n in estado.negocios" :key="n.id">
                            <li class="wf-linha cursor-pointer" :style="estado.negAtivo === n.id ? 'border-color:var(--prim)' : ''" @click="estado.negAtivo = n.id">
                                <div class="min-w-0"><p class="font-medium truncate" x-text="n.nome"></p><p class="text-xs texto-2" x-text="formatoNome(n.formato)"></p></div>
                                <button type="button" class="mini-btn perigo" title="Excluir negócio" @click.stop="remNegocio(n)">✕</button>
                            </li>
                        </template>
                    </ul>
                    <div class="pt-3 space-y-2" style="border-top:1px solid var(--linha)">
                        <input class="campo" x-model="nv.nome" placeholder="Nome do negócio" @keydown.enter.prevent="addNegocio()">
                        <select class="campo" x-model="nv.formato"><template x-for="f in formatos" :key="f.id"><option :value="f.id" x-text="f.nome"></option></template></select>
                        <p class="erro" x-text="nvErro"></p>
                        <button type="button" class="btn w-full" @click="addNegocio()">Criar negócio</button>
                    </div>
                </div>
            </x-carteira.item>

            <x-carteira.item sec="'negocios'" bid="'neg-resumo'" titulo="Resumo do mês" :minw="360" :minh="150">
                <p x-show="!neg()" class="text-sm texto-2">Selecione ou crie um negócio.</p>
                <template x-if="neg()">
                    <div x-data="{ get k() { return kpisNeg(neg()) } }" class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="wf-stat"><p class="text-xs texto-2">Vendas</p><p class="titulo text-lg" x-text="brl(k.vendas)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Custos</p><p class="titulo text-lg" x-text="brl(k.custos)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Lucro</p><p class="titulo text-lg" :style="{ color: cor(k.lucro) }" x-text="brl(k.lucro)"></p></div>
                        <div class="wf-stat"><p class="text-xs texto-2">Margem · Ticket</p><p class="font-medium" x-text="pc(k.margem) + ' · ' + brl(k.ticket)"></p></div>
                    </div>
                </template>
            </x-carteira.item>

            <x-carteira.item sec="'negocios'" bid="'neg-lanc'" titulo="Lançar venda ou custo" :minw="360" :minh="190">
                <p x-show="!neg()" class="text-sm texto-2">Selecione um negócio para lançar movimentações.</p>
                <template x-if="neg()">
                    <div class="space-y-3">
                        <div class="flex gap-1.5">
                            <button type="button" class="chip" :class="{ 'ativo': mv.modo === 'venda' }" @click="mv.modo = 'venda'">Venda</button>
                            <button type="button" class="chip" :class="{ 'ativo': mv.modo === 'custo' }" @click="mv.modo = 'custo'">Custo</button>
                        </div>
                        <div class="grid gap-2 md:grid-cols-5">
                            <input type="date" class="campo" x-model="mv.d" aria-label="Data">
                            <input class="campo md:col-span-2" x-model="mv.item" :placeholder="mv.modo === 'venda' ? 'Item vendido' : 'Descrição do custo'">
                            <input type="number" min="0" step="0.01" class="campo" x-model="mv.valor" placeholder="R$" @keydown.enter.prevent="registrarMov()">
                            <template x-if="mv.modo === 'venda'"><input class="campo" list="wf-canais" x-model="mv.canal" placeholder="Canal"></template>
                            <template x-if="mv.modo === 'custo'">
                                <select class="campo" x-model="mv.cat"><template x-for="c in catsCusto" :key="c"><option :value="c" x-text="c"></option></template></select>
                            </template>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" class="btn" @click="registrarMov()">Lançar</button>
                            <p class="erro" x-text="mvErro"></p>
                        </div>
                    </div>
                </template>
            </x-carteira.item>

            <x-carteira.item sec="'negocios'" bid="'neg-caixa'" titulo="Fluxo de caixa" :minw="320" :minh="240">
                <p x-show="!neg()" class="text-sm texto-2">Selecione um negócio.</p>
                <template x-if="neg()">
                    <div x-data="{ get c() { return caixaNeg(neg()) } }" class="space-y-3">
                        <div class="wf-stat"><p class="text-xs texto-2">Saldo em caixa</p><p class="titulo text-2xl" :style="{ color: cor(c.saldo) }" x-text="brl(c.saldo)"></p></div>
                        <p x-show="!c.itens.length" class="text-sm texto-2">Sem movimentações ainda.</p>
                        <ul class="space-y-1.5">
                            <template x-for="m in c.itens" :key="m.id">
                                <li class="wf-linha">
                                    <div class="min-w-0"><p class="truncate" x-text="m.desc"></p><p class="text-xs texto-2" x-text="dataBR(m.d) + ' · saldo ' + brl(m.saldo)"></p></div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="font-medium" :style="{ color: m.v >= 0 ? '#4ade80' : '#f87171' }" x-text="(m.v >= 0 ? '+ ' : '− ') + brl(Math.abs(m.v))"></span>
                                        <button type="button" class="mini-btn perigo" title="Remover" @click="remMov(neg(), m)">✕</button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </x-carteira.item>

            <x-carteira.item sec="'negocios'" bid="'neg-dash'" titulo="Dashboard" :minw="320" :minh="240">
                <x-slot name="acoes"><button type="button" class="btn-sec" x-show="neg()" @click="addGrafico()">+ Gráfico</button></x-slot>
                <p x-show="!neg()" class="text-sm texto-2">Selecione um negócio.</p>
                <template x-if="neg()">
                    <div class="space-y-4">
                        <template x-for="g in neg().graficos" :key="g.id">
                            <div class="wf-ref card p-3 space-y-2" x-data="{ get s() { return serieGrafico(neg(), g) } }">
                                <div class="flex items-center gap-2">
                                    <input class="wf-titulo flex-1" x-model="g.titulo" aria-label="Título do gráfico">
                                    <button type="button" class="mini-btn perigo" title="Remover gráfico" @click="remGrafico(neg(), g.id)">✕</button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <select class="campo wf-link-sel" x-model="g.metrica"><option value="vendas">Vendas</option><option value="custos">Custos</option><option value="lucro">Lucro</option></select>
                                    <select class="campo wf-link-sel" x-model="g.janela"><option value="7d">7 dias</option><option value="4s">4 semanas</option><option value="6m">6 meses</option></select>
                                    <select class="campo wf-link-sel" x-model="g.estilo"><option value="barras">Barras</option><option value="linha">Linha</option></select>
                                </div>
                                <template x-if="g.estilo === 'barras'">
                                    <div class="wf-grafico">
                                        <template x-for="i in s.itens" :key="i.l">
                                            <div class="col" :title="brl(i.v)"><div :class="{ 'neg': i.neg }" :style="`height:${i.h}%`"></div><small x-text="i.l"></small></div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="g.estilo === 'linha'">
                                    <div>
                                        <svg viewBox="0 0 100 40" class="w-full h-24" preserveAspectRatio="none" aria-hidden="true"><polyline :points="s.pontos" fill="none" stroke="var(--prim)" stroke-width="2" vector-effect="non-scaling-stroke"/></svg>
                                        <div class="flex justify-between"><template x-for="i in s.itens" :key="i.l"><small class="texto-2" x-text="i.l"></small></template></div>
                                    </div>
                                </template>
                                <p class="text-xs texto-2" x-text="'Total no período: ' + brl(s.total)"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </x-carteira.item>

            <x-carteira.item sec="'negocios'" bid="'neg-conexoes'" titulo="Marketplaces e tráfego" :minw="360" :minh="220">
                <p x-show="!neg()" class="text-sm texto-2">Selecione um negócio para vincular marketplaces e geradores de tráfego.</p>
                <template x-if="neg()">
                    <div class="space-y-4">
                        <div class="grid gap-2 md:grid-cols-[10rem_1fr_8rem_1fr_auto]">
                            <select class="campo" x-model="cn.tipo"><option value="marketplace">Marketplace</option><option value="trafego">Gerador de tráfego</option></select>
                            <input class="campo" list="wf-conexoes" x-model="cn.nome" placeholder="Mercado Livre, Meta Ads…">
                            <input type="number" min="0" step="0.01" class="campo" x-model="cn.gasto" placeholder="Gasto/mês">
                            <input class="campo" x-model="cn.nota" placeholder="Nota (loja, campanha…)">
                            <button type="button" class="btn" @click="addConexao()">Vincular</button>
                        </div>
                        <p x-show="!neg().conexoes.length" class="text-sm texto-2">Nenhuma conexão ainda.</p>
                        <ul class="grid gap-2 md:grid-cols-2">
                            <template x-for="c in neg().conexoes" :key="c.id">
                                <li class="wf-linha">
                                    <div class="min-w-0">
                                        <p class="font-medium truncate" x-text="c.nome"></p>
                                        <p class="text-xs texto-2" x-text="(c.tipo === 'trafego' ? 'Tráfego' : 'Marketplace') + (c.gasto ? ' · ' + brl(c.gasto) + '/mês' : '') + (c.nota ? ' · ' + c.nota : '')"></p>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <button type="button" class="link-prim text-xs" x-show="c.gasto > 0"
                                                @click="neg().custos.push({ id: uid(), d: hoje(), nome: c.nome, valor: c.gasto, cat: c.tipo === 'trafego' ? 'Tráfego' : 'Impostos e taxas' }); aviso('Custo lançado.')">Lançar custo</button>
                                        <label class="text-xs flex items-center gap-1"><input type="checkbox" x-model="c.ativa"> ativa</label>
                                        <button type="button" class="mini-btn perigo" title="Desvincular" @click="remConexao(neg(), c.id)">✕</button>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </x-carteira.item>

            @include('areas.carteira.partials.blocos', ['sec' => 'negocios'])
        </section>

        {{-- ================= TAREFAS (mesmo layout das outras áreas) ================= --}}
        <section class="wf-quadro" x-show="aba === 'tarefas'" x-cloak :style="{ minHeight: alturaQuadro('tarefas') + 'px' }">

            <x-carteira.item sec="'tarefas'" bid="'tarefa-form'" titulo="Nova tarefa" :minw="260" :minh="200">
                @include('areas.partials.formulario', [
                    'slug'        => $slug,
                    'placeholder' => 'Ex.: Pagar fatura, revisar investimentos…',
                ])
            </x-carteira.item>

            <x-carteira.item sec="'tarefas'" bid="'tarefa-lista'" titulo="Tarefas" :minw="320" :minh="200">
                @include('areas.partials.lista', [
                    'tarefas' => $tarefas,
                    'vazio'   => 'Nenhuma tarefa ainda.',
                ])
            </x-carteira.item>

            @include('areas.carteira.partials.blocos', ['sec' => 'tarefas'])
        </section>

        {{-- ================= TRABALHO ================= --}}
        <section class="wf-quadro" x-show="aba === 'trabalho'" x-cloak :style="{ minHeight: alturaQuadro('trabalho') + 'px' }">

            <x-carteira.item sec="'trabalho'" bid="'projetos'" titulo="Projetos" :minw="480" :minh="260">
                <x-slot name="acoes">
                    <input class="campo !w-44 !py-1" x-model="novoProj" placeholder="Novo projeto" @keydown.enter.prevent="addProjeto()" aria-label="Novo projeto">
                    <button type="button" class="btn" @click="addProjeto()">+</button>
                </x-slot>
                <div class="wf-kanban">
                    <template x-for="col in colunas" :key="col.id">
                        <div class="wf-col-k">
                            <div class="flex justify-between"><h3 class="titulo text-sm" x-text="col.nome"></h3><span class="text-xs texto-2" x-text="projDe(col.id).length"></span></div>
                            <template x-for="p in projDe(col.id)" :key="p.id">
                                <div class="wf-proj space-y-1.5">
                                    <div class="flex items-center gap-1">
                                        <input class="wf-cel font-medium flex-1" x-model="p.nome" aria-label="Nome do projeto">
                                        <button type="button" class="mini-btn" x-show="col.id !== 'fazer'" title="Voltar" @click="moverProj(p, -1)">←</button>
                                        <button type="button" class="mini-btn" x-show="col.id !== 'feito'" title="Avançar" @click="moverProj(p, 1)">→</button>
                                        <button type="button" class="mini-btn perigo" title="Remover" @click="remProj(p.id)">✕</button>
                                    </div>
                                    <textarea class="campo" rows="2" x-model="p.nota" placeholder="Notas"></textarea>
                                    <select class="campo wf-link-sel" x-model="p.doc" aria-label="Documento do projeto">
                                        <option value="">Sem documento</option>
                                        <template x-for="d in estado.docs" :key="d.id"><option :value="d.id" x-text="d.titulo"></option></template>
                                    </select>
                                    <button type="button" class="link-prim text-xs" x-show="p.doc" @click="abrirLink({ t: 'doc', id: p.doc })">Abrir documento</button>
                                    {{-- Anexos externos --}}
                                    <div class="space-y-1" x-data="{ link: '' }">
                                        <template x-for="a in (p.anexos || [])" :key="a.id">
                                            <div class="flex items-center gap-1 text-xs">
                                                <button type="button" class="link-prim truncate flex-1 text-left" :title="a.nome" @click="abrirAnexo(a)"
                                                        x-text="(a.tipo === 'link' ? '🔗 ' : '📎 ') + a.nome"></button>
                                                <button type="button" class="mini-btn perigo" title="Remover anexo" @click="remAnexo(p, a.id)">✕</button>
                                            </div>
                                        </template>
                                        <div class="flex items-center gap-1.5 pt-1">
                                            <label class="link-prim text-xs cursor-pointer">+ Arquivo
                                                <input type="file" class="hidden" accept=".pdf,.doc,.docx,.odt,.rtf,.txt"
                                                    @change="anexarArquivo(p, $event.target.files[0]); $event.target.value = ''">
                                            </label>
                                            <input class="campo !py-0.5 !text-xs flex-1 min-w-0" x-model="link" placeholder="ou cole um link (Google Docs…)"
                                                @keydown.enter.prevent="anexarLink(p, link); link = ''">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </x-carteira.item>

            @include('areas.carteira.partials.blocos', ['sec' => 'trabalho'])
        </section>

        {{-- ================= DOCUMENTOS ================= --}}
        <section class="wf-quadro" x-show="aba === 'docs'" x-cloak :style="{ minHeight: alturaQuadro('docs') + 'px' }">

            {{-- Lista, editor (Conteúdo / Estilo / Página), prévia e tela cheia --}}
            @include('areas.carteira.partials.documentos')

            @include('areas.carteira.partials.blocos', ['sec' => 'docs'])
        </section>

        {{-- ================= MAPA MENTAL (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'mapa'" x-cloak :style="{ minHeight: alturaQuadro('mapa') + 'px' }">
            @include('areas.carteira.partials.blocos', ['sec' => 'mapa'])
        </section>

        {{-- ================= QUADRO LIVRE (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'livre'" x-cloak :style="{ minHeight: alturaQuadro('livre') + 'px' }">
            <p x-show="!blocosDe('livre').length" class="text-sm texto-2">
                Quadro vazio. Use “+ Bloco” para adicionar texto, tabela, lista, imagem ou mapa mental — ou arraste arquivos (.md, .txt, .csv, imagens, .wf.json) para cá.
            </p>
            @include('areas.carteira.partials.blocos', ['sec' => 'livre'])
        </section>
    </div>
</div>
@endsection