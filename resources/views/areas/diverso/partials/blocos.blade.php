@include('areas.diverso.partials.escrita-js')
@verbatim
<script>
/* Work Flower · mini-IDE do bloco "GitHub / VS Code"
   Uso: <div x-data="WFIde(b)"> dentro do bloco do tipo "codigo".
   O projeto fica em b.dados.ide = { arquivos: [{id, caminho, texto}], pastas: [], abertas: [], ativo, layout: {lado, painel, barra} }.
   Não depende de nada do diverso.js.
   Atalhos: Ctrl+S salvar · Ctrl+P abrir arquivo · Ctrl+G ir para linha · Ctrl+F buscar · Ctrl+/ comentar · Alt+↑/↓ mover linha
   · Alt+Shift+↑/↓ duplicar linha · Ctrl+Shift+K excluir linha · Ctrl+Shift+F formatar JSON · Ctrl+Enter executar.
   Botão direito na lista de arquivos: ▶ Executar (.js, .html, .py e, via servidor Judge0, Java/PHP/C/Go/etc.), renomear, excluir, copiar caminho… */
(function () {
    'use strict';

    const U = (window.WFQuadro && window.WFQuadro.util) || {};
    const uid = U.uid || (() => Math.random().toString(36).slice(2, 10));

    const MAX_ARQ = 300, MAX_BYTES = 400000, MAX_TOTAL = 3000000, MAX_SAIDA = 500;
    const LH = 19.5; // altura da linha (13px * 1.5), igual ao CSS
    const IGNORAR = new Set(['node_modules', '.git', 'vendor', 'dist', 'build', '.next', '__pycache__', '.idea', '.venv', 'venv', 'storage', '.cache', 'target', '.gradle', 'obj']);
    const BINARIO = /\.(png|jpe?g|gif|webp|ico|bmp|pdf|zip|rar|7z|gz|tar|mp[34]|wav|ogg|webm|mov|woff2?|ttf|otf|eot|exe|dll|so|bin|psd|sqlite|db|class|jar|war|pyc|apk|dex)$/i;
    const limitar = (v, a, b) => Math.max(a, Math.min(b, v));

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
        php: 'php', blade: 'blade', sql: 'sql',
        py: 'hash', sh: 'hash', rb: 'hash', yml: 'hash', yaml: 'hash', toml: 'hash', env: 'hash', ini: 'hash', properties: 'hash', conf: 'hash',
        css: 'css', scss: 'css', less: 'css',
        html: 'html', htm: 'html', xml: 'html', svg: 'html', vue: 'html',
        json: 'json', md: 'md',
    };
    const NOME = { blade: 'Blade', php: 'PHP', java: 'Java', kt: 'Kotlin', kts: 'Kotlin', scala: 'Scala', groovy: 'Groovy', gradle: 'Gradle', cs: 'C#', go: 'Go', rs: 'Rust', c: 'C', h: 'C', cpp: 'C++', py: 'Python', rb: 'Ruby', sh: 'Shell', sql: 'SQL', js: 'JavaScript', ts: 'TypeScript', tsx: 'TSX', jsx: 'JSX', html: 'HTML', css: 'CSS', json: 'JSON', md: 'Markdown', yml: 'YAML', yaml: 'YAML', xml: 'XML', vue: 'Vue' };
    const TIPO = { blade: 'BL', php: 'PHP', java: 'JV', kt: 'KT', cs: 'C#', go: 'GO', rs: 'RS', py: 'PY', sql: 'SQL', js: 'JS', ts: 'TS', html: '<>', css: '#', json: '{}', md: 'MD' };
    // o que realmente não dá para executar isolado (Blade precisa do Laravel; .h é só cabeçalho; kts/gradle são scripts de build)
    const NAO_RODA = new Set(['blade', 'h', 'kts', 'gradle']);
    // linguagens executadas no servidor Judge0 (o navegador não roda Java, PHP, C, Go…)
    const JUDGE0 = 'https://ce.judge0.com'; // troque aqui se hospedar o seu próprio Judge0
    const REMOTO = {
        java: { id: 62, nome: 'Java' }, kt: { id: 78, nome: 'Kotlin' }, cs: { id: 51, nome: 'C#' },
        go: { id: 60, nome: 'Go' }, rs: { id: 73, nome: 'Rust' }, c: { id: 50, nome: 'C' }, cpp: { id: 54, nome: 'C++' },
        php: { id: 68, nome: 'PHP' }, rb: { id: 72, nome: 'Ruby' }, sql: { id: 82, nome: 'SQL (SQLite)' },
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
    const COMENT = { js: '//', java: '//', php: '//', hash: '#', sql: '--', css: ['/*', '*/'], html: ['<\!--', '-->'], md: ['<\!--', '-->'], blade: ['{{--', '--}}'] };
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
        let htmlId = null, timerPrev = 0; // página aberta na prévia (para atualizar sozinha ao editar)
        let timerAuto = 0;                // auto-salvamento na pasta do computador
        const cacheR = { k: '', t: null, v: '' }, cacheN = { n: -1, v: '' }; // realce e numeração só recalculam quando mudam

        return {
            sel: '', fechadas: [], ln: 1, col: 1, selN: 0, msg: '', ver: 0,
            saida: [], srcdoc: '', painel: null, disco: '', movendo: false, menuCtx: null,
            busca: { on: false, q: '', r: '', todos: false, caso: false, regex: false },
            pal: { on: false, modo: 'arq', q: '', i: 0 },
            suporteDisco: typeof window.showDirectoryPicker === 'function',
            // layout (dados reativos próprios; "painel" acima é o console/prévia aberto, por isso os nomes diferentes)
            largLado: I.layout.lado, altPainel: I.layout.painel, barra: I.layout.barra, auto: !!I.layout.auto,

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
                if (n.k === 'a') {
                    const f = I.arquivos.find(x => x.caminho === n.caminho);
                    if (!f) return;
                    pend.push(f.caminho);
                    f.caminho = novo;
                    this.sel = novo;
                    this.atualizarPrevia();
                    this.agendarAuto();
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
                return ['js', 'mjs', 'py'].includes(l) || !!REMOTO[l] || this.htmls().length > 0;
            },
            executar() {
                const f = this.arqAtivo(), e = f ? extDe(f.caminho) : '';
                const pagina = this.paginaPara(f);
                if (pagina) return this.rodarHtml(pagina);
                // Python roda no navegador via Pyodide
                if (f && e === 'py') return this.rodarPy(f);
                // nenhum HTML usa este arquivo: JavaScript solto roda sozinho, só com o console
                if (f && (e === 'js' || e === 'mjs')) return this.rodarJs(f);
                // Java, PHP, C, Go…: executa no servidor Judge0
                if (f && REMOTO[lingua(f.caminho)]) return this.rodarRemoto(f);
                // Blade e afins não rodam isolados
                if (f && NAO_RODA.has(lingua(f.caminho))) {
                    this.aviso('Blade precisa do Laravel para rodar. Use "Baixar ZIP" ou "Abrir pasta" e rode no seu projeto.');
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
                return ['js', 'mjs', 'py'].includes(l) || !!REMOTO[l] || ehHtml(n.caminho);
            },
            itensCtx() {
                const m = this.menuCtx;
                if (!m) return [];
                const n = m.n, out = [];
                if (n && n.k === 'a') {
                    const ok = this.rodavel(n), l = lingua(n.caminho), e = extDe(n.caminho);
                    out.push({ id: 'exec', nome: '▶ Executar', off: !ok, dica: ok ? (REMOTO[l] ? 'servidor' : e === 'py' ? 'Python' : e === 'js' || e === 'mjs' ? 'JavaScript' : 'Prévia') : 'não roda aqui' });
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
                const e = extDe(f.caminho);
                if (ehHtml(f.caminho)) return this.rodarHtml(f);
                if (e === 'py') return this.rodarPy(f);
                if (e === 'js' || e === 'mjs') return this.rodarJs(f);
                if (REMOTO[lingua(f.caminho)]) return this.rodarRemoto(f);
                this.executar();
            },

            /** Python no navegador (Pyodide). Os arquivos do projeto ficam num sistema de arquivos virtual, então `import outro_modulo` funciona. */
            rodarPy(f) {
                const n = ++token;
                htmlId = null;
                this.saida = [];
                const arqs = {};
                I.arquivos.forEach(x => { arqs[x.caminho] = x.texto; });
                const dados = JSON.stringify({ arqs, main: f.caminho }).replace(/</g, '\\u003c');
                const boot = `(async()=>{
const D=${dados};
try{
console.info('Carregando o Python (Pyodide)… na primeira vez demora alguns segundos.');
const py=await loadPyodide();
py.setStdout({batched:s=>console.log(s)});
py.setStderr({batched:s=>console.error(s)});
py.setStdin({stdin:()=>prompt('O programa pede uma entrada (input):')});
const fs=py.FS;
const dirs=p=>{const a=p.split('/');a.pop();let c='/home/pyodide';a.forEach(s=>{c+='/'+s;try{fs.mkdir(c)}catch(e){}})};
for(const k in D.arqs){dirs(k);fs.writeFile('/home/pyodide/'+k,D.arqs[k])}
await py.loadPackagesFromImports(D.arqs[D.main]);
py.globals.set('_main','/home/pyodide/'+D.main);
await py.runPythonAsync('import os,sys,runpy\\nd=os.path.dirname(_main)\\nos.chdir(d)\\nsys.path.insert(0,"/home/pyodide")\\nsys.path.insert(0,d)\\nrunpy.run_path(_main,run_name="__main__")');
console.info('— fim da execução —');
}catch(e){console.error(String(e&&e.message||e))}
})();`;
                this.srcdoc = '<!doctype html>' + shim(n)
                    + '<script src="https://cdn.jsdelivr.net/pyodide/v0.26.4/full/pyodide.js"><\/script>'
                    + '<script>' + boot + '<\/script>';
                this.painel = 'saida';
            },
            rodarJs(f) {
                const n = ++token;
                htmlId = null;
                this.saida = [];
                this.srcdoc = '<!doctype html>' + shim(n) + '<script>' + f.texto.replace(/<\/script/gi, '<\\/script') + '<\/script>';
                this.painel = 'saida';
            },
            /** Java, Kotlin, C#, Go, Rust, C, C++, PHP, Ruby, SQL, Bash… executados no servidor Judge0 (um arquivo por vez). */
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
    .wf-ide-saida { flex: 1 1 0; min-height: 0; min-width: 0; overflow: auto; padding: .4rem .6rem; font: 12px/1.45 ui-monospace, Menlo, Consolas, monospace; }
    .wf-ide-log { white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; border-bottom: 1px dashed var(--linha); padding: .1rem 0; }
    .wf-ide-log.l-error { color: #f87171; }
    .wf-ide-log.l-warn { color: #fbbf24; }
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

                <template x-if="b.tipo === 'texto'">
                    <div class="wf-es" x-data="WFEscrita(b)" x-init="iniciar()"
                        @click.outside="foco = null; menu = null; opcBl = null; sumario = false; tipoMenu = false; limparSel()">

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
                                    @dragstart="dragIni($event, bl)" @dragover.prevent="sobre = i" @drop.prevent="soltar(i)"
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

                                        <template x-if="bl.t !== 'hr'">
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
                            <div class="wf-es-fim" @click="aoFim()" @dragover.prevent @drop.prevent="soltar(blocos.length)" aria-hidden="true"></div>
                        </div>

                        <div class="wf-es-rodape" aria-live="polite">
                            <span x-text="palavras() + ' palavra(s) · ' + caracteres() + ' caractere(s) · ~' + leitura() + ' min de leitura'"></span>
                            <span x-show="tarefas().total" x-cloak x-text="'Tarefas: ' + tarefas().feitas + '/' + tarefas().total"></span>
                            <span x-show="selecao.length" x-cloak x-text="selecao.length + ' bloco(s) selecionado(s)'"></span>
                            <span x-show="b.dados.opcoes !== false" x-cloak>Dica: “/” abre os comandos · Esc seleciona o bloco · Shift+↑/↓ amplia · Ctrl+Alt+1/2/3 vira título</span>
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
                                        <button type="button" class="btn-sec" @click="novaPasta()" title="Nova pasta">+ Pasta</button>
                                        <label class="btn-sec" title="Enviar arquivos de texto do computador">Enviar
                                            <input type="file" multiple class="hidden" @change="enviar($event.target.files); $event.target.value = ''">
                                        </label>
                                        <span class="sep"></span>
                                        <button type="button" class="btn-sec" @click="salvar()" title="Salvar (Ctrl+S)">Salvar</button>
                                        <button type="button" class="btn-sec" @click="baixar()" x-show="arqAtivo()" title="Baixar o arquivo aberto">Baixar arquivo</button>
                                        <button type="button" class="btn-sec" @click="baixarZip()" title="Baixar o projeto inteiro em ZIP">Baixar ZIP</button>
                                        <span class="sep" x-show="suporteDisco"></span>
                                        <button type="button" class="btn-sec" x-show="suporteDisco" @click="abrirPasta()" title="Abrir uma pasta do computador (Chrome/Edge)">Abrir pasta</button>
                                        <button type="button" class="btn-sec" x-show="disco" x-cloak @click="salvarDisco()" :title="'Gravar tudo em ' + disco">Salvar na pasta</button>
                                        <span class="sep"></span>
                                        <button type="button" class="btn-sec" x-show="podeExecutar()" @click="executar()" title="Executar o arquivo aberto: .js, .py, .html (prévia) ou Java, PHP, C, Go… no servidor (Ctrl+Enter)">▶ Executar</button>
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
                                    <aside class="wf-ide-lado" aria-label="Arquivos do projeto" @click.self="sel = ''" @contextmenu="abrirMenu(null, $event)">
                                        <template x-for="n in arvore()" :key="n.k + ':' + n.caminho">
                                            <div class="wf-ide-no" :class="{ 'ativo': n.id && n.id === b.dados.ide.ativo, 'sel': sel === n.caminho }"
                                                 :style="`padding-left:${.4 + n.nivel * .9}rem`" @click="clicar(n)" @contextmenu="abrirMenu(n, $event)" :title="n.caminho">
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
                                                <span style="flex:1"></span>
                                                <button type="button" class="link-prim" x-show="painel === 'previa'" @click="telaCheiaPrevia()" title="Prévia em tela cheia">Tela cheia</button>
                                                <button type="button" class="link-prim" @click="limparSaida()">Limpar</button>
                                                <button type="button" class="mini-btn" @click="fecharPainel()" aria-label="Fechar painel">✕</button>
                                            </div>
                                            <div class="wf-ide-saida" x-show="painel === 'saida'">
                                                <p class="texto-2" x-show="!saida.length">Sem saída. Use console.log() (JS), print() (Python), System.out.println() (Java), echo (PHP)…</p>
                                                <template x-for="(l, i) in saida" :key="i">
                                                    <div class="wf-ide-log" :class="'l-' + l.t" x-text="l.m"></div>
                                                </template>
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
                                    <a class="btn-sec" :href="imagens[b.id]" download="imagem.png" title="Baixar a imagem atual">Baixar</a>
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

                {{-- Mapa mental e diagramas (BPMN, fluxogramas, ER/UML). Lógica em public/js/diagrama.js (métodos dd*). --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="wf-dd h-full" data-dd x-init="dd(b)">

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

                        {{-- Tela --}}
                        <div class="wf-mapa-col">
                            <div :style="`width:${mmTam(b).w * dd(b).z}px;height:${mmTam(b).h * dd(b).z}px;`">
                                <div class="wf-dd-lona" :class="{ 'grade': dd(b).grade }" tabindex="0" role="application" aria-label="Área do diagrama"
                                     :style="`width:${mmTam(b).w}px;height:${mmTam(b).h}px;transform:scale(${dd(b).z});transform-origin:0 0;`"
                                     @pointerdown="ddLonaDown($event, b)" @dblclick="ddLonaDbl($event, b)"
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
