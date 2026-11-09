/* Work Flower · áreas Carteira e Negócios (a mesma página, areas/carteira/index, com cfg.modo)
   Carteira: carteira individual, investimentos, tarefas e quadro livre (com o cartão Projetos de quem usava a antiga aba Trabalho).
   Negócios: negócios (vendas e caixa), clientes, documentos, loja e marketplaces, equipe, tarefas e quadro livre.
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
    const TIPOS_INVEST = ['Ação', 'FII', 'Fiagro', 'FI-Infra', 'ETF', 'BDR', 'Renda fixa', 'Cripto', 'Outro'];
    /* Tipos com ticker na B3 (cotação, dividendos e indicadores pela brapi). */
    const TIPOS_BOLSA = ['Ação', 'FII', 'Fiagro', 'FI-Infra', 'ETF', 'BDR'];
    /* Tipo do mercado (App\Support\Mercado::TIPOS) → tipo da carteira. */
    const TIPO_MERCADO = { fii: 'FII', fiagro: 'Fiagro', fiinfra: 'FI-Infra', etf: 'ETF', acao: 'Ação', bdr: 'BDR' };
    const NOMES_MERCADO = { todos: 'Todos', fii: 'FIIs', fiagro: 'Fiagro', fiinfra: 'FI-Infra', acao: 'Ações', etf: 'ETFs', bdr: 'BDRs' };
    const CORES_SIM = ['#60a5fa', '#f59e0b', '#4ade80', '#f472b6'];

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
            'inv-renda': { x: 0, y: 1058, w: 1088, h: 560 },
            'inv-filtro': { x: 0, y: 1634, w: 1088, h: 620 },
            'inv-simulador': { x: 0, y: 2270, w: 1088, h: 680 },
        },
        negocios: {
            'neg-negocios': { x: 0, y: 0, w: 340, h: 490 },
            'neg-resumo': { x: 356, y: 0, w: 732, h: 190 },
            'neg-lanc': { x: 356, y: 206, w: 732, h: 284 },
            'neg-caixa': { x: 0, y: 506, w: 540, h: 520 },
            'neg-dash': { x: 556, y: 506, w: 532, h: 520 },
            'neg-conexoes': { x: 0, y: 1042, w: 1088, h: 380 },
        },
        tarefas: {
            'tarefa-form': { x: 0, y: 0, w: 340, h: 440 },
            'tarefa-lista': { x: 356, y: 0, w: 732, h: 520 },
        },
        docs: {
            'doc-lista': { x: 0, y: 0, w: 232, h: 520 },
            'doc-editor': { x: 248, y: 0, w: 400, h: 860 },
            'doc-previa': { x: 664, y: 0, w: 424, h: 860 },
        },
        clientes: {
            'cli-lista': { x: 0, y: 0, w: 1088, h: 520 },
        },
        livre: {},
    };
    /* Cartão "Projetos" da antiga aba Trabalho: aparece no Quadro livre só para quem tinha projetos (ver carregar). */
    const PROJETOS_NO_LIVRE = { x: 0, y: 0, w: 1088, h: 440 };

    /* Abas de cada modo (as de Negócios saíram da Carteira em 04/11/2026; os dados antigos continuam guardados lá).
       As abas "Trabalho" e "Mapa mental" saíram em 09/11/2026: os cartões delas foram para o Quadro livre
       (App\Support\AbasRetiradas, no servidor, ao abrir a página). */
    const ABAS_DO_MODO = {
        carteira: ['tarefas', 'carteira', 'invest', 'livre'],
        negocios: ['negocios', 'produtos', 'clientes', 'docs', 'loja', 'equipe', 'calls', 'integracoes', 'tarefas', 'livre'],
    };

    /* Abas de Negócios com página comum (sem quadro de cartões soltos): sem "Reorganizar" e "+ Bloco". */
    const ABAS_SEM_QUADRO = ['loja', 'equipe', 'produtos', 'calls', 'integracoes'];

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    /* Pedido JSON ao servidor (catálogo de produtos). O catálogo é sempre da conta logada: X-WF-Dono vazio. */
    async function pedirJson(url, metodo = 'GET', corpo = null) {
        const h = { Accept: 'application/json', 'X-WF-Dono': '', 'X-CSRF-TOKEN': csrf() };
        if (corpo && !(corpo instanceof FormData)) { h['Content-Type'] = 'application/json'; corpo = JSON.stringify(corpo); }
        const r = await fetch(url, { method: metodo, credentials: 'same-origin', headers: h, body: corpo });
        let j = {};
        try { j = await r.json(); } catch { /* resposta sem JSON */ }
        if (!r.ok) {
            const erros = j.errors ? Object.values(j.errors).flat() : [];
            throw new Error(j.erro || erros[0] || j.message || 'Não deu certo agora. Tente de novo.');
        }
        return j;
    }
    const produtoVazio = () => ({ id: null, nome: '', sku: '', tipo: 'produto', categoria: '', preco: '', custo: '', estoque: '', descricao: '', na_loja: true, foto: null, tirar_foto: false });

    /* ================= estado inicial ================= */
    function completar(e) {
        e.layout ??= {}; e.blocos ??= [];
        e.contas ??= [{ id: uid(), nome: 'Carteira', banco: 'Dinheiro', inicial: 0, cor: PALETA[1] }];
        e.cats ??= ['Moradia', 'Alimentação', 'Transporte', 'Saúde', 'Lazer', 'Educação', 'Salário', 'Outros'];
        e.lanc ??= []; e.invest ??= []; e.radar ??= [];
        e.negocios ??= []; e.negAtivo ??= null;
        e.projetos ??= []; e.docs ??= []; e.docAtivo ??= null;
        e.clientes ??= [];
        // mensagens prontas para WhatsApp: {nome}, {primeiro_nome} e {loja} são trocados pelo cliente na hora
        e.msgModelos ??= [
            { id: 'm1', nome: 'Boas-vindas', texto: 'Olá, {primeiro_nome}! Obrigado por comprar com a gente. Qualquer dúvida, é só chamar por aqui.' },
            { id: 'm2', nome: 'Pedido pronto', texto: 'Oi, {primeiro_nome}! Seu pedido está pronto. Quando fica bom para você receber ou retirar?' },
            { id: 'm3', nome: 'Lembrete de pagamento', texto: 'Olá, {primeiro_nome}, tudo bem? Passando para lembrar do pagamento do seu pedido. Se já pagou, pode desconsiderar.' },
            { id: 'm4', nome: 'Novidade', texto: 'Oi, {primeiro_nome}! Chegaram novidades na {loja}. Quer que eu te mande as fotos?' },
        ];
        e.docs.forEach(d => D.migrar(d));   // documentos antigos ganham os novos campos sem perder nada
        // conta nova não ganha mais o mapa mental inicial (a aba saiu); o mapa mental continua no "+ Bloco"
        e.mapaSemeado = true;
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
        const MODO = cfg.modo === 'negocios' ? 'negocios' : 'carteira';
        const estado = carregar(cfg);
        // projetos da antiga aba Trabalho: o cartão fica no Quadro livre (só na Carteira e só para quem tem projetos)
        const projetosNoLivre = MODO === 'carteira' && estado.projetos.length > 0;
        if (projetosNoLivre) PADRAO.livre.projetos = { ...PROJETOS_NO_LIVRE };
        const imagens = {};
        Object.keys(cfg.dados).filter(k => k.startsWith('img.')).forEach(k => {
            try { imagens[k.slice(4)] = JSON.parse(cfg.dados[k]); } catch { /* ignora */ }
        });

        return {
            ...Q.estadoUI(),
            ...Q.metodos(PADRAO),
            ...D.estadoUI(),
            ...D.metodos(),
            cfg, estado, imagens, tarefas: cfg.tarefas || [], projetosNoLivre,
            paleta: PALETA, formatos: FORMATOS, catsCusto: CATS_CUSTO, tiposInvest: TIPOS_INVEST,
            fontes: D.FONTES_LISTA,
            colunas: [{ id: 'fazer', nome: 'A fazer' }, { id: 'andando', nome: 'Em andamento' }, { id: 'feito', nome: 'Concluído' }],
            modo: MODO,
            aba: MODO === 'negocios' ? 'negocios' : 'carteira',
            abas: [
                { id: 'tarefas', nome: 'Tarefas', icone: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>' },
                { id: 'carteira', nome: 'Carteira', icone: '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/>' },
                { id: 'invest', nome: 'Investimentos', icone: '<path d="M3 17l5-5 4 4 8-9"/><path d="M15 7h5v5"/>' },
                { id: 'negocios', nome: 'Negócios', icone: '<path d="M4 9l1-5h14l1 5M4 9h16v11H4zM9 20v-6h6v6"/>' },
                { id: 'docs', nome: 'Documentos', icone: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>' },
                { id: 'livre', nome: 'Quadro livre', icone: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="4" rx="1.5"/><rect x="13" y="10" width="7" height="10" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/>' },
                { id: 'produtos', nome: 'Produtos', icone: '<path d="M3.5 7.5L12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5L12 12l8.5-4.5M12 12v9"/>' },
                { id: 'clientes', nome: 'Clientes', icone: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.3-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.8c1.9.7 3.1 2.5 3.5 5.2"/>' },
                { id: 'loja', nome: 'Loja e marketplaces', icone: '<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>' },
                { id: 'equipe', nome: 'Equipe', icone: '<circle cx="12" cy="7" r="3"/><circle cx="5" cy="10" r="2.2"/><circle cx="19" cy="10" r="2.2"/><path d="M6.5 20c.6-3.4 2.8-5.5 5.5-5.5s4.9 2.1 5.5 5.5M1.5 19c.3-2 1.5-3.4 3.5-3.6M22.5 19c-.3-2-1.5-3.4-3.5-3.6"/>' },
                { id: 'calls', nome: 'Calls', icone: '<rect x="2.5" y="6" width="13" height="12" rx="2"/><path d="M15.5 10.5l6-3.5v10l-6-3.5"/>' },
                { id: 'integracoes', nome: 'Integrações', icone: '<path d="M9 7V3M15 7V3M7 7h10v4a5 5 0 0 1-10 0z"/><path d="M12 16v5"/>' },
            ].filter(a => ABAS_DO_MODO[MODO].includes(a.id)).sort((a, b) => ABAS_DO_MODO[MODO].indexOf(a.id) - ABAS_DO_MODO[MODO].indexOf(b.id)),
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
            // Investimentos › mercado (brapi, com cache no servidor): dados dos ativos da carteira, filtro e simulador
            mk: { dados: {}, carregando: false, token: null, erro: '', iniciado: false },
            scr: { f: { tipo: 'fii', setor: '', busca: '', liq_min: '', dy_min: '', dy_max: '', pvp_min: '', pvp_max: '', ordem: 'liquidez' },
                   itens: [], total: 0, semDados: 0, pendentes: 0, setores: [], carregando: false, erro: '', aberto: null, voltas: 0, feito: false },
            sim: { inicial: '1000', mensal: '500', anos: '10', reinvestir: true, alvo: '1000',
                   ativos: [{ ticker: '', dy: '', valorizacao: '' }], res: [], erros: [], carregando: false, erro: '' },
            tiposMercado: NOMES_MERCADO, coresSim: CORES_SIM,
            nv: { nome: '', formato: 'marketplace' }, nvErro: '',
            mv: { modo: 'venda', d: hoje(), item: '', valor: '', canal: '', cat: 'Variável', produto: '', qtd: 1 }, mvErro: '', mvEnviando: false,
            // catálogo de produtos (Negócios › Produtos), carregado do servidor; lojaVendas = pedidos pagos da loja
            prod: { lista: [], cats: [], carregado: false, erro: '', busca: '', cat: '', form: produtoVazio(), salvando: false, temLoja: false, aberto: false },
            lojaVendas: [], lojaNome: null,
            semQuadro: ABAS_SEM_QUADRO,
            cn: { tipo: 'marketplace', nome: '', gasto: '', nota: '' },
            novoProj: '',
            cl: { nome: '', contato: '', tags: '' }, clErro: '', clBusca: '',
            zap: { aberto: false, cli: null, modelo: '', texto: '', fila: [], editar: false },
            zo: { carregado: false, configurado: false, falta: [], eDono: false, config: null, webhook: '', conversas: [], painel: false,
                  form: { phone_number_id: '', waba_id: '', access_token: '', app_secret: '', verify_token: '' }, salvando: false, erroCfg: '', okCfg: '',
                  conversa: null, msgs: [], erro: '', enviando: false, modelos: null, erroModelos: '', modelo: '', params: [] },

            init() {
                const guardada = sessionStorage.getItem(MODO + '.aba');
                this.aba = this.abas.some(a => a.id === guardada) ? guardada : this.aba;
                this.$watch('aba', v => { sessionStorage.setItem(MODO + '.aba', v); this.menuBloco = false; });
                // avisa o ambiente (partials/ambiente) de qual setor está aberto: cada aba pode ter fundo e música próprios
                const avisarSetor = () => { const a = (this.abas).find(x => x.id === this.aba); window.wfSetorAtual = { id: this.aba || '', nome: a ? a.nome : '' }; window.dispatchEvent(new CustomEvent('wf-setor', { detail: window.wfSetorAtual })); };
                this.$watch('aba', avisarSetor); avisarSetor();
                this.$watch('aba', v => { if (v === 'invest') this.iniciarInvest(); });
                if (this.aba === 'invest') this.$nextTick(() => this.iniciarInvest());
                this.zTop = this.maiorZ();
                this.ln.conta = this.estado.contas[0]?.id || '';
                this.$watch('estado', () => this.agendar());
                this.initQuadro();
                this.zapIniciar();

                const urgente = () => { if (this.salvo === 'pendente') this.gravarEstado(); };
                document.addEventListener('visibilitychange', () => { if (document.hidden) urgente(); });
                window.addEventListener('pagehide', urgente);
                if (!this.cfg.dados.estado) this.agendar();
                if (MODO === 'negocios' && this.cfg.produtos) { this.carregarProdutos(); this.carregarVendasLoja(); }
            },

            hoje, uid, brl, num,
            /* Páginas comuns (Produtos, Loja, Equipe) ficam na mesma coluna do título: a área do quadro ocupa a janela
               inteira (quadro.js, ox = onde a coluna começa), então elas voltam para dentro dela e não esticam. */
            estiloPagina() {
                const ox = this.empilhado && this.empilhado() ? 0 : Math.max(0, this.ox || 0);
                return { marginLeft: ox + 'px', maxWidth: ox ? `min(76rem, calc(100vw - ${ox * 2}px))` : '76rem' };
            },
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
                    ...this.estado.invest.filter(a => TIPOS_BOLSA.includes(a.tipo)).map(a => a.nome),
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
                this.carregarMercado();
            },

            /* ================= investimentos › mercado (brapi, cache no servidor) =================
               Dados com atraso e hipóteses simples: nada aqui é recomendação de investimento. */
            iniciarInvest() {
                if (this.mk.iniciado || !this.cfg.mercado) return;
                this.mk.iniciado = true;
                this.carregarMercado();
                this.buscarFiltro();
            },
            tickerDe(a) { const t = String(a.nome || a.ticker || '').trim().toUpperCase(); return /^[A-Z0-9]{4,10}$/.test(t) ? t : ''; },
            async carregarMercado() {
                if (!this.cfg.mercado) return;
                const tk = [...new Set([
                    ...this.estado.invest.filter(a => TIPOS_BOLSA.includes(a.tipo)).map(a => this.tickerDe(a)),
                    ...this.estado.radar.map(r => this.tickerDe(r)),
                ].filter(Boolean))].slice(0, 30);
                if (!tk.length) return;
                this.mk.carregando = true; this.mk.erro = '';
                try {
                    const j = await pedirJson(`${this.cfg.mercado.ativos}?tickers=${tk.join(',')}`);
                    this.mk.dados = { ...this.mk.dados, ...(j.ativos || {}) };
                    this.mk.token = !!j.token;
                } catch (e) { this.mk.erro = e.message; }
                this.mk.carregando = false;
            },
            mkDe(a) { return this.mk.dados[this.tickerDe(a)] || null; },
            /* renda por cota ao mês: a informada à mão vence a média dos últimos 12 meses */
            rendaCota(a) { return num(a.rendaCota) > 0 ? num(a.rendaCota) : (this.mkDe(a)?.rendaMensalCota || 0); },
            setRendaCota(a, v) { a.rendaCota = num(v) > 0 ? num(v) : ''; },
            rendaAtivo(a) { return a.qtd * this.rendaCota(a); },
            rendaTotal() { return this.estado.invest.reduce((s, a) => s + this.rendaAtivo(a), 0); },
            yocAtivo(a) { const inv = this.investido(a); return inv > 0 ? this.rendaAtivo(a) * 12 / inv * 100 : 0; },
            yocTotal() { const inv = this.totalInvest().investido; return inv > 0 ? this.rendaTotal() * 12 / inv * 100 : 0; },
            /** Proventos por mês de pagamento: últimos 12 meses e os 3 próximos já anunciados, com a quantidade de hoje. */
            proventosPorMes() {
                const d0 = new Date(), meses = [];
                for (let i = -11; i <= 3; i++) {
                    const d = new Date(d0.getFullYear(), d0.getMonth() + i, 1);
                    meses.push({ k: iso(d).slice(0, 7), nome: MESES[d.getMonth()], ano: d.getFullYear(), futuro: i > 0, v: 0 });
                }
                const idx = Object.fromEntries(meses.map((m, i) => [m.k, i]));
                this.estado.invest.forEach(a => (this.mkDe(a)?.dividendos || []).forEach(dv => {
                    const i = idx[String(dv.pag).slice(0, 7)];
                    if (i !== undefined) meses[i].v += a.qtd * dv.valor;
                }));
                const max = Math.max(...meses.map(m => m.v)) || 1;
                return meses.map(m => ({ ...m, pct: Math.round(m.v / max * 100) }));
            },
            proventos12m() { return this.proventosPorMes().filter(m => !m.futuro).reduce((s, m) => s + m.v, 0); },
            alocacaoPor(campo) {
                const total = this.totalInvest().valor || 1, m = {};
                this.estado.invest.forEach(a => {
                    const k = campo === 'ativo' ? a.nome : (this.mkDe(a)?.setor || a.tipo);
                    m[k] = (m[k] || 0) + this.valorAtivo(a);
                });
                return Object.entries(m).map(([t, v]) => ({ t, v, pct: r1(v / total * 100) })).sort((a, b) => b.v - a.v).slice(0, 10);
            },
            fmtN(n, casas = 2) { return n === null || n === undefined || n === '' ? '—' : Number(n).toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }); },
            fmtLiq(n) {
                n = Number(n) || 0;
                if (n >= 1e9) return 'R$ ' + this.fmtN(n / 1e9, 1) + ' bi';
                if (n >= 1e6) return 'R$ ' + this.fmtN(n / 1e6, 1) + ' mi';
                if (n >= 1e3) return 'R$ ' + this.fmtN(n / 1e3, 0) + ' mil';
                return brl(n);
            },

            /* ---- filtro (screener) ---- */
            async buscarFiltro(continuar = false) {
                if (!this.cfg.mercado) return;
                if (!continuar) { this.scr.voltas = 0; clearTimeout(this._scrT); }
                this.scr.carregando = true; this.scr.erro = '';
                const q = new URLSearchParams();
                Object.entries(this.scr.f).forEach(([k, v]) => { v = String(v ?? '').trim(); if (v !== '') q.set(k, /_m(in|ax)$/.test(k) ? v.replace(',', '.') : v); });
                try {
                    const j = await pedirJson(`${this.cfg.mercado.lista}?${q}`);
                    Object.assign(this.scr, { itens: j.itens || [], total: j.total || 0, semDados: j.sem_dados || 0, pendentes: j.pendentes || 0, setores: j.setores || [], feito: true });
                    this.mk.token = !!j.token;
                    // os indicadores chegam aos poucos (10 ativos por pedido): continua sozinho mais algumas vezes
                    if (this.scr.pendentes > 0 && this.scr.voltas < 4) { this.scr.voltas++; this._scrT = setTimeout(() => this.buscarFiltro(true), 400); }
                } catch (e) { this.scr.erro = e.message; }
                this.scr.carregando = false;
            },
            trocarTipoFiltro(t) { this.scr.f.tipo = t; this.scr.f.setor = ''; this.buscarFiltro(); },
            limparFiltro() {
                this.scr.f = { tipo: this.scr.f.tipo, setor: '', busca: '', liq_min: '', dy_min: '', dy_max: '', pvp_min: '', pvp_max: '', ordem: 'liquidez' };
                this.buscarFiltro();
            },
            temIndicadorFiltro() { const f = this.scr.f; return [f.dy_min, f.dy_max, f.pvp_min, f.pvp_max].some(v => String(v).trim() !== '') || ['dy', 'pvp'].includes(f.ordem); },
            paraCarteira(x) {
                this.iv = { nome: x.ticker, tipo: TIPO_MERCADO[x.tipo] || 'Outro', qtd: '', pm: String(x.preco), atual: String(x.preco) };
                this.ivErro = '';
                this.mk.dados = { ...this.mk.dados, [x.ticker]: x };
                this.aviso(`${x.ticker} está no cartão "Novo ativo": informe a quantidade e confira o preço médio.`);
            },
            paraRadar(x) {
                if (this.estado.radar.some(r => r.ticker === x.ticker)) { this.aviso(`${x.ticker} já está no radar.`); return; }
                this.estado.radar.push({ id: uid(), ticker: x.ticker, tese: '', alvo: 0, preco: x.preco });
                this.aviso(`${x.ticker} entrou no radar.`);
            },
            paraSimulador(x) {
                const linha = { ticker: x.ticker, dy: x.dy ?? '', valorizacao: '' };
                const vazia = this.sim.ativos.findIndex(a => !String(a.ticker).trim() && String(a.dy).trim() === '');
                if (vazia >= 0) this.sim.ativos.splice(vazia, 1, linha);
                else if (this.sim.ativos.length < 4) this.sim.ativos.push(linha);
                else { this.aviso('O simulador compara até 4 ativos por vez.'); return; }
                this.aviso(`${x.ticker} está no simulador.`);
            },

            /* ---- simulador ---- */
            addSimAtivo() { if (this.sim.ativos.length < 4) this.sim.ativos.push({ ticker: '', dy: '', valorizacao: '' }); },
            remSimAtivo(i) { this.sim.ativos.splice(i, 1); if (!this.sim.ativos.length) this.addSimAtivo(); },
            async simular() {
                if (!this.cfg.mercado) return;
                const ativos = this.sim.ativos
                    .filter(a => String(a.ticker).trim() || String(a.dy).trim() !== '')
                    .map(a => {
                        const txt = String(a.ticker).trim();
                        return { ticker: /^[A-Za-z0-9]{4,10}$/.test(txt) ? txt.toUpperCase() : null, nome: txt.slice(0, 60) || null,
                                 dy: String(a.dy).trim() === '' ? null : num(a.dy), valorizacao: num(a.valorizacao) };
                    });
                if (!ativos.length) { this.sim.erro = 'Informe ao menos um ativo (ticker) ou um DY.'; return; }
                this.sim.carregando = true; this.sim.erro = ''; this.sim.erros = [];
                try {
                    const j = await pedirJson(this.cfg.mercado.simular, 'POST', {
                        inicial: num(this.sim.inicial), mensal: num(this.sim.mensal), alvo: num(this.sim.alvo), reinvestir: !!this.sim.reinvestir,
                        meses: Math.max(1, Math.min(600, Math.round(num(this.sim.anos) * 12) || 12)), ativos,
                    });
                    this.sim.res = j.resultados || []; this.sim.erros = j.erros || [];
                } catch (e) { this.sim.erro = e.message; this.sim.res = []; }
                this.sim.carregando = false;
            },
            /** Linhas do gráfico (patrimônio de cada cenário e o total aportado), em coordenadas 0–100. */
            simGrafico() {
                const res = this.sim.res;
                if (!res.length) return { max: 0, linhas: [], aportado: '' };
                const max = Math.max(1, ...res.flatMap(r => r.serie.map(s => s.patrimonio)));
                const pts = (serie, campo) => {
                    const fim = serie[serie.length - 1].mes || 1;
                    return serie.map(s => `${(s.mes / fim * 100).toFixed(2)},${(100 - s[campo] / max * 96).toFixed(2)}`).join(' ');
                };
                return { max, linhas: res.map((r, i) => ({ nome: r.nome, cor: CORES_SIM[i % CORES_SIM.length], pts: pts(r.serie, 'patrimonio') })), aportado: pts(res[0].serie, 'aportado') };
            },
            tempo(meses) {
                if (meses === null || meses === undefined) return 'não chega no período';
                const a = Math.floor(meses / 12), m = meses % 12;
                return [a ? `${a} ano${a > 1 ? 's' : ''}` : '', m ? `${m} ${m > 1 ? 'meses' : 'mês'}` : ''].filter(Boolean).join(' e ');
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
            /* Escolheu um produto do catálogo na venda: preço × quantidade, nome e canal vêm preenchidos. */
            escolherProduto() {
                const p = this.produtoPorId(this.mv.produto);
                this.mvErro = '';
                if (!p) return;
                const q = Math.max(1, Math.floor(num(this.mv.qtd)) || 1);
                this.mv.qtd = q;
                this.mv.item = p.nome + (q > 1 ? ' × ' + q : '');
                this.mv.valor = (Math.round(p.preco * q * 100) / 100).toFixed(2);
            },
            produtoPorId(id) { return id ? this.prod.lista.find(p => String(p.id) === String(id)) || null : null; },
            /* Prévia de custo e margem da venda com produto (o servidor confere de novo ao lançar). */
            previaVenda() {
                const p = this.produtoPorId(this.mv.produto);
                if (!p) return null;
                const q = Math.max(1, Math.floor(num(this.mv.qtd)) || 1), valor = num(this.mv.valor);
                const custo = p.custo === null ? null : p.custo * q;
                return { custo, lucro: custo === null ? null : valor - custo, margem: custo === null || valor <= 0 ? null : (valor - custo) / valor * 100,
                    estoque: p.estoque, falta: p.estoque !== null && p.estoque < q };
            },
            async registrarMov() {
                const n = this.neg(), f = this.mv;
                if (!n || this.mvEnviando) return;
                if (num(f.valor) <= 0) { this.mvErro = 'Informe um valor maior que zero.'; return; }
                if (!f.item.trim()) { this.mvErro = f.modo === 'venda' ? 'Descreva o item vendido ou escolha um produto.' : 'Descreva o custo.'; return; }
                const valor = Math.round(num(f.valor) * 100) / 100;
                if (f.modo === 'venda') {
                    const venda = { id: uid(), d: f.d || hoje(), item: f.item.trim(), valor, canal: f.canal.trim() };
                    const p = this.produtoPorId(f.produto);
                    if (p) {
                        // baixa o estoque no servidor e traz o custo do produto (para a margem)
                        this.mvEnviando = true;
                        try {
                            const j = await pedirJson(`${this.cfg.produtos}/${p.id}/venda`, 'POST', { qtd: Math.max(1, Math.floor(num(f.qtd)) || 1) });
                            Object.assign(venda, { produto: p.id, qtd: j.qtd, custo: j.custo_total });
                            p.estoque = j.estoque;
                        } catch (e) { this.mvErro = e.message; return; } finally { this.mvEnviando = false; }
                    }
                    n.vendas.push(venda);
                } else n.custos.push({ id: uid(), d: f.d || hoje(), nome: f.item.trim(), valor, cat: f.cat });
                this.mv.item = ''; this.mv.valor = ''; this.mv.produto = ''; this.mv.qtd = 1; this.mvErro = '';
            },
            async remMov(n, m) {
                if (m.loja) return; // pedido da loja: muda na página da loja
                const lista = m.tipo === 'venda' ? n.vendas : n.custos;
                const i = lista.findIndex(x => x.id === m.id);
                if (i < 0) return;
                const v = lista[i];
                if (m.tipo === 'venda' && v.produto && v.qtd) {
                    const p = this.produtoPorId(v.produto);
                    if (!confirm(`Apagar esta venda? ${p && p.estoque !== null ? v.qtd + ' unidade(s) voltam para o estoque de "' + p.nome + '".' : ''}`)) return;
                    try {
                        const j = await pedirJson(`${this.cfg.produtos}/${v.produto}/estorno`, 'POST', { qtd: v.qtd });
                        if (p) p.estoque = j.estoque;
                    } catch { /* produto apagado do catálogo: só tira a venda */ }
                }
                const k = lista.findIndex(x => x.id === m.id);
                if (k >= 0) lista.splice(k, 1);
            },
            /* Vendas do negócio + pedidos pagos da loja (quando o negócio soma a loja). */
            vendasDe(n) { return n ? (n.comLoja ? [...n.vendas, ...this.lojaVendas] : n.vendas) : []; },
            kpisNeg(n) {
                if (!n) return { vendas: 0, custos: 0, cmv: 0, lucro: 0, margem: 0, ticket: 0, qtd: 0 };
                const ini = mesIni();
                const v = this.vendasDe(n).filter(x => x.d >= ini), c = n.custos.filter(x => x.d >= ini);
                const vendas = v.reduce((s, x) => s + num(x.valor), 0);
                const cmv = v.reduce((s, x) => s + num(x.custo), 0); // custo dos produtos vendidos (do catálogo)
                const custos = c.reduce((s, x) => s + num(x.valor), 0) + cmv;
                return { vendas, custos, cmv, lucro: vendas - custos, margem: vendas > 0 ? (vendas - custos) / vendas * 100 : 0, ticket: v.length ? vendas / v.length : 0, qtd: v.length };
            },
            caixaNeg(n) {
                if (!n) return { itens: [], saldo: 0 };
                const l = [
                    ...this.vendasDe(n).map(v => ({ id: v.id, d: v.d, desc: v.item + (v.canal ? ' · ' + v.canal : ''), v: num(v.valor), tipo: 'venda', loja: String(v.id).startsWith('lj') })),
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
                const soma = (arr, a, b, campo = 'valor') => arr.filter(x => x.d >= a && x.d <= b).reduce((s, x) => s + num(x[campo]), 0);
                const vendas = this.vendasDe(n);
                const itens = this.baldes(g.janela).map(k => {
                    const custos = soma(n.custos, k.a, k.b) + soma(vendas, k.a, k.b, 'custo');
                    const v = g.metrica === 'vendas' ? soma(vendas, k.a, k.b)
                        : g.metrica === 'custos' ? custos
                        : soma(vendas, k.a, k.b) - custos;
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

            /* ================= produtos (área Negócios, o mesmo catálogo da loja) ================= */
            async carregarProdutos() {
                try {
                    const j = await pedirJson(this.cfg.produtos);
                    this.prod.lista = j.produtos || []; this.prod.cats = j.categorias || []; this.prod.temLoja = !!j.loja;
                    this.prod.erro = '';
                } catch (e) { this.prod.erro = 'Não consegui carregar o catálogo: ' + e.message; }
                this.prod.carregado = true;
            },
            async carregarVendasLoja() {
                if (!this.cfg.vendasLoja) return;
                try {
                    const j = await pedirJson(this.cfg.vendasLoja);
                    this.lojaVendas = (j.vendas || []).map(v => ({ ...v, loja: true }));
                    this.lojaNome = j.loja;
                } catch { this.lojaVendas = []; }
            },
            produtosFiltrados() {
                const b = norm(this.prod.busca.trim()), c = this.prod.cat;
                return this.prod.lista.filter(p => (!c || p.categoria === c)
                    && (!b || norm(p.nome + ' ' + (p.sku || '') + ' ' + (p.categoria || '')).includes(b)));
            },
            novoProduto() { this.prod.form = produtoVazio(); this.prod.form.na_loja = this.prod.temLoja; this.prod.aberto = true; this.prod.erro = ''; this.$nextTick(() => this.$refs.prodNome?.focus()); },
            editarProduto(p) {
                this.prod.form = { ...produtoVazio(), ...p, preco: p.preco, custo: p.custo ?? '', estoque: p.estoque ?? '', sku: p.sku || '', categoria: p.categoria || '', descricao: p.descricao || '' };
                this.prod.aberto = true; this.prod.erro = '';
                this.$nextTick(() => { this.$refs.prodForm?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); this.$refs.prodNome?.focus(); });
            },
            margemForm() {
                const f = this.prod.form, pr = num(f.preco), cu = f.custo === '' || f.custo === null ? null : num(f.custo);
                return cu === null || pr <= 0 ? null : (pr - cu) / pr * 100;
            },
            margemTexto(m) { return m === null || m === undefined ? '—' : this.pc(m); },
            async salvarProduto(form) {
                const f = this.prod.form;
                if (!String(f.nome).trim()) { this.prod.erro = 'Dê um nome ao produto ou serviço.'; return; }
                if (num(f.preco) <= 0) { this.prod.erro = 'Informe o preço de venda.'; return; }
                const fd = new FormData();
                ['nome', 'sku', 'tipo', 'categoria', 'descricao', 'preco', 'custo', 'estoque'].forEach(k => fd.append(k, f[k] === null || f[k] === undefined ? '' : String(f[k]).trim()));
                fd.append('na_loja', f.na_loja ? '1' : '0');
                if (f.tirar_foto) fd.append('tirar_foto', '1');
                const arq = form.querySelector('input[type=file]')?.files?.[0];
                if (arq) fd.append('foto', arq);
                this.prod.salvando = true; this.prod.erro = '';
                try {
                    const j = await pedirJson(f.id ? `${this.cfg.produtos}/${f.id}` : this.cfg.produtos, 'POST', fd);
                    const i = this.prod.lista.findIndex(p => p.id === j.produto.id);
                    if (i >= 0) this.prod.lista.splice(i, 1, j.produto); else this.prod.lista.push(j.produto);
                    this.prod.lista.sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));
                    if (j.produto.categoria && !this.prod.cats.includes(j.produto.categoria)) this.prod.cats.push(j.produto.categoria);
                    this.aviso(f.id ? 'Produto salvo.' : 'Produto cadastrado.');
                    this.prod.form = produtoVazio(); this.prod.aberto = false;
                    form.reset();
                } catch (e) { this.prod.erro = e.message; } finally { this.prod.salvando = false; }
            },
            async remProduto(p) {
                if (!confirm(`Apagar "${p.nome}" do catálogo?${p.na_loja ? ' Ele também sai da loja.' : ''} As vendas e pedidos antigos continuam com o nome e o preço.`)) return;
                try {
                    await pedirJson(`${this.cfg.produtos}/${p.id}`, 'DELETE');
                    const i = this.prod.lista.findIndex(x => x.id === p.id);
                    if (i >= 0) this.prod.lista.splice(i, 1);
                    if (this.prod.form.id === p.id) { this.prod.form = produtoVazio(); this.prod.aberto = false; }
                    this.aviso('Produto apagado.');
                } catch (e) { this.aviso(e.message); }
            },
            /* Atalho "Vender" da lista: abre a aba Negócios com o produto escolhido no lançamento. */
            venderProduto(p) {
                this.ir('negocios');
                if (!this.neg()) { this.aviso('Crie ou escolha um negócio para lançar a venda.'); return; }
                this.mv.modo = 'venda'; this.mv.produto = String(p.id); this.mv.qtd = 1; this.escolherProduto();
            },
            estoqueTexto(p) { return p.estoque === null || p.estoque === undefined ? (p.tipo === 'servico' ? 'serviço' : 'sem controle') : p.estoque + (p.estoque === 1 ? ' unid.' : ' unid.'); },

            /* ================= clientes (área Negócios) ================= */
            addCliente() {
                const f = this.cl;
                if (!f.nome.trim()) { this.clErro = 'Digite o nome do cliente.'; return; }
                this.estado.clientes.push({ id: uid(), nome: f.nome.trim().slice(0, 120), contato: f.contato.trim().slice(0, 120), tags: f.tags.trim().slice(0, 120), nota: '', pedidos: 0, total: 0 });
                this.cl = { nome: '', contato: '', tags: '' }; this.clErro = '';
            },
            remCliente(c) {
                if (!confirm(`Remover "${c.nome}" dos clientes?`)) return;
                const i = this.estado.clientes.findIndex(x => x.id === c.id);
                if (i >= 0) this.estado.clientes.splice(i, 1);
            },
            clientesFiltrados() {
                const q = norm(this.clBusca || '');
                const l = [...this.estado.clientes].sort((a, b) => String(a.nome).localeCompare(String(b.nome), 'pt-BR'));
                return q ? l.filter(c => norm(`${c.nome} ${c.contato} ${c.tags}`).includes(q)) : l;
            },
            /** WhatsApp (só números, com DDD) ou e-mail; nada de link para o que não for nenhum dos dois. */
            linkContato(c) {
                const t = String(c.contato || '').trim();
                if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(t)) return 'mailto:' + t;
                const d = t.replace(/\D/g, '');
                return d.length >= 10 ? 'https://wa.me/' + (d.length <= 11 ? '55' + d : d) : '';
            },
            /* ---------- mensagens prontas (WhatsApp) ---------- */
            zapNumero(c) { const d = String(c?.contato || '').replace(/\D/g, ''); return !String(c?.contato || '').includes('@') && d.length >= 10 ? (d.length <= 11 ? '55' + d : d) : ''; },
            zapPreencher(texto, c) {
                const nome = String(c?.nome || '').trim(), loja = this.cfg.nomeLoja || 'nossa loja';
                return String(texto || '').replace(/\{primeiro_nome\}/g, nome.split(/\s+/)[0] || nome).replace(/\{nome\}/g, nome).replace(/\{loja\}/g, loja);
            },
            /** Abre o painel para um cliente (ou para a fila dos filtrados, quando c é null). */
            zapAbrir(c) {
                const m = this.estado.msgModelos[0];
                const fila = c ? [] : this.clientesFiltrados().filter(x => this.zapNumero(x)).map(x => x.id);
                if (!c && !fila.length) { this.aviso('Nenhum cliente da busca tem WhatsApp com DDD.'); return; }
                this.zap = { aberto: true, cli: c ? c.id : null, modelo: m ? m.id : '', texto: m ? m.texto : '', fila, feitos: [], editar: false };
                if (c && this.zo.configurado) { this.zo.modelo = ''; this.zapCarregarConversa(); }
            },
            zapCliente() { return this.estado.clientes.find(c => c.id === this.zap.cli) || null; },
            zapEscolher(id) { const m = this.estado.msgModelos.find(x => x.id === id); this.zap.modelo = id; if (m) this.zap.texto = m.texto; },
            zapLink(c) { const n = this.zapNumero(c); return n ? 'https://wa.me/' + n + '?text=' + encodeURIComponent(this.zapPreencher(this.zap.texto, c)) : ''; },
            /** Registra o envio no histórico do cliente (o WhatsApp abre em outra aba, já com o texto). */
            zapEnviou(c) {
                c.msgs ??= [];
                const m = this.estado.msgModelos.find(x => x.id === this.zap.modelo);
                c.msgs.unshift({ em: new Date().toISOString(), modelo: m ? m.nome : 'Mensagem', texto: this.zapPreencher(this.zap.texto, c).slice(0, 1000) });
                c.msgs.splice(20);
                if (!this.zap.cli) this.zap.feitos = [...(this.zap.feitos || []), c.id];
            },
            zapUltima(c) { const m = (c.msgs || [])[0]; return m ? m.modelo + ' · ' + new Date(m.em).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : ''; },
            zapNovoModelo() {
                if (this.estado.msgModelos.length >= 30) { this.aviso('Limite de 30 mensagens prontas.'); return; }
                const m = { id: uid(), nome: 'Nova mensagem', texto: this.zap.texto || 'Olá, {primeiro_nome}!' };
                this.estado.msgModelos.push(m);
                this.zap.modelo = m.id;
                this.zap.editar = true;
            },
            zapSalvarModelo() { const m = this.estado.msgModelos.find(x => x.id === this.zap.modelo); if (m) { m.texto = this.zap.texto.slice(0, 1500); this.aviso('Mensagem pronta atualizada.'); } },
            zapTirarModelo() {
                const i = this.estado.msgModelos.findIndex(x => x.id === this.zap.modelo);
                if (i < 0 || !confirm('Apagar esta mensagem pronta?')) return;
                this.estado.msgModelos.splice(i, 1);
                this.zapEscolher(this.estado.msgModelos[0]?.id || '');
            },
            /* ---------- WhatsApp oficial (Cloud API; servidor em ZapController) ----------
               zo guarda a configuração, as conversas e a conversa aberta. Sem configuração, vale o wa.me acima. */
            async zapApi(caminho, op = {}) {
                // corpo em base64: o mod_security da hospedagem recusa (406) textos que parecem SQL ou código
                if (op.dados) {
                    const bytes = new TextEncoder().encode(JSON.stringify(op.dados));
                    let bin = ''; bytes.forEach(x => { bin += String.fromCharCode(x); });
                    op = Object.assign({}, op, { body: JSON.stringify({ b64: btoa(bin) }) });
                    delete op.dados;
                }
                const r = await fetch(this.cfg.zapApi + caminho, Object.assign({ credentials: 'same-origin' }, op, { headers: Object.assign({
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                }, op.headers || {}) }));
                const j = await r.json().catch(() => ({}));
                return { ok: r.ok && j.ok !== false, status: r.status, j };
            },
            async zapEstado() {
                if (!this.cfg.zapApi) return;
                try {
                    const { ok, j } = await this.zapApi('');
                    if (!ok) return;
                    Object.assign(this.zo, { carregado: true, configurado: j.configurado, falta: j.falta || [], eDono: j.eDono, config: j.config, webhook: j.webhook, conversas: j.conversas || [] });
                    if (j.config && !this.zo.form.phone_number_id) Object.assign(this.zo.form, { phone_number_id: j.config.phone_number_id || '', waba_id: j.config.waba_id || '', verify_token: j.config.verify_token || '' });
                } catch (e) { /* sem rede: tenta de novo no próximo ciclo */ }
            },
            zapIniciar() {
                if (!this.cfg.zapApi) return;
                this.zapEstado();
                // conversa aberta: confere a cada 8 s; lista e contadores: a cada 45 s (só com a aba visível)
                let n = 0;
                setInterval(() => {
                    if (document.hidden) return;
                    n++;
                    if (this.zap.aberto && this.zapCliente() && this.zo.configurado) this.zapCarregarConversa(true);
                    if (n % 6 === 0 || (this.zo.painel && n % 2 === 0)) this.zapEstado();
                }, 8000);
            },
            zapChave(t) {
                let d = String(t || '').replace(/\D/g, '');
                if (d.length >= 10 && d.length <= 11) d = '55' + d;
                return d.startsWith('55') && (d.length === 12 || d.length === 13) ? d.slice(2, 4) + d.slice(-8) : d.slice(-20);
            },
            /** Conversa do WhatsApp oficial com o cliente (pelo telefone; o WhatsApp às vezes tira o 9). */
            zapConversaDe(c) { const k = this.zapNumero(c) ? this.zapChave(this.zapNumero(c)) : ''; return k ? this.zo.conversas.find(x => x.chave === k) || null : null; },
            zapNaoLidas(c) { return this.zapConversaDe(c)?.nao_lidas || 0; },
            zapTotalNaoLidas() { return this.zo.conversas.reduce((s, c) => s + (c.nao_lidas || 0), 0); },
            async zapCarregarConversa(quieto) {
                const c = this.zapCliente();
                if (!c || !this.zo.configurado || !this.zapNumero(c)) return;
                if (!quieto) { this.zo.msgs = []; this.zo.conversa = null; this.zo.erro = ''; this.zo.modelos = null; }
                try {
                    const { ok, j } = await this.zapApi('/conversa?tel=' + encodeURIComponent(this.zapNumero(c)));
                    if (!ok || this.zapCliente()?.id !== c.id) return;
                    const novas = (j.mensagens || []).length !== this.zo.msgs.length;
                    this.zo.msgs = j.mensagens || []; this.zo.conversa = j.conversa;
                    const k = this.zo.conversas.find(x => x.id === j.conversa?.id); if (k) k.nao_lidas = 0;
                    if (novas) this.$nextTick(() => { const el = document.getElementById('zap-fio'); if (el) el.scrollTop = el.scrollHeight; });
                    if (j.conversa && !j.conversa.janela && this.zo.modelos === null) this.zapCarregarModelos();
                } catch (e) { /* tenta no próximo ciclo */ }
            },
            /** Abre a conversa de um número que escreveu (mesmo sem ser cliente: vira cliente novo). */
            zapAbrirConversa(cv) {
                let c = this.estado.clientes.find(x => x.id === cv.cliente_id) || this.estado.clientes.find(x => this.zapNumero(x) && this.zapChave(this.zapNumero(x)) === cv.chave);
                if (!c) {
                    if (!confirm(`${cv.nome || '+' + cv.telefone} ainda não está nos clientes. Adicionar agora?`)) return;
                    c = { id: uid(), nome: cv.nome || '+' + cv.telefone, contato: '+' + cv.telefone, tags: 'whatsapp', nota: '' };
                    this.estado.clientes.unshift(c);
                }
                this.zo.painel = false;
                this.zapAbrir(c);
            },
            zapJanela() { return !!this.zo.conversa?.janela; },
            zapJanelaAte() { const t = this.zo.conversa?.janela_ate; return t ? new Date(t).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
            async zapCarregarModelos() {
                this.zo.modelos = [];
                const { ok, j } = await this.zapApi('/modelos');
                if (!ok) { this.zo.erroModelos = j.erro || 'Não deu para ler os modelos aprovados.'; return; }
                this.zo.erroModelos = '';
                this.zo.modelos = j.modelos || [];
                if (this.zo.modelos[0] && !this.zo.modelo) this.zapEscolherModeloOficial(this.zo.modelos[0].nome);
            },
            zapEscolherModeloOficial(nome) {
                const m = (this.zo.modelos || []).find(x => x.nome === nome);
                this.zo.modelo = nome;
                this.zo.params = m ? Array.from({ length: m.params }, (_, i) => (i === 0 ? String(this.zapCliente()?.nome || '').split(/\s+/)[0] : '')) : [];
            },
            zapModeloOficial() { return (this.zo.modelos || []).find(x => x.nome === this.zo.modelo) || null; },
            /** Envia pela API: texto livre na janela de 24 h; fora dela, o modelo aprovado escolhido. */
            async zapResponder(comModelo) {
                const c = this.zapCliente();
                if (!c || this.zo.enviando) return;
                const texto = comModelo ? '' : this.zapPreencher(this.zap.texto, c).trim();
                if (!comModelo && !texto) { this.zo.erro = 'Escreva a mensagem.'; return; }
                const m = comModelo ? this.zapModeloOficial() : null;
                if (comModelo && !m) { this.zo.erro = 'Escolha um modelo aprovado.'; return; }
                this.zo.enviando = true; this.zo.erro = '';
                try {
                    const corpo = { tel: this.zapNumero(c), nome: c.nome, cliente_id: c.id };
                    if (m) Object.assign(corpo, { modelo: m.nome, idioma: m.idioma, params: this.zo.params });
                    else corpo.texto = texto;
                    const { ok, j } = await this.zapApi('/enviar', { method: 'POST', dados: corpo });
                    if (!ok) {
                        this.zo.erro = j.erro || (j.message ?? 'Não deu para enviar.');
                        if (j.janela === false) { if (this.zo.conversa) this.zo.conversa.janela = false; else this.zo.conversa = { janela: false }; if (this.zo.modelos === null) this.zapCarregarModelos(); }
                    } else {
                        this.zapEnviou(c);
                        if (!comModelo) this.zap.texto = '';
                        await this.zapCarregarConversa(true);
                        this.zapEstado();
                    }
                } catch (e) { this.zo.erro = 'Sem conexão com o servidor.'; }
                this.zo.enviando = false;
            },
            zapStatus(m) { return { sent: '✓', delivered: '✓✓', read: '✓✓ lida', failed: '⚠ falhou', recebida: '' }[m.status] || ''; },
            async zapSalvarConfig() {
                this.zo.salvando = true; this.zo.erroCfg = ''; this.zo.okCfg = '';
                try {
                    const { ok, j } = await this.zapApi('/config', { method: 'POST', dados: this.zo.form });
                    if (!ok) this.zo.erroCfg = j.erro || (j.errors ? Object.values(j.errors).flat()[0] : (j.message || 'Não deu para salvar.'));
                    else { this.zo.okCfg = j.aviso || 'WhatsApp oficial salvo.'; this.zo.form.access_token = ''; this.zo.form.app_secret = ''; this.zo.form.verify_token = ''; await this.zapEstado(); }
                } catch (e) { this.zo.erroCfg = 'Sem conexão com o servidor.'; }
                this.zo.salvando = false;
            },
            async zapRemoverConfig() {
                if (!confirm('Desligar o WhatsApp oficial? O token sai do site; as conversas continuam guardadas.')) return;
                await this.zapApi('/config', { method: 'DELETE' });
                this.zo.form = { phone_number_id: '', waba_id: '', access_token: '', app_secret: '', verify_token: '' };
                await this.zapEstado();
            },
            zapCopiar(t) { navigator.clipboard?.writeText(t).then(() => this.aviso('Copiado.'), () => this.aviso('Selecione e copie com Ctrl+C.')); },
            async importarClientesLoja() {
                if (!this.cfg.clientesLoja) return;
                try {
                    const r = await fetch(this.cfg.clientesLoja, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-WF-Dono': '' } });
                    if (!r.ok) throw new Error(String(r.status));
                    const j = await r.json();
                    const chave = t => String(t || '').toLowerCase().replace(/\s+/g, '');
                    let novos = 0, atualizados = 0;
                    (j.clientes || []).forEach(n => {
                        const ja = this.estado.clientes.find(c => chave(c.contato) && chave(c.contato) === chave(n.contato));
                        if (ja) { ja.pedidos = n.pedidos; ja.total = n.total; atualizados++; }
                        else { this.estado.clientes.push({ id: uid(), nome: n.nome, contato: n.contato, tags: 'loja', nota: '', pedidos: n.pedidos, total: n.total }); novos++; }
                    });
                    this.aviso(novos || atualizados ? `${novos} clientes novos e ${atualizados} atualizados com os pedidos da loja.` : 'Ninguém comprou na sua loja ainda.');
                } catch { this.aviso('Não consegui buscar os pedidos da loja.'); }
            },

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
                if (a.tipo === 'link') {
                    // só http(s): um anexo vindo de outra pessoa ou de arquivo importado não pode ser javascript:
                    if (/^https?:\/\//i.test(String(a.url || ''))) window.open(a.url, '_blank', 'noopener');
                    return;
                }
                if (!/^data:/i.test(String(a.data || ''))) return;
                const bruto = await (await fetch(a.data)).blob();
                // abre na aba só o que é PDF de verdade; o resto é baixado (um data:text/html aberto rodaria script no site)
                const pdf = a.mime === 'application/pdf' && /^data:application\/pdf[;,]/i.test(a.data);
                const blob = new Blob([await bruto.arrayBuffer()], { type: pdf ? 'application/pdf' : 'application/octet-stream' });
                const url = URL.createObjectURL(blob);
                if (pdf) window.open(url, '_blank');
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
                else if (l.t === 'doc') {
                    // os documentos moram na área Negócios; na Carteira o vínculo antigo leva para lá
                    if (MODO !== 'negocios') { location.href = this.cfg.negocios || '/areas/negocios'; return; }
                    this.estado.docAtivo = l.id; this.ir('docs');
                }
                else this.ir('tarefas');
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
