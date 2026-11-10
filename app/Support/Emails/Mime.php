<?php

namespace App\Support\Emails;

/** Pedaços de MIME que a lista de e-mails precisa: cabeçalhos, remetente e um trecho de texto do corpo. */
class Mime
{
    /** @return array<string, string> nome em minúsculas => valor já decodificado */
    public static function cabecalhos(string $bruto): array
    {
        $bruto = preg_replace("/\r?\n[ \t]+/", ' ', str_replace("\r\n", "\n", $bruto));
        $saida = [];
        foreach (explode("\n", $bruto) as $l) {
            if (! preg_match('/^([A-Za-z0-9-]+):\s*(.*)$/', $l, $m)) continue;
            $saida[strtolower($m[1])] = trim($m[2]);
        }

        return $saida;
    }

    /** "=?UTF-8?B?...?=" e companhia para texto UTF-8. */
    public static function decodificar(?string $v): string
    {
        if ($v === null || $v === '') return '';
        if (str_contains($v, '=?')) {
            // palavras codificadas seguidas: o espaço entre elas não conta
            $v = preg_replace('/\?=\s+=\?/', '?==?', $v);
            $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($d === false || $d === '') $d = mb_decode_mimeheader($v);
            $v = $d;
        } elseif (! mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        }

        return trim(mb_scrub($v, 'UTF-8'));
    }

    /** @return array{0: string, 1: string} [nome, e-mail] */
    public static function remetente(string $from): array
    {
        $from = self::decodificar($from);
        if (preg_match('/^(.*?)<([^>]+)>/', $from, $m)) {
            $nome = trim($m[1], " \t\"'");
            $email = trim($m[2]);
        } else {
            $email = trim($from, " <>\t");
            $nome = '';
        }
        $email = mb_strtolower($email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) $email = mb_substr($email, 0, 190);

        return [mb_substr($nome, 0, 190), mb_substr($email, 0, 190)];
    }

    /** Texto do corpo com as quebras de linha (para abrir a mensagem inteira no cartão Gmail). */
    public static function texto(string $contentType, string $transferencia, string $corpo, int $tam = 20000): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", self::textoDaParte($contentType, $transferencia, $corpo, 0) ?? '');
        $texto = preg_replace(["/[ \t\x{00A0}]+/u", "/ *\n */", "/\n{3,}/"], [' ', "\n", "\n\n"], $texto);

        return mb_substr(trim((string) $texto), 0, $tam);
    }

    /** Até $tam caracteres de texto puro do corpo (prefere text/plain; tira o HTML se só houver ele). */
    public static function trecho(string $contentType, string $transferencia, string $corpo, int $tam = 220): string
    {
        $texto = self::textoDaParte($contentType, $transferencia, $corpo, 0);
        $texto = preg_replace('/\s+/u', ' ', $texto ?? '');

        return mb_substr(trim($texto), 0, $tam);
    }

    private static function textoDaParte(string $ct, string $cte, string $corpo, int $nivel): ?string
    {
        $ctMin = strtolower($ct);
        if ($nivel < 4 && str_starts_with($ctMin, 'multipart/') && preg_match('/boundary="?([^";]+)"?/i', $ct, $b)) {
            $partes = explode('--' . $b[1], $corpo);
            array_shift($partes); // texto antes da primeira fronteira
            $html = null;
            foreach ($partes as $p) {
                if (str_starts_with($p, '--')) break; // fim
                $p = ltrim($p, "\r\n");
                $sep = preg_split("/\r?\n\r?\n/", $p, 2);
                if (count($sep) < 2) continue;
                $cab = self::cabecalhos($sep[0]);
                if (str_starts_with(strtolower($cab['content-disposition'] ?? ''), 'attachment')) continue;
                $pct = $cab['content-type'] ?? 'text/plain';
                $t = self::textoDaParte($pct, $cab['content-transfer-encoding'] ?? '', $sep[1], $nivel + 1);
                if ($t === null || trim($t) === '') continue;
                if (str_starts_with(strtolower($pct), 'text/html')) {
                    $html ??= $t;
                    continue;
                }

                return $t;
            }

            return $html;
        }
        if ($ctMin !== '' && ! str_starts_with($ctMin, 'text/') && ! str_starts_with($ctMin, 'multipart/')) return null;

        $t = self::decodificarCorpo($corpo, $cte);
        if (preg_match('/charset="?([A-Za-z0-9_.:-]+)"?/i', $ct, $c) && strtolower($c[1]) !== 'utf-8') {
            $conv = @mb_convert_encoding($t, 'UTF-8', $c[1]);
            if (is_string($conv)) $t = $conv;
        }
        if (! mb_check_encoding($t, 'UTF-8')) $t = mb_scrub($t, 'UTF-8');
        if (str_starts_with($ctMin, 'text/html')) {
            $t = preg_replace('#<(style|script|head)\b[^>]*>.*?</\1>#is', ' ', $t);
            $t = preg_replace('#<(br|/p|/div|/tr|/td|/li|/h[1-6])\b[^>]*>#i', ' ', $t);
            $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $t;
    }

    private static function decodificarCorpo(string $corpo, string $cte): string
    {
        $cte = strtolower(trim($cte));
        if ($cte === 'base64') {
            $limpo = preg_replace('/[^A-Za-z0-9+\/=]/', '', $corpo);
            $limpo = substr($limpo, 0, intdiv(strlen($limpo), 4) * 4); // o corpo vem cortado: só blocos inteiros

            return (string) base64_decode($limpo);
        }
        if ($cte === 'quoted-printable') {
            return quoted_printable_decode(preg_replace('/=[0-9A-Fa-f]?$/', '', $corpo));
        }

        return $corpo;
    }
}
