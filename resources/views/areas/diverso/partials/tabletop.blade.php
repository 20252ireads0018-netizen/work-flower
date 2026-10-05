{{-- Tabletop: mapa com grade, peças que se arrastam e desenho (pincel, borracha, formas).
     Usa o escopo do bloco `b` dentro do diversoAbas(). --}}
<div class="wf-tt">

    {{-- Peças prontas --}}
    <div class="wf-tt-linha">
        <template x-for="d in ttPecas" :key="d.id">
            <button type="button" class="wf-tt-chip" :style="'--pc:' + d.cor" @click="ttAdicionar(b, d)">
                <i></i><span x-text="'+ ' + d.nome"></span>
            </button>
        </template>
    </div>

    {{-- Minhas peças: modelos salvos (nome, formato, cor, tamanho, imagem e enquadramento), valem para todos os tabletops --}}
    <div class="wf-tt-linha" x-show="estado.pecasSalvas.length" x-cloak role="group" aria-label="Minhas peças">
        <span class="text-xs texto-2">Minhas peças</span>
        <template x-for="m in estado.pecasSalvas" :key="m.id">
            <span class="wf-tt-chip" style="padding-right: .3rem;">
                <button type="button" class="flex items-center gap-1.5"
                        :title="'Adicionar ' + m.nome + ' (' + ttFormaNome(m.forma) + ' ' + m.tam + '×' + m.tam + ')'"
                        @click="ttUsarModelo(b, m)">
                    <span :style="ttModeloThumb(m)">
                        <img x-show="ttModeloImg(m)" x-cloak :src="ttModeloImg(m) || null" :style="ttModeloImgEstilo(m)" alt="" draggable="false">
                    </span>
                    <span x-text="m.nome + ' · ' + m.tam + '×' + m.tam"></span>
                </button>
                <button type="button" class="mini-btn perigo" title="Excluir da lista" :aria-label="'Excluir ' + m.nome + ' de Minhas peças'"
                        @click="ttRemoverModelo(m)">✕</button>
            </span>
        </template>
    </div>

    {{-- Peça personalizada: nome + dropdown de atributos (cor, formato, tamanho, imagem) --}}
    <div class="wf-tt-linha">
        <input type="text" class="campo wf-tt-nome-in" maxlength="24" placeholder="Nova peça (ex.: Goblin, Baú)" aria-label="Nome da nova peça"
               x-model="ttUI(b).nome" @keydown.enter.prevent="ttAdicionar(b)">

        <div class="wf-tt-drop" @click.outside="ttUI(b).menuNovo = false" @keydown.escape.stop="ttUI(b).menuNovo = false">
            <button type="button" class="btn-sec !py-1.5 !px-3 text-xs" aria-haspopup="true" :aria-expanded="ttUI(b).menuNovo"
                    @click="ttUI(b).menuNovo = !ttUI(b).menuNovo">
                <span class="wf-tt-bolinha" :style="'background:' + ttUI(b).cor"></span>
                <span x-text="ttFormaNome(ttUI(b).forma) + ' ' + ttUI(b).tam + '×' + ttUI(b).tam"></span> ▾
            </button>
            <div class="wf-tt-pop" x-show="ttUI(b).menuNovo" x-cloak role="group" aria-label="Atributos da nova peça">
                <label class="wf-tt-attr"><span>Cor</span>
                    <input type="color" class="wf-cor" style="width: 1.6rem; height: 1.6rem;" aria-label="Cor da nova peça" x-model="ttUI(b).cor">
                </label>
                <label class="wf-tt-attr"><span>Formato</span>
                    <select class="campo" x-model="ttUI(b).forma">
                        <option value="circulo">Círculo</option>
                        <option value="quadrado">Quadrado</option>
                        <option value="losango">Losango</option>
                    </select>
                </label>
                <label class="wf-tt-attr"><span>Tamanho</span>
                    <select class="campo" x-model.number="ttUI(b).tam">
                        <option value="1">1×1</option><option value="2">2×2</option><option value="3">3×3</option><option value="4">4×4</option>
                    </select>
                </label>
                <div class="wf-tt-attr"><span>Imagem</span>
                    <div class="flex items-center gap-2">
                        <img class="wf-tt-mini" x-show="ttUI(b).imgNova" :src="ttUI(b).imgNova || null" alt="">
                        <label class="btn-sec cursor-pointer !py-1 !px-2 text-xs">
                            <span x-text="ttUI(b).imgNova ? 'Trocar' : 'Escolher'"></span>
                            <input type="file" accept="image/*" class="sr-only" @change="ttLerImgNova(b, $event.target.files[0]); $event.target.value = ''">
                        </label>
                        <button type="button" class="link-prim text-xs" x-show="ttUI(b).imgNova" x-cloak @click="ttUI(b).imgNova = ''; ttEnqReset(b, 'novo')">Remover</button>
                    </div>
                </div>

                <div class="wf-tt-enq-box" x-show="ttUI(b).imgNova" x-cloak>
                    <div class="wf-tt-enq" :class="'f-' + ttEnqForma(b, 'novo')"
                         @pointerdown="ttEnqArrastar($event, b, 'novo')"
                         @wheel.prevent="ttEnqZoom(b, 'novo', ttEnqLer(b, 'novo').z - Math.sign($event.deltaY) * 0.1)">
                        <img :src="ttUI(b).imgNova || null" :style="ttEnqEstiloPrev(b, 'novo')" alt="" draggable="false">
                    </div>
                    <label class="wf-tt-enq-zoom">Zoom
                        <input type="range" min="1" max="5" step="0.05" aria-label="Zoom da imagem"
                               :value="ttEnqLer(b, 'novo').z" @input="ttEnqZoom(b, 'novo', +$event.target.value)">
                        <button type="button" class="link-prim" @click="ttEnqReset(b, 'novo')">Redefinir</button>
                    </label>
                    <span class="text-xs texto-2">Arraste a imagem para posicionar.</span>
                </div>
            </div>
        </div>

        <button type="button" class="btn !py-1.5 !px-3 text-xs" @click="ttAdicionar(b)">Criar</button>
        <button type="button" class="btn-sec !py-1.5 !px-3 text-xs" title="Guarda nome, formato, cor, tamanho e imagem em Minhas peças, sem colocar no mapa"
                @click="ttSalvarModeloNovo(b)">Salvar modelo</button>
    </div>

    {{-- Ferramentas de desenho --}}
    <div class="wf-tt-linha" role="toolbar" aria-label="Ferramentas de desenho">
        <template x-for="f in ttFerramentas" :key="f.id">
            <button type="button" class="wf-tt-ferr"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === f.id }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === f.id"
                    @click="ttFerramenta(b, f.id)" x-text="f.nome"></button>
        </template>

        <span class="wf-tt-sep"></span>

        <button type="button" class="wf-tt-ferr" :disabled="!ttDes(b).hist.length" @click="ttDesDesfazer(b)" title="Desfazer desenho">↶ Desfazer</button>
        <button type="button" class="wf-tt-ferr" :disabled="!ttDes(b).refaz.length" @click="ttDesRefazer(b)" title="Refazer desenho">↷ Refazer</button>
        <button type="button" class="wf-tt-ferr" :disabled="!(b.dados.desenho || []).length" @click="ttDesLimpar(b)" title="Apagar todo o desenho">Limpar</button>

        <span class="wf-tt-sep"></span>

        <label class="flex items-center gap-1.5 text-xs texto-2">Cor
            <input type="color" class="wf-cor" style="width: 1.4rem; height: 1.4rem;" aria-label="Cor do desenho" x-model="ttDes(b).cor">
        </label>
        <label class="flex items-center gap-1.5 text-xs texto-2">Espessura
            <input type="range" min="1" max="24" step="1" style="width: 6rem;" aria-label="Espessura do traço" x-model.number="ttDes(b).larg">
            <span x-text="ttDes(b).larg" style="min-width: 1.2rem;"></span>
        </label>
        <label class="flex items-center gap-1.5 text-xs texto-2" x-show="!['pincel','borracha'].includes(ttDes(b).ferr)" x-cloak>
            <input type="checkbox" x-model="ttDes(b).preencher"> Preencher formas
        </label>
    </div>

    {{-- Peça(s) selecionada(s): os atributos valem para todas --}}
    <template x-if="ttSelecionadas(b).length">
        <div class="wf-tt-linha" role="group" aria-label="Peças selecionadas">
            <span class="text-xs texto-2" x-text="ttSelecionadas(b).length > 1 ? ttSelecionadas(b).length + ' selecionadas' : 'Selecionada'"></span>

            <template x-if="ttSelecionadas(b).length === 1">
                <input type="text" class="campo wf-tt-nome-in" maxlength="24" aria-label="Nome da peça"
                       :value="ttSel(b).nome" @input="ttSel(b).nome = $event.target.value">
            </template>

            <div class="wf-tt-drop" @click.outside="ttUI(b).menuSel = false" @keydown.escape.stop="ttUI(b).menuSel = false">
                <button type="button" class="btn-sec !py-1 !px-2 text-xs" aria-haspopup="true" :aria-expanded="ttUI(b).menuSel"
                        @click="ttUI(b).menuSel = !ttUI(b).menuSel">
                    <span class="wf-tt-bolinha" :style="'background:' + (ttValor(b, 'cor') || '#888888')"></span>
                    Atributos ▾
                </button>
                <div class="wf-tt-pop" x-show="ttUI(b).menuSel" x-cloak role="group" aria-label="Atributos da seleção">
                    <label class="wf-tt-attr"><span>Cor</span>
                        <input type="color" class="wf-cor" style="width: 1.6rem; height: 1.6rem;" aria-label="Cor"
                               :value="ttValor(b, 'cor') || '#888888'" @input="ttAplicar(b, 'cor', $event.target.value)">
                    </label>
                    <label class="wf-tt-attr"><span>Formato</span>
                        <select class="campo" :value="ttValor(b, 'forma')" @change="ttAplicar(b, 'forma', $event.target.value)">
                            <option value="" disabled>Misto</option>
                            <option value="circulo">Círculo</option>
                            <option value="quadrado">Quadrado</option>
                            <option value="losango">Losango</option>
                        </select>
                    </label>
                    <label class="wf-tt-attr"><span>Tamanho</span>
                        <select class="campo" :value="ttValor(b, 'tam')" @change="ttAplicar(b, 'tam', +$event.target.value)">
                            <option value="" disabled>Misto</option>
                            <option value="1">1×1</option><option value="2">2×2</option><option value="3">3×3</option><option value="4">4×4</option>
                        </select>
                    </label>
                    <div class="wf-tt-attr"><span>Imagem</span>
                        <div class="flex items-center gap-2">
                            <label class="btn-sec cursor-pointer !py-1 !px-2 text-xs">
                                <span>Escolher</span>
                                <input type="file" accept="image/*" class="sr-only" @change="ttLerImgSel(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="link-prim text-xs" x-show="ttSelecionadas(b).some(p => p.img)" x-cloak @click="ttTirarImgSel(b)">Remover</button>
                        </div>
                    </div>

                    <div class="wf-tt-enq-box" x-show="ttPrimeiraImg(b)" x-cloak>
                        <div class="wf-tt-enq" :class="'f-' + ttEnqForma(b, 'sel')"
                             @pointerdown="ttEnqArrastar($event, b, 'sel')"
                             @wheel.prevent="ttEnqZoom(b, 'sel', ttEnqLer(b, 'sel').z - Math.sign($event.deltaY) * 0.1)">
                            <img :src="ttEnqSrc(b, 'sel') || null" :style="ttEnqEstiloPrev(b, 'sel')" alt="" draggable="false">
                        </div>
                        <label class="wf-tt-enq-zoom">Zoom
                            <input type="range" min="1" max="5" step="0.05" aria-label="Zoom da imagem"
                                   :value="ttEnqLer(b, 'sel').z" @input="ttEnqZoom(b, 'sel', +$event.target.value)">
                            <button type="button" class="link-prim" @click="ttEnqReset(b, 'sel')">Redefinir</button>
                        </label>
                        <span class="text-xs texto-2">Arraste a imagem para posicionar. Vale para todas as peças com imagem selecionadas.</span>
                    </div>
                </div>
            </div>

            <button type="button" class="btn-sec !py-1 !px-2 text-xs" @click="ttDuplicar(b)">Duplicar</button>
            <button type="button" class="btn-sec !py-1 !px-2 text-xs" title="Guarda as peças selecionadas (nome, formato, cor, tamanho e imagem) para usar em qualquer tabletop"
                    @click="ttSalvarSelecao(b)">Salvar em Minhas peças</button>
            <button type="button" class="mini-btn perigo" title="Excluir selecionadas" aria-label="Excluir peças selecionadas" @click="ttRemoverSel(b)">✕</button>
        </div>
    </template>

    <p class="text-xs texto-2" x-show="!ttDes(b).on">Arraste no mapa para selecionar várias peças · Shift/Ctrl + clique soma ou tira uma · setas movem · Delete exclui.</p>
    <p class="text-xs texto-2" x-show="ttDes(b).on" x-cloak>
        Desenhando com <strong x-text="(ttFerramentas.find(f => f.id === ttDes(b).ferr) || {}).nome"></strong>.
        Clique de novo na ferramenta para voltar a mover as peças.
    </p>

    {{-- O mapa --}}
    <div class="wf-tt-cena">
        <div class="wf-tt-grade" :class="{ 'sem-grade': b.dados.grade === false }" :style="ttEstilo(b)"
             @pointerdown="ttCaixaIniciar($event, b)">
            <div class="wf-tt-mapa" x-show="ttMapa(b)" x-cloak :style="ttMapa(b) ? 'background-image:url(&quot;' + ttMapa(b) + '&quot;)' : ''"></div>

            {{-- Camada de desenho (SVG com 40 unidades por casa: não desalinha ao mudar o tamanho da casa) --}}
            <svg class="wf-tt-des" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"
                 :class="{ ativo: ttDes(b).on, borracha: ttDes(b).on && ttDes(b).ferr === 'borracha' }"
                 x-effect="$el.setAttribute('viewBox', ttViewBox(b))"
                 @pointerdown="ttDesDown($event, b)" @pointermove="ttDesHover($event, b)" @pointerleave="ttDesFora(b)">
                <g x-html="ttDesenhoSvg(b)"></g>
                <g x-html="ttVivoSvg(b)"></g>
                <circle x-show="ttDes(b).on && ttDes(b).ferr === 'borracha' && ttDes(b).cx !== null" x-cloak
                        :cx="ttDes(b).cx" :cy="ttDes(b).cy" :r="ttDesRaio(b)"
                        fill="rgba(255,255,255,.12)" stroke="#fff" stroke-width="1.5" stroke-dasharray="4 3" pointer-events="none"/>
            </svg>

            <template x-for="p in b.dados.pecas" :key="p.id">
                <div class="wf-tt-peca" tabindex="0" role="button"
                     :class="['f-' + ttForma(p), { sel: ttSelecionada(b, p), arrasta: ttUI(b).arrasta && ttSelecionada(b, p) }]"
                     :style="ttPecaEstilo(b, p)" :title="p.nome" :aria-label="'Peça ' + p.nome + '. Use as setas para mover.'"
                     @pointerdown="ttDown($event, b, p)" @keydown="ttTecla($event, b, p)">
                    <span class="wf-tt-img" x-show="ttImg(b, p)" x-cloak>
                        <img :src="ttImg(b, p) || null" :style="ttEnqEstilo(p)" alt="" draggable="false">
                    </span>
                    <span class="wf-tt-ini" x-show="!ttImg(b, p)" x-text="ttInicial(p)"></span>
                    <span class="wf-tt-rotulo" x-show="b.dados.nomes !== false" x-text="p.nome"></span>
                </div>
            </template>

            <div class="wf-tt-caixa" x-show="ttUI(b).caixa" x-cloak :style="ttCaixaEstilo(b)"></div>
        </div>
    </div>

    {{-- Ajustes do mapa --}}
    <details class="text-xs texto-2">
        <summary class="cursor-pointer select-none">Ajustes do mapa</summary>
        <div class="wf-tt-linha mt-2">
            <label class="flex items-center gap-1.5">Colunas
                <input type="number" min="4" max="60" class="campo" style="width: 4.5rem;" :value="ttCols(b)"
                       @change="b.dados.cols = $event.target.value; ttLimitar(b)">
            </label>
            <label class="flex items-center gap-1.5">Linhas
                <input type="number" min="4" max="60" class="campo" style="width: 4.5rem;" :value="ttRows(b)"
                       @change="b.dados.rows = $event.target.value; ttLimitar(b)">
            </label>
            <label class="flex items-center gap-1.5">Casa
                <select class="campo" @change="b.dados.cel = +$event.target.value; ttLimitar(b)" aria-label="Tamanho da casa">
                    <template x-for="n in [28, 36, 40, 48, 56, 64]" :key="n">
                        <option :value="n" :selected="ttCel(b) === n" x-text="n + ' px'"></option>
                    </template>
                </select>
            </label>
            <label class="flex items-center gap-1.5">
                <input type="checkbox" :checked="b.dados.grade !== false" @change="b.dados.grade = $event.target.checked"> Grade
            </label>
            <label class="flex items-center gap-1.5">
                <input type="checkbox" :checked="b.dados.nomes !== false" @change="b.dados.nomes = $event.target.checked"> Nomes
            </label>
            <label class="btn-sec cursor-pointer !py-1 !px-2 text-xs">
                <span x-text="ttMapa(b) ? 'Trocar mapa' : 'Mapa de fundo'"></span>
                <input type="file" accept="image/*" class="sr-only" @change="ttLerMapa(b, $event.target.files[0]); $event.target.value = ''">
            </label>
            <button type="button" class="link-prim" x-show="ttMapa(b)" x-cloak @click="ttTirarMapa(b)">Remover mapa</button>
        </div>
    </details>
</div>