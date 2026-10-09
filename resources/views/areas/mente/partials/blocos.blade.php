{{-- Blocos livres de uma aba. Uso: @include('areas.mente.partials.blocos', ['sec' => 'livros']) --}}

{{-- Ajustes de tela cheia (telacheia.js) e estilo da tabela-planilha (tabela.js). Impresso uma única vez, mesmo com vários @include. --}}
@once
<style>
    /* Escala usada pelo texto do bloco em tela cheia */
    .wf-item.wf-fs { --wf-escala: 1.2; }

    /* O bloco ocupa exatamente a altura do cartão; quem rola são os contêineres internos */
    .wf-item.wf-fs .wf-bloco { height: 100%; min-height: 0; }
    .wf-item.wf-fs .wf-cresce { min-height: 0; }

    /* Contêiner de rolagem interno (lista, documento) */
    .wf-esc { flex: 1; min-height: 0; overflow: auto; }

    /* Documento: folha centralizada e legível */
    .wf-item.wf-fs .wf-doc { max-width: 64rem; margin-inline: auto; }

    /* Mapa mental ocupa todo o espaço */
    .wf-item.wf-fs .wf-mapa { min-height: 0; }

    /* Imagem inteira, centralizada e sem cortes */
    .wf-item.wf-fs .wf-img-bloco {
        height: 100%; display: flex; align-items: center; justify-content: center;
    }
    .wf-item.wf-fs .wf-img-bloco img {
        width: auto; height: auto; max-width: 100%; max-height: 100%; object-fit: contain;
    }

    /* ===== Tabela estilo planilha (tabela.js) ===== */
    .wf-xl { display: flex; flex-direction: column; gap: .5rem; min-height: 0; }

    /* Barra de ferramentas */
    .wf-xl-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; flex-shrink: 0; }
    .wf-xl-barra .btn-sec {
        width: auto; padding: .25rem .6rem; font-size: .75rem; line-height: 1.2; white-space: nowrap;
        display: inline-flex; align-items: center; justify-content: center; cursor: pointer;
    }
    .wf-xl-barra .btn-sec:disabled { opacity: .35; cursor: default; }
    .wf-xl-barra select.campo {
        width: auto; max-width: 10rem; padding: .25rem 2rem .25rem .5rem; font-size: .75rem; line-height: 1.2;
    }
    .wf-xl-barra input.campo { padding: .25rem .5rem; font-size: .75rem; }
    .wf-xl-sep { width: 1px; height: 1.2rem; background: var(--linha); margin: 0 .15rem; flex-shrink: 0; }

    /* Barra de fórmulas */
    .wf-xl-fx { display: flex; align-items: center; gap: .4rem; flex-shrink: 0; }
    .wf-xl-fx .campo { flex: 1; min-width: 0; padding: .3rem .6rem; font-size: .8rem; }
    .wf-xl-ref {
        min-width: 3.5rem; text-align: center; font-size: .75rem; font-weight: 700; padding: .35rem .5rem;
        border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); color: var(--tinta);
    }

    /* Grade */
    .wf-xl-rolagem {
        flex: 1; min-height: 9rem; overflow: auto;
        border: 1px solid var(--linha); border-radius: .5rem;
    }
    .wf-xl-tab { table-layout: fixed; border-collapse: separate; border-spacing: 0; width: auto; }
    .wf-xl-tab th, .wf-xl-tab td {
        border: 0; border-right: 1px solid var(--linha); border-bottom: 1px solid var(--linha);
        padding: 0; position: relative;
    }
    .wf-xl-tab thead th { position: sticky; background: var(--superficie-2); z-index: 3; }
    .wf-xl-tab thead tr.letras th { top: 0; height: 22px; }
    .wf-xl-tab thead tr.nomes th { top: 22px; }

    .wf-xl-tab th.letra {
        text-align: center; font-size: .7rem; font-weight: 600; color: var(--tinta-2);
        cursor: pointer; user-select: none;
    }
    .wf-xl-tab th.letra.sel, .wf-xl-tab th.num.sel { background: var(--prim-suave); color: var(--tinta); }
    .wf-xl-tab th.canto { position: sticky; left: 0; z-index: 5; cursor: pointer; }
    .wf-xl-tab th.num {
        position: sticky; left: 0; z-index: 2; background: var(--superficie-2); text-align: center;
        font-size: .7rem; font-weight: 600; color: var(--tinta-2); cursor: pointer; user-select: none;
    }
    .wf-xl-grip {
        position: absolute; top: 0; right: -3px; width: 7px; height: 100%;
        cursor: col-resize; z-index: 4; touch-action: none;
    }
    .wf-xl-grip:hover { background: var(--prim-linha); }

    .wf-xl-tab td .wf-cel { min-width: 0; border-radius: 0; }
    .wf-xl-tab td .wf-cel:focus { box-shadow: none; background: transparent; }
    .wf-xl-tab td.sel {
        background-image: linear-gradient(color-mix(in srgb, var(--prim) 18%, transparent), color-mix(in srgb, var(--prim) 18%, transparent));
    }
    .wf-xl-tab td.ativa { outline: 2px solid var(--prim); outline-offset: -2px; z-index: 1; }
    .wf-xl-tab tfoot th, .wf-xl-tab tfoot td {
        background: var(--superficie-2); font-weight: 600; font-size: .85rem; padding: .35rem .5rem;
        position: sticky; bottom: 0; z-index: 2;
    }
    .wf-xl-tab tfoot th.num { left: 0; z-index: 4; }

    .wf-xl-status { margin-left: auto; display: flex; flex-wrap: wrap; gap: .25rem .9rem; font-size: .7rem; color: var(--tinta-2); }
</style>
@endonce

<template x-for="b in blocosDe('{{ $sec }}')" :key="b.id">
    <x-mente.item sec="'{{ $sec }}'" bid="b.id" :minw="220" :minh="140" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco" placeholder="Sem título">
            <span class="text-xs texto-2 hidden sm:inline" x-text="tipoNome(b.tipo)"></span>
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="wf-bloco">

            {{-- ===== Campo de texto ===== --}}
            <template x-if="b.tipo === 'texto'">
                <div class="wf-cresce flex flex-col gap-2">
                    <textarea class="campo wf-cresce resize-none" x-model="b.dados.texto" placeholder="Escreva aqui…"
                              :style="'font-size:calc(' + ({ p: '.8rem', m: '.95rem', g: '1.25rem' }[b.dados.tam] || '.95rem') + ' * var(--wf-escala, 1))'"></textarea>
                    <div class="flex gap-1.5">
                        <template x-for="t in [['p', 'Pequeno'], ['m', 'Médio'], ['g', 'Grande']]" :key="t[0]">
                            <button type="button" class="chip" :class="{ 'ativo': b.dados.tam === t[0] }" @click="b.dados.tam = t[0]" x-text="t[1]"></button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ===== Tabela (estilo planilha · lógica em public/js/tabela.js) ===== --}}
            <template x-if="b.tipo === 'tabela'">
                <div class="wf-xl wf-cresce" data-xl x-init="tbGarantir(b)" @keydown="tbTeclaRaiz($event, b)">

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
                                            <td :class="{ 'sel': tbSelecionada(b, i, j) && !tbUnico(b), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                <input class="wf-cel" spellcheck="false" autocomplete="off"
                                                       :data-r="i" :data-c="j" :style="tbInEstilo(b, i, j)" :aria-label="'Célula ' + tbLetra(j) + (i + 1)"
                                                       :value="tbValor(b, i, j)"
                                                       @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)" @input="tbInput(b, i, j, $event)"
                                                       @keydown="tbTecla($event, b, i, j)" @mousedown="tbDown($event, b, i, j)">
                                            </td>
                                        </template>
                                        <td style="border:0;text-align:center"><button type="button" class="mini-btn perigo" x-show="b.dados.linhas.length > 1" title="Remover linha" @click="tbLinhaRem(b, i)">✕</button></td>
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
                        <p x-show="!tbVisiveis(b).length" class="text-sm texto-2 p-3">Nenhuma linha encontrada para esse filtro.</p>
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

            {{-- ===== Lista de itens ===== --}}
            <template x-if="b.tipo === 'lista'">
                <div class="wf-cresce flex flex-col gap-2">
                    <input class="campo shrink-0" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $event.target)">
                    <p x-show="!b.dados.itens.length" class="text-sm texto-2">Lista vazia.</p>
                    <div class="wf-esc">
                        <ul class="space-y-1.5">
                            <template x-for="it in b.dados.itens" :key="it.id">
                                <li class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" x-model="it.f">
                                    <input class="wf-cel flex-1" :class="{ 'line-through texto-2': it.f }" x-model="it.t" aria-label="Item">
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remItem(b, it.id)">✕</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </template>

            {{-- ===== Imagem: só o cabeçalho e a imagem inteira. O card se ajusta ao tamanho dela (ver quadro.js). ===== --}}
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

            {{-- ===== Documento (currículo, contrato, resumo, lista…) ===== --}}
            <template x-if="b.tipo === 'documento'">
                <div class="wf-cresce flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <select class="campo !w-auto !py-1.5 text-sm" aria-label="Modelo"
                                @change="trocarModelo(b, $event.target.value); $event.target.value = b.dados.modelo">
                            <template x-for="m in modelos" :key="m.id">
                                <option :value="m.id" :selected="b.dados.modelo === m.id" x-text="m.nome"></option>
                            </template>
                        </select>
                        <select class="campo !w-auto !py-1.5 text-sm" aria-label="Layout" @change="b.dados.layout = $event.target.value">
                            <option value="coluna" :selected="b.dados.layout === 'coluna'">Uma coluna</option>
                            <option value="duas" :selected="b.dados.layout === 'duas'">Duas colunas</option>
                            <option value="faixa" :selected="b.dados.layout === 'faixa'">Faixa no topo</option>
                        </select>
                        <select class="campo !w-auto !py-1.5 text-sm" aria-label="Fonte" @change="b.dados.fonte = $event.target.value">
                            <option value="sans" :selected="b.dados.fonte === 'sans'">Moderna</option>
                            <option value="serif" :selected="b.dados.fonte === 'serif'">Clássica</option>
                            <option value="mono" :selected="b.dados.fonte === 'mono'">Mono</option>
                        </select>
                        <input type="color" class="wf-cor !w-6 !h-6" x-model="b.dados.cor" title="Cor do documento" aria-label="Cor do documento">
                        <button type="button" class="btn-sec !py-1.5 ml-auto" @click="imprimirDoc(b)">Imprimir / PDF</button>
                    </div>

                    {{-- A folha fica sem altura fixa dentro do contêiner de rolagem (as colunas não quebram na horizontal) --}}
                    <div class="wf-esc">
                        <div class="wf-doc" :class="'l-' + b.dados.layout" :style="'--dc:' + b.dados.cor + ';font-family:' + fontes[b.dados.fonte]">
                            <template x-for="(s, i) in b.dados.secoes" :key="s.id">
                                <section class="wf-doc-sec" :class="{ 'cab': b.dados.layout === 'faixa' && i === 0 }">
                                    <div class="flex items-center gap-1">
                                        <input class="wf-doc-t flex-1" x-model="s.t" placeholder="Título da seção" aria-label="Título da seção">
                                        <button type="button" class="mini-btn perigo" title="Remover seção" x-show="b.dados.secoes.length > 1"
                                                @click="b.dados.secoes.splice(i, 1)">✕</button>
                                    </div>
                                    <textarea class="wf-doc-x" rows="3" x-model="s.texto" placeholder="Escreva…"></textarea>
                                </section>
                            </template>
                        </div>
                    </div>
                    <button type="button" class="link-prim text-sm self-start shrink-0" @click="b.dados.secoes.push({ id: uid(), t: 'Nova seção', texto: '' })">+ Seção</button>
                </div>
            </template>

            {{-- Cartão ativo (de outra página) --}}
            @include('areas.partials.bloco-vivo')

            {{-- ===== Mapa mental ===== --}}
            <template x-if="b.tipo === 'mapa'">
                <div class="wf-mapa">
                    <div class="relative" :style="'width:' + mmTam(b).w + 'px;height:' + mmTam(b).h + 'px'">
                        <svg class="absolute inset-0 pointer-events-none" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true">
                            <path :d="mmLinhas(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.5" stroke-linecap="round" opacity=".6"/>
                        </svg>
                        <template x-for="n in b.dados.nos" :key="n.id">
                            <div class="wf-no" :style="'left:' + n.x + 'px;top:' + n.y + 'px;--no:' + n.cor">
                                <span class="wf-no-grip" title="Arrastar" @pointerdown.stop.prevent="mmMover($event, b, n)">⠿</span>
                                <button type="button" class="wf-ponto shrink-0" title="Trocar cor" @click="mmCor(n)"></button>
                                <input class="wf-no-t" x-model="n.t" aria-label="Ideia">
                                <button type="button" class="mini-btn" title="Nova ideia ligada" @click="mmAdd(b, n.id)">+</button>
                                <button type="button" class="mini-btn perigo" title="Remover ideia" x-show="n.pai" @click="mmRem(b, n.id)">✕</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

        </div>
    </x-mente.item>
</template>