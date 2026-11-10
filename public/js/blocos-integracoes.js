/* Work Flower · blocos do quadro para integrações que antes só existiam em telas separadas: Discord, Notion e Textos rápidos.
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o integracoes-cartoes.js (carregue depois do graficos.js:
   usa wfNovoCartao e WFGraficos.pedir). Tokens e webhooks ficam só no servidor; a conexão continua em
   Negócios › Integrações (Discord e Notion) e em Configurações › Atalhos (Textos rápidos), e o bloco só usa.
   - discord: b.dados = { }   status, mensagem/embed pelo webhook (IntegracaoController::enviar), canais pelo bot,
                              atalhos para mandar o resumo de um cartão da aba ou das tarefas da área
   - notion:  b.dados = { q }  busca as páginas compartilhadas, abre o conteúdo no bloco e importa como cartão
                              (mesmo formato do notas.js: o "Sincronizar com o Notion" atualiza esses cartões)
   - textos:  b.dados = { }   lista, cria, edita e apaga os textos rápidos (window.wfExpansor, prefs "trechos") */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function' || !window.WFGraficos) return;
    const TAG = document.currentScript ? document.currentScript.dataset : {};
    const URL_DC = TAG.discord || '', URL_NOTION = TAG.notion || '', URL_NEGOCIOS = TAG.negocios || '';
    const URL_TAREFAS = TAG.tarefas || '', URL_INSTALADOR = TAG.instalador || '';
    const TEXTOS = TAG.textos === '1';
    const pedir = (...a) => window.WFGraficos.pedir(...a);
    const TIPOS = [
        URL_DC && { id: 'discord', nome: 'Discord' },
        URL_NOTION && { id: 'notion', nome: 'Notion' },
        TEXTOS && { id: 'textos', nome: 'Textos rápidos' },
    ].filter(Boolean);
    const DESTE_ARQUIVO = ['discord', 'notion', 'textos'];
    const DE_INTEGRACAO = ['gdrive', 'gmail', 'youtube', 'lembretes', 'pasta', ...DESTE_ARQUIVO];
    const STATUS = { pendente: 'Pendente', andamento: 'Em andamento', concluida: 'Concluída' };

    const erroDe = r => (r.json && (r.json.erro || r.json.message)) || (r.status === 0 ? 'Sem conexão com o servidor.' : r.status === 419 ? 'A sessão expirou. Recarregue a página.' : 'Não deu certo agora. Tente de novo.');
    const titulo80 = s => String(s || '').replace(/[\u0000-\u001f]+/g, ' ').trim().slice(0, 80);
    const dataHora = s => { if (!s) return ''; const d = new Date(s); return isNaN(d) ? '' : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); };
    const dataBR = s => String(s || '').slice(0, 10).split('-').reverse().join('/');
    /** Corpo em base64 (CorpoCodificado no servidor): o mod_security da hospedagem não barra texto que parece código. */
    function b64(obj) {
        const bytes = new TextEncoder().encode(JSON.stringify(obj));
        let s = '';
        for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return { b64: btoa(s) };
    }
    const exp = () => window.wfExpansor || null;

    /** Resumo em texto de um cartão do quadro (para mandar ao Discord). */
    function resumoDoCartao(x) {
        const d = x.dados || {};
        if (x.tipo === 'texto') return String(d.texto || '');
        if (x.tipo === 'lista') return (d.itens || []).map(i => `${i.f ? '☑' : '☐'} ${i.t || ''}`).join('\n');
        if (x.tipo === 'tabela') {
            const linhas = [d.cab || [], ...(d.linhas || []).slice(0, 25)];
            return linhas.map(l => l.map(c => String(c ?? '').slice(0, 60)).join(' | ')).join('\n');
        }
        if (x.tipo === 'criador') return (d.posts || []).slice(0, 25).map(p => `• ${p.titulo || 'Post'} (${p.rede || ''} · ${p.etapa || ''}${p.data ? ' · ' + dataBR(p.data) : ''})`).join('\n');
        if (x.tipo === 'habitos') return (d.habitos || []).map(h => `• ${h.nome || h.t || ''}`).join('\n');
        if (x.tipo === 'imagem' && d.url && /^https?:/.test(d.url)) return d.url;
        return '';
    }

    const extras = {
        /* ---------- criar os blocos ---------- */
        novoDiscord(sec) { return this.wfNovoCartao(sec, 'discord', 'Discord', 460, 500, {}, 'Discord adicionado: mande mensagens para o canal do seu servidor daqui.'); },
        novoNotion(sec) { return this.wfNovoCartao(sec, 'notion', 'Notion', 500, 500, { q: '' }, 'Notion adicionado: abra as páginas aqui e traga para o quadro.'); },
        novoTextos(sec) { return this.wfNovoCartao(sec, 'textos', 'Textos rápidos', 440, 480, {}, 'Textos rápidos adicionado: crie atalhos que viram textos em qualquer campo.'); },

        /* ---------- estado de cada bloco (não vai para o banco) ---------- */
        biUI(b) {
            this.biEstado[b.id] ??= {
                carregando: false, erro: '', d: null, ver: 'msg', ocupado: '', msg: { titulo: '', texto: '', link: '' }, nota: '', cartao: '',
                canais: null, canaisErro: '', itens: null, aberto: null, lendo: false, q: '', ed: { i: -1, atalho: '', nome: '', texto: '' }, editando: false, edErro: '', filtro: '',
            };
            return this.biEstado[b.id];
        },
        biIniciar(b) {
            b.dados ??= {};
            const ui = this.biUI(b);
            if (b.tipo === 'textos') return;
            if (!ui.d && !ui.carregando) this.biCarregar(b);
        },
        async biCarregar(b) {
            const ui = this.biUI(b);
            const url = b.tipo === 'discord' ? URL_DC : b.tipo === 'notion' ? URL_NOTION : '';
            if (!url || ui.carregando) return;
            ui.carregando = true; ui.erro = '';
            const r = await pedir(url);
            ui.carregando = false;
            ui.d = r.ok ? (r.json || {}) : { conectado: false };
            if (!r.ok) ui.erro = erroDe(r);
            if (r.ok && ui.d.conectado && b.tipo === 'notion' && !ui.itens) this.biNtBuscar(b);
            if (r.ok && ui.d.conectado && b.tipo === 'discord' && ui.d.tem_bot && ui.ver === 'canais') this.biDcCanais(b);
        },
        /** Vai para Negócios › Integrações (a aba abre sozinha pelo sessionStorage, como na volta do Notion). */
        biIrIntegracoes() {
            try { sessionStorage.setItem('negocios.aba', 'integracoes'); } catch (_) { /* segue */ }
            location.href = URL_NEGOCIOS || '/areas/negocios';
        },
        biDataHora: dataHora,
        biData: dataBR,
        /** Cartões desta aba que dá para resumir (sem os blocos de integração e as pastas). */
        biCartoesDaAba(b) {
            return this.estado.blocos.filter(x => x.secao === this.aba && x.id !== b.id && !DE_INTEGRACAO.includes(x.tipo))
                .map(x => ({ id: x.id, nome: (titulo80(x.titulo) || 'Sem título') + ' · ' + ((this.tiposBloco || []).find(t => t.id === x.tipo)?.nome || x.tipo) }));
        },

        /* ================================================================ Discord ================================================================ */
        biDcVer(b, ver) {
            const ui = this.biUI(b);
            ui.ver = ver;
            if (ver === 'canais' && ui.canais === null) this.biDcCanais(b);
        },
        async biDcCanais(b) {
            const ui = this.biUI(b);
            ui.canaisErro = '';
            const r = await pedir(URL_DC + '/canais');
            if (!r.ok) { ui.canais = []; ui.canaisErro = erroDe(r); return; }
            ui.canais = r.json.canais || [];
            if (r.json.erro) ui.canaisErro = r.json.erro;
        },
        biDcLimite(b) { const m = this.biUI(b).msg; return `${m.texto.length}/3500`; },
        async biDcEnviar(b) {
            const ui = this.biUI(b), m = ui.msg;
            const titulo = m.titulo.trim(), link = m.link.trim();
            if (!titulo) { ui.nota = 'Escreva um título para a mensagem.'; return; }
            if (link && !/^https?:\/\/\S+$/i.test(link)) { ui.nota = 'O link precisa começar com http:// ou https://.'; return; }
            if (m.texto.length > 3500) { ui.nota = 'O texto pode ter até 3.500 caracteres.'; return; }
            ui.ocupado = 'enviar'; ui.nota = 'Enviando…';
            const r = await pedir(URL_DC + '/enviar', 'POST', b64({ titulo: titulo.slice(0, 250), texto: m.texto, link: link || null }));
            ui.ocupado = '';
            if (!r.ok || !r.json.ok) { ui.nota = erroDe(r); return; }
            ui.nota = '';
            ui.msg = { titulo: '', texto: '', link: '' };
            if (ui.d) { ui.d.ultimo_envio = r.json.ultimo_envio; ui.d.ultimo_erro = null; }
            this.aviso('Mensagem enviada ao Discord.');
        },
        /** Preenche a mensagem com o resumo de um cartão desta aba (a pessoa confere antes de enviar). */
        biDcDoCartao(b) {
            const ui = this.biUI(b), x = this.estado.blocos.find(c => c.id === ui.cartao);
            if (!x) return;
            const nome = (this.tiposBloco || []).find(t => t.id === x.tipo)?.nome || x.tipo;
            const texto = resumoDoCartao(x).trim();
            ui.msg = { titulo: ('🗂 ' + (titulo80(x.titulo) || nome)).slice(0, 250), texto: (texto || `Cartão “${nome}” do quadro.`).slice(0, 3500), link: '' };
            ui.cartao = '';
            ui.nota = 'Confira o texto e clique em Enviar.';
        },
        /** Preenche a mensagem com as tarefas em aberto da área (as mesmas do quadro de tarefas). */
        async biDcTarefas(b) {
            const ui = this.biUI(b);
            if (!URL_TAREFAS) { ui.nota = 'As tarefas não estão disponíveis nesta página.'; return; }
            const area = window.WFGraficos.areaDoQuadro ? window.WFGraficos.areaDoQuadro(this) : 'diverso';
            ui.ocupado = 'tarefas'; ui.nota = 'Lendo as tarefas…';
            const r = await pedir(URL_TAREFAS + '/' + area);
            ui.ocupado = '';
            if (!r.ok) { ui.nota = erroDe(r); return; }
            const todas = r.json.tarefas || [];
            const abertas = todas.filter(t => t.status !== 'concluida')
                .sort((a, c) => (a.prazo || '9999') < (c.prazo || '9999') ? -1 : (a.prazo || '9999') > (c.prazo || '9999') ? 1 : 0);
            const hoje = new Date().toISOString().slice(0, 10);
            const semana = new Date(Date.now() - 7 * 864e5).toISOString().slice(0, 10);
            const feitas = todas.filter(t => t.status === 'concluida' && (t.concluida_em || '') >= semana).length;
            const linhas = abertas.slice(0, 25).map(t => `• ${t.titulo} (${STATUS[t.status] || t.status}${t.prazo ? ' · prazo ' + dataBR(t.prazo) + (t.prazo < hoje ? ' ⚠ atrasada' : '') : ''})`);
            if (abertas.length > 25) linhas.push(`… e mais ${abertas.length - 25}.`);
            const nomeArea = area.charAt(0).toUpperCase() + area.slice(1);
            ui.msg = {
                titulo: `📋 Tarefas de ${nomeArea}: ${abertas.length} em aberto`,
                texto: ((linhas.join('\n') || 'Nenhuma tarefa em aberto. 🎉') + `\n\nConcluídas nos últimos 7 dias: ${feitas}.`).slice(0, 3500),
                link: '',
            };
            ui.nota = 'Confira o texto e clique em Enviar.';
        },

        /* ================================================================ Notion ================================================================ */
        async biNtBuscar(b) {
            const ui = this.biUI(b);
            ui.ocupado = 'buscar'; ui.erro = '';
            const r = await pedir(URL_NOTION + '/paginas?q=' + encodeURIComponent((b.dados.q || '').trim()));
            ui.ocupado = '';
            if (!r.ok) { ui.itens = []; ui.erro = erroDe(r); if (r.status === 409 && ui.d) ui.d.conectado = false; return; }
            ui.itens = r.json.itens || [];
        },
        async biNtLer(it) {
            const r = await pedir(URL_NOTION + '/conteudo', 'POST', { itens: [{ id: it.id, tipo: it.tipo || null }] });
            if (!r.ok) return { erro: erroDe(r) };
            const x = (r.json.itens || [])[0];
            return x ? { item: x } : { erro: ((r.json.erros || [])[0] || {}).erro || 'O Notion não devolveu esta página.' };
        },
        async biNtAbrir(b, it) {
            const ui = this.biUI(b);
            ui.aberto = { resumo: it, item: null, erro: '' }; ui.lendo = true;
            const c = await this.biNtLer(it);
            if (!ui.aberto || ui.aberto.resumo !== it) return;
            ui.lendo = false;
            ui.aberto.item = c.item || null;
            ui.aberto.erro = c.erro || '';
        },
        /** Página vira cartão de texto (banco de dados vira tabela), com a marca do Notion para sincronizar depois. */
        async biNtImportar(b, it) {
            const ui = this.biUI(b);
            let x = ui.aberto && ui.aberto.resumo === it ? ui.aberto.item : null;
            if (!x) {
                ui.ocupado = 'importar:' + it.id;
                const c = await this.biNtLer(it);
                ui.ocupado = '';
                if (c.erro) { this.aviso(c.erro); return; }
                x = c.item;
            }
            const sec = this.aba;
            const marca = { id: x.id, tipo: x.tipo, editado: x.editado, url: x.url };
            const tit = titulo80((x.icone ? x.icone + ' ' : '') + (x.titulo || 'Sem título'));
            const id = x.tipo === 'banco'
                ? this.wfNovoCartao(sec, 'tabela', tit, Math.min(1100, 160 + (x.cab || []).length * 140), Math.min(640, 120 + (x.linhas || []).length * 36),
                    { cab: x.cab || [''], linhas: x.linhas || [], notion: marca }, 'Banco de dados do Notion virou uma tabela no quadro.')
                : this.wfNovoCartao(sec, 'texto', tit, 380, 320, { texto: String(x.texto || ''), tam: 'm', notion: marca }, 'Página do Notion virou um cartão de texto no quadro.');
            if (id && typeof this.agendar === 'function') this.agendar();
        },

        /* ================================================================ Textos rápidos ================================================================ */
        biTxPronto() { return !!exp(); },
        biTxLigado() { void this.biV; return !!(exp() && exp().ligado()); },
        biTxLigar(v) { if (exp()) exp().ligar(v); },
        biTxLista(b) {
            void this.biV; // relê quando as preferências mudam (evento wf-prefs)
            const e = exp();
            if (!e) return [];
            const f = String(this.biUI(b).filtro || '').trim().toLowerCase();
            return e.lista().map((s, i) => ({ ...s, i }))
                .filter(s => !f || (s.atalho + ' ' + (s.nome || '') + ' ' + s.texto).toLowerCase().includes(f));
        },
        biTxTotal() { void this.biV; return exp() ? exp().lista().length : 0; },
        biTxLimite() { return exp() ? exp().LIMITE : 200; },
        biTxMax(c) { const e = exp(); return c === 'atalho' ? (e ? e.MAX_ATALHO : 30) : (e ? e.MAX_TEXTO : 5000); },
        biTxPrevia(s) { return s.nome || String(s.texto || '').replace(/\s+/g, ' ').slice(0, 80); },
        biTxNovo(b) { const ui = this.biUI(b); ui.ed = { i: -1, atalho: '', nome: '', texto: '' }; ui.edErro = ''; ui.editando = true; },
        biTxEditar(b, s) { const ui = this.biUI(b); ui.ed = { i: s.i, atalho: s.atalho, nome: s.nome || '', texto: s.texto }; ui.edErro = ''; ui.editando = true; },
        biTxVar(b, v, el) {
            const ed = this.biUI(b).ed;
            if (!ed) return;
            const ins = '{' + v + '}';
            if (el && typeof el.selectionStart === 'number') {
                const a = el.selectionStart, z = el.selectionEnd;
                ed.texto = ed.texto.slice(0, a) + ins + ed.texto.slice(z);
                this.$nextTick && this.$nextTick(() => { el.focus(); el.setSelectionRange(a + ins.length, a + ins.length); });
            } else ed.texto += ins;
        },
        biTxSalvar(b) {
            const ui = this.biUI(b), ed = ui.ed, e = exp();
            if (!ui.editando || !e) return;
            const atalho = ed.atalho.trim(), nome = ed.nome.trim(), texto = ed.texto;
            const itens = e.lista().slice();
            if (!atalho || /\s/.test(atalho)) { ui.edErro = 'O atalho não pode ficar vazio nem ter espaços.'; return; }
            if (!texto) { ui.edErro = 'Escreva o texto que entra no lugar do atalho.'; return; }
            if (itens.some((s, i) => s.atalho === atalho && i !== ed.i)) { ui.edErro = 'Já existe um texto com esse atalho.'; return; }
            if (ed.i < 0 && itens.length >= e.LIMITE) { ui.edErro = `O limite é de ${e.LIMITE} textos.`; return; }
            const novo = { atalho, texto, nome };
            if (ed.i >= 0) itens[ed.i] = novo; else itens.push(novo);
            e.salvar(itens);
            ui.editando = false;
            this.aviso('Texto salvo: digite ' + atalho + ' em qualquer campo para usar.');
        },
        biTxApagar(b, s) {
            const e = exp();
            if (!e || !confirm(`Apagar o texto "${s.atalho}"?`)) return;
            e.salvar(e.lista().filter((_, j) => j !== s.i));
            const ui = this.biUI(b);
            if (ui.editando && ui.ed.i === s.i) ui.editando = false;
            this.aviso('Texto apagado.');
        },
        async biTxCopiar(s) {
            const e = exp();
            const t = e ? e.montar(s.texto).texto : s.texto;
            try { await navigator.clipboard.writeText(t); this.aviso('Texto copiado (com as variáveis preenchidas).'); }
            catch (_) { this.aviso('O navegador não deixou copiar. Selecione o texto e use Ctrl + C.'); }
        },
        biTxConfig() { window.dispatchEvent(new CustomEvent('abrir-atalhos')); },
        biTxInstalador() { return URL_INSTALADOR; },
        biTxVars() { return ['data', 'hora', 'data_extenso', 'dia_semana', 'nome', 'primeiro_nome', 'email', 'cursor']; },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco)) TIPOS.forEach(t => { if (!this.tiposBloco.some(x => x.id === t.id)) this.tiposBloco.push(t); });
            const r = prep ? prep.apply(this, arguments) : undefined;
            if (primeira) {
                if (!this._biPrefs) { this._biPrefs = true; window.addEventListener('wf-prefs', () => { this.biV++; }); }
                if (typeof this.novoBloco === 'function') {
                    const novo = this.novoBloco;
                    this.novoBloco = function (sec, tipo, ...resto) {
                        if (tipo === 'discord') return this.novoDiscord(sec);
                        if (tipo === 'notion') return this.novoNotion(sec);
                        if (tipo === 'textos') return this.novoTextos(sec);
                        return novo.call(this, sec, tipo, ...resto);
                    };
                }
            }
            return r;
        };
        return Object.assign(m, extras, { biEstado: {}, biV: 0 });
    };
})();
