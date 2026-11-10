<?php

/**
 * Monta database/data/questoes/enem-2009-2017.jsonl.gz (provas do ENEM de 2009 a 2017, INEP) a partir do conjunto
 * eduagarcia/enem_challenge (Hugging Face), que reúne o "ENEM Challenge" de Silveira e Mauá (2017-2018).
 *
 * Uso: php database/data/questoes/construir-enem-2009-2017.php <pasta com ec_*.json>
 *   Os ec_*.json são as páginas da API de linhas do Hugging Face (100 por página):
 *   https://datasets-server.huggingface.co/rows?dataset=eduagarcia/enem_challenge&config=default&split=train&offset=0&length=100
 *
 * Matéria pela posição da questão no caderno:
 *   2009: 1–45 Ciências da Natureza, 46–90 Ciências Humanas, 91–135 Linguagens, 136–180 Matemática;
 *   2010 a 2016: 1–45 Ciências Humanas, 46–90 Ciências da Natureza, 91–135 Linguagens, 136–180 Matemática;
 *   2017 em diante: 1–45 Linguagens, 46–90 Ciências Humanas, 91–135 Ciências da Natureza, 136–180 Matemática.
 * Ficam de fora as de 2018 em diante (vêm do maritaca-ai/enem, no questoes.jsonl.gz), as anuladas e as que dependem de imagem (figura, gráfico, tirinha, mapa...), porque o cartão mostra só texto.
 */

if ($argc < 2 || ! is_dir($argv[1])) {
    fwrite(STDERR, "Uso: php construir-enem-2009-2017.php <pasta com ec_*.json>\n");
    exit(1);
}
require __DIR__ . '/comum.php';

const IMAGEM = '/\b(figura|imagem|gr[áa]fico|tirinha|charge|cartum|ilustra[çc][ãa]o|infogr[áa]fico|fotografia|foto|mapa|esquema|quadrinhos?|desenho|tabela|quadro a seguir|a seguir representad|conforme representad|observe)\b/iu';

function materiaEnem(int $ano, int $n): string
{
    $blocos = $ano === 2009 ? ['Ciências da Natureza', 'Ciências Humanas', 'Linguagens', 'Matemática'] : ($ano <= 2016
        ? ['Ciências Humanas', 'Ciências da Natureza', 'Linguagens', 'Matemática']
        : ['Linguagens', 'Ciências Humanas', 'Ciências da Natureza', 'Matemática']);

    return $blocos[min(3, intdiv(max(1, $n) - 1, 45))];
}

$saida = [];
$fora = ['anulada' => 0, 'imagem' => 0, 'sem gabarito' => 0];
foreach (glob(rtrim($argv[1], '/\\') . '/ec_*.json') as $arq) {
    foreach ((json_decode(file_get_contents($arq), true)['rows'] ?? []) as $r) {
        $q = $r['row'];
        if (! empty($q['nullified'])) { $fora['anulada']++; continue; }
        $alts = array_map(fn ($a) => limpar((string) $a), $q['choices']['text'] ?? []);
        $certa = array_search($q['answerKey'] ?? '', $q['choices']['label'] ?? [], true);
        if ($certa === false || count($alts) < 4) { $fora['sem gabarito']++; continue; }
        $enun = limpar((string) $q['question']);
        if (preg_match(IMAGEM, $enun) || mb_strlen($enun) < 30) { $fora['imagem']++; continue; }
        $ano = (int) $q['exam_year'];
        if ($ano > 2017) { $fora['2018+'] = ($fora['2018+'] ?? 0) + 1; continue; }
        $n = (int) $q['question_number'];
        $materia = materiaEnem($ano, $n);
        $estrangeira = $materia === 'Linguagens' && (($ano <= 2016 && $n <= 95) || ($ano > 2016 && $n <= 5));
        $alts = array_slice(array_pad($alts, 5, ''), 0, 5);
        $reaplic = str_contains((string) $q['exam_id'], '_');
        $saida[] = [
            'banca' => 'INEP', 'prova' => 'ENEM ' . $ano . ($reaplic ? ' (2ª aplicação)' : ''), 'ano' => $ano, 'materia' => $materia,
            'assunto' => $estrangeira ? 'Língua estrangeira' : assunto($materia, $enun . ' ' . implode(' ', $alts)),
            'enunciado' => $enun, 'alts' => $alts, 'certa' => $certa, 'fonte' => 'enem', 'ref' => 'ENEM_' . $q['exam_id'] . '_' . $n,
        ];
    }
}
usort($saida, fn ($a, $b) => [$a['ano'], $a['ref']] <=> [$b['ano'], $b['ref']]);
dificuldadePorTamanho($saida);

$gz = gzopen(__DIR__ . '/enem-2009-2017.jsonl.gz', 'wb9');
foreach ($saida as $q) gzwrite($gz, json_encode($q, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
gzclose($gz);

$resumo = [];
foreach ($saida as $q) $resumo[$q['ano'] . ' · ' . $q['materia']] = ($resumo[$q['ano'] . ' · ' . $q['materia']] ?? 0) + 1;
ksort($resumo);
echo count($saida) . ' questões (fora: ' . json_encode($fora, JSON_UNESCAPED_UNICODE) . ")\n";
foreach ($resumo as $k => $v) echo "  $k: $v\n";
