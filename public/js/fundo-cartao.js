/* Work Flower · fundo de cada cartão (Hábitos, Tarefas, Nova tarefa, gráficos, qualquer cartão de qualquer quadro).
   Fica no layout do cartão: estado.layout[aba][id].fundo = {
       tipo: 'cor' | 'imagem', cor: '#rrggbb', opac: 0..100 (100 = sem transparência), img: token de /midia,
       ajuste: 'cover' | 'contain' | 'repeat', escurecer: 0..90, desfoque: 0..20 (da imagem), vidro: bool (desfoca o que está atrás) }
   O desenho vai num ::before do cartão (assim a transparência vale só para o fundo, não para o conteúdo).
   window.WFFundoCartao.css(comp)               → regras CSS de todos os cartões com fundo próprio
   window.WFFundoCartao.abrir(comp, sec, id, el) → painel para escolher (prévia ao vivo; salva sozinho)
   Usado pelo quadro.js (menu do clique direito: "Fundo do cartão…"). Imagens/GIFs sobem por public/js/midia.js (até 25 MB). */
(function () {
    'use strict';
    if (window.WFFundoCartao) return;

    const CORES = ['#0b0f16', '#111827', '#1e293b', '#312e81', '#4c1d95', '#7f1d1d', '#14532d', '#0c4a6e', '#78350f', '#f8fafc'];
    const TOKEN = /^[A-Za-z0-9]{32}$/;
    const COR = /^#[0-9a-fA-F]{6}$/;
    const q = s => (window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/["\\]/g, '\\$&'));
    const urlMidia = t => {
        const tag = document.querySelector('script[src*="js/midia.js"]');
        return ((tag && tag.dataset.url) || '/midia').replace(/\/$/, '') + '/' + t;
    };
    const num = (v, a, b, p) => { v = Math.round(+v); return Number.isFinite(v) ? Math.min(b, Math.max(a, v)) : p; };

    /** Confere e completa o que veio do estado (pode ter sido mexido à mão ou vir de outra versão). */
    function limpo(f) {
        if (!f || typeof f !== 'object' || !['cor', 'imagem'].includes(f.tipo)) return null;
        return {
            tipo: f.tipo,
            cor: COR.test(f.cor || '') ? f.cor : '#111827',
            opac: num(f.opac, 0, 100, 100),
            img: TOKEN.test(f.img || '') ? f.img : null,
            ajuste: ['cover', 'contain', 'repeat'].includes(f.ajuste) ? f.ajuste : 'cover',
            escurecer: num(f.escurecer, 0, 90, 30),
            desfoque: num(f.desfoque, 0, 20, 0),
            vidro: !!f.vidro,
        };
    }

    function regra(sec, id, f) {
        const sel = `.wf-item[data-sec="${q(sec)}"][data-bid="${q(id)}"]`;
        const base = `color-mix(in srgb, ${f.cor} ${f.tipo === 'cor' ? 100 : 100}%, transparent)`;
        let fundo = base;
        if (f.tipo === 'imagem' && f.img) {
            const e = (f.escurecer / 100).toFixed(2);
            const tam = f.ajuste === 'repeat' ? '40%' : f.ajuste;
            fundo = `linear-gradient(rgba(0,0,0,${e}),rgba(0,0,0,${e})),url("${urlMidia(f.img)}") center/${tam} ${f.ajuste === 'repeat' ? 'repeat' : 'no-repeat'},${base}`;
        }
        const op = (f.opac / 100).toFixed(2);
        const blur = f.tipo === 'imagem' && f.desfoque ? `filter:blur(${Math.max(1, Math.round(f.desfoque / 2))}px);inset:-12px;` : 'inset:0;';
        return [
            `${sel}{background:transparent!important;isolation:isolate;${f.vidro ? 'backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);' : ''}}`,
            `${sel}::before{content:"";position:absolute;${blur}z-index:-1;pointer-events:none;background:${fundo};opacity:${op};border-radius:inherit}`,
            // o topo e o corpo ficam translúcidos para o fundo aparecer
            `${sel}>.wf-topo{background:color-mix(in srgb,var(--superficie-2) ${Math.min(55, f.opac)}%,transparent)!important}`,
        ].join('\n');
    }

    function css(c) {
        const out = [];
        const lay = (c && c.estado && c.estado.layout) || {};
        Object.keys(lay).forEach(sec => Object.keys(lay[sec] || {}).forEach(id => {
            const f = limpo(lay[sec][id] && lay[sec][id].fundo);
            if (f) out.push(regra(sec, id, f));
        }));
        return out.join('\n');
    }

    /* ---------------- painel ---------------- */
    const ESTILO = `
    .wf-fc{position:fixed;z-index:310;width:min(21rem,calc(100vw - 1.5rem));max-height:calc(100vh - 1.5rem);overflow:auto;padding:.9rem;border-radius:.8rem;
        background:var(--superficie,#141820);color:var(--tinta,#e5e7eb);border:1px solid var(--linha,#2c323c);box-shadow:0 18px 50px rgba(0,0,0,.45);font:13px/1.35 system-ui,sans-serif}
    .wf-fc h3{font-weight:700;font-size:14px;margin:0 0 .6rem;display:flex;align-items:center;gap:.5rem}
    .wf-fc h3 small{font-weight:500;opacity:.6;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
    .wf-fc .seg{display:grid;grid-template-columns:repeat(3,1fr);gap:.25rem;margin-bottom:.7rem}
    .wf-fc .seg button,.wf-fc .bt{padding:.38rem .5rem;border-radius:.45rem;border:1px solid var(--linha,#2c323c);background:var(--superficie-2,#1b2029);color:inherit;cursor:pointer;font:inherit}
    .wf-fc .seg button.on{background:var(--prim,#2f6fc7);border-color:var(--prim,#2f6fc7);color:var(--on-prim,#fff)}
    .wf-fc .bt.prim{background:var(--prim,#2f6fc7);border-color:var(--prim,#2f6fc7);color:var(--on-prim,#fff)}
    .wf-fc label.l{display:block;font-size:11px;opacity:.7;margin:.55rem 0 .25rem}
    .wf-fc .cores{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center}
    .wf-fc .cores button{width:1.45rem;height:1.45rem;border-radius:9999px;border:2px solid transparent;cursor:pointer;padding:0}
    .wf-fc .cores button.on{border-color:var(--tinta,#fff)}
    .wf-fc input[type=color]{width:1.8rem;height:1.6rem;border:0;background:none;padding:0;cursor:pointer}
    .wf-fc input[type=range]{width:100%}
    .wf-fc .lin{display:flex;gap:.4rem;align-items:center}
    .wf-fc .solta{display:grid;place-items:center;gap:.2rem;min-height:5.2rem;border:1px dashed var(--linha,#2c323c);border-radius:.6rem;cursor:pointer;text-align:center;padding:.5rem;
        background-size:cover;background-position:center;position:relative;overflow:hidden}
    .wf-fc .solta.sobre{border-color:var(--prim,#2f6fc7)}
    .wf-fc .solta span{background:rgba(0,0,0,.55);padding:.15rem .45rem;border-radius:.35rem;color:#fff}
    .wf-fc .campo{flex:1;min-width:0;padding:.38rem .5rem;border-radius:.45rem;border:1px solid var(--linha,#2c323c);background:var(--superficie-2,#1b2029);color:inherit;font:inherit}
    .wf-fc select{padding:.3rem .4rem;border-radius:.45rem;border:1px solid var(--linha,#2c323c);background:var(--superficie-2,#1b2029);color:inherit}
    .wf-fc .erro{color:#f87171;margin-top:.4rem}
    .wf-fc .rod{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.8rem}
    .wf-fc .rod .bt{flex:1}
    .wf-fc .x{margin-left:auto;background:none;border:0;color:inherit;cursor:pointer;font-size:16px;opacity:.7}`;

    let painel = null;
    function fechar() { if (painel) { painel.remove(); painel = null; removeEventListener('keydown', esc, true); removeEventListener('pointerdown', fora, true); } }
    function esc(e) { if (e.key === 'Escape') fechar(); }
    function fora(e) { if (painel && !painel.contains(e.target)) fechar(); }

    function abrir(c, sec, id, el) {
        fechar();
        if (!document.getElementById('wf-fc-css')) { const s = document.createElement('style'); s.id = 'wf-fc-css'; s.textContent = ESTILO; document.head.appendChild(s); }
        const entrada = () => c.entrada(sec, id);
        const nome = (c.blocoPorId && c.blocoPorId(id) && c.blocoPorId(id).titulo) || (el && el.querySelector('.wf-topo h2, .wf-topo h3, .wf-topo .wf-titulo')?.textContent) || 'Cartão';
        let f = limpo(entrada().fundo) || { tipo: 'cor', cor: '#111827', opac: 100, img: null, ajuste: 'cover', escurecer: 30, desfoque: 0, vidro: false };
        let modo = entrada().fundo ? f.tipo : 'padrao';
        let enviando = 0, erro = '';

        const aplicar = () => {
            const e = entrada();
            if (modo === 'padrao' || (modo === 'imagem' && !f.img)) delete e.fundo;
            else e.fundo = { ...f, tipo: modo };
            if (c.atualizarCssFundos) c.atualizarCssFundos();
            c.agendar && c.agendar();
        };

        painel = document.createElement('div');
        painel.className = 'wf-fc';
        painel.setAttribute('role', 'dialog');
        painel.setAttribute('aria-label', 'Fundo do cartão');
        document.body.appendChild(painel);

        const usarArquivo = async arq => {
            if (!arq) return;
            erro = ''; enviando = 0.01; desenhar();
            try {
                const m = await window.wfEnviarMidia(arq, p => { enviando = Math.max(0.01, p); const s = painel && painel.querySelector('[data-env]'); if (s) s.textContent = 'Enviando… ' + Math.round(p * 100) + '%'; });
                f.img = m.token; modo = 'imagem'; aplicar();
            } catch (e) { erro = e.message || 'Não consegui enviar esse arquivo.'; }
            enviando = 0; desenhar();
        };

        function desenhar() {
            if (!painel) return;
            const img = modo === 'imagem';
            painel.innerHTML = `
                <h3>Fundo do cartão <small></small><button type="button" class="x" data-a="fechar" aria-label="Fechar">✕</button></h3>
                <div class="seg" role="group" aria-label="Tipo de fundo">
                    <button type="button" data-m="padrao" class="${modo === 'padrao' ? 'on' : ''}">Padrão</button>
                    <button type="button" data-m="cor" class="${modo === 'cor' ? 'on' : ''}">Cor</button>
                    <button type="button" data-m="imagem" class="${img ? 'on' : ''}">Imagem/GIF</button>
                </div>
                ${modo === 'padrao' ? '<p style="opacity:.7">O cartão usa o visual do tema. Escolha uma cor ou uma imagem/GIF para personalizar só este cartão.</p>' : `
                ${img ? `
                <label class="solta" data-solta style="${f.img ? `background-image:url('${urlMidia(f.img)}')` : ''}">
                    <input type="file" accept="image/*" hidden data-arq>
                    <span data-env>${enviando ? 'Enviando… ' + Math.round(enviando * 100) + '%' : (f.img ? 'Trocar a imagem' : 'Solte uma imagem ou GIF aqui ou clique')}</span>
                    ${f.img ? '' : '<small style="opacity:.7">JPG, PNG, WebP ou GIF animado, até 25 MB</small>'}
                </label>
                <form class="lin" data-link style="margin-top:.4rem"><input class="campo" type="url" placeholder="ou cole o link de um GIF" aria-label="Link da imagem ou GIF"><button class="bt">Usar</button></form>
                <label class="l">Ajuste</label>
                <select data-c="ajuste"><option value="cover">Preencher o cartão</option><option value="contain">Mostrar inteira</option><option value="repeat">Repetir</option></select>
                <label class="l">Escurecer a imagem: <b data-v="escurecer">${f.escurecer}%</b></label>
                <input type="range" min="0" max="90" step="5" data-c="escurecer" value="${f.escurecer}">
                <label class="l">Desfocar a imagem: <b data-v="desfoque">${f.desfoque}</b></label>
                <input type="range" min="0" max="20" step="1" data-c="desfoque" value="${f.desfoque}">` : ''}
                <label class="l">${img ? 'Cor por baixo' : 'Cor'}</label>
                <div class="cores">${CORES.map(x => `<button type="button" data-cor="${x}" class="${f.cor === x ? 'on' : ''}" style="background:${x}" aria-label="Cor ${x}"></button>`).join('')}
                    <input type="color" data-c="cor" value="${f.cor}" aria-label="Outra cor"></div>
                <label class="l">Opacidade do fundo: <b data-v="opac">${f.opac}%</b> <span style="opacity:.6">(menos = mais transparente)</span></label>
                <input type="range" min="0" max="100" step="5" data-c="opac" value="${f.opac}">
                <label class="lin" style="margin-top:.5rem;cursor:pointer"><input type="checkbox" data-c="vidro" ${f.vidro ? 'checked' : ''}> Efeito vidro (desfoca o que está atrás do cartão)</label>`}
                ${erro ? `<p class="erro" role="alert"></p>` : ''}
                <div class="rod">
                    <button type="button" class="bt" data-a="todos" title="Usa este mesmo fundo em todos os cartões desta aba">Usar em todos desta aba</button>
                    <button type="button" class="bt prim" data-a="fechar">Pronto</button>
                </div>`;
            painel.querySelector('h3 small').textContent = '· ' + String(nome).trim().slice(0, 40);
            if (erro) painel.querySelector('.erro').textContent = erro;
            const sel = painel.querySelector('select[data-c="ajuste"]');
            if (sel) sel.value = f.ajuste;
        }

        painel.addEventListener('click', e => {
            const m = e.target.closest('[data-m]'), a = e.target.closest('[data-a]'), cor = e.target.closest('[data-cor]');
            if (m) { modo = m.dataset.m; if (modo === 'cor' && f.opac === 0) f.opac = 100; aplicar(); desenhar(); }
            else if (cor) { f.cor = cor.dataset.cor; aplicar(); desenhar(); }
            else if (a && a.dataset.a === 'fechar') fechar();
            else if (a && a.dataset.a === 'todos') {
                const ids = c.cartoesDe ? c.cartoesDe(sec) : [];
                if (!ids.length || !confirm(modo === 'padrao' ? `Voltar todos os ${ids.length} cartões desta aba ao visual padrão?` : `Usar este fundo nos ${ids.length} cartões desta aba?`)) return;
                ids.forEach(x => { const l = c.entrada(sec, x); if (modo === 'padrao') delete l.fundo; else l.fundo = { ...f, tipo: modo }; });
                if (c.atualizarCssFundos) c.atualizarCssFundos();
                c.agendar && c.agendar();
            }
        });
        painel.addEventListener('input', e => {
            const k = e.target.dataset.c;
            if (!k) return;
            f[k] = k === 'vidro' ? e.target.checked : k === 'cor' || k === 'ajuste' ? e.target.value : +e.target.value;
            const v = painel.querySelector(`[data-v="${k}"]`);
            if (v) v.textContent = f[k] + (k === 'desfoque' ? '' : '%');
            aplicar();
        });
        painel.addEventListener('change', e => {
            if (e.target.matches('[data-arq]')) { usarArquivo(e.target.files[0]); e.target.value = ''; }
            else if (e.target.dataset.c) { painel.querySelectorAll('.cores button').forEach(b => b.classList.toggle('on', b.dataset.cor === f.cor)); }
        });
        painel.addEventListener('submit', async e => {
            if (!e.target.matches('[data-link]')) return;
            e.preventDefault();
            const campo = e.target.querySelector('input');
            if (!campo.value.trim() || !window.wfBaixarImagem) return;
            erro = ''; enviando = 0.01; desenhar();
            try { const arq = await window.wfBaixarImagem(campo.value.trim()); await usarArquivo(arq); }
            catch (x) { erro = x.message || 'Não consegui baixar essa imagem.'; enviando = 0; desenhar(); }
        });
        painel.addEventListener('dragover', e => { const s = e.target.closest('[data-solta]'); if (s) { e.preventDefault(); s.classList.add('sobre'); } });
        painel.addEventListener('dragleave', e => { const s = e.target.closest('[data-solta]'); if (s) s.classList.remove('sobre'); });
        painel.addEventListener('drop', async e => {
            const s = e.target.closest('[data-solta]');
            if (!s) return;
            e.preventDefault(); e.stopPropagation();
            const dt = e.dataTransfer;
            try {
                const arq = window.wfArquivoDe ? await window.wfArquivoDe(dt) : dt.files[0];
                if (arq) usarArquivo(arq); else { erro = 'Não achei uma imagem aí. Use o link direto do GIF.'; desenhar(); }
            } catch (x) { erro = x.message || 'Não consegui usar essa imagem.'; desenhar(); }
        });
        painel.addEventListener('paste', e => {
            const d = e.clipboardData;
            if (modo !== 'imagem' || !d || ![...d.items].some(i => i.kind === 'file')) return;
            e.preventDefault();
            const arq = [...d.files][0];
            if (arq) usarArquivo(arq);
        });

        desenhar();
        // perto do cartão, sem sair da tela
        const r = el ? el.getBoundingClientRect() : { right: innerWidth / 2, top: 80, left: innerWidth / 2 };
        const w = painel.offsetWidth, h = painel.offsetHeight;
        let x = r.right + 10; if (x + w > innerWidth - 8) x = Math.max(8, r.left - w - 10);
        if (x < 8 || x + w > innerWidth - 8) x = Math.max(8, (innerWidth - w) / 2);
        painel.style.left = x + 'px';
        painel.style.top = Math.max(8, Math.min(innerHeight - h - 8, r.top)) + 'px';
        setTimeout(() => { addEventListener('keydown', esc, true); addEventListener('pointerdown', fora, true); }, 0);
    }

    /* ---- botão "Fundo do cartão" no topo de todo cartão (ao lado do de baixar), além do clique direito ---- */
    const ICO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M20.5 16l-5-5-8 8.5"/></svg>';
    function injetar() {
        document.querySelectorAll('.wf-item[data-bid] > .wf-topo').forEach(topo => {
            if (topo.querySelector('.wf-fc-btn')) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'mini-btn wf-fc-btn';
            btn.innerHTML = ICO;
            btn.title = 'Fundo do cartão (cor, transparência, imagem ou GIF)';
            btn.setAttribute('aria-label', 'Fundo do cartão');
            const ref = topo.querySelector('.wf-dl-btn') || topo.querySelector('.wf-fs-btn') || topo.querySelector('.mini-btn');
            if (ref && ref.parentNode) ref.parentNode.insertBefore(btn, ref); else topo.appendChild(btn);
        });
    }
    let agendado = false;
    const agendar = () => { if (agendado) return; agendado = true; requestAnimationFrame(() => { agendado = false; injetar(); }); };
    ['pointerdown', 'mousedown', 'touchstart'].forEach(t => document.addEventListener(t, e => {
        if (e.target.closest && e.target.closest('.wf-fc-btn')) e.stopPropagation(); // não começa a arrastar o cartão
    }, true));
    document.addEventListener('click', e => {
        const btn = e.target.closest && e.target.closest('.wf-fc-btn');
        if (!btn) return;
        e.preventDefault(); e.stopPropagation();
        const item = btn.closest('.wf-item[data-bid]'), q = item && item.closest('.wf-quadro');
        let c = null;
        try { c = q && window.Alpine ? window.Alpine.$data(q) : null; } catch (_) { c = null; }
        if (c && typeof c.fundoDoCartao === 'function') c.fundoDoCartao(item.dataset.sec, item.dataset.bid, item);
    }, true);
    const st = document.createElement('style');
    st.textContent = '.wf-fc-btn svg{width:.9rem;height:.9rem;display:block}';
    document.head.appendChild(st);
    const iniciar = () => { injetar(); new MutationObserver(agendar).observe(document.body, { childList: true, subtree: true }); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();

    window.WFFundoCartao = { css, abrir, fechar, limpo };
})();
