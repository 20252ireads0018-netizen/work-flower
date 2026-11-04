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
            barra.append(a);
        });
    }

    /* ---------- abrir o cartão favorito ---------- */
    function irPara(bid, sec) {
        // troca para a aba do cartão no componente da página (as áreas guardam a aba aberta em "aba")
        if (sec && window.Alpine) {
            document.querySelectorAll('[x-data]').forEach(el => {
                try { const d = Alpine.$data(el); if (d && 'aba' in d && Array.isArray(d.abas || d.estado?.abas)) d.aba = sec; } catch (e) { /* não é a área */ }
            });
        }
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
