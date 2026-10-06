{{-- Tabletop: mapa com grade, peças que se arrastam e desenho (pincel, borracha, formas).
     Usa o escopo do bloco `b` dentro do diversoAbas(). --}}

<div class="wf-tt">
    {{-- Estilo da barra de desenho. Fica DENTRO da raiz porque o x-if do Alpine só renderiza um elemento raiz. --}}
    <style>
        .wf-tt-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; }
        .wf-tt-grupo { display: inline-flex; align-items: center; gap: .125rem; padding: .1875rem; border-radius: .625rem;
                       background: color-mix(in srgb, currentColor 6%, transparent); }
        .wf-tt-ferr { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; padding: 0;
                      border: 0; border-radius: .5rem; background: transparent; color: inherit; opacity: .72; cursor: pointer;
                      transition: background .15s, opacity .15s, color .15s, transform .1s; }
        .wf-tt-ferr svg { width: 1.125rem; height: 1.125rem; fill: none; stroke: currentColor; stroke-width: 1.75;
                          stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
        .wf-tt-ferr:hover:not(:disabled) { opacity: 1; background: color-mix(in srgb, currentColor 12%, transparent); }
        .wf-tt-ferr:active:not(:disabled) { transform: scale(.92); }
        .wf-tt-ferr:focus-visible { outline: 2px solid var(--prim, #a78bfa); outline-offset: 1px; }
        .wf-tt-ferr.sel { opacity: 1; background: var(--prim, #a78bfa); color: var(--on-prim, #fff); }
        .wf-tt-ferr:disabled { opacity: .28; cursor: not-allowed; }
        .wf-tt-ferr.perigo:hover:not(:disabled) { color: #f87171; background: rgba(248, 113, 113, .14); }
        .wf-tt-sep { width: 1px; height: 1.25rem; background: currentColor; opacity: .16; }
        .wf-tt-opcao { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; opacity: .85; }
        .wf-tt-opcao input[type="range"] { accent-color: var(--prim, #a78bfa); }
        .wf-tt-opcao input[type="checkbox"] { accent-color: var(--prim, #a78bfa); }
        .wf-tt-opcao .wf-tt-valor { min-width: 1.25rem; text-align: right; font-variant-numeric: tabular-nums; }

        /* Ordem das camadas do mapa (de baixo para cima): fundo → desenho → peças → caixa de seleção.
           A grade vira um contexto de empilhamento próprio e as camadas recebem z-index explícito,
           então a imagem de fundo nunca fica por cima do desenho, com ou sem mapa. */
        .wf-tt-grade { isolation: isolate; }
        .wf-tt-grade > .wf-tt-des { position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important; z-index: 20 !important; }
        .wf-tt-grade > .wf-tt-des.ativo { pointer-events: auto !important; touch-action: none; }
        .wf-tt-grade > .wf-tt-peca { z-index: 21 !important; }
        .wf-tt-grade > .wf-tt-peca.sel { z-index: 22 !important; }
        .wf-tt-grade > .wf-tt-caixa { z-index: 30 !important; }
        /* Com uma ferramenta de desenho ligada, as peças não interceptam o mouse: o traço passa por cima delas */
        .wf-tt-grade.desenhando > .wf-tt-peca { pointer-events: none !important; }
    </style>

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

    {{-- Ferramentas de desenho (só ícones; o nome aparece ao passar o mouse e para leitores de tela) --}}
    <div class="wf-tt-barra" role="toolbar" aria-label="Ferramentas de desenho">

        {{-- Desenhar --}}
        <div class="wf-tt-grupo" role="group" aria-label="Pincel e borracha">
            <button type="button" class="wf-tt-ferr" title="Pincel" aria-label="Pincel"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'pincel' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'pincel'"
                    @click="ttFerramenta(b, 'pincel')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1-4L16.5 4.5a2.1 2.1 0 0 1 3 3L8 19z"/><path d="M14 7l3 3"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr" title="Borracha" aria-label="Borracha"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'borracha' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'borracha'"
                    @click="ttFerramenta(b, 'borracha')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 20H9l-4.6-4.6a2 2 0 0 1 0-2.8l8-8a2 2 0 0 1 2.8 0l5.4 5.4a2 2 0 0 1 0 2.8L12 20"/><path d="M8 8l8 8"/></svg>
            </button>
        </div>

        {{-- Formas --}}
        <div class="wf-tt-grupo" role="group" aria-label="Formas">
            <button type="button" class="wf-tt-ferr" title="Círculo" aria-label="Círculo"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'circulo' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'circulo'"
                    @click="ttFerramenta(b, 'circulo')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr" title="Quadrado" aria-label="Quadrado"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'quadrado' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'quadrado'"
                    @click="ttFerramenta(b, 'quadrado')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr" title="Cone" aria-label="Cone"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'cone' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'cone'"
                    @click="ttFerramenta(b, 'cone')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12L20 4v16z"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr" title="Triângulo" aria-label="Triângulo"
                    :class="{ sel: ttDes(b).on && ttDes(b).ferr === 'triangulo' }"
                    :aria-pressed="ttDes(b).on && ttDes(b).ferr === 'triangulo'"
                    @click="ttFerramenta(b, 'triangulo')">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4l9 16H3z"/></svg>
            </button>
        </div>

        {{-- Histórico --}}
        <div class="wf-tt-grupo" role="group" aria-label="Histórico do desenho">
            <button type="button" class="wf-tt-ferr" title="Desfazer desenho" aria-label="Desfazer desenho"
                    :disabled="!ttDes(b).hist.length" @click="ttDesDesfazer(b)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr" title="Refazer desenho" aria-label="Refazer desenho"
                    :disabled="!ttDes(b).refaz.length" @click="ttDesRefazer(b)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 14l5-5-5-5"/><path d="M20 9H10a6 6 0 0 0 0 12h3"/></svg>
            </button>
            <button type="button" class="wf-tt-ferr perigo" title="Apagar todo o desenho" aria-label="Apagar todo o desenho"
                    :disabled="!(b.dados.desenho || []).length" @click="ttDesLimpar(b)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v6M14 11v6"/></svg>
            </button>
        </div>

        <span class="wf-tt-sep" aria-hidden="true"></span>

        {{-- Cor, espessura e preenchimento --}}
        <label class="wf-tt-opcao" title="Cor do desenho">
            <input type="color" class="wf-cor" style="width: 1.4rem; height: 1.4rem;" aria-label="Cor do desenho" x-model="ttDes(b).cor">
        </label>
        <label class="wf-tt-opcao" title="Espessura do traço">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16" stroke-width="1"/><path d="M4 12h16" stroke-width="2.5"/><path d="M4 18h16" stroke-width="4.5"/></svg>
            <input type="range" min="1" max="24" step="1" style="width: 6rem;" aria-label="Espessura do traço" x-model.number="ttDes(b).larg">
            <span class="wf-tt-valor" x-text="ttDes(b).larg"></span>
        </label>
        <label class="wf-tt-opcao" x-show="!['pincel','borracha'].includes(ttDes(b).ferr)" x-cloak title="Preencher formas">
            <input type="checkbox" x-model="ttDes(b).preencher"> Preencher
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
        <div class="wf-tt-grade" :class="{ 'sem-grade': b.dados.grade === false, desenhando: ttDes(b).on }" :style="ttEstilo(b)"
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