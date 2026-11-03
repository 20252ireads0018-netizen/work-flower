{{-- Animação para e-commerce e vendedores (15 s): marketplaces, margem real, caixa e metas, e a escassez dos
     30 primeiros com Premium grátis. Moldura e controles em anuncios/base; cena final em anuncios/_vagas. --}}
@extends('anuncios.base')
@php
    [$prim, $onPrim] = \App\Support\Anuncios::cores();
@endphp

@section('titulo', 'Vende muito e o dinheiro some?')
@section('duracao', '15')
@section('arquivo', 'work-flower-vendedores')

@section('cenas')
    {{-- 1 · Gancho --}}
    <section class="cena" data-de="0" data-ate="2.3" style="background: radial-gradient(80% 50% at 50% 60%, rgba(255,60,60,.22), transparent 70%)">
        <p class="t g" data-a="sobe">Vende todo dia…</p>
        <p class="t g" data-a="sobe" data-em=".2">e o dinheiro</p>
        <p class="t x verm" data-a="zoom sacode" data-em=".7" data-dur=".25">some?</p>
        <p class="valor verm" data-a="pop" data-em="1.2" data-dur=".3" style="margin-top:30px"><span data-num="1240" data-fmt="brl0" data-em="1.2" data-dur=".7">R$ 0</span> perdidos</p>
        <div class="rodape">*valor ilustrativo</div>
    </section>

    {{-- 2 · Marketplaces e margem real --}}
    <section class="cena" data-de="2.3" data-ate="5.8">
        <span class="tag" data-a="sobe">MARKETPLACES</span>
        <p class="t m" data-a="sobe" data-em=".08">Veja o <mark>lucro de verdade</mark></p>
        <p class="t m" data-a="sobe" data-em=".18">de cada anúncio.</p>
        <div style="display:flex;gap:20px;margin-top:36px">
            <span class="logo-mkt" data-a="pop" data-em=".3" style="background:#ffe600;color:#2d3277">Mercado Livre</span>
            <span class="logo-mkt" data-a="pop" data-em=".4" style="background:#ee4d2d;color:#fff">Shopee</span>
            <span class="logo-mkt" data-a="pop" data-em=".5" style="background:#232f3e;color:#ff9900">Amazon</span>
        </div>
        <div style="width:100%;margin-top:26px;text-align:left">
            <div class="prod" data-a="dir" data-em=".75"><span>Fone Bluetooth<small>comissão + frete já descontados</small></span><b class="verde">+32%</b></div>
            <div class="prod ruim" data-a="dir sacode" data-em=".95"><span>Carregador turbo<small>vendendo no prejuízo</small></span><b class="verm">−R$ 3,20</b></div>
            <div class="prod bom" data-a="pop" data-em="2.3" data-dur=".3"><span>Carregador · preço corrigido<small>agora dá lucro</small></span><b class="verde">+R$ 18,40</b></div>
        </div>
        <div class="rodape">*valores ilustrativos</div>
    </section>

    {{-- 3 · Caixa e gastos --}}
    <section class="cena" data-de="5.8" data-ate="8.8">
        <span class="tag" data-a="sobe">CARTEIRA</span>
        <p class="t m" data-a="sobe" data-em=".08">Cada real no lugar:</p>
        <p class="t m" data-a="sobe" data-em=".18"><mark>nada escapa.</mark></p>
        <div class="card" data-a="sobe" data-em=".35" style="width:100%;margin-top:40px">
            <div class="rot">Caixa do mês</div>
            <div class="valor verde" data-num="8930.5" data-fmt="brl" data-em=".4" data-dur="1.1">R$ 0,00</div>
            <div class="grafico">
                @foreach ([30, 46, 41, 62, 58, 80] as $i => $h)<span data-a="barra" data-v="{{ $h }}" data-prop="height" data-em="{{ .6 + $i * .07 }}" data-dur=".4"></span>@endforeach
            </div>
        </div>
        <div class="card alerta" data-a="pop" data-em="1.7" data-dur=".3" style="width:100%;margin-top:30px;position:relative">
            <div class="linha"><span style="font-size:40px;font-weight:800">Taxas e assinaturas esquecidas</span><span class="verm" style="font:800 50px 'Bricolage Grotesque',sans-serif">−R$ 214</span></div>
            <span class="risco" data-a="barra" data-v="90" data-em="2.3" data-dur=".3"></span>
            <span class="ok-carimbo" data-a="pop" data-em="2.45" data-dur=".25" data-r="-8">CORTADO ✓</span>
        </div>
        <div class="rodape">*valores ilustrativos</div>
    </section>

    {{-- 4 · Quem controla lucra --}}
    <section class="cena" data-de="8.8" data-ate="11.2">
        <div class="fundo-cor" data-a="apaga" data-dur=".06" data-sai="1.05" style="background:#3a0d10"></div>
        <div class="fundo-cor" data-a="apaga" data-em="1.1" data-dur=".06" style="background:#0b3d22"></div>
        <p class="t x" data-a="zoom" data-dur=".22" data-sai="1.02" style="position:absolute;font-size:150px;line-height:1.05">Quem não<br>controla,<br><span class="verm">perde.</span></p>
        <p class="t x" data-a="zoom sacode" data-em="1.1" data-dur=".22" style="position:absolute;font-size:150px;line-height:1.05">Quem<br>controla,<br><span class="verde">lucra. 💰</span></p>
    </section>

    @include('anuncios._vagas', ['de' => 11.2, 'ate' => 15, 'chamada' => 'Pare de perder dinheiro.'])
@endsection
