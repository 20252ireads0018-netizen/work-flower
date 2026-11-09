{{-- Conteúdo do cartão "Plano de estudos" (Mente). Lógica em public/js/estudo.js (métodos pe*).
     Semana: tarefas fixas de cada dia (marcar vale só para a semana escolhida). Metas: horas por matéria na semana,
     contadas sozinhas pelas sessões dos cartões Pomodoro da página, e provas com contagem regressiva.
     Progresso: horas das últimas 8 semanas (linha vermelha = soma das metas), sequência de dias e revisões de flashcards. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <template x-for="v in [['semana', 'Semana'], ['metas', 'Metas e provas'], ['progresso', 'Progresso']]" :key="v[0]">
            <button type="button" class="btn-sec !py-0.5" :class="(b.dados.ver || 'semana') === v[0] && 'qz-on'" @click="b.dados.ver = v[0]" x-text="v[1]"></button>
        </template>
        <span class="ml-auto flex items-center gap-1">
            <button type="button" class="mini-btn" title="Semana anterior" aria-label="Semana anterior" @click="peUI(b).sem--">‹</button>
            <button type="button" class="texto-2" @click="peUI(b).sem = 0" title="Voltar para esta semana" x-text="peRotuloSemana(b)"></button>
            <button type="button" class="mini-btn" title="Próxima semana" aria-label="Próxima semana" @click="peUI(b).sem++">›</button>
        </span>
    </div>

    {{-- faixa de resumo --}}
    <div class="es-faixa text-xs">
        <div class="es-prog"><span :style="'width:' + peProgresso(b).pct + '%'"></span></div>
        <span><b x-text="(Math.round(peProgresso(b).total / 6) / 10).toLocaleString('pt-BR') + 'h'"></b><span class="texto-2" x-text="peProgresso(b).alvo ? ' de ' + (peProgresso(b).alvo / 60).toLocaleString('pt-BR') + 'h' : ' estudadas'"></span></span>
        <span class="texto-2" x-text="peProgresso(b).feitas + '/' + peProgresso(b).tarefas + ' tarefas'"></span>
        <span class="texto-2" x-show="peSequencia()" x-text="'🔥 ' + peSequencia() + (peSequencia() === 1 ? ' dia seguido' : ' dias seguidos')"></span>
        <template x-for="p in peProvas(b).slice(0, 2)" :key="p.id">
            <span class="es-prova" x-text="p.nome + ': ' + peFaltamTxt(p.faltam)"></span>
        </template>
    </div>

    {{-- semana --}}
    <template x-if="(b.dados.ver || 'semana') === 'semana'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <div class="es-semana">
                <template x-for="d in peDias(b)" :key="d.i">
                    <div class="es-dia" :class="d.hoje && 'hoje'">
                        <div class="es-dia-tit"><span x-text="d.nome"></span> <span class="texto-2 font-normal" x-text="d.data.split('-').reverse().slice(0, 2).join('/')"></span></div>
                        <template x-for="t in d.tarefas" :key="t.id">
                            <label class="es-tarefa" :class="peFeito(b, t) && 'feita'" :style="'--lc:' + (t.materia ? peCorMateria(b, t.materia) : 'var(--linha)')">
                                <input type="checkbox" :checked="peFeito(b, t)" @change="peMarcar(b, t)">
                                <span class="flex-1 min-w-0">
                                    <span class="block" x-text="t.texto"></span>
                                    <span class="block text-[10px] texto-2" x-show="t.materia" x-text="t.materia"></span>
                                </span>
                                <button type="button" class="es-x" title="Apagar tarefa" @click.prevent="peRemoverTarefa(b, t)">×</button>
                            </label>
                        </template>
                    </div>
                </template>
            </div>
            <div class="flex flex-wrap gap-1.5 items-center text-xs">
                <input class="campo !py-0.5 !text-xs flex-1" style="min-width:8rem" placeholder="Nova tarefa (repete toda semana)" x-model="peUI(b).nova.texto" @keydown.enter.prevent="peNovaTarefa(b)" maxlength="200">
                <select class="campo !py-0.5 !text-xs !w-auto" x-model.number="peUI(b).nova.dia" aria-label="Dia da semana">
                    <template x-for="(n, i) in ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']" :key="i"><option :value="i" x-text="n" :selected="i === peUI(b).nova.dia"></option></template>
                </select>
                <input class="campo !py-0.5 !text-xs" style="width:7rem" placeholder="Matéria" x-model="peUI(b).nova.materia" list="pe-materias" maxlength="60">
                <button type="button" class="btn !py-0.5" @click="peNovaTarefa(b)">Adicionar</button>
            </div>
        </div>
    </template>

    {{-- metas e provas --}}
    <template x-if="b.dados.ver === 'metas'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <template x-for="m in peProgresso(b).metas" :key="m.id">
                <div class="flex flex-col gap-0.5">
                    <div class="flex items-center gap-1.5">
                        <span class="es-bola" :style="'background:' + m.cor"></span>
                        <b x-text="m.materia"></b>
                        <span class="texto-2" x-text="(Math.round(m.feito / 6) / 10).toLocaleString('pt-BR') + 'h de'"></span>
                        <input type="number" min="0.5" max="80" step="0.5" class="campo !py-0.5 !text-xs" style="width:3.5rem" x-model.number="b.dados.metas.find(x => x.id === m.id).horas" aria-label="Horas por semana">
                        <span class="texto-2">h/semana</span>
                        <span class="ml-auto font-semibold" x-text="m.pct + '%'"></span>
                        <button type="button" class="mini-btn perigo" title="Apagar meta" @click="peRemoverMeta(b, m)">✕</button>
                    </div>
                    <div class="es-prog"><span :style="'width:' + m.pct + '%; background:' + m.cor"></span></div>
                </div>
            </template>
            <template x-if="peProgresso(b).fora.length">
                <p class="texto-2" x-text="'Também estudou: ' + peProgresso(b).fora.map(x => x.materia + ' (' + x.feito + ' min)').join(', ')"></p>
            </template>
            <div class="flex flex-wrap gap-1.5 items-center">
                <input class="campo !py-0.5 !text-xs flex-1" style="min-width:8rem" placeholder="Matéria" x-model="peUI(b).meta.materia" list="pe-materias" maxlength="60" @keydown.enter.prevent="peNovaMeta(b)">
                <input type="number" min="0.5" max="80" step="0.5" class="campo !py-0.5 !text-xs !w-16" x-model.number="peUI(b).meta.horas" aria-label="Horas por semana">
                <span class="texto-2">h/semana</span>
                <button type="button" class="btn-sec !py-0.5" @click="peNovaMeta(b)">Pôr meta</button>
            </div>
            <p class="texto-2" x-show="!estado.blocos.some(x => x.tipo === 'pomodoro')">Dica: adicione um cartão <b>Pomodoro</b> nesta página. Cada foco terminado conta aqui sozinho.</p>

            <b class="mt-1">Provas</b>
            <template x-for="p in peProvas(b)" :key="p.id">
                <div class="flex items-center gap-1.5">
                    <span x-text="p.nome"></span>
                    <span class="texto-2" x-text="p.data.split('-').reverse().join('/')"></span>
                    <span class="es-prova" x-text="peFaltamTxt(p.faltam)"></span>
                    <button type="button" class="mini-btn perigo ml-auto" title="Apagar prova" @click="peRemoverProva(b, p)">✕</button>
                </div>
            </template>
            <div class="flex flex-wrap gap-1.5 items-center">
                <input class="campo !py-0.5 !text-xs flex-1" style="min-width:8rem" placeholder="Nome da prova" x-model="peUI(b).prova.nome" maxlength="80">
                <input type="date" class="campo !py-0.5 !text-xs !w-auto" x-model="peUI(b).prova.data" aria-label="Data da prova">
                <button type="button" class="btn-sec !py-0.5" @click="peNovaProva(b)">Adicionar prova</button>
            </div>
        </div>
    </template>

    {{-- progresso --}}
    <template x-if="b.dados.ver === 'progresso'">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <div class="es-kpis">
                <div><b x-text="(Math.round(peProgresso(b).total / 6) / 10).toLocaleString('pt-BR') + 'h'"></b><span>na semana</span></div>
                <div><b x-text="peProgresso(b).pct + '%'"></b><span>das metas</span></div>
                <div><b x-text="peSequencia()"></b><span>dias seguidos</span></div>
                <div><b x-text="peRevisoesSemana(b)"></b><span>revisões de flashcards</span></div>
            </div>
            <div class="es-graf" x-html="peSvg(b)"></div>
        </div>
    </template>
    <datalist id="pe-materias"><template x-for="m in esMaterias()" :key="m"><option :value="m"></option></template></datalist>
</div>
