@props(['slot_id'])

{{-- Bloco de anúncio do AdSense. Não renderiza nada para planos sem anúncios (Premium). --}}
@if (config('services.adsense.client') && ! \App\Support\Planos::semAnuncios(auth()->user()))
    <div data-anuncio>
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="{{ config('services.adsense.client') }}"
             data-ad-slot="{{ $slot_id }}"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    </div>
@endif
