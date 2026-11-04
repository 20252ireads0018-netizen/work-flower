{{-- Espera: $slug | opcionais: $placeholder, $embutido (true dentro de um cartão do quadro) --}}
@php $embutido = $embutido ?? false; @endphp
<form method="POST" action="{{ route('areas.tarefas.store', $slug) }}" class="{{ $embutido ? 'space-y-4' : 'card p-5 space-y-4' }}" data-ajax data-limpar>
    @csrf
    @unless ($embutido)
        <h2 class="titulo text-lg">Nova tarefa</h2>
    @endunless

    <div>
        <label for="titulo" class="rotulo">Título</label>
        <input id="titulo" name="titulo" type="text" maxlength="150" required
               value="{{ old('titulo') }}" placeholder="{{ $placeholder ?? '' }}" class="campo">
        <p class="erro" data-erro="titulo">@error('titulo'){{ $message }}@enderror</p>
    </div>

    <div>
        <label for="descricao" class="rotulo">Descrição <span class="texto-2 font-normal">(opcional)</span></label>
        <textarea id="descricao" name="descricao" rows="3" maxlength="1000" class="campo">{{ old('descricao') }}</textarea>
        <p class="erro" data-erro="descricao">@error('descricao'){{ $message }}@enderror</p>
    </div>

    <div>
        <label for="prazo" class="rotulo">Prazo <span class="texto-2 font-normal">(opcional)</span></label>
        <div class="max-w-[11rem]">
            <input id="prazo" name="prazo" type="date" value="{{ old('prazo') }}" class="campo">
        </div>
        <p class="erro" data-erro="prazo">@error('prazo'){{ $message }}@enderror</p>
    </div>

    {{-- cor, categoria e responsável aparecem no quadro de tarefas (partials/lista) --}}
    @php
        $tfDono = (int) (request()->attributes->get('wf_dono') ?: auth()->id());
        $tfPessoas = \App\Support\Compartilhar::pessoas($tfDono, $slug);
    @endphp
    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label for="categoria" class="rotulo">Categoria <span class="texto-2 font-normal">(opcional)</span></label>
            <input id="categoria" name="categoria" type="text" maxlength="40" class="campo" placeholder="Ex.: Casa, Trabalho" list="tq-cats">
            <p class="erro" data-erro="categoria"></p>
        </div>
        @if (count($tfPessoas) > 1)
            <div>
                <label for="responsavel_id" class="rotulo">Responsável</label>
                <select id="responsavel_id" name="responsavel_id" class="campo">
                    <option value="">Ninguém em especial</option>
                    @foreach ($tfPessoas as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}{{ $id === auth()->id() ? ' (você)' : '' }}</option>
                    @endforeach
                </select>
                <p class="erro" data-erro="responsavel_id"></p>
            </div>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-1.5" role="radiogroup" aria-label="Cor da tarefa">
        <span class="rotulo !mb-0 mr-1">Cor</span>
        <label class="tq-cor" style="--c: var(--linha)" title="Sem cor"><input type="radio" name="cor" value="" checked></label>
        @foreach (\App\Models\Tarefa::CORES as $c)
            <label class="tq-cor" style="--c: {{ $c }}"><input type="radio" name="cor" value="{{ $c }}" aria-label="Cor {{ $c }}"></label>
        @endforeach
    </div>

    <button type="submit" class="btn">Adicionar</button>
</form>