@extends('layouts.app')

@section('titulo', 'Meu Flower')
@section('subtitulo', 'Todo o seu workflow numa flor: áreas, abas, pastas, cartões e tarefas. Clique para abrir um ramo; duplo clique leva até o cartão.')

@section('conteudo')
<style>
    .fl-palco{position:relative;width:min(100vw - 2rem, 1500px);left:50%;transform:translateX(-50%);height:max(520px, calc(100vh - 230px));
        border:1px solid var(--linha);border-radius:1.25rem;overflow:hidden;
        background:radial-gradient(circle at 50% 45%, color-mix(in srgb, var(--prim) 10%, var(--superficie)) 0, var(--papel) 70%);}
    .fl-palco:fullscreen{width:100vw;height:100vh;left:0;transform:none;border-radius:0;border:0}
    .fl-svg{display:block;width:100%;height:100%;touch-action:none;cursor:grab;user-select:none;-webkit-user-select:none}
    .fl-svg.fl-arrastando{cursor:grabbing}

    .fl-petala{fill:color-mix(in srgb, var(--c) 9%, transparent);stroke:color-mix(in srgb, var(--c) 38%, transparent);stroke-width:1.5;vector-effect:non-scaling-stroke}
    .fl-linha{fill:none;stroke:color-mix(in srgb, var(--c) 55%, transparent);stroke-width:1.6;transition:opacity .25s}
    .fl-l-area{stroke-width:4;stroke:color-mix(in srgb, var(--c) 70%, transparent)}
    .fl-l-aba{stroke-width:2.4}
    .fl-l-tarefa{stroke-width:1;stroke-dasharray:3 3}

    .fl-no{cursor:pointer;outline:none;transition:opacity .25s}
    .fl-bola{fill:color-mix(in srgb, var(--c) 30%, var(--superficie));stroke:var(--c);stroke-width:1.6;transition:r .2s, stroke-width .2s}
    .fl-pessoa .fl-bola{fill:var(--prim);stroke:color-mix(in srgb, var(--prim) 60%, white);stroke-width:3}
    .fl-flor path{fill:color-mix(in srgb, var(--prim) 12%, transparent);stroke:color-mix(in srgb, var(--prim) 70%, transparent);stroke-width:.18}
    .fl-area .fl-bola{fill:var(--c);stroke:color-mix(in srgb, var(--c) 55%, white);stroke-width:3}
    .fl-aba .fl-bola{fill:color-mix(in srgb, var(--c) 55%, var(--superficie))}
    .fl-pasta .fl-bola{fill:color-mix(in srgb, var(--c) 40%, var(--superficie));stroke-dasharray:3 2}
    .fl-cartao .fl-bola{fill:var(--superficie)}
    .fl-tarefa .fl-bola{fill:var(--superficie);stroke-width:1.2}
    .fl-tarefa[data-status="concluida"] .fl-bola{fill:color-mix(in srgb, var(--c) 45%, var(--superficie))}
    .fl-check{fill:none;stroke:var(--tinta);stroke-width:1.4;stroke-linecap:round;stroke-linejoin:round}
    .fl-fechado .fl-bola{stroke-width:3.2;fill:color-mix(in srgb, var(--c) 60%, var(--superficie))}
    .fl-no:hover .fl-bola,.fl-no:focus-visible .fl-bola{stroke:var(--tinta);stroke-width:3}
    .fl-sel .fl-bola{stroke:#fff;stroke-width:3.5;filter:drop-shadow(0 0 8px var(--c))}

    .fl-rot{fill:var(--tinta);font:500 12px 'DM Sans',system-ui,sans-serif;paint-order:stroke;stroke:var(--papel);stroke-width:4px;stroke-linejoin:round;pointer-events:none}
    .fl-pessoa .fl-rot{font:700 15px 'Bricolage Grotesque','DM Sans',sans-serif;fill:var(--on-prim);stroke:none}
    .fl-area .fl-rot{font:700 14px 'Bricolage Grotesque','DM Sans',sans-serif;fill:#0b0f14;stroke:none}
    .fl-conta{fill:var(--tinta-2);font:500 11px 'DM Sans',sans-serif;paint-order:stroke;stroke:var(--papel);stroke-width:4px;pointer-events:none}
    .fl-aba .fl-rot{font-weight:600;font-size:13px}
    .fl-tarefa .fl-rot{font-size:11px;fill:color-mix(in srgb, var(--tinta) 80%, var(--tinta-2))}
    .fl-tarefa[data-status="concluida"] .fl-rot{text-decoration:line-through;fill:var(--tinta-2)}
    .fl-mais{fill:var(--tinta);font:700 8px 'DM Sans',sans-serif;pointer-events:none}
    .fl-aba .fl-mais{font-size:9px}

    .fl-longe .fl-tarefa .fl-rot,.fl-longe .fl-cartao .fl-rot,.fl-longe .fl-pasta .fl-rot{display:none}
    .fl-muito-longe .fl-aba .fl-rot,.fl-muito-longe .fl-mais,.fl-muito-longe .fl-conta{display:none}
    .fl-buscando .fl-no:not(.fl-aceso):not(.fl-pessoa){opacity:.16}
    .fl-buscando .fl-linha:not(.fl-aceso){opacity:.12}
    .fl-buscando .fl-achado .fl-rot{display:inline;fill:#fff;font-weight:700}
    .fl-achado .fl-bola{stroke:#fde047;stroke-width:3.5}

    .fl-barra{position:absolute;top:.75rem;left:.75rem;right:.75rem;display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;pointer-events:none}
    .fl-barra > *{pointer-events:auto}
    .fl-caixa{display:flex;align-items:center;gap:.25rem;padding:.3rem;border:1px solid var(--linha);border-radius:.8rem;background:color-mix(in srgb, var(--superficie) 88%, transparent);backdrop-filter:blur(8px)}
    .fl-busca{width:min(15rem, 46vw);background:transparent;border:0;color:var(--tinta);padding:.35rem .5rem;font-size:.875rem;outline:none}
    .fl-busca-n{font-size:.75rem;color:var(--tinta-2);padding-right:.4rem;white-space:nowrap}
    .fl-bt{border:0;background:none;color:var(--tinta);border-radius:.55rem;padding:.35rem .6rem;font-size:.8rem;cursor:pointer;white-space:nowrap}
    .fl-bt:hover,.fl-bt:focus-visible{background:var(--prim-suave)}
    .fl-bt.fl-q{width:2rem;padding:.35rem 0;font-size:1rem;line-height:1}
    .fl-total{margin-left:auto;font-size:.75rem;color:var(--tinta-2);padding:.4rem .7rem}

    .fl-legenda{position:absolute;left:.75rem;bottom:.75rem;display:flex;flex-direction:column;gap:.3rem;margin:0;padding:0;list-style:none;max-width:min(17rem, 60vw)}
    .fl-legenda button{display:grid;grid-template-columns:auto 1fr;column-gap:.5rem;align-items:center;width:100%;text-align:left;padding:.4rem .6rem;border:1px solid var(--linha);border-radius:.7rem;
        background:color-mix(in srgb, var(--superficie) 88%, transparent);backdrop-filter:blur(8px);color:var(--tinta);cursor:pointer}
    .fl-legenda button:hover,.fl-legenda button:focus-visible{border-color:var(--c)}
    .fl-leg-cor{width:.7rem;height:.7rem;border-radius:50%;background:var(--c);box-shadow:0 0 0 3px color-mix(in srgb, var(--c) 25%, transparent)}
    .fl-legenda strong{font-size:.8rem}
    .fl-legenda small{grid-column:2;font-size:.68rem;color:var(--tinta-2)}
    .fl-leg-barra{grid-column:2;height:3px;border-radius:2px;background:var(--linha);margin-top:.2rem;overflow:hidden}
    .fl-leg-barra i{display:block;height:100%;background:var(--c)}

    .fl-detalhe{position:absolute;right:.75rem;bottom:.75rem;width:min(20rem, calc(100% - 1.5rem));padding:.85rem 1rem;border:1px solid var(--linha);border-left:4px solid var(--c);border-radius:.9rem;
        background:color-mix(in srgb, var(--superficie) 94%, transparent);backdrop-filter:blur(10px);box-shadow:0 10px 30px rgba(0,0,0,.35)}
    .fl-d-caminho{font-size:.7rem;color:var(--tinta-2);margin:0 1.5rem .15rem 0}
    .fl-d-nome{font:700 1.05rem 'Bricolage Grotesque','DM Sans',sans-serif;margin:0 1.5rem .3rem 0;word-break:break-word}
    .fl-d-info{font-size:.78rem;color:var(--tinta-2);margin:0 0 .7rem}
    .fl-d-acoes{display:flex;gap:.4rem;flex-wrap:wrap}
    .fl-d-abrir{background:var(--c);color:#0b0f14;font-weight:600;border-radius:.6rem;padding:.4rem .8rem;font-size:.8rem;text-decoration:none}
    .fl-d-alternar{border:1px solid var(--linha);background:none;color:var(--tinta);border-radius:.6rem;padding:.4rem .8rem;font-size:.8rem;cursor:pointer}
    .fl-d-fechar{position:absolute;top:.45rem;right:.5rem;border:0;background:none;color:var(--tinta-2);font-size:1.1rem;cursor:pointer;line-height:1;padding:.2rem .35rem;border-radius:.4rem}
    .fl-d-fechar:hover{color:var(--tinta)}

    /* ---- animações (leves: só transform/opacity; desligadas com "reduzir movimento") ---- */
    .fl-petala{transition:fill .35s ease, stroke .35s ease}
    .fl-petala.fl-trilha{fill:color-mix(in srgb, var(--c) 17%, transparent);stroke:color-mix(in srgb, var(--c) 65%, transparent)}
    .fl-linha{transition:opacity .25s, stroke-width .25s, stroke .25s}
    .fl-linha.fl-trilha{stroke:var(--c);stroke-width:3;opacity:1}
    .fl-l-area.fl-trilha{stroke-width:5.5}
    .fl-trilha .fl-bola{stroke:var(--tinta);stroke-width:2.6}
    .fl-bola{transform-box:fill-box;transform-origin:center;transition:r .2s, stroke-width .2s, transform .3s cubic-bezier(.34,1.56,.64,1)}
    .fl-no:hover .fl-bola,.fl-no:focus-visible .fl-bola{transform:scale(1.2)}
    .fl-pessoa:hover .fl-bola{transform:scale(1.08)}
    .fl-no:active .fl-bola{transform:scale(.92);transition-duration:.12s}
    .fl-nasce .fl-bola{animation:fl-pop .6s cubic-bezier(.34,1.56,.64,1) var(--atraso,0ms) both}
    .fl-nasce .fl-rot,.fl-nasce .fl-mais,.fl-nasce .fl-conta,.fl-nasce .fl-check{animation:fl-surge .45s ease calc(var(--atraso,0ms) + 120ms) both}
    .fl-linha.fl-nasce{animation:fl-surge .5s ease var(--atraso,0ms) both}
    .fl-onda{fill:none;stroke:var(--c);stroke-width:2;pointer-events:none;transform-box:fill-box;transform-origin:center;animation:fl-onda .65s cubic-bezier(.2,.7,.3,1) forwards}
    .fl-flor{transform-box:fill-box;transform-origin:center;animation:fl-cresce 1.3s cubic-bezier(.34,1.56,.64,1) both, fl-respira 7s ease-in-out 1.3s infinite}
    .fl-pessoa:hover .fl-flor path{fill:color-mix(in srgb, var(--prim) 22%, transparent)}
    .fl-flor path{transition:fill .4s}
    .fl-detalhe:not([hidden]){animation:fl-sobe .28s cubic-bezier(.2,.8,.3,1)}
    .fl-legenda li{animation:fl-sobe .4s cubic-bezier(.2,.8,.3,1) both}
    .fl-legenda li:nth-child(2){animation-delay:.05s}.fl-legenda li:nth-child(3){animation-delay:.1s}.fl-legenda li:nth-child(4){animation-delay:.15s}
    .fl-legenda li:nth-child(5){animation-delay:.2s}.fl-legenda li:nth-child(n+6){animation-delay:.25s}
    .fl-legenda button{transition:border-color .2s, transform .2s}
    .fl-legenda button:hover{transform:translateX(3px)}
    .fl-carregando:not([hidden]):not(.fl-erro)::before{content:'';display:block;width:2.6rem;height:2.6rem;margin:0 auto .8rem;border-radius:50% 0;background:color-mix(in srgb, var(--prim) 45%, transparent);animation:fl-gira 1.6s ease-in-out infinite}
    @keyframes fl-pop{from{transform:scale(0)}to{transform:scale(1)}}
    @keyframes fl-surge{from{opacity:0}to{opacity:1}}
    @keyframes fl-onda{from{transform:scale(1);opacity:.85}to{transform:scale(2.8);opacity:0}}
    @keyframes fl-cresce{from{transform:scale(0) rotate(-120deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}
    @keyframes fl-respira{0%,100%{transform:scale(1) rotate(0)}50%{transform:scale(1.045) rotate(8deg)}}
    @keyframes fl-sobe{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    @keyframes fl-gira{0%{transform:rotate(0) scale(.85)}50%{transform:rotate(180deg) scale(1.05)}100%{transform:rotate(360deg) scale(.85)}}
    @media (prefers-reduced-motion: reduce){
        .fl-palco *,.fl-palco *::before{animation:none!important;transition:none!important}
    }

    .fl-carregando{position:absolute;inset:0;display:grid;place-items:center;color:var(--tinta-2);font-size:.9rem;text-align:center;padding:2rem}
    .fl-carregando.fl-erro{color:#f87171}
    .fl-carregando[hidden],.fl-detalhe[hidden]{display:none}
    .fl-ajuda{position:absolute;left:50%;bottom:.6rem;transform:translateX(-50%);margin:0;font-size:.7rem;color:var(--tinta-2);text-align:center;pointer-events:none;white-space:nowrap}
    @media (max-width: 1100px){ .fl-ajuda{display:none} }
    @media (max-width: 640px){
        .fl-palco{width:calc(100vw - 1rem);height:calc(100vh - 190px)}
        .fl-legenda{display:none}
        .fl-ajuda{display:none}
        .fl-total{display:none}
    }
</style>

<div id="flower" class="fl-palco" data-url="{{ route('flower.dados') }}">
    <svg class="fl-svg" role="tree" aria-label="Mapa do seu workflow"></svg>

    <div class="fl-barra">
        <label class="fl-caixa" title="Buscar (atalho: /)">
            <svg class="w-4 h-4 shrink-0 texto-2" style="margin-left:.4rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
            <span class="sr-only">Buscar no Flower</span>
            <input type="search" class="fl-busca" placeholder="Buscar cartão, aba, tarefa…" autocomplete="off">
            <span class="fl-busca-n" aria-live="polite"></span>
        </label>
        <div class="fl-caixa" role="group" aria-label="Zoom">
            <button type="button" class="fl-bt fl-q" data-acao="menos" title="Afastar" aria-label="Afastar">−</button>
            <button type="button" class="fl-bt fl-q" data-acao="mais" title="Aproximar" aria-label="Aproximar">+</button>
            <button type="button" class="fl-bt" data-acao="ajustar" title="Ver a flor inteira">Ajustar</button>
        </div>
        <div class="fl-caixa" role="group" aria-label="Ramos">
            <button type="button" class="fl-bt" data-acao="tudo" title="Abrir pastas e listas de tarefas também">Abrir tudo</button>
            <button type="button" class="fl-bt" data-acao="inicio" title="Áreas e abas abertas">Padrão</button>
            <button type="button" class="fl-bt" data-acao="recolher" title="Só as áreas">Recolher</button>
            <button type="button" class="fl-bt" data-acao="tela" title="Tela cheia" aria-label="Tela cheia">⛶</button>
        </div>
        <span class="fl-total"></span>
    </div>
    <p class="fl-ajuda">Arraste para mover · roda do mouse aproxima · clique abre o ramo · duplo clique abre o cartão · / busca</p>

    <ul class="fl-legenda" aria-label="Áreas"></ul>

    <div class="fl-detalhe" hidden aria-live="polite">
        <button type="button" class="fl-d-fechar" aria-label="Fechar detalhe">×</button>
        <p class="fl-d-caminho"></p>
        <p class="fl-d-nome"></p>
        <p class="fl-d-info"></p>
        <div class="fl-d-acoes">
            <a class="fl-d-abrir" href="#">Abrir</a>
            <button type="button" class="fl-d-alternar">Expandir</button>
        </div>
    </div>

    <div class="fl-carregando" role="status">Desabrochando o seu Flower…</div>
</div>

<script src="{{ asset('js/flower.js') }}?v={{ @filemtime(public_path('js/flower.js')) }}" defer></script>
@endsection
