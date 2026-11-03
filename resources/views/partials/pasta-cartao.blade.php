{{-- Conteúdo do cartão "Pasta" (vale para Corpo, Mente, Carteira e Diverso; lógica em public/js/quadro.js).
     Usado dentro do x-for de blocos de cada página: <template x-if="b.tipo === 'pasta'"> @include('partials.pasta-cartao') </template>
     Desenho de pasta na cor do cartão (--wc): folhas aparecendo por cima com o ícone do que está dentro e a
     contagem na frente; ao passar o mouse a frente abre um pouco. Clique na pasta (ou duplo clique no vazio) abre.
     Guardar: arraste um cartão para cima da pasta, ou selecione vários com Ctrl+clique e use "Nova pasta com eles". --}}
<div class="wf-pasta" @dblclick.self="abrirPasta(b)">
    <button type="button" class="wf-pasta-capa" @click="abrirPasta(b)" :title="'Abrir a pasta ' + (b.titulo || '')"
            :aria-label="'Abrir a pasta ' + (b.titulo || '') + ', ' + resumoPasta(b)">
        <span class="wf-pasta-aba" aria-hidden="true"></span>
        <span class="wf-pasta-fundo" aria-hidden="true"></span>
        <span class="wf-pasta-papeis" aria-hidden="true">
            <template x-for="(f, i) in filhosDe(b).slice(0, 3)" :key="f.id">
                <span :style="`--i:${i}`" x-text="iconeTipo(f.tipo)"></span>
            </template>
        </span>
        <span class="wf-pasta-frente" aria-hidden="true">
            <b x-text="filhosDe(b).length || ''"></b>
            <span x-text="filhosDe(b).length ? (filhosDe(b).length === 1 ? 'item' : 'itens') : 'Vazia'"></span>
        </span>
    </button>

    <div class="wf-pasta-rodape">
        <span class="wf-pasta-info" x-text="resumoPasta(b)"></span>
        <button type="button" class="link-prim text-xs" x-show="filhosDe(b).length" @click="esvaziarPasta(b)"
                title="Os cartões voltam para o quadro e a pasta some">Desfazer pasta</button>
        <button type="button" class="btn !py-1 !px-3 text-sm" @click="abrirPasta(b)">Abrir</button>
    </div>

    <ul class="wf-pasta-lista" x-show="filhosDe(b).length">
        <template x-for="f in filhosDe(b)" :key="f.id">
            <li>
                <button type="button" @click="f.tipo === 'pasta' ? abrirPasta(f) : abrirPasta(b, f.id)" :title="'Abrir ' + (f.titulo || 'sem título')">
                    <span aria-hidden="true" x-text="iconeTipo(f.tipo)"></span>
                    <span x-text="f.titulo || 'Sem título'"></span>
                </button>
            </li>
        </template>
    </ul>
    <p class="wf-pasta-vazia" x-show="!filhosDe(b).length">
        Arraste cartões para cima desta pasta, ou marque vários com <b>Ctrl + clique</b> (no celular, segure o topo do cartão)
        e use “Nova pasta com eles”. O clique direito num cartão também tem “Guardar em pasta”.
    </p>
</div>
