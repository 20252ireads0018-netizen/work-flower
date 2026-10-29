/* Work Flower · vídeo curto vertical (views/video-curto.blade.php).
   Tudo é função do tempo t (segundos): render(t) põe cada elemento no lugar certo, sem transições de CSS.
   Assim o mesmo quadro sai igual ao tocar e ao gravar. Baixar: cada quadro vira uma imagem SVG
   (foreignObject com o HTML e o CSS do palco, fontes embutidas), é desenhado num canvas e codificado em
   H.264 com WebCodecs; o mp4-muxer monta o .mp4. Sem WebCodecs, grava WebM com MediaRecorder. */
(function () {
    'use strict';

    const FPS = 30, W = 1080, H = 1920;
    const MUXER_URL = 'https://cdn.jsdelivr.net/npm/mp4-muxer@5.1.3/build/mp4-muxer.min.js';

    const palco = document.getElementById('palco'), escala = document.getElementById('escala'), moldura = document.getElementById('moldura');
    const cenas = [...palco.querySelectorAll('.cena')].map(el => ({ el, de: +el.dataset.de, ate: +el.dataset.ate, itens: [...el.querySelectorAll('[data-a]')], nums: [...el.querySelectorAll('[data-num]')] }));
    const DUR = Math.max(...cenas.map(c => c.ate));

    const clamp = x => Math.min(1, Math.max(0, x));
    const sai = x => 1 - Math.pow(1 - x, 3);                                        // easeOutCubic
    const volta = x => { const c = 1.9; return 1 + (c + 1) * Math.pow(x - 1, 3) + c * Math.pow(x - 1, 2); }; // easeOutBack
    const ENTRADAS = ['sobe', 'desce', 'esq', 'dir', 'zoom', 'pop', 'apaga'];

    const fmt = (v, f) => {
        if (f === 'pct') return Math.round(v) + '%';
        if (f === 'brl0') return 'R$ ' + Math.round(v).toLocaleString('pt-BR');
        return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    // barras de progresso no topo, uma por cena (como nos stories)
    const barras = document.getElementById('barras');
    const fios = cenas.map(() => { const i = document.createElement('i'), b = document.createElement('b'); i.appendChild(b); barras.appendChild(i); return b; });

    function animar(el, lt) {
        const a = el.dataset.a.split(' '), em = +el.dataset.em || 0, dur = +el.dataset.dur || .35;
        const p = clamp((lt - em) / dur), e = sai(p), dt = lt - em;
        let tx = +el.dataset.dx || 0, ty = +el.dataset.dy || 0, s = 1, r = +el.dataset.r || 0, op = 1;
        const entra = a.some(x => ENTRADAS.includes(x));
        if (entra && p <= 0) op = 0;
        for (const x of a) {
            if (x === 'sobe') { ty += (1 - e) * 110; op = Math.min(op, clamp(p * 2.5)); }
            else if (x === 'desce') { ty -= (1 - e) * 110; op = Math.min(op, clamp(p * 2.5)); }
            else if (x === 'esq') { tx -= (1 - e) * 340; op = Math.min(op, clamp(p * 2.5)); }
            else if (x === 'dir') { tx += (1 - e) * 340; op = Math.min(op, clamp(p * 2.5)); }
            else if (x === 'zoom') { s *= 1 + (1 - e) * 1.4; op = Math.min(op, clamp(p * 3)); }
            else if (x === 'pop') { s *= p <= 0 ? 0 : Math.max(0, volta(p)); }
            else if (x === 'apaga') { op = Math.min(op, e); }
            else if (x === 'gira') { r += lt * 22; }
            else if (x === 'flutua') { ty += Math.sin(lt * 3.2 + (+el.dataset.dx || 0)) * 14; r += Math.sin(lt * 2.1 + (+el.dataset.dy || 0)) * 2; }
            else if (x === 'pulsa' && dt > dur) { s *= 1 + .045 * Math.sin((dt - dur) * 9); }
            else if (x === 'sacode' && dt > 0 && dt < .6) { const k = 1 - dt / .6; tx += Math.sin(dt * 70) * 22 * k; r += Math.sin(dt * 55) * 3 * k; }
            else if (x === 'junta') { const j = sai(p); tx *= (1 - j); ty *= (1 - j); s *= 1 - .85 * j; r *= (1 - j); op = 1 - j * j; }
            else if (x === 'confete') {
                if (p <= 0) { op = 0; continue; }
                const ang = (+el.dataset.ang) * Math.PI / 180, d = +el.dataset.dist;
                tx = Math.cos(ang) * d * sai(clamp(dt / .45)); ty = Math.sin(ang) * d * sai(clamp(dt / .45)) + 900 * Math.pow(Math.max(0, dt - .25), 2);
                r += dt * 540; op = 1 - clamp((dt - .7) / .4);
            }
            else if (x === 'ponteiro') { el.setAttribute('transform', 'rotate(' + ((lt * (+el.dataset.vel || 360)) % 360).toFixed(1) + ')'); return; }
            else if (x === 'barra') { el.style[el.dataset.prop || 'width'] = ((+el.dataset.v || 100) * e).toFixed(2) + '%'; return; }
            else if (x === 'anel') { el.style.strokeDashoffset = (1 - (+el.dataset.v || 100) / 100 * e).toFixed(4); return; }
        }
        if (el.dataset.sai !== undefined) { const q = clamp((lt - (+el.dataset.sai)) / .12); op *= 1 - q; s *= 1 - .25 * q; }
        el.style.opacity = op.toFixed(3);
        el.style.transform = `translate(${tx.toFixed(1)}px, ${ty.toFixed(1)}px) scale(${s.toFixed(4)}) rotate(${r.toFixed(2)}deg)`;
    }

    function render(t) {
        cenas.forEach((c, i) => {
            fios[i].style.width = (clamp((t - c.de) / (c.ate - c.de)) * 100).toFixed(1) + '%';
            const on = t >= c.de && (t < c.ate || i === cenas.length - 1);
            c.el.style.display = on ? '' : 'none';
            if (!on) return;
            const lt = t - c.de;
            // corte seco com um "soco" de zoom no começo de cada cena
            c.el.style.transform = `scale(${(1 + .07 * (1 - sai(clamp(lt / .28)))).toFixed(4)})`;
            c.itens.forEach(el => animar(el, lt));
            c.nums.forEach(el => { const k = sai(clamp((lt - (+el.dataset.em || 0)) / (+el.dataset.dur || 1))); el.textContent = fmt(+el.dataset.num * k, el.dataset.fmt); });
        });
    }

    // ===== reprodução =====
    const btTocar = document.getElementById('tocar'), linha = document.getElementById('linha'), tempo = document.getElementById('tempo');
    const btBaixar = document.getElementById('baixar'), estado = document.getElementById('estado');
    linha.max = DUR;
    let t = 0, tocando = true, ultimo = performance.now(), gravando = false;

    const mostrarTempo = () => { tempo.textContent = t.toFixed(1).replace('.', ',') + ' / ' + DUR.toFixed(1).replace('.', ',') + ' s'; linha.value = t; };
    function laco(agora) {
        if (tocando && !gravando) { t += (agora - ultimo) / 1000; if (t >= DUR) t = 0; render(t); mostrarTempo(); }
        ultimo = agora;
        requestAnimationFrame(laco);
    }
    const setTocando = v => { tocando = v; btTocar.textContent = v ? '❚❚ Pausar' : '▶ Tocar'; };
    btTocar.addEventListener('click', () => setTocando(!tocando));
    document.getElementById('reiniciar').addEventListener('click', () => { t = 0; render(t); mostrarTempo(); setTocando(true); });
    linha.addEventListener('input', () => { t = +linha.value; setTocando(false); render(t); mostrarTempo(); });

    const ajustar = () => { escala.style.transform = `scale(${moldura.clientWidth / W})`; };
    new ResizeObserver(ajustar).observe(moldura);
    ajustar();
    render(0); mostrarTempo();
    requestAnimationFrame(laco);

    // ===== gravação =====
    const avisar = (msg, frac) => {
        estado.innerHTML = '';
        estado.append(msg);
        if (frac !== undefined) { const b = document.createElement('div'), s = document.createElement('span'); b.className = 'barra'; s.style.width = (frac * 100).toFixed(1) + '%'; b.appendChild(s); estado.appendChild(b); }
    };
    const carregar = src => new Promise((ok, falha) => { const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = () => falha(new Error('não carregou ' + src)); document.head.appendChild(s); });
    const paraDataUrl = blob => new Promise(ok => { const r = new FileReader(); r.onload = () => ok(r.result); r.readAsDataURL(blob); });

    // fontes do Google embutidas (dentro de uma imagem SVG o navegador não busca nada de fora); só o subconjunto latin
    let fontesCss = null;
    async function fontesEmbutidas() {
        if (fontesCss !== null) return fontesCss;
        try {
            const css = await (await fetch(document.getElementById('fontes-video').href)).text();
            const blocos = css.match(/\/\* latin \*\/\s*@font-face\s*\{[^}]*\}/g) || [];
            const prontos = await Promise.all(blocos.map(async b => {
                const url = (b.match(/url\(([^)]+)\)/) || [])[1];
                if (!url) return '';
                const dados = await paraDataUrl(await (await fetch(url)).blob());
                return b.replace(/\/\* latin \*\//, '').replace(url, dados);
            }));
            fontesCss = prontos.join('\n');
        } catch (e) { fontesCss = ''; }
        return fontesCss;
    }

    const ser = new XMLSerializer();
    async function quadro(ctx, tq, estilo) {
        render(tq);
        // só o que está visível entra no quadro (as cenas escondidas deixariam cada imagem bem maior)
        const html = [...palco.children].filter(el => el.style.display !== 'none').map(el => ser.serializeToString(el)).join('');
        const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}"><foreignObject x="0" y="0" width="${W}" height="${H}">`
            + `<div xmlns="http://www.w3.org/1999/xhtml"><style>${estilo}</style><div class="palco" style="${palco.getAttribute('style') || ''}">${html}</div></div></foreignObject></svg>`;
        const img = new Image();
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
        await img.decode();
        ctx.drawImage(img, 0, 0, W, H);
    }

    async function codecH264() {
        if (!('VideoEncoder' in window)) return null;
        for (const codec of ['avc1.640028', 'avc1.4d0028', 'avc1.42e028']) {
            const cfg = { codec, width: W, height: H, bitrate: 9_000_000, framerate: FPS, avc: { format: 'avc' } };
            try { if ((await VideoEncoder.isConfigSupported(cfg)).supported) return cfg; } catch (e) { /* tenta o próximo */ }
        }
        return null;
    }

    function baixarArquivo(blob, nome) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = nome;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 60000);
    }

    async function gravar() {
        gravando = true; btBaixar.disabled = true; btTocar.disabled = true;
        const tAntes = t;
        try {
            avisar('Preparando as fontes…');
            await document.fonts.ready;
            const estilo = (await fontesEmbutidas()) + document.getElementById('css-video').textContent;
            const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = H;
            const ctx = canvas.getContext('2d');
            const total = Math.round(DUR * FPS);
            await quadro(ctx, 0, estilo); // aquece: o primeiro quadro carrega as fontes embutidas
            const cfg = await codecH264();

            if (cfg) {
                if (!window.Mp4Muxer) { avisar('Carregando o gravador de MP4…'); await carregar(MUXER_URL); }
                const M = window.Mp4Muxer;
                const muxer = new M.Muxer({ target: new M.ArrayBufferTarget(), video: { codec: 'avc', width: W, height: H }, fastStart: 'in-memory' });
                let erro = null;
                const enc = new VideoEncoder({ output: (ch, meta) => muxer.addVideoChunk(ch, meta), error: e => { erro = e; } });
                enc.configure(cfg);
                for (let i = 0; i < total; i++) {
                    if (erro) throw erro;
                    await quadro(ctx, i / FPS, estilo);
                    const vf = new VideoFrame(canvas, { timestamp: Math.round(i * 1e6 / FPS), duration: Math.round(1e6 / FPS) });
                    enc.encode(vf, { keyFrame: i % (FPS * 2) === 0 });
                    vf.close();
                    while (enc.encodeQueueSize > 6) await new Promise(r => setTimeout(r, 4));
                    if (i % 6 === 0) avisar(`Gravando quadro ${i + 1} de ${total}…`, i / total);
                }
                await enc.flush();
                if (erro) throw erro;
                muxer.finalize();
                enc.close();
                baixarArquivo(new Blob([muxer.target.buffer], { type: 'video/mp4' }), 'work-flower-video-curto.mp4');
                avisar('Pronto: work-flower-video-curto.mp4 (1080×1920, 30 fps, sem áudio).');
            } else {
                // sem WebCodecs: grava em tempo real com MediaRecorder (WebM)
                const tipo = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'].find(x => window.MediaRecorder && MediaRecorder.isTypeSupported(x));
                if (!tipo) throw new Error('este navegador não grava vídeo. Use o Chrome ou o Edge.');
                const fluxo = canvas.captureStream(0), trilha = fluxo.getVideoTracks()[0];
                const rec = new MediaRecorder(fluxo, { mimeType: tipo, videoBitsPerSecond: 9_000_000 });
                const partes = []; rec.ondataavailable = e => e.data.size && partes.push(e.data);
                const fim = new Promise(ok => { rec.onstop = ok; });
                rec.start();
                const t0 = performance.now();
                for (let i = 0; i < total; i++) {
                    await quadro(ctx, i / FPS, estilo);
                    trilha.requestFrame();
                    const espera = t0 + (i + 1) * 1000 / FPS - performance.now();
                    if (espera > 0) await new Promise(r => setTimeout(r, espera));
                    if (i % 6 === 0) avisar(`Gravando (WebM) quadro ${i + 1} de ${total}…`, i / total);
                }
                rec.stop(); await fim;
                baixarArquivo(new Blob(partes, { type: 'video/webm' }), 'work-flower-video-curto.webm');
                avisar('Pronto: work-flower-video-curto.webm (este navegador não grava MP4).');
            }
        } catch (e) {
            avisar('Não consegui gravar: ' + (e && e.message || e));
        } finally {
            gravando = false; btBaixar.disabled = false; btTocar.disabled = false;
            t = tAntes; render(t); mostrarTempo();
        }
    }
    btBaixar.addEventListener('click', gravar);
})();
