/* Work Flower · "Meu Flower": mapa mental de todo o workflow (dados em GET /flower/dados, FlowerController).
   - Pessoa no centro, uma pétala por área; dentro: abas › pastas › cartões › tarefas, em leque (árvore radial).
   - Clique: abre/fecha o ramo e mostra o detalhe (com "Abrir", que usa o ?fav=<id>&favsec=<aba> do js/favoritos.js).
     Duplo clique abre direto. Arrastar move, roda do mouse / pinça aproxima. Busca acende o que achar e abre o caminho.
   - Tudo animado: os nós andam em coordenadas polares, então o mapa "desabrocha" ao abrir um ramo (com um leve
     "estouro" na saída), nós novos nascem com um pop, o arrasto tem inércia e as pétalas balançam com o movimento,
     passar o mouse acende o caminho até a pessoa. Respeita "reduzir movimento" do sistema. */
(function () {
    'use strict';
    const palco = document.getElementById('flower');
    if (!palco) return;

    const NS = 'http://www.w3.org/2000/svg';
    const svg = palco.querySelector('.fl-svg');
    const $ = s => palco.querySelector(s);
    const el = (tag, attrs, pai) => { const e = document.createElementNS(NS, tag); for (const k in attrs || {}) e.setAttribute(k, attrs[k]); if (pai) pai.appendChild(e); return e; };
    const semAcento = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const reduzMov = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    const mundo = el('g', { class: 'fl-mundo' }, svg);
    const gPetalas = el('g', { class: 'fl-petalas' }, mundo);
    const gLinhas = el('g', { class: 'fl-linhas' }, mundo);
    const gNos = el('g', { class: 'fl-nos' }, mundo);

    /* ------------------------------------------------------------ dados */
    let raiz = null;
    const porId = new Map();
    const CHAVE_ABERTOS = 'flower.abertos';
    let abertos = new Set();

    function montar(d) {
        raiz = { id: 'eu', tipo: 'pessoa', nome: d.pessoa.nome, filhos: d.areas, cor: null };
        const visitar = (n, pai, area, prof) => {
            n.pai = pai; n.prof = prof; n.area = area || n; n.filhos = Array.isArray(n.filhos) ? n.filhos : [];
            n.cor = area ? area.cor : n.cor;
            n.busca = semAcento(n.nome);
            porId.set(n.id, n);
            n.total = 0;
            n.filhos.forEach(f => { visitar(f, n, prof === 0 ? f : area, prof + 1); n.total += 1 + f.total; });
        };
        visitar(raiz, null, null, 0);
        raiz.area = null;
        let guardados = null;
        try { guardados = JSON.parse(sessionStorage.getItem(CHAVE_ABERTOS) || 'null'); } catch (e) { /* sem sessionStorage */ }
        if (Array.isArray(guardados)) abertos = new Set(guardados.filter(id => porId.has(id)));
        else padrao();
        abertos.add('eu');
    }
    // começo: pessoa, áreas e abas abertas (os cartões aparecem); pastas e lista de tarefas fechadas
    function padrao() { abertos = new Set(['eu']); raiz.filhos.forEach(a => { abertos.add(a.id); a.filhos.forEach(ab => abertos.add(ab.id)); }); }
    function guardar() { try { sessionStorage.setItem(CHAVE_ABERTOS, JSON.stringify([...abertos])); } catch (e) { /* ok */ } }

    /* ------------------------------------------------------------ layout (árvore radial em pétalas) */
    const RAIO_AREA = 230;
    const PASSO = [0, 0, 210, 200, 175, 160, 150];       // distância entre um nível e o próximo
    const ESPACO = [0, 0, 30, 21, 18, 16, 16];           // arco mínimo entre irmãos, por nível
    const TAM = { pessoa: 50, area: 36, aba: 13, pasta: 10, cartao: 7.5, tarefa: 5.5 };

    const visiveis = n => (abertos.has(n.id) ? n.filhos : []);
    let alvo = new Map();     // id -> {a, r}
    let petalaAlvo = new Map(); // area id -> {a, meio, R}

    function calcular() {
        alvo = new Map(); petalaAlvo = new Map();
        alvo.set('eu', { a: -Math.PI / 2, r: 0 });
        const areas = visiveis(raiz);
        const A = areas.length || 1, setor = Math.PI * 2 / A;
        const folhas = n => { const f = visiveis(n); return f.length ? f.reduce((s, c) => s + folhas(c), 0) : 1; };
        areas.forEach((area, i) => {
            const a0 = -Math.PI / 2 + i * setor;
            // raio de cada nível: o passo mínimo, ou mais se os nós do nível não couberem no arco da pétala
            const porNivel = [];
            const contar = (n) => { porNivel[n.prof] = (porNivel[n.prof] || 0) + 1; visiveis(n).forEach(contar); };
            contar(area);
            const raios = [0, RAIO_AREA];
            for (let p = 2; p < porNivel.length; p++) {
                const pas = PASSO[Math.min(p, PASSO.length - 1)], esp = ESPACO[Math.min(p, ESPACO.length - 1)];
                raios[p] = Math.max(raios[p - 1] + pas, porNivel[p] * esp / (setor * 0.86));
            }
            const util = setor * 0.9;
            const pos = (n, ini, larg) => {
                alvo.set(n.id, { a: ini + larg / 2, r: raios[n.prof] });
                const fs = visiveis(n);
                if (!fs.length) return;
                const tot = fs.reduce((s, c) => s + folhas(c), 0);
                // filhos ocupam o arco do pai, mas nunca mais apertados que o mínimo (centralizados no pai)
                const minimo = fs.length * ESPACO[Math.min(n.prof + 1, ESPACO.length - 1)] / raios[n.prof + 1];
                const l = Math.min(util, Math.max(larg, minimo));
                let c = ini + larg / 2 - l / 2;
                fs.forEach(f => { const w = l * folhas(f) / tot; pos(f, c, w); c += w; });
            };
            pos(area, a0 - util / 2, util);
            alvo.get(area.id).a = a0; // a área fica no eixo da pétala
            petalaAlvo.set(area.id, { a: a0, meio: setor / 2, R: raios[raios.length - 1] + 90 });
        });
    }

    /* ------------------------------------------------------------ desenho e animação */
    const atual = new Map();       // id -> {a, r} na tela agora
    const petalaAtual = new Map();
    const elsNo = new Map(), elsLinha = new Map(), elsPetala = new Map();
    let anim = null;

    const xy = p => [Math.cos(p.a) * p.r, Math.sin(p.a) * p.r];
    const angDif = (a, b) => { let d = b - a; while (d > Math.PI) d -= 2 * Math.PI; while (d < -Math.PI) d += 2 * Math.PI; return d; };
    const ease = t => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    // sai um pouco além do alvo e volta: o ramo "desabrocha" em vez de só deslizar
    const estoura = t => { const c1 = 1.15, c3 = c1 + 1; return 1 + c3 * Math.pow(t - 1, 3) + c1 * Math.pow(t - 1, 2); };

    function ancestralVisivel(n, mapa) { let p = n.pai; while (p && !mapa.has(p.id)) p = p.pai; return p; }

    function noEl(n) {
        let g = elsNo.get(n.id);
        if (g) return g;
        g = el('g', { class: 'fl-no fl-' + n.tipo, tabindex: '0', role: 'button', 'data-id': n.id }, gNos);
        elsNo.set(n.id, g);
        g.style.setProperty('--c', n.cor || 'var(--prim)');
        if (n.status) g.dataset.status = n.status;
        if (n.tipo === 'pessoa') {
            // a flor da marca, grande, atrás do nome
            const flor = el('g', { class: 'fl-flor' }, g);
            for (let i = 0; i < 6; i++) el('path', { transform: `rotate(${i * 60}) scale(7.2)`, d: 'M0-3.6 L3.4-7.6 L0-11.4 L-3.4-7.6Z' }, flor);
        }
        el('circle', { class: 'fl-bola', r: TAM[n.tipo] || 7 }, g);
        if (n.tipo === 'tarefa' && n.status === 'concluida') el('path', { class: 'fl-check', d: 'M-2.6 0 L-0.6 2 L2.8 -1.9' }, g);
        const t = el('text', { class: 'fl-rot' }, g);
        t.textContent = n.nome.length > 34 ? n.nome.slice(0, 33) + '…' : n.nome;
        if (n.tipo === 'area' || n.tipo === 'pessoa') {
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('dy', n.tipo === 'pessoa' ? '0.35em' : '0.35em');
            if (n.tipo === 'area') {
                const c = el('text', { class: 'fl-conta', 'text-anchor': 'middle', dy: (TAM.area + 16) + '' }, g);
                const r = n.resumo || {};
                c.textContent = `${r.cartoes || 0} cartões · ${r.tarefas || 0} tarefas`;
            }
        }
        const mais = el('text', { class: 'fl-mais', 'text-anchor': 'middle', dy: '0.35em' }, g);
        g._mais = mais; g._rot = t;
        g.setAttribute('aria-label', rotuloAcessivel(n));
        return g;
    }
    function rotuloAcessivel(n) {
        const tipo = { pessoa: 'Você', area: 'Área', aba: 'Aba', pasta: 'Pasta', cartao: 'Cartão', tarefa: 'Tarefa' }[n.tipo] || '';
        return `${tipo}: ${n.nome}` + (n.filhos.length ? `, ${n.filhos.length} itens dentro` : '');
    }

    function linhaEl(n) {
        let p = elsLinha.get(n.id);
        if (!p) { p = el('path', { class: 'fl-linha fl-l-' + n.tipo }, gLinhas); p.style.setProperty('--c', n.cor || 'var(--prim)'); elsLinha.set(n.id, p); }
        return p;
    }
    function petalaEl(area) {
        let p = elsPetala.get(area.id);
        if (!p) { p = el('path', { class: 'fl-petala' }, gPetalas); p.style.setProperty('--c', area.cor); elsPetala.set(area.id, p); }
        return p;
    }

    function caminhoPetala(pt) {
        const P = (a, r) => (Math.cos(a) * r).toFixed(1) + ' ' + (Math.sin(a) * r).toFixed(1);
        const { a, meio, R } = pt, h = meio * 0.92;
        return `M0 0 C${P(a - h, R * 0.42)} ${P(a - h * 0.62, R * 0.9)} ${P(a, R)} C${P(a + h * 0.62, R * 0.9)} ${P(a + h, R * 0.42)} 0 0Z`;
    }
    function caminhoLinha(pp, pc) {
        const [x1, y1] = xy(pp), [x2, y2] = xy(pc);
        if (pp.r === 0) {
            const m = { a: pc.a, r: pc.r * 0.5 };
            const [mx, my] = xy(m);
            return `M0 0 Q${mx.toFixed(1)} ${my.toFixed(1)} ${x2.toFixed(1)} ${y2.toFixed(1)}`;
        }
        const rm = (pp.r + pc.r) / 2;
        const [c1x, c1y] = xy({ a: pp.a, r: rm }), [c2x, c2y] = xy({ a: pc.a, r: rm });
        return `M${x1.toFixed(1)} ${y1.toFixed(1)} C${c1x.toFixed(1)} ${c1y.toFixed(1)} ${c2x.toFixed(1)} ${c2y.toFixed(1)} ${x2.toFixed(1)} ${y2.toFixed(1)}`;
    }

    function pintarQuadro(pos, pets, saindo) {
        pos.forEach((p, id) => {
            const n = porId.get(id), g = elsNo.get(id);
            if (!n || !g) return;
            const [x, y] = xy(p);
            g.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)})`);
            if (n.tipo !== 'area' && n.tipo !== 'pessoa') {
                // rótulo para fora, ao longo do raio; do lado esquerdo vira para não ficar de cabeça para baixo
                const deg = p.a * 180 / Math.PI, esq = Math.cos(p.a) < 0, off = (TAM[n.tipo] || 7) + 5;
                g._rot.setAttribute('transform', esq ? `rotate(${(deg + 180).toFixed(2)}) translate(${-off} 0)` : `rotate(${deg.toFixed(2)}) translate(${off} 0)`);
                g._rot.setAttribute('text-anchor', esq ? 'end' : 'start');
                g._rot.setAttribute('dy', '0.35em');
            }
            if (n.pai) {
                const pp = pos.get(n.pai.id) || { a: 0, r: 0 };
                linhaEl(n).setAttribute('d', caminhoLinha(pp, p));
            }
        });
        pets.forEach((pt, id) => petalaEl(porId.get(id)).setAttribute('d', caminhoPetala(pt)));
        if (saindo) saindo.forEach(id => { const g = elsNo.get(id); if (g) g.style.opacity = String(1 - saindo.t); const l = elsLinha.get(id); if (l) l.style.opacity = String(1 - saindo.t); });
    }

    function marcarEstados() {
        alvo.forEach((_, id) => {
            const n = porId.get(id), g = noEl(n);
            const fechado = n.filhos.length && !abertos.has(id);
            g.classList.toggle('fl-fechado', !!fechado);
            g.classList.toggle('fl-tem-filhos', n.filhos.length > 0);
            g.setAttribute('aria-expanded', n.filhos.length ? String(abertos.has(id)) : 'false');
            g._mais.textContent = fechado && n.tipo !== 'pessoa' && n.tipo !== 'area' ? (n.total > 99 ? '99+' : String(n.total)) : '';
            if (n.tipo === 'area') g._mais.textContent = '';
        });
    }

    function redesenhar(instantaneo) {
        const antes = new Map(atual);
        const petAntes = new Map(petalaAtual);
        calcular();
        // nós novos (e as linhas deles) nascem da posição atual do ancestral, com um pop escalonado
        const novos = new Set();
        alvo.forEach((p, id) => {
            const n = porId.get(id);
            noEl(n);
            if (n.pai) linhaEl(n);
            if (!antes.has(id)) {
                const anc = ancestralVisivel(n, antes);
                antes.set(id, anc ? { ...antes.get(anc.id) } : { a: p.a, r: 0 });
                const g = elsNo.get(id); g.style.opacity = '';
                const l = elsLinha.get(id); if (l) l.style.opacity = '';
                if (!instantaneo && !reduzMov) {
                    const ms = Math.min(novos.size, 24) * 14, atraso = ms + 'ms';
                    g.style.setProperty('--atraso', atraso); g.classList.add('fl-nasce');
                    if (l) { l.style.setProperty('--atraso', atraso); l.classList.add('fl-nasce'); }
                    // garantia: em aba escondida o animationend pode não chegar
                    setTimeout(() => { g.classList.remove('fl-nasce'); if (l) l.classList.remove('fl-nasce'); }, ms + 900);
                }
                novos.add(id);
            }
        });
        const petNovas = new Set();
        petalaAlvo.forEach((pt, id) => { if (!petAntes.has(id)) { petAntes.set(id, { a: pt.a, meio: pt.meio, R: 0 }); petNovas.add(id); } });
        // nós que saem voltam para dentro do ancestral que continua aberto
        const saindo = [];
        const fimSaida = new Map();
        antes.forEach((p, id) => {
            if (alvo.has(id)) return;
            const anc = ancestralVisivel(porId.get(id), alvo);
            fimSaida.set(id, anc ? alvo.get(anc.id) : { a: p.a, r: 0 });
            saindo.push(id);
        });
        // quem está saindo perde o pop de nascimento (senão a animação seguraria a opacidade)
        saindo.forEach(id => { elsNo.get(id)?.classList.remove('fl-nasce'); elsLinha.get(id)?.classList.remove('fl-nasce'); });
        marcarEstados();
        aplicarBusca();

        const dur = instantaneo || reduzMov ? 0 : 720;
        const t0 = performance.now();
        if (anim) cancelAnimationFrame(anim);
        const passo = agora => {
            const t = dur ? Math.min(1, (agora - t0) / dur) : 1, e = ease(t), eb = estoura(t);
            const quadro = new Map();
            const mistura = (de, para, k) => ({ a: de.a + angDif(de.a, para.a) * k, r: Math.max(0, de.r + (para.r - de.r) * k) });
            alvo.forEach((p, id) => quadro.set(id, mistura(antes.get(id), p, novos.has(id) ? eb : e)));
            saindo.forEach(id => quadro.set(id, mistura(antes.get(id), fimSaida.get(id), e)));
            const pets = new Map();
            petalaAlvo.forEach((pt, id) => {
                const de = petAntes.get(id), k = petNovas.has(id) ? eb : e;
                pets.set(id, { a: de.a + angDif(de.a, pt.a) * k, meio: de.meio + (pt.meio - de.meio) * k, R: Math.max(0, de.R + (pt.R - de.R) * k) });
            });
            saindo.t = e;
            pintarQuadro(quadro, pets, saindo);
            quadro.forEach((p, id) => atual.set(id, p));
            pets.forEach((p, id) => petalaAtual.set(id, p));
            if (t < 1) { anim = requestAnimationFrame(passo); return; }
            anim = null;
            saindo.forEach(id => { elsNo.get(id)?.remove(); elsNo.delete(id); elsLinha.get(id)?.remove(); elsLinha.delete(id); atual.delete(id); });
        };
        anim = requestAnimationFrame(passo);
    }

    /* ------------------------------------------------------------ câmera (arrastar, zoom, ajustar) */
    const cam = { x: 0, y: 0, k: 1 };
    let camAnim = null;
    function aplicarCam() {
        mundo.setAttribute('transform', `translate(${cam.x.toFixed(1)} ${cam.y.toFixed(1)}) scale(${cam.k.toFixed(4)})`);
        svg.classList.toggle('fl-longe', cam.k < 0.42);
        svg.classList.toggle('fl-muito-longe', cam.k < 0.22);
    }
    function irCam(x, y, k, dur = 550) {
        if (camAnim) cancelAnimationFrame(camAnim);
        pararInercia();
        if (reduzMov) dur = 0;
        const de = { ...cam }, t0 = performance.now();
        const passo = agora => {
            const t = dur ? Math.min(1, (agora - t0) / dur) : 1, e = ease(t);
            cam.x = de.x + (x - de.x) * e; cam.y = de.y + (y - de.y) * e; cam.k = de.k + (k - de.k) * e;
            aplicarCam();
            camAnim = t < 1 ? requestAnimationFrame(passo) : null;
        };
        camAnim = requestAnimationFrame(passo);
    }
    const tamanho = () => { const r = svg.getBoundingClientRect(); return { w: r.width || 800, h: r.height || 600 }; };
    function enquadrar(ids, dur) {
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        (ids || [...alvo.keys()]).forEach(id => {
            const p = alvo.get(id); if (!p) return;
            const [x, y] = xy(p);
            x0 = Math.min(x0, x); y0 = Math.min(y0, y); x1 = Math.max(x1, x); y1 = Math.max(y1, y);
        });
        if (!isFinite(x0)) return;
        const pad = 140, { w: wTotal, h } = tamanho();
        // a legenda das áreas ocupa o canto esquerdo: a flor fica no espaço ao lado dela
        const leg = $('.fl-legenda'), esq = leg && leg.offsetWidth && wTotal > 900 ? leg.offsetWidth + 16 : 0, w = wTotal - esq;
        const k = Math.max(0.06, Math.min(1.6, Math.min(w / (x1 - x0 + pad * 2), h / (y1 - y0 + pad * 2))));
        irCam(esq + w / 2 - (x0 + x1) / 2 * k, h / 2 - (y0 + y1) / 2 * k, k, dur);
    }
    function zoomEm(fator, cx, cy) {
        if (camAnim) { cancelAnimationFrame(camAnim); camAnim = null; }
        const { w, h } = tamanho();
        if (cx == null) { cx = w / 2; cy = h / 2; }
        const k = Math.max(0.05, Math.min(4, cam.k * fator));
        cam.x = cx - (cx - cam.x) * (k / cam.k); cam.y = cy - (cy - cam.y) * (k / cam.k); cam.k = k;
        aplicarCam();
    }
    function centrarEm(id, k) {
        const p = alvo.get(id); if (!p) return;
        const [x, y] = xy(p), { w, h } = tamanho(), kk = k || Math.max(cam.k, 0.9);
        irCam(w / 2 - x * kk, h / 2 - y * kk, kk);
    }

    /* física leve: inércia depois de soltar e o balanço das pétalas (mola amortecida). Um só rAF, que para sozinho. */
    const fis = { vx: 0, vy: 0, deslizando: false, ang: 0, angV: 0, angAlvo: 0, raf: null };
    function moverFisica() {
        if (fis.raf || reduzMov) return;
        let ultimo = performance.now();
        const passo = agora => {
            const dt = Math.min(40, Math.max(1, agora - ultimo)); ultimo = agora;
            if (fis.deslizando) {
                cam.x += fis.vx * dt; cam.y += fis.vy * dt;
                const atrito = Math.exp(-dt / 220);
                fis.vx *= atrito; fis.vy *= atrito;
                fis.angAlvo = Math.max(-5, Math.min(5, fis.vx * 6));
                if (Math.hypot(fis.vx, fis.vy) < 0.015) { fis.deslizando = false; fis.vx = fis.vy = 0; fis.angAlvo = 0; }
                aplicarCam();
            }
            // mola: as pétalas se inclinam contra o movimento e voltam balançando
            const s = dt / 16;
            fis.angV += ((fis.angAlvo - fis.ang) * 0.06 - fis.angV * 0.18) * s;
            fis.ang += fis.angV * s;
            const parado = !fis.deslizando && !arrasto && Math.abs(fis.ang - fis.angAlvo) < 0.02 && Math.abs(fis.angV) < 0.005;
            if (parado) fis.ang = fis.angAlvo;
            gPetalas.setAttribute('transform', `rotate(${fis.ang.toFixed(3)})`);
            fis.raf = parado ? null : requestAnimationFrame(passo);
        };
        fis.raf = requestAnimationFrame(passo);
    }
    function pararInercia() { fis.deslizando = false; fis.vx = fis.vy = 0; }

    // um anel que se espalha a partir do nó clicado (o ramo abrindo/fechando)
    function onda(n) {
        const g = n && elsNo.get(n.id);
        if (!g || reduzMov) return;
        const c = el('circle', { class: 'fl-onda', r: TAM[n.tipo] || 7 });
        g.insertBefore(c, g.firstChild);
        c.addEventListener('animationend', () => c.remove(), { once: true });
        setTimeout(() => c.remove(), 1200);
    }

    // passar o mouse: acende o caminho até a pessoa e a pétala da área
    let trilha = [];
    function acender(n) {
        trilha.forEach(e => e.classList.remove('fl-trilha'));
        trilha = [];
        for (let m = n; m && m.pai; m = m.pai) {
            const l = elsLinha.get(m.id); if (l) trilha.push(l);
            const g = elsNo.get(m.pai.id); if (g && m.pai.tipo !== 'pessoa') trilha.push(g);
        }
        if (n && n.area) { const p = elsPetala.get(n.area.id); if (p) trilha.push(p); }
        trilha.forEach(e => e.classList.add('fl-trilha'));
    }
    gNos.addEventListener('pointerover', e => { const g = e.target.closest('.fl-no'); if (g && !(arrasto && arrasto.moveu)) acender(porId.get(g.dataset.id)); });
    gNos.addEventListener('pointerout', e => { const g = e.target.closest('.fl-no'); if (g && !g.contains(e.relatedTarget)) acender(null); });
    gNos.addEventListener('focusin', e => { const g = e.target.closest('.fl-no'); if (g) acender(porId.get(g.dataset.id)); });
    gNos.addEventListener('focusout', () => acender(null));
    // fim do pop de nascimento: libera o nó para o efeito de passar o mouse
    mundo.addEventListener('animationend', e => {
        if (e.animationName === 'fl-pop') e.target.closest('.fl-no')?.classList.remove('fl-nasce');
        else if (e.animationName === 'fl-surge' && e.target.classList.contains('fl-linha')) e.target.classList.remove('fl-nasce');
    });

    // arrastar com um dedo/mouse, pinça com dois; clique (sem arrastar) num nó abre/fecha
    const ponteiros = new Map();
    let arrasto = null, pinca = null, ultimoClique = { id: null, t: 0 };
    svg.addEventListener('pointerdown', e => {
        if (e.button !== 0 && e.pointerType === 'mouse') return;
        pararInercia();
        svg.setPointerCapture(e.pointerId);
        ponteiros.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (ponteiros.size === 1) {
            const g = e.target.closest('.fl-no');
            arrasto = { x: e.clientX, y: e.clientY, cx: cam.x, cy: cam.y, moveu: false, id: g ? g.dataset.id : null, ux: e.clientX, uy: e.clientY, ut: performance.now() };
        } else if (ponteiros.size === 2) {
            const [a, b] = [...ponteiros.values()];
            pinca = { d: Math.hypot(a.x - b.x, a.y - b.y), k: cam.k };
            arrasto = null;
        }
    });
    svg.addEventListener('pointermove', e => {
        if (!ponteiros.has(e.pointerId)) return;
        ponteiros.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pinca && ponteiros.size === 2) {
            const [a, b] = [...ponteiros.values()], r = svg.getBoundingClientRect();
            const d = Math.hypot(a.x - b.x, a.y - b.y);
            zoomEm((pinca.k * d / pinca.d) / cam.k, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top);
            return;
        }
        if (!arrasto) return;
        const dx = e.clientX - arrasto.x, dy = e.clientY - arrasto.y;
        if (!arrasto.moveu && Math.hypot(dx, dy) < 5) return;
        if (!arrasto.moveu && camAnim) { cancelAnimationFrame(camAnim); camAnim = null; arrasto.cx = cam.x; arrasto.cy = cam.y; }
        if (!arrasto.moveu) acender(null);
        arrasto.moveu = true;
        svg.classList.add('fl-arrastando');
        cam.x = arrasto.cx + dx; cam.y = arrasto.cy + dy;
        aplicarCam();
        // velocidade suavizada (px/ms) para a inércia e o balanço das pétalas
        const agora = performance.now(), dtm = Math.max(1, agora - arrasto.ut);
        fis.vx = fis.vx * 0.6 + ((e.clientX - arrasto.ux) / dtm) * 0.4;
        fis.vy = fis.vy * 0.6 + ((e.clientY - arrasto.uy) / dtm) * 0.4;
        arrasto.ux = e.clientX; arrasto.uy = e.clientY; arrasto.ut = agora;
        fis.angAlvo = Math.max(-5, Math.min(5, fis.vx * 6));
        moverFisica();
    });
    const soltar = e => {
        ponteiros.delete(e.pointerId);
        if (ponteiros.size < 2) pinca = null;
        svg.classList.remove('fl-arrastando');
        if (arrasto && arrasto.moveu) {
            // soltou: continua deslizando (se o gesto ainda estava em movimento) e as pétalas voltam balançando
            if (performance.now() - arrasto.ut > 90 || Math.hypot(fis.vx, fis.vy) < 0.05) fis.vx = fis.vy = 0;
            fis.vx = Math.max(-2.2, Math.min(2.2, fis.vx)); fis.vy = Math.max(-2.2, Math.min(2.2, fis.vy));
            fis.deslizando = !!(fis.vx || fis.vy);
            fis.angAlvo = 0;
            arrasto = null;
            moverFisica();
            return;
        }
        if (arrasto && !arrasto.moveu && e.type === 'pointerup') {
            if (arrasto.id) {
                const agora = performance.now();
                const duplo = ultimoClique.id === arrasto.id && agora - ultimoClique.t < 320;
                ultimoClique = { id: arrasto.id, t: agora };
                if (duplo) abrirLink(porId.get(arrasto.id)); else clicar(porId.get(arrasto.id));
            } else selecionar(null);
        }
        arrasto = null;
    };
    svg.addEventListener('pointerup', soltar);
    svg.addEventListener('pointercancel', soltar);
    svg.addEventListener('wheel', e => {
        e.preventDefault();
        const r = svg.getBoundingClientRect();
        const f = Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0015));
        zoomEm(f, e.clientX - r.left, e.clientY - r.top);
    }, { passive: false });
    svg.addEventListener('keydown', e => {
        const g = e.target.closest && e.target.closest('.fl-no');
        if (!g) return;
        const n = porId.get(g.dataset.id);
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey || e.shiftKey)) { e.preventDefault(); abrirLink(n); }
        else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); clicar(n); }
    });

    /* ------------------------------------------------------------ abrir/fechar, detalhe */
    let sel = null;
    function alternar(n, abrir) {
        if (!n.filhos.length) return;
        const vai = abrir == null ? !abertos.has(n.id) : abrir;
        if (vai) abertos.add(n.id);
        else { const fechar = m => { abertos.delete(m.id); m.filhos.forEach(fechar); }; fechar(n); abertos.add('eu'); }
        guardar();
        redesenhar();
    }
    function clicar(n) {
        if (!n) return;
        onda(n);
        if (n.tipo === 'pessoa') { selecionar(n); enquadrar(); return; }
        if (n.filhos.length) alternar(n);
        selecionar(n);
    }
    function abrirLink(n) { if (n && n.link) location.href = n.link; }

    const painel = $('.fl-detalhe');
    function caminho(n) { const c = []; let p = n.pai; while (p && p.tipo !== 'pessoa') { c.unshift(p.nome); p = p.pai; } return c; }
    function contar(n) {
        const r = { aba: 0, pasta: 0, cartao: 0, tarefa: 0, feita: 0 };
        const v = m => m.filhos.forEach(f => { r[f.tipo] = (r[f.tipo] || 0) + 1; if (f.status === 'concluida') r.feita++; v(f); });
        v(n);
        return r;
    }
    const plural = (n, um, varios) => n + ' ' + (n === 1 ? um : varios);
    function selecionar(n) {
        if (sel) elsNo.get(sel.id)?.classList.remove('fl-sel');
        sel = n;
        if (!n) { painel.hidden = true; return; }
        elsNo.get(n.id)?.classList.add('fl-sel');
        painel.hidden = false;
        painel.style.setProperty('--c', n.cor || 'var(--prim)');
        $('.fl-d-caminho').textContent = caminho(n).join(' › ');
        $('.fl-d-nome').textContent = n.nome;
        const r = contar(n), partes = [];
        if (r.aba) partes.push(plural(r.aba, 'aba', 'abas'));
        if (r.pasta) partes.push(plural(r.pasta, 'pasta', 'pastas'));
        if (r.cartao) partes.push(plural(r.cartao, 'cartão', 'cartões'));
        if (r.tarefa) partes.push(plural(r.tarefa, 'tarefa', 'tarefas') + (r.feita ? ` (${r.feita} concluída${r.feita === 1 ? '' : 's'})` : ''));
        $('.fl-d-info').textContent = [n.tipo === 'pessoa' ? 'Todo o seu workflow' : n.info, partes.join(' · ')].filter(Boolean).join(' — ');
        const abrir = $('.fl-d-abrir');
        abrir.hidden = !n.link;
        if (n.link) abrir.href = n.link;
        abrir.textContent = { area: 'Abrir a área', aba: 'Abrir a aba', pasta: 'Abrir a pasta', tarefa: 'Ver nas tarefas' }[n.tipo] || 'Abrir o cartão';
        const alt = $('.fl-d-alternar');
        alt.hidden = !n.filhos.length || n.tipo === 'pessoa';
        alt.textContent = abertos.has(n.id) ? 'Recolher' : 'Expandir';
    }
    $('.fl-d-alternar').addEventListener('click', () => { if (sel) { alternar(sel); selecionar(sel); } });
    $('.fl-d-fechar').addEventListener('click', () => selecionar(null));

    /* ------------------------------------------------------------ busca */
    const campo = $('.fl-busca');
    let achados = [], achadoI = -1;
    function aplicarBusca() {
        const q = semAcento(campo.value.trim());
        svg.classList.toggle('fl-buscando', !!q);
        const acesos = new Set();
        achados.forEach(n => { for (let m = n; m; m = m.pai) acesos.add(m.id); });
        elsNo.forEach((g, id) => { g.classList.toggle('fl-achado', !!q && achados.some(a => a.id === id)); g.classList.toggle('fl-aceso', acesos.has(id)); });
        elsLinha.forEach((l, id) => l.classList.toggle('fl-aceso', acesos.has(id)));
    }
    function buscar() {
        const q = semAcento(campo.value.trim());
        achados = q ? [...porId.values()].filter(n => n.tipo !== 'pessoa' && n.busca.includes(q)).slice(0, 300) : [];
        achadoI = -1;
        $('.fl-busca-n').textContent = q ? (achados.length ? plural(achados.length, 'resultado', 'resultados') : 'nada encontrado') : '';
        // abre o caminho até cada achado
        let mudou = false;
        achados.forEach(n => { for (let p = n.pai; p; p = p.pai) if (!abertos.has(p.id)) { abertos.add(p.id); mudou = true; } });
        if (mudou) { guardar(); redesenhar(); } else aplicarBusca();
    }
    let tBusca = 0;
    campo.addEventListener('input', () => { clearTimeout(tBusca); tBusca = setTimeout(() => { tBusca = 0; buscar(); }, 180); });
    campo.addEventListener('keydown', e => {
        if (e.key === 'Escape') { campo.value = ''; buscar(); return; }
        if (e.key === 'Enter' && tBusca) { clearTimeout(tBusca); tBusca = 0; buscar(); } // Enter logo depois de digitar
        if (e.key !== 'Enter' || !achados.length) return;
        e.preventDefault();
        achadoI = (achadoI + (e.shiftKey ? -1 : 1) + achados.length) % achados.length;
        const n = achados[achadoI];
        setTimeout(() => { centrarEm(n.id, 1.1); selecionar(n); }, 50);
        $('.fl-busca-n').textContent = `${achadoI + 1} de ${achados.length}`;
    });
    document.addEventListener('keydown', e => {
        if ((e.key === '/' || (e.key === 'f' && (e.ctrlKey || e.metaKey))) && document.activeElement !== campo && !/input|textarea|select/i.test(document.activeElement?.tagName || '')) {
            e.preventDefault(); campo.focus(); campo.select();
        }
    });

    /* ------------------------------------------------------------ barra e legenda */
    palco.addEventListener('click', e => {
        const b = e.target.closest('[data-acao]');
        if (!b) return;
        const acao = b.dataset.acao;
        if (acao === 'mais') zoomEm(1.3);
        else if (acao === 'menos') zoomEm(1 / 1.3);
        else if (acao === 'ajustar') enquadrar();
        else if (acao === 'tudo') { porId.forEach(n => { if (n.filhos.length) abertos.add(n.id); }); guardar(); redesenhar(); setTimeout(() => enquadrar(), 680); }
        else if (acao === 'recolher') { abertos = new Set(['eu', ...raiz.filhos.map(a => a.id)]); guardar(); redesenhar(); setTimeout(() => enquadrar(), 680); }
        else if (acao === 'inicio') { padrao(); guardar(); redesenhar(); setTimeout(() => enquadrar(), 680); }
        else if (acao === 'tela') { if (document.fullscreenElement) document.exitFullscreen(); else palco.requestFullscreen?.(); }
        else if (acao === 'area') {
            const a = porId.get(b.dataset.area);
            if (!a) return;
            if (!abertos.has(a.id)) { abertos.add(a.id); guardar(); redesenhar(); }
            selecionar(a);
            const ids = []; const v = n => { ids.push(n.id); visiveis(n).forEach(v); };
            setTimeout(() => { v(a); ids.push('eu'); enquadrar(ids); }, 680);
        }
    });
    document.addEventListener('fullscreenchange', () => setTimeout(() => enquadrar(null, 300), 80));

    function legenda(d) {
        const ul = $('.fl-legenda');
        ul.replaceChildren();
        let cartoes = 0, tarefas = 0, feitas = 0;
        d.areas.forEach(a => {
            const r = a.resumo || {};
            cartoes += r.cartoes || 0; tarefas += r.tarefas || 0; feitas += r.feitas || 0;
            const li = document.createElement('li');
            const b = document.createElement('button');
            b.type = 'button'; b.dataset.acao = 'area'; b.dataset.area = a.id;
            b.style.setProperty('--c', a.cor);
            b.title = 'Mostrar ' + a.nome;
            const bola = document.createElement('span'); bola.className = 'fl-leg-cor';
            const nome = document.createElement('strong'); nome.textContent = a.nome;
            const info = document.createElement('small');
            info.textContent = `${plural(r.abas || 0, 'aba', 'abas')} · ${plural(r.cartoes || 0, 'cartão', 'cartões')}`
                + (r.pastas ? ` · ${plural(r.pastas, 'pasta', 'pastas')}` : '') + ` · ${plural(r.tarefas || 0, 'tarefa', 'tarefas')}`;
            const barra = document.createElement('span'); barra.className = 'fl-leg-barra';
            const ench = document.createElement('i'); ench.style.width = (r.tarefas ? Math.round(100 * (r.feitas || 0) / r.tarefas) : 0) + '%';
            barra.title = r.tarefas ? `${r.feitas || 0} de ${r.tarefas} tarefas concluídas` : 'Sem tarefas';
            barra.append(ench);
            b.append(bola, nome, info, barra);
            li.append(b);
            ul.append(li);
        });
        $('.fl-total').textContent = `${plural(cartoes, 'cartão', 'cartões')} · ${plural(tarefas, 'tarefa', 'tarefas')}` + (tarefas ? ` · ${feitas} concluída${feitas === 1 ? '' : 's'}` : '');
    }

    /* ------------------------------------------------------------ início */
    new ResizeObserver(() => aplicarCam()).observe(svg);
    fetch(palco.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(d => {
            montar(d);
            legenda(d);
            $('.fl-carregando').hidden = true;
            const { w, h } = tamanho();
            cam.x = w / 2; cam.y = h / 2; cam.k = 0.5; aplicarCam();
            redesenhar(); // desabrocha a partir do centro
            calcular();
            enquadrar(null, reduzMov ? 0 : 900);
        })
        .catch(err => {
            console.error('Flower:', err);
            const c = $('.fl-carregando');
            c.hidden = false;
            c.textContent = 'Não deu para montar o seu Flower agora. Recarregue a página para tentar de novo.';
            c.classList.add('fl-erro');
        });
})();
