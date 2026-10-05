{{-- Cartão ativo: mostra o cartão ORIGINAL (de outra página) funcionando de verdade, com o JS e os dados de lá. --}}
<template x-if="b.tipo === 'vivo'">
    <div class="h-full flex flex-col gap-2">
        <template x-if="!embutido">
            <iframe :src="urlVivo(b)" :title="b.titulo || 'Cartão ativo'" loading="lazy"
                    class="w-full flex-1 min-h-0 rounded-lg" style="border:0"
                    :style="arrastando ? 'pointer-events:none' : ''"></iframe>
        </template>
        <p class="text-xs texto-2">
            Cartão ativo · <a :href="b.dados.pagina" class="link-prim" x-text="'abrir ' + (b.dados.pagina || '').replace('/areas/', '')"></a>
        </p>
    </div>
</template>