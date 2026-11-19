{{-- Pixel da Meta (anúncios do Facebook/Instagram). Só carrega com META_PIXEL_ID no .env (config services.meta.pixel).
     PageView em toda página; CompleteRegistration na primeira página depois de criar a conta
     (flash "wf_evento_cadastro" gravado em AuthController::registro), para a campanha otimizar por cadastros.
     LGPD: segue o aviso de cookies (partials/cookies). Sem "Aceitar todos" o pixel fica com o consentimento
     revogado e não envia nada; ao aceitar, partials/cookies chama fbq('consent', 'grant'). --}}
@php($metaPixel = config('services.meta.pixel'))
@if ($metaPixel)
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        if (!/(?:^|;\s*)wf_cookies=tudo(?:;|$)/.test(document.cookie)) fbq('consent', 'revoke');
        fbq('init', @json((string) $metaPixel));
        fbq('track', 'PageView');
        @if (session('wf_evento_cadastro'))
            fbq('track', 'CompleteRegistration', { content_name: 'Conta Work Flower', status: true });
        @endif
        {{-- Subscribe: assinatura paga confirmada em /admin/pix, avisada uma vez na primeira página depois (App\Support\MetaEventos) --}}
        @foreach (\App\Support\MetaEventos::assinaturasPendentes(auth()->id()) as $ass)
            fbq('track', 'Subscribe', @json(['value' => $ass['valor'], 'currency' => 'BRL', 'content_name' => 'Plano ' . \App\Support\Planos::nome($ass['plano']) . ($ass['vitalicio'] ? ' vitalício' : '')]), { eventID: @json('wf-assinatura-' . $ass['id']) });
        @endforeach
    </script>
@endif
