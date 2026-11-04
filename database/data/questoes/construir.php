<?php

/**
 * Monta o banco de questões do cartão "Questões de concurso" (database/data/questoes/questoes.jsonl.gz)
 * a partir dos conjuntos abertos baixados (veja o README.md desta pasta).
 *
 * Uso: php database/data/questoes/construir.php <pasta com os arquivos baixados>
 *   - OAB:  2010-01.txt ... 2018-25.txt (legal-nlp/oab-exams, official/raw, licença MIT)
 *   - ENEM: maritaca2022.jsonl, maritaca2023.jsonl, maritaca2024.jsonl (maritaca-ai/enem, Apache-2.0)
 *
 * Cada linha da saída: { banca, prova, ano, materia, assunto, dificuldade, enunciado, alts[5], certa, fonte, ref }
 * - Matéria das provas da OAB sem marcação de área: classificador de palavras treinado nas provas marcadas,
 *   respeitando a ordem fixa das matérias na prova da FGV (cada matéria é um bloco seguido).
 * - Assunto: palavras-chave por matéria (automático).
 * - Dificuldade: estimada pelo tamanho do enunciado e das alternativas, em terços dentro de cada matéria.
 */

if ($argc < 2 || ! is_dir($argv[1])) {
    fwrite(STDERR, "Uso: php construir.php <pasta com os arquivos baixados>\n");
    exit(1);
}
$dir = rtrim($argv[1], '/\\');

const AREAS = [
    'ETHICS' => 'Ética Profissional', 'PHILOSOPHY' => 'Filosofia do Direito', 'CONSTITUTIONAL' => 'Direito Constitucional',
    'HUMAN-RIGHTS' => 'Direitos Humanos', 'INTERNATIONAL' => 'Direito Internacional', 'TAXES' => 'Direito Tributário',
    'ADMINISTRATIVE' => 'Direito Administrativo', 'ENVIRONMENTAL' => 'Direito Ambiental', 'CIVIL' => 'Direito Civil',
    'CHILDREN' => 'Estatuto da Criança e do Adolescente', 'CONSUMER' => 'Direito do Consumidor', 'BUSINESS' => 'Direito Empresarial',
    'CIVIL-PROCEDURE' => 'Direito Processual Civil', 'CRIMINAL' => 'Direito Penal', 'CRIMINAL-PROCEDURE' => 'Direito Processual Penal',
    'LABOUR' => 'Direito do Trabalho', 'LABOUR-PROCEDURE' => 'Direito Processual do Trabalho',
];
// ordem das matérias na prova da FGV (a partir de 2013)
const ORDEM = ['ETHICS', 'PHILOSOPHY', 'CONSTITUTIONAL', 'HUMAN-RIGHTS', 'INTERNATIONAL', 'TAXES', 'ADMINISTRATIVE', 'ENVIRONMENTAL',
    'CIVIL', 'CHILDREN', 'CONSUMER', 'BUSINESS', 'CIVIL-PROCEDURE', 'CRIMINAL', 'CRIMINAL-PROCEDURE', 'LABOUR', 'LABOUR-PROCEDURE'];

// posição de cada matéria na prova de 80 questões da FGV (2013 em diante): ajuda a decidir as fronteiras dos blocos
const MOLDE = ['ETHICS' => [1, 10], 'PHILOSOPHY' => [11, 12], 'CONSTITUTIONAL' => [13, 19], 'HUMAN-RIGHTS' => [20, 22], 'INTERNATIONAL' => [23, 24],
    'TAXES' => [25, 28], 'ADMINISTRATIVE' => [29, 34], 'ENVIRONMENTAL' => [35, 36], 'CIVIL' => [37, 43], 'CHILDREN' => [44, 45], 'CONSUMER' => [46, 47],
    'BUSINESS' => [48, 52], 'CIVIL-PROCEDURE' => [53, 58], 'CRIMINAL' => [59, 64], 'CRIMINAL-PROCEDURE' => [65, 70], 'LABOUR' => [71, 75], 'LABOUR-PROCEDURE' => [76, 80]];

const ROMANOS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX', 'XXI', 'XXII', 'XXIII', 'XXIV', 'XXV'];

function limpar(string $s): string
{
    $s = str_replace(["\r", "\u{200a}", "\u{00a0}"], ['', ' ', ' '], $s);
    $s = preg_replace('/[ \t]+/', ' ', $s);
    $s = preg_replace("/ *\n */", "\n", $s);

    return trim(preg_replace("/\n{3,}/", "\n\n", $s));
}

/** Junta as linhas quebradas do texto (parágrafos separados por linha em branco). */
function juntar(array $linhas): string
{
    $pars = [];
    $atual = [];
    foreach ($linhas as $l) {
        if (trim($l) === '') {
            if ($atual) { $pars[] = implode(' ', $atual); $atual = []; }
            continue;
        }
        $atual[] = trim($l);
    }
    if ($atual) $pars[] = implode(' ', $atual);

    return limpar(implode("\n", $pars));
}

function palavras(string $s): array
{
    $s = mb_strtolower($s);
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c', 'ü' => 'u']);
    preg_match_all('/[a-z]{4,}/', $s, $m);

    return $m[0];
}

/* ---------- OAB ---------- */
$oab = [];
foreach (glob($dir . '/20[0-9][0-9]-[0-9][0-9]*.txt') as $arq) {
    $nome = basename($arq, '.txt');
    preg_match('/^(\d{4})-(\d{2})(a?)$/', $nome, $mm);
    [$ano, $num, $reap] = [(int) $mm[1], (int) $mm[2], $mm[3] === 'a'];
    $texto = str_replace("\r", '', file_get_contents($arq));
    $partes = preg_split('/^Questão\s+/m', $texto);
    array_shift($partes);
    foreach ($partes as $p) {
        $linhas = explode("\n", $p);
        $cab = array_shift($linhas);
        if (! preg_match('/^(\d+)\s*(NULL)?/', $cab, $c)) continue;
        $area = '';
        $enun = [];
        $alts = [];
        $certa = null;
        $emOpcoes = false;
        $atualAlt = -1;
        foreach ($linhas as $l) {
            if (! $emOpcoes && preg_match('/^AREA\s*(.*)$/', $l, $a)) { $area = trim(explode(' ', trim($a[1]))[0] ?? ''); continue; }
            if (trim($l) === 'OPTIONS') { $emOpcoes = true; continue; }
            if ($emOpcoes) {
                if (preg_match('/^([A-E])(:CORRECT)?\)\s*(.*)$/', $l, $o)) {
                    $atualAlt = ord($o[1]) - 65;
                    $alts[$atualAlt] = [$o[3]];
                    if ($o[2]) $certa = $atualAlt;
                } elseif ($atualAlt >= 0) {
                    $alts[$atualAlt][] = $l;
                }
            } else {
                $enun[] = $l;
            }
        }
        if ($area === 'PHILOSHOPY') $area = 'PHILOSOPHY';
        if (! empty($c[2]) || $certa === null || count($alts) < 4) continue; // anulada ou sem gabarito
        $enunciado = juntar($enun);
        if (mb_strlen($enunciado) < 20) continue;
        $oab[] = [
            'exame' => $nome, 'ano' => $ano, 'num' => $num, 'reap' => $reap, 'n' => (int) $c[1], 'area' => isset(AREAS[$area]) ? $area : '',
            'enunciado' => $enunciado, 'alts' => array_map(fn ($x) => juntar($x), array_values($alts)), 'certa' => $certa,
        ];
    }
}

// classificador simples (Naive Bayes) treinado nas questões com área
$cont = [];
$tot = [];
$vocab = [];
$docs = [];
foreach ($oab as $q) {
    if (! $q['area']) continue;
    $docs[$q['area']] = ($docs[$q['area']] ?? 0) + 1;
    foreach (palavras($q['enunciado'] . ' ' . implode(' ', $q['alts'])) as $w) {
        $cont[$q['area']][$w] = ($cont[$q['area']][$w] ?? 0) + 1;
        $tot[$q['area']] = ($tot[$q['area']] ?? 0) + 1;
        $vocab[$w] = 1;
    }
}
$V = count($vocab);
$pontos = function (array $q, string $area) use ($cont, $tot, $V): float {
    $s = 0.0;
    foreach (palavras($q['enunciado'] . ' ' . implode(' ', $q['alts'])) as $w) {
        $s += log((($cont[$area][$w] ?? 0) + 1) / (($tot[$area] ?? 0) + $V));
    }

    return $s;
};
// por prova: as matérias vêm em blocos na ordem fixa; programação dinâmica escolhe os blocos
$porExame = [];
foreach ($oab as $i => $q) $porExame[$q['exame']][] = $i;
foreach ($porExame as $exame => $idx) {
    $faltam = array_filter($idx, fn ($i) => ! $oab[$i]['area']);
    if (! $faltam) continue;
    usort($idx, fn ($a, $b) => $oab[$a]['n'] <=> $oab[$b]['n']);
    $K = count(ORDEM);
    $n = count($idx);
    $dp = array_fill(0, $n, array_fill(0, $K, -INF));
    $de = array_fill(0, $n, array_fill(0, $K, -1));
    foreach ($idx as $t => $i) {
        $q = $oab[$i];
        for ($k = 0; $k < $K; $k++) {
            [$lo, $hi] = MOLDE[ORDEM[$k]];
            $fora = $q['n'] < $lo ? $lo - $q['n'] : ($q['n'] > $hi ? $q['n'] - $hi : 0);
            $sc = $q['area'] ? ($q['area'] === ORDEM[$k] ? 0.0 : -INF) : $pontos($q, ORDEM[$k]) - ($fora ? 6 + 30 * ($fora - 1) : 0);
            if ($sc === -INF) continue;
            if ($t === 0) { $dp[0][$k] = $sc; continue; }
            $melhor = -INF; $arg = -1;
            for ($j = 0; $j <= $k; $j++) {
                if ($dp[$t - 1][$j] > $melhor) { $melhor = $dp[$t - 1][$j]; $arg = $j; }
            }
            if ($arg >= 0) { $dp[$t][$k] = $melhor + $sc; $de[$t][$k] = $arg; }
        }
    }
    $k = array_keys($dp[$n - 1], max($dp[$n - 1]))[0];
    for ($t = $n - 1; $t >= 0; $t--) {
        if (! $oab[$idx[$t]]['area']) $oab[$idx[$t]]['area'] = ORDEM[$k];
        $k = $de[$t][$k];
        if ($k < 0 && $t > 0) break;
    }
}

/* ---------- assunto por palavras-chave ---------- */
const ASSUNTOS = [
    'Ética Profissional' => ['Honorários' => 'honor[áa]ri', 'Sociedade de advogados' => 'sociedade de advogados', 'Infrações e sanções' => 'infra[çc][ãa]o|san[çc][ãa]o|suspens|exclus|censura|processo disciplinar', 'Inscrição na OAB' => 'inscri[çc][ãa]o|estagi', 'Sigilo e publicidade' => 'sigilo|publicidade|an[úu]ncio', 'Mandato e procuração' => 'mandato|procura[çc][ãa]o|substabelec|ren[úu]ncia'],
    'Direito Constitucional' => ['Controle de constitucionalidade' => 'inconstitucional|controle de constitucionalidade|adi\b|adpf|a[çc][ãa]o direta', 'Direitos fundamentais' => 'direitos? fundamenta|habeas|mandado de seguran|liberdade de', 'Poder Legislativo' => 'congresso|senado|c[âa]mara dos deputados|processo legislativo|emenda|medida provis', 'Poder Executivo' => 'presidente da rep[úu]blica|governador|prefeito|ministro de estado', 'Poder Judiciário' => 'supremo tribunal|stf|stj|poder judici|magistrad', 'Organização do Estado' => 'munic[íi]pio|estados-membros|uni[ãa]o federal|compet[êe]ncia (legislativa|comum|privativa)|interven[çc][ãa]o federal'],
    'Direito Civil' => ['Contratos' => 'contrat|compra e venda|loca[çc][ãa]o|doa[çc][ãa]o|fian[çc]a', 'Família' => 'casamento|div[óo]rcio|uni[ãa]o est[áa]vel|alimentos|guarda|filia[çc][ãa]o', 'Sucessões' => 'heran[çc]a|herdeir|testament|sucess[ãa]o|invent[áa]rio', 'Responsabilidade civil' => 'responsabilidade civil|indeniza|dano moral', 'Direitos reais' => 'propriedade|posse|usucapi|hipoteca|servid[ãa]o|condom[íi]nio', 'Obrigações' => 'obriga[çc][ãa]o|credor|devedor|pagamento|juros', 'Parte geral' => 'prescri[çc][ãa]o|decad[êe]ncia|capacidade|neg[óo]cio jur[íi]dico|pessoa jur[íi]dica'],
    'Direito Penal' => ['Crimes contra a pessoa' => 'homic[íi]dio|les[ãa]o corporal|estupro|feminic', 'Crimes contra o patrimônio' => 'furto|roubo|estelionato|extors[ãa]o|recepta[çc]', 'Penas e aplicação' => 'pena |dosimetria|regime (fechado|semiaberto|aberto)|substitui[çc][ãa]o da pena|sursis', 'Teoria do crime' => 'dolo|culpa|tentativa|leg[íi]tima defesa|estado de necessidade|erro de', 'Extinção da punibilidade' => 'prescri[çc][ãa]o|extin[çc][ãa]o da punibilidade|decad[êe]ncia'],
    'Direito Processual Penal' => ['Prisão e liberdade' => 'pris[ãa]o|flagrante|fian[çc]a|preventiva|tempor[áa]ria|liberdade provis', 'Recursos' => 'recurso|apela[çc][ãa]o|habeas corpus|revis[ãa]o criminal|embargos', 'Provas' => 'prova|testemunha|per[íi]cia|interrogat', 'Ação penal' => 'a[çc][ãa]o penal|den[úu]ncia|queixa|inqu[ée]rito', 'Júri' => 'j[úu]ri|jurados|pron[úu]ncia'],
    'Direito Processual Civil' => ['Recursos' => 'recurso|apela[çc][ãa]o|agravo|embargos de declara|recurso especial', 'Execução' => 'execu[çc][ãa]o|cumprimento de senten|penhora', 'Tutelas provisórias' => 'tutela|liminar|urg[êe]ncia', 'Procedimentos especiais' => 'possess[óo]ri|monit[óo]ri|invent[áa]rio|usucapi', 'Petição e resposta' => 'peti[çc][ãa]o inicial|contesta[çc][ãa]o|reconven[çc][ãa]o|revelia|cita[çc][ãa]o'],
    'Direito do Trabalho' => ['Jornada e férias' => 'jornada|horas? extra|f[ée]rias|intervalo|descanso', 'Rescisão' => 'rescis[ãa]o|dispensa|justa causa|aviso pr[ée]vio|fgts', 'Salário e remuneração' => 'sal[áa]rio|remunera[çc][ãa]o|gorjeta|adicional', 'Estabilidade' => 'estabilidade|gestante|cipa|acidente de trabalho'],
    'Direito Processual do Trabalho' => ['Recursos' => 'recurso|agravo|embargos', 'Execução' => 'execu[çc][ãa]o|penhora', 'Audiência e provas' => 'audi[êe]ncia|prova|testemunha|revelia'],
    'Direito Tributário' => ['Impostos' => 'icms|ipi|iptu|ipva|itbi|itcmd|imposto de renda|iss\b|imposto', 'Crédito tributário' => 'cr[ée]dito tribut[áa]rio|lan[çc]amento|prescri[çc][ãa]o|decad[êe]ncia|execu[çc][ãa]o fiscal', 'Limitações ao poder de tributar' => 'imunidade|anterioridade|legalidade tribut|isen[çc][ãa]o', 'Taxas e contribuições' => 'taxa|contribui[çc][ãa]o de melhoria|contribui[çc][õo]es'],
    'Direito Administrativo' => ['Licitações e contratos' => 'licita[çc][ãa]o|preg[ãa]o|contrato administrativo|concess[ãa]o|permiss[ãa]o', 'Servidores públicos' => 'servidor|cargo p[úu]blico|concurso p[úu]blico|est[áa]gio probat|aposentadoria', 'Atos administrativos' => 'ato administrativo|anula[çc][ãa]o|revoga[çc][ãa]o|poder de pol[íi]cia', 'Improbidade' => 'improbidade', 'Responsabilidade do Estado' => 'responsabilidade (civil )?do estado|responsabilidade objetiva', 'Bens públicos e intervenção' => 'desapropria|tombamento|bens p[úu]blicos|servid[ãa]o administrativa'],
    'Direito Empresarial' => ['Sociedades' => 'sociedade|s[óo]cio|acionista|companhia|ltda', 'Títulos de crédito' => 'cheque|nota promiss[óo]ria|duplicata|letra de c[âa]mbio|t[íi]tulo de cr[ée]dito|aval|endosso', 'Falência e recuperação' => 'fal[êe]ncia|recupera[çc][ãa]o judicial|recupera[çc][ãa]o extrajudicial', 'Propriedade industrial' => 'marca|patente|propriedade industrial'],
    'Matemática' => ['Porcentagem e juros' => 'porcent|%|juros|desconto|lucro', 'Probabilidade' => 'probabilidade|chance|sorte', 'Estatística' => 'm[ée]dia|mediana|moda|desvio|gr[áa]fico|tabela', 'Geometria' => '[áa]rea|volume|tri[âa]ngulo|c[íi]rculo|cilindro|cubo|[âa]ngulo|per[íi]metro|esfera|cone|pir[âa]mide', 'Funções' => 'fun[çc][ãa]o|par[áa]bola|exponencial|logaritm', 'Razão e proporção' => 'raz[ãa]o|propor[çc]|escala|regra de tr[êe]s', 'Contagem' => 'combina[çc]|arranjo|permuta|quantas maneiras|quantidade de modos'],
    'Ciências da Natureza' => ['Biologia' => 'c[ée]lula|gen[ée]tic|dna|esp[ée]cie|organismo|ecossistema|v[íi]rus|bact[ée]ria|prote[íi]na|evolu[çc]|plantas?|animal|doen[çc]a|sangue', 'Química' => 'qu[íi]mic|rea[çc][ãa]o|mol[ée]cula|[áa]tomo|[íi]ons?|solu[çc][ãa]o|ph\b|[áa]cido|combust[ãa]o|elemento|subst[âa]ncia|composto', 'Física' => 'energia|velocidade|for[çc]a|corrente el[ée]tric|tens[ãa]o|resist[êe]ncia|onda|luz|temperatura|calor|press[ãa]o|massa|acelera'],
    'Ciências Humanas' => ['História' => 's[ée]culo|imp[ée]rio|col[ôo]nia|revolu[çc][ãa]o|guerra|ditadura|rep[úu]blica|escravid|medieval|antig', 'Geografia' => 'clima|relevo|urbaniza|popula[çc][ãa]o|territ[óo]ri|agr[íi]col|bioma|migra[çc]|globaliza|ambient|regi[ãa]o', 'Filosofia' => 'fil[óo]sof|plat[ãa]o|arist[óo]teles|kant|descartes|nietzsche|[ée]tica|raz[ãa]o', 'Sociologia' => 'sociedade|social|trabalho|cultura|movimento|desigualdade|cidadania'],
    'Linguagens' => ['Língua estrangeira' => '\b(the|and|of|is|el|los|las|que es)\b', 'Literatura' => 'poema|poeta|romance|narrador|liter[áa]ri|verso|personagem|conto', 'Artes' => 'arte|pintura|obra|escultura|m[úu]sica|dan[çc]a|teatro|artista', 'Educação física' => 'esporte|atividade f[íi]sica|corpo|exerc[íi]cio|jogo|atleta', 'Gramática e variação' => 'variante|varia[çc][ãa]o lingu|norma-padr[ãa]o|regional|g[íi]ria|uso da l[íi]ngua', 'Gêneros e interpretação' => 'texto|g[êe]nero|leitor|argument|publicidade|campanha|not[íi]cia|reportagem'],
];

function assunto(string $materia, string $texto): string
{
    $melhor = 'Geral';
    $max = 0;
    foreach (ASSUNTOS[$materia] ?? [] as $nome => $re) {
        $n = preg_match_all('/' . $re . '/iu', $texto);
        if ($n > $max) { $max = $n; $melhor = $nome; }
    }

    return $melhor;
}

/* ---------- saída ---------- */
$saida = [];
foreach ($oab as $q) {
    if (! $q['area']) continue;
    $materia = AREAS[$q['area']];
    $prova = 'OAB · Exame de Ordem ' . ($q['num'] === 1 ? '2010.1' : (ROMANOS[$q['num']] ?? $q['num'])) . ($q['reap'] ? ' (reaplicação)' : '');
    $alts = array_slice(array_pad($q['alts'], 5, ''), 0, 5);
    $saida[] = [
        'banca' => $q['num'] === 1 ? 'Cespe/Cebraspe' : 'FGV', 'prova' => $prova, 'ano' => $q['ano'], 'materia' => $materia,
        'assunto' => assunto($materia, $q['enunciado'] . ' ' . implode(' ', $alts)), 'enunciado' => $q['enunciado'], 'alts' => $alts,
        'certa' => $q['certa'], 'fonte' => 'oab', 'ref' => $q['exame'] . '#' . $q['n'],
    ];
}
foreach (glob($dir . '/maritaca20*.jsonl') as $arq) {
    foreach (file($arq) as $linha) {
        $q = json_decode($linha, true);
        if (! $q || ! empty($q['IU']) || str_contains($q['question'], '[[placeholder]]') || empty($q['label']) || ! empty($q['figures'])) continue;
        $n = (int) preg_replace('/\D/', '', $q['id']);
        $materia = $n <= 45 ? 'Linguagens' : ($n <= 90 ? 'Ciências Humanas' : ($n <= 135 ? 'Ciências da Natureza' : 'Matemática'));
        $alts = array_map(fn ($a) => limpar((string) $a), array_slice(array_pad($q['alternatives'], 5, ''), 0, 5));
        $enun = limpar(preg_replace('/^#+\s*/m', '', $q['question']));
        $assunto = $n <= 5 ? 'Língua estrangeira' : assunto($materia, $enun . ' ' . implode(' ', $alts));
        $saida[] = [
            'banca' => 'INEP', 'prova' => 'ENEM ' . $q['exam'], 'ano' => (int) $q['exam'], 'materia' => $materia, 'assunto' => $assunto,
            'enunciado' => $enun, 'alts' => $alts, 'certa' => ord($q['label']) - 65, 'fonte' => 'enem', 'ref' => 'ENEM_' . $q['exam'] . '_' . $n,
        ];
    }
}

// dificuldade estimada: terços do tamanho do texto dentro de cada matéria
$porMateria = [];
foreach ($saida as $i => $q) $porMateria[$q['materia']][$i] = mb_strlen($q['enunciado'] . implode('', $q['alts']));
foreach ($porMateria as $lista) {
    asort($lista);
    $n = count($lista);
    $k = 0;
    foreach ($lista as $i => $_) {
        $saida[$i]['dificuldade'] = $k < $n / 3 ? 'facil' : ($k < 2 * $n / 3 ? 'media' : 'dificil');
        $k++;
    }
}

$gz = gzopen(__DIR__ . '/questoes.jsonl.gz', 'wb9');
foreach ($saida as $q) gzwrite($gz, json_encode($q, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
gzclose($gz);

$resumo = [];
foreach ($saida as $q) $resumo[$q['banca'] . ' · ' . $q['materia']] = ($resumo[$q['banca'] . ' · ' . $q['materia']] ?? 0) + 1;
ksort($resumo);
echo count($saida) . " questões\n";
foreach ($resumo as $k => $v) echo "  $k: $v\n";
