@extends('layouts.app')

@section('titulo', $area['nome'])
@section('subtitulo', $area['descricao'])

@section('conteudo')
@php
    // Tudo que o diverso.js precisa: onde salvar, o que já foi salvo e as tarefas da área.
    $cfg = [
        'url'     => url('/diverso/dados'),
        'dados'   => (object) $diversoDados->all(),
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

    @media (max-width: 899px) {
        .wf-quadro { display: flex; flex-direction: column; gap: 1rem; min-height: 0 !important; height: auto !important; }
        .wf-item { position: static; width: auto; height: auto; }
        .wf-corpo { overflow: visible; }
        .wf-topo { cursor: default; touch-action: auto; }
        .wf-redim { display: none; }
        .wf-kanban { grid-template-columns: 1fr; }
        .wf-mapa { height: 24rem; }
    }

    /* ===== Diverso: documento, loja, mapa com desenho ===== */
    .wf-cresce { flex: 1; min-height: 6rem; }
    .wf-doc { background: #fbfbf8; color: #1b1f24; border-radius: .5rem; padding: 1.1rem; }
    .wf-doc.l-duas { columns: 2; column-gap: 1.25rem; }
    .wf-doc-sec { break-inside: avoid; margin-bottom: .9rem; }
    .wf-doc-sec.cab { background: var(--dc); color: #fff; padding: .8rem; border-radius: .4rem; column-span: all; }
    .wf-doc-t, .wf-doc-x { width: 100%; background: transparent; color: inherit; border: 0; font-family: inherit; border-radius: .25rem; }
    .wf-doc-t { font-weight: 700; font-size: 1rem; padding: .1rem .2rem; border-bottom: 2px solid var(--dc); }
    .wf-doc-sec.cab .wf-doc-t { font-size: 1.4rem; border-color: rgba(255, 255, 255, .35); }
    .wf-doc-x { resize: vertical; padding: .3rem .2rem; font-size: .88rem; line-height: 1.45; }
    .wf-doc-t:focus, .wf-doc-x:focus { outline: none; background: rgba(0, 0, 0, .06); }
    .wf-doc ::placeholder { color: inherit; opacity: .4; }
    .wf-mapa-col { flex: 1; min-height: 0; overflow: auto; }
    .wf-no { z-index: 2; }
    .wf-vitrine { display: grid; grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr)); gap: .75rem; }
    .wf-prod { border: 1px solid var(--linha); border-radius: .75rem; background: var(--superficie-2); padding: .6rem; display: flex; flex-direction: column; gap: .45rem; transition: border-color .2s, transform .2s; }
    .wf-prod:hover { border-color: var(--prim-linha); transform: translateY(-2px); }
    .wf-prod-img { aspect-ratio: 4 / 3; border-radius: .5rem; border: 1px dashed var(--linha); display: flex; align-items: center; justify-content: center; overflow: hidden; cursor: pointer; color: var(--tinta-2); font-size: .8rem; background: var(--superficie); }
    .wf-prod-img:hover { border-color: var(--prim); color: var(--prim-forte); }
    .wf-prod-img img { width: 100%; height: 100%; object-fit: cover; }
    /* abas */
    .wf-grupo { display: contents; }
    .circ-nova .circ-bola { border-style: dashed; font-size: 1.5rem; }
    .circ-icone { display: flex; line-height: 0; }
    .wf-ic { width: 1.5rem; height: 1.5rem; }
    .wf-aba-acoes { display: flex; gap: .25rem; justify-content: center; margin-top: .25rem; }
    .wf-icone-op { width: 2.25rem; height: 2.25rem; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border-radius: .5rem; border: 1px solid var(--linha); background: var(--superficie-2); cursor: pointer; color: var(--tinta-2); transition: color .15s, border-color .15s; }
    .wf-icone-op .wf-ic { width: 1.25rem; height: 1.25rem; }
    .wf-icone-op:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .wf-icone-op.sel { border-color: var(--prim); box-shadow: 0 0 0 2px var(--prim-linha); color: var(--prim-forte); }
    /* ===== Tabletop (mapa com grade) ===== */
    .wf-tt { height: 100%; display: flex; flex-direction: column; gap: .6rem; min-height: 0; }
    .wf-tt-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .wf-tt-linha .campo { width: auto; padding: .3rem .5rem; font-size: .8rem; }
    .wf-tt-linha .wf-tt-nome-in { flex: 1; min-width: 7rem; }
    .wf-tt-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .25rem .6rem; font-size: .75rem; border-radius: 9999px; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta); cursor: pointer; transition: border-color .15s, color .15s; }
    .wf-tt-chip:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .wf-tt-chip i { display: inline-block; width: .6rem; height: .6rem; border-radius: 9999px; background: var(--pc); }

    /* dropdown de atributos */
    .wf-tt-drop { position: relative; }
    .wf-tt-bolinha { display: inline-block; width: .65rem; height: .65rem; border-radius: 9999px; margin-right: .3rem; vertical-align: -1px; box-shadow: 0 0 0 1px rgba(255, 255, 255, .25); }
    .wf-tt-pop { position: absolute; top: 100%; left: 0; z-index: 30; margin-top: .35rem; min-width: 15rem; padding: .7rem; display: grid; gap: .55rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    .wf-tt-attr { display: flex; align-items: center; justify-content: space-between; gap: .75rem; font-size: .78rem; color: var(--tinta-2); }
    .wf-tt-pop .campo { width: 8rem; }
    .wf-tt-mini { width: 1.75rem; height: 1.75rem; object-fit: cover; border-radius: .3rem; border: 1px solid var(--linha); }

    .wf-tt-cena { flex: 1; min-height: 8rem; overflow: auto; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); padding: .5rem; }
    .wf-tt-grade { position: relative; flex-shrink: 0; margin-bottom: 1.2rem; background: var(--superficie-2); box-shadow: 0 0 0 1px var(--linha); isolation: isolate; user-select: none; }
    .wf-tt-grade::after {
        content: ""; position: absolute; inset: 0; z-index: 1; pointer-events: none;
        background-image: linear-gradient(to right, rgba(255, 255, 255, .09) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, .09) 1px, transparent 1px);
        background-size: var(--c) var(--c);
    }
    .wf-tt-grade.sem-grade::after { display: none; }
    .wf-tt-mapa { position: absolute; inset: 0; z-index: 0; pointer-events: none; background-size: 100% 100%; background-repeat: no-repeat; }
    .wf-tt-peca {
        position: absolute; z-index: 2; display: flex; align-items: center; justify-content: center;
        cursor: grab; touch-action: none; user-select: none; outline: none;
        transition: left .1s ease, top .1s ease;
    }
    .wf-tt-peca::before { content: ""; position: absolute; inset: 3px; background: var(--pc); box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .25); }
    .wf-tt-peca.f-circulo::before { border-radius: 9999px; }
    .wf-tt-peca.f-quadrado::before { border-radius: .3rem; }
    .wf-tt-peca.f-losango::before { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); }
    /* imagem no lugar da inicial: a moldura (span) recorta na forma da peça e a imagem dentro dela recebe o zoom e a posição */
    .wf-tt-img { position: absolute; left: 3px; top: 3px; width: calc(100% - 6px); height: calc(100% - 6px); overflow: hidden; pointer-events: none; }
    .wf-tt-img img, .wf-tt-enq img { display: block; width: 100%; height: 100%; object-fit: cover; transform-origin: center; pointer-events: none; }
    .wf-tt-peca.f-circulo .wf-tt-img { border-radius: 9999px; }
    .wf-tt-peca.f-quadrado .wf-tt-img { border-radius: .3rem; }
    .wf-tt-peca.f-losango .wf-tt-img { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); }
    .wf-tt-peca:focus-visible, .wf-tt-peca.sel { z-index: 3; filter: drop-shadow(0 0 3px #fff); }
    .wf-tt-peca.arrasta { z-index: 5; cursor: grabbing; transition: none; filter: drop-shadow(0 6px 8px rgba(0, 0, 0, .55)); }
    .wf-tt-ini { position: relative; font-size: .75rem; font-weight: 700; color: #fff; text-shadow: 0 1px 2px rgba(0, 0, 0, .75); pointer-events: none; }
    .wf-tt-rotulo { position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 4; white-space: nowrap; font-size: .62rem; padding: 0 .3rem; border-radius: .25rem; background: rgba(0, 0, 0, .65); color: #fff; pointer-events: none; }
    /* retângulo de seleção */
    .wf-tt-caixa { position: absolute; z-index: 6; pointer-events: none; border: 1px solid var(--prim); background: rgba(255, 255, 255, .08); }

    /* editor de enquadramento da imagem (prévia no formato da peça, com zoom e posição) */
    .wf-tt-enq-box { display: grid; gap: .4rem; justify-items: center; padding-top: .5rem; border-top: 1px solid var(--linha); }
    .wf-tt-enq { position: relative; width: 9rem; height: 9rem; overflow: hidden; background: var(--superficie-2); cursor: move; touch-action: none; user-select: none; box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-tt-enq.f-circulo { border-radius: 9999px; }
    .wf-tt-enq.f-quadrado { border-radius: .4rem; }
    .wf-tt-enq.f-losango { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); box-shadow: none; }
    .wf-tt-enq-zoom { display: flex; align-items: center; gap: .5rem; width: 100%; font-size: .72rem; color: var(--tinta-2); }
    .wf-tt-enq-zoom input[type=range] { flex: 1; min-width: 0; }

    @media (max-width: 899px) { .wf-tt { height: auto; } .wf-tt-cena { max-height: 70vh; } }

    /* aviso de quadro vazio: logo abaixo do último cartão */
    .wf-vazio { position: absolute; left: var(--ox); top: var(--oy); }
    @media (max-width: 899px) { .wf-vazio { position: static; } }
</style>

<script type="application/json" id="diverso-cfg">{!! json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
{{-- Precisam vir antes do Alpine iniciar (o Alpine do layout usa defer) --}}
<script src="{{ asset('js/quadro.js') }}?v={{ @filemtime(public_path('js/quadro.js')) }}"></script>
<script src="{{ asset('js/diverso.js') }}?v={{ @filemtime(public_path('js/diverso.js')) }}"></script>

<script>
    // Ícones minimalistas (traço de 1,6px). O valor salvo na aba é só a chave (ex.: "casa").
    window.WF_ICONES = {
        pasta:      '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        pin:        '<path d="M12 21v-6"/><path d="M8 4h8l-1 6 3 3H6l3-3z"/>',
        casa:       '<path d="M4 11l8-7 8 7"/><path d="M6 10v9h12v-9"/><path d="M10 19v-5h4v5"/>',
        trabalho:   '<rect x="3" y="7" width="18" height="12" rx="2"/><path d="M9 7V5h6v2M3 13h18"/>',
        compras:    '<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>',
        dinheiro:   '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.5c-.5-1-1.5-1.5-2.5-1.5-1.4 0-2.5.8-2.5 2s1 1.7 2.5 2 2.5.8 2.5 2-1.1 2-2.5 2c-1 0-2-.5-2.5-1.5M12 6.5V8m0 8v1.5"/>',
        grafico:    '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
        calendario: '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        check:      '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
        relogio:    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        ideia:      '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.5 10.9c.5.4.8 1 .8 1.6V16h5.4v-.5c0-.6.3-1.2.8-1.6A6 6 0 0 0 12 3z"/>',
        livro:      '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 1-3-3z"/><path d="M5 17a3 3 0 0 1 3-3h10"/>',
        estudo:     '<path d="M3 9l9-5 9 5-9 5z"/><path d="M7 11.5V16c0 1.5 2.2 3 5 3s5-1.5 5-3v-4.5"/>',
        codigo:     '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
        chat:       '<path d="M4 5h16v11H9l-5 4z"/>',
        usuario:    '<circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6"/>',
        coracao:    '<path d="M12 20s-8-4.7-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5C20 15.3 12 20 12 20z"/>',
        saude:      '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        estrela:    '<path d="M12 4l2.4 5 5.6.7-4.1 3.8 1.1 5.5L12 16.3 7 19l1.1-5.5L4 9.7 9.6 9z"/>',
        musica:     '<path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/>',
        foto:       '<rect x="3" y="6" width="18" height="14" rx="2"/><circle cx="12" cy="13" r="3.5"/><path d="M8 6l1.5-2h5L16 6"/>',
        jogo:       '<rect x="3" y="8" width="18" height="9" rx="4.5"/><path d="M8 12.5h3M9.5 11v3"/><circle cx="15.5" cy="11.5" r=".6"/><circle cx="17.5" cy="13.5" r=".6"/>',
        cafe:       '<path d="M5 9h11v5a4 4 0 0 1-4 4H9a4 4 0 0 1-4-4z"/><path d="M16 10h1.5a2 2 0 0 1 0 4H16M8 3v2M12 3v2"/>',
        planta:     '<path d="M12 20v-8"/><path d="M12 12c0-4 3-6 7-6 0 4-3 6-7 6z"/><path d="M12 15c0-3-2-5-6-5 0 3 2 5 6 5z"/>',
        viagem:     '<path d="M21 4L3 11l7 3 3 7z"/><path d="M10 14L21 4"/>',
        globo:      '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>'
    };
    // Qualquer valor antigo (como um emoji) cai no ícone "pasta"
    window.wfIcone = function (chave) {
        const d = window.WF_ICONES[chave] || window.WF_ICONES.pasta;
        return '<svg class="wf-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
    };
</script>

<div x-data="diversoAbas()" class="space-y-6">

    <datalist id="wf-redes">
        <option value="Instagram"></option><option value="TikTok"></option><option value="YouTube"></option>
        <option value="X"></option><option value="LinkedIn"></option><option value="Facebook"></option>
    </datalist>

    {{-- Abas: só as que você criar (cada uma com nome e ícone) + o botão de nova aba --}}
    <nav class="flex flex-wrap items-start gap-x-5 gap-y-4" aria-label="Abas do Diverso">
        <template x-for="a in estado.abas" :key="a.id">
            <div class="flex flex-col items-center">
                <button type="button" class="circ" :class="{ 'ativo': aba === a.id }"
                        @click="irPara(a.id)" @dblclick="editarAba(a)"
                        :aria-pressed="aba === a.id" :title="a.nome">
                    <span class="circ-bola"><span class="circ-icone" x-html="wfIcone(a.icone)"></span></span>
                    <span class="circ-rotulo" style="max-width: 5.5rem; overflow-wrap: anywhere;" x-text="a.nome"></span>
                </button>
                <div class="wf-aba-acoes" x-show="aba === a.id" x-cloak>
                    <button type="button" class="mini-btn" title="Editar nome e ícone" :aria-label="'Editar a aba ' + a.nome" @click="editarAba(a)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
                    </button>
                    <button type="button" class="mini-btn perigo" x-show="a.id !== 'quadro'" title="Excluir aba" :aria-label="'Excluir a aba ' + a.nome" @click="removerAba(a)">✕</button>
                </div>
            </div>
        </template>

        <div class="flex flex-col items-center" x-show="estado.abas.length < 12">
            <button type="button" class="circ circ-nova" @click="abrirNovaAba()" title="Criar nova aba">
                <span class="circ-bola">+</span>
                <span class="circ-rotulo">Nova aba</span>
            </button>
        </div>
    </nav>

    {{-- Criar / editar aba: nome e ícone --}}
    <div x-show="formAba" x-cloak class="card p-4 space-y-3" role="group" aria-label="Dados da aba">
        <p class="text-sm font-medium" x-text="formAba && formAba.id ? 'Editar aba' : 'Nova aba'"></p>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[12rem]">
                <label class="rotulo" for="aba-nome">Nome da aba</label>
                <input id="aba-nome" x-ref="nomeAba" type="text" maxlength="40" class="campo" placeholder="Ex.: Casa, Trabalho, Compras…"
                       :value="formAba ? formAba.nome : ''" @input="formAba.nome = $event.target.value"
                       @keydown.enter.prevent="salvarAba()" @keydown.escape.prevent="cancelarAba()">
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5" role="listbox" aria-label="Ícones">
            <template x-for="ic in Object.keys(WF_ICONES)" :key="ic">
                <button type="button" class="wf-icone-op" :class="{ 'sel': formAba && formAba.icone === ic }"
                        @click="formAba.icone = ic" x-html="wfIcone(ic)" :title="ic" :aria-label="'Ícone ' + ic"></button>
            </template>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn" @click="salvarAba()" x-text="formAba && formAba.id ? 'Salvar' : 'Criar aba'"></button>
            <button type="button" class="btn-sec" @click="cancelarAba()">Cancelar</button>
        </div>
    </div>

    {{-- Barra de ferramentas --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs texto-2" x-text="rotuloSalvo()" aria-live="polite"></p>
        <div class="flex flex-wrap items-center gap-2" x-show="aba" x-cloak>
            <button type="button" class="btn-sec" @click="$dispatch('abrir-biblioteca')">Biblioteca</button>
            <button type="button" class="btn-sec" @click="reorganizar()">Reorganizar</button>
            <div class="relative" @click.outside="menuBloco = false" @keydown.escape.window="menuBloco = false">
                <button type="button" class="btn" @click="menuBloco = !menuBloco" aria-haspopup="menu" :aria-expanded="menuBloco">+ Bloco</button>
                <div x-show="menuBloco" x-cloak class="menu-pop absolute right-0 top-full mt-2 w-60 p-1.5 z-40" role="menu">
                    <template x-for="t in tiposBloco" :key="t.id">
                        <button type="button" class="menu-item" role="menuitem" @click="novoBloco(aba, t.id)" x-text="t.nome"></button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div x-show="msg" x-cloak class="aviso-ok flex items-center gap-3" role="status">
        <span x-text="msg"></span>
        <button type="button" class="link-prim text-sm" x-show="desfazerVisivel()" @click="desfazerRemocao()">Desfazer</button>
    </div>

    <p x-show="!aba && !formAba" x-cloak class="text-sm texto-2">
        Nenhuma aba ainda. Clique em “Nova aba”, escolha um nome e um ícone e coloque seus quadros lá.
    </p>

    {{-- O quadro livre da aba escolhida (a primeira aba também mostra as tarefas) --}}
    <div x-ref="area" class="w-full">
        <section class="wf-quadro" x-show="aba" x-cloak :style="{ minHeight: alturaQuadro(aba) + 'px' }">

            {{-- As tarefas (só na primeira aba) ficam FORA de qualquer <template>: assim a lista é atualizada na hora ao adicionar, concluir ou excluir. --}}
            <div class="wf-grupo" x-show="aba === 'quadro'" x-cloak>
            <x-diverso.item sec="'quadro'" bid="'tarefa-form'" titulo="Nova tarefa" :minw="260" :minh="200">
                @include('areas.partials.formulario', [
                    'slug'        => $slug,
                    'placeholder' => 'Ex.: Marcar reunião, comprar presente…',
                    'embutido'    => true,
                ])
            </x-diverso.item>

            <x-diverso.item sec="'quadro'" bid="'tarefa-lista'" titulo="Tarefas" :minw="320" :minh="200">
                @include('areas.partials.lista', [
                    'tarefas' => $tarefas,
                    'vazio'   => 'Nenhuma tarefa ainda. Que tal começar por uma?',
                ])
            </x-diverso.item>
            </div>

            {{-- O estilo fica num contêiner à parte: um :style no mesmo elemento do x-show apagaria o "display: none". --}}
            <div class="wf-vazio" :style="`--ox:${ox}px;--oy:${alturaConteudo(aba) + 16}px`">
                <p x-show="!blocosDe(aba).length" x-cloak class="text-sm texto-2">
                    Nenhum bloco nesta aba ainda. Use “+ Bloco” para criar textos, tabelas, imagens, documentos, mapas mentais, listas de compras, escrita, redes sociais, loja e mais.
                </p>
            </div>

            @include('areas.diverso.partials.blocos')
        </section>
    </div>
</div>
@endsection