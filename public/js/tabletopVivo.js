/* Work Flower · Tabletop ao vivo
   Transmite um bloco "tabletop" para uma sala com código; quem entra pelo código recebe um bloco tabletop
   ligado à mesma sala e todo mundo mexe junto (peças, desenho, configurações, imagens).

   Como funciona
   - Cada cliente guarda uma "foto" do último estado sincronizado. Quando o bloco muda, compara com a foto e
     envia só o que mudou (peça criada/movida/removida, traço novo, configuração alterada).
   - O servidor aplica as mudanças numa ordem única (revisão) e cada cliente busca as novidades por polling.
   - Conflito: vale a última alteração de cada peça/traço (peças diferentes nunca conflitam).
   - As imagens (mapa de fundo e imagens das peças) viajam por rotas separadas, só quando mudam.
   - Presença (novo): cursores dos outros jogadores e "pings" (duplo clique no mapa) viajam junto do polling,
     sem entrar no histórico de revisões. Se o servidor não devolver `pres`, o recurso simplesmente não aparece.

   Uso: window.diversoAbas (diverso.js) chama WFTabletopVivo.metodos({...}) e mistura os métodos no componente Alpine.
   No template do tabletop (tabletop.blade.php):
     - <g x-html="ttPresencaSvg(b)"></g> logo depois de <g x-html="ttVivoSvg(b)"></g> dentro do SVG de desenho (ttVivoSvg é a prévia do traço, do diverso.js; não mexa nele)
     - @pointermove.passive="vivoMover($event, b)" e @dblclick="vivoPing($event, b)" em .wf-tt-grade
     - a barra de transmissão: tabletop-vivo-barra.blade.php */
(function () {
    'use strict';

    const CFG = ['cols', 'rows', 'cel', 'grade', 'nomes']; // configurações escalares do tabletop que são sincronizadas
    const ENVIO_MS = 120;          // no máximo um envio a cada 120 ms (arrastar peça manda vários por segundo)
    const POLL_MS = 700;           // intervalo de consulta com a aba visível
    const POLL_OCULTA_MS = 3000;   // aba em segundo plano
    const POLL_ERRO_MS = 5000;     // depois de uma falha de rede
    const LOTE = 300;              // operações por envio
    const CURSOR_MS = 80;          // intervalo mínimo entre leituras do ponteiro
    const PING_MS = 3000;          // quanto tempo um ping fica na tela
    const UNID = 40;               // unidades do SVG por casa (igual ao viewBox do tabletop)
    const LIM_IMG = { mapa: 1000000, peca: 250000 }; // caracteres (data URL) aceitos pelo servidor

    const base = () => {
    if (window.WF_MESA_URL) return String(window.WF_MESA_URL).replace(/\/$/, '');
    // pega o prefixo da subpasta (/work-flower) a partir da URL que o Laravel já entrega ao Diverso
    try {
        const u = JSON.parse(document.getElementById('diverso-cfg').textContent).url || '';
        if (/\/diverso\/dados\/?$/.test(u)) return u.replace(/\/diverso\/dados\/?$/, '/tabletop-vivo');
    } catch { /* usa o padrão */ }
    return '/tabletop-vivo';
    };
    const cookie = n => { const m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; };
    const meuNome = () => String(window.WF_NOME || window.WF_USUARIO?.nome || 'Jogador').slice(0, 24);
    const esc = t => String(t).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    /** Cor estável por participante (todos calculam a mesma a partir do id da aba). */
    const corDe = c => { let h = 0; for (const ch of String(c)) h = (h * 31 + ch.charCodeAt(0)) >>> 0; return `hsl(${h % 360} 75% 55%)`; };

    async function api(metodo, caminho, corpo) {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        const meta = document.querySelector('meta[name="csrf-token"]')?.content;
        if (meta) h['X-CSRF-TOKEN'] = meta; else if (cookie('XSRF-TOKEN')) h['X-XSRF-TOKEN'] = cookie('XSRF-TOKEN');
        if (corpo !== undefined) h['Content-Type'] = 'application/json';
        const r = await fetch(base() + caminho, { method: metodo, credentials: 'same-origin', headers: h, body: corpo === undefined ? undefined : JSON.stringify(corpo) });
        let j = null; try { j = await r.json(); } catch { /* sem corpo */ }
        if (!r.ok) { const e = new Error(j?.message || j?.mensagem || `Erro ${r.status}`); e.status = r.status; throw e; }
        return j;
    }

    const normalizarCodigo = c => String(c || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
    const nomeImg = suf => (suf === 'mapa' ? 'mapa' : 'peca');
    const SALA_FIM = [403, 404, 410]; // a sala não existe mais (ou não é mais acessível)

    /** Foto do estado: o que já foi sincronizado (configurações + JSON de cada peça e de cada traço). */
    function foto(d) {
        const f = { cfg: {}, pecas: new Map(), desenho: new Map() };
        CFG.forEach(k => { if (d[k] !== undefined) f.cfg[k] = d[k]; });
        (d.pecas || []).forEach(p => { if (p && p.id != null) f.pecas.set(String(p.id), JSON.stringify(p)); });
        (d.desenho || []).forEach(t => { if (t && t.id != null) f.desenho.set(String(t.id), JSON.stringify(t)); });
        return f;
    }

    /** Compara a lista atual com a foto e coloca na fila o que mudou (a chave da fila junta mudanças repetidas do mesmo item). */
    function difLista(lista, mapa, tipo, fila) {
        const vistos = new Set();
        for (const it of lista || []) {
            if (!it || it.id == null) continue;
            const id = String(it.id), s = JSON.stringify(it);
            vistos.add(id);
            if (mapa.get(id) !== s) {
                mapa.set(id, s);
                fila.set(tipo + ':' + id, { t: tipo, id, v: { ...JSON.parse(s), id } }); // ids sempre como texto na rede
            }
        }
        for (const id of [...mapa.keys()]) {
            if (!vistos.has(id)) { mapa.delete(id); fila.set(tipo + ':' + id, { t: tipo, id, v: null }); }
        }
    }

    window.WFTabletopVivo = {
        /** ctx: { uid, copia, reduzirSrc, normalizarTabletop } vindos de diverso.js */
        metodos(ctx) {
            const { uid, copia, reduzirSrc, normalizarTabletop } = ctx;
            const cid = 'c' + String(uid()).replace(/[^A-Za-z0-9]/g, '').slice(0, 30); // identifica esta aba (para ignorar o próprio eco)
            const vivas = new Map();      // id do bloco -> sessão
            const conectando = new Set(); // blocos em reconexão
            let despejoLigado = false;

            return {
                /* ---------- consultas simples (usadas pela página) ---------- */
                vivoRotulo(b) {
                    if (!b.vivo) return '';
                    const n = this.ui['vv.' + b.id]?.online || 0;
                    return `${b.vivo.codigo}${n > 1 ? ' · ' + n : ''}`;
                },
                vivoBloco(s) { return this.estado.blocos.find(x => x.id === s.bid) || null; },
                /** Estado de interface da sala (online, presença, pings). Só use fora de funções de renderização. */
                vivoU(bid) {
                    const u = (this.ui['vv.' + bid] ??= {});
                    if (u.online === undefined) u.online = 1;
                    if (!u.pres) u.pres = [];
                    if (!u.pings) u.pings = {};
                    if (u.tick === undefined) u.tick = 0;
                    return u;
                },
                /** Quem está na sala agora (para a barra do bloco): outros jogadores com a cor do cursor. */
                vivoPresentes(b) {
                    const u = this.ui['vv.' + b.id];
                    return (u?.pres || []).map(p => ({ c: p.c, n: p.n, cor: corDe(p.c) }));
                },
                vivoMinhaCor() { return corDe(cid); },

                /* ---------- iniciar / entrar / sair ---------- */
                /** Dono: abre a sala com o estado atual do bloco e começa a sincronizar. */
                async vivoTransmitir(b) {
                    if (b.tipo !== 'tabletop') { this.aviso('Só o bloco tabletop pode ser transmitido.'); return; }
                    if (b.vivo) return this.vivoMenu(b);
                    this.aviso('Abrindo a sala…');
                    try {
                        const d = b.dados;
                        const dados = { pecas: copia(d.pecas || []), desenho: copia(d.desenho || []) };
                        CFG.forEach(k => { if (d[k] !== undefined) dados[k] = d[k]; });
                        dados.pecas.forEach(p => { p.id = String(p.id); if (p.img != null) p.img = String(p.img); });
                        dados.desenho.forEach(t => { t.id = String(t.id); });
                        const r = await api('POST', '', { titulo: b.titulo, dados });
                        b.vivo = { codigo: r.codigo, dono: true };
                        this.vivoIniciar(b, r.rev, []);   // as imagens existentes sobem no primeiro ciclo
                        try { await navigator.clipboard.writeText(r.codigo); } catch { /* sem permissão de área de transferência */ }
                        this.aviso(`Sala ${r.codigo} aberta. Código copiado: passe para quem vai entrar.`);
                    } catch (e) {
                        this.aviso('Não consegui abrir a sala: ' + e.message);
                    }
                },

                /** Convidado: entra pelo código e ganha um bloco tabletop ligado à sala. */
                async vivoEntrar(codigo, sec) {
                    codigo = normalizarCodigo(codigo);
                    if (codigo.length < 4) { this.aviso('Código inválido.'); return; }
                    const ja = this.estado.blocos.find(x => x.vivo?.codigo === codigo);
                    if (ja) { this.aba = ja.secao; this.aviso('Você já está nessa sala.'); return; }
                    if (!sec) { this.aviso('Crie uma aba antes de entrar em uma sala.'); return; }
                    try {
                        const r = await api('GET', '/' + codigo);
                        const id = this.criarBloco(sec, 'tabletop', null, { titulo: r.titulo || 'Tabletop ao vivo', dados: {} });
                        const b = this.estado.blocos.find(x => x.id === id);
                        this.vivoCarregar(b, r.dados);
                        b.vivo = { codigo, dono: !!r.dono };
                        this.vivoIniciar(b, r.rev, r.imgs || []);
                        this.aviso(`Você entrou na sala ${codigo}.`);
                    } catch (e) {
                        this.aviso(e.status === 404 || e.status === 410 ? 'Sala não encontrada. Confira o código.' : 'Não consegui entrar: ' + e.message);
                    }
                },
                vivoEntrarPrompt(sec) {
                    const c = window.prompt('Código da sala:');
                    if (c) this.vivoEntrar(c, sec);
                },

                async vivoMenu(b) {
                    if (!b.vivo) return this.vivoTransmitir(b);
                    try {
                        await navigator.clipboard.writeText(b.vivo.codigo);
                        this.aviso(`Código ${b.vivo.codigo} copiado. Quem tiver o código entra em "Entrar com código".`);
                    } catch { window.prompt('Código da sala:', b.vivo.codigo); }
                },

                async vivoSair(b) {
                    if (!b.vivo) return;
                    const dono = !!b.vivo.dono, s = vivas.get(b.id), codigo = b.vivo.codigo;
                    const ok = dono
                        ? confirm('Encerrar a sala? Quem está dentro fica com uma cópia do mapa, sem atualizar mais.')
                        : confirm('Sair da sala? O mapa continua aqui como cópia sua, sem atualizar mais.');
                    if (!ok) return;
                    if (s) {
                        if (!dono) { this.vivoDiff(s); await this.vivoEnviar(s); }
                        this.vivoParar(s);
                    }
                    if (dono) { try { await api('DELETE', '/' + codigo); } catch { /* já encerrada */ } }
                    b.vivo = null;
                    this.aviso(dono ? 'Sala encerrada.' : 'Você saiu da sala.');
                },

                /* ---------- sessão ---------- */
                vivoIniciar(b, rev, imgsRemotas) {
                    if (vivas.has(b.id)) return;
                    const s = {
                        bid: b.id, codigo: b.vivo.codigo, rev: rev || 0,
                        snap: foto(b.dados), fila: new Map(), imgSnap: {}, baixando: new Set(),
                        tDif: null, tPoll: null, enviando: false, parado: false, avisouImg: false,
                        polling: false, cur: null, curT: 0, pingPend: null, pingsVistos: {},
                    };
                    vivas.set(b.id, s);
                    (imgsRemotas || []).forEach(k => { s.baixando.add(k); this.vivoBaixarImg(s, k); });
                    const u = this.vivoU(b.id);
                    u.online = 1; u.pres = []; u.pings = {};
                    s.tPoll = setTimeout(() => this.vivoPoll(s), POLL_MS);
                    this.vivoLigarDespejo();
                },
                vivoParar(s) {
                    s.parado = true;
                    clearTimeout(s.tPoll); clearTimeout(s.tDif);
                    vivas.delete(s.bid);
                    const u = this.ui['vv.' + s.bid];
                    if (u) { u.pres = []; u.pings = {}; u.tick++; }
                },
                /** Sala encerrada pelo dono (ou apagada): o mapa vira uma cópia local. */
                vivoEncerrada(s) {
                    const b = this.vivoBloco(s);
                    this.vivoParar(s);
                    if (b) { b.vivo = null; this.aviso('A sala foi encerrada. O mapa ficou aqui como cópia sua.'); }
                },
                /** O bloco foi excluído da página. */
                vivoSumiu(s) {
                    const b = this.estado.blocos.find(x => x.id === s.bid);
                    this.vivoParar(s);
                    if (!b) { api('GET', '/' + s.codigo).then(r => { if (r.dono) api('DELETE', '/' + s.codigo).catch(() => {}); }).catch(() => {}); }
                },
                /** Depois de recarregar a página (ou de desfazer a exclusão de um bloco), volta para a sala. */
                async vivoReconectar(b) {
                    if (conectando.has(b.id) || vivas.has(b.id) || !b.vivo?.codigo) return;
                    conectando.add(b.id);
                    try {
                        const r = await api('GET', '/' + b.vivo.codigo);
                        this.vivoCarregar(b, r.dados);
                        b.vivo.dono = !!r.dono;
                        this.vivoIniciar(b, r.rev, r.imgs || []);
                    } catch (e) {
                        if (SALA_FIM.includes(e.status)) {
                            b.vivo = null;
                            this.aviso('A sala deste tabletop não existe mais. O mapa ficou como cópia local.');
                        } else {
                            setTimeout(() => { if (b.vivo && !vivas.has(b.id)) this.vivoReconectar(b); }, POLL_ERRO_MS);
                        }
                    } finally { conectando.delete(b.id); }
                },
                vivoIniciarTudo() {
                    this.estado.blocos.forEach(b => { if (b.tipo === 'tabletop' && b.vivo?.codigo) this.vivoReconectar(b); });
                },
                vivoVarrer() {
                    this.estado.blocos.forEach(b => {
                        if (b.vivo?.codigo && !vivas.has(b.id) && !conectando.has(b.id)) this.vivoReconectar(b);
                    });
                },

                /** Substitui o conteúdo do bloco pelo estado da sala. */
                vivoCarregar(b, dados) {
                    const d = b.dados = b.dados || {};
                    dados = dados || {};
                    CFG.forEach(k => { if (dados[k] !== undefined) d[k] = dados[k]; });
                    d.pecas = copia(dados.pecas || []);
                    d.desenho = copia(dados.desenho || []);
                    normalizarTabletop(d);
                    const u = this.ttUI(b);
                    u.sels = u.sels.filter(id => d.pecas.some(p => String(p.id) === String(id)));
                },

                /* ---------- enviar ---------- */
                /** Chamado a cada mudança no estado da página (ver init em diverso.js). */
                vivoAlterou() {
                    this.vivoVarrer();
                    vivas.forEach(s => {
                        if (s.tDif || s.parado) return;
                        s.tDif = setTimeout(() => {
                            s.tDif = null;
                            if (s.parado) return;
                            if (this.vivoDiff(s)) this.vivoEnviar(s);
                        }, ENVIO_MS);
                    });
                },
                vivoDiff(s) {
                    const b = this.vivoBloco(s);
                    if (!b) { this.vivoSumiu(s); return false; }
                    const d = b.dados || {};
                    CFG.forEach(k => {
                        const v = d[k];
                        if (v === undefined || v === null || typeof v === 'object') return;
                        if (s.snap.cfg[k] !== v) { s.snap.cfg[k] = v; s.fila.set('cfg:' + k, { t: 'cfg', k, v }); }
                    });
                    difLista(d.pecas, s.snap.pecas, 'peca', s.fila);
                    difLista(d.desenho, s.snap.desenho, 'des', s.fila);
                    return true;
                },
                async vivoEnviar(s) {
                    if (s.enviando || s.parado || !s.fila.size) return;
                    const lote = [...s.fila.entries()].slice(0, LOTE);
                    lote.forEach(([k]) => s.fila.delete(k));
                    s.enviando = true;
                    let ok = false;
                    try {
                        const r = await api('POST', `/${s.codigo}/ops`, { cid, ops: lote.map(([, o]) => o) });
                        if (r.anterior === s.rev) s.rev = r.rev; // ninguém escreveu no meio: já estamos em dia
                        ok = true;
                    } catch (e) {
                        lote.forEach(([k, o]) => { if (!s.fila.has(k)) s.fila.set(k, o); }); // devolve à fila (o que mudou depois vale mais)
                        if (SALA_FIM.includes(e.status)) { s.enviando = false; this.vivoEncerrada(s); return; }
                        setTimeout(() => { if (!s.parado) this.vivoEnviar(s); }, POLL_ERRO_MS);
                    }
                    s.enviando = false;
                    if (ok && s.fila.size) this.vivoEnviar(s);
                },
                /** Ao fechar a aba, manda o que ainda estava na fila. Ao voltar para a aba, consulta na hora. */
                vivoLigarDespejo() {
                    if (despejoLigado) return;
                    despejoLigado = true;
                    window.addEventListener('pagehide', () => {
                        vivas.forEach(s => {
                            this.vivoDiff(s);
                            if (!s.fila.size) return;
                            const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
                            const meta = document.querySelector('meta[name="csrf-token"]')?.content;
                            if (meta) h['X-CSRF-TOKEN'] = meta;
                            try {
                                fetch(base() + `/${s.codigo}/ops`, { method: 'POST', keepalive: true, credentials: 'same-origin', headers: h,
                                    body: JSON.stringify({ cid, ops: [...s.fila.values()].slice(0, LOTE) }) });
                            } catch { /* melhor esforço */ }
                        });
                    });
                    document.addEventListener('visibilitychange', () => {
                        if (document.hidden) return;
                        vivas.forEach(s => { if (!s.parado && !s.polling) { clearTimeout(s.tPoll); this.vivoPoll(s); } });
                    });
                },

                /* ---------- receber ---------- */
                async vivoPoll(s) {
                    if (s.parado || s.polling) return;
                    s.polling = true;
                    let espera = document.hidden ? POLL_OCULTA_MS : POLL_MS;
                    try {
                        this.vivoVarrerImgs(s);
                        // a presença (nome, cursor, ping) vai junto da consulta: sem rota extra e sem entrar no histórico
                        let q = `desde=${s.rev}&cid=${encodeURIComponent(cid)}&n=${encodeURIComponent(meuNome())}`;
                        if (s.cur) q += `&x=${Math.round(s.cur.x)}&y=${Math.round(s.cur.y)}`;
                        if (s.pingPend) { q += `&px=${Math.round(s.pingPend.x)}&py=${Math.round(s.pingPend.y)}`; s.pingPend = null; }
                        const r = await api('GET', `/${s.codigo}/ops?${q}`);
                        if (s.parado) return;
                        if (r.reset) this.vivoRestaurar(s, r); else this.vivoAplicar(s, r.ops || []);
                        s.rev = r.rev;
                        const u = this.vivoU(s.bid);
                        if (r.online && u.online !== r.online) u.online = r.online;
                        if (Array.isArray(r.pres)) this.vivoPresenca(s, r.pres);
                        if (r.mais) espera = 0;
                    } catch (e) {
                        if (SALA_FIM.includes(e.status)) { this.vivoEncerrada(s); return; }
                        espera = POLL_ERRO_MS;
                    } finally { s.polling = false; }
                    if (!s.parado) s.tPoll = setTimeout(() => this.vivoPoll(s), espera);
                },

                /** Aplica no bloco o que outras pessoas fizeram. */
                vivoAplicar(s, lotes) {
                    const b = this.vivoBloco(s);
                    if (!b || !lotes.length) return;
                    const d = b.dados, u = this.ttUI(b);
                    // peças que esta pessoa está arrastando agora não são mexidas por ninguém
                    const arrastando = new Set(u.arrasta ? u.sels.map(String) : []);
                    for (const lote of lotes) {
                        if (lote.cid === cid) continue;
                        for (const op of lote.ops || []) {
                            if (op.t === 'cfg') {
                                if (!CFG.includes(op.k) || s.fila.has('cfg:' + op.k)) continue;
                                d[op.k] = op.v; s.snap.cfg[op.k] = op.v;
                            } else if (op.t === 'peca') {
                                const id = String(op.id);
                                if (s.fila.has('peca:' + id) || arrastando.has(id)) continue;
                                const lista = (d.pecas ??= []);
                                const i = lista.findIndex(p => String(p.id) === id);
                                if (op.v === null) {
                                    if (i >= 0) lista.splice(i, 1);
                                    s.snap.pecas.delete(id);
                                    u.sels = u.sels.filter(x => String(x) !== id);
                                } else {
                                    let p;
                                    if (i >= 0) {
                                        p = lista[i];
                                        const idLocal = p.id;
                                        Object.keys(p).forEach(k => { if (!(k in op.v)) delete p[k]; });
                                        Object.assign(p, op.v);
                                        p.id = idLocal; // mantém o tipo do id que esta aba já usa
                                    } else { lista.push({ ...op.v }); p = lista[lista.length - 1]; }
                                    s.snap.pecas.set(id, JSON.stringify(p));
                                    if (p.img != null) this.vivoPedirImg(s, 'i.' + p.img, false);
                                }
                            } else if (op.t === 'des') {
                                const id = String(op.id);
                                if (s.fila.has('des:' + id)) continue;
                                const lista = (d.desenho ??= []);
                                const i = lista.findIndex(t => String(t.id) === id);
                                if (op.v === null) {
                                    if (i >= 0) lista.splice(i, 1);
                                    s.snap.desenho.delete(id);
                                } else {
                                    if (i >= 0) lista.splice(i, 1, { ...op.v }); else lista.push({ ...op.v });
                                    s.snap.desenho.set(id, JSON.stringify(lista[i >= 0 ? i : lista.length - 1]));
                                }
                            } else if (op.t === 'img') {
                                if (op.v) this.vivoPedirImg(s, op.k, true);
                                else { delete this.imagens[`${b.id}.${op.k}`]; delete s.imgSnap[op.k]; }
                            }
                        }
                    }
                },
                /** Estamos muito atrás (ou fora de sincronia): troca tudo pelo estado da sala. */
                vivoRestaurar(s, r) {
                    const b = this.vivoBloco(s);
                    if (!b) return;
                    s.fila.clear();
                    this.vivoCarregar(b, r.dados);
                    s.snap = foto(b.dados);
                    const remotas = new Set(r.imgs || []);
                    remotas.forEach(k => this.vivoPedirImg(s, k, true));
                    Object.keys(s.imgSnap).forEach(k => { if (!remotas.has(k)) { delete this.imagens[`${b.id}.${k}`]; delete s.imgSnap[k]; } });
                },

                /* ---------- presença: cursores e pings ---------- */
                /** Ponto do mouse em unidades do SVG do mapa (40 por casa), independente do zoom/tamanho da casa. */
                vivoPonto(ev, b) {
                    const el = ev.currentTarget?.getBoundingClientRect ? ev.currentTarget : ev.target?.closest?.('.wf-tt-grade');
                    if (!el) return null;
                    const r = el.getBoundingClientRect();
                    if (!r.width || !r.height) return null;
                    const cols = +this.ttCols(b) || 1, rows = +this.ttRows(b) || 1;
                    const x = (ev.clientX - r.left) / r.width * cols * UNID;
                    const y = (ev.clientY - r.top) / r.height * rows * UNID;
                    if (x < 0 || y < 0 || x > cols * UNID || y > rows * UNID) return null;
                    return { x, y };
                },
                /** Ligado em .wf-tt-grade com @pointermove.passive. Só lê o ponteiro; o envio vai no próximo polling. */
                vivoMover(ev, b) {
                    const s = vivas.get(b.id);
                    if (!s) return;
                    const t = performance.now();
                    if (t - s.curT < CURSOR_MS) return;
                    s.curT = t;
                    const p = this.vivoPonto(ev, b);
                    if (p) s.cur = p;
                },
                /** Duplo clique no mapa: chama a atenção de todo mundo para aquele ponto. */
                vivoPing(ev, b) {
                    const s = vivas.get(b.id);
                    if (!s || this.ttDes(b).on) return; // com o desenho ligado, o duplo clique é do pincel
                    const p = this.vivoPonto(ev, b);
                    if (!p) return;
                    s.pingPend = p;
                    this.vivoPingMostrar(this.vivoU(b.id), 'eu', p.x, p.y);
                    if (!s.polling) { clearTimeout(s.tPoll); this.vivoPoll(s); } // não espera o próximo ciclo
                },
                vivoPingMostrar(u, chave, x, y) {
                    u.pings[chave] = { x, y, ate: Date.now() + PING_MS };
                    setTimeout(() => { u.tick++; }, PING_MS + 100); // força a limpeza visual quando expira
                    u.tick++;
                },
                /** Recebe a lista de presença do servidor: [{ c, n, x, y, ping: { x, y, t } }] (sem a própria aba). */
                vivoPresenca(s, lista) {
                    const u = this.vivoU(s.bid);
                    const pres = [];
                    for (const p of lista) {
                        if (!p || p.c == null || String(p.c) === cid) continue;
                        const c = String(p.c);
                        pres.push({ c, n: String(p.n || 'Jogador').slice(0, 24), x: Number.isFinite(+p.x) && p.x !== null ? +p.x : null, y: Number.isFinite(+p.y) && p.y !== null ? +p.y : null });
                        if (p.ping && p.ping.t != null && s.pingsVistos[c] !== p.ping.t) {
                            s.pingsVistos[c] = p.ping.t;
                            this.vivoPingMostrar(u, c, +p.ping.x, +p.ping.y);
                        }
                    }
                    u.pres = pres;
                },
                /** Camada SVG com os cursores e pings dos outros jogadores (usada em <g x-html="ttPresencaSvg(b)">). */
                ttPresencaSvg(b) {
                    const u = this.ui['vv.' + b.id];
                    if (!b.vivo || !u) return '';
                    void u.tick; // dependência reativa: reavalia quando um ping expira
                    const agora = Date.now();
                    let h = '';
                    for (const p of u.pres || []) {
                        if (p.x === null || p.y === null) continue;
                        const cor = corDe(p.c);
                        h += `<g transform="translate(${p.x.toFixed(1)} ${p.y.toFixed(1)})" pointer-events="none">`
                          + `<path d="M0 0L0 16L4.5 12L8 19L11 17.5L7.5 11L13 11Z" fill="${cor}" stroke="#fff" stroke-width="1" stroke-linejoin="round"/>`
                          + `<text x="14" y="27" font-size="11" font-family="sans-serif" font-weight="600" fill="#fff" stroke="${cor}" stroke-width="3" paint-order="stroke" stroke-linejoin="round">${esc(p.n)}</text></g>`;
                    }
                    for (const [c, pg] of Object.entries(u.pings || {})) {
                        if (pg.ate <= agora) continue;
                        const cor = c === 'eu' ? corDe(cid) : corDe(c);
                        h += `<circle cx="${pg.x.toFixed(1)}" cy="${pg.y.toFixed(1)}" r="4" fill="none" stroke="${cor}" stroke-width="3" pointer-events="none">`
                          + `<animate attributeName="r" from="4" to="36" dur="1s" repeatCount="3"/>`
                          + `<animate attributeName="opacity" from="1" to="0" dur="1s" repeatCount="3"/></circle>`
                          + `<circle cx="${pg.x.toFixed(1)}" cy="${pg.y.toFixed(1)}" r="4" fill="${cor}" pointer-events="none"/>`;
                    }
                    return h;
                },

                /* ---------- imagens ---------- */
                vivoPedirImg(s, k, forcar) {
                    const b = this.vivoBloco(s);
                    if (!b || s.baixando.has(k)) return;
                    if (!forcar && (this.imagens[`${b.id}.${k}`] || s.imgSnap[k])) return;
                    s.baixando.add(k);
                    this.vivoBaixarImg(s, k);
                },
                async vivoBaixarImg(s, k) {
                    try {
                        const r = await api('GET', `/${s.codigo}/img/${k}`);
                        const b = this.vivoBloco(s);
                        if (b && r && r.v) { s.imgSnap[k] = r.v; this.imagens[`${b.id}.${k}`] = r.v; }
                    } catch { /* a imagem pode ter sido removida nesse meio tempo */ }
                    finally { s.baixando.delete(k); }
                },
                /** Procura imagens que esta pessoa adicionou ou removeu no bloco e avisa a sala. */
                vivoVarrerImgs(s) {
                    const b = this.vivoBloco(s);
                    if (!b) return;
                    const pre = b.id + '.', atuais = {};
                    for (const k of Object.keys(this.imagens)) {
                        if (!k.startsWith(pre)) continue;
                        const suf = k.slice(pre.length);
                        if (suf === 'mapa' || suf.startsWith('i.')) atuais[suf] = this.imagens[k];
                    }
                    for (const [suf, v] of Object.entries(atuais)) {
                        if (!v || s.baixando.has(suf) || s.imgSnap[suf] === v) continue;
                        s.imgSnap[suf] = v;
                        this.vivoSubirImg(s, suf, v);
                    }
                    for (const suf of Object.keys(s.imgSnap)) {
                        if (atuais[suf] !== undefined || s.baixando.has(suf)) continue;
                        delete s.imgSnap[suf];
                        api('DELETE', `/${s.codigo}/img/${suf}?cid=${encodeURIComponent(cid)}`).catch(() => {});
                    }
                },
                async vivoSubirImg(s, suf, v) {
                    const mapa = suf === 'mapa', lim = mapa ? LIM_IMG.mapa : LIM_IMG.peca;
                    try {
                        const red = await reduzirSrc(v, mapa ? 1100 : 256, mapa ? 0.7 : 0.75, Math.round(lim * 0.6));
                        if (red.length > lim) throw new Error('grande demais');
                        await api('PUT', `/${s.codigo}/img/${suf}`, { cid, v: red });
                    } catch (e) {
                        if (!s.avisouImg) { s.avisouImg = true; this.aviso(`Não consegui enviar a imagem da ${nomeImg(suf)} para a sala (${e.message}).`); }
                    }
                },
            };
        },
    };
})();
