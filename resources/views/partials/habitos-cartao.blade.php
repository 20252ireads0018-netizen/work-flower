{{-- Conteúdo do cartão "Hábitos" (Corpo, Mente, Carteira e Diverso). Lógica em public/js/graficos.js (métodos hb*).
     Grade: um hábito por linha, um dia por coluna; a porcentagem conta os dias do mês até hoje. Mapa: o mês em calendário,
     mais escuro quanto mais hábitos feitos. ⚙ de cada hábito: meta por semana, lembrete diário (vira evento no calendário),
     marcação automática por tarefa concluída e selos de sequência. Um cartão Gráfico pode usar estes dados. --}}
<div class="flex flex-col h-full gap-2 min-h-0" x-init="hbAuto(b)" @focus.window="hbAuto(b)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="mini-btn" title="Mês anterior" aria-label="Mês anterior" @click="hbMes(b, -1)">‹</button>
        <span class="font-semibold capitalize" x-text="hbRotuloMes(b)"></span>
        <button type="button" class="mini-btn" title="Próximo mês" aria-label="Próximo mês" @click="hbMes(b, 1)">›</button>
        <button type="button" class="btn-sec !py-0.5" @click="hbMes(b, 0)">Este mês</button>
        <button type="button" class="btn-sec !py-0.5" :class="hbUI(b).ver !== 'mapa' && !hbAberto(b) && 'qz-on'" @click="hbUI(b).ver = 'grade'; hbUI(b).cfg = null">Grade</button>
        <button type="button" class="btn-sec !py-0.5" :class="hbUI(b).ver === 'mapa' && !hbAberto(b) && 'qz-on'" @click="hbUI(b).ver = 'mapa'; hbUI(b).cfg = null">Mapa do mês</button>
        <span class="ml-auto texto-2">Geral: <b x-text="hbGeral(b) + '%'"></b></span>
    </div>

    {{-- nenhum hábito ainda --}}
    <template x-if="!(b.dados.habitos || []).length">
        <div class="flex-1 flex flex-col items-center justify-center text-center gap-1 texto-2 text-sm px-4">
            <p class="font-semibold">Nenhum hábito ainda</p>
            <p class="text-xs">Escreva um hábito no campo abaixo (ex.: “Caminhar 30 minutos”) e aperte Enter. Depois clique nos dias em que você fez.</p>
        </div>
    </template>

    {{-- grade do mês --}}
    <template x-if="(b.dados.habitos || []).length && hbUI(b).ver !== 'mapa' && !hbAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto">
            <table class="hb-tab">
                <thead>
                    <tr>
                        <th class="hb-nome">Hábito</th>
                        <template x-for="d in hbDias(b)" :key="d.iso">
                            <th :class="{ 'fds': d.fds, 'hoje': d.hoje }"><span x-text="d.sem"></span><br><span x-text="d.n"></span></th>
                        </template>
                        <th title="Vezes nesta semana / meta da semana">Sem.</th>
                        <th title="Dias feitos no mês, até hoje">%</th>
                        <th title="Dias seguidos até hoje">🔥</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="h in (b.dados.habitos || [])" :key="h.id">
                        <tr>
                            <td class="hb-nome">
                                <div class="flex items-center gap-1.5" x-data="{ ed: false }">
                                    <button type="button" class="hb-cor" :style="'background:' + h.cor" title="Trocar a cor" aria-label="Trocar a cor" @click="hbCor(b, h)"></button>
                                    <input x-show="ed" class="wf-cel" x-model="h.nome" maxlength="80" aria-label="Nome do hábito"
                                           @keydown.enter.prevent="ed = false" @blur="ed = false" x-effect="ed && $nextTick(() => $el.focus())">
                                    <button type="button" x-show="!ed" class="hb-nome-txt" :title="h.nome + ' (clique para renomear)'" @click="ed = true">
                                        <span x-text="h.nome || 'Sem nome'"></span>
                                        <span class="hb-icones">
                                            <span :class="hbSemanaOk(b, h) && 'hb-ok'" :title="'Esta semana: ' + hbSemana(b, h) + ' de ' + hbMeta(h)" x-text="hbSemana(b, h) + '/' + hbMeta(h) + ' sem.'"></span>
                                            <span x-show="hbSeq(b, h)" :title="hbSeq(b, h) + ' dias seguidos'" x-text="'🔥' + hbSeq(b, h)"></span>
                                            <span x-show="h.hora" :title="'Lembrete às ' + h.hora">⏰</span>
                                            <span x-show="h.auto" :title="'Marca sozinho com tarefas: ' + h.auto">🔗</span>
                                            <span x-show="hbSeloAtual(b, h)" :title="hbSeloAtual(b, h) && ('Selo: ' + hbSeloAtual(b, h).nome)">🏅</span>
                                        </span>
                                    </button>
                                    <button type="button" class="mini-btn shrink-0" title="Meta, lembrete, automação e selos" aria-label="Ajustes do hábito" @click="hbUI(b).cfg = h.id; hbUI(b).msg = ''; hbCarregarTarefas()">⚙</button>
                                </div>
                            </td>
                            <template x-for="d in hbDias(b)" :key="d.iso">
                                <td :class="{ 'fds': d.fds, 'hoje': d.hoje }">
                                    <button type="button" class="hb-dia" :disabled="d.futuro || (h.semFds && d.fds)"
                                            :class="{ 'feito': hbFeito(b, h, d.iso), 'hb-pulado': h.semFds && d.fds }" :style="hbFeito(b, h, d.iso) ? '--c:' + h.cor : ''"
                                            :title="h.semFds && d.fds ? 'Fim de semana pulado neste hábito' : ''"
                                            :aria-pressed="hbFeito(b, h, d.iso)" :aria-label="h.nome + ', dia ' + d.n"
                                            @click="hbAlternar(b, h, d)"></button>
                                </td>
                            </template>
                            <td class="hb-num" :class="hbSemanaOk(b, h) && 'hb-ok'" :title="hbSemanaOk(b, h) ? 'Meta da semana batida' : 'Meta: ' + hbMeta(h) + ' vezes por semana'"
                                x-text="hbSemana(b, h) + '/' + hbMeta(h)"></td>
                            <td class="hb-num" x-text="hbPct(b, h) + '%'"></td>
                            <td class="hb-num" :title="'Melhor sequência: ' + hbMelhor(b, h) + ' dias'" x-text="hbSeq(b, h)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    {{-- mapa do mês --}}
    <template x-if="(b.dados.habitos || []).length && hbUI(b).ver === 'mapa' && !hbAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto text-xs flex flex-col gap-2">
            <div class="hb-mapa">
                <template x-for="(s, i) in ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']" :key="i"><span class="hb-mapa-cab" x-text="s"></span></template>
                <template x-for="(c, k) in hbMapa(b).flat()" :key="k">
                        <span class="hb-mapa-dia" :class="c ? ('n' + c.nivel + (c.hoje ? ' hoje' : '')) : 'vazio'"
                              :title="c && ('Dia ' + c.n + ': ' + c.qt + ' de ' + c.total + ' hábitos feitos')" x-text="c ? c.n : ''"
                              :aria-label="c ? ('Dia ' + c.n + ': ' + c.qt + ' de ' + c.total + ' hábitos') : ''"></span>
                </template>
            </div>
            <p class="texto-2 flex items-center gap-1">Menos <span class="hb-mapa-dia n0 hb-leg"></span><span class="hb-mapa-dia n1 hb-leg"></span><span class="hb-mapa-dia n2 hb-leg"></span><span class="hb-mapa-dia n3 hb-leg"></span><span class="hb-mapa-dia n4 hb-leg"></span> Mais · quantos hábitos você fez em cada dia</p>
        </div>
    </template>

    {{-- ajustes de um hábito --}}
    <template x-for="h in (hbAberto(b) ? [hbAberto(b)] : [])" :key="h.id">
        <div class="flex-1 min-h-0 overflow-auto text-xs flex flex-col gap-2" x-data="{ hora: h.hora || '' }">
            <div class="flex items-center gap-1.5">
                <button type="button" class="btn-sec !py-0.5" @click="hbUI(b).cfg = null">‹ Voltar aos hábitos</button>
                <b class="text-sm">Ajustes do hábito</b>
            </div>
            <label class="flex flex-col gap-0.5">Nome<textarea class="campo text-xs" rows="2" x-model="h.nome" maxlength="80"></textarea></label>
            <div class="cx-grade">
                <label class="flex flex-col gap-0.5">Meta por semana
                    <select class="campo !py-0.5 !text-xs" x-model.number="h.meta">
                        <template x-for="n in (h.semFds ? 5 : 7)" :key="n"><option :value="n" :selected="hbMeta(h) === n" x-text="n === 7 ? 'Todo dia (7×)' : (n === 5 && h.semFds ? 'Todo dia útil (5×)' : n + (n === 1 ? ' vez' : ' vezes'))"></option></template>
                    </select>
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer" title="Sábado e domingo ficam fora: não contam na porcentagem e não quebram a sequência">
                    <input type="checkbox" :checked="!!h.semFds" @change="hbFds(h)"> Pular fins de semana
                </label>
                <label class="flex flex-col gap-0.5">Lembrete diário
                    <span class="flex gap-1"><input type="time" class="campo !py-0.5 !text-xs flex-1" x-model="hora">
                        <button type="button" class="btn !py-0.5" @click="hbLembrete(b, h, hora)">Salvar</button>
                        <button type="button" class="btn-sec !py-0.5" x-show="h.hora" @click="hora = ''; hbLembrete(b, h, '')">Tirar</button></span>
                </label>
            </div>
            <p class="texto-2" x-show="!hbUI(b).msg">O lembrete vira um evento “todo dia” no calendário do painel e avisa no navegador na hora marcada.</p>
            <p x-show="hbUI(b).msg" x-text="hbUI(b).msg" aria-live="polite"></p>
            <label class="flex flex-col gap-0.5">Marcar sozinho quando eu concluir uma tarefa com o nome contendo
                <input class="campo !py-0.5 text-xs" x-model.debounce.600ms="h.auto" maxlength="60" placeholder="Ex.: Treino (vale “Treino de pernas”, “Treino A”...)" @change="hbAuto(b, true)">
            </label>
            <div class="texto-2" x-show="h.auto">
                <p>Tarefas desta área que batem com o nome:</p>
                <template x-for="t in hbTarefasLigadas(b, h)" :key="t.id">
                    <p>• <span x-text="t.titulo"></span> <span x-text="t.concluida_em ? '(concluída em ' + t.concluida_em.split('-').reverse().join('/') + ')' : '(ainda não concluída)'"></span></p>
                </template>
                <p x-show="!hbTarefasLigadas(b, h).length">Nenhuma ainda. Quando uma tarefa com esse nome for concluída, o dia dela é marcado aqui.</p>
            </div>
            <div class="qz-ed">
                <p class="font-semibold">Selos de sequência <span class="texto-2 font-normal" x-text="'(melhor: ' + hbMelhor(b, h) + ' dias seguidos)'"></span></p>
                <div class="flex flex-wrap gap-1">
                    <template x-for="s in hbSelos(b, h).ganhos" :key="s.dias"><span class="qz-chip hb-selo" x-text="'🏅 ' + s.nome + ' · ' + s.dias + 'd'"></span></template>
                </div>
                <p class="texto-2" x-show="hbSelos(b, h).prox" x-text="hbSelos(b, h).prox && ('Próximo selo: ' + hbSelos(b, h).prox.nome + ' (' + hbSelos(b, h).prox.dias + ' dias seguidos)')"></p>
            </div>
            <button type="button" class="btn-sec !py-0.5 perigo self-start" @click="hbRemover(b, h)">Tirar este hábito</button>
        </div>
    </template>
    <input class="campo !py-1 text-sm" placeholder="Novo hábito e Enter (ex.: Caminhar 30 minutos)" maxlength="80" @keydown.enter.prevent="hbNovo(b, $el)" aria-label="Novo hábito">
</div>
