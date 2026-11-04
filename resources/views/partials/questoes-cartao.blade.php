{{-- Conteúdo do cartão "Questões de concurso" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos qz*).
     Estudar: uma questão por vez, filtro por matéria e por "não feitas"/"erradas", placar por matéria.
     Editar: cadastrar à mão ou colar várias de uma vez (A) ... E) e "Gabarito: C"). --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo !== 'editar' && 'qz-on'" @click="b.dados.modo = 'estudar'">Estudar</button>
        <button type="button" class="btn-sec !py-0.5" :class="b.dados.modo === 'editar' && 'qz-on'" @click="b.dados.modo = 'editar'">Editar</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="qzUI(b).materia" @change="b.dados.i = 0" aria-label="Matéria">
            <option value="">Todas as matérias</option>
            <template x-for="m in qzMaterias(b)" :key="m"><option :value="m" x-text="m"></option></template>
        </select>
        <select class="campo !py-0.5 !text-xs !w-auto" x-show="b.dados.modo !== 'editar'" x-model="qzUI(b).so" @change="b.dados.i = 0" aria-label="Quais questões">
            <option value="todas">Todas</option>
            <option value="novas">Não feitas</option>
            <option value="erradas">Que errei</option>
        </select>
        <span class="ml-auto texto-2" x-text="qzPlacar(b).ok + '/' + qzPlacar(b).feitas + ' certas · ' + qzPlacar(b).pct + '%'"></span>
    </div>

    {{-- estudar --}}
    <template x-if="b.dados.modo !== 'editar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <template x-if="!qzAtual(b)">
                <p class="texto-2 text-center py-6">Nenhuma questão neste filtro. Troque o filtro ou cadastre em “Editar”.</p>
            </template>
            <template x-if="qzAtual(b)">
                <div class="flex flex-col gap-2">
                    <p class="text-xs texto-2"><span x-text="(b.dados.i + 1) + ' de ' + qzLista(b).length"></span><span x-show="qzAtual(b).materia" x-text="' · ' + qzAtual(b).materia"></span></p>
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
    <div class="flex items-center gap-1.5 text-xs" x-show="b.dados.modo !== 'editar'">
        <button type="button" class="mini-btn" title="Anterior" aria-label="Questão anterior" @click="qzIr(b, -1)">‹</button>
        <button type="button" class="mini-btn" title="Próxima" aria-label="Próxima questão" @click="qzIr(b, 1)">›</button>
        <button type="button" class="btn-sec !py-0.5" @click="qzSortear(b)">Sortear</button>
        <button type="button" class="btn-sec !py-0.5 ml-auto" @click="qzZerar(b)">Zerar respostas</button>
    </div>

    {{-- editar --}}
    <template x-if="b.dados.modo === 'editar'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-3" x-data="{ colar: false, texto: '', pdf: false, lendo: false, msgPdf: '', gabTxt: '', mat: '' }">
            <div class="flex flex-wrap gap-1.5 text-xs">
                <button type="button" class="btn !py-0.5" @click="qzNova(b)">+ Questão</button>
                <button type="button" class="btn-sec !py-0.5" @click="colar = !colar">Colar várias</button>
                <button type="button" class="btn-sec !py-0.5" @click="pdf = !pdf">Importar PDF da prova</button>
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
