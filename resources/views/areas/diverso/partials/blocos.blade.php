@include('areas.diverso.partials.escrita-js')
{{-- cartão "GitHub / VS Code": mini-IDE (WFIde), WFRepo e estilo (também usado nas áreas que recebem o cartão) --}}
@include('partials.ide-cartao')
{{-- ?v= força o navegador a baixar de novo quando o arquivo muda; sem filemtime usa a versão fixa. --}}
<script>
    if (!window.WFEscrita) {
        console.error('[Work Flower] public/js/escrita.js não foi carregado (editor de texto indisponível). Confira se o arquivo existe em public/js/ e recarregue com Ctrl+F5.');
    }
</script>
<style>
    /* Lista de itens: o texto quebra para a linha de baixo (o campo cresce junto) em vez de ser cortado */
    .wf-item-txt { display: block; flex: 1; min-width: 0; resize: none; overflow: hidden; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; line-height: 1.35; min-height: 1.9rem; field-sizing: content; }

    /* Botão de recolher/mostrar opções (campo de texto e mapa mental) */
    .wf-opc-btn { display: inline-flex; align-items: center; gap: .35rem; }
    .wf-opc-btn svg { width: .85rem; height: .85rem; flex-shrink: 0; transition: transform .15s; }
    .wf-opc-btn.fechada svg { transform: rotate(-90deg); }

    /* ===== Editor de escrita (estilo Notion) ===== */
    .wf-es { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 14rem; }
    .wf-es-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-es-barra .btn-sec { padding: .2rem .55rem; font-size: .75rem; cursor: pointer; min-width: 1.9rem; }
    .wf-es-barra .btn-sec:disabled { opacity: .45; cursor: default; }
    .wf-es-barra select.wf-link-sel { min-width: 8.5rem; cursor: pointer; }
    .wf-es-sep { width: 1px; align-self: stretch; min-height: 1.1rem; background: var(--linha); margin: 0 .1rem; }
    .wf-es-sum { border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); padding: .3rem; max-height: 8rem; overflow: auto; font-size: .8rem; }
    .wf-es-sum button { display: block; width: 100%; text-align: left; padding: .15rem .4rem; border-radius: .3rem; color: var(--tinta); cursor: pointer; background: none; border: 0; }
    .wf-es-sum button:hover { background: var(--superficie); }
    .wf-es-folha { position: relative; flex: 1 1 auto; min-height: 8rem; overflow: auto; display: flex; flex-direction: column; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); padding: .75rem .75rem .5rem .25rem; line-height: 1.6; color: var(--tinta); }
    .wf-es-folha:focus { outline: none; }
    /* Modo "texto inteiro": uma caixa só, já selecionada, para copiar tudo de uma vez (Ctrl+C) */
    .wf-es-todo { flex: 1; min-height: 10rem; display: flex; flex-direction: column; gap: .3rem; }
    .wf-es-todo-ta { flex: 1; width: 100%; min-height: 8rem; resize: none; padding: .6rem .75rem; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--tinta); background: var(--superficie); border: 1px solid var(--prim-linha); border-radius: .5rem; outline: none; }
    .wf-es-todo-ta::selection { background: rgba(99, 140, 255, .45); }
    .wf-es-bloco { position: relative; display: flex; align-items: flex-start; border-radius: .3rem; }
    .wf-es-bloco.selecionado { background: rgba(99, 140, 255, .2); box-shadow: 0 0 0 1px rgba(99, 140, 255, .45); }
    .wf-es-bloco.sobre { box-shadow: 0 -2px 0 0 var(--prim); }
    .wf-es-bloco.arrastando { opacity: .45; }
    .wf-es-lado { display: flex; flex-shrink: 0; width: 2.9rem; justify-content: flex-end; opacity: 0; transition: opacity .12s; }
    .wf-es-bloco:hover > .wf-es-lado, .wf-es-bloco.foco > .wf-es-lado { opacity: 1; }
    .wf-es-lbtn { width: 1.3rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: .8rem; line-height: 1; color: var(--tinta-2); border: 0; background: none; border-radius: .25rem; cursor: pointer; }
    .wf-es-lbtn:hover { background: var(--superficie-2); color: var(--tinta); }
    .wf-es-grip { cursor: grab; letter-spacing: -.15em; font-size: .7rem; }
    .wf-es-corpo { flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: .5rem; }
    .wf-es-txt { flex: 1; min-width: 0; }
    .wf-es-ver, .wf-es-ta { display: block; width: 100%; margin: 0; padding: .12rem 0; font: inherit; line-height: inherit; color: inherit; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; min-height: 1.6em; }
    .wf-es-ta { background: transparent; border: 0; border-radius: 0; outline: 0; box-shadow: none; resize: none; overflow: hidden; appearance: none; }
    .wf-es-ver { cursor: text; }
    .wf-es-ph { color: var(--tinta-2); opacity: .7; }
    .wf-es-marca { flex-shrink: 0; min-width: 1.2rem; text-align: center; color: var(--tinta-2); padding-top: .12rem; user-select: none; }
    .wf-es-chk { flex-shrink: 0; width: 1rem; height: 1rem; margin-top: .4em; accent-color: var(--prim); cursor: pointer; }
    .wf-es-hr { flex: 1; border: 0; border-top: 1px solid var(--linha); margin: .8rem 0; }
    .wf-es-corpo.t-h1 { margin-top: .6rem; } .wf-es-corpo.t-h1 .wf-es-txt { font-size: 1.75em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h2 { margin-top: .5rem; } .wf-es-corpo.t-h2 .wf-es-txt { font-size: 1.4em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h3 { margin-top: .4rem; } .wf-es-corpo.t-h3 .wf-es-txt { font-size: 1.15em; font-weight: 600; line-height: 1.35; }
    .wf-es-corpo.t-quote .wf-es-txt { border-left: 3px solid var(--tinta-2); padding-left: .8rem; font-style: italic; color: var(--tinta-2); }
    .wf-es-corpo.t-callout { background: var(--superficie-2); border: 1px solid var(--linha); border-left: 3px solid var(--prim); border-radius: .4rem; padding: .4rem .7rem; }
    .wf-es-corpo.t-code .wf-es-txt { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .4rem; padding: .3rem .6rem .4rem; }
    .wf-es-corpo.t-code .wf-es-ta { font: 12.5px/1.5 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow-x: auto; tab-size: 2; min-height: 3em; }
    .wf-es-lang { width: 8rem; margin-bottom: .2rem; padding: 0; font-size: .68rem; color: var(--tinta-2); background: transparent; border: 0; outline: 0; }
    .wf-es-corpo.feito .wf-es-txt, .wf-es-corpo.feito .wf-es-ta { text-decoration: line-through; color: var(--tinta-2); }
    .wf-es-ver a { color: var(--prim-forte); text-decoration: underline; }
    .wf-es-ver mark { background: rgba(250, 204, 21, .35); color: inherit; border-radius: .2rem; padding: 0 .1em; }
    .wf-es-cod { font: .88em ui-monospace, Menlo, Consolas, monospace; background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .25rem; padding: 0 .3em; }
    .wf-es-menu { position: absolute; left: 2.9rem; top: 100%; z-index: 30; margin-top: .15rem; width: 17rem; max-height: 15rem; overflow: auto; padding: .3rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    /* menu do botão ⋮⋮ (ações e "Transformar em") */
    .wf-es-menu-bl { left: .3rem; top: 1.6rem; width: 13rem; max-height: 19rem; margin-top: 0; }
    .wf-es-menu-bl .menu-item:disabled { opacity: .45; cursor: default; }
    .wf-es-menu-tit { margin: .3rem 0 .15rem; padding: .25rem .5rem 0; border-top: 1px solid var(--linha); font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--tinta-2); }
    .wf-es-menu-tp-it { display: flex; align-items: center; gap: .5rem; }
    .wf-es-menu-tp-it.atual { background: var(--superficie-2); font-weight: 600; }
    .wf-es-menu-tp-it .wf-es-ic { width: 1.4rem; height: 1.4rem; font-size: .65rem; }
    /* barra flutuante ao selecionar texto ou blocos */
    .wf-es-bolha { position: absolute; left: 2.9rem; top: 100%; z-index: 25; display: flex; align-items: center; gap: .1rem; margin-top: .2rem; padding: .2rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, .4); font-size: .8rem; line-height: 1.2; }
    .wf-es-bb { min-width: 1.7rem; padding: .2rem .45rem; border: 0; border-radius: .3rem; background: none; color: var(--tinta); cursor: pointer; font-size: .8rem; }
    .wf-es-bb:hover { background: var(--superficie-2); }
    .wf-es-bb-tipo { font-weight: 600; white-space: nowrap; }
    .wf-es-bsep { width: 1px; align-self: stretch; margin: 0 .15rem; background: var(--linha); }
    .wf-es-menu-tp { left: 0; top: 100%; width: 14rem; margin-top: .25rem; }
    .wf-es-dobra { flex-shrink: 0; width: 1.2rem; height: 1.4em; padding: 0; border: 0; background: none; color: var(--tinta-2); cursor: pointer; opacity: .35; transition: opacity .12s, transform .12s; font-size: .8rem; font-weight: 400; line-height: 1.6; }
    .wf-es-bloco:hover .wf-es-dobra, .wf-es-dobra.fechada { opacity: 1; }
    .wf-es-dobra.fechada { transform: rotate(-90deg); }
    .wf-es-op { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .3rem .45rem; border: 0; border-radius: .4rem; text-align: left; font-size: .8rem; color: var(--tinta); background: none; cursor: pointer; }
    .wf-es-op small { display: block; font-size: .68rem; color: var(--tinta-2); }
    .wf-es-op.sel, .wf-es-op:hover { background: var(--superficie-2); }
    .wf-es-ic { width: 1.7rem; height: 1.7rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; border: 1px solid var(--linha); border-radius: .35rem; background: var(--superficie-2); }
    .wf-es-vazio { padding: .4rem; font-size: .75rem; color: var(--tinta-2); }
    .wf-es-fim { flex: 1; min-height: 3rem; cursor: text; }
    /* Imagem no meio do texto */
    .wf-es-corpo.t-img { display: block; }
    .wf-es-fig { margin: .35rem 0; display: flex; flex-direction: column; align-items: center; gap: .15rem; }
    .wf-es-fig.al-e { align-items: flex-start; }
    .wf-es-fig.al-d { align-items: flex-end; }
    .wf-es-fig-caixa { position: relative; min-width: 3rem; max-width: 100%; border-radius: .4rem; cursor: pointer; }
    .wf-es-fig-caixa img { display: block; width: 100%; height: auto; border-radius: .4rem; user-select: none; }
    .wf-es-bloco.selecionado .wf-es-fig-caixa { outline: 2px solid var(--prim); outline-offset: 2px; }
    .wf-es-fig-vazio { display: flex; align-items: center; justify-content: center; min-height: 6rem; padding: 1rem; font-size: .8rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .4rem; background: var(--superficie-2); }
    .wf-es-fig-barra { position: absolute; top: .35rem; right: .35rem; display: flex; align-items: center; gap: .1rem; padding: .15rem; border-radius: .45rem; background: rgba(15, 18, 28, .82); color: #fff; opacity: 0; pointer-events: none; transition: opacity .12s; font-size: .72rem; }
    .wf-es-fig-caixa:hover .wf-es-fig-barra, .wf-es-bloco.selecionado .wf-es-fig-barra { opacity: 1; pointer-events: auto; }
    .wf-es-fig-barra button { min-width: 1.6rem; padding: .15rem .3rem; border: 0; border-radius: .3rem; background: none; color: inherit; cursor: pointer; line-height: 1.1; }
    .wf-es-fig-barra button:hover { background: rgba(255, 255, 255, .15); }
    .wf-es-fig-barra button.sel { background: rgba(99, 140, 255, .55); }
    .wf-es-fig-barra .wf-es-bsep { width: 1px; align-self: stretch; background: rgba(255, 255, 255, .25); margin: 0 .15rem; }
    .wf-es-fig-puxa { position: absolute; right: -5px; bottom: -5px; width: 12px; height: 12px; border-radius: 3px; background: var(--prim); border: 2px solid var(--superficie); cursor: nwse-resize; opacity: 0; touch-action: none; }
    .wf-es-fig-puxa.esq { right: auto; left: -5px; cursor: nesw-resize; }
    .wf-es-fig-caixa:hover .wf-es-fig-puxa, .wf-es-bloco.selecionado .wf-es-fig-puxa { opacity: 1; }
    .wf-es-leg { max-width: 100%; min-width: 8rem; padding: .1rem .2rem; font-size: .8em; text-align: center; color: var(--tinta-2); background: transparent; border: 0; outline: 0; }
    .wf-es-fig.al-e .wf-es-leg { text-align: left; }
    .wf-es-fig.al-d .wf-es-leg { text-align: right; }
    .wf-es-leg:placeholder-shown { opacity: 0; }
    .wf-es-bloco:hover .wf-es-leg:placeholder-shown, .wf-es-leg:focus { opacity: .8; }
    /* Modo foco: sem barras, texto centralizado, linhas fora do cursor apagadas (tela cheia quando o navegador deixa) */
    .wf-es-sair-foco { display: none; }
    .wf-es.wf-es-foco { position: relative; background: var(--superficie); }
    .wf-es.wf-es-foco:fullscreen { padding: 2.5rem 1rem 1rem; overflow: auto; }
    .wf-es.wf-es-foco > .wf-es-barra, .wf-es.wf-es-foco > .wf-es-sum { display: none !important; }
    .wf-es.wf-es-foco .wf-es-sair-foco { display: inline-flex; position: absolute; top: .4rem; right: .4rem; z-index: 40; padding: .2rem .6rem; font-size: .75rem; opacity: .55; }
    .wf-es.wf-es-foco .wf-es-sair-foco:hover { opacity: 1; }
    .wf-es.wf-es-foco .wf-es-folha { border-color: transparent; background: transparent; padding-top: 1.5rem; }
    .wf-es.wf-es-foco .wf-es-folha > .wf-es-bloco, .wf-es.wf-es-foco .wf-es-fim { width: 100%; max-width: 46rem; margin-left: auto; margin-right: auto; }
    .wf-es.wf-es-foco .wf-es-folha:focus-within > .wf-es-bloco:not(.foco):not(.selecionado) { opacity: .38; transition: opacity .2s; }
    .wf-es.wf-es-foco .wf-es-rodape { justify-content: center; opacity: .6; }
    .wf-es-rodape { display: flex; flex-wrap: wrap; gap: .2rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) {
        .wf-es { height: auto; min-height: 0; }
        .wf-es-folha { min-height: 14rem; max-height: 70vh; }
        .wf-es-lado { opacity: .55; width: 2.6rem; }
    }
</style>

<template x-for="b in blocosDe(aba)" :key="b.id">
    <x-diverso.item sec="b.secao" bid="b.id" :minw="220" :minh="140" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco">
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="flex flex-col h-full gap-3">
            <div class="flex-1 min-h-0">

                {{-- Texto: editor por blocos (lógica em public/js/escrita.js; o conteúdo fica em b.dados.texto como Markdown) --}}
                {{-- Pasta: guarda outros cartões (partials/pasta-cartao) --}}
                <template x-if="b.tipo === 'pasta'">
                    @include('partials.pasta-cartao')
                </template>
                {{-- Editor de imagem com camadas (partials/editor-imagem-cartao; editor em js/editor-imagem.js) --}}
                <template x-if="b.tipo === 'editorimg'">
                    @include('partials.editor-imagem-cartao')
                </template>
                {{-- Editor de vídeo (partials/editor-video-cartao; editor em js/editor-video.js) --}}
                <template x-if="b.tipo === 'editorvid'">
                    @include('partials.editor-video-cartao')
                </template>
                {{-- Estúdio de música (partials/estudio-musica-cartao; estúdio em js/estudio-musica.js) --}}
                <template x-if="b.tipo === 'musica'">
                    @include('partials.estudio-musica-cartao')
                </template>
                {{-- Gráfico e Hábitos (partials/grafico-cartao e habitos-cartao; js/graficos.js) --}}
                <template x-if="b.tipo === 'grafico'">
                    @include('partials.grafico-cartao')
                </template>
                <template x-if="b.tipo === 'habitos'">
                    @include('partials.habitos-cartao')
                </template>
                {{-- Documento (vem da Mente; partials/documento-cartao, js/documento-cartao.js) --}}
                <template x-if="b.tipo === 'documento'">
                    @include('partials.documento-cartao')
                </template>
                {{-- Editor de PDF (partials/editor-pdf-cartao; editor em js/editor-pdf.js) --}}
                <template x-if="b.tipo === 'editorpdf'">
                    @include('partials.editor-pdf-cartao')
                </template>
                {{-- Questões de concurso, Criador de conteúdo e RH (partials/*-cartao; js/cartoes-extra.js) --}}
                <template x-if="b.tipo === 'questoes'">
                    @include('partials.questoes-cartao')
                </template>
                <template x-if="b.tipo === 'criador'">
                    @include('partials.criador-cartao')
                </template>
                {{-- Google Drive, Gmail, YouTube e Lembretes por e-mail (partials/integracoes-cartoes; js/integracoes-cartoes.js) --}}
                @include('partials.integracoes-cartoes')
                <template x-if="b.tipo === 'rh'">
                    @include('partials.rh-cartao')
                </template>
                <template x-if="b.tipo === 'cenas'">
                    @include('partials.cenas-cartao')
                </template>
                {{-- Conversa com o Claude, que muda os cartões pedindo permissão (partials/claude-cartao; js/claude.js) --}}
                <template x-if="b.tipo === 'claude'">
                    @include('partials.claude-cartao')
                </template>

                <template x-if="b.tipo === 'texto'">
                    <div class="wf-es" x-data="WFEscrita(b, $data)" x-init="iniciar()" :class="{ 'wf-es-foco': modoFoco }"
                        @click.outside="foco = null; menu = null; opcBl = null; sumario = false; tipoMenu = false; limparSel()">

                        {{-- Imagens no meio do texto: o botão "Imagem", o comando "/imagem", colar (Ctrl+V) e arrastar usam este campo --}}
                        <input type="file" accept="image/*" multiple class="hidden wf-es-arq" tabindex="-1" aria-hidden="true"
                               @change="escolhidas($event.target.files); $event.target.value = ''">
                        <button type="button" class="btn-sec wf-es-sair-foco" x-show="modoFoco" x-cloak @click="alternarFoco()" title="Sair do modo foco">Sair do foco</button>

                        <div class="wf-es-barra">
                            <button type="button" class="btn-sec wf-opc-btn" :class="{ 'fechada': b.dados.opcoes === false }"
                                    @click="b.dados.opcoes = (b.dados.opcoes === false); sumario = false"
                                    :aria-expanded="b.dados.opcoes !== false"
                                    :title="b.dados.opcoes === false ? 'Mostrar as opções' : 'Recolher as opções'">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                <span x-text="b.dados.opcoes === false ? 'Mostrar opções' : 'Recolher opções'"></span>
                            </button>
                            <button type="button" class="btn-sec" :disabled="!podeDesfazer()" @click="desfazer()" title="Desfazer (Ctrl+Z)" aria-label="Desfazer">↶</button>
                            <button type="button" class="btn-sec" :disabled="!podeRefazer()" @click="refazer()" title="Refazer (Ctrl+Shift+Z)" aria-label="Refazer">↷</button>
                            <button type="button" class="btn-sec" @click="todo = !todo"
                                    :aria-pressed="todo"
                                    title="Mostra o texto inteiro numa caixa só, já selecionado, para copiar com Ctrl+C (atalho: Ctrl+A três vezes)"
                                    x-text="todo ? 'Voltar ao editor' : 'Selecionar tudo'"></button>
                            <button type="button" class="btn-sec" @click="copiar()" title="Copiar o texto inteiro (Markdown) para a área de transferência">Copiar tudo</button>
                            <button type="button" class="btn-sec" @click="alternarFoco()" :aria-pressed="modoFoco"
                                    title="Modo foco: tela cheia, sem barras e com a linha atual em destaque">Modo foco</button>
                        </div>

                        <div class="wf-es-barra" role="toolbar" aria-label="Ferramentas de escrita" x-show="b.dados.opcoes !== false && !todo" x-cloak>
                            {{-- As opções usam :selected (e não :value no select): o valor era aplicado antes de as opções existirem e o campo ficava vazio. --}}
                            <select class="campo wf-link-sel" aria-label="Tipo do bloco" title="Transforma a linha (ou as linhas selecionadas) · Ctrl+Alt+1/2/3 = títulos"
                                    @change="transformar($event.target.value)">
                                <option value="" :selected="tipoAtual() === ''" disabled hidden>Misto</option>
                                <template x-for="t in tipos" :key="t.id">
                                    <option :value="t.id" :selected="tipoAtual() === t.id" x-text="t.nome"></option>
                                </template>
                            </select>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" style="font-weight:700" :disabled="semAlvo()" @mousedown.prevent @click="envolver('**', '**')" title="Negrito (Ctrl+B)">N</button>
                            <button type="button" class="btn-sec" style="font-style:italic" :disabled="semAlvo()" @mousedown.prevent @click="envolver('*', '*')" title="Itálico (Ctrl+I)">I</button>
                            <button type="button" class="btn-sec" style="text-decoration:line-through" :disabled="semAlvo()" @mousedown.prevent @click="envolver('~~', '~~')" title="Tachado (Ctrl+Shift+X)">S</button>
                            <button type="button" class="btn-sec" :disabled="semAlvo()" @mousedown.prevent @click="envolver('==', '==')" title="Marca-texto (Ctrl+Shift+H)"><mark style="background:rgba(250,204,21,.45);color:inherit;padding:0 .2em;border-radius:.2rem">A</mark></button>
                            <button type="button" class="btn-sec" :disabled="semAlvo()" @mousedown.prevent @click="envolver('`', '`')" title="Código em linha (Ctrl+E)">&lt;/&gt;</button>
                            <button type="button" class="btn-sec" :disabled="!ultimo()" @mousedown.prevent @click="link()" title="Link (Ctrl+K)">Link</button>
                            <button type="button" class="btn-sec" @mousedown.prevent @click="escolherImagem()" title="Inserir imagem abaixo da linha atual (também dá para colar com Ctrl+V ou arrastar para o texto)">🖼 Imagem</button>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!titulos().length" @click="sumario = !sumario" :aria-expanded="sumario" title="Sumário pelos títulos">Sumário</button>
                            <button type="button" class="btn-sec" x-show="recolhidos.length" x-cloak @click="recolhidos = []" title="Expandir todas as seções">Expandir seções</button>
                            <select class="campo wf-link-sel" x-model="b.dados.tam" aria-label="Tamanho da letra">
                                <option value="p">Letra pequena</option>
                                <option value="m">Letra média</option>
                                <option value="g">Letra grande</option>
                            </select>
                            <span class="wf-es-sep"></span>
                            <label class="btn-sec" title="Importar um arquivo .md ou .txt (substitui o texto)">Importar
                                <input type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" class="hidden"
                                    @change="importar($event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="baixar()" title="Baixar como Markdown (.md)">Exportar .md</button>
                        </div>

                        <div class="wf-es-sum" x-show="sumario && b.dados.opcoes !== false && !todo" x-cloak>
                            <template x-for="o in titulos()" :key="o.x.id">
                                <button type="button" :style="`padding-left:${(Number(o.x.t.charAt(1)) - 1) * .8 + .4}rem`"
                                        @click="irPara(o.x.id)" x-text="o.x.x || '(sem título)'"></button>
                            </template>
                        </div>

                        <template x-if="todo">
                            <div class="wf-es-todo">
                                <p class="wf-es-vazio" style="padding:0">Texto inteiro selecionado: pressione <b>Ctrl+C</b> para copiar tudo. <b>Esc</b> volta ao editor.</p>
                                <textarea class="wf-es-todo-ta" readonly aria-label="Texto inteiro, selecionado para copiar"
                                          :value="b.dados.texto || ''"
                                          x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                          @keydown.escape.prevent="todo = false"
                                          @click="$el.select()"></textarea>
                            </div>
                        </template>

                        {{-- A folha é focável (tabindex) para receber o teclado quando há blocos selecionados --}}
                        <div class="wf-es-folha" tabindex="-1" x-show="!todo" :style="{ fontSize: { p: '.8rem', m: '.95rem', g: '1.2rem' }[b.dados.tam] || '.95rem' }"
                             @keydown.capture="ctrlA($event)" @keydown.self="teclaSel($event)">
                            <template x-for="(bl, i) in blocos" :key="bl.id">
                                <div class="wf-es-bloco" :data-es="bl.id" x-show="visivel(i)"
                                    :class="{ 'foco': foco === bl.id, 'selecionado': ehSel(bl.id), 'sobre': sobre === i && arrastar !== bl.id, 'arrastando': arrastar === bl.id }"
                                    :draggable="arrastar === bl.id ? 'true' : 'false'"
                                    @dragstart="dragIni($event, bl)" @dragover.prevent="sobre = i" @drop.prevent="soltarEm($event, i)"
                                    @dragend="arrastar = null; sobre = -1">

                                    <div class="wf-es-lado">
                                        <button type="button" class="wf-es-lbtn" title="Inserir bloco abaixo" aria-label="Inserir bloco abaixo" @click="addAbaixo(i)">+</button>
                                        <button type="button" class="wf-es-lbtn wf-es-grip" title="Clique: selecionar e abrir opções · Shift+clique: selecionar vários · arraste para mover" aria-label="Opções do bloco"
                                                @mousedown="arrastar = bl.id" @mouseup="arrastar = null"
                                                @click="grip($event, bl)">⋮⋮</button>
                                    </div>

                                    {{-- Menu do bloco: age sobre todos os blocos selecionados --}}
                                    <div class="wf-es-menu wf-es-menu-bl" x-show="opcBl === bl.id" x-cloak role="menu">
                                        <button type="button" class="menu-item" role="menuitem" :disabled="!podeMover(-1)" @click="moverSel(-1); opcBl = null">Subir</button>
                                        <button type="button" class="menu-item" role="menuitem" :disabled="!podeMover(1)" @click="moverSel(1); opcBl = null">Descer</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="duplicarSel(); opcBl = null">Duplicar</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="removerSel(); opcBl = null">Excluir</button>
                                        <p class="wf-es-menu-tit" x-text="selecao.length > 1 ? 'Transformar ' + selecao.length + ' blocos em' : 'Transformar em'"></p>
                                        <template x-for="t in tipos" :key="t.id">
                                            <button type="button" class="menu-item wf-es-menu-tp-it" role="menuitem" :class="{ 'atual': tipoAtual() === t.id }" @click="transformar(t.id)">
                                                <span class="wf-es-ic" x-text="t.ic"></span><span x-text="t.nome"></span>
                                            </button>
                                        </template>
                                    </div>

                                    {{-- Barra flutuante: aparece ao selecionar texto ou blocos --}}
                                    <div class="wf-es-bolha" x-show="bolhaEm(bl.id)" x-cloak role="toolbar" aria-label="Formatar seleção" @mousedown.prevent>
                                        <div class="relative">
                                            <button type="button" class="wf-es-bb wf-es-bb-tipo" :aria-expanded="tipoMenu" @click="tipoMenu = !tipoMenu" title="Transformar em…">
                                                <span x-text="rotuloTipo()"></span> <span aria-hidden="true">▾</span>
                                            </button>
                                            <div class="wf-es-menu wf-es-menu-tp" x-show="tipoMenu" x-cloak role="menu">
                                                <template x-for="t in tipos" :key="t.id">
                                                    <button type="button" class="wf-es-op" role="menuitem" :class="{ 'sel': tipoAtual() === t.id }" @click="transformar(t.id)">
                                                        <span class="wf-es-ic" x-text="t.ic"></span><span x-text="t.nome"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        <template x-if="podeFormatar()">
                                            <span class="contents">
                                                <span class="wf-es-bsep"></span>
                                                <button type="button" class="wf-es-bb" style="font-weight:700" @click="envolver('**', '**')" title="Negrito (Ctrl+B)">N</button>
                                                <button type="button" class="wf-es-bb" style="font-style:italic" @click="envolver('*', '*')" title="Itálico (Ctrl+I)">I</button>
                                                <button type="button" class="wf-es-bb" style="text-decoration:line-through" @click="envolver('~~', '~~')" title="Tachado (Ctrl+Shift+X)">S</button>
                                                <button type="button" class="wf-es-bb" @click="envolver('==', '==')" title="Marca-texto (Ctrl+Shift+H)"><mark style="background:rgba(250,204,21,.45);color:inherit;padding:0 .2em;border-radius:.2rem">A</mark></button>
                                                <button type="button" class="wf-es-bb" @click="envolver('`', '`')" title="Código em linha (Ctrl+E)">&lt;/&gt;</button>
                                                <button type="button" class="wf-es-bb" x-show="!selecao.length" @click="link()" title="Link (Ctrl+K)">Link</button>
                                            </span>
                                        </template>
                                    </div>

                                    <div class="wf-es-corpo" :class="'t-' + bl.t + (bl.f ? ' feito' : '')" :style="`margin-left:${(bl.n || 0) * 1.5}rem`">
                                        <template x-if="/^h[1-3]$/.test(bl.t)">
                                            <button type="button" class="wf-es-dobra" :class="{ 'fechada': recolhidos.includes(bl.id) }" @click="dobrar(bl)"
                                                    :aria-expanded="!recolhidos.includes(bl.id)" title="Recolher ou expandir a seção" aria-label="Recolher ou expandir a seção">▾</button>
                                        </template>
                                        <template x-if="bl.t === 'ul'"><span class="wf-es-marca" x-text="['•', '◦', '▪', '•'][bl.n || 0]"></span></template>
                                        <template x-if="bl.t === 'ol'"><span class="wf-es-marca" x-text="numero(i) + '.'"></span></template>
                                        <template x-if="bl.t === 'todo'"><input type="checkbox" class="wf-es-chk" :checked="bl.f" @change="bl.f = $event.target.checked" aria-label="Tarefa concluída"></template>
                                        <template x-if="bl.t === 'callout'"><span class="wf-es-marca">💡</span></template>

                                        <template x-if="bl.t === 'hr'"><hr class="wf-es-hr"></template>

                                        {{-- Imagem: clique seleciona (Delete apaga, Enter cria uma linha abaixo); barra com alinhamento e tamanho; puxador no canto --}}
                                        <template x-if="bl.t === 'img'">
                                            <figure class="wf-es-fig" :class="'al-' + (bl.a || 'c')">
                                                <div class="wf-es-fig-caixa" :style="`width:${bl.w || 100}%`" @click="selUm(bl.id)">
                                                    <template x-if="srcImg(bl)">
                                                        <img :src="srcImg(bl)" :alt="bl.x || 'Imagem'" draggable="false" loading="lazy">
                                                    </template>
                                                    <template x-if="!srcImg(bl)">
                                                        <div class="wf-es-fig-vazio" x-text="enviando.includes(bl.id) ? 'Enviando imagem…' : 'Imagem não encontrada'"></div>
                                                    </template>
                                                    <div class="wf-es-fig-barra" role="toolbar" aria-label="Imagem" @click.stop @mousedown.stop>
                                                        <button type="button" :class="{ 'sel': bl.a === 'e' }" @click="alinharImg(bl, 'e')" title="Alinhar à esquerda" aria-label="Alinhar à esquerda">⇤</button>
                                                        <button type="button" :class="{ 'sel': (bl.a || 'c') === 'c' }" @click="alinharImg(bl, 'c')" title="Centralizar" aria-label="Centralizar">↔</button>
                                                        <button type="button" :class="{ 'sel': bl.a === 'd' }" @click="alinharImg(bl, 'd')" title="Alinhar à direita" aria-label="Alinhar à direita">⇥</button>
                                                        <span class="wf-es-bsep"></span>
                                                        <template x-for="w in [25, 50, 75, 100]" :key="w">
                                                            <button type="button" :class="{ 'sel': (bl.w || 100) === w }" @click="larguraImg(bl, w)" :title="'Largura ' + w + '%'" x-text="w + '%'"></button>
                                                        </template>
                                                        <span class="wf-es-bsep"></span>
                                                        <button type="button" @click="remover(i)" title="Excluir a imagem" aria-label="Excluir a imagem">🗑</button>
                                                    </div>
                                                    <span class="wf-es-fig-puxa" :class="bl.a === 'd' ? 'esq' : ''" @pointerdown="puxarImg($event, bl)" @click.stop title="Arraste para mudar o tamanho" aria-hidden="true"></span>
                                                </div>
                                                <input class="wf-es-leg" :value="bl.x" placeholder="Legenda (opcional)" aria-label="Legenda da imagem"
                                                       :style="`width:${bl.w || 100}%`"
                                                       @focus="limparSel(); foco = null" @input="legenda($event, bl)"
                                                       @keydown.enter.prevent="depoisDe(i)" @keydown.escape.prevent="selUm(bl.id)">
                                            </figure>
                                        </template>

                                        <template x-if="bl.t !== 'hr' && bl.t !== 'img'">
                                            <div class="wf-es-txt">
                                                <template x-if="bl.t === 'code'">
                                                    <input class="wf-es-lang" :value="bl.l" placeholder="linguagem (opcional)" aria-label="Linguagem do código"
                                                        @input="bl.l = $event.target.value.replace(/[^\w+#.-]/g, '')">
                                                </template>
                                                <div class="wf-es-ver" x-show="bl.t !== 'code' && foco !== bl.id" @click="ver($event, bl)" x-html="html(bl)"></div>
                                                <textarea class="wf-es-ta" rows="1" x-show="bl.t === 'code' || foco === bl.id"
                                                        :spellcheck="bl.t !== 'code'" autocomplete="off" aria-label="Texto do bloco"
                                                        :placeholder="bl.t === 'p' ? 'Digite “/” para ver os comandos…' : ''"
                                                        :value="bl.x"
                                                        x-init="$nextTick(() => fit($el))"
                                                        @focus="aoFocar(bl, $el)"
                                                        @select="sel($event)" @keyup="sel($event)" @mouseup="sel($event)"
                                                        @input="entrada($event, bl)" @keydown="tecla($event, bl, i)" @paste="colar($event, bl, i)"></textarea>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="menu && menu.id === bl.id">
                                        <div class="wf-es-menu" role="listbox" aria-label="Comandos">
                                            <template x-for="(it, k) in itensMenu()" :key="it.id">
                                                <button type="button" class="wf-es-op" role="option" :class="{ 'sel': menu && menu.i === k }"
                                                        @mousedown.prevent="aplicar(it.id, bl)" @mouseenter="menu.i = k">
                                                    <span class="wf-es-ic" x-text="it.ic"></span>
                                                    <span><b x-text="it.nome"></b><small x-text="it.desc"></small></span>
                                                </button>
                                            </template>
                                            <p class="wf-es-vazio" x-show="!itensMenu().length">Nada encontrado</p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div class="wf-es-fim" @click="aoFim()" @dragover.prevent @drop.prevent="soltarEm($event, blocos.length, true)" aria-hidden="true"></div>
                        </div>

                        <div class="wf-es-rodape" aria-live="polite">
                            <span x-text="palavras() + ' palavra(s) · ' + caracteres() + ' caractere(s) · ~' + leitura() + ' min de leitura'"></span>
                            <span x-show="tarefas().total" x-cloak x-text="'Tarefas: ' + tarefas().feitas + '/' + tarefas().total"></span>
                            <span x-show="selecao.length" x-cloak x-text="selecao.length + ' bloco(s) selecionado(s)'"></span>
                            <span x-show="b.dados.opcoes !== false" x-cloak>Dica: “/” abre os comandos · cole ou arraste imagens no texto · Esc seleciona o bloco · Shift+↑/↓ amplia · Ctrl+Alt+1/2/3 vira título</span>
                            <span x-show="dica" x-cloak style="color:var(--prim-forte)" x-text="dica"></span>
                        </div>
                    </div>
                </template>

                {{-- Tabela --}}
                <template x-if="b.tipo === 'tabela'">
                    <div class="wf-xl" data-xl x-init="tbGarantir(b)" @keydown="tbTeclaRaiz($event, b)">

                        <div class="wf-xl-barra" role="toolbar" aria-label="Ferramentas da tabela">
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).hist.length" @click="tbDesfazer(b)" title="Desfazer (Ctrl+Z)">↶</button>
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).refaz.length" @click="tbRefazer(b)" title="Refazer (Ctrl+Y)">↷</button>
                            <span class="wf-xl-sep"></span>

                            <select class="campo" aria-label="Formato da coluna" title="Formato dos números da coluna" @change="tbFmt(b, $event.target.value)">
                                <template x-for="f in tbFormatos" :key="f.id">
                                    <option :value="f.id" :selected="tbCol(b, tbUI(b).c).fmt === f.id" x-text="f.nome"></option>
                                </template>
                            </select>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'e')" title="Alinhar coluna à esquerda">⇤</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'c')" title="Centralizar coluna">↔</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'd')" title="Alinhar coluna à direita">⇥</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, '')" title="Alinhamento automático (números à direita)">Auto</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" style="font-weight:700" @click="tbNegrito(b)" title="Negrito (Ctrl+B)">N</button>
                            <input type="color" class="wf-cor !w-6 !h-6" value="#fde68a" @change="tbFundo(b, $event.target.value)" aria-label="Cor de fundo da seleção" title="Cor de fundo da seleção">
                            <button type="button" class="btn-sec" @click="tbFundo(b, '')" title="Remover cor de fundo">Sem cor</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbOrdenar(b, 1)" title="Ordenar a coluna ativa de A a Z">A→Z</button>
                            <button type="button" class="btn-sec" @click="tbOrdenar(b, -1)" title="Ordenar a coluna ativa de Z a A">Z→A</button>
                            <button type="button" class="btn-sec" @click="tbAutoSoma(b)" title="Soma automática dos números acima (ou à esquerda)">Σ</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'baixo')" title="Preencher para baixo (Ctrl+D)">Preencher ↓</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'direita')" title="Preencher para a direita (Ctrl+R)">Preencher →</button>
                            <button type="button" class="btn-sec" @click="tbLimpar(b)" title="Limpar o conteúdo da seleção (Delete)">Limpar</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbLinhaIns(b, tbIntervalo(b).i2 + 1)" title="Inserir linha abaixo da seleção">+ Linha aqui</button>
                            <button type="button" class="btn-sec" @click="tbColIns(b, tbIntervalo(b).j2 + 1)" title="Inserir coluna à direita da seleção">+ Coluna aqui</button>
                            <select class="campo" aria-label="Rodapé de totais da coluna" title="Total no rodapé da coluna" @change="tbRodapeSet(b, $event.target.value)">
                                <template x-for="a in tbAgregados" :key="a.id">
                                    <option :value="a.id" :selected="((b.dados.rodape || [])[tbUI(b).c] || '') === a.id" x-text="a.nome"></option>
                                </template>
                            </select>
                            <input class="campo" type="search" style="width:8rem" placeholder="Filtrar linhas…" aria-label="Filtrar linhas" x-model="tbUI(b).filtro">
                            <span class="wf-xl-sep"></span>

                            <label class="btn-sec" title="Importar um arquivo CSV/TSV (substitui a tabela)">Importar CSV
                                <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="tbImportar(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="tbExportar(b)" title="Baixar como CSV (abre no Excel)">Exportar CSV</button>
                        </div>

                        {{-- Barra de fórmulas --}}
                        <div class="wf-xl-fx">
                            <span class="wf-xl-ref" x-text="tbRef(b)" title="Célula ou intervalo selecionado"></span>
                            <span class="texto-2 text-xs">fx</span>
                            <input class="campo" data-barra spellcheck="false" autocomplete="off"
                                   placeholder="Valor ou fórmula (ex.: =SOMA(B1:B5) ou =A1*B1)" aria-label="Conteúdo da célula ativa"
                                   :value="tbRaw(b)" @focus="tbUI(b).sujo = false" @input="tbBarra(b, $event.target.value)"
                                   @keydown.enter.prevent="tbFocarAtivo($event, b)">
                        </div>

                        {{-- Grade --}}
                        <div class="wf-xl-rolagem" @copy="tbCopiar($event, b, false)" @cut="tbCopiar($event, b, true)" @paste="tbColar($event, b)">
                            <table class="wf-tab wf-xl-tab" :style="tbLarg(b)">
                                <colgroup>
                                    <col style="width:44px">
                                    <template x-for="(c, j) in b.dados.cab" :key="j"><col :style="'width:' + tbColW(b, j) + 'px'"></template>
                                    <col style="width:32px">
                                </colgroup>
                                <thead>
                                    <tr class="letras">
                                        <th class="canto" @click="tbSelTudo(b)" title="Selecionar tudo"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th class="letra" :class="{ 'sel': tbColAtiva(b, j) }" @click="tbSelCol(b, j, $event)" title="Selecionar coluna (Shift para ampliar)">
                                                <span x-text="tbLetra(j)"></span>
                                                <i class="wf-xl-grip" title="Arraste para mudar a largura (duplo clique restaura)"
                                                   @pointerdown.stop.prevent="tbRedim($event, b, j)" @click.stop @dblclick.stop="tbAutoLarg(b, j)"></i>
                                            </th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                    <tr class="nomes">
                                        <th class="canto"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th><div class="flex items-center">
                                                <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                                <button type="button" class="mini-btn perigo" x-show="b.dados.cab.length > 1" title="Remover coluna" @click="tbColRem(b, j)">✕</button>
                                            </div></th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="i in tbVisiveis(b)" :key="i">
                                        <tr>
                                            <th class="num" :class="{ 'sel': tbLinhaAtiva(b, i) }" @click="tbSelLinha(b, i, $event)" x-text="i + 1" title="Selecionar linha (Shift para ampliar)"></th>
                                            <template x-for="(c, j) in b.dados.cab" :key="j">
                                                <td :class="{ 'sel': tbSelecionada(b, i, j), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                    <input class="wf-cel" spellcheck="false" autocomplete="off"
                                                           :data-r="i" :data-c="j" :style="tbInEstilo(b, i, j)" :aria-label="'Célula ' + tbLetra(j) + (i + 1)"
                                                           :value="tbValor(b, i, j)"
                                                           @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)" @input="tbInput(b, i, j, $event)"
                                                           @keydown="tbTecla($event, b, i, j)" @mousedown="tbDown($event, b, i, j)">
                                                </td>
                                            </template>
                                            <td style="border:0;text-align:center"><button type="button" class="mini-btn perigo" title="Remover linha" @click="tbLinhaRem(b, i)">✕</button></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot x-show="tbTemRodape(b)">
                                    <tr>
                                        <th class="num" title="Totais (consideram as linhas visíveis)">Σ</th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j"><td x-text="tbRodape(b, j)"></td></template>
                                        <td style="border:0"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <button type="button" class="link-prim text-sm" @click="tbLinhaAdd(b)">+ Linha</button>
                            <button type="button" class="link-prim text-sm" @click="tbColAdd(b)">+ Coluna</button>
                            <div class="wf-xl-status" aria-live="polite">
                                <span x-text="b.dados.linhas.length + ' linha(s) × ' + b.dados.cab.length + ' coluna(s)' + (tbUI(b).filtro ? ' · ' + tbVisiveis(b).length + ' visível(is)' : '')"></span>
                                <span x-show="tbStats(b)" x-text="tbStats(b)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Lista: o texto de cada item quebra para a linha de baixo (o campo cresce), em vez de ficar cortado na largura do cartão --}}
                <template x-if="b.tipo === 'lista'">
                    <div class="space-y-2">
                        <input class="campo" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $el)">
                        <ul class="space-y-1.5">
                            <template x-for="i in b.dados.itens" :key="i.id">
                                <li class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" class="mt-2 shrink-0" x-model="i.f" aria-label="Item concluído">
                                    <textarea class="wf-cel wf-item-txt" :class="{ 'line-through texto-2': i.f }" rows="1" spellcheck="true"
                                              aria-label="Texto do item" x-model="i.t"
                                              x-init="const aj = () => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; };
                                                      $nextTick(aj); new ResizeObserver(aj).observe($el.parentElement)"
                                              @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                              @keydown.enter.prevent="$el.blur()"></textarea>
                                    <button type="button" class="mini-btn perigo mt-1.5 shrink-0" title="Remover" @click="remItem(b, i.id)">✕</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Código: repositórios (GitHub / VS Code) e editor de código --}}
                <template x-if="b.tipo === 'codigo'">
                    @include('partials.codigo-cartao')
                </template>

                {{-- Imagem: só exibe, troca e baixa (sem editor nem criador de imagens).
                     Usa imagens[b.id], lerImagem() e ajustarImagem() do diverso.js. --}}
                <template x-if="b.tipo === 'imagem'">
                    <div data-img class="wf-img w-full" x-data="{ erro: '' }"
                         @dragover.prevent
                         @drop.prevent="const f = Array.from($event.dataTransfer?.files || []).find(x => /^image\//.test(x.type));
                                        if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">

                        <p class="wf-img-erro" x-show="erro" x-cloak role="alert" x-text="erro"></p>

                        <template x-if="imagens[b.id]">
                            <div>
                                <div class="wf-img-acoes">
                                    <label class="btn-sec" title="Escolher outro arquivo">Trocar
                                        <input type="file" accept="image/*" class="hidden"
                                               @change="const f = $event.target.files[0]; $event.target.value = '';
                                                        if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">
                                    </label>
                                    <a class="btn-sec" :href="/^data:image\//.test(imagens[b.id] || '') ? imagens[b.id] : '#'" download="imagem.png" title="Baixar a imagem atual">Baixar</a>
                                </div>
                                <img :src="imagens[b.id]" :alt="b.titulo"
                                     class="block w-full h-auto rounded-lg"
                                     @load="ajustarImagem(b, $el)">
                            </div>
                        </template>

                        <div class="wf-img-vazio" x-show="!imagens[b.id]">
                            <label class="btn-sec">Escolher imagem
                                <input type="file" accept="image/*" class="hidden"
                                       @change="const f = $event.target.files[0]; $event.target.value = '';
                                                if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">
                            </label>
                        </div>
                    </div>
                </template>

                {{-- Cartão ativo (de outra página) --}}
                @include('areas.partials.bloco-vivo')

                {{-- Banco de dados: .sql (MySQL/MariaDB, PostgreSQL, SQLite) ou .csv lido no navegador, com diagrama, dados e
                     estatísticas. O conteúdo é montado por public/js/banco-sql.js fora do Alpine (aguenta milhões de linhas);
                     o trabalho pesado fica em public/js/banco-sql-worker.js. --}}
                <template x-if="b.tipo === 'banco'">
                    <div class="bd h-full flex flex-col gap-2" x-init="bdMontar(b, $el)" x-effect="bdSinc(b, $el)"></div>
                </template>

                {{-- Mapa mental e diagramas (BPMN, fluxogramas, ER/UML). Lógica em public/js/diagrama.js (métodos dd*). --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="wf-dd h-full" data-dd x-init="dd(b)">

                        {{-- Topo do mapa (botão de recolher + barras + propriedades): acompanha a rolagem da página e do
                             cartão, para as opções ficarem à mão mesmo longe do começo do mapa (diagrama.js, acompanharTopo) --}}
                        <div class="wf-dd-topo" data-dd-topo>

                        {{-- Recolher / mostrar todas as opções: libera a tela para ver o diagrama inteiro.
                             O estado fica direto em b.dados.opcoes (false = recolhidas), sem depender de funções externas. --}}
                        <div class="wf-dd-barra">
                            <button type="button" class="btn-sec wf-opc-btn" :class="{ 'fechada': b.dados.opcoes === false }"
                                    @click="b.dados.opcoes = (b.dados.opcoes === false); dd(b).menu = false;
                                            if (b.dados.opcoes === false && ui[b.id]) { ui[b.id].on = false; ui[b.id].borr = false; ui[b.id].vivo = ''; ui[b.id].cx = null; ui[b.id].cy = null; }"
                                    :aria-expanded="b.dados.opcoes !== false"
                                    :title="b.dados.opcoes === false ? 'Mostrar as opções' : 'Recolher as opções'">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                <span x-text="b.dados.opcoes === false ? 'Mostrar opções' : 'Recolher opções'"></span>
                            </button>
                        </div>

                        {{-- Tudo abaixo (barras, propriedades e dica) some quando as opções são recolhidas --}}
                        <div class="contents" x-show="b.dados.opcoes !== false" x-cloak>

                        {{-- Barra principal --}}
                        <div class="wf-dd-barra" role="toolbar" aria-label="Ferramentas do diagrama">
                            <div class="relative" @click.outside="dd(b).menu = false" @keydown.escape.window="dd(b).menu = false">
                                <button type="button" class="btn-sec" @click="dd(b).menu = !dd(b).menu" aria-haspopup="menu" :aria-expanded="dd(b).menu">+ Elemento</button>
                                <div class="wf-dd-menu" x-show="dd(b).menu" x-cloak role="menu">
                                    <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                        <div>
                                            <p class="wf-dd-menu-grupo" x-text="g.grupo"></p>
                                            <template x-for="it in g.itens" :key="it.id">
                                                <button type="button" class="menu-item" role="menuitem" @click="ddAdicionar(b, it.id, $event)" x-text="it.nome"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!dd(b).hist.length" @click="ddDesfazer(b)" title="Desfazer (Ctrl+Z)">↶ Desfazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).refaz.length" @click="ddRefazer(b)" title="Refazer (Ctrl+Shift+Z)">↷ Refazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length" @click="ddDuplicar(b)" title="Duplicar (Ctrl+D)">Duplicar</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length && !dd(b).selLig" @click="ddRemoverSel(b)" title="Excluir (Delete)">Excluir</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'frente')" title="Trazer para a frente">Frente</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'tras')" title="Enviar para trás">Trás</button>

                            <template x-if="ddSelNos(b).length > 1">
                                <span class="flex flex-wrap items-center gap-1">
                                    <span class="wf-dd-sep"></span>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'esq')" title="Alinhar à esquerda">⇤</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cx')" title="Centralizar na horizontal">↔</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dir')" title="Alinhar à direita">⇥</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'topo')" title="Alinhar ao topo">⤒</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cy')" title="Centralizar na vertical">↕</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'base')" title="Alinhar à base">⤓</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dh')" title="Distribuir na horizontal (3 ou mais)">⇹</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dv')" title="Distribuir na vertical (3 ou mais)">⇳</button>
                                </span>
                            </template>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1 / 1.2)" title="Diminuir o zoom" aria-label="Diminuir o zoom">−</button>
                            <button type="button" class="btn-sec" style="min-width:3.2rem" @click="ddZoomSet(b, 1)" title="Voltar a 100%" x-text="Math.round(dd(b).z * 100) + '%'"></button>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1.2)" title="Aumentar o zoom" aria-label="Aumentar o zoom">+</button>
                            <button type="button" class="btn-sec" @click="ddAjustar(b, $event)" title="Ajustar o diagrama à janela">Ajustar</button>

                            <span class="wf-dd-sep"></span>
                            <label class="wf-dd-chk" title="Mostrar a grade de pontos"><input type="checkbox" :checked="dd(b).grade" @change="dd(b).grade = $event.target.checked"> Grade</label>
                            <label class="wf-dd-chk" title="Encaixar elementos na grade"><input type="checkbox" :checked="dd(b).snap" @change="dd(b).snap = $event.target.checked"> Ímã</label>
                            <select class="campo" aria-label="Estilo padrão dos novos conectores" title="Estilo dos novos conectores"
                                    @change="b.dados.ligPadrao = $event.target.value">
                                <template x-for="s in ddEstilos" :key="s.id">
                                    <option :value="s.id" :selected="(b.dados.ligPadrao || 'ortogonal') === s.id" x-text="'Conector: ' + s.nome"></option>
                                </template>
                            </select>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'png')" title="Baixar como imagem PNG">PNG</button>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'svg')" title="Baixar como SVG">SVG</button>
                        </div>

                        {{-- Desenho livre (continua como no mapa mental) --}}
                        <div class="wf-dd-barra">
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && !ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && !ui[b.id]?.borr)" @click="penModo(b, 'lapis')">✏️ Desenhar</button>
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && ui[b.id]?.borr)" @click="penModo(b, 'borracha')">Borracha</button>
                            <template x-if="ui[b.id]?.on">
                                <span class="flex items-center gap-2">
                                    <input type="color" class="wf-cor !w-6 !h-6" x-model="ui[b.id].cor" aria-label="Cor do traço" title="Cor do traço">
                                    <input type="range" min="1" max="16" x-model.number="ui[b.id].larg" aria-label="Espessura do traço" title="Espessura" style="width:6rem">
                                </span>
                            </template>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penDesfazer(b)">Desfazer traço</button>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penLimpar(b)">Limpar desenho</button>
                        </div>

                        {{-- Propriedades do elemento selecionado --}}
                        <template x-for="n0 in (ddNoSel(b) ? [ddNoSel(b)] : [])" :key="n0.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Forma
                                    <select class="campo" @change="ddMudarForma(b, n0, $event.target.value)">
                                        <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                            <optgroup :label="g.grupo">
                                                <template x-for="it in g.itens" :key="it.id">
                                                    <option :value="it.id" :selected="(n0.forma || 'mapa') === it.id" x-text="it.nome"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Texto
                                    <input class="campo" style="width:9rem" :value="n0.t" @focus="ddGravar(b)" @input="n0.t = $event.target.value" aria-label="Texto do elemento">
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="n0.cor || '#a78bfa'" @input="ddCor(b, $event.target.value)" aria-label="Cor do elemento">
                                </label>
                                <template x-if="!ddGeo(n0).d.fixo">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <label class="wf-dd-campo">Largura
                                            <input type="number" class="campo" style="width:4.5rem" min="40" max="1600" :value="ddGeo(n0).w" @change="ddTam(b, 'w', $event.target.value)">
                                        </label>
                                        <label class="wf-dd-campo" x-show="n0.forma !== 'entidade'">Altura
                                            <input type="number" class="campo" style="width:4.5rem" min="24" max="1600" :value="ddGeo(n0).h" @change="ddTam(b, 'h', $event.target.value)">
                                        </label>
                                    </span>
                                </template>
                                <button type="button" class="btn-sec" @click="ddFilho(b, n0)" title="Criar elemento ligado a este (Tab)">+ Filho</button>
                                <button type="button" class="btn-sec" x-show="n0.pai" @click="ddDesligarPai(b, n0)" title="Soltar este tópico do pai">Desligar do pai</button>
                                <template x-if="n0.forma === 'entidade'">
                                    <label class="wf-dd-campo" style="flex-basis:100%;align-items:flex-start">Campos
                                        <textarea class="campo" rows="3" style="flex:1;min-width:12rem;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.75rem"
                                                  placeholder="PK id&#10;FK cliente_id&#10;nome"
                                                  :value="n0.campos || ''" @focus="ddGravar(b)" @input="n0.campos = $event.target.value" aria-label="Campos da entidade (um por linha; PK, FK ou UK no início)"></textarea>
                                    </label>
                                </template>
                            </div>
                        </template>

                        {{-- Propriedades do conector selecionado --}}
                        <template x-for="l in (ddLigSel(b) ? [ddLigSel(b)] : [])" :key="l.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Tipo
                                    <select class="campo" @change="ddPreset(b, l, $event.target.value)">
                                        <option value="" :selected="ddPresetAtual(l) === ''">Personalizado</option>
                                        <template x-for="p in ddPresets" :key="p.id">
                                            <option :value="p.id" :selected="ddPresetAtual(l) === p.id" x-text="p.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Rótulo
                                    <input class="campo" data-rot style="width:8rem" :value="l.rot || ''" @input="l.rot = $event.target.value" placeholder="ex.: sim, 1:N" aria-label="Rótulo do conector">
                                </label>
                                <label class="wf-dd-campo">Caminho
                                    <select class="campo" @change="l.estilo = $event.target.value">
                                        <template x-for="s in ddEstilos" :key="s.id">
                                            <option :value="s.id" :selected="(l.estilo || 'ortogonal') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Traço
                                    <select class="campo" @change="l.traco = $event.target.value">
                                        <template x-for="s in ddTracos" :key="s.id">
                                            <option :value="s.id" :selected="(l.traco || 'solido') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Início
                                    <select class="campo" @change="l.ini = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.ini == null ? 'nenhum' : l.ini) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Fim
                                    <select class="campo" @change="l.fim = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.fim == null ? 'seta' : l.fim) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="/^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : '#94a3b8'" @input="l.cor = $event.target.value" aria-label="Cor do conector">
                                </label>
                                <label class="wf-dd-campo">Espessura
                                    <input type="range" min="1" max="6" step="0.5" style="width:5rem" :value="l.w || 1.8" @input="l.w = Number($event.target.value)" aria-label="Espessura do conector">
                                </label>
                                <button type="button" class="btn-sec" @click="ddInverter(b, l)" title="Trocar origem e destino">⇄ Inverter</button>
                                <button type="button" class="btn-sec" @click="ddRemoverLig(b)">Excluir conector</button>
                            </div>
                        </template>

                        <p class="wf-dd-dica">Duplo clique no vazio cria um elemento · arraste a bolinha de um elemento selecionado até outro (ou até o vazio) para ligar · Tab cria um filho · Enter edita o texto · Ctrl+Z desfaz · Ctrl+roda do mouse dá zoom</p>

                        </div>{{-- fim das opções recolhíveis --}}
                        </div>{{-- fim do topo que acompanha a rolagem --}}

                        {{-- Tela --}}
                        <div class="wf-mapa-col">
                            <div :style="`width:${mmTam(b).w * dd(b).z}px;height:${mmTam(b).h * dd(b).z}px;`">
                                <div class="wf-dd-lona" :class="{ 'grade': dd(b).grade }" tabindex="0" role="application" aria-label="Área do diagrama"
                                     :style="`width:${mmTam(b).w}px;height:${mmTam(b).h}px;transform:scale(${dd(b).z});transform-origin:0 0;`"
                                     @pointerdown="ddLonaDown($event, b)" @dblclick="ddLonaDbl($event, b)" @contextmenu="ddMenu($event, b)"
                                     @keydown="ddTecla($event, b)" @wheel="ddWheel($event, b)">

                                    {{-- Ligações (pai→filho dos mapas e conectores) --}}
                                    <svg class="wf-dd-camada" style="z-index:3;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h"
                                         aria-label="Conectores" x-html="ddLigSvg(b)"
                                         @pointerdown="ddLigDown($event, b)" @dblclick="ddLigDbl($event, b)"></svg>

                                    {{-- Desenho livre --}}
                                    <svg class="wf-dd-camada" aria-label="Desenho livre" :width="mmTam(b).w" :height="mmTam(b).h"
                                         :style="{ zIndex: ui[b.id]?.on ? 8 : 2, pointerEvents: ui[b.id]?.on ? 'auto' : 'none', cursor: ui[b.id]?.borr ? 'cell' : 'crosshair', touchAction: ui[b.id]?.on ? 'none' : 'auto' }"
                                         @pointerdown="penDown($event, b)" @pointermove="penHover($event, b)" @pointerleave="penFora(b)">
                                        <g x-html="tracosSvg(b)"></g>
                                        <path :d="ui[b.id]?.vivo || ''" fill="none" :stroke="ui[b.id]?.cor" :stroke-width="ui[b.id]?.larg" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle x-show="ui[b.id]?.on && ui[b.id]?.borr && ui[b.id]?.cx != null" :cx="ui[b.id]?.cx ?? 0" :cy="ui[b.id]?.cy ?? 0" :r="penRaio(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.2" stroke-dasharray="3 3" style="pointer-events:none"/>
                                    </svg>

                                    {{-- Linha provisória enquanto se arrasta uma ligação --}}
                                    <svg class="wf-dd-camada" style="z-index:5;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true" x-show="dd(b).liga">
                                        <path :d="ddLiveD(b)" fill="none" stroke="var(--prim)" stroke-width="2" stroke-dasharray="6 4" stroke-linecap="round"/>
                                    </svg>

                                    {{-- Elementos --}}
                                    <template x-for="n in ddNos(b)" :key="n.id">
                                        <div class="wf-dd-no" :class="ddClasse(b, n)" :style="ddNoEstilo(n)"
                                             @pointerdown="ddNoDown($event, b, n)" @dblclick.stop="ddEditar(b, n)">
                                            <svg class="wf-dd-forma" :width="ddGeo(n).w" :height="ddGeo(n).h" aria-hidden="true" x-html="ddFormaSvg(n)"></svg>

                                            <template x-if="n.forma === 'entidade'">
                                                <div class="wf-dd-ent">
                                                    <div class="wf-dd-ent-t" x-text="n.t"></div>
                                                    <div class="wf-dd-ent-corpo">
                                                        <template x-for="(c, i) in ddCampos(n)" :key="i">
                                                            <div class="wf-dd-ent-c"><b x-show="c.tag" x-text="c.tag"></b><span x-text="c.txt"></span></div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="wf-dd-txt" x-show="n.forma !== 'entidade' && dd(b).edit !== n.id"
                                                 :class="{ 'fora': ddGeo(n).d.fora, 'raia': n.forma === 'raia' }" x-text="n.t"></div>

                                            <template x-if="dd(b).edit === n.id">
                                                <textarea class="wf-dd-edit" :class="{ 'fora': ddGeo(n).d.fora, 'ent': n.forma === 'entidade' }"
                                                          x-model="n.t" aria-label="Texto do elemento"
                                                          x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                          @focus="ddGravar(b)" @pointerdown.stop @dblclick.stop
                                                          @keydown="ddTeclaEdit($event, b)" @blur="ddFimEdit(b, n)"></textarea>
                                            </template>

                                            <button type="button" class="wf-dd-h t" tabindex="-1" aria-label="Ligar a partir do topo" @pointerdown="ddLigarDown($event, b, n, 't')"></button>
                                            <button type="button" class="wf-dd-h b" tabindex="-1" aria-label="Ligar a partir de baixo" @pointerdown="ddLigarDown($event, b, n, 'b')"></button>
                                            <button type="button" class="wf-dd-h e" tabindex="-1" aria-label="Ligar a partir da esquerda" @pointerdown="ddLigarDown($event, b, n, 'e')"></button>
                                            <button type="button" class="wf-dd-h d" tabindex="-1" aria-label="Ligar a partir da direita" @pointerdown="ddLigarDown($event, b, n, 'd')"></button>
                                            <span class="wf-dd-redim" x-show="ddRedimVisivel(b, n)" title="Redimensionar" @pointerdown="ddRedimDown($event, b, n)"></span>
                                        </div>
                                    </template>

                                    <div class="wf-dd-caixa" x-show="dd(b).caixa" :style="ddCaixaEstilo(b)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Tabletop: mapa com grade e peças arrastáveis --}}
                <template x-if="b.tipo === 'tabletop'">
                    @include('areas.diverso.partials.tabletop')
                </template>
            </div>
        </div>
    </x-diverso.item>
</template>
