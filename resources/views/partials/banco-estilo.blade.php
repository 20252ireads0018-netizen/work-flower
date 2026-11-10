{{-- Cartão "Banco de dados (.sql/.csv)": estilo. Incluído uma vez por página: no Diverso e nas áreas que receberam
     o cartão (partials/tipos-diverso). O conteúdo é montado pelo public/js/banco-sql.js. --}}
<style>
    /* ===== Banco de dados (.sql/.csv) — montado por banco-sql.js ===== */
    .bd { min-height: 0; position: relative; }
    .bd [hidden] { display: none !important; }
    .bd.bd-soltando { outline: 2px dashed var(--prim); outline-offset: -2px; border-radius: .6rem; }
    .bd-barra, .bd-ferr { display: flex; flex-wrap: wrap; align-items: center; gap: .375rem; font-size: .75rem; }
    .bd-abas { display: flex; gap: .25rem; }
    .bd-on { background: var(--prim) !important; color: var(--on-prim) !important; border-color: var(--prim) !important; }
    .bd-tela-sair { display: none; }
    .wf-fs .bd-tela { background: var(--prim); color: var(--on-prim); border-color: var(--prim); font-weight: 600; }
    .wf-fs .bd-tela-entrar { display: none; }
    .wf-fs .bd-tela-sair { display: inline; }
    .wf-fs .bd-limpar { display: none !important; } /* longe do "Sair da tela cheia" */
    .bd-msg { font-size: .78rem; padding: .4rem .6rem; border-radius: .5rem; background: var(--superficie-2); border: 1px solid var(--linha); }
    .bd-msg.erro { border-color: #e5484d; background: color-mix(in srgb, #e5484d 12%, var(--superficie)); }
    .bd-avisos { font-size: .72rem; color: var(--tinta-2); }
    .bd-prog { border: 1px solid var(--linha); border-radius: .6rem; padding: .5rem .65rem; background: var(--superficie-2); font-size: .8rem; }
    .bd-prog-topo { display: flex; justify-content: space-between; align-items: center; gap: .5rem; font-weight: 600; }
    .bd-prog-trilho { height: .55rem; border-radius: 99px; background: var(--linha); overflow: hidden; margin: .4rem 0 .25rem; }
    .bd-prog-barra { height: 100%; width: 0; background: var(--prim); border-radius: 99px; transition: width .2s; }
    .bd-vazio { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 1rem; border: 2px dashed var(--linha); border-radius: .75rem; }
    .bd-corpo { display: flex; flex-direction: column; }
    .bd-diag, .bd-grade-v, .bd-stats-v { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: .4rem; }
    .bd-area { position: relative; flex: 1; min-height: 12rem; overflow: hidden; border: 1px solid var(--linha); border-radius: .6rem; background: var(--superficie-2); cursor: grab; touch-action: none; }
    .bd-area.movendo { cursor: grabbing; }
    .bd-dica { position: absolute; left: .5rem; bottom: .35rem; font-size: .65rem; color: var(--tinta-2); pointer-events: none; background: color-mix(in srgb, var(--superficie-2) 80%, transparent); padding: 0 .3rem; border-radius: .3rem; }
    .bd-svg { display: block; user-select: none; -webkit-user-select: none; }
    .bd-svg [data-bd-t] { cursor: move; }
    .bd-svg .bd-caixa { fill: var(--superficie); stroke: var(--linha); }
    .bd-svg .bd-caixa.on { stroke: var(--prim); stroke-width: 3; }
    .bd-svg .bd-cab { fill: color-mix(in srgb, var(--prim) 30%, var(--superficie)); }
    .bd-svg text { font-family: 'DM Sans', sans-serif; fill: var(--tinta); }
    .bd-svg .bd-nome { font-size: 12.5px; font-weight: 700; }
    .bd-svg .bd-nome.grande { font-size: 24px; }
    .bd-svg .bd-qtd, .bd-svg .bd-tipo { font-size: 10px; fill: var(--tinta-2); }
    .bd-svg .bd-col { font-size: 11.5px; }
    .bd-svg .bd-col.pk { font-weight: 700; }
    .bd-svg .bd-tag { font-size: 9px; font-weight: 700; fill: var(--prim-forte, var(--prim)); }
    /* ligações: rota em ângulo reto, pé de galinha nas pontas; passar o mouse destaca e apaga o resto */
    .bd-svg .bd-rel, .bd-svg [data-bd-t] { transition: opacity .12s; }
    .bd-svg .bd-lig { fill: none; stroke: var(--tinta-2); stroke-width: 1.4; stroke-linejoin: round; opacity: .85; vector-effect: non-scaling-stroke; }
    .bd-svg .bd-lig-hit { fill: none; stroke: transparent; stroke-width: 12; vector-effect: non-scaling-stroke; pointer-events: stroke; }
    .bd-svg .bd-pe { fill: none; stroke: var(--tinta-2); stroke-width: 1.4; vector-effect: non-scaling-stroke; }
    .bd-svg .bd-pe-c { fill: var(--superficie-2); stroke: var(--tinta-2); stroke-width: 1.4; vector-effect: non-scaling-stroke; }
    .bd-svg .bd-rel.on .bd-lig, .bd-svg .bd-rel.rel .bd-lig { stroke: var(--prim); stroke-width: 2.6; opacity: 1; }
    .bd-svg .bd-rel.on .bd-pe, .bd-svg .bd-rel.rel .bd-pe, .bd-svg .bd-rel.on .bd-pe-c, .bd-svg .bd-rel.rel .bd-pe-c { stroke: var(--prim); stroke-width: 2; }
    .bd-svg g.foco .bd-rel:not(.rel) { opacity: .12; }
    .bd-svg g.foco [data-bd-t]:not(.rel) { opacity: .4; }
    .bd-svg [data-bd-t].rel .bd-caixa { stroke: var(--prim); stroke-width: 2; }
    .bd-svg .bd-col.rel { fill: var(--prim-forte, var(--prim)); font-weight: 700; }
    .bd-svg .bd-faixa { fill: color-mix(in srgb, var(--prim) 22%, transparent); }
    /* tabela virtual: só as linhas visíveis existem no DOM */
    .bd-rolagem { position: relative; flex: 1; min-height: 8rem; overflow: auto; border: 1px solid var(--linha); border-radius: .6rem; font-size: .75rem; }
    .bd-cabeca { position: sticky; top: 0; z-index: 2; background: var(--superficie-2); }
    .bd-corpo-v { position: relative; }
    .bd-janela { position: absolute; left: 0; right: 0; top: 0; will-change: transform; }
    .bd-lin { display: flex; height: 26px; border-bottom: 1px solid var(--linha); }
    .bd-lin.par { background: color-mix(in srgb, var(--superficie-2) 55%, transparent); }
    .bd-cel { flex: none; padding: 0 .5rem; line-height: 25px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-right: 1px solid var(--linha); }
    .bd-janela .bd-cel { cursor: pointer; }
    .bd-cel.n { text-align: right; font-variant-numeric: tabular-nums; }
    .bd-cel.nulo { color: var(--tinta-2); font-style: italic; }
    .bd-cel.bd-num { width: 64px; text-align: right; color: var(--tinta-2); font-size: .68rem; background: var(--superficie-2); position: sticky; left: 0; z-index: 1; }
    .bd-th { font-weight: 600; cursor: pointer; user-select: none; }
    .bd-th:hover { color: var(--prim); }
    .bd-th small { color: var(--prim-forte, var(--prim)); font-size: .6rem; }
    .bd-th b { color: var(--prim); }
    .bd-filtros .bd-cel { padding: 2px 3px; line-height: 1; }
    .bd-filtro { width: 100%; height: 21px; font-size: .7rem; padding: 0 .35rem; border: 1px solid var(--linha); border-radius: .3rem; background: var(--superficie); color: var(--tinta); }
    .bd-carregando { position: absolute; left: 50%; top: 4.5rem; transform: translateX(-50%); background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; padding: .4rem .75rem; font-size: .75rem; box-shadow: 0 4px 14px rgba(0,0,0,.12); z-index: 3; max-width: 90%; text-align: center; }
    .bd-detalhe { font-size: .75rem; padding: .35rem .55rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); max-height: 6.5rem; overflow: auto; white-space: pre-wrap; word-break: break-word; }
    .bd-rodape { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; font-size: .75rem; }
    .bd-pag { width: 4.5rem; display: inline-block; height: 1.5rem; }
    .bd-stats { flex: 1; min-height: 0; overflow: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: .5rem; align-content: start; }
    .bd-stat { border: 1px solid var(--linha); border-radius: .6rem; padding: .5rem .6rem; font-size: .75rem; background: var(--superficie-2); display: flex; flex-direction: column; gap: .2rem; }
    .bd-top { display: flex; flex-direction: column; gap: 2px; margin-top: .2rem; }
    .bd-barra-v { position: relative; display: flex; justify-content: space-between; gap: .5rem; padding: 1px .35rem; font-size: .7rem; border-radius: .25rem; overflow: hidden; }
    .bd-barra-v span { position: absolute; inset: 0 auto 0 0; background: color-mix(in srgb, var(--prim) 22%, transparent); }
    .bd-barra-v em, .bd-barra-v b { position: relative; font-style: normal; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bd-barra-v b { font-weight: 600; flex: none; }
</style>
