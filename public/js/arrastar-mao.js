/* Work Flower · mãozinha de arrastar (pan) para rolar o que é maior que a área visível
   (mapas mentais, diagramas, tabelas largas, imagens grandes, qualquer caixa com rolagem).
   - Botão do meio do mouse + arrastar: em qualquer caixa com rolagem.
   - Espaço segurado + arrastar: idem (fora de campos de texto); o cursor vira a mãozinha.
   - Arrastar no fundo vazio de um mapa mental (.wf-mapa) ou de uma caixa com [data-pan]: direto, com o botão esquerdo.
     No diagrama (.wf-dd-lona), o fundo continua fazendo a seleção em caixa: lá vale o botão do meio ou o Espaço.
   Arrastar só rola: não muda nada nos cartões. */
(function () {
    'use strict';
    if (window.WFMao) return;

    const EDITAVEL = 'input, textarea, select, [contenteditable=""], [contenteditable="true"]';
    // o que já tem arrasto ou clique próprio: o arrasto direto (botão esquerdo) não começa em cima disso
    const INTERATIVO = 'button, a, input, textarea, select, label, [contenteditable], [draggable="true"], [role="button"], .wf-no, .wf-no-grip, .wf-alca, .wf-cab, [data-sem-pan]';
    let espaco = false, ativo = null, engolirClique = false;
    document.addEventListener('click', e => { if (engolirClique) { e.stopPropagation(); e.preventDefault(); engolirClique = false; } }, true);

    const rolaX = el => el.scrollWidth > el.clientWidth + 1 && /(auto|scroll)/.test(getComputedStyle(el).overflowX);
    const rolaY = el => el.scrollHeight > el.clientHeight + 1 && /(auto|scroll)/.test(getComputedStyle(el).overflowY);

    /** Primeira caixa com rolagem a partir do alvo (sem contar a página: ela já rola com a roda). */
    function caixaRolavel(el) {
        for (let n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
            if (n.nodeType === 1 && (rolaX(n) || rolaY(n))) return n;
        }
        return null;
    }

    /** Fundo vazio de um mapa mental ou de uma caixa marcada com data-pan (arrasto direto com o botão esquerdo). */
    function fundoDePan(e) {
        const t = e.target;
        if (!(t instanceof Element) || t.closest(INTERATIVO) || t.closest('.wf-dd-lona')) return null;
        const zona = t.closest('.wf-mapa, [data-pan]');
        if (!zona) return null;
        return (rolaX(zona) || rolaY(zona)) ? zona : caixaRolavel(zona);
    }

    function comecar(e, caixa) {
        e.preventDefault();
        ativo = { caixa, x: e.clientX, y: e.clientY, sl: caixa.scrollLeft, st: caixa.scrollTop, id: e.pointerId, moveu: false };
        document.documentElement.classList.add('wf-mao-arrastando');
        try { caixa.setPointerCapture(e.pointerId); } catch { /* sem captura */ }
    }

    document.addEventListener('pointerdown', e => {
        if (ativo || e.pointerType === 'touch') return; // no toque o navegador já rola com o dedo
        const meio = e.button === 1, esq = e.button === 0;
        if (!meio && !esq) return;
        if (meio || (esq && espaco)) {
            const caixa = caixaRolavel(e.target);
            if (caixa) { e.stopPropagation(); comecar(e, caixa); }
            return;
        }
        const caixa = fundoDePan(e);
        if (caixa) comecar(e, caixa);
    }, true);

    // o botão do meio no Windows abre a rolagem automática: aqui ele é a mãozinha
    document.addEventListener('mousedown', e => { if (e.button === 1 && caixaRolavel(e.target)) e.preventDefault(); }, true);

    document.addEventListener('pointermove', e => {
        if (!ativo || e.pointerId !== ativo.id) return;
        const dx = e.clientX - ativo.x, dy = e.clientY - ativo.y;
        if (!ativo.moveu && Math.abs(dx) + Math.abs(dy) < 3) return;
        ativo.moveu = true;
        ativo.caixa.scrollLeft = ativo.sl - dx;
        ativo.caixa.scrollTop = ativo.st - dy;
    }, true);

    const soltar = e => {
        if (!ativo || (e && e.pointerId !== ativo.id)) return;
        try { ativo.caixa.releasePointerCapture(ativo.id); } catch { /* já solto */ }
        // um arrasto de verdade não vira clique em quem estava embaixo
        if (ativo.moveu) { engolirClique = true; setTimeout(() => { engolirClique = false; }, 0); }
        ativo = null;
        document.documentElement.classList.remove('wf-mao-arrastando');
    };
    document.addEventListener('pointerup', soltar, true);
    document.addEventListener('pointercancel', soltar, true);

    document.addEventListener('keydown', e => {
        if (e.code !== 'Space' || e.repeat || e.ctrlKey || e.metaKey || e.altKey) return;
        const a = document.activeElement;
        if (a && a.matches && a.matches(EDITAVEL)) return;
        // só segura o Espaço (sem rolar a página) se o mouse está em cima de algo que rola
        const sob = document.querySelectorAll(':hover');
        const alvo = sob.length ? sob[sob.length - 1] : null;
        if (!alvo || !caixaRolavel(alvo)) return;
        e.preventDefault();
        espaco = true;
        document.documentElement.classList.add('wf-mao');
    }, true);
    const semEspaco = () => { espaco = false; document.documentElement.classList.remove('wf-mao'); };
    document.addEventListener('keyup', e => { if (e.code === 'Space') semEspaco(); }, true);
    addEventListener('blur', semEspaco);

    const css = document.createElement('style');
    css.textContent = `
        .wf-mapa { cursor: grab; }
        .wf-mapa .wf-no, .wf-mapa button, .wf-mapa input { cursor: auto; }
        .wf-mapa button { cursor: pointer; }
        .wf-mapa .wf-no-grip { cursor: grab; }
        html.wf-mao, html.wf-mao * { cursor: grab !important; }
        html.wf-mao-arrastando, html.wf-mao-arrastando * { cursor: grabbing !important; user-select: none !important; }
    `;
    document.head.appendChild(css);

    window.WFMao = { caixaRolavel };
})();
