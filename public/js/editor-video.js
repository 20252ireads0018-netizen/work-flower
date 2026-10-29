/* Work Flower · Editor de vídeo (cartão "Editor de vídeo"; aberto por quadro.js, abrirEditorVideo).
   window.WFEditorVideo.abrir({ blocoId, titulo, projeto, aoSalvar }) abre o editor em tela cheia.
     projeto  = o que foi salvo antes (ver novoProjeto)
     aoSalvar = async ({ projeto, capa, dur }) => {}   (projeto vai para o cartão; capa é um JPEG pequeno)
   window.WFEditorVideo.assistir({ blocoId, titulo }) toca o último vídeo exportado deste cartão.

   Os arquivos (vídeos, fotos, músicas) ficam NO NAVEGADOR de quem editou (IndexedDB "wf-editor-video"),
   porque são grandes demais para o servidor; o projeto (cortes, ordem, textos, volumes) fica no cartão.
   Em outro aparelho o editor pede para localizar o arquivo de novo (mesmo nome).

   Trilhas: vídeo (clipes de vídeo e foto em sequência), texto (legendas e títulos por cima) e áudio (música e
   narração, em qualquer ponto). Corte nas pontas, dividir no cursor, velocidade, volume, entrada e saída
   suaves, ajustes de cor, proporção (16:9, 9:16, 1:1, 4:5) e exportação em MP4 ou WebM pelo próprio navegador
   (MediaRecorder: a exportação leva o tempo do vídeo). Não depende de nada. */
(function () {
    'use strict';
    if (window.WFEditorVideo) return;

    const FPS = 30;
    const MAX_PASSOS = 60;
    const PROPORCOES = { '16:9': [16, 9], '9:16': [9, 16], '1:1': [1, 1], '4:5': [4, 5] };
    const QUALIDADES = { 480: 2500000, 720: 5000000, 1080: 8000000 }; // altura (lado menor) → bits por segundo
    const TIPOS_SAIDA = [
        ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'mp4'], ['video/mp4', 'mp4'],
        ['video/webm;codecs=vp9,opus', 'webm'], ['video/webm;codecs=vp8,opus', 'webm'], ['video/webm', 'webm'],
    ];

    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
    const uid = () => Math.random().toString(36).slice(2, 10);
    const r2 = n => Math.round(n * 100) / 100;
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const tempo = s => {
        s = Math.max(0, s || 0);
        const m = Math.floor(s / 60), seg = s - m * 60;
        return `${m}:${seg.toFixed(1).padStart(4, '0')}`;
    };

    /* ---------- arquivos no navegador (IndexedDB) ---------- */
    let banco = null;
    function abrirBanco() {
        if (banco) return banco;
        banco = new Promise((ok, falha) => {
            const r = indexedDB.open('wf-editor-video', 1);
            r.onupgradeneeded = () => {
                const db = r.result;
                if (!db.objectStoreNames.contains('midias')) db.createObjectStore('midias');
                if (!db.objectStoreNames.contains('exportes')) db.createObjectStore('exportes');
            };
            r.onsuccess = () => ok(r.result);
            r.onerror = () => { banco = null; falha(r.error); };
        });
        return banco;
    }
    async function bd(loja, modo, fn) {
        const db = await abrirBanco();
        return new Promise((ok, falha) => {
            const tx = db.transaction(loja, modo);
            const req = fn(tx.objectStore(loja));
            tx.oncomplete = () => ok(req && req.result);
            tx.onerror = () => falha(tx.error);
            tx.onabort = () => falha(tx.error || new Error('abortado'));
        });
    }
    const lerArquivo = (loja, id) => bd(loja, 'readonly', s => s.get(id));
    const gravarArquivo = (loja, id, blob) => bd(loja, 'readwrite', s => s.put(blob, id));

    /* ---------- estilos ---------- */
    const CSS = `
    .wfv{position:fixed;inset:0;z-index:90;display:grid;grid-template-rows:auto 1fr auto;grid-template-columns:250px 1fr 290px;
         background:#14171c;color:#e5e7eb;font:13px/1.35 system-ui,sans-serif;user-select:none}
    .wfv[hidden]{display:none!important}
    .wfv *{box-sizing:border-box}
    .wfv button{font:inherit;color:inherit;background:none;border:0;cursor:pointer}
    .wfv input,.wfv select,.wfv textarea{font:inherit;color:#e5e7eb;background:#0d0f13;border:1px solid #2c323c;border-radius:6px;padding:3px 6px}
    .wfv input[type=range]{padding:0;accent-color:var(--prim,#3b82f6)}
    .wfv input[type=color]{width:34px;height:26px;padding:1px;cursor:pointer}
    .wfv-topo{grid-column:1/-1;display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:6px 10px;background:#1b1f26;border-bottom:1px solid #2c323c}
    .wfv-titulo{font-weight:600;margin-right:6px;max-width:22ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfv-b{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:7px;border:1px solid #2c323c!important;background:#232831!important;white-space:nowrap}
    .wfv-b:hover{border-color:var(--prim,#3b82f6)!important}
    .wfv-b:disabled{opacity:.4;cursor:default}
    .wfv-b.prim{background:var(--prim,#3b82f6)!important;border-color:var(--prim,#3b82f6)!important;color:var(--on-prim,#fff)}
    .wfv-sep{width:1px;height:22px;background:#2c323c;margin:0 4px}
    .wfv-esq,.wfv-dir{overflow:auto;background:#1b1f26}
    .wfv-esq{border-right:1px solid #2c323c}.wfv-dir{border-left:1px solid #2c323c}
    .wfv-sec{padding:10px;border-bottom:1px solid #2c323c}
    .wfv-sec h3{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#9aa3af;margin:0 0 8px;display:flex;justify-content:space-between;align-items:center;gap:6px}
    .wfv-midias{display:flex;flex-direction:column;gap:4px}
    .wfv-midia{display:flex;align-items:center;gap:7px;padding:4px;border-radius:7px;border:1px solid transparent}
    .wfv-midia:hover{background:#232831}
    .wfv-midia .mini{width:52px;height:32px;border-radius:4px;background:#0b0d10 center/cover no-repeat;flex:none;display:grid;place-content:center;font-size:15px}
    .wfv-midia .nome{flex:1;min-width:0}
    .wfv-midia .nome b{display:block;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfv-midia .nome small{color:#9aa3af}
    .wfv-midia .falta{color:#fbbf24}
    .wfv-midia button.add{width:26px;height:26px;border-radius:6px;border:1px solid #2c323c}
    .wfv-midia button.add:hover{border-color:var(--prim,#3b82f6)}
    .wfv-soltar{border:1.5px dashed #3a4150;border-radius:9px;padding:12px;text-align:center;color:#9aa3af;cursor:pointer}
    .wfv-soltar.sobre{border-color:var(--prim,#3b82f6);color:#e5e7eb}
    .wfv-centro{display:flex;flex-direction:column;min-width:0;min-height:0;background:#0b0d10}
    .wfv-palco{flex:1;min-height:0;display:grid;place-items:center;padding:12px;position:relative;overflow:hidden}
    .wfv-palco canvas{background:#000;box-shadow:0 8px 30px rgba(0,0,0,.5);border-radius:4px}
    .wfv-aviso{position:absolute;left:50%;top:12px;transform:translateX(-50%);padding:6px 12px;border-radius:8px;background:#232a36;border:1px solid var(--prim,#3b82f6);font-size:12.5px;pointer-events:none;opacity:0;transition:opacity .2s;max-width:90%;text-align:center}
    .wfv-aviso.ver{opacity:1}
    .wfv-controles{display:flex;align-items:center;justify-content:center;gap:6px;padding:6px 10px;background:#181b21;border-top:1px solid #2c323c;flex-wrap:wrap}
    .wfv-controles .t{font:12px ui-monospace,monospace;color:#cbd5e1;min-width:110px;text-align:center}
    .wfv-linha-tempo{grid-column:1/-1;display:flex;flex-direction:column;background:#161a20;border-top:1px solid #2c323c;height:230px;min-height:0}
    .wfv-lt-barra{display:flex;align-items:center;gap:8px;padding:5px 10px;border-bottom:1px solid #2c323c;color:#9aa3af;font-size:12px}
    .wfv-lt{flex:1;min-height:0;display:grid;grid-template-columns:74px 1fr;overflow:hidden}
    .wfv-rotulos{display:flex;flex-direction:column;padding-top:22px;border-right:1px solid #2c323c}
    .wfv-rotulos div{height:52px;display:flex;align-items:center;padding:0 8px;color:#9aa3af;font-size:12px;border-bottom:1px solid #20252d}
    .wfv-rolar{overflow-x:auto;overflow-y:hidden;position:relative}
    .wfv-faixas{position:relative;min-width:100%;height:100%}
    .wfv-regua{position:relative;height:22px;border-bottom:1px solid #2c323c;cursor:pointer}
    .wfv-regua span{position:absolute;top:3px;font:10px ui-monospace,monospace;color:#7b8594;transform:translateX(3px)}
    .wfv-regua i{position:absolute;bottom:0;width:1px;height:6px;background:#3a4150}
    .wfv-faixa{position:relative;height:52px;border-bottom:1px solid #20252d}
    .wfv-clipe{position:absolute;top:5px;height:42px;border-radius:6px;overflow:hidden;cursor:grab;border:1px solid rgba(255,255,255,.14);
               display:flex;align-items:center;padding:0 9px;font-size:11.5px;white-space:nowrap;touch-action:none}
    .wfv-clipe.video{background:#2b4a7a linear-gradient(180deg,rgba(255,255,255,.08),transparent)}
    .wfv-clipe.imagem{background:#3d5a3a linear-gradient(180deg,rgba(255,255,255,.08),transparent)}
    .wfv-clipe.texto{background:#6a4b8a linear-gradient(180deg,rgba(255,255,255,.08),transparent)}
    .wfv-clipe.audio{background:#7a5a2b linear-gradient(180deg,rgba(255,255,255,.08),transparent)}
    .wfv-clipe.sel{outline:2px solid var(--prim,#3b82f6);outline-offset:1px;z-index:2}
    .wfv-clipe.falta{background:#5a2b2b}
    .wfv-clipe span{overflow:hidden;text-overflow:ellipsis;pointer-events:none}
    .wfv-clipe .alca{position:absolute;top:0;bottom:0;width:8px;cursor:ew-resize;background:rgba(255,255,255,.18)}
    .wfv-clipe .alca.e{left:0}.wfv-clipe .alca.d{right:0}
    .wfv-cursor{position:absolute;top:0;bottom:0;width:2px;background:#f43f5e;pointer-events:none;z-index:4}
    .wfv-cursor::before{content:'';position:absolute;top:0;left:-5px;border:6px solid transparent;border-top-color:#f43f5e}
    .wfv-prop{display:flex;flex-direction:column;gap:7px}
    .wfv-prop label{display:flex;align-items:center;gap:6px;color:#b4bcc8}
    .wfv-prop label>span{min-width:86px}
    .wfv-prop label input[type=range]{flex:1}
    .wfv-prop label input[type=number]{width:74px}
    .wfv-prop textarea{width:100%;min-height:58px;resize:vertical}
    .wfv-prop .valor{min-width:42px;text-align:right;font:11px ui-monospace,monospace;color:#9aa3af}
    .wfv-dica{color:#9aa3af;font-size:12px;line-height:1.45}
    .wfv-dialogo{position:absolute;inset:0;z-index:6;display:grid;place-content:center;background:rgba(0,0,0,.55)}
    .wfv-dialogo>div{width:min(420px,92vw);padding:16px;border-radius:12px;background:#1b1f26;border:1px solid #2c323c;display:flex;flex-direction:column;gap:10px}
    .wfv-dialogo video{width:100%;max-height:60vh;border-radius:8px;background:#000}
    .wfv-barra-prog{height:8px;border-radius:9999px;background:#0d0f13;overflow:hidden}
    .wfv-barra-prog>i{display:block;height:100%;width:0;background:var(--prim,#3b82f6)}
    @media (max-width:980px){
        .wfv{grid-template-columns:1fr;grid-template-rows:auto auto minmax(44vh,1fr) auto auto;overflow-y:auto}
        .wfv-esq,.wfv-dir{max-height:24vh}
        .wfv-centro{min-height:44vh}
        .wfv-linha-tempo{height:200px}
    }`;

    let el = null;     // raiz do editor
    let S = null;      // estado do projeto aberto
    let ctxAudio = null;
    const pool = new Map();   // chave → { el, fonte, ganho, url }
    const imagensCache = new Map(); // midia → ImageBitmap

    function novoProjeto() {
        return { v: 1, proporcao: '16:9', altura: 720, fundo: '#000000', midias: {}, videos: [], textos: [], audios: [] };
    }
    const dims = p => {
        const [a, b] = PROPORCOES[p.proporcao] || PROPORCOES['16:9'];
        const lado = p.altura || 720;
        // o lado menor tem a "altura" escolhida; múltiplos de 2 (exigência dos codificadores)
        const w = a >= b ? Math.round(lado * a / b / 2) * 2 : lado;
        const h = a >= b ? lado : Math.round(lado * b / a / 2) * 2;
        return [w, h];
    };

    /* ---------- tempos ---------- */
    const durClipe = c => Math.max(0.1, (c.fim - c.ini) / (c.vel || 1));
    function sequencia() {
        let t = 0;
        return S.p.videos.map(c => { const d = durClipe(c); const s = { c, inicio: t, dur: d }; t += d; return s; });
    }
    function duracaoTotal() {
        const seq = sequencia();
        let fim = seq.length ? seq[seq.length - 1].inicio + seq[seq.length - 1].dur : 0;
        S.p.textos.forEach(x => { fim = Math.max(fim, x.inicio + x.dur); });
        S.p.audios.forEach(a => { fim = Math.max(fim, a.inicio + (a.fim - a.ini)); });
        return fim;
    }
    // 0..1: entrada e saída suaves de um item que vai de 0 a dur
    const suave = (t, dur, fi, fo) => {
        let f = 1;
        if (fi > 0 && t < fi) f = Math.min(f, t / fi);
        if (fo > 0 && t > dur - fo) f = Math.min(f, (dur - t) / fo);
        return clamp(f, 0, 1);
    };

    /* ---------- montagem da tela ---------- */
    function montar() {
        const st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
        el = document.createElement('div');
        el.className = 'wfv'; el.hidden = true;
        el.setAttribute('role', 'dialog'); el.setAttribute('aria-modal', 'true'); el.setAttribute('aria-label', 'Editor de vídeo');
        el.innerHTML = `
        <div class="wfv-topo">
            <span class="wfv-titulo" data-titulo>Editor de vídeo</span>
            <button class="wfv-b" data-acao="importar" title="Vídeos, fotos e músicas">＋ Importar mídia</button>
            <button class="wfv-b" data-acao="novoTexto" title="Título ou legenda (T)">T Texto</button>
            <span class="wfv-sep"></span>
            <button class="wfv-b" data-acao="desfazer" title="Desfazer (Ctrl+Z)">↶</button>
            <button class="wfv-b" data-acao="refazer" title="Refazer (Ctrl+Y)">↷</button>
            <span class="wfv-sep"></span>
            <label class="wfv-dica">Formato <select data-campo-proj="proporcao">
                <option value="16:9">16:9 (YouTube)</option><option value="9:16">9:16 (Reels, TikTok)</option>
                <option value="1:1">1:1 (quadrado)</option><option value="4:5">4:5 (feed)</option></select></label>
            <label class="wfv-dica">Qualidade <select data-campo-proj="altura">
                <option value="480">480p</option><option value="720">720p</option><option value="1080">1080p</option></select></label>
            <label class="wfv-dica">Fundo <input type="color" data-campo-proj="fundo"></label>
            <span style="flex:1"></span>
            <button class="wfv-b" data-acao="exportar">⇩ Exportar vídeo</button>
            <button class="wfv-b prim" data-acao="salvar">Salvar no cartão</button>
            <button class="wfv-b" data-acao="fechar" title="Fechar (Esc)">✕</button>
        </div>
        <div class="wfv-esq">
            <div class="wfv-sec">
                <h3>Mídias <small data-n-midias></small></h3>
                <div class="wfv-soltar" data-soltar>Solte vídeos, fotos ou músicas aqui<br><small>ou clique para escolher</small></div>
                <div class="wfv-midias" data-midias style="margin-top:8px"></div>
            </div>
            <div class="wfv-sec wfv-dica">
                Os arquivos ficam guardados neste navegador. O projeto fica no cartão: em outro aparelho, localize os arquivos de novo.
                <br><br><b>Atalhos:</b> espaço toca/pausa · S divide no cursor · Delete exclui · ← → avançam um quadro · Ctrl+Z desfaz.
            </div>
        </div>
        <div class="wfv-centro">
            <div class="wfv-palco"><canvas data-tela></canvas><div class="wfv-aviso" data-aviso></div></div>
            <div class="wfv-controles">
                <button class="wfv-b" data-acao="inicio" title="Início (Home)">⏮</button>
                <button class="wfv-b" data-acao="voltar" title="Quadro anterior (←)">◀︎</button>
                <button class="wfv-b prim" data-acao="tocar" data-btn-tocar title="Tocar/pausar (espaço)">▶ Tocar</button>
                <button class="wfv-b" data-acao="avancar" title="Próximo quadro (→)">▶︎</button>
                <button class="wfv-b" data-acao="fim" title="Fim (End)">⏭</button>
                <span class="t" data-tempo>0:00.0 / 0:00.0</span>
                <span class="wfv-sep"></span>
                <button class="wfv-b" data-acao="dividir" title="Dividir no cursor (S)">✂ Dividir</button>
                <button class="wfv-b" data-acao="duplicar" title="Duplicar">⧉</button>
                <button class="wfv-b" data-acao="excluir" title="Excluir (Delete)">🗑</button>
            </div>
        </div>
        <div class="wfv-dir"><div class="wfv-sec"><h3 data-prop-titulo>Propriedades</h3><div class="wfv-prop" data-prop></div></div></div>
        <div class="wfv-linha-tempo">
            <div class="wfv-lt-barra">
                <span>Linha do tempo</span><span data-duracao></span><span style="flex:1"></span>
                <label>Zoom <input type="range" min="10" max="220" value="60" data-zoom></label>
            </div>
            <div class="wfv-lt">
                <div class="wfv-rotulos"><div>🎬 Vídeo</div><div>T Texto</div><div>♪ Áudio</div></div>
                <div class="wfv-rolar" data-rolar><div class="wfv-faixas" data-faixas>
                    <div class="wfv-regua" data-regua></div>
                    <div class="wfv-faixa" data-faixa="videos"></div>
                    <div class="wfv-faixa" data-faixa="textos"></div>
                    <div class="wfv-faixa" data-faixa="audios"></div>
                    <div class="wfv-cursor" data-cursor></div>
                </div></div>
            </div>
        </div>
        <input type="file" data-arquivo accept="video/*,audio/*,image/*" multiple hidden>
        <input type="file" data-localizar accept="video/*,audio/*,image/*" hidden>`;
        document.body.appendChild(el);

        const q = s => el.querySelector(s);
        el.addEventListener('click', e => {
            const b = e.target.closest('[data-acao]');
            if (b && !b.disabled) acoes[b.dataset.acao]?.(b);
        });
        q('[data-soltar]').addEventListener('click', () => q('[data-arquivo]').click());
        q('[data-arquivo]').addEventListener('change', e => { importar([...e.target.files]); e.target.value = ''; });
        q('[data-localizar]').addEventListener('change', e => { const f = e.target.files[0]; e.target.value = ''; if (f && S && S.localizando) localizar(S.localizando, f); });
        const zona = q('[data-soltar]');
        ['dragenter', 'dragover'].forEach(t => el.addEventListener(t, e => { if (e.dataTransfer?.types?.includes('Files')) { e.preventDefault(); zona.classList.add('sobre'); } }));
        ['dragleave', 'drop'].forEach(t => el.addEventListener(t, () => zona.classList.remove('sobre')));
        el.addEventListener('drop', e => { if (e.dataTransfer?.files?.length) { e.preventDefault(); importar([...e.dataTransfer.files]); } });
        el.querySelectorAll('[data-campo-proj]').forEach(i => i.addEventListener('change', () => {
            const k = i.dataset.campoProj;
            S.p[k] = k === 'altura' ? Number(i.value) : i.value;
            redimensionarTela(); marcar(); desenhar();
        }));
        q('[data-zoom]').addEventListener('input', e => { S.zoom = Number(e.target.value); desenharLinha(); });
        q('[data-regua]').addEventListener('pointerdown', e => arrastarCursor(e));
        el.querySelectorAll('[data-faixa]').forEach(f => f.addEventListener('pointerdown', e => {
            if (e.target === f) { S.sel = null; desenharLinha(); desenharProp(); arrastarCursor(e); }
        }));
        q('[data-prop]').addEventListener('input', e => mudarProp(e.target, false));
        q('[data-prop]').addEventListener('change', e => mudarProp(e.target, true));
        q('[data-prop]').addEventListener('click', e => {
            const b = e.target.closest('[data-prop-acao]');
            if (b) acoes[b.dataset.propAcao]?.(b);
        });
        document.addEventListener('keydown', teclas, true);
        window.addEventListener('resize', () => { if (S) desenharLinha(); });
        // a prévia sempre cabe inteira no espaço que sobra (o canvas tem o tamanho real do vídeo)
        if (window.ResizeObserver) new ResizeObserver(() => ajustarVista()).observe(q('.wfv-palco'));
    }
    function ajustarVista() {
        if (!el || el.hidden) return;
        const palco = el.querySelector('.wfv-palco'), c = el.querySelector('[data-tela]');
        const pw = palco.clientWidth - 24, ph = palco.clientHeight - 24;
        if (pw <= 0 || ph <= 0) return;
        const e = Math.min(pw / c.width, ph / c.height);
        c.style.width = Math.floor(c.width * e) + 'px';
        c.style.height = Math.floor(c.height * e) + 'px';
    }

    /* ---------- abrir / fechar ---------- */
    async function abrir({ blocoId, titulo, projeto, aoSalvar }) {
        if (!el) montar();
        if (S) return;
        const p = projeto && typeof projeto === 'object' ? JSON.parse(JSON.stringify(projeto)) : novoProjeto();
        ['videos', 'textos', 'audios'].forEach(k => { if (!Array.isArray(p[k])) p[k] = []; });
        if (!p.midias || typeof p.midias !== 'object') p.midias = {};
        S = { blocoId, aoSalvar, p, t: 0, tocando: false, sel: null, zoom: 60, passos: [], refazer: [], sujo: false, faltando: new Set(), urls: new Map() };
        S.passos.push(JSON.stringify(p));
        el.querySelector('[data-titulo]').textContent = titulo || 'Editor de vídeo';
        el.querySelector('[data-zoom]').value = S.zoom;
        el.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        try { navigator.storage?.persist?.(); } catch { /* sem pedido de armazenamento persistente */ }
        await conferirMidias();
        redimensionarTela();
        desenharTudo();
        if (!Object.keys(p.midias).length) aviso('Comece importando vídeos, fotos ou músicas (botão “Importar mídia” ou arraste os arquivos).', 5000);
        else if (S.faltando.size) aviso(`${S.faltando.size} ${S.faltando.size === 1 ? 'arquivo não está' : 'arquivos não estão'} neste navegador. Use “Localizar” na lista de mídias.`, 6000);
    }
    function fechar(forcar) {
        if (!S) return;
        if (S.exportando) { aviso('Espere a exportação terminar (ou cancele).'); return; }
        if (S.sujo && !forcar && !confirm('Sair sem salvar as mudanças no cartão?')) return;
        pausar();
        pool.forEach(x => { try { x.el.pause(); x.el.removeAttribute('src'); x.el.load(); } catch { /* elemento já liberado */ } });
        pool.clear();
        imagensCache.forEach(b => { try { b.close(); } catch { /* já fechado */ } });
        imagensCache.clear();
        S.urls.forEach(u => URL.revokeObjectURL(u));
        S = null;
        el.hidden = true;
        document.documentElement.style.overflow = '';
    }

    /* ---------- mídias ---------- */
    async function conferirMidias() {
        S.faltando = new Set();
        for (const id of Object.keys(S.p.midias)) {
            let blob = null;
            try { blob = await lerArquivo('midias', id); } catch { /* banco indisponível */ }
            if (blob) S.urls.set(id, URL.createObjectURL(blob)); else S.faltando.add(id);
        }
    }
    const tipoDe = f => (f.type.startsWith('video/') ? 'video' : f.type.startsWith('audio/') ? 'audio' : f.type.startsWith('image/') ? 'imagem' : null);
    function lerMetadados(url, tipo) {
        return new Promise(ok => {
            if (tipo === 'imagem') {
                const im = new Image();
                im.onload = () => ok({ w: im.naturalWidth, h: im.naturalHeight, dur: 5, thumb: miniatura(im, im.naturalWidth, im.naturalHeight) });
                im.onerror = () => ok(null);
                im.src = url;
                return;
            }
            const m = document.createElement(tipo === 'video' ? 'video' : 'audio');
            m.preload = 'metadata'; m.muted = true; m.src = url;
            m.onerror = () => ok(null);
            const pronto = dur => {
                if (tipo !== 'video') { ok({ dur }); return; }
                m.onseeked = () => ok({ dur, w: m.videoWidth, h: m.videoHeight, thumb: miniatura(m, m.videoWidth, m.videoHeight) });
                m.currentTime = Math.min(0.5, dur / 3);
            };
            m.onloadedmetadata = () => {
                if (isFinite(m.duration) && m.duration > 0) { pronto(m.duration); return; }
                // WebM gravado pelo navegador (MediaRecorder) não traz a duração: pulando para o fim ela aparece
                m.ondurationchange = () => {
                    if (!isFinite(m.duration)) return;
                    m.ondurationchange = null;
                    const dur = m.duration;
                    m.onseeked = null;
                    pronto(dur);
                };
                m.currentTime = 1e7;
            };
        });
    }
    function miniatura(fonte, w, h) {
        try {
            const c = document.createElement('canvas'); c.width = 96; c.height = Math.max(1, Math.round(96 * h / Math.max(1, w)));
            c.getContext('2d').drawImage(fonte, 0, 0, c.width, c.height);
            return c.toDataURL('image/jpeg', 0.6);
        } catch { return ''; }
    }
    async function importar(arquivos) {
        if (!S) return;
        let n = 0;
        for (const f of arquivos) {
            const tipo = tipoDe(f);
            if (!tipo) { aviso(`“${f.name}” não é vídeo, foto nem áudio.`); continue; }
            const id = 'm' + uid();
            try { await gravarArquivo('midias', id, f); } catch { aviso('Este navegador não deixou guardar o arquivo (espaço cheio ou modo anônimo).', 5000); return; }
            const url = URL.createObjectURL(f);
            const meta = await lerMetadados(url, tipo);
            if (!meta) { URL.revokeObjectURL(url); aviso(`Não consegui abrir “${f.name}”. Formato não suportado por este navegador.`, 5000); continue; }
            S.urls.set(id, url);
            S.p.midias[id] = { nome: f.name, tipo, tam: f.size, dur: r2(meta.dur || 0), w: meta.w || 0, h: meta.h || 0, thumb: meta.thumb || '' };
            adicionarNaLinha(id, false);
            n++;
        }
        if (n) { marcar(); desenharTudo(); aviso(n === 1 ? 'Mídia importada e colocada na linha do tempo.' : `${n} mídias importadas e colocadas na linha do tempo.`); }
    }
    async function localizar(id, f) {
        const m = S.p.midias[id];
        if (!m) return;
        if (tipoDe(f) !== m.tipo) { aviso('Escolha um arquivo do mesmo tipo.'); return; }
        try { await gravarArquivo('midias', id, f); } catch { aviso('Não consegui guardar o arquivo neste navegador.'); return; }
        const velha = S.urls.get(id); if (velha) URL.revokeObjectURL(velha);
        S.urls.set(id, URL.createObjectURL(f));
        S.faltando.delete(id);
        pool.forEach((x, k) => { if (k.endsWith(':' + id)) { x.el.src = S.urls.get(id); } });
        imagensCache.delete(id);
        if (f.name !== m.nome) aviso(`Ligado a “${f.name}” (o projeto esperava “${m.nome}”).`, 4000);
        S.localizando = null;
        desenharTudo();
    }
    function adicionarNaLinha(id, comAviso = true) {
        const m = S.p.midias[id];
        if (!m) return;
        if (m.tipo === 'audio') {
            S.p.audios.push({ id: 'a' + uid(), midia: id, inicio: r2(S.p.audios.length ? S.t : 0), ini: 0, fim: m.dur || 1, vol: 1, fadeIn: 0, fadeOut: 0.5 });
        } else {
            S.p.videos.push({
                id: 'v' + uid(), midia: id, tipo: m.tipo, ini: 0, fim: m.tipo === 'imagem' ? 4 : (m.dur || 1), vel: 1, vol: 1,
                fadeIn: 0, fadeOut: 0, ajuste: 'cobrir', brilho: 100, contraste: 100, saturacao: 100, pb: 0,
            });
        }
        if (comAviso) { marcar(); desenharTudo(); }
    }

    /* ---------- tela (canvas) ---------- */
    function redimensionarTela() {
        const [w, h] = dims(S.p);
        const c = el.querySelector('[data-tela]');
        if (c.width !== w || c.height !== h) { c.width = w; c.height = h; }
        ajustarVista();
        el.querySelector('[data-campo-proj="proporcao"]').value = S.p.proporcao;
        el.querySelector('[data-campo-proj="altura"]').value = String(S.p.altura);
        el.querySelector('[data-campo-proj="fundo"]').value = S.p.fundo || '#000000';
    }
    function elemento(chave, tipo, midia) {
        let x = pool.get(chave);
        if (x) return x;
        const m = document.createElement(tipo === 'video' ? 'video' : 'audio');
        m.preload = 'auto'; m.playsInline = true; m.crossOrigin = 'anonymous';
        m.src = S.urls.get(midia) || '';
        m.addEventListener('seeked', () => { if (S && !S.tocando) desenhar(); });
        m.addEventListener('loadeddata', () => { if (S && !S.tocando) desenhar(); });
        x = { el: m, fonte: null, ganho: null };
        if (ctxAudio) ligarAudio(x);
        pool.set(chave, x);
        return x;
    }
    function ligarAudio(x) {
        if (x.ganho || !ctxAudio) return;
        try {
            x.fonte = ctxAudio.createMediaElementSource(x.el);
            x.ganho = ctxAudio.createGain();
            x.ganho.gain.value = 0;
            x.fonte.connect(x.ganho);
            x.ganho.connect(S.mestre);
        } catch { /* elemento já ligado a outro contexto */ }
    }
    function prepararAudio() {
        if (!ctxAudio) {
            const C = window.AudioContext || window.webkitAudioContext;
            if (!C) return;
            ctxAudio = new C();
        }
        if (!S.mestre) {
            S.mestre = ctxAudio.createGain();
            S.mestre.connect(ctxAudio.destination);
        }
        pool.forEach(x => ligarAudio(x));
        if (ctxAudio.state === 'suspended') ctxAudio.resume();
    }
    async function bitmap(midia) {
        if (imagensCache.has(midia)) return imagensCache.get(midia);
        const url = S.urls.get(midia);
        if (!url) return null;
        imagensCache.set(midia, null);
        try {
            const blob = await (await fetch(url)).blob();
            const b = await createImageBitmap(blob);
            imagensCache.set(midia, b);
            if (S && !S.tocando) desenhar();
            return b;
        } catch { return null; }
    }
    function desenharFonte(g, fonte, fw, fh, W, H, ajuste) {
        if (!fw || !fh) return;
        const esc = ajuste === 'conter' ? Math.min(W / fw, H / fh) : Math.max(W / fw, H / fh);
        const w = fw * esc, h = fh * esc;
        g.drawImage(fonte, (W - w) / 2, (H - h) / 2, w, h);
    }
    function quebrar(g, texto, larg) {
        const linhas = [];
        String(texto).split('\n').forEach(par => {
            let atual = '';
            par.split(/\s+/).forEach(p => {
                const tenta = atual ? atual + ' ' + p : p;
                if (g.measureText(tenta).width > larg && atual) { linhas.push(atual); atual = p; } else atual = tenta;
            });
            linhas.push(atual);
        });
        return linhas;
    }

    /** Desenha o quadro do tempo S.t e acerta os elementos de vídeo e áudio para esse tempo. */
    function desenhar() {
        if (!S) return;
        const c = el.querySelector('[data-tela]'), g = c.getContext('2d');
        const W = c.width, H = c.height, t = S.t;
        g.save();
        g.filter = 'none'; g.globalAlpha = 1;
        g.fillStyle = S.p.fundo || '#000'; g.fillRect(0, 0, W, H);

        // vídeo / foto
        const seq = sequencia();
        const ativo = seq.find(s => t >= s.inicio && t < s.inicio + s.dur)
            // no último instante mostra o último quadro em vez de tela vazia
            || (seq.length && Math.abs(t - (seq[seq.length - 1].inicio + seq[seq.length - 1].dur)) < 0.05 ? seq[seq.length - 1] : null);
        const usados = new Set();
        if (ativo && !S.faltando.has(ativo.c.midia)) {
            const cl = ativo.c, local = t - ativo.inicio;
            const f = suave(local, ativo.dur, cl.fadeIn, cl.fadeOut);
            if (temFiltro(g)) g.filter = `brightness(${cl.brilho ?? 100}%) contrast(${cl.contraste ?? 100}%) saturate(${cl.saturacao ?? 100}%) grayscale(${cl.pb ?? 0}%)`;
            if (cl.tipo === 'imagem') {
                const b = imagensCache.get(cl.midia);
                if (b) desenharFonte(g, b, b.width, b.height, W, H, cl.ajuste); else bitmap(cl.midia);
            } else {
                const chave = 'v:' + cl.midia;
                usados.add(chave);
                const x = elemento(chave, 'video', cl.midia), v = x.el;
                const alvo = cl.ini + local * (cl.vel || 1);
                if (S.tocando) {
                    v.playbackRate = cl.vel || 1;
                    if (v.paused) { try { v.currentTime = alvo; } catch { /* ainda carregando */ } v.play().catch(() => {}); }
                    else if (Math.abs(v.currentTime - alvo) > 0.3) { try { v.currentTime = alvo; } catch { /* ainda carregando */ } }
                } else if (Math.abs(v.currentTime - alvo) > 1 / FPS / 2) {
                    try { v.currentTime = alvo; } catch { /* ainda carregando */ }
                }
                if (x.ganho) x.ganho.gain.setTargetAtTime((cl.vol ?? 1) * f, ctxAudio.currentTime, 0.02);
                if (v.readyState >= 2) desenharFonte(g, v, v.videoWidth, v.videoHeight, W, H, cl.ajuste);
            }
            g.filter = 'none';
            if (f < 1) { g.globalAlpha = 1 - f; g.fillStyle = '#000'; g.fillRect(0, 0, W, H); g.globalAlpha = 1; }
        } else if (ativo) {
            g.fillStyle = '#5a2b2b'; g.fillRect(0, 0, W, H);
            g.fillStyle = '#fff'; g.font = `${Math.round(H / 24)}px system-ui`; g.textAlign = 'center';
            g.fillText('Arquivo não está neste navegador: ' + (S.p.midias[ativo.c.midia]?.nome || ''), W / 2, H / 2);
        }

        // textos por cima
        S.p.textos.forEach(x => {
            if (t < x.inicio || t >= x.inicio + x.dur || !String(x.texto).trim()) return;
            const f = suave(t - x.inicio, x.dur, x.fade || 0, x.fade || 0);
            const tam = Math.round((x.tam || 48) * H / 720);
            g.globalAlpha = f;
            g.font = `${x.negrito === false ? 400 : 700} ${tam}px system-ui, "Segoe UI", sans-serif`;
            g.textAlign = 'center'; g.textBaseline = 'middle';
            const linhas = quebrar(g, x.texto, W * 0.86);
            const alt = tam * 1.2, cx = W * (x.x ?? 50) / 100, cy = H * (x.y ?? 85) / 100 - (linhas.length - 1) * alt / 2;
            if (x.fundo) {
                const larg = Math.max(...linhas.map(l => g.measureText(l).width)) + tam;
                g.fillStyle = x.fundo; g.globalAlpha = f * 0.75;
                g.fillRect(cx - larg / 2, cy - alt / 2 - tam * 0.15, larg, alt * linhas.length + tam * 0.3);
                g.globalAlpha = f;
            }
            g.fillStyle = x.cor || '#ffffff';
            g.shadowColor = 'rgba(0,0,0,.6)'; g.shadowBlur = x.fundo ? 0 : tam / 6;
            linhas.forEach((l, i) => g.fillText(l, cx, cy + i * alt));
            g.shadowBlur = 0;
        });
        g.restore();

        // áudio (músicas e narração)
        S.p.audios.forEach(a => {
            if (S.faltando.has(a.midia)) return;
            const dur = a.fim - a.ini, local = t - a.inicio;
            const chave = 'a:' + a.id;
            const dentro = local >= 0 && local < dur;
            if (!dentro && !pool.has(chave)) return;
            const x = elemento(chave, 'audio', a.midia);
            if (dentro && S.tocando) {
                usados.add(chave);
                const alvo = a.ini + local;
                if (x.el.paused) { try { x.el.currentTime = alvo; } catch { /* carregando */ } x.el.play().catch(() => {}); }
                else if (Math.abs(x.el.currentTime - alvo) > 0.3) { try { x.el.currentTime = alvo; } catch { /* carregando */ } }
                if (x.ganho) x.ganho.gain.setTargetAtTime((a.vol ?? 1) * suave(local, dur, a.fadeIn, a.fadeOut), ctxAudio.currentTime, 0.02);
            }
        });
        // o que não está tocando neste instante fica parado e mudo
        pool.forEach((x, k) => {
            if (usados.has(k)) return;
            if (x.ganho) x.ganho.gain.setTargetAtTime(0, ctxAudio.currentTime, 0.01);
            if (!x.el.paused) x.el.pause();
        });
        atualizarTempo();
    }
    function temFiltro(g) { return 'filter' in g; }

    /* ---------- tocar ---------- */
    function tocar() {
        if (!S || S.tocando) return;
        const total = duracaoTotal();
        if (!total) { aviso('A linha do tempo está vazia.'); return; }
        if (S.t >= total - 0.05) S.t = 0;
        prepararAudio();
        S.tocando = true;
        S.relogio = performance.now() - S.t * 1000;
        el.querySelector('[data-btn-tocar]').textContent = '⏸ Pausar';
        const passo = () => {
            if (!S || !S.tocando) return;
            S.t = (performance.now() - S.relogio) / 1000;
            if (S.t >= duracaoTotal()) {
                S.t = duracaoTotal();
                pausar();
                desenhar();
                if (S.aoTerminar) { const f = S.aoTerminar; S.aoTerminar = null; f(); }
                return;
            }
            desenhar();
            posicionarCursor(true);
            S.raf = requestAnimationFrame(passo);
        };
        S.raf = requestAnimationFrame(passo);
    }
    function pausar() {
        if (!S) return;
        S.tocando = false;
        cancelAnimationFrame(S.raf);
        pool.forEach(x => { if (!x.el.paused) x.el.pause(); if (x.ganho && ctxAudio) x.ganho.gain.setTargetAtTime(0, ctxAudio.currentTime, 0.01); });
        const b = el.querySelector('[data-btn-tocar]'); if (b) b.textContent = '▶ Tocar';
    }
    function irPara(t) {
        S.t = clamp(t, 0, duracaoTotal());
        if (S.tocando) S.relogio = performance.now() - S.t * 1000;
        desenhar(); posicionarCursor(false);
    }

    /* ---------- linha do tempo ---------- */
    const px = s => s * S.zoom;
    function desenharTudo() { desenharMidias(); desenharLinha(); desenharProp(); desenhar(); }
    function desenharMidias() {
        const lista = el.querySelector('[data-midias]');
        const ids = Object.keys(S.p.midias);
        el.querySelector('[data-n-midias]').textContent = ids.length ? `(${ids.length})` : '';
        lista.innerHTML = ids.map(id => {
            const m = S.p.midias[id], falta = S.faltando.has(id);
            const icone = m.tipo === 'audio' ? '♪' : m.tipo === 'imagem' ? '🖼' : '🎬';
            const info = m.tipo === 'imagem' ? `${m.w}×${m.h}` : tempo(m.dur) + (m.w ? ` · ${m.w}×${m.h}` : '');
            return `<div class="wfv-midia" data-midia="${id}">
                <div class="mini" style="${m.thumb ? `background-image:url('${m.thumb}')` : ''}">${m.thumb ? '' : icone}</div>
                <div class="nome"><b title="${esc(m.nome)}">${esc(m.nome)}</b><small class="${falta ? 'falta' : ''}">${falta ? '⚠ não está neste navegador' : info}</small></div>
                ${falta ? `<button class="wfv-b" data-acao="localizar" data-id="${id}" title="Escolher o arquivo de novo">Localizar</button>`
                        : `<button class="add" data-acao="adicionar" data-id="${id}" title="Colocar na linha do tempo">＋</button>`}
            </div>`;
        }).join('') || '<p class="wfv-dica">Nenhuma mídia ainda.</p>';
    }
    function desenharLinha() {
        if (!S) return;
        const total = duracaoTotal();
        const rolar = el.querySelector('[data-rolar]');
        const largura = Math.max(rolar.clientWidth, px(total + 10));
        el.querySelector('[data-faixas]').style.width = largura + 'px';
        el.querySelector('[data-duracao]').textContent = 'Duração: ' + tempo(total);
        // régua
        const passo = S.zoom >= 120 ? 1 : S.zoom >= 50 ? 2 : S.zoom >= 25 ? 5 : 10;
        let regua = '';
        for (let s = 0; px(s) < largura; s += passo) regua += `<i style="left:${px(s)}px"></i><span style="left:${px(s)}px">${tempo(s).replace(/\.\d$/, '')}</span>`;
        el.querySelector('[data-regua]').innerHTML = regua;
        // clipes
        const clipe = (tipo, item, ini, dur, rotulo, cls) => `<div class="wfv-clipe ${cls}${S.sel && S.sel.id === item.id ? ' sel' : ''}" data-tipo="${tipo}" data-id="${item.id}"
            style="left:${px(ini)}px;width:${Math.max(14, px(dur))}px" title="${esc(rotulo)}">
            <div class="alca e" data-alca="e"></div><span>${esc(rotulo)}</span><div class="alca d" data-alca="d"></div></div>`;
        el.querySelector('[data-faixa="videos"]').innerHTML = sequencia().map(s => {
            const m = S.p.midias[s.c.midia] || {};
            return clipe('videos', s.c, s.inicio, s.dur, (m.nome || '?') + (s.c.vel && s.c.vel !== 1 ? ` · ${s.c.vel}×` : ''), (S.faltando.has(s.c.midia) ? 'falta ' : '') + (s.c.tipo === 'imagem' ? 'imagem' : 'video'));
        }).join('');
        el.querySelector('[data-faixa="textos"]').innerHTML = S.p.textos.map(x => clipe('textos', x, x.inicio, x.dur, x.texto || 'Texto', 'texto')).join('');
        el.querySelector('[data-faixa="audios"]').innerHTML = S.p.audios.map(a => clipe('audios', a, a.inicio, a.fim - a.ini, S.p.midias[a.midia]?.nome || '?', (S.faltando.has(a.midia) ? 'falta ' : '') + 'audio')).join('');
        el.querySelectorAll('.wfv-clipe').forEach(c => c.addEventListener('pointerdown', e => arrastarClipe(e, c)));
        posicionarCursor(false);
    }
    function posicionarCursor(seguir) {
        const c = el.querySelector('[data-cursor]');
        c.style.left = px(S.t) + 'px';
        if (seguir) {
            const r = el.querySelector('[data-rolar]');
            if (px(S.t) > r.scrollLeft + r.clientWidth - 40) r.scrollLeft = px(S.t) - 80;
        }
    }
    function atualizarTempo() {
        el.querySelector('[data-tempo]').textContent = `${tempo(S.t)} / ${tempo(duracaoTotal())}`;
    }
    function arrastarCursor(e) {
        const r = el.querySelector('[data-faixas]').getBoundingClientRect();
        const mover = ev => irPara((ev.clientX - r.left) / S.zoom);
        mover(e);
        const fim = () => { window.removeEventListener('pointermove', mover); window.removeEventListener('pointerup', fim); };
        window.addEventListener('pointermove', mover); window.addEventListener('pointerup', fim);
    }
    function itemPor(tipo, id) { return S.p[tipo].find(x => x.id === id); }
    function arrastarClipe(e, div) {
        e.preventDefault(); e.stopPropagation();
        const tipo = div.dataset.tipo, id = div.dataset.id, item = itemPor(tipo, id);
        if (!item) return;
        S.sel = { tipo, id }; desenharProp();
        el.querySelectorAll('.wfv-clipe.sel').forEach(c => c.classList.remove('sel')); div.classList.add('sel');
        const alca = e.target.dataset.alca, x0 = e.clientX, orig = { ...item };
        const m = S.p.midias[item.midia] || {};
        const max = m.tipo === 'imagem' || tipo === 'textos' ? Infinity : (m.dur || orig.fim);
        let mexeu = false;
        const ordem0 = S.p.videos.indexOf(item);
        const mover = ev => {
            const ds = (ev.clientX - x0) / S.zoom;
            if (Math.abs(ev.clientX - x0) > 3) mexeu = true;
            if (!mexeu) return;
            if (alca === 'e') {
                if (tipo === 'videos') item.ini = r2(clamp(orig.ini + ds * (orig.vel || 1), 0, orig.fim - 0.1));
                else if (tipo === 'textos') { const d = clamp(ds, -orig.inicio, orig.dur - 0.2); item.inicio = r2(orig.inicio + d); item.dur = r2(orig.dur - d); }
                else { const d = clamp(ds, Math.max(-orig.ini, -orig.inicio), orig.fim - orig.ini - 0.2); item.ini = r2(orig.ini + d); item.inicio = r2(orig.inicio + d); }
            } else if (alca === 'd') {
                if (tipo === 'videos') item.fim = r2(clamp(orig.fim + ds * (orig.vel || 1), orig.ini + 0.1, max));
                else if (tipo === 'textos') item.dur = r2(Math.max(0.2, orig.dur + ds));
                else item.fim = r2(clamp(orig.fim + ds, orig.ini + 0.2, max));
            } else if (tipo === 'videos') {
                // arrastar um clipe de vídeo troca a ordem da sequência
                const seq = sequencia(), cursor = seq[ordem0].inicio + seq[ordem0].dur / 2 + ds;
                const outros = S.p.videos.filter(c => c !== item);
                let i = 0, acc = 0;
                for (const c of outros) { const d = durClipe(c); if (cursor > acc + d / 2) i++; acc += d; }
                if (S.p.videos.indexOf(item) !== i) { outros.splice(i, 0, item); S.p.videos = outros; }
            } else {
                // textos e áudios andam livres (encostam no cursor quando passam perto)
                let ini = Math.max(0, orig.inicio + ds);
                if (Math.abs(ini - S.t) * S.zoom < 8) ini = S.t;
                item.inicio = r2(ini);
            }
            desenharLinha(); desenhar(); desenharProp();
        };
        const fim = () => {
            window.removeEventListener('pointermove', mover); window.removeEventListener('pointerup', fim);
            if (mexeu) marcar(); else if (tipo !== 'videos') irPara(Math.max(S.t, 0));
        };
        window.addEventListener('pointermove', mover); window.addEventListener('pointerup', fim);
    }

    /* ---------- propriedades ---------- */
    const faixa = (rot, campo, min, max, passo, valor, suf = '') => `<label><span>${rot}</span>
        <input type="range" data-campo="${campo}" min="${min}" max="${max}" step="${passo}" value="${valor}"><span class="valor">${valor}${suf}</span></label>`;
    const numero = (rot, campo, min, passo, valor) => `<label><span>${rot}</span><input type="number" data-campo="${campo}" min="${min}" step="${passo}" value="${valor}"> s</label>`;
    function desenharProp() {
        const caixa = el.querySelector('[data-prop]'), tit = el.querySelector('[data-prop-titulo]');
        const item = S.sel && itemPor(S.sel.tipo, S.sel.id);
        if (!item) {
            tit.textContent = 'Propriedades';
            caixa.innerHTML = `<p class="wfv-dica">Clique num clipe da linha do tempo para ajustar corte, velocidade, volume, cor e transições.</p>
                <p class="wfv-dica">Arraste as pontas de um clipe para cortar. Arraste o meio de um clipe de vídeo para mudar a ordem.</p>`;
            return;
        }
        const m = S.p.midias[item.midia] || {};
        if (S.sel.tipo === 'videos') {
            tit.textContent = item.tipo === 'imagem' ? 'Foto' : 'Clipe de vídeo';
            caixa.innerHTML = `<p class="wfv-dica" style="word-break:break-all">${esc(m.nome || '')}</p>
                ${item.tipo === 'imagem'
                    ? numero('Duração', 'durImagem', 0.2, 0.1, r2(item.fim - item.ini))
                    : numero('Começa em', 'ini', 0, 0.1, item.ini) + numero('Termina em', 'fim', 0.1, 0.1, item.fim)
                      + faixa('Velocidade', 'vel', 0.25, 4, 0.25, item.vel ?? 1, '×') + faixa('Volume', 'vol', 0, 2, 0.05, item.vol ?? 1)}
                <label><span>Enquadrar</span><select data-campo="ajuste"><option value="cobrir"${item.ajuste !== 'conter' ? ' selected' : ''}>Preencher a tela</option><option value="conter"${item.ajuste === 'conter' ? ' selected' : ''}>Mostrar inteiro</option></select></label>
                ${faixa('Entrada suave', 'fadeIn', 0, 3, 0.1, item.fadeIn || 0, 's')}${faixa('Saída suave', 'fadeOut', 0, 3, 0.1, item.fadeOut || 0, 's')}
                ${faixa('Brilho', 'brilho', 30, 200, 1, item.brilho ?? 100, '%')}${faixa('Contraste', 'contraste', 30, 200, 1, item.contraste ?? 100, '%')}
                ${faixa('Saturação', 'saturacao', 0, 250, 1, item.saturacao ?? 100, '%')}${faixa('Preto e branco', 'pb', 0, 100, 1, item.pb ?? 0, '%')}
                <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="wfv-b" data-prop-acao="moverAntes">← Antes</button><button class="wfv-b" data-prop-acao="moverDepois">Depois →</button><button class="wfv-b" data-prop-acao="resetCor">Cor original</button></div>`;
        } else if (S.sel.tipo === 'textos') {
            tit.textContent = 'Texto';
            caixa.innerHTML = `<textarea data-campo="texto" placeholder="Escreva o título ou a legenda">${esc(item.texto)}</textarea>
                ${numero('Começa em', 'inicio', 0, 0.1, item.inicio)}${numero('Duração', 'dur', 0.2, 0.1, item.dur)}
                ${faixa('Tamanho', 'tam', 14, 160, 1, item.tam || 48)}
                <label><span>Cor</span><input type="color" data-campo="cor" value="${item.cor || '#ffffff'}"></label>
                <label><span>Faixa atrás</span><input type="checkbox" data-campo="temFundo"${item.fundo ? ' checked' : ''}> <input type="color" data-campo="fundo" value="${item.fundo || '#000000'}"></label>
                <label><span>Negrito</span><input type="checkbox" data-campo="negrito"${item.negrito !== false ? ' checked' : ''}></label>
                ${faixa('Posição X', 'x', 5, 95, 1, item.x ?? 50, '%')}${faixa('Posição Y', 'y', 5, 95, 1, item.y ?? 85, '%')}
                ${faixa('Aparecer suave', 'fade', 0, 2, 0.1, item.fade ?? 0.3, 's')}
                <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="wfv-b" data-prop-acao="textoTopo">Em cima</button><button class="wfv-b" data-prop-acao="textoMeio">No meio</button><button class="wfv-b" data-prop-acao="textoBaixo">Embaixo</button></div>`;
        } else {
            tit.textContent = 'Áudio';
            caixa.innerHTML = `<p class="wfv-dica" style="word-break:break-all">${esc(m.nome || '')}</p>
                ${numero('Entra em', 'inicio', 0, 0.1, item.inicio)}${numero('Do trecho', 'ini', 0, 0.1, item.ini)}${numero('Até', 'fim', 0.2, 0.1, item.fim)}
                ${faixa('Volume', 'vol', 0, 2, 0.05, item.vol ?? 1)}
                ${faixa('Entrada suave', 'fadeIn', 0, 5, 0.1, item.fadeIn || 0, 's')}${faixa('Saída suave', 'fadeOut', 0, 5, 0.1, item.fadeOut || 0, 's')}`;
        }
    }
    function mudarProp(inp, final) {
        const item = S.sel && itemPor(S.sel.tipo, S.sel.id);
        const k = inp.dataset.campo;
        if (!item || !k) return;
        const m = S.p.midias[item.midia] || {};
        let v = inp.type === 'checkbox' ? inp.checked : inp.value;
        if (inp.type === 'range' || inp.type === 'number') v = Number(v);
        if (inp.type === 'number' && !isFinite(v)) return;
        switch (k) {
            case 'durImagem': item.fim = r2(item.ini + Math.max(0.2, v)); break;
            case 'ini': item.ini = r2(clamp(v, 0, item.fim - 0.1)); break;
            case 'fim': item.fim = r2(clamp(v, item.ini + 0.1, m.dur || v)); break;
            case 'inicio': item.inicio = r2(Math.max(0, v)); break;
            case 'dur': item.dur = r2(Math.max(0.2, v)); break;
            case 'temFundo': item.fundo = v ? (el.querySelector('[data-campo="fundo"]')?.value || '#000000') : ''; break;
            case 'fundo': if (el.querySelector('[data-campo="temFundo"]')?.checked) item.fundo = v; break;
            default: item[k] = v;
        }
        const rot = inp.parentElement.querySelector('.valor');
        if (rot) rot.textContent = v + (rot.textContent.match(/[%s×]$/)?.[0] || '');
        desenharLinha(); desenhar();
        if (final) marcar();
    }

    /* ---------- desfazer / salvar ---------- */
    function marcar() {
        const txt = JSON.stringify(S.p);
        if (S.passos[S.passos.length - 1] === txt) return;
        S.passos.push(txt);
        if (S.passos.length > MAX_PASSOS) S.passos.shift();
        S.refazer = [];
        S.sujo = true;
    }
    function voltarPara(txt) {
        S.p = JSON.parse(txt);
        if (S.sel && !itemPor(S.sel.tipo, S.sel.id)) S.sel = null;
        redimensionarTela(); desenharTudo();
    }
    function capa() {
        const c = el.querySelector('[data-tela]');
        const larg = 480, alt = Math.round(larg * c.height / c.width);
        const k = document.createElement('canvas'); k.width = larg; k.height = alt;
        k.getContext('2d').drawImage(c, 0, 0, larg, alt);
        return k.toDataURL('image/jpeg', 0.72);
    }

    /* ---------- exportar ---------- */
    function tipoSaida() {
        if (!window.MediaRecorder) return null;
        return TIPOS_SAIDA.find(([t]) => MediaRecorder.isTypeSupported(t)) || null;
    }
    function dialogo(html) {
        const d = document.createElement('div');
        d.className = 'wfv-dialogo';
        d.innerHTML = `<div>${html}</div>`;
        el.appendChild(d);
        return d;
    }
    async function exportar() {
        const total = duracaoTotal();
        if (!total) { aviso('Nada para exportar: a linha do tempo está vazia.'); return; }
        if (S.faltando.size && [...S.p.videos, ...S.p.audios].some(c => S.faltando.has(c.midia))) {
            aviso('Localize os arquivos que faltam antes de exportar.', 4000); return;
        }
        const saida = tipoSaida();
        if (!saida) { aviso('Este navegador não consegue gravar vídeo. Use o Chrome, Edge ou Firefox atualizados.', 5000); return; }
        pausar();
        prepararAudio();
        const tela = el.querySelector('[data-tela]');
        const destino = ctxAudio.createMediaStreamDestination();
        S.mestre.connect(destino);
        const fluxo = new MediaStream([...tela.captureStream(FPS).getVideoTracks(), ...destino.stream.getAudioTracks()]);
        const gravador = new MediaRecorder(fluxo, { mimeType: saida[0], videoBitsPerSecond: QUALIDADES[S.p.altura] || 5000000, audioBitsPerSecond: 160000 });
        const partes = [];
        gravador.ondataavailable = e => { if (e.data && e.data.size) partes.push(e.data); };
        const d = dialogo(`<b>Exportando o vídeo…</b>
            <p class="wfv-dica">O navegador grava o vídeo tocando do começo ao fim (${tempo(total)}). Deixe esta aba aberta e em primeiro plano até terminar.</p>
            <div class="wfv-barra-prog"><i data-prog></i></div><span class="wfv-dica" data-prog-txt>0%</span>
            <div style="display:flex;justify-content:flex-end"><button class="wfv-b" data-cancelar>Cancelar</button></div>`);
        let cancelado = false;
        const barra = d.querySelector('[data-prog]'), txt = d.querySelector('[data-prog-txt]');
        const prog = setInterval(() => { const p = Math.min(100, Math.round(S.t / total * 100)); barra.style.width = p + '%'; txt.textContent = p + '%'; }, 250);
        S.exportando = true;
        const terminar = () => new Promise(ok => { gravador.onstop = ok; if (gravador.state !== 'inactive') gravador.stop(); else ok(); });
        let pararEspera = () => {};
        d.querySelector('[data-cancelar]').addEventListener('click', () => { cancelado = true; S.aoTerminar = null; pausar(); pararEspera(); });
        S.t = 0; desenhar();
        // dá tempo de os primeiros quadros carregarem antes de começar a gravar
        await new Promise(r => setTimeout(r, 600));
        gravador.start(500);
        // toca do começo ao fim; termina sozinho (aoTerminar) ou pelo Cancelar
        await new Promise(ok => { pararEspera = ok; S.aoTerminar = ok; tocar(); });
        await new Promise(r => setTimeout(r, 300));
        await terminar();
        clearInterval(prog);
        try { S.mestre.disconnect(destino); } catch { /* já desligado */ }
        S.exportando = false;
        if (cancelado) { d.remove(); aviso('Exportação cancelada.'); return; }
        const blob = new Blob(partes, { type: saida[0].split(';')[0] });
        try { await gravarArquivo('exportes', S.blocoId, blob); } catch { /* sem espaço: só baixa */ }
        const url = URL.createObjectURL(blob);
        const nome = (el.querySelector('[data-titulo]').textContent || 'video').replace(/[\\/:*?"<>|]+/g, '').trim() || 'video';
        d.firstElementChild.innerHTML = `<b>Vídeo pronto</b>
            <video src="${url}" controls playsinline></video>
            <span class="wfv-dica">${saida[1].toUpperCase()} · ${(blob.size / 1048576).toFixed(1)} MB · ${tempo(total)}. Também fica guardado neste navegador: o botão “Assistir” do cartão toca de novo.</span>
            <div style="display:flex;gap:6px;justify-content:flex-end"><a class="wfv-b prim" href="${url}" download="${esc(nome)}.${saida[1]}">⇩ Baixar</a><button class="wfv-b" data-fechar>Fechar</button></div>`;
        d.querySelector('[data-fechar]').addEventListener('click', () => { d.remove(); URL.revokeObjectURL(url); });
        S.t = 0; desenhar(); posicionarCursor(false);
    }

    /* ---------- ações ---------- */
    const acoes = {
        importar: () => el.querySelector('[data-arquivo]').click(),
        adicionar: b => { adicionarNaLinha(b.dataset.id); aviso('Colocado na linha do tempo.'); },
        localizar: b => { S.localizando = b.dataset.id; el.querySelector('[data-localizar]').click(); },
        novoTexto: () => {
            const x = { id: 't' + uid(), texto: 'Seu texto aqui', inicio: r2(S.t), dur: 3, tam: 56, cor: '#ffffff', fundo: '', negrito: true, x: 50, y: 85, fade: 0.3 };
            S.p.textos.push(x); S.sel = { tipo: 'textos', id: x.id };
            marcar(); desenharTudo();
            setTimeout(() => { const t = el.querySelector('[data-campo="texto"]'); if (t) { t.focus(); t.select(); } }, 0);
        },
        tocar: () => (S.tocando ? pausar() : tocar()),
        inicio: () => irPara(0),
        fim: () => irPara(duracaoTotal()),
        voltar: () => irPara(S.t - 1 / FPS),
        avancar: () => irPara(S.t + 1 / FPS),
        dividir: () => {
            const t = S.t;
            // divide o item selecionado; sem seleção, o clipe de vídeo que está no cursor
            let sel = S.sel && itemPor(S.sel.tipo, S.sel.id) ? S.sel : null;
            if (!sel) { const s = sequencia().find(s => t > s.inicio && t < s.inicio + s.dur); if (s) sel = { tipo: 'videos', id: s.c.id }; }
            if (!sel) { aviso('Ponha o cursor em cima de um clipe para dividir.'); return; }
            const item = itemPor(sel.tipo, sel.id);
            if (sel.tipo === 'videos') {
                const s = sequencia().find(s => s.c === item);
                const local = t - s.inicio;
                if (local < 0.1 || local > s.dur - 0.1) { aviso('O cursor precisa estar dentro do clipe.'); return; }
                const corte = r2(item.ini + local * (item.vel || 1));
                const novo = { ...item, id: 'v' + uid(), ini: corte, fadeIn: 0 };
                item.fim = corte; item.fadeOut = 0;
                S.p.videos.splice(S.p.videos.indexOf(item) + 1, 0, novo);
                S.sel = { tipo: 'videos', id: novo.id };
            } else if (sel.tipo === 'textos') {
                const local = t - item.inicio;
                if (local < 0.2 || local > item.dur - 0.2) { aviso('O cursor precisa estar dentro do texto.'); return; }
                const novo = { ...item, id: 't' + uid(), inicio: r2(t), dur: r2(item.dur - local) };
                item.dur = r2(local);
                S.p.textos.push(novo); S.sel = { tipo: 'textos', id: novo.id };
            } else {
                const local = t - item.inicio;
                if (local < 0.2 || local > item.fim - item.ini - 0.2) { aviso('O cursor precisa estar dentro do áudio.'); return; }
                const novo = { ...item, id: 'a' + uid(), inicio: r2(t), ini: r2(item.ini + local), fadeIn: 0 };
                item.fim = r2(item.ini + local); item.fadeOut = 0;
                S.p.audios.push(novo); S.sel = { tipo: 'audios', id: novo.id };
            }
            marcar(); desenharTudo();
        },
        duplicar: () => {
            const item = S.sel && itemPor(S.sel.tipo, S.sel.id);
            if (!item) { aviso('Selecione um clipe para duplicar.'); return; }
            const novo = { ...item, id: S.sel.tipo[0] + uid() };
            if (S.sel.tipo === 'videos') S.p.videos.splice(S.p.videos.indexOf(item) + 1, 0, novo);
            else { novo.inicio = r2(item.inicio + (S.sel.tipo === 'textos' ? item.dur : item.fim - item.ini)); S.p[S.sel.tipo].push(novo); }
            S.sel = { tipo: S.sel.tipo, id: novo.id };
            marcar(); desenharTudo();
        },
        excluir: () => {
            const item = S.sel && itemPor(S.sel.tipo, S.sel.id);
            if (!item) { aviso('Selecione um clipe para excluir.'); return; }
            S.p[S.sel.tipo] = S.p[S.sel.tipo].filter(x => x !== item);
            if (S.sel.tipo === 'audios') { const x = pool.get('a:' + item.id); if (x) { x.el.pause(); pool.delete('a:' + item.id); } }
            S.sel = null;
            irPara(Math.min(S.t, duracaoTotal()));
            marcar(); desenharTudo();
        },
        moverAntes: () => { const i = S.p.videos.findIndex(c => c.id === S.sel?.id); if (i > 0) { S.p.videos.splice(i - 1, 0, S.p.videos.splice(i, 1)[0]); marcar(); desenharTudo(); } },
        moverDepois: () => { const i = S.p.videos.findIndex(c => c.id === S.sel?.id); if (i >= 0 && i < S.p.videos.length - 1) { S.p.videos.splice(i + 1, 0, S.p.videos.splice(i, 1)[0]); marcar(); desenharTudo(); } },
        resetCor: () => { const c = itemPor('videos', S.sel?.id); if (c) { Object.assign(c, { brilho: 100, contraste: 100, saturacao: 100, pb: 0 }); marcar(); desenharTudo(); } },
        textoTopo: () => { const x = itemPor('textos', S.sel?.id); if (x) { x.y = 12; marcar(); desenharTudo(); } },
        textoMeio: () => { const x = itemPor('textos', S.sel?.id); if (x) { x.y = 50; marcar(); desenharTudo(); } },
        textoBaixo: () => { const x = itemPor('textos', S.sel?.id); if (x) { x.y = 85; marcar(); desenharTudo(); } },
        desfazer: () => { if (S.passos.length < 2) return; S.refazer.push(S.passos.pop()); voltarPara(S.passos[S.passos.length - 1]); S.sujo = true; },
        refazer: () => { const p = S.refazer.pop(); if (!p) return; S.passos.push(p); voltarPara(p); S.sujo = true; },
        exportar: () => exportar(),
        salvar: async b => {
            pausar();
            const total = duracaoTotal();
            // capa: um quadro do começo do vídeo
            const t0 = S.t; S.t = Math.min(0.5, total / 2); desenhar();
            await new Promise(r => setTimeout(r, 350));
            desenhar();
            const img = capa();
            S.t = t0; desenhar();
            if (b) b.disabled = true;
            try {
                await S.aoSalvar({ projeto: JSON.parse(JSON.stringify(S.p)), capa: img, dur: r2(total) });
                S.sujo = false;
                aviso('Projeto salvo no cartão.');
            } catch {
                aviso('Não consegui salvar. Verifique a conexão e tente de novo.', 4000);
            } finally { if (b) b.disabled = false; }
        },
        fechar: () => fechar(false),
    };

    function teclas(e) {
        if (!S || el.hidden) return;
        const alvo = e.target;
        const digitando = alvo && (alvo.tagName === 'TEXTAREA' || (alvo.tagName === 'INPUT' && !['range', 'checkbox', 'color'].includes(alvo.type)));
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); const d = el.querySelector('.wfv-dialogo'); if (d && !S.exportando) d.remove(); else fechar(false); return; }
        if (digitando) return;
        const ctrl = e.ctrlKey || e.metaKey, k = e.key.toLowerCase();
        let feito = true;
        if (ctrl && k === 'z' && !e.shiftKey) acoes.desfazer();
        else if (ctrl && (k === 'y' || (k === 'z' && e.shiftKey))) acoes.refazer();
        else if (ctrl && k === 's') acoes.salvar();
        else if (ctrl) feito = false;
        else if (e.key === ' ') acoes.tocar();
        else if (k === 's') acoes.dividir();
        else if (k === 't') acoes.novoTexto();
        else if (e.key === 'Delete' || e.key === 'Backspace') acoes.excluir();
        else if (e.key === 'ArrowLeft') irPara(S.t - (e.shiftKey ? 1 : 1 / FPS));
        else if (e.key === 'ArrowRight') irPara(S.t + (e.shiftKey ? 1 : 1 / FPS));
        else if (e.key === 'Home') irPara(0);
        else if (e.key === 'End') irPara(duracaoTotal());
        else feito = false;
        if (feito) { e.preventDefault(); e.stopPropagation(); }
    }

    let avisoT = null;
    function aviso(txt, ms = 2600) {
        const a = el.querySelector('[data-aviso]');
        a.textContent = txt; a.classList.add('ver');
        clearTimeout(avisoT); avisoT = setTimeout(() => a.classList.remove('ver'), ms);
    }

    /* ---------- assistir o último vídeo exportado (botão do cartão) ---------- */
    async function assistir({ blocoId, titulo }) {
        if (!el) montar();
        let blob = null;
        try { blob = await lerArquivo('exportes', blocoId); } catch { /* banco indisponível */ }
        const fundo = document.createElement('div');
        fundo.className = 'wfv-dialogo';
        fundo.style.position = 'fixed'; fundo.style.zIndex = '95';
        const url = blob ? URL.createObjectURL(blob) : '';
        const nome = String(titulo || 'video').replace(/[\\/:*?"<>|]+/g, '').trim() || 'video';
        const ext = blob && /mp4/.test(blob.type) ? 'mp4' : 'webm';
        fundo.innerHTML = `<div style="font:13px/1.4 system-ui,sans-serif;color:#e5e7eb">
            <b>${esc(titulo || 'Vídeo')}</b>
            ${blob ? `<video src="${url}" controls autoplay playsinline></video>`
                   : '<p class="wfv-dica">O vídeo exportado fica guardado no navegador em que foi exportado. Abra o editor neste aparelho e use “Exportar vídeo”.</p>'}
            <div style="display:flex;gap:6px;justify-content:flex-end">${blob ? `<a class="wfv-b prim" href="${url}" download="${esc(nome)}.${ext}">⇩ Baixar</a>` : ''}<button class="wfv-b" data-fechar>Fechar</button></div></div>`;
        // o diálogo usa os estilos do editor (classe .wfv): fica dentro de uma caixa transparente com essa classe
        const caixa = document.createElement('div');
        caixa.className = 'wfv'; caixa.style.cssText = 'display:block;background:transparent;pointer-events:none';
        fundo.style.pointerEvents = 'auto';
        caixa.appendChild(fundo);
        document.body.appendChild(caixa);
        const sair = () => { caixa.remove(); if (url) URL.revokeObjectURL(url); document.removeEventListener('keydown', tecla); };
        const tecla = e => { if (e.key === 'Escape') sair(); };
        fundo.addEventListener('click', e => { if (e.target === fundo || e.target.closest('[data-fechar]')) sair(); });
        document.addEventListener('keydown', tecla);
    }

    window.WFEditorVideo = { abrir, assistir, fechar: () => fechar(true) };
})();
