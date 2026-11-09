/* Work Flower · editor do portfólio (Negócios › Portfólio).
   Estado inteiro em `d` (perfil, tema, seo, secoes). Cada mudança atualiza a prévia (POST /negocios/portfolio/previa,
   que devolve a página pública montada com o que está no editor) e "Salvar" manda tudo de uma vez.
   Imagens e GIFs: window.wfEnviarMidia (public/js/midia.js), guardadas como token de mídia. */
(function () {
    'use strict';

    const NOMES = {
        // estilos das seções
        cartoes: 'Cartões', lista: 'Lista', mosaico: 'Mosaico', destaque: 'Destaque', linha: 'Linha do tempo', simples: 'Simples',
        barras: 'Barras', etiquetas: 'Etiquetas', grade: 'Grade', circulos: 'Círculos', grandes: 'Grandes', citacao: 'Citação',
        carrossel: 'Carrossel', botoes: 'Botões', cartao: 'Cartão', dividido: 'Dividido',
        // tema
        classico: 'Clássico', lateral: 'Barra lateral', centralizado: 'Centralizado', revista: 'Revista',
        capa: 'Capa', minimo: 'Mínimo', gigante: 'Gigante', suaves: 'Suaves', retos: 'Retos', redondos: 'Redondos',
        leve: 'Leve', nenhuma: 'Nenhuma', forte: 'Forte', contorno: 'Contorno', cor: 'Cor', gradiente: 'Gradiente', imagem: 'Imagem/GIF', padrao: 'Desenho',
        pontos: 'Pontos', linhas: 'Linhas', xadrez: 'Xadrez', normal: 'Normal', estreita: 'Estreita', larga: 'Larga',
        compacto: 'Compacto', amplo: 'Amplo', p: 'Pequeno', m: 'Médio', g: 'Grande', maiusculas: 'Maiúsculas', sublinhado: 'Sublinhado', numerados: 'Numerados',
        circulo: 'Círculo', quadrado: 'Quadrado', arredondada: 'Arredondada', subir: 'Subir', aparecer: 'Aparecer', zoom: 'Zoom',
    };

    const ROTULOS = {
        projetos: { titulo: 'Nome do projeto', subtitulo: 'Categoria / ano', texto: 'Descrição', img: 'Imagem', link: 'Link do projeto', tags: 'Tags (separe com vírgula)' },
        experiencia: { titulo: 'Cargo', subtitulo: 'Empresa', periodo: 'Período', texto: 'O que você fez' },
        formacao: { titulo: 'Curso', subtitulo: 'Instituição', periodo: 'Período', texto: 'Detalhes' },
        habilidades: { titulo: 'Habilidade', nivel: 'Nível' },
        servicos: { titulo: 'Serviço', texto: 'Descrição', subtitulo: 'Preço (opcional)' },
        numeros: { titulo: 'Número', subtitulo: 'O que significa' },
        depoimentos: { texto: 'Depoimento', titulo: 'Quem disse', subtitulo: 'Cargo / empresa', img: 'Foto' },
        galeria: { img: 'Imagem', titulo: 'Legenda', link: 'Link ao clicar (opcional)' },
        links: { titulo: 'Texto do botão', link: 'Endereço' },
    };
    const DICAS = {
        projetos: { tags: 'Figma, React, Branding', link: 'https://…', subtitulo: 'App · 2026' },
        experiencia: { periodo: '2023 – atual' }, formacao: { periodo: '2018 – 2022' },
        numeros: { titulo: '120+', subtitulo: 'projetos entregues' }, servicos: { subtitulo: 'a partir de R$ 500' },
        links: { link: 'https://…' }, galeria: { link: 'https://…' },
    };

    const novoId = () => Math.random().toString(36).slice(2, 12).replace(/[^a-z0-9]/g, '') || String(Date.now());
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const copiaFunda = o => JSON.parse(JSON.stringify(o));

    window.portfolioEditor = function (cfg) {
        return {
            cfg,
            d: copiaFunda(cfg.dados),
            slug: cfg.slug,
            slugSalvo: cfg.slug,
            publicado: cfg.publicado,
            existe: cfg.existe,
            mensagens: cfg.mensagens || [],
            aba: 'conteudo',
            aberta: null,
            novoTipo: 'projetos',
            sujo: false,
            salvando: false,
            erro: '',
            enviando: 0,
            carregando: false,
            tela: '100%',
            estreito: false,
            verPrevia: false,
            arrasto: { tipo: null, de: null, alvo: null, secao: null },
            _seq: 0,
            _timer: null,

            iniciar() {
                const medir = () => { this.estreito = window.innerWidth < 1100; };
                medir();
                window.addEventListener('resize', medir);
                this.aberta = this.d.secoes[0]?.id || null;
                this.$watch('d', () => { this.sujo = true; this.erro = ''; this.agendarPrevia(); });
                window.addEventListener('beforeunload', e => { if (this.sujo) { e.preventDefault(); e.returnValue = ''; } });
                this.atualizarPrevia();
            },

            /* ---------- prévia ---------- */
            agendarPrevia() {
                clearTimeout(this._timer);
                this._timer = setTimeout(() => this.atualizarPrevia(), 350);
            },
            async atualizarPrevia() {
                if (this.estreito && !this.verPrevia) return;
                const seq = ++this._seq;
                this.carregando = true;
                try {
                    const r = await fetch(cfg.rotas.previa, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify({ dados: this.d, slug: this.slug }),
                    });
                    if (r.status === 401 || r.status === 419) { this.erro = 'Sua sessão expirou: recarregue a página (copie seus textos antes).'; return; }
                    if (!r.ok) return;
                    const html = await r.text();
                    if (seq !== this._seq) return; // chegou uma mais nova
                    const f = this.$refs.frame;
                    let y = 0;
                    try { y = f.contentWindow.scrollY; } catch (e) {}
                    f.onload = () => { try { f.contentWindow.scrollTo(0, y); } catch (e) {} };
                    f.srcdoc = html;
                } catch (e) { /* sem conexão: tenta de novo na próxima mudança */ }
                finally { if (seq === this._seq) this.carregando = false; }
            },

            /* ---------- salvar ---------- */
            async salvar() {
                if (this.salvando) return;
                this.salvando = true; this.erro = '';
                try {
                    const r = await fetch(cfg.rotas.salvar, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                        body: JSON.stringify({ slug: this.slug, publicado: this.publicado, dados: this.d }),
                    });
                    const j = await r.json().catch(() => ({}));
                    if (r.status === 419 || r.status === 401) { this.erro = 'Sua sessão expirou: recarregue a página.'; return; }
                    if (r.status === 422) { this.erro = Object.values(j.errors || {})[0]?.[0] || 'Confira os campos.'; return; }
                    if (!r.ok) { this.erro = 'Não consegui salvar (' + r.status + '). Tente de novo.'; return; }
                    this.existe = true; this.slugSalvo = this.slug; this.publicado = !!j.publicado;
                    this.$nextTick(() => { this.sujo = false; });
                    if (window.wfAviso) window.wfAviso(this.publicado ? 'Portfólio salvo e publicado.' : 'Portfólio salvo (ainda não publicado).');
                } catch (e) { this.erro = 'Sem conexão. Tente de novo.'; }
                finally { this.salvando = false; }
            },

            /* ---------- imagens ---------- */
            miniatura(v) {
                if (!v) return '';
                return /^[A-Za-z0-9]{32}$/.test(v) ? cfg.rotas.midia + '/' + v : v;
            },
            async enviar(ev, definir) {
                const arq = ev.target.files && ev.target.files[0];
                ev.target.value = '';
                if (!arq) return;
                if (!window.wfEnviarMidia) { this.erro = 'Recarregue a página e tente de novo.'; return; }
                this.enviando = 0.01;
                try {
                    const m = await window.wfEnviarMidia(arq, p => { this.enviando = Math.max(0.01, p); });
                    definir(m.token);
                } catch (e) { this.erro = e.message || 'Não deu para enviar a imagem.'; }
                finally { this.enviando = 0; }
            },

            /* ---------- seções e itens ---------- */
            mover(lista, de, para) {
                if (para < 0 || para >= lista.length || de === para) return;
                const [x] = lista.splice(de, 1);
                lista.splice(para, 0, x);
            },
            arrastar(ev, tipo, de, secao = null) {
                this.arrasto = { tipo, de, alvo: de, secao };
                try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', String(de)); } catch (e) {}
                const card = ev.target.closest(tipo === 'secao' ? '.pe-sec' : '.pe-item');
                if (card && ev.dataTransfer.setDragImage) ev.dataTransfer.setDragImage(card, 20, 20);
            },
            soltarSecao(para) {
                if (this.arrasto.tipo === 'secao' && this.arrasto.de !== null) this.mover(this.d.secoes, this.arrasto.de, para);
                this.arrasto = { tipo: null, de: null, alvo: null, secao: null };
            },
            soltarItem(si, para) {
                if (this.arrasto.tipo === 'item' && this.arrasto.secao === si) this.mover(this.d.secoes[si].itens, this.arrasto.de, para);
                this.arrasto = { tipo: null, de: null, alvo: null, secao: null };
            },
            novaSecao() {
                const t = this.novoTipo;
                const s = { id: novoId(), tipo: t, titulo: cfg.tipos[t].nome, subtitulo: '', texto: '', visivel: true, estilo: cfg.estilos[t][0], colunas: 3, fundo: 'nenhum', formulario: t === 'contato', img: null, itens: [] };
                if (cfg.tipos[t].itens.length) s.itens.push(this.itemVazio(t));
                this.d.secoes.push(s);
                this.aberta = s.id;
                this.$nextTick(() => [...document.querySelectorAll('.pe-sec')].pop()?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
            },
            duplicarSecao(si) {
                const c = copiaFunda(this.d.secoes[si]);
                c.id = novoId();
                c.itens.forEach(i => { i.id = novoId(); });
                this.d.secoes.splice(si + 1, 0, c);
                this.aberta = c.id;
            },
            itemVazio(tipo) {
                const it = { id: novoId() };
                cfg.tipos[tipo].itens.forEach(c => { it[c] = c === 'nivel' ? 80 : (c === 'img' ? null : ''); });
                return it;
            },
            novoItem(s) { s.itens.push(this.itemVazio(s.tipo)); },
            copia(it) { const c = copiaFunda(it); c.id = novoId(); return c; },
            usaTexto(t) { return !['numeros', 'depoimentos', 'galeria', 'links'].includes(t); },
            usaColunas(t) { return ['projetos', 'servicos', 'galeria', 'depoimentos', 'experiencia', 'formacao', 'habilidades'].includes(t); },
            rotulo(tipo, campo) { return (ROTULOS[tipo] || {})[campo] || campo; },
            dica(tipo, campo) { return (DICAS[tipo] || {})[campo] || ''; },
            nomeOp(o) { return NOMES[o] || o; },

            /* ---------- tema e SEO ---------- */
            aplicarTema(k) {
                const t = cfg.temas[k];
                Object.assign(this.d.tema, { preset: k, cor: t.cor, cor2: t.cor2, fundo: t.fundo, texto: t.texto, cartao: t.cartao, fonte_titulo: t.fonte_titulo, fonte_texto: t.fonte_texto, fundo_tipo: t.fundo_tipo });
            },
            tituloSeo() {
                const n = this.d.perfil.nome || 'Portfólio';
                return this.d.perfil.titulo ? n + ' · ' + this.d.perfil.titulo : n;
            },
            temFormulario() { return this.d.secoes.some(s => s.tipo === 'contato' && s.visivel && s.formulario); },

            /* ---------- mensagens ---------- */
            naoLidas() { return this.mensagens.filter(m => !m.lida).length; },
            linkContato(c) {
                if (/^\S+@\S+\.\S+$/.test(c)) return 'mailto:' + c;
                const n = (c || '').replace(/\D/g, '');
                return n.length >= 8 ? 'https://wa.me/' + (n.length <= 11 ? '55' + n : n) : '#';
            },
            async pedir(url, metodo, corpo) {
                const r = await fetch(url, {
                    method: metodo, credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    body: corpo ? JSON.stringify(corpo) : undefined,
                });
                if (!r.ok) throw new Error(String(r.status));
                return r.json();
            },
            async marcar(m, lida) {
                try { await this.pedir(cfg.rotas.mensagens + '/' + m.id + '/lida', 'POST', { lida }); m.lida = lida; }
                catch (e) { this.erro = 'Não consegui marcar a mensagem.'; }
            },
            async apagarMsg(m) {
                if (!confirm('Apagar a mensagem de ' + m.nome + '?')) return;
                try { await this.pedir(cfg.rotas.mensagens + '/' + m.id, 'DELETE'); this.mensagens = this.mensagens.filter(x => x.id !== m.id); }
                catch (e) { this.erro = 'Não consegui apagar a mensagem.'; }
            },
        };
    };
})();
