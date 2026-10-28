{{-- Conteúdo do cartão "Editor de imagem" (Corpo, Mente, Carteira e Diverso). Separado do bloco "Imagem" comum:
     a edição (camadas, pincel, ajustes...) acontece em tela cheia, em public/js/editor-imagem.js, aberto por quadro.js.
     A imagem final fica em imagens[b.id]; as camadas em imagens[b.id + '.camadas']. --}}
<div class="flex flex-col h-full gap-2 min-h-0">
    <div class="flex-1 min-h-0 rounded-lg overflow-hidden grid place-items-center cursor-pointer"
         style="background: repeating-conic-gradient(color-mix(in srgb, var(--tinta-2) 22%, transparent) 0 25%, transparent 0 50%) 0 0 / 16px 16px"
         @dblclick="abrirEditorImagem(b)" title="Duplo clique para editar">
        <template x-if="imagens[b.id]">
            <img :src="imagens[b.id]" :alt="b.titulo" class="block max-w-full max-h-full object-contain" draggable="false">
        </template>
        <template x-if="!imagens[b.id]">
            <div class="text-center p-4 text-sm texto-2">
                <div class="text-3xl mb-1" aria-hidden="true">🎨</div>
                Camadas, pincel, texto, formas, recorte e ajustes de cor.
            </div>
        </template>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button type="button" class="btn !py-1 !px-3 text-sm" @click="abrirEditorImagem(b)">✎ Editar</button>
        <span class="text-xs texto-2 flex-1 min-w-0 truncate" x-text="infoEditorImagem(b)"></span>
    </div>
</div>
