/* Work Flower · baixar cartões e criar blocos arrastando arquivos para o quadro
   Funciona nas páginas Diverso, Corpo e Mente. Todo cartão (.wf-item) ganha um botão de baixar no cabeçalho.
   Arrastar um .zip (baixado de um bloco de código) cria um bloco de código com o projeto (só onde existir esse bloco). */
(function () {
    'use strict';
    if (window.WFArquivos) return;

    const TIPOS = ['texto', 'tabela', 'lista', 'imagem', 'mapa', 'documento', 'codigo', 'tabletop'];
    const MAX_ARQ = 12 * 1024 * 1024;
    const MAX_TXT = 2 * 1024 * 1024;
    const EXT_IMG = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/webp': 'webp', 'image/gif': 'gif', 'image/svg+xml': 'svg' };
    // Cartões fixos: quais partes do estado cada aba guarda (Corpo e Mente)
    const CARTAO_FIXO = {
        // Corpo
        treinos: ['treinos', 'exercicios'], cargas: ['exercicios'], dietas: ['dieta'], agua: ['agua'], calorias: ['cal', 'alimentos'], metas: ['metas'],
        // Mente (tarefas é tratada à parte)
        livros: ['livros'], estudos: ['estudos'], notas: ['notas'], faculdade: ['fac'],
    };
    // Páginas que guardam a configuração num <script type="application/json">
    const IDS_CFG = ['mente-cfg', 'diverso-cfg', 'corpo-cfg'];

    const comp = () => {
        const q = document.querySelector('.wf-quadro');
        try { return q && window.Alpine ? window.Alpine.$data(q) : null; } catch (_) { return null; }
    };
    const limpo = s => String(s || '').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '').trim().slice(0, 80);
    const semExt = n => String(n || '').replace(/\.[^.]+$/, '');
    const espera = ms => new Promise(r => setTimeout(r, ms));

    /** Lê o JSON de configuração da página (mente-cfg, diverso-cfg ou corpo-cfg). */
    function cfgPagina() {
        for (const id of IDS_CFG) {
            const el = document.getElementById(id);
            if (el) { try { return JSON.parse(el.textContent) || {}; } catch (_) { /* tenta o próximo */ } }
        }
        return {};
    }

    /** Mostra um aviso usando o da página (aviso ou msg). */
    function avisar(c, m) {
        if (typeof c.aviso === 'function') { c.aviso(m); return; }
        c.msg = m;
        clearTimeout(avisar._t);
        avisar._t = setTimeout(() => { if (c.msg === m) c.msg = ''; }, 4000);
    }
    /** Endereço base de gravação (cfg.url do componente ou do JSON da página). */
    function urlBase(c) {
        if (c.cfg && c.cfg.url) return c.cfg.url;
        return cfgPagina().url || '';
    }
    /** A página aceita esse tipo de bloco? (Corpo e Mente não têm código nem tabletop.) */
    function permitido(c, tipo) {
        return !Array.isArray(c.tiposBloco) || c.tiposBloco.some(t => t.id === tipo);
    }
    /** Cria um bloco: usa criarBloco (Diverso) ou, se não existir, novoBloco (Corpo e Mente) e depois ajusta título, dados e posição. */
    function criar(c, sec, tipo, pos, over) {
        over = over || {};
        if (!TIPOS.includes(tipo) || !permitido(c, tipo)) { avisar(c, 'Esta página não tem esse tipo de bloco.'); return null; }
        if (typeof c.criarBloco === 'function') return c.criarBloco(sec, tipo, pos, over);
        if (typeof c.novoBloco !== 'function') return null;
        const antes = new Set(c.estado.blocos.map(b => b.id));
        c.novoBloco(sec, tipo);
        const nb = c.estado.blocos.find(b => !antes.has(b.id));
        if (!nb) return null;
        if (over.titulo) nb.titulo = over.titulo;
        if (over.dados) nb.dados = Object.assign({}, nb.dados, over.dados);
        const chaveSec = nb.secao || nb.sec || sec;
        const lay = c.estado.layout && c.estado.layout[chaveSec] && c.estado.layout[chaveSec][nb.id];
        if (lay && pos) { lay.x = pos.x; lay.y = pos.y; }
        return nb.id;
    }

    /* Acha o bloco `b` do cartão subindo pelo DOM e lendo a pilha de dados do Alpine (não depende de Alpine.$data). */
    function blocoDe(el) {
        for (let n = el; n; n = n.parentElement) {
            const pilha = n._x_dataStack;
            if (!pilha) continue;
            for (let i = 0; i < pilha.length; i++) {
                let o = pilha[i], b;
                try { b = o && o.b; } catch (_) { b = null; }
                if (b && typeof b === 'object' && b.id && b.tipo) return b;
            }
        }
        return null;
    }

    function salvarBlob(blob, nome) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = nome;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    }

    function dataUrlParaBlob(u) {
        const m = /^data:([^;,]+)((?:;[^;,]+)*?),([\s\S]*)$/.exec(String(u || ''));
        if (!m) return null;
        if (/;base64/i.test(m[2])) {
            const bin = atob(m[3]);
            const arr = new Uint8Array(bin.length);
            for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
            return new Blob([arr], { type: m[1] });
        }
        return new Blob([decodeURIComponent(m[3])], { type: m[1] });
    }

    /* ---------- baixar ---------- */
    function pacote(c, b) {
        const imgs = {};
        Object.keys(c.imagens || {}).forEach(k => {
            if (k === b.id || k.startsWith(b.id + '.')) imgs[k.slice(b.id.length)] = c.imagens[k];
        });
        return { wf: 'bloco', v: 1, tipo: b.tipo, titulo: b.titulo, dados: JSON.parse(JSON.stringify(b.dados || {})), imgs };
    }

    function baixarBloco(c, b, item) {
        const nome = limpo(b.titulo) || b.tipo;
        if (b.tipo === 'texto') {
            salvarBlob(new Blob([b.dados.texto || ''], { type: 'text/markdown;charset=utf-8' }), nome + '.md');
        } else if (b.tipo === 'imagem') {
            const src = c.imagens[b.id];
            const blob = src && dataUrlParaBlob(src);
            if (!blob) { avisar(c, 'Este bloco ainda não tem imagem.'); return; }
            salvarBlob(blob, nome + '.' + (EXT_IMG[blob.type] || 'png'));
        } else if (b.tipo === 'tabela' && typeof c.tbExportar === 'function') {
            c.tbExportar(b);
        } else if (b.tipo === 'codigo' && b.dados.modo === 'ide') {
            // o editor já sabe montar o ZIP do projeto
            const zip = Array.from(item.querySelectorAll('button')).find(x => /baixar zip/i.test(x.textContent || ''));
            if (zip) zip.click(); else avisar(c, 'Abra o editor para baixar o projeto em ZIP.');
        } else {
            // lista, mapa, documento etc.: pacote .wf.json (dá para soltar de volta em qualquer quadro)
            const json = JSON.stringify(pacote(c, b));
            salvarBlob(new Blob([json], { type: 'application/json;charset=utf-8' }), nome + '.' + b.tipo + '.wf.json');
        }
    }

    /** Cartões fixos (tarefas e, no Corpo e na Mente, as demais partes do estado da aba). */
    function baixarCartaoFixo(c, item) {
        const tarefas = c.tarefas || (c.cfg && c.cfg.tarefas) || cfgPagina().tarefas;
        if ((item.closest('.wf-grupo') || c.aba === 'tarefas') && Array.isArray(tarefas)) {
            salvarBlob(new Blob([JSON.stringify(tarefas, null, 2)], { type: 'application/json;charset=utf-8' }), 'tarefas.json');
            return true;
        }
        const chaves = CARTAO_FIXO[c.aba];
        if (chaves && c.estado) {
            const dados = {};
            chaves.forEach(k => { if (k in c.estado) dados[k] = JSON.parse(JSON.stringify(c.estado[k])); });
            if (Object.keys(dados).length) {
                salvarBlob(new Blob([JSON.stringify(dados, null, 2)], { type: 'application/json;charset=utf-8' }), c.aba + '.json');
                return true;
            }
        }
        return false;
    }

    function baixar(item) {
        const c = comp();
        if (!c || !item) return;
        try {
            const b = blocoDe(item);
            if (b) { baixarBloco(c, b, item); return; }
            if (baixarCartaoFixo(c, item)) return;
            avisar(c, 'Este cartão não tem um arquivo para baixar.');
        } catch (_) { avisar(c, 'Não consegui gerar o arquivo.'); }
    }

    /* ---------- ZIP: leitura no navegador ---------- */
    async function inflar(dados) {
        if (typeof DecompressionStream === 'undefined') throw new Error('sem DecompressionStream');
        const s = new Blob([dados]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
        return new Uint8Array(await new Response(s).arrayBuffer());
    }

    /** Devolve [{ nome, dados: Uint8Array }] (sem pastas). */
    async function lerZip(buf) {
        const dv = new DataView(buf), u8 = new Uint8Array(buf);
        let eocd = -1;
        for (let i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 65557); i--) {
            if (dv.getUint32(i, true) === 0x06054b50) { eocd = i; break; }
        }
        if (eocd < 0) throw new Error('zip inválido');
        const total = dv.getUint16(eocd + 10, true);
        let p = dv.getUint32(eocd + 16, true);
        const dec = new TextDecoder('utf-8');
        const saida = [];
        for (let k = 0; k < total; k++) {
            if (dv.getUint32(p, true) !== 0x02014b50) break;
            const metodo = dv.getUint16(p + 10, true);
            const csize = dv.getUint32(p + 20, true);
            const nl = dv.getUint16(p + 28, true), el = dv.getUint16(p + 30, true), cl = dv.getUint16(p + 32, true);
            const lh = dv.getUint32(p + 42, true);
            const nome = dec.decode(u8.subarray(p + 46, p + 46 + nl));
            p += 46 + nl + el + cl;
            if (nome.endsWith('/')) continue;
            const ini = lh + 30 + dv.getUint16(lh + 26, true) + dv.getUint16(lh + 28, true);
            const bruto = u8.subarray(ini, ini + csize);
            let dados;
            if (metodo === 0) dados = bruto;
            else if (metodo === 8) dados = await inflar(bruto);
            else continue;
            saida.push({ nome, dados });
        }
        return saida;
    }

    /** Arquivos de texto do zip, com caminhos limpos e sem a pasta raiz comum. */
    async function projetoDoZip(f) {
        const itens = await lerZip(await f.arrayBuffer());
        const dec = new TextDecoder('utf-8', { fatal: true });
        let ignorados = 0;
        let lista = [];
        for (const it of itens) {
            const caminho = it.nome.replace(/\\/g, '/').replace(/^\/+/, '');
            if (!caminho || /(^|\/)(__MACOSX|\.DS_Store)(\/|$)/.test(caminho) || caminho.split('/').includes('..')) continue;
            if (it.dados.length > MAX_TXT) { ignorados++; continue; }
            try { lista.push({ caminho, texto: dec.decode(it.dados) }); } catch (_) { ignorados++; }
        }
        if (lista.length && lista.every(x => x.caminho.includes('/')) && new Set(lista.map(x => x.caminho.split('/')[0])).size === 1) {
            lista = lista.map(x => ({ caminho: x.caminho.split('/').slice(1).join('/'), texto: x.texto }));
        }
        return { lista: lista.slice(0, 300), ignorados };
    }

    /** Cria um bloco de código em modo Editor e troca os arquivos dele pelos do zip. */
    async function importarZip(c, f, pos, base) {
        if (!permitido(c, 'codigo')) { avisar(c, 'Esta página não tem bloco de código para receber o projeto.'); return false; }
        const { lista, ignorados } = await projetoDoZip(f);
        if (!lista.length) { avisar(c, `"${f.name}" não tem arquivos de texto para importar.`); return false; }
        const id = criar(c, c.aba, 'codigo', pos, { titulo: base, dados: { modo: 'ide' } });
        if (!id) return false;
        const b = c.estado.blocos.find(x => x.id === id);

        // espera o editor montar e criar a estrutura inicial do projeto
        let ide = null;
        for (let t = 0; t < 40; t++) {
            await espera(50);
            ide = b.dados.ide;
            if (ide && typeof ide === 'object') break;
        }
        if (!ide || typeof ide !== 'object') { avisar(c, 'Criei o bloco, mas não consegui preencher o editor.'); return true; }

        // acha a lista de arquivos pelo formato: itens com "caminho" e "texto"
        const chaveArq = Object.keys(ide).find(k => Array.isArray(ide[k]) && ide[k].some(x => x && typeof x === 'object' && 'caminho' in x && 'texto' in x))
            || Object.keys(ide).find(k => Array.isArray(ide[k]) && !ide[k].length);
        if (!chaveArq) { avisar(c, 'Criei o bloco, mas o formato do editor é diferente do esperado.'); return true; }

        const antigos = ide[chaveArq].filter(x => x && typeof x === 'object');
        const modelo = antigos[0] ? JSON.parse(JSON.stringify(antigos[0])) : {};
        const idsAntigos = new Set(antigos.map(x => x.id));
        const novos = lista.map(x => Object.assign({}, modelo, { id: window.WFQuadro.util.uid(), caminho: x.caminho, texto: x.texto }));
        ide[chaveArq] = novos;

        const primeiro = novos.find(x => /(^|\/)index\.html?$/i.test(x.caminho)) || novos[0];
        // "ativo" e a lista de abas abertas apontam para ids de arquivos
        Object.keys(ide).forEach(k => {
            if (k === chaveArq) return;
            const v = ide[k];
            if (typeof v === 'string' && idsAntigos.has(v)) ide[k] = primeiro.id;
            else if (Array.isArray(v) && v.length && v.every(x => idsAntigos.has(x))) ide[k] = [primeiro.id];
        });
        ide.ativo = primeiro.id;

        const chaveLay = b.secao || b.sec;
        try { window.WFIde && window.WFIde.ampliar && window.WFIde.ampliar(c.estado.layout[chaveLay] && c.estado.layout[chaveLay][b.id]); } catch (_) { /* opcional */ }
        if (ignorados) avisar(c, `${ignorados} arquivo(s) binários ou grandes demais foram ignorados.`);
        return true;
    }

    /* ---------- criar blocos a partir de arquivos ---------- */
    const lerTexto = f => new Promise((ok, erro) => { const r = new FileReader(); r.onload = () => ok(String(r.result)); r.onerror = erro; r.readAsText(f); });
    const lerUrl = f => new Promise((ok, erro) => { const r = new FileReader(); r.onload = () => ok(String(r.result)); r.onerror = erro; r.readAsDataURL(f); });

    function guardarImagem(c, chave, src) {
        c.imagens[chave] = src;
        window.WFQuadro.enviar(`${urlBase(c)}/img.${chave}`, JSON.stringify(src))
            .catch(() => avisar(c, 'Não consegui salvar uma das imagens no servidor.'));
    }

    async function importar(c, f, pos) {
        const nome = f.name || '';
        const ext = (nome.split('.').pop() || '').toLowerCase();
        const base = limpo(semExt(nome)) || undefined;
        if (f.size > MAX_ARQ) { avisar(c, `"${nome}" é grande demais (máx. 12 MB).`); return false; }

        if (ext === 'zip' || f.type === 'application/zip' || f.type === 'application/x-zip-compressed') {
            return await importarZip(c, f, pos, base);
        }
        if (f.type.startsWith('image/')) {
            let src = await lerUrl(f);
            if (src.length > 900000 && window.WFQuadro.reduzir) {
                try { src = await window.WFQuadro.reduzir(f, 1600, 0.8); } catch (_) { /* usa a original */ }
            }
            const id = criar(c, c.aba, 'imagem', pos, { titulo: base });
            if (!id) return false;
            guardarImagem(c, id, src);
            return true;
        }
        if (['md', 'markdown', 'txt'].includes(ext) || f.type === 'text/markdown' || f.type === 'text/plain') {
            const texto = await lerTexto(f);
            return !!criar(c, c.aba, 'texto', pos, { titulo: base, dados: { texto, tam: 'm' } });
        }
        if (['csv', 'tsv'].includes(ext)) {
            const id = criar(c, c.aba, 'tabela', pos, { titulo: base });
            if (!id) return false;
            const nb = c.estado.blocos.find(x => x.id === id);
            if (nb && typeof c.tbImportar === 'function') { await c.tbImportar(nb, f); return true; }
            avisar(c, 'Não consegui importar o CSV.');
            return true;
        }
        if (ext === 'json' || f.type === 'application/json') {
            let o;
            try { o = JSON.parse(await lerTexto(f)); } catch (_) { o = null; }
            if (!o || o.wf !== 'bloco' || !TIPOS.includes(o.tipo) || !o.dados || typeof o.dados !== 'object') {
                avisar(c, `"${nome}" não é um bloco do Work Flower.`);
                return false;
            }
            const id = criar(c, c.aba, o.tipo, pos, { titulo: String(o.titulo || '').slice(0, 80) || undefined, dados: o.dados });
            if (!id) return false;
            Object.entries(o.imgs && typeof o.imgs === 'object' ? o.imgs : {}).forEach(([suf, src]) => {
                if (!/^(\.[A-Za-z0-9._-]{1,80})?$/.test(suf) || !/^data:image\//.test(String(src || ''))) return;
                guardarImagem(c, id + suf, src);
            });
            return true;
        }
        if (ext === 'pdf' || f.type === 'application/pdf') {
            avisar(c, 'Para ler um PDF, anexe-o a um livro na aba Livros.');
            return false;
        }
        avisar(c, `Tipo de arquivo não suportado: "${nome}".`);
        return false;
    }

    async function soltar(arquivos, x, y) {
        const c = comp();
        if (!c) return;
        if (!c.aba) { avisar(c, 'Crie uma aba antes de soltar arquivos.'); return; }
        let n = 0;
        for (const f of arquivos) {
            try {
                if (await importar(c, f, { x: Math.max(0, x + n * 28), y: Math.max(0, y + n * 28) })) n++;
            } catch (_) { avisar(c, `Não consegui ler "${f.name}".`); }
        }
        if (n) avisar(c, n === 1 ? 'Bloco criado a partir do arquivo.' : `${n} blocos criados a partir dos arquivos.`);
    }

    /* ---------- arrastar e soltar ---------- */
    const temArquivos = e => !!e.dataTransfer && Array.from(e.dataTransfer.types || []).includes('Files');
    const quadro = e => (e.target && e.target.closest ? e.target.closest('.wf-quadro') : null);
    const marcar = (q, on) => { document.querySelectorAll('.wf-quadro.wf-soltar').forEach(x => { if (x !== q || !on) x.classList.remove('wf-soltar'); }); if (q && on) q.classList.add('wf-soltar'); };

    document.addEventListener('dragover', e => {
        if (!temArquivos(e)) return;
        if (e.target.closest && e.target.closest('input[type=file]')) return;
        e.preventDefault();
        const q = quadro(e);
        e.dataTransfer.dropEffect = q ? 'copy' : 'none';
        marcar(q, !!q);
    });
    document.addEventListener('dragleave', e => {
        if (!temArquivos(e)) return;
        if (!e.relatedTarget) marcar(null, false);
    });
    document.addEventListener('dragend', () => marcar(null, false));
    document.addEventListener('drop', e => {
        if (!temArquivos(e)) return;
        if (e.target.closest && e.target.closest('input[type=file]')) return;
        e.preventDefault();
        const q = quadro(e);
        marcar(null, false);
        if (!q) return;
        const r = q.getBoundingClientRect();
        soltar(Array.from(e.dataTransfer.files || []), e.clientX - r.left, e.clientY - r.top);
    });

    /* ---------- botão de baixar em TODO cartão ---------- */
    const ICO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>';

    function injetar() {
        document.querySelectorAll('.wf-item .wf-topo').forEach(topo => {
            if (topo.querySelector('.wf-dl-btn')) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mini-btn wf-dl-btn';
            btn.innerHTML = ICO;
            btn.title = 'Baixar';
            btn.setAttribute('aria-label', 'Baixar');
            const ref = topo.querySelector('.wf-fs-btn') || topo.querySelector('.mini-btn');
            if (ref && ref.parentNode) ref.parentNode.insertBefore(btn, ref); else topo.appendChild(btn);
        });
    }

    let agendado = false;
    function agendar() {
        if (agendado) return;
        agendado = true;
        requestAnimationFrame(() => { agendado = false; injetar(); });
    }

    ['pointerdown', 'mousedown', 'touchstart'].forEach(tipo => {
        document.addEventListener(tipo, e => {
            if (e.target.closest && e.target.closest('.wf-dl-btn')) e.stopPropagation(); // não inicia o arrastar do cabeçalho
        }, true);
    });
    document.addEventListener('click', e => {
        const btn = e.target.closest && e.target.closest('.wf-dl-btn');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        baixar(btn.closest('.wf-item'));
    }, true);

    const st = document.createElement('style');
    st.textContent = `
        .wf-dl-btn svg { width: .9rem; height: .9rem; display: block; }
        .wf-quadro.wf-soltar { outline: 2px dashed var(--prim); outline-offset: -4px; }
        .wf-quadro.wf-soltar::after {
            content: "Solte o arquivo para criar o bloco"; position: absolute; inset: 0; z-index: 60; pointer-events: none;
            display: flex; align-items: center; justify-content: center; font-weight: 600; color: #fff; background: rgba(0, 0, 0, .35);
        }`;
    document.head.appendChild(st);

    function iniciar() {
        injetar();
        new MutationObserver(agendar).observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();

    window.WFArquivos = { baixar, soltar };
})();