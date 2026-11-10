{{-- Menu "+ Bloco" das áreas: busca, tipos agrupados por setor e em ordem alfabética (quadro.js: blocosPorSetor).
     Enter na busca cria o primeiro da lista. --}}
<div class="relative" @click.outside="menuBloco = false; buscaBloco = ''" @keydown.escape.window="menuBloco = false; buscaBloco = ''">
    <button type="button" class="btn" @click="menuBloco = !menuBloco; menuBloco && $nextTick(() => $refs.buscaBloco && $refs.buscaBloco.focus())" aria-haspopup="menu" :aria-expanded="menuBloco">+ Bloco</button>
    <div x-show="menuBloco" x-cloak class="menu-pop wf-menu-bloco absolute right-0 top-full mt-2 w-72 p-1.5 z-40" role="menu">
        <input type="search" x-ref="buscaBloco" class="campo !py-1 text-sm mb-1" placeholder="Buscar bloco…" aria-label="Buscar tipo de bloco"
               x-model="buscaBloco" @keydown.enter.prevent="blocoDaBusca(aba)">
        <div class="wf-menu-bloco-lista">
            <template x-for="g in blocosPorSetor()" :key="g.setor">
                <div role="group" :aria-label="g.setor">
                    <p class="wf-menu-bloco-setor" x-text="g.setor"></p>
                    <template x-for="t in g.tipos" :key="t.id">
                        <button type="button" class="menu-item" role="menuitem" @click="novoBloco(aba, t.id); buscaBloco = ''" x-text="t.nome"></button>
                    </template>
                </div>
            </template>
            <p x-show="!blocosPorSetor().length" class="text-xs texto-2 px-2 py-1.5">Nenhum bloco com esse nome.</p>
        </div>
    </div>
</div>
