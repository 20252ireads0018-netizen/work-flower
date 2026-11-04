<?php

namespace App\Http\Controllers;

use App\Models\RedeConta;
use Illuminate\Http\Client\Response as RespostaHttp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Métricas do Instagram e do TikTok para o cartão "Criador de conteúdo" (o YouTube fica no YoutubeController).
 * Cada pessoa conecta a própria conta por OAuth:
 *  - Instagram: Login do Facebook + Instagram Graph API (conta Profissional, Business ou Criador, ligada a uma Página).
 *    O token curto vira um token longo (~60 dias), renovado sozinho quando faltam menos de 7 dias.
 *  - TikTok: Login Kit + Display API. O token vale 24 h e é renovado com o refresh_token (vale 1 ano).
 * As chaves dos apps ficam no .env (META_APP_ID/SECRET, TIKTOK_CLIENT_KEY/SECRET); os tokens, criptografados no banco.
 */
class RedesController extends Controller
{
    public const REDES = ['instagram' => 'Instagram', 'tiktok' => 'TikTok'];

    private const IG_ESCOPOS = 'instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement,business_management';
    private const TT_ESCOPOS = 'user.info.basic,user.info.profile,user.info.stats,video.list';
    private const TT_API = 'https://open.tiktokapis.com/v2/';

    /** O que falta no .env para a rede funcionar (vazio = configurada). */
    public static function faltando(string $rede): array
    {
        $f = [];
        if ($rede === 'instagram') {
            if (! config('services.meta.id')) $f[] = 'META_APP_ID';
            if (! config('services.meta.secret')) $f[] = 'META_APP_SECRET';
        } else {
            if (! config('services.tiktok.key')) $f[] = 'TIKTOK_CLIENT_KEY';
            if (! config('services.tiktok.secret')) $f[] = 'TIKTOK_CLIENT_SECRET';
        }

        return $f;
    }

    private static function graph(): string
    {
        return 'https://graph.facebook.com/' . config('services.meta.versao', 'v21.0') . '/';
    }

    /** GET /redes/{rede}/conectar: manda a pessoa para a tela de autorização da rede. */
    public function conectar(Request $request, string $rede): RedirectResponse
    {
        $voltar = $this->voltar($request);
        if ($f = self::faltando($rede)) {
            return redirect($voltar)->with('ok', self::REDES[$rede] . ' ainda não foi configurado neste site (falta ' . implode(' e ', $f) . ' no .env).');
        }

        $state = Str::random(40);
        $request->session()->put("redes_state.{$state}", ['rede' => $rede, 'user' => $request->user()->id, 'em' => now()->timestamp, 'voltar' => $voltar]);
        $retorno = route('redes.retorno', $rede);

        $url = $rede === 'instagram'
            ? 'https://www.facebook.com/' . config('services.meta.versao', 'v21.0') . '/dialog/oauth?' . http_build_query([
                'client_id' => config('services.meta.id'), 'redirect_uri' => $retorno, 'state' => $state,
                'scope' => self::IG_ESCOPOS, 'response_type' => 'code',
            ])
            : 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                'client_key' => config('services.tiktok.key'), 'redirect_uri' => $retorno, 'state' => $state,
                'scope' => self::TT_ESCOPOS, 'response_type' => 'code',
            ]);

        return redirect()->away($url);
    }

    /** GET /redes/{rede}/retorno: a rede devolve com o código; troca pelo token e guarda a conta. */
    public function retorno(Request $request, string $rede): RedirectResponse
    {
        $state = (string) $request->query('state');
        $pedido = $state !== '' ? $request->session()->pull("redes_state.{$state}") : null;
        $nome = self::REDES[$rede];

        if (! $pedido || $pedido['rede'] !== $rede || (int) $pedido['user'] !== $request->user()->id || now()->timestamp - $pedido['em'] > 1800) {
            return redirect()->route('painel')->with('ok', "A autorização do {$nome} expirou ou não veio deste navegador. Tente conectar de novo.");
        }
        $voltar = $pedido['voltar'];
        if ($request->filled('error') || $request->filled('error_code') || ! $request->filled('code')) {
            return redirect($voltar)->with('ok', "A conexão com o {$nome} foi cancelada.");
        }
        if ($f = self::faltando($rede)) {
            return redirect($voltar)->with('ok', "{$nome} ainda não foi configurado neste site (falta " . implode(' e ', $f) . ' no .env).');
        }

        try {
            $dados = $rede === 'instagram' ? $this->igTrocar((string) $request->query('code')) : $this->ttTrocar((string) $request->query('code'));
        } catch (\RuntimeException $e) {
            return redirect($voltar)->with('ok', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect($voltar)->with('ok', "Não consegui concluir a conexão com o {$nome}. Tente de novo.");
        }

        $conta = RedeConta::updateOrCreate(['user_id' => $request->user()->id, 'rede' => $rede], $dados + ['erro' => null]);
        Cache::forget('redes.metricas.' . $conta->id);

        return redirect($voltar)->with('ok', "{$nome} conectado" . ($conta->usuario ? ' (@' . $conta->usuario . ')' : '') . '. Abra o cartão Criador para ver as métricas.');
    }

    /** GET /redes/{rede}/metricas: seguidores e últimos posts/vídeos da conta conectada (guardado por 15 minutos). */
    public function metricas(Request $request, string $rede): JsonResponse
    {
        $nome = self::REDES[$rede];
        if ($f = self::faltando($rede)) {
            return response()->json(['ok' => false, 'configurado' => false, 'erro' => "O {$nome} ainda não foi configurado neste site (falta " . implode(' e ', $f) . ' no .env).'], 503);
        }
        $conta = RedeConta::where(['user_id' => $request->user()->id, 'rede' => $rede])->first();
        if (! $conta) {
            return response()->json(['ok' => false, 'conectado' => false, 'conectar' => route('redes.conectar', $rede), 'erro' => "Conecte sua conta do {$nome} para ver as métricas."], 409);
        }

        try {
            $this->renovar($conta);
        } catch (\RuntimeException $e) {
            $conta->update(['erro' => $e->getMessage()]);

            return response()->json(['ok' => false, 'conectado' => false, 'conectar' => route('redes.conectar', $rede), 'erro' => $e->getMessage()], 409);
        }

        $chave = 'redes.metricas.' . $conta->id;
        if ($request->boolean('forcar')) Cache::forget($chave);
        try {
            $dados = Cache::remember($chave, now()->addMinutes(15), fn () => $rede === 'instagram' ? $this->igMetricas($conta) : $this->ttMetricas($conta));
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'erro' => "O {$nome} não respondeu agora. Tente de novo em alguns minutos."], 502);
        }
        if ($conta->erro) $conta->update(['erro' => null]);

        return response()->json(['ok' => true, 'rede' => $rede] + $dados);
    }

    /** DELETE /redes/{rede}: desconecta (apaga os tokens). */
    public function sair(Request $request, string $rede): JsonResponse
    {
        $conta = RedeConta::where(['user_id' => $request->user()->id, 'rede' => $rede])->first();
        if ($conta) {
            Cache::forget('redes.metricas.' . $conta->id);
            if ($rede === 'tiktok' && config('services.tiktok.key')) {
                rescue(fn () => Http::asForm()->timeout(10)->post(self::TT_API . 'oauth/revoke/', [
                    'client_key' => config('services.tiktok.key'), 'client_secret' => config('services.tiktok.secret'), 'token' => $conta->access_token,
                ]), report: false);
            }
            $conta->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Renova o token antes de vencer. Lança RuntimeException quando não dá mais (a pessoa precisa conectar de novo). */
    public function renovar(RedeConta $conta): void
    {
        $nome = self::REDES[$conta->rede];
        if ($conta->rede === 'instagram') {
            if ($conta->expira_em && $conta->expira_em->isPast()) {
                throw new \RuntimeException("A conexão com o {$nome} venceu. Conecte de novo.");
            }
            if ($conta->expira_em && $conta->expira_em->gt(now()->addDays(7))) return;
            $r = Http::timeout(15)->get(self::graph() . 'oauth/access_token', [
                'grant_type' => 'fb_exchange_token', 'client_id' => config('services.meta.id'),
                'client_secret' => config('services.meta.secret'), 'fb_exchange_token' => $conta->access_token,
            ]);
            if (! $r->successful() || ! $r->json('access_token')) {
                if ($conta->expira_em) return; // ainda vale: tenta de novo na próxima leitura
                throw new \RuntimeException("O {$nome} recusou a conexão. Conecte de novo.");
            }
            $conta->update(['access_token' => $r->json('access_token'), 'expira_em' => now()->addSeconds((int) ($r->json('expires_in') ?: 5184000))]);

            return;
        }

        // TikTok
        if ($conta->expira_em && $conta->expira_em->gt(now()->addMinutes(5))) return;
        if (! $conta->refresh_token || ($conta->refresh_expira_em && $conta->refresh_expira_em->isPast())) {
            throw new \RuntimeException("A conexão com o {$nome} venceu. Conecte de novo.");
        }
        $r = Http::asForm()->timeout(15)->post(self::TT_API . 'oauth/token/', [
            'client_key' => config('services.tiktok.key'), 'client_secret' => config('services.tiktok.secret'),
            'grant_type' => 'refresh_token', 'refresh_token' => $conta->refresh_token,
        ]);
        if (! $r->successful() || ! $r->json('access_token')) {
            throw new \RuntimeException("O {$nome} recusou a renovação da conexão. Conecte de novo.");
        }
        $conta->update($this->ttTokens($r));
    }

    /* ---------------- Instagram ---------------- */

    private function igTrocar(string $code): array
    {
        $r = Http::timeout(15)->get(self::graph() . 'oauth/access_token', [
            'client_id' => config('services.meta.id'), 'client_secret' => config('services.meta.secret'),
            'redirect_uri' => route('redes.retorno', 'instagram'), 'code' => $code,
        ]);
        if (! $r->successful() || ! $r->json('access_token')) throw new \RuntimeException('O Facebook recusou a autorização. Tente conectar de novo.');

        // token curto (1-2 h) → token longo (~60 dias)
        $l = Http::timeout(15)->get(self::graph() . 'oauth/access_token', [
            'grant_type' => 'fb_exchange_token', 'client_id' => config('services.meta.id'),
            'client_secret' => config('services.meta.secret'), 'fb_exchange_token' => $r->json('access_token'),
        ]);
        $token = $l->successful() && $l->json('access_token') ? $l->json('access_token') : $r->json('access_token');
        $expira = now()->addSeconds((int) (($l->successful() ? $l->json('expires_in') : $r->json('expires_in')) ?: 5184000));

        $paginas = Http::timeout(15)->get(self::graph() . 'me/accounts', [
            'fields' => 'name,instagram_business_account{id,username,name,profile_picture_url}', 'limit' => 50, 'access_token' => $token,
        ]);
        $pagina = collect($paginas->json('data') ?? [])->first(fn ($p) => ! empty($p['instagram_business_account']['id']));
        if (! $pagina) {
            throw new \RuntimeException('Não achei uma conta do Instagram Profissional (Comercial ou Criador de conteúdo) ligada a uma Página do Facebook sua. Mude a conta para Profissional, ligue-a a uma Página e conecte de novo marcando a Página.');
        }
        $ig = $pagina['instagram_business_account'];

        return [
            'externo_id' => (string) $ig['id'], 'usuario' => $ig['username'] ?? null, 'nome' => $ig['name'] ?? ($pagina['name'] ?? null),
            'access_token' => $token, 'refresh_token' => null, 'expira_em' => $expira,
            'extra' => ['pagina' => $pagina['name'] ?? null, 'pagina_id' => $pagina['id'] ?? null],
        ];
    }

    private function igMetricas(RedeConta $conta): array
    {
        $t = $conta->access_token;
        $p = Http::timeout(15)->get(self::graph() . $conta->externo_id, [
            'fields' => 'username,name,followers_count,follows_count,media_count,profile_picture_url', 'access_token' => $t,
        ]);
        if (! $p->successful()) throw new \RuntimeException($this->erroMeta($p));

        $midias = Http::timeout(15)->get(self::graph() . $conta->externo_id . '/media', [
            'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count', 'limit' => 12, 'access_token' => $t,
        ])->json('data') ?? [];

        $posts = collect($midias)->map(function ($m) use ($t) {
            // alcance e visualizações (nem todo tipo de post tem: o que não vier fica em branco)
            $ins = [];
            foreach (['reach,views', 'reach'] as $metricas) {
                $r = Http::timeout(10)->get(self::graph() . $m['id'] . '/insights', ['metric' => $metricas, 'access_token' => $t]);
                if ($r->successful()) {
                    foreach ($r->json('data') ?? [] as $d) $ins[$d['name']] = (int) ($d['values'][0]['value'] ?? $d['total_value']['value'] ?? 0);
                    break;
                }
            }

            return [
                'id'          => (string) $m['id'],
                'legenda'     => mb_substr((string) ($m['caption'] ?? ''), 0, 120),
                'tipo'        => ($m['media_product_type'] ?? '') === 'REELS' ? 'Reels'
                    : (['IMAGE' => 'Foto', 'VIDEO' => 'Vídeo', 'CAROUSEL_ALBUM' => 'Carrossel'][$m['media_type'] ?? ''] ?? 'Post'),
                'link'        => $m['permalink'] ?? null,
                'data'        => substr((string) ($m['timestamp'] ?? ''), 0, 10),
                'curtidas'    => (int) ($m['like_count'] ?? 0),
                'comentarios' => (int) ($m['comments_count'] ?? 0),
                'alcance'     => $ins['reach'] ?? null,
                'views'       => $ins['views'] ?? ($ins['impressions'] ?? null),
            ];
        })->values()->all();

        $conta->update(['usuario' => $p->json('username') ?: $conta->usuario, 'nome' => $p->json('name') ?: $conta->nome]);

        return [
            'conta' => [
                'usuario'    => $p->json('username'),
                'nome'       => mb_substr((string) $p->json('name'), 0, 100),
                'seguidores' => (int) $p->json('followers_count'),
                'seguindo'   => (int) $p->json('follows_count'),
                'posts'      => (int) $p->json('media_count'),
                'foto'       => $p->json('profile_picture_url'),
            ],
            'posts' => $posts,
            'em'    => now()->toIso8601String(),
        ];
    }

    private function erroMeta(RespostaHttp $r): string
    {
        $codigo = (int) $r->json('error.code');
        if ($codigo === 190) return 'A conexão com o Instagram venceu ou foi retirada. Conecte de novo.';
        if (in_array($codigo, [4, 17, 32, 613], true)) return 'O Instagram limitou as leituras por agora. Tente de novo mais tarde.';

        return 'O Instagram recusou a leitura (' . mb_substr((string) ($r->json('error.message') ?: 'erro ' . $r->status()), 0, 160) . ').';
    }

    /* ---------------- TikTok ---------------- */

    private function ttTrocar(string $code): array
    {
        $r = Http::asForm()->timeout(15)->post(self::TT_API . 'oauth/token/', [
            'client_key' => config('services.tiktok.key'), 'client_secret' => config('services.tiktok.secret'),
            'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => route('redes.retorno', 'tiktok'),
        ]);
        if (! $r->successful() || ! $r->json('access_token')) throw new \RuntimeException('O TikTok recusou a autorização. Tente conectar de novo.');

        $dados = $this->ttTokens($r) + ['externo_id' => (string) $r->json('open_id')];
        $u = Http::withToken($dados['access_token'])->timeout(15)->get(self::TT_API . 'user/info/', ['fields' => 'open_id,display_name,username']);
        $dados['usuario'] = $u->json('data.user.username');
        $dados['nome'] = $u->json('data.user.display_name');

        return $dados;
    }

    private function ttTokens(RespostaHttp $r): array
    {
        return [
            'access_token'      => $r->json('access_token'),
            'refresh_token'     => $r->json('refresh_token'),
            'expira_em'         => now()->addSeconds((int) ($r->json('expires_in') ?: 86400)),
            'refresh_expira_em' => now()->addSeconds((int) ($r->json('refresh_expires_in') ?: 31536000)),
        ];
    }

    private function ttMetricas(RedeConta $conta): array
    {
        $h = Http::withToken($conta->access_token)->timeout(15);
        $u = $h->get(self::TT_API . 'user/info/', ['fields' => 'open_id,display_name,username,avatar_url,follower_count,following_count,likes_count,video_count']);
        if (! $u->successful() || ($u->json('error.code') && $u->json('error.code') !== 'ok')) {
            $cod = (string) $u->json('error.code');
            throw new \RuntimeException($cod === 'access_token_invalid' ? 'A conexão com o TikTok venceu ou foi retirada. Conecte de novo.'
                : ($cod === 'scope_not_authorized' ? 'Faltou autorizar as estatísticas no TikTok. Conecte de novo e marque tudo.' : 'O TikTok recusou a leitura agora.'));
        }
        $v = Http::withToken($conta->access_token)->timeout(15)->asJson()
            ->post(self::TT_API . 'video/list/?fields=id,title,video_description,create_time,share_url,view_count,like_count,comment_count,share_count', ['max_count' => 20]);

        $videos = collect($v->json('data.videos') ?? [])->map(fn ($x) => [
            'id'          => (string) $x['id'],
            'titulo'      => mb_substr((string) (($x['title'] ?? '') ?: ($x['video_description'] ?? '')), 0, 120),
            'link'        => $x['share_url'] ?? null,
            'data'        => isset($x['create_time']) ? date('Y-m-d', (int) $x['create_time']) : '',
            'views'       => (int) ($x['view_count'] ?? 0),
            'curtidas'    => (int) ($x['like_count'] ?? 0),
            'comentarios' => (int) ($x['comment_count'] ?? 0),
            'compart'     => (int) ($x['share_count'] ?? 0),
        ])->values()->all();

        $user = $u->json('data.user') ?? [];
        $conta->update(['usuario' => $user['username'] ?? $conta->usuario, 'nome' => $user['display_name'] ?? $conta->nome]);

        return [
            'conta' => [
                'usuario'    => $user['username'] ?? null,
                'nome'       => mb_substr((string) ($user['display_name'] ?? ''), 0, 100),
                'seguidores' => (int) ($user['follower_count'] ?? 0),
                'seguindo'   => (int) ($user['following_count'] ?? 0),
                'curtidas'   => (int) ($user['likes_count'] ?? 0),
                'videos'     => (int) ($user['video_count'] ?? 0),
                'foto'       => $user['avatar_url'] ?? null,
            ],
            'videos' => $videos,
            'em'     => now()->toIso8601String(),
        ];
    }

    /** Para onde voltar depois da autorização: a página de onde a pessoa veio (só deste site). */
    private function voltar(Request $request): string
    {
        $v = (string) ($request->query('voltar') ?: url()->previous());
        $base = url('/');

        return str_starts_with($v, $base) ? $v : route('painel');
    }
}
