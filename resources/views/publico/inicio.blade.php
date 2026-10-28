{{-- Página inicial pública do Work Flower (para visitantes sem login) --}}
@extends('layouts.app')

@section('titulo', 'Organize seu trabalho e sua vida em um só lugar')

@section('conteudo')
@include('publico._topo')

{{-- ===== Abertura ===== --}}
<section class="cab-bg painel-tech">
    <div class="max-w-6xl mx-auto px-4 sm:px-8 py-16 sm:py-24 grid gap-12 lg:grid-cols-2 items-center">
        <div>
            <h1 class="titulo text-4xl sm:text-5xl leading-tight">Seu trabalho e sua vida, organizados em um só lugar</h1>
            <p class="mt-5 text-lg opacity-80 max-w-xl">
                O Work Flower reúne, em um espaço bonito e personalizável, tudo o que hoje vive espalhado
                em abas do navegador, aplicativos e anotações soltas.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                <a href="{{ route('registro') }}" class="btn">Criar conta grátis</a>
                <a href="{{ route('login') }}" class="btn-cab">Já tenho conta</a>
            </div>
        </div>

        <div class="flex justify-center">
            @include('partials.flor', ['class' => 'w-56 sm:w-72 lg:w-[22rem] max-w-full h-auto'])
        </div>
    </div>
</section>

<div class="max-w-6xl mx-auto px-4 sm:px-8">

    {{-- ===== O que é ===== --}}
    <section class="py-14 max-w-3xl">
        <h2 class="titulo text-3xl">O que é o Work Flower</h2>
        <div class="mt-5 space-y-4 leading-relaxed texto-2">
            <p>
                O Work Flower é um serviço de organização de trabalho e vida, feito para quem quer um espaço
                que unifique as abas do seu aparelho de forma organizada e bonita. Em vez de pular entre
                listas de tarefas, notas, planilhas e documentos, você mantém tudo em um único lugar.
            </p>
            <p>
                Cada pessoa organiza a vida de um jeito. Por isso, o Work Flower é construído para ser seu:
                você escolhe as cores, o fundo, o formato dos blocos e como cada área se apresenta.
            </p>
        </div>
    </section>

    {{-- ===== Áreas ===== --}}
    <section class="pb-14">
        <h2 class="titulo text-3xl">Quatro áreas para a sua rotina</h2>
        <p class="mt-3 texto-2 max-w-2xl">Cada área tem o seu próprio espaço e a mesma aparência, para você se movimentar sem esforço.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="card p-5">
                <span class="icone mb-4" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z"/></svg>
                </span>
                <h3 class="titulo text-xl">Corpo</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Tarefas, treinos, dieta, água, calorias, cargas e metas: cuide da rotina que sustenta o seu dia.</p>
            </article>

            <article class="card p-5">
                <span class="icone mb-4" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 5h9a4 4 0 014 4v10H9a4 4 0 01-4-4z"/><path d="M9 9h5M9 13h5"/></svg>
                </span>
                <h3 class="titulo text-xl">Mente</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Livros em PDF, estudos, anotações e mapas mentais para pensar e aprender com clareza.</p>
            </article>

            <article class="card p-5">
                <span class="icone mb-4" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h14a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2z"/><path d="M4 7l11-3v3"/><circle cx="16" cy="13.5" r="1"/></svg>
                </span>
                <h3 class="titulo text-xl">Carteira</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Um espaço para acompanhar o seu dinheiro e as suas metas financeiras.</p>
            </article>

            <article class="card p-5">
                <span class="icone mb-4" aria-hidden="true">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>
                </span>
                <h3 class="titulo text-xl">Diverso</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Escrita, tabelas, fluxogramas e blocos livres para tudo o que não cabe nas outras áreas.</p>
            </article>
        </div>
    </section>

    {{-- ===== Personalização ===== --}}
    <section class="pb-14">
        <h2 class="titulo text-3xl">Feito para ficar do seu jeito</h2>

        <div class="mt-8 grid gap-4 md:grid-cols-3">
            <article class="card p-5">
                <h3 class="titulo text-xl">Cores e fundo</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Escolha um tema pronto ou ajuste as cores. O fundo pode ser uma cor, uma imagem ou até um GIF animado.</p>
            </article>
            <article class="card p-5">
                <h3 class="titulo text-xl">Blocos livres</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Organize o conteúdo em blocos que você arrasta e redimensiona, com tabelas, textos, imagens e mapas mentais.</p>
            </article>
            <article class="card p-5">
                <h3 class="titulo text-xl">Biblioteca e backup</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Salve blocos na biblioteca para reutilizar em outras áreas e exporte tudo em um arquivo para guardar ou levar para outra conta.</p>
            </article>
        </div>
    </section>

    {{-- ===== Como começar ===== --}}
    <section class="pb-14">
        <h2 class="titulo text-3xl">Como começar</h2>

        <ol class="mt-8 grid gap-4 md:grid-cols-3">
            <li class="card p-5">
                <span class="titulo text-3xl" style="color: var(--prim-forte)">1</span>
                <h3 class="titulo text-xl mt-2">Crie a sua conta</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Leva menos de um minuto e é grátis. Você só precisa de um e-mail e uma senha.</p>
            </li>
            <li class="card p-5">
                <span class="titulo text-3xl" style="color: var(--prim-forte)">2</span>
                <h3 class="titulo text-xl mt-2">Monte as suas áreas</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Comece pela área que mais precisa de ordem e adicione os blocos que fazem sentido para você.</p>
            </li>
            <li class="card p-5">
                <span class="titulo text-3xl" style="color: var(--prim-forte)">3</span>
                <h3 class="titulo text-xl mt-2">Deixe com a sua cara</h3>
                <p class="mt-2 text-sm texto-2 leading-relaxed">Troque as cores, escolha um fundo e salve o que quiser reutilizar na biblioteca.</p>
            </li>
        </ol>
    </section>

    {{-- ===== Perguntas frequentes ===== --}}
    <section class="pb-14 max-w-3xl">
        <h2 class="titulo text-3xl">Perguntas frequentes</h2>

        <div class="mt-8 space-y-3">
            <details class="card p-4">
                <summary class="cursor-pointer font-medium">O Work Flower é gratuito?</summary>
                <p class="mt-3 text-sm texto-2 leading-relaxed">Sim, você pode começar sem pagar nada. Também existe o plano Pró, com mais recursos para quem precisa de mais espaço.</p>
            </details>
            <details class="card p-4">
                <summary class="cursor-pointer font-medium">Preciso instalar algum programa?</summary>
                <p class="mt-3 text-sm texto-2 leading-relaxed">Não. O Work Flower funciona direto no navegador, no computador ou no celular.</p>
            </details>
            <details class="card p-4">
                <summary class="cursor-pointer font-medium">Posso levar os meus dados comigo?</summary>
                <p class="mt-3 text-sm texto-2 leading-relaxed">Sim. Você exporta as suas áreas em um único arquivo e pode importá-las depois, inclusive em outra conta.</p>
            </details>
            <details class="card p-4">
                <summary class="cursor-pointer font-medium">Meus dados ficam privados?</summary>
                <p class="mt-3 text-sm texto-2 leading-relaxed">
                    Cada conta acessa apenas o próprio conteúdo, e as senhas são guardadas de forma criptografada.
                    Para saber mais, leia a
                    <a href="{{ \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade') }}" class="link-prim">Política de Privacidade</a>.
                </p>
            </details>
        </div>
    </section>

    {{-- ===== Chamada final ===== --}}
    <section class="pb-16">
        <div class="card p-8 sm:p-10 text-center">
            <h2 class="titulo text-3xl">Pronto para organizar o seu fluxo?</h2>
            <p class="mt-3 texto-2 max-w-xl mx-auto">Reúna as suas abas em um só lugar e comece hoje, sem custo.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('registro') }}" class="btn">Criar conta grátis</a>
                <a href="{{ route('login') }}" class="btn-sec">Entrar</a>
            </div>
        </div>
    </section>
</div>
@endsection

