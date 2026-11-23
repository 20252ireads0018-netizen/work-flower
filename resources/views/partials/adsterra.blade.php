{{-- Carrega os espaços da Adsterra (partials/adsterra-espaco) só com o consentimento "Aceitar todos" (cookie wf_cookies=tudo).
     Banner: cada um num iframe próprio (o código da Adsterra usa a variável global atOptions e document.write).
     Native: um por página, no primeiro espaço "native". Só Banner e Native: nunca Popunder nem Social Bar. --}}
@if (\App\Support\Publicidade::adsterra())
<style>
    .wf-anuncio { margin: 2rem auto 0; display: flex; flex-direction: column; align-items: center; gap: .35rem; max-width: 100%; padding: 0 1rem; }
    .wf-anuncio[hidden] { display: none; }
    .wf-anuncio-rotulo { font-size: .68rem; letter-spacing: .08em; text-transform: uppercase; color: var(--tinta-2, #8a8f9c); opacity: .7; }
    .wf-anuncio-espaco { max-width: 100%; overflow: hidden; }
    .wf-anuncio-espaco iframe { border: 0; display: block; background: transparent; }
    .wf-anuncio-native, .wf-anuncio-native .wf-anuncio-espaco { width: 100%; max-width: 72rem; }
</style>
<script>
(function () {
    var banners = @json(\App\Support\Publicidade::banners());
    var nativo = @json(\App\Support\Publicidade::native());
    var feito = false;

    function escolherBanner(largura) {
        var movel = banners.filter(function (b) { return b.onde === 'movel'; })[0];
        var pc = banners.filter(function (b) { return b.onde === 'computador'; })[0];
        if (pc && (largura >= pc.largura || !movel)) return pc;
        return movel || pc;
    }

    function banner(caixa) {
        var espaco = caixa.querySelector('.wf-anuncio-espaco');
        var largura = (caixa.parentNode && caixa.parentNode.clientWidth) || window.innerWidth || 0;
        var b = escolherBanner(largura || 9999);
        if (!b) return false;
        if (!(largura > 0)) largura = b.largura;
        var f = document.createElement('iframe');
        f.width = b.largura;
        f.height = b.altura;
        f.title = 'Publicidade';
        f.setAttribute('scrolling', 'no');
        var op = JSON.stringify({ key: b.chave, format: 'iframe', height: b.altura, width: b.largura, params: {} });
        f.srcdoc = '<!doctype html><html><' + 'head><style>html,body{margin:0;padding:0;overflow:hidden;background:transparent}</style></' + 'head><body>' +
            '<script>atOptions=' + op + ';<\/script>' +
            '<script src="' + (b.src || 'https://' + b.host + '/' + b.chave + '/invoke.js') + '"><\/script></body></html>';
        // banner maior que o espaço (728 no celular sem o banner de celular): diminui na proporção
        var escala = Math.min(1, Math.max(0, largura - 32) / b.largura) || 1;
        espaco.style.height = Math.ceil(b.altura * escala) + 'px';
        espaco.style.width = Math.floor(b.largura * escala) + 'px';
        if (escala < 1) { f.style.transform = 'scale(' + escala + ')'; f.style.transformOrigin = 'top left'; }
        espaco.appendChild(f);
        return true;
    }

    function native(caixa) {
        if (!nativo || document.getElementById('container-' + nativo.chave)) return false;
        var d = document.createElement('div');
        d.id = 'container-' + nativo.chave;
        caixa.querySelector('.wf-anuncio-espaco').appendChild(d);
        var s = document.createElement('script');
        s.async = true;
        s.setAttribute('data-cfasync', 'false');
        s.src = (nativo.src || 'https://' + nativo.host + '/' + nativo.chave + '/invoke.js');
        document.body.appendChild(s);
        return true;
    }

    window.wfCarregarAdsterra = function () {
        if (feito) return;
        feito = true;
        document.querySelectorAll('[data-anuncio]').forEach(function (caixa) {
            caixa.hidden = false;
            var ok = caixa.dataset.anuncio === 'native' ? native(caixa) : banner(caixa);
            if (!ok) caixa.hidden = true;
        });
    };
    function iniciar() {
        if (/(?:^|;\s*)wf_cookies=tudo(?:;|$)/.test(document.cookie)) window.wfCarregarAdsterra();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
</script>
@endif
