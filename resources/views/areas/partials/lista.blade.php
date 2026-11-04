{{-- Quadro de tarefas da área (Corpo, Mente, Carteira, Negócios, Diverso): colunas Pendente / Em andamento / Concluída.
     Arraste o cartão entre as colunas (ou use ‹ ›), dê cor, categoria e responsável, e filtre por categoria ou pessoa.
     Tudo grava pelos formulários data-ajax do layout, que trocam este bloco (data-atualiza="lista") pela versão nova.
     Espera: $tarefas | opcional: $vazio --}}
@php
    use App\Models\Tarefa;
    use App\Support\Compartilhar;

    $tqDono   = (int) (request()->attributes->get('wf_dono') ?: auth()->id());
    $tqArea   = request()->attributes->get('wf_area');
    $tqPessoas = $tqArea ? Compartilhar::pessoas($tqDono, $tqArea) : [];
    $tqEdita  = ! request()->attributes->get('wf_so_ver');
    $tqEu     = auth()->id();
    $tqCats   = $tarefas->pluck('categoria')->filter()->unique()->sort()->values();
    $tqTotal  = $tarefas->count();
    $tqFeitas = $tarefas->where('status', 'concluida')->count();
    $tqPct    = $tqTotal ? round($tqFeitas / $tqTotal * 100) : 0;
    $tqCols   = array_keys(Tarefa::STATUS);
    $tqOrdem  = fn ($lista) => $lista->sortBy(fn ($t) => $t->prazo?->timestamp ?? PHP_INT_MAX)->values();
@endphp

<section class="tq space-y-3" aria-label="Quadro de tarefas" data-atualiza="lista" x-data="{ cat: sessionStorage.getItem('tq.cat') || '', quem: sessionStorage.getItem('tq.quem') || '' }"
         x-init="$watch('cat', v => sessionStorage.setItem('tq.cat', v)); $watch('quem', v => sessionStorage.setItem('tq.quem', v))">
    @if ($tqTotal)
        <div>
            <div class="barra" role="progressbar" aria-valuenow="{{ $tqPct }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $tqPct }}%"></span></div>
            <p class="text-xs mt-1.5 texto-2">{{ $tqFeitas }} de {{ $tqTotal }} concluídas</p>
        </div>
    @endif

    @if ($tqCats->isNotEmpty() || count($tqPessoas) > 1)
        <div class="flex flex-wrap items-center gap-1.5 text-xs" role="group" aria-label="Filtrar tarefas">
            <button type="button" class="tq-chip" :class="{ 'on': !cat }" @click="cat = ''">Todas</button>
            @foreach ($tqCats as $c)
                <button type="button" class="tq-chip" :class="{ 'on': cat === @js($c) }" @click="cat = cat === @js($c) ? '' : @js($c)">{{ $c }}</button>
            @endforeach
            @if (count($tqPessoas) > 1)
                <select class="campo !py-0.5 !w-auto text-xs ml-auto" x-model="quem" aria-label="Filtrar por responsável">
                    <option value="">Todas as pessoas</option>
                    <option value="{{ $tqEu }}">Só as minhas</option>
                    @foreach ($tqPessoas as $id => $nome)
                        @continue($id === $tqEu)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    @endif

    @unless ($tqTotal)
        <p class="text-sm texto-2">{{ $vazio ?? 'Nenhuma tarefa ainda. Adicione a primeira ao lado.' }}</p>
    @endunless

    <div class="tq-grade">
        @foreach (Tarefa::STATUS as $st => $rotulo)
            @php $lista = $tqOrdem($tarefas->where('status', $st)); @endphp
            <div class="tq-col" data-tq-col="{{ $st }}" aria-label="{{ $rotulo }}">
                <h3 class="tq-col-tit">{{ $rotulo }} <span class="texto-2 font-normal">{{ $lista->count() }}</span></h3>
                @foreach ($lista as $t)
                    @php
                        $atrasada = $st !== 'concluida' && $t->prazo && $t->prazo->isBefore(today());
                        $minha = $t->responsavel_id === $tqEu;
                        $mexe = $tqEdita || $minha;
                        $i = array_search($st, $tqCols, true);
                    @endphp
                    <article class="tq-card" style="--c: {{ $t->cor ?: 'var(--linha)' }}" data-tq-id="{{ $t->id }}" @if ($mexe) draggable="true" @endif
                             x-show="(!cat || cat === @js((string) $t->categoria)) && (!quem || quem === '{{ (string) $t->responsavel_id }}')">
                        <p class="text-sm font-medium leading-snug break-words {{ $st === 'concluida' ? 'line-through texto-2' : '' }}">{{ $t->titulo }}</p>
                        @if ($t->descricao) <p class="text-xs texto-2 mt-1 break-words">{{ \Illuminate\Support\Str::limit($t->descricao, 160) }}</p> @endif
                        <div class="flex flex-wrap items-center gap-1.5 mt-2 text-[11px]">
                            @if ($t->categoria) <span class="tq-tag">{{ $t->categoria }}</span> @endif
                            @if ($t->prazo) <span style="color: {{ $atrasada ? '#f87171' : 'var(--tinta-2)' }}">📅 {{ $t->prazo->format('d/m') }}{{ $atrasada ? ' · atrasada' : '' }}</span> @endif
                            @if ($t->responsavel_id && isset($tqPessoas[$t->responsavel_id]))
                                <span class="tq-tag" title="Responsável">👤 {{ $minha ? 'você' : \Illuminate\Support\Str::of($tqPessoas[$t->responsavel_id])->explode(' ')->first() }}</span>
                            @endif
                        </div>

                        @if ($mexe)
                            <div class="flex items-center gap-1 mt-2">
                                {{-- mover pelo teclado ou no celular; o arrastar usa o mesmo formulário --}}
                                <form method="POST" action="{{ route('tarefas.update', $t) }}" data-ajax data-tq-status="{{ $t->id }}" class="contents">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="status" value="{{ $st }}">
                                    <button type="submit" class="mini-btn" @disabled($i === 0) onclick="this.form.status.value = @js($tqCols[max(0, $i - 1)])" aria-label="Mover para a coluna anterior" title="Coluna anterior">‹</button>
                                    <button type="submit" class="mini-btn" @disabled($i === count($tqCols) - 1) onclick="this.form.status.value = @js($tqCols[min(count($tqCols) - 1, $i + 1)])" aria-label="Mover para a próxima coluna" title="Próxima coluna">›</button>
                                </form>
                                @if ($tqEdita)
                                    <details class="tq-edit ml-auto">
                                        <summary class="mini-btn" title="Editar" aria-label="Editar a tarefa {{ $t->titulo }}">✎</summary>
                                        <form method="POST" action="{{ route('tarefas.update', $t) }}" data-ajax class="tq-form">
                                            @csrf @method('PUT')
                                            <input name="titulo" class="campo !py-1 text-sm" maxlength="150" required value="{{ $t->titulo }}" aria-label="Título">
                                            <input name="categoria" class="campo !py-1 text-sm" maxlength="40" value="{{ $t->categoria }}" placeholder="Categoria (ex.: Casa)" list="tq-cats" aria-label="Categoria">
                                            <input name="prazo" type="date" class="campo !py-1 text-sm" value="{{ $t->prazo?->format('Y-m-d') }}" aria-label="Prazo">
                                            @if (count($tqPessoas) > 1)
                                                <select name="responsavel_id" class="campo !py-1 text-sm" aria-label="Responsável">
                                                    <option value="">Sem responsável</option>
                                                    @foreach ($tqPessoas as $id => $nome)
                                                        <option value="{{ $id }}" @selected($t->responsavel_id === $id)>{{ $nome }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                            <div class="flex flex-wrap gap-1" role="radiogroup" aria-label="Cor">
                                                <label class="tq-cor" style="--c: var(--linha)" title="Sem cor"><input type="radio" name="cor" value="" @checked(! $t->cor)></label>
                                                @foreach (Tarefa::CORES as $c)
                                                    <label class="tq-cor" style="--c: {{ $c }}"><input type="radio" name="cor" value="{{ $c }}" @checked($t->cor === $c) aria-label="Cor {{ $c }}"></label>
                                                @endforeach
                                            </div>
                                            <p class="erro text-xs" data-erro="titulo"></p>
                                            <button type="submit" class="btn !py-1 text-sm">Salvar</button>
                                        </form>
                                    </details>
                                    <form method="POST" action="{{ route('tarefas.destroy', $t) }}" data-ajax onsubmit="return confirm('Excluir esta tarefa?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="mini-btn perigo" title="Excluir" aria-label="Excluir a tarefa {{ $t->titulo }}">✕</button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endforeach
    </div>
    <datalist id="tq-cats">@foreach ($tqCats as $c)<option value="{{ $c }}">@endforeach</datalist>
</section>

@once
    <style>
        .tq-grade { display: grid; gap: .75rem; grid-template-columns: repeat(3, minmax(0, 1fr)); align-items: start; }
        @media (max-width: 640px) { .tq-grade { grid-template-columns: 1fr; } }
        .tq-col { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .75rem; padding: .6rem; min-height: 7rem; display: flex; flex-direction: column; gap: .5rem; }
        .tq-col.alvo { border-color: var(--prim); box-shadow: 0 0 0 2px color-mix(in srgb, var(--prim) 30%, transparent) inset; }
        .tq-col-tit { font-size: .8rem; font-weight: 700; margin: 0 0 .1rem; }
        .tq-card { background: var(--superficie); border: 1px solid var(--linha); border-left: 4px solid var(--c); border-radius: .6rem; padding: .55rem .65rem; }
        .tq-card[draggable="true"] { cursor: grab; }
        .tq-card.arrastando { opacity: .45; }
        .tq-tag { padding: .05rem .4rem; border-radius: 9999px; background: var(--superficie-2); border: 1px solid var(--linha); }
        .tq-chip { padding: .15rem .6rem; border-radius: 9999px; border: 1px solid var(--linha); background: var(--superficie-2); }
        .tq-chip.on { background: var(--prim); color: var(--on-prim); border-color: var(--prim); }
        .tq-edit { position: relative; }
        .tq-edit > summary { list-style: none; cursor: pointer; }
        .tq-edit > summary::-webkit-details-marker { display: none; }
        .tq-form { position: absolute; right: 0; top: 1.9rem; z-index: 30; width: 15rem; display: flex; flex-direction: column; gap: .4rem; padding: .6rem;
            background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0,0,0,.35); }
        .tq-cor { position: relative; width: 1.15rem; height: 1.15rem; border-radius: 9999px; background: var(--c); border: 1px solid var(--linha); cursor: pointer; }
        .tq-cor input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .tq-cor:has(input:checked) { box-shadow: 0 0 0 2px var(--superficie), 0 0 0 4px var(--tinta-2); }
    </style>
    <script>
        // arrastar um cartão para outra coluna = mandar o formulário de coluna dele com o status novo
        (function () {
            if (window.wfQuadroTarefas) return;
            window.wfQuadroTarefas = true;
            let arrastado = null;
            document.addEventListener('dragstart', e => {
                const c = e.target.closest && e.target.closest('.tq-card[draggable="true"]');
                if (!c) return;
                arrastado = c;
                c.classList.add('arrastando');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', c.dataset.tqId); } catch (x) {}
            });
            document.addEventListener('dragend', () => {
                if (arrastado) arrastado.classList.remove('arrastando');
                arrastado = null;
                document.querySelectorAll('.tq-col.alvo').forEach(el => el.classList.remove('alvo'));
            });
            document.addEventListener('dragover', e => {
                const col = arrastado && e.target.closest && e.target.closest('[data-tq-col]');
                if (!col) return;
                e.preventDefault();
                document.querySelectorAll('.tq-col.alvo').forEach(el => { if (el !== col) el.classList.remove('alvo'); });
                col.classList.add('alvo');
            });
            document.addEventListener('drop', e => {
                const col = arrastado && e.target.closest && e.target.closest('[data-tq-col]');
                if (!col) return;
                e.preventDefault();
                const f = document.querySelector(`form[data-tq-status="${arrastado.dataset.tqId}"]`);
                if (f && f.status.value !== col.dataset.tqCol) {
                    f.status.value = col.dataset.tqCol;
                    col.appendChild(arrastado); // já mostra no lugar novo enquanto o servidor confirma
                    f.requestSubmit();
                }
            });
        })();
    </script>
@endonce
