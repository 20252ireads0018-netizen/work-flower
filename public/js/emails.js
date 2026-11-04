/* Work Flower · Negócios › E-mails (tela em areas/negocios/emails.blade.php; servidor em EmailController).
   Caixas conectadas por IMAP (ou Gmail pelo Google), lista com categorias automáticas, busca e resposta por mailto.
   Os corpos vão como {"b64": base64(JSON)}: o mod_security da HostGator recusa (406) texto que parece SQL ou código. */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const CORES = { Clientes: '#38bdf8', Financeiro: '#4ade80', 'Promoções': '#f472b6', Outros: '#94a3b8' };
    const EXTRAS = ['#fbbf24', '#a78bfa', '#fb923c', '#2dd4bf', '#f87171', '#facc15'];
    const semAcento = s => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const base64 = s => { const b = new TextEncoder().encode(s); let t = ''; for (let i = 0; i < b.length; i += 0x8000) t += String.fromCharCode.apply(null, b.subarray(i, i + 0x8000)); return btoa(t); };

    const PROVEDORES = [
        { id: 'titan', nome: 'Titan', host: 'imap.titan.email', modo: '993-ssl', dica: 'Titan (e-mail do domínio contratado na HostGator): use o e-mail completo e a senha da caixa.' },
        { id: 'gmail', nome: 'Gmail', host: 'imap.gmail.com', modo: '993-ssl', dica: 'Gmail: ative a verificação em duas etapas e crie uma <a class="underline" href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">senha de app</a> (16 letras). A senha normal da conta não funciona.' },
        { id: 'hostgator', nome: 'cPanel / HostGator', host: '', modo: '993-ssl', dica: 'cPanel: o servidor costuma ser mail.seudominio.com.br, com o e-mail completo como usuário.' },
        { id: 'outro', nome: 'Outro', host: '', modo: '993-ssl', dica: 'Procure “configurações IMAP” do seu provedor: servidor, porta (quase sempre 993 com SSL) e usuário.' },
    ];

    window.wfEmails = function (cfg) {
        return {
            cfg,
            contas: cfg.estado.contas || [],
            mensagens: cfg.estado.mensagens || [],
            regras: cfg.estado.regras || [],
            categorias: cfg.estado.categorias || [],
            regrasEdit: [],
            provedores: PROVEDORES,
            fm: { provedor: 'titan', endereco: '', senha: '', host: 'imap.titan.email', modo: '993-ssl', usuario: '' },
            dica: PROVEDORES[0].dica,
            filtro: { cat: 'todas', busca: '', naoLidas: false },
            painel: '',
            aberta: null,
            limite: 100,
            ocupado: false,
            ok: cfg.ok || '',
            erro: cfg.erro || '',

            iniciar() {
                this.$watch('filtro', () => { this.limite = 100; }, { deep: true });
                try { const c = sessionStorage.getItem('emails.cat'); if (c && (c === 'todas' || this.categorias.includes(c))) this.filtro.cat = c; } catch (e) {}
                this.$watch('filtro.cat', v => { try { sessionStorage.setItem('emails.cat', v); } catch (e) {} });
            },
            alternar(p) { this.painel = this.painel === p ? '' : p; },

            /* ---------- lista ---------- */
            get contagem() {
                const n = {};
                for (const m of this.mensagens) n[m.categoria] = (n[m.categoria] || 0) + 1;
                return n;
            },
            get filtradas() {
                const termo = semAcento(this.filtro.busca.trim());
                return this.mensagens.filter(m =>
                    (this.filtro.cat === 'todas' || m.categoria === this.filtro.cat)
                    && (!this.filtro.naoLidas || !m.lido)
                    && (!termo || semAcento([m.de_nome, m.de_email, m.assunto, m.trecho].join(' ')).includes(termo)));
            },
            get visiveis() { return this.filtradas.slice(0, this.limite); },
            limparFiltro() { this.filtro.cat = 'todas'; this.filtro.busca = ''; this.filtro.naoLidas = false; },

            cor(c) {
                if (CORES[c]) return CORES[c];
                let h = 0;
                for (const ch of String(c)) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
                return EXTRAS[h % EXTRAS.length];
            },
            contaDe(m) { return (this.contas.find(c => c.id === m.conta_id) || {}).endereco || ''; },
            quando(iso) {
                if (!iso) return '';
                const d = new Date(iso), agora = new Date();
                if (d.toDateString() === agora.toDateString()) return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                const dias = (agora - d) / 864e5;
                if (dias < 6) return d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
                return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }).replace('.', '');
            },
            dataLonga(iso) { return iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'medium', timeStyle: 'short' }) : ''; },
            mailto(m) {
                const assunto = m.assunto ? (/^re:/i.test(m.assunto) ? m.assunto : 'Re: ' + m.assunto) : '';
                return 'mailto:' + encodeURIComponent(m.de_email || '').replace('%40', '@') + (assunto ? '?subject=' + encodeURIComponent(assunto) : '');
            },
            /** Situação de um comando agendado pelo último batimento: verde se rodou dentro de $limite minutos. */
            saudeCron(iso, limite) {
                if (!iso) return { classe: 'em-ruim', texto: 'nunca rodou neste servidor (cron do cPanel parado ou não criado)' };
                const min = Math.round((Date.now() - new Date(iso)) / 6e4);
                const quando = this.dataLonga(iso) + (min < 1 ? ' (agora há pouco)' : min < 120 ? ` (há ${min} min)` : ` (há ${Math.round(min / 60)} h)`);
                return min <= limite ? { classe: 'em-bom', texto: 'ativo · última vez ' + quando } : { classe: 'em-ruim', texto: 'parado · última vez ' + quando };
            },

            /* ---------- servidor ---------- */
            async api(metodo, caminho, corpo) {
                this.ocupado = true; this.erro = ''; this.ok = '';
                try {
                    const r = await fetch(cfg.base + caminho, {
                        method: metodo, credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token') },
                        body: corpo === undefined ? undefined : JSON.stringify({ b64: base64(JSON.stringify(corpo)) }),
                    });
                    const j = await r.json().catch(() => ({}));
                    if (j.contas) this.aplicar(j);
                    if (j.envio) this.cfg.envio = j.envio;
                    if (!r.ok) {
                        const msg = j.erro || (j.errors && Object.values(j.errors)[0][0])
                            || ({ 419: 'A sessão expirou. Recarregue a página.', 429: 'Muitas tentativas seguidas. Espere um minuto.', 406: 'O servidor bloqueou o pedido (proteção da hospedagem). Tente de novo.' }[r.status])
                            || 'Não deu certo (' + r.status + '). Tente de novo.';
                        throw new Error(msg);
                    }
                    this.ok = j.ok || '';
                    if (j.erro) this.erro = j.erro;
                    return j;
                } catch (e) {
                    this.erro = e.message || 'Sem conexão com o servidor.';
                    return null;
                } finally {
                    this.ocupado = false;
                }
            },
            aplicar(j) {
                this.contas = j.contas; this.mensagens = j.mensagens; this.regras = j.regras; this.categorias = j.categorias;
                if (this.filtro.cat !== 'todas' && !this.categorias.includes(this.filtro.cat)) this.filtro.cat = 'todas';
            },

            usarProvedor(p) {
                this.fm.provedor = p.id; this.fm.modo = p.modo; this.dica = p.dica;
                this.fm.host = p.host;
                this.sugerirHost();
            },
            sugerirHost() {
                if (this.fm.provedor === 'hostgator' && !this.fm.host && this.fm.endereco.includes('@')) this.fm.host = 'mail.' + this.fm.endereco.split('@')[1];
            },
            editarConta(c) {
                const p = PROVEDORES.find(x => x.host && x.host === c.host);
                this.fm = { provedor: p ? p.id : 'outro', endereco: c.endereco, senha: '', host: c.host || '', modo: (c.porta || 993) + '-' + (c.seguranca || 'ssl'), usuario: '' };
                this.dica = 'Digite a senha nova de ' + c.endereco + ' e clique em “Testar e conectar”. A caixa continua com as mensagens que já estão aqui.';
                this.$nextTick(() => this.$root.querySelector('input[type=password]')?.focus());
            },
            async conectar() {
                const [porta, seguranca] = this.fm.modo.split('-');
                const j = await this.api('POST', '/contas', {
                    endereco: this.fm.endereco.trim(), senha: this.fm.senha, host: this.fm.host.trim(),
                    porta: Number(porta), seguranca, usuario: this.fm.usuario.trim(),
                });
                if (j) { this.fm.senha = ''; this.fm.endereco = ''; this.fm.usuario = ''; this.painel = ''; }
            },
            async remover(c) {
                if (!confirm(`Desconectar ${c.endereco}? As mensagens dela saem daqui (continuam no seu e-mail).`)) return;
                await this.api('DELETE', '/contas/' + c.id);
            },
            async sincronizar(conta) {
                await this.api('POST', '/sincronizar', conta ? { conta } : {});
            },
            async testarEnvio() {
                await this.api('POST', '/teste-envio', {});
            },

            abrirRegras() {
                this.regrasEdit = this.regras.map(r => ({ ...r }));
                this.alternar('regras');
            },
            moverRegra(i, d) {
                const j = i + d;
                if (j < 0 || j >= this.regrasEdit.length) return;
                const [r] = this.regrasEdit.splice(i, 1);
                this.regrasEdit.splice(j, 0, r);
            },
            async salvarRegras() {
                const regras = this.regrasEdit.filter(r => r.termo.trim() && r.categoria.trim());
                const j = await this.api('PUT', '/regras', { regras });
                if (j) { this.regrasEdit = this.regras.map(r => ({ ...r })); this.painel = ''; }
            },
            async mudarCategoria(m, categoria) {
                const j = await this.api('PATCH', '/mensagens/' + m.id, { categoria });
                if (!j || !j.mensagem) return;
                Object.assign(m, j.mensagem);
                if (!this.categorias.includes(m.categoria)) this.categorias.push(m.categoria);
                this.ok = m.manual ? `Este e-mail fica sempre em ${m.categoria}.` : `Voltou para a categoria automática (${m.categoria}).`;
            },
        };
    };
})();
