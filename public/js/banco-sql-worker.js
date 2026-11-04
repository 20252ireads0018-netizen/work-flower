/* Work Flower · cartão "Banco de dados (.sql)" · worker
   Tudo que é pesado roda aqui, fora da página (que nunca trava):
   - lê o arquivo aos pedaços (File.stream() + TextDecoder), sem carregar o arquivo inteiro na memória;
     entende dumps do MySQL/MariaDB (INSERT com várias linhas, crases, textos com \' e ''), do PostgreSQL
     (INSERT e blocos COPY ... FROM stdin), do SQLite e arquivos .csv;
   - junta as linhas de cada tabela em pedaços de ~12 MB de JSON, comprime (gzip) e manda em base64 para o
     servidor (sqlz.<cartão>.<versão>.<tabela>.<n>), um pedido de cada vez, nunca acima de ~6 MB. Texto SQL
     cru nunca sai do navegador: o mod_security da hospedagem recusa (406);
   - guarda uma cópia comprimida no IndexedDB, para abrir de novo sem baixar;
   - abre uma tabela só quando ela é pedida, e responde busca, filtros, ordenação, janelas de linhas
     (a página só desenha as linhas visíveis), estatísticas por coluna e o CSV para baixar.
   Também roda no Node (tests/banco-sql), sem o self.onmessage. */
'use strict';

const PEDACO_BYTES = 12000000;  // JSON cru por pedaço (antes do gzip; comprime ~5x, e se passar do limite divide ao meio)
const MAX_B64 = 5900000;        // o servidor aceita até 6 milhões de caracteres por chave
const MAX_TABELAS = 3000, MAX_COLUNAS = 500;
const ESCAPES = { n: '\n', t: '\t', r: '\r', 0: '\0', Z: '\x1a', b: '\b' };

/* =================== tokens (para CREATE/ALTER, que são curtos) =================== */
const PALAVRA = /[A-Za-z0-9_$\u0080-￿]/;
function Leitor(s, i, n) { this.s = s; this.i = i || 0; this.n = n == null ? s.length : n; }
Leitor.prototype.pularEspacos = function () {
    const s = this.s;
    for (;;) {
        const c = s.charCodeAt(this.i);
        if (c === 32 || c === 9 || c === 10 || c === 13 || c === 12) { this.i++; continue; }
        if (c === 45 && s.charCodeAt(this.i + 1) === 45) { const f = s.indexOf('\n', this.i); this.i = f < 0 || f > this.n ? this.n : f + 1; continue; }
        if (c === 35) { const f = s.indexOf('\n', this.i); this.i = f < 0 || f > this.n ? this.n : f + 1; continue; }
        if (c === 47 && s.charCodeAt(this.i + 1) === 42) { const f = s.indexOf('*/', this.i + 2); this.i = f < 0 || f > this.n ? this.n : f + 2; continue; }
        return;
    }
};
Leitor.prototype.texto = function (q) {
    const s = this.s, n = this.n, cq = q.charCodeAt(0), barra = q !== '`';
    let out = '', ini = this.i, i = this.i;
    while (i < n) {
        const c = s.charCodeAt(i);
        if (c === 92 && barra) { out += s.slice(ini, i); const e = s[i + 1]; out += ESCAPES[e] ?? e ?? ''; i += 2; ini = i; continue; }
        if (c === cq) {
            if (s.charCodeAt(i + 1) === cq) { out += s.slice(ini, i + 1); i += 2; ini = i; continue; }
            this.i = i + 1;
            return out + s.slice(ini, i);
        }
        i++;
    }
    this.i = n;
    return out + s.slice(ini, n);
};
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
        if (s[j] === "'" && /^[NnEeXxBb]$/.test(v)) { this.i++; const t = this.texto("'"); return /^[NnEe]$/.test(v) ? { t: 'str', v: t } : { t: 'raw', v: v + "'" + t + "'" }; }
        if (/^\$\w*\$$/.test(v)) { const f = s.indexOf(v, this.i); const t = s.slice(this.i, f < 0 ? this.n : f); this.i = f < 0 ? this.n : f + v.length; return { t: 'str', v: t }; }
        if (/^[0-9]/.test(v)) {
            let w = v;
            if (s[this.i] === '.' && /[0-9]/.test(s[this.i + 1] || '')) { let k = this.i + 1; while (/[0-9eE]/.test(s[k] || '')) k++; w += s.slice(this.i, k); this.i = k; }
            return { t: 'num', v: w };
        }
        return { t: 'id', v };
    }
    this.i++;
    return { t: 'p', v: c };
};
const P = (t, v) => t && t.t === 'p' && t.v === v;
const K = (t, v) => t && t.t === 'id' && !t.q && t.v.toUpperCase() === v;
function tokens(s) { const L = new Leitor(s), out = []; for (let t = L.prox(); t && out.length < 50000; t = L.prox()) out.push(t); return out; }

function porVirgula(toks) {
    const out = []; let atual = [], nivel = 0;
    for (const t of toks) {
        if (P(t, '(')) nivel++;
        if (P(t, ')')) nivel--;
        if (P(t, ',') && nivel === 0) { out.push(atual); atual = []; continue; }
        atual.push(t);
    }
    if (atual.length) out.push(atual);
    return out;
}
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
const novaColuna = n => ({ n, tipo: '', pk: false, fk: null, nn: false, ai: false, uq: false });

/* =================== o analisador em fluxo =================== */
/**
 * Recebe o texto aos pedaços (alimentar) e chama aoPedaco([[tabela, linhas], ...]):
 * - tabela que juntou ~12 MB de linhas vai sozinha num pedaço (abrir essa tabela baixa só os pedaços dela);
 * - as pequenas vão juntas num lote quando o pendente passa de ~36 MB e no fim (1.500 tabelas pequenas não viram 1.500 pedidos).
 * Nunca guarda o arquivo inteiro: só o resto do pedaço anterior que ainda não formou um comando/linha completos.
 */
function Analisador(op) {
    this.op = op || {};
    this.buf = ''; this.p = 0; this.eof = false;
    this.modo = 'topo';           // topo | valores | copy | pular | csv
    this.delim = ';';
    this.tabelas = new Map(); this.lista = [];
    this.avisos = new Set();
    this.alvo = null; this.mapa = null; this.varre = null;
    this.csv = op && op.csv ? { nome: op.csv, cab: null, sep: null } : null;
    if (this.csv) this.modo = 'csv';
}
Analisador.prototype.alimentar = function (texto) {
    if (this.varre) this.varre.k -= this.p; // a varredura guardada acompanha o recorte do buffer
    this.buf = this.p < this.buf.length ? this.buf.slice(this.p) + texto : texto;
    this.p = 0;
    this.rodar();
};
Analisador.prototype.terminar = function () {
    this.eof = true;
    this.rodar();
    this.despejar();
    return this.resultado();
};
Analisador.prototype.rodar = function () {
    for (let guarda = 0; guarda < 1e9; guarda++) {
        if (this.p >= this.buf.length && this.modo !== 'copy') { if (this.eof && this.modo === 'csv') this.csvFim(); return; }
        const ok = this.modo === 'topo' ? this.topo()
            : this.modo === 'valores' ? this.valores()
            : this.modo === 'copy' ? this.copy()
            : this.modo === 'csv' ? this.csvLinha()
            : this.pular();
        if (!ok) return; // precisa de mais texto
    }
};

/* ---------- tabelas e linhas ---------- */
Analisador.prototype.tabela = function (nome, criando) {
    const k = nome.toLowerCase();
    let t = this.tabelas.get(k);
    if (!t) {
        if (this.tabelas.size >= MAX_TABELAS) { this.avisos.add(`O arquivo tem mais de ${MAX_TABELAS} tabelas; li só as primeiras.`); return null; }
        t = { nome, i: this.lista.length, colunas: [], total: 0, pedacos: [], linhas: [], tam: 0, pkPendentes: [], fkPendentes: [], semCreate: !criando };
        this.tabelas.set(k, t);
        this.lista.push(t);
    } else if (criando && t.semCreate && !t.total) {
        t.semCreate = false; t.colunas = [];
    }
    return t;
};
Analisador.prototype.linha = function (t, vals, mapa) {
    let linha = vals;
    if (mapa) { linha = new Array(t.colunas.length).fill(null); for (let k = 0; k < mapa.length; k++) { const p = mapa[k]; if (p >= 0) linha[p] = vals[k] ?? null; } }
    else if (vals.length > t.colunas.length) {
        for (let k = t.colunas.length; k < vals.length && t.colunas.length < MAX_COLUNAS; k++) t.colunas.push(novaColuna('coluna_' + (k + 1)));
        if (linha.length > t.colunas.length) linha = linha.slice(0, t.colunas.length);
    }
    let tam = 2;
    for (let k = 0; k < linha.length; k++) { const v = linha[k]; tam += v == null ? 5 : typeof v === 'number' ? 12 : v.length + 3; }
    t.linhas.push(linha);
    t.total++;
    t.tam += tam;
    this.tam = (this.tam || 0) + tam;
    if (t.tam >= PEDACO_BYTES) this.despejarTabela(t);
    else if (this.tam >= 3 * PEDACO_BYTES) this.despejar();
};
Analisador.prototype.despejarTabela = function (t) {
    const linhas = t.linhas;
    this.tam -= t.tam;
    t.linhas = []; t.tam = 0;
    if (this.op.aoPedaco) this.op.aoPedaco([[t, linhas]]);
};
Analisador.prototype.despejar = function () {
    const lote = [];
    for (const t of this.lista) if (t.linhas.length) { lote.push([t, t.linhas]); t.linhas = []; t.tam = 0; }
    this.tam = 0;
    if (lote.length && this.op.aoPedaco) this.op.aoPedaco(lote);
};
Analisador.prototype.resultado = function () {
    const tabelas = this.lista;
    const nomes = new Map(tabelas.map(t => [t.nome.toLowerCase(), t]));
    tabelas.forEach(t => {
        t.pkPendentes.forEach(c => { const col = t.colunas.find(x => x.n === c); if (col) col.pk = true; });
        t.fkPendentes.forEach(p => { const col = t.colunas.find(x => x.n === p.c); if (col) col.fk = p.fk; });
        t.colunas.forEach(c => {
            if (!c.fk) return;
            const alvo = nomes.get(String(c.fk.t).toLowerCase());
            if (alvo) { c.fk.t = alvo.nome; if (!c.fk.c) c.fk.c = (alvo.colunas.find(x => x.pk) || alvo.colunas[0] || {}).n || null; }
        });
    });
    if (tabelas.some(t => t.semCreate) && !this.csv) this.avisos.add('Algumas tabelas não tinham a estrutura no arquivo, só os dados: os tipos das colunas ficaram em branco.');
    return {
        tabelas: tabelas.map(t => ({ nome: t.nome, colunas: t.colunas, total: t.total, pedacos: t.pedacos })),
        avisos: [...this.avisos],
    };
};

/* ---------- espaços e comentários entre comandos ---------- */
/** Pula espaços/comentários a partir de i. Devolve a posição, ou -1 se um comentário não terminou neste pedaço. */
Analisador.prototype.espacos = function (i) {
    const s = this.buf, n = s.length;
    for (;;) {
        const c = s.charCodeAt(i);
        if (c === 32 || c === 9 || c === 10 || c === 13 || c === 12 || c === 0xfeff) { i++; continue; }
        if (c === 45 && s.charCodeAt(i + 1) === 45) { const f = s.indexOf('\n', i); if (f < 0) return this.eof ? n : -1; i = f + 1; continue; }
        if (c === 35 && this.delim === ';') { const f = s.indexOf('\n', i); if (f < 0) return this.eof ? n : -1; i = f + 1; continue; }
        if (c === 47 && s.charCodeAt(i + 1) === 42) { const f = s.indexOf('*/', i + 2); if (f < 0) return this.eof ? n : -1; i = f + 2; continue; }
        if (i + 1 >= n && (c === 45 || c === 47) && !this.eof) return -1; // pode ser o começo de um comentário
        return i;
    }
};

/** Fim do comando que começa em this.p (depois do ; ou do DELIMITER). Retoma de onde parou se faltou texto. -1 = falta texto. */
Analisador.prototype.acharFim = function () {
    const s = this.buf, n = s.length, d = this.delim, d0 = d.charCodeAt(0), dl = d.length;
    let v = this.varre || (this.varre = { k: this.p, q: '' });
    let k = v.k, q = v.q;
    while (k < n) {
        const c = s.charCodeAt(k);
        if (q) {
            if (q === "'" || q === '"') {
                if (c === 92) { k += 2; continue; }
                if (c === q.charCodeAt(0)) {
                    if (k + 1 >= n && !this.eof) break;
                    if (s.charCodeAt(k + 1) === c) { k += 2; continue; }
                    q = ''; k++; continue;
                }
                k++; continue;
            }
            if (q === '`') { if (c === 96) q = ''; k++; continue; }
            if (q === '--') { if (c === 10) q = ''; k++; continue; }
            if (q === '/*') { if (c === 42 && s.charCodeAt(k + 1) === 47) { q = ''; k += 2; continue; } k++; continue; }
            // $tag$ do PostgreSQL
            if (c === 36) { if (s.startsWith(q, k)) { k += q.length; q = ''; continue; } if (k + q.length > n && !this.eof) break; }
            k++; continue;
        }
        if (c === d0) {
            if (dl === 1 || s.startsWith(d, k)) { this.varre = null; return k + dl; }
            if (k + dl > n && !this.eof) break; // $ cortado entre dois pedaços
        }
        if ((c === 45 || c === 47) && k + 1 >= n && !this.eof) break; // pode ser -- ou /*
        if (c === 39) { q = "'"; k++; continue; }
        if (c === 34) { q = '"'; k++; continue; }
        if (c === 96) { q = '`'; k++; continue; }
        if (c === 45 && s.charCodeAt(k + 1) === 45) { q = '--'; k += 2; continue; }
        if (c === 35 && d === ';') { q = '--'; k++; continue; }
        if (c === 47 && s.charCodeAt(k + 1) === 42) { q = '/*'; k += 2; continue; }
        if (c === 36 && d === ';') {
            const m = /^\$([A-Za-z_][A-Za-z0-9_]*)?\$/.exec(s.slice(k, k + 70));
            if (m) { q = m[0]; k += m[0].length; continue; }
            if (k + 70 > n && !this.eof) break;
        }
        k++;
    }
    if (this.eof) { this.varre = null; return n; }
    v.k = k; v.q = q;
    return -1;
};

/* ---------- comando de fora ---------- */
Analisador.prototype.topo = function () {
    const s = this.buf, n = s.length;
    const i = this.espacos(this.p);
    if (i < 0) return false;
    this.p = i;
    if (i >= n) return false;
    const c = s.charCodeAt(i);
    if (c === 59) { this.p = i + 1; return true; } // ;
    if (this.delim !== ';' && s.startsWith(this.delim, i)) { this.p = i + this.delim.length; return true; }
    if (this.delim !== ';' && i + this.delim.length > n && !this.eof) return false;
    if (c === 92) { // comandos do psql (barra invertida + connect, restrict...): vale a linha
        const f = s.indexOf('\n', i);
        if (f < 0 && !this.eof) return false;
        this.p = f < 0 ? n : f + 1;
        return true;
    }
    // a primeira palavra do comando
    let j = i;
    while (j < n && /[A-Za-z_]/.test(s[j])) j++;
    if (j >= n && !this.eof) return false;
    const w = s.slice(i, j).toUpperCase();
    if (w === 'DELIMITER') {
        const f = s.indexOf('\n', j);
        if (f < 0 && !this.eof) return false;
        this.delim = s.slice(j, f < 0 ? n : f).trim() || ';';
        this.p = f < 0 ? n : f + 1;
        return true;
    }
    if (w === 'INSERT' || w === 'REPLACE') return this.cabecalhoInsert(j);
    if (w === 'COPY') return this.cabecalhoCopy(j);
    if (w === 'CREATE' || w === 'ALTER') {
        const f = this.acharFim();
        if (f < 0) return false;
        let fimTexto = f - (s.startsWith(this.delim, f - this.delim.length) ? this.delim.length : 0);
        const toks = tokens(s.slice(i, fimTexto));
        this.p = f;
        const tipo = (toks.find((x, k) => k > 0 && x.t === 'id' && !x.q && !['TEMPORARY', 'TEMP', 'UNLOGGED', 'OR', 'REPLACE', 'GLOBAL', 'LOCAL'].includes(x.v.toUpperCase())) || {}).v || '';
        if (tipo.toUpperCase() === 'TABLE') {
            if (w === 'CREATE' && !toks.some(x => K(x, 'AS') || K(x, 'LIKE'))) this.lerCreate(toks);
            else if (w === 'ALTER') this.lerAlter(toks);
        }
        return true;
    }
    this.modo = 'pular';
    return true;
};
Analisador.prototype.pular = function () {
    const f = this.acharFim();
    if (f < 0) return false;
    this.p = f;
    this.modo = 'topo';
    return true;
};

/* ---------- CREATE TABLE / ALTER TABLE ---------- */
Analisador.prototype.lerCreate = function (toks) {
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
    const t = this.tabela(nome, true);
    if (!t) return;
    for (let def of porVirgula(toks.slice(i + 1, j))) {
        if (!def.length) continue;
        if (K(def[0], 'CONSTRAINT')) def = def.slice(2);
        const w = def[0] && !def[0].q ? def[0].v.toUpperCase() : '';
        if (w === 'PRIMARY') { nomesEntre(def, def.findIndex(x => P(x, '('))).nomes.forEach(c => marcar(t, c, 'pk')); continue; }
        if (w === 'FOREIGN') { chaveEstrangeira(t, def); continue; }
        if (w === 'UNIQUE') { const nn = nomesEntre(def, def.findIndex(x => P(x, '('))).nomes; if (nn.length === 1) marcar(t, nn[0], 'uq'); continue; }
        if (['KEY', 'INDEX', 'FULLTEXT', 'SPATIAL', 'CHECK', 'EXCLUDE', 'PERIOD', 'LIKE'].includes(w)) continue;
        if (!def[0] || def[0].t !== 'id' || t.colunas.length >= MAX_COLUNAS) continue;
        const col = novaColuna(def[0].v);
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
            else if (K(def[k], 'REFERENCES')) { const r = nomeEm(def, k + 1), alvo = nomesEntre(def, r.fim).nomes; col.fk = { t: r.nome, c: alvo[0] || null }; k = r.fim; }
        }
        if (/serial/i.test(col.tipo)) col.ai = true;
        t.colunas.push(col);
    }
};
function chaveEstrangeira(t, def) {
    const ini = def.findIndex(x => P(x, '('));
    const de = nomesEntre(def, ini);
    const r = def.findIndex((x, k) => k >= de.fim && K(x, 'REFERENCES'));
    if (r < 0) return;
    const alvo = nomeEm(def, r + 1), para = nomesEntre(def, alvo.fim).nomes;
    de.nomes.forEach((c, k) => {
        const fk = { t: alvo.nome, c: para[k] || para[0] || null };
        const col = t.colunas.find(x => x.n === c);
        if (col) col.fk = fk; else t.fkPendentes.push({ c, fk });
    });
}
function marcar(t, c, campo) {
    const col = t.colunas.find(x => x.n === c);
    if (col) col[campo] = true;
    else if (campo === 'pk') t.pkPendentes.push(c);
}
Analisador.prototype.lerAlter = function (toks) {
    let i = 2;
    if (K(toks[i], 'ONLY')) i++;
    if (K(toks[i], 'IF')) i += 2;
    if (K(toks[i], 'ONLY')) i++;
    const { nome, fim } = nomeEm(toks, i);
    const t = this.tabelas.get(nome.toLowerCase());
    if (!t) return;
    for (let a of porVirgula(toks.slice(fim))) {
        if (!K(a[0], 'ADD')) continue;
        a = a.slice(1);
        if (K(a[0], 'CONSTRAINT')) a = a.slice(2);
        if (K(a[0], 'PRIMARY')) nomesEntre(a, a.findIndex(x => P(x, '('))).nomes.forEach(c => marcar(t, c, 'pk'));
        else if (K(a[0], 'FOREIGN')) chaveEstrangeira(t, a);
        else if (K(a[0], 'UNIQUE')) { const nn = nomesEntre(a, a.findIndex(x => P(x, '('))).nomes; if (nn.length === 1) marcar(t, nn[0], 'uq'); }
    }
};

/* ---------- INSERT ---------- */
const FALTA = { falta: true };
Analisador.prototype.cabecalhoInsert = function (j) {
    // INSERT [LOW_PRIORITY|IGNORE...] [INTO] nome [(colunas)] VALUES  — o cabeçalho é curto; as linhas vêm depois, em fluxo
    try { return this._cabecalhoInsert(j); } catch (e) { if (e === FALTA) return false; throw e; }
};
Analisador.prototype._cabecalhoInsert = function (j) {
    const s = this.buf, n = s.length, eof = this.eof;
    const L = new Leitor(s, j, n);
    // um token que encosta no fim do texto pode estar cortado (VAL|UES): espera o próximo pedaço
    const prox = () => { const t = L.prox(); if (!eof && (L.i >= n || !t)) throw FALTA; return t; };
    let t = prox();
    while (t && t.t === 'id' && !t.q && ['LOW_PRIORITY', 'DELAYED', 'HIGH_PRIORITY', 'IGNORE', 'INTO', 'OR', 'REPLACE', 'ABORT', 'FAIL', 'ROLLBACK'].includes(t.v.toUpperCase())) t = prox();
    if (!t || t.t !== 'id') { this.modo = 'pular'; return true; }
    let nome = t.v, cols = null;
    for (;;) {
        L.pularEspacos();
        if (L.i >= n) { if (!eof) throw FALTA; this.modo = 'pular'; return true; }
        if (s[L.i] !== '.') break;
        L.i++;
        const x = prox();
        if (!x) { this.modo = 'pular'; return true; }
        nome = x.v;
    }
    t = prox();
    if (P(t, '(')) {
        cols = [];
        for (t = prox(); t && !P(t, ')'); t = prox()) if (t.t === 'id') cols.push(t.v);
        if (!t) { this.modo = 'pular'; return true; }
        t = prox();
    }
    if (!(K(t, 'VALUES') || K(t, 'VALUE'))) { this.modo = 'pular'; return true; } // INSERT ... SELECT: não dá para saber as linhas
    const tab = this.tabela(nome, false);
    if (!tab) { this.modo = 'pular'; return true; }
    if (!tab.colunas.length && cols) cols.slice(0, MAX_COLUNAS).forEach(c => tab.colunas.push(novaColuna(c)));
    this.alvo = tab;
    this.mapa = cols ? cols.map(c => tab.colunas.findIndex(x => x.n.toLowerCase() === c.toLowerCase())) : null;
    if (this.mapa && this.mapa.every((p, k) => p === k) && this.mapa.length === tab.colunas.length) this.mapa = null;
    this.p = L.i;
    this.modo = 'valores';
    return true;
};
Analisador.prototype.valores = function () {
    const s = this.buf, n = s.length, t = this.alvo;
    let i = this.p;
    for (;;) {
        let c = s.charCodeAt(i);
        while (c === 32 || c === 10 || c === 13 || c === 9) c = s.charCodeAt(++i);
        if (i >= n) { this.p = i; return false; }
        if (c === 40) { // (
            const r = this.tupla(i + 1);
            if (!r) { this.p = i; return false; }
            if (!t.colunas.length) r.forEach((_, k) => t.colunas.length < MAX_COLUNAS && t.colunas.push(novaColuna('coluna_' + (k + 1))));
            this.linha(t, r, this.mapa);
            i = this.fimTupla;
            continue;
        }
        if (c === 44) { i++; continue; } // ,
        if (c === 59 && this.delim === ';') { this.p = i + 1; this.modo = 'topo'; return true; }
        if (this.delim !== ';' && s.startsWith(this.delim, i)) { this.p = i + this.delim.length; this.modo = 'topo'; return true; }
        if (this.delim !== ';' && i + this.delim.length > n && !this.eof) { this.p = i; return false; }
        if ((c === 45 || c === 47 || c === 35)) { const k = this.espacos(i); if (k < 0) { this.p = i; return false; } if (k > i) { i = k; continue; } }
        // ON DUPLICATE KEY UPDATE / RETURNING ...: o resto do comando é pulado
        this.p = i; this.modo = 'pular';
        return true;
    }
};
const NUMERO = /^-?(0|[1-9]\d{0,14})(\.\d{1,12})?$/;
/** Valor que não está entre aspas: número, NULL ou expressão (fica escrita como está). */
function cru(v) {
    if (v === 'NULL' || v === 'null') return null;
    if (NUMERO.test(v)) { const x = Number(v); if (String(x) === v) return x; }
    return v;
}
/** Uma linha (a, 'b', NULL, ...) a partir de i (logo depois do "("). Devolve os valores e this.fimTupla, ou null se faltou texto. */
Analisador.prototype.tupla = function (i) {
    const s = this.buf, n = s.length, eof = this.eof;
    const vals = [];
    for (;;) {
        let c = s.charCodeAt(i);
        while (c === 32 || c === 10 || c === 13 || c === 9) c = s.charCodeAt(++i);
        if (i >= n) return null;
        let v;
        if (c === 39) { // texto
            let j = i + 1, ini = j, out = '';
            for (;;) {
                if (j >= n) { if (!eof) return null; out += s.slice(ini, j); break; }
                const d = s.charCodeAt(j);
                if (d === 92) { if (j + 1 >= n && !eof) return null; out += s.slice(ini, j); const e = s[j + 1]; out += ESCAPES[e] ?? e ?? ''; j += 2; ini = j; continue; }
                if (d === 39) {
                    if (j + 1 >= n && !eof) return null;
                    if (s.charCodeAt(j + 1) === 39) { out += s.slice(ini, j + 1); j += 2; ini = j; continue; }
                    out += s.slice(ini, j); j++; break;
                }
                j++;
            }
            v = out; i = j;
            c = s.charCodeAt(i);
            while (c === 32 || c === 10 || c === 13 || c === 9) c = s.charCodeAt(++i);
            if (i >= n) return null;
            if (c !== 44 && c !== 41) { // 'x'::text, 'a' COLLATE ...: junta como expressão
                const r = this.expressao(i);
                if (!r) return null;
                v = "'" + v + "'" + r.v; i = r.fim; c = s.charCodeAt(i);
            }
        } else {
            const r = this.expressao(i);
            if (!r) return null;
            const raw = r.v.trim();
            v = cru(raw);
            // _binary 'abc', _utf8mb4'x', N'x', E'x': só o texto
            if (typeof v === 'string' && v.length > 2 && v.charCodeAt(v.length - 1) === 39) {
                const m = /^(?:_\w+\s*|[NnEe])'([\s\S]*)'$/.exec(v);
                if (m && m[1].indexOf("'") < 0) v = m[1].replace(/\\(.)/g, (_, e) => ESCAPES[e] ?? e);
            }
            i = r.fim; c = s.charCodeAt(i);
        }
        vals.push(v);
        if (c === 44) { i++; continue; }
        if (c === 41) { this.fimTupla = i + 1; return vals; }
        if (i >= n) return null;
        i++; // caractere estranho: ignora
    }
};
/** Texto até a vírgula ou o ")" do mesmo nível, respeitando parênteses e aspas. */
Analisador.prototype.expressao = function (i) {
    const s = this.buf, n = s.length;
    let j = i, nivel = 0;
    while (j < n) {
        const c = s.charCodeAt(j);
        if (c === 39) { j++; while (j < n) { const d = s.charCodeAt(j); if (d === 92) { j += 2; continue; } if (d === 39) { if (s.charCodeAt(j + 1) === 39) { j += 2; continue; } break; } j++; } if (j >= n) return null; j++; continue; }
        if (c === 40) nivel++;
        else if (c === 41) { if (nivel === 0) break; nivel--; }
        else if (c === 44 && nivel === 0) break;
        j++;
    }
    if (j >= n) { if (!this.eof) return null; }
    return { v: s.slice(i, j), fim: j };
};

/* ---------- COPY ... FROM stdin (PostgreSQL) ---------- */
Analisador.prototype.cabecalhoCopy = function (j) {
    const s = this.buf;
    const f = this.acharFim();
    if (f < 0) return false;
    const toks = tokens(s.slice(j, f));
    let i = 0;
    if (K(toks[i], 'ONLY')) i++;
    const { nome, fim } = nomeEm(toks, i);
    const cols = P(toks[fim], '(') ? nomesEntre(toks, fim).nomes : null;
    const de = toks.findIndex(x => K(x, 'FROM'));
    const stdin = de >= 0 && K(toks[de + 1], 'STDIN');
    // pula até o fim da linha do comando: os dados começam na linha seguinte
    let k = f;
    while (k < s.length && s.charCodeAt(k) !== 10) k++;
    if (k >= s.length && !this.eof) { this.varre = null; return false; }
    this.p = Math.min(s.length, k + 1);
    if (!stdin || !nome) { if (stdin) this.modo = 'copy', this.alvo = null; return true; }
    const tab = this.tabela(nome, false);
    if (tab && !tab.colunas.length && cols) cols.slice(0, MAX_COLUNAS).forEach(c => tab.colunas.push(novaColuna(c)));
    this.alvo = tab;
    this.mapa = tab && cols ? cols.map(c => tab.colunas.findIndex(x => x.n.toLowerCase() === c.toLowerCase())) : null;
    if (this.mapa && this.mapa.every((p, k2) => p === k2) && this.mapa.length === tab.colunas.length) this.mapa = null;
    this.modo = 'copy';
    return true;
};
const ESC_COPY = { b: '\b', f: '\f', n: '\n', r: '\r', t: '\t', v: '\v', '\\': '\\' };
function campoCopy(v, numerico) {
    if (v === '\\N') return null;
    if (v.indexOf('\\') >= 0) {
        return v.replace(/\\(x[0-9a-fA-F]{1,2}|[0-7]{1,3}|.)/g, (_, e) => {
            if (e[0] === 'x' && e.length > 1) return String.fromCharCode(parseInt(e.slice(1), 16));
            if (/^[0-7]+$/.test(e)) return String.fromCharCode(parseInt(e, 8));
            return ESC_COPY[e] ?? e;
        });
    }
    return numerico ? cru(v) : v;
}
const TIPO_TEXTO = /char|text|uuid|json|xml|date|time|inet|bytea|enum|bit|money/i;
Analisador.prototype.copy = function () {
    const s = this.buf, n = s.length, t = this.alvo;
    const num = t ? t.colunas.map(c => !TIPO_TEXTO.test(c.tipo || '')) : [];
    let i = this.p;
    for (;;) {
        const f = s.indexOf('\n', i);
        if (f < 0 && !this.eof) { this.p = i; return false; }
        const fim = f < 0 ? n : f;
        let linha = s.slice(i, fim);
        if (linha.charCodeAt(linha.length - 1) === 13) linha = linha.slice(0, -1);
        i = f < 0 ? n : f + 1;
        if (linha === '\\.') { this.p = i; this.modo = 'topo'; return true; }
        if (t && (linha.length || f >= 0)) {
            const partes = linha.split('\t');
            if (!t.colunas.length) partes.forEach((_, k) => t.colunas.length < MAX_COLUNAS && t.colunas.push(novaColuna('coluna_' + (k + 1))));
            const vals = new Array(partes.length);
            for (let k = 0; k < partes.length; k++) vals[k] = campoCopy(partes[k], this.mapa ? num[this.mapa[k]] !== false : num[k] !== false);
            this.linha(t, vals, this.mapa);
        }
        if (i >= n) { this.p = i; if (this.eof) this.modo = 'topo'; return false; }
    }
};

/* ---------- CSV ---------- */
Analisador.prototype.csvLinha = function () {
    const s = this.buf, n = s.length, cfg = this.csv;
    if (!cfg.sep) {
        // separador: o que mais aparece na primeira linha, fora das aspas
        const f = s.indexOf('\n');
        if (f < 0 && !this.eof) return false;
        const l = s.slice(0, f < 0 ? n : f).replace(/"[^"]*"/g, '');
        const conta = ch => l.split(ch).length - 1;
        cfg.sep = [',', ';', '\t', '|'].sort((a, b) => conta(b) - conta(a))[0];
    }
    const sep = cfg.sep.charCodeAt(0);
    let i = this.p;
    for (;;) {
        if (i >= n) { this.p = i; return false; }
        const campos = [];
        let j = i, ok = false;
        for (;;) {
            if (j >= n) break;
            if (s.charCodeAt(j) === 34) { // "campo"
                let k = j + 1, out = '', ini = k, fechou = false;
                while (k < n) {
                    if (s.charCodeAt(k) === 34) {
                        if (k + 1 >= n && !this.eof) break;
                        if (s.charCodeAt(k + 1) === 34) { out += s.slice(ini, k + 1); k += 2; ini = k; continue; }
                        out += s.slice(ini, k); k++; fechou = true; break;
                    }
                    k++;
                }
                if (!fechou) { if (!this.eof) break; out += s.slice(ini, k); }
                campos.push({ v: out, q: true });
                j = k;
                // até o separador
                while (j < n && s.charCodeAt(j) !== sep && s.charCodeAt(j) !== 10) j++;
            } else {
                let k = j;
                while (k < n) { const c = s.charCodeAt(k); if (c === sep || c === 10) break; k++; }
                let v = s.slice(j, k);
                if (v.charCodeAt(v.length - 1) === 13) v = v.slice(0, -1);
                campos.push({ v, q: false });
                j = k;
            }
            if (j >= n) { if (this.eof) { ok = true; } break; }
            if (s.charCodeAt(j) === sep) { j++; continue; }
            ok = true; j++; break; // \n
        }
        if (!ok) { this.p = i; return false; }
        i = j;
        this.csvRegistro(campos);
    }
};
Analisador.prototype.csvRegistro = function (campos) {
    const cfg = this.csv;
    if (campos.length === 1 && !campos[0].q && campos[0].v === '') return; // linha vazia
    if (!cfg.cab) {
        const vistos = new Set();
        cfg.cab = campos.map((c, k) => {
            let nome = (c.v || '').trim() || 'coluna_' + (k + 1);
            while (vistos.has(nome.toLowerCase())) nome += '_' + (k + 1);
            vistos.add(nome.toLowerCase());
            return nome;
        });
        const t = this.tabela(cfg.nome, true);
        cfg.cab.slice(0, MAX_COLUNAS).forEach(nm => t.colunas.push(novaColuna(nm)));
        this.alvo = t;
        return;
    }
    this.linha(this.alvo, campos.map(c => (c.q ? c.v : c.v === '' ? null : cru(c.v))), null);
};
Analisador.prototype.csvFim = function () { /* nada pendente: cada registro já foi lido */ };

/* =================== compressão e base64 =================== */
async function gzip(u8) {
    const cs = new CompressionStream('gzip');
    const w = cs.writable.getWriter();
    w.write(u8); w.close();
    return new Uint8Array(await new Response(cs.readable).arrayBuffer());
}
async function gunzip(u8) {
    const ds = new DecompressionStream('gzip');
    const w = ds.writable.getWriter();
    w.write(u8); w.close();
    return new Response(ds.readable).text();
}
function paraB64(u8) {
    if (typeof Buffer !== 'undefined') return Buffer.from(u8).toString('base64');
    if (typeof FileReaderSync !== 'undefined') { const d = new FileReaderSync().readAsDataURL(new Blob([u8])); return d.slice(d.indexOf(',') + 1); }
    let s = ''; for (let i = 0; i < u8.length; i += 0x8000) s += String.fromCharCode.apply(null, u8.subarray(i, i + 0x8000));
    return btoa(s);
}
function deB64(s) {
    if (typeof Buffer !== 'undefined') return new Uint8Array(Buffer.from(s, 'base64'));
    if (Uint8Array.fromBase64) return Uint8Array.fromBase64(s);
    const b = atob(s), u = new Uint8Array(b.length);
    for (let i = 0; i < b.length; i++) u[i] = b.charCodeAt(i);
    return u;
}
/** Lote [[ti, linhas], ...] → pedaços gzip; divide ao meio (tabelas ou linhas) se o base64 passar do limite do servidor. */
async function comprimirLote(lote) {
    const u8 = new TextEncoder().encode(JSON.stringify(lote));
    const gz = await gzip(u8);
    const tis = lote.map(x => x[0]);
    if (Math.ceil(gz.length / 3) * 4 <= MAX_B64) return [{ gz, tis, cru: u8.length }];
    if (lote.length > 1) {
        const meio = Math.ceil(lote.length / 2);
        return [...await comprimirLote(lote.slice(0, meio)), ...await comprimirLote(lote.slice(meio))];
    }
    const [ti, linhas] = lote[0];
    if (linhas.length < 2) return [{ gz, tis, cru: u8.length }]; // uma linha só de mais de 4 MB: o servidor vai recusar
    const meio = Math.ceil(linhas.length / 2);
    return [...await comprimirLote([[ti, linhas.slice(0, meio)]]), ...await comprimirLote([[ti, linhas.slice(meio)]])];
}

/* =================== IndexedDB (cópia local) =================== */
let _db = null;
function bancoLocal() {
    if (_db) return _db;
    _db = new Promise(ok => {
        try {
            const r = indexedDB.open('wf-banco-sql', 1);
            r.onupgradeneeded = () => r.result.createObjectStore('pedacos');
            r.onsuccess = () => ok(r.result);
            r.onerror = () => ok(null);
            r.onblocked = () => ok(null);
        } catch { ok(null); }
    });
    return _db;
}
async function localPegar(k) {
    const db = await bancoLocal();
    if (!db) return null;
    return new Promise(ok => { try { const r = db.transaction('pedacos').objectStore('pedacos').get(k); r.onsuccess = () => ok(r.result || null); r.onerror = () => ok(null); } catch { ok(null); } });
}
async function localPor(k, v) {
    const db = await bancoLocal();
    if (!db) return;
    return new Promise(ok => { try { const tx = db.transaction('pedacos', 'readwrite'); tx.objectStore('pedacos').put(v, k); tx.oncomplete = () => ok(); tx.onerror = () => ok(); tx.onabort = () => ok(); } catch { ok(); } });
}
async function localApagar(prefixo) {
    const db = await bancoLocal();
    if (!db) return;
    return new Promise(ok => {
        try {
            const tx = db.transaction('pedacos', 'readwrite');
            tx.objectStore('pedacos').delete(IDBKeyRange.bound(prefixo, prefixo + '￿'));
            tx.oncomplete = () => ok(); tx.onerror = () => ok(); tx.onabort = () => ok();
        } catch { ok(); }
    });
}

/* =================== servidor =================== */
function cabecalhos(cfg, json) {
    const h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': cfg.csrf || '' };
    if (json) h['Content-Type'] = 'application/json';
    if (cfg.dono) h['X-WF-Dono'] = cfg.dono;
    return h;
}
const espera = ms => new Promise(r => setTimeout(r, ms));
function erroHttp(status) {
    const e = new Error(String(status));
    e.status = status;
    return e;
}
async function enviarPedaco(cfg, chave, b64) {
    for (let tentativa = 0; ; tentativa++) {
        let r;
        try {
            r = await fetch(`${cfg.url}/${chave}`, { method: 'PUT', credentials: 'same-origin', headers: cabecalhos(cfg, true), body: JSON.stringify({ valor: b64 }) });
        } catch (e) {
            if (tentativa < 3) { await espera(1500 * (tentativa + 1)); continue; }
            throw erroHttp(0);
        }
        if (r.ok) return;
        if ((r.status === 429 || r.status >= 500) && tentativa < 3) { await espera(2000 * (tentativa + 1)); continue; }
        throw erroHttp(r.status);
    }
}
async function baixarPedaco(cfg, chave) {
    for (let tentativa = 0; ; tentativa++) {
        let r;
        try {
            r = await fetch(`${cfg.url}/${chave}`, { credentials: 'same-origin', headers: cabecalhos(cfg, false) });
        } catch (e) {
            if (tentativa < 3) { await espera(1500 * (tentativa + 1)); continue; }
            throw erroHttp(0);
        }
        if (r.ok) return (await r.json()).valor;
        if ((r.status === 429 || r.status >= 500) && tentativa < 3) { await espera(2000 * (tentativa + 1)); continue; }
        throw erroHttp(r.status);
    }
}
async function apagarNoServidor(cfg, chave) {
    try { await fetch(`${cfg.url}/${chave}`, { method: 'DELETE', credentials: 'same-origin', headers: cabecalhos(cfg, true) }); } catch { /* sem rede: fica para depois */ }
}

/* =================== sessões (um cartão = uma sessão) =================== */
const sessoes = new Map(); // id do cartão → { versao, schema, gz: Map(ti → [Uint8Array]), abertas: Map(ti → tabela decodificada) }
function sessao(id) {
    let s = sessoes.get(id);
    if (!s) { s = { id, versao: '', schema: [], gz: new Map(), abertas: new Map(), cfg: null }; sessoes.set(id, s); }
    return s;
}
const chavePedaco = (id, versao, n) => `sqlz.${id}.${versao}.${n}`;

/** Lê o arquivo, manda os pedaços e devolve o índice (estrutura + em quais pedaços está cada tabela). */
async function ler(msg, avisar) {
    const { arquivo, id, versao, cfg } = msg;
    const s = sessao(id);
    s.cancelado = false;
    const gzNovo = new Map();
    const csv = /\.(csv|tsv)$/i.test(arquivo.name) ? arquivo.name.replace(/\.(csv|tsv)$/i, '').slice(0, 64) || 'planilha' : null;
    const fila = [];  // pedaços a comprimir/enviar, em ordem
    let lidos = 0, enviados = 0, bytesEnviar = 0, bytesEnviados = 0, crus = 0, contador = 0;
    const t0 = Date.now();
    const A = new Analisador({
        csv,
        aoPedaco(lote) { fila.push(lote); },
    });
    let falha = null;
    // envia em paralelo com a leitura (no máximo 2 pedidos ao mesmo tempo)
    const enviando = new Set();
    const processarFila = async () => {
        while (fila.length) {
            const lote = fila.shift();
            const partes = await comprimirLote(lote.map(([t, linhas]) => [t.i, linhas]));
            for (const parte of partes) {
                const n = contador++;
                parte.tis.forEach(ti => { const p = A.lista[ti].pedacos; if (p[p.length - 1] !== n) p.push(n); });
                gzNovo.set(n, parte.gz);
                crus += parte.cru;
                if (!cfg.local) {
                    const chave = chavePedaco(id, versao, n);
                    const b64 = paraB64(parte.gz);
                    bytesEnviar += b64.length;
                    const pr = enviarPedaco(cfg, chave, b64).then(() => { bytesEnviados += b64.length; enviados++; localPor(`${cfg.url}|${chave}`, parte.gz); })
                        .catch(e => { falha = falha || e; }).finally(() => enviando.delete(pr));
                    enviando.add(pr);
                    while (enviando.size >= 2) await Promise.race(enviando);
                    if (falha) throw falha;
                }
            }
        }
    };
    const progresso = (fase) => avisar({ t: 'progresso', fase, lidos, total: arquivo.size, bytesEnviar, bytesEnviados, ms: Date.now() - t0 });
    let ultimo = 0;
    const reader = arquivo.stream().getReader();
    const dec = new TextDecoder('utf-8');
    let acumulado = '';
    for (;;) {
        if (s.cancelado) { reader.cancel().catch(() => {}); throw new Error('cancelado'); }
        const { value, done } = await reader.read();
        if (done) break;
        lidos += value.length;
        acumulado += dec.decode(value, { stream: true });
        if (acumulado.length < 1 << 20) continue; // junta ~1 MB antes de analisar (menos recortes do buffer)
        A.alimentar(acumulado); acumulado = '';
        if (fila.length) await processarFila();
        if (falha) throw falha;
        if (Date.now() - ultimo > 150) { ultimo = Date.now(); progresso('lendo'); }
    }
    acumulado += dec.decode();
    if (acumulado) A.alimentar(acumulado);
    const r = A.terminar();
    if (fila.length) await processarFila();
    progresso('enviando');
    while (enviando.size) { await Promise.race(enviando); progresso('enviando'); }
    if (falha) throw falha;
    r.tabelas.forEach((t, i) => { t.pedacos = A.lista[i].pedacos; });
    s.cfg = cfg; s.versao = versao; s.gz = gzNovo; s.abertas = new Map(); s.schema = r.tabelas; s.legado = null;
    return { t: 'lido', id, versao, tabelas: r.tabelas, avisos: r.avisos, ms: Date.now() - t0, bytes: arquivo.size, cru: crus, enviado: bytesEnviados };
}

/** Linhas de uma tabela: da memória, do IndexedDB ou do servidor (nessa ordem). */
async function abrirTabela(s, ti, avisar) {
    if (s.abertas.has(ti)) return s.abertas.get(ti);
    const meta = s.schema[ti];
    if (!meta) throw new Error('tabela');
    if (s.legado) {
        const linhas = (s.legado[meta.nome] || []).map(l => l.map(v => (v == null ? null : NUMERO.test(String(v)) && String(Number(v)) === String(v) ? Number(v) : String(v))));
        const tab = { linhas, low: null, cache: new Map() };
        s.abertas.set(ti, tab);
        return tab;
    }
    const linhas = [];
    const ns = Array.isArray(meta.pedacos) ? meta.pedacos : [];
    const n = ns.length;
    // até 3 pedaços baixando ao mesmo tempo; a leitura segue a ordem
    const obter = async num => {
        let gz = s.gz.get(num);
        if (gz) return gz;
        const chave = chavePedaco(s.id, s.versao, num);
        gz = await localPegar(`${s.cfg.url}|${chave}`);
        if (!gz) {
            gz = deB64(await baixarPedaco(s.cfg, chave));
            localPor(`${s.cfg.url}|${chave}`, gz);
        }
        s.gz.set(num, gz);
        return gz;
    };
    const promessas = [];
    for (let k = 0; k < n; k++) {
        while (promessas.length < Math.min(n, k + 3)) { const p = obter(ns[promessas.length]); p.catch(() => {}); promessas.push(p); }
        const gz = await promessas[k];
        const lote = JSON.parse(await gunzip(gz));
        for (const [ti2, parte] of lote) if (ti2 === ti) for (let j = 0; j < parte.length; j++) linhas.push(parte[j]);
        avisar({ t: 'carregando', ti, feito: k + 1, de: n });
    }
    const tab = { linhas, low: null, cache: new Map() };
    // guarda no máximo 3 tabelas abertas (as linhas decodificadas ocupam bem mais que o gzip)
    s.abertas.set(ti, tab);
    if (s.abertas.size > 3) s.abertas.delete(s.abertas.keys().next().value);
    return tab;
}

/* ---------- busca, filtros e ordem ---------- */
function textoLinha(l) {
    let s = '';
    for (let k = 0; k < l.length; k++) { const v = l[k]; if (v != null) s += String(v).toLowerCase() + '\u0001'; }
    return s;
}
/** Filtro de uma coluna: texto (contém), =valor, !texto, >n, <n, >=n, <=n, "vazio". */
function criarFiltro(txt) {
    const f = String(txt || '').trim();
    if (!f) return null;
    if (/^(vazio|null|nulo)$/i.test(f)) return v => v == null || v === '';
    if (/^!(vazio|null|nulo)$/i.test(f)) return v => !(v == null || v === '');
    const m = /^(>=|<=|!=|>|<|=|!)\s*(.*)$/.exec(f);
    if (m) {
        const op = m[1], alvo = m[2], num = alvo !== '' && !isNaN(Number(alvo)) ? Number(alvo) : null, low = alvo.toLowerCase();
        const cmp = v => { if (v == null) return null; if (num !== null && !isNaN(Number(v)) && v !== '') return Number(v) - num; const a = String(v).toLowerCase(); return a < low ? -1 : a > low ? 1 : 0; };
        if (op === '=') return v => v != null && String(v).toLowerCase() === low;
        if (op === '!=') return v => v == null || String(v).toLowerCase() !== low;
        if (op === '!') return v => v == null || !String(v).toLowerCase().includes(low);
        if (op === '>') return v => { const c = cmp(v); return c !== null && c > 0; };
        if (op === '<') return v => { const c = cmp(v); return c !== null && c < 0; };
        if (op === '>=') return v => { const c = cmp(v); return c !== null && c >= 0; };
        if (op === '<=') return v => { const c = cmp(v); return c !== null && c <= 0; };
    }
    const low = f.toLowerCase();
    return v => v != null && String(v).toLowerCase().includes(low);
}
const NUM_TXT = /^-?\d{1,15}(\.\d+)?$/;
function comparar(a, b) {
    if (a === b) return 0;
    if (a == null) return 1;
    if (b == null) return -1;
    const na = typeof a === 'number', nb = typeof b === 'number';
    if (na && nb) return a - b;
    if (na) return -1;
    if (nb) return 1;
    return a < b ? -1 : a > b ? 1 : 0;
}
/** Índice das linhas que passam na busca e nos filtros, na ordem pedida. Guardado por consulta. */
function indice(tab, q) {
    const busca = String(q.busca || '').trim().toLowerCase();
    const filtros = Object.entries(q.filtros || {}).filter(([, v]) => String(v || '').trim());
    const ordem = q.ordem && q.ordem.c != null ? q.ordem : null;
    const chave = JSON.stringify([busca, filtros, ordem]);
    if (tab.cache.has(chave)) return tab.cache.get(chave);
    const L = tab.linhas, n = L.length;
    let idx;
    if (!busca && !filtros.length) {
        idx = new Uint32Array(n);
        for (let i = 0; i < n; i++) idx[i] = i;
    } else {
        // base: o resultado de uma busca mais curta que esta (digitando, a busca só estreita)
        let base = null;
        if (busca && !filtros.length) {
            for (const [k, v] of tab.cache) {
                const [b2, f2] = JSON.parse(k);
                if (!f2.length && b2 && busca.startsWith(b2) && (!base || b2.length > base.b.length)) base = { b: b2, idx: v.semOrdem || v };
            }
        }
        if (busca && !tab.low) { tab.low = new Array(n); for (let i = 0; i < n; i++) tab.low[i] = textoLinha(L[i]); }
        const fs = filtros.map(([c, v]) => [Number(c), criarFiltro(v)]).filter(x => x[1]);
        const out = [];
        const passa = i => {
            if (busca && tab.low[i].indexOf(busca) < 0) return false;
            for (let k = 0; k < fs.length; k++) if (!fs[k][1](L[i][fs[k][0]])) return false;
            return true;
        };
        if (base) { const b = base.idx; for (let k = 0; k < b.length; k++) if (passa(b[k])) out.push(b[k]); }
        else for (let i = 0; i < n; i++) if (passa(i)) out.push(i);
        idx = Uint32Array.from(out);
    }
    if (ordem) {
        const semOrdem = idx;
        const c = Number(ordem.c), dir = ordem.dir === 'desc' ? -1 : 1;
        // chave de ordem por linha: números (inclusive '99.60' entre aspas) como número, o resto como texto
        const ch = new Array(L.length);
        for (let k = 0; k < idx.length; k++) { const i = idx[k], v = L[i][c]; ch[i] = typeof v === 'string' && NUM_TXT.test(v) ? Number(v) : v; }
        const arr = Array.from(idx);
        arr.sort((x, y) => comparar(ch[x], ch[y]) * dir || x - y);
        idx = Uint32Array.from(arr);
        idx.semOrdem = semOrdem;
    }
    tab.cache.set(chave, idx);
    if (tab.cache.size > 24) tab.cache.delete(tab.cache.keys().next().value);
    return idx;
}

/* ---------- estatísticas por coluna ---------- */
function estatisticas(tab, ncol, idx) {
    const L = tab.linhas, n = idx ? idx.length : L.length;
    const out = [];
    for (let c = 0; c < ncol; c++) {
        let nulos = 0, nums = 0, soma = 0, min = null, max = null, minT = Infinity, maxT = 0, vazios = 0;
        const cont = new Map(); let estourou = false;
        for (let k = 0; k < n; k++) {
            const v = L[idx ? idx[k] : k][c];
            if (v == null) { nulos++; continue; }
            if (v === '') vazios++;
            const x = typeof v === 'number' ? v : NUM_TXT.test(v) ? Number(v) : null;
            if (x !== null) { nums++; soma += x; if (min === null || x < min) min = x; if (max === null || x > max) max = x; }
            else { const t = v.length; if (t < minT) minT = t; if (t > maxT) maxT = t; }
            if (!estourou) {
                const kk = typeof v === 'string' && v.length > 120 ? v.slice(0, 120) : v;
                cont.set(kk, (cont.get(kk) || 0) + 1);
                if (cont.size > 200000) estourou = true;
            }
        }
        const top = [...cont.entries()].sort((a, b) => b[1] - a[1]).slice(0, 5).map(([v, q]) => [typeof v === 'string' && v.length > 60 ? v.slice(0, 60) + '…' : v, q]);
        out.push({ total: n, nulos, vazios, distintos: cont.size, distintosMais: estourou, nums, media: nums ? soma / nums : null, min, max, minT: minT === Infinity ? null : minT, maxT, top });
    }
    return out;
}

function csvDe(tab, colunas, idx) {
    const cel = v => (v == null ? '' : /[";\n\r]/.test(String(v)) ? '"' + String(v).replace(/"/g, '""') + '"' : String(v));
    const partes = ['﻿' + colunas.map(cel).join(';') + '\r\n'];
    let bloco = '';
    for (let k = 0; k < idx.length; k++) {
        const l = tab.linhas[idx[k]];
        bloco += l.map(cel).join(';') + '\r\n';
        if (bloco.length > 1 << 20) { partes.push(bloco); bloco = ''; }
    }
    if (bloco) partes.push(bloco);
    return new Blob(partes, { type: 'text/csv;charset=utf-8' });
}

/* =================== mensagens =================== */
async function tratar(msg, avisar) {
    const s = msg.id ? sessao(msg.id) : null;
    switch (msg.acao) {
        case 'ler': return ler(msg, avisar);
        case 'cancelar': if (s) s.cancelado = true; return { t: 'ok' };
        case 'preparar': // índice já salvo: guarda a estrutura e de onde baixar
            if (s.versao !== msg.versao || msg.legado) { s.gz = new Map(); s.abertas = new Map(); }
            s.versao = msg.versao; s.schema = msg.tabelas; s.cfg = msg.cfg; s.legado = msg.legado || null;
            return { t: 'ok' };
        case 'janela': {
            const tab = await abrirTabela(s, msg.ti, avisar);
            const idx = indice(tab, msg);
            const ini = Math.max(0, Math.min(msg.ini || 0, idx.length));
            const fim = Math.min(idx.length, ini + (msg.n || 100));
            const linhas = [];
            for (let k = ini; k < fim; k++) linhas.push(tab.linhas[idx[k]]);
            return { t: 'janela', total: idx.length, todas: tab.linhas.length, ini, linhas };
        }
        case 'estatisticas': {
            const tab = await abrirTabela(s, msg.ti, avisar);
            const idx = indice(tab, msg);
            return { t: 'estatisticas', total: idx.length, cols: estatisticas(tab, msg.ncol, idx.length === tab.linhas.length ? null : idx) };
        }
        case 'csv': {
            const tab = await abrirTabela(s, msg.ti, avisar);
            return { t: 'csv', blob: csvDe(tab, msg.colunas, indice(tab, msg)) };
        }
        case 'apagar': // tira a cópia local (e, se pedido, a do servidor)
            if (msg.servidor && msg.cfg) await apagarNoServidor(msg.cfg, msg.chave);
            if (msg.cfg) await localApagar(`${msg.cfg.url}|${msg.chave}`);
            if (msg.esquecer) sessoes.delete(msg.id);
            return { t: 'ok' };
        case 'comprimir': { const gz = await gzip(new TextEncoder().encode(msg.texto)); return { t: 'b64', v: paraB64(gz) }; }
        case 'descomprimir': return { t: 'texto', v: await gunzip(deB64(msg.b64)) };
        default: throw new Error('ação desconhecida');
    }
}

if (typeof self !== 'undefined' && typeof self.postMessage === 'function' && typeof window === 'undefined') {
    self.onmessage = async e => {
        const msg = e.data || {};
        const avisar = d => self.postMessage({ ...d, pid: msg.pid, parcial: true });
        try {
            const r = await tratar(msg, avisar);
            self.postMessage({ ...r, pid: msg.pid });
        } catch (err) {
            self.postMessage({ t: 'erro', pid: msg.pid, status: err && err.status, erro: String((err && err.message) || err) });
        }
    };
}
if (typeof module !== 'undefined') module.exports = { Analisador, tratar, comprimirLote, gunzip, deB64, paraB64, indice, estatisticas, criarFiltro };
