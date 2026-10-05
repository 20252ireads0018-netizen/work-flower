{{--
    Cartão do quadro da área Diverso (arrastar, redimensionar, trocar cor).
    `sec` e `bid` são EXPRESSÕES JavaScript do Alpine:
      fixo:     sec="'quadro'" bid="'tarefa-lista'"
      dinâmico: sec="b.secao"  bid="b.id"   (dentro de um x-for de blocos)
    Slots: padrão (conteúdo) · cabeca (substitui o título) · acoes (botões no topo).
--}}
@props(['sec', 'bid', 'titulo' => '', 'minw' => 260, 'minh' => 160, 'fixo' => true])

<div class="wf-item"
     :class="{ 'arrastando': arrastando === {!! $bid !!} }"
     :style="caixa({!! $sec !!}, {!! $bid !!})"
     :data-sec="{!! $sec !!}" :data-bid="{!! $bid !!}"
     @pointerdown="frente({!! $sec !!}, {!! $bid !!})">

    <div class="wf-topo" @pointerdown="arrastar($event, {!! $sec !!}, {!! $bid !!})" title="Arraste para mover">
        <svg class="w-4 h-4 shrink-0 texto-2" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="9" cy="6" r="1.4"/><circle cx="15" cy="6" r="1.4"/>
            <circle cx="9" cy="12" r="1.4"/><circle cx="15" cy="12" r="1.4"/>
            <circle cx="9" cy="18" r="1.4"/><circle cx="15" cy="18" r="1.4"/>
        </svg>

        @isset($cabeca)
            {{ $cabeca }}
        @else
            <h2 class="titulo text-base flex-1 truncate">{{ $titulo }}</h2>
        @endisset

        @isset($acoes)
            {{ $acoes }}
        @endisset

        @if ($fixo)
            {{-- Cartões fixos: guardam uma cópia (tabela, texto ou imagem) na biblioteca. Os blocos livres têm o próprio botão. --}}
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarCartao($el)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
        @endif

        <input type="color" class="wf-cor"
               :value="corDe({!! $sec !!}, {!! $bid !!})"
               @input="setCor({!! $sec !!}, {!! $bid !!}, $event.target.value)"
               title="Cor do cartão" aria-label="Cor do cartão">
    </div>

    <div class="wf-corpo">
        {{ $slot }}
    </div>

    <span class="wf-redim" aria-hidden="true"
          @pointerdown.stop.prevent="redimensionar($event, {!! $sec !!}, {!! $bid !!}, {{ (int) $minw }}, {{ (int) $minh }})"></span>
</div>