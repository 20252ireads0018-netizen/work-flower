@extends('layouts.app')

@section('titulo', $area['nome'])
@section('subtitulo', $area['descricao'])

@section('conteudo')
@php
    // Tudo que o mente.js precisa: onde salvar, o que já foi salvo e as tarefas da área.
    $cfg = [
        'url'     => url('/mente/dados'),
        'pdf'     => url('/mente/pdf'),
        'dados'   => (object) $menteDados->all(),
        'tarefas' => $tarefasJs,
    ];
@endphp

<style>
    /* ===== Quadro da área Mente ===== */
    .wf-quadro { position: relative; width: 100%; isolation: isolate; }
    .wf-item {
        position: absolute; left: var(--x); top: var(--y); width: var(--w); height: var(--h);
        display: flex; flex-direction: column; overflow: hidden;
        background: var(--superficie); border: 1px solid var(--linha); border-top: 2px solid var(--wc);
        border-radius: .75rem; transition: box-shadow .2s;
    }
    .wf-item.arrastando { box-shadow: 0 14px 36px rgba(0, 0, 0, .5); user-select: none; }
    .wf-topo {
        display: flex; align-items: center; gap: .5rem; padding: .55rem .75rem;
        background: var(--superficie-2); border-bottom: 1px solid var(--linha);
        cursor: grab; touch-action: none;
    }
    .wf-item.arrastando .wf-topo { cursor: grabbing; }
    .wf-corpo { flex: 1; min-height: 0; overflow: auto; padding: 1rem; }
    .wf-redim {
        position: absolute; right: 3px; bottom: 3px; width: 16px; height: 16px;
        cursor: nwse-resize; touch-action: none;
        background: linear-gradient(135deg, transparent 52%, var(--tinta-2) 52%, var(--tinta-2) 60%, transparent 60%, transparent 72%, var(--tinta-2) 72%, var(--tinta-2) 80%, transparent 80%);
        opacity: .7;
    }
    .wf-cor {
        width: 1.1rem; height: 1.1rem; padding: 0; border: 0; border-radius: 9999px;
        background: none; cursor: pointer; overflow: hidden; flex-shrink: 0;
    }
    .wf-cor::-webkit-color-swatch-wrapper { padding: 0; }
    .wf-cor::-webkit-color-swatch { border: 0; border-radius: 9999px; }
    .wf-cor::-moz-color-swatch { border: 0; border-radius: 9999px; }

    .wf-titulo {
        background: transparent; border: 0; min-width: 0; color: var(--tinta); font-weight: 600;
        font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif; padding: .15rem .3rem; border-radius: .375rem;
    }
    .wf-titulo:hover { background: var(--superficie); }
    .wf-titulo:focus { outline: none; background: var(--superficie); box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-cel {
        width: 100%; min-width: 5rem; background: transparent; color: var(--tinta); border: 0;
        padding: .35rem .4rem; font-size: .85rem; border-radius: .375rem;
    }
    .wf-cel:focus { outline: none; background: var(--superficie-2); box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-tab { width: 100%; border-collapse: collapse; }
    .wf-tab th, .wf-tab td { border: 1px solid var(--linha); padding: 0; }
    .wf-tab th { background: var(--superficie-2); text-align: left; }
    .wf-link-sel { padding: .25rem 2rem .25rem .5rem; font-size: .75rem; width: auto; max-width: 11rem; }

    .wf-bloco { display: flex; flex-direction: column; gap: .75rem; min-height: 100%; }
    .wf-cresce { flex: 1; min-height: 6rem; }
    /* Bloco de altura fixa (leitor de PDF): o cartão não cresce, quem rola é o painel interno */
    .wf-bloco.fixo { height: 100%; min-height: 0; }
    .wf-bloco.fixo .wf-cresce { min-height: 0; }

    .chip {
        display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .6rem; border-radius: 9999px;
        font-size: .75rem; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta-2);
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .chip:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .chip.ativo { background: var(--prim); border-color: var(--prim); color: var(--on-prim); }

    .circ-bola { position: relative; }
    .circ-badge {
        position: absolute; top: -.2rem; right: -.2rem; min-width: 1.15rem; height: 1.15rem; padding: 0 .25rem;
        border-radius: 9999px; background: #f87171; color: #fff; font-size: .65rem; font-weight: 700;
        display: flex; align-items: center; justify-content: center; border: 2px solid var(--papel);
    }

    /* paleta de bolinhas (cor de curso, disciplina, destaque) */
    .wf-pal { display: flex; flex-wrap: wrap; gap: .4rem; }
    .wf-pal button {
        width: 1.35rem; height: 1.35rem; border-radius: 9999px; border: 2px solid transparent;
        transition: transform .12s, border-color .12s;
    }
    .wf-pal button:hover { transform: scale(1.12); }
    .wf-pal button.sel { border-color: var(--tinta); box-shadow: 0 0 0 2px var(--superficie); }

    .wf-linha {
        border: 1px solid var(--linha); border-radius: .6rem; padding: .65rem .75rem; background: var(--superficie-2);
        border-left: 3px solid var(--lc, var(--prim)); transition: border-color .3s, box-shadow .3s;
    }
    .wf-linha.destaque { border-color: var(--prim); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent); }
    .wf-linha.sel { background: var(--prim-suave); border-color: var(--prim-linha); }
    .wf-linha.atrasada { border-left-color: #f87171; }

    /* ----- Leitor de PDF (rolagem contínua) ----- */
    .wf-leitor {
        position: relative; /* necessário: o offsetTop das páginas é medido a partir daqui */
        background: color-mix(in srgb, var(--papel) 70%, #000); border: 1px solid var(--linha);
        border-radius: .6rem; padding: .75rem; overflow: auto; flex: 1; min-height: 12rem;
    }
    .wf-pagina { position: relative; margin: 0 auto; background: #fff; box-shadow: 0 6px 24px rgba(0, 0, 0, .45); }
    .wf-pagina canvas { display: block; }
    .wf-dest, .wf-busca { position: absolute; inset: 0; pointer-events: none; z-index: 1; }
    .wf-dest i { position: absolute; border-radius: 2px; opacity: .38; mix-blend-mode: multiply; }
    /* resultados da busca: amarelo para todos, laranja forte para o que está selecionado */
    .wf-busca i { position: absolute; border-radius: 2px; background: #f2c230; opacity: .5; mix-blend-mode: multiply; }
    .wf-busca i.atual { background: #fb923c; opacity: .8; box-shadow: 0 0 0 2px #ea580c; }
    .textLayer { position: absolute; inset: 0; overflow: hidden; line-height: 1; z-index: 2; }
    .textLayer span, .textLayer br { color: transparent; position: absolute; white-space: pre; cursor: text; transform-origin: 0 0; }
    .textLayer ::selection { background: color-mix(in srgb, var(--prim) 45%, transparent); color: transparent; }

    /* ----- Imagem ----- */
    .wf-img-caixa {
        display: flex; align-items: center; justify-content: center; overflow: hidden;
        border: 1px dashed var(--borda-campo); border-radius: .6rem; background: var(--superficie-2);
    }
    .wf-img-caixa img { width: 100%; height: 100%; }

    /* ----- Documento ----- */
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

    /* ----- Mapa mental ----- */
    .wf-mapa { flex: 1; min-height: 12rem; overflow: auto; border: 1px solid var(--linha); border-radius: .6rem; background: var(--superficie-2); }
    .wf-no {
        position: absolute; width: 188px; height: 38px; display: flex; align-items: center; gap: .15rem; padding: 0 .3rem;
        background: var(--superficie); border: 1px solid var(--no); border-radius: .5rem;
    }
    .wf-no-grip { cursor: grab; color: var(--tinta-2); padding: 0 .2rem; touch-action: none; user-select: none; }
    .wf-no-t { flex: 1; min-width: 0; background: transparent; border: 0; color: var(--tinta); font-size: .8rem; padding: .2rem; border-radius: .25rem; }
    .wf-no-t:focus { outline: none; background: var(--superficie-2); }
    .wf-ponto { width: .65rem; height: .65rem; border-radius: 9999px; background: var(--no); }

    /* ----- Faculdade ----- */
    .wf-dia { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem; min-height: 5.5rem; display: flex; flex-direction: column; gap: .35rem; }
    .wf-dia.hoje { border-color: var(--prim); }
    .wf-aula { border-left: 3px solid var(--lc); padding: .15rem .45rem; font-size: .75rem; line-height: 1.25; background: color-mix(in srgb, var(--lc) 12%, transparent); border-radius: .25rem; }
    .wf-prova { font-size: .7rem; color: #f87171; }

    /* ----- Tela cheia por cartão (telacheia.js) ----- */
    html.wf-fs-pagina { overflow: hidden; }
    .wf-quadro.wf-fs-ativo { isolation: auto; }
    .wf-item.wf-fs {
        position: fixed !important; inset: 0 !important; left: 0 !important; top: 0 !important;
        width: 100vw !important; height: 100vh !important; height: 100dvh !important;
        z-index: 1000; border-radius: 0; box-shadow: none; transition: none;
    }
    .wf-item.wf-fs .wf-corpo { padding: 1.25rem; overflow: auto; }
    .wf-item.wf-fs .wf-redim { display: none; }
    .wf-item.wf-fs .wf-topo { cursor: default; }
    .wf-item.wf-fs .wf-leitor { max-height: none; }

    /* Celular: os cartões empilham e o arrastar fica desligado */
    @media (max-width: 899px) {
        .wf-quadro { display: flex; flex-direction: column; gap: 1rem; min-height: 0 !important; height: auto !important; }
        .wf-item { position: static; width: auto; height: auto; }
        .wf-corpo { overflow: visible; }
        .wf-topo { cursor: default; touch-action: auto; }
        .wf-redim { display: none; }
        .wf-leitor { max-height: 70vh; }
        .wf-bloco.fixo { height: auto; }
    }
</style>

<script type="application/json" id="mente-cfg">{!! json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
{{-- Precisa vir antes do Alpine iniciar (o Alpine do layout usa defer) --}}
<script src="{{ asset('js/quadro.js') }}?v={{ @filemtime(public_path('js/quadro.js')) }}"></script>
<script src="{{ asset('js/mente.js') }}?v={{ @filemtime(public_path('js/mente.js')) }}"></script>
{{-- tabela.js estende menteAbas(): precisa vir DEPOIS de mente.js (importar/exportar CSV) --}}
<script src="{{ asset('js/tabela.js') }}?v={{ @filemtime(public_path('js/tabela.js')) }}"></script>
{{-- Baixar cartões / soltar arquivos para criar blocos / tela cheia por cartão --}}
<script src="{{ asset('js/arquivos.js') }}?v={{ @filemtime(public_path('js/arquivos.js')) }}"></script>
<script src="{{ asset('js/tela-cheia.js') }}?v={{ @filemtime(public_path('js/tela-cheia.js')) }}"></script>

<div x-data="menteAbas()" class="space-y-6">

    {{-- Mini cards circulares --}}
    <nav class="flex flex-wrap gap-x-5 gap-y-4" aria-label="Seções de Mente">
        <template x-for="a in abas" :key="a.id">
            <button type="button" class="circ" :class="{ 'ativo': aba === a.id }"
                    @click="aba = a.id" :aria-pressed="aba === a.id" :title="a.nome">
                <span class="circ-bola">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="a.icone"></svg>
                    <span class="circ-badge" x-show="a.id === 'estudos' && pendencias() > 0" x-text="pendencias()"></span>
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

        {{-- ================= TAREFAS ================= --}}
        <section class="wf-quadro" x-show="aba === 'tarefas'" x-cloak :style="{ minHeight: alturaQuadro('tarefas') + 'px' }">
            <x-mente.item sec="'tarefas'" bid="'tarefa-form'" titulo="Nova tarefa" :minw="260" :minh="200">
                @include('areas.partials.formulario', [
                    'slug'        => $slug,
                    'placeholder' => 'Ex.: Ler capítulo 3, entregar trabalho…',
                ])
                <p class="text-xs texto-2 mt-3">Use para leituras, estudos, provas e trabalhos.</p>
            </x-mente.item>

            <x-mente.item sec="'tarefas'" bid="'tarefa-lista'" titulo="Tarefas" :minw="320" :minh="200">
                @include('areas.partials.lista', [
                    'tarefas' => $tarefas,
                    'vazio'   => 'Nenhuma tarefa de mente ainda. Que tal começar por uma leitura?',
                ])
            </x-mente.item>

            @include('areas.mente.partials.blocos', ['sec' => 'tarefas'])
        </section>

        {{-- ================= LIVROS ================= --}}
        <section class="wf-quadro" x-show="aba === 'livros'" x-cloak :style="{ minHeight: alturaQuadro('livros') + 'px' }">

            <x-mente.item sec="'livros'" bid="'livro-lista'" titulo="Biblioteca" :minw="280" :minh="320">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <input class="campo" x-model="lv.titulo" placeholder="Título do livro" @keydown.enter.prevent="criarLivro($refs.pdfNovo)">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="campo" x-model="lv.autor" placeholder="Autor">
                            <input type="number" min="0" class="campo" x-model="lv.paginas" placeholder="Páginas">
                        </div>
                        <select class="campo" x-model="lv.status" aria-label="Situação">
                            <option value="lendo">Lendo</option>
                            <option value="quero">Quero ler</option>
                            <option value="lido">Lido</option>
                        </select>
                        <label class="btn-sec w-full cursor-pointer">
                            <span>📄 Anexar PDF (opcional)</span>
                            <input type="file" accept="application/pdf,.pdf" class="sr-only" x-ref="pdfNovo"
                                   @change="aviso($event.target.files[0] ? 'PDF escolhido: ' + $event.target.files[0].name : '')">
                        </label>
                        <p class="erro" x-text="lvErro"></p>
                        <button type="button" class="btn w-full" @click="criarLivro($refs.pdfNovo)">Adicionar livro</button>
                    </div>

                    <p x-show="!estado.livros.length" class="text-sm texto-2">Sua biblioteca está vazia. Adicione um livro ou envie um PDF.</p>
                    <ul class="space-y-2">
                        <template x-for="l in livrosOrdenados()" :key="l.id">
                            <li class="wf-linha cursor-pointer" :class="{ 'sel': leitor.id === l.id }" :style="'--lc:' + l.cor"
                                @click="abrirLivro(l)">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium truncate" x-text="l.titulo"></p>
                                        <p class="text-xs texto-2 truncate">
                                            <span x-text="statusLivro(l.status)"></span>
                                            <span x-show="l.autor" x-text="' · ' + l.autor"></span>
                                            <span x-show="l.pdf"> · PDF</span>
                                        </p>
                                    </div>
                                    <button type="button" class="mini-btn perigo" title="Excluir livro" @click.stop="excluirLivro(l)">✕</button>
                                </div>
                                <div class="barra mt-2"><span :style="'width:' + pctLivro(l) + '%'"></span></div>
                                <p class="text-xs texto-2 mt-1" x-text="(l.atual || 0) + ' / ' + (l.paginas || '?') + ' páginas'"></p>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-mente.item>

            <x-mente.item sec="'livros'" bid="'livro-leitor'" :minw="360" :minh="320">
                <x-slot name="cabeca">
                    <h2 class="titulo text-base flex-1 truncate" x-text="livroAtual()?.titulo || 'Leitor'"></h2>
                </x-slot>

                <div class="wf-bloco fixo">
                    <p x-show="!livroAtual()" class="text-sm texto-2">Escolha um livro na biblioteca para ler, marcar páginas e destacar trechos.</p>

                    <div x-show="livroAtual()" class="flex flex-col gap-3 wf-cresce">

                        {{-- Livro sem PDF: só controle de página --}}
                        <div x-show="livroAtual() && !livroAtual().pdf" class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span class="texto-2">Página atual</span>
                                <input type="number" min="0" class="campo !w-24" x-model.number="livroAtual().atual" aria-label="Página atual">
                                <span class="texto-2">de</span>
                                <input type="number" min="0" class="campo !w-24" x-model.number="livroAtual().paginas" aria-label="Total de páginas">
                            </div>
                            <label class="btn-sec cursor-pointer">
                                <span>📄 Enviar PDF deste livro</span>
                                <input type="file" accept="application/pdf,.pdf" class="sr-only"
                                       @change="enviarPdf(livroAtual(), $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <p class="text-xs texto-2">Sem PDF, você ainda pode registrar trechos e anotações ao lado.</p>
                        </div>

                        {{-- Leitor --}}
                        <div x-show="livroAtual()?.pdf" class="flex flex-col gap-3 wf-cresce">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="mini-btn" title="Página anterior" @click="irPagina(leitor.pg - 1)">‹</button>
                                <input type="number" min="1" class="campo !w-16 !py-1 text-center" :value="leitor.pg"
                                       @change="irPagina($event.target.value)" aria-label="Página">
                                <span class="text-xs texto-2" x-text="'/ ' + leitor.total"></span>
                                <button type="button" class="mini-btn" title="Próxima página" @click="irPagina(leitor.pg + 1)">›</button>
                                <span class="w-px h-5 mx-1" style="background: var(--linha)"></span>
                                <button type="button" class="mini-btn" title="Diminuir" @click="zoom(-0.1)">−</button>
                                <span class="text-xs texto-2 w-10 text-center" x-text="Math.round(leitor.zoom * 100) + '%'"></span>
                                <button type="button" class="mini-btn" title="Aumentar" @click="zoom(0.1)">+</button>
                                <span class="w-px h-5 mx-1" style="background: var(--linha)"></span>
                                <button type="button" class="chip" :class="{ 'ativo': marcada() }" @click="addMarca()"
                                        x-text="marcada() ? '🔖 Marcada' : '🔖 Marcar página'"></button>
                                <label class="chip cursor-pointer"><input type="checkbox" x-model="leitor.noite"> Modo noite</label>
                            </div>

                            {{-- Busca de texto: Enter vai para a próxima ocorrência, Shift+Enter volta --}}
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="search" class="campo !w-56 !py-1" x-model="bs.q" x-ref="buscaCampo"
                                       placeholder="Buscar no PDF…" aria-label="Buscar texto no PDF"
                                       @keydown.enter.prevent="aoEnter($event)"
                                       @keydown.escape="limparBusca()"
                                       @input.debounce.500ms="buscar()">
                                <button type="button" class="mini-btn" title="Resultado anterior (Shift+Enter)" aria-label="Resultado anterior"
                                        :disabled="!bs.res.length" @click="anterior()">‹</button>
                                <span class="text-xs texto-2 min-w-[5.5rem] text-center" aria-live="polite" x-text="rotuloBusca()"></span>
                                <button type="button" class="mini-btn" title="Próximo resultado (Enter)" aria-label="Próximo resultado"
                                        :disabled="!bs.res.length" @click="proximo()">›</button>
                                <button type="button" class="mini-btn" x-show="bs.q" title="Limpar busca (Esc)" aria-label="Limpar busca" @click="limparBusca()">✕</button>
                                <span class="text-xs texto-2" x-show="bs.ocupado" x-text="'Procurando… página ' + bs.prog + ' de ' + leitor.total"></span>
                            </div>

                            <div class="flex items-center gap-2 text-xs texto-2">
                                <span>Destacar com</span>
                                <div class="wf-pal">
                                    <template x-for="c in hlCores" :key="c">
                                        <button type="button" :class="{ 'sel': leitor.cor === c }" :style="'background:' + c"
                                                :aria-label="'Cor ' + c" @click="leitor.cor = c"></button>
                                    </template>
                                </div>
                                <span class="ml-auto hidden sm:inline">Selecione um trecho para destacar</span>
                            </div>

                            <p x-show="leitor.carregando" class="text-sm texto-2">Abrindo PDF…</p>
                            <p x-show="leitor.erro" class="erro" x-text="leitor.erro"></p>

                            {{-- Painel de rolagem contínua: uma .wf-pagina por página do PDF --}}
                            <div class="wf-leitor" x-ref="pdfLeitor" x-show="!leitor.erro"
                                 @scroll.passive="aoRolar()"
                                 @mouseup="capturar()" @touchend="setTimeout(() => capturar(), 250)">
                                <template x-for="n in Array.from({ length: leitor.total }, (_, i) => i + 1)" :key="n">
                                    <div class="wf-pagina" :data-pg="n" style="margin-bottom: .75rem">
                                        <canvas :style="leitor.noite ? 'filter:invert(.92) hue-rotate(180deg)' : ''"></canvas>
                                        <div class="wf-dest">
                                            <template x-for="d in destDe(n)" :key="d.id">
                                                <div>
                                                    <template x-for="(r, i) in d.rects" :key="i">
                                                        <i :style="`left:${r.x}%;top:${r.y}%;width:${r.w}%;height:${r.h}%;background:${d.cor}`"></i>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="wf-busca">
                                            <template x-for="m in buscaDe(n)" :key="m.i">
                                                <div>
                                                    <template x-for="(r, k) in m.rects" :key="k">
                                                        <i :class="{ 'atual': bs.i === m.i }"
                                                           :style="`left:${r.x}%;top:${r.y}%;width:${r.w}%;height:${r.h}%`"></i>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="textLayer"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </x-mente.item>

            <x-mente.item sec="'livros'" bid="'livro-destaques'" titulo="Destaques e marcadores" :minw="280" :minh="260">
                <div class="space-y-4">
                    <p x-show="!livroAtual()" class="text-sm texto-2">Abra um livro para ver os destaques dele.</p>

                    <div x-show="livroAtual()" class="space-y-4">
                        <div x-show="livroAtual()?.marcas.length">
                            <h3 class="titulo text-sm mb-2">Páginas marcadas</h3>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="m in livroAtual()?.marcas || []" :key="m.id">
                                    <span class="chip cursor-pointer" role="button" tabindex="0" @click="irDestaque(m)" @keydown.enter="irDestaque(m)">
                                        <span x-text="'🔖 p. ' + m.pg"></span>
                                        <span title="Remover marcador" @click.stop="remMarca(livroAtual(), m.id)">✕</span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div>
                            <h3 class="titulo text-sm mb-2">Trechos que gostei</h3>
                            <p x-show="!livroAtual()?.destaques.length" class="text-xs texto-2">Selecione um texto no PDF ou escreva um trecho abaixo.</p>
                            <ul class="space-y-2">
                                <template x-for="d in destaquesOrdenados(livroAtual())" :key="d.id">
                                    <li class="wf-linha" :style="'--lc:' + d.cor">
                                        <div class="flex items-start justify-between gap-2">
                                            <button type="button" class="text-xs texto-2 hover:underline" @click="irDestaque(d)" x-text="'Página ' + d.pg"></button>
                                            <div class="flex gap-1">
                                                <button type="button" class="mini-btn" :title="d.fav ? 'Tirar dos favoritos' : 'Favoritar'" @click="d.fav = !d.fav" x-text="d.fav ? '★' : '☆'"></button>
                                                <button type="button" class="mini-btn" title="Enviar para Anotações" @click="destaqueParaNota(livroAtual(), d)">📝</button>
                                                <button type="button" class="mini-btn perigo" title="Remover destaque" @click="remDestaque(livroAtual(), d.id)">✕</button>
                                            </div>
                                        </div>
                                        <p class="text-sm mt-1 italic" x-text="'“' + d.texto + '”'"></p>
                                        <textarea class="wf-cel mt-1.5" rows="2" x-model="d.nota" placeholder="Minha anotação sobre este trecho…"></textarea>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="space-y-2 pt-3" style="border-top: 1px solid var(--linha)">
                            <h3 class="titulo text-sm">Adicionar trecho</h3>
                            <div class="grid grid-cols-3 gap-2">
                                <input type="number" min="1" class="campo" x-model="dm.pg" placeholder="Pág.">
                                <input class="campo col-span-2" x-model="dm.nota" placeholder="Nota (opcional)">
                            </div>
                            <textarea class="campo" rows="3" x-model="dm.texto" placeholder="Copie ou escreva o trecho…"></textarea>
                            <button type="button" class="btn-sec w-full" @click="addTrecho()">Salvar trecho</button>
                        </div>
                    </div>
                </div>
            </x-mente.item>

            @include('areas.mente.partials.blocos', ['sec' => 'livros'])
        </section>

        {{-- ================= ESTUDOS ================= --}}
        <section class="wf-quadro" x-show="aba === 'estudos'" x-cloak :style="{ minHeight: alturaQuadro('estudos') + 'px' }">

            <x-mente.item sec="'estudos'" bid="'est-novo'" titulo="Estudei hoje" :minw="280" :minh="360">
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Conteúdo</label>
                        <input class="campo" x-model="ef.titulo" placeholder="Ex.: Derivadas — regra da cadeia" @keydown.enter.prevent="salvarEstudo()">
                    </div>
                    <div>
                        <label class="rotulo">Curso</label>
                        <select class="campo" x-model="ef.curso">
                            <option value="">Sem curso</option>
                            <template x-for="c in estado.estudos.cursos" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="rotulo">Data do estudo</label>
                        <input type="date" class="campo" x-model="ef.data">
                    </div>
                    <div>
                        <label class="rotulo">Observação</label>
                        <textarea class="campo" rows="2" x-model="ef.nota" placeholder="O que ficou difícil?"></textarea>
                    </div>
                    <p class="erro" x-text="efErro"></p>
                    <button type="button" class="btn w-full" @click="salvarEstudo()">Marcar como estudado</button>

                    <div class="pt-4 space-y-3" style="border-top: 1px solid var(--linha)">
                        <div class="flex items-center gap-2 text-sm">
                            <span>Revisar a cada</span>
                            <input type="number" min="1" max="90" class="campo !w-20 !py-1" x-model.number="estado.estudos.intervalo" aria-label="Dias entre revisões">
                            <span>dias</span>
                        </div>
                        <button type="button" class="link-prim text-sm" x-show="permAviso === 'default'" @click="ativarAvisos()">🔔 Ativar avisos de revisão</button>
                        <p class="text-xs texto-2" x-show="permAviso === 'granted'">🔔 Avisos de revisão ativos.</p>
                        <p class="text-xs texto-2" x-show="permAviso === 'denied'">Avisos bloqueados no navegador.</p>
                    </div>
                </div>
            </x-mente.item>

            <x-mente.item sec="'estudos'" bid="'est-cursos'" titulo="Cursos" :minw="260" :minh="240">
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <input type="color" class="wf-cor !w-6 !h-6" x-model="cu.cor" aria-label="Cor do curso" title="Cor do curso">
                        <input class="campo" x-model="cu.nome" placeholder="Novo curso" @keydown.enter.prevent="addCurso()">
                        <button type="button" class="btn-sec shrink-0" @click="addCurso()">Criar</button>
                    </div>
                    <p x-show="!estado.estudos.cursos.length" class="text-sm texto-2">Crie cursos para agrupar o que você estuda.</p>
                    <ul class="space-y-2">
                        <template x-for="c in estado.estudos.cursos" :key="c.id">
                            <li class="wf-linha flex items-center gap-2" :style="'--lc:' + c.cor">
                                <input class="wf-cel flex-1" x-model="c.nome" aria-label="Nome do curso">
                                <span class="text-xs texto-2 shrink-0" x-text="estado.estudos.itens.filter(i => i.curso === c.id).length + ' itens'"></span>
                                <button type="button" class="mini-btn perigo" title="Excluir curso" @click="remCurso(c)">✕</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-mente.item>

            <x-mente.item sec="'estudos'" bid="'est-revisar'" :minw="320" :minh="200">
                <x-slot name="cabeca">
                    <h2 class="titulo text-base flex-1 truncate">Hora de revisar</h2>
                    <span class="text-xs texto-2" x-text="pendencias() + ' pendente' + (pendencias() === 1 ? '' : 's')"></span>
                </x-slot>
                <p x-show="!aRevisar().length" class="text-sm texto-2">Nada para revisar hoje. Os conteúdos aparecem aqui quando passa o intervalo de revisão.</p>
                <ul class="space-y-2">
                    <template x-for="it in aRevisar()" :key="it.id">
                        <li class="wf-linha atrasada flex items-center justify-between gap-3" :style="'--lc:' + (cursoDe(it.curso)?.cor || '#94a3b8')">
                            <div class="min-w-0">
                                <p class="text-sm font-medium truncate" x-text="it.titulo"></p>
                                <p class="text-xs texto-2" x-text="(cursoDe(it.curso)?.nome || 'Sem curso') + ' · ' + statusEstudo(it)"></p>
                            </div>
                            <button type="button" class="btn !py-1.5 shrink-0" @click="estudei(it)">Revisei</button>
                        </li>
                    </template>
                </ul>
            </x-mente.item>

            <x-mente.item sec="'estudos'" bid="'est-lista'" titulo="Conteúdos estudados" :minw="320" :minh="220">
                <p x-show="!estado.estudos.itens.length" class="text-sm texto-2">Nenhum conteúdo ainda. Marque o primeiro ao lado.</p>
                <div class="space-y-5">
                    <template x-for="g in gruposEstudo()" :key="g.id">
                        <div>
                            <h3 class="titulo text-sm mb-2 flex items-center gap-2">
                                <span class="wf-ponto" :style="'--no:' + g.cor"></span><span x-text="g.nome"></span>
                            </h3>
                            <ul class="space-y-2">
                                <template x-for="it in g.itens" :key="it.id">
                                    <li class="wf-linha" :class="{ 'destaque': destaque === 'estudo:' + it.id, 'atrasada': vencido(it), 'opacity-60': it.arq }" :style="'--lc:' + g.cor">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium" x-text="it.titulo"></p>
                                                <p class="text-xs texto-2" x-text="statusEstudo(it)"></p>
                                                <p class="text-xs texto-2 mt-1" x-show="it.nota" x-text="it.nota"></p>
                                                <p class="text-xs texto-2 mt-1" x-text="it.hist.length + ' estudo' + (it.hist.length === 1 ? '' : 's') + ' registrado' + (it.hist.length === 1 ? '' : 's')"></p>
                                            </div>
                                            <div class="flex gap-1 shrink-0">
                                                <button type="button" class="mini-btn" title="Estudei de novo" @click="estudei(it)">✓</button>
                                                <button type="button" class="mini-btn" :title="it.arq ? 'Reativar revisão' : 'Arquivar (sem revisão)'" @click="it.arq = !it.arq" x-text="it.arq ? '↺' : '▣'"></button>
                                                <button type="button" class="mini-btn perigo" title="Excluir" @click="excluirEstudo(it)">✕</button>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </x-mente.item>

            @include('areas.mente.partials.blocos', ['sec' => 'estudos'])
        </section>

        {{-- ================= ANOTAÇÕES ================= --}}
        <section class="wf-quadro" x-show="aba === 'notas'" x-cloak :style="{ minHeight: alturaQuadro('notas') + 'px' }">

            <x-mente.item sec="'notas'" bid="'nota-lista'" titulo="Anotações" :minw="260" :minh="260">
                <div class="space-y-3">
                    <div class="flex gap-2">
                        <input class="campo" x-model="nt" placeholder="Título da nova anotação…" aria-label="Título da nova anotação"
                            @keydown.enter.prevent="novaNota(nt)">
                        <button type="button" class="btn shrink-0" @click="novaNota(nt)">+ Nova</button>
                    </div>
                    <input class="campo text-sm" x-model="nq" placeholder="Buscar…" aria-label="Buscar anotações">
                    <div class="flex flex-wrap gap-1.5" x-show="todasTags().length">
                        <button type="button" class="chip" :class="{ 'ativo': !nTag }" @click="nTag = ''">Todas</button>
                        <template x-for="t in todasTags()" :key="t">
                            <button type="button" class="chip" :class="{ 'ativo': nTag === t }" @click="nTag = (nTag === t ? '' : t)" x-text="'#' + t"></button>
                        </template>
                    </div>
                    <p x-show="!estado.notas.length" class="text-sm texto-2">Nenhuma anotação ainda. Digite um título acima e crie a primeira.</p>
                    <p x-show="estado.notas.length && !notasFiltradas().length" class="text-sm texto-2">Nada encontrado para esse filtro.</p>
                    <ul class="space-y-2">
                        <template x-for="n in notasFiltradas()" :key="n.id">
                            <li class="wf-linha cursor-pointer" :class="{ 'sel': notaSel === n.id }" :style="'--lc:' + n.cor"
                                @click="notaSel = n.id" @dblclick="editarNota(n)" title="Duplo clique para editar">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium truncate" x-text="(n.fixada ? '📌 ' : '') + n.titulo"></p>
                                    <span class="text-xs texto-2 shrink-0" x-text="quando(n)"></span>
                                </div>
                                <p class="text-xs texto-2 truncate mt-0.5" x-text="resumoNota(n)"></p>
                                <div class="flex items-center gap-1 mt-1.5">
                                    <div class="flex flex-wrap gap-x-2 flex-1 min-w-0">
                                        <template x-for="t in n.tags.slice(0, 3)" :key="t">
                                            <span class="text-xs texto-2" x-text="'#' + t"></span>
                                        </template>
                                    </div>
                                    <button type="button" class="mini-btn" title="Editar" aria-label="Editar anotação" @click.stop="editarNota(n)">✎</button>
                                    <button type="button" class="mini-btn" title="Duplicar" aria-label="Duplicar anotação" @click.stop="duplicarNota(n)">⧉</button>
                                    <button type="button" class="mini-btn" :title="n.fixada ? 'Desafixar' : 'Fixar no topo'" @click.stop="n.fixada = !n.fixada; tocar(n)" x-text="n.fixada ? '📌' : '📍'"></button>
                                    <button type="button" class="mini-btn perigo" title="Excluir" aria-label="Excluir anotação" @click.stop="excluirNota(n)">✕</button>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-mente.item>

            <x-mente.item sec="'notas'" bid="'nota-editor'" :minw="320" :minh="260">
                <x-slot name="cabeca">
                    <h2 class="titulo text-base flex-1 truncate" x-text="notaAtual()?.titulo || 'Editor'"></h2>
                </x-slot>
                <p x-show="!notaAtual()" class="text-sm texto-2">Escolha uma anotação ou crie uma nova.</p>

                <template x-if="notaAtual()">
                    <div class="wf-bloco" x-data="{ get n() { return notaAtual() } }">
                        <div class="flex items-center gap-2">
                            <input id="nota-titulo" class="campo font-semibold" x-model="n.titulo" @input="tocar(n)"
                                @keydown.enter.prevent="focarNota('texto')"
                                @blur="if (!n.titulo.trim()) n.titulo = 'Sem título'"
                                placeholder="Título da anotação" aria-label="Título da anotação">
                            <input type="color" class="wf-cor !w-6 !h-6" x-model="n.cor" title="Cor da anotação" aria-label="Cor da anotação">
                            <button type="button" class="mini-btn" title="Duplicar" aria-label="Duplicar anotação" @click="duplicarNota(n)">⧉</button>
                            <button type="button" class="mini-btn" :title="n.fixada ? 'Desafixar' : 'Fixar no topo'" @click="n.fixada = !n.fixada" x-text="n.fixada ? '📌' : '📍'"></button>
                            <button type="button" class="mini-btn perigo" title="Excluir anotação" @click="excluirNota(n)">✕</button>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="t in n.tags" :key="t">
                                <span class="chip">
                                    <span x-text="'#' + t"></span>
                                    <button type="button" title="Remover etiqueta" :aria-label="'Remover etiqueta ' + t" @click="remTag(n, t)">✕</button>
                                </span>
                            </template>
                            <input class="campo !w-auto flex-1 min-w-[10rem] !py-1 text-sm" placeholder="+ etiqueta (Enter ou vírgula)" aria-label="Adicionar etiqueta"
                                @keydown.enter.prevent="addTag(n, $event.target)"
                                @keydown.comma.prevent="addTag(n, $event.target)"
                                @blur="addTag(n, $event.target)">
                        </div>

                        <textarea id="nota-texto" class="campo wf-cresce resize-none" x-model="n.texto" @input="tocar(n)" placeholder="Escreva livremente…"></textarea>
                        <p class="text-xs texto-2" x-text="contagem(n) + ' · editada em ' + quando(n)"></p>
                    </div>
                </template>
            </x-mente.item>

            @include('areas.mente.partials.blocos', ['sec' => 'notas'])
        </section>

        {{-- ================= FACULDADE ================= --}}
        <section class="wf-quadro" x-show="aba === 'faculdade'" x-cloak :style="{ minHeight: alturaQuadro('faculdade') + 'px' }">

            <x-mente.item sec="'faculdade'" bid="'fac-semana'" titulo="Semana" :minw="480" :minh="200">
                <div class="grid grid-cols-7 gap-2 min-w-[40rem]">
                    <template x-for="d in semana()" :key="d.id">
                        <div class="wf-dia" :class="{ 'hoje': d.hoje }">
                            <p class="text-xs texto-2"><span x-text="d.c"></span> <span x-text="d.num"></span></p>
                            <template x-for="a in aulasDoDia(d.id)" :key="a.k">
                                <div class="wf-aula" :style="'--lc:' + a.cor">
                                    <p class="font-medium" x-text="a.nome"></p>
                                    <p class="texto-2" x-text="a.ini + '–' + a.fim + (a.sala ? ' · ' + a.sala : '')"></p>
                                </div>
                            </template>
                            <template x-for="v in avalsDaData(d.data)" :key="v.id">
                                <p class="wf-prova" x-text="'⚑ ' + v.tipo + ': ' + v.titulo"></p>
                            </template>
                            <p x-show="!aulasDoDia(d.id).length && !avalsDaData(d.data).length" class="text-xs texto-2">Livre</p>
                        </div>
                    </template>
                </div>
            </x-mente.item>

            <x-mente.item sec="'faculdade'" bid="'fac-disc-form'" :minw="300" :minh="360">
                <x-slot name="cabeca">
                    <h2 class="titulo text-base flex-1 truncate" x-text="df.id ? 'Editar disciplina' : 'Nova disciplina'"></h2>
                </x-slot>
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Nome</label>
                        <input class="campo" x-model="df.nome" placeholder="Ex.: Cálculo II">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="rotulo">Professor</label><input class="campo" x-model="df.prof"></div>
                        <div><label class="rotulo">Sala</label><input class="campo" x-model="df.sala"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 items-end">
                        <div><label class="rotulo">Limite de faltas</label><input type="number" min="0" class="campo" x-model="df.limite"></div>
                        <div>
                            <label class="rotulo">Cor</label>
                            <div class="wf-pal">
                                <template x-for="c in paleta" :key="c">
                                    <button type="button" :class="{ 'sel': df.cor === c }" :style="'background:' + c" :aria-label="'Cor ' + c" @click="df.cor = c"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="rotulo">Horários</label>
                        <ul class="space-y-2">
                            <template x-for="(h, n) in df.hor" :key="h.k">
                                <li class="flex items-center gap-1.5">
                                    <select class="campo !w-auto" x-model="h.dia" aria-label="Dia">
                                        <template x-for="d in dias" :key="d.id"><option :value="d.id" x-text="d.c"></option></template>
                                    </select>
                                    <input type="time" class="campo" x-model="h.ini" aria-label="Início">
                                    <input type="time" class="campo" x-model="h.fim" aria-label="Fim">
                                    <button type="button" class="mini-btn perigo shrink-0" title="Remover horário" x-show="df.hor.length > 1" @click="df.hor.splice(n, 1)">✕</button>
                                </li>
                            </template>
                        </ul>
                        <button type="button" class="link-prim text-sm mt-2" @click="addHorario()">+ Horário</button>
                    </div>
                    <p class="erro" x-text="dfErro"></p>
                    <div class="flex gap-2">
                        <button type="button" class="btn" @click="salvarDisc()" x-text="df.id ? 'Salvar alterações' : 'Salvar disciplina'"></button>
                        <button type="button" class="btn-sec" x-show="df.id" @click="cancelarDisc()">Cancelar</button>
                    </div>
                </div>
            </x-mente.item>

            <x-mente.item sec="'faculdade'" bid="'fac-disc'" titulo="Disciplinas" :minw="320" :minh="200">
                <p x-show="!estado.fac.disciplinas.length" class="text-sm texto-2">Nenhuma disciplina ainda. Cadastre a primeira no formulário.</p>
                <ul class="space-y-2.5">
                    <template x-for="d in estado.fac.disciplinas" :key="d.id">
                        <li class="wf-linha" :style="'--lc:' + d.cor">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate" x-text="d.nome"></p>
                                    <p class="text-xs texto-2 truncate" x-text="[d.prof, d.sala].filter(Boolean).join(' · ') || 'Sem detalhes'"></p>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <button type="button" class="mini-btn" title="Editar" @click="editarDisc(d)">✎</button>
                                    <button type="button" class="mini-btn perigo" title="Excluir" @click="excluirDisc(d)">✕</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mt-2 text-sm">
                                <span>Média: <strong x-text="media(d) === null ? '—' : fmt(media(d))"></strong></span>
                                <span class="flex items-center gap-1.5">
                                    Faltas:
                                    <button type="button" class="mini-btn" title="Menos uma falta" @click="d.faltas = Math.max(0, d.faltas - 1)">−</button>
                                    <strong x-text="d.faltas"></strong>
                                    <button type="button" class="mini-btn" title="Mais uma falta" @click="d.faltas++">+</button>
                                </span>
                                <span class="text-xs texto-2" x-show="restantes(d) !== null"
                                      :class="{ '!text-red-400': restantes(d) === 0 }"
                                      x-text="restantes(d) + ' falta' + (restantes(d) === 1 ? '' : 's') + ' restante' + (restantes(d) === 1 ? '' : 's')"></span>
                            </div>
                        </li>
                    </template>
                </ul>
            </x-mente.item>

            <x-mente.item sec="'faculdade'" bid="'fac-aval'" titulo="Provas e trabalhos" :minw="340" :minh="260">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-2">
                        <select class="campo" x-model="af.disc" aria-label="Disciplina">
                            <option value="">Disciplina…</option>
                            <template x-for="d in estado.fac.disciplinas" :key="d.id"><option :value="d.id" x-text="d.nome"></option></template>
                        </select>
                        <select class="campo" x-model="af.tipo" aria-label="Tipo">
                            <template x-for="t in tiposAval" :key="t"><option :value="t" x-text="t"></option></template>
                        </select>
                        <input class="campo col-span-2" x-model="af.titulo" placeholder="Título (ex.: P1 — Limites)" @keydown.enter.prevent="salvarAval()">
                        <input type="date" class="campo" x-model="af.data" aria-label="Data">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" min="0" step="0.1" class="campo" x-model="af.peso" placeholder="Peso" title="Peso">
                            <input type="number" min="0" step="0.1" class="campo" x-model="af.nota" placeholder="Nota" title="Nota (se já saiu)">
                        </div>
                    </div>
                    <p class="erro" x-text="afErro"></p>
                    <button type="button" class="btn w-full" @click="salvarAval()">Adicionar avaliação</button>

                    <p x-show="!estado.fac.avaliacoes.length" class="text-sm texto-2">Nenhuma avaliação cadastrada.</p>
                    <ul class="space-y-2">
                        <template x-for="a in avalsOrdenadas()" :key="a.id">
                            <li class="wf-linha flex items-center gap-3" :style="'--lc:' + (discPorId(a.disc)?.cor || '#94a3b8')" :class="{ 'opacity-60': a.feito }">
                                <input type="checkbox" x-model="a.feito" aria-label="Concluída">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate" x-text="a.tipo + ': ' + a.titulo"></p>
                                    <p class="text-xs texto-2 truncate"
                                       x-text="(discPorId(a.disc)?.nome || 'Disciplina removida') + ' · ' + dataBR(a.data) + ' · ' + prazoTxt(a) + ' · peso ' + fmt(a.peso)"></p>
                                </div>
                                <input type="number" min="0" step="0.1" class="campo !w-20 !py-1" x-model="a.nota" placeholder="Nota" aria-label="Nota">
                                <button type="button" class="mini-btn perigo" title="Remover" @click="remAval(a.id)">✕</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-mente.item>

            @include('areas.mente.partials.blocos', ['sec' => 'faculdade'])
        </section>

        {{-- ================= MAPA MENTAL (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'mapa'" x-cloak :style="{ minHeight: alturaQuadro('mapa') + 'px' }">
            @include('areas.mente.partials.blocos', ['sec' => 'mapa'])
        </section>

        {{-- ================= QUADRO LIVRE (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'livre'" x-cloak :style="{ minHeight: alturaQuadro('livre') + 'px' }">
            <p x-show="!blocosDe('livre').length" class="text-sm texto-2">
                Quadro vazio. Use “+ Bloco” para adicionar texto, tabela, lista, imagem, documento ou mapa mental.
                Você também pode arrastar arquivos (imagem, .md, .txt, .csv ou um bloco baixado .wf.json) para cá e eles viram blocos.
            </p>
            @include('areas.mente.partials.blocos', ['sec' => 'livre'])
        </section>
    </div>
</div>
@endsection