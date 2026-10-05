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

    <button type="submit" class="btn">Adicionar</button>
</form>