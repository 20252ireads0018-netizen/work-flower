@extends('layouts.app')

@section('titulo', 'Animações para anúncios')
@section('subtitulo', 'Vídeos curtos verticais (9:16) prontos para Reels, TikTok e Shorts. Abra, confira e baixe em MP4.')

@section('conteudo')
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach (\App\Support\Anuncios::LISTA as $slug => [$titulo, $seg, $desc])
        <a href="{{ route('anuncios.ver', $slug) }}" class="card p-4 flex flex-col gap-2 hover:border-[var(--prim)]">
            <div class="rounded-lg grid place-items-center text-4xl" style="aspect-ratio: 9 / 12; background: linear-gradient(160deg, var(--prim-suave), var(--superficie))">🎬</div>
            <b>{{ $titulo }}</b>
            <span class="text-xs texto-2">{{ $seg }} s · 1080×1920</span>
            <span class="text-sm texto-2">{{ $desc }}</span>
        </a>
    @endforeach
</div>
@endsection
