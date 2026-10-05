/* Work Flower · biblioteca de blocos (vale para todas as páginas)
   - WFBiblioteca: guarda, lista, renomeia e remove blocos salvos (rota /biblioteca).
   - bibliotecaPainel(): aba "Biblioteca" do painel de configurações.
   Quem guarda um bloco é o quadro.js (guardarBloco); quem cola também (colarBloco, ouvindo 'wf-colar'). */
(function () {
    'use strict';

    const MAX_ITENS = 60;
    const estado = { itens: [], carregado: false, promessa: null };

    const url = () => document.querySelector('meta[name="biblioteca-url"]')?.content || '';
    const cabecalhos = () => ({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
    });
    const avisar = () => window.dispatchEvent(new CustomEvent('wf-biblioteca', { detail: estado.itens.slice() }));

    async function carregar(forcar = false) {
        if (estado.carregado && !forcar) return estado.itens;
        if (!url()) throw new Error('rota');
        if (!estado.promessa) {
            estado.promessa = fetch(url(), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => {
                    if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
                    if (!r.ok) throw new Error(String(r.status));
                    return r.json();
                })
                .then(j => { estado.itens = Array.isArray(j.itens) ? j.itens : []; estado.carregado = true; return estado.itens; })
                .finally(() => { estado.promessa = null; });
        }
        return estado.promessa;
    }

    /** Lê o motivo de uma recusa 422 do Laravel e joga no console (ajuda a achar a regra que barrou). */
    async function motivo422(r) {
        try {
            const j = await r.json();
            const det = Object.entries(j.errors || {}).map(([k, v]) => `${k}: ${[].concat(v)[0]}`).join(' | ');
            console.error('[Biblioteca] 422 —', j.message || '', det);
            return det;
        } catch {
            console.error('[Biblioteca] 422 (sem detalhes)');
            return '';
        }
    }

    async function gravar() {
        const r = await fetch(url(), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: cabecalhos(),
            body: JSON.stringify({ itens: estado.itens }),
        });
        if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
        if (r.status === 413) throw new Error('cheia');
        if (r.status === 422) { await motivo422(r); throw new Error('422'); }
        if (!r.ok) throw new Error(String(r.status));
    }

    /** Aplica uma mudança na lista e grava; se o servidor recusar, desfaz. */
    async function mudar(fn) {
        await carregar();
        const antes = estado.itens.slice();
        fn(estado.itens);
        try { await gravar(); } catch (e) { estado.itens = antes; throw e; }
        avisar();
    }

    /** Garante que o item é JSON puro e tem os campos que o servidor espera (tipo, dados como objeto, números inteiros). */
    function limpar(item) {
        const c = JSON.parse(JSON.stringify(item)); // tira undefined, funções e proxies do Alpine
        c.id = String(c.id);
        c.nome = String(c.nome || '').trim().slice(0, 120) || 'Sem título';
        c.titulo = String(c.titulo || '').slice(0, 200);
        c.tipo = String(c.tipo || '');
        if (!c.dados || typeof c.dados !== 'object' || Array.isArray(c.dados)) c.dados = {};
        c.w = Math.max(1, Math.round(Number(c.w) || 360));
        c.h = Math.max(1, Math.round(Number(c.h) || 260));
        c.cor = /^#[0-9a-fA-F]{6}$/.test(c.cor || '') ? c.cor : null;
        c.imagem = typeof c.imagem === 'string' && c.imagem ? c.imagem : null;
        return c;
    }

    window.WFBiblioteca = {
        carregar,
        adicionar: item => mudar(lista => {
            if (lista.length >= MAX_ITENS) throw new Error('cheia');
            lista.unshift(limpar(item));
        }),
        remover: id => mudar(lista => { const i = lista.findIndex(x => x.id === id); if (i >= 0) lista.splice(i, 1); }),
        renomear: (id, nome) => mudar(lista => { const x = lista.find(i => i.id === id); if (x) x.nome = String(nome).trim().slice(0, 120) || x.nome; }),
    };

    /* ================= aba "Biblioteca" do painel ================= */
    const TIPOS = {
        texto: 'Texto', tabela: 'Tabela', lista: 'Lista', imagem: 'Imagem', documento: 'Documento', mapa: 'Mapa mental',
        vivo: 'Cartão ativo', compras: 'Lista de compras', campos: 'Campos', escrita: 'Escrita', social: 'Redes sociais',
        loja: 'Loja', codigo: 'Código', tabletop: 'Tabletop',
    };
    const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    window.bibliotecaPainel = function () {
        return {
            itens: [], carregando: false, erro: '', busca: '', tipo: '', pode: false, tipos: TIPOS,

            init() {
                window.addEventListener('wf-biblioteca', e => { this.itens = e.detail; });
                window.addEventListener('abrir-biblioteca', () => this.abrir());
            },

            async abrir() {
                this.pode = !!window.WFQuadro?.ativo; // só dá para colar em página com quadro de blocos
                this.erro = '';
                this.carregando = true;
                try { this.itens = (await window.WFBiblioteca.carregar(true)).slice(); }
                catch { this.erro = 'Não consegui carregar a biblioteca.'; }
                finally { this.carregando = false; }
            },

            get filtrados() {
                const b = norm(this.busca);
                return this.itens.filter(i => (!this.tipo || i.tipo === this.tipo) && (!b || norm(i.nome).includes(b) || norm(i.titulo).includes(b)));
            },

            rotuloTipo(t) { return TIPOS[t] || t; },

            /** A página aberta sabe desenhar esse tipo? (ex.: documento só existe na Mente) */
            colavel(i) {
                const tipos = window.WFQuadro?.tipos;
                return !tipos || tipos.includes(i.tipo);
            },

            resumo(i) {
                const d = i.dados || {};
                if (i.tipo === 'texto') return (d.texto || '').trim().slice(0, 90) || 'Texto vazio';
                if (i.tipo === 'tabela') return `${(d.cab || []).length} colunas · ${(d.linhas || []).length} linhas`;
                if (i.tipo === 'lista') return `${(d.itens || []).length} itens`;
                if (i.tipo === 'mapa') return `${(d.nos || []).length} nós` + ((d.tracos || []).length ? ` · ${d.tracos.length} traços` : '');
                if (i.tipo === 'compras') return `${(d.itens || []).length} itens`;
                if (i.tipo === 'campos') return (d.campos || []).map(c => c.nome).join(', ').slice(0, 90);
                if (i.tipo === 'escrita') return (d.texto || '').trim().slice(0, 90) || 'Texto vazio';
                if (i.tipo === 'social') return `${(d.posts || []).length} posts`;
                if (i.tipo === 'loja') return `${(d.produtos || []).length} produtos`;
                if (i.tipo === 'codigo') return `${(d.repos || []).length} repositórios`;
                if (i.tipo === 'tabletop') {
                    const partes = [`${d.cols || 16}×${d.rows || 12} casas`, `${(d.pecas || []).length} peças`];
                    if ((d.desenho || []).length) partes.push(`${d.desenho.length} desenhos`);
                    if (d.mapaImg) partes.push('com mapa de fundo');
                    return partes.join(' · ');
                }
                if (i.tipo === 'vivo') return `Abre o original em ${(d.pagina || '').replace('/areas/', '')}`;
                if (i.tipo === 'documento') return `${(d.secoes || []).length} seções`;
                return d.legenda || '';
            },

            colar(i) {
                if (!this.pode || !this.colavel(i)) return;
                // cópia: quem cola gera ids novos, a biblioteca continua intacta
                window.dispatchEvent(new CustomEvent('wf-colar', { detail: JSON.parse(JSON.stringify(i)) }));
            },

            async renomear(i, nome) {
                this.erro = '';
                try { await window.WFBiblioteca.renomear(i.id, nome); }
                catch { this.erro = 'Não consegui renomear.'; }
            },

            async remover(i) {
                if (!confirm(`Excluir "${i.nome}" da biblioteca?`)) return;
                this.erro = '';
                try { await window.WFBiblioteca.remover(i.id); }
                catch { this.erro = 'Não consegui excluir.'; }
            },
        };
    };
})();