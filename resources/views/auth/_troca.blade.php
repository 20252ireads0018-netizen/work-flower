<style>
    [data-texto],
    #painel-form form {
        transition: opacity .2s ease, transform .2s ease;
    }
    [data-texto].some,
    #painel-form form.some {
        opacity: 0;
        transform: translateY(6px);
    }
    @media (prefers-reduced-motion: reduce) {
        [data-texto], #painel-form form { transition: none; }
    }
</style>

<script>
    (function () {
        const espera    = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
        const animaveis = function () { return document.querySelectorAll('[data-texto], #painel-form form'); };
        let ocupado = false;

        async function trocar(url, empurrar) {
            if (ocupado) return;
            ocupado = true;

            const azul = document.getElementById('painel-azul');
            const form = document.getElementById('painel-form');

            const telaGrande   = window.matchMedia('(min-width: 1024px)').matches;
            const semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fade         = semMovimento ? 0 : 200;

            // Painéis deslizam só no desktop e sem "reduzir movimento"
            let fim = Promise.resolve();
            if (telaGrande && !semMovimento) {
                const dx = form.getBoundingClientRect().left - azul.getBoundingClientRect().left;
                const opcoes = { duration: 600, easing: 'cubic-bezier(.77, 0, .18, 1)', fill: 'forwards' };

                azul.style.zIndex = 2;
                azul.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(' + dx + 'px)' }], opcoes);
                fim = form.animate([{ transform: 'translateX(0)' }, { transform: 'translateX(' + (-dx) + 'px)' }], opcoes).finished;
            }

            // Textos somem enquanto a outra tela é buscada
            animaveis().forEach(function (e) { e.classList.add('some'); });

            try {
                const resp = (await Promise.all([fetch(url), espera(fade)]))[0];
                const doc  = new DOMParser().parseFromString(await resp.text(), 'text/html');
                const novoAzul = doc.getElementById('painel-azul');
                const novoForm = doc.getElementById('painel-form');
                if (!resp.ok || !novoAzul || !novoForm) throw new Error('resposta inesperada');

                // Troca os textos e o formulário (ainda invisíveis)
                const novosTextos = novoAzul.querySelectorAll('[data-texto]');
                azul.querySelectorAll('[data-texto]').forEach(function (el, i) {
                    el.textContent = ((novosTextos[i] && novosTextos[i].textContent) || '').trim();
                });

                form.innerHTML = novoForm.innerHTML;
                form.querySelector('form').classList.add('some');

                document.title = doc.title;
                if (empurrar) history.pushState({}, '', url);

                // Textos voltam com fade, ainda durante o deslize
                await espera(30);
                animaveis().forEach(function (e) { e.classList.remove('some'); });
                const campo = form.querySelector('input');
                if (campo) campo.focus({ preventScroll: true });

                // Quando o deslize termina: aplica a nova ordem e solta as animações no mesmo instante
                await fim;
                azul.className = novoAzul.className;
                form.className = novoForm.className;
                azul.style.zIndex = '';
                azul.getAnimations().concat(form.getAnimations()).forEach(function (a) { a.cancel(); });
            } catch (err) {
                window.location.href = url; // se algo falhar, navega normalmente
                return;
            }

            ocupado = false;
        }

        // Delegação: o formulário é substituído a cada troca, então o link novo também precisa funcionar
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[data-troca]');
            if (!link || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
            e.preventDefault();
            trocar(link.href, true);
        });

        // Botão voltar/avançar do navegador
        window.addEventListener('popstate', function () { trocar(window.location.href, false); });

        // Se a página vier do cache ao voltar, desfaz qualquer animação guardada
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) document.getAnimations().forEach(function (a) { a.cancel(); });
        });
    })();
</script>