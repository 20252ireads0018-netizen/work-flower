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

            {{-- Tabela --}}
            <template x-if="b.tipo === 'tabela'">
                <div class="h-full overflow-auto">
                    <table class="wf-tab">
                        <thead>
                            <tr>
                                <template x-for="(c, j) in b.dados.cab" :key="j">
                                    <th>
                                        <div class="flex items-center gap-1">
                                            <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                            <button type="button" class="mini-btn perigo shrink-0" x-show="b.dados.cab.length > 1"
                                                    title="Remover coluna" @click="tabColRem(b, j)">✕</button>
                                        </div>
                                    </th>
                                </template>
                                <th class="w-8"><button type="button" class="mini-btn" title="Nova coluna" @click="tabCol(b)">+</button></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(l, i) in b.dados.linhas" :key="i">
                                <tr>
                                    <template x-for="(c, j) in l" :key="j">
                                        <td><input class="wf-cel" x-model="l[j]" aria-label="Célula"></td>
                                    </template>
                                    <td><button type="button" class="mini-btn perigo" title="Remover linha"
                                                @click="b.dados.linhas.splice(i, 1)">✕</button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <button type="button" class="link-prim text-sm mt-2" @click="tabLinha(b)">+ Linha</button>
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