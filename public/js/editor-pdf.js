/* Work Flower · cartão "Editor de PDF" (Corpo, Mente, Carteira, Negócios e Diverso)
   Estende o WFQuadro.metodos (como graficos.js): carregue depois do quadro.js e antes do Alpine iniciar.
   - O PDF original fica numa chave à parte, img.<id>.pdf (data URL); as edições ficam em b.dados.pdf:
       { paginas: [{ o: índice no original, fundo: '#rrggbb'|null, itens: [...] }] }
     itens (em pontos do PDF, a partir do canto de cima à esquerda):
       { t: 'texto', x, y, w, h, txt, tam, cor } · { t: 'img', x, y, w, h, k } (imagem em img.<id>.pi.<k>)
       { t: 'ret', x, y, w, h, cor, fundo } · { t: 'marca', x, y, w, h, cor }
   - O editor abre em tela cheia: mostra as páginas com pdf.js (o mesmo da Mente), põe os itens por cima,
     e "Baixar PDF" gera o arquivo final com pdf-lib (fundo atrás da página, página, itens por cima).
   Nada é enviado a outro serviço: os dois scripts vêm do cdnjs e o PDF é montado no navegador. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid, snap } = Q.util;
    const PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    const PDFLIB = 'https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js';
    const MAX_PDF = 4 * 1024 * 1024; // o servidor guarda até 6 milhões de caracteres por chave (o base64 cresce 1/3)
    const CORES = ['#111111', '#ffffff', '#2f6fc7', '#dc2626', '#16a34a', '#f2c230', '#fb923c', '#a78bfa'];

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
    const bytesDe = url => fetch(url).then(r => r.arrayBuffer()).then(b => new Uint8Array(b));
    const lerArquivo = f => new Promise((ok, falha) => { const r = new FileReader(); r.onload = () => ok(r.result); r.onerror = falha; r.readAsDataURL(f); });
    const hexRgb = (lib, h) => { const n = parseInt(String(h || '#000000').slice(1), 16); return lib.rgb(((n >> 16) & 255) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255); };
    // as fontes padrão do PDF (WinAnsi) não têm emoji nem alguns símbolos: troca por "?" em vez de falhar
    const winAnsi = s => String(s || '').replace(/[^\x0A\x20-\x7E\xA0-\xFF–—‘’“”•…€]/g, '?');

    /* ================= gerar o PDF final ================= */
    async function gerarPdf(original, dados, imagens) {
        const lib = await carregar(PDFLIB, 'PDFLib');
        const saida = await lib.PDFDocument.create();
        const paginas = dados.paginas || [];
        const embutidas = await saida.embedPdf(original, paginas.map(p => p.o));
        const fonte = await saida.embedFont(lib.StandardFonts.Helvetica);
        for (let i = 0; i < paginas.length; i++) {
            const p = paginas[i], e = embutidas[i], W = e.width, H = e.height;
            const pg = saida.addPage([W, H]);
            if (p.fundo) pg.drawRectangle({ x: 0, y: 0, width: W, height: H, color: hexRgb(lib, p.fundo) });
            pg.drawPage(e, { x: 0, y: 0, width: W, height: H });
            for (const it of p.itens || []) {
                const y = H - it.y - it.h;
                if (it.t === 'marca') pg.drawRectangle({ x: it.x, y, width: it.w, height: it.h, color: hexRgb(lib, it.cor || '#f2c230'), opacity: 0.38 });
                else if (it.t === 'ret') pg.drawRectangle({ x: it.x, y, width: it.w, height: it.h, borderColor: hexRgb(lib, it.cor), borderWidth: 2, ...(it.fundo ? { color: hexRgb(lib, it.fundo) } : {}) });
                else if (it.t === 'img' && imagens[it.k]) {
                    const b = await bytesDe(imagens[it.k]);
                    const img = /^data:image\/png/i.test(imagens[it.k]) ? await saida.embedPng(b) : await saida.embedJpg(b);
                    pg.drawImage(img, { x: it.x, y, width: it.w, height: it.h });
                } else if (it.t === 'texto') {
                    const tam = it.tam || 14, linhaH = tam * 1.25;
                    // quebra as linhas na largura da caixa, como na tela
                    const linhas = [];
                    winAnsi(it.txt).split('\n').forEach(par => {
                        let atual = '';
                        par.split(' ').forEach(pal => {
                            const tenta = atual ? atual + ' ' + pal : pal;
                            if (atual && fonte.widthOfTextAtSize(tenta, tam) > it.w) { linhas.push(atual); atual = pal; } else atual = tenta;
                        });
                        linhas.push(atual);
                    });
                    linhas.forEach((l, k) => pg.drawText(l, { x: it.x + 2, y: H - it.y - tam - k * linhaH, size: tam, font: fonte, color: hexRgb(lib, it.cor) }));
                }
            }
        }
        return saida.save();
    }

    /* ================= editor em tela cheia ================= */
    function abrirEditor(ctx) {
        const { titulo, original, dados, imagens, aoSalvar, aoImagem } = ctx;
        const estado = JSON.parse(JSON.stringify(dados));
        let escala = 1, sel = null, mudou = false;
        const raiz = document.createElement('div');
        raiz.className = 'pe-raiz';
        raiz.setAttribute('role', 'dialog');
        raiz.setAttribute('aria-label', 'Editor de PDF');
        raiz.innerHTML = `
            <div class="pe-barra">
                <b class="pe-tit"></b>
                <span class="pe-sep"></span>
                <button type="button" data-a="texto" title="Caixa de texto">T Texto</button>
                <label class="pe-btn" title="Imagem por cima da página">🖼 Imagem<input type="file" accept="image/png,image/jpeg" hidden></label>
                <button type="button" data-a="ret" title="Retângulo">▭ Forma</button>
                <button type="button" data-a="marca" title="Marca-texto">▮ Marcar</button>
                <span class="pe-sep"></span>
                <label class="pe-btn" title="Cor dos itens novos e do item escolhido">Cor <input type="color" data-c="cor" value="#111111"></label>
                <label class="pe-btn" title="Tamanho da letra">Letra <input type="number" data-c="tam" min="6" max="96" value="14" style="width:3.2rem"></label>
                <label class="pe-btn" title="Cor de fundo desta página">Fundo <input type="color" data-c="fundo" value="#ffffff"></label>
                <button type="button" data-a="semfundo" title="Tirar a cor de fundo da página">Sem fundo</button>
                <span class="pe-sep"></span>
                <button type="button" data-a="sobe" title="Subir a página">↑ Página</button>
                <button type="button" data-a="desce" title="Descer a página">↓ Página</button>
                <button type="button" data-a="dup" title="Duplicar a página">⧉ Duplicar</button>
                <button type="button" data-a="tirapag" title="Tirar a página">✕ Página</button>
                <button type="button" data-a="apaga" title="Apagar o item escolhido (Delete)">🗑 Item</button>
                <span class="pe-esp"></span>
                <button type="button" data-a="baixar">⬇ Baixar PDF</button>
                <button type="button" data-a="fechar" class="pe-ok">Salvar e fechar</button>
            </div>
            <div class="pe-dica">Clique numa página para escolher. Arraste os itens para mover e o canto para mudar o tamanho. Dois cliques num texto para escrever.</div>
            <div class="pe-paginas"></div>`;
        raiz.querySelector('.pe-tit').textContent = titulo || 'PDF';
        document.body.appendChild(raiz);
        document.documentElement.classList.add('pe-aberto');
        const area = raiz.querySelector('.pe-paginas');
        const campo = n => raiz.querySelector(`[data-c="${n}"]`);
        let paginaAtual = 0, docPdf = null;

        const marcar = () => { mudou = true; };
        function itemPos(el, it) {
            Object.assign(el.style, { left: it.x * escala + 'px', top: it.y * escala + 'px', width: it.w * escala + 'px', height: it.h * escala + 'px' });
        }
        function desenharItens(pi) {
            const p = estado.paginas[pi], camada = area.querySelector(`[data-pg="${pi}"] .pe-camada`);
            if (!camada) return;
            camada.replaceChildren();
            (p.itens || []).forEach((it, ii) => {
                const el = document.createElement('div');
                el.className = 'pe-item pe-' + it.t + (sel && sel[0] === pi && sel[1] === ii ? ' sel' : '');
                el.dataset.i = ii;
                itemPos(el, it);
                if (it.t === 'texto') {
                    el.textContent = it.txt;
                    Object.assign(el.style, { color: it.cor, fontSize: (it.tam || 14) * escala + 'px', lineHeight: 1.25 });
                    el.addEventListener('dblclick', () => {
                        el.contentEditable = 'true'; el.focus();
                        el.addEventListener('blur', () => { it.txt = el.innerText.replace(/\n$/, ''); el.contentEditable = 'false'; marcar(); }, { once: true });
                    });
                } else if (it.t === 'img') {
                    const im = document.createElement('img');
                    im.src = imagens[it.k] || ''; im.alt = ''; im.draggable = false;
                    el.append(im);
                } else if (it.t === 'ret') {
                    Object.assign(el.style, { border: `${2 * escala}px solid ${it.cor}`, background: it.fundo || 'transparent' });
                } else if (it.t === 'marca') {
                    el.style.background = it.cor || '#f2c230';
                }
                const alca = document.createElement('span');
                alca.className = 'pe-alca';
                el.append(alca);
                el.addEventListener('pointerdown', e => pegar(e, pi, ii, e.target === alca));
                camada.append(el);
            });
        }
        function pegar(e, pi, ii, redim) {
            if (e.target.isContentEditable) return;
            e.stopPropagation();
            sel = [pi, ii]; paginaAtual = pi;
            const it = estado.paginas[pi].itens[ii];
            if (it.cor && /^#[0-9a-f]{6}$/i.test(it.cor)) campo('cor').value = it.cor;
            if (it.tam) campo('tam').value = it.tam;
            desenharItens(pi);
            const x0 = e.clientX, y0 = e.clientY, a = { ...it };
            const mover = ev => {
                const dx = (ev.clientX - x0) / escala, dy = (ev.clientY - y0) / escala;
                if (redim) { it.w = Math.max(10, a.w + dx); it.h = Math.max(8, a.h + dy); } else { it.x = a.x + dx; it.y = a.y + dy; }
                const el = area.querySelector(`[data-pg="${pi}"] .pe-item[data-i="${ii}"]`);
                if (el) itemPos(el, it);
                marcar();
            };
            const soltar = () => { removeEventListener('pointermove', mover); removeEventListener('pointerup', soltar); };
            addEventListener('pointermove', mover);
            addEventListener('pointerup', soltar);
        }
        async function desenharPaginas() {
            area.replaceChildren();
            const largura = Math.min(900, area.clientWidth - 40);
            for (let pi = 0; pi < estado.paginas.length; pi++) {
                const p = estado.paginas[pi];
                const pagina = await docPdf.getPage(p.o + 1);
                const vp1 = pagina.getViewport({ scale: 1 });
                escala = largura / vp1.width;
                const vp = pagina.getViewport({ scale: escala });
                const caixa = document.createElement('div');
                caixa.className = 'pe-pg' + (pi === paginaAtual ? ' atual' : '');
                caixa.dataset.pg = pi;
                Object.assign(caixa.style, { width: vp.width + 'px', height: vp.height + 'px', background: p.fundo || '#fff' });
                const cv = document.createElement('canvas');
                cv.width = vp.width * devicePixelRatio; cv.height = vp.height * devicePixelRatio;
                Object.assign(cv.style, { width: vp.width + 'px', height: vp.height + 'px' });
                const camada = document.createElement('div');
                camada.className = 'pe-camada';
                const num = document.createElement('span');
                num.className = 'pe-num';
                num.textContent = `${pi + 1} / ${estado.paginas.length}`;
                caixa.append(cv, camada, num);
                caixa.addEventListener('pointerdown', () => { paginaAtual = pi; sel = null; area.querySelectorAll('.pe-pg').forEach(x => x.classList.toggle('atual', x === caixa)); area.querySelectorAll('.pe-item.sel').forEach(x => x.classList.remove('sel')); campo('fundo').value = p.fundo || '#ffffff'; });
                area.append(caixa);
                // fundo transparente: a cor de fundo escolhida aparece por trás do conteúdo da página
                await pagina.render({ canvasContext: cv.getContext('2d'), viewport: vp, transform: [devicePixelRatio, 0, 0, devicePixelRatio, 0, 0], background: 'rgba(0,0,0,0)' }).promise;
                desenharItens(pi);
            }
        }
        const pAtual = () => estado.paginas[paginaAtual];
        const novoItem = it => {
            const p = pAtual();
            if (!p) return;
            p.itens = p.itens || [];
            p.itens.push(it);
            sel = [paginaAtual, p.itens.length - 1];
            desenharItens(paginaAtual);
            marcar();
            area.querySelector(`[data-pg="${paginaAtual}"]`)?.scrollIntoView({ block: 'nearest' });
        };
        raiz.addEventListener('click', async e => {
            const b = e.target.closest('button[data-a]');
            if (!b) return;
            const a = b.dataset.a, cor = campo('cor').value, tam = Number(campo('tam').value) || 14;
            if (a === 'texto') novoItem({ t: 'texto', x: 40, y: 40, w: 240, h: tam * 2.6, txt: 'Escreva aqui', tam, cor });
            else if (a === 'ret') novoItem({ t: 'ret', x: 60, y: 80, w: 180, h: 100, cor, fundo: null });
            else if (a === 'marca') novoItem({ t: 'marca', x: 60, y: 120, w: 220, h: 18, cor: cor === '#111111' ? '#f2c230' : cor });
            else if (a === 'semfundo') { if (pAtual()) { pAtual().fundo = null; marcar(); await desenharPaginas(); } }
            else if (a === 'apaga') { if (sel) { estado.paginas[sel[0]].itens.splice(sel[1], 1); const pg = sel[0]; sel = null; desenharItens(pg); marcar(); } }
            else if (a === 'sobe' || a === 'desce') {
                const j = paginaAtual + (a === 'sobe' ? -1 : 1);
                if (j < 0 || j >= estado.paginas.length) return;
                [estado.paginas[paginaAtual], estado.paginas[j]] = [estado.paginas[j], estado.paginas[paginaAtual]];
                paginaAtual = j; sel = null; marcar(); await desenharPaginas();
            } else if (a === 'dup') {
                if (!pAtual()) return;
                estado.paginas.splice(paginaAtual + 1, 0, JSON.parse(JSON.stringify(pAtual())));
                paginaAtual++; marcar(); await desenharPaginas();
            } else if (a === 'tirapag') {
                if (estado.paginas.length < 2) { alert('O PDF precisa de pelo menos uma página.'); return; }
                if (!confirm('Tirar esta página do PDF? O arquivo original continua guardado.')) return;
                estado.paginas.splice(paginaAtual, 1);
                paginaAtual = Math.min(paginaAtual, estado.paginas.length - 1); sel = null; marcar(); await desenharPaginas();
            } else if (a === 'baixar') {
                b.disabled = true; b.textContent = 'Gerando…';
                try {
                    const pdf = await gerarPdf(original, estado, imagens);
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
                    link.download = String(titulo || 'documento').replace(/[^\w.-]+/g, '_').replace(/\.pdf$/i, '') + '.pdf';
                    link.click();
                    setTimeout(() => URL.revokeObjectURL(link.href), 4000);
                } catch (x) { console.error('[Editor de PDF]', x); alert('Não consegui gerar o PDF. Se o arquivo for protegido por senha, ele não pode ser editado.'); }
                finally { b.disabled = false; b.textContent = '⬇ Baixar PDF'; }
            } else if (a === 'fechar') fechar(true);
        });
        raiz.querySelector('input[type=file]').addEventListener('change', async e => {
            const f = e.target.files[0];
            e.target.value = '';
            if (!f) return;
            try {
                const src = await Q.reduzir(f, 1400, 0.85);
                const k = uid();
                await aoImagem(k, src);
                imagens[k] = src;
                const im = new Image();
                im.onload = () => { const w = Math.min(240, im.naturalWidth); novoItem({ t: 'img', x: 50, y: 50, w, h: w * im.naturalHeight / im.naturalWidth, k }); };
                im.src = src;
            } catch { alert('Não consegui usar esta imagem.'); }
        });
        const aplicarCampo = n => {
            const v = campo(n).value;
            if (n === 'fundo') { if (pAtual()) { pAtual().fundo = v; const cx = area.querySelector(`[data-pg="${paginaAtual}"]`); if (cx) cx.style.background = v; marcar(); } return; }
            if (!sel) return;
            const it = estado.paginas[sel[0]].itens[sel[1]];
            if (n === 'cor') it.cor = v;
            if (n === 'tam' && it.t === 'texto') it.tam = Math.min(96, Math.max(6, Number(v) || 14));
            desenharItens(sel[0]); marcar();
        };
        ['cor', 'tam', 'fundo'].forEach(n => campo(n).addEventListener('input', () => aplicarCampo(n)));
        const tecla = e => {
            if (e.key === 'Escape') fechar(true);
            if ((e.key === 'Delete' || e.key === 'Backspace') && sel && !document.activeElement.isContentEditable && document.activeElement.tagName !== 'INPUT') {
                e.preventDefault(); raiz.querySelector('[data-a="apaga"]').click();
            }
        };
        document.addEventListener('keydown', tecla);
        function fechar(salvar) {
            document.removeEventListener('keydown', tecla);
            if (salvar && mudou) aoSalvar(estado);
            raiz.remove();
            document.documentElement.classList.remove('pe-aberto');
        }
        (async () => {
            try {
                const lib = await pdfjs();
                docPdf = await lib.getDocument({ data: original.slice() }).promise;
                await desenharPaginas();
            } catch (x) {
                console.error('[Editor de PDF]', x);
                area.textContent = 'Não consegui abrir este PDF. Ele pode estar protegido por senha ou corrompido.';
            }
        })();
    }

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
            const itens = (p.paginas || []).reduce((s, x) => s + (x.itens || []).length, 0);
            return `${(p.paginas || []).length} páginas · ${itens} ${itens === 1 ? 'edição' : 'edições'}` + (b.dados.nome ? ' · ' + b.dados.nome : '');
        },
        async pdfEscolher(b, arq) {
            if (!arq) return;
            if (arq.type !== 'application/pdf' && !/\.pdf$/i.test(arq.name)) { this.aviso('Escolha um arquivo PDF.'); return; }
            if (arq.size > MAX_PDF) { this.aviso('O PDF passa de 4 MB. Diminua o arquivo (ou divida em partes) e tente de novo.'); return; }
            if (b.dados && b.dados.pdf && !confirm('Trocar o PDF deste cartão? As edições do PDF atual saem.')) return;
            try {
                const url = await lerArquivo(arq);
                const lib = await pdfjs();
                const doc = await lib.getDocument({ data: await bytesDe(url) }).promise;
                await Q.enviar(`${this.cfg.url}/img.${b.id}.pdf`, JSON.stringify(url));
                this.imagens[b.id + '.pdf'] = url;
                b.dados = { ...(b.dados || {}), nome: arq.name.slice(0, 120), pdf: { paginas: Array.from({ length: doc.numPages }, (_, i) => ({ o: i, fundo: null, itens: [] })) } };
                if (!b.titulo || b.titulo === 'PDF') b.titulo = arq.name.replace(/\.pdf$/i, '').slice(0, 80);
                this.pdfAbrir(b);
            } catch (x) {
                console.error('[Editor de PDF]', x);
                this.aviso(/^\d+$/.test(x && x.message) ? 'Não consegui guardar o PDF no servidor. Tente um arquivo menor.' : 'Não consegui abrir este PDF. Ele pode estar protegido por senha.');
            }
        },
        async pdfAbrir(b) {
            const url = this.imagens[b.id + '.pdf'];
            if (!url || !(b.dados && b.dados.pdf)) { this.aviso('Escolha um PDF primeiro.'); return; }
            const imagens = {};
            Object.keys(this.imagens).filter(k => k.startsWith(b.id + '.pi.')).forEach(k => { imagens[k.slice(b.id.length + 4)] = this.imagens[k]; });
            const id = b.id;
            abrirEditor({
                titulo: b.titulo, original: await bytesDe(url), dados: b.dados.pdf, imagens,
                aoImagem: async (k, src) => { await Q.enviar(`${this.cfg.url}/img.${id}.pi.${k}`, JSON.stringify(src)); this.imagens[id + '.pi.' + k] = src; },
                aoSalvar: novo => { const bl = this.blocoPorId(id); if (bl) { bl.dados = { ...bl.dados, pdf: novo }; this.agendar(); } },
            });
        },
        async pdfBaixar(b) {
            const url = this.imagens[b.id + '.pdf'];
            if (!url || !(b.dados && b.dados.pdf)) return;
            const imagens = {};
            Object.keys(this.imagens).filter(k => k.startsWith(b.id + '.pi.')).forEach(k => { imagens[k.slice(b.id.length + 4)] = this.imagens[k]; });
            try {
                const pdf = await gerarPdf(await bytesDe(url), b.dados.pdf, imagens);
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
                a.download = String(b.titulo || 'documento').replace(/[^\w.-]+/g, '_') + '.pdf';
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 4000);
            } catch (x) { console.error('[Editor de PDF]', x); this.aviso('Não consegui gerar o PDF.'); }
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
            return r;
        };
        return Object.assign(m, extras);
    };
    window.WFEditorPdf = { gerarPdf, CORES, pdfjs };
})();
