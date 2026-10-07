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

</div>

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
@endsection