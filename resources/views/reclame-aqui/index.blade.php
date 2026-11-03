@extends('layouts.app')

@section('titulo', 'Reclame aqui')
@section('subtitulo', 'Algo deu errado, faltou alguma coisa ou tem uma ideia? Conte para a gente. A resposta aparece nesta página.')

@section('conteudo')
@php use App\Models\Reclamacao; @endphp
<div class="grid lg:grid-cols-5 gap-6 items-start">
    <form method="POST" action="{{ route('reclame.enviar') }}" class="card p-5 space-y-4 lg:col-span-2">
        @csrf
        <input type="hidden" name="pagina" value="{{ old('pagina', $pagina) }}">
        @if (session('ok'))
            <p class="text-sm p-3 rounded-lg" style="background: var(--prim-suave); color: var(--prim-forte)">{{ session('ok') }}</p>
        @endif

        <div>
            <label class="rotulo">Sobre o quê?</label>
            <div class="flex flex-wrap gap-2">
                @foreach (Reclamacao::TIPOS as $v => $nome)
                    <label class="btn-sec !py-1 cursor-pointer has-[:checked]:!bg-[var(--prim)] has-[:checked]:!text-[var(--on-prim)]">
                        <input type="radio" name="tipo" value="{{ $v }}" class="sr-only" @checked(old('tipo', 'reclamacao') === $v)> {{ $nome }}
                    </label>
                @endforeach
            </div>
        </div>
        <div>
            <label class="rotulo" for="rq-assunto">Assunto</label>
            <input id="rq-assunto" name="assunto" class="campo" maxlength="150" required value="{{ old('assunto') }}" placeholder="Ex.: o PDF do documento saiu cortado">
        </div>
        <div>
            <label class="rotulo" for="rq-msg">Conte o que aconteceu</label>
            <textarea id="rq-msg" name="mensagem" class="campo" rows="7" maxlength="5000" required placeholder="O que você fez, o que esperava e o que aconteceu.">{{ old('mensagem') }}</textarea>
        </div>
        @if ($errors->any())
            <p class="erro">{{ $errors->first() }}</p>
        @endif
        <button type="submit" class="btn w-full">Enviar</button>
        @if ($pagina)
            <p class="text-xs texto-2">Vamos anexar a página de onde você veio, para achar o problema mais rápido.</p>
        @endif
    </form>

    <section class="lg:col-span-3 space-y-3">
        <h2 class="titulo text-xl">Suas mensagens</h2>
        @forelse ($minhas as $r)
            <article class="card p-4 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tag">{{ Reclamacao::TIPOS[$r->tipo] ?? $r->tipo }}</span>
                    <b class="flex-1 min-w-0">{{ $r->assunto }}</b>
                    <span class="text-xs px-2 py-0.5 rounded-full" style="background: {{ $r->status === 'aberta' ? 'var(--linha)' : 'var(--prim-suave)' }}; color: {{ $r->status === 'aberta' ? 'inherit' : 'var(--prim-forte)' }}">{{ Reclamacao::STATUS[$r->status] ?? $r->status }}</span>
                </div>
                <p class="text-sm texto-2 whitespace-pre-line">{{ $r->mensagem }}</p>
                <p class="text-xs texto-2">Enviada em {{ $r->created_at->format('d/m/Y H:i') }}</p>
                @if ($r->resposta)
                    <div class="p-3 rounded-lg text-sm" style="background: var(--prim-suave)">
                        <b style="color: var(--prim-forte)">Resposta da equipe</b>
                        <p class="whitespace-pre-line mt-1">{{ $r->resposta }}</p>
                        <p class="text-xs texto-2 mt-1">{{ $r->respondida_em?->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
            </article>
        @empty
            <p class="texto-2">Você ainda não mandou nenhuma mensagem.</p>
        @endforelse
    </section>
</div>
@endsection
