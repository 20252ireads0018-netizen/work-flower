{{-- Cartão "Cenas" (escritores): estilo e script (public/js/cenas.js). Incluído no fim do partials/graficos,
     depois do cartoes-extra.js (usa o wfNovoCartao do graficos.js). Conteúdo em partials/cenas-cartao. --}}
<style>
    .cn-busca { width: 8.5rem; }
    .cn-col { flex: 0 0 12rem; display: flex; flex-direction: column; gap: .35rem; padding: .4rem; border-radius: .6rem; background: var(--superficie-2); min-height: 100%; border: 1px dashed transparent; transition: border-color .15s, background .15s; }
    .cn-col-ideias { flex-basis: 11rem; background: rgba(167,139,250,.10); }
    .cn-ato-nome { flex: 1; min-width: 0; font-size: .78rem; font-weight: 700; background: transparent; border: 1px solid transparent; border-radius: .35rem; padding: .1rem .25rem; color: var(--tinta); }
    .cn-ato-nome:hover, .cn-ato-nome:focus { border-color: var(--linha); background: var(--superficie); outline: none; }
    .cn-cena { padding: .45rem .5rem; border-radius: .55rem; background: var(--superficie); border: 1px solid var(--linha); border-left: 4px solid var(--cn-cor, var(--linha)); cursor: pointer; font-size: .8rem; border-top-color: var(--linha); }
    .cn-cena:hover { border-color: var(--prim); border-left-color: var(--cn-cor); }
    .cn-chip { padding: 0 .4rem; border-radius: 999px; background: color-mix(in srgb, var(--cn-cor) 22%, transparent); border: 1px solid var(--cn-cor); color: var(--tinta); }
    .cn-req { height: .3rem; border-radius: 999px; background: var(--superficie-2); overflow: hidden; margin-top: .35rem; }
    .cn-req > span { display: block; height: 100%; background: #22c55e; transition: width .3s ease; }
    .cn-ideia { display: flex; align-items: flex-start; gap: .3rem; padding: .3rem .4rem; border-radius: .45rem; background: var(--superficie); border: 1px solid var(--linha); font-size: .75rem; line-height: 1.3; }
    .cn-col-ideias .cn-ideia { flex-wrap: wrap; justify-content: flex-end; }
    .cn-col-ideias .cn-ideia > .cn-quebra { flex: 1 1 calc(100% - 1.4rem); }
    .cn-ideia-mini { padding: .1rem .35rem; font-size: .68rem; background: rgba(167,139,250,.16); border-color: rgba(167,139,250,.6); max-width: 100%; }
    .cn-pega { cursor: grab; touch-action: none; user-select: none; color: var(--tinta-2); padding: 0 .15rem; line-height: 1.2; flex: none; }
    .cn-pega:active { cursor: grabbing; }
    .cn-x { flex: none; padding: 0 .3rem; border-radius: .35rem; border: 1px solid transparent; background: transparent; color: var(--tinta-2); line-height: 1.4; font-size: .72rem; }
    .cn-x:hover { color: var(--tinta); border-color: var(--linha); background: var(--superficie-2); }
    .cn-levar { flex: none; width: 1.7rem; padding: 0; font-size: .72rem; border: 1px solid transparent; border-radius: .35rem; background: transparent; color: var(--tinta-2); cursor: pointer; }
    .cn-levar:hover { border-color: var(--linha); }
    .cn-quebra { overflow-wrap: anywhere; }
    .cn-saindo { opacity: .35; }
    .cn-fantasma { position: fixed; z-index: 9999; pointer-events: none; opacity: .92; transform: rotate(2deg); box-shadow: 0 10px 28px rgba(0,0,0,.28); }
    .cn-sobre { border-color: var(--prim) !important; background: rgba(47,111,199,.10); }
    .cn-cena.cn-sobre { box-shadow: 0 0 0 2px var(--prim); }
    .cn-grade { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: .5rem; }
    .cn-area { resize: vertical; min-height: 2.4rem; }
    .cn-ok { display: inline-flex; width: 1rem; height: 1rem; align-items: center; justify-content: center; border-radius: 999px; font-size: .6rem; border: 1px solid var(--linha); color: transparent; }
    .cn-ok.on { background: #22c55e; border-color: #22c55e; color: #fff; }
    .cn-bolinha { display: inline-block; width: .6rem; height: .6rem; border-radius: 999px; }
    /* linha do tempo */
    .cn-linha { display: flex; gap: .75rem; min-width: max-content; padding: .25rem .1rem .5rem; }
    .cn-linha-ato { display: flex; flex-direction: column; gap: .4rem; }
    .cn-linha-tit { font-size: .72rem; font-weight: 700; padding: .15rem .45rem; border-radius: .4rem; background: var(--superficie-2); align-self: flex-start; }
    .cn-linha-cenas { position: relative; display: flex; gap: .5rem; padding-top: .35rem; }
    .cn-linha-cenas::before { content: ""; position: absolute; left: 0; right: 0; top: 1.15rem; height: 2px; background: var(--linha); }
    .cn-ponto { position: relative; display: flex; flex-direction: column; align-items: center; gap: .15rem; width: 7rem; text-align: center; background: none; border: 0; padding: 0; color: var(--tinta); cursor: pointer; }
    .cn-ponto-bola { display: flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: 999px; background: var(--cn-cor); color: #fff; font-size: .72rem; font-weight: 700; box-shadow: 0 0 0 3px var(--superficie); }
    .cn-ponto:hover .cn-ponto-bola { transform: scale(1.12); }
    .cn-ponto-tit { font-size: .75rem; font-weight: 600; line-height: 1.2; }
    .cn-ponto-sub { font-size: .66rem; color: var(--tinta-2); line-height: 1.2; }
</style>
<script src="{{ asset('js/cenas.js') }}?v={{ @filemtime(public_path('js/cenas.js')) }}"></script>
