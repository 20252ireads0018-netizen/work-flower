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
    // endereço das métricas do YouTube (vem da tag do script, porque o site pode estar numa subpasta)
    const URL_YT = (document.currentScript && document.currentScript.dataset.yt) || '';
    const curto = n => (n == null ? '—' : n >= 1e6 ? (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : n >= 1e4 ? (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : n.toLocaleString('pt-BR'));

    /* ---------- prova em PDF → questões ---------- */
    /** Texto de um PDF com as quebras de linha certas; páginas em duas colunas são lidas coluna por coluna. */
    async function textoDoPdf(arquivo) {
        const lib = await window.WFEditorPdf.pdfjs();
        const doc = await lib.getDocument({ data: new Uint8Array(await arquivo.arrayBuffer()) }).promise;
        const paginas = [];
        for (let n = 1; n <= Math.min(doc.numPages, 120); n++) {
            const pg = await doc.getPage(n), larg = pg.getViewport({ scale: 1 }).width;
            const itens = (await pg.getTextContent()).items.filter(i => i.str && i.str.trim()).map(i => ({ s: i.str, x: i.transform[4], y: i.transform[5], w: i.width || 0 }));
            if (!itens.length) continue;
            // duas colunas: quase nenhum pedaço de texto atravessa o meio da página
            const meio = larg / 2, atravessa = itens.filter(i => i.x < meio - 4 && i.x + i.w > meio + 4).length;
            const duas = itens.length > 30 && atravessa < itens.length * 0.04 && itens.some(i => i.x > meio + 10) && itens.some(i => i.x + i.w < meio - 10);
            const colunas = duas ? [itens.filter(i => i.x < meio), itens.filter(i => i.x >= meio)] : [itens];
            for (const col of colunas) {
                const linhas = new Map();
                col.forEach(i => { const k = Math.round(i.y / 3); (linhas.get(k) || linhas.set(k, []).get(k)).push(i); });
                [...linhas.entries()].sort((a, b) => b[0] - a[0])
                    .forEach(([, l]) => paginas.push(l.sort((a, b) => a.x - b.x).map(i => i.s).join(' ').replace(/\s+/g, ' ').trim()));
            }
        }
        return paginas.join('\n');
    }
    /** Gabarito "1 C 2 A", "01-D", "Questão 3: B" (X ou * = anulada) → { 1: 'C', 2: 'A' }. */
    function lerGabarito(texto) {
        const g = {};
        for (const m of String(texto || '').toUpperCase().matchAll(/(?:QUEST[ÃA]O\s*)?\b(\d{1,3})\s*[-–:.)=]?\s*\b([A-E]|X|\*)(?![A-Z])/g)) {
            if (!(m[1] in g)) g[+m[1]] = m[2];
        }
        return g;
    }
    /** Questões numeradas em sequência ("1." / "Questão 01"), alternativas "(A)", "A)" ou "a." e o gabarito. */
    function questoesDoTexto(texto, gabarito) {
        const linhas = String(texto || '').split('\n').map(l => l.trim()).filter(Boolean);
        const inicio = /^(?:QUEST[ÃA]O\s*)?(\d{1,3})\s*(?:[.)\-–:]\s*|\s+(?=[A-ZÀ-Ú“"(])|\s*$)(.*)$/i;
        const alt = /^\(?([A-Ea-e])\s*[).\-–]\s*(.*)$/;
        const saida = [];
        let atual = null, ultimo = 0;
        const fechar = () => { if (atual && atual.enun.join(' ').trim().length > 8) saida.push(atual); atual = null; };
        for (const l of linhas) {
            const m = l.match(inicio);
            // só conta como nova questão se seguir a numeração (evita "1990 foi o ano..." no meio do texto)
            if (m && (+m[1] === ultimo + 1 || (!ultimo && +m[1] <= 5))) {
                fechar();
                ultimo = +m[1];
                atual = { n: ultimo, enun: [m[2]], alts: [], uAlt: -1 };
                continue;
            }
            if (!atual) continue;
            const a = l.match(alt);
            if (a && LETRAS.indexOf(a[1].toUpperCase()) === atual.alts.length) {
                atual.alts.push(a[2]);
                atual.uAlt = atual.alts.length - 1;
            } else if (atual.uAlt >= 0) atual.alts[atual.uAlt] += ' ' + l;
            else atual.enun.push(l);
        }
        fechar();
        return saida.map(q => {
            const letra = gabarito[q.n];
            if (letra === 'X' || letra === '*') return null; // anulada
            let alts = q.alts.slice(0, 5), certa = letra ? LETRAS.indexOf(letra) : 0;
            if (!alts.length) {
                // estilo Cebraspe (Certo/Errado): sem alternativas, gabarito C ou E
                alts = ['Certo', 'Errado'];
                certa = letra === 'E' ? 1 : 0;
            }
            return { n: q.n, enunciado: q.enun.join(' ').replace(/\s+/g, ' ').trim(), alts: LETRAS.map((_, k) => (alts[k] || '').trim()), certa: Math.max(0, certa), temGabarito: !!letra };
        }).filter(Boolean);
    }

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
        /** Importa a prova em PDF (com texto, não escaneada) e o gabarito (PDF ou digitado). Devolve a mensagem para a tela. */
        async qzImportarPdf(b, prova, arqGabarito, txtGabarito, materia) {
            if (!prova) return 'Escolha o PDF da prova.';
            if (!window.WFEditorPdf || !window.WFEditorPdf.pdfjs) return 'O leitor de PDF não carregou. Recarregue a página.';
            if (prova.size > 30 * 1024 * 1024) return 'O PDF passa de 30 MB.';
            let texto, gab;
            try {
                texto = await textoDoPdf(prova);
                gab = lerGabarito((arqGabarito ? await textoDoPdf(arqGabarito) : '') + '\n' + (txtGabarito || ''));
            } catch (e) {
                return 'Não consegui ler esse PDF. Ele pode estar protegido ou corrompido.';
            }
            if (texto.replace(/\s/g, '').length < 80) return 'Esse PDF não tem texto (deve ser uma prova escaneada, em imagem). Use um PDF com texto selecionável.';
            // gabarito dentro da própria prova (última página)
            if (!Object.keys(gab).length) {
                const m = texto.match(/GABARITO[\s\S]{0,4000}$/i);
                if (m) gab = lerGabarito(m[0]);
            }
            const achadas = questoesDoTexto(texto.replace(/GABARITO[\s\S]{0,4000}$/i, ''), gab);
            if (!achadas.length) return 'Não achei questões numeradas nesse PDF. Use “Colar várias” com o texto da prova.';
            b.dados.questoes ??= [];
            const cabe = Math.max(0, MAX - b.dados.questoes.length), novas = achadas.slice(0, cabe);
            const nome = (materia || '').trim().slice(0, 60) || (prova.name || '').replace(/\.pdf$/i, '').slice(0, 60);
            novas.forEach(q => b.dados.questoes.push({ id: uid(), materia: nome, enunciado: q.enunciado.slice(0, 4000), alts: q.alts.map(a => a.slice(0, 800)), certa: q.certa, expl: '' }));
            const semGab = novas.filter(q => !q.temGabarito).length;
            this.qzUI(b).materia = '';
            return `${novas.length} ${novas.length === 1 ? 'questão importada' : 'questões importadas'}` + (semGab ? ` (${semGab} sem gabarito: confira a alternativa certa em cada uma)` : '') + (achadas.length > novas.length ? `. O cartão já está no limite de ${MAX}.` : '.');
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
        crUI(b) { return (this.wfCxUI[b.id] ??= { aberto: null, rede: '', ver: 'posts', ytCarregando: false, ytErro: '' }); },
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
        /* YouTube: canal em b.dados.yt, última leitura em b.dados.ytSnap (aparece sem buscar de novo) */
        async crYtAtualizar(b) {
            const ui = this.crUI(b), canal = String(b.dados.yt || '').trim();
            if (!canal) { ui.ytErro = 'Cole o @ do seu canal (ex.: @seucanal).'; return; }
            if (!URL_YT) { ui.ytErro = 'Métricas do YouTube indisponíveis nesta página.'; return; }
            ui.ytCarregando = true; ui.ytErro = '';
            try {
                const r = await fetch(URL_YT + '?canal=' + encodeURIComponent(canal), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.ok) ui.ytErro = j.erro || 'Não deu para buscar o canal agora.';
                else b.dados.ytSnap = { canal: j.canal, videos: j.videos, em: j.em };
            } catch (e) { ui.ytErro = 'Sem conexão com o servidor.'; }
            ui.ytCarregando = false;
        },
        crYtNum(n) { return curto(n); },
        crYtQuando(b) { const s = b.dados.ytSnap; return s ? new Date(s.em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
        crYtTaxa(v) { return v.views ? ((v.curtidas + v.comentarios) / v.views * 100).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%' : '—'; },
        /** Métricas do vídeo ligado ao post (link do YouTube no post), se ele estiver entre os últimos lidos. */
        crYtDoPost(b, p) {
            const m = String(p.link || '').match(/(?:v=|youtu\.be\/|shorts\/)([A-Za-z0-9_-]{11})/);
            return m && b.dados.ytSnap ? (b.dados.ytSnap.videos || []).find(v => v.id === m[1]) || null : null;
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
    window.WFQuestoes = { textoDoPdf, lerGabarito, questoesDoTexto };
})();
