/* Gera dumps de teste para o cartão "Banco de dados (.sql)".
   node tests/banco-sql/gerar-dump.cjs <pasta> [MB]
   - mysql-<MB>mb.sql: dump no estilo mysqldump/phpMyAdmin (crases, INSERT com várias linhas, textos com \' '' \n,
     acentos, NULL, chaves estrangeiras em CREATE TABLE e em ALTER TABLE, comentários condicionais do MySQL, DELIMITER);
   - postgres.sql: dump do pg_dump com COPY ... FROM stdin, \N, tabulação escapada e ALTER TABLE ONLY ... FOREIGN KEY;
   - clientes.csv: CSV com ; e campos entre aspas. */
const fs = require('fs');
const path = require('path');

const pasta = process.argv[2] || '.';
const alvoMB = Number(process.argv[3] || 110);
fs.mkdirSync(pasta, { recursive: true });

let semente = 42;
const rnd = () => (semente = (semente * 1103515245 + 12345) % 2147483648) / 2147483648;
const pega = a => a[Math.floor(rnd() * a.length)];
const NOMES = ['Ana', 'João', 'Maria', 'José', 'Luíza', "D'Ávila", 'Conceição', 'Pedro', 'Fernanda', 'Çaiã', 'O\'Neil', 'Zé'];
const SOBRE = ['Silva', 'Souza', 'Oliveira', 'Pereira', 'Lima', 'Gonçalves', 'Ferreira', 'Araújo'];
const PALAVRAS = ['caneta', 'caderno', 'mochila', 'borracha', 'régua', 'lápis', 'estojo', 'cola', 'tesoura', 'apontador', 'marca-texto'];
const esc = s => "'" + String(s).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n') + "'";

const out = fs.createWriteStream(path.join(pasta, `mysql-${alvoMB}mb.sql`));
let escrito = 0;
const w = s => { escrito += Buffer.byteLength(s); return out.write(s); };

w(`-- phpMyAdmin SQL Dump\n-- version 5.2.1\n-- Host: localhost\n\nSET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";\nSTART TRANSACTION;\nSET time_zone = "+00:00";\n\n`);
w(`/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n/*!40101 SET NAMES utf8mb4 */;\n\n`);
w('CREATE DATABASE IF NOT EXISTS `loja` DEFAULT CHARACTER SET utf8mb4;\nUSE `loja`;\n\n');
w(`DELIMITER $$\nCREATE PROCEDURE \`limpa\` ()   BEGIN\n  DELETE FROM pedidos WHERE id < 0;\nEND$$\nDELIMITER ;\n\n`);
w('CREATE TABLE `clientes` (\n  `id` int(11) NOT NULL AUTO_INCREMENT,\n  `nome` varchar(120) NOT NULL,\n  `email` varchar(160) DEFAULT NULL,\n  `cidade` varchar(80) DEFAULT \'São Paulo\',\n  `criado_em` datetime NOT NULL DEFAULT current_timestamp(),\n  `obs` text,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `email` (`email`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;\n\n');
w('CREATE TABLE `produtos` (\n  `id` int(11) NOT NULL,\n  `nome` varchar(100) NOT NULL,\n  `preco` decimal(10,2) NOT NULL,\n  `estoque` int(11) DEFAULT 0,\n  `categoria_id` int(11) DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n');
w('CREATE TABLE `categorias` (\n  `id` int(11) NOT NULL,\n  `nome` varchar(60) NOT NULL,\n  `pai_id` int(11) DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  CONSTRAINT `fk_cat_pai` FOREIGN KEY (`pai_id`) REFERENCES `categorias` (`id`)\n) ENGINE=InnoDB;\n\n');
w('CREATE TABLE `pedidos` (\n  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n  `cliente_id` int(11) NOT NULL,\n  `status` enum(\'aberto\',\'pago\',\'enviado\',\'cancelado\') NOT NULL DEFAULT \'aberto\',\n  `total` decimal(12,2) NOT NULL,\n  `feito_em` datetime NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `cliente_id` (`cliente_id`),\n  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB;\n\n');
w('CREATE TABLE `itens_pedido` (\n  `pedido_id` bigint(20) UNSIGNED NOT NULL,\n  `produto_id` int(11) NOT NULL,\n  `qtd` int(11) NOT NULL DEFAULT 1,\n  `preco` decimal(10,2) NOT NULL,\n  `nota` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`pedido_id`,`produto_id`)\n) ENGINE=InnoDB;\n\n');
for (let i = 1; i <= 40; i++) w(`CREATE TABLE \`log_${i}\` (\n  \`id\` int NOT NULL,\n  \`pedido_id\` bigint UNSIGNED DEFAULT NULL,\n  \`msg\` varchar(200),\n  PRIMARY KEY (\`id\`),\n  FOREIGN KEY (\`pedido_id\`) REFERENCES \`pedidos\`(\`id\`)\n);\n\n`);

w('INSERT INTO `categorias` (`id`, `nome`, `pai_id`) VALUES\n');
const cats = [];
for (let i = 1; i <= 50; i++) cats.push(`(${i}, ${esc('Categoria ' + i)}, ${i > 5 ? 1 + (i % 5) : 'NULL'})`);
w(cats.join(',\n') + ';\n\n');
w('INSERT INTO `produtos` (`id`, `nome`, `preco`, `estoque`, `categoria_id`) VALUES\n');
const prods = [];
for (let i = 1; i <= 5000; i++) prods.push(`(${i}, ${esc(pega(PALAVRAS) + ' ' + i)}, ${(rnd() * 500).toFixed(2)}, ${Math.floor(rnd() * 1000)}, ${1 + (i % 50)})`);
w(prods.join(',\n') + ';\n\n');

const MAX = alvoMB * 1024 * 1024;
let cid = 0, pid = 0;
function blocoClientes(n) {
    const l = [];
    for (let k = 0; k < n; k++) {
        cid++;
        const nome = pega(NOMES) + ' ' + pega(SOBRE);
        const obs = rnd() < 0.3 ? 'NULL' : esc(`Cliente "vip" desde ${2000 + (cid % 24)}\nLinha 2 com \\barra e 'aspas'`);
        l.push(`(${cid},${esc(nome)},${rnd() < 0.1 ? 'NULL' : esc('cli' + cid + '@exemplo.com.br')},${esc(pega(['São Paulo', 'Recife', 'Porto Alegre', 'Goiânia', 'Belém']))},'2024-0${1 + (cid % 9)}-1${cid % 9} 10:${10 + (cid % 49)}:00',${obs})`);
    }
    return 'INSERT INTO `clientes` (`id`,`nome`,`email`,`cidade`,`criado_em`,`obs`) VALUES ' + l.join(',') + ';\n';
}
function blocoPedidos(n) {
    const l = [], itens = [];
    for (let k = 0; k < n; k++) {
        pid++;
        l.push(`(${pid},${1 + Math.floor(rnd() * Math.max(1, cid))},'${pega(['aberto', 'pago', 'enviado', 'cancelado'])}',${(rnd() * 2000).toFixed(2)},'2025-${String(1 + (pid % 12)).padStart(2, '0')}-${String(1 + (pid % 28)).padStart(2, '0')} 08:00:00')`);
        const q = 1 + Math.floor(rnd() * 3);
        for (let j = 0; j < q; j++) itens.push(`(${pid},${1 + ((pid * 7 + j * 13) % 5000)},${1 + Math.floor(rnd() * 5)},${(rnd() * 300).toFixed(2)},${rnd() < 0.7 ? 'NULL' : "'presente; embrulhar'"})`);
    }
    return 'INSERT INTO `pedidos` VALUES ' + l.join(',') + ';\nINSERT INTO `itens_pedido` (`pedido_id`, `produto_id`, `qtd`, `preco`, `nota`) VALUES ' + itens.join(',') + ';\n';
}
(async () => {
    while (escrito < MAX) {
        const ok = w(blocoClientes(800)) & w(blocoPedidos(1500));
        if (!ok) await new Promise(r => out.once('drain', r));
    }
    w('\nALTER TABLE `produtos`\n  ADD PRIMARY KEY (`id`),\n  ADD KEY `categoria_id` (`categoria_id`);\n\n');
    w('ALTER TABLE `produtos`\n  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);\n\n');
    w('ALTER TABLE `itens_pedido`\n  ADD CONSTRAINT `itens_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),\n  ADD CONSTRAINT `itens_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`);\nCOMMIT;\n');
    out.end();
    await new Promise(r => out.on('finish', r));
    console.log(`mysql-${alvoMB}mb.sql: ${(escrito / 1048576).toFixed(1)} MB, ${cid} clientes, ${pid} pedidos`);

    // PostgreSQL (pg_dump, formato texto)
    let pg = `--\n-- PostgreSQL database dump\n--\n\\restrict abc123\n\nSET statement_timeout = 0;\nSELECT pg_catalog.set_config('search_path', '', false);\n\n`;
    pg += `CREATE FUNCTION public.atualiza() RETURNS trigger\n    LANGUAGE plpgsql\n    AS $$\nBEGIN\n  NEW.atualizado := now(); -- ponto e vírgula dentro do corpo\n  RETURN NEW;\nEND;\n$$;\n\n`;
    pg += `CREATE TABLE public.autores (\n    id integer NOT NULL,\n    nome character varying(100) NOT NULL,\n    bio text\n);\n\n`;
    pg += `CREATE TABLE public."livros" (\n    id integer NOT NULL,\n    titulo text NOT NULL,\n    autor_id integer,\n    preco numeric(8,2),\n    publicado date\n);\n\n`;
    pg += `CREATE SEQUENCE public.autores_id_seq\n    AS integer\n    START WITH 1;\n\n`;
    pg += `COPY public.autores (id, nome, bio) FROM stdin;\n`;
    for (let i = 1; i <= 300; i++) pg += `${i}\tAutor ${i} ${pega(SOBRE)}\t${i % 4 === 0 ? '\\N' : 'Escreve\\tcom tab e\\nquebra; "aspas" e \\\\barra'}\n`;
    pg += `\\.\n\n`;
    pg += `COPY public.livros (id, titulo, autor_id, preco, publicado) FROM stdin;\n`;
    for (let i = 1; i <= 2000; i++) pg += `${i}\tLivro ${i}: ${pega(PALAVRAS)}\t${1 + (i % 300)}\t${(rnd() * 120).toFixed(2)}\t2020-0${1 + (i % 9)}-15\n`;
    pg += `\\.\n\n`;
    pg += `INSERT INTO public.autores VALUES (301, E'Autor com E\\'texto\\'', NULL);\n\n`;
    pg += `ALTER TABLE ONLY public.autores\n    ADD CONSTRAINT autores_pkey PRIMARY KEY (id);\n\n`;
    pg += `ALTER TABLE ONLY public.livros\n    ADD CONSTRAINT livros_pkey PRIMARY KEY (id);\n\n`;
    pg += `ALTER TABLE ONLY public.livros\n    ADD CONSTRAINT livros_autor_fk FOREIGN KEY (autor_id) REFERENCES public.autores(id);\n\n\\unrestrict abc123\n`;
    fs.writeFileSync(path.join(pasta, 'postgres.sql'), pg);
    console.log('postgres.sql: 301 autores, 2000 livros');

    let csv = 'id;nome;"cidade";valor\r\n';
    for (let i = 1; i <= 1000; i++) csv += `${i};"${pega(NOMES).replace(/"/g, '""')} ${pega(SOBRE)}";${pega(['Recife', '"Rio; de Janeiro"', 'Natal'])};${(rnd() * 100).toFixed(1)}\r\n`;
    fs.writeFileSync(path.join(pasta, 'clientes.csv'), csv);
    console.log('clientes.csv: 1000 linhas');
})();
