@props(['sec', 'bid', 'titulo' => null, 'minw' => 260, 'minh' => 160, 'fixo' => true])

{{-- Cartão do quadro: arraste pelo topo, redimensione pelo canto, mude a cor no círculo. --}}
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

        <input type="color" class="wf-cor" title="Cor do item" aria-label="Cor do item"
               :value="corDe({{ $sec }}, {{ $bid }})"
               @input="setCor({{ $sec }}, {{ $bid }}, $event.target.value)">
    </div>

    <div class="wf-corpo">
        {{ $slot }}
    </div>

    <span class="wf-redim" aria-hidden="true"
          @pointerdown.stop.prevent="redimensionar($event, {{ $sec }}, {{ $bid }}, {{ (int) $minw }}, {{ (int) $minh }})"></span>
</div>