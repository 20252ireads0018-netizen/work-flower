/* ===== Editor de escrita por blocos (bloco "texto" do Diverso) =====
   O conteúdo continua em b.dados.texto, como Markdown. */
(function () {
    'use strict';

    const LISTAS = ['ul', 'ol', 'todo'];
    const ITENS = [
        { id: 'p',       nome: 'Texto',               desc: 'Parágrafo simples',        ic: '¶',   k: 'texto paragrafo normal' },
        { id: 'h1',      nome: 'Título 1',            desc: 'Título grande',            ic: 'H1',  k: 'titulo heading h1' },
        { id: 'h2',      nome: 'Título 2',            desc: 'Título médio',             ic: 'H2',  k: 'titulo heading h2' },
        { id: 'h3',      nome: 'Título 3',            desc: 'Título pequeno',           ic: 'H3',  k: 'titulo heading h3' },
        { id: 'ul',      nome: 'Lista com marcadores', desc: 'Lista simples',           ic: '•',   k: 'lista bullet marcadores' },
        { id: 'ol',      nome: 'Lista numerada',      desc: 'Lista em ordem',           ic: '1.',  k: 'lista numerada ordenada' },
        { id: 'todo',    nome: 'Lista de tarefas',    desc: 'Itens com caixa de marcar', ic: '☑',  k: 'tarefa todo checklist caixa' },
        { id: 'quote',   nome: 'Citação',             desc: 'Trecho em destaque',       ic: '❝',   k: 'citacao quote' },
        { id: 'callout', nome: 'Destaque',            desc: 'Caixa de aviso ou nota',   ic: '💡',  k: 'destaque callout aviso nota' },
        { id: 'code',    nome: 'Código',              desc: 'Bloco de código',          ic: '</>', k: 'codigo code programacao' },
        { id: 'hr',      nome: 'Divisor',             desc: 'Linha separadora',         ic: '—',   k: 'divisor linha separador hr' },
    ];
    const PH = { h1: 'Título 1', h2: 'Título 2', h3: 'Título 3', ul: 'Item da lista', ol: 'Item da lista', todo: 'Tarefa', quote: 'Citação', callout: 'Escreva um destaque…' };
    const ATALHOS = [
        { re: /^(#{1,3}) /,        t: m => 'h' + m[1].length },
        { re: /^[-*+] /,           t: () => 'ul' },
        { re: /^\d+[.)] /,         t: () => 'ol' },
        { re: /^\[( |x|X)?\] /,    t: () => 'todo', f: m => /x/i.test(m[1] || '') },
        { re: /^> /,               t: () => 'quote' },
        { re: /^!> /,              t: () => 'callout' },
    ];

    const norm = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    let seq = 0;
    const nid = () => 'e' + Date.now().toString(36) + (seq++).toString(36);
    const novo = (t, x, extra) => Object.assign({ id: nid(), t: t, x: x || '', n: 0, f: false, l: '' }, extra || {});

    /* ---- Markdown -> blocos (sem perdas: uma linha = um bloco) ---- */
    function parse(txt) {
        const linhas = String(txt == null ? '' : txt).replace(/\r\n?/g, '\n').split('\n');
        const out = [];
        let i = 0;
        if (linhas.length === 1 && linhas[0] === '') return out;
        while (i < linhas.length) {
            const ln = linhas[i];
            let m;
            if ((m = /^```\s*([\w+#.-]*)\s*$/.exec(ln))) {
                const cod = [];
                i++;
                while (i < linhas.length && !/^```\s*$/.test(linhas[i])) cod.push(linhas[i++]);
                i++;
                out.push(novo('code', cod.join('\n'), { l: m[1] || '' }));
                continue;
            }
            i++;
            if (/^\s*([-*_])(\s*\1){2,}\s*$/.test(ln)) { out.push(novo('hr')); continue; }
            const ind = /^( *)/.exec(ln)[1].length;
            const nv = Math.min(3, Math.floor(ind / 2));
            const r = ln.slice(ind);
            if ((m = /^(#{1,3})\s+(.*)$/.exec(ln)))              out.push(novo('h' + m[1].length, m[2]));
            else if ((m = /^- \[( |x|X)\]\s?(.*)$/.exec(r)))      out.push(novo('todo', m[2], { n: nv, f: m[1] !== ' ' }));
            else if ((m = /^[-*+]\s+(.*)$/.exec(r)))              out.push(novo('ul', m[1], { n: nv }));
            else if ((m = /^\d+[.)]\s+(.*)$/.exec(r)))            out.push(novo('ol', m[1], { n: nv }));
            else if ((m = /^!>\s?(.*)$/.exec(ln)))                out.push(novo('callout', m[1]));
            else if ((m = /^>\s?(.*)$/.exec(ln)))                 out.push(novo('quote', m[1]));
            else                                                  out.push(novo('p', ln));
        }
        return out;
    }

    /* ---- Markdown inline -> HTML seguro ---- */
    function fmt(h) {
        return h
            .replace(/\*\*([^*\n]+?)\*\*/g, '<strong>$1</strong>')
            .replace(/~~([^~\n]+?)~~/g, '<s>$1</s>')
            .replace(/==([^=\n]+?)==/g, '<mark>$1</mark>')
            .replace(/(^|[^*\w])\*([^*\n]+?)\*(?![*\w])/g, '$1<em>$2</em>')
            .replace(/(^|[^_\w])_([^_\n]+?)_(?![_\w])/g, '$1<em>$2</em>');
    }
    function inline(s) {
        return String(s).split(/(`[^`\n]+`)/).map(function (p, k) {
            if (k % 2) return '<code class="wf-es-cod">' + esc(p.slice(1, -1)) + '</code>';
            const toks = [];
            let h = esc(p).replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, t, u) {
                const url = u.replace(/&amp;/g, '&');
                if (!/^(https?:\/\/|mailto:)/i.test(url)) return m;
                toks.push('<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + fmt(t) + '</a>');
                return '\u0001' + (toks.length - 1) + '\u0002';
            });
            h = fmt(h);
            return h.replace(/\u0001(\d+)\u0002/g, function (m, n) { return toks[+n]; });
        }).join('');
    }

    window.WFEscrita = function (b) {
        return {
            blocos: [], foco: null, menu: null, opcBl: null, sumario: false,
            arrastar: null, sobre: -1, dica: '', itens: ITENS, _ult: null, _t: null,

            iniciar() {
                if (!b.dados.tam) b.dados.tam = 'm';
                this.blocos = this.carregar(b.dados.texto);
                this._ult = this.serializar();
                this.$watch('blocos', () => {
                    this._ult = this.serializar();
                    if (b.dados.texto !== this._ult) b.dados.texto = this._ult;
                });
                // mudança vinda de fora (biblioteca, desfazer, etc.)
                this.$watch(() => b.dados.texto, v => {
                    if (v !== this._ult) { this.blocos = this.carregar(v); this._ult = this.serializar(); }
                });
            },
            carregar(t) { const r = parse(t); return r.length ? r : [novo('p')]; },

            serializar() {
                return this.blocos.map((bl, i) => {
                    const ind = '  '.repeat(bl.n || 0);
                    switch (bl.t) {
                        case 'h1': return '# ' + bl.x;
                        case 'h2': return '## ' + bl.x;
                        case 'h3': return '### ' + bl.x;
                        case 'ul': return ind + '- ' + bl.x;
                        case 'ol': return ind + this.numero(i) + '. ' + bl.x;
                        case 'todo': return ind + '- [' + (bl.f ? 'x' : ' ') + '] ' + bl.x;
                        case 'quote': return '> ' + bl.x;
                        case 'callout': return '!> ' + bl.x;
                        case 'hr': return '---';
                        case 'code': return '```' + (bl.l || '') + '\n' + bl.x + '\n```';
                        default: return bl.x;
                    }
                }).join('\n');
            },

            /* ---------- auxiliares ---------- */
            idx(id) { return this.blocos.findIndex(x => x.id === id); },
            atual() { return this.blocos.find(x => x.id === this.foco) || null; },
            ta(id) { return this.$root.querySelector('[data-es="' + id + '"] textarea.wf-es-ta'); },
            fit(t) { if (!t) return; t.style.height = 'auto'; const h = t.scrollHeight; if (h) t.style.height = h + 'px'; },
            numero(i) {
                const bl = this.blocos[i]; let c = 1;
                for (let j = i - 1; j >= 0; j--) {
                    const o = this.blocos[j];
                    if (o.t !== 'ol' || (o.n || 0) < (bl.n || 0)) break;
                    if ((o.n || 0) === (bl.n || 0)) c++;
                }
                return c;
            },
            html(bl) {
                if (!bl.x) {
                    if (bl.t === 'p') return this.blocos.length === 1 ? '<span class="wf-es-ph">Escreva algo ou digite “/” para ver os comandos…</span>' : '<br>';
                    return PH[bl.t] ? '<span class="wf-es-ph">' + PH[bl.t] + '</span>' : '<br>';
                }
                return inline(bl.x);
            },
            focar(id, pos) {
                this.foco = id;
                const tentar = n => {
                    const t = this.ta(id);
                    if (!t || t.offsetParent === null) { if (n < 6) setTimeout(() => tentar(n + 1), 20); return; }
                    t.focus(); this.fit(t);
                    const p = (pos == null || pos < 0) ? t.value.length : Math.min(pos, t.value.length);
                    t.setSelectionRange(p, p);
                };
                this.$nextTick(() => tentar(0));
            },
            avisar(m) { this.dica = m; clearTimeout(this._t); this._t = setTimeout(() => { this.dica = ''; }, 2500); },

            /* ---------- menu "/" ---------- */
            itensMenu() {
                if (!this.menu) return [];
                const q = norm(this.menu.q);
                return ITENS.filter(it => !q || norm(it.nome + ' ' + it.k).includes(q));
            },
            setTipo(bl, id) {
                if (bl.t === 'code' && id !== 'code') bl.x = bl.x.replace(/\r?\n/g, ' ');
                bl.t = id;
                if (!LISTAS.includes(id)) bl.n = 0;
                if (id !== 'todo') bl.f = false;
                if (id !== 'code') bl.l = '';
            },
            aplicar(id, bl) {
                this.menu = null;
                const i = this.idx(bl.id);
                bl.x = '';
                if (id === 'hr') {
                    bl.t = 'hr'; bl.n = 0; bl.f = false; bl.l = '';
                    const nb = novo('p');
                    this.blocos.splice(i + 1, 0, nb);
                    this.focar(nb.id, 0);
                    return;
                }
                this.setTipo(bl, id);
                const t = this.ta(bl.id); if (t) t.value = '';
                this.focar(bl.id, 0);
            },
            mudarTipo(id) {
                const bl = this.atual(); if (!bl) return;
                this.setTipo(bl, id);
                this.focar(bl.id, -1);
            },

            /* ---------- digitação ---------- */
            entrada(e, bl) {
                const t = e.target; let v = t.value;
                if (bl.t !== 'code' && v.indexOf('\n') >= 0) { v = v.replace(/\r?\n/g, ' '); t.value = v; }
                bl.x = v;
                if (bl.t === 'p' || bl.t === 'ul') {
                    for (const a of ATALHOS) {
                        const m = a.re.exec(v); if (!m) continue;
                        const tipo = a.t(m);
                        if (bl.t === 'ul' && tipo !== 'todo') continue;
                        bl.t = tipo; bl.f = a.f ? a.f(m) : false;
                        bl.x = v.slice(m[0].length); t.value = bl.x; t.setSelectionRange(0, 0);
                        break;
                    }
                }
                if (bl.t !== 'code') {
                    const s = /^\/([^\s/]*)$/.exec(bl.x);
                    if (s) this.menu = { id: bl.id, q: s[1], i: 0 };
                    else if (this.menu && this.menu.id === bl.id) this.menu = null;
                }
                this.fit(t);
            },
            ver(e, bl) { if (e.target.closest && e.target.closest('a')) return; this.focar(bl.id, -1); },

            tecla(e, bl, i) {
                const k = e.key, mod = e.ctrlKey || e.metaKey;
                const m = (this.menu && this.menu.id === bl.id) ? this.itensMenu() : null;
                if (m && m.length) {
                    if (k === 'ArrowDown') { e.preventDefault(); this.menu.i = (this.menu.i + 1) % m.length; return; }
                    if (k === 'ArrowUp') { e.preventDefault(); this.menu.i = (this.menu.i - 1 + m.length) % m.length; return; }
                    if (k === 'Enter' || k === 'Tab') { e.preventDefault(); this.aplicar(m[this.menu.i].id, bl); return; }
                }
                if (k === 'Escape') {
                    e.preventDefault();
                    if (this.menu) { this.menu = null; return; }
                    this.foco = null; e.target.blur(); return;
                }
                if (mod && !e.altKey) {
                    const lk = k.toLowerCase();
                    if (bl.t !== 'code') {
                        if (lk === 'b' && !e.shiftKey) { e.preventDefault(); this.envolver('**', '**'); return; }
                        if (lk === 'i' && !e.shiftKey) { e.preventDefault(); this.envolver('*', '*'); return; }
                        if (lk === 'e') { e.preventDefault(); this.envolver('`', '`'); return; }
                        if (lk === 'k') { e.preventDefault(); this.link(); return; }
                        if (lk === 'x' && e.shiftKey) { e.preventDefault(); this.envolver('~~', '~~'); return; }
                        if (lk === 'h' && e.shiftKey) { e.preventDefault(); this.envolver('==', '=='); return; }
                    }
                    if (k === 'Enter' && bl.t === 'code') { e.preventDefault(); this.depoisDe(i); }
                    return;
                }
                if (e.altKey && (k === 'ArrowUp' || k === 'ArrowDown')) { e.preventDefault(); this.mover(i, k === 'ArrowUp' ? -1 : 1); return; }
                if (k === 'Enter') { if (bl.t === 'code') return; e.preventDefault(); this.enter(e, bl, i); return; }
                if (k === 'Backspace') { this.voltar(e, bl, i); return; }
                if (k === 'Delete') { this.apagar(e, bl, i); return; }
                if (k === 'Tab') {
                    if (LISTAS.includes(bl.t)) { e.preventDefault(); bl.n = Math.max(0, Math.min(3, (bl.n || 0) + (e.shiftKey ? -1 : 1))); }
                    else if (bl.t === 'code') { e.preventDefault(); const t = e.target; t.setRangeText('  ', t.selectionStart, t.selectionEnd, 'end'); t.dispatchEvent(new Event('input', { bubbles: true })); }
                    return;
                }
                if (k === 'ArrowUp' && !e.shiftKey && e.target.selectionStart === 0 && e.target.selectionEnd === 0) { this.ir(e, i, -1, -1); return; }
                if (k === 'ArrowDown' && !e.shiftKey && e.target.selectionStart === e.target.value.length && e.target.selectionEnd === e.target.value.length) { this.ir(e, i, 1, 0); }
            },
            ir(e, i, d, pos) {
                let j = i + d;
                while (this.blocos[j] && this.blocos[j].t === 'hr') j += d;
                const o = this.blocos[j]; if (!o) return;
                e.preventDefault(); this.focar(o.id, pos);
            },
            enter(e, bl, i) {
                const t = e.target, ss = t.selectionStart, se = t.selectionEnd;
                if (bl.t === 'p') {
                    const xx = bl.x.trim();
                    if (/^([-*_])\1{2,}$/.test(xx)) {
                        bl.t = 'hr'; bl.x = '';
                        const nb = novo('p'); this.blocos.splice(i + 1, 0, nb); this.focar(nb.id, 0); return;
                    }
                    const cm = /^```([\w+#.-]*)$/.exec(xx);
                    if (cm) { bl.t = 'code'; bl.l = cm[1]; bl.x = ''; t.value = ''; this.focar(bl.id, 0); return; }
                }
                if (bl.t !== 'p' && !bl.x) {
                    if ((bl.n || 0) > 0) bl.n--; else { bl.t = 'p'; bl.f = false; }
                    this.focar(bl.id, 0); return;
                }
                const antes = bl.x.slice(0, ss), depois = bl.x.slice(se);
                bl.x = antes; t.value = antes; this.fit(t);
                const cont = LISTAS.includes(bl.t);
                const nb = novo(cont ? bl.t : 'p', depois, { n: cont ? (bl.n || 0) : 0 });
                this.blocos.splice(i + 1, 0, nb);
                this.menu = null;
                this.focar(nb.id, 0);
            },
            voltar(e, bl, i) {
                const t = e.target;
                if (t.selectionStart !== 0 || t.selectionEnd !== 0) return;
                if (bl.t === 'code') { if (!bl.x) { e.preventDefault(); this.remover(i); } return; }
                if (bl.t !== 'p') {
                    e.preventDefault();
                    if ((bl.n || 0) > 0) bl.n--; else { bl.t = 'p'; bl.f = false; }
                    this.focar(bl.id, 0); return;
                }
                if (i === 0) return;
                e.preventDefault();
                const ant = this.blocos[i - 1];
                if (ant.t === 'hr') { this.blocos.splice(i - 1, 1); this.focar(bl.id, 0); return; }
                if (ant.t === 'code') { if (!bl.x) this.blocos.splice(i, 1); this.focar(ant.id, -1); return; }
                const pos = ant.x.length;
                ant.x += bl.x; this.blocos.splice(i, 1); this.focar(ant.id, pos);
            },
            apagar(e, bl, i) {
                const t = e.target;
                if (bl.t === 'code' || t.selectionStart !== bl.x.length || t.selectionEnd !== bl.x.length) return;
                const prox = this.blocos[i + 1]; if (!prox) return;
                e.preventDefault();
                if (prox.t === 'hr') { this.blocos.splice(i + 1, 1); return; }
                if (prox.t === 'code') return;
                const pos = bl.x.length;
                bl.x += prox.x; this.blocos.splice(i + 1, 1); this.focar(bl.id, pos);
            },
            colar(e, bl, i) {
                if (bl.t === 'code') return;
                const txt = (e.clipboardData || window.clipboardData).getData('text') || '';
                const limpo = txt.replace(/\r\n?/g, '\n').replace(/\n+$/, '');
                if (limpo.indexOf('\n') < 0) return;
                e.preventDefault();
                const novos = parse(limpo); if (!novos.length) return;
                const t = e.target;
                const antes = bl.x.slice(0, t.selectionStart), depois = bl.x.slice(t.selectionEnd);
                let foco, pos = -1;
                if (!antes && !depois) {
                    this.blocos.splice(i, 1, ...novos); foco = novos[novos.length - 1].id;
                } else {
                    bl.x = antes;
                    if (depois) { const r = novo('p', depois); novos.push(r); foco = r.id; pos = 0; } else foco = novos[novos.length - 1].id;
                    this.blocos.splice(i + 1, 0, ...novos);
                }
                this.focar(foco, pos);
            },

            /* ---------- formatação inline ---------- */
            envolver(a, z) {
                const bl = this.atual(); if (!bl || bl.t === 'code' || bl.t === 'hr') return;
                const t = this.ta(bl.id); if (!t) return;
                const s = t.selectionStart, e = t.selectionEnd, v = t.value, sel = v.slice(s, e);
                const ja = s >= a.length && v.slice(s - a.length, s) === a && v.slice(e, e + z.length) === z
                    && !(a.length === 1 && v.charAt(s - a.length - 1) === a);
                if (ja) { t.setRangeText(sel, s - a.length, e + z.length, 'end'); t.setSelectionRange(s - a.length, e - a.length); }
                else { t.setRangeText(a + sel + z, s, e, 'end'); t.setSelectionRange(s + a.length, e + a.length); }
                t.focus();
                t.dispatchEvent(new Event('input', { bubbles: true }));
            },
            link() {
                const bl = this.atual(); if (!bl || bl.t === 'code' || bl.t === 'hr') return;
                const t = this.ta(bl.id); if (!t) return;
                const s = t.selectionStart, e = t.selectionEnd, sel = t.value.slice(s, e);
                const url = window.prompt('Endereço do link (https://…)', 'https://');
                if (!url || !/^(https?:\/\/|mailto:)/i.test(url.trim())) { t.focus(); return; }
                const txt = '[' + (sel || 'link') + '](' + url.trim() + ')';
                t.setRangeText(txt, s, e, 'end');
                t.focus();
                t.dispatchEvent(new Event('input', { bubbles: true }));
            },

            /* ---------- estrutura ---------- */
            mover(i, d) {
                const j = i + d; if (j < 0 || j >= this.blocos.length) return;
                const tinha = this.foco === this.blocos[i].id;
                const [m] = this.blocos.splice(i, 1);
                this.blocos.splice(j, 0, m);
                if (tinha) this.focar(m.id, -1);
            },
            duplicar(i) {
                const o = this.blocos[i];
                const c = novo(o.t, o.x, { n: o.n, f: o.f, l: o.l });
                this.blocos.splice(i + 1, 0, c);
                this.focar(c.id, -1);
            },
            remover(i) {
                this.blocos.splice(i, 1);
                if (!this.blocos.length) this.blocos.push(novo('p'));
                const o = this.blocos[Math.min(i, this.blocos.length - 1)];
                if (o.t !== 'hr') this.focar(o.id, -1); else this.foco = null;
            },
            depoisDe(i) { const nb = novo('p'); this.blocos.splice(i + 1, 0, nb); this.focar(nb.id, 0); },
            addAbaixo(i) {
                const nb = novo('p', '/');
                this.blocos.splice(i + 1, 0, nb);
                this.menu = { id: nb.id, q: '', i: 0 };
                this.focar(nb.id, -1);
            },
            aoFim() {
                const u = this.blocos[this.blocos.length - 1];
                if (u && u.t === 'p' && !u.x) { this.focar(u.id, 0); return; }
                const nb = novo('p'); this.blocos.push(nb); this.focar(nb.id, 0);
            },
            dragIni(e, bl) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', bl.id); } catch (_) {} },
            soltar(i) {
                const de = this.arrastar ? this.idx(this.arrastar) : -1;
                this.arrastar = null; this.sobre = -1;
                if (de < 0 || de === i) return;
                const [m] = this.blocos.splice(de, 1);
                this.blocos.splice(i, 0, m);
            },

            /* ---------- sumário, estatísticas, arquivos ---------- */
            titulos() { return this.blocos.map((x, i) => ({ x: x, i: i })).filter(o => /^h[1-3]$/.test(o.x.t)); },
            irPara(id) {
                this.focar(id, 0);
                this.$nextTick(() => { const r = this.$root.querySelector('[data-es="' + id + '"]'); if (r) r.scrollIntoView({ block: 'nearest' }); });
            },
            palavras() { const t = this.blocos.map(x => x.x).join(' ').trim(); return t ? (t.match(/\S+/g) || []).length : 0; },
            caracteres() { return this.blocos.reduce((n, x) => n + x.x.length, 0); },
            leitura() { return Math.max(1, Math.ceil(this.palavras() / 200)); },
            baixar() {
                const blob = new Blob([this.serializar()], { type: 'text/markdown;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (String(b.titulo || 'texto').replace(/[\\/:*?"<>|]+/g, '').trim() || 'texto') + '.md';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            },
            async copiar() {
                const t = this.serializar();
                let ok = false;
                try { await navigator.clipboard.writeText(t); ok = true; } catch (_) { ok = window.WFRepo ? await window.WFRepo.copiar(t) : false; }
                this.avisar(ok ? 'Markdown copiado.' : 'Não consegui copiar.');
            },
            async importar(f) {
                if (!f) return;
                const txt = await f.text();
                if (this.palavras() && !window.confirm('Substituir o texto atual pelo conteúdo do arquivo?')) return;
                this.blocos = this.carregar(txt);
                this.avisar('Arquivo importado.');
            },
        };
    };
})();