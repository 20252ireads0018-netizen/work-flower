@extends('layouts.app')

@section('titulo', 'Olá, ' . Str::of(auth()->user()->name)->explode(' ')->first())
@section('subtitulo', 'Escolha uma área para trabalhar hoje.')

@section('conteudo')
@php
    // Lista única de pendentes (todas as áreas), com prazo mais próximo primeiro e sem prazo no fim
    $lista = $pendentes->flatten()
        ->sortBy(fn ($t) => $t->prazo?->timestamp ?? PHP_INT_MAX)
        ->values();

    // Mesma lista, separada por área (a ordem das seções segue a ordem de $areas)
    $porArea = $lista->groupBy('area');
@endphp

<div class="grid gap-6 items-start lg:grid-cols-[minmax(0,1fr)_minmax(0,36rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_minmax(0,40rem)_minmax(0,1fr)]">

    {{-- Tarefas gerais: recolhível, arrastável (desktop) e separada por área --}}
    <aside id="janela-tarefas" class="card p-4 order-2 lg:col-start-1 lg:row-start-1 lg:sticky lg:top-24 lg:z-30" aria-label="Tarefas gerais">

        {{-- Cabeçalho fixo: serve de alça para arrastar e nunca é recarregado pelo data-atualiza --}}
        <div id="janela-tarefas-cabecalho" class="flex items-center gap-2 select-none lg:cursor-grab lg:touch-none" title="Arraste para mover · clique duplo para voltar ao lugar">
            <svg class="hidden lg:block w-4 h-4 shrink-0 texto-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/>
                <circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/>
                <circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/>
            </svg>
            <h2 class="titulo text-base">Tarefas</h2>
            <span id="janela-tarefas-total" class="ml-auto text-xs texto-2">{{ $lista->count() }}</span>
            <button type="button" id="janela-tarefas-alternar" class="mini-btn" aria-expanded="true" aria-controls="janela-tarefas-corpo" title="Recolher" aria-label="Recolher tarefas">
                <svg class="w-3.5 h-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
        </div>

        <div id="janela-tarefas-corpo" class="mt-3 max-h-[70vh] overflow-y-auto pr-1">
            <div data-atualiza="painel-tarefas" data-total="{{ $lista->count() }}">
                @if ($lista->isEmpty())
                    <p class="text-sm texto-2">Tudo em dia.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($areas as $slug => $a)
                            @php $grupo = $porArea->get($slug, collect()); @endphp

                            <details class="group" data-grupo="{{ $slug }}" open>
                                <summary class="flex items-center justify-between mb-2 text-xs font-medium cursor-pointer list-none texto-2 [&::-webkit-details-marker]:hidden">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                                        {{ $a['nome'] }}
                                    </span>
                                    <span>{{ $grupo->count() }}</span>
                                </summary>

                                @if ($grupo->isEmpty())
                                    <p class="pl-[1.125rem] text-sm texto-2">Sem pendências.</p>
                                @else
                                    <ul class="space-y-2.5">
                                        @foreach ($grupo as $t)
                                            @php $atrasada = $t->prazo && $t->prazo->isBefore(today()); @endphp
                                            <li class="flex items-center gap-2">
                                                <a href="{{ route('areas.show', $t->area) }}" class="block group min-w-0 flex-1">
                                                    <span class="block text-sm leading-snug truncate group-hover:underline" title="{{ $t->titulo }}">{{ $t->titulo }}</span>
                                                    @if ($t->prazo)
                                                        <span class="block text-xs {{ $atrasada ? 'text-red-700 font-medium' : 'texto-2' }}">
                                                            {{ $t->prazo->format('d/m') }}{{ $atrasada ? ' · atrasada' : '' }}
                                                        </span>
                                                    @endif
                                                </a>

                                                <div class="flex shrink-0 items-center gap-0.5">
                                                    <form method="POST" action="{{ route('tarefas.update', $t) }}" data-ajax>
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="status" value="concluida">
                                                        <button type="submit" class="mini-btn" title="Concluir" aria-label="Concluir a tarefa {{ $t->titulo }}">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('tarefas.destroy', $t) }}" data-ajax onsubmit="return confirm('Excluir esta tarefa?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="mini-btn perigo" title="Excluir" aria-label="Excluir a tarefa {{ $t->titulo }}">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12M9 7V5h6v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </aside>

    {{-- Áreas --}}
    <div class="grid gap-4 sm:grid-cols-2 order-1 lg:col-start-2 lg:row-start-1" data-atualiza="painel-areas">
        @foreach ($areas as $slug => $a)
            @php
                $r      = $resumo[$slug] ?? null;
                $total  = (int) ($r->total ?? 0);
                $feitas = (int) ($r->feitas ?? 0);
                $pct    = $total ? round($feitas / $total * 100) : 0;
            @endphp
            <a href="{{ route('areas.show', $slug) }}" class="card block p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="titulo text-2xl">{{ $a['nome'] }}</h2>
                        <p class="text-sm texto-2 mt-1">{{ $a['descricao'] }}</p>
                    </div>
                    <span class="icone" aria-hidden="true">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $a['icone'] }}"/>
                        </svg>
                    </span>
                </div>

                <div class="mt-8">
                    <div class="barra" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                        <span style="width: {{ $pct }}%"></span>
                    </div>
                    <p class="text-sm mt-2 texto-2">
                        {{ $total ? "{$feitas} de {$total} concluídas" : 'Nenhuma tarefa ainda. Abra a área para adicionar a primeira.' }}
                    </p>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Calendário: arrastável pelo cabeçalho e redimensionável pelo canto (desktop). Lógica em public/js/calendario.js --}}
    <aside id="janela-calendario" class="card p-4 order-3 lg:col-start-3 lg:row-start-1 lg:sticky lg:top-24 lg:z-30 wf-cal"
           aria-label="Calendário" data-base="{{ url('/calendario') }}" data-csrf="{{ csrf_token() }}">
        <div id="cal-cabecalho" class="flex items-center gap-1.5 select-none lg:cursor-grab lg:touch-none" title="Arraste para mover · clique duplo para voltar ao lugar e ao tamanho">
            <svg class="hidden lg:block w-4 h-4 shrink-0 texto-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/>
                <circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/>
                <circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/>
            </svg>
            <h2 class="titulo text-base">Calendário</h2>
            <span id="cal-mes" class="text-xs texto-2 truncate capitalize"></span>
            <span class="ml-auto"></span>
            <button type="button" id="cal-ant" class="mini-btn" title="Mês anterior" aria-label="Mês anterior">‹</button>
            <button type="button" id="cal-hoje" class="mini-btn !w-auto px-1.5 text-xs" title="Ir para hoje">Hoje</button>
            <button type="button" id="cal-prox" class="mini-btn" title="Próximo mês" aria-label="Próximo mês">›</button>
            <button type="button" id="cal-novo" class="mini-btn" title="Novo evento" aria-label="Novo evento">+</button>
            <button type="button" id="cal-alternar" class="mini-btn" aria-expanded="true" aria-controls="cal-corpo" title="Recolher" aria-label="Recolher calendário">
                <svg class="w-3.5 h-3.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
        </div>

        <div id="cal-corpo" class="cal-corpo mt-3">
            <p id="cal-aviso" class="text-xs mb-2" role="alert" style="color:#ef4444" hidden></p>
            <button type="button" id="cal-avisos" class="btn-sec !py-1 w-full text-xs mb-2" hidden>🔔 Ativar avisos dos lembretes neste navegador</button>
            <div id="cal-grade" class="cal-grade" role="grid" aria-label="Dias do mês"></div>
            <div id="cal-dia" class="cal-painel mt-3"></div>
            <form id="cal-form" class="cal-painel cal-form mt-3 space-y-2" hidden></form>
        </div>
        <span id="cal-redim" class="cal-redim hidden lg:block" title="Arraste para mudar o tamanho · clique duplo volta ao tamanho original" aria-hidden="true"></span>
    </aside>

</div>

<style>
    /* sem position aqui: o lg:sticky da janela segura a alça de redimensionar (absolute; no celular ela some) */
    .wf-cal { display: flex; flex-direction: column; }
    /* a coluna da direita é estreita: o calendário começa com pelo menos 18,5rem (passa por cima da margem) */
    @media (min-width: 1024px) { .wf-cal { width: max(100%, 18.5rem); } }
    .wf-cal.com-altura .cal-corpo { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; }
    .wf-cal.com-altura .cal-grade { flex: 1; min-height: 14rem; grid-template-rows: auto repeat(6, minmax(0, 1fr)); }
    .wf-cal.recolhido { height: auto !important; }
    .cal-grade { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 2px; }
    .cal-sem { text-align: center; font-size: .65rem; font-weight: 600; color: var(--tinta-2); padding-bottom: .15rem; }
    .cal-d { display: flex; flex-direction: column; align-items: stretch; gap: 1px; min-height: 2.6rem; padding: .15rem; border-radius: .4rem; border: 1px solid transparent; background: var(--superficie-2); text-align: left; overflow: hidden; cursor: pointer; }
    .cal-d:hover { border-color: var(--linha); }
    .cal-d.fora { opacity: .45; }
    .cal-d.hoje .cal-n { background: var(--prim); color: var(--on-prim); }
    .cal-d.sel { border-color: var(--prim); box-shadow: 0 0 0 1px var(--prim) inset; }
    .cal-n { align-self: flex-start; font-size: .7rem; line-height: 1; padding: .15rem .3rem; border-radius: 9999px; font-weight: 600; }
    .cal-itens { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
    .cal-chip { display: block; font-size: .58rem; line-height: 1.25; padding: 0 .2rem; border-radius: .2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        background: color-mix(in srgb, var(--c) 28%, transparent); border-left: 2px solid var(--c); }
    .cal-chip.tarefa { background: transparent; border-left: 2px dashed var(--tinta-2); }
    .cal-mais { font-size: .55rem; color: var(--tinta-2); padding-left: .2rem; }
    .cal-painel { border-top: 1px solid var(--linha); padding-top: .6rem; }
    .cal-ev { border-left: 3px solid var(--c, var(--tinta-2)); border-radius: .3rem; background: var(--superficie-2); }
    .cal-ev.tarefa { border-left-style: dashed; }
    .cal-ev-btn { display: block; width: 100%; text-align: left; padding: .3rem .5rem; }
    .cal-ev-t { display: block; font-size: .8rem; font-weight: 600; overflow-wrap: anywhere; }
    .cal-ev-t.feita { text-decoration: line-through; opacity: .7; }
    .cal-ev-q { display: block; font-size: .68rem; color: var(--tinta-2); }
    .cal-campo { display: block; font-size: .75rem; }
    .cal-campo > span { display: block; color: var(--tinta-2); font-size: .68rem; margin-bottom: .15rem; }
    .cal-par { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .5rem; }
    .cal-form .campo { padding-top: .3rem; padding-bottom: .3rem; font-size: .8rem; }
    .cal-cor { position: relative; width: 1.25rem; height: 1.25rem; border-radius: 9999px; background: var(--c); cursor: pointer; }
    .cal-cor input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
    .cal-cor:has(input:checked) { box-shadow: 0 0 0 2px var(--superficie), 0 0 0 4px var(--c); }
    .cal-cor:has(input:focus-visible) { outline: 2px solid var(--tinta); outline-offset: 3px; }
    .cal-redim { position: absolute; right: 3px; bottom: 3px; width: 16px; height: 16px; cursor: nwse-resize; touch-action: none; opacity: .7;
        background: linear-gradient(135deg, transparent 52%, var(--tinta-2) 52%, var(--tinta-2) 60%, transparent 60%, transparent 72%, var(--tinta-2) 72%, var(--tinta-2) 80%, transparent 80%); }
    .wf-cal.recolhido .cal-redim { display: none; }
</style>
<script src="{{ asset('js/calendario.js') }}?v={{ @filemtime(public_path('js/calendario.js')) }}" defer></script>

<script>
(function () {
    const raiz = document.getElementById('janela-tarefas');
    if (!raiz || raiz.dataset.pronto) return;
    raiz.dataset.pronto = '1';

    const K = {
        aberto: 'painel.tarefas.aberto',
        pos:    'painel.tarefas.pos',
        grupos: 'painel.tarefas.grupos',
    };
    const ler = (chave, padrao) => {
        try { const v = localStorage.getItem(chave); return v === null ? padrao : JSON.parse(v); }
        catch (e) { return padrao; }
    };
    const gravar = (chave, valor) => {
        try { localStorage.setItem(chave, JSON.stringify(valor)); } catch (e) {}
    };

    const cabecalho = document.getElementById('janela-tarefas-cabecalho');
    const corpo     = document.getElementById('janela-tarefas-corpo');
    const botao     = document.getElementById('janela-tarefas-alternar');
    const total     = document.getElementById('janela-tarefas-total');
    const seta      = botao.querySelector('svg');

    /* ---------- Expandir / recolher o card ---------- */
    function definirAberto(aberto, salvar = true) {
        corpo.hidden = !aberto;
        const rotulo = aberto ? 'Recolher tarefas' : 'Expandir tarefas';
        botao.setAttribute('aria-expanded', String(aberto));
        botao.setAttribute('aria-label', rotulo);
        botao.title = rotulo;
        seta.style.rotate = aberto ? '180deg' : '0deg';
        if (salvar) gravar(K.aberto, aberto);
    }
    botao.addEventListener('click', () => definirAberto(corpo.hidden));
    definirAberto(ler(K.aberto, true), false);

    /* ---------- Recolher / expandir cada área (lembra a escolha) ---------- */
    function aplicarGrupos() {
        const estado = ler(K.grupos, {});
        raiz.querySelectorAll('details[data-grupo]').forEach((d) => {
            const aberto = estado[d.dataset.grupo];
            if (aberto !== undefined && d.open !== aberto) d.open = aberto;
        });
    }
    // "toggle" não borbulha, então escutamos na fase de captura
    raiz.addEventListener('toggle', (e) => {
        const d = e.target;
        if (!d.matches || !d.matches('details[data-grupo]')) return;
        const estado = ler(K.grupos, {});
        estado[d.dataset.grupo] = d.open;
        gravar(K.grupos, estado);
    }, true);

    /* ---------- Reaplica estado quando o data-atualiza trocar a lista ---------- */
    function sincronizar() {
        aplicarGrupos();
        const fonte = raiz.querySelector('[data-atualiza="painel-tarefas"]');
        if (fonte && total.textContent !== fonte.dataset.total) total.textContent = fonte.dataset.total;
    }
    new MutationObserver(sincronizar).observe(raiz, { childList: true, subtree: true });
    sincronizar();

    /* ---------- Arrastar com o mouse (só no desktop, a partir de 1024px) ---------- */
    const desktop = window.matchMedia('(min-width: 1024px)');
    let pos = ler(K.pos, { x: 0, y: 0 });
    let arrasto = null;

    const aplicarPos = () => {
        raiz.style.translate = desktop.matches ? pos.x + 'px ' + pos.y + 'px' : '';
    };

    // Mantém o card dentro da janela (o cabeçalho sempre fica visível)
    function limitar(x, y) {
        const r  = raiz.getBoundingClientRect();
        const l0 = r.left - pos.x;
        const t0 = r.top - pos.y;
        const minX = -l0;
        const maxX = Math.max(minX, window.innerWidth - r.width - l0);
        const minY = -t0;
        const maxY = Math.max(minY, window.innerHeight - 56 - t0);
        return {
            x: Math.min(Math.max(x, minX), maxX),
            y: Math.min(Math.max(y, minY), maxY),
        };
    }

    cabecalho.addEventListener('pointerdown', (e) => {
        if (!desktop.matches || e.button !== 0 || e.target.closest('button')) return;
        arrasto = { px: e.clientX, py: e.clientY, x: pos.x, y: pos.y };
        cabecalho.setPointerCapture(e.pointerId);
        cabecalho.style.cursor = 'grabbing';
        raiz.style.boxShadow = '0 20px 40px rgba(0, 0, 0, .45)';
        e.preventDefault();
    });

    cabecalho.addEventListener('pointermove', (e) => {
        if (!arrasto) return;
        pos = limitar(arrasto.x + e.clientX - arrasto.px, arrasto.y + e.clientY - arrasto.py);
        aplicarPos();
    });

    function soltar() {
        if (!arrasto) return;
        arrasto = null;
        cabecalho.style.cursor = '';
        raiz.style.boxShadow = '';
        gravar(K.pos, pos);
    }
    cabecalho.addEventListener('pointerup', soltar);
    cabecalho.addEventListener('pointercancel', soltar);

    // Clique duplo no cabeçalho devolve o card ao lugar original
    cabecalho.addEventListener('dblclick', (e) => {
        if (e.target.closest('button')) return;
        pos = { x: 0, y: 0 };
        aplicarPos();
        gravar(K.pos, pos);
    });

    function reajustar() {
        aplicarPos();
        if (desktop.matches) {
            pos = limitar(pos.x, pos.y);
            aplicarPos();
        }
    }
    desktop.addEventListener('change', reajustar);
    window.addEventListener('resize', reajustar);
    reajustar();
})();
</script>
{{-- Tutorial do painel (botão flutuante) --}}
{{-- abre sozinho no primeiro acesso da conta (PainelController::index decide) --}}
@include('partials.tutorial', ['tutorialAuto' => $tutorialAuto ?? false])
@endsection
