/* Work Flower · abas Calls e Integrações da área Negócios (partials/integracoes; servidor em CallController e
   IntegracaoController). Sempre sobre a conta de quem está logado. Carrega na primeira vez que a aba abre. */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const cab = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token'), 'X-WF-Dono': '' });

    /* O corpo vai em base64: o mod_security da HostGator responde 406 a textos que parecem SQL ou código. */
    function codificar(obj) {
        const bytes = new TextEncoder().encode(JSON.stringify(obj));
        let bin = '';
        for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
        return { b64: btoa(bin) };
    }

    async function pedir(url, metodo = 'GET', corpo) {
        const r = await fetch(url, { method: metodo, credentials: 'same-origin', headers: cab(), body: corpo ? JSON.stringify(codificar(corpo)) : undefined });
        let j = {};
        try { j = await r.json(); } catch { /* resposta sem JSON */ }
        if (r.status === 406) throw new Error('O servidor bloqueou o pedido. Tire caracteres estranhos do texto e tente de novo.');
        if (r.status === 419) throw new Error('Sua sessão expirou. Recarregue a página.');
        if (!r.ok) {
            const primeiro = j.errors ? Object.values(j.errors)[0] : null;
            throw new Error((Array.isArray(primeiro) ? primeiro[0] : primeiro) || j.message || j.erro || 'Não deu certo. Tente de novo.');
        }
        return j;
    }

    const doisDig = n => String(n).padStart(2, '0');
    function agoraMais(min) {
        const d = new Date(Date.now() + min * 60000);
        d.setMinutes(Math.ceil(d.getMinutes() / 15) * 15, 0, 0);
        return `${d.getFullYear()}-${doisDig(d.getMonth() + 1)}-${doisDig(d.getDate())}T${doisDig(d.getHours())}:${doisDig(d.getMinutes())}`;
    }
    function salaJitsi() {
        const b = new Uint8Array(8);
        crypto.getRandomValues(b);
        return 'https://meet.jit.si/WorkFlower-' + Array.from(b, x => x.toString(16).padStart(2, '0')).join('');
    }
    const vazio = () => ({ id: null, titulo: '', inicio: agoraMais(60), duracao_min: 30, plataforma: 'jitsi', link: salaJitsi(), participantes: [], lembrete_min: '15', descricao: '' });

    /* ================= Calls ================= */
    window.wfCalls = function (url, urlCanais) {
        return {
            calls: [], pessoas: [], nomes: {}, plataformas: {}, lembretes: [], eu: 0, canaisVoz: [],
            carregou: false, enviando: false, erro: '', ok: '', f: vazio(), _aberto: false,
            abrir() { if (!this._aberto) { this._aberto = true; this.carregar(); } },
            async carregar() {
                try {
                    const j = await pedir(url);
                    Object.assign(this, { calls: j.calls, pessoas: j.pessoas, nomes: j.nomes, plataformas: j.plataformas, lembretes: j.lembretes, eu: j.eu });
                } catch { /* sem rede */ } finally { this.carregou = true; }
                try { this.canaisVoz = ((await pedir(urlCanais)).canais || []).filter(c => c.tipo === 'voz'); } catch { this.canaisVoz = []; }
            },
            escolher(id) {
                const antes = this.f.plataforma;
                this.f.plataforma = id;
                if (id === 'jitsi' && !/meet\.jit\.si/.test(this.f.link)) this.f.link = salaJitsi();
                else if (antes === 'jitsi' && id !== 'jitsi' && /meet\.jit\.si/.test(this.f.link)) this.f.link = '';
            },
            novoJitsi() { this.f.link = salaJitsi(); },
            dicaLink() {
                return ({ meet: 'https://meet.google.com/abc-defg-hij', zoom: 'https://zoom.us/j/…', discord: 'https://discord.com/channels/…', jitsi: 'https://meet.jit.si/…' })[this.f.plataforma] || 'https://…';
            },
            rotuloLembrete(m) {
                if (m === 0) return 'Na hora';
                if (m < 60) return m + ' min antes';
                if (m < 1440) return (m / 60) + ' h antes';
                return (m / 1440) === 7 ? '1 semana antes' : (m / 1440) + ' dia(s) antes';
            },
            limpar() { this.f = vazio(); this.erro = ''; },
            editar(c) {
                this.f = { ...c, participantes: [...c.participantes], lembrete_min: c.lembrete_min === null ? '' : String(c.lembrete_min), descricao: c.descricao || '' };
                this.erro = ''; this.ok = '';
                this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            async salvar() {
                this.erro = ''; this.ok = ''; this.enviando = true;
                const corpo = { ...this.f, lembrete_min: this.f.lembrete_min === '' ? null : Number(this.f.lembrete_min) };
                try {
                    const j = await pedir(this.f.id ? `${url}/${this.f.id}` : url, this.f.id ? 'PUT' : 'POST', corpo);
                    const i = this.calls.findIndex(c => c.id === j.call.id);
                    if (i >= 0) this.calls.splice(i, 1, j.call); else this.calls.push(j.call);
                    this.calls.sort((a, b) => a.inicio.localeCompare(b.inicio));
                    this.ok = this.f.id ? 'Call atualizada.' : 'Call marcada e colocada no calendário.';
                    this.f = vazio();
                } catch (e) { this.erro = e.message; } finally { this.enviando = false; }
            },
            async apagar(c) {
                if (!confirm(`Cancelar a call "${c.titulo}"? Ela sai do calendário de todos.`)) return;
                try { await pedir(`${url}/${c.id}`, 'DELETE'); this.calls = this.calls.filter(x => x.id !== c.id); } catch (e) { alert(e.message); }
            },
            quando(c) {
                const d = new Date(c.inicio);
                return d.toLocaleString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) + ` (${c.duracao_min} min)`;
            },
            passou(c) { return new Date(c.inicio).getTime() + c.duracao_min * 60000 < Date.now(); },
            proximas() { return this.calls.filter(c => !this.passou(c)); },
            passadas() { return this.calls.filter(c => this.passou(c)).reverse(); },
            async copiar(c) {
                try { await navigator.clipboard.writeText(c.link); c._copiado = true; setTimeout(() => { c._copiado = false; }, 1500); } catch { prompt('Copie o link:', c.link); }
            },
        };
    };

    /* ================= Discord ================= */
    window.wfDiscord = function (url) {
        return {
            d: { conectado: false, tem_bot: false, eventos: {}, nomes: {} }, canais: [],
            webhook: '', bot: '', erro: '', ok: '', enviando: false, _aberto: false,
            abrir() { if (!this._aberto) { this._aberto = true; this.carregar(); } },
            estado() {
                if (!this.d.conectado) return 'Não conectado';
                return '● Conectado' + (this.d.servidor ? ' a ' + this.d.servidor : '') + (this.d.canal ? ' › #' + this.d.canal : '');
            },
            async carregar() {
                try { this.d = await pedir(url); } catch { /* sem rede */ }
                if (this.d.tem_bot) this.lerCanais();
            },
            async lerCanais() {
                try { this.canais = (await pedir(url + '/canais')).canais || []; } catch { this.canais = []; }
            },
            async salvar(extra = {}) {
                this.erro = ''; this.ok = ''; this.enviando = true;
                try {
                    const j = await pedir(url, 'POST', { webhook_url: this.webhook, bot_token: this.bot, ...extra });
                    this.d = j;
                    if (j.canais) this.canais = j.canais;
                    else if (!j.tem_bot) this.canais = [];
                    this.webhook = ''; this.bot = '';
                    this.ok = 'Salvo.';
                } catch (e) { this.erro = e.message; } finally { this.enviando = false; }
            },
            tirarBot() { this.salvar({ tirar_bot: true }); },
            alternar(k, v) { this.d.eventos[k] = v; this.salvar({ eventos: { ...this.d.eventos } }); },
            async testar() {
                this.erro = ''; this.ok = ''; this.enviando = true;
                try { await pedir(url + '/testar', 'POST', {}); this.ok = 'Mensagem de teste enviada. Confira o canal.'; this.d.ultimo_erro = null; } catch (e) { this.erro = e.message; } finally { this.enviando = false; }
            },
            async desconectar() {
                if (!confirm('Desconectar o Discord? Os avisos param de chegar.')) return;
                try { this.d = await pedir(url, 'DELETE'); this.canais = []; this.ok = 'Desconectado.'; } catch (e) { this.erro = e.message; }
            },
        };
    };
})();
