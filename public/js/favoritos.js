/* Work Flower · cartões favoritos (carregado pelo layout para quem está logado)
   - Cada cartão das áreas ganha uma estrela no topo; marcada, ele entra nos favoritos da conta (preferências no servidor).
   - Encostar o mouse na borda esquerda da tela (ou tocar na aba ★ no celular) abre a barra de favoritos, em qualquer página.
   - Clicar num favorito abre a página certa, troca para a aba do cartão, rola até ele e pisca a borda. */
(function () {
    'use strict';
    if (window.wfFavoritos || !window.WFPrefs) return;
    window.wfFavoritos = true;
    const lista = () => (Array.isArray(WFPrefs.ler().favoritos) ? WFPrefs.ler().favoritos : []);
    // a página do cartão: o caminho e, num workflow de outra pessoa, o ?de=<dono>
    const aqui = () => { const de = new URLSearchParams(location.search).get('de'); return location.pathname + (de && /^\d+$/.test(de) ? '?de=' + de : ''); };
    const ehFav = bid => lista().some(f => f.bid === bid && f.url === aqui());

    function nomeDo(card) {
        const t = card.querySelector('.wf-topo .wf-titulo, .wf-topo input, .wf-topo h2, .wf-topo h3');
        return ((t && (t.value || t.textContent)) || 'Cartão').trim().slice(0, 80) || 'Cartão';
    }
    function salvar(nova) { WFPrefs.salvar({ favoritos: nova }); desenharBarra(); marcarTodas(); }
    function alternar(card) {
        const bid = card.dataset.bid;
        if (!bid) return;
        if (ehFav(bid)) { salvar(lista().filter(f => !(f.bid === bid && f.url === aqui()))); return; }
        if (lista().length >= 60) { alert('Você já tem 60 favoritos. Tire algum para adicionar outro.'); return; }
        const area = (document.querySelector('meta[name="wf-area-nome"]')?.content) || document.title.replace(/^Work Flower · /, '');
        salvar([...lista(), { url: aqui(), bid, sec: card.dataset.sec || '', nome: nomeDo(card), area }]);
    }

    /* ---------- estrela em cada cartão ---------- */
    function estrela(card) {
        const topo = card.querySelector(':scope > .wf-topo');
        if (!topo || topo.querySelector('.fav-btn') || !card.dataset.bid) return;
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'mini-btn fav-btn';
        b.addEventListener('pointerdown', e => e.stopPropagation()); // não começa a arrastar o cartão
        b.addEventListener('click', e => { e.stopPropagation(); alternar(card); });
        topo.insertBefore(b, topo.querySelector('.wf-cor') || null);
        pintar(card);
    }
    function pintar(card) {
        const b = card.querySelector('.fav-btn');
        if (!b) return;
        const on = ehFav(card.dataset.bid);
        b.textContent = on ? '★' : '☆';
        b.title = on ? 'Tirar dos favoritos' : 'Favoritar (aparece na barra da borda esquerda)';
        b.setAttribute('aria-pressed', String(on));
        b.style.color = on ? '#f2c230' : '';
    }
    const marcarTodas = () => document.querySelectorAll('.wf-item[data-bid]').forEach(c => { estrela(c); pintar(c); });
    new MutationObserver(() => { clearTimeout(marcarTodas._t); marcarTodas._t = setTimeout(marcarTodas, 120); })
        .observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-bid'] });

    /* ---------- barra da borda ---------- */
    const zona = document.createElement('div');
    zona.className = 'fav-zona';
    zona.setAttribute('aria-hidden', 'true');
    const aba = document.createElement('button');
    aba.type = 'button';
    aba.className = 'fav-aba';
    aba.textContent = '★';
    aba.title = 'Favoritos';
    aba.setAttribute('aria-label', 'Abrir os cartões favoritos');
    const barra = document.createElement('nav');
    barra.className = 'fav-barra';
    barra.setAttribute('aria-label', 'Cartões favoritos');
    document.body.append(zona, aba, barra);
    let fechar = 0;
    const abrir = () => { clearTimeout(fechar); desenharBarra(); barra.classList.add('aberta'); };
    const fecharDepois = () => { clearTimeout(fechar); fechar = setTimeout(() => barra.classList.remove('aberta'), 350); };
    zona.addEventListener('mouseenter', abrir);
    aba.addEventListener('click', () => (barra.classList.contains('aberta') ? barra.classList.remove('aberta') : abrir()));
    barra.addEventListener('mouseenter', () => clearTimeout(fechar));
    barra.addEventListener('mouseleave', fecharDepois);
    zona.addEventListener('mouseleave', e => { if (!barra.contains(e.relatedTarget)) fecharDepois(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') barra.classList.remove('aberta'); });

    function desenharBarra() {
        const l = lista();
        aba.style.display = l.length ? '' : 'none';
        barra.replaceChildren();
        const h = document.createElement('p');
        h.className = 'fav-tit';
        h.textContent = '★ Favoritos';
        barra.append(h);
        if (!l.length) {
            const p = document.createElement('p');
            p.className = 'fav-vazio';
            p.textContent = 'Toque na estrela ☆ no topo de um cartão para ele aparecer aqui.';
            barra.append(p);
            return;
        }
        l.forEach(f => {
            const a = document.createElement('a');
            a.className = 'fav-item';
            a.href = f.url + (f.url.includes('?') ? '&' : '?') + 'fav=' + encodeURIComponent(f.bid) + (f.sec ? '&favsec=' + encodeURIComponent(f.sec) : '');
            const n = document.createElement('span');
            n.textContent = f.nome;
            const s = document.createElement('small');
            s.textContent = f.area;
            a.append(n, s);
            if (f.url === aqui()) a.addEventListener('click', e => { e.preventDefault(); irPara(f.bid, f.sec); barra.classList.remove('aberta'); });
            // tirar dos favoritos pela própria barra
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'fav-tirar';
            x.textContent = '×';
            x.title = 'Tirar dos favoritos';
            x.setAttribute('aria-label', 'Tirar ' + f.nome + ' dos favoritos');
            x.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); salvar(lista().filter(g => !(g.bid === f.bid && g.url === f.url))); });
            const linha = document.createElement('div');
            linha.className = 'fav-linha';
            linha.append(a, x);
            barra.append(linha);
        });
    }
    const st = document.createElement('style');
    st.textContent = '.fav-linha{display:flex;align-items:stretch;gap:.15rem}.fav-linha .fav-item{flex:1;min-width:0}'
        + '.fav-tirar{flex:none;width:1.6rem;border:0;border-radius:.4rem;background:none;color:inherit;opacity:.45;cursor:pointer;font-size:1rem}'
        + '.fav-tirar:hover,.fav-tirar:focus-visible{opacity:1;background:rgba(239,68,68,.15);color:#f87171}';
    document.head.appendChild(st);

    /* ---------- favoritos de cartões ou abas que não existem mais (nesta página) saem sozinhos ---------- */
    function componente() {
        const q = document.querySelector('.wf-quadro');
        try { const d = q && window.Alpine ? Alpine.$data(q) : null; return d && d.estado && Array.isArray(d.estado.blocos) ? d : null; } catch (e) { return null; }
    }
    let pronto = false;
    function limpar() {
        const c = componente();
        if (!c || !pronto) return;
        const abas = Array.isArray(c.estado.abas) ? c.estado.abas.map(a => a.id) : null; // Diverso: abas criadas pela pessoa
        const existe = f => {
            if (abas && f.sec && !abas.includes(f.sec) && !(c.estado.layout && c.estado.layout[f.sec])) return false; // a aba foi apagada
            if (c.estado.blocos.some(b => b.id === f.bid)) return true;
            if (Object.values(c.estado.layout || {}).some(l => l && l[f.bid])) return true;
            return !!document.querySelector(`.wf-item[data-bid="${CSS.escape(f.bid)}"]`);
        };
        const l = lista(), nova = l.filter(f => f.url !== aqui() || existe(f));
        if (nova.length !== l.length) salvar(nova);
    }
    window.wfFavoritosLimpar = limpar;
    // só depois da página montar (antes disso o estado ainda não carregou e tudo pareceria apagado)
    setTimeout(() => { if (componente()) { pronto = true; limpar(); } }, 2500);
    new MutationObserver(() => { clearTimeout(limpar._t); limpar._t = setTimeout(limpar, 600); })
        .observe(document.documentElement, { childList: true, subtree: true });

    /* ---------- abrir o cartão favorito ---------- */
    function irPara(bid, sec) {
        // troca para a aba do cartão no componente da página (as áreas guardam a aba aberta em "aba")
        if (sec && window.Alpine) {
            document.querySelectorAll('[x-data]').forEach(el => {
                try { const d = Alpine.$data(el); if (d && 'aba' in d && Array.isArray(d.abas || d.estado?.abas)) d.aba = sec; } catch (e) { /* não é a área */ }
            });
        }
        // cartão guardado numa pasta: abre a pasta dele; fora de pasta: volta para o quadro da aba
        setTimeout(() => {
            const c = componente();
            const b = c && typeof c.blocoPorId === 'function' ? c.blocoPorId(bid) : null;
            const s = (b && b.secao) || sec;
            try {
                if (b && b.pasta && typeof c.abrirPasta === 'function') {
                    const p = c.blocoPorId(b.pasta);
                    if (p && p.tipo === 'pasta') c.abrirPasta(p, bid);
                } else if (s && c && c.pasta && c.pasta[s]) {
                    c.pasta = { ...c.pasta, [s]: null };
                }
            } catch (e) { /* página sem pastas */ }
        }, 60);
        let n = 0;
        const achar = () => {
            const c = document.querySelector(`.wf-item[data-bid="${CSS.escape(bid)}"]`);
            if (c && c.offsetParent) {
                c.scrollIntoView({ behavior: 'smooth', block: 'center' });
                c.classList.add('fav-pisca');
                setTimeout(() => c.classList.remove('fav-pisca'), 2200);
            } else if (n++ < 30) setTimeout(achar, 150);
        };
        setTimeout(achar, 120);
    }
    const q = new URLSearchParams(location.search);
    if (q.get('fav')) {
        const bid = q.get('fav'), sec = q.get('favsec');
        q.delete('fav'); q.delete('favsec');
        history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q : '') + location.hash);
        setTimeout(() => irPara(bid, sec), 900); // depois do Alpine montar a página
    }
    desenharBarra();
    window.addEventListener('wf-prefs', () => { desenharBarra(); marcarTodas(); });
})();
