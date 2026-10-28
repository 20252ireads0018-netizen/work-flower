{{-- Conteúdo do cartão "Pasta" (vale para Corpo, Mente, Carteira e Diverso; lógica em public/js/quadro.js).
     Usado dentro do x-for de blocos de cada página: <template x-if="b.tipo === 'pasta'"> @include('partials.pasta-cartao') </template>
     Guardar: arraste um cartão para cima da pasta, ou selecione vários com Ctrl+clique e use "Nova pasta com eles". --}}
<div class="wf-pasta" @dblclick.self="abrirPasta(b)">
    <div class="wf-pasta-topo">
        <span class="wf-pasta-ico" aria-hidden="true">📁</span>
        <span class="wf-pasta-info" x-text="resumoPasta(b)"></span>
        <button type="button" class="btn !py-1 !px-3 text-sm" @click="abrirPasta(b)">Abrir</button>
        <button type="button" class="btn-sec !py-1 !px-3 text-sm" x-show="filhosDe(b).length" @click="esvaziarPasta(b)"
                title="Os cartões voltam para o quadro e a pasta some">Desfazer pasta</button>
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
        Pasta vazia. Arraste cartões para cima dela, ou marque vários com <b>Ctrl + clique</b> (no celular, segure o dedo no topo do cartão) e use “Nova pasta com eles”.
    </p>
</div>
