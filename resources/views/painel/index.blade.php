@extends('layouts.app')

@section('titulo', 'Olá, ' . Str::of(auth()->user()->name)->explode(' ')->first())
@section('subtitulo', 'Escolha uma área para trabalhar hoje.')

@section('conteudo')
@php
    // Lista única de pendentes (todas as áreas), com prazo mais próximo primeiro e sem prazo no fim
    $lista = $pendentes->flatten()
        ->sortBy(fn ($t) => $t->prazo?->timestamp ?? PHP_INT_MAX)
        ->values();
@endphp

<div class="grid gap-6 items-start lg:grid-cols-[minmax(0,1fr)_minmax(0,36rem)_minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_minmax(0,40rem)_minmax(0,1fr)]">

    {{-- Tarefas gerais (esquerda no desktop, abaixo das áreas no celular) --}}
    <aside class="card p-4 order-2 lg:col-start-1 lg:row-start-1 lg:sticky lg:top-24" aria-label="Tarefas gerais" data-atualiza="painel-tarefas">
        <div class="flex items-baseline justify-between mb-3">
            <h2 class="titulo text-base">Tarefas</h2>
            <span class="text-xs texto-2">{{ $lista->count() }}</span>
        </div>

        @if ($lista->isEmpty())
            <p class="text-sm texto-2">Tudo em dia.</p>
        @else
            <ul class="space-y-2.5">
                @foreach ($lista->take(6) as $t)
                    @php $atrasada = $t->prazo && $t->prazo->isBefore(today()); @endphp
                    <li class="flex items-center gap-2">
                        <a href="{{ route('areas.show', $t->area) }}" class="block group min-w-0 flex-1">
                            <span class="block text-sm leading-snug truncate group-hover:underline" title="{{ $t->titulo }}">{{ $t->titulo }}</span>
                            <span class="block text-xs {{ $atrasada ? 'text-red-700 font-medium' : 'texto-2' }}">
                                {{ $areas[$t->area]['nome'] ?? $t->area }}@if ($t->prazo) · {{ $t->prazo->format('d/m') }}{{ $atrasada ? ' · atrasada' : '' }}@endif
                            </span>
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

            @if ($lista->count() > 6)
                <p class="text-xs texto-2 mt-3">+ {{ $lista->count() - 6 }} {{ $lista->count() - 6 === 1 ? 'outra' : 'outras' }}</p>
            @endif
        @endif
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
@endsection