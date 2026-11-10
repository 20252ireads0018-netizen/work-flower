<?php

namespace App\Support\Google;

use App\Models\GoogleConexao;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * YouTube pelo login do Google (cartão "YouTube" do quadro): o canal da própria pessoa, os vídeos (inclusive os
 * não listados) e as playlists. Escopo youtube.readonly: só leitura, nada é publicado ou mudado no canal.
 * Usa o mesmo app OAuth do Gmail (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET) e o mesmo repasse do GitHub Pages
 * (GOOGLE_RELAY_URL + "/youtube/"), porque o mod_security da HostGator barra o retorno direto do Google.
 * Sem login, o cartão ainda mostra canais públicos pela chave do site (YOUTUBE_API_KEY).
 */
class Youtube
{
    public const ESCOPO = 'https://www.googleapis.com/auth/youtube.readonly openid email';
    public const API = 'https://www.googleapis.com/youtube/v3/';

    public static function configurado(): bool
    {
        return (string) config('services.google.client_id') !== '' && (string) config('services.google.client_secret') !== '';
    }

    /** Pelo login dá para ler o próprio canal; pela chave, só canais públicos. */
    public static function temChave(): bool
    {
        return (string) config('services.youtube.key') !== '';
    }

    public static function retorno(): string
    {
        $relay = (string) config('services.google.relay');

        return $relay !== '' ? $relay . '/youtube/' : route('youtube.google.retorno');
    }

    public static function urlAutorizacao(string $estado): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => config('services.google.client_id'),
            'redirect_uri'  => self::retorno(),
            'response_type' => 'code',
            'scope'         => self::ESCOPO,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $estado,
        ]);
    }

    /** @return array{access_token: string, refresh_token: ?string, expira: int, email: ?string} */
    public static function trocarCodigo(string $codigo): array
    {
        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code'          => $codigo,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => self::retorno(),
            'grant_type'    => 'authorization_code',
        ]);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException('O Google não liberou o acesso ao YouTube. Tente conectar de novo.');
        }

        // e-mail no id_token, recebido direto do Google por HTTPS
        $email = null;
        $partes = explode('.', (string) $r->json('id_token'));
        if (count($partes) === 3) {
            $dados = json_decode((string) base64_decode(strtr($partes[1], '-_', '+/')), true);
            $email = is_array($dados) && is_string($dados['email'] ?? null) ? mb_strtolower($dados['email']) : null;
        }

        return [
            'access_token'  => (string) $r->json('access_token'),
            'refresh_token' => $r->json('refresh_token'),
            'expira'        => (int) $r->json('expires_in', 3600),
            'email'         => $email,
        ];
    }

    /** Token válido da conexão, renovando quando falta menos de um minuto. */
    public static function token(GoogleConexao $c): string
    {
        if ($c->access_token && $c->expira_em && $c->expira_em->isAfter(now()->addMinute())) {
            return $c->access_token;
        }
        if (! $c->refresh_token || ! self::configurado()) {
            throw new RuntimeException('O acesso ao YouTube venceu. Conecte de novo.');
        }
        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $c->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new RuntimeException('O Google cancelou o acesso ao YouTube (a permissão pode ter sido tirada). Conecte de novo.');
        }
        $c->forceFill(['access_token' => $r->json('access_token'), 'expira_em' => now()->addSeconds((int) $r->json('expires_in', 3600))])->save();

        return $c->access_token;
    }

    public static function revogar(GoogleConexao $c): void
    {
        $t = $c->refresh_token ?: $c->access_token;
        if ($t) {
            try {
                Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $t]);
            } catch (\Throwable) {
            }
        }
    }

    /* ------------------------------------------------------------------ leitura */

    /**
     * Canal, últimos vídeos e playlists. $alvo: ['mine' => 'true'] (com token) ou ['id' => ...] / ['forHandle' => ...].
     *
     * @return array{canal: array, videos: list<array>, playlists: list<array>}
     */
    public static function canal(array $alvo, ?string $token, int $max = 24): array
    {
        $c = self::get('channels', ['part' => 'snippet,statistics,contentDetails'] + $alvo, $token);
        $canal = $c->json('items.0');
        if (! $canal) {
            throw new RuntimeException(isset($alvo['mine']) ? 'Esta conta Google não tem canal no YouTube.' : 'Canal não encontrado. Confira o @ ou o link.');
        }

        $uploads = $canal['contentDetails']['relatedPlaylists']['uploads'] ?? null;
        $videos = $uploads ? self::playlist($uploads, $token, $max) : [];

        $playlists = [];
        $p = self::get('playlists', ['part' => 'snippet,contentDetails', 'channelId' => $canal['id'], 'maxResults' => 25], $token, false);
        foreach ((array) ($p?->json('items') ?? []) as $it) {
            $playlists[] = [
                'id'     => (string) $it['id'],
                'titulo' => mb_substr((string) ($it['snippet']['title'] ?? ''), 0, 120),
                'qtd'    => (int) ($it['contentDetails']['itemCount'] ?? 0),
                'thumb'  => self::thumb($it['snippet']['thumbnails'] ?? []),
            ];
        }

        return [
            'canal' => [
                'id'        => (string) $canal['id'],
                'nome'      => mb_substr((string) ($canal['snippet']['title'] ?? ''), 0, 100),
                'handle'    => $canal['snippet']['customUrl'] ?? null,
                'foto'      => self::thumb($canal['snippet']['thumbnails'] ?? []),
                'inscritos' => ($canal['statistics']['hiddenSubscriberCount'] ?? false) ? null : (int) ($canal['statistics']['subscriberCount'] ?? 0),
                'views'     => (int) ($canal['statistics']['viewCount'] ?? 0),
                'videos'    => (int) ($canal['statistics']['videoCount'] ?? 0),
            ],
            'videos'    => $videos,
            'playlists' => $playlists,
        ];
    }

    /** Vídeos de uma playlist (ou dos envios do canal), com números e duração. */
    public static function playlist(string $id, ?string $token, int $max = 24): array
    {
        $itens = self::get('playlistItems', ['part' => 'contentDetails', 'playlistId' => $id, 'maxResults' => min(50, $max)], $token)->json('items') ?? [];
        $ids = collect($itens)->pluck('contentDetails.videoId')->filter()->unique()->implode(',');
        if ($ids === '') {
            return [];
        }

        return collect(self::get('videos', ['part' => 'snippet,statistics,contentDetails,status', 'id' => $ids], $token)->json('items') ?? [])
            ->map(fn ($v) => self::video($v))->values()->all();
    }

    public static function video(array $v): array
    {
        return [
            'id'          => (string) $v['id'],
            'titulo'      => mb_substr((string) ($v['snippet']['title'] ?? ''), 0, 150),
            'descricao'   => mb_substr((string) ($v['snippet']['description'] ?? ''), 0, 1500),
            'publicado'   => substr((string) ($v['snippet']['publishedAt'] ?? ''), 0, 10),
            'thumb'       => self::thumb($v['snippet']['thumbnails'] ?? []),
            'duracao'     => self::duracao((string) ($v['contentDetails']['duration'] ?? '')),
            'privacidade' => $v['status']['privacyStatus'] ?? null,
            'views'       => (int) ($v['statistics']['viewCount'] ?? 0),
            'curtidas'    => (int) ($v['statistics']['likeCount'] ?? 0),
            'comentarios' => (int) ($v['statistics']['commentCount'] ?? 0),
        ];
    }

    /** "PT1H2M3S" → "1:02:03". */
    public static function duracao(string $iso): string
    {
        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return '';
        }
        $h = (int) ($m[1] ?? 0) * 24 + (int) ($m[2] ?? 0);
        $min = (int) ($m[3] ?? 0);
        $s = (int) ($m[4] ?? 0);

        return $h ? sprintf('%d:%02d:%02d', $h, $min, $s) : sprintf('%d:%02d', $min, $s);
    }

    private static function thumb(array $t): ?string
    {
        foreach (['medium', 'high', 'default'] as $k) {
            if (! empty($t[$k]['url'])) {
                return (string) $t[$k]['url'];
            }
        }

        return null;
    }

    /** GET na API com o token da pessoa ou a chave do site. $exigir=false devolve null em erro (dados opcionais). */
    private static function get(string $rota, array $q, ?string $token, bool $exigir = true): ?Response
    {
        $http = Http::timeout(15)->acceptJson();
        if ($token) {
            $http = $http->withToken($token);
        } else {
            $q['key'] = (string) config('services.youtube.key');
        }

        $r = $http->get(self::API . $rota, $q);
        if ($r->successful()) {
            return $r;
        }
        if (! $exigir) {
            return null;
        }

        $motivo = (string) $r->json('error.errors.0.reason');
        throw new RuntimeException(match (true) {
            $r->status() === 401           => 'O acesso ao YouTube venceu. Conecte de novo.',
            str_contains($motivo, 'quota') => 'O YouTube passou do limite diário de consultas deste site. Tente amanhã.',
            $r->status() === 403           => 'O YouTube recusou a consulta (' . ($motivo ?: 'sem permissão') . ').',
            $r->status() === 404           => 'Não encontrei isso no YouTube.',
            default                        => 'O YouTube não respondeu agora. Tente de novo.',
        });
    }
}
