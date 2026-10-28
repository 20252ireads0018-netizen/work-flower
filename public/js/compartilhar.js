/* Work Flower · workflows compartilhados (barra "Compartilhar" das áreas; servidor em CompartilharController).
   O dono convida pessoas pelo e-mail; o limite de pessoas vem do plano dele (Gratuito 1, Básico 5...).
   Quem foi convidado abre o workflow pelo seletor "Compartilhados comigo" (endereço com ?de=<dono>). */
(function () {
    'use strict';
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const cabecalhos = () => ({
        'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': meta('csrf-token'),
    });
    // pedidos da barra são sempre sobre a conta de quem está logado (não sobre o workflow aberto)
    const semDono = h => Object.assign(h, { 'X-WF-Dono': '' });

    window.wfCompartilhar = function (area) {
        return {
            area,
            url: meta('wf-compartilhar-url'),
            donoNome: meta('wf-dono-nome'),
            dono: meta('wf-dono'),
            aberto: false, carregando: false, enviando: false,
            email: '', erro: '', ok: '',
            membros: [], comigo: [], pessoas: 1, limite: 1, plano: '', semAcesso: 0,
            init() {
                // a lista "compartilhados comigo" aparece mesmo com o painel fechado
                this.carregar();
            },
            get vendoOutro() { return !!this.dono; },
            get cheio() { return this.limite !== null && this.pessoas >= this.limite; },
            get comigoAtivos() { return this.comigo.filter(c => c.ativo); },
            textoLimite() {
                if (this.limite === null) return `Plano ${this.plano}: ${this.pessoas} ${this.pessoas === 1 ? 'pessoa' : 'pessoas'} na conta, sem limite.`;
                return `Plano ${this.plano}: ${this.pessoas} de ${this.limite} ${this.limite === 1 ? 'pessoa' : 'pessoas'} na conta (contando você).`;
            },
            async carregar() {
                if (!this.url) return;
                this.carregando = true;
                try {
                    const r = await fetch(this.url, { credentials: 'same-origin', headers: semDono(cabecalhos()) });
                    if (!r.ok) return;
                    const j = await r.json();
                    Object.assign(this, { membros: j.membros, comigo: j.comigo, pessoas: j.pessoas, limite: j.limite, plano: j.plano, semAcesso: j.semAcesso });
                } catch { /* sem rede */ } finally { this.carregando = false; }
            },
            alternar() { this.aberto = !this.aberto; this.erro = ''; this.ok = ''; if (this.aberto) this.carregar(); },
            async adicionar() {
                this.erro = ''; this.ok = '';
                const email = this.email.trim();
                if (!email) { this.erro = 'Digite o e-mail da pessoa.'; return; }
                this.enviando = true;
                try {
                    const r = await fetch(this.url, { method: 'POST', credentials: 'same-origin', headers: semDono(cabecalhos()), body: JSON.stringify({ email }) });
                    const j = await r.json().catch(() => ({}));
                    if (r.ok) {
                        this.ok = j.message || 'Pessoa adicionada.'; this.email = '';
                        window.dispatchEvent(new CustomEvent('wf-compartilhado')); // o quadro passa a conferir mudanças
                        await this.carregar();
                    }
                    else if (j.plano_limite) { this.erro = j.message; }
                    else this.erro = j.message || (j.errors && Object.values(j.errors)[0][0]) || 'Não consegui adicionar. Tente de novo.';
                } catch { this.erro = 'Sem conexão. Tente de novo.'; } finally { this.enviando = false; }
            },
            async remover(m) {
                if (!confirm(`Tirar o acesso de ${m.nome || m.email} a este workflow?`)) return;
                const r = await fetch(`${this.url}/${m.id}`, { method: 'DELETE', credentials: 'same-origin', headers: semDono(cabecalhos()) });
                if (r.ok) { this.ok = 'Acesso removido.'; this.carregar(); } else this.erro = 'Não consegui remover. Tente de novo.';
            },
            async sairDoWorkflow() {
                if (!confirm(`Sair do workflow de ${this.donoNome}? Você deixa de ver e editar esta área dele(a).`)) return;
                const r = await fetch(`${this.url}/sair/${this.dono}`, { method: 'DELETE', credentials: 'same-origin', headers: semDono(cabecalhos()) });
                if (r.ok) location.href = location.pathname;
            },
            abrirComigo(c) { if (c && c.url) location.href = c.url; },
        };
    };
})();
