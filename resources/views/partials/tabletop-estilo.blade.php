{{-- Cartão "Tabletop" (mapa com grade e peças): estilo. Incluído uma vez por página: no Diverso e nas áreas que
     receberam o cartão (partials/tipos-diverso). O cartão é o areas/diverso/partials/tabletop; a lógica fica no
     public/js/diverso.js (WFTabletop) e no public/js/tabletopVivo.js (salas ao vivo). --}}
<style>
    /* ===== Tabletop (mapa com grade) ===== */
    .wf-tt { height: 100%; display: flex; flex-direction: column; gap: .6rem; min-height: 0; }
    .wf-tt-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .wf-tt-linha .campo { width: auto; padding: .3rem .5rem; font-size: .8rem; }
    .wf-tt-linha .wf-tt-nome-in { flex: 1; min-width: 7rem; }
    .wf-tt-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .25rem .6rem; font-size: .75rem; border-radius: 9999px; border: 1px solid var(--linha); background: var(--superficie-2); color: var(--tinta); cursor: pointer; transition: border-color .15s, color .15s; }
    .wf-tt-chip:hover { border-color: var(--prim-linha); color: var(--prim-forte); }
    .wf-tt-chip i { display: inline-block; width: .6rem; height: .6rem; border-radius: 9999px; background: var(--pc); }

    /* dropdown de atributos */
    .wf-tt-drop { position: relative; }
    .wf-tt-bolinha { display: inline-block; width: .65rem; height: .65rem; border-radius: 9999px; margin-right: .3rem; vertical-align: -1px; box-shadow: 0 0 0 1px rgba(255, 255, 255, .25); }
    .wf-tt-pop { position: absolute; top: 100%; left: 0; z-index: 30; margin-top: .35rem; min-width: 15rem; padding: .7rem; display: grid; gap: .55rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    .wf-tt-attr { display: flex; align-items: center; justify-content: space-between; gap: .75rem; font-size: .78rem; color: var(--tinta-2); }
    .wf-tt-pop .campo { width: 8rem; }
    .wf-tt-mini { width: 1.75rem; height: 1.75rem; object-fit: cover; border-radius: .3rem; border: 1px solid var(--linha); }

    .wf-tt-cena { flex: 1; min-height: 8rem; overflow: auto; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); padding: .5rem; }
    .wf-tt-grade { position: relative; flex-shrink: 0; margin-bottom: 1.2rem; background: var(--superficie-2); box-shadow: 0 0 0 1px var(--linha); isolation: isolate; user-select: none; }
    .wf-tt-grade::after {
        content: ""; position: absolute; inset: 0; z-index: 1; pointer-events: none;
        background-image: linear-gradient(to right, rgba(255, 255, 255, .09) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, .09) 1px, transparent 1px);
        background-size: var(--c) var(--c);
    }
    .wf-tt-grade.sem-grade::after { display: none; }
    .wf-tt-mapa { position: absolute; inset: 0; z-index: 0; pointer-events: none; background-size: 100% 100%; background-repeat: no-repeat; }
    .wf-tt-peca {
        position: absolute; z-index: 2; display: flex; align-items: center; justify-content: center;
        cursor: grab; touch-action: none; user-select: none; outline: none;
        transition: left .1s ease, top .1s ease;
    }
    .wf-tt-peca::before { content: ""; position: absolute; inset: 3px; background: var(--pc); box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .25); }
    .wf-tt-peca.f-circulo::before { border-radius: 9999px; }
    .wf-tt-peca.f-quadrado::before { border-radius: .3rem; }
    .wf-tt-peca.f-losango::before { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); }
    /* imagem no lugar da inicial: a moldura (span) recorta na forma da peça e a imagem dentro dela recebe o zoom e a posição */
    .wf-tt-img { position: absolute; left: 3px; top: 3px; width: calc(100% - 6px); height: calc(100% - 6px); overflow: hidden; pointer-events: none; }
    .wf-tt-img img, .wf-tt-enq img { display: block; width: 100%; height: 100%; object-fit: cover; transform-origin: center; pointer-events: none; }
    .wf-tt-peca.f-circulo .wf-tt-img { border-radius: 9999px; }
    .wf-tt-peca.f-quadrado .wf-tt-img { border-radius: .3rem; }
    .wf-tt-peca.f-losango .wf-tt-img { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); }
    .wf-tt-peca:focus-visible, .wf-tt-peca.sel { z-index: 3; filter: drop-shadow(0 0 3px #fff); }
    .wf-tt-peca.arrasta { z-index: 5; cursor: grabbing; transition: none; filter: drop-shadow(0 6px 8px rgba(0, 0, 0, .55)); }
    .wf-tt-ini { position: relative; font-size: .75rem; font-weight: 700; color: #fff; text-shadow: 0 1px 2px rgba(0, 0, 0, .75); pointer-events: none; }
    .wf-tt-rotulo { position: absolute; top: 100%; left: 50%; transform: translateX(-50%); z-index: 4; white-space: nowrap; font-size: .62rem; padding: 0 .3rem; border-radius: .25rem; background: rgba(0, 0, 0, .65); color: #fff; pointer-events: none; }
    /* retângulo de seleção */
    .wf-tt-caixa { position: absolute; z-index: 6; pointer-events: none; border: 1px solid var(--prim); background: rgba(255, 255, 255, .08); }

    /* editor de enquadramento da imagem (prévia no formato da peça, com zoom e posição) */
    .wf-tt-enq-box { display: grid; gap: .4rem; justify-items: center; padding-top: .5rem; border-top: 1px solid var(--linha); }
    .wf-tt-enq { position: relative; width: 9rem; height: 9rem; overflow: hidden; background: var(--superficie-2); cursor: move; touch-action: none; user-select: none; box-shadow: 0 0 0 2px var(--prim-linha); }
    .wf-tt-enq.f-circulo { border-radius: 9999px; }
    .wf-tt-enq.f-quadrado { border-radius: .4rem; }
    .wf-tt-enq.f-losango { clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%); box-shadow: none; }
    .wf-tt-enq-zoom { display: flex; align-items: center; gap: .5rem; width: 100%; font-size: .72rem; color: var(--tinta-2); }
    .wf-tt-enq-zoom input[type=range] { flex: 1; min-width: 0; }

    @media (max-width: 899px) { .wf-tt { height: auto; } .wf-tt-cena { max-height: 70vh; } }
</style>
