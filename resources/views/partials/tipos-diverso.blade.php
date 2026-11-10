{{-- Cartões do Diverso fora do Diverso ("Banco de dados", "GitHub / VS Code" e "Tabletop"): estilo e scripts.
     Vêm junto quando cartões ou a área são levados para cá (App\Support\MoverCartoes). Incluído uma vez por área (Corpo,
     Mente, Carteira/Negócios), logo depois do script da área e antes do Alpine; só carrega o que a página tem
     ($wfTiposDiverso = MoverCartoes::tiposDoDiverso(estado)). Os cartões ficam em partials/tipos-diverso-cartoes. --}}
@if (!empty($wfTiposDiverso))
    @if (in_array('tabletop', $wfTiposDiverso, true))
        @include('partials.tabletop-estilo')
        {{-- salas ao vivo (tabletopVivo.js): fora do Diverso o endereço não sai do diverso-cfg --}}
        <script>window.WF_MESA_URL = @json(url('/tabletop-vivo'));</script>
        {{-- métodos tt* do cartão (WFTabletop); o diverso.js só define funções aqui, a página continua sendo da área --}}
        <script src="{{ asset('js/diverso.js') }}?v={{ @filemtime(public_path('js/diverso.js')) }}&r=2"></script>
        <script src="{{ asset('js/tabletopVivo.js') }}?v={{ @filemtime(public_path('js/tabletopVivo.js')) }}"></script>
    @endif
    @if (in_array('banco', $wfTiposDiverso, true))
        @include('partials.banco-estilo')
        <script src="{{ asset('js/banco-sql.js') }}?v={{ @filemtime(public_path('js/banco-sql.js')) }}" data-worker="{{ asset('js/banco-sql-worker.js') }}?v={{ @filemtime(public_path('js/banco-sql-worker.js')) }}"></script>
    @endif
    @if (in_array('codigo', $wfTiposDiverso, true))
        @include('partials.ide-cartao')
        <script src="{{ asset('js/mapa-codigo.js') }}?v={{ @filemtime(public_path('js/mapa-codigo.js')) }}"></script>
        <script src="{{ asset('js/ide-motores.js') }}?v={{ @filemtime(public_path('js/ide-motores.js')) }}"
                data-nucleo="{{ asset('js/ide-mini-laravel.txt') }}?v={{ @filemtime(public_path('js/ide-mini-laravel.txt')) }}"></script>
    @endif
    <script src="{{ asset('js/cartoes-diverso.js') }}?v={{ @filemtime(public_path('js/cartoes-diverso.js')) }}"></script>
@endif
