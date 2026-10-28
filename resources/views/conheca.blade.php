@extends('layouts.app')

@section('titulo', 'Conheça')

@section('conteudo')
@php
    // Paisagem desenhada em SVG: usada na edição de imagem e como fundo de exemplo (sem depender de arquivos)
    $paisagem = '<svg viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice" class="w-full h-full block" aria-hidden="true">'
        . '<defs><linearGradient id="demoCeu" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#fb923c"/></linearGradient></defs>'
        . '<rect width="400" height="250" fill="url(#demoCeu)"/><circle cx="285" cy="150" r="34" fill="#fde68a"/>'
        . '<path d="M0 190L90 110L160 175L240 100L330 185L400 140V250H0Z" fill="#312e81"/>'
        . '<path d="M0 220L70 170L150 215L230 165L320 220L400 190V250H0Z" fill="#14122e"/></svg>';
@endphp
<style>
    :root { --demo-top: @auth 4rem @else 0px @endauth; }
    #demo { position: relative; height: 1430vh; }
    .palco { position: sticky; top: var(--demo-top); height: calc(100svh - var(--demo-top)); overflow: hidden; }
    .cena {
        position: absolute; inset: 0; z-index: 1; display: grid; align-content: center; gap: 1.5rem;
        max-width: 72rem; margin: 0 auto; padding: 4.5rem 1.25rem 1.5rem;
        opacity: var(--v, 0); pointer-events: none;
    }
    .cena.on { pointer-events: auto; }
    @media (min-width: 1024px) { .cena { grid-template-columns: 5fr 7fr; gap: 3rem; padding: 5rem 2rem 2rem; } }
    .cena.centro { grid-template-columns: 1fr; justify-items: center; text-align: center; }

    /* Animação por scroll: cada elemento usa --s (início) e --e (fim), de 0 a 1 dentro da cena */
    .a, .d, .k { --k: clamp(0, calc((var(--t) - var(--s, 0)) / (var(--e, .3) - var(--s, 0))), 1); }
    .a { opacity: var(--k); transform: translate(calc((1 - var(--k)) * var(--dx, 0px)), calc((1 - var(--k)) * var(--dy, 24px))); }
    .d { stroke-dasharray: 1; stroke-dashoffset: calc(1 - var(--k)); }

    .tag { display: inline-block; font-size: .7rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
           color: var(--prim-forte); background: var(--prim-suave); border: 1px solid var(--prim-linha); border-radius: 9999px; padding: .2rem .65rem; }
    .arq { padding: .3rem .5rem; border-radius: .375rem; color: var(--tinta-2); transition: background .25s, color .25s; }
    .arq.ativo, .preset.ativo { background: var(--prim-suave); color: var(--prim-forte); border-color: var(--prim); }
    .codigo { white-space: pre; font: 12px/1.6 ui-monospace, Menlo, monospace; min-height: 11rem; }
    #prog { position: fixed; top: 0; left: 0; height: 3px; width: 0; z-index: 50; background: var(--prim); }

    /* Botão de reprodução automática */
    #auto { position: fixed; right: 1rem; bottom: 1rem; z-index: 60; box-shadow: 0 6px 20px rgba(0,0,0,.4); }

    /* Navegação por pontos */
    #pontos { position: fixed; right: .9rem; top: 50%; transform: translateY(-50%); z-index: 45; display: none; flex-direction: column; gap: .6rem; }
    @media (min-width: 640px) { #pontos { display: flex; } }
    #pontos button { position: relative; width: .55rem; height: .55rem; padding: 0; border: 0; border-radius: 9999px; background: var(--linha); cursor: pointer; transition: transform .2s, background .2s; }
    #pontos button::before { content: ""; position: absolute; inset: -.45rem; }
    #pontos button.on { background: var(--prim); transform: scale(1.6); }
    #pontos button:focus-visible { outline: 2px solid var(--prim); outline-offset: 3px; }

    /* Fundos que trocam atrás da cena "Fundos" */
    .fundos { position: absolute; inset: 0; z-index: 0; opacity: var(--fv, 0); pointer-events: none; }
    .fundos::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(0,0,0,.6), rgba(0,0,0,.25)); }
    .fd { position: absolute; inset: 0; overflow: hidden; }
    .bolha { position: absolute; border-radius: 9999px; filter: blur(40px); opacity: .8; animation: deriva 9s ease-in-out infinite alternate; }
    @keyframes deriva { to { transform: translate(8vw, -6vh) scale(1.25); } }
    @media (prefers-reduced-motion: reduce) { .bolha { animation: none; } }

    /* Texto da cena "Fundos": branco para ficar legível sobre qualquer fundo */
    .sobre-fundo .titulo,
    .sobre-fundo .texto-2 { color: #fff !important; text-shadow: 0 1px 12px rgba(0,0,0,.45); }
    .sobre-fundo .tag { color: #fff; background: rgba(255,255,255,.16); border-color: rgba(255,255,255,.45); text-shadow: none; }

    /* No celular o texto fica sobre o fundo em coluna: escurece o fundo todo, não só a esquerda */
    @media (max-width: 1023px) { .fundos::after { background: rgba(0,0,0,.45); } }

    /* Estudos */
    .rev { display: flex; align-items: center; gap: .6rem; padding: .45rem .65rem; border: 1px solid var(--linha); border-radius: .5rem; font-size: .8rem; }
    .rev .ck { margin-left: auto; opacity: 0; color: var(--prim-forte); transition: opacity .25s; }
    .rev.lig .ck { opacity: 1; }
    .rev.lig { border-color: var(--prim-linha); background: var(--prim-suave); }
    .flip { perspective: 900px; }
    .flip-i { position: relative; height: 4.5rem; transform-style: preserve-3d; transform: rotateY(calc(var(--k) * 180deg)); }
    .flip-i > div { position: absolute; inset: 0; display: flex; flex-direction: column; justify-content: center; padding: .75rem 1rem; border-radius: .6rem; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
    .flip-i .fa { background: var(--superficie-2); border: 1px solid var(--linha); }
    .flip-i .fb { background: var(--prim-suave); border: 1px solid var(--prim-linha); transform: rotateY(180deg); }
    .flip-i small { font-size: .65rem; opacity: .7; }
    .nota { flex: 1; text-align: center; font-size: .72rem; padding: .35rem 0; border: 1px solid var(--linha); border-radius: .4rem; transition: background .25s, color .25s, border-color .25s; }
    .nota.lig { background: var(--prim); color: var(--on-prim); border-color: var(--prim); }
    .bal { font-size: .8rem; line-height: 1.45; padding: .55rem .75rem; border-radius: .75rem; max-width: 92%; white-space: pre-wrap; }
    .bal.eu { margin-left: auto; background: var(--prim); color: var(--on-prim); border-bottom-right-radius: .2rem; }
    .bal.ia { background: var(--superficie-2); border: 1px solid var(--linha); border-bottom-left-radius: .2rem; min-height: 4.6rem; }

    /* PDF */
    .pdf-ico { display: grid; place-items: center; flex: none; width: 2.2rem; height: 2.8rem; border-radius: .3rem; background: #fff; color: #c0392b; border-top: 4px solid #c0392b; font: 700 .65rem/1 system-ui, sans-serif; }
    .btn[data-liga] { transition: box-shadow .25s, transform .25s; }
    .btn[data-liga].lig { box-shadow: 0 0 0 4px var(--prim-linha); transform: scale(.97); }
    .fim { opacity: 0; transition: opacity .25s; }
    .fim.lig { opacity: 1; }

    /* Currículo: três modelos que trocam sozinhos */
    .cv { display: grid; grid-template-columns: 7rem 1fr; min-height: 13rem; overflow: hidden; border: 1px solid var(--linha); border-radius: .5rem; background: #fff; color: #14171b; }
    .cv .texto-2 { color: #5b6573; }
    .cv-lado { padding: .75rem; background: var(--prim-suave); color: #fff; transition: background .4s, color .4s; }
    .cv-corpo { padding: .75rem; }
    .cv-foto { display: grid; place-items: center; width: 2.6rem; height: 2.6rem; margin-bottom: .5rem; border-radius: 9999px; background: var(--prim); color: var(--on-prim); font-size: 1.1rem; }
    .cv-sk { height: 4px; margin-top: .3rem; border-radius: 2px; background: color-mix(in srgb, currentColor 25%, transparent); }
    .cv-sk > span { display: block; height: 100%; border-radius: 2px; background: currentColor; opacity: .75; }
    .cv-linha { height: 5px; margin-top: .3rem; border-radius: 3px; background: #e3e7ec; }
    .cv:has(.mod li:nth-child(2).ativo) .cv-lado { background: var(--prim); color: var(--on-prim); }
    .cv:has(.mod li:nth-child(3).ativo) { grid-template-columns: 1fr 7rem; }
    .cv:has(.mod li:nth-child(3).ativo) .cv-lado { order: 2; }

    /* Encerramento: imagem promocional */
    .promo {
        padding: 1.5rem 1.25rem; text-align: center; border-radius: 1rem; border: 1px solid var(--prim-linha);
        background:
            radial-gradient(60% 55% at 50% 58%, color-mix(in srgb, var(--prim) 18%, transparent), transparent 72%),
            linear-gradient(color-mix(in srgb, var(--on-cab) 4%, transparent) 1px, transparent 1px) 0 0 / 28px 28px,
            linear-gradient(90deg, color-mix(in srgb, var(--on-cab) 4%, transparent) 1px, transparent 1px) 0 0 / 28px 28px,
            var(--superficie);
        box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }
    .promo svg text { font-family: 'DM Sans', system-ui, sans-serif; }

    /* Edição de imagem */
    .foto { position: relative; aspect-ratio: 16 / 9; overflow: hidden; border-radius: .5rem; background: #000; }
    .fx { width: 100%; height: 100%; }
    .corte { position: absolute; border: 2px dashed #fff; box-shadow: 0 0 0 100vmax rgba(0,0,0,.5); pointer-events: none; }
</style>

<div id="prog"></div>
<nav id="pontos" aria-label="Cenas da demonstração"></nav>
<button id="auto" type="button" class="btn" aria-pressed="false">▶ Reproduzir</button>

@guest
<div class="fixed top-0 inset-x-0 z-40 flex items-center justify-between px-5 sm:px-8 py-3">
    @include('partials.marca', ['texto' => 'text-xl'])
    <a href="{{ route('login') }}" class="btn-sec">Entrar</a>
</div>
@endguest

<div id="demo">
<div class="palco">

    {{-- Fundos que aparecem atrás da cena "Fundos" (troca com transição suave) --}}
    <div class="fundos" id="fundos" aria-hidden="true">
        <div class="fd" style="opacity:var(--f0,0);background:linear-gradient(135deg,#1e1b4b,#7c3aed 55%,#f472b6)"></div>
        <div class="fd" style="opacity:var(--f1,0)">{!! $paisagem !!}</div>
        <div class="fd" style="opacity:var(--f2,0);background:#0b1417">
            <i class="bolha" style="left:8%;top:15%;width:18rem;height:18rem;background:#2dd4bf"></i>
            <i class="bolha" style="right:4%;bottom:8%;width:22rem;height:22rem;background:#a78bfa;animation-delay:-3s"></i>
            <i class="bolha" style="left:45%;top:48%;width:14rem;height:14rem;background:#f472b6;animation-delay:-6s"></i>
        </div>
        <div class="fd" style="opacity:var(--f3,0);background:radial-gradient(circle,rgba(255,255,255,.14) 1px,transparent 1px) 0 0/22px 22px,#0b1417"></div>
    </div>

    {{-- 1 · Abertura --}}
    <section class="cena centro" data-nome="Início">
        <div>
            <div class="flex justify-center a" style="--s:0;--e:0;--dy:0px">@include('partials.flor', ['class' => 'w-48 sm:w-64 h-auto'])</div>
            <h1 class="titulo text-3xl sm:text-5xl leading-tight max-w-2xl mx-auto mt-6">Seu fluxo de vida unificado em um lugar</h1>
            <p class="texto-2 mt-3">Unifique. Customize. Produza.</p>
            <p class="text-sm mt-10 texto-2">Role para ver na prática ↓</p>
        </div>
    </section>

    {{-- 2 · Vida --}}
    <section class="cena" data-nome="Vida" data-s=".1" data-e=".9">
        <div>
            <span class="tag">Gerenciamento de vida</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Corpo e mente no mesmo painel</h2>
            <p class="texto-2 mt-3">Tarefas, treinos, cargas, dietas, água e metas. Cada área com as suas próprias métricas.</p>
        </div>
        <div class="card p-4 sm:p-5">
            <div class="flex justify-between mb-4" data-ciclo data-s=".15" data-e=".9">
                @foreach (['Tarefas', 'Treinos', 'Cargas', 'Dietas', 'Água', 'Metas'] as $n)
                    <div class="circ"><span class="circ-bola titulo">{{ mb_substr($n, 0, 1) }}</span><span class="circ-rotulo">{{ $n }}</span></div>
                @endforeach
            </div>
            <div class="tabela-wrap a" style="--s:.1;--e:.25">
                <table class="tabela">
                    <thead><tr><th>Tarefa</th><th>Área</th><th>Status</th></tr></thead>
                    <tbody>
                        <tr class="a" style="--s:.2;--e:.32"><td data-label="Tarefa">Treino de pernas</td><td data-label="Área">Corpo</td><td data-label="Status"><span class="select-status">Feito</span></td></tr>
                        <tr class="a" style="--s:.3;--e:.42"><td data-label="Tarefa">Revisar capítulo 4</td><td data-label="Área">Mente</td><td data-label="Status"><span class="select-status">Em andamento</span></td></tr>
                        <tr class="a" style="--s:.4;--e:.52"><td data-label="Tarefa">Fechar fatura do mês</td><td data-label="Área">Carteira</td><td data-label="Status"><span class="select-status">Pendente</span></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <div class="flex justify-between text-xs texto-2 mb-1"><span>Água hoje</span><span data-num="2.1" data-fmt="l" data-s=".5" data-e=".85">0</span></div>
                <div class="barra"><span class="k" style="--s:.5;--e:.85;width:calc(var(--k) * 84%)"></span></div>
            </div>
        </div>
    </section>

    {{-- 3 · Estudos: plano e cronograma de revisão --}}
    <section class="cena" data-nome="Estudos">
        <div>
            <span class="tag">Gerenciamento de estudos</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Estude com plano, não no improviso</h2>
            <p class="texto-2 mt-3">Matérias, metas de horas e um cronograma que distribui as revisões antes da prova.</p>
        </div>
        <div class="card p-4 sm:p-5 space-y-4">
            <div class="flex items-center gap-4">
                <svg viewBox="0 0 56 56" class="w-14 h-14 flex-none" fill="none" stroke-width="5" stroke-linecap="round" aria-hidden="true">
                    <circle cx="28" cy="28" r="22" stroke="var(--linha)"/>
                    <circle class="d" cx="28" cy="28" r="22" pathLength="1" stroke="var(--prim)" transform="rotate(-90 28 28)" style="--s:.1;--e:.6;stroke-dashoffset:calc(1 - var(--k) * .72)"/>
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="titulo text-lg">Cálculo II</p>
                    <p class="text-xs texto-2">Prova em 9 dias · <span data-num="72" data-fmt="pct" data-s=".1" data-e=".6">0%</span> do conteúdo estudado</p>
                </div>
            </div>
            <div class="space-y-3">
                @foreach ([['Limites', '6h de 8h', 75, .15], ['Derivadas', '4h de 8h', 50, .25], ['Integrais', '1h de 8h', 12, .35]] as [$m, $h, $pc, $s])
                    <div class="a" style="--s:{{ $s }};--e:{{ $s + .12 }}">
                        <div class="flex justify-between text-xs mb-1"><span>{{ $m }}</span><span class="texto-2">{{ $h }}</span></div>
                        <div class="barra"><span class="k" style="--s:{{ $s }};--e:{{ $s + .3 }};width:calc(var(--k) * {{ $pc }}%)"></span></div>
                    </div>
                @endforeach
            </div>
            <div class="space-y-1.5">
                <p class="text-xs texto-2 a" style="--s:.5;--e:.58">Cronograma de revisão</p>
                <div class="rev a" data-liga data-s=".75" style="--s:.52;--e:.62;--dy:10px"><b>Hoje</b><span class="texto-2">Limites</span><span class="ck">✓</span></div>
                <div class="rev a" data-liga data-s=".85" style="--s:.62;--e:.72;--dy:10px"><b>Em 3 dias</b><span class="texto-2">Derivadas</span><span class="ck">✓</span></div>
                <div class="rev a" style="--s:.72;--e:.82;--dy:10px"><b>Em 7 dias</b><span class="texto-2">Integrais</span><span class="ck">✓</span></div>
            </div>
        </div>
    </section>

    {{-- 4 · Revisão e auxílio --}}
    <section class="cena" data-nome="Revisão">
        <div>
            <span class="tag">Revisão e auxílio</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Revise na hora certa, com ajuda quando travar</h2>
            <p class="texto-2 mt-3">Cartões de revisão espaçada e um assistente que explica o ponto em que você parou.</p>
        </div>
        <div class="card p-4 sm:p-5 space-y-3">
            <div class="flip a" style="--s:.05;--e:.12;--dy:10px">
                <div class="k flip-i" style="--s:.16;--e:.32">
                    <div class="fa"><small>Pergunta</small><b class="text-sm">Qual a derivada de sen(x)?</b></div>
                    <div class="fb"><small>Resposta</small><b class="text-sm">cos(x)</b></div>
                </div>
            </div>
            <div class="flex gap-1.5">
                <span class="nota">Errei</span><span class="nota">Difícil</span>
                <span class="nota" data-liga data-s=".42">Bom</span><span class="nota">Fácil</span>
            </div>
            <p class="text-xs link-prim a" style="--s:.44;--e:.52;--dy:6px">Próxima revisão em 4 dias</p>
            <div class="space-y-2 pt-1">
                <div class="bal eu a" style="--s:.54;--e:.6;--dy:10px">Não entendi a regra da cadeia.</div>
                <div class="bal ia" data-tipo="Pense em duas funções encaixadas: derive a de fora e multiplique pela derivada da de dentro. Em sen(2x), o resultado é cos(2x) · 2." data-s=".62" data-e=".92"></div>
            </div>
        </div>
    </section>

    {{-- 5 · Mapa mental --}}
    <section class="cena" data-nome="Mapas mentais">
        <div>
            <span class="tag">Mapas mentais</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Pense solto, organize depois</h2>
            <p class="texto-2 mt-3">Desenhe ideias, ligue conceitos e leve o mapa para qualquer quadro, em qualquer área.</p>
        </div>
        <div class="card p-3">
            <svg viewBox="0 0 480 300" class="w-full h-auto" fill="none" stroke="var(--prim)" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path class="d" pathLength="1" style="--s:.15;--e:.35" d="M240 150C180 150 140 60 90 60"/>
                <path class="d" pathLength="1" style="--s:.25;--e:.45" d="M240 150C300 150 340 60 390 60"/>
                <path class="d" pathLength="1" style="--s:.35;--e:.55" d="M240 150C180 150 140 240 90 240"/>
                <path class="d" pathLength="1" style="--s:.45;--e:.65" d="M240 150C300 150 340 240 390 240"/>
                <g class="a" style="--s:.1;--e:.2;--dy:0px"><rect x="185" y="132" width="110" height="36" rx="18" fill="var(--prim)" stroke="none"/><text x="240" y="155" text-anchor="middle" fill="var(--on-prim)" stroke="none" font-size="14" font-weight="700">Work Flower</text></g>
                @foreach ([[90, 60, 'Corpo', .3], [390, 60, 'Mente', .4], [90, 240, 'Carteira', .5], [390, 240, 'Diverso', .6]] as [$x, $y, $nome, $s])
                    <g class="a" style="--s:{{ $s }};--e:{{ $s + .12 }};--dy:6px"><rect x="{{ $x - 48 }}" y="{{ $y - 16 }}" width="96" height="32" rx="16" fill="var(--prim-suave)" stroke="var(--prim-linha)"/><text x="{{ $x }}" y="{{ $y + 5 }}" text-anchor="middle" fill="var(--tinta)" stroke="none" font-size="13">{{ $nome }}</text></g>
                @endforeach
            </svg>
        </div>
    </section>

    {{-- 6 · Programação --}}
    <section class="cena" data-nome="Programação">
        <div>
            <span class="tag">Programação</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Seu editor dentro do fluxo</h2>
            <p class="texto-2 mt-3">Arquivos, código e terminal ao lado das suas tarefas, com painéis que você redimensiona.</p>
        </div>
        <div class="card overflow-hidden">
            <div class="grid grid-cols-[8rem_1fr] text-xs">
                <ul class="p-2 space-y-1" style="background:var(--superficie-2);border-right:1px solid var(--linha)" data-ciclo data-s=".1" data-e=".8">
                    <li class="arq">Pedido.java</li><li class="arq">index.blade.php</li><li class="arq">rotas.php</li>
                </ul>
                <pre class="codigo p-4" data-tipo="public class Pedido {
  private double total;

  public double calcular() {
    return total * 1.1;
  }
}" data-s=".15" data-e=".7"></pre>
            </div>
            <div class="a p-3 font-mono text-xs" style="--s:.72;--e:.85;background:var(--papel);border-top:1px solid var(--linha)">
                <span class="texto-2">$</span> javac Pedido.java <span class="link-prim">✓ compilado</span>
            </div>
        </div>
    </section>

    {{-- 7 · Documentos e PDF --}}
    <section class="cena" data-nome="Documentos e PDF">
        <div>
            <span class="tag">Documentos e PDF</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Monte o documento e exporte em PDF</h2>
            <p class="texto-2 mt-3">Blocos de texto, tabela, imagem e assinatura. Arraste, redimensione e salve na biblioteca para reaproveitar.</p>
        </div>
        <div class="card p-4 sm:p-5 grid sm:grid-cols-2 gap-3">
            <div class="card p-3 sm:col-span-2 a" style="--s:.08;--e:.2;--dy:-20px"><h3 class="titulo text-lg">Contrato de prestação de serviço</h3></div>
            <div class="card p-3 hidden sm:block a" style="--s:.18;--e:.34;--dx:-60px;--dy:0px">
                <p class="text-xs texto-2 mb-1">Texto</p>
                <p class="text-sm">Pelo presente instrumento, as partes acordam o escopo, o prazo e as condições de pagamento abaixo.</p>
            </div>
            <div class="tabela-wrap sm:col-start-2 a" style="--s:.3;--e:.46;--dx:60px;--dy:0px">
                <table class="tabela"><thead><tr><th>Item</th><th>Valor</th></tr></thead>
                <tbody><tr><td data-label="Item">Design</td><td data-label="Valor">R$ 1.200</td></tr><tr><td data-label="Item">Entrega</td><td data-label="Valor">R$ 800</td></tr></tbody></table>
            </div>
            <div class="sm:col-span-2 flex items-end justify-between gap-3 pt-1">
                <div>
                    <svg viewBox="0 0 200 40" class="w-40 h-9" fill="none" stroke="var(--prim-forte)" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path class="d" pathLength="1" style="--s:.48;--e:.64" d="M5 30C20 5 30 5 35 25S50 35 60 15 80 30 100 20 140 28 190 18"/></svg>
                    <p class="text-xs texto-2">Assinatura</p>
                </div>
                <span class="btn" data-liga data-s=".66">Exportar PDF</span>
            </div>
            <div class="sm:col-span-2 card p-3 flex items-center gap-3 a" style="--s:.72;--e:.82;--dy:12px">
                <span class="pdf-ico">PDF</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">contrato-prestacao.pdf</p>
                    <div class="barra mt-1.5"><span class="k" style="--s:.74;--e:.9;width:calc(var(--k) * 100%)"></span></div>
                </div>
                <span class="text-xs link-prim fim" data-liga data-s=".9">Pronto ✓</span>
            </div>
        </div>
    </section>

    {{-- 8 · Currículo --}}
    <section class="cena" data-nome="Currículo">
        <div>
            <span class="tag">Currículos</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Currículo pronto, em qualquer modelo</h2>
            <p class="texto-2 mt-3">Preencha uma vez e troque o modelo com um clique. Seus dados continuam no lugar.</p>
        </div>
        <div class="card p-3 sm:p-4">
            <ul class="mod flex gap-1.5 mb-3 text-xs" data-ciclo data-s=".4" data-e=".95">
                <li class="arq">Clássico</li><li class="arq">Moderno</li><li class="arq">Lateral</li>
            </ul>
            <div class="cv">
                <aside class="cv-lado">
                    <span class="cv-foto titulo">M</span>
                    <p class="font-semibold text-sm" style="min-height:1.25rem" data-tipo="Marina Alves" data-s=".08" data-e=".24"></p>
                    <p class="text-xs" style="opacity:.8">Designer de produto</p>
                    <div class="mt-3 text-xs a" style="--s:.22;--e:.3">Habilidades</div>
                    @foreach ([['Figma', 90, .26], ['Pesquisa', 75, .32], ['Prototipação', 82, .38]] as [$h, $pc, $s])
                        <div class="cv-sk"><span class="k" style="--s:{{ $s }};--e:{{ $s + .12 }};width:calc(var(--k) * {{ $pc }}%)"></span></div>
                    @endforeach
                </aside>
                <div class="cv-corpo">
                    <p class="text-xs texto-2 a" style="--s:.2;--e:.28">Experiência</p>
                    @foreach ([['Designer sênior · Estúdio Flor', .26], ['Designer pleno · Agência Norte', .34]] as [$cargo, $s])
                        <div class="a mt-2" style="--s:{{ $s }};--e:{{ $s + .1 }}"><p class="text-sm font-medium">{{ $cargo }}</p><div class="cv-linha" style="width:92%"></div><div class="cv-linha" style="width:70%"></div></div>
                    @endforeach
                    <p class="text-xs texto-2 mt-3 a" style="--s:.42;--e:.5">Formação</p>
                    <div class="a mt-1" style="--s:.46;--e:.56"><p class="text-sm font-medium">Design Digital · Universidade Federal</p><div class="cv-linha" style="width:55%"></div></div>
                </div>
            </div>
        </div>
    </section>

    {{-- 9 · Edição de imagem --}}
    <section class="cena" data-nome="Imagem">
        <div>
            <span class="tag">Edição de imagem</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Ajuste a foto sem sair do painel</h2>
            <p class="texto-2 mt-3">Brilho, contraste, cor, corte e texto. A imagem corrigida vai direto para o documento ou o fundo.</p>
        </div>
        <div class="card p-3 sm:p-4 space-y-3">
            <ul class="flex flex-wrap gap-1.5 text-xs" data-ciclo data-s=".1" data-e=".85">
                <li class="arq">Brilho</li><li class="arq">Contraste</li><li class="arq">Cor</li><li class="arq">Cortar</li><li class="arq">Texto</li>
            </ul>
            <div class="foto">
                <div class="k fx" style="--s:.12;--e:.25;filter:brightness(calc(.65 + var(--k) * .4))">
                    <div class="k fx" style="--s:.27;--e:.4;filter:contrast(calc(.8 + var(--k) * .3))">
                        <div class="k fx" style="--s:.42;--e:.55;filter:saturate(calc(.25 + var(--k) * 1.1))">
                            {!! $paisagem !!}
                        </div>
                    </div>
                </div>
                <div class="corte k" style="--s:.57;--e:.7;inset:calc(var(--k) * 8%) calc(var(--k) * 14%)"></div>
                <span class="a" style="--s:.72;--e:.84;--dy:8px;position:absolute;left:18%;bottom:14%;color:#fff;font-weight:700;font-size:1.15rem;text-shadow:0 2px 10px rgba(0,0,0,.6)">Verão 26</span>
            </div>
            <div class="grid grid-cols-3 gap-3 text-xs">
                @foreach ([['Brilho', 40, .12], ['Contraste', 30, .27], ['Cor', 110, .42]] as [$r, $val, $s])
                    <div>
                        <div class="flex justify-between mb-1"><span>{{ $r }}</span><span class="texto-2" data-num="{{ $val }}" data-fmt="mais" data-s="{{ $s }}" data-e="{{ $s + .13 }}">+0</span></div>
                        <div class="barra"><span class="k" style="--s:{{ $s }};--e:{{ $s + .13 }};width:calc(var(--k) * 100%)"></span></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 10 · Negócios --}}
    <section class="cena" data-nome="Negócios">
        <div>
            <span class="tag">Gerenciamento de negócios</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Carteira: o caixa sempre à vista</h2>
            <p class="texto-2 mt-3">Contas, lançamentos e saldos num só lugar, com os bancos conectados via Open Finance.</p>
        </div>
        <div class="card p-4 sm:p-5 space-y-4">
            <div class="grid grid-cols-3 gap-3">
                @foreach ([['Saldo', 12480.9, .1], ['Entradas', 8200, .2], ['Saídas', 3150.4, .3]] as [$rot, $val, $s])
                    <div class="card p-3"><p class="text-xs texto-2">{{ $rot }}</p><p class="titulo text-base sm:text-xl" data-num="{{ $val }}" data-fmt="brl" data-s="{{ $s }}" data-e="{{ $s + .35 }}">R$ 0,00</p></div>
                @endforeach
            </div>
            <div class="flex items-end gap-2 h-24">
                @foreach ([40, 62, 48, 80, 66, 92] as $i => $h)
                    <span class="k flex-1 rounded-t" style="--s:{{ .3 + $i * .06 }};--e:{{ .45 + $i * .06 }};height:calc(var(--k) * {{ $h }}%);background:var(--prim)"></span>
                @endforeach
            </div>
            <div class="tabela-wrap a" style="--s:.6;--e:.8">
                <table class="tabela"><thead><tr><th>Lançamento</th><th>Valor</th></tr></thead>
                <tbody><tr><td data-label="Lançamento">Cliente · projeto site</td><td data-label="Valor">+ R$ 2.400,00</td></tr><tr><td data-label="Lançamento">Hospedagem</td><td data-label="Valor">− R$ 89,90</td></tr></tbody></table>
            </div>
        </div>
    </section>

    {{-- 11 · Fundos: a página inteira troca de fundo --}}
    <section class="cena" data-nome="Fundos" data-fundo data-s=".12" data-e=".9">
        <div class="sobre-fundo">
            <span class="tag">Fundos</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Cor, imagem ou GIF animado no fundo</h2>
            <p class="texto-2 mt-3">Escolha um fundo e a página inteira muda na hora. Envie sua imagem ou um GIF e ele roda atrás de tudo.</p>
        </div>
        <div class="card p-4 sm:p-5 space-y-4">
            <h3 class="titulo text-lg">Fundo da página</h3>
            <div class="grid grid-cols-2 gap-2" data-ciclo data-s=".12" data-e=".9">
                <div class="preset"><span class="block h-10 rounded mb-1.5" style="background:linear-gradient(135deg,#1e1b4b,#7c3aed 55%,#f472b6)"></span><span class="text-xs">Gradiente</span></div>
                <div class="preset"><span class="block h-10 rounded mb-1.5" style="background:linear-gradient(#1e3a8a,#fb923c)"></span><span class="text-xs">Imagem</span></div>
                <div class="preset"><span class="block h-10 rounded mb-1.5" style="background:radial-gradient(circle at 25% 40%,#2dd4bf 0,transparent 45%),radial-gradient(circle at 75% 60%,#a78bfa 0,transparent 45%),#0b1417"></span><span class="text-xs">GIF animado</span></div>
                <div class="preset"><span class="block h-10 rounded mb-1.5" style="background:radial-gradient(circle,rgba(255,255,255,.3) 1px,transparent 1px) 0 0/8px 8px,#0b1417"></span><span class="text-xs">Padrão</span></div>
            </div>
            <div>
                <div class="flex justify-between text-xs texto-2 mb-1"><span>Enviando fundo.gif</span><span data-num="100" data-fmt="pct" data-s=".1" data-e=".4">0%</span></div>
                <div class="barra"><span class="k" style="--s:.1;--e:.4;width:calc(var(--k) * 100%)"></span></div>
            </div>
        </div>
    </section>

    {{-- 12 · Customização e chamada --}}
    <section class="cena" data-nome="Cores" data-tema data-s=".1" data-e=".9">
        <div>
            <span class="tag">Customização</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3">Tudo com a sua cara</h2>
            <p class="texto-2 mt-3">Cores, fundo e blocos do seu jeito. Comece de graça.</p>
            <div class="flex flex-wrap gap-2 mt-5">
                <a href="{{ route('registro') }}" class="btn">Criar conta</a>
                <a href="{{ route('login') }}" class="btn-sec">Já tenho conta</a>
            </div>
        </div>
        <div class="card p-4 sm:p-5">
            <h3 class="titulo text-lg mb-3">Cores e fundo</h3>
            <div class="grid grid-cols-3 gap-2" data-ciclo data-s=".1" data-e=".9">
                @foreach ([['Sinal', '#f2c230', '#0e1319'], ['Caverna', '#2dd4bf', '#0b1417'], ['Neon', '#a78bfa', '#100d1c'], ['Brasa', '#fb923c', '#161009'], ['Rosé', '#f472b6', '#170b12'], ['Aço', '#94a3b8', '#0c1118']] as [$nome, $p, $c])
                    <div class="preset" data-p="{{ $p }}" data-c="{{ $c }}">
                        <span class="flex h-6 rounded overflow-hidden mb-1.5"><span class="flex-1" style="background:{{ $c }}"></span><span class="flex-1" style="background:{{ $p }}"></span></span>
                        <span class="text-xs">{{ $nome }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 13 · Encerramento: imagem promocional --}}
    @php
        // Criadores ao redor (elipse). Cada um liga só às áreas que realmente usa (a primeira é a principal).
        $criadores = [['Atletas', 270, 84, ['Corpo']], ['Estudantes', 315, 100, ['Mente', 'Corpo']],
                      ['Escritores', 0, 96, ['Mente']], ['Programadores', 45, 122, ['Mente', 'Carteira']],
                      ['Empreendedores', 90, 138, ['Carteira']], ['Videomakers', 135, 108, ['Carteira', 'Diverso']],
                      ['Músicos', 180, 86, ['Diverso']], ['Designers', 225, 92, ['Diverso']]];
        foreach ($criadores as $i => [$nome, $ang, $w, $lig]) {
            $criadores[$i] = [$nome, $w, round(400 + 300 * cos(deg2rad($ang)), 1), round(210 + 165 * sin(deg2rad($ang)), 1), $lig];
        }
        // As quatro áreas formam o anel interno, em volta do Work Flower
        $areasPromo = [['Corpo', -90], ['Mente', 0], ['Carteira', 90], ['Diverso', 180]];
        $posArea = [];
        foreach ($areasPromo as $i => [$nome, $ang]) {
            $areasPromo[$i] = [$nome, round(400 + 100 * cos(deg2rad($ang)), 1), round(210 + 100 * sin(deg2rad($ang)), 1)];
            $posArea[$nome] = [$areasPromo[$i][1], $areasPromo[$i][2]];
        }
    @endphp
    <section class="cena centro" data-nome="Work Flower">
        <div class="promo w-full max-w-3xl">
            <span class="tag a" style="--s:0;--e:.08;--dy:8px">Para criadores de todas as áreas</span>
            <h2 class="titulo text-2xl sm:text-4xl mt-3 a" style="--s:.02;--e:.14">O Work Flower organiza a sua vida</h2>
            <p class="texto-2 mt-2 max-w-xl mx-auto text-sm sm:text-base a" style="--s:.1;--e:.2">Design, código, estudo, treino, música, escrita e negócios no mesmo fluxo. Cada criador com o seu painel, as suas cores e as suas metas.</p>

            <svg viewBox="0 0 800 420" class="hidden sm:block w-full h-auto mx-auto mt-3" style="max-height:44svh" fill="none" stroke="var(--prim-linha)" stroke-width="1.5" stroke-linecap="round" role="img"
                 aria-label="Work Flower no centro, com as áreas Corpo, Mente, Carteira e Diverso, cada uma ligada aos criadores que a usam">
                <ellipse class="a" cx="400" cy="210" rx="300" ry="165" stroke-dasharray="3 7" style="--s:.1;--e:.3;--dy:0px"/>
                <circle class="a" cx="400" cy="210" r="100" stroke-dasharray="3 7" style="--s:.1;--e:.3;--dy:0px"/>

                {{-- Work Flower → áreas --}}
                @foreach ($areasPromo as $i => [$nome, $x, $y])
                    <path class="d" pathLength="1" stroke-width="2.5" style="--s:{{ .12 + $i * .03 }};--e:{{ .24 + $i * .03 }}" d="M400 210L{{ $x }} {{ $y }}"/>
                @endforeach

                {{-- Áreas → criadores que as usam (principal forte, secundária suave) --}}
                @php $n = 0; @endphp
                @foreach ($criadores as [$nome, $w, $x, $y, $lig])
                    @foreach ($lig as $j => $area)
                        <path class="d" pathLength="1" stroke-opacity="{{ $j ? .4 : 1 }}" style="--s:{{ .2 + $n * .025 }};--e:{{ .36 + $n * .025 }}" d="M{{ $posArea[$area][0] }} {{ $posArea[$area][1] }}L{{ $x }} {{ $y }}"/>
                        @php $n++; @endphp
                    @endforeach
                @endforeach

                @foreach ($areasPromo as $i => [$nome, $x, $y])
                    <g class="a" style="--s:{{ .2 + $i * .05 }};--e:{{ .32 + $i * .05 }};--dy:6px">
                        <rect x="{{ $x - 42 }}" y="{{ $y - 14 }}" width="84" height="28" rx="14" fill="var(--prim)" stroke="none"/>
                        <text x="{{ $x }}" y="{{ $y + 4.5 }}" text-anchor="middle" fill="var(--on-prim)" stroke="none" font-size="13" font-weight="600">{{ $nome }}</text>
                    </g>
                @endforeach

                <g class="a" style="--s:.04;--e:.16;--dy:0px">
                    @foreach ([0, 60, 120, 180, 240, 300] as $g)
                        <ellipse cx="400" cy="172" rx="15" ry="30" fill="var(--prim)" fill-opacity=".3" stroke="none" transform="rotate({{ $g }} 400 210)"/>
                    @endforeach
                    <circle cx="400" cy="210" r="38" fill="var(--cab)" stroke="var(--prim)" stroke-width="3"/>
                    <text x="400" y="207" text-anchor="middle" fill="var(--tinta)" stroke="none" font-size="13" font-weight="700">Work</text>
                    <text x="400" y="223" text-anchor="middle" fill="var(--tinta)" stroke="none" font-size="13" font-weight="700">Flower</text>
                </g>

                @foreach ($criadores as $i => [$nome, $w, $x, $y])
                    <g class="a" style="--s:{{ .3 + $i * .05 }};--e:{{ .42 + $i * .05 }};--dy:8px">
                        <rect x="{{ $x - $w / 2 }}" y="{{ $y - 15 }}" width="{{ $w }}" height="30" rx="15" fill="var(--prim-suave)" stroke="var(--prim-linha)"/>
                        <text x="{{ $x }}" y="{{ $y + 4.5 }}" text-anchor="middle" fill="var(--tinta)" stroke="none" font-size="13">{{ $nome }}</text>
                    </g>
                @endforeach
            </svg>

            {{-- Celular: o mesmo conteúdo em lista, para o texto continuar legível --}}
            <div class="sm:hidden mt-4 space-y-3 a" style="--s:.15;--e:.3">
                <div class="flex flex-wrap justify-center gap-1.5">
                    @foreach ($areasPromo as [$nome])<span class="btn !py-1 !px-3 !text-xs">{{ $nome }}</span>@endforeach
                </div>
                <div class="flex flex-wrap justify-center gap-1.5">
                    @foreach ($criadores as [$nome])<span class="tag">{{ $nome }}</span>@endforeach
                </div>
            </div>

            <p class="text-xs sm:text-sm mt-3 a" style="--s:.62;--e:.72;--dy:8px">Corpo · Mente · Carteira · Diverso. <b>Tudo num só lugar, de graça para começar.</b></p>
            <div class="flex flex-wrap justify-center gap-2 mt-4 a" style="--s:.68;--e:.8;--dy:10px">
                <a href="{{ route('registro') }}" class="btn">Criar conta grátis</a>
                <a href="{{ route('login') }}" class="btn-sec">Já tenho conta</a>
                <button type="button" id="rever" class="btn-sec">↻ Ver de novo</button>
            </div>
        </div>
    </section>

</div>
</div>

<script>
(() => {
    const demo = document.getElementById('demo'), prog = document.getElementById('prog'), fundos = document.getElementById('fundos'), pontos = document.getElementById('pontos');
    const cenas = [...demo.querySelectorAll('.cena')];
    const P = [...demo.querySelectorAll('.preset[data-p]')].map(b => [b.dataset.p, b.dataset.c]);
    const nFundos = fundos.children.length;
    const clamp = x => Math.min(1, Math.max(0, x));
    const k = (el, t) => clamp((t - (+el.dataset.s || 0)) / ((+el.dataset.e || 1) - (+el.dataset.s || 0)));
    const lum = h => { const n = parseInt(h.slice(1), 16); return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255 > 0.6 ? '#14171b' : '#ffffff'; };
    const fmt = (e, x) => {
        const f = e.dataset.fmt;
        if (f === 'l') return x.toFixed(1).replace('.', ',') + ' L';
        if (f === 'pct') return Math.round(x) + '%';
        if (f === 'mais') return '+' + Math.round(x);
        return x.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };
    let tema = -2;

    // Mesma troca de cores do painel "Cores e fundo" (variáveis na raiz); fora da última cena volta ao tema do usuário
    function setTema(i) {
        if (i === tema) return;
        tema = i;
        const r = document.documentElement.style;
        if (i < 0) { ['--prim', '--cab', '--on-prim', '--on-cab'].forEach(v => r.removeProperty(v)); return; }
        const [p, c] = P[i];
        r.setProperty('--prim', p); r.setProperty('--cab', c);
        r.setProperty('--on-prim', lum(p)); r.setProperty('--on-cab', lum(c));
    }

    // Pontos de navegação: clicar leva até o final da animação daquela cena
    const botoes = cenas.map((c, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.title = c.dataset.nome || ('Cena ' + (i + 1));
        b.setAttribute('aria-label', b.title);
        b.addEventListener('click', () => {
            const topo = demo.getBoundingClientRect().top + scrollY;
            const alvo = topo + ((i === 0 ? 0 : i + .85) / cenas.length) * (demo.offsetHeight - innerHeight);
            scrollTo({ top: alvo, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        });
        pontos.appendChild(b);
        return b;
    });

    function frame() {
        const r = demo.getBoundingClientRect();
        const p = clamp(-r.top / (r.height - innerHeight)), n = cenas.length, f = p * n;
        prog.style.width = (p * 100) + '%';
        botoes.forEach((b, i) => b.classList.toggle('on', i === Math.min(n - 1, Math.floor(f))));
        let idx = -1, fv = 0, fpos = 0;
        cenas.forEach((c, i) => {
            const t = clamp(f - i);
            let v = Math.min(1, (1 - t) * 8, t * 8);
            if (i === 0) v = Math.min(1, (1 - t) * 8);
            if (i === n - 1) v = Math.min(1, t * 8);
            c.style.setProperty('--t', t.toFixed(4));
            c.style.setProperty('--v', v.toFixed(3));
            c.classList.toggle('on', v > .5);
            if (v <= 0) return;
            c.querySelectorAll('[data-num]').forEach(e => { e.textContent = fmt(e, +e.dataset.num * k(e, t)); });
            c.querySelectorAll('[data-tipo]').forEach(e => { e.textContent = e.dataset.tipo.slice(0, Math.round(e.dataset.tipo.length * k(e, t))); });
            c.querySelectorAll('[data-ciclo]').forEach(e => {
                const m = e.children.length, j = Math.min(m - 1, Math.floor(k(e, t) * m));
                [...e.children].forEach((x, q) => x.classList.toggle('ativo', q === j));
            });
            c.querySelectorAll('[data-liga]').forEach(e => e.classList.toggle('lig', t >= +e.dataset.s));
            if (c.hasAttribute('data-fundo')) { fv = v; fpos = k(c, t) * (nFundos - 1); }
            if (c.hasAttribute('data-tema') && t > .05) idx = Math.min(P.length - 1, Math.floor(k(c, t) * P.length));
        });
        // Fundos: troca com transição suave entre um e o próximo
        fundos.style.setProperty('--fv', fv.toFixed(3));
        for (let j = 0; j < nFundos; j++) fundos.style.setProperty('--f' + j, clamp(1 - Math.abs(fpos - j)).toFixed(3));
        setTema(idx);
    }

    let pend = false;
    const pede = () => { if (!pend) { pend = true; requestAnimationFrame(() => { pend = false; frame(); }); } };
    addEventListener('scroll', pede, { passive: true });
    addEventListener('resize', pede);
    frame();

    // ===== Reprodução automática =====
    const auto = document.getElementById('auto');
    const SEG_POR_CENA = 7;   // ritmo: segundos por cena
    const PAUSA_MS = 1400;    // pausa curta quando a animação da cena termina
    let rodando = false, ultimo = 0, pos = 0, pausaAte = 0, cenaPausada = -1;

    const tamanho = () => demo.offsetHeight - innerHeight;
    const topoDemo = () => demo.getBoundingClientRect().top + scrollY;
    const fAtual = () => ((scrollY - topoDemo()) / tamanho()) * cenas.length;

    function parar() {
        if (!rodando) return;
        rodando = false;
        auto.textContent = '▶ Reproduzir';
        auto.setAttribute('aria-pressed', 'false');
    }

    function passo(agora) {
        if (!rodando) return;
        const dt = Math.min(64, agora - ultimo);
        ultimo = agora;

        if (agora >= pausaAte) {
            pos += (tamanho() / cenas.length) * (dt / 1000) / SEG_POR_CENA;
            const fim = topoDemo() + tamanho();
            if (pos >= fim) { scrollTo({ top: fim, behavior: 'instant' }); return parar(); }
            scrollTo({ top: pos, behavior: 'instant' });

            // ao fim da animação de cada cena, segura um instante para dar tempo de ler
            const f = fAtual(), i = Math.floor(f);
            if (f - i >= .9 && i !== cenaPausada && i < cenas.length - 1) {
                cenaPausada = i;
                pausaAte = agora + PAUSA_MS;
            }
        }
        requestAnimationFrame(passo);
    }

    auto.addEventListener('click', () => {
        if (rodando) return parar();
        if (fAtual() >= cenas.length - .02) scrollTo({ top: topoDemo(), behavior: 'instant' }); // terminou: recomeça
        const f = fAtual();
        pos = scrollY;
        cenaPausada = f - Math.floor(f) >= .9 ? Math.floor(f) : -1;
        pausaAte = 0;
        ultimo = performance.now();
        rodando = true;
        auto.textContent = '❚❚ Pausar';
        auto.setAttribute('aria-pressed', 'true');
        requestAnimationFrame(passo);
    });

    // Se a pessoa rolar, tocar ou usar o teclado, a reprodução para e ela assume o controle
    ['wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(ev =>
        addEventListener(ev, e => { if (!e.target.closest?.('#auto')) parar(); }, { passive: true }));

    // "Ver de novo" na imagem final: volta ao início
    document.getElementById('rever')?.addEventListener('click', () =>
        scrollTo({ top: topoDemo(), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }));
})();
</script>
@endsection
