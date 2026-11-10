/* Work Flower · cartão "Cenas" (escritores): organiza a história em capítulos/atos, cada cena com os requisitos de cena
   (personagens, local, objetivo, conflito e gancho), status, ideias soltas que se arrastam para as cenas e uma linha do tempo.
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o cartoes-extra.js (carregue depois do graficos.js: usa wfNovoCartao).
   - b.dados = { atos: [{ id, nome }],
                 cenas: [{ id, ato, titulo, status, pov, personagens, local, objetivo, conflito, gancho, quando, resumo,
                           extras: [{ id, t, ok }], texto: idDoCartaoDeTexto|null, palavras }],
                 ideias: [{ id, t, cena: idDaCena|null }] }
     A ordem das cenas dentro do ato é a ordem do array (a linha do tempo segue ato por ato).
   - Arrastar: por ponteiro (mouse, toque e caneta), pela alça ⠿ de cada cena ou ideia. Soltar uma cena noutro ato/cena
     muda de lugar; soltar uma ideia numa cena a prende nela (soltar em "Ideias soltas" devolve).
   - "Escrever" cria (ou abre) um cartão de Texto na mesma aba com os requisitos da cena no topo: o editor de escrita
     (escrita.js) cuida do texto e os textos rápidos (expansor.js) funcionam em todos os campos do cartão. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid } = Q.util;

    const STATUS = [
        { id: 'ideia', nome: 'Ideia', cor: '#a78bfa' },
        { id: 'rascunho', nome: 'Rascunho', cor: '#f59e0b' },
        { id: 'revisao', nome: 'Revisão', cor: '#38bdf8' },
        { id: 'pronta', nome: 'Pronta', cor: '#22c55e' },
    ];
    const REQS = [
        { id: 'personagens', nome: 'Personagens', dica: 'Quem está em cena? Ex.: Ana, o pai dela' },
        { id: 'local', nome: 'Local', dica: 'Onde e quando? Ex.: cozinha, de madrugada' },
        { id: 'objetivo', nome: 'Objetivo', dica: 'O que o personagem do ponto de vista quer nesta cena?' },
        { id: 'conflito', nome: 'Conflito', dica: 'O que impede? Quem ou o que está contra?' },
        { id: 'gancho', nome: 'Gancho', dica: 'Como a cena termina e puxa a próxima?' },
    ];
    const MAX_CENAS = 400, MAX_IDEIAS = 300, MAX_ATOS = 40;
    const txt = (v, n) => String(v == null ? '' : v).slice(0, n);
    const palavras = s => (String(s || '').replace(/[#>*_`~=\-\[\]()!|]/g, ' ').match(/[\p{L}\p{N}]+/gu) || []).length;

    function cenaVazia(ato, titulo) {
        return { id: uid(), ato, titulo: titulo || '', status: 'ideia', pov: '', personagens: '', local: '', objetivo: '', conflito: '', gancho: '', quando: '', resumo: '', extras: [], texto: null, palavras: 0 };
    }

    /** Completa e limpa os dados (cartões antigos, colados da biblioteca ou mexidos à mão). */
    function preparar(d) {
        if (!Array.isArray(d.atos) || !d.atos.length) d.atos = [{ id: uid(), nome: 'Ato 1' }];
        d.atos = d.atos.filter(a => a && typeof a === 'object').slice(0, MAX_ATOS).map(a => ({ id: String(a.id || uid()), nome: txt(a.nome, 80) }));
        const atos = new Set(d.atos.map(a => a.id));
        d.cenas = (Array.isArray(d.cenas) ? d.cenas : []).filter(c => c && typeof c === 'object').slice(0, MAX_CENAS).map(c => {
            const n = Object.assign(cenaVazia(d.atos[0].id), c);
            if (!atos.has(n.ato)) n.ato = d.atos[0].id;
            if (!STATUS.some(s => s.id === n.status)) n.status = 'ideia';
            if (!Array.isArray(n.extras)) n.extras = [];
            n.id = String(n.id);
            return n;
        });
        const cenas = new Set(d.cenas.map(c => c.id));
        d.ideias = (Array.isArray(d.ideias) ? d.ideias : []).filter(i => i && typeof i === 'object').slice(0, MAX_IDEIAS)
            .map(i => ({ id: String(i.id || uid()), t: txt(i.t, 500), cena: cenas.has(i.cena) ? i.cena : null }));
        return d;
    }

    /* ---------- arrastar por ponteiro (funciona no toque) ---------- */
    let arraste = null;
    function soltarEm(x, y) {
        const el = document.elementFromPoint(x, y);
        return el ? el.closest('[data-cn-alvo]') : null;
    }
    function marcar(alvo) {
        document.querySelectorAll('.cn-sobre').forEach(e => e !== alvo && e.classList.remove('cn-sobre'));
        if (alvo) alvo.classList.add('cn-sobre');
    }

    const extras = {
        novoCenas(sec) {
            const a1 = uid(), a2 = uid(), a3 = uid();
            const c1 = Object.assign(cenaVazia(a1, 'Abertura: o mundo comum'), { status: 'rascunho', personagens: 'Protagonista', local: 'Casa da protagonista', objetivo: 'Mostrar a rotina e o que falta na vida dela', conflito: '', gancho: 'Chega uma notícia inesperada' });
            const c2 = cenaVazia(a2, 'O primeiro obstáculo');
            const c3 = cenaVazia(a3, 'Clímax');
            return this.wfNovoCartao(sec, 'cenas', 'Cenas da história', 760, 480, {
                atos: [{ id: a1, nome: 'Ato 1 · Apresentação' }, { id: a2, nome: 'Ato 2 · Confronto' }, { id: a3, nome: 'Ato 3 · Resolução' }],
                cenas: [c1, c2, c3],
                ideias: [{ id: uid(), t: 'Um objeto que volta no final', cena: null }, { id: uid(), t: 'Diálogo com o vizinho que sabe demais', cena: null }],
            }, 'Cenas adicionado. Arraste as ideias para as cenas e abra uma cena para preencher os requisitos.');
        },
        cnUI(b) {
            if (!this.wfCnUI[b.id]) {
                preparar(b.dados);
                this.wfCnUI[b.id] = { ver: 'quadro', aberta: null, ideia: '', filtro: '', soltas: true };
            }
            return this.wfCnUI[b.id];
        },
        cnStatus() { return STATUS; },
        cnReqs() { return REQS; },
        cnSt(id) { return STATUS.find(s => s.id === id) || STATUS[0]; },
        cnDoAto(b, ato) {
            const f = (this.cnUI(b).filtro || '').toLowerCase();
            return b.dados.cenas.filter(c => c.ato === ato && (!f || [c.titulo, c.personagens, c.local, c.resumo].join(' ').toLowerCase().includes(f)));
        },
        /** Todas as cenas na ordem da história (ato por ato), com o número da cena. */
        cnOrdem(b) {
            const out = [];
            b.dados.atos.forEach(a => b.dados.cenas.filter(c => c.ato === a.id).forEach(c => out.push(c)));
            return out;
        },
        cnNumero(b, c) { return this.cnOrdem(b).indexOf(c) + 1; },
        cnFeitos(c) { return REQS.filter(r => String(c[r.id] || '').trim()).length + (c.extras || []).filter(x => x.ok).length; },
        cnTotal(c) { return REQS.length + (c.extras || []).length; },
        cnFalta(c) { return REQS.filter(r => !String(c[r.id] || '').trim()).map(r => r.nome); },
        cnIdeiasDa(b, c) { return b.dados.ideias.filter(i => i.cena === c.id); },
        cnSoltas(b) { return b.dados.ideias.filter(i => !i.cena); },
        cnAberta(b) { const id = this.cnUI(b).aberta; return id ? b.dados.cenas.find(c => c.id === id) || null : null; },
        cnResumo(b) {
            const t = b.dados.cenas.length, p = b.dados.cenas.filter(c => c.status === 'pronta').length;
            const w = b.dados.cenas.reduce((s, c) => s + (Number(c.palavras) || 0), 0);
            return `${t} ${t === 1 ? 'cena' : 'cenas'} · ${p} ${p === 1 ? 'pronta' : 'prontas'}` + (w ? ` · ${w.toLocaleString('pt-BR')} palavras` : '');
        },

        cnNovaCena(b, ato) {
            if (b.dados.cenas.length >= MAX_CENAS) return this.aviso('Limite de cenas neste cartão.');
            const c = cenaVazia(ato || b.dados.atos[b.dados.atos.length - 1].id);
            b.dados.cenas.push(c);
            Object.assign(this.cnUI(b), { aberta: c.id });
        },
        cnNovoAto(b) {
            if (b.dados.atos.length >= MAX_ATOS) return;
            b.dados.atos.push({ id: uid(), nome: 'Capítulo ' + (b.dados.atos.length + 1) });
        },
        cnRemoverAto(b, a) {
            if (b.dados.atos.length < 2) return this.aviso('A história precisa de pelo menos um ato ou capítulo.');
            const n = b.dados.cenas.filter(c => c.ato === a.id).length;
            if (n && !confirm(`Apagar "${a.nome}"? As ${n} cenas dele vão para o ato anterior.`)) return;
            const i = b.dados.atos.indexOf(a), destino = b.dados.atos[i > 0 ? i - 1 : 1].id;
            b.dados.cenas.forEach(c => { if (c.ato === a.id) c.ato = destino; });
            b.dados.atos.splice(i, 1);
        },
        cnMoverAto(b, a, d) {
            const i = b.dados.atos.indexOf(a), j = i + d;
            if (j < 0 || j >= b.dados.atos.length) return;
            b.dados.atos.splice(i, 1); b.dados.atos.splice(j, 0, a);
        },
        /** Sobe/desce a cena na ordem; passando da ponta do ato, vai para o ato vizinho. */
        cnMover(b, c, d) {
            const doAto = b.dados.cenas.filter(x => x.ato === c.ato), k = doAto.indexOf(c);
            const vizinha = doAto[k + d];
            if (vizinha) {
                const cs = b.dados.cenas, i = cs.indexOf(c), j = cs.indexOf(vizinha);
                cs[i] = vizinha; cs[j] = c;
                b.dados.cenas = [...cs];
                return;
            }
            const ia = b.dados.atos.findIndex(a => a.id === c.ato), outro = b.dados.atos[ia + d];
            if (!outro) return;
            this.cnColocar(b, c, outro.id, d > 0 ? 'inicio' : null);
        },
        /** Põe a cena no ato `ato`, antes da cena `antes` (id), no início ('inicio') ou no fim (null). */
        cnColocar(b, c, ato, antes) {
            const cs = b.dados.cenas.filter(x => x !== c);
            c.ato = ato;
            let pos = cs.length;
            if (antes === 'inicio') { const p = cs.findIndex(x => x.ato === ato); pos = p < 0 ? cs.length : p; }
            else if (antes) { const p = cs.findIndex(x => x.id === antes); if (p >= 0) pos = p; }
            else { let u = -1; cs.forEach((x, i) => { if (x.ato === ato) u = i; }); if (u >= 0) pos = u + 1; }
            cs.splice(pos, 0, c);
            b.dados.cenas = cs;
        },
        cnRemover(b, c) {
            if (!confirm(`Excluir a cena "${c.titulo || 'sem título'}"? As ideias presas nela voltam para as ideias soltas.`)) return;
            b.dados.ideias.forEach(i => { if (i.cena === c.id) i.cena = null; });
            b.dados.cenas = b.dados.cenas.filter(x => x !== c);
            this.cnUI(b).aberta = null;
        },
        cnDuplicar(b, c) {
            const n = Object.assign(JSON.parse(JSON.stringify(c)), { id: uid(), titulo: (c.titulo || 'Cena') + ' (cópia)', texto: null, palavras: 0 });
            n.extras.forEach(x => { x.id = uid(); });
            b.dados.cenas.splice(b.dados.cenas.indexOf(c) + 1, 0, n);
        },
        cnNovaIdeia(b) {
            const ui = this.cnUI(b), t = (ui.ideia || '').trim().slice(0, 500);
            if (!t) return;
            if (b.dados.ideias.length >= MAX_IDEIAS) return this.aviso('Limite de ideias neste cartão.');
            b.dados.ideias.push({ id: uid(), t, cena: null });
            ui.ideia = '';
        },
        cnApagarIdeia(b, i) { b.dados.ideias = b.dados.ideias.filter(x => x !== i); },
        /** Ideia vira cena (no último ato) e sai das ideias. */
        cnIdeiaVirarCena(b, i) {
            const c = cenaVazia(b.dados.atos[b.dados.atos.length - 1].id, i.t.slice(0, 120));
            if (i.t.length > 120) c.resumo = i.t;
            b.dados.cenas.push(c);
            this.cnApagarIdeia(b, i);
            this.aviso('A ideia virou uma cena no fim da história.');
        },
        cnNovoExtra(b, c, campo) {
            const t = (campo.value || '').trim().slice(0, 120);
            if (!t) return;
            c.extras.push({ id: uid(), t, ok: false });
            campo.value = '';
        },

        /* ---------- arrastar (ponteiro) ---------- */
        cnPegar(e, b, tipo, item) {
            if (e.button != null && e.button !== 0) return;
            e.preventDefault(); e.stopPropagation();
            const origem = e.currentTarget.closest('.cn-cena, .cn-ideia') || e.currentTarget;
            const r = origem.getBoundingClientRect();
            const fantasma = origem.cloneNode(true);
            fantasma.classList.add('cn-fantasma');
            Object.assign(fantasma.style, { width: r.width + 'px', left: r.left + 'px', top: r.top + 'px' });
            document.body.appendChild(fantasma);
            origem.classList.add('cn-saindo');
            const dx = e.clientX - r.left, dy = e.clientY - r.top;
            arraste = { b, tipo, item };
            const mover = ev => {
                fantasma.style.left = (ev.clientX - dx) + 'px';
                fantasma.style.top = (ev.clientY - dy) + 'px';
                const alvo = soltarEm(ev.clientX, ev.clientY);
                marcar(alvo && this.cnAceita(alvo, tipo, item) ? alvo : null);
            };
            const soltar = ev => {
                window.removeEventListener('pointermove', mover);
                window.removeEventListener('pointerup', soltar);
                window.removeEventListener('pointercancel', soltar);
                fantasma.remove();
                origem.classList.remove('cn-saindo');
                marcar(null);
                const alvo = ev.type === 'pointerup' ? soltarEm(ev.clientX, ev.clientY) : null;
                if (alvo && this.cnAceita(alvo, tipo, item)) this.cnSoltar(b, tipo, item, alvo.dataset.cnAlvo, alvo.dataset.cnId);
                arraste = null;
            };
            window.addEventListener('pointermove', mover);
            window.addEventListener('pointerup', soltar);
            window.addEventListener('pointercancel', soltar);
        },
        cnAceita(alvo, tipo, item) {
            const k = alvo.dataset.cnAlvo;
            if (alvo.dataset.cnB !== arraste?.b?.id) return false; // só dentro do mesmo cartão
            if (tipo === 'ideia') return k === 'cena' || k === 'soltas';
            return (k === 'cena' && alvo.dataset.cnId !== item.id) || k === 'ato';
        },
        cnSoltar(b, tipo, item, k, id) {
            if (tipo === 'ideia') { item.cena = k === 'cena' ? id : null; return; }
            if (k === 'ato') return this.cnColocar(b, item, id, null);
            const alvo = b.dados.cenas.find(c => c.id === id);
            if (alvo) this.cnColocar(b, item, alvo.ato, alvo.id);
        },

        /* ---------- integração com o editor de escrita ---------- */
        cnTextoDe(c) { return c.texto ? this.estado.blocos.find(x => x.id === c.texto && x.tipo === 'texto') || null : null; },
        /** Abre o cartão de Texto da cena; se não houver, cria um na mesma aba com os requisitos no topo. */
        cnEscrever(b, c) {
            const ja = this.cnTextoDe(c);
            if (ja) {
                if (typeof this.destacarCartao === 'function') this.destacarCartao(ja.secao, ja.id);
                return;
            }
            const antes = new Set(this.estado.blocos.map(x => x.id));
            this.novoBloco(b.secao, 'texto');
            const x = this.estado.blocos.find(n => !antes.has(n.id));
            if (!x) return this.aviso('Não consegui criar o cartão de texto nesta aba.');
            const n = this.cnNumero(b, c);
            x.titulo = `Cena ${n}: ${c.titulo || 'sem título'}`.slice(0, 120);
            const req = REQS.filter(r => String(c[r.id] || '').trim()).map(r => `- **${r.nome}:** ${String(c[r.id]).trim()}`);
            const ideias = this.cnIdeiasDa(b, c).map(i => `- [ ] ${i.t}`);
            x.dados.texto = [
                `# ${c.titulo || 'Cena ' + n}`,
                ...(req.length ? ['', '## Requisitos da cena', ...req] : []),
                ...(ideias.length ? ['', '## Ideias para usar', ...ideias] : []),
                '', '---', '', c.resumo ? c.resumo : '',
            ].join('\n');
            c.texto = x.id;
            if (c.status === 'ideia') c.status = 'rascunho';
            if (this.estado.layout && this.estado.layout[b.secao]) {
                const lb = this.estado.layout[b.secao][b.id], lx = this.estado.layout[b.secao][x.id];
                if (lb && lx) { lx.y = lb.y + lb.h + 16; lx.x = lb.x; lx.w = Math.max(lx.w, 480); lx.h = Math.max(lx.h, 360); }
            }
            if (typeof this.destacarCartao === 'function') this.destacarCartao(b.secao, x.id);
            this.aviso('Cartão de texto da cena criado logo abaixo. Escreva nele; a contagem de palavras volta para a cena.');
        },
        /** Atualiza a contagem de palavras das cenas com cartão de texto ligado (o que vem depois do "---"). */
        cnContar(b) {
            b.dados.cenas.forEach(c => {
                const t = this.cnTextoDe(c);
                if (!t) { if (c.texto && !this.estado.blocos.some(x => x.id === c.texto)) c.texto = null; return; }
                const corpo = String(t.dados.texto || '').split(/\n-{3,}\n/).slice(1).join('\n');
                const n = palavras(corpo);
                if (c.palavras !== n) c.palavras = n;
            });
            return '';
        },
        /** Copia a escaleta (todas as cenas e requisitos) como texto, para colar onde quiser. */
        async cnCopiarEscaleta(b) {
            const linhas = [];
            b.dados.atos.forEach(a => {
                linhas.push(`## ${a.nome}`);
                b.dados.cenas.filter(c => c.ato === a.id).forEach(c => {
                    linhas.push(`${this.cnNumero(b, c)}. ${c.titulo || 'Sem título'} [${this.cnSt(c.status).nome}]` + (c.quando ? ` (${c.quando})` : ''));
                    REQS.forEach(r => { if (String(c[r.id] || '').trim()) linhas.push(`   ${r.nome}: ${String(c[r.id]).trim()}`); });
                    if (c.resumo) linhas.push(`   ${c.resumo.replace(/\n/g, ' ')}`);
                });
                linhas.push('');
            });
            try { await navigator.clipboard.writeText(linhas.join('\n')); this.aviso('Escaleta copiada.'); }
            catch (e) { this.aviso('Não deu para copiar. Seu navegador bloqueou a área de transferência.'); }
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco) && !this.tiposBloco.some(x => x.id === 'cenas')) this.tiposBloco.push({ id: 'cenas', nome: 'Cenas (escritores)' });
            const r = prep.apply(this, arguments);
            if (primeira && typeof this.novoBloco === 'function') {
                const novo = this.novoBloco;
                this.novoBloco = function (sec, tipo, ...resto) {
                    if (tipo === 'cenas') return this.novoCenas(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            return r;
        };
        return Object.assign(m, extras, { wfCnUI: {} });
    };
    window.WFCenas = { STATUS, REQS, preparar, palavras };
})();
