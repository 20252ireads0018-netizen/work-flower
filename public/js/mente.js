/* Work Flower · área Mente
   Livros e PDFs (leitor com destaques e busca de texto), estudos com revisão em 7 dias, anotações,
   faculdade, quadro arrastável e blocos livres (texto, tabela, lista, imagem, mapa, documento).
   Sem build: a view carrega este arquivo e o Alpine já está na página.
   Depende de quadro.js (carregue antes). */
(function () {
    'use strict';

    /* ================= utilidades ================= */
    const Q = window.WFQuadro;
    const { pad, iso, hoje, uid, r1, num, clamp, dataBR } = Q.util;
    const { enviar } = Q;
    const addDias = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };
    const diasEntre = (a, b) => Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / 864e5);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const r2 = n => Math.round(n * 100) / 100;

    const DIAS = [
        { id: 'seg', nome: 'Segunda', c: 'Seg' }, { id: 'ter', nome: 'Terça', c: 'Ter' },
        { id: 'qua', nome: 'Quarta', c: 'Qua' }, { id: 'qui', nome: 'Quinta', c: 'Qui' },
        { id: 'sex', nome: 'Sexta', c: 'Sex' }, { id: 'sab', nome: 'Sábado', c: 'Sáb' },
        { id: 'dom', nome: 'Domingo', c: 'Dom' },
    ];
    const PALETA = Q.PALETA;
    const HL = ['#f2c230', '#4ade80', '#38bdf8', '#f472b6', '#fb923c'];
    const FONTES = { sans: "'DM Sans', system-ui, sans-serif", serif: "Georgia, 'Times New Roman', serif", mono: "ui-monospace, Menlo, Consolas, monospace" };
    const TIPOS_AVAL = ['Prova', 'Trabalho', 'Seminário', 'Atividade', 'Outro'];
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    const MAX_BUSCA = 1000;       // para de procurar depois de tantas ocorrências

    /* Normaliza um caractere para a busca: sem acento, minúsculo, espaços viram ' '.
       Sempre devolve exatamente 1 caractere, para os índices continuarem batendo com o texto original. */
    function norm1(ch) {
        if (/\s/.test(ch)) return ' ';
        const s = ch.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        if (s.length === 1) return s;
        return ch.toLowerCase().slice(0, 1) || ch;
    }
    const normTxt = t => String(t).split('').map(norm1).join('').replace(/ +/g, ' ').trim();

    /* Modelos de documento: [título da seção, texto inicial] */
    const MODELOS = {
        branco: { nome: 'Em branco', secoes: [['', '']] },
        curriculo: { nome: 'Currículo', secoes: [['Seu nome', 'Cargo · cidade · e-mail · telefone'], ['Objetivo', ''], ['Experiência', ''], ['Formação', ''], ['Habilidades', '']] },
        contrato: { nome: 'Contrato', secoes: [['Partes', ''], ['Objeto', ''], ['Prazo e valor', ''], ['Cláusulas', ''], ['Assinaturas', '']] },
        resumo: { nome: 'Resumo de estudo', secoes: [['Tema', ''], ['Ideias principais', ''], ['Dúvidas', ''], ['Para revisar', '']] },
        lista: { nome: 'Lista', secoes: [['Lista', '']] },
    };

    /* Posição inicial dos itens (para uma área de 1088 px de largura). O usuário muda tudo. */
    const PADRAO = {
        tarefas: {
            'tarefa-form': { x: 0, y: 0, w: 340, h: 440 },
            'tarefa-lista': { x: 356, y: 0, w: 732, h: 520 },
        },
        livros: {
            'livro-lista': { x: 0, y: 0, w: 340, h: 640 },
            'livro-leitor': { x: 356, y: 0, w: 732, h: 760 },
            'livro-destaques': { x: 0, y: 656, w: 340, h: 560 },
        },
        estudos: {
            'est-novo': { x: 0, y: 0, w: 340, h: 420 },
            'est-cursos': { x: 0, y: 436, w: 340, h: 380 },
            'est-revisar': { x: 356, y: 0, w: 732, h: 320 },
            'est-lista': { x: 356, y: 336, w: 732, h: 480 },
        },
        notas: {
            'nota-lista': { x: 0, y: 0, w: 340, h: 640 },
            'nota-editor': { x: 356, y: 0, w: 732, h: 640 },
        },
        faculdade: {
            'fac-semana': { x: 0, y: 0, w: 1088, h: 300 },
            'fac-disc-form': { x: 0, y: 316, w: 340, h: 640 },
            'fac-disc': { x: 356, y: 316, w: 732, h: 300 },
            'fac-aval': { x: 356, y: 632, w: 732, h: 440 },
        },
        mapa: {},
        livre: {},
    };

    /* ================= pdf.js (carregado só quando abre um PDF) ================= */
    let pdfDoc = null, pdfLib = null;
    let obs = null;               // observa quais páginas estão perto da tela
    let geracao = 0;              // muda a cada novo layout/livro; cancela desenhos antigos
    let pgDim = [];               // tamanho de cada página em escala 1: pgDim[n] = { w, h }
    let travaScroll = 0;          // ignora o evento de rolagem logo após rolarmos por código
    let rafRolar = 0;
    let textoPg = [];             // texto de cada página (cache da busca): textoPg[n] = { texto, segs, vp }
    let buscaGer = 0;             // muda a cada nova busca; cancela a busca anterior
    const estPg = new Map();      // páginas desenhadas agora: n -> { task }

    function carregarPdfJs() {
        if (pdfLib) return Promise.resolve(pdfLib);
        return new Promise((ok, falha) => {
            const s = document.createElement('script');
            s.src = PDFJS + 'pdf.min.js';
            s.onload = () => { pdfLib = window.pdfjsLib; pdfLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js'; ok(pdfLib); };
            s.onerror = () => falha(new Error('pdfjs'));
            document.head.appendChild(s);
        });
    }

    /* ================= estado inicial ================= */
    function mapaSemente() {
        return {
            id: 'mapa-inicial', secao: 'mapa', tipo: 'mapa', titulo: 'Mapa mental', links: [],
            dados: { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 200, pai: null, cor: PALETA[0] }] },
        };
    }

    function completar(e) {
        e.layout ??= {}; e.livros ??= []; e.notas ??= []; e.blocos ??= [];
        const es = (e.estudos ??= {});
        es.intervalo ??= 7; es.cursos ??= []; es.itens ??= [];
        const f = (e.fac ??= {});
        f.disciplinas ??= []; f.avaliacoes ??= [];
        if (!e.mapaSemeado) {
            e.mapaSemeado = true;
            e.blocos.push(mapaSemente());
            (e.layout.mapa ??= {})['mapa-inicial'] = { x: 0, y: 0, w: 1088, h: 560, z: 1 };
        }
        return e;
    }

    function lerCfg() {
        try {
            const c = JSON.parse(document.getElementById('mente-cfg').textContent);
            c.dados = Array.isArray(c.dados) ? {} : (c.dados || {});
            return c;
        } catch { return { url: '', pdf: '', dados: {}, tarefas: [] }; }
    }

    function carregar(cfg) {
        let e = null;
        try { if (cfg.dados.estado) e = JSON.parse(cfg.dados.estado); } catch { /* usa o padrão */ }
        return completar(e || {});
    }

    const vazioLivro = () => ({ titulo: '', autor: '', paginas: '', status: 'lendo' });
    const vazioDisc = () => ({ id: null, nome: '', prof: '', sala: '', cor: PALETA[0], limite: '', hor: [{ k: uid(), dia: 'seg', ini: '08:00', fim: '10:00' }] });
    const vazioAval = () => ({ disc: '', tipo: 'Prova', titulo: '', data: hoje(), peso: 1, nota: '' });
    const vazioBusca = () => ({ q: '', feitoQ: '', res: [], porPg: {}, i: -1, ocupado: false, prog: 0, cheio: false });

    /* ================= componente da página ================= */
    window.menteAbas = function () {
        const cfg = lerCfg();
        const estado = carregar(cfg);
        const imagens = {};
        Object.keys(cfg.dados).filter(k => k.startsWith('img.')).forEach(k => {
            try { imagens[k.slice(4)] = JSON.parse(cfg.dados[k]); } catch { /* ignora imagem corrompida */ }
        });

        const motor = Q.metodos(PADRAO);

        return {
            ...Q.estadoUI(),
            ...motor,
            cfg, estado, imagens, tarefas: cfg.tarefas || [],
            dias: DIAS, paleta: PALETA, hlCores: HL, fontes: FONTES, tiposAval: TIPOS_AVAL,
            modelos: Object.entries(MODELOS).map(([id, m]) => ({ id, nome: m.nome })),
            aba: 'tarefas',
            abas: [
                { id: 'tarefas', nome: 'Tarefas', icone: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>' },
                { id: 'livros', nome: 'Livros', icone: '<path d="M4 5.5A1.5 1.5 0 015.5 4H11v16H5.5A1.5 1.5 0 014 18.5v-13zM13 4h5.5A1.5 1.5 0 0120 5.5v13a1.5 1.5 0 01-1.5 1.5H13V4z"/>' },
                { id: 'estudos', nome: 'Estudos', icone: '<path d="M2.5 9.5L12 5l9.5 4.5L12 14 2.5 9.5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5M21.5 9.5V15"/>' },
                { id: 'notas', nome: 'Anotações', icone: '<path d="M5 20l1-4L17.5 4.5a2 2 0 013 3L9 19l-4 1z"/><path d="M15 7l3 3"/>' },
                { id: 'faculdade', nome: 'Faculdade', icone: '<path d="M4 20V9l8-5 8 5v11M9 20v-6h6v6M3 20h18"/>' },
                { id: 'mapa', nome: 'Mapa mental', icone: '<circle cx="12" cy="12" r="2.5"/><circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="20" r="2"/><path d="M10 10.5L6.5 7.5M14 10.5l3.5-3M12 14.5v3.5"/>' },
                { id: 'livre', nome: 'Quadro livre', icone: '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="4" rx="1.5"/><rect x="13" y="10" width="7" height="10" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/>' },
            ],
            tiposBloco: [
                { id: 'texto', nome: 'Campo de texto' }, { id: 'tabela', nome: 'Tabela' },
                { id: 'lista', nome: 'Lista de itens' }, { id: 'imagem', nome: 'Imagem' },
                { id: 'documento', nome: 'Documento' }, { id: 'mapa', nome: 'Mapa mental' },
            ],

            // livros
            lv: vazioLivro(), lvErro: '',
            leitor: { id: null, pg: 1, total: 0, zoom: 1.3, carregando: false, erro: '', cor: HL[0], noite: false },
            bs: vazioBusca(),
            dm: { pg: '', texto: '', nota: '' },
            // estudos
            ef: { titulo: '', curso: '', data: hoje(), nota: '' }, efErro: '',
            cu: { nome: '', cor: PALETA[1] }, permAviso: 'indisponivel',
            // anotações
            nq: '', nTag: '', notaSel: null, nt: '',
            // faculdade
            df: vazioDisc(), dfErro: '', af: vazioAval(), afErro: '',

            init() {
                const guardada = sessionStorage.getItem('mente.aba');
                this.aba = this.abas.some(a => a.id === guardada) ? guardada : 'tarefas';
                this.$watch('aba', v => { sessionStorage.setItem('mente.aba', v); this.menuBloco = false; });
                // ao voltar para Livros, o painel de leitura perde a rolagem: volta para a página atual
                this.$watch('aba', v => {
                    if (v === 'livros' && pdfDoc) this.$nextTick(() => requestAnimationFrame(() => this.rolarPara(this.leitor.pg)));
                });
                this.zTop = this.maiorZ();
                this.notaSel = this.estado.notas[0]?.id || null;
                if ('Notification' in window) this.permAviso = Notification.permission;

                this.$watch('estado', () => this.agendar());

                this.initQuadro();

                const urgente = () => { if (this.salvo === 'pendente') this.gravarEstado(); };
                document.addEventListener('visibilitychange', () => { if (document.hidden) urgente(); });
                window.addEventListener('pagehide', urgente);
                if (!this.cfg.dados.estado) this.agendar();

                // revisões: confere de tempos em tempos (a página aberta avisa uma vez por dia)
                setTimeout(() => this.avisoNavegador(), 2500);
                setInterval(() => { this._v = this._v; this.avisoNavegador(); }, 60000);
            },

            hoje, uid, dataBR,
            fmt(n) { return String(r1(n)).replace('.', ','); },
            pct(v, m) { return m > 0 ? clamp(v / m * 100, 0, 100) : 0; },
            /* Ao soltar o redimensionamento, o leitor confere de novo quais páginas estão visíveis. */
            redimensionar(e, sec, id, minw, minh) {
                if (this.empilhado()) return;
                motor.redimensionar.call(this, e, sec, id, minw, minh);
                const fim = () => {
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                    if (this.aba === 'livros' && pdfDoc) this.desenhar();
                };
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },

            /* ================= LIVROS ================= */
            livroPorId(id) { return this.estado.livros.find(l => l.id === id); },
            livroAtual() { return this.livroPorId(this.leitor.id); },
            pctLivro(l) { return l.paginas > 0 ? clamp(l.atual / l.paginas * 100, 0, 100) : 0; },
            statusLivro(s) { return { lendo: 'Lendo', quero: 'Quero ler', lido: 'Lido' }[s] || s; },
            livrosOrdenados() {
                const ordem = { lendo: 0, quero: 1, lido: 2 };
                return [...this.estado.livros].sort((a, b) => (ordem[a.status] ?? 3) - (ordem[b.status] ?? 3) || a.titulo.localeCompare(b.titulo));
            },
            async criarLivro(el) {
                const f = this.lv, arq = el?.files?.[0];
                if (!f.titulo.trim() && !arq) { this.lvErro = 'Informe o título ou escolha um PDF.'; return; }
                this.estado.livros.push({
                    id: uid(), titulo: f.titulo.trim() || arq.name.replace(/\.pdf$/i, ''), autor: f.autor.trim(),
                    paginas: num(f.paginas) || 0, atual: 0, status: f.status, pdf: false,
                    cor: PALETA[this.estado.livros.length % PALETA.length], marcas: [], destaques: [],
                });
                const l = this.estado.livros[this.estado.livros.length - 1]; // versão reativa
                this.lv = vazioLivro(); this.lvErro = '';
                if (arq) { el.value = ''; await this.enviarPdf(l, arq); }
                else this.aviso('Livro adicionado.');
            },
            async enviarPdf(l, arq) {
                if (!arq) return;
                if (!/\.pdf$/i.test(arq.name) && arq.type !== 'application/pdf') { this.aviso('Escolha um arquivo PDF.'); return; }
                if (arq.size > 40 * 1024 * 1024) { this.aviso('O PDF passa de 40 MB.'); return; }
                this.aviso('Enviando PDF…');
                try {
                    const fd = new FormData();
                    fd.append('pdf', arq);
                    const r = await fetch(`${this.cfg.pdf}/${l.id}`, {
                        method: 'POST', body: fd, credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    });
                    if (r.status === 401 || r.status === 419) { location.reload(); return; }
                    if (r.status === 413) { this.aviso('O servidor recusou: arquivo grande demais.'); return; }
                    if (!r.ok) throw new Error(String(r.status));
                    l.pdf = true;
                    this.aviso('PDF enviado.');
                    this.abrirLivro(l);
                } catch { this.aviso('Não consegui enviar o PDF.'); }
            },
            excluirLivro(l) {
                if (!confirm(`Excluir "${l.titulo}", o PDF e os destaques?`)) return;
                if (l.pdf) enviar(`${this.cfg.pdf}/${l.id}`, null, 'DELETE').catch(() => {});
                if (this.leitor.id === l.id) { this.leitor.id = null; this.leitor.total = 0; pdfDoc = null; this.limparPaginas(); }
                const i = this.estado.livros.findIndex(x => x.id === l.id);
                if (i >= 0) this.estado.livros.splice(i, 1);
            },
            async abrirLivro(l) {
                this.ir('livros');
                const lt = this.leitor;
                lt.id = l.id; lt.erro = ''; lt.total = 0; pdfDoc = null;
                this.limparPaginas();
                lt.pg = Math.max(1, l.atual || 1);
                if (!l.pdf) return;
                lt.carregando = true;
                try {
                    const lib = await carregarPdfJs();
                    const r = await fetch(`${this.cfg.pdf}/${l.id}`, { credentials: 'same-origin' });
                    if (!r.ok) throw new Error(String(r.status));
                    const doc = await lib.getDocument({ data: await r.arrayBuffer() }).promise;
                    if (lt.id !== l.id) return;          // o usuário abriu outro livro enquanto carregava
                    pdfDoc = doc;
                    await this.medirPaginas();
                    if (pdfDoc !== doc) return;
                    lt.total = doc.numPages;
                    if (!l.paginas) l.paginas = lt.total;
                    lt.pg = clamp(lt.pg, 1, lt.total);
                    await this.$nextTick();
                    await new Promise(ok => requestAnimationFrame(ok));
                    this.layoutPaginas();
                    this.rolarPara(lt.pg);
                } catch { lt.erro = 'Não consegui abrir o PDF. Tente enviar de novo.'; }
                finally { lt.carregando = false; }
            },

            /* ---------- leitor em rolagem contínua ---------- */
            elPagina(n) { return this.$refs.pdfLeitor?.querySelector(`.wf-pagina[data-pg="${n}"]`) || null; },
            limparPaginas() {
                geracao++;
                estPg.forEach(st => { try { st.task?.cancel(); } catch { /* ok */ } });
                estPg.clear();
                if (obs) { obs.disconnect(); obs = null; }
                pgDim = [];
                // a busca é do PDF aberto: ao trocar de livro ela recomeça do zero
                textoPg = [];
                buscaGer++;
                this.bs = vazioBusca();
            },
            // mede todas as páginas antes de mostrar, para a barra de rolagem já nascer com o tamanho certo
            async medirPaginas() {
                const doc = pdfDoc, total = doc.numPages;
                pgDim = [];
                for (let i = 1; i <= total; i += 40) {
                    const ids = Array.from({ length: Math.min(40, total - i + 1) }, (_, k) => i + k);
                    await Promise.all(ids.map(async n => {
                        try { const v = (await doc.getPage(n)).getViewport({ scale: 1 }); pgDim[n] = { w: v.width, h: v.height }; } catch { /* usa a página 1 */ }
                    }));
                    if (doc !== pdfDoc) return;
                }
            },
            // dá o tamanho a cada página (conforme o zoom) e passa a observar quais estão perto da tela
            layoutPaginas() {
                const c = this.$refs.pdfLeitor;
                if (!c || !pdfDoc) return;
                geracao++;
                estPg.forEach(st => { try { st.task?.cancel(); } catch { /* ok */ } });
                estPg.clear();
                const z = this.leitor.zoom;
                const els = c.querySelectorAll('.wf-pagina');
                els.forEach(el => {
                    const n = +el.dataset.pg, d = pgDim[n] || pgDim[1] || { w: 612, h: 792 };
                    el.style.width = Math.round(d.w * z) + 'px';
                    el.style.height = Math.round(d.h * z) + 'px';
                    const cv = el.querySelector('canvas'), tl = el.querySelector('.textLayer');
                    if (cv) { cv.width = 0; cv.height = 0; }
                    if (tl) tl.innerHTML = '';
                });
                if (obs) obs.disconnect();
                obs = new IntersectionObserver(entradas => {
                    entradas.forEach(e => {
                        const n = +e.target.dataset.pg;
                        if (e.isIntersecting) this.renderPagina(n); else this.liberar(n);
                    });
                }, { root: c, rootMargin: '1500px 0px' });
                els.forEach(el => obs.observe(el));
            },
            async renderPagina(n) {
                const doc = pdfDoc, g = geracao;
                if (!doc || estPg.has(n)) return;
                const el = this.elPagina(n);
                if (!el) return;
                const st = {};
                estPg.set(n, st);
                try {
                    const page = await doc.getPage(n);
                    if (g !== geracao || estPg.get(n) !== st) return;
                    const vp = page.getViewport({ scale: this.leitor.zoom });
                    const cv = el.querySelector('canvas'), tl = el.querySelector('.textLayer');
                    if (!cv || !tl) { estPg.delete(n); return; }
                    const dpr = window.devicePixelRatio || 1;
                    el.style.width = Math.round(vp.width) + 'px';
                    el.style.height = Math.round(vp.height) + 'px';
                    cv.width = Math.round(vp.width * dpr); cv.height = Math.round(vp.height * dpr);
                    st.task = page.render({ canvasContext: cv.getContext('2d'), viewport: vp, transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null });
                    await st.task.promise;
                    if (g !== geracao || estPg.get(n) !== st) return;
                    tl.innerHTML = '';
                    tl.style.setProperty('--scale-factor', vp.scale);
                    const conteudo = await page.getTextContent();
                    if (g !== geracao || estPg.get(n) !== st) return;
                    pdfLib.renderTextLayer({ textContentSource: conteudo, textContent: conteudo, container: tl, viewport: vp, textDivs: [] });
                } catch { if (estPg.get(n) === st) estPg.delete(n); }
            },
            // páginas que saíram da área próxima à tela são esvaziadas para poupar memória
            liberar(n) {
                const st = estPg.get(n);
                if (!st) return;
                try { st.task?.cancel(); } catch { /* ok */ }
                estPg.delete(n);
                const el = this.elPagina(n);
                if (!el) return;
                const cv = el.querySelector('canvas'), tl = el.querySelector('.textLayer');
                if (cv) { cv.width = 0; cv.height = 0; }
                if (tl) tl.innerHTML = '';
            },
            // reconfere o que está visível (usado depois de redimensionar o cartão)
            desenhar() {
                const c = this.$refs.pdfLeitor;
                if (!pdfDoc || !obs || !c) return;
                c.querySelectorAll('.wf-pagina').forEach(el => { obs.unobserve(el); obs.observe(el); });
            },
            // descobre a página que está na parte de cima do painel enquanto você rola
            aoRolar() {
                if (rafRolar) return;
                rafRolar = requestAnimationFrame(() => {
                    rafRolar = 0;
                    const c = this.$refs.pdfLeitor;
                    if (!pdfDoc || !c || !c.clientHeight || Date.now() < travaScroll) return;
                    const els = c.querySelectorAll('.wf-pagina');
                    if (!els.length) return;
                    const ref = c.scrollTop + Math.min(120, c.clientHeight / 3);
                    let lo = 0, hi = els.length - 1;
                    while (lo < hi) {
                        const mid = (lo + hi + 1) >> 1;
                        if (els[mid].offsetTop <= ref) lo = mid; else hi = mid - 1;
                    }
                    const n = lo + 1, lt = this.leitor;
                    if (n !== lt.pg) {
                        lt.pg = n;
                        const l = this.livroAtual();
                        if (l) l.atual = n;
                    }
                });
            },
            // rola o painel até a página n (fy = altura em % dentro da página, opcional)
            rolarPara(n, fy = 0) {
                const c = this.$refs.pdfLeitor, el = this.elPagina(n);
                if (!c || !el || !c.clientHeight) return;
                travaScroll = Date.now() + 300;
                c.scrollTop = Math.max(0, el.offsetTop + (fy ? el.offsetHeight * fy / 100 - 60 : -8));
            },
            // guarda e recoloca a posição de leitura (usado no zoom, para você não se perder)
            ancora() {
                const c = this.$refs.pdfLeitor, el = this.elPagina(this.leitor.pg);
                if (!c || !el || !el.offsetHeight) return null;
                return { n: this.leitor.pg, f: (c.scrollTop - el.offsetTop) / el.offsetHeight };
            },
            restaurar(a) {
                if (!a) return;
                const c = this.$refs.pdfLeitor, el = this.elPagina(a.n);
                if (!c || !el) return;
                travaScroll = Date.now() + 300;
                c.scrollTop = el.offsetTop + a.f * el.offsetHeight;
            },
            irPagina(n, fy = 0) {
                const lt = this.leitor;
                if (!pdfDoc) return;
                n = clamp(Math.round(num(n)) || 1, 1, lt.total);
                lt.pg = n;
                const l = this.livroAtual();
                if (l) l.atual = n;
                this.rolarPara(n, fy);
            },
            zoom(d) {
                const lt = this.leitor, a = this.ancora();
                lt.zoom = clamp(r1(lt.zoom + d), 0.6, 3);
                this.layoutPaginas();
                this.restaurar(a);
            },

            /* ---------- busca de texto no PDF ---------- */
            // texto normalizado da página + de onde vem cada pedaço (para achar o lugar na tela)
            async textoDaPagina(n) {
                if (textoPg[n]) return textoPg[n];
                const doc = pdfDoc;
                const page = await doc.getPage(n);
                const vp = page.getViewport({ scale: 1 });
                const c = await page.getTextContent();
                if (doc !== pdfDoc) return null;
                let texto = '';
                const segs = [];
                c.items.forEach(it => {
                    if (typeof it.str !== 'string' || !it.str) { if (it.hasEOL) texto += ' '; return; }
                    const a = texto.length;
                    for (let i = 0; i < it.str.length; i++) texto += norm1(it.str[i]);
                    segs.push({ a, b: texto.length, it });
                    if (it.hasEOL) texto += ' ';
                });
                return (textoPg[n] = { texto, segs, vp });
            },
            // converte um trecho [s, e) do texto da página em retângulos (em % da página)
            rectsDe(t, s, e) {
                const out = [], W = t.vp.width, H = t.vp.height;
                t.segs.forEach(sg => {
                    const oa = Math.max(s, sg.a), ob = Math.min(e, sg.b);
                    if (oa >= ob) return;
                    const tx = sg.it.transform, len = sg.b - sg.a;
                    const larg = Math.abs(sg.it.width) || 0;
                    const alt = Math.abs(sg.it.height) || Math.hypot(tx[2], tx[3]) || 10;
                    const fa = (oa - sg.a) / len, fb = (ob - sg.a) / len;
                    const [x1, y1] = t.vp.convertToViewportPoint(tx[4] + larg * fa, tx[5] - alt * 0.2);
                    const [x2, y2] = t.vp.convertToViewportPoint(tx[4] + larg * fb, tx[5] + alt * 0.85);
                    out.push({
                        x: r2(Math.min(x1, x2) / W * 100), y: r2(Math.min(y1, y2) / H * 100),
                        w: Math.max(0.3, r2(Math.abs(x2 - x1) / W * 100)), h: r2(Math.abs(y2 - y1) / H * 100),
                    });
                });
                return out;
            },
            async buscar(forcar = false) {
                const bs = this.bs, q = bs.q.trim();
                if (!forcar && q === bs.feitoQ) return;
                const ger = ++buscaGer;
                bs.res = []; bs.porPg = {}; bs.i = -1; bs.cheio = false; bs.prog = 0;
                bs.feitoQ = q; bs.ocupado = false;
                const doc = pdfDoc;
                if (!q || !doc) return;
                const nq = normTxt(q);
                if (!nq) return;
                const total = doc.numPages;
                bs.ocupado = true;
                try {
                    for (let ini = 1; ini <= total; ini += 8) {
                        const ids = Array.from({ length: Math.min(8, total - ini + 1) }, (_, k) => ini + k);
                        const textos = await Promise.all(ids.map(n => this.textoDaPagina(n).catch(() => null)));
                        if (ger !== buscaGer || doc !== pdfDoc) return;
                        for (let k = 0; k < ids.length; k++) {
                            const n = ids[k], t = textos[k];
                            if (!t) continue;
                            let pos = 0;
                            while ((pos = t.texto.indexOf(nq, pos)) !== -1) {
                                if (this.bs.res.length >= MAX_BUSCA) { this.bs.cheio = true; break; }
                                const rects = this.rectsDe(t, pos, pos + nq.length);
                                const i = this.bs.res.length;
                                this.bs.res.push({ i, pg: n, rects });
                                if (!this.bs.porPg[n]) this.bs.porPg[n] = [];
                                this.bs.porPg[n].push({ i, rects });
                                pos += nq.length;
                            }
                            if (this.bs.cheio) break;
                        }
                        this.bs.prog = ids[ids.length - 1];
                        if (this.bs.i < 0 && this.bs.res.length) this.irResultado(0);
                        if (this.bs.cheio) break;
                    }
                } finally {
                    if (ger === buscaGer) { this.bs.ocupado = false; this.bs.prog = total; }
                }
            },
            // Enter: busca se o texto mudou; senão vai para a próxima ocorrência (Shift+Enter volta)
            aoEnter(e) {
                if (this.bs.q.trim() !== this.bs.feitoQ) { this.buscar(); return; }
                if (e.shiftKey) this.anterior(); else this.proximo();
            },
            irResultado(i) {
                const n = this.bs.res.length;
                if (!n) return;
                i = ((i % n) + n) % n;
                this.bs.i = i;
                const r = this.bs.res[i];
                this.irPagina(r.pg, r.rects[0]?.y || 0);
            },
            proximo() { this.irResultado(this.bs.i + 1); },
            anterior() { this.irResultado(this.bs.i < 0 ? -1 : this.bs.i - 1); },
            limparBusca() {
                buscaGer++;
                this.bs = vazioBusca();
            },
            buscaDe(n) { return this.bs.porPg[n] || []; },
            rotuloBusca() {
                const bs = this.bs;
                if (!bs.feitoQ) return '';
                if (!bs.res.length) return bs.ocupado ? 'Buscando…' : 'Nenhum resultado';
                return `${bs.i + 1} de ${bs.res.length}${bs.cheio ? '+' : ''}`;
            },

            // destaques: o texto selecionado vira retângulos em % da página (valem em qualquer zoom)
            capturar() {
                const sel = window.getSelection(), l = this.livroAtual();
                if (!sel || sel.isCollapsed || !l || !sel.rangeCount) return;
                const range = sel.getRangeAt(0);
                const no = range.startContainer.nodeType === 1 ? range.startContainer : range.startContainer.parentElement;
                const pagEl = no?.closest?.('.wf-pagina');       // a página onde a seleção começa
                if (!pagEl) return;
                const pg = +pagEl.dataset.pg;
                const area = pagEl.getBoundingClientRect();
                const texto = sel.toString().replace(/\s+/g, ' ').trim();
                const rects = [...range.getClientRects()]
                    .filter(r => r.width > 2 && r.height > 2 && r.height < area.height / 3)
                    .filter(r => {
                        const cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                        return cx >= area.left && cx <= area.right && cy >= area.top && cy <= area.bottom;
                    })
                    .map(r => ({
                        x: r2((r.left - area.left) / area.width * 100), y: r2((r.top - area.top) / area.height * 100),
                        w: r2(r.width / area.width * 100), h: r2(r.height / area.height * 100),
                    }));
                if (!texto || !rects.length) return;
                l.destaques.push({ id: uid(), pg, texto: texto.slice(0, 1500), nota: '', cor: this.leitor.cor, rects, fav: false });
                sel.removeAllRanges();
            },
            destPg() { return this.destDe(this.leitor.pg); },
            destDe(n) { return (this.livroAtual()?.destaques || []).filter(d => d.pg === n && d.rects?.length); },
            destaquesOrdenados(l) { return [...(l?.destaques || [])].sort((a, b) => a.pg - b.pg); },
            irDestaque(d) { if (pdfDoc) this.irPagina(d.pg, d.rects?.[0]?.y || 0); },
            remDestaque(l, id) { const i = l.destaques.findIndex(x => x.id === id); if (i >= 0) l.destaques.splice(i, 1); },
            addTrecho() {
                const l = this.livroAtual(), f = this.dm;
                if (!l || !f.texto.trim()) return;
                l.destaques.push({ id: uid(), pg: Math.max(1, Math.round(num(f.pg)) || this.leitor.pg || 1), texto: f.texto.trim(), nota: f.nota.trim(), cor: this.leitor.cor, rects: [], fav: false });
                this.dm = { pg: '', texto: '', nota: '' };
            },
            addMarca() {
                const l = this.livroAtual();
                if (!l) return;
                const pg = this.leitor.pg;
                if (l.marcas.some(m => m.pg === pg)) { this.aviso('Essa página já está marcada.'); return; }
                l.marcas.push({ id: uid(), pg, rotulo: 'Página ' + pg });
                l.marcas.sort((a, b) => a.pg - b.pg);
            },
            remMarca(l, id) { const i = l.marcas.findIndex(x => x.id === id); if (i >= 0) l.marcas.splice(i, 1); },
            marcada() { return !!this.livroAtual()?.marcas.some(m => m.pg === this.leitor.pg); },
            destaqueParaNota(l, d) {
                const n = { id: uid(), titulo: `${l.titulo} · p. ${d.pg}`, texto: `“${d.texto}”${d.nota ? '\n\n' + d.nota : ''}`, tags: ['livro'], fixada: false, cor: d.cor, atualizada: Date.now() };
                this.estado.notas.push(n);
                this.aviso('Trecho enviado para Anotações.');
            },

            /* ================= ESTUDOS ================= */
            intervalo() { return Math.max(1, Math.round(num(this.estado.estudos.intervalo)) || 7); },
            diasDesde(it) { return diasEntre(it.ultimo, hoje()); },
            proxima(it) { return addDias(it.ultimo, this.intervalo()); },
            vencido(it) { return !it.arq && this.diasDesde(it) >= this.intervalo(); },
            aRevisar() {
                return this.estado.estudos.itens.filter(i => this.vencido(i)).sort((a, b) => this.diasDesde(b) - this.diasDesde(a));
            },
            proximas() {
                return this.estado.estudos.itens.filter(i => !i.arq && !this.vencido(i)).sort((a, b) => this.proxima(a).localeCompare(this.proxima(b)));
            },
            pendencias() { this._v; return this.aRevisar().length; },
            statusEstudo(it) {
                if (it.arq) return 'Arquivado';
                const d = this.diasDesde(it);
                if (this.vencido(it)) {
                    const atraso = d - this.intervalo();
                    return atraso > 0 ? `Revisão atrasada há ${atraso} dia${atraso > 1 ? 's' : ''}` : 'Revisar hoje';
                }
                return `Estudado em ${dataBR(it.ultimo)} · revisão em ${dataBR(this.proxima(it))}`;
            },
            cursoDe(id) { return this.estado.estudos.cursos.find(c => c.id === id); },
            gruposEstudo() {
                const g = this.estado.estudos.cursos.map(c => ({ id: c.id, nome: c.nome, cor: c.cor, itens: this.estado.estudos.itens.filter(i => i.curso === c.id) }));
                const ids = this.estado.estudos.cursos.map(c => c.id);
                g.push({ id: '', nome: 'Sem curso', cor: '#94a3b8', itens: this.estado.estudos.itens.filter(i => !ids.includes(i.curso)) });
                return g.filter(x => x.itens.length);
            },
            salvarEstudo() {
                const f = this.ef;
                if (!f.titulo.trim()) { this.efErro = 'Dê um nome ao conteúdo.'; return; }
                const d = f.data || hoje();
                this.estado.estudos.itens.push({ id: uid(), titulo: f.titulo.trim(), curso: f.curso, ultimo: d, hist: [d], nota: f.nota.trim(), arq: false });
                this.ef = { titulo: '', curso: f.curso, data: hoje(), nota: '' }; this.efErro = '';
                this.aviso('Conteúdo marcado como estudado. A revisão chega em ' + this.intervalo() + ' dias.');
            },
            estudei(it) {
                const d = hoje();
                if (it.ultimo !== d) { it.hist.push(d); if (it.hist.length > 60) it.hist.shift(); }
                it.ultimo = d; it.arq = false;
                this.aviso(`“${it.titulo}” revisado. Próxima revisão em ${dataBR(this.proxima(it))}.`);
            },
            excluirEstudo(it) {
                if (!confirm(`Excluir "${it.titulo}"?`)) return;
                const i = this.estado.estudos.itens.findIndex(x => x.id === it.id);
                if (i >= 0) this.estado.estudos.itens.splice(i, 1);
            },
            addCurso() {
                const f = this.cu;
                if (!f.nome.trim()) return;
                this.estado.estudos.cursos.push({ id: uid(), nome: f.nome.trim(), cor: f.cor });
                this.cu = { nome: '', cor: PALETA[(this.estado.estudos.cursos.length + 1) % PALETA.length] };
            },
            remCurso(c) {
                if (!confirm(`Excluir o curso "${c.nome}"? Os conteúdos ficam em "Sem curso".`)) return;
                const i = this.estado.estudos.cursos.findIndex(x => x.id === c.id);
                if (i >= 0) this.estado.estudos.cursos.splice(i, 1);
            },
            async ativarAvisos() {
                if (!('Notification' in window)) { this.aviso('Seu navegador não suporta avisos.'); return; }
                this.permAviso = await Notification.requestPermission();
                if (this.permAviso === 'granted') { localStorage.removeItem('mente.notif'); this.avisoNavegador(); }
            },
            avisoNavegador() {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                const n = this.aRevisar().length;
                if (!n || localStorage.getItem('mente.notif') === hoje()) return;
                localStorage.setItem('mente.notif', hoje());
                try { new Notification('Hora de revisar', { body: n === 1 ? '1 conteúdo para revisar hoje.' : `${n} conteúdos para revisar hoje.` }); } catch { /* ok */ }
            },

            /* ================= ANOTAÇÕES ================= */
            notaAtual() { return this.estado.notas.find(n => n.id === this.notaSel); },
            todasTags() { return [...new Set(this.estado.notas.flatMap(n => n.tags))].sort(); },
            notasFiltradas() {
                const q = this.nq.trim().toLowerCase();
                return this.estado.notas
                    .filter(n => (!this.nTag || n.tags.includes(this.nTag)) && (!q || (n.titulo + ' ' + n.texto + ' ' + n.tags.join(' ')).toLowerCase().includes(q)))
                    .sort((a, b) => (b.fixada - a.fixada) || (b.atualizada - a.atualizada));
            },
            novaNota(titulo = '') {
                const t = typeof titulo === 'string' ? titulo.trim() : '';
                const n = {
                    id: uid(), titulo: t || 'Sem título', texto: '', tags: this.nTag ? [this.nTag] : [],
                    fixada: false, cor: PALETA[this.estado.notas.length % PALETA.length], atualizada: Date.now(),
                };
                this.estado.notas.push(n);
                this.notaSel = n.id;
                this.nt = ''; this.nq = '';
                this.focarNota(t ? 'texto' : 'titulo');
            },
            editarNota(n) { this.notaSel = n.id; this.focarNota('titulo'); },
            focarNota(campo) {
                setTimeout(() => {
                    const el = document.getElementById('nota-' + campo);
                    if (!el) return;
                    el.focus();
                    if (campo === 'titulo') el.select();
                }, 80);
            },
            duplicarNota(n) {
                const c = { ...n, id: uid(), titulo: n.titulo + ' (cópia)', tags: [...n.tags], fixada: false, atualizada: Date.now() };
                this.estado.notas.push(c);
                this.notaSel = c.id;
                this.aviso('Anotação duplicada.');
            },
            addTag(n, el) {
                const novas = this.tagsDe(el.value);
                if (!novas.length) { el.value = ''; return; }
                n.tags = [...new Set([...n.tags, ...novas])];
                el.value = '';
                this.tocar(n);
            },
            remTag(n, t) { n.tags = n.tags.filter(x => x !== t); this.tocar(n); },
            contagem(n) {
                const p = this.palavras(n), c = n.texto.length;
                return `${p} palavra${p === 1 ? '' : 's'} · ${c} caractere${c === 1 ? '' : 's'}`;
            },
            excluirNota(n) {
                if (!confirm(`Excluir a anotação "${n.titulo}"?`)) return;
                const i = this.estado.notas.findIndex(x => x.id === n.id);
                if (i >= 0) this.estado.notas.splice(i, 1);
                if (this.notaSel === n.id) this.notaSel = this.estado.notas[0]?.id || null;
            },
            tocar(n) { n.atualizada = Date.now(); },
            tagsDe(txt) { return [...new Set(String(txt).split(',').map(t => t.trim().toLowerCase()).filter(Boolean))]; },
            palavras(n) { const t = n.texto.trim(); return t ? t.split(/\s+/).length : 0; },
            quando(n) { const d = new Date(n.atualizada); return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`; },
            resumoNota(n) { return n.texto.replace(/\s+/g, ' ').slice(0, 90) || 'Vazia'; },

            /* ================= FACULDADE ================= */
            discPorId(id) { return this.estado.fac.disciplinas.find(d => d.id === id); },
            semana() {
                const d0 = new Date(), idx = (d0.getDay() + 6) % 7;
                return DIAS.map((d, i) => {
                    const dt = new Date(d0.getFullYear(), d0.getMonth(), d0.getDate() - idx + i);
                    return { ...d, data: iso(dt), num: dt.getDate(), hoje: i === idx };
                });
            },
            aulasDoDia(id) {
                return this.estado.fac.disciplinas
                    .flatMap(d => d.hor.filter(h => h.dia === id).map(h => ({ k: d.id + h.k, nome: d.nome, cor: d.cor, ini: h.ini, fim: h.fim, sala: d.sala })))
                    .sort((a, b) => a.ini.localeCompare(b.ini));
            },
            avalsDaData(data) { return this.estado.fac.avaliacoes.filter(a => a.data === data && !a.feito); },
            media(d) {
                const l = this.estado.fac.avaliacoes.filter(a => a.disc === d.id && a.nota !== '' && a.nota !== null);
                const pesos = l.reduce((s, a) => s + (num(a.peso) || 1), 0);
                return pesos ? r1(l.reduce((s, a) => s + num(a.nota) * (num(a.peso) || 1), 0) / pesos) : null;
            },
            restantes(d) { return d.limite > 0 ? Math.max(0, d.limite - d.faltas) : null; },
            avalsOrdenadas() {
                return [...this.estado.fac.avaliacoes].sort((a, b) => (a.feito - b.feito) || a.data.localeCompare(b.data));
            },
            prazoTxt(a) {
                const d = diasEntre(hoje(), a.data);
                if (a.feito) return 'Concluída';
                return d < 0 ? `venceu há ${-d} dia${d < -1 ? 's' : ''}` : d === 0 ? 'hoje' : d === 1 ? 'amanhã' : `em ${d} dias`;
            },
            addHorario() { this.df.hor.push({ k: uid(), dia: 'seg', ini: '08:00', fim: '10:00' }); },
            editarDisc(d) {
                this.df = { id: d.id, nome: d.nome, prof: d.prof || '', sala: d.sala || '', cor: d.cor, limite: d.limite || '', hor: d.hor.map(h => ({ ...h })) };
                this.dfErro = ''; this.ir('faculdade');
            },
            cancelarDisc() { this.df = vazioDisc(); this.dfErro = ''; },
            salvarDisc() {
                const f = this.df;
                if (!f.nome.trim()) { this.dfErro = 'Informe o nome da disciplina.'; return; }
                const dados = { nome: f.nome.trim(), prof: f.prof.trim(), sala: f.sala.trim(), cor: f.cor, limite: Math.max(0, Math.round(num(f.limite))), hor: f.hor.map(h => ({ ...h })) };
                if (f.id) { const d = this.discPorId(f.id); if (d) Object.assign(d, dados); }
                else this.estado.fac.disciplinas.push({ id: uid(), faltas: 0, ...dados });
                this.df = vazioDisc(); this.dfErro = '';
                this.aviso('Disciplina salva.');
            },
            excluirDisc(d) {
                if (!confirm(`Excluir "${d.nome}" e as avaliações dela?`)) return;
                this.estado.fac.avaliacoes = this.estado.fac.avaliacoes.filter(a => a.disc !== d.id);
                const i = this.estado.fac.disciplinas.findIndex(x => x.id === d.id);
                if (i >= 0) this.estado.fac.disciplinas.splice(i, 1);
            },
            salvarAval() {
                const f = this.af;
                if (!f.disc) { this.afErro = 'Escolha a disciplina.'; return; }
                if (!f.titulo.trim()) { this.afErro = 'Dê um título.'; return; }
                this.estado.fac.avaliacoes.push({ id: uid(), disc: f.disc, tipo: f.tipo, titulo: f.titulo.trim(), data: f.data || hoje(), peso: num(f.peso) || 1, nota: f.nota === '' ? '' : num(f.nota), feito: f.nota !== '' });
                this.af = { ...vazioAval(), disc: f.disc }; this.afErro = '';
            },
            remAval(id) { const i = this.estado.fac.avaliacoes.findIndex(x => x.id === id); if (i >= 0) this.estado.fac.avaliacoes.splice(i, 1); },

            /* ================= blocos livres ================= */
            tipoNome(t) { if (t === 'vivo') return 'Cartão ativo'; return this.tiposBloco.find(x => x.id === t)?.nome || t; },
            secoesModelo(m) { return (MODELOS[m] || MODELOS.branco).secoes.map(([t, texto]) => ({ id: uid(), t, texto })); },
            novoBloco(sec, tipo) {
                const def = {
                    texto: [340, 240, 'Texto', { texto: '', tam: 'm' }],
                    tabela: [480, 280, 'Tabela', { cab: ['Item', 'Valor'], linhas: [['', ''], ['', '']] }],
                    lista: [320, 300, 'Lista', { itens: [] }],
                    imagem: [360, 300, 'Imagem', { ajuste: 'cover', legenda: '' }],
                    documento: [520, 520, 'Documento', { modelo: 'branco', layout: 'coluna', fonte: 'sans', cor: PALETA[0], secoes: this.secoesModelo('branco') }],
                    mapa: [720, 480, 'Mapa mental', { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }] }],
                }[tipo];
                if (!def) return;
                const id = 'b' + uid();
                const W = this.col || 1088, y0 = this.alturaConteudo(sec);
                this.estado.blocos.push({ id, secao: sec, tipo, titulo: def[2], links: [], dados: def[3] });
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = { x: 0, y: y0 ? y0 + 16 : 0, w: Math.min(def[0], W), h: def[1], z: ++this.zTop };
                this.menuBloco = false;
                this.aviso(`${def[2]} adicionado ao fim da aba. Arraste para onde quiser.`);
            },
            // documento (currículo, contrato, resumo…)
            trocarModelo(b, m) {
                if (!m || !MODELOS[m]) return;
                const preenchido = b.dados.secoes.some(s => s.texto.trim());
                if (preenchido && !confirm('Trocar o modelo substitui as seções atuais. Continuar?')) return;
                b.dados.modelo = m;
                b.dados.secoes = this.secoesModelo(m);
                if (m === 'curriculo') { b.dados.layout = 'faixa'; b.dados.fonte = 'sans'; }
                if (m === 'contrato') { b.dados.layout = 'coluna'; b.dados.fonte = 'serif'; }
                if (b.titulo === 'Documento' || Object.values(MODELOS).some(x => x.nome === b.titulo)) b.titulo = MODELOS[m].nome;
            },
            imprimirDoc(b) {
                const d = b.dados, w = window.open('', '_blank');
                if (!w) { this.aviso('Permita pop-ups para imprimir.'); return; }
                const secs = d.secoes.map((s, i) => `<section class="${i === 0 && d.layout === 'faixa' ? 'cab' : ''}"><h2>${esc(s.t)}</h2><p>${esc(s.texto).replace(/\n/g, '<br>')}</p></section>`).join('');
                w.document.write(`<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>${esc(b.titulo)}</title><style>
                    body{font-family:${FONTES[d.fonte]};color:#1b1f24;margin:2cm;line-height:1.5}
                    h2{font-size:1rem;margin:0 0 .25rem;border-bottom:2px solid ${d.cor};padding-bottom:.15rem}
                    section{break-inside:avoid;margin-bottom:1rem}p{margin:0}
                    .cab{background:${d.cor};color:#fff;padding:1rem;border-radius:6px}.cab h2{border-color:#fff3;font-size:1.5rem}
                    main{${d.layout === 'duas' ? 'columns:2;column-gap:1.5cm' : ''}}
                    </style><main>${secs}</main><script>onload=()=>print()<\/script></html>`);
                w.document.close();
            },

            // vínculos
            rotuloLink(l) {
                const e = this.estado;
                switch (l.t) {
                    case 'livro': return e.livros.find(x => x.id === l.id)?.titulo || 'livro removido';
                    case 'estudo': return e.estudos.itens.find(x => x.id === l.id)?.titulo || 'conteúdo removido';
                    case 'nota': return e.notas.find(x => x.id === l.id)?.titulo || 'anotação removida';
                    case 'disc': return e.fac.disciplinas.find(x => x.id === l.id)?.nome || 'disciplina removida';
                    default: return this.tarefas.find(t => String(t.id) === String(l.id))?.titulo || 'tarefa removida';
                }
            },
            iconeLink(t) { return { livro: '📖', estudo: '🎓', nota: '📝', disc: '🏛️', tarefa: '✓' }[t] || '🔗'; },
            abrirLink(l) {
                if (l.t === 'livro') { const x = this.livroPorId(l.id); if (x) this.abrirLivro(x); return; }
                const dest = { estudo: 'estudos', nota: 'notas', disc: 'faculdade', tarefa: 'tarefas' }[l.t];
                if (l.t === 'nota') this.notaSel = l.id;
                if (dest) this.ir(dest);
                if (l.t === 'estudo') { this.destaque = 'estudo:' + l.id; setTimeout(() => { this.destaque = null; }, 2200); }
            },
        };
    };
})();