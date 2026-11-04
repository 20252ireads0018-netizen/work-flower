{{-- Conteúdo do cartão "Questões de concurso" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos qz*).
     Estudar: uma questão por vez, filtro por matéria e por "não feitas"/"erradas"/"último simulado", placar por matéria.
     Banco: questões abertas (OAB/FGV e ENEM/INEP, tabela questoes_banco) filtradas por banca, matéria, assunto, ano e dificuldade;
     escolher e adicionar, ou montar um simulado sorteado. Editar: cadastrar à mão, colar várias ou importar o PDF da prova. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="!['editar', 'banco'].includes(b.dados.modo) && 'qz-on'" @click="b.dados.modo = 'estudar'">Estudar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'banco' && 'qz-on'" @click="qzBancoAbrir(b)">Banco de questões</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'editar' && 'qz-on'" @click="b.dados.modo = 'editar'">Editar</button>
        <template x-if="b.dados.modo !== 'banco'">
            <span style="display: contents">
                <select class="campo !py-0.5 !text-xs !w-auto" x-model="qzUI(b).materia" @change="b.dados.i = 0; if (qzUI(b).so === 'simulado') qzUI(b).so = 'todas'" aria-label="Matéria">
                    <option value="">Todas as matérias</option>
                    <template x-for="m in qzMaterias(b)" :key="m"><option :value="m" x-text="m"></option></template>
                </select>
                <select class="campo !py-0.5 !text-xs !w-auto" x-show="b.dados.modo !== 'editar'" x-model="qzUI(b).so" @change="b.dados.i = 0" aria-label="Quais questões">
                    <option value="todas">Todas</option>
                    <option value="novas">Não feitas</option>
                    <option value="erradas">Que errei</option>
                    <option value="simulado" x-show="b.dados.simulado">Último simulado</option>
                </select>
            </span>
        </template>
        <span class="ml-auto texto-2" x-text="qzPlacar(b).ok + '/' + qzPlacar(b).feitas + ' certas · ' + qzPlacar(b).pct + '%'"></span>
    </div>

    {{-- estudar --}}
    <template x-if="!['editar', 'banco'].includes(b.dados.modo)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <template x-if="qzUI(b).so === 'simulado' && qzSimPlacar(b)">
                <div class="qz-sim" :class="qzSimPlacar(b).fim && 'fim'">
                    <b x-text="qzSimPlacar(b).fim ? 'Simulado terminado: ' + qzSimPlacar(b).ok + ' de ' + qzSimPlacar(b).total + ' (' + qzSimPlacar(b).pct + '%)' : 'Simulado: ' + qzSimPlacar(b).feitas + ' de ' + qzSimPlacar(b).total + ' respondidas'"></b>
                    <span class="texto-2" x-text="' · ' + qzSimPlacar(b).nome"></span>
                    <button type="button" class="link-prim ml-1" x-show="qzSimPlacar(b).fim" @click="qzBancoAbrir(b)">Fazer outro</button>
                </div>
            </template>
            <template x-if="!qzAtual(b)">
                <div class="texto-2 text-center py-6 flex flex-col items-center gap-2">
                    <p x-text="(b.dados.questoes || []).length ? 'Nenhuma questão neste filtro. Troque o filtro acima.' : 'Ainda não há questões neste cartão.'"></p>
                    <div class="flex flex-wrap gap-1.5 justify-center">
                        <button type="button" class="btn !py-0.5 text-xs" @click="qzBancoAbrir(b)">Escolher no banco de questões</button>
                        <button type="button" class="btn-sec !py-0.5 text-xs" @click="b.dados.modo = 'editar'">Cadastrar ou importar</button>
                    </div>
                </div>
            </template>
            <template x-if="qzAtual(b)">
                <div class="flex flex-col gap-2">
                    <p class="text-xs texto-2"><span x-text="(b.dados.i + 1) + ' de ' + qzLista(b).length"></span><span x-show="qzAtual(b).materia" x-text="' · ' + qzAtual(b).materia"></span></p>
                    <p class="text-[11px] texto-2" x-show="qzOrigem(qzAtual(b))" x-text="qzOrigem(qzAtual(b))"></p>
                    <p class="whitespace-pre-wrap font-medium" x-text="qzAtual(b).enunciado"></p>
                    <template x-for="(a, k) in qzAtual(b).alts" :key="k">
                        <button type="button" x-show="a" class="qz-alt" :class="qzClasse(b, qzAtual(b), k)" @click="qzResponder(b, qzAtual(b), k)">
                            <b x-text="qzLetra(k) + ')'"></b> <span x-text="a"></span>
                        </button>
                    </template>
                    <template x-if="qzAtual(b).id in (b.dados.resp || {})">
                        <div class="qz-res" :class="b.dados.resp[qzAtual(b).id] === qzAtual(b).certa ? 'ok' : 'erro'">
                            <b x-text="b.dados.resp[qzAtual(b).id] === qzAtual(b).certa ? 'Certa!' : 'Errou. A certa é ' + qzLetra(qzAtual(b).certa) + '.'"></b>
                            <span x-show="qzAtual(b).expl" class="whitespace-pre-wrap" x-text="' ' + qzAtual(b).expl"></span>
                            <button type="button" class="link-prim text-xs ml-1" @click="qzRefazer(b, qzAtual(b))">Refazer</button>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="qzPlacar(b).materias.length > 1">
                <div class="flex flex-wrap gap-1 text-xs mt-auto">
                    <template x-for="m in qzPlacar(b).materias" :key="m.m">
                        <span class="qz-chip" :title="m.ok + ' de ' + m.n" x-text="m.m + ': ' + m.pct + '%'"></span>
                    </template>
                </div>
            </template>
        </div>
    </template>
    <div class="flex items-center gap-1.5 text-xs" x-show="!['editar', 'banco'].includes(b.dados.modo)">
        <button type="button" class="mini-btn" title="Anterior" aria-label="Questão anterior" @click="qzIr(b, -1)">‹</button>
        <button type="button" class="mini-btn" title="Próxima" aria-label="Próxima questão" @click="qzIr(b, 1)">›</button>
        <button type="button" class="btn-sec !py-0.5" @click="qzSortear(b)">Sortear</button>
        <button type="button" class="btn-sec !py-0.5 ml-auto" @click="qzZerar(b)">Zerar respostas</button>
    </div>

    {{-- banco de questões abertas --}}
    <template x-if="b.dados.modo === 'banco'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" class="btn-sec !py-0.5" @click="b.dados.modo = 'estudar'">‹ Voltar a estudar</button>
                <b class="text-sm">Banco de questões</b>
                <span class="texto-2" x-show="qzUI(b).filtros" x-text="qzUI(b).filtros && (qzUI(b).filtros.total.toLocaleString('pt-BR') + ' questões de provas públicas')"></span>
            </div>
            <p class="texto-2">Escolha os filtros, abra uma questão para ver as alternativas e marque as que quer estudar. Ou monte um simulado sorteado.</p>
            <template x-if="qzUI(b).filtros">
                <div class="cx-grade">
                    <label class="flex flex-col gap-0.5">Banca
                        <select class="campo !py-0.5 !text-xs" x-model="qzUI(b).bf.banca" @change="qzBancoMudou(b, 'banca')">
                            <option value="">Todas</option>
                            <template x-for="x in qzUI(b).filtros.bancas" :key="x.nome"><option :value="x.nome" x-text="x.nome + ' (' + x.n + ')'"></option></template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Matéria
                        <select class="campo !py-0.5 !text-xs" x-model="qzUI(b).bf.materia" @change="qzBancoMudou(b, 'materia')">
                            <option value="">Todas</option>
                            <template x-for="x in qzBancoMaterias(b)" :key="x.nome"><option :value="x.nome" x-text="x.nome" :selected="x.nome === qzUI(b).bf.materia"></option></template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Assunto
                        <select class="campo !py-0.5 !text-xs" x-model="qzUI(b).bf.assunto" @change="qzBancoMudou(b, 'assunto')" :disabled="!qzUI(b).bf.materia" :title="qzUI(b).bf.materia ? '' : 'Escolha a matéria primeiro'">
                            <option value="" x-text="qzUI(b).bf.materia ? 'Todos' : 'Escolha a matéria'"></option>
                            <template x-for="x in qzBancoAssuntos(b)" :key="x.nome"><option :value="x.nome" x-text="x.nome + ' (' + x.n + ')'" :selected="x.nome === qzUI(b).bf.assunto"></option></template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Ano
                        <select class="campo !py-0.5 !text-xs" x-model="qzUI(b).bf.ano" @change="qzBancoMudou(b, 'ano')">
                            <option value="">Todos</option>
                            <template x-for="a in qzUI(b).filtros.anos" :key="a"><option :value="a" x-text="a"></option></template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Dificuldade (estimada)
                        <select class="campo !py-0.5 !text-xs" x-model="qzUI(b).bf.dificuldade" @change="qzBancoMudou(b, 'dificuldade')">
                            <option value="">Todas</option>
                            <template x-for="d in qzUI(b).filtros.dificuldades" :key="d.id"><option :value="d.id" x-text="d.nome"></option></template>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Palavra no enunciado
                        <input class="campo !py-0.5 text-xs" x-model="qzUI(b).bf.busca" maxlength="80" placeholder="Ex.: usucapião" @keydown.enter.prevent="qzBancoBuscar(b, 1)" @change="qzBancoBuscar(b, 1)">
                    </label>
                </div>
            </template>
            <div class="flex flex-wrap items-center gap-1.5 qz-barra-banco">
                <span class="texto-2" x-show="qzUI(b).res" x-text="qzUI(b).res && (qzUI(b).res.total.toLocaleString('pt-BR') + ' encontradas')"></span>
                <button type="button" class="link-prim" @click="qzBancoLimpar(b)">Limpar filtros</button>
                <span class="flex flex-wrap items-center gap-1 ml-auto">Simulado de
                    <input type="number" min="1" max="100" class="campo !py-0.5 !text-xs" style="width: 4rem" x-model.number="qzUI(b).nSim" aria-label="Quantas questões no simulado">
                    questões <button type="button" class="btn !py-0.5" :disabled="qzUI(b).carregando" @click="qzSimulado(b)">Montar simulado</button></span>
            </div>
            <p class="text-red-500" x-show="qzUI(b).erro" x-text="qzUI(b).erro" aria-live="polite"></p>
            <p class="texto-2" x-show="qzUI(b).carregando">Carregando…</p>

            <template x-if="qzUI(b).res && !qzUI(b).res.itens.length && !qzUI(b).carregando">
                <p class="texto-2 text-center py-4">Nenhuma questão com esses filtros. Tire algum filtro ou use “Limpar filtros”.</p>
            </template>
            <template x-if="qzUI(b).res && qzUI(b).res.itens.length">
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <button type="button" class="link-prim" @click="qzBancoMarcarPagina(b)">Marcar/desmarcar a página</button>
                        <span class="ml-auto texto-2" x-text="'Página ' + qzUI(b).res.pagina + ' de ' + qzUI(b).res.paginas"></span>
                        <button type="button" class="mini-btn" aria-label="Página anterior" title="Página anterior" :disabled="qzUI(b).res.pagina <= 1" @click="qzBancoBuscar(b, qzUI(b).res.pagina - 1)">‹</button>
                        <button type="button" class="mini-btn" aria-label="Próxima página" title="Próxima página" :disabled="qzUI(b).res.pagina >= qzUI(b).res.paginas" @click="qzBancoBuscar(b, qzUI(b).res.pagina + 1)">›</button>
                    </div>
                    <template x-for="q in qzUI(b).res.itens" :key="q.id">
                        <div class="qz-ed qz-banco-item" :class="qzUI(b).sel[q.id] && 'sel'">
                            <div class="flex gap-2 items-start">
                                <input type="checkbox" class="mt-0.5" :checked="!!qzUI(b).sel[q.id] || qzBancoJa(b, q)" :disabled="qzBancoJa(b, q)" @change="qzBancoMarcar(b, q)" :aria-label="'Escolher a questão ' + q.ref">
                                <button type="button" class="text-left flex-1 min-w-0" @click="qzUI(b).abertos[q.id] = !qzUI(b).abertos[q.id]" :aria-expanded="!!qzUI(b).abertos[q.id]">
                                    <span class="block text-[11px] texto-2" x-text="q.banca + ' · ' + q.prova + ' · ' + q.materia + (q.assunto !== 'Geral' ? ' · ' + q.assunto : '') + ' · ' + qzDif(q.dificuldade)"></span>
                                    <span class="block whitespace-pre-wrap" :class="!qzUI(b).abertos[q.id] && 'qz-2linhas'" x-text="q.enunciado"></span>
                                </button>
                                <span class="qz-chip shrink-0" x-show="qzBancoJa(b, q)">no cartão</span>
                            </div>
                            <template x-if="qzUI(b).abertos[q.id]">
                                <div class="flex flex-col gap-0.5 pl-6">
                                    <template x-for="(a, k) in q.alts" :key="k"><p x-show="a"><b x-text="qzLetra(k) + ')'"></b> <span x-text="a"></span></p></template>
                                    <p class="texto-2">A resposta aparece quando você resolver no “Estudar”.</p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <div class="qz-rodape-banco" x-show="qzBancoNSel(b)">
                <span x-text="qzBancoNSel(b) + ' ' + (qzBancoNSel(b) === 1 ? 'escolhida' : 'escolhidas')"></span>
                <button type="button" class="link-prim" @click="qzUI(b).sel = {}">Desmarcar</button>
                <button type="button" class="btn !py-0.5 ml-auto" @click="qzBancoAdicionar(b)">Adicionar ao cartão</button>
            </div>
            <p class="texto-2 text-[11px]">Fontes abertas: provas da OAB (FGV e Cespe, conjunto legal-nlp/oab-exams, licença MIT) e do ENEM 2022–2024 (INEP, conjunto maritaca-ai/enem, licença Apache-2.0). Assunto e dificuldade são calculados automaticamente.</p>
        </div>
    </template>

    {{-- editar --}}
    <template x-if="b.dados.modo === 'editar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-3" x-data="{ colar: false, texto: '', pdf: false, lendo: false, msgPdf: '', gabTxt: '', mat: '' }">
            <div class="flex flex-wrap gap-1.5 text-xs">
                <button type="button" class="btn !py-0.5" @click="qzNova(b)">+ Questão</button>
                <button type="button" class="btn-sec !py-0.5" @click="colar = !colar">Colar várias</button>
                <button type="button" class="btn-sec !py-0.5" @click="pdf = !pdf">Importar PDF da prova</button>
                <button type="button" class="btn-sec !py-0.5" @click="qzBancoAbrir(b)">Banco de questões</button>
                <span class="texto-2 self-center" x-text="(b.dados.questoes || []).length + ' questões'"></span>
            </div>
            {{-- prova e gabarito em PDF (com texto; prova escaneada não dá) --}}
            <div x-show="pdf" class="qz-ed text-xs">
                <label class="flex flex-col gap-0.5">Prova (PDF)<input type="file" accept="application/pdf" x-ref="qzProva" class="text-xs"></label>
                <label class="flex flex-col gap-0.5">Gabarito em PDF (opcional, se não estiver no fim da prova)<input type="file" accept="application/pdf" x-ref="qzGab" class="text-xs"></label>
                <label class="flex flex-col gap-0.5">ou digite o gabarito<input class="campo !py-0.5 text-xs" x-model="gabTxt" maxlength="4000" placeholder="1-C 2-A 3-E 4-B ..."></label>
                <label class="flex flex-col gap-0.5">Matéria ou nome da prova<input class="campo !py-0.5 text-xs" x-model="mat" maxlength="60" placeholder="Ex.: TRT 2024 · Português (se vazio, usa o nome do arquivo)"></label>
                <button type="button" class="btn !py-0.5 text-xs self-start" :disabled="lendo"
                        @click="lendo = true; msgPdf = 'Lendo o PDF…'; qzImportarPdf(b, $refs.qzProva.files[0], $refs.qzGab.files[0], gabTxt, mat).then(m => { msgPdf = m; lendo = false; })"
                        x-text="lendo ? 'Lendo…' : 'Importar questões'"></button>
                <p x-show="msgPdf" x-text="msgPdf" aria-live="polite"></p>
                <p class="texto-2">Funciona com PDFs que têm texto (dá para selecionar as palavras). As questões precisam ser numeradas (1., 2., Questão 3...) com alternativas A) a E); provas Certo/Errado também entram.</p>
            </div>
            <div x-show="colar" class="flex flex-col gap-1">
                <textarea class="campo text-xs" rows="6" x-model="texto" placeholder="1. Enunciado da questão&#10;A) primeira&#10;B) segunda&#10;C) terceira&#10;Gabarito: B&#10;Matéria: Português (opcional)&#10;Explicação: ... (opcional)&#10;&#10;&#10;2. Próxima questão..."></textarea>
                <button type="button" class="btn !py-0.5 text-xs self-start" @click="if (qzImportar(b, texto)) { texto = ''; colar = false; }">Importar</button>
            </div>
            <template x-for="(q, n) in [...(b.dados.questoes || [])].reverse()" :key="q.id">
                <div class="qz-ed">
                    <div class="flex gap-1.5 items-center">
                        <input class="campo !py-0.5 text-xs flex-1" x-model="q.materia" maxlength="60" placeholder="Matéria" aria-label="Matéria" list="qz-materias">
                        <button type="button" class="mini-btn perigo" title="Tirar a questão" aria-label="Tirar a questão" @click="qzRemover(b, q)">✕</button>
                    </div>
                    <p class="text-[11px] texto-2" x-show="qzOrigem(q)" x-text="'Do banco: ' + qzOrigem(q)"></p>
                    <textarea class="campo text-xs" rows="2" x-model="q.enunciado" maxlength="4000" placeholder="Enunciado" aria-label="Enunciado"></textarea>
                    <template x-for="(a, k) in q.alts" :key="k">
                        <label class="flex items-center gap-1.5 text-xs">
                            <input type="radio" :name="'qz-' + q.id" :checked="q.certa === k" @change="q.certa = k" :title="'Marcar ' + qzLetra(k) + ' como certa'">
                            <b x-text="qzLetra(k)"></b>
                            <input class="campo !py-0.5 text-xs flex-1" x-model="q.alts[k]" maxlength="800" :placeholder="'Alternativa ' + qzLetra(k) + (k > 1 ? ' (opcional)' : '')">
                        </label>
                    </template>
                    <input class="campo !py-0.5 text-xs" x-model="q.expl" maxlength="2000" placeholder="Explicação (aparece depois de responder)" aria-label="Explicação">
                </div>
            </template>
            <datalist id="qz-materias"><template x-for="m in qzMaterias(b)" :key="m"><option :value="m"></option></template></datalist>
        </div>
    </template>
</div>
