{{-- Conteúdo do cartão "Matemática e gráficos" (Mente). Lógica em public/js/estudo.js (métodos mt*; math.js e nerdamer vêm de CDN).
     Uma conta por linha com a resposta ao lado (clique na resposta para copiar). Variáveis, funções, unidades, porcentagem,
     "total"/"média" do bloco, derivar, integrar, simplificar, resolver, fatorar, expandir, limite e "gráfico f(x)".
     O gráfico arrasta com o mouse e aproxima com a rodinha. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm" x-init="mtGarantir(b)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <label class="flex items-center gap-1" title="2,5 é dois e meio; separe argumentos com ponto e vírgula: max(1; 2)"><input type="checkbox" x-model="b.dados.virgula"> Vírgula decimal</label>
        <label class="flex items-center gap-1" title="sen, cos e tg em graus"><input type="checkbox" x-model="b.dados.graus"> Graus</label>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.verGraf && 'qz-on'" @click="b.dados.verGraf = !b.dados.verGraf">Gráfico</button>
        <button type="button" class="btn-sec !py-0.5" :class="mtUI(b).ajuda && 'qz-on'" @click="mtUI(b).ajuda = !mtUI(b).ajuda">Como usar</button>
        <span class="texto-2" x-show="mtStatus()" x-text="mtStatus()"></span>
    </div>

    <template x-if="mtUI(b).ajuda">
        <div class="mt-ajuda text-xs">
            <p class="texto-2">Clique num exemplo para pôr no fim do texto:</p>
            <div class="flex flex-wrap gap-1">
                <template x-for="e in ['12,5 * 3', '15% de 200', '1500 - 10%', 'raiz(144)', '2^10', '5 km para m', 'x = 4', 'f(x) = x^2 - 3x', 'f(5)', 'total', 'derivar x^3 * sen(x)', 'd2/dx2 x^4', 'derivar f(x) em x = 2', 'integrar x^2 * e^x', 'integrar sen(x) de 0 a pi', 'simplificar (x^2 - 1)/(x - 1)', 'resolver x^2 - 5x + 6 = 0', 'fatorar x^3 - 8', 'expandir (x + 2)^3', 'limite (1 + 1/n)^n quando n -> infinito', 'gráfico sen(x); cos(x)']" :key="e">
                    <button type="button" class="qz-chip font-mono" @click="mtInserir(b, e)" x-text="e"></button>
                </template>
            </div>
            <p class="texto-2 mt-1">Use <code>//</code> para comentários e <code>Rótulo: conta</code> para dar nome à linha. Linha em branco separa os blocos de <code>total</code> e <code>média</code>; <code>ans</code> é a última resposta. Funções: sen, cos, tg, ln (natural), log (base 10), raiz, abs, round, fatorial (5!), combinações <code>combinations(5; 2)</code>.</p>
            <button type="button" class="link-prim mt-1" @click="mtExemplo(b)">Trocar o texto pelos exemplos</button>
        </div>
    </template>

    <div class="mt-ed" :class="b.dados.verGraf ? 'com-graf' : ''">
        <textarea class="mt-ta" wrap="off" spellcheck="false" autocomplete="off" x-model="b.dados.texto" maxlength="20000" aria-label="Contas, uma por linha"
                  placeholder="Escreva uma conta por linha…&#10;2 + 2&#10;derivar x^2&#10;gráfico x^2"
                  @scroll="$el.nextElementSibling.scrollTop = $el.scrollTop"></textarea>
        <div class="mt-res" aria-live="polite">
            <template x-for="(r, i) in mtLinhas(b)" :key="i">
                <div class="mt-r" :class="r.cls" :title="r.tit || (r.txt && r.cls !== 'erro' ? 'Clique para copiar' : '')" @click="mtCopiar(b, r)" x-text="r.txt"></div>
            </template>
            <div class="mt-r"></div><div class="mt-r"></div>
        </div>
    </div>

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
