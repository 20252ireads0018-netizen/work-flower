/* Work Flower · histórico de blocos excluídos (lixeira)
   Ligações com o resto do sistema:
   - quadro.js / diverso.js disparam "wf-bloco-removido" ao excluir um bloco → aqui guardamos uma cópia completa.
   - O painel "Histórico" do layout usa lixeiraPainel() (Alpine) para listar, restaurar e apagar de vez.
   - Restaurar dispara "wf-colar" com { ...item, restaurar: true }; o quadro recria o bloco na aba e
     posição de origem e marca detail.ok = true/false.
   Onde fica guardado (separado por página, location.pathname):
   - localStorage: os dados leves do bloco (texto, lista, tabela, mapa…).
   - IndexedDB: as imagens (imagem do bloco, peças e mapa do tabletop). O localStorage tem ~5 MB e estoura
     com poucas fotos; o IndexedDB aguenta muito mais. Se o navegador não tiver IndexedDB, as imagens voltam
     para o localStorage, como antes. */
(function () {
    'use strict';
    if (window.lixeiraPainel) return;

    const PREFIXO = 'wf:lixeira:';
    const MAX = 30; // blocos guardados por página

    const chave = () => PREFIXO + location.pathname;
    const kPesado = (id, chv = chave()) => chv + '#' + id;
    const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);

    /* ---------- IndexedDB: só para o que é pesado (imagens) ---------- */
    const DB = 'wf-lixeira', ST = 'pesados';
    let dbP = null;
    function abrirDb() {
        if (dbP) return dbP;
        dbP = new Promise((ok, no) => {
            if (!window.indexedDB) { no(new Error('sem indexeddb')); return; }
            let r;
            try { r = indexedDB.open(DB, 1); } catch (e) { no(e); return; }
            r.onupgradeneeded = () => { r.result.createObjectStore(ST); };
            r.onsuccess = () => ok(r.result);
            r.onerror = () => no(r.error || new Error('idb'));
            r.onblocked = () => no(new Error('idb bloqueado'));
        });
        dbP.catch(() => { dbP = null; });
        return dbP;
    }
    function idb(modo, fn) {
        return abrirDb().then(db => new Promise((ok, no) => {
            const t = db.transaction(ST, modo);
            const req = fn(t.objectStore(ST));
            t.oncomplete = () => ok(req ? req.result : undefined);
            t.onerror = () => no(t.error);
            t.onabort = () => no(t.error || new Error('abortado'));
        }));
    }
    const idbPut = (k, v) => idb('readwrite', s => { s.put(v, k); });
    const idbGet = k => idb('readonly', s => s.get(k));
    const idbDel = ks => { ks = [].concat(ks).filter(Boolean); return ks.length ? idb('readwrite', s => { ks.forEach(k => s.delete(k)); }).catch(() => {}) : Promise.resolve(); };

    /* imagens já lidas nesta página (evita ler o IndexedDB de novo a cada atualização do painel) */
    const cacheImg = new Map();

    /* ---------- localStorage: dados leves ---------- */
    function ler() {
        try {
            const j = JSON.parse(localStorage.getItem(chave()) || '[]');
            return Array.isArray(j) ? j.filter(x => x && x.id && x.tipo) : [];
        } catch (e) { return []; }
    }

    /** Chaves do histórico de OUTRAS páginas. O localStorage (~5 MB) é um só para o site inteiro: o histórico
        antigo de outras páginas pode ter ocupado todo o espaço e impedir que este grave. */
    const chavesOutras = () => {
        const r = [];
        try { for (let k = 0; k < localStorage.length; k++) { const n = localStorage.key(k); if (n && n.startsWith(PREFIXO) && n !== chave()) r.push(n); } } catch (e) { /* ok */ }
        return r;
    };

    /** Tira o item mais antigo do histórico das outras páginas. Devolve false se não há mais nada a tirar. */
    function liberarEspaco() {
        let alvo = null;
        chavesOutras().forEach(n => {
            let l; try { l = JSON.parse(localStorage.getItem(n) || '[]'); } catch (e) { l = null; }
            if (!Array.isArray(l)) { alvo = alvo || { n, i: -1, t: -1 }; return; }
            l.forEach((x, i) => { const t = Number(x && x.removido) || 0; if (!alvo || t < alvo.t) alvo = { n, i, t, x }; });
            if (!l.length && !alvo) alvo = { n, i: -1, t: -1 };
        });
        if (!alvo) return false;
        try {
            if (alvo.i < 0) { localStorage.removeItem(alvo.n); return true; }
            const l = JSON.parse(localStorage.getItem(alvo.n));
            l.splice(alvo.i, 1);
            if (l.length) localStorage.setItem(alvo.n, JSON.stringify(l)); else localStorage.removeItem(alvo.n);
            if (alvo.x && alvo.x.pesado) idbDel(kPesado(alvo.x.id, alvo.n));
            return true;
        } catch (e) { try { localStorage.removeItem(alvo.n); return true; } catch (e2) { return false; } }
    }

    /** Grava a lista. Sem espaço: 1) tira imagens que ficaram dentro dela, 2) tira itens antigos de outras páginas, 3) tira os itens mais antigos desta. */
    function gravar(lista) {
        lista = lista.slice(0, MAX);
        while (true) {
            try {
                if (lista.length) localStorage.setItem(chave(), JSON.stringify(lista));
                else localStorage.removeItem(chave());
                return lista;
            } catch (e) {
                let i = -1;
                for (let k = lista.length - 1; k >= 0; k--) { if (lista[k].imagem) { i = k; break; } }
                if (i >= 0) { lista[i] = { ...lista[i], imagem: null, temImg: false, semImagem: true }; continue; }
                if (liberarEspaco()) continue;
                if (lista.length > 1) { lista.pop(); continue; }
                console.error('[Histórico] sem espaço no navegador: o bloco excluído não pôde ser guardado.');
                return lista; // não coube de jeito nenhum
            }
        }
    }

    /** Histórico antigo (versão que guardava imagens dentro do localStorage): passa as imagens para o IndexedDB e libera o espaço. */
    async function migrarInline() {
        let chaves = [];
        try { for (let k = 0; k < localStorage.length; k++) { const n = localStorage.key(k); if (n && n.startsWith(PREFIXO)) chaves.push(n); } } catch (e) { return; }
        for (const n of chaves) {
            let l; try { l = JSON.parse(localStorage.getItem(n) || '[]'); } catch (e) { continue; }
            if (!Array.isArray(l)) continue;
            let mudou = false;
            for (const x of l) {
                if (!x || !x.id || x.pesado) continue;
                const d = x.dados && typeof x.dados === 'object' ? x.dados : {};
                const pesado = {};
                if (x.imagem) pesado.imagem = x.imagem;
                if (d.imgs) pesado.imgs = d.imgs;
                if (d.mapaImg) pesado.mapaImg = d.mapaImg;
                if (!Object.keys(pesado).length) continue;
                try {
                    await idbPut(kPesado(x.id, n), pesado);
                    x.temImg = !!pesado.imagem; x.pesado = true;
                    delete x.imagem; delete d.imgs; delete d.mapaImg; mudou = true;
                } catch (e) { return; } // sem IndexedDB: deixa como está
            }
            if (mudou) { try { localStorage.setItem(n, JSON.stringify(l)); } catch (e) { /* ok */ } }
        }
        avisarMudanca();
    }

    const avisarMudanca = () => window.dispatchEvent(new CustomEvent('wf-lixeira-mudou'));

    /** Apaga do IndexedDB o que pertencia a itens que saíram da lista. */
    function limparPesados(antes, depois) {
        const ficam = new Set(depois.map(x => x.id));
        const ids = antes.filter(x => x.pesado && !ficam.has(x.id)).map(x => kPesado(x.id));
        antes.filter(x => !ficam.has(x.id)).forEach(x => cacheImg.delete(x.id));
        return idbDel(ids);
    }

    /* Captura global: guarda o bloco mesmo que o painel ainda não tenha sido aberto */
    window.addEventListener('wf-bloco-removido', async e => {
        const d = e.detail;
        if (!d || typeof d !== 'object' || !d.tipo) return;
        const item = { ...d, id: d.id || uid(), removido: Date.now() };
        item.dados = item.dados && typeof item.dados === 'object' ? { ...item.dados } : {};

        // separa o que é pesado (imagens) do resto
        const pesado = {};
        if (item.imagem) pesado.imagem = item.imagem;
        if (item.dados.imgs) pesado.imgs = item.dados.imgs;
        if (item.dados.mapaImg) pesado.mapaImg = item.dados.mapaImg;
        delete item.imagem; delete item.dados.imgs; delete item.dados.mapaImg;
        item.temImg = !!pesado.imagem;

        if (Object.keys(pesado).length) {
            try {
                await idbPut(kPesado(item.id), pesado);
                item.pesado = true;
                if (pesado.imagem) cacheImg.set(item.id, pesado.imagem);
            } catch (err) {
                // sem IndexedDB: guarda tudo no localStorage, como antes
                if (pesado.imagem) item.imagem = pesado.imagem;
                if (pesado.imgs) item.dados.imgs = pesado.imgs;
                if (pesado.mapaImg) item.dados.mapaImg = pesado.mapaImg;
            }
        }

        const antes = ler().filter(x => x.id !== item.id);
        antes.unshift(item);
        const depois = gravar(antes);
        await limparPesados(antes, depois);
        avisarMudanca();
    });

    migrarInline().catch(() => {});

    const ROTULOS = {
        texto: 'Texto', tabela: 'Tabela', lista: 'Lista de itens', imagem: 'Imagem', documento: 'Documento',
        mapa: 'Mapa mental', vivo: 'Cartão ativo', compras: 'Compras', campos: 'Campos', escrita: 'Escrita',
        social: 'Social', loja: 'Loja', codigo: 'Código', tabletop: 'Tabletop',
    };

    const plural = (n, um, varios) => `${n} ${n === 1 ? um : varios}`;

    window.lixeiraPainel = function () {
        return {
            itens: [],
            tick: 0,
            ocupado: false,

            init() {
                this.carregar();
                const sync = () => { this.carregar(); this.tick++; };
                window.addEventListener('wf-lixeira-mudou', sync);
                window.addEventListener('abrir-lixeira', sync);
                window.addEventListener('storage', e => { if (e.key === chave()) sync(); });
            },

            /** Lê a lista e, em seguida, traz as imagens do IndexedDB para a prévia. */
            carregar() {
                const lista = ler();
                lista.forEach(x => { if (cacheImg.has(x.id)) x.imagem = cacheImg.get(x.id); });
                this.itens = lista;
                lista.filter(x => x.pesado && x.temImg && !cacheImg.has(x.id)).forEach(async x => {
                    try {
                        const p = await idbGet(kPesado(x.id));
                        if (!p || !p.imagem) return;
                        cacheImg.set(x.id, p.imagem);
                        const alvo = this.itens.find(y => y.id === x.id);
                        if (alvo) alvo.imagem = p.imagem;
                    } catch (err) { /* sem prévia, mas ainda dá para restaurar */ }
                });
            },

            /** A página tem quadro de blocos? (o quadro.js marca WFQuadro.ativo ao iniciar) */
            get pode() {
                this.tick;
                return !!(window.WFQuadro && window.WFQuadro.ativo);
            },

            rotuloTipo(t) { return ROTULOS[t] || 'Bloco'; },

            /** Pode ser restaurado nesta página? */
            colavel(i) {
                const Q = window.WFQuadro;
                if (!Q || !Q.ativo) return false;
                if (Array.isArray(Q.tipos) && !Q.tipos.includes(i.tipo)) return false;
                if (i.tipo === 'imagem' && !(i.temImg || i.imagem)) return false;
                if (typeof Q.dadosPerdidos === 'function' && Q.dadosPerdidos(i)) return false;
                return true;
            },

            quando(i) {
                const t = Number(i.removido) || 0;
                if (!t) return '';
                const min = Math.floor((Date.now() - t) / 60000);
                if (min < 1) return 'agora';
                if (min < 60) return `há ${min} min`;
                const h = Math.floor(min / 60);
                if (h < 24) return `há ${h} h`;
                const d = new Date(t);
                const dia = Math.floor(h / 24);
                if (dia === 1) return 'ontem';
                const p = n => String(n).padStart(2, '0');
                return `${p(d.getDate())}/${p(d.getMonth() + 1)}`;
            },

            resumo(i) {
                const d = i.dados || {};
                switch (i.tipo) {
                    case 'texto': {
                        const t = String(d.texto || '').replace(/\s+/g, ' ').trim();
                        return t ? (t.length > 40 ? t.slice(0, 40) + '…' : t) : 'vazio';
                    }
                    case 'tabela':
                        return `${plural((d.linhas || []).length, 'linha', 'linhas')} × ${plural((d.cab || []).length, 'coluna', 'colunas')}`;
                    case 'lista': {
                        const n = (d.itens || []).length;
                        const f = (d.itens || []).filter(x => x && x.f).length;
                        return `${plural(n, 'item', 'itens')}${n ? ` (${f} feito${f === 1 ? '' : 's'})` : ''}`;
                    }
                    case 'mapa': return plural((d.nos || []).length, 'ideia', 'ideias');
                    case 'documento': return plural((d.secoes || []).length, 'seção', 'seções');
                    case 'imagem': return (i.temImg || i.imagem) ? (d.legenda || 'com imagem') : 'imagem não guardada (pouco espaço)';
                    case 'vivo': return 'continua usando os dados da página de origem';
                    default: return `${i.w || '?'}×${i.h || '?'} px`;
                }
            },

            /** Recria o bloco no quadro (aba e posição de origem). Só sai do histórico se o quadro aceitar. */
            async restaurar(i) {
                if (this.ocupado || !this.pode || !this.colavel(i)) return;
                this.ocupado = true;
                try {
                    // parte da cópia guardada (sem a prévia em memória) e devolve as imagens
                    const base = ler().find(x => x.id === i.id) || i;
                    const detalhe = { ...base, restaurar: true, dados: { ...(base.dados || {}) } };
                    if (base.pesado) {
                        let p = null;
                        try { p = await idbGet(kPesado(base.id)); } catch (err) { /* sem imagens */ }
                        if (p) {
                            if (p.imagem) detalhe.imagem = p.imagem;
                            if (p.imgs) detalhe.dados.imgs = p.imgs;
                            if (p.mapaImg) detalhe.dados.mapaImg = p.mapaImg;
                        }
                        if (base.tipo === 'imagem' && !detalhe.imagem) {
                            alert('Não encontrei a imagem deste bloco no navegador. Não dá para restaurá-lo.');
                            return;
                        }
                    }
                    window.dispatchEvent(new CustomEvent('wf-colar', { detail: detalhe }));
                    if (detalhe.ok) {
                        const antes = ler();
                        const depois = gravar(antes.filter(x => x.id !== i.id));
                        await limparPesados(antes, depois);
                        this.carregar();
                        avisarMudanca();
                    }
                } finally { this.ocupado = false; }
            },

            async excluir(i) {
                if (!confirm(`Apagar "${i.titulo || i.nome || 'bloco'}" de vez? Não dá para desfazer.`)) return;
                const antes = ler();
                const depois = gravar(antes.filter(x => x.id !== i.id));
                await limparPesados(antes, depois);
                this.carregar();
                avisarMudanca();
            },

            async esvaziar() {
                if (!this.itens.length) return;
                if (!confirm('Esvaziar o histórico desta página? Os blocos excluídos não poderão mais ser restaurados.')) return;
                const antes = ler();
                gravar([]);
                await limparPesados(antes, []);
                this.itens = [];
                avisarMudanca();
            },
        };
    };

    window.WFLixeira = {
        listar: ler,
        quantidade: () => ler().length,
    };
})();
