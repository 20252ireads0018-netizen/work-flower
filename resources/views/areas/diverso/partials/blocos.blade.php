@include('areas.diverso.partials.escrita-js')
@verbatim
<script>
/* Work Flower · mini-IDE do bloco "GitHub / VS Code"
   Uso: <div x-data="WFIde(b)"> dentro do bloco do tipo "codigo".
   O projeto fica em b.dados.ide = { arquivos: [{id, caminho, texto}], pastas: [], abertas: [], ativo, layout: {lado, painel, barra} }.
   Não depende de nada do diverso.js.
   Atalhos: Ctrl+S salvar · Ctrl+P abrir arquivo · Ctrl+G ir para linha · Ctrl+F buscar · Ctrl+/ comentar · Alt+↑/↓ mover linha
   · Alt+Shift+↑/↓ duplicar linha · Ctrl+Shift+K excluir linha · Ctrl+Shift+F formatar JSON · Ctrl+Enter executar.
   Botão direito na lista de arquivos: ▶ Executar (.html, JS/TS/TSX/JSX estilo Node, .py, .php, .sql, .rb, .lua e views
   .blade.php no próprio navegador; Java/C/Go/etc. no servidor Judge0), renomear, excluir, copiar caminho…
   Projeto com routes/web.php = app Laravel: ▶ Executar abre o app na prévia (links e formulários navegam), com Artisan e
   a aba Banco. Motores e dicas de erro: public/js/ide-motores.js (WFMotores, WFDicas); o mini-Laravel em
   public/js/ide-mini-laravel.txt. */
(function () {
    'use strict';

    const U = (window.WFQuadro && window.WFQuadro.util) || {};
    const uid = U.uid || (() => Math.random().toString(36).slice(2, 10));

    const MAX_ARQ = 300, MAX_BYTES = 400000, MAX_TOTAL = 3000000, MAX_SAIDA = 500;
    const LH = 19.5; // altura da linha (13px * 1.5), igual ao CSS
    const IGNORAR = new Set(['node_modules', '.git', 'vendor', 'dist', 'build', '.next', '__pycache__', '.idea', '.venv', 'venv', 'storage', '.cache', 'target', '.gradle', 'obj']);
    const BINARIO = /\.(png|jpe?g|gif|webp|ico|bmp|pdf|zip|rar|7z|gz|tar|mp[34]|wav|ogg|webm|mov|woff2?|ttf|otf|eot|exe|dll|so|bin|psd|sqlite|db|class|jar|war|pyc|apk|dex)$/i;
    const limitar = (v, a, b) => Math.max(a, Math.min(b, v));
    /** Cópia simples (sem os Proxies do Alpine), para mandar por postMessage ao motor. */
    const copia = v => JSON.parse(JSON.stringify(v ?? {}));

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

    /* ---------- linguagens ---------- */
    const GRUPO = {
        js: 'js', mjs: 'js', cjs: 'js', jsx: 'js', ts: 'js', tsx: 'js', c: 'js', h: 'js', cpp: 'js', go: 'js', rs: 'js', swift: 'js', dart: 'js',
        java: 'java', kt: 'java', kts: 'java', scala: 'java', groovy: 'java', gradle: 'java', cs: 'java',
        php: 'php', blade: 'blade', sql: 'sql', lua: 'lua',
        py: 'hash', sh: 'hash', rb: 'hash', yml: 'hash', yaml: 'hash', toml: 'hash', env: 'hash', ini: 'hash', properties: 'hash', conf: 'hash',
        css: 'css', scss: 'css', less: 'css',
        html: 'html', htm: 'html', xml: 'html', svg: 'html', vue: 'html',
        json: 'json', md: 'md',
    };
    const NOME = { blade: 'Blade', php: 'PHP', java: 'Java', kt: 'Kotlin', kts: 'Kotlin', scala: 'Scala', groovy: 'Groovy', gradle: 'Gradle', cs: 'C#', go: 'Go', rs: 'Rust', c: 'C', h: 'C', cpp: 'C++', py: 'Python', rb: 'Ruby', lua: 'Lua', sh: 'Shell', sql: 'SQL', js: 'JavaScript', mjs: 'JavaScript', cjs: 'JavaScript', ts: 'TypeScript', tsx: 'TSX', jsx: 'JSX', html: 'HTML', css: 'CSS', json: 'JSON', md: 'Markdown', yml: 'YAML', yaml: 'YAML', xml: 'XML', vue: 'Vue' };
    const TIPO = { blade: 'BL', php: 'PHP', java: 'JV', kt: 'KT', cs: 'C#', go: 'GO', rs: 'RS', py: 'PY', rb: 'RB', lua: 'LUA', sql: 'SQL', js: 'JS', ts: 'TS', tsx: 'TSX', jsx: 'JSX', html: '<>', css: '#', json: '{}', md: 'MD' };
    // o que realmente não dá para executar isolado (.h é só cabeçalho; kts/gradle são scripts de build)
    const NAO_RODA = new Set(['h', 'kts', 'gradle']);
    // rodam no próprio navegador (public/js/ide-motores.js); JS e TypeScript rodam na prévia, com require/import entre arquivos
    const NAVEGADOR = { js: 'JavaScript', mjs: 'JavaScript', cjs: 'JavaScript', ts: 'TypeScript', tsx: 'TSX', jsx: 'JSX', py: 'Python', php: 'PHP', blade: 'Blade', sql: 'SQL', rb: 'Ruby', lua: 'Lua' };
    const JS_NODE = ['js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx'];
    // linguagens executadas no servidor Judge0 (o navegador não roda Java, C, Go…)
    const JUDGE0 = 'https://ce.judge0.com'; // troque aqui se hospedar o seu próprio Judge0
    const REMOTO = {
        java: { id: 62, nome: 'Java' }, kt: { id: 78, nome: 'Kotlin' }, cs: { id: 51, nome: 'C#' },
        go: { id: 60, nome: 'Go' }, rs: { id: 73, nome: 'Rust' }, c: { id: 50, nome: 'C' }, cpp: { id: 54, nome: 'C++' },
        sh: { id: 46, nome: 'Bash' }, scala: { id: 81, nome: 'Scala' }, groovy: { id: 88, nome: 'Groovy' },
    };
    const b64 = s => { const u = new TextEncoder().encode(s); let r = ''; for (let i = 0; i < u.length; i += 8192) r += String.fromCharCode.apply(null, u.subarray(i, i + 8192)); return btoa(r); };
    const deb64 = s => (s ? new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0))) : '');
    /** "pagina.blade.php" é Blade, não PHP. */
    const lingua = c => (/\.blade\.php$/i.test(c) ? 'blade' : extDe(c));
    const grupoDe = c => GRUPO[lingua(c)];

    /* ---------- realce de sintaxe ---------- */
    const mk = (c, s, n, k, t, f) => new RegExp([c, s, n, k, t].map(x => '(' + (x || '(?!)') + ')').join('|'), f || 'g');
    const S_DUPLO = /"(?:\\.|[^"\\\n])*"|'(?:\\.|[^'\\\n])*'/.source;
    const S_CRASE = /`(?:\\.|[^`\\])*`/.source;
    const NUM = /\b\d+(?:\.\d+)?\b/.source;
    const KW = /\b(?:abstract|and|as|async|await|break|case|catch|class|const|continue|def|default|del|do|echo|elif|else|enum|except|export|extends|false|False|finally|fn|for|foreach|from|function|if|implements|import|in|instanceof|interface|is|lambda|let|match|namespace|new|None|not|null|of|or|pass|print|private|protected|public|raise|return|self|static|super|switch|this|throw|true|True|try|typeof|undefined|use|var|void|while|with|yield)\b/.source;
    const KW_JAVA = /\b(?:abstract|assert|boolean|break|byte|case|catch|char|class|const|continue|default|do|double|else|enum|extends|false|final|finally|float|for|if|implements|import|instanceof|int|interface|long|native|new|null|package|private|protected|public|return|short|static|super|switch|synchronized|this|throw|throws|transient|true|try|var|void|volatile|while|record|sealed|permits|fun|val|when|override|namespace|using|String|async|await|readonly|internal|partial)\b/.source;
    const KW_PHP = /\b(?:abstract|and|array|as|break|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enum|extends|false|final|finally|fn|for|foreach|function|global|if|implements|include|include_once|instanceof|interface|isset|list|match|namespace|new|null|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|true|try|unset|use|var|while|yield|self|parent)\b/.source;
    const KW_SQL = /\b(?:select|from|where|insert|into|values|update|set|delete|create|table|alter|drop|join|left|right|inner|outer|on|group|by|order|having|limit|offset|as|and|or|not|null|is|in|like|between|distinct|union|primary|key|foreign|references|index|default|unique|int|varchar|text|date|timestamp|boolean|count|sum|avg|min|max|case|when|then|else|end|exists|view|database|constraint|cascade)\b/.source;
    const COM_C = /\/\/[^\n]*|\/\*[\s\S]*?\*\//.source;
    const REGEX = {
        js: mk(COM_C, S_DUPLO + '|' + S_CRASE, NUM, KW, null),
        hash: mk(/#[^\n]*/.source, S_DUPLO, NUM, KW, null),
        css: mk(/\/\*[\s\S]*?\*\//.source, S_DUPLO, /#[0-9a-fA-F]{3,8}\b|\b\d+(?:\.\d+)?(?:px|rem|em|%|vh|vw|ms|s|deg)?\b/.source, /[a-z-]+(?=\s*:)/.source, null),
        html: mk(/<\!--[\s\S]*?-->/.source, null, null, null, /<\/?[A-Za-z][^>]*>/.source),
        json: mk(null, /"(?:\\.|[^"\\\n])*"/.source, /-?\b\d+(?:\.\d+)?(?:[eE][+-]?\d+)?\b/.source, /\b(?:true|false|null)\b/.source, null),
        md: mk(null, /`[^`\n]+`/.source, null, null, /^#{1,6} [^\n]*/.source, 'gm'),
        // Java, Kotlin, C#, Scala, Groovy, Gradle (anotações @Algo em destaque)
        java: mk(COM_C, S_DUPLO, NUM, KW_JAVA, /@[A-Za-z_]\w*/.source),
        // PHP puro (variáveis $x e as tags de abertura/fechamento do PHP em destaque)
        php: mk(/\/\/[^\n]*|#[^\n]*|\/\*[\s\S]*?\*\//.source, S_DUPLO, NUM, KW_PHP, /<\?php|<\?=|\?>|\$[A-Za-z_]\w*/.source, 'gi'),
        sql: mk(/--[^\n]*|\/\*[\s\S]*?\*\//.source, S_DUPLO + '|' + S_CRASE, NUM, KW_SQL, null, 'gi'),
        lua: mk(/--\[\[[\s\S]*?\]\]|--[^\n]*/.source, S_DUPLO + '|' + /\[\[[\s\S]*?\]\]/.source, NUM, /\b(?:and|break|do|else|elseif|end|false|for|function|goto|if|in|local|nil|not|or|repeat|return|then|true|until|while)\b/.source, null),
        // Blade: comentários {{-- --}}, ecos {{ }} / {!! !!}, diretivas @if/@foreach… e tags HTML / <x-componentes>
        blade: mk(
            /\{\{--[\s\S]*?--\}\}|<\!--[\s\S]*?-->/.source,
            null,
            /\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\}/.source,
            /(?<![\w.@])@[A-Za-z]\w*/.source,
            /<\/?[A-Za-z][^>]*>/.source
        ),
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
        + 'window.addEventListener("unhandledrejection",function(e){p("error",String(e.reason))})})();<\/script>';

    /* Prévia de um app Laravel: links e formulários da página viram requisições ao app (postMessage para a IDE), e
       fetch() para o próprio app ("/api/…") também; endereços de fora (https://…) seguem normais. */
    const navLaravel = (n, atual) => '<script>(function(){var T=' + n + ',A=' + JSON.stringify('http://localhost' + atual) + ';'
        + 'function p(o){o.wfIde=T;parent.postMessage(o,"*")}'
        + 'function local(h){try{var u=new URL(h,A);return u.origin==="http://localhost"?u:null}catch(e){return null}}'
        + 'document.addEventListener("click",function(e){if(e.defaultPrevented||e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey)return;var a=e.target.closest&&e.target.closest("a[href]");if(!a)return;var h=a.getAttribute("href");if(!h||h.charAt(0)==="#"||/^(javascript|mailto|tel):/i.test(h))return;var u=local(h);if(!u)return;e.preventDefault();var b=new URL(A);if(u.pathname===b.pathname&&u.search===b.search&&u.hash){location.hash=u.hash;return}p({t:"nav",metodo:"GET",url:u.pathname+u.search})});'
        + 'document.addEventListener("submit",function(e){var f=e.target;if(e.defaultPrevented)return;var u=local(f.getAttribute("action")||A);if(!u)return;e.preventDefault();var fd=new FormData(f,e.submitter||undefined),q=new URLSearchParams();fd.forEach(function(v,k){q.append(k,typeof v==="string"?v:(v&&v.name)||"")});var m=(f.getAttribute("method")||"GET").toUpperCase();if(m==="GET"){u.search=q.toString();p({t:"nav",metodo:"GET",url:u.pathname+u.search})}else p({t:"nav",metodo:m,url:u.pathname+u.search,corpo:q.toString()})});'
        + 'var F=window.fetch,S=0,E={};window.addEventListener("message",function(ev){var d=ev.data;if(!d||d.wfIdeResp!==T||!E[d.id])return;var x=E[d.id];delete E[d.id];x(new Response(d.corpo,{status:d.status,headers:{"Content-Type":d.tipo||"text/plain"}}))});'
        + 'window.fetch=function(r,o){var url=typeof r==="string"?r:(r&&r.url)||String(r);var u=local(url);if(!u)return F.apply(this,arguments);o=o||{};var id=++S,c=o.body;if(c instanceof FormData||c instanceof URLSearchParams){var q=new URLSearchParams();c.forEach(function(v,k){q.append(k,v)});c=q.toString()}var h={};if(o.headers){(o.headers instanceof Headers?o.headers:new Headers(o.headers)).forEach(function(v,k){h[k]=v})}return new Promise(function(ok){E[id]=ok;p({t:"fetch",id:id,metodo:(o.method||"GET").toUpperCase(),url:u.pathname+u.search,corpo:typeof c==="string"?c:"",cabecalhos:h})})};'
        + '})();<\/script>';

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
    /** Apaga a seleção atual mantendo o histórico de desfazer (Ctrl+Z). */
    function apagarSel(ta) {
        ta.focus();
        if (!document.execCommand('delete')) {
            ta.setRangeText('', ta.selectionStart, ta.selectionEnd, 'end');
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /* ---------- edição de linhas ---------- */
    const rx = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const inicioLinha = (v, i) => (i <= 0 ? 0 : v.lastIndexOf('\n', i - 1) + 1);
    /** Início e fim (sem o \n) das linhas tocadas pela seleção. */
    function linhasSel(v, ini, fim) {
        const a = inicioLinha(v, ini);
        let z = fim > ini && v[fim - 1] === '\n' ? fim - 1 : v.indexOf('\n', fim);
        if (z < 0) z = v.length;
        return [a, z];
    }
    function moverLinhas(ta, dir) {
        const v = ta.value, ini = ta.selectionStart, fim = ta.selectionEnd, [a, z] = linhasSel(v, ini, fim);
        const bloco = v.slice(a, z);
        if (dir < 0) {
            if (a === 0) return;
            const pa = inicioLinha(v, a - 1), prev = v.slice(pa, a - 1);
            ta.setSelectionRange(pa, z);
            inserir(ta, bloco + '\n' + prev);
            ta.setSelectionRange(pa + (ini - a), pa + (fim - a));
        } else {
            if (z >= v.length) return;
            let nz = v.indexOf('\n', z + 1);
            if (nz < 0) nz = v.length;
            const next = v.slice(z + 1, nz), d = next.length + 1;
            ta.setSelectionRange(a, nz);
            inserir(ta, next + '\n' + bloco);
            ta.setSelectionRange(ini + d, fim + d);
        }
    }
    function duplicarLinhas(ta, dir) {
        const v = ta.value, ini = ta.selectionStart, fim = ta.selectionEnd, [a, z] = linhasSel(v, ini, fim);
        const bloco = v.slice(a, z), d = bloco.length + 1;
        ta.setSelectionRange(z, z);
        inserir(ta, '\n' + bloco);
        if (dir > 0) ta.setSelectionRange(ini + d, fim + d); else ta.setSelectionRange(ini, fim);
    }
    function excluirLinhas(ta) {
        const v = ta.value, [a, z] = linhasSel(v, ta.selectionStart, ta.selectionEnd);
        let de = a, ate = z + 1;
        if (z >= v.length) { ate = z; if (a > 0) de = a - 1; }
        ta.setSelectionRange(de, ate);
        apagarSel(ta);
    }
    /** Troca o texto inteiro mantendo o desfazer e a posição de rolagem. */
    function substituirTudo(ta, novo) {
        const rolagem = ta.scrollTop, pos = ta.selectionStart;
        ta.setSelectionRange(0, ta.value.length);
        inserir(ta, novo);
        const p = Math.min(pos, novo.length);
        ta.setSelectionRange(p, p);
        ta.scrollTop = rolagem;
    }

    /* ---------- comentar / descomentar (Ctrl+/) ---------- */
    const COMENT = { js: '//', java: '//', php: '//', hash: '#', sql: '--', lua: '--', css: ['/*', '*/'], html: ['<\!--', '-->'], md: ['<\!--', '-->'], blade: ['{{--', '--}}'] };
    function comentar(ta, grupo) {
        const m = COMENT[grupo];
        if (!m) return false;
        const [p, s] = Array.isArray(m) ? m : [m, ''];
        const v = ta.value, ini = ta.selectionStart, fim = ta.selectionEnd, [a, z] = linhasSel(v, ini, fim);
        const ls = v.slice(a, z).split('\n'), uteis = ls.filter(l => l.trim());
        const todas = uteis.length > 0 && uteis.every(l => { const t = l.trim(); return t.startsWith(p) && (!s || t.endsWith(s)); });
        const novo = ls.map(l => {
            if (!l.trim()) return l;
            if (todas) {
                let r = l.replace(new RegExp('^(\\s*)' + rx(p) + ' ?'), '$1');
                if (s) r = r.replace(new RegExp(' ?' + rx(s) + '(\\s*)$'), '$1');
                return r;
            }
            const ind = l.match(/^\s*/)[0];
            return ind + p + ' ' + l.slice(ind.length) + (s ? ' ' + s : '');
        }).join('\n');
        ta.setSelectionRange(a, z);
        inserir(ta, novo);
        ta.setSelectionRange(a, a + novo.length);
        return true;
    }

    /* ---------- fechamento automático de ( [ { " ' ` ---------- */
    const PARES = { '(': ')', '[': ']', '{': '}', '"': '"', "'": "'", '`': '`' };
    const FECHAM = ')]}"\'`';

    /** Pontua um caminho para a busca "Abrir arquivo" (letras em ordem; -1 = não combina). */
    function pontuar(c, q) {
        const nome = c.lastIndexOf('/') + 1;
        let i = 0, s = 0, ult = -2;
        for (let k = 0; k < c.length && i < q.length; k++) {
            if (c[k] !== q[i]) continue;
            s += (k === ult + 1 ? 5 : 1) + (k >= nome ? 2 : 0) + (k === 0 || '/._-'.includes(c[k - 1]) ? 3 : 0);
            ult = k; i++;
        }
        return i === q.length ? s - c.length * 0.05 : -1;
    }

    /* ---------- projeto inicial e modelos ---------- */
    function projetoInicial() {
        const f = (caminho, texto) => ({ id: uid(), caminho, texto });
        return [
            f('README.md', '# Meu projeto\n\nEdite os arquivos ao lado.\n\n- Ctrl+S: salvar\n- Ctrl+Enter: executar (.js, .py, Java, PHP, C…) ou abrir a prévia (.html)\n- Botão direito num arquivo: ▶ Executar só ele\n- Ctrl+F: buscar e substituir\n'),
            f('index.html', '<!doctype html>\n<html lang="pt-BR">\n<head><meta charset="utf-8">\n  \n  <title>Meu projeto</title>\n  <link rel="stylesheet" href="style.css">\n</head>\n<body>\n  <h1>Olá, Work Flower!</h1>\n  <button id="b">Clique aqui</button>\n  <script src="script.js"></' + 'script>\n</body>\n</html>\n'),
            f('style.css', 'body {\n  font-family: system-ui, sans-serif;\n  padding: 2rem;\n}\n\nbutton {\n  padding: .5rem 1rem;\n  border-radius: .5rem;\n}\n'),
            f('script.js', "const botao = document.getElementById('b');\nlet n = 0;\n\nbotao.addEventListener('click', () => {\n  n++;\n  botao.textContent = 'Cliques: ' + n;\n  console.log('clique', n);\n});\n\nconsole.log('Olá do script.js!');\n"),
        ];
    }

    /** Conteúdo inicial de um arquivo novo, conforme a linguagem. */
    function modeloDe(c) {
        const l = lingua(c), base = nomeDe(c).split('.')[0].replace(/[^A-Za-z0-9_]/g, '') || 'Main';
        const Cap = base.charAt(0).toUpperCase() + base.slice(1);
        switch (l) {
            case 'blade': return "@extends('layouts.app')\n\n@section('conteudo')\n    \n@endsection\n";
            case 'php': return '<' + '?php\n\n'; // partido em dois para o PHP/Blade não confundir com a tag de abertura quando o código vai embutido numa view
            case 'java': return 'public class ' + Cap + ' {\n    public static void main(String[] args) {\n        System.out.println("Olá!");\n    }\n}\n';
            case 'kt': return 'fun main() {\n    println("Olá!")\n}\n';
            case 'cs': return 'using System;\n\nclass ' + Cap + '\n{\n    static void Main()\n    {\n        Console.WriteLine("Olá!");\n    }\n}\n';
            case 'py': return 'def main():\n    print("Olá!")\n\n\nif __name__ == "__main__":\n    main()\n';
            case 'go': return 'package main\n\nimport "fmt"\n\nfunc main() {\n    fmt.Println("Olá!")\n}\n';
            case 'rb': return 'def saudacao(nome)\n  "Olá, #{nome}!"\nend\n\nputs saudacao("mundo")\n';
            case 'lua': return 'local function saudacao(nome)\n  return "Olá, " .. nome .. "!"\nend\n\nprint(saudacao("mundo"))\n';
            case 'ts': return 'function saudacao(nome: string): string {\n  return `Olá, ${nome}!`;\n}\n\nconsole.log(saudacao("mundo"));\n';
            case 'tsx': case 'jsx': return 'const Ola = ({ nome }' + (l === 'tsx' ? ': { nome: string }' : '') + ') => <h1>Olá, {nome}!</h1>;\n\nconsole.log(Ola({ nome: "mundo" }));\n';
            case 'sql': return '-- ' + nomeDe(c) + '\n';
            case 'html': case 'htm': return '<!doctype html>\n<html lang="pt-BR">\n<head>\n  \n  <title>' + Cap + '</title>\n</head>\n<body>\n  \n</body>\n</html>\n';
            default: return '';
        }
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
        // tamanhos dos painéis (px) e visibilidade da barra de opções
        I.layout = Object.assign({ lado: 208, painel: 176, barra: true, auto: false }, I.layout || {});
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
        let filaLv = Promise.resolve();   // requisições ao Laravel do navegador, uma de cada vez
        let htmlId = null, timerPrev = 0; // página aberta na prévia (para atualizar sozinha ao editar)
        let timerAuto = 0;                // auto-salvamento na pasta do computador
        const cacheR = { k: '', t: null, v: '' }, cacheN = { n: -1, v: '' }; // realce e numeração só recalculam quando mudam

        return {
            sel: '', fechadas: [], ln: 1, col: 1, selN: 0, msg: '', ver: 0,
            saida: [], srcdoc: '', painel: null, disco: '', movendo: false, menuCtx: null,
            // motores já usados nesta página (a primeira execução de cada um avisa que vai baixar) e a aba Banco (SQL)
            motorUsado: {}, banco: { tabelas: [], sel: null, dados: null },
            // app Laravel aberto na prévia: endereço atual, histórico (voltar), sessão (flash, old(), $errors, login) e a resposta
            lv: { ativo: false, url: '/', campo: '/', hist: [], sessao: {}, carregando: false, status: 0, raiz: '' },
            linguaJs: 'js',
            busca: { on: false, q: '', r: '', todos: false, caso: false, regex: false },
            pal: { on: false, modo: 'arq', q: '', i: 0 },
            suporteDisco: typeof window.showDirectoryPicker === 'function',
            // layout (dados reativos próprios; "painel" acima é o console/prévia aberto, por isso os nomes diferentes)
            largLado: I.layout.lado, altPainel: I.layout.painel, barra: I.layout.barra, auto: !!I.layout.auto,

            iniciar() {
                ouvinte = ev => {
                    const d = ev.data;
                    if (!d || d.wfIde !== token || ev.source !== (this.$refs.frame && this.$refs.frame.contentWindow)) return;
                    // app Laravel na prévia: clique em link / envio de formulário vira a próxima requisição; fetch() vira uma requisição à parte
                    if (d.t === 'nav') { this.rodarLaravel({ metodo: d.metodo, url: d.url, corpo: d.corpo || '' }); return; }
                    if (d.t === 'fetch') { this.fetchLaravel(d, ev.source); return; }
                    this.logar(d.t, d.m, this.lv.ativo ? 'laravel' : (this.linguaJs || 'js'));
                };
                window.addEventListener('message', ouvinte);
                this.$nextTick(() => { this.sincronizar(); this.cursor(); });
            },
            destroy() {
                if (ouvinte) window.removeEventListener('message', ouvinte);
                clearTimeout(timer); clearTimeout(timerPrev); clearTimeout(timerAuto);
            },

            aviso(m) {
                this.msg = m;
                clearTimeout(timer);
                timer = setTimeout(() => { this.msg = ''; }, 4500);
            },

            /* ---------- tamanho dos painéis ---------- */
            gravarLayout() {
                Object.assign(I.layout, { lado: this.largLado, painel: this.altPainel, barra: this.barra, auto: this.auto });
            },
            alternarBarra() { this.barra = !this.barra; this.gravarLayout(); },
            alternarAuto() {
                this.auto = !this.auto;
                this.gravarLayout();
                this.aviso(this.auto ? 'Salvamento automático na pasta ligado.' : 'Salvamento automático na pasta desligado.');
                if (this.auto) this.agendarAuto();
            },
            restaurar(tipo) {
                if (tipo === 'lado') this.largLado = 208; else this.altPainel = 176;
                this.gravarLayout();
            },
            arrastar(tipo, e) {
                if (e.button != null && e.button !== 0) return;
                e.preventDefault(); e.stopPropagation();
                const alvo = e.currentTarget, x0 = e.clientX, y0 = e.clientY;
                const v0 = tipo === 'lado' ? this.largLado : this.altPainel, raiz = this.$root;
                const max = tipo === 'lado'
                    ? Math.max(160, (raiz.clientWidth || 800) - 220)
                    : Math.max(120, (raiz.clientHeight || 600) - 200);
                this.movendo = true;
                try { alvo.setPointerCapture(e.pointerId); } catch { /* sem captura */ }
                const mover = ev => {
                    if (tipo === 'lado') this.largLado = limitar(Math.round(v0 + ev.clientX - x0), 120, max);
                    else this.altPainel = limitar(Math.round(v0 - (ev.clientY - y0)), 70, max);
                };
                const fim = () => {
                    this.movendo = false;
                    alvo.removeEventListener('pointermove', mover);
                    alvo.removeEventListener('pointerup', fim);
                    alvo.removeEventListener('pointercancel', fim);
                    this.gravarLayout();
                };
                alvo.addEventListener('pointermove', mover);
                alvo.addEventListener('pointerup', fim);
                alvo.addEventListener('pointercancel', fim);
            },
            teclaDivisor(tipo, e) {
                const passo = e.shiftKey ? 40 : 16, k = e.key;
                if (tipo === 'lado' && (k === 'ArrowLeft' || k === 'ArrowRight')) {
                    this.largLado = limitar(this.largLado + (k === 'ArrowRight' ? passo : -passo), 120, 700);
                } else if (tipo === 'painel' && (k === 'ArrowUp' || k === 'ArrowDown')) {
                    this.altPainel = limitar(this.altPainel + (k === 'ArrowUp' ? passo : -passo), 70, 900);
                } else return;
                e.preventDefault();
                this.gravarLayout();
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
                        out.push({ k: 'a', caminho: f.caminho, nome: nomeDe(f.caminho), nivel, id: f.id, arq: f, tipo: TIPO[lingua(f.caminho)] || '' }));
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
                // Python, PHP, SQL e Blade: o motor começa a baixar em segundo plano, para o ▶ Executar ser rápido
                if (window.WFMotores) { const l = this.doLaravel(f) ? 'laravel' : lingua(f.caminho); setTimeout(() => window.WFMotores.aquecer(l), 1200); }
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
            extensao() {
                const f = this.arqAtivo();
                if (!f) return '';
                const l = lingua(f.caminho);
                return NOME[l] || l || 'txt';
            },

            /* ---------- editor ---------- */
            realce() {
                const f = this.arqAtivo();
                if (!f) return '';
                const k = f.id + '|' + f.caminho;
                if (cacheR.k === k && cacheR.t === f.texto) return cacheR.v;
                cacheR.k = k; cacheR.t = f.texto;
                return (cacheR.v = realcar(f.texto, grupoDe(f.caminho)) + '\n');
            },
            numeros() {
                const n = this.totalLinhas();
                if (!n) return '';
                if (cacheN.n === n) return cacheN.v;
                cacheN.n = n;
                return (cacheN.v = Array.from({ length: n }, (_, i) => i + 1).join('\n'));
            },
            totalLinhas() {
                const f = this.arqAtivo();
                if (!f) return 0;
                let n = 1, i = -1;
                while ((i = f.texto.indexOf('\n', i + 1)) >= 0) n++;
                return n;
            },
            editar(e) {
                const f = this.arqAtivo();
                if (f) f.texto = e.target.value;
                this.cursor();
                this.atualizarPrevia();
                this.agendarAuto();
            },
            /** Arquivo com mudanças ainda não gravadas na pasta do computador (só vale com "Abrir pasta"). */
            alterado(f) {
                this.ver; // dependência reativa: atualiza o ponto quando salva
                return !!(dir && f && gravados.get(f.caminho) !== f.texto);
            },
            totalAlterados() { return dir ? I.arquivos.filter(f => this.alterado(f)).length : 0; },
            agendarAuto() {
                if (!this.auto || !dir) return;
                clearTimeout(timerAuto);
                timerAuto = setTimeout(() => this.salvarDisco(true), 1500);
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
                this.selN = ta.selectionEnd - ta.selectionStart;
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
                const k = e.key, kl = k.toLowerCase(), f = this.arqAtivo(), caminho = f ? f.caminho : '';
                if (mod && kl === 's') { e.preventDefault(); this.salvar(); return; }
                if (mod && kl === 'f' && !e.shiftKey) { e.preventDefault(); this.abrirBusca(); return; }
                if (mod && kl === 'p') { e.preventDefault(); this.abrirPaleta('arq'); return; }
                if (mod && kl === 'g') { e.preventDefault(); this.abrirPaleta('linha'); return; }
                if (mod && k === 'Enter') { e.preventDefault(); this.executar(); return; }
                if (mod && e.shiftKey && kl === 'f') { e.preventDefault(); this.formatar(); return; }
                if (mod && k === '/') {
                    e.preventDefault();
                    if (!comentar(ta, grupoDe(caminho))) this.aviso('Esta linguagem não tem comentário de linha.');
                    return;
                }
                if (mod && e.shiftKey && kl === 'k') { e.preventDefault(); excluirLinhas(ta); return; }
                if (e.altKey && !mod && (k === 'ArrowUp' || k === 'ArrowDown')) {
                    e.preventDefault();
                    const d = k === 'ArrowUp' ? -1 : 1;
                    if (e.shiftKey) duplicarLinhas(ta, d); else moverLinhas(ta, d);
                    return;
                }

                if (k === 'Tab') {
                    e.preventDefault();
                    const multi = v.slice(ini, fim).includes('\n');
                    if (!multi && !e.shiftKey) { inserir(ta, '  '); return; }
                    const [a, z] = linhasSel(v, ini, fim);
                    const novo = v.slice(a, z).split('\n').map(l => (e.shiftKey ? l.replace(/^(?:  |\t| )/, '') : '  ' + l)).join('\n');
                    ta.setSelectionRange(a, z);
                    inserir(ta, novo);
                    ta.setSelectionRange(a, a + novo.length);
                    return;
                }
                if (k === 'Enter' && !e.shiftKey && !mod && !e.altKey) {
                    const a = inicioLinha(v, ini);
                    const ind = v.slice(a, ini).match(/^[ \t]*/)[0];
                    const antes = v[ini - 1], depois = v[fim];
                    const ab = '{[(', fe = '}])';
                    e.preventDefault();
                    if (antes && depois && ab.indexOf(antes) >= 0 && ab.indexOf(antes) === fe.indexOf(depois)) {
                        inserir(ta, '\n' + ind + '  \n' + ind);
                        const p = ini + 1 + ind.length + 2;
                        ta.setSelectionRange(p, p);
                    } else {
                        const extra = antes && (ab.includes(antes) || (antes === ':' && extDe(caminho) === 'py')) ? '  ' : '';
                        inserir(ta, '\n' + ind + extra);
                    }
                    return;
                }
                // Backspace entre um par vazio — (|) "|" {|} — apaga os dois
                if (k === 'Backspace' && ini === fim && ini > 0 && PARES[v[ini - 1]] === v[ini]) {
                    e.preventDefault();
                    ta.setSelectionRange(ini - 1, ini + 1);
                    apagarSel(ta);
                    return;
                }
                if (!mod && !e.altKey && k.length === 1 && f) {
                    const g = grupoDe(caminho);
                    // digitar o fecho que já está logo à frente só avança o cursor
                    if (FECHAM.includes(k) && ini === fim && v[ini] === k) {
                        e.preventDefault();
                        ta.setSelectionRange(ini + 1, ini + 1);
                        this.cursor();
                        return;
                    }
                    if (PARES[k] && g !== 'md') {
                        const aspas = k === '"' || k === "'" || k === '`';
                        if (ini !== fim) {
                            e.preventDefault();
                            inserir(ta, k + v.slice(ini, fim) + PARES[k]);
                            ta.setSelectionRange(ini + 1, fim + 1);
                            return;
                        }
                        // não fecha aspas depois de letra (ex.: don't) nem parêntese antes de letra
                        if (aspas ? /\w/.test(v[ini - 1] || '') : /\w/.test(v[ini] || '')) return;
                        e.preventDefault();
                        inserir(ta, k + PARES[k]);
                        ta.setSelectionRange(ini + 1, ini + 1);
                        return;
                    }
                }
                if (k === 'Escape') {
                    if (this.menuCtx) this.menuCtx = null;
                    else if (this.busca.on) this.busca.on = false;
                    else if (this.painel) this.fecharPainel();
                }
            },
            salvar() {
                if (dir) this.salvarDisco();
                else this.aviso('Salvo no Work Flower (o salvamento é automático).');
            },

            /* ---------- criar, renomear e excluir ---------- */
            novoArquivo() {
                const base = this.pastaAlvo();
                const nome = prompt('Nome do novo arquivo' + (base ? ' (em ' + base + '/)' : '') + ' (ex.: pagina.blade.php, Main.java):', 'novo.js');
                if (!nome) return;
                const c = limpar((base ? base + '/' : '') + nome);
                if (!c) { this.aviso('Nome inválido.'); return; }
                const ja = I.arquivos.find(f => f.caminho === c);
                if (ja) { this.abrir(ja.id); this.aviso('Esse arquivo já existe.'); return; }
                if (this.todasPastas().has(c)) { this.aviso('Já existe uma pasta com esse nome.'); return; }
                if (I.arquivos.length >= MAX_ARQ) { this.aviso('Limite de ' + MAX_ARQ + ' arquivos neste projeto.'); return; }
                const f = { id: uid(), caminho: c, texto: modeloDe(c) };
                I.arquivos.push(f);
                this.fechadas = this.fechadas.filter(p => !c.startsWith(p + '/'));
                this.abrir(f.id);
                this.atualizarPrevia();
                this.agendarAuto();
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
                this.mover(n, novo);
            },
            /* Troca o caminho de um arquivo ou de uma pasta (com tudo o que tem dentro). Usado por renomear e arrastar. */
            mover(n, novo) {
                if (n.k === 'a') {
                    const f = I.arquivos.find(x => x.caminho === n.caminho);
                    if (!f) return false;
                    pend.push(f.caminho);
                    f.caminho = novo;
                    this.sel = novo;
                    this.atualizarPrevia();
                    this.agendarAuto();
                    return true;
                }
                const pre = n.caminho + '/';
                if (novo.startsWith(pre)) { this.aviso('Não dá para mover uma pasta para dentro dela mesma.'); return false; }
                I.arquivos.filter(f => f.caminho.startsWith(pre)).forEach(f => { pend.push(f.caminho); f.caminho = novo + '/' + f.caminho.slice(pre.length); });
                I.pastas = I.pastas.map(p => (p === n.caminho ? novo : p.startsWith(pre) ? novo + '/' + p.slice(pre.length) : p));
                this.fechadas = this.fechadas.map(p => (p === n.caminho ? novo : p.startsWith(pre) ? novo + '/' + p.slice(pre.length) : p));
                this.sel = novo;
                this.atualizarPrevia();
                return true;
            },

            /* ---------- arrastar e soltar na árvore ----------
               Arraste um arquivo ou pasta para cima de uma pasta (ou de um arquivo dela) para mover para lá;
               solte no espaço vazio da lista para levar para a raiz do projeto. */
            arrastoIde: null, alvoIde: null,
            destinoDe(n) { return !n ? '' : n.k === 'p' ? n.caminho : pai(n.caminho); },
            podeSoltar(destino) {
                const a = this.arrastoIde;
                if (!a) return false;
                if (pai(a.caminho) === destino) return false; // já está nessa pasta
                return !(a.k === 'p' && (destino === a.caminho || destino.startsWith(a.caminho + '/')));
            },
            comecarArrasto(n, e) {
                this.arrastoIde = { k: n.k, caminho: n.caminho };
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/x-wf-ide', n.caminho); // o Firefox só arrasta com algum dado
            },
            sobreArrasto(n, e) {
                if (!this.arrastoIde) return;
                const destino = this.destinoDe(n);
                if (!this.podeSoltar(destino)) { this.alvoIde = null; return; }
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.alvoIde = destino;
            },
            soltarArrasto(n, e) {
                const a = this.arrastoIde, destino = this.destinoDe(n), ok = this.podeSoltar(destino);
                this.fimArrasto();
                if (!ok) return;
                e.preventDefault();
                const novo = (destino ? destino + '/' : '') + nomeDe(a.caminho);
                if (this.existe(novo)) { this.aviso('Já existe "' + nomeDe(a.caminho) + '" em ' + (destino || 'a raiz do projeto') + '.'); return; }
                const origem = pai(a.caminho);
                if (!this.mover(a, novo)) return;
                // a pasta de onde saiu continua na árvore, mesmo que tenha ficado vazia; e a de destino abre
                if (origem && !this.todasPastas().has(origem)) I.pastas.push(origem);
                this.fechadas = this.fechadas.filter(p => p !== destino && !destino.startsWith(p + '/'));
                this.agendarAuto();
            },
            fimArrasto() { this.arrastoIde = null; this.alvoIde = null; },
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
                this.agendarAuto();
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
                this.agendarAuto();
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
            /** Cria, logo abaixo, um cartão de Mapa mental com as pastas, arquivos, classes, funções e imports (js/mapa-codigo.js). */
            mapaDoCodigo() {
                if (!I.arquivos.length) { this.aviso('O projeto está vazio.'); return; }
                if (!window.WFMapaCodigo || typeof this.criarBloco !== 'function') { this.aviso('Recarregue a página (Ctrl+F5) para usar o mapa do código.'); return; }
                const r = window.WFMapaCodigo.gerar(I.arquivos, b.titulo);
                const L = this.estado && this.estado.layout && this.estado.layout[b.secao] && this.estado.layout[b.secao][b.id];
                const id = this.criarBloco(b.secao, 'mapa', L ? { x: L.x, y: L.y + L.h + 16 } : null,
                    { titulo: (b.titulo || 'Projeto') + ' (mapa do código)', dados: { nos: r.nos, lig: r.lig, tracos: [] } });
                if (!id) return;
                const N = this.estado.layout[b.secao][id];
                // aqui "this.col" é a coluna do cursor da IDE: a largura sai do próprio cartão de código
                if (N) { N.w = Math.max(L ? L.w : 720, 960); N.h = 640; }
                this.aviso('Mapa do código criado logo abaixo: ' + r.resumo + '.');
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
                    this.fechadas = []; this.sel = ''; this.disco = h.name; this.ver++;
                    htmlId = null;
                    const primeiro = arqs.find(f => /^readme/i.test(f.caminho)) || arqs[0];
                    if (primeiro) this.abrir(primeiro.id);
                    this.aviso(arqs.length + ' arquivo(s) carregado(s) de "' + h.name + '"' + (pulados ? ' (' + pulados + ' ignorado(s): binários, grandes ou limite)' : '') + '.');
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    this.aviso('Não consegui abrir a pasta.');
                }
            },
            async salvarDisco(silencioso) {
                if (!dir) return;
                clearTimeout(timerAuto);
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
                    this.ver++;
                    if (!silencioso || n || apagados) this.aviso('Salvo em "' + this.disco + '": ' + n + ' gravado(s)' + (apagados ? ', ' + apagados + ' apagado(s)' : '') + '.');
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
            /** Monta a expressão da busca (ou null se vazia/inválida). g = flags extras: 'g', 'y' ou ''. */
            reBusca(g = 'g') {
                const q = this.busca.q;
                if (!q) return null;
                try { return new RegExp(this.busca.regex ? q : rx(q), (this.busca.caso ? '' : 'i') + g); } catch { return null; }
            },
            buscaInvalida() { return !!(this.busca.regex && this.busca.q && !this.reBusca()); },
            contar() {
                const f = this.arqAtivo(), re = this.reBusca();
                if (!f || !re) return 0;
                let n = 0, m;
                while ((m = re.exec(f.texto))) { if (!m[0].length) re.lastIndex++; else n++; if (n > 99999) break; }
                return n;
            },
            achar(dirc) {
                const ta = this.$refs.ta, re = this.reBusca();
                if (!ta || !re) return null;
                const pos = dirc > 0 ? ta.selectionEnd : ta.selectionStart;
                let m, primeiro = null, ultimo = null, antes = null;
                while ((m = re.exec(ta.value))) {
                    if (!m[0].length) { re.lastIndex++; continue; }
                    if (!primeiro) primeiro = m;
                    ultimo = m;
                    if (dirc > 0 && m.index >= pos) return m;
                    if (dirc < 0 && m.index + m[0].length <= pos) antes = m;
                }
                return dirc > 0 ? primeiro : (antes || ultimo);
            },
            proxima(dirc) {
                const m = this.achar(dirc);
                if (!m) { this.aviso(this.buscaInvalida() ? 'Expressão regular inválida.' : 'Nada encontrado.'); return; }
                this.selecionar(m.index, m.index + m[0].length);
            },
            /** Texto que entra no lugar de uma ocorrência (com $1, $2… no modo regex). */
            trocaDe(trecho) {
                const r = this.busca.r || '';
                if (!this.busca.regex) return r;
                const re = this.reBusca('');
                return re ? trecho.replace(re, r) : r;
            },
            substituirUm() {
                const ta = this.$refs.ta, re = this.reBusca('y');
                if (!ta || !re) return;
                re.lastIndex = ta.selectionStart;
                const m = re.exec(ta.value);
                if (m && m[0].length && m.index === ta.selectionStart && m.index + m[0].length === ta.selectionEnd) inserir(ta, this.trocaDe(m[0]));
                this.proxima(1);
            },
            substituirTodos() {
                const f = this.arqAtivo(), ta = this.$refs.ta, re = this.reBusca();
                if (!f || !ta || !re) return;
                const n = this.contar();
                if (!n) { this.aviso(this.buscaInvalida() ? 'Expressão regular inválida.' : 'Nada encontrado.'); return; }
                const r = this.busca.r || '';
                const novo = f.texto.replace(re, this.busca.regex ? r : () => r);
                substituirTudo(ta, novo);   // entra no histórico: Ctrl+Z desfaz
                this.aviso(n + ' ocorrência(s) substituída(s).');
            },
            resultados() {
                if (!this.busca.todos || (this.busca.q || '').length < 2) return [];
                const re = this.reBusca('');
                if (!re) return [];
                const out = [];
                for (const f of I.arquivos) {
                    const ls = f.texto.split('\n');
                    for (let i = 0; i < ls.length; i++) {
                        if (!re.test(ls[i])) continue;
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

            /* ---------- abrir arquivo (Ctrl+P) e ir para a linha (Ctrl+G) ---------- */
            abrirPaleta(modo) {
                this.pal = { on: true, modo, q: '', i: 0 };
                this.$nextTick(() => { const q = this.$refs.palq; if (q) { q.focus(); q.select(); } });
            },
            fecharPaleta() {
                this.pal.on = false;
                this.$nextTick(() => { const ta = this.$refs.ta; if (ta) ta.focus(); });
            },
            itensPaleta() {
                const q = (this.pal.q || '').trim().toLowerCase();
                const lista = I.arquivos
                    .map(f => ({ f, s: q ? pontuar(f.caminho.toLowerCase(), q) : 0 }))
                    .filter(x => x.s >= 0);
                lista.sort((a, b) => b.s - a.s || a.f.caminho.localeCompare(b.f.caminho, 'pt-BR'));
                return lista.slice(0, 50).map(x => x.f);
            },
            teclaPaleta(e) {
                const k = e.key;
                if (k === 'Escape') { e.preventDefault(); this.fecharPaleta(); return; }
                if (this.pal.modo === 'linha') {
                    if (k === 'Enter') { e.preventDefault(); this.irParaLinha(this.pal.q); }
                    return;
                }
                const n = this.itensPaleta().length;
                if (k === 'ArrowDown' && n) { e.preventDefault(); this.pal.i = (this.pal.i + 1) % n; }
                else if (k === 'ArrowUp' && n) { e.preventDefault(); this.pal.i = (this.pal.i - 1 + n) % n; }
                else if (k === 'Enter') {
                    e.preventDefault();
                    const f = this.itensPaleta()[this.pal.i];
                    if (f) this.escolherPaleta(f);
                }
            },
            escolherPaleta(f) {
                this.pal.on = false;
                this.abrir(f.id);
                this.$nextTick(() => { const ta = this.$refs.ta; if (ta) ta.focus(); });
            },
            /** Aceita "12" ou "12:5" (linha e coluna). */
            irParaLinha(txt) {
                const f = this.arqAtivo();
                if (!f) { this.fecharPaleta(); return; }
                const m = String(txt || '').trim().match(/^(\d+)(?:\s*[:,]\s*(\d+))?$/);
                if (!m) { this.aviso('Digite um número de linha (ex.: 42 ou 42:7).'); return; }
                const ls = f.texto.split('\n');
                const linha = limitar(parseInt(m[1], 10), 1, ls.length);
                const ini = ls.slice(0, linha - 1).reduce((s, l) => s + l.length + 1, 0);
                const col = m[2] ? limitar(parseInt(m[2], 10), 1, ls[linha - 1].length + 1) - 1 : 0;
                this.pal.on = false;
                this.$nextTick(() => this.selecionar(ini + col, ini + col));
            },

            /** Formata o JSON aberto (Ctrl+Shift+F). */
            formatar() {
                const f = this.arqAtivo(), ta = this.$refs.ta;
                if (!f || !ta) return;
                if (extDe(f.caminho) !== 'json') { this.aviso('Formatar está disponível para arquivos JSON.'); return; }
                try {
                    const novo = JSON.stringify(JSON.parse(f.texto), null, 2) + '\n';
                    if (novo === f.texto) { this.aviso('O JSON já está formatado.'); return; }
                    substituirTudo(ta, novo);
                    this.aviso('JSON formatado.');
                } catch (err) {
                    this.aviso('JSON inválido: ' + String(err.message).slice(0, 80));
                }
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
                const f = this.arqAtivo(), l = f ? lingua(f.caminho) : '';
                return !!NAVEGADOR[l] || !!REMOTO[l] || this.htmls().length > 0 || (!!f && this.doLaravel(f));
            },
            /** Uma linha no console; erros ganham uma dica em português do porquê. */
            logar(t, m, l) {
                const texto = String(m ?? '').slice(0, 4000);
                if (!texto.trim() && t !== 'log') return;
                this.saida.push({ t, m: texto });
                if ((t === 'error' || /fatal error|parse error|warning:|exception/i.test(texto)) && window.WFDicas) {
                    const d = window.WFDicas.explicar(l, texto);
                    if (d && !this.saida.some(x => x.t === 'dica' && x.m === d)) this.saida.push({ t: 'dica', m: d });
                }
                while (this.saida.length > MAX_SAIDA) this.saida.shift();
            },
            /** Antes de rodar: chave, parêntese ou colchete sem par vira dica já com a linha. */
            conferirAntes(f) {
                if (!window.WFDicas) return true;
                const l = lingua(f.caminho);
                if (l === 'blade') { const p = window.WFDicas.conferirBlade(f.texto); if (p) { this.logar('warn', 'Atenção em ' + f.caminho + ': ' + p, l); } return true; }
                if (!['js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'py', 'php', 'sql', 'rb', 'lua'].includes(l)) return true;
                const p = window.WFDicas.conferirPares(f.texto, JS_NODE.includes(l) ? 'js' : l);
                if (p) { this.logar('error', f.caminho + ', linha ' + p.linha + ': ' + p.msg, l); this.saida.push({ t: 'dica', m: 'Corrija e rode de novo. Dica: clique numa chave/parêntese para ver o par dele; Ctrl+G vai direto para a linha ' + p.linha + '.' }); return false; }
                return true;
            },
            motorPronto() {
                if (window.WFMotores) return true;
                this.logar('error', 'Os motores da IDE não carregaram. Recarregue a página (Ctrl+F5).');
                this.painel = 'saida';
                return false;
            },
            arquivosTexto() { const a = {}; I.arquivos.forEach(x => { a[x.caminho] = x.texto; }); return a; },
            /** PHP comum roda no navegador (PHP 8.4). Arquivos do Laravel (controllers, models, rotas…) só têm a sintaxe conferida. */
            ehLaravel(f) { return /(\bnamespace\s+App\\|use\s+Illuminate\\|\bRoute::|\bSchema::|extends\s+(Model|Controller|Migration)\b)/.test(f.texto); },
            async rodarPhp(f) {
                if (!this.motorPronto()) return;
                if (this.doLaravel(f)) return this.rodarLaravel({ url: this.lv.ativo ? this.lv.url : '/' });
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                if (this.ehLaravel(f)) return this.verificarPhp(f, n);
                this.logar('info', 'Rodando PHP 8.4 no navegador…' + (this.motorUsado.php ? '' : ' (a primeira vez baixa o PHP, ~12 MB)'));
                const t0 = performance.now();
                let html = '';
                const html_ = /<html|<body|<div|<p>|<h[1-6]/i.test(f.texto.replace(/<\?php[\s\S]*?(\?>|$)/g, ''));
                await window.WFMotores.rodar({ acao: 'php', arqs: this.arquivosTexto(), html: html_, codigo: '<' + '?php chdir(' + JSON.stringify('/app/' + pai(f.caminho)) + '); error_reporting(E_ALL); ini_set("display_errors", "1"); require ' + JSON.stringify('/app/' + f.caminho) + ';' }, d => {
                    if (n !== token) return;
                    if (d.t === 'html') html = d.m || '';
                    else this.logar(d.t, d.m, 'php');
                });
                this.motorUsado.php = true;
                if (n !== token) return;
                if (html_) { this.mostrarHtml(html, n); if (/(fatal error|parse error|warning:)/i.test(html)) this.logar('error', html.replace(/<[^>]+>/g, '').trim().slice(0, 600), 'php'); }
                this.logar('info', '— fim · ' + Math.round(performance.now() - t0) + ' ms —');
            },
            /** Confere a sintaxe de todos os .php do projeto (token_get_all com TOKEN_PARSE acusa o erro e a linha). */
            async verificarPhp(f, n) {
                const phps = I.arquivos.filter(x => lingua(x.caminho) === 'php');
                this.logar('info', 'Este arquivo é do Laravel, mas o projeto não tem routes/web.php: sem rotas o app não abre. Conferindo a sintaxe de ' + phps.length + ' arquivo(s) PHP…');
                this.saida.push({ t: 'dica', m: 'Para rodar o app aqui no navegador, crie routes/web.php (+ Laravel → Rotas) ou comece pelo “+ Laravel → Projeto completo”.' });
                const lista = JSON.stringify(phps.map(x => x.caminho));
                const codigo = '<' + '?php foreach (json_decode(' + JSON.stringify(lista) + ', true) as $a) { try { token_get_all(file_get_contents("/app/" . $a), TOKEN_PARSE); echo "✓ $a\\n"; } catch (\\ParseError $e) { echo "✗ $a, linha " . $e->getLine() . ": " . $e->getMessage() . "\\n"; } }';
                await window.WFMotores.rodar({ acao: 'php', arqs: this.arquivosTexto(), codigo }, d => {
                    if (n !== token) return;
                    String(d.m || '').split('\n').filter(Boolean).forEach(linha => this.logar(linha.startsWith('✗') ? 'error' : d.t, linha, 'php'));
                });
                this.motorUsado.php = true;
            },
            /** View Blade: converte as views do projeto para PHP e mostra a página com os dados de dados.json. */
            async rodarBlade(f) {
                if (!this.motorPronto()) return;
                if (this.doLaravel(f)) return this.rodarLaravel({ url: this.lv.ativo ? this.lv.url : '/' });
                const n = ++token;
                htmlId = null; this.saida = []; this.lv.ativo = false;
                this.conferirAntes(f);
                const M = window.WFMotores, arqs = {};
                I.arquivos.forEach(x => {
                    if (lingua(x.caminho) === 'blade') arqs['__views/' + M.nomeView(x.caminho) + '.php'] = M.compilarBlade(x.texto);
                    else if (!BINARIO.test(x.caminho)) arqs[x.caminho] = x.texto;
                });
                const dadosArq = I.arquivos.find(x => x.caminho === f.caminho.replace(/\.blade\.php$/i, '.json')) || I.arquivos.find(x => nomeDe(x.caminho) === 'dados.json');
                let dados = {};
                if (dadosArq) { try { dados = JSON.parse(dadosArq.texto) || {}; } catch (e) { this.logar('error', nomeDe(dadosArq.caminho) + ' não é um JSON válido: ' + e.message); this.saida.push({ t: 'dica', m: 'No JSON, os nomes vão entre aspas duplas ("nome": "Ana") e não pode sobrar vírgula no último item.' }); } }
                else this.logar('info', 'Dica: crie um arquivo dados.json com as variáveis da view (ex.: {"titulo": "Olá"}) para ver a página com dados.');
                this.logar('info', 'Renderizando ' + f.caminho + ' com o PHP do navegador…' + (this.motorUsado.php ? '' : ' (a primeira vez baixa o PHP, ~12 MB)'));
                const t0 = performance.now();
                let r = null, solto = '';
                try { arqs['__laravel.php'] = await M.nucleoLaravel(); } catch (e) { this.logar('error', e.message); this.painel = 'saida'; return; }
                await M.rodar({ acao: 'laravel', arqs, req: { modo: 'previa', view: M.nomeView(f.caminho), dados, db: '/tmp/wf-previa-' + b.id + '.sqlite' } }, d => {
                    if (n !== token) return;
                    if (d.t === 'laravel') { r = d.resp; solto = d.saida || ''; } else this.logar(d.t, d.m, 'blade');
                });
                this.motorUsado.php = true;
                if (n !== token) return;
                if (!r) { this.logar('error', 'O PHP parou antes de terminar: ' + solto.replace(/<[^>]+>/g, '').trim().slice(0, 800), 'blade'); this.painel = 'saida'; return; }
                (r.logs || []).forEach(([t, m]) => this.logar(t, m, 'blade'));
                this.mostrarHtml(r.corpo || '', n);
                this.logar('info', '— página pronta · ' + Math.round(performance.now() - t0) + ' ms —');
            },

            /* ---------- Laravel do navegador (public/js/ide-mini-laravel.txt) ---------- */
            /** Pasta do projeto onde está o Laravel ('' = raiz). null = não é um projeto Laravel (sem routes/web.php). */
            raizLaravel() {
                const r = I.arquivos.find(x => /(^|\/)routes\/web\.php$/.test(x.caminho));
                return r ? r.caminho.slice(0, -'routes/web.php'.length) : null;
            },
            /** O arquivo faz parte do app Laravel (rodar ele = abrir o app na prévia)? */
            doLaravel(f) {
                const r = this.raizLaravel();
                if (r === null || !f || !f.caminho.startsWith(r)) return false;
                const c = f.caminho.slice(r.length);
                return /^(app|routes|database|config|resources|bootstrap|lang)\//.test(c) || ['.env', 'composer.json', 'artisan'].includes(c) || /^public\/.+\.(css|js)$/.test(c);
            },
            arquivosDoLaravel() {
                const r = this.raizLaravel() || '', a = {};
                I.arquivos.forEach(x => { if (x.caminho.startsWith(r) && !BINARIO.test(x.caminho)) a[x.caminho.slice(r.length)] = x.texto; });
                return a;
            },
            normalizarUrl(u) {
                u = String(u || '/').trim().replace(/^https?:\/\/localhost(?::\d+)?/i, '');
                return u.startsWith('/') ? u : '/' + u;
            },
            /** Uma requisição ao app (seguindo redirecionamentos). As requisições vão em fila: o PHP atende uma por vez. */
            requisitarLaravel(req, aoLog) {
                const fazer = async () => {
                    const M = window.WFMotores;
                    const arqs = await M.arquivosLaravel(this.arquivosDoLaravel());
                    let metodo = (req.metodo || 'GET').toUpperCase(), url = this.normalizarUrl(req.url), corpo = req.corpo || '', r = null;
                    for (let salto = 0; salto < 6; salto++) {
                        let solto = '';
                        r = null;
                        await M.rodar({ acao: 'laravel', arqs, req: { metodo, url, corpo, cabecalhos: copia(req.cabecalhos), sessao: copia(this.lv.sessao), db: '/tmp/wf-laravel-' + b.id + '.sqlite' } }, d => {
                            if (d.t === 'laravel') { r = d.resp; solto = d.saida || ''; } else aoLog(d.t, d.m);
                        });
                        this.motorUsado.php = true;
                        if (!r) return { erro: 'O PHP parou antes de responder: ' + solto.replace(/<[^>]+>/g, '').trim().slice(0, 800) };
                        this.lv.sessao = r.sessao || {};
                        (r.logs || []).forEach(([t, m]) => aoLog(t, m));
                        aoLog('info', metodo + ' ' + url + ' → ' + r.status + (r.rota ? ' (' + r.rota + ')' : ''));
                        if (!(r.local && r.status >= 300 && r.status < 400)) break;
                        const u = /^https?:\/\/localhost(?::\d+)?(\/[^#]*)?/i.exec(r.local) || (r.local.startsWith('/') ? [0, r.local] : null);
                        if (!u) { aoLog('warn', 'O app redirecionou para fora dele: ' + r.local); break; }
                        metodo = 'GET'; url = this.normalizarUrl(u[1] || '/'); corpo = '';
                    }
                    return { r, url };
                };
                const p = filaLv.then(fazer, fazer);
                filaLv = p.catch(() => {});
                return p;
            },
            /** Abre (ou navega) o app Laravel na prévia. */
            async rodarLaravel(req, opc = {}) {
                if (!this.motorPronto()) return;
                const n = ++token, lv = this.lv;
                htmlId = null;
                const navegando = lv.ativo;
                lv.ativo = true; lv.carregando = true;
                if (!opc.manterSaida) this.saida = [];
                if (!this.motorUsado.php) this.logar('info', 'Abrindo o app Laravel no PHP do navegador… (a primeira vez baixa o PHP, ~12 MB; o banco é criado pelas migrations)');
                const t0 = performance.now();
                let res;
                try { res = await this.requisitarLaravel(req, (t, m) => { if (n === token) this.logar(t, m, 'laravel'); }); }
                catch (e) { res = { erro: String(e && e.message || e) }; }
                if (n !== token) return;
                lv.carregando = false;
                if (res.erro) { this.logar('error', res.erro, 'laravel'); this.painel = 'saida'; return; }
                if (navegando && !opc.semHist && res.url !== lv.url) { lv.hist.push(lv.url); if (lv.hist.length > 50) lv.hist.shift(); }
                lv.url = res.url; lv.campo = res.url; lv.status = res.r.status;
                this.mostrarLaravel(res.r, n);
                this.logar('info', '— ' + Math.round(performance.now() - t0) + ' ms —');
            },
            mostrarLaravel(r, n) {
                const tipo = r.tipo || 'text/html';
                let html = r.corpo || '';
                if (tipo !== 'text/html') {
                    let txt = html;
                    if (/json/.test(tipo)) { try { txt = JSON.stringify(JSON.parse(html), null, 2); } catch (e) { /* mostra como veio */ } }
                    html = '<!doctype html><meta char' + 'set="utf-8"><pre style="margin:0;padding:1rem;font:13px/1.5 ui-monospace,monospace;white-space:pre-wrap;word-break:break-word">' + esc(txt) + '</pre>';
                }
                const extra = shim(n) + navLaravel(n, this.lv.url);
                const temHead = /<head(?:\s[^>]*)?>/i, temDoctype = /^\s*<!doctype[^>]*>/i;
                if (temHead.test(html)) html = html.replace(temHead, m => m + extra);
                else if (temDoctype.test(html)) html = html.replace(temDoctype, m => m + extra);
                else html = extra + html;
                if (this.painel !== 'previa' && this.altPainel < 340) { this.altPainel = 340; this.gravarLayout(); }
                this.srcdoc = (temDoctype.test(html) ? '' : '<!doctype html><meta char' + 'set="utf-8">') + html;
                this.painel = 'previa';
            },
            /** fetch() feito pela página do app: vira uma requisição ao Laravel e a resposta volta para a página. */
            async fetchLaravel(d, janela) {
                let res;
                try { res = await this.requisitarLaravel({ metodo: d.metodo, url: d.url, corpo: d.corpo, cabecalhos: Object.assign({ accept: 'application/json', 'x-requested-with': 'XMLHttpRequest' }, d.cabecalhos || {}) }, (t, m) => this.logar(t, m, 'laravel')); }
                catch (e) { res = { erro: String(e && e.message || e) }; }
                const r = res.r || { status: 500, corpo: res.erro || 'erro', tipo: 'text/plain' };
                try { janela.postMessage({ wfIdeResp: d.wfIde, id: d.id, status: r.status || 200, corpo: r.corpo || '', tipo: r.tipo || 'text/plain' }, '*'); } catch (e) { /* a prévia já mudou */ }
            },
            irPara(url) { if (!url) return; this.rodarLaravel({ url }); },
            voltarLaravel() { const u = this.lv.hist.pop(); if (u) this.rodarLaravel({ url: u }, { semHist: true }); },
            recarregarLaravel() { this.rodarLaravel({ url: this.lv.url }, { semHist: true }); },
            /** Comandos do artisan (route:list, migrate:fresh --seed, tinker …) no Laravel do navegador. */
            async artisan(cmd, silencioso) {
                if (!this.motorPronto()) return null;
                if (cmd === undefined) {
                    cmd = prompt('Comando do artisan (list mostra todos):\nex.: route:list · migrate:fresh --seed · db:seed · tinker App\\Models\\User::count()', this.ultimoArtisan || 'route:list');
                    if (!cmd) return null;
                    this.ultimoArtisan = cmd = cmd.trim().replace(/^(php\s+)?artisan\s+/, '');
                }
                const M = window.WFMotores;
                const fazer = async () => {
                    const arqs = await M.arquivosLaravel(this.arquivosDoLaravel());
                    let r = null, solto = '';
                    await M.rodar({ acao: 'laravel', arqs, req: { modo: 'artisan', cmd, sessao: copia(this.lv.sessao), db: '/tmp/wf-laravel-' + b.id + '.sqlite' } }, d => { if (d.t === 'laravel') { r = d.resp; solto = d.saida || ''; } else if (!silencioso) this.logar(d.t, d.m, 'laravel'); });
                    this.motorUsado.php = true;
                    return r || { erro: solto };
                };
                const p = filaLv.then(fazer, fazer);
                filaLv = p.catch(() => {});
                const r = await p;
                if (silencioso) return r;
                this.painel = 'saida';
                this.logar('info', '$ php artisan ' + cmd);
                if (r.erro !== undefined && !r.corpo) { this.logar('error', 'O PHP parou: ' + String(r.erro).replace(/<[^>]+>/g, '').trim().slice(0, 800), 'laravel'); return r; }
                (r.logs || []).forEach(([t, m]) => this.logar(t, m, 'laravel'));
                String(r.corpo || '').replace(/\n$/, '').split('\n').forEach(l => this.logar(r.status >= 500 ? 'error' : 'log', l.replace(/<[^>]+>/g, ''), 'laravel'));
                if (/^(migrate|db:)/.test(cmd) && this.lv.ativo) { this.atualizarBanco(); }
                return r;
            },
            mostrarHtml(html, n) {
                if (this.painel !== 'previa' && this.altPainel < 340) { this.altPainel = 340; this.gravarLayout(); }
                this.srcdoc = (/^\s*<!doctype/i.test(html) ? '' : '<!doctype html><meta char' + 'set="utf-8">') + shim(n) + html;
                this.painel = this.saida.some(x => x.t === 'error') ? 'saida' : 'previa';
            },
            /** SQL no navegador (SQLite). O banco fica guardado enquanto a página estiver aberta; veja as tabelas na aba Banco. */
            async rodarSql(f) {
                if (!this.motorPronto()) return;
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                this.logar('info', 'Rodando no SQLite do navegador…' + (this.motorUsado.sql ? '' : ' (a primeira vez baixa o SQLite, ~1 MB)'));
                const t0 = performance.now();
                await window.WFMotores.rodar({ acao: 'sql', projeto: b.id, texto: f.texto }, d => this.receberSql(d, n));
                this.motorUsado.sql = true;
                if (n === token) this.logar('info', '— ' + Math.round(performance.now() - t0) + ' ms —');
            },
            receberSql(d, n) {
                if (n !== undefined && n !== token) return;
                if (d.t === 'tabela') this.saida.push({ t: 'tabela', m: d.titulo, colunas: d.colunas, linhas: d.linhas, total: d.total });
                else if (d.t === 'esquema') this.banco.tabelas = d.tabelas || [];
                else this.logar(d.t, d.m, 'sql');
            },
            async verTabela(nome) {
                if (!this.motorPronto()) return;
                this.banco.sel = nome; this.banco.dados = null;
                if (this.lv.ativo) { const r = await this.artisan('wf:tabela ' + nome, true); try { this.banco.dados = JSON.parse(r.corpo); } catch (e) { this.logar('error', 'Não consegui ler a tabela ' + nome + '.'); } return; }
                await window.WFMotores.rodar({ acao: 'sqltabela', projeto: b.id, tabela: nome }, d => { if (d.t === 'tabela') this.banco.dados = d; else this.receberSql(d); });
            },
            async atualizarBanco() {
                if (!this.motorPronto()) return;
                if (this.lv.ativo) { const r = await this.artisan('wf:esquema', true); try { this.banco.tabelas = JSON.parse(r.corpo); } catch (e) { this.banco.tabelas = []; } return; }
                await window.WFMotores.rodar({ acao: 'sqlesquema', projeto: b.id }, d => this.receberSql(d));
            },
            /** Apaga o banco e roda todos os .sql do projeto em ordem de nome (ex.: 01_tabelas.sql, 02_dados.sql). */
            async recriarBanco() {
                if (!this.motorPronto()) return;
                if (this.lv.ativo) { this.banco.sel = null; this.banco.dados = null; await this.artisan('migrate:fresh --seed'); this.painel = 'banco'; return this.atualizarBanco(); }
                const n = ++token;
                this.saida = []; this.banco.sel = null; this.banco.dados = null;
                await window.WFMotores.rodar({ acao: 'sqlreset', projeto: b.id }, d => this.receberSql(d, n));
                const sqls = I.arquivos.filter(x => lingua(x.caminho) === 'sql').sort((a, c) => a.caminho.localeCompare(c.caminho));
                for (const s of sqls) {
                    this.logar('info', '▶ ' + s.caminho);
                    await window.WFMotores.rodar({ acao: 'sql', projeto: b.id, texto: s.texto }, d => this.receberSql(d, n));
                }
                if (!sqls.length) this.logar('info', 'Nenhum arquivo .sql no projeto.');
            },
            temSql() { return I.arquivos.some(x => lingua(x.caminho) === 'sql'); },
            temBanco() { return this.lv.ativo || this.temSql(); },
            /** Programa travado (laço infinito em Python/PHP/Ruby): recria o motor. */
            pararMotores() {
                if (window.WFMotores) window.WFMotores.reiniciar();
                this.motorUsado = {};
                ++token;
                filaLv = Promise.resolve();
                this.lv.carregando = false;
                this.srcdoc = '';
                this.logar('info', 'Execução interrompida. Os motores serão carregados de novo na próxima execução' + (this.lv.ativo ? ' (o banco do app Laravel recomeça das migrations).' : '.'));
            },
            /** Cria o projeto Laravel de exemplo (lista de tarefas) que roda aqui no navegador. */
            projetoLaravel() {
                const M = window.WFMotores;
                if (!M) { this.aviso('Recarregue a página para usar os modelos do Laravel.'); return; }
                const arqs = M.projetoLaravel();
                const ja = arqs.filter(([c]) => I.arquivos.some(x => x.caminho === c)).length;
                if (ja && !confirm(ja + ' arquivo(s) do projeto de exemplo já existem e serão substituídos. Continuar?')) return;
                if (I.arquivos.length + arqs.length - ja > MAX_ARQ) { this.aviso('Limite de ' + MAX_ARQ + ' arquivos neste projeto.'); return; }
                let abrir = null;
                for (const [c, texto] of arqs) {
                    const x = I.arquivos.find(y => y.caminho === c);
                    if (x) x.texto = texto; else I.arquivos.push({ id: uid(), caminho: c, texto });
                    if (c === 'routes/web.php') abrir = I.arquivos.find(y => y.caminho === c);
                }
                this.fechadas = [];
                this.lv.sessao = {}; this.lv.hist = [];
                if (abrir) this.abrir(abrir.id);
                this.agendarAuto();
                this.artisan('migrate:fresh --seed', true).then(() => this.rodarLaravel({ url: '/' }));
                this.aviso('Projeto Laravel criado. Ele já abre na Prévia: clique nos links e use os formulários.');
            },
            /** Arquivos prontos do Laravel: projeto completo, controller, model, migration, rota, view, layout… */
            novoLaravel() {
                const tipos = { 0: 'projeto', 1: 'controller', 2: 'model', 3: 'migration', 4: 'rota', 5: 'view', 6: 'layout', 7: 'seeder', 8: 'factory', 9: 'request', 10: 'componente' };
                const t = prompt('Que arquivo Laravel criar?\n0 Projeto completo que roda aqui (tarefas: CRUD, banco, validação)\n1 Controller\n2 Model\n3 Migration (tabela)\n4 Rotas (routes/web.php)\n5 View Blade\n6 Layout Blade (layouts/app)\n7 Seeder (DatabaseSeeder)\n8 Factory\n9 Form Request (validação)\n10 Componente Blade (<x-...>)', this.raizLaravel() === null ? '0' : '1');
                const tipo = tipos[String(t || '').trim()];
                if (!tipo) return;
                if (tipo === 'projeto') return this.projetoLaravel();
                const nome = ['rota', 'layout'].includes(tipo) ? 'Post' : prompt(tipo === 'componente' ? 'Nome do componente (ex.: Alerta, Botao):' : 'Nome (ex.: Post, Cliente, Produto):', tipo === 'componente' ? 'Alerta' : 'Post');
                if (!nome) return;
                const novo = window.WFMotores ? window.WFMotores.laravel(tipo, nome) : null;
                if (!novo) { this.aviso('Recarregue a página para usar os modelos do Laravel.'); return; }
                const criar = (caminho, texto) => {
                    const c = limpar(caminho);
                    const ja = I.arquivos.find(x => x.caminho === c);
                    if (ja) return ja;
                    if (I.arquivos.length >= MAX_ARQ) return null;
                    const f = { id: uid(), caminho: c, texto };
                    I.arquivos.push(f);
                    return f;
                };
                const f = criar(novo.caminho, novo.texto);
                if (!f) { this.aviso('Limite de ' + MAX_ARQ + ' arquivos neste projeto.'); return; }
                // a view de exemplo já vem com o layout e os dados para a prévia funcionar de primeira
                if (tipo === 'view') {
                    const l = window.WFMotores.laravel('layout');
                    criar(l.caminho, l.texto);
                    if (this.raizLaravel() === null) criar('dados.json', window.WFMotores.DADOS_EXEMPLO.replace(/posts/g, novo.caminho.split('/').slice(-2, -1)[0] || 'posts'));
                }
                if (tipo === 'controller' && !I.arquivos.some(x => /(^|\/)app\/Http\/Controllers\/Controller\.php$/.test(x.caminho))) criar('app/Http/Controllers/Controller.php', '<' + '?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n    //\n}\n');
                this.fechadas = this.fechadas.filter(p => !f.caminho.startsWith(p + '/'));
                this.abrir(f.id);
                this.agendarAuto();
                this.aviso(tipo === 'view' ? 'View criada com layout' + (this.raizLaravel() === null ? ' e dados.json' : '') + '. Clique em ▶ Executar para ver a página.' : 'Arquivo criado: ' + f.caminho);
            },
            /** Roda o arquivo pela linguagem dele; false se a linguagem não roda. */
            rodarLingua(f) {
                const l = lingua(f.caminho);
                if (l === 'py') return this.rodarPy(f);
                if (l === 'blade') return this.rodarBlade(f);
                if (l === 'php') return this.rodarPhp(f);
                if (l === 'sql') return this.rodarSql(f);
                if (l === 'rb') return this.rodarRb(f);
                if (l === 'lua') return this.rodarLua(f);
                if (JS_NODE.includes(l)) return this.rodarJs(f);
                if (REMOTO[l]) return this.rodarRemoto(f);
                return false;
            },
            executar() {
                const f = this.arqAtivo();
                // projeto Laravel: qualquer arquivo do app abre o app na prévia, no endereço atual
                if (f && this.doLaravel(f)) return this.rodarLaravel({ url: this.lv.ativo ? this.lv.url : '/' });
                this.lv.ativo = false;
                const pagina = this.paginaPara(f);
                if (pagina) return this.rodarHtml(pagina);
                // Python, PHP, SQL, Ruby, Lua, Blade e JS/TS rodam no navegador (public/js/ide-motores.js); Java, C, Go… no Judge0
                if (f) { const r = this.rodarLingua(f); if (r !== false) return r; }
                // cabeçalhos .h e scripts de build não rodam isolados
                if (f && NAO_RODA.has(lingua(f.caminho))) {
                    this.aviso('Este tipo de arquivo não roda sozinho. Execute o arquivo principal do projeto.');
                    return;
                }
                // outros arquivos (css, md, json…): abre a página principal do projeto
                const alvo = this.htmls().find(x => nomeDe(x.caminho).toLowerCase() === 'index.html') || this.htmls()[0];
                if (alvo) return this.rodarHtml(alvo);
                this.aviso('Crie um arquivo .html ou abra um .js ou .py para executar.');
            },

            /* ---------- menu de contexto (botão direito) ---------- */
            abrirMenu(n, e) {
                e.preventDefault(); e.stopPropagation();
                const r = this.$root.getBoundingClientRect();
                this.sel = n ? n.caminho : '';
                this.menuCtx = {
                    n,
                    x: limitar(e.clientX - r.left, 4, Math.max(4, r.width - 210)),
                    y: limitar(e.clientY - r.top, 4, Math.max(4, r.height - 250)),
                };
            },
            rodavel(n) {
                if (!n || n.k !== 'a') return false;
                const l = lingua(n.caminho);
                return !!NAVEGADOR[l] || !!REMOTO[l] || ehHtml(n.caminho) || this.doLaravel(n.arq || { caminho: n.caminho });
            },
            itensCtx() {
                const m = this.menuCtx;
                if (!m) return [];
                const n = m.n, out = [];
                if (n && n.k === 'a') {
                    const ok = this.rodavel(n), l = lingua(n.caminho);
                    const dica = this.doLaravel({ caminho: n.caminho }) ? 'app Laravel' : REMOTO[l] ? 'servidor' : ehHtml(n.caminho) ? 'Prévia' : NAVEGADOR[l] || 'Prévia';
                    out.push({ id: 'exec', nome: '▶ Executar', off: !ok, dica: ok ? dica : 'não roda aqui' });
                    out.push({ id: 'abrir', nome: 'Abrir' });
                } else if (n) {
                    out.push({ id: 'abrir', nome: n.fechada ? 'Expandir' : 'Recolher' });
                }
                out.push({ id: 'novoArq', nome: 'Novo arquivo aqui', sep: !!n });
                out.push({ id: 'novaPasta', nome: 'Nova pasta aqui' });
                if (n) {
                    out.push({ id: 'renomear', nome: 'Renomear / mover', sep: true });
                    if (n.k === 'a') out.push({ id: 'baixar', nome: 'Baixar arquivo' });
                    out.push({ id: 'copiar', nome: 'Copiar caminho' });
                    out.push({ id: 'excluir', nome: 'Excluir' });
                }
                return out;
            },
            acaoCtx(id) {
                const n = this.menuCtx && this.menuCtx.n;
                this.menuCtx = null;
                switch (id) {
                    case 'exec': return this.executarArquivo(n);
                    case 'abrir': return this.clicar(n);
                    case 'novoArq': return this.novoArquivo();
                    case 'novaPasta': return this.novaPasta();
                    case 'renomear': return this.renomear(n);
                    case 'excluir': return this.excluir(n);
                    case 'baixar': { this.abrir(n.id); return this.baixar(); }
                    case 'copiar':
                        return (window.WFRepo ? window.WFRepo.copiar(n.caminho) : Promise.resolve(false))
                            .then(ok => this.aviso(ok ? 'Caminho copiado.' : 'Não consegui copiar.'));
                }
            },
            /** Executa SÓ este arquivo (sem procurar HTML que o use). */
            executarArquivo(n) {
                const f = n && I.arquivos.find(x => x.caminho === n.caminho);
                if (!f) return;
                this.abrir(f.id);
                if (this.doLaravel(f)) return this.rodarLaravel({ url: this.lv.ativo ? this.lv.url : '/' });
                this.lv.ativo = false;
                if (ehHtml(f.caminho)) return this.rodarHtml(f);
                const r = this.rodarLingua(f);
                if (r !== false) return r;
                this.executar();
            },

            /** Python no navegador (Pyodide). O motor fica carregado entre execuções: só a primeira baixa o Python.
                Os arquivos do projeto ficam num sistema de arquivos virtual, então `import outro_modulo` funciona. */
            async rodarPy(f) {
                if (!this.motorPronto()) return;
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                this.logar('info', this.motorUsado.py ? 'Rodando Python…' : 'Carregando o Python (só na primeira vez demora alguns segundos)…');
                const t0 = performance.now();
                await window.WFMotores.rodar({ acao: 'py', arqs: this.arquivosTexto(), main: f.caminho }, d => { if (n === token) this.logar(d.t, d.m, 'py'); });
                this.motorUsado.py = true;
                if (n === token) this.logar('info', '— fim · ' + Math.round(performance.now() - t0) + ' ms —');
            },
            /** JavaScript / TypeScript "estilo Node" na prévia: require() e import entre os arquivos do projeto, module.exports,
                fs/path/events/readline… e pacotes do npm (baixados do esm.sh). TS, TSX, JSX e arquivos com import/export
                passam antes pelo compilador do TypeScript (só remove os tipos: não confere tipos). */
            async rodarJs(f) {
                const n = ++token;
                htmlId = null;
                this.saida = [];
                this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                const l = lingua(f.caminho), M = window.WFMotores;
                this.linguaJs = ['ts', 'tsx'].includes(l) ? 'ts' : 'js';
                if (!M) { this.srcdoc = '<!doctype html>' + shim(n) + '<script>' + f.texto.replace(/<\/script/gi, '<\\/script') + '<\/script>'; return; }
                const mods = {}, compilar = {};
                I.arquivos.forEach(x => {
                    const e = lingua(x.caminho);
                    if (!JS_NODE.includes(e)) return;
                    if (['ts', 'tsx', 'jsx'].includes(e) || /^\s*(?:import\s*[\w{*'"]|export\s)/m.test(x.texto)) compilar[x.caminho] = x.texto;
                    else mods[x.caminho] = x.texto;
                });
                if (Object.keys(compilar).length) {
                    this.logar('info', this.motorUsado.ts ? 'Compilando TypeScript / import…' : 'Baixando o compilador do TypeScript (só na primeira vez, ~3 MB)…');
                    let res = null;
                    await M.rodar({ acao: 'ts', arqs: compilar }, d => { if (d.t === 'js') res = d; else if (n === token) this.logar(d.t, d.m, 'ts'); });
                    this.motorUsado.ts = true;
                    if (n !== token || !res) return;
                    Object.assign(mods, res.mapa);
                    (res.avisos || []).forEach(a => this.logar('error', a, 'ts'));
                    if (res.avisos && res.avisos.length) return;
                }
                const npm = M.pacotesNpm(mods);
                if (npm.length) this.logar('info', 'Pacotes do npm (baixados do esm.sh): ' + npm.join(', '));
                const script = M.empacotarNode(mods, this.arquivosTexto(), f.caminho, npm);
                this.srcdoc = '<!doctype html><meta char' + 'set="utf-8">' + shim(n) + '<script>' + script.replace(/<\/script/gi, '<\\/script') + '<\/script>';
            },
            /** Ruby 3.4 no navegador (ruby.wasm): require_relative entre os .rb do projeto e gets pedindo a entrada. */
            async rodarRb(f) {
                if (!this.motorPronto()) return;
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                this.logar('info', this.motorUsado.rb ? 'Rodando Ruby…' : 'Baixando o Ruby 3.4 (só na primeira vez, ~6 MB; depois fica guardado)…');
                const t0 = performance.now(), arqs = {};
                I.arquivos.forEach(x => { if (lingua(x.caminho) === 'rb') arqs[x.caminho] = x.texto; });
                await window.WFMotores.rodar({ acao: 'rb', preludio: window.WFMotores.preludioRuby(arqs), main: f.caminho }, d => { if (n === token) this.logar(d.t, d.m, 'rb'); });
                this.motorUsado.rb = true;
                if (n === token) this.logar('info', '— fim · ' + Math.round(performance.now() - t0) + ' ms —');
            },
            /** Lua 5.3 no navegador (fengari): require "modulo" entre os .lua do projeto e io.read pedindo a entrada. */
            async rodarLua(f) {
                if (!this.motorPronto()) return;
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                if (!this.conferirAntes(f)) return;
                this.logar('info', this.motorUsado.lua ? 'Rodando Lua…' : 'Baixando o Lua (só na primeira vez, ~250 KB)…');
                const t0 = performance.now(), arqs = {};
                I.arquivos.forEach(x => { if (lingua(x.caminho) === 'lua') arqs[x.caminho] = x.texto; });
                await window.WFMotores.rodar({ acao: 'lua', codigo: window.WFMotores.codigoLua(arqs, f.caminho) }, d => { if (n === token) this.logar(d.t, d.m, 'lua'); });
                this.motorUsado.lua = true;
                if (n === token) this.logar('info', '— fim · ' + Math.round(performance.now() - t0) + ' ms —');
            },
            /** Java, Kotlin, C#, Go, Rust, C, C++, Bash… executados no servidor Judge0 (um arquivo por vez). */
            async rodarRemoto(f) {
                const l = REMOTO[lingua(f.caminho)];
                if (!l) return;
                const n = ++token;
                htmlId = null; this.saida = []; this.painel = 'saida';
                let src = f.texto;
                if (lingua(f.caminho) === 'java') {   // o Judge0 exige a classe Main
                    const m = src.match(/public\s+(?:final\s+)?class\s+(\w+)/);
                    if (m && m[1] !== 'Main') src = src.replace(new RegExp('\\b' + m[1] + '\\b', 'g'), 'Main');
                }
                let stdin = '';
                if (/Scanner|BufferedReader|readLine|scanf|cin\s*>>|fgets|STDIN|Console\.Read|bufio/.test(src)) {
                    const r = prompt('O programa parece ler entrada. Digite a entrada (separe linhas com |):', '');
                    if (r === null) return;
                    stdin = r.split('|').join('\n');
                }
                this.saida.push({ t: 'info', m: 'Executando ' + l.nome + ' no servidor (Judge0)…' });
                const ctl = new AbortController(), tm = setTimeout(() => ctl.abort(), 40000);
                try {
                    const r = await fetch(JUDGE0 + '/submissions?base64_encoded=true&wait=true', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' }, signal: ctl.signal,
                        body: JSON.stringify({ language_id: l.id, source_code: b64(src), stdin: stdin ? b64(stdin) : null }),
                    });
                    const d = await r.json().catch(() => null);
                    if (n !== token) return;
                    if (!r.ok || !d) throw new Error('HTTP ' + r.status + (d && d.error ? ' – ' + d.error : ''));
                    if (d.stdout) this.saida.push({ t: 'log', m: deb64(d.stdout) });
                    if (d.compile_output) this.saida.push({ t: 'error', m: deb64(d.compile_output) });
                    if (d.stderr) this.saida.push({ t: 'error', m: deb64(d.stderr) });
                    if (d.message) this.saida.push({ t: 'error', m: deb64(d.message) });
                    const st = d.status ? d.status.description : '';
                    this.saida.push({ t: 'info', m: '— ' + st + (d.time ? ' · ' + d.time + ' s' : '') + ' —' });
                } catch (e) {
                    if (n === token) this.saida.push({ t: 'error', m: 'Não consegui executar no servidor: ' + (e.name === 'AbortError' ? 'tempo esgotado' : e.message) });
                } finally { clearTimeout(tm); }
            },
            /** Monta a página com os .css e .js do projeto embutidos e mostra na prévia. */
            montarHtml(f, n) {
                const base = pai(f.caminho), achar = c => (c ? I.arquivos.find(x => x.caminho === c) : null);
                // embute @import "outro.css" de arquivos do projeto
                const imports = (css, b, nivel = 0) => nivel > 5 ? css : css.replace(/@import\s+(?:url\(\s*)?["']?([^"')\s;]+)["']?\s*\)?[^;]*;/gi, (m, href) => {
                    const a = achar(resolver(b, semConsulta(href)));
                    return a ? imports(a.texto, pai(a.caminho), nivel + 1) : m;
                });
                let h = f.texto.replace(RE_LINK, (m, href) => {
                    if (!/stylesheet/i.test(m)) return m;
                    const a = achar(resolver(base, semConsulta(href)));
                    return a ? '<style>' + imports(a.texto, pai(a.caminho)).replace(/<\/style/gi, '<\\/style') + '</style>' : m;
                });
                h = h.replace(RE_SCRIPT, (m, x, src, y) => {
                    const a = achar(resolver(base, semConsulta(src)));
                    return a ? '<script' + x + y + '>' + a.texto.replace(/<\/script/gi, '<\\/script') + '<\/script>' : m;
                });
                // "<head" também casava com "<header"; agora só a tag head de verdade
                const temHead = /<head(?:\s[^>]*)?>/i, temDoctype = /^\s*<!doctype[^>]*>/i;
                if (temHead.test(h)) h = h.replace(temHead, m => m + shim(n));
                else if (temDoctype.test(h)) h = h.replace(temDoctype, m => m + shim(n));
                else h = shim(n) + h;
                // sem doctype o navegador usa o modo antigo (quirks) e o layout muda
                return temDoctype.test(h) ? h : '<!doctype html>' + h;
            },
            rodarHtml(f) {
                const n = ++token;
                if (this.painel !== 'previa' && this.altPainel < 340) { this.altPainel = 340; this.gravarLayout(); } // abre a prévia com altura útil
                htmlId = f.id;
                this.lv.ativo = false;
                this.saida = [];
                this.srcdoc = this.montarHtml(f, n);
                this.painel = 'previa';
            },
            telaCheiaPrevia() {
                const fr = this.$refs.frame;
                if (fr && fr.requestFullscreen) fr.requestFullscreen().catch(() => this.aviso('Não consegui abrir em tela cheia.'));
            },
            /** Com a prévia aberta, qualquer modificação no projeto atualiza a página sozinha. */
            atualizarPrevia() {
                // app Laravel aberto: ao editar, a página atual é pedida de novo (só GET; um POST não é repetido)
                if (this.lv.ativo && this.painel === 'previa') {
                    clearTimeout(timerPrev);
                    timerPrev = setTimeout(() => { if (this.lv.ativo && this.painel === 'previa' && !this.lv.carregando) this.rodarLaravel({ url: this.lv.url }, { semHist: true }); }, 900);
                    return;
                }
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
    /* Versão do editor: o bloco (blocos.blade.php) confere este número e avisa se o navegador carregou um ide.js antigo. */
    WFIde.versao = 5;
    window.WFIde = WFIde;
})();
</script>
@endverbatim
{{-- ?v= força o navegador a baixar de novo quando o arquivo muda; sem filemtime usa a versão fixa. --}}
<script>
    // Confere se o editor carregado é o que este bloco espera (versão 5 ou mais nova).
    if (!window.WFIde || (window.WFIde.versao || 0) < 5) {
        console.error('[Work Flower] O editor carregado está desatualizado (versão ' + ((window.WFIde && window.WFIde.versao) || 'antiga') + ', esperada 5+). Recarregue a página com Ctrl+F5.');
    }
    if (!window.WFEscrita) {
        console.error('[Work Flower] public/js/escrita.js não foi carregado (editor de texto indisponível). Confira se o arquivo existe em public/js/ e recarregue com Ctrl+F5.');
    }
</script>
<style>
    .wf-repo { border: 1px solid var(--linha); border-left: 4px solid var(--prim); border-radius: .6rem; padding: .6rem .7rem; background: var(--superficie-2); display: grid; gap: .5rem; }
    .wf-repo.invalido { border-left-color: #f87171; }
    .wf-repo-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .wf-repo-linha .wf-cel { flex: 1; min-width: 8rem; background: var(--superficie); border: 1px solid var(--linha); }
    .wf-repo-linha .wf-repo-ramo { flex: 0 0 8rem; min-width: 6rem; }
    .wf-repo-selo { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .5rem; font-size: .7rem; border-radius: 9999px; border: 1px solid var(--prim-linha); color: var(--prim-forte); background: var(--superficie); white-space: nowrap; }
    .wf-repo-aviso { font-size: .72rem; color: #f87171; }
    .wf-repo-acoes { display: flex; flex-wrap: wrap; gap: .35rem; padding-top: .4rem; border-top: 1px solid var(--linha); }
    .wf-repo-acoes .btn-sec { padding: .25rem .6rem; font-size: .75rem; }

    /* Lista de itens: o texto quebra para a linha de baixo (o campo cresce junto) em vez de ser cortado */
    .wf-item-txt { display: block; flex: 1; min-width: 0; resize: none; overflow: hidden; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; line-height: 1.35; min-height: 1.9rem; field-sizing: content; }

    /* Botão de recolher/mostrar opções (campo de texto e mapa mental) */
    .wf-opc-btn { display: inline-flex; align-items: center; gap: .35rem; }
    .wf-opc-btn svg { width: .85rem; height: .85rem; flex-shrink: 0; transition: transform .15s; }
    .wf-opc-btn.fechada svg { transform: rotate(-90deg); }

    /* ===== Editor de código (mini-IDE) ===== */
    .wf-ide { position: relative; display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 24rem; min-width: 0; }
    .wf-ide-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-ide-barra .btn-sec { padding: .2rem .55rem; font-size: .75rem; cursor: pointer; }
    .wf-ide-barra .sep { width: 1px; align-self: stretch; background: var(--linha); margin: 0 .15rem; }
    /* colunas: lista de arquivos (largura ajustável) | divisor | editor.
       grid-template-rows: minmax(0, 1fr) impede que a linha cresça com o conteúdo (era isso que fazia o console vazar). */
    .wf-ide-corpo { flex: 1; min-height: 0; display: grid; grid-template-columns: var(--lado, 208px) 8px minmax(0, 1fr); grid-template-rows: minmax(0, 1fr); gap: 0; }
    .wf-ide-lado { border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); overflow: auto; padding: .3rem 0; font-size: .8rem; min-height: 0; }
    .wf-ide-no { display: flex; align-items: center; gap: .3rem; padding: .15rem .4rem; cursor: pointer; white-space: nowrap; color: var(--tinta); }
    .wf-ide-no:hover { background: var(--superficie); }
    .wf-ide-no.ativo { background: var(--prim-linha); }
    .wf-ide-no.sel { outline: 1px solid var(--prim-linha); outline-offset: -1px; }
    /* arrastar e soltar: pasta que vai receber, item sendo levado e a raiz (espaço vazio da lista) */
    .wf-ide-no.alvo { background: var(--prim-linha); outline: 1px dashed var(--prim); outline-offset: -1px; }
    .wf-ide-no.arrastando { opacity: .45; }
    .wf-ide-lado.alvo-raiz { outline: 1px dashed var(--prim); outline-offset: -2px; }
    .wf-ide-no .nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .wf-ide-no .seta { width: .8rem; color: var(--tinta-2); font-size: .65rem; text-align: center; flex-shrink: 0; }
    .wf-ide-no .tipo { flex-shrink: 0; min-width: 1.5rem; padding: 0 .25rem; text-align: center; font-size: .58rem; font-weight: 700; letter-spacing: .02em; color: var(--tinta-2); border: 1px solid var(--linha); border-radius: .25rem; }
    .wf-ide-no .acoes { display: none; gap: .1rem; }
    .wf-ide-no:hover .acoes { display: flex; }
    .wf-ide-res { padding: .2rem .5rem; font-size: .72rem; cursor: pointer; border-top: 1px solid var(--linha); }
    .wf-ide-res:hover { background: var(--superficie); }
    /* overflow: hidden mantém editor + console dentro do cartão */
    .wf-ide-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; gap: .4rem; overflow: hidden; }

    /* divisores para redimensionar (lista de arquivos e console/prévia) */
    .wf-ide-div { position: relative; flex-shrink: 0; touch-action: none; outline: none; }
    .wf-ide-div.v { width: 8px; cursor: col-resize; }
    .wf-ide-div.h { height: 8px; cursor: row-resize; }
    .wf-ide-div::after { content: ""; position: absolute; background: var(--linha); border-radius: 9999px; transition: background .15s; }
    .wf-ide-div.v::after { left: 3px; right: 3px; top: 0; bottom: 0; }
    .wf-ide-div.h::after { top: 3px; bottom: 3px; left: 0; right: 0; }
    .wf-ide-div:hover::after, .wf-ide-div:focus-visible::after, .wf-ide.movendo .wf-ide-div::after { background: var(--prim); }
    .wf-ide.movendo { user-select: none; }
    .wf-ide-velho { padding: .4rem .6rem; font-size: .75rem; color: #fecaca; background: rgba(248, 113, 113, .12); border: 1px solid #f87171; border-radius: .4rem; }
    .wf-ide.movendo iframe { pointer-events: none; }

    .wf-ide-abas { display: flex; gap: .2rem; overflow-x: auto; flex-shrink: 0; }
    .wf-ide-aba { display: flex; align-items: center; gap: .4rem; padding: .25rem .55rem; font-size: .75rem; border: 1px solid var(--linha); border-bottom: 2px solid transparent; border-radius: .4rem .4rem 0 0; background: var(--superficie-2); cursor: pointer; white-space: nowrap; color: var(--tinta-2); }
    .wf-ide-aba.ativo { color: var(--tinta); border-bottom-color: var(--prim); background: var(--superficie); }
    .wf-ide-aba .ponto { width: .5rem; height: .5rem; border-radius: 9999px; background: var(--prim); flex-shrink: 0; }
    .wf-ide-aba .x { opacity: .6; }
    .wf-ide-aba .x:hover { opacity: 1; color: #f87171; }
    /* o editor cede espaço ao console (flex: 1 1 0) mas nunca some (min-height) */
    .wf-ide-ed { position: relative; flex: 1 1 0; min-height: 6rem; display: flex; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie); overflow: hidden; }
    .wf-ide-num { flex-shrink: 0; min-width: 2.6rem; padding: .6rem .5rem calc(2rem + 16px) .6rem; text-align: right; color: var(--tinta-2); font: 13px/1.5 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow: hidden; user-select: none; border-right: 1px solid var(--linha); background: var(--superficie-2); }
    .wf-ide-cod { position: relative; flex: 1; min-width: 0; }
    .wf-ide-pre, .wf-ide-ta { position: absolute; inset: 0; margin: 0; border: 0; font: 13px/1.5 ui-monospace, Menlo, Consolas, monospace; tab-size: 2; -moz-tab-size: 2; white-space: pre; letter-spacing: 0; padding: .6rem .6rem 2rem; }
    .wf-ide-pre { overflow: auto; scrollbar-width: none; pointer-events: none; color: var(--tinta); padding-right: calc(.6rem + 16px); padding-bottom: calc(2rem + 16px); }
    .wf-ide-pre::-webkit-scrollbar { display: none; }
    .wf-ide-ta { overflow: auto; background: transparent; color: transparent; -webkit-text-fill-color: transparent; caret-color: var(--tinta); resize: none; outline: none; }
    .wf-ide-ta::selection { background: rgba(99, 140, 255, .35); }
    .wf-ide-vazio { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; padding: 1rem; font-size: .85rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .4rem; }
    .wf-h-c { color: var(--tinta-2); font-style: italic; }
    .wf-h-s { color: #22c55e; }
    .wf-h-n { color: #f59e0b; }
    .wf-h-k { color: #a78bfa; }
    .wf-h-t { color: #38bdf8; }
    .wf-ide-busca { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; padding: .35rem; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie-2); font-size: .75rem; }
    .wf-ide-busca .campo { width: 9rem; padding: .25rem .5rem; font-size: .8rem; }
    .wf-ide-busca .btn-sec { padding: .15rem .5rem; font-size: .72rem; }
    .wf-ide-busca .btn-sec.on { background: var(--prim); color: var(--on-prim); border-color: var(--prim); }
    .wf-ide-busca .campo.erro { border-color: #f87171; }
    .wf-ide-no .ponto { width: .4rem; height: .4rem; border-radius: 9999px; background: var(--prim); flex-shrink: 0; }
    /* paleta: abrir arquivo (Ctrl+P) e ir para a linha (Ctrl+G) */
    .wf-ide-pal-fundo { position: absolute; inset: 0; z-index: 20; display: flex; justify-content: center; align-items: flex-start; padding-top: 2.5rem; background: rgba(0, 0, 0, .25); border-radius: .4rem; }
    .wf-ide-pal { width: min(26rem, 92%); display: flex; flex-direction: column; gap: .3rem; padding: .5rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    .wf-ide-pal .campo { width: 100%; }
    .wf-ide-pal-lista { max-height: 14rem; overflow: auto; }
    .wf-ide-pal-it { display: flex; align-items: baseline; gap: .5rem; padding: .25rem .45rem; border-radius: .35rem; font-size: .8rem; cursor: pointer; color: var(--tinta); }
    .wf-ide-pal-it small { color: var(--tinta-2); font-size: .68rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wf-ide-pal-it.sel, .wf-ide-pal-it:hover { background: var(--superficie-2); }
    .wf-ide-pal-dica { padding: .2rem .45rem; font-size: .7rem; color: var(--tinta-2); }
    /* menu do botão direito (lista de arquivos) */
    .wf-ide-ctx { position: absolute; z-index: 30; min-width: 12.5rem; padding: .25rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    .wf-ide-ctx-it { display: flex; align-items: center; justify-content: space-between; gap: .75rem; width: 100%; padding: .3rem .5rem; border: 0; border-radius: .35rem; background: none; color: var(--tinta); font-size: .8rem; text-align: left; cursor: pointer; }
    .wf-ide-ctx-it small { color: var(--tinta-2); font-size: .68rem; }
    .wf-ide-ctx-it:hover:not(:disabled) { background: var(--superficie-2); }
    .wf-ide-ctx-it:disabled { opacity: .45; cursor: default; }
    .wf-ide-ctx-sep { height: 1px; margin: .2rem 0; background: var(--linha); }
    /* console/prévia: ocupa a altura escolhida, mas encolhe (flex-shrink) se faltar espaço, sem passar do cartão */
    .wf-ide-painel { flex: 0 1 var(--painel, 176px); min-height: 4.5rem; display: flex; flex-direction: column; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie); overflow: hidden; }
    .wf-ide-painel-topo { flex-shrink: 0; display: flex; align-items: center; gap: .3rem; padding: .2rem .4rem; background: var(--superficie-2); border-bottom: 1px solid var(--linha); font-size: .72rem; }
    .wf-ide-painel-topo .t { padding: .1rem .5rem; border-radius: .3rem; cursor: pointer; color: var(--tinta-2); }
    .wf-ide-painel-topo .t.ativo { background: var(--superficie); color: var(--tinta); }
    .wf-ide-lvbar { flex-shrink: 0; display: flex; align-items: center; gap: .3rem; padding: .2rem .4rem; border-bottom: 1px solid var(--linha); font-size: .72rem; }
    .wf-ide-lvbar .campo { flex: 1; min-width: 4rem; padding: .1rem .4rem; font: 12px ui-monospace, monospace; }
    .wf-ide-lvbar .st { font: 11px ui-monospace, monospace; padding: 0 .3rem; border-radius: .3rem; background: color-mix(in srgb, #16a34a 18%, transparent); }
    .wf-ide-lvbar .st.ruim { background: color-mix(in srgb, #dc2626 22%, transparent); }
    .wf-ide-saida { flex: 1 1 0; min-height: 0; min-width: 0; overflow: auto; padding: .4rem .6rem; font: 12px/1.45 ui-monospace, Menlo, Consolas, monospace; }
    .wf-ide-log { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; border-bottom: 1px dashed var(--linha); padding: .1rem 0; }
    .wf-ide-log.l-error { color: #f87171; }
    .wf-ide-log.l-warn { color: #fbbf24; }
    .wf-ide-log.l-info { color: var(--tinta-2); }
    /* dica em português de por que o código deu erro (WFDicas) */
    .wf-ide-log.l-dica { color: var(--tinta); background: color-mix(in srgb, var(--prim) 12%, transparent); border: 1px solid var(--prim-linha); border-radius: .35rem; padding: .3rem .5rem; margin: .2rem 0; font-family: system-ui, sans-serif; font-size: 12.5px; }
    /* resultado de SELECT e tabelas do banco */
    .wf-ide-tab { margin: .3rem 0; border: 1px solid var(--linha); border-radius: .35rem; overflow: hidden; }
    .wf-ide-tab .cab { padding: .2rem .5rem; background: var(--superficie-2); color: var(--tinta-2); font-size: 11px; border-bottom: 1px solid var(--linha); }
    .wf-ide-tab .rolar { max-height: 16rem; overflow: auto; }
    .wf-ide-tab table { border-collapse: collapse; width: max-content; min-width: 100%; }
    .wf-ide-tab th, .wf-ide-tab td { border: 1px solid var(--linha); padding: .15rem .5rem; text-align: left; white-space: nowrap; max-width: 22rem; overflow: hidden; text-overflow: ellipsis; }
    .wf-ide-tab th { position: sticky; top: 0; background: var(--superficie-2); font-weight: 600; }
    .wf-ide-tab td.nulo { color: var(--tinta-2); font-style: italic; }
    .wf-ide-banco .lista { display: flex; flex-direction: column; gap: .25rem; }
    .wf-ide-banco .item { text-align: left; padding: .3rem .5rem; border: 1px solid var(--linha); border-radius: .35rem; background: var(--superficie-2); }
    .wf-ide-banco .item.sel { border-color: var(--prim); }
    .wf-ide-banco .item .cols { display: block; font-size: 11px; color: var(--tinta-2); white-space: normal; }
    .wf-ide-frame { flex: 1 1 0; min-height: 0; width: 100%; border: 0; background: #fff; }
    .wf-ide-status { display: flex; flex-wrap: wrap; gap: .3rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) {
        .wf-ide { min-height: 0; height: auto; }
        .wf-ide-corpo { grid-template-columns: 1fr; grid-template-rows: none; gap: .5rem; }
        .wf-ide-div.v { display: none; }
        .wf-ide-lado { max-height: 10rem; }
        .wf-ide-main { overflow: visible; }
        .wf-ide-ed { height: 22rem; flex: none; }
        .wf-ide-painel { flex: none; height: var(--painel, 176px); }
    }

    /* ===== Editor de escrita (estilo Notion) ===== */
    .wf-es { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 14rem; }
    .wf-es-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-es-barra .btn-sec { padding: .2rem .55rem; font-size: .75rem; cursor: pointer; min-width: 1.9rem; }
    .wf-es-barra .btn-sec:disabled { opacity: .45; cursor: default; }
    .wf-es-barra select.wf-link-sel { min-width: 8.5rem; cursor: pointer; }
    .wf-es-sep { width: 1px; align-self: stretch; min-height: 1.1rem; background: var(--linha); margin: 0 .1rem; }
    .wf-es-sum { border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); padding: .3rem; max-height: 8rem; overflow: auto; font-size: .8rem; }
    .wf-es-sum button { display: block; width: 100%; text-align: left; padding: .15rem .4rem; border-radius: .3rem; color: var(--tinta); cursor: pointer; background: none; border: 0; }
    .wf-es-sum button:hover { background: var(--superficie); }
    .wf-es-folha { position: relative; flex: 1 1 auto; min-height: 8rem; overflow: auto; display: flex; flex-direction: column; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); padding: .75rem .75rem .5rem .25rem; line-height: 1.6; color: var(--tinta); }
    .wf-es-folha:focus { outline: none; }
    /* Modo "texto inteiro": uma caixa só, já selecionada, para copiar tudo de uma vez (Ctrl+C) */
    .wf-es-todo { flex: 1; min-height: 10rem; display: flex; flex-direction: column; gap: .3rem; }
    .wf-es-todo-ta { flex: 1; width: 100%; min-height: 8rem; resize: none; padding: .6rem .75rem; font: 12.5px/1.55 ui-monospace, Menlo, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--tinta); background: var(--superficie); border: 1px solid var(--prim-linha); border-radius: .5rem; outline: none; }
    .wf-es-todo-ta::selection { background: rgba(99, 140, 255, .45); }
    .wf-es-bloco { position: relative; display: flex; align-items: flex-start; border-radius: .3rem; }
    .wf-es-bloco.selecionado { background: rgba(99, 140, 255, .2); box-shadow: 0 0 0 1px rgba(99, 140, 255, .45); }
    .wf-es-bloco.sobre { box-shadow: 0 -2px 0 0 var(--prim); }
    .wf-es-bloco.arrastando { opacity: .45; }
    .wf-es-lado { display: flex; flex-shrink: 0; width: 2.9rem; justify-content: flex-end; opacity: 0; transition: opacity .12s; }
    .wf-es-bloco:hover > .wf-es-lado, .wf-es-bloco.foco > .wf-es-lado { opacity: 1; }
    .wf-es-lbtn { width: 1.3rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: .8rem; line-height: 1; color: var(--tinta-2); border: 0; background: none; border-radius: .25rem; cursor: pointer; }
    .wf-es-lbtn:hover { background: var(--superficie-2); color: var(--tinta); }
    .wf-es-grip { cursor: grab; letter-spacing: -.15em; font-size: .7rem; }
    .wf-es-corpo { flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: .5rem; }
    .wf-es-txt { flex: 1; min-width: 0; }
    .wf-es-ver, .wf-es-ta { display: block; width: 100%; margin: 0; padding: .12rem 0; font: inherit; line-height: inherit; color: inherit; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; min-height: 1.6em; }
    .wf-es-ta { background: transparent; border: 0; border-radius: 0; outline: 0; box-shadow: none; resize: none; overflow: hidden; appearance: none; }
    .wf-es-ver { cursor: text; }
    .wf-es-ph { color: var(--tinta-2); opacity: .7; }
    .wf-es-marca { flex-shrink: 0; min-width: 1.2rem; text-align: center; color: var(--tinta-2); padding-top: .12rem; user-select: none; }
    .wf-es-chk { flex-shrink: 0; width: 1rem; height: 1rem; margin-top: .4em; accent-color: var(--prim); cursor: pointer; }
    .wf-es-hr { flex: 1; border: 0; border-top: 1px solid var(--linha); margin: .8rem 0; }
    .wf-es-corpo.t-h1 { margin-top: .6rem; } .wf-es-corpo.t-h1 .wf-es-txt { font-size: 1.75em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h2 { margin-top: .5rem; } .wf-es-corpo.t-h2 .wf-es-txt { font-size: 1.4em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h3 { margin-top: .4rem; } .wf-es-corpo.t-h3 .wf-es-txt { font-size: 1.15em; font-weight: 600; line-height: 1.35; }
    .wf-es-corpo.t-quote .wf-es-txt { border-left: 3px solid var(--tinta-2); padding-left: .8rem; font-style: italic; color: var(--tinta-2); }
    .wf-es-corpo.t-callout { background: var(--superficie-2); border: 1px solid var(--linha); border-left: 3px solid var(--prim); border-radius: .4rem; padding: .4rem .7rem; }
    .wf-es-corpo.t-code .wf-es-txt { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .4rem; padding: .3rem .6rem .4rem; }
    .wf-es-corpo.t-code .wf-es-ta { font: 12.5px/1.5 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow-x: auto; tab-size: 2; min-height: 3em; }
    .wf-es-lang { width: 8rem; margin-bottom: .2rem; padding: 0; font-size: .68rem; color: var(--tinta-2); background: transparent; border: 0; outline: 0; }
    .wf-es-corpo.feito .wf-es-txt, .wf-es-corpo.feito .wf-es-ta { text-decoration: line-through; color: var(--tinta-2); }
    .wf-es-ver a { color: var(--prim-forte); text-decoration: underline; }
    .wf-es-ver mark { background: rgba(250, 204, 21, .35); color: inherit; border-radius: .2rem; padding: 0 .1em; }
    .wf-es-cod { font: .88em ui-monospace, Menlo, Consolas, monospace; background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .25rem; padding: 0 .3em; }
    .wf-es-menu { position: absolute; left: 2.9rem; top: 100%; z-index: 30; margin-top: .15rem; width: 17rem; max-height: 15rem; overflow: auto; padding: .3rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    /* menu do botão ⋮⋮ (ações e "Transformar em") */
    .wf-es-menu-bl { left: .3rem; top: 1.6rem; width: 13rem; max-height: 19rem; margin-top: 0; }
    .wf-es-menu-bl .menu-item:disabled { opacity: .45; cursor: default; }
    .wf-es-menu-tit { margin: .3rem 0 .15rem; padding: .25rem .5rem 0; border-top: 1px solid var(--linha); font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--tinta-2); }
    .wf-es-menu-tp-it { display: flex; align-items: center; gap: .5rem; }
    .wf-es-menu-tp-it.atual { background: var(--superficie-2); font-weight: 600; }
    .wf-es-menu-tp-it .wf-es-ic { width: 1.4rem; height: 1.4rem; font-size: .65rem; }
    /* barra flutuante ao selecionar texto ou blocos */
    .wf-es-bolha { position: absolute; left: 2.9rem; top: 100%; z-index: 25; display: flex; align-items: center; gap: .1rem; margin-top: .2rem; padding: .2rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .5rem; box-shadow: 0 8px 24px rgba(0, 0, 0, .4); font-size: .8rem; line-height: 1.2; }
    .wf-es-bb { min-width: 1.7rem; padding: .2rem .45rem; border: 0; border-radius: .3rem; background: none; color: var(--tinta); cursor: pointer; font-size: .8rem; }
    .wf-es-bb:hover { background: var(--superficie-2); }
    .wf-es-bb-tipo { font-weight: 600; white-space: nowrap; }
    .wf-es-bsep { width: 1px; align-self: stretch; margin: 0 .15rem; background: var(--linha); }
    .wf-es-menu-tp { left: 0; top: 100%; width: 14rem; margin-top: .25rem; }
    .wf-es-dobra { flex-shrink: 0; width: 1.2rem; height: 1.4em; padding: 0; border: 0; background: none; color: var(--tinta-2); cursor: pointer; opacity: .35; transition: opacity .12s, transform .12s; font-size: .8rem; font-weight: 400; line-height: 1.6; }
    .wf-es-bloco:hover .wf-es-dobra, .wf-es-dobra.fechada { opacity: 1; }
    .wf-es-dobra.fechada { transform: rotate(-90deg); }
    .wf-es-op { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .3rem .45rem; border: 0; border-radius: .4rem; text-align: left; font-size: .8rem; color: var(--tinta); background: none; cursor: pointer; }
    .wf-es-op small { display: block; font-size: .68rem; color: var(--tinta-2); }
    .wf-es-op.sel, .wf-es-op:hover { background: var(--superficie-2); }
    .wf-es-ic { width: 1.7rem; height: 1.7rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; border: 1px solid var(--linha); border-radius: .35rem; background: var(--superficie-2); }
    .wf-es-vazio { padding: .4rem; font-size: .75rem; color: var(--tinta-2); }
    .wf-es-fim { flex: 1; min-height: 3rem; cursor: text; }
    /* Imagem no meio do texto */
    .wf-es-corpo.t-img { display: block; }
    .wf-es-fig { margin: .35rem 0; display: flex; flex-direction: column; align-items: center; gap: .15rem; }
    .wf-es-fig.al-e { align-items: flex-start; }
    .wf-es-fig.al-d { align-items: flex-end; }
    .wf-es-fig-caixa { position: relative; min-width: 3rem; max-width: 100%; border-radius: .4rem; cursor: pointer; }
    .wf-es-fig-caixa img { display: block; width: 100%; height: auto; border-radius: .4rem; user-select: none; }
    .wf-es-bloco.selecionado .wf-es-fig-caixa { outline: 2px solid var(--prim); outline-offset: 2px; }
    .wf-es-fig-vazio { display: flex; align-items: center; justify-content: center; min-height: 6rem; padding: 1rem; font-size: .8rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .4rem; background: var(--superficie-2); }
    .wf-es-fig-barra { position: absolute; top: .35rem; right: .35rem; display: flex; align-items: center; gap: .1rem; padding: .15rem; border-radius: .45rem; background: rgba(15, 18, 28, .82); color: #fff; opacity: 0; pointer-events: none; transition: opacity .12s; font-size: .72rem; }
    .wf-es-fig-caixa:hover .wf-es-fig-barra, .wf-es-bloco.selecionado .wf-es-fig-barra { opacity: 1; pointer-events: auto; }
    .wf-es-fig-barra button { min-width: 1.6rem; padding: .15rem .3rem; border: 0; border-radius: .3rem; background: none; color: inherit; cursor: pointer; line-height: 1.1; }
    .wf-es-fig-barra button:hover { background: rgba(255, 255, 255, .15); }
    .wf-es-fig-barra button.sel { background: rgba(99, 140, 255, .55); }
    .wf-es-fig-barra .wf-es-bsep { width: 1px; align-self: stretch; background: rgba(255, 255, 255, .25); margin: 0 .15rem; }
    .wf-es-fig-puxa { position: absolute; right: -5px; bottom: -5px; width: 12px; height: 12px; border-radius: 3px; background: var(--prim); border: 2px solid var(--superficie); cursor: nwse-resize; opacity: 0; touch-action: none; }
    .wf-es-fig-puxa.esq { right: auto; left: -5px; cursor: nesw-resize; }
    .wf-es-fig-caixa:hover .wf-es-fig-puxa, .wf-es-bloco.selecionado .wf-es-fig-puxa { opacity: 1; }
    .wf-es-leg { max-width: 100%; min-width: 8rem; padding: .1rem .2rem; font-size: .8em; text-align: center; color: var(--tinta-2); background: transparent; border: 0; outline: 0; }
    .wf-es-fig.al-e .wf-es-leg { text-align: left; }
    .wf-es-fig.al-d .wf-es-leg { text-align: right; }
    .wf-es-leg:placeholder-shown { opacity: 0; }
    .wf-es-bloco:hover .wf-es-leg:placeholder-shown, .wf-es-leg:focus { opacity: .8; }
    /* Modo foco: sem barras, texto centralizado, linhas fora do cursor apagadas (tela cheia quando o navegador deixa) */
    .wf-es-sair-foco { display: none; }
    .wf-es.wf-es-foco { position: relative; background: var(--superficie); }
    .wf-es.wf-es-foco:fullscreen { padding: 2.5rem 1rem 1rem; overflow: auto; }
    .wf-es.wf-es-foco > .wf-es-barra, .wf-es.wf-es-foco > .wf-es-sum { display: none !important; }
    .wf-es.wf-es-foco .wf-es-sair-foco { display: inline-flex; position: absolute; top: .4rem; right: .4rem; z-index: 40; padding: .2rem .6rem; font-size: .75rem; opacity: .55; }
    .wf-es.wf-es-foco .wf-es-sair-foco:hover { opacity: 1; }
    .wf-es.wf-es-foco .wf-es-folha { border-color: transparent; background: transparent; padding-top: 1.5rem; }
    .wf-es.wf-es-foco .wf-es-folha > .wf-es-bloco, .wf-es.wf-es-foco .wf-es-fim { width: 100%; max-width: 46rem; margin-left: auto; margin-right: auto; }
    .wf-es.wf-es-foco .wf-es-folha:focus-within > .wf-es-bloco:not(.foco):not(.selecionado) { opacity: .38; transition: opacity .2s; }
    .wf-es.wf-es-foco .wf-es-rodape { justify-content: center; opacity: .6; }
    .wf-es-rodape { display: flex; flex-wrap: wrap; gap: .2rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) {
        .wf-es { height: auto; min-height: 0; }
        .wf-es-folha { min-height: 14rem; max-height: 70vh; }
        .wf-es-lado { opacity: .55; width: 2.6rem; }
    }
</style>
<script>
    // Funções puras do bloco de código (não dependem do Alpine nem do diverso.js).
    window.WFRepo = {
        _dono: /^[A-Za-z0-9-]{1,39}$/,
        _repo: /^[A-Za-z0-9._-]{1,100}$/,
        /** Aceita "dono/repo", URL do GitHub (com /tree/..., ?..., .git) ou git@github.com:dono/repo.git. */
        parse(v) {
            let s = String(v || '').trim()
                .replace(/^git@github\.com:/i, '')
                .replace(/^(?:https?:\/\/)?(?:www\.)?github\.com\//i, '')
                .replace(/[?#].*$/, '');
            const p = s.split('/').filter(Boolean);
            if (p.length < 2) return null;
            const dono = p[0], repo = p[1].replace(/\.git$/i, '');
            if (!this._dono.test(dono) || !this._repo.test(repo) || repo === '.' || repo === '..') return null;
            return { dono, repo };
        },
        ok(v) { return !!this.parse(v); },
        slug(r) { const p = this.parse(r && r.nome); return p ? p.dono + '/' + p.repo : ''; },
        ramo(r) {
            const b = String((r && r.ramo) || '').trim().replace(/[^A-Za-z0-9._\/-]/g, '');
            return b.includes('..') ? '' : b.replace(/^\/+|\/+$/g, '');
        },
        base(r) { const s = this.slug(r); return s ? 'https://github.com/' + s : ''; },
        /** Endereços seguros; '#' quando o repositório não é válido. */
        url(r, tipo) {
            const s = this.slug(r);
            if (!s) return '#';
            const b = this.ramo(r), tree = b ? '/tree/' + b : '';
            switch (tipo) {
                case 'issues': return 'https://github.com/' + s + '/issues';
                case 'pulls': return 'https://github.com/' + s + '/pulls';
                case 'actions': return 'https://github.com/' + s + '/actions';
                case 'web': return 'https://vscode.dev/github/' + s + tree;
                case 'dev': return 'https://github.dev/' + s + tree;
                case 'clone': return 'vscode://vscode.git/clone?url=' + encodeURIComponent('https://github.com/' + s + '.git');
                default: return 'https://github.com/' + s + tree;
            }
        },
        cmd(r) {
            const s = this.slug(r);
            if (!s) return '';
            const b = this.ramo(r);
            return 'git clone ' + (b ? '--branch ' + b + ' ' : '') + 'https://github.com/' + s + '.git';
        },
        local(r) {
            const p = String((r && r.pasta) || '').trim();
            if (!p) return '#';
            return 'vscode://file/' + encodeURI(p.replace(/\\/g, '/')).replace(/#/g, '%23').replace(/\?/g, '%3F');
        },
        async copiar(t) {
            t = String(t || '');
            if (!t) return false;
            try { await navigator.clipboard.writeText(t); return true; } catch { /* tenta o método antigo */ }
            try {
                const a = document.createElement('textarea');
                a.value = t; a.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(a); a.select();
                const ok = document.execCommand('copy');
                a.remove();
                return ok;
            } catch { return false; }
        },
    };
</script>

<template x-for="b in blocosDe(aba)" :key="b.id">
    <x-diverso.item sec="b.secao" bid="b.id" :minw="220" :minh="140" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco">
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="flex flex-col h-full gap-3">
            <div class="flex-1 min-h-0">

                {{-- Texto: editor por blocos (lógica em public/js/escrita.js; o conteúdo fica em b.dados.texto como Markdown) --}}
                {{-- Pasta: guarda outros cartões (partials/pasta-cartao) --}}
                <template x-if="b.tipo === 'pasta'">
                    @include('partials.pasta-cartao')
                </template>
                {{-- Editor de imagem com camadas (partials/editor-imagem-cartao; editor em js/editor-imagem.js) --}}
                <template x-if="b.tipo === 'editorimg'">
                    @include('partials.editor-imagem-cartao')
                </template>
                {{-- Editor de vídeo (partials/editor-video-cartao; editor em js/editor-video.js) --}}
                <template x-if="b.tipo === 'editorvid'">
                    @include('partials.editor-video-cartao')
                </template>
                {{-- Estúdio de música (partials/estudio-musica-cartao; estúdio em js/estudio-musica.js) --}}
                <template x-if="b.tipo === 'musica'">
                    @include('partials.estudio-musica-cartao')
                </template>
                {{-- Gráfico e Hábitos (partials/grafico-cartao e habitos-cartao; js/graficos.js) --}}
                <template x-if="b.tipo === 'grafico'">
                    @include('partials.grafico-cartao')
                </template>
                <template x-if="b.tipo === 'habitos'">
                    @include('partials.habitos-cartao')
                </template>
                {{-- Documento (vem da Mente; partials/documento-cartao, js/documento-cartao.js) --}}
                <template x-if="b.tipo === 'documento'">
                    @include('partials.documento-cartao')
                </template>
                {{-- Editor de PDF (partials/editor-pdf-cartao; editor em js/editor-pdf.js) --}}
                <template x-if="b.tipo === 'editorpdf'">
                    @include('partials.editor-pdf-cartao')
                </template>
                {{-- Questões de concurso, Criador de conteúdo e RH (partials/*-cartao; js/cartoes-extra.js) --}}
                <template x-if="b.tipo === 'questoes'">
                    @include('partials.questoes-cartao')
                </template>
                <template x-if="b.tipo === 'criador'">
                    @include('partials.criador-cartao')
                </template>
                {{-- Google Drive, Gmail, YouTube e Lembretes por e-mail (partials/integracoes-cartoes; js/integracoes-cartoes.js) --}}
                @include('partials.integracoes-cartoes')
                <template x-if="b.tipo === 'rh'">
                    @include('partials.rh-cartao')
                </template>
                <template x-if="b.tipo === 'cenas'">
                    @include('partials.cenas-cartao')
                </template>
                {{-- Conversa com o Claude, que muda os cartões pedindo permissão (partials/claude-cartao; js/claude.js) --}}
                <template x-if="b.tipo === 'claude'">
                    @include('partials.claude-cartao')
                </template>

                <template x-if="b.tipo === 'texto'">
                    <div class="wf-es" x-data="WFEscrita(b, $data)" x-init="iniciar()" :class="{ 'wf-es-foco': modoFoco }"
                        @click.outside="foco = null; menu = null; opcBl = null; sumario = false; tipoMenu = false; limparSel()">

                        {{-- Imagens no meio do texto: o botão "Imagem", o comando "/imagem", colar (Ctrl+V) e arrastar usam este campo --}}
                        <input type="file" accept="image/*" multiple class="hidden wf-es-arq" tabindex="-1" aria-hidden="true"
                               @change="escolhidas($event.target.files); $event.target.value = ''">
                        <button type="button" class="btn-sec wf-es-sair-foco" x-show="modoFoco" x-cloak @click="alternarFoco()" title="Sair do modo foco">Sair do foco</button>

                        <div class="wf-es-barra">
                            <button type="button" class="btn-sec wf-opc-btn" :class="{ 'fechada': b.dados.opcoes === false }"
                                    @click="b.dados.opcoes = (b.dados.opcoes === false); sumario = false"
                                    :aria-expanded="b.dados.opcoes !== false"
                                    :title="b.dados.opcoes === false ? 'Mostrar as opções' : 'Recolher as opções'">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                <span x-text="b.dados.opcoes === false ? 'Mostrar opções' : 'Recolher opções'"></span>
                            </button>
                            <button type="button" class="btn-sec" :disabled="!podeDesfazer()" @click="desfazer()" title="Desfazer (Ctrl+Z)" aria-label="Desfazer">↶</button>
                            <button type="button" class="btn-sec" :disabled="!podeRefazer()" @click="refazer()" title="Refazer (Ctrl+Shift+Z)" aria-label="Refazer">↷</button>
                            <button type="button" class="btn-sec" @click="todo = !todo"
                                    :aria-pressed="todo"
                                    title="Mostra o texto inteiro numa caixa só, já selecionado, para copiar com Ctrl+C (atalho: Ctrl+A três vezes)"
                                    x-text="todo ? 'Voltar ao editor' : 'Selecionar tudo'"></button>
                            <button type="button" class="btn-sec" @click="copiar()" title="Copiar o texto inteiro (Markdown) para a área de transferência">Copiar tudo</button>
                            <button type="button" class="btn-sec" @click="alternarFoco()" :aria-pressed="modoFoco"
                                    title="Modo foco: tela cheia, sem barras e com a linha atual em destaque">Modo foco</button>
                        </div>

                        <div class="wf-es-barra" role="toolbar" aria-label="Ferramentas de escrita" x-show="b.dados.opcoes !== false && !todo" x-cloak>
                            {{-- As opções usam :selected (e não :value no select): o valor era aplicado antes de as opções existirem e o campo ficava vazio. --}}
                            <select class="campo wf-link-sel" aria-label="Tipo do bloco" title="Transforma a linha (ou as linhas selecionadas) · Ctrl+Alt+1/2/3 = títulos"
                                    @change="transformar($event.target.value)">
                                <option value="" :selected="tipoAtual() === ''" disabled hidden>Misto</option>
                                <template x-for="t in tipos" :key="t.id">
                                    <option :value="t.id" :selected="tipoAtual() === t.id" x-text="t.nome"></option>
                                </template>
                            </select>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" style="font-weight:700" :disabled="semAlvo()" @mousedown.prevent @click="envolver('**', '**')" title="Negrito (Ctrl+B)">N</button>
                            <button type="button" class="btn-sec" style="font-style:italic" :disabled="semAlvo()" @mousedown.prevent @click="envolver('*', '*')" title="Itálico (Ctrl+I)">I</button>
                            <button type="button" class="btn-sec" style="text-decoration:line-through" :disabled="semAlvo()" @mousedown.prevent @click="envolver('~~', '~~')" title="Tachado (Ctrl+Shift+X)">S</button>
                            <button type="button" class="btn-sec" :disabled="semAlvo()" @mousedown.prevent @click="envolver('==', '==')" title="Marca-texto (Ctrl+Shift+H)"><mark style="background:rgba(250,204,21,.45);color:inherit;padding:0 .2em;border-radius:.2rem">A</mark></button>
                            <button type="button" class="btn-sec" :disabled="semAlvo()" @mousedown.prevent @click="envolver('`', '`')" title="Código em linha (Ctrl+E)">&lt;/&gt;</button>
                            <button type="button" class="btn-sec" :disabled="!ultimo()" @mousedown.prevent @click="link()" title="Link (Ctrl+K)">Link</button>
                            <button type="button" class="btn-sec" @mousedown.prevent @click="escolherImagem()" title="Inserir imagem abaixo da linha atual (também dá para colar com Ctrl+V ou arrastar para o texto)">🖼 Imagem</button>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!titulos().length" @click="sumario = !sumario" :aria-expanded="sumario" title="Sumário pelos títulos">Sumário</button>
                            <button type="button" class="btn-sec" x-show="recolhidos.length" x-cloak @click="recolhidos = []" title="Expandir todas as seções">Expandir seções</button>
                            <select class="campo wf-link-sel" x-model="b.dados.tam" aria-label="Tamanho da letra">
                                <option value="p">Letra pequena</option>
                                <option value="m">Letra média</option>
                                <option value="g">Letra grande</option>
                            </select>
                            <span class="wf-es-sep"></span>
                            <label class="btn-sec" title="Importar um arquivo .md ou .txt (substitui o texto)">Importar
                                <input type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" class="hidden"
                                    @change="importar($event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="baixar()" title="Baixar como Markdown (.md)">Exportar .md</button>
                        </div>

                        <div class="wf-es-sum" x-show="sumario && b.dados.opcoes !== false && !todo" x-cloak>
                            <template x-for="o in titulos()" :key="o.x.id">
                                <button type="button" :style="`padding-left:${(Number(o.x.t.charAt(1)) - 1) * .8 + .4}rem`"
                                        @click="irPara(o.x.id)" x-text="o.x.x || '(sem título)'"></button>
                            </template>
                        </div>

                        <template x-if="todo">
                            <div class="wf-es-todo">
                                <p class="wf-es-vazio" style="padding:0">Texto inteiro selecionado: pressione <b>Ctrl+C</b> para copiar tudo. <b>Esc</b> volta ao editor.</p>
                                <textarea class="wf-es-todo-ta" readonly aria-label="Texto inteiro, selecionado para copiar"
                                          :value="b.dados.texto || ''"
                                          x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                          @keydown.escape.prevent="todo = false"
                                          @click="$el.select()"></textarea>
                            </div>
                        </template>

                        {{-- A folha é focável (tabindex) para receber o teclado quando há blocos selecionados --}}
                        <div class="wf-es-folha" tabindex="-1" x-show="!todo" :style="{ fontSize: { p: '.8rem', m: '.95rem', g: '1.2rem' }[b.dados.tam] || '.95rem' }"
                             @keydown.capture="ctrlA($event)" @keydown.self="teclaSel($event)">
                            <template x-for="(bl, i) in blocos" :key="bl.id">
                                <div class="wf-es-bloco" :data-es="bl.id" x-show="visivel(i)"
                                    :class="{ 'foco': foco === bl.id, 'selecionado': ehSel(bl.id), 'sobre': sobre === i && arrastar !== bl.id, 'arrastando': arrastar === bl.id }"
                                    :draggable="arrastar === bl.id ? 'true' : 'false'"
                                    @dragstart="dragIni($event, bl)" @dragover.prevent="sobre = i" @drop.prevent="soltarEm($event, i)"
                                    @dragend="arrastar = null; sobre = -1">

                                    <div class="wf-es-lado">
                                        <button type="button" class="wf-es-lbtn" title="Inserir bloco abaixo" aria-label="Inserir bloco abaixo" @click="addAbaixo(i)">+</button>
                                        <button type="button" class="wf-es-lbtn wf-es-grip" title="Clique: selecionar e abrir opções · Shift+clique: selecionar vários · arraste para mover" aria-label="Opções do bloco"
                                                @mousedown="arrastar = bl.id" @mouseup="arrastar = null"
                                                @click="grip($event, bl)">⋮⋮</button>
                                    </div>

                                    {{-- Menu do bloco: age sobre todos os blocos selecionados --}}
                                    <div class="wf-es-menu wf-es-menu-bl" x-show="opcBl === bl.id" x-cloak role="menu">
                                        <button type="button" class="menu-item" role="menuitem" :disabled="!podeMover(-1)" @click="moverSel(-1); opcBl = null">Subir</button>
                                        <button type="button" class="menu-item" role="menuitem" :disabled="!podeMover(1)" @click="moverSel(1); opcBl = null">Descer</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="duplicarSel(); opcBl = null">Duplicar</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="removerSel(); opcBl = null">Excluir</button>
                                        <p class="wf-es-menu-tit" x-text="selecao.length > 1 ? 'Transformar ' + selecao.length + ' blocos em' : 'Transformar em'"></p>
                                        <template x-for="t in tipos" :key="t.id">
                                            <button type="button" class="menu-item wf-es-menu-tp-it" role="menuitem" :class="{ 'atual': tipoAtual() === t.id }" @click="transformar(t.id)">
                                                <span class="wf-es-ic" x-text="t.ic"></span><span x-text="t.nome"></span>
                                            </button>
                                        </template>
                                    </div>

                                    {{-- Barra flutuante: aparece ao selecionar texto ou blocos --}}
                                    <div class="wf-es-bolha" x-show="bolhaEm(bl.id)" x-cloak role="toolbar" aria-label="Formatar seleção" @mousedown.prevent>
                                        <div class="relative">
                                            <button type="button" class="wf-es-bb wf-es-bb-tipo" :aria-expanded="tipoMenu" @click="tipoMenu = !tipoMenu" title="Transformar em…">
                                                <span x-text="rotuloTipo()"></span> <span aria-hidden="true">▾</span>
                                            </button>
                                            <div class="wf-es-menu wf-es-menu-tp" x-show="tipoMenu" x-cloak role="menu">
                                                <template x-for="t in tipos" :key="t.id">
                                                    <button type="button" class="wf-es-op" role="menuitem" :class="{ 'sel': tipoAtual() === t.id }" @click="transformar(t.id)">
                                                        <span class="wf-es-ic" x-text="t.ic"></span><span x-text="t.nome"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        <template x-if="podeFormatar()">
                                            <span class="contents">
                                                <span class="wf-es-bsep"></span>
                                                <button type="button" class="wf-es-bb" style="font-weight:700" @click="envolver('**', '**')" title="Negrito (Ctrl+B)">N</button>
                                                <button type="button" class="wf-es-bb" style="font-style:italic" @click="envolver('*', '*')" title="Itálico (Ctrl+I)">I</button>
                                                <button type="button" class="wf-es-bb" style="text-decoration:line-through" @click="envolver('~~', '~~')" title="Tachado (Ctrl+Shift+X)">S</button>
                                                <button type="button" class="wf-es-bb" @click="envolver('==', '==')" title="Marca-texto (Ctrl+Shift+H)"><mark style="background:rgba(250,204,21,.45);color:inherit;padding:0 .2em;border-radius:.2rem">A</mark></button>
                                                <button type="button" class="wf-es-bb" @click="envolver('`', '`')" title="Código em linha (Ctrl+E)">&lt;/&gt;</button>
                                                <button type="button" class="wf-es-bb" x-show="!selecao.length" @click="link()" title="Link (Ctrl+K)">Link</button>
                                            </span>
                                        </template>
                                    </div>

                                    <div class="wf-es-corpo" :class="'t-' + bl.t + (bl.f ? ' feito' : '')" :style="`margin-left:${(bl.n || 0) * 1.5}rem`">
                                        <template x-if="/^h[1-3]$/.test(bl.t)">
                                            <button type="button" class="wf-es-dobra" :class="{ 'fechada': recolhidos.includes(bl.id) }" @click="dobrar(bl)"
                                                    :aria-expanded="!recolhidos.includes(bl.id)" title="Recolher ou expandir a seção" aria-label="Recolher ou expandir a seção">▾</button>
                                        </template>
                                        <template x-if="bl.t === 'ul'"><span class="wf-es-marca" x-text="['•', '◦', '▪', '•'][bl.n || 0]"></span></template>
                                        <template x-if="bl.t === 'ol'"><span class="wf-es-marca" x-text="numero(i) + '.'"></span></template>
                                        <template x-if="bl.t === 'todo'"><input type="checkbox" class="wf-es-chk" :checked="bl.f" @change="bl.f = $event.target.checked" aria-label="Tarefa concluída"></template>
                                        <template x-if="bl.t === 'callout'"><span class="wf-es-marca">💡</span></template>

                                        <template x-if="bl.t === 'hr'"><hr class="wf-es-hr"></template>

                                        {{-- Imagem: clique seleciona (Delete apaga, Enter cria uma linha abaixo); barra com alinhamento e tamanho; puxador no canto --}}
                                        <template x-if="bl.t === 'img'">
                                            <figure class="wf-es-fig" :class="'al-' + (bl.a || 'c')">
                                                <div class="wf-es-fig-caixa" :style="`width:${bl.w || 100}%`" @click="selUm(bl.id)">
                                                    <template x-if="srcImg(bl)">
                                                        <img :src="srcImg(bl)" :alt="bl.x || 'Imagem'" draggable="false" loading="lazy">
                                                    </template>
                                                    <template x-if="!srcImg(bl)">
                                                        <div class="wf-es-fig-vazio" x-text="enviando.includes(bl.id) ? 'Enviando imagem…' : 'Imagem não encontrada'"></div>
                                                    </template>
                                                    <div class="wf-es-fig-barra" role="toolbar" aria-label="Imagem" @click.stop @mousedown.stop>
                                                        <button type="button" :class="{ 'sel': bl.a === 'e' }" @click="alinharImg(bl, 'e')" title="Alinhar à esquerda" aria-label="Alinhar à esquerda">⇤</button>
                                                        <button type="button" :class="{ 'sel': (bl.a || 'c') === 'c' }" @click="alinharImg(bl, 'c')" title="Centralizar" aria-label="Centralizar">↔</button>
                                                        <button type="button" :class="{ 'sel': bl.a === 'd' }" @click="alinharImg(bl, 'd')" title="Alinhar à direita" aria-label="Alinhar à direita">⇥</button>
                                                        <span class="wf-es-bsep"></span>
                                                        <template x-for="w in [25, 50, 75, 100]" :key="w">
                                                            <button type="button" :class="{ 'sel': (bl.w || 100) === w }" @click="larguraImg(bl, w)" :title="'Largura ' + w + '%'" x-text="w + '%'"></button>
                                                        </template>
                                                        <span class="wf-es-bsep"></span>
                                                        <button type="button" @click="remover(i)" title="Excluir a imagem" aria-label="Excluir a imagem">🗑</button>
                                                    </div>
                                                    <span class="wf-es-fig-puxa" :class="bl.a === 'd' ? 'esq' : ''" @pointerdown="puxarImg($event, bl)" @click.stop title="Arraste para mudar o tamanho" aria-hidden="true"></span>
                                                </div>
                                                <input class="wf-es-leg" :value="bl.x" placeholder="Legenda (opcional)" aria-label="Legenda da imagem"
                                                       :style="`width:${bl.w || 100}%`"
                                                       @focus="limparSel(); foco = null" @input="legenda($event, bl)"
                                                       @keydown.enter.prevent="depoisDe(i)" @keydown.escape.prevent="selUm(bl.id)">
                                            </figure>
                                        </template>

                                        <template x-if="bl.t !== 'hr' && bl.t !== 'img'">
                                            <div class="wf-es-txt">
                                                <template x-if="bl.t === 'code'">
                                                    <input class="wf-es-lang" :value="bl.l" placeholder="linguagem (opcional)" aria-label="Linguagem do código"
                                                        @input="bl.l = $event.target.value.replace(/[^\w+#.-]/g, '')">
                                                </template>
                                                <div class="wf-es-ver" x-show="bl.t !== 'code' && foco !== bl.id" @click="ver($event, bl)" x-html="html(bl)"></div>
                                                <textarea class="wf-es-ta" rows="1" x-show="bl.t === 'code' || foco === bl.id"
                                                        :spellcheck="bl.t !== 'code'" autocomplete="off" aria-label="Texto do bloco"
                                                        :placeholder="bl.t === 'p' ? 'Digite “/” para ver os comandos…' : ''"
                                                        :value="bl.x"
                                                        x-init="$nextTick(() => fit($el))"
                                                        @focus="aoFocar(bl, $el)"
                                                        @select="sel($event)" @keyup="sel($event)" @mouseup="sel($event)"
                                                        @input="entrada($event, bl)" @keydown="tecla($event, bl, i)" @paste="colar($event, bl, i)"></textarea>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="menu && menu.id === bl.id">
                                        <div class="wf-es-menu" role="listbox" aria-label="Comandos">
                                            <template x-for="(it, k) in itensMenu()" :key="it.id">
                                                <button type="button" class="wf-es-op" role="option" :class="{ 'sel': menu && menu.i === k }"
                                                        @mousedown.prevent="aplicar(it.id, bl)" @mouseenter="menu.i = k">
                                                    <span class="wf-es-ic" x-text="it.ic"></span>
                                                    <span><b x-text="it.nome"></b><small x-text="it.desc"></small></span>
                                                </button>
                                            </template>
                                            <p class="wf-es-vazio" x-show="!itensMenu().length">Nada encontrado</p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div class="wf-es-fim" @click="aoFim()" @dragover.prevent @drop.prevent="soltarEm($event, blocos.length, true)" aria-hidden="true"></div>
                        </div>

                        <div class="wf-es-rodape" aria-live="polite">
                            <span x-text="palavras() + ' palavra(s) · ' + caracteres() + ' caractere(s) · ~' + leitura() + ' min de leitura'"></span>
                            <span x-show="tarefas().total" x-cloak x-text="'Tarefas: ' + tarefas().feitas + '/' + tarefas().total"></span>
                            <span x-show="selecao.length" x-cloak x-text="selecao.length + ' bloco(s) selecionado(s)'"></span>
                            <span x-show="b.dados.opcoes !== false" x-cloak>Dica: “/” abre os comandos · cole ou arraste imagens no texto · Esc seleciona o bloco · Shift+↑/↓ amplia · Ctrl+Alt+1/2/3 vira título</span>
                            <span x-show="dica" x-cloak style="color:var(--prim-forte)" x-text="dica"></span>
                        </div>
                    </div>
                </template>

                {{-- Tabela --}}
                <template x-if="b.tipo === 'tabela'">
                    <div class="wf-xl" data-xl x-init="tbGarantir(b)" @keydown="tbTeclaRaiz($event, b)">

                        <div class="wf-xl-barra" role="toolbar" aria-label="Ferramentas da tabela">
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).hist.length" @click="tbDesfazer(b)" title="Desfazer (Ctrl+Z)">↶</button>
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).refaz.length" @click="tbRefazer(b)" title="Refazer (Ctrl+Y)">↷</button>
                            <span class="wf-xl-sep"></span>

                            <select class="campo" aria-label="Formato da coluna" title="Formato dos números da coluna" @change="tbFmt(b, $event.target.value)">
                                <template x-for="f in tbFormatos" :key="f.id">
                                    <option :value="f.id" :selected="tbCol(b, tbUI(b).c).fmt === f.id" x-text="f.nome"></option>
                                </template>
                            </select>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'e')" title="Alinhar coluna à esquerda">⇤</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'c')" title="Centralizar coluna">↔</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'd')" title="Alinhar coluna à direita">⇥</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, '')" title="Alinhamento automático (números à direita)">Auto</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" style="font-weight:700" @click="tbNegrito(b)" title="Negrito (Ctrl+B)">N</button>
                            <input type="color" class="wf-cor !w-6 !h-6" value="#fde68a" @change="tbFundo(b, $event.target.value)" aria-label="Cor de fundo da seleção" title="Cor de fundo da seleção">
                            <button type="button" class="btn-sec" @click="tbFundo(b, '')" title="Remover cor de fundo">Sem cor</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbOrdenar(b, 1)" title="Ordenar a coluna ativa de A a Z">A→Z</button>
                            <button type="button" class="btn-sec" @click="tbOrdenar(b, -1)" title="Ordenar a coluna ativa de Z a A">Z→A</button>
                            <button type="button" class="btn-sec" @click="tbAutoSoma(b)" title="Soma automática dos números acima (ou à esquerda)">Σ</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'baixo')" title="Preencher para baixo (Ctrl+D)">Preencher ↓</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'direita')" title="Preencher para a direita (Ctrl+R)">Preencher →</button>
                            <button type="button" class="btn-sec" @click="tbLimpar(b)" title="Limpar o conteúdo da seleção (Delete)">Limpar</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbLinhaIns(b, tbIntervalo(b).i2 + 1)" title="Inserir linha abaixo da seleção">+ Linha aqui</button>
                            <button type="button" class="btn-sec" @click="tbColIns(b, tbIntervalo(b).j2 + 1)" title="Inserir coluna à direita da seleção">+ Coluna aqui</button>
                            <select class="campo" aria-label="Rodapé de totais da coluna" title="Total no rodapé da coluna" @change="tbRodapeSet(b, $event.target.value)">
                                <template x-for="a in tbAgregados" :key="a.id">
                                    <option :value="a.id" :selected="((b.dados.rodape || [])[tbUI(b).c] || '') === a.id" x-text="a.nome"></option>
                                </template>
                            </select>
                            <input class="campo" type="search" style="width:8rem" placeholder="Filtrar linhas…" aria-label="Filtrar linhas" x-model="tbUI(b).filtro">
                            <span class="wf-xl-sep"></span>

                            <label class="btn-sec" title="Importar um arquivo CSV/TSV (substitui a tabela)">Importar CSV
                                <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="tbImportar(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="tbExportar(b)" title="Baixar como CSV (abre no Excel)">Exportar CSV</button>
                        </div>

                        {{-- Barra de fórmulas --}}
                        <div class="wf-xl-fx">
                            <span class="wf-xl-ref" x-text="tbRef(b)" title="Célula ou intervalo selecionado"></span>
                            <span class="texto-2 text-xs">fx</span>
                            <input class="campo" data-barra spellcheck="false" autocomplete="off"
                                   placeholder="Valor ou fórmula (ex.: =SOMA(B1:B5) ou =A1*B1)" aria-label="Conteúdo da célula ativa"
                                   :value="tbRaw(b)" @focus="tbUI(b).sujo = false" @input="tbBarra(b, $event.target.value)"
                                   @keydown.enter.prevent="tbFocarAtivo($event, b)">
                        </div>

                        {{-- Grade --}}
                        <div class="wf-xl-rolagem" @copy="tbCopiar($event, b, false)" @cut="tbCopiar($event, b, true)" @paste="tbColar($event, b)">
                            <table class="wf-tab wf-xl-tab" :style="tbLarg(b)">
                                <colgroup>
                                    <col style="width:44px">
                                    <template x-for="(c, j) in b.dados.cab" :key="j"><col :style="'width:' + tbColW(b, j) + 'px'"></template>
                                    <col style="width:32px">
                                </colgroup>
                                <thead>
                                    <tr class="letras">
                                        <th class="canto" @click="tbSelTudo(b)" title="Selecionar tudo"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th class="letra" :class="{ 'sel': tbColAtiva(b, j) }" @click="tbSelCol(b, j, $event)" title="Selecionar coluna (Shift para ampliar)">
                                                <span x-text="tbLetra(j)"></span>
                                                <i class="wf-xl-grip" title="Arraste para mudar a largura (duplo clique restaura)"
                                                   @pointerdown.stop.prevent="tbRedim($event, b, j)" @click.stop @dblclick.stop="tbAutoLarg(b, j)"></i>
                                            </th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                    <tr class="nomes">
                                        <th class="canto"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th><div class="flex items-center">
                                                <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                                <button type="button" class="mini-btn perigo" x-show="b.dados.cab.length > 1" title="Remover coluna" @click="tbColRem(b, j)">✕</button>
                                            </div></th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="i in tbVisiveis(b)" :key="i">
                                        <tr>
                                            <th class="num" :class="{ 'sel': tbLinhaAtiva(b, i) }" @click="tbSelLinha(b, i, $event)" x-text="i + 1" title="Selecionar linha (Shift para ampliar)"></th>
                                            <template x-for="(c, j) in b.dados.cab" :key="j">
                                                <td :class="{ 'sel': tbSelecionada(b, i, j), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                    <input class="wf-cel" spellcheck="false" autocomplete="off"
                                                           :data-r="i" :data-c="j" :style="tbInEstilo(b, i, j)" :aria-label="'Célula ' + tbLetra(j) + (i + 1)"
                                                           :value="tbValor(b, i, j)"
                                                           @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)" @input="tbInput(b, i, j, $event)"
                                                           @keydown="tbTecla($event, b, i, j)" @mousedown="tbDown($event, b, i, j)">
                                                </td>
                                            </template>
                                            <td style="border:0;text-align:center"><button type="button" class="mini-btn perigo" title="Remover linha" @click="tbLinhaRem(b, i)">✕</button></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot x-show="tbTemRodape(b)">
                                    <tr>
                                        <th class="num" title="Totais (consideram as linhas visíveis)">Σ</th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j"><td x-text="tbRodape(b, j)"></td></template>
                                        <td style="border:0"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <button type="button" class="link-prim text-sm" @click="tbLinhaAdd(b)">+ Linha</button>
                            <button type="button" class="link-prim text-sm" @click="tbColAdd(b)">+ Coluna</button>
                            <div class="wf-xl-status" aria-live="polite">
                                <span x-text="b.dados.linhas.length + ' linha(s) × ' + b.dados.cab.length + ' coluna(s)' + (tbUI(b).filtro ? ' · ' + tbVisiveis(b).length + ' visível(is)' : '')"></span>
                                <span x-show="tbStats(b)" x-text="tbStats(b)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Lista: o texto de cada item quebra para a linha de baixo (o campo cresce), em vez de ficar cortado na largura do cartão --}}
                <template x-if="b.tipo === 'lista'">
                    <div class="space-y-2">
                        <input class="campo" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $el)">
                        <ul class="space-y-1.5">
                            <template x-for="i in b.dados.itens" :key="i.id">
                                <li class="flex items-start gap-2 text-sm">
                                    <input type="checkbox" class="mt-2 shrink-0" x-model="i.f" aria-label="Item concluído">
                                    <textarea class="wf-cel wf-item-txt" :class="{ 'line-through texto-2': i.f }" rows="1" spellcheck="true"
                                              aria-label="Texto do item" x-model="i.t"
                                              x-init="const aj = () => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'; };
                                                      $nextTick(aj); new ResizeObserver(aj).observe($el.parentElement)"
                                              @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                              @keydown.enter.prevent="$el.blur()"></textarea>
                                    <button type="button" class="mini-btn perigo mt-1.5 shrink-0" title="Remover" @click="remItem(b, i.id)">✕</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Código: repositórios (GitHub / VS Code) e editor de código --}}
                <template x-if="b.tipo === 'codigo'">
                    <div class="h-full flex flex-col gap-3">
                        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Modo do bloco de código">
                            <button type="button" class="btn-sec !py-1" role="tab"
                                    :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': (b.dados.modo || 'repos') === 'repos' }"
                                    :aria-selected="(b.dados.modo || 'repos') === 'repos'"
                                    @click="b.dados.modo = 'repos'">Repositórios</button>
                            <button type="button" class="btn-sec !py-1" role="tab"
                                    :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': b.dados.modo === 'ide' }"
                                    :aria-selected="b.dados.modo === 'ide'"
                                    @click="b.dados.modo = 'ide'; WFIde.ampliar(estado.layout[b.secao]?.[b.id])">Editor</button>
                        </div>

                        {{-- Repositórios --}}
                        <template x-if="(b.dados.modo || 'repos') === 'repos'">
                            <div class="space-y-3 flex-1 min-h-0 overflow-auto">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input class="campo flex-1" style="min-width:12rem"
                                           placeholder="Cole a URL do GitHub ou dono/repositório e Enter"
                                           aria-label="Adicionar repositório"
                                           @keydown.enter.prevent="if ($el.value.trim()) { b.dados.repos.push({ id: uid(), nome: $el.value.trim(), pasta: '', ramo: '', nota: '' }); $el.value = ''; }">
                                    <button type="button" class="btn-sec"
                                            @click="b.dados.repos.push({ id: uid(), nome: '', pasta: '', ramo: '', nota: '' })">+ Repositório</button>
                                </div>
                                <p class="text-xs texto-2" x-show="b.dados.repos.length"
                                   x-text="b.dados.repos.length + ' repositório(s) · ' + b.dados.repos.filter(r => (r.pasta || '').trim()).length + ' com pasta local'"></p>
                                <p class="text-sm texto-2" x-show="!b.dados.repos.length">Nenhum repositório ainda. Cole uma URL do GitHub acima para começar.</p>

                                <template x-for="r in b.dados.repos" :key="r.id">
                                    <div class="wf-repo" :class="{ 'invalido': (r.nome || '').trim() && !WFRepo.ok(r.nome) }">
                                        <div class="wf-repo-linha">
                                            <input class="wf-cel" x-model="r.nome" placeholder="dono/repositório ou URL do GitHub" aria-label="Repositório">
                                            <input class="wf-cel wf-repo-ramo" x-model="r.ramo" placeholder="ramo (opcional)" aria-label="Ramo (branch)">
                                            <span class="wf-repo-selo" x-show="WFRepo.ok(r.nome)" x-text="WFRepo.slug(r)"></span>
                                            <button type="button" class="mini-btn perigo" title="Remover repositório" aria-label="Remover repositório"
                                                    @click="b.dados.repos.splice(b.dados.repos.indexOf(r), 1)">✕</button>
                                        </div>
                                        <p class="wf-repo-aviso" x-show="(r.nome || '').trim() && !WFRepo.ok(r.nome)">
                                            Não reconheci esse repositório. Use dono/repositório ou a URL completa do GitHub.
                                        </p>
                                        <div class="wf-repo-linha">
                                            <input class="wf-cel" x-model="r.pasta" placeholder="Pasta local (ex.: C:/projetos/app)" aria-label="Pasta local">
                                            <button type="button" class="mini-btn" x-show="(r.pasta || '').trim()" title="Copiar caminho da pasta" aria-label="Copiar caminho da pasta"
                                                    @click="WFRepo.copiar(r.pasta).then(ok => aviso(ok ? 'Caminho copiado.' : 'Não consegui copiar.'))">⧉</button>
                                        </div>
                                        <input class="wf-cel" style="border:1px solid var(--linha);background:var(--superficie)" x-model="r.nota" placeholder="Nota (opcional): para que serve, o que falta fazer…" aria-label="Nota">

                                        <div class="wf-repo-acoes" x-show="WFRepo.ok(r.nome) || (r.pasta || '').trim()">
                                            <template x-if="WFRepo.ok(r.nome)">
                                                <span class="contents">
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'repo')" target="_blank" rel="noopener noreferrer">GitHub</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'issues')" target="_blank" rel="noopener noreferrer">Issues</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'pulls')" target="_blank" rel="noopener noreferrer">PRs</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'actions')" target="_blank" rel="noopener noreferrer">Actions</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'web')" target="_blank" rel="noopener noreferrer">VS Code web</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'dev')" target="_blank" rel="noopener noreferrer">github.dev</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'clone')" title="Abre o VS Code instalado e clona o repositório">Clonar no VS Code</a>
                                                    <button type="button" class="btn-sec" title="Copiar o comando git clone"
                                                            @click="WFRepo.copiar(WFRepo.cmd(r)).then(ok => aviso(ok ? 'Comando git clone copiado.' : 'Não consegui copiar.'))">Copiar git clone</button>
                                                </span>
                                            </template>
                                            <a class="btn-sec" x-show="(r.pasta || '').trim()" :href="WFRepo.local(r)" title="Abre a pasta no VS Code instalado">VS Code local</a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Editor de código (lógica embutida no início deste arquivo) --}}
                        <template x-if="b.dados.modo === 'ide'">
                            <div class="wf-ide" :class="{ 'movendo': movendo }" x-data="WFIde(b)" x-init="iniciar()"
                                 :style="`--lado:${b.dados.ide.layout?.lado ?? 208}px;--painel:${b.dados.ide.layout?.painel ?? 176}px`">

                                {{-- Aparece só se o navegador carregou um editor mais antigo que este bloco (a causa dos erros "… is not defined" no console) --}}
                                <div class="wf-ide-velho" x-show="!(window.WFIde && WFIde.versao >= 5)" x-cloak role="alert">
                                    O editor carregado no navegador está desatualizado e não vai funcionar direito.
                                    Recarregue a página com <b>Ctrl+F5</b>.
                                </div>

                                <div class="wf-ide-barra" role="toolbar" aria-label="Ferramentas do editor">
                                    <button type="button" class="btn-sec" @click="alternarBarra()" :aria-expanded="b.dados.ide.layout?.barra ?? true" title="Mostrar ou ocultar as opções">☰ Opções</button>
                                    <span class="contents" x-show="b.dados.ide.layout?.barra ?? true">
                                        <button type="button" class="btn-sec" @click="novoArquivo()" title="Novo arquivo (ex.: pagina.blade.php, Main.java)">+ Arquivo</button>
                                        <button type="button" class="btn-sec" @click="novoLaravel()" title="Projeto Laravel completo que roda aqui, ou controller, model, migration, rotas, view, seeder, factory, componente…">+ Laravel</button>
                                        <button type="button" class="btn-sec" @click="novaPasta()" title="Nova pasta">+ Pasta</button>
                                        <label class="btn-sec" title="Enviar arquivos de texto do computador">Enviar
                                            <input type="file" multiple class="hidden" @change="enviar($event.target.files); $event.target.value = ''">
                                        </label>
                                        <span class="sep"></span>
                                        <button type="button" class="btn-sec" @click="salvar()" title="Salvar (Ctrl+S)">Salvar</button>
                                        <button type="button" class="btn-sec" @click="baixar()" x-show="arqAtivo()" title="Baixar o arquivo aberto">Baixar arquivo</button>
                                        <button type="button" class="btn-sec" @click="baixarZip()" title="Baixar o projeto inteiro em ZIP">Baixar ZIP</button>
                                        <button type="button" class="btn-sec" @click="mapaDoCodigo()" title="Cria um cartão de Mapa mental com as pastas, arquivos, classes, funções e o que cada arquivo importa">Mapa do código</button>
                                        <span class="sep" x-show="suporteDisco"></span>
                                        <button type="button" class="btn-sec" x-show="suporteDisco" @click="abrirPasta()" title="Abrir uma pasta do computador (Chrome/Edge)">Abrir pasta</button>
                                        <button type="button" class="btn-sec" x-show="disco" x-cloak @click="salvarDisco()" :title="'Gravar tudo em ' + disco">Salvar na pasta</button>
                                        <span class="sep"></span>
                                        <button type="button" class="btn-sec" x-show="podeExecutar()" @click="executar()" title="Executar o arquivo aberto (Ctrl+Enter): JS/TS, Python, PHP, Laravel (projeto com routes/web.php), SQL, Ruby, Lua e Blade rodam no navegador; Java, C, Go… no servidor">▶ Executar</button>
                                        <button type="button" class="btn-sec" x-show="raizLaravel() !== null" x-cloak @click="artisan()" title="php artisan no navegador: route:list, migrate:fresh --seed, db:seed, tinker…">Artisan</button>
                                        <button type="button" class="btn-sec" x-show="Object.keys(motorUsado).length" x-cloak @click="pararMotores()" title="Interrompe um programa travado (laço infinito) e recarrega os motores">■ Parar</button>
                                        <button type="button" class="btn-sec" @click="abrirBusca()" title="Buscar e substituir (Ctrl+F)">Buscar</button>
                                        <button type="button" class="btn-sec" @click="abrirPaleta('arq')" title="Abrir arquivo pelo nome (Ctrl+P)">Ir p/ arquivo</button>
                                        <button type="button" class="btn-sec" x-show="arqAtivo()" @click="abrirPaleta('linha')" title="Ir para a linha (Ctrl+G)">Ir p/ linha</button>
                                        <button type="button" class="btn-sec" x-show="arqAtivo() && extensao() === 'JSON'" x-cloak @click="formatar()" title="Formatar JSON (Ctrl+Shift+F)">Formatar</button>
                                        <button type="button" class="btn-sec" x-show="disco" x-cloak @click="alternarAuto()" :aria-pressed="auto" title="Grava na pasta sozinho, 1,5 s depois de parar de digitar" x-text="'Auto-salvar: ' + (auto ? 'sim' : 'não')"></button>
                                    </span>
                                </div>

                                <div class="wf-ide-busca" x-show="busca.on" x-cloak role="search">
                                    <input x-ref="q" class="campo" :class="{ 'erro': buscaInvalida() }" placeholder="Buscar" aria-label="Buscar" x-model="busca.q"
                                           @keydown.enter.prevent="proxima($event.shiftKey ? -1 : 1)" @keydown.escape.prevent="busca.on = false">
                                    <input class="campo" placeholder="Substituir por" aria-label="Substituir por" x-model="busca.r"
                                           @keydown.enter.prevent="substituirUm()" @keydown.escape.prevent="busca.on = false">
                                    <button type="button" class="btn-sec" :class="{ 'on': busca.caso }" :aria-pressed="busca.caso" @click="busca.caso = !busca.caso" title="Diferenciar maiúsculas de minúsculas">Aa</button>
                                    <button type="button" class="btn-sec" :class="{ 'on': busca.regex }" :aria-pressed="busca.regex" @click="busca.regex = !busca.regex" title="Expressão regular (use $1, $2… na substituição)">.*</button>
                                    <span class="texto-2" x-text="buscaInvalida() ? 'regex inválida' : contar() + ' no arquivo'"></span>
                                    <button type="button" class="btn-sec" @click="proxima(-1)" title="Anterior">↑</button>
                                    <button type="button" class="btn-sec" @click="proxima(1)" title="Próxima">↓</button>
                                    <button type="button" class="btn-sec" @click="substituirUm()">Substituir</button>
                                    <button type="button" class="btn-sec" @click="substituirTodos()">Substituir todas</button>
                                    <label class="flex items-center gap-1 texto-2"><input type="checkbox" x-model="busca.todos"> Em todos os arquivos</label>
                                    <button type="button" class="mini-btn" @click="busca.on = false" aria-label="Fechar busca">✕</button>
                                </div>

                                <div class="wf-ide-corpo">
                                    <aside class="wf-ide-lado" aria-label="Arquivos do projeto" @click.self="sel = ''" @contextmenu="abrirMenu(null, $event)"
                                           :class="{ 'alvo-raiz': alvoIde === '' }"
                                           @dragover="sobreArrasto(null, $event)" @drop="soltarArrasto(null, $event)"
                                           @dragleave.self="alvoIde = null">
                                        <template x-for="n in arvore()" :key="n.k + ':' + n.caminho">
                                            <div class="wf-ide-no" :class="{ 'ativo': n.id && n.id === b.dados.ide.ativo, 'sel': sel === n.caminho, 'alvo': alvoIde !== null && alvoIde !== '' && n.k === 'p' && alvoIde === n.caminho, 'arrastando': arrastoIde && arrastoIde.caminho === n.caminho }"
                                                 :style="`padding-left:${.4 + n.nivel * .9}rem`" @click="clicar(n)" @contextmenu="abrirMenu(n, $event)" :title="n.caminho"
                                                 draggable="true" @dragstart.stop="comecarArrasto(n, $event)" @dragend="fimArrasto()"
                                                 @dragover.stop="sobreArrasto(n, $event)" @drop.stop="soltarArrasto(n, $event)">
                                                <span class="seta" x-text="n.k === 'p' ? (n.fechada ? '▸' : '▾') : ''"></span>
                                                <span class="tipo" x-show="n.tipo" x-text="n.tipo"></span>
                                                <span class="nome" x-text="n.nome"></span>
                                                <span class="ponto" x-show="n.k === 'a' && alterado(n.arq)" x-cloak title="Alterações ainda não gravadas na pasta"></span>
                                                <span class="acoes">
                                                    <button type="button" class="mini-btn" title="Renomear / mover" aria-label="Renomear" @click.stop="renomear(n)">✎</button>
                                                    <button type="button" class="mini-btn perigo" title="Excluir" aria-label="Excluir" @click.stop="excluir(n)">✕</button>
                                                </span>
                                            </div>
                                        </template>
                                        <p class="px-2 py-1 text-xs texto-2" x-show="!arvore().length">Projeto vazio. Use “+ Arquivo”.</p>

                                        <template x-for="(r, i) in resultados()" :key="i">
                                            <div class="wf-ide-res" @click="irLinha(r)">
                                                <b x-text="r.caminho + ':' + r.linha"></b>
                                                <div class="texto-2" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="r.trecho"></div>
                                            </div>
                                        </template>
                                    </aside>

                                    {{-- Divisor: arraste para mudar a largura da lista de arquivos --}}
                                    <div class="wf-ide-div v" role="separator" aria-orientation="vertical" tabindex="0" aria-label="Redimensionar lista de arquivos"
                                         title="Arraste para redimensionar · duplo clique restaura"
                                         @pointerdown="arrastar('lado', $event)" @dblclick="restaurar('lado')" @keydown="teclaDivisor('lado', $event)"></div>

                                    <section class="wf-ide-main">
                                        <div class="wf-ide-abas" role="tablist" aria-label="Arquivos abertos">
                                            <template x-for="f in abas()" :key="f.id">
                                                <div class="wf-ide-aba" role="tab" :class="{ 'ativo': f.id === b.dados.ide.ativo }" :aria-selected="f.id === b.dados.ide.ativo"
                                                     @click="abrir(f.id)" @auxclick.prevent="if ($event.button === 1) fechar(f.id)" :title="f.caminho + ' (clique do meio fecha)'">
                                                    <span x-text="nomeArq(f)"></span>
                                                    <span class="ponto" x-show="alterado(f)" x-cloak title="Alterações ainda não gravadas na pasta"></span>
                                                    <button type="button" class="x" :aria-label="'Fechar ' + nomeArq(f)" @click.stop="fechar(f.id)">✕</button>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="wf-ide-ed" x-show="arqAtivo()" x-cloak>
                                            <div class="wf-ide-num" x-ref="num" aria-hidden="true" x-text="numeros()"></div>
                                            <div class="wf-ide-cod">
                                                <pre class="wf-ide-pre" x-ref="pre" aria-hidden="true" x-html="realce()"></pre>
                                                <textarea class="wf-ide-ta" x-ref="ta" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off" autocorrect="off"
                                                          aria-label="Código do arquivo aberto"
                                                          :value="arqAtivo() ? arqAtivo().texto : ''"
                                                          @input="editar($event)" @scroll="sincronizar()" @keydown="teclas($event)"
                                                          @keyup="cursor()" @click="cursor()"></textarea>
                                            </div>
                                        </div>
                                        <div class="wf-ide-vazio" x-show="!arqAtivo()" x-cloak>
                                            Abra um arquivo na lista ao lado ou crie um novo com “+ Arquivo”.
                                        </div>

                                        {{-- Divisor: arraste para mudar a altura do console/prévia --}}
                                        <div class="wf-ide-div h" x-show="painel" x-cloak role="separator" aria-orientation="horizontal" tabindex="0" aria-label="Redimensionar console e prévia"
                                             title="Arraste para redimensionar · duplo clique restaura"
                                             @pointerdown="arrastar('painel', $event)" @dblclick="restaurar('painel')" @keydown="teclaDivisor('painel', $event)"></div>

                                        <div class="wf-ide-painel" x-show="painel" x-cloak>
                                            <div class="wf-ide-painel-topo">
                                                <span class="t" :class="{ 'ativo': painel === 'saida' }" @click="painel = 'saida'">Console</span>
                                                <span class="t" :class="{ 'ativo': painel === 'previa' }" @click="painel = 'previa'">Prévia</span>
                                                <span class="t" x-show="temBanco()" :class="{ 'ativo': painel === 'banco' }" @click="painel = 'banco'; atualizarBanco()">Banco</span>
                                                <span style="flex:1"></span>
                                                <button type="button" class="link-prim" x-show="painel === 'previa'" @click="telaCheiaPrevia()" title="Prévia em tela cheia">Tela cheia</button>
                                                <button type="button" class="link-prim" @click="limparSaida()">Limpar</button>
                                                <button type="button" class="mini-btn" @click="fecharPainel()" aria-label="Fechar painel">✕</button>
                                            </div>
                                            <div class="wf-ide-saida" x-show="painel === 'saida'">
                                                <p class="texto-2" x-show="!saida.length">Sem saída. Use console.log() (JS/TS), print() (Python/Lua), puts (Ruby), echo (PHP), System.out.println() (Java)…</p>
                                                <template x-for="(l, i) in saida" :key="i">
                                                    <div>
                                                        <template x-if="l.t !== 'tabela'">
                                                            <div class="wf-ide-log" :class="'l-' + l.t"><span x-show="l.t === 'dica'" aria-hidden="true">💡 </span><span x-text="l.m"></span></div>
                                                        </template>
                                                        <template x-if="l.t === 'tabela'">
                                                            <div class="wf-ide-tab">
                                                                <div class="cab" x-text="l.m + ' · ' + l.total + (l.total === 1 ? ' linha' : ' linhas') + (l.total > l.linhas.length ? ' (mostrando ' + l.linhas.length + ')' : '')"></div>
                                                                <div class="rolar"><table>
                                                                    <thead><tr><template x-for="c in l.colunas" :key="c"><th x-text="c"></th></template></tr></thead>
                                                                    <tbody><template x-for="(r, k) in l.linhas" :key="k"><tr><template x-for="(v, j) in r" :key="j"><td :class="{ nulo: v === null }" x-text="v === null ? 'NULL' : v"></td></template></tr></template></tbody>
                                                                </table></div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                            <div class="wf-ide-saida wf-ide-banco" x-show="painel === 'banco'">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <b x-text="lv.ativo ? 'Banco do app Laravel (SQLite do navegador)' : 'Tabelas do banco (SQLite do navegador)'"></b>
                                                    <span style="flex:1"></span>
                                                    <button type="button" class="link-prim" @click="recriarBanco()" :title="lv.ativo ? 'Apaga as tabelas e roda as migrations e o DatabaseSeeder de novo (migrate:fresh --seed)' : 'Apaga o banco e roda todos os .sql do projeto em ordem de nome'">Recriar banco</button>
                                                </div>
                                                <p class="texto-2" x-show="!banco.tabelas.length" x-text="lv.ativo ? 'Nenhuma tabela ainda. Crie uma migration (+ Laravel → Migration) e execute o app.' : 'Nenhuma tabela ainda. Rode um .sql com CREATE TABLE (▶ Executar).'"></p>
                                                <div class="lista">
                                                    <template x-for="t in banco.tabelas" :key="t.nome">
                                                        <button type="button" class="item" :class="{ sel: banco.sel === t.nome }" @click="verTabela(t.nome)">
                                                            <b x-text="t.nome"></b> <span class="texto-2" x-text="t.total + (t.total === 1 ? ' linha' : ' linhas')"></span>
                                                            <span class="cols" x-text="t.colunas.join(' · ')"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                                <template x-if="banco.dados">
                                                    <div class="wf-ide-tab mt-2">
                                                        <div class="cab" x-text="banco.dados.titulo + ' · primeiras ' + banco.dados.linhas.length + ' linhas'"></div>
                                                        <div class="rolar"><table>
                                                            <thead><tr><template x-for="c in banco.dados.colunas" :key="c"><th x-text="c"></th></template></tr></thead>
                                                            <tbody><template x-for="(r, k) in banco.dados.linhas" :key="k"><tr><template x-for="(v, j) in r" :key="j"><td :class="{ nulo: v === null }" x-text="v === null ? 'NULL' : v"></td></template></tr></template></tbody>
                                                        </table></div>
                                                    </div>
                                                </template>
                                            </div>
                                            {{-- app Laravel na prévia: endereço, voltar, recarregar, rotas e artisan --}}
                                            <div class="wf-ide-lvbar" x-show="painel === 'previa' && lv.ativo" x-cloak>
                                                <button type="button" class="mini-btn" @click="voltarLaravel()" :disabled="!lv.hist.length" title="Voltar" aria-label="Voltar">←</button>
                                                <button type="button" class="mini-btn" @click="recarregarLaravel()" title="Recarregar (pede a página de novo)" aria-label="Recarregar">↻</button>
                                                <span class="texto-2">localhost</span>
                                                <input class="campo" x-model="lv.campo" @keydown.enter.prevent="irPara(lv.campo)" aria-label="Endereço no app Laravel" placeholder="/rota" spellcheck="false">
                                                <span class="st" :class="{ 'ruim': lv.status >= 400 }" x-show="lv.status" x-text="lv.carregando ? '…' : lv.status"></span>
                                                <button type="button" class="link-prim" @click="artisan('route:list')" title="Lista as rotas do app (artisan route:list)">Rotas</button>
                                                <button type="button" class="link-prim" @click="artisan()" title="php artisan: migrate:fresh --seed, db:seed, tinker…">Artisan</button>
                                            </div>
                                            <iframe class="wf-ide-frame" x-ref="frame" x-show="painel === 'previa'" title="Prévia do projeto"
                                                    sandbox="allow-scripts allow-forms allow-modals allow-popups" :srcdoc="srcdoc"></iframe>
                                        </div>
                                    </section>
                                </div>

                                {{-- Menu do botão direito (arquivos e pastas). :style em forma de objeto para não apagar o display:none do x-show --}}
                                <div class="wf-ide-ctx" x-show="menuCtx" x-cloak role="menu" aria-label="Ações do arquivo"
                                     :style="menuCtx ? { left: menuCtx.x + 'px', top: menuCtx.y + 'px' } : {}"
                                     @click.outside="menuCtx = null" @contextmenu.prevent>
                                    <template x-for="it in itensCtx()" :key="it.id">
                                        <div>
                                            <div class="wf-ide-ctx-sep" x-show="it.sep"></div>
                                            <button type="button" class="wf-ide-ctx-it" role="menuitem" :disabled="it.off" @click="acaoCtx(it.id)">
                                                <span x-text="it.nome"></span><small x-show="it.dica" x-text="it.dica"></small>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                {{-- Paleta: abrir arquivo pelo nome (Ctrl+P) ou ir para a linha (Ctrl+G) --}}
                                <div class="wf-ide-pal-fundo" x-show="pal.on" x-cloak @click.self="fecharPaleta()">
                                    <div class="wf-ide-pal" role="dialog" :aria-label="pal.modo === 'linha' ? 'Ir para a linha' : 'Abrir arquivo'">
                                        <input x-ref="palq" class="campo" x-model="pal.q" @input="pal.i = 0" @keydown="teclaPaleta($event)" autocomplete="off" spellcheck="false"
                                               :placeholder="pal.modo === 'linha' ? 'Linha ou linha:coluna (ex.: 42:7) e Enter' : 'Digite parte do nome do arquivo…'"
                                               :aria-label="pal.modo === 'linha' ? 'Número da linha' : 'Nome do arquivo'">
                                        <div class="wf-ide-pal-lista" x-show="pal.modo === 'arq'" role="listbox">
                                            <template x-for="(f, i) in (pal.on && pal.modo === 'arq' ? itensPaleta() : [])" :key="f.id">
                                                <div class="wf-ide-pal-it" role="option" :class="{ 'sel': i === pal.i }" :aria-selected="i === pal.i"
                                                     @mousedown.prevent="escolherPaleta(f)" @mouseenter="pal.i = i">
                                                    <span x-text="nomeArq(f)"></span><small x-text="f.caminho"></small>
                                                </div>
                                            </template>
                                            <p class="wf-ide-pal-dica" x-show="pal.on && pal.modo === 'arq' && !itensPaleta().length">Nenhum arquivo encontrado.</p>
                                        </div>
                                        <p class="wf-ide-pal-dica" x-show="pal.modo === 'linha'" x-text="arqAtivo() ? 'O arquivo tem ' + totalLinhas() + ' linhas.' : 'Abra um arquivo primeiro.'"></p>
                                    </div>
                                </div>

                                <div class="wf-ide-status" aria-live="polite">
                                    <span x-text="arqAtivo() ? arqAtivo().caminho : 'Nenhum arquivo aberto'"></span>
                                    <span x-show="arqAtivo()" x-text="extensao() + ' · Ln ' + ln + ', Col ' + col + (selN ? ' (' + selN + ' selec.)' : '') + ' · ' + totalLinhas() + ' linhas'"></span>
                                    <span x-show="disco" x-cloak x-text="'Pasta: ' + disco + (totalAlterados() ? ' · ' + totalAlterados() + ' não gravado(s)' : '')"></span>
                                    <span x-show="msg" x-cloak style="color:var(--prim-forte)" x-text="msg"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Imagem: só exibe, troca e baixa (sem editor nem criador de imagens).
                     Usa imagens[b.id], lerImagem() e ajustarImagem() do diverso.js. --}}
                <template x-if="b.tipo === 'imagem'">
                    <div data-img class="wf-img w-full" x-data="{ erro: '' }"
                         @dragover.prevent
                         @drop.prevent="const f = Array.from($event.dataTransfer?.files || []).find(x => /^image\//.test(x.type));
                                        if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">

                        <p class="wf-img-erro" x-show="erro" x-cloak role="alert" x-text="erro"></p>

                        <template x-if="imagens[b.id]">
                            <div>
                                <div class="wf-img-acoes">
                                    <label class="btn-sec" title="Escolher outro arquivo">Trocar
                                        <input type="file" accept="image/*" class="hidden"
                                               @change="const f = $event.target.files[0]; $event.target.value = '';
                                                        if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">
                                    </label>
                                    <a class="btn-sec" :href="/^data:image\//.test(imagens[b.id] || '') ? imagens[b.id] : '#'" download="imagem.png" title="Baixar a imagem atual">Baixar</a>
                                </div>
                                <img :src="imagens[b.id]" :alt="b.titulo"
                                     class="block w-full h-auto rounded-lg"
                                     @load="ajustarImagem(b, $el)">
                            </div>
                        </template>

                        <div class="wf-img-vazio" x-show="!imagens[b.id]">
                            <label class="btn-sec">Escolher imagem
                                <input type="file" accept="image/*" class="hidden"
                                       @change="const f = $event.target.files[0]; $event.target.value = '';
                                                if (f) { erro = ''; Promise.resolve(lerImagem(b, f)).catch(e => erro = (e && e.message) || 'Não foi possível salvar a imagem.') }">
                            </label>
                        </div>
                    </div>
                </template>

                {{-- Cartão ativo (de outra página) --}}
                @include('areas.partials.bloco-vivo')

                {{-- Banco de dados: .sql (MySQL/MariaDB, PostgreSQL, SQLite) ou .csv lido no navegador, com diagrama, dados e
                     estatísticas. O conteúdo é montado por public/js/banco-sql.js fora do Alpine (aguenta milhões de linhas);
                     o trabalho pesado fica em public/js/banco-sql-worker.js. --}}
                <template x-if="b.tipo === 'banco'">
                    <div class="bd h-full flex flex-col gap-2" x-init="bdMontar(b, $el)" x-effect="bdSinc(b, $el)"></div>
                </template>

                {{-- Mapa mental e diagramas (BPMN, fluxogramas, ER/UML). Lógica em public/js/diagrama.js (métodos dd*). --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="wf-dd h-full" data-dd x-init="dd(b)">

                        {{-- Topo do mapa (botão de recolher + barras + propriedades): acompanha a rolagem da página e do
                             cartão, para as opções ficarem à mão mesmo longe do começo do mapa (diagrama.js, acompanharTopo) --}}
                        <div class="wf-dd-topo" data-dd-topo>

                        {{-- Recolher / mostrar todas as opções: libera a tela para ver o diagrama inteiro.
                             O estado fica direto em b.dados.opcoes (false = recolhidas), sem depender de funções externas. --}}
                        <div class="wf-dd-barra">
                            <button type="button" class="btn-sec wf-opc-btn" :class="{ 'fechada': b.dados.opcoes === false }"
                                    @click="b.dados.opcoes = (b.dados.opcoes === false); dd(b).menu = false;
                                            if (b.dados.opcoes === false && ui[b.id]) { ui[b.id].on = false; ui[b.id].borr = false; ui[b.id].vivo = ''; ui[b.id].cx = null; ui[b.id].cy = null; }"
                                    :aria-expanded="b.dados.opcoes !== false"
                                    :title="b.dados.opcoes === false ? 'Mostrar as opções' : 'Recolher as opções'">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                <span x-text="b.dados.opcoes === false ? 'Mostrar opções' : 'Recolher opções'"></span>
                            </button>
                        </div>

                        {{-- Tudo abaixo (barras, propriedades e dica) some quando as opções são recolhidas --}}
                        <div class="contents" x-show="b.dados.opcoes !== false" x-cloak>

                        {{-- Barra principal --}}
                        <div class="wf-dd-barra" role="toolbar" aria-label="Ferramentas do diagrama">
                            <div class="relative" @click.outside="dd(b).menu = false" @keydown.escape.window="dd(b).menu = false">
                                <button type="button" class="btn-sec" @click="dd(b).menu = !dd(b).menu" aria-haspopup="menu" :aria-expanded="dd(b).menu">+ Elemento</button>
                                <div class="wf-dd-menu" x-show="dd(b).menu" x-cloak role="menu">
                                    <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                        <div>
                                            <p class="wf-dd-menu-grupo" x-text="g.grupo"></p>
                                            <template x-for="it in g.itens" :key="it.id">
                                                <button type="button" class="menu-item" role="menuitem" @click="ddAdicionar(b, it.id, $event)" x-text="it.nome"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!dd(b).hist.length" @click="ddDesfazer(b)" title="Desfazer (Ctrl+Z)">↶ Desfazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).refaz.length" @click="ddRefazer(b)" title="Refazer (Ctrl+Shift+Z)">↷ Refazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length" @click="ddDuplicar(b)" title="Duplicar (Ctrl+D)">Duplicar</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length && !dd(b).selLig" @click="ddRemoverSel(b)" title="Excluir (Delete)">Excluir</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'frente')" title="Trazer para a frente">Frente</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'tras')" title="Enviar para trás">Trás</button>

                            <template x-if="ddSelNos(b).length > 1">
                                <span class="flex flex-wrap items-center gap-1">
                                    <span class="wf-dd-sep"></span>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'esq')" title="Alinhar à esquerda">⇤</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cx')" title="Centralizar na horizontal">↔</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dir')" title="Alinhar à direita">⇥</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'topo')" title="Alinhar ao topo">⤒</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cy')" title="Centralizar na vertical">↕</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'base')" title="Alinhar à base">⤓</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dh')" title="Distribuir na horizontal (3 ou mais)">⇹</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dv')" title="Distribuir na vertical (3 ou mais)">⇳</button>
                                </span>
                            </template>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1 / 1.2)" title="Diminuir o zoom" aria-label="Diminuir o zoom">−</button>
                            <button type="button" class="btn-sec" style="min-width:3.2rem" @click="ddZoomSet(b, 1)" title="Voltar a 100%" x-text="Math.round(dd(b).z * 100) + '%'"></button>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1.2)" title="Aumentar o zoom" aria-label="Aumentar o zoom">+</button>
                            <button type="button" class="btn-sec" @click="ddAjustar(b, $event)" title="Ajustar o diagrama à janela">Ajustar</button>

                            <span class="wf-dd-sep"></span>
                            <label class="wf-dd-chk" title="Mostrar a grade de pontos"><input type="checkbox" :checked="dd(b).grade" @change="dd(b).grade = $event.target.checked"> Grade</label>
                            <label class="wf-dd-chk" title="Encaixar elementos na grade"><input type="checkbox" :checked="dd(b).snap" @change="dd(b).snap = $event.target.checked"> Ímã</label>
                            <select class="campo" aria-label="Estilo padrão dos novos conectores" title="Estilo dos novos conectores"
                                    @change="b.dados.ligPadrao = $event.target.value">
                                <template x-for="s in ddEstilos" :key="s.id">
                                    <option :value="s.id" :selected="(b.dados.ligPadrao || 'ortogonal') === s.id" x-text="'Conector: ' + s.nome"></option>
                                </template>
                            </select>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'png')" title="Baixar como imagem PNG">PNG</button>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'svg')" title="Baixar como SVG">SVG</button>
                        </div>

                        {{-- Desenho livre (continua como no mapa mental) --}}
                        <div class="wf-dd-barra">
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && !ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && !ui[b.id]?.borr)" @click="penModo(b, 'lapis')">✏️ Desenhar</button>
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && ui[b.id]?.borr)" @click="penModo(b, 'borracha')">Borracha</button>
                            <template x-if="ui[b.id]?.on">
                                <span class="flex items-center gap-2">
                                    <input type="color" class="wf-cor !w-6 !h-6" x-model="ui[b.id].cor" aria-label="Cor do traço" title="Cor do traço">
                                    <input type="range" min="1" max="16" x-model.number="ui[b.id].larg" aria-label="Espessura do traço" title="Espessura" style="width:6rem">
                                </span>
                            </template>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penDesfazer(b)">Desfazer traço</button>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penLimpar(b)">Limpar desenho</button>
                        </div>

                        {{-- Propriedades do elemento selecionado --}}
                        <template x-for="n0 in (ddNoSel(b) ? [ddNoSel(b)] : [])" :key="n0.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Forma
                                    <select class="campo" @change="ddMudarForma(b, n0, $event.target.value)">
                                        <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                            <optgroup :label="g.grupo">
                                                <template x-for="it in g.itens" :key="it.id">
                                                    <option :value="it.id" :selected="(n0.forma || 'mapa') === it.id" x-text="it.nome"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Texto
                                    <input class="campo" style="width:9rem" :value="n0.t" @focus="ddGravar(b)" @input="n0.t = $event.target.value" aria-label="Texto do elemento">
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="n0.cor || '#a78bfa'" @input="ddCor(b, $event.target.value)" aria-label="Cor do elemento">
                                </label>
                                <template x-if="!ddGeo(n0).d.fixo">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <label class="wf-dd-campo">Largura
                                            <input type="number" class="campo" style="width:4.5rem" min="40" max="1600" :value="ddGeo(n0).w" @change="ddTam(b, 'w', $event.target.value)">
                                        </label>
                                        <label class="wf-dd-campo" x-show="n0.forma !== 'entidade'">Altura
                                            <input type="number" class="campo" style="width:4.5rem" min="24" max="1600" :value="ddGeo(n0).h" @change="ddTam(b, 'h', $event.target.value)">
                                        </label>
                                    </span>
                                </template>
                                <button type="button" class="btn-sec" @click="ddFilho(b, n0)" title="Criar elemento ligado a este (Tab)">+ Filho</button>
                                <button type="button" class="btn-sec" x-show="n0.pai" @click="ddDesligarPai(b, n0)" title="Soltar este tópico do pai">Desligar do pai</button>
                                <template x-if="n0.forma === 'entidade'">
                                    <label class="wf-dd-campo" style="flex-basis:100%;align-items:flex-start">Campos
                                        <textarea class="campo" rows="3" style="flex:1;min-width:12rem;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.75rem"
                                                  placeholder="PK id&#10;FK cliente_id&#10;nome"
                                                  :value="n0.campos || ''" @focus="ddGravar(b)" @input="n0.campos = $event.target.value" aria-label="Campos da entidade (um por linha; PK, FK ou UK no início)"></textarea>
                                    </label>
                                </template>
                            </div>
                        </template>

                        {{-- Propriedades do conector selecionado --}}
                        <template x-for="l in (ddLigSel(b) ? [ddLigSel(b)] : [])" :key="l.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Tipo
                                    <select class="campo" @change="ddPreset(b, l, $event.target.value)">
                                        <option value="" :selected="ddPresetAtual(l) === ''">Personalizado</option>
                                        <template x-for="p in ddPresets" :key="p.id">
                                            <option :value="p.id" :selected="ddPresetAtual(l) === p.id" x-text="p.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Rótulo
                                    <input class="campo" data-rot style="width:8rem" :value="l.rot || ''" @input="l.rot = $event.target.value" placeholder="ex.: sim, 1:N" aria-label="Rótulo do conector">
                                </label>
                                <label class="wf-dd-campo">Caminho
                                    <select class="campo" @change="l.estilo = $event.target.value">
                                        <template x-for="s in ddEstilos" :key="s.id">
                                            <option :value="s.id" :selected="(l.estilo || 'ortogonal') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Traço
                                    <select class="campo" @change="l.traco = $event.target.value">
                                        <template x-for="s in ddTracos" :key="s.id">
                                            <option :value="s.id" :selected="(l.traco || 'solido') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Início
                                    <select class="campo" @change="l.ini = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.ini == null ? 'nenhum' : l.ini) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Fim
                                    <select class="campo" @change="l.fim = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.fim == null ? 'seta' : l.fim) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="/^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : '#94a3b8'" @input="l.cor = $event.target.value" aria-label="Cor do conector">
                                </label>
                                <label class="wf-dd-campo">Espessura
                                    <input type="range" min="1" max="6" step="0.5" style="width:5rem" :value="l.w || 1.8" @input="l.w = Number($event.target.value)" aria-label="Espessura do conector">
                                </label>
                                <button type="button" class="btn-sec" @click="ddInverter(b, l)" title="Trocar origem e destino">⇄ Inverter</button>
                                <button type="button" class="btn-sec" @click="ddRemoverLig(b)">Excluir conector</button>
                            </div>
                        </template>

                        <p class="wf-dd-dica">Duplo clique no vazio cria um elemento · arraste a bolinha de um elemento selecionado até outro (ou até o vazio) para ligar · Tab cria um filho · Enter edita o texto · Ctrl+Z desfaz · Ctrl+roda do mouse dá zoom</p>

                        </div>{{-- fim das opções recolhíveis --}}
                        </div>{{-- fim do topo que acompanha a rolagem --}}

                        {{-- Tela --}}
                        <div class="wf-mapa-col">
                            <div :style="`width:${mmTam(b).w * dd(b).z}px;height:${mmTam(b).h * dd(b).z}px;`">
                                <div class="wf-dd-lona" :class="{ 'grade': dd(b).grade }" tabindex="0" role="application" aria-label="Área do diagrama"
                                     :style="`width:${mmTam(b).w}px;height:${mmTam(b).h}px;transform:scale(${dd(b).z});transform-origin:0 0;`"
                                     @pointerdown="ddLonaDown($event, b)" @dblclick="ddLonaDbl($event, b)" @contextmenu="ddMenu($event, b)"
                                     @keydown="ddTecla($event, b)" @wheel="ddWheel($event, b)">

                                    {{-- Ligações (pai→filho dos mapas e conectores) --}}
                                    <svg class="wf-dd-camada" style="z-index:3;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h"
                                         aria-label="Conectores" x-html="ddLigSvg(b)"
                                         @pointerdown="ddLigDown($event, b)" @dblclick="ddLigDbl($event, b)"></svg>

                                    {{-- Desenho livre --}}
                                    <svg class="wf-dd-camada" aria-label="Desenho livre" :width="mmTam(b).w" :height="mmTam(b).h"
                                         :style="{ zIndex: ui[b.id]?.on ? 8 : 2, pointerEvents: ui[b.id]?.on ? 'auto' : 'none', cursor: ui[b.id]?.borr ? 'cell' : 'crosshair', touchAction: ui[b.id]?.on ? 'none' : 'auto' }"
                                         @pointerdown="penDown($event, b)" @pointermove="penHover($event, b)" @pointerleave="penFora(b)">
                                        <g x-html="tracosSvg(b)"></g>
                                        <path :d="ui[b.id]?.vivo || ''" fill="none" :stroke="ui[b.id]?.cor" :stroke-width="ui[b.id]?.larg" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle x-show="ui[b.id]?.on && ui[b.id]?.borr && ui[b.id]?.cx != null" :cx="ui[b.id]?.cx ?? 0" :cy="ui[b.id]?.cy ?? 0" :r="penRaio(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.2" stroke-dasharray="3 3" style="pointer-events:none"/>
                                    </svg>

                                    {{-- Linha provisória enquanto se arrasta uma ligação --}}
                                    <svg class="wf-dd-camada" style="z-index:5;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true" x-show="dd(b).liga">
                                        <path :d="ddLiveD(b)" fill="none" stroke="var(--prim)" stroke-width="2" stroke-dasharray="6 4" stroke-linecap="round"/>
                                    </svg>

                                    {{-- Elementos --}}
                                    <template x-for="n in ddNos(b)" :key="n.id">
                                        <div class="wf-dd-no" :class="ddClasse(b, n)" :style="ddNoEstilo(n)"
                                             @pointerdown="ddNoDown($event, b, n)" @dblclick.stop="ddEditar(b, n)">
                                            <svg class="wf-dd-forma" :width="ddGeo(n).w" :height="ddGeo(n).h" aria-hidden="true" x-html="ddFormaSvg(n)"></svg>

                                            <template x-if="n.forma === 'entidade'">
                                                <div class="wf-dd-ent">
                                                    <div class="wf-dd-ent-t" x-text="n.t"></div>
                                                    <div class="wf-dd-ent-corpo">
                                                        <template x-for="(c, i) in ddCampos(n)" :key="i">
                                                            <div class="wf-dd-ent-c"><b x-show="c.tag" x-text="c.tag"></b><span x-text="c.txt"></span></div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="wf-dd-txt" x-show="n.forma !== 'entidade' && dd(b).edit !== n.id"
                                                 :class="{ 'fora': ddGeo(n).d.fora, 'raia': n.forma === 'raia' }" x-text="n.t"></div>

                                            <template x-if="dd(b).edit === n.id">
                                                <textarea class="wf-dd-edit" :class="{ 'fora': ddGeo(n).d.fora, 'ent': n.forma === 'entidade' }"
                                                          x-model="n.t" aria-label="Texto do elemento"
                                                          x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                          @focus="ddGravar(b)" @pointerdown.stop @dblclick.stop
                                                          @keydown="ddTeclaEdit($event, b)" @blur="ddFimEdit(b, n)"></textarea>
                                            </template>

                                            <button type="button" class="wf-dd-h t" tabindex="-1" aria-label="Ligar a partir do topo" @pointerdown="ddLigarDown($event, b, n, 't')"></button>
                                            <button type="button" class="wf-dd-h b" tabindex="-1" aria-label="Ligar a partir de baixo" @pointerdown="ddLigarDown($event, b, n, 'b')"></button>
                                            <button type="button" class="wf-dd-h e" tabindex="-1" aria-label="Ligar a partir da esquerda" @pointerdown="ddLigarDown($event, b, n, 'e')"></button>
                                            <button type="button" class="wf-dd-h d" tabindex="-1" aria-label="Ligar a partir da direita" @pointerdown="ddLigarDown($event, b, n, 'd')"></button>
                                            <span class="wf-dd-redim" x-show="ddRedimVisivel(b, n)" title="Redimensionar" @pointerdown="ddRedimDown($event, b, n)"></span>
                                        </div>
                                    </template>

                                    <div class="wf-dd-caixa" x-show="dd(b).caixa" :style="ddCaixaEstilo(b)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Tabletop: mapa com grade e peças arrastáveis --}}
                <template x-if="b.tipo === 'tabletop'">
                    @include('areas.diverso.partials.tabletop')
                </template>
            </div>
        </div>
    </x-diverso.item>
</template>
