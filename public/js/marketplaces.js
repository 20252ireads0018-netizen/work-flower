/* Work Flower · Carteira › Marketplaces (tela em areas/carteira/marketplaces.blade.php; servidor em MarketplaceController).
   Contas do Mercado Livre, Shopee e Amazon; anúncios com margem; custos por SKU; pedidos; alterações em massa.
   A conta da margem espelha App\Support\Marketplaces\Marketplaces (tarifaPorRegra / precoParaMargem). */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const CORES = { mercadolivre: '#ffe600', shopee: '#ee4d2d', amazon: '#ff9900' };
    const CANCELADO = /cancel/i;
    const POR_PAGINA = 100;
    const LOTE_ACAO = 50;   // o servidor aceita até 50 anúncios por chamada
    const LOTE_SYNC = 3;    // contas por chamada de sincronização (cada uma pode demorar)

    const num = v => (v === '' || v === null || v === undefined || isNaN(Number(v))) ? null : Number(v);
    /** "1.234,56" / "14,90" / "14.90" -> número */
    const numBR = s => {
        s = String(s ?? '').trim().replace(/^R\$\s*/, '');
        if (s === '') return null;
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        return num(s);
    };

    /* ---------- conta da margem (igual ao PHP) ---------- */
    function tarifaPorRegra(r, preco, comissaoPct) {
        const pct = comissaoPct ?? Number(r.comissao_pct || 0);
        const lim = num(r.taxa_fixa_abaixo_de);
        const fixa = (lim === null || preco < lim) ? Number(r.taxa_fixa || 0) : 0;
        return Math.round((preco * pct / 100 + fixa) * 100) / 100;
    }
    function precoParaMargem(custo, margemPct, r, comissaoPct, frete) {
        const pct = (comissaoPct ?? Number(r.comissao_pct || 0)) + Number(r.imposto_pct || 0) + margemPct;
        if (pct >= 100) return null;
        frete = frete ?? Number(r.frete || 0);
        const calc = fixa => Math.round((custo + frete + fixa) / (1 - pct / 100) * 100) / 100;
        const lim = num(r.taxa_fixa_abaixo_de);
        const comFixa = calc(Number(r.taxa_fixa || 0));
        if (lim === null || comFixa < lim) return comFixa;
        const semFixa = calc(0);
        return semFixa >= lim ? semFixa : lim;
    }

    window.wfMarketplaces = function (cfg) {
        return {
            cfg,
            dono: meta('wf-dono'),
            dados: { canais: [], contas: [], produtos: [], anuncios: [], pedidos: [], dias_pedidos: 30, limite: 1, contas_reais: 0, plano: '' },
            carregado: false, ocupado: false, progresso: '', ok: cfg.ok || '', erro: cfg.erro || '',
            abas: [
                { id: 'resumo', nome: 'Resumo' }, { id: 'contas', nome: 'Contas' }, { id: 'anuncios', nome: 'Anúncios' },
                { id: 'produtos', nome: 'Produtos e custos' }, { id: 'pedidos', nome: 'Pedidos' },
            ],
            aba: 'resumo',
            selContas: {}, selAnuncios: {}, selProdutos: {},
            filtro: { busca: '', conta: '', status: '', margem: '' }, ordem: 'titulo', pagina: 1,
            filtroPedidos: '', buscaProduto: '',
            acao: { tipo: 'preco_pct', valor: '' },
            regras: { comissao_pct: '', taxa_fixa: '', taxa_fixa_abaixo_de: '', frete: '', imposto_pct: '' }, regrasAberto: false,
            importarAberto: false, textoImportar: '', custoMassa: '',
            calc: { onde: 'canal:mercadolivre', custo: 30, frete: '', preco: 99.9, margem: 20 },
            _salvarProdutos: null,

            iniciar() {
                const h = location.hash.slice(1);
                if (this.abas.some(a => a.id === h)) this.aba = h;
                window.addEventListener('hashchange', () => { const x = location.hash.slice(1); if (this.abas.some(a => a.id === x)) this.aba = x; });
                ['filtro.busca', 'filtro.conta', 'filtro.status', 'filtro.margem', 'ordem'].forEach(k => this.$watch(k, () => { this.pagina = 1; }));
                this.recarregar();
            },
            irPara(id) { this.aba = id; history.replaceState(null, '', '#' + id); },

            /* ---------- servidor ---------- */
            async pedir(metodo, caminho, corpo) {
                const r = await fetch(cfg.base + caminho, {
                    method: metodo, credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token') },
                    body: corpo === undefined ? undefined : JSON.stringify(corpo),
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const msg = j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Não deu certo. Tente de novo.';
                    throw Object.assign(new Error(msg), { plano: !!j.plano_limite });
                }
                return j;
            },
            async recarregar() {
                try {
                    this.dados = await this.pedir('GET', '/dados');
                    if (!this.opcoesCalc.some(o => o.valor === this.calc.onde)) this.calc.onde = this.opcoesCalc[0]?.valor || '';
                } catch (e) { this.erro = e.message; }
                this.carregado = true;
            },
            async tarefa(fn) {
                if (this.ocupado) return;
                this.ocupado = true; this.erro = ''; this.ok = '';
                try { await fn(); } catch (e) { this.erro = e.message; } finally { this.ocupado = false; this.progresso = ''; }
            },

            /* ---------- formatação ---------- */
            dinheiro(v) { return num(v) === null ? '—' : Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); },
            pct(v) { return num(v) === null ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'; },
            cor(canal) { return CORES[canal] || 'var(--tinta-2)'; },
            classeMargem(m) { return m === null || m === undefined ? '' : (m < 0 ? 'mk-neg' : (m < 10 ? 'mk-meio' : 'mk-pos')); },
            quando(iso) {
                const s = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
                if (s < 60) return 'agora há pouco';
                if (s < 3600) return `há ${Math.round(s / 60)} min`;
                if (s < 86400) return `há ${Math.round(s / 3600)} h`;
                return new Date(iso).toLocaleDateString('pt-BR');
            },

            /* ---------- seleção ---------- */
            nSel(sel) { return Object.values(sel).filter(Boolean).length; },
            ids(sel) { return Object.keys(sel).filter(k => sel[k]).map(Number); },
            todasMarcadas(lista, sel) { return lista.length > 0 && lista.every(x => x.id && sel[x.id]); },
            marcarTodas(lista, sel, on) { lista.forEach(x => { if (x.id) sel[x.id] = on; }); },

            /* ---------- dados derivados ---------- */
            get contas() { return this.dados.contas; },
            get anuncios() { return this.dados.anuncios; },
            get contasAtivas() { return this.contas.filter(c => c.ativa); },
            conta(id) { return this.contas.find(c => c.id === id); },
            get cheio() { return this.dados.limite !== null && this.dados.contas_reais >= this.dados.limite; },
            textoLimite() {
                const n = this.dados.contas_reais, l = this.dados.limite;
                const contas = x => `${x} ${x === 1 ? 'conta' : 'contas'}`;
                return l === null
                    ? `Plano ${this.dados.plano}: ${contas(n)} de marketplace conectadas, sem limite.`
                    : `Plano ${this.dados.plano}: ${n} de ${contas(l)} de marketplace (contas de demonstração não contam).`;
            },
            avisarLimite() { this.erro = `O plano ${this.dados.plano} conecta até ${this.dados.limite} ${this.dados.limite === 1 ? 'conta' : 'contas'} de marketplace. Desconecte uma ou assine um plano maior.`; },
            urlConectar(canal) { return `${cfg.base}/conectar/${canal}` + (this.dono ? `?de=${encodeURIComponent(this.dono)}` : ''); },

            get produtoPorSku() { const m = {}; this.dados.produtos.forEach(p => { if (p.id) m[p.sku] = p; }); return m; },

            get resumo() {
                const regrasConta = id => this.conta(id)?.regras_efetivas || {};
                const skuDoAnuncio = {};
                this.anuncios.forEach(a => { skuDoAnuncio[a.conta_id + '|' + a.externo_id] = a.sku; });
                const canais = {};
                const canal = c => canais[c] ??= { canal: c, nome: this.dados.canais.find(x => x.canal === c)?.nome || c, contas: 0, ativos: 0, pedidos: 0, faturamento: 0, tarifas: 0, _m: [] };
                this.contas.forEach(c => canal(c.canal).contas++);

                let faturamento = 0, tarifas = 0, lucro = 0, pedidos = 0, estimadas = false, conhecido = false, semCustoVendas = 0;
                for (const p of this.dados.pedidos) {
                    if (CANCELADO.test(p.status || '')) continue;
                    const c = this.conta(p.conta_id); if (!c) continue;
                    const r = regrasConta(p.conta_id);
                    let t = p.tarifas;
                    if (t === null) { t = p.itens.reduce((s, i) => s + tarifaPorRegra(r, i.preco, null) * i.qtd, 0); estimadas = true; }
                    pedidos++; faturamento += p.total; tarifas += t;
                    const cc = canal(c.canal); cc.pedidos++; cc.faturamento += p.total; cc.tarifas += t;
                    if (!p.itens.length) continue;
                    let custo = 0, ok = true;
                    for (const i of p.itens) {
                        const prod = this.produtoPorSku[i.sku || skuDoAnuncio[p.conta_id + '|' + i.externo_id]];
                        if (!prod) { ok = false; semCustoVendas += i.qtd; break; }
                        custo += (Number(prod.custo) + Number(prod.custo_extra)) * i.qtd
                            + Number(r.frete || 0) * i.qtd + i.preco * i.qtd * Number(r.imposto_pct || 0) / 100;
                    }
                    if (ok) { lucro += p.total - t - custo; conhecido = true; }
                }
                let ativos = 0, negativos = 0, semCusto = 0; const margens = [];
                for (const a of this.anuncios) {
                    if (a.status !== 'ativo') continue;
                    ativos++;
                    const c = this.conta(a.conta_id); if (c) canal(c.canal).ativos++;
                    if (a.margem === null) { semCusto++; continue; }
                    margens.push(a.margem); if (c) canal(c.canal)._m.push(a.margem);
                    if (a.margem < 0) negativos++;
                }
                const media = l => l.length ? Math.round(l.reduce((s, x) => s + x, 0) / l.length * 10) / 10 : null;
                return {
                    faturamento, tarifas, lucro, pedidos, tarifasEstimadas: estimadas, lucroConhecido: conhecido, semCustoVendas,
                    ativos, negativos, semCusto, margemMedia: media(margens),
                    porCanal: Object.values(canais).map(c => ({ ...c, margem: media(c._m) })),
                };
            },

            get anunciosFiltrados() {
                const f = this.filtro, b = f.busca.trim().toLowerCase();
                let l = this.anuncios.filter(a =>
                    (!f.conta || String(a.conta_id) === f.conta) &&
                    (!f.status || a.status === f.status) &&
                    (!f.margem || (f.margem === 'negativa' ? a.margem !== null && a.margem < 0
                        : f.margem === 'baixa' ? a.margem !== null && a.margem < 10 : a.margem === null)) &&
                    (!b || [a.titulo, a.sku, a.externo_id].some(x => String(x || '').toLowerCase().includes(b))));
                const desc = this.ordem.startsWith('-'), campo = this.ordem.replace('-', '');
                const val = a => campo === 'titulo' ? (a.titulo || '').toLowerCase() : (a[campo] ?? (desc ? -Infinity : Infinity));
                return l.sort((x, y) => { const p = val(x), q = val(y); return (p < q ? -1 : p > q ? 1 : 0) * (desc ? -1 : 1); });
            },
            get paginas() { return Math.max(1, Math.ceil(this.anunciosFiltrados.length / POR_PAGINA)); },
            get paginaAnuncios() { return this.anunciosFiltrados.slice((this.pagina - 1) * POR_PAGINA, this.pagina * POR_PAGINA); },

            get produtosFiltrados() {
                const b = this.buscaProduto.trim().toLowerCase();
                return this.dados.produtos.filter(p => !b || !p.id || [p.sku, p.nome].some(x => String(x || '').toLowerCase().includes(b)));
            },
            get skusSemProduto() {
                const tem = new Set(this.dados.produtos.map(p => p.sku));
                return [...new Set(this.anuncios.filter(a => a.sku && !tem.has(a.sku)).map(a => a.sku))];
            },
            get pedidosFiltrados() { return this.dados.pedidos.filter(p => !this.filtroPedidos || String(p.conta_id) === this.filtroPedidos); },

            /* ---------- calculadora ---------- */
            get opcoesCalc() {
                return [
                    ...this.contas.map(c => ({ valor: 'conta:' + c.id, nome: c.canal_nome + ' · ' + c.nome })),
                    ...this.dados.canais.map(c => ({ valor: 'canal:' + c.canal, nome: c.nome + ' (referência)' })),
                ];
            },
            get regrasCalc() {
                const [tipo, id] = String(this.calc.onde).split(':');
                if (tipo === 'conta') return this.conta(Number(id))?.regras_efetivas || {};
                return this.dados.canais.find(c => c.canal === id)?.regras || {};
            },
            get calculo() {
                const r = this.regrasCalc, preco = Number(this.calc.preco || 0), custo = Number(this.calc.custo || 0);
                const frete = num(this.calc.frete) ?? Number(r.frete || 0);
                const tarifa = tarifaPorRegra(r, preco, null);
                const imposto = Math.round(preco * Number(r.imposto_pct || 0)) / 100;
                const lucro = Math.round((preco - custo - tarifa - frete - imposto) * 100) / 100;
                const m = num(this.calc.margem);
                return {
                    tarifa, imposto, lucro, margem: preco > 0 ? Math.round(lucro / preco * 1000) / 10 : null,
                    precoAlvo: m === null ? null : precoParaMargem(custo, m, r, null, frete),
                };
            },

            /* ---------- contas ---------- */
            async criarDemo(canal) {
                await this.tarefa(async () => {
                    const j = await this.pedir('POST', '/contas/demo', { canal });
                    this.ok = j.message; await this.recarregar();
                });
            },
            async sincronizar(ids) {
                await this.tarefa(async () => {
                    const res = [];
                    for (let i = 0; i < ids.length; i += LOTE_SYNC) {
                        this.progresso = `Sincronizando ${Math.min(i + LOTE_SYNC, ids.length)} de ${ids.length}…`;
                        const j = await this.pedir('POST', '/sincronizar', { ids: ids.slice(i, i + LOTE_SYNC) });
                        res.push(...j.contas);
                    }
                    const falhas = res.filter(r => !r.ok);
                    const an = res.reduce((s, r) => s + r.anuncios, 0), pe = res.reduce((s, r) => s + r.pedidos, 0);
                    this.ok = `${res.length - falhas.length} de ${res.length} ${res.length === 1 ? 'conta sincronizada' : 'contas sincronizadas'}: ${an} anúncios, ${pe} pedidos.`;
                    if (falhas.length) this.erro = falhas.map(f => `${f.nome}: ${f.erro}`).join(' · ');
                    await this.recarregar();
                });
            },
            sincronizarTodas() { this.sincronizar(this.contasAtivas.map(c => c.id)); },
            async editarContas(campos, ids) {
                await this.tarefa(async () => {
                    await this.pedir('PATCH', '/contas', { ids: ids || this.ids(this.selContas), ...campos });
                    this.ok = 'Contas atualizadas.'; await this.recarregar();
                });
            },
            async renomear(c) {
                const nome = prompt('Nome da conta no Work Flower:', c.nome);
                if (nome && nome.trim() && nome.trim() !== c.nome) await this.editarContas({ nome: nome.trim() }, [c.id]);
            },
            async salvarRegras() {
                const regras = {};
                Object.entries(this.regras).forEach(([k, v]) => { regras[k] = String(v).trim() === '' ? null : numBR(v); });
                await this.editarContas({ regras });
                this.regrasAberto = false;
            },
            async desconectar() {
                const ids = this.ids(this.selContas);
                if (!confirm(`Desconectar ${ids.length} ${ids.length === 1 ? 'conta' : 'contas'}? Os anúncios e pedidos dela(s) saem do Work Flower (nada muda no marketplace).`)) return;
                await this.tarefa(async () => {
                    const j = await this.pedir('DELETE', '/contas', { ids });
                    this.selContas = {}; this.ok = `${j.removidas} ${j.removidas === 1 ? 'conta desconectada' : 'contas desconectadas'}.`;
                    await this.recarregar();
                });
            },

            /* ---------- anúncios em massa ---------- */
            previaAcao() {
                const v = num(this.acao.valor), n = this.nSel(this.selAnuncios);
                const ex = this.anuncios.find(a => this.selAnuncios[a.id]);
                switch (this.acao.tipo) {
                    case 'preco_pct': return v === null ? 'Ex.: 10 sobe 10%, -5 baixa 5%.' : (ex ? `Ex.: ${ex.titulo}: ${this.dinheiro(ex.preco)} → ${this.dinheiro(ex.preco * (1 + v / 100))}` : '');
                    case 'margem': return 'Cada anúncio recebe o preço que dá essa margem, com o custo do SKU e as tarifas da conta. Anúncios sem custo ficam como estão.';
                    case 'estoque_soma': return 'Use número negativo para tirar unidades. O estoque nunca fica abaixo de zero.';
                    case 'pausado': return 'Na Amazon, pausar zera o estoque (ele volta ao reativar).';
                    case 'comissao': case 'frete': return 'Muda só a conta da margem aqui no Work Flower; nada é enviado ao marketplace. Deixe vazio para voltar à regra da conta.';
                    default: return n > LOTE_ACAO ? `Enviado em lotes de ${LOTE_ACAO}.` : '';
                }
            },
            async aplicarAcao() {
                const ids = this.ids(this.selAnuncios), t = this.acao.tipo;
                if (t === 'comissao' || t === 'frete') {
                    const v = String(this.acao.valor).trim() === '' ? null : numBR(this.acao.valor);
                    await this.tarefa(async () => {
                        await this.pedir('PATCH', '/anuncios', { ids, [t === 'comissao' ? 'comissao_pct' : 'frete']: v });
                        this.ok = `Margem recalculada em ${ids.length} anúncios.`; await this.recarregar();
                    });
                    return;
                }
                const status = t === 'ativo' || t === 'pausado';
                const valor = status ? t : numBR(this.acao.valor);
                if (valor === null) { this.erro = 'Preencha o valor.'; return; }
                const reais = ids.filter(id => { const a = this.anuncios.find(x => x.id === id); return a && !this.conta(a.conta_id)?.demo; }).length;
                if (reais && !confirm(`Isso altera ${reais} ${reais === 1 ? 'anúncio' : 'anúncios'} direto no marketplace. Continuar?`)) return;

                await this.tarefa(async () => {
                    let ok = 0; const erros = [];
                    for (let i = 0; i < ids.length; i += LOTE_ACAO) {
                        this.progresso = `Enviando ${Math.min(i + LOTE_ACAO, ids.length)} de ${ids.length}…`;
                        const j = await this.pedir('POST', '/anuncios/acao', { ids: ids.slice(i, i + LOTE_ACAO), acao: status ? 'status' : t, valor });
                        ok += j.ok; erros.push(...j.erros);
                    }
                    this.ok = `${ok} de ${ids.length} ${ids.length === 1 ? 'anúncio atualizado' : 'anúncios atualizados'}.`;
                    if (erros.length) this.erro = `${erros.length} não foram: ` + erros.slice(0, 5).map(e => `${e.titulo || e.id} (${e.erro})`).join(' · ') + (erros.length > 5 ? '…' : '');
                    else this.selAnuncios = {};
                    await this.recarregar();
                });
            },
            async cadastrarCusto(a) {
                const v = numBR(prompt(a.sku === a.externo_id ? `Custo do anúncio ${a.titulo} (R$):` : `Custo do SKU ${a.sku} (R$):`, ''));
                if (v === null) return;
                await this.tarefa(async () => {
                    await this.pedir('PUT', '/produtos', { produtos: [{ sku: a.sku, nome: a.titulo, custo: v }] });
                    await this.recarregar();
                });
            },

            /* ---------- produtos ---------- */
            novoProduto() { this.dados.produtos.unshift({ id: null, sku: '', nome: '', custo: '', custo_extra: '', anuncios: 0 }); },
            importarDosAnuncios() {
                this.skusSemProduto.forEach(sku => {
                    const a = this.anuncios.find(x => x.sku === sku);
                    this.dados.produtos.unshift({ id: null, sku, nome: a?.titulo || '', custo: '', custo_extra: '', anuncios: this.anuncios.filter(x => x.sku === sku).length });
                });
                this.ok = 'SKUs trazidos para o topo da tabela. Preencha o custo: cada linha salva assim que tiver custo.';
            },
            salvarProduto(p) {
                if (!String(p.sku || '').trim() || (!p.id && num(p.custo) === null)) return; // linha nova só salva com custo
                clearTimeout(this._salvarProdutos);
                p._sujo = true;
                this._salvarProdutos = setTimeout(() => this.enviarProdutos(), 500);
            },
            async enviarProdutos() {
                const sujos = this.dados.produtos.filter(p => p._sujo);
                if (!sujos.length) return;
                sujos.forEach(p => { p._sujo = false; });
                try {
                    await this.pedir('PUT', '/produtos', { produtos: sujos.map(p => ({ sku: String(p.sku).trim(), nome: p.nome || null, custo: num(p.custo) ?? 0, custo_extra: num(p.custo_extra) ?? 0 })) });
                    const novos = this.dados.produtos.filter(p => !p.id && num(p.custo) === null); // linhas ainda sem custo continuam na tela
                    await this.recarregar();
                    this.dados.produtos.unshift(...novos.filter(n => !this.dados.produtos.some(p => p.sku === n.sku)));
                } catch (e) { this.erro = e.message; }
            },
            async importarTexto() {
                const produtos = [];
                for (const linha of this.textoImportar.split(/\r?\n/)) {
                    const c = linha.split(linha.includes('\t') ? '\t' : ';').map(s => s.trim());
                    if (!c[0] || /^sku$/i.test(c[0])) continue;
                    produtos.push({ sku: c[0], nome: c[1] || null, custo: numBR(c[2]) ?? 0, custo_extra: numBR(c[3]) ?? 0 });
                }
                if (!produtos.length) { this.erro = 'Nenhuma linha válida. Use: SKU; nome; custo; custo extra'; return; }
                await this.tarefa(async () => {
                    const j = await this.pedir('PUT', '/produtos', { produtos });
                    this.ok = `${j.salvos} produtos importados.`; this.textoImportar = ''; this.importarAberto = false;
                    await this.recarregar();
                });
            },
            async custoEmMassa(campo) {
                const v = numBR(this.custoMassa);
                if (v === null) return;
                const sel = this.dados.produtos.filter(p => p.id && this.selProdutos[p.id]);
                await this.tarefa(async () => {
                    await this.pedir('PUT', '/produtos', { produtos: sel.map(p => ({ sku: p.sku, [campo]: v })) });
                    this.ok = `${sel.length} produtos atualizados.`; this.custoMassa = '';
                    await this.recarregar();
                });
            },
            async removerProdutos() {
                const ids = this.ids(this.selProdutos);
                if (!confirm(`Excluir ${ids.length} ${ids.length === 1 ? 'produto' : 'produtos'}? Os anúncios ficam, mas sem custo para a margem.`)) return;
                await this.tarefa(async () => {
                    await this.pedir('DELETE', '/produtos', { ids });
                    this.selProdutos = {}; await this.recarregar();
                });
            },
        };
    };
})();
