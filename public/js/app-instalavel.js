/* Work Flower como app instalável (PWA).
 *
 * - Registra o service worker (endereço e escopo vêm do <script data-sw data-escopo>, que usam a base real do site).
 * - Guarda o pedido de instalação do navegador (beforeinstallprompt) e avisa a página com o evento
 *   "wf-app-instalavel"; o item "Instalar o app" das configurações chama window.wfApp.instalar().
 * - No iPhone/iPad (Safari não tem esse pedido) o item mostra como adicionar à Tela de Início.
 * - Botões "Baixar o app" ([data-wf-instalar]) na página inicial, na apresentação, no login, no cadastro e no
 *   cabeçalho do workflow: instalam com 1 clique ou abrem a janela com o instalador do Windows e os passos.
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
        document.documentElement.classList.add('wf-app-instalado');
        avisar();
    });

    if (app.ios) {
        document.addEventListener('DOMContentLoaded', avisar);
    }

    /* ---------- Botões "Baixar o app" (partials/instalar-app: [data-wf-instalar]) ----------
     * 1 clique: se o navegador já ofereceu a instalação (Chrome, Edge, Android), instala na hora.
     * Senão abre uma janelinha com o caminho certo para o aparelho: instalador do Windows (.cmd leve que cria
     * os atalhos) e os passos do navegador (iPhone, Safari, Firefox...). No app instalado os botões somem. */
    var winUrl = script && script.dataset.windows;
    var windows = /windows/i.test(ua);
    var android = /android/i.test(ua);
    var nav = /edg\//i.test(ua) ? 'edge' : /samsungbrowser/i.test(ua) ? 'samsung' : /firefox|fxios/i.test(ua) ? 'firefox'
        : /opr\//i.test(ua) ? 'opera' : /chrome|crios/i.test(ua) ? 'chrome' : /safari/i.test(ua) ? 'safari' : 'outro';

    function estilo() {
        if (document.getElementById('wf-inst-css')) return;
        var s = document.createElement('style');
        s.id = 'wf-inst-css';
        s.textContent = 'html.wf-app-instalado [data-wf-instalar]{display:none!important}'
            + '#wf-inst{border:1px solid var(--linha,#334);border-radius:1rem;padding:0;max-width:min(30rem,calc(100vw - 2rem));width:100%;background:var(--superficie,#141b24);color:var(--tinta,#e5e7eb);box-shadow:0 24px 60px rgba(0,0,0,.45)}'
            + '#wf-inst::backdrop{background:rgba(5,8,12,.6);backdrop-filter:blur(3px)}'
            + '#wf-inst[open]{animation:wfInst .25s cubic-bezier(.2,.8,.3,1)}@keyframes wfInst{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}'
            + '#wf-inst .wi-c{padding:1.25rem 1.25rem 1rem}#wf-inst h2{font:700 1.25rem "Bricolage Grotesque","DM Sans",sans-serif;margin:0 2rem .25rem 0}'
            + '#wf-inst p{margin:.35rem 0;font-size:.875rem;line-height:1.5}#wf-inst .wi-2{color:var(--tinta-2,#9ca3af)}'
            + '#wf-inst .wi-bloco{border:1px solid var(--linha,#334);border-radius:.75rem;padding:.8rem .9rem;margin-top:.75rem}'
            + '#wf-inst .wi-bloco strong{display:block;font-size:.9rem;margin-bottom:.15rem}#wf-inst ol{list-style:decimal;margin:.3rem 0 0 1.1rem;padding:0;font-size:.85rem;line-height:1.55}'
            + '#wf-inst .wi-acoes{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem}'
            + '#wf-inst .wi-x{position:absolute;top:.6rem;right:.7rem;border:0;background:none;color:inherit;font-size:1.4rem;line-height:1;cursor:pointer;opacity:.7}#wf-inst .wi-x:hover{opacity:1}';
        document.head.appendChild(s);
    }
    estilo();
    if (app.instalado) document.documentElement.classList.add('wf-app-instalado');

    function passosNavegador() {
        if (app.ios) return ['Toque em <b>Compartilhar</b> (o quadrado com a seta para cima).', 'Escolha <b>Adicionar à Tela de Início</b>.', 'Toque em <b>Adicionar</b>. O ícone da flor aparece junto dos outros apps.'];
        if (nav === 'safari') return ['No menu <b>Arquivo</b>, escolha <b>Adicionar ao Dock</b>.', 'Confirme em <b>Adicionar</b>.'];
        if (nav === 'firefox') return windows
            ? ['O Firefox não instala apps de sites. Use o <b>instalador para Windows</b> acima (abre no Chrome ou no Edge).']
            : ['O Firefox não instala apps de sites. Abra este endereço no Chrome ou no Edge e clique em <b>Baixar o app</b> de novo.'];
        if (android) return ['Toque no menu <b>⋮</b> do navegador.', 'Escolha <b>Instalar app</b> (ou <b>Adicionar à tela inicial</b>).', 'Confirme. O ícone da flor aparece na tela inicial.'];
        if (nav === 'edge') return ['Clique em <b>…</b> (Configurações e mais) no canto do Edge.', 'Vá em <b>Aplicativos › Instalar este site como um aplicativo</b>.', 'Clique em <b>Instalar</b>.'];
        return ['Clique no ícone de instalar <b>⊕</b> no fim da barra de endereço (ou menu <b>⋮ › Transmitir, salvar e compartilhar › Instalar página como app</b>).', 'Clique em <b>Instalar</b>.'];
    }

    function janela() {
        var d = document.getElementById('wf-inst');
        if (!d) {
            d = document.createElement('dialog');
            d.id = 'wf-inst';
            d.setAttribute('aria-labelledby', 'wf-inst-t');
            document.body.appendChild(d);
            d.addEventListener('click', function (e) {
                if (e.target === d || e.target.closest('[data-wi-fechar]')) d.close();
                var b = e.target.closest('[data-wi-agora]');
                if (b) { d.close(); app.instalar(); }
            });
        }
        var h = '<button type="button" class="wi-x" data-wi-fechar aria-label="Fechar">×</button><div class="wi-c">'
            + '<h2 id="wf-inst-t">Baixar o Work Flower</h2>'
            + '<p class="wi-2">Vira um app com o ícone da flor, abre em janela própria, direto no seu workflow e com o login mantido.</p>';
        if (pedido) {
            h += '<div class="wi-bloco"><strong>Instalar agora</strong><p class="wi-2">Um clique e pronto: o navegador cria o app.</p>'
                + '<div class="wi-acoes"><button type="button" class="btn" data-wi-agora>Instalar o app</button></div></div>';
        }
        if (windows && winUrl) {
            h += '<div class="wi-bloco"><strong>Instalador para Windows</strong>'
                + '<ol><li>Baixe o <b>Instalar Work Flower.cmd</b> (poucos KB, sem administrador).</li>'
                + '<li>Dê dois cliques nele. Se o Windows avisar, clique em <b>Mais informações › Executar assim mesmo</b>.</li>'
                + '<li>O atalho aparece na Área de Trabalho e no Menu Iniciar e o app já abre.</li></ol>'
                + '<div class="wi-acoes"><a class="' + (pedido ? 'btn-sec' : 'btn') + '" href="' + winUrl + '" download>⬇ Baixar para Windows</a></div></div>';
        }
        h += '<div class="wi-bloco"><strong>' + (pedido || (windows && winUrl) ? 'Ou pelo navegador' : 'Pelo navegador') + '</strong><ol>'
            + passosNavegador().map(function (p) { return '<li>' + p + '</li>'; }).join('') + '</ol></div>'
            + '<p class="wi-2" style="margin-top:.8rem;font-size:.78rem">Ao entrar, deixe marcado <b>Manter conectado neste aparelho</b>: o app abre sempre logado e sincroniza com o site.</p>'
            + '</div>';
        d.innerHTML = h;
        if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
    }

    app.abrirJanela = janela;
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-wf-instalar]');
        if (!b) return;
        e.preventDefault();
        if (pedido && !b.hasAttribute('data-wf-instalar-janela')) app.instalar(); else janela();
    });

    if (swUrl && 'serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(swUrl, { scope: escopo }).catch(function (e) {
                if (window.console) console.warn('Work Flower: service worker não registrado', e);
            });
        });
    }
})();
