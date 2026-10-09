/* Work Flower · tela cheia para qualquer cartão (.wf-item)
   Injeta um botão no cabeçalho (.wf-topo) de cada cartão, inclusive os criados depois.
   Usa a Fullscreen API; sem suporte (ex.: iPhone), cai numa camada que cobre a janela. */
(function () {
    'use strict';
    if (window.WFTelaCheia) return;

    const svg = d => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
    const ICO_ENTRAR = svg('<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>');
    const ICO_SAIR = svg('<path d="M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5"/>');

    let atual = null;   // cartão em tela cheia
    let nativo = false; // true quando a Fullscreen API está em uso
    let ocupado = false; // evita cliques repetidos durante a transição

    const elFs = () => document.fullscreenElement || document.webkitFullscreenElement || null;

    /* Só mexe no DOM do botão quando o estado muda (evita o ciclo com o MutationObserver). */
    function pintar(b, ativo) {
        const est = ativo ? '1' : '0';
        if (b.dataset.fs === est) return;
        b.dataset.fs = est;
        b.innerHTML = ativo ? ICO_SAIR : ICO_ENTRAR;
        const t = ativo ? 'Sair da tela cheia (Esc)' : 'Tela cheia';
        b.title = t;
        b.setAttribute('aria-label', t);
        b.setAttribute('aria-pressed', ativo ? 'true' : 'false');
    }

    function atualizar() {
        document.querySelectorAll('.wf-fs-btn').forEach(b => {
            pintar(b, !!atual && b.closest('.wf-item') === atual);
        });
    }

    function limpar() {
        if (atual) {
            atual.classList.remove('wf-fs');
            const q = atual.closest('.wf-quadro');
            if (q) q.classList.remove('wf-fs-ativo');
        }
        document.documentElement.classList.remove('wf-fs-pagina');
        atual = null;
        nativo = false;
        atualizar();
    }

    /* O navegador pode ter saído da tela cheia sem nos avisar a tempo: descarta o estado velho. */
    function sincronizar() {
        if (atual && (!atual.isConnected || (nativo && !elFs()))) limpar();
    }

    async function sair() {
        if (!atual) return;
        if (elFs()) {
            try {
                const x = document.exitFullscreen || document.webkitExitFullscreen;
                const r = x && x.call(document);
                if (r && r.then) await r;
            } catch (_) { /* limpa mesmo assim */ }
        }
        limpar();
    }

    async function entrar(item) {
        if (atual && atual !== item) await sair();
        atual = item;
        nativo = false;
        item.classList.add('wf-fs');
        const q = item.closest('.wf-quadro');
        if (q) q.classList.add('wf-fs-ativo'); // tira o "isolation" do quadro para a camada ficar por cima
        document.documentElement.classList.add('wf-fs-pagina');
        atualizar();
        const req = item.requestFullscreen || item.webkitRequestFullscreen;
        if (req) {
            try {
                const r = req.call(item);
                if (r && r.then) await r;
                nativo = !!elFs();
            } catch (_) { nativo = false; } // cai na camada de janela
        }
        atualizar();
    }

    async function alternar(item) {
        if (!item || ocupado) return;
        ocupado = true;
        try {
            sincronizar();
            if (item === atual) await sair(); else await entrar(item);
        } finally { ocupado = false; }
    }

    /* Botões no cabeçalho de cada cartão */
    function injetar() {
        document.querySelectorAll('.wf-item .wf-topo').forEach(topo => {
            if (topo.querySelector('.wf-fs-btn')) return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'mini-btn wf-fs-btn';
            b.dataset.fs = '0';
            b.innerHTML = ICO_ENTRAR;
            b.title = 'Tela cheia';
            b.setAttribute('aria-label', 'Tela cheia');
            b.setAttribute('aria-pressed', 'false');
            const primeiro = topo.querySelector('.mini-btn');
            if (primeiro && primeiro.parentNode) primeiro.parentNode.insertBefore(b, primeiro);
            else topo.appendChild(b);
        });
        atualizar();
    }

    let agendado = false;
    function agendar() {
        if (agendado) return;
        agendado = true;
        requestAnimationFrame(() => {
            agendado = false;
            injetar();
            sincronizar();
        });
    }

    // O botão não deve iniciar o arrastar do cabeçalho
    ['pointerdown', 'mousedown', 'touchstart'].forEach(tipo => {
        document.addEventListener(tipo, e => {
            const t = e.target;
            if (!t || !t.closest) return;
            if (t.closest('.wf-fs-btn')) { e.stopPropagation(); return; }
            // Em tela cheia, o cabeçalho e a alça não arrastam nem redimensionam o cartão
            if (t.closest('.wf-fs .wf-topo') || t.closest('.wf-fs .wf-redim')) e.stopPropagation();
        }, true);
    });

    document.addEventListener('click', e => {
        const b = e.target.closest && e.target.closest('.wf-fs-btn');
        if (!b) return;
        e.preventDefault();
        e.stopPropagation();
        alternar(b.closest('.wf-item'));
    }, true);

    // duplo clique na parte vazia do cabeçalho
    document.addEventListener('dblclick', e => {
        const t = e.target;
        if (t && t.classList && t.classList.contains('wf-topo')) alternar(t.closest('.wf-item'));
    });

    // Esc: na API nativa o navegador já sai sozinho; aqui cobre a camada de janela
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && atual && !nativo) sair();
    }, true);

    // O usuário saiu pelo navegador (Esc / gesto)
    const aoMudar = () => { if (atual && nativo && !elFs()) limpar(); };
    document.addEventListener('fullscreenchange', aoMudar);
    document.addEventListener('webkitfullscreenchange', aoMudar);


    /* Tamanho do ícone: sem isso o svg (que só tem viewBox) pode colapsar dentro do .mini-btn */
    const st = document.createElement('style');
    st.textContent = `
        .wf-fs-btn { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; }
        .wf-fs-btn svg { width: .9rem; height: .9rem; display: block; pointer-events: none; }`;
    document.head.appendChild(st);
    function iniciar() {
        injetar();
        new MutationObserver(agendar).observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    window.WFTelaCheia = { alternar, entrar, sair, atual: () => atual };
})();