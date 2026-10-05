@extends('layouts.app')

@section('titulo', 'Criar conta')

@section('conteudo')
<div id="auth-grid" class="grid min-h-screen overflow-hidden lg:grid-cols-2">
    <section id="painel-azul" class="cab-bg painel-tech flex flex-col justify-between gap-10 p-8 sm:p-12 lg:order-2">
        @include('partials.marca', ['texto' => 'text-2xl'])

        <div class="text-center">
            <div class="flex justify-center">
                @include('partials.flor', ['class' => 'w-56 sm:w-64 lg:w-[22rem] max-w-full h-auto'])
            </div>
            <h1 class="titulo text-3xl sm:text-4xl leading-tight max-w-md mx-auto mt-8">Organize-se. Produza mais.</h1>
            <p class="mt-4 max-w-sm mx-auto opacity-80">Unifique. Customize. Produza.</p>
        </div>

        <p class="text-sm opacity-70">Seu corpo, Mente, Carteira e fluxos em um lugar.</p>
    </section>

    <section id="painel-form" class="flex items-center justify-center p-6 sm:p-12 lg:order-1">
        <form method="POST" action="{{ route('registro') }}" class="w-full max-w-sm space-y-5">
            @csrf
            <h2 class="titulo text-3xl">Criar conta</h2>

            <div>
                <label for="name" class="rotulo">Nome</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" class="campo" autocomplete="name" required autofocus>
                @error('name') <p class="erro">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="rotulo">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="campo" autocomplete="email" required>
                @error('email') <p class="erro">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="rotulo">Senha (mínimo 8 caracteres)</label>
                <input id="password" type="password" name="password" class="campo" autocomplete="new-password" required>
                @error('password') <p class="erro">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="rotulo">Repita a senha</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="campo" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn w-full">Criar conta</button>

            <p class="text-sm texto-2 text-center">
                Já tem conta?
                <a href="{{ route('login') }}" data-troca class="link-prim">Entrar</a>
            </p>
        </form>
    </section>
</div>

@include('auth._troca')
@endsection