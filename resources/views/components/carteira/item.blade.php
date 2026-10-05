@props(['sec', 'bid', 'titulo' => null, 'minw' => 280, 'minh' => 160, 'fixo' => true])

<div class="wf-item"
     :class="{ 'arrastando': arrastando === {{ $bid }} }"
     :style="caixa({{ $sec }}, {{ $bid }})"
     :data-sec="{{ $sec }}" :data-bid="{{ $bid }}"
     @pointerdown="frente({{ $sec }}, {{ $bid }})">
    <div class="wf-topo" @pointerdown="arrastar($event, {{ $sec }}, {{ $bid }})">
        @isset($cabeca)
            {{ $cabeca }}
        @else
            <h2 class="titulo text-base flex-1 truncate">{{ $titulo }}</h2>
        @endisset
        {{ $acoes ?? '' }}
        @if ($fixo)
            {{-- Cartões fixos: guardam uma cópia (tabela, texto ou imagem) na biblioteca. Os blocos livres têm o próprio botão. --}}
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarCartao($el)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
        @endif
        <input type="color" class="wf-cor" title="Cor do cartão" aria-label="Cor do cartão"
               :value="corDe({{ $sec }}, {{ $bid }})"
               @input="setCor({{ $sec }}, {{ $bid }}, $event.target.value)">
    </div>
    <div class="wf-corpo">{{ $slot }}</div>
    <div class="wf-redim" title="Redimensionar"
         @pointerdown.stop.prevent="redimensionar($event, {{ $sec }}, {{ $bid }}, {{ (int) $minw }}, {{ (int) $minh }})"></div>
</div>