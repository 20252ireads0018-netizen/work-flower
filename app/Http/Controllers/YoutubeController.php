<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Métricas públicas de um canal do YouTube para o cartão "Criador de conteúdo": inscritos, visualizações
 * e os últimos vídeos com visualizações, curtidas e comentários. Usa a YouTube Data API v3 com a chave do site
 * (YOUTUBE_API_KEY no .env); a chave nunca vai para o navegador. Resultado guardado por 30 minutos por canal.
 */
class YoutubeController extends Controller
{
    private const API = 'https://www.googleapis.com/youtube/v3/';

    public function canal(Request $request): JsonResponse
    {
        $chave = (string) config('services.youtube.key');
        if ($chave === '') {
            return response()->json(['ok' => false, 'erro' => 'O YouTube ainda não foi configurado neste site (falta a chave da API).'], 503);
        }
        $alvo = self::alvo((string) $request->query('canal'));
        if (! $alvo) {
            return response()->json(['ok' => false, 'erro' => 'Cole o @ do canal (ex.: @seucanal) ou o link dele.'], 422);
        }

        $dados = Cache::remember('yt.canal.' . md5(json_encode($alvo)), now()->addMinutes(30), function () use ($alvo, $chave) {
            $q = ['part' => 'snippet,statistics,contentDetails', 'key' => $chave] + $alvo;
            $c = Http::timeout(15)->get(self::API . 'channels', $q);
            if (! $c->successful()) return ['erro' => $c->status() === 403 ? 'A chave do YouTube foi recusada ou passou do limite diário.' : 'O YouTube não respondeu agora.'];
            $canal = $c->json('items.0');
            if (! $canal) return ['erro' => 'Canal não encontrado. Confira o @ ou o link.'];

            $videos = [];
            $uploads = $canal['contentDetails']['relatedPlaylists']['uploads'] ?? null;
            if ($uploads) {
                $ids = collect(Http::timeout(15)->get(self::API . 'playlistItems', ['part' => 'contentDetails', 'playlistId' => $uploads, 'maxResults' => 12, 'key' => $chave])->json('items') ?? [])
                    ->pluck('contentDetails.videoId')->filter()->implode(',');
                if ($ids !== '') {
                    $videos = collect(Http::timeout(15)->get(self::API . 'videos', ['part' => 'snippet,statistics', 'id' => $ids, 'key' => $chave])->json('items') ?? [])
                        ->map(fn ($v) => [
                            'id'          => $v['id'],
                            'titulo'      => mb_substr((string) ($v['snippet']['title'] ?? ''), 0, 120),
                            'publicado'   => substr((string) ($v['snippet']['publishedAt'] ?? ''), 0, 10),
                            'views'       => (int) ($v['statistics']['viewCount'] ?? 0),
                            'curtidas'    => (int) ($v['statistics']['likeCount'] ?? 0),
                            'comentarios' => (int) ($v['statistics']['commentCount'] ?? 0),
                        ])->values()->all();
                }
            }

            return [
                'canal' => [
                    'id'         => $canal['id'],
                    'nome'       => mb_substr((string) ($canal['snippet']['title'] ?? ''), 0, 100),
                    'handle'     => $canal['snippet']['customUrl'] ?? null,
                    'inscritos'  => ($canal['statistics']['hiddenSubscriberCount'] ?? false) ? null : (int) ($canal['statistics']['subscriberCount'] ?? 0),
                    'views'      => (int) ($canal['statistics']['viewCount'] ?? 0),
                    'videos'     => (int) ($canal['statistics']['videoCount'] ?? 0),
                ],
                'videos' => $videos,
                'em'     => now()->toIso8601String(),
            ];
        });

        if (isset($dados['erro'])) {
            Cache::forget('yt.canal.' . md5(json_encode($alvo))); // não guarda erro por 30 min

            return response()->json(['ok' => false, 'erro' => $dados['erro']], 422);
        }

        return response()->json(['ok' => true] + $dados);
    }

    /** "@canal", "youtube.com/@canal", "youtube.com/channel/UC..." ou o id UC... → parâmetro da API. */
    public static function alvo(string $t): ?array
    {
        $t = trim($t);
        if (preg_match('~(?:youtube\.com/)?channel/(UC[A-Za-z0-9_-]{22})~', $t, $m) || preg_match('~^(UC[A-Za-z0-9_-]{22})$~', $t, $m)) return ['id' => $m[1]];
        if (preg_match('~(?:youtube\.com/)?(@[A-Za-z0-9._-]{3,100})~', $t, $m) || preg_match('~^([A-Za-z0-9._-]{3,100})$~', $t, $m)) {
            return ['forHandle' => str_starts_with($m[1], '@') ? $m[1] : '@' . $m[1]];
        }

        return null;
    }
}
