{{-- Animação para produtores (15 s): vídeo, música, imagem, código e escrita de graça, o workflow que melhora a
     produção, e a escassez dos 30 primeiros com Premium grátis. Moldura e controles em anuncios/base; cena final em anuncios/_vagas. --}}
@extends('anuncios.base')
@php
    [$prim, $onPrim] = \App\Support\Anuncios::cores();
    $bagunca = [['📁 final_v7_AGORA.mp4', -280, -520, -7], ['🎵 beat_novo(2).wav', 260, -380, 6], ['🖼️ capa-certa.png', -300, -160, 5], ['📝 roteiro?.docx', 290, -40, -6],
                ['💬 cliente no WhatsApp', -240, 160, -4], ['🗂️ 23 abas abertas', 250, 300, 7], ['⌨ site_backup_old', -60, 470, -3]];
@endphp

@section('titulo', 'Quem não se organiza não vende')
@section('duracao', '15')
@section('arquivo', 'work-flower-produtores')

@section('cenas')
    {{-- 1 · Gancho: tudo de graça --}}
    <section class="cena" data-de="0" data-ate="2.4">
        <p class="t m" data-a="sobe">Vídeo, música,</p>
        <p class="t m" data-a="sobe" data-em=".12">imagem, código</p>
        <p class="t m" data-a="sobe" data-em=".24">e escrita…</p>
        <p class="t x verde" data-a="zoom sacode" data-em=".85" data-dur=".25" style="margin-top:30px">DE GRAÇA.</p>
    </section>

    {{-- 2 · As ferramentas --}}
    <section class="cena" data-de="2.4" data-ate="6.2">
        <span class="tag" data-a="sobe">TUDO NUM LUGAR SÓ</span>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;width:100%;margin-top:20px;text-align:left">
            <div class="card" data-a="pop" data-em=".2"><div style="font-size:44px;font-weight:800">🎬 Vídeo</div>
                <div style="display:flex;gap:8px;margin-top:18px">@foreach ([30, 18, 26, 14] as $i => $w)<span data-a="barra" data-v="{{ $w }}" data-em="{{ .45 + $i * .1 }}" data-dur=".3" style="display:block;height:40px;border-radius:8px;background:{{ ['#38bdf8', '#a78bfa', '#38bdf8', '#f472b6'][$i] }};width:0;flex:none"></span>@endforeach</div></div>
            <div class="card" data-a="pop" data-em=".35"><div style="font-size:44px;font-weight:800">🎹 Música</div>
                <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:6px;margin-top:18px">@foreach (range(0, 15) as $i)<span style="height:30px;border-radius:6px;background:rgba(255,255,255,.12);position:relative;overflow:hidden">@if (in_array($i, [0, 2, 4, 6, 9, 11, 13, 15], true))<span data-a="pop" data-em="{{ .6 + ($i % 8) * .08 }}" data-dur=".15" style="position:absolute;inset:0;background:{{ $prim }}"></span>@endif</span>@endforeach</div></div>
            <div class="card" data-a="pop" data-em=".5"><div style="font-size:44px;font-weight:800">🖼️ Imagem</div>
                <div style="position:relative;height:120px;margin-top:14px">@foreach ([0, 1, 2] as $i)<span data-a="pop" data-em="{{ .75 + $i * .12 }}" style="position:absolute;left:{{ $i * 40 }}px;top:{{ $i * 16 }}px;width:200px;height:84px;border-radius:12px;background:{{ ['#fb923c', '#2dd4bf', '#f472b6'][$i] }};opacity:.9;box-shadow:0 6px 16px rgba(0,0,0,.4)"></span>@endforeach</div></div>
            <div class="card" data-a="pop" data-em=".65" style="font-family:ui-monospace,Consolas,monospace"><div style="font-size:44px;font-weight:800;font-family:'DM Sans',sans-serif">⌨ Código</div>
                <div style="font-size:28px;margin-top:14px;line-height:1.5"><span style="color:#f472b6">Route</span>::get(<span class="verde">'/'</span>)<br><span style="color:#38bdf8">return</span> view(<span class="verde">'home'</span>);</div></div>
            <div class="card" data-a="pop" data-em=".8" style="grid-column:1 / -1"><div style="font-size:44px;font-weight:800">✍️ Escrita</div>
                @foreach ([96, 88, 70] as $i => $w)<div style="height:18px;border-radius:9px;background:rgba(255,255,255,.14);margin-top:14px;width:{{ $w }}%;overflow:hidden"><span data-a="barra" data-v="100" data-em="{{ 1 + $i * .15 }}" data-dur=".4" style="display:block;height:100%;width:0;background:#e8edf3"></span></div>@endforeach</div>
        </div>
        <p class="t p" data-a="sobe" data-em="1.6" style="margin-top:34px">Sem pagar <mark>cinco assinaturas.</mark></p>
    </section>

    {{-- 3 · Workflow organizado = produção melhor --}}
    <section class="cena" data-de="6.2" data-ate="9">
        @foreach ($bagunca as $i => [$nome, $dx, $dy, $r])
            <span class="chip" data-a="junta" data-em="{{ .6 + $i * .04 }}" data-dur=".55" data-dx="{{ $dx }}" data-dy="{{ $dy }}" data-r="{{ $r }}" style="font-size:36px;margin:-40px 0 0 -200px">{{ $nome }}</span>
        @endforeach
        <div class="card" data-a="pop" data-em="1.15" data-dur=".35" style="width:100%;text-align:left;position:relative">
            <div style="font-size:46px;font-weight:800">📁 Projeto · Cliente X</div>
            @foreach (['🎬 Vídeo final', '🎵 Trilha', '🖼️ Capa', '📝 Roteiro'] as $i => $t)
                <div class="linha" data-a="dir" data-em="{{ 1.35 + $i * .1 }}" style="font-size:36px;font-weight:700;margin-top:14px"><span>{{ $t }}</span><span class="verde">✓</span></div>
            @endforeach
        </div>
        <p class="t m" data-a="sobe" data-em="1.9" style="margin-top:40px">Workflow organizado</p>
        <p class="t m" data-a="sobe" data-em="2.05">= <mark>produção melhor.</mark></p>
    </section>

    {{-- 4 · Quem não se organiza não vende --}}
    <section class="cena" data-de="9" data-ate="11.4">
        <div class="fundo-cor" data-a="apaga" data-dur=".06" data-sai="1.1" style="background:#0f1720"></div>
        <div class="fundo-cor" data-a="apaga" data-em="1.15" data-dur=".06" style="background:#3a0d10"></div>
        <p class="t x" data-a="zoom" data-dur=".22" data-sai="1.08" style="position:absolute;font-size:150px;line-height:1.05">Bagunça<br>não entrega.</p>
        <p class="t x" data-a="zoom sacode" data-em="1.15" data-dur=".22" style="position:absolute;font-size:140px;line-height:1.05">Quem não<br>se organiza<br><span class="verm">não vende.</span></p>
    </section>

    @include('anuncios._vagas', ['de' => 11.4, 'ate' => 15, 'chamada' => 'Produza mais. Venda mais.'])
@endsection
