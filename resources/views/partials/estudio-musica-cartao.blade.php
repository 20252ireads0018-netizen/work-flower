{{-- Conteúdo do cartão "Estúdio de música" (Corpo, Mente, Carteira e Diverso). A edição (channel rack, piano roll,
     playlist, mixer e exportação em WAV) acontece em tela cheia, em public/js/estudio-musica.js, aberto por quadro.js.
     O projeto fica em b.dados.projeto; aqui aparece a miniatura do primeiro padrão e o botão de tocar. --}}
<div class="flex flex-col h-full gap-2 min-h-0">
    <div class="flex-1 min-h-0 rounded-lg overflow-hidden cursor-pointer relative p-2 flex flex-col justify-center gap-1" style="background:#22272e"
         @dblclick="abrirEstudioMusica(b)" title="Duplo clique para abrir o estúdio">
        <template x-for="c in gradeEstudioMusica(b)" :key="c.id">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="text-[10px] w-14 truncate flex-none" style="color:#adbac7" x-text="c.nome"></span>
                <div class="flex-1 grid gap-[2px]" style="grid-template-columns: repeat(16, minmax(0, 1fr))">
                    <template x-for="(on, i) in c.passos" :key="i">
                        <span class="h-3 rounded-sm" :style="on ? 'background: var(--prim)' : (Math.floor(i / 4) % 2 ? 'background:#5c4848' : 'background:#4b5260')"></span>
                    </template>
                </div>
            </div>
        </template>
        <template x-if="!gradeEstudioMusica(b).length">
            <div class="text-center p-4 text-sm" style="color:#adbac7">
                <div class="text-3xl mb-1" aria-hidden="true">🎹</div>
                Monte batidas, escreva melodias no piano roll e exporte em WAV.
            </div>
        </template>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button type="button" class="btn !py-1 !px-3 text-sm" @click="abrirEstudioMusica(b)">✎ Abrir estúdio</button>
        <button type="button" class="btn-sec !py-1 !px-3 text-sm" x-show="b.dados && b.dados.projeto" @click="tocarEstudioMusica(b)"
                :aria-label="musicaTocando === b.id ? 'Parar a música' : 'Tocar a música'" x-text="musicaTocando === b.id ? '■ Parar' : '▶ Tocar'"></button>
        <span class="text-xs texto-2 flex-1 min-w-0 truncate" x-text="infoEstudioMusica(b)"></span>
    </div>
</div>
