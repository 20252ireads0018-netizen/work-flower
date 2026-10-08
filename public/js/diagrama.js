/* Work Flower · área Diverso · editor de diagramas (mapa mental, BPMN, fluxogramas, diagramas relacionais/UML)
   Estende o diverso.js SEM alterá-lo: carregue depois dele e use x-data="diversoAbasDiagrama()".
   O bloco "mapa" continua com os mesmos dados (nos, tracos) e ganha: nos[].forma/w/h/campos e b.dados.lig (conectores).
   Mapas mentais antigos abrem normalmente (nós sem forma = "Tópico"; ligações pai→filho continuam). */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q) return;
    const { uid, num, r1 } = Q.util;
    const PALETA = Q.PALETA || ['#38bdf8', '#4ade80', '#f87171', '#fbbf24', '#a78bfa', '#f472b6'];
    const COR_OK = /^#[0-9a-fA-F]{6}$/;
    const limitar = (v, a, b) => Math.min(Math.max(v, a), b);
    const corOk = (c, pad = '#a78bfa') => (COR_OK.test(c || '') ? c : pad);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
    const MAX_NOS = 300, MAX_LIG = 600, MAX_TRACOS = 600, HIST = 80, SNAP = 10;

    /* ---------- catálogo de formas ---------- */
    const F = {
        mapa: { nome: 'Tópico (mapa mental)', w: 188, h: 38, g: 'Básico' },
        retangulo: { nome: 'Retângulo (processo)', w: 160, h: 70, g: 'Básico' },
        arredondado: { nome: 'Retângulo arredondado', w: 160, h: 70, g: 'Básico' },
        elipse: { nome: 'Elipse (estado)', w: 150, h: 80, g: 'Básico', elip: 1 },
        losango: { nome: 'Losango (decisão)', w: 140, h: 100, g: 'Básico', diam: 1 },
        paralelogramo: { nome: 'Entrada / saída', w: 170, h: 70, g: 'Básico' },
        cilindro: { nome: 'Banco de dados', w: 110, h: 100, g: 'Básico' },
        documento: { nome: 'Documento', w: 150, h: 90, g: 'Básico' },
        nota: { nome: 'Nota', w: 150, h: 90, g: 'Básico' },
        texto: { nome: 'Texto livre', w: 160, h: 40, g: 'Básico' },
        inicio: { nome: 'Evento de início', w: 44, h: 44, g: 'BPMN', fixo: 1, elip: 1, fora: 1 },
        intermediario: { nome: 'Evento intermediário', w: 44, h: 44, g: 'BPMN', fixo: 1, elip: 1, fora: 1 },
        fim: { nome: 'Evento de fim', w: 44, h: 44, g: 'BPMN', fixo: 1, elip: 1, fora: 1 },
        tarefa: { nome: 'Tarefa', w: 140, h: 70, g: 'BPMN' },
        subprocesso: { nome: 'Subprocesso', w: 160, h: 80, g: 'BPMN' },
        gateway_x: { nome: 'Gateway exclusivo (X)', w: 60, h: 60, g: 'BPMN', fixo: 1, diam: 1, fora: 1 },
        gateway_mais: { nome: 'Gateway paralelo (+)', w: 60, h: 60, g: 'BPMN', fixo: 1, diam: 1, fora: 1 },
        gateway_ou: { nome: 'Gateway inclusivo (O)', w: 60, h: 60, g: 'BPMN', fixo: 1, diam: 1, fora: 1 },
        raia: { nome: 'Raia / piscina', w: 520, h: 200, g: 'BPMN' },
        entidade: { nome: 'Entidade / classe / tabela', w: 190, h: 110, g: 'Relacional' },
    };
    const DEF_TXT = {
        mapa: 'Novo tópico', retangulo: 'Processo', arredondado: 'Etapa', elipse: 'Estado', losango: 'Decisão?', paralelogramo: 'Dados',
        cilindro: 'Banco de dados', documento: 'Documento', nota: 'Nota', texto: 'Texto', inicio: 'Início', intermediario: '', fim: 'Fim',
        tarefa: 'Tarefa', subprocesso: 'Subprocesso', gateway_x: '', gateway_mais: '', gateway_ou: '', raia: 'Raia', entidade: 'Entidade',
    };
    const DEF_COR = {
        inicio: '#4ade80', fim: '#f87171', intermediario: '#fbbf24', gateway_x: '#fbbf24', gateway_mais: '#fbbf24', gateway_ou: '#fbbf24',
        raia: '#64748b', nota: '#fbbf24', entidade: '#38bdf8', cilindro: '#a78bfa',
    };
    /* forma do elemento criado ao arrastar uma ligação até o vazio */
    const PROX = {
        inicio: 'tarefa', intermediario: 'tarefa', fim: 'tarefa', gateway_x: 'tarefa', gateway_mais: 'tarefa', gateway_ou: 'tarefa',
        raia: 'retangulo', texto: 'retangulo', nota: 'retangulo', losango: 'retangulo',
    };
    const GRUPOS = ['Básico', 'BPMN', 'Relacional'].map(g => ({
        grupo: g === 'Relacional' ? 'Relacional / UML' : g,
        itens: Object.entries(F).filter(([, d]) => d.g === g).map(([id, d]) => ({ id, nome: d.nome })),
    }));
    const MARCAS = [
        { id: 'nenhum', nome: 'Nenhum' }, { id: 'seta', nome: 'Seta cheia' }, { id: 'aberta', nome: 'Seta aberta' },
        { id: 'triangulo', nome: 'Triângulo vazio (herança)' }, { id: 'losango_cheio', nome: 'Losango cheio (composição)' },
        { id: 'losango', nome: 'Losango vazio (agregação)' }, { id: 'circulo', nome: 'Círculo (mensagem)' }, { id: 'ponto', nome: 'Ponto' },
        { id: 'um', nome: 'Um (ER)' }, { id: 'zero_um', nome: 'Zero ou um (ER)' }, { id: 'muitos', nome: 'Muitos (ER)' },
        { id: 'um_muitos', nome: 'Um ou muitos (ER)' }, { id: 'zero_muitos', nome: 'Zero ou muitos (ER)' },
    ];
    const PRESETS = [
        { id: 'fluxo', nome: 'Fluxo de sequência (BPMN)', ini: 'nenhum', fim: 'seta', traco: 'solido' },
        { id: 'mensagem', nome: 'Fluxo de mensagem (BPMN)', ini: 'circulo', fim: 'aberta', traco: 'tracejado' },
        { id: 'associacao', nome: 'Associação', ini: 'nenhum', fim: 'nenhum', traco: 'pontilhado' },
        { id: 'bidirecional', nome: 'Bidirecional', ini: 'seta', fim: 'seta', traco: 'solido' },
        { id: 'dependencia', nome: 'Dependência (UML)', ini: 'nenhum', fim: 'aberta', traco: 'tracejado' },
        { id: 'heranca', nome: 'Herança (UML)', ini: 'nenhum', fim: 'triangulo', traco: 'solido' },
        { id: 'composicao', nome: 'Composição (UML)', ini: 'losango_cheio', fim: 'nenhum', traco: 'solido' },
        { id: 'agregacao', nome: 'Agregação (UML)', ini: 'losango', fim: 'nenhum', traco: 'solido' },
        { id: 'er_1n', nome: 'Um para muitos (ER)', ini: 'um', fim: 'um_muitos', traco: 'solido' },
        { id: 'er_1n_op', nome: 'Um para muitos opcional (ER)', ini: 'um', fim: 'zero_muitos', traco: 'solido' },
        { id: 'er_11', nome: 'Um para um (ER)', ini: 'um', fim: 'um', traco: 'solido' },
        { id: 'er_nm', nome: 'Muitos para muitos (ER)', ini: 'um_muitos', fim: 'um_muitos', traco: 'solido' },
    ];
    const TRACOS = { solido: '', tracejado: '8 5', pontilhado: '2 5' };

    /* ---------- geometria ---------- */
    function listaCampos(n) {
        return String(n.campos == null ? '' : n.campos).split('\n').map(s => s.replace(/\s+$/, '')).filter(s => s.trim() !== '');
    }
    function campos(n) {
        return listaCampos(n).map(s => {
            const t = s.trim(), m = /^(PK|FK|UK)(?:\s*,\s*(PK|FK|UK))?\s+(.*)$/i.exec(t);
            return m ? { tag: (m[1] + (m[2] ? ',' + m[2] : '')).toUpperCase(), txt: m[3] } : { tag: '', txt: t };
        });
    }
    function geo(n) {
        const f = F[n.forma] ? n.forma : 'mapa', d = F[f];
        let w = d.fixo ? d.w : (num(n.w) > 0 ? num(n.w) : d.w);
        let h = d.fixo ? d.h : (num(n.h) > 0 ? num(n.h) : d.h);
        if (f === 'entidade') { h = 34 + Math.max(1, listaCampos(n).length) * 20 + 8; w = Math.max(110, w); }
        w = Math.max(w, 24); h = Math.max(h, 24);
        const x = num(n.x), y = num(n.y);
        return { x, y, w, h, f, d, cx: x + w / 2, cy: y + h / 2 };
    }
    function lado(g, l) {
        return l === 'd' ? [g.x + g.w, g.cy] : l === 'e' ? [g.x, g.cy] : l === 't' ? [g.cx, g.y] : [g.cx, g.y + g.h];
    }
    /** Ponto do contorno da forma na direção de (tx, ty). */
    function borda(g, tx, ty) {
        let dx = tx - g.cx, dy = ty - g.cy;
        if (!dx && !dy) dy = 1;
        const hw = g.w / 2, hh = g.h / 2;
        let k;
        if (g.d.elip) k = 1 / Math.sqrt((dx / hw) ** 2 + (dy / hh) ** 2);
        else if (g.d.diam) k = 1 / (Math.abs(dx) / hw + Math.abs(dy) / hh);
        else k = 1 / Math.max(Math.abs(dx) / hw, Math.abs(dy) / hh);
        return [g.cx + dx * k, g.cy + dy * k];
    }
    const un = (x, y) => { const m = Math.hypot(x, y) || 1; return [x / m, y / m]; };
    function arredondada(p, r) {
        let d = `M${r1(p[0][0])} ${r1(p[0][1])}`;
        for (let i = 1; i < p.length - 1; i++) {
            const a = p[i - 1], b = p[i], c = p[i + 1];
            const l1 = Math.hypot(b[0] - a[0], b[1] - a[1]), l2 = Math.hypot(c[0] - b[0], c[1] - b[1]), k = Math.min(r, l1 / 2, l2 / 2);
            if (k < 1) { d += `L${r1(b[0])} ${r1(b[1])}`; continue; }
            const p1 = [b[0] - (b[0] - a[0]) / l1 * k, b[1] - (b[1] - a[1]) / l1 * k];
            const p2 = [b[0] + (c[0] - b[0]) / l2 * k, b[1] + (c[1] - b[1]) / l2 * k];
            d += `L${r1(p1[0])} ${r1(p1[1])}Q${r1(b[0])} ${r1(b[1])} ${r1(p2[0])} ${r1(p2[1])}`;
        }
        const u = p[p.length - 1];
        return d + `L${r1(u[0])} ${r1(u[1])}`;
    }
    /** Caminho da ligação entre os elementos A e B. us/ue: direção com que a linha "chega" ao início e ao fim. */
    function rota(A, B, estilo, self) {
        if (self) {
            const xr = A.x + A.w, s = [xr, A.y + A.h * 0.3], e = [xr, A.y + A.h * 0.7];
            return { d: `M${r1(s[0])} ${r1(s[1])}C${r1(xr + 55)} ${r1(s[1] - 28)} ${r1(xr + 55)} ${r1(e[1] + 28)} ${r1(e[0])} ${r1(e[1])}`, s, e, us: [-1, 0], ue: [-1, 0], mid: [xr + 41, A.cy] };
        }
        const dx = B.cx - A.cx, dy = B.cy - A.cy;
        if (estilo !== 'curva' && estilo !== 'ortogonal') {
            const s = borda(A, B.cx, B.cy), e = borda(B, A.cx, A.cy), u = un(e[0] - s[0], e[1] - s[1]);
            return { d: `M${r1(s[0])} ${r1(s[1])}L${r1(e[0])} ${r1(e[1])}`, s, e, us: [-u[0], -u[1]], ue: u, mid: [(s[0] + e[0]) / 2, (s[1] + e[1]) / 2] };
        }
        const hor = Math.abs(dx) >= Math.abs(dy);
        const s = hor ? lado(A, dx >= 0 ? 'd' : 'e') : lado(A, dy >= 0 ? 'b' : 't');
        const e = hor ? lado(B, dx >= 0 ? 'e' : 'd') : lado(B, dy >= 0 ? 't' : 'b');
        if (estilo === 'curva') {
            let c1, c2;
            if (hor) { const k = Math.max(40, Math.abs(e[0] - s[0]) / 2), sg = dx >= 0 ? 1 : -1; c1 = [s[0] + sg * k, s[1]]; c2 = [e[0] - sg * k, e[1]]; }
            else { const k = Math.max(40, Math.abs(e[1] - s[1]) / 2), sg = dy >= 0 ? 1 : -1; c1 = [s[0], s[1] + sg * k]; c2 = [e[0], e[1] - sg * k]; }
            return {
                d: `M${r1(s[0])} ${r1(s[1])}C${r1(c1[0])} ${r1(c1[1])} ${r1(c2[0])} ${r1(c2[1])} ${r1(e[0])} ${r1(e[1])}`, s, e,
                us: un(s[0] - c1[0], s[1] - c1[1]), ue: un(e[0] - c2[0], e[1] - c2[1]),
                mid: [(s[0] + 3 * c1[0] + 3 * c2[0] + e[0]) / 8, (s[1] + 3 * c1[1] + 3 * c2[1] + e[1]) / 8],
            };
        }
        let pts;
        if (hor) { const mx = (s[0] + e[0]) / 2; pts = Math.abs(s[1] - e[1]) < 1 ? [s, e] : [s, [mx, s[1]], [mx, e[1]], e]; }
        else { const my = (s[1] + e[1]) / 2; pts = Math.abs(s[0] - e[0]) < 1 ? [s, e] : [s, [s[0], my], [e[0], my], e]; }
        const n = pts.length;
        return {
            d: arredondada(pts, 8), s, e,
            us: un(pts[0][0] - pts[1][0], pts[0][1] - pts[1][1]), ue: un(pts[n - 1][0] - pts[n - 2][0], pts[n - 1][1] - pts[n - 2][1]),
            mid: n === 4 ? [(pts[1][0] + pts[2][0]) / 2, (pts[1][1] + pts[2][1]) / 2] : [(s[0] + e[0]) / 2, (s[1] + e[1]) / 2],
        };
    }

    /* ---------- desenho das formas e das ligações ---------- */
    function pintura(exp) {
        return exp
            ? { fundo: '#ffffff', tinta: '#111827', suave: '#6b7280', sup: '#ffffff' }
            : { fundo: 'var(--superficie-2)', tinta: 'var(--tinta)', suave: 'var(--tinta-2)', sup: 'var(--superficie)' };
    }
    function formaSvg(n, exp) {
        const g = geo(n), c = corOk(n.cor), P = pintura(exp), w = g.w, h = g.h, hw = w / 2, hh = h / 2;
        const tr = `stroke:${c};stroke-width:1.6;stroke-linejoin:round;`;
        const duo = (tag, at) => `<${tag} ${at} style="fill:${P.fundo};stroke:none"/><${tag} ${at} style="${tr}fill:${c};fill-opacity:.14"/>`;
        const linha = d => `<path d="${d}" style="${tr}fill:none"/>`;
        const rombo = `${r1(hw)},1 ${w - 1},${r1(hh)} ${r1(hw)},${h - 1} 1,${r1(hh)}`;
        const s = Math.min(w, h) * 0.2;
        switch (g.f) {
            case 'mapa': return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="8"`);
            case 'retangulo': return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="3"`);
            case 'arredondado': return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="16"`);
            case 'tarefa': return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="10"`);
            case 'subprocesso':
                return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="10"`)
                    + `<rect x="${r1(hw - 7)}" y="${h - 18}" width="14" height="12" rx="1" style="fill:none;stroke:${c};stroke-width:1.2"/>`
                    + `<path d="M${r1(hw - 4)} ${h - 12}h8M${r1(hw)} ${h - 16}v8" style="fill:none;stroke:${c};stroke-width:1.2"/>`;
            case 'elipse': return duo('ellipse', `cx="${r1(hw)}" cy="${r1(hh)}" rx="${r1(hw - 1)}" ry="${r1(hh - 1)}"`);
            case 'losango': return duo('polygon', `points="${rombo}"`);
            case 'paralelogramo': return duo('polygon', `points="${r1(w * 0.15)},1 ${w - 1},1 ${r1(w * 0.85)},${h - 1} 1,${h - 1}"`);
            case 'cilindro': {
                const ry = Math.min(14, h / 5);
                return duo('path', `d="M1 ${r1(ry)}A${r1(hw - 1)} ${r1(ry)} 0 0 1 ${w - 1} ${r1(ry)}L${w - 1} ${r1(h - ry)}A${r1(hw - 1)} ${r1(ry)} 0 0 1 1 ${r1(h - ry)}Z"`)
                    + linha(`M1 ${r1(ry)}A${r1(hw - 1)} ${r1(ry)} 0 0 0 ${w - 1} ${r1(ry)}`);
            }
            case 'documento':
                return duo('path', `d="M1 1H${w - 1}V${h - 10}C${r1(w * 0.85)} ${h + 4} ${r1(w * 0.65)} ${h + 4} ${r1(w * 0.5)} ${h - 10}S${r1(w * 0.15)} ${h - 24} 1 ${h - 10}Z"`);
            case 'nota':
                return duo('path', `d="M1 1H${w - 17}L${w - 1} 17V${h - 1}H1Z"`) + linha(`M${w - 17} 1V17H${w - 1}`);
            case 'texto': return '';
            case 'inicio': return duo('circle', `cx="${r1(hw)}" cy="${r1(hh)}" r="${r1(hw - 2)}"`);
            case 'intermediario':
                return duo('circle', `cx="${r1(hw)}" cy="${r1(hh)}" r="${r1(hw - 2)}"`)
                    + `<circle cx="${r1(hw)}" cy="${r1(hh)}" r="${r1(hw - 6)}" style="fill:none;stroke:${c};stroke-width:1.2"/>`;
            case 'fim':
                return `<circle cx="${r1(hw)}" cy="${r1(hh)}" r="${r1(hw - 3)}" style="fill:${P.fundo};stroke:none"/>`
                    + `<circle cx="${r1(hw)}" cy="${r1(hh)}" r="${r1(hw - 3)}" style="stroke:${c};stroke-width:4;fill:${c};fill-opacity:.14"/>`;
            case 'gateway_x':
                return duo('polygon', `points="${rombo}"`)
                    + `<path d="M${r1(g.cx - s)} ${r1(g.cy - s)}L${r1(g.cx + s)} ${r1(g.cy + s)}M${r1(g.cx + s)} ${r1(g.cy - s)}L${r1(g.cx - s)} ${r1(g.cy + s)}" style="fill:none;stroke:${c};stroke-width:3;stroke-linecap:round"/>`;
            case 'gateway_mais':
                return duo('polygon', `points="${rombo}"`)
                    + `<path d="M${r1(g.cx - s)} ${r1(g.cy)}H${r1(g.cx + s)}M${r1(g.cx)} ${r1(g.cy - s)}V${r1(g.cy + s)}" style="fill:none;stroke:${c};stroke-width:3;stroke-linecap:round"/>`;
            case 'gateway_ou':
                return duo('polygon', `points="${rombo}"`)
                    + `<circle cx="${r1(g.cx)}" cy="${r1(g.cy)}" r="${r1(s * 1.1)}" style="fill:none;stroke:${c};stroke-width:2.4"/>`;
            case 'raia':
                return `<rect x="1" y="1" width="${w - 2}" height="${h - 2}" rx="4" style="fill:${c};fill-opacity:.05;stroke:${c};stroke-width:1.4"/>`
                    + `<rect x="1" y="1" width="29" height="${h - 2}" rx="4" style="fill:${c};fill-opacity:.16;stroke:none"/>`
                    + `<path d="M30 1V${h - 1}" style="stroke:${c};stroke-width:1.2;fill:none"/>`;
            case 'entidade':
                return duo('rect', `x="1" y="1" width="${w - 2}" height="${h - 2}" rx="6"`)
                    + `<path d="M1 34V7a6 6 0 0 1 6-6H${w - 7}a6 6 0 0 1 6 6V34Z" style="fill:${c};fill-opacity:.24;stroke:none"/>`
                    + `<path d="M1 34H${w - 1}" style="stroke:${c};stroke-width:1.2;fill:none"/>`;
            default: return '';
        }
    }
    /** Ponta da ligação. p = ponto na borda; u = direção (unitária) em que a linha chega ao ponto. */
    function marca(tipo, p, u, c, w, P) {
        const L = 9 + w * 1.2, W = 4.5 + w * 0.8, nx = -u[1], ny = u[0];
        const pt = (a, b) => [p[0] - u[0] * a + nx * b, p[1] - u[1] * a + ny * b];
        const f = q => r1(q[0]) + ',' + r1(q[1]);
        const st = `stroke:${c};stroke-width:${w};stroke-linejoin:round;stroke-linecap:round;`;
        const barra = a => `M${f(pt(a, W))}L${f(pt(a, -W))}`;
        const pata = `M${f(pt(12, 0))}L${f(pt(0, W))}M${f(pt(12, 0))}L${f(pt(0, -W))}`;
        switch (tipo) {
            case 'seta': return `<polygon points="${f(p)} ${f(pt(L, W))} ${f(pt(L, -W))}" style="${st}fill:${c}"/>`;
            case 'aberta': return `<polyline points="${f(pt(L, W))} ${f(p)} ${f(pt(L, -W))}" style="${st}fill:none"/>`;
            case 'triangulo': return `<polygon points="${f(p)} ${f(pt(L + 2, W + 1))} ${f(pt(L + 2, -W - 1))}" style="${st}fill:${P.sup}"/>`;
            case 'losango': return `<polygon points="${f(p)} ${f(pt(L * 0.6, W))} ${f(pt(L * 1.2, 0))} ${f(pt(L * 0.6, -W))}" style="${st}fill:${P.sup}"/>`;
            case 'losango_cheio': return `<polygon points="${f(p)} ${f(pt(L * 0.6, W))} ${f(pt(L * 1.2, 0))} ${f(pt(L * 0.6, -W))}" style="${st}fill:${c}"/>`;
            case 'circulo': { const q = pt(5, 0); return `<circle cx="${r1(q[0])}" cy="${r1(q[1])}" r="5" style="${st}fill:${P.sup}"/>`; }
            case 'ponto': { const q = pt(4, 0); return `<circle cx="${r1(q[0])}" cy="${r1(q[1])}" r="3.6" style="fill:${c};stroke:none"/>`; }
            case 'um': return `<path d="${barra(7)}${barra(12)}" style="${st}fill:none"/>`;
            case 'zero_um': { const q = pt(16, 0); return `<path d="${barra(7)}" style="${st}fill:none"/><circle cx="${r1(q[0])}" cy="${r1(q[1])}" r="4.2" style="${st}fill:${P.sup}"/>`; }
            case 'muitos': return `<path d="${pata}" style="${st}fill:none"/>`;
            case 'um_muitos': return `<path d="${pata}${barra(16)}" style="${st}fill:none"/>`;
            case 'zero_muitos': { const q = pt(20, 0); return `<path d="${pata}" style="${st}fill:none"/><circle cx="${r1(q[0])}" cy="${r1(q[1])}" r="4.2" style="${st}fill:${P.sup}"/>`; }
            default: return '';
        }
    }
    /** Todas as ligações (pai→filho dos mapas mentais + conectores) em um único SVG. selId = conector selecionado. */
    function ligacoesSvg(b, selId, exp) {
        const P = pintura(exp), nos = b.dados.nos || [], mapa = new Map(nos.map(n => [n.id, geo(n)]));
        let out = '';
        nos.forEach(n => {
            if (!n.pai) return;
            const A = mapa.get(n.pai), B = mapa.get(n.id);
            if (!A || !B) return;
            out += `<path d="${rota(A, B, 'curva').d}" style="fill:none;stroke:${P.suave};stroke-width:1.6;stroke-linecap:round"/>`;
        });
        (b.dados.lig || []).forEach(l => {
            const A = mapa.get(l.de), B = mapa.get(l.para);
            if (!A || !B) return;
            const r = rota(A, B, l.estilo, l.de === l.para);
            const c = COR_OK.test(l.cor || '') ? l.cor : P.suave, w = limitar(num(l.w) || 1.8, 1, 6);
            const dash = TRACOS[l.traco] ? `stroke-dasharray:${TRACOS[l.traco]};` : '';
            const ini = l.ini == null ? 'nenhum' : l.ini, fim = l.fim == null ? 'seta' : l.fim;
            let g = `<g data-lig="${esc(l.id)}">`;
            if (!exp && selId === l.id) g += `<path d="${r.d}" style="fill:none;stroke:var(--prim);stroke-width:${w + 7};stroke-opacity:.35;stroke-linecap:round"/>`;
            if (!exp) g += `<path d="${r.d}" style="fill:none;stroke:transparent;stroke-width:14;pointer-events:stroke;cursor:pointer"/>`;
            g += `<path d="${r.d}" style="fill:none;stroke:${c};stroke-width:${w};stroke-linecap:round;${dash}pointer-events:none"/>`;
            g += marca(ini, r.s, r.us, c, w, P) + marca(fim, r.e, r.ue, c, w, P);
            if (l.rot) {
                const tw = Math.max(22, String(l.rot).length * 6.6 + 12);
                g += `<rect x="${r1(r.mid[0] - tw / 2)}" y="${r1(r.mid[1] - 9)}" width="${r1(tw)}" height="18" rx="4" style="fill:${P.sup};fill-opacity:.94;stroke:none${exp ? '' : ';pointer-events:all;cursor:pointer'}"/>`
                    + `<text x="${r1(r.mid[0])}" y="${r1(r.mid[1])}" text-anchor="middle" dominant-baseline="central" style="font-size:11.5px;font-family:'DM Sans',Arial,sans-serif;fill:${P.tinta};pointer-events:none">${esc(l.rot)}</text>`;
            }
            out += g + '</g>';
        });
        return out;
    }

    /* ---------- exportação ---------- */
    function quebrar(t, max) {
        const out = [];
        String(t || '').split('\n').forEach(par => {
            let l = '';
            par.split(/\s+/).forEach(pal => {
                if (!pal) return;
                if (l && (l + ' ' + pal).length > max) { out.push(l); l = pal; } else l = (l ? l + ' ' : '') + pal;
            });
            out.push(l);
        });
        return out;
    }
    function textoExport(n, g) {
        const fam = `font-family="DM Sans, Arial, sans-serif" font-size="13" fill="#111827"`;
        const max = Math.max(4, Math.floor((g.w - 16) / 7));
        const T = (x, y, s, extra = '') => `<text x="${r1(x)}" y="${r1(y)}" ${fam} text-anchor="middle" dominant-baseline="central" ${extra}>${esc(s)}</text>`;
        if (g.f === 'entidade') {
            let o = T(g.w / 2, 18, n.t || '', 'font-weight="700"');
            campos(n).forEach((c, i) => {
                const y = 34 + 4 + i * 20 + 10;
                o += `<text x="10" y="${y}" ${fam} font-size="12" dominant-baseline="central">${c.tag ? `<tspan font-weight="700" font-size="10">${esc(c.tag)} </tspan>` : ''}${esc(c.txt)}</text>`;
            });
            return o;
        }
        if (!String(n.t || '').trim()) return '';
        if (g.f === 'raia') return `<g transform="translate(15 ${r1(g.h / 2)}) rotate(-90)">${T(0, 0, n.t)}</g>`;
        const linhas = quebrar(n.t, g.d.fora ? 18 : max);
        if (g.d.fora) return linhas.map((s, i) => T(g.w / 2, g.h + 12 + i * 15, s)).join('');
        const y0 = g.h / 2 - (linhas.length - 1) * 8;
        return linhas.map((s, i) => T(g.w / 2, y0 + i * 16, s)).join('');
    }
    function baixar(blob, nome) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = nome;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    }
    function caminhoPontos(p) {
        if (p.length === 1) return `M${p[0][0]} ${p[0][1]}l0.1 0`;
        let d = `M${p[0][0]} ${p[0][1]}`;
        if (p.length === 2) return d + `L${p[1][0]} ${p[1][1]}`;
        for (let i = 1; i < p.length - 1; i++) d += `Q${p[i][0]} ${p[i][1]} ${r1((p[i][0] + p[i + 1][0]) / 2)} ${r1((p[i][1] + p[i + 1][1]) / 2)}`;
        const u = p[p.length - 1];
        return d + `L${u[0]} ${u[1]}`;
    }

    /* ---------- métodos mesclados no diversoAbas ---------- */
    function metodos() {
        return {
            ddFormasGrupos: GRUPOS, ddMarcas: MARCAS, ddPresets: PRESETS,
            ddEstilos: [{ id: 'reta', nome: 'Reta' }, { id: 'curva', nome: 'Curva' }, { id: 'ortogonal', nome: 'Em ângulo (ortogonal)' }],
            ddTracos: [{ id: 'solido', nome: 'Sólida' }, { id: 'tracejado', nome: 'Tracejada' }, { id: 'pontilhado', nome: 'Pontilhada' }],

            /* estado de tela do diagrama (não é salvo) */
            dd(b) {
                const p = this.pen(b);
                if (!p.dd) p.dd = { sel: [], selLig: null, edit: null, z: 1, grade: true, snap: true, menu: false, hist: [], refaz: [], liga: null, caixa: null, ultima: 'mapa' };
                return p.dd;
            },
            ddNos(b) { return [...(b.dados.nos || [])].sort((a, c) => (a.forma === 'raia' ? 0 : 1) - (c.forma === 'raia' ? 0 : 1)); },
            ddGeo(n) { return geo(n); },
            ddCampos(n) { return campos(n); },
            ddNoEstilo(n) {
                const g = geo(n);
                return `left:${g.x}px;top:${g.y}px;width:${g.w}px;height:${g.h}px;z-index:${g.f === 'raia' ? 1 : 4};--no:${corOk(n.cor)};`;
            },
            ddClasse(b, n) {
                const d = this.dd(b), sel = d.sel.includes(n.id), f = F[n.forma] ? n.forma : 'mapa';
                return ['f-' + f, sel ? 'sel' : '', sel && d.sel.length === 1 ? 'unico' : '', F[f].fixo ? 'fixo' : '',
                    d.liga && d.liga.alvo === n.id ? 'alvo' : '', d.edit === n.id ? 'edit' : ''].filter(Boolean).join(' ');
            },
            ddFormaSvg(n) { return formaSvg(n, false); },
            ddLigSvg(b) { return ligacoesSvg(b, this.dd(b).selLig, false); },
            ddSelNos(b) { const s = this.dd(b).sel; return (b.dados.nos || []).filter(n => s.includes(n.id)); },
            ddNoSel(b) { const l = this.ddSelNos(b); return l.length === 1 ? l[0] : null; },
            ddLigSel(b) { const id = this.dd(b).selLig; return id ? (b.dados.lig || []).find(l => l.id === id) || null : null; },
            ddLiveD(b) { const l = this.dd(b).liga; return l ? `M${r1(l.x1)} ${r1(l.y1)}L${r1(l.x2)} ${r1(l.y2)}` : ''; },
            ddCaixaEstilo(b) { const k = this.dd(b).caixa; return k ? `left:${k.x}px;top:${k.y}px;width:${k.w}px;height:${k.h}px;` : ''; },
            ddSnapV(b, v) { return this.dd(b).snap ? Math.round(v / SNAP) * SNAP : Math.round(v); },
            ddXY(lona, ev, z) { const r = lona.getBoundingClientRect(); return [(ev.clientX - r.left) / z, (ev.clientY - r.top) / z]; },

            /* ---------- histórico (desfazer / refazer) ---------- */
            ddSnapshot(b) { return JSON.stringify({ nos: b.dados.nos || [], lig: b.dados.lig || [], tracos: b.dados.tracos || [] }); },
            ddEmpurrar(b, json) { const d = this.dd(b); d.hist.push(json); if (d.hist.length > HIST) d.hist.shift(); d.refaz = []; },
            ddGravar(b) { this.ddEmpurrar(b, this.ddSnapshot(b)); },
            ddRestaurar(b, json) {
                const p = JSON.parse(json), d = this.dd(b);
                b.dados.nos = p.nos; b.dados.lig = p.lig; b.dados.tracos = p.tracos;
                const ids = new Set(p.nos.map(n => n.id));
                d.sel = d.sel.filter(id => ids.has(id));
                if (d.selLig && !p.lig.some(l => l.id === d.selLig)) d.selLig = null;
                d.edit = null;
            },
            ddDesfazer(b) { const d = this.dd(b); if (!d.hist.length) return; d.refaz.push(this.ddSnapshot(b)); this.ddRestaurar(b, d.hist.pop()); },
            ddRefazer(b) { const d = this.dd(b); if (!d.refaz.length) return; d.hist.push(this.ddSnapshot(b)); this.ddRestaurar(b, d.refaz.pop()); },

            /* ---------- criar elementos ---------- */
            ddOcupado(b, x, y, w, h) {
                return (b.dados.nos || []).some(n => { const g = geo(n); return g.f !== 'raia' && x < g.x + g.w && x + w > g.x && y < g.y + g.h && y + h > g.y; });
            },
            ddAdicionar(b, forma, ev) {
                const def = F[forma], d = this.dd(b);
                if (!def) return;
                if ((b.dados.nos || []).length >= MAX_NOS) { this.aviso(`Limite de ${MAX_NOS} elementos neste diagrama.`); return; }
                const cont = ev && ev.target && ev.target.closest ? ev.target.closest('[data-dd]')?.querySelector('.wf-mapa-col') : null;
                let x = this.ddSnapV(b, (cont ? cont.scrollLeft / d.z : 0) + 40), y = this.ddSnapV(b, (cont ? cont.scrollTop / d.z : 0) + 40);
                for (let i = 0; i < 40 && forma !== 'raia' && this.ddOcupado(b, x, y, def.w, def.h); i++) { x += 30; y += 30; }
                this.ddGravar(b);
                const n = this.ddNovoNo(forma, x, y);
                b.dados.nos.push(n);
                d.sel = [n.id]; d.selLig = null; d.menu = false; d.ultima = forma;
            },
            ddNovoNo(forma, x, y, extra = {}) {
                const def = F[forma] || F.mapa;
                const n = {
                    id: uid(), t: DEF_TXT[forma] != null ? DEF_TXT[forma] : def.nome, x: Math.max(0, x), y: Math.max(0, y), pai: null,
                    cor: DEF_COR[forma] || PALETA[0], forma, w: def.w, h: def.h, ...extra,
                };
                if (forma === 'entidade') n.campos = 'PK id\nnome';
                return n;
            },
            ddLonaDbl(e, b) {
                if (e.target !== e.currentTarget || this.pen(b).on) return;
                if ((b.dados.nos || []).length >= MAX_NOS) { this.aviso(`Limite de ${MAX_NOS} elementos neste diagrama.`); return; }
                const d = this.dd(b), forma = F[d.ultima] && d.ultima !== 'raia' ? d.ultima : 'mapa', def = F[forma];
                const [px, py] = this.ddXY(e.currentTarget, e, d.z);
                this.ddGravar(b);
                const n = this.ddNovoNo(forma, this.ddSnapV(b, px - def.w / 2), this.ddSnapV(b, py - def.h / 2));
                b.dados.nos.push(n);
                d.sel = [n.id]; d.selLig = null; d.edit = n.id;
            },
            /** Elemento novo ligado a `src` (arrastar a ligação até o vazio, ou Tab). px/py = centro desejado. */
            ddCriarLigado(b, src, px, py) {
                if ((b.dados.nos || []).length >= MAX_NOS) { this.aviso(`Limite de ${MAX_NOS} elementos neste diagrama.`); return null; }
                const gs = geo(src), d = this.dd(b), mapaTipo = gs.f === 'mapa';
                const forma = PROX[gs.f] || gs.f, def = F[forma];
                let x, y;
                if (px != null) { x = this.ddSnapV(b, px - def.w / 2); y = this.ddSnapV(b, py - def.h / 2); }
                else {
                    x = this.ddSnapV(b, gs.x + gs.w + 70); y = this.ddSnapV(b, gs.y + (gs.h - def.h) / 2);
                    for (let i = 0; i < 40 && this.ddOcupado(b, x, y, def.w, def.h); i++) y += def.h + 14;
                }
                this.ddGravar(b);
                const n = this.ddNovoNo(forma, x, y, { cor: src.cor || PALETA[0] });
                if (forma === 'mapa') n.t = 'Novo nó';
                if (mapaTipo) n.pai = src.id;
                b.dados.nos.push(n);
                if (!mapaTipo) this.ddNovaLig(b, src.id, n.id, true);
                d.sel = [n.id]; d.selLig = null;
                return n;
            },
            ddNovaLig(b, de, para, semHist) {
                b.dados.lig ??= [];
                if (b.dados.lig.length >= MAX_LIG) { this.aviso(`Limite de ${MAX_LIG} conectores neste diagrama.`); return null; }
                if (!semHist) this.ddGravar(b);
                const l = { id: uid(), de, para, rot: '', estilo: b.dados.ligPadrao || 'ortogonal', traco: 'solido', ini: 'nenhum', fim: 'seta', cor: '', w: 1.8 };
                b.dados.lig.push(l);
                return l;
            },
            ddFilho(b, n) {
                if (geo(n).f === 'mapa') { this.mmAdd(b, n.id); return; }
                const novo = this.ddCriarLigado(b, n);
                if (novo) this.dd(b).edit = novo.id;
            },

            /* compatibilidade com o mapa mental original */
            mmAdd(b, paiId) {
                const pai = (b.dados.nos || []).find(n => n.id === paiId);
                if (!pai) return;
                if (b.dados.nos.length >= MAX_NOS) { this.aviso(`Limite de ${MAX_NOS} elementos neste diagrama.`); return; }
                this.ddGravar(b);
                const g = geo(pai), filhos = b.dados.nos.filter(n => n.pai === pai.id).length, def = F.mapa;
                const x = this.ddSnapV(b, g.x + g.w + 60);
                let y = this.ddSnapV(b, g.y + filhos * (def.h + 14));
                for (let i = 0; i < 60 && this.ddOcupado(b, x, y, def.w, def.h); i++) y += def.h + 14;
                const ci = PALETA.indexOf(pai.cor);
                const n = { id: uid(), t: 'Novo nó', x, y, pai: pai.id, cor: PALETA[(ci + 1 + filhos) % PALETA.length] || PALETA[0], forma: 'mapa' };
                b.dados.nos.push(n);
                const d = this.dd(b);
                d.sel = [n.id]; d.selLig = null; d.edit = n.id;
            },
            mmRem(b, id) { this.ddGravar(b); this.ddRemoverNos(b, [id]); },
            mmCor(n) { const i = PALETA.indexOf(n.cor); n.cor = PALETA[(i + 1) % PALETA.length]; },
            mmMover(e, b, n) { this.ddNoDown(e, b, n); },
            mmLinhas(b) {
                const mapa = new Map((b.dados.nos || []).map(n => [n.id, geo(n)]));
                return (b.dados.nos || []).filter(n => n.pai && mapa.has(n.pai)).map(n => rota(mapa.get(n.pai), mapa.get(n.id), 'curva').d).join('');
            },
            mmTam(b) {
                let w = 0, h = 0;
                (b.dados.nos || []).forEach(n => { const g = geo(n); w = Math.max(w, g.x + g.w + 80); h = Math.max(h, g.y + g.h + 80); });
                (b.dados.tracos || []).forEach(t => { w = Math.max(w, (t.m?.[0] || 0) + 30); h = Math.max(h, (t.m?.[1] || 0) + 30); });
                return { w: Math.max(w, 680), h: Math.max(h, 380) };
            },

            /* ---------- remover / duplicar / ordem / alinhar ---------- */
            ddRemoverNos(b, ids) {
                const set = new Set(ids), nos = b.dados.nos || [], porId = new Map(nos.map(n => [n.id, n]));
                nos.forEach(n => {
                    if (set.has(n.id)) return;
                    let p = n.pai, g = 0;
                    while (p && set.has(p) && g++ < 60) p = porId.get(p)?.pai ?? null;
                    if ((p || null) !== (n.pai || null)) n.pai = p || null;
                });
                b.dados.nos = nos.filter(n => !set.has(n.id));
                b.dados.lig = (b.dados.lig || []).filter(l => !set.has(l.de) && !set.has(l.para));
                const d = this.dd(b);
                d.sel = d.sel.filter(id => !set.has(id));
            },
            ddRemoverSel(b) {
                const d = this.dd(b);
                if (d.selLig) { this.ddGravar(b); b.dados.lig = (b.dados.lig || []).filter(l => l.id !== d.selLig); d.selLig = null; return; }
                if (!d.sel.length) return;
                this.ddGravar(b);
                this.ddRemoverNos(b, d.sel.slice());
                d.sel = [];
            },
            ddRemoverLig(b) { const d = this.dd(b); if (!d.selLig) return; this.ddGravar(b); b.dados.lig = (b.dados.lig || []).filter(l => l.id !== d.selLig); d.selLig = null; },
            ddDuplicar(b) {
                const d = this.dd(b), orig = this.ddSelNos(b);
                if (!orig.length) return;
                if (b.dados.nos.length + orig.length > MAX_NOS) { this.aviso(`Limite de ${MAX_NOS} elementos neste diagrama.`); return; }
                this.ddGravar(b);
                const mapa = new Map(), novos = [];
                orig.forEach(n => {
                    const c = JSON.parse(JSON.stringify(n));
                    c.id = uid(); c.x = num(n.x) + 24; c.y = num(n.y) + 24;
                    mapa.set(n.id, c.id); novos.push(c);
                });
                novos.forEach(c => { c.pai = c.pai && mapa.has(c.pai) ? mapa.get(c.pai) : null; });
                b.dados.nos.push(...novos);
                (b.dados.lig || []).slice().forEach(l => {
                    if (mapa.has(l.de) && mapa.has(l.para) && b.dados.lig.length < MAX_LIG)
                        b.dados.lig.push({ ...JSON.parse(JSON.stringify(l)), id: uid(), de: mapa.get(l.de), para: mapa.get(l.para) });
                });
                d.sel = novos.map(c => c.id); d.selLig = null;
            },
            ddOrdem(b, onde) {
                const sel = new Set(this.dd(b).sel);
                if (!sel.size) return;
                this.ddGravar(b);
                const nos = b.dados.nos, dentro = nos.filter(n => sel.has(n.id)), fora = nos.filter(n => !sel.has(n.id));
                b.dados.nos = onde === 'frente' ? [...fora, ...dentro] : [...dentro, ...fora];
            },
            ddAlinhar(b, modo) {
                const nos = this.ddSelNos(b);
                if (nos.length < 2) return;
                this.ddGravar(b);
                const gs = nos.map(n => ({ n, g: geo(n) }));
                const minx = Math.min(...gs.map(o => o.g.x)), maxx = Math.max(...gs.map(o => o.g.x + o.g.w));
                const miny = Math.min(...gs.map(o => o.g.y)), maxy = Math.max(...gs.map(o => o.g.y + o.g.h));
                if (modo === 'dh' || modo === 'dv') {
                    const h = modo === 'dh', ord = gs.slice().sort((a, c) => h ? a.g.x - c.g.x : a.g.y - c.g.y);
                    if (ord.length < 3) return;
                    const total = ord.reduce((s, o) => s + (h ? o.g.w : o.g.h), 0), vao = ((h ? maxx - minx : maxy - miny) - total) / (ord.length - 1);
                    let pos = h ? minx : miny;
                    ord.forEach(o => { if (h) o.n.x = Math.round(pos); else o.n.y = Math.round(pos); pos += (h ? o.g.w : o.g.h) + vao; });
                    return;
                }
                gs.forEach(({ n, g }) => {
                    if (modo === 'esq') n.x = Math.round(minx);
                    else if (modo === 'dir') n.x = Math.round(maxx - g.w);
                    else if (modo === 'cx') n.x = Math.round((minx + maxx) / 2 - g.w / 2);
                    else if (modo === 'topo') n.y = Math.round(miny);
                    else if (modo === 'base') n.y = Math.round(maxy - g.h);
                    else if (modo === 'cy') n.y = Math.round((miny + maxy) / 2 - g.h / 2);
                });
            },

            /* ---------- propriedades ---------- */
            ddMudarForma(b, n, forma) {
                if (!n || !F[forma]) return;
                this.ddGravar(b);
                const od = F[F[n.forma] ? n.forma : 'mapa'], nd = F[forma];
                if (!num(n.w) || num(n.w) === od.w || nd.fixo) n.w = nd.w;
                if (!num(n.h) || num(n.h) === od.h || nd.fixo) n.h = nd.h;
                n.forma = forma;
                if (forma === 'entidade' && !n.campos) n.campos = 'PK id\nnome';
            },
            ddCor(b, c) {
                if (!COR_OK.test(c || '')) return;
                const nos = this.ddSelNos(b);
                if (!nos.length) return;
                if (!this._ddCorT || Date.now() - this._ddCorT > 800) this.ddGravar(b);
                this._ddCorT = Date.now();
                nos.forEach(n => { n.cor = c; });
            },
            ddTam(b, campo, v) {
                const n = this.ddNoSel(b);
                if (!n) return;
                this.ddGravar(b);
                n[campo] = limitar(Math.round(num(v)), campo === 'w' ? 40 : 24, 1600);
            },
            ddPreset(b, l, id) {
                const p = PRESETS.find(x => x.id === id);
                if (!l || !p) return;
                this.ddGravar(b);
                l.ini = p.ini; l.fim = p.fim; l.traco = p.traco;
            },
            ddPresetAtual(l) { const p = l && PRESETS.find(x => x.ini === (l.ini == null ? 'nenhum' : l.ini) && x.fim === (l.fim == null ? 'seta' : l.fim) && x.traco === (l.traco || 'solido')); return p ? p.id : ''; },
            ddInverter(b, l) {
                if (!l) return;
                this.ddGravar(b);
                const t = l.de; l.de = l.para; l.para = t;
                const i = l.ini == null ? 'nenhum' : l.ini; l.ini = l.fim == null ? 'seta' : l.fim; l.fim = i;
            },
            ddDesligarPai(b, n) { if (!n || !n.pai) return; this.ddGravar(b); n.pai = null; },

            /* ---------- edição de texto ---------- */
            ddEditar(b, n) { const d = this.dd(b); d.sel = [n.id]; d.selLig = null; d.edit = n.id; },
            ddFimEdit(b, n) { const d = this.dd(b); if (d.edit === (n && n.id)) d.edit = null; },
            ddTeclaEdit(e, b) {
                e.stopPropagation();
                if (e.key === 'Escape' || (e.key === 'Enter' && !e.shiftKey)) {
                    e.preventDefault();
                    this.dd(b).edit = null;
                    e.target.closest('.wf-dd-lona')?.focus({ preventScroll: true });
                }
            },

            /* ---------- ponteiro: mover, ligar, redimensionar, selecionar em caixa ---------- */
            ddNoEm(b, x, y) {
                const nos = b.dados.nos || [];
                let achado = null;
                for (let i = nos.length - 1; i >= 0; i--) {
                    const g = geo(nos[i]);
                    if (x >= g.x && x <= g.x + g.w && y >= g.y && y <= g.y + g.h) { if (g.f !== 'raia') return nos[i]; if (!achado) achado = nos[i]; }
                }
                return achado;
            },
            ddNoDown(e, b, n) {
                if (e.button > 0 || this.pen(b).on) return;
                e.stopPropagation();
                const d = this.dd(b), lona = e.currentTarget.closest('.wf-dd-lona');
                if (d.edit && d.edit !== n.id) d.edit = null;
                lona?.focus({ preventScroll: true });
                if (e.shiftKey || e.ctrlKey || e.metaKey) {
                    const i = d.sel.indexOf(n.id);
                    if (i >= 0) d.sel.splice(i, 1); else d.sel.push(n.id);
                    d.selLig = null;
                    return;
                }
                if (!d.sel.includes(n.id)) d.sel = [n.id];
                d.selLig = null;
                if (d.edit === n.id) return;
                const z = d.z, x0 = e.clientX, y0 = e.clientY;
                const ids = new Set(d.sel);
                const gn = geo(n);
                if (gn.f === 'raia') (b.dados.nos || []).forEach(m => { const g = geo(m); if (g.f !== 'raia' && g.cx >= gn.x && g.cx <= gn.x + gn.w && g.cy >= gn.y && g.cy <= gn.y + gn.h) ids.add(m.id); });
                const grupo = (b.dados.nos || []).filter(m => ids.has(m.id)).map(m => ({ m, x: num(m.x), y: num(m.y) }));
                const prim = grupo.find(o => o.m.id === n.id) || grupo[0];
                const minX = Math.min(...grupo.map(o => o.x)), minY = Math.min(...grupo.map(o => o.y));
                let moveu = false;
                const mover = ev => {
                    const dx = (ev.clientX - x0) / z, dy = (ev.clientY - y0) / z;
                    if (!moveu && Math.hypot(dx, dy) < 3 / z) return;
                    if (!moveu) { moveu = true; this.ddGravar(b); }
                    const ddx = Math.max(this.ddSnapV(b, prim.x + dx) - prim.x, -minX), ddy = Math.max(this.ddSnapV(b, prim.y + dy) - prim.y, -minY);
                    grupo.forEach(o => { o.m.x = o.x + ddx; o.m.y = o.y + ddy; });
                };
                const fim = () => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            ddLigarDown(e, b, n, ld) {
                if (e.button > 0) return;
                e.stopPropagation(); e.preventDefault();
                const d = this.dd(b), lona = e.currentTarget.closest('.wf-dd-lona'), z = d.z, p0 = lado(geo(n), ld);
                lona?.focus({ preventScroll: true });
                d.sel = [n.id]; d.selLig = null; d.edit = null;
                d.liga = { de: n.id, x1: p0[0], y1: p0[1], x2: p0[0], y2: p0[1], alvo: null };
                const mover = ev => {
                    const [x, y] = this.ddXY(lona, ev, z), L = d.liga;
                    if (!L) return;
                    L.x2 = x; L.y2 = y;
                    const a = this.ddNoEm(b, x, y);
                    L.alvo = a ? a.id : null;
                };
                const fim = () => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                    const L = d.liga;
                    d.liga = null;
                    if (!L) return;
                    const dist = Math.hypot(L.x2 - L.x1, L.y2 - L.y1);
                    if (L.alvo && (L.alvo !== L.de || dist > 40)) {
                        const l = this.ddNovaLig(b, L.de, L.alvo);
                        if (l) { d.sel = []; d.selLig = l.id; }
                    } else if (!L.alvo && dist > 30) {
                        const src = (b.dados.nos || []).find(m => m.id === L.de);
                        if (src) { const novo = this.ddCriarLigado(b, src, L.x2, L.y2); if (novo) d.edit = novo.id; }
                    }
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            ddRedimDown(e, b, n) {
                if (e.button > 0) return;
                e.stopPropagation(); e.preventDefault();
                const d = this.dd(b), z = d.z, g0 = geo(n), x0 = e.clientX, y0 = e.clientY;
                let gravou = false;
                const mover = ev => {
                    if (!gravou) { this.ddGravar(b); gravou = true; }
                    n.w = limitar(this.ddSnapV(b, g0.w + (ev.clientX - x0) / z), 40, 1600);
                    if (g0.f !== 'entidade') n.h = limitar(this.ddSnapV(b, g0.h + (ev.clientY - y0) / z), 24, 1600);
                };
                const fim = () => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            ddRedimVisivel(b, n) { const d = this.dd(b); return d.sel.length === 1 && d.sel[0] === n.id && !F[F[n.forma] ? n.forma : 'mapa'].fixo; },
            ddLonaDown(e, b) {
                if (e.target !== e.currentTarget || e.button > 0 || this.pen(b).on) return;
                const d = this.dd(b), lona = e.currentTarget;
                lona.focus({ preventScroll: true });
                d.edit = null; d.selLig = null;
                const aditivo = e.shiftKey || e.ctrlKey || e.metaKey, base = aditivo ? d.sel.slice() : [];
                if (!aditivo) d.sel = [];
                if (e.pointerType === 'touch') return;
                e.preventDefault();
                const z = d.z, [x0, y0] = this.ddXY(lona, e, z);
                let moveu = false;
                const mover = ev => {
                    const [x1, y1] = this.ddXY(lona, ev, z);
                    const x = Math.min(x0, x1), y = Math.min(y0, y1), w = Math.abs(x1 - x0), h = Math.abs(y1 - y0);
                    if (!moveu && w < 4 && h < 4) return;
                    moveu = true;
                    d.caixa = { x, y, w, h };
                    const ids = (b.dados.nos || []).filter(n => { const g = geo(n); return g.x < x + w && g.x + g.w > x && g.y < y + h && g.y + g.h > y; }).map(n => n.id);
                    d.sel = [...new Set([...base, ...ids])];
                };
                const fim = () => {
                    d.caixa = null;
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            ddLigDown(e, b) {
                const g = e.target.closest ? e.target.closest('[data-lig]') : null;
                if (!g || e.button > 0) return;
                e.stopPropagation();
                const d = this.dd(b);
                d.sel = []; d.edit = null;
                d.selLig = g.getAttribute('data-lig');
                e.currentTarget.closest('.wf-dd-lona')?.focus({ preventScroll: true });
            },
            ddLigDbl(e, b) {
                const g = e.target.closest ? e.target.closest('[data-lig]') : null;
                if (!g) return;
                const raiz = e.currentTarget.closest('[data-dd]');
                this.dd(b).selLig = g.getAttribute('data-lig');
                this.$nextTick(() => { const el = raiz && raiz.querySelector('[data-rot]'); if (el) { el.focus(); el.select(); } });
            },

            /* ---------- teclado ---------- */
            ddTecla(e, b) {
                if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
                const d = this.dd(b), k = e.key, mod = e.ctrlKey || e.metaKey, kl = k.toLowerCase();
                if (mod && kl === 'z') { e.preventDefault(); if (e.shiftKey) this.ddRefazer(b); else this.ddDesfazer(b); return; }
                if (mod && kl === 'y') { e.preventDefault(); this.ddRefazer(b); return; }
                if (mod && kl === 'd') { e.preventDefault(); this.ddDuplicar(b); return; }
                if (mod && kl === 'a') { e.preventDefault(); d.sel = (b.dados.nos || []).map(n => n.id); d.selLig = null; return; }
                if (k === 'Escape') { d.sel = []; d.selLig = null; d.edit = null; d.liga = null; return; }
                if (k === 'Delete' || k === 'Backspace') { if (d.selLig || d.sel.length) { e.preventDefault(); this.ddRemoverSel(b); } return; }
                if (k === 'Enter' || k === 'F2') { const n = this.ddNoSel(b); if (n) { e.preventDefault(); this.ddEditar(b, n); } return; }
                if (k === 'Tab') { const n = this.ddNoSel(b); if (n) { e.preventDefault(); this.ddFilho(b, n); } return; }
                const m = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[k];
                if (m && d.sel.length) {
                    e.preventDefault();
                    const st = e.shiftKey ? 10 : 1;
                    this.ddGravar(b);
                    this.ddSelNos(b).forEach(n => { n.x = Math.max(0, num(n.x) + m[0] * st); n.y = Math.max(0, num(n.y) + m[1] * st); });
                }
            },

            /* ---------- zoom ---------- */
            ddZoomSet(b, z) { this.dd(b).z = Math.round(limitar(z, 0.3, 2.5) * 100) / 100; },
            ddZoom(b, f) { this.ddZoomSet(b, this.dd(b).z * f); },
            ddWheel(e, b) { if (!(e.ctrlKey || e.metaKey)) return; e.preventDefault(); this.ddZoom(b, e.deltaY < 0 ? 1.1 : 1 / 1.1); },
            ddAjustar(b, ev) {
                const cont = ev && ev.target.closest ? ev.target.closest('[data-dd]')?.querySelector('.wf-mapa-col') : null;
                const nos = b.dados.nos || [];
                if (!cont || !nos.length) { this.ddZoomSet(b, 1); return; }
                let w = 0, h = 0;
                nos.forEach(n => { const g = geo(n); w = Math.max(w, g.x + g.w + 40); h = Math.max(h, g.y + g.h + 40); });
                this.ddZoomSet(b, Math.min(cont.clientWidth / w, cont.clientHeight / h, 1.5));
                cont.scrollLeft = 0; cont.scrollTop = 0;
            },

            /* ---------- exportar ---------- */
            ddSvgTexto(b) {
                const nos = b.dados.nos || [];
                if (!nos.length) return null;
                let x0 = Infinity, y0 = Infinity, x1 = 0, y1 = 0;
                nos.forEach(n => {
                    const g = geo(n), extra = g.d.fora ? 40 : 0;
                    x0 = Math.min(x0, g.x); y0 = Math.min(y0, g.y); x1 = Math.max(x1, g.x + g.w); y1 = Math.max(y1, g.y + g.h + extra);
                });
                (b.dados.tracos || []).forEach(t => { x1 = Math.max(x1, t.m?.[0] || 0); y1 = Math.max(y1, t.m?.[1] || 0); });
                const pad = 30, vx = Math.max(0, x0 - pad), vy = Math.max(0, y0 - pad), W = Math.ceil(x1 + pad - vx), H = Math.ceil(y1 + pad - vy);
                const no = n => { const g = geo(n); return `<g transform="translate(${r1(g.x)} ${r1(g.y)})">${formaSvg(n, true)}${textoExport(n, g)}</g>`; };
                const corpo = nos.filter(n => n.forma === 'raia').map(no).join('')
                    + ligacoesSvg(b, null, true)
                    + nos.filter(n => n.forma !== 'raia').map(no).join('')
                    + this.tracosSvg(b);
                return { W, H, svg: `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="${vx} ${vy} ${W} ${H}"><rect x="${vx}" y="${vy}" width="${W}" height="${H}" fill="#ffffff"/>${corpo}</svg>` };
            },
            ddExportar(b, tipo) {
                const r = this.ddSvgTexto(b);
                if (!r) { this.aviso('Nada para exportar: o diagrama está vazio.'); return; }
                const nome = String(b.titulo || 'diagrama').replace(/[\\/:*?"<>|]+/g, '').trim() || 'diagrama';
                const blob = new Blob([r.svg], { type: 'image/svg+xml;charset=utf-8' });
                if (tipo === 'svg') { baixar(blob, nome + '.svg'); return; }
                const url = URL.createObjectURL(blob), im = new Image();
                im.onload = () => {
                    const c = document.createElement('canvas');
                    c.width = r.W * 2; c.height = r.H * 2;
                    const cx = c.getContext('2d');
                    cx.fillStyle = '#fff'; cx.fillRect(0, 0, c.width, c.height);
                    cx.drawImage(im, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(url);
                    c.toBlob(bl => { if (bl) baixar(bl, nome + '.png'); else this.aviso('Não consegui gerar o PNG.'); }, 'image/png');
                };
                im.onerror = () => { URL.revokeObjectURL(url); this.aviso('Não consegui gerar o PNG.'); };
                im.src = url;
            },

            /* ---------- desenho livre: agora com zoom e histórico ---------- */
            penHover(e, b) {
                const p = this.ui[b.id];
                if (!p || !p.on || !p.borr) return;
                const z = this.dd(b).z, r = e.currentTarget.getBoundingClientRect();
                p.cx = r1((e.clientX - r.left) / z); p.cy = r1((e.clientY - r.top) / z);
            },
            penDown(e, b) {
                const p = this.pen(b);
                if (!p.on || e.button > 0) return;
                e.preventDefault();
                const svg = e.currentTarget, z = this.dd(b).z;
                svg.setPointerCapture?.(e.pointerId);
                const pt = ev => { const r = svg.getBoundingClientRect(); return [r1((ev.clientX - r.left) / z), r1((ev.clientY - r.top) / z)]; };
                const raio = this.penRaio(b), antes = this.ddSnapshot(b);
                let pts = [], anterior = pt(e), gravou = false;
                const gravar = () => { if (!gravou) { this.ddEmpurrar(b, antes); gravou = true; } };
                const apagar = (de, ate) => {
                    const ref = b.dados.tracos;
                    this.apagarCaminho(b, de, ate, raio);
                    if (b.dados.tracos !== ref) gravar();
                };
                const mover = ev => {
                    const q = pt(ev);
                    if (p.borr) { apagar(anterior, q); anterior = q; return; }
                    const u = pts[pts.length - 1];
                    if (Math.hypot(q[0] - u[0], q[1] - u[1]) < 2.5) return;
                    pts.push(q);
                    p.vivo = caminhoPontos(pts);
                };
                const fim = () => {
                    svg.removeEventListener('pointermove', mover);
                    svg.removeEventListener('pointerup', fim);
                    svg.removeEventListener('pointercancel', fim);
                    if (!p.borr && pts.length) {
                        b.dados.tracos ??= [];
                        if (b.dados.tracos.length >= MAX_TRACOS) this.aviso('Limite de traços neste mapa. Desfaça ou limpe alguns.');
                        else {
                            gravar();
                            b.dados.tracos.push({
                                id: uid(), c: COR_OK.test(p.cor) ? p.cor : '#f472b6', w: Math.min(Math.max(num(p.larg), 1), 24),
                                d: caminhoPontos(pts), p: pts.flat(), m: [Math.ceil(Math.max(...pts.map(q => q[0]))), Math.ceil(Math.max(...pts.map(q => q[1])))],
                            });
                        }
                    }
                    p.vivo = '';
                };
                svg.addEventListener('pointermove', mover);
                svg.addEventListener('pointerup', fim);
                svg.addEventListener('pointercancel', fim);
                if (p.borr) apagar(anterior, anterior); else { pts = [pt(e)]; p.vivo = caminhoPontos(pts); }
            },
            penLimpar(b) {
                if ((b.dados.tracos || []).length && confirm('Apagar todo o desenho deste mapa? Os elementos ficam.')) { this.ddGravar(b); b.dados.tracos = []; }
            },
        };
    }

    window.diversoAbasDiagrama = function () {
        const base = window.diversoAbas();
        base.tiposBloco = base.tiposBloco.map(t => (t.id === 'mapa' ? { ...t, nome: 'Mapa mental e diagramas (BPMN, ER…)' } : t));
        return Object.assign(base, metodos());
    };
})();