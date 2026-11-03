/* Work Flower · menu de clique direito (compartilhado por quadro.js, diagrama.js e estudio-musica.js).
   window.WFMenuCtx.abrir(evento | {x, y}, itens)
     itens: [{ rotulo, acao, icone?, atalho?, perigo?, desativado?, marcado?, sub? }, '-', ...]
     sub: lista de itens (abre um submenu ao lado).
   Fecha com Esc, clique fora, rolagem ou ao escolher. Navega com as setas e Enter. */
(function () {
    'use strict';
    if (window.WFMenuCtx) return;

    const CSS = `
    .wf-ctx{position:fixed;z-index:300;min-width:13rem;max-width:20rem;padding:.3rem;border-radius:.6rem;
        background:var(--superficie,#1b1f26);color:var(--tinta,#e5e7eb);border:1px solid var(--linha,#2c323c);
        box-shadow:0 14px 40px rgba(0,0,0,.35);font:13px/1.3 system-ui,sans-serif;user-select:none}
    .wf-ctx button{display:flex;align-items:center;gap:.55rem;width:100%;padding:.42rem .6rem;border:0;border-radius:.4rem;
        background:none;color:inherit;font:inherit;text-align:left;cursor:pointer}
    .wf-ctx button:hover,.wf-ctx button:focus-visible,.wf-ctx button.aberto{background:var(--prim-suave,#24324a);color:var(--prim-forte,#93c5fd);outline:none}
    .wf-ctx button:disabled{opacity:.45;cursor:default;background:none;color:inherit}
    .wf-ctx button.perigo{color:#f87171}
    .wf-ctx .ic{width:1.1rem;text-align:center;flex:none;opacity:.85}
    .wf-ctx .rot{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wf-ctx .at{opacity:.55;font-size:11px}
    .wf-ctx hr{border:0;border-top:1px solid var(--linha,#2c323c);margin:.25rem .2rem}
    .wf-ctx .titulo{padding:.3rem .6rem .2rem;font-size:11px;text-transform:uppercase;letter-spacing:.05em;opacity:.6}`;

    let menus = [];
    function fecharTudo() {
        menus.forEach(m => m.remove());
        menus = [];
        removeEventListener('pointerdown', foraDoMenu, true);
        removeEventListener('keydown', teclado, true);
        removeEventListener('scroll', fecharTudo, true);
        removeEventListener('resize', fecharTudo);
        removeEventListener('blur', fecharTudo);
    }
    function foraDoMenu(e) { if (!menus.some(m => m.contains(e.target))) fecharTudo(); }
    function teclado(e) {
        const m = menus[menus.length - 1];
        if (!m) return;
        const bts = [...m.querySelectorAll('button:not(:disabled)')];
        const i = bts.indexOf(document.activeElement);
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); if (menus.length > 1) { menus.pop().remove(); menus[menus.length - 1].querySelector('button.aberto')?.focus(); } else fecharTudo(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); bts[(i + 1) % bts.length]?.focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); bts[(i - 1 + bts.length) % bts.length]?.focus(); }
        else if (e.key === 'ArrowRight' && document.activeElement?.dataset.sub) { e.preventDefault(); document.activeElement.click(); }
        else if (e.key === 'ArrowLeft' && menus.length > 1) { e.preventDefault(); menus.pop().remove(); }
    }

    function montar(itens, x, y, nivel) {
        if (!document.getElementById('wf-ctx-css')) {
            const s = document.createElement('style');
            s.id = 'wf-ctx-css';
            s.textContent = CSS;
            document.head.appendChild(s);
        }
        const m = document.createElement('div');
        m.className = 'wf-ctx';
        m.setAttribute('role', 'menu');
        itens.forEach(it => {
            if (it === '-') { m.appendChild(document.createElement('hr')); return; }
            if (it.titulo) { const t = document.createElement('div'); t.className = 'titulo'; t.textContent = it.titulo; m.appendChild(t); return; }
            const b = document.createElement('button');
            b.type = 'button';
            b.setAttribute('role', 'menuitem');
            if (it.perigo) b.classList.add('perigo');
            b.disabled = !!it.desativado;
            const ic = document.createElement('span'); ic.className = 'ic'; ic.textContent = it.cor ? '■' : (it.marcado ? '✓' : (it.icone || ''));
            if (it.cor) { ic.style.color = it.cor; ic.style.opacity = '1'; if (it.marcado) ic.style.outline = '2px solid currentColor'; }
            const rot = document.createElement('span'); rot.className = 'rot'; rot.textContent = it.rotulo;
            b.append(ic, rot);
            if (it.atalho || it.sub) { const at = document.createElement('span'); at.className = 'at'; at.textContent = it.sub ? '▸' : it.atalho; b.appendChild(at); }
            if (it.sub) {
                b.dataset.sub = '1';
                const abrirSub = () => {
                    while (menus.length > nivel + 1) menus.pop().remove();
                    m.querySelectorAll('button.aberto').forEach(x => x.classList.remove('aberto'));
                    b.classList.add('aberto');
                    const r = b.getBoundingClientRect();
                    montar(it.sub, r.right + 2, r.top - 4, nivel + 1).querySelector('button:not(:disabled)')?.focus();
                };
                b.addEventListener('click', abrirSub);
                b.addEventListener('pointerenter', abrirSub);
            } else {
                b.addEventListener('pointerenter', () => { while (menus.length > nivel + 1) menus.pop().remove(); m.querySelectorAll('button.aberto').forEach(x => x.classList.remove('aberto')); });
                b.addEventListener('click', () => { fecharTudo(); try { it.acao && it.acao(); } catch (err) { console.error(err); } });
            }
            m.appendChild(b);
        });
        document.body.appendChild(m);
        // cabe na tela: vira para a esquerda/para cima se precisar
        const r = m.getBoundingClientRect();
        let px = x, py = y;
        if (px + r.width > innerWidth - 6) px = nivel ? x - r.width - (menus[nivel - 1]?.getBoundingClientRect().width || 0) - 4 : innerWidth - r.width - 6;
        if (py + r.height > innerHeight - 6) py = Math.max(6, innerHeight - r.height - 6);
        m.style.left = Math.max(6, px) + 'px';
        m.style.top = Math.max(6, py) + 'px';
        menus.push(m);
        return m;
    }

    function abrir(ev, itens) {
        fecharTudo();
        const lista = (itens || []).filter(Boolean);
        if (!lista.length) return;
        if (ev && ev.preventDefault) { ev.preventDefault(); ev.stopPropagation(); }
        const x = ev.clientX ?? ev.x, y = ev.clientY ?? ev.y;
        montar(lista, x, y, 0);
        addEventListener('pointerdown', foraDoMenu, true);
        addEventListener('keydown', teclado, true);
        addEventListener('scroll', fecharTudo, true);
        addEventListener('resize', fecharTudo);
        addEventListener('blur', fecharTudo);
    }

    window.WFMenuCtx = { abrir, fechar: fecharTudo, aberto: () => menus.length > 0 };
})();
