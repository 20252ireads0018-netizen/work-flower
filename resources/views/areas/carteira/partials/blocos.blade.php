<template x-for="b in blocosDe('{{ $sec }}')" :key="b.id">
    <x-carteira.item sec="'{{ $sec }}'" bid="b.id" :minw="220" :minh="140" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco">
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="flex flex-col h-full gap-3">
            <div class="flex-1 min-h-0">

                {{-- Texto --}}
                <template x-if="b.tipo === 'texto'">
                    <div class="h-full flex flex-col gap-2">
                        <select class="campo wf-link-sel" x-model="b.dados.tam" aria-label="Tamanho da letra">
                            <option value="p">Letra pequena</option>
                            <option value="m">Letra média</option>
                            <option value="g">Letra grande</option>
                        </select>
                        <textarea class="campo flex-1" placeholder="Escreva aqui…" x-model="b.dados.texto"
                                  :style="{ fontSize: { p: '.8rem', m: '.95rem', g: '1.2rem' }[b.dados.tam] }"></textarea>
                    </div>
                </template>

                {{-- Tabela (estilo planilha: métodos tb* de js/tabela.js) --}}
                <template x-if="b.tipo === 'tabela'">
                    <div class="tb-wrap" data-xl x-init="tbGarantir(b)"
                         @keydown="tbTeclaRaiz($event, b)"
                         @copy="tbCopiar($event, b, false)"
                         @cut="tbCopiar($event, b, true)"
                         @paste="tbColar($event, b)">

                        {{-- Ferramentas --}}
                        <div class="tb-barra">
                            <button type="button" class="tb-btn" title="Desfazer (Ctrl+Z)" aria-label="Desfazer" @click="tbDesfazer(b)" :disabled="!tbUI(b).hist.length">↶</button>
                            <button type="button" class="tb-btn" title="Refazer (Ctrl+Y)" aria-label="Refazer" @click="tbRefazer(b)" :disabled="!tbUI(b).refaz.length">↷</button>
                            <span class="tb-sep"></span>
                            <button type="button" class="tb-btn font-bold" title="Negrito (Ctrl+B)" aria-label="Negrito" @click="tbNegrito(b)">N</button>
                            <label class="tb-btn" style="position:relative;cursor:pointer" title="Cor de fundo">Fundo
                                <input type="color" value="#fde68a" style="position:absolute;opacity:0;width:0;height:0;left:0;bottom:0" aria-label="Cor de fundo" @change="tbFundo(b, $event.target.value)">
                            </label>
                            <button type="button" class="tb-btn" title="Sem cor de fundo" aria-label="Sem cor de fundo" @click="tbFundo(b, '')">∅</button>
                            <span class="tb-sep"></span>
                            <button type="button" class="tb-btn" title="Alinhar à esquerda" aria-label="Alinhar à esquerda" @click="tbAlinhar(b, 'e')">⇤</button>
                            <button type="button" class="tb-btn" title="Centralizar" aria-label="Centralizar" @click="tbAlinhar(b, 'c')">↔</button>
                            <button type="button" class="tb-btn" title="Alinhar à direita" aria-label="Alinhar à direita" @click="tbAlinhar(b, 'd')">⇥</button>
                            <button type="button" class="tb-btn" title="Alinhamento automático" aria-label="Alinhamento automático" @click="tbAlinhar(b, '')">A</button>
                            <span class="tb-sep"></span>
                            <select class="campo wf-link-sel" aria-label="Formato da coluna" @change="tbFmt(b, $event.target.value)">
                                <template x-for="f in tbFormatos" :key="f.id"><option :value="f.id" :selected="tbCol(b, tbUI(b).c).fmt === f.id" x-text="f.nome"></option></template>
                            </select>
                            <select class="campo wf-link-sel" aria-label="Rodapé da coluna" @change="tbRodapeSet(b, $event.target.value)">
                                <template x-for="a in tbAgregados" :key="a.id"><option :value="a.id" :selected="(b.dados.rodape || [])[tbUI(b).c] === a.id" x-text="a.nome"></option></template>
                            </select>
                            <span class="tb-sep"></span>
                            <button type="button" class="tb-btn" title="Ordenar A→Z pela coluna ativa" aria-label="Ordenar crescente" @click="tbOrdenar(b, 1)">A↓</button>
                            <button type="button" class="tb-btn" title="Ordenar Z→A pela coluna ativa" aria-label="Ordenar decrescente" @click="tbOrdenar(b, -1)">Z↓</button>
                            <button type="button" class="tb-btn" title="Soma automática" aria-label="Soma automática" @click="tbAutoSoma(b)">Σ</button>
                            <button type="button" class="tb-btn" title="Preencher para baixo (Ctrl+D)" aria-label="Preencher para baixo" @click="tbPreencher(b, 'baixo')">⤓</button>
                            <button type="button" class="tb-btn" title="Preencher para a direita (Ctrl+R)" aria-label="Preencher para a direita" @click="tbPreencher(b, 'direita')">⇥</button>
                        </div>
                        <div class="tb-barra">
                            <button type="button" class="tb-btn" title="Inserir linha acima" @click="tbLinhaIns(b, tbUI(b).r)">Linha ↑</button>
                            <button type="button" class="tb-btn" title="Inserir linha abaixo" @click="tbLinhaIns(b, tbUI(b).r + 1)">Linha ↓</button>
                            <button type="button" class="tb-btn perigo" title="Excluir linha ativa" @click="tbLinhaRem(b, tbUI(b).r)" :disabled="b.dados.linhas.length <= 1">Linha ✕</button>
                            <button type="button" class="tb-btn" title="Inserir coluna à esquerda" @click="tbColIns(b, tbUI(b).c)">Col ←</button>
                            <button type="button" class="tb-btn" title="Inserir coluna à direita" @click="tbColIns(b, tbUI(b).c + 1)">Col →</button>
                            <button type="button" class="tb-btn perigo" title="Excluir coluna ativa" @click="tbColRem(b, tbUI(b).c)" :disabled="b.dados.cab.length <= 1">Col ✕</button>
                            <span class="tb-sep"></span>
                            <label class="tb-btn" style="cursor:pointer" title="Importar CSV">Importar CSV
                                <input type="file" accept=".csv,.tsv,text/csv,text/plain" class="hidden"
                                       @change="tbImportar(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="tb-btn" title="Exportar CSV" @click="tbExportar(b)">Exportar CSV</button>
                            <input class="campo wf-link-sel" style="width:9rem" x-model="tbUI(b).filtro" placeholder="Filtrar linhas…" aria-label="Filtrar linhas">
                        </div>

                        {{-- Barra de fórmula --}}
                        <div class="tb-formula">
                            <span class="tb-ref" x-text="tbRef(b)"></span>
                            <span class="texto-2 text-xs">fx</span>
                            <input class="campo flex-1" data-barra :value="tbRaw(b)" placeholder="Valor ou fórmula (ex.: =SOMA(B1:B5))"
                                   aria-label="Barra de fórmula"
                                   @input="tbBarra(b, $event.target.value)"
                                   @keydown.enter.prevent="tbFocarAtivo($event, b)">
                        </div>

                        {{-- Grade --}}
                        <div class="tb-scroll">
                            <table class="tb" :style="tbLarg(b)">
                                <thead>
                                    <tr>
                                        <th class="tb-num tb-canto" style="width:44px" title="Selecionar tudo" @click="tbSelTudo(b)"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th :class="{ 'sel': tbColAtiva(b, j) }" :style="`width:${tbColW(b, j)}px`">
                                                <div class="tb-cab">
                                                    <span class="tb-letra" title="Selecionar coluna" @click="tbSelCol(b, j, $event)" x-text="tbLetra(j)"></span>
                                                    <input class="tb-cab-in" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                                    <span class="tb-res" title="Arraste para redimensionar (duplo clique: padrão)"
                                                          @pointerdown.stop.prevent="tbRedim($event, b, j)" @dblclick="tbAutoLarg(b, j)"></span>
                                                </div>
                                            </th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="i in tbVisiveis(b)" :key="i">
                                        <tr>
                                            <th class="tb-num" :class="{ 'sel': tbLinhaAtiva(b, i) }" title="Selecionar linha" @click="tbSelLinha(b, i, $event)" x-text="i + 1"></th>
                                            <template x-for="(c, j) in b.dados.cab" :key="j">
                                                <td :class="{ 'sel': tbSelecionada(b, i, j), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                    <input class="tb-cel" :data-r="i" :data-c="j"
                                                           :value="tbValor(b, i, j)" :style="tbInEstilo(b, i, j)"
                                                           :aria-label="tbLetra(j) + (i + 1)"
                                                           @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)"
                                                           @mousedown="tbDown($event, b, i, j)"
                                                           @input="tbInput(b, i, j, $event)"
                                                           @keydown="tbTecla($event, b, i, j)">
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot x-show="tbTemRodape(b)">
                                    <tr>
                                        <th class="tb-num"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <td class="tb-rod" x-text="tbRodape(b, j)"></td>
                                        </template>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex gap-3">
                                <button type="button" class="link-prim text-sm" @click="tbLinhaAdd(b)">+ Linha</button>
                                <button type="button" class="link-prim text-sm" @click="tbColAdd(b)">+ Coluna</button>
                            </div>
                            <p class="text-xs texto-2" x-text="tbStats(b)" aria-live="polite"></p>
                        </div>
                    </div>
                </template>

                {{-- Lista --}}
                <template x-if="b.tipo === 'lista'">
                    <div class="space-y-2">
                        <input class="campo" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $el)">
                        <ul class="space-y-1.5">
                            <template x-for="i in b.dados.itens" :key="i.id">
                                <li class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" x-model="i.f">
                                    <input class="wf-cel flex-1" :class="{ 'line-through texto-2': i.f }" x-model="i.t">
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remItem(b, i.id)">✕</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Imagem --}}
                <template x-if="b.tipo === 'imagem'">
                    <div data-img class="w-full">
                        <template x-if="imagens[b.id]">
                            <img :src="imagens[b.id]" :alt="b.titulo"
                                class="block w-full h-auto rounded-lg cursor-pointer"
                                title="Duplo clique para trocar a imagem"
                                @load="ajustarImagem(b, $el)"
                                @dblclick="$el.closest('[data-img]').querySelector('input[type=file]').click()">
                        </template>
                        <button type="button" class="btn-sec" x-show="!imagens[b.id]"
                                @click="$el.closest('[data-img]').querySelector('input[type=file]').click()">Escolher imagem</button>
                        <input type="file" accept="image/*" class="hidden"
                            @change="lerImagem(b, $event.target.files[0]); $event.target.value = ''">
                    </div>
                </template>

                {{-- Cartão ativo (de outra página) --}}
                @include('areas.partials.bloco-vivo')

                {{-- Mapa mental --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="wf-mapa">
                        <div class="relative" :style="{ width: mmTam(b).w + 'px', height: mmTam(b).h + 'px' }">
                            <svg class="absolute inset-0 pointer-events-none" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true">
                                <path :d="mmLinhas(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                            <template x-for="n in b.dados.nos" :key="n.id">
                                <div class="wf-no" :style="`left:${n.x}px;top:${n.y}px;--no:${n.cor}`">
                                    <span class="wf-no-grip" title="Mover" @pointerdown.stop.prevent="mmMover($event, b, n)">⠿</span>
                                    <button type="button" class="wf-ponto" title="Trocar cor" @click="mmCor(n)"></button>
                                    <input class="wf-no-t" x-model="n.t" aria-label="Texto do nó">
                                    <button type="button" class="mini-btn" title="Novo nó filho" @click="mmAdd(b, n.id)">+</button>
                                    <button type="button" class="mini-btn perigo" x-show="n.pai" title="Remover nó" @click="mmRem(b, n.id)">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </x-carteira.item>
</template>