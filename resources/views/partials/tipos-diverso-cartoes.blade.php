{{-- Cartões do Diverso dentro dos blocos de outra área (vêm junto ao mover; partials/tipos-diverso carrega estilo e
     scripts, js/cartoes-diverso.js liga os métodos). Só os tipos que a página tem ($wfTiposDiverso). --}}
@if (!empty($wfTiposDiverso))
    @if (in_array('codigo', $wfTiposDiverso, true))
        {{-- GitHub / VS Code: repositórios e mini-IDE (partials/codigo-cartao) --}}
        <template x-if="b.tipo === 'codigo'">
            @include('partials.codigo-cartao')
        </template>
    @endif
    @if (in_array('banco', $wfTiposDiverso, true))
        {{-- Banco de dados (.sql/.csv): montado por public/js/banco-sql.js fora do Alpine --}}
        <template x-if="b.tipo === 'banco'">
            <div class="bd h-full flex flex-col gap-2" x-init="bdMontar(b, $el)" x-effect="bdSinc(b, $el)"></div>
        </template>
    @endif
    @if (in_array('tabletop', $wfTiposDiverso, true))
        {{-- Tabletop: mapa com grade e peças arrastáveis (métodos tt* do WFTabletop, salas ao vivo do tabletopVivo.js) --}}
        <template x-if="b.tipo === 'tabletop'">
            @include('areas.diverso.partials.tabletop')
        </template>
    @endif
@endif
