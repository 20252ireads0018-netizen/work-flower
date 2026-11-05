<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Imagens e GIFs grandes guardados como arquivo (storage/app/midia/<usuário>/<token>.<ext>), para fundos da área,
 * das abas e de cada cartão. Chegam em pedaços de até ~4 MB (o tamanho que passa pelo mod_security e pelo limite de
 * POST da hospedagem) e só viram arquivo depois de conferido o conteúdo. O endereço é o token (32 letras e números),
 * impossível de adivinhar; ver exige estar logado.
 */
class Midia
{
    public const MAX = 25 * 1024 * 1024;
    public const PEDACO = 4 * 1024 * 1024 + 1024;
    public const MAX_ARQUIVOS = 300;
    public const TIPOS = ['image/gif' => 'gif', 'image/webp' => 'webp', 'image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const TOKEN = '/^[A-Za-z0-9]{32}$/';

    private static function disco()
    {
        return Storage::disk('local');
    }

    private static function erro(string $m): ValidationException
    {
        return ValidationException::withMessages(['midia' => $m]);
    }

    public static function tokenValido(?string $t): bool
    {
        return is_string($t) && preg_match(self::TOKEN, $t) === 1;
    }

    /** Começa um envio. Devolve o token. */
    public static function iniciar(int $uid, string $tipo, int $tamanho): string
    {
        if (! isset(self::TIPOS[$tipo])) throw self::erro('Use uma imagem JPG, PNG, WebP ou GIF.');
        if ($tamanho < 1 || $tamanho > self::MAX) throw self::erro('O arquivo pode ter até ' . (self::MAX / 1024 / 1024) . ' MB.');
        $existentes = count(self::disco()->files("midia/{$uid}"));
        if ($existentes >= self::MAX_ARQUIVOS * 2) throw self::erro('Você já tem muitas imagens guardadas. Tire alguns fundos antes de enviar outro.');

        $token = Str::random(32);
        self::disco()->put("midia/{$uid}/{$token}.json", json_encode(['tipo' => $tipo, 'tamanho' => $tamanho, 'recebido' => 0, 'n' => 0, 'em' => time()]));
        self::disco()->put("midia/{$uid}/{$token}.part", '');

        return $token;
    }

    private static function meta(int $uid, string $token): array
    {
        if (! self::tokenValido($token)) throw self::erro('Envio inválido.');
        $j = self::disco()->get("midia/{$uid}/{$token}.json");
        $m = $j ? json_decode($j, true) : null;
        if (! is_array($m)) throw self::erro('Esse envio não existe mais. Escolha o arquivo de novo.');

        return $m;
    }

    /** Recebe o pedaço n (em ordem, a partir de 0). */
    public static function pedaco(int $uid, string $token, int $n, string $b64): void
    {
        $m = self::meta($uid, $token);
        if ($n !== (int) $m['n']) throw self::erro('Pedaço fora de ordem. Tente de novo.');
        $bin = base64_decode($b64, true);
        if ($bin === false || $bin === '' || strlen($bin) > self::PEDACO) throw self::erro('Pedaço inválido.');
        if ($m['recebido'] + strlen($bin) > $m['tamanho']) throw self::erro('O arquivo ficou maior do que o anunciado.');

        $caminho = self::disco()->path("midia/{$uid}/{$token}.part");
        if (file_put_contents($caminho, $bin, FILE_APPEND | LOCK_EX) !== strlen($bin)) throw self::erro('Não consegui guardar o arquivo. Tente de novo.');
        $m['recebido'] += strlen($bin);
        $m['n']++;
        self::disco()->put("midia/{$uid}/{$token}.json", json_encode($m));
    }

    /** Termina o envio: confere tamanho e conteúdo e devolve o token pronto para usar. */
    public static function concluir(int $uid, string $token): array
    {
        $m = self::meta($uid, $token);
        $part = "midia/{$uid}/{$token}.part";
        if ((int) $m['recebido'] !== (int) $m['tamanho']) throw self::erro('O envio não chegou inteiro. Tente de novo.');
        $real = (string) (new \finfo(FILEINFO_MIME_TYPE))->file(self::disco()->path($part));
        if (! isset(self::TIPOS[$real])) {
            self::disco()->delete([$part, "midia/{$uid}/{$token}.json"]);
            throw self::erro('Esse arquivo não é uma imagem JPG, PNG, WebP ou GIF.');
        }
        self::disco()->move($part, "midia/{$uid}/{$token}." . self::TIPOS[$real]);
        self::disco()->delete("midia/{$uid}/{$token}.json");

        return ['token' => $token, 'tipo' => $real, 'tamanho' => (int) $m['tamanho']];
    }

    /** Caminho relativo do arquivo pronto (de qualquer usuário) ou null. */
    public static function caminho(string $token): ?string
    {
        if (! self::tokenValido($token)) return null;
        foreach (glob(self::disco()->path('midia') . "/*/{$token}.{gif,webp,png,jpg}", GLOB_BRACE) ?: [] as $f) {
            return 'midia/' . basename(dirname($f)) . '/' . basename($f);
        }

        return null;
    }

    public static function dono(string $token): ?int
    {
        $c = self::caminho($token);

        return $c ? (int) explode('/', $c)[1] : null;
    }

    /** O token é de um arquivo pronto deste usuário? */
    public static function doUsuario(int $uid, ?string $token): bool
    {
        return self::tokenValido($token) && self::dono($token) === $uid;
    }

    public static function apagar(int $uid, ?string $token): void
    {
        if (! self::doUsuario($uid, $token)) return;
        self::disco()->delete(self::caminho($token));
    }

    public static function resposta(string $token): BinaryFileResponse
    {
        $c = self::caminho($token);
        abort_unless($c, 404);
        $tipo = array_search(pathinfo($c, PATHINFO_EXTENSION), self::TIPOS, true) ?: 'application/octet-stream';

        return response()->file(self::disco()->path($c), [
            'Content-Type'           => $tipo,
            'Cache-Control'          => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ---- fundos guardados em corpo_dados: "data:..." (antigo, até 4 MB) ou "midia:<token>" (arquivo) ---- */

    public static function valor(string $token): string
    {
        return 'midia:' . $token;
    }

    public static function tokenDoValor(?string $valor): ?string
    {
        return is_string($valor) && str_starts_with($valor, 'midia:') && self::tokenValido(substr($valor, 6)) ? substr($valor, 6) : null;
    }

    /** Envios que pararam no meio (mais de um dia): apaga. Roda junto com o envio de outro arquivo. */
    public static function limparParados(int $uid): void
    {
        foreach (self::disco()->files("midia/{$uid}") as $f) {
            if (str_ends_with($f, '.json')) {
                $m = json_decode((string) self::disco()->get($f), true);
                if (! is_array($m) || ($m['em'] ?? 0) < time() - 86400) self::disco()->delete([$f, substr($f, 0, -5) . '.part']);
            }
        }
    }
}
