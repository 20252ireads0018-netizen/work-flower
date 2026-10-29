/* Work Flower · motores da mini-IDE (cartão "GitHub / VS Code" do Diverso; a IDE fica em areas/diverso/partials/blocos.blade.php).
   - Python (Pyodide), PHP 8.4 (php-wasm) e SQL (SQLite, sql.js) rodam NO NAVEGADOR, num iframe isolado (sandbox sem
     acesso à página nem aos cookies) que fica carregado: só a primeira execução de cada linguagem baixa o motor.
   - Blade: as views .blade.php são convertidas para PHP aqui e renderizadas pelo PHP do navegador (prévia com dados
     de exemplo de dados.json). Controllers, models e rotas do Laravel não rodam sem o framework: a IDE confere a sintaxe.
   - WFDicas: explica em português por que o código deu erro e acha chaves/parênteses sem par antes de rodar.

   WFMotores.rodar({ lingua, arqs, main, projeto, modo }, aoMsg) -> Promise (resolve no fim)
     aoMsg({ t: 'log'|'error'|'info'|'tabela'|'html'|'esquema', ... })
   WFMotores.aquecer(lingua) baixa o motor em segundo plano. */
(function () {
    'use strict';
    if (window.WFMotores) return;

    const PYODIDE = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/pyodide.js';
    const PHPWASM = 'https://cdn.jsdelivr.net/npm/php-wasm@0.2.0/PhpWeb.mjs';
    const SQLJS = 'https://cdn.jsdelivr.net/npm/sql.js@1.15.2/dist/';

    /* ---------- código que roda dentro do iframe isolado ---------- */
    const MOTOR = `
const post = (id, t, extra) => parent.postMessage(Object.assign({ wfMotor: 1, id, t }, extra || {}), '*');
const carregarScript = src => new Promise((ok, falha) => { const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = () => falha(new Error('Não consegui baixar ' + src)); document.head.appendChild(s); });

/* Python */
let py = null, pyP = null, pyId = null;
function motorPy() {
  return pyP ??= (async () => {
    await carregarScript(${JSON.stringify(PYODIDE)});
    py = await loadPyodide();
    py.setStdout({ batched: s => pyId && post(pyId, 'log', { m: s }) });
    py.setStderr({ batched: s => pyId && post(pyId, 'error', { m: s }) });
    py.setStdin({ stdin: () => prompt('O programa pede uma entrada (input):') });
    return py;
  })().catch(e => { pyP = null; throw e; });
}
// traceback só com as linhas do projeto (sem as do Pyodide e do runpy) e caminhos relativos
function limparTraceback(t, raiz) {
  let manter = true;
  const fora = t.split("\\n").filter(l => {
    const m = /^  File "(.+?)"/.exec(l);
    if (m) return (manter = m[1].startsWith(raiz));
    if (/^    /.test(l)) return manter;
    manter = true;
    return true;
  });
  return fora.join("\\n").split(raiz + "/").join("").trim();
}
async function rodarPy(id, arqs, main) {
  const p = await motorPy();
  pyId = id;
  const fs = p.FS, raiz = '/home/pyodide/proj';
  const apagar = d => { try { for (const n of fs.readdir(d)) { if (n === '.' || n === '..') continue; const c = d + '/' + n; if (fs.isDir(fs.stat(c).mode)) { apagar(c); fs.rmdir(c); } else fs.unlink(c); } } catch (e) {} };
  apagar(raiz); try { fs.mkdir(raiz); } catch (e) {}
  for (const k in arqs) { const a = k.split('/'); a.pop(); let c = raiz; a.forEach(s => { c += '/' + s; try { fs.mkdir(c); } catch (e) {} }); fs.writeFile(raiz + '/' + k, arqs[k]); }
  await p.loadPackagesFromImports(arqs[main]);
  p.globals.set('_main', raiz + '/' + main);
  p.globals.set('_raiz', raiz);
  // módulos do projeto importados na execução anterior saem do cache (senão a mudança no arquivo não aparece)
  try {
    await p.runPythonAsync('import os,sys,runpy\\nfor _k,_m in list(sys.modules.items()):\\n    if getattr(_m,"__file__","") and str(_m.__file__).startswith(_raiz): del sys.modules[_k]\\nos.chdir(os.path.dirname(_main))\\nfor _d in (_raiz, os.path.dirname(_main)):\\n    if _d not in sys.path: sys.path.insert(0,_d)\\nrunpy.run_path(_main,run_name="__main__")');
  } catch (e) { throw new Error(limparTraceback(String(e && e.message || e), raiz)); }
}

/* PHP */
let php = null, phpP = null, phpId = null, phpHtml = null;
function motorPhp() {
  return phpP ??= (async () => {
    // o iframe isolado não pode usar Web Locks nem IndexedDB: sem eles o php-wasm usa uma trava local e não persiste o disco
    try { Object.defineProperty(navigator, 'locks', { value: undefined, configurable: true }); } catch (e) {}
    const { PhpWeb } = await import(${JSON.stringify(PHPWASM)});
    const p = new PhpWeb({ version: '8.4', autoTransaction: false });
    const pronto = new Promise(ok => p.addEventListener('ready', ok, { once: true }));
    p.addEventListener('output', e => { const s = (e.detail || []).join(''); if (phpHtml !== null) phpHtml += s; else if (phpId) post(phpId, 'log', { m: s }); });
    p.addEventListener('error', e => { const s = (e.detail || []).join(''); if (phpId) post(phpId, 'error', { m: s }); });
    await pronto;
    php = p;
    return p;
  })().catch(e => { phpP = null; throw e; });
}
// grava direto no FS do Emscripten: p.mkdir/p.writeFile entram numa fila que espera timers depois de cada chamada
async function escreverPhp(p, arqs) {
  const FS = (await p.binary).FS;
  for (const k in arqs) {
    const a = ('/app/' + k).split('/'); a.pop();
    let c = '';
    for (const s of a) { if (!s) continue; c += '/' + s; try { FS.mkdir(c); } catch (e) {} }
    FS.writeFile('/app/' + k, arqs[k]);
  }
}
async function rodarPhp(id, arqs, codigo, html) {
  const p = await motorPhp();
  // refresh e run "diretos" (PhpBase.refresh e _run, versão fixada 0.2.0): os públicos passam pela fila com espera de timers
  const base = Object.getPrototypeOf(Object.getPrototypeOf(Object.getPrototypeOf(p)));
  const direto = typeof p._run === 'function' && base && typeof base.refresh === 'function';
  await (direto ? base.refresh.call(p) : p.refresh());   // estado limpo a cada execução (funções e classes não "redeclaram")
  await escreverPhp(p, arqs);
  phpId = id; phpHtml = html ? '' : null;
  await (direto ? p._run(codigo) : p.run(codigo));
  p.flush();
  if (html) post(id, 'html', { m: phpHtml });
  phpHtml = null;
}

/* SQL */
let SQL = null, sqlP = null;
const bancos = {};
function motorSql() {
  return sqlP ??= (async () => {
    await carregarScript(${JSON.stringify(SQLJS + 'sql-wasm.js')});
    SQL = await initSqlJs({ locateFile: f => ${JSON.stringify(SQLJS)} + f });
    return SQL;
  })().catch(e => { sqlP = null; throw e; });
}
const banco = proj => bancos[proj] ??= new SQL.Database();
function esquema(id, db) {
  const t = db.exec("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
  const tabelas = (t[0] ? t[0].values : []).map(([n]) => {
    const q = '"' + String(n).replace(/"/g, '""') + '"';
    const cols = db.exec('PRAGMA table_info(' + q + ')');
    const total = db.exec('SELECT COUNT(*) FROM ' + q);
    return { nome: n, colunas: cols[0] ? cols[0].values.map(c => c[1] + (c[2] ? ' ' + c[2] : '') + (c[5] ? ' 🔑' : '')) : [], total: total[0] ? total[0].values[0][0] : 0 };
  });
  post(id, 'esquema', { tabelas });
}
async function rodarSql(id, proj, texto) {
  await motorSql();
  const db = banco(proj);
  let pos = 0, n = 0;
  try {
    for (const st of db.iterateStatements(texto)) {
      const sqlTxt = st.getSQL();
      const i = texto.indexOf(sqlTxt.trim().slice(0, 40), pos);
      const inicio = i >= 0 ? i : pos;
      const cols = st.getColumnNames();
      if (cols.length) {
        const linhas = []; let total = 0;
        while (st.step()) { total++; if (linhas.length < 500) linhas.push(st.get()); }
        post(id, 'tabela', { colunas: cols, linhas, total, titulo: sqlTxt.trim().split('\\n')[0].slice(0, 80) });
      } else {
        st.step();
        const mud = db.getRowsModified();
        const cmd = (sqlTxt.trim().match(/^\\w+/) || ['?'])[0].toUpperCase();
        if (['INSERT', 'UPDATE', 'DELETE', 'REPLACE'].includes(cmd)) post(id, 'info', { m: cmd + ': ' + mud + (mud === 1 ? ' linha' : ' linhas') });
      }
      st.free();
      n++;
      pos = inicio + sqlTxt.trim().length; // fim deste comando: um erro no próximo aponta para depois daqui
    }
    post(id, 'info', { m: '— ' + n + (n === 1 ? ' comando executado' : ' comandos executados') + ' —' });
  } catch (e) {
    const off = texto.slice(pos).search(/[^\\s;]/);
    const linha = texto.slice(0, pos + Math.max(0, off)).split('\\n').length;
    post(id, 'error', { m: String(e.message || e) + ' (perto da linha ' + linha + ')', linha });
  }
  esquema(id, db);
}

addEventListener('message', async ev => {
  const d = ev.data;
  if (!d || !d.wfMotor || ev.source !== parent) return;
  const id = d.id;
  try {
    if (d.acao === 'aquecer') { await ({ py: motorPy, php: motorPhp, blade: motorPhp, sql: motorSql }[d.lingua] || (async () => {}))(); }
    else if (d.acao === 'py') await rodarPy(id, d.arqs, d.main);
    else if (d.acao === 'php') await rodarPhp(id, d.arqs, d.codigo, !!d.html);
    else if (d.acao === 'sql') await rodarSql(id, d.projeto, d.texto);
    else if (d.acao === 'sqltabela') { await motorSql(); const r = banco(d.projeto).exec('SELECT * FROM "' + String(d.tabela).replace(/"/g, '""') + '" LIMIT 200'); post(id, 'tabela', { colunas: r[0] ? r[0].columns : [], linhas: r[0] ? r[0].values : [], total: r[0] ? r[0].values.length : 0, titulo: d.tabela }); }
    else if (d.acao === 'sqlesquema') { await motorSql(); esquema(id, banco(d.projeto)); }
    else if (d.acao === 'sqlreset') { if (bancos[d.projeto]) { bancos[d.projeto].close(); delete bancos[d.projeto]; } await motorSql(); esquema(id, banco(d.projeto)); }
  } catch (e) {
    post(id, 'error', { m: String(e && e.message || e) });
  }
  post(id, 'fim');
});
`;

    let frame = null, seq = 0;
    const espera = new Map();
    function garantirFrame() {
        if (frame && document.body.contains(frame)) return frame;
        frame = document.createElement('iframe');
        frame.setAttribute('sandbox', 'allow-scripts allow-modals');
        frame.setAttribute('aria-hidden', 'true');
        frame.title = 'Motores da IDE';
        // 1 px dentro da tela e transparente: fora da tela o Chrome desacelera os timers do iframe (o motor fica lento)
        frame.style.cssText = 'position:fixed;width:1px;height:1px;border:0;left:0;bottom:0;opacity:0;pointer-events:none;z-index:-1';
        frame.tabIndex = -1;
        frame.srcdoc = '<!doctype html><meta char' + 'set="utf-8"><script type="module">' + MOTOR.replace(/<\/script/gi, '<\\/script') + '<\/script>';
        document.body.appendChild(frame);
        frame._pronto = new Promise(ok => frame.addEventListener('load', ok, { once: true }));
        return frame;
    }
    window.addEventListener('message', ev => {
        const d = ev.data;
        if (!d || !d.wfMotor || !frame || ev.source !== frame.contentWindow) return;
        const e = espera.get(d.id);
        if (!e) return;
        if (d.t === 'fim') { espera.delete(d.id); e.ok(); } else e.aoMsg(d);
    });
    async function enviar(msg, aoMsg) {
        const f = garantirFrame();
        await f._pronto;
        const id = 'm' + (++seq);
        return new Promise(ok => {
            espera.set(id, { ok, aoMsg: aoMsg || (() => {}) });
            f.contentWindow.postMessage(Object.assign({ wfMotor: 1, id }, msg), '*');
        });
    }
    const aquecidos = new Set();
    function aquecer(lingua) {
        if (!['py', 'php', 'blade', 'sql'].includes(lingua) || aquecidos.has(lingua === 'blade' ? 'php' : lingua)) return;
        aquecidos.add(lingua === 'blade' ? 'php' : lingua);
        enviar({ acao: 'aquecer', lingua }).catch(() => aquecidos.delete(lingua));
    }
    /** Para tudo: recria o motor (útil se um programa travou num laço infinito). */
    function reiniciar() {
        if (frame) frame.remove();
        frame = null; aquecidos.clear();
        espera.forEach(e => e.ok()); espera.clear();
    }

    /* ---------- Blade -> PHP ---------- */
    const PRELUDIO = '<' + `?php
error_reporting(E_ALL); ini_set('display_errors', '1');
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function url($p = '') { return '/' . ltrim((string) $p, '/'); }
function asset($p) { return url($p); }
function route($n, $p = []) { return '/' . str_replace('.', '/', (string) $n); }
function old($k = null, $d = null) { return $d; }
function csrf_token() { return 'token-de-exemplo'; }
function csrf_field() { return '<input type="hidden" name="_token" value="token-de-exemplo">'; }
function method_field($m) { return '<input type="hidden" name="_method" value="' . e($m) . '">'; }
function __($s, $r = []) { return $s; }
function config($k = null, $d = null) { return $d; }
function session($k = null, $d = null) { return $d; }
function now() { return new DateTime(); }
function auth() { return new class { function check() { return false; } function user() { return null; } function id() { return null; } }; }
class __Erros { function any() { return false; } function has($k) { return false; } function first($k = null) { return ''; } function all() { return []; } }
class __Blade {
    public $sec = []; public $pilha = []; public $empurra = [];
    function incluir($n, $v = []) {
        $arq = '/app/__views/' . $n . '.php';
        if (!is_file($arq)) return '<!-- view "' . e($n) . '" não encontrada -->';
        // variáveis internas (__arq, __blade…) não passam de uma view para a outra; a view enxerga o $__blade e o $errors
        return (function ($__arq, $__v) {
            extract(array_filter($__v, fn ($k) => !str_starts_with((string) $k, '__'), ARRAY_FILTER_USE_KEY));
            $__blade = $this; $errors = $errors ?? $GLOBALS['errors'];
            ob_start(); include $__arq; return ob_get_clean();
        })($arq, $v);
    }
    function comecar($n) { $this->pilha[] = $n; ob_start(); }
    function terminar() { $n = array_pop($this->pilha); $c = ob_get_clean(); if (!isset($this->sec[$n])) $this->sec[$n] = $c; return $this->sec[$n]; }
    function secao($n, $d = '') { return $this->sec[$n] ?? $d; }
    function empurrar($n) { $this->pilha[] = '__push:' . $n; ob_start(); }
    function soltar() { $n = substr(array_pop($this->pilha), 7); $this->empurra[$n] = ($this->empurra[$n] ?? '') . ob_get_clean(); }
    function pilha($n) { return $this->empurra[$n] ?? ''; }
}
$__blade = new __Blade();
$errors = new __Erros();
`;

    /** Lê "@nome(args)" a partir de i, com parênteses equilibrados. */
    function argumentos(s, i) {
        if (s[i] !== '(') return null;
        let n = 0, q = '';
        for (let j = i; j < s.length; j++) {
            const c = s[j];
            if (q) { if (c === '\\') j++; else if (c === q) q = ''; continue; }
            if (c === '"' || c === "'") q = c;
            else if (c === '(') n++;
            else if (c === ')') { n--; if (!n) return { args: s.slice(i + 1, j), fim: j + 1 }; }
        }
        return null;
    }
    const P = c => '<' + '?php ' + c + ' ?>';
    /** Converte um template Blade em PHP (o essencial: ecos, condicionais, laços, layouts, seções, includes, pilhas). */
    function compilarBlade(src) {
        let pai = null;
        src = src.replace(/\{\{--[\s\S]*?--\}\}/g, '')
            .replace(/@\{\{/g, '\u0001')
            .replace(/@php\b([\s\S]*?)@endphp/g, (m, c) => P(c))
            .replace(/\{!!\s*([\s\S]+?)\s*!!\}/g, (m, c) => P('echo ' + c + ';'))
            .replace(/\{\{\s*([\s\S]+?)\s*\}\}/g, (m, c) => P('echo e(' + c + ');'));
        let out = '', i = 0;
        const re = /@([A-Za-z]+)/g;
        let m;
        while ((m = re.exec(src))) {
            const nome = m[1];
            if (m.index > 0 && /[\w@]/.test(src[m.index - 1])) continue; // e-mail, @@
            let k = m.index + m[0].length;
            const temEspaco = src[k] === ' ' && src[k + 1] === '(';
            const a = argumentos(src, temEspaco ? k + 1 : k);
            const args = a ? a.args : '';
            let troca = null;
            switch (nome) {
                case 'if': troca = P('if (' + args + '):'); break;
                case 'elseif': troca = P('elseif (' + args + '):'); break;
                case 'else': troca = P('else:'); break;
                case 'endif': case 'endunless': case 'endisset': case 'endauth': case 'endguest': case 'endforelse': troca = P('endif;'); break;
                case 'unless': troca = P('if (!(' + args + ')):'); break;
                case 'isset': troca = P('if (isset(' + args + ')):'); break;
                case 'empty': troca = a ? P('if (empty(' + args + ')):') : P('endforeach; if ($__vazio):'); break;
                case 'endempty': troca = P('endif;'); break;
                case 'foreach': troca = P('foreach (' + args + '):'); break;
                case 'forelse': troca = P('$__vazio = true; foreach (' + args + '): $__vazio = false;'); break;
                case 'endforeach': troca = P('endforeach;'); break;
                case 'for': troca = P('for (' + args + '):'); break;
                case 'endfor': troca = P('endfor;'); break;
                case 'while': troca = P('while (' + args + '):'); break;
                case 'endwhile': troca = P('endwhile;'); break;
                case 'auth': troca = P('if (auth()->check()):'); break;
                case 'guest': troca = P('if (!auth()->check()):'); break;
                case 'csrf': troca = P('echo csrf_field();'); break;
                case 'method': troca = P('echo method_field(' + args + ');'); break;
                case 'json': troca = P('echo json_encode(' + args + ');'); break;
                case 'dump': case 'dd': troca = P('var_dump(' + args + ');'); break;
                case 'include': case 'includeIf': {
                    const [view, ...resto] = dividirArgs(args);
                    troca = P('echo $__blade->incluir(' + view + ', array_merge(get_defined_vars(), ' + (resto.join(',') || '[]') + '));');
                    break;
                }
                case 'extends': pai = dividirArgs(args)[0]; troca = ''; break;
                case 'section': {
                    const p = dividirArgs(args);
                    troca = p.length > 1 ? P('$__blade->sec[' + p[0] + '] ??= e(' + p[1] + ');') : P('$__blade->comecar(' + p[0] + ');');
                    break;
                }
                case 'endsection': case 'stop': troca = P('$__blade->terminar();'); break;
                case 'show': troca = P('echo $__blade->terminar();'); break;
                case 'yield': { const p = dividirArgs(args); troca = P('echo $__blade->secao(' + p[0] + (p[1] ? ', ' + p[1] : '') + ');'); break; }
                case 'push': case 'prepend': troca = P('$__blade->empurrar(' + args + ');'); break;
                case 'endpush': case 'endprepend': troca = P('$__blade->soltar();'); break;
                case 'stack': troca = P('echo $__blade->pilha(' + args + ');'); break;
                case 'parent': troca = ''; break;
            }
            if (troca === null) continue;
            out += src.slice(i, m.index) + troca;
            i = a && !['else', 'csrf', 'endif', 'parent'].includes(nome) ? a.fim : k;
            re.lastIndex = i;
        }
        out += src.slice(i);
        out = out.replace(/\u0001/g, '{{');
        // com @extends, o que está fora das seções não aparece (como no Laravel) e o layout vem depois
        if (pai) out = P('ob_start();') + out + P('ob_end_clean(); echo $__blade->incluir(' + pai + ', get_defined_vars());');
        return out;
    }
    /** Divide argumentos no nível de cima: "'a', ['x' => 1]" -> ["'a'", "['x' => 1]"]. */
    function dividirArgs(s) {
        const r = []; let n = 0, q = '', ini = 0;
        for (let j = 0; j < s.length; j++) {
            const c = s[j];
            if (q) { if (c === '\\') j++; else if (c === q) q = ''; continue; }
            if (c === '"' || c === "'") q = c;
            else if ('([{'.includes(c)) n++;
            else if (')]}'.includes(c)) n--;
            else if (c === ',' && !n) { r.push(s.slice(ini, j).trim()); ini = j + 1; }
        }
        if (s.slice(ini).trim()) r.push(s.slice(ini).trim());
        return r;
    }
    /** "resources/views/posts/lista.blade.php" -> "posts.lista" */
    function nomeView(caminho) {
        return caminho.replace(/^.*?resources\/views\//, '').replace(/\.blade\.php$/i, '').replace(/\//g, '.');
    }

    /* ---------- dicas ---------- */
    const DICAS = {
        js: [
            [/(\w+) is not defined/, m => `“${m[1]}” não existe neste ponto. Confira se o nome está escrito igual (maiúsculas contam) e se foi criado antes com let, const ou function.`],
            [/Cannot read propert(?:y|ies) of (undefined|null)(?: \(reading '([^']+)'\))?/, m => `Você tentou usar ${m[2] ? '“.' + m[2] + '”' : 'uma propriedade'} de algo que está ${m[1] === 'null' ? 'vazio (null)' : 'indefinido (undefined)'}. Talvez o elemento não exista na página (document.getElementById retornou null) ou a variável ainda não recebeu valor.`],
            [/(\S+) is not a function/, m => `“${m[1]}” não é uma função. Confira o nome do método (ex.: addEventListener, querySelector) ou se a variável guarda mesmo uma função.`],
            [/Unexpected token '?([^'\s]+)'?/, m => `Tem um “${m[1]}” onde o JavaScript não esperava. Costuma ser vírgula, parêntese ou chave a mais ou faltando na linha indicada ou na anterior.`],
            [/Unexpected end of input/, () => 'O arquivo terminou antes da hora: falta fechar alguma chave }, parêntese ) ou colchete ].'],
            [/Assignment to constant variable/, () => 'Você tentou mudar uma variável criada com const. Use let se o valor precisa mudar.'],
            [/has already been declared/, () => 'A mesma variável foi criada duas vezes. Remova o let/const da segunda vez ou use outro nome.'],
            [/Unexpected identifier/, () => 'Duas palavras seguidas sem nada entre elas: costuma faltar vírgula, ponto e vírgula, operador ou fechar aspas.'],
            [/Invalid or unexpected token/, () => 'Caractere inválido: confira aspas abertas e não fechadas ou um símbolo colado de outro lugar.'],
            [/Maximum call stack size exceeded/, () => 'Uma função está chamando a si mesma sem parar (recursão sem fim). Confira a condição de parada.'],
            [/Failed to fetch/, () => 'O pedido de rede falhou: o endereço pode estar errado, sem internet ou o site não permite ser lido daqui (CORS).'],
        ],
        py: [
            [/NameError: name '(\w+)' is not defined/, m => `“${m[1]}” não existe. Confira a escrita (maiúsculas contam), se a variável foi criada antes ou se falta importar o módulo.`],
            [/IndentationError: (expected an indented block|unexpected indent|unindent does not match)/, m => m[1].startsWith('expected') ? 'Depois de uma linha terminada em “:” (if, for, def…) a próxima precisa de recuo (4 espaços).' : 'O recuo (espaços no começo da linha) está diferente do resto do bloco. Use sempre 4 espaços.'],
            [/SyntaxError: (?:invalid syntax|expected ':')/, () => 'Erro de escrita: costuma faltar “:” no fim de if/for/def/while, fechar parênteses ou aspas.'],
            [/SyntaxError: unterminated string/, () => 'Uma string (texto entre aspas) foi aberta e não fechada.'],
            [/TypeError: can only concatenate str \(not "(\w+)"\) to str/, m => `Você juntou texto com ${m[1] === 'int' ? 'número' : m[1]} usando +. Converta com str(...) ou use f"texto {valor}".`],
            [/TypeError: unsupported operand type\(s\) for ([^:]+): '(\w+)' and '(\w+)'/, m => `A operação ${m[1]} não funciona entre ${m[2]} e ${m[3]}. Converta antes (int(), float(), str()).`],
            [/ZeroDivisionError/, () => 'Divisão por zero. Confira o valor do divisor antes de dividir.'],
            [/ModuleNotFoundError: No module named '([^']+)'/, m => `O módulo “${m[1]}” não existe aqui. Se é um arquivo seu, confira o nome (${m[1]}.py); se é uma biblioteca, nem todas funcionam no navegador.`],
            [/IndexError: list index out of range/, () => 'Você pediu uma posição que a lista não tem. Lembre: a primeira posição é 0 e a última é len(lista) - 1.'],
            [/KeyError: (.+)/, m => `A chave ${m[1]} não existe no dicionário. Use dicionario.get(chave) para evitar o erro ou confira o nome.`],
            [/AttributeError: '(\w+)' object has no attribute '(\w+)'/, m => `${m[1]} não tem “${m[2]}”. Confira o nome do método ou o tipo da variável.`],
            [/ValueError: invalid literal for int\(\)/, () => 'int() recebeu um texto que não é número. Confira o que foi digitado.'],
            [/RecursionError/, () => 'A função se chama sem parar. Confira a condição de parada da recursão.'],
        ],
        php: [
            [/syntax error, unexpected end of file/, () => 'O arquivo terminou antes da hora: falta fechar alguma chave } ou um bloco (endif, endforeach).'],
            [/syntax error, unexpected (?:token )?"?([^",\s]+)"?/, m => `Tem um “${m[1]}” fora do lugar. No PHP, costuma faltar ponto e vírgula “;” no fim da linha anterior ou fechar parêntese.`],
            [/Undefined variable \$?(\w+)/, m => `A variável $${m[1]} não existe. Confira o nome (com $) ou se ela foi definida antes. Numa view Blade, passe o valor em dados.json.`],
            [/Call to undefined function (\w+)\(\)/, m => `A função ${m[1]}() não existe. Confira o nome; se é do Laravel (helpers), ela só existe dentro do framework.`],
            [/Class "([^"]+)" not found/, m => `A classe ${m[1]} não foi encontrada. Arquivos do Laravel (models, facades como Route ou DB) só funcionam dentro do framework; aqui dá para conferir a sintaxe.`],
            [/Call to undefined method ([\w\\]+)::(\w+)\(\)/, m => `${m[1]} não tem o método ${m[2]}(). Confira o nome.`],
            [/Undefined array key "?([^"]+)"?/, m => `O array não tem a chave “${m[1]}”. Use isset($array['${m[1]}']) ou $array['${m[1]}'] ?? valor_padrão.`],
            [/Division by zero/, () => 'Divisão por zero. Confira o divisor.'],
            [/Unsupported operand types/, () => 'A operação não funciona entre esses tipos (ex.: somar array com número).'],
        ],
        sql: [
            [/no such table: (\w+)/, m => `A tabela “${m[1]}” não existe. Crie antes com CREATE TABLE ${m[1]} (...) ou confira o nome. Veja as tabelas na aba Banco.`],
            [/no such column: ([\w.]+)/, m => `A coluna “${m[1]}” não existe nessa tabela. Veja as colunas na aba Banco; texto vai entre aspas simples ('assim').`],
            [/near "([^"]+)": syntax error/, m => `Erro de escrita perto de “${m[1]}”: confira vírgulas entre colunas, parênteses e o ponto e vírgula entre comandos.`],
            [/table (\w+) already exists/, m => `A tabela “${m[1]}” já existe. Use CREATE TABLE IF NOT EXISTS ou apague antes com DROP TABLE ${m[1]}; (ou “Recriar banco”).`],
            [/UNIQUE constraint failed: ([\w.]+)/, m => `Já existe um registro com o mesmo valor em ${m[1]}, que não pode se repetir.`],
            [/NOT NULL constraint failed: ([\w.]+)/, m => `${m[1]} é obrigatório e ficou vazio no INSERT/UPDATE.`],
            [/(\d+) values for (\d+) columns/, m => `O INSERT tem ${m[1]} valores para ${m[2]} colunas: a quantidade precisa bater.`],
            [/incomplete input/, () => 'O comando ficou pela metade: falta fechar parênteses ou aspas.'],
        ],
    };
    DICAS.mjs = DICAS.js; DICAS.blade = DICAS.php;

    function explicar(lingua, texto) {
        const lista = DICAS[lingua] || [];
        for (const [re, f] of lista) { const m = re.exec(texto); if (m) return f(m); }
        return null;
    }

    /** Procura ( [ { sem par, ignorando textos e comentários. Devolve { linha, msg } ou null. */
    function conferirPares(texto, lingua) {
        const linhaCom = lingua === 'py' ? '#' : lingua === 'sql' ? '--' : '//';
        const abre = { '(': ')', '[': ']', '{': '}' }, fecha = { ')': '(', ']': '[', '}': '{' };
        const pilha = [];
        let linha = 1, q = '', triplo = false;
        for (let i = 0; i < texto.length; i++) {
            const c = texto[i];
            if (c === '\n') { linha++; if (q && q !== '`' && !triplo) q = ''; continue; }
            if (q) {
                if (c === '\\') { i++; continue; }
                if (triplo ? texto.startsWith(q.repeat(3), i) : c === q) { if (triplo) i += 2; q = ''; triplo = false; }
                continue;
            }
            if (texto.startsWith(linhaCom, i) || (lingua === 'php' && c === '#')) { const f = texto.indexOf('\n', i); if (f < 0) break; i = f - 1; continue; }
            if (lingua !== 'py' && texto.startsWith('/*', i)) { const f = texto.indexOf('*/', i + 2); if (f < 0) break; linha += texto.slice(i, f).split('\n').length - 1; i = f + 1; continue; }
            if (c === '"' || c === "'" || (c === '`' && lingua !== 'py')) { q = c; triplo = lingua === 'py' && texto.startsWith(c.repeat(3), i); if (triplo) i += 2; continue; }
            if (abre[c]) pilha.push({ c, linha });
            else if (fecha[c]) {
                const t = pilha.pop();
                if (!t) return { linha, msg: `Tem um “${c}” a mais na linha ${linha}: nada foi aberto antes dele.` };
                if (t.c !== fecha[c]) return { linha, msg: `Na linha ${linha} fecha com “${c}”, mas o que estava aberto (linha ${t.linha}) era “${t.c}”: esperava “${abre[t.c]}”.` };
            }
        }
        if (q && (q === '`' || triplo)) return { linha, msg: 'Um texto entre aspas foi aberto e não fechado.' };
        const t = pilha.pop();
        if (t) return { linha: t.linha, msg: `O “${t.c}” aberto na linha ${t.linha} não foi fechado: falta um “${abre[t.c]}”.` };
        return null;
    }

    /** Diretivas Blade sem par (@if sem @endif etc.). */
    function conferirBlade(texto) {
        const pares = [['if', 'endif'], ['foreach', 'endforeach'], ['forelse', 'endforelse'], ['for', 'endfor'], ['while', 'endwhile'], ['unless', 'endunless'], ['isset', 'endisset'], ['auth', 'endauth'], ['guest', 'endguest'], ['push', 'endpush']];
        const conta = n => (texto.match(new RegExp('(^|[^\\w@])@' + n + '\\b', 'g')) || []).length;
        for (const [a, f] of pares) {
            const na = conta(a), nf = conta(f);
            if (na > nf) return `Falta @${f}: há ${na} @${a} e ${nf} @${f}.`;
            if (nf > na) return `Tem @${f} a mais: ${nf} @${f} para ${na} @${a}.`;
        }
        const sec = (texto.match(/@section\s*\(\s*['"][^'"]+['"]\s*\)/g) || []).length;
        const fim = conta('endsection') + conta('stop') + conta('show');
        if (sec > fim) return `Falta @endsection: ${sec} @section sem fechar.`;
        return null;
    }

    /* ---------- modelos Laravel ---------- */
    function laravel(tipo, nome) {
        const N = (nome || 'Exemplo').replace(/[^A-Za-z0-9_]/g, '') || 'Exemplo';
        const Cap = N.charAt(0).toUpperCase() + N.slice(1);
        const snake = Cap.replace(/([a-z0-9])([A-Z])/g, '$1_$2').toLowerCase();
        const plural = snake.endsWith('s') ? snake : snake + 's';
        const data = new Date().toISOString().slice(0, 10).replace(/-/g, '_');
        const php = '<' + '?php\n\n';
        switch (tipo) {
            case 'controller': return { caminho: `app/Http/Controllers/${Cap}Controller.php`, texto: `${php}namespace App\\Http\\Controllers;\n\nuse Illuminate\\Http\\Request;\n\nclass ${Cap}Controller extends Controller\n{\n    public function index()\n    {\n        return view('${plural}.index', ['${plural}' => []]);\n    }\n\n    public function store(Request $request)\n    {\n        $dados = $request->validate([\n            'nome' => ['required', 'string', 'max:120'],\n        ]);\n\n        return redirect()->back();\n    }\n}\n` };
            case 'model': return { caminho: `app/Models/${Cap}.php`, texto: `${php}namespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass ${Cap} extends Model\n{\n    protected $fillable = ['nome'];\n}\n` };
            case 'migration': return { caminho: `database/migrations/${data}_000000_create_${plural}_table.php`, texto: `${php}use Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('${plural}', function (Blueprint $table) {\n            $table->id();\n            $table->string('nome');\n            $table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('${plural}');\n    }\n};\n` };
            case 'rota': return { caminho: 'routes/web.php', texto: `${php}use App\\Http\\Controllers\\${Cap}Controller;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/${plural}', [${Cap}Controller::class, 'index'])->name('${plural}.index');\nRoute::post('/${plural}', [${Cap}Controller::class, 'store'])->name('${plural}.store');\n` };
            case 'layout': return { caminho: 'resources/views/layouts/app.blade.php', texto: `<!doctype html>\n<html lang="pt-BR">\n<${"head"}>\n    <meta ${"char" + "set"}="utf-8">\n    <title>@yield('titulo', 'Meu app')</title>\n    @stack('estilos')\n</head>\n<body>\n    <main>\n        @yield('conteudo')\n    </main>\n</body>\n</html>\n` };
            case 'view': default: return { caminho: `resources/views/${plural}/index.blade.php`, texto: `@extends('layouts.app')\n\n@section('titulo', '${Cap}')\n\n@section('conteudo')\n    <h1>{{ $titulo ?? '${Cap}' }}</h1>\n\n    @forelse ($${plural} as $item)\n        <p>{{ $item['nome'] }}</p>\n    @empty\n        <p>Nenhum registro ainda.</p>\n    @endforelse\n@endsection\n` };
        }
    }
    const DADOS_EXEMPLO = '{\n  "titulo": "Lista de exemplo",\n  "posts": [\n    { "nome": "Primeiro" },\n    { "nome": "Segundo" }\n  ]\n}\n';

    window.WFMotores = { rodar: (msg, aoMsg) => enviar(msg, aoMsg), aquecer, reiniciar, compilarBlade, nomeView, PRELUDIO, laravel, DADOS_EXEMPLO };
    window.WFDicas = { explicar, conferirPares, conferirBlade };
})();
