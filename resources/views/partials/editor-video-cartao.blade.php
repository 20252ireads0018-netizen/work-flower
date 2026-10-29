{{-- Conteúdo do cartão "Editor de vídeo" (Corpo, Mente, Carteira e Diverso). A edição (linha do tempo, cortes,
     textos, música, exportação) acontece em tela cheia, em public/js/editor-video.js, aberto por quadro.js.
     O projeto fica em b.dados.projeto e a capa em imagens[b.id]; os arquivos de vídeo ficam no navegador de quem editou. --}}
<div class="flex flex-col h-full gap-2 min-h-0">
    <div class="flex-1 min-h-0 rounded-lg overflow-hidden grid place-items-center cursor-pointer relative" style="background:#0b0d10"
         @dblclick="abrirEditorVideo(b)" title="Duplo clique para editar">
        <template x-if="imagens[b.id]">
            <img :src="imagens[b.id]" :alt="b.titulo" class="block max-w-full max-h-full object-contain" draggable="false">
        </template>
        <template x-if="imagens[b.id]">
            <button type="button" class="absolute inset-0 m-auto w-14 h-14 rounded-full grid place-content-center text-2xl"
                    style="background: color-mix(in srgb, var(--prim) 85%, transparent); color: var(--on-prim)"
                    @click.stop="assistirVideo(b)" aria-label="Assistir o vídeo exportado" title="Assistir o vídeo exportado">▶</button>
        </template>
        <template x-if="!imagens[b.id]">
            <div class="text-center p-4 text-sm texto-2">
                <div class="text-3xl mb-1" aria-hidden="true">🎬</div>
                Corte, junte vídeos e fotos, ponha títulos e música e exporte em MP4.
            </div>
        </template>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button type="button" class="btn !py-1 !px-3 text-sm" @click="abrirEditorVideo(b)">✎ Editar</button>
        <span class="text-xs texto-2 flex-1 min-w-0 truncate" x-text="infoEditorVideo(b)"></span>
    </div>
</div>
