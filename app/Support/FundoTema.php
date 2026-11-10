<?php

namespace App\Support;

/**
 * Fundo de um workflow pronto (WorkflowsProntos), desenhado na hora com o GD: degradê de três cores na diagonal,
 * luzes desfocadas (bokeh) e um detalhe do tema (pontos, grade synthwave, ondas, estrelas, linhas de caderno).
 * Sai como data URL JPEG (o mesmo formato que o "Fundo" aceita de um arquivo enviado). Sem GD, devolve null e o
 * workflow fica só com a cor do tema.
 */
class FundoTema
{
    public const PADROES = ['bokeh', 'pontos', 'grade', 'ondas', 'estrelas', 'caderno'];

    private const L = 1280;

    private const A = 800;

    /** @param string[] $cores três cores #rrggbb (canto de cima à esquerda → canto de baixo à direita) */
    public static function gerar(array $cores, string $padrao = 'bokeh', int $semente = 1): ?string
    {
        if (! function_exists('imagecreatetruecolor') || count($cores) < 3) {
            return null;
        }
        mt_srand($semente);
        $rgb = array_map(fn ($c) => sscanf($c, '#%02x%02x%02x'), $cores);

        // degradê: desenhado pequeno e ampliado com reamostragem (fica liso e é rápido)
        $p = imagecreatetruecolor(64, 40);
        for ($y = 0; $y < 40; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $t = ($x / 63 + $y / 39) / 2;
                [$a, $b, $u] = $t < .5 ? [$rgb[0], $rgb[1], $t * 2] : [$rgb[1], $rgb[2], ($t - .5) * 2];
                imagesetpixel($p, $x, $y, imagecolorallocate($p,
                    (int) round($a[0] + ($b[0] - $a[0]) * $u), (int) round($a[1] + ($b[1] - $a[1]) * $u), (int) round($a[2] + ($b[2] - $a[2]) * $u)));
            }
        }
        $img = imagecreatetruecolor(self::L, self::A);
        imagecopyresampled($img, $p, 0, 0, 0, 0, self::L, self::A, 64, 40);
        imagedestroy($p);
        imagealphablending($img, true);

        // luzes desfocadas: círculos em camadas, cada uma mais transparente
        $luzes = $padrao === 'bokeh' ? 14 : 7;
        for ($i = 0; $i < $luzes; $i++) {
            $cx = mt_rand(0, self::L);
            $cy = mt_rand(0, self::A);
            $r = mt_rand(60, $padrao === 'bokeh' ? 220 : 160);
            $c = $rgb[mt_rand(0, 2)];
            for ($k = 6; $k >= 1; $k--) {
                $cor = imagecolorallocatealpha($img, min(255, $c[0] + 70), min(255, $c[1] + 70), min(255, $c[2] + 70), 118 - (6 - $k) * 4);
                imagefilledellipse($img, $cx, $cy, (int) ($r * $k / 3), (int) ($r * $k / 3), $cor);
            }
        }

        $branco = fn (int $alfa) => imagecolorallocatealpha($img, 255, 255, 255, $alfa);
        switch ($padrao) {
            case 'pontos':
                for ($y = 24; $y < self::A; $y += 48) {
                    for ($x = 24 + ($y / 48 % 2) * 24; $x < self::L; $x += 48) {
                        imagefilledellipse($img, (int) $x, $y, 5, 5, $branco(100));
                    }
                }
                break;
            case 'grade': // grade em perspectiva (synthwave) na metade de baixo
                imagesetthickness($img, 2);
                $h = (int) (self::A * .55);
                for ($i = 0; $i < 14; $i++) {
                    $y = $h + (int) ((self::A - $h) * ($i / 13) ** 2);
                    imageline($img, 0, $y, self::L, $y, $branco(95));
                }
                for ($i = -12; $i <= 12; $i++) {
                    imageline($img, (int) (self::L / 2 + $i * 18), $h, (int) (self::L / 2 + $i * 150), self::A, $branco(95));
                }
                imagefilledellipse($img, (int) (self::L / 2), $h - 10, 380, 380, imagecolorallocatealpha($img, 255, 210, 120, 95));
                break;
            case 'ondas':
                imagesetthickness($img, 3);
                for ($o = 0; $o < 5; $o++) {
                    $base = 260 + $o * 70;
                    $amp = 30 + $o * 12;
                    $ant = null;
                    for ($x = 0; $x <= self::L; $x += 8) {
                        $y = (int) ($base + sin($x / (90 + $o * 20) + $o) * $amp * (0.6 + 0.4 * sin($x / 300)));
                        if ($ant) imageline($img, $ant[0], $ant[1], $x, $y, $branco(90 + $o * 5));
                        $ant = [$x, $y];
                    }
                }
                break;
            case 'estrelas':
                for ($i = 0; $i < 70; $i++) {
                    $x = mt_rand(0, self::L);
                    $y = mt_rand(0, self::A);
                    $t = mt_rand(2, 9);
                    $c = $branco(mt_rand(40, 90));
                    imagefilledpolygon($img, [$x, $y - $t * 2, $x + (int) ($t / 2), $y, $x, $y + $t * 2, $x - (int) ($t / 2), $y], $c);
                    imagefilledpolygon($img, [$x - $t * 2, $y, $x, $y - (int) ($t / 2), $x + $t * 2, $y, $x, $y + (int) ($t / 2)], $c);
                }
                break;
            case 'caderno':
                for ($y = 60; $y < self::A; $y += 36) {
                    imageline($img, 0, $y, self::L, $y, $branco(105));
                }
                imagesetthickness($img, 2);
                imageline($img, 120, 0, 120, self::A, imagecolorallocatealpha($img, 255, 120, 140, 85));
                break;
        }

        ob_start();
        imagejpeg($img, null, 82);
        $bin = ob_get_clean();
        imagedestroy($img);

        return 'data:image/jpeg;base64,' . base64_encode($bin);
    }
}
