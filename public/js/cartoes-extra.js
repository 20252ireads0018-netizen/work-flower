/* Work Flower · cartões "Questões" (concursos), "Criador" (conteúdo para redes) e "RH" (vagas, candidatos e integração)
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o graficos.js (carregue depois dele: usa wfNovoCartao).
   - Questões: b.dados = { questoes: [{ id, materia, enunciado, alts: [5], certa, expl }], resp: { id: indice }, modo: 'estudar'|'editar', i }
   - Criador:  b.dados = { posts: [{ id, titulo, rede, etapa, data, legenda, tags }], filtro }
   - RH:       b.dados = { vagas: [{ id, nome }], cands: [{ id, nome, vaga, etapa, contato, nota, obs, feitos: {} }], passos: [texto] } */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    const { uid } = Q.util;
    const TIPOS = [{ id: 'questoes', nome: 'Questões de concurso' }, { id: 'criador', nome: 'Criador de conteúdo' }, { id: 'rh', nome: 'RH: vagas e candidatos' }];
    const LETRAS = ['A', 'B', 'C', 'D', 'E'];
    const REDES = { Instagram: 2200, TikTok: 2200, YouTube: 5000, 'X (Twitter)': 280, LinkedIn: 3000, Facebook: 63206, Threads: 500, Blog: 0 };
    const ETAPAS_POST = ['Ideia', 'Roteiro', 'Gravado', 'Editado', 'Agendado', 'Publicado'];
    const ETAPAS_RH = ['Triagem', 'Entrevista', 'Teste', 'Proposta', 'Contratado', 'Recusado'];
    const PASSOS_RH = ['Documentos recebidos', 'Contrato assinado', 'Acessos e e-mail criados', 'Apresentação à equipe', 'Treinamento inicial', 'Conversa de 30 dias'];
    const MAX = 400;

    const extras = {
        /* ---------- Questões ---------- */
        novoQuestoes(sec) {
            return this.wfNovoCartao(sec, 'questoes', 'Questões de concurso', 520, 420, {
                modo: 'estudar', i: 0, resp: {},
                questoes: [{
                    id: uid(), materia: 'Português',
                    enunciado: 'Assinale a alternativa em que a palavra está escrita corretamente.',
                    alts: ['Excessão', 'Exceção', 'Exessão', 'Eceção', 'Excesão'], certa: 1,
                    expl: '“Exceção” se escreve com xc e ç.',
                }],
            }, 'Questões adicionado com um exemplo. Use “Editar” para cadastrar as suas.');
        },
        qzUI(b) { return (this.wfCxUI[b.id] ??= { materia: '', so: 'todas' }); },
        qzLista(b) {
            const ui = this.qzUI(b), r = b.dados.resp || {};
            return (b.dados.questoes || []).filter(q => (!ui.materia || q.materia === ui.materia)
                && (ui.so === 'todas' || (ui.so === 'erradas' ? (q.id in r && r[q.id] !== q.certa) : !(q.id in r))));
        },
        qzMaterias(b) { return [...new Set((b.dados.questoes || []).map(q => (q.materia || '').trim()).filter(Boolean))].sort(); },
        qzAtual(b) { const l = this.qzLista(b); if (!l.length) return null; b.dados.i = Math.min(Math.max(0, b.dados.i || 0), l.length - 1); return l[b.dados.i]; },
        qzIr(b, passo) { const n = this.qzLista(b).length; if (n) b.dados.i = ((b.dados.i || 0) + passo + n) % n; },
        qzSortear(b) { const n = this.qzLista(b).length; if (n) b.dados.i = Math.floor(Math.random() * n); },
        qzResponder(b, q, k) { b.dados.resp ??= {}; if (!(q.id in b.dados.resp)) b.dados.resp[q.id] = k; },
        qzRefazer(b, q) { if (b.dados.resp) delete b.dados.resp[q.id]; },
        qzZerar(b) { if (confirm('Apagar todas as respostas e começar de novo? As questões continuam.')) { b.dados.resp = {}; b.dados.i = 0; } },
        qzClasse(b, q, k) {
            const r = (b.dados.resp || {})[q.id];
            if (r === undefined) return '';
            return k === q.certa ? 'qz-certa' : (k === r ? 'qz-errada' : 'qz-apagada');
        },
        qzPlacar(b) {
            const r = b.dados.resp || {}, qs = b.dados.questoes || [], por = {};
            let ok = 0, feitas = 0;
            qs.forEach(q => {
                if (!(q.id in r)) return;
                const m = q.materia || 'Sem matéria', c = r[q.id] === q.certa;
                feitas++; if (c) ok++;
                (por[m] ??= { m, ok: 0, n: 0 }).n++; if (c) por[m].ok++;
            });
            return { ok, feitas, total: qs.length, pct: feitas ? Math.round(ok / feitas * 100) : 0, materias: Object.values(por).map(x => ({ ...x, pct: Math.round(x.ok / x.n * 100) })) };
        },
        qzNova(b) {
            if ((b.dados.questoes || []).length >= MAX) { this.aviso(`Limite de ${MAX} questões por cartão.`); return; }
            b.dados.questoes.push({ id: uid(), materia: this.qzUI(b).materia || '', enunciado: '', alts: ['', '', '', '', ''], certa: 0, expl: '' });
        },
        qzRemover(b, q) {
            if (!confirm('Tirar esta questão?')) return;
            b.dados.questoes = b.dados.questoes.filter(x => x.id !== q.id);
            if (b.dados.resp) delete b.dados.resp[q.id];
        },
        /** Importa questões coladas: enunciado, alternativas "A) ...", e "Gabarito: C" (ou "Resposta: C"); questões separadas por linha em branco dupla ou numeração "1." */
        qzImportar(b, texto) {
            const blocos = String(texto || '').replace(/\r/g, '').split(/\n\s*\n(?=\s*(?:\d+[.)-]|Quest))|\n{3,}/).map(s => s.trim()).filter(Boolean);
            let n = 0;
            blocos.forEach(bl => {
                const linhas = bl.split('\n').map(s => s.trim()).filter(Boolean), alts = [], enun = [];
                let certa = 0, expl = '', materia = this.qzUI(b).materia || '';
                linhas.forEach(l => {
                    let m;
                    if ((m = l.match(/^\(?([A-Ea-e])[).:\-]\s*(.+)$/))) alts[LETRAS.indexOf(m[1].toUpperCase())] = m[2];
                    else if ((m = l.match(/^(?:gabarito|resposta|correta)\s*[:\-]?\s*\(?([A-Ea-e])\)?/i))) certa = LETRAS.indexOf(m[1].toUpperCase());
                    else if ((m = l.match(/^(?:mat[ée]ria|disciplina)\s*[:\-]\s*(.+)$/i))) materia = m[1];
                    else if ((m = l.match(/^(?:explica[çc][ãa]o|coment[áa]rio)\s*[:\-]\s*(.+)$/i))) expl = m[1];
                    else if (!alts.length) enun.push(l.replace(/^\d+[.)-]\s*/, ''));
                });
                if (!enun.length || alts.filter(Boolean).length < 2 || (b.dados.questoes || []).length >= MAX) return;
                const cinco = LETRAS.map((_, k) => alts[k] || '');
                b.dados.questoes.push({ id: uid(), materia, enunciado: enun.join('\n'), alts: cinco, certa: Math.max(0, certa), expl });
                n++;
            });
            this.aviso(n ? `${n} ${n === 1 ? 'questão importada' : 'questões importadas'}.` : 'Não achei questões no texto. Veja o modelo no campo.');
            return n;
        },
        qzLetra(k) { return LETRAS[k]; },

        /* ---------- Criador ---------- */
        novoCriador(sec) {
            const hoje = new Date().toISOString().slice(0, 10);
            return this.wfNovoCartao(sec, 'criador', 'Criador de conteúdo', 600, 420, {
                filtro: '', posts: [
                    { id: uid(), titulo: 'Bastidores da semana', rede: 'Instagram', etapa: 'Ideia', data: hoje, legenda: '', tags: '#bastidores' },
                ],
            }, 'Criador adicionado. Cada post passa de Ideia até Publicado.');
        },
        crEtapas() { return ETAPAS_POST; },
        crRedes() { return Object.keys(REDES); },
        crUI(b) { return (this.wfCxUI[b.id] ??= { aberto: null, rede: '' }); },
        crDa(b, etapa) {
            const ui = this.crUI(b);
            return (b.dados.posts || []).filter(p => p.etapa === etapa && (!ui.rede || p.rede === ui.rede))
                .sort((x, y) => String(x.data || '9').localeCompare(String(y.data || '9')));
        },
        crNovo(b, etapa) {
            if ((b.dados.posts || []).length >= MAX) { this.aviso(`Limite de ${MAX} posts por cartão.`); return; }
            const p = { id: uid(), titulo: 'Novo post', rede: this.crUI(b).rede || 'Instagram', etapa: etapa || 'Ideia', data: '', legenda: '', tags: '' };
            b.dados.posts.push(p);
            this.crUI(b).aberto = p.id;
        },
        crMover(b, p, passo) { const i = ETAPAS_POST.indexOf(p.etapa); p.etapa = ETAPAS_POST[Math.min(ETAPAS_POST.length - 1, Math.max(0, i + passo))]; },
        crRemover(b, p) { if (confirm(`Tirar "${p.titulo}"?`)) { b.dados.posts = b.dados.posts.filter(x => x.id !== p.id); this.crUI(b).aberto = null; } },
        crAberto(b) { const id = this.crUI(b).aberto; return (b.dados.posts || []).find(p => p.id === id) || null; },
        crTexto(p) { return [p.legenda || '', p.tags || ''].filter(Boolean).join('\n\n'); },
        crConta(p) {
            const lim = REDES[p.rede] || 0, n = [...this.crTexto(p)].length, tags = (String(p.tags || '').match(/#[\p{L}\p{N}_]+/gu) || []).length;
            return { n, lim, tags, passou: lim > 0 && n > lim, muitasTags: p.rede === 'Instagram' && tags > 30 };
        },
        async crCopiar(p) {
            try { await navigator.clipboard.writeText(this.crTexto(p)); this.aviso('Legenda e hashtags copiadas.'); }
            catch (e) { this.aviso('Não deu para copiar. Selecione o texto e use Ctrl+C.'); }
        },
        crSemana(b) {
            const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
            const fim = new Date(hoje); fim.setDate(fim.getDate() + 7);
            return (b.dados.posts || []).filter(p => p.data && p.etapa !== 'Publicado' && new Date(p.data + 'T00:00') >= hoje && new Date(p.data + 'T00:00') < fim).length;
        },

        /* ---------- RH ---------- */
        novoRh(sec) {
            const v = uid();
            return this.wfNovoCartao(sec, 'rh', 'RH: vagas e candidatos', 680, 420, {
                vagas: [{ id: v, nome: 'Atendimento' }], passos: [...PASSOS_RH],
                cands: [{ id: uid(), nome: 'Candidato de exemplo', vaga: v, etapa: 'Triagem', contato: '', nota: 0, obs: '', feitos: {} }],
            }, 'RH adicionado. Arraste os candidatos pelas etapas com ‹ ›.');
        },
        rhEtapas() { return ETAPAS_RH; },
        rhUI(b) { return (this.wfCxUI[b.id] ??= { vaga: '', aberto: null, ver: 'funil' }); },
        rhDa(b, etapa) {
            const ui = this.rhUI(b);
            return (b.dados.cands || []).filter(c => c.etapa === etapa && (!ui.vaga || c.vaga === ui.vaga)).sort((x, y) => (y.nota || 0) - (x.nota || 0));
        },
        rhVaga(b, id) { return ((b.dados.vagas || []).find(v => v.id === id) || {}).nome || 'Sem vaga'; },
        rhNovaVaga(b, campo) {
            const nome = (campo.value || '').trim().slice(0, 60);
            if (!nome) return;
            b.dados.vagas.push({ id: uid(), nome });
            campo.value = '';
        },
        rhTirarVaga(b, v) {
            if (!confirm(`Tirar a vaga "${v.nome}"? Os candidatos dela ficam sem vaga.`)) return;
            b.dados.vagas = b.dados.vagas.filter(x => x.id !== v.id);
            (b.dados.cands || []).forEach(c => { if (c.vaga === v.id) c.vaga = ''; });
            if (this.rhUI(b).vaga === v.id) this.rhUI(b).vaga = '';
        },
        rhNovo(b) {
            if ((b.dados.cands || []).length >= MAX) { this.aviso(`Limite de ${MAX} candidatos por cartão.`); return; }
            const c = { id: uid(), nome: 'Novo candidato', vaga: this.rhUI(b).vaga || ((b.dados.vagas || [])[0] || {}).id || '', etapa: 'Triagem', contato: '', nota: 0, obs: '', feitos: {} };
            b.dados.cands.push(c);
            this.rhUI(b).aberto = c.id;
        },
        rhMover(b, c, passo) { const i = ETAPAS_RH.indexOf(c.etapa); c.etapa = ETAPAS_RH[Math.min(ETAPAS_RH.length - 1, Math.max(0, i + passo))]; },
        rhRemover(b, c) { if (confirm(`Tirar "${c.nome}"?`)) { b.dados.cands = b.dados.cands.filter(x => x.id !== c.id); this.rhUI(b).aberto = null; } },
        rhAberto(b) { const id = this.rhUI(b).aberto; return (b.dados.cands || []).find(c => c.id === id) || null; },
        rhContratados(b) { return (b.dados.cands || []).filter(c => c.etapa === 'Contratado'); },
        rhProgresso(b, c) {
            const p = b.dados.passos || [], f = c.feitos || {};
            return p.length ? Math.round(p.filter((_, k) => f[k]).length / p.length * 100) : 0;
        },
        rhPasso(b, c, k) { c.feitos ??= {}; if (c.feitos[k]) delete c.feitos[k]; else c.feitos[k] = 1; },
        rhLinkContato(c) {
            const t = String(c.contato || '').trim();
            if (/^\S+@\S+\.\S+$/.test(t)) return 'mailto:' + t;
            const d = t.replace(/\D/g, '');
            return d.length >= 10 ? 'https://wa.me/' + (d.length <= 11 ? '55' + d : d) : '';
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        const prep = m.prepararPastas;
        m.prepararPastas = function () {
            const primeira = !this._pastasProntas;
            if (primeira && Array.isArray(this.tiposBloco)) TIPOS.forEach(t => { if (!this.tiposBloco.some(x => x.id === t.id)) this.tiposBloco.push(t); });
            const r = prep.apply(this, arguments);
            if (primeira && typeof this.novoBloco === 'function') {
                const novo = this.novoBloco;
                this.novoBloco = function (sec, tipo, ...resto) {
                    if (tipo === 'questoes') return this.novoQuestoes(sec);
                    if (tipo === 'criador') return this.novoCriador(sec);
                    if (tipo === 'rh') return this.novoRh(sec);
                    return novo.call(this, sec, tipo, ...resto);
                };
            }
            return r;
        };
        return Object.assign(m, extras, { wfCxUI: {} });
    };
})();
