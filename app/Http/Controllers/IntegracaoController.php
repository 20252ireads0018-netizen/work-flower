<?php

namespace App\Http\Controllers;

use App\Models\DiscordIntegracao;
use App\Support\Discord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Negócios › Integrações: liga a conta a um servidor do Discord (webhook colado pela pessoa e, se quiser, o token
 * de um bot para listar canais). Webhook e token nunca voltam para o navegador. Tela em
 * resources/views/areas/negocios/partials/integracoes.blade.php (public/js/integracoes.js).
 */
class IntegracaoController extends Controller
{
    use Concerns\CorpoCodificado;

    private function minha(Request $request): ?DiscordIntegracao
    {
        return DiscordIntegracao::where('user_id', $request->user()->id)->first();
    }

    private function resumo(?DiscordIntegracao $i): array
    {
        return [
            'conectado'   => (bool) $i?->webhook_url,
            'tem_bot'     => (bool) $i?->bot_token,
            'servidor'    => $i?->servidor_nome,
            'canal'       => $i?->canal_nome,
            'eventos'     => ($i ?? new DiscordIntegracao())->eventosTodos(),
            'nomes'       => DiscordIntegracao::EVENTOS,
            'ultimo_erro' => $i?->ultimo_erro,
            'ultimo_envio' => $i?->ultimo_envio_em?->toIso8601String(),
            // conectar com um clique pelo Discord (app do site configurado); sem isso, colar o webhook
            'oauth'       => Discord::oauthConfigurado(),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->resumo($this->minha($request)));
    }

    /** Leva ao Discord, que pede só "escolha o servidor e o canal". Volta para a página de onde a pessoa veio. */
    public function conectar(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless(Discord::oauthConfigurado(), 404);
        $estado = \Illuminate\Support\Str::random(40);
        // só volta para uma página deste site (nunca para um endereço de fora)
        $voltar = (string) url()->previous();
        if (! str_starts_with($voltar, rtrim(url('/'), '/') . '/') || str_contains($voltar, '/integracoes/discord')) {
            $voltar = route('areas.negocios');
        }
        $request->session()->put('discord_state', ['v' => $estado, 't' => now()->timestamp, 'voltar' => $voltar]);

        return redirect()->away(Discord::urlAutorizacao($estado));
    }

    /** Retorno do Discord (?code=&state=, ou ?error=access_denied): guarda o webhook e manda a mensagem de boas-vindas. */
    public function retorno(Request $request): \Illuminate\Http\RedirectResponse
    {
        $guardado = $request->session()->pull('discord_state');
        $destino = is_array($guardado) && is_string($guardado['voltar'] ?? null) ? $guardado['voltar'] : route('areas.negocios');
        $voltar = fn (string $msg, bool $ok = false) => redirect()->to($destino)->with($ok ? 'discord_ok' : 'discord_erro', $msg);
        $estado = (string) $request->query('state');
        if (! is_array($guardado) || $estado === '' || ! hash_equals((string) ($guardado['v'] ?? ''), $estado)
            || now()->timestamp - (int) ($guardado['t'] ?? 0) > 1800) {
            return $voltar('A autorização expirou ou não veio deste navegador. Tente conectar o Discord de novo.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $voltar('A conexão foi cancelada no Discord.');
        }
        try {
            $w = Discord::trocarCodigo((string) $request->query('code'));
        } catch (\RuntimeException $e) {
            return $voltar($e->getMessage());
        }

        $i = $this->minha($request) ?? new DiscordIntegracao();
        $i->user_id = $request->user()->id;
        // canal novo: os nomes guardados pelo bot (se houver) eram do canal antigo
        $i->fill($w + ['canal_nome' => null]);
        $i->ultimo_erro = null;
        $i->save();
        $ok = Discord::enviar($i, '✅ Work Flower conectado', 'Os avisos de ' . $request->user()->name . ' vão chegar neste canal.', route('areas.negocios'));

        return $voltar($ok ? 'Discord conectado! Mandamos uma mensagem de teste no canal escolhido.' : 'Discord conectado, mas a mensagem de teste falhou: ' . $i->ultimo_erro, $ok);
    }

    public function salvar(Request $request): JsonResponse
    {
        $this->decodificarCorpo($request);
        $d = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:300'],
            'bot_token'   => ['nullable', 'string', 'max:200'],
            'tirar_bot'   => ['boolean'],
            'eventos'     => ['nullable', 'array'],
            'eventos.*'   => ['boolean'],
        ]);
        $i = $this->minha($request) ?? new DiscordIntegracao();
        $i->user_id = $request->user()->id;

        $url = trim((string) ($d['webhook_url'] ?? ''));
        if ($url !== '') {
            if (! Discord::webhookValido($url)) {
                throw ValidationException::withMessages(['webhook_url' => 'Cole o endereço do webhook como o Discord mostra (começa com https://discord.com/api/webhooks/).']);
            }
            $info = Discord::infoWebhook($url);
            if (! $info) {
                throw ValidationException::withMessages(['webhook_url' => 'O Discord não reconheceu este webhook. Confira se ele não foi apagado e copie de novo.']);
            }
            $i->fill(['webhook_url' => $url, 'guild_id' => $info['guild_id'] ?: null, 'canal_id' => $info['canal_id'] ?: null]);
            $i->ultimo_erro = null;
        } elseif (! $i->webhook_url) {
            throw ValidationException::withMessages(['webhook_url' => 'Cole o webhook do canal do Discord.']);
        }

        if ($request->boolean('tirar_bot')) {
            $i->fill(['bot_token' => null, 'servidor_nome' => null, 'canal_nome' => null]);
        } elseif ($tok = trim((string) ($d['bot_token'] ?? ''))) {
            if (! preg_match('/^[\w.-]{50,120}$/', $tok)) {
                throw ValidationException::withMessages(['bot_token' => 'Esse não parece um token de bot do Discord.']);
            }
            $i->bot_token = $tok;
        }

        if (isset($d['eventos'])) {
            $ev = [];
            foreach (array_keys(DiscordIntegracao::EVENTOS) as $k) {
                $ev[$k] = (bool) ($d['eventos'][$k] ?? $i->avisa($k));
            }
            $i->eventos = $ev;
        }

        // com o bot, guarda os nomes do servidor e do canal para a tela
        $canais = null;
        if ($i->bot_token && $i->guild_id && ($i->isDirty(['bot_token', 'webhook_url']) || ! $i->servidor_nome)) {
            $canais = Discord::canais($i->bot_token, $i->guild_id);
            if ($canais === null && $i->isDirty('bot_token')) {
                throw ValidationException::withMessages(['bot_token' => 'O Discord recusou este token, ou o bot ainda não foi adicionado ao servidor do webhook.']);
            }
            if ($canais) {
                $i->servidor_nome = mb_substr($canais['servidor'], 0, 100) ?: null;
                $canal = collect($canais['canais'])->firstWhere('id', $i->canal_id);
                $i->canal_nome = $canal ? mb_substr($canal['nome'], 0, 100) : null;
            }
        }
        $i->save();

        // webhook novo: manda logo a mensagem de teste (a pessoa já vê no canal que deu certo, sem outro clique)
        $teste = null;
        if ($url !== '') {
            $teste = Discord::enviar($i, '✅ Work Flower conectado', 'Os avisos de ' . $request->user()->name . ' vão chegar neste canal.', route('areas.negocios'));
        }

        return response()->json(['ok' => true, 'teste' => $teste] + $this->resumo($i) + ($canais ? ['canais' => $canais['canais']] : []));
    }

    public function testar(Request $request): JsonResponse
    {
        $i = $this->minha($request);
        abort_unless($i && $i->webhook_url, 422, 'Conecte o Discord primeiro.');
        $ok = Discord::enviar($i, '✅ Work Flower conectado', 'Os avisos de ' . $request->user()->name . ' vão chegar neste canal.', route('areas.negocios'));

        return response()->json(['ok' => $ok, 'erro' => $ok ? null : $i->ultimo_erro], $ok ? 200 : 422);
    }

    /**
     * Mensagem escrita no bloco Discord do quadro (public/js/blocos-integracoes.js): título, texto e link opcional,
     * enviada como embed pelo webhook da conta. A resposta não traz o webhook nem o token.
     */
    public function enviar(Request $request): JsonResponse
    {
        $this->decodificarCorpo($request);
        $d = $request->validate([
            'titulo' => ['required', 'string', 'max:250'],
            'texto'  => ['nullable', 'string', 'max:3500'],
            'link'   => ['nullable', 'string', 'max:500', 'regex:#^https?://[^\s<>"]+$#i'],
        ], [
            'titulo.required' => 'Escreva um título para a mensagem.',
            'titulo.max'      => 'O título pode ter até 250 caracteres.',
            'texto.max'       => 'O texto pode ter até 3.500 caracteres.',
            'link.regex'      =>'O link precisa começar com http:// ou https://.',
        ]);
        $i = $this->minha($request);
        abort_unless($i && $i->webhook_url, 409, 'Conecte o Discord em Negócios › Integrações primeiro.');

        $ok = Discord::enviar($i, trim($d['titulo']), (string) ($d['texto'] ?? ''), ($d['link'] ?? null) ?: null, ['Por' => $request->user()->name]);

        return response()->json([
            'ok'           => $ok,
            'erro'         => $ok ? null : $i->ultimo_erro,
            'ultimo_envio' => $i->ultimo_envio_em?->toIso8601String(),
        ], $ok ? 200 : 422);
    }

    /** Canais de texto e de voz do servidor (precisa do bot). Os de voz viram opção de link nas Calls. */
    public function canais(Request $request): JsonResponse
    {
        $i = $this->minha($request);
        if (! $i || ! $i->bot_token || ! $i->guild_id) {
            return response()->json(['canais' => [], 'precisa_bot' => true]);
        }
        $r = Discord::canais($i->bot_token, $i->guild_id);

        return response()->json(['canais' => $r['canais'] ?? [], 'servidor' => $r['servidor'] ?? null, 'erro' => $r ? null : 'O Discord não liberou a lista de canais para o bot.']);
    }

    public function remover(Request $request): JsonResponse
    {
        DiscordIntegracao::where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true] + $this->resumo(null));
    }
}
