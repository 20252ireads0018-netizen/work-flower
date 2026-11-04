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
    .gr-tab { border-collapse: collapse; font-size: .8rem; }
    .gr-tab th, .gr-tab td { border-bottom: 1px solid var(--linha); padding: .1rem .2rem; }
    .gr-tab th .wf-cel { font-weight: 600; }
    .gr-tab .wf-cel, .hb-tab .wf-cel { width: 100%; min-width: 4.5rem; background: transparent; color: var(--tinta); border: 0; padding: .3rem .35rem; font-size: .8rem; border-radius: .375rem; }
    .gr-tab .wf-cel:focus, .hb-tab .wf-cel:focus { outline: none; background: var(--superficie-2); box-shadow: 0 0 0 2px var(--prim-linha, var(--prim)); }
    .hb-tab { border-collapse: separate; border-spacing: 2px; font-size: .7rem; }
    .hb-tab th { font-weight: 600; color: var(--tinta-2); line-height: 1.1; text-align: center; min-width: 1.35rem; }
    .hb-tab th.hoje { color: var(--prim); }
    .hb-tab .hb-nome { text-align: left; min-width: 9rem; position: sticky; left: 0; background: var(--superficie); z-index: 1; }
    .hb-tab td.fds .hb-dia:not(.feito) { opacity: .7; }
    .hb-tab td.hoje .hb-dia { box-shadow: 0 0 0 1.5px var(--prim); }
    .hb-dia { display: block; width: 1.3rem; height: 1.3rem; border-radius: .3rem; background: var(--superficie-2); border: 1px solid var(--linha); cursor: pointer; padding: 0; }
    .hb-dia.feito { background: var(--c, var(--prim)); border-color: var(--c, var(--prim)); }
    .hb-dia:disabled { opacity: .3; cursor: default; }
    .hb-dia:focus-visible { outline: 2px solid var(--tinta); outline-offset: 1px; }
    .hb-num { text-align: right; font-weight: 600; padding: 0 .25rem; font-size: .75rem; white-space: nowrap; }
    .hb-cor { width: .85rem; height: .85rem; border-radius: 9999px; flex-shrink: 0; cursor: pointer; border: 0; padding: 0; }

    /* Editor de PDF em tela cheia (js/editor-pdf.js) */
    html.pe-aberto { overflow: hidden; }
    .pe-raiz { position: fixed; inset: 0; z-index: 2000; display: flex; flex-direction: column; background: #3b3f45; color: #f1f1f1; }
    .pe-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; padding: .5rem .75rem; background: #24272b; border-bottom: 1px solid #000; }
    .pe-barra button, .pe-btn { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .6rem; font-size: .8rem; border-radius: .4rem; border: 1px solid #4b5058; background: #33373d; color: #f1f1f1; cursor: pointer; }
    .pe-barra button:hover, .pe-btn:hover { border-color: #8ab4f8; }
    .pe-barra .pe-ok { background: #2f6fc7; border-color: #2f6fc7; }
    .pe-barra input[type=color] { width: 1.6rem; height: 1.3rem; padding: 0; border: 0; background: none; }
    .pe-barra input[type=number] { background: #1c1f22; color: #f1f1f1; border: 1px solid #4b5058; border-radius: .3rem; padding: .1rem .3rem; }
    .pe-tit { max-width: 14rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pe-sep { width: 1px; height: 1.4rem; background: #4b5058; }
    .pe-esp { flex: 1; }
    .pe-dica { font-size: .75rem; padding: .3rem .75rem; color: #b8bcc2; background: #2c2f34; }
    .pe-paginas { flex: 1; overflow: auto; display: flex; flex-direction: column; align-items: center; gap: 1.25rem; padding: 1.25rem; }
    .pe-pg { position: relative; flex-shrink: 0; box-shadow: 0 6px 20px rgba(0,0,0,.45); outline: 3px solid transparent; }
    .pe-pg.atual { outline-color: #2f6fc7; }
    .pe-pg canvas { position: absolute; inset: 0; }
    .pe-camada { position: absolute; inset: 0; }
    .pe-num { position: absolute; right: -2.6rem; top: 0; font-size: .7rem; color: #b8bcc2; }
    .pe-item { position: absolute; cursor: move; box-sizing: border-box; outline: 1px dashed transparent; }
    .pe-item:hover, .pe-item.sel { outline-color: #2f6fc7; }
    .pe-item.sel { outline-style: solid; }
    .pe-texto { white-space: pre-wrap; overflow-wrap: anywhere; overflow: hidden; padding: 0 2px; font-family: Helvetica, Arial, sans-serif; }
    .pe-texto[contenteditable="true"] { cursor: text; background: rgba(47,111,199,.08); }
    .pe-marca { opacity: .38; }
    .pe-img img { width: 100%; height: 100%; display: block; pointer-events: none; }
    .pe-alca { position: absolute; right: -5px; bottom: -5px; width: 10px; height: 10px; background: #2f6fc7; border-radius: 2px; cursor: nwse-resize; display: none; }
    .pe-item.sel .pe-alca, .pe-item:hover .pe-alca { display: block; }
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
</style>
<script src="{{ asset('js/graficos.js') }}?v={{ @filemtime(public_path('js/graficos.js')) }}"></script>
{{-- cartão "Editor de PDF" (estende o quadro.js do mesmo jeito) --}}
<script src="{{ asset('js/editor-pdf.js') }}?v={{ @filemtime(public_path('js/editor-pdf.js')) }}"></script>
{{-- cartões Questões, Criador e RH (usa o wfNovoCartao do graficos.js) --}}
<script src="{{ asset('js/cartoes-extra.js') }}?v={{ @filemtime(public_path('js/cartoes-extra.js')) }}"></script>
