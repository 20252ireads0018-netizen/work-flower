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
</style>
<script src="{{ asset('js/graficos.js') }}?v={{ @filemtime(public_path('js/graficos.js')) }}"></script>
