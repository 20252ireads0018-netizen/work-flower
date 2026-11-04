/* Work Flower · cartão "Editor de PDF" (Corpo, Mente, Carteira, Negócios e Diverso)
   Estende o WFQuadro.metodos (como graficos.js): carregue depois do quadro.js e antes do Alpine iniciar.

   Onde ficam os dados
   - O PDF original fica em DISCO no servidor (EditorPdfController, storage/app/editorpdf/<dono>/<área>/<arquivo>.pdf),
     enviado em pedaços de 3 MB (cada pedido ~4 MB em base64, por causa do mod_security da hospedagem). Até 80 MB.
   - As edições ficam em b.dados.pdf (versão 2):
       { v: 2, fontes: { p: {id, v, tam, nome}, m<k>: {...} },          ← PDFs usados (o principal é "p"; "Juntar PDF" cria outros)
         paginas: [{ f: 'p'|'m<k>'|null, o: índice na fonte, rot: 0|90|180|270, branca: [w, h]|null, fundo: '#rrggbb'|null, itens: [...] }] }
     itens (em pontos do PDF, a partir do canto de cima à esquerda da página como aparece na tela, já girada):
       { t: 'texto', x, y, w, h, txt, tam, cor, fonte: 'sans'|'serif'|'mono', neg, ita, al: 'l'|'c'|'r', fundo: '#rrggbb'|null }
       { t: 'img', x, y, w, h, k }      (imagem/assinatura em img.<id>.pi.<k>)
       { t: 'ret'|'elipse', x, y, w, h, cor, esp, fundo } · { t: 'marca', x, y, w, h, cor } · { t: 'linha', x1, y1, x2, y2, cor, esp, seta }
   - Cartões antigos (versão 1): o PDF estava no banco, na chave img.<id>.pdf (data URL), sem "fontes".
     Ao abrir, o arquivo é levado para o disco e a chave antiga é apagada (se der errado, continua funcionando do jeito antigo).

   Editor em tela cheia: pdf.js desenha só as páginas que estão à vista (IntersectionObserver), guarda as já desenhadas e
   faz miniaturas pequenas; as edições ficam numa camada por cima e não fazem a página ser desenhada de novo.
   "Baixar PDF" monta o arquivo final com pdf-lib (fundo, página original, itens). As fontes padrão do PDF cobrem os
   acentos do português (WinAnsi); só emoji e símbolos raros viram "?". Os dois scripts vêm do cdnjs. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid, snap } = Q.util;
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    const PDFLIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js';
    const MAX_PDF = 80 * 1024 * 1024;
    const PARTE = 3 * 1024 * 1024;
    const PT = 4 / 3;                       // zoom 100% = tamanho real (1 ponto = 1,333 px)
    const PAD = 2;                          // folga dentro da caixa de texto, em pontos
    const LINHA = 1.25;                     // altura da linha do texto (em "tam")
    const FAMILIAS = { sans: 'Helvetica, Arial, sans-serif', serif: '"Times New Roman", Times, serif', mono: '"Courier New", Courier, monospace' };
    const NOMES_FONTE = { sans: 'Helvetica (Arial)', serif: 'Times', mono: 'Courier' };
    // onde fica a linha de base da 1ª linha, em "tam" a partir do topo (igual ao navegador com line-height 1,25)
    const BASE = { sans: 0.97, serif: 0.96, mono: 0.89 };
    const CORES = ['#111111', '#ffffff', '#2f6fc7', '#dc2626', '#16a34a', '#f2c230', '#fb923c', '#a78bfa'];
    const MARCAS = ['#f2c230', '#4ade80', '#60a5fa', '#f472b6', '#fb923c'];
    const ZOOMS = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];

    /* ================= utilidades ================= */
    const carregar = (src, global) => new Promise((ok, falha) => {
        if (window[global]) return ok(window[global]);
        const s = document.createElement('script');
        s.src = src;
        s.onload = () => (window[global] ? ok(window[global]) : falha(new Error(global)));
        s.onerror = () => falha(new Error(global));
        document.head.appendChild(s);
    });
    async function pdfjs() {
        const lib = await carregar(PDFJS + 'pdf.min.js', 'pdfjsLib');
        lib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js';
        return lib;
    }
    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const clone = o => JSON.parse(JSON.stringify(o));
    const limitar = (v, a, b) => Math.min(b, Math.max(a, v));
    const espera = ms => new Promise(r => setTimeout(r, ms));
    const ehHex = c => /^#[0-9a-f]{6}$/i.test(c || '');
    const hexOu = (c, pad) => (ehHex(c) ? c : pad);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const legivel = n => (n >= 1048576 ? (n / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB');
    const bytesDe = url => fetch(url, { credentials: 'same-origin' }).then(r => { if (!r.ok) throw Object.assign(new Error('HTTP ' + r.status), { status: r.status }); return r.arrayBuffer(); }).then(b => new Uint8Array(b));
    const hexRgb = (lib, h) => { const n = parseInt(String(h || '#000000').slice(1), 16); return lib.rgb(((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255); };
    const rgbHex = (r, g, b) => '#' + [r, g, b].map(v => limitar(Math.round(v), 0, 255).toString(16).padStart(2, '0')).join('');
    // as fontes padrão do PDF (WinAnsi) têm todos os acentos do português; emoji e símbolos raros viram "?"
    const winAnsi = s => String(s || '').replace(/\r/g, '').replace(/\t/g, '    ').replace(/[^\x0A\x20-\x7E\xA0-\xFF–—‘’‚“”„†‡•…‰‹›€™ŠšŽžŒœŸƒˆ˜]/g, '?');
    const aleatorio = n => Array.from(crypto.getRandomValues(new Uint8Array(n)), b => 'abcdefghijklmnopqrstuvwxyz0123456789'[b % 36]).join('');
    const el = (tag, cls, html) => { const e = document.createElement(tag); if (cls) e.className = cls; if (html !== undefined) e.innerHTML = html; return e; };
    const caixaDe = it => (it.t === 'linha'
        ? { x: Math.min(it.x1, it.x2), y: Math.min(it.y1, it.y2), w: Math.abs(it.x2 - it.x1), h: Math.abs(it.y2 - it.y1) }
        : { x: it.x, y: it.y, w: it.w, h: it.h });
    function dataUrlBytes(u) {
        const s = atob(u.slice(u.indexOf(',') + 1)), b = new Uint8Array(s.length);
        for (let i = 0; i < s.length; i++) b[i] = s.charCodeAt(i);
        return b;
    }

    /* ================= arquivos no servidor ================= */
    /** Base dos endereços dos arquivos desta área: <app>/editor-pdf/<área> (vem do cfg.url = <app>/<área>/dados). */
    function rotaArquivos(cfgUrl) {
        const m = String(cfgUrl || '').match(/^(.*)\/([a-z]+)\/dados\/?$/);
        return m ? m[1] + '/editor-pdf/' + m[2] : null;
    }
    const urlFonte = (base, f) => `${base}/${encodeURIComponent(f.id)}?v=${encodeURIComponent(f.v || '')}`;
    const base64 = u8 => new Promise((ok, falha) => {
        const r = new FileReader();
        r.onload = () => { const s = String(r.result); ok(s.slice(s.indexOf(',') + 1)); };
        r.onerror = falha;
        r.readAsDataURL(new Blob([u8]));
    });
    /** POST com JSON e progresso do envio (XMLHttpRequest, que o fetch não tem). */
    function postar(url, corpo, aoProgresso) {
        return new Promise((ok, falha) => {
            const x = new XMLHttpRequest();
            x.open('POST', url);
            x.withCredentials = true;
            x.setRequestHeader('Content-Type', 'application/json');
            x.setRequestHeader('Accept', 'application/json');
            x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            x.setRequestHeader('X-CSRF-TOKEN', meta('csrf-token'));
            if (meta('wf-dono')) x.setRequestHeader('X-WF-Dono', meta('wf-dono')); // workflow de outra pessoa
            if (aoProgresso) x.upload.onprogress = e => { if (e.lengthComputable) aoProgresso(e.loaded / e.total); };
            x.onload = () => {
                let j = {};
                try { j = JSON.parse(x.responseText || '{}'); } catch { /* resposta sem JSON */ }
                if (x.status >= 200 && x.status < 300) return ok(j);
                const msg = x.status === 401 || x.status === 419 ? 'Sua sessão expirou. Recarregue a página e tente de novo.'
                    : x.status === 403 ? (j.message || 'Você não pode mudar este workflow.')
                    : x.status === 406 || x.status === 413 ? 'O servidor recusou o envio (' + x.status + ').'
                    : (j.message || 'O servidor respondeu ' + x.status + '.');
                falha(Object.assign(new Error(msg), { status: x.status }));
            };
            x.onerror = () => falha(Object.assign(new Error('Sem conexão com o servidor.'), { status: 0 }));
            x.send(JSON.stringify(corpo));
        });
    }
    /** Envia um PDF em pedaços; devolve { id, v, tam }. aoProgresso(0..1). */
    async function enviarArquivo(base, id, bytes, aoProgresso) {
        const envio = aleatorio(20), partes = Math.max(1, Math.ceil(bytes.length / PARTE));
        for (let n = 0; n < partes; n++) {
            const dados = await base64(bytes.subarray(n * PARTE, (n + 1) * PARTE));
            for (let tent = 0; ; tent++) {
                try {
                    await postar(`${base}/${encodeURIComponent(id)}/parte`, { envio, n, dados }, f => aoProgresso && aoProgresso((n + f) / partes));
                    break;
                } catch (x) {
                    // erro de rede, 429 ou 5xx: tenta mais duas vezes; o resto (422, 403, 406…) não adianta repetir
                    if (tent >= 2 || (x.status && x.status !== 429 && x.status < 500)) throw x;
                    await espera(1500 * (tent + 1));
                }
            }
        }
        const j = await postar(`${base}/${encodeURIComponent(id)}/concluir`, { envio, partes, tamanho: bytes.length });
        if (aoProgresso) aoProgresso(1);
        return { id, v: j.versao, tam: bytes.length };
    }
    function apagarArquivo(base, id) {
        return fetch(`${base}/${encodeURIComponent(id)}`, {
            method: 'DELETE', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': meta('csrf-token') },
        }).catch(() => {});
    }

    /* ================= formato dos dados ================= */
    function normItem(it) {
        if (!it || typeof it !== 'object') return null;
        const n = (v, pad) => (Number.isFinite(Number(v)) ? Number(v) : pad);
        const cx = { x: n(it.x, 40), y: n(it.y, 40), w: Math.max(4, n(it.w, 120)), h: Math.max(4, n(it.h, 30)) };
        const cor = hexOu(it.cor, '#111111');
        switch (it.t) {
            case 'texto': return { t: 'texto', ...cx, txt: String(it.txt ?? ''), tam: limitar(n(it.tam, 14), 4, 200), cor,
                fonte: FAMILIAS[it.fonte] ? it.fonte : 'sans', neg: !!it.neg, ita: !!it.ita, al: ['l', 'c', 'r'].includes(it.al) ? it.al : 'l', fundo: hexOu(it.fundo, null) };
            case 'img': return it.k ? { t: 'img', ...cx, k: String(it.k) } : null;
            case 'ret': case 'elipse': return { t: it.t, ...cx, cor, esp: limitar(n(it.esp, 2), 0, 40), fundo: hexOu(it.fundo, null) };
            case 'marca': return { t: 'marca', ...cx, cor: hexOu(it.cor, '#f2c230') };
            case 'linha': return { t: 'linha', x1: n(it.x1, 40), y1: n(it.y1, 40), x2: n(it.x2, 160), y2: n(it.y2, 40), cor, esp: limitar(n(it.esp, 2), 0.5, 40), seta: !!it.seta };
            default: return null;
        }
    }
    /** Converte qualquer versão dos dados do cartão para a versão 2 (cartões antigos continuam abrindo). */
    function normalizar(d) {
        d = d && typeof d === 'object' ? d : {};
        const fontes = {};
        Object.entries(d.fontes && typeof d.fontes === 'object' ? d.fontes : {}).forEach(([k, f]) => {
            if (f && typeof f.id === 'string') fontes[k] = { id: f.id, v: String(f.v || ''), tam: Number(f.tam) || 0, nome: String(f.nome || '') };
        });
        const paginas = (Array.isArray(d.paginas) ? d.paginas : []).filter(p => p && typeof p === 'object').map(p => {
            const branca = Array.isArray(p.branca) ? [limitar(Number(p.branca[0]) || 595, 36, 14400), limitar(Number(p.branca[1]) || 842, 36, 14400)] : null;
            const r = Math.round((Number(p.rot) || 0) / 90) * 90;
            return {
                f: branca ? null : (typeof p.f === 'string' && p.f ? p.f : 'p'),
                o: Math.max(0, Number(p.o) | 0),
                rot: branca ? 0 : ((r % 360) + 360) % 360,
                branca,
                fundo: hexOu(p.fundo, null),
                itens: (Array.isArray(p.itens) ? p.itens : []).map(normItem).filter(Boolean),
            };
        });
        return { v: 2, fontes, paginas };
    }

    /* ================= quebra das linhas do texto (igual na tela e no PDF) ================= */
    function quebrar(txt, largura, medir) {
        const linhas = [];
        String(txt || '').split('\n').forEach(par => {
            let atual = '';
            par.split(/(\s+)/).forEach(pedaco => {
                if (!pedaco) return;
                const tenta = atual + pedaco;
                if (atual && !/^\s+$/.test(pedaco) && medir(tenta.trimEnd()) > largura) {
                    linhas.push(atual.trimEnd());
                    atual = pedaco;
                } else atual = tenta;
                // palavra maior que a caixa: quebra no meio (como o overflow-wrap da tela)
                while (!/^\s+$/.test(atual) && medir(atual.trimEnd()) > largura && atual.trim().length > 1) {
                    let k = atual.length - 1;
                    while (k > 1 && medir(atual.slice(0, k)) > largura) k--;
                    linhas.push(atual.slice(0, k));
                    atual = atual.slice(k);
                }
            });
            linhas.push(atual.trimEnd());
        });
        return linhas;
    }

    /* ================= gerar o PDF final ================= */
    /** fonteBytes(chave) → Uint8Array do PDF daquela fonte. aoProgresso(texto). */
    async function gerarPdf(dados, fonteBytes, imagens, aoProgresso) {
        const lib = await carregar(PDFLIB, 'PDFLib');
        const { PDFDocument, StandardFonts, degrees } = lib;
        const d = normalizar(dados);
        const fala = t => { if (aoProgresso) aoProgresso(t); };
        // páginas usadas de cada fonte (cada página original entra uma vez só, mesmo duplicada)
        const usadas = {};
        d.paginas.forEach(p => { if (p.f) (usadas[p.f] ??= new Set()).add(p.o); });
        const chaves = Object.keys(usadas), lidos = {};
        let original = null;
        for (const k of chaves) {
            fala('Lendo o PDF' + (chaves.length > 1 ? ` (${chaves.indexOf(k) + 1} de ${chaves.length})` : '') + '…');
            const bytes = await fonteBytes(k);
            if (k === 'p') original = bytes;
            lidos[k] = await PDFDocument.load(bytes, { ignoreEncryption: true, updateMetadata: false });
        }
        // sem nenhuma edição: devolve o arquivo original como está (rápido e sem perder links nem formulários)
        if (original && chaves.length === 1 && d.paginas.length === lidos.p.getPageCount()
            && d.paginas.every((p, i) => p.f === 'p' && p.o === i && !p.rot && !p.fundo && !p.itens.length)) return original;
        const saida = await PDFDocument.create();
        const emb = {}; // chave → { o → { e: página embutida, r: rotação original } }
        for (const k of chaves) {
            const doc = lidos[k];
            const idx = [...usadas[k]].filter(o => o < doc.getPageCount());
            const pags = idx.map(o => doc.getPage(o));
            // a área visível da página é a CropBox (é o que o pdf.js mostra)
            const caixas = pags.map(pg => { const c = pg.getCropBox(); return { left: c.x, bottom: c.y, right: c.x + c.width, top: c.y + c.height }; });
            const embutidas = await saida.embedPages(pags, caixas);
            emb[k] = {};
            idx.forEach((o, i) => { emb[k][o] = { e: embutidas[i], r: ((pags[i].getRotation().angle || 0) % 360 + 360) % 360 }; });
        }
        const fontes = {};
        const NOMES = {
            sans: ['Helvetica', 'HelveticaBold', 'HelveticaOblique', 'HelveticaBoldOblique'],
            serif: ['TimesRoman', 'TimesRomanBold', 'TimesRomanItalic', 'TimesRomanBoldItalic'],
            mono: ['Courier', 'CourierBold', 'CourierOblique', 'CourierBoldOblique'],
        };
        const fonteDe = async it => {
            const nome = NOMES[it.fonte || 'sans'][(it.neg ? 1 : 0) + (it.ita ? 2 : 0)];
            return (fontes[nome] ??= await saida.embedFont(StandardFonts[nome]));
        };
        const imgs = {};
        const imagemDe = async k => {
            if (imgs[k] !== undefined) return imgs[k];
            const src = imagens[k];
            if (!src) return (imgs[k] = null);
            const b = await bytesDe(src);
            imgs[k] = /^data:image\/png/i.test(src) ? await saida.embedPng(b) : await saida.embedJpg(b);
            return imgs[k];
        };
        const multiplicar = lib.BlendMode ? { blendMode: lib.BlendMode.Multiply } : {};
        const ponta = lib.LineCapStyle ? { lineCap: lib.LineCapStyle.Round } : {};
        for (let i = 0; i < d.paginas.length; i++) {
            if (i % 10 === 0) fala(`Montando a página ${i + 1} de ${d.paginas.length}…`);
            const p = d.paginas[i];
            const fonte = p.f && emb[p.f] && emb[p.f][p.o];
            if (p.f && !fonte) continue; // página de um arquivo que sumiu
            const W0 = fonte ? fonte.e.width : p.branca[0], H0 = fonte ? fonte.e.height : p.branca[1];
            const giro = fonte ? (fonte.r + p.rot) % 360 : 0;
            const [W, H] = giro % 180 ? [H0, W0] : [W0, H0];
            const pg = saida.addPage([W, H]);
            if (p.fundo) pg.drawRectangle({ x: 0, y: 0, width: W, height: H, color: hexRgb(lib, p.fundo) });
            if (fonte) {
                // girar no sentido horário = rotação negativa no pdf-lib, com o canto certo na origem
                const pos = { 0: { x: 0, y: 0 }, 90: { x: 0, y: H }, 180: { x: W, y: H }, 270: { x: W, y: 0 } }[giro];
                pg.drawPage(fonte.e, { ...pos, width: W0, height: H0, rotate: degrees(-giro) });
            }
            for (const it of p.itens) {
                const cor = hexRgb(lib, it.cor);
                if (it.t === 'linha') {
                    const b = { x: it.x2, y: H - it.y2 };
                    pg.drawLine({ start: { x: it.x1, y: H - it.y1 }, end: b, thickness: it.esp, color: cor, ...ponta });
                    if (it.seta) pontaSeta(it).forEach(([x, y]) => pg.drawLine({ start: b, end: { x, y: H - y }, thickness: it.esp, color: cor, ...ponta }));
                    continue;
                }
                const y = H - it.y - it.h;
                if (it.t === 'marca') pg.drawRectangle({ x: it.x, y, width: it.w, height: it.h, color: cor, opacity: 0.4, ...multiplicar });
                else if (it.t === 'ret') {
                    const m = it.esp / 2;
                    pg.drawRectangle({ x: it.x + m, y: y + m, width: Math.max(0.1, it.w - it.esp), height: Math.max(0.1, it.h - it.esp),
                        ...(it.esp ? { borderColor: cor, borderWidth: it.esp } : {}), ...(it.fundo ? { color: hexRgb(lib, it.fundo) } : {}) });
                } else if (it.t === 'elipse') {
                    pg.drawEllipse({ x: it.x + it.w / 2, y: y + it.h / 2, xScale: Math.max(0.1, it.w / 2 - it.esp / 2), yScale: Math.max(0.1, it.h / 2 - it.esp / 2),
                        ...(it.esp ? { borderColor: cor, borderWidth: it.esp } : {}), ...(it.fundo ? { color: hexRgb(lib, it.fundo) } : {}) });
                } else if (it.t === 'img') {
                    const img = await imagemDe(it.k);
                    if (img) pg.drawImage(img, { x: it.x, y, width: it.w, height: it.h });
                } else if (it.t === 'texto') {
                    if (it.fundo) pg.drawRectangle({ x: it.x, y, width: it.w, height: it.h, color: hexRgb(lib, it.fundo) });
                    const f = await fonteDe(it), tam = it.tam, larg = Math.max(1, it.w - 2 * PAD);
                    const linhas = quebrar(winAnsi(it.txt), larg, s => f.widthOfTextAtSize(s, tam));
                    linhas.forEach((l, k) => {
                        if (!l) return;
                        const lw = f.widthOfTextAtSize(l, tam);
                        const x = it.x + PAD + (it.al === 'c' ? (larg - lw) / 2 : it.al === 'r' ? larg - lw : 0);
                        const yb = H - (it.y + PAD + BASE[it.fonte] * tam + k * tam * LINHA);
                        if (yb < y - tam * 0.2) return; // a linha passou da caixa (na tela ela fica escondida)
                        pg.drawText(l, { x, y: yb, size: tam, font: f, color: cor });
                    });
                }
            }
        }
        fala('Gravando o arquivo…');
        return saida.save();
    }
    /** As duas pontas da seta (no fim da linha), em pontos. */
    function pontaSeta(it) {
        const ang = Math.atan2(it.y2 - it.y1, it.x2 - it.x1), t = Math.max(9, it.esp * 4), a = Math.PI / 7;
        return [[it.x2 - t * Math.cos(ang - a), it.y2 - t * Math.sin(ang - a)], [it.x2 - t * Math.cos(ang + a), it.y2 - t * Math.sin(ang + a)]];
    }
    function baixarBytes(bytes, nome) {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        link.download = String(nome || 'documento').replace(/\.pdf$/i, '').replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '_').trim().slice(0, 100) + '.pdf';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 30000);
    }
    /** Imagem escolhida pelo usuário: reduz para no máximo 1800 px; PNG continua PNG (transparência), o resto vira JPG. */
    function prepararImagem(arquivo) {
        return new Promise((ok, falha) => {
            const img = new Image(), url = URL.createObjectURL(arquivo);
            img.onload = () => {
                const k = Math.min(1, 1800 / Math.max(img.width, img.height));
                const cv = document.createElement('canvas');
                cv.width = Math.max(1, Math.round(img.width * k)); cv.height = Math.max(1, Math.round(img.height * k));
                const cx = cv.getContext('2d');
                const png = /png|webp|gif/i.test(arquivo.type);
                if (!png) { cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); }
                cx.drawImage(img, 0, 0, cv.width, cv.height);
                URL.revokeObjectURL(url);
                let src = png ? cv.toDataURL('image/png') : cv.toDataURL('image/jpeg', 0.88);
                if (src.length > 5500000) src = cv.toDataURL('image/jpeg', 0.82); // a chave do servidor guarda até 6 milhões de caracteres
                ok({ src, w: cv.width, h: cv.height });
            };
            img.onerror = () => { URL.revokeObjectURL(url); falha(new Error('imagem')); };
            img.src = url;
        });
    }

    /* ================= editor em tela cheia ================= */
    function abrirEditor(ctx) {
        let estado = normalizar(ctx.dados);
        const imagens = ctx.imagens;
        const hist = [JSON.stringify(estado)];
        let hi = 0, sel = null, ferramenta = 'sel', paginaAtual = 0, editando = null, modalAtual = null, fechado = false;
        let sujo = false, timerSalvar = null, timerHist = null, copiado = null;
        let autoSalvar = localStorage.getItem('wf-pdf-auto') !== '0';
        let zoom = localStorage.getItem('wf-pdf-zoom') || 'largura';
        if (zoom !== 'largura') zoom = Number(zoom) > 0 ? Number(zoom) : 'largura';
        let escala = PT;
        // valores para os próximos itens (mudam com a barra de propriedades)
        const padrao = { fonte: 'sans', tam: 14, cor: '#111111', neg: false, ita: false, al: 'l', fundoTxt: null, traco: '#dc2626', esp: 2, preench: null, marca: '#f2c230' };
        const dpr = Math.min(2, window.devicePixelRatio || 1);

        const raiz = el('div', 'pe-raiz');
        raiz.setAttribute('role', 'dialog');
        raiz.setAttribute('aria-modal', 'true');
        raiz.setAttribute('aria-label', 'Editor de PDF');
        const ferr = (f, ic, nome, dica, tecla) => `<button type="button" data-f="${f}" title="${esc(dica)} (tecla ${tecla})"><span class="pe-ic">${ic}</span><span class="pe-tx">${nome}</span></button>`;
        const bot = (a, ic, nome, dica, cls = '') => `<button type="button" data-a="${a}" class="${cls}" title="${esc(dica)}"><span class="pe-ic">${ic}</span><span class="pe-tx">${nome}</span></button>`;
        raiz.innerHTML = `
            <div class="pe-topo">
                ${bot('fechar', '✕', 'Fechar', 'Fechar o editor e voltar ao quadro (Esc)', 'pe-sair')}
                <b class="pe-tit"></b>
                <span class="pe-salvo" aria-live="polite"></span>
                <span class="pe-esp"></span>
                <label class="pe-chk" title="Grava as mudanças sozinho, um segundo depois de cada edição"><input type="checkbox" data-a="auto"> Salvar sozinho</label>
                ${bot('salvar', '💾', 'Salvar', 'Salvar agora (Ctrl+S)')}
                ${bot('baixar', '⬇', 'Baixar PDF', 'Gerar e baixar o PDF com as edições', 'pe-ok')}
            </div>
            <div class="pe-barra" role="toolbar" aria-label="Ferramentas">
                <div class="pe-grupo"><span class="pe-rot">Ferramentas</span>
                    ${ferr('sel', '➤', 'Selecionar', 'Escolher, mover e mudar o tamanho dos itens', 'V')}
                    ${ferr('texto', 'T', 'Texto', 'Clique na página para escrever (ou arraste para escolher a largura)', 'T')}
                    ${ferr('cobrir', '✎', 'Cobrir e reescrever', 'Clique numa linha do PDF (ou arraste sobre um trecho): o texto original é coberto e você escreve por cima', 'C')}
                    ${ferr('marca', '▮', 'Marca-texto', 'Arraste sobre o texto para destacar', 'H')}
                    ${ferr('ret', '▭', 'Retângulo', 'Arraste para desenhar um retângulo', 'R')}
                    ${ferr('elipse', '◯', 'Elipse', 'Arraste para desenhar uma elipse', 'E')}
                    ${ferr('linha', '╱', 'Linha', 'Arraste para desenhar uma linha', 'L')}
                    ${ferr('seta', '➝', 'Seta', 'Arraste para desenhar uma seta', 'A')}
                    <label class="pe-btn" title="Pôr uma imagem (PNG ou JPG) na página atual (tecla I)"><span class="pe-ic">🖼</span><span class="pe-tx">Imagem</span><input type="file" data-in="img" accept="image/png,image/jpeg,image/webp" hidden></label>
                    ${bot('assinar', '✍', 'Assinatura', 'Desenhar uma assinatura e pôr na página atual (tecla S)')}
                </div>
                <div class="pe-grupo"><span class="pe-rot">Editar</span>
                    ${bot('desfazer', '↶', 'Desfazer', 'Desfazer (Ctrl+Z)')}
                    ${bot('refazer', '↷', 'Refazer', 'Refazer (Ctrl+Y)')}
                    ${bot('dupitem', '⧉', 'Duplicar', 'Duplicar o item escolhido (Ctrl+D)')}
                    ${bot('apagaitem', '🗑', 'Apagar', 'Apagar o item escolhido (Delete)')}
                </div>
                <div class="pe-grupo"><span class="pe-rot">Zoom</span>
                    <button type="button" data-a="menos" title="Diminuir (Ctrl + −)">−</button>
                    <select data-z="zoom" title="Zoom (Ctrl + roda do mouse)">
                        <option value="largura">Largura da tela</option>
                        ${ZOOMS.map(z => `<option value="${z}">${Math.round(z * 100)}%</option>`).join('')}
                    </select>
                    <button type="button" data-a="mais" title="Aumentar (Ctrl + +)">+</button>
                </div>
            </div>
            <div class="pe-props" aria-label="Propriedades"></div>
            <div class="pe-corpo">
                <aside class="pe-lado" aria-label="Páginas">
                    <div class="pe-lado-topo"><b>Páginas</b> <span class="pe-npag"></span><span class="pe-esp"></span><button type="button" data-a="lado" class="pe-mini-bt" title="Esconder o painel de páginas">«</button></div>
                    <div class="pe-lado-acoes">
                        <button type="button" data-a="girarE" title="Girar a página atual para a esquerda">↺</button>
                        <button type="button" data-a="girarD" title="Girar a página atual para a direita">↻</button>
                        <button type="button" data-a="duppag" title="Duplicar a página atual">⧉</button>
                        <button type="button" data-a="sobe" title="Subir a página atual">↑</button>
                        <button type="button" data-a="desce" title="Descer a página atual">↓</button>
                        <button type="button" data-a="apagapag" title="Excluir a página atual (dá para desfazer)">🗑</button>
                    </div>
                    <div class="pe-lado-acoes">
                        <button type="button" data-a="branca" title="Inserir uma página em branco depois da atual">＋ Em branco</button>
                        <label class="pe-btn" title="Juntar outro PDF depois da página atual"><span>⊕ Juntar PDF</span><input type="file" data-in="juntar" accept="application/pdf,.pdf" hidden></label>
                    </div>
                    <div class="pe-minis"></div>
                </aside>
                <button type="button" data-a="lado" class="pe-lado-abrir" title="Mostrar o painel de páginas">» Páginas</button>
                <main class="pe-paginas" tabindex="-1"><div class="pe-carregando">Abrindo o PDF…</div></main>
            </div>
            <div class="pe-rodape"><span class="pe-st-pag"></span><span class="pe-st-dica"></span></div>`;
        raiz.querySelector('.pe-tit').textContent = ctx.titulo || 'PDF';
        document.body.appendChild(raiz);
        document.documentElement.classList.add('pe-aberto');
        const $ = s => raiz.querySelector(s);
        const area = $('.pe-paginas'), minis = $('.pe-minis'), barraProps = $('.pe-props');
        $('[data-a="auto"]').checked = autoSalvar;
        $('[data-z="zoom"]').value = String(zoom);
        if (localStorage.getItem('wf-pdf-lado') === '0' || innerWidth < 700) raiz.classList.add('sem-lado'); // no celular o painel abre por cima, pelo botão

        /* ---------- PDFs abertos (pdf.js) e tamanho das páginas ---------- */
        const docs = new Map(), tamanhos = new Map(), textos = new Map();
        function abrirDoc(f) {
            if (!docs.has(f)) {
                docs.set(f, (async () => {
                    const lib = await pdfjs(), src = ctx.origem(f, estado.fontes[f]);
                    if (!src) throw Object.assign(new Error('sem-arquivo'), { status: 404 });
                    // arquivo no servidor: o pdf.js pede só os pedaços que precisa (Range), sem baixar tudo antes
                    const tarefa = lib.getDocument(src.url
                        ? { url: src.url, withCredentials: true, rangeChunkSize: 1 << 20, httpHeaders: meta('wf-dono') ? { 'X-WF-Dono': meta('wf-dono') } : {} }
                        : { data: src.data });
                    return tarefa.promise;
                })());
            }
            return docs.get(f);
        }
        async function lerTamanhos(f, aoProgresso) {
            const doc = await abrirDoc(f);
            let lote = [];
            for (let i = 0; i < doc.numPages; i++) {
                if (tamanhos.has(f + ':' + i)) continue;
                lote.push(doc.getPage(i + 1).then(pg => {
                    const vp = pg.getViewport({ scale: 1, rotation: 0 });
                    tamanhos.set(f + ':' + i, { w: vp.width, h: vp.height, r: pg.rotate || 0 });
                }));
                if (lote.length >= 24) { await Promise.all(lote); lote = []; if (aoProgresso) aoProgresso(i / doc.numPages); }
            }
            await Promise.all(lote);
            return doc.numPages;
        }
        function tamPagina(p) {
            if (p.branca) return { w: p.branca[0], h: p.branca[1] };
            const s = tamanhos.get(p.f + ':' + p.o) || { w: 595, h: 842, r: 0 };
            return ((s.r + p.rot) % 180) ? { w: s.h, h: s.w } : { w: s.w, h: s.h };
        }

        /* ---------- fila de desenho (uma página por vez, as visíveis primeiro) ---------- */
        const fila = [];
        let desenhando = false;
        function enfileirar(t) {
            const ja = fila.find(x => x.chave === t.chave);
            if (ja) { ja.prio = Math.min(ja.prio, t.prio); ja.valida = t.valida; ja.fazer = t.fazer; return; }
            fila.push(t);
            proxima();
        }
        async function proxima() {
            if (desenhando || fechado) return;
            fila.sort((a, b) => a.prio - b.prio);
            const t = fila.shift();
            if (!t) return;
            if (!t.valida()) { proxima(); return; }
            desenhando = true;
            try { await t.fazer(); } catch (x) { if (!fechado) console.warn('[Editor de PDF]', x); } finally { desenhando = false; proxima(); }
        }
        async function renderizar(p, k) {
            const doc = await abrirDoc(p.f), pg = await doc.getPage(p.o + 1);
            const t = tamPagina(p);
            k = Math.min(k, Math.sqrt(16e6 / (t.w * t.h))); // no máximo ~16 milhões de pixels por página
            const vp = pg.getViewport({ scale: k, rotation: ((pg.rotate || 0) + p.rot) % 360 });
            const cv = document.createElement('canvas');
            cv.width = Math.max(1, Math.ceil(vp.width)); cv.height = Math.max(1, Math.ceil(vp.height));
            // fundo transparente: a cor de fundo escolhida aparece por trás do conteúdo da página
            await pg.render({ canvasContext: cv.getContext('2d'), viewport: vp, background: 'rgba(0,0,0,0)' }).promise;
            pg.cleanup();
            return cv;
        }
        // páginas já desenhadas (as menos usadas saem quando passa de ~60 milhões de pixels)
        const cache = new Map();
        let pixels = 0;
        function guardar(chave, cv) {
            if (cache.has(chave)) pixels -= cache.get(chave).width * cache.get(chave).height;
            cache.delete(chave); cache.set(chave, cv); pixels += cv.width * cv.height;
            for (const [k, c] of cache) {
                if (pixels < 60e6) break;
                if (c.isConnected) continue;
                cache.delete(k); pixels -= c.width * c.height; c.width = c.height = 0;
            }
        }
        function copiaDe(cv) {
            if (!cv.isConnected) return cv;
            const n = document.createElement('canvas');
            n.width = cv.width; n.height = cv.height;
            n.getContext('2d').drawImage(cv, 0, 0);
            return n;
        }
        const chaveDe = (p, k) => `${p.f}:${p.o}:${p.rot}:${k.toFixed(3)}`;

        /* ---------- páginas na tela ---------- */
        let vistas = [];
        const visiveis = new Set();
        const io = new IntersectionObserver(entradas => {
            entradas.forEach(e => {
                const pi = Number(e.target.dataset.pg);
                if (e.isIntersecting) { visiveis.add(pi); garantirCanvas(pi); } else { visiveis.delete(pi); soltarCanvas(pi); }
            });
        }, { root: area, rootMargin: '900px 0px' });
        const ioMini = new IntersectionObserver(entradas => {
            entradas.forEach(e => { if (e.isIntersecting) garantirMini(Number(e.target.dataset.pg)); });
        }, { root: minis, rootMargin: '300px 0px' });

        function garantirCanvas(pi) {
            const v = vistas[pi], p = estado.paginas[pi];
            if (!v || !p || p.branca) return;
            const k = escala * dpr, chave = chaveDe(p, k);
            if (v.chave === chave) return;
            if (cache.has(chave)) { colocar(v, copiaDe(cache.get(chave)), chave); return; }
            enfileirar({
                chave: 'pg:' + pi + ':' + chave, prio: Math.abs(pi - paginaAtual),
                valida: () => vistas[pi] === v && visiveis.has(pi) && chaveDe(estado.paginas[pi], escala * dpr) === chave,
                fazer: async () => {
                    const cv = cache.get(chave) || await renderizar(p, k);
                    guardar(chave, cv);
                    if (vistas[pi] === v && visiveis.has(pi) && chaveDe(estado.paginas[pi], escala * dpr) === chave) colocar(v, copiaDe(cv), chave);
                },
            });
        }
        function colocar(v, cv, chave) {
            cv.className = 'pe-cv';
            if (v.cv) v.cv.replaceWith(cv); else v.caixa.prepend(cv);
            v.cv = cv; v.chave = chave;
            v.caixa.classList.remove('vazia');
        }
        function soltarCanvas(pi) {
            const v = vistas[pi];
            if (!v || !v.cv) return;
            v.cv.remove(); v.cv = null; v.chave = '';
            v.caixa.classList.add('vazia');
        }
        function garantirMini(pi) {
            const v = vistas[pi], p = estado.paginas[pi];
            if (!v || !p || p.branca || v.miniPronta) return;
            const t = tamPagina(p), k = (108 / t.w) * dpr, chave = chaveDe(p, k);
            const pronto = cv => { const c = copiaDe(cv); c.className = 'pe-mini-cv'; v.miniCv.replaceWith(c); v.miniCv = c; v.miniPronta = true; };
            if (cache.has(chave)) { pronto(cache.get(chave)); return; }
            enfileirar({
                chave: 'mi:' + pi + ':' + chave, prio: 1000 + Math.abs(pi - paginaAtual),
                valida: () => vistas[pi] === v && !v.miniPronta,
                fazer: async () => { const cv = cache.get(chave) || await renderizar(p, k); guardar(chave, cv); if (vistas[pi] === v && !v.miniPronta) pronto(cv); },
            });
        }

        function calcularEscala() {
            if (zoom !== 'largura') return Number(zoom) * PT;
            const maior = Math.max(200, ...estado.paginas.map(p => tamPagina(p).w));
            return limitar((area.clientWidth - 90) / maior, 0.2, 1.5 * PT); // ajusta à largura, mas sem passar de 150%
        }
        /** Monta a lista de páginas (os desenhos vêm do cache, então é rápido). */
        function montar() {
            io.disconnect(); ioMini.disconnect(); visiveis.clear();
            fila.length = 0;
            escala = calcularEscala();
            area.replaceChildren(); minis.replaceChildren();
            vistas = estado.paginas.map((p, pi) => {
                const caixa = el('div', 'pe-pg vazia' + (p.branca ? ' branca' : ''));
                caixa.dataset.pg = pi;
                const camada = el('div', 'pe-camada');
                const num = el('span', 'pe-num'); num.textContent = pi + 1;
                caixa.append(camada, num);
                area.append(caixa);
                const mini = el('div', 'pe-mini');
                mini.dataset.pg = pi; mini.draggable = true;
                mini.title = `Página ${pi + 1}: clique para ir até ela, arraste para mudar de lugar`;
                const miniCv = el('div', 'pe-mini-cv');
                const ac = el('div', 'pe-mini-ac', '<button type="button" data-m="girar" title="Girar para a direita">↻</button><button type="button" data-m="dup" title="Duplicar">⧉</button><button type="button" data-m="apagar" title="Excluir">✕</button>');
                const n = el('span', 'pe-mini-n'); n.textContent = pi + 1;
                mini.append(miniCv, ac, n);
                minis.append(mini);
                const v = { caixa, camada, cv: null, chave: '', mini, miniCv, miniPronta: false };
                dimensionar(v, p);
                return v;
            });
            vistas.forEach((v, pi) => { desenharItens(pi); io.observe(v.caixa); ioMini.observe(v.mini); });
            $('.pe-npag').textContent = `(${estado.paginas.length})`;
            paginaAtual = limitar(paginaAtual, 0, estado.paginas.length - 1);
            marcarAtual();
        }
        function dimensionar(v, p) {
            const t = tamPagina(p);
            Object.assign(v.caixa.style, { width: t.w * escala + 'px', height: t.h * escala + 'px', background: p.fundo || '#fff' });
            v.mini.style.setProperty('--mh', (108 * t.h / t.w) + 'px');
            v.mini.style.setProperty('--mf', p.fundo || '#fff');
        }
        /** Mudou o zoom: estica o que já está desenhado e redesenha as páginas visíveis na nitidez nova. */
        function aplicarZoom(novo, foco) {
            const antes = escala, topo = area.scrollTop, alt = foco ? foco.y : area.clientHeight / 2, esq = area.scrollLeft, fx = foco ? foco.x : area.clientWidth / 2;
            zoom = novo;
            localStorage.setItem('wf-pdf-zoom', String(zoom));
            const s = $('[data-z="zoom"]');
            if (![...s.options].some(o => o.value === String(zoom))) { const o = el('option'); o.value = String(zoom); o.textContent = Math.round(zoom * 100) + '%'; s.append(o); }
            s.value = String(zoom);
            escala = calcularEscala();
            vistas.forEach((v, pi) => { dimensionar(v, estado.paginas[pi]); desenharItens(pi); });
            if (editando) { posItem(editando.el, editando.it); estiloTexto(editando.ta, editando.it); }
            const f = escala / antes;
            area.scrollTop = (topo + alt) * f - alt;
            area.scrollLeft = (esq + fx) * f - fx;
            clearTimeout(aplicarZoom.t);
            aplicarZoom.t = setTimeout(() => visiveis.forEach(garantirCanvas), 180);
        }
        function passoZoom(d, foco) {
            const atual = escala / PT;
            const lista = d > 0 ? ZOOMS.filter(z => z > atual + 0.01) : ZOOMS.filter(z => z < atual - 0.01).reverse();
            if (lista.length) aplicarZoom(lista[0], foco);
        }

        /* ---------- itens por cima da página ---------- */
        function estiloTexto(e, it) {
            Object.assign(e.style, {
                color: it.cor, fontSize: it.tam * escala + 'px', lineHeight: String(LINHA), fontFamily: FAMILIAS[it.fonte],
                fontWeight: it.neg ? '700' : '400', fontStyle: it.ita ? 'italic' : 'normal',
                textAlign: { l: 'left', c: 'center', r: 'right' }[it.al], padding: PAD * escala + 'px',
            });
        }
        function posItem(e, it) {
            const c = caixaDe(it), m = it.t === 'linha' ? Math.max(6, it.esp * escala) : 0;
            Object.assign(e.style, { left: c.x * escala - m + 'px', top: c.y * escala - m + 'px', width: c.w * escala + 2 * m + 'px', height: c.h * escala + 2 * m + 'px' });
        }
        function pintarLinha(e, it) {
            const c = caixaDe(it), m = Math.max(6, it.esp * escala);
            const p = (x, y) => `${(x - c.x) * escala + m},${(y - c.y) * escala + m}`;
            const seta = it.seta ? pontaSeta(it).map(([x, y]) => `<polyline points="${p(x, y)} ${p(it.x2, it.y2)}" />`).join('') : '';
            e.innerHTML = `<svg width="100%" height="100%" stroke="${it.cor}" stroke-width="${Math.max(1, it.esp * escala)}" stroke-linecap="round" fill="none"><polyline points="${p(it.x1, it.y1)} ${p(it.x2, it.y2)}" />${seta}</svg>`;
        }
        function elItem(it, pi, ii) {
            const e = el('div', 'pe-item pe-' + it.t);
            const escolhido = sel && sel.p === pi && sel.i === ii;
            if (escolhido) e.classList.add('sel');
            e.dataset.i = ii;
            if (it.t === 'texto') {
                e.style.background = it.fundo || 'transparent';
                const t = el('div', 'pe-txt');
                estiloTexto(t, it);
                t.textContent = it.txt;
                e.append(t);
            } else if (it.t === 'img') {
                const im = el('img'); im.src = imagens[it.k] || ''; im.alt = ''; im.draggable = false;
                e.append(im);
            } else if (it.t === 'ret' || it.t === 'elipse') {
                Object.assign(e.style, { border: it.esp ? `${it.esp * escala}px solid ${it.cor}` : '0', background: it.fundo || 'transparent' });
            } else if (it.t === 'marca') e.style.background = it.cor;
            else if (it.t === 'linha') pintarLinha(e, it);
            posItem(e, it);
            if (escolhido) {
                (it.t === 'linha' ? ['p1', 'p2'] : ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w']).forEach(h => {
                    const a = el('span', 'pe-alca pe-h-' + h); a.dataset.h = h;
                    if (it.t === 'linha') {
                        const c = caixaDe(it), m = Math.max(6, it.esp * escala);
                        const [x, y] = h === 'p1' ? [it.x1, it.y1] : [it.x2, it.y2];
                        Object.assign(a.style, { left: (x - c.x) * escala + m + 'px', top: (y - c.y) * escala + m + 'px' });
                    }
                    e.append(a);
                });
            }
            return e;
        }
        function desenharItens(pi) {
            const v = vistas[pi], p = estado.paginas[pi];
            if (!v || !p) return;
            const ed = editando && editando.p === pi ? editando : null;
            v.camada.replaceChildren(...p.itens.map((it, ii) => (ed && ed.i === ii ? ed.el : elItem(it, pi, ii))));
        }
        const itemSel = () => (sel && estado.paginas[sel.p] ? estado.paginas[sel.p].itens[sel.i] || null : null);
        /** Texto maior que a caixa (letra aumentou, caixa ficou estreita): a caixa cresce para baixo até caber. */
        function caberTexto(pi, ii) {
            const it = estado.paginas[pi] && estado.paginas[pi].itens[ii], v = vistas[pi];
            const e = v && v.camada.querySelector(`.pe-item[data-i="${ii}"]`), t = e && e.querySelector('.pe-txt');
            if (!it || it.t !== 'texto' || !t || t.scrollHeight <= t.clientHeight + 1) return;
            it.h = t.scrollHeight / escala;
            posItem(e, it);
        }
        function escolher(pi, ii) {
            const antes = sel;
            sel = ii === null || ii === undefined ? null : { p: pi, i: ii };
            if (antes && antes.p !== pi) desenharItens(antes.p);
            desenharItens(pi);
            montarProps();
            atualizarBotoes();
        }

        /* ---------- histórico (desfazer/refazer) e salvamento ---------- */
        const assinatura = () => JSON.stringify(estado.paginas.map(p => [p.f, p.o, p.rot, p.branca, p.fundo]));
        function registrar() {
            clearTimeout(timerHist); timerHist = null;
            const s = JSON.stringify(estado);
            if (s === hist[hi]) { atualizarBotoes(); return; }
            hist.length = hi + 1;
            hist.push(s);
            if (hist.length > 200) hist.shift();
            hi = hist.length - 1;
            sujo = true;
            agendarSalvar();
            atualizarBotoes();
        }
        const registrarLogo = () => { clearTimeout(timerHist); timerHist = setTimeout(registrar, 400); atualizarBotoes(); };
        function voltar(delta) {
            if (editando) terminarEdicao();
            registrar();
            const i = hi + delta;
            if (i < 0 || i >= hist.length) return;
            const antes = assinatura();
            hi = i;
            estado = JSON.parse(hist[hi]);
            sel = null;
            if (assinatura() !== antes) montar(); else vistas.forEach((_, pi) => desenharItens(pi));
            sujo = true;
            agendarSalvar();
            montarProps(); atualizarBotoes();
        }
        function agendarSalvar() {
            atualizarStatus();
            if (!autoSalvar) return;
            clearTimeout(timerSalvar);
            timerSalvar = setTimeout(salvarAgora, 1000);
        }
        function salvarAgora() {
            clearTimeout(timerSalvar); timerSalvar = null;
            if (timerHist) registrar();
            if (!sujo) return;
            ctx.aoSalvar(clone(estado));
            sujo = false;
            atualizarStatus();
        }
        function atualizarStatus() {
            const st = $('.pe-salvo');
            let txt, cls = '';
            if (ctx.soVer) { txt = 'Só visualização: as mudanças não são gravadas'; cls = 'aviso'; }
            else if (sujo || timerHist) { txt = autoSalvar ? 'Salvando…' : '● Mudanças não salvas'; cls = autoSalvar ? '' : 'aviso'; }
            else {
                const s = ctx.estadoSalvo();
                txt = s === 'erro' ? '⚠ Erro ao salvar, tentando de novo' : s === 'ok' ? '✓ Tudo salvo' : 'Salvando…';
                cls = s === 'erro' ? 'erro' : s === 'ok' ? 'ok' : '';
            }
            if (st.textContent !== txt) st.textContent = txt;
            st.className = 'pe-salvo ' + cls;
        }
        const relogio = setInterval(atualizarStatus, 700);
        function atualizarBotoes() {
            $('[data-a="desfazer"]').disabled = hi <= 0 && !timerHist;
            $('[data-a="refazer"]').disabled = hi >= hist.length - 1;
            $('[data-a="dupitem"]').disabled = !itemSel();
            $('[data-a="apagaitem"]').disabled = !itemSel();
            raiz.querySelectorAll('[data-f]').forEach(b => b.classList.toggle('on', b.dataset.f === ferramenta));
        }

        /* ---------- página atual ---------- */
        function marcarAtual() {
            vistas.forEach((v, pi) => { v.caixa.classList.toggle('atual', pi === paginaAtual); v.mini.classList.toggle('atual', pi === paginaAtual); });
            const v = vistas[paginaAtual];
            if (v && !raiz.classList.contains('sem-lado')) {
                const r = v.mini.getBoundingClientRect(), rm = minis.getBoundingClientRect();
                if (r.top < rm.top || r.bottom > rm.bottom) minis.scrollTop += r.top < rm.top ? r.top - rm.top - 8 : r.bottom - rm.bottom + 8;
            }
            const t = estado.paginas[paginaAtual] ? tamPagina(estado.paginas[paginaAtual]) : null;
            $('.pe-st-pag').textContent = estado.paginas.length ? `Página ${paginaAtual + 1} de ${estado.paginas.length}` + (t ? ` · ${Math.round(t.w * 0.3528)} × ${Math.round(t.h * 0.3528)} mm` : '') : '';
            const f = barraProps.querySelector('[data-p="fundoPag"]');
            if (f) f.value = (estado.paginas[paginaAtual] && estado.paginas[paginaAtual].fundo) || '#ffffff';
        }
        function definirAtual(pi) { if (pi !== paginaAtual) { paginaAtual = pi; marcarAtual(); } }
        function irPara(pi) {
            const v = vistas[pi];
            if (!v) return;
            area.scrollTop = v.caixa.offsetTop - 16;
            definirAtual(pi);
        }
        let rolando = false;
        area.addEventListener('scroll', () => {
            if (rolando) return;
            rolando = true;
            requestAnimationFrame(() => {
                rolando = false;
                const meio = area.scrollTop + area.clientHeight / 3;
                let ini = 0, fim = vistas.length - 1, melhor = 0;
                while (ini <= fim) { // busca binária: serve para PDFs com milhares de páginas
                    const m = (ini + fim) >> 1;
                    if (vistas[m].caixa.offsetTop <= meio) { melhor = m; ini = m + 1; } else fim = m - 1;
                }
                if (vistas.length) definirAtual(melhor);
            });
        }, { passive: true });
        area.addEventListener('wheel', e => {
            if (!(e.ctrlKey || e.metaKey)) return;
            e.preventDefault();
            const r = area.getBoundingClientRect();
            passoZoom(e.deltaY < 0 ? 1 : -1, { x: e.clientX - r.left, y: e.clientY - r.top });
        }, { passive: false });
        const ro = new ResizeObserver(() => { if (zoom === 'largura' && vistas.length && Math.abs(calcularEscala() - escala) > 0.01) aplicarZoom('largura'); });
        ro.observe(area);

        /* ---------- ferramentas ---------- */
        const DICAS = {
            sel: 'Clique num item para escolher; arraste para mover e use as alças para mudar o tamanho. Clique de novo (ou dois cliques) num texto para escrever.',
            texto: 'Clique onde o texto deve começar e escreva. Arraste para escolher a largura da caixa.',
            cobrir: 'Clique numa linha do PDF (ou arraste sobre um trecho): ela é coberta com a cor do fundo e o texto aparece para você reescrever.',
            marca: 'Arraste sobre o texto para destacar.',
            ret: 'Arraste para desenhar o retângulo.', elipse: 'Arraste para desenhar a elipse.',
            linha: 'Arraste do começo ao fim da linha.', seta: 'Arraste do começo até a ponta da seta.',
        };
        function usarFerramenta(f) {
            if (editando) terminarEdicao();
            ferramenta = f;
            raiz.dataset.ferramenta = f;
            $('.pe-st-dica').textContent = DICAS[f] || '';
            atualizarBotoes();
            montarProps();
        }
        function pontoEm(e, pi) {
            const r = vistas[pi].caixa.getBoundingClientRect();
            return { x: (e.clientX - r.left) / escala, y: (e.clientY - r.top) / escala };
        }
        function arrastar(e, aoMover, aoSoltar) {
            const x0 = e.clientX, y0 = e.clientY;
            let andou = false;
            const mover = ev => {
                if (!andou && Math.hypot(ev.clientX - x0, ev.clientY - y0) < 3) return;
                andou = true;
                aoMover(ev, (ev.clientX - x0) / escala, (ev.clientY - y0) / escala);
            };
            const soltar = ev => {
                removeEventListener('pointermove', mover); removeEventListener('pointerup', soltar); removeEventListener('pointercancel', soltar);
                aoSoltar(ev, andou);
            };
            addEventListener('pointermove', mover);
            addEventListener('pointerup', soltar);
            addEventListener('pointercancel', soltar);
        }
        function novoItem(pi, it, editar) {
            const p = estado.paginas[pi];
            p.itens.push(it);
            const ii = p.itens.length - 1;
            if (ferramenta !== 'sel') usarFerramenta('sel');
            escolher(pi, ii);
            if (editar) editarTexto(pi, ii, true); else registrar();
            return ii;
        }
        area.addEventListener('pointerdown', e => {
            if (e.button !== 0) return;
            const caixa = e.target.closest('.pe-pg');
            if (editando && e.target.closest('.pe-ta')) return; // clicando dentro do texto que está sendo escrito
            if (editando) terminarEdicao();
            if (!caixa) { if (sel) escolher(sel.p, null); return; }
            const pi = Number(caixa.dataset.pg), p = estado.paginas[pi];
            definirAtual(pi);
            const pt = pontoEm(e, pi), tp = tamPagina(p);
            const itemEl = e.target.closest('.pe-item'), alca = e.target.closest('.pe-alca');
            const ii = itemEl ? Number(itemEl.dataset.i) : null;
            e.preventDefault();
            if (ferramenta === 'sel' || (ferramenta === 'texto' && itemEl && p.itens[ii] && p.itens[ii].t === 'texto')) {
                if (ii === null || !p.itens[ii]) { escolher(pi, null); return; }
                const jaEra = sel && sel.p === pi && sel.i === ii;
                escolher(pi, ii);
                const it = p.itens[ii], a = clone(it);
                const elDoItem = () => vistas[pi].camada.querySelector(`.pe-item[data-i="${ii}"]`);
                arrastar(e, (ev, dx, dy) => {
                    if (alca) redimensionar(it, a, alca.dataset.h, dx, dy, ev.shiftKey, tp);
                    else moverItem(it, a, dx, dy, tp);
                    const x = elDoItem();
                    if (x) x.replaceWith(elItem(it, pi, ii));
                }, (ev, andou) => {
                    if (andou) { if (alca && it.t === 'texto') caberTexto(pi, ii); registrar(); }
                    else if (jaEra && it.t === 'texto' && !alca) editarTexto(pi, ii, false);
                });
                return;
            }
            // criar arrastando: mostra um retângulo provisório
            const fantasma = el('div', 'pe-fantasma' + (ferramenta === 'marca' ? ' marca' : ''));
            vistas[pi].camada.append(fantasma);
            let fim = pt;
            arrastar(e, ev => {
                fim = pontoEm(ev, pi);
                fim.x = limitar(fim.x, 0, tp.w); fim.y = limitar(fim.y, 0, tp.h);
                Object.assign(fantasma.style, { left: Math.min(pt.x, fim.x) * escala + 'px', top: Math.min(pt.y, fim.y) * escala + 'px', width: Math.abs(fim.x - pt.x) * escala + 'px', height: Math.abs(fim.y - pt.y) * escala + 'px' });
            }, (ev, andou) => {
                fantasma.remove();
                const r = { x: Math.min(pt.x, fim.x), y: Math.min(pt.y, fim.y), w: Math.abs(fim.x - pt.x), h: Math.abs(fim.y - pt.y) };
                const grande = andou && r.w > 4 && r.h > 4;
                if (ferramenta === 'texto') {
                    const tam = padrao.tam;
                    const w = andou && r.w > 20 ? r.w : Math.max(60, Math.min(260, tp.w - pt.x - 6));
                    const x = andou && r.w > 20 ? r.x : limitar(pt.x - PAD, 0, Math.max(0, tp.w - w));
                    const y = andou && r.w > 20 ? r.y : limitar(pt.y - PAD - tam * 0.6, 0, Math.max(0, tp.h - tam * LINHA - 2 * PAD));
                    novoItem(pi, normItem({ t: 'texto', x, y, w, h: Math.max(tam * LINHA + 2 * PAD, grande ? r.h : 0), txt: '',
                        tam, cor: padrao.cor, fonte: padrao.fonte, neg: padrao.neg, ita: padrao.ita, al: padrao.al, fundo: padrao.fundoTxt }), true);
                } else if (ferramenta === 'cobrir') {
                    cobrir(pi, andou && r.w > 3 && r.h > 3 ? r : null, pt);
                } else if (ferramenta === 'marca') {
                    novoItem(pi, normItem({ t: 'marca', ...(grande ? r : { x: pt.x - 60, y: pt.y - 8, w: 120, h: 16 }), cor: padrao.marca }));
                } else if (ferramenta === 'ret' || ferramenta === 'elipse') {
                    novoItem(pi, normItem({ t: ferramenta, ...(grande ? r : { x: pt.x - 60, y: pt.y - 40, w: 120, h: 80 }), cor: padrao.traco, esp: padrao.esp, fundo: padrao.preench }));
                } else if (ferramenta === 'linha' || ferramenta === 'seta') {
                    const curta = !andou || Math.hypot(fim.x - pt.x, fim.y - pt.y) < 5;
                    novoItem(pi, normItem({ t: 'linha', x1: pt.x, y1: pt.y, x2: curta ? Math.min(tp.w, pt.x + 120) : fim.x, y2: curta ? pt.y : fim.y, cor: padrao.traco, esp: padrao.esp, seta: ferramenta === 'seta' }));
                }
            });
            if (ferramenta === 'linha' || ferramenta === 'seta') fantasma.classList.add('linha');
        });
        area.addEventListener('dblclick', e => {
            const itemEl = e.target.closest('.pe-item.pe-texto'), caixa = e.target.closest('.pe-pg');
            if (itemEl && caixa && !editando) editarTexto(Number(caixa.dataset.pg), Number(itemEl.dataset.i), false);
        });
        function moverItem(it, a, dx, dy, tp) {
            if (it.t === 'linha') {
                const c = caixaDe(a);
                dx = limitar(dx, -c.x, Math.max(-c.x, tp.w - c.x - c.w)); dy = limitar(dy, -c.y, Math.max(-c.y, tp.h - c.y - c.h));
                it.x1 = a.x1 + dx; it.x2 = a.x2 + dx; it.y1 = a.y1 + dy; it.y2 = a.y2 + dy;
                return;
            }
            it.x = limitar(a.x + dx, Math.min(0, tp.w - a.w), Math.max(0, tp.w - a.w));
            it.y = limitar(a.y + dy, Math.min(0, tp.h - a.h), Math.max(0, tp.h - a.h));
        }
        function redimensionar(it, a, h, dx, dy, livre, tp) {
            if (it.t === 'linha') {
                if (h === 'p1') { it.x1 = limitar(a.x1 + dx, 0, tp.w); it.y1 = limitar(a.y1 + dy, 0, tp.h); } else { it.x2 = limitar(a.x2 + dx, 0, tp.w); it.y2 = limitar(a.y2 + dy, 0, tp.h); }
                return;
            }
            const min = 6;
            let { x, y, w, h: alt } = a;
            if (h.includes('e')) w = Math.max(min, a.w + dx);
            if (h.includes('s')) alt = Math.max(min, a.h + dy);
            if (h.includes('w')) { w = Math.max(min, a.w - dx); x = a.x + a.w - w; }
            if (h.includes('n')) { alt = Math.max(min, a.h - dy); y = a.y + a.h - alt; }
            // imagem pelos cantos: mantém a proporção (Shift solta)
            if (it.t === 'img' && h.length === 2 && !livre) {
                const k = a.w / a.h;
                if (w / alt > k) w = alt * k; else alt = w / k;
                if (h.includes('w')) x = a.x + a.w - w;
                if (h.includes('n')) y = a.y + a.h - alt;
            }
            Object.assign(it, { x, y, w, h: alt });
        }

        /* ---------- escrever texto direto na página ---------- */
        function editarTexto(pi, ii, selecionarTudo) {
            if (editando) terminarEdicao();
            const it = estado.paginas[pi] && estado.paginas[pi].itens[ii];
            if (!it || it.t !== 'texto') return;
            sel = { p: pi, i: ii };
            const e = el('div', 'pe-item pe-texto sel editando');
            e.dataset.i = ii;
            e.style.background = it.fundo || 'transparent';
            posItem(e, it);
            const ta = el('textarea', 'pe-ta');
            ta.value = it.txt; ta.spellcheck = true; ta.lang = 'pt-BR';
            ta.setAttribute('aria-label', 'Texto da caixa');
            estiloTexto(ta, it);
            e.append(ta);
            editando = { p: pi, i: ii, it, el: e, ta };
            desenharItens(pi);
            ta.focus({ preventScroll: true });
            if (selecionarTudo) ta.select(); else ta.setSelectionRange(ta.value.length, ta.value.length);
            const crescer = () => { if (ta.scrollHeight > ta.clientHeight + 1) { it.h = ta.scrollHeight / escala; posItem(e, it); } };
            ta.addEventListener('input', () => { it.txt = ta.value; crescer(); registrarLogo(); });
            crescer();
            montarProps();
            atualizarBotoes();
        }
        function terminarEdicao() {
            if (!editando) return;
            const { p, i, it, ta } = editando;
            editando = null;
            it.txt = ta.value.replace(/\s+$/, '');
            // caixa vazia sem cobertura não serve para nada: sai
            if (!it.txt && !it.fundo) {
                const lista = estado.paginas[p] && estado.paginas[p].itens;
                if (lista && lista[i] === it) lista.splice(i, 1);
                sel = null;
            }
            desenharItens(p);
            registrar();
            montarProps();
            atualizarBotoes();
        }

        /* ---------- cobrir e reescrever ---------- */
        async function textoDaPagina(p) {
            const chave = `${p.f}:${p.o}:${p.rot}`;
            if (textos.has(chave)) return textos.get(chave);
            const doc = await abrirDoc(p.f), pg = await doc.getPage(p.o + 1);
            const vp = pg.getViewport({ scale: 1, rotation: ((pg.rotate || 0) + p.rot) % 360 });
            const tc = await pg.getTextContent();
            const lista = [];
            tc.items.forEach(t => {
                if (!t.str || !t.str.trim()) return;
                const [a, b, , , e, f] = t.transform, alt = Math.hypot(t.transform[2], t.transform[3]) || Math.abs(t.height) || 10;
                if (Math.abs(b) > Math.abs(a) * 0.05) return; // texto inclinado ou vertical: fica de fora
                const [x1, y1] = vp.convertToViewportPoint(e, f - alt * 0.22), [x2, y2] = vp.convertToViewportPoint(e + t.width, f + alt * 0.8);
                const [bx, by] = vp.convertToViewportPoint(e, f);
                const fam = ((tc.styles[t.fontName] || {}).fontFamily || '').toLowerCase();
                const r = { x: Math.min(x1, x2), y: Math.min(y1, y2), w: Math.abs(x2 - x1), h: Math.abs(y2 - y1), str: t.str, alt, bx, by,
                    fonte: /mono|courier/.test(fam) ? 'mono' : /serif/.test(fam) && !/sans/.test(fam) ? 'serif' : 'sans' };
                if (r.h > r.w * 3 && t.str.length > 2) return;
                lista.push(r);
            });
            textos.set(chave, lista);
            return lista;
        }
        /** Cor que mais aparece na borda do trecho (o fundo) e a mais escura de dentro (a letra). */
        function coresDoTrecho(pi, r) {
            const fundoPag = estado.paginas[pi].fundo || '#ffffff', v = vistas[pi], t = tamPagina(estado.paginas[pi]);
            if (!v || !v.cv) return { fundo: fundoPag, tinta: '#111111' };
            const kx = v.cv.width / t.w, ky = v.cv.height / t.h;
            const x = Math.max(0, Math.floor(r.x * kx)), y = Math.max(0, Math.floor(r.y * ky));
            const w = Math.min(v.cv.width - x, Math.ceil(r.w * kx)), h = Math.min(v.cv.height - y, Math.ceil(r.h * ky));
            if (w < 2 || h < 2) return { fundo: fundoPag, tinta: '#111111' };
            let d;
            try { d = v.cv.getContext('2d').getImageData(x, y, w, h).data; } catch { return { fundo: fundoPag, tinta: '#111111' }; }
            const px = (xx, yy) => { const i = (yy * w + xx) * 4; return [d[i], d[i + 1], d[i + 2], d[i + 3]]; };
            const conta = new Map();
            const borda = (xx, yy) => {
                const [R, G, B, A] = px(xx, yy);
                const k = A < 40 ? fundoPag : rgbHex(Math.round(R / 8) * 8, Math.round(G / 8) * 8, Math.round(B / 8) * 8);
                conta.set(k, (conta.get(k) || 0) + 1);
            };
            for (let xx = 0; xx < w; xx++) { borda(xx, 0); borda(xx, h - 1); }
            for (let yy = 0; yy < h; yy++) { borda(0, yy); borda(w - 1, yy); }
            let fundo = fundoPag, max = 0;
            conta.forEach((n, k) => { if (n > max) { max = n; fundo = k; } });
            if (/^#f[89a-f]f[89a-f]f[89a-f]$/i.test(fundo)) fundo = '#ffffff';
            let tinta = '#111111', menor = 999;
            for (let yy = 0; yy < h; yy += 2) for (let xx = 0; xx < w; xx += 2) {
                const [R, G, B, A] = px(xx, yy);
                if (A < 160) continue;
                const l = 0.3 * R + 0.59 * G + 0.11 * B;
                if (l < menor) { menor = l; tinta = rgbHex(R, G, B); }
            }
            if (menor > 170) tinta = '#111111';
            return { fundo, tinta };
        }
        async function cobrir(pi, r, pt) {
            const p = estado.paginas[pi];
            let linhas = [];
            if (!p.branca) { try { linhas = await textoDaPagina(p); } catch (x) { console.warn('[Editor de PDF]', x); } }
            let alvo = r;
            if (!alvo) {
                const hit = linhas.find(l => pt.x >= l.x - 1 && pt.x <= l.x + l.w + 1 && pt.y >= l.y - 1 && pt.y <= l.y + l.h + 1);
                if (!hit) { aviso('Clique em cima de um texto do PDF, ou arraste sobre o trecho que quer cobrir.'); return; }
                // junta os pedaços da mesma linha que estão encostados
                const grupo = [hit];
                for (let mudou = true; mudou;) {
                    mudou = false;
                    const gx1 = Math.min(...grupo.map(g => g.x)), gx2 = Math.max(...grupo.map(g => g.x + g.w));
                    linhas.forEach(l => {
                        if (grupo.includes(l) || Math.abs(l.by - hit.by) > hit.alt * 0.35) return;
                        if (l.x <= gx2 + hit.alt * 1.2 && l.x + l.w >= gx1 - hit.alt * 1.2) { grupo.push(l); mudou = true; }
                    });
                }
                const x1 = Math.min(...grupo.map(g => g.x)), y1 = Math.min(...grupo.map(g => g.y));
                alvo = { x: x1 - 1, y: y1 - 1, w: Math.max(...grupo.map(g => g.x + g.w)) - x1 + 2, h: Math.max(...grupo.map(g => g.y + g.h)) - y1 + 2 };
            }
            const dentro = linhas.filter(l => {
                const cx = l.x + l.w / 2, cy = l.y + l.h / 2;
                return cx >= alvo.x && cx <= alvo.x + alvo.w && cy >= alvo.y && cy <= alvo.y + alvo.h;
            }).sort((a, b) => (Math.abs(a.by - b.by) > Math.min(a.alt, b.alt) * 0.4 ? a.by - b.by : a.x - b.x));
            // monta o texto linha por linha
            let txt = '', ult = null;
            dentro.forEach(l => {
                if (ult && Math.abs(l.by - ult.by) <= Math.min(l.alt, ult.alt) * 0.4) {
                    const vao = l.x - (ult.x + ult.w);
                    txt += (vao > l.alt * 0.15 && !/\s$/.test(ult.str) && !/^\s/.test(l.str) ? ' ' : '') + l.str;
                } else txt += (ult ? '\n' : '') + l.str;
                ult = l;
            });
            txt = txt.replace(/[ \t]+/g, ' ');
            const alturas = dentro.map(l => l.alt).sort((a, b) => a - b);
            const tam = alturas.length ? Math.round(alturas[Math.floor(alturas.length / 2)] * 2) / 2 : padrao.tam;
            const usadas = dentro.map(l => l.fonte);
            const fonte = ['sans', 'serif', 'mono'].sort((a, b) => usadas.filter(x => x === b).length - usadas.filter(x => x === a).length)[0];
            const { fundo, tinta } = coresDoTrecho(pi, alvo);
            // a caixa começa onde a 1ª linha do texto novo cai na mesma linha de base do original
            let x = alvo.x, y = alvo.y;
            if (dentro.length) { x = Math.min(alvo.x, dentro[0].bx - PAD); y = Math.min(alvo.y, dentro[0].by - PAD - BASE[fonte] * tam); }
            const nLinhas = Math.max(1, txt.split('\n').length);
            const h = Math.max(alvo.y + alvo.h - y, nLinhas * tam * LINHA + 2 * PAD);
            const w = Math.max(alvo.x + alvo.w - x + 2, 20);
            novoItem(pi, normItem({ t: 'texto', x, y, w, h, txt, tam: limitar(tam, 4, 200), cor: tinta, fonte, neg: false, ita: false, al: 'l', fundo }), true);
            if (!dentro.length) aviso('Não achei texto do PDF nesse trecho (pode ser uma imagem). A área foi coberta: escreva o texto novo.');
        }

        /* ---------- barra de propriedades ---------- */
        function montarProps() {
            const it = editando ? editando.it : itemSel();
            const tipo = it ? (it.t === 'elipse' ? 'ret' : it.t) : ({ texto: 'texto', cobrir: 'texto', marca: 'marca', ret: 'ret', elipse: 'ret', linha: 'linha', seta: 'linha' }[ferramenta] || null);
            const v = it || {};
            const amostras = (p, atual, lista = CORES) => lista.map(c => `<button type="button" class="pe-amostra${c === atual ? ' on' : ''}" data-p="${p}" data-v="${c}" style="background:${c}" title="${c}"></button>`).join('');
            let h = '';
            if (tipo === 'texto') {
                const q = k => (it ? v[k] : padrao[k === 'fundo' ? 'fundoTxt' : k]);
                const f = q('fonte'), tam = q('tam'), cor = q('cor'), al = q('al'), fundo = q('fundo');
                h = `<span class="pe-rot">Texto</span>
                    <select data-p="fonte" title="Tipo de letra">${Object.entries(NOMES_FONTE).map(([k, n]) => `<option value="${k}"${k === f ? ' selected' : ''}>${n}</option>`).join('')}</select>
                    <button type="button" data-p="tam" data-v="-1" title="Letra menor">A−</button>
                    <input type="number" data-p="tam" min="4" max="200" step="0.5" value="${tam}" title="Tamanho da letra, em pontos">
                    <button type="button" data-p="tam" data-v="1" title="Letra maior">A+</button>
                    <button type="button" data-p="neg" class="${q('neg') ? 'on' : ''}" title="Negrito (Ctrl+B)"><b>N</b></button>
                    <button type="button" data-p="ita" class="${q('ita') ? 'on' : ''}" title="Itálico (Ctrl+I)"><i>I</i></button>
                    <button type="button" data-p="al" data-v="l" class="${al === 'l' ? 'on' : ''}" title="Alinhar à esquerda">Esq.</button>
                    <button type="button" data-p="al" data-v="c" class="${al === 'c' ? 'on' : ''}" title="Centralizar">Centro</button>
                    <button type="button" data-p="al" data-v="r" class="${al === 'r' ? 'on' : ''}" title="Alinhar à direita">Dir.</button>
                    <span class="pe-sep"></span><label class="pe-btn" title="Cor da letra">Cor <input type="color" data-p="cor" value="${cor}"></label>${amostras('cor', cor)}
                    <span class="pe-sep"></span><label class="pe-chk" title="Pinta o fundo da caixa, cobrindo o que está embaixo"><input type="checkbox" data-p="cobre"${fundo ? ' checked' : ''}> Cobrir o fundo</label>
                    <input type="color" data-p="fundoTxt" value="${fundo || '#ffffff'}" title="Cor da cobertura"${fundo ? '' : ' disabled'}>`;
            } else if (tipo === 'ret' || tipo === 'linha') {
                const cor = it ? v.cor : padrao.traco, esp = it ? v.esp : padrao.esp, fundo = it ? v.fundo : padrao.preench;
                h = `<span class="pe-rot">${tipo === 'linha' ? 'Linha' : 'Forma'}</span>
                    <label class="pe-btn" title="Cor do traço">Traço <input type="color" data-p="traco" value="${cor}"></label>${amostras('traco', cor)}
                    <label class="pe-btn" title="Espessura do traço, em pontos">Espessura <input type="number" data-p="esp" min="${tipo === 'linha' ? 0.5 : 0}" max="40" step="0.5" value="${esp}"></label>`
                    + (tipo === 'ret' ? `<span class="pe-sep"></span><label class="pe-chk" title="Preencher a forma"><input type="checkbox" data-p="enche"${fundo ? ' checked' : ''}> Preencher</label><input type="color" data-p="preench" value="${fundo || '#ffffff'}"${fundo ? '' : ' disabled'} title="Cor do preenchimento">` : '')
                    + (tipo === 'linha' && it ? `<label class="pe-chk"><input type="checkbox" data-p="seta"${v.seta ? ' checked' : ''}> Ponta de seta</label>` : '');
            } else if (tipo === 'marca') {
                const cor = it ? v.cor : padrao.marca;
                h = `<span class="pe-rot">Marca-texto</span>${amostras('marca', cor, MARCAS)}<input type="color" data-p="marca" value="${cor}" title="Outra cor">`;
            } else if (tipo === 'img') {
                h = '<span class="pe-rot">Imagem</span><span class="pe-dim">Arraste para mover; os cantos mudam o tamanho (Shift solta a proporção).</span>';
            } else {
                h = '<span class="pe-dim">Escolha uma ferramenta acima, ou clique num item para mudar cor, letra e tamanho.</span>';
            }
            if (it) h += '<span class="pe-sep"></span><button type="button" data-p="frente" title="Trazer para a frente dos outros itens">Frente</button><button type="button" data-p="tras" title="Mandar para trás dos outros itens">Trás</button>';
            const pag = estado.paginas[paginaAtual];
            h += `<span class="pe-esp"></span><span class="pe-rot">Página</span><label class="pe-btn" title="Cor de fundo da página atual">Fundo <input type="color" data-p="fundoPag" value="${(pag && pag.fundo) || '#ffffff'}"></label><button type="button" data-p="semFundo" title="Tirar a cor de fundo da página">Sem fundo</button>`;
            barraProps.innerHTML = h;
        }
        barraProps.addEventListener('mousedown', e => { if (e.target.closest('button')) e.preventDefault(); }); // não tira o foco do texto sendo escrito
        function aplicarProp(nome, valor, deBotao) {
            const it = editando ? editando.it : itemSel();
            const pag = estado.paginas[paginaAtual];
            if (nome === 'fundoPag' || nome === 'semFundo') {
                if (!pag) return;
                pag.fundo = nome === 'semFundo' ? null : valor;
                dimensionar(vistas[paginaAtual], pag);
                registrarLogo();
                if (nome === 'semFundo') montarProps();
                return;
            }
            if (nome === 'frente' || nome === 'tras') {
                if (!it || !sel) return;
                if (editando) terminarEdicao();
                const lista = estado.paginas[sel.p].itens, i = lista.indexOf(it);
                if (i < 0) return;
                lista.splice(i, 1);
                if (nome === 'frente') lista.push(it); else lista.unshift(it);
                escolher(sel.p, lista.indexOf(it));
                registrar();
                return;
            }
            const set = (k, v) => { if (it) it[k] = v; };
            switch (nome) {
                case 'fonte': padrao.fonte = valor; set('fonte', valor); break;
                case 'tam': {
                    const atual = it ? it.tam : padrao.tam;
                    const n = limitar(deBotao ? atual + Number(valor) * (atual >= 24 ? 2 : 1) : Number(valor) || atual, 4, 200);
                    padrao.tam = n; set('tam', n);
                    break;
                }
                case 'neg': case 'ita': { const n = !(it ? it[nome] : padrao[nome]); padrao[nome] = n; set(nome, n); deBotao = true; break; }
                case 'al': padrao.al = valor; set('al', valor); break;
                case 'cor': padrao.cor = valor; set('cor', valor); break;
                case 'cobre': { const v = valor ? (barraProps.querySelector('[data-p="fundoTxt"]').value || '#ffffff') : null; padrao.fundoTxt = v; set('fundo', v); deBotao = true; break; }
                case 'fundoTxt': padrao.fundoTxt = valor; set('fundo', valor); break;
                case 'traco': padrao.traco = valor; set('cor', valor); break;
                case 'esp': { const n = limitar(Number(valor) || 0, it && it.t === 'linha' ? 0.5 : 0, 40); padrao.esp = n; set('esp', n); break; }
                case 'enche': { const v = valor ? (barraProps.querySelector('[data-p="preench"]').value || '#ffffff') : null; padrao.preench = v; set('fundo', v); deBotao = true; break; }
                case 'preench': padrao.preench = valor; set('fundo', valor); break;
                case 'seta': set('seta', !!valor); break;
                case 'marca': padrao.marca = valor; set('cor', valor); break;
                default: return;
            }
            if (it && sel) {
                if (editando) {
                    estiloTexto(editando.ta, it);
                    editando.el.style.background = it.fundo || 'transparent';
                    if (editando.ta.scrollHeight > editando.ta.clientHeight + 1) { it.h = editando.ta.scrollHeight / escala; posItem(editando.el, it); }
                    editando.ta.focus({ preventScroll: true });
                } else { desenharItens(sel.p); if (it.t === 'texto') caberTexto(sel.p, sel.i); }
                registrarLogo();
            }
            // botões e caixas de marcar redesenham a barra; cor e número sendo arrastados/digitados não (perderiam o foco)
            if (deBotao || nome === 'fonte' || nome === 'seta') montarProps();
        }
        barraProps.addEventListener('click', e => {
            const b = e.target.closest('button[data-p]');
            if (b) aplicarProp(b.dataset.p, b.dataset.v, true);
        });
        barraProps.addEventListener('input', e => {
            const c = e.target.closest('[data-p]');
            if (!c || c.tagName === 'BUTTON' || c.tagName === 'SELECT' || c.type === 'checkbox' || (c.type === 'number' && c.value === '')) return;
            aplicarProp(c.dataset.p, c.value, false);
        });
        barraProps.addEventListener('change', e => {
            const c = e.target.closest('[data-p]');
            if (!c) return;
            if (c.type === 'checkbox') aplicarProp(c.dataset.p, c.checked, false);
            else if (c.tagName === 'SELECT') aplicarProp(c.dataset.p, c.value, false);
        });

        /* ---------- páginas: girar, duplicar, excluir, mover, em branco, juntar ---------- */
        function estruturaMudou(focar) {
            sel = null;
            editando = null;
            montar();
            registrar();
            montarProps(); atualizarBotoes();
            if (focar !== undefined) irPara(focar);
        }
        function girar(pi, d) {
            const p = estado.paginas[pi];
            if (!p) return;
            if (editando) terminarEdicao();
            const t = tamPagina(p);
            if (p.branca) p.branca = [p.branca[1], p.branca[0]]; else p.rot = (p.rot + d + 360) % 360;
            // os itens acompanham o giro (o centro de cada um vai para o lugar girado)
            const gira = (x, y) => (d > 0 ? [t.h - y, x] : [y, t.w - x]);
            p.itens.forEach(it => {
                if (it.t === 'linha') { [it.x1, it.y1] = gira(it.x1, it.y1); [it.x2, it.y2] = gira(it.x2, it.y2); return; }
                const [cx, cy] = gira(it.x + it.w / 2, it.y + it.h / 2);
                it.x = cx - it.w / 2; it.y = cy - it.h / 2;
            });
            definirAtual(pi);
            estruturaMudou();
        }
        function moverPagina(de, para) {
            if (para < 0 || para >= estado.paginas.length || de === para) return;
            const [p] = estado.paginas.splice(de, 1);
            estado.paginas.splice(para, 0, p);
            paginaAtual = para;
            estruturaMudou(para);
        }
        function apagarPagina(pi) {
            if (estado.paginas.length < 2) { aviso('O PDF precisa de pelo menos uma página.'); return; }
            estado.paginas.splice(pi, 1);
            paginaAtual = Math.min(pi, estado.paginas.length - 1);
            estruturaMudou(paginaAtual);
            aviso(`Página ${pi + 1} excluída. Ctrl+Z desfaz.`);
        }
        function duplicarPagina(pi) {
            estado.paginas.splice(pi + 1, 0, clone(estado.paginas[pi]));
            paginaAtual = pi + 1;
            estruturaMudou(pi + 1);
        }
        minis.addEventListener('click', e => {
            const m = e.target.closest('.pe-mini');
            if (!m) return;
            const pi = Number(m.dataset.pg), b = e.target.closest('button[data-m]');
            if (editando) terminarEdicao();
            if (!b) { irPara(pi); return; }
            if (b.dataset.m === 'girar') girar(pi, 90);
            if (b.dataset.m === 'dup') duplicarPagina(pi);
            if (b.dataset.m === 'apagar') apagarPagina(pi);
        });
        let arrastada = null;
        minis.addEventListener('dragstart', e => {
            const m = e.target.closest('.pe-mini');
            if (!m) return;
            arrastada = Number(m.dataset.pg);
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(arrastada));
            m.classList.add('arrastando');
        });
        minis.addEventListener('dragover', e => {
            if (arrastada === null) return;
            e.preventDefault();
            const m = e.target.closest('.pe-mini');
            minis.querySelectorAll('.antes,.depois').forEach(x => x.classList.remove('antes', 'depois'));
            if (m) { const r = m.getBoundingClientRect(); m.classList.add(e.clientY < r.top + r.height / 2 ? 'antes' : 'depois'); }
        });
        minis.addEventListener('drop', e => {
            if (arrastada === null) return;
            e.preventDefault();
            e.stopPropagation();
            const m = e.target.closest('.pe-mini'), de = arrastada;
            arrastada = null;
            minis.querySelectorAll('.antes,.depois,.arrastando').forEach(x => x.classList.remove('antes', 'depois', 'arrastando'));
            if (!m) return;
            const alvo = Number(m.dataset.pg), r = m.getBoundingClientRect();
            let para = alvo + (e.clientY < r.top + r.height / 2 ? 0 : 1);
            if (para > de) para--;
            moverPagina(de, para);
        });
        minis.addEventListener('dragend', () => { arrastada = null; minis.querySelectorAll('.antes,.depois,.arrastando').forEach(x => x.classList.remove('antes', 'depois', 'arrastando')); });

        async function juntarPdf(arq) {
            if (!arq) return;
            if (arq.type !== 'application/pdf' && !/\.pdf$/i.test(arq.name)) { aviso('Escolha um arquivo PDF.'); return; }
            if (arq.size > MAX_PDF) { aviso(`O PDF passa de ${legivel(MAX_PDF)}.`); return; }
            if (editando) terminarEdicao();
            const pr = progresso('Juntando “' + arq.name + '”');
            try {
                const bytes = new Uint8Array(await arq.arrayBuffer());
                pr('Lendo o arquivo…');
                const lib = await pdfjs();
                const doc = await lib.getDocument({ data: bytes.slice() }).promise;
                const k = 'm' + aleatorio(6);
                pr(`Enviando 0% de ${legivel(bytes.length)}`, 0);
                const f = await ctx.aoEnviarPdf(bytes, x => pr(`Enviando ${Math.round(x * 100)}% de ${legivel(bytes.length)}`, x));
                f.nome = arq.name.slice(0, 120);
                estado.fontes[k] = f;
                docs.set(k, Promise.resolve(doc));
                await lerTamanhos(k);
                const novas = Array.from({ length: doc.numPages }, (_, i) => ({ f: k, o: i, rot: 0, branca: null, fundo: null, itens: [] }));
                const depois = estado.paginas.length ? paginaAtual + 1 : 0;
                estado.paginas.splice(depois, 0, ...novas);
                paginaAtual = depois;
                pr.fim();
                estruturaMudou(paginaAtual);
                salvarAgora();
                aviso(`${doc.numPages} ${doc.numPages === 1 ? 'página entrou' : 'páginas entraram'} a partir da página ${depois + 1}.`);
            } catch (x) {
                console.error('[Editor de PDF]', x);
                pr.fim();
                aviso(x && x.status !== undefined ? x.message : 'Não consegui abrir este PDF. Ele pode estar protegido por senha.');
            }
        }
        function paginaBranca() {
            const base = estado.paginas[paginaAtual], t = base ? tamPagina(base) : { w: 595.28, h: 841.89 };
            estado.paginas.splice(paginaAtual + 1, 0, { f: null, o: 0, rot: 0, branca: [t.w, t.h], fundo: null, itens: [] });
            paginaAtual++;
            estruturaMudou(paginaAtual);
        }

        /* ---------- imagem e assinatura ---------- */
        /** Meio da parte visível da página atual (onde entram imagem e assinatura). */
        function lugarVisivel(pi, w, h) {
            const v = vistas[pi], t = tamPagina(estado.paginas[pi]);
            const ra = area.getBoundingClientRect(), rc = v.caixa.getBoundingClientRect();
            const cy = ((Math.max(ra.top, rc.top) + Math.min(ra.bottom, rc.bottom)) / 2 - rc.top) / escala;
            return { x: limitar(t.w / 2 - w / 2, 0, Math.max(0, t.w - w)), y: limitar(cy - h / 2, 0, Math.max(0, t.h - h)) };
        }
        async function porImagem(src, wpx, hpx, larguraPt) {
            const k = uid();
            await ctx.aoImagem(k, src);
            imagens[k] = src;
            const pi = paginaAtual, t = tamPagina(estado.paginas[pi]);
            const w = Math.min(larguraPt, t.w * 0.8, wpx * 0.75), h = w * hpx / wpx;
            novoItem(pi, normItem({ t: 'img', ...lugarVisivel(pi, w, h), w, h, k }));
        }
        async function escolherImagem(arq) {
            if (!arq) return;
            const pr = progresso('Pondo a imagem');
            try {
                pr('Preparando…');
                const { src, w, h } = await prepararImagem(arq);
                pr('Enviando a imagem…');
                await porImagem(src, w, h, 240);
            } catch (x) { console.error('[Editor de PDF]', x); aviso('Não consegui usar esta imagem.'); } finally { pr.fim(); }
        }
        function assinar() {
            if (editando) terminarEdicao();
            const m = modal(`<h3>Assinatura</h3>
                <p class="pe-dim">Desenhe com o mouse, o dedo ou a caneta. Ela entra na página atual e depois pode ser movida e redimensionada.</p>
                <canvas class="pe-ass"></canvas>
                <div class="pe-linha">
                    <button type="button" class="pe-amostra on" data-c="#111111" style="background:#111111" title="Preta"></button>
                    <button type="button" class="pe-amostra" data-c="#1d3f9e" style="background:#1d3f9e" title="Azul"></button>
                    <label class="pe-btn">Traço <input type="range" min="1" max="6" step="0.5" value="2.5" data-c="esp"></label>
                    <button type="button" data-m="limpar">Limpar</button>
                    <span class="pe-esp"></span>
                    <button type="button" data-m="cancelar">Cancelar</button>
                    <button type="button" data-m="ok" class="pe-ok">Pôr no PDF</button>
                </div>`, () => {});
            const cv = m.caixa.querySelector('canvas'), c2 = cv.getContext('2d');
            const r = cv.getBoundingClientRect();
            cv.width = Math.max(300, Math.round(r.width * 2)); cv.height = Math.max(120, Math.round(r.height * 2));
            let cor = '#111111', esp = 2.5, riscou = false, ultimo = null;
            const ponto = e => { const b = cv.getBoundingClientRect(); return { x: (e.clientX - b.left) * cv.width / b.width, y: (e.clientY - b.top) * cv.height / b.height }; };
            cv.addEventListener('pointerdown', e => {
                try { cv.setPointerCapture(e.pointerId); } catch { /* sem captura */ } ultimo = ponto(e); riscou = true;
                c2.beginPath(); c2.arc(ultimo.x, ultimo.y, esp, 0, Math.PI * 2); c2.fillStyle = cor; c2.fill();
            });
            cv.addEventListener('pointermove', e => {
                if (!ultimo) return;
                const p = ponto(e), pressao = e.pointerType === 'pen' && e.pressure ? 0.5 + e.pressure : 1;
                c2.strokeStyle = cor; c2.lineWidth = esp * 2 * pressao; c2.lineCap = 'round'; c2.lineJoin = 'round';
                c2.beginPath(); c2.moveTo(ultimo.x, ultimo.y); c2.lineTo(p.x, p.y); c2.stroke();
                ultimo = p;
            });
            const fim = () => { ultimo = null; };
            cv.addEventListener('pointerup', fim);
            cv.addEventListener('pointercancel', fim);
            m.caixa.addEventListener('input', e => { if (e.target.dataset.c === 'esp') esp = Number(e.target.value); });
            m.caixa.addEventListener('click', async e => {
                const a = e.target.closest('[data-c^="#"]');
                if (a) { cor = a.dataset.c; m.caixa.querySelectorAll('.pe-amostra').forEach(x => x.classList.toggle('on', x === a)); return; }
                const b = e.target.closest('[data-m]');
                if (!b) return;
                if (b.dataset.m === 'limpar') { c2.clearRect(0, 0, cv.width, cv.height); riscou = false; }
                if (b.dataset.m === 'cancelar') m.fechar();
                if (b.dataset.m === 'ok') {
                    const corte = riscou && recortar(cv);
                    if (!corte) { aviso('Desenhe a assinatura primeiro.'); return; }
                    b.disabled = true; b.textContent = 'Pondo…';
                    try { await porImagem(corte.toDataURL('image/png'), corte.width, corte.height, 170); m.fechar(); } catch (x) { console.error('[Editor de PDF]', x); aviso('Não consegui guardar a assinatura.'); b.disabled = false; b.textContent = 'Pôr no PDF'; }
                }
            });
        }
        function recortar(cv) {
            const d = cv.getContext('2d').getImageData(0, 0, cv.width, cv.height).data;
            let x1 = cv.width, y1 = cv.height, x2 = -1, y2 = -1;
            for (let y = 0; y < cv.height; y++) for (let x = 0; x < cv.width; x++) {
                if (d[(y * cv.width + x) * 4 + 3] > 8) { if (x < x1) x1 = x; if (x > x2) x2 = x; if (y < y1) y1 = y; if (y > y2) y2 = y; }
            }
            if (x2 < 0) return null;
            const m = 6, w = x2 - x1 + 1 + 2 * m, h = y2 - y1 + 1 + 2 * m;
            const n = document.createElement('canvas');
            n.width = w; n.height = h;
            n.getContext('2d').drawImage(cv, x1 - m, y1 - m, w, h, 0, 0, w, h);
            return n;
        }

        /* ---------- janelas (assinatura, sair, progresso) e avisos ---------- */
        function modal(html, aoCancelar) {
            const fundo = el('div', 'pe-modal-fundo'), caixa = el('div', 'pe-modal', html);
            fundo.append(caixa);
            raiz.append(fundo);
            const m = {
                caixa,
                fechar: () => { fundo.remove(); if (modalAtual === m) modalAtual = null; },
                cancelar: () => { if (aoCancelar === false) return; m.fechar(); if (aoCancelar) aoCancelar(); },
            };
            modalAtual = m;
            fundo.addEventListener('pointerdown', e => { if (e.target === fundo) m.cancelar(); });
            return m;
        }
        function progresso(titulo) {
            const m = modal(`<h3>${esc(titulo)}</h3><p class="pe-pr-txt">…</p><div class="pe-barra-pr indef"><span></span></div>`, false);
            const f = (txt, x) => {
                m.caixa.querySelector('.pe-pr-txt').textContent = txt;
                const s = m.caixa.querySelector('.pe-barra-pr span');
                s.style.width = x === undefined ? '' : Math.round(x * 100) + '%';
                s.parentElement.classList.toggle('indef', x === undefined);
            };
            f.fim = () => m.fechar();
            return f;
        }
        function aviso(txt) {
            let a = raiz.querySelector('.pe-aviso');
            if (!a) { a = el('div', 'pe-aviso'); a.setAttribute('role', 'status'); raiz.append(a); }
            a.textContent = txt;
            a.classList.add('on');
            clearTimeout(aviso.t);
            aviso.t = setTimeout(() => a.classList.remove('on'), 4200);
        }

        /* ---------- baixar ---------- */
        async function baixar() {
            if (editando) terminarEdicao();
            const pr = progresso('Gerando o PDF');
            try {
                const bytes = await gerarPdf(estado, k => ctx.bytes(k, estado.fontes[k], x => pr(`Baixando o original ${Math.round(x * 100)}%…`, x)), imagens, t => pr(t));
                baixarBytes(bytes, ctx.titulo);
            } catch (x) {
                console.error('[Editor de PDF]', x);
                aviso('Não consegui gerar o PDF. Se o arquivo for protegido por senha, ele não pode ser editado.');
            } finally { pr.fim(); }
        }

        /* ---------- botões e teclado ---------- */
        raiz.addEventListener('click', e => {
            if (e.target.closest('.pe-modal') || e.target.closest('.pe-props') || e.target.closest('.pe-minis')) return;
            const f = e.target.closest('button[data-f]');
            if (f) { usarFerramenta(f.dataset.f); return; }
            const b = e.target.closest('button[data-a]');
            if (!b) return;
            const a = b.dataset.a;
            if (editando && a !== 'desfazer' && a !== 'refazer') terminarEdicao();
            if (a === 'fechar') pedirFechar();
            else if (a === 'salvar') { salvarAgora(); aviso(ctx.soVer ? 'Só visualização: nada foi gravado.' : 'Salvo.'); }
            else if (a === 'baixar') baixar();
            else if (a === 'desfazer') voltar(-1);
            else if (a === 'refazer') voltar(1);
            else if (a === 'apagaitem') apagarItem();
            else if (a === 'dupitem') duplicarItem();
            else if (a === 'menos') passoZoom(-1);
            else if (a === 'mais') passoZoom(1);
            else if (a === 'assinar') assinar();
            else if (a === 'girarE') girar(paginaAtual, -90);
            else if (a === 'girarD') girar(paginaAtual, 90);
            else if (a === 'duppag') duplicarPagina(paginaAtual);
            else if (a === 'apagapag') apagarPagina(paginaAtual);
            else if (a === 'sobe') moverPagina(paginaAtual, paginaAtual - 1);
            else if (a === 'desce') moverPagina(paginaAtual, paginaAtual + 1);
            else if (a === 'branca') paginaBranca();
            else if (a === 'lado') {
                raiz.classList.toggle('sem-lado');
                localStorage.setItem('wf-pdf-lado', raiz.classList.contains('sem-lado') ? '0' : '1');
            }
        });
        $('[data-z="zoom"]').addEventListener('change', e => aplicarZoom(e.target.value === 'largura' ? 'largura' : Number(e.target.value)));
        $('[data-a="auto"]').addEventListener('change', e => {
            autoSalvar = e.target.checked;
            localStorage.setItem('wf-pdf-auto', autoSalvar ? '1' : '0');
            if (autoSalvar && sujo) salvarAgora();
            atualizarStatus();
        });
        $('[data-in="img"]').addEventListener('change', e => { const f = e.target.files[0]; e.target.value = ''; escolherImagem(f); });
        $('[data-in="juntar"]').addEventListener('change', e => { const f = e.target.files[0]; e.target.value = ''; juntarPdf(f); });
        // soltar um PDF (junta) ou uma imagem em cima do editor
        raiz.addEventListener('dragover', e => { if (arrastada === null && e.dataTransfer && [...e.dataTransfer.types].includes('Files')) e.preventDefault(); });
        raiz.addEventListener('drop', e => {
            if (arrastada !== null || !e.dataTransfer || !e.dataTransfer.files.length) return;
            e.preventDefault();
            const f = e.dataTransfer.files[0];
            if (/pdf/i.test(f.type) || /\.pdf$/i.test(f.name)) juntarPdf(f); else if (/^image\//.test(f.type)) escolherImagem(f);
        });
        function apagarItem() {
            const it = itemSel();
            if (!it) return;
            editando = null;
            const pi = sel.p;
            estado.paginas[pi].itens.splice(sel.i, 1);
            sel = null;
            desenharItens(pi); registrar(); montarProps(); atualizarBotoes();
        }
        function duplicarItem(origem) {
            const it = origem || itemSel();
            if (!it) return;
            if (editando) terminarEdicao();
            const pi = origem ? paginaAtual : sel.p, t = tamPagina(estado.paginas[pi]), n = clone(it);
            if (n.t === 'linha') { n.x1 += 12; n.x2 += 12; n.y1 += 12; n.y2 += 12; } else { n.x = Math.min(n.x + 12, Math.max(0, t.w - n.w)); n.y = Math.min(n.y + 12, Math.max(0, t.h - n.h)); }
            estado.paginas[pi].itens.push(n);
            escolher(pi, estado.paginas[pi].itens.length - 1);
            registrar();
        }
        const TECLAS = { v: 'sel', t: 'texto', c: 'cobrir', h: 'marca', r: 'ret', e: 'elipse', l: 'linha', a: 'seta' };
        const tecla = e => {
            if (fechado) return;
            e.stopPropagation(); // os atalhos do quadro ficam parados enquanto o editor está aberto
            const chave = e.key || '';
            const alvo = e.target, digitando = alvo && (alvo.tagName === 'TEXTAREA' || (alvo.tagName === 'INPUT' && !['checkbox', 'range', 'color', 'file'].includes(alvo.type)) || alvo.tagName === 'SELECT' || alvo.isContentEditable);
            const ctrl = e.ctrlKey || e.metaKey, k = chave.length === 1 ? chave.toLowerCase() : chave;
            if (modalAtual) { if (chave === 'Escape') { e.preventDefault(); modalAtual.cancelar(); } return; }
            if (chave === 'Escape') {
                e.preventDefault();
                if (editando) terminarEdicao(); else if (ferramenta !== 'sel') usarFerramenta('sel'); else pedirFechar();
                return;
            }
            if (ctrl && k === 's') { e.preventDefault(); salvarAgora(); aviso(ctx.soVer ? 'Só visualização: nada foi gravado.' : 'Salvo.'); return; }
            const txtSel = editando || (itemSel() && itemSel().t === 'texto');
            if (ctrl && (k === 'b' || k === 'i') && txtSel) { e.preventDefault(); aplicarProp(k === 'b' ? 'neg' : 'ita', null, true); return; }
            if (digitando) return;
            if (ctrl && k === 'z') { e.preventDefault(); voltar(e.shiftKey ? 1 : -1); return; }
            if (ctrl && k === 'y') { e.preventDefault(); voltar(1); return; }
            if (ctrl && k === 'd') { e.preventDefault(); duplicarItem(); return; }
            if (ctrl && k === 'c' && itemSel()) { e.preventDefault(); copiado = clone(itemSel()); aviso('Item copiado. Ctrl+V cola na página atual.'); return; }
            if (ctrl && k === 'v' && copiado) { e.preventDefault(); duplicarItem(copiado); return; }
            if (ctrl && (k === '+' || k === '=')) { e.preventDefault(); passoZoom(1); return; }
            if (ctrl && k === '-') { e.preventDefault(); passoZoom(-1); return; }
            if (ctrl && k === '0') { e.preventDefault(); aplicarZoom('largura'); return; }
            if (ctrl || e.altKey) return;
            const it = itemSel();
            if ((chave === 'Delete' || chave === 'Backspace') && it) { e.preventDefault(); apagarItem(); return; }
            if (chave === 'Enter' && it && it.t === 'texto') { e.preventDefault(); editarTexto(sel.p, sel.i, false); return; }
            if (chave.startsWith('Arrow') && it) {
                e.preventDefault();
                const passo = e.shiftKey ? 10 : 1, dx = { ArrowLeft: -passo, ArrowRight: passo }[chave] || 0, dy = { ArrowUp: -passo, ArrowDown: passo }[chave] || 0;
                moverItem(it, clone(it), dx, dy, tamPagina(estado.paginas[sel.p]));
                desenharItens(sel.p); registrarLogo();
                return;
            }
            if (chave === 'PageDown' || chave === 'PageUp') { e.preventDefault(); irPara(limitar(paginaAtual + (chave === 'PageDown' ? 1 : -1), 0, estado.paginas.length - 1)); return; }
            if (TECLAS[k]) { e.preventDefault(); usarFerramenta(TECLAS[k]); return; }
            if (k === 'i') { e.preventDefault(); $('[data-in="img"]').click(); return; }
            if (k === 's') { e.preventDefault(); assinar(); }
        };
        document.addEventListener('keydown', tecla, true);

        /* ---------- fechar ---------- */
        function pedirFechar() {
            if (editando) terminarEdicao();
            if (timerHist) registrar();
            if (!sujo || ctx.soVer || autoSalvar) { fechar(true); return; } // com "Salvar sozinho", grava o que faltava e sai
            const m = modal(`<h3>Salvar as mudanças?</h3><p>Você mudou este PDF e ainda não salvou.</p>
                <div class="pe-linha"><button type="button" data-m="nao">Sair sem salvar</button><span class="pe-esp"></span>
                <button type="button" data-m="voltar">Continuar editando</button><button type="button" data-m="sim" class="pe-ok">Salvar e sair</button></div>`, () => {});
            m.caixa.addEventListener('click', e => {
                const b = e.target.closest('[data-m]');
                if (!b) return;
                m.fechar();
                if (b.dataset.m === 'sim') fechar(true);
                if (b.dataset.m === 'nao') fechar(false);
            });
            m.caixa.querySelector('[data-m="sim"]').focus();
        }
        function fechar(salvar) {
            if (fechado) return;
            if (salvar) salvarAgora();
            fechado = true;
            clearTimeout(timerSalvar); clearTimeout(timerHist); clearInterval(relogio);
            document.removeEventListener('keydown', tecla, true);
            io.disconnect(); ioMini.disconnect(); ro.disconnect();
            fila.length = 0;
            raiz.remove();
            document.documentElement.classList.remove('pe-aberto');
            cache.forEach(c => { c.width = c.height = 0; });
            cache.clear();
            docs.forEach(p => p.then(d => d.destroy()).catch(() => {}));
            if (ctx.aoFechar) ctx.aoFechar();
        }

        /* ---------- abrir ---------- */
        usarFerramenta('sel');
        atualizarStatus();
        atualizarBotoes();
        (async () => {
            try {
                const usadas = [...new Set(estado.paginas.map(p => p.f).filter(Boolean))];
                if (!estado.paginas.length) usadas.push('p');
                const carregando = area.querySelector('.pe-carregando');
                for (const f of usadas) {
                    const total = await lerTamanhos(f, x => { if (carregando) carregando.textContent = `Lendo as páginas… ${Math.round(x * 100)}%`; });
                    // edições antigas podem apontar para páginas que não existem: ficam de fora
                    estado.paginas = estado.paginas.filter(p => p.f !== f || p.o < total);
                }
                if (!estado.paginas.length) {
                    const doc = await abrirDoc('p');
                    estado.paginas = Array.from({ length: doc.numPages }, (_, i) => ({ f: 'p', o: i, rot: 0, branca: null, fundo: null, itens: [] }));
                }
                hist[0] = JSON.stringify(estado);
                if (fechado) return;
                montar();
                montarProps();
                atualizarBotoes();
                area.focus({ preventScroll: true });
            } catch (x) {
                console.error('[Editor de PDF]', x);
                const msg = x && (x.status === 404 || /Missing PDF|404|sem-arquivo/.test(String((x && x.message) || x)))
                    ? 'O arquivo deste PDF não foi encontrado no servidor. Feche e escolha o PDF de novo no cartão.'
                    : 'Não consegui abrir este PDF. Ele pode estar protegido por senha ou corrompido.';
                area.innerHTML = `<div class="pe-carregando">${esc(msg)}</div>`;
            }
        })();
        return { fechar, pedirFechar, estado: () => clone(estado), historico: () => ({ hi, total: hist.length }) };
    }

    /* ================= o cartão no quadro ================= */
    const extras = {
        novoEditorPdf(sec) {
            sec = sec || this.aba;
            if (!sec) return null;
            const id = 'b' + uid(), aberta = this.pastaAberta(sec), { x0 } = this.areaUtil();
            const bloco = { id, secao: sec, tipo: 'editorpdf', titulo: 'PDF', links: [], dados: {} };
            if (aberta) bloco.pasta = aberta;
            this.estado.blocos.push(bloco);
            (this.estado.layout[sec] ??= {})[id] = { x: snap(x0), y: this.fimDaVista(sec, aberta), w: 340, h: 240, z: ++this.zTop };
            this.menuBloco = false;
            this.aviso('Editor de PDF adicionado. Escolha um arquivo PDF no cartão.');
            return id;
        },
        pdfInfo(b) {
            const p = b.dados && b.dados.pdf;
            if (!p) return 'Nenhum PDF ainda.';
            const itens = (p.paginas || []).reduce((s, x) => s + ((x && x.itens) || []).length, 0);
            const tam = p.fontes && p.fontes.p && p.fontes.p.tam ? ' · ' + legivel(p.fontes.p.tam) : '';
            return `${(p.paginas || []).length} páginas · ${itens} ${itens === 1 ? 'edição' : 'edições'}` + tam + (b.dados.nome ? ' · ' + b.dados.nome : '');
        },
        /** Envio em andamento do cartão (barra de progresso no cartão): { txt, pct } ou null. */
        pdfEnvioDe(b) { return (this.pdfEnvio && this.pdfEnvio[b.id]) || null; },
        _pdfProgresso(id, txt, x) {
            const novo = { ...(this.pdfEnvio || {}) };
            if (txt === null) delete novo[id]; else novo[id] = { txt, pct: x === undefined ? null : Math.round(x * 100) };
            this.pdfEnvio = novo;
        },
        _pdfBase() { return rotaArquivos(this.cfg && this.cfg.url); },
        /** De onde ler cada PDF do cartão: o servidor ou (cartões antigos) o data URL guardado no banco. */
        _pdfOrigem(b, chave, fonte) {
            const d = b.dados && b.dados.pdf, f = fonte || (d && d.fontes && d.fontes[chave]), base = this._pdfBase();
            if (f && f.id && base) return { url: urlFonte(base, f) };
            if (chave === 'p' && this.imagens[b.id + '.pdf']) return { dataUrl: this.imagens[b.id + '.pdf'] };
            return null;
        },
        async _pdfBytes(b, chave, fonte, aoProgresso) {
            const o = this._pdfOrigem(b, chave, fonte);
            if (!o) throw Object.assign(new Error('sem-arquivo'), { status: 404 });
            if (o.dataUrl) return dataUrlBytes(o.dataUrl);
            const r = await fetch(o.url, { credentials: 'same-origin' });
            if (!r.ok) throw Object.assign(new Error('HTTP ' + r.status), { status: r.status });
            const total = Number(r.headers.get('Content-Length')) || 0;
            if (!r.body || !total || !aoProgresso) return new Uint8Array(await r.arrayBuffer());
            const partes = [], leitor = r.body.getReader();
            let pos = 0;
            for (;;) {
                const { done, value } = await leitor.read();
                if (done) break;
                partes.push(value); pos += value.length;
                aoProgresso(Math.min(1, pos / total));
            }
            return new Uint8Array(await new Blob(partes).arrayBuffer());
        },
        /** Arquivos do servidor que só este cartão usa (cópias do cartão apontam para o mesmo arquivo). */
        _pdfSoDeste(b, fontes) {
            const outras = new Set();
            this.estado.blocos.forEach(x => {
                if (x.id === b.id || x.tipo !== 'editorpdf' || !x.dados || !x.dados.pdf || !x.dados.pdf.fontes) return;
                Object.values(x.dados.pdf.fontes).forEach(f => f && outras.add(f.id));
            });
            return Object.values(fontes || {}).filter(f => f && f.id && !outras.has(f.id)).map(f => f.id);
        },
        async pdfEscolher(b, arq) {
            if (!arq) return;
            if (arq.type !== 'application/pdf' && !/\.pdf$/i.test(arq.name)) { this.aviso('Escolha um arquivo PDF.'); return; }
            if (arq.size > MAX_PDF) { this.aviso(`O PDF passa de ${legivel(MAX_PDF)}. Divida o arquivo em partes e tente de novo.`); return; }
            if (meta('wf-so-ver') === '1') { this.aviso('O dono deste workflow liberou só a visualização para você.'); return; }
            if (this.pdfEnvioDe(b)) { this.aviso('Espere o envio atual terminar.'); return; }
            if (b.dados && b.dados.pdf && !confirm('Trocar o PDF deste cartão? As edições do PDF atual saem.')) return;
            const base = this._pdfBase(), id = b.id;
            if (!base) { this.aviso('Não consegui achar onde guardar o PDF.'); return; }
            try {
                this._pdfProgresso(id, 'Lendo o arquivo…');
                const bytes = new Uint8Array(await arq.arrayBuffer());
                const lib = await pdfjs();
                const doc = await lib.getDocument({ data: bytes.slice() }).promise;
                const n = doc.numPages;
                doc.destroy();
                this._pdfProgresso(id, `Enviando 0% de ${legivel(bytes.length)}`, 0);
                const f = await enviarArquivo(base, id + '-' + aleatorio(6), bytes, x => this._pdfProgresso(id, `Enviando ${Math.round(x * 100)}% de ${legivel(bytes.length)}`, x));
                f.nome = arq.name.slice(0, 120);
                const bl = this.blocoPorId(id);
                if (!bl) { apagarArquivo(base, f.id); return; } // o cartão foi apagado durante o envio
                const antigas = bl.dados && bl.dados.pdf ? this._pdfSoDeste(bl, bl.dados.pdf.fontes) : [];
                bl.dados = { ...(bl.dados || {}), nome: arq.name.slice(0, 120), pdf: { v: 2, fontes: { p: f }, paginas: Array.from({ length: n }, (_, i) => ({ f: 'p', o: i, rot: 0, branca: null, fundo: null, itens: [] })) } };
                if (!bl.titulo || bl.titulo === 'PDF') bl.titulo = arq.name.replace(/\.pdf$/i, '').slice(0, 80);
                this.agendar();
                antigas.forEach(x => apagarArquivo(base, x));
                this._pdfApagarLegado(id);
                this._pdfProgresso(id, null);
                this.pdfAbrir(bl);
            } catch (x) {
                console.error('[Editor de PDF]', x);
                this._pdfProgresso(id, null);
                this.aviso(x && x.status !== undefined ? 'Não consegui guardar o PDF: ' + x.message : 'Não consegui abrir este PDF. Ele pode estar protegido por senha ou corrompido.');
            }
        },
        _pdfApagarLegado(id) {
            if (this.imagens[id + '.pdf'] === undefined) return;
            delete this.imagens[id + '.pdf'];
            Q.enviar(`${this.cfg.url}/img.${id}.pdf`, null, 'DELETE').catch(() => {});
        },
        /** Cartão antigo (PDF dentro do banco): leva o arquivo para o disco do servidor. Se der errado, segue do jeito antigo. */
        async _pdfMigrar(b) {
            const d = b.dados && b.dados.pdf;
            if (!d || (d.fontes && d.fontes.p) || !this.imagens[b.id + '.pdf'] || meta('wf-so-ver') === '1') return;
            const base = this._pdfBase();
            if (!base) return;
            try {
                this._pdfProgresso(b.id, 'Atualizando o cartão…');
                const bytes = dataUrlBytes(this.imagens[b.id + '.pdf']);
                const f = await enviarArquivo(base, b.id + '-' + aleatorio(6), bytes, x => this._pdfProgresso(b.id, `Atualizando o cartão ${Math.round(x * 100)}%`, x));
                f.nome = String(b.dados.nome || '').slice(0, 120);
                const bl = this.blocoPorId(b.id);
                if (!bl) { apagarArquivo(base, f.id); return; }
                const nd = normalizar(bl.dados.pdf);
                nd.fontes.p = f;
                bl.dados = { ...bl.dados, pdf: nd };
                this.agendar();
                this._pdfApagarLegado(b.id);
            } catch (x) {
                console.warn('[Editor de PDF] o cartão continua no formato antigo:', x);
            } finally { this._pdfProgresso(b.id, null); }
        },
        _pdfImagens(id) {
            const imagens = {};
            Object.keys(this.imagens).filter(k => k.startsWith(id + '.pi.')).forEach(k => { imagens[k.slice(id.length + 4)] = this.imagens[k]; });
            return imagens;
        },
        async pdfAbrir(b) {
            if (!b || !(b.dados && b.dados.pdf)) { this.aviso('Escolha um PDF primeiro.'); return; }
            if (this.pdfEnvioDe(b)) { this.aviso('Espere o envio terminar.'); return; }
            if (this._pdfAberto) return;
            await this._pdfMigrar(b);
            const id = b.id, eu = this, base = this._pdfBase();
            const atual = () => eu.blocoPorId(id) || b;
            this._pdfAberto = abrirEditor({
                titulo: atual().titulo,
                dados: atual().dados.pdf,
                imagens: this._pdfImagens(id),
                soVer: meta('wf-so-ver') === '1',
                origem: (chave, fonte) => {
                    const o = eu._pdfOrigem(atual(), chave, fonte);
                    return o && o.dataUrl ? { data: dataUrlBytes(o.dataUrl) } : o;
                },
                bytes: (chave, fonte, aoProgresso) => eu._pdfBytes(atual(), chave, fonte, aoProgresso),
                aoEnviarPdf: (bytes, aoProgresso) => enviarArquivo(base, id + '-' + aleatorio(6), bytes, aoProgresso),
                aoImagem: async (k, src) => { await Q.enviar(`${eu.cfg.url}/img.${id}.pi.${k}`, JSON.stringify(src)); eu.imagens[id + '.pi.' + k] = src; },
                aoSalvar: novo => {
                    const bl = eu.blocoPorId(id);
                    if (!bl) return;
                    // fontes que saíram (PDF juntado e depois excluído) continuam guardadas: o desfazer pode trazê-las de volta
                    bl.dados = { ...bl.dados, pdf: { ...novo, fontes: { ...((bl.dados.pdf && bl.dados.pdf.fontes) || {}), ...novo.fontes } } };
                    eu.agendar();
                },
                estadoSalvo: () => eu.salvo,
                aoFechar: () => { eu._pdfAberto = null; },
            });
        },
        async pdfBaixar(b) {
            if (!(b.dados && b.dados.pdf)) return;
            if (this.pdfEnvioDe(b)) return;
            this._pdfProgresso(b.id, 'Gerando o PDF…');
            try {
                const pdf = await gerarPdf(b.dados.pdf, k => this._pdfBytes(b, k, null, x => this._pdfProgresso(b.id, `Baixando ${Math.round(x * 100)}%…`, x)), this._pdfImagens(b.id), t => this._pdfProgresso(b.id, t));
                baixarBytes(pdf, b.titulo);
            } catch (x) {
                console.error('[Editor de PDF]', x);
                this.aviso(x && x.status === 404 ? 'O arquivo deste PDF não foi encontrado no servidor.' : 'Não consegui gerar o PDF.');
            } finally { this._pdfProgresso(b.id, null); }
        },
        /** Cartão excluído: apaga do servidor os arquivos e as imagens que só ele usava. */
        _pdfLimpar(b) {
            const d = b.dados && b.dados.pdf, base = this._pdfBase();
            if (d && d.fontes && base) this._pdfSoDeste(b, d.fontes).forEach(x => apagarArquivo(base, x));
            this._pdfApagarLegado(b.id);
            Object.keys(this.imagens).filter(k => k.startsWith(b.id + '.pi.')).forEach(k => {
                delete this.imagens[k];
                Q.enviar(`${this.cfg.url}/img.${k}`, null, 'DELETE').catch(() => {});
            });
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco) && !this.tiposBloco.some(t => t.id === 'editorpdf')) this.tiposBloco.push({ id: 'editorpdf', nome: 'Editor de PDF' });
            const r = prep.apply(this, arguments);
            if (primeira && typeof this.novoBloco === 'function') {
                const novo = this.novoBloco;
                this.novoBloco = function (sec, tipo, ...resto) { return tipo === 'editorpdf' ? this.novoEditorPdf(sec) : novo.call(this, sec, tipo, ...resto); };
            }
            if (primeira && typeof this.removerBloco === 'function') {
                const remover = this.removerBloco;
                this.removerBloco = function (b, ...resto) {
                    const copia = b && b.tipo === 'editorpdf' ? clone(b) : null;
                    const r2 = remover.call(this, b, ...resto);
                    if (copia && !this.blocoPorId(copia.id)) this._pdfLimpar(copia);
                    return r2;
                };
            }
            return r;
        };
        return Object.assign(m, extras, { pdfEnvio: {} });
    };
    window.WFEditorPdf = { gerarPdf, normalizar, quebrar, CORES, pdfjs, abrirEditor };
})();
