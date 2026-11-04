{{-- Conteúdo do cartão "Gráfico" (Corpo, Mente, Carteira e Diverso). Lógica e desenho em public/js/graficos.js (métodos gr*).
     Os dados vêm do próprio cartão (digitados), de um cartão Tabela ou de um cartão Hábitos da página. --}}
<div class="flex flex-col h-full gap-2 min-h-0">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <select class="campo !py-1 !w-auto" aria-label="Tipo de gráfico" x-model="b.dados.tipo">
            <option value="barras">Barras</option>
            <option value="linhas">Linhas</option>
            <option value="pizza">Pizza</option>
        </select>
        <select class="campo !py-1 !w-auto" style="max-width: 13rem" aria-label="De onde vêm os dados" @change="grEscolherFonte(b, $event.target.value)">
            <option value="manual" :selected="b.dados.fonte === 'manual'">Dados digitados aqui</option>
            <template x-for="f in grFontes(b)" :key="f.id">
                <option :value="f.id" :selected="b.dados.ref === f.id" x-text="f.nome"></option>
            </template>
        </select>
        <button type="button" class="btn-sec !py-1" :aria-pressed="grUI(b).editar" @click="grUI(b).editar = !grUI(b).editar"
                x-text="grUI(b).editar ? 'Ver gráfico' : 'Dados'"></button>
        <span class="texto-2 ml-auto truncate" x-text="grResumo(b)"></span>
    </div>

    {{-- gráfico --}}
    <div class="flex-1 min-h-0 gr-area" x-show="!grUI(b).editar" x-html="grSvg(b)"></div>

    {{-- dados digitados: primeira coluna = nome de cada item; as outras = séries --}}
    <template x-if="grUI(b).editar && b.dados.fonte === 'manual'">
        <div class="flex-1 min-h-0 overflow-auto">
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
    <template x-if="grUI(b).editar && b.dados.fonte === 'tabela'">
        <div class="flex-1 min-h-0 overflow-auto text-sm space-y-2">
            <p class="texto-2 text-xs">A primeira coluna da tabela dá o nome de cada item. Marque as colunas com números que entram no gráfico:</p>
            <template x-for="c in grColunas(b)" :key="c.j">
                <label class="flex items-center gap-2"><input type="checkbox" :checked="c.on" @change="grAlternarColuna(b, c.j)"> <span x-text="c.nome"></span></label>
            </template>
            <p class="texto-2 text-xs" x-show="!grColunas(b).length">A tabela precisa de pelo menos duas colunas.</p>
        </div>
    </template>

    <template x-if="grUI(b).editar && b.dados.fonte === 'habitos'">
        <p class="flex-1 text-sm texto-2">O gráfico mostra a porcentagem de dias feitos de cada hábito no mês que está aberto no cartão de Hábitos.
            Mude o mês lá para ver outro.</p>
    </template>
</div>
