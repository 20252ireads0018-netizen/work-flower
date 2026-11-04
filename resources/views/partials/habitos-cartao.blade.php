{{-- Conteúdo do cartão "Hábitos" (Corpo, Mente, Carteira e Diverso). Lógica em public/js/graficos.js (métodos hb*).
     Um hábito por linha, um dia por coluna; a porcentagem conta os dias do mês até hoje. Um cartão Gráfico pode usar estes dados. --}}
<div class="flex flex-col h-full gap-2 min-h-0">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="mini-btn" title="Mês anterior" aria-label="Mês anterior" @click="hbMes(b, -1)">‹</button>
        <span class="font-semibold capitalize" x-text="hbRotuloMes(b)"></span>
        <button type="button" class="mini-btn" title="Próximo mês" aria-label="Próximo mês" @click="hbMes(b, 1)">›</button>
        <button type="button" class="btn-sec !py-0.5" @click="hbMes(b, 0)">Este mês</button>
        <span class="ml-auto texto-2">Geral: <b x-text="hbGeral(b) + '%'"></b></span>
    </div>

    <div class="flex-1 min-h-0 overflow-auto">
        <table class="hb-tab">
            <thead>
                <tr>
                    <th class="hb-nome">Hábito</th>
                    <template x-for="d in hbDias(b)" :key="d.iso">
                        <th :class="{ 'fds': d.fds, 'hoje': d.hoje }"><span x-text="d.sem"></span><br><span x-text="d.n"></span></th>
                    </template>
                    <th title="Dias feitos no mês, até hoje">%</th>
                    <th title="Dias seguidos até hoje">🔥</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="h in (b.dados.habitos || [])" :key="h.id">
                    <tr>
                        <td class="hb-nome">
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="hb-cor" :style="'background:' + h.cor" title="Trocar a cor" aria-label="Trocar a cor" @click="hbCor(b, h)"></button>
                                <input class="wf-cel" x-model="h.nome" maxlength="60" aria-label="Nome do hábito">
                            </div>
                        </td>
                        <template x-for="d in hbDias(b)" :key="d.iso">
                            <td :class="{ 'fds': d.fds, 'hoje': d.hoje }">
                                <button type="button" class="hb-dia" :disabled="d.futuro"
                                        :class="{ 'feito': hbFeito(b, h, d.iso) }" :style="hbFeito(b, h, d.iso) ? '--c:' + h.cor : ''"
                                        :aria-pressed="hbFeito(b, h, d.iso)" :aria-label="h.nome + ', dia ' + d.n"
                                        @click="hbAlternar(b, h, d)"></button>
                            </td>
                        </template>
                        <td class="hb-num" x-text="hbPct(b, h) + '%'"></td>
                        <td class="hb-num" x-text="hbSeq(b, h)"></td>
                        <td><button type="button" class="mini-btn perigo" title="Tirar o hábito" aria-label="Tirar o hábito" @click="hbRemover(b, h)">✕</button></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <input class="campo !py-1 text-sm" placeholder="Novo hábito e Enter" maxlength="60" @keydown.enter.prevent="hbNovo(b, $el)">
</div>
