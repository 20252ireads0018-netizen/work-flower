/* Work Flower · mini-IDE do bloco "GitHub / VS Code"
   Uso: <div x-data="WFIde(b)"> dentro do bloco do tipo "codigo".
   O projeto fica em b.dados.ide = { arquivos: [{id, caminho, texto}], pastas: [], abertas: [], ativo }.
   Não depende de nada do diverso.js. */
(function () {
    'use strict';

    const U = (window.WFQuadro && window.WFQuadro.util) || {};
    const uid = U.uid || (() => Math.random().toString(36).slice(2, 10));

    const MAX_ARQ = 300, MAX_BYTES = 400000, MAX_TOTAL = 3000000, MAX_SAIDA = 500;
    const LH = 19.5; // altura da linha (13px * 1.5), igual ao CSS
    const IGNORAR = new Set(['node_modules', '.git', 'vendor', 'dist', 'build', '.next', '__pycache__', '.idea', '.venv', 'venv', 'storage', '.cache']);
    const BINARIO = /\.(png|jpe?g|gif|webp|ico|bmp|pdf|zip|rar|7z|gz|tar|mp[34]|wav|ogg|webm|mov|woff2?|ttf|otf|eot|exe|dll|so|bin|psd|sqlite|db)$/i;

    /* ---------- caminhos ---------- */
    function limpar(p) {
        const s = String(p || '').replace(/\\/g, '/').split('/').map(x => x.trim()).filter(x => x && x !== '.');
        if (s.some(x => x === '..' || x.length > 100 || /[<>:"|?*\u0000-\u001f]/.test(x))) return '';
        const r = s.join('/');
        return r.length > 200 ? '' : r;
    }
    const pai = p => (p.includes('/') ? p.slice(0, p.lastIndexOf('/')) : '');
    const nomeDe = p => p.slice(p.lastIndexOf('/') + 1);
    function extDe(c) {
        const n = nomeDe(c), i = n.lastIndexOf('.');
        return i > 0 ? n.slice(i + 1).toLowerCase() : '';
    }
    const ehHtml = c => ['html', 'htm'].includes(extDe(c));

    /* ---------- realce de sintaxe ---------- */
    const GRUPO = {
        js: 'js', mjs: 'js', cjs: 'js', jsx: 'js', ts: 'js', tsx: 'js', java: 'js', c: 'js', h: 'js', cpp: 'js', cs: 'js', go: 'js', rs: 'js', php: 'js', kt: 'js', swift: 'js', dart: 'js',
        py: 'hash', sh: 'hash', rb: 'hash', yml: 'hash', yaml: 'hash', toml: 'hash', env: 'hash',
        css: 'css', scss: 'css', less: 'css',
        html: 'html', htm: 'html', xml: 'html', svg: 'html', vue: 'html',
        json: 'json', md: 'md',
    };
    const mk = (c, s, n, k, t, f) => new RegExp([c, s, n, k, t].map(x => '(' + (x || '(?!)') + ')').join('|'), f || 'g');
    const S_DUPLO = /"(?:\\.|[^"\\\n])*"|'(?:\\.|[^'\\\n])*'/.source;
    const S_CRASE = /`(?:\\.|[^`\\])*`/.source;
    const NUM = /\b\d+(?:\.\d+)?\b/.source;
    const KW = /\b(?:abstract|and|as|async|await|break|case|catch|class|const|continue|def|default|del|do|echo|elif|else|enum|except|export|extends|false|False|finally|fn|for|foreach|from|function|if|implements|import|in|instanceof|interface|is|lambda|let|match|namespace|new|None|not|null|of|or|pass|print|private|protected|public|raise|return|self|static|super|switch|this|throw|true|True|try|typeof|undefined|use|var|void|while|with|yield)\b/.source;
    const REGEX = {
        js: mk(/\/\/[^\n]*|\/\*[\s\S]*?\*\//.source, S_DUPLO + '|' + S_CRASE, NUM, KW, null),
        hash: mk(/#[^\n]*/.source, S_DUPLO, NUM, KW, null),
        css: mk(/\/\*[\s\S]*?\*\//.source, S_DUPLO, /#[0-9a-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw|ms|s|deg)?\b/.source, /[a-z-]+(?=\s*:)/.source, null),
        html: mk(/<!--[\s\S]*?-->/.source, null, null, null, /<\/?[A-Za-z][^>]*>/.source),
        json: mk(null, /"(?:\\.|[^"\\\n])*"/.source, /-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b/.source, /\b(?:true|false|null)\b/.source, null),
        md: mk(null, /`[^`\n]+`/.source, null, null, /^#{1,6} [^\n]*/.source, 'gm'),
    };
    const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    function realcar(txt, grupo) {
        const R = REGEX[grupo];
        if (!R || txt.length > 200000) return esc(txt);
        let out = '', i = 0, m;
        R.lastIndex = 0;
        while ((m = R.exec(txt))) {
            if (m[0] === '') { R.lastIndex++; continue; }
            out += esc(txt.slice(i, m.index));
            const cls = m[1] !== undefined ? 'c' : m[2] !== undefined ? 's' : m[3] !== undefined ? 'n' : m[4] !== undefined ? 'k' : 't';
            out += '<span class="wf-h-' + cls + '">' + esc(m[0]) + '</span>';
            i = m.index + m[0].length;
        }
        return out + esc(txt.slice(i));
    }

    /* ---------- execução (iframe isolado) ---------- */
    const shim = n => '<script>(function(){var T=' + n + ';'
        + 'function f(a){return Array.prototype.map.call(a,function(x){try{if(typeof x==="string")return x;if(x instanceof Error)return x.stack||String(x);var s=JSON.stringify(x,null,2);return s===undefined?String(x):s}catch(e){return String(x)}}).join(" ")}'
        + 'function p(t,m){parent.postMessage({wfIde:T,t:t,m:m},"*")}'
        + '["log","info","warn","error"].forEach(function(k){console[k]=function(){p(k,f(arguments))}});'
        + 'window.addEventListener("error",function(e){p("error",e.message+(e.lineno?" (linha "+e.lineno+")":""))});'
        + 'window.addEventListener("unhandledrejection",function(e){p("error",String(e.reason))})})();</script>';

    function resolver(base, rel) {
        if (/^(?:[a-z]+:)?\/\//i.test(rel) || /^(?:data|blob|mailto):/i.test(rel)) return null;
        const partes = (rel.startsWith('/') ? rel.slice(1) : (base ? base + '/' : '') + rel).split('/'), o = [];
        partes.forEach(p => { if (p === '..') o.pop(); else if (p && p !== '.') o.push(p); });
        return o.join('/');
    }

    /* Expressões usadas para achar e embutir os arquivos que o HTML referencia */
    const RE_LINK = /<link\b[^>]*?href=["']([^"']+)["'][^>]*>/gi;
    const RE_SCRIPT = /<script\b([^>]*?)\ssrc=["']([^"']+)["']([^>]*)>\s*<\/script>/gi;
    const semConsulta = s => s.split(/[?#]/)[0];

    /** Caminhos (já resolvidos) dos arquivos que um HTML carrega por <link rel="stylesheet"> e <script src>. */
    function referenciasDe(h) {
        const base = pai(h.caminho), set = new Set();
        h.texto.replace(RE_LINK, (m, href) => {
            if (/stylesheet/i.test(m)) { const c = resolver(base, semConsulta(href)); if (c) set.add(c); }
            return m;
        });
        h.texto.replace(RE_SCRIPT, (m, x, src) => {
            const c = resolver(base, semConsulta(src)); if (c) set.add(c);
            return m;
        });
        return set;
    }

    /* ---------- ZIP (sem compressão) ---------- */
    function crc32(u8) {
        let t = crc32.t;
        if (!t) {
            t = crc32.t = [];
            for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; }
        }
        let r = 0xFFFFFFFF;
        for (let i = 0; i < u8.length; i++) r = t[(r ^ u8[i]) & 255] ^ (r >>> 8);
        return (r ^ 0xFFFFFFFF) >>> 0;
    }
    function criarZip(arquivos) {
        const enc = new TextEncoder(), partes = [], central = [];
        let off = 0, tamCentral = 0;
        arquivos.forEach(f => {
            const nome = enc.encode(f.caminho), dados = enc.encode(f.texto), c = crc32(dados);
            const h = new DataView(new ArrayBuffer(30));
            h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(6, 0x0800, true);
            h.setUint16(10, 0, true); h.setUint16(12, 0x21, true);
            h.setUint32(14, c, true); h.setUint32(18, dados.length, true); h.setUint32(22, dados.length, true);
            h.setUint16(26, nome.length, true);
            partes.push(h.buffer, nome, dados);
            const ch = new DataView(new ArrayBuffer(46));
            ch.setUint32(0, 0x02014b50, true); ch.setUint16(4, 20, true); ch.setUint16(6, 20, true); ch.setUint16(8, 0x0800, true);
            ch.setUint16(14, 0x21, true);
            ch.setUint32(16, c, true); ch.setUint32(20, dados.length, true); ch.setUint32(24, dados.length, true);
            ch.setUint16(28, nome.length, true); ch.setUint32(42, off, true);
            central.push(ch.buffer, nome);
            tamCentral += 46 + nome.length;
            off += 30 + nome.length + dados.length;
        });
        const fim = new DataView(new ArrayBuffer(22));
        fim.setUint32(0, 0x06054b50, true); fim.setUint16(8, arquivos.length, true); fim.setUint16(10, arquivos.length, true);
        fim.setUint32(12, tamCentral, true); fim.setUint32(16, off, true);
        return new Blob([...partes, ...central, fim.buffer], { type: 'application/zip' });
    }
    function baixarBlob(blob, nome) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = nome;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    }

    function inserir(ta, txt) {
        ta.focus();
        if (!document.execCommand('insertText', false, txt)) {
            ta.setRangeText(txt, ta.selectionStart, ta.selectionEnd, 'end');
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /* ---------- projeto inicial ---------- */
    function projetoInicial() {
        const f = (caminho, texto) => ({ id: uid(), caminho, texto });
        return [
            f('README.md', '# Meu projeto\n\nEdite os arquivos ao lado.\n\n- Ctrl+S: salvar\n- Ctrl+Enter: executar (.js) ou abrir a prévia (.html)\n- Ctrl+F: buscar e substituir\n'),
            f('index.html', '<!doctype html>\n<html lang="pt-BR">\n<head>\n  <meta charset="utf-8">\n  <title>Meu projeto</title>\n  <link rel="stylesheet" href="style.css">\n</head>\n<body>\n  <h1>Olá, Work Flower!</h1>\n  <button id="b">Clique aqui</button>\n  <script src="script.js"></' + 'script>\n</body>\n</html>\n'),
            f('style.css', 'body {\n  font-family: system-ui, sans-serif;\n  padding: 2rem;\n}\n\nbutton {\n  padding: .5rem 1rem;\n  border-radius: .5rem;\n}\n'),
            f('script.js', "const botao = document.getElementById('b');\nlet n = 0;\n\nbotao.addEventListener('click', () => {\n  n++;\n  botao.textContent = 'Cliques: ' + n;\n  console.log('clique', n);\n});\n\nconsole.log('Olá do script.js!');\n"),
        ];
    }

    function garantir(b) {
        if (!b.dados.ide || typeof b.dados.ide !== 'object') b.dados.ide = {};
        const I = b.dados.ide;
        if (!Array.isArray(I.arquivos)) I.arquivos = [];
        if (!Array.isArray(I.pastas)) I.pastas = [];
        if (!Array.isArray(I.abertas)) I.abertas = [];
        if (!I.arquivos.length && !I.criado) {
            I.arquivos = projetoInicial();
            I.criado = true;
            I.abertas = [I.arquivos[3].id];
            I.ativo = I.arquivos[3].id;
        }
        if (!I.criado) I.criado = true;
        return I;
    }

    /* Aumenta o cartão quando o editor é aberto (o editor precisa de espaço). */
    function ampliar(L) {
        if (!L) return;
        L.h = Math.max(L.h || 0, 560);
        L.w = Math.max(L.w || 0, 820);
    }

    function WFIde(b) {
        const I = garantir(b);
        let dir = null;                 // pasta do computador (fora do estado reativo)
        let pend = [];                  // caminhos a apagar do disco no próximo "Salvar na pasta"
        const gravados = new Map();     // caminho -> texto gravado por último no disco
        let timer = 0, token = 0, ouvinte = null;
        let htmlId = null, timerPrev = 0; // página aberta na prévia (para atualizar sozinha ao editar)

        return {
            sel: '', fechadas: [], ln: 1, col: 1, msg: '',
            saida: [], srcdoc: '', painel: null, disco: '',
            busca: { on: false, q: '', r: '', todos: false },
            suporteDisco: typeof window.showDirectoryPicker === 'function',

            iniciar() {
                ouvinte = ev => {
                    const d = ev.data;
                    if (!d || d.wfIde !== token || ev.source !== (this.$refs.frame && this.$refs.frame.contentWindow)) return;
                    this.saida.push({ t: d.t, m: String(d.m).slice(0, 4000) });
                    if (this.saida.length > MAX_SAIDA) this.saida.shift();
                };
                window.addEventListener('message', ouvinte);
                this.$nextTick(() => { this.sincronizar(); this.cursor(); });
            },
            destroy() {
                if (ouvinte) window.removeEventListener('message', ouvinte);
                clearTimeout(timer); clearTimeout(timerPrev);
            },

            aviso(m) {
                this.msg = m;
                clearTimeout(timer);
                timer = setTimeout(() => { this.msg = ''; }, 4500);
            },

            /* ---------- árvore ---------- */
            todasPastas() {
                const s = new Set(I.pastas);
                I.arquivos.forEach(f => { const p = f.caminho.split('/'); for (let i = 1; i < p.length; i++) s.add(p.slice(0, i).join('/')); });
                return s;
            },
            existe(c) { return I.arquivos.some(f => f.caminho === c) || this.todasPastas().has(c); },
            arvore() {
                const filhos = {};
                const no = p => (filhos[p] ??= { pastas: [], arqs: [] });
                this.todasPastas().forEach(p => no(pai(p)).pastas.push(p));
                I.arquivos.forEach(f => no(pai(f.caminho)).arqs.push(f));
                const out = [];
                const andar = (p, nivel) => {
                    const c = filhos[p];
                    if (!c) return;
                    c.pastas.sort((x, y) => nomeDe(x).localeCompare(nomeDe(y), 'pt-BR')).forEach(d => {
                        const fe = this.fechadas.includes(d);
                        out.push({ k: 'p', caminho: d, nome: nomeDe(d), nivel, fechada: fe });
                        if (!fe) andar(d, nivel + 1);
                    });
                    c.arqs.sort((x, y) => nomeDe(x.caminho).localeCompare(nomeDe(y.caminho), 'pt-BR')).forEach(f =>
                        out.push({ k: 'a', caminho: f.caminho, nome: nomeDe(f.caminho), nivel, id: f.id }));
                };
                andar('', 0);
                return out;
            },
            clicar(n) {
                this.sel = n.caminho;
                if (n.k === 'p') {
                    const i = this.fechadas.indexOf(n.caminho);
                    if (i >= 0) this.fechadas.splice(i, 1); else this.fechadas.push(n.caminho);
                } else this.abrir(n.id);
            },
            pastaAlvo() {
                if (!this.sel) return '';
                return I.arquivos.some(f => f.caminho === this.sel) ? pai(this.sel) : this.sel;
            },

            /* ---------- abas e arquivo ativo ---------- */
            arqAtivo() { return I.arquivos.find(f => f.id === I.ativo) || null; },
            abas() { return I.abertas.map(id => I.arquivos.find(f => f.id === id)).filter(Boolean); },
            nomeArq(f) { return nomeDe(f.caminho); },
            abrir(id) {
                const f = I.arquivos.find(x => x.id === id);
                if (!f) return;
                const mudou = I.ativo !== id;
                if (!I.abertas.includes(id)) I.abertas.push(id);
                I.ativo = id;
                this.sel = f.caminho;
                this.$nextTick(() => {
                    const ta = this.$refs.ta;
                    if (mudou && ta) { ta.scrollTop = 0; ta.scrollLeft = 0; ta.setSelectionRange(0, 0); }
                    this.sincronizar(); this.cursor();
                });
            },
            fechar(id) {
                const i = I.abertas.indexOf(id);
                if (i < 0) return;
                I.abertas.splice(i, 1);
                if (I.ativo === id) I.ativo = I.abertas[i] ?? I.abertas[i - 1] ?? null;
            },
            extensao() { const f = this.arqAtivo(); return f ? (extDe(f.caminho) || 'txt') : ''; },

            /* ---------- editor ---------- */
            realce() { const f = this.arqAtivo(); return f ? realcar(f.texto, GRUPO[extDe(f.caminho)]) + '\n' : ''; },
            numeros() {
                const f = this.arqAtivo();
                if (!f) return '';
                const n = f.texto.split('\n').length;
                return Array.from({ length: n }, (_, i) => i + 1).join('\n');
            },
            totalLinhas() { const f = this.arqAtivo(); return f ? f.texto.split('\n').length : 0; },
            editar(e) {
                const f = this.arqAtivo();
                if (f) f.texto = e.target.value;
                this.cursor();
                this.atualizarPrevia();
            },
            sincronizar() {
                const ta = this.$refs.ta;
                if (!ta) return;
                if (this.$refs.pre) { this.$refs.pre.scrollTop = ta.scrollTop; this.$refs.pre.scrollLeft = ta.scrollLeft; }
                if (this.$refs.num) this.$refs.num.scrollTop = ta.scrollTop;
            },
            cursor() {
                const ta = this.$refs.ta;
                if (!ta) return;
                const ls = ta.value.slice(0, ta.selectionStart).split('\n');
                this.ln = ls.length;
                this.col = ls[ls.length - 1].length + 1;
            },
            selecionar(ini, fim) {
                const ta = this.$refs.ta;
                if (!ta) return;
                ta.focus();
                ta.setSelectionRange(ini, fim);
                const linha = ta.value.slice(0, ini).split('\n').length - 1;
                ta.scrollTop = Math.max(0, linha * LH - ta.clientHeight / 2);
                this.sincronizar();
                this.cursor();
            },
            teclas(e) {
                const ta = e.target, ini = ta.selectionStart, fim = ta.selectionEnd, v = ta.value, mod = e.ctrlKey || e.metaKey;
                if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); this.salvar(); return; }
                if (mod && e.key.toLowerCase() === 'f') { e.preventDefault(); this.abrirBusca(); return; }
                if (mod && e.key === 'Enter') { e.preventDefault(); this.executar(); return; }

                if (e.key === 'Tab') {
                    e.preventDefault();
                    const multi = v.slice(ini, fim).includes('\n');
                    if (!multi && !e.shiftKey) { inserir(ta, '  '); return; }
                    const a = v.lastIndexOf('\n', ini - 1) + 1;
                    let z;
                    if (fim > ini && v[fim - 1] === '\n') z = fim - 1;
                    else { z = v.indexOf('\n', fim); if (z < 0) z = v.length; }
                    const novo = v.slice(a, z).split('\n').map(l => (e.shiftKey ? l.replace(/^(?:  |\t| )/, '') : '  ' + l)).join('\n');
                    ta.setSelectionRange(a, z);
                    inserir(ta, novo);
                    ta.setSelectionRange(a, a + novo.length);
                    return;
                }
                if (e.key === 'Enter' && !e.shiftKey && !mod && !e.altKey) {
                    const a = v.lastIndexOf('\n', ini - 1) + 1;
                    const ind = v.slice(a, ini).match(/^[ \t]*/)[0];
                    const antes = v[ini - 1], depois = v[fim];
                    const ab = '{[(', fe = '}])';
                    e.preventDefault();
                    if (antes && depois && ab.indexOf(antes) >= 0 && ab.indexOf(antes) === fe.indexOf(depois)) {
                        inserir(ta, '\n' + ind + '  \n' + ind);
                        const p = ini + 1 + ind.length + 2;
                        ta.setSelectionRange(p, p);
                    } else {
                        const extra = antes && (ab.includes(antes) || (antes === ':' && extDe(this.arqAtivo()?.caminho || '') === 'py')) ? '  ' : '';
                        inserir(ta, '\n' + ind + extra);
                    }
                    return;
                }
                if (e.key === 'Escape' && this.busca.on) { this.busca.on = false; }
            },
            salvar() {
                if (dir) this.salvarDisco();
                else this.aviso('Salvo no Work Flower (o salvamento é automático).');
            },

            /* ---------- criar, renomear e excluir ---------- */
            novoArquivo() {
                const base = this.pastaAlvo();
                const nome = prompt('Nome do novo arquivo' + (base ? ' (em ' + base + '/)' : '') + ':', 'novo.js');
                if (!nome) return;
                const c = limpar((base ? base + '/' : '') + nome);
                if (!c) { this.aviso('Nome inválido.'); return; }
                const ja = I.arquivos.find(f => f.caminho === c);
                if (ja) { this.abrir(ja.id); this.aviso('Esse arquivo já existe.'); return; }
                if (this.todasPastas().has(c)) { this.aviso('Já existe uma pasta com esse nome.'); return; }
                if (I.arquivos.length >= MAX_ARQ) { this.aviso('Limite de ' + MAX_ARQ + ' arquivos neste projeto.'); return; }
                const f = { id: uid(), caminho: c, texto: '' };
                I.arquivos.push(f);
                this.fechadas = this.fechadas.filter(p => !c.startsWith(p + '/'));
                this.abrir(f.id);
                this.atualizarPrevia();
            },
            novaPasta() {
                const base = this.pastaAlvo();
                const nome = prompt('Nome da nova pasta' + (base ? ' (em ' + base + '/)' : '') + ':', 'src');
                if (!nome) return;
                const c = limpar((base ? base + '/' : '') + nome);
                if (!c) { this.aviso('Nome inválido.'); return; }
                if (this.existe(c)) { this.aviso('Já existe algo com esse nome.'); return; }
                I.pastas.push(c);
                this.fechadas = this.fechadas.filter(p => !c.startsWith(p + '/'));
                this.sel = c;
            },
            renomear(n) {
                const entrada = prompt('Novo nome ou caminho:', n.caminho);
                if (entrada === null) return;
                const novo = limpar(entrada);
                if (!novo || novo === n.caminho) { if (!novo) this.aviso('Nome inválido.'); return; }
                if (this.existe(novo)) { this.aviso('Já existe algo com esse caminho.'); return; }
                if (n.k === 'a') {
                    const f = I.arquivos.find(x => x.caminho === n.caminho);
                    if (!f) return;
                    pend.push(f.caminho);
                    f.caminho = novo;
                    this.sel = novo;
                    this.atualizarPrevia();
                    return;
                }
                const pre = n.caminho + '/';
                if (novo.startsWith(pre)) { this.aviso('Não dá para mover uma pasta para dentro dela mesma.'); return; }
                I.arquivos.filter(f => f.caminho.startsWith(pre)).forEach(f => { pend.push(f.caminho); f.caminho = novo + '/' + f.caminho.slice(pre.length); });
                I.pastas = I.pastas.map(p => (p === n.caminho ? novo : p.startsWith(pre) ? novo + '/' + p.slice(pre.length) : p));
                this.fechadas = this.fechadas.map(p => (p === n.caminho ? novo : p.startsWith(pre) ? novo + '/' + p.slice(pre.length) : p));
                this.sel = novo;
                this.atualizarPrevia();
            },
            excluir(n) {
                const extra = dir ? ' Ao salvar na pasta, ele também será apagado do disco.' : '';
                if (n.k === 'a') {
                    if (!confirm('Excluir o arquivo "' + n.caminho + '"?' + extra)) return;
                    const f = I.arquivos.find(x => x.caminho === n.caminho);
                    if (!f) return;
                    this.fechar(f.id);
                    I.arquivos = I.arquivos.filter(x => x.id !== f.id);
                    pend.push(f.caminho);
                } else {
                    const pre = n.caminho + '/', lista = I.arquivos.filter(f => f.caminho.startsWith(pre));
                    if (!confirm('Excluir a pasta "' + n.caminho + '" e os ' + lista.length + ' arquivo(s) dela?' + extra)) return;
                    lista.forEach(f => { this.fechar(f.id); pend.push(f.caminho); });
                    I.arquivos = I.arquivos.filter(f => !f.caminho.startsWith(pre));
                    I.pastas = I.pastas.filter(p => p !== n.caminho && !p.startsWith(pre));
                }
                this.sel = '';
                this.atualizarPrevia();
            },

            /* ---------- arquivos do computador ---------- */
            async enviar(lista) {
                const base = this.pastaAlvo();
                let ok = 0, pulados = 0;
                for (const arq of Array.from(lista || [])) {
                    const c = limpar((base ? base + '/' : '') + arq.name);
                    if (!c || arq.size > MAX_BYTES || BINARIO.test(arq.name)) { pulados++; continue; }
                    const t = await arq.text();
                    if (t.includes('\u0000')) { pulados++; continue; }
                    const ja = I.arquivos.find(f => f.caminho === c);
                    if (ja) ja.texto = t;
                    else if (I.arquivos.length < MAX_ARQ) I.arquivos.push({ id: uid(), caminho: c, texto: t });
                    else { pulados++; continue; }
                    ok++;
                }
                this.aviso(ok + ' arquivo(s) enviado(s)' + (pulados ? ', ' + pulados + ' ignorado(s) (binário, grande demais ou limite)' : '') + '.');
                this.atualizarPrevia();
            },
            baixar() {
                const f = this.arqAtivo();
                if (!f) return;
                baixarBlob(new Blob([f.texto], { type: 'text/plain;charset=utf-8' }), nomeDe(f.caminho));
            },
            baixarZip() {
                if (!I.arquivos.length) { this.aviso('O projeto está vazio.'); return; }
                const nome = String(b.titulo || 'projeto').replace(/[^\w.-]+/g, '_').slice(0, 40) || 'projeto';
                baixarBlob(criarZip(I.arquivos), nome + '.zip');
            },

            /* ---------- pasta real do computador (Chrome / Edge) ---------- */
            async abrirPasta() {
                if (!this.suporteDisco) return;
                try {
                    const h = await window.showDirectoryPicker({ mode: 'readwrite' });
                    if (I.arquivos.length && !confirm('Substituir o projeto atual pelos arquivos da pasta "' + h.name + '"?')) return;
                    const arqs = [], pastas = [];
                    let total = 0, pulados = 0;
                    const andar = async (hd, pre, nivel) => {
                        for await (const [nome, ent] of hd.entries()) {
                            const caminho = limpar(pre + nome);
                            if (ent.kind === 'directory') {
                                if (IGNORAR.has(nome) || nivel > 8 || !caminho) continue;
                                pastas.push(caminho);
                                await andar(ent, caminho + '/', nivel + 1);
                            } else {
                                if (!caminho || arqs.length >= MAX_ARQ || BINARIO.test(nome)) { pulados++; continue; }
                                const fi = await ent.getFile();
                                if (fi.size > MAX_BYTES || total + fi.size > MAX_TOTAL) { pulados++; continue; }
                                const t = await fi.text();
                                if (t.includes('\u0000')) { pulados++; continue; }
                                arqs.push({ id: uid(), caminho, texto: t });
                                total += fi.size;
                            }
                        }
                    };
                    this.aviso('Lendo a pasta…');
                    await andar(h, '', 0);
                    dir = h; pend = []; gravados.clear();
                    arqs.forEach(f => gravados.set(f.caminho, f.texto));
                    I.arquivos = arqs; I.pastas = pastas; I.abertas = []; I.ativo = null; I.criado = true;
                    this.fechadas = []; this.sel = ''; this.disco = h.name;
                    htmlId = null;
                    const primeiro = arqs.find(f => /^readme/i.test(f.caminho)) || arqs[0];
                    if (primeiro) this.abrir(primeiro.id);
                    this.aviso(arqs.length + ' arquivo(s) carregado(s) de "' + h.name + '"' + (pulados ? ' (' + pulados + ' ignorado(s): binários, grandes ou limite)' : '') + '.');
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    this.aviso('Não consegui abrir a pasta.');
                }
            },
            async salvarDisco() {
                if (!dir) return;
                try {
                    if (dir.requestPermission && (await dir.requestPermission({ mode: 'readwrite' })) !== 'granted') { this.aviso('Sem permissão para gravar na pasta.'); return; }
                    const ir = async (caminho, criar) => {
                        let h = dir;
                        for (const p of (caminho ? caminho.split('/') : [])) h = await h.getDirectoryHandle(p, { create: criar });
                        return h;
                    };
                    let n = 0;
                    for (const p of I.pastas) await ir(p, true);
                    for (const f of I.arquivos) {
                        if (gravados.get(f.caminho) === f.texto) continue;
                        const pasta = await ir(pai(f.caminho), true);
                        const fh = await pasta.getFileHandle(nomeDe(f.caminho), { create: true });
                        const w = await fh.createWritable();
                        await w.write(f.texto);
                        await w.close();
                        gravados.set(f.caminho, f.texto);
                        n++;
                    }
                    const atuais = new Set(I.arquivos.map(f => f.caminho));
                    let apagados = 0;
                    for (const c of [...new Set(pend)]) {
                        if (atuais.has(c)) continue;
                        try { const pasta = await ir(pai(c), false); await pasta.removeEntry(nomeDe(c)); apagados++; } catch { /* já não existe */ }
                        gravados.delete(c);
                    }
                    pend = [];
                    this.aviso('Salvo em "' + this.disco + '": ' + n + ' gravado(s)' + (apagados ? ', ' + apagados + ' apagado(s)' : '') + '.');
                } catch {
                    this.aviso('Não consegui gravar na pasta.');
                }
            },

            /* ---------- buscar e substituir ---------- */
            abrirBusca() {
                this.busca.on = true;
                const ta = this.$refs.ta;
                if (ta && ta.selectionEnd > ta.selectionStart && ta.selectionEnd - ta.selectionStart < 80 && !ta.value.slice(ta.selectionStart, ta.selectionEnd).includes('\n'))
                    this.busca.q = ta.value.slice(ta.selectionStart, ta.selectionEnd);
                this.$nextTick(() => { const q = this.$refs.q; if (q) { q.focus(); q.select(); } });
            },
            contar() {
                const f = this.arqAtivo(), q = (this.busca.q || '').toLowerCase();
                if (!f || !q) return 0;
                return f.texto.toLowerCase().split(q).length - 1;
            },
            proxima(dirc) {
                const ta = this.$refs.ta, q = (this.busca.q || '').toLowerCase();
                if (!ta || !q) return;
                const h = ta.value.toLowerCase();
                let i = dirc > 0 ? h.indexOf(q, ta.selectionEnd) : h.lastIndexOf(q, ta.selectionStart - 1);
                if (i < 0) i = dirc > 0 ? h.indexOf(q) : h.lastIndexOf(q);
                if (i < 0) { this.aviso('Nada encontrado.'); return; }
                this.selecionar(i, i + q.length);
            },
            substituirUm() {
                const ta = this.$refs.ta, q = (this.busca.q || '').toLowerCase();
                if (!ta || !q) return;
                if (ta.value.slice(ta.selectionStart, ta.selectionEnd).toLowerCase() === q) inserir(ta, this.busca.r || '');
                this.proxima(1);
            },
            substituirTodos() {
                const f = this.arqAtivo(), q = this.busca.q || '';
                if (!f || !q) return;
                const n = this.contar();
                if (!n) { this.aviso('Nada encontrado.'); return; }
                const r = this.busca.r || '';
                f.texto = f.texto.replace(new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), () => r);
                this.aviso(n + ' ocorrência(s) substituída(s).');
                this.atualizarPrevia();
            },
            resultados() {
                const q = (this.busca.q || '').toLowerCase();
                if (!this.busca.todos || q.length < 2) return [];
                const out = [];
                for (const f of I.arquivos) {
                    const ls = f.texto.split('\n');
                    for (let i = 0; i < ls.length; i++) {
                        if (!ls[i].toLowerCase().includes(q)) continue;
                        out.push({ id: f.id, caminho: f.caminho, linha: i + 1, trecho: ls[i].trim().slice(0, 70) });
                        if (out.length >= 100) return out;
                    }
                }
                return out;
            },
            irLinha(r) {
                this.abrir(r.id);
                this.$nextTick(() => {
                    const f = this.arqAtivo();
                    if (!f) return;
                    const ls = f.texto.split('\n');
                    const ini = ls.slice(0, r.linha - 1).reduce((s, l) => s + l.length + 1, 0);
                    this.selecionar(ini, ini + (ls[r.linha - 1] || '').length);
                });
            },

            /* ---------- executar e prévia ---------- */
            htmls() { return I.arquivos.filter(x => ehHtml(x.caminho)); },
            /** Páginas HTML do projeto que carregam o arquivo f (por <script src> ou <link href>). */
            paginasQueUsam(f) {
                return this.htmls().filter(h => referenciasDe(h).has(f.caminho));
            },
            /** Qual página abrir para executar f: ele mesmo (se for HTML), uma página que o use, ou o index.html. */
            paginaPara(f) {
                if (!f) return this.htmls().find(x => nomeDe(x.caminho).toLowerCase() === 'index.html') || this.htmls()[0] || null;
                if (ehHtml(f.caminho)) return f;
                const usam = this.paginasQueUsam(f);
                if (usam.length) return usam.find(x => nomeDe(x.caminho).toLowerCase() === 'index.html') || usam[0];
                return null;
            },
            podeExecutar() {
                const f = this.arqAtivo(), e = f ? extDe(f.caminho) : '';
                return ['js', 'mjs'].includes(e) || this.htmls().length > 0;
            },
            executar() {
                const f = this.arqAtivo(), e = f ? extDe(f.caminho) : '';
                const pagina = this.paginaPara(f);
                if (pagina) return this.rodarHtml(pagina);
                // nenhum HTML usa este arquivo: JavaScript solto roda sozinho, só com o console
                if (f && (e === 'js' || e === 'mjs')) return this.rodarJs(f);
                // outros arquivos (css, md, json…): abre a página principal do projeto
                const alvo = this.htmls().find(x => nomeDe(x.caminho).toLowerCase() === 'index.html') || this.htmls()[0];
                if (alvo) return this.rodarHtml(alvo);
                this.aviso('Crie um arquivo .html ou abra um .js para executar.');
            },
            rodarJs(f) {
                const n = ++token;
                htmlId = null;
                this.saida = [];
                this.srcdoc = '<!doctype html><meta charset="utf-8">' + shim(n) + '<script>' + f.texto.replace(/<\/script/gi, '<\\/script') + '</script>';
                this.painel = 'saida';
            },
            /** Monta a página com os .css e .js do projeto embutidos e mostra na prévia. */
            montarHtml(f, n) {
                const base = pai(f.caminho), achar = c => (c ? I.arquivos.find(x => x.caminho === c) : null);
                let h = f.texto.replace(RE_LINK, (m, href) => {
                    if (!/stylesheet/i.test(m)) return m;
                    const a = achar(resolver(base, semConsulta(href)));
                    return a ? '<style>' + a.texto.replace(/<\/style/gi, '<\\/style') + '</style>' : m;
                });
                h = h.replace(RE_SCRIPT, (m, x, src, y) => {
                    const a = achar(resolver(base, semConsulta(src)));
                    return a ? '<script' + x + y + '>' + a.texto.replace(/<\/script/gi, '<\\/script') + '</script>' : m;
                });
                return /<head[^>]*>/i.test(h) ? h.replace(/<head[^>]*>/i, m => m + shim(n)) : shim(n) + h;
            },
            rodarHtml(f) {
                const n = ++token;
                htmlId = f.id;
                this.saida = [];
                this.srcdoc = this.montarHtml(f, n);
                this.painel = 'previa';
            },
            /** Com a prévia aberta, qualquer modificação no projeto atualiza a página sozinha. */
            atualizarPrevia() {
                if (this.painel !== 'previa' || !htmlId) return;
                clearTimeout(timerPrev);
                timerPrev = setTimeout(() => {
                    const f = I.arquivos.find(x => x.id === htmlId);
                    if (!f) { htmlId = null; return; }
                    if (this.painel !== 'previa') return;
                    this.rodarHtml(f);
                }, 500);
            },
            fecharPainel() { this.painel = null; clearTimeout(timerPrev); },
            limparSaida() { this.saida = []; },
        };
    }

    WFIde.ampliar = ampliar;
    window.WFIde = WFIde;
})();