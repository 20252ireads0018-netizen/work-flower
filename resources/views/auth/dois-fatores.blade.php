@extends('layouts.app')

@section('titulo', 'Verificação em duas etapas')

@section('conteudo')
<div class="min-h-screen flex items-center justify-center p-6 sm:p-12">
    <form method="POST" action="{{ route('login.dois-fatores') }}" class="card w-full max-w-sm p-6 space-y-5" x-data="{ recuperacao: false }">
        @csrf
        <div>
            <h2 class="titulo text-2xl">Verificação em duas etapas</h2>
            <p class="text-sm texto-2 mt-1" x-show="!recuperacao">Abra o app autenticador (Google Authenticator, Microsoft Authenticator, Authy…) e digite o código de 6 dígitos do Work Flower.</p>
            <p class="text-sm texto-2 mt-1" x-show="recuperacao" x-cloak>Digite um dos códigos de recuperação que você guardou ao ativar (cada um vale uma vez).</p>
        </div>

        <div>
            <label for="codigo" class="rotulo" x-text="recuperacao ? 'Código de recuperação' : 'Código do app'">Código do app</label>
            <input id="codigo" name="codigo" class="campo tracking-widest text-center text-lg" required autofocus maxlength="40"
                   autocomplete="one-time-code" :inputmode="recuperacao ? 'text' : 'numeric'" inputmode="numeric"
                   :placeholder="recuperacao ? 'ABCDE-FGHIJ' : '123456'" placeholder="123456">
            @error('codigo') <p class="erro">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn w-full">Entrar</button>

        <div class="flex justify-between text-sm">
            <button type="button" class="link-prim" @click="recuperacao = !recuperacao; $nextTick(() => document.getElementById('codigo').focus())"
                    x-text="recuperacao ? 'Usar o código do app' : 'Perdi o celular: usar código de recuperação'">Perdi o celular: usar código de recuperação</button>
        </div>
        <p class="text-sm texto-2 text-center"><a href="{{ route('login') }}" class="link-prim">Voltar ao login</a></p>
    </form>
</div>
@endsection
