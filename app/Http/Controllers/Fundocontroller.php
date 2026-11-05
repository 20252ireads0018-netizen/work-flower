<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use App\Support\Midia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Fundo personalizado do painel (cor, imagem, ajuste, escurecer e desfoque).
 * Usa a tabela corpo_dados: chave "fundo" guarda as opções (JSON) e "fundo.img" guarda a imagem.
 */
class FundoController extends Controller
{
    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function salvar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'cor'            => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ajuste'         => ['required', 'in:cover,contain,repeat'],
            'escurecer'      => ['required', 'integer', 'between:0,90'],
            'desfoque'       => ['required', 'integer', 'between:0,20'],
            'imagem'         => ['nullable', 'string', 'max:6000000'],
            'midia'          => ['nullable', 'string', 'size:32'],
            'remover_imagem' => ['nullable', 'boolean'],
        ], [
            'cor.regex'      => 'Escolha uma cor válida.',
            'imagem.max'     => 'A imagem ficou grande demais. Escolha uma menor.',
        ]);

        $usuarioId = $request->user()->id;
        self::gravarImagem($usuarioId, 'fundo.img', $dados);

        CorpoDado::updateOrCreate(
            ['user_id' => $usuarioId, 'chave' => 'fundo'],
            ['valor' => json_encode([
                'cor'       => $dados['cor'] ?? null,
                'ajuste'    => $dados['ajuste'],
                'escurecer' => (int) $dados['escurecer'],
                'desfoque'  => (int) $dados['desfoque'],
            ])],
        );

        $linha = CorpoDado::where('user_id', $usuarioId)->where('chave', 'fundo.img')->first(['id', 'updated_at']);

        return response()->json([
            'ok'     => true,
            'imagem' => (bool) $linha,
            'versao' => $linha?->updated_at?->timestamp ?? 0,
        ]);
    }

    /** Entrega a imagem como arquivo de verdade, para o navegador guardar em cache. */
    public function imagem(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $linha = CorpoDado::where('user_id', $request->user()->id)
            ->where('chave', 'fundo.img')
            ->first();

        abort_if(! $linha, 404);

        return self::entregar($linha->valor);
    }

    /**
     * Grava (ou tira) a imagem de um fundo em corpo_dados: "midia" = token de um arquivo grande já enviado
     * (App\Support\Midia, até 25 MB), "imagem" = data URL (até ~4 MB). O arquivo antigo sai do disco quando é trocado.
     */
    public static function gravarImagem(int $dono, string $chave, array $dados): void
    {
        $antigo = CorpoDado::where('user_id', $dono)->where('chave', $chave)->value('valor');
        $tokenAntigo = Midia::tokenDoValor($antigo);
        $valor = null;
        if (! empty($dados['midia'])) {
            if (! Midia::doUsuario($dono, $dados['midia'])) {
                throw ValidationException::withMessages(['imagem' => 'A imagem enviada não foi encontrada. Escolha de novo.']);
            }
            $valor = Midia::valor($dados['midia']);
        } elseif (! empty($dados['imagem'])) {
            self::validarImagem($dados['imagem']);
            $valor = $dados['imagem'];
        } elseif (empty($dados['remover_imagem'])) {
            return;
        }

        if ($valor === null) CorpoDado::where('user_id', $dono)->where('chave', $chave)->delete();
        else CorpoDado::updateOrCreate(['user_id' => $dono, 'chave' => $chave], ['valor' => $valor]);
        if ($tokenAntigo && $tokenAntigo !== Midia::tokenDoValor($valor)) Midia::apagar($dono, $tokenAntigo);
    }

    /** Resposta com a imagem guardada (arquivo de mídia ou data URL antigo). */
    public static function entregar(string $valor): \Symfony\Component\HttpFoundation\Response
    {
        if ($token = Midia::tokenDoValor($valor)) return Midia::resposta($token);
        [$tipo, $bin] = self::decodificar($valor);

        return response($bin, 200, [
            'Content-Type'           => $tipo,
            'Cache-Control'          => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Até quanto baixa de um link (o mesmo limite dos GIFs enviados do computador). */
    public const LIMITE_LINK = Midia::MAX;

    /**
     * Baixa a imagem ou GIF de um link (imagem arrastada de outro site, link colado) e devolve os bytes,
     * para o navegador usar como se o arquivo tivesse sido escolhido. O navegador não baixa sozinho
     * por causa do CORS dos outros sites. Página em vez de imagem (Tenor, Pinterest...): usa o og:image.
     * Só endereços públicos (nada de localhost ou rede interna) e no máximo 25 MB.
     */
    public function baixar(Request $request): Response
    {
        $url = (string) $request->validate(['url' => ['required', 'string', 'max:2000']])['url'];
        $falha = function (string $m) { throw ValidationException::withMessages(['url' => $m]); };

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            [$tipo, $corpo, $proxima] = self::buscar($url, $falha);
            if ($proxima) { $url = $proxima; continue; }

            if (str_starts_with($tipo, 'text/html')) {
                // página: procura a imagem principal (og:image / twitter:image)
                if (! preg_match('/<meta[^>]+(?:property|name)=["\'](?:og:image(?::url)?|twitter:image)["\'][^>]*content=["\']([^"\']+)["\']/i', $corpo, $m)
                    && ! preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\'](?:og:image(?::url)?|twitter:image)["\']/i', $corpo, $m)) {
                    $falha('Esse link é de uma página, não de uma imagem. Abra o GIF, clique com o botão direito e use “Copiar endereço da imagem”.');
                }
                $url = self::absoluta(html_entity_decode($m[1]), $url);
                continue;
            }

            $info = @getimagesizefromstring($corpo);
            if ($info === false || ! in_array($info['mime'] ?? '', self::TIPOS, true)) {
                $falha('O link não é de uma imagem JPG, PNG, WebP ou GIF.');
            }

            return response($corpo, 200, ['Content-Type' => $info['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
        }

        $falha('O link redireciona demais. Tente o endereço direto da imagem.');
    }

    /** Uma requisição: [tipo, corpo, próximo endereço se for redirecionamento]. */
    private static function buscar(string $url, callable $falha): array
    {
        $p = parse_url($url);
        $esquema = strtolower($p['scheme'] ?? '');
        $host = $p['host'] ?? '';
        if (! in_array($esquema, ['http', 'https'], true) || $host === '' || isset($p['user'])) {
            $falha('Use um link que comece com http:// ou https://.');
        }
        $porta = (int) ($p['port'] ?? ($esquema === 'https' ? 443 : 80));
        if (! in_array($porta, [80, 443, 8080, 8443], true)) $falha('Esse endereço não é aceito.');

        // resolve o nome uma vez e prende a conexão nesse IP (evita trocar para um IP interno no meio)
        $semColchete = trim($host, '[]');
        $ip = filter_var($semColchete, FILTER_VALIDATE_IP) ? $semColchete : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $falha('Não consegui encontrar esse endereço.');
        }

        $tipo = '';
        $recebido = '';
        $grande = false;
        try {
            $resp = \Illuminate\Support\Facades\Http::withOptions([
                'allow_redirects' => false,
                'curl'            => [CURLOPT_RESOLVE => [$semColchete . ':' . $porta . ':' . $ip]],
                // para no meio se passar do limite (o cURL avisa o quanto já veio)
                'progress'        => function ($total, $baixado) use (&$grande) {
                    if ($total > self::LIMITE_LINK || $baixado > self::LIMITE_LINK) { $grande = true; throw new \RuntimeException('grande'); }
                },
            ])->withHeaders([
                'User-Agent'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
                // os mesmos cabeçalhos de um <img>: Tenor, Tumblr e outros devolvem uma página de visualização
                // em vez do GIF quando o pedido aceita HTML
                'Accept'         => 'image/avif,image/webp,image/apng,image/gif,image/*,*/*;q=0.8',
                'Sec-Fetch-Dest' => 'image',
                'Sec-Fetch-Mode' => 'no-cors',
            ])->connectTimeout(8)->timeout(20)->get($url);

            if (in_array($resp->status(), [301, 302, 303, 307, 308], true) && $resp->header('Location')) {
                return ['', '', self::absoluta($resp->header('Location'), $url)];
            }
            if (! $resp->successful()) $falha('O site respondeu com erro (' . $resp->status() . '). Tente o endereço direto da imagem.');

            $tipo = strtolower($resp->header('Content-Type'));
            $recebido = $resp->body();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($grande) $falha('Esse GIF passa de 25 MB. Escolha uma versão menor (ou reduza em ezgif.com/optimize).');
            report($e);
            $falha('Não consegui baixar desse link. Salve o GIF no computador e escolha o arquivo.');
        }

        return [$tipo, $recebido, null];
    }

    /** Endereço relativo ("/x.gif", "//cdn...") → absoluto, a partir da página onde estava. */
    private static function absoluta(string $alvo, string $base): string
    {
        if (preg_match('#^https?://#i', $alvo)) return $alvo;
        $b = parse_url($base);
        if (str_starts_with($alvo, '//')) return $b['scheme'] . ':' . $alvo;
        $raiz = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($alvo, '/')) return $raiz . $alvo;

        return $raiz . rtrim(str_replace('\\', '/', dirname($b['path'] ?? '/')), '/') . '/' . $alvo;
    }

    /** Tipo e bytes de uma imagem guardada como data URL (404 se estiver estragada). Também usado pelo fundo de cada workflow. */
    public static function decodificar(string $valor): array
    {
        $pos = strpos($valor, ';base64,');
        abort_if($pos === false, 404);
        $tipo = substr($valor, 5, $pos - 5); // "image/jpeg"
        $bin  = base64_decode(substr($valor, $pos + 8), true);
        abort_if($bin === false || ! in_array($tipo, self::TIPOS, true), 404);

        return [$tipo, $bin];
    }

    /** Só aceita JPG, PNG, WebP ou GIF e confere se o conteúdo é mesmo uma imagem. */
    public static function validarImagem(string $url): void
    {
        $erro = ValidationException::withMessages(['imagem' => 'Use uma imagem JPG, PNG, WebP ou GIF.']);

        $pos = strpos($url, ';base64,');
        if ($pos === false) {
            throw $erro;
        }

        $tipo = substr($url, 5, $pos - 5);
        if (! str_starts_with($url, 'data:') || ! in_array($tipo, self::TIPOS, true)) {
            throw $erro;
        }

        $bin = base64_decode(substr($url, $pos + 8), true);
        if ($bin === false) {
            throw $erro;
        }

        $info = @getimagesizefromstring($bin);
        if ($info === false || ! in_array($info['mime'] ?? '', self::TIPOS, true)) {
            throw $erro;
        }
    }
}