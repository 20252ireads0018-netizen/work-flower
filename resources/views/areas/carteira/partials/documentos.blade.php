{{-- Documentos: lista, editor (3 abas) e prévia. Incluído em areas/carteira (aba "docs"). --}}
<style>
    .doc-tabs { display: flex; gap: .25rem; padding: .2rem; background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .6rem; position: sticky; top: 0; z-index: 5; margin-bottom: .9rem; }
    .doc-tab { flex: 1; padding: .4rem .5rem; font-size: .8rem; border-radius: .45rem; color: var(--tinta-2); text-align: center; transition: background-color .15s, color .15s; }
    .doc-tab:hover { color: var(--tinta); }
    .doc-tab.ativo { background: var(--prim); color: var(--on-prim); }

    .doc-card { border: 1px solid var(--linha); border-radius: .6rem; background: var(--superficie-2); padding: .6rem .75rem; }
    .doc-card > summary { cursor: pointer; font-weight: 600; font-size: .85rem; list-style: none; display: flex; align-items: center; justify-content: space-between; }
    .doc-card > summary::-webkit-details-marker { display: none; }
    .doc-card > summary::after { content: '▾'; color: var(--tinta-2); transition: transform .15s; }
    .doc-card:not([open]) > summary::after { transform: rotate(-90deg); }
    .doc-card[open] > summary { margin-bottom: .6rem; }

    .doc-sec { border: 1px solid var(--linha); border-left: 3px solid var(--prim); border-radius: .6rem; background: var(--superficie-2); }
    .doc-sec.oculta { opacity: .55; }
    .doc-sec.alvo { outline: 2px dashed var(--prim); outline-offset: 2px; }
    .doc-sec-topo { display: flex; align-items: center; gap: .35rem; padding: .35rem .5rem; }
    .doc-sec-corpo { padding: .65rem .75rem .75rem; border-top: 1px solid var(--linha); display: grid; gap: .65rem; }
    .doc-grip { cursor: grab; color: var(--tinta-2); user-select: none; padding: 0 .15rem; }
    .doc-ico { width: 1.1rem; height: 1.1rem; color: var(--prim-forte); flex-shrink: 0; }

    .doc-tb { display: flex; flex-wrap: wrap; align-items: center; gap: .1rem; padding: .25rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); }
    .doc-tb button { min-width: 1.75rem; height: 1.75rem; padding: 0 .3rem; border-radius: .35rem; font-size: .8rem; color: var(--tinta); display: inline-flex; align-items: center; justify-content: center; }
    .doc-tb button:hover { background: var(--superficie-2); color: var(--prim-forte); }
    .doc-tb .sep { width: 1px; height: 1.1rem; background: var(--linha); margin: 0 .2rem; }
    .doc-ta { min-height: 7rem; resize: vertical; line-height: 1.5; }

    .doc-item { border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem; background: var(--superficie); }
    .doc-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; }
    .doc-ops { display: flex; flex-wrap: wrap; gap: .25rem .9rem; padding-top: .4rem; border-top: 1px dashed var(--linha); }
    .doc-ops button { font-size: .75rem; color: var(--prim-forte); }
    .doc-ops button:disabled { opacity: .35; cursor: default; }
    .doc-ops button.perigo { color: #f87171; }

    .doc-seg { display: inline-flex; gap: .15rem; padding: .15rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); }
    .doc-segbtn { min-width: 1.7rem; height: 1.6rem; padding: 0 .4rem; border-radius: .35rem; font-size: .75rem; color: var(--tinta-2); display: inline-flex; align-items: center; justify-content: center; }
    .doc-segbtn:hover { color: var(--tinta); }
    .doc-segbtn.ativo { background: var(--prim); color: var(--on-prim); }

    .doc-paleta { display: flex; flex-wrap: wrap; align-items: center; gap: .45rem; }
    .doc-cor { width: 1.4rem; height: 1.4rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,.18); cursor: pointer; }
    .doc-cor.ativo { box-shadow: 0 0 0 2px var(--superficie), 0 0 0 4px var(--tinta); }
    .doc-tom { width: 1.4rem; height: 1.4rem; border-radius: .35rem; border: 1px solid rgba(255,255,255,.18); cursor: pointer; }
    .doc-tom.ativo { box-shadow: 0 0 0 2px var(--superficie), 0 0 0 4px var(--tinta); }

    .doc-temas { display: grid; grid-template-columns: repeat(auto-fill, minmax(5.2rem, 1fr)); gap: .5rem; }
    .doc-tema { display: flex; flex-direction: column; align-items: center; gap: .25rem; padding: .45rem .35rem; border: 1px solid var(--linha); border-radius: .55rem; background: var(--superficie-2); color: var(--tinta-2); font-size: .72rem; transition: border-color .15s, color .15s; }
    .doc-tema:hover { border-color: var(--prim-linha); color: var(--tinta); }
    .doc-tema.ativo { border-color: var(--prim); color: var(--prim-forte); }

    .doc-add { display: grid; grid-template-columns: repeat(auto-fill, minmax(6.4rem, 1fr)); gap: .45rem; }
    .doc-addbtn { display: flex; align-items: center; gap: .4rem; padding: .45rem .55rem; border: 1px dashed var(--linha); border-radius: .55rem; font-size: .75rem; color: var(--tinta-2); text-align: left; transition: border-color .15s, color .15s, background-color .15s; }
    .doc-addbtn:hover { border-color: var(--prim); color: var(--prim-forte); background: var(--superficie-2); }
    .doc-addbtn svg { width: 1rem; height: 1rem; flex-shrink: 0; }

    .doc-modelos { display: grid; gap: .4rem; }
    .doc-modelo { display: flex; align-items: center; gap: .6rem; padding: .5rem .6rem; border: 1px solid var(--linha); border-radius: .55rem; background: var(--superficie-2); text-align: left; transition: border-color .15s; }
    .doc-modelo:hover { border-color: var(--prim); }
    .doc-modelo svg { width: 1.3rem; height: 1.3rem; color: var(--prim-forte); flex-shrink: 0; }
    .doc-mini { width: .55rem; height: .55rem; border-radius: 9999px; flex-shrink: 0; }

    .doc-foto { width: 3.5rem; height: 3.5rem; border-radius: .6rem; border: 1px dashed var(--linha); background: var(--superficie); display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--tinta-2); flex-shrink: 0; }
    .doc-foto.rd { border-radius: 9999px; }
    .doc-foto img { width: 100%; height: 100%; object-fit: cover; }
    .doc-range { accent-color: var(--prim); width: 100%; }

    .doc-prev { display: flex; flex-direction: column; height: 100%; min-height: 0; gap: .5rem; }
    .doc-prev-barra { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; font-size: .75rem; color: var(--tinta-2); }
    .doc-prev-area { flex: 1; min-height: 0; overflow: auto; padding: .75rem; border-radius: .6rem; background: var(--superficie-2); border: 1px solid var(--linha); }

    .doc-overlay { position: fixed; inset: 0; z-index: 90; background: rgba(5,7,12,.84); display: flex; padding: 1rem; }
    .doc-overlay-box { margin: auto; width: min(960px, 100%); height: 100%; display: flex; flex-direction: column; gap: .6rem; }
    .doc-overlay-barra { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; color: #e5e7eb; }
    .doc-overlay .doc-prev-area { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.12); }

    @media (max-width: 899px) { .doc-prev { height: 32rem; } }
</style>

{{-- ============ Lista / modelos ============ --}}
<x-carteira.item sec="'docs'" bid="'doc-lista'" titulo="Documentos" :minw="240" :minh="220">
    <div class="space-y-4">
        <button type="button" class="btn w-full" @click="docNovo = !docNovo" :aria-expanded="docNovo">+ Novo documento</button>

        <div x-show="docNovo" x-cloak class="doc-modelos">
            <p class="text-xs texto-2">Escolha um modelo para começar:</p>
            <template x-for="m in modelosDoc" :key="m.chave">
                <button type="button" class="doc-modelo" @click="novoDoc(m.chave)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="m.icone"></svg>
                    <span class="min-w-0"><span class="block text-sm font-medium" x-text="m.nome"></span><span class="block text-xs texto-2" x-text="m.desc"></span></span>
                </button>
            </template>
        </div>

        <p x-show="!estado.docs.length" class="text-sm texto-2">Crie um currículo, contrato, proposta ou documento livre.</p>
        <ul class="space-y-2">
            <template x-for="d in estado.docs" :key="d.id">
                <li class="wf-linha cursor-pointer" :style="estado.docAtivo === d.id ? 'border-color:var(--prim)' : ''" @click="estado.docAtivo = d.id; docTab = 'conteudo'">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="doc-mini" :style="`background:${(d.layout && d.layout.cor) || 'var(--prim)'}`"></span>
                        <div class="min-w-0"><p class="font-medium truncate" x-text="d.titulo"></p><p class="text-xs texto-2" x-text="tipoDoc(d.tipo)"></p></div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" class="mini-btn" title="Duplicar" @click.stop="duplicarDoc(d)">⧉</button>
                        <button type="button" class="mini-btn perigo" title="Excluir" @click.stop="remDoc(d)">✕</button>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</x-carteira.item>

{{-- ============ Editor ============ --}}
<x-carteira.item sec="'docs'" bid="'doc-editor'" titulo="Editor" :minw="360" :minh="360">
    <p x-show="!docAtual()" class="text-sm texto-2">Selecione ou crie um documento.</p>
    <template x-if="docAtual()">
        <div x-data="{ get d() { return docAtual() } }">

            <div class="doc-tabs" role="tablist">
                <template x-for="t in docAbas" :key="t.id">
                    <button type="button" role="tab" class="doc-tab" :class="{ 'ativo': docTab === t.id }" :aria-selected="docTab === t.id" @click="docTab = t.id" x-text="t.nome"></button>
                </template>
            </div>

            {{-- ============ ABA: CONTEÚDO ============ --}}
            <div x-show="docTab === 'conteudo'" class="space-y-4">
                <div><label class="rotulo">Título do documento</label><input class="campo" x-model="d.titulo"></div>

                <details class="doc-card" open>
                    <summary>Cabeçalho</summary>
                    <div class="space-y-3">
                        <label class="text-xs flex items-center gap-2"><input type="checkbox" x-model="d.layout.cabecalho"> Mostrar cabeçalho no documento</label>

                        <div class="flex items-center gap-3">
                            <div class="doc-foto" :class="{ 'rd': d.layout.fotoForma === 'redonda' && d.tipo === 'curriculo' }">
                                <template x-if="d.campos.foto"><img :src="d.campos.foto" alt="Foto ou logo"></template>
                                <span x-show="!d.campos.foto" class="text-lg">+</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <label class="btn-sec cursor-pointer"><span x-text="d.campos.foto ? 'Trocar' : (d.tipo === 'curriculo' ? 'Adicionar foto' : 'Adicionar logo')"></span>
                                    <input type="file" accept="image/*" class="hidden"
                                           @change="docImagem(d.campos, 'foto', $event.target.files[0], { quadrada: d.tipo === 'curriculo', max: d.tipo === 'curriculo' ? 360 : 500 }); $event.target.value = ''">
                                </label>
                                <button type="button" class="btn-sec" x-show="d.campos.foto" @click="d.campos.foto = ''">Remover</button>
                            </div>
                        </div>

                        <template x-if="d.tipo === 'curriculo'">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="col-span-2"><label class="rotulo">Nome</label><input class="campo" x-model="d.campos.nome" placeholder="Seu nome"></div>
                                <div class="col-span-2"><label class="rotulo">Cargo / objetivo</label><input class="campo" x-model="d.campos.cargo"></div>
                                <div><label class="rotulo">E-mail</label><input class="campo" x-model="d.campos.email"></div>
                                <div><label class="rotulo">Telefone</label><input class="campo" x-model="d.campos.tel"></div>
                                <div><label class="rotulo">Cidade</label><input class="campo" x-model="d.campos.cidade"></div>
                                <div><label class="rotulo">Site / LinkedIn</label><input class="campo" x-model="d.campos.site"></div>
                            </div>
                        </template>
                        <template x-if="d.tipo !== 'curriculo'">
                            <div class="grid grid-cols-1 gap-2">
                                <div><label class="rotulo">Subtítulo</label><input class="campo" x-model="d.campos.subtitulo" placeholder="Opcional"></div>
                                <div><label class="rotulo">Local, data ou referência</label><input class="campo" x-model="d.campos.data" placeholder="Ex.: São Paulo, 12 de março de 2026"></div>
                            </div>
                        </template>
                    </div>
                </details>

                <div class="flex items-center justify-between gap-2">
                    <h3 class="titulo text-sm" x-text="'Elementos (' + d.secoes.length + ')'"></h3>
                    <button type="button" class="link-prim text-xs" x-show="d.secoes.length > 1" @click="docAlternarTodas(d)">Recolher / expandir</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(s, i) in d.secoes" :key="s.id">
                        <div class="doc-sec" :data-sec="s.id" :class="{ 'oculta': s.oculta, 'alvo': dragSobre === i && dragSec !== null && dragSec !== i }"
                             @dragover.prevent="dragSobre = i" @dragleave="dragSobre === i && (dragSobre = null)" @drop.prevent="soltarSecao(d, i)">

                            <div class="doc-sec-topo">
                                <span class="doc-grip" draggable="true" title="Arraste para reordenar" @dragstart="iniciarArrasto($event, i)" @dragend="dragSec = null; dragSobre = null">⠿</span>
                                <svg class="doc-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" :title="docNomeElemento(s.tipo)" aria-hidden="true" x-html="docIconeElemento(s.tipo)"></svg>
                                <input class="wf-titulo flex-1" x-show="docTemTitulo(s.tipo)" x-model="s.titulo" aria-label="Título do elemento" placeholder="Título">
                                <span class="flex-1 text-xs texto-2 truncate" x-show="!docTemTitulo(s.tipo)" x-text="docNomeElemento(s.tipo)"></span>
                                <button type="button" class="mini-btn" :title="docFechada[s.id] ? 'Expandir' : 'Recolher'" :aria-expanded="!docFechada[s.id]" @click="docFechada[s.id] = !docFechada[s.id]" x-text="docFechada[s.id] ? '▸' : '▾'"></button>
                            </div>

                            <div class="doc-sec-corpo" x-show="!docFechada[s.id]">

                                {{-- Texto --}}
                                <template x-if="s.tipo === 'texto'">
                                    <div data-ed class="space-y-2">
                                        <div class="doc-tb" role="toolbar" aria-label="Formatação do texto">
                                            <button type="button" title="Negrito (Ctrl+B)" @mousedown.prevent @click="fmtTexto($event, 'b')"><b>B</b></button>
                                            <button type="button" title="Itálico (Ctrl+I)" @mousedown.prevent @click="fmtTexto($event, 'i')"><i>I</i></button>
                                            <button type="button" title="Sublinhado (Ctrl+U)" @mousedown.prevent @click="fmtTexto($event, 'u')"><u>U</u></button>
                                            <button type="button" title="Tachado" @mousedown.prevent @click="fmtTexto($event, 's')"><s>S</s></button>
                                            <button type="button" title="Marca-texto" @mousedown.prevent @click="fmtTexto($event, 'mark')"><span style="background:#facc1544;padding:0 .25rem;border-radius:.2rem">A</span></button>
                                            <span class="sep"></span>
                                            <button type="button" title="Subtítulo" @mousedown.prevent @click="fmtTexto($event, 'h')"><b>H</b></button>
                                            <button type="button" title="Lista com marcadores" @mousedown.prevent @click="fmtTexto($event, 'ul')">•≡</button>
                                            <button type="button" title="Lista numerada" @mousedown.prevent @click="fmtTexto($event, 'ol')">1.</button>
                                            <button type="button" title="Citação" @mousedown.prevent @click="fmtTexto($event, 'q')">❝</button>
                                            <button type="button" title="Inserir link" @mousedown.prevent @click="fmtTexto($event, 'link')">🔗</button>
                                            <span class="sep"></span>
                                            <button type="button" title="MAIÚSCULAS" @mousedown.prevent @click="fmtTexto($event, 'up')">AA</button>
                                            <button type="button" title="minúsculas" @mousedown.prevent @click="fmtTexto($event, 'low')">aa</button>
                                            <button type="button" title="Iniciais Maiúsculas" @mousedown.prevent @click="fmtTexto($event, 'cap')">Aa</button>
                                            <button type="button" title="Limpar formatação" @mousedown.prevent @click="fmtTexto($event, 'limpar')">Tₓ</button>
                                        </div>
                                        <textarea class="campo doc-ta" rows="6" x-model="s.texto" :placeholder="s.dica || 'Escreva aqui… Selecione um trecho e use a barra acima.'" @keydown="docAtalho($event)"></textarea>
                                        <p class="text-xs texto-2" x-text="docPalavras(s.texto) + ' palavras'"></p>
                                    </div>
                                </template>

                                {{-- Experiência --}}
                                <template x-if="s.tipo === 'experiencia'">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2"><span class="text-xs texto-2">Visual</span>
                                            <select class="campo wf-link-sel" x-model="s.estilo"><option value="linha">Linha do tempo</option><option value="lista">Lista simples</option></select></div>
                                        <template x-for="(it, k) in s.itens" :key="it.id">
                                            <div class="doc-item space-y-2">
                                                <input class="campo" x-model="it.cargo" placeholder="Cargo / título">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input class="campo" x-model="it.local" placeholder="Empresa / local">
                                                    <input class="campo" x-model="it.periodo" placeholder="2021 – 2024">
                                                </div>
                                                <textarea class="campo" rows="3" x-model="it.desc" placeholder="Atividades e resultados (use - para marcadores)" @keydown="docAtalho($event)"></textarea>
                                                <div class="flex justify-end gap-1">
                                                    <button type="button" class="mini-btn" title="Subir" @click="docMover(s.itens, k, -1)">↑</button>
                                                    <button type="button" class="mini-btn" title="Descer" @click="docMover(s.itens, k, 1)">↓</button>
                                                    <button type="button" class="mini-btn perigo" title="Remover" @click="docRemover(s.itens, k)">✕</button>
                                                </div>
                                            </div>
                                        </template>
                                        <button type="button" class="link-prim text-sm" @click="docAddExp(s)">+ Adicionar item</button>
                                    </div>
                                </template>

                                {{-- Habilidades --}}
                                <template x-if="s.tipo === 'habilidades'">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2"><span class="text-xs texto-2">Visual</span>
                                            <select class="campo wf-link-sel" x-model="s.estilo"><option value="barras">Barras</option><option value="pontos">Pontos</option><option value="etiquetas">Etiquetas</option></select></div>
                                        <input class="campo" placeholder="Digite uma habilidade e Enter (aceita várias separadas por vírgula)" @keydown.enter.prevent="docAddHab(s, $el)">
                                        <template x-for="(h, k) in s.itens" :key="h.id">
                                            <div class="flex items-center gap-2">
                                                <input class="wf-cel flex-1" x-model="h.nome" aria-label="Habilidade">
                                                <input type="range" min="1" max="5" step="1" class="doc-range !w-24" x-show="s.estilo !== 'etiquetas'" x-model.number="h.nivel" aria-label="Nível">
                                                <button type="button" class="mini-btn perigo" title="Remover" @click="docRemover(s.itens, k)">✕</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Tabela --}}
                                <template x-if="s.tipo === 'tabela'">
                                    <div class="space-y-2">
                                        <div class="overflow-auto">
                                            <table class="wf-tab">
                                                <thead><tr>
                                                    <template x-for="(c, j) in s.cab" :key="j">
                                                        <th><div class="flex items-center">
                                                            <input class="wf-cel font-semibold" x-model="s.cab[j]">
                                                            <button type="button" class="mini-btn perigo" x-show="s.cab.length > 1" title="Remover coluna" @click="docTabRemCol(s, j)">✕</button>
                                                        </div></th>
                                                    </template>
                                                    <th style="border:0;background:none"></th>
                                                </tr></thead>
                                                <tbody>
                                                    <template x-for="(l, li) in s.linhas" :key="li">
                                                        <tr>
                                                            <template x-for="(c, j) in s.cab" :key="j"><td><input class="wf-cel" x-model="s.linhas[li][j]"></td></template>
                                                            <td style="border:0"><button type="button" class="mini-btn perigo" title="Remover linha" @click="docRemover(s.linhas, li)">✕</button></td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                            <button type="button" class="link-prim text-sm" @click="docTabLinha(s)">+ Linha</button>
                                            <button type="button" class="link-prim text-sm" @click="docTabCol(s)">+ Coluna</button>
                                            <label class="text-xs flex items-center gap-1"><input type="checkbox" x-model="s.total"> Somar última coluna</label>
                                            <label class="text-xs flex items-center gap-1"><input type="checkbox" x-model="s.zebra"> Linhas alternadas</label>
                                        </div>
                                    </div>
                                </template>

                                {{-- Destaque --}}
                                <template x-if="s.tipo === 'destaque'">
                                    <div class="space-y-2">
                                        <div class="doc-paleta">
                                            <span class="text-xs texto-2">Tom</span>
                                            <template x-for="t in tonsDoc" :key="t.id">
                                                <button type="button" class="doc-tom" :class="{ 'ativo': s.tom === t.id }" :style="`background:${t.cor || d.layout.cor}`" :title="t.nome" :aria-label="t.nome" @click="s.tom = t.id"></button>
                                            </template>
                                        </div>
                                        <textarea class="campo" rows="3" x-model="s.texto" placeholder="Texto do destaque" @keydown="docAtalho($event)"></textarea>
                                    </div>
                                </template>

                                {{-- Citação --}}
                                <template x-if="s.tipo === 'citacao'">
                                    <div class="space-y-2">
                                        <textarea class="campo" rows="3" x-model="s.texto" placeholder="Frase em destaque"></textarea>
                                        <input class="campo" x-model="s.autor" placeholder="Autor (opcional)">
                                    </div>
                                </template>

                                {{-- Imagem --}}
                                <template x-if="s.tipo === 'imagem'">
                                    <div class="space-y-2">
                                        <template x-if="s.src"><img :src="s.src" alt="" class="rounded-lg" style="max-height:8rem;max-width:100%"></template>
                                        <label class="btn-sec cursor-pointer inline-block"><span x-text="s.src ? 'Trocar imagem' : 'Escolher imagem'"></span>
                                            <input type="file" accept="image/*" class="hidden" @change="docImagem(s, 'src', $event.target.files[0], { max: 1000 }); $event.target.value = ''">
                                        </label>
                                        <div x-show="s.src" class="space-y-2">
                                            <div><label class="rotulo" x-text="'Largura: ' + s.largura + '%'"></label><input type="range" min="10" max="100" step="5" class="doc-range" x-model.number="s.largura"></div>
                                            <div class="doc-linha">
                                                <div class="doc-seg" role="group" aria-label="Posição">
                                                    <template x-for="a in alinsDoc.slice(0, 3)" :key="a.id">
                                                        <button type="button" class="doc-segbtn" :class="{ 'ativo': s.alin === a.id }" :title="a.nome" @click="s.alin = a.id">
                                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" x-html="a.icone"></svg>
                                                        </button>
                                                    </template>
                                                </div>
                                                <select class="campo wf-link-sel" x-model="s.arred" aria-label="Cantos">
                                                    <option value="0">Cantos retos</option><option value="8">Cantos suaves</option><option value="20">Cantos arredondados</option><option value="circulo">Círculo</option>
                                                </select>
                                            </div>
                                            <input class="campo" x-model="s.legenda" placeholder="Legenda (opcional)">
                                        </div>
                                    </div>
                                </template>

                                {{-- Divisor --}}
                                <template x-if="s.tipo === 'divisor'">
                                    <div class="flex items-center gap-2"><span class="text-xs texto-2">Estilo</span>
                                        <select class="campo wf-link-sel" x-model="s.estilo">
                                            <option value="linha">Linha fina</option><option value="tracejada">Tracejada</option><option value="pontilhada">Pontilhada</option><option value="dupla">Dupla</option><option value="grossa">Grossa colorida</option>
                                        </select></div>
                                </template>

                                {{-- Espaço --}}
                                <template x-if="s.tipo === 'espaco'">
                                    <div><label class="rotulo" x-text="'Altura: ' + s.altura + ' px'"></label><input type="range" min="8" max="200" step="4" class="doc-range" x-model.number="s.altura"></div>
                                </template>

                                {{-- Quebra --}}
                                <template x-if="s.tipo === 'quebra'">
                                    <p class="text-xs texto-2">O conteúdo abaixo começa em uma nova página ao imprimir ou salvar em PDF.</p>
                                </template>

                                {{-- Assinaturas --}}
                                <template x-if="s.tipo === 'assinatura'">
                                    <div class="space-y-2">
                                        <template x-for="(a, k) in s.assinantes" :key="a.id">
                                            <div class="flex items-center gap-2">
                                                <input class="campo flex-1 min-w-0" x-model="a.nome" placeholder="Nome">
                                                <input class="campo flex-1 min-w-0" x-model="a.papel" placeholder="Papel (Contratante…)">
                                                <button type="button" class="mini-btn perigo" title="Remover" @click="docRemover(s.assinantes, k)">✕</button>
                                            </div>
                                        </template>
                                        <button type="button" class="link-prim text-sm" @click="docAddAssin(s)">+ Assinante</button>
                                    </div>
                                </template>

                                {{-- Controles comuns --}}
                                <div class="doc-linha">
                                    <template x-if="['texto', 'destaque', 'citacao'].includes(s.tipo)">
                                        <div class="doc-seg" role="group" aria-label="Alinhamento do texto">
                                            <template x-for="a in alinsDoc" :key="a.id">
                                                <button type="button" class="doc-segbtn" :class="{ 'ativo': s.alin === a.id }" :title="a.nome" :aria-label="a.nome" @click="s.alin = a.id">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" x-html="a.icone"></svg>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                    <select class="campo wf-link-sel" x-model="s.larg" aria-label="Largura do elemento" x-show="!['quebra', 'espaco'].includes(s.tipo)">
                                        <option value="inteira">Largura total</option><option value="metade">Meia largura</option>
                                    </select>
                                    <label class="text-xs flex items-center gap-1" x-show="docTemTitulo(s.tipo)"><input type="checkbox" x-model="s.mostrarTitulo"> título visível</label>
                                </div>
                                <div class="doc-ops">
                                    <button type="button" :disabled="i === 0" @click="moverSecao(d, i, -1)">↑ Subir</button>
                                    <button type="button" :disabled="i === d.secoes.length - 1" @click="moverSecao(d, i, 1)">↓ Descer</button>
                                    <button type="button" @click="duplicarSecao(d, i)">Duplicar</button>
                                    <button type="button" @click="s.oculta = !s.oculta" x-text="s.oculta ? 'Mostrar' : 'Ocultar'"></button>
                                    <button type="button" class="perigo" @click="remSecao(d, i)">Remover</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="space-y-2">
                    <h3 class="titulo text-sm">Adicionar elemento</h3>
                    <div class="doc-add">
                        <template x-for="el in elementosDoc" :key="el.id">
                            <button type="button" class="doc-addbtn" :title="el.desc" @click="addElemento(d, el.id)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="el.icone"></svg>
                                <span x-text="el.nome"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ============ ABA: ESTILO ============ --}}
            <div x-show="docTab === 'estilo'" x-cloak class="space-y-5">
                <div class="space-y-2">
                    <label class="rotulo">Estilo do documento</label>
                    <div class="doc-temas">
                        <template x-for="e in estilosDoc" :key="e.id">
                            <button type="button" class="doc-tema" :class="{ 'ativo': d.layout.estilo === e.id }" @click="aplicarEstilo(d, e.id)">
                                <svg viewBox="0 0 40 28" class="w-full h-8" fill="currentColor" aria-hidden="true" x-html="e.mini"></svg>
                                <span x-text="e.nome"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="rotulo">Cor de destaque</label>
                    <div class="doc-paleta">
                        <template x-for="c in docCores" :key="c">
                            <button type="button" class="doc-cor" :class="{ 'ativo': d.layout.cor === c }" :style="`background:${c}`" :aria-label="'Cor ' + c" @click="d.layout.cor = c"></button>
                        </template>
                        <input type="color" class="wf-cor" x-model="d.layout.cor" aria-label="Cor personalizada" title="Cor personalizada">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="rotulo">Cor do texto</label>
                    <div class="doc-paleta">
                        <template x-for="c in docCoresTexto" :key="c">
                            <button type="button" class="doc-cor" :class="{ 'ativo': d.layout.corTexto === c }" :style="`background:${c}`" :aria-label="'Cor do texto ' + c" @click="d.layout.corTexto = c"></button>
                        </template>
                        <input type="color" class="wf-cor" x-model="d.layout.corTexto" aria-label="Cor de texto personalizada" title="Cor personalizada">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div><label class="rotulo">Fonte</label>
                        <select class="campo" x-model="d.layout.fonte"><template x-for="f in fontesDoc" :key="f.id"><option :value="f.id" x-text="f.nome"></option></template></select></div>
                    <div><label class="rotulo">Espaço entre linhas</label>
                        <select class="campo" x-model="d.layout.linha"><option value="1.3">Compacto</option><option value="1.5">Normal</option><option value="1.75">Confortável</option><option value="2">Amplo</option></select></div>
                    <div><label class="rotulo">Tamanho da letra</label>
                        <div class="doc-seg w-full">
                            <button type="button" class="doc-segbtn flex-1" :class="{ 'ativo': d.layout.tam === 'p' }" @click="d.layout.tam = 'p'">Pequeno</button>
                            <button type="button" class="doc-segbtn flex-1" :class="{ 'ativo': d.layout.tam === 'm' }" @click="d.layout.tam = 'm'">Médio</button>
                            <button type="button" class="doc-segbtn flex-1" :class="{ 'ativo': d.layout.tam === 'g' }" @click="d.layout.tam = 'g'">Grande</button>
                        </div></div>
                    <div><label class="rotulo">Cabeçalho</label>
                        <div class="doc-seg w-full">
                            <button type="button" class="doc-segbtn flex-1" :class="{ 'ativo': d.layout.cab === 'esq' }" @click="d.layout.cab = 'esq'">À esquerda</button>
                            <button type="button" class="doc-segbtn flex-1" :class="{ 'ativo': d.layout.cab === 'centro' }" @click="d.layout.cab = 'centro'">Centralizado</button>
                        </div></div>
                    <div class="col-span-2" x-show="d.tipo === 'curriculo' && d.campos.foto"><label class="rotulo">Formato da foto</label>
                        <div class="doc-seg">
                            <button type="button" class="doc-segbtn" :class="{ 'ativo': d.layout.fotoForma === 'redonda' }" @click="d.layout.fotoForma = 'redonda'">Redonda</button>
                            <button type="button" class="doc-segbtn" :class="{ 'ativo': d.layout.fotoForma === 'quadrada' }" @click="d.layout.fotoForma = 'quadrada'">Quadrada</button>
                        </div></div>
                </div>
            </div>

            {{-- ============ ABA: PÁGINA ============ --}}
            <div x-show="docTab === 'pagina'" x-cloak class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="rotulo">Papel</label>
                        <select class="campo" x-model="d.layout.papel"><template x-for="p in papeisDoc" :key="p.id"><option :value="p.id" x-text="p.nome"></option></template></select></div>
                    <div><label class="rotulo">Margens</label>
                        <select class="campo" x-model="d.layout.margem"><option value="p">Estreitas</option><option value="m">Normais</option><option value="g">Amplas</option></select></div>
                    <div class="col-span-2"><label class="rotulo">Numeração dos títulos</label>
                        <select class="campo" x-model="d.layout.numerar"><option value="nao">Sem numeração</option><option value="num">1. 2. 3.</option><option value="clausula">Cláusula 1 – Cláusula 2 –</option></select></div>
                </div>
                <div><label class="rotulo">Rodapé (fim do documento)</label><input class="campo" x-model="d.layout.rodape" placeholder="Ex.: Empresa Ltda · CNPJ 00.000.000/0001-00"></div>
                <div><label class="rotulo">Marca d'água</label><input class="campo" x-model="d.layout.marca" placeholder="Ex.: RASCUNHO, CONFIDENCIAL"></div>
                <p class="text-xs texto-2">Dica: ao imprimir, desative “Cabeçalhos e rodapés” do navegador e escolha “Salvar como PDF”. As linhas cinzas na prévia indicam onde a página quebra, de forma aproximada.</p>
            </div>
        </div>
    </template>
</x-carteira.item>

{{-- ============ Prévia ============ --}}
<x-carteira.item sec="'docs'" bid="'doc-previa'" titulo="Prévia" :minw="320" :minh="320">
    <x-slot name="acoes">
        <button type="button" class="btn-sec" x-show="docAtual()" @click="docTela = true">Ampliar</button>
        <button type="button" class="btn" x-show="docAtual()" @click="imprimir(docAtual())">Imprimir / PDF</button>
    </x-slot>
    <p x-show="!docAtual()" class="text-sm texto-2">A prévia aparece aqui.</p>
    <template x-if="docAtual()">
        <div class="doc-prev" x-data="docPrevia()">
            <div class="doc-prev-barra">
                <span x-text="docResumo(docAtual())"></span>
                <label class="flex items-center gap-1.5">Zoom
                    <select class="campo wf-link-sel" x-model="zoom" aria-label="Zoom da prévia">
                        <option value="fit">Ajustar</option><option value="0.5">50%</option><option value="0.75">75%</option><option value="1">100%</option><option value="1.25">125%</option>
                    </select>
                </label>
            </div>
            <div class="doc-prev-area" x-ref="area">
                <div :style="docPapelStyle(docAtual()) + 'zoom:' + escala(docPapelLargura(docAtual()))" x-html="docHtml(docAtual())"></div>
            </div>
        </div>
    </template>
</x-carteira.item>

{{-- Prévia em tela cheia --}}
<template x-teleport="body">
    <div x-show="docTela && docAtual()" x-cloak class="doc-overlay" role="dialog" aria-modal="true" aria-label="Prévia ampliada" @keydown.escape.window="docTela = false">
        <div class="doc-overlay-box" x-data="docPrevia()">
            <div class="doc-overlay-barra">
                <strong x-text="docAtual()?.titulo"></strong>
                <div class="flex items-center gap-2">
                    <select class="campo wf-link-sel" x-model="zoom" aria-label="Zoom">
                        <option value="fit">Ajustar</option><option value="0.75">75%</option><option value="1">100%</option><option value="1.25">125%</option>
                    </select>
                    <button type="button" class="btn" @click="imprimir(docAtual())">Imprimir / PDF</button>
                    <button type="button" class="btn-sec" @click="docTela = false">Fechar</button>
                </div>
            </div>
            <div class="doc-prev-area" x-ref="area" style="flex:1">
                <template x-if="docAtual()">
                    <div :style="docPapelStyle(docAtual()) + 'zoom:' + escala(docPapelLargura(docAtual()))" x-html="docHtml(docAtual())"></div>
                </template>
            </div>
        </div>
    </div>
</template>