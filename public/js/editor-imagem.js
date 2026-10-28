/* Work Flower · Editor de imagem (estilo Photoshop), separado do bloco "Imagem" comum.
   window.WFEditorImagem.abrir({ titulo, projeto, imagem, aoSalvar }) abre o editor em tela cheia.
     projeto  = { v: 1, w, h, camadas: [{ nome, visivel, opacidade, modo, src }] } (o que foi salvo antes)
     imagem   = data URL da imagem achatada (usada se não houver projeto)
     aoSalvar = async ({ imagem, projeto }) => {}  (imagem achatada + camadas, para o bloco guardar)
   Camadas com opacidade e modo de mistura, pincel/borracha com suavidade, balde, conta-gotas, texto,
   formas, seleção retangular, recorte, mover camada, ajustes (brilho, contraste, saturação, matiz,
   desfoque, P&B, sépia, inverter), girar/espelhar/redimensionar e desfazer/refazer. Não depende de nada. */
(function () {
    'use strict';
    if (window.WFEditorImagem) return;

    const MAX_LADO = 2400;           // imagens maiores são reduzidas ao abrir (o servidor guarda até ~6 MB)
    const MAX_PASSOS = 30;           // passos de desfazer guardados
    const MAX_CAMADAS = 12;
    const LIMITE_SALVAR = 5400000;   // tamanho máximo do projeto em texto
    const MODOS = [
        ['source-over', 'Normal'], ['multiply', 'Multiplicar'], ['screen', 'Tela'], ['overlay', 'Sobrepor'],
        ['darken', 'Escurecer'], ['lighten', 'Clarear'], ['color-dodge', 'Subexposição de cor'], ['color-burn', 'Superexposição de cor'],
        ['hard-light', 'Luz forte'], ['soft-light', 'Luz suave'], ['difference', 'Diferença'], ['exclusion', 'Exclusão'],
        ['hue', 'Matiz'], ['saturation', 'Saturação'], ['color', 'Cor'], ['luminosity', 'Luminosidade'],
    ];
    const FERRAMENTAS = [
        ['mover', 'Mover camada', 'V', 'M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20'],
        ['selecao', 'Seleção retangular', 'M', 'M4 4h3M10 4h4M17 4h3v3M20 10v4M20 17v3h-3M14 20h-4M7 20H4v-3M4 14v-4'],
        ['recorte', 'Recortar a imagem', 'C', 'M6 2v14a2 2 0 002 2h14M18 22V8a2 2 0 00-2-2H2'],
        ['pincel', 'Pincel', 'B', 'M18.4 2.6a2 2 0 012.9 2.9L12 14.8 9.2 12zM9.2 12C7 12 5 14 5 16.2 5 18 3.5 19.5 2 20c2.5 1.5 8 2 10-1.5.9-1.6.6-3.6-.6-4.9'],
        ['borracha', 'Borracha', 'E', 'M20 20H8l-5-5a2 2 0 010-2.8l9.2-9.2a2 2 0 012.8 0l5.8 5.8a2 2 0 010 2.8L13 19.4M6 11l7 7'],
        ['balde', 'Balde de tinta', 'G', 'M19 11l-8-8-8.5 8.5a2.1 2.1 0 000 3l5 5a2.1 2.1 0 003 0zM5 2l5 5M2 13h15M22 20a2 2 0 11-4 0c0-1.6 2-4 2-4s2 2.4 2 4z'],
        ['contagotas', 'Conta-gotas', 'I', 'M2 22l1-1h3l9-9M3 21v-3l9-9M15 6l3-3 3 3-3 3M14 5l5 5'],
        ['texto', 'Texto', 'T', 'M4 7V4h16v3M9 20h6M12 4v16'],
        ['retangulo', 'Retângulo', 'U', 'M3 5h18v14H3z'],
        ['elipse', 'Elipse', 'O', 'M12 5c5 0 9 3.1 9 7s-4 7-9 7-9-3.1-9-7 4-7 9-7z'],
        ['linha', 'Linha', 'L', 'M4 20L20 4'],
        ['mao', 'Mão (mover a vista)', 'H', 'M18 11V6a2 2 0 00-4 0v5M14 10V4a2 2 0 00-4 0v6M10 10.5V6a2 2 0 00-4 0v8M18 8a2 2 0 014 0v6a8 8 0 01-8 8h-2c-2.8 0-4.5-.9-6-2.4l-3.6-3.6a2 2 0 012.8-2.8L7 15'],
    ];

    const temFiltro = (() => { try { return 'filter' in document.createElement('canvas').getContext('2d'); } catch { return false; } })();
    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
    const uid = () => Math.random().toString(36).slice(2, 9);
    const novoCanvas = (w, h) => { const c = document.createElement('canvas'); c.width = Math.max(1, Math.round(w)); c.height = Math.max(1, Math.round(h)); return c; };
    const copiaCanvas = cv => { const c = novoCanvas(cv.width, cv.height); c.getContext('2d').drawImage(cv, 0, 0); return c; };
    const carregar = src => new Promise((ok, falha) => { const im = new Image(); im.onload = () => ok(im); im.onerror = falha; im.src = src; });
    const hexRgb = h => { const n = parseInt(h.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; };
    const rgbHex = (r, g, b) => '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('');
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    /* ---------- estilos ---------- */
    const CSS = `
    .wfe{position:fixed;inset:0;z-index:90;display:grid;grid-template-rows:auto auto 1fr;grid-template-columns:auto 1fr auto;
         background:#14171c;color:#e5e7eb;font:13px/1.35 system-ui,sans-serif;user-select:none}
    .wfe[hidden]{display:none!important}  /* sem isto o display:grid acima venceria o atributo hidden e o editor não fecharia */
    .wfe *{box-sizing:border-box}
    .wfe button{font:inherit;color:inherit;background:none;border:0;cursor:pointer}
    .wfe input,.wfe select{font:inherit;color:#e5e7eb;background:#0d0f13;border:1px solid #2c323c;border-radius:6px;padding:3px 6px}
    .wfe input[type=range]{padding:0;accent-color:var(--prim,#3b82f6)}
    .wfe input[type=color]{width:34px;height:28px;padding:1px;cursor:pointer}
    .wfe-topo{grid-column:1/-1;display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:6px 10px;background:#1b1f26;border-bottom:1px solid #2c323c}
    .wfe-titulo{font-weight:600;margin-right:6px;max-width:22ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfe-b{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:7px;border:1px solid #2c323c!important;background:#232831!important;white-space:nowrap}
    .wfe-b:hover{border-color:var(--prim,#3b82f6)!important}
    .wfe-b:disabled{opacity:.4;cursor:default}
    .wfe-b.prim{background:var(--prim,#3b82f6)!important;border-color:var(--prim,#3b82f6)!important;color:var(--on-prim,#fff)}
    .wfe-sep{width:1px;height:22px;background:#2c323c;margin:0 4px}
    .wfe-menu{position:relative}
    .wfe-menu>div{position:absolute;top:calc(100% + 4px);left:0;z-index:5;min-width:210px;padding:4px;border-radius:9px;background:#1b1f26;border:1px solid #2c323c;box-shadow:0 12px 30px rgba(0,0,0,.5);display:none}
    .wfe-menu.aberto>div{display:block}
    .wfe-menu>div button{display:flex;justify-content:space-between;gap:12px;width:100%;text-align:left;padding:6px 9px;border-radius:6px}
    .wfe-menu>div button:hover{background:#2a303a}
    .wfe-menu kbd,.wfe kbd{font:11px ui-monospace,monospace;color:#9aa3af}
    .wfe-opcoes{grid-column:1/-1;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:6px 10px;background:#181b21;border-bottom:1px solid #2c323c;min-height:42px}
    .wfe-opcoes label{display:inline-flex;align-items:center;gap:6px;color:#b4bcc8}
    .wfe-ferr{display:flex;flex-direction:column;gap:2px;padding:6px;background:#1b1f26;border-right:1px solid #2c323c;overflow:auto}
    .wfe-ferr button{width:36px;height:36px;display:grid;place-content:center;border-radius:7px}
    .wfe-ferr button:hover{background:#2a303a}
    .wfe-ferr button.sel{background:var(--prim,#3b82f6);color:var(--on-prim,#fff)}
    .wfe-ferr svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
    .wfe-cores{position:relative;width:36px;height:44px;margin-top:6px}
    .wfe-cores input{position:absolute;width:26px!important;height:26px!important;border-radius:5px}
    .wfe-cores input:first-child{left:0;top:0;z-index:2}.wfe-cores input:nth-child(2){left:10px;top:14px}
    .wfe-palco{position:relative;overflow:hidden;background:#0b0d10;touch-action:none}
    .wfe-palco canvas{position:absolute;inset:0;width:100%;height:100%}
    .wfe-lado{width:290px;display:flex;flex-direction:column;background:#1b1f26;border-left:1px solid #2c323c;overflow:auto}
    .wfe-sec{padding:10px;border-bottom:1px solid #2c323c}
    .wfe-sec h3{font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#9aa3af;margin:0 0 8px;display:flex;justify-content:space-between;align-items:center}
    .wfe-camadas{display:flex;flex-direction:column;gap:3px;max-height:280px;overflow:auto}
    .wfe-cam{display:flex;align-items:center;gap:7px;padding:4px 6px;border-radius:7px;border:1px solid transparent;cursor:pointer}
    .wfe-cam:hover{background:#232831}.wfe-cam.sel{border-color:var(--prim,#3b82f6);background:#232a36}
    .wfe-cam canvas{width:42px;height:32px;border-radius:4px;background:repeating-conic-gradient(#3a3f47 0 25%,#2b2f36 0 50%) 0 0/10px 10px;flex:none}
    .wfe-cam span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfe-cam .olho{width:24px;height:24px;display:grid;place-content:center;border-radius:5px;opacity:.85}
    .wfe-cam .olho.off{opacity:.3}
    .wfe-linha{display:flex;align-items:center;gap:6px;margin-top:6px}
    .wfe-linha>span{min-width:76px;color:#b4bcc8}
    .wfe-linha input[type=range]{flex:1}
    .wfe-acoes{display:flex;flex-wrap:wrap;gap:4px;margin-top:8px}
    .wfe-acoes .wfe-b{padding:4px 7px;font-size:12px}
    .wfe-status{position:absolute;left:8px;bottom:8px;padding:3px 8px;border-radius:6px;background:rgba(0,0,0,.55);color:#cbd5e1;font-size:12px;pointer-events:none}
    .wfe-aviso{position:absolute;left:50%;top:12px;transform:translateX(-50%);padding:6px 12px;border-radius:8px;background:#232a36;border:1px solid var(--prim,#3b82f6);font-size:12.5px;pointer-events:none;opacity:0;transition:opacity .2s}
    .wfe-aviso.ver{opacity:1}
    .wfe-texto{position:absolute;z-index:3;display:flex;flex-direction:column;gap:4px;padding:6px;border-radius:8px;background:#1b1f26;border:1px solid var(--prim,#3b82f6);box-shadow:0 10px 24px rgba(0,0,0,.5)}
    .wfe-texto textarea{font:inherit;color:#e5e7eb;background:#0d0f13;border:1px solid #2c323c;border-radius:6px;min-width:220px;min-height:60px;padding:5px}
    .wfe-dialogo{position:absolute;inset:0;z-index:6;display:grid;place-content:center;background:rgba(0,0,0,.45)}
    .wfe-dialogo>div{min-width:280px;padding:14px;border-radius:12px;background:#1b1f26;border:1px solid #2c323c;display:flex;flex-direction:column;gap:8px}
    @media (max-width:900px){
        .wfe{grid-template-columns:auto 1fr;grid-template-rows:auto auto 1fr auto}
        .wfe-lado{grid-column:1/-1;width:auto;max-height:38vh}
    }`;

    let xadrez = null;        // padrão do fundo transparente
    let el = null;            // raiz do editor (montada uma vez)
    let S = null;             // estado do documento aberto

    function montar() {
        const st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
        el = document.createElement('div');
        el.className = 'wfe'; el.hidden = true;
        el.setAttribute('role', 'dialog'); el.setAttribute('aria-modal', 'true'); el.setAttribute('aria-label', 'Editor de imagem');
        el.innerHTML = `
        <div class="wfe-topo">
            <span class="wfe-titulo" data-titulo>Editor de imagem</span>
            <div class="wfe-menu" data-menu="arquivo"><button class="wfe-b" data-abrir-menu>Arquivo ▾</button><div>
                <button data-acao="abrirArquivo">Abrir imagem… <kbd>Ctrl+O</kbd></button>
                <button data-acao="abrirComoCamada">Colocar imagem como camada…</button>
                <button data-acao="novaTela">Nova tela em branco…</button>
                <button data-acao="exportarPng">Baixar como PNG</button>
                <button data-acao="exportarJpg">Baixar como JPG</button>
            </div></div>
            <div class="wfe-menu" data-menu="imagem"><button class="wfe-b" data-abrir-menu>Imagem ▾</button><div>
                <button data-acao="girarDir">Girar 90° à direita</button>
                <button data-acao="girarEsq">Girar 90° à esquerda</button>
                <button data-acao="espelharH">Espelhar na horizontal</button>
                <button data-acao="espelharV">Espelhar na vertical</button>
                <button data-acao="redimensionar">Tamanho da imagem…</button>
                <button data-acao="recortarSelecao">Recortar para a seleção</button>
                <button data-acao="achatar">Achatar imagem (juntar camadas)</button>
            </div></div>
            <div class="wfe-menu" data-menu="selecao"><button class="wfe-b" data-abrir-menu>Seleção ▾</button><div>
                <button data-acao="selecionarTudo">Selecionar tudo <kbd>Ctrl+A</kbd></button>
                <button data-acao="desmarcar">Desmarcar <kbd>Ctrl+D</kbd></button>
                <button data-acao="apagarSelecao">Apagar o selecionado <kbd>Del</kbd></button>
                <button data-acao="preencherSelecao">Preencher com a cor <kbd>Alt+Del</kbd></button>
                <button data-acao="copiarParaCamada">Copiar para nova camada <kbd>Ctrl+J</kbd></button>
            </div></div>
            <span class="wfe-sep"></span>
            <button class="wfe-b" data-acao="desfazer" title="Desfazer (Ctrl+Z)">↶</button>
            <button class="wfe-b" data-acao="refazer" title="Refazer (Ctrl+Y)">↷</button>
            <span class="wfe-sep"></span>
            <button class="wfe-b" data-acao="zoomMenos" title="Diminuir zoom (Ctrl+-)">−</button>
            <button class="wfe-b" data-acao="ajustarTela" data-zoom title="Ajustar à tela (Ctrl+0)">100%</button>
            <button class="wfe-b" data-acao="zoomMais" title="Aumentar zoom (Ctrl++)">+</button>
            <span style="flex:1"></span>
            <button class="wfe-b" data-acao="cancelar">Cancelar</button>
            <button class="wfe-b prim" data-acao="salvar" title="Salvar no cartão (Ctrl+S)">Salvar e fechar</button>
        </div>
        <div class="wfe-opcoes" data-opcoes></div>
        <div class="wfe-ferr" data-ferr></div>
        <div class="wfe-palco" data-palco>
            <canvas data-vista></canvas>
            <div class="wfe-status" data-status></div>
            <div class="wfe-aviso" data-aviso></div>
        </div>
        <div class="wfe-lado">
            <div class="wfe-sec">
                <h3>Camadas <span style="text-transform:none;letter-spacing:0" data-ncam></span></h3>
                <div class="wfe-camadas" data-camadas></div>
                <div class="wfe-linha"><span>Opacidade</span><input type="range" min="0" max="100" data-cam-opac><b data-cam-opac-v style="width:34px;text-align:right"></b></div>
                <div class="wfe-linha"><span>Mistura</span><select data-cam-modo style="flex:1">${MODOS.map(([v, n]) => `<option value="${v}">${n}</option>`).join('')}</select></div>
                <div class="wfe-acoes">
                    <button class="wfe-b" data-acao="novaCamada" title="Nova camada (Ctrl+Shift+N)">+ Nova</button>
                    <button class="wfe-b" data-acao="duplicarCamada">Duplicar</button>
                    <button class="wfe-b" data-acao="subirCamada" title="Subir camada">▲</button>
                    <button class="wfe-b" data-acao="descerCamada" title="Descer camada">▼</button>
                    <button class="wfe-b" data-acao="mesclarAbaixo" title="Mesclar com a de baixo (Ctrl+E)">Mesclar</button>
                    <button class="wfe-b" data-acao="espelharCamadaH" title="Espelhar só esta camada">⇋</button>
                    <button class="wfe-b" data-acao="limparCamada">Limpar</button>
                    <button class="wfe-b" data-acao="excluirCamada" style="color:#f87171">Excluir</button>
                </div>
            </div>
            <div class="wfe-sec">
                <h3>Ajustes da camada <button class="wfe-b" data-acao="zerarAjustes" style="padding:2px 7px;font-size:11px">Zerar</button></h3>
                ${[['brilho', 'Brilho', 0, 200, 100], ['contraste', 'Contraste', 0, 200, 100], ['saturacao', 'Saturação', 0, 200, 100],
                   ['matiz', 'Matiz', -180, 180, 0], ['desfoque', 'Desfoque', 0, 20, 0], ['cinza', 'Preto e branco', 0, 100, 0],
                   ['sepia', 'Sépia', 0, 100, 0], ['inverter', 'Inverter', 0, 100, 0]].map(([k, n, a, b, v]) =>
                    `<div class="wfe-linha"><span>${n}</span><input type="range" min="${a}" max="${b}" value="${v}" data-aj="${k}"><b data-aj-v="${k}" style="width:34px;text-align:right">${v}</b></div>`).join('')}
                <div class="wfe-acoes"><button class="wfe-b prim" data-acao="aplicarAjustes">Aplicar à camada</button></div>
                <p data-sem-filtro style="display:none;color:#9aa3af;font-size:11.5px;margin:6px 0 0">Seu navegador não mostra a prévia; os ajustes são aplicados ao clicar (matiz e desfoque não funcionam nele).</p>
            </div>
            <div class="wfe-sec" style="color:#9aa3af;font-size:11.5px">
                <b style="color:#cbd5e1">Teclas:</b> V mover · M seleção · C recorte · B pincel · E borracha · G balde · I conta-gotas · T texto · U retângulo · O elipse · L linha · H mão (ou segure Espaço) · [ e ] tamanho · X troca cores · Ctrl+roda zoom.
            </div>
        </div>
        <input type="file" accept="image/*" hidden data-arquivo>`;
        document.body.appendChild(el);

        const ferr = el.querySelector('[data-ferr]');
        ferr.innerHTML = FERRAMENTAS.map(([id, nome, tecla, d]) =>
            `<button data-ferramenta="${id}" title="${esc(nome)} (${tecla})" aria-label="${esc(nome)}"><svg viewBox="0 0 24 24"><path d="${d}"/></svg></button>`).join('')
            + `<div class="wfe-cores" title="Cor principal e secundária (X troca)"><input type="color" data-cor1><input type="color" data-cor2></div>`;

        // cliques em ações, menus e ferramentas
        el.addEventListener('click', e => {
            const menu = e.target.closest('[data-abrir-menu]');
            el.querySelectorAll('.wfe-menu.aberto').forEach(m => { if (!menu || m !== menu.parentElement) m.classList.remove('aberto'); });
            if (menu) { menu.parentElement.classList.toggle('aberto'); return; }
            const f = e.target.closest('[data-ferramenta]');
            if (f) { escolherFerramenta(f.dataset.ferramenta); return; }
            const a = e.target.closest('[data-acao]');
            if (a && ACOES[a.dataset.acao]) { ACOES[a.dataset.acao](); }
        });
        el.querySelector('[data-cor1]').addEventListener('input', e => { S.cor1 = e.target.value; });
        el.querySelector('[data-cor2]').addEventListener('input', e => { S.cor2 = e.target.value; });
        el.querySelector('[data-arquivo]').addEventListener('change', async e => {
            const f = e.target.files[0]; e.target.value = '';
            if (!f || !f.type.startsWith('image/')) return;
            const src = await new Promise(ok => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(f); });
            if (S.abrirComo === 'camada') colocarComoCamada(src, f.name); else abrirImagem(src);
        });
        // camada: opacidade e modo
        el.querySelector('[data-cam-opac]').addEventListener('input', e => {
            const c = camadaAtiva(); if (!c) return;
            if (!S._opacPasso) { passoFull(); S._opacPasso = true; }
            c.opacidade = e.target.value / 100; desenharCamadas(); render();
        });
        el.querySelector('[data-cam-opac]').addEventListener('change', () => { S._opacPasso = false; });
        el.querySelector('[data-cam-modo]').addEventListener('change', e => { const c = camadaAtiva(); if (!c) return; passoFull(); c.modo = e.target.value; render(); });
        // ajustes: prévia ao vivo
        el.querySelectorAll('[data-aj]').forEach(inp => inp.addEventListener('input', () => {
            S.ajustes[inp.dataset.aj] = Number(inp.value);
            el.querySelector(`[data-aj-v="${inp.dataset.aj}"]`).textContent = inp.value;
            render();
        }));
        el.querySelector('[data-sem-filtro]').style.display = temFiltro ? 'none' : 'block';

        // palco: desenho, zoom e arraste
        const palco = el.querySelector('[data-palco]');
        palco.addEventListener('pointerdown', aoApertar);
        palco.addEventListener('pointermove', aoMover);
        palco.addEventListener('pointerup', aoSoltar);
        palco.addEventListener('pointercancel', aoSoltar);
        palco.addEventListener('pointerleave', () => { if (S) { S.mouse = null; render(); } });
        palco.addEventListener('wheel', e => {
            e.preventDefault();
            if (e.ctrlKey || e.metaKey) zoomEm(e.deltaY < 0 ? 1.15 : 1 / 1.15, e.offsetX, e.offsetY);
            else { S.panX -= e.shiftKey ? e.deltaY : e.deltaX; S.panY -= e.shiftKey ? 0 : e.deltaY; render(); }
        }, { passive: false });
        new ResizeObserver(() => { if (S && !el.hidden) { medirPalco(); render(); } }).observe(palco);
        document.addEventListener('keydown', teclado, true);
        document.addEventListener('keyup', e => { if (S && !el.hidden && e.code === 'Space') { S.espaco = false; atualizarCursor(); } }, true);
    }

    /* ---------- documento e camadas ---------- */
    function camadaAtiva() { return S.camadas[S.ativa] || null; }
    function novaCamadaVazia(nome) { return { id: uid(), nome, cv: novoCanvas(S.W, S.H), visivel: true, opacidade: 1, modo: 'source-over' }; }
    function nomeLivre(base) { let n = 1; while (S.camadas.some(c => c.nome === `${base} ${n}`)) n++; return `${base} ${n}`; }

    async function abrirDocumento(opcoes) {
        S = {
            W: 800, H: 600, camadas: [], ativa: 0, ferramenta: 'pincel',
            cor1: '#000000', cor2: '#ffffff', tamanho: 18, opacidadePincel: 100, suavidade: 20,
            tolerancia: 32, contiguo: true, fonte: 42, negrito: false, preencher: false,
            zoom: 1, panX: 0, panY: 0, selecao: null, recorte: null, hist: [], refaz: [],
            ajustes: zeroAjustes(), mouse: null, arraste: null, espaco: false, alterado: false,
            titulo: opcoes.titulo || 'Imagem', aoSalvar: opcoes.aoSalvar, aoFechar: opcoes.aoFechar,
        };
        const p = opcoes.projeto;
        try {
            if (p && Array.isArray(p.camadas) && p.camadas.length && p.w > 0 && p.h > 0) {
                S.W = p.w; S.H = p.h;
                for (const c of p.camadas) {
                    const cam = novaCamadaVazia(c.nome || nomeLivre('Camada'));
                    cam.visivel = c.visivel !== false; cam.opacidade = clamp(Number(c.opacidade ?? 1), 0, 1);
                    cam.modo = MODOS.some(m => m[0] === c.modo) ? c.modo : 'source-over';
                    if (c.src) { const im = await carregar(c.src); cam.cv.getContext('2d').drawImage(im, 0, 0); }
                    S.camadas.push(cam);
                }
                S.ativa = clamp(Number(p.ativa) || S.camadas.length - 1, 0, S.camadas.length - 1);
            } else if (opcoes.imagem) {
                const im = await carregar(opcoes.imagem);
                const k = Math.min(1, MAX_LADO / Math.max(im.width, im.height));
                S.W = Math.round(im.width * k); S.H = Math.round(im.height * k);
                const cam = novaCamadaVazia('Fundo');
                cam.cv.getContext('2d').drawImage(im, 0, 0, S.W, S.H);
                S.camadas.push(cam);
            } else {
                const cam = novaCamadaVazia('Fundo');
                const cx = cam.cv.getContext('2d'); cx.fillStyle = '#ffffff'; cx.fillRect(0, 0, S.W, S.H);
                S.camadas.push(cam);
            }
        } catch {
            S.camadas = [novaCamadaVazia('Fundo')];
            aviso('Não consegui abrir a imagem salva. Começando uma tela nova.');
        }
        S.temp = novoCanvas(S.W, S.H); // traço em andamento
    }

    function serializar(qualidade) {
        const tipo = 'image/webp';
        const camadas = S.camadas.map(c => {
            let src = c.cv.toDataURL(tipo, qualidade);
            if (!src.startsWith('data:image/webp')) src = c.cv.toDataURL('image/png');
            return { nome: c.nome, visivel: c.visivel, opacidade: Math.round(c.opacidade * 100) / 100, modo: c.modo, src };
        });
        return { v: 1, w: S.W, h: S.H, ativa: S.ativa, camadas };
    }
    /** Imagem achatada (o que aparece no cartão). */
    function achatar(fundoBranco) {
        const cv = novoCanvas(S.W, S.H), cx = cv.getContext('2d');
        if (fundoBranco) { cx.fillStyle = '#fff'; cx.fillRect(0, 0, S.W, S.H); }
        S.camadas.forEach(c => {
            if (!c.visivel) return;
            cx.globalAlpha = c.opacidade; cx.globalCompositeOperation = c.modo;
            cx.drawImage(c.cv, 0, 0);
        });
        cx.globalAlpha = 1; cx.globalCompositeOperation = 'source-over';
        return cv;
    }

    /* ---------- desfazer / refazer ---------- */
    function estadoCompleto() {
        return { W: S.W, H: S.H, ativa: S.ativa, camadas: S.camadas.map(c => ({ ...c, cv: copiaCanvas(c.cv) })) };
    }
    function empilhar(p) {
        S.hist.push(p); if (S.hist.length > MAX_PASSOS) S.hist.shift();
        S.refaz = []; S.alterado = true; atualizarBotoes();
    }
    /** Antes de pintar uma camada: guarda só os pixels dela. */
    function passoPixels(c) { c = c || camadaAtiva(); if (c) empilhar({ k: 'pix', id: c.id, cv: copiaCanvas(c.cv) }); }
    /** Antes de mudar a estrutura (camadas, tamanho, giros): guarda tudo. */
    function passoFull() { empilhar({ k: 'full', e: estadoCompleto() }); }
    function voltar(de, para) {
        const p = de.pop(); if (!p) return;
        if (p.k === 'pix') {
            const c = S.camadas.find(x => x.id === p.id);
            if (!c) return voltar(de, para);
            para.push({ k: 'pix', id: c.id, cv: c.cv });
            c.cv = p.cv;
        } else {
            para.push({ k: 'full', e: estadoCompleto() });
            S.W = p.e.W; S.H = p.e.H; S.ativa = p.e.ativa; S.camadas = p.e.camadas;
            S.temp = novoCanvas(S.W, S.H);
            if (S.selecao && (S.selecao.x + S.selecao.w > S.W || S.selecao.y + S.selecao.h > S.H)) S.selecao = null;
        }
        S.alterado = true;
        desenharCamadas(); render(); atualizarBotoes();
    }

    /* ---------- vista ---------- */
    function medirPalco() {
        const palco = el.querySelector('[data-palco]'), cv = el.querySelector('[data-vista]');
        const r = palco.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
        S.vw = r.width; S.vh = r.height; S.dpr = dpr;
        cv.width = Math.round(r.width * dpr); cv.height = Math.round(r.height * dpr);
    }
    function ajustarTela() {
        medirPalco();
        const z = Math.min((S.vw - 40) / S.W, (S.vh - 40) / S.H, 4);
        S.zoom = Math.max(0.02, z);
        S.panX = (S.vw - S.W * S.zoom) / 2; S.panY = (S.vh - S.H * S.zoom) / 2;
        render();
    }
    function zoomEm(f, x, y) {
        const z = clamp(S.zoom * f, 0.02, 32);
        x = x ?? S.vw / 2; y = y ?? S.vh / 2;
        S.panX = x - (x - S.panX) * (z / S.zoom); S.panY = y - (y - S.panY) * (z / S.zoom);
        S.zoom = z; render();
    }
    const paraImagem = (x, y) => [(x - S.panX) / S.zoom, (y - S.panY) / S.zoom];
    function filtroAjustes(a) {
        const f = [];
        if (a.brilho !== 100) f.push(`brightness(${a.brilho}%)`);
        if (a.contraste !== 100) f.push(`contrast(${a.contraste}%)`);
        if (a.saturacao !== 100) f.push(`saturate(${a.saturacao}%)`);
        if (a.matiz) f.push(`hue-rotate(${a.matiz}deg)`);
        if (a.desfoque) f.push(`blur(${a.desfoque}px)`);
        if (a.cinza) f.push(`grayscale(${a.cinza}%)`);
        if (a.sepia) f.push(`sepia(${a.sepia}%)`);
        if (a.inverter) f.push(`invert(${a.inverter}%)`);
        return f.length ? f.join(' ') : 'none';
    }
    function zeroAjustes() { return { brilho: 100, contraste: 100, saturacao: 100, matiz: 0, desfoque: 0, cinza: 0, sepia: 0, inverter: 0 }; }

    function render() {
        if (!S || !el || el.hidden) return;
        const cv = el.querySelector('[data-vista]'), cx = cv.getContext('2d');
        const d = S.dpr || 1;
        cx.setTransform(1, 0, 0, 1, 0, 0);
        cx.clearRect(0, 0, cv.width, cv.height);
        cx.setTransform(d, 0, 0, d, 0, 0);
        const x0 = S.panX, y0 = S.panY, w = S.W * S.zoom, h = S.H * S.zoom;
        // xadrez da transparência
        cx.save();
        cx.beginPath(); cx.rect(x0, y0, w, h); cx.clip();
        if (!xadrez) {
            const p = novoCanvas(20, 20), px = p.getContext('2d');
            px.fillStyle = '#3a3f47'; px.fillRect(0, 0, 20, 20);
            px.fillStyle = '#2b2f36'; px.fillRect(0, 0, 10, 10); px.fillRect(10, 10, 10, 10);
            xadrez = cx.createPattern(p, 'repeat');
        }
        cx.fillStyle = xadrez; cx.fillRect(x0, y0, w, h);
        cx.restore();
        // camadas
        cx.save();
        cx.translate(x0, y0); cx.scale(S.zoom, S.zoom);
        cx.imageSmoothingEnabled = S.zoom < 2;
        const prev = filtroAjustes(S.ajustes);
        S.camadas.forEach((c, i) => {
            if (!c.visivel) return;
            cx.globalAlpha = c.opacidade; cx.globalCompositeOperation = c.modo;
            if (i === S.ativa && temFiltro && prev !== 'none') cx.filter = prev;
            if (i === S.ativa && S.arraste && S.arraste.tipo === 'mover') cx.drawImage(c.cv, S.arraste.dx, S.arraste.dy);
            else if (i === S.ativa && S.arraste && S.arraste.tipo === 'traco') {
                // camada + traço em andamento (borracha mostra o resultado de verdade)
                const t = composicaoTraco(c);
                cx.drawImage(t, 0, 0);
            } else cx.drawImage(c.cv, 0, 0);
            cx.filter = 'none';
        });
        cx.globalAlpha = 1; cx.globalCompositeOperation = 'source-over';
        // prévia de forma
        const a = S.arraste;
        if (a && a.tipo === 'forma') desenharForma(cx, a.x0, a.y0, a.x1, a.y1, true);
        cx.restore();
        // borda da imagem
        cx.strokeStyle = 'rgba(255,255,255,.18)'; cx.lineWidth = 1; cx.strokeRect(x0 - .5, y0 - .5, w + 1, h + 1);
        // seleção e recorte
        const marca = (r, cor) => {
            if (!r) return;
            const rx = x0 + r.x * S.zoom, ry = y0 + r.y * S.zoom, rw = r.w * S.zoom, rh = r.h * S.zoom;
            cx.save(); cx.setLineDash([6, 4]); cx.lineDashOffset = -(Date.now() / 60) % 10;
            cx.strokeStyle = '#000'; cx.strokeRect(rx + .5, ry + .5, rw, rh);
            cx.strokeStyle = cor; cx.lineDashOffset += 5; cx.strokeRect(rx + .5, ry + .5, rw, rh); cx.restore();
        };
        if (S.recorte) {
            const r = S.recorte;
            cx.fillStyle = 'rgba(0,0,0,.55)';
            cx.fillRect(x0, y0, w, r.y * S.zoom);
            cx.fillRect(x0, y0 + (r.y + r.h) * S.zoom, w, h - (r.y + r.h) * S.zoom);
            cx.fillRect(x0, y0 + r.y * S.zoom, r.x * S.zoom, r.h * S.zoom);
            cx.fillRect(x0 + (r.x + r.w) * S.zoom, y0 + r.y * S.zoom, w - (r.x + r.w) * S.zoom, r.h * S.zoom);
            marca(r, '#fbbf24');
        }
        marca(S.selecao, '#fff');
        // cursor do pincel
        if (S.mouse && ['pincel', 'borracha'].includes(S.ferramenta) && !S.espaco) {
            cx.beginPath(); cx.arc(S.mouse.x, S.mouse.y, Math.max(1, S.tamanho * S.zoom / 2), 0, Math.PI * 2);
            cx.strokeStyle = 'rgba(0,0,0,.7)'; cx.lineWidth = 2; cx.stroke();
            cx.strokeStyle = 'rgba(255,255,255,.9)'; cx.lineWidth = 1; cx.stroke();
        }
        // textos de estado
        el.querySelector('[data-zoom]').textContent = Math.round(S.zoom * 100) + '%';
        const pos = S.mouse ? paraImagem(S.mouse.x, S.mouse.y).map(Math.floor) : null;
        const sel = S.selecao ? ` · seleção ${Math.round(S.selecao.w)}×${Math.round(S.selecao.h)}` : '';
        el.querySelector('[data-status]').textContent = `${S.W}×${S.H} px${pos ? ` · ${pos[0]}, ${pos[1]}` : ''}${sel}`;
        if ((S.selecao || S.recorte) && !S._anima) { S._anima = requestAnimationFrame(() => { S._anima = 0; render(); }); }
    }

    /* ---------- traço do pincel e da borracha ---------- */
    function composicaoTraco(c) {
        const t = S._compo || (S._compo = novoCanvas(S.W, S.H));
        if (t.width !== S.W || t.height !== S.H) { t.width = S.W; t.height = S.H; }
        const cx = t.getContext('2d');
        cx.clearRect(0, 0, S.W, S.H);
        cx.drawImage(c.cv, 0, 0);
        aplicarTraco(cx);
        return t;
    }
    /** Junta o traço em andamento (S.temp) na camada, com opacidade, suavidade e limite da seleção. */
    function aplicarTraco(cx) {
        cx.save();
        if (S.selecao) { cx.beginPath(); cx.rect(S.selecao.x, S.selecao.y, S.selecao.w, S.selecao.h); cx.clip(); }
        cx.globalAlpha = S.opacidadePincel / 100;
        cx.globalCompositeOperation = S.ferramenta === 'borracha' ? 'destination-out' : 'source-over';
        const suave = S.suavidade / 100 * S.tamanho / 3;
        if (temFiltro && suave > 0.4) cx.filter = `blur(${suave.toFixed(1)}px)`;
        cx.drawImage(S.temp, 0, 0);
        cx.restore();
    }
    function riscar(x0, y0, x1, y1, pressao) {
        const cx = S.temp.getContext('2d');
        const larg = Math.max(0.5, S.tamanho * (pressao > 0 && pressao !== 0.5 ? 0.25 + pressao * 0.95 : 1));
        cx.strokeStyle = cx.fillStyle = S.ferramenta === 'borracha' ? '#000' : S.cor1;
        cx.lineWidth = larg; cx.lineCap = 'round'; cx.lineJoin = 'round';
        cx.beginPath(); cx.moveTo(x0, y0); cx.lineTo(x1, y1); cx.stroke();
        if (x0 === x1 && y0 === y1) { cx.beginPath(); cx.arc(x1, y1, larg / 2, 0, Math.PI * 2); cx.fill(); }
    }

    /* ---------- formas ---------- */
    function desenharForma(cx, x0, y0, x1, y1, previa) {
        cx.save();
        if (S.selecao && !previa) { cx.beginPath(); cx.rect(S.selecao.x, S.selecao.y, S.selecao.w, S.selecao.h); cx.clip(); }
        cx.globalAlpha = S.opacidadePincel / 100;
        cx.strokeStyle = S.cor1; cx.fillStyle = S.preencher ? S.cor1 : S.cor2;
        cx.lineWidth = Math.max(1, S.tamanho); cx.lineCap = 'round'; cx.lineJoin = 'round';
        const x = Math.min(x0, x1), y = Math.min(y0, y1), w = Math.abs(x1 - x0), h = Math.abs(y1 - y0);
        cx.beginPath();
        if (S.ferramenta === 'linha') { cx.moveTo(x0, y0); cx.lineTo(x1, y1); cx.stroke(); }
        else {
            if (S.ferramenta === 'retangulo') cx.rect(x, y, w, h);
            else cx.ellipse(x + w / 2, y + h / 2, Math.max(.5, w / 2), Math.max(.5, h / 2), 0, 0, Math.PI * 2);
            if (S.preencher) cx.fill(); else cx.stroke();
        }
        cx.restore();
    }

    /* ---------- balde (preenchimento por semelhança de cor) ---------- */
    function balde(px, py) {
        const c = camadaAtiva(); if (!c) return;
        px = Math.floor(px); py = Math.floor(py);
        if (px < 0 || py < 0 || px >= S.W || py >= S.H) return;
        const sel = S.selecao;
        if (sel && (px < sel.x || py < sel.y || px >= sel.x + sel.w || py >= sel.y + sel.h)) return;
        passoPixels(c);
        const cx = c.cv.getContext('2d');
        const img = cx.getImageData(0, 0, S.W, S.H), d = img.data;
        // a cor de referência vem da imagem como aparece (todas as camadas), como no Photoshop "amostrar tudo" desligado = só a camada
        const i0 = (py * S.W + px) * 4;
        const alvo = [d[i0], d[i0 + 1], d[i0 + 2], d[i0 + 3]];
        const [r, g, b] = hexRgb(S.cor1), a = Math.round(S.opacidadePincel / 100 * 255);
        const tol = S.tolerancia * 4;
        const parecido = i => Math.abs(d[i] - alvo[0]) + Math.abs(d[i + 1] - alvo[1]) + Math.abs(d[i + 2] - alvo[2]) + Math.abs(d[i + 3] - alvo[3]) <= tol;
        const dentro = (x, y) => !sel || (x >= sel.x && y >= sel.y && x < sel.x + sel.w && y < sel.y + sel.h);
        const pintar = i => { const ai = a / 255, bi = d[i + 3] / 255, ao = ai + bi * (1 - ai) || 1;
            d[i] = (r * ai + d[i] * bi * (1 - ai)) / ao; d[i + 1] = (g * ai + d[i + 1] * bi * (1 - ai)) / ao; d[i + 2] = (b * ai + d[i + 2] * bi * (1 - ai)) / ao; d[i + 3] = ao * 255; };
        const feito = new Uint8Array(S.W * S.H);
        if (S.contiguo) {
            const pilha = [px, py];
            while (pilha.length) {
                const y = pilha.pop(), x = pilha.pop();
                let xi = x;
                while (xi >= 0 && !feito[y * S.W + xi] && dentro(xi, y) && parecido((y * S.W + xi) * 4)) xi--;
                xi++;
                let cima = false, baixo = false;
                for (; xi < S.W; xi++) {
                    const k = y * S.W + xi;
                    if (feito[k] || !dentro(xi, y) || !parecido(k * 4)) break;
                    feito[k] = 1;
                    if (y > 0) { const ok = !feito[k - S.W] && dentro(xi, y - 1) && parecido((k - S.W) * 4); if (ok && !cima) { pilha.push(xi, y - 1); cima = true; } else if (!ok) cima = false; }
                    if (y < S.H - 1) { const ok = !feito[k + S.W] && dentro(xi, y + 1) && parecido((k + S.W) * 4); if (ok && !baixo) { pilha.push(xi, y + 1); baixo = true; } else if (!ok) baixo = false; }
                }
            }
            for (let k = 0; k < feito.length; k++) if (feito[k]) pintar(k * 4);
        } else {
            for (let y = 0; y < S.H; y++) for (let x = 0; x < S.W; x++) { const k = (y * S.W + x) * 4; if (dentro(x, y) && parecido(k)) pintar(k); }
        }
        cx.putImageData(img, 0, 0);
        desenharCamadas(); render();
    }

    /* ---------- texto ---------- */
    function caixaTexto(ix, iy, sx, sy) {
        fecharTexto();
        const palco = el.querySelector('[data-palco]');
        const box = document.createElement('div');
        box.className = 'wfe-texto';
        box.style.left = Math.min(sx, S.vw - 260) + 'px'; box.style.top = Math.min(sy, S.vh - 130) + 'px';
        box.innerHTML = `<textarea placeholder="Digite o texto…"></textarea>
            <div style="display:flex;gap:6px;justify-content:flex-end"><button class="wfe-b" data-t="nao">Cancelar</button><button class="wfe-b prim" data-t="sim">Colocar <kbd>Ctrl+Enter</kbd></button></div>`;
        palco.appendChild(box);
        const ta = box.querySelector('textarea');
        ta.style.color = S.cor1; ta.style.fontWeight = S.negrito ? '700' : '400';
        // o foco vai para a caixa depois que o clique termina (senão o soltar do mouse tira o foco dela)
        S._focarTexto = ta;
        setTimeout(() => ta.focus(), 120);
        const confirmar = () => {
            const txt = ta.value; fecharTexto();
            if (!txt.trim()) return;
            if (S.camadas.length >= MAX_CAMADAS) { aviso(`Limite de ${MAX_CAMADAS} camadas. Mescle algumas para continuar.`); return; }
            passoFull();
            const cam = novaCamadaVazia('Texto: ' + txt.trim().slice(0, 18));
            const cx = cam.cv.getContext('2d');
            cx.globalAlpha = S.opacidadePincel / 100;
            cx.fillStyle = S.cor1; cx.textBaseline = 'top';
            cx.font = `${S.negrito ? '700 ' : ''}${S.fonte}px system-ui, sans-serif`;
            txt.split('\n').forEach((l, i) => cx.fillText(l, ix, iy + i * S.fonte * 1.2));
            S.camadas.splice(S.ativa + 1, 0, cam); S.ativa++;
            desenharCamadas(); render();
        };
        box.addEventListener('click', e => { const b = e.target.closest('[data-t]'); if (!b) return; if (b.dataset.t === 'sim') confirmar(); else fecharTexto(); });
        ta.addEventListener('keydown', e => {
            e.stopPropagation();
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); confirmar(); }
            if (e.key === 'Escape') { e.preventDefault(); fecharTexto(); }
        });
    }
    function fecharTexto() { el.querySelectorAll('.wfe-texto').forEach(b => b.remove()); }

    /* ---------- ponteiro ---------- */
    function aoApertar(e) {
        if (!S || e.target.closest('.wfe-texto')) return;
        if (e.button === 1 || e.button === 2) return;
        try { el.querySelector('[data-palco]').setPointerCapture(e.pointerId); } catch { /* ponteiro sem captura (ex.: eventos sintéticos) */ }
        const [ix, iy] = paraImagem(e.offsetX, e.offsetY);
        const f = S.espaco ? 'mao' : S.ferramenta;
        if (f === 'mao') { S.arraste = { tipo: 'mao', sx: e.offsetX, sy: e.offsetY, px: S.panX, py: S.panY }; return; }
        const c = camadaAtiva();
        if (['pincel', 'borracha', 'mover', 'balde', 'retangulo', 'elipse', 'linha'].includes(f) && c && !c.visivel) { aviso('A camada ativa está escondida. Mostre ela (olho) para editar.'); return; }
        if (f === 'pincel' || f === 'borracha') {
            S.temp.getContext('2d').clearRect(0, 0, S.W, S.H);
            S.arraste = { tipo: 'traco', x: ix, y: iy };
            riscar(ix, iy, ix, iy, e.pressure);
        } else if (f === 'mover') {
            S.arraste = { tipo: 'mover', x0: ix, y0: iy, dx: 0, dy: 0 };
        } else if (f === 'selecao' || f === 'recorte') {
            S.arraste = { tipo: f, x0: clamp(ix, 0, S.W), y0: clamp(iy, 0, S.H) };
            if (f === 'selecao') S.selecao = null; else S.recorte = null;
        } else if (['retangulo', 'elipse', 'linha'].includes(f)) {
            S.arraste = { tipo: 'forma', x0: ix, y0: iy, x1: ix, y1: iy };
        } else if (f === 'balde') { balde(ix, iy); }
        else if (f === 'contagotas') { pegarCor(ix, iy, e.altKey); }
        else if (f === 'texto') { caixaTexto(ix, iy, e.offsetX, e.offsetY); }
        render();
    }
    function aoMover(e) {
        if (!S) return;
        S.mouse = { x: e.offsetX, y: e.offsetY };
        const a = S.arraste;
        if (!a) { render(); return; }
        const [ix, iy] = paraImagem(e.offsetX, e.offsetY);
        if (a.tipo === 'mao') { S.panX = a.px + e.offsetX - a.sx; S.panY = a.py + e.offsetY - a.sy; }
        else if (a.tipo === 'traco') {
            const evs = e.getCoalescedEvents ? e.getCoalescedEvents() : [e];
            evs.forEach(ev => {
                const [jx, jy] = paraImagem(ev.offsetX ?? e.offsetX, ev.offsetY ?? e.offsetY);
                riscar(a.x, a.y, jx, jy, ev.pressure ?? e.pressure);
                a.x = jx; a.y = jy;
            });
        } else if (a.tipo === 'mover') { a.dx = Math.round(ix - a.x0); a.dy = Math.round(iy - a.y0); }
        else if (a.tipo === 'selecao' || a.tipo === 'recorte') {
            let x1 = clamp(ix, 0, S.W), y1 = clamp(iy, 0, S.H);
            if (e.shiftKey) { const l = Math.max(Math.abs(x1 - a.x0), Math.abs(y1 - a.y0)); x1 = a.x0 + Math.sign(x1 - a.x0 || 1) * l; y1 = a.y0 + Math.sign(y1 - a.y0 || 1) * l; }
            const r = { x: Math.round(Math.min(a.x0, x1)), y: Math.round(Math.min(a.y0, y1)), w: Math.round(Math.abs(x1 - a.x0)), h: Math.round(Math.abs(y1 - a.y0)) };
            if (a.tipo === 'selecao') S.selecao = r.w && r.h ? r : null; else S.recorte = r.w && r.h ? r : null;
        } else if (a.tipo === 'forma') {
            let x1 = ix, y1 = iy;
            if (e.shiftKey) {
                if (S.ferramenta === 'linha') { const ang = Math.round(Math.atan2(y1 - a.y0, x1 - a.x0) / (Math.PI / 4)) * Math.PI / 4, l = Math.hypot(x1 - a.x0, y1 - a.y0); x1 = a.x0 + Math.cos(ang) * l; y1 = a.y0 + Math.sin(ang) * l; }
                else { const l = Math.max(Math.abs(x1 - a.x0), Math.abs(y1 - a.y0)); x1 = a.x0 + Math.sign(x1 - a.x0 || 1) * l; y1 = a.y0 + Math.sign(y1 - a.y0 || 1) * l; }
            }
            a.x1 = x1; a.y1 = y1;
        }
        render();
    }
    function aoSoltar() {
        if (!S) return;
        if (S._focarTexto) { const ta = S._focarTexto; S._focarTexto = null; setTimeout(() => ta.isConnected && ta.focus(), 0); }
        const a = S.arraste; S.arraste = null;
        if (!a) return;
        const c = camadaAtiva();
        if (a.tipo === 'traco' && c) {
            passoPixels(c);
            aplicarTraco(c.cv.getContext('2d'));
            S.temp.getContext('2d').clearRect(0, 0, S.W, S.H);
            desenharCamadas();
        } else if (a.tipo === 'mover' && c && (a.dx || a.dy)) {
            passoPixels(c);
            const v = copiaCanvas(c.cv), cx = c.cv.getContext('2d');
            cx.clearRect(0, 0, S.W, S.H); cx.drawImage(v, a.dx, a.dy);
            desenharCamadas();
        } else if (a.tipo === 'forma' && c) {
            passoPixels(c);
            desenharForma(c.cv.getContext('2d'), a.x0, a.y0, a.x1, a.y1, false);
            desenharCamadas();
        } else if (a.tipo === 'recorte' && S.recorte) {
            aviso('Ajuste o retângulo e aperte Enter (ou "Aplicar recorte") para recortar.');
            desenharOpcoes();
        }
        render();
    }
    function pegarCor(ix, iy, secundaria) {
        ix = Math.floor(ix); iy = Math.floor(iy);
        if (ix < 0 || iy < 0 || ix >= S.W || iy >= S.H) return;
        const d = achatar(false).getContext('2d').getImageData(ix, iy, 1, 1).data;
        const cor = rgbHex(d[0], d[1], d[2]);
        if (secundaria) S.cor2 = cor; else S.cor1 = cor;
        sincronizarCores();
        aviso(`Cor ${secundaria ? 'secundária' : 'principal'}: ${cor}`);
    }

    /* ---------- painel de camadas ---------- */
    function desenharCamadas() {
        if (!S) return;
        const box = el.querySelector('[data-camadas]');
        box.innerHTML = '';
        for (let i = S.camadas.length - 1; i >= 0; i--) {
            const c = S.camadas[i];
            const linha = document.createElement('div');
            linha.className = 'wfe-cam' + (i === S.ativa ? ' sel' : '');
            const mini = novoCanvas(84, 64); mini.style.width = '42px';
            const mx = mini.getContext('2d'), k = Math.min(84 / S.W, 64 / S.H);
            mx.drawImage(c.cv, (84 - S.W * k) / 2, (64 - S.H * k) / 2, S.W * k, S.H * k);
            const olho = document.createElement('button');
            olho.className = 'olho' + (c.visivel ? '' : ' off'); olho.title = c.visivel ? 'Esconder camada' : 'Mostrar camada'; olho.textContent = '👁';
            olho.addEventListener('click', e => { e.stopPropagation(); passoFull(); c.visivel = !c.visivel; desenharCamadas(); render(); });
            const nome = document.createElement('span'); nome.textContent = c.nome; nome.title = 'Duplo clique para renomear';
            nome.addEventListener('dblclick', e => {
                e.stopPropagation();
                const inp = document.createElement('input'); inp.value = c.nome; inp.style.flex = '1'; inp.style.minWidth = '0';
                nome.replaceWith(inp); inp.focus(); inp.select();
                const fim = ok => { if (ok && inp.value.trim()) c.nome = inp.value.trim().slice(0, 40); desenharCamadas(); };
                inp.addEventListener('keydown', ev => { ev.stopPropagation(); if (ev.key === 'Enter') fim(true); if (ev.key === 'Escape') fim(false); });
                inp.addEventListener('blur', () => fim(true));
            });
            linha.append(olho, mini, nome);
            linha.addEventListener('click', () => { S.ativa = i; desenharCamadas(); render(); });
            box.appendChild(linha);
        }
        const c = camadaAtiva();
        el.querySelector('[data-cam-opac]').value = c ? Math.round(c.opacidade * 100) : 100;
        el.querySelector('[data-cam-opac-v]').textContent = (c ? Math.round(c.opacidade * 100) : 100) + '%';
        el.querySelector('[data-cam-modo]').value = c ? c.modo : 'source-over';
        el.querySelector('[data-ncam]').textContent = `${S.camadas.length}/${MAX_CAMADAS}`;
        atualizarBotoes();
    }
    function atualizarBotoes() {
        if (!S) return;
        el.querySelector('[data-acao="desfazer"]').disabled = !S.hist.length;
        el.querySelector('[data-acao="refazer"]').disabled = !S.refaz.length;
    }

    /* ---------- barra de opções da ferramenta ---------- */
    function desenharOpcoes() {
        const box = el.querySelector('[data-opcoes]');
        const f = S.ferramenta;
        const rng = (k, nome, a, b, suf = '') => `<label>${nome}<input type="range" min="${a}" max="${b}" value="${S[k]}" data-op="${k}" style="width:110px"><b data-op-v="${k}">${S[k]}${suf}</b></label>`;
        const chk = (k, nome) => `<label><input type="checkbox" data-op-c="${k}" ${S[k] ? 'checked' : ''}>${nome}</label>`;
        let h = `<b style="color:#e5e7eb">${esc(FERRAMENTAS.find(x => x[0] === f)[1])}</b>`;
        if (f === 'pincel' || f === 'borracha') h += rng('tamanho', 'Tamanho', 1, 300, 'px') + rng('opacidadePincel', 'Opacidade', 1, 100, '%') + rng('suavidade', 'Suavidade', 0, 100, '%') + '<span style="color:#9aa3af">Caneta com pressão funciona.</span>';
        else if (['retangulo', 'elipse', 'linha'].includes(f)) h += rng('tamanho', 'Espessura', 1, 120, 'px') + rng('opacidadePincel', 'Opacidade', 1, 100, '%') + (f !== 'linha' ? chk('preencher', 'Preencher') : '') + '<span style="color:#9aa3af">Shift: proporção certa / ângulos de 45°.</span>';
        else if (f === 'balde') h += rng('tolerancia', 'Tolerância', 0, 255) + rng('opacidadePincel', 'Opacidade', 1, 100, '%') + chk('contiguo', 'Só a área ligada');
        else if (f === 'texto') h += rng('fonte', 'Tamanho', 8, 300, 'px') + chk('negrito', 'Negrito') + rng('opacidadePincel', 'Opacidade', 1, 100, '%') + '<span style="color:#9aa3af">Clique na imagem para escrever. Cada texto vira uma camada.</span>';
        else if (f === 'contagotas') h += '<span style="color:#9aa3af">Clique para pegar a cor principal. Alt + clique pega a secundária.</span>';
        else if (f === 'selecao') h += '<span style="color:#9aa3af">Arraste para selecionar (Shift = quadrado). Pincel, balde e formas ficam dentro da seleção.</span><button class="wfe-b" data-acao="copiarParaCamada">Copiar para camada</button><button class="wfe-b" data-acao="apagarSelecao">Apagar</button><button class="wfe-b" data-acao="recortarSelecao">Recortar imagem</button><button class="wfe-b" data-acao="desmarcar">Desmarcar</button>';
        else if (f === 'recorte') h += `<span style="color:#9aa3af">Arraste o retângulo que deve ficar.</span><button class="wfe-b prim" data-acao="aplicarRecorte" ${S.recorte ? '' : 'disabled'}>Aplicar recorte <kbd>Enter</kbd></button><button class="wfe-b" data-acao="cancelarRecorte">Cancelar</button>`;
        else if (f === 'mover') h += '<span style="color:#9aa3af">Arraste para mover a camada ativa. Setas movem 1 px (Shift: 10 px).</span>';
        else if (f === 'mao') h += '<span style="color:#9aa3af">Arraste para mover a vista. Ctrl + roda do mouse dá zoom.</span>';
        box.innerHTML = h;
        box.querySelectorAll('[data-op]').forEach(i => i.addEventListener('input', () => {
            S[i.dataset.op] = Number(i.value);
            box.querySelector(`[data-op-v="${i.dataset.op}"]`).textContent = i.value + ({ tamanho: 'px', fonte: 'px', opacidadePincel: '%', suavidade: '%' }[i.dataset.op] || '');
            render();
        }));
        box.querySelectorAll('[data-op-c]').forEach(i => i.addEventListener('change', () => { S[i.dataset.opC] = i.checked; }));
    }
    function escolherFerramenta(f) {
        if (S.ferramenta === 'recorte' && f !== 'recorte') S.recorte = null;
        S.ferramenta = f;
        fecharTexto();
        el.querySelectorAll('[data-ferramenta]').forEach(b => b.classList.toggle('sel', b.dataset.ferramenta === f));
        desenharOpcoes(); atualizarCursor(); render();
    }
    function atualizarCursor() {
        const f = S.espaco ? 'mao' : S.ferramenta;
        el.querySelector('[data-palco]').style.cursor = { mao: S.arraste ? 'grabbing' : 'grab', mover: 'move', texto: 'text', pincel: 'none', borracha: 'none', contagotas: 'copy' }[f] || 'crosshair';
    }
    function sincronizarCores() { el.querySelector('[data-cor1]').value = S.cor1; el.querySelector('[data-cor2]').value = S.cor2; }

    function aviso(t) {
        const a = el.querySelector('[data-aviso]');
        a.textContent = t; a.classList.add('ver');
        clearTimeout(a._t); a._t = setTimeout(() => a.classList.remove('ver'), 2600);
    }

    /* ---------- transformações da imagem inteira ---------- */
    function transformarTudo(novoW, novoH, desenho) {
        passoFull();
        S.camadas.forEach(c => {
            const n = novoCanvas(novoW, novoH), cx = n.getContext('2d');
            desenho(cx, c.cv); c.cv = n;
        });
        S.W = novoW; S.H = novoH; S.temp = novoCanvas(S.W, S.H); S.selecao = null; S.recorte = null;
        desenharCamadas(); ajustarTela();
    }
    function recortar(r) {
        if (!r || r.w < 1 || r.h < 1) return;
        transformarTudo(r.w, r.h, (cx, v) => cx.drawImage(v, -r.x, -r.y));
        aviso(`Imagem recortada para ${r.w}×${r.h}.`);
    }
    function dialogo(titulo, campos, aoOk) {
        const palco = el.querySelector('[data-palco]');
        const d = document.createElement('div'); d.className = 'wfe-dialogo';
        d.innerHTML = `<div><b>${esc(titulo)}</b>${campos.map(c => c.tipo === 'check'
            ? `<label style="display:flex;gap:6px;align-items:center"><input type="checkbox" data-c="${c.k}" ${c.v ? 'checked' : ''}>${esc(c.n)}</label>`
            : `<label style="display:flex;justify-content:space-between;gap:10px;align-items:center">${esc(c.n)}<input type="number" min="1" max="${MAX_LADO * 2}" value="${c.v}" data-c="${c.k}" style="width:110px"></label>`).join('')}
            <div style="display:flex;gap:6px;justify-content:flex-end;margin-top:4px"><button class="wfe-b" data-d="nao">Cancelar</button><button class="wfe-b prim" data-d="sim">OK</button></div></div>`;
        palco.appendChild(d);
        const ler = () => Object.fromEntries([...d.querySelectorAll('[data-c]')].map(i => [i.dataset.c, i.type === 'checkbox' ? i.checked : Number(i.value)]));
        d.addEventListener('input', e => { if (d._aoMudar) d._aoMudar(e.target, ler(), d); });
        d.addEventListener('keydown', e => { e.stopPropagation(); if (e.key === 'Enter') { d.remove(); aoOk(ler()); } if (e.key === 'Escape') d.remove(); });
        d.addEventListener('click', e => { const b = e.target.closest('[data-d]'); if (!b) return; d.remove(); if (b.dataset.d === 'sim') aoOk(ler()); });
        setTimeout(() => d.querySelector('input')?.focus(), 0);
        return d;
    }

    /* ---------- ações (botões, menus e teclas) ---------- */
    const ACOES = {
        abrirArquivo() { S.abrirComo = 'doc'; el.querySelector('[data-arquivo]').click(); },
        abrirComoCamada() { S.abrirComo = 'camada'; el.querySelector('[data-arquivo]').click(); },
        novaTela() {
            dialogo('Nova tela em branco', [{ k: 'w', n: 'Largura (px)', v: 1200 }, { k: 'h', n: 'Altura (px)', v: 800 }, { k: 'transp', n: 'Fundo transparente', tipo: 'check', v: false }], v => {
                const w = clamp(Math.round(v.w) || 1200, 1, MAX_LADO), h = clamp(Math.round(v.h) || 800, 1, MAX_LADO);
                passoFull();
                S.W = w; S.H = h; S.temp = novoCanvas(w, h); S.selecao = null; S.recorte = null;
                const cam = novaCamadaVazia('Fundo');
                if (!v.transp) { const cx = cam.cv.getContext('2d'); cx.fillStyle = S.cor2; cx.fillRect(0, 0, w, h); }
                S.camadas = [cam]; S.ativa = 0;
                desenharCamadas(); ajustarTela();
            });
        },
        exportarPng() { baixar(achatar(false).toDataURL('image/png'), 'png'); },
        exportarJpg() { baixar(achatar(true).toDataURL('image/jpeg', 0.92), 'jpg'); },
        girarDir() { const w = S.W, h = S.H; transformarTudo(h, w, (cx, v) => { cx.translate(h, 0); cx.rotate(Math.PI / 2); cx.drawImage(v, 0, 0); }); },
        girarEsq() { const w = S.W, h = S.H; transformarTudo(h, w, (cx, v) => { cx.translate(0, w); cx.rotate(-Math.PI / 2); cx.drawImage(v, 0, 0); }); },
        espelharH() { transformarTudo(S.W, S.H, (cx, v) => { cx.translate(S.W, 0); cx.scale(-1, 1); cx.drawImage(v, 0, 0); }); },
        espelharV() { transformarTudo(S.W, S.H, (cx, v) => { cx.translate(0, S.H); cx.scale(1, -1); cx.drawImage(v, 0, 0); }); },
        redimensionar() {
            const d = dialogo('Tamanho da imagem', [{ k: 'w', n: 'Largura (px)', v: S.W }, { k: 'h', n: 'Altura (px)', v: S.H }, { k: 'prop', n: 'Manter a proporção', tipo: 'check', v: true }], v => {
                const w = clamp(Math.round(v.w) || S.W, 1, MAX_LADO), h = clamp(Math.round(v.h) || S.H, 1, MAX_LADO);
                if (w === S.W && h === S.H) return;
                transformarTudo(w, h, (cx, c) => { cx.imageSmoothingQuality = 'high'; cx.drawImage(c, 0, 0, w, h); });
                aviso(`Imagem agora tem ${w}×${h}.`);
            });
            const r = S.W / S.H;
            d._aoMudar = (alvo, v, dd) => {
                if (!v.prop) return;
                if (alvo.dataset.c === 'w') dd.querySelector('[data-c="h"]').value = Math.max(1, Math.round(v.w / r));
                if (alvo.dataset.c === 'h') dd.querySelector('[data-c="w"]').value = Math.max(1, Math.round(v.h * r));
            };
        },
        recortarSelecao() { if (!S.selecao) { aviso('Faça uma seleção primeiro (ferramenta Seleção, M).'); return; } recortar(S.selecao); },
        aplicarRecorte() { if (S.recorte) recortar(S.recorte); escolherFerramenta('recorte'); },
        cancelarRecorte() { S.recorte = null; desenharOpcoes(); render(); },
        achatar() {
            if (S.camadas.length < 2) { aviso('Só existe uma camada.'); return; }
            passoFull();
            const cam = novaCamadaVazia('Fundo'); cam.cv = achatar(false);
            S.camadas = [cam]; S.ativa = 0; desenharCamadas(); render();
        },
        selecionarTudo() { S.selecao = { x: 0, y: 0, w: S.W, h: S.H }; render(); },
        desmarcar() { S.selecao = null; render(); },
        apagarSelecao() {
            const c = camadaAtiva(); if (!c) return;
            if (!S.selecao) { aviso('Nada selecionado. Use a Seleção (M) ou Ctrl+A.'); return; }
            passoPixels(c); c.cv.getContext('2d').clearRect(S.selecao.x, S.selecao.y, S.selecao.w, S.selecao.h);
            desenharCamadas(); render();
        },
        preencherSelecao() {
            const c = camadaAtiva(); if (!c) return;
            const r = S.selecao || { x: 0, y: 0, w: S.W, h: S.H };
            passoPixels(c);
            const cx = c.cv.getContext('2d'); cx.globalAlpha = S.opacidadePincel / 100; cx.fillStyle = S.cor1; cx.fillRect(r.x, r.y, r.w, r.h); cx.globalAlpha = 1;
            desenharCamadas(); render();
        },
        copiarParaCamada() {
            const c = camadaAtiva(); if (!c) return;
            if (S.camadas.length >= MAX_CAMADAS) { aviso(`Limite de ${MAX_CAMADAS} camadas.`); return; }
            const r = S.selecao || { x: 0, y: 0, w: S.W, h: S.H };
            passoFull();
            const cam = novaCamadaVazia(c.nome + ' (cópia)');
            cam.cv.getContext('2d').drawImage(c.cv, r.x, r.y, r.w, r.h, r.x, r.y, r.w, r.h);
            S.camadas.splice(S.ativa + 1, 0, cam); S.ativa++;
            desenharCamadas(); render();
        },
        desfazer() { voltar(S.hist, S.refaz); },
        refazer() { voltar(S.refaz, S.hist); },
        zoomMais() { zoomEm(1.25); }, zoomMenos() { zoomEm(1 / 1.25); }, ajustarTela() { ajustarTela(); },
        novaCamada() {
            if (S.camadas.length >= MAX_CAMADAS) { aviso(`Limite de ${MAX_CAMADAS} camadas.`); return; }
            passoFull(); S.camadas.splice(S.ativa + 1, 0, novaCamadaVazia(nomeLivre('Camada'))); S.ativa++;
            desenharCamadas(); render();
        },
        duplicarCamada() {
            const c = camadaAtiva(); if (!c) return;
            if (S.camadas.length >= MAX_CAMADAS) { aviso(`Limite de ${MAX_CAMADAS} camadas.`); return; }
            passoFull(); S.camadas.splice(S.ativa + 1, 0, { ...c, id: uid(), nome: c.nome + ' (cópia)', cv: copiaCanvas(c.cv) }); S.ativa++;
            desenharCamadas(); render();
        },
        excluirCamada() {
            if (S.camadas.length < 2) { aviso('A imagem precisa de pelo menos uma camada. Use "Limpar" para apagar o conteúdo.'); return; }
            passoFull(); S.camadas.splice(S.ativa, 1); S.ativa = clamp(S.ativa - 1, 0, S.camadas.length - 1);
            desenharCamadas(); render();
        },
        subirCamada() {
            if (S.ativa >= S.camadas.length - 1) return;
            passoFull(); const [c] = S.camadas.splice(S.ativa, 1); S.camadas.splice(S.ativa + 1, 0, c); S.ativa++;
            desenharCamadas(); render();
        },
        descerCamada() {
            if (S.ativa <= 0) return;
            passoFull(); const [c] = S.camadas.splice(S.ativa, 1); S.camadas.splice(S.ativa - 1, 0, c); S.ativa--;
            desenharCamadas(); render();
        },
        mesclarAbaixo() {
            if (S.ativa <= 0) { aviso('Não há camada abaixo para mesclar.'); return; }
            passoFull();
            const de = S.camadas[S.ativa], para = S.camadas[S.ativa - 1], cx = para.cv.getContext('2d');
            if (de.visivel) { cx.globalAlpha = de.opacidade; cx.globalCompositeOperation = de.modo; cx.drawImage(de.cv, 0, 0); cx.globalAlpha = 1; cx.globalCompositeOperation = 'source-over'; }
            S.camadas.splice(S.ativa, 1); S.ativa--;
            desenharCamadas(); render();
        },
        espelharCamadaH() {
            const c = camadaAtiva(); if (!c) return;
            passoPixels(c);
            const v = copiaCanvas(c.cv), cx = c.cv.getContext('2d');
            cx.clearRect(0, 0, S.W, S.H); cx.save(); cx.translate(S.W, 0); cx.scale(-1, 1); cx.drawImage(v, 0, 0); cx.restore();
            desenharCamadas(); render();
        },
        limparCamada() { const c = camadaAtiva(); if (!c) return; passoPixels(c); c.cv.getContext('2d').clearRect(0, 0, S.W, S.H); desenharCamadas(); render(); },
        zerarAjustes() {
            S.ajustes = zeroAjustes();
            el.querySelectorAll('[data-aj]').forEach(i => { i.value = S.ajustes[i.dataset.aj]; el.querySelector(`[data-aj-v="${i.dataset.aj}"]`).textContent = i.value; });
            render();
        },
        aplicarAjustes() {
            const c = camadaAtiva(); if (!c) return;
            const f = filtroAjustes(S.ajustes);
            if (f === 'none') { aviso('Mexa em algum ajuste primeiro.'); return; }
            passoPixels(c);
            const v = copiaCanvas(c.cv), cx = c.cv.getContext('2d');
            cx.clearRect(0, 0, S.W, S.H);
            if (temFiltro) { cx.filter = f; cx.drawImage(v, 0, 0); cx.filter = 'none'; }
            else { cx.drawImage(v, 0, 0); ajustarPixels(cx, S.ajustes); }
            ACOES.zerarAjustes(); desenharCamadas(); render();
            aviso('Ajustes aplicados à camada.');
        },
        cancelar() { fechar(false); },
        salvar() { fechar(true); },
    };

    /** Ajustes sem ctx.filter (navegadores antigos): brilho, contraste, saturação, P&B, sépia e inverter. */
    function ajustarPixels(cx, a) {
        const img = cx.getImageData(0, 0, S.W, S.H), d = img.data;
        const br = a.brilho / 100, ct = a.contraste / 100, sa = a.saturacao / 100, gr = a.cinza / 100, se = a.sepia / 100, iv = a.inverter / 100;
        for (let i = 0; i < d.length; i += 4) {
            let r = d[i] * br, g = d[i + 1] * br, b = d[i + 2] * br;
            r = (r - 128) * ct + 128; g = (g - 128) * ct + 128; b = (b - 128) * ct + 128;
            const l = 0.2126 * r + 0.7152 * g + 0.0722 * b;
            r = l + (r - l) * sa; g = l + (g - l) * sa; b = l + (b - l) * sa;
            if (gr) { const m = 0.2126 * r + 0.7152 * g + 0.0722 * b; r += (m - r) * gr; g += (m - g) * gr; b += (m - b) * gr; }
            if (se) { const sr = .393 * r + .769 * g + .189 * b, sg = .349 * r + .686 * g + .168 * b, sb = .272 * r + .534 * g + .131 * b; r += (sr - r) * se; g += (sg - g) * se; b += (sb - b) * se; }
            if (iv) { r += (255 - 2 * r) * iv; g += (255 - 2 * g) * iv; b += (255 - 2 * b) * iv; }
            d[i] = clamp(r, 0, 255); d[i + 1] = clamp(g, 0, 255); d[i + 2] = clamp(b, 0, 255);
        }
        cx.putImageData(img, 0, 0);
    }

    async function abrirImagem(src) {
        try {
            const im = await carregar(src);
            const k = Math.min(1, MAX_LADO / Math.max(im.width, im.height));
            passoFull();
            S.W = Math.round(im.width * k); S.H = Math.round(im.height * k);
            S.temp = novoCanvas(S.W, S.H); S.selecao = null; S.recorte = null;
            const cam = novaCamadaVazia('Fundo'); cam.cv.getContext('2d').drawImage(im, 0, 0, S.W, S.H);
            S.camadas = [cam]; S.ativa = 0;
            desenharCamadas(); ajustarTela();
            if (k < 1) aviso(`A imagem foi reduzida para ${S.W}×${S.H} (máximo ${MAX_LADO} px).`);
        } catch { aviso('Não consegui abrir esse arquivo.'); }
    }
    async function colocarComoCamada(src, nome) {
        if (S.camadas.length >= MAX_CAMADAS) { aviso(`Limite de ${MAX_CAMADAS} camadas.`); return; }
        try {
            const im = await carregar(src);
            const k = Math.min(1, S.W / im.width, S.H / im.height);
            passoFull();
            const cam = novaCamadaVazia((nome || 'Imagem').replace(/\.[a-z0-9]+$/i, '').slice(0, 30));
            const w = im.width * k, h = im.height * k;
            cam.cv.getContext('2d').drawImage(im, (S.W - w) / 2, (S.H - h) / 2, w, h);
            S.camadas.splice(S.ativa + 1, 0, cam); S.ativa++;
            escolherFerramenta('mover');
            desenharCamadas(); render();
            aviso('Imagem colocada numa camada nova. Arraste para posicionar.');
        } catch { aviso('Não consegui abrir esse arquivo.'); }
    }
    function baixar(url, ext) {
        const a = document.createElement('a');
        a.href = url; a.download = (S.titulo || 'imagem').replace(/[\\/:*?"<>|]+/g, '').slice(0, 60) + '.' + ext;
        document.body.appendChild(a); a.click(); a.remove();
    }

    /* ---------- teclado ---------- */
    function teclado(e) {
        if (!S || !el || el.hidden) return;
        const t = e.target;
        if (t && (t.tagName === 'INPUT' && t.type !== 'range' && t.type !== 'checkbox' || t.tagName === 'TEXTAREA')) return;
        const ctrl = e.ctrlKey || e.metaKey, k = e.key.toLowerCase();
        const pare = () => { e.preventDefault(); e.stopPropagation(); };
        if (e.code === 'Space' && !ctrl) { pare(); if (!S.espaco) { S.espaco = true; atualizarCursor(); } return; }
        if (k === 'escape') {
            pare();
            if (el.querySelector('.wfe-dialogo')) { el.querySelector('.wfe-dialogo').remove(); return; }
            if (el.querySelector('.wfe-menu.aberto')) { el.querySelectorAll('.wfe-menu.aberto').forEach(m => m.classList.remove('aberto')); return; }
            if (S.recorte) { ACOES.cancelarRecorte(); return; }
            if (S.selecao) { ACOES.desmarcar(); return; }
            ACOES.cancelar(); return;
        }
        if (el.querySelector('.wfe-dialogo')) return;
        if (ctrl) {
            const mapa = { z: e.shiftKey ? 'refazer' : 'desfazer', y: 'refazer', s: 'salvar', o: 'abrirArquivo', a: 'selecionarTudo', d: 'desmarcar', j: 'copiarParaCamada', e: 'mesclarAbaixo', '0': 'ajustarTela', '=': 'zoomMais', '+': 'zoomMais', '-': 'zoomMenos' };
            if (k === 'n' && e.shiftKey) { pare(); ACOES.novaCamada(); return; }
            if (mapa[k]) { pare(); ACOES[mapa[k]](); }
            else pare(); // não deixa atalhos do navegador/página escaparem com o editor aberto
            return;
        }
        if (k === 'enter' && S.recorte) { pare(); ACOES.aplicarRecorte(); return; }
        if ((k === 'delete' || k === 'backspace')) { pare(); if (e.altKey) ACOES.preencherSelecao(); else ACOES.apagarSelecao(); return; }
        if (S.ferramenta === 'mover' && k.startsWith('arrow')) {
            pare();
            const c = camadaAtiva(); if (!c) return;
            const p = e.shiftKey ? 10 : 1, dx = k === 'arrowleft' ? -p : k === 'arrowright' ? p : 0, dy = k === 'arrowup' ? -p : k === 'arrowdown' ? p : 0;
            passoPixels(c); const v = copiaCanvas(c.cv), cx = c.cv.getContext('2d'); cx.clearRect(0, 0, S.W, S.H); cx.drawImage(v, dx, dy);
            desenharCamadas(); render(); return;
        }
        if (k === '[' || k === ']') { pare(); S.tamanho = clamp(Math.round(S.tamanho * (k === ']' ? 1.2 : 1 / 1.2)) + (k === ']' ? 1 : 0), 1, 300); desenharOpcoes(); render(); return; }
        if (k === 'x') { pare(); [S.cor1, S.cor2] = [S.cor2, S.cor1]; sincronizarCores(); return; }
        const f = FERRAMENTAS.find(x => x[2].toLowerCase() === k);
        if (f && !e.altKey) { pare(); escolherFerramenta(f[0]); return; }
        // as outras teclas não chegam aos atalhos da página enquanto o editor está aberto
        e.stopPropagation();
    }

    /* ---------- abrir e fechar ---------- */
    async function fechar(salvar) {
        if (!S) return;
        fecharTexto();
        if (salvar) {
            const btn = el.querySelector('[data-acao="salvar"]');
            btn.disabled = true; btn.textContent = 'Salvando…';
            try {
                const plano = achatar(false);
                let imagem = plano.toDataURL('image/webp', 0.9);
                if (!imagem.startsWith('data:image/webp')) imagem = plano.toDataURL('image/png');
                let projeto = serializar(0.95), txt = JSON.stringify(projeto);
                if (txt.length > LIMITE_SALVAR) { projeto = serializar(0.8); txt = JSON.stringify(projeto); }
                if (txt.length > LIMITE_SALVAR) {
                    // grande demais para guardar as camadas: fica só a imagem achatada
                    projeto = null;
                    if (!confirm('As camadas ficaram grandes demais para salvar. Salvar só a imagem final (as camadas são juntadas)?')) { btn.disabled = false; btn.textContent = 'Salvar e fechar'; return; }
                }
                if (S.aoSalvar) await S.aoSalvar({ imagem, projeto, w: S.W, h: S.H });
            } catch (err) {
                btn.disabled = false; btn.textContent = 'Salvar e fechar';
                aviso('Não consegui salvar. Tente de novo.'); return;
            }
            btn.disabled = false; btn.textContent = 'Salvar e fechar';
        } else if (S.alterado && !confirm('Sair sem salvar? As alterações feitas no editor serão perdidas.')) return;
        el.hidden = true;
        document.documentElement.style.overflow = S._overflow || '';
        const fim = S.aoFechar; S = null;
        if (fim) fim();
    }
    async function abrir(opcoes = {}) {
        if (!el) montar();
        if (S) return;
        await abrirDocumento(opcoes);
        S._overflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        el.hidden = false;
        el.querySelector('[data-titulo]').textContent = S.titulo;
        S.hist = []; S.refaz = []; S.alterado = false;
        sincronizarCores(); escolherFerramenta('pincel'); desenharCamadas();
        requestAnimationFrame(() => { ajustarTela(); });
    }

    window.WFEditorImagem = { abrir, aberto: () => !!S };
})();
