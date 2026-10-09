@extends('layouts.app')

@section('titulo', $area['nome'])
@section('subtitulo', $area['descricao'])

@section('conteudo')
@php
    // Tudo que o corpo.js precisa: onde salvar, o que já foi salvo e as tarefas da área.
    $cfg = [
        'url'     => url('/corpo/dados'),
        'dados'   => (object) $corpoDados->all(),
        'tarefas' => $tarefasJs,
    ];
@endphp

<style>
    /* ===== Quadro da área Corpo ===== */
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
    .wf-link-sel { padding: .25rem 2rem .25rem .5rem; font-size: .75rem; width: auto; }

    .chip {
        display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .6rem; border-radius: 9999px;
        font-size: .75rem; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta-2);
        transition: border-color .15s, color .15s, background-color .15s;
    }
    .chip:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .chip.ativo { background: var(--prim); border-color: var(--prim); color: var(--on-prim); }

    .wf-dia { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem; min-height: 5.5rem; display: flex; flex-direction: column; gap: .35rem; }
    .wf-dia.hoje { border-color: var(--prim); }
    .wf-treino-dia { display: flex; align-items: flex-start; gap: .4rem; font-size: .8rem; line-height: 1.2; cursor: pointer; }
    .wf-treino-dia.feito span { text-decoration: line-through; color: var(--tinta-2); }
    .wf-tr { border: 1px solid var(--linha); border-radius: .6rem; padding: .75rem; background: var(--superficie-2); transition: border-color .3s, box-shadow .3s; }
    .wf-tr.destaque { border-color: var(--prim); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent); }
    .wf-ref { border: 1px solid var(--linha); border-radius: .6rem; padding: .75rem; background: var(--superficie-2); }
    .wf-ex-linha { display: grid; grid-template-columns: minmax(0, 1fr) 3.4rem 3.4rem 4rem auto; gap: .35rem; align-items: center; }
    .wf-kg { width: 5rem; padding: .3rem .5rem; }

    .wf-sug {
        position: absolute; left: 0; right: 0; top: calc(100% + .25rem); z-index: 30; max-height: 16rem; overflow: auto;
        background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
    }
    .wf-sug-item { display: flex; flex-direction: column; align-items: flex-start; gap: .1rem; width: 100%; padding: .5rem .75rem; text-align: left; }
    .wf-sug-item.ativo { background: var(--prim-suave); }

    .wf-barras { position: relative; height: 8rem; }
    .wf-plot { position: absolute; inset: 0 0 1.2rem 0; display: flex; gap: .5rem; align-items: flex-end; }
    .wf-col { flex: 1; height: 100%; display: flex; align-items: flex-end; }
    .wf-barra { width: 100%; min-height: 2px; border-radius: .25rem .25rem 0 0; background: var(--prim-suave); border: 1px solid var(--prim-linha); }
    .wf-barra.hoje { background: var(--prim); border-color: var(--prim); }
    .wf-meta-linha { position: absolute; left: 0; right: 0; border-top: 1px dashed var(--tinta-2); pointer-events: none; }
    .wf-rot { position: absolute; left: 0; right: 0; bottom: 0; display: flex; gap: .5rem; }
    .wf-rot span { flex: 1; text-align: center; font-size: .7rem; color: var(--tinta-2); }

    .wf-mapa { height: 100%; overflow: auto; }
    .wf-no {
        position: absolute; width: 188px; height: 38px; display: flex; align-items: center; gap: .15rem; padding: 0 .3rem;
        background: var(--superficie-2); border: 1px solid var(--no); border-radius: .5rem;
    }
    .wf-no-grip { cursor: grab; color: var(--tinta-2); padding: 0 .2rem; touch-action: none; user-select: none; }
    .wf-no-t { flex: 1; min-width: 0; background: transparent; border: 0; color: var(--tinta); font-size: .8rem; padding: .2rem; border-radius: .25rem; }
    .wf-no-t:focus { outline: none; background: var(--superficie); }
    .wf-ponto { width: .65rem; height: .65rem; border-radius: 9999px; background: var(--no); }

    /* Celular: os cartões empilham e o arrastar fica desligado */
    @media (max-width: 899px) {
        .wf-quadro { display: flex; flex-direction: column; gap: 1rem; min-height: 0 !important; height: auto !important; }
        .wf-item { position: static; width: auto; height: auto; }
        .wf-corpo { overflow: visible; }
        .wf-topo { cursor: default; touch-action: auto; }
        .wf-redim { display: none; }
        .wf-ex-linha { grid-template-columns: minmax(0, 1fr) 3.2rem 3.2rem 3.6rem auto; }
    }

        /* ===== Tabela estilo planilha (tabela.js) ===== */
    .wf-xl { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 0; }
    .wf-xl-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-xl-barra .btn-sec { padding: .15rem .5rem; font-size: .75rem; cursor: pointer; }
    .wf-xl-barra .btn-sec:disabled { opacity: .45; cursor: default; }
    .wf-xl-barra .campo { width: auto; padding: .15rem 1.5rem .15rem .45rem; font-size: .75rem; }
    .wf-xl-barra input.campo { padding-right: .45rem; }
    .wf-xl-sep { width: 1px; align-self: stretch; min-height: 1.1rem; background: var(--linha); margin: 0 .1rem; }
    .wf-xl-fx { display: flex; align-items: center; gap: .4rem; }
    .wf-xl-ref { min-width: 3.2rem; text-align: center; font-size: .75rem; font-weight: 600; padding: .2rem .4rem; border: 1px solid var(--linha); border-radius: .375rem; background: var(--superficie-2); color: var(--tinta); white-space: nowrap; }
    .wf-xl-fx .campo { flex: 1; min-width: 0; padding: .2rem .5rem; font-size: .8rem; font-family: ui-monospace, Menlo, Consolas, monospace; }
    .wf-xl-rolagem { flex: 1; min-height: 6rem; overflow: auto; border: 1px solid var(--linha); border-radius: .4rem; }
    .wf-xl-tab { table-layout: fixed; border-collapse: separate; border-spacing: 0; }
    .wf-xl-tab th, .wf-xl-tab td { border: 0; border-right: 1px solid var(--linha); border-bottom: 1px solid var(--linha); padding: 0; }
    .wf-xl-tab .wf-cel { min-width: 0; border-radius: 0; }
    .wf-xl-tab thead th { position: sticky; z-index: 3; background: var(--superficie-2); }
    .wf-xl-tab thead tr.letras th { top: 0; height: 20px; }
    .wf-xl-tab thead tr.nomes th { top: 20px; }
    .wf-xl-tab th.letra { font-size: .68rem; font-weight: 600; color: var(--tinta-2); text-align: center; cursor: pointer; user-select: none; line-height: 20px; }
    .wf-xl-tab th.letra.sel, .wf-xl-tab th.num.sel { background: var(--prim-linha); color: var(--tinta); }
    .wf-xl-tab th.num { position: sticky; left: 0; z-index: 2; font-size: .68rem; font-weight: 500; color: var(--tinta-2); text-align: center; cursor: pointer; background: var(--superficie-2); user-select: none; }
    .wf-xl-tab thead th.canto { left: 0; z-index: 4; cursor: pointer; }
    .wf-xl-tab td.sel { box-shadow: inset 0 0 0 9999px rgba(99, 140, 255, .14); }
    .wf-xl-tab td.ativa { outline: 2px solid var(--prim); outline-offset: -2px; }
    .wf-xl-tab tfoot th, .wf-xl-tab tfoot td { position: sticky; bottom: 0; z-index: 2; background: var(--superficie-2); font-weight: 600; font-size: .8rem; padding: .3rem .5rem; text-align: right; }
    .wf-xl-tab tfoot th.num { left: 0; z-index: 3; text-align: center; }
    .wf-xl-grip { position: absolute; right: -3px; top: 0; bottom: 0; width: 7px; cursor: col-resize; touch-action: none; z-index: 5; }
    .wf-xl-status { display: flex; flex-wrap: wrap; gap: .2rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) { .wf-xl { height: auto; } .wf-xl-rolagem { max-height: 70vh; } }

    /* ===== Tela cheia dos cartões (tela-cheia.js) ===== */
    .wf-fs-btn svg { width: .9rem; height: .9rem; display: block; }
    .wf-item.wf-fs {
        position: fixed !important; left: 0 !important; top: 0 !important; right: 0 !important; bottom: 0 !important;
        width: 100% !important; height: 100% !important; max-width: none !important; max-height: none !important;
        z-index: 2147483000 !important; transform: none !important; margin: 0 !important;
        border-radius: 0; border-width: 0; border-top: 3px solid var(--wc); background: var(--superficie);
    }
    .wf-item.wf-fs .wf-topo { cursor: default; touch-action: auto; }
    .wf-item.wf-fs .wf-redim { display: none !important; }
    .wf-item.wf-fs .wf-corpo { overflow: auto; padding: 1rem 1.25rem; }
    .wf-quadro.wf-fs-ativo { isolation: auto; }
    html.wf-fs-pagina { overflow: hidden; }
</style>

<script type="application/json" id="corpo-cfg">{!! json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
{{-- Precisa vir antes do Alpine iniciar (o Alpine do layout usa defer) --}}
<script src="{{ asset('js/quadro.js') }}?v={{ @filemtime(public_path('js/quadro.js')) }}"></script>
<script src="{{ asset('js/corpo.js') }}?v={{ @filemtime(public_path('js/corpo.js')) }}"></script>
<script src="{{ asset('js/tabela.js') }}?v={{ @filemtime(public_path('js/tabela.js')) }}"></script>
<script src="{{ asset('js/tela-cheia.js') }}?v={{ @filemtime(public_path('js/tela-cheia.js')) }}"></script>
<script src="{{ asset('js/arquivos.js') }}?v={{ @filemtime(public_path('js/arquivos.js')) }}"></script>

<div x-data="corpoAbas()"
     @add-alimento="aoAdicionar($event.detail)"
     @criar-alimento="criarAlimento($event.detail)"
     class="space-y-6">

    {{-- Sugestões de exercícios (usado em Treinos e Cargas) --}}
    <datalist id="wf-exercicios">
        <template x-for="e in estado.exercicios" :key="e.id"><option :value="e.nome"></option></template>
    </datalist>

    {{-- Mini cards circulares --}}
    <nav class="flex flex-wrap gap-x-5 gap-y-4" aria-label="Seções de Corpo">
        <template x-for="a in abas" :key="a.id">
            <button type="button" class="circ" :class="{ 'ativo': aba === a.id }"
                    @click="aba = a.id" :aria-pressed="aba === a.id" :title="a.nome">
                <span class="circ-bola">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="a.icone"></svg>
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
            <x-corpo.item sec="'tarefas'" bid="'tarefa-form'" titulo="Nova tarefa" :minw="260" :minh="560">
                @include('areas.partials.formulario', [
                    'slug'        => $slug,
                    'placeholder' => 'Ex.: Treino de pernas, consulta no dentista…',
                ])
                <p class="text-xs texto-2 mt-3">Use para treino, sono, alimentação e consultas.</p>
            </x-corpo.item>

            <x-corpo.item sec="'tarefas'" bid="'tarefa-lista'" titulo="Tarefas" :minw="320" :minh="200">
                @include('areas.partials.lista', [
                    'tarefas' => $tarefas,
                    'vazio'   => 'Nenhuma tarefa de corpo ainda. Que tal começar com um treino?',
                ])
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'tarefas'])
        </section>

        {{-- ================= TREINOS ================= --}}
        <section class="wf-quadro" x-show="aba === 'treinos'" x-cloak :style="{ minHeight: alturaQuadro('treinos') + 'px' }">
            <x-corpo.item sec="'treinos'" bid="'semana'" titulo="Semana" :minw="480" :minh="200">
                <x-slot name="acoes">
                    <span class="text-xs texto-2"
                          x-text="progressoSemana().feitos + ' de ' + progressoSemana().plan + ' feitos'"></span>
                </x-slot>
                <div class="grid grid-cols-7 gap-2 min-w-[32rem]">
                    <template x-for="d in semana()" :key="d.id">
                        <div class="wf-dia" :class="{ 'hoje': d.hoje }">
                            <p class="text-xs texto-2"><span x-text="d.c"></span> <span x-text="d.num"></span></p>
                            <template x-for="t in treinosDoDia(d.id)" :key="t.id">
                                <label class="wf-treino-dia" :class="{ 'feito': feito(t.id, d.data) }">
                                    <input type="checkbox" class="mt-0.5" :checked="feito(t.id, d.data)" @change="alternar(t.id, d.data)">
                                    <span x-text="t.nome"></span>
                                </label>
                            </template>
                            <p x-show="!treinosDoDia(d.id).length" class="text-xs texto-2">Livre</p>
                        </div>
                    </template>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'treinos'" bid="'treino-form'" :minw="320" :minh="320">
                <x-slot name="cabeca">
                    <h2 class="titulo text-base flex-1 truncate" x-text="tf.id ? 'Editar treino' : 'Novo treino'"></h2>
                </x-slot>
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Nome</label>
                        <input class="campo" x-model="tf.nome" placeholder="Ex.: Peito e tríceps">
                    </div>
                    <div>
                        <label class="rotulo">Dias</label>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="d in dias" :key="d.id">
                                <button type="button" class="chip" :class="{ 'ativo': tf.dias.includes(d.id) }"
                                        :aria-pressed="tf.dias.includes(d.id)" @click="alternarDia(d.id)" x-text="d.c"></button>
                            </template>
                        </div>
                    </div>
                    <div>
                        <label class="rotulo">Horário (opcional)</label>
                        <input type="time" class="campo" x-model="tf.hora">
                    </div>
                    <div>
                        <label class="rotulo">Exercícios</label>
                        <ul class="space-y-2">
                            <template x-for="(it, n) in tf.itens" :key="it.k">
                                <li class="wf-ex-linha">
                                    <input class="campo" list="wf-exercicios" x-model="it.nome" @change="puxarEx(it)" placeholder="Exercício">
                                    <input type="number" min="1" class="campo" x-model.number="it.series" placeholder="Séries" title="Séries">
                                    <input type="number" min="1" class="campo" x-model.number="it.reps" placeholder="Reps" title="Repetições">
                                    <input type="number" min="0" step="0.5" class="campo" x-model="it.kg" placeholder="kg" title="Carga em kg">
                                    <button type="button" class="mini-btn perigo" title="Remover exercício" @click="tf.itens.splice(n, 1)">✕</button>
                                </li>
                            </template>
                        </ul>
                        <button type="button" class="link-prim text-sm mt-2"
                                @click="tf.itens.push({ k: uid(), nome: '', series: 3, reps: 10, kg: '' })">+ Exercício</button>
                    </div>
                    <div>
                        <label class="rotulo">Observações</label>
                        <textarea class="campo" rows="2" x-model="tf.nota" placeholder="Descanso, aquecimento, ritmo…"></textarea>
                    </div>
                    <p class="erro" x-text="tfErro"></p>
                    <div class="flex gap-2">
                        <button type="button" class="btn" @click="salvarTreino()" x-text="tf.id ? 'Salvar alterações' : 'Salvar treino'"></button>
                        <button type="button" class="btn-sec" x-show="tf.id" @click="cancelarTreino()">Cancelar</button>
                    </div>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'treinos'" bid="'treino-lista'" titulo="Meus treinos" :minw="320" :minh="200">
                <p x-show="!estado.treinos.length" class="text-sm texto-2">Nenhum treino ainda. Monte o primeiro no formulário.</p>
                <ul class="space-y-3">
                    <template x-for="t in estado.treinos" :key="t.id">
                        <li :id="'treino-' + t.id" class="wf-tr" :class="{ 'destaque': destaque === 'treino:' + t.id }">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-sm" x-text="t.nome"></p>
                                    <p class="text-xs texto-2" x-text="diasTxt(t) + (t.hora ? ' · ' + t.hora : '')"></p>
                                </div>
                                <div class="flex gap-1">
                                    <button type="button" class="mini-btn" title="Editar treino" @click="editarTreino(t)">✎</button>
                                    <button type="button" class="mini-btn perigo" title="Excluir treino" @click="excluirTreino(t)">✕</button>
                                </div>
                            </div>
                            <ul class="mt-2 space-y-1.5">
                                <template x-for="ex in exsDoTreino(t)" :key="ex.id">
                                    <li class="flex items-center justify-between gap-2 text-sm">
                                        <span class="min-w-0 truncate" x-text="ex.nome"></span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="text-xs texto-2" x-text="ex.series + ' × ' + ex.reps"></span>
                                            <input type="number" min="0" step="0.5" class="campo wf-kg" :value="ex.kg"
                                                   @change="setCarga(ex.id, $event.target.value)" aria-label="Carga em kg">
                                            <span class="text-xs texto-2">kg</span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <p class="text-xs texto-2 mt-2" x-show="t.nota" x-text="t.nota"></p>
                        </li>
                    </template>
                </ul>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'treinos'])
        </section>

        {{-- ================= CARGAS ================= --}}
        <section class="wf-quadro" x-show="aba === 'cargas'" x-cloak :style="{ minHeight: alturaQuadro('cargas') + 'px' }">
            <x-corpo.item sec="'cargas'" bid="'carga-nova'" titulo="Nova carga" :minw="280" :minh="280">
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Exercício</label>
                        <input class="campo" list="wf-exercicios" x-model="cn.nome" placeholder="Ex.: Agachamento livre">
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <div><label class="rotulo">Séries</label><input type="number" min="1" class="campo" x-model="cn.series"></div>
                        <div><label class="rotulo">Reps</label><input type="number" min="1" class="campo" x-model="cn.reps"></div>
                        <div><label class="rotulo">Carga (kg)</label><input type="number" min="0" step="0.5" class="campo" x-model="cn.kg"></div>
                    </div>
                    <p class="erro" x-text="cnErro"></p>
                    <button type="button" class="btn" @click="registrarCarga()">Registrar carga</button>
                    <p class="text-xs texto-2">A carga fica ligada ao exercício: mudar aqui muda também nos treinos.</p>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'cargas'" bid="'carga-lista'" titulo="Exercícios e cargas" :minw="360" :minh="200">
                <p x-show="!estado.exercicios.length" class="text-sm texto-2">Nenhuma carga registrada.</p>
                <ul class="space-y-2.5">
                    <template x-for="ex in exsOrdenados()" :key="ex.id">
                        <li class="card p-3 px-4 space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate" x-text="ex.nome"></p>
                                    <p class="text-xs texto-2" x-text="variacaoTxt(ex)"></p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs" x-show="recorde(ex)">🏆 recorde</span>
                                    <svg x-show="pontos(ex)" viewBox="0 0 100 30" class="w-20 h-7" aria-hidden="true">
                                        <polyline :points="pontos(ex)" fill="none" stroke="var(--prim)" stroke-width="2" vector-effect="non-scaling-stroke"/>
                                    </svg>
                                    <input type="number" min="0" step="0.5" class="campo wf-kg" :value="ex.kg"
                                           @change="setCarga(ex.id, $event.target.value)" aria-label="Carga em kg">
                                    <span class="text-xs texto-2" x-text="'kg × ' + ex.reps"></span>
                                    <button type="button" class="mini-btn perigo" title="Excluir exercício" @click="excluirEx(ex)">✕</button>
                                </div>
                            </div>
                            <p class="text-xs texto-2" x-show="treinosDoEx(ex.id).length"
                               x-text="'Nos treinos: ' + treinosDoEx(ex.id).map(t => t.nome).join(', ')"></p>
                        </li>
                    </template>
                </ul>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'cargas'])
        </section>

        {{-- ================= DIETAS ================= --}}
        <section class="wf-quadro" x-show="aba === 'dietas'" x-cloak :style="{ minHeight: alturaQuadro('dietas') + 'px' }">
            <x-corpo.item sec="'dietas'" bid="'dieta-resumo'" titulo="Resumo do plano" :minw="260" :minh="260">
                <div x-data="{ get t() { return totalDieta() } }" class="space-y-4">
                    <div>
                        <p class="text-xs texto-2">Calorias planejadas</p>
                        <p class="titulo text-3xl">
                            <span x-text="t.kcal"></span>
                            <span class="text-sm texto-2 font-normal"> / <span x-text="estado.cal.metas.kcal"></span> kcal</span>
                        </p>
                        <div class="barra mt-2"><span :style="'width:' + pct(t.kcal, estado.cal.metas.kcal) + '%'"></span></div>
                    </div>
                    <template x-for="m in macros" :key="m.k">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span x-text="m.nome"></span>
                                <span class="texto-2" x-text="fmt(t[m.k]) + ' / ' + estado.cal.metas[m.k] + ' g'"></span>
                            </div>
                            <div class="barra mt-1"><span :style="'width:' + pct(t[m.k], estado.cal.metas[m.k]) + '%'"></span></div>
                        </div>
                    </template>
                    <button type="button" class="btn-sec w-full" @click="addRefeicao()">+ Refeição</button>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'dietas'" bid="'dieta-plano'" titulo="Refeições" :minw="360" :minh="240">
                <div class="space-y-4">
                    <template x-for="r in estado.dieta.refeicoes" :key="r.id">
                        <div class="wf-ref">
                            <div class="flex items-center gap-2">
                                <input class="wf-titulo flex-1" x-model="r.nome" aria-label="Nome da refeição">
                                <input type="time" class="campo !w-auto !py-1" x-model="r.hora" aria-label="Horário">
                                <button type="button" class="mini-btn perigo" title="Excluir refeição" @click="remRefeicao(r)">✕</button>
                            </div>
                            <div class="mt-3">
                                @include('areas.corpo.partials.busca', ['alvo' => "{ tipo: 'dieta', ref: r.id }"])
                            </div>
                            <ul class="mt-2 space-y-1.5">
                                <template x-for="(i, n) in r.itens" :key="i.id">
                                    <li class="flex items-center justify-between gap-2 text-sm">
                                        <span class="min-w-0 truncate" x-text="i.nome + ' · ' + fmt(i.g) + ' g'"></span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="text-xs texto-2"
                                                  x-text="i.kcal + ' kcal · P ' + fmt(i.p) + ' · C ' + fmt(i.c) + ' · G ' + fmt(i.gd)"></span>
                                            <button type="button" class="mini-btn perigo" title="Remover alimento" @click="r.itens.splice(n, 1)">✕</button>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <textarea class="campo mt-2" rows="2" x-model="r.nota" placeholder="Observações da refeição"></textarea>
                            <div class="flex items-center justify-between gap-2 mt-2">
                                <span class="text-sm texto-2" x-text="totalRef(r).kcal + ' kcal'"></span>
                                <button type="button" class="btn-sec" @click="registrarRefeicao(r)">Registrar em calorias de hoje</button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'dietas'])
        </section>

        {{-- ================= ÁGUA ================= --}}
        <section class="wf-quadro" x-show="aba === 'agua'" x-cloak :style="{ minHeight: alturaQuadro('agua') + 'px' }">
            <x-corpo.item sec="'agua'" bid="'agua-hoje'" titulo="Água de hoje" :minw="300" :minh="320">
                <div class="space-y-4">
                    <p>
                        <span class="titulo text-4xl" x-text="totalAgua()"></span>
                        <span class="texto-2 text-sm"> / <span x-text="estado.agua.meta"></span> ml</span>
                    </p>
                    <div class="barra"><span :style="'width:' + pctAgua() + '%'"></span></div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in estado.agua.passos" :key="p">
                            <button type="button" class="btn-sec" @click="beber(p)" x-text="'+' + p + ' ml'"></button>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <input type="number" min="1" max="5000" class="campo" x-model="aguaCustom" placeholder="Outra quantidade (ml)"
                               @keydown.enter.prevent="beberCustom()">
                        <button type="button" class="btn" @click="beberCustom()">Somar</button>
                    </div>
                    <div class="flex items-center justify-between">
                        <h3 class="titulo text-sm">Registros de hoje</h3>
                        <button type="button" class="link-prim text-sm" x-show="estado.agua.log.length" @click="desfazerAgua()">Desfazer último</button>
                    </div>
                    <p x-show="!estado.agua.log.length" class="text-sm texto-2">Nada registrado hoje.</p>
                    <ul class="space-y-1.5">
                        <template x-for="l in estado.agua.log" :key="l.id">
                            <li class="flex items-center justify-between text-sm">
                                <span x-text="l.h + ' · ' + l.ml + ' ml'"></span>
                                <button type="button" class="mini-btn perigo" title="Remover" @click="removerAgua(l.id)">✕</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'agua'" bid="'agua-meta'" titulo="Meta e atalhos" :minw="260" :minh="280">
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Meta diária (ml)</label>
                        <input type="number" min="500" step="100" class="campo" x-model.number="estado.agua.meta">
                    </div>
                    <div>
                        <label class="rotulo">Seu peso (kg)</label>
                        <div class="flex gap-2">
                            <input type="number" min="0" step="0.1" class="campo" x-model.number="estado.agua.peso">
                            <button type="button" class="btn-sec shrink-0" @click="sugerirMeta()">Calcular meta</button>
                        </div>
                        <p class="text-xs texto-2 mt-1">Usa 35 ml por kg como ponto de partida.</p>
                    </div>
                    <div>
                        <label class="rotulo">Botões rápidos</label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <template x-for="(p, i) in estado.agua.passos" :key="p">
                                <span class="chip"><span x-text="p + ' ml'"></span>
                                    <button type="button" title="Remover atalho" @click="remPasso(i)">✕</button></span>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <input type="number" min="1" max="2000" class="campo" x-model="novoPasso" placeholder="ml" @keydown.enter.prevent="addPasso()">
                            <button type="button" class="btn-sec shrink-0" @click="addPasso()">Adicionar</button>
                        </div>
                    </div>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'agua'" bid="'agua-semana'" titulo="Últimos 7 dias" :minw="260" :minh="220">
                @include('areas.corpo.partials.barras', ['tipo' => 'agua'])
                <p class="text-xs texto-2 mt-3">A linha tracejada é a sua meta.</p>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'agua'])
        </section>

        {{-- ================= CALORIAS ================= --}}
        <section class="wf-quadro" x-show="aba === 'calorias'" x-cloak :style="{ minHeight: alturaQuadro('calorias') + 'px' }">
            <x-corpo.item sec="'calorias'" bid="'cal-registrar'" titulo="Registrar alimento" :minw="300" :minh="300">
                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Refeição</label>
                        <select class="campo" x-model="calRef">
                            <template x-for="r in refsCal()" :key="r.id"><option :value="r.id" x-text="r.nome"></option></template>
                        </select>
                    </div>
                    <div>
                        <label class="rotulo">Alimento</label>
                        @include('areas.corpo.partials.busca', ['alvo' => "{ tipo: 'cal' }"])
                        <p class="text-xs texto-2 mt-1">Escreva o alimento e a quantidade: “arroz 150 g”, “2 ovos”.</p>
                    </div>

                    <div class="pt-4 space-y-3" style="border-top: 1px solid var(--linha)">
                        <button type="button" class="link-prim text-sm" @click="cfAberto = !cfAberto"
                                x-text="cfAberto ? 'Fechar cadastro' : 'Cadastrar alimento novo'"></button>
                        <div x-show="cfAberto" x-cloak class="space-y-2">
                            <div><label class="rotulo">Nome</label><input class="campo" x-model="cf.nome" placeholder="Ex.: Marmita da casa"></div>
                            <p class="text-xs texto-2">Valores por 100 g (ou 100 ml).</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="rotulo">Calorias</label><input type="number" min="0" class="campo" x-model="cf.kcal"></div>
                                <div><label class="rotulo">Proteína (g)</label><input type="number" min="0" step="0.1" class="campo" x-model="cf.p"></div>
                                <div><label class="rotulo">Carboidrato (g)</label><input type="number" min="0" step="0.1" class="campo" x-model="cf.c"></div>
                                <div><label class="rotulo">Gordura (g)</label><input type="number" min="0" step="0.1" class="campo" x-model="cf.gd"></div>
                                <div><label class="rotulo">Fibra (g)</label><input type="number" min="0" step="0.1" class="campo" x-model="cf.fib"></div>
                            </div>
                            <p class="erro" x-text="cfErro"></p>
                            <button type="button" class="btn" @click="salvarAlimento()">Salvar alimento</button>
                        </div>

                        <ul class="space-y-1.5" x-show="estado.alimentos.length">
                            <template x-for="a in estado.alimentos" :key="a.id">
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="min-w-0 truncate" x-text="a.nome"></span>
                                    <span class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs texto-2" x-text="a.kcal + ' kcal/100 g'"></span>
                                        <button type="button" class="mini-btn perigo" title="Excluir alimento" @click="removerAlimento(a.id)">✕</button>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'calorias'" bid="'cal-resumo'" titulo="Resumo de hoje" :minw="360" :minh="260">
                <div x-data="{ get t() { return totalCal() } }" class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-4">
                        <div>
                            <p class="titulo text-3xl">
                                <span x-text="t.kcal"></span>
                                <span class="text-sm texto-2 font-normal"> / <span x-text="estado.cal.metas.kcal"></span> kcal</span>
                            </p>
                            <div class="barra mt-2"><span :style="'width:' + pct(t.kcal, estado.cal.metas.kcal) + '%'"></span></div>
                            <p class="text-xs texto-2 mt-1" x-text="falta('kcal')"></p>
                        </div>
                        <template x-for="m in macros" :key="m.k">
                            <div>
                                <div class="flex justify-between text-sm">
                                    <span x-text="m.nome"></span>
                                    <span class="texto-2" x-text="fmt(t[m.k]) + ' / ' + estado.cal.metas[m.k] + ' g · ' + falta(m.k)"></span>
                                </div>
                                <div class="barra mt-1"><span :style="'width:' + pct(t[m.k], estado.cal.metas[m.k]) + '%'"></span></div>
                            </div>
                        </template>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <h3 class="titulo text-sm mb-2">Metas diárias</h3>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="rotulo">Calorias</label><input type="number" min="500" step="50" class="campo" x-model.number="estado.cal.metas.kcal"></div>
                                <div><label class="rotulo">Proteína (g)</label><input type="number" min="0" class="campo" x-model.number="estado.cal.metas.p"></div>
                                <div><label class="rotulo">Carboidrato (g)</label><input type="number" min="0" class="campo" x-model.number="estado.cal.metas.c"></div>
                                <div><label class="rotulo">Gordura (g)</label><input type="number" min="0" class="campo" x-model.number="estado.cal.metas.gd"></div>
                                <div><label class="rotulo">Fibra (g)</label><input type="number" min="0" class="campo" x-model.number="estado.cal.metas.fib"></div>
                            </div>
                        </div>
                        <div>
                            <h3 class="titulo text-sm mb-2">Últimos 7 dias</h3>
                            @include('areas.corpo.partials.barras', ['tipo' => 'cal'])
                        </div>
                    </div>
                </div>
            </x-corpo.item>

            <x-corpo.item sec="'calorias'" bid="'cal-diario'" titulo="Diário de hoje" :minw="360" :minh="200">
                <p x-show="!estado.cal.itens.length" class="text-sm texto-2">Nada registrado hoje.</p>
                <div class="space-y-4">
                    <template x-for="r in refsCal()" :key="r.id">
                        <div x-show="porRef(r.id).length">
                            <div class="flex items-center justify-between mb-1.5">
                                <h3 class="titulo text-sm" x-text="r.nome"></h3>
                                <span class="text-xs texto-2" x-text="somaItens(porRef(r.id)).kcal + ' kcal'"></span>
                            </div>
                            <ul class="space-y-1.5">
                                <template x-for="i in porRef(r.id)" :key="i.id">
                                    <li class="card p-2 px-3 flex items-center justify-between gap-3">
                                        <span class="text-sm min-w-0 truncate"
                                              x-text="i.nome + (i.g ? ' · ' + fmt(i.g) + ' g' : '')"></span>
                                        <span class="flex items-center gap-2 shrink-0">
                                            <span class="text-xs texto-2" x-text="i.h + ' · ' + i.kcal + ' kcal'"></span>
                                            <button type="button" class="mini-btn perigo" title="Remover" @click="removerCal(i.id)">✕</button>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'calorias'])
        </section>

        {{-- ================= METAS ================= --}}
        <section class="wf-quadro" x-show="aba === 'metas'" x-cloak :style="{ minHeight: alturaQuadro('metas') + 'px' }">
            <x-corpo.item sec="'metas'" bid="'metas'" titulo="Metas" :minw="300" :minh="240">
                <div class="space-y-4">
                    <div class="flex gap-2">
                        <input class="campo" x-model="novaMeta" placeholder="Ex.: Perder 5 kg até dezembro" @keydown.enter.prevent="addMeta()">
                        <button type="button" class="btn" @click="addMeta()">Adicionar</button>
                    </div>
                    <div x-show="estado.metas.length">
                        <div class="barra"><span :style="'width:' + pct(metasFeitas(), estado.metas.length) + '%'"></span></div>
                        <p class="text-sm mt-2 texto-2">
                            <span x-text="metasFeitas()"></span> de <span x-text="estado.metas.length"></span> atingidas
                        </p>
                    </div>
                    <p x-show="!estado.metas.length" class="text-sm texto-2">Nenhuma meta ainda. Escreva a primeira acima.</p>
                    <ul class="space-y-2.5">
                        <template x-for="m in estado.metas" :key="m.id">
                            <li class="card p-3 px-4 flex items-center gap-3">
                                <input type="checkbox" x-model="m.feita" class="w-4 h-4">
                                <span class="flex-1 text-sm" :class="{ 'line-through texto-2': m.feita }" x-text="m.texto"></span>
                                <button type="button" class="mini-btn perigo" title="Remover" @click="remMeta(m.id)">✕</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-corpo.item>

            @include('areas.corpo.partials.blocos', ['sec' => 'metas'])
        </section>

        {{-- ================= MAPA MENTAL (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'mapa'" x-cloak :style="{ minHeight: alturaQuadro('mapa') + 'px' }">
            @include('areas.corpo.partials.blocos', ['sec' => 'mapa'])
        </section>

        {{-- ================= QUADRO LIVRE (só blocos) ================= --}}
        <section class="wf-quadro" x-show="aba === 'livre'" x-cloak :style="{ minHeight: alturaQuadro('livre') + 'px' }">
            <p x-show="!blocosDe('livre').length" class="text-sm texto-2">
                Quadro vazio. Use “+ Bloco” para adicionar texto, tabela, lista, imagem ou mapa mental.
            </p>
            @include('areas.corpo.partials.blocos', ['sec' => 'livre'])
        </section>
    </div>
</div>
@endsection