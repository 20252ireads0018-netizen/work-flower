
@verbatim
<script>
/* ===== Editor de escrita por blocos (bloco "texto" do Diverso) =====
   O conteúdo continua em b.dados.texto, como Markdown (uma linha = um bloco).

   Recursos
   - Seleção de blocos (várias linhas): Esc seleciona o bloco · Shift+↑/↓ amplia · Shift+clique · Ctrl+A (2x) seleciona todos
   - Barra flutuante ao selecionar texto ou blocos: "Transformar em" (título, tarefas, lista, citação, código…) + formatação
   - Transformar vale para vários blocos de uma vez (vários → código junta as linhas; código → outro tipo separa as linhas)
   - Atalhos: Ctrl+Alt+0…9 mudam o tipo (0 texto, 1-3 títulos, 4 tarefas, 5 marcadores, 6 numerada, 7 citação, 8 código, 9 destaque)
   - Desfazer/Refazer da estrutura inteira (Ctrl+Z, Ctrl+Shift+Z / Ctrl+Y)
   - Recortar, copiar, duplicar, excluir, mover e indentar vários blocos; arrastar o grupo
   - Recolher/expandir seções por título · Ctrl+Enter marca/desmarca tarefa · /data · links automáticos · contador de tarefas

   Correções desta versão (v3)
   - Agora vai embutido na view (sem arquivo separado): resolve "limparSel is not defined" e "todo is not defined", causados por um escrita.js antigo em cache
   - Lista numerada não reinicia mais depois de sub-itens
   - Link automático não engole mais aspas no fim do endereço
   - Se alguma função faltar, o editor completa com um valor seguro em vez de quebrar a tela */
(function () {
    'use strict';

    const LISTAS = ['ul', 'ol', 'todo'];
    const ITENS = [
        { id: 'p',       nome: 'Texto',               desc: 'Parágrafo simples',         ic: '¶',   k: 'texto paragrafo normal' },
        { id: 'h1',      nome: 'Título 1',            desc: 'Título grande',             ic: 'H1',  k: 'titulo heading h1' },
        { id: 'h2',      nome: 'Título 2',            desc: 'Título médio',              ic: 'H2',  k: 'titulo heading h2' },
        { id: 'h3',      nome: 'Título 3',            desc: 'Título pequeno',            ic: 'H3',  k: 'titulo heading h3' },
        { id: 'ul',      nome: 'Lista com marcadores', desc: 'Lista simples',            ic: '•',   k: 'lista bullet marcadores' },
        { id: 'ol',      nome: 'Lista numerada',      desc: 'Lista em ordem',            ic: '1.',  k: 'lista numerada ordenada' },
        { id: 'todo',    nome: 'Lista de tarefas',    desc: 'Itens com caixa de marcar', ic: '☑',   k: 'tarefa todo checklist caixa' },
        { id: 'quote',   nome: 'Citação',             desc: 'Trecho em destaque',        ic: '❝',   k: 'citacao quote' },
        { id: 'callout', nome: 'Destaque',            desc: 'Caixa de aviso ou nota',    ic: '💡',  k: 'destaque callout aviso nota' },
        { id: 'code',    nome: 'Código',              desc: 'Bloco de código',           ic: '</>', k: 'codigo code programacao' },
        { id: 'hr',      nome: 'Divisor',             desc: 'Linha separadora',          ic: '—',   k: 'divisor linha separador hr' },
        { id: 'hoje',    nome: 'Data de hoje',        desc: 'Insere a data atual',       ic: '📅',  k: 'data hoje dia agora', acao: true },
    ];
    // tipos para os quais um bloco existente pode ser transformado (sem divisor e sem ações)
    const TIPOS = ITENS.filter(it => !it.acao && it.id !== 'hr');
    const PH = { h1: 'Título 1', h2: 'Título 2', h3: 'Título 3', ul: 'Item da lista', ol: 'Item da lista', todo: 'Tarefa', quote: 'Citação', callout: 'Escreva um destaque…' };
    const ATALHOS = [
        { re: /^(#{1,3}) /,        t: m => 'h' + m[1].length },
        { re: /^[-*+] /,           t: () => 'ul' },
        { re: /^\d+[.)] /,         t: () => 'ol' },
        { re: /^\[( |x|X)?\] /,    t: () => 'todo', f: m => /x/i.test(m[1] || '') },
        { re: /^> /,               t: () => 'quote' },
        { re: /^!> /,              t: () => 'callout' },
    ];
    // Ctrl+Alt+tecla (usa e.code para funcionar em qualquer layout de teclado)
    const TECLA_TIPO = { Digit0: 'p', Digit1: 'h1', Digit2: 'h2', Digit3: 'h3', Digit4: 'todo', Digit5: 'ul', Digit6: 'ol', Digit7: 'quote', Digit8: 'code', Digit9: 'callout' };
    const MAX_HIST = 100;

    const norm = s => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    let seq = 0;
    const nid = () => 'e' + Date.now().toString(36) + '-' + (seq++).toString(36);
    const novo = (t, x, extra) => Object.assign({ id: nid(), t: t, x: x || '', n: 0, f: false, l: '' }, extra || {});
    const nivelTitulo = bl => { const m = /^h([1-3])$/.exec(bl.t); return m ? +m[1] : 0; };

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
            const guardar = html => { toks.push(html); return '\u0001' + (toks.length - 1) + '\u0002'; };
            let h = esc(p).replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, t, u) {
                const url = u.replace(/&amp;/g, '&');
                if (!/^(https?:\/\/|mailto:)/i.test(url)) return m;
                return guardar('<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + fmt(t) + '</a>');
            });
            // endereços soltos (https://…) viram link sozinhos; aspas e ">" no fim ficam de fora do link
            h = h.replace(/(^|[\s(>])(https?:\/\/[^\s<]*[^\s<.,;:!?)'"])/gi, function (m, pre, u) {
                let resto = '';
                const q = /(?:&quot;|&#39;|&gt;)+$/.exec(u);
                if (q) { resto = q[0]; u = u.slice(0, -resto.length); }
                return pre + guardar('<a href="' + u + '" target="_blank" rel="noopener noreferrer">' + u + '</a>') + resto;
            });
            h = fmt(h);
            return h.replace(/\u0001(\d+)\u0002/g, function (m, n) { return toks[+n]; });
        }).join('');
    }

    window.WFEscrita = function (b) {
        // histórico (fora do estado reativo)
        let pilha = [], pos = -1, tHist = 0;
        const assinatura = bls => bls.map(x => x.id + ':' + x.t + ':' + (x.n || 0) + ':' + (x.f ? 1 : 0)).join('|');

        return {
            blocos: [], foco: null, menu: null, opcBl: null, sumario: false,
            arrastar: null, sobre: -1, dica: '', itens: ITENS, tipos: TIPOS, _ult: null, _t: null,
            selecao: [], selTxt: false, tipoMenu: false, recolhidos: [], todo: false,
            pH: 0, nH: 1, _anc: null, _fim: null, _uf: null,

            iniciar() {
                if (!b.dados) b.dados = {};
                if (!b.dados.tam) b.dados.tam = 'm';
                if (typeof b.dados.texto !== 'string') b.dados.texto = b.dados.texto == null ? '' : String(b.dados.texto);
                this.blocos = this.carregar(b.dados.texto);
                this._ult = this.serializar();
                pilha = [{ s: this.snap(), g: assinatura(this.blocos) }]; pos = 0;
                this.pH = 0; this.nH = 1;
                this.$watch('blocos', () => {
                    this._ult = this.serializar();
                    if (b.dados.texto !== this._ult) b.dados.texto = this._ult;
                    this.registrar();
                });
                // mudança vinda de fora (biblioteca, desfazer, etc.)
                this.$watch(() => b.dados.texto, v => {
                    if (v !== this._ult) { this.blocos = this.carregar(v); this._ult = this.serializar(); }
                });
                // ao sair do modo "texto inteiro" a folha estava oculta: recalcula a altura de todos os campos
                this.$watch('todo', v => { if (!v) this.ajustar(); });
            },
            carregar(t) { const r = parse(t); return r.length ? r : [novo('p')]; },

            /* ---------- Markdown ---------- */
            md(lista) {
                return lista.map((bl, i) => {
                    const ind = '  '.repeat(bl.n || 0);
                    switch (bl.t) {
                        case 'h1': return '# ' + bl.x;
                        case 'h2': return '## ' + bl.x;
                        case 'h3': return '### ' + bl.x;
                        case 'ul': return ind + '- ' + bl.x;
                        case 'ol': return ind + this.numero(i, lista) + '. ' + bl.x;
                        case 'todo': return ind + '- [' + (bl.f ? 'x' : ' ') + '] ' + bl.x;
                        case 'quote': return '> ' + bl.x;
                        case 'callout': return '!> ' + bl.x;
                        case 'hr': return '---';
                        case 'code': return '```' + (bl.l || '') + '\n' + bl.x + '\n```';
                        default: return bl.x;
                    }
                }).join('\n');
            },
            serializar() { return this.md(this.blocos); },

            /* ---------- desfazer / refazer (estrutura + texto) ---------- */
            snap() { return JSON.stringify(this.blocos); },
            registrar() {
                const s = this.snap();
                if (pilha[pos] && s === pilha[pos].s) return;
                const g = assinatura(this.blocos), agora = Date.now();
                pilha.length = pos + 1;
                // só texto mudou e foi há pouco: junta numa única etapa de desfazer
                if (pos > 0 && pilha[pos].g === g && agora - tHist < 1000) pilha[pos] = { s: s, g: g };
                else {
                    pilha.push({ s: s, g: g }); pos++;
                    if (pilha.length > MAX_HIST) { pilha.shift(); pos--; }
                }
                tHist = agora;
                this.pH = pos; this.nH = pilha.length;
            },
            restaurar() {
                const foco = this.foco;
                this.blocos = JSON.parse(pilha[pos].s);
                this.pH = pos; this.nH = pilha.length;
                this.selecao = []; this.menu = null; this.opcBl = null; this.tipoMenu = false; this.selTxt = false;
                if (foco && this.idx(foco) >= 0) this.focar(foco, -1); else this.foco = null;
                this.ajustar();
            },
            desfazer() { if (pos <= 0) { this.avisar('Nada para desfazer.'); return; } pos--; this.restaurar(); },
            refazer() { if (pos >= pilha.length - 1) { this.avisar('Nada para refazer.'); return; } pos++; this.restaurar(); },
            podeDesfazer() { return this.pH > 0; },
            podeRefazer() { return this.pH < this.nH - 1; },

            /* ---------- auxiliares ---------- */
            idx(id) { return this.blocos.findIndex(x => x.id === id); },
            atual() { return this.blocos.find(x => x.id === this.foco) || null; },
            /** Último bloco em que o cursor esteve (continua valendo quando o foco vai para a barra de ferramentas). */
            ultimo() {
                const u = this.atual() || this.blocos.find(x => x.id === this._uf) || null;
                return u && u.t !== 'hr' ? u : null;
            },
            aoFocar(bl, el) {
                this.foco = bl.id; this._uf = bl.id;
                this.selecao = []; this.selTxt = false;
                this.fit(el);
            },
            ta(id) { return this.$root.querySelector('[data-es="' + id + '"] textarea.wf-es-ta'); },
            fit(t) { if (!t) return; t.style.height = 'auto'; const h = t.scrollHeight; if (h) t.style.height = h + 'px'; },
            ajustar() {
                this.$nextTick(() => this.$root.querySelectorAll('textarea.wf-es-ta').forEach(t => { if (t.offsetParent !== null) this.fit(t); }));
            },
            /** Número de um item de lista numerada: sub-itens (nível mais fundo) não reiniciam a contagem. */
            numero(i, lista) {
                lista = lista || this.blocos;
                const bl = lista[i], nv = bl.n || 0;
                let c = 1;
                for (let j = i - 1; j >= 0; j--) {
                    const o = lista[j], on = o.n || 0;
                    if (!LISTAS.includes(o.t) || on < nv) break;      // saiu da lista
                    if (on === nv) { if (o.t !== 'ol') break; c++; }  // mesmo nível: só conta se for numerada
                    // nível mais fundo (sub-itens): ignora e continua subindo
                }
                return c;
            },
            html(bl) {
                if (!bl.x) {
                    if (bl.t === 'p') return this.blocos.length === 1 ? '<span class="wf-es-ph">Escreva algo ou digite “/” para ver os comandos…</span>' : '<br>';
                    return PH[bl.t] ? '<span class="wf-es-ph">' + PH[bl.t] + '</span>' : '<br>';
                }
                try { return inline(bl.x); } catch (_) { return esc(bl.x); }
            },
            focar(id, pos2) {
                this.foco = id; this._uf = id; this.selecao = []; this.selTxt = false; this.tipoMenu = false;
                const tentar = n => {
                    const t = this.ta(id);
                    if (!t || t.offsetParent === null) { if (n < 6) setTimeout(() => tentar(n + 1), 20); return; }
                    t.focus(); this.fit(t);
                    const p = (pos2 == null || pos2 < 0) ? t.value.length : Math.min(pos2, t.value.length);
                    t.setSelectionRange(p, p);
                };
                this.$nextTick(() => tentar(0));
            },
            avisar(m) { this.dica = m; clearTimeout(this._t); this._t = setTimeout(() => { this.dica = ''; }, 2500); },
            async copiarTexto(t, ok) {
                let feito = false;
                try { await navigator.clipboard.writeText(t); feito = true; } catch (_) { feito = window.WFRepo ? await window.WFRepo.copiar(t) : false; }
                this.avisar(feito ? (ok || 'Markdown copiado.') : 'Não consegui copiar.');
                return feito;
            },

            /* ---------- dobrar seções por título ---------- */
            dobrar(bl) {
                const i = this.recolhidos.indexOf(bl.id);
                if (i >= 0) this.recolhidos.splice(i, 1); else this.recolhidos.push(bl.id);
            },
            visivel(i) {
                if (!this.recolhidos.length) return true;
                const bl = this.blocos[i]; if (!bl) return true;
                let limite = nivelTitulo(bl) || 99;
                for (let j = i - 1; j >= 0; j--) {
                    const n = nivelTitulo(this.blocos[j]);
                    if (!n || n >= limite) continue;
                    if (this.recolhidos.includes(this.blocos[j].id)) return false;
                    limite = n;
                    if (n === 1) break;
                }
                return true;
            },

            /* ---------- seleção de blocos ---------- */
            ehSel(id) { return this.selecao.includes(id); },
            focarFolha() {
                this.$nextTick(() => { const f = this.$root.querySelector('.wf-es-folha'); if (f) f.focus({ preventScroll: true }); });
            },
            selUm(id) {
                this.foco = null; this._uf = id; this.menu = null; this.opcBl = null; this.tipoMenu = false; this.selTxt = false;
                this.selecao = [id]; this._anc = id; this._fim = id;
                this.focarFolha();
            },
            selFaixa(a, z) {
                const i = this.idx(a), j = this.idx(z); if (i < 0 || j < 0) return;
                this._fim = z;
                this.selecao = this.blocos.slice(Math.min(i, j), Math.max(i, j) + 1).map(x => x.id);
                this.foco = null; this.menu = null; this.opcBl = null; this.selTxt = false;
                this.focarFolha();
            },
            selAte(id) { this.selFaixa(this._anc || this.foco || this._uf || id, id); },
            estender(d) {
                const j = this.idx(this._fim || this._anc) + d;
                if (j < 0 || j >= this.blocos.length) return;
                this.selFaixa(this._anc, this.blocos[j].id);
            },
            selTodos() {
                if (!this.blocos.length) return;
                this._anc = this.blocos[0].id;
                this.selFaixa(this._anc, this.blocos[this.blocos.length - 1].id);
            },
            limparSel() { this.selecao = []; this._anc = null; this._fim = null; },
            semAlvo() { return !this.selecao.length && !this.ultimo(); },
            alvos() {
                if (this.selecao.length) return this.blocos.filter(x => this.selecao.includes(x.id));
                const a = this.ultimo(); return a ? [a] : [];
            },
            ctrlA(e) {
                if (!(e.ctrlKey || e.metaKey) || String(e.key).toLowerCase() !== 'a' || this.todo) return;
                if (this.selecao.length) {
                    e.preventDefault();
                    if (this.selecao.length === this.blocos.length) this.todo = true; // 3ª vez: texto inteiro numa caixa só
                    else this.selTodos();
                    return;
                }
                const t = e.target;
                if (!t || t.tagName !== 'TEXTAREA') return;
                const tudo = t.value.length === 0 || (t.selectionStart === 0 && t.selectionEnd === t.value.length);
                if (!tudo) return;
                e.preventDefault();
                this.selTodos();
            },
            teclaSel(e) {
                if (!this.selecao.length) return;
                const k = e.key, mod = e.ctrlKey || e.metaKey, lk = k.toLowerCase();
                if (mod && !e.altKey && lk === 'z') { e.preventDefault(); if (e.shiftKey) this.refazer(); else this.desfazer(); return; }
                if (mod && !e.altKey && lk === 'y') { e.preventDefault(); this.refazer(); return; }
                if (this.atalhoTipo(e)) return;
                if (mod && !e.altKey) {
                    if (lk === 'c' || (lk === 'x' && !e.shiftKey)) {
                        e.preventDefault();
                        const corte = lk === 'x';
                        this.copiarTexto(this.md(this.alvos()), corte ? 'Recortado.' : 'Copiado.').then(ok => { if (ok && corte) this.removerSel(); });
                        return;
                    }
                    if (lk === 'd') { e.preventDefault(); this.duplicarSel(); return; }
                    if (lk === 'b' && !e.shiftKey) { e.preventDefault(); this.envolver('**', '**'); return; }
                    if (lk === 'i' && !e.shiftKey) { e.preventDefault(); this.envolver('*', '*'); return; }
                    if (lk === 'e') { e.preventDefault(); this.envolver('`', '`'); return; }
                    if (lk === 'x' && e.shiftKey) { e.preventDefault(); this.envolver('~~', '~~'); return; }
                    if (lk === 'h' && e.shiftKey) { e.preventDefault(); this.envolver('==', '=='); return; }
                    return;
                }
                if (k === 'Escape') { e.preventDefault(); this.limparSel(); return; }
                if (k === 'Enter') {
                    e.preventDefault();
                    const bl = this.blocos.find(x => x.id === this.selecao[0]);
                    this.limparSel();
                    if (bl && bl.t !== 'hr') this.focar(bl.id, -1);
                    return;
                }
                if (k === 'Backspace' || k === 'Delete') { e.preventDefault(); this.removerSel(); return; }
                if (k === 'Tab') { e.preventDefault(); this.indentarSel(e.shiftKey ? -1 : 1); return; }
                if (k === 'ArrowUp' || k === 'ArrowDown') {
                    e.preventDefault();
                    const d = k === 'ArrowUp' ? -1 : 1;
                    if (e.altKey) { this.moverSel(d); return; }
                    if (e.shiftKey) { this.estender(d); return; }
                    const ids = this.selecao, ref = this.idx(d < 0 ? ids[0] : ids[ids.length - 1]) + d;
                    if (this.blocos[ref]) this.selUm(this.blocos[ref].id);
                }
            },
            grip(e, bl) {
                if (e.shiftKey) { this.selAte(bl.id); return; }
                if (this.opcBl === bl.id) { this.opcBl = null; return; }
                if (!this.selecao.includes(bl.id)) this.selUm(bl.id);
                this.opcBl = bl.id;
            },
            indices() { return this.blocos.map((x, i) => (this.selecao.includes(x.id) ? i : -1)).filter(i => i >= 0); },
            podeMover(d) {
                const ix = this.indices(); if (!ix.length) return false;
                return d < 0 ? ix[0] > 0 : ix[ix.length - 1] < this.blocos.length - 1;
            },
            moverSel(d) {
                const ix = this.indices(); if (!ix.length || !this.podeMover(d)) return;
                const a = ix[0], z = ix[ix.length - 1];
                if (d < 0) { const [p] = this.blocos.splice(a - 1, 1); this.blocos.splice(z, 0, p); }
                else { const [n] = this.blocos.splice(z + 1, 1); this.blocos.splice(a, 0, n); }
                this.focarFolha();
            },
            duplicarSel() {
                const ix = this.indices(); if (!ix.length) return;
                const copias = ix.map(i => { const o = this.blocos[i]; return novo(o.t, o.x, { n: o.n, f: o.f, l: o.l }); });
                this.blocos.splice(ix[ix.length - 1] + 1, 0, ...copias);
                this.selecao = copias.map(c => c.id); this._anc = copias[0].id; this._fim = copias[copias.length - 1].id;
                this.focarFolha();
            },
            removerSel() {
                const ix = this.indices(); if (!ix.length) return;
                const ids = new Set(this.selecao), a = ix[0];
                this.blocos = this.blocos.filter(x => !ids.has(x.id));
                if (!this.blocos.length) this.blocos.push(novo('p'));
                this.limparSel();
                const o = this.blocos[Math.min(a, this.blocos.length - 1)];
                if (o.t !== 'hr') this.focar(o.id, -1); else this.foco = null;
            },
            indentarSel(d) {
                let mudou = false;
                this.alvos().forEach(bl => { if (LISTAS.includes(bl.t)) { bl.n = Math.max(0, Math.min(3, (bl.n || 0) + d)); mudou = true; } });
                if (!mudou) this.avisar('Só listas podem ser recuadas.');
            },
            atalhoTipo(e) {
                if (!(e.ctrlKey || e.metaKey) || !e.altKey || e.shiftKey) return false;
                const id = TECLA_TIPO[e.code]; if (!id) return false;
                e.preventDefault(); this.transformar(id); return true;
            },

            /* ---------- barra flutuante ---------- */
            sel(e) {
                const t = e.target;
                this.selTxt = t.selectionStart !== t.selectionEnd;
                if (!this.selTxt) this.tipoMenu = false;
            },
            bolhaEm(id) {
                if (this.selecao.length) return this.selecao[0] === id;
                const bl = this.atual();
                return !!bl && bl.id === id && this.selTxt && bl.t !== 'hr';
            },
            podeFormatar() { return this.alvos().some(x => x.t !== 'code' && x.t !== 'hr'); },
            tipoAtual() {
                const ts = [...new Set(this.alvos().map(x => x.t).filter(t => t !== 'hr'))];
                return ts.length === 1 ? ts[0] : (ts.length ? '' : 'p');
            },
            rotuloTipo() {
                const t = this.tipoAtual(), it = ITENS.find(x => x.id === t);
                return it ? it.nome : 'Misto';
            },

            /* ---------- transformar (uma ou várias linhas) ---------- */
            setTipo(bl, id) {
                if (bl.t === 'code' && id !== 'code') bl.x = bl.x.replace(/\r?\n/g, ' ');
                bl.t = id;
                if (!LISTAS.includes(id)) bl.n = 0;
                if (id !== 'todo') bl.f = false;
                if (id !== 'code') bl.l = '';
            },
            transformar(id) {
                if (!TIPOS.some(t => t.id === id)) return;
                const alvo = this.alvos().filter(x => x.t !== 'hr');
                if (!alvo.length) { this.tipoMenu = false; this.avisar('Clique em um bloco de texto primeiro.'); return; }
                const ids = new Set(alvo.map(x => x.id));
                const emSel = this.selecao.length > 0, focoAnt = this.foco || this._uf;
                const juntar = id === 'code' && alvo.length > 1;
                const txtJunto = juntar ? alvo.map(x => x.x).join('\n') : '';
                const out = [], novaSel = [];
                let jaJunto = false;
                for (const bl of this.blocos) {
                    if (!ids.has(bl.id)) { out.push(bl); continue; }
                    if (juntar) {
                        if (jaJunto) continue;
                        jaJunto = true;
                        this.setTipo(bl, 'code'); bl.x = txtJunto; bl.l = '';
                        out.push(bl); novaSel.push(bl.id);
                        continue;
                    }
                    // código de várias linhas vira um bloco por linha
                    const linhas = (bl.t === 'code' && id !== 'code') ? bl.x.split('\n') : [bl.x];
                    const antes = bl.t;
                    this.setTipo(bl, id);
                    if (antes === 'code' && id === 'code') bl.l = bl.l || '';
                    bl.x = linhas[0];
                    out.push(bl); novaSel.push(bl.id);
                    for (let k = 1; k < linhas.length; k++) { const nb = novo(id, linhas[k]); out.push(nb); novaSel.push(nb.id); }
                }
                this.blocos = out;
                this.tipoMenu = false; this.opcBl = null; this.menu = null;
                if (emSel) { this.selecao = novaSel; this._anc = novaSel[0]; this._fim = novaSel[novaSel.length - 1]; this.focarFolha(); }
                else if (focoAnt && this.idx(focoAnt) >= 0) this.focar(focoAnt, -1);
                this.ajustar();
            },
            mudarTipo(id) { this.transformar(id); },

            /* ---------- menu "/" ---------- */
            itensMenu() {
                if (!this.menu) return [];
                const q = norm(this.menu.q);
                return ITENS.filter(it => !q || norm(it.nome + ' ' + it.k).includes(q));
            },
            aplicar(id, bl) {
                this.menu = null;
                const i = this.idx(bl.id);
                if (id === 'hoje') {
                    bl.x = new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
                    const t0 = this.ta(bl.id); if (t0) t0.value = bl.x;
                    this.focar(bl.id, -1);
                    return;
                }
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
            ver(e, bl) {
                if (e.target.closest && e.target.closest('a')) return;
                if (e.shiftKey && (this.foco || this.selecao.length)) { e.preventDefault(); this.selAte(bl.id); return; }
                this.focar(bl.id, -1);
            },

            tecla(e, bl, i) {
                const k = e.key, mod = e.ctrlKey || e.metaKey, lk = k.toLowerCase();
                const m = (this.menu && this.menu.id === bl.id) ? this.itensMenu() : null;
                if (m && m.length) {
                    if (k === 'ArrowDown') { e.preventDefault(); this.menu.i = (this.menu.i + 1) % m.length; return; }
                    if (k === 'ArrowUp') { e.preventDefault(); this.menu.i = (this.menu.i - 1 + m.length) % m.length; return; }
                    if (k === 'Enter' || k === 'Tab') { e.preventDefault(); this.aplicar(m[this.menu.i].id, bl); return; }
                }
                if (k === 'Escape') {
                    e.preventDefault();
                    if (this.menu) { this.menu = null; return; }
                    if (this.tipoMenu) { this.tipoMenu = false; return; }
                    this.selUm(bl.id); e.target.blur(); return;      // Esc seleciona o bloco inteiro
                }
                if (mod && !e.altKey && lk === 'z') { e.preventDefault(); if (e.shiftKey) this.refazer(); else this.desfazer(); return; }
                if (mod && !e.altKey && lk === 'y') { e.preventDefault(); this.refazer(); return; }
                if (this.atalhoTipo(e)) return;
                if (mod && !e.altKey) {
                    if (k === 'Enter') {
                        e.preventDefault();
                        if (bl.t === 'code') this.depoisDe(i);
                        else if (bl.t === 'todo') bl.f = !bl.f;
                        return;
                    }
                    if (bl.t !== 'code') {
                        if (lk === 'b' && !e.shiftKey) { e.preventDefault(); this.envolver('**', '**'); return; }
                        if (lk === 'i' && !e.shiftKey) { e.preventDefault(); this.envolver('*', '*'); return; }
                        if (lk === 'e') { e.preventDefault(); this.envolver('`', '`'); return; }
                        if (lk === 'k') { e.preventDefault(); this.link(); return; }
                        if (lk === 'x' && e.shiftKey) { e.preventDefault(); this.envolver('~~', '~~'); return; }
                        if (lk === 'h' && e.shiftKey) { e.preventDefault(); this.envolver('==', '=='); return; }
                    }
                    return;
                }
                if (e.altKey && (k === 'ArrowUp' || k === 'ArrowDown')) { e.preventDefault(); this.mover(i, k === 'ArrowUp' ? -1 : 1); return; }
                // Shift+↑/↓ no limite do texto: passa a selecionar blocos inteiros
                if (e.shiftKey && !e.altKey && (k === 'ArrowUp' || k === 'ArrowDown')) {
                    const t = e.target;
                    const noLimite = k === 'ArrowUp' ? t.selectionStart === 0 : t.selectionEnd === t.value.length;
                    if (noLimite) { e.preventDefault(); this.selUm(bl.id); this.estender(k === 'ArrowUp' ? -1 : 1); return; }
                }
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
            ir(e, i, d, pos2) {
                let j = i + d;
                while (this.blocos[j] && (this.blocos[j].t === 'hr' || !this.visivel(j))) j += d;
                const o = this.blocos[j]; if (!o) return;
                e.preventDefault(); this.focar(o.id, pos2);
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
                // Enter num título recolhido expande a seção (senão o novo bloco ficaria escondido)
                if (this.recolhidos.includes(bl.id)) this.recolhidos = this.recolhidos.filter(x => x !== bl.id);
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
                const pos2 = ant.x.length;
                ant.x += bl.x; this.blocos.splice(i, 1); this.focar(ant.id, pos2);
            },
            apagar(e, bl, i) {
                const t = e.target;
                if (bl.t === 'code' || t.selectionStart !== bl.x.length || t.selectionEnd !== bl.x.length) return;
                const prox = this.blocos[i + 1]; if (!prox) return;
                e.preventDefault();
                if (prox.t === 'hr') { this.blocos.splice(i + 1, 1); return; }
                if (prox.t === 'code') return;
                const pos2 = bl.x.length;
                bl.x += prox.x; this.blocos.splice(i + 1, 1); this.focar(bl.id, pos2);
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
                let foco, pos2 = -1;
                if (!antes && !depois) {
                    this.blocos.splice(i, 1, ...novos); foco = novos[novos.length - 1].id;
                } else {
                    bl.x = antes;
                    if (depois) { const r = novo('p', depois); novos.push(r); foco = r.id; pos2 = 0; } else foco = novos[novos.length - 1].id;
                    this.blocos.splice(i + 1, 0, ...novos);
                }
                this.focar(foco, pos2);
            },

            /* ---------- formatação inline ---------- */
            /** Envolve o texto selecionado (ou, com blocos selecionados, o texto inteiro de cada bloco). */
            envolver(a, z) {
                if (this.selecao.length) {
                    const alvo = this.alvos().filter(x => x.t !== 'code' && x.t !== 'hr' && x.x);
                    if (!alvo.length) return;
                    const ja = x => x.length > a.length + z.length && x.startsWith(a) && x.endsWith(z) && !(a.length === 1 && x.charAt(1) === a);
                    const desfaz = alvo.every(x => ja(x.x));
                    alvo.forEach(x => { x.x = desfaz ? x.x.slice(a.length, x.x.length - z.length) : a + x.x + z; });
                    return;
                }
                const bl = this.ultimo(); if (!bl || bl.t === 'code' || bl.t === 'hr') return;
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
                if (this.selecao.length) { this.avisar('Selecione o texto dentro de um bloco para criar um link.'); return; }
                const bl = this.ultimo(); if (!bl || bl.t === 'code' || bl.t === 'hr') return;
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
                this.limparSel();
                const u = this.blocos[this.blocos.length - 1];
                if (u && u.t === 'p' && !u.x) { this.focar(u.id, 0); return; }
                const nb = novo('p'); this.blocos.push(nb); this.focar(nb.id, 0);
            },
            dragIni(e, bl) { e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', bl.id); } catch (_) {} },
            /** Solta antes do bloco i (i = total de blocos: no fim). Se o bloco arrastado está selecionado, move o grupo todo. */
            soltar(i) {
                const idDrag = this.arrastar;
                this.arrastar = null; this.sobre = -1;
                const de = idDrag ? this.idx(idDrag) : -1;
                if (de < 0) return;
                const grupo = this.selecao.includes(idDrag) ? this.blocos.filter(x => this.selecao.includes(x.id)) : [this.blocos[de]];
                const alvoBl = this.blocos[i];
                if (alvoBl && grupo.some(g => g.id === alvoBl.id)) return;
                const ids = new Set(grupo.map(g => g.id));
                const resto = this.blocos.filter(x => !ids.has(x.id));
                const p = alvoBl ? resto.findIndex(x => x.id === alvoBl.id) : resto.length;
                resto.splice(p < 0 ? resto.length : p, 0, ...grupo);
                this.blocos = resto;
            },

            /* ---------- sumário, estatísticas, arquivos ---------- */
            titulos() { return this.blocos.map((x, i) => ({ x: x, i: i })).filter(o => /^h[1-3]$/.test(o.x.t)); },
            irPara(id) {
                const i = this.idx(id);
                if (i >= 0 && !this.visivel(i)) this.recolhidos = [];
                this.focar(id, 0);
                this.$nextTick(() => { const r = this.$root.querySelector('[data-es="' + id + '"]'); if (r) r.scrollIntoView({ block: 'nearest' }); });
            },
            palavras() { const t = this.blocos.map(x => x.x).join(' ').trim(); return t ? (t.match(/\S+/g) || []).length : 0; },
            caracteres() { return this.blocos.reduce((n, x) => n + x.x.length, 0); },
            leitura() { return Math.max(1, Math.ceil(this.palavras() / 200)); },
            tarefas() {
                const t = this.blocos.filter(x => x.t === 'todo');
                return { feitas: t.filter(x => x.f).length, total: t.length };
            },
            baixar() {
                const blob = new Blob([this.serializar()], { type: 'text/markdown;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (String(b.titulo || 'texto').replace(/[\\/:*?"<>|]+/g, '').trim() || 'texto') + '.md';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            },
            copiar() { return this.copiarTexto(this.serializar(), 'Markdown copiado.'); },
            async importar(f) {
                if (!f) return;
                const txt = await f.text();
                if (this.palavras() && !window.confirm('Substituir o texto atual pelo conteúdo do arquivo? (dá para desfazer com Ctrl+Z)')) return;
                this.limparSel(); this.recolhidos = [];
                this.blocos = this.carregar(txt);
                this.avisar('Arquivo importado.');
            },
        };
    };
    /* Versão do editor: a view confere este número e avisa se algo estiver desatualizado. */
    window.WFEscrita.versao = 3;
})();
</script>
@endverbatim
