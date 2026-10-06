{{-- Blocos livres de uma aba. Uso: @include('areas.mente.partials.blocos', ['sec' => 'livros']) --}}
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
                              :style="'font-size:' + ({ p: '.8rem', m: '.95rem', g: '1.25rem' }[b.dados.tam] || '.95rem')"></textarea>
                    <div class="flex gap-1.5">
                        <template x-for="t in [['p', 'Pequeno'], ['m', 'Médio'], ['g', 'Grande']]" :key="t[0]">
                            <button type="button" class="chip" :class="{ 'ativo': b.dados.tam === t[0] }" @click="b.dados.tam = t[0]" x-text="t[1]"></button>
                        </template>
                    </div>
                </div>
            </template>

            {{-- ===== Tabela ===== --}}
            <template x-if="b.tipo === 'tabela'">
                <div class="wf-cresce space-y-2">
                    <div class="overflow-auto">
                        <table class="wf-tab">
                            <thead>
                                <tr>
                                    <template x-for="(c, j) in b.dados.cab" :key="j">
                                        <th>
                                            <div class="flex items-center">
                                                <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Cabeçalho da coluna">
                                                <button type="button" class="mini-btn perigo shrink-0" title="Remover coluna" @click="tabColRem(b, j)">✕</button>
                                            </div>
                                        </th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(l, i) in b.dados.linhas" :key="i">
                                    <tr>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <td><input class="wf-cel" x-model="b.dados.linhas[i][j]" aria-label="Célula"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex gap-3 text-sm">
                        <button type="button" class="link-prim" @click="tabLinha(b)">+ Linha</button>
                        <button type="button" class="link-prim" @click="tabCol(b)">+ Coluna</button>
                        <button type="button" class="texto-2 hover:underline ml-auto" x-show="b.dados.linhas.length > 1" @click="b.dados.linhas.pop()">− Linha</button>
                    </div>
                </div>
            </template>

            {{-- ===== Lista de itens ===== --}}
            <template x-if="b.tipo === 'lista'">
                <div class="wf-cresce space-y-2">
                    <input class="campo" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $event.target)">
                    <p x-show="!b.dados.itens.length" class="text-sm texto-2">Lista vazia.</p>
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
                <div class="wf-cresce space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
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
                    <button type="button" class="link-prim text-sm" @click="b.dados.secoes.push({ id: uid(), t: 'Nova seção', texto: '' })">+ Seção</button>
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