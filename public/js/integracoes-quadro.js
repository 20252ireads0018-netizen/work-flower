/* Work Flower · dados das integrações (Instagram, TikTok, Shopee, Amazon) como elementos do quadro.
   - WFIntegra.arrastar(ev, bloco): começa a arrastar um post, uma lista ou um gráfico (tipo 'application/x-wf-bloco').
     Soltar sobre o quadro de uma área cria o bloco ali (pelo evento 'wf-colar' do quadro.js) no ponto onde caiu.
   - WFIntegra.porNoQuadro(bloco): o mesmo sem arrastar (toque, teclado): cola no fim da aba aberta; numa página
     sem quadro (ex.: Carteira › Marketplaces), guarda na Biblioteca para colar em qualquer área.
   - WFIntegra.barras(rotulos, valores, op): gráfico de barras em SVG com o texto escapado, para telas sem o graficos.js.
   bloco = { tipo: 'texto'|'tabela'|'grafico', titulo, dados, w, h }. Gráfico vira Tabela onde a página não tem Gráfico.
   Usado por js/criador-redes.js (cartão Criador) e js/marketplaces.js (abas Shopee e Amazon). */
(function () {
    'use strict';
    if (window.WFIntegra) return;
    const TIPO = 'application/x-wf-bloco';
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const curto = n => (n == null || isNaN(n) ? '—' : Math.abs(n) >= 1e6 ? (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi'
        : Math.abs(n) >= 1e4 ? (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 2 }));
    let atual = null; // bloco sendo arrastado (alguns navegadores não deixam ler o dataTransfer no dragover)

    // estilo dos elementos arrastáveis e do gráfico simples (uma vez por página)
    const css = document.createElement('style');
    css.textContent = '.ig-arrasta{cursor:grab}.ig-arrasta:active{cursor:grabbing}.ig-arrasta:hover{border-color:var(--prim-linha,var(--prim))}'
        + '.ig-svg{width:100%;height:auto;display:block}.ig-svg text{font-family:"DM Sans",sans-serif;fill:var(--tinta-2)}'
        + '.ig-svg .ig-val{font-size:10px;fill:var(--tinta)}.ig-svg .ig-rot{font-size:10px}.ig-svg .ig-vazio{font-size:12px}';
    document.head.appendChild(css);

    /** Componente Alpine do quadro desta página (ou null). */
    function quadro() {
        const el = document.querySelector('[x-ref="area"]');
        if (!el || !window.Alpine || !(window.WFQuadro && window.WFQuadro.ativo)) return null;
        try { const vm = window.Alpine.$data(el); return vm && typeof vm.colarBloco === 'function' ? { vm, el } : null; } catch (e) { return null; }
    }

    /** Gráfico vira tabela se a página não tem o cartão Gráfico. */
    function adaptar(b, vm) {
        const item = JSON.parse(JSON.stringify(b));
        const tipos = vm && Array.isArray(vm.tiposBloco) ? vm.tiposBloco.map(t => t.id) : null;
        if (item.tipo === 'grafico' && tipos && !tipos.includes('grafico')) {
            item.tipo = 'tabela';
            item.dados = { cab: item.dados.cab, linhas: item.dados.linhas };
        }
        item.nome = item.titulo;
        return item;
    }

    /** Dados de um cartão Gráfico com números digitados (o mesmo formato do novoGrafico do graficos.js). */
    function grafico(tipo, cab, linhas, eixoX, eixoY) {
        return {
            tipo, fonte: 'manual', ref: null, cols: null, eixoX: eixoX || '', eixoY: eixoY || '', meta: '', metaNome: 'Meta',
            alerta: '', diario: false, diarioValor: 'total', hist: {},
            cab, linhas: linhas.map(l => l.map(v => (v == null ? '' : String(v)))),
        };
    }

    function aviso(vm, msg) {
        if (vm && typeof vm.aviso === 'function') vm.aviso(msg);
        else if (typeof window.wfAviso === 'function') window.wfAviso(msg);
        else window.alert(msg);
    }

    /**
     * Cola no quadro; com x/y (coordenadas da tela), move o bloco novo para o ponto onde foi solto.
     * Numa página sem quadro, guarda na Biblioteca. A Biblioteca do servidor (BibliotecaController) não guarda o tipo
     * Gráfico: ele vai como Tabela, que o cartão Gráfico do quadro consegue usar como fonte ("Dados de outro cartão").
     * avisar(msg, erro): onde mostrar o resultado (padrão: o aviso do quadro ou um alert).
     */
    function porNoQuadro(bloco, x, y, avisar) {
        const q = quadro();
        if (!q) {
            const dizer = avisar || (m => aviso(null, m));
            if (!window.WFBiblioteca) { dizer('Abra uma área (Corpo, Mente, Carteira ou Diverso) para pôr este item no quadro.', true); return false; }
            const item = adaptar(bloco, null);
            if (item.tipo === 'grafico') { item.tipo = 'tabela'; item.dados = { cab: item.dados.cab, linhas: item.dados.linhas }; }
            window.WFBiblioteca.adicionar({ id: 'i' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6), nome: item.titulo, tipo: item.tipo,
                titulo: item.titulo, dados: item.dados, cor: null, w: item.w || 420, h: item.h || 280, imagem: null, criado: new Date().toISOString() })
                .then(() => dizer(`“${item.titulo}” guardado na Biblioteca (engrenagem › Biblioteca): abra uma área e cole no quadro.`))
                .catch(e => dizer(e && e.message === 'cheia' ? 'A Biblioteca está cheia. Apague algum item para guardar este.' : 'Não consegui guardar na Biblioteca agora.', true));
            return true;
        }
        const { vm, el } = q;
        const item = adaptar(bloco, vm);
        const antes = new Set((vm.estado.blocos || []).map(b => b.id));
        const ev = new CustomEvent('wf-colar', { detail: item });
        window.dispatchEvent(ev);
        if (ev.detail.ok === false) return false;
        const novo = (vm.estado.blocos || []).find(b => !antes.has(b.id));
        if (novo && x != null && vm.estado.layout[novo.secao] && vm.estado.layout[novo.secao][novo.id]) {
            const r = el.getBoundingClientRect(), l = vm.estado.layout[novo.secao][novo.id];
            const x0 = typeof vm.areaUtil === 'function' ? vm.areaUtil().x0 : 0;
            l.x = Math.max(x0, Math.round(x - r.left - 20));
            l.y = Math.max(0, Math.round(y - r.top - 16));
        }
        aviso(vm, x != null ? `“${item.titulo}” adicionado ao quadro.` : `“${item.titulo}” adicionado ao fim da aba. Arraste para onde quiser.`);
        return true;
    }

    function arrastar(ev, bloco) {
        if (!ev.dataTransfer) return;
        atual = bloco;
        try { ev.dataTransfer.setData(TIPO, JSON.stringify(bloco)); } catch (e) { /* IE/antigos */ }
        try { ev.dataTransfer.setData('text/plain', bloco.titulo || ''); } catch (e) { }
        ev.dataTransfer.effectAllowed = 'copy';
        ev.stopPropagation(); // não arrasta o cartão de onde saiu
    }

    const meu = e => !!atual && !!e.dataTransfer && Array.from(e.dataTransfer.types || []).includes(TIPO);
    document.addEventListener('dragover', e => {
        if (!meu(e) || !quadro()) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });
    document.addEventListener('drop', e => {
        if (!meu(e)) return;
        const q = quadro();
        if (!q) return;
        e.preventDefault();
        e.stopPropagation();
        let b = atual;
        try { b = JSON.parse(e.dataTransfer.getData(TIPO)) || atual; } catch (x) { }
        atual = null;
        porNoQuadro(b, e.clientX, e.clientY);
    }, true);
    document.addEventListener('dragend', () => { atual = null; });

    /** Barras simples (até 30) com rótulo e valor; op = { cor, largura, altura, nome } (em unidades do viewBox). */
    function barras(rotulos, valores, op = {}) {
        const W = op.largura || 440, H = op.altura || 180, L = 8, B = 28, T = 16;
        const n = Math.min(rotulos.length, 30), vals = valores.slice(0, n).map(v => Math.max(0, Number(v) || 0));
        if (!n || !vals.some(v => v > 0)) return `<svg viewBox="0 0 ${W} ${H}" class="ig-svg" role="img" aria-label="Gráfico vazio"><text x="${W / 2}" y="${H / 2}" text-anchor="middle" class="ig-vazio">Sem números para mostrar ainda</text></svg>`;
        const max = Math.max(...vals), larg = (W - L * 2) / n, h = H - T - B;
        let g = '';
        vals.forEach((v, i) => {
            const bh = Math.max(1, v / max * h), x = L + i * larg + larg * 0.15, w = larg * 0.7, y = T + h - bh;
            g += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${w.toFixed(1)}" height="${bh.toFixed(1)}" rx="2" fill="${esc(op.cor || 'var(--prim)')}"><title>${esc(rotulos[i])}: ${esc(curto(v))}</title></rect>`;
            if (n <= 12) g += `<text x="${(x + w / 2).toFixed(1)}" y="${(y - 3).toFixed(1)}" text-anchor="middle" class="ig-val">${esc(curto(v))}</text>`;
            if (n <= 12 || i % Math.ceil(n / 10) === 0) g += `<text x="${(x + w / 2).toFixed(1)}" y="${H - 10}" text-anchor="middle" class="ig-rot">${esc(String(rotulos[i]).slice(0, 8))}</text>`;
        });
        return `<svg viewBox="0 0 ${W} ${H}" class="ig-svg" role="img" aria-label="${esc(op.nome || 'Gráfico de barras')}">${g}</svg>`;
    }

    window.WFIntegra = { arrastar, porNoQuadro, grafico, barras, curto, esc };
})();
