@extends('layouts.app')

@section('titulo', 'Promoções relâmpago')
@section('subtitulo', 'Preço baixo por um prazo: aparece com contagem regressiva nos planos e o Pix sai com esse valor.')

@section('conteudo')
@php
    $hora = fn ($d) => $d?->copy()->tz($fuso)->format('d/m/Y H:i');
    $agora = now()->tz($fuso);
@endphp
<div class="space-y-4">
    @if ($planos->isEmpty())
        <p class="card p-4 text-sm">Nenhum plano pago está à venda agora.</p>
    @else
        <form method="POST" action="{{ route('promocoes.admin.criar') }}" class="card p-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @csrf
            <h2 class="titulo text-lg sm:col-span-2 lg:col-span-3">Nova promoção</h2>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Plano</span>
                <select name="plano_slug" class="campo">
                    @foreach ($planos as $p)
                        <option value="{{ $p->slug }}" @selected(old('plano_slug') === $p->slug)>{{ $p->name }} · R$ {{ number_format((float) $p->price, 2, ',', '.') }}</option>
                    @endforeach
                </select>
                @error('plano_slug')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Como dar o desconto</span>
                <select name="tipo" class="campo">
                    <option value="preco" @selected(old('tipo', 'preco') === 'preco')>Preço promocional (R$)</option>
                    <option value="desconto" @selected(old('tipo') === 'desconto')>Desconto (%)</option>
                </select>
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Valor (R$ ou %)</span>
                <input name="valor" class="campo" inputmode="decimal" type="number" step="0.01" min="0.01" required value="{{ old('valor') }}" placeholder="Ex.: 4,90 ou 70">
                @error('valor')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Começa (vazio = agora)</span>
                <input name="comeca_em" type="datetime-local" class="campo" value="{{ old('comeca_em') }}">
                @error('comeca_em')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Termina (hora de Brasília)</span>
                <input name="termina_em" type="datetime-local" class="campo" required value="{{ old('termina_em', $agora->copy()->addDay()->format('Y-m-d\TH:i')) }}">
                @error('termina_em')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Chamada (opcional)</span>
                <input name="titulo" class="campo" maxlength="80" value="{{ old('titulo') }}" placeholder="Ex.: Só hoje!">
            </label>
            <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn">Criar promoção</button>
                <span class="texto-2 text-xs">O preço vale para o Pix gerado durante a promoção. A renovação depois do prazo volta ao preço normal.</span>
            </div>
        </form>
    @endif

    @forelse ($lista as $p)
        @php
            $situacao = $p->encerrada_em ? 'Encerrada' : ($p->termina_em?->isPast() ? 'Terminou' : ($p->comeca_em?->isFuture() ? 'Agendada' : 'Valendo agora'));
        @endphp
        <article class="card p-4 flex flex-wrap items-center gap-4">
            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tag">{{ $situacao }}</span>
                    <b>R$ {{ number_format((float) $p->preco, 2, ',', '.') }}</b>
                    <span class="texto-2 text-sm">plano {{ \App\Support\Planos::nome($p->plano_slug) }}</span>
                    @if ($p->titulo)<span class="text-sm">· {{ $p->titulo }}</span>@endif
                </div>
                <p class="text-xs texto-2">
                    de {{ $hora($p->comeca_em) }} até {{ $hora($p->termina_em) }}
                    @if ($p->encerrada_em) · encerrada em {{ $hora($p->encerrada_em) }} @endif
                </p>
            </div>
            @if (! $p->encerrada_em && $p->termina_em?->isFuture())
                <form method="POST" action="{{ route('promocoes.admin.encerrar', $p) }}" onsubmit="return confirm('Encerrar esta promoção agora?')">
                    @csrf
                    <button type="submit" class="btn-sec !py-1">Encerrar agora</button>
                </form>
            @endif
        </article>
    @empty
        <p class="texto-2">Nenhuma promoção criada ainda.</p>
    @endforelse
</div>
@endsection
