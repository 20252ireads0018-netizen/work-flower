/* Work Flower · aviso discreto de mudanças em workflows compartilhados (servidor: GET /sincronia, Sincronia::novidades).
   Na página aberta o próprio quadro aplica as mudanças na hora (quadro.js, prepararSincronia); aqui só se avisa
   quando outra pessoa mudou uma área que NÃO é a desta tela. O aviso usa as cores que a pessoa escolheu (--prim).
   Também expõe window.wfAvisoSutil(texto, { rotulo, acao, chave }) para o quadro avisar de mudanças em outra aba. */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const CHAVE_VISTO = 'wf-novidades-visto';
    const RAPIDO = 10000, LENTO = 60000; // sem workflows compartilhados, confere bem menos

    const ler = () => { try { return JSON.parse(localStorage.getItem(CHAVE_VISTO) || '{}') || {}; } catch { return {}; } };
    const gravar = v => { try { localStorage.setItem(CHAVE_VISTO, JSON.stringify(v)); } catch { /* sem armazenamento */ } };

    /* ---------- aviso ---------- */
    function caixa() {
        let c = document.getElementById('wf-sutil');
        if (c) return c;
        const css = document.createElement('style');
        css.textContent = `
            #wf-sutil { position: fixed; left: 16px; bottom: calc(16px + env(safe-area-inset-bottom, 0px)); z-index: 9000;
                display: flex; flex-direction: column; gap: 8px; max-width: min(360px, calc(100vw - 32px)); pointer-events: none; }
            .wf-sutil { pointer-events: auto; display: flex; align-items: center; gap: 10px; padding: 8px 10px 8px 12px;
                font-size: 13px; line-height: 1.35; color: var(--tinta, #e5e7eb); border-radius: 12px;
                background: color-mix(in srgb, var(--prim, #8b5cf6) 10%, var(--superficie, #10151b));
                border: 1px solid var(--prim-linha, rgba(139,92,246,.4)); box-shadow: 0 8px 24px rgba(0,0,0,.28);
                opacity: 0; transform: translateY(6px); transition: opacity .25s, transform .25s; }
            .wf-sutil.visivel { opacity: 1; transform: none; }
            .wf-sutil-ponto { width: 8px; height: 8px; border-radius: 9999px; background: var(--prim, #8b5cf6); flex-shrink: 0;
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim, #8b5cf6) 25%, transparent); }
            .wf-sutil-txt { flex: 1; min-width: 0; }
            .wf-sutil a, .wf-sutil button.wf-sutil-ir { color: var(--prim-forte, #a78bfa); font-weight: 600; white-space: nowrap; background: none; border: 0; padding: 0; cursor: pointer; font: inherit; }
            .wf-sutil a:hover, .wf-sutil button.wf-sutil-ir:hover { text-decoration: underline; }
            .wf-sutil-x { color: var(--tinta-2, #9ca3af); background: none; border: 0; cursor: pointer; font-size: 15px; line-height: 1; padding: 2px 4px; }
            @media (prefers-reduced-motion: reduce) { .wf-sutil { transition: none; } }`;
        document.head.appendChild(css);
        c = document.createElement('div');
        c.id = 'wf-sutil';
        c.setAttribute('role', 'status');
        c.setAttribute('aria-live', 'polite');
        document.body.appendChild(c);
        return c;
    }

    /** Aviso pequeno no canto. opcoes: { rotulo, url | acao, chave (troca o aviso de mesma chave), tempo (ms) } */
    window.wfAvisoSutil = function (texto, opcoes = {}) {
        const c = caixa();
        if (opcoes.chave) c.querySelector(`[data-chave="${CSS.escape(opcoes.chave)}"]`)?.remove();
        while (c.children.length >= 3) c.firstElementChild.remove();

        const el = document.createElement('div');
        el.className = 'wf-sutil';
        if (opcoes.chave) el.dataset.chave = opcoes.chave;
        const ponto = document.createElement('span'); ponto.className = 'wf-sutil-ponto';
        const txt = document.createElement('span'); txt.className = 'wf-sutil-txt'; txt.textContent = texto;
        el.append(ponto, txt);
        const fechar = () => { el.classList.remove('visivel'); setTimeout(() => el.remove(), 260); };
        if (opcoes.rotulo && (opcoes.url || opcoes.acao)) {
            const ir = document.createElement(opcoes.url ? 'a' : 'button');
            ir.className = 'wf-sutil-ir'; ir.textContent = opcoes.rotulo;
            if (opcoes.url) ir.href = opcoes.url; else { ir.type = 'button'; ir.addEventListener('click', () => { opcoes.acao(); fechar(); }); }
            el.appendChild(ir);
        }
        const x = document.createElement('button');
        x.type = 'button'; x.className = 'wf-sutil-x'; x.textContent = '×'; x.setAttribute('aria-label', 'Fechar aviso');
        x.addEventListener('click', fechar);
        el.appendChild(x);
        c.appendChild(el);
        requestAnimationFrame(() => el.classList.add('visivel'));
        let t = setTimeout(fechar, opcoes.tempo || 9000);
        el.addEventListener('mouseenter', () => clearTimeout(t));
        el.addEventListener('mouseleave', () => { t = setTimeout(fechar, 3000); });
        return el;
    };

    /* ---------- conferência ---------- */
    const url = meta('wf-novidades-url');
    if (!url) return;
    // workflow desta tela (o quadro dela já se atualiza sozinho)
    const aqui = meta('wf-area') ? `${meta('wf-area')}:${meta('wf-dono') || meta('wf-eu')}` : '';
    let ocupado = false, intervalo = RAPIDO, timer = null, ultima = 0;

    async function conferir() {
        if (ocupado || document.hidden || Date.now() - ultima < 3000) return;
        ocupado = true; ultima = Date.now();
        try {
            const r = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-WF-Dono': '' } });
            if (!r.ok) return;
            const { novidades = [] } = await r.json();
            const visto = ler();
            for (const n of novidades) {
                const antes = visto[n.chave];
                visto[n.chave] = n.em;
                if (n.chave === aqui || antes === undefined || n.em <= antes) continue; // esta tela, primeira vez ou já visto
                const quem = n.por_nome || 'Alguém';
                const onde = n.meu ? `na sua área ${n.area_nome}` : `em ${n.area_nome} de ${n.dono_nome || 'outra conta'}`;
                window.wfAvisoSutil(`${quem} fez mudanças ${onde}.`, { rotulo: 'Ver', url: n.url, chave: n.chave });
            }
            gravar(visto);
            const novo = novidades.length ? RAPIDO : LENTO;
            if (novo !== intervalo) { intervalo = novo; agendar(); }
        } catch { /* sem rede: tenta de novo depois */ } finally { ocupado = false; }
    }
    function agendar() { clearInterval(timer); timer = setInterval(conferir, intervalo); }

    agendar();
    setTimeout(conferir, 2500);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) conferir(); });
})();
