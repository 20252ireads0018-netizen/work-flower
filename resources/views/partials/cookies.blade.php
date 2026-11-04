{{-- Aviso de cookies (LGPD), para visitantes e contas logadas. A escolha fica no cookie wf_cookies por um ano:
     "essenciais" (login e preferências; anúncios sem personalização) ou "tudo" (anúncios personalizados).
     O layout lê esse cookie antes do AdSense. "Cookies" no rodapé ou em window.wfCookies() abre o aviso de novo. --}}
<div id="wf-cookies" class="wf-cookies" role="dialog" aria-live="polite" aria-label="Aviso de cookies" hidden>
    <p class="text-sm">
        Usamos cookies essenciais para o login e as suas preferências, e cookies de anúncios para manter o Work Flower gratuito.
        Você escolhe se os anúncios podem ser personalizados. <a class="link-prim" href="{{ \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade') }}#cookies">Saiba mais</a>
    </p>
    <div class="flex flex-wrap gap-2 shrink-0">
        <button type="button" class="btn-sec !py-1.5 text-sm" data-cookies="essenciais">Só os essenciais</button>
        <button type="button" class="btn !py-1.5 text-sm" data-cookies="tudo">Aceitar todos</button>
    </div>
</div>
<style>
    .wf-cookies { position: fixed; left: 1rem; right: 1rem; bottom: 1rem; z-index: 1500; max-width: 46rem; margin: 0 auto;
        display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; padding: .9rem 1rem;
        background: var(--superficie, #1b1f24); color: var(--tinta, #e6e6e6); border: 1px solid var(--linha, #333); border-radius: .9rem; box-shadow: 0 16px 40px rgba(0,0,0,.4); }
    .wf-cookies[hidden] { display: none; }
    .wf-cookies p { flex: 1 1 18rem; margin: 0; }
</style>
<script>
    (function () {
        var caixa = document.getElementById('wf-cookies');
        if (!caixa) return;
        var escolhido = /(?:^|;\s*)wf_cookies=(tudo|essenciais)(?:;|$)/.test(document.cookie);
        window.wfCookies = function () { caixa.hidden = false; };
        if (!escolhido) caixa.hidden = false;
        caixa.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cookies]');
            if (!b) return;
            var v = b.getAttribute('data-cookies');
            var mudou = /(?:^|;\s*)wf_cookies=tudo(?:;|$)/.test(document.cookie) !== (v === 'tudo');
            document.cookie = 'wf_cookies=' + v + '; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
            caixa.hidden = true;
            // os anúncios só mudam de personalização num carregamento novo da página
            if (mudou && escolhido) location.reload();
        });
    })();
</script>
