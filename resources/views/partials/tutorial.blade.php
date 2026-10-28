{{-- Work Flower · tutorial do painel (helper)
     Botão flutuante "Tutorial" que abre um passo a passo: painel, quadros, Corpo, Mente, Carteira, Diverso,
     Biblioteca e Importar/exportar workflows. Não depende de nenhum outro .js; usa as variáveis de tema do layout.
     Também abre pelo evento: window.dispatchEvent(new CustomEvent('abrir-tutorial')) ou por WFTutorial.abrir(). --}}
<button type="button" id="wft-botao" class="wft-botao" aria-haspopup="dialog" aria-label="Abrir o tutorial do Work Flower" title="Tutorial">
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 114 2c-.9.6-1.6 1.1-1.6 2.1M12 17h.01"/>
    </svg>
    <span class="wft-botao-texto">Tutorial</span>
</button>

<style>
    .wft-botao {
        position: fixed; z-index: 40;
        right: max(1rem, var(--seg-d, 0px)); bottom: calc(1rem + var(--seg-b, 0px));
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .55rem .9rem; border-radius: 9999px;
        background: var(--prim); color: var(--on-prim);
        font-size: .85rem; font-weight: 600;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .4);
        transition: background-color .15s, transform .15s;
    }
    .wft-botao:hover { background: var(--prim-forte); transform: translateY(-1px); }
    .wft-botao.novo { animation: wft-pulso 2.2s ease-out 3; }
    @keyframes wft-pulso {
        0% { box-shadow: 0 8px 24px rgba(0,0,0,.4), 0 0 0 0 color-mix(in srgb, var(--prim) 55%, transparent); }
        100% { box-shadow: 0 8px 24px rgba(0,0,0,.4), 0 0 0 14px transparent; }
    }
    @media (max-width: 639px) { .wft-botao-texto { display: none; } .wft-botao { padding: .7rem; } }

    .wft-raiz { position: fixed; inset: 0; z-index: 60; }
    .wft-raiz[hidden] { display: none; }
    .wft-fundo { position: absolute; inset: 0; }
    .wft-furo {
        position: fixed; pointer-events: none; border-radius: .75rem;
        box-shadow: 0 0 0 100vmax rgba(4, 6, 9, .78);
        outline: 2px solid var(--prim); outline-offset: 0;
        transition: left .2s, top .2s, width .2s, height .2s;
    }
    .wft-furo.vazio { outline: none; border-radius: 0; }

    .wft-caixa {
        position: fixed; left: 50%; width: min(36rem, calc(100vw - 1rem));
        max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem);
        display: flex; flex-direction: column;
        background: var(--superficie); color: var(--tinta);
        border: 1px solid var(--linha); border-radius: .85rem;
        box-shadow: 0 20px 50px rgba(0, 0, 0, .55);
    }
    .wft-caixa:focus { outline: none; }
    .wft-caixa.centro { top: 50%; transform: translate(-50%, -50%); }
    .wft-caixa.baixo  { bottom: calc(1rem + var(--seg-b, 0px)); transform: translateX(-50%); }
    .wft-caixa.topo   { top: 1rem; transform: translateX(-50%); }

    .wft-cab { display: flex; align-items: flex-start; gap: .75rem; padding: 1.1rem 1.25rem .5rem; }
    .wft-cab h2 { font-size: 1.35rem; line-height: 1.2; flex: 1; min-width: 0; }
    .wft-passo { font-size: .75rem; color: var(--tinta-2); margin-bottom: .2rem; }
    .wft-corpo { padding: .25rem 1.25rem 1rem; overflow-y: auto; min-height: 0; }
    .wft-corpo > p { font-size: .9rem; line-height: 1.5; color: var(--tinta); max-width: 60ch; }
    .wft-corpo > p + * { margin-top: .85rem; }
    .wft-pontos { list-style: none; padding: 0; display: grid; gap: .5rem; }
    .wft-pontos li {
        font-size: .85rem; line-height: 1.45; color: var(--tinta-2);
        padding-left: .9rem; border-left: 2px solid var(--prim-linha);
    }
    .wft-pontos b { color: var(--tinta); font-weight: 600; }
    .wft-campos { display: grid; gap: .5rem; grid-template-columns: 1fr; }
    @media (min-width: 560px) { .wft-campos { grid-template-columns: 1fr 1fr; } }
    .wft-campo { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .6rem; padding: .55rem .7rem; min-width: 0; }
    .wft-campo b { display: block; font-size: .85rem; color: var(--prim-forte); font-weight: 600; margin-bottom: .1rem; }
    .wft-campo span { font-size: .8rem; line-height: 1.4; color: var(--tinta-2); }
    .wft-campo.largo { grid-column: 1 / -1; }
    .wft-acoes { display: flex; flex-wrap: wrap; gap: .5rem; }

    .wft-rodape {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem;
        padding: .75rem 1.25rem 1rem; border-top: 1px solid var(--linha);
    }
    .wft-pontinhos { display: flex; flex-wrap: wrap; gap: .1rem; }
    .wft-pontinho { width: 1.1rem; height: 1.1rem; display: grid; place-content: center; border-radius: 9999px; }
    .wft-pontinho::after { content: ""; width: .5rem; height: .5rem; border-radius: 9999px; background: var(--linha); transition: background-color .15s, transform .15s; }
    .wft-pontinho:hover::after { background: var(--prim-linha); }
    .wft-pontinho.ativo::after { background: var(--prim); transform: scale(1.3); }
    @media (pointer: coarse) { .wft-pontinho { width: 1.6rem; height: 1.6rem; } }
    .wft-nav { display: flex; gap: .5rem; margin-left: auto; }
    .wft-nav .btn, .wft-nav .btn-sec { padding: .5rem .9rem; }
    .wft-nav [disabled] { opacity: .45; cursor: not-allowed; }

    @media (prefers-reduced-motion: reduce) {
        .wft-botao.novo { animation: none; }
        .wft-furo { transition: none; }
    }
    .wft-caixa { width: min(38rem, calc(100vw - 1rem)); }
    .wft-campo { padding: .4rem .6rem; }
    .wft-campo span { font-size: .78rem; }

    /* ---------- demos animados (mini-gifs em CSS puro, com elementos do app) ---------- */
    .wfd { --T: 8s; --wf-s: color-mix(in srgb, var(--prim) 18%, transparent); position: relative; height: 12rem; margin-bottom: .85rem; border-radius: .7rem; border: 1px solid var(--linha); background: var(--superficie-2); overflow: hidden; font-size: .68rem; line-height: 1.25; color: var(--tinta); user-select: none; pointer-events: none; }
    @media (max-width: 420px) { .wfd { font-size: .62rem; } }
    .wfd *, .wfd *::before, .wfd *::after { box-sizing: border-box; }
    .wfd-in { position: absolute; inset: .65rem; display: flex; gap: .55rem; min-height: 0; animation: wfdf var(--T) linear infinite; }
    @keyframes wfdf { 0% { opacity: 0; } 4%, 92% { opacity: 1; } 100% { opacity: 0; } }
    .wfd .bl { background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; padding: .4rem .5rem; min-width: 0; position: relative; }
    .wfd .ln { height: .28rem; border-radius: 9999px; background: var(--linha); margin-top: .3rem; }
    .wfd .t { font-weight: 600; color: var(--tinta); }
    .wfd .s { color: var(--tinta-2); }
    .wfd .col { display: flex; flex-direction: column; gap: .55rem; min-width: 0; }
    .wfd .ic { width: 1.15rem; height: 1.15rem; stroke: var(--prim-forte); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .wfd .ck { position: relative; flex: none; width: .85rem; height: .85rem; border: 1.5px solid var(--prim); border-radius: .25rem; background: var(--prim); }
    .wfd .ck::after { content: ""; position: absolute; left: .21rem; top: .02rem; width: .22rem; height: .47rem; border: solid var(--on-prim); border-width: 0 2px 2px 0; transform: rotate(45deg); }
    .wfd .ck.v { background: transparent; border-color: var(--prim-linha); }
    .wfd .ck.v::after { display: none; }
    .wfd .cur { position: absolute; width: 16px; height: 16px; z-index: 6; opacity: 0; filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .55)); }
    .wfd .bar { height: .35rem; border-radius: 9999px; background: var(--linha); overflow: hidden; margin-top: .3rem; }
    .wfd .bar i { display: block; height: 100%; width: var(--w); background: var(--prim); transform-origin: left; animation: d3b var(--T) ease-out infinite both; animation-delay: calc(var(--i, 0) * .25s); }
    @keyframes d3b { 0% { transform: scaleX(0); } 20%, 90% { transform: scaleX(1); } 98%, 100% { transform: scaleX(0); } }
    .wfd .tg { display: inline-block; padding: .05rem .35rem; border-radius: 9999px; background: var(--wf-s); color: var(--prim-forte); font-size: .58rem; margin-right: .2rem; }

    /* 1 · início */
    .wfd .d1-flor { animation: d1g 14s linear infinite; flex: none; }
    .wfd .d1-flor ellipse { fill: var(--prim); fill-opacity: .35; }
    .wfd .d1-flor circle { fill: var(--cab, var(--superficie)); stroke: var(--prim); stroke-width: 3; }
    .wfd .d1-grade { display: grid; grid-template-columns: repeat(4, 1fr); gap: .5rem; width: 100%; }
    .wfd .d1-t { display: grid; justify-items: center; gap: .15rem; text-align: center; padding: .55rem .3rem; animation: d1p var(--T) ease-out infinite both; animation-delay: calc(var(--i) * .35s); }
    .wfd .d1-t .s { font-size: .58rem; }
    @keyframes d1g { to { transform: rotate(360deg); } }
    @keyframes d1p { 0% { opacity: 0; transform: translateY(10px) scale(.9); } 8%, 88% { opacity: 1; transform: none; } 96%, 100% { opacity: 0; } }

    /* 2 · tarefas gerais */
    .wfd .d2-h { display: flex; justify-content: space-between; }
    .wfd .d2-g { font-size: .56rem; letter-spacing: .07em; color: var(--prim-forte); font-weight: 700; margin-top: .3rem; }
    .wfd .d2-r { display: flex; align-items: center; gap: .4rem; padding: .2rem 0; max-height: 1.4rem; }
    .wfd .d2-r span { flex: 1; position: relative; white-space: nowrap; }
    .wfd .d2-r em { font-style: normal; color: var(--tinta-2); font-size: .58rem; }
    .wfd .d2-r.alvo { animation: d2g var(--T) ease-in-out infinite; }
    .wfd .d2-r.alvo span { color: var(--tinta-2); }
    .wfd .d2-r.alvo span::after { content: ""; position: absolute; left: 0; right: 0; top: 52%; height: 1px; background: var(--tinta-2); transform-origin: left; animation: d2s var(--T) infinite; }
    .wfd .alvo .ck { animation: d2c var(--T) infinite; }
    .wfd .alvo .ck::after { animation: d2t var(--T) infinite; }
    .wfd .alvo .cur { left: .5rem; top: .5rem; animation: d2m var(--T) infinite; }
    @keyframes d2c { 0%, 28% { background: transparent; border-color: var(--prim-linha); } 34%, 100% { background: var(--prim); border-color: var(--prim); } }
    @keyframes d2t { 0%, 28% { opacity: 0; } 34%, 100% { opacity: 1; } }
    @keyframes d2s { 0%, 34% { transform: scaleX(0); } 44%, 100% { transform: scaleX(1); } }
    @keyframes d2g { 0%, 56% { max-height: 1.4rem; opacity: 1; } 70%, 94% { max-height: 0; opacity: 0; padding: 0; } 100% { max-height: 1.4rem; opacity: 1; } }
    @keyframes d2m { 0%, 6% { opacity: 0; transform: translate(46px, 34px); } 12% { opacity: 1; transform: translate(46px, 34px); } 25% { opacity: 1; transform: translate(0, 0); } 29% { opacity: 1; transform: translate(0, 0) scale(.78); } 33% { opacity: 1; transform: translate(0, 0); } 44%, 100% { opacity: 0; transform: translate(0, 0); } }

    /* 3 · áreas */
    .wfd .d3 { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; width: 100%; }
    .wfd .d3 .bl { display: flex; flex-direction: column; justify-content: center; }
    .wfd .d3-top { display: flex; align-items: center; gap: .35rem; }
    .wfd .d3-top .s { margin-left: auto; font-size: .6rem; }
    .wfd .d3-1 { animation: d3h var(--T) infinite; }
    .wfd .d3-1 .cur { right: .6rem; bottom: .3rem; animation: d3m var(--T) infinite; }
    @keyframes d3h { 0%, 56% { border-color: var(--linha); box-shadow: none; } 62%, 92% { border-color: var(--prim); box-shadow: 0 0 0 3px var(--wf-s); } 100% { border-color: var(--linha); box-shadow: none; } }
    @keyframes d3m { 0%, 34% { opacity: 0; transform: translate(60px, 40px); } 42% { opacity: 1; transform: translate(60px, 40px); } 56% { opacity: 1; transform: translate(0, 0); } 60% { opacity: 1; transform: scale(.78); } 64%, 92% { opacity: 1; transform: none; } 100% { opacity: 0; } }

    /* 4 · quadros */
    .wfd .d4 { padding: 0; }
    .wfd .d4-fundo { position: absolute; inset: 0; background-image: radial-gradient(var(--linha) 1px, transparent 1px); background-size: 14px 14px; }
    .wfd .bl.d4-a, .wfd .bl.d4-b, .wfd .bl.d4-c { position: absolute; padding: .3rem .45rem; }
    .wfd .d4-a { left: 5%; top: 9%; width: 38%; height: 38%; animation: d4a var(--T) infinite; }
    .wfd .d4-b { right: 5%; top: 9%; width: 42%; height: 38%; animation: d4b var(--T) ease-in-out infinite; }
    .wfd .d4-c { left: 5%; bottom: 9%; width: 38%; height: 38%; animation: d4c var(--T) ease-in-out infinite; }
    .wfd .d4-hd { display: flex; justify-content: space-between; align-items: center; font-weight: 600; margin-bottom: .15rem; }
    .wfd .d4-x { color: var(--tinta-2); font-size: .85rem; line-height: 1; }
    .wfd .d4-rs { position: absolute; right: -.2rem; bottom: -.2rem; width: .5rem; height: .5rem; border-radius: .15rem; background: var(--prim); }
    .wfd .tb { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; }
    .wfd .tb i { height: .55rem; background: var(--linha); border-radius: 2px; }
    .wfd .tb i:nth-child(-n+3) { background: var(--prim-linha); }
    .wfd .d4-li { display: flex; align-items: center; gap: .3rem; margin-top: .25rem; }
    .wfd .d4-li .ln { flex: 1; margin: 0; }
    .wfd .d4-b .cur { left: 45%; top: .25rem; animation: d4mb var(--T) infinite; }
    .wfd .d4-c .cur { right: -.35rem; bottom: -.35rem; animation: d4mc var(--T) infinite; }
    .wfd .d4-a .cur { right: .1rem; top: .05rem; animation: d4ma var(--T) infinite; }
    .wfd .d4-toast { position: absolute; left: 50%; bottom: .6rem; display: flex; gap: .7rem; padding: .35rem .65rem; border-radius: .5rem; background: var(--superficie); border: 1px solid var(--prim); box-shadow: 0 6px 18px rgba(0, 0, 0, .4); font-weight: 600; white-space: nowrap; transform: translateX(-50%); animation: d4t var(--T) ease-out infinite; z-index: 5; }
    .wfd .d4-toast b { color: var(--prim-forte); }
    @keyframes d4b { 0%, 6% { transform: none; } 30%, 90% { transform: translateY(112%); } 100% { transform: none; } }
    @keyframes d4mb { 0% { opacity: 0; } 6%, 30% { opacity: 1; } 36%, 100% { opacity: 0; } }
    @keyframes d4c { 0%, 38% { width: 38%; } 56%, 90% { width: 46%; } 100% { width: 38%; } }
    @keyframes d4mc { 0%, 34% { opacity: 0; } 38%, 58% { opacity: 1; } 64%, 100% { opacity: 0; } }
    @keyframes d4a { 0%, 70% { opacity: 1; transform: none; } 78%, 92% { opacity: 0; transform: scale(.85); } 100% { opacity: 1; transform: none; } }
    @keyframes d4ma { 0%, 62% { opacity: 0; } 66% { opacity: 1; } 72% { opacity: 1; transform: scale(.8); } 76%, 100% { opacity: 0; } }
    @keyframes d4t { 0%, 76% { opacity: 0; transform: translate(-50%, 8px); } 82%, 92% { opacity: 1; transform: translate(-50%, 0); } 98%, 100% { opacity: 0; } }

    /* 5 · corpo */
    .wfd .d5-l { flex: 1.5; }
    .wfd .d5-r { display: grid; grid-template-columns: 1fr auto auto; gap: .6rem; padding: .27rem 0; border-top: 1px solid var(--linha); animation: d5r var(--T) ease-out infinite both; animation-delay: calc(var(--i) * .3s); }
    .wfd .d5-r b { color: var(--prim-forte); font-weight: 600; }
    .wfd .d5-r:first-of-type { margin-top: .3rem; }
    .wfd .d5-a { display: flex; align-items: center; gap: .5rem; flex: 1; }
    .wfd .gl { position: relative; flex: none; width: 2rem; height: 3.3rem; border: 1.5px solid var(--prim-linha); border-radius: .25rem .25rem .7rem .7rem; overflow: hidden; }
    .wfd .gl i { position: absolute; left: 0; right: 0; bottom: 0; height: 70%; background: var(--prim); opacity: .85; animation: d5w var(--T) ease-out infinite; }
    .wfd .d5-m { display: grid; grid-template-columns: 2rem 1fr; align-items: center; gap: .3rem; }
    .wfd .d5-m .bar { margin: .15rem 0; }
    @keyframes d5r { 0% { opacity: 0; transform: translateX(-12px); } 10%, 90% { opacity: 1; transform: none; } 98%, 100% { opacity: 0; } }
    @keyframes d5w { 0%, 15% { height: 12%; } 45%, 92% { height: 70%; } 100% { height: 12%; } }

    /* 6 · mente */
    .wfd .d6-l { flex: 1.3; }
    .wfd .hl { position: relative; margin-top: .35rem; }
    .wfd .hl::before { content: ""; position: absolute; inset: -.05rem -.1rem; background: rgba(250, 204, 21, .5); border-radius: .15rem; transform-origin: left; animation: d6h var(--T) ease-out infinite; }
    .wfd .hl span { position: relative; color: var(--tinta); }
    .wfd .d6-n { animation: d6n var(--T) ease-out infinite; }
    .wfd .d6-r { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .55rem; border-radius: 9999px; background: var(--wf-s); color: var(--prim-forte); font-weight: 600; width: max-content; animation: d6r var(--T) ease-out infinite; }
    @keyframes d6h { 0%, 14% { transform: scaleX(0); } 40%, 100% { transform: scaleX(1); } }
    @keyframes d6n { 0%, 42% { opacity: 0; transform: translateX(26px); } 52%, 100% { opacity: 1; transform: none; } }
    @keyframes d6r { 0%, 62% { opacity: 0; transform: scale(.7); } 72%, 100% { opacity: 1; transform: none; } }

    /* 7 · carteira */
    .wfd .d7-l { flex: 1.4; }
    .wfd .d7-b { display: flex; align-items: flex-end; gap: .35rem; height: 5rem; margin-top: .4rem; border-bottom: 1px solid var(--linha); }
    .wfd .d7-b i { flex: 1; height: var(--h); background: var(--prim); opacity: .55; border-radius: .2rem .2rem 0 0; transform-origin: bottom; animation: d7b var(--T) ease-out infinite both; animation-delay: calc(var(--i) * .15s); }
    .wfd .d7-b i:last-child { opacity: 1; }
    .wfd .d7-m { display: flex; justify-content: space-between; margin-top: .25rem; font-size: .58rem; color: var(--tinta-2); }
    .wfd .d7-v { font-size: 1.1rem; font-weight: 700; margin: .1rem 0 .3rem; }
    .wfd .d7-t { display: flex; justify-content: space-between; padding: .25rem .4rem; border-radius: .35rem; margin-top: .25rem; font-weight: 600; animation: d7t var(--T) ease-out infinite both; animation-delay: calc(var(--i) * 1.1s); }
    .wfd .d7-t.g { background: rgba(52, 211, 153, .14); color: #34d399; }
    .wfd .d7-t.r { background: rgba(248, 113, 113, .14); color: #f87171; }
    @keyframes d7b { 0% { transform: scaleY(0); } 16%, 90% { transform: scaleY(1); } 98%, 100% { transform: scaleY(0); } }
    @keyframes d7t { 0%, 35% { opacity: 0; transform: translateX(16px); } 46%, 90% { opacity: 1; transform: none; } 98%, 100% { opacity: 0; } }

    /* 8 · diverso */
    .wfd .d8 { flex-direction: column; }
    .wfd .d8-tabs { display: flex; gap: .35rem; align-items: center; }
    .wfd .d8-tab { padding: .2rem .55rem; border-radius: 9999px; border: 1px solid var(--linha); background: var(--superficie); white-space: nowrap; overflow: hidden; }
    .wfd .d8-tab.on { border-color: var(--prim); color: var(--prim-forte); font-weight: 600; }
    .wfd .d8-nova { animation: d8t var(--T) ease-out infinite; }
    .wfd .d8-mais { flex: none; width: 1.3rem; height: 1.3rem; display: grid; place-content: center; border-radius: 9999px; background: var(--prim); color: var(--on-prim); font-weight: 700; animation: d8p var(--T) infinite; }
    .wfd .mp { position: relative; flex: 1; border: 1px solid var(--linha); border-radius: .5rem; background-color: var(--superficie); background-image: linear-gradient(var(--linha) 1px, transparent 1px), linear-gradient(90deg, var(--linha) 1px, transparent 1px); background-size: 1.6rem 1.6rem; overflow: hidden; }
    .wfd .tk { position: absolute; left: .3rem; top: .3rem; width: 1rem; height: 1rem; border-radius: 9999px; background: var(--prim); box-shadow: 0 0 0 2px var(--wf-s); animation: d8m var(--T) ease-in-out infinite; }
    .wfd .tk .cur { left: .55rem; top: .55rem; animation: d8c var(--T) infinite; }
    .wfd .pc { position: absolute; width: 1rem; height: 1rem; border-radius: .25rem; background: #f472b6; left: 8.2rem; top: 4.9rem; }
    .wfd .fm { position: absolute; left: 6.5rem; top: 1.6rem; width: 3.2rem; height: 1.6rem; border: 2px solid var(--prim-linha); border-radius: .3rem; background: var(--wf-s); }
    @keyframes d8t { 0%, 14% { max-width: 0; opacity: 0; padding-left: 0; padding-right: 0; border-width: 0; } 28%, 100% { max-width: 7rem; opacity: 1; padding-left: .55rem; padding-right: .55rem; border-width: 1px; } }
    @keyframes d8p { 0% { transform: scale(1); } 8% { transform: scale(1.3); } 16%, 100% { transform: scale(1); } }
    @keyframes d8m { 0%, 34% { transform: translate(0, 0); } 54%, 66% { transform: translate(4.8rem, 0); } 86%, 100% { transform: translate(4.8rem, 3.2rem); } }
    @keyframes d8c { 0%, 30% { opacity: 0; } 36%, 88% { opacity: 1; } 96%, 100% { opacity: 0; } }

    /* 9 · biblioteca */
    .wfd .d9-b { flex: 1.4; display: flex; flex-direction: column; gap: .5rem; }
    .wfd .d9-b > .bl { flex: 1; }
    .wfd .bm { fill: var(--prim); stroke: var(--prim-forte); stroke-width: 2; stroke-linejoin: round; transform-origin: center; animation: d9m var(--T) infinite; }
    .wfd .d9-slot { flex: 1; border: 1.5px dashed var(--prim-linha); border-radius: .5rem; padding: .3rem; display: flex; flex-direction: column; gap: .2rem; }
    .wfd .d9-c { animation: d9c var(--T) ease-out infinite; }
    .wfd .d9-l { flex: 1; }
    .wfd .d9-i { padding: .28rem .4rem; border: 1px solid var(--linha); border-radius: .35rem; margin-top: .3rem; display: flex; gap: .3rem; align-items: center; }
    .wfd .d9-n { border-color: var(--prim); animation: d9i var(--T) ease-out infinite; }
    .wfd .d9-bt { position: relative; margin-top: .4rem; text-align: center; padding: .28rem; border-radius: .4rem; border: 1px solid var(--prim-linha); color: var(--prim-forte); font-weight: 600; animation: d9g var(--T) infinite; }
    .wfd .d9-bt .cur { right: .7rem; top: .6rem; animation: d9k var(--T) infinite; }
    @keyframes d9m { 0%, 6% { fill: transparent; transform: scale(1); } 12% { transform: scale(1.35); } 18%, 100% { fill: var(--prim); transform: scale(1); } }
    @keyframes d9i { 0%, 20% { opacity: 0; transform: translateX(-30px); } 32%, 100% { opacity: 1; transform: none; } }
    @keyframes d9g { 0%, 52% { background: transparent; color: var(--prim-forte); box-shadow: none; } 58%, 70% { background: var(--prim); color: var(--on-prim); box-shadow: 0 0 0 3px var(--wf-s); } 76%, 100% { background: transparent; color: var(--prim-forte); box-shadow: none; } }
    @keyframes d9c { 0%, 72% { opacity: 0; transform: translateY(-10px); } 84%, 100% { opacity: 1; transform: none; } }
    @keyframes d9k { 0%, 40% { opacity: 0; transform: translate(40px, 30px); } 50% { opacity: 1; transform: translate(40px, 30px); } 56% { opacity: 1; transform: translate(0, 0); } 60% { opacity: 1; transform: scale(.78); } 64%, 76% { opacity: 1; transform: none; } 82%, 100% { opacity: 0; } }

    /* 10 · importar / exportar */
    .wfd .d10-l { flex: 1; }
    .wfd .d10-k { display: flex; align-items: center; gap: .4rem; padding: .24rem 0; }
    .wfd .d10-k .ck { animation: d10c var(--T) infinite both; animation-delay: calc(var(--i) * .4s); }
    .wfd .d10-k .ck::after { animation: d10t var(--T) infinite both; animation-delay: calc(var(--i) * .4s); }
    .wfd .d10-mid { flex: .7; display: grid; place-items: center; color: var(--tinta-2); }
    .wfd .fl { position: absolute; top: 50%; margin-top: -1.05rem; left: 32%; display: flex; align-items: center; gap: .3rem; padding: .3rem .55rem; border-radius: .5rem; background: var(--prim); color: var(--on-prim); font-weight: 600; z-index: 3; white-space: nowrap; box-shadow: 0 6px 16px rgba(0, 0, 0, .35); animation: d10f var(--T) ease-in-out infinite; }
    .wfd .d10-av { width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: var(--wf-s); border: 1.5px solid var(--prim); margin-bottom: .3rem; }
    .wfd .d10-g { display: flex; align-items: center; gap: .4rem; padding: .24rem 0; animation: d10g var(--T) infinite; }
    @keyframes d10c { 0%, 8% { background: transparent; border-color: var(--prim-linha); } 14%, 90% { background: var(--prim); border-color: var(--prim); } 98%, 100% { background: transparent; border-color: var(--prim-linha); } }
    @keyframes d10t { 0%, 8% { opacity: 0; } 14%, 90% { opacity: 1; } 98%, 100% { opacity: 0; } }
    @keyframes d10f { 0%, 40% { left: 30%; opacity: 0; transform: scale(.8); } 48% { left: 32%; opacity: 1; transform: none; } 70% { left: 52%; opacity: 1; } 80%, 100% { left: 62%; opacity: 0; transform: scale(.8); } }
    @keyframes d10g { 0%, 76% { opacity: .25; } 86%, 92% { opacity: 1; } 98%, 100% { opacity: .25; } }

    @media (prefers-reduced-motion: reduce) {
        .wfd *, .wfd *::before, .wfd *::after { animation: none !important; }
        .wfd .d4-toast { display: none; }
    }
</style>

<script>
(function () {
    if (window.WFTutorial) return;

    const CHAVE = 'wf:tutorial:visto';
    const ENGRENAGEM = 'header button[aria-label="Configurações"]';

    /* ---------- conteúdo ----------
       t = título · v = demo animado · x = texto curto · p = pontos · campos = [nome, descrição] · alvo = elemento destacado
       area = nome da área (vira o botão "Abrir ...") · acoes = botões que abrem painéis do layout */
    /* ---------- demos visuais: mini-animações com elementos reais do app (só HTML/CSS, sem imagens externas) ---------- */
    const CUR = '<svg class="cur" viewBox="0 0 24 24"><path d="M5 3l14 8-6 2-3 6z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>';
    const IC = d => `<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
    const ICONES = {
        corpo: IC('<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>'),
        mente: IC('<path d="M4 5.5A2.5 2.5 0 016.5 3H20v15H6.5A2.5 2.5 0 004 20.5zM4 20.5A2.5 2.5 0 006.5 18H20"/>'),
        carteira: IC('<path d="M3 7.5A2.5 2.5 0 015.5 5H19v14H5.5A2.5 2.5 0 013 16.5zM16 12h.01"/>'),
        diverso: IC('<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>'),
    };
    const MARCADOR = '<svg class="bm" viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M6 3h12v18l-6-4-6 4z"/></svg>';
    const ARQUIVO = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>';
    const lns = (...w) => w.map(x => `<div class="ln" style="width:${x}%"></div>`).join('');

    const DEMOS = {
        inicio: () => `
            <div class="wfd-in" style="flex-direction:column;align-items:center;justify-content:space-between">
                <svg class="d1-flor" viewBox="0 0 100 100" width="60" height="60" aria-hidden="true">
                    ${[0, 60, 120, 180, 240, 300].map(g => `<ellipse cx="50" cy="27" rx="11" ry="22" transform="rotate(${g} 50 50)"/>`).join('')}
                    <circle cx="50" cy="50" r="17"/>
                </svg>
                <div class="d1-grade">
                    ${[['corpo', 'Corpo', 'treino e dieta'], ['mente', 'Mente', 'estudo e livros'], ['carteira', 'Carteira', 'contas e negócios'], ['diverso', 'Diverso', 'abas livres']]
                        .map(([k, n, s], i) => `<div class="bl d1-t" style="--i:${i}">${ICONES[k]}<div class="t">${n}</div><div class="s">${s}</div></div>`).join('')}
                </div>
            </div>`,

        tarefas: () => `
            <div class="wfd-in"><div class="bl" style="flex:1">
                <div class="d2-h"><span class="t">Tarefas gerais</span><span class="s">4 pendentes</span></div>
                <div class="d2-g">CORPO</div>
                <div class="d2-r"><i class="ck v"></i><span>Treino de pernas</span><em>hoje</em></div>
                <div class="d2-r alvo"><i class="ck">${CUR}</i><span>Beber 3 L de água</span><em>hoje</em></div>
                <div class="d2-g">MENTE</div>
                <div class="d2-r"><i class="ck v"></i><span>Revisar o capítulo 4</span><em>amanhã</em></div>
                <div class="d2-g">CARTEIRA</div>
                <div class="d2-r"><i class="ck v"></i><span>Pagar a fatura</span><em>sex</em></div>
            </div></div>`,

        areas: () => `
            <div class="wfd-in"><div class="d3">
                ${[['corpo', 'Corpo', '5/8', 62], ['mente', 'Mente', '3/6', 50], ['carteira', 'Carteira', '4/5', 80], ['diverso', 'Diverso', '2/4', 50]]
                    .map(([k, n, f, w], i) => `<div class="bl${i === 0 ? ' d3-1' : ''}"><div class="d3-top">${ICONES[k]}<span class="t">${n}</span><span class="s">${f}</span></div><div class="bar"><i style="--w:${w}%;--i:${i}"></i></div>${i === 0 ? CUR : ''}</div>`).join('')}
            </div></div>`,

        quadros: () => `
            <div class="wfd-in d4" style="inset:0">
                <div class="d4-fundo"></div>
                <div class="bl d4-a"><div class="d4-hd"><span>Notas</span><span class="d4-x">×</span></div>${lns(90, 70, 80)}${CUR}</div>
                <div class="bl d4-b"><div class="d4-hd"><span>Tabela</span><span class="s">⋮</span></div><div class="tb">${'<i></i>'.repeat(9)}</div>${CUR}</div>
                <div class="bl d4-c"><div class="d4-hd"><span>Lista</span></div>
                    ${[70, 55, 80].map(w => `<div class="d4-li"><i class="ck v"></i><div class="ln" style="width:${w}%"></div></div>`).join('')}
                    <span class="d4-rs"></span>${CUR}</div>
                <div class="d4-toast">Bloco excluído <b>Desfazer</b></div>
            </div>`,

        corpo: () => `
            <div class="wfd-in">
                <div class="bl d5-l"><div class="d2-h"><span class="t">Treino · Segunda</span><span class="s">Peito e costas</span></div>
                    ${[['Supino reto', '4×10', '60 kg'], ['Remada curvada', '4×10', '50 kg'], ['Crucifixo', '3×12', '14 kg'], ['Puxada alta', '3×12', '45 kg']]
                        .map(([n, s, c], i) => `<div class="d5-r" style="--i:${i}"><span>${n}</span><span class="s">${s}</span><b>${c}</b></div>`).join('')}
                </div>
                <div class="col" style="flex:1">
                    <div class="bl d5-a"><div class="gl"><i></i></div><div><div class="t">1,4 L</div><div class="s">meta 2 L</div></div></div>
                    <div class="bl" style="padding:.35rem .5rem">
                        ${[['Prot', 72], ['Carb', 55], ['Gord', 40]].map(([n, w], i) => `<div class="d5-m"><span class="s">${n}</span><div class="bar"><i style="--w:${w}%;--i:${i}"></i></div></div>`).join('')}
                    </div>
                </div>
            </div>`,

        mente: () => `
            <div class="wfd-in">
                <div class="bl d6-l"><div class="d2-h"><span class="t">Hábitos Atômicos</span><span class="s">p. 42</span></div>
                    ${lns(95, 85)}
                    <div class="hl s"><span>Pequenas mudanças geram grandes resultados.</span></div>
                    ${lns(90, 60, 80)}
                </div>
                <div class="col" style="flex:1">
                    <div class="bl d6-n"><div class="t">Pequenas mudanças…</div><div style="margin:.25rem 0"><span class="tg">#livro</span><span class="tg">#hábitos</span></div>${lns(90, 65)}</div>
                    <div class="d6-r"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Revisar em 7 dias</div>
                </div>
            </div>`,

        carteira: () => `
            <div class="wfd-in">
                <div class="bl d7-l"><div class="d2-h"><span class="t">Ganhos por mês</span><span class="s">R$</span></div>
                    <div class="d7-b">${[38, 52, 44, 66, 58, 90].map((h, i) => `<i style="--h:${h}%;--i:${i}"></i>`).join('')}</div>
                    <div class="d7-m">${['Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out'].map(m => `<span>${m}</span>`).join('')}</div>
                </div>
                <div class="bl" style="flex:1"><div class="s">Saldo</div><div class="d7-v">R$ 4.820</div>
                    <div class="d7-t g" style="--i:0"><span>Venda</span><span>+ R$ 250</span></div>
                    <div class="d7-t r" style="--i:1"><span>Mercado</span><span>− R$ 80</span></div>
                </div>
            </div>`,

        diverso: () => `
            <div class="wfd-in d8">
                <div class="d8-tabs"><span class="d8-tab on">Ideias</span><span class="d8-tab">Mapa RPG</span><span class="d8-tab d8-nova">Viagem</span><span class="d8-mais">+</span></div>
                <div class="mp"><div class="fm"></div><div class="pc"></div><div class="tk">${CUR}</div></div>
            </div>`,

        biblioteca: () => `
            <div class="wfd-in">
                <div class="d9-b">
                    <div class="bl"><div class="d2-h"><span class="t">Treino A</span>${MARCADOR}</div>${lns(85, 60, 75)}</div>
                    <div class="d9-slot"><span class="s" style="font-size:.58rem">Corpo · quadro</span>
                        <div class="bl d9-c" style="padding:.3rem .45rem"><div class="t">Treino A</div>${lns(80, 55)}</div></div>
                </div>
                <div class="bl d9-l"><div class="t">Biblioteca</div>
                    <div class="d9-i"><span class="s">Tabela de gastos</span></div>
                    <div class="d9-i d9-n"><span class="t">Treino A</span></div>
                    <div class="d9-bt">Colar aqui${CUR}</div>
                </div>
            </div>`,

        workflows: () => `
            <div class="wfd-in">
                <div class="bl d10-l"><div class="t" style="margin-bottom:.2rem">Áreas</div>
                    ${['Corpo', 'Mente', 'Carteira', 'Diverso'].map((n, i) => `<div class="d10-k" style="--i:${i}"><i class="ck v"></i><span>${n}</span></div>`).join('')}
                </div>
                <div class="d10-mid"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></div>
                <div class="bl d10-l"><div class="d10-av"></div><div class="t" style="margin-bottom:.1rem">Outra conta</div>
                    ${['Corpo', 'Mente', 'Carteira', 'Diverso'].map(n => `<div class="d10-g"><i class="ck"></i><span class="s">${n}</span></div>`).join('')}
                </div>
                <div class="fl">${ARQUIVO}workflow.json</div>
            </div>`,
    };

    const PASSOS = [
        {
            t: 'Bem-vindo ao Work Flower', v: 'inicio',
            x: 'Sua vida em quatro áreas, num só lugar. Setas do teclado avançam e <b>Esc</b> sai.',
        },
        {
            t: 'Tarefas gerais', v: 'tarefas', alvo: '#janela-tarefas',
            x: 'Todas as pendências juntas, com o prazo mais próximo primeiro.',
            p: [
                'Conclua, exclua ou clique no título para ir à área.',
                'No computador, arraste o cabeçalho para mover o cartão (clique duplo devolve).',
            ],
        },
        {
            t: 'As áreas', v: 'areas', alvo: '[data-atualiza="painel-areas"]',
            x: 'Cada cartão abre uma área e mostra quantas tarefas já foram concluídas.',
        },
        {
            t: 'Como os quadros funcionam', v: 'quadros',
            x: 'Toda área tem um quadro livre, feito de blocos.',
            campos: [
                ['Arrastar e redimensionar', 'Mova e puxe os cantos. Fica salvo.'],
                ['Tipos de bloco', 'Texto, tabela, lista, imagem, mapa mental.'],
                ['Tela cheia e download', 'Em cada bloco. Plano grátis tem limites.'],
                ['Excluir sem medo', '“Desfazer” na hora; depois, no Histórico.'],
            ],
            p: ['Restaurar: engrenagem → <b>Histórico</b> → <b>Restaurar</b>.'],
        },
        {
            t: 'Corpo', v: 'corpo', area: 'Corpo',
            x: 'Treino, dieta e hábitos de saúde.',
            campos: [
                ['Treinos', 'Por dia, com séries e carga.'],
                ['Cargas', 'Evolução de cada exercício.'],
                ['Dietas', 'Refeições com horário.'],
                ['Água', 'Meta diária e registros em ml.'],
                ['Calorias', 'Macros contra suas metas.'],
                ['Metas', 'Objetivos da área.'],
                ['Mapa mental e Quadro livre', 'Ideias e blocos soltos.'],
            ],
        },
        {
            t: 'Mente', v: 'mente', area: 'Mente',
            x: 'Leitura, estudo e anotações.',
            campos: [
                ['Livros', 'PDF no app, com destaques viram notas.'],
                ['Estudos', 'Aviso de revisão após 7 dias.'],
                ['Anotações', 'Tags, cores e fixação.'],
                ['Faculdade', 'Horários, provas e notas.'],
                ['Mapa mental e Quadro livre', 'Inclui o bloco Documento (currículo, contrato…).'],
            ],
        },
        {
            t: 'Carteira', v: 'carteira', area: 'Carteira',
            x: 'Do dinheiro pessoal ao seu negócio, com gráficos e totais automáticos.',
            campos: [
                ['Carteira', 'Contas, ganhos e gastos.'],
                ['Investimentos', 'Ações, FIIs, renda fixa, cripto.'],
                ['Negócios', 'Vendas, custos e canais.'],
                ['Trabalho', 'Colunas: A fazer, Em andamento, Concluído.'],
                ['Documentos', 'Documentos com layout próprio.'],
                ['Mapa mental e Quadro livre', 'Para planejar o resto.'],
            ],
        },
        {
            t: 'Diverso', v: 'diverso', area: 'Diverso',
            x: 'A área em branco: até 12 abas suas, cada uma um quadro.',
            campos: [
                ['Abas suas', 'Nome e ícone à escolha.'],
                ['Mapa mental', 'Desenho livre e borracha.'],
                ['GitHub / VS Code', 'Editor de arquivos e terminal.'],
                ['Tabletop', 'Mapa, peças, pincel e formas.'],
            ],
        },
        {
            t: 'Biblioteca', v: 'biblioteca', alvo: ENGRENAGEM,
            x: 'Guarde um bloco e reaproveite em qualquer área.',
            p: [
                '<b>Salvar</b>: ícone de marcador no cabeçalho do bloco.',
                '<b>Usar</b>: engrenagem → <b>Biblioteca</b> → <b>Colar aqui</b>, dentro de Corpo, Mente, Carteira ou Diverso.',
                'O plano gratuito limita o tamanho da biblioteca.',
            ],
            acoes: [{ r: 'Abrir a Biblioteca', ev: 'abrir-biblioteca' }],
        },
        {
            t: 'Importar e exportar workflows', v: 'workflows', alvo: ENGRENAGEM,
            x: 'Backup ou mudança de conta, tudo em um arquivo.',
            p: [
                '<b>Baixar</b> tudo ou só as áreas marcadas.',
                '<b>Importar</b>: escolha o arquivo e as áreas.',
                'Atenção: importar <b>substitui</b> o conteúdo das áreas marcadas.',
                'Importe só arquivos de confiança. O plano gratuito tem limites.',
            ],
            acoes: [{ r: 'Abrir Importar / exportar', ev: 'abrir-workflows' }],
        },
    ];

    /* ---------- estado e elementos ---------- */
    let i = 0, aberto = false, alvoAtual = null, voltarFoco = null, quadro = 0;
    let raiz, furo, caixa, cab, corpo, rodape;

    const el = (tag, cls, txt) => {
        const e = document.createElement(tag);
        if (cls) e.className = cls;
        if (txt != null) e.textContent = txt;
        return e;
    };
    /* Só <b> é permitido nos textos; o resto vira texto puro */
    function comNegrito(alvo, html) {
        String(html).split(/(<b>.*?<\/b>)/g).forEach(parte => {
            const m = parte.match(/^<b>(.*)<\/b>$/);
            if (m) alvo.appendChild(el('b', '', m[1])); else if (parte) alvo.appendChild(document.createTextNode(parte));
        });
    }
    const reduzido = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const marcarVisto = () => { try { localStorage.setItem(CHAVE, '1'); } catch (e) {} document.getElementById('wft-botao')?.classList.remove('novo'); };

    function linkDaArea(nome) {
        const links = document.querySelectorAll('[data-atualiza="painel-areas"] a.card');
        for (const a of links) {
            if ((a.querySelector('h2')?.textContent || '').trim().toLowerCase() === nome.toLowerCase()) return a.href;
        }
        return null;
    }

    function montar() {
        raiz = el('div', 'wft-raiz');
        raiz.hidden = true;
        raiz.appendChild(el('div', 'wft-fundo'));
        furo = el('div', 'wft-furo vazio');
        furo.setAttribute('aria-hidden', 'true');
        raiz.appendChild(furo);

        caixa = el('div', 'wft-caixa centro');
        caixa.setAttribute('role', 'dialog');
        caixa.setAttribute('aria-modal', 'true');
        caixa.setAttribute('aria-labelledby', 'wft-titulo');
        caixa.tabIndex = -1;

        cab = el('div', 'wft-cab');
        corpo = el('div', 'wft-corpo');
        rodape = el('div', 'wft-rodape');
        caixa.append(cab, corpo, rodape);
        raiz.appendChild(caixa);
        document.body.appendChild(raiz);

        raiz.addEventListener('keydown', teclado);
        window.addEventListener('resize', agendar, { passive: true });
        window.addEventListener('scroll', agendar, { passive: true, capture: true });
    }

    /* ---------- desenho do passo ---------- */
    function desenhar() {
        const p = PASSOS[i];

        cab.replaceChildren();
        const titulos = el('div'); titulos.style.cssText = 'flex:1;min-width:0';
        titulos.appendChild(el('p', 'wft-passo', `Passo ${i + 1} de ${PASSOS.length}`));
        const h = el('h2', 'titulo', p.t); h.id = 'wft-titulo'; titulos.appendChild(h);
        const fechar = el('button', 'mini-btn'); fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar o tutorial'); fechar.title = 'Fechar (Esc)';
        fechar.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
        fechar.addEventListener('click', fecharTutorial);
        cab.append(titulos, fechar);

        corpo.replaceChildren();
        if (p.v && DEMOS[p.v]) { // mini-animação decorativa; o texto abaixo descreve o mesmo conteúdo
            const d = el('div', 'wfd'); d.setAttribute('aria-hidden', 'true'); d.innerHTML = DEMOS[p.v](); corpo.appendChild(d);
        }
        const txt = el('p'); comNegrito(txt, p.x); corpo.appendChild(txt);

        if (p.campos) {
            const g = el('div', 'wft-campos');
            p.campos.forEach(([nome, desc], k) => {
                const c = el('div', 'wft-campo' + (p.campos.length % 2 && k === p.campos.length - 1 ? ' largo' : ''));
                c.appendChild(el('b', '', nome)); c.appendChild(el('span', '', desc));
                g.appendChild(c);
            });
            corpo.appendChild(g);
        }
        if (p.p) {
            const ul = el('ul', 'wft-pontos');
            p.p.forEach(t => { const li = el('li'); comNegrito(li, t); ul.appendChild(li); });
            corpo.appendChild(ul);
        }

        const acoes = el('div', 'wft-acoes');
        if (p.area) {
            const href = linkDaArea(p.area);
            if (href) { const a = el('a', 'btn-sec', `Abrir ${p.area}`); a.href = href; acoes.appendChild(a); }
        }
        (p.acoes || []).forEach(ac => {
            const b = el('button', 'btn-sec', ac.r); b.type = 'button';
            b.addEventListener('click', () => { fecharTutorial(); setTimeout(() => window.dispatchEvent(new CustomEvent(ac.ev)), 0); });
            acoes.appendChild(b);
        });
        if (acoes.children.length) corpo.appendChild(acoes);
        corpo.scrollTop = 0;

        rodape.replaceChildren();
        const pts = el('div', 'wft-pontinhos');
        PASSOS.forEach((s, k) => {
            const b = el('button', 'wft-pontinho' + (k === i ? ' ativo' : '')); b.type = 'button';
            b.setAttribute('aria-label', `Ir para o passo ${k + 1}: ${s.t}`);
            if (k === i) b.setAttribute('aria-current', 'step');
            b.addEventListener('click', () => ir(k));
            pts.appendChild(b);
        });
        const nav = el('div', 'wft-nav');
        const ant = el('button', 'btn-sec', 'Anterior'); ant.type = 'button'; ant.disabled = i === 0;
        ant.addEventListener('click', () => ir(i - 1));
        const ultimo = i === PASSOS.length - 1;
        const prox = el('button', 'btn', ultimo ? 'Concluir' : 'Próximo'); prox.type = 'button';
        prox.addEventListener('click', () => ultimo ? fecharTutorial() : ir(i + 1));
        nav.append(ant, prox);
        rodape.append(pts, nav);

        // destaque do elemento do passo (se existir e estiver visível); senão, caixa no centro
        alvoAtual = null;
        if (p.alvo) {
            const e = document.querySelector(p.alvo);
            if (e && e.getClientRects().length) {
                alvoAtual = e;
                e.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
            }
        }
        posicionar();
        prox.focus({ preventScroll: true });
    }

    function posicionar() {
        quadro = 0;
        if (!aberto) return;
        if (alvoAtual && alvoAtual.isConnected && alvoAtual.getClientRects().length) {
            const r = alvoAtual.getBoundingClientRect(), m = 8;
            furo.classList.remove('vazio');
            furo.style.left = (r.left - m) + 'px'; furo.style.top = (r.top - m) + 'px';
            furo.style.width = (r.width + m * 2) + 'px'; furo.style.height = (r.height + m * 2) + 'px';
            // a caixa fica do lado da tela que não cobre o destaque
            const meio = r.top + r.height / 2;
            caixa.className = 'wft-caixa ' + (meio > window.innerHeight / 2 ? 'topo' : 'baixo');
        } else {
            furo.classList.add('vazio');
            furo.style.left = '50vw'; furo.style.top = '50vh'; furo.style.width = '0px'; furo.style.height = '0px';
            caixa.className = 'wft-caixa centro';
        }
    }
    function agendar() { if (aberto && !quadro) quadro = requestAnimationFrame(posicionar); }

    /* ---------- navegação ---------- */
    function ir(n) {
        if (n < 0 || n >= PASSOS.length) return;
        i = n; desenhar();
    }
    function abrir(inicio) {
        if (aberto) return;
        if (!raiz) montar();
        voltarFoco = document.activeElement;
        aberto = true; i = Math.max(0, Math.min(inicio || 0, PASSOS.length - 1));
        raiz.hidden = false;
        marcarVisto();
        desenhar();
    }
    function fecharTutorial() {
        if (!aberto) return;
        aberto = false; raiz.hidden = true; alvoAtual = null;
        if (quadro) { cancelAnimationFrame(quadro); quadro = 0; }
        if (voltarFoco && voltarFoco.isConnected) voltarFoco.focus({ preventScroll: true });
    }
    function teclado(e) {
        if (e.key === 'Escape') { e.stopPropagation(); fecharTutorial(); return; }
        if (e.key === 'ArrowRight') { e.preventDefault(); ir(i + 1); return; }
        if (e.key === 'ArrowLeft') { e.preventDefault(); ir(i - 1); return; }
        if (e.key === 'Tab') { // o foco fica preso dentro da caixa
            const itens = [...caixa.querySelectorAll('button:not([disabled]), a[href]')];
            if (!itens.length) return;
            const primeiro = itens[0], ultimo = itens[itens.length - 1];
            if (e.shiftKey && (document.activeElement === primeiro || document.activeElement === caixa)) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
        }
    }

    /* ---------- início ---------- */
    document.getElementById('wft-botao').addEventListener('click', () => abrir(0));
    window.addEventListener('abrir-tutorial', () => abrir(0));
    window.WFTutorial = { abrir, fechar: fecharTutorial };

    let visto = false;
    try { visto = localStorage.getItem(CHAVE) === '1'; } catch (e) {}
    if (!visto) document.getElementById('wft-botao').classList.add('novo');
})();
</script>
