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

require __DIR__ . '/comum.php';

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

dificuldadePorTamanho($saida);

$gz = gzopen(__DIR__ . '/questoes.jsonl.gz', 'wb9');
foreach ($saida as $q) gzwrite($gz, json_encode($q, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
gzclose($gz);

$resumo = [];
foreach ($saida as $q) $resumo[$q['banca'] . ' · ' . $q['materia']] = ($resumo[$q['banca'] . ' · ' . $q['materia']] ?? 0) + 1;
ksort($resumo);
echo count($saida) . " questões\n";
foreach ($resumo as $k => $v) echo "  $k: $v\n";
