/* Work Flower · aba Equipe da área Negócios (partials/equipe; servidor em EquipeController e CompartilharController).
   Lista as pessoas dos workflows do dono, quem está online e onde, permissões (editar ou só ver), bloqueio,
   tarefas delegadas e o histórico de colaborações. Atualiza sozinha a cada 20 s enquanto a aba está aberta. */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    // pedidos da equipe são sempre sobre a conta de quem está logado (não sobre o workflow aberto)
    const cab = () => ({ 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token'), 'X-WF-Dono': '' });
    let URL_EQUIPE = '/equipe';
    const BASE = () => URL_EQUIPE;
    const COMP = () => (meta('wf-compartilhar-url') || '/compartilhar/negocios').replace(/\/[^/]+$/, '');

    window.wfEquipe = function (url) {
        if (url) URL_EQUIPE = url;
        return {
            vendoOutro: !!meta('wf-dono'),
            pessoas: [], atividades: [], areas: [], carregou: false,
            email: '', convidarEm: ['negocios'], enviando: false, erro: '', ok: '',
            init() {
                if (this.vendoOutro) return;
                this.carregar();
                this._t = setInterval(() => { if (!document.hidden && this.$el.offsetParent) this.carregar(); }, 20000);
            },
            async carregar() {
                try {
                    const r = await fetch(BASE(), { credentials: 'same-origin', headers: cab() });
                    if (!r.ok) return;
                    const j = await r.json();
                    this.pessoas = j.pessoas || []; this.atividades = j.atividades || []; this.areas = j.areas || [];
                } catch { /* sem rede */ } finally { this.carregou = true; }
            },
            iniciais(n) { return String(n || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(s => s[0].toUpperCase()).join(''); },
            quando(iso) {
                if (!iso) return '';
                const s = Math.round((Date.now() - new Date(iso)) / 1000);
                if (s < 60) return 'agora';
                if (s < 3600) return `há ${Math.floor(s / 60)} min`;
                if (s < 86400) return `há ${Math.floor(s / 3600)} h`;
                return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
            },
            textoAgora(p) {
                if (p.online.length) return '● Online agora: ' + p.online.map(o => o.area + (o.aba ? ' › ' + o.aba : '')).join(', ');
                return p.ultima ? 'Última mudança ' + this.quando(p.ultima) : 'Ainda não mexeu em nada';
            },
            async alterar(p, a, mudanca) {
                const antes = { pode_editar: a.pode_editar, bloqueado: a.bloqueado };
                Object.assign(a, mudanca);
                try {
                    const r = await fetch(`${BASE()}/${p.id}/${a.area}`, { method: 'PATCH', credentials: 'same-origin', headers: cab(), body: JSON.stringify(mudanca) });
                    if (!r.ok) throw new Error(String(r.status));
                    const j = await r.json();
                    a.pode_editar = j.pode_editar; a.bloqueado = j.bloqueado;
                } catch { Object.assign(a, antes); alert('Não consegui salvar. Tente de novo.'); }
            },
            async remover(p, a) {
                if (!confirm(`Tirar ${p.nome} do workflow ${a.nome}?`)) return;
                const r = await fetch(`${COMP()}/${a.area}/${p.id}`, { method: 'DELETE', credentials: 'same-origin', headers: cab() });
                if (r.ok) this.carregar(); else alert('Não consegui tirar o acesso. Tente de novo.');
            },
            async convidar() {
                this.erro = ''; this.ok = '';
                const email = this.email.trim();
                if (!email) { this.erro = 'Digite o e-mail da pessoa.'; return; }
                if (!this.convidarEm.length) { this.erro = 'Escolha pelo menos uma área.'; return; }
                this.enviando = true;
                const feitos = [];
                try {
                    for (const area of this.convidarEm) {
                        const r = await fetch(`${COMP()}/${area}`, { method: 'POST', credentials: 'same-origin', headers: cab(), body: JSON.stringify({ email }) });
                        const j = await r.json().catch(() => ({}));
                        if (r.ok) feitos.push(this.areas.find(a => a.id === area)?.nome || area);
                        else { this.erro = j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Não consegui convidar.'; break; }
                    }
                    if (feitos.length) { this.ok = `Convite feito: ${feitos.join(', ')}.`; this.email = ''; window.dispatchEvent(new CustomEvent('wf-compartilhado')); }
                    await this.carregar();
                } catch { this.erro = 'Sem conexão. Tente de novo.'; } finally { this.enviando = false; }
            },
        };
    };
})();
