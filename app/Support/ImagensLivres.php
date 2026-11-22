<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Imagens de bancos gratuitos com licença livre para o gerador de workflows (GeradorWorkflow).
 *   - Openverse (sem chave): só licenças que permitem reusar, inclusive comercialmente: CC0, domínio público, CC BY e CC BY-SA.
 *   - Pexels (licença Pexels, uso livre): só se PEXELS_API_KEY estiver no .env.
 * Cada imagem volta já baixada como data URL (cabe no cartão de imagem e no fundo) e com o crédito para registrar.
 */
class ImagensLivres
{
    public const MAX_BYTES = 1_500_000;

    /** @return list<array{src: string, titulo: string, autor: string, licenca: string, fonte: string, banco: string}> */
    public static function buscar(string $termo, int $quantas = 2): array
    {
        $termo = trim(mb_substr($termo, 0, 80));
        if ($termo === '') return [];
        $achadas = config('services.pexels.chave') ? self::pexels($termo, $quantas) : [];
        if (count($achadas) < $quantas) {
            $achadas = array_merge($achadas, self::openverse($termo, $quantas - count($achadas)));
        }

        return array_slice($achadas, 0, $quantas);
    }

    private static function openverse(string $termo, int $quantas): array
    {
        // a API pública do Openverse demora uns 15 s e às vezes responde 504: espera até 25 s e tenta uma segunda vez
        $r = null;
        for ($tentativa = 0; $tentativa < 2 && ! $r?->successful(); $tentativa++) {
            try {
                $r = Http::timeout(25)->acceptJson()->withUserAgent('WorkFlower/1.0')
                    ->get('https://api.openverse.org/v1/images/', [
                        'q' => $termo, 'license' => 'cc0,pdm,by,by-sa', 'page_size' => min(20, $quantas * 4), 'mature' => 'false',
                    ]);
            } catch (\Throwable) {
                $r = null;
            }
        }
        if (! $r?->successful()) return [];

        $saida = [];
        foreach ((array) $r->json('results') as $x) {
            if (count($saida) >= $quantas) break;
            // a original (boa resolução) se couber; senão a miniatura do Openverse
            $src = self::baixar((string) ($x['url'] ?? '')) ?? self::baixar((string) ($x['thumbnail'] ?? ''));
            if (! $src) continue;
            $licenca = strtoupper((string) ($x['license'] ?? ''));
            $saida[] = [
                'src'     => $src,
                'titulo'  => mb_substr(trim((string) ($x['title'] ?? '')), 0, 80) ?: 'Sem título',
                'autor'   => mb_substr(trim((string) ($x['creator'] ?? '')), 0, 60) ?: 'autor desconhecido',
                'licenca' => in_array($licenca, ['CC0', 'PDM'], true) ? ($licenca === 'PDM' ? 'domínio público' : 'CC0') : 'CC ' . $licenca . ' ' . ($x['license_version'] ?? ''),
                'fonte'   => (string) ($x['foreign_landing_url'] ?? $x['url'] ?? ''),
                'banco'   => 'Openverse',
            ];
        }

        return $saida;
    }

    private static function pexels(string $termo, int $quantas): array
    {
        try {
            $r = Http::timeout(12)->acceptJson()->withHeaders(['Authorization' => (string) config('services.pexels.chave')])
                ->get('https://api.pexels.com/v1/search', ['query' => $termo, 'per_page' => $quantas * 2, 'orientation' => 'landscape']);
        } catch (\Throwable) {
            return [];
        }
        if (! $r->successful()) return [];

        $saida = [];
        foreach ((array) $r->json('photos') as $x) {
            if (count($saida) >= $quantas) break;
            $src = self::baixar((string) data_get($x, 'src.medium', ''));
            if (! $src) continue;
            $saida[] = [
                'src' => $src, 'titulo' => mb_substr(trim((string) ($x['alt'] ?? '')), 0, 80) ?: 'Foto',
                'autor' => mb_substr(trim((string) ($x['photographer'] ?? '')), 0, 60) ?: 'Pexels',
                'licenca' => 'licença Pexels', 'fonte' => (string) ($x['url'] ?? ''), 'banco' => 'Pexels',
            ];
        }

        return $saida;
    }

    /** Baixa a imagem (só http(s), só tipos de imagem, até MAX_BYTES) e devolve como data URL. */
    private static function baixar(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) return null;
        try {
            $r = Http::timeout(15)->withUserAgent('WorkFlower/1.0')->get($url);
        } catch (\Throwable) {
            return null;
        }
        $tipo = strtolower(trim(explode(';', (string) $r->header('Content-Type'))[0]));
        $corpo = $r->body();
        if (! $r->successful() || ! in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true) || $corpo === '' || strlen($corpo) > self::MAX_BYTES) {
            return null;
        }

        return 'data:' . $tipo . ';base64,' . base64_encode($corpo);
    }

    /** Linha de crédito de uma imagem. */
    public static function credito(array $i): string
    {
        return '“' . $i['titulo'] . '”, de ' . $i['autor'] . ' (' . trim($i['licenca']) . ', via ' . $i['banco'] . ')' . ($i['fonte'] ? ': ' . $i['fonte'] : '');
    }
}
