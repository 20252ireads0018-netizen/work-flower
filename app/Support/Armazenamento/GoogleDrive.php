<?php

namespace App\Support\Armazenamento;

use App\Models\ArmazenamentoDestino;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Drive pela API REST (sem SDK). Escopo "drive.file": o site só enxerga os arquivos que ele mesmo
 * criou, dentro de uma pasta "Work Flower" no Drive da pessoa.
 *
 * Credenciais do destino: access_token, refresh_token, expira_em (timestamp), email, pasta_id.
 * O app do Google (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET no .env) é criado pelo dono do site.
 */
class GoogleDrive implements Destino
{
    public const ESCOPO = 'https://www.googleapis.com/auth/drive.file openid email';

    private const AUTH  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN = 'https://oauth2.googleapis.com/token';
    private const API   = 'https://www.googleapis.com/drive/v3/files';
    private const UP    = 'https://www.googleapis.com/upload/drive/v3/files';

    /** Pedaço do envio em partes (múltiplo de 256 KB); acima disto não usa o envio simples. */
    public const PEDACO = 8 * 1024 * 1024;

    /** O envio simples (multipart) do Drive aceita até 5 MB; acima de 4 MB já vai em partes. */
    public const SIMPLES = 4 * 1024 * 1024;

    public function __construct(private ArmazenamentoDestino $destino)
    {
    }

    public static function configurado(): bool
    {
        return (string) config('armazenamento.google.client_id') !== '' && (string) config('armazenamento.google.client_secret') !== '';
    }

    public static function urlAutorizacao(string $estado, string $retorno): string
    {
        return self::AUTH . '?' . http_build_query([
            'client_id'     => config('armazenamento.google.client_id'),
            'redirect_uri'  => $retorno,
            'response_type' => 'code',
            'scope'         => self::ESCOPO,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $estado,
        ]);
    }

    /** Troca o código do retorno pelos tokens. Devolve as credenciais prontas para guardar. */
    public static function trocarCodigo(string $codigo, string $retorno): array
    {
        $r = Http::asForm()->timeout(30)->post(self::TOKEN, [
            'code'          => $codigo,
            'client_id'     => config('armazenamento.google.client_id'),
            'client_secret' => config('armazenamento.google.client_secret'),
            'redirect_uri'  => $retorno,
            'grant_type'    => 'authorization_code',
        ]);
        if (! $r->successful() || ! $r->json('refresh_token')) {
            throw new RuntimeException('O Google não liberou o acesso ao Drive. Tente conectar de novo.');
        }

        // o e-mail vem no id_token (recebido direto do Google por HTTPS, então dá para só ler o conteúdo)
        $email = null;
        $partes = explode('.', (string) $r->json('id_token'));
        if (count($partes) === 3) {
            $dados = json_decode(base64_decode(strtr($partes[1], '-_', '+/')), true);
            $email = is_array($dados) ? ($dados['email'] ?? null) : null;
        }

        return [
            'access_token'  => $r->json('access_token'),
            'refresh_token' => $r->json('refresh_token'),
            'expira_em'     => time() + (int) $r->json('expires_in', 3600),
            'email'         => is_string($email) ? $email : null,
        ];
    }

    public function enviar(string $caminho, string $conteudo, string $mime): string
    {
        if (strlen($conteudo) > self::SIMPLES) {
            return $this->enviarEmPartes($caminho, $conteudo, $mime);
        }

        $limite = 'wf' . bin2hex(random_bytes(8));
        $meta = json_encode(['name' => str_replace('/', '-', ltrim($caminho, '/')), 'parents' => [$this->pasta()]]);
        $corpo = "--{$limite}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
               . "--{$limite}\r\nContent-Type: {$mime}\r\n\r\n{$conteudo}\r\n--{$limite}--";

        $r = $this->http()->withBody($corpo, 'multipart/related; boundary=' . $limite)
            ->post(self::UP . '?uploadType=multipart&fields=id');
        $this->conferir($r, 'enviar o arquivo');

        return (string) $r->json('id');
    }

    /**
     * Envio "resumable" do Drive: abre uma sessão e manda pedaços de 8 MB (múltiplo de 256 KB, como a API pede).
     * O envio simples (multipart) do Drive só aceita até 5 MB.
     */
    private function enviarEmPartes(string $caminho, string $conteudo, string $mime): string
    {
        $total = strlen($conteudo);
        $r = $this->http()->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $total])
            ->post(self::UP . '?uploadType=resumable&fields=id', [
                'name'    => str_replace('/', '-', ltrim($caminho, '/')),
                'parents' => [$this->pasta()],
            ]);
        $this->conferir($r, 'começar o envio em partes');
        $sessao = $r->header('Location');
        if (! $sessao) throw new RuntimeException('O Google Drive não abriu o envio em partes.');

        for ($ini = 0; $ini < $total; $ini += self::PEDACO) {
            $pedaco = substr($conteudo, $ini, self::PEDACO);
            $fim = $ini + strlen($pedaco) - 1;
            $r = Http::withToken($this->token())->timeout(120)->withOptions(['allow_redirects' => false])
                ->withHeaders(['Content-Range' => "bytes {$ini}-{$fim}/{$total}"])
                ->withBody($pedaco, $mime)->put($sessao);
            if ($r->status() === 308) continue; // pedaço recebido, falta o resto
            $this->conferir($r, 'enviar o arquivo');

            return (string) $r->json('id');
        }

        throw new RuntimeException('O Google Drive não confirmou o fim do envio.');
    }

    public function baixar(string $remoto): string
    {
        $r = $this->http()->get(self::API . '/' . rawurlencode($remoto), ['alt' => 'media']);
        $this->conferir($r, 'baixar o arquivo');

        return $r->body();
    }

    public function apagar(string $remoto): void
    {
        $r = $this->http()->delete(self::API . '/' . rawurlencode($remoto));
        if ($r->status() !== 404) $this->conferir($r, 'apagar o arquivo');
    }

    public function testar(): void
    {
        $this->pasta();
    }

    public function urlTemporaria(string $remoto, int $segundos = 300): ?string
    {
        return null; // o Drive exige o token: o site repassa o arquivo
    }

    /** Tira a permissão do site no Google (ao desconectar). Falha aqui não impede desconectar. */
    public function revogar(): void
    {
        $token = $this->destino->credenciais['refresh_token'] ?? null;
        if ($token) {
            try { Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]); } catch (\Throwable $e) {}
        }
    }

    // ---------------------------------------------------------------- interno

    private function http()
    {
        return Http::withToken($this->token())->timeout(120);
    }

    /** Token de acesso válido, renovando com o refresh_token quando falta menos de um minuto. */
    private function token(): string
    {
        $c = $this->destino->credenciais ?? [];
        if (! empty($c['access_token']) && (int) ($c['expira_em'] ?? 0) > time() + 60) {
            return $c['access_token'];
        }
        if (empty($c['refresh_token'])) {
            throw new RuntimeException('A conexão com o Google Drive expirou. Conecte de novo.');
        }

        $r = Http::asForm()->timeout(30)->post(self::TOKEN, [
            'client_id'     => config('armazenamento.google.client_id'),
            'client_secret' => config('armazenamento.google.client_secret'),
            'refresh_token' => $c['refresh_token'],
            'grant_type'    => 'refresh_token',
        ]);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException('O Google recusou renovar o acesso ao Drive (a permissão pode ter sido tirada). Conecte de novo.');
        }

        $c['access_token'] = $r->json('access_token');
        $c['expira_em']    = time() + (int) $r->json('expires_in', 3600);
        $this->guardar($c);

        return $c['access_token'];
    }

    /** Id da pasta "Work Flower" no Drive, criada na primeira vez. */
    private function pasta(): string
    {
        $c = $this->destino->credenciais ?? [];
        if (! empty($c['pasta_id'])) return $c['pasta_id'];

        $r = $this->http()->post(self::API . '?fields=id', [
            'name'     => 'Work Flower',
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        $this->conferir($r, 'criar a pasta Work Flower');

        $c = $this->destino->credenciais ?? [];
        $c['pasta_id'] = (string) $r->json('id');
        $this->guardar($c);

        return $c['pasta_id'];
    }

    private function guardar(array $c): void
    {
        $this->destino->credenciais = $c;
        if ($this->destino->exists) $this->destino->save();
    }

    private function conferir(Response $r, string $acao): void
    {
        if ($r->successful()) return;

        $motivo = $r->json('error.message') ?: 'HTTP ' . $r->status();
        throw new RuntimeException('O Google Drive recusou ' . $acao . ' (' . mb_substr((string) $motivo, 0, 120) . ').');
    }
}
