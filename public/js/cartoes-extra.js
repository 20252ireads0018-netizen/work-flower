/* Work Flower · cartões "Questões" (concursos), "Criador" (conteúdo para redes) e "RH" (vagas, candidatos e integração)
   Estende o WFQuadro.metodos sem mexer no quadro.js, como o graficos.js (carregue depois dele: usa wfNovoCartao).
   - Questões: b.dados = { questoes: [{ id, materia, enunciado, alts: [5], certa, expl, banca?, prova?, ano?, assunto?, dificuldade?, orig? }],
                           resp: { id: indice }, modo: 'estudar'|'editar'|'banco', i, simulado: { ids, em, nome } }
               "Banco": questões abertas do servidor (CartoesController, tabela questoes_banco) por banca, matéria, assunto, ano e dificuldade.
   - Criador:  b.dados = { posts: [{ id, titulo, rede, etapa, data, legenda, tags, link }], filtro }
   - RH:       b.dados = { vagas: [{ id, nome }], passos: [texto], criterios: [{ id, nome }], modelos: { etapa: texto }, auto: { tarefas, entrevista },
                           cands: [{ id, nome, vaga, etapa, contato, nota, obs, feitos: {}, hist: [{ etapa, em }], score: { idCriterio: 1..5 },
                                    entrevista: { quando, dur, evento }, tarefasCriadas }] } */
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
    const TAG = document.currentScript ? document.currentScript.dataset : {};
    const URL_YT = TAG.yt || '', URL_BANCO = TAG.banco || '', URL_CAL = TAG.cal || '', URL_TAREFAS = TAG.tarefas || '';
    const pedir = (...a) => window.WFGraficos.pedir(...a);
    const DIFS = { facil: 'Fácil', media: 'Média', dificil: 'Difícil' };
    const CRITERIOS_RH = ['Comunicação', 'Experiência na área', 'Conhecimento técnico', 'Combina com a equipe'];
    const MODELOS_RH = {
        Triagem: 'Olá, {nome}! Recebemos seu currículo para a vaga de {vaga}. Obrigado pelo interesse! Em breve entramos em contato com os próximos passos.',
        Entrevista: 'Olá, {nome}! Gostaríamos de conversar com você sobre a vaga de {vaga}. A entrevista ficou para {entrevista}. Pode confirmar?',
        Teste: 'Olá, {nome}! Você passou para a etapa de teste da vaga de {vaga}. Vou te enviar as instruções por aqui.',
        Proposta: 'Olá, {nome}! Temos uma proposta para você na vaga de {vaga}. Quando podemos conversar?',
        Contratado: 'Bem-vindo(a) à equipe, {nome}! Vamos começar a integração na vaga de {vaga}. Envie seus documentos, por favor.',
        Recusado: 'Olá, {nome}. Agradecemos sua participação no processo da vaga de {vaga}. Desta vez seguimos com outro perfil, mas guardamos seu contato para próximas oportunidades.',
    };
    const DICAS_REDE = {
        Instagram: 'Até 2.200 caracteres e 30 hashtags. A primeira linha aparece antes do “mais”: comece pelo mais importante.',
        TikTok: 'Até 2.200 caracteres. Legenda curta e direta funciona melhor; use 3 a 5 hashtags.',
        YouTube: 'Descrição até 5.000 caracteres. As 2 primeiras linhas aparecem na busca; coloque o link do vídeo no campo Link.',
        'X (Twitter)': 'Até 280 caracteres, contando as hashtags.',
        LinkedIn: 'Até 3.000 caracteres. Parágrafos curtos e uma pergunta no fim ajudam nos comentários.',
        Facebook: 'Textos longos são aceitos, mas os primeiros 80 caracteres são os que mais aparecem.',
        Threads: 'Até 500 caracteres.',
        Blog: 'Sem limite de caracteres. Use o roteiro como rascunho do texto.',
    };
    const fmtQuando = s => (s ? new Date(s).toLocaleString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '');
    const areaDe = vm => window.WFGraficos.areaDoQuadro(vm);
    const somaDias = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return Q.util.iso(d); };
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
        qzUI(b) {
            // devolve sempre o objeto lido de novo (o reativo): o "??=" devolveria o objeto cru e o Alpine não veria as mudanças
            this.wfCxUI[b.id] ??= {
                materia: '', so: 'todas', filtros: null, res: null, carregando: false, erro: '', sel: {}, abertos: {}, nSim: 10,
                bf: { banca: '', materia: '', assunto: '', ano: '', dificuldade: '', busca: '' },
            };
            return this.wfCxUI[b.id];
        },
        qzLista(b) {
            const ui = this.qzUI(b), r = b.dados.resp || {};
            if (ui.so === 'simulado') {
                const ids = (b.dados.simulado || {}).ids || [], por = new Map((b.dados.questoes || []).map(q => [q.id, q]));
                return ids.map(id => por.get(id)).filter(Boolean);
            }
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
        qzDif(d) { return DIFS[d] || ''; },
        /** Linha de origem da questão (banca · prova · assunto · dificuldade), quando veio do banco. */
        qzOrigem(q) {
            if (!q || !q.banca) return '';
            return [q.banca, q.prova, q.assunto && q.assunto !== 'Geral' ? q.assunto : '', q.dificuldade ? 'dificuldade ' + (DIFS[q.dificuldade] || '').toLowerCase() + ' (estimada)' : ''].filter(Boolean).join(' · ');
        },

        /* banco de questões abertas (servidor) */
        async qzBancoAbrir(b) {
            b.dados.modo = 'banco';
            const ui = this.qzUI(b);
            if (!URL_BANCO) { ui.erro = 'O banco de questões não está disponível nesta página.'; return; }
            if (!ui.filtros) {
                ui.carregando = true;
                const r = await pedir(URL_BANCO + '/filtros');
                ui.carregando = false;
                if (!r.ok) { ui.erro = r.json.message || 'Não deu para abrir o banco agora. Tente de novo.'; return; }
                ui.filtros = r.json;
            }
            if (!ui.res) this.qzBancoBuscar(b, 1);
        },
        qzBancoMaterias(b) {
            const ui = this.qzUI(b), f = ui.filtros;
            return f ? f.materias.filter(m => !ui.bf.banca || m.bancas.includes(ui.bf.banca)) : [];
        },
        qzBancoAssuntos(b) { const ui = this.qzUI(b); return (ui.filtros && ui.bf.materia && ui.filtros.assuntos[ui.bf.materia]) || []; },
        qzBancoMudou(b, campo) {
            const ui = this.qzUI(b);
            if (campo === 'banca' && ui.bf.materia && !this.qzBancoMaterias(b).some(m => m.nome === ui.bf.materia)) ui.bf.materia = '';
            if (campo === 'banca' || campo === 'materia') ui.bf.assunto = '';
            this.qzBancoBuscar(b, 1);
        },
        qzBancoLimpar(b) { const ui = this.qzUI(b); ui.bf = { banca: '', materia: '', assunto: '', ano: '', dificuldade: '', busca: '' }; this.qzBancoBuscar(b, 1); },
        qzBancoParams(b) {
            const bf = this.qzUI(b).bf, p = new URLSearchParams();
            Object.entries(bf).forEach(([k, v]) => { if (String(v || '').trim()) p.set(k, String(v).trim()); });
            return p;
        },
        async qzBancoBuscar(b, pagina) {
            const ui = this.qzUI(b), p = this.qzBancoParams(b);
            p.set('pagina', pagina || 1);
            ui.carregando = true; ui.erro = '';
            const r = await pedir(URL_BANCO + '?' + p.toString());
            ui.carregando = false;
            if (!r.ok) { ui.erro = r.status === 422 ? 'Confira os filtros.' : (r.json.message || 'Não deu para buscar agora.'); return; }
            ui.res = r.json;
            ui.abertos = {};
        },
        qzBancoJa(b, q) { return (b.dados.questoes || []).some(x => x.orig === q.ref); },
        qzBancoMarcar(b, q) { const s = this.qzUI(b).sel; if (s[q.id]) delete s[q.id]; else s[q.id] = q; },
        qzBancoMarcarPagina(b) {
            const ui = this.qzUI(b), livres = ((ui.res || {}).itens || []).filter(q => !this.qzBancoJa(b, q));
            const todas = livres.length && livres.every(q => ui.sel[q.id]);
            livres.forEach(q => { if (todas) delete ui.sel[q.id]; else ui.sel[q.id] = q; });
        },
        qzBancoNSel(b) { return Object.keys(this.qzUI(b).sel).length; },
        qzDoBanco(q) {
            return { id: uid(), materia: q.materia, enunciado: q.enunciado, alts: LETRAS.map((_, k) => q.alts[k] || ''), certa: q.certa, expl: '',
                banca: q.banca, prova: q.prova, ano: q.ano, assunto: q.assunto, dificuldade: q.dificuldade, orig: q.ref };
        },
        /** Põe as questões do banco no cartão (sem repetir) e devolve os ids no cartão, na mesma ordem. */
        qzBancoGuardar(b, lista) {
            b.dados.questoes ??= [];
            const ids = [];
            let novas = 0, cheio = false;
            lista.forEach(q => {
                const ja = b.dados.questoes.find(x => x.orig === q.ref);
                if (ja) { ids.push(ja.id); return; }
                if (b.dados.questoes.length >= MAX) { cheio = true; return; }
                const nova = this.qzDoBanco(q);
                b.dados.questoes.push(nova);
                ids.push(nova.id);
                novas++;
            });
            return { ids, novas, cheio };
        },
        qzBancoAdicionar(b) {
            const ui = this.qzUI(b), { novas, cheio } = this.qzBancoGuardar(b, Object.values(ui.sel));
            ui.sel = {};
            this.aviso((novas ? `${novas} ${novas === 1 ? 'questão adicionada' : 'questões adicionadas'} ao cartão.` : 'Essas questões já estavam no cartão.')
                + (cheio ? ` O cartão chegou no limite de ${MAX}.` : ' Clique em “Estudar” para resolver.'));
        },
        /** Simulado: N questões sorteadas dentro dos filtros; entram no cartão e o "Estudar" mostra só elas. */
        async qzSimulado(b) {
            const ui = this.qzUI(b), n = Math.max(1, Math.min(100, +ui.nSim || 10)), p = this.qzBancoParams(b);
            p.set('n', n);
            ui.carregando = true; ui.erro = '';
            const r = await pedir(URL_BANCO + '/simulado?' + p.toString());
            ui.carregando = false;
            if (!r.ok) { ui.erro = r.json.message || 'Não deu para montar o simulado agora.'; return; }
            if (!r.json.itens.length) { ui.erro = 'Nenhuma questão nesses filtros. Tire algum filtro.'; return; }
            const { ids, cheio } = this.qzBancoGuardar(b, r.json.itens);
            if (!ids.length) { ui.erro = `O cartão está no limite de ${MAX} questões. Tire algumas em “Editar”.`; return; }
            b.dados.resp ??= {};
            ids.forEach(id => delete b.dados.resp[id]);
            const bf = ui.bf, nome = [bf.banca, bf.materia, bf.assunto, bf.ano, DIFS[bf.dificuldade]].filter(Boolean).join(' · ') || 'Todas as provas';
            b.dados.simulado = { ids, em: new Date().toISOString(), nome };
            b.dados.modo = 'estudar'; ui.so = 'simulado'; ui.materia = ''; b.dados.i = 0;
            this.aviso(`Simulado com ${ids.length} questões pronto.` + (cheio ? ' Algumas não couberam no cartão.' : ''));
        },
        qzSimPlacar(b) {
            const s = b.dados.simulado;
            if (!s) return null;
            const r = b.dados.resp || {}, por = new Map((b.dados.questoes || []).map(q => [q.id, q]));
            const qs = s.ids.map(id => por.get(id)).filter(Boolean), feitas = qs.filter(q => q.id in r), ok = feitas.filter(q => r[q.id] === q.certa).length;
            return { total: qs.length, feitas: feitas.length, ok, pct: feitas.length ? Math.round(ok / feitas.length * 100) : 0, fim: qs.length > 0 && feitas.length === qs.length, nome: s.nome };
        },

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
        /** Dica do campo de texto conforme a rede escolhida. */
        crDica(p) { return DICAS_REDE[p.rede] || 'Escreva o roteiro (o que vai falar ou mostrar) e a legenda do post.'; },
        crRedes() { return Object.keys(REDES); },
        crUI(b) { this.wfCxUI[b.id] ??= { aberto: null, rede: '', ver: 'posts', ytCarregando: false, ytErro: '' }; return this.wfCxUI[b.id]; },
        crDa(b, etapa) {
            const ui = this.crUI(b);
            return (b.dados.posts || []).filter(p => p.etapa === etapa && (!ui.rede || p.rede === ui.rede))
                .sort((x, y) => String(x.data || '9').localeCompare(String(y.data || '9')));
        },
        crNovo(b, etapa) {
            if ((b.dados.posts || []).length >= MAX) { this.aviso(`Limite de ${MAX} posts por cartão.`); return; }
            const p = { id: uid(), titulo: '', rede: this.crUI(b).rede || 'Instagram', etapa: etapa || 'Ideia', data: '', legenda: '', tags: '', link: '' };
            b.dados.posts.push(p);
            this.crUI(b).aberto = p.id;
        },
        crMover(b, p, passo) { const i = ETAPAS_POST.indexOf(p.etapa); p.etapa = ETAPAS_POST[Math.min(ETAPAS_POST.length - 1, Math.max(0, i + passo))]; },
        crRemover(b, p) { if (confirm(`Excluir o post "${p.titulo || 'sem título'}"?`)) { b.dados.posts = b.dados.posts.filter(x => x.id !== p.id); this.crUI(b).aberto = null; } },
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
            const v = uid(), agora = new Date().toISOString();
            return this.wfNovoCartao(sec, 'rh', 'RH: vagas e candidatos', 720, 460, {
                vagas: [{ id: v, nome: 'Atendimento' }], passos: [...PASSOS_RH],
                criterios: CRITERIOS_RH.map(nome => ({ id: uid(), nome })), modelos: { ...MODELOS_RH }, auto: { tarefas: true, entrevista: true },
                cands: [{ id: uid(), nome: 'Candidato de exemplo', vaga: v, etapa: 'Triagem', contato: '', nota: 0, obs: '', feitos: {}, hist: [{ etapa: 'Triagem', em: agora }], score: {} }],
            }, 'RH adicionado. Mova os candidatos pelas etapas com ‹ ›.');
        },
        /** Completa cartões antigos com os campos novos (critérios, modelos de mensagem, automações). */
        rhPreparar(b) {
            const d = b.dados;
            if (!Array.isArray(d.criterios)) d.criterios = CRITERIOS_RH.map(nome => ({ id: uid(), nome }));
            if (!d.modelos || typeof d.modelos !== 'object') d.modelos = { ...MODELOS_RH };
            if (!d.auto || typeof d.auto !== 'object') d.auto = { tarefas: true, entrevista: true };
            if (!Array.isArray(d.passos)) d.passos = [...PASSOS_RH];
        },
        rhEtapas() { return ETAPAS_RH; },
        rhUI(b) { this.wfCxUI[b.id] ??= { vaga: '', aberto: null, ver: 'funil', agendar: false, msg: '', quando: '', dur: 45 }; return this.wfCxUI[b.id]; },
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
            const c = { id: uid(), nome: '', vaga: this.rhUI(b).vaga || ((b.dados.vagas || [])[0] || {}).id || '', etapa: 'Triagem', contato: '', nota: 0, obs: '', feitos: {}, hist: [{ etapa: 'Triagem', em: new Date().toISOString() }], score: {} };
            b.dados.cands.push(c);
            this.rhUI(b).aberto = c.id;
        },
        rhMover(b, c, passo) { const i = ETAPAS_RH.indexOf(c.etapa); this.rhIrPara(b, c, ETAPAS_RH[Math.min(ETAPAS_RH.length - 1, Math.max(0, i + passo))]); },
        /** Muda a etapa, guarda no histórico (para o tempo por etapa) e roda as automações da etapa nova. */
        rhIrPara(b, c, etapa) {
            if (!ETAPAS_RH.includes(etapa) || etapa === c.etapa) return;
            this.rhPreparar(b);
            c.hist = Array.isArray(c.hist) && c.hist.length ? c.hist : [{ etapa: c.etapa, em: null }];
            c.hist.push({ etapa, em: new Date().toISOString() });
            if (c.hist.length > 60) c.hist = c.hist.slice(-60);
            c.etapa = etapa;
            const ui = this.rhUI(b), auto = b.dados.auto;
            if (etapa === 'Contratado') {
                c.feitos ??= {};
                if (auto.tarefas && !c.tarefasCriadas) this.rhCriarTarefas(b, c);
                else this.aviso(`${c.nome || 'Candidato'} contratado. O checklist está na aba Integração.`);
            } else if (etapa === 'Entrevista' && auto.entrevista && !(c.entrevista && c.entrevista.quando)) {
                ui.aberto = c.id; ui.agendar = true; ui.msg = '';
                this.aviso('Escolha a data da entrevista para ela ir para o calendário.');
            }
        },
        /** Cria na aba Tarefas da área uma tarefa para cada passo da integração. */
        async rhCriarTarefas(b, c) {
            if (!URL_TAREFAS) { this.aviso('Não dá para criar tarefas nesta página.'); return; }
            const passos = (b.dados.passos || []).filter(p => String(p).trim()).slice(0, 40);
            if (!passos.length) { this.aviso('Cadastre os passos da integração primeiro.'); return; }
            const nome = c.nome || 'Novo contratado';
            const r = await pedir(URL_TAREFAS + '/' + areaDe(this), 'POST', {
                categoria: 'Integração',
                tarefas: passos.map((p, k) => ({ titulo: (nome + ': ' + p).slice(0, 150), descricao: 'Integração de ' + nome + ' (' + this.rhVaga(b, c.vaga) + '), criada pelo cartão de RH.', prazo: somaDias(k === passos.length - 1 && /30/.test(p) ? 30 : k + 1) })),
            });
            if (r.ok) {
                c.tarefasCriadas = r.json.ids.length;
                this.aviso(`${nome} contratado: ${r.json.ids.length} tarefas de integração criadas na aba Tarefas (recarregue para ver).`);
            } else this.aviso(r.json.message || 'Não deu para criar as tarefas agora. Use “Criar tarefas” na aba Integração.');
        },
        rhRemover(b, c) {
            if (!confirm(`Excluir "${c.nome || 'candidato sem nome'}"?`)) return;
            if (c.entrevista && c.entrevista.evento && URL_CAL) pedir(URL_CAL + '/' + c.entrevista.evento, 'DELETE');
            b.dados.cands = b.dados.cands.filter(x => x.id !== c.id); this.rhUI(b).aberto = null;
        },
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

        /* entrevista no calendário do painel (tabela eventos) */
        rhAbrirAgenda(b, c) {
            const ui = this.rhUI(b);
            ui.agendar = true; ui.msg = '';
            ui.quando = (c.entrevista && c.entrevista.quando) || '';
            ui.dur = (c.entrevista && c.entrevista.dur) || 45;
        },
        async rhAgendar(b, c) {
            const ui = this.rhUI(b);
            if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(ui.quando || '')) { ui.msg = 'Escolha o dia e a hora.'; return; }
            const fim = new Date(ui.quando);
            fim.setMinutes(fim.getMinutes() + (+ui.dur || 45));
            const corpo = {
                titulo: ('Entrevista: ' + (c.nome || 'candidato') + ' (' + this.rhVaga(b, c.vaga) + ')').slice(0, 120),
                descricao: ['Contato: ' + (c.contato || '—'), c.obs ? 'Anotações: ' + c.obs.slice(0, 1500) : ''].filter(Boolean).join('\n'),
                inicio: ui.quando, fim: Q.util.iso(fim) + 'T' + String(fim.getHours()).padStart(2, '0') + ':' + String(fim.getMinutes()).padStart(2, '0'),
                repete: 'nao', dia_todo: false, lembrete_min: 30, lembrar_email: false, cor: '#a78bfa',
            };
            if (!URL_CAL) { c.entrevista = { quando: ui.quando, dur: +ui.dur || 45, evento: null }; ui.agendar = false; return; }
            ui.msg = 'Agendando…';
            const ev = c.entrevista && c.entrevista.evento;
            let r = ev ? await pedir(URL_CAL + '/' + ev, 'PUT', corpo) : null;
            if (!r || r.status === 404) r = await pedir(URL_CAL, 'POST', corpo);
            if (!r.ok) { ui.msg = r.json.message || 'Não deu para agendar agora.'; return; }
            c.entrevista = { quando: ui.quando, dur: +ui.dur || 45, evento: (r.json.evento || {}).id || null };
            ui.agendar = false; ui.msg = '';
            this.aviso('Entrevista no calendário, com lembrete 30 minutos antes.');
        },
        async rhDesmarcar(b, c) {
            if (!c.entrevista || !confirm('Desmarcar a entrevista e tirar do calendário?')) return;
            if (c.entrevista.evento && URL_CAL) await pedir(URL_CAL + '/' + c.entrevista.evento, 'DELETE');
            c.entrevista = null;
        },
        rhQuando(c) { return c.entrevista && c.entrevista.quando ? fmtQuando(c.entrevista.quando) : ''; },

        /* mensagens prontas por etapa */
        rhMensagem(b, c) {
            const t = (b.dados.modelos || {})[c.etapa] || MODELOS_RH[c.etapa] || '';
            return t.replace(/\{nome\}/g, String(c.nome || '').trim().split(/\s+/)[0] || 'tudo bem')
                .replace(/\{vaga\}/g, this.rhVaga(b, c.vaga)).replace(/\{entrevista\}/g, this.rhQuando(c) || '(data a combinar)');
        },
        rhLinkWhats(b, c) {
            const d = String(c.contato || '').replace(/\D/g, '');
            if (/@/.test(c.contato || '') || d.length < 10) return '';
            return 'https://wa.me/' + (d.length <= 11 ? '55' + d : d) + '?text=' + encodeURIComponent(this.rhMensagem(b, c));
        },
        rhLinkEmail(b, c) {
            const t = String(c.contato || '').trim();
            if (!/^\S+@\S+\.\S+$/.test(t)) return '';
            return 'mailto:' + t + '?subject=' + encodeURIComponent('Vaga de ' + this.rhVaga(b, c.vaga)) + '&body=' + encodeURIComponent(this.rhMensagem(b, c));
        },
        rhModeloPadrao(b, etapa) { b.dados.modelos[etapa] = MODELOS_RH[etapa]; },

        /* avaliação por critérios */
        rhNotaCrit(b, c, cr, n) {
            c.score ??= {};
            if (c.score[cr.id] === n) delete c.score[cr.id]; else c.score[cr.id] = n;
            const m = this.rhMedia(b, c);
            c.nota = m ? Math.round(m) : 0;
        },
        rhMedia(b, c) {
            const ids = new Set((b.dados.criterios || []).map(x => x.id)), v = Object.entries(c.score || {}).filter(([k]) => ids.has(k)).map(([, n]) => n);
            return v.length ? v.reduce((s, n) => s + n, 0) / v.length : 0;
        },
        rhMediaTxt(b, c) { const m = this.rhMedia(b, c); return m ? m.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) : '—'; },
        rhNovoCriterio(b, campo) {
            const nome = (campo.value || '').trim().slice(0, 60);
            if (!nome || b.dados.criterios.length >= 12) return;
            b.dados.criterios.push({ id: uid(), nome });
            campo.value = '';
        },

        /* relatório: funil e tempo por etapa */
        rhRelatorio(b) {
            const ui = this.rhUI(b), cands = (b.dados.cands || []).filter(c => !ui.vaga || c.vaga === ui.vaga), agora = Date.now();
            const funil = ETAPAS_RH.slice(0, 5).map((e, i) => {
                // chegaram nesta etapa: estão nela ou passaram por ela (pelo histórico ou por estarem numa etapa depois)
                const n = cands.filter(c => {
                    const idx = ETAPAS_RH.indexOf(c.etapa);
                    return i === 0 || (idx >= i && c.etapa !== 'Recusado') || (c.hist || []).some(h => ETAPAS_RH.indexOf(h.etapa) >= i && h.etapa !== 'Recusado');
                }).length;
                return { etapa: e, n, agora: cands.filter(c => c.etapa === e).length };
            });
            const topo = funil[0].n || 1;
            funil.forEach(f => { f.pct = Math.round(f.n / topo * 100); });
            const tempos = ETAPAS_RH.slice(0, 4).map(e => {
                const dur = [];
                cands.forEach(c => {
                    const h = c.hist || [];
                    h.forEach((x, k) => {
                        if (x.etapa !== e || !x.em) return;
                        const fim = h[k + 1] ? Date.parse(h[k + 1].em) : (c.etapa === e ? agora : NaN);
                        if (Number.isFinite(fim)) dur.push((fim - Date.parse(x.em)) / 864e5);
                    });
                });
                return { etapa: e, dias: dur.length ? (dur.reduce((s, d) => s + d, 0) / dur.length) : null, n: dur.length };
            });
            const notas = cands.map(c => this.rhMedia(b, c)).filter(Boolean);
            return {
                funil, tempos, total: cands.length, recusados: cands.filter(c => c.etapa === 'Recusado').length,
                contratados: cands.filter(c => c.etapa === 'Contratado').length,
                media: notas.length ? (notas.reduce((s, n) => s + n, 0) / notas.length).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) : '—',
                vagas: (b.dados.vagas || []).map(v => ({ nome: v.nome, ativos: (b.dados.cands || []).filter(c => c.vaga === v.id && !['Recusado', 'Contratado'].includes(c.etapa)).length, contratados: (b.dados.cands || []).filter(c => c.vaga === v.id && c.etapa === 'Contratado').length })),
            };
        },
        rhDias(d) { return d == null ? '—' : d < 1 ? 'menos de 1 dia' : Math.round(d) + (Math.round(d) === 1 ? ' dia' : ' dias'); },
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
