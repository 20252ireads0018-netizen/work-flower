/* Work Flower · cartões do Diverso fora do Diverso: "Banco de dados (.sql)", "GitHub / VS Code" e "Tabletop".
   Os três nasceram no Diverso. Quando uma área inteira ou cartões do Diverso (ou de uma área criada) são levados para o
   Corpo, a Mente, a Carteira ou os Negócios (App\Support\MoverCartoes), o cartão vai junto e passa a ser mostrado e
   criado lá também, como o "Documento" da Mente (documento-cartao.js):
   - mostra: partials/tipos-diverso carrega estilo e scripts (diverso.js → WFTabletop, tabletopVivo.js, banco-sql.js →
     WFBancoSql, ide-motores.js e mapa-codigo.js, partials/ide-cartao → WFIde e WFRepo) e os blocos.blade.php de cada área
     incluem partials/codigo-cartao, areas/diverso/partials/tabletop e o <div class="bd"> do banco. Tudo isso só nas
     páginas que têm o tipo (MoverCartoes::tiposDoDiverso), porque é pesado;
   - cria: o "+ Bloco" da página ganha o tipo quando estado.tiposTrazidos (gravado pelo servidor ao mover) o contém ou a
     página já tem um cartão dele.
   - dados que ficam fora do bloco seguem com ele: img.<id>.* (mapa e peças do Tabletop, índice do banco) e sqlz.<id>.*
     (linhas do banco, App\Support\PedacosBanco) vão para a tabela da área; a sala do Tabletop ao vivo é a do código
     guardado no bloco (b.vivo). "Minhas peças" do Tabletop (estado.pecasSalvas) são de cada área.
   Envolve os construtores corpoAbas / menteAbas / carteiraAbas (Negócios usa o da Carteira), como o tabela.js.
   Carregue depois do script da área e antes do Alpine. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || Q._tiposDiverso) return;
    Q._tiposDiverso = true;
    const { uid } = Q.util;
    const T = window.WFTabletop;   // diverso.js
    const BD = window.WFBancoSql;  // banco-sql.js

    const TIPOS = [
        { id: 'banco', nome: 'Banco de dados (.sql)' },
        { id: 'codigo', nome: 'GitHub / VS Code' },
        { id: 'tabletop', nome: 'Tabletop' },
    ];
    const IDS = TIPOS.map(t => t.id);
    const nomeDe = id => (TIPOS.find(t => t.id === id) || {}).nome || id;
    const copia = o => JSON.parse(JSON.stringify(o));

    /* [largura, altura, título, dados iniciais], os mesmos do Diverso (diverso.js, defBloco). O banco é criado pelo banco-sql.js. */
    function defBloco(tipo) {
        if (tipo === 'tabletop') return T ? T.novo() : null;
        if (tipo === 'codigo') return [640, 260, 'Código', { repos: [{ id: uid(), nome: '', pasta: '' }] }];
        // "Mapa do código" (IDE) e "Diagrama" (banco) criam um mapa mental logo abaixo do cartão
        if (tipo === 'mapa') return [720, 480, 'Mapa mental', { nos: [{ id: uid(), t: 'Tema central', x: 40, y: 180, pai: null, cor: Q.PALETA[0] }], tracos: [] }];
        return null;
    }

    /** Tipos do Diverso que esta página mostra e cria: os trazidos ao mover e os que já têm cartão aqui. */
    function tiposDaPagina(e) {
        const trazidos = Array.isArray(e && e.tiposTrazidos) ? e.tiposTrazidos : [];
        const blocos = Array.isArray(e && e.blocos) ? e.blocos : [];
        return IDS.filter(t => trazidos.includes(t) || blocos.some(b => b && b.tipo === t));
    }

    const metodos = () => ({
        ui: {},
        ttPecas: T ? T.pecas : [], ttFormas: T ? T.formas : [], ttFerramentas: T ? T.ferramentas : [],
        ...(T ? T.metodos() : {}),
        ...(T && window.WFTabletopVivo
            ? window.WFTabletopVivo.metodos({ uid, copia, reduzirSrc: T.reduzirSrc, normalizarTabletop: T.normalizar })
            : {}),
        /** Cria um bloco desses tipos (ou o mapa mental do "Mapa do código") no fim da aba ou em pos; devolve o id. */
        criarBloco(sec, tipo, pos, over = {}) {
            sec = sec || this.aba;
            const d = defBloco(tipo);
            if (!sec || !d) return null;
            const id = 'b' + uid(), W = this.col || 1088, w = Math.min(d[0], W);
            const dados = over.dados ? { ...d[3], ...over.dados } : d[3];
            const y0 = this.alturaConteudo(sec);
            this.estado.blocos.push({ id, secao: sec, tipo, titulo: over.titulo || d[2], links: [], dados });
            (this.estado.layout[sec] ??= {})[id] = pos
                ? { x: pos.x, y: pos.y, w, h: d[1], z: ++this.zTop }
                : { x: 0, y: y0 ? y0 + 16 : 0, w, h: d[1], z: ++this.zTop };
            return id;
        },
        /** Tira do servidor as chaves do bloco: img.<id> e img.<id>.* (o banco-sql.js também tira as linhas, sqlz.<id>.*). */
        apagarImagens(id) {
            Object.keys(this.imagens).filter(k => k === id || k.startsWith(id + '.')).forEach(k => {
                delete this.imagens[k];
                Q.enviar(`${this.cfg.url}/img.${k}`, null, 'DELETE').catch(() => {});
            });
        },
    });

    function envolver(nome) {
        const base = window[nome];
        if (typeof base !== 'function') return false;
        window[nome] = function () {
            const o = base.apply(this, arguments);
            const tem = tiposDaPagina(o.estado);
            if (!tem.length || !Array.isArray(o.tiposBloco)) return o;
            const extra = metodos();
            Object.keys(extra).forEach(k => { if (!(k in o)) o[k] = extra[k]; });
            if (BD) BD.estender(o, tem.includes('banco')); // criarBloco do banco, bd* e apagarImagens com as linhas
            tem.forEach(t => { if (!o.tiposBloco.some(x => x.id === t)) o.tiposBloco.push({ id: t, nome: nomeDe(t) }); });

            // "+ Bloco": a área não sabe criar esses tipos (o prepararPastas do quadro.js envolve este, para entrar na pasta aberta)
            const novo = o.novoBloco;
            o.novoBloco = function (sec, tipo, ...resto) {
                if (!IDS.includes(tipo)) return novo.call(this, sec, tipo, ...resto);
                const id = this.criarBloco(sec || this.aba, tipo);
                if (!id) return undefined;
                this.menuBloco = false;
                this.aviso(`${nomeDe(tipo)} adicionado ao fim da aba. Arraste para onde quiser.`);
                return id;
            };
            // excluir: o quadro.js só tira img.<id>; o mapa, as peças e o índice/linhas do banco ficam em img.<id>.* e sqlz.<id>.*
            const remover = o.removerBloco;
            o.removerBloco = function (b, ...resto) {
                const r = remover.call(this, b, ...resto);
                // (a sala ao vivo percebe sozinha que o bloco sumiu: tabletopVivo.js, vivoSumiu)
                if (b && IDS.includes(b.tipo) && !this.blocoPorId(b.id)) this.apagarImagens(b.id);
                return r;
            };
            // biblioteca: o Tabletop leva as imagens junto (como no Diverso)
            const guardar = o.guardarBloco;
            if (typeof guardar === 'function' && T) {
                o.guardarBloco = async function (b, ...resto) {
                    if (!b || b.tipo !== 'tabletop') return guardar.call(this, b, ...resto);
                    return guardar.call(this, { ...b, dados: await T.paraBiblioteca(this, b) }, ...resto);
                };
            }
            const colar = o.colarBloco;
            if (typeof colar === 'function' && T) {
                o.colarBloco = function (item, ...resto) {
                    const antes = this.estado.blocos.length;
                    const r = colar.call(this, item, ...resto);
                    const nb = this.estado.blocos[this.estado.blocos.length - 1];
                    if (r !== false && nb && this.estado.blocos.length > antes && nb.tipo === 'tabletop') T.daBiblioteca(this, nb);
                    return r;
                };
            }

            const ini = o.init;
            o.init = function () {
                if (tem.includes('tabletop') && T) {
                    // como no Diverso: "Minhas peças" validadas e tabletops antigos consertados (antes de vigiar o estado)
                    this.estado.pecasSalvas = T.normalizarModelos(this.estado.pecasSalvas);
                    this.estado.blocos.forEach(b => { if (b && b.tipo === 'tabletop') T.normalizar(b.dados || (b.dados = {})); });
                }
                const r = typeof ini === 'function' ? ini.apply(this, arguments) : undefined;
                // Tabletop ao vivo: volta a se ligar às salas e envia o que mudar
                if (typeof this.vivoIniciarTudo === 'function') {
                    this.$watch('estado', () => this.vivoAlterou?.());
                    this.vivoIniciarTudo();
                }
                return r;
            };
            return o;
        };
        return true;
    }

    const ok = ['corpoAbas', 'menteAbas', 'carteiraAbas'].map(envolver);
    if (!ok.some(Boolean)) console.warn('cartoes-diverso.js: carregue depois de corpo.js, mente.js ou carteira.js.');
})();
