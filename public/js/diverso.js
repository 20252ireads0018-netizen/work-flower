/* Work Flower · área Diverso
   Abas criadas pelo usuário (a principal tem as tarefas); cada aba é um quadro livre, com os mesmos cartões das outras áreas (tarefas, textos, tabelas, imagens,
   documentos, mapas mentais com desenho livre, compras, escrita, redes sociais, loja e código).
   Depende de quadro.js (carregue antes). */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    const { uid, num, r1 } = Q.util;
    const { PALETA, MM } = Q;
    const brl = n => (Number(n) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const fmt = n => Math.round(n).toLocaleString('pt-BR');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const FONTES = { sans: "'DM Sans', system-ui, sans-serif", serif: "Georgia, 'Times New Roman', serif", mono: "ui-monospace, Menlo, Consolas, monospace" };
    const COR_OK = /^#[0-9a-fA-F]{6}$/;
    const MAX_TRACOS = 600;
    const SEC = 'quadro'; // primeira aba criada: é nela que ficam os cartões de tarefas
    const MAX_ABAS = 12;  // máximo de abas criadas pelo usuário
    const ICONE_PADRAO = 'pasta';
    // As chaves vêm do WF_ICONES (definido na página). Lê na hora de usar, porque este arquivo carrega antes dele.
    const listaIcones = () => Object.keys(window.WF_ICONES || {});
    // Emojis de abas antigas viram o ícone equivalente
    const EMOJI_PARA_ICONE = {
        '📁': 'pasta', '📌': 'pin', '🏠': 'casa', '💼': 'trabalho', '🛒': 'compras', '💡': 'ideia',
        '📚': 'livro', '🎯': 'estrela', '💰': 'dinheiro', '🧾': 'dinheiro', '✈️': 'viagem', '🎨': 'foto',
        '🎵': 'musica', '🍽️': 'cafe', '🛠️': 'codigo', '❤️': 'coracao', '⭐': 'estrela', '📅': 'calendario',
        '🧠': 'ideia', '🌱': 'planta', '📷': 'foto', '🎮': 'jogo', '👥': 'usuario', '🚗': 'viagem',
    };
    /** Devolve sempre uma chave válida de ícone (aceita chave, emoji antigo ou qualquer outra coisa). */
    function normalizarIcone(v) {
        const t = String(v || '').trim();
        if ((window.WF_ICONES || {})[t]) return t;
        return EMOJI_PARA_ICONE[t] || EMOJI_PARA_ICONE[t.replace(/\uFE0F/g, '')] || ICONE_PADRAO;
    }
    const VERSAO = 2;     // estado.v = 2: as abas criadas pelo usuário ficam em estado.abas

    /* Modelos de documento: [título da seção, texto inicial] */
    const MODELOS = {
        branco: { nome: 'Em branco', secoes: [['', '']] },
        curriculo: { nome: 'Currículo', secoes: [['Seu nome', 'Cargo · cidade · e-mail · telefone'], ['Objetivo', ''], ['Experiência', ''], ['Formação', ''], ['Habilidades', '']] },
        contrato: { nome: 'Contrato', secoes: [['Partes', ''], ['Objeto', ''], ['Prazo e valor', ''], ['Cláusulas', ''], ['Assinaturas', '']] },
        resumo: { nome: 'Resumo de estudo', secoes: [['Tema', ''], ['Ideias principais', ''], ['Dúvidas', ''], ['Para revisar', '']] },
        lista: { nome: 'Lista', secoes: [['Lista', '']] },
    };

    /* Posição inicial dos cartões fixos de tarefas, no topo do quadro */
    const PADRAO = {
        [SEC]: {
            'tarefa-form': { x: 0, y: 0, w: 340, h: 440 },
            'tarefa-lista': { x: 356, y: 0, w: 732, h: 520 },
        },
    };

    /* Tabletop (mapa com grade): peças prontas, formas e limites */
    const TT_PECAS = [
        { id: 'heroi', nome: 'Herói', forma: 'circulo', cor: '#38bdf8' },
        { id: 'aliado', nome: 'Aliado', forma: 'circulo', cor: '#4ade80' },
        { id: 'inimigo', nome: 'Inimigo', forma: 'circulo', cor: '#f87171' },
        { id: 'objeto', nome: 'Objeto', forma: 'losango', cor: '#fbbf24' },
        { id: 'obstaculo', nome: 'Obstáculo', forma: 'quadrado', cor: '#64748b' },
    ];
    const TT_FORMAS = ['circulo', 'quadrado', 'losango'];
    const TT_MAX_PECAS = 200;
    const MAX_MODELOS = 60; // peças salvas em "Minhas peças"
    const limitar = (v, a, b) => Math.min(Math.max(v, a), b);

    /** Texto seguro: null/undefined viram ''. O servidor converte "" em null ao salvar a biblioteca, e o null quebrava startsWith e afins. */
    const txt = v => (v == null ? '' : String(v));

    /** Conserta um tabletop que veio da biblioteca (ou de um estado antigo): listas existentes, peças com id e nome em texto.
        Devolve true se mudou alguma coisa (para o estado ser salvo de novo). */
    function normalizarTabletop(d) {
        if (!d || typeof d !== 'object') return false;
        let mudou = false;
        if (!Array.isArray(d.pecas)) { d.pecas = []; mudou = true; }
        if (!Array.isArray(d.desenho)) { d.desenho = []; mudou = true; }
        const validas = d.pecas.filter(p => p && typeof p === 'object');
        if (validas.length !== d.pecas.length) { d.pecas = validas; mudou = true; }
        d.pecas.forEach(p => {
            if (p.id == null || p.id === '') { p.id = uid(); mudou = true; }
            const nome = txt(p.nome).slice(0, 24) || 'Peça';
            if (p.nome !== nome) { p.nome = nome; mudou = true; }
        });
        const itens = d.desenho.filter(it => it && typeof it === 'object');
        if (itens.length !== d.desenho.length) { d.desenho = itens; mudou = true; }
        return mudou;
    }

    /** Zoom entre 1× e 5× e deslocamento limitado ao ponto em que a imagem ainda cobre a peça toda. */
    function enqAjustar(z, x, y) {
        z = limitar(num(z) || 1, 1, 5);
        const m = (z - 1) / 2 * 100;
        return { z: Math.round(z * 100) / 100, x: r1(limitar(num(x), -m, m)), y: r1(limitar(num(y), -m, m)) };
    }

    /** Valida a lista de peças salvas vinda do servidor (ids seguros, formas e cores válidas, números nos limites). */
    function normalizarModelos(lista) {
        return (Array.isArray(lista) ? lista : [])
            .filter(m => m && /^[A-Za-z0-9._-]{1,60}$/.test(m.id || ''))
            .slice(0, MAX_MODELOS)
            .map(m => {
                const enq = enqAjustar(m.iz, m.ix, m.iy);
                return {
                    id: m.id,
                    nome: String(m.nome || 'Peça').slice(0, 24),
                    forma: TT_FORMAS.includes(m.forma) ? m.forma : 'circulo',
                    cor: COR_OK.test(m.cor || '') ? m.cor : '#a78bfa',
                    tam: limitar(Math.round(num(m.tam)) || 1, 1, 4),
                    iz: enq.z, ix: enq.x, iy: enq.y,
                    img: !!m.img,
                };
            });
    }

    /** Reduz uma imagem (data URL) para caber melhor na biblioteca. Se já for pequena ou der erro, devolve a original. */
    function reduzirSrc(src, max, qualidade, limite = Infinity) {
        return new Promise(resolve => {
            const im = new Image();
            im.onload = () => {
                const k = Math.min(1, max / Math.max(im.width, im.height));
                // já cabe em tamanho e em peso: devolve a original
                if (k >= 1 && src.length <= limite) { resolve(src); return; }
                const c = document.createElement('canvas');
                c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
                const cx = c.getContext('2d');
                cx.fillStyle = '#fff'; cx.fillRect(0, 0, c.width, c.height); // JPEG não tem transparência
                cx.drawImage(im, 0, 0, c.width, c.height);
                const novo = c.toDataURL('image/jpeg', qualidade);
                resolve(novo.length < src.length ? novo : src);
            };
            im.onerror = () => resolve(src);
            im.src = src;
        });
    }

    /* [largura, altura, título, dados iniciais] de cada tipo de bloco */
    function defBloco(tipo, self) {
        return {
            texto: [340, 240, 'Texto', { texto: '', tam: 'm' }],
            tabela: [480, 280, 'Tabela', { cab: ['Item', 'Valor'], linhas: [['', ''], ['', '']] }],
            lista: [320, 300, 'Lista', { itens: [] }],
            imagem: [360, 300, 'Imagem', { ajuste: 'cover' }],
            documento: [520, 520, 'Documento', { modelo: 'branco', layout: 'coluna', fonte: 'sans', cor: PALETA[0], secoes: self.secoesModelo('branco') }],
            mapa: [720, 480, 'Mapa mental', { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }], tracos: [] }],
            compras: [420, 360, 'Lista de compras', { itens: [] }],
            escrita: [560, 420, 'Escrita', { texto: '', lado: 'left', larg: 35, fonte: 'serif' }],
            social: [720, 400, 'Redes sociais', { posts: [] }],
            loja: [640, 440, 'Minha loja', { pagamento: '', produtos: [] }],
            codigo: [640, 260, 'Código', { repos: [{ id: uid(), nome: '', pasta: '' }] }],
            tabletop: [720, 560, 'Tabletop', { cols: 16, rows: 12, cel: 40, grade: true, nomes: true, pecas: [], desenho: [] }],
        }[tipo];
    }

    function lerCfg() {
        try {
            const c = JSON.parse(document.getElementById('diverso-cfg').textContent);
            c.dados = Array.isArray(c.dados) ? {} : (c.dados || {});
            return c;
        } catch { return { url: '', dados: {}, tarefas: [] }; }
    }

    /* Estado antigo -> quadro único. Devolve os ids de blocos cujas imagens devem sair do servidor.
       - todos os blocos das abas antigas vão para o mesmo quadro (cada aba antiga fica abaixo da anterior);
       - os cartões de tarefas mantêm a posição que o usuário já tinha dado;
       - o bloco "Campos do dia" foi retirado da página;
       - a lixeira foi retirada da página: o que estava nela é descartado de vez. */
    function migrar(e) {
        const orfaos = [];
        let mudou = false;

        if (Array.isArray(e.lixeira)) e.lixeira.forEach(it => { if (it?.bloco?.id) orfaos.push(it.bloco.id); });
        if ('lixeira' in e) { delete e.lixeira; mudou = true; }

        const campos = e.blocos.filter(b => b?.tipo === 'campos');
        if (campos.length) {
            campos.forEach(b => {
                orfaos.push(b.id);
                if (e.layout[b.secao]) delete e.layout[b.secao][b.id];
            });
            e.blocos = e.blocos.filter(b => b?.tipo !== 'campos');
            mudou = true;
        }

        // formato novo (v2): as abas são do usuário e cada bloco pertence à sua aba; nada a juntar
        if (e.v === VERSAO) {
            e.abas = (Array.isArray(e.abas) ? e.abas : [])
                .filter(a => a && /^[A-Za-z0-9._-]{1,60}$/.test(a.id || ''))
                .map(a => ({ id: a.id, nome: String(a.nome || 'Aba').slice(0, 40), icone: normalizarIcone(a.icone) }));
            if (casaParaOQuadro(e)) mudou = true;
            // bloco de uma aba que não existe mais: volta para a primeira aba
            const ok = new Set([SEC, ...e.abas.map(a => a.id)]);
            e.blocos.forEach(b => {
                if (ok.has(b.secao)) return;
                const l = e.layout[b.secao]?.[b.id];
                if (e.layout[b.secao]) delete e.layout[b.secao][b.id];
                (e.layout[SEC] ??= {})[b.id] = l || { x: 0, y: 0, w: 360, h: 260, z: 1 };
                b.secao = SEC;
                mudou = true;
            });
            return { orfaos, mudou };
        }

        const abasAntigas = Array.isArray(e.abas) ? e.abas.map(a => a.id) : [];
        const secoes = [...new Set([...abasAntigas, ...Object.keys(e.layout), ...e.blocos.map(b => b.secao)])]
            .filter(id => id && id !== SEC && id !== 'tarefas');
        const veioDeAbas = 'abas' in e || 'tarefas' in e.layout || secoes.length > 0 || e.blocos.some(b => b.secao !== SEC);
        if (veioDeAbas) {
            const L = (e.layout[SEC] ??= {});
            const fundo = ids => ids.reduce((m, id) => (L[id] ? Math.max(m, L[id].y + L[id].h) : m), 0);

            // 1) aba Tarefas: cartões fixos e blocos ficam onde estavam
            Object.entries(e.layout.tarefas || {}).forEach(([id, l]) => { L[id] = l; });
            e.blocos.filter(b => b.secao === 'tarefas').forEach(b => { b.secao = SEC; });
            const fixos = Object.keys(PADRAO[SEC]);
            fixos.forEach(id => { L[id] ??= { ...PADRAO[SEC][id] }; });
            let y0 = Math.max(fundo(fixos), fundo(e.blocos.filter(b => b.secao === SEC).map(b => b.id)));

            // 2) demais abas, na ordem em que apareciam, uma abaixo da outra
            secoes.forEach(sec => {
                const blocos = e.blocos.filter(b => b.secao === sec);
                if (!blocos.length) return;
                const lay = e.layout[sec] || {};
                let alt = 0;
                blocos.forEach(b => {
                    const l = lay[b.id] || { x: 0, y: alt, w: 360, h: 260, z: 1 };
                    L[b.id] = { ...l, y: l.y + y0 + 16 };
                    alt = Math.max(alt, l.y + l.h);
                    b.secao = SEC;
                });
                y0 += alt + 16;
            });

            Object.keys(e.layout).forEach(k => { if (k !== SEC) delete e.layout[k]; });
            mudou = true;
        }
        // a partir daqui o estado usa o formato novo, com abas do usuário
        e.abas = [];
        e.v = VERSAO;
        casaParaOQuadro(e);
        return { orfaos, mudou: true };
    }

    /** Blocos e tarefas que já estavam no quadro único precisam de uma aba: cria a "Geral" (só se houver algo lá). */
    function casaParaOQuadro(e) {
        if (e.abas.some(a => a.id === SEC)) return false;
        const temAlgo = e.blocos.some(b => b.secao === SEC) || Object.keys(e.layout[SEC] || {}).length > 0;
        if (!temAlgo) return false;
        e.abas.unshift({ id: SEC, nome: 'Geral', icone: 'pin' });
        return true;
    }

    /* ---------- desenho: caminho suave a partir de pontos ---------- */
    function caminho(p) {
        if (p.length === 1) return `M${p[0][0]} ${p[0][1]}l0.1 0`;
        let d = `M${p[0][0]} ${p[0][1]}`;
        if (p.length === 2) return d + `L${p[1][0]} ${p[1][1]}`;
        for (let i = 1; i < p.length - 1; i++) d += `Q${p[i][0]} ${p[i][1]} ${r1((p[i][0] + p[i + 1][0]) / 2)} ${r1((p[i][1] + p[i + 1][1]) / 2)}`;
        const u = p[p.length - 1];
        return d + `L${u[0]} ${u[1]}`;
    }
    /* ---------- borracha: corta o traço só onde ela passa ---------- */
    function pontosDe(t) {
        const p = t.p || [], o = [];
        for (let i = 0; i + 1 < p.length; i += 2) o.push([p[i], p[i + 1]]);
        return o;
    }
    /** Trecho do segmento A→B que cai DENTRO do círculo (x, y, r), como intervalo [t0, t1] em 0..1; ou null. */
    function trechoDentro(a, b, x, y, r) {
        const dx = b[0] - a[0], dy = b[1] - a[1], fx = a[0] - x, fy = a[1] - y;
        const A = dx * dx + dy * dy, B = 2 * (fx * dx + fy * dy), C = fx * fx + fy * fy - r * r;
        if (A < 1e-9) return C <= 0 ? [0, 1] : null;
        const D = B * B - 4 * A * C;
        if (D <= 0) return null;
        const q = Math.sqrt(D), i0 = Math.max(0, (-B - q) / (2 * A)), i1 = Math.min(1, (-B + q) / (2 * A));
        return i0 < i1 ? [i0, i1] : null;
    }
    /** Corta o traço onde o círculo da borracha passa. Devolve null se não tocou; senão a lista de pedaços que sobraram (cada um com 2+ pontos). */
    function cortarTraco(t, x, y, r) {
        const P = pontosDe(t);
        if (!P.length) return null;
        const R = r + (t.w || 2) / 2;
        if (P.length === 1) return Math.hypot(P[0][0] - x, P[0][1] - y) <= R ? [] : null;
        const em = (a, b, k) => [r1(a[0] + (b[0] - a[0]) * k), r1(a[1] + (b[1] - a[1]) * k)];
        const pedacos = [];
        let atual = [], tocou = false;
        for (let i = 0; i + 1 < P.length; i++) {
            const a = P[i], b = P[i + 1], iv = trechoDentro(a, b, x, y, R);
            let partes = [[0, 1]];
            if (iv) {
                tocou = true;
                partes = [];
                if (iv[0] > 1e-6) partes.push([0, iv[0]]);
                if (iv[1] < 1 - 1e-6) partes.push([iv[1], 1]);
            }
            if ((!partes.length || partes[0][0] > 0) && atual.length) { pedacos.push(atual); atual = []; }
            for (const [ini, fim] of partes) {
                const q = em(a, b, fim);
                if (ini === 0 && atual.length) atual.push(q);
                else { if (atual.length) pedacos.push(atual); atual = [em(a, b, ini), q]; }
                if (fim < 1) { pedacos.push(atual); atual = []; }
            }
        }
        if (atual.length) pedacos.push(atual);
        return tocou ? pedacos : null;
    }
    function novoTraco(base, pts) {
        return {
            id: uid(), c: base.c, w: base.w,
            d: caminho(pts), p: pts.flat(), m: [Math.ceil(Math.max(...pts.map(q => q[0]))), Math.ceil(Math.max(...pts.map(q => q[1])))],
        };
    }

    /* ---------- tabletop: desenho (pincel + formas) ---------- */
    const TT_REF = 40;          // unidades do desenho por casa (o SVG escala com o tamanho da casa)
    const TT_HIST = 60;         // passos de desfazer
    const MAX_DESENHO = 600;    // itens de desenho por mapa
    const TT_FERRAMENTAS = [
        { id: 'pincel', nome: 'Pincel' }, { id: 'borracha', nome: 'Borracha' },
        { id: 'circulo', nome: 'Círculo' }, { id: 'quadrado', nome: 'Quadrado' },
        { id: 'cone', nome: 'Cone' }, { id: 'triangulo', nome: 'Triângulo' },
    ];
    const novoPincel = (base, pts) => ({ ...novoTraco(base, pts), t: 'pincel' });

    function tracoSvg(t) {
        if (!COR_OK.test(t.c || '') || !/^[MLQl0-9. -]+$/.test(t.d || '')) return '';
        const w = Math.min(Math.max(num(t.w), 1), 24);
        return `<path d="${t.d}" fill="none" stroke="${t.c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;
    }
    /** Pontos do contorno de uma forma arrastada de (x1,y1) até (x2,y2). */
    function formaPontos(f) {
        const x1 = num(f.x1), y1 = num(f.y1), x2 = num(f.x2), y2 = num(f.y2), dx = x2 - x1, dy = y2 - y1;
        if (f.t === 'circulo') {
            const r = Math.hypot(dx, dy), n = 40;
            return Array.from({ length: n }, (_, i) => { const a = i / n * Math.PI * 2; return [x1 + Math.cos(a) * r, y1 + Math.sin(a) * r]; });
        }
        if (f.t === 'quadrado') {
            const s = Math.max(Math.abs(dx), Math.abs(dy)), ex = x1 + (dx < 0 ? -s : s), ey = y1 + (dy < 0 ? -s : s);
            return [[x1, y1], [ex, y1], [ex, ey], [x1, ey]];
        }
        if (f.t === 'cone') // vértice em A; a base, centrada em B, tem largura igual ao comprimento
            return [[x1, y1], [x2 - dy / 2, y2 + dx / 2], [x2 + dy / 2, y2 - dx / 2]];
        if (f.t === 'triangulo') return [[(x1 + x2) / 2, y1], [x2, y2], [x1, y2]];
        return [];
    }
    function formaSvg(f) {
        if (!COR_OK.test(f.c || '')) return '';
        const w = Math.min(Math.max(num(f.w), 1), 24);
        const attrs = `${f.f ? `fill="${f.c}" fill-opacity="0.18"` : 'fill="none"'} stroke="${f.c}" stroke-width="${w}" stroke-linejoin="round"`;
        if (f.t === 'circulo') return `<circle cx="${r1(num(f.x1))}" cy="${r1(num(f.y1))}" r="${r1(Math.hypot(num(f.x2) - num(f.x1), num(f.y2) - num(f.y1)))}" ${attrs}/>`;
        const P = formaPontos(f);
        return P.length ? `<polygon points="${P.map(q => r1(q[0]) + ',' + r1(q[1])).join(' ')}" ${attrs}/>` : '';
    }
    /** A borracha (círculo x,y,r) toca o contorno da forma (ou o interior, se for preenchida)? */
    function formaTocada(f, x, y, r) {
        const P = formaPontos(f), R = r + num(f.w) / 2;
        if (P.length < 2) return false;
        for (let i = 0; i < P.length; i++) if (trechoDentro(P[i], P[(i + 1) % P.length], x, y, R)) return true;
        if (!f.f) return false;
        let dentro = false;
        for (let i = 0, j = P.length - 1; i < P.length; j = i++) {
            const [xi, yi] = P[i], [xj, yj] = P[j];
            if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) dentro = !dentro;
        }
        return dentro;
    }
    const desPadrao = () => ({ on: false, ferr: 'pincel', cor: '#f472b6', larg: 3, preencher: true, vivo: null, cx: null, cy: null, hist: [], refaz: [] });

    /* Garantias para a seleção em caixa, independentes do CSS da página: o fundo e a caixa nunca interceptam o clique,
       a camada de desenho só recebe o mouse com uma ferramenta ligada, e arrastar na grade não seleciona texto. */
    (function () {
        if (document.getElementById('wf-tt-selecao')) return;
        const st = document.createElement('style');
        st.id = 'wf-tt-selecao';
        st.textContent = `
            .wf-tt-grade { user-select: none; -webkit-user-select: none; }
            .wf-tt-mapa, .wf-tt-caixa { pointer-events: none; }
            .wf-tt-des:not(.ativo) { pointer-events: none; }`;
        document.head.appendChild(st);
    })();

    window.diversoAbas = function () {
        const cfg = lerCfg();
        let e = null;
        try { if (cfg.dados.estado) e = JSON.parse(cfg.dados.estado); } catch { /* usa o padrão */ }
        e = e && typeof e === 'object' ? e : {};
        e.layout = e.layout && typeof e.layout === 'object' && !Array.isArray(e.layout) ? e.layout : {};
        e.blocos = Array.isArray(e.blocos) ? e.blocos.filter(b => b && typeof b === 'object' && b.id) : [];
        const migracao = migrar(e);
        e.pecasSalvas = normalizarModelos(e.pecasSalvas); // "Minhas peças" do tabletop
        e.layout[SEC] ??= {};
        const estado = e;
        const imagens = {};
        Object.keys(cfg.dados).filter(k => k.startsWith('img.')).forEach(k => {
            try { imagens[k.slice(4)] = JSON.parse(cfg.dados[k]); } catch { /* ignora */ }
        });
        const motor = Q.metodos(PADRAO);
        const copia = o => JSON.parse(JSON.stringify(o));

        return {
            ...Q.estadoUI(), ...motor,
            cfg, estado, imagens, tarefas: cfg.tarefas || [], aba: estado.abas[0]?.id ?? null,
            icones: listaIcones(), formAba: null,
            ui: {}, ultimo: null,
            modelos: Object.entries(MODELOS).map(([id, m]) => ({ id, nome: m.nome })),
            fontes: FONTES, paleta: PALETA,
            tiposBloco: [
                { id: 'texto', nome: 'Campo de texto' }, { id: 'tabela', nome: 'Tabela' },
                { id: 'lista', nome: 'Lista de itens' }, { id: 'imagem', nome: 'Imagem' },
                { id: 'documento', nome: 'Documento' }, { id: 'mapa', nome: 'Mapa mental (com desenho)' },
                { id: 'compras', nome: 'Lista de compras' },
                { id: 'escrita', nome: 'Escrita com imagem' }, { id: 'social', nome: 'Redes sociais' },
                { id: 'loja', nome: 'Loja' }, { id: 'codigo', nome: 'GitHub / VS Code' },
                { id: 'tabletop', nome: 'Tabletop (mapa com grade)' },
            ],
            ttPecas: TT_PECAS, ttFormas: TT_FORMAS, ttFerramentas: TT_FERRAMENTAS,
            uid, num, r1, brl, fmt,
            pct(v, m) { return m > 0 ? Math.min(Math.max(v / m * 100, 0), 100) : 0; },

            init() {
                // cartões ativos que apontam para uma aba que não existe mais caem na aba principal
                if (Q.EMBED && !this.abaExiste(Q.EMBED.sec)) Q.EMBED.sec = SEC;
                // tabletops colados da biblioteca antes desta correção podem ter nomes null: conserta e salva de novo
                let reparou = false;
                this.estado.blocos.forEach(b => { if (b.tipo === 'tabletop' && normalizarTabletop(b.dados)) reparou = true; });
                this.zTop = this.maiorZ();
                this.$watch('estado', () => this.agendar());
                this.initQuadro();
                const urgente = () => { if (this.salvo === 'pendente') this.gravarEstado(); };
                document.addEventListener('visibilitychange', () => { if (document.hidden) urgente(); });
                window.addEventListener('pagehide', urgente);
                migracao.orfaos.forEach(id => this.apagarImagens(id));
                if (!this.cfg.dados.estado || migracao.mudou || reparou) this.agendar();
            },

            /* ---------- abas (nome e ícone escolhidos pelo usuário) ---------- */
            abaExiste(id) { return id === SEC || this.estado.abas.some(a => a.id === id); },
            irPara(id) { this.aba = id; this.menuBloco = false; this.formAba = null; },
            abrirNovaAba() {
                if (this.estado.abas.length >= MAX_ABAS) { this.aviso(`Limite de ${MAX_ABAS} abas. Exclua alguma para criar outra.`); return; }
                this.menuBloco = false;
                const ic = listaIcones();
                this.formAba = { id: null, nome: '', icone: ic.length ? ic[this.estado.abas.length % ic.length] : ICONE_PADRAO };
                this.focarNomeAba();
            },
            editarAba(a) {
                this.menuBloco = false;
                this.aba = a.id;
                this.formAba = { id: a.id, nome: a.nome, icone: normalizarIcone(a.icone) };
                this.focarNomeAba();
            },
            focarNomeAba() {
                this.$nextTick(() => { const i = this.$refs.nomeAba; if (i) { i.focus(); i.select(); } });
            },
            cancelarAba() { this.formAba = null; },
            salvarAba() {
                const f = this.formAba;
                if (!f) return;
                const icone = normalizarIcone(f.icone);
                if (f.id) {
                    const a = this.estado.abas.find(x => x.id === f.id);
                    if (a) { a.nome = String(f.nome || '').trim().slice(0, 40) || a.nome; a.icone = icone; }
                    this.formAba = null;
                    return;
                }
                if (this.estado.abas.length >= MAX_ABAS) { this.formAba = null; return; }
                let n = this.estado.abas.length + 1;
                const nome = String(f.nome || '').trim().slice(0, 40) || `Aba ${n}`;
                // a primeira aba guarda os cartões de tarefas (eles pertencem ao quadro "quadro")
                const id = this.estado.abas.length === 0 && !this.estado.abas.some(a => a.id === SEC) ? SEC : 'a' + uid();
                this.estado.abas.push({ id, nome, icone });
                this.estado.layout[id] ??= {};
                this.formAba = null;
                this.aba = id;
            },
            removerAba(a) {
                if (a.id === SEC) { this.aviso('A primeira aba guarda as tarefas e não pode ser excluída. Você pode renomeá-la e trocar o ícone.'); return; }
                const blocos = this.estado.blocos.filter(b => b.secao === a.id);
                const texto = blocos.length
                    ? `Excluir a aba "${a.nome}" e os ${blocos.length} bloco(s) dela? Não dá para desfazer.`
                    : `Excluir a aba "${a.nome}"?`;
                if (!confirm(texto)) return;
                blocos.forEach(b => this.apagarImagens(b.id));
                this.estado.blocos = this.estado.blocos.filter(b => b.secao !== a.id);
                delete this.estado.layout[a.id];
                this.estado.abas = this.estado.abas.filter(x => x.id !== a.id);
                this.formAba = null;
                this.aba = this.estado.abas[0]?.id ?? null;
                this.aviso('Aba excluída.');
            },

            /* ---------- blocos ---------- */
            criarBloco(sec, tipo, pos, over = {}) {
                const d = defBloco(tipo, this);
                if (!d) return null;
                const id = 'b' + uid(), W = this.col || 1088, w = Math.min(d[0], W);
                const dados = over.dados ? { ...d[3], ...over.dados } : d[3];
                this.estado.blocos.push({ id, secao: sec, tipo, titulo: over.titulo || d[2], links: [], dados });
                const y0 = this.alturaConteudo(sec);
                (this.estado.layout[sec] ??= {})[id] = pos
                    ? { x: pos.x, y: pos.y, w, h: d[1], z: ++this.zTop }
                    : { x: 0, y: y0 ? y0 + 16 : 0, w, h: d[1], z: ++this.zTop };
                return id;
            },
            novoBloco(sec, tipo) {
                if (!this.criarBloco(sec, tipo)) return;
                this.menuBloco = false;
                this.aviso(`${this.tipoNome(tipo)} adicionado ao fim da aba. Arraste para onde quiser.`);
            },
            desfazerVisivel() { return !!this.ultimo && this.msg === 'Bloco excluído.'; },
            tipoNome(t) { return t === 'vivo' ? 'Cartão ativo' : (this.tiposBloco.find(x => x.id === t)?.nome || t); },

            /* ---------- excluir (com "Desfazer" por alguns segundos; sem lixeira) ---------- */
            removerBloco(b) {
                const i = this.estado.blocos.findIndex(x => x.id === b.id);
                if (i < 0) return;
                const [bloco] = this.estado.blocos.splice(i, 1);
                const lay = this.estado.layout[bloco.secao]?.[bloco.id];
                if (this.estado.layout[bloco.secao]) delete this.estado.layout[bloco.secao][bloco.id];
                // as imagens saem do servidor agora; a cópia fica só na memória enquanto dá para desfazer
                const imgs = {};
                Object.keys(this.imagens).filter(k => k === bloco.id || k.startsWith(bloco.id + '.')).forEach(k => { imgs[k] = this.imagens[k]; });
                this.apagarImagens(bloco.id);
                this.ultimo = { bloco: copia(bloco), lay: lay ? copia(lay) : null, imgs };
                this.msg = 'Bloco excluído.';
                clearTimeout(this._m);
                this._m = setTimeout(() => { this.msg = ''; this.ultimo = null; }, 8000);
            },
            desfazerRemocao() {
                const u = this.ultimo;
                if (!u) return;
                this.ultimo = null;
                const b = copia(u.bloco);
                if (!this.abaExiste(b.secao)) b.secao = SEC;
                this.estado.blocos.push(b);
                (this.estado.layout[b.secao] ??= {})[b.id] = u.lay
                    ? { ...u.lay, z: ++this.zTop }
                    : { x: 0, y: this.alturaConteudo(b.secao) + 16, w: 360, h: 260, z: ++this.zTop };
                Object.entries(u.imgs).forEach(([k, v]) => {
                    this.imagens[k] = v;
                    Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(v)).catch(() => this.aviso('Não consegui salvar uma imagem restaurada.'));
                });
                this.aviso('Bloco restaurado.');
            },
            apagarImagens(id) {
                Object.keys(this.imagens).filter(k => k === id || k.startsWith(id + '.')).forEach(k => {
                    delete this.imagens[k];
                    Q.enviar(`${this.cfg.url}/img.${k}`, null, 'DELETE').catch(() => {});
                });
            },

            /* ---------- biblioteca: a loja e o tabletop levam as imagens junto ----------
               As imagens ficam fora de `dados` (img.<bloco>.<...>); ao guardar, vão embutidas no item da
               biblioteca e, ao colar, voltam para o servidor com o id do novo bloco.
               O mapa de fundo do tabletop é reduzido ao guardar (é a imagem mais pesada e pode estourar o limite do servidor). */
            async guardarBloco(b) {
                if (b.tipo !== 'loja' && b.tipo !== 'tabletop') return motor.guardarBloco.call(this, b);
                const dados = copia(b.dados);
                if (b.tipo === 'loja') {
                    dados.produtos.forEach(p => { const im = this.imagens[`${b.id}.${p.id}`]; if (im) p.img = im; });
                } else {
                    const imgs = {};
                    (dados.pecas || []).forEach(p => {
                        if (!p.img || imgs[p.img]) return;
                        const im = this.imagens[`${b.id}.i.${p.img}`];
                        if (im) imgs[p.img] = im;
                    });
                    if (Object.keys(imgs).length) dados.imgs = imgs;
                    // a biblioteca inteira vai num único PUT: o mapa de fundo e as imagens das peças entram compactados
                    for (const id of Object.keys(imgs)) imgs[id] = await reduzirSrc(imgs[id], 256, 0.75, 40000);
                    const mapa = this.imagens[`${b.id}.mapa`];
                    if (mapa) dados.mapaImg = await reduzirSrc(mapa, 900, 0.65, 180000);
                }
                return motor.guardarBloco.call(this, { ...b, dados });
            },
            colarBloco(item) {
                if (!this.aba) { this.aviso('Crie uma aba antes de colar blocos.'); return; }
                const antes = this.estado.blocos.length;
                motor.colarBloco.call(this, item);
                const nb = this.estado.blocos[this.estado.blocos.length - 1];
                if (this.estado.blocos.length === antes || !nb) return;
                const salvar = (k, v, msg) => {
                    this.imagens[k] = v;
                    Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(v)).catch(() => this.aviso(msg));
                };
                if (nb.tipo === 'loja') {
                    nb.dados.produtos.forEach(p => {
                        if (!p.img) return;
                        salvar(`${nb.id}.${p.id}`, p.img, 'Não consegui salvar uma foto de produto.');
                        delete p.img;
                    });
                } else if (nb.tipo === 'tabletop') {
                    const d = copia(nb.dados);
                    Object.entries(d.imgs || {}).forEach(([id, src]) => salvar(`${nb.id}.i.${id}`, src, 'Não consegui salvar a imagem de uma peça.'));
                    if (d.mapaImg) salvar(`${nb.id}.mapa`, d.mapaImg, 'Não consegui salvar a imagem do mapa.');
                    delete d.imgs; delete d.mapaImg;
                    normalizarTabletop(d); // o servidor troca "" por null: nomes de peças voltam como texto
                    nb.dados = d;
                }
            },

            /* ---------- redes sociais ---------- */
            addPost(b) { b.dados.posts.push({ id: uid(), nome: '', rede: '', likes: 0, views: 0, coment: 0, comp: 0 }); },
            somaSocial(b, k) { return fmt(b.dados.posts.reduce((s, p) => s + num(p[k]), 0)); },
            engSocial(b) {
                const P = b.dados.posts, eng = P.reduce((s, p) => s + num(p.likes) + num(p.coment) + num(p.comp), 0);
                const v = P.reduce((s, p) => s + num(p.views), 0);
                return fmt(eng) + (v > 0 ? ` (${String(r1(eng / v * 100)).replace('.', ',')}%)` : '');
            },

            /* ---------- loja ---------- */
            addProduto(b) { b.dados.produtos.push({ id: uid(), nome: 'Novo produto', preco: 0, estoque: 0, vendidos: 0 }); },
            remProduto(b, p) { this.apagarImagens(`${b.id}.${p.id}`); b.dados.produtos.splice(b.dados.produtos.indexOf(p), 1); },
            imgProduto(b, p) { return this.imagens[`${b.id}.${p.id}`] || ''; },
            async lerImgProduto(b, p, arq) {
                if (!arq || !arq.type.startsWith('image/')) return;
                const k = `${b.id}.${p.id}`;
                try {
                    this.imagens[k] = await Q.reduzir(arq, 320, 0.72);
                    await Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(this.imagens[k]));
                } catch { this.aviso('Não consegui salvar a foto do produto.'); }
            },
            vender(p) { p.vendidos = num(p.vendidos) + 1; if (num(p.estoque) > 0) p.estoque = num(p.estoque) - 1; },
            faturaLoja(b) { return b.dados.produtos.reduce((s, p) => s + num(p.preco) * num(p.vendidos), 0); },
            somaLoja(b, k) { return fmt(b.dados.produtos.reduce((s, p) => s + num(p[k]), 0)); },
            linkSeguro(u) { return /^https:\/\/[^\s]+$/i.test(String(u || '').trim()) ? String(u).trim() : '#'; },

            /* ---------- lista de compras ---------- */
            addCompra(b, el) {
                const t = el.value.trim();
                if (!t) return;
                b.dados.itens.push({ id: uid(), t, q: 1, p: 0, f: false });
                el.value = '';
            },
            totCompra(b, comprados) { return b.dados.itens.filter(i => !!i.f === comprados).reduce((s, i) => s + num(i.q) * num(i.p), 0); },
            qtdCompra(b, comprados) { return b.dados.itens.filter(i => !!i.f === comprados).length; },
            limparComprados(b) { b.dados.itens = b.dados.itens.filter(i => !i.f); },

            /* ---------- documento (currículo, contrato, resumo…) ---------- */
            secoesModelo(m) { return (MODELOS[m] || MODELOS.branco).secoes.map(([t, texto]) => ({ id: uid(), t, texto })); },
            trocarModelo(b, m) {
                if (!m || !MODELOS[m]) return;
                const preenchido = b.dados.secoes.some(s => s.texto.trim());
                if (preenchido && !confirm('Trocar o modelo substitui as seções atuais. Continuar?')) return;
                b.dados.modelo = m;
                b.dados.secoes = this.secoesModelo(m);
                if (m === 'curriculo') { b.dados.layout = 'faixa'; b.dados.fonte = 'sans'; }
                if (m === 'contrato') { b.dados.layout = 'coluna'; b.dados.fonte = 'serif'; }
                if (b.titulo === 'Documento' || Object.values(MODELOS).some(x => x.nome === b.titulo)) b.titulo = MODELOS[m].nome;
            },
            imprimirDoc(b) {
                const d = b.dados, w = window.open('', '_blank');
                if (!w) { this.aviso('Permita pop-ups para imprimir.'); return; }
                const cor = COR_OK.test(d.cor) ? d.cor : PALETA[0];
                const secs = d.secoes.map((s, i) => `<section class="${i === 0 && d.layout === 'faixa' ? 'cab' : ''}"><h2>${esc(s.t)}</h2><p>${esc(s.texto).replace(/\n/g, '<br>')}</p></section>`).join('');
                w.document.write(`<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>${esc(b.titulo)}</title><style>
                    body{font-family:${FONTES[d.fonte] || FONTES.sans};color:#1b1f24;margin:2cm;line-height:1.5}
                    h2{font-size:1rem;margin:0 0 .25rem;border-bottom:2px solid ${cor};padding-bottom:.15rem}
                    section{break-inside:avoid;margin-bottom:1rem}p{margin:0}
                    .cab{background:${cor};color:#fff;padding:1rem;border-radius:6px}.cab h2{border-color:#fff3;font-size:1.5rem}
                    main{${d.layout === 'duas' ? 'columns:2;column-gap:1.5cm' : ''}}
                    </style><main>${secs}</main><script>onload=()=>print()<\/script></html>`);
                w.document.close();
            },

            /* ---------- tabletop: mapa com grade e peças arrastáveis ---------- */
            ttUI(b) {
                const k = 'tt.' + b.id;
                if (!this.ui[k]) this.ui[k] = { sels: [], arrasta: false, caixa: null, nome: '', cor: '#a78bfa', forma: 'circulo', tam: 1, imgNova: '', enq: { z: 1, x: 0, y: 0 }, menuNovo: false, menuSel: false, des: desPadrao() };
                return this.ui[k];
            },
            ttFormaNome(f) { return { circulo: 'Círculo', quadrado: 'Quadrado', losango: 'Losango' }[f] || 'Círculo'; },
            ttCols(b) { return limitar(Math.round(num(b.dados.cols)) || 16, 4, 60); },
            ttRows(b) { return limitar(Math.round(num(b.dados.rows)) || 12, 4, 60); },
            ttCel(b) { return limitar(Math.round(num(b.dados.cel)) || 40, 20, 80); },
            ttTam(p) { return limitar(Math.round(num(p.tam)) || 1, 1, 4); },
            ttForma(p) { return TT_FORMAS.includes(p.forma) ? p.forma : 'circulo'; },
            ttInicial(p) { return Array.from(String(p.nome || '').trim()).slice(0, 2).join('').toUpperCase(); },
            ttEstilo(b) {
                const c = this.ttCel(b);
                return `width:${this.ttCols(b) * c}px;height:${this.ttRows(b) * c}px;--c:${c}px;`;
            },
            ttPecaEstilo(b, p) {
                const c = this.ttCel(b), t = this.ttTam(p);
                const cor = COR_OK.test(p.cor || '') ? p.cor : '#a78bfa';
                return `left:${num(p.x) * c}px;top:${num(p.y) * c}px;width:${t * c}px;height:${t * c}px;--pc:${cor};`;
            },

            /* seleção (várias peças) */
            ttSelecionada(b, p) { return this.ttUI(b).sels.includes(p.id); },
            ttSelecionadas(b) { const s = this.ttUI(b).sels; return (b.dados.pecas || []).filter(p => s.includes(p.id)); },
            /** A peça selecionada, só quando há exatamente uma (usada para editar o nome). */
            ttSel(b) { const l = this.ttSelecionadas(b); return l.length === 1 ? l[0] : null; },
            /** Valor comum de um atributo na seleção; '' quando as peças divergem. */
            ttValor(b, campo) {
                const l = this.ttSelecionadas(b);
                if (!l.length) return '';
                const ler = p => campo === 'tam' ? this.ttTam(p) : campo === 'forma' ? this.ttForma(p) : (COR_OK.test(p.cor || '') ? p.cor : '#a78bfa');
                const v = ler(l[0]);
                return l.every(p => ler(p) === v) ? v : '';
            },
            /** Aplica cor, formato ou tamanho a todas as peças selecionadas. */
            ttAplicar(b, campo, valor) {
                const l = this.ttSelecionadas(b);
                if (campo === 'forma' && !TT_FORMAS.includes(valor)) return;
                if (campo === 'cor' && !COR_OK.test(valor || '')) return;
                l.forEach(p => { p[campo] = campo === 'tam' ? limitar(Math.round(num(valor)) || 1, 1, 4) : valor; });
                if (campo === 'tam') this.ttLimitar(b);
            },

            /** Primeira posição livre t×t (se estiver tudo ocupado, volta para o canto). */
            ttLivre(b, t = 1) {
                const C = this.ttCols(b), R = this.ttRows(b);
                const ocupado = (x, y) => b.dados.pecas.some(p => {
                    const s = this.ttTam(p);
                    return x < num(p.x) + s && x + t > num(p.x) && y < num(p.y) + s && y + t > num(p.y);
                });
                for (let y = 0; y + t <= R; y++) for (let x = 0; x + t <= C; x++) if (!ocupado(x, y)) return [x, y];
                return [0, 0];
            },
            ttAdicionar(b, def) {
                b.dados.pecas ??= [];
                if (b.dados.pecas.length >= TT_MAX_PECAS) { this.aviso(`Limite de ${TT_MAX_PECAS} peças neste mapa.`); return; }
                const u = this.ttUI(b);
                const d = def || { nome: u.nome, forma: u.forma, cor: u.cor, tam: u.tam };
                const t = limitar(Math.round(num(d.tam)) || 1, 1, 4);
                let nome = txt(d.nome).trim().slice(0, 24) || 'Peça';
                if (def) { // peças prontas ganham número: Inimigo, Inimigo 2, Inimigo 3…
                    const n = b.dados.pecas.filter(p => txt(p.nome) === def.nome || txt(p.nome).startsWith(def.nome + ' ')).length;
                    if (n > 0) nome = `${def.nome} ${n + 1}`;
                }
                const [x, y] = this.ttLivre(b, t);
                const peca = {
                    id: uid(), nome, x, y, tam: t,
                    forma: TT_FORMAS.includes(d.forma) ? d.forma : 'circulo',
                    cor: COR_OK.test(d.cor || '') ? d.cor : '#a78bfa',
                };
                if (!def && u.imgNova) { // imagem escolhida no dropdown de atributos
                    const id = uid(), k = `${b.id}.i.${id}`;
                    this.imagens[k] = u.imgNova;
                    Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(u.imgNova)).catch(() => this.aviso('Não consegui salvar a imagem da peça.'));
                    peca.img = id;
                    // enquadramento escolhido na prévia (zoom e posição)
                    const enq = this.ttEnqAjustar(u.enq?.z, u.enq?.x, u.enq?.y);
                    peca.iz = enq.z; peca.ix = enq.x; peca.iy = enq.y;
                    u.enq = { z: 1, x: 0, y: 0 };
                    u.imgNova = '';
                }
                b.dados.pecas.push(peca);
                u.sels = [peca.id];
                if (!def) u.nome = '';
            },
            /** Duplica todas as peças selecionadas (a imagem e o enquadramento são compartilhados, sem novo envio). */
            ttDuplicar(b) {
                const orig = this.ttSelecionadas(b);
                if (!orig.length) return;
                if (b.dados.pecas.length + orig.length > TT_MAX_PECAS) { this.aviso(`Limite de ${TT_MAX_PECAS} peças neste mapa.`); return; }
                const novas = [];
                orig.forEach(p => {
                    const [x, y] = this.ttLivre(b, this.ttTam(p));
                    const n = { ...copia(p), id: uid(), x, y };
                    b.dados.pecas.push(n);
                    novas.push(n.id);
                });
                this.ttUI(b).sels = novas;
            },
            ttRemoverSel(b) {
                const ids = this.ttUI(b).sels.slice();
                b.dados.pecas = (b.dados.pecas || []).filter(p => !ids.includes(p.id));
                this.ttUI(b).sels = [];
                this.ttLimparImgs(b);
            },
            /** Mantém colunas, linhas e casa em limites válidos e traz de volta as peças que ficaram fora do mapa. */
            ttLimitar(b) {
                const C = this.ttCols(b), R = this.ttRows(b);
                b.dados.cols = C; b.dados.rows = R; b.dados.cel = this.ttCel(b);
                (b.dados.pecas || []).forEach(p => {
                    const t = this.ttTam(p);
                    p.x = limitar(num(p.x), 0, Math.max(0, C - t));
                    p.y = limitar(num(p.y), 0, Math.max(0, R - t));
                });
            },

            /* ---------- "Minhas peças": modelos de peça salvos (nome, formato, cor, tamanho, imagem e enquadramento) ----------
               Ficam em estado.pecasSalvas e valem para todos os tabletops. A imagem de cada modelo fica em img.ps.<id>.
               Ao usar um modelo, a imagem é copiada para o bloco (img.<bloco>.i.p<id>), como as demais peças. */
            /** Cria ou atualiza (mesmo nome) um modelo. `d` traz nome, forma, cor, tam, iz, ix, iy; `src` é a imagem (data URL) ou ''. */
            ttGravarModelo(d, src) {
                const lista = this.estado.pecasSalvas;
                const nome = String(d.nome || '').trim().slice(0, 24) || 'Peça';
                let m = lista.find(x => x.nome.toLowerCase() === nome.toLowerCase());
                const novo = !m;
                if (novo && lista.length >= MAX_MODELOS) { this.aviso(`Limite de ${MAX_MODELOS} peças salvas. Exclua alguma para salvar outra.`); return false; }
                const id = m ? m.id : uid();
                const enq = enqAjustar(d.iz, d.ix, d.iy);
                const dados = {
                    id, nome,
                    forma: TT_FORMAS.includes(d.forma) ? d.forma : 'circulo',
                    cor: COR_OK.test(d.cor || '') ? d.cor : '#a78bfa',
                    tam: limitar(Math.round(num(d.tam)) || 1, 1, 4),
                    iz: enq.z, ix: enq.x, iy: enq.y,
                    img: !!src,
                };
                const k = `ps.${id}`;
                if (src) {
                    this.imagens[k] = src;
                    Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(src)).catch(() => this.aviso('Não consegui salvar a imagem da peça.'));
                } else if (this.imagens[k]) this.apagarImagens(k);
                if (m) Object.assign(m, dados); else lista.push(dados);
                return true;
            },
            /** Salva as peças selecionadas no mapa como modelos. */
            ttSalvarSelecao(b) {
                const sel = this.ttSelecionadas(b);
                if (!sel.length) return;
                let n = 0;
                for (const p of sel) {
                    const src = p.img ? (this.imagens[`${b.id}.i.${p.img}`] || '') : '';
                    if (!this.ttGravarModelo({ nome: p.nome, forma: this.ttForma(p), cor: p.cor, tam: this.ttTam(p), iz: p.iz, ix: p.ix, iy: p.iy }, src)) break;
                    n++;
                }
                if (n) this.aviso(n === 1 ? 'Peça salva em "Minhas peças".' : `${n} peças salvas em "Minhas peças".`);
            },
            /** Salva o que está no formulário "Nova peça" (nome, formato, cor, tamanho, imagem e zoom) sem colocar no mapa. */
            ttSalvarModeloNovo(b) {
                const u = this.ttUI(b);
                if (!String(u.nome || '').trim()) { this.aviso('Dê um nome à peça para salvar o modelo.'); return; }
                const enq = u.enq || { z: 1, x: 0, y: 0 };
                if (this.ttGravarModelo({ nome: u.nome, forma: u.forma, cor: u.cor, tam: u.tam, iz: enq.z, ix: enq.x, iy: enq.y }, u.imgNova || ''))
                    this.aviso('Modelo salvo em "Minhas peças".');
            },
            /** Coloca no mapa uma peça criada a partir do modelo. */
            ttUsarModelo(b, m) {
                b.dados.pecas ??= [];
                if (b.dados.pecas.length >= TT_MAX_PECAS) { this.aviso(`Limite de ${TT_MAX_PECAS} peças neste mapa.`); return; }
                const t = this.ttTam(m);
                const n = b.dados.pecas.filter(p => txt(p.nome) === m.nome || txt(p.nome).startsWith(m.nome + ' ')).length;
                const nome = (n > 0 ? `${m.nome} ${n + 1}` : m.nome).slice(0, 24);
                const [x, y] = this.ttLivre(b, t);
                const peca = { id: uid(), nome, x, y, tam: t, forma: this.ttForma(m), cor: COR_OK.test(m.cor || '') ? m.cor : '#a78bfa' };
                const src = m.img ? this.imagens[`ps.${m.id}`] : '';
                if (src) {
                    const imgId = 'p' + m.id, k = `${b.id}.i.${imgId}`;
                    if (!this.imagens[k]) {
                        this.imagens[k] = src;
                        Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(src)).catch(() => this.aviso('Não consegui salvar a imagem da peça.'));
                    }
                    const enq = enqAjustar(m.iz, m.ix, m.iy);
                    peca.img = imgId; peca.iz = enq.z; peca.ix = enq.x; peca.iy = enq.y;
                }
                b.dados.pecas.push(peca);
                this.ttUI(b).sels = [peca.id];
            },
            ttRemoverModelo(m) {
                if (!confirm(`Excluir "${m.nome}" de Minhas peças? As peças que já estão nos mapas continuam.`)) return;
                this.estado.pecasSalvas = this.estado.pecasSalvas.filter(x => x.id !== m.id);
                this.apagarImagens(`ps.${m.id}`);
            },
            ttModeloImg(m) { return m.img ? (this.imagens[`ps.${m.id}`] || '') : ''; },
            /** Miniatura do modelo, no formato e na cor da peça. */
            ttModeloThumb(m) {
                const forma = { circulo: 'border-radius:9999px;', quadrado: 'border-radius:.25rem;', losango: 'clip-path:polygon(50% 0,100% 50%,50% 100%,0 50%);' }[this.ttForma(m)];
                const cor = COR_OK.test(m.cor || '') ? m.cor : '#a78bfa';
                return `display:inline-block;flex-shrink:0;width:1.5rem;height:1.5rem;overflow:hidden;background:${cor};${forma}`;
            },
            ttModeloImgEstilo(m) { return `display:block;width:100%;height:100%;object-fit:cover;transform-origin:center;${this.ttEnqEstilo(m)}`; },

            /** Arrasta e solta: se a peça faz parte da seleção, move o grupo inteiro; tudo gruda nas casas. */
            ttDown(e, b, p) {
                if (e.button > 0) return;
                e.preventDefault();
                const u = this.ttUI(b);
                if (e.shiftKey || e.ctrlKey || e.metaKey) { // soma ou tira da seleção, sem arrastar
                    const i = u.sels.indexOf(p.id);
                    if (i >= 0) u.sels.splice(i, 1); else u.sels.push(p.id);
                    return;
                }
                if (!u.sels.includes(p.id)) u.sels = [p.id];
                u.arrasta = true;
                const el = e.currentTarget;
                el.setPointerCapture?.(e.pointerId);
                const c = this.ttCel(b), C = this.ttCols(b), R = this.ttRows(b);
                const grupo = this.ttSelecionadas(b).map(q => ({ q, x: num(q.x), y: num(q.y), t: this.ttTam(q) }));
                const minDx = Math.max(...grupo.map(g => -g.x)), maxDx = Math.min(...grupo.map(g => C - g.t - g.x));
                const minDy = Math.max(...grupo.map(g => -g.y)), maxDy = Math.min(...grupo.map(g => R - g.t - g.y));
                const x0 = e.clientX, y0 = e.clientY;
                const mover = ev => {
                    const dx = limitar(Math.round((ev.clientX - x0) / c), minDx, maxDx);
                    const dy = limitar(Math.round((ev.clientY - y0) / c), minDy, maxDy);
                    grupo.forEach(g => { g.q.x = g.x + dx; g.q.y = g.y + dy; });
                };
                const fim = () => {
                    u.arrasta = false;
                    el.removeEventListener('pointermove', mover);
                    el.removeEventListener('pointerup', fim);
                    el.removeEventListener('pointercancel', fim);
                };
                el.addEventListener('pointermove', mover);
                el.addEventListener('pointerup', fim);
                el.addEventListener('pointercancel', fim);
            },

            /** Arrastar no fundo do mapa com o botão esquerdo desenha um retângulo e seleciona as peças que ele toca. */
            /* O listener fica na grade inteira (sem .self): o mapa de fundo e a camada de desenho cobrem a grade,
               então o alvo do clique quase nunca é a própria grade e a seleção em caixa nunca começava.
               Aqui o clique é ignorado só quando pertence a outra coisa: uma peça (que arrasta) ou o modo desenho. */
            ttCaixaIniciar(e, b) {
                if (e.button > 0) return;                                  // só o botão esquerdo
                if (e.target.closest?.('.wf-tt-peca')) return;             // peça: quem trata é ttDown
                if (this.ttDes(b).on) return;                              // desenhando: quem trata é ttDesDown
                const u = this.ttUI(b);
                const aditivo = e.shiftKey || e.ctrlKey || e.metaKey;
                if (e.pointerType === 'touch') { if (!aditivo) u.sels = []; return; } // no toque, arrastar continua rolando a tela
                e.preventDefault();
                const grade = e.currentTarget;
                const cena = grade.closest('.wf-tt-cena');
                grade.setPointerCapture?.(e.pointerId);
                const base = aditivo ? u.sels.slice() : [];
                if (!aditivo) u.sels = [];
                const c = this.ttCel(b);
                const r0 = grade.getBoundingClientRect();
                // o ponto inicial fica em coordenadas da grade, então continua certo quando a cena rola
                const x0 = limitar(e.clientX - r0.left, 0, r0.width), y0 = limitar(e.clientY - r0.top, 0, r0.height);
                let cx = e.clientX, cy = e.clientY, moveu = false, quadro = 0;

                const atualizar = () => {
                    const r = grade.getBoundingClientRect();
                    const x1 = limitar(cx - r.left, 0, r.width), y1 = limitar(cy - r.top, 0, r.height);
                    const x = Math.min(x0, x1), y = Math.min(y0, y1), w = Math.abs(x1 - x0), h = Math.abs(y1 - y0);
                    if (!moveu && w < 4 && h < 4) return;
                    moveu = true;
                    u.caixa = { x, y, w, h };
                    const ids = (b.dados.pecas || []).filter(p => {
                        const t = this.ttTam(p) * c, px = num(p.x) * c, py = num(p.y) * c;
                        return px < x + w && px + t > x && py < y + h && py + t > y;
                    }).map(p => p.id);
                    u.sels = [...new Set([...base, ...ids])];
                };
                // perto da borda da cena, ela rola sozinha: dá para selecionar áreas maiores que a janela
                const rolar = () => {
                    quadro = 0;
                    if (!cena) return;
                    const k = cena.getBoundingClientRect(), borda = 32, vel = 18;
                    const dx = cx < k.left + borda ? -vel : cx > k.right - borda ? vel : 0;
                    const dy = cy < k.top + borda ? -vel : cy > k.bottom - borda ? vel : 0;
                    if (!dx && !dy) return;
                    const antesX = cena.scrollLeft, antesY = cena.scrollTop;
                    cena.scrollLeft += dx; cena.scrollTop += dy;
                    if (cena.scrollLeft !== antesX || cena.scrollTop !== antesY) atualizar();
                    quadro = requestAnimationFrame(rolar);
                };
                const mover = ev => {
                    cx = ev.clientX; cy = ev.clientY;
                    atualizar();
                    if (!quadro) quadro = requestAnimationFrame(rolar);
                };
                const fim = () => {
                    u.caixa = null;
                    if (quadro) cancelAnimationFrame(quadro);
                    grade.removeEventListener('pointermove', mover);
                    grade.removeEventListener('pointerup', fim);
                    grade.removeEventListener('pointercancel', fim);
                };
                grade.addEventListener('pointermove', mover);
                grade.addEventListener('pointerup', fim);
                grade.addEventListener('pointercancel', fim);
            },
            ttCaixaEstilo(b) {
                const k = this.ttUI(b).caixa;
                return k ? `left:${k.x}px;top:${k.y}px;width:${k.w}px;height:${k.h}px;` : '';
            },

            /** Teclado: setas movem a seleção uma casa; Delete exclui; Esc limpa a seleção. */
            ttTecla(e, b, p) {
                const u = this.ttUI(b);
                if (e.key === 'Escape') { u.sels = []; return; }
                if (!u.sels.includes(p.id)) u.sels = [p.id];
                const m = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
                if (m) {
                    e.preventDefault();
                    const C = this.ttCols(b), R = this.ttRows(b), grupo = this.ttSelecionadas(b);
                    const dx = limitar(m[0], Math.max(...grupo.map(q => -num(q.x))), Math.min(...grupo.map(q => C - this.ttTam(q) - num(q.x))));
                    const dy = limitar(m[1], Math.max(...grupo.map(q => -num(q.y))), Math.min(...grupo.map(q => R - this.ttTam(q) - num(q.y))));
                    grupo.forEach(q => { q.x = num(q.x) + dx; q.y = num(q.y) + dy; });
                    return;
                }
                if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); this.ttRemoverSel(b); }
            },

            /* imagens das peças: cada imagem é guardada uma vez (img.<bloco>.i.<id>) e as peças só apontam para ela */
            ttImg(b, p) { return p.img ? (this.imagens[`${b.id}.i.${p.img}`] || '') : ''; },

            /* ---------- enquadramento da imagem da peça (zoom + posição), sem alterar a imagem original ----------
               Fica na própria peça: iz = zoom (1 a 5), ix e iy = deslocamento em % do tamanho da peça. */
            /** Mantém o zoom entre 1× e 5× e o deslocamento dentro do limite em que a imagem ainda cobre a peça toda. */
            ttEnqAjustar(z, x, y) { return enqAjustar(z, x, y); },
            ttPrimeiraImg(b) { return this.ttSelecionadas(b).find(p => p.img) || null; },
            /** modo: 'novo' (peça que será criada) ou 'sel' (peças selecionadas). */
            ttEnqLer(b, modo) {
                if (modo === 'novo') { const u = this.ttUI(b); u.enq ||= { z: 1, x: 0, y: 0 }; return u.enq; }
                const p = this.ttPrimeiraImg(b);
                return p ? this.ttEnqAjustar(p.iz, p.ix, p.iy) : { z: 1, x: 0, y: 0 };
            },
            ttEnqSet(b, modo, v) {
                const e = this.ttEnqAjustar(v.z, v.x, v.y);
                if (modo === 'novo') { this.ttUI(b).enq = e; return; }
                this.ttSelecionadas(b).filter(p => p.img).forEach(p => { p.iz = e.z; p.ix = e.x; p.iy = e.y; });
            },
            ttEnqZoom(b, modo, z) { const c = this.ttEnqLer(b, modo); this.ttEnqSet(b, modo, { z, x: c.x, y: c.y }); },
            ttEnqReset(b, modo) { this.ttEnqSet(b, modo, { z: 1, x: 0, y: 0 }); },
            ttEnqSrc(b, modo) { return modo === 'novo' ? this.ttUI(b).imgNova : (this.ttPrimeiraImg(b) ? this.ttImg(b, this.ttPrimeiraImg(b)) : ''); },
            ttEnqForma(b, modo) { return modo === 'novo' ? this.ttUI(b).forma : (this.ttValor(b, 'forma') || 'circulo'); },
            /** Estilo da imagem dentro da peça no mapa. */
            ttEnqEstilo(p) { const e = this.ttEnqAjustar(p.iz, p.ix, p.iy); return `transform:translate(${e.x}%,${e.y}%) scale(${e.z});`; },
            /** Estilo da imagem na prévia do editor. */
            ttEnqEstiloPrev(b, modo) { const e = this.ttEnqLer(b, modo); return `transform:translate(${e.x}%,${e.y}%) scale(${e.z});`; },
            /** Arrastar a prévia move a imagem dentro do recorte. */
            ttEnqArrastar(e, b, modo) {
                if (e.button > 0) return;
                e.preventDefault();
                const el = e.currentTarget;
                el.setPointerCapture?.(e.pointerId);
                const w = el.getBoundingClientRect().width || 144, c = { ...this.ttEnqLer(b, modo) }, x0 = e.clientX, y0 = e.clientY;
                const mover = ev => this.ttEnqSet(b, modo, { z: c.z, x: c.x + (ev.clientX - x0) / w * 100, y: c.y + (ev.clientY - y0) / w * 100 });
                const fim = () => {
                    el.removeEventListener('pointermove', mover);
                    el.removeEventListener('pointerup', fim);
                    el.removeEventListener('pointercancel', fim);
                };
                el.addEventListener('pointermove', mover);
                el.addEventListener('pointerup', fim);
                el.addEventListener('pointercancel', fim);
            },

            async ttLerImgNova(b, arq) {
                if (!arq || !arq.type.startsWith('image/')) return;
                try {
                    const u = this.ttUI(b);
                    u.imgNova = await Q.reduzir(arq, 256, 0.8);
                    u.enq = { z: 1, x: 0, y: 0 }; // imagem nova começa sem zoom
                }
                catch { this.aviso('Não consegui ler a imagem.'); }
            },
            async ttLerImgSel(b, arq) {
                const alvos = this.ttSelecionadas(b);
                if (!arq || !arq.type.startsWith('image/') || !alvos.length) return;
                const id = uid(), k = `${b.id}.i.${id}`;
                try {
                    this.imagens[k] = await Q.reduzir(arq, 256, 0.8);
                    await Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(this.imagens[k]));
                    alvos.forEach(p => { p.img = id; p.iz = 1; p.ix = 0; p.iy = 0; });
                    this.ttLimparImgs(b);
                } catch { delete this.imagens[k]; this.aviso('Não consegui salvar a imagem da peça.'); }
            },
            ttTirarImgSel(b) {
                this.ttSelecionadas(b).forEach(p => { delete p.img; delete p.iz; delete p.ix; delete p.iy; });
                this.ttLimparImgs(b);
            },
            /** Apaga do servidor as imagens que nenhuma peça usa mais. */
            ttLimparImgs(b) {
                const usadas = new Set((b.dados.pecas || []).map(p => p.img).filter(Boolean));
                const pre = `${b.id}.i.`;
                Object.keys(this.imagens).filter(k => k.startsWith(pre) && !usadas.has(k.slice(pre.length))).forEach(k => this.apagarImagens(k));
            },

            ttMapa(b) { return this.imagens[`${b.id}.mapa`] || ''; },
            async ttLerMapa(b, arq) {
                if (!arq || !arq.type.startsWith('image/')) return;
                const k = `${b.id}.mapa`;
                try {
                    this.imagens[k] = await Q.reduzir(arq, 1400, 0.75);
                    await Q.enviar(`${this.cfg.url}/img.${k}`, JSON.stringify(this.imagens[k]));
                } catch { this.aviso('Não consegui salvar a imagem do mapa.'); }
            },
            ttTirarMapa(b) { this.apagarImagens(`${b.id}.mapa`); },

            /* ---------- tabletop: desenho (pincel, borracha, círculo, quadrado, cone, triângulo) ----------
               Tudo fica em b.dados.desenho (lista única de traços e formas), então é salvo com o bloco e vai para a biblioteca.
               O SVG usa um viewBox fixo de TT_REF unidades por casa: mudar o tamanho da casa não desalinha nada.
               O histórico de desfazer/refazer fica só na memória (ui), não no estado salvo. */
            ttDes(b) { return this.ttUI(b).des; },
            ttViewBox(b) { return `0 0 ${this.ttCols(b) * TT_REF} ${this.ttRows(b) * TT_REF}`; },
            ttDesenhoSvg(b) { return (b.dados.desenho || []).map(it => it.t === 'pincel' ? tracoSvg(it) : formaSvg(it)).join(''); },
            ttVivoSvg(b) { const v = this.ttDes(b).vivo; return v ? (v.t === 'pincel' ? tracoSvg(v) : formaSvg(v)) : ''; },
            ttDesRaio(b) { return limitar(num(this.ttDes(b).larg) || 3, 1, 24) + 8; },
            ttFerramenta(b, f) {
                const d = this.ttDes(b);
                d.cx = null; d.cy = null; d.vivo = null;
                if (d.on && d.ferr === f) { d.on = false; return; }
                d.on = true; d.ferr = f;
                this.ttUI(b).sels = [];
            },
            ttDesPt(svg, b, ev) {
                const r = svg.getBoundingClientRect(), W = this.ttCols(b) * TT_REF, H = this.ttRows(b) * TT_REF;
                return [r1(limitar((ev.clientX - r.left) * W / (r.width || W), 0, W)), r1(limitar((ev.clientY - r.top) * H / (r.height || H), 0, H))];
            },
            ttDesHover(e, b) {
                const d = this.ttDes(b);
                if (!d.on || d.ferr !== 'borracha') return;
                const [x, y] = this.ttDesPt(e.currentTarget, b, e);
                d.cx = x; d.cy = y;
            },
            ttDesFora(b) { const d = this.ttDes(b); d.cx = null; d.cy = null; },

            /** Passa a borracha de `de` até `ate`. Traços do pincel são cortados; formas tocadas somem. Devolve true se mudou algo. */
            ttApagarCaminho(b, de, ate, raio) {
                const passo = Math.max(2, raio * 0.5);
                const n = Math.max(1, Math.ceil(Math.hypot(ate[0] - de[0], ate[1] - de[1]) / passo));
                let lista = b.dados.desenho || [], mudou = false;
                for (let k = 1; k <= n; k++) {
                    const x = de[0] + (ate[0] - de[0]) * k / n, y = de[1] + (ate[1] - de[1]) * k / n, novos = [];
                    lista.forEach(it => {
                        if (it.t === 'pincel') {
                            const pedacos = cortarTraco(it, x, y, raio);
                            if (!pedacos) { novos.push(it); return; }
                            mudou = true;
                            pedacos.forEach(pts => novos.push(novoPincel(it, pts)));
                        } else if (formaTocada(it, x, y, raio)) mudou = true;
                        else novos.push(it);
                    });
                    lista = novos;
                }
                if (mudou) b.dados.desenho = lista;
                return mudou;
            },

            ttDesDown(e, b) {
                const d = this.ttDes(b);
                if (!d.on || e.button > 0) return;
                e.preventDefault();
                const svg = e.currentTarget;
                svg.setPointerCapture?.(e.pointerId);
                b.dados.desenho ??= [];
                const ferr = d.ferr, cor = COR_OK.test(d.cor) ? d.cor : '#f472b6', larg = limitar(num(d.larg) || 3, 1, 24), raio = larg + 8;
                const antes = JSON.stringify(b.dados.desenho);
                const gravar = () => { d.hist.push(antes); if (d.hist.length > TT_HIST) d.hist.shift(); d.refaz = []; };
                const pt = ev => this.ttDesPt(svg, b, ev);
                const inicio = pt(e);
                const pts = [inicio];
                let anterior = inicio, gravou = false;
                const apagar = q => {
                    if (this.ttApagarCaminho(b, anterior, q, raio) && !gravou) { gravar(); gravou = true; }
                    anterior = q;
                };
                const mover = ev => {
                    const q = pt(ev);
                    if (ferr === 'borracha') { apagar(q); return; }
                    if (ferr === 'pincel') {
                        const u = pts[pts.length - 1];
                        if (Math.hypot(q[0] - u[0], q[1] - u[1]) < 2.5) return;
                        pts.push(q);
                        d.vivo = { t: 'pincel', c: cor, w: larg, d: caminho(pts) };
                        return;
                    }
                    d.vivo = { t: ferr, c: cor, w: larg, f: !!d.preencher, x1: inicio[0], y1: inicio[1], x2: q[0], y2: q[1] };
                };
                const fim = () => {
                    svg.removeEventListener('pointermove', mover);
                    svg.removeEventListener('pointerup', fim);
                    svg.removeEventListener('pointercancel', fim);
                    const v = d.vivo; d.vivo = null;
                    if (ferr === 'borracha') return;
                    if (b.dados.desenho.length >= MAX_DESENHO) { this.aviso('Limite de itens de desenho neste mapa. Desfaça ou limpe alguns.'); return; }
                    if (ferr === 'pincel') {
                        gravar();
                        b.dados.desenho.push(novoPincel({ c: cor, w: larg }, pts));
                        return;
                    }
                    if (!v || Math.hypot(v.x2 - v.x1, v.y2 - v.y1) < 3) return;
                    gravar();
                    b.dados.desenho.push({ id: uid(), ...v });
                };
                svg.addEventListener('pointermove', mover);
                svg.addEventListener('pointerup', fim);
                svg.addEventListener('pointercancel', fim);
                if (ferr === 'borracha') apagar(inicio);
                else if (ferr === 'pincel') d.vivo = { t: 'pincel', c: cor, w: larg, d: caminho(pts) };
            },
            ttDesDesfazer(b) {
                const d = this.ttDes(b);
                if (!d.hist.length) return;
                d.refaz.push(JSON.stringify(b.dados.desenho || []));
                b.dados.desenho = JSON.parse(d.hist.pop());
            },
            ttDesRefazer(b) {
                const d = this.ttDes(b);
                if (!d.refaz.length) return;
                d.hist.push(JSON.stringify(b.dados.desenho || []));
                b.dados.desenho = JSON.parse(d.refaz.pop());
            },
            ttDesLimpar(b) {
                if (!(b.dados.desenho || []).length || !confirm('Apagar todo o desenho deste mapa? As peças ficam.')) return;
                const d = this.ttDes(b);
                d.hist.push(JSON.stringify(b.dados.desenho));
                d.refaz = [];
                b.dados.desenho = [];
            },

            /* ---------- mapa mental: tamanho considera o desenho ---------- */
            mmTam(b) {
                const nos = b.dados.nos || [];
                let w = nos.length ? Math.max(...nos.map(n => n.x)) + MM.w + 60 : 0;
                let h = nos.length ? Math.max(...nos.map(n => n.y)) + MM.h + 60 : 0;
                (b.dados.tracos || []).forEach(t => { w = Math.max(w, (t.m?.[0] || 0) + 30); h = Math.max(h, (t.m?.[1] || 0) + 30); });
                return { w: Math.max(w, 680), h: Math.max(h, 380) };
            },

            /* ---------- desenho livre nos mapas mentais ---------- */
            pen(b) {
                if (!this.ui[b.id]) this.ui[b.id] = { on: false, borr: false, cor: '#f472b6', larg: 3, vivo: '', cx: null, cy: null };
                return this.ui[b.id];
            },
            penModo(b, modo) {
                const p = this.pen(b);
                const borr = modo === 'borracha';
                p.cx = null; p.cy = null;
                if (p.on && p.borr === borr) { p.on = false; p.borr = false; return; }
                p.on = true; p.borr = borr;
            },
            penRaio(b) { return num(this.ui[b.id]?.larg || 3) + 8; },
            penHover(e, b) {
                const p = this.ui[b.id];
                if (!p || !p.on || !p.borr) return;
                const r = e.currentTarget.getBoundingClientRect();
                p.cx = r1(e.clientX - r.left); p.cy = r1(e.clientY - r.top);
            },
            penFora(b) { const p = this.ui[b.id]; if (p) { p.cx = null; p.cy = null; } },
            tracosSvg(b) { return (b.dados.tracos || []).map(tracoSvg).join(''); },
            /** Passa a borracha de `de` até `ate` (em passos curtos, para não deixar buracos quando o mouse é rápido). */
            apagarCaminho(b, de, ate, raio) {
                const passo = Math.max(2, raio * 0.5);
                const n = Math.max(1, Math.ceil(Math.hypot(ate[0] - de[0], ate[1] - de[1]) / passo));
                let lista = b.dados.tracos || [], mudou = false;
                for (let k = 1; k <= n; k++) {
                    const x = de[0] + (ate[0] - de[0]) * k / n, y = de[1] + (ate[1] - de[1]) * k / n;
                    const novos = [];
                    lista.forEach(t => {
                        const pedacos = cortarTraco(t, x, y, raio);
                        if (!pedacos) { novos.push(t); return; }
                        mudou = true;
                        pedacos.forEach(pts => novos.push(novoTraco(t, pts)));
                    });
                    lista = novos;
                }
                if (mudou) b.dados.tracos = lista;
            },
            penDown(e, b) {
                const p = this.pen(b);
                if (!p.on || e.button > 0) return;
                e.preventDefault();
                const svg = e.currentTarget;
                svg.setPointerCapture?.(e.pointerId);
                const pt = ev => { const r = svg.getBoundingClientRect(); return [r1(ev.clientX - r.left), r1(ev.clientY - r.top)]; };
                const raio = this.penRaio(b);
                const fechar = fim => {
                    svg.removeEventListener('pointermove', mover);
                    svg.removeEventListener('pointerup', fim);
                    svg.removeEventListener('pointercancel', fim);
                };
                let pts = [], anterior = pt(e);
                const mover = ev => {
                    if (p.borr) {
                        const q = pt(ev);
                        this.apagarCaminho(b, anterior, q, raio);
                        anterior = q;
                        return;
                    }
                    const q = pt(ev), u = pts[pts.length - 1];
                    if (Math.hypot(q[0] - u[0], q[1] - u[1]) < 2.5) return;
                    pts.push(q);
                    p.vivo = caminho(pts);
                };
                const fim = () => {
                    fechar(fim);
                    if (!p.borr && pts.length) {
                        b.dados.tracos ??= [];
                        if (b.dados.tracos.length >= MAX_TRACOS) { this.aviso('Limite de traços neste mapa. Desfaça ou limpe alguns.'); }
                        else {
                            b.dados.tracos.push({
                                id: uid(), c: COR_OK.test(p.cor) ? p.cor : '#f472b6', w: Math.min(Math.max(num(p.larg), 1), 24),
                                d: caminho(pts), p: pts.flat(), m: [Math.ceil(Math.max(...pts.map(q => q[0]))), Math.ceil(Math.max(...pts.map(q => q[1])))],
                            });
                        }
                    }
                    p.vivo = '';
                };
                svg.addEventListener('pointermove', mover);
                svg.addEventListener('pointerup', fim);
                svg.addEventListener('pointercancel', fim);
                if (p.borr) this.apagarCaminho(b, anterior, anterior, raio); else { pts = [pt(e)]; p.vivo = caminho(pts); }
            },
            penDesfazer(b) { b.dados.tracos?.pop(); },
            penLimpar(b) { if ((b.dados.tracos || []).length && confirm('Apagar todo o desenho deste mapa? Os nós ficam.')) b.dados.tracos = []; },
        };
    };
})();