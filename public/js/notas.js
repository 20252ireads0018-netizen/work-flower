/* Work Flower · Notion e Obsidian nos quadros (Corpo, Mente, Carteira/Negócios e Diverso)
   Obsidian: um cofre (.zip ou pasta com .md, [[links]], frontmatter e anexos) vira uma pasta de cartões de texto, com
   subpastas pelas pastas do cofre ou pelas tags, imagens como cartões de imagem e um mapa mental dos links.
   O caminho inverso baixa a aba (ou uma pasta) como um cofre .zip, com o frontmatter de volta e listas, tabelas,
   mapas e documentos convertidos para Markdown.
   Notion: a conexão fica em Negócios › Integrações (NotionController); aqui se escolhem as páginas e bancos de dados
   que viram cartões, e "Sincronizar com o Notion" atualiza os cartões que vieram de lá.
   Tudo pelo clique direito no quadro (submenu "Notion e Obsidian"), arrastando um .zip de cofre ou uma pasta, ou
   pelo clique direito numa pasta (exportar só ela). */
(function () {
    'use strict';
    if (window.WFNotas) return;

    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const URL_NOTION = () => meta('wf-notion-url') || ((meta('wf-base-url') || '') + '/integracoes/notion');
    const MAX_NOTAS = 400;
    const MAX_IMGS = 40;
    const MAX_IMG = 3 * 1024 * 1024;
    const MAX_NOS = 150;
    const EXT_IMG = { png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', svg: 'image/svg+xml' };
    const IMG_EXT = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/gif': 'gif', 'image/webp': 'webp', 'image/svg+xml': 'svg' };

    const comp = () => {
        const q = document.querySelector('.wf-quadro');
        try { return q && window.Alpine ? window.Alpine.$data(q) : null; } catch (_) { return null; }
    };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const uid = () => (window.WFQuadro && window.WFQuadro.util.uid) ? window.WFQuadro.util.uid() : Math.random().toString(36).slice(2, 10);
    const base = c => String(c || '').split('/').pop();
    const semExt = n => String(n || '').replace(/\.[^./]+$/, '');
    const extDe = n => (String(n || '').match(/\.([^./]+)$/) || [, ''])[1].toLowerCase();
    const chave = n => semExt(base(n)).trim().toLowerCase();
    const titulo80 = s => String(s || '').replace(/[\u0000-\u001f]+/g, ' ').trim().slice(0, 80);

    function avisar(c, m) {
        if (!c) { alert(m); return; }
        if (typeof c.aviso === 'function') { c.aviso(m); return; }
        c.msg = m;
        clearTimeout(avisar._t);
        avisar._t = setTimeout(() => { if (c.msg === m) c.msg = ''; }, 4000);
    }

    /* ---------------- janela simples (sem depender da página) ---------------- */
    function janela(tituloTxt, html) {
        const fundo = document.createElement('div');
        fundo.className = 'wf-notas-fundo';
        fundo.innerHTML = `<div class="wf-notas-caixa card" role="dialog" aria-modal="true" aria-label="${esc(tituloTxt)}">
            <div class="wf-notas-topo"><h3 class="titulo text-lg">${esc(tituloTxt)}</h3><button type="button" class="btn-sec !py-0.5" data-fechar aria-label="Fechar">✕</button></div>
            <div class="wf-notas-corpo">${html}</div></div>`;
        const fechar = () => { fundo.remove(); document.removeEventListener('keydown', tecla); };
        const tecla = e => { if (e.key === 'Escape') fechar(); };
        fundo.addEventListener('click', e => { if (e.target === fundo || e.target.closest('[data-fechar]')) fechar(); });
        document.addEventListener('keydown', tecla);
        document.body.appendChild(fundo);
        return { el: fundo, $: s => fundo.querySelector(s), $$: s => Array.from(fundo.querySelectorAll(s)), fechar };
    }
    (function estilo() {
        const s = document.createElement('style');
        s.textContent = `
            .wf-notas-fundo { position: fixed; inset: 0; z-index: 9000; background: rgb(0 0 0 / .55); display: grid; place-items: center; padding: 16px; }
            .wf-notas-caixa { width: min(560px, 100%); max-height: min(86vh, 760px); display: flex; flex-direction: column; gap: .75rem; padding: 1rem; overflow: hidden; }
            .wf-notas-topo { display: flex; align-items: center; gap: .5rem; } .wf-notas-topo h3 { flex: 1; min-width: 0; }
            .wf-notas-corpo { display: flex; flex-direction: column; gap: .65rem; min-height: 0; overflow: auto; font-size: .9rem; }
            .wf-notas-lista { list-style: none; margin: 0; padding: 0; display: grid; gap: .3rem; overflow: auto; max-height: 46vh; }
            .wf-notas-lista label { display: flex; align-items: center; gap: .5rem; padding: .35rem .5rem; border: 1px solid var(--linha, #8884); border-radius: .5rem; cursor: pointer; min-width: 0; }
            .wf-notas-lista .t { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .wf-notas-lista .s { font-size: .72rem; opacity: .7; flex: none; }
            .wf-notas-op { display: flex; align-items: flex-start; gap: .45rem; }
            .wf-notas-acoes { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
            .wf-notas-erro { color: #f87171; overflow-wrap: anywhere; }`;
        document.head.appendChild(s);
    })();

    /* ---------------- criar cartões ---------------- */
    const temPastas = c => typeof c.novaPasta === 'function' || (Array.isArray(c.tiposBloco) && c.tiposBloco.some(t => t.id === 'pasta'));
    const aceita = (c, tipo) => !Array.isArray(c.tiposBloco) || c.tiposBloco.some(t => t.id === tipo) || ['texto', 'tabela', 'lista', 'imagem', 'mapa'].includes(tipo);

    /** Cria um cartão na aba `sec`, dentro da pasta `pasta` (ou fora de todas), na posição e no tamanho dados. */
    function novo(c, sec, tipo, tit, dados, pasta, L) {
        let id = null;
        if (typeof c.criarBloco === 'function') id = c.criarBloco(sec, tipo, { x: L.x, y: L.y }, { titulo: tit, dados });
        if (!id && typeof c.novoBloco === 'function') {
            const antes = new Set(c.estado.blocos.map(b => b.id));
            c.novoBloco(sec, tipo);
            const nb = c.estado.blocos.find(b => !antes.has(b.id));
            if (nb) { nb.titulo = tit; nb.dados = Object.assign({}, nb.dados, dados); id = nb.id; }
        }
        const b = id && c.estado.blocos.find(x => x.id === id);
        if (!b) return null;
        if (pasta) b.pasta = pasta; else delete b.pasta;
        const lay = c.estado.layout[b.secao || sec] && c.estado.layout[b.secao || sec][id];
        if (lay) Object.assign(lay, { x: L.x, y: L.y }, L.w ? { w: L.w } : {}, L.h ? { h: L.h } : {});
        return id;
    }
    function novaPasta(c, sec, tit, pai, L) {
        const id = 'b' + uid();
        const b = { id, secao: sec, tipo: 'pasta', titulo: titulo80(tit) || 'Pasta', links: [], dados: {} };
        if (pai) b.pasta = pai;
        c.estado.blocos.push(b);
        (c.estado.layout[sec] ??= {})[id] = { x: L.x, y: L.y, w: 320, h: 240, z: ++c.zTop };
        return id;
    }
    const pastaAberta = (c, sec) => (typeof c.pastaAberta === 'function' ? c.pastaAberta(sec) : null) || null;
    /** Próxima posição livre, em grade, dentro de cada pasta (as pastas mostram só os próprios cartões). */
    function grade(c, sec, pasta) {
        const larg = Math.max(400, Math.min(c.col || c.larg || 1088, 1400));
        const cols = Math.max(1, Math.floor(larg / 376));
        let n = 0;
        let y0 = 0;
        if (!pasta && typeof c.alturaConteudo === 'function') { const h = c.alturaConteudo(sec); y0 = h ? h + 16 : 0; }
        if (pasta && typeof c.fimDaVista === 'function') { try { y0 = c.fimDaVista(sec, pasta) || 0; } catch (_) { y0 = 0; } }
        return (w = 360, h = 280) => { const i = n++; return { x: (i % cols) * 376, y: y0 + Math.floor(i / cols) * 296, w, h }; };
    }
    const salvar = c => { try { c.agendar && c.agendar(); } catch (_) { /* a página salva sozinha pelo $watch */ } };

    /* ================================================================ OBSIDIAN ================================================================ */

    /** Separa o frontmatter (--- ... ---) do corpo e lê as tags e aliases dele. */
    function frontmatter(texto) {
        const m = /^﻿?---\r?\n([\s\S]*?)\r?\n---[ \t]*(?:\r?\n|$)/.exec(texto);
        if (!m) return { fm: '', corpo: texto, tags: [] };
        const fm = m[1];
        const tags = [];
        const lista = campo => {
            const linha = new RegExp(`^${campo}:[ \\t]*(.*)$`, 'mi').exec(fm);
            if (!linha) return [];
            let v = linha[1].trim();
            if (v === '') { // lista em linhas "  - a"
                const resto = fm.slice(linha.index + linha[0].length).split(/\r?\n/);
                const itens = [];
                for (const l of resto) { const x = /^\s*-\s+(.*)$/.exec(l); if (x) itens.push(x[1]); else if (l.trim()) break; }
                return itens;
            }
            v = v.replace(/^\[|\]$/g, '');
            return v.split(/[,\s]+/);
        };
        lista('tags').concat(lista('tag')).forEach(t => { t = t.replace(/^["'#]+|["']+$/g, '').trim(); if (t) tags.push(t); });
        return { fm, corpo: texto.slice(m[0].length), tags };
    }
    /** Tags no meio do texto (#ideia, #projeto/site), fora de blocos de código. */
    function tagsDoTexto(corpo) {
        const sem = corpo.replace(/```[\s\S]*?```/g, '').replace(/`[^`\n]*`/g, '');
        const out = [];
        for (const m of sem.matchAll(/(^|[\s(])#([\p{L}\p{N}_\-/]+)/gu)) if (!/^\d+$/.test(m[2])) out.push(m[2]);
        return out;
    }
    /** Destinos dos links da nota: [[Nota]], [[pasta/Nota|apelido]], [[Nota#Título]], ![[imagem.png]] e [x](Nota.md). */
    function links(corpo) {
        const out = [];
        const sem = corpo.replace(/```[\s\S]*?```/g, '');
        for (const m of sem.matchAll(/(!?)\[\[([^\]|#^]+)(?:[#^][^\]|]*)?(?:\|[^\]]*)?\]\]/g)) out.push({ alvo: m[2].trim(), embed: !!m[1] });
        for (const m of sem.matchAll(/(!?)\[[^\]]*\]\(([^)\s]+)\)/g)) {
            if (/^[a-z]+:/i.test(m[2])) continue;
            let a; try { a = decodeURIComponent(m[2]); } catch (_) { a = m[2]; }
            out.push({ alvo: a.replace(/#.*$/, ''), embed: !!m[1] });
        }
        return out;
    }

    /**
     * Lê o cofre a partir de uma lista [{ caminho, ler: () => Promise<Uint8Array>, tam }].
     * Devolve { nome, notas: [{ caminho, pasta, nome, fm, corpo, tags, links }], imagens: [{ caminho, nome, src }], ignorados }.
     */
    async function lerCofre(nomeCofre, arquivos) {
        let lista = arquivos
            .map(a => ({ ...a, caminho: String(a.caminho).replace(/\\/g, '/').replace(/^\/+/, '') }))
            .filter(a => a.caminho && !a.caminho.split('/').includes('..')
                && !/(^|\/)(\.obsidian|\.trash|\.git|__MACOSX)(\/|$)|(^|\/)\.DS_Store$/.test(a.caminho));
        // pasta raiz comum (o próprio cofre dentro do zip): sai do caminho e vira o nome
        if (lista.length && lista.every(a => a.caminho.includes('/')) && new Set(lista.map(a => a.caminho.split('/')[0])).size === 1) {
            nomeCofre = lista[0].caminho.split('/')[0] || nomeCofre;
            lista = lista.map(a => ({ ...a, caminho: a.caminho.split('/').slice(1).join('/') }));
        }
        const dec = new TextDecoder('utf-8');
        const notas = [], imagens = [];
        let ignorados = 0;
        for (const a of lista) {
            const ext = extDe(a.caminho);
            if (ext === 'md' || ext === 'markdown') {
                if (notas.length >= MAX_NOTAS) { ignorados++; continue; }
                const texto = dec.decode(await a.ler());
                const { fm, corpo, tags } = frontmatter(texto);
                const partes = a.caminho.split('/');
                notas.push({
                    caminho: a.caminho, nome: semExt(partes.pop()), pasta: partes.join('/'),
                    fm, corpo, tags: [...new Set([...tags, ...tagsDoTexto(corpo)])], links: links(corpo),
                });
            } else if (EXT_IMG[ext] && (a.tam ?? 0) <= MAX_IMG && imagens.length < MAX_IMGS) {
                const d = await a.ler();
                if (d.length > MAX_IMG) { ignorados++; continue; }
                imagens.push({ caminho: a.caminho, nome: base(a.caminho), src: await paraDataUrl(d, EXT_IMG[ext]) });
            } else ignorados++;
        }
        return { nome: titulo80(nomeCofre) || 'Cofre', notas, imagens, ignorados };
    }
    const paraDataUrl = (u8, tipo) => new Promise((ok, erro) => {
        const r = new FileReader(); r.onload = () => ok(String(r.result)); r.onerror = erro; r.readAsDataURL(new Blob([u8], { type: tipo }));
    });

    /** O zip parece um cofre? (.obsidian/ dentro, ou quase só notas .md e imagens, sem cara de projeto de código). */
    function pareceCofre(nomes) {
        const n = nomes.map(x => x.replace(/\\/g, '/')).filter(x => !/(^|\/)(__MACOSX|\.DS_Store)/.test(x));
        if (n.some(x => /(^|\/)\.obsidian\//.test(x))) return true;
        const md = n.filter(x => /\.(md|markdown)$/i.test(x)).length;
        if (!md) return false;
        const outros = n.filter(x => !/\.(md|markdown|png|jpe?g|gif|webp|svg|pdf|canvas)$/i.test(x)).length;
        return md >= 2 && outros === 0;
    }

    /** Mapa mental dos links: um tronco por grupo de notas ligadas, a partir da nota com mais ligações. */
    function mapaDosLinks(nomeCofre, notas, ligacoes) {
        const PALETA = (window.WFQuadro && window.WFQuadro.PALETA) || ['#6366f1'];
        const MM = (window.WFQuadro && window.WFQuadro.MM) || { w: 188, h: 38 };
        const viz = notas.map(() => new Set());
        ligacoes.forEach(([a, b]) => { if (a !== b) { viz[a].add(b); viz[b].add(a); } });
        const raiz = { id: uid(), t: nomeCofre, pai: null, filhos: [], cor: PALETA[0] };
        const visto = new Set();
        let total = 1;
        const ordem = notas.map((_, i) => i).sort((a, b) => viz[b].size - viz[a].size);
        const soltas = [];
        ordem.forEach((ini, k) => {
            if (visto.has(ini)) return;
            if (!viz[ini].size) { soltas.push(ini); return; }
            if (total >= MAX_NOS) return;
            const cor = PALETA[(k % (PALETA.length - 1)) + 1] || PALETA[0];
            const no0 = { id: uid(), t: notas[ini].nome, filhos: [], cor };
            raiz.filhos.push(no0); total++;
            visto.add(ini);
            const fila = [[ini, no0]];
            while (fila.length && total < MAX_NOS) {
                const [i, no] = fila.shift();
                for (const j of [...viz[i]].sort((a, b) => viz[b].size - viz[a].size)) {
                    if (visto.has(j) || total >= MAX_NOS) continue;
                    visto.add(j);
                    const f = { id: uid(), t: notas[j].nome, filhos: [], cor };
                    no.filhos.push(f); total++;
                    fila.push([j, f]);
                }
            }
        });
        if (soltas.length && total < MAX_NOS) {
            const g = { id: uid(), t: `Sem links (${soltas.length})`, filhos: [], cor: PALETA[PALETA.length - 1] || PALETA[0] };
            raiz.filhos.push(g);
            soltas.slice(0, Math.max(0, MAX_NOS - total - 1)).forEach(i => g.filhos.push({ id: uid(), t: notas[i].nome, filhos: [], cor: g.cor }));
        }
        // desenho em árvore: folhas uma embaixo da outra, pai no meio dos filhos
        const nos = [];
        let linha = 0;
        const por = (no, nivel, pai) => {
            let y;
            if (!no.filhos.length) y = 20 + (linha++) * (MM.h + 14);
            else { const ys = no.filhos.map(f => por(f, nivel + 1, no.id)); y = Math.round((ys[0] + ys[ys.length - 1]) / 2); }
            nos.push({ id: no.id, t: String(no.t).slice(0, 60), x: 40 + nivel * (MM.w + 56), y, pai, cor: no.cor });
            return y;
        };
        por(raiz, 0, null);
        const w = Math.max(...nos.map(n => n.x)) + MM.w + 60, h = Math.max(...nos.map(n => n.y)) + MM.h + 60;
        return { dados: { nos }, w: Math.min(Math.max(w, 480), 1400), h: Math.min(Math.max(h + 56, 320), 900) };
    }

    /** Pergunta como organizar e importa o cofre na aba aberta. */
    async function importarCofre(c, nomeCofre, arquivos) {
        if (!c || !c.aba) { avisar(c, 'Abra uma aba do quadro antes de importar.'); return false; }
        let cofre;
        try { cofre = await lerCofre(nomeCofre, arquivos); } catch (e) { avisar(c, 'Não consegui ler o cofre do Obsidian.'); return false; }
        if (!cofre.notas.length) { avisar(c, 'Não achei notas .md nesse cofre.'); return false; }
        const pastas = temPastas(c);
        const temSub = cofre.notas.some(n => n.pasta);
        const temTags = cofre.notas.some(n => n.tags.length);
        const op = await new Promise(ok => {
            const j = janela('Importar do Obsidian', `
                <p>Cofre <b>${esc(cofre.nome)}</b>: ${cofre.notas.length} nota(s)${cofre.imagens.length ? `, ${cofre.imagens.length} imagem(ns)` : ''}${cofre.ignorados ? ` · ${cofre.ignorados} arquivo(s) ficam de fora (outros tipos ou grandes demais)` : ''}.</p>
                ${pastas ? `<fieldset class="space-y-1"><legend class="texto-2 text-xs">Organizar as notas</legend>
                    <label class="wf-notas-op"><input type="radio" name="org" value="pastas" ${temSub ? 'checked' : ''} ${temSub ? '' : 'disabled'}> <span>Pelas pastas do cofre${temSub ? '' : ' (o cofre não tem pastas)'}</span></label>
                    <label class="wf-notas-op"><input type="radio" name="org" value="tags" ${!temSub && temTags ? 'checked' : ''} ${temTags ? '' : 'disabled'}> <span>Pelas tags (uma pasta por tag; a nota vai para a primeira tag)${temTags ? '' : ' (nenhuma nota tem tag)'}</span></label>
                    <label class="wf-notas-op"><input type="radio" name="org" value="nada" ${!temSub && !temTags ? 'checked' : ''}> <span>Tudo junto numa pasta só</span></label>
                </fieldset>` : '<p class="texto-2 text-xs">Esta página não tem pastas: as notas entram soltas na aba.</p>'}
                <label class="wf-notas-op"><input type="checkbox" name="mapa" checked> <span>Criar um mapa mental com os links entre as notas</span></label>
                ${cofre.imagens.length ? '<label class="wf-notas-op"><input type="checkbox" name="imgs" checked> <span>Trazer as imagens do cofre como cartões de imagem</span></label>' : ''}
                <div class="wf-notas-acoes"><button type="button" class="btn" data-ok>Importar</button><button type="button" class="btn-sec" data-fechar>Cancelar</button></div>`);
            j.el.addEventListener('click', e => {
                if (e.target.closest('[data-fechar]') || e.target === j.el) { ok(null); return; }
                if (!e.target.closest('[data-ok]')) return;
                const r = { org: j.$('input[name=org]:checked')?.value || 'nada', mapa: !!j.$('input[name=mapa]')?.checked, imgs: !!j.$('input[name=imgs]')?.checked };
                j.fechar(); ok(r);
            }, true);
        });
        if (!op) return false;
        criarCofre(c, cofre, op);
        return true;
    }

    function criarCofre(c, cofre, op) {
        const sec = c.aba;
        const pastas = temPastas(c);
        const fora = pastaAberta(c, sec);
        const raiz = pastas ? novaPasta(c, sec, cofre.nome, fora, grade(c, sec, fora)()) : fora;
        const posDe = {}; // uma grade por pasta
        const pos = (p, w, h) => (posDe[p || ''] ??= grade(c, sec, p))(w, h);
        const subpastas = {};
        const pastaPara = caminho => { // "a/b/c" → pastas aninhadas dentro da pasta do cofre
            if (!pastas || !caminho) return raiz;
            let pai = raiz, acc = '';
            for (const parte of caminho.split('/').filter(Boolean)) {
                acc += '/' + parte;
                if (!subpastas[acc]) subpastas[acc] = novaPasta(c, sec, parte, pai, pos(pai));
                pai = subpastas[acc];
            }
            return pai;
        };

        // nomes → índice da nota, para os links
        const porNome = new Map(), porCaminho = new Map();
        cofre.notas.forEach((n, i) => { if (!porNome.has(chave(n.nome))) porNome.set(chave(n.nome), i); porCaminho.set(semExt(n.caminho).toLowerCase(), i); });
        const ligacoes = [];
        cofre.notas.forEach((n, i) => n.links.forEach(l => {
            if (l.embed && EXT_IMG[extDe(l.alvo)]) return;
            const j = porCaminho.get(semExt(l.alvo).toLowerCase().replace(/^\/+/, '')) ?? porNome.get(chave(l.alvo));
            if (j !== undefined) ligacoes.push([i, j]);
        }));

        let feitas = 0;
        cofre.notas.forEach(n => {
            const destino = op.org === 'pastas' ? pastaPara(n.pasta) : op.org === 'tags' ? pastaPara(n.tags[0] || '') : raiz;
            const id = novo(c, sec, 'texto', titulo80(n.nome), {
                texto: n.corpo.replace(/^\s*\n/, ''), tam: 'm',
                obsidian: { caminho: n.caminho, fm: n.fm || undefined, tags: n.tags.length ? n.tags : undefined },
            }, destino, pos(destino));
            if (id) feitas++;
        });

        if (op.imgs && cofre.imagens.length && aceita(c, 'imagem')) {
            const pi = pastas ? novaPasta(c, sec, 'Anexos', raiz, pos(raiz)) : raiz;
            cofre.imagens.forEach(im => {
                const id = novo(c, sec, 'imagem', titulo80(semExt(im.nome)), { obsidian: { caminho: im.caminho } }, pi, pos(pi, 320, 240));
                if (!id) return;
                c.imagens[id] = im.src;
                const url = (c.cfg && c.cfg.url) || '';
                if (url && window.WFQuadro && window.WFQuadro.enviar) {
                    window.WFQuadro.enviar(`${url}/img.${id}`, JSON.stringify(im.src)).catch(() => avisar(c, 'Não consegui salvar uma das imagens no servidor.'));
                }
            });
        }

        if (op.mapa && aceita(c, 'mapa')) {
            const m = mapaDosLinks(cofre.nome, cofre.notas, ligacoes);
            const L = pos(raiz, m.w, m.h);
            novo(c, sec, 'mapa', titulo80(`Mapa · ${cofre.nome}`), { ...m.dados, obsidianMapa: true }, raiz, { ...L, w: m.w, h: m.h });
        }
        salvar(c);
        if (pastas && raiz && typeof c.abrirPasta === 'function') {
            const p = c.estado.blocos.find(b => b.id === raiz);
            try { if (p) c.abrirPasta(p); } catch (_) { /* fica na vista atual */ }
        }
        // depois do aviso genérico do arquivos.js ("Bloco criado a partir do arquivo")
        setTimeout(() => avisar(c, `${feitas} nota(s) do Obsidian importada(s)${pastas ? ` na pasta “${cofre.nome}”` : ''}.`), 0);
    }

    /** .zip solto no quadro ou escolhido no menu. Devolve null quando o zip não é um cofre (aí segue como projeto de código). */
    async function talvezCofreZip(c, f, forcar) {
        const ler = window.WFArquivos && window.WFArquivos.lerZip;
        if (!ler) return null;
        const itens = await ler(await f.arrayBuffer());
        if (!forcar && !pareceCofre(itens.map(i => i.nome))) return null;
        return importarCofre(c, semExt(f.name || 'Cofre'), itens.map(i => ({ caminho: i.nome, tam: i.dados.length, ler: async () => i.dados })));
    }

    /** Pasta arrastada (DataTransferItem.webkitGetAsEntry) ou escolhida no seletor (input webkitdirectory). */
    async function arquivosDaEntrada(entrada) {
        const out = [];
        const lerDir = dir => new Promise((ok, erro) => {
            const r = dir.createReader(), todos = [];
            const prox = () => r.readEntries(lote => { if (!lote.length) ok(todos); else { todos.push(...lote); prox(); } }, erro);
            prox();
        });
        const andar = async (e, caminho) => {
            if (out.length > 2000) return;
            if (e.isFile) {
                const f = await new Promise((ok, erro) => e.file(ok, erro));
                out.push({ caminho: caminho + f.name, tam: f.size, ler: async () => new Uint8Array(await f.arrayBuffer()) });
            } else if (e.isDirectory && !/^(\.obsidian|\.trash|\.git|node_modules)$/.test(e.name)) {
                for (const x of await lerDir(e)) await andar(x, caminho + e.name + '/');
            }
        };
        await andar(entrada, '');
        return out;
    }
    async function soltarPasta(entrada) {
        const c = comp();
        if (!c) return;
        try { await importarCofre(c, entrada.name, await arquivosDaEntrada(entrada)); }
        catch (_) { avisar(c, 'Não consegui ler a pasta.'); }
    }

    /** Seletor de arquivos: zip do cofre ou pasta inteira. */
    function escolher(c, pasta) {
        const inp = document.createElement('input');
        inp.type = 'file';
        if (pasta) { inp.webkitdirectory = true; inp.multiple = true; } else inp.accept = '.zip,application/zip';
        inp.style.display = 'none';
        inp.addEventListener('change', async () => {
            const fs = Array.from(inp.files || []);
            inp.remove();
            if (!fs.length) return;
            try {
                if (pasta) {
                    const nome = (fs[0].webkitRelativePath || '').split('/')[0] || 'Cofre';
                    await importarCofre(c, nome, fs.map(f => ({ caminho: f.webkitRelativePath || f.name, tam: f.size, ler: async () => new Uint8Array(await f.arrayBuffer()) })));
                } else await talvezCofreZip(c, fs[0], true);
            } catch (_) { avisar(c, 'Não consegui ler o cofre.'); }
        });
        document.body.appendChild(inp);
        inp.click();
    }

    /* ---------------- exportar como cofre ---------------- */
    const nomeArquivo = s => (String(s || '').replace(/[\\/:*?"<>|#^[\]\u0000-\u001f]+/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 100) || 'Sem título');
    const celula = v => String(v ?? '').replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');

    /** Conteúdo do cartão em Markdown (ou null se o tipo não vira nota). */
    function markdownDe(b, titulos) {
        const d = b.dados || {};
        switch (b.tipo) {
            case 'texto': {
                const o = d.obsidian || {};
                // o frontmatter original volta como era (as tags do meio do texto continuam no texto)
                let fm = o.fm || '';
                if (d.notion && d.notion.url && !/^notion:/m.test(fm)) fm = (fm ? fm + '\n' : '') + 'notion: ' + d.notion.url;
                return (fm ? `---\n${fm}\n---\n` : '') + String(d.texto || '');
            }
            case 'lista':
                return (d.itens || []).map(i => `- [${i.f ? 'x' : ' '}] ${String(i.t || '').replace(/\r?\n/g, ' ')}`).join('\n') + '\n';
            case 'tabela': {
                const cab = d.cab || [], linhas = d.linhas || [];
                if (!cab.length) return '';
                return [`| ${cab.map(celula).join(' | ')} |`, `|${cab.map(() => ' --- |').join('')}`, ...linhas.map(l => `| ${cab.map((_, j) => celula(l[j])).join(' | ')} |`)].join('\n') + '\n';
            }
            case 'mapa': {
                const nos = d.nos || [];
                const filhos = id => nos.filter(n => (n.pai || null) === id).sort((a, b) => a.y - b.y);
                const out = [];
                const ir = (n, nivel) => {
                    const t = String(n.t || '').replace(/\r?\n/g, ' ');
                    out.push('    '.repeat(nivel) + '- ' + (titulos.has(t.toLowerCase()) ? `[[${t}]]` : t));
                    filhos(n.id).forEach(f => ir(f, nivel + 1));
                };
                filhos(null).forEach(n => ir(n, 0));
                return out.join('\n') + '\n';
            }
            case 'documento':
                return (d.secoes || []).map(s => (s.t ? `## ${s.t}\n\n` : '') + String(s.texto || '')).join('\n\n') + '\n';
            default:
                return null;
        }
    }

    /** Baixa a aba (ou a pasta `pastaId`) como um cofre do Obsidian em .zip. */
    async function exportar(c, sec, pastaId) {
        sec = sec || c.aba;
        const todos = c.estado.blocos.filter(b => b.secao === sec);
        const pasta = pastaId && todos.find(b => b.id === pastaId);
        const nomeCofre = nomeArquivo(pasta ? pasta.titulo : ((c.abas || c.estado.abas || []).find?.(a => (a.id || a) === sec)?.nome || sec));
        const enc = new TextEncoder();
        const arquivos = [];
        const usados = new Set();
        const unico = caminho => {
            let p = caminho, n = 2;
            const [semE, e] = [caminho.replace(/(\.[^./]+)$/, ''), (caminho.match(/(\.[^./]+)$/) || [, ''])[1]];
            while (usados.has(p.toLowerCase())) p = `${semE} ${n++}${e}`;
            usados.add(p.toLowerCase());
            return p;
        };
        const titulos = new Set(todos.filter(b => b.tipo === 'texto').map(b => String(b.titulo || '').trim().toLowerCase()));
        let fora = 0;
        const ir = (paiId, dir, visitados) => {
            todos.filter(b => (b.pasta || null) === (paiId || null)).forEach(b => {
                if (b.tipo === 'pasta') {
                    if (visitados.has(b.id)) return;
                    ir(b.id, dir + nomeArquivo(b.titulo) + '/', new Set([...visitados, b.id]));
                    return;
                }
                if (b.tipo === 'imagem') {
                    const src = String(c.imagens[b.id] || '');
                    const m = /^data:([^;,]+);base64,(.*)$/.exec(src);
                    if (!m) { fora++; return; }
                    const bin = atob(m[2]), u8 = new Uint8Array(bin.length);
                    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
                    const orig = b.dados && b.dados.obsidian && b.dados.obsidian.caminho;
                    // o Obsidian acha ![[foto.png]] pelo nome em qualquer pasta: o anexo fica na pasta em que o cartão está
                    arquivos.push({ nome: unico(`${dir}${orig ? nomeArquivo(semExt(base(orig))) + '.' + extDe(orig) : nomeArquivo(b.titulo) + '.' + (IMG_EXT[m[1]] || 'png')}`), dados: u8 });
                    return;
                }
                if (b.dados && b.dados.obsidianMapa) return; // o mapa gerado na importação não volta como nota
                const md = markdownDe(b, titulos);
                if (md === null) { fora++; return; }
                arquivos.push({ nome: unico(`${dir}${nomeArquivo(b.titulo)}.md`), dados: enc.encode(md) });
            });
        };
        ir(pasta ? pasta.id : null, '', new Set(pasta ? [pasta.id] : []));
        if (!arquivos.length) { avisar(c, 'Não há cartões de texto, lista, tabela, mapa ou documento para exportar aqui.'); return; }
        // o limite de downloads do plano também vale aqui
        if (window.WFArquivos && window.WFArquivos.registrarDownload && !(await window.WFArquivos.registrarDownload(c))) return;
        const blob = zip(arquivos.map(a => ({ nome: `${nomeCofre}/${a.nome}`, dados: a.dados })));
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${nomeCofre}.zip`;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        avisar(c, `Cofre com ${arquivos.length} arquivo(s) baixado${fora ? ` (${fora} cartão(ões) de outros tipos ficaram de fora)` : ''}. Abra a pasta no Obsidian como cofre.`);
    }

    /* ---------------- zip (sem compressão), para o cofre exportado ---------------- */
    const TAB_CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let x = n; for (let k = 0; k < 8; k++) x = x & 1 ? 0xEDB88320 ^ (x >>> 1) : x >>> 1; t[n] = x >>> 0; } return t; })();
    function crc32(u8) { let x = 0xFFFFFFFF; for (let i = 0; i < u8.length; i++) x = TAB_CRC[(x ^ u8[i]) & 0xFF] ^ (x >>> 8); return (x ^ 0xFFFFFFFF) >>> 0; }
    function zip(arquivos) {
        const enc = new TextEncoder(), partes = [], central = [];
        const d0 = new Date();
        const hora = (d0.getHours() << 11) | (d0.getMinutes() << 5) | (d0.getSeconds() >> 1);
        const dia = ((d0.getFullYear() - 1980) << 9) | ((d0.getMonth() + 1) << 5) | d0.getDate();
        let off = 0;
        for (const a of arquivos) {
            const nome = enc.encode(a.nome), d = a.dados, crc = crc32(d);
            const lh = new DataView(new ArrayBuffer(30));
            lh.setUint32(0, 0x04034b50, true); lh.setUint16(4, 20, true); lh.setUint16(6, 0x0800, true); lh.setUint16(8, 0, true);
            lh.setUint16(10, hora, true); lh.setUint16(12, dia, true); lh.setUint32(14, crc, true);
            lh.setUint32(18, d.length, true); lh.setUint32(22, d.length, true); lh.setUint16(26, nome.length, true); lh.setUint16(28, 0, true);
            partes.push(lh, nome, d);
            const ch = new DataView(new ArrayBuffer(46));
            ch.setUint32(0, 0x02014b50, true); ch.setUint16(4, 20, true); ch.setUint16(6, 20, true); ch.setUint16(8, 0x0800, true); ch.setUint16(10, 0, true);
            ch.setUint16(12, hora, true); ch.setUint16(14, dia, true); ch.setUint32(16, crc, true);
            ch.setUint32(20, d.length, true); ch.setUint32(24, d.length, true); ch.setUint16(28, nome.length, true);
            ch.setUint32(42, off, true);
            central.push(ch, nome);
            off += 30 + nome.length + d.length;
        }
        const tam = central.reduce((s, p) => s + p.byteLength, 0);
        const fim = new DataView(new ArrayBuffer(22));
        fim.setUint32(0, 0x06054b50, true); fim.setUint16(8, arquivos.length, true); fim.setUint16(10, arquivos.length, true);
        fim.setUint32(12, tam, true); fim.setUint32(16, off, true);
        return new Blob([...partes, ...central, fim], { type: 'application/zip' });
    }

    /* ================================================================ NOTION ================================================================ */
    async function pedir(url, metodo = 'GET', corpo) {
        const opcoes = {
            method: metodo, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token'), 'X-WF-Dono': '' },
        };
        if (corpo) {
            // em base64: o mod_security da hospedagem barra corpos que parecem código ou SQL
            const bytes = new TextEncoder().encode(JSON.stringify(corpo));
            let bin = '';
            for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
            opcoes.body = JSON.stringify({ b64: btoa(bin) });
        }
        const r = await fetch(url, opcoes);
        let j = {};
        try { j = await r.json(); } catch (_) { /* sem JSON */ }
        if (r.status === 419) throw new Error('Sua sessão expirou. Recarregue a página.');
        if (r.status === 429) throw new Error('Muitos pedidos seguidos. Espere um minuto e tente de novo.');
        if (!r.ok) {
            const e = new Error(j.message || 'O Notion não respondeu. Tente de novo.');
            e.status = r.status;
            throw e;
        }
        return j;
    }
    const irParaIntegracoes = () => {
        try { sessionStorage.setItem('negocios.aba', 'integracoes'); } catch (_) { /* sem sessionStorage */ }
        location.href = meta('wf-negocios-url') || '/areas/negocios';
    };
    function semConexao(msg) {
        const j = janela('Notion', `<p>${esc(msg || 'O Notion ainda não está conectado à sua conta.')}</p>
            <p class="texto-2 text-xs">A conexão fica em Negócios › Integrações › Notion.</p>
            <div class="wf-notas-acoes"><button type="button" class="btn" data-ir>Conectar o Notion →</button><button type="button" class="btn-sec" data-fechar>Fechar</button></div>`);
        j.$('[data-ir]').addEventListener('click', irParaIntegracoes);
    }
    const dataBR = iso => { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' }); };

    /** Pede o conteúdo em lotes de 10 e chama `cada(item)`; devolve os erros por página. */
    async function conteudos(lista, cada, progresso) {
        const erros = [];
        for (let i = 0; i < lista.length; i += 10) {
            progresso && progresso(i, lista.length);
            const j = await pedir(URL_NOTION() + '/conteudo', 'POST', { itens: lista.slice(i, i + 10).map(x => ({ id: x.id, tipo: x.tipo || null })) });
            (j.itens || []).forEach(cada);
            erros.push(...(j.erros || []));
        }
        return erros;
    }
    const dadosNotion = it => ({ id: it.id, tipo: it.tipo, editado: it.editado, url: it.url });

    async function importarNotion(c) {
        if (!c || !c.aba) { avisar(c, 'Abra uma aba do quadro antes de importar.'); return; }
        let st;
        try { st = await pedir(URL_NOTION()); } catch (e) { avisar(c, e.message); return; }
        if (!st.conectado) { semConexao(); return; }
        const j = janela('Importar do Notion', `
            <p class="texto-2 text-xs">${st.workspace ? `Espaço <b>${esc(st.workspace)}</b>. ` : ''}Aparecem só as páginas compartilhadas com a integração (no Notion: ••• › Conexões).</p>
            <div class="wf-notas-acoes"><input class="campo flex-1" type="search" placeholder="Buscar pelo título" data-q aria-label="Buscar no Notion" style="min-width:10rem"><button type="button" class="btn-sec" data-buscar>Buscar</button></div>
            <ul class="wf-notas-lista" data-lista><li class="texto-2">Carregando…</li></ul>
            <p class="wf-notas-erro text-sm" data-erro hidden></p>
            <div class="wf-notas-acoes"><button type="button" class="btn" data-ok disabled>Importar</button><button type="button" class="btn-sec" data-todas>Marcar todas</button><span class="text-xs texto-2" data-info></span></div>`);
        let itens = [];
        const desenhar = () => {
            const ja = new Set(c.estado.blocos.filter(b => b.secao === c.aba && b.dados && b.dados.notion).map(b => b.dados.notion.id));
            j.$('[data-lista]').innerHTML = itens.length ? itens.map((it, i) => `<li><label title="${esc(it.titulo)}"><input type="checkbox" data-i="${i}">
                <span>${esc(it.icone || (it.tipo === 'banco' ? '🗃' : '📄'))}</span><span class="t">${esc(it.titulo || 'Sem título')}</span>
                <span class="s">${it.tipo === 'banco' ? 'banco · ' : ''}${ja.has(it.id) ? 'já importada · ' : ''}${esc(dataBR(it.editado))}</span></label></li>`).join('')
                : '<li class="texto-2">Nada encontrado. Compartilhe as páginas com a integração no Notion e busque de novo.</li>';
            atualizar();
        };
        const marcadas = () => j.$$('[data-i]:checked').map(x => itens[+x.dataset.i]);
        const atualizar = () => { const n = marcadas().length; j.$('[data-ok]').disabled = !n; j.$('[data-info]').textContent = n ? `${n} marcada(s)` : ''; };
        const erro = m => { const e = j.$('[data-erro]'); e.textContent = m || ''; e.hidden = !m; };
        const buscar = async () => {
            erro('');
            j.$('[data-lista]').innerHTML = '<li class="texto-2">Carregando…</li>';
            try { itens = (await pedir(URL_NOTION() + '/paginas?q=' + encodeURIComponent(j.$('[data-q]').value.trim()))).itens || []; desenhar(); }
            catch (e) { j.$('[data-lista]').innerHTML = ''; erro(e.message); }
        };
        j.$('[data-lista]').addEventListener('change', atualizar);
        j.$('[data-buscar]').addEventListener('click', buscar);
        j.$('[data-q]').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); buscar(); } });
        j.$('[data-todas]').addEventListener('click', () => { const t = j.$$('[data-i]'); const todas = t.every(x => x.checked); t.forEach(x => { x.checked = !todas; }); atualizar(); });
        j.$('[data-ok]').addEventListener('click', async () => {
            const lista = marcadas();
            if (!lista.length) return;
            const bt = j.$('[data-ok]');
            bt.disabled = true;
            erro('');
            try {
                const r = await trazerDoNotion(c, lista, (i, n) => { bt.textContent = `Importando ${i + 1}–${Math.min(i + 10, n)} de ${n}…`; });
                j.fechar();
                avisar(c, `${r.novos} cartão(ões) criado(s) e ${r.atualizados} atualizado(s) a partir do Notion${r.erros.length ? ` · ${r.erros.length} com erro` : ''}.`);
            } catch (e) { erro(e.message); bt.disabled = false; bt.textContent = 'Importar'; }
        });
        buscar();
    }

    /** Cria os cartões (ou atualiza os que já vieram do Notion nesta aba) numa pasta "Notion". */
    async function trazerDoNotion(c, lista, progresso) {
        const sec = c.aba;
        const pastas = temPastas(c);
        const fora = pastaAberta(c, sec);
        let raiz = fora;
        let pos = null;
        let novos = 0, atualizados = 0;
        const erros = await conteudos(lista, it => {
            const ja = c.estado.blocos.find(b => b.secao === sec && b.dados && b.dados.notion && b.dados.notion.id === it.id);
            if (ja) { aplicar(ja, it); atualizados++; return; }
            if (!pos) { // a pasta só nasce quando chega a primeira página nova
                if (pastas) {
                    const existente = c.estado.blocos.find(b => b.secao === sec && b.tipo === 'pasta' && b.dados && b.dados.notionRaiz && (b.pasta || null) === fora);
                    raiz = existente ? existente.id : novaPasta(c, sec, 'Notion', fora, grade(c, sec, fora)());
                    const p = c.estado.blocos.find(b => b.id === raiz);
                    if (p) p.dados = Object.assign({}, p.dados, { notionRaiz: true });
                }
                pos = grade(c, sec, raiz);
            }
            const tit = titulo80((it.icone ? it.icone + ' ' : '') + (it.titulo || 'Sem título'));
            const id = it.tipo === 'banco'
                ? novo(c, sec, 'tabela', tit, { cab: it.cab, linhas: it.linhas, notion: dadosNotion(it) }, raiz, pos(Math.min(1100, 160 + it.cab.length * 140), Math.min(640, 120 + it.linhas.length * 36)))
                : novo(c, sec, 'texto', tit, { texto: it.texto, tam: 'm', notion: dadosNotion(it) }, raiz, pos(380, 320));
            if (id) novos++;
        }, progresso);
        salvar(c);
        if (novos && pastas && raiz && raiz !== fora && typeof c.abrirPasta === 'function') {
            const p = c.estado.blocos.find(b => b.id === raiz);
            try { if (p) c.abrirPasta(p); } catch (_) { /* fica na vista atual */ }
        }
        return { novos, atualizados, erros };
    }
    function aplicar(b, it) {
        const tit = titulo80((it.icone ? it.icone + ' ' : '') + (it.titulo || 'Sem título'));
        if (it.tipo === 'banco') b.dados = Object.assign({}, b.dados, { cab: it.cab, linhas: it.linhas, notion: dadosNotion(it) });
        else b.dados = Object.assign({}, b.dados, { texto: it.texto, notion: dadosNotion(it) });
        b.titulo = tit;
    }

    /** Atualiza todos os cartões desta aba que vieram do Notion (só os que mudaram lá). */
    async function sincronizar(c) {
        if (!c || !c.aba) return;
        const cartoes = c.estado.blocos.filter(b => b.secao === c.aba && b.dados && b.dados.notion && b.dados.notion.id);
        if (!cartoes.length) { avisar(c, 'Nenhum cartão desta aba veio do Notion. Use “Importar do Notion” primeiro.'); return; }
        avisar(c, `Sincronizando ${cartoes.length} cartão(ões) com o Notion…`);
        let mudou = 0, igual = 0;
        try {
            const unicos = [...new Map(cartoes.map(b => [b.dados.notion.id, b.dados.notion])).values()];
            const erros = await conteudos(unicos, it => {
                cartoes.filter(b => b.dados.notion.id === it.id).forEach(b => {
                    if (b.dados.notion.editado === it.editado) { igual++; return; }
                    aplicar(b, it); mudou++;
                });
            });
            salvar(c);
            avisar(c, `Notion: ${mudou} cartão(ões) atualizado(s), ${igual} sem mudança${erros.length ? `, ${erros.length} não encontrado(s) (tirado(s) da integração ou apagado(s))` : ''}.`);
        } catch (e) {
            if (e.status === 409) semConexao(e.message); else avisar(c, e.message);
        }
    }

    /* ---------------- menus ---------------- */
    function menuQuadro(c, sec) {
        const n = c.estado.blocos.filter(b => b.secao === sec && b.dados && b.dados.notion).length;
        const aberta = pastaAberta(c, sec);
        return [{ rotulo: 'Notion e Obsidian', icone: '⇄', sub: [
            { rotulo: 'Importar do Notion…', icone: 'N', acao: () => importarNotion(c) },
            { rotulo: n ? `Sincronizar com o Notion (${n})` : 'Sincronizar com o Notion', icone: '↻', desativado: !n, acao: () => sincronizar(c) },
            '-',
            { rotulo: 'Importar cofre do Obsidian (.zip)…', icone: '◆', acao: () => escolher(c, false) },
            { rotulo: 'Importar cofre do Obsidian (pasta)…', icone: '📁', acao: () => escolher(c, true) },
            { rotulo: aberta ? 'Exportar esta pasta para o Obsidian (.zip)' : 'Exportar a aba para o Obsidian (.zip)', icone: '⤓', acao: () => exportar(c, sec, aberta) },
        ] }];
    }
    function menuCartao(c, b) {
        return b && b.tipo === 'pasta' ? [{ rotulo: 'Exportar para o Obsidian (.zip)', icone: '⤓', acao: () => exportar(c, b.secao, b.id) }] : [];
    }

    window.WFNotas = {
        menuQuadro, menuCartao, talvezCofreZip, soltarPasta, importarCofre, exportar, importarNotion, sincronizar,
        // para os testes no navegador
        _: { frontmatter, tagsDoTexto, links, pareceCofre, lerCofre, mapaDosLinks, markdownDe, zip, crc32 },
    };
})();
