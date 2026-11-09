{{-- Conteúdo do cartão "Flashcards" (Mente). Lógica em public/js/estudo.js (métodos fc*).
     Estudar: as cartas vencidas e as novas do dia, uma por vez; Espaço vira a carta, 1–4 responde (Errei, Difícil, Bom, Fácil).
     Editar: cadastrar, buscar, reiniciar ou apagar cartas. Importar: colar "frente ; verso" (ou exportação do Anki/Quizlet)
     ou trazer as questões de um cartão "Questões de concurso" da página. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm" tabindex="0" @keydown="fcTecla(b, $event)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="(b.dados.modo || 'estudar') === 'estudar' && 'qz-on'" @click="b.dados.modo = 'estudar'">Estudar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'editar' && 'qz-on'" @click="b.dados.modo = 'editar'">Editar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'importar' && 'qz-on'" @click="b.dados.modo = 'importar'">Importar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'progresso' && 'qz-on'" @click="b.dados.modo = 'progresso'">Progresso</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="b.dados.materia" aria-label="Matéria">
            <option value="">Todas as matérias</option>
            <template x-for="m in fcMaterias(b)" :key="m"><option :value="m" x-text="m" :selected="m === b.dados.materia"></option></template>
        </select>
        <span class="ml-auto texto-2" x-show="(b.dados.modo || 'estudar') === 'estudar'">
            <b class="fc-n-nova" x-text="fcContagem(b).novas"></b> novas ·
            <b class="fc-n-rev" x-text="fcContagem(b).revisar"></b> para revisar
        </span>
    </div>

    {{-- estudar --}}
    <template x-if="(b.dados.modo || 'estudar') === 'estudar'">
        <div class="flex-1 min-h-0 flex flex-col gap-2">
            <template x-if="!fcAtual(b)">
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 texto-2 px-4">
                    <p class="text-2xl" aria-hidden="true">🎉</p>
                    <p class="font-semibold" x-text="fcCartas(b).length ? 'Tudo revisado por hoje!' : 'Nenhuma carta ainda.'"></p>
                    <p class="text-xs" x-show="fcCartas(b).length" x-text="'Hoje: ' + fcContagem(b).feitasHoje + ' revisões' + (fcContagem(b).acertoHoje != null ? ' · ' + fcContagem(b).acertoHoje + '% de acerto' : '') + (fcContagem(b).amanha > 0 ? ' · amanhã: ' + fcContagem(b).amanha : '')"></p>
                    <div class="flex gap-1.5">
                        <button type="button" class="btn !py-0.5 text-xs" @click="b.dados.modo = 'editar'">Criar cartas</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" @click="b.dados.modo = 'importar'">Importar</button>
                    </div>
                </div>
            </template>
            <template x-if="fcAtual(b)">
                <div class="flex-1 min-h-0 flex flex-col gap-2">
                    <button type="button" class="fc-carta flex-1 min-h-0" :class="fcUI(b).virada && 'virada'" @click="fcUI(b).virada = true" :aria-label="fcUI(b).virada ? 'Resposta' : 'Mostrar a resposta'">
                        <span class="text-[11px] texto-2 flex gap-2">
                            <span x-text="fcAtual(b).materia || 'Sem matéria'"></span>
                            <span x-text="fcAtual(b).prox ? 'revisão' : 'nova'" class="fc-selo" :class="fcAtual(b).prox ? 'rev' : 'nova'"></span>
                        </span>
                        <span class="fc-frente whitespace-pre-wrap" x-text="fcAtual(b).frente"></span>
                        <template x-if="fcUI(b).virada">
                            <span class="fc-verso whitespace-pre-wrap" x-text="fcAtual(b).verso"></span>
                        </template>
                        <span class="text-[11px] texto-2 mt-auto" x-show="!fcUI(b).virada">Clique ou aperte Espaço para ver a resposta</span>
                    </button>
                    <div class="fc-notas" x-show="fcUI(b).virada">
                        <template x-for="(n, k) in ['Errei', 'Difícil', 'Bom', 'Fácil']" :key="k">
                            <button type="button" class="fc-nota" :class="'n' + k" @click="fcResponder(b, k)" :title="'Tecla ' + (k + 1)">
                                <b x-text="n"></b>
                                <span class="text-[10px] opacity-80" x-text="fcPrevisao(b, fcAtual(b), k)"></span>
                            </button>
                        </template>
                    </div>
                    <button type="button" class="btn !py-1" x-show="!fcUI(b).virada" @click="fcUI(b).virada = true">Mostrar resposta</button>
                </div>
            </template>
        </div>
    </template>

    {{-- editar --}}
    <template x-if="b.dados.modo === 'editar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <div class="qz-ed">
                <textarea class="campo !text-xs" rows="2" placeholder="Frente (pergunta)" x-model="fcUI(b).nova.frente" maxlength="2000"></textarea>
                <textarea class="campo !text-xs" rows="2" placeholder="Verso (resposta)" x-model="fcUI(b).nova.verso" maxlength="4000" @keydown.ctrl.enter="fcAdicionar(b)"></textarea>
                <div class="flex gap-1.5 items-center">
                    <input class="campo !py-0.5 !text-xs flex-1" placeholder="Matéria" x-model="fcUI(b).nova.materia" list="fc-materias" maxlength="60">
                    <button type="button" class="btn !py-0.5 text-xs" @click="fcAdicionar(b)">Adicionar carta</button>
                </div>
            </div>
            <datalist id="fc-materias"><template x-for="m in esMaterias()" :key="m"><option :value="m"></option></template></datalist>
            <div class="flex gap-1.5 items-center text-xs">
                <input class="campo !py-0.5 !text-xs flex-1" placeholder="Buscar nas cartas" x-model="fcUI(b).busca">
                <label class="texto-2 flex items-center gap-1" title="Quantas cartas novas entram por dia">Novas/dia
                    <input type="number" min="0" max="500" class="campo !py-0.5 !text-xs !w-16" x-model.number="b.dados.novasDia">
                </label>
            </div>
            <template x-for="c in fcEditaveis(b)" :key="c.id">
                <div class="qz-ed">
                    <textarea class="campo !text-xs" rows="2" x-model="c.frente" aria-label="Frente" maxlength="2000"></textarea>
                    <textarea class="campo !text-xs" rows="2" x-model="c.verso" aria-label="Verso" maxlength="4000"></textarea>
                    <div class="flex items-center gap-1.5 text-[11px] texto-2">
                        <input class="campo !py-0.5 !text-xs" style="width:8rem" x-model="c.materia" placeholder="Matéria" list="fc-materias" maxlength="60">
                        <span x-text="c.prox ? 'próxima: ' + c.prox.split('-').reverse().join('/') + ' · intervalo ' + (c.int || 0) + 'd' : 'nova'"></span>
                        <button type="button" class="link-prim ml-auto" x-show="c.prox" @click="fcReiniciar(b, c)">Reiniciar</button>
                        <button type="button" class="mini-btn perigo" title="Apagar carta" @click="fcRemover(b, c)">✕</button>
                    </div>
                </div>
            </template>
            <p class="text-xs texto-2" x-show="fcCartas(b).length > 200">Mostrando 200 cartas. Use a busca para achar as outras.</p>
        </div>
    </template>

    {{-- importar --}}
    <template x-if="b.dados.modo === 'importar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <p class="texto-2">Uma carta por linha: <code>frente ; verso</code> (ou <code>frente ; verso ; matéria</code>). Também aceita o texto exportado do Anki ou do Quizlet (separado por tabulação).</p>
            <textarea class="campo !text-xs font-mono" rows="6" x-model="fcUI(b).imp" placeholder="Capital da França ; Paris&#10;2 + 2 ; 4 ; Matemática"></textarea>
            <div class="flex gap-1.5">
                <button type="button" class="btn !py-0.5" @click="fcImportarTexto(b)">Importar cartas</button>
                <button type="button" class="btn-sec !py-0.5" @click="fcBaixarCsv(b)">Baixar cartas (.txt para o Anki)</button>
            </div>
            <div class="qz-ed">
                <b>Trazer do cartão de Questões</b>
                <template x-if="!fcFontes(b).length"><p class="texto-2">Nenhum cartão “Questões de concurso” com questões nesta página.</p></template>
                <template x-if="fcFontes(b).length">
                    <div class="flex flex-wrap gap-1.5 items-center">
                        <select class="campo !py-0.5 !text-xs !w-auto" x-model="fcUI(b).fonte" aria-label="Cartão de questões">
                            <option value="">Escolha…</option>
                            <template x-for="f in fcFontes(b)" :key="f.id"><option :value="f.id" x-text="f.nome" :selected="f.id === fcUI(b).fonte"></option></template>
                        </select>
                        <label class="flex items-center gap-1 texto-2"><input type="checkbox" x-model="fcUI(b).soErradas"> só as que errei</label>
                        <button type="button" class="btn-sec !py-0.5" @click="fcImportarQuestoes(b)">Virar flashcards</button>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- progresso --}}
    <template x-if="b.dados.modo === 'progresso'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <div class="es-kpis">
                <div><b x-text="fcContagem(b).total"></b><span>cartas</span></div>
                <div><b x-text="fcContagem(b).maduras"></b><span>maduras (21+ dias)</span></div>
                <div><b x-text="fcContagem(b).feitasHoje"></b><span>revisões hoje</span></div>
                <div><b x-text="fcContagem(b).acertoHoje != null ? fcContagem(b).acertoHoje + '%' : '—'"></b><span>acerto hoje</span></div>
            </div>
            <div class="es-graf" x-html="fcSvg(b)"></div>
        </div>
    </template>
</div>
