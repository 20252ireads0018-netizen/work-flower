{{-- Espera: $alvo (expressão JS) · ex.: "{ tipo: 'cal' }" ou "{ tipo: 'dieta', ref: r.id }" --}}
<div x-data="buscaAlimento({!! $alvo !!})" class="relative" @click.outside="aberto = false">
    <input class="campo" x-model="q" autocomplete="off"
           placeholder="Ex.: arroz 150 g, 2 ovos, banana"
           @focus="aberto = true"
           @input="aberto = true; i = 0"
           @keydown.arrow-down.prevent="i = Math.min(i + 1, sug.length - 1)"
           @keydown.arrow-up.prevent="i = Math.max(i - 1, 0)"
           @keydown.enter.prevent="enter()"
           @keydown.escape="aberto = false">

    <ul x-show="aberto && q.trim()" x-cloak class="wf-sug" role="listbox">
        <template x-for="(s, n) in sug" :key="s.f.id">
            <li>
                <button type="button" class="wf-sug-item" :class="{ 'ativo': n === i }"
                        @mouseenter="i = n" @click="escolher(s)">
                    <span class="text-sm" x-text="s.item.nome + ' · ' + s.item.g + ' g'"></span>
                    <span class="text-xs texto-2"
                          x-text="s.item.kcal + ' kcal · P ' + s.item.p + ' · C ' + s.item.c + ' · G ' + s.item.gd"></span>
                </button>
            </li>
        </template>
        <li x-show="!sug.length" class="p-3 text-sm texto-2">
            Não encontrei esse alimento.
            <button type="button" class="link-prim"
                    @click="$dispatch('criar-alimento', { nome: p ? p.nome : '' }); aberto = false">Cadastrar alimento</button>
        </li>
    </ul>
</div>