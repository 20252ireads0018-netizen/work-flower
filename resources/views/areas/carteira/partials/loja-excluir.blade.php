{{-- Excluir a loja (Negócios › Loja e marketplaces e cabeçalho da página da loja). Pede o nome da loja digitado.
     $loja: a loja; $abertos: pedidos esperando pagamento (com eles a exclusão é recusada no servidor também). --}}
@php $idDialogo = 'lj-excluir-' . $loja->id; @endphp
<button type="button" class="btn-sec !py-1.5 !px-3 text-sm lj-perigo" onclick="document.getElementById('{{ $idDialogo }}').showModal()">Excluir loja</button>
<dialog id="{{ $idDialogo }}" class="lj-dialogo" aria-labelledby="{{ $idDialogo }}-t" @if ($errors->has('confirmar_nome')) data-abrir @endif>
    <form method="POST" action="{{ route('carteira.loja.excluir') }}" class="space-y-3">
        @csrf
        @method('DELETE')
        <h2 id="{{ $idDialogo }}-t" class="titulo text-lg">Excluir a loja “{{ $loja->nome }}”?</h2>
        <ul class="text-sm list-disc pl-5 space-y-1">
            <li>A página pública <b>{{ route('loja.ver', $loja->slug) }}</b> sai do ar e o endereço fica livre.</li>
            <li>Os cupons, o Mercado Pago conectado, a logo e o banner são apagados.</li>
            <li><b>Ficam guardados:</b> o histórico de pedidos, o seu saldo e os resgates. Os produtos continuam em Negócios › Produtos.</li>
            <li>Se criar a loja de novo, o histórico volta junto.</li>
        </ul>
        @if ($abertos > 0)
            <p class="text-sm" role="alert" style="color:#ef4444">Há {{ $abertos }} {{ $abertos === 1 ? 'pedido esperando' : 'pedidos esperando' }} pagamento. Conclua ou cancele em Pedidos antes de excluir.</p>
        @endif
        <label class="block text-sm">
            <span class="texto-2 text-xs block mb-1">Para confirmar, digite o nome da loja: <b>{{ $loja->nome }}</b></span>
            <input name="confirmar_nome" class="campo" maxlength="80" autocomplete="off" required data-nome="{{ $loja->nome }}"
                   oninput="this.form.querySelector('[data-confirmar]').disabled = this.value.trim().toLowerCase() !== this.dataset.nome.trim().toLowerCase()">
        </label>
        @error('confirmar_nome') <p class="text-sm" role="alert" style="color:#ef4444">{{ $message }}</p> @enderror
        <div class="flex flex-wrap gap-2 justify-end">
            <button type="button" class="btn-sec" onclick="this.closest('dialog').close()">Voltar</button>
            <button type="submit" class="btn lj-btn-perigo" data-confirmar disabled>Excluir a loja</button>
        </div>
    </form>
</dialog>
@once
    <style>
        .lj-perigo { color: #ef4444 !important; }
        .lj-btn-perigo { background: #dc2626 !important; color: #fff !important; border-color: #dc2626 !important; }
        .lj-btn-perigo:disabled { opacity: .45; cursor: not-allowed; }
        .lj-dialogo { width: min(34rem, calc(100vw - 2rem)); padding: 1.25rem; border-radius: .9rem; border: 1px solid var(--linha); background: var(--superficie); color: var(--tinta); }
        .lj-dialogo::backdrop { background: rgb(0 0 0 / .55); }
    </style>
    <script>
        // voltou do servidor com erro na confirmação: reabre a caixa para mostrar a mensagem
        document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('dialog[data-abrir]').forEach(d => d.showModal()));
    </script>
@endonce
