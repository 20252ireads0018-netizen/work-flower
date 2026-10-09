/* Work Flower · Diverso, Corpo, Mente e Carteira — tabelas estilo planilha.
   Estende diversoAbasDiagrama() / diversoAbas() / corpoAbas() / menteAbas() / carteiraAbas() com métodos tb*.
   Carregue DEPOIS de diverso.js e diagrama.js (Diverso), corpo.js (Corpo), mente.js (Mente) ou carteira.js (Carteira). Não altera o formato antigo (dados.cab / dados.linhas);
   guarda extras opcionais em dados.cols, dados.rodape e dados.estl. */
(function () {
    'use strict';

    const MAX_L = 1000, MAX_C = 40, HIST = 40, LARG = 140;
    const COR_OK = /^#[0-9a-fA-F]{6}$/;
    const FORMATOS = [
        { id: 'auto', nome: 'Geral' }, { id: 'texto', nome: 'Texto' }, { id: 'numero', nome: 'Número (2 casas)' },
        { id: 'inteiro', nome: 'Inteiro' }, { id: 'moeda', nome: 'Moeda (R$)' }, { id: 'percent', nome: 'Porcentagem' }, { id: 'data', nome: 'Data' },
    ];
    const FMT_IDS = FORMATOS.map(f => f.id);
    const AGREGADOS = [
        { id: '', nome: 'Rodapé: nenhum' }, { id: 'soma', nome: 'Rodapé: soma' }, { id: 'media', nome: 'Rodapé: média' },
        { id: 'min', nome: 'Rodapé: mínimo' }, { id: 'max', nome: 'Rodapé: máximo' }, { id: 'contar', nome: 'Rodapé: contagem' },
    ];
    const AGG_IDS = AGREGADOS.map(a => a.id);
    const DEF_COL = { fmt: 'auto', al: '', w: 0 };
    const txt = v => (v == null ? '' : String(v));
    const pad = n => String(n).padStart(2, '0');
    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);

    /* ---------- letras de coluna ---------- */
    const letra = j => { let s = '', n = j + 1; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
    const colIdx = s => s.split('').reduce((a, ch) => a * 26 + ch.charCodeAt(0) - 64, 0) - 1;
    const txtRef = r => (r.ac ? '$' : '') + letra(r.j) + (r.ar ? '$' : '') + (r.i + 1);

    /* ---------- números, datas e formatos ---------- */
    class Erro { constructor(c) { this.cod = c; } }
    class Intervalo { constructor(v) { this.v = v; } }

    /** Aceita "1.234,56", "R$ 10", "12%", "-3.5". Devolve número ou null. */
    function paraNum(v) {
        if (typeof v === 'number') return isFinite(v) ? v : null;
        let s = txt(v).trim();
        if (!s) return null;
        let pct = false;
        if (/%$/.test(s)) { pct = true; s = s.slice(0, -1); }
        s = s.replace(/R\$/gi, '').replace(/\s/g, '');
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        else if ((s.match(/\./g) || []).length > 1) s = s.replace(/\./g, '');
        if (!/^[-+]?(\d+\.?\d*|\.\d+)$/.test(s)) return null;
        const n = parseFloat(s);
        return pct ? n / 100 : n;
    }
    const EPOCA = Date.UTC(1899, 11, 30);
    function paraData(s) {
        s = txt(s).trim();
        let m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(s), y, mo, d;
        if (m) { y = +m[1]; mo = +m[2]; d = +m[3]; }
        else {
            m = /^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/.exec(s);
            if (!m) return null;
            d = +m[1]; mo = +m[2]; y = +m[3]; if (y < 100) y += 2000;
        }
        const t = Date.UTC(y, mo - 1, d), dt = new Date(t);
        if (dt.getUTCMonth() !== mo - 1 || dt.getUTCDate() !== d) return null;
        return Math.round((t - EPOCA) / 864e5);
    }
    function deSerie(n) {
        const d = new Date(EPOCA + Math.round(n) * 864e5);
        return pad(d.getUTCDate()) + '/' + pad(d.getUTCMonth() + 1) + '/' + d.getUTCFullYear();
    }
    function fmtNum(n, f) {
        switch (f) {
            case 'numero': return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            case 'inteiro': return Math.round(n).toLocaleString('pt-BR');
            case 'moeda': return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            case 'percent': return (n * 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '%';
            case 'data': return deSerie(n);
            default: return n.toLocaleString('pt-BR', { maximumFractionDigits: 10 });
        }
    }
    function mostrar(v, f) {
        if (v instanceof Erro) return v.cod;
        if (v instanceof Intervalo) return '#VALOR!';
        if (typeof v === 'boolean') return v ? 'VERDADEIRO' : 'FALSO';
        if (typeof v === 'number') return isFinite(v) ? fmtNum(v, f) : '#NUM!';
        return txt(v);
    }
    function lum(hex) {
        const r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    }

    /* ---------- acesso aos dados ---------- */
    const colDe = (b, j) => (b.dados.cols && b.dados.cols[j]) || DEF_COL;
    const estDe = (b, i, j) => (b.dados.estl && b.dados.estl[i] && b.dados.estl[i][j]) || null;

    /** Valor calculado da célula: número, texto, booleano ou Erro. `pilha` detecta referência circular. */
    function valorCel(b, i, j, pilha) {
        const d = b.dados;
        if (i < 0 || j < 0 || i >= d.linhas.length || j >= d.cab.length) return '';
        const s = txt(d.linhas[i] && d.linhas[i][j]);
        if (s.charAt(0) === '=') {
            const k = i + ',' + j;
            if (pilha.has(k)) return new Erro('#CICLO!');
            pilha.add(k);
            try { return avaliar(s.slice(1), b, pilha); } finally { pilha.delete(k); }
        }
        if (s === '') return '';
        const f = colDe(b, j).fmt;
        if (f === 'texto') return s;
        if (f === 'data') { const n = paraData(s); if (n !== null) return n; }
        const n = paraNum(s);
        if (n !== null) return (f === 'percent' && !/%\s*$/.test(s)) ? n / 100 : n;
        return s;
    }

    /* ---------- fórmulas: tokenizador ---------- */
    const RE_REF = /^(\$?)([A-Za-z]{1,3})(\$?)(\d+)(?![A-Za-z0-9_.(])/;
    const RE_RANGE = /^(\$?)([A-Za-z]{1,3})(\$?)(\d+):(\$?)([A-Za-z]{1,3})(\$?)(\d+)(?![A-Za-z0-9_.(])/;
    const pRef = (m, o) => ({ ac: m[o] === '$', j: colIdx(m[o + 1].toUpperCase()), ar: m[o + 2] === '$', i: parseInt(m[o + 3], 10) - 1 });
    const semAcento = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

    function tokenizar(s) {
        const t = []; let i = 0;
        while (i < s.length) {
            const ch = s[i];
            if (/\s/.test(ch)) { i++; continue; }
            if (ch === '"') {
                let j = i + 1, out = '';
                while (j < s.length) {
                    if (s[j] === '"') { if (s[j + 1] === '"') { out += '"'; j += 2; continue; } break; }
                    out += s[j++];
                }
                if (j >= s.length) throw new Erro('#NOME?');
                t.push({ k: 's', v: out }); i = j + 1; continue;
            }
            if (s.startsWith('#REF!', i)) { t.push({ k: 'e' }); i += 5; continue; }
            const r = s.slice(i); let m;
            if ((m = /^(\d+([.,]\d+)?|[.,]\d+)/.exec(r))) { t.push({ k: 'n', v: parseFloat(m[1].replace(',', '.')) }); i += m[0].length; continue; }
            if ((m = RE_REF.exec(r))) { t.push({ k: 'r', ...pRef(m, 1) }); i += m[0].length; continue; }
            if ((m = /^[A-Za-zÀ-ÿ_][A-Za-zÀ-ÿ0-9_.]*/.exec(r))) {
                i += m[0].length;
                let k = i; while (s[k] === ' ') k++;
                t.push({ k: s[k] === '(' ? 'f' : 'id', v: semAcento(m[0]) });
                continue;
            }
            const dois = s.substr(i, 2);
            if (dois === '<>' || dois === '<=' || dois === '>=') { t.push({ k: 'o', v: dois }); i += 2; continue; }
            if ('+-*/^&=<>(),;:%'.includes(ch)) { t.push({ k: 'o', v: ch === ',' ? ';' : ch }); i++; continue; }
            throw new Erro('#NOME?');
        }
        return t;
    }

    /* ---------- fórmulas: analisador (precedência como no Excel) ---------- */
    function analisar(src) {
        const T = tokenizar(src); let p = 0;
        const op = v => T[p] && T[p].k === 'o' && T[p].v === v;
        const ops = l => T[p] && T[p].k === 'o' && l.includes(T[p].v);
        const bin = (sub, lista) => () => { let a = sub(); while (ops(lista)) { const o = T[p++].v; a = { t: 'b', o, a, b: sub() }; } return a; };
        function prim() {
            const k = T[p++];
            if (!k) throw new Erro('#VALOR!');
            if (k.k === 'n' || k.k === 's') return { t: 'v', v: k.v };
            if (k.k === 'e') throw new Erro('#REF!');
            if (k.k === 'r') {
                if (op(':')) {
                    p++; const k2 = T[p++];
                    if (!k2 || k2.k !== 'r') throw new Erro('#VALOR!');
                    return { t: 'g', i1: Math.min(k.i, k2.i), i2: Math.max(k.i, k2.i), j1: Math.min(k.j, k2.j), j2: Math.max(k.j, k2.j) };
                }
                return { t: 'r', i: k.i, j: k.j };
            }
            if (k.k === 'id') {
                if (k.v === 'VERDADEIRO' || k.v === 'TRUE') return { t: 'v', v: true };
                if (k.v === 'FALSO' || k.v === 'FALSE') return { t: 'v', v: false };
                throw new Erro('#NOME?');
            }
            if (k.k === 'f') {
                if (!op('(')) throw new Erro('#NOME?');
                p++;
                const args = [];
                if (!op(')')) {
                    for (;;) {
                        args.push(op(';') || op(')') ? { t: 'v', v: '' } : comp());
                        if (op(';')) { p++; continue; }
                        break;
                    }
                }
                if (!op(')')) throw new Erro('#VALOR!');
                p++;
                return { t: 'f', n: k.v, a: args };
            }
            if (k.k === 'o' && k.v === '(') {
                const e = comp();
                if (!op(')')) throw new Erro('#VALOR!');
                p++; return e;
            }
            throw new Erro('#VALOR!');
        }
        function pct() { let a = prim(); while (op('%')) { p++; a = { t: 'p', a }; } return a; }
        function unario() {
            if (op('-')) { p++; return { t: 'u', a: unario() }; }
            if (op('+')) { p++; return unario(); }
            return pct();
        }
        const pot = bin(unario, ['^']), mult = bin(pot, ['*', '/']), soma = bin(mult, ['+', '-']),
            conc = bin(soma, ['&']), comp = bin(conc, ['=', '<>', '<', '>', '<=', '>=']);
        const a = comp();
        if (p < T.length) throw new Erro('#VALOR!');
        return a;
    }
    const CACHE = new Map();
    function arvore(src) {
        let a = CACHE.get(src);
        if (!a) { a = analisar(src); if (CACHE.size > 800) CACHE.clear(); CACHE.set(src, a); }
        return a;
    }

    /* ---------- fórmulas: avaliação ---------- */
    function aNum(v) {
        if (v instanceof Intervalo) throw new Erro('#VALOR!');
        if (typeof v === 'number') return v;
        if (v === '' || v == null) return 0;
        if (typeof v === 'boolean') return v ? 1 : 0;
        const n = paraNum(v);
        if (n === null) throw new Erro('#VALOR!');
        return n;
    }
    function aTexto(v) {
        if (v instanceof Intervalo) throw new Erro('#VALOR!');
        if (typeof v === 'boolean') return v ? 'VERDADEIRO' : 'FALSO';
        if (typeof v === 'number') return String(v).replace('.', ',');
        return txt(v);
    }
    function aBool(v) {
        if (v instanceof Intervalo) throw new Erro('#VALOR!');
        if (typeof v === 'boolean') return v;
        if (typeof v === 'number') return v !== 0;
        if (v === '') return false;
        const s = String(v).toUpperCase();
        if (s === 'VERDADEIRO' || s === 'TRUE') return true;
        if (s === 'FALSO' || s === 'FALSE') return false;
        throw new Erro('#VALOR!');
    }
    function cmp(a, b) {
        const sa = typeof a === 'string' && a !== '', sb = typeof b === 'string' && b !== '';
        if (sa || sb) {
            if ((sa && sb) || (sa && b === '') || (sb && a === '')) return String(a).toLowerCase().localeCompare(String(b).toLowerCase(), 'pt-BR');
            return sa ? 1 : -1; // texto vale mais que número
        }
        const x = aNum(a), y = aNum(b);
        return x < y ? -1 : x > y ? 1 : 0;
    }
    function criterio(c) {
        if (typeof c === 'number') return x => typeof x === 'number' && x === c;
        const m = /^(<=|>=|<>|<|>|=)?([\s\S]*)$/.exec(txt(c)), op = m[1] || '=', alvo = m[2], nAlvo = paraNum(alvo);
        return x => {
            const xn = typeof x === 'number' ? x : (x !== '' ? paraNum(x) : null);
            let r;
            if (nAlvo !== null && xn !== null) r = xn < nAlvo ? -1 : xn > nAlvo ? 1 : 0;
            else {
                const a = txt(x).toLowerCase(), b2 = alvo.toLowerCase();
                r = (op === '=' || op === '<>') ? (a === b2 ? 0 : 1) : a.localeCompare(b2, 'pt-BR');
            }
            switch (op) {
                case '=': return r === 0; case '<>': return r !== 0; case '<': return r < 0;
                case '>': return r > 0; case '<=': return r <= 0; default: return r >= 0;
            }
        };
    }
    function coletar(A, ctx) {
        const out = [];
        A.forEach(a => {
            const v = ev(a, ctx);
            if (v instanceof Intervalo) v.v.forEach(x => { if (typeof x === 'number') out.push(x); });
            else out.push(aNum(v));
        });
        return out;
    }
    const ALIAS = {
        SUM: 'SOMA', AVERAGE: 'MEDIA', COUNT: 'CONT.NUM', COUNTA: 'CONT.VALORES', IF: 'SE', ROUND: 'ARRED', AND: 'E', OR: 'OU',
        NOT: 'NAO', CONCAT: 'CONCATENAR', UPPER: 'MAIUSCULA', LOWER: 'MINUSCULA', SUMIF: 'SOMASE', COUNTIF: 'CONT.SE',
        IFERROR: 'SEERRO', TODAY: 'HOJE', SQRT: 'RAIZ', POWER: 'POTENCIA', MOD: 'RESTO', MEDIAN: 'MED', LEN: 'NUM.CARACT',
        MAXIMO: 'MAX', MINIMO: 'MIN', ABS: 'ABS',
    };
    function chamar(n, ctx) {
        const nome = ALIAS[n.n] || n.n, A = n.a;
        const v = i => { if (i >= A.length) throw new Erro('#VALOR!'); return ev(A[i], ctx); };
        const num = i => aNum(v(i));
        switch (nome) {
            case 'SOMA': return coletar(A, ctx).reduce((x, y) => x + y, 0);
            case 'MEDIA': { const l = coletar(A, ctx); if (!l.length) throw new Erro('#DIV/0!'); return l.reduce((x, y) => x + y, 0) / l.length; }
            case 'MIN': { const l = coletar(A, ctx); return l.length ? Math.min(...l) : 0; }
            case 'MAX': { const l = coletar(A, ctx); return l.length ? Math.max(...l) : 0; }
            case 'MED': {
                const l = coletar(A, ctx).sort((x, y) => x - y);
                if (!l.length) throw new Erro('#NUM!');
                const m = l.length >> 1; return l.length % 2 ? l[m] : (l[m - 1] + l[m]) / 2;
            }
            case 'CONT.NUM': { let c = 0; A.forEach(a => { const x = ev(a, ctx); if (x instanceof Intervalo) c += x.v.filter(y => typeof y === 'number').length; else if (typeof x === 'number' || paraNum(x) !== null) c++; }); return c; }
            case 'CONT.VALORES': { let c = 0; A.forEach(a => { const x = ev(a, ctx); if (x instanceof Intervalo) c += x.v.filter(y => y !== '').length; else if (x !== '') c++; }); return c; }
            case 'SE': return aBool(v(0)) ? v(1) : (A.length > 2 ? v(2) : false);
            case 'SEERRO': try { return v(0); } catch (e) { if (e instanceof Erro) return v(1); throw e; }
            case 'ARRED': {
                const x = num(0), c = A.length > 1 ? Math.trunc(num(1)) : 0;
                const r = Math.round(Number(Math.abs(x) + 'e' + c));
                return Math.sign(x) * Number(r + 'e' + (-c));
            }
            case 'ABS': return Math.abs(num(0));
            case 'INT': return Math.floor(num(0));
            case 'RAIZ': { const x = num(0); if (x < 0) throw new Erro('#NUM!'); return Math.sqrt(x); }
            case 'POTENCIA': return Math.pow(num(0), num(1));
            case 'RESTO': { const d = num(1); if (d === 0) throw new Erro('#DIV/0!'); const x = num(0); return x - d * Math.floor(x / d); }
            case 'E': { if (!A.length) throw new Erro('#VALOR!'); return A.every((_, i) => aBool(v(i))); }
            case 'OU': { if (!A.length) throw new Erro('#VALOR!'); return A.some((_, i) => aBool(v(i))); }
            case 'NAO': return !aBool(v(0));
            case 'CONCATENAR': return A.map((_, i) => { const x = v(i); return x instanceof Intervalo ? x.v.map(aTexto).join('') : aTexto(x); }).join('');
            case 'MAIUSCULA': return aTexto(v(0)).toUpperCase();
            case 'MINUSCULA': return aTexto(v(0)).toLowerCase();
            case 'NUM.CARACT': return aTexto(v(0)).length;
            case 'HOJE': { const t = new Date(); return Math.round((Date.UTC(t.getFullYear(), t.getMonth(), t.getDate()) - EPOCA) / 864e5); }
            case 'SOMASE': case 'CONT.SE': {
                const r = v(0), c = v(1);
                if (!(r instanceof Intervalo)) throw new Erro('#VALOR!');
                const ok = criterio(c);
                if (nome === 'CONT.SE') return r.v.filter(ok).length;
                const s = A.length > 2 ? v(2) : r;
                if (!(s instanceof Intervalo)) throw new Erro('#VALOR!');
                let t = 0;
                r.v.forEach((x, k) => { if (ok(x) && typeof s.v[k] === 'number') t += s.v[k]; });
                return t;
            }
            default: throw new Erro('#NOME?');
        }
    }
    function ev(n, ctx) {
        switch (n.t) {
            case 'v': return n.v;
            case 'r': { const x = valorCel(ctx.b, n.i, n.j, ctx.pilha); if (x instanceof Erro) throw x; return x; }
            case 'g': {
                const d = ctx.b.dados, i2 = Math.min(n.i2, d.linhas.length - 1), j2 = Math.min(n.j2, d.cab.length - 1), vals = [];
                for (let i = n.i1; i <= i2; i++) for (let j = n.j1; j <= j2; j++) {
                    const x = valorCel(ctx.b, i, j, ctx.pilha);
                    if (x instanceof Erro) throw x;
                    vals.push(x);
                }
                return new Intervalo(vals);
            }
            case 'u': return -aNum(ev(n.a, ctx));
            case 'p': return aNum(ev(n.a, ctx)) / 100;
            case 'b': {
                const a = ev(n.a, ctx), c = ev(n.b, ctx);
                switch (n.o) {
                    case '+': return aNum(a) + aNum(c);
                    case '-': return aNum(a) - aNum(c);
                    case '*': return aNum(a) * aNum(c);
                    case '/': { const d = aNum(c); if (d === 0) throw new Erro('#DIV/0!'); return aNum(a) / d; }
                    case '^': return Math.pow(aNum(a), aNum(c));
                    case '&': return aTexto(a) + aTexto(c);
                    case '=': return cmp(a, c) === 0;
                    case '<>': return cmp(a, c) !== 0;
                    case '<': return cmp(a, c) < 0;
                    case '>': return cmp(a, c) > 0;
                    case '<=': return cmp(a, c) <= 0;
                    default: return cmp(a, c) >= 0;
                }
            }
            default: return chamar(n, ctx);
        }
    }
    function avaliar(src, b, pilha) {
        try {
            const v = ev(arvore(src), { b, pilha });
            if (v instanceof Intervalo) return new Erro('#VALOR!');
            if (typeof v === 'number' && !isFinite(v)) return new Erro('#NUM!');
            return v;
        } catch (e) {
            if (e instanceof Erro) return e;
            if (e instanceof RangeError) return new Erro('#VALOR!');
            throw e;
        }
    }

    /* ---------- reescrita de referências (copiar, ordenar, inserir, excluir) ---------- */
    function mapearRefs(f, onRef, onRange) {
        let out = '', k = 0;
        while (k < f.length) {
            const ch = f[k];
            if (ch === '"') {
                let j = k + 1;
                while (j < f.length) { if (f[j] === '"') { if (f[j + 1] === '"') { j += 2; continue; } break; } j++; }
                out += f.slice(k, j + 1); k = j + 1; continue;
            }
            if (!/[A-Za-z0-9_.$]/.test(out.slice(-1))) {
                const r = f.slice(k); let m;
                if (onRange && (m = RE_RANGE.exec(r))) { out += onRange(pRef(m, 1), pRef(m, 5)); k += m[0].length; continue; }
                if ((m = RE_REF.exec(r))) { out += onRef(pRef(m, 1)); k += m[0].length; continue; }
            }
            out += ch; k++;
        }
        return out;
    }
    /** Move referências relativas (di linhas, dj colunas). `filtro` limita quais referências se movem. */
    function transladar(f, di, dj, filtro) {
        return mapearRefs(f, r => {
            if (filtro && !filtro(r)) return txtRef(r);
            const i = r.ar ? r.i : r.i + di, j = r.ac ? r.j : r.j + dj;
            return (i < 0 || j < 0) ? '#REF!' : txtRef({ ...r, i, j });
        });
    }
    /** Depois de inserir (delta>0) ou excluir (delta<0) a linha/coluna idx, corrige todas as fórmulas. */
    function ajustarRefs(b, eixo, idx, delta) {
        const d = b.dados, key = eixo === 'l' ? 'i' : 'j';
        const onRef = r => {
            let v = r[key];
            if (delta > 0) v = v >= idx ? v + delta : v;
            else if (v === idx) return '#REF!';
            else if (v > idx) v--;
            return txtRef({ ...r, [key]: v });
        };
        const onRange = (a, c) => {
            const lo = a[key] <= c[key] ? a : c, hi = lo === a ? c : a;
            let l = lo[key], h = hi[key];
            if (delta > 0) { if (l >= idx) l += delta; if (h >= idx) h += delta; }
            else { if (l === idx && h === idx) return '#REF!'; if (l > idx) l--; if (h >= idx) h--; }
            return txtRef({ ...lo, [key]: l }) + ':' + txtRef({ ...hi, [key]: h });
        };
        d.linhas.forEach(l => l.forEach((c, j) => {
            if (typeof c === 'string' && c.charAt(0) === '=') { const n = mapearRefs(c, onRef, onRange); if (n !== c) l[j] = n; }
        }));
    }

    /* ---------- estrutura dos dados ---------- */
    /** Deixa dados.cols/rodape/estl coerentes com cab/linhas. Devolve true se mudou algo. */
    function normalizar(d) {
        let m = false;
        if (!Array.isArray(d.cab) || !d.cab.length) { d.cab = ['Item', 'Valor']; m = true; }
        if (!Array.isArray(d.linhas)) { d.linhas = []; m = true; }
        const nc = d.cab.length;
        d.cab.forEach((h, j) => { if (typeof h !== 'string') { d.cab[j] = txt(h); m = true; } });
        d.linhas.forEach((l, i) => {
            if (!Array.isArray(l)) { d.linhas[i] = Array(nc).fill(''); m = true; return; }
            for (let j = 0; j < nc; j++) if (typeof l[j] !== 'string') { l[j] = txt(l[j]); m = true; }
        });
        if (!Array.isArray(d.cols)) { d.cols = []; m = true; }
        for (let j = 0; j < nc; j++) {
            const c = d.cols[j];
            if (!c || typeof c !== 'object') { d.cols[j] = { ...DEF_COL }; m = true; continue; }
            if (!FMT_IDS.includes(c.fmt)) { c.fmt = 'auto'; m = true; }
            if (!['', 'e', 'c', 'd'].includes(c.al)) { c.al = ''; m = true; }
            if (typeof c.w !== 'number') { c.w = 0; m = true; }
        }
        if (d.cols.length > nc) { d.cols.length = nc; m = true; }
        if (!Array.isArray(d.rodape)) { d.rodape = []; m = true; }
        for (let j = 0; j < nc; j++) if (!AGG_IDS.includes(d.rodape[j])) { d.rodape[j] = ''; m = true; }
        if (d.rodape.length > nc) { d.rodape.length = nc; m = true; }
        if (!Array.isArray(d.estl)) { d.estl = []; m = true; }
        for (let i = 0; i < d.linhas.length; i++) if (!d.estl[i] || typeof d.estl[i] !== 'object') { d.estl[i] = {}; m = true; }
        if (d.estl.length > d.linhas.length) { d.estl.length = d.linhas.length; m = true; }
        return m;
    }
    const snap = d => JSON.stringify({ cab: d.cab, linhas: d.linhas, estl: d.estl || [], cols: d.cols || [], rodape: d.rodape || [] });
    function setEst(d, i, j, patch) {
        const o = { ...((d.estl[i] && d.estl[i][j]) || {}), ...patch };
        Object.keys(o).forEach(k => { if (!o[k]) delete o[k]; });
        if (Object.keys(o).length) d.estl[i][j] = o; else delete d.estl[i][j];
    }
    function deslocarChaves(obj, idx, delta) {
        const novo = {};
        Object.keys(obj || {}).forEach(k => {
            const j = +k;
            if (delta > 0) novo[j >= idx ? j + delta : j] = obj[k];
            else if (j !== idx) novo[j > idx ? j - 1 : j] = obj[k];
        });
        return novo;
    }

    /* ---------- CSV ---------- */
    function lerCsv(texto) {
        const t = texto.replace(/^\uFEFF/, '');
        const prim = t.split(/\r?\n/)[0] || '';
        const cont = ch => prim.split(ch).length - 1;
        let delim = ';', melhor = cont(';');
        if (cont('\t') > melhor) { delim = '\t'; melhor = cont('\t'); }
        if (cont(',') > melhor) delim = ',';
        const linhas = []; let l = [], c = '', q = false;
        for (let k = 0; k < t.length; k++) {
            const ch = t[k];
            if (q) { if (ch === '"') { if (t[k + 1] === '"') { c += '"'; k++; } else q = false; } else c += ch; }
            else if (ch === '"' && c === '') q = true;
            else if (ch === delim) { l.push(c); c = ''; }
            else if (ch === '\n' || ch === '\r') { if (ch === '\r' && t[k + 1] === '\n') k++; l.push(c); linhas.push(l); l = []; c = ''; }
            else c += ch;
        }
        if (c !== '' || l.length) { l.push(c); linhas.push(l); }
        while (linhas.length && linhas[linhas.length - 1].every(x => x === '')) linhas.pop();
        return linhas;
    }

    let clipTb = null; // última cópia feita dentro da tabela (preserva fórmulas ao colar)

    /* ---------- métodos que entram no x-data ---------- */
    function metodosTabela() {
        return {
            tbFormatos: FORMATOS,
            tbAgregados: AGREGADOS,
            tbLetra: letra,
            tbCol(b, j) { return colDe(b, j); },

            tbUI(b) {
                // `ui` guarda só o estado de tela (seleção, histórico); algumas páginas (ex.: Mente) podem não ter esse objeto
                if (!this.ui) this.ui = {};
                const k = 'tb.' + b.id;
                if (!this.ui[k]) this.ui[k] = { r: 0, c: 0, r2: 0, c2: 0, edit: null, filtro: '', hist: [], refaz: [], sujo: false };
                return this.ui[k];
            },
            tbGarantir(b) { normalizar(b.dados); },
            tbGravar(b) {
                const u = this.tbUI(b);
                u.hist.push(snap(b.dados));
                if (u.hist.length > HIST) u.hist.shift();
                u.refaz = [];
            },
            tbDesfazer(b) {
                const u = this.tbUI(b);
                if (!u.hist.length) return;
                u.refaz.push(snap(b.dados));
                Object.assign(b.dados, JSON.parse(u.hist.pop()));
            },
            tbRefazer(b) {
                const u = this.tbUI(b);
                if (!u.refaz.length) return;
                u.hist.push(snap(b.dados));
                Object.assign(b.dados, JSON.parse(u.refaz.pop()));
            },

            /* ----- seleção ----- */
            tbIntervalo(b) {
                const u = this.tbUI(b), n = b.dados.linhas.length, m = b.dados.cab.length;
                const cl = (v, mx) => clamp(v, 0, Math.max(0, mx - 1));
                const r = cl(u.r, n), r2 = cl(u.r2, n), c = cl(u.c, m), c2 = cl(u.c2, m);
                return { i1: Math.min(r, r2), i2: Math.max(r, r2), j1: Math.min(c, c2), j2: Math.max(c, c2) };
            },
            tbUnico(b) { const g = this.tbIntervalo(b); return g.i1 === g.i2 && g.j1 === g.j2; },
            tbSelecionada(b, i, j) { const g = this.tbIntervalo(b); return i >= g.i1 && i <= g.i2 && j >= g.j1 && j <= g.j2; },
            tbAtiva(b, i, j) { const u = this.tbUI(b); return u.r === i && u.c === j; },
            tbLinhaAtiva(b, i) { const g = this.tbIntervalo(b); return i >= g.i1 && i <= g.i2; },
            tbColAtiva(b, j) { const g = this.tbIntervalo(b); return j >= g.j1 && j <= g.j2; },
            tbRef(b) {
                const g = this.tbIntervalo(b), a = letra(g.j1) + (g.i1 + 1);
                return this.tbUnico(b) ? a : a + ':' + letra(g.j2) + (g.i2 + 1);
            },
            tbRaw(b) { const u = this.tbUI(b), l = b.dados.linhas[u.r]; return l ? txt(l[u.c]) : ''; },
            tbFoco(b, i, j) { const u = this.tbUI(b); u.r = u.r2 = i; u.c = u.c2 = j; u.edit = i + ',' + j; u.sujo = false; },
            tbBlur(b, i, j) { const u = this.tbUI(b); if (u.edit === i + ',' + j) u.edit = null; },
            tbDown(e, b, i, j) {
                if (e.button > 0 || !e.shiftKey) return;
                e.preventDefault(); // Shift+clique estende o intervalo sem mudar a célula ativa
                const u = this.tbUI(b); u.r2 = i; u.c2 = j;
            },
            tbSelCol(b, j, e) {
                const u = this.tbUI(b);
                if (e && e.shiftKey) u.c2 = j; else { u.c = j; u.c2 = j; }
                u.r = 0; u.r2 = Math.max(0, b.dados.linhas.length - 1);
            },
            tbSelLinha(b, i, e) {
                const u = this.tbUI(b);
                if (e && e.shiftKey) u.r2 = i; else { u.r = i; u.r2 = i; }
                u.c = 0; u.c2 = Math.max(0, b.dados.cab.length - 1);
            },
            tbSelTudo(b) {
                const u = this.tbUI(b);
                u.r = 0; u.c = 0; u.r2 = Math.max(0, b.dados.linhas.length - 1); u.c2 = Math.max(0, b.dados.cab.length - 1);
            },

            /* ----- exibição ----- */
            tbExibir(b, i, j) {
                const raw = txt(b.dados.linhas[i] && b.dados.linhas[i][j]), f = colDe(b, j).fmt;
                if (raw.charAt(0) === '=') return mostrar(valorCel(b, i, j, new Set()), f);
                if (raw === '' || f === 'auto' || f === 'texto') return raw;
                const v = valorCel(b, i, j, new Set());
                return typeof v === 'number' ? fmtNum(v, f) : raw;
            },
            tbValor(b, i, j) {
                const u = this.tbUI(b);
                return u.edit === i + ',' + j ? txt(b.dados.linhas[i] && b.dados.linhas[i][j]) : this.tbExibir(b, i, j);
            },
            tbInEstilo(b, i, j) {
                const c = colDe(b, j), v = valorCel(b, i, j, new Set()), num = typeof v === 'number', e = estDe(b, i, j) || {};
                const editando = this.tbUI(b).edit === i + ',' + j;
                const al = editando ? 'left' : (c.al ? { e: 'left', c: 'center', d: 'right' }[c.al] : (num ? 'right' : 'left'));
                let s = `text-align:${al};`;
                if (e.n) s += 'font-weight:700;';
                let cor = '';
                if (e.f && COR_OK.test(e.f)) cor = lum(e.f) > 0.6 ? '#111827' : '#f8fafc';
                else if (v instanceof Erro) cor = '#f87171';
                else if (num && v < 0 && !['auto', 'texto', 'data'].includes(c.fmt)) cor = '#f87171';
                return cor ? s + `color:${cor};` : s;
            },
            tbTdEstilo(b, i, j) { const e = estDe(b, i, j); return e && COR_OK.test(e.f || '') ? `background:${e.f};` : ''; },
            tbColW(b, j) { return colDe(b, j).w || LARG; },
            tbLarg(b) { return `width:${44 + b.dados.cab.reduce((s, _, j) => s + this.tbColW(b, j), 0) + 32}px;`; },
            tbVisiveis(b) {
                const n = b.dados.linhas.length, f = txt(this.tbUI(b).filtro).trim().toLowerCase(), todas = [...Array(n).keys()];
                if (!f) return todas;
                return todas.filter(i => b.dados.cab.some((_, j) => this.tbExibir(b, i, j).toLowerCase().includes(f)));
            },
            tbTemRodape(b) { return (b.dados.rodape || []).some(Boolean); },
            tbRodape(b, j) {
                const ag = (b.dados.rodape || [])[j];
                if (!ag) return '';
                const vals = this.tbVisiveis(b).map(i => valorCel(b, i, j, new Set())), ns = vals.filter(v => typeof v === 'number'), f = colDe(b, j).fmt;
                switch (ag) {
                    case 'soma': return fmtNum(ns.reduce((x, y) => x + y, 0), f);
                    case 'media': return ns.length ? fmtNum(ns.reduce((x, y) => x + y, 0) / ns.length, f) : '—';
                    case 'min': return ns.length ? fmtNum(Math.min(...ns), f) : '—';
                    case 'max': return ns.length ? fmtNum(Math.max(...ns), f) : '—';
                    default: return String(vals.filter(v => v !== '').length);
                }
            },
            tbStats(b) {
                const g = this.tbIntervalo(b), cel = (g.i2 - g.i1 + 1) * (g.j2 - g.j1 + 1);
                if (cel < 2) return '';
                if (cel > 4000) return `${cel} células`;
                const ns = [];
                for (let i = g.i1; i <= g.i2; i++) for (let j = g.j1; j <= g.j2; j++) {
                    const v = valorCel(b, i, j, new Set());
                    if (typeof v === 'number') ns.push(v);
                }
                if (!ns.length) return `${cel} células`;
                let f = g.j1 === g.j2 ? colDe(b, g.j1).fmt : 'auto';
                if (f === 'data' || f === 'texto') f = 'auto';
                const soma = ns.reduce((x, y) => x + y, 0);
                return `Soma: ${fmtNum(soma, f)} · Média: ${fmtNum(soma / ns.length, f)} · Contagem: ${ns.length} · Mín: ${fmtNum(Math.min(...ns), f)} · Máx: ${fmtNum(Math.max(...ns), f)}`;
            },

            /* ----- edição ----- */
            tbSet(b, i, j, v) { const l = b.dados.linhas[i]; if (l) l[j] = String(v); },
            tbInput(b, i, j, e) {
                const u = this.tbUI(b);
                if (!u.sujo) { this.tbGravar(b); u.sujo = true; }
                this.tbSet(b, i, j, e.target.value);
            },
            tbBarra(b, v) {
                const u = this.tbUI(b);
                if (!u.sujo) { this.tbGravar(b); u.sujo = true; }
                this.tbSet(b, u.r, u.c, v);
            },
            tbFocarAtivo(e, b) {
                const u = this.tbUI(b), el = e.target.closest('[data-xl]').querySelector(`input[data-r="${u.r}"][data-c="${u.c}"]`);
                if (el) { el.focus(); el.select(); }
            },
            tbMover(b, e, i, j, di, dj) {
                const vis = this.tbVisiveis(b), pos = vis.indexOf(i);
                const ni = vis.length ? vis[clamp((pos < 0 ? 0 : pos) + di, 0, vis.length - 1)] : i;
                const nj = clamp(j + dj, 0, b.dados.cab.length - 1);
                const el = e.target.closest('[data-xl]').querySelector(`input[data-r="${ni}"][data-c="${nj}"]`);
                if (el) { el.focus(); el.select(); }
            },
            tbTecla(e, b, i, j) {
                if (e.ctrlKey || e.metaKey) return; // atalhos: tbTeclaRaiz
                const k = e.key, el = e.target, u = this.tbUI(b);
                if (k === 'Enter') { e.preventDefault(); this.tbMover(b, e, i, j, e.shiftKey ? -1 : 1, 0); return; }
                if (k === 'ArrowDown' || k === 'ArrowUp') {
                    e.preventDefault();
                    const d = k === 'ArrowDown' ? 1 : -1;
                    if (e.shiftKey) { u.r2 = clamp(u.r2 + d, 0, b.dados.linhas.length - 1); return; }
                    this.tbMover(b, e, i, j, d, 0); return;
                }
                if (k === 'ArrowLeft' && !e.shiftKey && el.selectionStart === 0 && el.selectionEnd === 0) { e.preventDefault(); this.tbMover(b, e, i, j, 0, -1); return; }
                if (k === 'ArrowRight' && !e.shiftKey && el.selectionStart === el.value.length && el.selectionEnd === el.value.length) { e.preventDefault(); this.tbMover(b, e, i, j, 0, 1); return; }
                if (k === 'Delete' && !this.tbUnico(b)) { e.preventDefault(); this.tbLimpar(b); }
            },
            tbTeclaRaiz(e, b) {
                const ds = e.target && e.target.dataset;
                if (!ds || (ds.r === undefined && ds.barra === undefined)) return;
                if (!(e.ctrlKey || e.metaKey)) return;
                const k = e.key.toLowerCase();
                if (k === 'z') { e.preventDefault(); e.shiftKey ? this.tbRefazer(b) : this.tbDesfazer(b); }
                else if (k === 'y') { e.preventDefault(); this.tbRefazer(b); }
                else if (k === 'd') { e.preventDefault(); this.tbPreencher(b, 'baixo'); }
                else if (k === 'r') { e.preventDefault(); this.tbPreencher(b, 'direita'); }
                else if (k === 'b') { e.preventDefault(); this.tbNegrito(b); }
            },
            tbLimpar(b) {
                this.tbGarantir(b);
                const g = this.tbIntervalo(b);
                this.tbGravar(b);
                for (let i = g.i1; i <= g.i2; i++) for (let j = g.j1; j <= g.j2; j++) b.dados.linhas[i][j] = '';
            },
            tbPreencher(b, dir) {
                this.tbGarantir(b);
                const d = b.dados; let { i1, i2, j1, j2 } = this.tbIntervalo(b);
                if (dir === 'baixo') {
                    if (i1 === i2) { if (i1 < 1) return; i1--; }
                    this.tbGravar(b);
                    for (let j = j1; j <= j2; j++) {
                        const src = d.linhas[i1][j];
                        for (let i = i1 + 1; i <= i2; i++) d.linhas[i][j] = src.charAt(0) === '=' ? transladar(src, i - i1, 0) : src;
                    }
                } else {
                    if (j1 === j2) { if (j1 < 1) return; j1--; }
                    this.tbGravar(b);
                    for (let i = i1; i <= i2; i++) {
                        const src = d.linhas[i][j1];
                        for (let j = j1 + 1; j <= j2; j++) d.linhas[i][j] = src.charAt(0) === '=' ? transladar(src, 0, j - j1) : src;
                    }
                }
            },
            tbAutoSoma(b) {
                this.tbGarantir(b);
                const { i1, j1 } = this.tbIntervalo(b), d = b.dados;
                const num = (i, j) => typeof valorCel(b, i, j, new Set()) === 'number';
                let f = '', i = i1 - 1;
                while (i >= 0 && num(i, j1)) i--;
                if (i + 1 < i1) f = `=SOMA(${letra(j1)}${i + 2}:${letra(j1)}${i1})`;
                else {
                    let j = j1 - 1;
                    while (j >= 0 && num(i1, j)) j--;
                    if (j + 1 < j1) f = `=SOMA(${letra(j + 1)}${i1 + 1}:${letra(j1 - 1)}${i1 + 1})`;
                }
                if (!f) { this.aviso?.('Não há números acima ou à esquerda da célula para somar.'); return; }
                this.tbGravar(b);
                d.linhas[i1][j1] = f;
            },

            /* ----- formato, estilo e rodapé (valem para todas as colunas/células selecionadas) ----- */
            tbFmt(b, v) {
                if (!FMT_IDS.includes(v)) return;
                this.tbGarantir(b);
                const g = this.tbIntervalo(b); this.tbGravar(b);
                for (let j = g.j1; j <= g.j2; j++) b.dados.cols[j].fmt = v;
            },
            tbAlinhar(b, al) {
                this.tbGarantir(b);
                const g = this.tbIntervalo(b); this.tbGravar(b);
                for (let j = g.j1; j <= g.j2; j++) b.dados.cols[j].al = al;
            },
            tbRodapeSet(b, v) {
                if (!AGG_IDS.includes(v)) return;
                this.tbGarantir(b);
                const g = this.tbIntervalo(b); this.tbGravar(b);
                for (let j = g.j1; j <= g.j2; j++) b.dados.rodape[j] = v;
            },
            tbNegrito(b) {
                this.tbGarantir(b);
                const g = this.tbIntervalo(b), u = this.tbUI(b), ligar = !(estDe(b, clamp(u.r, 0, b.dados.linhas.length - 1), clamp(u.c, 0, b.dados.cab.length - 1)) || {}).n;
                this.tbGravar(b);
                for (let i = g.i1; i <= g.i2; i++) for (let j = g.j1; j <= g.j2; j++) setEst(b.dados, i, j, { n: ligar ? 1 : 0 });
            },
            tbFundo(b, cor) {
                if (cor && !COR_OK.test(cor)) return;
                this.tbGarantir(b);
                const g = this.tbIntervalo(b); this.tbGravar(b);
                for (let i = g.i1; i <= g.i2; i++) for (let j = g.j1; j <= g.j2; j++) setEst(b.dados, i, j, { f: cor || '' });
            },

            /* ----- linhas e colunas ----- */
            tbLinhaAdd(b, gravar) {
                this.tbGarantir(b);
                if (b.dados.linhas.length >= MAX_L) { this.aviso?.(`Limite de ${MAX_L} linhas.`); return; }
                if (gravar !== false) this.tbGravar(b);
                b.dados.linhas.push(Array(b.dados.cab.length).fill(''));
                b.dados.estl.push({});
            },
            tbLinhaIns(b, i) {
                this.tbGarantir(b);
                const d = b.dados;
                if (d.linhas.length >= MAX_L) { this.aviso?.(`Limite de ${MAX_L} linhas.`); return; }
                this.tbGravar(b);
                i = clamp(i, 0, d.linhas.length);
                d.linhas.splice(i, 0, Array(d.cab.length).fill(''));
                d.estl.splice(i, 0, {});
                ajustarRefs(b, 'l', i, 1);
                const u = this.tbUI(b); u.r = u.r2 = i;
            },
            tbLinhaRem(b, i) {
                this.tbGarantir(b);
                const d = b.dados;
                this.tbGravar(b);
                d.linhas.splice(i, 1); d.estl.splice(i, 1);
                ajustarRefs(b, 'l', i, -1);
                const u = this.tbUI(b), mx = Math.max(0, d.linhas.length - 1);
                u.r = clamp(u.r, 0, mx); u.r2 = clamp(u.r2, 0, mx);
            },
            tbColAdd(b, gravar) {
                this.tbGarantir(b);
                const d = b.dados;
                if (d.cab.length >= MAX_C) { this.aviso?.(`Limite de ${MAX_C} colunas.`); return; }
                if (gravar !== false) this.tbGravar(b);
                d.cab.push('Coluna ' + letra(d.cab.length));
                d.linhas.forEach(l => l.push(''));
                d.cols.push({ ...DEF_COL }); d.rodape.push('');
            },
            tbColIns(b, j) {
                this.tbGarantir(b);
                const d = b.dados;
                if (d.cab.length >= MAX_C) { this.aviso?.(`Limite de ${MAX_C} colunas.`); return; }
                this.tbGravar(b);
                j = clamp(j, 0, d.cab.length);
                d.cab.splice(j, 0, 'Coluna');
                d.linhas.forEach(l => l.splice(j, 0, ''));
                d.cols.splice(j, 0, { ...DEF_COL }); d.rodape.splice(j, 0, '');
                d.estl.forEach((o, i) => { d.estl[i] = deslocarChaves(o, j, 1); });
                ajustarRefs(b, 'c', j, 1);
                const u = this.tbUI(b); u.c = u.c2 = j;
            },
            tbColRem(b, j) {
                this.tbGarantir(b);
                const d = b.dados;
                if (d.cab.length <= 1) return;
                this.tbGravar(b);
                d.cab.splice(j, 1);
                d.linhas.forEach(l => l.splice(j, 1));
                d.cols.splice(j, 1); d.rodape.splice(j, 1);
                d.estl.forEach((o, i) => { d.estl[i] = deslocarChaves(o, j, -1); });
                ajustarRefs(b, 'c', j, -1);
                const u = this.tbUI(b), mx = d.cab.length - 1;
                u.c = clamp(u.c, 0, mx); u.c2 = clamp(u.c2, 0, mx);
            },
            tbRedim(e, b, j) {
                this.tbGarantir(b);
                const el = e.currentTarget;
                el.setPointerCapture?.(e.pointerId);
                const x0 = e.clientX, w0 = this.tbColW(b, j);
                const mover = ev => { b.dados.cols[j].w = Math.round(clamp(w0 + ev.clientX - x0, 50, 600)); };
                const fim = () => {
                    el.removeEventListener('pointermove', mover);
                    el.removeEventListener('pointerup', fim);
                    el.removeEventListener('pointercancel', fim);
                };
                el.addEventListener('pointermove', mover);
                el.addEventListener('pointerup', fim);
                el.addEventListener('pointercancel', fim);
            },
            tbAutoLarg(b, j) { this.tbGarantir(b); b.dados.cols[j].w = 0; },

            /** Ordena as linhas pela coluna ativa (vazios sempre no fim). Fórmulas que citam a própria linha acompanham o movimento. */
            tbOrdenar(b, dir) {
                this.tbGarantir(b);
                const d = b.dados, n = d.linhas.length;
                if (n < 2) return;
                const j = clamp(this.tbUI(b).c, 0, d.cab.length - 1);
                const chaves = d.linhas.map((_, i) => valorCel(b, i, j, new Set()));
                const vazio = v => v === '' || v == null || v instanceof Erro;
                const idx = [...Array(n).keys()];
                idx.sort((x, y) => {
                    const a = chaves[x], c = chaves[y], va = vazio(a), vc = vazio(c);
                    if (va || vc) return va && vc ? x - y : (va ? 1 : -1);
                    let r;
                    if (typeof a === 'number' && typeof c === 'number') r = a - c;
                    else if (typeof a === 'number') r = -1;
                    else if (typeof c === 'number') r = 1;
                    else r = String(a).localeCompare(String(c), 'pt-BR', { sensitivity: 'base', numeric: true });
                    return r * dir || x - y;
                });
                this.tbGravar(b);
                const linhas = idx.map((o, k) => d.linhas[o].map(c => c.charAt(0) === '=' ? transladar(c, k - o, 0, r => r.i === o) : c));
                const estl = idx.map(o => d.estl[o]);
                d.linhas = linhas; d.estl = estl;
            },

            /* ----- área de transferência (compatível com Excel / Google Planilhas) ----- */
            tbCopiar(e, b, corte) {
                if (this.tbUnico(b)) return; // uma célula: o navegador copia o texto normalmente
                const d = b.dados, g = this.tbIntervalo(b), raw = [], vis = [];
                for (let i = g.i1; i <= g.i2; i++) {
                    const r = [], v = [];
                    for (let j = g.j1; j <= g.j2; j++) { r.push(txt(d.linhas[i][j])); v.push(this.tbExibir(b, i, j).replace(/[\t\r\n]+/g, ' ')); }
                    raw.push(r); vis.push(v);
                }
                const tsv = vis.map(r => r.join('\t')).join('\n');
                e.clipboardData.setData('text/plain', tsv);
                e.preventDefault();
                clipTb = { tsv, raw, i0: g.i1, j0: g.j1 };
                if (corte) this.tbLimpar(b);
            },
            tbColar(e, b) {
                const t = (e.clipboardData && e.clipboardData.getData('text/plain')) || '';
                const interno = !!clipTb && clipTb.tsv === t.replace(/\r/g, '').replace(/\n$/, '');
                const simples = !/[\t\n]/.test(t.replace(/\r?\n$/, ''));
                if (!interno && simples) return; // texto simples: colagem normal na célula
                e.preventDefault();
                this.tbGarantir(b);
                this.tbGravar(b);
                const d = b.dados, g = this.tbIntervalo(b), ri = g.i1, cj = g.j1;
                const m = interno ? clipTb.raw : t.replace(/\r/g, '').replace(/\n$/, '').split('\n').map(l => l.split('\t'));
                const larg = Math.max(...m.map(l => l.length));
                while (d.linhas.length < ri + m.length && d.linhas.length < MAX_L) this.tbLinhaAdd(b, false);
                while (d.cab.length < cj + larg && d.cab.length < MAX_C) this.tbColAdd(b, false);
                m.forEach((l, a) => l.forEach((v, c) => {
                    const i = ri + a, j = cj + c;
                    if (i >= d.linhas.length || j >= d.cab.length) return;
                    d.linhas[i][j] = interno && v.charAt(0) === '=' ? transladar(v, ri - clipTb.i0, cj - clipTb.j0) : v;
                }));
                const u = this.tbUI(b);
                u.r = ri; u.c = cj;
                u.r2 = Math.min(ri + m.length - 1, d.linhas.length - 1); u.c2 = Math.min(cj + larg - 1, d.cab.length - 1);
            },

            /* ----- CSV ----- */
            async tbImportar(b, arq) {
                if (!arq) return;
                let t;
                try { t = await arq.text(); } catch { this.aviso?.('Não consegui ler o arquivo.'); return; }
                const m = lerCsv(t);
                if (!m.length) { this.aviso?.('O arquivo está vazio.'); return; }
                this.tbGarantir(b);
                const d = b.dados;
                if (d.linhas.some(l => l.some(c => c !== '')) && !confirm('Substituir o conteúdo desta tabela pelo arquivo? (Dá para desfazer com Ctrl+Z.)')) return;
                this.tbGravar(b);
                const nc = Math.min(MAX_C, Math.max(1, ...m.map(r => r.length)));
                d.cab = Array.from({ length: nc }, (_, j) => txt(m[0][j]) || ('Coluna ' + letra(j)));
                const corpo = m.slice(1, MAX_L + 1).map(r => Array.from({ length: nc }, (_, j) => txt(r[j])));
                d.linhas = corpo.length ? corpo : [Array(nc).fill('')];
                d.cols = []; d.rodape = []; d.estl = [];
                this.tbGarantir(b);
                const u = this.tbUI(b); u.r = u.c = u.r2 = u.c2 = 0;
                this.aviso?.(`${corpo.length} linha(s) importada(s).`);
            },
            tbExportar(b) {
                this.tbGarantir(b);
                const d = b.dados, q = v => (/[;"\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v);
                const linhas = [d.cab.map(q).join(';')];
                d.linhas.forEach((_, i) => linhas.push(d.cab.map((__, j) => q(this.tbExibir(b, i, j))).join(';')));
                const blob = new Blob(['\uFEFF' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = (txt(b.titulo).replace(/[^\p{L}\p{N}_ -]+/gu, '').trim() || 'tabela') + '.csv';
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            },
        };
    }

    /* ---------- encaixa nos construtores do x-data ---------- */
    function envolver(nome) {
        const base = window[nome];
        if (typeof base !== 'function') return false;
        window[nome] = function () {
            const o = base.apply(this, arguments), extra = metodosTabela();
            Object.keys(extra).forEach(k => { if (!(k in o)) o[k] = extra[k]; });
            return o;
        };
        return true;
    }
    const ok = ['diversoAbasDiagrama', 'diversoAbas', 'menteAbas', 'corpoAbas', 'carteiraAbas'].map(envolver);
    if (!ok.some(Boolean)) console.warn('tabela.js: carregue depois de diverso.js e diagrama.js (Diverso), corpo.js (Corpo), mente.js (Mente) ou carteira.js (Carteira).');
})();