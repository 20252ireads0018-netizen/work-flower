{{-- Espera: $tarefas | opcional: $vazio --}}
@php
    $abertas = $tarefas->where('status', '!=', 'concluida')
        ->sortBy(fn ($t) => $t->prazo?->timestamp ?? PHP_INT_MAX)
        ->values();
    $feitas  = $tarefas->where('status', 'concluida')->values();
    $total   = $tarefas->count();
    $pct     = $total ? round($feitas->count() / $total * 100) : 0;
@endphp

<section class="space-y-6" aria-label="Tarefas da área" data-atualiza="lista">
    @if ($total)
        <div>
            <div class="barra" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                <span style="width: {{ $pct }}%"></span>
            </div>
            <p class="text-sm mt-2 texto-2">{{ $feitas->count() }} de {{ $total }} concluídas</p>
        </div>
    @endif

    @if ($abertas->isNotEmpty())
        <div>
            <h2 class="titulo text-base mb-3">Pendentes <span class="text-xs texto-2 font-normal">{{ $abertas->count() }}</span></h2>
            <ul class="grid gap-3 [grid-template-columns:repeat(auto-fill,minmax(10.5rem,10.5rem))]">
                @foreach ($abertas as $t) @include('areas.partials.item', ['t' => $t]) @endforeach
            </ul>
        </div>
    @elseif (! $total)
        <p class="text-sm texto-2">{{ $vazio ?? 'Nenhuma tarefa ainda. Adicione a primeira ao lado.' }}</p>
    @else
        <p class="text-sm texto-2">Tudo em dia.</p>
    @endif

    @if ($feitas->isNotEmpty())
        <div>
            <h2 class="titulo text-base mb-3">Concluídas <span class="text-xs texto-2 font-normal">{{ $feitas->count() }}</span></h2>
            <ul class="space-y-2.5">
                @foreach ($feitas as $t) @include('areas.partials.item', ['t' => $t]) @endforeach
            </ul>
        </div>
    @endif
</section>