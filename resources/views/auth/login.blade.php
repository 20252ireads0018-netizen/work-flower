@extends('layouts.app')

@section('titulo', 'Entrar')

@section('conteudo')
<div id="auth-grid" class="grid min-h-screen overflow-hidden lg:grid-cols-2">
    <section id="painel-azul" class="cab-bg painel-tech flex flex-col justify-between gap-10 p-8 sm:p-12">
        @include('partials.marca', ['texto' => 'text-2xl'])

        <div class="text-center">
            <div class="flex justify-center">
                @include('partials.flor', ['class' => 'w-56 sm:w-64 lg:w-[22rem] max-w-full h-auto'])
            </div>
            <h1 class="titulo text-3xl sm:text-4xl leading-tight max-w-md mx-auto mt-8">Seu fluxo de vida unificado em um lugar</h1>
            <p class="mt-4 max-w-sm mx-auto opacity-80">Unifique. Customize. Produza.</p>
        </div>

        {{-- Mantido vazio para o conteúdo continuar centralizado entre a marca e o rodapé --}}
        <div aria-hidden="true"></div>
    </section>

    <section id="painel-form" class="flex items-center justify-center p-6 sm:p-12">
        <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm space-y-5">
            @csrf
            <h2 class="titulo text-3xl">Entrar</h2>

            <div>
                <label for="email" class="rotulo">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="campo" autocomplete="email" required autofocus>
                @error('email') <p class="erro">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="rotulo">Senha</label>
                <input id="password" type="password" name="password" class="campo" autocomplete="current-password" required>
                @error('password') <p class="erro">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="lembrar" value="1"> Manter conectado neste aparelho
            </label>

            <button type="submit" class="btn w-full">Entrar</button>

            <p class="text-sm texto-2 text-center">
                Ainda não tem conta?
                <a href="{{ route('registro') }}" data-troca class="link-prim">Criar conta</a>
            </p>
        </form>
    </section>
</div>

@include('auth._troca')
@endsection