{{-- Animação para quem estuda (15 s): mapas mentais, revisão, leitura com marcação e anotações, e a escassez
     dos 30 primeiros com Premium grátis. Moldura e controles em anuncios/base; cena final em anuncios/_vagas. --}}
@extends('anuncios.base')
@php
    [$prim, $onPrim] = \App\Support\Anuncios::cores();
    // mapa mental: [texto, x, y] em volta do centro (540, 470) do quadro de 940×740
    $ramos = [['Fórmulas', 180, 140], ['Resumo', 760, 150], ['Exercícios', 150, 560], ['Prova dia 12', 770, 580], ['Dúvidas', 470, 690]];
@endphp

@section('titulo', 'Quem não se organiza não passa')
@section('duracao', '15')
@section('arquivo', 'work-flower-estudantes')

@section('cenas')
    {{-- 1 · Gancho --}}
    <section class="cena" data-de="0" data-ate="2.2" style="background: radial-gradient(80% 50% at 50% 60%, rgba(255,60,60,.22), transparent 70%)">
        <p class="t g" data-a="sobe">Quem não</p>
        <p class="t g" data-a="sobe" data-em=".15">se organiza</p>
        <p class="t x verm" data-a="zoom sacode" data-em=".75" data-dur=".25" style="font-size:150px;line-height:1.02">não passa<br>em nada.</p>
    </section>

    {{-- 2 · Mapa mental --}}
    <section class="cena" data-de="2.2" data-ate="5">
        <span class="tag" data-a="sobe">MAPAS MENTAIS</span>
        <p class="t m" data-a="sobe" data-em=".08">A matéria inteira</p>
        <p class="t m" data-a="sobe" data-em=".18">numa <mark>tela só.</mark></p>
        <div class="card" data-a="sobe" data-em=".3" style="width:100%;height:740px;margin-top:40px;position:relative;padding:0">
            <svg viewBox="0 0 940 740" width="100%" height="100%" style="position:absolute;inset:0">
                @foreach ($ramos as $i => [$t, $x, $y])
                    <path d="M470 370 Q{{ (470 + $x) / 2 }} {{ $y < 370 ? 370 : 370 }} {{ $x }} {{ $y }}" fill="none" stroke="{{ $prim }}" stroke-width="6" stroke-linecap="round" pathLength="1" stroke-dasharray="1" data-a="anel" data-v="100" data-em="{{ .55 + $i * .14 }}" data-dur=".35"/>
                @endforeach
            </svg>
            <span class="chip" data-a="pop" data-em=".45" style="left:50%;top:50%;margin:-50px 0 0 -190px;width:380px;text-align:center;background:{{ $prim }};color:{{ $onPrim }};border:0">Cálculo I</span>
            @foreach ($ramos as $i => [$t, $x, $y])
                <span class="chip" data-a="pop" data-em="{{ .8 + $i * .14 }}" style="left:{{ round($x / 940 * 100, 1) }}%;top:{{ round($y / 740 * 100, 1) }}%;margin:-42px 0 0 -150px;width:300px;text-align:center;font-size:38px">{{ $t }}</span>
            @endforeach
        </div>
    </section>

    {{-- 3 · Revisão na hora certa --}}
    <section class="cena" data-de="5" data-ate="7.6">
        <span class="tag" data-a="sobe">REVISÃO</span>
        <p class="t m" data-a="sobe" data-em=".08">Revise na hora certa,</p>
        <p class="t m" data-a="sobe" data-em=".18"><mark>antes de esquecer.</mark></p>
        <div style="width:100%;margin-top:40px">
            <div class="prod bom" data-a="dir" data-em=".35"><span>Limites<small>revisar hoje</small></span><b class="verde">✓</b></div>
            <div class="prod" data-a="dir" data-em=".5"><span>Derivadas<small>em 3 dias</small></span><b class="ouro">⏳</b></div>
            <div class="prod" data-a="dir" data-em=".65"><span>Integrais<small>em 7 dias</small></span><b class="ouro">⏳</b></div>
        </div>
        <div class="card" data-a="pop" data-em="1.5" data-dur=".3" style="width:100%;margin-top:30px">
            <div class="rot">Tópicos em dia</div>
            <div class="trilho"><span data-a="barra" data-v="100" data-em="1.6" data-dur=".8"></span></div>
        </div>
    </section>

    {{-- 4 · Leitura com marcação e anotações --}}
    <section class="cena" data-de="7.6" data-ate="10.2">
        <span class="tag" data-a="sobe">LEITURA</span>
        <p class="t m" data-a="sobe" data-em=".08">Leia, marque e</p>
        <p class="t m" data-a="sobe" data-em=".18"><mark>anote</mark> no mesmo lugar.</p>
        <div style="display:flex;gap:26px;width:100%;margin-top:40px;align-items:flex-start">
            <div class="card" data-a="esq" data-em=".3" style="flex:1.4;background:#f5f1e6;color:#2b2b2b;position:relative;padding:40px 36px">
                <span data-a="pop" data-em="1.3" data-dur=".25" style="position:absolute;right:26px;top:-18px;background:#ff4d4d;color:#fff;font:800 30px 'DM Sans',sans-serif;padding:10px 18px;border-radius:10px">🔖 pág. 47</span>
                @foreach ([100, 92, 96, 70, 98, 88] as $i => $w)
                    <div style="height:20px;border-radius:10px;background:#c9c2b0;margin:20px 0;width:{{ $w }}%;position:relative;overflow:hidden">
                        @if (in_array($i, [2, 3], true))<span data-a="barra" data-v="100" data-em="{{ .7 + ($i - 2) * .25 }}" data-dur=".35" style="position:absolute;left:0;top:0;bottom:0;background:#ffd23f"></span>@endif
                    </div>
                @endforeach
            </div>
            <div class="card" data-a="dir" data-em="1" style="flex:1;font-size:36px;font-weight:700;line-height:1.3">
                <div class="rot">✍️ Anotação</div>
                <div style="margin-top:12px">Isso <span class="ouro">cai na prova!</span></div>
            </div>
        </div>
    </section>

    {{-- 5 · A interface organizada --}}
    <section class="cena" data-de="10.2" data-ate="11.4">
        <p class="t m" data-a="sobe">Estudos, tarefas e</p>
        <p class="t m" data-a="sobe" data-em=".1">rotina <mark>organizados.</mark></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;width:100%;margin-top:44px">
            @foreach ([['🧠', 'Mente', 'estudos e leitura'], ['💪', 'Corpo', 'treino e sono'], ['💰', 'Carteira', 'gastos e metas'], ['🗂️', 'Diverso', 'projetos e pastas']] as $i => [$ic, $n, $d])
                <div class="card" data-a="pop" data-em="{{ .3 + $i * .1 }}" style="text-align:left"><div style="font-size:56px">{{ $ic }}</div><div style="font-size:44px;font-weight:800">{{ $n }}</div><div class="rot">{{ $d }}</div></div>
            @endforeach
        </div>
    </section>

    @include('anuncios._vagas', ['de' => 11.4, 'ate' => 15, 'chamada' => 'Comece a estudar do jeito certo.'])
@endsection
