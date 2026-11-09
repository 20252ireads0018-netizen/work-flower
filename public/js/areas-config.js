/* Work Flower · tela "Áreas" (engrenagem › Áreas e botão "Personalizar áreas" do painel)
   Esconder/mostrar, renomear e reordenar as áreas do menu de cima e do painel, criar e excluir áreas próprias
   (quadros com abas, como o Diverso) e levar tudo de uma área para outra. Servidor: AreasContaController.
   A marcação fica em resources/views/partials/areas-config.blade.php. */
(function () {
    'use strict';
    const base = () => (document.querySelector('meta[name="wf-areas-url"]')?.content || '/areas-config').replace(/\/$/, '');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function pedir(url, metodo = 'GET', corpo = null) {
        const r = await fetch(url, {
            method: metodo, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-WF-Dono': '' },
            body: corpo ? JSON.stringify(corpo) : null,
        });
        let j = {};
        try { j = await r.json(); } catch { /* sem JSON */ }
        if (r.status === 419 || r.status === 401) { location.reload(); throw new Error('Sessão expirada.'); }
        if (!r.ok) throw new Error((j.errors && Object.values(j.errors).flat()[0]) || j.message || 'Não deu certo agora. Tente de novo.');
        return j;
    }

    window.wfAreasConfig = function () {
        return {
            aberto: false, carregando: false, salvando: false, erro: '', msg: '',
            areas: [], original: '', novaNome: '',
            tudo: { de: '', para: '', esconder: true },
            excluir: null, // { id, nome, destino }

            async abrir() {
                this.aberto = true; this.erro = ''; this.msg = ''; this.excluir = null;
                this.carregando = true;
                try { this.receber(await pedir(base())); } catch (e) { this.erro = e.message; } finally { this.carregando = false; }
            },
            fechar() { this.aberto = false; },
            receber(j) {
                this.areas = (j.areas || []).map(a => ({ ...a, nomeEdit: a.nome }));
                this.original = this.assinatura();
                if (!this.areas.some(a => a.slug === this.tudo.de)) this.tudo.de = this.areas.find(a => !a.extra && a.slug !== 'diverso')?.slug || '';
                this.acertarDestino();
            },
            assinatura() { return JSON.stringify(this.areas.map(a => [a.slug, a.oculta, a.nomeEdit])); },
            mudou() { return this.assinatura() !== this.original; },
            mover(i, passo) {
                const j = i + passo;
                if (j < 0 || j >= this.areas.length) return;
                const l = this.areas; [l[i], l[j]] = [l[j], l[i]];
                this.areas = [...l];
            },
            alternar(a) {
                if (!a.oculta && this.areas.filter(x => !x.oculta).length <= 1) { this.erro = 'Deixe pelo menos uma área à mostra.'; return; }
                this.erro = '';
                a.oculta = !a.oculta;
            },
            async salvar() {
                this.erro = ''; this.salvando = true;
                try {
                    const nomes = {};
                    this.areas.forEach(a => { nomes[a.slug] = (a.nomeEdit || '').trim(); });
                    await pedir(base(), 'PUT', { ordem: this.areas.map(a => a.slug), ocultas: this.areas.filter(a => a.oculta).map(a => a.slug), nomes });
                    location.reload(); // o menu de cima e o painel são montados no servidor
                } catch (e) { this.erro = e.message; this.salvando = false; }
            },
            async criar() {
                const nome = this.novaNome.trim();
                if (!nome) { this.erro = 'Dê um nome à área.'; return; }
                this.erro = ''; this.salvando = true;
                try {
                    const j = await pedir(base() + '/extras', 'POST', { nome });
                    location.href = j.criada.url;
                } catch (e) { this.erro = e.message; this.salvando = false; }
            },
            pedirExcluir(a) { this.excluir = { id: a.slug, nome: a.nome, destino: 'diverso' }; this.erro = ''; },
            async confirmarExcluir() {
                const x = this.excluir;
                if (!x) return;
                if (x.destino === 'apagar' && !confirm(`Apagar a área "${x.nome}" com todas as abas e cartões dela? Não dá para desfazer.`)) return;
                this.salvando = true;
                try {
                    await pedir(`${base()}/extras/${encodeURIComponent(x.id)}`, 'DELETE', { destino: x.destino });
                    // estava na página da área excluída: volta ao painel
                    if (location.pathname.endsWith('/areas/extra/' + x.id)) location.href = document.querySelector('a[aria-label^="Work Flower"]')?.href || '/';
                    else location.reload();
                } catch (e) { this.erro = e.message; this.salvando = false; }
            },
            /* ---------- levar tudo de uma área para outra ---------- */
            destinosTudo() { return this.areas.filter(a => (a.slug === 'diverso' || a.extra) && a.slug !== this.tudo.de); },
            acertarDestino() {
                const d = this.destinosTudo();
                if (!d.some(a => a.slug === this.tudo.para)) this.tudo.para = d[0]?.slug || '';
            },
            async moverTudo() {
                const de = this.areas.find(a => a.slug === this.tudo.de), para = this.areas.find(a => a.slug === this.tudo.para);
                if (!de || !para) return;
                if (!confirm(`Levar os cartões de todas as abas de "${de.nome}" para "${para.nome}"? Cada aba vira uma aba nova lá. Os cartões fixos de ${de.nome} ficam guardados onde estão.`)) return;
                this.erro = ''; this.salvando = true;
                try {
                    const j = await pedir(base() + '/mover-tudo', 'POST', { de: de.slug, para: para.slug, esconder: !!this.tudo.esconder });
                    const ficaram = (j.ficaram || []).length;
                    alert(`${j.movidos === 1 ? '1 cartão movido' : j.movidos + ' cartões movidos'} para ${para.nome}.`
                        + (ficaram ? `\n${ficaram === 1 ? '1 cartão ficou' : ficaram + ' cartões ficaram'} em ${de.nome} (tipo que ${para.nome} não mostra): ${(j.ficaram || []).map(f => f.titulo).join(', ')}.` : ''));
                    location.href = j.url || location.href;
                } catch (e) { this.erro = e.message; this.salvando = false; }
            },
        };
    };
})();
