/* Work Flower · área Diverso · cartão "Banco de dados (.sql)"
   Lê no navegador um arquivo .sql (dump do phpMyAdmin/MySQL, SQLite ou PostgreSQL com INSERT), desenha as
   tabelas e as ligações (chaves estrangeiras) e guarda os dados para consultar depois.
   - Estrutura (tabelas, colunas, posições no diagrama) fica em b.dados.
   - As linhas ficam numa chave à parte, img.<id>.sql (como as camadas do editor de imagem), e saem junto
     quando o cartão é excluído.
   Estende o diverso.js + diagrama.js: carregue depois deles; continua usando x-data="diversoAbasDiagrama()". */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof window.diversoAbasDiagrama !== 'function') return;
    const { uid } = Q.util;
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    const MAX_ARQUIVO = 25 * 1024 * 1024;  // 25 MB de .sql
    const ORCAMENTO = 4500000;             // caracteres de linhas guardadas (o servidor aceita até 6 milhões por chave)
    const MAX_TABELAS = 200, MAX_COLUNAS = 150;
    const POR_PAGINA = 50;
    const BOX_W = 220, CAB_H = 28, LIN_H = 18, MOSTRA_COL = 24;

    /* =================== leitura do .sql =================== */
    const PALAVRA = /[A-Za-z0-9_$\u0080-￿]/;

    /** Lê tokens de um texto SQL: palavras, nomes entre crases/aspas/colchetes, textos, números e símbolos. Pula comentários. */
    function Leitor(s) { this.s = s; this.i = 0; this.n = s.length; this.dolar = true; }
    Leitor.prototype.pularEspacos = function () {
        const s = this.s;
        for (;;) {
            const c = s.charCodeAt(this.i);
            if (c === 32 || c === 9 || c === 10 || c === 13 || c === 12) { this.i++; continue; }
            if (c === 45 && s.charCodeAt(this.i + 1) === 45) { const f = s.indexOf('\n', this.i); this.i = f < 0 ? this.n : f + 1; continue; } // --
            if (c === 35) { const f = s.indexOf('\n', this.i); this.i = f < 0 ? this.n : f + 1; continue; }                                    // #
            if (c === 47 && s.charCodeAt(this.i + 1) === 42) { const f = s.indexOf('*/', this.i + 2); this.i = f < 0 ? this.n : f + 2; continue; } // /* */
            return;
        }
    };
    Leitor.prototype.texto = function (q) {
        // de this.i (logo depois da aspa) até a aspa que fecha; aceita \' e '' como aspa dentro do texto
        // uma passada só, caractere a caractere (indexOf a cada texto ficava lento em dumps grandes)
        const s = this.s, n = this.n, cq = q.charCodeAt(0), barra = q !== '`';
        let out = '', ini = this.i, i = this.i;
        while (i < n) {
            const c = s.charCodeAt(i);
            if (c === 92 && barra) { // \
                out += s.slice(ini, i);
                const e = s[i + 1];
                out += ESCAPES[e] ?? e ?? '';
                i += 2; ini = i;
                continue;
            }
            if (c === cq) {
                if (s.charCodeAt(i + 1) === cq) { out += s.slice(ini, i + 1); i += 2; ini = i; continue; }
                this.i = i + 1;
                return out + s.slice(ini, i);
            }
            i++;
        }
        this.i = n;
        return out + s.slice(ini);
    };
    const ESCAPES = { n: '\n', t: '\t', r: '\r', 0: '\0', Z: '\x1a' };
    Leitor.prototype.prox = function () {
        this.pularEspacos();
        if (this.i >= this.n) return null;
        const s = this.s, c = s[this.i];
        if (c === "'") { this.i++; return { t: 'str', v: this.texto("'") }; }
        if (c === '"' || c === '`') { this.i++; return { t: 'id', v: this.texto(c), q: 1 }; }
        if (c === '[') { const f = s.indexOf(']', this.i); const v = s.slice(this.i + 1, f < 0 ? this.n : f); this.i = f < 0 ? this.n : f + 1; return { t: 'id', v, q: 1 }; }
        if (PALAVRA.test(c)) {
            let j = this.i + 1;
            while (j < this.n && PALAVRA.test(s[j])) j++;
            const v = s.slice(this.i, j);
            this.i = j;
            // N'texto', E'texto' (texto) e X'..', B'..' (binário, fica como está)
            if (s[j] === "'" && /^[NnEeXxBb]$/.test(v)) {
                this.i++;
                const t = this.texto("'");
                return /^[NnEe]$/.test(v) ? { t: 'str', v: t } : { t: 'raw', v: v + "'" + t + "'" };
            }
            // $$ corpo $$ do PostgreSQL (desligado enquanto um DELIMITER do MySQL estiver valendo)
            if (this.dolar && /^\$\w*\$$/.test(v)) { const f = s.indexOf(v, this.i); const t = s.slice(this.i, f < 0 ? this.n : f); this.i = f < 0 ? this.n : f + v.length; return { t: 'str', v: t }; }
            if (/^[0-9]/.test(v)) {
                // 1.5, 1e10, 2.5e-3
                let w = v;
                if (s[this.i] === '.' && /[0-9]/.test(s[this.i + 1] || '')) { let k = this.i + 1; while (/[0-9eE]/.test(s[k] || '')) k++; w += s.slice(this.i, k); this.i = k; }
                if (/[eE]$/.test(w) && /[+-]/.test(s[this.i] || '')) { let k = this.i + 1; while (/[0-9]/.test(s[k] || '')) k++; w += s.slice(this.i, k); this.i = k; }
                return { t: 'num', v: w };
            }
            return { t: 'id', v };
        }
        if (c === '.' && /[0-9]/.test(s[this.i + 1] || '')) { let k = this.i + 1; while (/[0-9eE]/.test(s[k] || '')) k++; const v = s.slice(this.i, k); this.i = k; return { t: 'num', v }; }
        this.i++;
        return { t: 'p', v: c };
    };
    Leitor.prototype.espiar = function () { const i = this.i, t = this.prox(); this.i = i; return t; };

    const P = (t, v) => t && t.t === 'p' && t.v === v;
    const K = (t, v) => t && t.t === 'id' && !t.q && t.v.toUpperCase() === v;

    /** Divide uma lista de tokens pelas vírgulas do nível de fora dos parênteses. */
    function porVirgula(toks) {
        const out = [];
        let atual = [], nivel = 0;
        for (const t of toks) {
            if (P(t, '(')) nivel++;
            if (P(t, ')')) nivel--;
            if (P(t, ',') && nivel === 0) { out.push(atual); atual = []; continue; }
            atual.push(t);
        }
        if (atual.length) out.push(atual);
        return out;
    }
    /** Nomes entre parênteses a partir de toks[i] === '(' → { nomes, fim }. */
    function nomesEntre(toks, i) {
        const nomes = [];
        if (!P(toks[i], '(')) return { nomes, fim: i };
        let j = i + 1, nivel = 0;
        for (; j < toks.length; j++) {
            const t = toks[j];
            if (P(t, '(')) nivel++;
            else if (P(t, ')')) { if (nivel === 0) break; nivel--; }
            else if (nivel === 0 && t.t === 'id') nomes.push(t.v);
        }
        return { nomes, fim: j + 1 };
    }
    /** Nome com esquema (banco.tabela, public."tabela") → só a tabela. Lê a partir de toks[i]. */
    function nomeEm(toks, i) {
        let nome = toks[i] && toks[i].v, j = i + 1;
        while (P(toks[j], '.') && toks[j + 1]) { nome = toks[j + 1].v; j += 2; }
        return { nome: String(nome || ''), fim: j };
    }
    function textoTipo(toks) {
        let out = '';
        toks.forEach((t, i) => {
            const v = t.t === 'str' ? "'" + t.v + "'" : t.v;
            if (P(t, '(') || P(t, ')') || i === 0 || P(toks[i - 1], '(')) out += v;
            else if (P(t, ',')) out += ', ';
            else if (P(toks[i - 1], ',')) out += v;
            else out += ' ' + v;
        });
        return out.slice(0, 48);
    }

    const FIM_TIPO = new Set(['NOT', 'NULL', 'DEFAULT', 'PRIMARY', 'REFERENCES', 'AUTO_INCREMENT', 'AUTOINCREMENT', 'UNIQUE', 'COMMENT',
        'COLLATE', 'CHARACTER', 'CHARSET', 'GENERATED', 'CHECK', 'CONSTRAINT', 'ON', 'IDENTITY', 'AS', 'KEY']);

    function lerCreate(toks, banco) {
        // CREATE [TEMPORARY] TABLE [IF NOT EXISTS] nome ( ... )
        let i = 1;
        while (toks[i] && !K(toks[i], 'TABLE')) i++;
        i++;
        if (K(toks[i], 'IF')) i += 3;
        const { nome, fim } = nomeEm(toks, i);
        i = fim;
        if (!nome || !P(toks[i], '(')) return;
        let nivel = 0, j = i;
        for (; j < toks.length; j++) {
            if (P(toks[j], '(')) nivel++;
            else if (P(toks[j], ')') && --nivel === 0) break;
        }
        const t = tabela(banco, nome, true);
        if (!t) return;
        for (let def of porVirgula(toks.slice(i + 1, j))) {
            if (!def.length) continue;
            if (K(def[0], 'CONSTRAINT')) def = def.slice(2);
            const w = def[0] && !def[0].q ? def[0].v.toUpperCase() : '';
            if (w === 'PRIMARY') { nomesEntre(def, def.findIndex(x => P(x, '('))).nomes.forEach(c => marcar(t, c, 'pk')); continue; }
            if (w === 'FOREIGN') { chaveEstrangeira(t, def); continue; }
            if (w === 'UNIQUE') { const n = nomesEntre(def, def.findIndex(x => P(x, '('))).nomes; if (n.length === 1) marcar(t, n[0], 'uq'); continue; }
            if (['KEY', 'INDEX', 'FULLTEXT', 'SPATIAL', 'CHECK', 'EXCLUDE', 'PERIOD'].includes(w)) continue;
            if (def[0].t !== 'id' || t.colunas.length >= MAX_COLUNAS) continue;
            const col = { n: def[0].v, tipo: '', pk: false, fk: null, nn: false, ai: false, uq: false };
            let k = 1;
            while (k < def.length && !(def[k].t === 'id' && !def[k].q && FIM_TIPO.has(def[k].v.toUpperCase()))) {
                if (P(def[k], '(')) { let nv = 0; do { if (P(def[k], '(')) nv++; if (P(def[k], ')')) nv--; k++; } while (k < def.length && nv > 0); continue; }
                k++;
            }
            col.tipo = textoTipo(def.slice(1, k));
            for (; k < def.length; k++) {
                if (K(def[k], 'PRIMARY')) col.pk = true;
                else if (K(def[k], 'NOT') && K(def[k + 1], 'NULL')) col.nn = true;
                else if (K(def[k], 'AUTO_INCREMENT') || K(def[k], 'AUTOINCREMENT') || K(def[k], 'IDENTITY')) col.ai = true;
                else if (K(def[k], 'UNIQUE')) col.uq = true;
                else if (K(def[k], 'REFERENCES')) {
                    const r = nomeEm(def, k + 1), alvo = nomesEntre(def, r.fim).nomes;
                    col.fk = { t: r.nome, c: alvo[0] || null };
                    k = r.fim;
                }
            }
            if (/serial/i.test(col.tipo)) col.ai = true;
            t.colunas.push(col);
        }
    }
    function chaveEstrangeira(t, def) {
        // FOREIGN KEY [nome] (a, b) REFERENCES outra (x, y)
        const ini = def.findIndex(x => P(x, '('));
        const de = nomesEntre(def, ini);
        const r = def.findIndex((x, k) => k >= de.fim && K(x, 'REFERENCES'));
        if (r < 0) return;
        const alvo = nomeEm(def, r + 1), para = nomesEntre(def, alvo.fim).nomes;
        de.nomes.forEach((c, k) => {
            const col = t.colunas.find(x => x.n === c);
            if (col) col.fk = { t: alvo.nome, c: para[k] || para[0] || null };
            else t.fkPendentes.push({ c, fk: { t: alvo.nome, c: para[k] || para[0] || null } });
        });
    }
    function marcar(t, c, campo) {
        const col = t.colunas.find(x => x.n === c);
        if (col) col[campo] = true;
        else if (campo === 'pk') t.pkPendentes.push(c);
    }
    function lerAlter(toks, banco) {
        // ALTER TABLE [ONLY] [IF EXISTS] nome ADD ..., ADD ...
        let i = 2;
        if (K(toks[i], 'ONLY')) i++;
        if (K(toks[i], 'IF')) i += 2;
        const { nome, fim } = nomeEm(toks, i);
        const t = banco.tabelas.get(nome.toLowerCase());
        if (!t) return;
        for (let a of porVirgula(toks.slice(fim))) {
            if (!K(a[0], 'ADD')) continue;
            a = a.slice(1);
            if (K(a[0], 'CONSTRAINT')) a = a.slice(2);
            if (K(a[0], 'PRIMARY')) nomesEntre(a, a.findIndex(x => P(x, '('))).nomes.forEach(c => marcar(t, c, 'pk'));
            else if (K(a[0], 'FOREIGN')) chaveEstrangeira(t, a);
            else if (K(a[0], 'UNIQUE')) { const n = nomesEntre(a, a.findIndex(x => P(x, '('))).nomes; if (n.length === 1) marcar(t, n[0], 'uq'); }
        }
    }
    function tabela(banco, nome, criando) {
        const k = nome.toLowerCase();
        let t = banco.tabelas.get(k);
        if (!t) {
            if (banco.tabelas.size >= MAX_TABELAS) { banco.aviso(`O arquivo tem mais de ${MAX_TABELAS} tabelas; li só as primeiras.`); return null; }
            t = { nome, colunas: [], total: 0, linhas: [], pkPendentes: [], fkPendentes: [], semCreate: !criando };
            banco.tabelas.set(k, t);
        }
        return t;
    }

    /** Valor de uma coluna num INSERT: texto, número ou NULL; o resto (-5, NOW(), 'a'::text) fica escrito como está. */
    function valor(partes) {
        if (partes.length === 1) {
            const t = partes[0];
            if (K(t, 'NULL')) return null;
            return t.v;
        }
        return partes.map(t => (t.t === 'str' ? "'" + t.v + "'" : t.v)).join('');
    }

    /** Lê as linhas de um INSERT direto do texto (sem guardar todos os tokens: dumps têm INSERTs enormes). */
    function lerInsert(L, banco, delim) {
        // INSERT|REPLACE [LOW_PRIORITY|DELAYED|HIGH_PRIORITY] [IGNORE] [INTO] nome [(colunas)] VALUES (...), (...)
        let t = L.prox();
        while (t && t.t === 'id' && !t.q && ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY', 'IGNORE', 'INTO', 'OR', 'REPLACE', 'ABORT', 'FAIL', 'ROLLBACK'].includes(t.v.toUpperCase())) t = L.prox();
        if (!t || t.t !== 'id') return;
        let nome = t.v;
        while (P(L.espiar(), '.')) { L.prox(); nome = (L.prox() || {}).v || nome; }
        let cols = null;
        t = L.prox();
        if (P(t, '(')) {
            cols = [];
            for (t = L.prox(); t && !P(t, ')'); t = L.prox()) if (t.t === 'id') cols.push(t.v);
            t = L.prox();
        }
        if (!(K(t, 'VALUES') || K(t, 'VALUE'))) return; // INSERT ... SELECT: não dá para saber as linhas
        const tab = tabela(banco, nome, false);
        if (!tab) return;
        if (!tab.colunas.length && cols) cols.slice(0, MAX_COLUNAS).forEach(c => tab.colunas.push({ n: c, tipo: '', pk: false, fk: null, nn: false, ai: false, uq: false }));
        const mapa = cols ? cols.map(c => tab.colunas.findIndex(x => x.n.toLowerCase() === c.toLowerCase())) : null;
        for (;;) {
            t = L.prox();
            if (!P(t, '(')) { L.voltar = t; return; }
            const vals = [];
            let partes = [], nivel = 0;
            for (t = L.prox(); t; t = L.prox()) {
                if (nivel === 0 && (P(t, ',') || P(t, ')'))) {
                    vals.push(valor(partes));
                    partes = [];
                    if (P(t, ')')) break;
                    continue;
                }
                if (P(t, '(')) nivel++;
                if (P(t, ')')) nivel--;
                partes.push(t);
            }
            if (!tab.colunas.length) vals.forEach((_, k) => tab.colunas.length < MAX_COLUNAS && tab.colunas.push({ n: 'coluna_' + (k + 1), tipo: '', pk: false, fk: null, nn: false, ai: false, uq: false }));
            let linha = vals;
            if (mapa) { linha = new Array(tab.colunas.length).fill(null); mapa.forEach((p, k) => { if (p >= 0) linha[p] = vals[k] ?? null; }); }
            tab.total++;
            if (banco.usado < ORCAMENTO) {
                tab.linhas.push(linha.map(v => (v == null ? null : String(v))));
                banco.usado += linha.reduce((s, v) => s + (v == null ? 5 : String(v).length + 3), 2);
            }
            t = L.prox();
            if (P(t, ',')) continue;
            L.voltar = t;
            return;
        }
    }

    /** Lê o arquivo inteiro → { tabelas: [...], linhas: { nome: [[...]] }, avisos: [] }. */
    function lerSql(texto) {
        const avisos = new Set();
        const banco = { tabelas: new Map(), usado: 0, aviso: m => avisos.add(m) };
        const L = new Leitor(texto);
        let delim = ';';
        // fim do comando: ";" ou, com DELIMITER, o delimitador (pode vir grudado, como em END$$)
        const fimComando = t => (delim === ';' ? P(t, ';') : !!t && typeof t.v === 'string' && t.t !== 'str' && t.v.endsWith(delim));
        // pula até o fim do comando atual (respeitando o DELIMITER do MySQL)
        const pular = () => {
            for (;;) {
                L.pularEspacos();
                if (L.i >= L.n) return;
                if (delim !== ';' && L.s.startsWith(delim, L.i)) { L.i += delim.length; return; }
                const t = L.prox();
                if (!t || fimComando(t)) return;
            }
        };
        while (L.i < L.n) {
            L.voltar = undefined;
            const t = L.prox();
            if (!t) break;
            if (P(t, ';')) continue;
            const w = t.t === 'id' && !t.q ? t.v.toUpperCase() : '';
            if (w === 'DELIMITER') {
                const f = L.s.indexOf('\n', L.i);
                delim = L.s.slice(L.i, f < 0 ? L.n : f).trim() || ';';
                L.i = f < 0 ? L.n : f + 1;
                L.dolar = delim === ';';
                continue;
            }
            if (w === 'INSERT' || w === 'REPLACE') {
                lerInsert(L, banco, delim);
                if (!(L.voltar && fimComando(L.voltar))) pular();
                continue;
            }
            if (w === 'CREATE' || w === 'ALTER') {
                // comandos de estrutura são curtos: junta os tokens até o fim
                const toks = [t];
                for (;;) {
                    L.pularEspacos();
                    if (L.i >= L.n) break;
                    if (delim !== ';' && L.s.startsWith(delim, L.i)) { L.i += delim.length; break; }
                    const x = L.prox();
                    if (!x || fimComando(x)) break;
                    if (toks.length < 20000) toks.push(x);
                }
                const tipo = (toks.find((x, k) => k > 0 && x.t === 'id' && !x.q && !['TEMPORARY', 'TEMP', 'UNLOGGED', 'OR', 'REPLACE', 'GLOBAL', 'LOCAL'].includes(x.v.toUpperCase())) || {}).v || '';
                if (tipo.toUpperCase() === 'TABLE') {
                    if (w === 'CREATE' && !toks.some(x => K(x, 'AS') || K(x, 'LIKE'))) lerCreate(toks, banco);
                    else if (w === 'ALTER') lerAlter(toks, banco);
                }
                continue;
            }
            if (w === 'COPY') avisos.add('Comandos COPY do PostgreSQL não foram lidos. Exporte com INSERTs (pg_dump --inserts).');
            pular();
        }

        const tabelas = [...banco.tabelas.values()];
        const nomes = new Map(tabelas.map(t => [t.nome.toLowerCase(), t]));
        tabelas.forEach(t => {
            t.pkPendentes.forEach(c => { const col = t.colunas.find(x => x.n === c); if (col) col.pk = true; });
            t.fkPendentes.forEach(p => { const col = t.colunas.find(x => x.n === p.c); if (col) col.fk = p.fk; });
            // chave estrangeira sem coluna de destino: aponta para a chave primária da outra tabela
            t.colunas.forEach(c => {
                if (!c.fk) return;
                const alvo = nomes.get(String(c.fk.t).toLowerCase());
                if (alvo) { c.fk.t = alvo.nome; if (!c.fk.c) c.fk.c = (alvo.colunas.find(x => x.pk) || alvo.colunas[0] || {}).n || null; }
            });
        });
        if (tabelas.some(t => t.linhas.length < t.total)) avisos.add('O arquivo tem mais dados do que cabe no cartão: guardei as primeiras linhas e contei todas.');
        if (tabelas.some(t => t.semCreate)) avisos.add('Algumas tabelas só tinham INSERT (sem CREATE TABLE): os tipos das colunas ficaram em branco.');

        const linhas = {};
        tabelas.forEach(t => { linhas[t.nome] = t.linhas; });
        return {
            tabelas: tabelas.map(t => ({ nome: t.nome, colunas: t.colunas, total: t.total, guardadas: t.linhas.length })),
            linhas,
            avisos: [...avisos],
        };
    }

    /* =================== diagrama =================== */
    function alturaTabela(t) {
        const n = Math.min(t.colunas.length, MOSTRA_COL) + (t.colunas.length > MOSTRA_COL ? 1 : 0);
        return CAB_H + Math.max(1, n) * LIN_H + 8;
    }
    /** Primeira arrumação: tabelas mais ligadas primeiro, em colunas. */
    function arrumar(tabelas) {
        const grau = new Map(tabelas.map(t => [t.nome, 0]));
        tabelas.forEach(t => t.colunas.forEach(c => { if (c.fk && grau.has(c.fk.t)) { grau.set(t.nome, grau.get(t.nome) + 1); grau.set(c.fk.t, grau.get(c.fk.t) + 1); } }));
        const ordem = [...tabelas].sort((a, b) => grau.get(b.nome) - grau.get(a.nome));
        const cols = Math.max(1, Math.ceil(Math.sqrt(ordem.length * 1.4)));
        const alturas = new Array(cols).fill(20);
        ordem.forEach((t, i) => {
            const c = i % cols;
            t.x = 20 + c * (BOX_W + 70);
            t.y = alturas[c];
            alturas[c] += alturaTabela(t) + 40;
        });
    }
    function linhaDe(t, nomeCol) {
        const i = t.colunas.findIndex(c => c.n === nomeCol);
        return i < 0 ? 0 : Math.min(i, MOSTRA_COL);
    }

    function metodos() {
        return {
            bdUI(b) {
                return (this.ui['bd:' + b.id] ??= { tab: '', busca: '', pag: 0, msg: '', lendo: false, z: 1 });
            },
            bdLinhas(b) { return this.imagens[b.id + '.sql'] || {}; },
            bdResumo(b) {
                const ts = b.dados.tabelas || [];
                const total = ts.reduce((s, t) => s + (t.total || 0), 0);
                const fks = ts.reduce((s, t) => s + t.colunas.filter(c => c.fk).length, 0);
                return `${b.dados.arquivo || 'arquivo'} · ${ts.length} ${ts.length === 1 ? 'tabela' : 'tabelas'} · ${fks} ${fks === 1 ? 'ligação' : 'ligações'} · ${total.toLocaleString('pt-BR')} ${total === 1 ? 'linha' : 'linhas'}`;
            },
            async bdLer(b, arq) {
                const u = this.bdUI(b);
                if (!arq) return;
                if (!/\.sql$/i.test(arq.name) && !/sql|text/.test(arq.type || '')) { u.msg = 'Escolha um arquivo .sql.'; return; }
                if (arq.size > MAX_ARQUIVO) { u.msg = 'O arquivo passa de 25 MB. Exporte só as tabelas que quer ver.'; return; }
                if ((b.dados.tabelas || []).length && !confirm('Trocar o banco deste cartão pelo novo arquivo?')) return;
                u.lendo = true; u.msg = 'Lendo o arquivo…';
                await new Promise(r => setTimeout(r, 30)); // deixa o aviso aparecer antes do trabalho pesado
                try {
                    const r = lerSql(await arq.text());
                    if (!r.tabelas.length) { u.msg = 'Não achei nenhuma tabela (CREATE TABLE ou INSERT) neste arquivo.'; return; }
                    arrumar(r.tabelas);
                    let json = JSON.stringify(r.linhas);
                    // garantia: o servidor aceita até 6 milhões de caracteres por chave
                    while (json.length > 5800000) {
                        Object.keys(r.linhas).forEach(k => { r.linhas[k] = r.linhas[k].slice(0, Math.floor(r.linhas[k].length * 0.8)); });
                        json = JSON.stringify(r.linhas);
                    }
                    r.tabelas.forEach(t => { t.guardadas = (r.linhas[t.nome] || []).length; });
                    await Q.enviar(`${this.cfg.url}/img.${b.id}.sql`, json);
                    this.imagens[b.id + '.sql'] = r.linhas;
                    b.dados.tabelas = r.tabelas;
                    b.dados.arquivo = arq.name.slice(0, 120);
                    b.dados.lido = new Date().toISOString().slice(0, 10);
                    b.dados.avisos = r.avisos;
                    if (!b.titulo || b.titulo === 'Banco de dados') b.titulo = arq.name.replace(/\.sql$/i, '').slice(0, 80) || 'Banco de dados';
                    u.tab = r.tabelas[0].nome; u.pag = 0; u.busca = '';
                    u.msg = '';
                } catch (e) {
                    console.error('[Banco .sql]', e);
                    u.msg = e && /^\d+$/.test(e.message) ? 'Não consegui salvar os dados no servidor (' + e.message + '). Tente um arquivo menor.' : 'Não consegui ler este arquivo.';
                } finally {
                    u.lendo = false;
                }
            },
            bdLimpar(b) {
                if (!confirm('Tirar o banco deste cartão? A estrutura e os dados saem daqui.')) return;
                b.dados.tabelas = []; b.dados.arquivo = ''; b.dados.avisos = [];
                this.apagarImagens(b.id + '.sql');
            },
            bdZoom(b, passo) {
                const u = this.bdUI(b);
                u.z = passo ? Math.min(2, Math.max(0.3, Math.round((u.z + passo) * 10) / 10)) : 1;
            },

            /** SVG do diagrama (texto escapado; desenhado com x-html). */
            bdSvg(b) {
                const ts = b.dados.tabelas || [], z = this.bdUI(b).z;
                if (!ts.length) return '';
                const por = new Map(ts.map(t => [t.nome, t]));
                let w = 0, h = 0, linhas = '', caixas = '';
                ts.forEach(t => { w = Math.max(w, (t.x || 0) + BOX_W + 40); h = Math.max(h, (t.y || 0) + alturaTabela(t) + 40); });
                ts.forEach(t => t.colunas.forEach((c, i) => {
                    const alvo = c.fk && por.get(c.fk.t);
                    if (!alvo) return;
                    const y1 = t.y + CAB_H + Math.min(i, MOSTRA_COL) * LIN_H + LIN_H / 2 + 2;
                    const y2 = alvo.y + CAB_H + linhaDe(alvo, c.fk.c) * LIN_H + LIN_H / 2 + 2;
                    let x1, x2, d;
                    if (alvo === t) {
                        x1 = t.x + BOX_W; d = `M${x1} ${y1} C${x1 + 40} ${y1},${x1 + 40} ${y2},${x1} ${y2}`;
                    } else if (Math.abs(alvo.x - t.x) < BOX_W / 2) {
                        x1 = t.x + BOX_W; x2 = alvo.x + BOX_W;
                        const dx = 40 + Math.abs(y2 - y1) * 0.1;
                        d = `M${x1} ${y1} C${x1 + dx} ${y1},${x2 + dx} ${y2},${x2} ${y2}`;
                    } else {
                        const dir = alvo.x > t.x ? 1 : -1;
                        x1 = dir > 0 ? t.x + BOX_W : t.x; x2 = dir > 0 ? alvo.x : alvo.x + BOX_W;
                        const dx = Math.max(30, Math.abs(x2 - x1) / 2) * dir;
                        d = `M${x1} ${y1} C${x1 + dx} ${y1},${x2 - dx} ${y2},${x2} ${y2}`;
                    }
                    linhas += `<path class="bd-lig" d="${d}" marker-end="url(#bd-seta-${esc(b.id)})"><title>${esc(t.nome + '.' + c.n + ' → ' + alvo.nome + '.' + (c.fk.c || ''))}</title></path><circle class="bd-pt" cx="${x1}" cy="${y1}" r="3"/>`;
                }));
                ts.forEach(t => {
                    const hT = alturaTabela(t);
                    let g = `<g data-bd-t="${esc(t.nome)}" transform="translate(${t.x || 0},${t.y || 0})">`
                        + `<rect class="bd-caixa" width="${BOX_W}" height="${hT}" rx="8"/>`
                        + `<path class="bd-cab" d="M0 8a8 8 0 0 1 8-8h${BOX_W - 16}a8 8 0 0 1 8 8v${CAB_H - 8}h-${BOX_W}z"/>`
                        + `<text class="bd-nome" x="10" y="18">${esc(t.nome.length > 24 ? t.nome.slice(0, 23) + '…' : t.nome)}</text>`
                        + `<text class="bd-qtd" x="${BOX_W - 8}" y="18" text-anchor="end">${(t.total || 0).toLocaleString('pt-BR')}</text>`;
                    t.colunas.slice(0, MOSTRA_COL).forEach((c, i) => {
                        const y = CAB_H + i * LIN_H + 14;
                        const tag = (c.pk ? 'PK' : '') + (c.pk && c.fk ? ',' : '') + (c.fk ? 'FK' : '') || (c.uq ? 'UK' : '');
                        g += `<text class="bd-tag" x="8" y="${y}">${tag}</text>`
                            + `<text class="bd-col${c.pk ? ' pk' : ''}" x="40" y="${y}">${esc(c.n.length > 18 ? c.n.slice(0, 17) + '…' : c.n)}</text>`
                            + `<text class="bd-tipo" x="${BOX_W - 8}" y="${y}" text-anchor="end">${esc((c.tipo || '').replace(/\s*(unsigned|zerofill)/i, '').slice(0, 14))}</text>`;
                    });
                    if (t.colunas.length > MOSTRA_COL) g += `<text class="bd-tipo" x="40" y="${CAB_H + MOSTRA_COL * LIN_H + 14}">+ ${t.colunas.length - MOSTRA_COL} colunas</text>`;
                    caixas += g + `<title>${esc(t.nome)}: ${t.colunas.length} colunas, ${(t.total || 0).toLocaleString('pt-BR')} linhas · clique duplo para ver os dados</title></g>`;
                });
                return `<svg class="bd-svg" width="${Math.round(w * z)}" height="${Math.round(h * z)}" viewBox="0 0 ${Math.round(w)} ${Math.round(h)}" role="img" aria-label="Diagrama das tabelas">`
                    + `<defs><marker id="bd-seta-${esc(b.id)}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="bd-seta" d="M0 0L10 5L0 10z"/></marker></defs>`
                    + linhas + caixas + '</svg>';
            },
            /** Arrastar uma tabela do diagrama (pelo corpo da caixa). */
            bdPegar(b, e) {
                const g = e.target.closest && e.target.closest('[data-bd-t]');
                if (!g || e.button !== 0) return;
                const t = (b.dados.tabelas || []).find(x => x.nome === g.getAttribute('data-bd-t'));
                if (!t) return;
                e.preventDefault();
                e.stopPropagation();
                const z = this.bdUI(b).z, x0 = e.clientX, y0 = e.clientY, tx = t.x || 0, ty = t.y || 0;
                const mover = ev => { t.x = Math.max(0, Math.round((tx + (ev.clientX - x0) / z) / 10) * 10); t.y = Math.max(0, Math.round((ty + (ev.clientY - y0) / z) / 10) * 10); };
                const soltar = () => { removeEventListener('pointermove', mover); removeEventListener('pointerup', soltar); removeEventListener('pointercancel', soltar); };
                addEventListener('pointermove', mover);
                addEventListener('pointerup', soltar);
                addEventListener('pointercancel', soltar);
            },
            bdAbrirTabela(b, e) {
                const g = e.target.closest && e.target.closest('[data-bd-t]');
                if (!g) return;
                const u = this.bdUI(b);
                u.tab = g.getAttribute('data-bd-t'); u.pag = 0; u.busca = '';
                b.dados.visao = 'dados';
            },
            bdRearrumar(b) { arrumar(b.dados.tabelas || []); },

            /* ---------- dados ---------- */
            bdTabela(b) {
                const ts = b.dados.tabelas || [], u = this.bdUI(b);
                return ts.find(t => t.nome === u.tab) || ts[0] || null;
            },
            bdFiltradas(b) {
                const t = this.bdTabela(b), u = this.bdUI(b);
                if (!t) return [];
                const todas = this.bdLinhas(b)[t.nome] || [];
                const q = u.busca.trim().toLowerCase();
                return q ? todas.filter(l => l.some(v => v != null && String(v).toLowerCase().includes(q))) : todas;
            },
            bdPagina(b) {
                const u = this.bdUI(b), f = this.bdFiltradas(b);
                const max = Math.max(0, Math.ceil(f.length / POR_PAGINA) - 1);
                if (u.pag > max) u.pag = max;
                return f.slice(u.pag * POR_PAGINA, (u.pag + 1) * POR_PAGINA);
            },
            bdRodape(b) {
                const t = this.bdTabela(b), u = this.bdUI(b), f = this.bdFiltradas(b);
                if (!t) return '';
                const ini = f.length ? u.pag * POR_PAGINA + 1 : 0, fim = Math.min(f.length, (u.pag + 1) * POR_PAGINA);
                let txt = `${ini}–${fim} de ${f.length.toLocaleString('pt-BR')}`;
                if (u.busca.trim()) txt += ' encontradas';
                if (t.guardadas < t.total) txt += ` · ${t.total.toLocaleString('pt-BR')} no arquivo (guardadas ${t.guardadas.toLocaleString('pt-BR')})`;
                if (t.total && !t.guardadas && !this.imagens[b.id + '.sql']) txt = 'Os dados não vieram junto (cartão restaurado ou copiado). Abra o .sql de novo para ver as linhas.';
                return txt;
            },
            bdTemMais(b) { const u = this.bdUI(b); return (u.pag + 1) * POR_PAGINA < this.bdFiltradas(b).length; },
            bdBaixarCsv(b) {
                const t = this.bdTabela(b);
                if (!t) return;
                const cel = v => (v == null ? '' : /[";\n\r]/.test(String(v)) ? '"' + String(v).replace(/"/g, '""') + '"' : String(v));
                const csv = [t.colunas.map(c => cel(c.n)).join(';'), ...this.bdFiltradas(b).map(l => l.map(cel).join(';'))].join('\r\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
                a.download = (t.nome || 'tabela').replace(/[^\w.-]+/g, '_') + '.csv';
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 2000);
            },

            /** Cria um cartão "Mapa mental e diagramas" com as tabelas como entidades, para editar à vontade. */
            bdParaMapa(b) {
                const ts = (b.dados.tabelas || []).slice(0, 300);
                if (!ts.length) return;
                const ids = new Map(ts.map(t => [t.nome, uid()]));
                const nos = ts.map(t => ({
                    id: ids.get(t.nome), t: t.nome, x: t.x || 0, y: t.y || 0, pai: null, cor: '#2f6fc7', forma: 'entidade', w: 200,
                    campos: t.colunas.slice(0, 30).map(c => [c.pk && 'PK', c.fk && 'FK', !c.pk && !c.fk && c.uq && 'UK'].filter(Boolean).join(',') + (c.pk || c.fk || c.uq ? ' ' : '') + c.n + (c.tipo ? ' ' + c.tipo : '')).join('\n') || 'PK id',
                }));
                const lig = [];
                ts.forEach(t => t.colunas.forEach(c => {
                    if (c.fk && ids.has(c.fk.t) && lig.length < 600)
                        lig.push({ id: uid(), de: ids.get(t.nome), para: ids.get(c.fk.t), rot: c.n, estilo: 'ortogonal', traco: 'solido', ini: 'nenhum', fim: 'seta', cor: '', w: 1.8 });
                }));
                const L = this.estado.layout?.[b.secao]?.[b.id];
                const id = this.criarBloco(b.secao, 'mapa', L ? { x: L.x, y: L.y + L.h + 16 } : null, { titulo: (b.titulo || 'Banco') + ' (diagrama)', dados: { nos, lig, tracos: [] } });
                if (id) this.aviso('Diagrama criado num cartão de Mapa mental logo abaixo. Lá dá para editar, colorir e ligar à vontade.');
            },
        };
    }

    window.WFBancoSql = { lerSql }; // usado nos testes (e útil no console)

    const anterior = window.diversoAbasDiagrama;
    window.diversoAbasDiagrama = function () {
        const base = anterior();
        base.tiposBloco = [...base.tiposBloco, { id: 'banco', nome: 'Banco de dados (.sql)' }];
        const criar = base.criarBloco;
        base.criarBloco = function (sec, tipo, pos, over = {}) {
            if (tipo !== 'banco') return criar.call(this, sec, tipo, pos, over);
            const id = 'b' + uid(), W = this.col || 1088, w = Math.min(780, W), h = 520;
            this.estado.blocos.push({ id, secao: sec, tipo, titulo: over.titulo || 'Banco de dados', links: [], dados: { tabelas: [], visao: 'diagrama', ...(over.dados || {}) } });
            const y0 = this.alturaConteudo(sec);
            (this.estado.layout[sec] ??= {})[id] = pos ? { x: pos.x, y: pos.y, w, h, z: ++this.zTop } : { x: 0, y: y0 ? y0 + 16 : 0, w, h, z: ++this.zTop };
            return id;
        };
        return Object.assign(base, metodos());
    };
})();
