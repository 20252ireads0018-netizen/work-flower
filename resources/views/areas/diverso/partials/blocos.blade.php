<template x-for="b in blocosDe(aba)" :key="b.id">
    <x-diverso.item sec="b.secao" bid="b.id" :minw="220" :minh="140" :fixo="false">
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


                {{-- Escrita com imagem e texto contornando --}}
                <template x-if="b.tipo === 'escrita'">
                    <div class="h-full flex flex-col gap-2">
                        <div class="flex flex-wrap gap-2 items-center">
                            <label class="btn-sec cursor-pointer"><span x-text="imagens[b.id] ? 'Trocar imagem' : '+ Imagem'"></span>
                                <input type="file" accept="image/*" class="hidden" @change="lerImagem(b, $event.target.files[0]); $event.target.value = ''"></label>
                            <select class="campo wf-link-sel" x-model="b.dados.lado" aria-label="Lado da imagem"><option value="left">Imagem à esquerda</option><option value="right">Imagem à direita</option></select>
                            <input type="range" min="15" max="70" x-model.number="b.dados.larg" aria-label="Largura da imagem">
                            <select class="campo wf-link-sel" x-model="b.dados.fonte" aria-label="Fonte"><option value="sans">Sem serifa</option><option value="serif">Com serifa</option><option value="mono">Mono</option></select>
                        </div>
                        <div class="flex-1 min-h-0 overflow-auto" :style="{ fontFamily: { sans: 'inherit', serif: 'Georgia,serif', mono: 'ui-monospace,monospace' }[b.dados.fonte] }">
                            <img x-show="imagens[b.id]" :src="imagens[b.id]" alt="" class="rounded-lg"
                                 :style="`float:${b.dados.lado};width:${b.dados.larg}%;shape-margin:12px;margin:0 ${b.dados.lado === 'left' ? '14px' : '0'} 8px ${b.dados.lado === 'right' ? '14px' : '0'}`">
                            <div contenteditable="plaintext-only" style="white-space:pre-wrap;outline:none;min-height:100%;line-height:1.65" data-ph="Escreva aqui…"
                                 x-init="$el.textContent = b.dados.texto" @input="b.dados.texto = $el.textContent"></div>
                        </div>
                    </div>
                </template>

                {{-- Redes sociais: curtidas, views, engajamento --}}
                <template x-if="b.tipo === 'social'">
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="wf-stat"><p class="text-xs texto-2">Curtidas</p><p class="titulo text-lg" x-text="somaSocial(b, 'likes')"></p></div>
                            <div class="wf-stat"><p class="text-xs texto-2">Views</p><p class="titulo text-lg" x-text="somaSocial(b, 'views')"></p></div>
                            <div class="wf-stat"><p class="text-xs texto-2">Engajamento</p><p class="titulo text-lg" x-text="engSocial(b)"></p></div>
                        </div>
                        <div class="overflow-auto"><table class="wf-tabela">
                            <thead><tr><th>Post</th><th>Rede</th><th>Likes</th><th>Views</th><th>Coment.</th><th>Compart.</th><th></th></tr></thead>
                            <tbody><template x-for="p in b.dados.posts" :key="p.id"><tr>
                                <td><input class="wf-cel" x-model="p.nome"></td>
                                <td><input class="wf-cel" list="wf-redes" x-model="p.rede"></td>
                                <td><input class="campo wf-num" inputmode="numeric" x-model="p.likes"></td>
                                <td><input class="campo wf-num" inputmode="numeric" x-model="p.views"></td>
                                <td><input class="campo wf-num" inputmode="numeric" x-model="p.coment"></td>
                                <td><input class="campo wf-num" inputmode="numeric" x-model="p.comp"></td>
                                <td><button type="button" class="mini-btn perigo" @click="b.dados.posts.splice(b.dados.posts.indexOf(p), 1)">✕</button></td>
                            </tr></template></tbody></table></div>
                        <button type="button" class="link-prim text-sm" @click="addPost(b)">+ Post</button>
                    </div>
                </template>

                {{-- Loja: vitrine com fotos, preços, estoque e vendas --}}
                <template x-if="b.tipo === 'loja'">
                    <div class="space-y-3">
                        <div class="flex flex-wrap gap-2">
                            <input class="campo flex-1" style="min-width:12rem" placeholder="Link de pagamento (https://… Pix, Stripe, Mercado Pago)" x-model="b.dados.pagamento" aria-label="Link de pagamento">
                            <a class="btn-sec" :href="linkSeguro(b.dados.pagamento)" target="_blank" rel="noopener noreferrer" x-show="linkSeguro(b.dados.pagamento) !== '#'">Abrir pagamento</a>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="wf-stat"><p class="text-xs texto-2">Faturamento</p><p class="titulo text-lg" x-text="brl(faturaLoja(b))"></p></div>
                            <div class="wf-stat"><p class="text-xs texto-2">Vendidos</p><p class="titulo text-lg" x-text="somaLoja(b, 'vendidos')"></p></div>
                            <div class="wf-stat"><p class="text-xs texto-2">Em estoque</p><p class="titulo text-lg" x-text="somaLoja(b, 'estoque')"></p></div>
                        </div>
                        <p x-show="!b.dados.produtos.length" class="text-sm texto-2">Nenhum produto ainda. Adicione o primeiro para montar sua vitrine.</p>
                        <div class="wf-vitrine">
                            <template x-for="p in b.dados.produtos" :key="p.id">
                                <div class="wf-prod">
                                    <label class="wf-prod-img" title="Trocar foto">
                                        <img x-show="imgProduto(b, p)" :src="imgProduto(b, p)" alt="">
                                        <span x-show="!imgProduto(b, p)">+ foto</span>
                                        <input type="file" accept="image/*" class="hidden" @change="lerImgProduto(b, p, $event.target.files[0]); $event.target.value = ''">
                                    </label>
                                    <input class="wf-cel font-medium" x-model="p.nome" aria-label="Nome do produto">
                                    <div class="flex items-center gap-1"><span class="text-xs texto-2">R$</span><input class="campo wf-num" inputmode="decimal" x-model="p.preco" aria-label="Preço"></div>
                                    <div class="flex items-center gap-1"><span class="text-xs texto-2">Estoque</span><input class="campo wf-num" style="width:4.5rem" inputmode="numeric" x-model="p.estoque" aria-label="Estoque"></div>
                                    <div class="flex items-center justify-between gap-1">
                                        <span class="text-xs texto-2">Vendidos: <b x-text="num(p.vendidos)"></b></span>
                                        <span class="flex items-center gap-1">
                                            <button type="button" class="btn-sec !py-1" @click="vender(p)">+ Venda</button>
                                            <button type="button" class="mini-btn perigo" title="Remover produto" @click="remProduto(b, p)">✕</button>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <button type="button" class="link-prim text-sm" @click="addProduto(b)">+ Produto</button>
                    </div>
                </template>

                {{-- Código: GitHub e VS Code --}}
                <template x-if="b.tipo === 'codigo'">
                    <div class="space-y-2">
                        <template x-for="r in b.dados.repos" :key="r.id">
                            <div class="wf-linha flex-wrap">
                                <input class="wf-cel flex-1" x-model="r.nome" placeholder="dono/repositório">
                                <input class="wf-cel flex-1" x-model="r.pasta" placeholder="Pasta local (ex.: C:/projetos/app)">
                                <a class="btn-sec" :href="`https://github.com/${r.nome.replace(/[^A-Za-z0-9._\/-]/g, '')}`" target="_blank" rel="noopener noreferrer">GitHub</a>
                                <a class="btn-sec" :href="`https://vscode.dev/github/${r.nome.replace(/[^A-Za-z0-9._\/-]/g, '')}`" target="_blank" rel="noopener noreferrer">VS Code web</a>
                                <a class="btn-sec" x-show="r.pasta" :href="'vscode://file/' + encodeURI(r.pasta.replace(/\\/g, '/'))">VS Code local</a>
                                <button type="button" class="mini-btn perigo" @click="b.dados.repos.splice(b.dados.repos.indexOf(r), 1)">✕</button>
                            </div>
                        </template>
                        <button type="button" class="link-prim text-sm" @click="b.dados.repos.push({ id: uid(), nome: '', pasta: '' })">+ Repositório</button>
                    </div>
                </template>

                {{-- Lista de compras --}}
                <template x-if="b.tipo === 'compras'">
                    <div class="space-y-2">
                        <input class="campo" placeholder="Adicionar item e apertar Enter" aria-label="Novo item" @keydown.enter.prevent="addCompra(b, $el)">
                        <p x-show="!b.dados.itens.length" class="text-sm texto-2">Sua lista está vazia.</p>
                        <template x-for="i in b.dados.itens" :key="i.id">
                            <div class="wf-linha">
                                <input type="checkbox" x-model="i.f" aria-label="Comprado">
                                <input class="wf-cel flex-1" :class="{ 'line-through opacity-60': i.f }" x-model="i.t" aria-label="Item">
                                <input class="campo wf-num" style="width:3.5rem" inputmode="numeric" x-model="i.q" aria-label="Quantidade">
                                <span class="text-xs texto-2">×</span>
                                <input class="campo wf-num" style="width:5rem" inputmode="decimal" x-model="i.p" placeholder="R$" aria-label="Preço unitário">
                                <button type="button" class="mini-btn perigo" title="Remover" @click="remItem(b, i.id)">✕</button>
                            </div>
                        </template>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="wf-stat"><p class="text-xs texto-2">A comprar (<span x-text="qtdCompra(b, false)"></span>)</p><p class="titulo text-lg" x-text="brl(totCompra(b, false))"></p></div>
                            <div class="wf-stat"><p class="text-xs texto-2">No carrinho (<span x-text="qtdCompra(b, true)"></span>)</p><p class="titulo text-lg" x-text="brl(totCompra(b, true))"></p></div>
                        </div>
                        <button type="button" class="link-prim text-sm" x-show="qtdCompra(b, true) > 0" @click="limparComprados(b)">Limpar comprados</button>
                    </div>
                </template>

                {{-- Documento (currículo, contrato, resumo, lista…) --}}
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

                                {{-- Imagem --}}
                <template x-if="b.tipo === 'imagem'">
                    <div class="h-full flex flex-col gap-2">
                        <template x-if="imagens[b.id]">
                            <img :src="imagens[b.id]" :alt="b.titulo"
                                 class="w-full flex-1 min-h-0 rounded-lg" :style="{ objectFit: b.dados.ajuste }">
                        </template>
                        <div class="flex gap-2 items-center">
                            <label class="btn-sec cursor-pointer">
                                <span x-text="imagens[b.id] ? 'Trocar imagem' : 'Escolher imagem'"></span>
                                <input type="file" accept="image/*" class="hidden" @change="lerImagem(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <select class="campo wf-link-sel" x-model="b.dados.ajuste" aria-label="Ajuste da imagem">
                                <option value="cover">Preencher</option>
                                <option value="contain">Inteira</option>
                            </select>
                        </div>
                    </div>
                </template>

{{-- Cartão ativo (de outra página) --}}
            @include('areas.partials.bloco-vivo')

            {{-- Mapa mental --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="h-full flex flex-col gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && !ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && !ui[b.id]?.borr)" @click="penModo(b, 'lapis')">✏️ Desenhar</button>
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && ui[b.id]?.borr)" @click="penModo(b, 'borracha')">Borracha</button>
                            <template x-if="ui[b.id]?.on">
                                <span class="flex items-center gap-2">
                                    <input type="color" class="wf-cor !w-6 !h-6" x-model="ui[b.id].cor" aria-label="Cor do traço" title="Cor do traço">
                                    <input type="range" min="1" max="16" x-model.number="ui[b.id].larg" aria-label="Espessura do traço" title="Espessura" style="width:6rem">
                                </span>
                            </template>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penDesfazer(b)">Desfazer traço</button>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penLimpar(b)">Limpar desenho</button>
                        </div>
                        <div class="wf-mapa-col">
                            <div class="relative" :style="{ width: mmTam(b).w + 'px', height: mmTam(b).h + 'px' }">
                                <svg class="absolute inset-0 pointer-events-none" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true">
                                    <path :d="mmLinhas(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                                <svg class="absolute inset-0" :width="mmTam(b).w" :height="mmTam(b).h" aria-label="Desenho livre"
                                     :style="{ zIndex: ui[b.id]?.on ? 3 : 1, pointerEvents: ui[b.id]?.on ? 'auto' : 'none', cursor: ui[b.id]?.borr ? 'cell' : 'crosshair', touchAction: ui[b.id]?.on ? 'none' : 'auto' }"
                                     @pointerdown="penDown($event, b)" @pointermove="penHover($event, b)" @pointerleave="penFora(b)">
                                    <g x-html="tracosSvg(b)"></g>
                                    <path :d="ui[b.id]?.vivo || ''" fill="none" :stroke="ui[b.id]?.cor" :stroke-width="ui[b.id]?.larg" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle x-show="ui[b.id]?.on && ui[b.id]?.borr && ui[b.id]?.cx != null" :cx="ui[b.id]?.cx ?? 0" :cy="ui[b.id]?.cy ?? 0" :r="penRaio(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.2" stroke-dasharray="3 3" style="pointer-events:none"/>
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
                    </div>
                </template>

                {{-- Tabletop: mapa com grade e peças arrastáveis --}}
                <template x-if="b.tipo === 'tabletop'">
                    @include('areas.diverso.partials.tabletop')
                </template>
            </div>
        </div>
    </x-diverso.item>
</template>