{{-- Base das animações curtas (9:16) de anúncio: moldura, CSS do palco, controles e "Baixar MP4" (js/video-curto.js).
     Cada animação em resources/views/anuncios/<slug>.blade.php faz @extends('anuncios.base'), define $prim e $onPrim
     (App\Support\Anuncios::cores()) e preenche: titulo, duracao, arquivo (nome do arquivo baixado) e cenas.
     Atributos das animações: data-a (efeitos), data-em (início, em segundos desde o começo da cena), data-dur,
     data-sai (saída), data-dx/data-dy/data-r (posição e giro de base), data-num/data-fmt (contadores), data-v (barras). --}}
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('titulo') · Work Flower</title>
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
        <a href="{{ route('anuncios.index') }}">← Animações</a>
        <span style="color:#6f8296">@yield('titulo') · 9:16 · @yield('duracao') s · só você vê</span>
    </div>

    <div class="moldura" id="moldura">
        <div class="escala" id="escala">
            <div class="palco" id="palco" data-arquivo="@yield('arquivo')" style="--prim: {{ $prim }}; --on-cab: #e8edf3">
                <div class="grade"></div>
                <div class="barras" id="barras"></div>
                <div class="marca">
                    <svg viewBox="-12 -12 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true">
                        @foreach (range(0, 5) as $i)<path transform="rotate({{ $i * 60 }})" d="M0-3.6 L3.4-7.6 L0-11.4 L-3.4-7.6Z"/>@endforeach
                        <circle r="2" fill="{{ $prim }}" stroke="none"/>
                    </svg>
                    Work Flower
                </div>

@yield('cenas')
            </div>
        </div>
    </div>

    <div class="controles">
        <button type="button" class="bt" id="tocar">❚❚ Pausar</button>
        <button type="button" class="bt" id="reiniciar" title="Do começo">↺</button>
        <input type="range" id="linha" min="0" max="@yield('duracao')" step="0.01" value="0" aria-label="Posição do vídeo">
        <span class="tempo" id="tempo">0,0 s</span>
        <button type="button" class="bt prim" id="baixar">⬇ Baixar MP4</button>
    </div>
    <div class="estado" id="estado"></div>
</div>
<script src="{{ asset('js/video-curto.js') }}?v={{ @filemtime(public_path('js/video-curto.js')) }}"></script>
</body>
</html>
