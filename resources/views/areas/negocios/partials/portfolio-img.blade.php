{{-- Campo de imagem do editor do portfólio. $v = expressão Alpine do valor (token de mídia ou https://…). --}}
<div class="pe-img">
    <template x-if="{{ $v }}"><img :src="miniatura({{ $v }})" alt=""></template>
    <template x-if="!{{ $v }}"><span class="pe-vazio">🖼️</span></template>
    <span class="btn-sec !py-1 !px-2 text-xs pe-arquivo">
        <span x-text="enviando ? 'Enviando… ' + Math.round(enviando * 100) + '%' : 'Enviar imagem/GIF'"></span>
        <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" :disabled="!!enviando" @change="enviar($event, v => {{ $v }} = v)" aria-label="Enviar imagem">
    </span>
    <button type="button" class="pe-ic" @click="const u = prompt('Endereço da imagem (https://…)', /^https:/.test({{ $v }} || '') ? {{ $v }} : ''); if (u !== null) {{ $v }} = u.trim() || null">Link</button>
    <button type="button" class="pe-ic" x-show="{{ $v }}" @click="{{ $v }} = null">Tirar</button>
</div>
