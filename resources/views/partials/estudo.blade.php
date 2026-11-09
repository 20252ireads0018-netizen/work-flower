{{-- Cartões de estudo da área Mente: "Flashcards", "Pomodoro", "Plano de estudos" e "Matemática e gráficos".
     Estilo e script (public/js/estudo.js). Incluído na página da Mente logo depois de partials/graficos (usa o wfNovoCartao
     do graficos.js) e antes do mente.js. O conteúdo de cada cartão está em partials/*-cartao. --}}
<style>
    /* comum: o cartão ocupa a altura do bloco e rola por dentro (editor, listas), não o bloco inteiro */
    .wf-bloco:has(> .es-cartao) { height: 100%; min-height: 0; }
    .wf-corpo:has(> .wf-bloco > .es-cartao) { overflow: hidden; }
    .es-cartao { height: 100%; min-height: 0; overflow: auto; }
    .es-cartao:focus { outline: none; }
    .es-svg { width: 100%; height: auto; display: block; }
    .es-svg text { font-family: 'DM Sans', sans-serif; }
    .es-svg .es-eixo { font-size: 10px; fill: var(--tinta-2); }
    .es-svg .es-grade { stroke: var(--linha); stroke-width: 1; }
    .es-svg .es-meta { stroke: #ef4444; stroke-width: 1.5; stroke-dasharray: 5 4; }
    .es-graf { border: 1px solid var(--linha); border-radius: .6rem; padding: .4rem; background: var(--superficie-2); }
    .es-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr)); gap: .4rem; }
    .es-kpis > div { display: flex; flex-direction: column; padding: .45rem .55rem; border-radius: .6rem; background: var(--superficie-2); border: 1px solid var(--linha); }
    .es-kpis b { font-size: 1.15rem; line-height: 1.2; }
    .es-kpis span { color: var(--tinta-2); font-size: .7rem; }
    .es-prog { height: .45rem; border-radius: 999px; background: var(--superficie-2); border: 1px solid var(--linha); overflow: hidden; min-width: 4rem; }
    .es-prog > span { display: block; height: 100%; background: var(--prim); border-radius: 999px; transition: width .4s ease; }
    .es-bola { width: .65rem; height: .65rem; border-radius: 999px; flex-shrink: 0; }

    /* flashcards */
    .fc-carta { display: flex; flex-direction: column; gap: .6rem; text-align: left; padding: .9rem 1rem; border-radius: .75rem; border: 1px solid var(--linha);
        background: var(--superficie-2); color: var(--tinta); overflow: auto; cursor: pointer; transition: border-color .15s; }
    .fc-carta:hover { border-color: var(--prim); }
    .fc-carta.virada { cursor: default; }
    .fc-frente { font-size: 1.02rem; font-weight: 600; }
    .fc-verso { padding-top: .6rem; border-top: 1px dashed var(--linha); font-size: .95rem; }
    .fc-selo { padding: 0 .4rem; border-radius: 999px; font-weight: 600; }
    .fc-selo.nova, .fc-n-nova { color: #2563eb; } .fc-selo.rev, .fc-n-rev { color: #16a34a; }
    html.dark .fc-selo.nova, html.dark .fc-n-nova { color: #93c5fd; } html.dark .fc-selo.rev, html.dark .fc-n-rev { color: #86efac; }
    .fc-notas { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .375rem; }
    .fc-nota { display: flex; flex-direction: column; align-items: center; padding: .35rem .2rem; border-radius: .5rem; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta); font-size: .78rem; }
    .fc-nota.n0 { border-color: rgba(239,68,68,.6); } .fc-nota.n0:hover { background: rgba(239,68,68,.15); }
    .fc-nota.n1 { border-color: rgba(251,146,60,.6); } .fc-nota.n1:hover { background: rgba(251,146,60,.15); }
    .fc-nota.n2 { border-color: rgba(34,197,94,.6); } .fc-nota.n2:hover { background: rgba(34,197,94,.15); }
    .fc-nota.n3 { border-color: rgba(47,111,199,.6); } .fc-nota.n3:hover { background: rgba(47,111,199,.15); }

    /* pomodoro */
    .pm-relogio { position: relative; width: min(11rem, 60%); aspect-ratio: 1; }
    .pm-relogio .pm-anel { width: 100%; height: 100%; display: block; }
    .pm-anel .pm-fundo { fill: none; stroke: var(--superficie-2); stroke-width: 10; }
    .pm-anel .pm-arco { fill: none; stroke-width: 10; stroke-linecap: round; transition: stroke-dashoffset .9s linear; }
    .pm-centro { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .pm-tempo { font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif; font-size: 2.2rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.1; }

    /* plano de estudos */
    .es-faixa { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .75rem; }
    .es-faixa .es-prog { flex: 1 1 6rem; }
    .es-prova { padding: .05rem .5rem; border-radius: 999px; background: rgba(167,139,250,.16); border: 1px solid rgba(167,139,250,.55); }
    .es-semana { display: grid; grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr)); gap: .4rem; }
    .es-dia { display: flex; flex-direction: column; gap: .3rem; padding: .4rem; border-radius: .6rem; background: var(--superficie-2); border: 1px solid var(--linha); min-height: 4.5rem; }
    .es-dia.hoje { border-color: var(--prim); box-shadow: 0 0 0 2px color-mix(in srgb, var(--prim) 22%, transparent); }
    .es-dia-tit { font-size: .72rem; font-weight: 600; }
    .es-tarefa { display: flex; align-items: flex-start; gap: .35rem; padding: .3rem .4rem; border-radius: .45rem; background: var(--superficie); border: 1px solid var(--linha);
        border-left: 3px solid var(--lc); font-size: .75rem; cursor: pointer; }
    .es-tarefa input { margin-top: .15rem; }
    .es-tarefa.feita > span > span:first-child { text-decoration: line-through; opacity: .6; }
    .es-x { color: var(--tinta-2); opacity: 0; line-height: 1; } .es-tarefa:hover .es-x, .es-x:focus { opacity: 1; }

    /* matemática */
    .mt-ed { flex: 1 1 auto; min-height: 7rem; display: grid; grid-template-columns: minmax(0, 1fr) minmax(7rem, 38%); border: 1px solid var(--linha); border-radius: .6rem; overflow: hidden; background: var(--superficie); }
    .mt-ed.com-graf { flex-basis: 45%; }
    .mt-ta, .mt-res { font-family: ui-monospace, 'Cascadia Mono', Menlo, Consolas, monospace; font-size: 13px; line-height: 22px; padding: 8px 10px; }
    .mt-ta { resize: none; border: 0; outline: none; background: transparent; color: var(--tinta); white-space: pre; overflow: auto; tab-size: 4; min-height: 0; }
    .mt-ta::placeholder { color: var(--tinta-2); opacity: .7; }
    .mt-res { overflow: hidden; background: var(--superficie-2); border-left: 1px solid var(--linha); text-align: right; }
    .mt-r { height: 22px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mt-r.ok, .mt-r.cas, .mt-r.info { cursor: copy; }
    .mt-r.ok { color: var(--prim-forte, var(--prim)); font-weight: 600; }
    .mt-r.total { border-top: 1px solid var(--linha); }
    .mt-r.cas { color: #7c3aed; font-weight: 600; } html.dark .mt-r.cas { color: #c4b5fd; }
    .mt-r.info, .mt-r.espera { color: var(--tinta-2); font-style: italic; }
    .mt-r.erro { color: #ef4444; opacity: .75; cursor: help; }
    .mt-ajuda { padding: .5rem; border-radius: .6rem; border: 1px solid var(--linha); background: var(--superficie-2); max-height: 40%; overflow: auto; flex-shrink: 0; }
    .mt-graf-caixa { position: relative; flex: 1 1 42%; min-height: 150px; }
    .mt-graf { position: absolute; inset: 0; border: 1px solid var(--linha); border-radius: .6rem; overflow: hidden; background: var(--superficie); cursor: crosshair; touch-action: none; user-select: none; }
    .mt-graf:active { cursor: grabbing; }
    .mt-graf-botoes { position: absolute; right: .4rem; top: .4rem; display: flex; gap: .25rem; }
    .mt-svg { display: block; }
    .mt-svg .mt-grade { stroke: var(--linha); stroke-width: 1; opacity: .7; }
    .mt-svg .mt-eixo { stroke: var(--tinta-2); stroke-width: 1.2; }
    .mt-svg .mt-num { font: 10px 'DM Sans', sans-serif; fill: var(--tinta-2); }
    .mt-svg .mt-curva { fill: none; stroke-width: 2.2; stroke-linejoin: round; }
    .mt-svg .mt-cursor { stroke: var(--tinta-2); stroke-dasharray: 3 3; }
    .mt-svg .mt-leitura { fill: var(--superficie); stroke: var(--linha); opacity: .95; }
    .mt-svg .mt-leit-txt { font: 600 11px ui-monospace, Menlo, Consolas, monospace; fill: var(--tinta); }
</style>
<script src="{{ asset('js/estudo.js') }}?v={{ @filemtime(public_path('js/estudo.js')) }}"></script>
