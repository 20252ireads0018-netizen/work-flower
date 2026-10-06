/* Work Flower · Documentos
   Modelos, elementos, formatação de texto, renderização (prévia/impressão) e métodos do editor.
   Depende de quadro.js e deve ser carregado antes de carteira.js. */
(function () {
    'use strict';

    const Q = window.WFQuadro;
    const { uid, num } = Q.util;
    const PALETA = Q.PALETA;

    /* ================= utilidades ================= */
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const brl = n => (Number(n) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const corOk = (c, fb = '#2f6fc7') => /^#[0-9a-f]{6}$/i.test(c || '') ? c : fb;
    const contraste = hex => {
        const n = parseInt(corOk(hex).slice(1), 16);
        const lum = 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255);
        return lum > 165 ? '#111827' : '#ffffff';
    };
    function numBR(v) {
        let s = String(v ?? '').replace(/[^\d,.\-]/g, '');
        if (!s) return 0;
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
        const n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    /* ================= catálogos ================= */
    const FONTES = {
        sans: "'DM Sans', Arial, sans-serif",
        serif: "Georgia, 'Times New Roman', serif",
        mono: "ui-monospace, Menlo, Consolas, monospace",
        moderna: "'Trebuchet MS', 'Segoe UI', Arial, sans-serif",
        elegante: "'Palatino Linotype', Palatino, 'Book Antiqua', Georgia, serif",
        classica: "'Times New Roman', Times, serif",
        humanista: "Verdana, Geneva, Tahoma, sans-serif",
    };
    const FONTES_LISTA = [
        { id: 'sans', nome: 'Sem serifa' }, { id: 'moderna', nome: 'Moderna' }, { id: 'humanista', nome: 'Humanista' },
        { id: 'serif', nome: 'Com serifa' }, { id: 'elegante', nome: 'Elegante' }, { id: 'classica', nome: 'Clássica' },
        { id: 'mono', nome: 'Monoespaçada' },
    ];
    const CORES = ['#2f6fc7', '#0f766e', '#7c3aed', '#be123c', '#c2410c', '#b45309', '#15803d', '#0e7490', '#a21caf', '#1f2937'];
    const CORES_TEXTO = ['#1b1f24', '#000000', '#374151', '#1e3a5f', '#3b2f2f'];

    const ESTILOS = [
        { id: 'classico', nome: 'Clássico', mini: '<rect x="4" y="4" width="18" height="4" rx="1"/><rect x="4" y="10" width="32" height="1.6"/><rect x="4" y="16" width="30" height="1.5" opacity=".4"/><rect x="4" y="20" width="24" height="1.5" opacity=".4"/>' },
        { id: 'moderno', nome: 'Moderno', mini: '<rect x="2" y="2" width="36" height="10" rx="2"/><rect x="4" y="16" width="2.5" height="5"/><rect x="9" y="17" width="24" height="1.5" opacity=".45"/><rect x="9" y="21" width="18" height="1.5" opacity=".45"/>' },
        { id: 'minimal', nome: 'Minimalista', mini: '<rect x="4" y="4" width="14" height="2.2" opacity=".8"/><rect x="4" y="9" width="32" height=".8"/><rect x="4" y="14" width="10" height="1.2"/><rect x="4" y="19" width="30" height="1.2" opacity=".4"/>' },
        { id: 'executivo', nome: 'Executivo', mini: '<rect x="6" y="3" width="28" height="1"/><rect x="12" y="6.5" width="16" height="3"/><rect x="6" y="12" width="28" height="1"/><rect x="6" y="17" width="28" height="1.5" opacity=".4"/><rect x="6" y="21" width="20" height="1.5" opacity=".4"/>' },
        { id: 'criativo', nome: 'Criativo', mini: '<rect x="2" y="3" width="3" height="11"/><rect x="5" y="3" width="33" height="11" opacity=".18"/><rect x="4" y="18" width="14" height="4" rx="2"/><rect x="21" y="19" width="15" height="1.5" opacity=".4"/>' },
    ];

    const TONS = [
        { id: 'cor', nome: 'Cor do documento', cor: null },
        { id: 'info', nome: 'Informação', cor: '#2f6fc7' },
        { id: 'ok', nome: 'Sucesso', cor: '#1f9d55' },
        { id: 'alerta', nome: 'Atenção', cor: '#d97706' },
        { id: 'erro', nome: 'Importante', cor: '#dc2626' },
        { id: 'neutro', nome: 'Neutro', cor: '#6b7280' },
    ];
    const ALINS = [
        { id: 'left', nome: 'Esquerda', icone: '<path d="M4 6h16M4 10h10M4 14h16M4 18h10"/>' },
        { id: 'center', nome: 'Centro', icone: '<path d="M4 6h16M7 10h10M4 14h16M7 18h10"/>' },
        { id: 'right', nome: 'Direita', icone: '<path d="M4 6h16M10 10h10M4 14h16M10 18h10"/>' },
        { id: 'justify', nome: 'Justificado', icone: '<path d="M4 6h16M4 10h16M4 14h16M4 18h16"/>' },
    ];
    const PAPEIS = {
        a4: { nome: 'A4', css: 'A4', w: 794, h: 1123 },
        carta: { nome: 'Carta', css: 'Letter', w: 816, h: 1056 },
    };
    const PAPEIS_LISTA = Object.entries(PAPEIS).map(([id, p]) => ({ id, nome: p.nome }));
    const MARGEM_MM = { p: 10, m: 15, g: 20 };
    const MARGEM_PX = { p: 38, m: 57, g: 76 };

    const ELEMENTOS = [
        { id: 'texto', nome: 'Texto', desc: 'Parágrafos, listas e formatação', icone: '<path d="M5 6h14M12 6v13M9 19h6"/>' },
        { id: 'experiencia', nome: 'Experiência', desc: 'Cargos, datas e detalhes em linha do tempo', icone: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/>' },
        { id: 'habilidades', nome: 'Habilidades', desc: 'Barras, pontos ou etiquetas', icone: '<path d="M4 7h10M4 12h16M4 17h7"/>' },
        { id: 'tabela', nome: 'Tabela', desc: 'Com soma automática opcional', icone: '<rect x="3" y="4" width="18" height="16" rx="1.5"/><path d="M3 10h18M9 4v16"/>' },
        { id: 'destaque', nome: 'Destaque', desc: 'Caixa colorida para avisos e notas', icone: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9v6"/>' },
        { id: 'citacao', nome: 'Citação', desc: 'Frase em evidência com autor', icone: '<path d="M7 7h4v5H7zM13 7h4v5h-4zM9 12c0 3-1 4-3 5M15 12c0 3-1 4-3 5"/>' },
        { id: 'imagem', nome: 'Imagem', desc: 'Foto, logo ou ilustração', icone: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M4 18l5-5 4 4 3-3 4 4"/>' },
        { id: 'assinatura', nome: 'Assinaturas', desc: 'Linhas de assinatura', icone: '<path d="M4 17c3-6 5-6 6-2s3 2 4-1 3-2 6 0M4 21h16"/>' },
        { id: 'divisor', nome: 'Divisor', desc: 'Linha separadora', icone: '<path d="M4 12h16"/>' },
        { id: 'espaco', nome: 'Espaço', desc: 'Espaço em branco ajustável', icone: '<path d="M12 4v16M8 8l4-4 4 4M8 16l4 4 4-4"/>' },
        { id: 'quebra', nome: 'Quebra de página', desc: 'Começa uma nova página', icone: '<path d="M4 12h3M10 12h4M17 12h3M8 5h8v4H8zM8 15h8v4H8z"/>' },
    ];
    const TEM_TITULO = ['texto', 'experiencia', 'habilidades', 'tabela'];

    /* ================= elementos (fábrica) ================= */
    function novoItemExp() { return { id: uid(), cargo: '', local: '', periodo: '', desc: '' }; }

    function novaSecao(tipo, o = {}) {
        const base = { id: uid(), tipo, titulo: '', texto: '', dica: '', larg: 'inteira', alin: 'left', mostrarTitulo: true, oculta: false };
        const por = {
            texto: { titulo: 'Nova seção' },
            experiencia: { titulo: 'Experiência', estilo: 'linha', itens: [novoItemExp()] },
            habilidades: { titulo: 'Habilidades', estilo: 'barras', itens: [] },
            tabela: { titulo: 'Tabela', cab: ['Descrição', 'Qtd.', 'Valor (R$)'], linhas: [['', '', ''], ['', '', '']], total: false, zebra: true },
            destaque: { titulo: 'Atenção', tom: 'cor' },
            citacao: { mostrarTitulo: false, autor: '', alin: 'left' },
            imagem: { mostrarTitulo: false, src: '', largura: 60, alin: 'center', legenda: '', arred: '8' },
            divisor: { mostrarTitulo: false, estilo: 'linha' },
            espaco: { mostrarTitulo: false, altura: 24 },
            quebra: { mostrarTitulo: false },
            assinatura: { mostrarTitulo: false, assinantes: [{ id: uid(), nome: '', papel: 'Contratante' }, { id: uid(), nome: '', papel: 'Contratada' }] },
        };
        return { ...base, ...(por[tipo] || {}), ...o };
    }

    /* ================= modelos ================= */
    const S = novaSecao;
    const MODELOS = {
        curriculo: {
            tipo: 'curriculo', nome: 'Currículo clássico', desc: 'Simples e direto', titulo: 'Meu currículo',
            icone: '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/>',
            layout: { cab: 'esq', estilo: 'classico', fonte: 'sans' },
            secoes: () => [
                S('texto', { titulo: 'Resumo profissional', dica: 'Conte em 3 ou 4 linhas quem você é e o que busca.' }),
                S('experiencia', { titulo: 'Experiência' }),
                S('texto', { titulo: 'Formação', dica: 'Curso, instituição e ano.' }),
                S('habilidades', { titulo: 'Habilidades' }),
            ],
        },
        'curriculo-moderno': {
            tipo: 'curriculo', nome: 'Currículo moderno', desc: 'Faixa colorida e habilidades em barras', titulo: 'Meu currículo',
            icone: '<rect x="3" y="3" width="18" height="7" rx="1.5"/><path d="M6 14h12M6 18h8"/>',
            layout: { cab: 'esq', estilo: 'moderno', fonte: 'moderna', cor: '#0f766e' },
            secoes: () => [
                S('texto', { titulo: 'Resumo profissional', dica: 'Conte em 3 ou 4 linhas quem você é e o que busca.' }),
                S('experiencia', { titulo: 'Experiência' }),
                S('texto', { titulo: 'Formação', larg: 'metade', dica: 'Curso, instituição e ano.' }),
                S('habilidades', { titulo: 'Habilidades', larg: 'metade' }),
            ],
        },
        contrato: {
            tipo: 'contrato', nome: 'Contrato', desc: 'Cláusulas numeradas e assinaturas', titulo: 'Contrato de prestação de serviços',
            icone: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h4"/>',
            layout: { cab: 'centro', estilo: 'executivo', fonte: 'serif', cor: '#1f2937', numerar: 'clausula', justificar: true },
            secoes: () => [
                S('texto', { titulo: 'Partes', alin: 'justify', dica: 'Identifique contratante e contratada: nome, documento e endereço.' }),
                S('texto', { titulo: 'Objeto', alin: 'justify', dica: 'Descreva o serviço ou produto contratado.' }),
                S('texto', { titulo: 'Valor e pagamento', alin: 'justify', dica: 'Valor, forma e prazo de pagamento.' }),
                S('texto', { titulo: 'Prazo', alin: 'justify', dica: 'Início, duração e condições de rescisão.' }),
                S('texto', { titulo: 'Disposições gerais', alin: 'justify', dica: 'Foro, confidencialidade e demais condições.' }),
                S('assinatura'),
            ],
        },
        proposta: {
            tipo: 'proposta', nome: 'Proposta comercial', desc: 'Escopo, investimento e aceite', titulo: 'Proposta comercial',
            icone: '<path d="M4 20V10l8-6 8 6v10zM9 20v-6h6v6"/>',
            layout: { cab: 'esq', estilo: 'moderno', fonte: 'moderna', cor: '#2f6fc7' },
            secoes: () => [
                S('texto', { titulo: 'Apresentação', dica: 'Apresente a empresa e o objetivo da proposta.' }),
                S('texto', { titulo: 'Escopo', dica: '- Entrega 1\n- Entrega 2' }),
                S('tabela', { titulo: 'Investimento', total: true }),
                S('texto', { titulo: 'Prazo e condições', dica: 'Prazos de entrega, forma de pagamento e garantias.' }),
                S('destaque', { titulo: 'Validade', texto: 'Esta proposta é válida por 15 dias.', tom: 'cor' }),
                S('assinatura'),
            ],
        },
        orcamento: {
            tipo: 'orcamento', nome: 'Orçamento', desc: 'Tabela com total automático', titulo: 'Orçamento',
            icone: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h3M13 12h3M8 16h3M13 16h3"/>',
            layout: { cab: 'esq', estilo: 'classico', fonte: 'sans', cor: '#15803d' },
            secoes: () => [
                S('tabela', { titulo: 'Itens', total: true, cab: ['Item', 'Qtd.', 'Valor (R$)'], linhas: [['', '', ''], ['', '', ''], ['', '', '']] }),
                S('destaque', { titulo: 'Observações', texto: 'Orçamento válido por 7 dias.', tom: 'neutro' }),
            ],
        },
        carta: {
            tipo: 'carta', nome: 'Carta formal', desc: 'Sem cabeçalho, pronta para escrever', titulo: 'Carta',
            icone: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
            layout: { cabecalho: false, estilo: 'minimal', fonte: 'elegante', cor: '#1f2937' },
            secoes: () => [
                S('texto', { mostrarTitulo: false, titulo: 'Local e data', alin: 'right', dica: 'Cidade, data' }),
                S('texto', { mostrarTitulo: false, titulo: 'Destinatário', dica: 'Prezado(a) Senhor(a),' }),
                S('texto', { mostrarTitulo: false, titulo: 'Corpo', alin: 'justify', dica: 'Escreva sua mensagem aqui.' }),
                S('texto', { mostrarTitulo: false, titulo: 'Fechamento', dica: 'Atenciosamente,' }),
                S('assinatura', { assinantes: [{ id: uid(), nome: '', papel: '' }] }),
            ],
        },
        recibo: {
            tipo: 'recibo', nome: 'Recibo', desc: 'Comprovante de pagamento', titulo: 'Recibo',
            icone: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
            layout: { cab: 'centro', estilo: 'executivo', fonte: 'serif', cor: '#1f2937' },
            secoes: () => [
                S('destaque', { titulo: 'Valor', texto: 'R$ 0,00', tom: 'cor', alin: 'center' }),
                S('texto', { mostrarTitulo: false, titulo: 'Texto', alin: 'justify', dica: 'Recebi de [nome] a quantia de [valor] referente a [descrição].' }),
                S('texto', { mostrarTitulo: false, titulo: 'Local e data', alin: 'right', dica: 'Cidade, data' }),
                S('assinatura', { assinantes: [{ id: uid(), nome: '', papel: 'Recebedor' }] }),
            ],
        },
        ata: {
            tipo: 'ata', nome: 'Ata de reunião', desc: 'Pauta, decisões e responsáveis', titulo: 'Ata de reunião',
            icone: '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            layout: { cab: 'esq', estilo: 'minimal', fonte: 'sans', cor: '#7c3aed' },
            secoes: () => [
                S('texto', { titulo: 'Pauta', dica: '- Assunto 1\n- Assunto 2' }),
                S('texto', { titulo: 'Participantes', dica: '- Nome — cargo' }),
                S('texto', { titulo: 'Discussão' }),
                S('tabela', { titulo: 'Próximos passos', cab: ['Ação', 'Responsável', 'Prazo'], linhas: [['', '', ''], ['', '', '']], total: false }),
            ],
        },
        livre: {
            tipo: 'livre', nome: 'Documento em branco', desc: 'Comece do zero', titulo: 'Novo documento',
            icone: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
            layout: { cab: 'centro', estilo: 'classico', fonte: 'sans' },
            secoes: () => [S('texto', { titulo: 'Texto' })],
        },
    };
    const MODELOS_LISTA = Object.entries(MODELOS).map(([chave, m]) => ({ chave, nome: m.nome, desc: m.desc, icone: m.icone }));
    const TIPOS_NOME = { curriculo: 'Currículo', contrato: 'Contrato', proposta: 'Proposta', orcamento: 'Orçamento', carta: 'Carta', recibo: 'Recibo', ata: 'Ata de reunião', livre: 'Documento' };

    /* ================= migração (documentos antigos continuam válidos) ================= */
    function migrar(d) {
        d.tipo ??= 'livre';
        d.titulo ??= 'Documento';
        d.layout ??= {};
        const L = d.layout;
        L.fonte ??= 'sans'; L.tam ??= 'm'; L.cor ??= PALETA[0]; L.cab ??= 'centro';
        L.estilo ??= 'classico'; L.corTexto ??= '#1b1f24'; L.linha ??= '1.5';
        L.papel ??= 'a4'; L.margem ??= 'm'; L.numerar ??= 'nao'; L.rodape ??= ''; L.marca ??= '';
        L.cabecalho ??= true; L.fotoForma ??= 'redonda';
        d.campos ??= {};
        ['nome', 'cargo', 'email', 'tel', 'cidade', 'site', 'subtitulo', 'data', 'foto'].forEach(k => { d.campos[k] ??= ''; });
        d.secoes ??= [];
        d.secoes.forEach(s => {
            s.tipo ??= 'texto';
            Object.entries(novaSecao(s.tipo)).forEach(([k, v]) => { if (s[k] === undefined) s[k] = v; });
            if (L.justificar && s.alin === 'left' && s.tipo === 'texto' && !s.__j) { /* mantém o que o usuário escolheu */ }
        });
        return d;
    }

    /* ================= formatação de texto (markdown simplificado) ================= */
    function emph(s, c) {
        return s
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/__(.+?)__/g, '<u>$1</u>')
            .replace(/~~(.+?)~~/g, '<s>$1</s>')
            .replace(/==(.+?)==/g, `<mark style="background:${c}38;color:inherit;padding:0 2px;border-radius:2px">$1</mark>`)
            .replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
    }
    function fmt(txt, c) {
        const links = [];
        let s = esc(String(txt ?? '').replace(/\u0000/g, '')).replace(/\[([^\]\n]+)\]\(([^)\s]+)\)/g, (m, t, u) => {
            if (!/^(https?:\/\/|mailto:|tel:)/i.test(u)) return m;
            links.push(`<a href="${u}" target="_blank" rel="noopener" style="color:${c};text-decoration:underline">${emph(t, c)}</a>`);
            return `\u0000${links.length - 1}\u0000`;
        });
        s = emph(s, c);
        return s.replace(/\u0000(\d+)\u0000/g, (m, i) => links[i]);
    }
    function blocos(txt, c, alin = 'left') {
        const bruto = String(txt ?? '').replace(/\s+$/, '');
        if (!bruto.trim()) return '';
        const al = ['left', 'center', 'right', 'justify'].includes(alin) ? alin : 'left';
        let out = '', lista = '';
        const fecha = () => { if (lista) { out += `</${lista}>`; lista = ''; } };
        const abre = t => {
            if (lista === t) return;
            fecha();
            out += t === 'ul' ? '<ul style="margin:.25em 0;padding-left:1.4em">' : '<ol style="margin:.25em 0;padding-left:1.7em">';
            lista = t;
        };
        bruto.split('\n').forEach(raw => {
            const l = raw.replace(/\s+$/, '');
            let m;
            if (!l.trim()) { fecha(); out += '<div style="height:.6em"></div>'; return; }
            if ((m = l.match(/^\s*[-•*]\s+(.+)$/))) { abre('ul'); out += `<li style="margin:.15em 0">${fmt(m[1], c)}</li>`; return; }
            if ((m = l.match(/^\s*\d+[.)]\s+(.+)$/))) { abre('ol'); out += `<li style="margin:.15em 0">${fmt(m[1], c)}</li>`; return; }
            fecha();
            if ((m = l.match(/^##\s+(.+)$/))) { out += `<div style="font-weight:700;color:${c};margin:.7em 0 .2em;font-size:1.08em;break-after:avoid">${fmt(m[1], c)}</div>`; return; }
            if ((m = l.match(/^#\s+(.+)$/))) { out += `<div style="font-weight:800;color:${c};margin:.8em 0 .25em;font-size:1.3em;break-after:avoid">${fmt(m[1], c)}</div>`; return; }
            if (/^---+$/.test(l.trim())) { out += '<div style="border-top:1px solid #cfd5dc;margin:.7em 0"></div>'; return; }
            if ((m = l.match(/^>\s?(.*)$/))) { out += `<div style="border-left:3px solid ${c};padding:.1em 0 .1em .8em;margin:.35em 0;opacity:.85;font-style:italic">${fmt(m[1], c)}</div>`; return; }
            out += `<p style="margin:0 0 .35em;orphans:2;widows:2">${fmt(l, c)}</p>`;
        });
        fecha();
        return `<div style="text-align:${al}">${out}</div>`;
    }

    /* ================= renderização ================= */
    function tituloSec(t, X) {
        const { c, E, sz } = X;
        const b = 'break-after:avoid;page-break-after:avoid;';
        switch (E) {
            case 'moderno':
                return `<h2 style="${b}border-left:5px solid ${c};padding:1px 0 1px 10px;color:${c};font-weight:700;font-size:${sz * 1.1}px;margin:${sz * 1.3}px 0 ${sz * .5}px">${t}</h2>`;
            case 'minimal':
                return `<h2 style="${b}color:${c};font-weight:600;font-size:${sz * .8}px;letter-spacing:.16em;text-transform:uppercase;border-bottom:1px solid #dfe3e8;padding-bottom:4px;margin:${sz * 1.4}px 0 ${sz * .6}px">${t}</h2>`;
            case 'executivo':
                return `<h2 style="${b}color:${c};font-weight:700;font-size:${sz * .95}px;letter-spacing:.08em;text-transform:uppercase;border-bottom:1px solid ${c};padding-bottom:3px;margin:${sz * 1.4}px 0 ${sz * .55}px">${t}</h2>`;
            case 'criativo':
                return `<h2 style="${b}margin:${sz * 1.3}px 0 ${sz * .6}px;font-size:${sz * .95}px"><span style="display:inline-block;background:${c};color:${contraste(c)};font-weight:700;padding:3px 14px;border-radius:999px">${t}</span></h2>`;
            default:
                return `<h2 style="${b}font-weight:700;font-size:${sz * 1.05}px;color:${c};border-bottom:2px solid ${c};padding-bottom:2px;margin:${sz * 1.3}px 0 ${sz * .5}px">${t}</h2>`;
        }
    }

    function cabecalho(d, X) {
        const L = d.layout, K = d.campos || {}, { c, sz, E } = X;
        if (L.cabecalho === false) return '';
        const curr = d.tipo === 'curriculo';
        const nome = curr ? (K.nome || 'Seu nome') : (d.titulo || 'Documento');
        const sub = curr ? K.cargo : K.subtitulo;
        const info = (curr ? [K.email, K.tel, K.cidade, K.site] : [K.data]).filter(Boolean).map(esc).join(' · ');
        const foto = /^data:image\//.test(K.foto || '')
            ? (curr
                ? `<img src="${esc(K.foto)}" alt="" style="width:88px;height:88px;object-fit:cover;flex:none;border-radius:${L.fotoForma === 'quadrada' ? '10px' : '50%'}"/>`
                : `<img src="${esc(K.foto)}" alt="" style="height:60px;width:auto;max-width:200px;object-fit:contain;flex:none;border-radius:6px"/>`)
            : '';
        const centro = L.cab === 'centro' || E === 'executivo';
        const h1Extra = E === 'minimal' ? 'font-weight:300;letter-spacing:.05em;'
            : E === 'executivo' ? 'text-transform:uppercase;letter-spacing:.14em;font-weight:700;'
            : E === 'criativo' ? 'font-weight:800;' : 'font-weight:700;';
        const corH1 = E === 'moderno' ? contraste(c) : E === 'minimal' ? X.tx : c;
        const tam = (curr ? 2 : 1.8) * sz * (E === 'executivo' ? .85 : 1);
        const texto = `<div style="min-width:0"><h1 style="margin:0;font-size:${tam}px;line-height:1.15;color:${corH1};${h1Extra}">${esc(nome)}</h1>`
            + (sub ? `<div style="margin-top:4px;font-size:${sz * 1.1}px">${esc(sub)}</div>` : '')
            + (info ? `<div style="margin-top:3px;font-size:.92em;opacity:.78">${info}</div>` : '') + '</div>';
        const base = `display:flex;flex-direction:${centro ? 'column' : 'row'};align-items:center;gap:18px;text-align:${centro ? 'center' : 'left'};${centro ? 'justify-content:center;' : ''}margin-bottom:${sz * .4}px;`;
        const extra = {
            moderno: `background:${c};color:${contraste(c)};padding:22px 26px;border-radius:8px;`,
            minimal: 'border-bottom:1px solid #dfe3e8;padding-bottom:16px;',
            executivo: `border-top:3px double ${c};border-bottom:3px double ${c};padding:16px 0;`,
            criativo: `background:${c}14;border-left:10px solid ${c};padding:18px 22px;border-radius:0 8px 8px 0;`,
        }[E] || '';
        return `<div style="${base}${extra}">${foto}${texto}</div>`;
    }

    function corpo(s, X) {
        const { c, sz } = X;
        switch (s.tipo) {
            case 'experiencia': {
                const its = (s.itens || []).filter(i => [i.cargo, i.local, i.periodo, i.desc].some(v => String(v || '').trim()));
                if (!its.length) return '';
                const tl = s.estilo !== 'lista';
                const lista = its.map(i => `<div style="position:relative;margin:0 0 ${sz * .8}px;break-inside:avoid">`
                    + (tl ? `<span style="position:absolute;left:-20px;top:.5em;width:10px;height:10px;border-radius:50%;background:${c}"></span>` : '')
                    + `<div style="display:flex;justify-content:space-between;gap:12px;align-items:baseline"><div><strong>${esc(i.cargo)}</strong>${i.local ? ` <span style="opacity:.75">· ${esc(i.local)}</span>` : ''}</div>`
                    + (i.periodo ? `<div style="opacity:.7;font-size:.9em;white-space:nowrap">${esc(i.periodo)}</div>` : '') + '</div>'
                    + (i.desc ? `<div style="margin-top:2px">${blocos(i.desc, c, 'left')}</div>` : '') + '</div>').join('');
                return tl ? `<div style="border-left:2px solid ${c}55;margin-left:5px;padding-left:14px">${lista}</div>` : lista;
            }
            case 'habilidades': {
                const its = (s.itens || []).filter(i => String(i.nome || '').trim());
                if (!its.length) return '';
                const nv = i => Math.min(5, Math.max(1, Math.round(num(i.nivel) || 3)));
                if (s.estilo === 'etiquetas')
                    return '<div>' + its.map(i => `<span style="display:inline-block;margin:0 6px 6px 0;padding:2px 11px;border:1px solid ${c};color:${c};border-radius:999px;font-size:.92em">${esc(i.nome)}</span>`).join('') + '</div>';
                if (s.estilo === 'pontos')
                    return its.map(i => `<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:.3em 0;break-inside:avoid"><span>${esc(i.nome)}</span><span style="white-space:nowrap">`
                        + [1, 2, 3, 4, 5].map(k => `<span style="display:inline-block;width:9px;height:9px;margin-left:3px;border-radius:50%;background:${k <= nv(i) ? c : c + '30'}"></span>`).join('') + '</span></div>').join('');
                return its.map(i => `<div style="margin:.45em 0;break-inside:avoid"><div style="font-size:.92em;margin-bottom:3px">${esc(i.nome)}</div>`
                    + `<div style="height:6px;border-radius:99px;background:${c}30"><div style="height:100%;width:${nv(i) * 20}%;border-radius:99px;background:${c}"></div></div></div>`).join('');
            }
            case 'tabela': {
                const cab = s.cab || [], lin = s.linhas || [];
                if (!cab.length) return '';
                const ult = cab.length - 1, tot = s.total && cab.length > 1;
                const th = `background:${c};color:${contraste(c)};padding:6px 9px;text-align:left;font-weight:600;font-size:.92em`;
                const td = 'padding:6px 9px;border-bottom:1px solid #dfe3e8';
                let h = '<table style="width:100%;border-collapse:collapse;margin:.3em 0"><thead><tr>'
                    + cab.map((x, j) => `<th style="${th}${tot && j === ult ? ';text-align:right' : ''}">${fmt(x, contraste(c))}</th>`).join('') + '</tr></thead><tbody>';
                lin.forEach((l, i) => {
                    h += `<tr style="break-inside:avoid;${s.zebra && i % 2 ? `background:${c}10` : ''}">`
                        + cab.map((_, j) => `<td style="${td}${tot && j === ult ? ';text-align:right;white-space:nowrap' : ''}">${fmt(l[j] ?? '', c)}</td>`).join('') + '</tr>';
                });
                if (tot) {
                    const t = lin.reduce((a, l) => a + numBR(l[ult]), 0);
                    h += `<tr style="break-inside:avoid"><td colspan="${cab.length - 1}" style="padding:7px 9px;text-align:right;font-weight:700">Total</td>`
                        + `<td style="padding:7px 9px;text-align:right;font-weight:700;border-top:2px solid ${c};white-space:nowrap">${brl(t)}</td></tr>`;
                }
                return h + '</tbody></table>';
            }
            case 'destaque': {
                const tom = TONS.find(t => t.id === s.tom)?.cor || c;
                const tit = s.mostrarTitulo !== false && String(s.titulo || '').trim()
                    ? `<div style="font-weight:700;color:${tom};margin-bottom:3px">${esc(s.titulo)}</div>` : '';
                const txt = blocos(s.texto, tom, s.alin);
                if (!tit && !txt) return '';
                return `<div style="background:${tom}18;border-left:4px solid ${tom};border-radius:6px;padding:10px 14px;margin:.6em 0;break-inside:avoid">${tit}${txt}</div>`;
            }
            case 'citacao': {
                const txt = blocos(s.texto, c, s.alin);
                if (!txt) return '';
                return `<div style="border-left:4px solid ${c};padding:.4em 0 .4em 16px;margin:.7em 0;font-style:italic;font-size:1.12em;break-inside:avoid">${txt}`
                    + (s.autor ? `<div style="font-style:normal;font-size:.8em;opacity:.7;margin-top:.3em">— ${esc(s.autor)}</div>` : '') + '</div>';
            }
            case 'imagem': {
                if (!/^data:image\//.test(s.src || '')) return '';
                const w = Math.min(100, Math.max(10, num(s.largura) || 60));
                const r = s.arred === 'circulo' ? '50%' : `${Math.min(40, num(s.arred))}px`;
                return `<div style="text-align:${s.alin || 'center'};margin:.6em 0;break-inside:avoid"><img src="${esc(s.src)}" alt="${esc(s.legenda || '')}" style="width:${w}%;max-width:100%;height:auto;border-radius:${r}"/>`
                    + (s.legenda ? `<div style="font-size:.82em;opacity:.7;margin-top:4px">${esc(s.legenda)}</div>` : '') + '</div>';
            }
            case 'divisor': {
                const e = { linha: `1px solid #cfd5dc`, tracejada: '1px dashed #9aa3af', pontilhada: '2px dotted #9aa3af', dupla: `3px double ${c}`, grossa: `3px solid ${c}` }[s.estilo] || '1px solid #cfd5dc';
                return `<div style="border-top:${e};margin:${sz}px 0"></div>`;
            }
            case 'espaco':
                return `<div style="height:${Math.min(400, Math.max(4, num(s.altura) || 24))}px"></div>`;
            case 'assinatura': {
                const a = s.assinantes || [];
                if (!a.length) return '';
                return `<div style="display:flex;gap:36px;margin-top:${sz * 3}px;break-inside:avoid">` + a.map(x =>
                    `<div style="flex:1;text-align:center;min-width:0"><div style="border-top:1px solid ${X.tx};padding-top:4px;font-weight:600">${esc(x.nome) || '&nbsp;'}</div>`
                    + (x.papel ? `<div style="font-size:.85em;opacity:.7">${esc(x.papel)}</div>` : '') + '</div>').join('') + '</div>';
            }
            default:
                return blocos(s.texto, c, s.alin);
        }
    }

    /** HTML do documento com estilos inline. opt.impressao = sem margens (o @page cuida delas). */
    function render(d, opt = {}) {
        const L = d.layout;
        const X = {
            c: corOk(L.cor), tx: corOk(L.corTexto, '#1b1f24'), E: L.estilo || 'classico',
            sz: { p: 12, m: 14, g: 16 }[L.tam] || 14,
        };
        const lh = num(L.linha) || 1.5;
        const pad = opt.impressao ? 0 : (MARGEM_PX[L.margem] ?? 57);
        let cont = 0;

        const itens = (d.secoes || []).filter(s => !s.oculta).map(s => {
            let tit = '';
            if (TEM_TITULO.includes(s.tipo) && s.mostrarTitulo !== false && String(s.titulo || '').trim()) {
                let pref = '';
                if (L.numerar && L.numerar !== 'nao') { cont++; pref = L.numerar === 'clausula' ? `Cláusula ${cont} – ` : `${cont}. `; }
                tit = tituloSec(esc(pref + s.titulo), X);
            }
            return { s, html: tit + corpo(s, X) };
        });

        let fluxo = '';
        for (let i = 0; i < itens.length; i++) {
            const it = itens[i];
            if (it.s.tipo === 'quebra') {
                fluxo += opt.impressao
                    ? '<div style="break-after:page;page-break-after:always;height:0"></div>'
                    : '<div style="margin:1em 0;border-top:2px dashed #94a3b8;text-align:center;font-size:11px;line-height:0;color:#64748b"><span style="background:#fff;padding:0 8px">quebra de página</span></div>';
                continue;
            }
            if (it.s.larg === 'metade') {
                const prox = itens[i + 1];
                if (prox && prox.s.larg === 'metade' && prox.s.tipo !== 'quebra') {
                    fluxo += '<div style="display:flex;gap:20px;align-items:flex-start;break-inside:avoid">'
                        + [it, prox].map(x => `<div style="flex:1 1 0;min-width:0">${x.html}</div>`).join('') + '</div>';
                    i++;
                } else fluxo += `<div style="width:calc(50% - 10px)">${it.html}</div>`;
                continue;
            }
            fluxo += `<div>${it.html}</div>`;
        }

        const marca = String(L.marca || '').trim()
            ? `<div style="${opt.impressao ? 'position:fixed;' : 'position:absolute;'}left:0;top:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;pointer-events:none;overflow:hidden;z-index:0">`
              + `<span style="transform:rotate(-30deg);font-size:${X.sz * 6}px;font-weight:800;letter-spacing:.08em;color:rgba(0,0,0,.07);white-space:nowrap">${esc(L.marca)}</span></div>` : '';
        const rodape = String(L.rodape || '').trim()
            ? `<div style="margin-top:${X.sz * 2}px;padding-top:8px;border-top:1px solid #dfe3e8;text-align:center;font-size:.8em;opacity:.7">${fmt(L.rodape, X.c)}</div>` : '';

        return `<div style="font-family:${FONTES[L.fonte] || FONTES.sans};font-size:${X.sz}px;line-height:${lh};color:${X.tx};padding:${pad}px;box-sizing:border-box;position:relative;text-align:left">`
            + marca + `<div style="position:relative;z-index:1">${cabecalho(d, X)}${fluxo}${rodape}</div></div>`;
    }

    /* ================= componentes e métodos ================= */
    window.docPrevia = function () {
        return {
            w: 0, zoom: 'fit', ro: null,
            init() {
                this.$nextTick(() => {
                    if (!this.$refs.area || !window.ResizeObserver) return;
                    this.ro = new ResizeObserver(e => { this.w = e[0].contentRect.width; });
                    this.ro.observe(this.$refs.area);
                });
            },
            destroy() { this.ro?.disconnect(); },
            escala(pw) {
                if (this.zoom === 'fit') return this.w > 0 ? Math.max(.25, Math.min(1.6, (this.w - 8) / pw)).toFixed(3) : 0.5;
                return Number(this.zoom) || 1;
            },
        };
    };

    window.WFDocs = {
        migrar, FONTES_LISTA,

        estadoUI() {
            return {
                docTab: 'conteudo', docFechada: {}, docNovo: false, docTela: false, dragSec: null, dragSobre: null,
                docAbas: [{ id: 'conteudo', nome: 'Conteúdo' }, { id: 'estilo', nome: 'Estilo' }, { id: 'pagina', nome: 'Página' }],
                modelosDoc: MODELOS_LISTA, elementosDoc: ELEMENTOS, estilosDoc: ESTILOS, tonsDoc: TONS, alinsDoc: ALINS,
                fontesDoc: FONTES_LISTA, papeisDoc: PAPEIS_LISTA, docCores: CORES, docCoresTexto: CORES_TEXTO,
            };
        },

        metodos() {
            return {
                /* ---------- documentos ---------- */
                docAtual() { return this.estado.docs.find(d => d.id === this.estado.docAtivo) || null; },
                tipoDoc(t) { return TIPOS_NOME[t] || 'Documento'; },
                novoDoc(chave) {
                    const m = MODELOS[chave] || MODELOS.livre;
                    const d = migrar({ id: uid(), tipo: m.tipo || chave, titulo: m.titulo, layout: { ...m.layout }, campos: {}, secoes: m.secoes() });
                    this.estado.docs.push(d);
                    this.estado.docAtivo = d.id;
                    this.docTab = 'conteudo'; this.docNovo = false; this.docFechada = {};
                    this.aviso('Documento criado.');
                },
                duplicarDoc(d) {
                    const c = JSON.parse(JSON.stringify(d));
                    c.id = uid(); c.titulo = `${d.titulo} (cópia)`;
                    c.secoes.forEach(s => { s.id = uid(); ['itens', 'assinantes'].forEach(k => (s[k] || []).forEach(x => { x.id = uid(); })); });
                    const i = this.estado.docs.findIndex(x => x.id === d.id);
                    this.estado.docs.splice(i + 1, 0, c);
                    this.estado.docAtivo = c.id;
                    this.aviso('Documento duplicado.');
                },
                remDoc(d) {
                    if (!confirm(`Excluir "${d.titulo}"?`)) return;
                    const i = this.estado.docs.findIndex(x => x.id === d.id);
                    if (i >= 0) this.estado.docs.splice(i, 1);
                    if (this.estado.docAtivo === d.id) this.estado.docAtivo = this.estado.docs[0]?.id || null;
                },
                docResumo(d) {
                    if (!d) return '';
                    let t = this.docPalavras(d.titulo);
                    (d.secoes || []).forEach(s => {
                        t += this.docPalavras(s.texto) + this.docPalavras(s.titulo);
                        (s.itens || []).forEach(i => { t += this.docPalavras(i.desc) + this.docPalavras(i.cargo) + this.docPalavras(i.nome); });
                    });
                    return `${t} palavras · ${(d.secoes || []).filter(s => !s.oculta).length} elementos`;
                },
                docPalavras(t) { return String(t || '').trim().split(/\s+/).filter(Boolean).length; },

                /* ---------- elementos ---------- */
                docTemTitulo(t) { return [...TEM_TITULO, 'destaque'].includes(t); },
                docNomeElemento(t) { return ELEMENTOS.find(e => e.id === t)?.nome || 'Elemento'; },
                docIconeElemento(t) { return ELEMENTOS.find(e => e.id === t)?.icone || ''; },
                addSecao(d) { this.addElemento(d, 'texto'); },
                addElemento(d, tipo) {
                    const s = novaSecao(tipo);
                    d.secoes.push(s);
                    this.docTab = 'conteudo';
                    this.$nextTick(() => document.querySelector(`[data-sec="${s.id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
                },
                remSecao(d, i) {
                    const s = d.secoes[i];
                    const tem = String(s.texto || '').trim() || s.src || (s.itens || []).some(x => String(x.cargo || x.nome || x.desc || '').trim());
                    if (tem && !confirm('Remover este elemento e o conteúdo dele?')) return;
                    d.secoes.splice(i, 1);
                },
                moverSecao(d, i, dir) {
                    const j = i + dir;
                    if (j < 0 || j >= d.secoes.length) return;
                    [d.secoes[i], d.secoes[j]] = [d.secoes[j], d.secoes[i]];
                },
                duplicarSecao(d, i) {
                    const c = JSON.parse(JSON.stringify(d.secoes[i]));
                    c.id = uid();
                    ['itens', 'assinantes'].forEach(k => (c[k] || []).forEach(x => { x.id = uid(); }));
                    if (c.titulo) c.titulo += ' (cópia)';
                    d.secoes.splice(i + 1, 0, c);
                },
                iniciarArrasto(ev, i) {
                    this.dragSec = i;
                    if (ev.dataTransfer) {
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', String(i));
                        const card = ev.target.closest('.doc-sec');
                        if (card) ev.dataTransfer.setDragImage(card, 12, 12);
                    }
                },
                soltarSecao(d, i) {
                    const de = this.dragSec;
                    this.dragSec = null; this.dragSobre = null;
                    if (de == null || de === i) return;
                    const [s] = d.secoes.splice(de, 1);
                    d.secoes.splice(i, 0, s);
                },
                docAlternarTodas(d) {
                    const algumAberto = d.secoes.some(s => !this.docFechada[s.id]);
                    const m = {};
                    if (algumAberto) d.secoes.forEach(s => { m[s.id] = true; });
                    this.docFechada = m;
                },
                aplicarEstilo(d, id) {
                    d.layout.estilo = id;
                    if (id === 'executivo') d.layout.cab = 'centro';
                },

                /* ---------- listas internas dos elementos ---------- */
                docMover(lista, i, dir) {
                    const j = i + dir;
                    if (j < 0 || j >= lista.length) return;
                    [lista[i], lista[j]] = [lista[j], lista[i]];
                },
                docRemover(lista, i) { lista.splice(i, 1); },
                docAddExp(s) { s.itens.push(novoItemExp()); },
                docAddHab(s, el) {
                    el.value.split(',').map(t => t.trim()).filter(Boolean).forEach(nome => s.itens.push({ id: uid(), nome, nivel: 4 }));
                    el.value = '';
                },
                docTabLinha(s) { s.linhas.push(s.cab.map(() => '')); },
                docTabCol(s) { s.cab.push('Coluna'); s.linhas.forEach(l => l.push('')); },
                docTabRemCol(s, j) { if (s.cab.length <= 1) return; s.cab.splice(j, 1); s.linhas.forEach(l => l.splice(j, 1)); },
                docAddAssin(s) { s.assinantes.push({ id: uid(), nome: '', papel: 'Assinante' }); },

                /* ---------- imagens (redimensiona e comprime antes de salvar) ---------- */
                docImagem(alvo, chave, file, o = {}) {
                    if (!file) return;
                    if (!/^image\//.test(file.type)) { this.aviso('Escolha um arquivo de imagem.'); return; }
                    if (file.size > 12 * 1024 * 1024) { this.aviso('A imagem passa de 12 MB.'); return; }
                    const leitor = new FileReader();
                    leitor.onload = () => {
                        const img = new Image();
                        img.onload = () => {
                            if (!img.naturalWidth) { alvo[chave] = leitor.result; return; }
                            let sw = img.naturalWidth, sh = img.naturalHeight, sx = 0, sy = 0;
                            if (o.quadrada) { const l = Math.min(sw, sh); sx = (sw - l) / 2; sy = (sh - l) / 2; sw = sh = l; }
                            const fator = Math.min(1, (o.max || 1000) / Math.max(sw, sh));
                            const dw = Math.max(1, Math.round(sw * fator)), dh = Math.max(1, Math.round(sh * fator));
                            const cv = document.createElement('canvas');
                            cv.width = dw; cv.height = dh;
                            const cx = cv.getContext('2d');
                            cx.fillStyle = '#fff'; cx.fillRect(0, 0, dw, dh);
                            cx.drawImage(img, sx, sy, sw, sh, 0, 0, dw, dh);
                            alvo[chave] = cv.toDataURL('image/jpeg', .86);
                        };
                        img.onerror = () => this.aviso('Não consegui abrir essa imagem.');
                        img.src = leitor.result;
                    };
                    leitor.readAsDataURL(file);
                },

                /* ---------- edição de texto ---------- */
                docAplicar(ta, de, ate, novo, sa, sb) {
                    ta.focus();
                    ta.setSelectionRange(de, ate);
                    let ok = false;
                    try { ok = document.execCommand('insertText', false, novo); } catch { ok = false; }
                    if (!ok) { ta.setRangeText(novo, de, ate, 'end'); ta.dispatchEvent(new Event('input', { bubbles: true })); }
                    ta.setSelectionRange(sa, sb);
                },
                fmtTexto(ev, acao) {
                    const alvo = ev.target;
                    const ta = alvo.tagName === 'TEXTAREA' ? alvo : alvo.closest('[data-ed]')?.querySelector('textarea');
                    if (!ta) return;
                    const v = ta.value, ini = ta.selectionStart, fim = ta.selectionEnd, sel = v.slice(ini, fim);
                    const ap = (de, ate, novo, sa, sb) => this.docAplicar(ta, de, ate, novo, sa, sb);

                    const envolver = m => {
                        const antes = v.slice(ini - m.length, ini), depois = v.slice(fim, fim + m.length);
                        const italicoEmNegrito = m === '*' && v[ini - 2] === '*';
                        if (antes === m && depois === m && !italicoEmNegrito) return ap(ini - m.length, fim + m.length, sel, ini - m.length, ini - m.length + sel.length);
                        if (sel.length > m.length * 2 && sel.startsWith(m) && sel.endsWith(m) && !(m === '*' && sel.startsWith('**'))) {
                            const miolo = sel.slice(m.length, -m.length);
                            return ap(ini, fim, miolo, ini, ini + miolo.length);
                        }
                        const t = sel || 'texto';
                        ap(ini, fim, m + t + m, ini + m.length, ini + m.length + t.length);
                    };

                    const prefixo = tipo => {
                        const iniL = ini === 0 ? 0 : v.lastIndexOf('\n', ini - 1) + 1;
                        let fimL = v.indexOf('\n', fim);
                        if (fimL < 0) fimL = v.length;
                        const linhas = v.slice(iniL, fimL).split('\n');
                        const RE = { ul: /^\s*[-•]\s+/, ol: /^\s*\d+[.)]\s+/, h: /^##\s+/, q: /^>\s?/ }[tipo];
                        const QUALQUER = /^(\s*[-•]\s+|\s*\d+[.)]\s+|##\s+|>\s?)/;
                        const cheias = linhas.filter(l => l.trim());
                        const remover = cheias.length > 0 && cheias.every(l => RE.test(l));
                        let n = 0;
                        const novas = linhas.map(l => {
                            if (!l.trim()) return l;
                            const limpa = l.replace(QUALQUER, '');
                            if (remover) return limpa;
                            n++;
                            return ({ ul: '- ', ol: `${n}. `, h: '## ', q: '> ' }[tipo]) + limpa;
                        });
                        const novo = novas.join('\n');
                        ap(iniL, fimL, novo, iniL, iniL + novo.length);
                    };

                    const caixa = modo => {
                        const a = sel ? ini : 0, b = sel ? fim : v.length, base = v.slice(a, b);
                        const r = modo === 'up' ? base.toUpperCase()
                            : modo === 'low' ? base.toLowerCase()
                            : base.toLowerCase().replace(/(^|[\s(“"'\-])(\p{L})/gu, (m, p, l) => p + l.toUpperCase());
                        ap(a, b, r, a, a + r.length);
                    };

                    switch (acao) {
                        case 'b': return envolver('**');
                        case 'i': return envolver('*');
                        case 'u': return envolver('__');
                        case 's': return envolver('~~');
                        case 'mark': return envolver('==');
                        case 'ul': case 'ol': case 'h': case 'q': return prefixo(acao);
                        case 'up': case 'low': case 'cap': return caixa(acao);
                        case 'link': {
                            const url = prompt('Endereço do link (ex.: https://site.com)');
                            if (!url) return;
                            const u = /^(https?:|mailto:|tel:)/i.test(url.trim()) ? url.trim() : 'https://' + url.trim();
                            const t = sel || 'link';
                            return ap(ini, fim, `[${t}](${u})`, ini + 1, ini + 1 + t.length);
                        }
                        case 'limpar': {
                            const a = sel ? ini : 0, b = sel ? fim : v.length;
                            const r = v.slice(a, b)
                                .replace(/\[([^\]\n]+)\]\([^)\s]+\)/g, '$1')
                                .replace(/(\*\*|__|~~|==)/g, '')
                                .replace(/\*([^*\n]+)\*/g, '$1')
                                .replace(/^(\s*[-•]\s+|\s*\d+[.)]\s+|#{1,2}\s+|>\s?)/gm, '');
                            return ap(a, b, r, a, a + r.length);
                        }
                    }
                },
                docAtalho(ev) {
                    const ta = ev.target;
                    if ((ev.ctrlKey || ev.metaKey) && !ev.shiftKey && !ev.altKey) {
                        const m = { b: 'b', i: 'i', u: 'u' }[String(ev.key).toLowerCase()];
                        if (m) { ev.preventDefault(); this.fmtTexto(ev, m); }
                        return;
                    }
                    if (ev.key !== 'Enter' || ev.shiftKey || ta.selectionStart !== ta.selectionEnd) return;
                    const p = ta.selectionStart, v = ta.value;
                    const iniL = p === 0 ? 0 : v.lastIndexOf('\n', p - 1) + 1;
                    const m = v.slice(iniL, p).match(/^(\s*)([-•]|\d+[.)])\s+(.*)$/);
                    if (!m) return;
                    ev.preventDefault();
                    if (!m[3].trim()) { this.docAplicar(ta, iniL, p, '', iniL, iniL); return; }
                    const marca = /^\d/.test(m[2]) ? `${parseInt(m[2], 10) + 1}${m[2].slice(-1)}` : m[2];
                    const novo = `\n${m[1]}${marca} `;
                    this.docAplicar(ta, p, p, novo, p + novo.length, p + novo.length);
                },

                /* ---------- prévia e impressão ---------- */
                docHtml(d) { return d ? render(d, { preview: true }) : ''; },
                docPapelLargura(d) { return (PAPEIS[d?.layout?.papel] || PAPEIS.a4).w; },
                docPapelStyle(d) {
                    const P = PAPEIS[d.layout.papel] || PAPEIS.a4;
                    const m = MARGEM_PX[d.layout.margem] ?? 57;
                    const fundo = `linear-gradient(#fff,#fff) 0 0 / 100% ${m}px no-repeat, `
                        + `linear-gradient(to bottom, transparent calc(100% - 2px), #c3cbd8 calc(100% - 2px)) 0 ${m}px / 100% ${P.h - 2 * m}px repeat-y, #fff`;
                    return `width:${P.w}px;min-height:${P.h}px;background:${fundo};box-shadow:0 6px 24px rgba(0,0,0,.35);position:relative;overflow:hidden;color:#1b1f24;margin:0 auto;`;
                },
                imprimir(d) {
                    const w = window.open('', '_blank');
                    if (!w) { this.aviso('Libere os pop-ups para imprimir.'); return; }
                    const P = PAPEIS[d.layout.papel] || PAPEIS.a4;
                    const mm = MARGEM_MM[d.layout.margem] ?? 15;
                    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(d.titulo)}</title>`
                        + `<style>@page{size:${P.css};margin:${mm}mm}html,body{margin:0;background:#fff}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}</style></head>`
                        + `<body>${render(d, { impressao: true })}</body></html>`);
                    w.document.close(); w.focus();
                    setTimeout(() => w.print(), 300); // na janela de impressão, escolha "Salvar como PDF"
                },
            };
        },
    };
})();