/* Work Flower · área Diverso · cartão "Banco de dados (.sql)"
   Abre um dump .sql (MySQL/MariaDB, PostgreSQL com INSERT ou COPY, SQLite) ou um .csv, desenha as tabelas e as
   ligações (chaves estrangeiras) e deixa consultar os dados.
   - O trabalho pesado fica no worker (js/banco-sql-worker.js): leitura em fluxo, compressão, envio, busca,
     filtros, ordem e estatísticas. A página só desenha o que está visível (linhas e tabelas do diagrama).
   - Nada de SQL cru vai ao servidor (o mod_security da hospedagem recusa com 406):
       img.<id>.sql                     índice comprimido: "wfsql2:" + base64(gzip(JSON com estrutura e contagens))
       sqlz.<id>.<versão>.<tabela>.<n>  linhas, em pedaços de gzip+base64 (< 6 MB cada), baixados só quando a tabela abre
     Formato antigo (até 11/2026): b.dados.tabelas no estado e img.<id>.sql com as linhas em JSON. Continua abrindo.
   - b.dados guarda só o leve: versao, arquivo, resumo, visao e as posições das tabelas que a pessoa arrastou.
   Estende o diverso.js + diagrama.js: carregue depois deles; continua usando x-data="diversoAbasDiagrama()". */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof window.diversoAbasDiagrama !== 'function') return;
    const { uid } = Q.util;
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const num = n => Number(n || 0).toLocaleString('pt-BR');
    const raw = x => (window.Alpine && typeof window.Alpine.raw === 'function' ? window.Alpine.raw(x) : x);
    const clone = x => JSON.parse(JSON.stringify(raw(x)));
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';

    const eu = document.currentScript;
    const URL_WORKER = (eu && eu.dataset.worker) || ((eu && eu.src) || '/js/banco-sql.js').replace(/banco-sql\.js/, 'banco-sql-worker.js');
    const MAX_ARQUIVO = 500 * 1024 * 1024;
    const PREFIXO = 'wfsql2:';
    const BOX_W = 220, CAB_H = 28, LIN_H = 18, MOSTRA_COL = 20;
    const LIN = 26, MAX_ALTURA = 8000000, POR_PAGINA = 100;
    const NUM_TXT = /^-?\d{1,15}(\.\d+)?$/;

    /* =================== conversa com o worker =================== */
    let W = null, pidN = 0;
    const pend = new Map();
    function worker() {
        if (W) return W;
        W = new Worker(URL_WORKER);
        W.onmessage = e => {
            const d = e.data || {}, p = pend.get(d.pid);
            if (!p) return;
            if (d.parcial) { p.parcial && p.parcial(d); return; }
            pend.delete(d.pid);
            if (d.t === 'erro') { const er = new Error(d.erro); er.status = d.status; p.falha(er); } else p.ok(d);
        };
        W.onerror = e => {
            console.error('[Banco .sql] worker', e);
            pend.forEach(p => p.falha(new Error('worker')));
            pend.clear();
            W = null;
        };
        return W;
    }
    function pedir(msg, parcial) {
        return new Promise((ok, falha) => {
            const pid = ++pidN;
            pend.set(pid, { ok, falha, parcial });
            worker().postMessage({ ...msg, pid });
        });
    }
    function cfgServidor(ctx) {
        return { url: new URL(ctx.cfg.url, location.href).href.replace(/\/$/, ''), csrf: meta('csrf-token'), dono: meta('wf-dono') };
    }
    const comprimir = async texto => (await pedir({ acao: 'comprimir', texto })).v;
    const descomprimir = async b64 => (await pedir({ acao: 'descomprimir', b64 })).v;

    /** Erro → frase para a pessoa. */
    function mensagemErro(e) {
        const s = e && e.status, m = String((e && e.message) || '');
        if (m === 'cancelado') return 'Leitura cancelada.';
        if (s === 401 || s === 419) return 'Sua sessão expirou. Recarregue a página, entre de novo e abra o arquivo outra vez.';
        if (s === 403) return 'Você só pode ver este workflow: o dono não liberou a edição para você.';
        if (s === 404) return 'Parte dos dados não está mais no servidor. Abra o arquivo de novo.';
        if (s === 406 || s === 422) return 'O servidor recusou os dados (' + s + '). Tente de novo; se continuar, avise o suporte.';
        if (s === 413) return 'Um pedaço dos dados ficou grande demais para o servidor (413). Avise o suporte.';
        if (s === 429) return 'Muitos envios seguidos. Espere um minuto e tente de novo.';
        if (s === 0) return 'Sem conexão com o servidor. Confira a internet e tente de novo.';
        if (s >= 500) return 'O servidor teve um problema (' + s + '). Tente de novo daqui a pouco.';
        if (m === 'worker') return 'Este navegador não conseguiu ler o arquivo (pouca memória?). Feche outras abas e tente de novo.';
        return 'Não consegui ler este arquivo. Confira se é um .sql ou .csv em texto (UTF-8).';
    }
    function tempo(ms) {
        if (!isFinite(ms) || ms < 0) return '';
        const s = Math.round(ms / 1000);
        if (s < 5) return 'menos de 5 s';
        if (s < 90) return `cerca de ${s} s`;
        return `cerca de ${Math.round(s / 60)} min`;
    }
    const mb = b => (b < 1048576 ? Math.max(b ? 1 : 0, Math.round(b / 1024)) + ' KB' : (b / 1048576).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' MB');

    /* =================== sessões (uma por cartão) =================== */
    const sessoes = new Map();

    /* =================== diagrama: arrumação automática =================== */
    function alturaTabela(t) {
        const n = Math.min(t.colunas.length, MOSTRA_COL) + (t.colunas.length > MOSTRA_COL ? 1 : 0);
        return CAB_H + Math.max(1, n) * LIN_H + 8;
    }
    /** Camadas por chave estrangeira (as referenciadas à esquerda), componentes ligados lado a lado, soltas em grade. */
    function arrumar(tabelas) {
        const n = tabelas.length, por = new Map(tabelas.map((t, i) => [t.nome.toLowerCase(), i]));
        const pais = tabelas.map(() => new Set()), filhos = tabelas.map(() => new Set());
        const raiz = tabelas.map((_, i) => i);
        const achar = i => { while (raiz[i] !== i) i = raiz[i] = raiz[raiz[i]]; return i; };
        tabelas.forEach((t, i) => t.colunas.forEach(c => {
            const j = c.fk ? por.get(String(c.fk.t).toLowerCase()) : undefined;
            if (j === undefined || j === i) return;
            pais[i].add(j); filhos[j].add(i);
            raiz[achar(i)] = achar(j);
        }));
        const camada = new Array(n).fill(0);
        for (let volta = 0; volta < 25; volta++) {
            let mudou = false;
            for (let i = 0; i < n; i++) for (const p of pais[i]) if (camada[p] + 1 > camada[i] && camada[p] + 1 < 25) { camada[i] = camada[p] + 1; mudou = true; }
            if (!mudou) break;
        }
        const grupos = new Map();
        for (let i = 0; i < n; i++) { const r = achar(i); (grupos.get(r) || grupos.set(r, []).get(r)).push(i); }
        const pos = new Array(n);
        const blocos = [];
        const soltas = [];
        for (const membros of grupos.values()) {
            if (membros.length === 1) { soltas.push(membros[0]); continue; }
            const porCamada = new Map();
            membros.forEach(i => (porCamada.get(camada[i]) || porCamada.set(camada[i], []).get(camada[i])).push(i));
            const cams = [...porCamada.keys()].sort((a, b) => a - b);
            let w = 0, h = 0, x = 0;
            const ys = new Map();
            // camada com muitas tabelas quebra em várias colunas (o componente fica perto de quadrado)
            const areaC = membros.reduce((s, i) => s + (BOX_W + 60) * (alturaTabela(tabelas[i]) + 30), 0);
            const hMax = Math.max(1400, Math.sqrt(areaC) * 0.9);
            cams.forEach(c => {
                const lista = porCamada.get(c);
                // ordem: média da altura dos pais já colocados (cruza menos linhas)
                const m = i => { const ps = [...pais[i]].filter(p => ys.has(p)); return ps.length ? ps.reduce((s, p) => s + ys.get(p), 0) / ps.length : (filhos[i].size ? -1 : 1e9); };
                const chave = new Map(lista.map(i => [i, m(i)]));
                lista.sort((a, b) => chave.get(a) - chave.get(b) || filhos[b].size - filhos[a].size);
                let y = 0;
                lista.forEach(i => {
                    const ht = alturaTabela(tabelas[i]);
                    if (y > 0 && y + ht > hMax) { x += BOX_W + 40; y = 0; }
                    pos[i] = { x, y }; ys.set(i, y); y += ht + 30; h = Math.max(h, y);
                });
                w = x + BOX_W;
                x += BOX_W + 90;
            });
            blocos.push({ membros, w, h });
        }
        if (soltas.length) {
            const cols = Math.max(1, Math.ceil(Math.sqrt(soltas.length)));
            const alt = new Array(cols).fill(0);
            soltas.sort((a, b) => tabelas[a].nome.localeCompare(tabelas[b].nome)).forEach((i, k) => {
                const c = k % cols;
                pos[i] = { x: c * (BOX_W + 40), y: alt[c] };
                alt[c] += alturaTabela(tabelas[i]) + 30;
            });
            blocos.push({ membros: soltas, w: cols * (BOX_W + 40), h: Math.max(...alt) });
        }
        // prateleiras: blocos maiores primeiro, linha até ~1,6 × a raiz da área total
        blocos.sort((a, b) => b.membros.length - a.membros.length);
        const area = blocos.reduce((s, b) => s + b.w * b.h, 0);
        const largura = Math.max(1400, Math.sqrt(area) * 1.6);
        let x = 20, y = 20, alturaLinha = 0;
        blocos.forEach(b => {
            if (x > 20 && x + b.w > largura) { x = 20; y += alturaLinha + 80; alturaLinha = 0; }
            b.membros.forEach(i => { pos[i].x += x; pos[i].y += y; });
            x += b.w + 100; alturaLinha = Math.max(alturaLinha, b.h);
        });
        return pos;
    }

    /* =================== o cartão =================== */
    function Cartao(ctx, b, el) {
        this.ctx = ctx; this.b = b; this.el = el;
        this.s = sessoes.get(b.id) || { id: b.id, schema: [], avisos: [], versao: '', idx: null };
        sessoes.set(b.id, this.s);
        this.s.cartao = this;
        this.ui = this.s.ui || (this.s.ui = { visao: b.dados.visao === 'dados' || b.dados.visao === 'stats' ? b.dados.visao : 'diagrama', ti: 0, busca: '', filtros: {}, ordem: null, vp: null });
        this.montarDom();
        this.desenhar();
        this.sinc(true);
    }
    const C = Cartao.prototype;

    C.montarDom = function () {
        const el = this.el;
        const soVer = !!meta('wf-so-ver');
        el.innerHTML = `
            <div class="bd-barra">
                <label class="btn-sec !py-1 cursor-pointer bd-abrir" ${soVer ? 'hidden' : ''}>
                    <span data-r="abrir-txt">Abrir arquivo .sql ou .csv</span>
                    <input type="file" accept=".sql,.csv,.tsv,.txt,application/sql,text/csv,text/plain" class="sr-only" data-r="arquivo">
                </label>
                <div class="bd-abas" role="tablist" aria-label="Ver" data-r="abas" hidden>
                    <button type="button" class="btn-sec !py-1" role="tab" data-visao="diagrama">Diagrama</button>
                    <button type="button" class="btn-sec !py-1" role="tab" data-visao="dados">Dados</button>
                    <button type="button" class="btn-sec !py-1" role="tab" data-visao="stats">Estatísticas</button>
                </div>
                <span class="flex-1"></span>
                <button type="button" class="btn-sec !py-1 bd-tela" data-r="tela" title="Ver em tela cheia">
                    <span class="bd-tela-entrar">⤢ Tela cheia</span><span class="bd-tela-sair">✕ Sair da tela cheia</span>
                </button>
                <button type="button" class="mini-btn perigo bd-limpar" data-r="limpar" title="Tirar o banco do cartão" aria-label="Tirar o banco do cartão" hidden>✕</button>
            </div>
            <p class="text-xs texto-2" data-r="resumo" hidden></p>
            <div class="bd-prog" data-r="prog" hidden role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="bd-prog-topo"><span data-r="prog-txt">Lendo…</span><button type="button" class="btn-sec !py-0.5 !px-2 text-xs" data-r="cancelar">Cancelar</button></div>
                <div class="bd-prog-trilho"><div class="bd-prog-barra" data-r="prog-barra"></div></div>
                <p class="text-xs texto-2" data-r="prog-sub"></p>
            </div>
            <div class="bd-msg" data-r="msg" role="status" hidden></div>
            <div class="bd-avisos" data-r="avisos" hidden></div>
            <div class="bd-vazio flex-1" data-r="vazio" hidden>
                <p class="font-semibold text-sm">Solte aqui um arquivo .sql ou .csv</p>
                <p class="text-xs texto-2 mt-1">ou clique em <b>Abrir arquivo</b>. Vale dump do phpMyAdmin/mysqldump (MySQL e MariaDB),
                    do pg_dump (PostgreSQL, com INSERT ou COPY), do SQLite, e planilhas .csv. Até 500 MB.</p>
                <p class="text-xs texto-2 mt-1">O arquivo é lido no seu navegador. Ao servidor vão só os dados já comprimidos, para você
                    (e quem tem acesso a este workflow) consultar depois.</p>
            </div>
            <div class="bd-corpo flex-1 min-h-0" data-r="corpo" hidden></div>`;
        this.r = {};
        el.querySelectorAll('[data-r]').forEach(n => { this.r[n.dataset.r] = n; });
        this.r.arquivo.addEventListener('change', e => { const f = e.target.files[0]; e.target.value = ''; this.ler(f); });
        this.r.abas.addEventListener('click', e => { const v = e.target.closest('[data-visao]'); if (v) this.mudarVisao(v.dataset.visao); });
        this.r.limpar.addEventListener('click', () => this.ctx.bdLimpar(this.b));
        this.r.cancelar.addEventListener('click', () => { if (this.lendo) pedir({ acao: 'cancelar', id: this.b.id }); });
        this.r.tela.addEventListener('click', () => { const bt = el.closest('.wf-item')?.querySelector('.wf-fs-btn'); if (bt) bt.click(); });
        el.addEventListener('dragover', e => { e.preventDefault(); e.stopPropagation(); el.classList.add('bd-soltando'); });
        el.addEventListener('dragleave', e => { if (!el.contains(e.relatedTarget)) el.classList.remove('bd-soltando'); });
        el.addEventListener('drop', e => { e.preventDefault(); e.stopPropagation(); el.classList.remove('bd-soltando'); if (!soVer) this.ler(e.dataTransfer.files[0]); });
    };

    C.msg = function (texto, erro) {
        const m = this.r.msg;
        m.hidden = !texto;
        m.textContent = texto || '';
        m.classList.toggle('erro', !!erro);
    };

    /** Lê de novo o que está guardado (ao montar, e quando outra pessoa troca o arquivo). */
    C.sinc = async function (forcar) {
        const b = this.b, s = this.s;
        const idx = raw(this.ctx.imagens[b.id + '.sql']);
        if (this.lendo) return;
        try {
            if (typeof idx === 'string' && idx.startsWith(PREFIXO)) {
                if (s.idx !== idx) {
                    const j = JSON.parse(await descomprimir(idx.slice(PREFIXO.length)));
                    s.idx = idx; s.schema = j.tabelas || []; s.avisos = j.avisos || []; s.versao = j.versao; s.formato = j.formato; s.semLinhas = false;
                    await pedir({ acao: 'preparar', id: b.id, versao: s.versao, tabelas: s.schema, cfg: cfgServidor(this.ctx) });
                    s.pos = null;
                    forcar = true;
                }
            } else if ((b.dados.tabelas || []).length) {
                // formato antigo: estrutura no estado, linhas (se vieram) em img.<id>.sql como JSON
                const chave = 'antigo:' + (b.dados.lido || '') + ':' + b.dados.tabelas.length + ':' + (idx ? 1 : 0);
                if (s.idx !== chave) {
                    s.idx = chave; s.schema = clone(b.dados.tabelas).map(t => ({ ...t, pedacos: 0 })); s.avisos = clone(b.dados.avisos || []); s.versao = 'antigo';
                    s.semLinhas = !idx;
                    await pedir({ acao: 'preparar', id: b.id, versao: 'antigo', tabelas: s.schema, cfg: cfgServidor(this.ctx), legado: idx && typeof idx === 'object' ? raw(idx) : {} });
                    s.pos = null;
                    forcar = true;
                }
            } else if (s.idx) {
                s.idx = null; s.schema = []; s.avisos = []; s.versao = ''; forcar = true;
            }
            // cartão restaurado do histórico ou colado da biblioteca: o índice e as linhas não vêm junto
            if (!s.idx && b.dados.versao && b.dados.arquivo && !this.r.msg.textContent)
                this.msg(`Os dados de "${b.dados.arquivo}" não vieram junto com este cartão (restaurado ou copiado). Abra o arquivo de novo para ver as tabelas.`);
        } catch (e) {
            console.error('[Banco .sql] índice', e);
            this.msg('Não consegui abrir o que estava guardado neste cartão. Abra o arquivo de novo.', true);
        }
        if (forcar) this.desenhar();
    };

    C.resumo = function () {
        const ts = this.s.schema;
        const total = ts.reduce((s, t) => s + (t.total || 0), 0);
        const fks = ts.reduce((s, t) => s + t.colunas.filter(c => c.fk).length, 0);
        return `${this.b.dados.arquivo || 'arquivo'} · ${num(ts.length)} ${ts.length === 1 ? 'tabela' : 'tabelas'} · ${num(fks)} ${fks === 1 ? 'ligação' : 'ligações'} · ${num(total)} ${total === 1 ? 'linha' : 'linhas'}`;
    };

    C.desenhar = function () {
        const tem = this.s.schema.length > 0, r = this.r;
        r.abas.hidden = !tem; r.limpar.hidden = !tem || !!meta('wf-so-ver');
        r.resumo.hidden = !tem; r.resumo.textContent = tem ? this.resumo() : '';
        r['abrir-txt'].textContent = tem ? 'Trocar arquivo' : 'Abrir arquivo .sql ou .csv';
        r.vazio.hidden = tem || !!this.lendo;
        r.corpo.hidden = !tem;
        const avisos = this.s.avisos || [];
        r.avisos.hidden = !avisos.length;
        r.avisos.innerHTML = avisos.map(a => `<p>⚠ ${esc(a)}</p>`).join('');
        if (tem) this.mudarVisao(this.ui.visao, true);
        else { if (this.vista && this.vista.sair) this.vista.sair(); r.corpo.innerHTML = ''; this.vista = null; }
    };

    C.mudarVisao = function (v, forcar) {
        if (!forcar && v === this.ui.visao && this.vista) return;
        this.ui.visao = v;
        if (this.b.dados.visao !== v) this.b.dados.visao = v;
        this.r.abas.querySelectorAll('[data-visao]').forEach(x => { const on = x.dataset.visao === v; x.classList.toggle('bd-on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        if (this.vista && this.vista.sair) this.vista.sair();
        this.r.corpo.innerHTML = '';
        this.vista = v === 'dados' ? new Grade(this) : v === 'stats' ? new Estatisticas(this) : new Diagrama(this);
    };

    /* ---------- abrir um arquivo ---------- */
    C.ler = async function (arq) {
        if (!arq || this.lendo) return;
        const ctx = this.ctx, b = this.b, s = this.s;
        if (!/\.(sql|csv|tsv|txt)$/i.test(arq.name)) { this.msg('Escolha um arquivo .sql (dump do banco) ou .csv (planilha).', true); return; }
        if (arq.size > MAX_ARQUIVO) { this.msg(`O arquivo tem ${mb(arq.size)}; o limite é 500 MB. Exporte só as tabelas que quer ver.`, true); return; }
        if (!arq.size) { this.msg('O arquivo está vazio.', true); return; }
        if (typeof CompressionStream !== 'function' || typeof Worker !== 'function') { this.msg('Este navegador é antigo demais para abrir bancos. Use um Chrome, Edge, Firefox ou Safari atualizado.', true); return; }
        if (s.schema.length && !confirm('Trocar o banco deste cartão pelo novo arquivo?')) return;
        const cfg = cfgServidor(ctx);
        const versao = uid().replace(/[^a-z0-9]/gi, '').slice(-7);
        const antiga = s.versao && s.versao !== 'antigo' ? s.versao : null;
        // cartão sem banco guardado: tira restos de uma leitura interrompida (página fechada no meio do envio)
        if (!antiga) await pedir({ acao: 'apagar', cfg, chave: `sqlz.${b.id}`, servidor: true }).catch(() => {});
        this.lendo = true;
        this.msg('');
        const r = this.r;
        r.prog.hidden = false; r.vazio.hidden = true;
        r.arquivo.disabled = true;
        const t0 = performance.now();
        const pintar = d => {
            const fl = d.total ? d.lidos / d.total : 0;
            let frac, txt;
            if (d.fase === 'lendo') {
                const estimado = fl > 0.02 ? d.bytesEnviar / fl : 0;
                const fe = estimado ? d.bytesEnviados / estimado : 0;
                frac = estimado ? Math.min(fl, (fl + fe) / 2) : fl * 0.5;
                txt = `Lendo e enviando… ${Math.floor(frac * 100)}%`;
            } else {
                frac = d.bytesEnviar ? d.bytesEnviados / d.bytesEnviar : 0.99;
                txt = `Terminando o envio… ${Math.floor(Math.min(0.99, frac) * 100)}%`;
            }
            frac = Math.max(0, Math.min(0.99, frac));
            const ms = performance.now() - t0;
            const falta = frac > 0.03 ? ms / frac - ms : NaN;
            r['prog-txt'].textContent = txt + (falta ? ` · falta ${tempo(falta)}` : '');
            r['prog-barra'].style.width = (frac * 100).toFixed(1) + '%';
            r.prog.setAttribute('aria-valuenow', String(Math.floor(frac * 100)));
            r['prog-sub'].textContent = `${mb(d.lidos)} de ${mb(d.total)} lidos · ${mb(d.bytesEnviados)} enviados (comprimidos)`;
        };
        pintar({ fase: 'lendo', lidos: 0, total: arq.size, bytesEnviar: 0, bytesEnviados: 0 });
        let enviouAlgo = false;
        try {
            const res = await pedir({ acao: 'ler', arquivo: arq, id: b.id, versao, cfg }, d => { if (d.t === 'progresso') { enviouAlgo = enviouAlgo || d.bytesEnviar > 0; pintar(d); } });
            if (!res.tabelas.length) {
                if (res.enviado) pedir({ acao: 'apagar', cfg, chave: `sqlz.${b.id}.${versao}`, servidor: true });
                this.msg('Não achei nenhuma tabela neste arquivo (nem estrutura de tabela, nem linhas de dados, nem cabeçalho de planilha).', true);
                s.idx = null;
                return;
            }
            r['prog-txt'].textContent = 'Salvando o índice…';
            const formato = /\.(csv|tsv)$/i.test(arq.name) ? 'csv' : 'sql';
            const indice = { v: 2, versao, arquivo: arq.name.slice(0, 120), formato, bytes: arq.size, tabelas: res.tabelas, avisos: res.avisos };
            const texto = PREFIXO + await comprimir(JSON.stringify(indice));
            if (texto.length > 5900000) throw Object.assign(new Error('índice'), { status: 413 });
            await Q.enviar(`${ctx.cfg.url}/img.${b.id}.sql`, JSON.stringify(texto));
            // tudo no servidor: troca o cartão para o arquivo novo
            s.idx = texto; s.schema = res.tabelas; s.avisos = res.avisos; s.versao = versao; s.formato = formato; s.pos = null; s.semLinhas = false;
            ctx.imagens[b.id + '.sql'] = texto;
            const total = res.tabelas.reduce((a, t) => a + t.total, 0);
            b.dados.versao = versao;
            b.dados.formato = 2;
            b.dados.arquivo = arq.name.slice(0, 120);
            b.dados.lido = new Date().toISOString().slice(0, 10);
            b.dados.resumo = { tabelas: res.tabelas.length, linhas: total };
            b.dados.pos = {};
            delete b.dados.tabelas; delete b.dados.avisos;
            if (!b.titulo || b.titulo === 'Banco de dados') b.titulo = arq.name.replace(/\.(sql|csv|tsv|txt)$/i, '').slice(0, 80) || 'Banco de dados';
            this.ui.ti = 0; this.ui.busca = ''; this.ui.filtros = {}; this.ui.ordem = null; this.ui.vp = null;
            if (antiga) pedir({ acao: 'apagar', cfg, chave: `sqlz.${b.id}.${antiga}`, servidor: true });
            const seg = ((performance.now() - t0) / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
            this.msg(`Pronto: ${num(total)} linhas em ${num(res.tabelas.length)} ${res.tabelas.length === 1 ? 'tabela' : 'tabelas'}, lidas e salvas em ${seg} s (${mb(res.enviado)} enviados).`);
            console.info('[Banco .sql]', { arquivo: arq.name, bytes: arq.size, ms: Math.round(performance.now() - t0), leituraMs: res.ms, jsonBytes: res.cru, enviados: res.enviado });
        } catch (e) {
            console.error('[Banco .sql]', e);
            if (enviouAlgo) pedir({ acao: 'apagar', cfg, chave: `sqlz.${b.id}.${versao}`, servidor: true }).catch(() => {});
            this.msg(mensagemErro(e), String(e && e.message) !== 'cancelado');
            // o worker ficou com a leitura pela metade: volta ao que estava salvo
            s.idx = null;
        } finally {
            this.lendo = false;
            r.prog.hidden = true;
            r.arquivo.disabled = false;
            if (s.idx === null && (typeof raw(ctx.imagens[b.id + '.sql']) === 'string' || (b.dados.tabelas || []).length)) await this.sinc(true).catch(() => {});
            this.desenhar();
        }
    };

    /* =================== diagrama =================== */
    function Diagrama(c) {
        this.c = c;
        const ui = c.ui;
        const el = document.createElement('div');
        el.className = 'bd-diag';
        el.innerHTML = `
            <div class="bd-ferr">
                <button type="button" class="mini-btn" data-a="menos" title="Diminuir" aria-label="Diminuir">−</button>
                <button type="button" class="mini-btn !w-auto px-1.5" data-a="ajustar" title="Mostrar tudo" data-r="z">100%</button>
                <button type="button" class="mini-btn" data-a="mais" title="Aumentar" aria-label="Aumentar">+</button>
                <button type="button" class="btn-sec !py-1" data-a="arrumar" title="Arrumar as tabelas de novo (desfaz o que você arrastou)">Arrumar</button>
                <input class="campo !py-1" style="width: 11rem" type="search" placeholder="Achar tabela" aria-label="Achar tabela" data-r="achar" list="bd-l-${esc(c.b.id)}">
                <datalist id="bd-l-${esc(c.b.id)}">${c.s.schema.slice(0, 3000).map(t => `<option value="${esc(t.nome)}">`).join('')}</datalist>
                <button type="button" class="btn-sec !py-1" data-a="mapa" title="Cria um cartão de Mapa mental com as tabelas, para editar">Copiar para Mapa mental</button>
            </div>
            <div class="bd-area" data-r="area" tabindex="0" aria-label="Diagrama das tabelas. Arraste o fundo para mover, use a roda do mouse para aproximar e clique duas vezes numa tabela para ver os dados.">
                <svg class="bd-svg" width="100%" height="100%"><defs><marker id="bd-seta-${esc(c.b.id)}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path class="bd-seta" d="M0 0L10 5L0 10z"/></marker></defs><g data-r="vp"></g></svg>
                <p class="bd-dica">Arraste o fundo para mover · roda do mouse para aproximar · clique duplo numa tabela abre os dados</p>
            </div>`;
        c.r.corpo.appendChild(el);
        this.r = {};
        el.querySelectorAll('[data-r]').forEach(n => { this.r[n.dataset.r] = n; });
        this.el = el;
        this.posicoes();
        el.querySelector('.bd-ferr').addEventListener('click', e => {
            const a = e.target.closest('[data-a]');
            if (!a) return;
            const act = a.dataset.a;
            if (act === 'menos') this.zoomEm(1 / 1.25);
            else if (act === 'mais') this.zoomEm(1.25);
            else if (act === 'ajustar') this.ajustar();
            else if (act === 'arrumar') { c.b.dados.pos = {}; c.s.pos = null; this.posicoes(); this.ajustar(); }
            else if (act === 'mapa') c.ctx.bdParaMapa(c.b);
        });
        this.r.achar.addEventListener('change', () => this.achar(this.r.achar.value));
        this.r.achar.addEventListener('keydown', e => { if (e.key === 'Enter') this.achar(this.r.achar.value); });
        const area = this.r.area;
        area.addEventListener('wheel', e => {
            e.preventDefault();
            const rc = area.getBoundingClientRect();
            this.zoomEm(Math.exp(-e.deltaY * (e.deltaMode === 1 ? 0.05 : 0.0018)), e.clientX - rc.left, e.clientY - rc.top);
        }, { passive: false });
        area.addEventListener('pointerdown', e => this.pegar(e));
        area.addEventListener('dblclick', e => {
            const g = e.target.closest('[data-bd-t]');
            if (!g) return;
            c.ui.ti = Number(g.dataset.bdT); c.ui.busca = ''; c.ui.filtros = {}; c.ui.ordem = null;
            c.mudarVisao('dados');
        });
        this.ro = new ResizeObserver(() => this.agendar());
        this.ro.observe(area);
        if (!ui.vp) requestAnimationFrame(() => this.ajustar()); else this.agendar();
    }
    const D = Diagrama.prototype;
    D.sair = function () { this.ro && this.ro.disconnect(); };
    /** Posição de cada tabela: a que a pessoa arrastou, a do formato antigo, ou a da arrumação automática. */
    D.posicoes = function () {
        const s = this.c.s, ts = s.schema;
        if (!s.pos) {
            const auto = arrumar(ts), salvo = raw(this.c.b.dados.pos) || {};
            s.pos = ts.map((t, i) => {
                const p = salvo[t.nome] || (t.x != null && t.y != null ? [t.x, t.y] : null);
                return { x: p ? p[0] : auto[i].x, y: p ? p[1] : auto[i].y, h: alturaTabela(t) };
            });
        }
        s.porNome = new Map(ts.map((t, i) => [t.nome.toLowerCase(), i]));
        this.p = s.pos;
    };
    D.agendar = function () {
        if (this._raf) return;
        this._raf = requestAnimationFrame(() => { this._raf = 0; this.render(); });
    };
    D.limites = function () {
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        this.p.forEach(p => { x0 = Math.min(x0, p.x); y0 = Math.min(y0, p.y); x1 = Math.max(x1, p.x + BOX_W); y1 = Math.max(y1, p.y + p.h); });
        return isFinite(x0) ? { x0, y0, x1, y1 } : { x0: 0, y0: 0, x1: 100, y1: 100 };
    };
    D.ajustar = function () {
        const a = this.r.area, W0 = a.clientWidth || 600, H0 = a.clientHeight || 400, l = this.limites();
        const z = Math.max(0.02, Math.min(1, Math.min((W0 - 40) / (l.x1 - l.x0), (H0 - 40) / (l.y1 - l.y0))));
        this.c.ui.vp = { z, tx: (W0 - (l.x1 - l.x0) * z) / 2 - l.x0 * z, ty: Math.max(10, (H0 - (l.y1 - l.y0) * z) / 2) - l.y0 * z };
        this.agendar();
    };
    D.zoomEm = function (f, cx, cy) {
        const vp = this.c.ui.vp, a = this.r.area;
        if (!vp) return;
        if (cx == null) { cx = a.clientWidth / 2; cy = a.clientHeight / 2; }
        const z = Math.max(0.02, Math.min(2.5, vp.z * f));
        vp.tx = cx - (cx - vp.tx) * (z / vp.z); vp.ty = cy - (cy - vp.ty) * (z / vp.z); vp.z = z;
        this.agendar();
    };
    D.achar = function (nome) {
        const i = this.c.s.porNome.get(String(nome || '').trim().toLowerCase());
        if (i === undefined) return;
        const p = this.p[i], a = this.r.area, vp = this.c.ui.vp;
        vp.z = Math.max(vp.z, 0.8);
        vp.tx = a.clientWidth / 2 - (p.x + BOX_W / 2) * vp.z; vp.ty = a.clientHeight / 2 - (p.y + p.h / 2) * vp.z;
        this.realce = i;
        this.agendar();
    };
    /** Desenha só as tabelas que aparecem na tela (e as ligações delas): aguenta milhares de tabelas. */
    D.render = function () {
        const c = this.c, vp = c.ui.vp, a = this.r.area, ts = c.s.schema, P = this.p;
        if (!vp || !a.isConnected) return;
        this.r.z.textContent = Math.round(vp.z * 100) + '%';
        const W0 = a.clientWidth, H0 = a.clientHeight, m = 200 / vp.z;
        const vx0 = -vp.tx / vp.z - m, vy0 = -vp.ty / vp.z - m, vx1 = (W0 - vp.tx) / vp.z + m, vy1 = (H0 - vp.ty) / vp.z + m;
        const vis = new Uint8Array(ts.length);
        for (let i = 0; i < ts.length; i++) { const p = P[i]; if (p.x + BOX_W >= vx0 && p.x <= vx1 && p.y + p.h >= vy0 && p.y <= vy1) vis[i] = 1; }
        const simples = vp.z < 0.45, por = c.s.porNome;
        let linhas = '', caixas = '', nVis = 0;
        const seta = `url(#bd-seta-${esc(c.b.id)})`;
        for (let i = 0; i < ts.length; i++) {
            const t = ts[i];
            for (let k = 0; k < t.colunas.length; k++) {
                const col = t.colunas[k];
                if (!col.fk) continue;
                const j = por.get(String(col.fk.t).toLowerCase());
                if (j === undefined || (!vis[i] && !vis[j])) continue;
                const pa = P[i], pb = P[j];
                const li = simples ? 0 : Math.min(k, MOSTRA_COL);
                const lj = simples ? 0 : Math.min(Math.max(0, ts[j].colunas.findIndex(x => x.n === col.fk.c)), MOSTRA_COL);
                const y1 = pa.y + (simples ? CAB_H / 2 : CAB_H + li * LIN_H + LIN_H / 2 + 2), y2 = pb.y + (simples ? CAB_H / 2 : CAB_H + lj * LIN_H + LIN_H / 2 + 2);
                let d, x1;
                if (i === j) { x1 = pa.x + BOX_W; d = `M${x1} ${y1}C${x1 + 40} ${y1},${x1 + 40} ${y2},${x1} ${y2}`; }
                else if (Math.abs(pb.x - pa.x) < BOX_W / 2) { x1 = pa.x + BOX_W; const x2 = pb.x + BOX_W, dx = 40 + Math.abs(y2 - y1) * 0.1; d = `M${x1} ${y1}C${x1 + dx} ${y1},${x2 + dx} ${y2},${x2} ${y2}`; }
                else { const dir = pb.x > pa.x ? 1 : -1; x1 = dir > 0 ? pa.x + BOX_W : pa.x; const x2 = dir > 0 ? pb.x : pb.x + BOX_W, dx = Math.max(30, Math.abs(x2 - x1) / 2) * dir; d = `M${x1} ${y1}C${x1 + dx} ${y1},${x2 - dx} ${y2},${x2} ${y2}`; }
                const rl = this.realce === i || this.realce === j ? ' on' : '';
                linhas += `<path class="bd-lig${rl}" d="${d}" marker-end="${seta}"><title>${esc(t.nome + '.' + col.n + ' → ' + ts[j].nome + '.' + (col.fk.c || ''))}</title></path>`;
            }
            if (!vis[i]) continue;
            nVis++;
            const p = P[i], rl = this.realce === i ? ' on' : '';
            if (simples) {
                caixas += `<g data-bd-t="${i}" transform="translate(${p.x},${p.y})"><rect class="bd-caixa${rl}" width="${BOX_W}" height="${p.h}" rx="8"/><rect class="bd-cab" width="${BOX_W}" height="${CAB_H}" rx="8"/>`
                    + `<text class="bd-nome grande" x="10" y="${CAB_H - 6}">${esc(t.nome.length > 15 ? t.nome.slice(0, 14) + '…' : t.nome)}</text><title>${esc(t.nome)} · ${num(t.total)} linhas</title></g>`;
                continue;
            }
            let g = `<g data-bd-t="${i}" transform="translate(${p.x},${p.y})"><rect class="bd-caixa${rl}" width="${BOX_W}" height="${p.h}" rx="8"/>`
                + `<path class="bd-cab" d="M0 8a8 8 0 0 1 8-8h${BOX_W - 16}a8 8 0 0 1 8 8v${CAB_H - 8}h-${BOX_W}z"/>`
                + `<text class="bd-nome" x="10" y="18">${esc(t.nome.length > 22 ? t.nome.slice(0, 21) + '…' : t.nome)}</text>`
                + `<text class="bd-qtd" x="${BOX_W - 8}" y="18" text-anchor="end">${num(t.total)}</text>`;
            const n = Math.min(t.colunas.length, MOSTRA_COL);
            for (let k = 0; k < n; k++) {
                const col = t.colunas[k], y = CAB_H + k * LIN_H + 14;
                const tag = (col.pk ? 'PK' : '') + (col.pk && col.fk ? ',' : '') + (col.fk ? 'FK' : '') || (col.uq ? 'UK' : '');
                g += `<text class="bd-tag" x="8" y="${y}">${tag}</text><text class="bd-col${col.pk ? ' pk' : ''}" x="40" y="${y}">${esc(col.n.length > 18 ? col.n.slice(0, 17) + '…' : col.n)}</text>`
                    + `<text class="bd-tipo" x="${BOX_W - 8}" y="${y}" text-anchor="end">${esc((col.tipo || '').replace(/\s*(unsigned|zerofill)/ig, '').slice(0, 14))}</text>`;
            }
            if (t.colunas.length > MOSTRA_COL) g += `<text class="bd-tipo" x="40" y="${CAB_H + MOSTRA_COL * LIN_H + 14}">+ ${t.colunas.length - MOSTRA_COL} colunas</text>`;
            caixas += g + `<title>${esc(t.nome)}: ${t.colunas.length} colunas, ${num(t.total)} linhas · clique duplo para ver os dados</title></g>`;
        }
        const g = this.r.vp;
        g.setAttribute('transform', `translate(${vp.tx},${vp.ty}) scale(${vp.z})`);
        g.innerHTML = linhas + caixas;
        this.nVis = nVis;
    };
    /** Arrastar o fundo move a vista; arrastar uma tabela muda a posição dela (fica salva no cartão). */
    D.pegar = function (e) {
        if (e.button !== 0) return;
        const c = this.c, vp = c.ui.vp, g = e.target.closest('[data-bd-t]');
        if (!vp) return;
        e.preventDefault(); e.stopPropagation();
        const x0 = e.clientX, y0 = e.clientY, area = this.r.area;
        try { area.setPointerCapture(e.pointerId); } catch { /* sem captura */ }
        let mover, soltar;
        if (g) {
            const i = Number(g.dataset.bdT), p = this.p[i], px = p.x, py = p.y;
            let mexeu = false;
            mover = ev => { mexeu = true; p.x = Math.round((px + (ev.clientX - x0) / vp.z) / 10) * 10; p.y = Math.round((py + (ev.clientY - y0) / vp.z) / 10) * 10; this.agendar(); };
            soltar = () => {
                if (!mexeu) return;
                const nome = c.s.schema[i].nome;
                c.b.dados.pos = { ...(raw(c.b.dados.pos) || {}), [nome]: [p.x, p.y] };
            };
        } else {
            const tx = vp.tx, ty = vp.ty;
            area.classList.add('movendo');
            mover = ev => { vp.tx = tx + ev.clientX - x0; vp.ty = ty + ev.clientY - y0; this.agendar(); };
            soltar = () => area.classList.remove('movendo');
        }
        const fim = () => { area.removeEventListener('pointermove', mover); area.removeEventListener('pointerup', fim); area.removeEventListener('pointercancel', fim); soltar(); };
        area.addEventListener('pointermove', mover);
        area.addEventListener('pointerup', fim);
        area.addEventListener('pointercancel', fim);
    };

    /* =================== dados (tabela virtual) =================== */
    function Grade(c) {
        this.c = c;
        const ui = c.ui, ts = c.s.schema;
        if (!ts[ui.ti]) ui.ti = 0;
        const el = document.createElement('div');
        el.className = 'bd-grade-v';
        el.innerHTML = `
            <div class="bd-ferr">
                <select class="campo !py-1 !w-auto" style="max-width: 16rem" aria-label="Tabela" data-r="tab">${ts.map((t, i) => `<option value="${i}">${esc(t.nome)} (${num(t.total)})</option>`).join('')}</select>
                <input class="campo !py-1" style="width: 12rem" type="search" placeholder="Buscar em todas as colunas" aria-label="Buscar nas linhas" data-r="busca">
                <button type="button" class="btn-sec !py-1" data-r="limparf" title="Tira a busca, os filtros e a ordem">Limpar filtros</button>
                <button type="button" class="btn-sec !py-1" data-r="csv" title="Baixa a tabela (só o que a busca e os filtros acharam, na ordem escolhida) em CSV">Baixar CSV</button>
            </div>
            <div class="bd-rolagem" data-r="rol" tabindex="0">
                <div class="bd-cabeca" data-r="cab"></div>
                <div class="bd-corpo-v" data-r="corpo"><div class="bd-janela" data-r="jan"></div></div>
                <div class="bd-carregando" data-r="carr" hidden></div>
            </div>
            <div class="bd-detalhe" data-r="det" hidden></div>
            <div class="bd-rodape">
                <button type="button" class="mini-btn" data-r="ant" aria-label="Página anterior">‹</button>
                <span>Página <input class="campo !py-0 !px-1 bd-pag" type="number" min="1" data-r="pag" aria-label="Página"> de <span data-r="paginas">1</span></span>
                <button type="button" class="mini-btn" data-r="prox" aria-label="Próxima página">›</button>
                <span data-r="info" class="texto-2"></span>
            </div>`;
        c.r.corpo.appendChild(el);
        this.el = el; this.r = {};
        el.querySelectorAll('[data-r]').forEach(n => { this.r[n.dataset.r] = n; });
        this.r.tab.value = String(ui.ti);
        this.r.busca.value = ui.busca || '';
        this.r.tab.addEventListener('change', () => { ui.ti = Number(this.r.tab.value); ui.busca = ''; ui.filtros = {}; ui.ordem = null; this.r.busca.value = ''; this.montarTabela(); });
        let tb;
        this.r.busca.addEventListener('input', () => { clearTimeout(tb); tb = setTimeout(() => { ui.busca = this.r.busca.value; this.recarregar(); }, 250); });
        this.r.limparf.addEventListener('click', () => { ui.busca = ''; ui.filtros = {}; ui.ordem = null; this.r.busca.value = ''; this.montarTabela(); });
        this.r.csv.addEventListener('click', () => this.baixarCsv());
        this.r.rol.addEventListener('scroll', () => this.agendar(), { passive: true });
        this.r.ant.addEventListener('click', () => this.irPagina(this.pagina() - 1));
        this.r.prox.addEventListener('click', () => this.irPagina(this.pagina() + 1));
        this.r.pag.addEventListener('change', () => this.irPagina(Number(this.r.pag.value) - 1));
        this.r.cab.addEventListener('click', e => {
            const th = e.target.closest('[data-ord]');
            if (!th) return;
            const k = Number(th.dataset.ord), o = ui.ordem;
            ui.ordem = !o || o.c !== k ? { c: k, dir: 'asc' } : o.dir === 'asc' ? { c: k, dir: 'desc' } : null;
            this.pintarCabeca();
            this.recarregar();
        });
        this.r.cab.addEventListener('input', e => {
            const f = e.target.closest('[data-filtro]');
            if (!f) return;
            clearTimeout(this._tf);
            this._tf = setTimeout(() => { const fs = { ...ui.filtros, [f.dataset.filtro]: f.value }; if (!f.value) delete fs[f.dataset.filtro]; ui.filtros = fs; this.recarregar(); }, 300);
        });
        this.r.jan.addEventListener('click', e => {
            const td = e.target.closest('[data-c]');
            if (!td) return;
            const i = Number(td.parentNode.dataset.i), l = this.linhaEm(i), k = Number(td.dataset.c);
            if (!l) return;
            const col = this.t.colunas[k], v = l[k];
            this.r.det.hidden = false;
            this.r.det.innerHTML = `<b>${esc(col ? col.n : '')}</b> <span class="texto-2">(linha ${num(i + 1)})</span>: `
                + (v == null ? '<i class="texto-2">NULL</i>' : `<span class="bd-det-v">${esc(String(v).slice(0, 20000))}</span>`)
                + ' <button type="button" class="mini-btn !w-auto px-1.5" data-copiar>Copiar</button> <button type="button" class="mini-btn" data-fechar aria-label="Fechar">✕</button>';
            this.r.det.querySelector('[data-copiar]').onclick = () => navigator.clipboard && navigator.clipboard.writeText(v == null ? '' : String(v));
            this.r.det.querySelector('[data-fechar]').onclick = () => { this.r.det.hidden = true; };
        });
        this.ro = new ResizeObserver(() => this.agendar());
        this.ro.observe(this.r.rol);
        this.montarTabela();
    }
    const G = Grade.prototype;
    G.sair = function () { this.ro && this.ro.disconnect(); this.morto = true; };
    G.montarTabela = function () {
        const c = this.c, t = c.s.schema[c.ui.ti];
        this.t = t;
        this.cache = null; this.total = t ? t.total : 0; this.todas = this.total;
        this.larguras = t.colunas.map(col => Math.max(70, Math.min(260, col.n.length * 7.5 + 34, 90 + (col.tipo || '').length * 3)));
        this.medido = false;
        this.r.det.hidden = true;
        this.r.carr.hidden = true;
        this.pintarCabeca();
        if (c.s.semLinhas && t.total) {
            this.r.carr.hidden = false;
            this.r.carr.textContent = 'Os dados deste cartão não vieram junto (cartão restaurado ou copiado). Abra o arquivo de novo para ver as linhas.';
        }
        this.recarregar();
    };
    G.pintarCabeca = function () {
        const t = this.t, ui = this.c.ui, w = this.larguras;
        const total = 64 + w.reduce((a, b) => a + b, 0);
        this.r.cab.style.width = total + 'px';
        this.r.corpo.style.width = total + 'px';
        const ord = ui.ordem;
        this.r.cab.innerHTML = `<div class="bd-lin bd-nomes"><div class="bd-cel bd-num">#</div>${t.colunas.map((col, k) => {
            const seta = ord && ord.c === k ? (ord.dir === 'asc' ? ' ▲' : ' ▼') : '';
            const tags = [col.pk && 'PK', col.fk && 'FK'].filter(Boolean).join(',');
            const dica = (col.pk ? 'Chave primária · ' : '') + (col.fk ? 'Liga com ' + col.fk.t + '.' + (col.fk.c || '') + ' · ' : '') + (col.tipo || 'sem tipo') + ' · clique para ordenar';
            return `<div class="bd-cel bd-th" style="width:${w[k]}px" data-ord="${k}" title="${esc(dica)}" role="columnheader" aria-sort="${seta ? (ord.dir === 'asc' ? 'ascending' : 'descending') : 'none'}">${esc(col.n)}${tags ? ` <small>${tags}</small>` : ''}<b>${seta}</b></div>`;
        }).join('')}</div><div class="bd-lin bd-filtros"><div class="bd-cel bd-num" title="Filtros: texto (contém), =valor, !texto, &gt;10, &lt;10, vazio">⌕</div>${t.colunas.map((col, k) =>
            `<div class="bd-cel" style="width:${w[k]}px"><input class="bd-filtro" data-filtro="${k}" value="${esc((ui.filtros || {})[k] || '')}" placeholder="filtrar" aria-label="Filtrar ${esc(col.n)}" title="texto, =valor, !texto, &gt;10, &lt;10, vazio"></div>`).join('')}</div>`;
    };
    G.recarregar = function () { this.cache = null; this.seq = (this.seq || 0) + 1; this.r.rol.scrollTop = 0; this.pedir(0); };
    G.consulta = function () { const ui = this.c.ui; return { busca: ui.busca, filtros: ui.filtros, ordem: ui.ordem }; };
    G.geometria = function () {
        const rol = this.r.rol, H = Math.max(LIN, rol.clientHeight - this.r.cab.offsetHeight), n = this.total;
        const alt = Math.min(n * LIN, MAX_ALTURA), escala = n * LIN > MAX_ALTURA;
        const vis = Math.ceil(H / LIN) + 1;
        const st = rol.scrollTop;
        const primeira = escala ? Math.min(n - 1, Math.round(st * (n - vis + 1) / Math.max(1, alt - H))) : Math.floor(st / LIN);
        return { H, alt, escala, vis, primeira: Math.max(0, primeira), st };
    };
    G.agendar = function () {
        if (this._raf) return;
        this._raf = requestAnimationFrame(() => { this._raf = 0; this.render(); });
    };
    G.pedir = async function (ini) {
        const c = this.c, seq = this.seq, t = this.t;
        if (this.pedindo === seq + ':' + ini) return;
        this.pedindo = seq + ':' + ini;
        const tAbrir = setTimeout(() => { if (seq === this.seq && !this.cache) { this.r.carr.hidden = false; this.r.carr.textContent = 'Abrindo a tabela…'; } }, 150);
        try {
            const r = await pedir({ acao: 'janela', id: c.b.id, ti: c.ui.ti, ini, n: 300, ...this.consulta() }, d => {
                if (d.t === 'carregando' && seq === this.seq) { this.r.carr.hidden = false; this.r.carr.textContent = `Abrindo a tabela… ${Math.round(d.feito / d.de * 100)}% (${d.feito} de ${d.de} pedaços)`; }
            });
            if (seq !== this.seq || this.morto) return;
            this.cache = { ini: r.ini, linhas: r.linhas };
            this.total = r.total; this.todas = r.todas;
            if (!(c.s.semLinhas && t.total)) this.r.carr.hidden = true;
            if (!this.medido && r.linhas.length) this.medir(r.linhas);
            this.render();
        } catch (e) {
            if (seq !== this.seq) return;
            console.error('[Banco .sql] janela', e);
            this.r.carr.hidden = false;
            this.r.carr.textContent = mensagemErro(e);
        } finally {
            clearTimeout(tAbrir);
            if (this.pedindo === seq + ':' + ini) this.pedindo = null;
        }
    };
    /** Larguras das colunas pelo conteúdo das primeiras linhas (uma vez por tabela). */
    G.medir = function (linhas) {
        this.medido = true;
        const amostra = linhas.slice(0, 80);
        this.larguras = this.larguras.map((w, k) => {
            let m = 0;
            amostra.forEach(l => { const v = l[k]; const n = v == null ? 4 : Math.min(60, String(v).length); if (n > m) m = n; });
            return Math.round(Math.max(w, Math.min(320, m * 7.2 + 20)));
        });
        this.pintarCabeca();
    };
    G.linhaEm = function (i) { const ca = this.cache; return ca && i >= ca.ini && i < ca.ini + ca.linhas.length ? ca.linhas[i - ca.ini] : null; };
    G.render = function () {
        if (this.morto || !this.t) return;
        const g = this.geometria(), n = this.total, ca = this.cache;
        this.r.corpo.style.height = g.alt + 'px';
        const fim = Math.min(n, g.primeira + g.vis);
        if (ca && n && (g.primeira < ca.ini || fim > ca.ini + ca.linhas.length)) this.pedir(Math.max(0, g.primeira - 100));
        const topo = g.escala ? g.st : g.primeira * LIN;
        this.r.jan.style.transform = `translateY(${topo}px)`;
        const w = this.larguras;
        let h = '';
        for (let i = g.primeira; i < fim; i++) {
            const l = this.linhaEm(i);
            h += `<div class="bd-lin${i % 2 ? ' par' : ''}" data-i="${i}"><div class="bd-cel bd-num">${num(i + 1)}</div>`;
            if (!l) { h += `<div class="bd-cel texto-2">…</div></div>`; continue; }
            for (let k = 0; k < w.length; k++) {
                const v = l[k];
                if (v == null) h += `<div class="bd-cel nulo" style="width:${w[k]}px" data-c="${k}">NULL</div>`;
                else if (typeof v === 'number' || NUM_TXT.test(v)) h += `<div class="bd-cel n" style="width:${w[k]}px" data-c="${k}">${esc(v)}</div>`;
                else h += `<div class="bd-cel" style="width:${w[k]}px" data-c="${k}">${esc(v.length > 200 ? v.slice(0, 200) + '…' : v).replace(/\n/g, '↵')}</div>`;
            }
            h += '</div>';
        }
        this.r.jan.innerHTML = h;
        // rodapé
        const pag = Math.floor(g.primeira / POR_PAGINA), pags = Math.max(1, Math.ceil(n / POR_PAGINA));
        this.r.paginas.textContent = num(pags);
        if (document.activeElement !== this.r.pag) this.r.pag.value = String(pag + 1);
        this.r.pag.max = String(pags);
        this.r.ant.disabled = pag <= 0; this.r.prox.disabled = pag >= pags - 1;
        let info = n ? `linhas ${num(g.primeira + 1)}–${num(fim)} de ${num(n)}` : (ca ? 'nenhuma linha' : '');
        if (ca && n !== this.todas) info += ` (de ${num(this.todas)} na tabela)`;
        this.r.info.textContent = info;
    };
    G.pagina = function () { return Math.floor(this.geometria().primeira / POR_PAGINA); };
    G.irPagina = function (p) {
        const pags = Math.max(1, Math.ceil(this.total / POR_PAGINA));
        p = Math.max(0, Math.min(pags - 1, p || 0));
        const g = this.geometria(), linha = p * POR_PAGINA;
        this.r.rol.scrollTop = g.escala ? linha * Math.max(1, g.alt - g.H) / Math.max(1, this.total - g.vis + 1) : linha * LIN;
        this.agendar();
    };
    G.baixarCsv = async function () {
        const c = this.c, t = this.t, bt = this.r.csv;
        bt.disabled = true; const txt = bt.textContent; bt.textContent = 'Gerando…';
        try {
            const r = await pedir({ acao: 'csv', id: c.b.id, ti: c.ui.ti, colunas: t.colunas.map(x => x.n), ...this.consulta() });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(r.blob);
            a.download = (t.nome || 'tabela').replace(/[^\w.-]+/g, '_') + '.csv';
            a.click();
            setTimeout(() => URL.revokeObjectURL(a.href), 5000);
        } catch (e) { c.msg(mensagemErro(e), true); } finally { bt.disabled = false; bt.textContent = txt; }
    };

    /* =================== estatísticas por coluna =================== */
    function Estatisticas(c) {
        this.c = c;
        const ui = c.ui, ts = c.s.schema;
        if (!ts[ui.ti]) ui.ti = 0;
        const el = document.createElement('div');
        el.className = 'bd-stats-v';
        el.innerHTML = `<div class="bd-ferr"><select class="campo !py-1 !w-auto" style="max-width: 16rem" aria-label="Tabela" data-r="tab">${ts.map((t, i) => `<option value="${i}">${esc(t.nome)} (${num(t.total)})</option>`).join('')}</select>
            <span class="text-xs texto-2" data-r="info"></span></div><div class="bd-stats" data-r="lista"></div>`;
        c.r.corpo.appendChild(el);
        this.r = {};
        el.querySelectorAll('[data-r]').forEach(n => { this.r[n.dataset.r] = n; });
        this.r.tab.value = String(ui.ti);
        this.r.tab.addEventListener('change', () => { ui.ti = Number(this.r.tab.value); ui.busca = ''; ui.filtros = {}; ui.ordem = null; this.carregar(); });
        this.carregar();
    }
    Estatisticas.prototype.carregar = async function () {
        const c = this.c, ui = c.ui, t = c.s.schema[ui.ti], seq = (this.seq = (this.seq || 0) + 1);
        const filtrado = (ui.busca || '').trim() || Object.values(ui.filtros || {}).some(v => String(v || '').trim());
        this.r.info.textContent = 'Calculando…';
        this.r.lista.innerHTML = '';
        try {
            const r = await pedir({ acao: 'estatisticas', id: c.b.id, ti: ui.ti, ncol: t.colunas.length, busca: ui.busca, filtros: ui.filtros }, d => {
                if (d.t === 'carregando' && seq === this.seq) this.r.info.textContent = `Abrindo a tabela… ${d.feito} de ${d.de} pedaços`;
            });
            if (seq !== this.seq) return;
            this.r.info.textContent = `${num(r.total)} linhas${filtrado ? ' (com a busca e os filtros da aba Dados)' : ''}`;
            const fmt = v => (typeof v === 'number' ? v.toLocaleString('pt-BR', { maximumFractionDigits: 2 }) : esc(v));
            this.r.lista.innerHTML = t.colunas.map((col, k) => {
                const s = r.cols[k], cheio = s.total - s.nulos;
                const pct = s.total ? Math.round(cheio / s.total * 100) : 0;
                const maior = s.top.length ? s.top[0][1] : 1;
                let linhas = `<p>Preenchidos <b>${pct}%</b> · nulos ${num(s.nulos)} · distintos ${num(s.distintos)}${s.distintosMais ? '+' : ''}</p>`;
                if (s.nums) linhas += `<p>Mín <b>${fmt(s.min)}</b> · máx <b>${fmt(s.max)}</b> · média <b>${fmt(s.media)}</b>${s.nums < cheio ? ` <span class="texto-2">(${num(s.nums)} números)</span>` : ''}</p>`;
                if (s.minT != null) linhas += `<p class="texto-2">Texto com ${num(s.minT)} a ${num(s.maxT)} caracteres</p>`;
                const top = s.top.map(([v, q]) => `<div class="bd-barra-v" title="${esc(String(v))}: ${num(q)}"><span style="width:${Math.max(2, q / maior * 100)}%"></span><em>${v === '' ? '<i>(vazio)</i>' : fmt(v)}</em><b>${num(q)}</b></div>`).join('');
                return `<div class="bd-stat"><p class="font-semibold text-sm">${esc(col.n)} <small class="texto-2">${esc(col.tipo || '')}${col.pk ? ' · PK' : ''}${col.fk ? ' · FK → ' + esc(col.fk.t) : ''}</small></p>${linhas}${top ? `<div class="bd-top">${top}</div>` : ''}</div>`;
            }).join('');
        } catch (e) {
            if (seq !== this.seq) return;
            this.r.info.textContent = mensagemErro(e);
        }
    };

    /* =================== métodos do quadro (Alpine) =================== */
    function metodos() {
        return {
            /** Chamado pelo x-init do cartão: o conteúdo é montado e mantido por este arquivo (fora do Alpine). */
            bdMontar(b, el) {
                if (el._bd && el._bd.b === b) return;
                el._bd = new Cartao(this, b, el);
            },
            /** x-effect: outra pessoa (ou outra aba) trocou o arquivo do cartão. */
            bdSinc(b, el) {
                void b.dados.versao; void this.imagens[b.id + '.sql'];
                if (!el || !el._bd) return;
                if (!el._bdEfeito) { el._bdEfeito = true; return; }
                el._bd.sinc(false);
            },
            bdLimpar(b) {
                if (!confirm('Tirar o banco deste cartão? A estrutura e os dados saem daqui e do servidor.')) return;
                delete b.dados.tabelas; delete b.dados.avisos;
                b.dados.versao = ''; b.dados.arquivo = ''; b.dados.resumo = null; b.dados.pos = {};
                this.apagarImagens(b.id + '.sql');
                const s = sessoes.get(b.id);
                if (s) { s.idx = null; s.schema = []; s.avisos = []; s.versao = ''; if (s.cartao) s.cartao.desenhar(); }
            },
            /** Cria um cartão "Mapa mental e diagramas" com as tabelas como entidades, para editar à vontade. */
            bdParaMapa(b) {
                const s = sessoes.get(b.id);
                const todas = s ? s.schema : [];
                const ts = todas.slice(0, 300);
                if (!ts.length) return;
                const pos = (s && s.pos) || arrumar(todas);
                const ids = new Map(ts.map(t => [t.nome, uid()]));
                const nos = ts.map((t, i) => ({
                    id: ids.get(t.nome), t: t.nome, x: pos[i].x, y: pos[i].y, pai: null, cor: '#2f6fc7', forma: 'entidade', w: 200,
                    campos: t.colunas.slice(0, 30).map(c => [c.pk && 'PK', c.fk && 'FK', !c.pk && !c.fk && c.uq && 'UK'].filter(Boolean).join(',') + (c.pk || c.fk || c.uq ? ' ' : '') + c.n + (c.tipo ? ' ' + c.tipo : '')).join('\n') || 'PK id',
                }));
                const lig = [];
                ts.forEach(t => t.colunas.forEach(c => {
                    if (c.fk && ids.has(c.fk.t) && lig.length < 600)
                        lig.push({ id: uid(), de: ids.get(t.nome), para: ids.get(c.fk.t), rot: c.n, estilo: 'ortogonal', traco: 'solido', ini: 'nenhum', fim: 'seta', cor: '', w: 1.8 });
                }));
                const L = this.estado.layout?.[b.secao]?.[b.id];
                const id = this.criarBloco(b.secao, 'mapa', L ? { x: L.x, y: L.y + L.h + 16 } : null, { titulo: (b.titulo || 'Banco') + ' (diagrama)', dados: { nos, lig, tracos: [] } });
                if (id) this.aviso(todas.length > 300 ? 'Diagrama criado logo abaixo com as primeiras 300 tabelas.' : 'Diagrama criado num cartão de Mapa mental logo abaixo. Lá dá para editar, colorir e ligar à vontade.');
            },
        };
    }

    window.WFBancoSql = { sessoes, arrumar };

    const anterior = window.diversoAbasDiagrama;
    window.diversoAbasDiagrama = function () {
        const base = anterior();
        base.tiposBloco = [...base.tiposBloco, { id: 'banco', nome: 'Banco de dados (.sql)' }];
        const criar = base.criarBloco;
        base.criarBloco = function (sec, tipo, pos, over = {}) {
            if (tipo !== 'banco') return criar.call(this, sec, tipo, pos, over);
            const id = 'b' + uid(), W0 = this.col || 1088, w = Math.min(820, W0), h = 560;
            this.estado.blocos.push({ id, secao: sec, tipo, titulo: over.titulo || 'Banco de dados', links: [], dados: { versao: '', visao: 'diagrama', pos: {}, ...(over.dados || {}) } });
            const y0 = this.alturaConteudo(sec);
            (this.estado.layout[sec] ??= {})[id] = pos ? { x: pos.x, y: pos.y, w, h, z: ++this.zTop } : { x: 0, y: y0 ? y0 + 16 : 0, w, h, z: ++this.zTop };
            return id;
        };
        Object.assign(base, metodos());
        // os pedaços (sqlz.<id>.*) não ficam em this.imagens: saem junto com o índice img.<id>.sql
        const apagar = base.apagarImagens;
        base.apagarImagens = function (id) {
            const m = /^(b[A-Za-z0-9]+)(\.sql)?$/.exec(String(id || ''));
            const tinha = !!m && this.imagens[m[1] + '.sql'] !== undefined;
            const r = apagar.call(this, id);
            if (!m || !tinha) return r;
            const bloco = m[1], cfg = cfgServidor(this);
            const tirar = () => {
                if (!m[2] && this.blocoPorId && this.blocoPorId(bloco)) return; // "Desfazer": o cartão voltou
                pedir({ acao: 'apagar', id: bloco, cfg, chave: `sqlz.${bloco}`, servidor: true, esquecer: true }).catch(() => {});
                sessoes.delete(bloco);
            };
            // cartão excluído: espera o "Desfazer" (8 s) antes de tirar os pedaços do servidor
            if (m[2]) tirar(); else setTimeout(tirar, 9000);
            return r;
        };
        return base;
    };
})();
