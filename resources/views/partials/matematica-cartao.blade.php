{{-- Conteúdo do cartão "Matemática e gráficos" (Mente). Lógica em public/js/estudo.js (métodos mt*; math.js e nerdamer vêm de CDN).
     Uma conta por linha com a resposta ao lado enquanto digita (clique na resposta para copiar). Variáveis, funções, unidades,
     porcentagem, "total"/"média" do bloco, derivar, integrar, simplificar, resolver, fatorar, expandir, limite e "gráfico f(x)".
     Teclado de símbolos (escreve no cursor), exemplos clicáveis por assunto, erro explicado em português (na linha e na faixa
     embaixo) e o campo "Desenhar y =" para pôr funções no gráfico sem escrever no texto. O gráfico arrasta e aproxima com a rodinha. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm" x-init="mtGarantir(b)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <label class="flex items-center gap-1" title="2,5 é dois e meio; separe argumentos com ponto e vírgula: max(1; 2)"><input type="checkbox" x-model="b.dados.virgula"> Vírgula decimal</label>
        <label class="flex items-center gap-1" title="sen, cos e tg em graus"><input type="checkbox" x-model="b.dados.graus"> Graus</label>
        <button type="button" class="btn-sec !py-0.5" :class="mtUI(b).teclado && 'qz-on'" @click="mtUI(b).teclado = !mtUI(b).teclado" title="Teclado de símbolos">∑ Teclado</button>
        <button type="button" class="btn-sec !py-0.5" :class="mtUI(b).ajuda && 'qz-on'" @click="mtUI(b).ajuda = !mtUI(b).ajuda">Exemplos</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.verGraf && 'qz-on'" @click="b.dados.verGraf = !b.dados.verGraf">Gráfico</button>
        <span class="texto-2" x-show="mtStatus()" x-text="mtStatus()"></span>
    </div>

    <template x-if="mtUI(b).ajuda || !String(b.dados.texto || '').trim()">
        <div class="mt-ajuda text-xs">
            <p class="texto-2">Clique num exemplo para pôr no fim do texto; a resposta aparece ao lado.</p>
            <template x-for="g in mtGruposExemplos()" :key="g.nome">
                <div class="mt-grupo">
                    <b class="texto-2" x-text="g.nome"></b>
                    <template x-for="e in g.itens" :key="e">
                        <button type="button" class="qz-chip font-mono" @click="mtInserir(b, e)" x-text="e"></button>
                    </template>
                </div>
            </template>
            <p class="texto-2 mt-1">Use <code>//</code> para comentários e <code>Rótulo: conta</code> para dar nome à linha. Linha em branco separa os blocos de <code>total</code> e <code>média</code>; <code>ans</code> é a última resposta. Funções: sen, cos, tg, ln (natural), log (base 10), raiz, abs, round, fatorial (5!), combinações <code>combinations(5; 2)</code>.</p>
            <button type="button" class="link-prim mt-1" @click="mtExemplo(b)">Trocar o texto pelos exemplos</button>
        </div>
    </template>

    <template x-if="mtUI(b).teclado">
        <div class="mt-teclado" role="toolbar" aria-label="Teclado de símbolos">
            <template x-for="t in mtTeclas()" :key="t[0]">
                <button type="button" class="mt-tecla" :title="t[2] + ' → ' + t[1].replace('|', '…')" @mousedown.prevent @click="mtTecla(b, $refs.mtTa, t[1])" x-text="t[0]"></button>
            </template>
        </div>
    </template>

    <div class="mt-ed" :class="b.dados.verGraf ? 'com-graf' : ''">
        <textarea class="mt-ta" wrap="off" spellcheck="false" autocomplete="off" x-model="b.dados.texto" maxlength="20000" aria-label="Contas, uma por linha" x-ref="mtTa"
                  placeholder="Escreva uma conta por linha…&#10;2 + 2&#10;15% de 80&#10;derivar x^2&#10;gráfico x^2"
                  @scroll="$el.nextElementSibling.scrollTop = $el.scrollTop"></textarea>
        <div class="mt-res" aria-live="polite">
            <template x-for="(r, i) in mtLinhas(b)" :key="i">
                <div class="mt-r" :class="r.cls" :title="r.tit || (r.txt && r.cls !== 'erro' ? 'Clique para copiar' : '')" @click="mtCopiar(b, r)" x-text="r.txt"></div>
            </template>
            <div class="mt-r"></div><div class="mt-r"></div>
        </div>
    </div>
    <p class="mt-aviso text-xs" x-show="mtPrimeiroErro(b)" x-text="mtPrimeiroErro(b)" aria-live="polite"></p>

    <template x-if="b.dados.verGraf">
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <label class="flex items-center gap-1 font-mono">y =
                <input class="campo !py-0.5 !text-xs font-mono" style="width: 11rem" x-model="mtUI(b).plot" maxlength="200" placeholder="x^2 - 4  (Enter)" aria-label="Função para desenhar"
                       @keydown.enter.prevent="mtPlotAdd(b)">
            </label>
            <button type="button" class="btn-sec !py-0.5" @click="mtPlotAdd(b)">Desenhar</button>
            <template x-for="f in mtFuncoes(b)" :key="f.nome">
                <span class="qz-chip font-mono flex items-center gap-1"><span class="es-bola" :style="'background:' + f.cor"></span><span x-text="f.nome"></span>
                    <button type="button" x-show="f.doCampo" class="texto-2" :aria-label="'Tirar ' + f.nome + ' do gráfico'" @click="mtPlotTirar(b, f.nome)">✕</button></span>
            </template>
            <span class="text-red-500" x-show="mtUI(b).plotErro" x-text="mtUI(b).plotErro"></span>
        </div>
    </template>

    <template x-if="b.dados.verGraf">
        <div class="mt-graf-caixa">
            <div class="mt-graf" x-init="mtMedir(b, $el)"
                 @pointerdown="mtBaixo(b, $event)" @pointermove="mtMover(b, $event)" @pointerup="mtSoltar(b)" @pointercancel="mtSoltar(b)"
                 @pointerleave="mtUI(b).cursor = null" @wheel.prevent="mtZoom(b, $event)" x-html="mtSvg(b)"></div>
            <div class="mt-graf-botoes">
                <button type="button" class="mini-btn" title="Aproximar" aria-label="Aproximar" @click="mtZoom(b, null, 1 / 1.4)">+</button>
                <button type="button" class="mini-btn" title="Afastar" aria-label="Afastar" @click="mtZoom(b, null, 1.4)">−</button>
                <button type="button" class="mini-btn" title="Centralizar" aria-label="Centralizar o gráfico" @click="mtCentralizar(b)">⌖</button>
            </div>
        </div>
    </template>
</div>
