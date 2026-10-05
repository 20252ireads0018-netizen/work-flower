{{-- Espera: $t (Tarefa) --}}
@php
    $concluida = $t->status === 'concluida';
    $atrasada  = ! $concluida && $t->prazo && $t->prazo->isBefore(today());
@endphp

<li class="card p-4 flex flex-col gap-3">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium leading-snug break-words {{ $concluida ? 'line-through texto-2' : '' }}">{{ $t->titulo }}</p>

            @if ($t->descricao)
                <p class="text-sm texto-2 mt-1 break-words">{{ $t->descricao }}</p>
            @endif

            @if ($t->prazo)
                <p class="text-xs mt-2 flex items-center gap-1.5"
                   style="color: {{ $atrasada ? '#f87171' : 'var(--tinta-2)' }}"
                   title="{{ $atrasada ? 'Prazo vencido' : 'Prazo' }}">
                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>
                    </svg>
                    <span>{{ $t->prazo->format('d/m/Y') }}</span>
                </p>
            @endif
        </div>

        <form method="POST" action="{{ route('tarefas.destroy', $t) }}" data-ajax onsubmit="return confirm('Excluir esta tarefa?')" class="shrink-0">
            @csrf
            @method('DELETE')
            <button type="submit" class="mini-btn perigo" title="Excluir" aria-label="Excluir a tarefa {{ $t->titulo }}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 7h12M9 7V5h6v2M7 7l1 12h8l1-12M10 11v5M14 11v5"/></svg>
            </button>
        </form>
    </div>

    {{-- Status: pendente / em andamento / concluída (salva sozinho ao trocar) --}}
    <form method="POST" action="{{ route('tarefas.update', $t) }}" data-ajax>
        @csrf
        @method('PUT')
        <select name="status" class="select-status w-full" aria-label="Status da tarefa {{ $t->titulo }}" onchange="this.form.requestSubmit()">
            @foreach (\App\Models\Tarefa::STATUS as $valor => $rotulo)
                <option value="{{ $valor }}" @selected($t->status === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
    </form>
</li>
