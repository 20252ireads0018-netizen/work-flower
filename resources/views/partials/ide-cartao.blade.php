{{-- Cartão "GitHub / VS Code" (tipo codigo): a mini-IDE (WFIde), as funções dos repositórios (WFRepo) e o estilo.
     Incluído uma vez por página: no Diverso (areas/diverso/partials/blocos) e nas áreas que receberam o cartão
     (partials/tipos-diverso). O cartão em si é o partials/codigo-cartao. --}}
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
<script>
    // Confere se o editor carregado é o que este bloco espera (versão 5 ou mais nova).
    if (!window.WFIde || (window.WFIde.versao || 0) < 5) {
        console.error('[Work Flower] O editor carregado está desatualizado (versão ' + ((window.WFIde && window.WFIde.versao) || 'antiga') + ', esperada 5+). Recarregue a página com Ctrl+F5.');
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
