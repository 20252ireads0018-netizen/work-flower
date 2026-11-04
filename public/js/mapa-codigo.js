/* Work Flower · "Mapa do código" (cartão GitHub / VS Code do Diverso)
   Lê os arquivos do projeto da IDE e monta os dados de um cartão "Mapa mental e diagramas" (diagrama.js):
   - uma raia por pasta, com os arquivos dentro como caixas (forma "entidade");
   - em cada caixa: o que o arquivo é (do comentário do topo ou do tipo de arquivo) e as classes e funções dele;
   - setas de um arquivo para cada arquivo do projeto que ele importa (import, require, use, include, @extends…).
   Tudo é feito no navegador, por leitura do texto (sem IA e sem executar nada).
   Uso: WFMapaCodigo.gerar(arquivos, nome) → { nos, lig, resumo } (arquivos: [{ caminho, texto }]). */
(function () {
    'use strict';
    const uid = (window.WFQuadro && window.WFQuadro.util && window.WFQuadro.util.uid) || (() => Math.random().toString(36).slice(2, 10));
    const CORES = ['#2f6fc7', '#2dd4bf', '#a78bfa', '#fb923c', '#f472b6', '#f2c230', '#4ade80', '#94a3b8'];
    const MAX_ARQ = 180, MAX_ITENS = 8, MAX_LIG = 560, LARG = 250, COLS = 3, GX = 24, GY = 24, CAB_RAIA = 22, ESQ = 46; // a raia escreve o nome na vertical, à esquerda
    const BINARIO = /\.(png|jpe?g|gif|webp|ico|bmp|pdf|zip|mp[34]|wav|woff2?|ttf|otf|eot|lock)$/i;

    const ext = c => { const n = c.slice(c.lastIndexOf('/') + 1), i = n.lastIndexOf('.'); return i > 0 ? n.slice(i + 1).toLowerCase() : ''; };
    const pasta = c => (c.includes('/') ? c.slice(0, c.lastIndexOf('/')) : '');
    const nome = c => c.slice(c.lastIndexOf('/') + 1);
    const corta = (s, n) => (s.length > n ? s.slice(0, n - 1) + '…' : s);
    const unicos = a => [...new Set(a)];

    /** Texto do primeiro comentário do arquivo (docblock, //, #, """ ou <!-- -->), já limpo. */
    function comentarioDoTopo(t) {
        const s = t.replace(/^\uFEFF/, '').replace(/^#!.*\n/, '').replace(/^<\?php\s*/, '').replace(/^\s*(declare\(strict_types=1\);|namespace [^;]+;|'use strict';|"use strict";)\s*/g, '').trimStart();
        let m = /^\/\*\*?([\s\S]*?)\*\//.exec(s) || /^<!--([\s\S]*?)-->/.exec(s) || /^\{\{--([\s\S]*?)--\}\}/.exec(s) || /^("""|''')([\s\S]*?)\1/.exec(s);
        let txt = m ? (m[2] !== undefined && m[1].length === 3 ? m[2] : m[1]) : '';
        if (!txt) {
            const linhas = [];
            for (const l of s.split('\n').slice(0, 8)) {
                const c = /^\s*(\/\/|#|--)\s?(.*)$/.exec(l);
                if (!c) break;
                linhas.push(c[2]);
            }
            txt = linhas.join(' ');
        }
        // também vale o docblock logo antes da primeira classe (comum em PHP e Java)
        if (!txt) { const d = /\/\*\*([\s\S]*?)\*\/\s*(?:final\s+|abstract\s+|export\s+)?(?:class|interface|trait)\s/.exec(t); if (d) txt = d[1]; }
        return txt.split('\n').map(l => l.replace(/^\s*\*\s?/, '').trim()).filter(l => l && !l.startsWith('@')).join(' ').replace(/\s+/g, ' ').trim();
    }

    /** O que o arquivo é, quando não há comentário: pelo caminho, nome e conteúdo. */
    function papel(c, t, e) {
        const n = nome(c).toLowerCase(), p = c.toLowerCase();
        if (/(^|\/)(tests?|__tests__|spec)\//.test(p) || /\.(test|spec)\.[a-z]+$/.test(n) || /test\.php$/.test(n)) return 'Testes automáticos';
        if (/(^|\/)migrations?\//.test(p)) return 'Migração do banco de dados (cria ou altera tabelas)';
        if (/controller\.(php|js|ts|java|cs|kt)$/.test(n)) return 'Controller: recebe as requisições e devolve as respostas';
        if (/(^|\/)models?\//.test(p)) return 'Model: os dados de uma tabela e as regras deles';
        if (/(^|\/)routes?\//.test(p) || /^(web|api|routes?)\.(php|js|ts)$/.test(n)) return 'Rotas: liga cada endereço ao código que responde';
        if (/middleware/.test(p)) return 'Middleware: roda antes ou depois de cada requisição';
        if (n.endsWith('.blade.php')) return /@extends|@section/.test(t) ? 'Página (view Blade)' : 'Parte de página / layout (Blade)';
        if (/(^|\/)(config)\//.test(p)) return 'Configuração';
        if (n === 'package.json') return 'Dependências e scripts do projeto (Node)';
        if (n === 'composer.json') return 'Dependências do projeto (PHP)';
        if (n === 'readme.md') return 'Apresentação e instruções do projeto';
        if (/^\.env|\.ini$|\.ya?ml$|\.toml$/.test(n)) return 'Configuração';
        if (/^(index|main|app|server)\.(js|ts|jsx|tsx|py|go|java|php)$/.test(n)) return 'Ponto de entrada do programa';
        if (['css', 'scss', 'less'].includes(e)) return 'Estilos (aparência)';
        if (['html', 'htm'].includes(e)) return 'Página HTML';
        if (e === 'sql') return 'Banco de dados em SQL';
        if (e === 'md') return 'Documentação';
        if (e === 'json') return 'Dados ou configuração em JSON';
        if (['jsx', 'tsx', 'vue', 'svelte'].includes(e) || /return\s*\(\s*</.test(t)) return 'Componente de interface';
        return '';
    }

    /** Classes, funções e o que o arquivo importa, por linguagem. */
    function analisar(c, t) {
        const e = ext(c), itens = [], imports = [];
        const add = (tipo, n) => { if (n && itens.length < 60) itens.push({ tipo, n }); };
        const todos = (re, fn) => { for (const m of t.matchAll(re)) fn(m); };
        if (['js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx', 'vue', 'svelte'].includes(e)) {
            todos(/^\s*(?:export\s+(?:default\s+)?)?(?:abstract\s+)?class\s+([A-Za-z_$][\w$]*)/gm, m => add('classe', m[1]));
            todos(/^\s*(?:export\s+(?:default\s+)?)?(?:async\s+)?function\s*\*?\s*([A-Za-z_$][\w$]*)/gm, m => add('função', m[1]));
            todos(/^\s*(?:export\s+)?(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:async\s*)?(?:function\b|\([^)]*\)\s*=>|[A-Za-z_$][\w$]*\s*=>)/gm, m => add('função', m[1]));
            todos(/^\s*(?:export\s+)?interface\s+([A-Za-z_$][\w$]*)/gm, m => add('tipo', m[1]));
            todos(/(?:import\s[^'"]*?from\s*|import\s*\(\s*|import\s+|require\s*\(\s*)['"]([^'"]+)['"]/g, m => imports.push(m[1]));
        } else if (e === 'php') {
            todos(/^\s*(?:final\s+|abstract\s+|readonly\s+)*(class|interface|trait|enum)\s+([A-Za-z_]\w*)/gm, m => add(m[1] === 'class' ? 'classe' : m[1], m[2]));
            todos(/^\s*(?:public|protected|private|static|\s)*function\s+&?\s*([A-Za-z_]\w*)\s*\(/gm, m => add('função', m[1]));
            todos(/^\s*use\s+([A-Z][\w\\]+)(?:\s+as\s+\w+)?\s*;/gm, m => imports.push({ php: m[1] }));
            todos(/(?:require|include)(?:_once)?\s*\(?\s*(?:__DIR__\s*\.\s*)?['"]([^'"]+)['"]/g, m => imports.push(m[1].replace(/^\//, './')));
            todos(/@(?:extends|include|includeIf|component)\(\s*['"]([\w.\-/]+)['"]/g, m => imports.push({ view: m[1] }));
            todos(/\bview\(\s*['"]([\w.\-/]+)['"]/g, m => imports.push({ view: m[1] }));
            todos(/Route::(get|post|put|patch|delete|resource|view)\(/g, () => add('rota', ''));
        } else if (e === 'py') {
            todos(/^class\s+([A-Za-z_]\w*)/gm, m => add('classe', m[1]));
            todos(/^(?:async\s+)?def\s+([A-Za-z_]\w*)/gm, m => add('função', m[1]));
            todos(/^\s*from\s+([.\w]+)\s+import|^\s*import\s+([\w.]+)/gm, m => imports.push({ py: m[1] || m[2] }));
        } else if (['java', 'kt', 'cs', 'scala', 'groovy'].includes(e)) {
            todos(/^\s*(?:public\s+|private\s+|protected\s+|internal\s+|abstract\s+|final\s+|static\s+|sealed\s+|data\s+|open\s+)*(class|interface|enum|record|object)\s+([A-Za-z_]\w*)/gm, m => add(m[1] === 'class' ? 'classe' : m[1], m[2]));
            todos(/^\s+(?:public|private|protected|internal|override|static|final|suspend|\s)*(?:fun\s+([a-zA-Z_]\w*)|[\w<>\[\],.? ]+\s+([a-zA-Z_]\w*)\s*\([^)]*\)\s*(?:throws [\w., ]+)?\s*\{)/gm, m => { const n = m[1] || m[2]; if (!/^(if|for|while|switch|catch|return|new)$/.test(n)) add('função', n); });
            todos(/^\s*(?:import|using)\s+(?:static\s+)?([\w.]+)\s*;?/gm, m => imports.push({ java: m[1] }));
        } else if (e === 'go') {
            todos(/^type\s+([A-Za-z_]\w*)\s+(struct|interface)/gm, m => add(m[2] === 'struct' ? 'tipo' : 'interface', m[1]));
            todos(/^func\s+(?:\([^)]*\)\s*)?([A-Za-z_]\w*)/gm, m => add('função', m[1]));
        } else if (['c', 'h', 'cpp', 'hpp', 'cc'].includes(e)) {
            todos(/^\s*(?:class|struct)\s+([A-Za-z_]\w*)\s*[{:]/gm, m => add('classe', m[1]));
            todos(/^[\w\s*&:<>]+?\b([A-Za-z_]\w*)\s*\([^;{)]*\)\s*\{/gm, m => { if (!/^(if|for|while|switch)$/.test(m[1])) add('função', m[1]); });
            todos(/#include\s*"([^"]+)"/g, m => imports.push('./' + m[1]));
        } else if (e === 'sql') {
            todos(/create\s+table\s+(?:if\s+not\s+exists\s+)?[`"[]?([\w.]+)/gi, m => add('tabela', m[1].split('.').pop()));
        } else if (['html', 'htm'].includes(e)) {
            const tt = /<title>([^<]*)<\/title>/i.exec(t);
            if (tt) add('título', tt[1].trim());
            todos(/<(?:script[^>]+src|link[^>]+href)\s*=\s*["']([^"']+)["']/gi, m => { if (!/^(https?:)?\/\//.test(m[1])) imports.push(m[1].startsWith('/') ? m[1].slice(1) : './' + m[1].replace(/^\.\//, '')); });
        } else if (['css', 'scss', 'less'].includes(e)) {
            const n = (t.match(/[^{}]+\{/g) || []).length;
            if (n) add('regras', String(n));
            todos(/@import\s+(?:url\()?['"]([^'"]+)['"]/g, m => imports.push('./' + m[1].replace(/^\.\//, '')));
        }
        return { itens, imports };
    }

    /** Liga cada import a um arquivo do projeto (quando existir). */
    function resolver(origem, imp, idx) {
        const tentar = alvo => {
            const base = alvo.replace(/\/+$/, '');
            for (const s of ['', '.js', '.ts', '.jsx', '.tsx', '.mjs', '.vue', '.php', '.py', '.css', '.scss', '/index.js', '/index.ts', '/index.jsx', '/index.tsx', '/__init__.py']) {
                const c = idx.porCaminho.get((base + s).toLowerCase());
                if (c) return c;
            }
            return null;
        };
        if (typeof imp === 'string') {
            if (imp.startsWith('.')) {
                const partes = pasta(origem) ? pasta(origem).split('/') : [];
                for (const p of imp.split('/')) { if (p === '..') partes.pop(); else if (p !== '.' && p) partes.push(p); }
                return tentar(partes.join('/'));
            }
            if (imp.startsWith('@/') || imp.startsWith('~/')) return tentar('src/' + imp.slice(2)) || tentar(imp.slice(2));
            return tentar(imp);
        }
        if (imp.php) { // App\Models\User → .../Models/User.php
            const fim = imp.php.split('\\').slice(1).join('/').toLowerCase() + '.php';
            return idx.lista.find(c => c.toLowerCase().endsWith('/' + fim) || c.toLowerCase() === fim || c.toLowerCase().endsWith(fim.replace(/^[^/]*\//, '/'))) || null;
        }
        if (imp.view) { // layouts.app → resources/views/layouts/app.blade.php
            const fim = imp.view.replace(/\./g, '/').toLowerCase() + '.blade.php';
            return idx.lista.find(c => c.toLowerCase().endsWith(fim)) || null;
        }
        if (imp.py) {
            const rel = imp.py.startsWith('.');
            const mod = imp.py.replace(/^\.+/, '').replace(/\./g, '/');
            return (rel ? tentar(pasta(origem) + '/' + mod) : null) || tentar(mod) || idx.lista.find(c => c.toLowerCase().endsWith('/' + mod.toLowerCase() + '.py')) || null;
        }
        if (imp.java) {
            const fim = imp.java.replace(/\./g, '/').toLowerCase();
            return idx.lista.find(c => /\.(java|kt|cs|scala)$/.test(c) && c.toLowerCase().replace(/\.(java|kt|cs|scala)$/, '').endsWith(fim)) || null;
        }
        return null;
    }

    /** Até duas linhas de ~34 letras para a explicação. */
    function quebrar(txt, n = 34, linhas = 2) {
        const out = [];
        let atual = '';
        for (const p of txt.split(' ')) {
            if ((atual + ' ' + p).trim().length > n) { out.push(atual.trim()); atual = p; if (out.length === linhas) break; }
            else atual += ' ' + p;
        }
        if (out.length < linhas && atual.trim()) out.push(atual.trim());
        if (out.length === linhas && txt.length > out.join(' ').length + 1) out[linhas - 1] = corta(out[linhas - 1], n - 1) + '…';
        return out;
    }

    function gerar(arquivos, titulo) {
        const lista = (arquivos || []).filter(f => f && f.caminho && typeof f.texto === 'string' && !BINARIO.test(f.caminho))
            .sort((a, b) => a.caminho.localeCompare(b.caminho)).slice(0, MAX_ARQ);
        const idx = { lista: lista.map(f => f.caminho), porCaminho: new Map(lista.map(f => [f.caminho.toLowerCase(), f.caminho])) };
        const info = lista.map(f => {
            const e = ext(f.caminho), a = analisar(f.caminho, f.texto);
            const explic = comentarioDoTopo(f.texto) || papel(f.caminho, f.texto, e) || (a.itens.length ? '' : 'Arquivo ' + (e ? '.' + e : 'sem extensão'));
            const rotas = a.itens.filter(i => i.tipo === 'rota').length;
            const itens = a.itens.filter(i => i.tipo !== 'rota');
            const linhas = [...quebrar(explic)];
            if (rotas) linhas.push(rotas + (rotas === 1 ? ' rota' : ' rotas'));
            const classes = itens.filter(i => i.tipo !== 'função'), funcs = itens.filter(i => i.tipo === 'função');
            const cs = classes.slice(0, 3);
            cs.forEach(i => linhas.push('◆ ' + corta(i.tipo + ' ' + i.n, 30)));
            const fs = funcs.slice(0, Math.max(0, MAX_ITENS - linhas.length));
            fs.forEach(i => linhas.push('· ' + corta(i.n + '()', 30)));
            const sobra = classes.length - cs.length + funcs.length - fs.length;
            if (sobra > 0) linhas.push('+ ' + sobra + (sobra === 1 ? ' item' : ' itens'));
            const deps = unicos(a.imports.map(i => resolver(f.caminho, i, idx)).filter(c => c && c !== f.caminho));
            return { caminho: f.caminho, linhas: linhas.filter(Boolean), deps, nLinhas: f.texto.split('\n').length };
        });

        // uma raia por pasta, arquivos em grade (3 por linha) dentro dela
        const porPasta = new Map();
        info.forEach(i => { const p = pasta(i.caminho) || '(raiz)'; if (!porPasta.has(p)) porPasta.set(p, []); porPasta.get(p).push(i); });
        const nos = [], idDe = new Map();
        let y = 0, cor = 0;
        for (const [p, arqs] of porPasta) {
            const c = CORES[cor++ % CORES.length];
            const alturas = arqs.map(a => 34 + Math.max(1, a.linhas.length) * 20 + 8);
            const cols = Math.min(COLS, arqs.length), linhasGrade = Math.ceil(arqs.length / cols);
            const altLinha = [...Array(linhasGrade)].map((_, r) => Math.max(...alturas.slice(r * cols, r * cols + cols)));
            const w = ESQ + cols * LARG + cols * GX, h = CAB_RAIA + altLinha.reduce((s, v) => s + v + GY, 0);
            nos.push({ id: uid(), t: p === '(raiz)' ? (titulo || 'Projeto') : p, x: 0, y, pai: null, cor: c, forma: 'raia', w, h });
            let yy = y + CAB_RAIA;
            arqs.forEach((a, k) => {
                const col = k % cols, lin = Math.floor(k / cols);
                if (col === 0 && k) yy += altLinha[lin - 1] + GY;
                const id = uid();
                idDe.set(a.caminho, id);
                nos.push({ id, t: nome(a.caminho), x: ESQ + col * (LARG + GX), y: yy, pai: null, cor: c, forma: 'entidade', w: LARG, campos: a.linhas.join('\n') || 'arquivo' });
            });
            y += h + 40;
        }
        const lig = [];
        info.forEach(a => a.deps.forEach(d => {
            if (lig.length < MAX_LIG && idDe.has(d)) lig.push({ id: uid(), de: idDe.get(a.caminho), para: idDe.get(d), rot: '', estilo: 'curva', traco: 'tracejado', ini: 'nenhum', fim: 'seta', cor: '', w: 1.4 });
        }));
        const fora = (arquivos || []).length - lista.length;
        return {
            nos, lig,
            resumo: `${lista.length} arquivos em ${porPasta.size} ${porPasta.size === 1 ? 'pasta' : 'pastas'} · ${lig.length} ${lig.length === 1 ? 'ligação' : 'ligações'}` + (fora > 0 ? ` · ${fora} arquivos ficaram de fora (imagens ou passou de ${MAX_ARQ})` : ''),
        };
    }

    window.WFMapaCodigo = { gerar, analisar, comentarioDoTopo };
})();
