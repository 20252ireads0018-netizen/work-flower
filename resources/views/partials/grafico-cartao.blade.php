{{-- Conteúdo do cartão "Gráfico" (Corpo, Mente, Carteira e Diverso). Lógica e desenho em public/js/graficos.js (métodos gr*).
     Os dados vêm do próprio cartão (digitados) ou de outro cartão da página (Tabela, Hábitos, Questões, RH, Criador), ao vivo.
     O desenho anima quando os valores mudam (grDesenhar, por x-effect). "Ajustes": eixos, meta, alerta e evolução diária. --}}
<div class="gr-raiz flex flex-col h-full gap-2 min-h-0">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <select class="campo !py-1 !w-auto" aria-label="Tipo de gráfico" x-model="b.dados.tipo">
            <template x-for="t in grTipos()" :key="t.id"><option :value="t.id" x-text="t.nome" :selected="t.id === b.dados.tipo"></option></template>
        </select>
        <select class="campo !py-1 !w-auto" style="max-width: 13rem" aria-label="De onde vêm os dados" @change="grEscolherFonte(b, $event.target.value)">
            <option value="manual" :selected="b.dados.fonte === 'manual'">Dados digitados aqui</option>
            <template x-for="f in grFontes(b)" :key="f.id">
                <option :value="f.id" :selected="b.dados.ref === f.id" x-text="f.nome"></option>
            </template>
        </select>
        <button type="button" class="btn-sec !py-1" :class="grUI(b).editar && grUI(b).painel === 'dados' && 'qz-on'"
                @click="grUI(b).editar = !(grUI(b).editar && grUI(b).painel === 'dados'); grUI(b).painel = 'dados'">Dados</button>
        <button type="button" class="btn-sec !py-1" :class="grUI(b).editar && grUI(b).painel === 'ajustes' && 'qz-on'"
                @click="grUI(b).editar = !(grUI(b).editar && grUI(b).painel === 'ajustes'); grUI(b).painel = 'ajustes'">Ajustes</button>
        <button type="button" class="btn-sec !py-1" x-show="grUI(b).editar" @click="grUI(b).editar = false">‹ Ver gráfico</button>
        <button type="button" class="btn-sec !py-1" x-show="!grUI(b).editar && grHistN(b) > 0" :class="b.dados.verHist && 'qz-on'"
                @click="b.dados.verHist = !b.dados.verHist" title="Valores guardados um por dia">Evolução diária</button>
        <span class="texto-2 ml-auto truncate" x-text="grResumo(b)"></span>
    </div>
    <template x-if="grMetaStatus(b) && !grUI(b).editar">
        <p class="text-[11px] gr-meta-chip" :class="grMetaStatus(b).ok ? 'ok' : ''" x-text="grMetaStatus(b).txt"></p>
    </template>

    {{-- gráfico (desenhado e animado pelo grDesenhar) --}}
    <div class="flex-1 min-h-0 gr-area" x-show="!grUI(b).editar" x-effect="grDesenhar(b, $el)"></div>
    <div class="flex flex-wrap gap-1.5 text-xs" x-show="!grUI(b).editar">
        <button type="button" class="btn-sec !py-0.5" title="Baixar o gráfico como imagem" @click="grPng(b, $el.closest('.gr-raiz'))">Baixar PNG</button>
        <button type="button" class="btn-sec !py-0.5" title="Baixar os números para o Excel ou Planilhas" @click="grCsv(b)">Baixar CSV</button>
    </div>

    {{-- dados digitados: primeira coluna = nome de cada item; as outras = séries --}}
    <template x-if="grUI(b).editar && grUI(b).painel === 'dados' && b.dados.fonte === 'manual'">
        <div class="flex-1 min-h-0 overflow-auto">
            <p class="texto-2 text-xs mb-1">A primeira coluna dá o nome de cada item (ex.: o mês). Cada coluna seguinte é uma série com números.</p>
            <table class="gr-tab">
                <thead>
                    <tr>
                        <template x-for="(c, j) in b.dados.cab" :key="'c' + j">
                            <th>
                                <div class="flex items-center gap-1">
                                    <input class="wf-cel" x-model="b.dados.cab[j]" :aria-label="j ? 'Nome da série' : 'Nome da coluna dos itens'">
                                    <button type="button" class="mini-btn perigo" x-show="j > 0 && b.dados.cab.length > 2" title="Tirar a série" @click="grRemSerie(b, j)">✕</button>
                                </div>
                            </th>
                        </template>
                        <th><button type="button" class="mini-btn" title="Nova série" aria-label="Nova série" @click="grAddSerie(b)">+</button></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(l, i) in b.dados.linhas" :key="'l' + i">
                        <tr>
                            <template x-for="(v, j) in l" :key="'v' + j">
                                <td><input class="wf-cel" x-model="b.dados.linhas[i][j]" :inputmode="j ? 'decimal' : 'text'" :aria-label="j ? 'Valor' : 'Item'"></td>
                            </template>
                            <td><button type="button" class="mini-btn perigo" title="Tirar a linha" aria-label="Tirar a linha" @click="grRemLinha(b, i)">✕</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <button type="button" class="btn-sec !py-1 mt-2 text-xs" @click="grAddLinha(b)">+ Linha</button>
        </div>
    </template>

    {{-- dados de uma tabela: escolher as colunas que viram séries --}}
    <template x-if="grUI(b).editar && grUI(b).painel === 'dados' && b.dados.fonte === 'tabela'">
        <div class="flex-1 min-h-0 overflow-auto text-sm space-y-2">
            <p class="texto-2 text-xs">A primeira coluna da tabela dá o nome de cada item. Marque as colunas com números que entram no gráfico:</p>
            <template x-for="c in grColunas(b)" :key="c.j">
                <label class="flex items-center gap-2"><input type="checkbox" :checked="c.on" @change="grAlternarColuna(b, c.j)"> <span x-text="c.nome"></span></label>
            </template>
            <p class="texto-2 text-xs" x-show="!grColunas(b).length">A tabela precisa de pelo menos duas colunas.</p>
            <p class="texto-2 text-xs">O gráfico acompanha a tabela ao vivo: mudou lá, muda aqui.</p>
        </div>
    </template>

    <template x-if="grUI(b).editar && grUI(b).painel === 'dados' && !['manual', 'tabela'].includes(b.dados.fonte)">
        <div class="flex-1 text-sm texto-2 space-y-1">
            <p x-show="b.dados.fonte === 'habitos'">O gráfico mostra a porcentagem de dias feitos de cada hábito no mês aberto no cartão de Hábitos. Mude o mês lá para ver outro.</p>
            <p x-show="b.dados.fonte === 'questoes'">O gráfico mostra a porcentagem de acerto em cada matéria do cartão de Questões.</p>
            <p x-show="b.dados.fonte === 'rh'">O gráfico mostra quantos candidatos estão em cada etapa do cartão de RH.</p>
            <p x-show="b.dados.fonte === 'criador'">O gráfico mostra quantos posts estão em cada etapa do cartão Criador de conteúdo.</p>
            <p>Os números seguem o <span x-text="grNomeFonte(b)"></span> ao vivo.</p>
        </div>
    </template>

    {{-- ajustes: eixos, meta, alerta e evolução diária --}}
    <template x-if="grUI(b).editar && grUI(b).painel === 'ajustes'">
        <div class="flex-1 min-h-0 overflow-auto text-xs flex flex-col gap-3">
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Títulos dos eixos</legend>
                <div class="cx-grade">
                    <label class="flex flex-col gap-0.5">Eixo horizontal (embaixo)<input class="campo !py-0.5 text-xs" x-model="b.dados.eixoX" maxlength="50" placeholder="Ex.: Mês"></label>
                    <label class="flex flex-col gap-0.5">Eixo vertical (lado)<input class="campo !py-0.5 text-xs" x-model="b.dados.eixoY" maxlength="34" placeholder="Ex.: Vendas (R$)"></label>
                </div>
                <p class="texto-2">Pizza e rosca não têm eixos.</p>
            </fieldset>
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Meta</legend>
                <div class="cx-grade">
                    <label class="flex flex-col gap-0.5">Valor da meta<input class="campo !py-0.5 text-xs" x-model="b.dados.meta" inputmode="decimal" maxlength="20" placeholder="Ex.: 1000"></label>
                    <label class="flex flex-col gap-0.5">Nome da linha<input class="campo !py-0.5 text-xs" x-model="b.dados.metaNome" maxlength="20" placeholder="Meta"></label>
                    <label class="flex flex-col gap-0.5">Comparar com
                        <select class="campo !py-0.5 !text-xs" x-model="b.dados.diarioValor">
                            <option value="total">o total da 1ª série</option>
                            <option value="media">a média da 1ª série</option>
                            <option value="ultimo">o último valor</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-0.5">Avisar quando
                        <select class="campo !py-0.5 !text-xs" x-model="b.dados.alerta">
                            <option value="">não avisar</option>
                            <option value="acima">chegar na meta</option>
                            <option value="abaixo">cair abaixo da meta</option>
                            <option value="ambos">cruzar a meta (os dois)</option>
                        </select>
                    </label>
                </div>
                <p class="texto-2">A meta aparece como uma linha tracejada. O aviso aparece na página e, se você permitir, como notificação.
                    <button type="button" class="link-prim" @click="grPedirNotificacao()">Permitir notificações</button></p>
            </fieldset>
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Evolução diária (automática)</legend>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="b.dados.diario"> Guardar o valor de cada dia</label>
                <p class="texto-2">Todo dia em que o quadro for aberto, o gráfico guarda o <span x-text="grValorNome(b)"></span> do dia
                    (ótimo com Hábitos ou uma Tabela). Depois, “Evolução diária” mostra a linha do tempo.</p>
                <p class="flex items-center gap-2" x-show="grHistN(b)"><span x-text="grHistN(b) + ' dias guardados'"></span>
                    <button type="button" class="link-prim" @click="grLimparHist(b)">Apagar a evolução</button></p>
            </fieldset>
        </div>
    </template>
</div>
