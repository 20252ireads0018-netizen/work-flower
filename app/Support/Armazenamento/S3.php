<?php

namespace App\Support\Armazenamento;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Bucket S3-compatível (AWS S3, Cloudflare R2, Backblaze B2, Wasabi, MinIO...) sem SDK:
 * o servidor de produção não tem composer nem o adaptador do Flysystem, então as requisições são
 * assinadas aqui mesmo com AWS Signature V4 e enviadas pelo Http do Laravel. Endereço no estilo
 * "path" (endpoint/bucket/chave), que todos esses serviços aceitam.
 *
 * Credenciais: endpoint (vazio = AWS), regiao, bucket, chave (access key id), segredo, prefixo (pasta).
 */
class S3 implements Destino
{
    /** Acima disto o envio vai em partes deste tamanho (o S3 exige no mínimo 5 MB por parte, menos a última). */
    public const PEDACO = 8 * 1024 * 1024;

    private string $endpoint;
    private string $regiao;
    private string $bucket;
    private string $chave;
    private string $segredo;
    private string $prefixo;

    public function __construct(array $c)
    {
        $this->regiao   = trim((string) ($c['regiao'] ?? '')) ?: self::regiaoDoEndpoint((string) ($c['endpoint'] ?? ''));
        $this->endpoint = rtrim(trim((string) ($c['endpoint'] ?? '')) ?: 'https://s3.' . $this->regiao . '.amazonaws.com', '/');
        $this->bucket   = (string) ($c['bucket'] ?? '');
        $this->chave    = (string) ($c['chave'] ?? '');
        $this->segredo  = (string) ($c['segredo'] ?? '');
        $this->prefixo  = trim((string) ($c['prefixo'] ?? 'work-flower'), '/');
    }

    /** Região tirada do endereço quando o usuário não preenche (R2 usa "auto"). */
    public static function regiaoDoEndpoint(string $endpoint): string
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        if (str_ends_with($host, 'r2.cloudflarestorage.com')) return 'auto';
        if (preg_match('/^s3[.-]([a-z0-9-]+)\.(amazonaws\.com|backblazeb2\.com|wasabisys\.com)$/', $host, $m)) return $m[1];

        return 'us-east-1';
    }

    public function enviar(string $caminho, string $conteudo, string $mime): string
    {
        $chave = $this->chaveCompleta($caminho);
        if (strlen($conteudo) > self::PEDACO) {
            $this->enviarEmPartes($chave, $conteudo, $mime);

            return $chave;
        }

        $r = $this->requisicao('PUT', $chave, $conteudo, ['Content-Type' => $mime]);
        $this->conferir($r, 'enviar o arquivo');

        return $chave;
    }

    /**
     * Arquivo grande (backup com PDFs, PDF de 40 MB): "multipart upload" do S3 em pedaços de 8 MB,
     * cada um numa requisição curta. Se algo falhar no meio, o envio é cancelado no bucket.
     */
    private function enviarEmPartes(string $chave, string $conteudo, string $mime): void
    {
        $r = $this->requisicao('POST', $chave, '', ['Content-Type' => $mime], ['uploads' => '']);
        $this->conferir($r, 'começar o envio em partes');
        if (! preg_match('#<UploadId>([^<]+)</UploadId>#', $r->body(), $m)) {
            throw new RuntimeException('O bucket não aceitou o envio em partes.');
        }
        $id = html_entity_decode($m[1], ENT_XML1);

        try {
            $partes = '';
            $total = (int) ceil(strlen($conteudo) / self::PEDACO);
            for ($n = 1; $n <= $total; $n++) {
                $pedaco = substr($conteudo, ($n - 1) * self::PEDACO, self::PEDACO);
                $r = $this->requisicao('PUT', $chave, $pedaco, [], ['partNumber' => (string) $n, 'uploadId' => $id]);
                $this->conferir($r, "enviar a parte {$n} de {$total}");
                $etag = $r->header('ETag');
                $partes .= '<Part><PartNumber>' . $n . '</PartNumber><ETag>' . htmlspecialchars($etag, ENT_XML1) . '</ETag></Part>';
            }

            $xml = '<CompleteMultipartUpload>' . $partes . '</CompleteMultipartUpload>';
            $r = $this->requisicao('POST', $chave, $xml, ['Content-Type' => 'application/xml'], ['uploadId' => $id]);
            $this->conferir($r, 'juntar as partes');
            if (str_contains($r->body(), '<Error>')) {
                throw new RuntimeException('O bucket não conseguiu juntar as partes do arquivo.');
            }
        } catch (RuntimeException $e) {
            try { $this->requisicao('DELETE', $chave, '', [], ['uploadId' => $id]); } catch (\Throwable $x) {}
            throw $e;
        }
    }

    public function baixar(string $remoto): string
    {
        $r = $this->requisicao('GET', $remoto);
        $this->conferir($r, 'baixar o arquivo');

        return $r->body();
    }

    public function apagar(string $remoto): void
    {
        $r = $this->requisicao('DELETE', $remoto);
        if ($r->status() !== 404) $this->conferir($r, 'apagar o arquivo');
    }

    public function testar(): void
    {
        $chave = $this->enviar('.teste-conexao.txt', 'Work Flower ' . now()->toIso8601String(), 'text/plain');
        $this->apagar($chave);
    }

    public function urlTemporaria(string $remoto, int $segundos = 300): ?string
    {
        $agora  = now()->utc()->format('Ymd\THis\Z');
        $dia    = substr($agora, 0, 8);
        $escopo = "{$dia}/{$this->regiao}/s3/aws4_request";
        $host   = $this->host();

        $query = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->chave . '/' . $escopo,
            'X-Amz-Date'          => $agora,
            'X-Amz-Expires'       => (string) $segundos,
            'X-Amz-SignedHeaders' => 'host',
        ];
        $qs = self::queryCanonica($query);
        $canonica = implode("\n", ['GET', $this->uri($remoto), $qs, 'host:' . $host, '', 'host', 'UNSIGNED-PAYLOAD']);
        $assinatura = $this->assinar($agora, $escopo, $canonica);

        return $this->endpoint . $this->uri($remoto) . '?' . $qs . '&X-Amz-Signature=' . $assinatura;
    }

    // ------------------------------------------------------------ assinatura

    private function requisicao(string $metodo, string $chave, string $corpo = '', array $extra = [], array $query = []): Response
    {
        if ($this->bucket === '' || $this->chave === '' || $this->segredo === '') {
            throw new RuntimeException('O destino está sem bucket ou sem as chaves de acesso.');
        }

        $agora  = now()->utc()->format('Ymd\THis\Z');
        $dia    = substr($agora, 0, 8);
        $escopo = "{$dia}/{$this->regiao}/s3/aws4_request";
        $hash  = hash('sha256', $corpo);
        $host   = $this->host();

        $canonica = implode("\n", [
            $metodo,
            $this->uri($chave),
            $qs = self::queryCanonica($query),
            'host:' . $host,
            'x-amz-content-sha256:' . $hash,
            'x-amz-date:' . $agora,
            '',
            'host;x-amz-content-sha256;x-amz-date',
            $hash,
        ]);

        $cabecalhos = $extra + [
            'x-amz-content-sha256' => $hash,
            'x-amz-date'           => $agora,
            'Authorization'        => 'AWS4-HMAC-SHA256 Credential=' . $this->chave . '/' . $escopo
                . ', SignedHeaders=host;x-amz-content-sha256;x-amz-date, Signature=' . $this->assinar($agora, $escopo, $canonica),
        ];

        try {
            $http = Http::timeout(120)->withHeaders($cabecalhos);
            $url  = $this->endpoint . $this->uri($chave) . ($qs !== '' ? '?' . $qs : '');

            return match ($metodo) {
                'PUT'    => $http->withBody($corpo, $extra['Content-Type'] ?? 'application/octet-stream')->put($url),
                'POST'   => $http->withBody($corpo, $extra['Content-Type'] ?? 'application/octet-stream')->post($url),
                'DELETE' => $http->delete($url),
                default  => $http->get($url),
            };
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new RuntimeException('Não consegui falar com ' . $this->host() . '. Confira o endereço (endpoint).');
        }
    }

    private function assinar(string $agora, string $escopo, string $canonica): string
    {
        $paraAssinar = "AWS4-HMAC-SHA256\n{$agora}\n{$escopo}\n" . hash('sha256', $canonica);
        $k = hash_hmac('sha256', substr($agora, 0, 8), 'AWS4' . $this->segredo, true);
        $k = hash_hmac('sha256', $this->regiao, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);

        return hash_hmac('sha256', $paraAssinar, $k);
    }

    private function host(): string
    {
        $p = parse_url($this->endpoint);
        $host = strtolower($p['host'] ?? '');
        $porta = $p['port'] ?? null;
        if ($porta && ! (($p['scheme'] ?? 'https') === 'https' && $porta == 443) && ! (($p['scheme'] ?? '') === 'http' && $porta == 80)) {
            $host .= ':' . $porta;
        }

        return $host;
    }

    /** /bucket/chave com cada pedaço codificado (a "/" fica). Bucket vazio = o bucket já está no endpoint. */
    private function uri(string $chave): string
    {
        $base = rtrim((string) (parse_url($this->endpoint, PHP_URL_PATH) ?? ''), '/');
        $caminho = trim($this->bucket, '/') !== '' ? trim($this->bucket, '/') . '/' . ltrim($chave, '/') : ltrim($chave, '/');
        $partes = array_map('rawurlencode', explode('/', $caminho));

        return $base . '/' . implode('/', $partes);
    }

    private static function queryCanonica(array $q): string
    {
        ksort($q);
        $pares = [];
        foreach ($q as $k => $v) $pares[] = rawurlencode($k) . '=' . rawurlencode($v);

        return implode('&', $pares);
    }

    private function chaveCompleta(string $caminho): string
    {
        return ($this->prefixo !== '' ? $this->prefixo . '/' : '') . ltrim($caminho, '/');
    }

    private function conferir(Response $r, string $acao): void
    {
        if ($r->successful()) return;

        $codigo = preg_match('/<Code>([^<]+)<\/Code>/', $r->body(), $m) ? $m[1] : 'HTTP ' . $r->status();
        $dica = match ($codigo) {
            'SignatureDoesNotMatch', 'InvalidAccessKeyId', 'InvalidAccessKey' => ' Confira a chave e o segredo.',
            'NoSuchBucket'      => ' O bucket não existe nesse endereço.',
            'AccessDenied'      => ' A chave não tem permissão nesse bucket.',
            'AuthorizationHeaderMalformed', 'IllegalLocationConstraintException' => ' Confira a região.',
            default             => '',
        };

        throw new RuntimeException("O bucket recusou {$acao} ({$codigo}).{$dica}");
    }
}
