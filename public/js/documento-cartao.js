/* Work Flower · cartão "Documento" fora da Mente (currículo, contrato, resumo, lista…).
   O Documento nasceu na Mente (mente.js tem a versão dela). Quando uma área inteira ou cartões da Mente são levados
   para outra área (App\Support\MoverCartoes), o cartão vai junto e passa a ser mostrado e criado lá também:
   - mostra: partials/documento-cartao (métodos doc* abaixo), incluído nos blocos de Corpo, Carteira e Diverso;
   - cria: o "+ Bloco" da página ganha "Documento" quando a página tem algum documento ou estado.tiposTrazidos
     (gravado pelo servidor ao mover) contém 'documento'.
   Estende o WFQuadro.metodos como o graficos.js (carregue depois do quadro.js). */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || Q._documento) return;
    Q._documento = true;
    const { uid } = Q.util;

    const FONTES = { sans: "'DM Sans', system-ui, sans-serif", serif: "Georgia, 'Times New Roman', serif", mono: "ui-monospace, Menlo, Consolas, monospace" };
    const MODELOS = {
        branco: { nome: 'Em branco', secoes: [['', '']] },
        curriculo: { nome: 'Currículo', secoes: [['Seu nome', 'Cargo · cidade · e-mail · telefone'], ['Objetivo', ''], ['Experiência', ''], ['Formação', ''], ['Habilidades', '']] },
        contrato: { nome: 'Contrato', secoes: [['Partes', ''], ['Objeto', ''], ['Prazo e valor', ''], ['Cláusulas', ''], ['Assinaturas', '']] },
        resumo: { nome: 'Resumo de estudo', secoes: [['Tema', ''], ['Ideias principais', ''], ['Dúvidas', ''], ['Para revisar', '']] },
        lista: { nome: 'Lista', secoes: [['Lista', '']] },
    };
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const secoes = m => (MODELOS[m] || MODELOS.branco).secoes.map(([t, texto]) => ({ id: uid(), t, texto }));

    const extras = {
        docModelos: Object.entries(MODELOS).map(([id, m]) => ({ id, nome: m.nome })),
        docFonte(b) { return FONTES[b.dados.fonte] || FONTES.sans; },
        /** Garante o formato (cartão antigo ou colado da biblioteca sem tudo). */
        docGarantir(b) {
            const d = b.dados || (b.dados = {});
            if (!Array.isArray(d.secoes) || !d.secoes.length) d.secoes = secoes(d.modelo || 'branco');
            d.modelo ??= 'branco'; d.layout ??= 'coluna'; d.fonte ??= 'sans'; d.cor ??= '#2f6fc7';
        },
        novoDocumento(sec) {
            return this.wfNovoCartao(sec, 'documento', 'Documento', 520, 520,
                { modelo: 'branco', layout: 'coluna', fonte: 'sans', cor: '#2f6fc7', secoes: secoes('branco') },
                'Documento adicionado ao fim da aba. Arraste para onde quiser.');
        },
        docTrocarModelo(b, m) {
            if (!m || !MODELOS[m]) return;
            const preenchido = (b.dados.secoes || []).some(s => String(s.texto || '').trim());
            if (preenchido && !confirm('Trocar o modelo substitui as seções atuais. Continuar?')) return;
            b.dados.modelo = m;
            b.dados.secoes = secoes(m);
            if (m === 'curriculo') { b.dados.layout = 'faixa'; b.dados.fonte = 'sans'; }
            if (m === 'contrato') { b.dados.layout = 'coluna'; b.dados.fonte = 'serif'; }
            if (b.titulo === 'Documento' || Object.values(MODELOS).some(x => x.nome === b.titulo)) b.titulo = MODELOS[m].nome;
        },
        docImprimir(b) {
            const d = b.dados, w = window.open('', '_blank');
            const cor = /^#[0-9a-f]{3,8}$/i.test(String(d.cor || '')) ? d.cor : '#1b1f24'; // vai dentro do <style>: só uma cor
            if (!w) { this.aviso('Permita pop-ups para imprimir.'); return; }
            const secs = (d.secoes || []).map((s, i) => `<section class="${i === 0 && d.layout === 'faixa' ? 'cab' : ''}"><h2>${esc(s.t)}</h2><p>${esc(s.texto).replace(/\n/g, '<br>')}</p></section>`).join('');
            w.document.write(`<!doctype html><html lang="pt-BR"><title>${esc(b.titulo)}</title><style>
                body{font-family:${FONTES[d.fonte] || FONTES.sans};color:#1b1f24;margin:2cm;line-height:1.5}
                h2{font-size:1rem;margin:0 0 .25rem;border-bottom:2px solid ${cor};padding-bottom:.15rem}
                section{break-inside:avoid;margin-bottom:1rem}p{margin:0}
                .cab{background:${cor};color:#fff;padding:1rem;border-radius:6px}.cab h2{border-color:#fff3;font-size:1.5rem}
                main{${d.layout === 'duas' ? 'columns:2;column-gap:1.5cm' : ''}}
                </style><main>${secs}</main><script>onload=()=>print()<\/script></html>`);
            w.document.close();
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco) && !this.tiposBloco.some(x => x.id === 'documento')) {
                const e = this.estado || {};
                const trazido = Array.isArray(e.tiposTrazidos) && e.tiposTrazidos.includes('documento');
                const tem = Array.isArray(e.blocos) && e.blocos.some(b => b && b.tipo === 'documento');
                if (trazido || tem) this.tiposBloco.push({ id: 'documento', nome: 'Documento' });
            }
            const r = prep.apply(this, arguments);
            if (primeira && typeof this.novoBloco === 'function' && !this._docNovo) {
                this._docNovo = true;
                const novo = this.novoBloco;
                // a Mente cria o documento do jeito dela (mente.js); nas outras áreas, por aqui
                this.novoBloco = function (sec, tipo, ...resto) {
                    if (tipo === 'documento' && !this._docDaArea) return this.novoDocumento(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            return r;
        };
        return Object.assign(m, extras);
    };
})();
