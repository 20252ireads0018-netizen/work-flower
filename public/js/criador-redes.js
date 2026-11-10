/* Work Flower · cartão "Criador de conteúdo": abas Instagram e TikTok (servidor em RedesController).
   Fica separado do cartoes-extra.js (que cuida dos posts e do YouTube): carregue DEPOIS dele.
   Cada pessoa conecta a própria conta por OAuth (tokens só no servidor) ou importa os números à mão
   (planilha CSV exportada da rede, ou seguidores/posts digitados). Os dois aparecem do mesmo jeito.
   A última leitura fica no cartão: b.dados.igSnap / b.dados.ttSnap = { conta, itens, em, manual, hist }.
   Cada número, post, a lista e o gráfico podem ir para o quadro (arrastando ou pelo botão): js/integracoes-quadro.js.
   Tela em partials/criador-redes.blade.php. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const TAG = document.currentScript;
    // base /redes (vem da tag do script, porque o site pode estar numa subpasta)
    const URL_REDES = (TAG && TAG.dataset.redes) || '';
    // "Publicar no Instagram" (InstagramPublicarController); vazio = a rota não existe e o botão não aparece
    const URL_PUB = (TAG && TAG.dataset.publicar) || '';
    const MAX_IMG = 10, MAX_IMG_BYTES = 8 * 1024 * 1024, MAX_LEGENDA = 2200;
    // elementos arrastáveis para o quadro (mesma pasta deste arquivo)
    if (!window.WFIntegra && TAG && TAG.src) {
        const s = document.createElement('script');
        s.src = TAG.src.replace(/criador-redes\.js/, 'integracoes-quadro.js');
        document.head.appendChild(s);
    }
    const SNAP = { ig: 'igSnap', tt: 'ttSnap' }, ROTA = { ig: 'instagram', tt: 'tiktok' }, NOME = { ig: 'Instagram', tt: 'TikTok' };
    const COR = { ig: '#e1306c', tt: '#25c4d4' };
    const curto = n => (n == null ? '—' : n >= 1e6 ? (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : n >= 1e4 ? (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : Number(n).toLocaleString('pt-BR'));
    const dataBR = s => String(s || '').split('-').reverse().join('/');
    const diaMes = s => (s ? dataBR(s).slice(0, 5) : '');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const MAX_CSV = 2 * 1024 * 1024;

    async function pedir(url, metodo = 'GET', corpo) {
        const r = await fetch(url, {
            method: metodo, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-WF-Dono': '' },
            body: corpo ? JSON.stringify(corpo) : undefined,
        });
        const j = await r.json().catch(() => ({}));
        return { r, j };
    }

    const extras = {
        /** Estado da tela das redes, por cartão (pelo proxy do Alpine, para reagir). */
        crRdUI(b) {
            this.wfRdUI[b.id] ??= {
                carregando: false, erro: '', conectar: '', configurar: false, importar: false, enviando: false, grafico: 'posts',
                imp: { usuario: '', seguidores: '', seguindo: '', posts: '', curtidas: '', csv: '', arquivo: '' },
            };
            return this.wfRdUI[b.id];
        },
        crMetrica(b) { return ['yt', 'ig', 'tt'].includes(this.crUI(b).ver); },
        crRedeAberta(b) { return !!SNAP[this.crUI(b).ver]; },
        crRedeNome(b) { return NOME[this.crUI(b).ver] || ''; },
        crVer(b, ver) {
            const ui = this.crUI(b), rd = this.crRdUI(b);
            ui.ver = ver; ui.aberto = null;
            Object.assign(rd, { erro: '', conectar: '', configurar: false, importar: false });
            if (SNAP[ver] && !b.dados[SNAP[ver]]) this.crRede(b, ver);
        },
        crSnap(b) { const k = SNAP[this.crUI(b).ver]; return k ? b.dados[k] || null : null; },
        /** Guarda a leitura no cartão (mesmo formato vindo da API ou da importação). */
        crRdGuardar(b, ver, j) {
            b.dados[SNAP[ver]] = { conta: j.conta || {}, itens: j.posts || j.videos || [], em: j.em, manual: !!j.manual, hist: j.historico || {} };
        },
        async crRede(b, ver, forcar) {
            const rd = this.crRdUI(b);
            if (!URL_REDES) { rd.erro = 'As métricas de redes não estão disponíveis nesta página.'; return; }
            Object.assign(rd, { carregando: true, erro: '', conectar: '', configurar: false });
            try {
                const { r, j } = await pedir(URL_REDES + '/' + ROTA[ver] + '/metricas' + (forcar ? '?forcar=1' : ''));
                rd.conectar = j.conectar ? j.conectar + '?voltar=' + encodeURIComponent(location.href) : '';
                if (r.ok && j.ok) this.crRdGuardar(b, ver, j);
                else {
                    rd.erro = j.erro || 'Não deu para buscar as métricas agora. Tente de novo em alguns minutos.';
                    rd.configurar = j.configurado === false;
                    if (j.importar && !this.crSnap(b)) rd.importar = true;
                }
            } catch (e) { rd.erro = 'Sem conexão com o servidor.'; }
            rd.carregando = false;
        },
        async crRedeSair(b) {
            const ver = this.crUI(b).ver;
            if (!SNAP[ver] || !confirm(`Desconectar a sua conta do ${NOME[ver]} deste site? Para ver as métricas de novo, será preciso conectar outra vez ou importar os números.`)) return;
            try {
                await pedir(URL_REDES + '/' + ROTA[ver], 'DELETE');
                delete b.dados[SNAP[ver]];
                this.crRede(b, ver);
            } catch (e) { this.aviso('Sem conexão com o servidor.'); }
        },

        /* ---------- importação manual (planilha CSV ou números digitados) ---------- */
        crRdAbrirImportar(b) {
            const rd = this.crRdUI(b), c = this.crSnap(b)?.conta || {};
            rd.importar = !rd.importar;
            if (rd.importar) Object.assign(rd.imp, { usuario: rd.imp.usuario || c.usuario || '', seguidores: '', seguindo: '', posts: '', curtidas: '' });
        },
        crRdArquivo(b, ev) {
            const f = ev.target.files && ev.target.files[0], rd = this.crRdUI(b);
            if (!f) return;
            if (f.size > MAX_CSV) { rd.erro = 'A planilha passa de 2 MB. Exporte um período menor.'; ev.target.value = ''; return; }
            const leitor = new FileReader();
            leitor.onload = () => { rd.imp.csv = String(leitor.result || ''); rd.imp.arquivo = f.name; };
            leitor.onerror = () => { rd.erro = 'Não consegui ler o arquivo. Abra-o numa planilha e cole as linhas aqui.'; };
            leitor.readAsText(f, 'utf-8');
            ev.target.value = '';
        },
        async crRdImportar(b) {
            const ver = this.crUI(b).ver, rd = this.crRdUI(b);
            if (!SNAP[ver] || rd.enviando) return;
            const corpo = {};
            Object.entries(rd.imp).forEach(([k, v]) => { if (k !== 'arquivo' && String(v).trim() !== '') corpo[k] = String(v).trim(); });
            if (!Object.keys(corpo).length) { rd.erro = 'Cole a planilha ou preencha pelo menos um número.'; return; }
            rd.enviando = true; rd.erro = '';
            try {
                const { r, j } = await pedir(URL_REDES + '/' + ROTA[ver] + '/importar', 'POST', corpo);
                if (r.ok && j.ok) {
                    this.crRdGuardar(b, ver, j);
                    rd.importar = false;
                    Object.assign(rd.imp, { csv: '', arquivo: '' });
                    this.aviso(j.mensagem || 'Números importados.');
                } else rd.erro = j.erro || j.message || 'Não deu para importar agora.';
            } catch (e) { rd.erro = 'Sem conexão com o servidor.'; }
            rd.enviando = false;
        },
        async crRdApagarImportacao(b) {
            const ver = this.crUI(b).ver;
            if (!confirm(`Apagar os números do ${NOME[ver]} que você importou?`)) return;
            await pedir(URL_REDES + '/' + ROTA[ver] + '/importar', 'DELETE').catch(() => {});
            delete b.dados[SNAP[ver]];
            this.crRdUI(b).importar = false;
            this.crRede(b, ver);
        },

        /* ---------- números, lista e gráfico ---------- */
        crRdNum(n) { return curto(n); },
        crRdQuando(b) { const s = this.crSnap(b); return s ? new Date(s.em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
        crRdColunas(b) { return this.crUI(b).ver === 'ig' ? ['Curtidas', 'Coment.', 'Alcance', 'Views'] : ['Views', 'Curtidas', 'Coment.', 'Compart.']; },
        crRdValores(b, v) { return this.crUI(b).ver === 'ig' ? [v.curtidas, v.comentarios, v.alcance, v.views] : [v.views, v.curtidas, v.comentarios, v.compart]; },
        crRdResumo(b) {
            const c = this.crSnap(b)?.conta || {};
            return this.crUI(b).ver === 'ig'
                ? [[c.seguidores, 'seguidores'], [c.posts, 'posts'], [c.seguindo, 'seguindo']]
                : [[c.seguidores, 'seguidores'], [c.curtidas, 'curtidas'], [c.videos, 'vídeos']];
        },
        /** Engajamento médio dos itens (curtidas + comentários por visualização). */
        crRdEngaj(b) {
            const l = (this.crSnap(b)?.itens || []).filter(v => v.views);
            if (!l.length) return null;
            const t = l.reduce((s, v) => s + ((v.curtidas || 0) + (v.comentarios || 0)) / v.views, 0) / l.length;
            return (t * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
        },
        /** Séries do gráfico: views/curtidas dos últimos itens (mais antigo à esquerda) ou seguidores por dia. */
        crRdSerie(b) {
            const s = this.crSnap(b), ver = this.crUI(b).ver;
            if (!s) return { rotulos: [], series: [], cab: [], linhas: [] };
            if (this.crRdUI(b).grafico === 'seg') {
                const h = Object.entries(s.hist || {}).slice(-30);
                return { tipo: 'linhas', rotulos: h.map(([d]) => diaMes(d)), series: [{ nome: 'Seguidores', valores: h.map(([, n]) => n) }],
                    cab: ['Dia', 'Seguidores'], linhas: h.map(([d, n]) => [dataBR(d), n]) };
            }
            const itens = (s.itens || []).slice(0, 12).reverse();
            const temViews = itens.some(v => v.views), campo = temViews ? 'views' : 'curtidas', nome = temViews ? 'Visualizações' : 'Curtidas';
            const rot = itens.map((v, i) => diaMes(v.data) || `#${i + 1}`);
            return { tipo: 'barras', rotulos: rot, series: [{ nome, valores: itens.map(v => v[campo] ?? 0) }, { nome: 'Curtidas', valores: itens.map(v => v.curtidas ?? 0) }].slice(0, temViews ? 2 : 1),
                cab: [ver === 'ig' ? 'Post' : 'Vídeo', nome].concat(temViews ? ['Curtidas'] : []),
                linhas: itens.map((v, i) => [rot[i], v[campo] ?? 0].concat(temViews ? [v.curtidas ?? 0] : [])) };
        },
        crRdGrafico(b) {
            const s = this.crRdSerie(b), G = window.WFGraficos, I = window.WFIntegra;
            if (!s.rotulos.length) return '';
            const cores = [COR[this.crUI(b).ver], '#94a3b8'];
            if (G && typeof G.svgGrafico === 'function') return G.svgGrafico(s.tipo, s.rotulos, s.series.map((x, i) => ({ ...x, cor: cores[i] })), {});
            return I ? I.barras(s.rotulos, s.series[0].valores, { cor: COR[this.crUI(b).ver] }) : '';
        },
        crRdTemHist(b) { return Object.keys(this.crSnap(b)?.hist || {}).length >= 2; },

        /* ---------- para o quadro ---------- */
        crRdQuem(b) { const c = this.crSnap(b)?.conta || {}; return NOME[this.crUI(b).ver] + (c.usuario ? ' @' + c.usuario : ''); },
        crRdBlocoNumero(b, r) {
            return { tipo: 'texto', titulo: this.crRdQuem(b), w: 300, h: 160,
                dados: { tam: 'g', texto: `${Number(r[0] || 0).toLocaleString('pt-BR')} ${r[1]}\n${this.crRdQuem(b)} · ${this.crRdQuando(b)}` } };
        },
        crRdBlocoItem(b, v) {
            const ig = this.crUI(b).ver === 'ig', cols = this.crRdColunas(b), vals = this.crRdValores(b, v);
            const linhaNums = cols.map((c, i) => `${c}: ${vals[i] == null ? '—' : Number(vals[i]).toLocaleString('pt-BR')}`).join(' · ');
            return { tipo: 'texto', titulo: `${NOME[this.crUI(b).ver]} · ${ig ? (v.tipo || 'Post') : 'Vídeo'} ${dataBR(v.data)}`.trim(), w: 360, h: 220,
                dados: { tam: 'm', texto: [(ig ? v.legenda : v.titulo) || '(sem legenda)', linhaNums, v.link || ''].filter(Boolean).join('\n\n') } };
        },
        crRdBlocoLista(b) {
            const cols = this.crRdColunas(b), ig = this.crUI(b).ver === 'ig';
            return { tipo: 'tabela', titulo: `${this.crRdQuem(b)} · últimos ${ig ? 'posts' : 'vídeos'}`, w: 640, h: 360,
                dados: { cab: ['Data', ig ? 'Post' : 'Vídeo', ...cols, 'Link'], linhas: (this.crSnap(b)?.itens || []).slice(0, 50).map(v =>
                    [dataBR(v.data), (ig ? v.legenda : v.titulo) || '', ...this.crRdValores(b, v).map(n => (n == null ? '' : String(n))), v.link || '']) } };
        },
        crRdBlocoGrafico(b) {
            const s = this.crRdSerie(b), I = window.WFIntegra;
            const titulo = `${this.crRdQuem(b)} · ${this.crRdUI(b).grafico === 'seg' ? 'seguidores' : (s.series[0]?.nome || '').toLowerCase()}`;
            return { tipo: 'grafico', titulo, w: 480, h: 360, dados: I ? I.grafico(s.tipo, s.cab, s.linhas) : { cab: s.cab, linhas: s.linhas } };
        },
        /* ---------- publicar no Instagram (foto ou carrossel com legenda) ---------- */
        crPubTem() { return !!URL_PUB; },
        crPubUI(b) {
            this.wfRdUI['pub' + b.id] ??= { aberto: false, estado: null, carregando: false, imagens: [], legenda: '', post: '', enviando: false, erro: '', feito: null };
            return this.wfRdUI['pub' + b.id];
        },
        async crPubAbrir(b) {
            const p = this.crPubUI(b);
            p.aberto = !p.aberto;
            p.erro = ''; p.feito = null;
            if (!p.aberto) return;
            p.carregando = true;
            try {
                const { r, j } = await pedir(URL_PUB);
                p.estado = r.ok ? j : null;
                if (!r.ok) p.erro = j.message || 'Não deu para conferir a conta agora.';
            } catch (e) { p.erro = 'Sem conexão com o servidor.'; }
            p.carregando = false;
        },
        /** Link para conectar de novo pedindo a permissão de publicar (volta para esta página). */
        crPubConectar(b) {
            const e = this.crPubUI(b).estado;
            return e && e.conectar ? e.conectar + '&voltar=' + encodeURIComponent(location.href) : '';
        },
        crPubArquivos(b, ev) {
            const p = this.crPubUI(b);
            p.erro = '';
            for (const f of Array.from(ev.target.files || [])) {
                if (p.imagens.length >= MAX_IMG) { p.erro = `O carrossel aceita até ${MAX_IMG} imagens.`; break; }
                if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { p.erro = `“${f.name}” não é JPG, PNG ou WebP.`; continue; }
                if (f.size > MAX_IMG_BYTES) { p.erro = `“${f.name}” passa de 8 MB.`; continue; }
                p.imagens.push({ arquivo: f, url: URL.createObjectURL(f), nome: f.name });
            }
            ev.target.value = '';
        },
        crPubTirar(b, i) {
            const p = this.crPubUI(b), [x] = p.imagens.splice(i, 1);
            if (x) URL.revokeObjectURL(x.url);
        },
        crPubMover(b, i, d) {
            const l = this.crPubUI(b).imagens, j = i + d;
            if (j < 0 || j >= l.length) return;
            [l[i], l[j]] = [l[j], l[i]];
        },
        /** Posts do Criador (rede Instagram) que podem dar a legenda. */
        crPubPosts(b) { return (b.dados.posts || []).filter(x => x.rede === 'Instagram' && x.etapa !== 'Publicado'); },
        crPubUsarPost(b) {
            const p = this.crPubUI(b), x = (b.dados.posts || []).find(y => y.id === p.post);
            if (x) p.legenda = [x.legenda || '', x.tags || ''].filter(Boolean).join('\n\n').slice(0, MAX_LEGENDA);
        },
        async crPubEnviar(b) {
            const p = this.crPubUI(b);
            if (p.enviando) return;
            if (!p.imagens.length) { p.erro = 'Escolha pelo menos uma imagem.'; return; }
            if (p.legenda.length > MAX_LEGENDA) { p.erro = `A legenda vai até ${MAX_LEGENDA} caracteres.`; return; }
            const n = p.imagens.length;
            if (!confirm(n > 1 ? `Publicar este carrossel com ${n} imagens no Instagram agora?` : 'Publicar esta foto no Instagram agora?')) return;
            const fd = new FormData();
            p.imagens.forEach(x => fd.append('imagens[]', x.arquivo, x.nome));
            fd.append('legenda', p.legenda);
            p.enviando = true; p.erro = ''; p.feito = null;
            try {
                const r = await fetch(URL_PUB, { method: 'POST', credentials: 'same-origin', body: fd,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() } });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) {
                    p.feito = { link: j.link || '' };
                    this.aviso(j.mensagem || 'Publicado no Instagram.');
                    // o post do Criador que deu a legenda passa para "Publicado" (ou entra um novo)
                    const hoje = new Date().toISOString().slice(0, 10);
                    const x = (b.dados.posts || []).find(y => y.id === p.post);
                    if (x) Object.assign(x, { etapa: 'Publicado', data: hoje, link: j.link || x.link || '' });
                    else {
                        b.dados.posts ??= [];
                        b.dados.posts.push({ id: Q.util.uid(), titulo: (p.legenda.split('\n')[0] || 'Post no Instagram').slice(0, 120), rede: 'Instagram', etapa: 'Publicado',
                            data: hoje, legenda: p.legenda, tags: '', link: j.link || '' });
                    }
                    p.imagens.forEach(y => URL.revokeObjectURL(y.url));
                    Object.assign(p, { imagens: [], legenda: '', post: '' });
                    if (typeof this.agendar === 'function') this.agendar();
                    this.crRede(b, 'ig', true);
                } else {
                    p.erro = j.erro || j.message || 'O Instagram não publicou agora. Tente de novo.';
                    if (j.errors) p.erro = Object.values(j.errors).flat()[0] || p.erro;
                    if (j.permissao && p.estado) p.estado.pode = false;
                }
            } catch (e) { p.erro = 'Sem conexão com o servidor.'; }
            p.enviando = false;
        },

        crRdArrastar(ev, bloco) { if (window.WFIntegra) window.WFIntegra.arrastar(ev, bloco); },
        crRdPor(bloco) {
            if (window.WFIntegra) window.WFIntegra.porNoQuadro(bloco);
            else this.aviso('Um instante: carregando… tente de novo.');
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        // o crUI do cartoes-extra devolve o objeto cru na primeira vez (valor de um ??=); aqui devolve sempre
        // pelo proxy do Alpine, senão o botão "Posts" não acompanha a troca de aba
        const crUI = m.crUI;
        if (typeof crUI === 'function') m.crUI = function (b) { crUI.call(this, b); return this.wfCxUI[b.id]; };
        return Object.assign(m, extras, { wfRdUI: {} });
    };
})();
