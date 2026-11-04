/* Confere o analisador do worker (public/js/banco-sql-worker.js) no Node, com o texto cortado em pedaços
   de tamanhos aleatórios (o navegador lê o arquivo aos pedaços).
   node tests/banco-sql/testar-analisador.cjs [arquivo.sql ...] */
const fs = require('fs');
const path = require('path');
const assert = require('assert');
// o worker não é módulo: roda o arquivo com um module de mentira
const modulo = { exports: {} };
new Function('module', 'require', fs.readFileSync(path.join(__dirname, '../../public/js/banco-sql-worker.js'), 'utf8'))(modulo, require);
const { Analisador } = modulo.exports;

function analisar(texto, op = {}, passo = 0) {
    const linhas = new Map();
    const A = new Analisador({ ...op, aoPedaco(lote) { for (const [t, ls] of lote) { if (!linhas.has(t.nome)) linhas.set(t.nome, []); const d = linhas.get(t.nome); for (const l of ls) d.push(l); } } });
    let i = 0, semente = 7;
    while (i < texto.length) {
        semente = (semente * 48271) % 2147483647;
        const n = passo || 1 + (semente % 9000);
        A.alimentar(texto.slice(i, i + n));
        i += n;
    }
    const r = A.terminar();
    return { ...r, linhas, porNome: Object.fromEntries(r.tabelas.map(t => [t.nome, t])) };
}

// 1) casos pequenos, cortados de todo jeito
const mysql = `-- comentário
/*!40101 SET NAMES utf8mb4 */;
CREATE TABLE \`a\` (\`id\` int(11) NOT NULL AUTO_INCREMENT, \`t\` varchar(20) DEFAULT 'x;y', PRIMARY KEY (\`id\`));
CREATE TABLE \`b\` (\`id\` int, \`a_id\` int, CONSTRAINT fk FOREIGN KEY (\`a_id\`) REFERENCES \`a\` (\`id\`));
INSERT INTO \`a\` VALUES (1,'O\\'Neil'),(2,'It''s'),(3,NULL),(4,'linha\\nnova'),(5,'(parênteses, vírgula)'),(6,_binary 'abc'),(7,'-12.5');
INSERT INTO \`b\` (\`a_id\`,\`id\`) VALUES (1,10),(2,20) ON DUPLICATE KEY UPDATE a_id=VALUES(a_id);
DELIMITER $$
CREATE TRIGGER x BEFORE INSERT ON a FOR EACH ROW BEGIN SET NEW.t = 'a;b'; END$$
DELIMITER ;
INSERT INTO a VALUES (8, 'depois do delimiter');
`;
for (const passo of [0, 1, 2, 3, 7, 50]) {
    const r = analisar(mysql, {}, passo);
    const a = r.linhas.get('a');
    assert.strictEqual(r.porNome.a.total, 8, 'a: 8 linhas (passo ' + passo + ')');
    assert.deepStrictEqual(a[0], [1, "O'Neil"]);
    assert.deepStrictEqual(a[1], [2, "It's"]);
    assert.deepStrictEqual(a[2], [3, null]);
    assert.deepStrictEqual(a[3], [4, 'linha\nnova']);
    assert.deepStrictEqual(a[4], [5, '(parênteses, vírgula)']);
    assert.deepStrictEqual(a[5], [6, 'abc']);
    assert.deepStrictEqual(a[6], [7, '-12.5']);
    assert.deepStrictEqual(a[7], [8, 'depois do delimiter']);
    assert.deepStrictEqual(r.linhas.get('b'), [[10, 1], [20, 2]]);
    assert.ok(r.porNome.a.colunas[0].pk && r.porNome.a.colunas[0].ai);
    assert.deepStrictEqual(r.porNome.b.colunas[1].fk, { t: 'a', c: 'id' });
}
console.log('ok: casos do MySQL (cortados em pedaços de 1, 2, 3, 7, 50 e aleatórios)');

const pg = `\\restrict k
CREATE FUNCTION f() RETURNS trigger AS $fn$ BEGIN x := 1; RETURN NEW; END; $fn$ LANGUAGE plpgsql;
CREATE TABLE public.p (id integer NOT NULL, nome text, n numeric);
COPY public.p (id, nome, n) FROM stdin;
1\tum\\ttab\t1.5
2\t\\N\t\\N
3\tbarra \\\\ e\\nlinha\t007
\\.
ALTER TABLE ONLY public.p ADD CONSTRAINT p_pkey PRIMARY KEY (id);
`;
for (const passo of [0, 1, 5]) {
    const r = analisar(pg, {}, passo);
    assert.deepStrictEqual(r.linhas.get('p'), [[1, 'um\ttab', 1.5], [2, null, null], [3, 'barra \\ e\nlinha', '007']], 'COPY passo ' + passo);
    assert.ok(r.porNome.p.colunas[0].pk);
}
console.log('ok: COPY do PostgreSQL');

const csv = 'id;nome;obs\r\n1;"Ana; Lima";"disse ""oi""\nem duas linhas"\r\n2;Zé;\r\n';
for (const passo of [0, 1, 4]) {
    const r = analisar(csv, { csv: 'gente' }, passo);
    assert.deepStrictEqual(r.linhas.get('gente'), [[1, 'Ana; Lima', 'disse "oi"\nem duas linhas'], [2, 'Zé', null]], 'CSV passo ' + passo);
}
console.log('ok: CSV');

// 2) arquivos gerados (gerar-dump.cjs)
for (const arq of process.argv.slice(2)) {
    const t0 = Date.now();
    const texto = fs.readFileSync(arq, 'utf8');
    const t1 = Date.now();
    const r = analisar(texto, /\.csv$/i.test(arq) ? { csv: path.basename(arq, '.csv') } : {}, 1 << 20);
    const t2 = Date.now();
    console.log(`${path.basename(arq)}: ${(texto.length / 1048576).toFixed(1)} M caracteres, leitura do disco ${t1 - t0} ms, análise ${t2 - t1} ms`);
    r.tabelas.filter(t => t.total).forEach(t => console.log(`  ${t.nome}: ${t.total} linhas, ${t.colunas.length} colunas, ${t.colunas.filter(c => c.fk).length} FK`));
    if (r.avisos.length) console.log('  avisos:', r.avisos);
}

// 3) o caminho completo do worker (ler → pedaços gzip → abrir tabela → janela/ordem/busca/estatísticas), sem servidor
(async () => {
    const { tratar } = modulo.exports;
    const dump = 'CREATE TABLE a (id int PRIMARY KEY, v varchar(9), preco decimal(5,2));\n'
        + 'INSERT INTO a VALUES ' + Array.from({ length: 3000 }, (_, i) => `(${i + 1},'x${i % 7}','${(i % 50) + 0.5}0')`).join(',') + ';\n'
        + Array.from({ length: 300 }, (_, i) => `CREATE TABLE p${i} (id int);\nINSERT INTO p${i} VALUES (${i});\n`).join('');
    const arquivo = new File([dump], 'teste.sql');
    const r = await tratar({ acao: 'ler', arquivo, id: 'bx', versao: 'v1', cfg: { local: true, url: 'http://x' } }, () => {});
    assert.strictEqual(r.tabelas.length, 301);
    const pedacos = new Set(r.tabelas.flatMap(t => t.pedacos));
    assert.strictEqual(pedacos.size, 1, '301 tabelas pequenas cabem num pedaço só');
    let j = await tratar({ acao: 'janela', id: 'bx', ti: 0, ini: 0, n: 3, ordem: { c: 2, dir: 'desc' } }, () => {});
    assert.strictEqual(j.total, 3000);
    assert.strictEqual(j.linhas[0][2], '49.50', 'ordem numérica de decimais entre aspas');
    j = await tratar({ acao: 'janela', id: 'bx', ti: 0, ini: 0, n: 3, busca: 'x3', filtros: { 0: '>2990' } }, () => {});
    assert.deepStrictEqual(j.linhas.map(l => l[0]), [2993, 3000]);
    j = await tratar({ acao: 'janela', id: 'bx', ti: 300, ini: 0, n: 3 }, () => {});
    assert.deepStrictEqual(j.linhas, [[299]]);
    const e = await tratar({ acao: 'estatisticas', id: 'bx', ti: 0, ncol: 3 }, () => {});
    assert.strictEqual(e.cols[0].max, 3000);
    assert.strictEqual(e.cols[1].distintos, 7);
    const csv = await tratar({ acao: 'csv', id: 'bx', ti: 0, colunas: ['id', 'v', 'preco'], busca: 'x3', filtros: { 0: '>2990' } }, () => {});
    assert.deepStrictEqual([...new Uint8Array(await csv.blob.arrayBuffer()).slice(0, 3)], [0xef, 0xbb, 0xbf], 'CSV com BOM, para o Excel');
    assert.strictEqual(await csv.blob.text(), 'id;v;preco\r\n2993;x3;42.50\r\n3000;x3;49.50\r\n');
    console.log('ok: worker completo (lote de tabelas pequenas, ordem, busca, filtro, estatísticas, CSV)');
})().catch(e => { console.error(e); process.exit(1); });
