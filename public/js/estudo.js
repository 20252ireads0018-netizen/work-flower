/* Work Flower · cartões de estudo da área Mente: "Flashcards", "Pomodoro", "Plano de estudos" e "Matemática".
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o graficos.js (carregue depois dele: usa wfNovoCartao).
   - Flashcards: revisão espaçada (estilo SM-2). Errei / Difícil / Bom / Fácil decidem quando a carta volta.
       b.dados = { cartas: [{ id, frente, verso, materia, int (dias), ef, prox ('AAAA-MM-DD' ou null = nova), reps, lapsos, orig? }],
                   modo: 'estudar'|'editar'|'importar', materia, novasDia, hist: { 'AAAA-MM-DD': { n, ok } }, novas: { d, n } }
   - Pomodoro: foco / pausa curta / pausa longa ligados a uma matéria; cada foco terminado vira uma sessão.
       b.dados = { foco, curta, longa, ciclos, materias: [nome], materia, fase, rodando, fim (ms), resta (s), feitos, som, auto,
                   sessoes: [{ id, d: 'AAAA-MM-DD', em (ms), materia, min }] }
   - Plano de estudos: metas de horas por matéria na semana (contadas pelos Pomodoros da página), tarefas fixas por dia
       da semana e provas com contagem regressiva; gráfico das últimas semanas e sequência de dias estudando.
       b.dados = { metas: [{ id, materia, horas }], tarefas: [{ id, dia (0 = segunda), texto, materia, feitos: { 'segunda AAAA-MM-DD': 1 } }],
                   provas: [{ id, nome, data }], ver: 'semana'|'metas'|'progresso' }
   - Matemática: uma conta por linha com a resposta ao lado (variáveis, funções, unidades, porcentagem, soma do bloco),
       derivar / integrar / simplificar / resolver / fatorar / expandir / limite e gráfico de funções (arrastar e rolar).
       math.js e nerdamer (CAS) vêm de CDN só quando o cartão aparece. b.dados = { texto, virgula, graus, graf: { xmin, xmax, ymin, ymax } }
   Desenhos (SVG) gerados aqui com texto escapado; partials/estudo-*-cartao mostram. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid, iso } = Q.util;
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const TIPOS = [
        { id: 'flashcards', nome: 'Flashcards (revisão espaçada)' },
        { id: 'pomodoro', nome: 'Pomodoro' },
        { id: 'planoestudo', nome: 'Plano de estudos' },
        { id: 'matematica', nome: 'Matemática e gráficos' },
    ];
    const CORES = ['#2f6fc7', '#2dd4bf', '#f2c230', '#fb923c', '#f472b6', '#a78bfa', '#4ade80', '#94a3b8'];
    const DIAS = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
    const MAX_CARTAS = 3000, MAX_SESSOES = 3000, MAX_HIST = 400, MAX_TEXTO_MAT = 20000;
    const MATHJS = 'https://cdnjs.cloudflare.com/ajax/libs/mathjs/13.2.0/math.min.js';
    const NERDAMER = 'https://cdn.jsdelivr.net/npm/nerdamer@1.1.13/all.min.js';

    const hoje = () => iso(new Date());
    const addDias = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };
    const diasEntre = (a, b) => Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / 864e5);
    /** Segunda-feira da semana de uma data ('AAAA-MM-DD'). */
    const segunda = s => { const d = new Date((s || hoje()) + 'T12:00:00'); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return iso(d); };
    const semAcento = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const dataCurta = s => { const [, m, d] = String(s).split('-'); return `${d}/${m}`; };
    const minTxt = m => { m = Math.round(m || 0); if (m < 60) return m + ' min'; const h = Math.floor(m / 60), r = m % 60; return h + 'h' + (r ? String(r).padStart(2, '0') : ''); };
    function passoBonito(faixa, alvo) {
        if (!(faixa > 0)) return 1;
        const bruto = faixa / (alvo || 6), mag = Math.pow(10, Math.floor(Math.log10(bruto))), f = bruto / mag;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * mag;
    }
    /** Barras simples (um valor por rótulo) em SVG; usado pelos três cartões de estudo. */
    function svgBarras(itens, opc = {}) {
        const W = 560, H = 170, e = 30, base = H - 22, topo = 12;
        const max = Math.max(opc.meta || 0, ...itens.map(i => i.v), 1);
        const larg = (W - e - 6) / Math.max(itens.length, 1);
        let s = `<svg class="es-svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(opc.rotulo || 'Gráfico')}">`;
        const passo = passoBonito(max, 3);
        for (let v = 0; v <= max + 1e-9; v += passo) {
            const y = base - (v / max) * (base - topo);
            s += `<line class="es-grade" x1="${e}" x2="${W}" y1="${y}" y2="${y}"/><text class="es-eixo" x="${e - 4}" y="${y + 3}" text-anchor="end">${esc(opc.fmt ? opc.fmt(v) : Math.round(v * 10) / 10)}</text>`;
        }
        itens.forEach((it, k) => {
            const h = (it.v / max) * (base - topo), x = e + k * larg + larg * 0.18, w = larg * 0.64;
            s += `<rect class="es-barra" x="${x}" y="${base - h}" width="${w}" height="${Math.max(h, it.v ? 1.5 : 0)}" rx="3" fill="${esc(it.cor || CORES[0])}"><title>${esc(it.r + ': ' + (opc.fmt ? opc.fmt(it.v) : it.v))}</title></rect>`;
            s += `<text class="es-eixo" x="${x + w / 2}" y="${H - 7}" text-anchor="middle">${esc(it.r)}</text>`;
        });
        if (opc.meta) {
            const y = base - (opc.meta / max) * (base - topo);
            s += `<line class="es-meta" x1="${e}" x2="${W}" y1="${y}" y2="${y}"/>`;
        }
        return s + '</svg>';
    }
    /** Carrega um script de CDN uma vez só. */
    const SCRIPTS = {};
    function carregar(url) {
        if (!SCRIPTS[url]) {
            SCRIPTS[url] = new Promise((ok, falha) => {
                const s = document.createElement('script');
                s.src = url; s.async = true; s.crossOrigin = 'anonymous';
                s.onload = () => ok(); s.onerror = () => { delete SCRIPTS[url]; falha(new Error('Não foi possível carregar ' + url)); };
                document.head.appendChild(s);
            });
        }
        return SCRIPTS[url];
    }

    /* ================= Flashcards: agenda (SM-2 simplificado) ================= */
    /** Próximo estado da carta para a nota 0 (errei), 1 (difícil), 2 (bom) ou 3 (fácil). Não altera a carta. */
    function agendar(c, nota, dia) {
        let ef = c.ef || 2.5, int = c.int || 0, reps = c.reps || 0, lapsos = c.lapsos || 0;
        if (nota === 0) { ef = Math.max(1.3, ef - 0.2); reps = 0; lapsos++; int = 0; }
        else {
            if (nota === 1) { int = reps === 0 ? 1 : Math.max(int + 1, Math.round(int * 1.2)); ef = Math.max(1.3, ef - 0.15); }
            else if (nota === 2) int = reps === 0 ? 1 : reps === 1 ? 3 : Math.max(int + 1, Math.round(int * ef));
            else { int = reps === 0 ? 3 : Math.max(int + 2, Math.round(int * ef * 1.3)); ef += 0.15; }
            reps++;
        }
        int = Math.min(int, 3650);
        return { ef: Math.round(ef * 100) / 100, int, reps, lapsos, prox: addDias(dia || hoje(), int) };
    }
    const intTxt = d => (d <= 0 ? 'de novo' : d < 30 ? d + 'd' : d < 365 ? Math.round(d / 30) + ' mês' : (Math.round(d / 36.5) / 10).toLocaleString('pt-BR') + ' ano');
    /** "frente ; verso" ou "frente<TAB>verso" (exportação do Anki/Quizlet), uma por linha. Matéria opcional em 3ª coluna. */
    function lerImportacao(texto) {
        return String(texto || '').split(/\r?\n/).map(l => {
            const p = l.includes('\t') ? l.split('\t') : l.split(/\s*;\s*|\s+-\s+|\s*=\s*/);
            if (p.length < 2 || !p[0].trim() || !p[1].trim()) return null;
            return { frente: p[0].trim().slice(0, 2000), verso: p[1].trim().slice(0, 4000), materia: (p[2] || '').trim().slice(0, 60) };
        }).filter(Boolean);
    }

    /* ================= Matemática: interpretar as linhas ================= */
    const NOMES_PT = { sen: 'sin', tg: 'tan', arcsen: 'asin', arccos: 'acos', arctg: 'atan', raiz: 'sqrt', log: 'log10', ln: 'log', cotg: 'cot', cossec: 'csc' };
    /** Ajusta o jeito brasileiro de escrever para a sintaxe do math.js (vírgula decimal, ";" entre argumentos, sen, ln, log, ×, ÷, ², π). */
    function traduzir(s, virgula) {
        s = String(s)
            .replace(/[×·]/g, '*').replace(/÷/g, '/').replace(/−/g, '-').replace(/π/g, 'pi').replace(/√\s*\(/g, 'sqrt(').replace(/√\s*([\w.]+)/g, 'sqrt($1)')
            .replace(/²/g, '^2').replace(/³/g, '^3').replace(/∞/g, 'Infinity');
        if (virgula) {
            s = s.replace(/\b\d{1,3}(?:\.\d{3})+(?:,\d+)?\b/g, m => m.replace(/\./g, '')); // 1.234.567,89
            s = s.replace(/(\d),(\d)/g, '$1.$2').replace(/;/g, ',');
        }
        s = s.replace(/\braiz\s*\(([^(),]+),([^(),]+)\)/gi, 'nthRoot($1,$2)'); // raiz(27; 3) = raiz cúbica
        s = s.replace(/\b(sen|tg|arcsen|arccos|arctg|raiz|log|ln|cotg|cossec)\s*\(/gi, (m, n) => NOMES_PT[n.toLowerCase()] + '(');
        // "10% de 250" → (10/100)*250 ; "250 + 10%" → 250*(1+10/100)
        s = s.replace(/(\d+(?:\.\d+)?)\s*%\s*(?:de|of)\s+/gi, '($1/100)*');
        s = s.replace(/^(.+?)\s*([+-])\s*(\d+(?:\.\d+)?)\s*%\s*$/, (m, a, op, p) => `(${a})*(1${op}${p}/100)`);
        s = s.replace(/\s+(?:para|em)\s+(?=[a-zA-Z°]+\s*$)/, ' to '); // 5 km para m
        return s;
    }
    /** Número / matriz / unidade → texto (vírgula decimal quando ligado). */
    function formatar(math, v, virgula) {
        if (v == null) return '';
        if (typeof v === 'boolean') return v ? 'verdadeiro' : 'falso';
        if (typeof v === 'function') return '';
        if (typeof v === 'number') {
            if (!Number.isFinite(v)) return Number.isNaN(v) ? 'indefinido' : (v > 0 ? '∞' : '-∞');
            const a = Math.abs(v);
            if (a !== 0 && (a >= 1e15 || a < 1e-6)) {
                const t = v.toExponential(6).replace(/\.?0+e/, 'e').replace('e+', 'e');
                return virgula ? t.replace('.', ',') : t;
            }
            return v.toLocaleString(virgula ? 'pt-BR' : 'en-US', { maximumFractionDigits: 10 });
        }
        let t = math.format(v, { precision: 10 });
        if (virgula) t = t.replace(/,/g, ';').replace(/(\d)\.(\d)/g, '$1,$2');
        return t;
    }
    /** Deixa a saída do CAS mais legível (2*x → 2x, espaços). */
    function bonito(t, virgula) {
        t = String(t).replace(/\s+/g, '').replace(/(?<![\^\w.])(\d+(?:\.\d+)?)\*([a-zA-Z(])/g, '$1$2')
            .replace(/\basin\(/g, 'arcsen(').replace(/\batan\(/g, 'arctg(').replace(/\bsin\(/g, 'sen(').replace(/\btan\(/g, 'tg(').replace(/\*/g, '·').replace(/\+/g, ' + ').replace(/(?<=[\w)])-/g, ' - ').replace(/=/g, ' = ');
        if (virgula) t = t.replace(/(\d)\.(\d)/g, '$1,$2').replace(/,(?=\D)/g, '; ');
        return t.replace(/log10\(|(?<![a-z])log\(/g, m => (m === 'log10(' ? 'log(' : 'ln(')).replace(/sqrt\(/g, '√(').replace(/\bpi\b/g, 'π').replace(/\bInfinity\b/g, '∞');
    }
    /** Primeira variável de uma letra que aparece na expressão (prefere x). */
    function variavelDe(s, escopo) {
        const nomes = (String(s).match(/\b[a-zA-Z]\b/g) || []).filter(n => !['e', 'i'].includes(n));
        if (nomes.includes('x')) return 'x';
        return nomes.find(n => !(escopo && n in escopo)) || nomes[0] || 'x';
    }
    /** Integral definida numérica (Simpson, n par) como apoio quando o CAS não fecha a conta. */
    function simpson(f, a, b, n = 2000) {
        const h = (b - a) / n;
        let s = f(a) + f(b);
        for (let i = 1; i < n; i++) s += f(a + i * h) * (i % 2 ? 4 : 2);
        return s * h / 3;
    }
    /** Raízes reais por varredura + bisseção (apoio para "resolver" sem CAS). */
    function raizes(f, a = -100, b = 100, n = 4000) {
        const out = [], h = (b - a) / n;
        let x0 = a, y0 = f(x0);
        for (let k = 1; k <= n; k++) {
            const x1 = a + k * h, y1 = f(x1);
            if (Number.isFinite(y0) && Number.isFinite(y1)) {
                if (y0 === 0) out.push(x0);
                else if (y0 * y1 < 0) {
                    let lo = x0, hi = x1, flo = y0;
                    for (let i = 0; i < 60; i++) { const m = (lo + hi) / 2, fm = f(m); if (flo * fm <= 0) hi = m; else { lo = m; flo = fm; } }
                    const r = (lo + hi) / 2;
                    if (Math.abs(f(r)) < 1e-6) out.push(r);
                }
            }
            x0 = x1; y0 = y1;
        }
        return [...new Set(out.map(r => Math.round(r * 1e9) / 1e9))].slice(0, 12);
    }
    const PRECISA_CAS = /^\s*(integrar|integral|∫|resolver|resolva|fatorar|fatore|expandir|expanda|limite|lim)\b/i;

    /** Avalia todas as linhas. Devolve { linhas: [{ txt, cls, tit }], escopo, funcs: [{ expr, nome }] }. */
    function avaliarTudo(texto, opc, math, nerd) {
        const escopo = {}, funcs = [], linhas = [];
        let bloco = [];
        const virgula = !!opc.virgula;
        if (opc.graus) {
            const r = Math.PI / 180;
            const dir = fn => x => (typeof x === 'number' ? fn(x * r) : math[fn.name](x));
            escopo.sin = dir(Math.sin); escopo.cos = dir(Math.cos); escopo.tan = dir(Math.tan);
            escopo.asin = x => Math.asin(x) / r; escopo.acos = x => Math.acos(x) / r; escopo.atan = x => Math.atan(x) / r;
        }
        const num = v => (typeof v === 'number' ? v : (v && typeof v.toNumber === 'function' && v.units == null ? v.toNumber() : NaN));
        const fNum = (expr, v) => { const c = math.compile(expr); return x => { try { const r = c.evaluate({ ...escopo, [v]: x }); return typeof r === 'number' ? r : NaN; } catch (_) { return NaN; } }; };
        const ok = (txt, extra) => ({ txt, cls: 'ok', ...extra });
        const cas = (fn) => { if (!nerd) return { txt: 'carregando…', cls: 'espera' }; return fn(); };
        const defs = {};
        const TRIG = ['sin', 'cos', 'tan', 'asin', 'acos', 'atan'];
        /** Expressão do usuário → nó do math.js com as funções definidas (f(x) = ...) e as variáveis numéricas já trocadas. */
        const expandir = (txt, v) => {
            let n = math.parse(traduzir(txt, virgula));
            for (let volta = 0; volta < 8; volta++) {
                let mudou = false;
                n = n.transform(no => {
                    if (no.isFunctionNode && no.fn && defs[no.fn.name]) {
                        const df = defs[no.fn.name];
                        mudou = true;
                        return df.corpo.transform(s => {
                            const k = s.isSymbolNode ? df.params.indexOf(s.name) : -1;
                            return k >= 0 && no.args[k] ? no.args[k] : s;
                        });
                    }
                    if (no.isSymbolNode && no.name !== v && typeof escopo[no.name] === 'number' && !TRIG.includes(no.name)) {
                        mudou = true;
                        return new math.ConstantNode(escopo[no.name]);
                    }
                    return no;
                });
                if (!mudou) break;
            }
            return n;
        };
        const paraCas = (txt, v) => expandir(txt, v).toString({ implicit: 'show', parenthesis: 'auto' });

        String(texto || '').slice(0, MAX_TEXTO_MAT).split('\n').forEach(bruta => {
            let l = bruta.replace(/\s*(\/\/|#).*$/, '').trim();
            if (!l) { if (!bruta.trim()) bloco = []; linhas.push({ txt: '', cls: '' }); return; }
            // "Rótulo: conta" (estilo Soulver); "f(x): ..." não é rótulo
            const rot = l.match(/^([A-Za-zÀ-ú][^:=()]*?):\s*(.+)$/);
            if (rot && !/^(derivar|derivada|integrar|integral|simplificar|resolver|fatorar|expandir|limite|grafico|gráfico|plotar|plot)\b/i.test(rot[1])) l = rot[2];
            const cmd = semAcento(l.split(/\s+/)[0]);
            const resto = l.replace(/^\S+\s*/, '').replace(/^(de|da|do)\s+/i, '');
            try {
                /* ----- gráfico ----- */
                if (/^(grafico|plotar|plot|graf)$/.test(cmd)) {
                    const partes = resto.split(virgula ? /\s*;\s*/ : /\s*[;,]\s*(?![^(]*\))/).map(p => p.replace(/^\s*y\s*=\s*/i, '').trim()).filter(Boolean);
                    partes.forEach(p => {
                        const e = /^[a-zA-Z]\w*$/.test(p) && typeof escopo[p] === 'function' ? p + '(x)' : traduzir(p, virgula);
                        math.parse(e);
                        funcs.push({ expr: e, nome: p });
                    });
                    linhas.push(ok(partes.length ? '📈 ' + partes.length + (partes.length === 1 ? ' função no gráfico' : ' funções no gráfico') : 'escreva a função: gráfico x^2', { cls: partes.length ? 'info' : 'erro' }));
                    return;
                }
                /* ----- derivada: "derivar x^2 sin(x)", "d/dy y^3", "derivar x^3 em x = 2", "derivar 2 x^4" (segunda) ----- */
                const dd = l.match(/^d(\d)?\/d([a-z])\d?\s+(.+)$/i);
                if (dd || /^(derivar|derivada|derive|derivacao)$/.test(cmd)) {
                    let corpo = dd ? dd[3] : resto, ordem = dd && dd[1] ? +dd[1] : 1, ponto = null;
                    const vezes = corpo.match(/^(.+?)\s+(\d)\s*vezes$/i);
                    if (vezes) { corpo = vezes[1]; ordem = +vezes[2]; }
                    const em = corpo.match(/^(.+?)\s+(?:em|no ponto|para|at)\s+(?:([a-z])\s*=\s*)?(\S+)$/i);
                    if (em) { corpo = em[1]; ponto = em[3]; }
                    const v = dd ? dd[2] : (em && em[2]) || variavelDe(traduzir(corpo, virgula), escopo);
                    let d = expandir(corpo, v);
                    for (let k = 0; k < Math.min(ordem, 6); k++) d = math.derivative(d, v);
                    d = math.simplify(d);
                    if (ponto != null) {
                        const val = d.evaluate({ ...escopo, [v]: math.evaluate(traduzir(ponto, virgula), escopo) });
                        linhas.push(ok(formatar(math, val, virgula), { tit: (ordem > 1 ? ordem + 'ª derivada' : 'derivada') + ': ' + bonito(d.toString(), virgula) }));
                        return;
                    }
                    linhas.push(ok(bonito(d.toString(), virgula), { cls: 'cas', tit: `d${ordem > 1 ? '^' + ordem : ''}/d${v}` }));
                    return;
                }
                if (/^(simplificar|simplifique|simplifica)$/.test(cmd)) {
                    linhas.push(ok(bonito(math.simplify(expandir(resto, null)).toString(), virgula), { cls: 'cas' }));
                    return;
                }
                /* ----- integral: "integrar x^2", "integrar x^2 de 0 a 3", "∫ x^2 dx" ----- */
                if (/^(integrar|integral|integre|∫)$/.test(cmd) || /^∫/.test(l)) {
                    let corpo = /^∫/.test(l) && cmd !== '∫' ? l.slice(1) : resto, a = null, bb = null;
                    const lim = corpo.match(/^(.+?)\s+de\s+(\S+)\s+(?:a|ate|até)\s+(\S+)$/i);
                    if (lim) { corpo = lim[1]; a = lim[2]; bb = lim[3]; }
                    const dx = corpo.match(/^(.+?)\s*d([a-z])$/);
                    const v = dx ? dx[2] : variavelDe(traduzir(corpo, virgula), escopo);
                    const e = paraCas(dx ? dx[1] : corpo, v);
                    if (a != null) {
                        const A = num(math.evaluate(traduzir(a, virgula), escopo)), B = num(math.evaluate(traduzir(bb, virgula), escopo));
                        const n = simpson(fNum(e, v), A, B);
                        let simb = '';
                        if (nerd) { try { simb = bonito(nerd(`integrate(${e},${v})`).toString(), virgula); } catch (_) { /* só o número */ } }
                        linhas.push(ok(formatar(math, Math.round(n * 1e9) / 1e9, virgula), { tit: simb ? 'primitiva: ' + simb + ' + C' : 'integral numérica' }));
                        return;
                    }
                    linhas.push(cas(() => {
                        const r = nerd(`integrate(${e},${v})`).toString();
                        if (/integrate\(/.test(r)) return { txt: 'sem primitiva simples', cls: 'erro' };
                        return ok(bonito(r, virgula) + ' + C', { cls: 'cas', tit: '∫ d' + v });
                    }));
                    return;
                }
                /* ----- resolver equação: "resolver x^2 - 5x + 6 = 0" (ou sem "= 0") ----- */
                if (/^(resolver|resolva|resolve)$/.test(cmd)) {
                    const [esq0, dir0] = resto.split('=');
                    const v = variavelDe(traduzir(resto, virgula).replace(/\b[a-z]\w+\(/gi, '('), escopo);
                    const exprZero = dir0 != null ? `(${paraCas(esq0, v)})-(${paraCas(dir0, v)})` : paraCas(esq0, v);
                    if (nerd) {
                        try {
                            const sol = nerd.solve(exprZero, v).toString().replace(/^\[|\]$/g, '');
                            if (sol) { linhas.push(ok(sol.split(',').map(s => v + ' = ' + bonito(s, virgula)).join(' ou '), { cls: 'cas' })); return; }
                        } catch (_) { /* tenta numérico */ }
                    }
                    const rs = raizes(fNum(exprZero, v));
                    linhas.push(rs.length ? ok(rs.map(r => v + ' ≈ ' + formatar(math, r, virgula)).join(' ou '), { tit: 'raízes reais entre -100 e 100' }) : (nerd ? { txt: 'sem solução real encontrada', cls: 'erro' } : { txt: 'carregando…', cls: 'espera' }));
                    return;
                }
                if (/^(fatorar|fatore|expandir|expanda)$/.test(cmd)) {
                    const fn = cmd.startsWith('fator') ? 'factor' : 'expand';
                    linhas.push(cas(() => ok(bonito(nerd(`${fn}(${paraCas(resto, null)})`).toString(), virgula), { cls: 'cas' })));
                    return;
                }
                /* ----- limite: "limite sin(x)/x quando x -> 0" ----- */
                if (/^(limite|lim)$/.test(cmd)) {
                    const m = resto.match(/^(.+?)\s*(?:,|quando|com|para)?\s*([a-z])\s*(?:->|→|tende a)\s*(\S+)$/i);
                    if (!m) { linhas.push({ txt: 'use: limite sin(x)/x quando x -> 0', cls: 'erro' }); return; }
                    const alvo = traduzir(m[3], virgula).replace(/^infinito$/i, 'Infinity');
                    linhas.push(cas(() => ok(bonito(nerd(`limit(${paraCas(m[1], m[2])},${m[2]},${alvo})`).toString(), virgula), { cls: 'cas' })));
                    return;
                }
                /* ----- soma / média do bloco (linhas acima até a última em branco) ----- */
                if (/^(soma|total|media)$/.test(cmd) && !resto) {
                    const nums = bloco.filter(Number.isFinite);
                    const v = cmd === 'media' ? (nums.length ? nums.reduce((s, n) => s + n, 0) / nums.length : 0) : nums.reduce((s, n) => s + n, 0);
                    escopo.ans = v; // não entra no bloco: "total" embaixo de "média" não soma a média
                    linhas.push(ok(formatar(math, v, virgula), { cls: 'ok total' }));
                    return;
                }
                /* ----- conta comum (com variáveis e funções: "x = 3", "f(x) = x^2 + 1", "f(2)") ----- */
                const e = traduzir(l, virgula);
                const v = math.evaluate(e, escopo);
                const def = e.match(/^\s*([a-zA-Z]\w*)\s*\(\s*([a-zA-Z]\w*(?:\s*,\s*[a-zA-Z]\w*)*)\s*\)\s*=\s*(.+)$/);
                if (def) defs[def[1]] = { params: def[2].split(',').map(p => p.trim()), corpo: math.parse(def[3]) };
                if (typeof v === 'function') { linhas.push({ txt: 'função ' + l.split('=')[0].trim(), cls: 'info' }); return; }
                const n = num(v);
                if (Number.isFinite(n)) bloco.push(n);
                if (v != null && typeof v !== 'function') escopo.ans = v;
                linhas.push(ok(formatar(math, v, virgula)));
            } catch (err) {
                // texto solto (sem números nem operadores) é anotação: fica sem resposta
                if (!/[\d+\-*/^=()]/.test(l)) { linhas.push({ txt: '', cls: '' }); return; }
                linhas.push({ txt: '?', cls: 'erro', tit: String(err && err.message || err).slice(0, 200) });
            }
        });
        return { linhas, escopo, funcs };
    }

    /* ================= Matemática: gráfico ================= */
    function svgFuncoes(funcs, escopo, g, w, h, math, cursor) {
        const { xmin, xmax, ymin, ymax } = g;
        const X = x => (x - xmin) / (xmax - xmin) * w, Y = y => h - (y - ymin) / (ymax - ymin) * h;
        let s = `<svg class="mt-svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}">`;
        // mesma escala nos dois eixos: o passo sai da menor dimensão (pelo menos 4 marcas), sem amontoar os números
        const base = passoBonito(Math.min(xmax - xmin, ymax - ymin), 4);
        const px = Math.max(base, passoBonito(xmax - xmin, Math.max(3, w / 55))), py = Math.max(base, passoBonito(ymax - ymin, Math.max(2, h / 40)));
        const rot = v => { const r = Math.round(v / Math.min(px, py) * 1e6) / 1e6 * Math.min(px, py); return String(Math.round(r * 1e8) / 1e8).replace('.', ','); };
        for (let x = Math.ceil(xmin / px) * px; x <= xmax; x += px) s += `<line class="mt-grade" x1="${X(x)}" x2="${X(x)}" y1="0" y2="${h}"/>`;
        for (let y = Math.ceil(ymin / py) * py; y <= ymax; y += py) s += `<line class="mt-grade" y1="${Y(y)}" y2="${Y(y)}" x1="0" x2="${w}"/>`;
        const x0 = Math.min(Math.max(X(0), 0), w), y0 = Math.min(Math.max(Y(0), 0), h);
        s += `<line class="mt-eixo" x1="0" x2="${w}" y1="${y0}" y2="${y0}"/><line class="mt-eixo" x1="${x0}" x2="${x0}" y1="0" y2="${h}"/>`;
        for (let x = Math.ceil(xmin / px) * px; x <= xmax; x += px) if (Math.abs(x) > px / 2) s += `<text class="mt-num" x="${X(x)}" y="${Math.min(Math.max(y0 + 12, 12), h - 3)}" text-anchor="middle">${esc(rot(x))}</text>`;
        for (let y = Math.ceil(ymin / py) * py; y <= ymax; y += py) if (Math.abs(y) > py / 2) s += `<text class="mt-num" x="${Math.min(Math.max(x0 - 4, 26), w - 2)}" y="${Y(y) + 3}" text-anchor="end">${esc(rot(y))}</text>`;
        const leit = [];
        funcs.forEach((f, k) => {
            let comp;
            try { comp = math.compile(f.expr); } catch (_) { return; }
            const cor = CORES[k % CORES.length], ev = x => { try { const r = comp.evaluate({ ...escopo, x }); return typeof r === 'number' ? r : (r && r.re !== undefined && Math.abs(r.im) < 1e-12 ? r.re : NaN); } catch (_) { return NaN; } };
            let d = '', ant = null;
            const passos = Math.min(Math.round(w), 1600);
            for (let i = 0; i <= passos; i++) {
                const x = xmin + (xmax - xmin) * i / passos, y = ev(x);
                if (!Number.isFinite(y)) { ant = null; continue; }
                const sy = Y(y);
                // salto muito grande (assíntota): começa outro trecho
                if (ant != null && Math.abs(sy - ant) > h * 2) { ant = null; }
                const cy = Math.max(-h, Math.min(2 * h, sy));
                d += (ant == null ? 'M' : 'L') + (X(x)).toFixed(1) + ' ' + cy.toFixed(1);
                ant = sy;
            }
            if (d) s += `<path class="mt-curva" d="${d}" stroke="${cor}"/>`;
            if (cursor) { const y = ev(cursor.x); if (Number.isFinite(y)) { leit.push({ nome: f.nome, y, cor }); s += `<circle cx="${X(cursor.x)}" cy="${Y(y)}" r="3.5" fill="${cor}"/>`; } }
        });
        if (cursor) {
            s += `<line class="mt-cursor" x1="${X(cursor.x)}" x2="${X(cursor.x)}" y1="0" y2="${h}"/>`;
            const fx = v => String(Math.round(v * 1000) / 1000).replace('.', ',');
            const linhas = [`x = ${fx(cursor.x)}`, ...leit.map(l => `${corta(l.nome, 18)} = ${fx(l.y)}`)];
            const bw = 8 + Math.max(...linhas.map(t => t.length)) * 6.4, bx = X(cursor.x) + bw + 14 > w ? X(cursor.x) - bw - 8 : X(cursor.x) + 8;
            s += `<rect class="mt-leitura" x="${bx}" y="6" width="${bw}" height="${8 + linhas.length * 14}" rx="5"/>`;
            linhas.forEach((t, i) => { s += `<text class="mt-leit-txt" x="${bx + 5}" y="${19 + i * 14}" ${i ? `style="fill:${leit[i - 1].cor}"` : ''}>${esc(t)}</text>`; });
        }
        if (!funcs.length) s += `<text class="mt-num" x="${w / 2}" y="${h / 2 - 14}" text-anchor="middle">Escreva uma linha “gráfico x^2” (ou “gráfico sen(x); f”) para desenhar aqui.</text>`;
        return s + '</svg>';
    }
    const corta = (t, n) => (String(t).length > n ? String(t).slice(0, n - 1) + '…' : String(t));

    const CACHE_MT = new Map(); // b.id → { chave, res } (fora do Alpine: o escopo tem funções e objetos do math.js)

    const EXEMPLO_MAT = [
        '// Uma conta por linha; a resposta aparece ao lado.',
        'Aluguel: 1.250,00',
        'Mercado: 830,50',
        'Luz: 189,90',
        'total',
        '',
        '15% de 830,50',
        '1200 + 8%',
        '5 km para m',
        '',
        'r = 3',
        'área = pi * r^2',
        'f(x) = x^3 - 4x',
        'f(2)',
        '',
        'derivar x^2 * sen(x)',
        'derivar f(x) em x = 1',
        'integrar x^2 de 0 a 3',
        'integrar x * e^x',
        'resolver x^2 - 5x + 6 = 0',
        'limite sen(x)/x quando x -> 0',
        'fatorar x^2 - 9',
        '',
        'gráfico f; sen(x); x^2/4',
    ].join('\n');

    /* ================= métodos dos cartões ================= */
    const extras = {
        /* ---------- criar ---------- */
        novoFlashcards(sec) {
            return this.wfNovoCartao(sec, 'flashcards', 'Flashcards', 460, 400, {
                modo: 'estudar', materia: '', novasDia: 20, hist: {}, novas: { d: '', n: 0 },
                cartas: [
                    { id: uid(), frente: 'Qual é a derivada de sen(x)?', verso: 'cos(x)', materia: 'Matemática', prox: null },
                    { id: uid(), frente: 'Fórmula de Bhaskara', verso: 'x = (-b ± √(b² - 4ac)) / 2a', materia: 'Matemática', prox: null },
                    { id: uid(), frente: 'Crase: “Vou ___ escola.”', verso: 'à (a preposição + a artigo)', materia: 'Português', prox: null },
                ],
            }, 'Flashcards adicionado com 3 cartas de exemplo. Use “Editar” ou “Importar” para pôr as suas.');
        },
        novoPomodoro(sec) {
            return this.wfNovoCartao(sec, 'pomodoro', 'Pomodoro', 400, 440, {
                foco: 25, curta: 5, longa: 15, ciclos: 4, materias: ['Matemática', 'Português'], materia: 'Matemática',
                fase: 'foco', rodando: false, fim: null, resta: 25 * 60, feitos: 0, som: true, auto: false, sessoes: [],
            }, 'Pomodoro adicionado. Escolha a matéria e clique em Começar.');
        },
        novoPlanoEstudo(sec) {
            return this.wfNovoCartao(sec, 'planoestudo', 'Plano de estudos', 620, 440, {
                ver: 'semana',
                metas: [{ id: uid(), materia: 'Matemática', horas: 5 }, { id: uid(), materia: 'Português', horas: 3 }],
                tarefas: [
                    { id: uid(), dia: 0, texto: 'Lista de exercícios', materia: 'Matemática', feitos: {} },
                    { id: uid(), dia: 2, texto: 'Revisar flashcards', materia: '', feitos: {} },
                    { id: uid(), dia: 4, texto: 'Redação', materia: 'Português', feitos: {} },
                ],
                provas: [],
            }, 'Plano de estudos adicionado. As horas contam sozinhas pelos cartões Pomodoro da página.');
        },
        novoMatematica(sec) {
            return this.wfNovoCartao(sec, 'matematica', 'Matemática', 680, 620, {
                texto: EXEMPLO_MAT, virgula: true, graus: false, verGraf: true, graf: { xmin: -8, xmax: 8, yc: 0 },
            }, 'Matemática adicionado com exemplos. Apague e escreva as suas contas.');
        },
        esUI(b, padrao) { this.wfEsUI[b.id] ??= { ...padrao }; return this.wfEsUI[b.id]; },
        /** Matérias citadas nos cartões de estudo e de questões da página (para as listas de escolha). */
        esMaterias() {
            const s = new Set();
            this.estado.blocos.forEach(x => {
                const d = x.dados || {};
                if (x.tipo === 'pomodoro') (d.materias || []).forEach(m => m && s.add(m));
                if (x.tipo === 'planoestudo') (d.metas || []).forEach(m => m.materia && s.add(m.materia));
                if (x.tipo === 'flashcards') (d.cartas || []).forEach(c => c.materia && s.add(c.materia));
                if (x.tipo === 'questoes') (d.questoes || []).forEach(q => q.materia && s.add(q.materia));
            });
            return [...s].sort((a, b) => a.localeCompare(b, 'pt-BR'));
        },

        /* ---------- Flashcards ---------- */
        fcUI(b) { return this.esUI(b, { virada: false, vistos: {}, busca: '', imp: '', fonte: '', nova: { frente: '', verso: '', materia: '' }, sessao: 0 }); },
        fcCartas(b) { const m = b.dados.materia; return (b.dados.cartas || []).filter(c => !m || c.materia === m); },
        fcMaterias(b) { return [...new Set((b.dados.cartas || []).map(c => c.materia).filter(Boolean))].sort((a, c) => a.localeCompare(c, 'pt-BR')); },
        /** Cartas para hoje: as vencidas e as novas (até o limite diário), as já vistas nesta sessão vão para o fim. */
        fcFila(b) {
            const d = hoje(), ui = this.fcUI(b), cartas = this.fcCartas(b);
            const usadas = b.dados.novas && b.dados.novas.d === d ? b.dados.novas.n : 0;
            const vencidas = cartas.filter(c => c.prox && c.prox <= d).sort((a, c) => (a.prox < c.prox ? -1 : 1));
            const novas = cartas.filter(c => !c.prox).slice(0, Math.max(0, (b.dados.novasDia ?? 20) - usadas));
            return [...vencidas, ...novas].sort((a, c) => (ui.vistos[a.id] || 0) - (ui.vistos[c.id] || 0));
        },
        fcAtual(b) { return this.fcFila(b)[0] || null; },
        fcContagem(b) {
            const d = hoje(), cartas = this.fcCartas(b), fila = this.fcFila(b);
            return {
                novas: fila.filter(c => !c.prox).length, revisar: fila.filter(c => c.prox).length,
                total: cartas.length, maduras: cartas.filter(c => (c.int || 0) >= 21).length,
                amanha: cartas.filter(c => c.prox && c.prox <= addDias(d, 1)).length - fila.filter(c => c.prox).length,
                feitasHoje: (b.dados.hist || {})[d] ? b.dados.hist[d].n : 0,
                acertoHoje: (b.dados.hist || {})[d] && b.dados.hist[d].n ? Math.round(b.dados.hist[d].ok / b.dados.hist[d].n * 100) : null,
            };
        },
        fcPrevisao(b, c, nota) { return intTxt(agendar(c, nota).int); },
        fcResponder(b, nota) {
            const c = this.fcAtual(b), ui = this.fcUI(b);
            if (!c || !ui.virada) return;
            const d = hoje();
            if (!c.prox) { // nova: conta no limite diário
                if (!b.dados.novas || b.dados.novas.d !== d) b.dados.novas = { d, n: 0 };
                b.dados.novas.n++;
            }
            Object.assign(c, agendar(c, nota, d));
            b.dados.hist ??= {};
            const h = b.dados.hist[d] || { n: 0, ok: 0 };
            h.n++; if (nota > 0) h.ok++;
            b.dados.hist[d] = h;
            const ks = Object.keys(b.dados.hist).sort();
            if (ks.length > MAX_HIST) ks.slice(0, ks.length - MAX_HIST).forEach(k => delete b.dados.hist[k]);
            ui.vistos[c.id] = ++ui.sessao;
            ui.virada = false;
        },
        fcTecla(b, ev) {
            if (b.dados.modo !== 'estudar' || /INPUT|TEXTAREA|SELECT/.test(ev.target.tagName)) return;
            const ui = this.fcUI(b);
            if (ev.key === ' ' || ev.key === 'Enter') { ev.preventDefault(); if (!ui.virada) ui.virada = true; else this.fcResponder(b, 2); }
            else if (ui.virada && /^[1-4]$/.test(ev.key)) { ev.preventDefault(); this.fcResponder(b, +ev.key - 1); }
        },
        fcAdicionar(b) {
            const n = this.fcUI(b).nova;
            if (!n.frente.trim() || !n.verso.trim()) { this.aviso('Preencha a frente e o verso.'); return; }
            if ((b.dados.cartas || []).length >= MAX_CARTAS) { this.aviso(`Limite de ${MAX_CARTAS} cartas por cartão.`); return; }
            b.dados.cartas.unshift({ id: uid(), frente: n.frente.trim(), verso: n.verso.trim(), materia: (n.materia || b.dados.materia || '').trim(), prox: null });
            this.fcUI(b).nova = { frente: '', verso: '', materia: n.materia };
        },
        fcRemover(b, c) { b.dados.cartas = b.dados.cartas.filter(x => x.id !== c.id); },
        fcReiniciar(b, c) { Object.assign(c, { prox: null, int: 0, reps: 0, ef: 2.5, lapsos: 0 }); },
        fcEditaveis(b) {
            const q = semAcento(this.fcUI(b).busca).trim();
            return this.fcCartas(b).filter(c => !q || semAcento(c.frente + ' ' + c.verso + ' ' + (c.materia || '')).includes(q)).slice(0, 200);
        },
        fcImportarTexto(b) {
            const ui = this.fcUI(b), novas = lerImportacao(ui.imp);
            if (!novas.length) { this.aviso('Nada para importar. Use uma carta por linha: frente ; verso'); return; }
            const livre = MAX_CARTAS - (b.dados.cartas || []).length;
            novas.slice(0, livre).forEach(n => b.dados.cartas.push({ id: uid(), ...n, materia: n.materia || b.dados.materia || '', prox: null }));
            ui.imp = '';
            b.dados.modo = 'estudar';
            const n = Math.min(novas.length, livre);
            this.aviso(n === 1 ? '1 carta importada.' : `${n} cartas importadas.`);
        },
        /** Cartões "Questões" da página que podem virar flashcards. */
        fcFontes(b) { return this.estado.blocos.filter(x => x.tipo === 'questoes' && (x.dados.questoes || []).length).map(x => ({ id: x.id, nome: (x.titulo || 'Questões') + ' (' + x.dados.questoes.length + ')' })); },
        fcImportarQuestoes(b) {
            const x = this.blocoPorId(this.fcUI(b).fonte);
            if (!x) { this.aviso('Escolha um cartão de Questões.'); return; }
            const L = ['A', 'B', 'C', 'D', 'E'], ja = new Set((b.dados.cartas || []).map(c => c.orig).filter(Boolean));
            let n = 0;
            const soErradas = !!this.fcUI(b).soErradas, resp = x.dados.resp || {};
            (x.dados.questoes || []).forEach(q => {
                const orig = 'q:' + x.id + ':' + q.id;
                if (ja.has(orig) || b.dados.cartas.length >= MAX_CARTAS) return;
                if (soErradas && !(q.id in resp && resp[q.id] !== q.certa)) return;
                const alts = (q.alts || []).map((a, k) => (a ? L[k] + ') ' + a : '')).filter(Boolean).join('\n');
                const certa = q.alts && q.alts[q.certa] ? L[q.certa] + ') ' + q.alts[q.certa] : '';
                b.dados.cartas.push({ id: uid(), orig, materia: q.materia || '', prox: null, frente: (q.enunciado + (alts ? '\n\n' + alts : '')).slice(0, 2000), verso: ('Resposta: ' + certa + (q.expl ? '\n\n' + q.expl : '')).slice(0, 4000) });
                n++;
            });
            this.aviso(n === 1 ? '1 questão virou flashcard.' : n ? `${n} questões viraram flashcards.` : 'Nenhuma questão nova para trazer.');
            if (n) b.dados.modo = 'estudar';
        },
        fcSvg(b) {
            const h = b.dados.hist || {}, d = hoje();
            const itens = Array.from({ length: 14 }, (_, i) => { const k = addDias(d, i - 13); return { r: dataCurta(k), v: h[k] ? h[k].n : 0, cor: CORES[1] }; });
            return svgBarras(itens, { rotulo: 'Revisões nos últimos 14 dias' });
        },
        fcBaixarCsv(b) {
            const linhas = (b.dados.cartas || []).map(c => [c.frente, c.verso, c.materia || ''].map(t => String(t).replace(/[\t\r\n]+/g, ' ')).join('\t'));
            const url = URL.createObjectURL(new Blob([linhas.join('\n')], { type: 'text/tab-separated-values;charset=utf-8' }));
            const a = document.createElement('a'); a.href = url; a.download = (b.titulo || 'flashcards').replace(/[\\/:*?"<>|]+/g, '') + '.txt'; a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },

        /* ---------- Pomodoro ---------- */
        pmUI(b) { return this.esUI(b, { nova: '', manual: { min: 25, materia: '' }, cfg: false, ver: '' }); },
        /** Relógio único da página (um setInterval para todos os Pomodoros). */
        pmRelogio() {
            if (this._pmRel) return;
            this._pmRel = setInterval(() => { this.wfEsAgora = Date.now(); this.pmConferir(); }, 1000);
        },
        pmDur(b, fase) { return Math.max(1, Math.min(240, +b.dados[fase || b.dados.fase] || 1)) * 60; },
        pmResta(b) {
            void this.wfEsAgora;
            const d = b.dados;
            return d.rodando && d.fim ? Math.max(0, Math.ceil((d.fim - Date.now()) / 1000)) : (d.resta ?? this.pmDur(b));
        },
        pmTempo(b) { const s = this.pmResta(b); return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'); },
        pmFaseNome(f) { return { foco: 'Foco', curta: 'Pausa curta', longa: 'Pausa longa' }[f] || 'Foco'; },
        pmAnel(b) {
            const tot = this.pmDur(b), p = 1 - this.pmResta(b) / tot, r = 70, c = 2 * Math.PI * r;
            const cor = b.dados.fase === 'foco' ? '#ef4444' : b.dados.fase === 'curta' ? '#22c55e' : '#2f6fc7';
            return `<svg viewBox="0 0 160 160" class="pm-anel" aria-hidden="true"><circle cx="80" cy="80" r="${r}" class="pm-fundo"/><circle cx="80" cy="80" r="${r}" stroke="${cor}" class="pm-arco" stroke-dasharray="${c}" stroke-dashoffset="${c * (1 - Math.min(1, Math.max(0, p)))}" transform="rotate(-90 80 80)"/></svg>`;
        },
        pmComecar(b) {
            const d = b.dados;
            if (d.rodando) return;
            if (d.fase === 'foco' && !d.materia) { this.aviso('Escolha a matéria antes de começar.'); return; }
            d.fim = Date.now() + (d.resta ?? this.pmDur(b)) * 1000;
            d.rodando = true;
            if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission().catch(() => {});
            this.pmRelogio();
        },
        pmPausar(b) { const d = b.dados; if (!d.rodando) return; d.resta = this.pmResta(b); d.rodando = false; d.fim = null; },
        pmZerar(b) { const d = b.dados; d.rodando = false; d.fim = null; d.resta = this.pmDur(b); },
        pmFase(b, f) { const d = b.dados; d.fase = f; d.rodando = false; d.fim = null; d.resta = this.pmDur(b, f); },
        /** Pula para a próxima fase; "contar" registra o foco como sessão. */
        pmAvancar(b, contar) {
            const d = b.dados;
            if (d.fase === 'foco') {
                if (contar) {
                    d.sessoes ??= [];
                    d.sessoes.push({ id: uid(), d: hoje(), em: Date.now(), materia: d.materia || 'Sem matéria', min: +d.foco || 25 });
                    if (d.sessoes.length > MAX_SESSOES) d.sessoes.splice(0, d.sessoes.length - MAX_SESSOES);
                }
                d.feitos = (d.feitos || 0) + 1;
                d.fase = d.feitos % Math.max(1, +d.ciclos || 4) === 0 ? 'longa' : 'curta';
            } else d.fase = 'foco';
            d.resta = this.pmDur(b);
            d.fim = null;
            d.rodando = false;
        },
        /** Fases que terminaram (inclusive com a página fechada): registra e avisa. */
        pmConferir() {
            (this.estado.blocos || []).forEach(b => {
                if (b.tipo !== 'pomodoro' || !b.dados.rodando || !b.dados.fim || b.dados.fim > Date.now()) return;
                const fase = b.dados.fase, auto = b.dados.auto, mat = b.dados.materia;
                this.pmAvancar(b, true);
                const msg = fase === 'foco' ? `Foco em ${mat || 'estudo'} terminado! Hora da ${this.pmFaseNome(b.dados.fase).toLowerCase()}.` : 'Pausa terminada. Bora voltar ao foco!';
                this.aviso(msg);
                if (b.dados.som) this.pmSom();
                try { if ('Notification' in window && Notification.permission === 'granted' && document.hidden) new Notification('Pomodoro', { body: msg }); } catch (_) { /* sem notificação */ }
                if (auto) this.pmComecar(b);
            });
        },
        pmSom() {
            try {
                const C = window.AudioContext || window.webkitAudioContext, ctx = new C();
                [0, 0.25, 0.5].forEach(t => {
                    const o = ctx.createOscillator(), g = ctx.createGain();
                    o.frequency.value = 880; o.connect(g); g.connect(ctx.destination);
                    g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
                    g.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.2);
                    o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.22);
                });
                setTimeout(() => ctx.close(), 1200);
            } catch (_) { /* sem áudio */ }
        },
        pmNovaMateria(b) {
            const ui = this.pmUI(b), n = ui.nova.trim().slice(0, 60);
            if (!n) return;
            b.dados.materias ??= [];
            if (!b.dados.materias.includes(n)) b.dados.materias.push(n);
            b.dados.materia = n;
            ui.nova = '';
        },
        pmRemoverMateria(b, m) { b.dados.materias = (b.dados.materias || []).filter(x => x !== m); if (b.dados.materia === m) b.dados.materia = b.dados.materias[0] || ''; },
        pmRegistrar(b) {
            const ui = this.pmUI(b), min = Math.round(+ui.manual.min);
            if (!(min > 0 && min <= 600)) { this.aviso('Informe os minutos (1 a 600).'); return; }
            b.dados.sessoes ??= [];
            b.dados.sessoes.push({ id: uid(), d: hoje(), em: Date.now(), materia: ui.manual.materia || b.dados.materia || 'Sem matéria', min, manual: true });
            this.aviso(`${min} min registrados.`);
        },
        pmHoje(b) {
            const d = hoje(), por = {};
            (b.dados.sessoes || []).filter(s => s.d === d).forEach(s => { por[s.materia] = (por[s.materia] || 0) + s.min; });
            const lista = Object.entries(por).map(([m, min]) => ({ m, min })).sort((a, c) => c.min - a.min);
            return { lista, total: lista.reduce((s, x) => s + x.min, 0), n: (b.dados.sessoes || []).filter(s => s.d === d && !s.manual).length };
        },
        pmUltimas(b) { return (b.dados.sessoes || []).slice(-8).reverse(); },
        pmApagarSessao(b, s) { b.dados.sessoes = (b.dados.sessoes || []).filter(x => x.id !== s.id); },
        pmSvg(b) {
            const d = hoje(), sess = b.dados.sessoes || [];
            const itens = Array.from({ length: 7 }, (_, i) => { const k = addDias(d, i - 6); return { r: dataCurta(k), v: sess.filter(s => s.d === k).reduce((t, s) => t + s.min, 0), cor: '#ef4444' }; });
            return svgBarras(itens, { rotulo: 'Minutos de foco nos últimos 7 dias', fmt: v => minTxt(v) });
        },

        /* ---------- Plano de estudos ---------- */
        peUI(b) { return this.esUI(b, { sem: 0, nova: { texto: '', dia: 0, materia: '' }, meta: { materia: '', horas: 2 }, prova: { nome: '', data: '' } }); },
        /** Todas as sessões de estudo da página (cartões Pomodoro de qualquer aba). */
        peSessoes() {
            const out = [];
            this.estado.blocos.forEach(x => { if (x.tipo === 'pomodoro') (x.dados.sessoes || []).forEach(s => out.push(s)); });
            return out;
        },
        peSemana(b) { return addDias(segunda(), 7 * (this.peUI(b).sem || 0)); },
        peRotuloSemana(b) {
            const s = this.peSemana(b), f = addDias(s, 6), off = this.peUI(b).sem || 0;
            return (off === 0 ? 'Esta semana' : off === -1 ? 'Semana passada' : off === 1 ? 'Próxima semana' : 'Semana') + ` · ${dataCurta(s)} a ${dataCurta(f)}`;
        },
        peDias(b) {
            const s = this.peSemana(b), d = hoje();
            return DIAS.map((nome, i) => {
                const k = addDias(s, i);
                return { i, nome, data: k, hoje: k === d, tarefas: (b.dados.tarefas || []).filter(t => +t.dia === i) };
            });
        },
        peFeito(b, t) { return !!(t.feitos || {})[this.peSemana(b)]; },
        peMarcar(b, t) {
            const k = this.peSemana(b);
            t.feitos ??= {};
            if (t.feitos[k]) delete t.feitos[k]; else t.feitos[k] = 1;
            const ks = Object.keys(t.feitos).sort();
            if (ks.length > 120) ks.slice(0, ks.length - 120).forEach(x => delete t.feitos[x]);
        },
        peNovaTarefa(b) {
            const n = this.peUI(b).nova;
            if (!n.texto.trim()) return;
            b.dados.tarefas ??= [];
            if (b.dados.tarefas.length >= 200) { this.aviso('Limite de 200 tarefas no plano.'); return; }
            b.dados.tarefas.push({ id: uid(), dia: +n.dia || 0, texto: n.texto.trim().slice(0, 200), materia: n.materia || '', feitos: {} });
            n.texto = '';
        },
        peRemoverTarefa(b, t) { b.dados.tarefas = (b.dados.tarefas || []).filter(x => x.id !== t.id); },
        peCorMateria(b, m) {
            const metas = (b.dados.metas || []).map(x => x.materia), i = metas.indexOf(m);
            if (i >= 0) return CORES[i % CORES.length];
            let h = 0; for (const ch of String(m)) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
            return CORES[h % CORES.length];
        },
        /** Minutos de cada matéria na semana escolhida + progresso das metas. */
        peProgresso(b) {
            const s = this.peSemana(b), f = addDias(s, 6), por = {};
            this.peSessoes().filter(x => x.d >= s && x.d <= f).forEach(x => { por[x.materia] = (por[x.materia] || 0) + (+x.min || 0); });
            const metas = (b.dados.metas || []).map(m => {
                const feito = por[m.materia] || 0, alvo = (+m.horas || 0) * 60;
                return { ...m, feito, alvo, pct: alvo ? Math.min(100, Math.round(feito / alvo * 100)) : 0, cor: this.peCorMateria(b, m.materia) };
            });
            const fora = Object.entries(por).filter(([m]) => !metas.some(x => x.materia === m)).map(([m, feito]) => ({ materia: m, feito }));
            const total = Object.values(por).reduce((a, c) => a + c, 0), alvo = metas.reduce((a, m) => a + m.alvo, 0);
            const tarefas = b.dados.tarefas || [], feitas = tarefas.filter(t => (t.feitos || {})[s]).length;
            return { metas, fora, total, alvo, pct: alvo ? Math.min(100, Math.round(total / alvo * 100)) : 0, tarefas: tarefas.length, feitas };
        },
        peNovaMeta(b) {
            const m = this.peUI(b).meta, nome = (m.materia || '').trim().slice(0, 60);
            if (!nome) { this.aviso('Escreva a matéria.'); return; }
            b.dados.metas ??= [];
            const ja = b.dados.metas.find(x => x.materia === nome);
            if (ja) ja.horas = +m.horas || 1; else b.dados.metas.push({ id: uid(), materia: nome, horas: Math.max(0.5, +m.horas || 1) });
            m.materia = '';
        },
        peRemoverMeta(b, m) { b.dados.metas = (b.dados.metas || []).filter(x => x.id !== m.id); },
        peNovaProva(b) {
            const p = this.peUI(b).prova;
            if (!p.nome.trim() || !/^\d{4}-\d{2}-\d{2}$/.test(p.data)) { this.aviso('Informe o nome e a data da prova.'); return; }
            b.dados.provas ??= [];
            b.dados.provas.push({ id: uid(), nome: p.nome.trim().slice(0, 80), data: p.data });
            b.dados.provas.sort((a, c) => (a.data < c.data ? -1 : 1));
            p.nome = ''; p.data = '';
        },
        peRemoverProva(b, p) { b.dados.provas = (b.dados.provas || []).filter(x => x.id !== p.id); },
        peProvas(b) {
            const d = hoje();
            return (b.dados.provas || []).map(p => ({ ...p, faltam: diasEntre(d, p.data) })).filter(p => p.faltam >= -1);
        },
        peFaltamTxt(n) { return n < 0 ? 'foi ontem' : n === 0 ? 'é hoje!' : n === 1 ? 'amanhã' : `faltam ${n} dias`; },
        /** Dias seguidos (até hoje) com estudo: sessão de Pomodoro ou revisão de flashcards. */
        peSequencia() {
            const dias = new Set(this.peSessoes().map(s => s.d));
            this.estado.blocos.forEach(x => { if (x.tipo === 'flashcards') Object.entries(x.dados.hist || {}).forEach(([k, h]) => { if (h && h.n) dias.add(k); }); });
            let d = hoje(), n = 0;
            if (!dias.has(d)) d = addDias(d, -1); // hoje ainda pode estudar
            while (dias.has(d)) { n++; d = addDias(d, -1); }
            return n;
        },
        peRevisoesSemana(b) {
            const s = this.peSemana(b), f = addDias(s, 6);
            let n = 0;
            this.estado.blocos.forEach(x => { if (x.tipo === 'flashcards') Object.entries(x.dados.hist || {}).forEach(([k, h]) => { if (k >= s && k <= f) n += h.n || 0; }); });
            return n;
        },
        peSvg(b) {
            const sess = this.peSessoes(), atual = segunda(), meta = (b.dados.metas || []).reduce((a, m) => a + (+m.horas || 0), 0);
            const itens = Array.from({ length: 8 }, (_, i) => {
                const s = addDias(atual, 7 * (i - 7)), f = addDias(s, 6);
                return { r: dataCurta(s), v: Math.round(sess.filter(x => x.d >= s && x.d <= f).reduce((t, x) => t + (+x.min || 0), 0) / 6) / 10, cor: CORES[0] };
            });
            return svgBarras(itens, { rotulo: 'Horas de estudo nas últimas 8 semanas', meta, fmt: v => (Math.round(v * 10) / 10).toLocaleString('pt-BR') + 'h' });
        },

        /* ---------- Matemática ---------- */
        mtUI(b) { return this.esUI(b, { ajuda: false, cursor: null, arrasto: null, gw: 600, gh: 260, rolagem: 0 }); },
        /** Carrega o math.js (e o nerdamer quando alguma linha pede CAS). */
        mtGarantir(b) {
            const L = this.wfEsLib;
            if (!L.math && !L.carregando) {
                L.carregando = true;
                carregar(MATHJS).then(() => { L.math = true; }).catch(() => { L.erro = 'Não foi possível carregar a biblioteca de matemática. Confira a internet.'; }).finally(() => { L.carregando = false; });
            }
            if (!L.cas && !L.casCarregando && String(b.dados.texto || '').split('\n').some(l => PRECISA_CAS.test(l))) {
                L.casCarregando = true;
                carregar(NERDAMER).then(() => { L.cas = true; }).catch(() => { /* fica só com o numérico */ }).finally(() => { L.casCarregando = false; });
            }
        },
        /** Resultado de cada linha (guardado fora do Alpine; recalcula quando o texto ou as opções mudam). */
        mtCalc(b) {
            const L = this.wfEsLib, d = b.dados;
            const chave = [d.texto, d.virgula, d.graus, L.math, L.cas].join('\u0001');
            const c = CACHE_MT.get(b.id);
            if (c && c.chave === chave) return c.res;
            if (!L.math || !window.math) { this.mtGarantir(b); return { linhas: [], escopo: {}, funcs: [] }; }
            if (!L.cas) this.mtGarantir(b);
            let res;
            try { res = avaliarTudo(d.texto, d, window.math, L.cas ? window.nerdamer : null); } catch (e) { res = { linhas: [], escopo: {}, funcs: [] }; }
            CACHE_MT.set(b.id, { chave, res });
            return res;
        },
        mtLinhas(b) { return this.mtCalc(b).linhas; },
        mtCopiar(b, r) {
            if (!r || !r.txt || r.cls === 'erro' || r.cls === 'espera') return;
            const t = r.txt.replace(/^📈\s*/, '');
            (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => this.aviso('Copiado: ' + corta(t, 40))).catch(() => {});
        },
        mtInserir(b, txt) {
            const t = b.dados.texto || '';
            b.dados.texto = t + (t && !t.endsWith('\n') ? '\n' : '') + txt;
        },
        mtExemplo(b) { b.dados.texto = EXEMPLO_MAT; },
        mtMedir(b, el) {
            const ui = this.mtUI(b), ajusta = () => { const w = Math.round(el.clientWidth), h = Math.round(el.clientHeight); if (w > 20 && (w !== ui.gw || h !== ui.gh)) { ui.gw = w; ui.gh = Math.max(120, h); } };
            ajusta();
            if (window.ResizeObserver) new ResizeObserver(ajusta).observe(el);
        },
        /** Janela do gráfico com a mesma escala nos dois eixos: guarda xmin, xmax e o centro vertical (yc). */
        mtGraf(b) {
            const ui = this.mtUI(b);
            let g = ui.arrasto?.g || b.dados.graf;
            if (!g || !(g.xmax > g.xmin)) g = { xmin: -8, xmax: 8, yc: 0 };
            const yc = Number.isFinite(g.yc) ? g.yc : (Number.isFinite(g.ymin) && Number.isFinite(g.ymax) ? (g.ymin + g.ymax) / 2 : 0);
            const meia = (g.xmax - g.xmin) * ui.gh / Math.max(ui.gw, 1) / 2;
            return { xmin: g.xmin, xmax: g.xmax, yc, ymin: yc - meia, ymax: yc + meia };
        },
        mtSvg(b) {
            const L = this.wfEsLib, ui = this.mtUI(b);
            if (!L.math || !window.math) return '';
            const r = this.mtCalc(b);
            return svgFuncoes(r.funcs, r.escopo, this.mtGraf(b), ui.gw, ui.gh, window.math, ui.cursor);
        },
        mtPonto(b, ev) {
            const el = ev.currentTarget, rc = el.getBoundingClientRect(), g = this.mtGraf(b), ui = this.mtUI(b);
            return { x: g.xmin + (ev.clientX - rc.left) / ui.gw * (g.xmax - g.xmin), y: g.ymax - (ev.clientY - rc.top) / ui.gh * (g.ymax - g.ymin), px: ev.clientX, py: ev.clientY };
        },
        mtBaixo(b, ev) {
            if (ev.button !== 0) return;
            ev.currentTarget.setPointerCapture?.(ev.pointerId);
            this.mtUI(b).arrasto = { px: ev.clientX, py: ev.clientY, g0: { ...this.mtGraf(b) }, g: { ...this.mtGraf(b) } };
        },
        mtMover(b, ev) {
            const ui = this.mtUI(b), a = ui.arrasto;
            if (a) {
                const dx = (ev.clientX - a.px) / ui.gw * (a.g0.xmax - a.g0.xmin), dy = (ev.clientY - a.py) / ui.gh * (a.g0.ymax - a.g0.ymin);
                ui.arrasto = { ...a, g: { xmin: a.g0.xmin - dx, xmax: a.g0.xmax - dx, yc: a.g0.yc + dy } };
                ui.cursor = null;
                return;
            }
            ui.cursor = { x: this.mtPonto(b, ev).x };
        },
        mtSoltar(b) { const ui = this.mtUI(b); if (ui.arrasto) { b.dados.graf = ui.arrasto.g; ui.arrasto = null; } },
        mtZoom(b, ev, fator) {
            const g = this.mtGraf(b), f = fator || (ev.deltaY > 0 ? 1.15 : 1 / 1.15);
            const p = ev && ev.currentTarget && ev.clientX != null ? this.mtPonto(b, ev) : { x: (g.xmin + g.xmax) / 2, y: (g.ymin + g.ymax) / 2 };
            const nova = { xmin: p.x - (p.x - g.xmin) * f, xmax: p.x + (g.xmax - p.x) * f, yc: p.y + (g.yc - p.y) * f };
            if (nova.xmax - nova.xmin < 1e-6 || nova.xmax - nova.xmin > 1e7) return;
            b.dados.graf = nova;
        },
        mtCentralizar(b) { b.dados.graf = { xmin: -8, xmax: 8, yc: 0 }; },
        mtStatus() {
            const L = this.wfEsLib;
            return L.erro || (L.carregando ? 'Carregando a calculadora…' : L.casCarregando ? 'Carregando integrais e equações…' : '');
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco)) TIPOS.forEach(t => { if (!this.tiposBloco.some(x => x.id === t.id)) this.tiposBloco.push(t); });
            const r = prep.apply(this, arguments);
            if (primeira && typeof this.novoBloco === 'function') {
                const novo = this.novoBloco;
                this.novoBloco = function (sec, tipo, ...resto) {
                    if (tipo === 'flashcards') return this.novoFlashcards(sec);
                    if (tipo === 'pomodoro') return this.novoPomodoro(sec);
                    if (tipo === 'planoestudo') return this.novoPlanoEstudo(sec);
                    if (tipo === 'matematica') return this.novoMatematica(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            // Pomodoro rodando quando a página abriu: liga o relógio (e registra o que terminou com a página fechada)
            if (primeira && (this.estado.blocos || []).some(b => b.tipo === 'pomodoro')) setTimeout(() => this.pmRelogio(), 500);
            return r;
        };
        return Object.assign(m, extras, { wfEsUI: {}, wfEsAgora: Date.now(), wfEsLib: { math: false, cas: false, carregando: false, casCarregando: false, erro: '' } });
    };
    window.WFEstudo = { agendar, lerImportacao, traduzir, avaliarTudo, segunda, raizes, simpson };
})();
