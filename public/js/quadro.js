/* Work Flower · motor do quadro (compartilhado)
   Arrastar, redimensionar, cor, blocos livres (texto, tabela, lista, imagem, mapa mental) e salvamento.
   Usado por carteira.js. O corpo.js pode migrar para cá depois, sem pressa. */
(function () {
    'use strict';

    const pad = n => String(n).padStart(2, '0');
    const iso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const hoje = () => iso(new Date());
    const agora = () => { const d = new Date(); return `${pad(d.getHours())}:${pad(d.getMinutes())}`; };
    const uid = () => Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    const r1 = n => Math.round((Number(n) || 0) * 10) / 10;
    const num = v => { const n = parseFloat(String(v).replace(',', '.')); return isFinite(n) ? n : 0; };
    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
    const snap = v => Math.round(v / 8) * 8;
    const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
    const dataBR = s => { const [, m, d] = s.split('-'); return `${d}/${m}`; };

    /* ---------- bloco de imagem: só o cabeçalho e a imagem inteira (vale para Corpo e Mente) ---------- */
    (function () {
        const st = document.createElement('style');
        st.textContent = `
            .wf-corpo:has(.wf-img-bloco.com-img) { padding: 0 !important; overflow-x: hidden; overflow-y: auto; }
            .wf-corpo:has(.wf-img-bloco.com-img) > div { height: auto !important; min-height: 0 !important; padding: 0 !important; }
            .wf-img-bloco img { display: block; width: 100%; height: auto; }
            .wf-img-vazio {
                display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
                min-height: 7rem; height: 100%; padding: 1rem; text-align: center;
                border: 1px dashed var(--linha); border-radius: .5rem;
            }
            /* pastas */
            .wf-pasta { display: flex; flex-direction: column; gap: .6rem; height: 100%; min-height: 0; }
            .wf-pasta-topo { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
            .wf-pasta-ico { font-size: 1.9rem; line-height: 1; }
            .wf-pasta-info { flex: 1; min-width: 6rem; font-size: .8rem; color: var(--tinta-2); }
            .wf-pasta-lista { list-style: none; margin: 0; padding: 0; display: grid; gap: .35rem;
                              grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr)); overflow: auto; min-height: 0; }
            .wf-pasta-lista button { width: 100%; display: flex; align-items: center; gap: .45rem; text-align: left;
                                     padding: .4rem .55rem; border-radius: .5rem; border: 1px solid var(--linha);
                                     background: color-mix(in srgb, var(--prim) 6%, transparent); font-size: .8rem; }
            .wf-pasta-lista button:hover { border-color: var(--prim); }
            .wf-pasta-lista span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .wf-pasta-vazia { font-size: .8rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .5rem;
                              padding: .75rem; text-align: center; }
            .wf-pasta-barra { position: sticky; top: 4.5rem; z-index: 25; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
                              padding: .55rem .75rem; margin-bottom: .75rem; border-radius: .75rem;
                              background: color-mix(in srgb, var(--prim) 16%, var(--superficie, #111)); border: 1px solid var(--prim); }
            .wf-pasta-trilha { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; font-size: .85rem; flex: 1; min-width: 0; }
            .wf-pasta-trilha button { padding: .15rem .4rem; border-radius: .35rem; }
            .wf-pasta-trilha button:hover { background: color-mix(in srgb, var(--prim) 25%, transparent); }
            .wf-pasta-trilha b { font-weight: 600; }
            .wf-sel-barra { position: fixed; left: 50%; bottom: calc(1rem + var(--seg-b, 0px)); transform: translateX(-50%); z-index: 45;
                            display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .5rem;
                            max-width: calc(100vw - 1.5rem); padding: .55rem .75rem; border-radius: .85rem;
                            background: var(--superficie, #111); border: 1px solid var(--prim); box-shadow: 0 12px 30px rgba(0,0,0,.45); }
            .wf-sel-barra select { max-width: 11rem; }
            .wf-item.wf-piscar { animation: wf-piscar 1.3s ease-out; }
            @keyframes wf-piscar { 0%, 60% { box-shadow: 0 0 0 4px var(--prim); } 100% { box-shadow: 0 0 0 0 transparent; } }`;
        document.head.appendChild(st);
    })();

    /* ---------- cartão ativo: ?embed=secao/id mostra só aquele cartão da página ---------- */
    // pasta dos scripts (o editor de imagem é carregado só quando alguém abre)
    const BASE_JS = (() => { const s = document.currentScript && document.currentScript.src; return s ? s.replace(/quadro\.js(\?.*)?$/, '') : '/js/'; })();
    const VERSAO_JS = (() => { const s = document.currentScript && document.currentScript.src; const m = s && /\?v=([^&]+)/.exec(s); return m ? m[1] : ''; })();
    let editorCarregando = null;
    function carregarEditor() {
        if (window.WFEditorImagem) return Promise.resolve();
        if (!editorCarregando) {
            editorCarregando = new Promise((ok, falha) => {
                const s = document.createElement('script');
                const v = document.querySelector('meta[name="wf-editor-imagem-v"]')?.content || VERSAO_JS;
                s.src = BASE_JS + 'editor-imagem.js' + (v ? '?v=' + v : '');
                s.onload = () => ok(); s.onerror = () => { editorCarregando = null; falha(new Error('editor')); };
                document.head.appendChild(s);
            });
        }
        return editorCarregando;
    }

    const SEG = /^[A-Za-z0-9._-]{1,60}$/;
    const EMBED = (() => {
        const v = new URLSearchParams(location.search).get('embed') || '';
        const i = v.indexOf('/');
        if (i < 1) return null;
        const sec = v.slice(0, i), bid = v.slice(i + 1);
        return SEG.test(sec) && SEG.test(bid) ? { sec, bid } : null;
    })();
    if (EMBED) {
        const st = document.createElement('style');
        st.textContent = `
            html.wf-embed-pendente body { visibility: hidden !important; }
            html.wf-embed, html.wf-embed body { background: transparent !important; overflow: hidden !important; }
            .wf-oculto { display: none !important; }
            .wf-caminho { margin: 0 !important; padding: 0 !important; max-width: none !important; width: auto !important; height: auto !important;
                          min-height: 0 !important; border: 0 !important; position: static !important; transform: none !important; }
            .wf-item.wf-alvo { position: fixed !important; inset: 0 !important; width: 100% !important; height: 100% !important; z-index: 1 !important; }
            .wf-alvo .wf-redim, .wf-alvo .wf-cor, .wf-alvo .wf-topo .mini-btn[title="Salvar na biblioteca"] { display: none !important; }
            .wf-alvo .wf-topo { cursor: default; }
            .wf-embed-vazio { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem;
                              text-align: center; font: 14px/1.4 sans-serif; color: #94a3b8; }`;
        document.head.appendChild(st);
        document.documentElement.classList.add('wf-embed-pendente');
    }

    /** Esconde tudo da página, menos o caminho até o cartão pedido; o cartão passa a ocupar a janela inteira. */
    function mostrarSoAlvo(alvo) {
        document.documentElement.classList.add('wf-embed');
        alvo.classList.add('wf-alvo');
        let n = alvo;
        while (n && n.parentElement && n !== document.documentElement) {
            const pai = n.parentElement;
            [...pai.children].forEach(irmao => { if (irmao !== n) irmao.classList.add('wf-oculto'); });
            pai.classList.add('wf-caminho');
            n = pai;
        }
        document.documentElement.classList.remove('wf-embed-pendente');
    }

    const PALETA = ['#2f6fc7', '#2dd4bf', '#a78bfa', '#fb923c', '#f472b6', '#f2c230', '#94a3b8', '#4ade80'];
    const MM = { w: 188, h: 38 }; // tamanho de um nó do mapa mental

    async function enviar(url, texto, metodo = 'PUT') {
        const r = await fetch(url, {
            method: metodo,
            credentials: 'same-origin',
            keepalive: !texto || texto.length < 50000,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: metodo === 'PUT' ? JSON.stringify({ valor: texto }) : undefined,
        });
        if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
        if (!r.ok) throw new Error(String(r.status));
    }

    /** Reduz a imagem escolhida (máx. 1100 px) para não pesar no banco. */
    function reduzir(arquivo, max = 1100, qualidade = 0.82) {
        return new Promise((ok, falha) => {
            const img = new Image();
            const url = URL.createObjectURL(arquivo);
            img.onload = () => {
                const k = Math.min(1, max / Math.max(img.width, img.height));
                const cv = document.createElement('canvas');
                cv.width = Math.round(img.width * k); cv.height = Math.round(img.height * k);
                const cx = cv.getContext('2d');
                cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height);
                cx.drawImage(img, 0, 0, cv.width, cv.height);
                URL.revokeObjectURL(url);
                ok(cv.toDataURL('image/jpeg', qualidade));
            };
            img.onerror = () => { URL.revokeObjectURL(url); falha(new Error('imagem')); };
            img.src = url;
        });
    }

    /* ---------- formato dos dados de cada tipo de bloco ---------- */
    const TIPOS_COM_DADOS = ['texto', 'tabela', 'lista', 'mapa', 'documento'];

    /** Item da biblioteca salvo sem conteúdo (versão antiga do salvamento gravava `dados` vazio). Não há o que colar. */
    function dadosPerdidos(item) {
        if (!item || !TIPOS_COM_DADOS.includes(item.tipo)) return false;
        const d = item.dados;
        return !d || typeof d !== 'object' || Array.isArray(d) || Object.keys(d).length === 0;
    }

    /** Devolve uma cópia de `dados` com todos os campos que o tipo precisa (aceita null, texto solto e conteúdo antigo). */
    function normalizarDados(tipo, bruto) {
        const d = bruto && typeof bruto === 'object' && !Array.isArray(bruto) ? JSON.parse(JSON.stringify(bruto)) : {};
        const txt = v => (v == null || typeof v === 'object' ? '' : String(v));
        const arr = v => (Array.isArray(v) ? v : (v && typeof v === 'object' ? Object.values(v) : []));
        switch (tipo) {
            case 'texto':
                d.texto = txt(d.texto);
                if (!['p', 'm', 'g'].includes(d.tam)) d.tam = 'm';
                break;
            case 'tabela': {
                let cab = arr(d.cab).map(txt);
                let linhas = arr(d.linhas).map(l => arr(l).map(txt));
                const n = Math.max(cab.length, ...linhas.map(l => l.length), 1);
                cab = Array.from({ length: n }, (_, j) => cab[j] ?? '');
                linhas = linhas.map(l => Array.from({ length: n }, (_, j) => l[j] ?? ''));
                d.cab = cab;
                d.linhas = linhas.length ? linhas : [cab.map(() => '')];
                break;
            }
            case 'lista':
                d.itens = arr(d.itens)
                    .map(i => (typeof i === 'string' ? { t: i } : i))
                    .filter(i => i && typeof i === 'object')
                    .map(i => ({ ...i, id: i.id ? String(i.id) : uid(), t: txt(i.t ?? i.texto), f: !!i.f }));
                break;
            case 'mapa': {
                const nos = arr(d.nos).filter(n => n && typeof n === 'object')
                    .map(n => ({ ...n, id: n.id ? String(n.id) : uid(), t: txt(n.t), x: Number(n.x) || 0, y: Number(n.y) || 0, pai: n.pai || null, cor: n.cor || PALETA[0] }));
                d.nos = nos.length ? nos : [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }];
                break;
            }
            case 'documento': {
                const secoes = arr(d.secoes).filter(x => x && typeof x === 'object')
                    .map(x => ({ ...x, id: x.id ? String(x.id) : uid(), t: txt(x.t), texto: txt(x.texto) }));
                d.secoes = secoes.length ? secoes : [{ id: uid(), t: '', texto: '' }];
                d.modelo = d.modelo || 'branco'; d.layout = d.layout || 'coluna'; d.fonte = d.fonte || 'sans'; d.cor = d.cor || PALETA[0];
                break;
            }
            case 'imagem':
                d.ajuste = d.ajuste || 'cover';
                d.legenda = txt(d.legenda);
                d.prop = Number(d.prop) > 0 ? Number(d.prop) : 0; // altura ÷ largura da imagem
                break;
        }
        return d;
    }

    /* ---------- converter um cartão fixo em conteúdo estático para a biblioteca ---------- */
    function textoCelula(c) {
        const f = c.querySelector('input,textarea,select');
        if (f) return f.tagName === 'SELECT' ? (f.selectedOptions[0]?.textContent || '').trim() : String(f.value || '').trim();
        const k = c.cloneNode(true);
        k.querySelectorAll('button,svg').forEach(x => x.remove());
        return k.textContent.replace(/\s+/g, ' ').trim();
    }

    /** Lê o texto visível de um cartão (inclui valores de campos), com quebras de linha nos blocos. */
    function textoDe(raiz) {
        const out = [];
        const BLOCO = /^(DIV|P|LI|TR|H1|H2|H3|UL|SECTION|LABEL)$/;
        (function ir(n) {
            if (n.nodeType === 3) { const t = n.textContent.replace(/\s+/g, ' ').trim(); if (t) out.push(t + ' '); return; }
            if (n.nodeType !== 1) return;
            const tag = n.tagName.toUpperCase();
            if (['BUTTON', 'SVG', 'SCRIPT', 'STYLE', 'TEMPLATE'].includes(tag)) return;
            if (n.hidden || n.style.display === 'none') return;
            if (tag === 'INPUT' || tag === 'TEXTAREA') { if (!['checkbox', 'file', 'color', 'range'].includes(n.type) && n.value) out.push(n.value + ' '); return; }
            if (tag === 'SELECT') { const o = n.selectedOptions[0]; if (o && o.value) out.push(o.textContent.trim() + ' '); return; }
            n.childNodes.forEach(ir);
            if (BLOCO.test(tag)) out.push('\n');
        })(raiz);
        return out.join('').replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    /** Decide o formato da cópia: tabela, imagem ou texto. Devolve null se não houver nada para guardar. */
    function cartaoParaBloco(corpo) {
        const t = corpo.querySelector('table');
        if (t) {
            let cab = [...t.querySelectorAll('thead th')].map(textoCelula);
            let linhas = [...t.querySelectorAll('tbody tr')].map(tr => [...tr.children].map(textoCelula));
            const n = Math.max(cab.length, ...linhas.map(l => l.length), 1);
            cab = Array.from({ length: n }, (_, j) => cab[j] || '');
            linhas = linhas.map(l => Array.from({ length: n }, (_, j) => l[j] || ''));
            // tira colunas sem título e sem conteúdo (botões, gráficos)
            const manter = cab.map((c, j) => c !== '' || linhas.some(l => l[j] !== ''));
            cab = cab.filter((_, j) => manter[j]);
            linhas = linhas.map(l => l.filter((_, j) => manter[j]));
            if (cab.length) return { tipo: 'tabela', dados: { cab, linhas: linhas.length ? linhas : [cab.map(() => '')] } };
        }
        const img = corpo.querySelector('img');
        if (img && /^data:image\//.test(img.src)) return { tipo: 'imagem', dados: { ajuste: 'cover', legenda: '' }, imagem: img.src };
        const texto = textoDe(corpo).slice(0, 5000);
        return texto ? { tipo: 'texto', dados: { texto, tam: 'm' } } : null;
    }

    /* ---------- várias pessoas no mesmo workflow: juntar mudanças e aplicar as dos outros ---------- */
    const igual = (a, b) => a === b || JSON.stringify(a) === JSON.stringify(b);
    const ehObj = v => v !== null && typeof v === 'object' && !Array.isArray(v);
    const temIds = arr => Array.isArray(arr) && arr.length > 0 && arr.every(x => ehObj(x) && x.id != null);
    const clonar = v => (v === undefined ? undefined : JSON.parse(JSON.stringify(v)));

    /**
     * Junta três versões do estado: base (a última que os dois viram), a minha e a deles.
     * Quem mudou um campo vence; se os dois mudaram o mesmo campo, a minha vence (é a gravação mais nova).
     * Listas de objetos com "id" (blocos, abas, itens, nós) são juntadas item a item: cada pessoa pode
     * criar, editar e excluir itens diferentes sem perder o que a outra fez.
     */
    function juntarEstados(base, meu, deles) {
        if (igual(meu, base)) return clonar(deles);
        if (igual(deles, base) || igual(meu, deles)) return meu;
        if (ehObj(meu) && ehObj(deles)) {
            const b = ehObj(base) ? base : {}, out = {};
            new Set([...Object.keys(meu), ...Object.keys(deles)]).forEach(k => {
                const emB = k in b, emM = k in meu, emD = k in deles;
                if (!emM && emB && igual(deles[k], b[k])) return;   // eu apaguei e eles não mexeram
                if (!emD && emB && igual(meu[k], b[k])) return;     // eles apagaram e eu não mexi
                if (!emM) { out[k] = clonar(deles[k]); return; }
                if (!emD) { out[k] = meu[k]; return; }
                out[k] = juntarEstados(b[k], meu[k], deles[k]);
            });
            return out;
        }
        if ((temIds(meu) || temIds(deles)) && Array.isArray(meu) && Array.isArray(deles)) {
            const porId = arr => new Map((Array.isArray(arr) ? arr : []).filter(ehObj).map(x => [String(x.id), x]));
            const B = porId(base), M = porId(meu), D = porId(deles), out = [];
            deles.forEach(x => {
                const id = String(x.id);
                if (M.has(id)) out.push(juntarEstados(B.get(id), M.get(id), x));
                else if (!B.has(id) || !igual(x, B.get(id))) out.push(clonar(x)); // novo deles, ou eu apaguei mas eles editaram
            });
            meu.forEach((x, i) => {
                const id = String(x.id);
                if (D.has(id)) return;
                if (B.has(id) && igual(x, B.get(id))) return;  // eles apagaram e eu não mexi
                // novo meu (ou eu editei o que eles apagaram): entra perto de onde estava
                let antes = -1;
                for (let p = i - 1; p >= 0 && antes < 0; p--) antes = out.findIndex(o => String(o.id) === String(meu[p].id));
                out.splice(antes + 1, 0, x);
            });
            return out;
        }
        return meu;
    }

    /**
     * Coloca o estado novo dentro do objeto atual sem trocar os objetos que continuam existindo (mesmo id):
     * cartões abertos (editor de texto, tabelas, tabletop) seguem apontando para o objeto certo.
     */
    function aplicarNoLugar(alvo, novo) {
        if (!ehObj(alvo) || !ehObj(novo)) return novo;
        Object.keys(alvo).forEach(k => { if (!(k in novo)) delete alvo[k]; });
        Object.keys(novo).forEach(k => {
            const a = alvo[k], n = novo[k];
            if (ehObj(a) && ehObj(n)) aplicarNoLugar(a, n);
            else if (Array.isArray(a) && Array.isArray(n) && temIds(n)) {
                const velhos = new Map(a.filter(ehObj).map(x => [String(x.id), x]));
                const lista = n.map(x => { const v = velhos.get(String(x.id)); return v && ehObj(x) ? aplicarNoLugar(v, x) : x; });
                if (!igual(a.map(x => x && x.id), lista.map(x => x && x.id)) || lista.some((x, i) => x !== a[i])) a.splice(0, a.length, ...lista);
            } else if (!igual(a, n)) alvo[k] = n;
        });
        return alvo;
    }
    const metaWF = n => document.querySelector(`meta[name="${n}"]`)?.content || '';

    /* ---------- encaixe dos cartões (organização automática) ---------- */
    const ORG_VERSAO = 2;            // sobe quando o algoritmo muda: layouts automáticos antigos são refeitos uma vez
    const ORG_CHAVE = 'wf-organizar-auto';
    const ORG_FOLGA = 16, ORG_INF = 1e7;

    /** Organização automática ligada neste navegador? (padrão: ligada) */
    // A escolha fica na conta (preferências, window.WFPrefs do layout); o navegador guarda uma cópia para quando não houver conta.
    function autoLigado() {
        const p = window.WFPrefs && window.WFPrefs.ler();
        if (p && typeof p.organizarAuto === 'boolean') return p.organizarAuto;
        try { return localStorage.getItem(ORG_CHAVE) !== '0'; } catch { return true; }
    }
    function definirAuto(ligado) {
        try { localStorage.setItem(ORG_CHAVE, ligado ? '1' : '0'); } catch { /* navegador sem armazenamento */ }
        if (window.WFPrefs) window.WFPrefs.salvar({ organizarAuto: !!ligado });
        window.dispatchEvent(new CustomEvent('wf-organizar-auto', { detail: { ligado: !!ligado } }));
    }

    /** Recorta o retângulo ocupado `p` de cada espaço livre que ele toca e descarta os espaços contidos em outros. */
    function recortarLivres(livres, p) {
        const out = [];
        for (const f of livres) {
            if (p.x >= f.x + f.w || p.x + p.w <= f.x || p.y >= f.y + f.h || p.y + p.h <= f.y) { out.push(f); continue; }
            if (p.x > f.x) out.push({ x: f.x, y: f.y, w: p.x - f.x, h: f.h });
            if (p.x + p.w < f.x + f.w) out.push({ x: p.x + p.w, y: f.y, w: f.x + f.w - p.x - p.w, h: f.h });
            if (p.y > f.y) out.push({ x: f.x, y: f.y, w: f.w, h: p.y - f.y });
            if (p.y + p.h < f.y + f.h) out.push({ x: f.x, y: p.y + p.h, w: f.w, h: f.y + f.h - p.y - p.h });
        }
        const uteis = out.filter(a => a.w >= 136 && a.h >= 96); // menor cartão possível + folga
        return uteis.filter((a, i) => !uteis.some((b, j) => j !== i
            && b.x <= a.x && b.y <= a.y && b.x + b.w >= a.x + a.w && b.y + b.h >= a.y + a.h
            && (j < i || b.w * b.h > a.w * a.h)));
    }

    /**
     * Encaixa os cartões na ordem dada ("retângulos livres"): cada um vai para o espaço livre mais alto,
     * inclusive buracos entre cartões já colocados. Se o cartão for um pouco mais largo que o buraco,
     * ele pode estreitar até a largura mínima dele para caber ali, em vez de cair para baixo de tudo.
     */
    function encaixar(lista, W) {
        const G = ORG_FOLGA;
        let livres = [{ x: 0, y: 0, w: W + G, h: ORG_INF }];
        const pos = [];
        let H = 0, cortes = 0;
        for (const it of lista) {
            let m = null;
            for (const f of livres) {
                let w = it.w;
                if (f.w < w + G) {
                    w = Math.floor((f.w - G) / 8) * 8;
                    if (w < it.minW) continue;
                }
                const h = it.alturaPara ? it.alturaPara(w) : it.h;
                if (f.h < h + G) continue;
                // subir vale muito mais que estreitar: estreitar 1 px "custa" 1,5 px de altura
                const custo = f.y + (it.w - w) * 1.5 + f.x * 0.001;
                if (!m || custo < m.custo) m = { x: f.x, y: f.y, w, h, custo };
            }
            if (!m) m = { x: 0, y: H ? H + G : 0, w: it.w, h: it.h };
            if (m.w < it.w) cortes++;
            livres = recortarLivres(livres, { x: m.x, y: m.y, w: m.w + G, h: m.h + G });
            pos.push({ it, x: m.x, y: m.y, w: m.w, h: m.h });
            H = Math.max(H, m.y + m.h);
        }
        return { pos, H, cortes };
    }

    /** Fecha as sobras: alarga cartões até o vizinho (ou a borda) e estica as colunas até o fim do quadro. */
    function esticar(pos, W, H) {
        const G = ORG_FOLGA;
        pos.slice().sort((a, b) => (b.x + b.w) - (a.x + a.w)).forEach(p => {
            if (p.it.fixo) return;
            let lim = W;
            pos.forEach(o => { if (o !== p && o.x >= p.x + p.w && o.y < p.y + p.h + G && p.y < o.y + o.h + G) lim = Math.min(lim, o.x - G); });
            const ext = lim - (p.x + p.w);
            if (ext > 0 && ext <= p.w * 0.5) p.w += ext;
        });
        pos.slice().sort((a, b) => (b.y + b.h) - (a.y + a.h)).forEach(p => {
            if (p.it.fixo) return;
            let lim = H;
            pos.forEach(o => { if (o !== p && o.y >= p.y + p.h && o.x < p.x + p.w + G && p.x < o.x + o.w + G) lim = Math.min(lim, o.y - G); });
            const ext = lim - (p.y + p.h);
            if (ext > 0 && ext <= p.h) p.h += ext;
        });
    }

    /**
     * Testa várias ordens (mais altos, maior área, mais largos, maior lado e a ordem atual da tela)
     * e fica com o quadro mais baixo; em empate, o que estreita menos cartões e o que mantém a ordem atual.
     */
    function planejar(itens, W) {
        const ordens = [
            (a, b) => (b.h - a.h) || (b.w - a.w),
            (a, b) => (b.w * b.h - a.w * a.h) || (b.h - a.h),
            (a, b) => (b.w - a.w) || (b.h - a.h),
            (a, b) => (Math.max(b.w, b.h) - Math.max(a.w, a.h)) || (b.h - a.h),
            (a, b) => a.ordem - b.ordem,
        ];
        let melhor = null;
        ordens.forEach((cmp, k) => {
            const lista = itens.slice().sort((a, b) => cmp(a, b) || String(a.id).localeCompare(String(b.id)));
            const r = encaixar(lista, W);
            r.estavel = k === ordens.length - 1;
            const ganha = !melhor
                || r.H < melhor.H - 24
                || (Math.abs(r.H - melhor.H) <= 24 && (r.cortes < melhor.cortes || (r.cortes === melhor.cortes && r.estavel && r.H <= melhor.H + 8)));
            if (ganha) melhor = r;
        });
        esticar(melhor.pos, W, melhor.H);
        return melhor;
    }

    /** Propriedades de interface que o motor precisa no componente Alpine. */
    function estadoUI() {
        return {
            larg: 1088, col: 1088, ox: 0, salvo: 'ok', msg: '', zTop: 10, arrastando: null, menuBloco: false, destaque: null, _v: 0, embutido: !!EMBED,
            // pastas: pasta aberta em cada aba (só na tela, não é salvo), cartões selecionados (Ctrl+clique) e o último cartão clicado
            pasta: {}, sel: [], atual: null, alvoPasta: null,
            // workflow compartilhado: abas com mudanças de outra pessoa que ainda não foram vistas (bolinha no botão)
            novidades: {},
        };
    }

    /** Métodos do motor. PADRAO = posição inicial dos itens por aba (área de 1088 px). */
    function metodos(PADRAO) {
        return {
            /* ---------- avisos e salvamento ---------- */
            aviso(txt) { this.msg = txt; clearTimeout(this._m); this._m = setTimeout(() => { this.msg = ''; }, 2600); },
            ir(sec) { this.aba = sec; },
            rotuloSalvo() {
                return { ok: 'Tudo salvo', pendente: 'Alterações…', salvando: 'Salvando…', erro: 'Erro ao salvar. Tentando de novo' }[this.salvo];
            },
            agendar() {
                if (this._silencio) return; // mudança que veio de outra pessoa: não precisa gravar de volta
                this._v++;
                this.salvo = 'pendente';
                clearTimeout(this._t);
                this._t = setTimeout(() => this.gravarEstado(), 800);
            },
            /**
             * Grava o estado dizendo em cima de qual versão ele foi feito (X-WF-Versao). Se outra pessoa do
             * workflow gravou antes (409), junta as duas versões (juntarEstados) e grava de novo.
             */
            async gravarEstado() {
                clearTimeout(this._t);
                const v = this._v;
                this.salvo = 'salvando';
                const texto = JSON.stringify(this.estado);
                try {
                    const cab = {
                        'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    };
                    if (this._versao !== undefined) cab['X-WF-Versao'] = this._versao;
                    const r = await fetch(this.cfg.url + '/estado', {
                        method: 'PUT', credentials: 'same-origin', keepalive: texto.length < 50000,
                        headers: cab, body: JSON.stringify({ valor: texto }),
                    });
                    if (r.status === 401 || r.status === 419) { location.reload(); return; }
                    if (r.status === 409) {
                        const j = await r.json();
                        this.juntarComOutros(j.valor, j.versao);
                        this.salvo = 'pendente';
                        this._t = setTimeout(() => this.gravarEstado(), 300);
                        return;
                    }
                    if (r.status === 403) { this.salvo = 'erro'; this.aviso('Você não tem mais acesso a este workflow. Recarregue a página.'); return; }
                    if (!r.ok) throw new Error(String(r.status));
                    const j = await r.json().catch(() => ({}));
                    if (j && typeof j.versao === 'string') { this._versao = j.versao; this._baseTexto = texto; }
                    if (v === this._v) this.salvo = 'ok';
                } catch {
                    this.salvo = 'erro';
                    this._t = setTimeout(() => this.gravarEstado(), 10000);
                }
            },
            /** Outra pessoa gravou antes: junta (base, o meu agora, o dela) e fica na versão dela como base. */
            juntarComOutros(valorDeles, versaoDeles) {
                let base = {}, deles = {};
                try { base = JSON.parse(this._baseTexto || '{}'); } catch { /* base desconhecida */ }
                try { deles = JSON.parse(valorDeles || '{}'); } catch { return; }
                const junto = juntarEstados(base, JSON.parse(JSON.stringify(this.estado)), deles);
                this.aplicarSemGravar(junto);
                this._versao = versaoDeles; this._baseTexto = valorDeles;
                this.aviso('Outra pessoa também mexeu neste workflow agora. As mudanças das duas foram juntadas.');
            },
            /** Troca o estado pelo novo sem disparar uma gravação (e sem recriar os objetos que já existem). */
            aplicarSemGravar(novo) {
                this._silencio = true;
                aplicarNoLugar(this.estado, novo);
                setTimeout(() => { this._silencio = false; }, 0);
            },
            /** Nome de uma aba desta página (para os avisos). */
            nomeAba(sec) {
                const lista = Array.isArray(this.abas) ? this.abas : (this.estado && Array.isArray(this.estado.abas) ? this.estado.abas : []);
                const a = lista.find(x => (typeof x === 'string' ? x : x.id) === sec);
                return (a && typeof a === 'object' && a.nome) || sec;
            },
            /** Abas em que algo mudou entre dois estados (cartões da aba ou posições). */
            abasMudadas(antes, depois) {
                const por = e => {
                    const m = {};
                    (e.blocos || []).forEach(b => { (m[b.secao] ??= []).push(b); });
                    return m;
                };
                const a = por(antes), d = por(depois), la = antes.layout || {}, ld = depois.layout || {};
                const secs = new Set([...Object.keys(a), ...Object.keys(d), ...Object.keys(la), ...Object.keys(ld)]);
                return [...secs].filter(s => s && (JSON.stringify(a[s] || []) !== JSON.stringify(d[s] || []) || JSON.stringify(la[s] || {}) !== JSON.stringify(ld[s] || {})));
            },
            /**
             * Workflow com mais de uma pessoa: a cada 2 s pergunta ao servidor se o estado mudou (e imagens novas);
             * sem mudança o servidor responde sem ler o estado (marca no cache), então a conferência é leve.
             * Mudança dos outros entra direto na tela se não há nada meu esperando para gravar; se houver, a gravação junta.
             * Mudança numa aba que não é a aberta: bolinha no botão da aba e um aviso discreto (js/novidades.js).
             */
            prepararSincronia() {
                if (this._sincPronta || EMBED) return;
                this._sincPronta = true;
                if (metaWF('wf-versao') !== '' || document.querySelector('meta[name="wf-versao"]')) {
                    this._versao = metaWF('wf-versao');
                    this._baseTexto = (this.cfg && this.cfg.dados && typeof this.cfg.dados.estado === 'string') ? this.cfg.dados.estado : JSON.stringify(this.estado);
                }
                const url = metaWF('wf-sincronia-url');
                if (!url) return;
                const conferir = async () => {
                    if (this._sincOcupado || document.hidden) return;
                    this._sincOcupado = true;
                    try {
                        const q = `?versao=${encodeURIComponent(this._versao || '')}` + (this._desde ? `&desde=${encodeURIComponent(this._desde)}` : '');
                        const r = await fetch(url + q, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (r.status === 403) { clearInterval(this._sincT); this.aviso('Você não tem mais acesso a este workflow.'); return; }
                        if (!r.ok) return;
                        const j = await r.json();
                        this._desde = j.agora;
                        Object.entries(j.imagens || {}).forEach(([k, v]) => {
                            if (!k.startsWith('img.')) return;
                            try { this.imagens[k.slice(4)] = JSON.parse(v); } catch { /* imagem corrompida */ }
                        });
                        if (typeof j.estado === 'string' && j.versao !== this._versao && this.salvo === 'ok' && !this.arrastando) {
                            let deles; try { deles = JSON.parse(j.estado); } catch { return; }
                            let antes = {}; try { antes = JSON.parse(JSON.stringify(this.estado)); } catch { /* sem comparação */ }
                            this.aplicarSemGravar(deles);
                            this._versao = j.versao; this._baseTexto = j.estado;
                            const outras = this.abasMudadas(antes, deles).filter(s => s !== this.aba);
                            outras.forEach(s => { this.novidades[s] = true; });
                            if (outras.length && window.wfAvisoSutil) {
                                const quem = j.por_nome || 'Outra pessoa';
                                const s = outras[0];
                                window.wfAvisoSutil(outras.length === 1
                                    ? `${quem} mudou a aba ${this.nomeAba(s)}.`
                                    : `${quem} mudou ${outras.length} abas (${outras.map(x => this.nomeAba(x)).join(', ')}).`,
                                { rotulo: 'Ver', acao: () => { this.aba = s; }, chave: 'abas' });
                            }
                        }
                    } catch { /* sem rede: tenta de novo na próxima */ } finally { this._sincOcupado = false; }
                };
                const iniciar = () => {
                    if (this._sincT) return;
                    this._sincT = setInterval(conferir, 2000);
                    // abriu a aba: a bolinha de novidade some
                    this.$watch('aba', a => { if (this.novidades[a]) delete this.novidades[a]; });
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) conferir(); });
                    setTimeout(conferir, 1500);
                };
                if (metaWF('wf-sincronizar') === '1') iniciar();
                // o dono acabou de adicionar a primeira pessoa (compartilhar.js): começa a conferir sem recarregar
                window.addEventListener('wf-compartilhado', iniciar);
            },

            /* ---------- quadro: medir, arrastar, redimensionar, cor ---------- */
            initQuadro() {
                // avisa a biblioteca (configurações) que esta página aceita blocos colados
                window.WFQuadro.ativo = true;
                // tipos de bloco que ESTA página sabe desenhar (a Mente tem "documento", as outras não)
                window.WFQuadro.tipos = [...(this.tiposBloco || []).map(t => t.id), 'vivo'];
                if (!this._colarOuvindo) {
                    this._colarOuvindo = true;
                    window.addEventListener('wf-colar', e => {
                        const ok = this.colarBloco(e.detail);
                        // avisa quem pediu (ex.: Histórico) se o bloco realmente voltou
                        if (e.detail && typeof e.detail === 'object') e.detail.ok = ok !== false;
                    });
                }
                if (EMBED) this.prepararEmbed();
                if (!this._orgOuvindo && !EMBED) {
                    this._orgOuvindo = true;
                    // aba "Atalhos" das configurações: liga/desliga a organização automática e "Reorganizar agora" (Alt+R)
                    window.addEventListener('wf-organizar-auto', e => { if (e.detail && e.detail.ligado && !this.arrastando) this.autoOrganizar(); });
                    window.addEventListener('wf-reorganizar', () => { if (!this.arrastando) this.reorganizar(); });
                    // teclas de atalho personalizáveis (layout): ações do quadro chegam por este evento
                    window.addEventListener('wf-atalho', e => { if (e.detail && e.detail.id) this.executarAtalho(e.detail.id, e.detail); });
                }
                this.prepararPastas();
                this.prepararSincronia();
                this.$nextTick(() => {
                    const area = this.$refs.area, pai = area.parentElement;
                    const medir = () => {
                        this.col = pai.clientWidth || 1088;
                        if (this.empilhado()) {
                            area.style.width = ''; area.style.marginLeft = '';
                            this.ox = 0; this.larg = this.col;
                            return;
                        }
                        const janela = document.documentElement.clientWidth;
                        const esq = Math.round(pai.getBoundingClientRect().left);
                        area.style.width = janela + 'px';
                        area.style.marginLeft = (-esq) + 'px';
                        this.ox = esq; this.larg = janela;
                    };
                    medir();
                    const ro = new ResizeObserver(() => {
                        const antes = this.larg;
                        medir();
                        // só a LARGURA da janela importa (a altura do quadro muda a cada arraste e não deve reorganizar nada):
                        // se os cartões não couberem mais, arruma de novo, sem atrapalhar quem está arrastando
                        if (this.larg === antes) return;
                        clearTimeout(this._ao);
                        this._ao = setTimeout(() => { if (!this.arrastando) this.autoOrganizar(); }, 500);
                    });
                    ro.observe(pai);
                    ro.observe(document.documentElement);

                    // ao entrar na página: cartões organizados por tamanho, sem sobreposição, no tamanho desta tela.
                    // Imagens antigas mudam a altura do cartão ao carregar; depois disso a aba é conferida de novo,
                    // para uma imagem que cresceu não ficar por cima do cartão de baixo.
                    const conferirAba = sec => { if (autoLigado() && !this.arrastando && this.temConflito(sec) && this.organizar(sec)) this.aviso('Cartões reorganizados para caber a imagem.'); };
                    this.autoOrganizar();
                    this.corrigirImagens(this.aba).then(n => { if (n) conferirAba(this.aba); });
                    this.$watch('aba', v => this.$nextTick(() => this.corrigirImagens(v).then(n => { if (n) conferirAba(v); })));
                });
            },
            /** Modo cartão ativo (dentro de um iframe): abre a aba certa e mostra só o cartão pedido. */
            prepararEmbed() {
                const procurar = () => document.querySelector(`.wf-item[data-sec="${EMBED.sec}"][data-bid="${EMBED.bid}"]`);
                let tentativas = 0;
                // cartão guardado numa pasta: abre a pasta dele para o cartão aparecer
                const bl = (this.estado.blocos || []).find(x => x.id === EMBED.bid);
                const dentro = bl ? this.pastaValida(bl) : null;
                if (dentro) this.pasta = { ...this.pasta, [EMBED.sec]: dentro };
                const t = setInterval(() => {
                    this.aba = EMBED.sec;
                    const alvo = procurar();
                    if (alvo) { clearInterval(t); mostrarSoAlvo(alvo); return; }
                    if (++tentativas > 50) {
                        clearInterval(t);
                        const m = document.createElement('div');
                        m.className = 'wf-embed-vazio';
                        m.textContent = 'Este cartão não existe mais na página original.';
                        document.documentElement.appendChild(m);
                    }
                }, 100);
            },
            /** Endereço do cartão ativo de um bloco colado (só caminhos internos /areas/...). */
            urlVivo(b) {
                const d = b.dados || {};
                if (!/^\/areas\/[a-z]+$/.test(d.pagina || '') || !SEG.test(d.sec || '') || !SEG.test(d.bid || '')) return 'about:blank';
                return `${d.pagina}?embed=${encodeURIComponent(d.sec + '/' + d.bid)}`;
            },
            empilhado() { return window.matchMedia('(max-width: 899px)').matches; },
            maiorZ() {
                let z = 10;
                Object.values(this.estado.layout).forEach(sec => Object.values(sec).forEach(l => { z = Math.max(z, l.z || 0); }));
                return z;
            },
            base(sec, id) {
                const p = PADRAO[sec]?.[id] || { x: 0, y: 0, w: 360, h: 260 };
                const f = this.col > 0 && this.col < 1088 ? this.col / 1088 : 1;
                return { x: Math.round(p.x * f), y: p.y, w: Math.round(p.w * f), h: p.h };
            },
            rect(sec, id) { return this.estado.layout[sec]?.[id] || this.base(sec, id); },
            caixa(sec, id) {
                const l = this.rect(sec, id);
                const w = Math.min(l.w, this.larg);
                const x = clamp(l.x, -this.ox, Math.max(-this.ox, this.larg - this.ox - w));
                return `--x:${x + this.ox}px;--y:${l.y}px;--w:${w}px;--h:${l.h}px;z-index:${l.z || 1};--wc:${l.cor || 'var(--prim)'}`;
            },
            entrada(sec, id) {
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                const L = this.estado.layout[sec];
                if (!L[id]) L[id] = this.base(sec, id);
                return L[id];
            },
            /** Altura ocupada pelos cartões que aparecem agora na aba (com uma pasta aberta, só os cartões dela). */
            alturaConteudo(sec) {
                let alt = 0;
                this.cartoesDe(sec).forEach(id => { const l = this.rect(sec, id); alt = Math.max(alt, l.y + l.h); });
                return alt;
            },
            alturaQuadro(sec) { return Math.max(360, this.alturaConteudo(sec) + 40); },
            frente(sec, id) {
                if (EMBED) return;
                this.atual = id; // último cartão clicado: é nele que agem os atalhos (excluir, duplicar, tela cheia…)
                const l = this.rect(sec, id);
                if ((l.z || 1) >= this.zTop) return;
                this.entrada(sec, id).z = ++this.zTop;
            },
            arrastar(e, sec, id) {
                if (EMBED) return;
                if (e.button !== 0 || this.empilhado()) return;
                if (e.target.closest('button,input,select,textarea,label,a')) return;
                e.preventDefault();
                const l = this.entrada(sec, id);
                const larg = this.larg, ox = this.ox;
                const x0 = e.clientX, y0 = e.clientY, lx = l.x, ly = l.y;
                this.arrastando = id;
                // soltar em cima de uma pasta guarda o cartão nela; soltar na barra da pasta aberta tira o cartão dela
                const ehBloco = this.estado.blocos.some(b => b.id === id);
                let destino;
                const mover = ev => {
                    l.x = clamp(snap(lx + ev.clientX - x0), -ox, Math.max(-ox, larg - ox - Math.min(l.w, larg)));
                    l.y = Math.max(0, snap(ly + ev.clientY - y0));
                    if (ehBloco) {
                        destino = this.destinoSob(ev.clientX, ev.clientY, sec, id);
                        const alvo = destino ? destino.pasta || 'sair' : null;
                        if (alvo !== this.alvoPasta) { this.alvoPasta = alvo; this.atualizarCssPastas(); }
                    }
                };
                const fim = () => {
                    this.arrastando = null;
                    if (destino && ehBloco) {
                        // cartões selecionados junto com este vão todos
                        const ids = this.sel.includes(id) ? [...this.sel] : [id];
                        l.x = lx; l.y = ly;
                        this.moverParaPasta(sec, ids, destino.pasta);
                    }
                    destino = null;
                    if (this.alvoPasta) { this.alvoPasta = null; this.atualizarCssPastas(); }
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            redimensionar(e, sec, id, minw, minh) {
                if (EMBED) return;
                if (this.empilhado()) return;
                const l = this.entrada(sec, id);
                const larg = this.larg, ox = this.ox;
                const x0 = e.clientX, y0 = e.clientY, w0 = l.w, h0 = l.h;
                // bloco de imagem: a proporção fica travada, para nunca sobrar espaço nem cortar
                const bl = this.estado.blocos.find(x => x.id === id);
                const prop = bl && bl.tipo === 'imagem' && this.imagens[id] ? Number(bl.dados.prop) || 0 : 0;
                const topo = prop ? this.alturaTopo(id) : 0;
                this.arrastando = id;
                const mover = ev => {
                    l.w = clamp(snap(w0 + ev.clientX - x0), minw, Math.max(minw, larg - ox - l.x));
                    l.h = prop
                        ? Math.ceil((l.w - 2) * prop) + topo + 3
                        : Math.max(minh, snap(h0 + ev.clientY - y0));
                };
                const fim = () => {
                    this.arrastando = null;
                    // tamanho escolhido à mão passa a ser o tamanho do cartão (a organização volta a ele)
                    if (l.w !== w0 || l.h !== h0) { delete l.bw; delete l.bh; }
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
            corDe(sec, id) {
                return this.estado.layout[sec]?.[id]?.cor
                    || getComputedStyle(document.documentElement).getPropertyValue('--prim').trim() || '#2f6fc7';
            },
            setCor(sec, id, cor) { this.entrada(sec, id).cor = cor; },
            /* ---------- organização automática (por tamanho, sem buracos, sem sobreposição, no tamanho da tela) ---------- */
            /** Botão "Reorganizar" (e Alt+R): arruma a aba atual, mesmo com a organização automática desligada. */
            reorganizar() {
                if (EMBED || this.empilhado()) return;
                if (!this.organizar(this.aba)) { this.aviso('Nada para reorganizar nesta aba.'); return; }
                this.marcarTela();
                this.aviso('Layout reorganizado para o tamanho da sua tela.');
            },
            /** Abas que têm cartões (fixos do PADRAO, blocos livres ou layout salvo). */
            secoesQuadro() {
                const s = new Set([...Object.keys(PADRAO || {}), ...Object.keys(this.estado.layout || {})]);
                (this.estado.blocos || []).forEach(b => { if (b.secao) s.add(b.secao); });
                return [...s];
            },
            /** Cartões que existem de fato numa aba (entradas de layout de blocos já apagados são ignoradas). */
            cartoesDe(sec) {
                // com uma pasta aberta, só os cartões dela aparecem (os fixos ficam escondidos)
                const ids = new Set(this.pastaAberta(sec) ? [] : Object.keys(PADRAO[sec] || {}));
                this.blocosDe(sec).forEach(b => ids.add(b.id));
                return [...ids];
            },
            /** Todos os cartões da aba, inclusive os guardados em pastas fechadas. */
            todosIdsDe(sec) {
                const ids = new Set(Object.keys(PADRAO[sec] || {}));
                this.estado.blocos.forEach(b => { if (b.secao === sec) ids.add(b.id); });
                return [...ids];
            },
            /** Nome da vista para a assinatura da organização: a aba, ou a aba + a pasta aberta. */
            chaveVista(sec) {
                const p = this.pastaAberta(sec);
                return p ? sec + '>' + p : sec;
            },
            /** Largura útil e origem (em coordenadas do layout, que começam na coluna central). */
            areaUtil() {
                const larg = this.larg || this.col || 1088, ox = this.ox || 0, margem = 16;
                return { W: Math.max(240, larg - margem * 2), x0: -ox + margem, larg, ox };
            },
            /** true se algum cartão da aba sai da tela ou cobre outro. */
            temConflito(sec) {
                const { W, x0 } = this.areaUtil();
                const r = this.cartoesDe(sec).map(id => this.rect(sec, id));
                const tol = 4;
                for (let i = 0; i < r.length; i++) {
                    const a = r[i];
                    if (a.x < x0 - tol || a.x + Math.min(a.w, W) > x0 + W + tol) return true;
                    for (let j = i + 1; j < r.length; j++) {
                        const b = r[j];
                        if (a.x < b.x + b.w - tol && b.x < a.x + a.w - tol && a.y < b.y + b.h - tol && b.y < a.y + a.h - tol) return true;
                    }
                }
                return false;
            },
            /** Largura e altura mínimas do cartão, lidas do puxador de redimensionar (cada cartão declara as suas). */
            minimosDe(sec, id) {
                const q = window.CSS && CSS.escape ? CSS.escape : s => s;
                const el = document.querySelector(`.wf-item[data-sec="${q(sec)}"][data-bid="${q(id)}"]`);
                const pux = el && el.querySelector('.wf-redim');
                if (pux) {
                    for (const a of pux.attributes) {
                        const m = /redimensionar\(.*,\s*(\d+)\s*,\s*(\d+)\s*\)\s*;?\s*$/.exec(a.value);
                        if (m) return { w: +m[1], h: +m[2] };
                    }
                }
                return { w: 260, h: 160 };
            },
            /** Calcula (sem aplicar) a melhor arrumação da aba. null se a aba não tem cartões. */
            planoDe(sec) {
                const ids = this.cartoesDe(sec);
                if (!ids.length) return null;
                const { W } = this.areaUtil();
                const itens = ids.map(id => {
                    const l = this.rect(sec, id);
                    // tamanho que o usuário escolheu (antes de a organização estreitar ou esticar o cartão)
                    const w = Math.min(Math.max(Number(l.bw || l.w) || 360, 120), W);
                    const h = Math.max(Number(l.bh || l.h) || 260, 80);
                    const min = this.minimosDe(sec, id);
                    const bl = this.estado.blocos.find(x => x.id === id);
                    const img = !!bl && bl.tipo === 'imagem';
                    const prop = img && this.imagens[id] ? Number(bl.dados.prop) || 0 : 0;
                    const it = { id, w, h, cor: l.cor, z: l.z, ordem: (Number(l.y) || 0) * 10000 + (Number(l.x) || 0), fixo: img };
                    if (prop) {
                        // imagem: a proporção fica travada, então a altura acompanha a largura
                        const topo = this.alturaTopo(id);
                        it.alturaPara = ww => Math.ceil((ww - 2) * prop) + topo + 3;
                        it.h = it.alturaPara(w);
                        it.minW = Math.min(w, Math.max(220, Math.round(w * 0.6)));
                    } else if (img || (bl && bl.tipo === 'pasta')) {
                        // imagem sem proporção e pasta (o título e os botões precisam de espaço) não estreitam
                        it.minW = w;
                    } else {
                        it.minW = Math.min(w, Math.max(min.w, Math.round(w * 0.7)));
                    }
                    return it;
                });
                return planejar(itens, W);
            },
            /**
             * Arruma todos os cartões de uma aba sem sobreposição e sem buracos, dentro da largura da tela.
             * Cor e ordem (z) são mantidas. O tamanho escolhido pelo usuário fica guardado (bw/bh), para o cartão
             * voltar ao tamanho dele numa tela maior. Devolve false se a aba não tem cartões.
             */
            organizar(sec) {
                const plano = this.planoDe(sec);
                if (!plano) return false;
                const { x0 } = this.areaUtil();
                const novo = {};
                plano.pos.forEach(p => {
                    const it = p.it;
                    const e = { x: snap(x0 + p.x), y: Math.ceil(p.y / 8) * 8, w: Math.round(p.w), h: Math.round(p.h), z: it.z || 1 };
                    if (it.cor) e.cor = it.cor;
                    if (!it.alturaPara && (e.w !== it.w || e.h !== it.h)) { e.bw = it.w; e.bh = it.h; }
                    else if (it.alturaPara && e.w !== it.w) { e.bw = it.w; }
                    novo[it.id] = e;
                });
                // posições dos cartões de outras vistas (dentro de pastas fechadas, ou do quadro com uma pasta aberta) ficam
                const antigo = this.estado.layout[sec] || {}, existem = new Set(this.todosIdsDe(sec));
                Object.keys(antigo).forEach(id => { if (!novo[id] && existem.has(id)) novo[id] = antigo[id]; });
                this.estado.layout[sec] = novo;
                const org = (this.estado.org && this.estado.org.v === ORG_VERSAO) ? this.estado.org : { v: ORG_VERSAO, sig: {} };
                org.sig = org.sig || {};
                org.sig[this.chaveVista(sec)] = this.assinatura(sec);
                this.estado.org = org;
                return true;
            },
            /** Posição e tamanho de cada cartão da aba, para saber depois se o usuário mexeu na arrumação automática. */
            assinatura(sec) {
                const s = {};
                const L = this.estado.layout[sec] || {};
                this.cartoesDe(sec).forEach(id => { const l = L[id]; if (l) s[id] = [l.x, l.y, l.w, l.h].map(v => Math.round(v)).join(','); });
                return s;
            },
            /** true se a aba ainda está como a organização automática deixou (cartões novos não contam). */
            intocado(sec) {
                const sig = this.estado.org?.sig?.[this.chaveVista(sec)];
                if (!sig) return false;
                const atual = this.assinatura(sec);
                return Object.keys(sig).every(id => !(id in atual) || atual[id] === sig[id]);
            },
            /** true se arrumar de novo deixa o quadro bem mais baixo (sinal de buraco ou de espaço desperdiçado). */
            vaiMelhorar(sec) {
                const ids = this.cartoesDe(sec);
                if (!ids.length) return false;
                const plano = this.planoDe(sec);
                let alt = 0, topo = Infinity;
                ids.forEach(id => { const l = this.rect(sec, id); alt = Math.max(alt, l.y + l.h); topo = Math.min(topo, l.y); });
                const atual = alt - topo;
                return !!plano && plano.H < atual - Math.max(96, atual * 0.08);
            },
            /** Guarda para que largura de tela o layout foi arrumado (decide se precisa arrumar de novo). */
            marcarTela() {
                this.estado.tela = { larg: this.larg || this.col || 1088, em: hoje() };
            },
            /** Faixa de largura de tela: cada faixa tem o seu próprio layout guardado (notebook, monitor, monitor grande...). */
            faixaTela(larg) {
                const w = Number(larg) || 0;
                return w < 1100 ? 'p' : w < 1450 ? 'm' : w < 1900 ? 'g' : 'gg';
            },
            /**
             * Volta o layout guardado de uma faixa de tela. Cartões criados depois (que não estão no layout guardado)
             * mantêm a posição atual; entradas de cartões já apagados são descartadas.
             */
            restaurarLayout(guardado) {
                const atual = this.estado.layout || {};
                const novo = {};
                this.secoesQuadro().forEach(sec => {
                    const g = guardado[sec] || {}, a = atual[sec] || {}, L = {};
                    this.todosIdsDe(sec).forEach(id => {
                        if (g[id]) L[id] = { ...g[id], ...(a[id]?.cor ? { cor: a[id].cor } : {}) };
                        else if (a[id]) L[id] = a[id];
                    });
                    if (Object.keys(L).length) novo[sec] = L;
                });
                this.estado.layout = novo;
            },
            /**
             * Ao entrar na página e quando a largura da janela muda (só com a organização automática ligada):
             * - outra faixa de tela: o layout da faixa anterior é guardado e o desta faixa volta (se já existir);
             *   sem layout guardado para esta faixa, todas as abas são arrumadas para ela;
             * - mesma faixa: abas com cartão fora da tela ou cobrindo outro são arrumadas; abas que a organização
             *   automática arrumou (e o usuário não mexeu) são refeitas se isso fechar buracos.
             *   Arrumação feita à mão só é tocada se houver sobreposição.
             */
            autoOrganizar() {
                if (EMBED || this.empilhado() || !this.larg || !autoLigado()) return;
                const larg = this.larg, ref = this.estado.tela;
                const fNova = this.faixaTela(larg), fVelha = ref ? this.faixaTela(ref.larg) : null;
                const copia = o => JSON.parse(JSON.stringify(o || {}));
                const antigo = !this.estado.org || this.estado.org.v !== ORG_VERSAO;
                let n = 0, msg = '';

                if (fVelha && fVelha !== fNova) {
                    const telas = (this.estado.telas && typeof this.estado.telas === 'object') ? this.estado.telas : {};
                    telas[fVelha] = copia(this.estado.layout);
                    this.estado.telas = telas;
                    if (telas[fNova]) {
                        this.restaurarLayout(copia(telas[fNova]));
                        msg = 'Layout desta tela restaurado.';
                    } else {
                        this.secoesQuadro().forEach(sec => { if (this.organizar(sec)) n++; });
                        this.marcarTela();
                        if (n) this.aviso('Cartões organizados automaticamente para o tamanho da sua tela.');
                        return;
                    }
                }

                this.secoesQuadro().forEach(sec => {
                    if (this.temConflito(sec)) { if (this.organizar(sec)) n++; return; }
                    if ((antigo || this.intocado(sec)) && this.vaiMelhorar(sec) && this.organizar(sec)) n++;
                });
                if (antigo && !(this.estado.org && this.estado.org.v === ORG_VERSAO)) this.estado.org = { v: ORG_VERSAO, sig: {} };
                if (!ref || fVelha !== fNova || n) this.marcarTela();
                if (n) msg = 'Cartões organizados automaticamente para o tamanho da sua tela.';
                if (msg) this.aviso(msg);
            },

            /* ---------- blocos livres ---------- */
            /** Blocos que aparecem agora na aba: os da pasta aberta, ou os que não estão em pasta nenhuma. */
            blocosDe(sec) {
                const aberta = this.pastaAberta(sec);
                return this.estado.blocos.filter(b => b.secao === sec && this.pastaValida(b) === aberta);
            },

            /* ---------- pastas: guardar cartões, abrir, mover, desfazer e excluir ---------- */
            /**
             * Liga as pastas a esta página: tipo "Pasta" no menu "+ Bloco", blocos novos ou colados entram na pasta aberta,
             * Ctrl+clique seleciona cartões e o CSS de seleção/destino é mantido num <style> próprio.
             */
            prepararPastas() {
                if (this._pastasProntas) return;
                this._pastasProntas = true;
                if (Array.isArray(this.tiposBloco) && !this.tiposBloco.some(t => t.id === 'pasta')) {
                    this.tiposBloco.push({ id: 'pasta', nome: 'Pasta' });
                }
                // editor de imagem com camadas (separado do bloco "Imagem" comum)
                if (Array.isArray(this.tiposBloco) && !this.tiposBloco.some(t => t.id === 'editorimg')) {
                    this.tiposBloco.push({ id: 'editorimg', nome: 'Editor de imagem' });
                }
                if (Array.isArray(this.tiposBloco)) window.WFQuadro.tipos = [...this.tiposBloco.map(t => t.id), 'vivo'];
                // cada página cria e cola blocos do seu jeito; aqui só se ajusta o resultado
                const dentroDaPasta = fn => (...args) => {
                    const antes = new Set(this.estado.blocos.map(b => b.id));
                    const r = fn(...args);
                    const sec = typeof args[0] === 'string' ? args[0] : this.aba;
                    const aberta = this.pastaAberta(sec);
                    if (aberta) {
                        const novos = this.estado.blocos.filter(b => !antes.has(b.id) && b.secao === sec && b.id !== aberta);
                        novos.forEach(b => { b.pasta = aberta; });
                        if (novos.length && /fim da aba/.test(this.msg || '')) {
                            this.msg = this.msg.replace(/no fim da aba|ao fim da aba/, `na pasta “${(this.blocoPorId(aberta)?.titulo || 'Pasta').trim()}”`);
                        }
                    }
                    return r;
                };
                const novo = this.novoBloco, colar = this.colarBloco, remover = this.removerBloco, guardar = this.guardarBloco;
                if (typeof guardar === 'function') {
                    this.guardarBloco = (b, ...resto) => (b && b.tipo === 'pasta'
                        ? this.aviso('Pastas não vão para a biblioteca. Salve os cartões de dentro, um a um.')
                        : guardar.call(this, b, ...resto));
                }
                if (typeof novo === 'function') {
                    this.novoBloco = dentroDaPasta((sec, tipo, ...resto) => (tipo === 'pasta' ? this.novaPasta(sec)
                        : tipo === 'editorimg' ? this.novoEditorImagem(sec)
                        : novo.call(this, sec, tipo, ...resto)));
                }
                if (typeof colar === 'function') {
                    this.colarBloco = item => dentroDaPasta(() => colar.call(this, item))(this.aba);
                }
                if (typeof remover === 'function') {
                    this._removerBase = remover;
                    this.removerBloco = (b, ...resto) => {
                        if (b && b.tipo === 'pasta') return this.removerPasta(b);
                        const r = remover.call(this, b, ...resto);
                        // editor de imagem: as camadas ficam numa chave à parte (img.<id>.camadas)
                        const k = b && b.id + '.camadas';
                        if (b && !this.blocoPorId(b.id) && this.imagens[k] !== undefined) {
                            delete this.imagens[k];
                            enviar(`${this.cfg.url}/img.${k}`, null, 'DELETE').catch(() => {});
                        }
                        return r;
                    };
                }
                this._cssPastas = document.createElement('style');
                document.head.appendChild(this._cssPastas);
                this.$watch('sel', () => this.atualizarCssPastas());
                this.$watch('pasta', () => this.atualizarCssPastas());
                this.$watch('aba', () => { this.sel = []; });
                // Ctrl (ou Cmd) + clique num cartão: marca/desmarca; clique no fundo do quadro: limpa a seleção
                this.$nextTick(() => {
                    const area = this.$refs.area;
                    if (!area) return;
                    area.addEventListener('pointerdown', e => {
                        const card = e.target.closest('.wf-item[data-bid]');
                        if (!card) { if (this.sel.length && !e.ctrlKey && !e.metaKey) this.sel = []; return; }
                        if (!(e.ctrlKey || e.metaKey) || e.button !== 0) return;
                        const id = card.dataset.bid;
                        if (!this.estado.blocos.some(b => b.id === id)) return; // cartões fixos não vão para pastas
                        e.preventDefault(); e.stopPropagation();
                        this.alternarSel(id);
                    }, true);
                    // no celular (sem Ctrl): segurar o dedo meio segundo no topo de um cartão marca/desmarca
                    let toque = 0, x0 = 0, y0 = 0;
                    const cancelar = () => { clearTimeout(toque); toque = 0; };
                    area.addEventListener('pointerdown', e => {
                        if (e.pointerType !== 'touch') return;
                        const topo = e.target.closest('.wf-topo');
                        const card = topo && topo.closest('.wf-item[data-bid]');
                        if (!card || !this.estado.blocos.some(b => b.id === card.dataset.bid)) return;
                        x0 = e.clientX; y0 = e.clientY;
                        cancelar();
                        toque = setTimeout(() => { toque = 0; this.alternarSel(card.dataset.bid); if (navigator.vibrate) navigator.vibrate(15); }, 550);
                    }, true);
                    area.addEventListener('pointermove', e => { if (toque && Math.hypot(e.clientX - x0, e.clientY - y0) > 10) cancelar(); }, true);
                    ['pointerup', 'pointercancel'].forEach(t => area.addEventListener(t, cancelar, true));
                });
                this.atualizarCssPastas();
            },
            /** Pasta onde o bloco está (null = solto no quadro). Pasta apagada, de outra aba ou em ciclo conta como solto. */
            pastaValida(b) {
                let id = b && b.pasta, n = 0;
                const p = id && this.estado.blocos.find(x => x.id === id && x.tipo === 'pasta' && x.secao === b.secao);
                if (!p) return null;
                // pasta dentro dela mesma (direta ou indiretamente) não vale
                for (let q = p; q && n < 50; n++) {
                    if (q.pasta === b.id) return null;
                    q = q.pasta && this.estado.blocos.find(x => x.id === q.pasta && x.tipo === 'pasta');
                }
                return id;
            },
            /** Pasta aberta na aba (null = quadro principal). */
            pastaAberta(sec) {
                const id = this.pasta && this.pasta[sec];
                if (!id) return null;
                const p = this.estado.blocos.find(b => b.id === id && b.tipo === 'pasta' && b.secao === sec);
                return p ? id : null;
            },
            blocoPorId(id) { return this.estado.blocos.find(b => b.id === id) || null; },
            /** Cartões guardados direto na pasta. */
            filhosDe(p) {
                const id = typeof p === 'string' ? p : p && p.id;
                return this.estado.blocos.filter(b => b.pasta === id && this.pastaValida(b) === id);
            },
            /** Todos os cartões dentro da pasta, inclusive os de subpastas. */
            descendentesDe(p) {
                const out = [];
                const ir = id => this.filhosDe(id).forEach(f => { out.push(f); if (f.tipo === 'pasta') ir(f.id); });
                ir(typeof p === 'string' ? p : p.id);
                return out;
            },
            /** Caminho da pasta aberta: [pasta de fora, ..., pasta aberta]. */
            caminhoPasta(sec) {
                const out = [];
                let id = this.pastaAberta(sec), n = 0;
                while (id && n++ < 50) {
                    const p = this.blocoPorId(id);
                    if (!p) break;
                    out.unshift(p);
                    id = this.pastaValida(p);
                }
                return out;
            },
            iconeTipo(t) {
                return { texto: '📝', tabela: '▦', lista: '☑', imagem: '🖼', mapa: '🧠', documento: '📄', codigo: '⌨', tabletop: '♟', pasta: '📁', vivo: '🔗' }[t] || '▢';
            },
            resumoPasta(b) {
                const f = this.filhosDe(b).length, t = this.descendentesDe(b).length;
                if (!t) return 'Vazia';
                return `${f} ${f === 1 ? 'item' : 'itens'}` + (t > f ? ` · ${t} no total` : '');
            },
            /** Abre a pasta (o quadro passa a mostrar só o que está nela). `foco` = cartão para destacar. */
            abrirPasta(b, foco) {
                if (!b || b.tipo !== 'pasta') return;
                this.pasta = { ...this.pasta, [b.secao]: b.id };
                this.sel = [];
                this.aoMudarVista(b.secao, foco);
            },
            /** Volta um nível (para a pasta de fora ou para o quadro). */
            fecharPasta(sec) {
                const id = this.pastaAberta(sec);
                if (!id) return false;
                const p = this.blocoPorId(id);
                this.pasta = { ...this.pasta, [sec]: p ? this.pastaValida(p) : null };
                this.sel = [];
                this.aoMudarVista(sec, id);
                return true;
            },
            irPasta(sec, id) {
                this.pasta = { ...this.pasta, [sec]: id || null };
                this.sel = [];
                this.aoMudarVista(sec);
            },
            /** Depois de abrir/fechar uma pasta: sobe para o quadro, arruma se algo ficou sobreposto e destaca um cartão. */
            aoMudarVista(sec, foco) {
                this.$nextTick(() => {
                    if (autoLigado() && !this.empilhado() && this.temConflito(sec)) this.organizar(sec);
                    const area = this.$refs.area;
                    const topo = area ? area.getBoundingClientRect().top + window.scrollY - 120 : 0;
                    if (window.scrollY > topo) window.scrollTo({ top: Math.max(0, topo), behavior: 'smooth' });
                    if (foco) this.destacarCartao(sec, foco);
                });
            },
            destacarCartao(sec, id) {
                this.$nextTick(() => {
                    const q = window.CSS && CSS.escape ? CSS.escape : s => s;
                    const el = document.querySelector(`.wf-item[data-sec="${q(sec)}"][data-bid="${q(id)}"]`);
                    if (!el) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('wf-piscar');
                    setTimeout(() => el.classList.remove('wf-piscar'), 1400);
                });
            },
            /** Primeira posição livre no fim de uma vista (quadro ou pasta), para cartões que acabaram de entrar nela. */
            fimDaVista(sec, pastaId) {
                const ids = this.estado.blocos.filter(b => b.secao === sec && this.pastaValida(b) === pastaId).map(b => b.id);
                if (!pastaId) Object.keys(PADRAO[sec] || {}).forEach(id => ids.push(id));
                let alt = 0;
                ids.forEach(id => { const l = this.rect(sec, id); alt = Math.max(alt, l.y + l.h); });
                return alt ? alt + 16 : 0;
            },
            /**
             * Guarda cartões numa pasta (pastaId) ou tira para o nível de fora (pastaId undefined = sai da pasta aberta).
             * Cartões vão para o fim da vista de destino, um abaixo do outro.
             */
            moverParaPasta(sec, ids, pastaId) {
                const aberta = this.pastaAberta(sec);
                const destino = pastaId === undefined
                    ? (aberta ? this.pastaValida(this.blocoPorId(aberta)) : null)
                    : pastaId;
                if (pastaId === undefined && !aberta) return;
                const proibidos = new Set();
                if (destino) {
                    // uma pasta não pode entrar nela mesma nem numa pasta que está dentro dela
                    let q = this.blocoPorId(destino), n = 0;
                    while (q && n++ < 50) { proibidos.add(q.id); q = q.pasta ? this.blocoPorId(q.pasta) : null; }
                }
                const blocos = ids.map(id => this.blocoPorId(id)).filter(b => b && b.secao === sec && !proibidos.has(b.id) && this.pastaValida(b) !== destino);
                if (!blocos.length) { if (ids.length) this.aviso('Esse cartão já está aí.'); return; }
                const { x0 } = this.areaUtil();
                let y = this.fimDaVista(sec, destino);
                blocos.forEach(b => {
                    if (destino) b.pasta = destino; else delete b.pasta;
                    const l = this.entrada(sec, b.id);
                    l.x = snap(x0); l.y = y; y += l.h + 16;
                });
                this.sel = this.sel.filter(id => !blocos.some(b => b.id === id));
                if (autoLigado() && !this.empilhado() && this.temConflito(sec)) this.organizar(sec);
                const nome = destino ? `“${(this.blocoPorId(destino)?.titulo || 'Pasta').trim()}”` : (aberta && this.pastaValida(this.blocoPorId(aberta)) ? 'a pasta de fora' : 'o quadro');
                this.aviso(`${blocos.length === 1 ? 'Cartão movido' : blocos.length + ' cartões movidos'} para ${nome}.`);
            },
            /** Cria uma pasta na vista atual; com `ids`, já guarda esses cartões nela. */
            novaPasta(sec, ids = []) {
                sec = sec || this.aba;
                if (!sec) return null;
                const id = 'b' + uid();
                const aberta = this.pastaAberta(sec);
                const { x0 } = this.areaUtil();
                const bloco = { id, secao: sec, tipo: 'pasta', titulo: 'Nova pasta', links: [], dados: {} };
                if (aberta) bloco.pasta = aberta;
                this.estado.blocos.push(bloco);
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = { x: snap(x0), y: this.fimDaVista(sec, aberta), w: 320, h: 240, z: ++this.zTop };
                this.menuBloco = false;
                if (ids.length) this.moverParaPasta(sec, ids, id);
                if (autoLigado() && !this.empilhado()) this.$nextTick(() => { if (this.temConflito(sec) || this.vaiMelhorar(sec)) this.organizar(sec); });
                this.aviso(ids.length ? `Pasta criada com ${ids.length} ${ids.length === 1 ? 'cartão' : 'cartões'}. Dê um nome a ela no topo do cartão.` : 'Pasta criada. Arraste cartões para cima dela para guardá-los.');
                this.destacarCartao(sec, id);
                return id;
            },
            /** Desfaz a pasta: o que estava dentro volta para o nível de fora e a pasta some. */
            esvaziarPasta(b) {
                const filhos = this.filhosDe(b);
                if (filhos.length && !confirm(`Desfazer a pasta “${b.titulo || 'Pasta'}”? Os ${filhos.length} itens dela voltam para o quadro.`)) return;
                const fora = this.pastaValida(b);
                const { x0 } = this.areaUtil();
                const l0 = this.rect(b.secao, b.id);
                let y = l0.y;
                filhos.forEach(f => {
                    if (fora) f.pasta = fora; else delete f.pasta;
                    const l = this.entrada(b.secao, f.id);
                    l.x = snap(x0); l.y = y; y += l.h + 16;
                });
                this.apagarPastaSo(b);
                if (autoLigado() && !this.empilhado()) this.$nextTick(() => { if (this.temConflito(b.secao)) this.organizar(b.secao); });
                this.aviso(filhos.length ? 'Pasta desfeita. Os cartões voltaram para o quadro.' : 'Pasta removida.');
            },
            /** Tira só o bloco da pasta (sem conteúdo, sem histórico). */
            apagarPastaSo(b) {
                const i = this.estado.blocos.findIndex(x => x.id === b.id);
                if (i >= 0) this.estado.blocos.splice(i, 1);
                if (this.estado.layout[b.secao]) delete this.estado.layout[b.secao][b.id];
                if (this.pasta[b.secao] === b.id) this.pasta = { ...this.pasta, [b.secao]: this.pastaValida(b) };
            },
            /** Exclui a pasta e tudo o que está dentro (cada cartão vai para o Histórico, como numa exclusão normal). */
            removerPasta(b) {
                const tudo = this.descendentesDe(b);
                const ok = tudo.length
                    ? confirm(`Excluir a pasta “${b.titulo || 'Pasta'}” e os ${tudo.length} itens dentro dela?\nPara manter os cartões, use “Desfazer pasta”.`)
                    : confirm(`Excluir a pasta vazia “${b.titulo || 'Pasta'}”?`);
                if (!ok) return;
                this.excluirSemPerguntar(tudo.filter(x => x.tipo !== 'pasta'));
                tudo.filter(x => x.tipo === 'pasta').forEach(p => this.apagarPastaSo(p));
                this.apagarPastaSo(b);
                this.aviso(tudo.length ? `Pasta excluída com ${tudo.length} itens (eles ficam no Histórico).` : 'Pasta excluída.');
            },
            /** Exclui vários blocos com a exclusão da própria página, sem uma pergunta para cada um. */
            excluirSemPerguntar(blocos) {
                const base = this._removerBase || this.removerBloco;
                const conf = window.confirm;
                window.confirm = () => true;
                try { blocos.forEach(x => base.call(this, x)); } finally { window.confirm = conf; }
            },
            /* ---------- seleção (Ctrl+clique) ---------- */
            alternarSel(id) {
                this.sel = this.sel.includes(id) ? this.sel.filter(x => x !== id) : [...this.sel, id];
            },
            selecionarTodos(sec) {
                this.sel = this.blocosDe(sec || this.aba).map(b => b.id);
                if (!this.sel.length) this.aviso('Esta aba não tem blocos para selecionar.');
            },
            /** Pastas da vista atual, para o "Mover para…" da barra de seleção. */
            pastasDaVista(sec) {
                return this.blocosDe(sec).filter(b => b.tipo === 'pasta' && !this.sel.includes(b.id));
            },
            pastaComSelecionados(sec) {
                const ids = this.sel.filter(id => this.blocoPorId(id));
                this.novaPasta(sec || this.aba, ids);
            },
            excluirSelecionados() {
                const blocos = this.sel.map(id => this.blocoPorId(id)).filter(Boolean);
                if (!blocos.length) return;
                if (blocos.length === 1) { this.removerBloco(blocos[0]); this.sel = []; return; }
                const n = blocos.reduce((s, b) => s + 1 + (b.tipo === 'pasta' ? this.descendentesDe(b).length : 0), 0);
                if (!confirm(`Excluir ${blocos.length} cartões selecionados${n > blocos.length ? ` (${n} contando o que está dentro das pastas)` : ''}?`)) return;
                const pastas = blocos.filter(b => b.tipo === 'pasta');
                const tudo = [...blocos.filter(b => b.tipo !== 'pasta'), ...pastas.flatMap(p => this.descendentesDe(p).filter(x => x.tipo !== 'pasta'))];
                this.excluirSemPerguntar([...new Set(tudo)]);
                pastas.forEach(p => { this.descendentesDe(p).filter(x => x.tipo === 'pasta').forEach(q => this.apagarPastaSo(q)); this.apagarPastaSo(p); });
                this.sel = [];
                this.aviso(`${blocos.length} cartões excluídos (eles ficam no Histórico).`);
            },
            /** O que está debaixo do ponteiro durante um arraste: uma pasta (guardar) ou a barra da pasta aberta (tirar). */
            destinoSob(x, y, sec, id) {
                const els = document.elementsFromPoint(x, y);
                for (const el of els) {
                    if (el.closest && el.closest('[data-wf-sair-pasta]') && this.pastaAberta(sec)) return { pasta: undefined };
                    const card = el.closest && el.closest('.wf-item[data-bid]');
                    if (!card || card.dataset.bid === id || card.dataset.sec !== sec) continue;
                    const b = this.blocoPorId(card.dataset.bid);
                    if (b && b.tipo === 'pasta' && !(this.sel.includes(id) && this.sel.includes(b.id))) return { pasta: b.id };
                    return null;
                }
                return null;
            },
            /** CSS gerado: cartões selecionados, pasta de destino do arraste e cartões fixos escondidos dentro de uma pasta. */
            atualizarCssPastas() {
                if (!this._cssPastas) return;
                const q = window.CSS && CSS.escape ? CSS.escape : s => s;
                const r = [];
                this.sel.forEach(id => r.push(`.wf-item[data-bid="${q(id)}"]{outline:2px solid var(--prim);outline-offset:3px}`));
                if (this.alvoPasta && this.alvoPasta !== 'sair') {
                    r.push(`.wf-item[data-bid="${q(this.alvoPasta)}"]{outline:3px dashed var(--prim);outline-offset:4px;filter:brightness(1.25)}`);
                }
                if (this.alvoPasta === 'sair') r.push('[data-wf-sair-pasta]{outline:3px dashed var(--prim);outline-offset:2px}');
                Object.keys(this.pasta || {}).forEach(sec => {
                    if (!this.pastaAberta(sec)) return;
                    Object.keys(PADRAO[sec] || {}).forEach(id => r.push(`.wf-item[data-sec="${q(sec)}"][data-bid="${q(id)}"]{display:none!important}`));
                });
                this._cssPastas.textContent = r.join('\n');
            },
            /** Copia um bloco (conteúdo, cor, tamanho e imagem) logo abaixo do fim da vista atual. */
            duplicarBloco(b) {
                if (!b) return;
                if (b.tipo === 'pasta') { this.aviso('Pastas não podem ser duplicadas. Duplique os cartões de dentro.'); return; }
                const l = this.rect(b.secao, b.id);
                const ok = this.colarBloco({
                    tipo: b.tipo, titulo: (b.titulo || 'Bloco') + ' (cópia)', nome: b.titulo,
                    dados: JSON.parse(JSON.stringify(b.dados || {})),
                    cor: /^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : null,
                    w: Math.round(l.w), h: Math.round(l.h), imagem: this.imagens[b.id] || null,
                });
                if (ok !== false) this.aviso('Cartão duplicado no fim da aba.');
            },

            /* ---------- editor de imagem (camadas, estilo Photoshop; js/editor-imagem.js) ---------- */
            /** Cria o cartão e já abre o editor. */
            novoEditorImagem(sec) {
                sec = sec || this.aba;
                if (!sec) return null;
                const id = 'b' + uid();
                const aberta = this.pastaAberta(sec);
                const { x0 } = this.areaUtil();
                const bloco = { id, secao: sec, tipo: 'editorimg', titulo: 'Imagem editável', links: [], dados: {} };
                if (aberta) bloco.pasta = aberta;
                this.estado.blocos.push(bloco);
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = { x: snap(x0), y: this.fimDaVista(sec, aberta), w: 420, h: 340, z: ++this.zTop };
                this.menuBloco = false;
                this.aviso('Editor de imagem adicionado. Abra uma foto em Arquivo → Abrir imagem, ou desenhe na tela branca.');
                this.$nextTick(() => this.abrirEditorImagem(this.blocoPorId(id)));
                return id;
            },
            /** Texto do rodapé do cartão: tamanho e camadas. */
            infoEditorImagem(b) {
                const d = (b && b.dados) || {};
                if (!this.imagens[b.id]) return 'Vazio. Clique em “Editar” para começar.';
                return `${d.w || '?'}×${d.h || '?'} px · ${d.camadas || 1} ${d.camadas === 1 ? 'camada' : 'camadas'}`;
            },
            async abrirEditorImagem(b) {
                if (!b || EMBED) return;
                try { await carregarEditor(); } catch { this.aviso('Não consegui carregar o editor de imagem. Verifique a conexão e tente de novo.'); return; }
                const id = b.id;
                window.WFEditorImagem.abrir({
                    titulo: b.titulo || 'Imagem',
                    projeto: this.imagens[id + '.camadas'] || null,
                    imagem: this.imagens[id] || null,
                    aoSalvar: async ({ imagem, projeto, w, h }) => {
                        const bl = this.blocoPorId(id);
                        if (!bl) throw new Error('bloco');
                        await enviar(`${this.cfg.url}/img.${id}`, JSON.stringify(imagem));
                        if (projeto) await enviar(`${this.cfg.url}/img.${id}.camadas`, JSON.stringify(projeto));
                        else if (this.imagens[id + '.camadas']) await enviar(`${this.cfg.url}/img.${id}.camadas`, null, 'DELETE').catch(() => {});
                        this.imagens[id] = imagem;
                        if (projeto) this.imagens[id + '.camadas'] = projeto; else delete this.imagens[id + '.camadas'];
                        bl.dados = { ...(bl.dados || {}), w, h, camadas: projeto ? projeto.camadas.length : 1, editado: hoje() };
                        // o cartão acompanha a proporção da imagem (até 900 px de altura)
                        const l = this.entrada(bl.secao, id);
                        l.h = clamp(Math.round((l.w - 2) * h / w) + this.alturaTopo(id) + 44, 160, 900);
                        delete l.bh;
                        if (autoLigado() && !this.empilhado()) this.$nextTick(() => { if (this.temConflito(bl.secao)) this.organizar(bl.secao); });
                        this.aviso('Imagem salva no cartão.');
                    },
                });
            },

            /* ---------- teclas de atalho (as teclas são definidas na aba Atalhos das configurações) ---------- */
            /** Cartão em que os atalhos agem: o último clicado, se ainda estiver na tela. */
            cartaoAtual() {
                const b = this.atual && this.blocoPorId(this.atual);
                return b && b.secao === this.aba && this.blocosDe(this.aba).includes(b) ? b : null;
            },
            executarAtalho(id) {
                if (EMBED || !this.aba) return;
                const sec = this.aba;
                if (id.startsWith('novo.')) {
                    const tipo = id.slice(5);
                    if (tipo === 'pasta' && this.sel.length) { this.pastaComSelecionados(sec); return; }
                    if (!(this.tiposBloco || []).some(t => t.id === tipo)) { this.aviso('Esta página não tem esse tipo de bloco.'); return; }
                    this.novoBloco(sec, tipo);
                    return;
                }
                const b = this.cartaoAtual();
                const precisa = () => { if (!b) this.aviso('Clique num cartão (bloco livre) primeiro.'); return !!b; };
                switch (id) {
                    case 'salvar': this.gravarEstado().then(() => this.aviso('Tudo salvo.')); break;
                    case 'reorganizar': this.reorganizar(); break;
                    case 'excluir':
                        if (this.sel.length) this.excluirSelecionados();
                        else if (precisa()) this.removerBloco(b);
                        break;
                    case 'duplicar': if (precisa()) this.duplicarBloco(b); break;
                    case 'telaCheia': {
                        const alvo = b ? document.querySelector(`.wf-item[data-bid="${(window.CSS && CSS.escape) ? CSS.escape(b.id) : b.id}"]`) : null;
                        if (!alvo) { this.aviso('Clique num cartão primeiro.'); break; }
                        if (window.WFTelaCheia) window.WFTelaCheia.alternar(alvo);
                        break;
                    }
                    case 'pastaSelecao': this.pastaComSelecionados(sec); break;
                    case 'abrirPasta':
                        if (!precisa()) break;
                        if (b.tipo === 'pasta') this.abrirPasta(b);
                        else if (b.tipo === 'editorimg') this.abrirEditorImagem(b);
                        else this.aviso('O cartão clicado não é uma pasta nem um editor de imagem.');
                        break;
                    case 'fecharPasta': if (!this.fecharPasta(sec)) this.aviso('Nenhuma pasta aberta nesta aba.'); break;
                    case 'tirarDaPasta':
                        if (!this.pastaAberta(sec)) { this.aviso('Abra uma pasta para tirar cartões dela.'); break; }
                        if (this.sel.length) this.moverParaPasta(sec, [...this.sel]);
                        else if (precisa()) this.moverParaPasta(sec, [b.id]);
                        break;
                    case 'selecionarTodos': this.selecionarTodos(sec); break;
                    case 'abaAnterior': case 'abaSeguinte': this.trocarAba(id === 'abaSeguinte' ? 1 : -1); break;
                }
            },
            /** Lista das abas desta página, na ordem dos botões. */
            listaAbas() {
                if (Array.isArray(this.abas)) return this.abas.map(a => (typeof a === 'string' ? a : a.id));
                if (this.estado && Array.isArray(this.estado.abas)) return this.estado.abas.map(a => a.id);
                return Object.keys(PADRAO || {});
            },
            trocarAba(passo) {
                const abas = this.listaAbas().filter(Boolean);
                if (abas.length < 2) return;
                const i = abas.indexOf(this.aba);
                this.aba = abas[(i + passo + abas.length) % abas.length];
            },
            novoBloco(sec, tipo) {
                const def = {
                    texto: [340, 240, 'Texto', { texto: '', tam: 'm' }],
                    tabela: [480, 280, 'Tabela', { cab: ['Item', 'Valor'], linhas: [['', ''], ['', '']] }],
                    lista: [320, 300, 'Lista', { itens: [] }],
                    imagem: [360, 200, 'Imagem', { ajuste: 'contain', legenda: '', prop: 0 }],
                    mapa: [720, 480, 'Mapa mental', { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: PALETA[0] }] }],
                }[tipo];
                if (!def) return;
                const id = 'b' + uid();
                const W = this.col || 1088, y0 = this.alturaConteudo(sec);
                this.estado.blocos.push({ id, secao: sec, tipo, titulo: def[2], links: [], dados: def[3] });
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = { x: 0, y: y0 ? y0 + 16 : 0, w: Math.min(def[0], W), h: def[1], z: ++this.zTop };
                this.menuBloco = false;
                this.aviso(`${def[2]} adicionado ao fim da aba. Arraste para onde quiser.`);
            },
            removerBloco(b) {
                if (!confirm(`Excluir o bloco "${b.titulo || 'sem título'}"?`)) return;
                const { id, secao } = b;
                // histórico: guarda uma cópia do bloco (conteúdo, imagem, cor, tamanho e posição) para poder restaurar
                const l = this.rect(secao, id);
                window.dispatchEvent(new CustomEvent('wf-bloco-removido', { detail: {
                    id: uid(),
                    nome: (b.titulo || '').trim() || 'Sem título',
                    tipo: b.tipo,
                    titulo: b.titulo || '',
                    dados: normalizarDados(b.tipo, b.dados),
                    cor: /^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : null,
                    w: Math.round(l.w), h: Math.round(l.h), x: l.x, y: l.y,
                    secao,
                    imagem: this.imagens[id] || null,
                } }));
                const i = this.estado.blocos.findIndex(x => x.id === id);
                if (i >= 0) this.estado.blocos.splice(i, 1);
                if (this.estado.layout[secao]) delete this.estado.layout[secao][id];
                if (this.imagens[id]) {
                    delete this.imagens[id];
                    enviar(`${this.cfg.url}/img.${id}`, null, 'DELETE').catch(() => {});
                }
            },
            /* ---------- biblioteca: guardar e colar blocos ---------- */
            /** Copia o bloco (conteúdo, título, cor, tamanho e imagem) para a biblioteca do usuário. */
            guardarBloco(b) {
                const l = this.rect(b.secao, b.id);
                const item = {
                    id: uid(),
                    nome: (b.titulo || '').trim() || 'Sem título',
                    tipo: b.tipo,
                    titulo: b.titulo || '',
                    dados: normalizarDados(b.tipo, b.dados),
                    cor: /^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : null,
                    w: Math.round(l.w), h: Math.round(l.h),
                    imagem: this.imagens[b.id] || null,
                    criado: new Date().toISOString(),
                };
                this.enviarBiblioteca(item, 'Bloco salvo na biblioteca (engrenagem → Biblioteca).');
            },
            /** Cartões fixos (contas, relatórios, tarefas…): guarda uma cópia estática, como tabela, texto ou imagem. */
            guardarCartao(btn) {
                const el = btn.closest('.wf-item');
                const corpo = el?.querySelector('.wf-corpo');
                if (!corpo) return;
                const titulo = (el.querySelector('.wf-topo input.wf-titulo')?.value || el.querySelector('.wf-topo h2')?.textContent || 'Cartão').trim();
                const cor = el.querySelector('.wf-cor')?.value || '';
                const sec = el.dataset.sec, bid = el.dataset.bid;
                if (SEG.test(sec || '') && SEG.test(bid || '') && /^\/areas\/[a-z]+$/.test(location.pathname)) {
                    // cartão ativo: ao colar, abre o cartão original (com o JS e os dados da página de origem)
                    this.enviarBiblioteca({
                        id: uid(), nome: titulo, tipo: 'vivo', titulo,
                        dados: { pagina: location.pathname, sec, bid },
                        cor: /^#[0-9a-fA-F]{6}$/.test(cor) ? cor : null,
                        w: Math.max(100, Math.round(el.offsetWidth)), h: Math.max(80, Math.round(el.offsetHeight)),
                        imagem: null, criado: new Date().toISOString(),
                    }, `“${titulo}” salvo como cartão ativo: ao colar, ele continua funcionando e usando os dados de origem.`);
                    return;
                }
                const c = cartaoParaBloco(corpo);
                if (!c) { this.aviso('Não há nada para salvar neste cartão.'); return; }
                this.enviarBiblioteca({
                    id: uid(), nome: titulo, tipo: c.tipo, titulo, dados: c.dados,
                    cor: /^#[0-9a-fA-F]{6}$/.test(cor) ? cor : null,
                    w: Math.max(100, Math.round(el.offsetWidth)), h: Math.max(80, Math.round(el.offsetHeight)),
                    imagem: c.imagem || null, criado: new Date().toISOString(),
                }, `“${titulo}” salvo como cópia (${{ tabela: 'tabela', texto: 'texto', imagem: 'imagem' }[c.tipo]}) com os dados de agora.`);
            },
            enviarBiblioteca(item, ok) {
                if (!window.WFBiblioteca) { this.aviso('A biblioteca não está disponível nesta página.'); return; }
                // limites do servidor: nome até 120 caracteres, título até 200
                item.nome = String(item.nome || '').trim().slice(0, 120) || 'Sem título';
                item.titulo = String(item.titulo || '').slice(0, 200);
                window.WFBiblioteca.adicionar(item)
                    .then(() => this.aviso(ok))
                    .catch(e => {
                        // limite do plano: leva para a tela de planos em vez de mostrar erro
                        const limitado = window.WFPlanos && window.WFPlanos.limite('biblioteca') !== null;
                        // 402/403 = o servidor recusou por limite de plano (vale mesmo com o biblioteca.js antigo)
                        if (['plano', '402', '403'].includes(e.message) || (e.message === 'cheia' && limitado)) {
                            if (window.WFPlanos) window.WFPlanos.barrar('biblioteca');
                            else {
                                // sem o planos.js carregado: avisa e abre a tela de planos por conta própria
                                this.aviso('Limite do plano gratuito atingido. Abrindo os planos…');
                                const destino = document.querySelector('meta[name="planos-url"]')?.content || '/planos';
                                setTimeout(() => { location.href = destino; }, 1200);
                            }
                            return;
                        }
                        console.error('[Biblioteca] falha ao salvar:', e);
                        this.aviso(
                        e.message === 'cheia' ? 'A biblioteca está cheia. Exclua algum item.'
                            : e.message === '422' ? 'A biblioteca recusou este bloco (dados inválidos). Tente salvar de novo.'
                                : `Não consegui salvar na biblioteca (erro: ${e.message || 'desconhecido'}).`);
                    });
            },
            /** Cola uma cópia independente de um item da biblioteca no fim da aba atual.
                Se vier do histórico (item.restaurar), volta para a aba e a posição de origem.
                Devolve true se o bloco foi criado, false se foi recusado. */
            colarBloco(item) {
                const sec = (item && item.restaurar && PADRAO[item.secao]) ? item.secao : this.aba;
                if (!sec || !item || !item.tipo) return false;
                if (item.tipo !== 'vivo' && this.tiposBloco && !this.tiposBloco.some(t => t.id === item.tipo)) {
                    this.aviso('Esta página não tem esse tipo de bloco. Cole em outra página.');
                    return false;
                }
                if (dadosPerdidos(item)) {
                    this.aviso('Este item da biblioteca está sem conteúdo (foi salvo por uma versão antiga). Exclua-o e salve o bloco de novo.');
                    return false;
                }
                const dados = normalizarDados(item.tipo, item.dados);
                // ids novos: a cópia não pode compartilhar identidade com o original
                if (item.tipo === 'lista') (dados.itens || []).forEach(i => { i.id = uid(); });
                if (item.tipo === 'documento') (dados.secoes || []).forEach(s => { s.id = uid(); });
                if (item.tipo === 'mapa') {
                    const m = {};
                    (dados.nos || []).forEach(n => { m[n.id] = uid(); });
                    (dados.nos || []).forEach(n => { n.id = m[n.id]; n.pai = n.pai ? (m[n.pai] || null) : null; });
                }
                const id = 'b' + uid();
                const W = this.col || 1088, y0 = this.alturaConteudo(sec);
                this.estado.blocos.push({ id, secao: sec, tipo: item.tipo, titulo: item.titulo || item.nome || 'Bloco', links: [], dados });
                if (!this.estado.layout[sec]) this.estado.layout[sec] = {};
                this.estado.layout[sec][id] = {
                    x: item.restaurar ? Math.max(0, Number(item.x) || 0) : 0,
                    y: item.restaurar ? Math.max(0, Number(item.y) || 0) : (y0 ? y0 + 16 : 0),
                    w: Math.min(item.w || 360, W), h: item.h || 260, z: ++this.zTop,
                    ...(item.cor ? { cor: item.cor } : {}),
                };
                if (item.imagem) {
                    this.imagens[id] = item.imagem;
                    enviar(`${this.cfg.url}/img.${id}`, JSON.stringify(item.imagem)).catch(() => this.aviso('Não consegui salvar a imagem colada.'));
                    if (item.tipo === 'imagem') {
                        // ajusta o card ao tamanho da imagem colada
                        const bl = this.estado.blocos.find(x => x.id === id);
                        this.$nextTick(() => { if (bl) this.ajustarImagem(bl, item.imagem); });
                    }
                }
                this.aviso(item.restaurar ? 'Bloco restaurado.' : 'Bloco colado no fim da aba. Arraste para onde quiser.');
                return true;
            },
            tabCol(b) { b.dados.cab.push('Coluna ' + (b.dados.cab.length + 1)); b.dados.linhas.forEach(l => l.push('')); },
            tabColRem(b, j) { if (b.dados.cab.length < 2) return; b.dados.cab.splice(j, 1); b.dados.linhas.forEach(l => l.splice(j, 1)); },
            tabLinha(b) { b.dados.linhas.push(b.dados.cab.map(() => '')); },
            addItem(b, el) {
                const t = el.value.trim();
                if (!t) return;
                b.dados.itens.push({ id: uid(), t, f: false });
                el.value = '';
            },
            remItem(b, id) { const i = b.dados.itens.findIndex(x => x.id === id); if (i >= 0) b.dados.itens.splice(i, 1); },

            /* ---------- imagem: o card se adapta ao tamanho da imagem ---------- */
            /** Altura do cabeçalho do cartão (cai para 44 px se a aba estiver escondida). */
            alturaTopo(id) {
                return document.querySelector(`.wf-item[data-bid="${id}"] .wf-topo`)?.offsetHeight || 44;
            },
            /** Mede a imagem e ajusta o card: largura natural (limitada à área) e altura pela proporção. */
            ajustarImagem(b, src) {
                return new Promise(ok => {
                    const im = new Image();
                    im.onload = () => {
                        const nw = im.naturalWidth, nh = im.naturalHeight;
                        if (nw && nh) {
                            b.dados.prop = nh / nw;
                            const l = this.entrada(b.secao, b.id);
                            const max = Math.max(220, Math.min(this.col || 1088, this.larg - this.ox - l.x));
                            l.w = Math.round(clamp(nw, 220, max));
                            // 2 px = bordas laterais; 3 px = bordas de cima (2) e de baixo (1)
                            l.h = Math.ceil((l.w - 2) * b.dados.prop) + this.alturaTopo(b.id) + 3;
                        }
                        ok();
                    };
                    im.onerror = () => ok();
                    im.src = src;
                });
            },
            /** Blocos de imagem antigos (sem proporção guardada) são ajustados uma vez. */
            /** Devolve uma Promise com quantos cartões foram ajustados (0 = nada mudou). */
            corrigirImagens(sec) {
                const lista = this.blocosDe(sec).filter(b => b.tipo === 'imagem' && this.imagens[b.id] && !b.dados.prop);
                return Promise.all(lista.map(b => this.ajustarImagem(b, this.imagens[b.id]))).then(() => lista.length);
            },
            async lerImagem(b, arquivo) {
                if (!arquivo || !arquivo.type.startsWith('image/')) return;
                try {
                    const src = await reduzir(arquivo);
                    this.imagens[b.id] = src;
                    await this.ajustarImagem(b, src);
                    await enviar(`${this.cfg.url}/img.${b.id}`, JSON.stringify(src));
                } catch { this.aviso('Não consegui salvar a imagem.'); }
            },
            vincular(b, el) {
                const v = el.value; el.value = '';
                if (!v) return;
                const [t, id] = v.split(':');
                if (!b.links.some(l => l.t === t && String(l.id) === id)) b.links.push({ t, id });
            },

            /* ---------- mapa mental ---------- */
            mmTam(b) {
                const nos = b.dados.nos;
                return { w: Math.max(...nos.map(n => n.x)) + MM.w + 60, h: Math.max(...nos.map(n => n.y)) + MM.h + 60 };
            },
            mmLinhas(b) {
                return b.dados.nos.filter(n => n.pai).map(n => {
                    const pai = b.dados.nos.find(x => x.id === n.pai);
                    if (!pai) return '';
                    const dir = (n.x + MM.w / 2) >= (pai.x + MM.w / 2);
                    const x1 = dir ? pai.x + MM.w : pai.x, x2 = dir ? n.x : n.x + MM.w;
                    const y1 = pai.y + MM.h / 2, y2 = n.y + MM.h / 2, mx = (x1 + x2) / 2;
                    return `M${x1} ${y1} C${mx} ${y1} ${mx} ${y2} ${x2} ${y2}`;
                }).join(' ');
            },
            mmAdd(b, paiId) {
                const nos = b.dados.nos, pai = nos.find(n => n.id === paiId);
                if (!pai) return;
                const irmaos = nos.filter(n => n.pai === paiId).length;
                const cor = pai.pai ? pai.cor : PALETA[(irmaos + 1) % PALETA.length];
                nos.push({ id: uid(), t: 'Nova ideia', x: pai.x + MM.w + 48, y: pai.y + irmaos * (MM.h + 12), pai: paiId, cor });
            },
            mmRem(b, id) {
                const del = new Set([id]);
                let mudou = true;
                while (mudou) {
                    mudou = false;
                    b.dados.nos.forEach(n => { if (n.pai && del.has(n.pai) && !del.has(n.id)) { del.add(n.id); mudou = true; } });
                }
                b.dados.nos = b.dados.nos.filter(n => !del.has(n.id));
            },
            mmCor(n) { n.cor = PALETA[(PALETA.indexOf(n.cor) + 1) % PALETA.length]; },
            mmMover(e, b, n) {
                const x0 = e.clientX, y0 = e.clientY, nx = n.x, ny = n.y;
                const mover = ev => { n.x = Math.max(0, Math.round(nx + ev.clientX - x0)); n.y = Math.max(0, Math.round(ny + ev.clientY - y0)); };
                const fim = () => {
                    window.removeEventListener('pointermove', mover);
                    window.removeEventListener('pointerup', fim);
                    window.removeEventListener('pointercancel', fim);
                };
                window.addEventListener('pointermove', mover);
                window.addEventListener('pointerup', fim);
                window.addEventListener('pointercancel', fim);
            },
        };
    }

    window.WFQuadro = {
        util: { pad, iso, hoje, agora, uid, r1, num, clamp, snap, norm, dataBR },
        PALETA, MM, EMBED, enviar, reduzir, estadoUI, metodos, normalizarDados, dadosPerdidos,
        autoLigado, definirAuto, planejar, juntarEstados, aplicarNoLugar,
    };
})();
