/* Work Flower como app instalável (PWA).
 *
 * - Registra o service worker (endereço e escopo vêm do <script data-sw data-escopo>, que usam a base real do site).
 * - Guarda o pedido de instalação do navegador (beforeinstallprompt) e avisa a página com o evento
 *   "wf-app-instalavel"; o item "Instalar o app" das configurações chama window.wfApp.instalar().
 * - No iPhone/iPad (Safari não tem esse pedido) o item mostra como adicionar à Tela de Início.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var swUrl = script && script.dataset.sw;
    var escopo = script && script.dataset.escopo;

    var pedido = null;
    var standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true;
    var ua = navigator.userAgent || '';
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    var app = window.wfApp = {
        instalado: standalone,
        ios: ios && !standalone,
        // há como instalar agora (Chrome/Edge/Android ofereceram) ou dá para ensinar (iOS)
        podeInstalar: function () { return !app.instalado && (!!pedido || app.ios); },
        instalar: function () {
            if (pedido) {
                var p = pedido;
                pedido = null;
                p.prompt();
                return p.userChoice.then(function (r) {
                    avisar();
                    return r && r.outcome === 'accepted';
                }).catch(function () { avisar(); return false; });
            }
            if (app.ios) {
                window.alert('Para instalar no iPhone ou iPad:\n\n1. Toque em Compartilhar (o quadrado com a seta para cima).\n2. Escolha "Adicionar à Tela de Início".\n3. Toque em "Adicionar".');
            }
            return Promise.resolve(false);
        },
    };

    function avisar() {
        window.dispatchEvent(new CustomEvent('wf-app-instalavel', { detail: { pode: app.podeInstalar(), ios: app.ios } }));
    }

    window.addEventListener('beforeinstallprompt', function (ev) {
        ev.preventDefault(); // o navegador não mostra o próprio aviso; a instalação fica no menu de configurações
        pedido = ev;
        avisar();
    });

    window.addEventListener('appinstalled', function () {
        pedido = null;
        app.instalado = true;
        avisar();
    });

    if (app.ios) {
        document.addEventListener('DOMContentLoaded', avisar);
    }

    if (swUrl && 'serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl, { scope: escopo }).catch(function (e) {
                if (window.console) console.warn('Work Flower: service worker não registrado', e);
            });
        });
    }
})();
