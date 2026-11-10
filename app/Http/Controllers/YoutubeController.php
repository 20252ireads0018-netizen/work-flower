<?php

namespace App\Http\Controllers;

use App\Models\GoogleConexao;
use App\Support\Google\Youtube;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

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

    /* ------------------------------------------------------------------
     * Cartão "YouTube" do quadro (public/js/integracoes-cartoes.js): o canal da própria pessoa pelo login do Google
     * (vídeos, inclusive não listados, e playlists) ou um canal público pelo @, com a chave do site.
     * ------------------------------------------------------------------ */

    /** GET /youtube/cartao[?canal=@x][&playlist=PL..][&forcar=1] */
    public function cartao(Request $request): JsonResponse
    {
        $u = $request->user();
        $con = GoogleConexao::where('user_id', $u->id)->where('servico', 'youtube')->first();
        $base = [
            'conectado'     => (bool) $con,
            'email'         => $con?->email,
            'pode_conectar' => Youtube::configurado(),
            'tem_chave'     => Youtube::temChave(),
            'conectar'      => Youtube::configurado() ? route('youtube.google') : null,
            // edição do canal (escopo youtube): pedida só quando a pessoa clica em "Permitir editar"
            'pode_editar'     => Youtube::podeEditar($con),
            'permitir_editar' => $con && Youtube::configurado() ? route('youtube.google', ['editar' => 1]) : null,
        ];

        $publico = trim((string) $request->query('canal', ''));
        $playlist = (string) $request->query('playlist', '');
        if ($playlist !== '' && ! preg_match('/^[A-Za-z0-9_-]{10,64}$/', $playlist)) {
            return response()->json(['ok' => false, 'erro' => 'Playlist inválida.'] + $base, 422);
        }

        try {
            if ($publico !== '') {
                $alvo = self::alvo($publico);
                if (! $alvo) {
                    return response()->json(['ok' => false, 'erro' => 'Cole o @ do canal (ex.: @seucanal) ou o link dele.'] + $base, 422);
                }
                if (! Youtube::temChave() && ! $con) {
                    return response()->json(['ok' => false, 'erro' => 'Entre com a conta Google para ver canais do YouTube por aqui.'] + $base, 409);
                }
                $token = $con ? Youtube::token($con) : null;
                $chave = 'yt.cartao.' . md5(json_encode([$alvo, $playlist]));
            } else {
                if (! $con) {
                    return response()->json(['ok' => true] + $base);
                }
                $token = Youtube::token($con);
                $alvo = ['mine' => 'true'];
                $chave = 'yt.meu.' . $u->id . '.' . md5($playlist);
            }
            if ($request->boolean('forcar')) {
                Cache::forget($chave);
            }

            $dados = Cache::remember($chave, now()->addMinutes(10), function () use ($alvo, $token, $playlist) {
                if ($playlist !== '') {
                    return ['videos' => Youtube::playlist($playlist, $token, 50), 'playlist' => $playlist];
                }

                return Youtube::canal($alvo, $token);
            });
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()] + $base, 422);
        }

        if ($con && $publico === '' && $playlist === '' && isset($dados['canal'])) {
            $con->forceFill(['dados' => array_merge((array) $con->dados, ['canal' => $dados['canal']['id'], 'nome' => $dados['canal']['nome']])])->save();
        }

        return response()->json(['ok' => true] + $base + $dados + ['em' => now()->toIso8601String()]);
    }

    /** GET /youtube/google[?editar=1]: vai para o login do Google (youtube.readonly; com editar=1, também o escopo youtube). */
    public function conectar(Request $request): RedirectResponse
    {
        if (! Youtube::configurado()) {
            return redirect()->route('painel')->with('erro', 'O login do Google ainda não foi configurado neste Work Flower.');
        }
        $editar = $request->boolean('editar');
        $con = $editar ? GoogleConexao::where('user_id', $request->user()->id)->where('servico', 'youtube')->first() : null;
        $estado = Str::random(40);
        $request->session()->put('youtube.estado', $estado);
        $request->session()->put('youtube.editar', $editar);
        $request->session()->put('youtube.voltar', self::voltar($request));

        return redirect()->away(Youtube::urlAutorizacao($estado, $editar, $con?->email));
    }

    /** GET /youtube/google/retorno (direto ou pela página de repasse docs/google/youtube). */
    public function retorno(Request $request): RedirectResponse
    {
        $voltar = (string) ($request->session()->pull('youtube.voltar') ?: route('painel'));
        $esperado = (string) $request->session()->pull('youtube.estado');
        if ($esperado === '' || ! hash_equals($esperado, (string) $request->query('state'))) {
            return redirect($voltar)->with('erro', 'A conexão com o YouTube expirou ou não veio deste navegador. Tente de novo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect($voltar)->with('erro', 'A conexão com o YouTube foi cancelada no Google.');
        }

        try {
            $t = Youtube::trocarCodigo((string) $request->query('code'));
        } catch (RuntimeException $e) {
            return redirect($voltar)->with('erro', $e->getMessage());
        }

        $con = GoogleConexao::firstOrNew(['user_id' => $request->user()->id, 'servico' => 'youtube']);
        $con->forceFill([
            'email'         => $t['email'],
            'access_token'  => $t['access_token'],
            'refresh_token' => $t['refresh_token'] ?: $con->refresh_token,
            'expira_em'     => now()->addSeconds($t['expira']),
            'dados'         => array_merge((array) $con->dados, ['escopos' => $t['escopos']]),
        ])->save();
        Cache::forget('yt.meu.' . $request->user()->id . '.' . md5(''));

        if ($request->session()->pull('youtube.editar')) {
            // o Google deixa a pessoa desmarcar a edição na tela de consentimento
            return Youtube::podeEditar($con)
                ? redirect($voltar)->with('ok', 'Edição liberada: já dá para criar playlists e mudar título, descrição e privacidade dos seus vídeos pelo cartão YouTube.')
                : redirect($voltar)->with('erro', 'O Google não liberou a edição do canal (a permissão ficou desmarcada). Tente de novo e marque a permissão.');
        }

        return redirect($voltar)->with('ok', 'YouTube conectado' . ($t['email'] ? " ({$t['email']})" : '') . '. Seus vídeos aparecem no cartão YouTube.');
    }

    /** DELETE /youtube/google: tira a permissão no Google e apaga os tokens. */
    public function desconectar(Request $request): JsonResponse
    {
        $con = GoogleConexao::where('user_id', $request->user()->id)->where('servico', 'youtube')->first();
        if ($con) {
            Youtube::revogar($con);
            $con->delete();
        }
        Cache::forget('yt.meu.' . $request->user()->id . '.' . md5(''));

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     * Editar o próprio canal pelo cartão (escopo youtube, opcional). Sem a permissão, 409 com "permitir".
     * ------------------------------------------------------------------ */

    /** GET /youtube/playlists: playlists da pessoa (inclusive privadas), para pôr um vídeo nelas. */
    public function playlists(Request $request): JsonResponse
    {
        return $this->comCanal($request, false, fn (string $token) => ['playlists' => Youtube::minhasPlaylists($token)]);
    }

    /** POST /youtube/playlists { titulo, descricao?, privacidade } */
    public function criarPlaylist(Request $request): JsonResponse
    {
        $d = $this->validarB64($request, [
            'titulo'      => ['required', 'string', 'max:150'],
            'descricao'   => ['nullable', 'string', 'max:5000'],
            'privacidade' => ['required', 'in:' . implode(',', Youtube::PRIVACIDADES)],
        ], ['titulo.required' => 'Dê um nome à playlist.']);

        return $this->comCanal($request, true, function (string $token) use ($d, $request) {
            $p = Youtube::criarPlaylist($token, trim($d['titulo']), (string) ($d['descricao'] ?? ''), $d['privacidade']);
            Cache::forget('yt.meu.' . $request->user()->id . '.' . md5(''));

            return ['playlist' => $p, 'msg' => 'Playlist “' . trim($d['titulo']) . '” criada no seu canal.'];
        });
    }

    /** POST /youtube/playlists/{playlist}/videos { video } */
    public function adicionarVideo(Request $request, string $playlist): JsonResponse
    {
        $d = $request->validate(['video' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{11}$/']], ['video.*' => 'Vídeo inválido.']);

        return $this->comCanal($request, true, function (string $token) use ($playlist, $d, $request) {
            Youtube::adicionarVideo($token, $playlist, $d['video']);
            Cache::forget('yt.meu.' . $request->user()->id . '.' . md5($playlist));

            return ['msg' => 'Vídeo adicionado à playlist.'];
        });
    }

    /** DELETE /youtube/playlists/{playlist}/videos/{video} */
    public function removerVideo(Request $request, string $playlist, string $video): JsonResponse
    {
        return $this->comCanal($request, true, function (string $token) use ($playlist, $video, $request) {
            $n = Youtube::removerVideo($token, $playlist, $video);
            Cache::forget('yt.meu.' . $request->user()->id . '.' . md5($playlist));

            return ['removidos' => $n, 'msg' => $n ? 'Vídeo tirado da playlist.' : 'Esse vídeo já não estava na playlist.'];
        });
    }

    /** PATCH /youtube/videos/{video} { titulo?, descricao?, privacidade? }: só vídeos do próprio canal (o YouTube confere). */
    public function editarVideo(Request $request, string $video): JsonResponse
    {
        $d = $this->validarB64($request, [
            'titulo'      => ['sometimes', 'required', 'string', 'max:100'],
            'descricao'   => ['sometimes', 'nullable', 'string', 'max:5000'],
            'privacidade' => ['sometimes', 'required', 'in:' . implode(',', Youtube::PRIVACIDADES)],
        ], ['titulo.required' => 'O vídeo precisa de um título.', 'titulo.max' => 'Título comprido demais (o YouTube aceita até 100 caracteres).']);
        foreach (['titulo', 'descricao'] as $k) {
            if (isset($d[$k]) && preg_match('/[<>]/', (string) $d[$k])) {
                return response()->json(['ok' => false, 'erro' => 'O YouTube não aceita < nem > no título e na descrição.'], 422);
            }
        }

        return $this->comCanal($request, true, function (string $token) use ($video, $d, $request) {
            $v = Youtube::editarVideo($token, $video, isset($d['titulo']) ? trim($d['titulo']) : null,
                array_key_exists('descricao', $d) ? (string) $d['descricao'] : null, $d['privacidade'] ?? null);
            Cache::forget('yt.meu.' . $request->user()->id . '.' . md5(''));

            return ['video' => $v, 'msg' => 'Vídeo atualizado no YouTube.'];
        });
    }

    /** Roda $fazer com o token da conexão; $editar exige o escopo youtube. Erros viram JSON com "erro". */
    private function comCanal(Request $request, bool $editar, \Closure $fazer): JsonResponse
    {
        $con = GoogleConexao::where('user_id', $request->user()->id)->where('servico', 'youtube')->first();
        if (! $con) {
            return response()->json(['ok' => false, 'erro' => 'Entre com a conta Google no cartão YouTube primeiro.'], 409);
        }
        $permitir = Youtube::configurado() ? route('youtube.google', ['editar' => 1]) : null;
        if ($editar && ! Youtube::podeEditar($con)) {
            return response()->json(['ok' => false, 'erro' => 'Falta permitir que o Work Flower edite o seu canal.', 'permitir' => $permitir], 409);
        }
        try {
            return response()->json(['ok' => true] + $fazer(Youtube::token($con)));
        } catch (\App\Support\Google\SemPermissao $e) {
            // a pessoa tirou a permissão na conta Google: some o "pode editar" até permitir de novo
            $escopos = array_diff(preg_split('/\s+/', (string) ($con->dados['escopos'] ?? '')) ?: [], [Youtube::ESCOPO_EDITAR]);
            $con->forceFill(['dados' => array_merge((array) $con->dados, ['escopos' => implode(' ', $escopos)])])->save();

            return response()->json(['ok' => false, 'erro' => $e->getMessage(), 'permitir' => $permitir], 409);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    /** Valida o corpo, que pode vir como {"b64": base64(JSON)} (o mod_security da HostGator barra descrições com cara de código). */
    private function validarB64(Request $request, array $regras, array $msgs = []): array
    {
        $b64 = $request->input('b64');
        if (is_string($b64)) {
            $dados = json_decode((string) base64_decode($b64, true), true);
            abort_unless(is_array($dados), 422, 'Corpo inválido.');
            $request->request->remove('b64');
            $request->json()->remove('b64');
            $request->merge($dados);
        }

        return $request->validate($regras, $msgs);
    }

    /** Página para onde voltar depois do Google: só endereços deste site (de onde a pessoa veio). */
    public static function voltar(Request $request): string
    {
        $prev = (string) url()->previous();
        $raiz = rtrim(url('/'), '/');

        return $prev !== '' && str_starts_with($prev, $raiz . '/') && ! preg_match('#/(youtube|armazenamento|negocios/emails)/google#', $prev)
            ? $prev : route('painel');
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
