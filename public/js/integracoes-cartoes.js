/* Work Flower · cartões de integração do quadro: Google Drive, Gmail, YouTube e Lembretes por e-mail.
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o cartoes-extra.js (carregue depois do graficos.js: usa
   wfNovoCartao e WFGraficos.pedir). Os dados vêm da conta de quem está vendo (tokens só no servidor) e cada item
   funciona como um elemento do próprio quadro: abre dentro do cartão, vira cartão (botão "No quadro" ou arrastando
   o item para um espaço vazio do quadro) e gera tarefa, evento, post do Criador ou arquivo no Drive.
   - gdrive:    b.dados = { }                                   (GoogleDriveController; pasta "Work Flower" do Drive)
   - gmail:     b.dados = { categoria, conta }                  (EmailController::cartao/corpo; Negócios › E-mails)
   - youtube:   b.dados = { canal, video: { id, titulo }? }     (YoutubeController::cartao; login do Google ou @ público)
   - lembretes: b.dados = { dias }                              (LembretesController + CalendarioController) */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function' || !window.WFGraficos) return;
    const { uid, snap } = Q.util;
    const TAG = document.currentScript ? document.currentScript.dataset : {};
    const URL_DRIVE = TAG.drive || '', URL_GMAIL = TAG.gmail || '', URL_YT = TAG.yt || '', URL_LEMB = TAG.lembretes || '';
    const URL_CAL = TAG.cal || '', URL_TAREFAS = TAG.tarefas || '';
    const pedir = (...a) => window.WFGraficos.pedir(...a);
    const TIPOS = [
        URL_DRIVE && { id: 'gdrive', nome: 'Google Drive' },
        URL_GMAIL && { id: 'gmail', nome: 'Gmail' },
        URL_YT && { id: 'youtube', nome: 'YouTube' },
        URL_LEMB && { id: 'lembretes', nome: 'Lembretes por e-mail' },
    ].filter(Boolean);
    const DE_INTEGRACAO = ['gdrive', 'gmail', 'youtube', 'lembretes'];
    const ANTES = [[0, 'Na hora'], [5, '5 min antes'], [10, '10 min antes'], [15, '15 min antes'], [30, '30 min antes'], [60, '1 hora antes'],
        [120, '2 horas antes'], [1440, '1 dia antes'], [2880, '2 dias antes'], [10080, '1 semana antes']];

    /* ---------- utilidades ---------- */
    const erroDe = r => (r.json && (r.json.erro || r.json.message)) || (r.status === 0 ? 'Sem conexão com o servidor.' : r.status === 419 ? 'A sessão expirou. Recarregue a página.' : 'Não deu certo agora. Tente de novo.');
    const dataHora = s => { if (!s) return ''; const d = new Date(s); return isNaN(d) ? '' : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); };
    const dataCurta = s => {
        if (!s) return '';
        const d = new Date(s), h = new Date();
        if (isNaN(d)) return '';
        return d.toDateString() === h.toDateString() ? d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    };
    const tamanho = n => (n == null ? '' : n < 1024 ? n + ' B' : n < 1048576 ? Math.round(n / 1024) + ' KB' : (n / 1048576).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' MB');
    const semExt = n => String(n || '').replace(/\.[^./]{1,8}$/, '');
    const titulo80 = s => String(s || '').replace(/[\u0000-\u001f]+/g, ' ').trim().slice(0, 80);
    const num = n => (n == null ? '—' : n >= 1e6 ? (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : n >= 1e4 ? (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : n.toLocaleString('pt-BR'));
    /** "AAAA-MM-DDTHH:MM" local, daqui a `dias` dias às `hora`. */
    const localDT = (dias, hora) => {
        const d = new Date();
        d.setDate(d.getDate() + dias);
        if (hora != null) d.setHours(hora, 0, 0, 0); else d.setMinutes(Math.ceil((d.getMinutes() + 1) / 15) * 15, 0, 0);
        const p = x => String(x).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
    };
    /** Texto (UTF-8) em base64, em pedaços para não estourar a pilha com textos grandes. */
    function base64(texto) {
        const bytes = new TextEncoder().encode(texto);
        let s = '';
        for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return btoa(s);
    }
    /** CSV/TSV → { cab, linhas } (aspas, ; ou , ou tab). */
    function lerCsv(txt) {
        txt = String(txt || '').replace(/^﻿/, '');
        const primeira = txt.split(/\r?\n/, 1)[0] || '';
        const sep = (primeira.match(/\t/g) || []).length > 0 ? '\t' : (primeira.match(/;/g) || []).length > (primeira.match(/,/g) || []).length ? ';' : ',';
        const linhas = [];
        let campo = '', linha = [], aspas = false;
        for (let i = 0; i < txt.length; i++) {
            const ch = txt[i];
            if (aspas) {
                if (ch === '"' && txt[i + 1] === '"') { campo += '"'; i++; } else if (ch === '"') aspas = false; else campo += ch;
            } else if (ch === '"') aspas = true;
            else if (ch === sep) { linha.push(campo); campo = ''; }
            else if (ch === '\n' || ch === '\r') {
                if (ch === '\r' && txt[i + 1] === '\n') i++;
                linha.push(campo); campo = '';
                if (linha.some(c => c !== '')) linhas.push(linha);
                linha = [];
                if (linhas.length > 500) break;
            } else campo += ch;
        }
        if (campo !== '' || linha.length) { linha.push(campo); if (linha.some(c => c !== '')) linhas.push(linha); }
        const n = Math.min(20, Math.max(1, ...linhas.map(l => l.length)));
        const norm = l => Array.from({ length: n }, (_, j) => String(l[j] ?? '').slice(0, 500));
        const cab = norm(linhas.shift() || []);
        return { cab, linhas: linhas.length ? linhas.slice(0, 500).map(norm) : [cab.map(() => '')] };
    }
    const csvCelula = v => (/[",;\n]/.test(String(v)) ? '"' + String(v).replace(/"/g, '""') + '"' : String(v));
    /** Cartão do quadro → arquivo para o Drive. */
    function arquivoDoCartao(x) {
        const nome = titulo80(x.titulo) || 'Cartão';
        const d = x.dados || {};
        if (x.tipo === 'texto') return { nome: nome + '.md', mime: 'text/markdown', conteudo: String(d.texto || '') };
        if (x.tipo === 'tabela') return { nome: nome + '.csv', mime: 'text/csv', conteudo: [d.cab || [], ...(d.linhas || [])].map(l => l.map(csvCelula).join(',')).join('\n') };
        if (x.tipo === 'lista') return { nome: nome + '.md', mime: 'text/markdown', conteudo: (d.itens || []).map(i => `- [${i.f ? 'x' : ' '}] ${i.t || ''}`).join('\n') };
        // os outros tipos vão no formato de bloco do Work Flower (o mesmo do "Baixar"): soltar o .json no quadro traz de volta
        return { nome: nome + '.json', mime: 'application/json', conteudo: JSON.stringify({ wf: 'bloco', v: 1, tipo: x.tipo, titulo: x.titulo || '', dados: d }) };
    }
    const iconeArquivo = f => {
        const m = f.mime || '';
        if (m === 'application/vnd.google-apps.folder') return '📁';
        if (m.startsWith('image/')) return '🖼';
        if (m === 'application/json') return /^backup-/.test(f.nome) ? '🗄' : '🧩';
        if (m.includes('spreadsheet') || m === 'text/csv') return '📊';
        if (m.includes('presentation')) return '📽';
        if (m === 'application/pdf') return '📕';
        if (m.startsWith('video/')) return '🎞';
        if (m.startsWith('audio/')) return '🎵';
        return '📄';
    };

    /* ---------- arrastar um item de um cartão para o quadro ---------- */
    let ARRASTO = null; // { de: tipo do cartão de origem, origem: id do cartão, item }
    const limparAlvo = () => document.querySelectorAll('.wf-quadro.wf-soltar').forEach(q => q.classList.remove('wf-soltar'));
    document.addEventListener('dragover', e => {
        if (!ARRASTO) return;
        const q = e.target.closest && e.target.closest('.wf-quadro');
        if (!q) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        if (!q.classList.contains('wf-soltar')) { limparAlvo(); q.classList.add('wf-soltar'); }
    });
    document.addEventListener('dragend', () => { ARRASTO = null; limparAlvo(); });
    document.addEventListener('drop', e => {
        if (!ARRASTO) return;
        const a = ARRASTO;
        ARRASTO = null;
        limparAlvo();
        const q = e.target.closest && e.target.closest('.wf-quadro');
        if (!q) return;
        e.preventDefault();
        e.stopPropagation();
        const cartao = e.target.closest('.wf-item[data-bid]');
        if (cartao && cartao.dataset.bid === a.origem) return; // soltou no próprio cartão: nada
        let vm = null;
        try { vm = window.Alpine.$data(q); } catch (_) { /* sem Alpine */ }
        if (!vm || typeof vm.igSoltar !== 'function') return;
        const r = q.getBoundingClientRect();
        vm.igSoltar(a, { x: Math.max(0, snap(e.clientX - r.left - (vm.ox || 0) - 40)), y: Math.max(0, snap(e.clientY - r.top - 20)) });
    }, true);

    const extras = {
        /* ---------- criar os cartões ---------- */
        novoGdrive(sec) { return this.wfNovoCartao(sec, 'gdrive', 'Google Drive', 460, 460, {}, 'Google Drive adicionado: a pasta Work Flower do seu Drive aparece aqui.'); },
        novoGmail(sec) { return this.wfNovoCartao(sec, 'gmail', 'Gmail', 540, 480, { categoria: '', conta: '0' }, 'Gmail adicionado: arraste um e-mail para o quadro para virar cartão.'); },
        novoYoutube(sec) { return this.wfNovoCartao(sec, 'youtube', 'YouTube', 560, 500, { canal: '' }, 'YouTube adicionado: entre com a conta Google para ver seus vídeos.'); },
        novoLembretes(sec) { return this.wfNovoCartao(sec, 'lembretes', 'Lembretes por e-mail', 460, 480, { dias: '14' }, 'Lembretes adicionado: os próximos lembretes do calendário aparecem aqui.'); },

        /* ---------- estado de cada cartão (não vai para o banco) ---------- */
        igUI(b) {
            this.igEstado[b.id] ??= { carregando: false, erro: '', d: null, aberto: null, corpo: '', corpoErro: '', lendo: false, q: '', canal: '', pl: null, verPl: false, enviar: '', ocupado: '', novo: null };
            return this.igEstado[b.id];
        },
        igIniciar(b) {
            b.dados ??= {};
            const ui = this.igUI(b);
            if (!ui.d && !ui.carregando) this.igCarregar(b);
        },
        async igCarregar(b, forcar) {
            const ui = this.igUI(b), d = b.dados || {};
            if (ui.carregando) return;
            let url = '';
            if (b.tipo === 'gdrive') url = URL_DRIVE + (ui.q ? '?q=' + encodeURIComponent(ui.q) : '');
            else if (b.tipo === 'gmail') url = URL_GMAIL + '/cartao?' + new URLSearchParams({ q: ui.q || '', categoria: d.categoria || '', conta: +d.conta || '' });
            else if (b.tipo === 'youtube') {
                if (d.video) return; // cartão de um vídeo só: não precisa do servidor
                url = URL_YT + '/cartao?' + new URLSearchParams({ canal: d.canal || '', forcar: forcar ? '1' : '' });
            } else if (b.tipo === 'lembretes') url = URL_LEMB + '/cartao?dias=' + (+d.dias || 14);
            if (!url) return;
            ui.carregando = true;
            ui.erro = '';
            const r = await pedir(url);
            ui.carregando = false;
            ui.d = r.json || {};
            if (!r.ok) ui.erro = erroDe(r);
        },
        igArrastar(e, b, item) {
            ARRASTO = { de: b.tipo, origem: b.id, item };
            try {
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', item.assunto || item.titulo || item.nome || (item.evento && item.evento.titulo) || '');
            } catch (_) { /* navegador antigo */ }
        },
        /** Item solto no quadro (pos) ou mandado pelo botão "No quadro" (sem pos: vai para o fim da aba). */
        igSoltar(a, pos) {
            if (a.de === 'gmail') return this.igEmailCartao(a.item, pos);
            if (a.de === 'gdrive') return this.igDriveTrazer(a.item, pos);
            if (a.de === 'youtube') return this.igYtCartao(a.item, pos);
            if (a.de === 'lembretes') return this.igLbCartao(a.item, pos);
        },
        igNoQuadro(b, item) { return this.igSoltar({ de: b.tipo, origem: b.id, item }, null); },
        /** Cria um cartão comum (texto, tabela, imagem ou de integração) na aba aberta, na pasta aberta e na posição pedida. */
        igCriar(tipo, titulo, dados, pos, w, h) {
            const sec = this.aba;
            if (!sec) return null;
            if (Array.isArray(this.tiposBloco) && !this.tiposBloco.some(t => t.id === tipo)) { this.aviso('Esta página não tem esse tipo de cartão.'); return null; }
            let id = typeof this.criarBloco === 'function' ? this.criarBloco(sec, tipo, pos, { titulo, dados }) : null;
            if (!id) {
                const antes = new Set(this.estado.blocos.map(x => x.id));
                this.novoBloco(sec, tipo);
                const nb = this.estado.blocos.find(x => !antes.has(x.id));
                if (!nb) return null;
                id = nb.id;
                if (titulo) nb.titulo = titulo;
                nb.dados = Object.assign({}, nb.dados, dados);
            }
            const nb = this.estado.blocos.find(x => x.id === id);
            const aberta = typeof this.pastaAberta === 'function' ? this.pastaAberta(sec) : null;
            if (nb && aberta && aberta !== id) nb.pasta = aberta;
            const lay = this.estado.layout[sec] && this.estado.layout[sec][id];
            if (lay) {
                if (pos) { lay.x = pos.x; lay.y = pos.y; }
                if (w) lay.w = Math.min(w, this.col || this.larg || w);
                if (h) lay.h = h;
            }
            this.menuBloco = false;
            if (typeof this.agendar === 'function') this.agendar();
            return id;
        },
        igCartoesDaAba(b) {
            return this.estado.blocos.filter(x => x.secao === this.aba && x.id !== b.id && !DE_INTEGRACAO.includes(x.tipo) && x.tipo !== 'pasta')
                .map(x => ({ id: x.id, nome: (titulo80(x.titulo) || 'Sem título') + ' · ' + ((this.tiposBloco || []).find(t => t.id === x.tipo)?.nome || x.tipo) }));
        },
        igData: dataCurta,
        igDataHora: dataHora,
        igTam: tamanho,
        igNum: num,
        igIcone: iconeArquivo,
        igAntes() { return ANTES; },
        igAntesNome(n) { return (ANTES.find(a => a[0] === n) || [, ''])[1]; },

        /* ================================================================ Gmail ================================================================ */
        igMsgs(b) { return (this.igUI(b).d || {}).mensagens || []; },
        igDe(m) { return m.de_nome || m.de_email || 'Sem remetente'; },
        async igLerCorpo(m) {
            this.igCorpos ??= {};
            if (this.igCorpos[m.id] != null) return { texto: this.igCorpos[m.id] };
            const r = await pedir(URL_GMAIL + '/mensagens/' + m.id + '/corpo');
            if (r.ok) this.igCorpos[m.id] = String(r.json.texto || '');
            return { texto: String((r.json && r.json.texto) || m.trecho || ''), erro: r.ok ? '' : erroDe(r) };
        },
        async igAbrirEmail(b, m) {
            const ui = this.igUI(b);
            ui.aberto = m; ui.corpo = m.trecho || ''; ui.corpoErro = ''; ui.lendo = true;
            const c = await this.igLerCorpo(m);
            if (ui.aberto !== m) return;
            ui.lendo = false; ui.corpo = c.texto; ui.corpoErro = c.erro || '';
        },
        async igEmailCartao(m, pos) {
            this.aviso('Abrindo o e-mail…');
            const c = await this.igLerCorpo(m);
            const cab = [`De: ${m.de_nome ? m.de_nome + ' <' + (m.de_email || '') + '>' : (m.de_email || '—')}`, `Data: ${dataHora(m.data)}`, `Assunto: ${m.assunto || '(sem assunto)'}`].join('\n');
            const id = this.igCriar('texto', '✉ ' + titulo80(m.assunto || '(sem assunto)'), { texto: cab + '\n\n' + c.texto, tam: 'p', gmail: { id: m.id } }, pos, 420, 360);
            if (id) this.aviso('E-mail virou cartão de texto.' + (c.erro ? ' Só o começo veio: ' + c.erro : ''));
        },
        async igEmailTarefa(b, m) {
            if (!URL_TAREFAS) { this.aviso('Não dá para criar tarefas nesta página.'); return; }
            const r = await pedir(URL_TAREFAS + '/' + window.WFGraficos.areaDoQuadro(this), 'POST', {
                tarefas: [{ titulo: (m.assunto || 'Responder e-mail').slice(0, 150), descricao: ('E-mail de ' + this.igDe(m) + ' em ' + dataHora(m.data)).slice(0, 1000) }],
                categoria: 'E-mail',
            });
            this.aviso(r.ok ? 'Tarefa criada a partir do e-mail (aba Tarefas desta área).' : erroDe(r));
        },
        async igEmailEvento(b, m) {
            if (!URL_CAL) { this.aviso('O calendário não está disponível nesta página.'); return; }
            const r = await pedir(URL_CAL, 'POST', {
                titulo: (m.assunto || 'E-mail').slice(0, 120), descricao: ('E-mail de ' + this.igDe(m)).slice(0, 2000),
                inicio: localDT(1, 9), repete: 'nao', dia_todo: false, lembrete_min: 30, lembrar_email: true, cor: '#ea4335',
            });
            this.aviso(r.ok ? 'Evento criado amanhã às 9h no calendário do painel, com lembrete 30 min antes.' : erroDe(r));
        },
        async igEmailSincronizar(b) {
            const ui = this.igUI(b);
            ui.ocupado = 'sync';
            const r = await pedir(URL_GMAIL + '/sincronizar', 'POST', {});
            ui.ocupado = '';
            this.aviso((r.json && (r.json.ok || r.json.erro)) || erroDe(r));
            await this.igCarregar(b);
        },

        /* ================================================================ Google Drive ================================================================ */
        igArquivos(b) { return (this.igUI(b).d || {}).arquivos || []; },
        igDriveConectar(b) { const d = this.igUI(b).d || {}; return d.conectar ? d.conectar + '?de=cartao' : ''; },
        async igDriveTrazer(f, pos) {
            this.aviso('Trazendo “' + f.nome + '” do Drive…');
            const r = await pedir(URL_DRIVE + '/arquivos/' + encodeURIComponent(f.id));
            if (!r.ok) { this.aviso(erroDe(r)); return; }
            const a = r.json, nome = titulo80(semExt(a.nome)) || 'Arquivo do Drive', ref = { drive: { id: f.id } };
            if (a.tipo === 'imagem') {
                const id = this.igCriar('imagem', nome, Object.assign({ ajuste: 'contain', legenda: '', prop: 0 }, ref), pos, 360, 260);
                if (!id) return;
                this.imagens[id] = a.src;
                Q.enviar(`${this.cfg.url}/img.${id}`, JSON.stringify(a.src)).catch(() => this.aviso('Não consegui salvar a imagem no servidor.'));
                this.aviso('Imagem do Drive no quadro.');
                return;
            }
            if (a.tipo === 'csv') {
                const t = lerCsv(a.conteudo);
                this.igCriar('tabela', nome, Object.assign(t, ref), pos, Math.min(1000, 160 + t.cab.length * 130), Math.min(620, 120 + t.linhas.length * 34));
                this.aviso('Planilha do Drive virou tabela.');
                return;
            }
            if (a.tipo === 'json') {
                let o = null;
                try { o = JSON.parse(a.conteudo); } catch (_) { /* vai como texto */ }
                if (o && o.formato === 'work-flower.workflows') {
                    this.aviso('Esse arquivo é um backup dos workflows: para restaurar, use Configurações › Armazenamento.');
                    return;
                }
                if (o && o.wf === 'bloco' && /^[a-z][a-z0-9_-]{1,30}$/.test(String(o.tipo || '')) && o.dados && typeof o.dados === 'object') {
                    if (this.igCriar(o.tipo, titulo80(o.titulo) || nome, o.dados, pos)) this.aviso('Cartão trazido do Drive.');
                    return;
                }
                if (o) a.conteudo = JSON.stringify(o, null, 2);
            }
            if (a.tipo === 'texto' || a.tipo === 'json') {
                this.igCriar('texto', nome, Object.assign({ texto: String(a.conteudo || '').slice(0, 200000), tam: 'm' }, ref), pos, 400, 340);
                this.aviso('Arquivo do Drive virou cartão de texto.');
                return;
            }
            this.igCriar('texto', '📎 ' + nome, Object.assign({ texto: `${a.nome}\n${a.aviso ? a.aviso + '\n' : ''}Abrir no Google Drive: ${a.link || ''}`, tam: 'm' }, ref), pos, 360, 180);
            this.aviso(a.aviso || 'Esse tipo de arquivo não vira cartão: ficou um atalho para abrir no Drive.');
        },
        async igDriveEnviar(b) {
            const ui = this.igUI(b), x = this.estado.blocos.find(c => c.id === ui.enviar);
            if (!x) { this.aviso('Escolha um cartão desta aba.'); return; }
            const f = arquivoDoCartao(x);
            ui.ocupado = 'enviar';
            const r = await pedir(URL_DRIVE + '/arquivos', 'POST', { nome: f.nome, mime: f.mime, b64: base64(f.conteudo) });
            ui.ocupado = '';
            this.aviso(r.ok ? r.json.msg : erroDe(r));
            if (r.ok) { ui.enviar = ''; this.igCarregar(b); }
        },
        async igDriveRenomear(b, f) {
            const nome = prompt('Novo nome do arquivo no Drive:', f.nome);
            if (!nome || nome === f.nome) return;
            const r = await pedir(URL_DRIVE + '/arquivos/' + encodeURIComponent(f.id), 'PATCH', { nome });
            if (!r.ok) { this.aviso(erroDe(r)); return; }
            f.nome = nome;
        },
        async igDriveBackup(b) {
            const ui = this.igUI(b);
            ui.ocupado = 'backup';
            this.aviso('Fazendo o backup dos workflows no Drive…');
            const r = await pedir(URL_DRIVE + '/backup', 'POST', {});
            ui.ocupado = '';
            this.aviso(r.ok ? r.json.msg : erroDe(r));
            if (r.ok) this.igCarregar(b);
        },
        async igDriveDiario(b, ligado) {
            const r = await pedir(URL_DRIVE + '/diario', 'PATCH', { ligado });
            if (!r.ok) { this.aviso(erroDe(r)); return; }
            this.igUI(b).d.backup_diario = r.json.backup_diario;
            this.aviso(ligado ? 'Backup automático ligado: todo dia de madrugada, na pasta Work Flower.' : 'Backup automático desligado.');
        },

        /* ================================================================ YouTube ================================================================ */
        igYtVideos(b) { const ui = this.igUI(b); return ui.pl ? ui.pl.videos : ((ui.d || {}).videos || []); },
        igYtBuscar(b) { b.dados.canal = String(this.igUI(b).canal || '').trim(); this.igUI(b).pl = null; this.igCarregar(b); },
        igYtMeu(b) { b.dados.canal = ''; this.igUI(b).canal = ''; this.igUI(b).pl = null; this.igCarregar(b); },
        async igYtPlaylist(b, p) {
            const ui = this.igUI(b);
            ui.pl = { titulo: p.titulo, videos: [], carregando: true };
            const r = await pedir(URL_YT + '/cartao?' + new URLSearchParams({ canal: b.dados.canal || '', playlist: p.id }));
            if (!ui.pl) return;
            ui.pl.carregando = false;
            if (r.ok) ui.pl.videos = r.json.videos || []; else { ui.pl = null; this.aviso(erroDe(r)); }
        },
        igYtEmbed(id) { return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?rel=0'; },
        igYtLink(id) { return 'https://www.youtube.com/watch?v=' + encodeURIComponent(id); },
        igYtCartao(v, pos) {
            const id = this.igCriar('youtube', '▶ ' + titulo80(v.titulo), { video: { id: v.id, titulo: v.titulo, publicado: v.publicado || '', views: v.views ?? null }, canal: '' }, pos, 480, 340);
            if (id) this.aviso('Vídeo no quadro: ele toca dentro do cartão.');
        },
        igYtPost(b, v) {
            const sec = this.aba;
            let cr = this.estado.blocos.find(x => x.secao === sec && x.tipo === 'criador');
            if (!cr) {
                const antes = new Set(this.estado.blocos.map(x => x.id));
                this.novoBloco(sec, 'criador');
                cr = this.estado.blocos.find(x => !antes.has(x.id) && x.tipo === 'criador');
                if (!cr) { this.aviso('Esta página não tem o cartão Criador de conteúdo.'); return; }
                cr.dados.posts = [];
            }
            const link = this.igYtLink(v.id);
            cr.dados.posts ??= [];
            if (cr.dados.posts.some(p => p.link === link)) { this.aviso('Esse vídeo já está no Criador de conteúdo.'); return; }
            cr.dados.posts.push({ id: uid(), titulo: String(v.titulo || 'Vídeo').slice(0, 120), rede: 'YouTube', etapa: v.privacidade && v.privacidade !== 'public' ? 'Agendado' : 'Publicado',
                data: v.publicado || '', legenda: String(v.descricao || '').slice(0, 5000), tags: '', link });
            if (typeof this.agendar === 'function') this.agendar();
            this.aviso('Vídeo adicionado ao Criador de conteúdo desta aba (rede YouTube).');
        },
        async igYtSair(b) {
            if (!confirm('Desconectar o YouTube? O Work Flower deixa de ler o seu canal (dá para conectar de novo depois).')) return;
            await pedir(URL_YT + '/google', 'DELETE');
            this.igUI(b).d = null;
            this.igCarregar(b);
        },

        /* ================================================================ Lembretes ================================================================ */
        igLembretes(b) { return (this.igUI(b).d || {}).lembretes || []; },
        igLbEnvio(b) { return (this.igUI(b).d || {}).envio || {}; },
        async igLbEmail(b, l, ligado) {
            const r = await pedir(URL_LEMB + '/' + l.evento.id, 'PATCH', { lembrar_email: ligado });
            if (!r.ok) { this.aviso(erroDe(r)); return; }
            this.igLembretes(b).forEach(x => { if (x.evento.id === l.evento.id) x.evento.lembrar_email = ligado; });
        },
        async igLbTodos(b, ligar) {
            const r = await pedir(URL_LEMB + '/email', 'POST', { ligar });
            this.aviso(r.ok ? (ligar ? 'E-mail ligado em todos os lembretes.' : 'E-mail desligado em todos os lembretes.') : erroDe(r));
            if (r.ok) this.igCarregar(b);
        },
        async igLbTeste(b) {
            const r = await pedir(URL_LEMB + '/teste', 'POST', {});
            this.aviso(r.ok ? r.json.msg : erroDe(r));
        },
        igLbAbrirNovo(b, deCartao) {
            const x = deCartao ? this.estado.blocos.find(c => c.id === deCartao) : null;
            this.igUI(b).novo = { titulo: x ? titulo80(x.titulo) : '', descricao: x && x.tipo === 'texto' ? String(x.dados.texto || '').slice(0, 2000) : '', quando: localDT(1, 9), antes: 15, email: true, msg: '' };
        },
        async igLbCriar(b) {
            const ui = this.igUI(b), n = ui.novo;
            if (!n || !n.titulo.trim()) { if (n) n.msg = 'Escreva do que lembrar.'; return; }
            if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(n.quando || '')) { n.msg = 'Escolha o dia e a hora.'; return; }
            n.msg = 'Salvando…';
            const r = await pedir(URL_CAL, 'POST', { titulo: n.titulo.trim().slice(0, 120), descricao: n.descricao || null, inicio: n.quando, repete: 'nao',
                dia_todo: false, lembrete_min: +n.antes, lembrar_email: !!n.email, cor: '#6366f1' });
            if (!r.ok) { n.msg = erroDe(r); return; }
            ui.novo = null;
            this.aviso('Lembrete criado (também aparece no calendário do painel).');
            this.igCarregar(b);
        },
        igLbCartao(l, pos) {
            const e = l.evento;
            const txt = [`Quando: ${dataHora(l.quando)}${e.dia_todo ? ' (dia todo)' : ''}`, `Lembrete: ${this.igAntesNome(e.lembrete_min)}${e.lembrar_email ? ' · por e-mail' : ''}`, e.descricao ? '\n' + e.descricao : ''].join('\n');
            if (this.igCriar('texto', '⏰ ' + titulo80(e.titulo), { texto: txt, tam: 'm', evento: { id: e.id } }, pos, 340, 220)) this.aviso('Lembrete virou cartão de texto.');
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco)) TIPOS.forEach(t => { if (!this.tiposBloco.some(x => x.id === t.id)) this.tiposBloco.push(t); });
            const r = prep ? prep.apply(this, arguments) : undefined;
            if (primeira && typeof this.novoBloco === 'function') {
                const novo = this.novoBloco;
                this.novoBloco = function (sec, tipo, ...resto) {
                    if (tipo === 'gdrive') return this.novoGdrive(sec);
                    if (tipo === 'gmail') return this.novoGmail(sec);
                    if (tipo === 'youtube') return this.novoYoutube(sec);
                    if (tipo === 'lembretes') return this.novoLembretes(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            return r;
        };
        return Object.assign(m, extras, { igEstado: {}, igCorpos: {} });
    };
})();
