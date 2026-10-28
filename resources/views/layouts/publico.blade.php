{{-- Layout das páginas de texto (privacidade, termos, contato).
     Funciona com e sem login: visitantes ganham cabeçalho público e título próprio;
     quem está logado vê a página dentro do layout normal do painel. --}}
@extends('layouts.app')

@section('conteudo')
    @guest
        @include('publico._topo')

        <div class="max-w-3xl mx-auto px-4 sm:px-8 pt-12 pb-4">
            <h1 class="titulo text-3xl sm:text-4xl">@yield('titulo')</h1>
            @hasSection('subtitulo')
                <p class="texto-2 text-sm mt-1.5">@yield('subtitulo')</p>
            @endif
        </div>

        <div class="max-w-3xl mx-auto px-4 sm:px-8 pb-12">
            @yield('texto')
        </div>
    @else
        <div class="max-w-3xl">
            @yield('texto')
        </div>
    @endguest
@endsection

