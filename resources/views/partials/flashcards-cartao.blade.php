{{-- Conteúdo do cartão "Flashcards" (Mente). Lógica em public/js/estudo.js (métodos fc*).
     Baralhos: um resumo por baralho (novas, para revisar, maduras) com Estudar, Estudo livre, renomear e apagar.
     Estudar: as cartas vencidas e as novas do dia, uma por vez (repetição espaçada estilo SM-2); Espaço vira a carta,
     1–4 responde (Errei, Difícil, Bom, Fácil), Ctrl+Z desfaz. Estudo livre: todas / só as difíceis / só as novas, sem mexer na agenda.
     Editar: cadastrar (com imagem colada ou ![](endereço) e fórmulas $...$), buscar, reiniciar ou apagar cartas.
     Importar/exportar: texto, CSV, .txt do Anki; questões (ou só as erradas) de um cartão "Questões de concurso".
     Estatísticas: acerto, revisões dos últimos 30 dias, previsão dos próximos 14, intervalos e cartas difíceis. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm" tabindex="0" @keydown="fcTecla(b, $event)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'baralhos' && 'qz-on'" @click="b.dados.modo = 'baralhos'">Baralhos</button>
        <button type="button" class="btn-sec !py-0.5" :class="(b.dados.modo || 'estudar') === 'estudar' && 'qz-on'" @click="b.dados.modo = 'estudar'">Estudar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'editar' && 'qz-on'" @click="b.dados.modo = 'editar'">Editar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'importar' && 'qz-on'" @click="b.dados.modo = 'importar'">Importar/exportar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'progresso' && 'qz-on'" @click="b.dados.modo = 'progresso'">Estatísticas</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="b.dados.materia" @change="fcSairLivre(b)" aria-label="Baralho">
            <option value="">Todos os baralhos</option>
            <template x-for="m in fcMaterias(b)" :key="m"><option :value="m" x-text="m" :selected="m === b.dados.materia"></option></template>
        </select>
        <span class="ml-auto texto-2" x-show="(b.dados.modo || 'estudar') === 'estudar' && !fcUI(b).livre">
            <b class="fc-n-nova" x-text="fcContagem(b).novas"></b> novas ·
            <b class="fc-n-rev" x-text="fcContagem(b).revisar"></b> para revisar
        </span>
    </div>

    {{-- baralhos --}}
    <template x-if="b.dados.modo === 'baralhos'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-1.5 text-xs">
            <template x-if="!fcBaralhos(b).length"><p class="texto-2 text-center py-4">Nenhuma carta ainda. Crie um baralho abaixo ou importe do Anki/CSV.</p></template>
            <template x-for="x in fcBaralhos(b)" :key="x.nome">
                <div class="fc-baralho">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <template x-if="fcUI(b).renomear && fcUI(b).renomear.de === x.nome">
                            <input class="campo !py-0.5 !text-xs flex-1" x-model="fcUI(b).renomear.nome" maxlength="60" aria-label="Novo nome do baralho"
                                   @keydown.enter.prevent="fcRenomearBaralho(b, x.nome)" @keydown.escape="fcUI(b).renomear = null" x-init="$nextTick(() => $el.focus())">
                        </template>
                        <template x-if="!(fcUI(b).renomear && fcUI(b).renomear.de === x.nome)">
                            <b class="flex-1 min-w-0 truncate text-sm" x-text="x.nome || 'Sem baralho'"></b>
                        </template>
                        <span class="fc-n-nova" :title="x.novas + ' novas no total'" x-text="x.novasHoje + ' novas'"></span>
                        <span class="fc-n-rev" x-text="x.revisar + ' revisar'"></span>
                        <span class="texto-2" x-text="x.total + ' cartas · ' + x.maduras + ' maduras'"></span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap mt-1">
                        <button type="button" class="btn !py-0.5" @click="fcAbrirBaralho(b, x.nome)" :disabled="!(x.novasHoje + x.revisar)" x-text="x.novasHoje + x.revisar ? 'Estudar' : 'Em dia ✓'"></button>
                        <button type="button" class="btn-sec !py-0.5" @click="b.dados.materia = x.nome; fcLivre(b, 'todas')" title="Revisar todas as cartas embaralhadas, sem mudar a agenda">Estudo livre</button>
                        <button type="button" class="btn-sec !py-0.5" @click="b.dados.materia = x.nome; fcLivre(b, 'dificeis')" title="Só as cartas que você já errou">Difíceis</button>
                        <button type="button" class="link-prim" @click="fcAbrirBaralho(b, x.nome, 'editar')">Editar</button>
                        <button type="button" class="link-prim" x-show="x.nome" @click="fcUI(b).renomear = { de: x.nome, nome: x.nome }">Renomear</button>
                        <button type="button" class="mini-btn perigo ml-auto" title="Apagar o baralho" :aria-label="'Apagar o baralho ' + (x.nome || 'Sem baralho')" @click="fcApagarBaralho(b, x.nome)">✕</button>
                    </div>
                </div>
            </template>
            <div class="flex gap-1.5 items-center mt-1">
                <input class="campo !py-0.5 !text-xs flex-1" placeholder="Novo baralho (ex.: Biologia · ENEM)" x-model="fcUI(b).novoBaralho" maxlength="60" @keydown.enter.prevent="fcCriarBaralho(b)">
                <button type="button" class="btn-sec !py-0.5" @click="fcCriarBaralho(b)">Criar baralho</button>
            </div>
            <label class="texto-2 flex items-center gap-1" title="Quantas cartas novas entram por dia (somando os baralhos)">Novas por dia
                <input type="number" min="0" max="500" class="campo !py-0.5 !text-xs !w-16" x-model.number="b.dados.novasDia">
            </label>
        </div>
    </template>

    {{-- estudar (agenda ou estudo livre) --}}
    <template x-if="(b.dados.modo || 'estudar') === 'estudar'">
        <div class="flex-1 min-h-0 flex flex-col gap-2">
            <template x-if="fcUI(b).livre">
                <div class="qz-sim flex items-center gap-1.5 text-xs">
                    <b x-text="fcLivreTxt(b)"></b>
                    <span class="texto-2" x-show="fcUI(b).livre.n" x-text="'· ' + fcUI(b).livre.ok + '/' + fcUI(b).livre.n + ' certas'"></span>
                    <span class="texto-2">· não muda a agenda</span>
                    <button type="button" class="link-prim ml-auto" @click="fcSairLivre(b)">Sair</button>
                </div>
            </template>
            <template x-if="!fcAtual(b)">
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 texto-2 px-4">
                    <p class="text-2xl" aria-hidden="true">🎉</p>
                    <template x-if="fcUI(b).livre">
                        <p class="font-semibold" x-text="'Estudo livre terminado: ' + fcUI(b).livre.ok + ' de ' + fcUI(b).livre.n + ' certas (' + (fcUI(b).livre.n ? Math.round(fcUI(b).livre.ok / fcUI(b).livre.n * 100) : 0) + '%).'"></p>
                    </template>
                    <template x-if="!fcUI(b).livre">
                        <p class="font-semibold" x-text="fcCartas(b).length ? 'Tudo revisado por hoje!' : 'Nenhuma carta ainda.'"></p>
                    </template>
                    <p class="text-xs" x-show="fcCartas(b).length && !fcUI(b).livre" x-text="'Hoje: ' + fcContagem(b).feitasHoje + ' revisões' + (fcContagem(b).acertoHoje != null ? ' · ' + fcContagem(b).acertoHoje + '% de acerto' : '') + (fcContagem(b).amanha > 0 ? ' · amanhã: ' + fcContagem(b).amanha : '')"></p>
                    <div class="flex flex-wrap gap-1.5 justify-center">
                        <button type="button" class="btn !py-0.5 text-xs" x-show="fcCartas(b).length" @click="fcLivre(b, 'todas')">Estudo livre (sem agenda)</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" x-show="fcCartas(b).length" @click="fcLivre(b, 'dificeis')">Só as difíceis</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" @click="fcSairLivre(b); b.dados.modo = 'baralhos'">Baralhos</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" x-show="!fcCartas(b).length" @click="b.dados.modo = 'editar'">Criar cartas</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" x-show="!fcCartas(b).length" @click="b.dados.modo = 'importar'">Importar</button>
                    </div>
                </div>
            </template>
            <template x-if="fcAtual(b)">
                <div class="flex-1 min-h-0 flex flex-col gap-2">
                    <button type="button" class="fc-carta flex-1 min-h-0" :class="fcUI(b).virada && 'virada'" @click="fcUI(b).virada = true" :aria-label="fcUI(b).virada ? 'Resposta' : 'Mostrar a resposta'">
                        <span class="text-[11px] texto-2 flex gap-2">
                            <span x-text="fcAtual(b).materia || 'Sem baralho'"></span>
                            <span x-text="fcAtual(b).prox ? 'revisão' : 'nova'" class="fc-selo" :class="fcAtual(b).prox ? 'rev' : 'nova'"></span>
                        </span>
                        <span class="fc-frente whitespace-pre-wrap" x-html="fcHtml(fcAtual(b).frente)"></span>
                        <template x-if="fcUI(b).virada">
                            <span class="fc-verso whitespace-pre-wrap" x-html="fcHtml(fcAtual(b).verso)"></span>
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
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="btn !py-1 flex-1" x-show="!fcUI(b).virada" @click="fcUI(b).virada = true">Mostrar resposta</button>
                        <button type="button" class="link-prim text-xs" x-show="fcUI(b).ultimo && !fcUI(b).livre" @click="fcDesfazer(b)" title="Ctrl+Z">↶ Desfazer</button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    {{-- editar --}}
    <template x-if="b.dados.modo === 'editar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <div class="qz-ed">
                <textarea class="campo !text-xs" rows="2" placeholder="Frente (pergunta). Cole um print aqui; fórmula entre $: $x^2 + 1$" x-model="fcUI(b).nova.frente" maxlength="2000" @paste="fcColar(b, fcUI(b).nova, 'frente', $event)" x-ref="fcNovaFrente"></textarea>
                <textarea class="campo !text-xs" rows="2" placeholder="Verso (resposta)" x-model="fcUI(b).nova.verso" maxlength="4000" @keydown.ctrl.enter="fcAdicionar(b)" @paste="fcColar(b, fcUI(b).nova, 'verso', $event)" x-ref="fcNovoVerso"></textarea>
                <template x-if="fcRica(fcUI(b).nova.frente) || fcRica(fcUI(b).nova.verso)">
                    <div class="fc-previa text-xs"><span class="texto-2">Prévia:</span> <span class="whitespace-pre-wrap" x-html="fcHtml(fcUI(b).nova.frente)"></span><span class="texto-2"> → </span><span class="whitespace-pre-wrap" x-html="fcHtml(fcUI(b).nova.verso)"></span></div>
                </template>
                <div class="flex flex-wrap gap-1.5 items-center">
                    <input class="campo !py-0.5 !text-xs flex-1" placeholder="Baralho" x-model="fcUI(b).nova.materia" list="fc-materias" maxlength="60">
                    <label class="btn-sec !py-0.5 text-xs cursor-pointer" title="Imagem na frente">🖼 Frente<input type="file" accept="image/*" class="sr-only" @change="fcImagem(b, fcUI(b).nova, 'frente', $event.target.files[0], $refs.fcNovaFrente); $event.target.value = ''"></label>
                    <label class="btn-sec !py-0.5 text-xs cursor-pointer" title="Imagem no verso">🖼 Verso<input type="file" accept="image/*" class="sr-only" @change="fcImagem(b, fcUI(b).nova, 'verso', $event.target.files[0], $refs.fcNovoVerso); $event.target.value = ''"></label>
                    <button type="button" class="btn !py-0.5 text-xs" @click="fcAdicionar(b)" title="Ctrl+Enter">Adicionar carta</button>
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
                    <textarea class="campo !text-xs" rows="2" x-model="c.frente" aria-label="Frente" maxlength="2000" @paste="fcColar(b, c, 'frente', $event)"></textarea>
                    <textarea class="campo !text-xs" rows="2" x-model="c.verso" aria-label="Verso" maxlength="4000" @paste="fcColar(b, c, 'verso', $event)"></textarea>
                    <template x-if="fcRica(c.frente) || fcRica(c.verso)">
                        <div class="fc-previa text-xs"><span class="whitespace-pre-wrap" x-html="fcHtml(c.frente)"></span><span class="texto-2"> → </span><span class="whitespace-pre-wrap" x-html="fcHtml(c.verso)"></span></div>
                    </template>
                    <div class="flex items-center gap-1.5 text-[11px] texto-2">
                        <input class="campo !py-0.5 !text-xs" style="width:8rem" x-model="c.materia" placeholder="Baralho" list="fc-materias" maxlength="60">
                        <span x-text="c.prox ? 'próxima: ' + c.prox.split('-').reverse().join('/') + ' · intervalo ' + (c.int || 0) + 'd' + (c.lapsos ? ' · errou ' + c.lapsos + 'x' : '') : 'nova'"></span>
                        <button type="button" class="link-prim ml-auto" x-show="c.prox" @click="fcReiniciar(b, c)">Reiniciar</button>
                        <button type="button" class="mini-btn perigo" title="Apagar carta" @click="fcRemover(b, c)">✕</button>
                    </div>
                </div>
            </template>
            <p class="text-xs texto-2" x-show="fcCartas(b).length > 200">Mostrando 200 cartas. Use a busca para achar as outras.</p>
        </div>
    </template>

    {{-- importar / exportar --}}
    <template x-if="b.dados.modo === 'importar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <p class="texto-2">Uma carta por linha: <code>frente ; verso</code> (ou <code>frente ; verso ; baralho</code>). Também aceita CSV (<code>frente,verso,baralho</code>) e o .txt exportado do Anki ou do Quizlet (separado por tabulação).</p>
            <textarea class="campo !text-xs font-mono" rows="6" x-model="fcUI(b).imp" placeholder="Capital da França ; Paris&#10;Derivada de $x^2$ ; $2x$ ; Matemática"></textarea>
            <div class="flex flex-wrap gap-1.5 items-center">
                <button type="button" class="btn !py-0.5" @click="fcImportarTexto(b)">Importar cartas</button>
                <label class="btn-sec !py-0.5 cursor-pointer">Abrir arquivo (.csv, .txt do Anki)<input type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" class="sr-only" @change="fcImportarArquivo(b, $event.target.files[0]); $event.target.value = ''"></label>
            </div>
            <div class="qz-ed">
                <b x-text="'Exportar ' + (b.dados.materia ? 'o baralho “' + b.dados.materia + '”' : 'todos os baralhos') + ' (' + fcCartas(b).length + ' cartas)'"></b>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="btn-sec !py-0.5" @click="fcBaixarAnki(b)" title="No Anki: Arquivo › Importar">Para o Anki (.txt)</button>
                    <button type="button" class="btn-sec !py-0.5" @click="fcBaixarCsv(b)" title="Abre no Excel e no Google Planilhas">Planilha (.csv)</button>
                </div>
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
                <p class="texto-2">A frente leva o enunciado e as alternativas; o verso, a resposta e o comentário.</p>
            </div>
        </div>
    </template>

    {{-- estatísticas --}}
    <template x-if="b.dados.modo === 'progresso'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <div class="es-kpis">
                <div><b x-text="fcContagem(b).total"></b><span>cartas</span></div>
                <div><b x-text="fcContagem(b).maduras"></b><span>maduras (21+ dias)</span></div>
                <div><b x-text="fcContagem(b).feitasHoje"></b><span>revisões hoje</span></div>
                <div><b x-text="fcContagem(b).acertoHoje != null ? fcContagem(b).acertoHoje + '%' : '—'"></b><span>acerto hoje</span></div>
                <div><b x-text="fcContagem(b).acerto30 != null ? fcContagem(b).acerto30 + '%' : '—'"></b><span x-text="'acerto em 30 dias (' + fcContagem(b).revisoes30 + ')'"></span></div>
                <div><b x-text="peSequencia()"></b><span>dias seguidos estudando</span></div>
            </div>
            <p class="font-semibold">Revisões nos últimos 30 dias</p>
            <div class="es-graf" x-html="fcSvg(b)"></div>
            <p class="font-semibold">Próximos 14 dias</p>
            <div class="es-graf" x-html="fcPrevisaoSvg(b)"></div>
            <p class="font-semibold">Cartas por intervalo</p>
            <div class="es-graf" x-html="fcIntervalosSvg(b)"></div>
            <template x-if="fcDificeis(b).length">
                <div class="flex flex-col gap-1">
                    <p class="font-semibold">Cartas que você mais erra</p>
                    <template x-for="c in fcDificeis(b)" :key="c.id">
                        <div class="fc-baralho flex items-center gap-1.5">
                            <span class="flex-1 min-w-0 truncate" x-text="c.frente.replace(/!\[[^\]]*\]\([^)]*\)/g, '🖼')"></span>
                            <span class="texto-2 shrink-0" x-text="'errou ' + c.lapsos + 'x'"></span>
                        </div>
                    </template>
                    <button type="button" class="btn-sec !py-0.5 self-start" @click="fcLivre(b, 'dificeis')">Treinar só as difíceis</button>
                </div>
            </template>
        </div>
    </template>
</div>
