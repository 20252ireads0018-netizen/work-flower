{{-- Animação "Organiza, controla, lucra" (30 s): da bagunça ao lucro. Moldura e controles em anuncios/base. --}}
@extends('anuncios.base')
@php
    [$prim, $onPrim] = \App\Support\Anuncios::cores();
    $bagunca = [['📊 Planilha', -300, -480, -8], ['📝 Bloco de notas', 250, -570, 6], ['📅 Agenda', -330, -190, 5], ['🏦 App do banco', 290, -270, -7],
                ['💬 WhatsApp', -250, 90, -4], ['📒 Caderno', 300, 30, 8], ['🗂️ 14 abas abertas', -20, 310, -3], ['🟨 Post-it', 40, -760, 4]];
    $confete = [];
    for ($i = 0; $i < 44; $i++) {
        $confete[] = [round(fmod($i * 137.5, 360), 1), 260 + ($i * 53) % 420, ['#22c55e', '#facc15', $prim, '#f472b6', '#38bdf8'][$i % 5], ($i * 71) % 360];
    }
@endphp

@section('titulo', 'Organiza, controla, lucra')
@section('duracao', '30')
@section('arquivo', 'work-flower-organiza-controla-lucra')

@section('cenas')
                {{-- 1 · Gancho --}}
                <section class="cena" data-de="0" data-ate="2.4" style="background: radial-gradient(80% 50% at 50% 60%, rgba(255,60,60,.22), transparent 70%)">
                    <p class="t m" data-a="sobe">Você trabalha</p>
                    <p class="t m" data-a="sobe" data-em=".22">o mês inteiro…</p>
                    <p class="t m" data-a="pop" data-em=".95" style="margin-top:70px">e no fim</p>
                    <p class="t m" data-a="pop" data-em="1.15">não sobra</p>
                    <p class="t x verm" data-a="zoom sacode" data-em="1.35" data-dur=".25">NADA?</p>
                </section>

                {{-- 2 · A bagunça --}}
                <section class="cena" data-de="2.4" data-ate="4.6">
                    @foreach ($bagunca as $i => [$nome, $dx, $dy, $r])
                        <span class="chip" data-a="pop flutua" data-em="{{ .05 + $i * .07 }}" data-dx="{{ $dx }}" data-dy="{{ $dy }}" data-r="{{ $r }}">{{ $nome }}</span>
                    @endforeach
                    <div style="position:absolute;left:60px;right:60px;bottom:200px;z-index:2">
                        <p class="t p" data-a="sobe" data-em=".55">O problema não é você.</p>
                        <p class="t g" data-a="zoom" data-em="1.05" data-dur=".3" style="margin-top:18px">É a <mark>bagunça.</mark></p>
                    </div>
                </section>

                {{-- 3 · Junta tudo --}}
                <section class="cena" data-de="4.6" data-ate="6.4">
                    @foreach ($bagunca as $i => [$nome, $dx, $dy, $r])
                        <span class="chip" data-a="junta" data-em="{{ $i * .04 }}" data-dur=".55" data-dx="{{ $dx }}" data-dy="{{ $dy }}" data-r="{{ $r }}">{{ $nome }}</span>
                    @endforeach
                    <div class="flor" data-a="pop gira" data-em=".45" data-dur=".4" style="width:560px;height:560px;margin-top:-160px">@include('partials.flor', ['class' => ''])</div>
                    <p class="t g" data-a="sobe" data-em=".75" style="margin-top:40px">Junta <mark>tudo</mark></p>
                    <p class="t p" data-a="sobe" data-em=".95" style="margin-top:20px;color:#c9d4e0">num lugar só.</p>
                </section>

                {{-- 4 · Carteira --}}
                <section class="cena" data-de="6.4" data-ate="10">
                    <span class="tag" data-a="sobe">CARTEIRA</span>
                    <p class="t m" data-a="sobe" data-em=".08">Veja pra onde vai</p>
                    <p class="t m" data-a="sobe" data-em=".2"><mark>cada real.</mark></p>
                    <div class="card" data-a="sobe" data-em=".35" style="width:100%;margin-top:56px">
                        <div class="rot">Saldo do mês</div>
                        <div class="valor verde" data-num="12480.9" data-fmt="brl" data-em=".4" data-dur="1.3">R$ 0,00</div>
                        <div class="linha" style="margin-top:14px;font-size:36px;font-weight:700">
                            <span>Entradas <span class="verde" data-num="18200" data-fmt="brl0" data-em=".5" data-dur="1.1">R$ 0</span></span>
                            <span>Saídas <span class="verm" data-num="5719" data-fmt="brl0" data-em=".5" data-dur="1.1">R$ 0</span></span>
                        </div>
                        <div class="grafico">
                            @foreach ([38, 55, 47, 70, 62, 88] as $i => $h)<span data-a="barra" data-v="{{ $h }}" data-prop="height" data-em="{{ .7 + $i * .07 }}" data-dur=".45"></span>@endforeach
                        </div>
                    </div>
                    <div class="card alerta" data-a="pop" data-em="1.9" data-dur=".3" style="width:100%;margin-top:34px;position:relative">
                        <div class="linha"><span style="font-size:40px;font-weight:800">Assinaturas esquecidas</span><span class="verm" style="font:800 54px 'Bricolage Grotesque',sans-serif">−R$ 189/mês</span></div>
                        <span class="risco" data-a="barra" data-v="90" data-em="2.55" data-dur=".3"></span>
                        <span class="ok-carimbo" data-a="pop" data-em="2.7" data-dur=".25" data-r="-8">CORTADO ✓</span>
                    </div>
                    <div class="rodape">*valores ilustrativos</div>
                </section>

                {{-- 5 · Marketplaces --}}
                <section class="cena" data-de="10" data-ate="14">
                    <span class="tag" data-a="sobe">MARKETPLACES</span>
                    <p class="t m" data-a="sobe" data-em=".08">Vende online?</p>
                    <div style="display:flex;gap:20px;margin-top:40px">
                        <span class="logo-mkt" data-a="pop" data-em=".25" style="background:#ffe600;color:#2d3277">Mercado Livre</span>
                        <span class="logo-mkt" data-a="pop" data-em=".35" style="background:#ee4d2d;color:#fff">Shopee</span>
                        <span class="logo-mkt" data-a="pop" data-em=".45" style="background:#232f3e;color:#ff9900">Amazon</span>
                    </div>
                    <div style="width:100%;margin-top:30px;text-align:left">
                        <div class="prod" data-a="dir" data-em=".7"><span>Fone Bluetooth<small>margem real</small></span><b class="verde">+32%</b></div>
                        <div class="prod" data-a="dir" data-em=".82"><span>Capinha de celular<small>margem real</small></span><b class="verde">+18%</b></div>
                        <div class="prod ruim" data-a="dir sacode" data-em=".94"><span>Carregador turbo<small>comissão + frete comem tudo</small></span><b class="verm">−R$ 3,20</b></div>
                        <div class="prod bom" data-a="pop" data-em="2.55" data-dur=".3"><span>Carregador · preço ajustado<small>agora dá lucro</small></span><b class="verde">+R$ 18,40</b></div>
                    </div>
                    <p class="t p" data-a="sobe" data-em="1.5" style="margin-top:46px">Pare de vender no <mark>prejuízo.</mark></p>
                    <div class="rodape">*valores ilustrativos</div>
                </section>

                {{-- 6 · Tempo é dinheiro --}}
                <section class="cena" data-de="14" data-ate="17.2">
                    <p class="t m" data-a="sobe">Menos tempo</p>
                    <p class="t m" data-a="sobe" data-em=".12">organizando.</p>
                    <svg viewBox="-110 -110 220 220" width="330" height="330" data-a="pop" data-em=".3" style="margin-top:50px;overflow:visible">
                        <circle r="100" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="10"/>
                        <circle r="100" fill="none" stroke="{{ $prim }}" stroke-width="10" pathLength="1" stroke-dasharray="1" data-a="anel" data-v="100" data-em=".3" data-dur="1.4" transform="rotate(-90)"/>
                        <line x1="0" y1="0" x2="0" y2="-70" stroke="#fff" stroke-width="10" stroke-linecap="round" data-a="ponteiro" data-vel="900"/>
                        <line x1="0" y1="0" x2="45" y2="0" stroke="#fff" stroke-width="10" stroke-linecap="round" data-a="ponteiro" data-vel="75"/>
                        <circle r="12" fill="#fff"/>
                    </svg>
                    <p class="t x ouro" data-a="zoom" data-em=".7" data-dur=".3" style="margin-top:30px">+6h</p>
                    <p class="t p" data-a="sobe" data-em=".9">livres por semana</p>
                    <p class="txt" data-a="sobe" data-em="1.5" style="margin-top:46px">6h × 4 semanas × R$ 50/h</p>
                    <p class="t g verde" data-a="pop" data-em="1.85" data-dur=".3" style="margin-top:14px">= <span data-num="1200" data-fmt="brl0" data-em="1.85" data-dur=".9">R$ 0</span>/mês</p>
                    <div class="rodape">*exemplo ilustrativo</div>
                </section>

                {{-- 7 · Clientes e dinheiro entrando --}}
                <section class="cena" data-de="17.2" data-ate="20.6">
                    <p class="t m" data-a="sobe">Feche mais</p>
                    <p class="t g" data-a="sobe" data-em=".12"><mark>clientes.</mark></p>
                    <div class="card" data-a="esq" data-em=".35" style="width:100%;margin-top:50px;display:flex;gap:30px;align-items:center">
                        <span class="pdf">PDF</span>
                        <div style="flex:1">
                            <div style="font-size:40px;font-weight:800">contrato-cliente.pdf</div>
                            <div class="trilho"><span data-a="barra" data-v="100" data-em=".55" data-dur=".7"></span></div>
                        </div>
                        <span class="verde" data-a="pop" data-em="1.25" data-dur=".25" style="font-size:40px;font-weight:900">Pronto ✓</span>
                    </div>
                    <div style="width:100%">
                        <div class="toast" data-a="pop" data-em="1.5" data-dur=".28"><i>✓</i><span>Pix recebido · +R$ 2.400<small>Projeto site · agora</small></span></div>
                        <div class="toast" data-a="pop" data-em="1.85" data-dur=".28"><i>✓</i><span>Pix recebido · +R$ 850<small>Manutenção · agora</small></span></div>
                        <div class="toast" data-a="pop" data-em="2.2" data-dur=".28"><i>✓</i><span>Pix recebido · +R$ 1.300<small>Identidade visual · agora</small></span></div>
                    </div>
                    <p class="t m verde" data-a="zoom" data-em="2.6" data-dur=".3" style="margin-top:44px"><span data-num="4550" data-fmt="brl0" data-em="2.6" data-dur=".6">R$ 0</span> no mês</p>
                    <div class="rodape">*valores ilustrativos</div>
                </section>

                {{-- 8 · Meta batida --}}
                <section class="cena" data-de="20.6" data-ate="23.4">
                    <p class="t m" data-a="sobe">Meta do mês</p>
                    <div style="position:relative;width:560px;height:560px;margin-top:50px" data-a="pop" data-em=".1">
                        <svg viewBox="-110 -110 220 220" width="560" height="560">
                            <circle r="92" fill="none" stroke="rgba(255,255,255,.14)" stroke-width="18"/>
                            <circle r="92" fill="none" stroke="#2ee07a" stroke-width="18" stroke-linecap="round" pathLength="1" stroke-dasharray="1" data-a="anel" data-v="100" data-em=".25" data-dur="1.3" transform="rotate(-90)"/>
                        </svg>
                        <div class="t g" style="position:absolute;inset:0;display:grid;place-items:center" data-num="100" data-fmt="pct" data-em=".25" data-dur="1.3">0%</div>
                    </div>
                    <p class="t verde" data-a="zoom sacode" data-em="1.6" data-dur=".25" style="margin-top:40px;font-size:170px;white-space:nowrap">BATIDA 🎯</p>
                    @foreach ($confete as [$ang, $dist, $cor, $rot])
                        <span class="confete" data-a="confete" data-em="1.6" data-dur="1.1" data-ang="{{ $ang }}" data-dist="{{ $dist }}" data-r="{{ $rot }}" style="background:{{ $cor }}"></span>
                    @endforeach
                </section>

                {{-- 9 · Ritmo: três palavras --}}
                <section class="cena" data-de="23.4" data-ate="25.8">
                    <div class="fundo-cor" data-a="apaga" data-dur=".08" data-sai=".75" style="background:#0f1720"></div>
                    <div class="fundo-cor" data-a="apaga" data-em=".75" data-dur=".08" data-sai=".75" style="background:{{ $prim }}"></div>
                    <div class="fundo-cor" data-a="apaga" data-em="1.5" data-dur=".08" style="background:#0b3d22"></div>
                    <p class="t x" data-a="zoom" data-dur=".22" data-sai=".72" style="position:absolute">Organiza.</p>
                    <p class="t x" data-a="zoom" data-em=".75" data-dur=".22" data-sai=".72" style="position:absolute;color:{{ $onPrim }}">Controla.</p>
                    <p class="t x" data-a="zoom sacode" data-em="1.5" data-dur=".22" style="position:absolute;color:#2ee07a">Lucra. 💰</p>
                </section>

                {{-- 10 · Chamada --}}
                <section class="cena" data-de="25.8" data-ate="30">
                    <div class="flor" data-a="pop gira" data-dur=".45" style="width:470px;height:470px">@include('partials.flor', ['class' => ''])</div>
                    <p class="t g" data-a="sobe" data-em=".3" style="margin-top:30px">Work Flower</p>
                    <p class="txt" data-a="sobe" data-em=".5" style="margin-top:24px">Organize a vida.<br><mark style="font-weight:800">Aumente o lucro.</mark></p>
                    <div data-a="pop pulsa" data-em=".9" data-dur=".3" style="margin-top:60px"><span class="botao">Comece grátis</span></div>
                    <div data-a="sobe" data-em="1.2" style="margin-top:40px"><span class="url">websight.com.br/work-flower</span></div>
                    <p class="t p" data-a="sobe flutua" data-em="1.6" style="margin-top:56px">👆 Link na bio</p>
                </section>
@endsection
