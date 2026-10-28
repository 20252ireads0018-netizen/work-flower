@extends('layouts.app')

@section('titulo', 'Dados para o pagamento')
@section('subtitulo', 'Falta só um passo para assinar o plano ' . $plano->name . '.')

@section('conteudo')
<div class="max-w-md mx-auto">
    <form method="POST" action="{{ route('assinar.dados.salvar', $plano->slug) }}" class="card p-6 space-y-4" novalidate>
        @csrf

        <div>
            <h2 class="titulo text-xl">CPF ou CNPJ</h2>
            <p class="texto-2 text-sm mt-1">
                O processador de pagamentos exige esse documento para emitir a cobrança.
                Ele fica salvo só na sua conta e você não precisa digitar de novo.
            </p>
        </div>

        <div>
            <label for="cpf_cnpj" class="texto-2 text-sm block mb-1.5">Documento</label>
            <input id="cpf_cnpj" name="cpf_cnpj" type="text" inputmode="numeric" autocomplete="off"
                   maxlength="18" placeholder="000.000.000-00" required
                   value="{{ old('cpf_cnpj', $documento) }}"
                   class="w-full rounded-xl px-4 py-3 text-sm"
                   style="background: transparent; border: 1px solid var(--borda, #d4d4d8); color: inherit;">
            @error('cpf_cnpj')
                <p class="text-sm mt-1.5" role="alert" style="color: #ef4444;">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn flex-1">Continuar para o pagamento</button>
            <a href="{{ route('planos') }}" class="btn-sec">Voltar</a>
        </div>
    </form>
</div>

<script>
    // máscara de CPF (000.000.000-00) e CNPJ (00.000.000/0000-00) enquanto digita
    (function () {
        var campo = document.getElementById('cpf_cnpj');
        if (!campo) return;
        function formatar(v) {
            v = v.replace(/\D/g, '').slice(0, 14);
            if (v.length <= 11) {
                return v.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            return v.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
        }
        campo.addEventListener('input', function () { campo.value = formatar(campo.value); });
        campo.value = formatar(campo.value);
    })();
</script>
@endsection
