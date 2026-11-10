{{-- Cartão "Documento" fora da Mente (vem junto quando cartões ou a área Mente são levados para outra área).
     Lógica em public/js/documento-cartao.js (métodos doc*). A Mente usa a versão dela (areas/mente/partials/blocos). --}}
<div class="wf-doc-cartao flex flex-col gap-3 h-full min-h-0" x-init="docGarantir(b)">
    <div class="flex flex-wrap items-center gap-2 shrink-0">
        <select class="campo !w-auto !py-1.5 text-sm" aria-label="Modelo"
                @change="docTrocarModelo(b, $event.target.value); $event.target.value = b.dados.modelo">
            <template x-for="m in docModelos" :key="m.id">
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
        <button type="button" class="btn-sec !py-1.5 ml-auto" @click="docImprimir(b)">Imprimir / PDF</button>
    </div>
    <div class="flex-1 min-h-0 overflow-auto">
        <div class="wf-doc" :class="'l-' + b.dados.layout" :style="'--dc:' + b.dados.cor + ';font-family:' + docFonte(b)">
            <template x-for="(s, i) in (b.dados.secoes || [])" :key="s.id">
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
    <button type="button" class="link-prim text-sm self-start shrink-0" @click="b.dados.secoes.push({ id: 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), t: 'Nova seção', texto: '' })">+ Seção</button>
</div>
