/* Work Flower · área Carteira
   Carteira individual, investimentos, negócios, trabalho, documentos, quadro e blocos livres.
   Depende de quadro.js e documentos.js (carregue antes). A lógica de documentos fica em documentos.js. */
(function () {
    'use strict';

    const Q = window.WFQuadro;
    const D = window.WFDocs;
    const { iso, hoje, uid, r1, num, norm, dataBR } = Q.util;
    const PALETA = Q.PALETA;

    const brl = n => (Number(n) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const mesIni = () => { const d = new Date(); return iso(new Date(d.getFullYear(), d.getMonth(), 1)); };
    const somaDias = n => { const d = new Date(); d.setDate(d.getDate() + n); return iso(d); };
    const LETRAS = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    const FORMATOS = [
        { id: 'marketplace', nome: 'Marketplace', canais: ['Mercado Livre', 'Shopee', 'Amazon', 'Magalu'] },
        { id: 'presencial', nome: 'Venda presencial', canais: ['Balcão', 'Delivery', 'Encomenda'] },
        { id: 'online', nome: 'Loja própria online', canais: ['Site', 'Instagram', 'WhatsApp'] },
        { id: 'servico', nome: 'Serviços', canais: ['Contrato', 'Avulso'] },
    ];
    const CATS_CUSTO = ['Fixo', 'Variável', 'Tráfego', 'Impostos e taxas'];
    const TIPOS_INVEST = ['Ação', 'FII', 'Renda fixa', 'Cripto', 'Outro'];

    /* Posição inicial dos itens (área de 1088 px de largura). O usuário muda tudo. */
    const PADRAO = {
        carteira: {
            'cart-resumo': { x: 0, y: 0, w: 1088, h: 150 },
            'cart-contas': { x: 0, y: 166, w: 340, h: 460 },
            'cart-novo': { x: 356, y: 166, w: 340, h: 460 },
            'cart-cats': { x: 712, y: 166, w: 376, h: 460 },
            'cart-relatorio': { x: 0, y: 642, w: 1088, h: 560 },
        },
        invest: {
            'inv-resumo': { x: 0, y: 0, w: 1088, h: 190 },
            'inv-novo': { x: 0, y: 206, w: 340, h: 440 },
            'inv-lista': { x: 356, y: 206, w: 732, h: 440 },
            'inv-radar': { x: 0, y: 662, w: 1088, h: 380 },
        },
        negocios: {
            'neg-negocios': { x: 0, y: 0, w: 340, h: 420 },
            'neg-resumo': { x: 356, y: 0, w: 732, h: 190 },
            'neg-lanc': { x: 356, y: 206, w: 732, h: 214 },
            'neg-caixa': { x: 0, y: 436, w: 540, h: 520 },
            'neg-dash': { x: 556, y: 436, w: 532, h: 520 },
            'neg-conexoes': { x: 0, y: 972, w: 1088, h: 380 },
        },
        trabalho: {
            'tarefa-form': { x: 0, y: 0, w: 340, h: 440 },
            'tarefa-lista': { x: 356, y: 0, w: 732, h: 520 },
            'projetos': { x: 0, y: 536, w: 1088, h: 440 },
        },
        docs: {
            'doc-lista': { x: 0, y: 0, w: 232, h: 520 },
            'doc-editor': { x: 248, y: 0, w: 400, h: 860 },
            'doc-previa': { x: 664, y: 0, w: 424, h: 860 },
        },
        mapa: {},
        livre: {},
    };

    /* ================= estado inicial ================= */
    function mapaSemente() {
        return {
            id: 'mapa-inicial', secao: 'mapa', tipo: 'mapa', titulo: 'Mapa mental', links: [],
            dados: { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 200, pai: null, cor: PALETA[0] }] },
        };
    }

    function completar(e) {
        e.layout ??= {}; e.blocos ??= [];
        e.contas ??= [{ id: uid(), nome: 'Carteira', banco: 'Dinheiro', inicial: 0, cor: PALETA[1] }];
        e.cats ??= ['Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Lazer', 'Educação', 'Salário', 'Outros'];
        e.lanc ??= []; e.invest ??= []; e.radar ??= [];
        e.negocios ??= []; e.negAtivo ??= null;
        e.projetos ??= []; e.docs ??= []; e.docAtivo ??= null;
        e.docs.forEach(d => D.migrar(d));   // documentos antigos ganham os novos campos sem perder nada
        if (!e.mapaSemeado) {
            e.mapaSemeado = true;
            e.blocos.push(mapaSemente());
            (e.layout.mapa ??= {})['mapa-inicial'] = { x: 0, y: 0, w: 1088, h: 560, z: 1 };
        }
        return e;
    }

    function lerCfg() {
        try {
            const c = JSON.parse(document.getElementById('carteira-cfg').textContent);
            c.dados = Array.isArray(c.dados) ? {} : (c.dados || {});
            return c;
        } catch { return { url: '', cotacao: '', dados: {}, tarefas: [] }; }
    }

    function carregar(cfg) {
        let e = null;
        try { if (cfg.dados.estado) e = JSON.parse(cfg.dados.estado); } catch { /* usa o padrão */ }
        return completar(e || {});
    }

    /* ================= componente da página ================= */
    window.carteiraAbas = function () {
        const cfg = lerCfg();
        const estado = carregar(cfg);
        const imagens = {};
        Object.keys(cfg.dados).filter(k => k.startsWith('img.')).forEach(k => {
            try { imagens[k.slice(4)] = JSON.parse(cfg.dados[k]); } catch { /* ignora */ }
        });

        return {
            ...Q.estadoUI(),
            ...Q.metodos(PADRAO),
            ...D.estadoUI(),
            ...D.metodos(),
            cfg, estado, imagens, tarefas: cfg.tarefas || [],
            paleta: PALETA, formatos: FORMATOS, catsCusto: CATS_CUSTO, tiposInvest: TIPOS_INVEST,
            fontes: D.FONTES_LISTA,
            colunas: [{ id: 'fazer', nome: 'A fazer' }, { id: 'andando', nome: 'Em andamento' }, { id: 'feito', nome: 'Concluído' }],
            aba: 'carteira',
            abas: [
                { id: 'carteira', nome: 'Carteira', icone: '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/>' },
                { id: 'invest', nome: 'Investimentos', icone: '<path d="M3 17l5-5 4 4 8-9"/><path d="M15 7h5v5"/>' },
                { id: 'negocios', nome: 'Negócios', icone: '<path d="M4 9l1-5h14l1 5M4 9h16v11H4zM9 20v-6h6v6"/>' },
                { id: 'trabalho', nome: 'Trabalho', icone: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/>' },
                { id: 'docs', nome: 'Documentos', icone: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>' },
                { id: 'mapa', nome: 'Mapa mental', icone: '<circle cx="12" cy="12" r="2.5"/><circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="20" r="2"/><path d="M10 10.5L6.5 7.5M14 10.5l3.5-3M12 14.5v3.5"/>' },
                { id: 'livre', nome: 'Quadro livre', icone: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="4" rx="1.5"/><rect x="13" y="10" width="7" height="10" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/>' },
            ],
            tiposBloco: [
                { id: 'texto', nome: 'Campo de texto' }, { id: 'tabela', nome: 'Tabela' },
                { id: 'lista', nome: 'Lista de itens' }, { id: 'imagem', nome: 'Imagem' },
                { id: 'mapa', nome: 'Mapa mental' },
            ],

            // formulários
            fl: { per: 'mes', tipo: 'todos', cat: '', conta: '' },
            ln: { tipo: 'gasto', valor: '', cat: '', conta: '', d: hoje(), desc: '' }, lnErro: '', novaCat: '',
            cc: { nome: '', banco: '', inicial: '' }, ccErro: '',
            iv: { nome: '', tipo: 'Ação', qtd: '', pm: '', atual: '' }, ivErro: '',
            rd: { ticker: '', tese: '', alvo: '' },
            nv: { nome: '', formato: 'marketplace' }, nvErro: '',
            mv: { modo: 'venda', d: hoje(), item: '', valor: '', canal: '', cat: 'Variável' }, mvErro: '',
            cn: { tipo: 'marketplace', nome: '', gasto: '', nota: '' },
            novoProj: '',

            init() {
                const guardada = sessionStorage.getItem('carteira.aba');
                this.aba = this.abas.some(a => a.id === guardada) ? guardada : 'carteira';
                this.$watch('aba', v => { sessionStorage.setItem('carteira.aba', v); this.menuBloco = false; });
                this.zTop = this.maiorZ();
                this.ln.conta = this.estado.contas[0]?.id || '';
                this.$watch('estado', () => this.agendar());
                this.initQuadro();

                const urgente = () => { if (this.salvo === 'pendente') this.gravarEstado(); };
                document.addEventListener('visibilitychange', () => { if (document.hidden) urgente(); });
                window.addEventListener('pagehide', urgente);
                if (!this.cfg.dados.estado) this.agendar();
            },

            hoje, uid, brl,
            pc(n) { return String(r1(n)).replace('.', ',') + '%'; },
            pct(v, m) { return m > 0 ? Math.min(Math.max(v / m * 100, 0), 100) : 0; },
            dataBR,
            cor(v) { return v > 0 ? '#4ade80' : v < 0 ? '#f87171' : 'inherit'; },

            /* ================= carteira individual ================= */
            contaNome(id) { return this.estado.contas.find(c => c.id === id)?.nome || 'Sem conta'; },
            saldoConta(c) {
                return num(c.inicial) + this.estado.lanc.filter(l => l.conta === c.id)
                    .reduce((s, l) => s + (l.tipo === 'ganho' ? 1 : -1) * num(l.valor), 0);
            },
            saldoTotal() { return this.estado.contas.reduce((s, c) => s + this.saldoConta(c), 0); },
            addConta() {
                const f = this.cc;
                if (!f.nome.trim()) { this.ccErro = 'Dê um nome à conta.'; return; }
                const c = { id: uid(), nome: f.nome.trim(), banco: f.banco.trim(), inicial: num(f.inicial), cor: PALETA[this.estado.contas.length % PALETA.length] };
                this.estado.contas.push(c);
                if (!this.ln.conta) this.ln.conta = c.id;
                this.cc = { nome: '', banco: '', inicial: '' }; this.ccErro = '';
            },
            remConta(c) {
                if (!confirm(`Excluir a conta "${c.nome}"? Os lançamentos ficam sem conta.`)) return;
                const i = this.estado.contas.findIndex(x => x.id === c.id);
                if (i >= 0) this.estado.contas.splice(i, 1);
                if (this.ln.conta === c.id) this.ln.conta = this.estado.contas[0]?.id || '';
            },
            addCat() {
                const t = this.novaCat.trim();
                if (t && !this.estado.cats.includes(t)) this.estado.cats.push(t);
                this.novaCat = '';
            },
            registrar() {
                const f = this.ln;
                if (num(f.valor) <= 0) { this.lnErro = 'Informe um valor maior que zero.'; return; }
                this.estado.lanc.push({
                    id: uid(), d: f.d || hoje(), tipo: f.tipo, valor: r1(num(f.valor) * 100) / 100 === 0 ? num(f.valor) : Math.round(num(f.valor) * 100) / 100,
                    cat: f.cat || 'Outros', conta: f.conta, desc: f.desc.trim(),
                });
                this.ln.valor = ''; this.ln.desc = ''; this.lnErro = '';
                this.aviso(f.tipo === 'ganho' ? 'Ganho registrado.' : 'Gasto registrado.');
            },
            remLanc(id) { const i = this.estado.lanc.findIndex(x => x.id === id); if (i >= 0) this.estado.lanc.splice(i, 1); },
            mesTotais() { return this.totais(this.estado.lanc.filter(l => l.d >= mesIni())); },
            totais(lista) {
                const t = { ganhos: 0, gastos: 0 };
                lista.forEach(l => { if (l.tipo === 'ganho') t.ganhos += num(l.valor); else t.gastos += num(l.valor); });
                return { ...t, saldo: t.ganhos - t.gastos };
            },
            filtrados() {
                const f = this.fl;
                const ini = { mes: mesIni(), '30d': somaDias(-30), ano: `${new Date().getFullYear()}-01-01`, tudo: '' }[f.per];
                return this.estado.lanc
                    .filter(l => (!ini || l.d >= ini) && (f.tipo === 'todos' || l.tipo === f.tipo) && (!f.cat || l.cat === f.cat) && (!f.conta || l.conta === f.conta))
                    .sort((a, b) => b.d.localeCompare(a.d) || b.id.localeCompare(a.id));
            },
            porCategoria() {
                const m = {};
                this.filtrados().filter(l => l.tipo === 'gasto').forEach(l => { m[l.cat] = (m[l.cat] || 0) + num(l.valor); });
                const arr = Object.entries(m).map(([c, v]) => ({ c, v })).sort((a, b) => b.v - a.v);
                const max = arr[0]?.v || 1;
                return arr.map(x => ({ ...x, pct: Math.round(x.v / max * 100) }));
            },

            /* ================= investimentos ================= */
            addInvest() {
                const f = this.iv;
                if (!f.nome.trim()) { this.ivErro = 'Informe o ativo (ex.: PETR4, Tesouro Selic).'; return; }
                if (num(f.qtd) <= 0 || num(f.pm) <= 0) { this.ivErro = 'Quantidade e preço médio precisam ser maiores que zero.'; return; }
                const atual = num(f.atual) > 0 ? num(f.atual) : num(f.pm);
                this.estado.invest.push({ id: uid(), nome: f.nome.trim(), tipo: f.tipo, qtd: num(f.qtd), pm: num(f.pm), atual, hist: [{ d: hoje(), v: atual }] });
                this.iv = { nome: '', tipo: f.tipo, qtd: '', pm: '', atual: '' }; this.ivErro = '';
            },
            remInvest(a) {
                if (!confirm(`Remover "${a.nome}" da carteira?`)) return;
                const i = this.estado.invest.findIndex(x => x.id === a.id);
                if (i >= 0) this.estado.invest.splice(i, 1);
            },
            investido(a) { return a.qtd * a.pm; },
            valorAtivo(a) { return a.qtd * a.atual; },
            resAtivo(a) { return this.valorAtivo(a) - this.investido(a); },
            resPct(a) { return this.investido(a) > 0 ? this.resAtivo(a) / this.investido(a) * 100 : 0; },
            setAtual(a, v) {
                v = num(v);
                if (v <= 0 || v === a.atual) return;
                a.atual = v;
                const ult = a.hist[a.hist.length - 1];
                if (ult && ult.d === hoje()) ult.v = v; else a.hist.push({ d: hoje(), v });
                if (a.hist.length > 120) a.hist.splice(0, a.hist.length - 120);
            },
            totalInvest() {
                const investido = this.estado.invest.reduce((s, a) => s + this.investido(a), 0);
                const valor = this.estado.invest.reduce((s, a) => s + this.valorAtivo(a), 0);
                return { investido, valor, res: valor - investido, pct: investido > 0 ? (valor - investido) / investido * 100 : 0 };
            },
            ganhosPerdas() {
                const l = this.estado.invest.map(a => this.resAtivo(a));
                return { ganhos: l.filter(x => x > 0).reduce((s, x) => s + x, 0), perdas: l.filter(x => x < 0).reduce((s, x) => s + x, 0) };
            },
            alocacao() {
                const total = this.totalInvest().valor || 1, m = {};
                this.estado.invest.forEach(a => { m[a.tipo] = (m[a.tipo] || 0) + this.valorAtivo(a); });
                return Object.entries(m).map(([t, v]) => ({ t, v, pct: r1(v / total * 100) })).sort((a, b) => b.v - a.v);
            },
            pontosAtivo(a) {
                const h = a.hist.slice(-14);
                if (h.length < 2) return '';
                const v = h.map(x => x.v), mn = Math.min(...v), span = (Math.max(...v) - mn) || 1;
                return h.map((x, i) => `${(i / (h.length - 1) * 100).toFixed(1)},${(26 - ((x.v - mn) / span) * 22).toFixed(1)}`).join(' ');
            },
            addRadar() {
                const f = this.rd;
                if (!f.ticker.trim()) return;
                this.estado.radar.push({ id: uid(), ticker: f.ticker.trim().toUpperCase(), tese: f.tese.trim(), alvo: num(f.alvo), preco: 0 });
                this.rd = { ticker: '', tese: '', alvo: '' };
            },
            remRadar(id) { const i = this.estado.radar.findIndex(x => x.id === id); if (i >= 0) this.estado.radar.splice(i, 1); },
            radarTxt(x) {
                if (!x.preco) return 'sem cotação';
                if (x.alvo > 0) return x.preco <= x.alvo ? 'abaixo do alvo' : 'acima do alvo';
                return brl(x.preco);
            },
            async atualizarCotacoes() {
                const tk = [...new Set([
                    ...this.estado.invest.filter(a => ['Ação', 'FII'].includes(a.tipo)).map(a => a.nome),
                    ...this.estado.radar.map(r => r.ticker),
                ].map(t => String(t).trim().toUpperCase()).filter(t => /^[A-Z0-9]{3,10}$/.test(t)))];
                if (!tk.length) { this.aviso('Nenhum ticker para atualizar.'); return; }
                try {
                    const r = await fetch(`${this.cfg.cotacao}?tickers=${tk.join(',')}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                    if (!r.ok) throw new Error(String(r.status));
                    const q = await r.json();
                    let n = 0;
                    this.estado.invest.forEach(a => { const p = num(q[String(a.nome).trim().toUpperCase()]); if (p > 0) { this.setAtual(a, p); n++; } });
                    this.estado.radar.forEach(x => { const p = num(q[String(x.ticker).trim().toUpperCase()]); if (p > 0) { x.preco = p; n++; } });
                    this.aviso(n ? `${n} cotações atualizadas.` : 'Sem cotações disponíveis agora.');
                } catch { this.aviso('Não consegui buscar cotações.'); }
            },

            /* ================= negócios ================= */
            neg() { return this.estado.negocios.find(n => n.id === this.estado.negAtivo) || null; },
            formatoNome(id) { return FORMATOS.find(f => f.id === id)?.nome || ''; },
            canais() { return FORMATOS.find(f => f.id === this.neg()?.formato)?.canais || []; },
            addNegocio() {
                const f = this.nv;
                if (!f.nome.trim()) { this.nvErro = 'Dê um nome ao negócio.'; return; }
                const n = {
                    id: uid(), nome: f.nome.trim(), formato: f.formato, vendas: [], custos: [], conexoes: [],
                    graficos: [
                        { id: uid(), titulo: 'Vendas · 7 dias', metrica: 'vendas', janela: '7d', estilo: 'barras' },
                        { id: uid(), titulo: 'Lucro · 6 meses', metrica: 'lucro', janela: '6m', estilo: 'linha' },
                    ],
                };
                this.estado.negocios.push(n);
                this.estado.negAtivo = n.id;
                this.nv = { nome: '', formato: f.formato }; this.nvErro = '';
                this.aviso('Negócio criado.');
            },
            remNegocio(n) {
                if (!confirm(`Excluir o negócio "${n.nome}" com todas as vendas e custos?`)) return;
                const i = this.estado.negocios.findIndex(x => x.id === n.id);
                if (i >= 0) this.estado.negocios.splice(i, 1);
                if (this.estado.negAtivo === n.id) this.estado.negAtivo = this.estado.negocios[0]?.id || null;
            },
            registrarMov() {
                const n = this.neg(), f = this.mv;
                if (!n) return;
                if (num(f.valor) <= 0) { this.mvErro = 'Informe um valor maior que zero.'; return; }
                if (!f.item.trim()) { this.mvErro = f.modo === 'venda' ? 'Descreva o item vendido.' : 'Descreva o custo.'; return; }
                const valor = Math.round(num(f.valor) * 100) / 100;
                if (f.modo === 'venda') n.vendas.push({ id: uid(), d: f.d || hoje(), item: f.item.trim(), valor, canal: f.canal.trim() });
                else n.custos.push({ id: uid(), d: f.d || hoje(), nome: f.item.trim(), valor, cat: f.cat });
                this.mv.item = ''; this.mv.valor = ''; this.mvErro = '';
            },
            remMov(n, m) {
                const lista = m.tipo === 'venda' ? n.vendas : n.custos;
                const i = lista.findIndex(x => x.id === m.id);
                if (i >= 0) lista.splice(i, 1);
            },
            kpisNeg(n) {
                if (!n) return { vendas: 0, custos: 0, lucro: 0, margem: 0, ticket: 0, qtd: 0 };
                const ini = mesIni();
                const v = n.vendas.filter(x => x.d >= ini), c = n.custos.filter(x => x.d >= ini);
                const vendas = v.reduce((s, x) => s + num(x.valor), 0), custos = c.reduce((s, x) => s + num(x.valor), 0);
                return { vendas, custos, lucro: vendas - custos, margem: vendas > 0 ? (vendas - custos) / vendas * 100 : 0, ticket: v.length ? vendas / v.length : 0, qtd: v.length };
            },
            caixaNeg(n) {
                if (!n) return { itens: [], saldo: 0 };
                const l = [
                    ...n.vendas.map(v => ({ id: v.id, d: v.d, desc: v.item + (v.canal ? ' · ' + v.canal : ''), v: num(v.valor), tipo: 'venda' })),
                    ...n.custos.map(c => ({ id: c.id, d: c.d, desc: c.nome + (c.cat ? ' · ' + c.cat : ''), v: -num(c.valor), tipo: 'custo' })),
                ].sort((a, b) => a.d.localeCompare(b.d) || a.id.localeCompare(b.id));
                let s = 0;
                l.forEach(x => { s += x.v; x.saldo = s; });
                return { itens: l.reverse(), saldo: s };
            },
            baldes(j) {
                const out = [], h = new Date();
                if (j === '7d') {
                    for (let i = 6; i >= 0; i--) { const d = new Date(h); d.setDate(d.getDate() - i); out.push({ a: iso(d), b: iso(d), l: LETRAS[d.getDay()] }); }
                } else if (j === '4s') {
                    for (let i = 3; i >= 0; i--) {
                        const fim = new Date(h); fim.setDate(fim.getDate() - i * 7);
                        const ini = new Date(fim); ini.setDate(ini.getDate() - 6);
                        out.push({ a: iso(ini), b: iso(fim), l: dataBR(iso(fim)) });
                    }
                } else {
                    for (let i = 5; i >= 0; i--) {
                        const d = new Date(h.getFullYear(), h.getMonth() - i, 1);
                        out.push({ a: iso(d), b: iso(new Date(d.getFullYear(), d.getMonth() + 1, 0)), l: MESES[d.getMonth()] });
                    }
                }
                return out;
            },
            serieGrafico(n, g) {
                if (!n) return { itens: [], pontos: '', total: 0 };
                const soma = (arr, a, b) => arr.filter(x => x.d >= a && x.d <= b).reduce((s, x) => s + num(x.valor), 0);
                const itens = this.baldes(g.janela).map(k => {
                    const v = g.metrica === 'vendas' ? soma(n.vendas, k.a, k.b)
                        : g.metrica === 'custos' ? soma(n.custos, k.a, k.b)
                        : soma(n.vendas, k.a, k.b) - soma(n.custos, k.a, k.b);
                    return { l: k.l, v };
                });
                const max = Math.max(...itens.map(i => Math.abs(i.v)), 1);
                itens.forEach(i => { i.h = Math.round(Math.abs(i.v) / max * 100); i.neg = i.v < 0; });
                const vs = itens.map(i => i.v), mn = Math.min(...vs), span = (Math.max(...vs) - mn) || 1;
                const pontos = itens.map((i, k) => `${(k / (itens.length - 1) * 100).toFixed(1)},${(36 - ((i.v - mn) / span) * 32).toFixed(1)}`).join(' ');
                return { itens, pontos, total: vs.reduce((a, b) => a + b, 0) };
            },
            addGrafico() {
                const n = this.neg();
                if (n) n.graficos.push({ id: uid(), titulo: 'Novo gráfico', metrica: 'vendas', janela: '4s', estilo: 'barras' });
            },
            remGrafico(n, id) { const i = n.graficos.findIndex(x => x.id === id); if (i >= 0) n.graficos.splice(i, 1); },
            addConexao() {
                const n = this.neg(), f = this.cn;
                if (!n || !f.nome.trim()) return;
                n.conexoes.push({ id: uid(), tipo: f.tipo, nome: f.nome.trim(), gasto: num(f.gasto), nota: f.nota.trim(), ativa: true });
                this.cn = { tipo: f.tipo, nome: '', gasto: '', nota: '' };
            },
            remConexao(n, id) { const i = n.conexoes.findIndex(x => x.id === id); if (i >= 0) n.conexoes.splice(i, 1); },

            /* ================= trabalho: projetos ================= */
            addProjeto() {
                const t = this.novoProj.trim();
                if (!t) return;
                this.estado.projetos.push({ id: uid(), nome: t, status: 'fazer', nota: '', doc: '', anexos: [] });
                this.novoProj = '';
            },
            projDe(st) { return this.estado.projetos.filter(p => p.status === st); },
            moverProj(p, dir) {
                const ids = this.colunas.map(c => c.id), i = ids.indexOf(p.status) + dir;
                if (i >= 0 && i < ids.length) p.status = ids[i];
            },
            remProj(id) { const i = this.estado.projetos.findIndex(x => x.id === id); if (i >= 0) this.estado.projetos.splice(i, 1); },

            /* ---- anexos externos dos projetos (PDF, Word, texto e links) ---- */
            anexarArquivo(p, file) {
                if (!file) return;
                if (!/\.(pdf|docx?|odt|rtf|txt)$/i.test(file.name)) { this.aviso('Use PDF, Word ou texto.'); return; }
                if (file.size > 3 * 1024 * 1024) { this.aviso('O arquivo passa de 3 MB.'); return; }
                const r = new FileReader();
                r.onload = () => {
                    (p.anexos ??= []).push({ id: uid(), tipo: 'arquivo', nome: file.name, mime: file.type, data: r.result });
                    this.aviso('Arquivo anexado.');
                };
                r.readAsDataURL(file);
            },
            anexarLink(p, url) {
                url = String(url || '').trim();
                if (!url) return;
                if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
                let nome;
                try { nome = new URL(url).hostname.replace(/^www\./, ''); } catch { this.aviso('Link inválido.'); return; }
                (p.anexos ??= []).push({ id: uid(), tipo: 'link', nome, url });
            },
            remAnexo(p, id) {
                const i = (p.anexos || []).findIndex(x => x.id === id);
                if (i >= 0) p.anexos.splice(i, 1);
            },
            async abrirAnexo(a) {
                if (a.tipo === 'link') { window.open(a.url, '_blank', 'noopener'); return; }
                const blob = await (await fetch(a.data)).blob();
                const url = URL.createObjectURL(blob);
                if (a.mime === 'application/pdf') window.open(url, '_blank');
                else { const l = document.createElement('a'); l.href = url; l.download = a.nome; l.click(); }
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            },

            /* ================= documentos =================
               Toda a lógica (modelos, elementos, formatação, prévia, impressão) está em documentos.js. */

            /* ================= vínculos dos blocos ================= */
            rotuloLink(l) {
                if (l.t === 'negocio') return this.estado.negocios.find(n => n.id === l.id)?.nome || 'negócio removido';
                if (l.t === 'doc') return this.estado.docs.find(d => d.id === l.id)?.titulo || 'documento removido';
                return this.tarefas.find(t => String(t.id) === String(l.id))?.titulo || 'tarefa removida';
            },
            abrirLink(l) {
                if (l.t === 'negocio') { this.estado.negAtivo = l.id; this.ir('negocios'); }
                else if (l.t === 'doc') { this.estado.docAtivo = l.id; this.ir('docs'); }
                else this.ir('trabalho');
            },

            /* Ajusta a altura do bloco à proporção da imagem (só quando a imagem muda). */
            ajustarImagem(b, img) {
                if (!img.naturalWidth) return;
                const ratio = img.naturalHeight / img.naturalWidth;
                if (b.dados.ratio === ratio) return;          // não sobrescreve redimensionamento manual
                b.dados.ratio = ratio;
                const L = this.estado.layout?.[b.secao]?.[b.id];
                if (!L) return;
                const cabecalho = 46, padding = 32;           // topo do bloco + padding do corpo
                L.h = Math.max(Math.round((L.w - padding) * ratio) + cabecalho + padding, 80);
            },
        };
    };
})();