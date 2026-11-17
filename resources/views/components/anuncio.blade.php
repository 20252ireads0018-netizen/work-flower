@props(['slot_id'])

{{-- Bloco de anúncio do AdSense. Não renderiza nada para planos pagos nem nas telas sem anúncio (App\Support\Publicidade). --}}
@if (\App\Support\Publicidade::permitida())
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
