<?php

namespace App\Support\Integracoes;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Leitura das planilhas exportadas pelas redes e marketplaces (CSV com vírgula, ponto e vírgula ou tabulação,
 * ou linhas coladas de uma planilha). Os cabeçalhos são reconhecidos por apelidos em português e inglês,
 * sem acento e sem diferença de maiúsculas: "Curtidas", "Likes", "like_count" viram a mesma coluna.
 */
class Planilha
{
    /**
     * @param  array<string, list<string>>  $apelidos  campo => cabeçalhos aceitos (já normalizados)
     * @return list<array<string, string>>  linhas só com os campos reconhecidos
     */
    public static function linhas(string $texto, array $apelidos, ?int $max = null): array
    {
        $max ??= (int) config('integracoes.max_linhas', 2000);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $texto));
        $brutas = array_values(array_filter(explode("\n", $texto), fn ($l) => trim($l) !== ''));
        if (count($brutas) < 2) {
            return [];
        }

        // o separador é o que mais aparece no cabeçalho
        $cab = $brutas[0];
        $sep = collect(["\t", ';', ','])->sortByDesc(fn ($s) => substr_count($cab, $s))->first();
        $colunas = array_map([self::class, 'normalizar'], str_getcsv($cab, $sep, '"', ''));

        $mapa = [];
        foreach ($colunas as $i => $c) {
            foreach ($apelidos as $campo => $nomes) {
                if (! isset($mapa[$campo]) && in_array($c, $nomes, true)) {
                    $mapa[$campo] = $i;
                    break;
                }
            }
        }
        if (! $mapa) {
            return [];
        }

        $saida = [];
        foreach (array_slice($brutas, 1, $max) as $l) {
            $cel = str_getcsv($l, $sep, '"', '');
            $linha = [];
            foreach ($mapa as $campo => $i) {
                $linha[$campo] = trim((string) ($cel[$i] ?? ''));
            }
            if (implode('', $linha) !== '') {
                $saida[] = $linha;
            }
        }

        return $saida;
    }

    /** Cabeçalho reconhecível: minúsculo, sem acento, só letras e números separados por espaço. */
    public static function normalizar(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($s))));
    }

    /** "1.234", "1,5 mil", "2,3K", "1.2M", "R$ 12,90", "12.90", "15%" → número (null se vazio ou inválido). */
    public static function numero($v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = Str::lower(trim((string) $v));
        if ($s === '' || $s === '-' || $s === '—') {
            return null;
        }
        $mult = 1;
        if (preg_match('/(mi|milh[oõ]es|m)\s*$/u', $s)) {
            $mult = 1e6;
        } elseif (preg_match('/(mil|k)\s*$/u', $s)) {
            $mult = 1e3;
        }
        $s = preg_replace('/[^\d,.\-]/', '', $s);
        if ($s === '' || $s === '-') {
            return null;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            // o último separador é o decimal
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) {
            $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1 || ($mult === 1 && preg_match('/^\-?\d{1,3}\.\d{3}$/', $s))) {
            $s = str_replace('.', '', $s); // 1.234 = mil duzentos e trinta e quatro
        }

        return is_numeric($s) ? round((float) $s * $mult, 2) : null;
    }

    public static function inteiro($v): ?int
    {
        $n = self::numero($v);

        return $n === null ? null : (int) round($n);
    }

    /**
     * true se as datas da planilha estão no formato americano (mês/dia/ano): alguma tem o segundo número acima de 12
     * e nenhuma tem o primeiro acima de 12. Ex.: o CSV do Meta Business Suite sai como 10/31/2026.
     */
    public static function formatoEua(iterable $valores): bool
    {
        $eua = false;
        foreach ($valores as $v) {
            if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-]\d{2,4}#', trim((string) $v), $m)) {
                if ((int) $m[1] > 12) return false;
                if ((int) $m[2] > 12) $eua = true;
            }
        }

        return $eua;
    }

    /** "31/10/2026", "31/10/2026 10:00", "2026-10-31T10:00:00Z", timestamp → Carbon (null se não der). $eua: mês/dia/ano. */
    public static function data($v, bool $eua = false): ?Carbon
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        try {
            if (ctype_digit($s) && strlen($s) >= 9) {
                return Carbon::createFromTimestamp((int) (strlen($s) > 11 ? intdiv((int) $s, 1000) : $s));
            }
            if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})(?:[ T,]+(\d{1,2}):(\d{2}))?#', $s, $m)) {
                $ano = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
                [$dia, $mes] = $eua || (int) $m[2] > 12 ? [(int) $m[2], (int) $m[1]] : [(int) $m[1], (int) $m[2]]; // 10/31/2026 (formato americano)

                return checkdate($mes, $dia, $ano) ? Carbon::create($ano, $mes, $dia, (int) ($m[4] ?? 0), (int) ($m[5] ?? 0)) : null;
            }

            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
