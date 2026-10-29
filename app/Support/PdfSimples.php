<?php

namespace App\Support;

/**
 * Gerador de PDF mínimo, sem dependências: páginas A4, retângulos (com cantos arredondados),
 * texto em Helvetica / Helvetica-Bold (WinAnsi, cobre os acentos do português) e quebra de linha.
 * Coordenadas em pontos, com a origem no canto superior esquerdo (y cresce para baixo).
 */
class PdfSimples
{
    public const LARGURA = 595.28;
    public const ALTURA  = 841.89;

    /** Larguras (milésimos de em) dos caracteres 32..126. */
    private const W_NORMAL = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    private const W_NEGRITO = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];

    /** Caracteres fora do ASCII: largura igual à da letra sem acento (ou um valor próprio). */
    private const BASE = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','í'=>'i','ì'=>'i','î'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ò'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C','“'=>'"','”'=>'"','‘'=>"'",'’'=>"'"];
    private const EXTRA = ['•' => 350, '—' => 1000, '–' => 556, '·' => 278, '…' => 1000, '✓' => 556];

    /** @var list<string> conteúdo de cada página */
    private array $paginas = [];

    private int $atual = -1;

    public function novaPagina(): void
    {
        $this->paginas[] = '';
        $this->atual = array_key_last($this->paginas);
    }

    /** Volta a escrever numa página já criada (0 é a primeira). */
    public function irParaPagina(int $i): void
    {
        $this->atual = $i;
    }

    public function totalPaginas(): int
    {
        return count($this->paginas);
    }

    /** Cor em hexadecimal (#rrggbb) para o operador de preenchimento do PDF. */
    private static function cor(string $hex): string
    {
        [$r, $g, $b] = array_map(fn ($p) => round(hexdec($p) / 255, 3), str_split(ltrim($hex, '#'), 2));

        return "$r $g $b";
    }

    private function op(string $s): void
    {
        $this->paginas[$this->atual] .= $s . "\n";
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }

    public function retangulo(float $x, float $y, float $w, float $h, string $cor, float $raio = 0): void
    {
        $y = self::ALTURA - $y - $h; // o PDF conta y de baixo para cima
        $n = fn ($v) => self::n($v);
        $this->op(self::cor($cor) . ' rg');

        if ($raio <= 0) {
            $this->op("{$n($x)} {$n($y)} {$n($w)} {$n($h)} re f");
            return;
        }

        $r = min($raio, $w / 2, $h / 2);
        $k = $r * 0.5523; // aproximação do quarto de círculo por Bézier
        $x2 = $x + $w; $y2 = $y + $h;
        $this->op(implode("\n", [
            "{$n($x + $r)} {$n($y)} m",
            "{$n($x2 - $r)} {$n($y)} l",
            "{$n($x2 - $r + $k)} {$n($y)} {$n($x2)} {$n($y + $r - $k)} {$n($x2)} {$n($y + $r)} c",
            "{$n($x2)} {$n($y2 - $r)} l",
            "{$n($x2)} {$n($y2 - $r + $k)} {$n($x2 - $r + $k)} {$n($y2)} {$n($x2 - $r)} {$n($y2)} c",
            "{$n($x + $r)} {$n($y2)} l",
            "{$n($x + $r - $k)} {$n($y2)} {$n($x)} {$n($y2 - $r + $k)} {$n($x)} {$n($y2 - $r)} c",
            "{$n($x)} {$n($y + $r)} l",
            "{$n($x)} {$n($y + $r - $k)} {$n($x + $r - $k)} {$n($y)} {$n($x + $r)} {$n($y)} c",
            'f',
        ]));
    }

    /** Largura do texto em pontos. */
    public static function largura(string $texto, float $tamanho, bool $negrito = false): float
    {
        $tabela = $negrito ? self::W_NEGRITO : self::W_NORMAL;
        $soma = 0;
        foreach (mb_str_split($texto) as $c) {
            if (isset(self::EXTRA[$c])) { $soma += self::EXTRA[$c]; continue; }
            $c = self::BASE[$c] ?? $c;
            $o = ord($c);
            $soma += strlen($c) === 1 && $o >= 32 && $o <= 126 ? $tabela[$o - 32] : 556;
        }

        return $soma * $tamanho / 1000;
    }

    /** Escreve uma linha; $y é a linha de base. $alinhar: 'e' (esquerda), 'c' (centro) ou 'd' (direita). */
    public function texto(float $x, float $y, string $texto, float $tamanho, string $cor, bool $negrito = false, string $alinhar = 'e'): void
    {
        if ($alinhar !== 'e') {
            $w = self::largura($texto, $tamanho, $negrito);
            $x -= $alinhar === 'c' ? $w / 2 : $w;
        }

        $cp = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        $cp = strtr($cp, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        $fonte = $negrito ? 'F2' : 'F1';
        $this->op('BT ' . self::cor($cor) . " rg /$fonte " . self::n($tamanho) . ' Tf ' . self::n($x) . ' ' . self::n(self::ALTURA - $y) . " Td ($cp) Tj ET");
    }

    /** Quebra o texto em linhas que cabem em $largura. */
    public static function quebrar(string $texto, float $tamanho, float $largura, bool $negrito = false): array
    {
        $linhas = [];
        $atual = '';
        foreach (preg_split('/\s+/u', trim($texto)) as $palavra) {
            $teste = $atual === '' ? $palavra : "$atual $palavra";
            if ($atual !== '' && self::largura($teste, $tamanho, $negrito) > $largura) {
                $linhas[] = $atual;
                $atual = $palavra;
            } else {
                $atual = $teste;
            }
        }
        if ($atual !== '') $linhas[] = $atual;

        return $linhas;
    }

    /** Escreve um parágrafo quebrado em linhas e devolve o y logo abaixo dele. */
    public function paragrafo(float $x, float $y, float $largura, string $texto, float $tamanho, string $cor, bool $negrito = false, float $entrelinha = 1.4): float
    {
        foreach (self::quebrar($texto, $tamanho, $largura, $negrito) as $linha) {
            $y += $tamanho * $entrelinha;
            $this->texto($x, $y - $tamanho * ($entrelinha - 1), $linha, $tamanho, $cor, $negrito);
        }

        return $y;
    }

    /** Monta o arquivo PDF. */
    public function gerar(string $titulo = ''): string
    {
        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $obj[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $obj[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $tit = strtr(mb_convert_encoding($titulo, 'Windows-1252', 'UTF-8'), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $obj[5] = "<< /Title ($tit) /Producer (Work Flower) >>";

        $kids = [];
        $prox = 6;
        foreach ($this->paginas as $conteudo) {
            $dados = gzcompress($conteudo, 9);
            $obj[$prox] = '<< /Length ' . strlen($dados) . " /Filter /FlateDecode >>\nstream\n$dados\nendstream";
            $obj[$prox + 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::LARGURA . ' ' . self::ALTURA . '] '
                . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $prox 0 R >>";
            $kids[] = ($prox + 1) . ' 0 R';
            $prox += 2;
        }
        $obj[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($obj);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($obj as $i => $corpo) {
            $offsets[$i] = strlen($pdf);
            $pdf .= "$i 0 obj\n$corpo\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf . 'trailer' . "\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n$xref\n%%EOF";
    }
}
