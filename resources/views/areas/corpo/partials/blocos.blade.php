{{-- Espera: $sec (nome da seção, ex.: 'treinos') · desenha os blocos livres dessa aba --}}
<template x-for="b in blocosDe('{{ $sec }}')" :key="b.id">
    <x-corpo.item sec="b.secao" bid="b.id" :minw="200" :minh="120" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco" placeholder="Título">
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" aria-label="Excluir bloco"
                    @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="h-full">

            {{-- Texto --}}
            <template x-if="b.tipo === 'texto'">
                <div class="h-full flex flex-col gap-2">
                    <select class="wf-link-sel self-end" x-model="b.dados.tam" aria-label="Tamanho da letra">
                        <option value="p">Letra pequena</option>
                        <option value="m">Letra média</option>
                        <option value="g">Letra grande</option>
                    </select>
                    <textarea class="campo flex-1 resize-none" x-model="b.dados.texto" placeholder="Escreva aqui"
                              :style="'font-size:' + ({ p: '.8rem', m: '.95rem', g: '1.2rem' }[b.dados.tam] || '.95rem')"></textarea>
                </div>
            </template>

            {{-- Tabela (estilo planilha · lógica em public/js/tabela.js) --}}
            <template x-if="b.tipo === 'tabela'">
                <div class="wf-xl" data-xl x-init="tbGarantir(b)" @keydown="tbTeclaRaiz($event, b)">

                    <div class="wf-xl-barra" role="toolbar" aria-label="Ferramentas da tabela">
                        <button type="button" class="btn-sec" :disabled="!tbUI(b).hist.length" @click="tbDesfazer(b)" title="Desfazer (Ctrl+Z)">↶</button>
                        <button type="button" class="btn-sec" :disabled="!tbUI(b).refaz.length" @click="tbRefazer(b)" title="Refazer (Ctrl+Y)">↷</button>
                        <span class="wf-xl-sep"></span>

                        <select class="campo" aria-label="Formato da coluna" title="Formato dos números da coluna" @change="tbFmt(b, $event.target.value)">
                            <template x-for="f in tbFormatos" :key="f.id">
                                <option :value="f.id" :selected="tbCol(b, tbUI(b).c).fmt === f.id" x-text="f.nome"></option>
                            </template>
                        </select>
                        <button type="button" class="btn-sec" @click="tbAlinhar(b, 'e')" title="Alinhar coluna à esquerda">⇤</button>
                        <button type="button" class="btn-sec" @click="tbAlinhar(b, 'c')" title="Centralizar coluna">↔</button>
                        <button type="button" class="btn-sec" @click="tbAlinhar(b, 'd')" title="Alinhar coluna à direita">⇥</button>
                        <button type="button" class="btn-sec" @click="tbAlinhar(b, '')" title="Alinhamento automático (números à direita)">Auto</button>
                        <span class="wf-xl-sep"></span>

                        <button type="button" class="btn-sec" style="font-weight:700" @click="tbNegrito(b)" title="Negrito (Ctrl+B)">N</button>
                        <input type="color" class="wf-cor !w-6 !h-6" value="#fde68a" @change="tbFundo(b, $event.target.value)" aria-label="Cor de fundo da seleção" title="Cor de fundo da seleção">
                        <button type="button" class="btn-sec" @click="tbFundo(b, '')" title="Remover cor de fundo">Sem cor</button>
                        <span class="wf-xl-sep"></span>

                        <button type="button" class="btn-sec" @click="tbOrdenar(b, 1)" title="Ordenar a coluna ativa de A a Z">A→Z</button>
                        <button type="button" class="btn-sec" @click="tbOrdenar(b, -1)" title="Ordenar a coluna ativa de Z a A">Z→A</button>
                        <button type="button" class="btn-sec" @click="tbAutoSoma(b)" title="Soma automática dos números acima (ou à esquerda)">Σ</button>
                        <button type="button" class="btn-sec" @click="tbPreencher(b, 'baixo')" title="Preencher para baixo (Ctrl+D)">Preencher ↓</button>
                        <button type="button" class="btn-sec" @click="tbPreencher(b, 'direita')" title="Preencher para a direita (Ctrl+R)">Preencher →</button>
                        <button type="button" class="btn-sec" @click="tbLimpar(b)" title="Limpar o conteúdo da seleção (Delete)">Limpar</button>
                        <span class="wf-xl-sep"></span>

                        <button type="button" class="btn-sec" @click="tbLinhaIns(b, tbIntervalo(b).i2 + 1)" title="Inserir linha abaixo da seleção">+ Linha aqui</button>
                        <button type="button" class="btn-sec" @click="tbColIns(b, tbIntervalo(b).j2 + 1)" title="Inserir coluna à direita da seleção">+ Coluna aqui</button>
                        <select class="campo" aria-label="Rodapé de totais da coluna" title="Total no rodapé da coluna" @change="tbRodapeSet(b, $event.target.value)">
                            <template x-for="a in tbAgregados" :key="a.id">
                                <option :value="a.id" :selected="((b.dados.rodape || [])[tbUI(b).c] || '') === a.id" x-text="a.nome"></option>
                            </template>
                        </select>
                        <input class="campo" type="search" style="width:8rem" placeholder="Filtrar linhas…" aria-label="Filtrar linhas" x-model="tbUI(b).filtro">
                        <span class="wf-xl-sep"></span>

                        <label class="btn-sec" title="Importar um arquivo CSV/TSV (substitui a tabela)">Importar CSV
                            <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="tbImportar(b, $event.target.files[0]); $event.target.value = ''">
                        </label>
                        <button type="button" class="btn-sec" @click="tbExportar(b)" title="Baixar como CSV (abre no Excel)">Exportar CSV</button>
                    </div>

                    {{-- Barra de fórmulas --}}
                    <div class="wf-xl-fx">
                        <span class="wf-xl-ref" x-text="tbRef(b)" title="Célula ou intervalo selecionado"></span>
                        <span class="texto-2 text-xs">fx</span>
                        <input class="campo" data-barra spellcheck="false" autocomplete="off"
                               placeholder="Valor ou fórmula (ex.: =SOMA(B1:B5) ou =A1*B1)" aria-label="Conteúdo da célula ativa"
                               :value="tbRaw(b)" @focus="tbUI(b).sujo = false" @input="tbBarra(b, $event.target.value)"
                               @keydown.enter.prevent="tbFocarAtivo($event, b)">
                    </div>

                    {{-- Grade --}}
                    <div class="wf-xl-rolagem" @copy="tbCopiar($event, b, false)" @cut="tbCopiar($event, b, true)" @paste="tbColar($event, b)">
                        <table class="wf-tab wf-xl-tab" :style="tbLarg(b)">
                            <colgroup>
                                <col style="width:44px">
                                <template x-for="(c, j) in b.dados.cab" :key="j"><col :style="'width:' + tbColW(b, j) + 'px'"></template>
                                <col style="width:32px">
                            </colgroup>
                            <thead>
                                <tr class="letras">
                                    <th class="canto" @click="tbSelTudo(b)" title="Selecionar tudo"></th>
                                    <template x-for="(c, j) in b.dados.cab" :key="j">
                                        <th class="letra" :class="{ 'sel': tbColAtiva(b, j) }" @click="tbSelCol(b, j, $event)" title="Selecionar coluna (Shift para ampliar)">
                                            <span x-text="tbLetra(j)"></span>
                                            <i class="wf-xl-grip" title="Arraste para mudar a largura (duplo clique restaura)"
                                               @pointerdown.stop.prevent="tbRedim($event, b, j)" @click.stop @dblclick.stop="tbAutoLarg(b, j)"></i>
                                        </th>
                                    </template>
                                    <th style="border:0"></th>
                                </tr>
                                <tr class="nomes">
                                    <th class="canto"></th>
                                    <template x-for="(c, j) in b.dados.cab" :key="j">
                                        <th><div class="flex items-center">
                                            <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                            <button type="button" class="mini-btn perigo" x-show="b.dados.cab.length > 1" title="Remover coluna" @click="tbColRem(b, j)">✕</button>
                                        </div></th>
                                    </template>
                                    <th style="border:0"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="i in tbVisiveis(b)" :key="i">
                                    <tr>
                                        <th class="num" :class="{ 'sel': tbLinhaAtiva(b, i) }" @click="tbSelLinha(b, i, $event)" x-text="i + 1" title="Selecionar linha (Shift para ampliar)"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <td :class="{ 'sel': tbSelecionada(b, i, j), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                <input class="wf-cel" spellcheck="false" autocomplete="off"
                                                       :data-r="i" :data-c="j" :style="tbInEstilo(b, i, j)" :aria-label="'Célula ' + tbLetra(j) + (i + 1)"
                                                       :value="tbValor(b, i, j)"
                                                       @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)" @input="tbInput(b, i, j, $event)"
                                                       @keydown="tbTecla($event, b, i, j)" @mousedown="tbDown($event, b, i, j)">
                                            </td>
                                        </template>
                                        <td style="border:0;text-align:center"><button type="button" class="mini-btn perigo" title="Remover linha" @click="tbLinhaRem(b, i)">✕</button></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot x-show="tbTemRodape(b)">
                                <tr>
                                    <th class="num" title="Totais (consideram as linhas visíveis)">Σ</th>
                                    <template x-for="(c, j) in b.dados.cab" :key="j"><td x-text="tbRodape(b, j)"></td></template>
                                    <td style="border:0"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <button type="button" class="link-prim text-sm" @click="tbLinhaAdd(b)">+ Linha</button>
                        <button type="button" class="link-prim text-sm" @click="tbColAdd(b)">+ Coluna</button>
                        <div class="wf-xl-status" aria-live="polite">
                            <span x-text="b.dados.linhas.length + ' linha(s) × ' + b.dados.cab.length + ' coluna(s)' + (tbUI(b).filtro ? ' · ' + tbVisiveis(b).length + ' visível(is)' : '')"></span>
                            <span x-show="tbStats(b)" x-text="tbStats(b)"></span>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Lista --}}
            <template x-if="b.tipo === 'lista'">
                <div class="h-full flex flex-col gap-2" x-data="{ novo: '' }">
                    <div class="flex gap-2">
                        <input class="campo" x-model="novo" placeholder="Novo item" aria-label="Novo item"
                               @keydown.enter.prevent="if (novo.trim()) { b.dados.itens.push({ id: uid(), t: novo.trim(), f: false }); novo = '' }">
                        <button type="button" class="btn-sec shrink-0"
                                @click="if (novo.trim()) { b.dados.itens.push({ id: uid(), t: novo.trim(), f: false }); novo = '' }">Adicionar</button>
                    </div>
                    <p class="text-sm texto-2" x-show="!b.dados.itens.length">Nenhum item ainda.</p>
                    <ul class="space-y-1.5 overflow-auto">
                        <template x-for="it in b.dados.itens" :key="it.id">
                            <li class="flex items-center gap-2">
                                <input type="checkbox" class="w-4 h-4" x-model="it.f">
                                <span class="flex-1 text-sm" :class="{ 'line-through texto-2': it.f }" x-text="it.t"></span>
                                <button type="button" class="mini-btn perigo" title="Remover item" @click="remItem(b, it.id)">✕</button>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            {{-- Imagem: só o cabeçalho e a imagem inteira. O card se ajusta ao tamanho dela (ver quadro.js). --}}
            <template x-if="b.tipo === 'imagem'">
                <div class="wf-img-bloco" :class="{ 'com-img': imagens[b.id] }">
                    <img x-show="imagens[b.id]" :src="imagens[b.id]" :alt="b.titulo" draggable="false">
                    <label x-show="!imagens[b.id]" class="wf-img-vazio cursor-pointer">
                        <span class="btn-sec">Escolher imagem</span>
                        <input type="file" accept="image/*" class="sr-only"
                               @change="lerImagem(b, $event.target.files[0]); $event.target.value = ''">
                    </label>
                </div>
            </template>

{{-- Cartão ativo (de outra página) --}}
            @include('areas.partials.bloco-vivo')

            {{-- Mapa mental --}}
            <template x-if="b.tipo === 'mapa'">
                <div class="wf-mapa">
                    <div class="relative" :style="'width:' + mmTam(b).w + 'px;height:' + mmTam(b).h + 'px'">
                        <svg class="absolute inset-0 pointer-events-none" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true">
                            <path :d="mmLinhas(b)" fill="none" stroke="var(--prim-linha)" stroke-width="2"/>
                        </svg>
                        <template x-for="n in b.dados.nos" :key="n.id">
                            <div class="wf-no" :style="'left:' + n.x + 'px;top:' + n.y + 'px;--no:' + n.cor">
                                <span class="wf-no-grip" title="Arraste para mover" @pointerdown.prevent="mmMover($event, b, n)">⠿</span>
                                <input class="wf-no-t" x-model="n.t" aria-label="Texto da ideia">
                                <button type="button" class="mini-btn" title="Trocar cor" @click="mmCor(n)"><span class="wf-ponto"></span></button>
                                <button type="button" class="mini-btn" title="Adicionar ideia filha" @click="mmAdd(b, n.id)">+</button>
                                <button type="button" class="mini-btn perigo" x-show="n.pai" title="Remover ideia" @click="mmRem(b, n.id)">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </x-corpo.item>
</template>