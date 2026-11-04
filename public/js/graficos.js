/* Work Flower · cartões "Gráfico" e "Hábitos" (Corpo, Mente, Carteira e Diverso)
   Estende o WFQuadro.metodos SEM mexer no quadro.js: carregue depois do quadro.js e antes do Alpine iniciar.
   - Gráfico: barras, linhas ou pizza. Dados digitados no cartão, de um cartão Tabela ou de um cartão Hábitos da página.
     b.dados = { tipo, fonte: 'manual'|'tabela'|'habitos', ref, cols, cab, linhas }
   - Hábitos: um hábito por linha, um dia por coluna; marcar o dia conta para a porcentagem do mês e a sequência.
     b.dados = { habitos: [{ id, nome, cor }], feitos: { idDoHabito: { 'AAAA-MM-DD': 1 } }, mes: 'AAAA-MM' }
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
    const MAX_LINHAS = 200, MAX_SERIES = 8, MAX_HABITOS = 40;

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
    /** Passo "bonito" para as linhas de grade (1, 2, 5 × 10^n). */
    function passoBonito(max) {
        if (max <= 0) return 1;
        const bruto = max / 4, mag = Math.pow(10, Math.floor(Math.log10(bruto))), f = bruto / mag;
        return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * mag;
    }

    /* ---------- hábitos: contas ---------- */
    function diasDoMes(mes) {
        const [a, m] = mes.split('-').map(Number), n = new Date(a, m, 0).getDate(), hoje = iso(new Date());
        return Array.from({ length: n }, (_, i) => {
            const d = new Date(a, m - 1, i + 1), k = iso(d);
            return { n: i + 1, iso: k, sem: SEM[d.getDay()], fds: d.getDay() === 0 || d.getDay() === 6, futuro: k > hoje, hoje: k === hoje };
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

    /* ---------- gráfico: SVG ---------- */
    function svgGrafico(tipo, rotulos, series) {
        const W = 440, H = 250;
        const vals = series.flatMap(s => s.valores.filter(v => v != null));
        if (!rotulos.length || !series.length || !vals.length) {
            return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico vazio"><text x="${W / 2}" y="${H / 2}" text-anchor="middle" class="gr-vazio">Sem números para mostrar ainda</text></svg>`;
        }
        if (tipo === 'pizza') {
            const s = series[0], fatias = rotulos.map((r, i) => ({ r, v: Math.max(0, s.valores[i] || 0), cor: CORES[i % CORES.length] })).filter(f => f.v > 0);
            const total = fatias.reduce((t, f) => t + f.v, 0);
            if (!total) return svgGrafico('barras', rotulos, series);
            const cx = 120, cy = H / 2, R = 100, r0 = 52;
            let ang = -Math.PI / 2, g = '';
            fatias.forEach(f => {
                const a = f.v / total * Math.PI * 2, a2 = ang + a, grande = a > Math.PI ? 1 : 0;
                const p = (rr, t) => `${(cx + rr * Math.cos(t)).toFixed(2)} ${(cy + rr * Math.sin(t)).toFixed(2)}`;
                const d = fatias.length === 1
                    ? `M${cx - R} ${cy}a${R} ${R} 0 1 0 ${2 * R} 0a${R} ${R} 0 1 0 ${-2 * R} 0M${cx - r0} ${cy}a${r0} ${r0} 0 1 1 ${2 * r0} 0a${r0} ${r0} 0 1 1 ${-2 * r0} 0z`
                    : `M${p(R, ang)}A${R} ${R} 0 ${grande} 1 ${p(R, a2)}L${p(r0, a2)}A${r0} ${r0} 0 ${grande} 0 ${p(r0, ang)}z`;
                g += `<path d="${d}" fill="${f.cor}" fill-rule="evenodd" class="gr-fatia"><title>${esc(f.r)}: ${esc(curto(f.v))} (${Math.round(f.v / total * 100)}%)</title></path>`;
                ang = a2;
            });
            g += `<text x="${cx}" y="${cy - 2}" text-anchor="middle" class="gr-total">${esc(curto(total))}</text><text x="${cx}" y="${cy + 14}" text-anchor="middle" class="gr-eixo">${esc(corte(s.nome, 16))}</text>`;
            const leg = fatias.slice(0, 10).map((f, i) => `<rect x="250" y="${22 + i * 21}" width="11" height="11" rx="2" fill="${f.cor}"/><text x="267" y="${32 + i * 21}" class="gr-leg">${esc(corte(f.r, 18))} · ${Math.round(f.v / total * 100)}%</text>`).join('');
            return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico de pizza">${g}${leg}${fatias.length > 10 ? `<text x="250" y="${32 + 10 * 21}" class="gr-eixo">+ ${fatias.length - 10}</text>` : ''}</svg>`;
        }
        const legenda = series.length > 1 ? 18 : 0;
        const L = 46, Rm = 10, T = 12 + legenda, B = 34, w = W - L - Rm, h = H - T - B;
        let min = Math.min(0, ...vals), max = Math.max(0, ...vals);
        if (min === max) max = min + 1;
        const passo = passoBonito(max - min);
        max = Math.ceil(max / passo) * passo;
        min = Math.floor(min / passo) * passo;
        const y = v => T + h - (v - min) / (max - min) * h;
        let g = '';
        for (let v = min; v <= max + passo / 2; v += passo) {
            g += `<line x1="${L}" x2="${W - Rm}" y1="${y(v).toFixed(1)}" y2="${y(v).toFixed(1)}" class="gr-grade${Math.abs(v) < passo / 1e6 ? ' zero' : ''}"/><text x="${L - 6}" y="${(y(v) + 3.5).toFixed(1)}" text-anchor="end" class="gr-eixo">${esc(curto(Math.round(v * 1e6) / 1e6))}</text>`;
        }
        const n = rotulos.length, faixa = w / n, cadaRot = Math.max(1, Math.ceil(n / Math.floor(w / 46)));
        rotulos.forEach((r, i) => {
            if (i % cadaRot) return;
            g += `<text x="${(L + faixa * (i + 0.5)).toFixed(1)}" y="${H - B + 16}" text-anchor="middle" class="gr-eixo">${esc(corte(r, Math.max(4, Math.floor(faixa * cadaRot / 6.5))))}</text>`;
        });
        if (tipo === 'linhas') {
            series.forEach(s => {
                const pts = s.valores.map((v, i) => (v == null ? null : [L + faixa * (i + 0.5), y(v)]));
                let d = '', novo = true;
                pts.forEach(p => { if (!p) { novo = true; return; } d += (novo ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); novo = false; });
                g += `<path d="${d}" fill="none" stroke="${s.cor}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>`;
                pts.forEach((p, i) => { if (p) g += `<circle cx="${p[0].toFixed(1)}" cy="${p[1].toFixed(1)}" r="${n > 40 ? 1.8 : 3.4}" fill="${s.cor}" class="gr-ponto"><title>${esc(rotulos[i])} · ${esc(s.nome)}: ${esc(curto(s.valores[i]))}</title></circle>`; });
            });
        } else {
            const k = series.length, larg = Math.max(2, Math.min(42, faixa * 0.78 / k));
            series.forEach((s, j) => s.valores.forEach((v, i) => {
                if (v == null) return;
                const x = L + faixa * (i + 0.5) - larg * k / 2 + larg * j, y0 = y(0), y1 = y(v);
                g += `<rect x="${x.toFixed(1)}" y="${Math.min(y0, y1).toFixed(1)}" width="${(larg - 1.5).toFixed(1)}" height="${Math.max(0.8, Math.abs(y1 - y0)).toFixed(1)}" rx="2" fill="${s.cor}" class="gr-barra"><title>${esc(rotulos[i])} · ${esc(s.nome)}: ${esc(curto(v))}</title></rect>`;
            }));
        }
        if (legenda) {
            let x = L;
            series.forEach(s => {
                g += `<rect x="${x}" y="4" width="10" height="10" rx="2" fill="${s.cor}"/><text x="${x + 14}" y="13" class="gr-leg">${esc(corte(s.nome, 16))}</text>`;
                x += 24 + Math.min(16, String(s.nome).length) * 6.3;
            });
        }
        return `<svg viewBox="0 0 ${W} ${H}" class="gr-svg" role="img" aria-label="Gráfico de ${tipo === 'linhas' ? 'linhas' : 'barras'}">${g}</svg>`;
    }

    const extras = {
        /* ---------- criar ---------- */
        novoGrafico(sec) {
            return this.wfNovoCartao(sec, 'grafico', 'Gráfico', 460, 330, {
                tipo: 'barras', fonte: 'manual', ref: null, cols: null,
                cab: ['Mês', 'Valor'], linhas: [['Jan', '12'], ['Fev', '19'], ['Mar', '8'], ['Abr', '15']],
            }, 'Gráfico adicionado com dados de exemplo. Clique em “Dados” para trocar.');
        },
        novoHabitos(sec) {
            const d = new Date();
            return this.wfNovoCartao(sec, 'habitos', 'Hábitos', 680, 300, {
                habitos: [{ id: uid(), nome: 'Beber água', cor: CORES[0] }, { id: uid(), nome: 'Ler 10 páginas', cor: CORES[1] }],
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
        grUI(b) { return (this.wfGrUI[b.id] ??= { editar: false }); },
        /** Cartões que podem alimentar o gráfico (tabelas e hábitos de qualquer aba desta página). */
        grFontes(b) {
            return this.estado.blocos.filter(x => x.id !== b.id && (x.tipo === 'tabela' || x.tipo === 'habitos'))
                .map(x => ({ id: x.id, tipo: x.tipo, nome: (x.tipo === 'tabela' ? 'Tabela: ' : 'Hábitos: ') + (x.titulo || 'sem título') }));
        },
        grEscolherFonte(b, v) {
            if (v === 'manual') { b.dados.fonte = 'manual'; b.dados.ref = null; return; }
            const x = this.blocoPorId(v);
            if (!x) return;
            b.dados.fonte = x.tipo === 'habitos' ? 'habitos' : 'tabela';
            b.dados.ref = x.id;
            b.dados.cols = null;
            if (x.tipo === 'habitos' && b.dados.tipo === 'linhas') b.dados.tipo = 'barras';
        },
        /** { rotulos, series: [{ nome, cor, valores }] } conforme a fonte. */
        grDados(b) {
            const d = b.dados || {};
            if (d.fonte === 'habitos') {
                const h = this.blocoPorId(d.ref);
                if (!h || h.tipo !== 'habitos') return { rotulos: [], series: [], falta: true };
                const mes = h.dados.mes || iso(new Date()).slice(0, 7), lista = (h.dados.habitos || []).slice(0, MAX_LINHAS);
                return { rotulos: lista.map(x => x.nome || 'Hábito'), series: [{ nome: '% feito em ' + MESES[+mes.slice(5) - 1], cor: CORES[0], valores: lista.map(x => pctHabito(h.dados, x, mes)) }] };
            }
            let cab = d.cab || [], linhas = d.linhas || [];
            if (d.fonte === 'tabela') {
                const t = this.blocoPorId(d.ref);
                if (!t || t.tipo !== 'tabela') return { rotulos: [], series: [], falta: true };
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
        grSvg(b) {
            const { rotulos, series, falta } = this.grDados(b);
            if (falta) return `<svg viewBox="0 0 440 250" class="gr-svg" role="img" aria-label="Fonte não encontrada"><text x="220" y="125" text-anchor="middle" class="gr-vazio">O cartão de origem não existe mais. Escolha outra fonte em “Dados”.</text></svg>`;
            return svgGrafico(b.dados.tipo, rotulos, series);
        },
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
            const { rotulos, series } = this.grDados(b);
            const f = { manual: 'dados digitados', tabela: 'da tabela', habitos: 'dos hábitos' }[b.dados.fonte] || '';
            return `${rotulos.length} ${rotulos.length === 1 ? 'item' : 'itens'} · ${series.length} ${series.length === 1 ? 'série' : 'séries'} · ${f}`;
        },

        /* ---------- hábitos ---------- */
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
            if (f[d.iso]) delete f[d.iso]; else f[d.iso] = 1;
        },
        hbPct(b, h) { return pctHabito(b.dados, h, b.dados.mes); },
        hbSeq(b, h) { return sequencia(b.dados, h); },
        hbGeral(b) {
            const l = b.dados.habitos || [];
            return l.length ? Math.round(l.reduce((s, h) => s + pctHabito(b.dados, h, b.dados.mes), 0) / l.length) : 0;
        },
        hbNovo(b, campo) {
            const nome = (campo.value || '').trim().slice(0, 60);
            if (!nome) return;
            b.dados.habitos ??= [];
            if (b.dados.habitos.length >= MAX_HABITOS) { this.aviso(`Limite de ${MAX_HABITOS} hábitos por cartão.`); return; }
            b.dados.habitos.push({ id: uid(), nome, cor: CORES[b.dados.habitos.length % CORES.length] });
            campo.value = '';
        },
        hbRemover(b, h) {
            if (!confirm(`Tirar o hábito "${h.nome}" e as marcações dele?`)) return;
            b.dados.habitos = b.dados.habitos.filter(x => x.id !== h.id);
            if (b.dados.feitos) delete b.dados.feitos[h.id];
        },
        hbCor(b, h) { const i = CORES.indexOf(h.cor); h.cor = CORES[(i + 1) % CORES.length]; },
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
    window.WFGraficos = { numero, svgGrafico, diasDoMes };
})();
