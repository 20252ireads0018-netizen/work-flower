{{-- Conteúdo do cartão "Cenas" (escritores, todas as áreas). Lógica em public/js/cenas.js (métodos cn*), estilo em partials/cenas.
     Quadro: ideias soltas + uma coluna por ato/capítulo com as cenas (arraste pela alça ⠿ ou use ↑ ↓).
     Linha do tempo: todas as cenas em ordem. Abrir uma cena: requisitos (personagens, local, objetivo, conflito, gancho),
     checklist próprio, status, ideias presas e "Escrever" (cartão de Texto ligado à cena). --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm cn" x-init="cnUI(b)">
    <span hidden x-text="cnContar(b)"></span>
    <div class="flex flex-wrap items-center gap-1.5 text-xs" x-show="!cnAberta(b)">
        <button type="button" class="btn-sec !py-0.5" :class="cnUI(b).ver === 'quadro' && 'qz-on'" @click="cnUI(b).ver = 'quadro'">Quadro</button>
        <button type="button" class="btn-sec !py-0.5" :class="cnUI(b).ver === 'linha' && 'qz-on'" @click="cnUI(b).ver = 'linha'">Linha do tempo</button>
        <button type="button" class="btn !py-0.5" @click="cnNovaCena(b)">+ Cena</button>
        <button type="button" class="btn-sec !py-0.5" @click="cnNovoAto(b)" title="Novo ato ou capítulo">+ Ato/capítulo</button>
        <input class="campo !py-0.5 !text-xs cn-busca" x-model="cnUI(b).filtro" placeholder="Filtrar cenas" aria-label="Filtrar cenas por título, personagem ou local">
        <button type="button" class="btn-sec !py-0.5" @click="cnCopiarEscaleta(b)" title="Copia todas as cenas e requisitos como texto">Copiar escaleta</button>
        <span class="ml-auto texto-2" x-text="cnResumo(b)"></span>
    </div>

    {{-- ===== quadro: ideias soltas + atos ===== --}}
    <template x-if="cnUI(b).ver === 'quadro' && !cnAberta(b)">
        <div class="flex-1 min-h-0 overflow-auto flex gap-2 cn-quadro">
            <div class="cn-col cn-col-ideias" data-cn-alvo="soltas" :data-cn-b="b.id">
                <p class="cx-col-tit flex items-center">💡&nbsp;Ideias soltas&nbsp;<span class="texto-2" x-text="cnSoltas(b).length"></span></p>
                <form class="flex gap-1" @submit.prevent="cnNovaIdeia(b)">
                    <input class="campo !py-0.5 !text-xs flex-1 min-w-0" x-model="cnUI(b).ideia" maxlength="500" placeholder="Anote uma ideia" aria-label="Nova ideia">
                    <button class="mini-btn" title="Guardar ideia" aria-label="Guardar ideia">+</button>
                </form>
                <template x-for="i in cnSoltas(b)" :key="i.id">
                    <div class="cn-ideia">
                        <span class="cn-pega" title="Arraste para uma cena" aria-hidden="true" @pointerdown="cnPegar($event, b, 'ideia', i)">⠿</span>
                        <span class="flex-1 min-w-0 cn-quebra" x-text="i.t"></span>
                        <select class="cn-levar" aria-label="Levar ideia para uma cena" title="Levar para uma cena" @change="i.cena = $event.target.value || null; $event.target.value = ''">
                            <option value="">→</option>
                            <template x-for="c in cnOrdem(b)" :key="c.id"><option :value="c.id" x-text="cnNumero(b, c) + '. ' + (c.titulo || 'Sem título')"></option></template>
                        </select>
                        <button type="button" class="cn-x" title="Virar cena" aria-label="Transformar a ideia em cena" @click="cnIdeiaVirarCena(b, i)">🎬</button>
                        <button type="button" class="cn-x" title="Apagar ideia" aria-label="Apagar ideia" @click="cnApagarIdeia(b, i)">×</button>
                    </div>
                </template>
                <p class="text-[11px] texto-2 px-1" x-show="!cnSoltas(b).length">Ideias que ainda não têm lugar. Arraste pela ⠿ para dentro de uma cena.</p>
            </div>

            <template x-for="a in b.dados.atos" :key="a.id">
                <div class="cn-col" data-cn-alvo="ato" :data-cn-id="a.id" :data-cn-b="b.id">
                    <div class="flex items-center gap-1">
                        <input class="cn-ato-nome" x-model="a.nome" maxlength="80" aria-label="Nome do ato ou capítulo">
                        <span class="texto-2 text-[11px]" x-text="cnDoAto(b, a.id).length"></span>
                    </div>
                    <div class="flex gap-1 text-[11px]">
                        <button type="button" class="cn-x" title="Mover o ato para a esquerda" aria-label="Mover o ato para a esquerda" @click="cnMoverAto(b, a, -1)">‹</button>
                        <button type="button" class="cn-x" title="Mover o ato para a direita" aria-label="Mover o ato para a direita" @click="cnMoverAto(b, a, 1)">›</button>
                        <button type="button" class="cn-x" title="Nova cena neste ato" aria-label="Nova cena neste ato" @click="cnNovaCena(b, a.id)">+ cena</button>
                        <button type="button" class="cn-x ml-auto" title="Apagar ato" aria-label="Apagar ato" @click="cnRemoverAto(b, a)">×</button>
                    </div>
                    <template x-for="c in cnDoAto(b, a.id)" :key="c.id">
                        <div class="cn-cena" data-cn-alvo="cena" :data-cn-id="c.id" :data-cn-b="b.id" :style="'--cn-cor:' + cnSt(c.status).cor"
                             role="button" tabindex="0" :title="'Abrir a cena ' + (c.titulo || 'sem título')"
                             @click="cnUI(b).aberta = c.id" @keydown.enter.self="cnUI(b).aberta = c.id">
                            <div class="flex items-start gap-1">
                                <span class="cn-pega" title="Arraste para outro lugar" aria-hidden="true" @pointerdown="cnPegar($event, b, 'cena', c)" @click.stop>⠿</span>
                                <p class="font-medium leading-tight flex-1 min-w-0 cn-quebra"><span class="texto-2" x-text="cnNumero(b, c) + '.'"></span> <span x-text="c.titulo || 'Sem título'"></span></p>
                            </div>
                            <div class="flex items-center gap-1 text-[11px] mt-1">
                                <span class="cn-chip" x-text="cnSt(c.status).nome"></span>
                                <span class="texto-2" x-show="c.quando" x-text="c.quando"></span>
                                <span class="texto-2 ml-auto" x-show="c.palavras" x-text="c.palavras + ' pal.'"></span>
                            </div>
                            <div class="cn-req" :title="cnFalta(c).length ? 'Falta: ' + cnFalta(c).join(', ') : 'Requisitos completos'">
                                <span :style="'width:' + Math.round(cnFeitos(c) / cnTotal(c) * 100) + '%'"></span>
                            </div>
                            <p class="text-[11px] texto-2" x-text="'Requisitos ' + cnFeitos(c) + '/' + cnTotal(c)"></p>
                            <div class="flex flex-wrap gap-1 mt-1" x-show="cnIdeiasDa(b, c).length" @click.stop>
                                <template x-for="i in cnIdeiasDa(b, c)" :key="i.id">
                                    <span class="cn-ideia cn-ideia-mini">
                                        <span class="cn-pega" title="Arraste para outra cena ou para as ideias soltas" aria-hidden="true" @pointerdown="cnPegar($event, b, 'ideia', i)">⠿</span>
                                        <span class="cn-quebra" x-text="i.t"></span>
                                    </span>
                                </template>
                            </div>
                            <div class="flex gap-1 mt-1" @click.stop>
                                <button type="button" class="cn-x" title="Subir (antes)" aria-label="Mover a cena para antes" @click="cnMover(b, c, -1)">↑</button>
                                <button type="button" class="cn-x" title="Descer (depois)" aria-label="Mover a cena para depois" @click="cnMover(b, c, 1)">↓</button>
                                <button type="button" class="cn-x ml-auto" :title="cnTextoDe(c) ? 'Abrir o texto da cena' : 'Escrever a cena num cartão de texto'" @click="cnEscrever(b, c)" x-text="cnTextoDe(c) ? '✍ Texto' : '✍ Escrever'"></button>
                            </div>
                        </div>
                    </template>
                    <p class="text-[11px] texto-2 px-1" x-show="!cnDoAto(b, a.id).length">Solte cenas aqui.</p>
                </div>
            </template>
        </div>
    </template>

    {{-- ===== linha do tempo ===== --}}
    <template x-if="cnUI(b).ver === 'linha' && !cnAberta(b)">
        <div class="flex-1 min-h-0 overflow-auto">
            <div class="cn-linha">
                <template x-for="a in b.dados.atos" :key="a.id">
                    <div class="cn-linha-ato">
                        <p class="cn-linha-tit" x-text="a.nome"></p>
                        <div class="cn-linha-cenas">
                            <template x-for="c in cnDoAto(b, a.id)" :key="c.id">
                                <button type="button" class="cn-ponto" :style="'--cn-cor:' + cnSt(c.status).cor" @click="cnUI(b).aberta = c.id"
                                        :title="(c.titulo || 'Sem título') + ' · ' + cnSt(c.status).nome + (cnFalta(c).length ? ' · falta: ' + cnFalta(c).join(', ') : '')">
                                    <span class="cn-ponto-bola" x-text="cnNumero(b, c)"></span>
                                    <span class="cn-ponto-tit cn-quebra" x-text="c.titulo || 'Sem título'"></span>
                                    <span class="cn-ponto-sub" x-text="c.quando || c.local || ''"></span>
                                    <span class="cn-ponto-sub" x-show="cnFalta(c).length" x-text="'⚠ ' + cnFalta(c).length + ' requisito(s)'"></span>
                                </button>
                            </template>
                            <p class="text-[11px] texto-2" x-show="!cnDoAto(b, a.id).length">Sem cenas.</p>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex flex-wrap gap-2 text-[11px] texto-2 mt-2">
                <template x-for="s in cnStatus()" :key="s.id"><span class="flex items-center gap-1"><i class="cn-bolinha" :style="'background:' + s.cor"></i><span x-text="s.nome"></span></span></template>
            </div>
        </div>
    </template>

    {{-- ===== editar cena ===== --}}
    <template x-for="c in (cnAberta(b) ? [cnAberta(b)] : [])" :key="c.id">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2.5 cr-editor">
            <div class="flex flex-wrap gap-1.5 items-center">
                <button type="button" class="btn-sec !py-0.5 text-xs" @click="cnUI(b).aberta = null">‹ Voltar</button>
                <h3 class="font-semibold text-sm" x-text="'Cena ' + cnNumero(b, c)"></h3>
                <button type="button" class="btn !py-0.5 text-xs ml-auto" @click="cnEscrever(b, c)" x-text="cnTextoDe(c) ? '✍ Abrir o texto' : '✍ Escrever a cena'"></button>
                <button type="button" class="btn-sec !py-0.5 text-xs" @click="cnDuplicar(b, c)">Duplicar</button>
                <button type="button" class="btn-sec !py-0.5 text-xs perigo" @click="cnRemover(b, c)">Excluir</button>
            </div>
            <label class="cr-campo"><span class="cr-rot">Título</span>
                <input class="campo !py-1 font-medium" x-model="c.titulo" maxlength="120" placeholder="Ex.: Ana descobre a carta" x-init="!c.titulo && $nextTick(() => $el.focus())"></label>
            <div class="cn-grade">
                <label class="cr-campo"><span class="cr-rot">Status</span>
                    <select class="campo !py-0.5 !text-xs" x-model="c.status"><template x-for="s in cnStatus()" :key="s.id"><option :value="s.id" x-text="s.nome" :selected="s.id === c.status"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Ato / capítulo</span>
                    <select class="campo !py-0.5 !text-xs" :value="c.ato" @change="cnColocar(b, c, $event.target.value, null)"><template x-for="a in b.dados.atos" :key="a.id"><option :value="a.id" x-text="a.nome" :selected="a.id === c.ato"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Quando (na história)</span>
                    <input class="campo !py-0.5 !text-xs" x-model="c.quando" maxlength="60" placeholder="Ex.: Dia 3, noite"></label>
                <label class="cr-campo"><span class="cr-rot">Ponto de vista</span>
                    <input class="campo !py-0.5 !text-xs" x-model="c.pov" maxlength="80" placeholder="Quem narra"></label>
            </div>

            <div class="qz-ed">
                <p class="flex items-center gap-2 text-xs font-semibold">Requisitos da cena
                    <span class="texto-2 font-normal" x-text="cnFeitos(c) + '/' + cnTotal(c) + ' prontos'"></span></p>
                <template x-for="r in cnReqs()" :key="r.id">
                    <label class="cr-campo">
                        <span class="cr-rot flex items-center gap-1"><span class="cn-ok" :class="String(c[r.id] || '').trim() && 'on'" aria-hidden="true">✓</span><span x-text="r.nome"></span></span>
                        <textarea class="campo !py-1 !text-xs cn-area" rows="2" x-model="c[r.id]" maxlength="1000" :placeholder="r.dica"></textarea>
                    </label>
                </template>
                <p class="cr-rot mt-1">Checklist próprio</p>
                <template x-for="x in c.extras" :key="x.id">
                    <div class="flex items-center gap-1.5 text-xs">
                        <input type="checkbox" x-model="x.ok" :aria-label="x.t">
                        <input class="campo !py-0.5 !text-xs flex-1 min-w-0" x-model="x.t" maxlength="120" aria-label="Item do checklist" :class="x.ok && 'line-through texto-2'">
                        <button type="button" class="cn-x" aria-label="Apagar item" title="Apagar item" @click="c.extras = c.extras.filter(y => y !== x)">×</button>
                    </div>
                </template>
                <form class="flex gap-1" @submit.prevent="cnNovoExtra(b, c, $el.querySelector('input'))">
                    <input class="campo !py-0.5 !text-xs flex-1 min-w-0" maxlength="120" placeholder="Ex.: plantar a pista da chave" aria-label="Novo item do checklist">
                    <button class="btn-sec !py-0.5 text-xs">Adicionar</button>
                </form>
            </div>

            <label class="cr-campo"><span class="cr-rot">Resumo / o que acontece</span>
                <textarea class="campo !py-1 !text-xs cn-area" rows="4" x-model="c.resumo" maxlength="5000" placeholder="Os acontecimentos da cena, em poucas linhas"></textarea></label>

            <div class="qz-ed" data-cn-alvo="cena" :data-cn-id="c.id" :data-cn-b="b.id">
                <p class="text-xs font-semibold">Ideias desta cena</p>
                <template x-for="i in cnIdeiasDa(b, c)" :key="i.id">
                    <div class="cn-ideia"><span class="flex-1 min-w-0 cn-quebra" x-text="i.t"></span>
                        <button type="button" class="cn-x" title="Devolver às ideias soltas" aria-label="Devolver às ideias soltas" @click="i.cena = null">↩</button></div>
                </template>
                <p class="text-[11px] texto-2" x-show="!cnIdeiasDa(b, c).length">Nenhuma. Use → nas ideias soltas ou arraste pela ⠿ no quadro.</p>
                <select class="campo !py-0.5 !text-xs" x-show="cnSoltas(b).length" aria-label="Prender uma ideia solta nesta cena" @change="const i = b.dados.ideias.find(x => x.id === $event.target.value); if (i) i.cena = c.id; $event.target.value = ''">
                    <option value="">+ Prender uma ideia solta…</option>
                    <template x-for="i in cnSoltas(b)" :key="i.id"><option :value="i.id" x-text="i.t.slice(0, 80)"></option></template>
                </select>
            </div>
            <p class="text-[11px] texto-2" x-show="cnTextoDe(c)" x-text="'Texto ligado: ' + (cnTextoDe(c) || {}).titulo + ' · ' + (c.palavras || 0) + ' palavras'"></p>
        </div>
    </template>
</div>
