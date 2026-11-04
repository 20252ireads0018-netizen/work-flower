{{-- Formulário de um produto da loja ($p = null para um produto novo). Usado em areas/carteira/loja. --}}
<form method="POST" action="{{ $p ? route('carteira.loja.produtos.salvar', $p) : route('carteira.loja.produtos.criar') }}" enctype="multipart/form-data" class="mt-3 space-y-3">
    @csrf
    <div class="lj-grade">
        <label class="lj-campo"><span>Nome</span><input name="nome" class="campo" maxlength="120" required value="{{ $p ? $p->nome : old('nome') }}"></label>
        <label class="lj-campo"><span>Preço (R$)</span><input name="preco" class="campo" type="number" step="0.01" min="0.01" required value="{{ $p ? $p->preco : old('preco') }}"></label>
        <label class="lj-campo"><span>Preço antigo, riscado (opcional)</span><input name="preco_antigo" class="campo" type="number" step="0.01" min="0" value="{{ $p ? $p->preco_antigo : old('preco_antigo') }}"></label>
        <label class="lj-campo"><span>Estoque (vazio = não controlar)</span><input name="estoque" class="campo" type="number" step="1" min="0" value="{{ $p ? $p->estoque : old('estoque') }}"></label>
        <label class="lj-campo"><span>Ordem na vitrine</span><input name="ordem" class="campo" type="number" step="1" min="0" value="{{ $p ? $p->ordem : old('ordem', 0) }}"></label>
    </div>
    <label class="lj-campo"><span>Descrição</span><textarea name="descricao" class="campo" rows="2" maxlength="2000">{{ $p ? $p->descricao : old('descricao') }}</textarea></label>
    <div class="flex flex-wrap items-center gap-4 text-sm">
        <div class="lj-campo"><span>Foto (JPG, PNG ou WebP, até 3 MB)</span><input type="file" name="imagem" accept="image/jpeg,image/png,image/webp" class="text-sm"></div>
        @if ($p && $p->imagem) <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="tirar_imagem" value="1"> tirar a foto atual</label> @endif
        <label class="flex items-center gap-2"><input type="checkbox" name="ativo" value="1" @checked($p ? $p->ativo : true)> Aparece na loja</label>
    </div>
    <button type="submit" class="btn !py-1.5">{{ $p ? 'Salvar produto' : 'Adicionar produto' }}</button>
</form>
