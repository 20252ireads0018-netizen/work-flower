{{-- Cartões "Gráfico" e "Hábitos": estilo e script (public/js/graficos.js). Incluído uma vez em cada área,
     logo depois do quadro.js e antes da página da área (o Alpine do layout usa defer). --}}
<style>
    .gr-area { display: flex; align-items: center; justify-content: center; }
    .gr-svg { width: 100%; height: 100%; max-height: 100%; display: block; }
    .gr-svg text { font-family: 'DM Sans', sans-serif; fill: var(--tinta); }
    .gr-svg .gr-eixo { font-size: 10px; fill: var(--tinta-2); }
    .gr-svg .gr-leg { font-size: 11px; }
    .gr-svg .gr-total { font-size: 18px; font-weight: 700; }
    .gr-svg .gr-vazio { font-size: 12px; fill: var(--tinta-2); }
    .gr-svg .gr-grade { stroke: var(--linha); stroke-width: 1; }
    .gr-svg .gr-grade.zero { stroke: var(--tinta-2); }
    .gr-svg .gr-barra:hover, .gr-svg .gr-fatia:hover, .gr-svg .gr-ponto:hover { opacity: .8; }
    .gr-svg .gr-tit-eixo { font-size: 10.5px; font-weight: 600; fill: var(--tinta-2); }
    .gr-svg .gr-meta { stroke: #ef4444; stroke-width: 1.5; stroke-dasharray: 5 4; }
    .gr-svg .gr-meta-txt { font-size: 10px; font-weight: 600; fill: #ef4444; }
    .gr-svg .gr-area-fill { opacity: .22; }
    .gr-meta-chip { align-self: flex-start; padding: .05rem .5rem; border-radius: 999px; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.5); color: var(--tinta); }
    .gr-meta-chip.ok { background: rgba(34,197,94,.14); border-color: rgba(34,197,94,.55); }
    /* grade de campos que se ajusta à largura do cartão (de celular a tela larga) */
    .cx-grade { display: grid; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); gap: .4rem .6rem; }
    .gr-tab { border-collapse: collapse; font-size: .8rem; }
    .gr-tab th, .gr-tab td { border-bottom: 1px solid var(--linha); padding: .1rem .2rem; }
    .gr-tab th .wf-cel { font-weight: 600; }
    .gr-tab .wf-cel, .hb-tab .wf-cel { width: 100%; min-width: 4.5rem; background: transparent; color: var(--tinta); border: 0; padding: .3rem .35rem; font-size: .8rem; border-radius: .375rem; }
    .gr-tab .wf-cel:focus, .hb-tab .wf-cel:focus { outline: none; background: var(--superficie-2); box-shadow: 0 0 0 2px var(--prim-linha, var(--prim)); }
    .hb-tab { border-collapse: separate; border-spacing: 2px; font-size: .7rem; }
    .hb-tab th { font-weight: 600; color: var(--tinta-2); line-height: 1.1; text-align: center; min-width: 1.35rem; }
    .hb-tab th.hoje { color: var(--prim); }
    .hb-tab .hb-nome { text-align: left; min-width: 9rem; width: 9rem; max-width: 9rem; position: sticky; left: 0; background: var(--superficie); z-index: 1; }
    @media (min-width: 768px) { .hb-tab .hb-nome { min-width: 13rem; width: 13rem; max-width: 13rem; } }
    @media (min-width: 1600px) { .hb-tab .hb-nome { min-width: 16rem; width: 16rem; max-width: 16rem; } }
    /* nome longo: até duas linhas, o resto vai para a dica (title) */
    .hb-nome-txt { flex: 1; min-width: 0; text-align: left; font-size: .78rem; line-height: 1.15; color: var(--tinta); background: none; border: 0; padding: .15rem .2rem; border-radius: .3rem; cursor: text; }
    .hb-nome-txt > span:first-child { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
    .hb-nome-txt:hover { background: var(--superficie-2); }
    .hb-icones { display: block; font-size: .62rem; line-height: 1.2; color: var(--tinta-2); margin-top: .1rem; } .hb-icones > span { margin-right: .3rem; }
    .hb-ok { color: #16a34a !important; font-weight: 600; }
    .hb-mapa { display: grid; grid-template-columns: repeat(7, minmax(1.6rem, 2.6rem)); gap: 3px; }
    .hb-mapa-cab { font-size: .65rem; text-align: center; color: var(--tinta-2); font-weight: 600; }
    .hb-mapa-dia { aspect-ratio: 1; border-radius: .35rem; display: flex; align-items: center; justify-content: center; font-size: .65rem; background: var(--superficie-2); border: 1px solid var(--linha); color: var(--tinta-2); }
    .hb-mapa-dia.vazio { background: transparent; border-color: transparent; }
    .hb-mapa-dia.n-1 { opacity: .4; }
    .hb-mapa-dia.n1 { background: rgba(47,111,199,.22); } .hb-mapa-dia.n2 { background: rgba(47,111,199,.45); color: var(--tinta); }
    .hb-mapa-dia.n3 { background: rgba(47,111,199,.7); color: #fff; } .hb-mapa-dia.n4 { background: #2f6fc7; color: #fff; border-color: #2f6fc7; }
    .hb-mapa-dia.hoje { box-shadow: 0 0 0 2px var(--prim); }
    .hb-mapa-dia.hb-leg { width: .85rem; height: .85rem; display: inline-block; }
    .hb-selo { background: rgba(242,194,48,.18); border-color: rgba(242,194,48,.6); }
    .hb-tab td.fds .hb-dia:not(.feito) { opacity: .7; }
    .hb-tab td.hoje .hb-dia { box-shadow: 0 0 0 1.5px var(--prim); }
    .hb-dia { display: block; width: 1.3rem; height: 1.3rem; border-radius: .3rem; background: var(--superficie-2); border: 1px solid var(--linha); cursor: pointer; padding: 0; }
    .hb-dia.feito { background: var(--c, var(--prim)); border-color: var(--c, var(--prim)); }
    .hb-dia:disabled { opacity: .3; cursor: default; }
    .hb-dia.hb-pulado { opacity: .18; background: repeating-linear-gradient(135deg, transparent 0 3px, var(--linha) 3px 4px); cursor: not-allowed; }
    .hb-dia:focus-visible { outline: 2px solid var(--tinta); outline-offset: 1px; }
    .hb-num { text-align: right; font-weight: 600; padding: 0 .25rem; font-size: .75rem; white-space: nowrap; }
    .hb-cor { width: .85rem; height: .85rem; border-radius: 9999px; flex-shrink: 0; cursor: pointer; border: 0; padding: 0; }

    /* Editor de PDF em tela cheia (js/editor-pdf.js) */
    html.pe-aberto { overflow: hidden; }
    .pe-raiz { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; background: #3b3f45; color: #f1f1f1; font-size: 14px; }
    .pe-raiz button, .pe-btn { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .55rem; font-size: .8rem; line-height: 1.2; border-radius: .4rem; border: 1px solid #4b5058; background: #33373d; color: #f1f1f1; cursor: pointer; white-space: nowrap; }
    .pe-raiz button:hover:not(:disabled), .pe-btn:hover { border-color: #8ab4f8; }
    .pe-raiz button:disabled { opacity: .4; cursor: default; }
    .pe-raiz button.on { background: #2f6fc7; border-color: #8ab4f8; }
    .pe-raiz .pe-ok { background: #2f6fc7; border-color: #2f6fc7; font-weight: 600; }
    .pe-raiz input[type=color] { width: 1.7rem; height: 1.4rem; padding: 0; border: 1px solid #4b5058; border-radius: .25rem; background: none; cursor: pointer; }
    .pe-raiz input[type=color]:disabled { opacity: .35; }
    .pe-raiz input[type=number], .pe-raiz select { background: #1c1f22; color: #f1f1f1; border: 1px solid #4b5058; border-radius: .3rem; padding: .2rem .35rem; font-size: .8rem; }
    .pe-raiz input[type=number] { width: 4rem; }
    .pe-topo { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .45rem .75rem; background: #1b1d20; border-bottom: 1px solid #000; }
    .pe-raiz .pe-sair { background: #4a2a2d; border-color: #7f3a40; font-weight: 600; }
    .pe-raiz .pe-sair:hover { background: #6b2f35; }
    .pe-tit { max-width: 22rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pe-salvo { font-size: .75rem; color: #b8bcc2; }
    .pe-salvo.ok { color: #86efac; } .pe-salvo.aviso { color: #fcd34d; } .pe-salvo.erro { color: #fca5a5; }
    .pe-chk { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; color: #d6d9dd; cursor: pointer; white-space: nowrap; }
    .pe-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem .9rem; padding: .4rem .75rem; background: #24272b; border-bottom: 1px solid #000; }
    .pe-grupo { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; }
    .pe-rot { font-size: .65rem; text-transform: uppercase; letter-spacing: .04em; color: #8b9097; margin-right: .15rem; }
    .pe-ic { font-size: .95rem; line-height: 1; min-width: 1em; text-align: center; }
    .pe-props { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; padding: .35rem .75rem; min-height: 2.4rem; background: #2c2f34; border-bottom: 1px solid #1b1d20; }
    .pe-dim { font-size: .75rem; color: #b8bcc2; }
    .pe-sep { width: 1px; height: 1.4rem; background: #4b5058; margin: 0 .15rem; }
    .pe-esp { flex: 1; }
    .pe-raiz .pe-amostra { width: 1.2rem; height: 1.2rem; padding: 0; border-radius: 999px; border: 2px solid #4b5058; }
    .pe-raiz .pe-amostra.on { border-color: #fff; box-shadow: 0 0 0 2px #2f6fc7; }
    .pe-corpo { flex: 1; min-height: 0; display: flex; position: relative; }
    .pe-lado { width: 180px; flex-shrink: 0; display: flex; flex-direction: column; background: #2a2d31; border-right: 1px solid #1b1d20; }
    .pe-raiz.sem-lado .pe-lado { display: none; }
    .pe-lado-topo { display: flex; align-items: center; gap: .3rem; padding: .45rem .55rem .25rem; font-size: .8rem; }
    .pe-npag { color: #8b9097; font-size: .75rem; }
    .pe-raiz .pe-mini-bt { padding: .1rem .4rem; }
    .pe-lado-acoes { display: flex; flex-wrap: wrap; gap: .15rem; padding: .2rem .5rem; }
    .pe-lado-acoes button, .pe-lado-acoes .pe-btn { padding: .2rem .4rem; font-size: .75rem; }
    .pe-minis { flex: 1; overflow-y: auto; padding: .5rem 0 1rem; display: flex; flex-direction: column; align-items: center; gap: .65rem; }
    .pe-mini { position: relative; width: 108px; flex-shrink: 0; cursor: pointer; border-radius: 3px; outline: 2px solid transparent; outline-offset: 2px; }
    .pe-mini.atual { outline-color: #2f6fc7; }
    .pe-mini.arrastando { opacity: .4; }
    .pe-mini.antes::before, .pe-mini.depois::after { content: ''; position: absolute; left: -6px; right: -6px; height: 3px; background: #8ab4f8; border-radius: 2px; }
    .pe-mini.antes::before { top: -6px; } .pe-mini.depois::after { bottom: -6px; }
    .pe-mini-cv { display: block; width: 108px; height: var(--mh, 140px); background: var(--mf, #fff); box-shadow: 0 2px 6px rgba(0,0,0,.4); }
    .pe-mini-n { position: absolute; left: 3px; bottom: 3px; font-size: .65rem; padding: 0 .3rem; border-radius: .25rem; background: rgba(0,0,0,.6); color: #fff; }
    .pe-mini-ac { position: absolute; top: 3px; right: 3px; display: none; gap: 2px; }
    .pe-mini:hover .pe-mini-ac, .pe-mini.atual .pe-mini-ac { display: flex; }
    .pe-raiz .pe-mini-ac button { padding: 0 .3rem; font-size: .7rem; background: rgba(27,29,32,.9); }
    .pe-raiz .pe-lado-abrir { display: none; position: absolute; left: .5rem; top: .5rem; z-index: 3; }
    .pe-raiz.sem-lado .pe-lado-abrir { display: inline-flex; }
    .pe-paginas { flex: 1; min-width: 0; overflow: auto; display: flex; flex-direction: column; align-items: flex-start; gap: 1.25rem; padding: 1.25rem 2.5rem 3rem; user-select: none; outline: none; }
    .pe-carregando { margin: auto; color: #d6d9dd; font-size: .9rem; max-width: 30rem; text-align: center; }
    .pe-pg { position: relative; flex-shrink: 0; margin: 0 auto; box-shadow: 0 6px 20px rgba(0,0,0,.45); outline: 3px solid transparent; }
    .pe-pg.atual { outline-color: rgba(47,111,199,.7); }
    .pe-pg.vazia:not(.branca)::after { content: 'Carregando a página…'; position: absolute; inset: 0; display: grid; place-items: center; color: #9aa0a6; font-size: .8rem; pointer-events: none; }
    .pe-cv { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
    .pe-camada { position: absolute; inset: 0; overflow: hidden; }
    .pe-raiz:not([data-ferramenta="sel"]) .pe-camada { cursor: crosshair; touch-action: none; }
    .pe-raiz[data-ferramenta="texto"] .pe-camada { cursor: text; }
    .pe-num { position: absolute; right: -2.1rem; top: 0; font-size: .7rem; color: #b8bcc2; }
    .pe-item { position: absolute; cursor: move; box-sizing: border-box; outline: 1px dashed transparent; touch-action: none; }
    .pe-raiz[data-ferramenta="sel"] .pe-item:hover, .pe-item.sel { outline-color: #2f6fc7; }
    .pe-item.sel { outline: 1.5px solid #2f6fc7; }
    .pe-txt { width: 100%; height: 100%; box-sizing: border-box; white-space: pre-wrap; overflow-wrap: anywhere; overflow: hidden; }
    .pe-texto.editando { cursor: text; overflow: visible; outline: 2px solid #2f6fc7; }
    .pe-ta { display: block; width: 100%; height: 100%; margin: 0; border: 0; outline: none; resize: none; overflow: hidden; background: transparent; box-sizing: border-box; white-space: pre-wrap; overflow-wrap: anywhere; user-select: text; }
    .pe-marca { mix-blend-mode: multiply; opacity: .4; }
    .pe-img img { width: 100%; height: 100%; display: block; pointer-events: none; }
    .pe-linha svg { display: block; overflow: visible; pointer-events: none; }
    .pe-alca { position: absolute; width: 10px; height: 10px; margin: -5px 0 0 -5px; background: #fff; border: 1.5px solid #2f6fc7; border-radius: 2px; z-index: 2; }
    .pe-h-nw { left: 0; top: 0; cursor: nwse-resize; } .pe-h-n { left: 50%; top: 0; cursor: ns-resize; } .pe-h-ne { left: 100%; top: 0; cursor: nesw-resize; }
    .pe-h-e { left: 100%; top: 50%; cursor: ew-resize; } .pe-h-se { left: 100%; top: 100%; cursor: nwse-resize; } .pe-h-s { left: 50%; top: 100%; cursor: ns-resize; }
    .pe-h-sw { left: 0; top: 100%; cursor: nesw-resize; } .pe-h-w { left: 0; top: 50%; cursor: ew-resize; }
    .pe-h-p1, .pe-h-p2 { border-radius: 999px; cursor: move; }
    .pe-fantasma { position: absolute; border: 1.5px dashed #2f6fc7; background: rgba(47,111,199,.08); pointer-events: none; }
    .pe-fantasma.marca { background: rgba(242,194,48,.4); border-color: #f2c230; }
    .pe-rodape { display: flex; gap: 1rem; padding: .25rem .75rem; font-size: .72rem; color: #b8bcc2; background: #1b1d20; border-top: 1px solid #000; }
    .pe-st-dica { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pe-modal-fundo { position: absolute; inset: 0; z-index: 10; background: rgba(0,0,0,.55); display: grid; place-items: center; padding: 1rem; }
    .pe-modal { width: min(680px, 100%); background: #24272b; border: 1px solid #4b5058; border-radius: .75rem; padding: 1rem 1.1rem; box-shadow: 0 20px 50px rgba(0,0,0,.5); display: flex; flex-direction: column; gap: .6rem; }
    .pe-modal h3 { font-size: 1rem; font-weight: 600; margin: 0; }
    .pe-modal p { margin: 0; font-size: .85rem; }
    .pe-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .pe-ass { width: 100%; height: 220px; background: #fff; border-radius: .5rem; touch-action: none; cursor: crosshair; }
    .pe-barra-pr, .pe-cartao-pr { height: 8px; border-radius: 999px; background: #3b3f45; overflow: hidden; }
    .pe-barra-pr span, .pe-cartao-pr span { display: block; height: 100%; width: 0; background: #2f6fc7; transition: width .2s; }
    .pe-cartao-pr { margin: .4rem auto 0; max-width: 16rem; background: var(--linha); }
    .pe-cartao-pr span { background: var(--prim, #2f6fc7); }
    .pe-barra-pr.indef span, .pe-cartao-pr.indef span { width: 35%; animation: pe-vai 1.1s ease-in-out infinite; }
    @keyframes pe-vai { from { transform: translateX(-100%); } to { transform: translateX(290%); } }
    .pe-aviso { position: absolute; left: 50%; bottom: 2.5rem; transform: translate(-50%, 1rem); z-index: 11; max-width: min(36rem, 90vw); padding: .55rem .9rem; border-radius: .5rem; background: #111; color: #fff; font-size: .82rem; box-shadow: 0 8px 24px rgba(0,0,0,.5); opacity: 0; pointer-events: none; transition: opacity .2s, transform .2s; }
    .pe-aviso.on { opacity: 1; transform: translate(-50%, 0); }
    @media (max-width: 1100px) { .pe-barra .pe-tx { display: none; } }
    @media (max-width: 700px) { .pe-lado { position: absolute; left: 0; top: 0; bottom: 0; z-index: 4; box-shadow: 4px 0 16px rgba(0,0,0,.5); } .pe-paginas { padding: .75rem; } .pe-topo [data-a="salvar"] .pe-tx, .pe-tit { display: none; } .pe-chk { font-size: .7rem; } .pe-num { display: none; } }
    /* Questões, Criador e RH (js/cartoes-extra.js) */
    .qz-on { background: var(--prim) !important; color: #fff !important; border-color: var(--prim) !important; }
    .qz-alt { text-align: left; padding: .45rem .6rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); color: var(--tinta); }
    .qz-alt:hover { border-color: var(--prim); }
    .qz-alt.qz-certa { border-color: #22c55e; background: rgba(34,197,94,.15); }
    .qz-alt.qz-errada { border-color: #ef4444; background: rgba(239,68,68,.15); }
    .qz-alt.qz-apagada { opacity: .55; }
    .qz-res { font-size: .8rem; padding: .45rem .6rem; border-radius: .5rem; }
    .qz-res.ok { background: rgba(34,197,94,.12); } .qz-res.erro { background: rgba(239,68,68,.12); }
    .qz-chip { padding: .1rem .45rem; border-radius: 999px; background: var(--superficie-2); border: 1px solid var(--linha); }
    .qz-ed { display: flex; flex-direction: column; gap: .35rem; padding: .5rem; border: 1px solid var(--linha); border-radius: .6rem; }
    .cx-col { flex: 0 0 9.5rem; display: flex; flex-direction: column; gap: .35rem; padding: .35rem; border-radius: .6rem; background: var(--superficie-2); min-height: 100%; }
    .cx-col-tit { font-size: .72rem; font-weight: 600; padding: 0 .15rem; }
    .cx-card { padding: .4rem .5rem; border-radius: .5rem; background: var(--superficie); border: 1px solid var(--linha); cursor: pointer; font-size: .8rem; }
    .cx-card:hover { border-color: var(--prim); }
    .rh-estrela { font-size: 1.05rem; color: var(--linha); line-height: 1; } .rh-estrela.on { color: #f2c230; }
    .rh-crit { min-width: 9rem; flex: 1; }
    .rh-msg { padding: .4rem .5rem; border-radius: .5rem; background: var(--superficie-2); }
    .rh-agenda { color: #7c3aed; } html.dark .rh-agenda { color: #c4b5fd; }
    .rh-barra { height: .35rem; border-radius: 999px; background: var(--superficie-2); overflow: hidden; }
    .rh-barra > span { display: block; height: 100%; background: #22c55e; transition: width .4s ease; }
    .rh-funil { display: grid; grid-template-columns: 5.5rem 1fr 4.5rem; align-items: center; gap: .4rem; }
    .rh-funil-barra { height: .9rem; border-radius: .3rem; background: var(--superficie-2); overflow: hidden; }
    .rh-funil-barra > span { display: block; height: 100%; background: #a78bfa; border-radius: .3rem; transition: width .5s ease; }
    .rh-funil-num { text-align: right; font-weight: 600; }
    /* Criador: editor do post */
    .cr-campo { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
    .cr-rot { font-size: .72rem; font-weight: 600; color: var(--tinta); }
    .cr-editor .campo::placeholder { color: var(--tinta-2); opacity: .75; }
    /* Questões: banco */
    .qz-2linhas { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .qz-banco-item.sel { border-color: var(--prim); background: rgba(47,111,199,.07); }
    .qz-rodape-banco { position: sticky; bottom: 0; display: flex; align-items: center; gap: .5rem; padding: .45rem .6rem; border-radius: .6rem; background: var(--superficie); border: 1px solid var(--prim); box-shadow: 0 -4px 12px rgba(0,0,0,.08); }
    .qz-sim { font-size: .78rem; padding: .35rem .55rem; border-radius: .5rem; background: rgba(47,111,199,.1); }
    .qz-sim.fim { background: rgba(34,197,94,.14); }
</style>
@php
    // endereços usados pelos cartões (lembrete dos hábitos e entrevistas no calendário, tarefas da área, banco de questões)
    $wfCartoesUrls = [
        'cal'     => \Illuminate\Support\Facades\Route::has('calendario.criar') ? url('/calendario/eventos') : '',
        'tarefas' => \Illuminate\Support\Facades\Route::has('cartoes.tarefas') ? url('/cartoes/tarefas') : '',
        'banco'   => \Illuminate\Support\Facades\Route::has('cartoes.questoes') ? route('cartoes.questoes') : '',
    ];
@endphp
<script src="{{ asset('js/graficos.js') }}?v={{ @filemtime(public_path('js/graficos.js')) }}" data-cal="{{ $wfCartoesUrls['cal'] }}" data-tarefas="{{ $wfCartoesUrls['tarefas'] }}"></script>
{{-- cartão "Editor de PDF" (estende o quadro.js do mesmo jeito) --}}
<script src="{{ asset('js/editor-pdf.js') }}?v={{ @filemtime(public_path('js/editor-pdf.js')) }}"></script>
{{-- cartões Questões, Criador e RH (usa o wfNovoCartao do graficos.js) --}}
<script src="{{ asset('js/cartoes-extra.js') }}?v={{ @filemtime(public_path('js/cartoes-extra.js')) }}" data-yt="{{ \Illuminate\Support\Facades\Route::has('youtube.canal') ? route('youtube.canal') : '' }}" data-banco="{{ $wfCartoesUrls['banco'] }}" data-cal="{{ $wfCartoesUrls['cal'] }}" data-tarefas="{{ $wfCartoesUrls['tarefas'] }}"></script>
<script src="{{ asset('js/criador-redes.js') }}?v={{ @filemtime(public_path('js/criador-redes.js')) }}" data-redes="{{ \Illuminate\Support\Facades\Route::has('redes.metricas') ? url('/redes') : '' }}"></script>
{{-- cartões Google Drive, Gmail, YouTube e Lembretes por e-mail (partials/integracoes-cartoes) --}}
@include('partials.integracoes-cartoes-script')
