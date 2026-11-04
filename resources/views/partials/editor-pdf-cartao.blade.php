{{-- Conteúdo do cartão "Editor de PDF" (todas as áreas). A edição acontece em tela cheia (public/js/editor-pdf.js);
     aqui ficam a escolha do arquivo, o resumo e os botões de abrir e baixar. --}}
<div class="flex flex-col h-full gap-2 min-h-0 justify-center">
    <div class="flex-1 min-h-0 rounded-lg grid place-items-center text-center p-3 cursor-pointer" style="background: var(--superficie-2); border: 1px dashed var(--linha)"
         @dblclick="pdfAbrir(b)" @dragover.prevent.stop @drop.prevent.stop="pdfEscolher(b, $event.dataTransfer.files[0])"
         title="Solte um PDF aqui · duplo clique para abrir o editor">
        <div>
            <div class="text-3xl" aria-hidden="true">📄</div>
            <p class="text-sm mt-1" x-text="pdfInfo(b)"></p>
            <p class="text-xs texto-2 mt-1" x-show="!(b.dados && b.dados.pdf)">Textos, imagens, formas, marca-texto, cor de fundo e páginas: tudo por cima do PDF original, que continua guardado.</p>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <label class="btn-sec !py-1 !px-3 text-sm cursor-pointer">
            <span x-text="b.dados && b.dados.pdf ? 'Trocar PDF' : 'Escolher PDF'"></span>
            <input type="file" accept="application/pdf,.pdf" class="sr-only" @change="pdfEscolher(b, $event.target.files[0]); $event.target.value = ''">
        </label>
        <button type="button" class="btn !py-1 !px-3 text-sm" x-show="b.dados && b.dados.pdf" @click="pdfAbrir(b)">✎ Editar</button>
        <button type="button" class="btn-sec !py-1 !px-3 text-sm" x-show="b.dados && b.dados.pdf" @click="pdfBaixar(b)">⬇ Baixar</button>
    </div>
</div>
