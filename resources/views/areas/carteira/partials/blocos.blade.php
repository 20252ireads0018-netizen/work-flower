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

                {{-- Tabela --}}
                <template x-if="b.tipo === 'tabela'">
                    <div class="space-y-2">
                        <div class="overflow-auto">
                            <table class="wf-tab">
                                <thead><tr>
                                    <template x-for="(c, j) in b.dados.cab" :key="j">
                                        <th><div class="flex items-center">
                                            <input class="wf-cel font-semibold" x-model="b.dados.cab[j]">
                                            <button type="button" class="mini-btn perigo" x-show="b.dados.cab.length > 1" title="Remover coluna" @click="tabColRem(b, j)">✕</button>
                                        </div></th>
                                    </template>
                                    <th style="border:0;background:none"></th>
                                </tr></thead>
                                <tbody>
                                    <template x-for="(l, i) in b.dados.linhas" :key="i">
                                        <tr>
                                            <template x-for="(c, j) in l" :key="j"><td><input class="wf-cel" x-model="l[j]"></td></template>
                                            <td style="border:0"><button type="button" class="mini-btn perigo" title="Remover linha" @click="b.dados.linhas.splice(i, 1)">✕</button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" class="link-prim text-sm" @click="tabLinha(b)">+ Linha</button>
                            <button type="button" class="link-prim text-sm" @click="tabCol(b)">+ Coluna</button>
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