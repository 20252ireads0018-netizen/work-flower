{{-- Conteúdo do cartão "Editor de PDF" (todas as áreas). A edição acontece em tela cheia (public/js/editor-pdf.js);
     aqui ficam a escolha do arquivo (até 80 MB, guardado em disco no servidor), o progresso do envio, o resumo e os botões. --}}
<div class="flex flex-col h-full gap-2 min-h-0 justify-center">
    <div class="flex-1 min-h-0 rounded-lg grid place-items-center text-center p-3 cursor-pointer" style="background: var(--superficie-2); border: 1px dashed var(--linha)"
         @dblclick="pdfAbrir(b)" @dragover.prevent.stop @drop.prevent.stop="pdfEscolher(b, $event.dataTransfer.files[0])"
         title="Solte um PDF aqui · duplo clique para abrir o editor">
        <div class="w-full">
            <div class="text-3xl" aria-hidden="true">📄</div>
            <template x-if="pdfEnvioDe(b)">
                <div class="mt-1" role="status" aria-live="polite">
                    <p class="text-sm" x-text="pdfEnvioDe(b).txt"></p>
                    <div class="pe-cartao-pr" :class="pdfEnvioDe(b).pct === null && 'indef'"><span :style="pdfEnvioDe(b).pct !== null ? `width: ${pdfEnvioDe(b).pct}%` : ''"></span></div>
                </div>
            </template>
            <template x-if="!pdfEnvioDe(b)">
                <div>
                    <p class="text-sm mt-1" x-text="pdfInfo(b)"></p>
                    <p class="text-xs texto-2 mt-1" x-show="!(b.dados && b.dados.pdf)">Escreva, cubra e reescreva textos, assine, destaque, ponha imagens e formas, gire, junte e reorganize páginas. PDFs de até 80 MB; o original continua guardado.</p>
                </div>
            </template>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <label class="btn-sec !py-1 !px-3 text-sm cursor-pointer" :class="pdfEnvioDe(b) && 'opacity-50 pointer-events-none'">
            <span x-text="b.dados && b.dados.pdf ? 'Trocar PDF' : 'Escolher PDF'"></span>
            <input type="file" accept="application/pdf,.pdf" class="sr-only" @change="pdfEscolher(b, $event.target.files[0]); $event.target.value = ''">
        </label>
        <button type="button" class="btn !py-1 !px-3 text-sm" x-show="b.dados && b.dados.pdf" :disabled="!!pdfEnvioDe(b)" @click="pdfAbrir(b)">✎ Editar</button>
        <button type="button" class="btn-sec !py-1 !px-3 text-sm" x-show="b.dados && b.dados.pdf" :disabled="!!pdfEnvioDe(b)" @click="pdfBaixar(b)">⬇ Baixar</button>
    </div>
</div>
