/* Work Flower · motor do quadro (compartilhado)
   Arrastar, redimensionar, cor, blocos livres (texto, tabela, lista, imagem, mapa mental) e salvamento.
   Usado por carteira.js. O corpo.js pode migrar para cá depois, sem pressa. */
(function () {
    'use strict';

    const pad = n => String(n).padStart(2, '0');
    const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const hoje = () => iso(new Date());
    const agora = () => { const d = new Date(); return `${pad(d.getHours())}:${pad(d.getMinutes())}`; };
    const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    const r1 = n => Math.round((Number(n) || 0) * 10) / 10;
    const num = v => { const n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : 0; };
    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
    const snap = v => Math.round(v / 8) * 8;
    const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
    const dataBR = s => { const [, m, d] = s.split('-'); return `${d}/${m}`; };

    /* ---------- bloco de imagem: só o cabeçalho e a imagem inteira (vale para Corpo e Mente) ---------- */
    (function () {
        const st = document.createElement('style');
        st.textContent = `
            .wf-corpo:has(.wf-img-bloco.com-img) { padding: 0 !important; overflow-x: hidden; overflow-y: auto; }
            .wf-corpo:has(.wf-img-bloco.com-img) > div { height: auto !important; min-height: 0 !important; padding: 0 !important; }
            .wf-img-bloco img { display: block; width: 100%; height: auto; }
            .wf-img-vazio {
                display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
                min-height: 7rem; height: 100%; padding: 1rem; text-align: center;
                border: 1px dashed var(--linha); border-radius: .5rem;
            }`;
        document.head.appendChild(st);
    })();

    /* ---------- cartão ativo: ?embed=secao/id mostra só aquele cartão da página ---------- */
    const SEG = /^[A-Za-z0-9._-]{1,60}$/;
    const EMBED = (() => {
        const v = new URLSearchParams(location.search).get('embed') || '';
        const i = v.indexOf('/');
        if (i < 1) return null;
        const sec = v.slice(0, i), bid = v.slice(i + 1);
        return SEG.test(sec) && SEG.test(bid) ? { sec, bid } : null;
    })();
    if (EMBED) {
        const st = document.createElement('style');
        st.textContent = `
            html.wf-embed-pendente body { visibility: hidden !important; }
            html.wf-embed, html.wf-embed body { background: transparent !important; overflow: hidden !important; }
            .wf-oculto { display: none !important; }
            .wf-caminho { margin: 0 !important; padding: 0 !important; max-width: none !important; width: auto !important; height: auto !important;
                          min-height: 0 !important; border: 0 !important; position: static !important; transform: none !important; }
            .wf-item.wf-alvo { position: fixed !important; inset: 0 !important; width: 100% !important; height: 100% !important; z-index: 1 !important; }
            .wf-alvo .wf-redim, .wf-alvo .wf-cor, .wf-alvo .wf-topo .mini-btn[title="Salvar na biblioteca"] { display: none !important; }
            .wf-alvo .wf-topo { cursor: default; }
            .wf-embed-vazio { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem;
                              text-align: center; font: 14px/1.4 sans-serif; color: #94a3b8; }`;
        document.head.appendChild(st);
        document.documentElement.classList.add('wf-embed-pendente');
    }

    /** Esconde tudo da página, menos o caminho até o cartão pedido; o cartão passa a ocupar a janela inteira. */
    function mostrarSoAlvo(alvo) {
        document.documentElement.classList.add('wf-embed');
        alvo.classList.add('wf-alvo');
        let n = alvo;
        while (n && n.parentElement && n !== document.documentElement) {
            const pai = n.parentElement;
            [...pai.children].forEach(irmao => { if (irmao !== n) irmao.classList.add('wf-oculto'); });
            pai.classList.add('wf-caminho');
            n = pai;
        }
        document.documentElement.classList.remove('wf-embed-pendente');
    }

    const PALETA = ['#2f6fc7', '#2dd4bf', '#a78bfa', '#fb923c', '#f472b6', '#f2c230', '#94a3b8', '#4ade80'];
    const MM = { w: 188, h: 38 }; // tamanho de um nó do mapa mental

    async function enviar(url, texto, metodo = 'PUT') {
        const r = await fetch(url, {
            method: metodo,
            credentials: 'same-origin',
            keepalive: !texto || texto.length < 50000,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: metodo === 'PUT' ? JSON.stringify({ valor: texto }) : undefined,
        });
        if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
        if (!r.ok) throw new Error(String(r.status));
    }

    /** Reduz a imagem escolhida (máx. 1100 px) para não pesar no banco. */
    function reduzir(arquivo, max = 1100, qualidade = 0.82) {
        return new Promise((ok, falha) => {
            const img = new Image();
            const url = URL.createObjectURL(arquivo);
            img.onload = () => {
                const k = Math.min(1, max / Math.max(img.width, img.height));
                const cv = document.createElement('canvas');
                cv.width = Math.round(img.width * k); cv.height = Math.round(img.height * k);
                const cx = cv.getContext('2d');
                cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
                cx.drawImage(img, 0, 0, cv.width, cv.height);
                URL.revokeObjectURL(url);
                ok(cv.toDataURL('image/jpeg', qualidade));
            };
            img.onerror = () => { URL.revokeObjectURL(url); falha(new Error('imagem')); };
            img.src = url;
        });
    }

    /* ---------- formato dos dados de cada tipo de bloco ---------- */
    const TIPOS_COM_DADOS = ['texto', 'tabela', 'lista', 'mapa', 'documento'];

    /** Item da biblioteca salvo sem conteúdo (versão antiga do salvamento gravava `dados` vazio). Não há o que colar. */
    function dadosPerdidos(item) {
        if (!item || !TIPOS_COM_DADOS.includes(item.tipo)) return false;
        const d = item.dados;
        return !d || typeof d !== 'object' || Array.isArray(d) || Object.keys(d).length === 0;
    }

    /** Devolve uma cópia de `dados` com todos os campos que o tipo precisa (aceita null, texto solto e conteúdo antigo). */
    function normalizarDados(tipo, bruto) {
        const d = bruto && typeof bruto === 'object' && !Array.isArray(bruto) ? JSON.parse(JSON.stringify(bruto)) : {};
        const txt = v => (v == null || typeof v === 'object' ? '' : String(v));
        const arr = v => (Array.isArray(v) ? v : (v && typeof v === 'object' ? Object.values(v) : []));
        switch (tipo) {
            case 'texto':
                d.texto = txt(d.texto);
                if (!['p', 'm', 'g'].includes(d.tam)) d.tam = 'm';
                break;
            case 'tabela': {
                let cab = arr(d.cab).map(txt);
                let linhas = arr(d.linhas).map(l => arr(l).map(txt));
                const n = Math.max(cab.length, ...linhas.map(l => l.length), 1);
                cab = Array.from({ length: n }, (_, j) => cab[j] ?? '');
                linhas = linhas.map(l => Array.from({ length: n }, (_, j) => l[j] ?? ''));
                d.cab = cab;
                d.linhas = linhas.length ? linhas : [cab.map(() => '')];
                break;
            }
            case 'lista':
                d.itens = arr(d.itens)
                    .map(i => (typeof i === 'string' ? { t: i } : i))
                    .filter(i => i && typeof i === 'object')
                    .map(i => ({ ...i, id: i.id ? String(i.id) : uid(), t: txt(i.t ?? i.texto), f: !!i.f }));
                break;
            case 'mapa': {
                const nos = arr(d.nos).filter(n => n && typeof n === 'object')
                    .map(n => ({ ...n, id: n.id ? String(n.id) : uid(), t: txt(n.t), x: Number(n.x) || 0, y: Number(n.y) || 0, pai: n.pai || null, cor: n.cor || PALETA[0] }));
                d.nos = nos.length ? nos : [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }];
                break;
            }
            case 'documento': {
                const secoes = arr(d.secoes).filter(x => x && typeof x === 'object')
                    .map(x => ({ ...x, id: x.id ? String(x.id) : uid(), t: txt(x.t), texto: txt(x.texto) }));
                d.secoes = secoes.length ? secoes : [{ id: uid(), t: '', texto: '' }];
                d.modelo = d.modelo || 'branco'; d.layout = d.layout || 'coluna'; d.fonte = d.fonte || 'sans'; d.cor = d.cor || PALETA[0];
                break;
            }
            case 'imagem':
                d.ajuste = d.ajuste || 'cover';
                d.legenda = txt(d.legenda);
                d.prop = Number(d.prop) > 0 ? Number(d.prop) : 0; // altura ÷ largura da imagem
                break;
        }
        return d;
    }

    /* ---------- converter um cartão fixo em conteúdo estático para a biblioteca ---------- */
    function textoCelula(c) {
        const f = c.querySelector('input,textarea,select');
        if (f) return f.tagName === 'SELECT' ? (f.selectedOptions[0]?.textContent || '').trim() : String(f.value || '').trim();
        const k = c.cloneNode(true);
        k.querySelectorAll('button,svg').forEach(x => x.remove());
        return k.textContent.replace(/\s+/g, ' ').trim();
    }

    /** Lê o texto visível de um cartão (inclui valores de campos), com quebras de linha nos blocos. */
    function textoDe(raiz) {
        const out = [];
        const BLOCO = /^(DIV|P|LI|TR|H1|H2|H3|UL|SECTION|LABEL)$/;
        (function ir(n) {
            if (n.nodeType === 3) { const t = n.textContent.replace(/\s+/g, ' ').trim(); if (t) out.push(t + ' '); return; }
            if (n.nodeType !== 1) return;
            const tag = n.tagName.toUpperCase();
            if (['BUTTON', 'SVG', 'SCRIPT', 'STYLE', 'TEMPLATE'].includes(tag)) return;
            if (n.hidden || n.style.display === 'none') return;
            if (tag === 'INPUT' || tag === 'TEXTAREA') { if (!['checkbox', 'file', 'color', 'range'].includes(n.type) && n.value) out.push(n.value + ' '); return; }
            if (tag === 'SELECT') { const o = n.selectedOptions[0]; if (o && o.value) out.push(o.textContent.trim() + ' '); return; }
            n.childNodes.forEach(ir);
            if (BLOCO.test(tag)) out.push('\n');
        })(raiz);
        return out.join('').replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    /** Decide o formato da cópia: tabela, imagem ou texto. Devolve null se não houver nada para guardar. */
    function cartaoParaBloco(corpo) {
        const t = corpo.querySelector('table');
        if (t) {
            let cab = [...t.querySelectorAll('thead th')].map(textoCelula);
            let linhas = [...t.querySelectorAll('tbody tr')].map(tr => [...tr.children].map(textoCelula));
            const n = Math.max(cab.length, ...linhas.map(l => l.length), 1);
            cab = Array.from({ length: n }, (_, j) => cab[j] || '');
            linhas = linhas.map(l => Array.from({ length: n }, (_, j) => l[j] || ''));
            // tira colunas sem título e sem conteúdo (botões, gráficos)
            const manter = cab.map((c, j) => c !== '' || linhas.some(l => l[j] !== ''));
            cab = cab.filter((_, j) => manter[j]);
            linhas = linhas.map(l => l.filter((_, j) => manter[j]));
            if (cab.length) return { tipo: 'tabela', dados: { cab, linhas: linhas.length ? linhas : [cab.map(() => '')] } };
        }
        const img = corpo.querySelector('img');
        if (img && /^data:image\//.test(img.src)) return { tipo: 'imagem', dados: { ajuste: 'cover', legenda: '' }, imagem: img.src };
        const texto = textoDe(corpo).slice(0, 5000);
        return texto ? { tipo: 'texto', dados: { texto, tam: 'm' } } : null;
    }

    /** Propriedades de interface que o motor precisa no componente Alpine. */
    function estadoUI() {
        return { larg: 1088, col: 1088, ox: 0, salvo: 'ok', msg: '', zTop: 10, arrastando: null, menuBloco: false, destaque: null, _v: 0, embutido: !!EMBED };
    }

    /** Métodos do motor. PADRAO = posição inicial dos itens por aba (área de 1088 px). */
    function metodos(PADRAO) {
        return {
            /* ---------- avisos e salvamento ---------- */
            aviso(txt) { this.msg = txt; clearTimeout(this._m); this._m = setTimeout(() => { this.msg = ''; }, 2600); },
            ir(sec) { this.aba = sec; },
            rotuloSalvo() {
                return { ok: 'Tudo salvo', pendente: 'Alterações…', salvando: 'Salvando…', erro: 'Erro ao salvar. Tentando de novo' }[this.salvo];
            },
            agendar() {
                this._v++;
                this.salvo = 'pendente';
                clearTimeout(this._t);
                this._t = setTimeout(() => this.gravarEstado(), 800);
            },
            async gravarEstado() {
                clearTimeout(this._t);
                const v = this._v;
                this.salvo = 'salvando';
                try {
                    await enviar(this.cfg.url + '/estado', JSON.stringify(this.estado));
                    if (v === this._v) this.salvo = 'ok';
                } catch {
                    this.salvo = 'erro';
                    this._t = setTimeout(() => this.gravarEstado(), 10000);
                }
            },

            /* ---------- quadro: medir, arrastar, redimensionar, cor ---------- */
            initQuadro() {
                // avisa a biblioteca (configurações) que esta página aceita blocos colados
                window.WFQuadro.ativo = true;
                // tipos de bloco que ESTA página sabe desenhar (a Mente tem "documento", as outras não)
                window.WFQuadro.tipos = [...(this.tiposBloco || []).map(t => t.id), 'vivo'];
                if (!this._colarOuvindo) {
                    this._colarOuvindo = true;
                    window.addEventListener('wf-colar', e => this.colarBloco(e.detail));
                }
                if (EMBED) this.prepararEmbed();
                this.$nextTick(() => {
                    const area = this.$refs.area, pai = area.parentElement;
                    const medir = () => {
                        this.col = pai.clientWidth || 1088;
                        if (this.empilhado()) {
                            area.style.width = ''; area.style.marginLeft = '';
                            this.ox = 0; this.larg = this.col;
                            return;
                        }
                        const janela = document.documentElement.clientWidth;
                        const esq = Math.round(pai.getBoundingClientRect().left);
                        area.style.width = janela + 'px';
                        area.style.marginLeft = (-esq) + 'px';
                        this.ox = esq; this.larg = janela;
                    };
                    medir();
                    const ro = new ResizeObserver(medir);
                    ro.observe(pai);
                    ro.observe(document.documentElement);

                    // imagens que já existiam: ajusta o card ao tamanho delas (uma única vez, por aba)
                    this.corrigirImagens(this.aba);
                    this.$watch('aba', v => this.$nextTick(() => this.corrigirImagens(v)));
                });
            },
            /** Modo cartão ativo (dentro de um iframe): abre a aba certa e mostra só o cartão pedido. */
            prepararEmbed() {
                const procurar = () => document.querySelector(`.wf-item[data-sec="${EMBED.sec}"][data-bid="${EMBED.bid}"]`);
                let tentativas = 0;
                const t = setInterval(() => {
                    this.aba = EMBED.sec;
                    const alvo = procurar();
                    if (alvo) { clearInterval(t); mostrarSoAlvo(alvo); return; }
                    if (++tentativas > 50) {
                        clearInterval(t);
                        const m = document.createElement('div');
                        m.className = 'wf-embed-vazio';
                        m.textContent = 'Este cartão não existe mais na página original.';
                        document.documentElement.appendChild(m);
                    }
                }, 100);
            },
            /** Endereço do cartão ativo de um bloco colado (só caminhos internos /areas/...). */
            urlVivo(b) {
                const d = b.dados || {};
                if (!/^\/areas\/[a-z]+$/.test(d.pagina || '') || !SEG.test(d.sec || '') || !SEG.test(d.bid || '')) return 'about:blank';
                return `${d.pagina}?embed=${encodeURIComponent(d.sec + '/' + d.bid)}`;
            },
            empilhado() { return window.matchMedia('(max-width: 899px)').matches; },
            maiorZ() {
                let z = 10;
                Object.values(this.estado.layout).forEach(sec => Object.values(sec).forEach(l => { z = Math.max(z, l.z || 0); }));
                return z;
            },
            base(sec, id) {
                const p = PADRAO[sec]?.[id] || { x: 0, y: 0, w: 360, h: 260 };
                const f = this.col > 0 && this.col < 1088 ? this.col / 1088 : 1;
                return { x: Math.round(p.x * f), y: p.y, w: Math.round(p.w * f), h: p.h };
            },
            rect(sec, id) { return this.estado.layout[sec]?.[id] || this.base(sec, id); },
            caixa(sec, id) {
                const l = this.rect(sec, id);
                const w = Math.min(l.w, this.larg);
                const x = clamp(l.x, -this.ox, Math.max(-this.ox, this.larg - this.ox - w));
                return `--x:${x + this.ox}px;--y:${l.y}px;--w:${w}px;--h:${l.h}px;z-index:${l.z || 1};--wc:${l.cor || 'var(--prim)'}`;
            },
            entrada(sec, id) {
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                const L = this.estado.layout[sec];
                if (!L[id]) L[id] = this.base(sec, id);
                return L[id];
            },
            alturaConteudo(sec) {
                const ids = new Set([...Object.keys(PADRAO[sec] || {}), ...Object.keys(this.estado.layout[sec] || {})]);
                let alt = 0;
                ids.forEach(id => { const l = this.rect(sec, id); alt = Math.max(alt, l.y + l.h); });
                return alt;
            },
            alturaQuadro(sec) { return Math.max(360, this.alturaConteudo(sec) + 40); },
            frente(sec, id) {
                if (EMBED) return;
                const l = this.rect(sec, id);
                if ((l.z || 1) >= this.zTop) return;
                this.entrada(sec, id).z = ++this.zTop;
            },
            arrastar(e, sec, id) {
                if (EMBED) return;
                if (e.button !== 0 || this.empilhado()) return;
                if (e.target.closest('button,input,select,textarea,label,a')) return;
                e.preventDefault();
                const l = this.entrada(sec, id);
                const larg = this.larg, ox = this.ox;
                const x0 = e.clientX, y0 = e.clientY, lx = l.x, ly = l.y;
                this.arrastando = id;
                const mover = ev => {
                    l.x = clamp(snap(lx + ev.clientX - x0), -ox, Math.max(-ox, larg - ox - Math.min(l.w, larg)));
                    l.y = Math.max(0, snap(ly + ev.clientY - y0));
                };
                const fim = () => {
                    this.arrastando = null;
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            redimensionar(e, sec, id, minw, minh) {
                if (EMBED) return;
                if (this.empilhado()) return;
                const l = this.entrada(sec, id);
                const larg = this.larg, ox = this.ox;
                const x0 = e.clientX, y0 = e.clientY, w0 = l.w, h0 = l.h;
                // bloco de imagem: a proporção fica travada, para nunca sobrar espaço nem cortar
                const bl = this.estado.blocos.find(x => x.id === id);
                const prop = bl && bl.tipo === 'imagem' && this.imagens[id] ? Number(bl.dados.prop) || 0 : 0;
                const topo = prop ? this.alturaTopo(id) : 0;
                this.arrastando = id;
                const mover = ev => {
                    l.w = clamp(snap(w0 + ev.clientX - x0), minw, Math.max(minw, larg - ox - l.x));
                    l.h = prop
                        ? Math.ceil((l.w - 2) * prop) + topo + 3
                        : Math.max(minh, snap(h0 + ev.clientY - y0));
                };
                const fim = () => {
                    this.arrastando = null;
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            corDe(sec, id) {
                return this.estado.layout[sec]?.[id]?.cor
                    || getComputedStyle(document.documentElement).getPropertyValue('--prim').trim() || '#2f6fc7';
            },
            setCor(sec, id, cor) { this.entrada(sec, id).cor = cor; },
            reorganizar() {
                const sec = this.aba, W = this.col || 1088;
                const novo = {};
                let y = 0;
                Object.keys(PADRAO[sec] || {}).forEach(id => { const b = this.base(sec, id); y = Math.max(y, b.y + b.h); });
                if (y) y += 16;
                let x = 0, linha = 0;
                this.blocosDe(sec).forEach(b => {
                    const o = this.estado.layout[sec]?.[b.id] || { w: 360, h: 260 };
                    const w = Math.min(o.w, W);
                    if (x + w > W) { x = 0; y += linha + 16; linha = 0; }
                    novo[b.id] = { x, y, w, h: o.h, z: 1, ...(o.cor ? { cor: o.cor } : {}) };
                    x += w + 16; linha = Math.max(linha, o.h);
                });
                this.estado.layout[sec] = novo;
                this.aviso('Layout reorganizado.');
            },

            /* ---------- blocos livres ---------- */
            blocosDe(sec) { return this.estado.blocos.filter(b => b.secao === sec); },
            novoBloco(sec, tipo) {
                const def = {
                    texto: [340, 240, 'Texto', { texto: '', tam: 'm' }],
                    tabela: [480, 280, 'Tabela', { cab: ['Item', 'Valor'], linhas: [['', ''], ['', '']] }],
                    lista: [320, 300, 'Lista', { itens: [] }],
                    imagem: [360, 200, 'Imagem', { ajuste: 'contain', legenda: '', prop: 0 }],
                    mapa: [720, 480, 'Mapa mental', { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }] }],
                }[tipo];
                if (!def) return;
                const id = 'b' + uid();
                const W = this.col || 1088, y0 = this.alturaConteudo(sec);
                this.estado.blocos.push({ id, secao: sec, tipo, titulo: def[2], links: [], dados: def[3] });
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = { x: 0, y: y0 ? y0 + 16 : 0, w: Math.min(def[0], W), h: def[1], z: ++this.zTop };
                this.menuBloco = false;
                this.aviso(`${def[2]} adicionado ao fim da aba. Arraste para onde quiser.`);
            },
            removerBloco(b) {
                if (!confirm(`Excluir o bloco "${b.titulo || 'sem título'}"?`)) return;
                const { id, secao } = b;
                const i = this.estado.blocos.findIndex(x => x.id === id);
                if (i >= 0) this.estado.blocos.splice(i, 1);
                if (this.estado.layout[secao]) delete this.estado.layout[secao][id];
                if (this.imagens[id]) {
                    delete this.imagens[id];
                    enviar(`${this.cfg.url}/img.${id}`, null, 'DELETE').catch(() => {});
                }
            },
            /* ---------- biblioteca: guardar e colar blocos ---------- */
            /** Copia o bloco (conteúdo, título, cor, tamanho e imagem) para a biblioteca do usuário. */
            guardarBloco(b) {
                const l = this.rect(b.secao, b.id);
                const item = {
                    id: uid(),
                    nome: (b.titulo || '').trim() || 'Sem título',
                    tipo: b.tipo,
                    titulo: b.titulo || '',
                    dados: normalizarDados(b.tipo, b.dados),
                    cor: /^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : null,
                    w: Math.round(l.w), h: Math.round(l.h),
                    imagem: this.imagens[b.id] || null,
                    criado: new Date().toISOString(),
                };
                this.enviarBiblioteca(item, 'Bloco salvo na biblioteca (engrenagem → Biblioteca).');
            },
            /** Cartões fixos (contas, relatórios, tarefas…): guarda uma cópia estática, como tabela, texto ou imagem. */
            guardarCartao(btn) {
                const el = btn.closest('.wf-item');
                const corpo = el?.querySelector('.wf-corpo');
                if (!corpo) return;
                const titulo = (el.querySelector('.wf-topo input.wf-titulo')?.value || el.querySelector('.wf-topo h2')?.textContent || 'Cartão').trim();
                const cor = el.querySelector('.wf-cor')?.value || '';
                const sec = el.dataset.sec, bid = el.dataset.bid;
                if (SEG.test(sec || '') && SEG.test(bid || '') && /^\/areas\/[a-z]+$/.test(location.pathname)) {
                    // cartão ativo: ao colar, abre o cartão original (com o JS e os dados da página de origem)
                    this.enviarBiblioteca({
                        id: uid(), nome: titulo, tipo: 'vivo', titulo,
                        dados: { pagina: location.pathname, sec, bid },
                        cor: /^#[0-9a-fA-F]{6}$/.test(cor) ? cor : null,
                        w: Math.max(100, Math.round(el.offsetWidth)), h: Math.max(80, Math.round(el.offsetHeight)),
                        imagem: null, criado: new Date().toISOString(),
                    }, `“${titulo}” salvo como cartão ativo: ao colar, ele continua funcionando e usando os dados de origem.`);
                    return;
                }
                const c = cartaoParaBloco(corpo);
                if (!c) { this.aviso('Não há nada para salvar neste cartão.'); return; }
                this.enviarBiblioteca({
                    id: uid(), nome: titulo, tipo: c.tipo, titulo, dados: c.dados,
                    cor: /^#[0-9a-fA-F]{6}$/.test(cor) ? cor : null,
                    w: Math.max(100, Math.round(el.offsetWidth)), h: Math.max(80, Math.round(el.offsetHeight)),
                    imagem: c.imagem || null, criado: new Date().toISOString(),
                }, `“${titulo}” salvo como cópia (${{ tabela: 'tabela', texto: 'texto', imagem: 'imagem' }[c.tipo]}) com os dados de agora.`);
            },
            enviarBiblioteca(item, ok) {
                if (!window.WFBiblioteca) { this.aviso('A biblioteca não está disponível nesta página.'); return; }
                // limites do servidor: nome até 120 caracteres, título até 200
                item.nome = String(item.nome || '').trim().slice(0, 120) || 'Sem título';
                item.titulo = String(item.titulo || '').slice(0, 200);
                window.WFBiblioteca.adicionar(item)
                    .then(() => this.aviso(ok))
                    .catch(e => this.aviso(
                        e.message === 'cheia' ? 'A biblioteca está cheia. Exclua algum item.'
                            : e.message === '422' ? 'A biblioteca recusou este bloco (dados inválidos). Tente salvar de novo.'
                                : 'Não consegui salvar na biblioteca.'));
            },
            /** Cola uma cópia independente de um item da biblioteca no fim da aba atual. */
            colarBloco(item) {
                const sec = this.aba;
                if (!sec || !item || !item.tipo) return;
                if (item.tipo !== 'vivo' && this.tiposBloco && !this.tiposBloco.some(t => t.id === item.tipo)) {
                    this.aviso('Esta página não tem esse tipo de bloco. Cole em outra página.');
                    return;
                }
                if (dadosPerdidos(item)) {
                    this.aviso('Este item da biblioteca está sem conteúdo (foi salvo por uma versão antiga). Exclua-o e salve o bloco de novo.');
                    return;
                }
                const dados = normalizarDados(item.tipo, item.dados);
                // ids novos: a cópia não pode compartilhar identidade com o original
                if (item.tipo === 'lista') (dados.itens || []).forEach(i => { i.id = uid(); });
                if (item.tipo === 'documento') (dados.secoes || []).forEach(s => { s.id = uid(); });
                if (item.tipo === 'mapa') {
                    const m = {};
                    (dados.nos || []).forEach(n => { m[n.id] = uid(); });
                    (dados.nos || []).forEach(n => { n.id = m[n.id]; n.pai = n.pai ? (m[n.pai] || null) : null; });
                }
                const id = 'b' + uid();
                const W = this.col || 1088, y0 = this.alturaConteudo(sec);
                this.estado.blocos.push({ id, secao: sec, tipo: item.tipo, titulo: item.titulo || item.nome || 'Bloco', links: [], dados });
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = {
                    x: 0, y: y0 ? y0 + 16 : 0, w: Math.min(item.w || 360, W), h: item.h || 260, z: ++this.zTop,
                    ...(item.cor ? { cor: item.cor } : {}),
                };
                if (item.imagem) {
                    this.imagens[id] = item.imagem;
                    enviar(`${this.cfg.url}/img.${id}`, JSON.stringify(item.imagem)).catch(() => this.aviso('Não consegui salvar a imagem colada.'));
                    if (item.tipo === 'imagem') {
                        // ajusta o card ao tamanho da imagem colada
                        const bl = this.estado.blocos.find(x => x.id === id);
                        this.$nextTick(() => { if (bl) this.ajustarImagem(bl, item.imagem); });
                    }
                }
                this.aviso('Bloco colado no fim da aba. Arraste para onde quiser.');
            },
            tabCol(b) { b.dados.cab.push('Coluna ' + (b.dados.cab.length + 1)); b.dados.linhas.forEach(l => l.push('')); },
            tabColRem(b, j) { if (b.dados.cab.length < 2) return; b.dados.cab.splice(j, 1); b.dados.linhas.forEach(l => l.splice(j, 1)); },
            tabLinha(b) { b.dados.linhas.push(b.dados.cab.map(() => '')); },
            addItem(b, el) {
                const t = el.value.trim();
                if (!t) return;
                b.dados.itens.push({ id: uid(), t, f: false });
                el.value = '';
            },
            remItem(b, id) { const i = b.dados.itens.findIndex(x => x.id === id); if (i >= 0) b.dados.itens.splice(i, 1); },

            /* ---------- imagem: o card se adapta ao tamanho da imagem ---------- */
            /** Altura do cabeçalho do cartão (cai para 44 px se a aba estiver escondida). */
            alturaTopo(id) {
                return document.querySelector(`.wf-item[data-bid="${id}"] .wf-topo`)?.offsetHeight || 44;
            },
            /** Mede a imagem e ajusta o card: largura natural (limitada à área) e altura pela proporção. */
            ajustarImagem(b, src) {
                return new Promise(ok => {
                    const im = new Image();
                    im.onload = () => {
                        const nw = im.naturalWidth, nh = im.naturalHeight;
                        if (nw && nh) {
                            b.dados.prop = nh / nw;
                            const l = this.entrada(b.secao, b.id);
                            const max = Math.max(220, Math.min(this.col || 1088, this.larg - this.ox - l.x));
                            l.w = Math.round(clamp(nw, 220, max));
                            // 2 px = bordas laterais; 3 px = bordas de cima (2) e de baixo (1)
                            l.h = Math.ceil((l.w - 2) * b.dados.prop) + this.alturaTopo(b.id) + 3;
                        }
                        ok();
                    };
                    im.onerror = () => ok();
                    im.src = src;
                });
            },
            /** Blocos de imagem antigos (sem proporção guardada) são ajustados uma vez. */
            corrigirImagens(sec) {
                this.blocosDe(sec)
                    .filter(b => b.tipo === 'imagem' && this.imagens[b.id] && !b.dados.prop)
                    .forEach(b => this.ajustarImagem(b, this.imagens[b.id]));
            },
            async lerImagem(b, arquivo) {
                if (!arquivo || !arquivo.type.startsWith('image/')) return;
                try {
                    const src = await reduzir(arquivo);
                    this.imagens[b.id] = src;
                    await this.ajustarImagem(b, src);
                    await enviar(`${this.cfg.url}/img.${b.id}`, JSON.stringify(src));
                } catch { this.aviso('Não consegui salvar a imagem.'); }
            },
            vincular(b, el) {
                const v = el.value; el.value = '';
                if (!v) return;
                const [t, id] = v.split(':');
                if (!b.links.some(l => l.t === t && String(l.id) === id)) b.links.push({ t, id });
            },

            /* ---------- mapa mental ---------- */
            mmTam(b) {
                const nos = b.dados.nos;
                return { w: Math.max(...nos.map(n => n.x)) + MM.w + 60, h: Math.max(...nos.map(n => n.y)) + MM.h + 60 };
            },
            mmLinhas(b) {
                return b.dados.nos.filter(n => n.pai).map(n => {
                    const pai = b.dados.nos.find(x => x.id === n.pai);
                    if (!pai) return '';
                    const dir = (n.x + MM.w / 2) >= (pai.x + MM.w / 2);
                    const x1 = dir ? pai.x + MM.w : pai.x, x2 = dir ? n.x : n.x + MM.w;
                    const y1 = pai.y + MM.h / 2, y2 = n.y + MM.h / 2, mx = (x1 + x2) / 2;
                    return `M${x1} ${y1} C${mx} ${y1} ${mx} ${y2} ${x2} ${y2}`;
                }).join(' ');
            },
            mmAdd(b, paiId) {
                const nos = b.dados.nos, pai = nos.find(n => n.id === paiId);
                if (!pai) return;
                const irmaos = nos.filter(n => n.pai === paiId).length;
                const cor = pai.pai ? pai.cor : PALETA[(irmaos + 1) % PALETA.length];
                nos.push({ id: uid(), t: 'Nova ideia', x: pai.x + MM.w + 48, y: pai.y + irmaos * (MM.h + 12), pai: paiId, cor });
            },
            mmRem(b, id) {
                const del = new Set([id]);
                let mudou = true;
                while (mudou) {
                    mudou = false;
                    b.dados.nos.forEach(n => { if (n.pai && del.has(n.pai) && !del.has(n.id)) { del.add(n.id); mudou = true; } });
                }
                b.dados.nos = b.dados.nos.filter(n => !del.has(n.id));
            },
            mmCor(n) { n.cor = PALETA[(PALETA.indexOf(n.cor) + 1) % PALETA.length]; },
            mmMover(e, b, n) {
                const x0 = e.clientX, y0 = e.clientY, nx = n.x, ny = n.y;
                const mover = ev => { n.x = Math.max(0, Math.round(nx + ev.clientX - x0)); n.y = Math.max(0, Math.round(ny + ev.clientY - y0)); };
                const fim = () => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
        };
    }

    window.WFQuadro = {
        util: { pad, iso, hoje, agora, uid, r1, num, clamp, snap, norm, dataBR },
        PALETA, MM, EMBED, enviar, reduzir, estadoUI, metodos, normalizarDados, dadosPerdidos,
    };
})();