/* Work Flower · Estúdio de música (cartão "Estúdio de música"; aberto por quadro.js, abrirEstudioMusica).
   window.WFEstudioMusica.abrir({ blocoId, titulo, projeto, aoSalvar }) abre o estúdio em tela cheia.
     projeto  = o que foi salvo antes (ver novoProjeto)
     aoSalvar = async ({ projeto, dur }) => {}   (o projeto vai para o cartão; dur em segundos)
   window.WFEstudioMusica.previa(blocoId, projeto) toca ou para a música do cartão sem abrir o estúdio.

   No estilo do FL Studio: Channel rack (step sequencer), Piano roll, Playlist (padrões em compassos) e um
   mixer simples (volume e pan por canal; reverb, eco e filtro no master). Os instrumentos são sintetizados
   pelo Web Audio (bateria, baixo, 808, lead, pluck, pad e piano elétrico), então nada depende de arquivos.
   Tudo é nota: o passo aceso no rack é uma nota na altura base do canal; o piano roll edita as mesmas notas.
   A exportação renderiza a música num OfflineAudioContext e baixa um WAV. Não depende de nada. */
(function () {
    'use strict';
    if (window.WFEstudioMusica) return;

    const MAX_PASSOS = 60;
    const FAIXAS = 8;
    const NOTA_MIN = 24, NOTA_MAX = 95; // C1..B6 no piano roll
    const CORES = ['#f97316', '#22c55e', '#3b82f6', '#a855f7', '#ec4899', '#eab308', '#14b8a6', '#ef4444'];
    const NOMES_NOTA = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
    // teclado do computador como piano (linha de baixo e a de cima, como no FL)
    const TECLAS = { z: 0, s: 1, x: 2, d: 3, c: 4, v: 5, g: 6, b: 7, h: 8, n: 9, j: 10, m: 11, q: 12, 2: 13, w: 14, 3: 15, e: 16, r: 17, 5: 18, t: 19, 6: 20, y: 21, 7: 22, u: 23 };

    const INSTRUMENTOS = {
        kick:  { nome: 'Bumbo (kick)', bat: true, base: 60 },
        snare: { nome: 'Caixa (snare)', bat: true, base: 60 },
        clap:  { nome: 'Palma (clap)', bat: true, base: 60 },
        hat:   { nome: 'Chimbal fechado', bat: true, base: 60 },
        ohat:  { nome: 'Chimbal aberto', bat: true, base: 60 },
        tom:   { nome: 'Tom', bat: true, base: 60 },
        perc:  { nome: 'Percussão (rim)', bat: true, base: 60 },
        baixo: { nome: 'Baixo', base: 36 },
        b808:  { nome: '808', base: 36 },
        lead:  { nome: 'Lead', base: 72 },
        pluck: { nome: 'Pluck', base: 72 },
        pad:   { nome: 'Pad', base: 60 },
        piano: { nome: 'Piano elétrico', base: 60 },
    };

    const clamp = (v, a, b) => Math.min(Math.max(v, a), b);
    const uid = () => Math.random().toString(36).slice(2, 10);
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const nomeNota = m => NOMES_NOTA[m % 12] + (Math.floor(m / 12) - 1);
    const freq = m => 440 * Math.pow(2, (m - 69) / 12);
    const preta = m => [1, 3, 6, 8, 10].includes(m % 12);

    /* ---------- projeto ---------- */
    function novoProjeto() {
        const canais = [
            ['Kick', 'kick'], ['Clap', 'clap'], ['Hat', 'hat'], ['Open hat', 'ohat'], ['Baixo', 'baixo'], ['Pluck', 'pluck'],
        ].map(([nome, inst]) => ({ id: uid(), nome, inst, vol: .8, pan: 0, mudo: false, solo: false }));
        const [k, cl, h, oh, bx, pl] = canais.map(c => c.id);
        const nota = (s, n, d = 1, v = .8) => ({ s, n, d, v });
        const notas = {
            [k]: [0, 4, 8, 12].map(s => nota(s, 60)),
            [cl]: [4, 12].map(s => nota(s, 60)),
            [h]: [2, 6, 10, 14].map(s => nota(s, 60, 1, .6)),
            [oh]: [15].map(s => nota(s, 60, 1, .5)),
            [bx]: [nota(0, 36, 2), nota(3, 36, 1), nota(6, 43, 2), nota(10, 41, 2), nota(14, 39, 2)],
            [pl]: [nota(0, 72, 2), nota(3, 75, 1), nota(6, 79, 2), nota(10, 77, 2), nota(12, 75, 1), nota(14, 74, 2)],
        };
        const p1 = { id: uid(), nome: 'Padrão 1', cor: CORES[0], notas };
        return {
            v: 1, bpm: 124, swing: 0, passos: 16, volume: .8, compassos: 32,
            efeitos: { reverb: .18, eco: .12, filtro: 1 },
            canais, padroes: [p1],
            playlist: [0, 1, 2, 3].map(c => ({ id: uid(), f: 0, c, p: p1.id })),
        };
    }
    function normalizar(p) {
        const n = novoProjeto();
        p = p && typeof p === 'object' ? JSON.parse(JSON.stringify(p)) : n;
        p.bpm = clamp(+p.bpm || 124, 40, 220);
        p.swing = clamp(+p.swing || 0, 0, .6);
        p.passos = [16, 32, 64].includes(+p.passos) ? +p.passos : 16;
        p.volume = clamp(p.volume ?? .8, 0, 1);
        p.compassos = clamp(+p.compassos || 32, 8, 128);
        p.efeitos = { reverb: .18, eco: .12, filtro: 1, ...(p.efeitos || {}) };
        if (!Array.isArray(p.canais)) p.canais = [];
        p.canais = p.canais.filter(c => c && INSTRUMENTOS[c.inst]);
        if (!Array.isArray(p.padroes) || !p.padroes.length) p.padroes = [{ id: uid(), nome: 'Padrão 1', cor: CORES[0], notas: {} }];
        p.padroes.forEach(x => { if (!x.notas || typeof x.notas !== 'object') x.notas = {}; });
        if (!Array.isArray(p.playlist)) p.playlist = [];
        p.playlist = p.playlist.filter(c => c && p.padroes.some(x => x.id === c.p));
        return p;
    }
    const padrao = (p, id) => p.padroes.find(x => x.id === id) || p.padroes[0];
    const compassosPadrao = p => p.passos / 16;
    /** Fim da música em passos (último clipe da playlist). */
    const fimMusica = p => p.playlist.reduce((m, c) => Math.max(m, (c.c + compassosPadrao(p)) * 16), 0);
    const audivel = (p, c) => (p.canais.some(x => x.solo) ? c.solo : !c.mudo);
    const durPasso = p => 60 / p.bpm / 4;

    /* ---------- som (Web Audio) ---------- */
    const ruidos = new WeakMap();
    function ruido(ctx) {
        let b = ruidos.get(ctx);
        if (!b) {
            b = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
            const d = b.getChannelData(0);
            for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
            ruidos.set(ctx, b);
        }
        return b;
    }
    function impulso(ctx, seg) {
        const n = Math.floor(ctx.sampleRate * seg), b = ctx.createBuffer(2, n, ctx.sampleRate);
        for (let c = 0; c < 2; c++) {
            const d = b.getChannelData(c);
            for (let i = 0; i < n; i++) d[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / n, 2.6);
        }
        return b;
    }
    const freqFiltro = f => 180 * Math.pow(20000 / 180, clamp(f, 0, 1));

    /** Mixer: canal → volume → pan → bus → (seco + reverb + eco) → filtro → master → compressor → saída. */
    function criarGrafo(ctx) {
        const master = ctx.createGain(), comp = ctx.createDynamicsCompressor(), filtro = ctx.createBiquadFilter(), bus = ctx.createGain();
        comp.threshold.value = -8; comp.ratio.value = 4; comp.attack.value = .005; comp.release.value = .15;
        filtro.type = 'lowpass'; filtro.Q.value = .7;
        bus.connect(filtro); filtro.connect(master); master.connect(comp); comp.connect(ctx.destination);
        const rev = ctx.createConvolver(), revG = ctx.createGain();
        rev.buffer = impulso(ctx, 2.4);
        bus.connect(revG); revG.connect(rev); rev.connect(filtro);
        const eco = ctx.createDelay(2), fb = ctx.createGain(), ecoG = ctx.createGain(), ecoF = ctx.createBiquadFilter();
        ecoF.type = 'lowpass'; ecoF.frequency.value = 3500; fb.gain.value = .38;
        bus.connect(ecoG); ecoG.connect(eco); eco.connect(ecoF); ecoF.connect(fb); fb.connect(eco); ecoF.connect(filtro);
        const canais = new Map();
        const g = {
            ctx,
            canal(p, c) {
                let n = canais.get(c.id);
                if (!n) {
                    const vol = ctx.createGain(), pan = ctx.createStereoPanner();
                    vol.connect(pan); pan.connect(bus);
                    n = { vol, pan };
                    canais.set(c.id, n);
                }
                n.vol.gain.value = audivel(p, c) ? c.vol : 0;
                n.pan.pan.value = clamp(c.pan, -1, 1);
                return n.vol;
            },
            atualizar(p) {
                master.gain.value = p.volume;
                filtro.frequency.value = freqFiltro(p.efeitos.filtro);
                revG.gain.value = p.efeitos.reverb * 1.2;
                ecoG.gain.value = p.efeitos.eco;
                eco.delayTime.value = Math.min(1.9, durPasso(p) * 3); // colcheia pontuada
                p.canais.forEach(c => g.canal(p, c));
            },
        };
        return g;
    }

    /** Envelope ADSR num ganho; devolve quando o som acaba. */
    function envelope(param, t, a, d, s, r, dur, vel) {
        param.setValueAtTime(0, t);
        param.linearRampToValueAtTime(vel, t + a);
        param.setTargetAtTime(vel * s, t + a, Math.max(d, .001) / 3);
        const fim = t + Math.max(dur, a);
        param.setTargetAtTime(0, fim, Math.max(r, .005) / 3);
        return fim + r * 2.5 + .05;
    }
    function osc(ctx, tipo, f, t, fim, destino, detune = 0) {
        const o = ctx.createOscillator();
        o.type = tipo; o.frequency.setValueAtTime(f, t); o.detune.value = detune;
        o.connect(destino); o.start(t); o.stop(fim);
        return o;
    }
    function fonteRuido(ctx, t, fim, destino) {
        const s = ctx.createBufferSource();
        s.buffer = ruido(ctx); s.loop = true;
        s.connect(destino); s.start(t, Math.random() * .5); s.stop(fim);
        return s;
    }
    function filtroDe(ctx, tipo, f, q = .7) {
        const b = ctx.createBiquadFilter();
        b.type = tipo; b.frequency.value = f; b.Q.value = q;
        return b;
    }

    /** Toca uma nota do instrumento em t (segundos do contexto). */
    function tocarNota(ctx, destino, inst, t, midi, dur, vel) {
        const g = ctx.createGain();
        g.connect(destino);
        const fator = Math.pow(2, (midi - 60) / 12); // bateria: a altura base é C4 (60)
        const v = clamp(vel, 0, 1);
        switch (inst) {
            case 'kick': {
                const o = osc(ctx, 'sine', 150 * fator, t, t + .6, g);
                o.frequency.exponentialRampToValueAtTime(42 * fator, t + .12);
                g.gain.setValueAtTime(v * 1.1, t); g.gain.exponentialRampToValueAtTime(.001, t + .5);
                const cl = ctx.createGain(); cl.connect(destino);
                osc(ctx, 'square', 1200 * fator, t, t + .02, cl);
                cl.gain.setValueAtTime(v * .12, t); cl.gain.exponentialRampToValueAtTime(.001, t + .015);
                break;
            }
            case 'snare': {
                const hp = filtroDe(ctx, 'highpass', 1400 * fator); hp.connect(g);
                fonteRuido(ctx, t, t + .3, hp);
                g.gain.setValueAtTime(v * .7, t); g.gain.exponentialRampToValueAtTime(.001, t + .2);
                const tg = ctx.createGain(); tg.connect(destino);
                const o = osc(ctx, 'triangle', 200 * fator, t, t + .15, tg);
                o.frequency.exponentialRampToValueAtTime(150 * fator, t + .1);
                tg.gain.setValueAtTime(v * .6, t); tg.gain.exponentialRampToValueAtTime(.001, t + .12);
                break;
            }
            case 'clap': {
                const bp = filtroDe(ctx, 'bandpass', 1100 * fator, 1.2); bp.connect(g);
                fonteRuido(ctx, t, t + .35, bp);
                g.gain.setValueAtTime(0, t);
                [0, .012, .024].forEach(o => { g.gain.setValueAtTime(v * 1.4, t + o); g.gain.exponentialRampToValueAtTime(.05, t + o + .01); });
                g.gain.setValueAtTime(v * 1.1, t + .035); g.gain.exponentialRampToValueAtTime(.001, t + .3);
                break;
            }
            case 'hat':
            case 'ohat': {
                const hp = filtroDe(ctx, 'highpass', 7000 * fator); hp.connect(g);
                const fim = inst === 'hat' ? .06 : .35;
                fonteRuido(ctx, t, t + fim + .05, hp);
                g.gain.setValueAtTime(v * .45, t); g.gain.exponentialRampToValueAtTime(.001, t + fim);
                break;
            }
            case 'tom': {
                const o = osc(ctx, 'sine', 180 * fator, t, t + .5, g);
                o.frequency.exponentialRampToValueAtTime(80 * fator, t + .3);
                g.gain.setValueAtTime(v * .9, t); g.gain.exponentialRampToValueAtTime(.001, t + .4);
                break;
            }
            case 'perc': {
                const bp = filtroDe(ctx, 'bandpass', 1700 * fator, 3); bp.connect(g);
                osc(ctx, 'square', 820 * fator, t, t + .06, bp);
                g.gain.setValueAtTime(v * .9, t); g.gain.exponentialRampToValueAtTime(.001, t + .045);
                break;
            }
            case 'baixo': {
                const f = freq(midi), lp = filtroDe(ctx, 'lowpass', 300, 4); lp.connect(g);
                const fim = envelope(g.gain, t, .005, .25, .55, .08, dur, v * .55);
                lp.frequency.setValueAtTime(1600, t); lp.frequency.exponentialRampToValueAtTime(320, t + .25);
                osc(ctx, 'sawtooth', f, t, fim, lp);
                osc(ctx, 'sine', f / 2, t, fim, lp);
                break;
            }
            case 'b808': {
                const f = freq(midi);
                const sh = ctx.createWaveShaper(), curva = new Float32Array(256);
                for (let i = 0; i < 256; i++) { const x = i / 128 - 1; curva[i] = Math.tanh(x * 2.2); }
                sh.curve = curva; sh.connect(g);
                const fim = envelope(g.gain, t, .003, Math.max(dur, .4), .35, .25, dur, v * .7);
                const o = osc(ctx, 'sine', f * 1.6, t, fim, sh);
                o.frequency.exponentialRampToValueAtTime(f, t + .06);
                break;
            }
            case 'lead': {
                const f = freq(midi), lp = filtroDe(ctx, 'lowpass', 3200, 2); lp.connect(g);
                const fim = envelope(g.gain, t, .01, .12, .7, .15, dur, v * .2);
                osc(ctx, 'square', f, t, fim, lp, -8);
                osc(ctx, 'sawtooth', f, t, fim, lp, 8);
                break;
            }
            case 'pluck': {
                const f = freq(midi), lp = filtroDe(ctx, 'lowpass', 400, 3); lp.connect(g);
                const fim = envelope(g.gain, t, .003, .3, 0, .2, Math.min(dur, .3), v * .35);
                lp.frequency.setValueAtTime(5000, t); lp.frequency.exponentialRampToValueAtTime(500, t + .25);
                osc(ctx, 'sawtooth', f, t, fim, lp);
                osc(ctx, 'triangle', f * 2, t, fim, lp);
                break;
            }
            case 'pad': {
                const f = freq(midi), lp = filtroDe(ctx, 'lowpass', 1600, .8); lp.connect(g);
                const fim = envelope(g.gain, t, .35, .5, .75, .7, dur, v * .14);
                [-12, 0, 12].forEach(d => osc(ctx, 'sawtooth', f, t, fim, lp, d));
                break;
            }
            case 'piano': {
                const f = freq(midi);
                const fim = envelope(g.gain, t, .004, 1.1, 0, .3, Math.min(dur, 1.2), v * .35);
                osc(ctx, 'sine', f, t, fim, g);
                const h = ctx.createGain(); h.gain.value = .25; h.connect(g);
                osc(ctx, 'sine', f * 2, t, fim, h);
                const tr = ctx.createGain(); tr.gain.value = .12; tr.connect(g);
                osc(ctx, 'triangle', f * 3, t, fim, tr);
                break;
            }
        }
    }

    /** Toca o passo `passo` (padrão: dentro do padrão; música: posição na playlist). */
    function tocarPasso(g, p, modo, padraoId, passo, t) {
        const sp = durPasso(p);
        const disparar = (pad, local) => {
            const tt = t + (local % 2 ? p.swing * sp * .5 : 0);
            for (const c of p.canais) {
                if (!audivel(p, c)) continue;
                const notas = pad.notas[c.id];
                if (!notas) continue;
                for (const n of notas) if (n.s === local) tocarNota(g.ctx, g.canal(p, c), c.inst, tt, n.n, n.d * sp, n.v ?? .8);
            }
        };
        if (modo === 'padrao') { disparar(padrao(p, padraoId), passo); return; }
        for (const c of p.playlist) {
            const ini = c.c * 16;
            if (passo >= ini && passo < ini + p.passos) disparar(padrao(p, c.p), passo - ini);
        }
    }

    /* ---------- tocador (sequenciador com agendamento adiantado) ---------- */
    let ctx = null, grafo = null;
    const A = { timer: 0, p: null, modo: 'padrao', padraoId: null, passo: 0, prox: 0, fila: [], dono: null };
    function contexto() {
        if (!ctx) { ctx = new (window.AudioContext || window.webkitAudioContext)(); grafo = criarGrafo(ctx); }
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }
    function tamanhoLaco() {
        if (A.modo === 'padrao') return A.p.passos;
        return fimMusica(A.p) || A.p.passos;
    }
    function agendar() {
        const sp = durPasso(A.p);
        while (A.prox < ctx.currentTime + .12) {
            tocarPasso(grafo, A.p, A.modo, A.padraoId, A.passo, A.prox);
            A.fila.push({ passo: A.passo, t: A.prox });
            A.prox += sp;
            A.passo = (A.passo + 1) % tamanhoLaco();
        }
    }
    function iniciar(p, modo, padraoId, dono, desde = 0) {
        parar();
        contexto();
        Object.assign(A, { p, modo, padraoId, dono, passo: desde, prox: ctx.currentTime + .06, fila: [] });
        grafo.atualizar(p);
        agendar();
        A.timer = setInterval(agendar, 25);
    }
    function parar() {
        if (A.timer) clearInterval(A.timer);
        A.timer = 0; A.fila = []; A.dono = null;
    }
    /** Passo que está soando agora (ou -1). */
    function passoAtual() {
        if (!A.timer || !ctx) return -1;
        const agora = ctx.currentTime;
        while (A.fila.length > 1 && A.fila[1].t <= agora) A.fila.shift();
        return A.fila.length && A.fila[0].t <= agora ? A.fila[0].passo : -1;
    }
    function previaNota(p, c, midi, vel = .8) {
        contexto();
        grafo.atualizar(p);
        tocarNota(ctx, grafo.canal(p, { ...c, mudo: false, solo: false }), c.inst, ctx.currentTime + .01, midi, durPasso(p) * 2, vel);
    }

    /* ---------- exportação (WAV) ---------- */
    async function renderizar(p, modo, padraoId) {
        const musica = modo === 'musica' && p.playlist.length;
        const total = musica ? fimMusica(p) : p.passos * 4;
        const sr = 44100, dur = total * durPasso(p) + 3;
        const off = new OfflineAudioContext(2, Math.ceil(dur * sr), sr);
        const g = criarGrafo(off);
        g.atualizar(p);
        for (let i = 0; i < total; i++) tocarPasso(g, p, musica ? 'musica' : 'padrao', padraoId, musica ? i : i % p.passos, .05 + i * durPasso(p));
        return { buf: await off.startRendering(), seg: total * durPasso(p) };
    }
    function wav(buf) {
        const n = buf.length, ch = buf.numberOfChannels, dados = new DataView(new ArrayBuffer(44 + n * ch * 2));
        const str = (o, s) => [...s].forEach((c, i) => dados.setUint8(o + i, c.charCodeAt(0)));
        str(0, 'RIFF'); dados.setUint32(4, 36 + n * ch * 2, true); str(8, 'WAVE'); str(12, 'fmt ');
        dados.setUint32(16, 16, true); dados.setUint16(20, 1, true); dados.setUint16(22, ch, true);
        dados.setUint32(24, buf.sampleRate, true); dados.setUint32(28, buf.sampleRate * ch * 2, true);
        dados.setUint16(32, ch * 2, true); dados.setUint16(34, 16, true); str(36, 'data'); dados.setUint32(40, n * ch * 2, true);
        const canais = [...Array(ch)].map((_, i) => buf.getChannelData(i));
        let o = 44;
        for (let i = 0; i < n; i++) for (let c = 0; c < ch; c++) { const s = clamp(canais[c][i], -1, 1); dados.setInt16(o, s < 0 ? s * 0x8000 : s * 0x7fff, true); o += 2; }
        return new Blob([dados], { type: 'audio/wav' });
    }

    /* ---------- tela ---------- */
    const CSS = `
    .wfm{position:fixed;inset:0;z-index:90;display:grid;grid-template-rows:auto 1fr;grid-template-columns:230px 1fr;
         background:#22272e;color:#e5e7eb;font:13px/1.35 system-ui,sans-serif;user-select:none;-webkit-user-select:none}
    .wfm[hidden]{display:none!important}
    .wfm *{box-sizing:border-box}
    .wfm button{font:inherit;color:inherit;background:none;border:0;cursor:pointer}
    .wfm input,.wfm select{font:inherit;color:#e5e7eb;background:#15181d;border:1px solid #39404b;border-radius:6px;padding:3px 6px}
    .wfm input[type=range]{padding:0;accent-color:var(--prim,#f97316)}
    .wfm-topo{grid-column:1/-1;display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:6px 10px;background:#2d333b;border-bottom:1px solid #15181d}
    .wfm-titulo{font-weight:600;margin-right:4px;max-width:20ch;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfm-b{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:7px;border:1px solid #444c56!important;background:#373e47!important;white-space:nowrap}
    .wfm-b:hover{border-color:var(--prim,#f97316)!important}
    .wfm-b.on,.wfm-b.prim{background:var(--prim,#f97316)!important;border-color:var(--prim,#f97316)!important;color:var(--on-prim,#fff)}
    .wfm-b:disabled{opacity:.45;cursor:default}
    .wfm-sep{width:1px;height:22px;background:#444c56;margin:0 3px}
    .wfm-lcd{font:600 14px ui-monospace,Consolas,monospace;background:#0d1014;color:#7ee787;border:1px solid #39404b;border-radius:6px;padding:4px 8px;min-width:6.5em;text-align:center}
    .wfm-campo{display:inline-flex;align-items:center;gap:4px;color:#adbac7;font-size:12px}
    .wfm-esq{overflow:auto;background:#2d333b;border-right:1px solid #15181d}
    .wfm-sec{padding:10px;border-bottom:1px solid #15181d}
    .wfm-sec h3{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#909dab;margin:0 0 8px;display:flex;justify-content:space-between;gap:6px}
    .wfm-pad{display:flex;align-items:center;gap:7px;width:100%;padding:5px 7px;border-radius:7px;text-align:left}
    .wfm-pad:hover{background:#373e47}.wfm-pad.sel{background:#444c56}
    .wfm-pad i{width:12px;height:12px;border-radius:3px;flex:none}
    .wfm-acoes{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
    .wfm-acoes .wfm-b{padding:3px 7px;font-size:12px}
    .wfm-fx{display:grid;grid-template-columns:70px 1fr;gap:6px 8px;align-items:center;font-size:12px;color:#adbac7}
    .wfm-ajuda{font-size:12px;color:#909dab;line-height:1.5}
    .wfm-ajuda b{color:#cdd9e5;font-weight:600}
    .wfm-centro{display:flex;flex-direction:column;min-width:0;min-height:0}
    .wfm-rack{flex:none;max-height:46%;overflow:auto;background:#2a2f36;border-bottom:2px solid #15181d;padding:6px 8px}
    .wfm-rack-topo{display:flex;align-items:center;gap:8px;margin-bottom:6px;color:#909dab;font-size:12px}
    .wfm-canal{display:flex;align-items:center;gap:6px;padding:2px 0}
    .wfm-led{width:14px;height:14px;border-radius:50%;background:#7ee787!important;box-shadow:0 0 6px #7ee78788;flex:none}
    .wfm-led.off{background:#4b5563!important;box-shadow:none}
    .wfm-solo{font-size:10px;font-weight:700;width:18px;height:18px;border-radius:4px;border:1px solid #444c56!important;color:#909dab!important;flex:none}
    .wfm-solo.on{background:#eab308!important;color:#111!important;border-color:#eab308!important}
    .wfm-nome{width:110px;flex:none;padding:5px 8px;border-radius:6px;background:#444c56!important;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .wfm-canal.sel .wfm-nome{background:var(--prim,#f97316)!important;color:var(--on-prim,#fff)}
    .wfm-canal select{width:104px;flex:none;font-size:11px;padding:2px 3px}
    .wfm-mini{width:56px;flex:none}
    .wfm-passos{display:flex;gap:2px;flex:none}
    .wfm-passo{width:22px;height:26px;border-radius:4px;background:#4b5260!important;position:relative;flex:none}
    .wfm-passo.g2{background:#5c4848!important}
    .wfm-passo.on{background:var(--prim,#f97316)!important;box-shadow:inset 0 -3px 0 rgba(0,0,0,.25)}
    .wfm-passo.on.ac{filter:brightness(1.25)}
    .wfm-passo.nota::after{content:"";position:absolute;left:50%;top:4px;width:4px;height:4px;margin-left:-2px;border-radius:50%;background:#fff;opacity:.85}
    .wfm-passo.agora{outline:2px solid #7ee787;outline-offset:-2px}
    .wfm-passo+.wfm-passo:nth-child(4n+1){margin-left:5px}
    .wfm-x{width:22px;height:22px;border-radius:5px;color:#909dab!important;flex:none}
    .wfm-x:hover{background:#7f1d1d!important;color:#fff!important}
    .wfm-abas{display:flex;align-items:center;gap:4px;padding:5px 8px;background:#2d333b;border-bottom:1px solid #15181d;flex:none;flex-wrap:wrap}
    .wfm-painel{flex:1;min-height:0;position:relative;background:#1c2128}
    .wfm-rolo{position:absolute;inset:0;overflow:auto}
    .wfm-rolo canvas{display:block;touch-action:none}
    .wfm-aviso{position:absolute;left:50%;bottom:18px;transform:translateX(-50%);background:#0d1014;border:1px solid #444c56;padding:8px 14px;border-radius:9px;
         box-shadow:0 8px 30px rgba(0,0,0,.4);max-width:min(90vw,560px);text-align:center;z-index:5;pointer-events:none;transition:opacity .2s}
    .wfm-aviso[hidden]{display:block!important;opacity:0}
    @media (max-width:820px){.wfm{grid-template-columns:1fr;grid-template-rows:auto auto 1fr}.wfm-esq{max-height:30vh;border-right:0;border-bottom:1px solid #15181d}
        .wfm-esq .wfm-sec:last-child{display:none}.wfm-canal select,.wfm-mini{display:none}.wfm-nome{width:78px}}
    `;

    let el = null, S = null, rafId = 0;

    function montar() {
        const st = document.createElement('style');
        st.textContent = CSS;
        document.documentElement.appendChild(st);
        el = document.createElement('div');
        el.className = 'wfm';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Estúdio de música');
        el.innerHTML = `
        <div class="wfm-topo">
            <span class="wfm-titulo" data-titulo></span>
            <button type="button" class="wfm-b prim" data-acao="tocar" title="Tocar / parar (espaço)">▶ Tocar</button>
            <span class="wfm-lcd" data-lcd>1.1.1</span>
            <span class="wfm-sep"></span>
            <button type="button" class="wfm-b" data-modo="padrao" title="Tocar só o padrão escolhido, em laço">Padrão</button>
            <button type="button" class="wfm-b" data-modo="musica" title="Tocar a playlist inteira">Música</button>
            <span class="wfm-sep"></span>
            <label class="wfm-campo">BPM <input type="number" min="40" max="220" step="1" data-bpm style="width:62px"></label>
            <label class="wfm-campo">Swing <input type="range" min="0" max="0.6" step="0.02" data-swing style="width:70px"></label>
            <label class="wfm-campo">Passos <select data-npassos><option>16</option><option>32</option><option>64</option></select></label>
            <label class="wfm-campo">Master <input type="range" min="0" max="1" step="0.01" data-volume style="width:70px"></label>
            <span style="flex:1"></span>
            <button type="button" class="wfm-b" data-acao="desfazer" title="Desfazer (Ctrl+Z)">↶</button>
            <button type="button" class="wfm-b" data-acao="refazer" title="Refazer (Ctrl+Y)">↷</button>
            <button type="button" class="wfm-b" data-acao="exportar" title="Baixar a música em WAV">⬇ Exportar WAV</button>
            <button type="button" class="wfm-b prim" data-acao="salvar" title="Salvar no cartão (Ctrl+S)">Salvar</button>
            <button type="button" class="wfm-b" data-acao="fechar" title="Fechar (Esc)">✕</button>
        </div>
        <aside class="wfm-esq">
            <div class="wfm-sec">
                <h3>Padrões</h3>
                <div data-padroes></div>
                <div class="wfm-acoes">
                    <button type="button" class="wfm-b" data-acao="novoPadrao">+ Novo</button>
                    <button type="button" class="wfm-b" data-acao="duplicarPadrao">⧉ Duplicar</button>
                    <button type="button" class="wfm-b" data-acao="renomearPadrao">✎</button>
                    <button type="button" class="wfm-b" data-acao="apagarPadrao" title="Apagar o padrão">🗑</button>
                </div>
            </div>
            <div class="wfm-sec">
                <h3>Master</h3>
                <div class="wfm-fx">
                    <span>Reverb</span><input type="range" min="0" max="1" step="0.01" data-fx="reverb">
                    <span>Eco</span><input type="range" min="0" max="0.8" step="0.01" data-fx="eco">
                    <span>Filtro</span><input type="range" min="0" max="1" step="0.01" data-fx="filtro">
                </div>
            </div>
            <div class="wfm-sec wfm-ajuda">
                <h3>Como usar</h3>
                <b>Channel rack:</b> clique nos passos para acender as batidas (Shift+clique dá ênfase). Clique no nome do canal para editar no piano roll; duplo clique renomeia.<br>
                <b>Piano roll:</b> clique para pôr uma nota e arraste para alongar; arraste a nota para mover; toque nela (ou botão direito) para apagar.<br>
                <b>Playlist:</b> clique num compasso vazio para pôr o padrão escolhido; arraste para mover; duplo clique ou botão direito apaga.<br>
                <b>Teclado:</b> espaço toca/para; Z S X D C V… tocam notas no canal escolhido.
            </div>
        </aside>
        <main class="wfm-centro">
            <section class="wfm-rack" aria-label="Channel rack">
                <div class="wfm-rack-topo">
                    <b style="color:#cdd9e5">Channel rack</b><span data-rack-padrao></span>
                    <span style="flex:1"></span>
                    <label class="wfm-campo">+ Canal <select data-novo-canal><option value="">escolha…</option>
                        ${Object.entries(INSTRUMENTOS).map(([k, i]) => `<option value="${k}">${esc(i.nome)}</option>`).join('')}</select></label>
                </div>
                <div data-canais></div>
            </section>
            <div class="wfm-abas">
                <button type="button" class="wfm-b" data-aba="piano">🎹 Piano roll</button>
                <button type="button" class="wfm-b" data-aba="playlist">▦ Playlist</button>
                <span class="wfm-campo" data-info-aba style="margin-left:6px"></span>
                <span style="flex:1"></span>
                <label class="wfm-campo" data-so-piano>Duração <select data-dur><option value="1">1/16</option><option value="2">1/8</option><option value="4">1/4</option><option value="8">1/2</option><option value="16">1 compasso</option></select></label>
                <button type="button" class="wfm-b" data-acao="limparCanal" data-so-piano title="Apagar as notas deste canal no padrão">Limpar canal</button>
            </div>
            <section class="wfm-painel">
                <div class="wfm-rolo" data-rolo><canvas data-tela></canvas></div>
                <div class="wfm-aviso" data-aviso hidden></div>
            </section>
        </main>`;
        document.body.appendChild(el);
        ligarEventos();
    }

    let avisoT = 0;
    function aviso(txt, ms = 3200) {
        const a = el.querySelector('[data-aviso]');
        a.textContent = txt;
        a.hidden = false;
        clearTimeout(avisoT);
        avisoT = setTimeout(() => { a.hidden = true; }, ms);
    }

    /* ---------- histórico ---------- */
    function mudou() {
        S.passos.push(JSON.stringify(S.p));
        if (S.passos.length > MAX_PASSOS) S.passos.shift();
        S.refazer = [];
        S.sujo = true;
        el.querySelector('[data-acao="desfazer"]').disabled = false;
        el.querySelector('[data-acao="refazer"]').disabled = true;
    }
    function desfazer() {
        if (!S.passos.length) return;
        S.refazer.push(JSON.stringify(S.p));
        trocarProjeto(JSON.parse(S.passos.pop()));
    }
    function refazer() {
        if (!S.refazer.length) return;
        S.passos.push(JSON.stringify(S.p));
        trocarProjeto(JSON.parse(S.refazer.pop()));
    }
    function trocarProjeto(p) {
        S.p = p;
        S.sujo = true;
        if (!p.padroes.some(x => x.id === S.padraoId)) S.padraoId = p.padroes[0].id;
        if (!p.canais.some(c => c.id === S.canalId)) S.canalId = p.canais[0]?.id || null;
        if (A.timer && A.dono === 'estudio') A.p = p;
        desenharTudo();
    }

    /* ---------- abrir / fechar ---------- */
    function abrir({ blocoId, titulo, projeto, aoSalvar }) {
        if (!el) montar();
        if (S) return;
        if (A.dono && A.dono !== 'estudio') parar();
        const p = normalizar(projeto);
        S = { blocoId, aoSalvar, titulo: titulo || 'Música', p, padraoId: p.padroes[0].id, canalId: p.canais.find(c => !INSTRUMENTOS[c.inst].bat)?.id || p.canais[0]?.id || null,
              modo: p.playlist.length ? 'musica' : 'padrao', aba: 'piano', passos: [], refazer: [], sujo: false, dur: 1, arraste: null };
        el.querySelector('[data-titulo]').textContent = S.titulo;
        el.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        desenharTudo();
        rolarParaNota();
        const laco = () => { rafId = requestAnimationFrame(laco); quadroAnimacao(); };
        laco();
        if (!projeto) aviso('Um ritmo de exemplo já está pronto: aperte ▶ Tocar ou o espaço.', 4500);
    }
    function fechar(forcar) {
        if (!S) return;
        if (S.exportando) { aviso('Espere a exportação terminar.'); return; }
        if (S.sujo && !forcar && !confirm('Sair sem salvar as mudanças no cartão?')) return;
        if (A.dono === 'estudio') parar();
        cancelAnimationFrame(rafId);
        S = null;
        el.hidden = true;
        document.documentElement.style.overflow = '';
    }
    async function salvar() {
        if (!S || S.salvando) return;
        S.salvando = true;
        try {
            const p = S.p, dur = (p.playlist.length ? fimMusica(p) : p.passos) * durPasso(p);
            await S.aoSalvar({ projeto: JSON.parse(JSON.stringify(p)), dur: Math.round(dur * 10) / 10 });
            S.sujo = false;
            aviso('Música salva no cartão.');
        } catch {
            aviso('Não consegui salvar. Verifique a conexão e tente de novo.', 5000);
        } finally {
            if (S) S.salvando = false;
        }
    }
    async function exportar() {
        if (!S || S.exportando) return;
        if (!window.OfflineAudioContext) { aviso('Este navegador não consegue exportar áudio.'); return; }
        S.exportando = true;
        const b = el.querySelector('[data-acao="exportar"]');
        b.disabled = true; b.textContent = 'Exportando…';
        try {
            const { buf, seg } = await renderizar(S.p, S.modo, S.padraoId);
            const url = URL.createObjectURL(wav(buf));
            const a = document.createElement('a');
            a.href = url;
            a.download = (S.titulo || 'musica').replace(/[\\/:*?"<>|]+/g, '').trim().slice(0, 60) + '.wav';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 10000);
            aviso(`WAV exportado (${Math.round(seg)} s${S.modo === 'musica' && S.p.playlist.length ? ', a playlist inteira' : ', o padrão 4 vezes'}).`);
        } catch {
            aviso('A exportação falhou. Tente de novo.', 5000);
        } finally {
            if (S) S.exportando = false;
            b.disabled = false; b.textContent = '⬇ Exportar WAV';
        }
    }

    /* ---------- tocar ---------- */
    function alternarTocar() {
        if (A.timer && A.dono === 'estudio') { parar(); desenharTopo(); return; }
        if (S.modo === 'musica' && !S.p.playlist.length) { aviso('A playlist está vazia: ponha um padrão nela ou toque no modo Padrão.'); return; }
        iniciar(S.p, S.modo, S.padraoId, 'estudio');
        desenharTopo();
    }
    function trocarModo(m) {
        S.modo = m;
        if (A.timer && A.dono === 'estudio') iniciar(S.p, S.modo, S.padraoId, 'estudio');
        desenharTopo();
    }
    function aplicarSom() {
        if (grafo) grafo.atualizar(S.p);
    }

    /* ---------- desenho ---------- */
    function desenharTudo() {
        desenharTopo();
        desenharPadroes();
        desenharRack();
        desenharAbas();
        desenharTela();
    }
    function desenharTopo() {
        const p = S.p, tocando = A.timer && A.dono === 'estudio';
        const b = el.querySelector('[data-acao="tocar"]');
        b.textContent = tocando ? '■ Parar' : '▶ Tocar';
        el.querySelectorAll('[data-modo]').forEach(x => x.classList.toggle('on', x.dataset.modo === S.modo));
        const v = (sel, val) => { const i = el.querySelector(sel); if (document.activeElement !== i) i.value = val; };
        v('[data-bpm]', p.bpm); v('[data-swing]', p.swing); v('[data-npassos]', p.passos); v('[data-volume]', p.volume);
        ['reverb', 'eco', 'filtro'].forEach(k => v(`[data-fx="${k}"]`, p.efeitos[k]));
        el.querySelector('[data-acao="desfazer"]').disabled = !S.passos.length;
        el.querySelector('[data-acao="refazer"]').disabled = !S.refazer.length;
    }
    function desenharPadroes() {
        el.querySelector('[data-padroes]').innerHTML = S.p.padroes.map(x =>
            `<button type="button" class="wfm-pad${x.id === S.padraoId ? ' sel' : ''}" data-padrao="${x.id}"><i style="background:${esc(x.cor)}"></i>${esc(x.nome)}</button>`).join('');
        el.querySelector('[data-rack-padrao]').textContent = '· ' + padrao(S.p, S.padraoId).nome;
    }
    function desenharRack() {
        const p = S.p, pad = padrao(p, S.padraoId);
        el.querySelector('[data-canais]').innerHTML = p.canais.map(c => {
            const inst = INSTRUMENTOS[c.inst], base = inst.base, notas = pad.notas[c.id] || [];
            const passos = [...Array(p.passos)].map((_, s) => {
                const aqui = notas.filter(n => n.s === s);
                const cls = ['wfm-passo'];
                if (Math.floor(s / 4) % 2) cls.push('g2');
                if (aqui.length) cls.push('on');
                if (aqui.some(n => (n.v ?? .8) > .9)) cls.push('ac');
                if (aqui.some(n => n.n !== base)) cls.push('nota');
                return `<button type="button" class="${cls.join(' ')}" data-passo="${s}" aria-label="Passo ${s + 1}"></button>`;
            }).join('');
            return `<div class="wfm-canal${c.id === S.canalId ? ' sel' : ''}" data-canal="${c.id}">
                <button type="button" class="wfm-led${audivel(p, c) ? '' : ' off'}" data-mudo title="Ligar / desligar (mudo)"></button>
                <button type="button" class="wfm-solo${c.solo ? ' on' : ''}" data-solo title="Solo">S</button>
                <button type="button" class="wfm-nome" data-nome title="Editar no piano roll (duplo clique renomeia)">${esc(c.nome)}</button>
                <select data-inst title="Instrumento">${Object.entries(INSTRUMENTOS).map(([k, i]) => `<option value="${k}"${k === c.inst ? ' selected' : ''}>${esc(i.nome)}</option>`).join('')}</select>
                <input type="range" class="wfm-mini" min="0" max="1" step="0.01" value="${c.vol}" data-vol title="Volume">
                <input type="range" class="wfm-mini" min="-1" max="1" step="0.05" value="${c.pan}" data-pan title="Pan (esquerda / direita)">
                <div class="wfm-passos">${passos}</div>
                <button type="button" class="wfm-x" data-tirar title="Tirar o canal">✕</button>
            </div>`;
        }).join('') || '<p style="color:#909dab;padding:8px">Nenhum canal. Adicione um em “+ Canal”.</p>';
    }
    function desenharAbas() {
        el.querySelectorAll('[data-aba]').forEach(x => x.classList.toggle('on', x.dataset.aba === S.aba));
        el.querySelectorAll('[data-so-piano]').forEach(x => { x.style.display = S.aba === 'piano' ? '' : 'none'; });
        el.querySelector('[data-dur]').value = String(S.dur);
        const c = S.p.canais.find(x => x.id === S.canalId);
        el.querySelector('[data-info-aba]').textContent = S.aba === 'piano'
            ? (c ? `${c.nome} · ${padrao(S.p, S.padraoId).nome}` : 'Escolha um canal no rack')
            : `Padrão para pôr: ${padrao(S.p, S.padraoId).nome}`;
    }

    /* ---------- piano roll e playlist (canvas) ---------- */
    const KW = 46, RH = 14, PL_RH = 30, PL_TOPO = 20;
    function medidas() {
        const rolo = el.querySelector('[data-rolo]');
        const W = rolo.clientWidth;
        if (S.aba === 'piano') {
            const cw = Math.max(14, Math.floor((W - KW - 2) / S.p.passos));
            return { cw, w: KW + cw * S.p.passos, h: (NOTA_MAX - NOTA_MIN + 1) * RH };
        }
        const cw = Math.max(26, Math.floor((W - 2) / S.p.compassos));
        return { cw, w: cw * S.p.compassos, h: PL_TOPO + FAIXAS * PL_RH };
    }
    function desenharTela(agora = -1) {
        const c = el.querySelector('[data-tela]'), m = medidas(), dpr = window.devicePixelRatio || 1;
        if (c.width !== Math.round(m.w * dpr) || c.height !== Math.round(m.h * dpr)) {
            c.width = Math.round(m.w * dpr); c.height = Math.round(m.h * dpr);
            c.style.width = m.w + 'px'; c.style.height = m.h + 'px';
        }
        const g = c.getContext('2d');
        g.setTransform(dpr, 0, 0, dpr, 0, 0);
        const prim = getComputedStyle(el).getPropertyValue('--prim').trim() || '#f97316';
        if (S.aba === 'piano') desenharPiano(g, m, prim, agora); else desenharPlaylist(g, m, prim, agora);
    }
    function desenharPiano(g, { cw, w, h }, prim, agora) {
        const p = S.p, canal = p.canais.find(x => x.id === S.canalId);
        g.fillStyle = '#1c2128'; g.fillRect(0, 0, w, h);
        for (let m = NOTA_MAX; m >= NOTA_MIN; m--) {
            const y = (NOTA_MAX - m) * RH;
            g.fillStyle = preta(m) ? '#1a1e24' : '#222831';
            g.fillRect(KW, y, w - KW, RH);
            g.fillStyle = preta(m) ? '#2b2f36' : '#e6e8eb';
            g.fillRect(0, y, KW - 2, RH - 1);
            if (m % 12 === 0) {
                g.fillStyle = '#5b6370'; g.font = '10px system-ui'; g.fillText(nomeNota(m), 4, y + RH - 3);
                g.fillStyle = '#39404b'; g.fillRect(KW, y + RH - 1, w - KW, 1);
            }
        }
        for (let s = 0; s <= p.passos; s++) {
            g.fillStyle = s % 16 === 0 ? '#5b6370' : s % 4 === 0 ? '#3d444e' : '#2a3038';
            g.fillRect(KW + s * cw, 0, 1, h);
        }
        if (agora >= 0 && S.modo === 'padrao') { g.fillStyle = 'rgba(126,231,135,.12)'; g.fillRect(KW + agora * cw, 0, cw, h); }
        if (!canal) return;
        const base = INSTRUMENTOS[canal.inst].base;
        g.fillStyle = 'rgba(255,255,255,.04)'; g.fillRect(KW, (NOTA_MAX - base) * RH, w - KW, RH);
        for (const n of padrao(p, S.padraoId).notas[canal.id] || []) {
            const x = KW + n.s * cw + 1, y = (NOTA_MAX - n.n) * RH + 1;
            g.fillStyle = prim; g.globalAlpha = .55 + .45 * (n.v ?? .8);
            g.fillRect(x, y, n.d * cw - 2, RH - 2);
            g.globalAlpha = 1;
            g.fillStyle = 'rgba(0,0,0,.35)'; g.fillRect(x + n.d * cw - 5, y, 3, RH - 2);
            if (n.d * cw > 30) { g.fillStyle = 'rgba(0,0,0,.7)'; g.font = '9px system-ui'; g.fillText(nomeNota(n.n), x + 3, y + RH - 4); }
        }
    }
    function desenharPlaylist(g, { cw, w, h }, prim, agora) {
        const p = S.p, len = compassosPadrao(p);
        g.fillStyle = '#1c2128'; g.fillRect(0, 0, w, h);
        g.fillStyle = '#2d333b'; g.fillRect(0, 0, w, PL_TOPO);
        for (let f = 0; f < FAIXAS; f++) { g.fillStyle = f % 2 ? '#20252c' : '#1c2128'; g.fillRect(0, PL_TOPO + f * PL_RH, w, PL_RH); }
        for (let c = 0; c <= p.compassos; c++) {
            g.fillStyle = c % 4 === 0 ? '#444c56' : '#2a3038';
            g.fillRect(c * cw, PL_TOPO, 1, h);
            if (c < p.compassos && (cw >= 30 || c % 4 === 0)) { g.fillStyle = '#909dab'; g.font = '10px system-ui'; g.fillText(String(c + 1), c * cw + 3, 14); }
        }
        for (const cl of p.playlist) {
            const pad = padrao(p, cl.p), x = cl.c * cw + 1, y = PL_TOPO + cl.f * PL_RH + 2, ww = len * cw - 2;
            g.fillStyle = pad.cor; g.globalAlpha = .85; g.fillRect(x, y, ww, PL_RH - 4); g.globalAlpha = 1;
            if (cl.p === S.padraoId) { g.strokeStyle = '#fff'; g.lineWidth = 1.5; g.strokeRect(x + .75, y + .75, ww - 1.5, PL_RH - 5.5); }
            g.save(); g.beginPath(); g.rect(x, y, ww, PL_RH - 4); g.clip();
            g.fillStyle = 'rgba(0,0,0,.75)'; g.font = '600 11px system-ui'; g.fillText(pad.nome, x + 4, y + 14);
            // miniatura das notas
            g.fillStyle = 'rgba(0,0,0,.35)';
            Object.values(pad.notas).forEach(ns => ns.forEach(n => g.fillRect(x + (n.s / p.passos) * ww, y + PL_RH - 9, Math.max(1, (n.d / p.passos) * ww - 1), 2)));
            g.restore();
        }
        if (agora >= 0 && S.modo === 'musica') { g.fillStyle = '#7ee787'; g.fillRect((agora / 16) * cw, 0, 2, h); }
    }
    function rolarParaNota() {
        if (S.aba !== 'piano') return;
        const c = S.p.canais.find(x => x.id === S.canalId);
        const notas = c ? padrao(S.p, S.padraoId).notas[c.id] || [] : [];
        const alvo = notas.length ? Math.max(...notas.map(n => n.n)) + 3 : (c ? INSTRUMENTOS[c.inst].base + 12 : 72);
        const rolo = el.querySelector('[data-rolo]');
        requestAnimationFrame(() => { rolo.scrollTop = Math.max(0, (NOTA_MAX - alvo) * RH - 10); });
    }

    let ultimoPasso = -2;
    function quadroAnimacao() {
        if (!S) return;
        const agora = A.dono === 'estudio' ? passoAtual() : -1;
        if (agora === ultimoPasso) return;
        ultimoPasso = agora;
        const p = S.p;
        let local = -1;
        if (agora >= 0) {
            if (S.modo === 'padrao') local = agora;
            else {
                const cl = p.playlist.find(c => c.p === S.padraoId && agora >= c.c * 16 && agora < c.c * 16 + p.passos);
                local = cl ? agora - cl.c * 16 : -1;
            }
            const comp = Math.floor(agora / 16), bat = Math.floor((agora % 16) / 4), pas = agora % 4;
            el.querySelector('[data-lcd]').textContent = `${comp + 1}.${bat + 1}.${pas + 1}`;
        }
        el.querySelectorAll('.wfm-passo.agora').forEach(x => x.classList.remove('agora'));
        if (local >= 0) el.querySelectorAll(`.wfm-passo[data-passo="${local}"]`).forEach(x => x.classList.add('agora'));
        desenharTela(S.aba === 'piano' ? local : agora);
    }

    /* ---------- eventos ---------- */
    function canalDe(alvo) {
        const row = alvo.closest('[data-canal]');
        return row ? S.p.canais.find(c => c.id === row.dataset.canal) : null;
    }
    function ligarEventos() {
        el.addEventListener('click', e => {
            if (!S) return;
            const t = e.target;
            const acao = t.closest('[data-acao]')?.dataset.acao;
            if (acao) { acoes[acao]?.(); return; }
            const modo = t.closest('[data-modo]')?.dataset.modo;
            if (modo) { trocarModo(modo); return; }
            const aba = t.closest('[data-aba]')?.dataset.aba;
            if (aba) { S.aba = aba; el.querySelector('[data-rolo]').scrollTop = 0; desenharAbas(); desenharTela(); rolarParaNota(); return; }
            const pad = t.closest('[data-padrao]')?.dataset.padrao;
            if (pad) { escolherPadrao(pad); return; }
            const c = canalDe(t);
            if (!c) return;
            if (t.closest('[data-mudo]')) { mudou(); c.mudo = !c.mudo; if (!c.mudo) c.solo = false; aplicarSom(); desenharRack(); return; }
            if (t.closest('[data-solo]')) { mudou(); c.solo = !c.solo; aplicarSom(); desenharRack(); return; }
            if (t.closest('[data-tirar]')) {
                if (!confirm(`Tirar o canal “${c.nome}” e as notas dele de todos os padrões?`)) return;
                mudou();
                S.p.canais = S.p.canais.filter(x => x !== c);
                S.p.padroes.forEach(x => delete x.notas[c.id]);
                if (S.canalId === c.id) S.canalId = S.p.canais[0]?.id || null;
                desenharRack(); desenharAbas(); desenharTela();
                return;
            }
            if (t.closest('[data-nome]')) { S.canalId = c.id; S.aba = 'piano'; desenharRack(); desenharAbas(); desenharTela(); rolarParaNota(); previaNota(S.p, c, INSTRUMENTOS[c.inst].base); return; }
            const passo = t.closest('[data-passo]');
            if (passo) alternarPasso(c, +passo.dataset.passo, e.shiftKey);
        });
        el.addEventListener('dblclick', e => {
            const c = S && e.target.closest('[data-nome]') && canalDe(e.target);
            if (!c) return;
            const nome = prompt('Nome do canal:', c.nome);
            if (nome && nome.trim()) { mudou(); c.nome = nome.trim().slice(0, 30); desenharRack(); desenharAbas(); }
        });
        el.addEventListener('input', e => {
            if (!S) return;
            const t = e.target, c = canalDe(t);
            if (c && t.matches('[data-vol],[data-pan]')) {
                if (!S._arrastoSlider) { mudou(); S._arrastoSlider = true; }
                if (t.matches('[data-vol]')) c.vol = +t.value; else c.pan = +t.value;
                aplicarSom();
                return;
            }
            const fx = t.dataset.fx;
            if (fx || t.matches('[data-swing],[data-volume]')) {
                if (!S._arrastoSlider) { mudou(); S._arrastoSlider = true; }
                if (fx) S.p.efeitos[fx] = +t.value;
                else if (t.matches('[data-swing]')) S.p.swing = +t.value;
                else S.p.volume = +t.value;
                aplicarSom();
            }
        });
        el.addEventListener('change', e => {
            if (!S) return;
            const t = e.target;
            S._arrastoSlider = false;
            if (t.matches('[data-bpm]')) { mudou(); S.p.bpm = clamp(Math.round(+t.value || 124), 40, 220); t.value = S.p.bpm; aplicarSom(); desenharTopo(); return; }
            if (t.matches('[data-npassos]')) { mudarPassos(+t.value); return; }
            if (t.matches('[data-dur]')) { S.dur = +t.value; return; }
            if (t.matches('[data-novo-canal]')) {
                const inst = t.value; t.value = '';
                if (!INSTRUMENTOS[inst]) return;
                mudou();
                const c = { id: uid(), nome: INSTRUMENTOS[inst].nome.replace(/ \(.*\)$/, ''), inst, vol: .8, pan: 0, mudo: false, solo: false };
                S.p.canais.push(c);
                S.canalId = c.id;
                desenharRack(); desenharAbas(); desenharTela(); rolarParaNota();
                previaNota(S.p, c, INSTRUMENTOS[inst].base);
                return;
            }
            const c = canalDe(t);
            if (c && t.matches('[data-inst]')) {
                mudou();
                const antes = INSTRUMENTOS[c.inst], depois = INSTRUMENTOS[t.value];
                // passos na altura base acompanham a nova altura base
                S.p.padroes.forEach(x => (x.notas[c.id] || []).forEach(n => { if (n.n === antes.base) n.n = depois.base; }));
                c.inst = t.value;
                desenharRack(); desenharTela();
                previaNota(S.p, c, depois.base);
            }
        });
        const tela = el.querySelector('[data-tela]');
        tela.addEventListener('contextmenu', e => e.preventDefault());
        tela.addEventListener('pointerdown', e => (S.aba === 'piano' ? pianoDown(e) : playlistDown(e)));
        tela.addEventListener('pointermove', e => { if (S && S.arraste) (S.aba === 'piano' ? pianoMove(e) : playlistMove(e)); });
        const solta = () => {
            if (!S || !S.arraste) return;
            const a = S.arraste;
            S.arraste = null;
            if (a.tipo === 'mover' && !a.moveu && S.aba === 'piano') apagarNota(a.nota, false);
            if (a.tipo === 'clipe' && !a.moveu) { escolherPadrao(a.clipe.p); return; }
            if (a.nota && a.tipo !== 'mover' && [1, 2, 4, 8, 16].includes(a.nota.d)) S.dur = a.nota.d;
            if (S.aba === 'piano') desenharRack();
            desenharAbas();
            desenharTela();
        };
        tela.addEventListener('pointerup', solta);
        tela.addEventListener('pointercancel', solta);
        tela.addEventListener('dblclick', e => {
            if (S.aba !== 'playlist') return;
            const { f, c } = posPlaylist(e), hit = clipeEm(f, c);
            if (hit) { mudou(); S.p.playlist = S.p.playlist.filter(x => x !== hit); desenharTela(); }
        });
        addEventListener('resize', () => { if (S) desenharTela(); });
        document.addEventListener('keydown', e => {
            if (!S || el.hidden) return;
            const campo = e.target.closest && e.target.closest('input,select,textarea');
            if (e.key === 'Escape') { e.preventDefault(); fechar(false); return; }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); salvar(); return; }
            if (campo) return;
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? refazer() : desfazer(); return; }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); refazer(); return; }
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            if (e.key === ' ') { e.preventDefault(); alternarTocar(); return; }
            const k = TECLAS[e.key.toLowerCase()];
            const c = S.p.canais.find(x => x.id === S.canalId);
            if (k !== undefined && c && !e.repeat) {
                e.preventDefault();
                const base = INSTRUMENTOS[c.inst].bat ? 60 : Math.floor(INSTRUMENTOS[c.inst].base / 12) * 12;
                previaNota(S.p, c, base + k);
            }
        }, true);
    }

    const acoes = {
        tocar: () => alternarTocar(),
        salvar: () => salvar(),
        exportar: () => exportar(),
        fechar: () => fechar(false),
        desfazer: () => desfazer(),
        refazer: () => refazer(),
        novoPadrao() {
            mudou();
            const x = { id: uid(), nome: 'Padrão ' + (S.p.padroes.length + 1), cor: CORES[S.p.padroes.length % CORES.length], notas: {} };
            S.p.padroes.push(x);
            escolherPadrao(x.id);
        },
        duplicarPadrao() {
            mudou();
            const o = padrao(S.p, S.padraoId);
            const x = { ...JSON.parse(JSON.stringify(o)), id: uid(), nome: o.nome + ' (cópia)', cor: CORES[S.p.padroes.length % CORES.length] };
            S.p.padroes.push(x);
            escolherPadrao(x.id);
        },
        renomearPadrao() {
            const o = padrao(S.p, S.padraoId);
            const nome = prompt('Nome do padrão:', o.nome);
            if (nome && nome.trim()) { mudou(); o.nome = nome.trim().slice(0, 30); desenharPadroes(); desenharAbas(); desenharTela(); }
        },
        apagarPadrao() {
            if (S.p.padroes.length < 2) { aviso('A música precisa de pelo menos um padrão.'); return; }
            const o = padrao(S.p, S.padraoId);
            if (!confirm(`Apagar o padrão “${o.nome}” (e os clipes dele na playlist)?`)) return;
            mudou();
            S.p.padroes = S.p.padroes.filter(x => x !== o);
            S.p.playlist = S.p.playlist.filter(c => c.p !== o.id);
            escolherPadrao(S.p.padroes[0].id);
        },
        limparCanal() {
            const c = S.p.canais.find(x => x.id === S.canalId);
            if (!c) return;
            mudou();
            delete padrao(S.p, S.padraoId).notas[c.id];
            desenharRack(); desenharTela();
        },
    };

    function escolherPadrao(id) {
        S.padraoId = id;
        if (A.timer && A.dono === 'estudio') A.padraoId = id;
        desenharPadroes(); desenharRack(); desenharAbas(); desenharTela();
    }
    function mudarPassos(n) {
        if (n === S.p.passos) return;
        if (n < S.p.passos && S.p.padroes.some(x => Object.values(x.notas).some(ns => ns.some(nt => nt.s + nt.d > n)))
            && !confirm(`As notas depois do passo ${n} serão cortadas. Continuar?`)) { desenharTopo(); return; }
        mudou();
        S.p.passos = n;
        S.p.padroes.forEach(x => Object.keys(x.notas).forEach(k => {
            x.notas[k] = x.notas[k].filter(nt => nt.s < n).map(nt => ({ ...nt, d: Math.min(nt.d, n - nt.s) }));
        }));
        // clipes não podem se sobrepor na mesma faixa com o padrão mais comprido
        const len = n / 16, vistos = [];
        S.p.playlist = S.p.playlist.sort((a, b) => a.c - b.c).filter(c => {
            if (vistos.some(v => v.f === c.f && c.c < v.c + len)) return false;
            vistos.push(c);
            return true;
        });
        if (A.timer && A.dono === 'estudio') iniciar(S.p, S.modo, S.padraoId, 'estudio');
        desenharTudo();
    }
    function alternarPasso(c, s, acento) {
        const pad = padrao(S.p, S.padraoId), notas = pad.notas[c.id] || (pad.notas[c.id] = []);
        const aqui = notas.filter(n => n.s === s);
        mudou();
        if (aqui.length && acento) {
            aqui.forEach(n => { n.v = (n.v ?? .8) > .9 ? .8 : 1; });
        } else if (aqui.length) {
            pad.notas[c.id] = notas.filter(n => n.s !== s);
        } else {
            const n = { s, n: INSTRUMENTOS[c.inst].base, d: 1, v: acento ? 1 : .8 };
            notas.push(n);
            if (!A.timer) previaNota(S.p, c, n.n, n.v);
        }
        desenharRack();
        if (c.id === S.canalId) desenharTela();
    }

    /* piano roll */
    function posPiano(e) {
        const r = e.currentTarget.getBoundingClientRect(), { cw } = medidas();
        const x = e.clientX - r.left, y = e.clientY - r.top;
        return { x, sf: (x - KW) / cw, m: clamp(NOTA_MAX - Math.floor(y / RH), NOTA_MIN, NOTA_MAX) };
    }
    function pianoDown(e) {
        const c = S.p.canais.find(x => x.id === S.canalId);
        if (!c) { aviso('Escolha um canal no rack (clique no nome dele).'); return; }
        const { x, sf, m } = posPiano(e);
        if (x < KW) { previaNota(S.p, c, m); return; }
        const pad = padrao(S.p, S.padraoId), notas = pad.notas[c.id] || (pad.notas[c.id] = []);
        const hit = notas.find(n => n.n === m && sf >= n.s && sf < n.s + n.d);
        e.currentTarget.setPointerCapture(e.pointerId);
        if (e.button === 2) { if (hit) apagarNota(hit, true); return; }
        mudou();
        if (hit) {
            S.arraste = sf > hit.s + hit.d - .35 && hit.d * medidas().cw > 12
                ? { tipo: 'esticar', nota: hit, canal: c }
                : { tipo: 'mover', nota: hit, canal: c, off: Math.floor(sf) - hit.s, n0: hit.n, s0: hit.s, moveu: false };
            if (S.arraste.tipo === 'mover') previaNota(S.p, c, hit.n, hit.v);
            return;
        }
        const s = clamp(Math.floor(sf), 0, S.p.passos - 1);
        const n = { s, n: m, d: Math.min(S.dur, S.p.passos - s), v: .8 };
        notas.push(n);
        S.arraste = { tipo: 'esticar', nota: n, canal: c, novo: true };
        previaNota(S.p, c, m);
        desenharTela();
    }
    function pianoMove(e) {
        const a = S.arraste, { sf, m } = posPiano(e), n = a.nota;
        if (a.tipo === 'esticar') {
            const d = clamp(Math.ceil(sf - n.s), 1, S.p.passos - n.s);
            if (!a.novo || d > 1 || sf - n.s > 1) n.d = d;
        } else {
            const s = clamp(Math.floor(sf) - a.off, 0, S.p.passos - n.d);
            if (s !== n.s || m !== n.n) {
                a.moveu = true;
                if (m !== n.n) previaNota(S.p, a.canal, m, n.v);
                n.s = s; n.n = m;
            }
        }
        desenharTela();
    }
    function apagarNota(nota, registrar) {
        const c = S.p.canais.find(x => x.id === S.canalId), pad = padrao(S.p, S.padraoId);
        if (!c || !pad.notas[c.id]) return;
        if (registrar) mudou();
        pad.notas[c.id] = pad.notas[c.id].filter(n => n !== nota);
        desenharRack(); desenharTela();
    }

    /* playlist */
    function posPlaylist(e) {
        const r = e.currentTarget.getBoundingClientRect(), { cw } = medidas();
        return { f: clamp(Math.floor((e.clientY - r.top - PL_TOPO) / PL_RH), 0, FAIXAS - 1), c: Math.floor((e.clientX - r.left) / cw), y: e.clientY - r.top };
    }
    const clipeEm = (f, c, ignorar) => S.p.playlist.find(x => x !== ignorar && x.f === f && c >= x.c && c < x.c + compassosPadrao(S.p));
    const cabe = (f, c, ignorar) => {
        const len = compassosPadrao(S.p);
        return c >= 0 && !S.p.playlist.some(x => x !== ignorar && x.f === f && c < x.c + len && x.c < c + len);
    };
    function playlistDown(e) {
        const { f, c, y } = posPlaylist(e);
        if (y < PL_TOPO) {
            // régua: no modo música, começa a tocar dali
            if (S.p.playlist.length) { S.modo = 'musica'; iniciar(S.p, 'musica', S.padraoId, 'estudio', Math.min(c * 16, Math.max(0, fimMusica(S.p) - 1))); desenharTopo(); }
            return;
        }
        const hit = clipeEm(f, c);
        e.currentTarget.setPointerCapture(e.pointerId);
        if (e.button === 2) { if (hit) { mudou(); S.p.playlist = S.p.playlist.filter(x => x !== hit); desenharTela(); } return; }
        if (hit) { mudou(); S.arraste = { tipo: 'clipe', clipe: hit, off: c - hit.c, moveu: false }; return; }
        if (!cabe(f, c)) return;
        mudou();
        const novo = { id: uid(), f, c, p: S.padraoId };
        S.p.playlist.push(novo);
        S.arraste = { tipo: 'clipe-novo', clipe: novo, off: 0, moveu: true };
        crescerPlaylist();
        desenharTela();
    }
    function playlistMove(e) {
        const a = S.arraste, { f, c } = posPlaylist(e), nc = Math.max(0, c - a.off);
        if ((nc !== a.clipe.c || f !== a.clipe.f) && cabe(f, nc, a.clipe)) {
            a.clipe.c = nc; a.clipe.f = f; a.moveu = true;
            crescerPlaylist();
            desenharTela();
        }
    }
    function crescerPlaylist() {
        const fim = fimMusica(S.p) / 16;
        if (fim > S.p.compassos - 2) S.p.compassos = Math.min(128, S.p.compassos + 8);
    }

    /* ---------- prévia no cartão (sem abrir o estúdio) ---------- */
    function previa(blocoId, projeto) {
        if (A.timer && A.dono === 'cartao:' + blocoId) { parar(); return false; }
        if (S) return false;
        const p = normalizar(projeto);
        const musica = p.playlist.length > 0;
        iniciar(p, musica ? 'musica' : 'padrao', p.padroes[0].id, 'cartao:' + blocoId);
        return true;
    }
    const tocandoPrevia = blocoId => !!A.timer && A.dono === 'cartao:' + blocoId;

    window.WFEstudioMusica = { abrir, fechar: () => fechar(true), previa, tocandoPrevia, parar, novoProjeto, INSTRUMENTOS };
})();
