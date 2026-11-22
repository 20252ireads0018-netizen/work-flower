{{-- Espaço de anúncio da Adsterra. @include('partials.adsterra-espaco', ['tipo' => 'banner' | 'native']).
     Fica escondido até o visitante clicar em "Aceitar todos" no aviso de cookies (partials/adsterra preenche).
     Sem chave no .env, ou para quem está logado, não sai nada (App\Support\Publicidade::adsterra). --}}
@php($tipo = $tipo ?? 'banner')
@if (\App\Support\Publicidade::adsterra() && ($tipo === 'native' ? \App\Support\Publicidade::native() : \App\Support\Publicidade::banners()))
    <aside class="wf-anuncio wf-anuncio-{{ $tipo }}" data-anuncio="{{ $tipo }}" aria-label="Publicidade" hidden>
        <span class="wf-anuncio-rotulo">publicidade</span>
        <div class="wf-anuncio-espaco"></div>
    </aside>
@endif
