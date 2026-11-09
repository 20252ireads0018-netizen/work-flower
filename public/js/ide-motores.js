/* Work Flower · motores da mini-IDE (cartão "GitHub / VS Code" do Diverso; a IDE fica em areas/diverso/partials/blocos.blade.php).
   - Python (Pyodide), PHP 8.4 (php-wasm, com SQLite/PDO e mbstring), SQL (SQLite, sql.js), Ruby 3.4 (ruby.wasm),
     Lua 5.3 (fengari) e TypeScript/TSX/JSX (compilador oficial do TypeScript) rodam NO NAVEGADOR, num iframe isolado
     (sandbox sem acesso à página nem aos cookies) que fica carregado: só a primeira execução de cada linguagem baixa o motor.
   - Laravel: um projeto com routes/web.php roda no PHP do navegador com o mini-Laravel (public/js/ide-mini-laravel.txt):
     rotas, controllers, Eloquent sobre SQLite, migrations, seeders, validação, sessão e Blade. Não é o framework inteiro.
   - Blade: as views .blade.php são convertidas para PHP aqui (compilarBlade), inclusive componentes <x-...>.
   - JavaScript "estilo Node": empacotarNode() junta os módulos do projeto com require/module.exports, fs, path, events…
     e pacotes do npm baixados do esm.sh na hora.
   - WFDicas: explica em português por que o código deu erro e acha chaves/parênteses sem par antes de rodar.

   WFMotores.rodar({ acao, arqs, main, projeto, ... }, aoMsg) -> Promise (resolve no fim)
     aoMsg({ t: 'log'|'error'|'info'|'tabela'|'html'|'esquema'|'laravel'|'js', ... })
   WFMotores.aquecer(lingua) baixa o motor em segundo plano. */
(function () {
    'use strict';
    if (window.WFMotores) return;

    const PYODIDE = 'https://cdn.jsdelivr.net/pyodide/v0.26.4/full/pyodide.js';
    const PHPWASM = 'https://cdn.jsdelivr.net/npm/php-wasm@0.2.0/PhpWeb.mjs';
    const PHPLIBS = ['https://cdn.jsdelivr.net/npm/php-wasm-sqlite@0.2.0/index.mjs', 'https://cdn.jsdelivr.net/npm/php-wasm-mbstring@0.2.0/index.mjs'];
    const SQLJS = 'https://cdn.jsdelivr.net/npm/sql.js@1.15.2/dist/';
    const RUBY_JS = 'https://cdn.jsdelivr.net/npm/@ruby/3.4-wasm-wasi@2.7.1/dist/browser.umd.js';
    const RUBY_WASM = 'https://cdn.jsdelivr.net/npm/@ruby/3.4-wasm-wasi@2.7.1/dist/ruby+stdlib.wasm';
    const LUA = 'https://cdn.jsdelivr.net/npm/fengari-web@0.1.4/dist/fengari-web.js';
    const TYPESCRIPT = 'https://cdn.jsdelivr.net/npm/typescript@5.6.3/lib/typescript.js';
    const ESM = 'https://esm.sh/';
    // o núcleo do mini-Laravel fica ao lado deste arquivo (é .txt: no servidor é só texto, nunca executado);
    // a página passa o endereço com ?v= em data-nucleo para o navegador baixar de novo quando ele muda
    const ESTE = document.currentScript;
    const NUCLEO = (ESTE && ESTE.dataset.nucleo) || (ESTE && ESTE.src ? ESTE.src.replace(/ide-motores\.js(\?.*)?$/, 'ide-mini-laravel.txt') : 'js/ide-mini-laravel.txt');

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
    // extensões que o build básico não traz: SQLite (+ PDO SQLite) e mbstring; se uma falhar, o PHP sobe sem ela
    const libs = (await Promise.all(${JSON.stringify(PHPLIBS)}.map(u => import(u).catch(() => null)))).filter(Boolean);
    const p = new PhpWeb({ version: '8.4', autoTransaction: false, sharedLibs: libs });
    const pronto = new Promise(ok => p.addEventListener('ready', ok, { once: true }));
    p.addEventListener('output', e => { const s = (e.detail || []).join(''); if (phpHtml !== null) phpHtml += s; else if (phpId) post(phpId, 'log', { m: s }); });
    p.addEventListener('error', e => { const s = (e.detail || []).join(''); if (phpId) post(phpId, 'error', { m: s }); });
    await pronto;
    php = p;
    return p;
  })().catch(e => { phpP = null; throw e; });
}
// apaga /app inteiro: arquivos removidos ou renomeados no projeto não podem continuar no disco virtual
function apagarDir(FS, d) {
  let itens = [];
  try { itens = FS.readdir(d); } catch (e) { return; }
  for (const n of itens) {
    if (n === '.' || n === '..') continue;
    const c = d + '/' + n;
    try { if (FS.isDir(FS.stat(c).mode)) { apagarDir(FS, c); FS.rmdir(c); } else FS.unlink(c); } catch (e) {}
  }
}
// grava direto no FS do Emscripten: p.mkdir/p.writeFile entram numa fila que espera timers depois de cada chamada
async function escreverPhp(p, arqs) {
  const FS = (await p.binary).FS;
  apagarDir(FS, '/app');
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
/* Laravel do navegador: o núcleo (__laravel.php) atende uma requisição e devolve status, corpo, sessão e logs em JSON.
   O banco SQLite fica em /tmp (fora de /app), então continua entre as requisições enquanto a página estiver aberta. */
async function rodarLaravel(id, arqs, req) {
  const p = await motorPhp();
  const FS = (await p.binary).FS;
  const base = Object.getPrototypeOf(Object.getPrototypeOf(Object.getPrototypeOf(p)));
  const direto = typeof p._run === 'function' && base && typeof base.refresh === 'function';
  await (direto ? base.refresh.call(p) : p.refresh());
  arqs['__req.json'] = JSON.stringify(req);
  await escreverPhp(p, arqs);
  try { FS.unlink('/tmp/__wf_resp.json'); } catch (e) {}
  phpId = id; phpHtml = '';
  const codigo = '<' + '?php error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); ini_set("display_errors", "1"); $GLOBALS["__wf_ob"] = ob_get_level(); $__wf_r = json_decode(file_get_contents("/app/__req.json"), true); require "/app/__laravel.php"; '
    + 'file_put_contents("/tmp/__wf_resp.json", json_encode(WF' + String.fromCharCode(92) + 'principal($__wf_r), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));';
  await (direto ? p._run(codigo) : p.run(codigo));
  p.flush();
  const solto = phpHtml; phpHtml = null;
  let resp = null;
  try { resp = JSON.parse(new TextDecoder().decode(FS.readFile('/tmp/__wf_resp.json'))); } catch (e) {}
  post(id, 'laravel', { resp, saida: solto });
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

/* saída de console: o Ruby manda pedaços com quebra de linha no fim, o Lua manda uma linha por chamada */
function capturarConsole(id) {
  const orig = { log: console.log, warn: console.warn, error: console.error };
  const enviar = t => (...a) => { let s = a.map(x => typeof x === 'string' ? x : String(x)).join(' '); if (s.endsWith('\\n')) s = s.slice(0, -1); post(id, t, { m: s }); };
  console.log = enviar('log'); console.warn = enviar('error'); console.error = enviar('error');
  return () => Object.assign(console, orig);
}

/* Ruby (ruby.wasm): o módulo compilado fica guardado; cada execução cria uma VM nova (estado limpo) */
let rbMod = null, rbP = null;
function motorRb() {
  return rbP ??= (async () => {
    await carregarScript(${JSON.stringify(RUBY_JS)});
    const r = await fetch(${JSON.stringify(RUBY_WASM)});
    if (!r.ok) throw new Error('Não consegui baixar o Ruby (HTTP ' + r.status + ')');
    rbMod = await (WebAssembly.compileStreaming ? WebAssembly.compileStreaming(r).catch(async () => WebAssembly.compile(await (await fetch(${JSON.stringify(RUBY_WASM)})).arrayBuffer())) : WebAssembly.compile(await r.arrayBuffer()));
    return rbMod;
  })().catch(e => { rbP = null; throw e; });
}
async function rodarRb(id, preludio, main) {
  const mod = await motorRb();
  const soltar = capturarConsole(id);
  try {
    const { vm } = await self['ruby-wasm-wasi'].DefaultRubyVM(mod, { consolePrint: true });
    vm.eval(preludio);
    vm.eval('__wf_rodar(' + JSON.stringify(main) + ')');
  } catch (e) {
    console.error(String(e && e.message || e).replace(/^Error: /, ''));
  } finally { soltar(); }
}

/* Lua (fengari, Lua 5.3 em JavaScript) */
let luaP = null;
function motorLua() { return luaP ??= carregarScript(${JSON.stringify(LUA)}).catch(e => { luaP = null; throw e; }); }
async function rodarLua(id, codigo) {
  await motorLua();
  const soltar = capturarConsole(id);
  try {
    const r = self.fengari.load(codigo, '=__wf')();
    if (typeof r === 'string' && r) console.error(r);
  } catch (e) {
    console.error(String(e && e.message || e));
  } finally { soltar(); }
}

/* TypeScript / TSX / JSX: o compilador oficial converte para JavaScript (CommonJS); o JS roda na prévia da IDE */
let tsP = null;
function motorTs() { return tsP ??= carregarScript(${JSON.stringify(TYPESCRIPT)}).catch(e => { tsP = null; throw e; }); }
async function transpilar(id, arqs) {
  await motorTs();
  const ts = self.ts, saida = {}, avisos = [];
  for (const k in arqs) {
    const jsx = /\\.(tsx|jsx)$/i.test(k);
    const opc = { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true, allowJs: true };
    if (jsx) opc.jsx = ts.JsxEmit.React;
    const r = ts.transpileModule(arqs[k], { fileName: k, reportDiagnostics: true, compilerOptions: opc });
    saida[k] = r.outputText;
    for (const dg of r.diagnostics || []) {
      if (dg.code === 1378 || dg.code === 1375) continue;   // top-level await: o empacotador trata
      const pos = dg.file && dg.start !== undefined ? dg.file.getLineAndCharacterOfPosition(dg.start) : null;
      avisos.push(k + (pos ? ':' + (pos.line + 1) + ':' + (pos.character + 1) : '') + ' ' + ts.flattenDiagnosticMessageText(dg.messageText, ' '));
    }
  }
  post(id, 'js', { mapa: saida, avisos });
}

addEventListener('message', async ev => {
  const d = ev.data;
  if (!d || !d.wfMotor || ev.source !== parent) return;
  const id = d.id;
  try {
    if (d.acao === 'aquecer') { await ({ py: motorPy, php: motorPhp, blade: motorPhp, laravel: motorPhp, sql: motorSql, rb: motorRb, lua: motorLua, ts: motorTs, tsx: motorTs, jsx: motorTs }[d.lingua] || (async () => {}))(); }
    else if (d.acao === 'py') await rodarPy(id, d.arqs, d.main);
    else if (d.acao === 'php') await rodarPhp(id, d.arqs, d.codigo, !!d.html);
    else if (d.acao === 'laravel') await rodarLaravel(id, d.arqs, d.req);
    else if (d.acao === 'rb') await rodarRb(id, d.preludio, d.main);
    else if (d.acao === 'lua') await rodarLua(id, d.codigo);
    else if (d.acao === 'ts') await transpilar(id, d.arqs);
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
    const MOTOR_DE = { py: 'py', php: 'php', blade: 'php', laravel: 'php', sql: 'sql', rb: 'rb', lua: 'lua', ts: 'ts', tsx: 'ts', jsx: 'ts' };
    function aquecer(lingua) {
        const m = MOTOR_DE[lingua];
        if (!m || aquecidos.has(m)) return;
        aquecidos.add(m);
        enviar({ acao: 'aquecer', lingua }).catch(() => aquecidos.delete(m));
        if (lingua === 'laravel' || lingua === 'blade') nucleoLaravel().catch(() => {});
    }
    /** Para tudo: recria o motor (útil se um programa travou num laço infinito). */
    function reiniciar() {
        if (frame) frame.remove();
        frame = null; aquecidos.clear();
        espera.forEach(e => e.ok()); espera.clear();
    }

    /* ---------- Blade -> PHP ---------- */
    /** Lê "(args)" a partir de i, com parênteses equilibrados. */
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
    const strPhp = s => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
    const camel = s => s.replace(/-([a-z0-9])/g, (m, c) => c.toUpperCase());

    /** "$p in $lista" não existe em PHP; separa "expr as $x" no " as " de nível de cima. */
    function partirForeach(args) {
        let n = 0, q = '';
        for (let j = 0; j < args.length; j++) {
            const c = args[j];
            if (q) { if (c === '\\') j++; else if (c === q) q = ''; continue; }
            if (c === '"' || c === "'") q = c;
            else if ('([{'.includes(c)) n++;
            else if (')]}'.includes(c)) n--;
            else if (!n && /^\sas\s/i.test(args.slice(j, j + 4))) return [args.slice(0, j).trim(), args.slice(j + 3).trim()];
        }
        return [args, '$__item'];
    }

    /** Atributos de uma tag <x-...>: a="texto", :a="$php", ::a="alpine", a (booleano). Devolve código PHP de array. */
    function atributosComp(txt) {
        const partes = [];
        const re = /([^\s=\/>"']+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>"']+)))?/g;
        let m, extra = null;
        while ((m = re.exec(txt))) {
            let nome = m[1];
            const v = m[2] !== undefined ? m[2] : m[3] !== undefined ? m[3] : m[4];
            if (nome.startsWith('{{')) continue;
            if (nome === '@class' || nome === '@style') continue;
            if (nome.startsWith('::')) { partes.push(strPhp(nome.slice(1)) + ' => ' + strPhp(v ?? '')); continue; }
            if (nome.startsWith(':') && nome !== ':') {
                nome = nome.slice(1);
                if (nome === 'attributes') { extra = v; continue; }
                partes.push(strPhp(nome) + ' => (' + (v === undefined ? 'true' : v) + ')');
                continue;
            }
            if (v === undefined) { partes.push(strPhp(nome) + ' => true'); continue; }
            // texto com {{ }} dentro vira concatenação
            const pedacos = [];
            let i = 0;
            v.replace(/\{\{\s*([\s\S]+?)\s*\}\}/g, (mm, ex, pos) => { if (pos > i) pedacos.push(strPhp(v.slice(i, pos))); pedacos.push('e(' + ex + ')'); i = pos + mm.length; return mm; });
            if (i < v.length || !pedacos.length) pedacos.push(strPhp(v.slice(i)));
            partes.push(strPhp(nome) + ' => ' + pedacos.join(' . '));
        }
        const arr = '[' + partes.join(', ') + ']';
        return extra ? 'array_merge((' + extra + ')->getAttributes(), ' + arr + ')' : arr;
    }
    /** <x-alerta tipo="ok">…</x-alerta>, <x-slot:titulo>, <x-slot name="rodape"> e <x-dynamic-component :component="$c">. */
    function compilarComponentes(src, guardar) {
        const ATR = /((?:\s+(?:[^\s>"'=\/]+)(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>"']+))?)*)/.source;
        // abertura e fechamento de slots
        src = src.replace(new RegExp('<x-slot(?::([\\w.-]+)|\\s+(?:name|:name)="([^"]+)")' + ATR + '\\s*>', 'g'), (m, n1, n2, at) =>
            guardar(P('$__blade->slot(' + strPhp(n1 || n2) + ', ' + atributosComp(at || '') + ');')));
        src = src.replace(/<\/x-slot(?::[\w.-]+)?\s*>/g, () => guardar(P('$__blade->fimSlot();')));
        // componentes que se fecham sozinhos
        const abrir = (nome, at) => {
            if (nome === 'dynamic-component') {
                const m = /:component="([^"]+)"/.exec(at) || /component="([^"]+)"/.exec(at);
                const exp = m ? (at.includes(':component=') ? m[1] : strPhp(m[1])) : "''";
                return '$__blade->comp(' + exp + ', ' + atributosComp(at.replace(/:?component="[^"]*"/, '')) + ');';
            }
            return '$__blade->comp(' + strPhp(nome) + ', ' + atributosComp(at || '') + ');';
        };
        src = src.replace(new RegExp('<x-([\\w.:-]+)' + ATR + '\\s*\\/>', 'g'), (m, nome, at) => guardar(P(abrir(nome, at) + ' echo $__blade->fimComp();')));
        src = src.replace(new RegExp('<x-([\\w.:-]+)' + ATR + '\\s*>', 'g'), (m, nome, at) => guardar(P(abrir(nome, at))));
        src = src.replace(/<\/x-[\w.:-]+\s*>/g, () => guardar(P('echo $__blade->fimComp();')));
        return src;
    }

    /** Converte um template Blade em PHP: ecos, condicionais, laços com $loop, layouts, seções, includes, pilhas,
        componentes, @error, @class, @checked/@selected, @switch, @once, @vite, @auth/@guest, @can, @session… */
    function compilarBlade(src) {
        let pai = null, nLaco = 0, nUma = 0;
        const guardados = [];
        const guardar = s => '\u0002' + (guardados.push(s) - 1) + '\u0003';
        const pilha = [];
        src = String(src).replace(/\r\n?/g, '\n');
        src = src.replace(/@verbatim([\s\S]*?)@endverbatim/g, (m, c) => guardar(c))
            .replace(/\{\{--[\s\S]*?--\}\}/g, '')
            .replace(/@@/g, () => guardar('@'))
            .replace(/@(\{\{[\s\S]*?\}\}|\{!![\s\S]*?!!\})/g, (m, c) => guardar(c))
            .replace(/@php(?!\s*\()([\s\S]*?)@endphp/g, (m, c) => guardar(P(c)));
        src = compilarComponentes(src, guardar);
        src = src.replace(/\{!!\s*([\s\S]+?)\s*!!\}/g, (m, c) => guardar(P('echo ' + c + ';')))
            .replace(/\{\{\s*([\s\S]+?)\s*\}\}/g, (m, c) => guardar(P('echo e(' + c + ');')));
        let out = '', i = 0;
        const re = /@([A-Za-z]+)/g;
        const SEM_ARGS = new Set(['else', 'csrf', 'endif', 'parent', 'endforeach', 'endfor', 'endwhile', 'endforelse', 'endsection', 'stop', 'show', 'overwrite', 'append',
            'endpush', 'endprepend', 'endPushOnce', 'endauth', 'endguest', 'endisset', 'endunless', 'enderror', 'endonce', 'endswitch', 'default', 'endcan', 'endcannot', 'endcanany',
            'endsession', 'endenv', 'endproduction', 'endempty', 'endphp', 'endfragment', 'endhasSection', 'endsectionMissing', 'livewireStyles', 'livewireScripts', 'viteReactRefresh', 'inertiaHead']);
        let m;
        while ((m = re.exec(src))) {
            const nome = m[1];
            if (m.index > 0 && /[\w@]/.test(src[m.index - 1])) continue; // e-mail (nome@dominio)
            const k = m.index + m[0].length;
            const temEspaco = src[k] === ' ' && src[k + 1] === '(';
            const a = SEM_ARGS.has(nome) ? null : argumentos(src, temEspaco ? k + 1 : k);
            const args = a ? a.args : '';
            const lista = () => dividirArgs(args);
            const topo = pilha[pilha.length - 1];
            let troca = null;
            switch (nome) {
                case 'if': troca = P('if (' + args + '):'); break;
                case 'elseif': troca = P('elseif (' + args + '):'); break;
                case 'else': troca = P('else:'); break;
                case 'endif': case 'endunless': case 'endisset': case 'endauth': case 'endguest': case 'endforelse': case 'enderror': case 'endonce':
                case 'endcan': case 'endcannot': case 'endcanany': case 'endsession': case 'endenv': case 'endproduction': case 'endempty': case 'endhasSection': case 'endsectionMissing':
                    troca = P('endif;'); break;
                case 'unless': troca = P('if (!(' + args + ')):'); break;
                case 'isset': troca = P('if (isset(' + args + ')):'); break;
                case 'empty':
                    if (a) troca = P('if (empty(' + args + ')):');
                    else { troca = P('endforeach; $loop = $__blade->fecharLaco(); if ($__vazio' + (topo && topo.n) + '):'); pilha.pop(); }
                    break;
                case 'foreach': case 'forelse': {
                    const [exp, resto] = partirForeach(args);
                    const n = ++nLaco, v = '$__l' + n;
                    pilha.push({ t: 'laco', n });
                    troca = P(v + ' = ' + exp + '; ' + (nome === 'forelse' ? '$__vazio' + n + ' = true; ' : '') + '$loop = $__blade->abrirLaco(' + v + '); foreach (' + v + ' as ' + resto + '): $loop = $__blade->passoLaco();' + (nome === 'forelse' ? ' $__vazio' + n + ' = false;' : ''));
                    break;
                }
                case 'endforeach': pilha.pop(); troca = P('endforeach; $loop = $__blade->fecharLaco();'); break;
                case 'for': pilha.push({ t: 'laco' }); troca = P('for (' + args + '):'); break;
                case 'endfor': pilha.pop(); troca = P('endfor;'); break;
                case 'while': pilha.push({ t: 'laco' }); troca = P('while (' + args + '):'); break;
                case 'endwhile': pilha.pop(); troca = P('endwhile;'); break;
                case 'break': case 'continue':
                    if (topo && topo.t === 'switch') { troca = ''; break; }
                    troca = P(a && args.trim() ? 'if (' + args + ') ' + nome + ';' : nome + ';');
                    break;
                // @switch vira if/elseif (case sem @break não "cai" no próximo, como no PHP)
                case 'switch': pilha.push({ t: 'switch', casos: 0, v: '$__sw' + (++nLaco) }); troca = P(pilha[pilha.length - 1].v + ' = ' + args + ';'); break;
                case 'case': if (topo && topo.t === 'switch') { troca = P((topo.casos++ ? 'elseif' : 'if') + ' (' + topo.v + ' == (' + args + ')):'); } break;
                case 'default': if (topo && topo.t === 'switch') { troca = P(topo.casos++ ? 'else:' : 'if (true):'); } break;
                case 'endswitch': { const s = pilha.pop(); troca = s && s.casos ? P('endif;') : ''; break; }
                case 'auth': troca = P('if (auth()->check()):'); break;
                case 'guest': troca = P('if (!auth()->check()):'); break;
                case 'can': troca = P('if (\\Illuminate\\Support\\Facades\\Gate::allows(' + canArgs(args) + ')):'); break;
                case 'cannot': troca = P('if (\\Illuminate\\Support\\Facades\\Gate::denies(' + canArgs(args) + ')):'); break;
                case 'canany': troca = P('if (collect(' + (lista()[0] || '[]') + ')->contains(fn ($__h) => \\Illuminate\\Support\\Facades\\Gate::allows($__h' + (lista()[1] ? ', ' + lista()[1] : '') + '))):'); break;
                case 'env': troca = P('if (app()->environment(' + args + ')):'); break;
                case 'production': troca = P('if (false):'); break;
                case 'session': troca = P('if (session()->has(' + args + ')): $value = session(' + args + ');'); break;
                case 'error': { const p = lista(); troca = P('if ($errors->has(' + p[0] + ')): $message = $errors->first(' + p[0] + ');'); break; }
                case 'csrf': troca = P('echo csrf_field();'); break;
                case 'method': troca = P('echo method_field(' + args + ');'); break;
                case 'json': { const p = lista(); troca = P('echo json_encode(' + p[0] + ', ' + (p[1] || 'JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE') + ');'); break; }
                case 'dump': troca = P('dump(' + args + ');'); break;
                case 'dd': troca = P('dd(' + args + ');'); break;
                case 'php': if (a) troca = P('(' + args + ');'); break;
                case 'unset': troca = P('unset(' + args + ');'); break;
                case 'lang': troca = P('echo e(__(' + args + '));'); break;
                case 'choice': troca = P('echo e(trans_choice(' + args + '));'); break;
                case 'inject': { const p = lista(); troca = P('$' + p[0].replace(/['"$]/g, '') + ' = app(' + p[1] + ');'); break; }
                case 'use': { const p = lista(); troca = P('use ' + p[0].replace(/['"]/g, '').replace(/^\\/, '') + (p[1] ? ' as ' + p[1].replace(/['"]/g, '') : '') + ';'); break; }
                case 'include': { const [view, ...resto] = lista(); troca = P('echo $__blade->incluir(' + view + ', array_merge(get_defined_vars(), ' + (resto.join(',') || '[]') + '));'); break; }
                case 'includeIf': { const [view, ...resto] = lista(); troca = P('echo $__blade->incluir(' + view + ', array_merge(get_defined_vars(), ' + (resto.join(',') || '[]') + '), true);'); break; }
                case 'includeWhen': case 'includeUnless': { const [c, view, ...resto] = lista(); troca = P('if (' + (nome === 'includeUnless' ? '!' : '') + '(' + c + ')) echo $__blade->incluir(' + view + ', array_merge(get_defined_vars(), ' + (resto.join(',') || '[]') + '));'); break; }
                case 'includeFirst': { const [vs, ...resto] = lista(); troca = P('echo $__blade->incluirPrimeira(' + vs + ', array_merge(get_defined_vars(), ' + (resto.join(',') || '[]') + '));'); break; }
                case 'each': troca = P('echo $__blade->cada(' + args + ');'); break;
                case 'extends': pai = lista()[0]; troca = ''; break;
                case 'section': {
                    const p = lista();
                    troca = p.length > 1 ? P('$__blade->sec[' + p[0] + '] ??= e(' + p[1] + ');') : P('$__blade->comecar(' + p[0] + ');');
                    break;
                }
                case 'endsection': case 'stop': case 'overwrite': case 'append': troca = P('$__blade->terminar();'); break;
                case 'show': troca = P('echo $__blade->terminar();'); break;
                case 'yield': { const p = lista(); troca = P('echo $__blade->secao(' + p[0] + (p[1] ? ', ' + p[1] : '') + ');'); break; }
                case 'hasSection': troca = P('if ($__blade->temSecao(' + args + ')):'); break;
                case 'sectionMissing': troca = P('if (!$__blade->temSecao(' + args + ')):'); break;
                case 'push': case 'pushOnce': case 'pushIf': troca = P('$__blade->empurrar(' + lista()[0] + ');'); break;
                case 'endpush': case 'endPushOnce': case 'endPushIf': troca = P('$__blade->soltar();'); break;
                case 'prepend': troca = P('$__blade->antepor(' + args + ');'); break;
                case 'endprepend': troca = P('$__blade->soltarAntes();'); break;
                case 'stack': troca = P('echo $__blade->pilha(' + lista()[0] + ');'); break;
                case 'parent': troca = '##pai##'; break;
                case 'once': troca = P('if ($__blade->umaVez(' + strPhp('u' + (++nUma) + ':' + Math.random().toString(36).slice(2, 7)) + ')):'); break;
                case 'class': troca = 'class="' + P('echo e(__classes(' + args + '));') + '"'; break;
                case 'style': troca = 'style="' + P('echo e(__estilos(' + args + '));') + '"'; break;
                case 'checked': case 'selected': case 'disabled': case 'readonly': case 'required':
                    troca = P('if (' + (args || 'true') + ') echo ' + strPhp(nome) + ';'); break;
                case 'props': case 'aware': troca = P('extract($__blade->props(' + (args || '[]') + ', get_defined_vars()));'); break;
                case 'vite': troca = P('echo __vite((array) (' + (lista().length > 1 ? '[' + args + ']' : args) + '));'); break;
                case 'viteReactRefresh': case 'livewireStyles': case 'livewireScripts': case 'inertiaHead': case 'routes': case 'fragment': case 'endfragment': troca = ''; break;
                case 'livewire': case 'inertia': troca = P('echo "<!-- @' + nome + ' não roda no navegador -->";'); break;
            }
            if (troca === null) continue;
            out += src.slice(i, m.index) + troca;
            i = a ? a.fim : k;
            re.lastIndex = i;
        }
        out += src.slice(i);
        out = out.replace(/\u0002(\d+)\u0003/g, (mm, n) => guardados[+n]);
        // com @extends, o que está fora das seções não aparece (como no Laravel) e o layout vem depois
        if (pai) out = P('ob_start();') + out + P('ob_end_clean(); echo $__blade->incluir(' + pai + ', get_defined_vars());');
        // "?>" seguido de quebra de linha come a quebra no PHP; o Blade mantém
        return out.replace(/\?>\n/g, '?>\n\n');
    }
    /** @can('editar', $post) -> Gate::allows('editar', [$post]) */
    function canArgs(args) { const p = dividirArgs(args); return p[0] + (p.length > 1 ? ', [' + p.slice(1).join(', ') + ']' : ''); }
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
        return caminho.replace(/^.*?resources\/views\//, '').replace(/\.blade\.php$/i, '').replace(/\.php$/i, '').replace(/\//g, '.');
    }

    /* ---------- Laravel do navegador ---------- */
    let nucleoP = null;
    /** Baixa (uma vez) o núcleo do mini-Laravel. */
    function nucleoLaravel() {
        return nucleoP ??= fetch(NUCLEO, { credentials: 'same-origin' }).then(r => {
            if (!r.ok) throw new Error('Não consegui baixar o Laravel do navegador (HTTP ' + r.status + ').');
            return r.text();
        }).catch(e => { nucleoP = null; throw e; });
    }
    /** Arquivos do projeto (caminhos relativos à raiz do Laravel) + views compiladas + núcleo, prontos para o motor. */
    async function arquivosLaravel(arqs) {
        const saida = {};
        for (const k in arqs) {
            saida[k] = arqs[k];
            if (/^resources\/views\/.+\.blade\.php$/i.test(k)) saida['__views/' + nomeView(k) + '.php'] = compilarBlade(arqs[k]);
            else if (/^resources\/views\/.+\.php$/i.test(k)) saida['__views/' + nomeView(k) + '.php'] = arqs[k];
        }
        saida['__laravel.php'] = await nucleoLaravel();
        return saida;
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
    DICAS.rb = [
        [/undefined local variable or method [`'](\w+)'/, m => `“${m[1]}” não existe aqui. Confira a escrita, se a variável foi criada antes ou se o método foi definido (def ${m[1]}).`],
        [/undefined method [`'](\w+)' for (nil|an instance of \w+|\S+)/, m => m[2] === 'nil' ? `Você chamou .${m[1]} em algo que é nil (vazio). A variável ainda não recebeu valor ou o método anterior devolveu nil.` : `${m[2]} não tem o método “${m[1]}”. Confira o nome ou o tipo do objeto.`],
        [/syntax error.*unexpected end-of-input|unexpected end-of-input/, () => 'O arquivo terminou antes da hora: falta um “end” para fechar um def, if, do, class ou while.'],
        [/syntax error.*unexpected [`']?end'?/, () => 'Tem um “end” a mais: confira se cada def/if/do/class tem um end só.'],
        [/syntax error/, () => 'Erro de escrita: confira aspas, parênteses e se cada bloco (def, if, do) fecha com end.'],
        [/no implicit conversion of (\w+) into String|String can't be coerced into (\w+)/, m => `Você juntou texto com ${m[1] || m[2]}. Use interpolação "texto #{valor}" ou .to_s.`],
        [/divided by 0/, () => 'Divisão por zero. Confira o divisor antes de dividir.'],
        [/cannot load such file -- (\S+)/, m => `O arquivo “${m[1]}” não foi encontrado. Com require_relative, o caminho é relativo ao arquivo atual (ex.: require_relative 'lib/conta').`],
        [/wrong number of arguments \(given (\d+), expected (\d+)/, m => `O método recebeu ${m[1]} argumento(s), mas espera ${m[2]}.`],
        [/uninitialized constant (\S+)/, m => `A classe ou constante ${m[1]} não existe. Confira a escrita (começa com maiúscula) ou o require do arquivo onde ela está.`],
    ];
    DICAS.lua = [
        [/attempt to call a nil value \((?:global|field|method) '(\w+)'\)/, m => `“${m[1]}” não existe (é nil). Confira a escrita ou se a função foi definida antes de ser chamada.`],
        [/attempt to index a nil value(?: \((?:global|local|field) '(\w+)'\))?/, m => `Você usou ${m[1] ? '“' + m[1] + '”' : 'algo'} como tabela, mas o valor é nil. A variável não recebeu valor ou a chave não existe.`],
        [/attempt to perform arithmetic on a (\w+) value/, m => `Conta com um valor ${m[1] === 'nil' ? 'nil (vazio)' : 'do tipo ' + m[1]}. Converta com tonumber() ou confira a variável.`],
        [/attempt to concatenate a (\w+) value/, m => `Você juntou texto com .. e um valor ${m[1]}. Use tostring(valor).`],
        [/'end' expected/, () => 'Falta um “end” para fechar function, if, for ou while.'],
        [/'=' expected|unexpected symbol/, () => 'Erro de escrita: confira se faltou “then” depois do if, “do” depois do for/while ou se há um símbolo fora do lugar.'],
        [/module '([^']+)' not found/, m => `O módulo “${m[1]}” não existe no projeto. Para require("${m[1]}"), crie o arquivo ${m[1].replace(/\./g, '/')}.lua.`],
    ];
    DICAS.ts = [
        [/Cannot find name '(\w+)'/, m => `“${m[1]}” não existe neste ponto. Confira a escrita, se foi declarado antes ou se falta um import.`],
        [/Type '([^']+)' is not assignable to type '([^']+)'/, m => `O valor é do tipo ${m[1]}, mas ali o tipo esperado é ${m[2]}. (Os tipos só avisam: o código roda mesmo assim.)`],
        [/Property '(\w+)' does not exist on type/, m => `O tipo não declara a propriedade “${m[1]}”. Confira o nome ou acrescente-a na interface/tipo.`],
        ...DICAS.js,
    ];
    DICAS.php.unshift(
        [/Add \[(\w+)\] to fillable property/, m => `O model não deixa preencher “${m[1]}” com create()/update(). Acrescente '${m[1]}' em protected $fillable = [...] no model.`],
        [/Route \[([\w.-]+)\] not defined/, m => `Não existe rota com o nome “${m[1]}”. Dê o nome em routes/web.php com ->name('${m[1]}') ou confira a escrita (artisan route:list mostra todas).`],
        [/View \[([\w.-]+)\] not found/, m => `A view “${m[1]}” não existe. Crie resources/views/${m[1].replace(/\./g, '/')}.blade.php.`],
        [/no such table: (\w+)/, m => `A tabela “${m[1]}” não existe no banco. Crie uma migration com Schema::create('${m[1]}', ...) (+ Laravel → Migration) e rode de novo; para refazer tudo use Artisan → migrate:fresh --seed.`],
        [/table "?(\w+)"? has no column named (\w+)|no such column: (\w+)/, m => `A coluna “${m[2] || m[3]}” não existe${m[1] ? ' na tabela ' + m[1] : ''}. Acrescente na migration e rode Artisan → migrate:fresh --seed.`],
        [/Missing required parameter for \[Route: ([\w.-]+)\].*Missing parameter: (\w+)/, m => `A rota ${m[1]} precisa do parâmetro {${m[2]}}: passe-o em route('${m[1]}', $${m[2]}).`],
        [/Target class \[([^\]]+)\] does not exist|Class "(App\\[^"]+)" not found/, m => `A classe ${m[1] || m[2]} não foi encontrada. Confira o namespace no topo do arquivo e se o caminho bate (App\\Models\\Post → app/Models/Post.php).`],
        [/Method ([\w\\]+)::(\w+) does not exist/, m => `${m[1]} não tem o método ${m[2]}(). Confira o nome do método na rota e no controller.`],
        [/Call to a member function (\w+)\(\) on null/, m => `Você chamou ->${m[1]}() em algo que é null. Talvez a relação não exista (ex.: o post sem categoria) ou a busca não achou nada; use ?-> ou confira antes.`],
    );
    DICAS.mjs = DICAS.js; DICAS.cjs = DICAS.js; DICAS.jsx = DICAS.js; DICAS.tsx = DICAS.ts; DICAS.blade = DICAS.php; DICAS.laravel = DICAS.php;

    function explicar(lingua, texto) {
        const lista = DICAS[lingua] || [];
        for (const [re, f] of lista) { const m = re.exec(texto); if (m) return f(m); }
        return null;
    }

    /** Procura ( [ { sem par, ignorando textos e comentários. Devolve { linha, msg } ou null. */
    function conferirPares(texto, lingua) {
        const linhaCom = lingua === 'py' || lingua === 'rb' ? '#' : lingua === 'sql' || lingua === 'lua' ? '--' : '//';
        if (lingua === 'lua') texto = texto.replace(/--\[(=*)\[[\s\S]*?\]\1\]|\[(=*)\[[\s\S]*?\]\2\]/g, s => s.replace(/[^\n]/g, ' '));
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
            if (!['py', 'rb', 'lua'].includes(lingua) && texto.startsWith('/*', i)) { const f = texto.indexOf('*/', i + 2); if (f < 0) break; linha += texto.slice(i, f).split('\n').length - 1; i = f + 1; continue; }
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
            case 'controller': return { caminho: `app/Http/Controllers/${Cap}Controller.php`, texto: `${php}namespace App\\Http\\Controllers;\n\nuse App\\Models\\${Cap};\nuse Illuminate\\Http\\Request;\n\nclass ${Cap}Controller extends Controller\n{\n    public function index()\n    {\n        return view('${plural}.index', ['${plural}' => ${Cap}::latest('id')->get()]);\n    }\n\n    public function store(Request $request)\n    {\n        $dados = $request->validate([\n            'nome' => ['required', 'string', 'max:120'],\n        ]);\n        ${Cap}::create($dados);\n\n        return redirect()->back()->with('ok', 'Salvo!');\n    }\n}\n` };
            case 'model': return { caminho: `app/Models/${Cap}.php`, texto: `${php}namespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass ${Cap} extends Model\n{\n    use HasFactory;\n\n    protected $fillable = ['nome'];\n}\n` };
            case 'migration': return { caminho: `database/migrations/${data}_000000_create_${plural}_table.php`, texto: `${php}use Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('${plural}', function (Blueprint $table) {\n            $table->id();\n            $table->string('nome');\n            $table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('${plural}');\n    }\n};\n` };
            case 'rota': return { caminho: 'routes/web.php', texto: `${php}use App\\Http\\Controllers\\${Cap}Controller;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => redirect()->route('${plural}.index'));\nRoute::get('/${plural}', [${Cap}Controller::class, 'index'])->name('${plural}.index');\nRoute::post('/${plural}', [${Cap}Controller::class, 'store'])->name('${plural}.store');\n` };
            case 'layout': return { caminho: 'resources/views/layouts/app.blade.php', texto: `<!doctype html>\n<html lang="pt-BR">\n<${"head"}>\n    <meta ${"char" + "set"}="utf-8">\n    <title>@yield('titulo', 'Meu app')</title>\n    @stack('estilos')\n</head>\n<body>\n    @if (session('ok'))\n        <p style="color: green">{{ session('ok') }}</p>\n    @endif\n    <main>\n        @yield('conteudo')\n    </main>\n</body>\n</html>\n` };
            case 'seeder': return { caminho: 'database/seeders/DatabaseSeeder.php', texto: `${php}namespace Database\\Seeders;\n\nuse App\\Models\\${Cap};\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n        ${Cap}::factory(5)->create();\n    }\n}\n` };
            case 'factory': return { caminho: `database/factories/${Cap}Factory.php`, texto: `${php}namespace Database\\Factories;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\Factory;\n\nclass ${Cap}Factory extends Factory\n{\n    public function definition(): array\n    {\n        return [\n            'nome' => fake()->name(),\n        ];\n    }\n}\n` };
            case 'request': return { caminho: `app/Http/Requests/Store${Cap}Request.php`, texto: `${php}namespace App\\Http\\Requests;\n\nuse Illuminate\\Foundation\\Http\\FormRequest;\n\nclass Store${Cap}Request extends FormRequest\n{\n    public function authorize(): bool\n    {\n        return true;\n    }\n\n    public function rules(): array\n    {\n        return [\n            'nome' => ['required', 'string', 'max:120'],\n        ];\n    }\n}\n` };
            case 'componente': return { caminho: `resources/views/components/${snake.replace(/_/g, '-')}.blade.php`, texto: `@props(['tipo' => 'info'])\n\n<div {{ $attributes->merge(['class' => 'caixa caixa-' . $tipo]) }}>\n    {{ $slot }}\n</div>\n` };
            case 'view': default: return { caminho: `resources/views/${plural}/index.blade.php`, texto: `@extends('layouts.app')\n\n@section('titulo', '${Cap}')\n\n@section('conteudo')\n    <h1>{{ $titulo ?? '${Cap}' }}</h1>\n\n    @forelse ($${plural} as $item)\n        <p>{{ $loop->iteration }}. {{ $item['nome'] }}</p>\n    @empty\n        <p>Nenhum registro ainda.</p>\n    @endforelse\n@endsection\n` };
        }
    }
    const DADOS_EXEMPLO = '{\n  "titulo": "Lista de exemplo",\n  "posts": [\n    { "nome": "Primeiro" },\n    { "nome": "Segundo" }\n  ]\n}\n';

    /** Projeto Laravel completo que roda no navegador: lista de tarefas com categorias, busca, paginação, validação e CRUD. */
    function projetoLaravel() {
        const php = '<' + '?php\n\n';
        return [
            ['README.md', '# Tarefas (Laravel no navegador)\n\nClique em ▶ Executar com qualquer arquivo do projeto aberto: o app abre na Prévia.\n\n- Links e formulários da prévia funcionam (GET, POST, PUT, DELETE).\n- O banco é SQLite e é criado na primeira execução pelas migrations + DatabaseSeeder.\n- Barra de endereço da prévia: digite /tarefas, /tarefas/1, /api/tarefas…\n- Artisan: route:list, migrate:fresh --seed, tinker App\\Models\\Tarefa::count()\n- Aba Banco: veja as tabelas e os dados.\n\nÉ um Laravel simplificado (sem Composer): rotas, controllers, Eloquent, migrations,\nseeders, factories, validação, sessão, Blade e componentes. Pacotes extras (Livewire,\nSanctum, filas, e-mail) não rodam aqui. Baixe o ZIP para continuar num Laravel de verdade.\n'],
            ['.env', 'APP_NAME="Tarefas"\nAPP_ENV=local\nAPP_DEBUG=true\nDB_CONNECTION=sqlite\n'],
            ['routes/web.php', `${php}use App\\Http\\Controllers\\TarefaController;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => redirect()->route('tarefas.index'));\nRoute::resource('tarefas', TarefaController::class);\nRoute::patch('/tarefas/{tarefa}/concluir', [TarefaController::class, 'concluir'])->name('tarefas.concluir');\nRoute::view('/sobre', 'sobre')->name('sobre');\n`],
            ['routes/api.php', `${php}use App\\Models\\Tarefa;\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/tarefas', fn () => Tarefa::with('categoria')->latest('id')->get());\nRoute::get('/tarefas/{tarefa}', fn (Tarefa $tarefa) => $tarefa->load('categoria'));\n`],
            ['app/Http/Controllers/Controller.php', `${php}namespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n    //\n}\n`],
            ['app/Http/Controllers/TarefaController.php', `${php}namespace App\\Http\\Controllers;\n\nuse App\\Models\\Categoria;\nuse App\\Models\\Tarefa;\nuse Illuminate\\Http\\Request;\n\nclass TarefaController extends Controller\n{\n    public function index(Request $request)\n    {\n        $tarefas = Tarefa::with('categoria')\n            ->when($request->filled('q'), fn ($q) => $q->where('titulo', 'like', '%' . $request->q . '%'))\n            ->orderBy('concluida')\n            ->latest('id')\n            ->paginate(5)\n            ->withQueryString();\n\n        return view('tarefas.index', [\n            'tarefas' => $tarefas,\n            'pendentes' => Tarefa::pendentes()->count(),\n        ]);\n    }\n\n    public function create()\n    {\n        return view('tarefas.create', ['tarefa' => new Tarefa(), 'categorias' => Categoria::orderBy('nome')->get()]);\n    }\n\n    public function store(Request $request)\n    {\n        $tarefa = Tarefa::create($this->validar($request));\n\n        return redirect()->route('tarefas.show', $tarefa)->with('ok', 'Tarefa criada!');\n    }\n\n    public function show(Tarefa $tarefa)\n    {\n        return view('tarefas.show', compact('tarefa'));\n    }\n\n    public function edit(Tarefa $tarefa)\n    {\n        return view('tarefas.edit', ['tarefa' => $tarefa, 'categorias' => Categoria::orderBy('nome')->get()]);\n    }\n\n    public function update(Request $request, Tarefa $tarefa)\n    {\n        $tarefa->update($this->validar($request));\n\n        return redirect()->route('tarefas.show', $tarefa)->with('ok', 'Tarefa atualizada.');\n    }\n\n    public function concluir(Tarefa $tarefa)\n    {\n        $tarefa->update(['concluida' => !$tarefa->concluida]);\n\n        return back()->with('ok', $tarefa->concluida ? 'Concluída! 🎉' : 'Reaberta.');\n    }\n\n    public function destroy(Tarefa $tarefa)\n    {\n        $tarefa->delete();\n\n        return redirect()->route('tarefas.index')->with('ok', 'Tarefa apagada.');\n    }\n\n    private function validar(Request $request): array\n    {\n        return $request->validate([\n            'titulo' => ['required', 'string', 'min:3', 'max:120'],\n            'descricao' => ['nullable', 'string', 'max:1000'],\n            'prazo' => ['nullable', 'date'],\n            'categoria_id' => ['nullable', 'exists:categorias,id'],\n        ]);\n    }\n}\n`],
            ['app/Models/Tarefa.php', `${php}namespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\HasFactory;\nuse Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n\nclass Tarefa extends Model\n{\n    use HasFactory;\n\n    protected $fillable = ['titulo', 'descricao', 'prazo', 'concluida', 'categoria_id'];\n\n    protected function casts(): array\n    {\n        return [\n            'concluida' => 'boolean',\n            'prazo' => 'date',\n        ];\n    }\n\n    public function categoria(): BelongsTo\n    {\n        return $this->belongsTo(Categoria::class);\n    }\n\n    public function scopePendentes($query)\n    {\n        return $query->where('concluida', false);\n    }\n\n    public function atrasada(): bool\n    {\n        return !$this->concluida && $this->prazo && $this->prazo->isPast();\n    }\n}\n`],
            ['app/Models/Categoria.php', `${php}namespace App\\Models;\n\nuse Illuminate\\Database\\Eloquent\\Model;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n\nclass Categoria extends Model\n{\n    protected $fillable = ['nome', 'cor'];\n\n    public function tarefas(): HasMany\n    {\n        return $this->hasMany(Tarefa::class);\n    }\n}\n`],
            ['database/migrations/2024_01_01_000001_create_categorias_table.php', `${php}use Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('categorias', function (Blueprint $table) {\n            $table->id();\n            $table->string('nome')->unique();\n            $table->string('cor')->default('#6366f1');\n            $table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('categorias');\n    }\n};\n`],
            ['database/migrations/2024_01_01_000002_create_tarefas_table.php', `${php}use Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n        Schema::create('tarefas', function (Blueprint $table) {\n            $table->id();\n            $table->foreignId('categoria_id')->nullable()->constrained()->nullOnDelete();\n            $table->string('titulo');\n            $table->text('descricao')->nullable();\n            $table->date('prazo')->nullable();\n            $table->boolean('concluida')->default(false);\n            $table->timestamps();\n        });\n    }\n\n    public function down(): void\n    {\n        Schema::dropIfExists('tarefas');\n    }\n};\n`],
            ['database/factories/TarefaFactory.php', `${php}namespace Database\\Factories;\n\nuse Illuminate\\Database\\Eloquent\\Factories\\Factory;\n\nclass TarefaFactory extends Factory\n{\n    public function definition(): array\n    {\n        return [\n            'titulo' => fake()->sentence(4),\n            'descricao' => fake()->paragraph(),\n            'prazo' => fake()->dateTimeBetween('-5 days', '+20 days')->format('Y-m-d'),\n            'concluida' => fake()->boolean(30),\n        ];\n    }\n}\n`],
            ['database/seeders/DatabaseSeeder.php', `${php}namespace Database\\Seeders;\n\nuse App\\Models\\Categoria;\nuse App\\Models\\Tarefa;\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n        $categorias = collect([\n            ['nome' => 'Trabalho', 'cor' => '#2563eb'],\n            ['nome' => 'Estudos', 'cor' => '#16a34a'],\n            ['nome' => 'Casa', 'cor' => '#ea580c'],\n        ])->map(fn ($c) => Categoria::create($c));\n\n        Tarefa::factory(8)->create()->each(fn ($t) => $t->update(['categoria_id' => $categorias->random()->id]));\n    }\n}\n`],
            ['resources/views/layouts/app.blade.php', `<!doctype html>\n<html lang="pt-BR">\n<${"head"}>\n    <meta ${"char" + "set"}="utf-8">\n    <title>@yield('titulo', config('app.name'))</title>\n    <style>\n        body { font-family: system-ui, sans-serif; margin: 0; background: #f4f4f7; color: #1f2937; }\n        header { background: #4f46e5; color: #fff; padding: .8rem 1.2rem; display: flex; gap: 1rem; align-items: center; }\n        header a { color: #fff; text-decoration: none; }\n        main { max-width: 760px; margin: 1.2rem auto; padding: 0 1rem; }\n        .cartao { background: #fff; border-radius: .6rem; padding: 1rem; box-shadow: 0 1px 2px #0001; margin-bottom: .8rem; }\n        .btn { display: inline-block; padding: .4rem .8rem; border-radius: .4rem; border: 1px solid #c7c9d1; background: #fff; cursor: pointer; text-decoration: none; color: inherit; font-size: .9rem; }\n        .btn-prim { background: #4f46e5; border-color: #4f46e5; color: #fff; }\n        .feita { text-decoration: line-through; color: #9ca3af; }\n        .atrasada { color: #dc2626; }\n        .erro { color: #dc2626; font-size: .85rem; }\n        input, textarea, select { width: 100%; padding: .45rem; border: 1px solid #c7c9d1; border-radius: .4rem; box-sizing: border-box; font: inherit; }\n        label { display: block; margin: .6rem 0 .2rem; font-weight: 600; font-size: .9rem; }\n        .etiqueta { font-size: .75rem; padding: .1rem .45rem; border-radius: 1rem; color: #fff; }\n    </style>\n</head>\n<body>\n    <header>\n        <b>✔ {{ config('app.name') }}</b>\n        <a href="{{ route('tarefas.index') }}">Tarefas</a>\n        <a href="{{ route('tarefas.create') }}">Nova</a>\n        <a href="{{ route('sobre') }}">Sobre</a>\n    </header>\n    <main>\n        @if (session('ok'))\n            <x-alerta tipo="ok">{{ session('ok') }}</x-alerta>\n        @endif\n        @yield('conteudo')\n    </main>\n</body>\n</html>\n`],
            ['resources/views/components/alerta.blade.php', `@props(['tipo' => 'info'])\n\n<div {{ $attributes->merge(['class' => 'cartao']) }} @style(['border-left: 4px solid #16a34a' => $tipo === 'ok', 'border-left: 4px solid #2563eb' => $tipo !== 'ok'])>\n    {{ $slot }}\n</div>\n`],
            ['resources/views/tarefas/index.blade.php', `@extends('layouts.app')\n\n@section('titulo', 'Tarefas')\n\n@section('conteudo')\n    <div class="cartao">\n        <form method="GET" action="{{ route('tarefas.index') }}" style="display:flex; gap:.5rem">\n            <input name="q" value="{{ request('q') }}" placeholder="Buscar tarefa…">\n            <button class="btn">Buscar</button>\n        </form>\n        <p>{{ $pendentes }} pendente(s) · {{ $tarefas->total() }} no total</p>\n    </div>\n\n    @forelse ($tarefas as $tarefa)\n        <div class="cartao" style="display:flex; gap:.6rem; align-items:center">\n            <form method="POST" action="{{ route('tarefas.concluir', $tarefa) }}">\n                @csrf\n                @method('PATCH')\n                <button class="btn" title="Concluir">{{ $tarefa->concluida ? '↺' : '✔' }}</button>\n            </form>\n            <div style="flex:1">\n                <a href="{{ route('tarefas.show', $tarefa) }}" @class(['feita' => $tarefa->concluida, 'atrasada' => $tarefa->atrasada()])>\n                    {{ $loop->iteration + ($tarefas->currentPage() - 1) * $tarefas->perPage() }}. {{ $tarefa->titulo }}\n                </a>\n                @if ($tarefa->categoria)\n                    <span class="etiqueta" style="background: {{ $tarefa->categoria->cor }}">{{ $tarefa->categoria->nome }}</span>\n                @endif\n                @isset($tarefa->prazo)\n                    <small> · prazo {{ $tarefa->prazo->format('d/m/Y') }}</small>\n                @endisset\n            </div>\n        </div>\n    @empty\n        <x-alerta>Nenhuma tarefa encontrada. <a href="{{ route('tarefas.create') }}">Criar a primeira</a></x-alerta>\n    @endforelse\n\n    {{ $tarefas->links() }}\n@endsection\n`],
            ['resources/views/tarefas/_form.blade.php', `<label for="titulo">Título</label>\n<input id="titulo" name="titulo" value="{{ old('titulo', $tarefa->titulo) }}">\n@error('titulo')\n    <div class="erro">{{ $message }}</div>\n@enderror\n\n<label for="descricao">Descrição</label>\n<textarea id="descricao" name="descricao" rows="3">{{ old('descricao', $tarefa->descricao) }}</textarea>\n\n<label for="prazo">Prazo</label>\n<input id="prazo" type="date" name="prazo" value="{{ old('prazo', $tarefa->prazo?->format('Y-m-d')) }}">\n@error('prazo')\n    <div class="erro">{{ $message }}</div>\n@enderror\n\n<label for="categoria_id">Categoria</label>\n<select id="categoria_id" name="categoria_id">\n    <option value="">Sem categoria</option>\n    @foreach ($categorias as $c)\n        <option value="{{ $c->id }}" @selected(old('categoria_id', $tarefa->categoria_id) == $c->id)>{{ $c->nome }}</option>\n    @endforeach\n</select>\n`],
            ['resources/views/tarefas/create.blade.php', `@extends('layouts.app')\n\n@section('titulo', 'Nova tarefa')\n\n@section('conteudo')\n    <div class="cartao">\n        <h2>Nova tarefa</h2>\n        <form method="POST" action="{{ route('tarefas.store') }}">\n            @csrf\n            @include('tarefas._form')\n            <p><button class="btn btn-prim">Salvar</button> <a class="btn" href="{{ route('tarefas.index') }}">Cancelar</a></p>\n        </form>\n    </div>\n@endsection\n`],
            ['resources/views/tarefas/edit.blade.php', `@extends('layouts.app')\n\n@section('titulo', 'Editar tarefa')\n\n@section('conteudo')\n    <div class="cartao">\n        <h2>Editar: {{ $tarefa->titulo }}</h2>\n        <form method="POST" action="{{ route('tarefas.update', $tarefa) }}">\n            @csrf\n            @method('PUT')\n            @include('tarefas._form')\n            <p><button class="btn btn-prim">Salvar</button> <a class="btn" href="{{ route('tarefas.show', $tarefa) }}">Cancelar</a></p>\n        </form>\n    </div>\n@endsection\n`],
            ['resources/views/tarefas/show.blade.php', `@extends('layouts.app')\n\n@section('titulo', $tarefa->titulo)\n\n@section('conteudo')\n    <div class="cartao">\n        <h2 @class(['feita' => $tarefa->concluida])>{{ $tarefa->titulo }}</h2>\n        <p>{{ $tarefa->descricao ?: 'Sem descrição.' }}</p>\n        <p>\n            Categoria: {{ $tarefa->categoria?->nome ?? '—' }}<br>\n            Prazo: {{ $tarefa->prazo ? $tarefa->prazo->format('d/m/Y') : '—' }}\n            @if ($tarefa->atrasada()) <b class="atrasada">(atrasada)</b> @endif<br>\n            Criada {{ $tarefa->created_at->diffForHumans() }}\n        </p>\n        <div style="display:flex; gap:.5rem">\n            <a class="btn" href="{{ route('tarefas.edit', $tarefa) }}">Editar</a>\n            <form method="POST" action="{{ route('tarefas.destroy', $tarefa) }}" onsubmit="return confirm('Apagar esta tarefa?')">\n                @csrf\n                @method('DELETE')\n                <button class="btn">Apagar</button>\n            </form>\n            <a class="btn" href="{{ route('tarefas.index') }}">Voltar</a>\n        </div>\n    </div>\n@endsection\n`],
            ['resources/views/sobre.blade.php', `@extends('layouts.app')\n\n@section('conteudo')\n    <div class="cartao">\n        <h2>Sobre</h2>\n        <p>Este app roda inteiro no navegador: PHP {{ PHP_VERSION }}, SQLite e um Laravel simplificado.</p>\n        <ul>\n            @foreach (['Rotas e controllers', 'Eloquent e migrations', 'Validação e sessão', 'Blade e componentes'] as $item)\n                <li>{{ $item }}@if ($loop->last).@else;@endif</li>\n            @endforeach\n        </ul>\n    </div>\n@endsection\n`],
        ];
    }

    /* ---------- Ruby e Lua: prelúdios que dão require/require_relative e entrada (gets / io.read) ---------- */
    /** Código Ruby que guarda os arquivos do projeto e redefine require_relative, require e gets. */
    function preludioRuby(arqs) {
        const json = JSON.stringify(arqs);
        let fim = '__WF_JSON__';
        while (json.includes(fim)) fim += '_';
        return [
            "require 'json'",
            "$__wf_arqs = JSON.parse(<<~'" + fim + "')",
            json,
            fim,
            '$__wf_carregados = {}',
            'def __wf_norm(p)',
            '  partes = []',
            "  p.split('/').each { |s| next if s.empty? || s == '.'; s == '..' ? partes.pop : partes << s }",
            "  partes.join('/')",
            'end',
            'def __wf_carregar(caminho)',
            '  return false if $__wf_carregados[caminho]',
            '  codigo = $__wf_arqs[caminho]',
            "  raise LoadError, 'cannot load such file -- ' + caminho unless codigo",
            '  $__wf_carregados[caminho] = true',
            '  eval(codigo, TOPLEVEL_BINDING, caminho, 1)',
            '  true',
            'end',
            'def __wf_gets(*)',
            "  require 'js'",
            "  r = JS.global.call(:prompt, 'O programa pede uma entrada (gets):')",
            "  r.typeof == 'string' ? r.to_s + \"\\n\" : nil",
            'end',
            'module Kernel',
            '  alias_method :__wf_require_original, :require',
            '  def require_relative(nome)',
            "    base = File.dirname(caller_locations(1, 1).first.path) rescue '.'",
            "    c = __wf_norm((base == '.' ? '' : base + '/') + nome)",
            "    c += '.rb' unless c.end_with?('.rb')",
            '    __wf_carregar(c)',
            '  end',
            '  def require(nome)',
            "    c = nome.end_with?('.rb') ? nome : nome + '.rb'",
            '    return __wf_carregar(c) if $__wf_arqs.key?(c)',
            "    return __wf_carregar('lib/' + c) if $__wf_arqs.key?('lib/' + c)",
            '    __wf_require_original(nome)',
            '  end',
            '  def gets(*a) = __wf_gets(*a)',
            'end',
            '$stdin.define_singleton_method(:gets) { |*a| __wf_gets(*a) }',
            '$stdin.define_singleton_method(:read) { |*a| __wf_gets(*a) }',
            'def __wf_rodar(main)',
            '  __wf_carregar(main)',
            'rescue SystemExit',
            'rescue Exception => e',
            "  $stderr.puts e.class.name + ': ' + e.message",
            "  bt = (e.backtrace || []).reject { |l| l.start_with?('/') || l.start_with?('eval:') || l.start_with?('-e:') || l.include?('<internal') || l.include?('(eval') }.first(8)",
            "  $stderr.puts bt.map { |l| '  em ' + l }.join(\"\\n\") unless bt.empty?",
            'end',
        ].join('\n');
    }
    /** Programa Lua com os arquivos do projeto em package.preload (require "modulo") e io.read pedindo a entrada. */
    function codigoLua(arqs, main) {
        const longo = s => { let n = 0; while (s.includes(']' + '='.repeat(n) + ']')) n++; const q = '='.repeat(n); return '[' + q + '[\n' + s + ']' + q + ']'; };
        const linhas = ['local __arqs = {}'];
        for (const k in arqs) linhas.push('__arqs[' + JSON.stringify(k) + '] = ' + longo(arqs[k]));
        linhas.push(
            'table.insert(package.searchers, 2, function(nome)',
            '  local c = nome:gsub("%.", "/") .. ".lua"',
            '  local src = __arqs[c] or __arqs[nome]',
            '  if not src then return "\\n\\tno arquivo " .. c .. " do projeto" end',
            '  return assert(load(src, "@" .. c)), c',
            'end)',
            'local js = require "js"',
            'io = io or {}',
            'io.read = function(fmt)',
            '  local r = js.global:prompt("O programa pede uma entrada (io.read):")',
            '  if type(r) ~= "string" then return nil end',
            '  if fmt == "n" or fmt == "*n" then return tonumber(r) end',
            '  return r',
            'end',
            'io.write = function(...) local t = {} for i = 1, select("#", ...) do t[#t + 1] = tostring((select(i, ...))) end print(table.concat(t)) return io end',
            'local principal, erro = load(__arqs[' + JSON.stringify(main) + '], "@" .. ' + JSON.stringify(main) + ')',
            'if not principal then return erro end',
            'local ok, falha = xpcall(principal, debug.traceback)',
            'if not ok then return (tostring(falha):gsub("\\n%s*%[JS%][^\\n]*", ""):gsub("\\n%s*%[C%][^\\n]*", ""):gsub("\\n%s*__wf:[^\\n]*", "")) end',
            'return nil',
        );
        return linhas.join('\n');
    }

    /* ---------- JavaScript estilo Node ---------- */
    const NODE_BUILTINS = ['fs', 'fs/promises', 'path', 'events', 'util', 'os', 'assert', 'readline', 'readline/promises', 'process', 'buffer', 'crypto', 'timers', 'url', 'querystring', 'string_decoder', 'http', 'https', 'child_process', 'net', 'stream', 'worker_threads', 'zlib'];
    /** Pacotes do npm usados por require('x') / import … from 'x' (o que não é arquivo do projeto nem módulo do Node). */
    function pacotesNpm(mods) {
        const s = new Set();
        const re = /\brequire\s*\(\s*['"]([^'"]+)['"]\s*\)|\bfrom\s*['"]([^'"]+)['"]|\bimport\s*['"]([^'"]+)['"]|\bimport\s*\(\s*['"]([^'"]+)['"]\s*\)/g;
        for (const k in mods) {
            let m;
            re.lastIndex = 0;
            while ((m = re.exec(mods[k]))) {
                const n = m[1] || m[2] || m[3] || m[4];
                if (!n || n.startsWith('.') || n.startsWith('/') || /^https?:/.test(n)) continue;
                const base = n.replace(/^node:/, '');
                if (NODE_BUILTINS.includes(base)) continue;
                s.add(n);
            }
        }
        return [...s];
    }
    /** Script que roda na prévia: os módulos do projeto (já em CommonJS) com require, module.exports, fs, path, events…
        e os pacotes do npm baixados do esm.sh antes de começar. */
    function empacotarNode(mods, arqs, main, npm) {
        const j = v => JSON.stringify(v).replace(/<\/(script)/gi, '<\\/$1').replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029');
        return '(async () => {\n'
            + 'const MODS = ' + j(mods) + ', ARQS = ' + j(arqs) + ', MAIN = ' + j(main) + ', NPM = ' + j(npm) + ', ESM = ' + j(ESM) + ';\n'
            + '(' + nodeRuntime.toString() + ')(MODS, ARQS, MAIN, NPM, ESM);\n'
            + '})();';
    }
    // roda DENTRO da prévia (é convertido em texto por empacotarNode): não pode usar nada de fora desta função
    async function nodeRuntime(MODS, ARQS, MAIN, NPM, ESM) {
        const dir = p => (p.includes('/') ? p.slice(0, p.lastIndexOf('/')) : '');
        const norm = p => { const o = []; p.split('/').forEach(s => { if (s === '..') o.pop(); else if (s && s !== '.') o.push(s); }); return o.join('/'); };
        const juntar = (...ps) => norm(ps.filter(Boolean).join('/'));
        const cwd = dir(MAIN);
        const absoluto = p => norm(String(p).startsWith('/') ? String(p) : juntar(cwd, String(p)));
        const npm = {};
        await Promise.all(NPM.map(async n => {
            try { npm[n] = await import(ESM + n); }
            catch (e) { console.error('Não consegui baixar o pacote "' + n + '" do npm (esm.sh): ' + (e && e.message || e)); }
        }));
        class SaidaEncerrada extends Error {}
        const linhaPendente = { out: '', err: '' };
        const escrever = (qual, s) => {
            linhaPendente[qual] += String(s);
            const partes = linhaPendente[qual].split('\n');
            linhaPendente[qual] = partes.pop();
            partes.forEach(l => (qual === 'out' ? console.log : console.error)(l));
            return true;
        };
        const descarregar = () => { if (linhaPendente.out) console.log(linhaPendente.out); if (linhaPendente.err) console.error(linhaPendente.err); linhaPendente.out = linhaPendente.err = ''; };
        class EventEmitter {
            constructor() { this._ev = {}; }
            on(e, f) { (this._ev[e] ||= []).push(f); return this; }
            addListener(e, f) { return this.on(e, f); }
            prependListener(e, f) { (this._ev[e] ||= []).unshift(f); return this; }
            once(e, f) { const g = (...a) => { this.off(e, g); f(...a); }; return this.on(e, g); }
            off(e, f) { this._ev[e] = (this._ev[e] || []).filter(x => x !== f); return this; }
            removeListener(e, f) { return this.off(e, f); }
            removeAllListeners(e) { if (e) delete this._ev[e]; else this._ev = {}; return this; }
            emit(e, ...a) { const l = (this._ev[e] || []).slice(); l.forEach(f => f.apply(this, a)); return l.length > 0; }
            listenerCount(e) { return (this._ev[e] || []).length; }
            listeners(e) { return (this._ev[e] || []).slice(); }
            eventNames() { return Object.keys(this._ev); }
            setMaxListeners() { return this; }
        }
        const inspecionar = v => { try { if (typeof v === 'string') return v; if (v instanceof Error) return v.stack || String(v); const s = JSON.stringify(v, null, 2); return s === undefined ? String(v) : s; } catch (e) { return String(v); } };
        const formatar = (f, ...a) => {
            if (typeof f !== 'string') return [f, ...a].map(inspecionar).join(' ');
            let i = 0;
            const s = f.replace(/%[sdifjoO%]/g, m => { if (m === '%%') return '%'; if (i >= a.length) return m; const v = a[i++]; return m === '%d' || m === '%i' ? String(parseInt(v, 10)) : m === '%f' ? String(parseFloat(v)) : m === '%s' ? String(v) : inspecionar(v); });
            return [s, ...a.slice(i).map(inspecionar)].join(' ');
        };
        class Buffer extends Uint8Array {
            static from(v, enc) {
                if (typeof v === 'string') {
                    if (enc === 'base64') return new Buffer([...atob(v)].map(c => c.charCodeAt(0)));
                    if (enc === 'hex') return new Buffer((v.match(/../g) || []).map(h => parseInt(h, 16)));
                    return new Buffer(new TextEncoder().encode(v));
                }
                return new Buffer(v);
            }
            static alloc(n, f = 0) { return new Buffer(n).fill(f); }
            static isBuffer(b) { return b instanceof Buffer; }
            static concat(l) { const t = l.reduce((s, b) => s + b.length, 0), r = new Buffer(t); let o = 0; l.forEach(b => { r.set(b, o); o += b.length; }); return r; }
            static byteLength(s) { return new TextEncoder().encode(String(s)).length; }
            toString(enc) {
                if (enc === 'base64') return btoa(String.fromCharCode(...this));
                if (enc === 'hex') return [...this].map(b => b.toString(16).padStart(2, '0')).join('');
                return new TextDecoder().decode(this);
            }
            toJSON() { return { type: 'Buffer', data: [...this] }; }
        }
        const lerArq = (p, enc) => {
            const c = absoluto(p);
            if (!(c in ARQS)) { const e = new Error("ENOENT: no such file or directory, open '" + p + "'"); e.code = 'ENOENT'; throw e; }
            const o = typeof enc === 'object' && enc ? enc.encoding : enc;
            return o ? ARQS[c] : Buffer.from(ARQS[c]);
        };
        let avisouGravar = false;
        const gravarArq = (p, d) => { ARQS[absoluto(p)] = String(d instanceof Uint8Array ? new TextDecoder().decode(d) : d); if (!avisouGravar) { avisouGravar = true; console.info('(fs: o arquivo gravado fica só na memória desta execução; não muda o projeto)'); } };
        const fs = {
            readFileSync: lerArq,
            writeFileSync: gravarArq,
            appendFileSync: (p, d) => { const c = absoluto(p); ARQS[c] = (ARQS[c] || '') + String(d); },
            existsSync: p => { const c = absoluto(p); return c in ARQS || Object.keys(ARQS).some(k => k.startsWith(c + '/')) || c === ''; },
            readdirSync: p => { const c = absoluto(p); const pre = c ? c + '/' : ''; return [...new Set(Object.keys(ARQS).filter(k => k.startsWith(pre)).map(k => k.slice(pre.length).split('/')[0]))]; },
            statSync: p => { const c = absoluto(p); const arq = c in ARQS; if (!arq && !fs.existsSync(p)) throw Object.assign(new Error("ENOENT: no such file or directory, stat '" + p + "'"), { code: 'ENOENT' }); return { isFile: () => arq, isDirectory: () => !arq, size: arq ? ARQS[c].length : 0, mtime: new Date() }; },
            mkdirSync: () => undefined,
            unlinkSync: p => { delete ARQS[absoluto(p)]; },
            rmSync: p => { delete ARQS[absoluto(p)]; },
            readFile: (p, enc, cb) => { if (typeof enc === 'function') { cb = enc; enc = null; } setTimeout(() => { try { cb(null, lerArq(p, enc)); } catch (e) { cb(e); } }); },
            writeFile: (p, d, enc, cb) => { if (typeof enc === 'function') cb = enc; setTimeout(() => { gravarArq(p, d); cb && cb(null); }); },
        };
        fs.promises = { readFile: async (p, enc) => lerArq(p, enc), writeFile: async (p, d) => gravarArq(p, d), appendFile: async (p, d) => fs.appendFileSync(p, d), readdir: async p => fs.readdirSync(p), stat: async p => fs.statSync(p), mkdir: async () => undefined, access: async p => { if (!fs.existsSync(p)) throw Object.assign(new Error('ENOENT'), { code: 'ENOENT' }); }, unlink: async p => fs.unlinkSync(p) };
        const path = {
            sep: '/', delimiter: ':',
            join: (...a) => ((String(a[0] || '').startsWith('/') ? '/' : '') + norm(a.join('/'))) || '.',
            resolve: (...a) => { let r = ''; a.forEach(p => { r = String(p).startsWith('/') ? p : r + '/' + p; }); return '/' + norm(String(r).startsWith('/') ? r : cwd + '/' + r); },
            dirname: p => { const d = dir(String(p)); return d || (String(p).startsWith('/') ? '/' : '.'); },
            basename: (p, ext) => { let b = String(p).split('/').pop(); if (ext && b.endsWith(ext)) b = b.slice(0, -ext.length); return b; },
            extname: p => { const b = String(p).split('/').pop(); const i = b.lastIndexOf('.'); return i > 0 ? b.slice(i) : ''; },
            normalize: p => norm(p) || '.',
            isAbsolute: p => String(p).startsWith('/'),
            relative: (a, b) => { const x = norm(a).split('/').filter(Boolean), y = norm(b).split('/').filter(Boolean); while (x.length && y.length && x[0] === y[0]) { x.shift(); y.shift(); } return [...x.map(() => '..'), ...y].join('/'); },
            parse: p => { const base = path.basename(p), ext = path.extname(p); return { root: String(p).startsWith('/') ? '/' : '', dir: path.dirname(p), base, ext, name: ext ? base.slice(0, -ext.length) : base }; },
            format: o => (o.dir ? o.dir + '/' : '') + (o.base || (o.name || '') + (o.ext || '')),
        };
        path.posix = path;
        const perguntar = q => { const r = prompt(String(q || 'Entrada:')); return r === null ? null : r; };
        const readline = {
            createInterface: () => {
                const rl = new EventEmitter();
                rl.question = (q, a, cb) => { if (typeof a === 'function') cb = a; const r = perguntar(q); setTimeout(() => cb(r ?? '')); };
                rl.close = () => { if (!rl.fechado) { rl.fechado = true; rl.emit('close'); } };
                rl.setPrompt = p => { rl._p = p; };
                rl.prompt = () => {};
                rl.write = s => escrever('out', s);
                rl[Symbol.asyncIterator] = async function* () { for (;;) { const r = perguntar(rl._p || 'Linha (Cancelar para terminar):'); if (r === null) { rl.close(); return; } yield r; } };
                const on = rl.on.bind(rl);
                rl.on = (e, f) => { on(e, f); if (e === 'line' && !rl.lendo) { rl.lendo = true; setTimeout(function laco() { if (rl.fechado) return; const r = perguntar(rl._p || 'Linha (Cancelar para terminar):'); if (r === null) { rl.close(); return; } rl.emit('line', r); setTimeout(laco); }); } return rl; };
                return rl;
            },
        };
        const readlinePromises = { createInterface: () => { const rl = readline.createInterface(); const q = rl.question; rl.question = qq => new Promise(ok => q(qq, ok)); return rl; } };
        const util = {
            format: formatar, inspect: inspecionar, isDeepStrictEqual: (a, b) => JSON.stringify(a) === JSON.stringify(b),
            promisify: f => (...a) => new Promise((ok, falha) => f(...a, (e, v) => (e ? falha(e) : ok(v)))),
            types: { isPromise: p => p instanceof Promise, isDate: d => d instanceof Date, isRegExp: r => r instanceof RegExp },
            inherits: (a, b) => { Object.setPrototypeOf(a.prototype, b.prototype); }, deprecate: f => f, TextEncoder, TextDecoder,
        };
        const igual = (a, b) => JSON.stringify(a) === JSON.stringify(b);
        const falhaAssert = (m, pad) => { const e = new Error(m || pad); e.name = 'AssertionError'; e.code = 'ERR_ASSERTION'; throw e; };
        const assert = Object.assign((v, m) => { if (!v) falhaAssert(m, 'The expression evaluated to a falsy value'); }, {
            ok: (v, m) => { if (!v) falhaAssert(m, 'The expression evaluated to a falsy value'); },
            equal: (a, b, m) => { if (a != b) falhaAssert(m, inspecionar(a) + ' == ' + inspecionar(b)); },
            notEqual: (a, b, m) => { if (a == b) falhaAssert(m, inspecionar(a) + ' != ' + inspecionar(b)); },
            strictEqual: (a, b, m) => { if (!Object.is(a, b)) falhaAssert(m, 'Expected values to be strictly equal:\n' + inspecionar(a) + ' !== ' + inspecionar(b)); },
            notStrictEqual: (a, b, m) => { if (Object.is(a, b)) falhaAssert(m, 'Expected values to be not strictly equal'); },
            deepEqual: (a, b, m) => { if (!igual(a, b)) falhaAssert(m, 'Expected values to be deeply equal'); },
            deepStrictEqual: (a, b, m) => { if (!igual(a, b)) falhaAssert(m, 'Expected values to be strictly deep-equal:\n' + inspecionar(a) + '\n' + inspecionar(b)); },
            notDeepStrictEqual: (a, b, m) => { if (igual(a, b)) falhaAssert(m, 'Expected values not to be deep-equal'); },
            throws: (f, x, m) => { try { f(); } catch (e) { return; } falhaAssert(typeof x === 'string' ? x : m, 'Missing expected exception.'); },
            rejects: async (p, x, m) => { try { await (typeof p === 'function' ? p() : p); } catch (e) { return; } falhaAssert(m, 'Missing expected rejection.'); },
            fail: m => falhaAssert(m, 'Failed'),
            match: (s, r, m) => { if (!r.test(s)) falhaAssert(m, 'The input did not match the regular expression ' + r); },
        });
        assert.strict = assert;
        const processo = new EventEmitter();
        Object.assign(processo, {
            argv: ['node', '/' + MAIN], env: { NODE_ENV: 'development' }, platform: 'browser', version: 'v20.0.0 (navegador)', versions: { node: '20.0.0' }, pid: 1, arch: 'wasm', exitCode: 0,
            cwd: () => '/' + cwd, chdir: () => {}, nextTick: (f, ...a) => queueMicrotask(() => f(...a)), uptime: () => performance.now() / 1000,
            hrtime: Object.assign(p => { const t = performance.now(), s = Math.floor(t / 1000), n = Math.floor((t % 1000) * 1e6); return p ? [s - p[0], n - p[1]] : [s, n]; }, { bigint: () => BigInt(Math.floor(performance.now() * 1e6)) }),
            memoryUsage: () => ({ rss: 0, heapTotal: 0, heapUsed: 0 }), exit: c => { processo.exitCode = c ?? 0; throw new SaidaEncerrada('exit'); },
            stdout: { write: s => escrever('out', s), isTTY: false, columns: 80 }, stderr: { write: s => escrever('err', s), isTTY: false },
            stdin: Object.assign(new EventEmitter(), { setEncoding: () => {}, resume: () => {}, pause: () => {} }),
        });
        const os = { EOL: '\n', platform: () => 'browser', type: () => 'Browser', arch: () => 'wasm', cpus: () => [{ model: 'navegador' }], totalmem: () => 0, freemem: () => 0, homedir: () => '/home', tmpdir: () => '/tmp', hostname: () => 'localhost', userInfo: () => ({ username: 'voce' }), release: () => '', uptime: () => performance.now() / 1000 };
        const crypto = {
            randomUUID: () => globalThis.crypto.randomUUID(), randomBytes: n => Buffer.from(globalThis.crypto.getRandomValues(new Uint8Array(n))),
            randomInt: (a, b) => (b === undefined ? Math.floor(Math.random() * a) : a + Math.floor(Math.random() * (b - a))),
            createHash: () => { throw new Error('crypto.createHash não existe no navegador; use crypto.subtle.digest.'); }, webcrypto: globalThis.crypto, subtle: globalThis.crypto.subtle,
        };
        const naoTem = n => new Proxy({}, { get: (t, k) => (k === '__esModule' ? false : () => { throw new Error('O módulo "' + n + '" do Node não funciona no navegador (não há servidor, rede crua nem processos aqui).'); }) });
        const timers = { setTimeout, setInterval, clearTimeout, clearInterval, setImmediate: (f, ...a) => setTimeout(f, 0, ...a) };
        const nativos = {
            fs, 'fs/promises': fs.promises, path, events: Object.assign(EventEmitter, { EventEmitter, once: (em, e) => new Promise(ok => em.once(e, (...a) => ok(a))) }), util, os, assert, 'assert/strict': assert,
            readline, 'readline/promises': readlinePromises, process: processo, buffer: { Buffer }, crypto, timers, 'timers/promises': { setTimeout: (ms, v) => new Promise(ok => setTimeout(() => ok(v), ms)) },
            url: { URL, URLSearchParams, fileURLToPath: u => String(u).replace(/^file:\/\//, ''), pathToFileURL: p => new URL('file://' + p) }, querystring: { parse: s => Object.fromEntries(new URLSearchParams(s)), stringify: o => new URLSearchParams(o).toString() },
            string_decoder: { StringDecoder: class { write(b) { return new TextDecoder().decode(b); } end() { return ''; } } },
        };
        ['http', 'https', 'child_process', 'net', 'stream', 'worker_threads', 'zlib', 'cluster', 'dgram', 'dns', 'tls', 'vm', 'v8'].forEach(n => { nativos[n] = naoTem(n); });
        globalThis.process = processo; globalThis.Buffer = Buffer; globalThis.global = globalThis; globalThis.setImmediate = timers.setImmediate;
        const cache = {};
        const EXTS = ['', '.js', '.ts', '.mjs', '.cjs', '.jsx', '.tsx', '.json', '/index.js', '/index.ts', '/index.mjs', '/index.jsx', '/index.tsx', '/index.json'];
        const achar = (de, nome) => { const base = nome.startsWith('/') ? norm(nome) : juntar(dir(de), nome); for (const e of EXTS) { const c = norm(base + e); if (c in MODS || (c.endsWith('.json') && c in ARQS)) return c; } return null; };
        const exigirDe = de => {
            const req = nome => {
                const n = String(nome);
                const nat = n.replace(/^node:/, '');
                if (nat in nativos) return nativos[nat];
                if (n.startsWith('.') || n.startsWith('/')) {
                    const c = achar(de, n);
                    if (!c) { const e = new Error("Cannot find module '" + n + "' (pedido por " + de + '). Confira o nome e a pasta do arquivo.'); e.code = 'MODULE_NOT_FOUND'; throw e; }
                    return carregar(c);
                }
                if (n in npm) { const m = npm[n]; return m.default !== undefined ? m.default : m; }
                const e = new Error("Cannot find module '" + n + "'. Pacotes do npm são baixados do esm.sh quando o require/import tem o nome escrito direto (ex.: require('lodash')).");
                e.code = 'MODULE_NOT_FOUND';
                throw e;
            };
            req.resolve = n => achar(de, n) || n;
            req.cache = cache;
            req.main = null;
            return req;
        };
        const compilar = (codigo, caminho, assinc) => (0, eval)('(' + (assinc ? 'async ' : '') + 'function (exports, require, module, __filename, __dirname) {' + codigo + '\n})\n//# sourceURL=' + caminho);
        function carregar(c) {
            if (cache[c]) return cache[c].exports;
            const module = { exports: {}, id: c, filename: '/' + c, loaded: false, children: [], paths: [] };
            cache[c] = module;
            if (!(c in MODS)) { module.exports = JSON.parse(ARQS[c]); module.loaded = true; return module.exports; }
            compilar(MODS[c], c).call(module.exports, module.exports, exigirDe(c), module, '/' + c, '/' + dir(c));
            module.loaded = true;
            return module.exports;
        }
        // linha do código gerado pelo TypeScript -> linha do arquivo original (procura a mesma linha de texto)
        const linhaOriginal = (c, n) => {
            if (!(c in ARQS) || ARQS[c] === MODS[c]) return n;
            const ger = (MODS[c] || '').split('\n')[n - 1];
            const alvo = ger ? ger.trim().replace(/\(0, (\w+)\.(\w+)\)/g, '$2').replace(/\b\w+_1\.(?:default\.)?/g, '') : '';
            if (!alvo || alvo.length < 3) return n;
            const ls = ARQS[c].split('\n');
            const i = ls.findIndex(l => l.trim() === alvo) + 1 || ls.findIndex(l => l.includes(alvo.slice(0, 40))) + 1;
            return i || n;
        };
        const limparPilha = s => String(s).split('\n').filter(l => !/about:srcdoc|<anonymous>|\(eval at |nodeRuntime/.test(l) || !/^\s+at /.test(l))
            .map(l => l.replace(/\beval at [^)]*\), /, '').replace(/([\w./-]+\.(?:ts|tsx|jsx|js|mjs|cjs)):(\d+):(\d+)/g, (m, c, n, col) => c + ':' + linhaOriginal(c, +n) + (c in ARQS && ARQS[c] !== MODS[c] ? '' : ':' + col))).join('\n');
        if (!globalThis.React) globalThis.React = { createElement: (type, props, ...children) => ({ type: typeof type === 'function' ? (type.name || 'Componente') : type, props: Object.assign({}, props, children.length ? { children: children.length === 1 ? children[0] : children } : {}) }), Fragment: 'Fragment' };
        const mod = { exports: {}, id: '.', filename: '/' + MAIN, loaded: false, children: [], paths: [] };
        cache[MAIN] = mod;
        try {
            let f;
            try { f = compilar(MODS[MAIN], MAIN, false); } catch (e) { if (e instanceof SyntaxError && /\bawait\b/.test(MODS[MAIN])) f = compilar(MODS[MAIN], MAIN, true); else throw e; }
            const req = exigirDe(MAIN);
            req.main = mod;
            await f.call(mod.exports, mod.exports, req, mod, '/' + MAIN, '/' + dir(MAIN));
            mod.loaded = true;
        } catch (e) {
            if (!(e instanceof SaidaEncerrada)) { descarregar(); console.error(limparPilha(e && e.stack ? e.stack : String(e))); }
        }
        addEventListener('unhandledrejection', ev => { if (ev.reason instanceof SaidaEncerrada) ev.preventDefault(); });
        setTimeout(descarregar, 0);
        processo.emit('exit', processo.exitCode);
    }

    window.WFMotores = {
        rodar: (msg, aoMsg) => enviar(msg, aoMsg), aquecer, reiniciar, compilarBlade, nomeView, laravel, DADOS_EXEMPLO,
        projetoLaravel, arquivosLaravel, nucleoLaravel, preludioRuby, codigoLua, empacotarNode, pacotesNpm,
    };
    window.WFDicas = { explicar, conferirPares, conferirBlade };
})();
