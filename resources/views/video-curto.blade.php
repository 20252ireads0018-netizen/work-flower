{{-- Vídeo curto vertical (9:16, 30 s) de divulgação, feito só com HTML e CSS e animado por uma linha do tempo
     (js/video-curto.js). Só abre para as contas em User::VIDEO_CURTO. O botão "Baixar" grava quadro a quadro em MP4.
     Atributos das animações: data-a (efeitos), data-em (início, em segundos desde o começo da cena), data-dur,
     data-sai (saída), data-dx/data-dy/data-r (posição e giro de base), data-num/data-fmt (contadores), data-v (barras). --}}
@php
    $u = auth()->user();
    $prim = $u->cor_primaria ?: \App\Models\User::COR_PRIMARIA_PADRAO;
    $onPrim = \App\Models\User::contraste($prim);
    $bagunca = [['📊 Planilha', -300, -480, -8], ['📝 Bloco de notas', 250, -570, 6], ['📅 Agenda', -330, -190, 5], ['🏦 App do banco', 290, -270, -7],
                ['💬 WhatsApp', -250, 90, -4], ['📒 Caderno', 300, 30, 8], ['🗂️ 14 abas abertas', -20, 310, -3], ['🟨 Post-it', 40, -760, 4]];
    $confete = [];
    for ($i = 0; $i < 44; $i++) {
        $confete[] = [round(($i * 137.5) % 360, 1), 260 + ($i * 53) % 420, ['#22c55e', '#facc15', $prim, '#f472b6', '#38bdf8'][$i % 5], ($i * 71) % 360];
    }
@endphp
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Vídeo curto · Work Flower</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link id="fontes-video" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Sans:wght@500;700;900&display=block" rel="stylesheet">
<style>
    html, body { margin: 0; height: 100%; background: #07090c; color: #e8edf3; font-family: 'DM Sans', system-ui, sans-serif; }
    .pagina { min-height: 100%; display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 14px 16px 22px; box-sizing: border-box; }
    .topo { width: 100%; max-width: 720px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 14px; }
    .topo a { color: #9fb0c3; text-decoration: none; }
    .topo a:hover { color: #fff; }
    .moldura { position: relative; height: min(78svh, calc((100vw - 32px) * 16 / 9)); aspect-ratio: 9 / 16; border-radius: 18px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.6); background: #000; }
    .escala { position: absolute; left: 0; top: 0; width: 1080px; height: 1920px; transform-origin: 0 0; }
    .controles { width: 100%; max-width: 520px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .controles input[type=range] { flex: 1 1 200px; accent-color: {{ $prim }}; }
    .bt { font: 600 14px/1 'DM Sans', system-ui, sans-serif; padding: 10px 14px; border-radius: 10px; border: 1px solid #2a3442; background: #121821; color: #e8edf3; cursor: pointer; }
    .bt:hover { background: #18212c; }
    .bt.prim { background: {{ $prim }}; color: {{ $onPrim }}; border-color: transparent; }
    .bt:disabled { opacity: .55; cursor: default; }
    .tempo { font: 500 13px ui-monospace, Menlo, monospace; color: #9fb0c3; min-width: 92px; text-align: right; }
    .estado { width: 100%; max-width: 520px; font-size: 13px; color: #9fb0c3; min-height: 18px; }
    .estado .barra { height: 6px; border-radius: 3px; background: #1b2430; overflow: hidden; margin-top: 6px; }
    .estado .barra span { display: block; height: 100%; width: 0; background: {{ $prim }}; }
</style>
{{-- Tudo que o vídeo usa fica neste bloco: ele é copiado para dentro de cada quadro na hora de baixar --}}
<style id="css-video">
    .palco { position: relative; width: 1080px; height: 1920px; overflow: hidden; box-sizing: border-box;
             font-family: 'DM Sans', system-ui, sans-serif; color: #f3f6fa;
             background: radial-gradient(120% 70% at 50% 0%, #16202c 0%, #0a0d12 60%, #06080b 100%); }
    .palco * { box-sizing: border-box; }
    .palco .grade { position: absolute; inset: 0; opacity: .5;
             background: linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px) 0 0 / 60px 60px, linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px) 0 0 / 60px 60px; }
    .palco .barras { position: absolute; left: 36px; right: 36px; top: 34px; display: flex; gap: 8px; z-index: 20; }
    .palco .barras i { flex: 1; height: 8px; border-radius: 4px; background: rgba(255,255,255,.25); overflow: hidden; }
    .palco .barras b { display: block; height: 100%; width: 0; background: #fff; }
    .palco .marca { position: absolute; left: 44px; top: 70px; z-index: 20; display: flex; align-items: center; gap: 14px; font: 700 34px 'Bricolage Grotesque', sans-serif; opacity: .9; }
    .palco .marca svg { width: 44px; height: 44px; }
    .palco .cena { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 170px 70px 140px; transform-origin: 50% 50%; }
    .palco .t { font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif; font-weight: 800; letter-spacing: -0.03em; line-height: .98; margin: 0; }
    .palco .g { font-size: 128px; } .palco .m { font-size: 92px; } .palco .p { font-size: 64px; }
    .palco .x { font-size: 210px; }
    .palco .txt { font-size: 50px; font-weight: 700; line-height: 1.2; color: #c9d4e0; margin: 0; }
    .palco mark { background: {{ $prim }}; color: {{ $onPrim }}; padding: 0 .16em; border-radius: .14em; box-decoration-break: clone; -webkit-box-decoration-break: clone; }
    .palco .verm { color: #ff4d4d; } .palco .verde { color: #2ee07a; } .palco .ouro { color: #ffd23f; }
    .palco .tag { display: inline-block; font: 800 30px 'DM Sans', sans-serif; letter-spacing: .14em; padding: 12px 26px; border-radius: 999px; color: {{ $prim }}; border: 3px solid {{ $prim }}; background: rgba(255,255,255,.04); margin-bottom: 34px; }
    .palco .card { background: rgba(22,30,40,.92); border: 2px solid rgba(255,255,255,.1); border-radius: 36px; padding: 34px 40px; box-shadow: 0 30px 80px rgba(0,0,0,.5); text-align: left; }
    .palco .rot { font-size: 34px; font-weight: 700; color: #8fa2b6; }
    .palco .valor { font: 800 96px 'Bricolage Grotesque', sans-serif; letter-spacing: -0.03em; }
    .palco .linha { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
    .palco .chip { position: absolute; left: 50%; top: 50%; white-space: nowrap; font-size: 44px; font-weight: 700; padding: 22px 34px; border-radius: 26px; background: #1a2330; border: 2px solid rgba(255,255,255,.14); box-shadow: 0 16px 40px rgba(0,0,0,.45); margin: -45px 0 0 -170px; }
    .palco .rodape { position: absolute; left: 0; right: 0; bottom: 70px; font-size: 26px; color: #6f8296; }
    .palco .grafico { display: flex; align-items: flex-end; gap: 18px; height: 230px; margin-top: 30px; }
    .palco .grafico span { flex: 1; border-radius: 14px 14px 4px 4px; background: {{ $prim }}; height: 0; }
    .palco .alerta { border-color: #ff4d4d; background: rgba(60,14,18,.92); }
    .palco .ok-carimbo { position: absolute; right: 30px; top: -36px; transform-origin: 50% 50%; font: 800 44px 'DM Sans', sans-serif; color: #06120a; background: #2ee07a; padding: 12px 26px; border-radius: 18px; }
    .palco .risco { position: absolute; left: 30px; top: 50%; height: 8px; width: 0; background: #2ee07a; border-radius: 4px; }
    .palco .logo-mkt { font-size: 40px; font-weight: 900; padding: 18px 30px; border-radius: 22px; }
    .palco .prod { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 26px 32px; border-radius: 26px; background: rgba(22,30,40,.92); border: 2px solid rgba(255,255,255,.1); font-size: 42px; font-weight: 700; margin-top: 18px; }
    .palco .prod small { display: block; font-size: 28px; color: #8fa2b6; font-weight: 600; margin-top: 4px; }
    .palco .prod b { font: 800 50px 'Bricolage Grotesque', sans-serif; }
    .palco .prod.ruim { border-color: #ff4d4d; background: rgba(60,14,18,.92); }
    .palco .prod.bom { border-color: #2ee07a; background: rgba(10,48,26,.92); }
    .palco .toast { display: flex; align-items: center; gap: 24px; padding: 28px 34px; border-radius: 30px; background: #f3f6fa; color: #0a0d12; font-size: 42px; font-weight: 800; box-shadow: 0 20px 50px rgba(0,0,0,.45); margin-top: 22px; text-align: left; }
    .palco .toast i { flex: none; width: 74px; height: 74px; border-radius: 22px; background: #2ee07a; display: grid; place-items: center; font-style: normal; font-size: 40px; }
    .palco .toast small { display: block; font-size: 28px; font-weight: 600; color: #4b5b6c; }
    .palco .pdf { flex: none; width: 96px; height: 120px; border-radius: 14px; background: #fff; color: #d4372c; border-top: 14px solid #d4372c; display: grid; place-items: center; font: 900 30px 'DM Sans', sans-serif; }
    .palco .trilho { height: 18px; border-radius: 9px; background: rgba(255,255,255,.12); overflow: hidden; margin-top: 16px; }
    .palco .trilho span { display: block; height: 100%; width: 0; background: #2ee07a; border-radius: 9px; }
    .palco .confete { position: absolute; left: 50%; top: 46%; width: 26px; height: 40px; border-radius: 6px; margin: -20px 0 0 -13px; }
    .palco .fundo-cor { position: absolute; inset: 0; }
    .palco .botao { display: inline-block; font: 800 64px 'DM Sans', sans-serif; padding: 34px 70px; border-radius: 999px; background: {{ $prim }}; color: {{ $onPrim }}; box-shadow: 0 24px 70px rgba(0,0,0,.5); }
    .palco .url { display: inline-block; font-size: 40px; font-weight: 700; padding: 18px 34px; border-radius: 999px; border: 2px solid rgba(255,255,255,.25); color: #dbe4ee; }
    .palco .flor { color: #e8edf3; }
    .palco .flor svg { width: 100%; height: 100%; display: block; }
</style>
</head>
<body>
<div class="pagina">
    <div class="topo">
        <a href="{{ route('painel') }}">← Voltar ao painel</a>
        <span style="color:#6f8296">Vídeo curto · 9:16 · 30 s · só você vê</span>
    </div>

    <div class="moldura" id="moldura">
        <div class="escala" id="escala">
            <div class="palco" id="palco" style="--prim: {{ $prim }}; --on-cab: #e8edf3">
                <div class="grade"></div>
                <div class="barras" id="barras"></div>
                <div class="marca">
                    <svg viewBox="-12 -12 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true">
                        @foreach (range(0, 5) as $i)<path transform="rotate({{ $i * 60 }})" d="M0-3.6 L3.4-7.6 L0-11.4 L-3.4-7.6Z"/>@endforeach
                        <circle r="2" fill="{{ $prim }}" stroke="none"/>
                    </svg>
                    Work Flower
                </div>

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
            </div>
        </div>
    </div>

    <div class="controles">
        <button type="button" class="bt" id="tocar">❚❚ Pausar</button>
        <button type="button" class="bt" id="reiniciar" title="Do começo">↺</button>
        <input type="range" id="linha" min="0" max="30" step="0.01" value="0" aria-label="Posição do vídeo">
        <span class="tempo" id="tempo">0,0 / 30,0 s</span>
        <button type="button" class="bt prim" id="baixar">⬇ Baixar MP4</button>
    </div>
    <div class="estado" id="estado"></div>
</div>
<script src="{{ asset('js/video-curto.js') }}?v={{ @filemtime(public_path('js/video-curto.js')) }}"></script>
</body>
</html>
