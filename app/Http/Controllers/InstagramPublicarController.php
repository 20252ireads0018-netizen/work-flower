<?php

namespace App\Http\Controllers;

use App\Models\RedeConta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Publicar no Instagram" do cartão Criador (public/js/criador-redes.js): foto única ou carrossel (até 10) com legenda,
 * pela Instagram Graph API (Content Publishing) na conta já conectada em RedesController.
 * A permissão instagram_content_publish só é pedida quando a pessoa usa o botão (redes/instagram/conectar?publicar=1).
 * A API exige que a imagem esteja num endereço público: o arquivo enviado fica em storage/app/ig-publicar, sai por um
 * link assinado que vale 30 minutos (rota redes.instagram.imagem, sem login) e é apagado assim que o post sai.
 */
class InstagramPublicarController extends Controller
{
    public const PASTA = 'ig-publicar';

    public const MAX_IMAGENS = 10;

    public const MAX_LEGENDA = 2200;

    /** Proporções aceitas pelo Instagram: de 4:5 (retrato) a 1,91:1 (paisagem). */
    private const PROPORCAO_MIN = 0.8;

    private const PROPORCAO_MAX = 1.91;

    private function conta(Request $request): ?RedeConta
    {
        if (RedesController::faltando('instagram')) return null;

        return RedeConta::where(['user_id' => $request->user()->id, 'rede' => 'instagram'])->first();
    }

    private static function conectarUrl(): string
    {
        return route('redes.conectar', 'instagram') . '?publicar=1';
    }

    /** A conta deu a permissão de publicar? (pergunta ao Facebook; guardado por 10 minutos para cada token) */
    private function podePublicar(RedeConta $conta): bool
    {
        $chave = 'redes.ig.publicar.' . $conta->id . '.' . md5((string) $conta->access_token);

        return (bool) Cache::remember($chave, now()->addMinutes(10), function () use ($conta) {
            try {
                $r = Http::timeout(10)->get(RedesController::graph() . 'me/permissions', ['access_token' => $conta->access_token]);
            } catch (\Throwable) {
                return false;
            }

            return collect($r->json('data') ?? [])->contains(fn ($p) => ($p['permission'] ?? '') === RedesController::IG_PUBLICAR && ($p['status'] ?? '') === 'granted');
        });
    }

    /** GET /redes/instagram/publicar: { conectado, pode, conectar, usuario }. */
    public function estado(Request $request): JsonResponse
    {
        $configurado = ! RedesController::faltando('instagram');
        $conta = $this->conta($request);
        if (! $conta) {
            return response()->json(['conectado' => false, 'configurado' => $configurado, 'pode' => false, 'conectar' => $configurado ? self::conectarUrl() : null]);
        }

        return response()->json(['conectado' => true, 'configurado' => true, 'pode' => $this->podePublicar($conta), 'conectar' => self::conectarUrl(), 'usuario' => $conta->usuario]);
    }

    /** POST /redes/instagram/publicar (multipart): imagens[] (1 a 10) e legenda. */
    public function publicar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'imagens'   => ['required', 'array', 'min:1', 'max:' . self::MAX_IMAGENS],
            'imagens.*' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
            'legenda'   => ['nullable', 'string', 'max:' . self::MAX_LEGENDA],
        ], [
            'imagens.required'    => 'Escolha pelo menos uma imagem.',
            'imagens.max'         => 'O carrossel aceita até ' . self::MAX_IMAGENS . ' imagens.',
            'imagens.*.mimetypes' => 'Use imagens JPG, PNG ou WebP.',
            'imagens.*.max'       => 'Cada imagem pode ter até 8 MB.',
            'legenda.max'         => 'A legenda do Instagram vai até ' . self::MAX_LEGENDA . ' caracteres.',
        ]);

        $conta = $this->conta($request);
        if (! $conta) {
            return response()->json(['ok' => false, 'erro' => 'Conecte a sua conta do Instagram primeiro.', 'conectar' => self::faltando() ? null : self::conectarUrl()], 409);
        }
        try {
            app(RedesController::class)->renovar($conta);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage(), 'conectar' => self::conectarUrl()], 409);
        }
        if (! $this->podePublicar($conta)) {
            return response()->json(['ok' => false, 'permissao' => true, 'conectar' => self::conectarUrl(),
                'erro' => 'Falta autorizar a publicação: conecte o Instagram de novo e permita “publicar conteúdo”.'], 409);
        }

        $this->limparAntigos();
        $arquivos = [];
        try {
            foreach ($d['imagens'] as $n => $img) {
                $arquivos[] = $this->guardarJpeg($img, $n + 1);
            }
        } catch (\RuntimeException $e) {
            $this->apagar($arquivos);

            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }

        @set_time_limit(180);
        try {
            $post = $this->enviarAoInstagram($conta, $arquivos, trim((string) ($d['legenda'] ?? '')));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'erro' => 'O Instagram não respondeu agora. Tente de novo em alguns minutos.'], 502);
        } finally {
            $this->apagar($arquivos);
        }
        Cache::forget('redes.metricas.' . $conta->id);

        return response()->json(['ok' => true, 'id' => $post['id'], 'link' => $post['link'],
            'mensagem' => count($arquivos) > 1 ? 'Carrossel publicado no Instagram.' : 'Foto publicada no Instagram.']);
    }

    private static function faltando(): bool
    {
        return (bool) RedesController::faltando('instagram');
    }

    /** GET /redes/instagram/imagem/{arquivo} (link assinado, sem login): a imagem para o Instagram baixar. */
    public function imagem(string $arquivo): BinaryFileResponse
    {
        $caminho = storage_path('app/' . self::PASTA . '/' . $arquivo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    /** Confere a proporção e guarda como JPEG (o Instagram só aceita JPEG). Devolve o nome do arquivo. */
    private function guardarJpeg(UploadedFile $img, int $n): string
    {
        $info = @getimagesize($img->getRealPath());
        if (! $info || ! $info[0] || ! $info[1]) {
            throw new \RuntimeException("Não consegui ler a imagem {$n}.");
        }
        $prop = $info[0] / $info[1];
        if ($prop < self::PROPORCAO_MIN - 0.01 || $prop > self::PROPORCAO_MAX + 0.01) {
            throw new \RuntimeException("A imagem {$n} está fora das proporções do Instagram: use de 4:5 (retrato) a 1,91:1 (paisagem). Quadrada (1:1) sempre serve.");
        }

        File::ensureDirectoryExists(storage_path('app/' . self::PASTA));
        $nome = Str::uuid()->toString() . '.jpg';
        $destino = storage_path('app/' . self::PASTA . '/' . $nome);
        if ($info[2] === IMAGETYPE_JPEG) {
            File::copy($img->getRealPath(), $destino);

            return $nome;
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Este servidor só consegue publicar imagens JPG. Converta a imagem para JPG e tente de novo.');
        }
        $im = @imagecreatefromstring((string) file_get_contents($img->getRealPath()));
        if (! $im) {
            throw new \RuntimeException("Não consegui converter a imagem {$n} para JPG.");
        }
        // fundo branco no lugar da transparência
        $fundo = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($fundo, 0, 0, imagecolorallocate($fundo, 255, 255, 255));
        imagecopy($fundo, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        imagejpeg($fundo, $destino, 90);
        imagedestroy($im);
        imagedestroy($fundo);

        return $nome;
    }

    /**
     * Contêiner(es) de mídia → espera ficarem prontos → media_publish. Lança RuntimeException com a mensagem para a tela.
     *
     * @return array{id: string, link: ?string}
     */
    private function enviarAoInstagram(RedeConta $conta, array $arquivos, string $legenda): array
    {
        $g = RedesController::graph();
        $t = $conta->access_token;
        $ig = $conta->externo_id;
        $url = fn (string $nome) => URL::temporarySignedRoute('redes.instagram.imagem', now()->addMinutes(30), ['arquivo' => $nome]);

        if (count($arquivos) === 1) {
            $criacao = $this->criarConteiner($g . $ig . '/media', ['image_url' => $url($arquivos[0]), 'caption' => $legenda, 'access_token' => $t]);
        } else {
            $filhos = [];
            foreach ($arquivos as $nome) {
                $filhos[] = $this->criarConteiner($g . $ig . '/media', ['image_url' => $url($nome), 'is_carousel_item' => 'true', 'access_token' => $t]);
            }
            foreach ($filhos as $f) $this->esperarPronto($g, $f, $t);
            $criacao = $this->criarConteiner($g . $ig . '/media', ['media_type' => 'CAROUSEL', 'children' => implode(',', $filhos), 'caption' => $legenda, 'access_token' => $t]);
        }
        $this->esperarPronto($g, $criacao, $t);

        $p = Http::asForm()->timeout(60)->post($g . $ig . '/media_publish', ['creation_id' => $criacao, 'access_token' => $t]);
        if (! $p->successful() || ! $p->json('id')) {
            throw new \RuntimeException($this->erro($p));
        }
        $id = (string) $p->json('id');
        $link = rescue(fn () => Http::timeout(10)->get($g . $id, ['fields' => 'permalink', 'access_token' => $t])->json('permalink'), null, false);

        return ['id' => $id, 'link' => is_string($link) ? $link : null];
    }

    private function criarConteiner(string $endpoint, array $campos): string
    {
        $r = Http::asForm()->timeout(60)->post($endpoint, $campos);
        if (! $r->successful() || ! $r->json('id')) {
            throw new \RuntimeException($this->erro($r));
        }

        return (string) $r->json('id');
    }

    /** O Instagram baixa e processa a imagem: espera o status FINISHED (fotos costumam sair na hora). */
    private function esperarPronto(string $g, string $id, string $token): void
    {
        for ($i = 0; $i < 10; $i++) {
            $s = (string) Http::timeout(10)->get($g . $id, ['fields' => 'status_code', 'access_token' => $token])->json('status_code');
            if ($s === 'FINISHED' || $s === 'PUBLISHED' || $s === '') return;
            if ($s === 'ERROR' || $s === 'EXPIRED') {
                throw new \RuntimeException('O Instagram não aceitou a imagem (confira o formato e a proporção) e não publicou nada.');
            }
            sleep(2);
        }
        throw new \RuntimeException('O Instagram demorou para processar a imagem. Confira no app em alguns minutos antes de tentar de novo.');
    }

    private function erro(\Illuminate\Http\Client\Response $r): string
    {
        $codigo = (int) $r->json('error.code');
        $sub = (int) $r->json('error.error_subcode');
        if ($codigo === 190) return 'A conexão com o Instagram venceu ou foi retirada. Conecte de novo.';
        if (in_array($codigo, [10, 200], true)) return 'O Instagram não liberou a publicação para este app. Conecte de novo e permita “publicar conteúdo”.';
        if ($codigo === 9 || $sub === 2207042) return 'A conta chegou ao limite de publicações pela API nas últimas 24 horas. Tente amanhã.';
        if (in_array($codigo, [4, 17, 32, 613], true)) return 'O Instagram limitou os pedidos por agora. Tente de novo mais tarde.';

        return 'O Instagram recusou a publicação (' . mb_substr((string) ($r->json('error.error_user_msg') ?: $r->json('error.message') ?: 'erro ' . $r->status()), 0, 200) . ').';
    }

    private function apagar(array $arquivos): void
    {
        foreach ($arquivos as $nome) {
            @unlink(storage_path('app/' . self::PASTA . '/' . $nome));
        }
    }

    /** Sobras de publicações que falharam no meio (mais de 2 horas). */
    private function limparAntigos(): void
    {
        foreach (glob(storage_path('app/' . self::PASTA . '/*.jpg')) ?: [] as $f) {
            if (@filemtime($f) < time() - 7200) @unlink($f);
        }
    }
}
