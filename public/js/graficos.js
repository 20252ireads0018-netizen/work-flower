/* Work Flower · cartões "Gráfico" e "Hábitos" (Corpo, Mente, Carteira e Diverso)
   Estende o WFQuadro.metodos SEM mexer no quadro.js: carregue depois do quadro.js e antes do Alpine iniciar.
   - Gráfico: barras, barras empilhadas, linhas, área, pizza ou rosca. Dados digitados no cartão ou de outro cartão
     da página (Tabela, Hábitos, Questões, RH, Criador), sempre ao vivo. Os valores mudam com animação.
     b.dados = { tipo, fonte: 'manual'|'tabela'|'habitos'|'questoes'|'rh'|'criador', ref, cols, cab, linhas,
                 eixoX, eixoY, meta, metaNome, alerta: ''|'acima'|'abaixo'|'ambos', alertaEstado,
                 diario: bool, diarioValor: 'total'|'media'|'ultimo', hist: { 'AAAA-MM-DD': n }, verHist: bool }
   - Hábitos: um hábito por linha, um dia por coluna; marcar o dia conta para a porcentagem do mês e a sequência.
     b.dados = { habitos: [{ id, nome, cor, meta (vezes por semana), hora ('HH:MM' do lembrete), evento (id no calendário),
                             auto (trecho do nome da tarefa que marca o dia sozinha), autoFeitos: { idTarefa: 1 } }],
                 feitos: { idDoHabito: { 'AAAA-MM-DD': 1 } }, mes: 'AAAA-MM' }
   Desenhos: public/js/graficos.js gera o SVG (texto escapado) e os partials/grafico-cartao e habitos-cartao mostram. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid, iso, snap } = Q.util;
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const CORES = ['#2f6fc7', '#2dd4bf', '#f2c230', '#fb923c', '#f472b6', '#a78bfa', '#4ade80', '#94a3b8'];
    const TIPOS = [{ id: 'grafico', nome: 'Gráfico' }, { id: 'habitos', nome: 'Hábitos' }];
    const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const SEM = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    const MAX_LINHAS = 200, MAX_SERIES = 8, MAX_HABITOS = 40, MAX_HIST = 400;
    const SELOS = [[3, 'Começou bem'], [7, 'Uma semana'], [14, 'Duas semanas'], [30, 'Um mês'], [60, 'Dois meses'], [100, 'Cem dias'], [365, 'Um ano']];
    const ETAPAS_RH = ['Triagem', 'Entrevista', 'Teste', 'Proposta', 'Contratado', 'Recusado'];
    const ETAPAS_POST = ['Ideia', 'Roteiro', 'Gravado', 'Editado', 'Agendado', 'Publicado'];
    // endereços vindos da tag do script (o site pode estar numa subpasta)
    const TAG = document.currentScript ? document.currentScript.dataset : {};
    const URL_CAL = TAG.cal || '', URL_TAREFAS = TAG.tarefas || '';
    const semAnimacao = () => window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /** "R$ 1.234,56", "12%", "3.5" → número (ou null se não for número). */
    function numero(v) {
        if (typeof v === 'number') return Number.isFinite(v) ? v : null;
        let s = String(v == null ? '' : v).replace(/[^\d,.\-]/g, '');
        if (!s || s === '-') return null;
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        else if ((s.match(/\./g) || []).length > 1) s = s.replace(/\./g, '');
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : null;
    }
    const curto = n => {
        const a = Math.abs(n);
        if (a >= 1e9) return (n / 1e9).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' bi';
        if (a >= 1e6) return (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
        if (a >= 1e4) return (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil';
        return n.toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    };
    const corte = (s, n) => (String(s).length > n ? String(s).slice(0, n - 1) + '…' : String(s));
    const semAcento = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    /** Passo "bonito" para as linhas de grade (1, 2, 5 × 10^n). */
    function passoBonito(max) {
        if (max <= 0) return 1;
        const bruto = max / 4, mag = Math.pow(10, Math.floor(Math.log10(bruto))), f = bruto / mag;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * mag;
    }
    /** Área da página (para as tarefas): sai do endereço onde o quadro salva (/corpo/dados, /negocios/dados...). */
    function areaDoQuadro(vm) {
        const m = String((vm.cfg && vm.cfg.url) || '').match(/\/(corpo|mente|carteira|negocios|diverso)\/dados/);
        return m ? m[1] : 'diverso';
    }
    /** fetch com o token do Laravel e o dono do workflow compartilhado (?de=). Devolve { ok, status, json }. */
    async function pedir(url, metodo = 'GET', corpo) {
        const de = new URLSearchParams(location.search).get('de');
        const cab = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' };
        if (de) cab['X-WF-Dono'] = de;
        if (corpo) cab['Content-Type'] = 'application/json';
        try {
            const r = await fetch(url, { method: metodo, credentials: 'same-origin', headers: cab, body: corpo ? JSON.stringify(corpo) : undefined });
            const j = await r.json().catch(() => ({}));
            return { ok: r.ok, status: r.status, json: j };
        } catch (e) { return { ok: false, status: 0, json: { message: 'Sem conexão com o servidor.' } }; }
    }

    /* ---------- hábitos: contas ---------- */
    function diasDoMes(mes) {
        const [a, m] = mes.split('-').map(Number), n = new Date(a, m, 0).getDate(), hoje = iso(new Date());
        return Array.from({ length: n }, (_, i) => {
            const d = new Date(a, m - 1, i + 1), k = iso(d);
            return { n: i + 1, iso: k, sem: SEM[d.getDay()], dsem: d.getDay(), fds: d.getDay() === 0 || d.getDay() === 6, futuro: k > hoje, hoje: k === hoje };
        });
    }
    function pctHabito(dados, h, mes) {
        const dias = diasDoMes(mes).filter(d => !d.futuro);
        if (!dias.length) return 0;
        const f = (dados.feitos || {})[h.id] || {};
        return Math.round(dias.filter(d => f[d.iso]).length / dias.length * 100);
    }
    /** Dias seguidos até hoje (se hoje ainda não foi marcado, conta até ontem). */
    function sequencia(dados, h) {
        const f = (dados.feitos || {})[h.id] || {};
        const d = new Date();
        if (!f[iso(d)]) d.setDate(d.getDate() - 1);
        let n = 0;
        while (f[iso(d)] && n < 3660) { n++; d.setDate(d.getDate() - 1); }
        return n;
    }
    /** Maior sequência já feita. */
    function melhorSequencia(dados, h) {
        const dias = Object.keys((dados.feitos || {})[h.id] || {}).sort();
        let melhor = 0, atual = 0, ant = null;
        dias.forEach(k => {
            const d = new Date(k + 'T00:00');
            atual = ant && Math.round((d - ant) / 864e5) === 1 ? atual + 1 : 1;
            melhor = Math.max(melhor, atual);
            ant = d;
        });
        return melhor;
    }
    /** Vezes feitas na semana de hoje (domingo a sábado). */
    function naSemana(dados, h) {
        const f = (dados.feitos || {})[h.id] || {}, d = new Date();
        d.setDate(d.getDate() - d.getDay());
        let n = 0;
        for (let i = 0; i < 7; i++) { if (f[iso(d)]) n++; d.setDate(d.getDate() + 1); }
        return n;
    }

    /* ---------- gráfico: SVG ---------- */
    /** Mínimo e máximo do eixo (múltiplos de um passo "bonito"), contando a meta e as pilhas das barras empilhadas. */
    function escalaDe(tipo, series, metaTxt) {
        let lim = series.flatMap(s => s.valores.filter(v => v != null));
        if (tipo === 'empilhadas' && series.length > 1) {
            lim = [];
            const n = Math.max(0, ...series.map(s => s.valores.length));
            for (let i = 0; i < n; i++) {
                let pos = 0, neg = 0;
                series.forEach(s => { const v = s.valores[i]; if (v > 0) pos += v; else if (v < 0) neg += v; });
                lim.push(pos, neg);
            }
        }
        const meta = numero(metaTxt);
        let min = Math.min(0, ...lim, meta ?? 0), max = Math.max(0, ...lim, meta ?? 0);
        if (min === max) max = min + 1;
        const passo = passoBonito(max - min);
        return { min: Math.floor(min / passo) * passo, max: Math.ceil(max / passo) * passo };
    }
    /**
     * tipo: barras | empilhadas | linhas | area | pizza | rosca
     * op: { eixoX, eixoY, meta, metaNome } — títulos dos eixos e linha de meta (não vale para pizza/rosca)
     */
    function svgGrafico(tipo, rotulos, series, op = {}) {
        const W = 440, H = 250;
        const vals = series.flatMap(s => s.valores.filter(v => v != null));
        if (!rotulos.length || !series.length || !vals.length) {
            return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico vazio"><text x="${W / 2}" y="${H / 2}" text-anchor="middle" class="gr-vazio">Sem números para mostrar ainda</text></svg>`;
        }
        if (tipo === 'pizza' || tipo === 'rosca') {
            const s = series[0], fatias = rotulos.map((r, i) => ({ r, v: Math.max(0, s.valores[i] || 0), cor: CORES[i % CORES.length] })).filter(f => f.v > 0.0001);
            const total = fatias.reduce((t, f) => t + f.v, 0);
            if (!total) return svgGrafico('barras', rotulos, series, op);
            const cx = 120, cy = H / 2, R = 100, r0 = tipo === 'rosca' ? 56 : 0;
            let ang = -Math.PI / 2, g = '';
            const p = (rr, t) => `${(cx + rr * Math.cos(t)).toFixed(2)} ${(cy + rr * Math.sin(t)).toFixed(2)}`;
            fatias.forEach(f => {
                const a = f.v / total * Math.PI * 2, a2 = ang + a, grande = a > Math.PI ? 1 : 0;
                let d;
                if (fatias.length === 1) {
                    d = `M${cx - R} ${cy}a${R} ${R} 0 1 0 ${2 * R} 0a${R} ${R} 0 1 0 ${-2 * R} 0` + (r0 ? `M${cx - r0} ${cy}a${r0} ${r0} 0 1 1 ${2 * r0} 0a${r0} ${r0} 0 1 1 ${-2 * r0} 0z` : 'z');
                } else if (r0) {
                    d = `M${p(R, ang)}A${R} ${R} 0 ${grande} 1 ${p(R, a2)}L${p(r0, a2)}A${r0} ${r0} 0 ${grande} 0 ${p(r0, ang)}z`;
                } else {
                    d = `M${cx} ${cy}L${p(R, ang)}A${R} ${R} 0 ${grande} 1 ${p(R, a2)}z`;
                }
                g += `<path d="${d}" fill="${f.cor}" fill-rule="evenodd" class="gr-fatia"><title>${esc(f.r)}: ${esc(curto(f.v))} (${Math.round(f.v / total * 100)}%)</title></path>`;
                ang = a2;
            });
            if (r0) g += `<text x="${cx}" y="${cy - 2}" text-anchor="middle" class="gr-total">${esc(curto(total))}</text><text x="${cx}" y="${cy + 14}" text-anchor="middle" class="gr-eixo">${esc(corte(s.nome, 16))}</text>`;
            const leg = fatias.slice(0, 10).map((f, i) => `<rect x="250" y="${22 + i * 21}" width="11" height="11" rx="2" fill="${f.cor}"/><text x="267" y="${32 + i * 21}" class="gr-leg">${esc(corte(f.r, 18))} · ${Math.round(f.v / total * 100)}%</text>`).join('');
            return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico de ${tipo}">${g}${leg}${fatias.length > 10 ? `<text x="250" y="${32 + 10 * 21}" class="gr-eixo">+ ${fatias.length - 10}</text>` : ''}</svg>`;
        }
        const legenda = series.length > 1 ? 18 : 0;
        const L = 46 + (op.eixoY ? 14 : 0), Rm = 10, T = 12 + legenda, B = 34 + (op.eixoX ? 14 : 0), w = W - L - Rm, h = H - T - B;
        const n = rotulos.length, empilhar = tipo === 'empilhadas' && series.length > 1;
        const meta = numero(op.meta);
        // escala: a da animação (que anda junto com os valores, sem pulo) ou a "bonita" calculada aqui
        const { min, max } = op.escala || escalaDe(tipo, series, op.meta);
        const passo = passoBonito(max - min);
        const y = v => T + h - (v - min) / (max - min) * h;
        let g = '';
        for (let v = Math.ceil(min / passo - 1e-9) * passo; v <= max + passo / 1e6; v += passo) {
            g += `<line x1="${L}" x2="${W - Rm}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" class="gr-grade${Math.abs(v) < passo / 1e6 ? ' zero' : ''}"/><text x="${L - 6}" y="${(y(v) + 3.5).toFixed(1)}" text-anchor="end" class="gr-eixo">${esc(curto((Math.round(v * 1e6) / 1e6) || 0))}</text>`;
        }
        const faixa = w / n, cadaRot = Math.max(1, Math.ceil(n / Math.floor(w / 46)));
        rotulos.forEach((r, i) => {
            if (i % cadaRot) return;
            g += `<text x="${(L + faixa * (i + 0.5)).toFixed(1)}" y="${T + h + 16}" text-anchor="middle" class="gr-eixo">${esc(corte(r, Math.max(4, Math.floor(faixa * cadaRot / 6.5))))}</text>`;
        });
        if (op.eixoX) g += `<text x="${(L + w / 2).toFixed(1)}" y="${H - 4}" text-anchor="middle" class="gr-tit-eixo">${esc(corte(op.eixoX, 50))}</text>`;
        if (op.eixoY) g += `<text transform="translate(11 ${(T + h / 2).toFixed(1)}) rotate(-90)" text-anchor="middle" class="gr-tit-eixo">${esc(corte(op.eixoY, 34))}</text>`;
        if (tipo === 'linhas' || tipo === 'area') {
            const base = y(Math.max(min, Math.min(0, max)));
            series.forEach(s => {
                const pts = s.valores.map((v, i) => (v == null ? null : [L + faixa * (i + 0.5), y(v)]));
                let d = '', novo = true;
                const trechos = [];
                pts.forEach(p => {
                    if (!p) { novo = true; return; }
                    if (novo) trechos.push([]);
                    trechos[trechos.length - 1].push(p);
                    d += (novo ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); novo = false;
                });
                if (tipo === 'area') {
                    trechos.forEach(t => {
                        const a = 'M' + t[0][0].toFixed(1) + ' ' + base.toFixed(1) + t.map(p => 'L' + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join('') + 'L' + t[t.length - 1][0].toFixed(1) + ' ' + base.toFixed(1) + 'z';
                        g += `<path d="${a}" fill="${s.cor}" class="gr-area-fill"/>`;
                    });
                }
                g += `<path d="${d}" fill="none" stroke="${s.cor}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>`;
                pts.forEach((p, i) => { if (p) g += `<circle cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="${n > 40 ? 1.8 : 3.4}" fill="${s.cor}" class="gr-ponto"><title>${esc(rotulos[i])} · ${esc(s.nome)}: ${esc(curto(s.valores[i]))}</title></circle>`; });
            });
        } else if (empilhar) {
            const larg = Math.max(2, Math.min(48, faixa * 0.7));
            for (let i = 0; i < n; i++) {
                let pos = 0, neg = 0;
                const x = L + faixa * (i + 0.5) - larg / 2;
                series.forEach(s => {
                    const v = s.valores[i];
                    if (v == null || v === 0) return;
                    const de = v > 0 ? pos : neg, ate = de + v;
                    if (v > 0) pos = ate; else neg = ate;
                    const y0 = y(de), y1 = y(ate);
                    g += `<rect x="${x.toFixed(1)}" y="${Math.min(y0, y1).toFixed(1)}" width="${larg.toFixed(1)}" height="${Math.max(0.5, Math.abs(y1 - y0)).toFixed(1)}" fill="${s.cor}" class="gr-barra"><title>${esc(rotulos[i])} · ${esc(s.nome)}: ${esc(curto(v))}</title></rect>`;
                });
            }
        } else {
            const k = series.length, larg = Math.max(2, Math.min(42, faixa * 0.78 / k));
            series.forEach((s, j) => s.valores.forEach((v, i) => {
                if (v == null) return;
                const x = L + faixa * (i + 0.5) - larg * k / 2 + larg * j, y0 = y(0), y1 = y(v);
                g += `<rect x="${x.toFixed(1)}" y="${Math.min(y0, y1).toFixed(1)}" width="${(larg - 1.5).toFixed(1)}" height="${Math.max(0.8, Math.abs(y1 - y0)).toFixed(1)}" rx="2" fill="${s.cor}" class="gr-barra"><title>${esc(rotulos[i])} · ${esc(s.nome)}: ${esc(curto(v))}</title></rect>`;
            }));
        }
        if (meta != null) {
            const ym = y(meta).toFixed(1);
            g += `<line x1="${L}" x2="${W - Rm}" y1="${ym}" y2="${ym}" class="gr-meta"/><text x="${W - Rm - 2}" y="${(+ym - 4).toFixed(1)}" text-anchor="end" class="gr-meta-txt">${esc(corte(op.metaNome || 'Meta', 20))}: ${esc(curto(meta))}</text>`;
        }
        if (legenda) {
            let x = L;
            series.forEach(s => {
                g += `<rect x="${x}" y="4" width="10" height="10" rx="2" fill="${s.cor}"/><text x="${x + 14}" y="13" class="gr-leg">${esc(corte(s.nome, 16))}</text>`;
                x += 24 + Math.min(16, String(s.nome).length) * 6.3;
            });
        }
        const nome = { linhas: 'linhas', area: 'área', empilhadas: 'barras empilhadas' }[tipo] || 'barras';
        return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico de ${nome}">${g}</svg>`;
    }

    /* ---------- animação: os valores andam do desenho anterior até o novo ---------- */
    const ANIM = new Map(); // id do cartão → { atual: [[valores]], raf }
    function interpolar(de, para, t) {
        return para.map((s, j) => ({
            ...s,
            valores: s.valores.map((v, i) => {
                if (v == null) return null;
                const a = de && de[j] && de[j][i] != null ? de[j][i] : 0;
                return a + (v - a) * t;
            }),
        }));
    }

    /* ---------- exportar ---------- */
    const PROPS = ['fill', 'stroke', 'stroke-width', 'stroke-dasharray', 'opacity', 'font-size', 'font-weight', 'font-family', 'fill-opacity'];
    async function svgParaPng(svg, nome) {
        const copia = svg.cloneNode(true), orig = svg.querySelectorAll('*'), novos = copia.querySelectorAll('*');
        orig.forEach((el, i) => {
            const cs = getComputedStyle(el);
            PROPS.forEach(p => { const v = cs.getPropertyValue(p); if (v) novos[i].style.setProperty(p, v); });
        });
        const vb = svg.viewBox.baseVal, esc2 = 3, W = vb.width * esc2, H = vb.height * esc2;
        copia.setAttribute('width', W); copia.setAttribute('height', H);
        copia.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        // fundo: a primeira cor de fundo de verdade acima do gráfico (o cartão), para o texto continuar legível no tema escuro
        let fundo = '', el = svg;
        while (el && el !== document.documentElement) {
            const cor = getComputedStyle(el).backgroundColor;
            if (cor && cor !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(cor)) { fundo = cor; break; }
            el = el.parentElement;
        }
        const url = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(copia)], { type: 'image/svg+xml;charset=utf-8' }));
        const img = new Image();
        await new Promise((ok, erro) => { img.onload = ok; img.onerror = erro; img.src = url; });
        const cv = document.createElement('canvas');
        cv.width = W; cv.height = H;
        const ctx = cv.getContext('2d');
        ctx.fillStyle = fundo && fundo !== 'rgba(0, 0, 0, 0)' ? fundo : '#ffffff';
        ctx.fillRect(0, 0, W, H);
        ctx.drawImage(img, 0, 0, W, H);
        URL.revokeObjectURL(url);
        const blob = await new Promise(ok => cv.toBlob(ok, 'image/png'));
        baixar(blob, nome + '.png');
    }
    function baixar(blob, nome) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = nome.replace(/[\\/:*?"<>|]+/g, '-');
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    }

    /* ---------- tarefas da área (para os hábitos que marcam sozinhos) ---------- */
    const TAREFAS = {}; // área → { em, lista, pedindo }
    async function tarefasDaArea(area, forcar) {
        const c = (TAREFAS[area] ??= { em: 0, lista: [], pedindo: null });
        if (!URL_TAREFAS) return c.lista;
        if (!forcar && Date.now() - c.em < 60000) return c.lista;
        if (c.pedindo) return c.pedindo;
        c.pedindo = pedir(URL_TAREFAS + '/' + area).then(r => { if (r.ok) { c.lista = r.json.tarefas || []; c.em = Date.now(); } c.pedindo = null; return c.lista; });
        return c.pedindo;
    }

    const extras = {
        /* ---------- criar ---------- */
        novoGrafico(sec) {
            return this.wfNovoCartao(sec, 'grafico', 'Gráfico', 480, 360, {
                tipo: 'barras', fonte: 'manual', ref: null, cols: null, eixoX: '', eixoY: '', meta: '', metaNome: 'Meta', alerta: '', diario: false, diarioValor: 'total', hist: {},
                cab: ['Mês', 'Valor'], linhas: [['Jan', '12'], ['Fev', '19'], ['Mar', '8'], ['Abr', '15']],
            }, 'Gráfico adicionado com dados de exemplo. Clique em “Dados” para trocar.');
        },
        novoHabitos(sec) {
            const d = new Date();
            return this.wfNovoCartao(sec, 'habitos', 'Hábitos', 720, 340, {
                habitos: [{ id: uid(), nome: 'Beber água', cor: CORES[0], meta: 7 }, { id: uid(), nome: 'Ler 10 páginas', cor: CORES[1], meta: 5 }],
                feitos: {}, mes: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
            }, 'Hábitos adicionado. Clique num dia para marcar que fez.');
        },
        wfNovoCartao(sec, tipo, titulo, w, h, dados, msg) {
            sec = sec || this.aba;
            if (!sec) return null;
            const id = 'b' + uid(), aberta = this.pastaAberta(sec), { x0 } = this.areaUtil();
            const bloco = { id, secao: sec, tipo, titulo, links: [], dados };
            if (aberta) bloco.pasta = aberta;
            this.estado.blocos.push(bloco);
            if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
            this.estado.layout[sec][id] = { x: snap(x0), y: this.fimDaVista(sec, aberta), w, h, z: ++this.zTop };
            this.menuBloco = false;
            this.aviso(msg);
            return id;
        },

        /* ---------- gráfico ---------- */
        grUI(b) { this.wfGrUI[b.id] ??= { editar: false, painel: 'dados' }; return this.wfGrUI[b.id]; },
        grTipos() {
            return [{ id: 'barras', nome: 'Barras' }, { id: 'empilhadas', nome: 'Barras empilhadas' }, { id: 'linhas', nome: 'Linhas' },
                { id: 'area', nome: 'Área' }, { id: 'pizza', nome: 'Pizza' }, { id: 'rosca', nome: 'Rosca' }];
        },
        /** Cartões que podem alimentar o gráfico (de qualquer aba desta página). */
        grFontes(b) {
            const nomes = { tabela: 'Tabela', habitos: 'Hábitos', questoes: 'Questões', rh: 'RH', criador: 'Criador' };
            return this.estado.blocos.filter(x => x.id !== b.id && nomes[x.tipo])
                .map(x => ({ id: x.id, tipo: x.tipo, nome: nomes[x.tipo] + ': ' + (x.titulo || 'sem título') }));
        },
        grEscolherFonte(b, v) {
            if (v === 'manual') { b.dados.fonte = 'manual'; b.dados.ref = null; return; }
            const x = this.blocoPorId(v);
            if (!x) return;
            b.dados.fonte = x.tipo;
            b.dados.ref = x.id;
            b.dados.cols = null;
            b.dados.diarioValor = x.tipo === 'habitos' || x.tipo === 'questoes' ? 'media' : 'total';
        },
        /** { rotulos, series: [{ nome, cor, valores }] } conforme a fonte. */
        grDados(b) {
            const d = b.dados || {};
            const outro = tipo => { const x = this.blocoPorId(d.ref); return x && x.tipo === tipo ? x : null; };
            if (d.fonte === 'habitos') {
                const h = outro('habitos');
                if (!h) return { rotulos: [], series: [], falta: true };
                const mes = h.dados.mes || iso(new Date()).slice(0, 7), lista = (h.dados.habitos || []).slice(0, MAX_LINHAS);
                return { rotulos: lista.map(x => x.nome || 'Hábito'), series: [{ nome: '% feito em ' + MESES[+mes.slice(5) - 1], cor: CORES[0], valores: lista.map(x => pctHabito(h.dados, x, mes)) }], cab: ['Hábito', '% feito'] };
            }
            if (d.fonte === 'questoes') {
                const q = outro('questoes');
                if (!q) return { rotulos: [], series: [], falta: true };
                const r = q.dados.resp || {}, por = {};
                (q.dados.questoes || []).forEach(x => {
                    if (!(x.id in r)) return;
                    const m = x.materia || 'Sem matéria';
                    (por[m] ??= { ok: 0, n: 0 }).n++;
                    if (r[x.id] === x.certa) por[m].ok++;
                });
                const ms = Object.keys(por).sort();
                return { rotulos: ms, series: [{ nome: '% de acerto', cor: CORES[6], valores: ms.map(m => Math.round(por[m].ok / por[m].n * 100)) }], cab: ['Matéria', '% de acerto'] };
            }
            if (d.fonte === 'rh' || d.fonte === 'criador') {
                const x = outro(d.fonte);
                if (!x) return { rotulos: [], series: [], falta: true };
                const etapas = d.fonte === 'rh' ? ETAPAS_RH : ETAPAS_POST, itens = d.fonte === 'rh' ? (x.dados.cands || []) : (x.dados.posts || []);
                return { rotulos: etapas, series: [{ nome: d.fonte === 'rh' ? 'Candidatos' : 'Posts', cor: CORES[d.fonte === 'rh' ? 5 : 4], valores: etapas.map(e => itens.filter(i => i.etapa === e).length) }], cab: ['Etapa', 'Quantidade'] };
            }
            let cab = d.cab || [], linhas = d.linhas || [];
            if (d.fonte === 'tabela') {
                const t = outro('tabela');
                if (!t) return { rotulos: [], series: [], falta: true };
                cab = (t.dados.cab || []).map(c => (c && typeof c === 'object' ? c.t ?? c.v ?? '' : c));
                linhas = (t.dados.linhas || []).map(l => l.map(c => (c && typeof c === 'object' ? c.v ?? c.t ?? '' : c)));
            }
            linhas = linhas.filter(l => Array.isArray(l) && l.some(c => String(c ?? '').trim() !== '')).slice(0, MAX_LINHAS);
            let cols = Array.isArray(d.cols) && d.fonte === 'tabela' ? d.cols : null;
            if (!cols) cols = cab.map((_, j) => j).filter(j => j > 0 && linhas.some(l => numero(l[j]) != null));
            cols = cols.filter(j => j > 0 && j < cab.length).slice(0, MAX_SERIES);
            return {
                rotulos: linhas.map((l, i) => String(l[0] ?? '').trim() || String(i + 1)),
                series: cols.map((j, k) => ({ nome: String(cab[j] || 'Série ' + j), cor: CORES[k % CORES.length], valores: linhas.map(l => numero(l[j])) })),
                cab,
            };
        },
        /** Um número que resume o gráfico (para o histórico diário e o alerta de meta). */
        grValor(b, dados) {
            const vals = ((dados || this.grDados(b)).series[0] || { valores: [] }).valores.filter(v => v != null);
            if (!vals.length) return null;
            const modo = b.dados.diarioValor || 'total';
            const v = modo === 'media' ? vals.reduce((s, x) => s + x, 0) / vals.length : modo === 'ultimo' ? vals[vals.length - 1] : vals.reduce((s, x) => s + x, 0);
            return Math.round(v * 100) / 100;
        },
        grValorNome(b) { return { total: 'total', media: 'média', ultimo: 'último valor' }[b.dados.diarioValor || 'total']; },
        /** O que o gráfico mostra agora: os dados da fonte ou a evolução diária guardada. */
        grVista(b) {
            const base = this.grDados(b);
            if (!b.dados.verHist) return base;
            const h = b.dados.hist || {}, dias = Object.keys(h).sort().slice(-60);
            return { rotulos: dias.map(k => k.slice(8, 10) + '/' + k.slice(5, 7)), series: [{ nome: 'Evolução (' + this.grValorNome(b) + ')', cor: CORES[0], valores: dias.map(k => h[k]) }], falta: base.falta, cab: ['Dia', 'Valor'] };
        },
        /** Automação: guarda o valor do dia e avisa quando cruza a meta. Roda sempre que os dados mudam. */
        grAutomatizar(b, dados) {
            const d = b.dados;
            if (dados.falta) return;
            const v = this.grValor(b, dados);
            if (d.diario && v != null) {
                const hoje = iso(new Date());
                d.hist ??= {};
                if (d.hist[hoje] !== v) {
                    d.hist[hoje] = v;
                    const ks = Object.keys(d.hist).sort();
                    if (ks.length > MAX_HIST) ks.slice(0, ks.length - MAX_HIST).forEach(k => delete d.hist[k]);
                }
            }
            const meta = numero(d.meta);
            if (!d.alerta || meta == null || v == null) return;
            const estado = v >= meta ? 'acima' : 'abaixo';
            if (d.alertaEstado === estado) return;
            const antes = d.alertaEstado;
            d.alertaEstado = estado;
            if (!antes || (d.alerta !== 'ambos' && d.alerta !== estado)) return;
            const txt = `${b.titulo || 'Gráfico'}: o ${this.grValorNome(b)} (${curto(v)}) ${estado === 'acima' ? 'chegou na' : 'ficou abaixo da'} meta de ${curto(meta)}.`;
            this.aviso(txt);
            try { if (window.Notification && Notification.permission === 'granted') new Notification('Work Flower', { body: txt }); } catch (e) { /* sem notificação */ }
        },
        grPedirNotificacao() {
            if (!window.Notification) { this.aviso('Este navegador não mostra notificações. O aviso aparece aqui na página.'); return; }
            Notification.requestPermission().then(p => this.aviso(p === 'granted' ? 'Pronto: o aviso também aparece como notificação.' : 'Sem permissão: o aviso aparece só aqui na página.'));
        },
        grMetaStatus(b) {
            const meta = numero(b.dados.meta), v = this.grValor(b, this.grDados(b));
            if (meta == null || v == null) return null;
            return { ok: v >= meta, txt: v >= meta ? `Meta atingida (${this.grValorNome(b)} ${curto(v)})` : `Faltam ${curto(meta - v)} para a meta` };
        },
        grOpcoes(b) { return { eixoX: b.dados.eixoX || '', eixoY: b.dados.eixoY || '', meta: b.dados.meta, metaNome: b.dados.metaNome || 'Meta' }; },
        /** Desenha no elemento com animação (chamado por x-effect: roda de novo sempre que os dados mudam). */
        grDesenhar(b, el) {
            const dados = this.grVista(b), tipo = b.dados.tipo || 'barras', op = this.grOpcoes(b);
            this.grAutomatizar(b, this.grDados(b));
            if (dados.falta) {
                el.innerHTML = `<svg viewBox="0 0 440 250" class="gr-svg" role="img" aria-label="Fonte não encontrada"><text x="220" y="125" text-anchor="middle" class="gr-vazio">O cartão de origem não existe mais. Escolha outra fonte em “Dados”.</text></svg>`;
                return;
            }
            const st = ANIM.get(b.id) || { atual: null, raf: 0 };
            ANIM.set(b.id, st);
            cancelAnimationFrame(st.raf);
            const alvo = dados.series.map(s => ({ nome: s.nome, cor: s.cor, valores: [...s.valores] }));
            const escAlvo = escalaDe(tipo, alvo, op.meta);
            if (semAnimacao()) { st.atual = alvo.map(s => s.valores); st.esc = escAlvo; el.innerHTML = svgGrafico(tipo, dados.rotulos, alvo, op); return; }
            // os valores e a escala do eixo andam juntos do desenho anterior até o novo
            const de = st.atual, escDe = st.esc || escAlvo, t0 = performance.now(), dur = 520;
            const quadro = agora => {
                const t = Math.min(1, (agora - t0) / dur), e = 1 - Math.pow(1 - t, 3), s = interpolar(de, alvo, e);
                st.atual = s.map(x => x.valores);
                st.esc = { min: escDe.min + (escAlvo.min - escDe.min) * e, max: escDe.max + (escAlvo.max - escDe.max) * e };
                el.innerHTML = svgGrafico(tipo, dados.rotulos, s, { ...op, escala: st.esc });
                if (t < 1) st.raf = requestAnimationFrame(quadro);
            };
            st.raf = requestAnimationFrame(quadro);
        },
        grSvg(b) { const d = this.grVista(b); return d.falta ? '' : svgGrafico(b.dados.tipo, d.rotulos, d.series, this.grOpcoes(b)); },
        async grPng(b, raiz) {
            const svg = raiz && raiz.querySelector('.gr-area svg');
            if (!svg) { this.aviso('Abra o gráfico (não os dados) para baixar a imagem.'); return; }
            try { await svgParaPng(svg, b.titulo || 'grafico'); } catch (e) { this.aviso('Não consegui gerar a imagem neste navegador.'); }
        },
        grCsv(b) {
            const { rotulos, series, cab } = this.grVista(b);
            const cel = v => { const s = v == null ? '' : typeof v === 'number' ? String(v).replace('.', ',') : String(v); return /[;"\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
            const linhas = [[(cab && cab[0]) || 'Item', ...series.map(s => s.nome)], ...rotulos.map((r, i) => [r, ...series.map(s => s.valores[i])])];
            baixar(new Blob(['﻿' + linhas.map(l => l.map(cel).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' }), (b.titulo || 'grafico') + '.csv');
        },
        grLimparHist(b) { if (confirm('Apagar a evolução diária guardada neste gráfico?')) { b.dados.hist = {}; b.dados.verHist = false; } },
        grHistN(b) { return Object.keys(b.dados.hist || {}).length; },
        /** Colunas da tabela de origem que podem virar série (para marcar e desmarcar). */
        grColunas(b) {
            if (b.dados.fonte !== 'tabela') return [];
            const { cab } = this.grDados(b), ativas = new Set(this.grDados(b).series.map(s => s.nome));
            return (cab || []).map((c, j) => ({ j, nome: String(c || 'Coluna ' + (j + 1)), on: ativas.has(String(c || 'Série ' + j)) })).filter(c => c.j > 0);
        },
        grAlternarColuna(b, j) {
            const atuais = this.grColunas(b).filter(c => c.on).map(c => c.j);
            b.dados.cols = atuais.includes(j) ? atuais.filter(x => x !== j) : [...atuais, j].sort((a, c) => a - c);
        },
        grAddLinha(b) { if ((b.dados.linhas || []).length < MAX_LINHAS) b.dados.linhas.push(b.dados.cab.map(() => '')); },
        grRemLinha(b, i) { b.dados.linhas.splice(i, 1); },
        grAddSerie(b) {
            if (b.dados.cab.length > MAX_SERIES) return;
            b.dados.cab.push('Série ' + b.dados.cab.length);
            b.dados.linhas.forEach(l => l.push(''));
        },
        grRemSerie(b, j) {
            if (b.dados.cab.length <= 2 || j < 1) return;
            b.dados.cab.splice(j, 1);
            b.dados.linhas.forEach(l => l.splice(j, 1));
        },
        grResumo(b) {
            const { rotulos, series } = this.grVista(b);
            const f = { manual: 'dados digitados', tabela: 'da tabela', habitos: 'dos hábitos', questoes: 'das questões', rh: 'do RH', criador: 'do Criador' }[b.dados.fonte] || '';
            if (b.dados.verHist) return `evolução de ${rotulos.length} ${rotulos.length === 1 ? 'dia' : 'dias'}`;
            return `${rotulos.length} ${rotulos.length === 1 ? 'item' : 'itens'} · ${series.length} ${series.length === 1 ? 'série' : 'séries'} · ${f}`;
        },
        grNomeFonte(b) { return { tabela: 'tabela', habitos: 'cartão de Hábitos', questoes: 'cartão de Questões', rh: 'cartão de RH', criador: 'cartão Criador' }[b.dados.fonte] || ''; },

        /* ---------- hábitos ---------- */
        hbUI(b) { this.wfGrUI['hb' + b.id] ??= { ver: 'grade', cfg: null, msg: '' }; return this.wfGrUI['hb' + b.id]; },
        hbDias(b) { return diasDoMes(b.dados.mes || iso(new Date()).slice(0, 7)); },
        hbRotuloMes(b) { const [a, m] = (b.dados.mes || '').split('-').map(Number); return MESES[m - 1] + ' ' + a; },
        hbMes(b, passo) {
            if (!passo) { const d = new Date(); b.dados.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; return; }
            const [a, m] = b.dados.mes.split('-').map(Number), d = new Date(a, m - 1 + passo, 1);
            b.dados.mes = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        },
        hbFeito(b, h, dia) { return !!((b.dados.feitos || {})[h.id] || {})[dia]; },
        hbAlternar(b, h, d) {
            if (d.futuro) return;
            b.dados.feitos ??= {};
            const f = (b.dados.feitos[h.id] ??= {});
            const antes = sequencia(b.dados, h);
            if (f[d.iso]) delete f[d.iso]; else f[d.iso] = 1;
            const depois = sequencia(b.dados, h), selo = SELOS.find(s => s[0] === depois);
            if (depois > antes && selo) this.aviso(`🏅 ${h.nome}: ${selo[0]} dias seguidos! Selo “${selo[1]}”.`);
        },
        hbPct(b, h) { return pctHabito(b.dados, h, b.dados.mes); },
        hbSeq(b, h) { return sequencia(b.dados, h); },
        hbMelhor(b, h) { return melhorSequencia(b.dados, h); },
        hbSemana(b, h) { return naSemana(b.dados, h); },
        hbMeta(h) { return Math.max(1, Math.min(7, +h.meta || 7)); },
        hbSemanaOk(b, h) { return naSemana(b.dados, h) >= this.hbMeta(h); },
        /** Selos já ganhos (pela maior sequência) e o próximo. */
        hbSelos(b, h) {
            const m = melhorSequencia(b.dados, h), ganhos = SELOS.filter(s => m >= s[0]), prox = SELOS.find(s => m < s[0]);
            return { ganhos: ganhos.map(s => ({ dias: s[0], nome: s[1] })), prox: prox ? { dias: prox[0], nome: prox[1], falta: prox[0] - sequencia(b.dados, h) } : null, melhor: m };
        },
        hbSeloAtual(b, h) { const s = this.hbSelos(b, h).ganhos; return s.length ? s[s.length - 1] : null; },
        hbGeral(b) {
            const l = b.dados.habitos || [];
            return l.length ? Math.round(l.reduce((s, h) => s + pctHabito(b.dados, h, b.dados.mes), 0) / l.length) : 0;
        },
        /** Mapa do mês: semanas × dias, com a fração dos hábitos feitos em cada dia. */
        hbMapa(b) {
            const dias = this.hbDias(b), l = b.dados.habitos || [], f = b.dados.feitos || {};
            const cel = dias.map(d => {
                const n = l.filter(h => (f[h.id] || {})[d.iso]).length;
                return { ...d, qt: n, total: l.length, nivel: d.futuro || !l.length ? -1 : Math.ceil(n / l.length * 4) };
            });
            const semanas = [];
            let sem = Array(dias[0].dsem).fill(null);
            cel.forEach(c => { sem.push(c); if (sem.length === 7) { semanas.push(sem); sem = []; } });
            if (sem.length) semanas.push([...sem, ...Array(7 - sem.length).fill(null)]);
            return semanas;
        },
        hbNovo(b, campo) {
            const nome = (campo.value || '').trim().slice(0, 80);
            if (!nome) return;
            b.dados.habitos ??= [];
            if (b.dados.habitos.length >= MAX_HABITOS) { this.aviso(`Limite de ${MAX_HABITOS} hábitos por cartão.`); return; }
            b.dados.habitos.push({ id: uid(), nome, cor: CORES[b.dados.habitos.length % CORES.length], meta: 7 });
            campo.value = '';
        },
        hbRemover(b, h) {
            if (!confirm(`Tirar o hábito "${h.nome}" e as marcações dele?`)) return;
            if (h.evento && URL_CAL) pedir(URL_CAL + '/' + h.evento, 'DELETE');
            b.dados.habitos = b.dados.habitos.filter(x => x.id !== h.id);
            if (b.dados.feitos) delete b.dados.feitos[h.id];
            if (this.hbUI(b).cfg === h.id) this.hbUI(b).cfg = null;
        },
        hbCor(b, h) { const i = CORES.indexOf(h.cor); h.cor = CORES[(i + 1) % CORES.length]; },
        hbAberto(b) { const id = this.hbUI(b).cfg; return (b.dados.habitos || []).find(h => h.id === id) || null; },
        /** Lembrete diário: vira um evento "todo dia" no calendário do painel, que já avisa no navegador. */
        async hbLembrete(b, h, hora) {
            const ui = this.hbUI(b);
            if (!URL_CAL) { ui.msg = 'O calendário não está disponível nesta página.'; return; }
            if (!hora) {
                if (h.evento) await pedir(URL_CAL + '/' + h.evento, 'DELETE');
                h.evento = null; h.hora = '';
                ui.msg = 'Lembrete tirado.';
                return;
            }
            if (!/^\d{2}:\d{2}$/.test(hora)) { ui.msg = 'Escolha um horário.'; return; }
            const corpo = { titulo: ('Hábito: ' + h.nome).slice(0, 120), descricao: 'Lembrete do cartão de Hábitos.', inicio: iso(new Date()) + 'T' + hora, repete: 'diario', dia_todo: false, lembrete_min: 0, lembrar_email: false, cor: h.cor };
            ui.msg = 'Salvando o lembrete…';
            let r = h.evento ? await pedir(URL_CAL + '/' + h.evento, 'PUT', corpo) : null;
            if (!r || r.status === 404) r = await pedir(URL_CAL, 'POST', corpo);
            if (r.ok) { h.evento = (r.json.evento || {}).id || h.evento; h.hora = hora; ui.msg = `Pronto: lembrete todo dia às ${hora} (aparece no calendário do painel).`; }
            else ui.msg = r.json.message || 'Não deu para criar o lembrete agora.';
        },
        /** Marca sozinho o dia em que uma tarefa da área com aquele nome foi concluída. */
        async hbAuto(b, forcar) {
            const com = (b.dados.habitos || []).filter(h => String(h.auto || '').trim());
            if (!com.length) return;
            const lista = await tarefasDaArea(areaDoQuadro(this), forcar);
            let n = 0;
            com.forEach(h => {
                const alvo = semAcento(h.auto).trim();
                h.autoFeitos ??= {};
                lista.forEach(t => {
                    if (!t.concluida_em || h.autoFeitos[t.id] || !semAcento(t.titulo).includes(alvo)) return;
                    h.autoFeitos[t.id] = 1;
                    b.dados.feitos ??= {};
                    const f = (b.dados.feitos[h.id] ??= {});
                    if (!f[t.concluida_em]) { f[t.concluida_em] = 1; n++; }
                });
                const ks = Object.keys(h.autoFeitos);
                if (ks.length > 500) ks.slice(0, ks.length - 500).forEach(k => delete h.autoFeitos[k]);
            });
            if (n) this.aviso(`${n} ${n === 1 ? 'dia marcado' : 'dias marcados'} sozinho pelas tarefas concluídas.`);
        },
        hbTarefasLigadas(b, h) {
            const alvo = semAcento(h.auto).trim(), c = TAREFAS[areaDoQuadro(this)];
            if (!alvo || !c) return [];
            return c.lista.filter(t => semAcento(t.titulo).includes(alvo)).slice(0, 5);
        },
        async hbCarregarTarefas() { await tarefasDaArea(areaDoQuadro(this), true); },
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
                    if (tipo === 'grafico') return this.novoGrafico(sec);
                    if (tipo === 'habitos') return this.novoHabitos(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            return r;
        };
        // estado da tela (modo "Dados" de cada gráfico): um objeto novo por página, para o Alpine deixar reativo
        return Object.assign(m, extras, { wfGrUI: {} });
    };
    window.WFGraficos = { numero, svgGrafico, diasDoMes, pedir, areaDoQuadro, melhorSequencia };
})();
