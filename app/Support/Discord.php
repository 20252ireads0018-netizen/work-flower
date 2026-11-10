<?php

namespace App\Support;

use App\Models\DiscordIntegracao;
use Illuminate\Support\Facades\Http;

/**
 * Avisos do Work Flower num servidor do Discord, pelo webhook que a pessoa cola em Negócios › Integrações.
 *   Discord::avisar($userId, 'tarefa_criada', 'Título', 'texto', $url, ['Campo' => 'valor'])
 * Nunca derruba quem chamou: erro de rede ou webhook apagado só fica anotado em ultimo_erro.
 * O token do bot (opcional) serve só para ler a lista de canais do servidor (texto e voz).
 */
class Discord
{
    /** Só endereços de webhook do próprio Discord (o servidor não faz pedidos para outros lugares). */
    public const WEBHOOK_REGEX = '#^https://(?:(?:ptb|canary)\.)?(?:discord|discordapp)\.com/api(?:/v\d+)?/webhooks/(\d{5,25})/([\w-]{20,200})$#';

    private const API = 'https://discord.com/api/v10';

    private const COR = 0x2F6FC7;

    public static function webhookValido(string $url): bool
    {
        return (bool) preg_match(self::WEBHOOK_REGEX, trim($url));
    }

    /*
     * Conexão de um clique (OAuth2 do Discord com o escopo webhook.incoming): o próprio Discord mostra "escolha o
     * servidor e o canal" e devolve o webhook pronto, sem a pessoa criar nem copiar nada. Precisa do app do dono do
     * site (DISCORD_CLIENT_ID/SECRET, discord.com/developers › OAuth2, Redirect: /integracoes/discord/retorno).
     * Sem isso, continua valendo colar o webhook.
     */
    public static function oauthConfigurado(): bool
    {
        return (string) config('services.discord.client_id') !== '' && (string) config('services.discord.client_secret') !== '';
    }

    public static function retorno(): string
    {
        return (string) (config('services.discord.redirect') ?: route('integracoes.discord.retorno'));
    }

    public static function urlAutorizacao(string $estado): string
    {
        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id'     => config('services.discord.client_id'),
            'response_type' => 'code',
            'scope'         => 'webhook.incoming',
            'redirect_uri'  => self::retorno(),
            'state'         => $estado,
        ]);
    }

    /** Troca o código do retorno pelo webhook criado no canal escolhido: ['webhook_url', 'guild_id', 'canal_id']. */
    public static function trocarCodigo(string $codigo): array
    {
        try {
            $r = Http::asForm()->acceptJson()->timeout(20)->connectTimeout(5)
                ->withBasicAuth((string) config('services.discord.client_id'), (string) config('services.discord.client_secret'))
                ->post(self::API . '/oauth2/token', [
                    'grant_type'   => 'authorization_code',
                    'code'         => $codigo,
                    'redirect_uri' => self::retorno(),
                ]);
        } catch (\Throwable) {
            throw new \RuntimeException('Não deu para falar com o Discord agora. Tente conectar de novo.');
        }
        $url = (string) $r->json('webhook.url');
        if (! $r->successful() || ! self::webhookValido($url)) {
            throw new \RuntimeException('O Discord não liberou o webhook. Tente conectar de novo e escolha um canal.');
        }

        return [
            'webhook_url' => $url,
            'guild_id'    => ((string) $r->json('webhook.guild_id')) ?: null,
            'canal_id'    => ((string) $r->json('webhook.channel_id')) ?: null,
        ];
    }

    /** Manda o aviso se a conta tem Discord ligado e esse tipo de aviso ativo. true se o Discord aceitou. */
    public static function avisar(int $userId, string $evento, string $titulo, string $texto = '', ?string $url = null, array $campos = []): bool
    {
        try {
            $i = DiscordIntegracao::where('user_id', $userId)->first();
            if (! $i || ! $i->webhook_url || ! $i->avisa($evento)) {
                return false;
            }

            return self::enviar($i, $titulo, $texto, $url, $campos);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /** Aviso de tarefa do quadro (criada, movida ou delegada), para o Discord do dono do workflow. */
    public static function avisarTarefa(string $evento, \App\Models\Tarefa $t, string $quem, string $titulo): void
    {
        $area = \App\Models\Tarefa::AREAS[$t->area]['nome'] ?? $t->area;
        self::avisar((int) $t->user_id, $evento, $titulo, (string) $t->descricao, null, [
            'Tarefa'      => mb_substr($t->titulo, 0, 150),
            'Área'        => $area,
            'Coluna'      => \App\Models\Tarefa::STATUS[$t->status] ?? null,
            'Responsável' => $t->responsavel?->name,
            'Prazo'       => $t->prazo?->format('d/m/Y'),
            'Por'         => $quem,
        ]);
    }

    /** Envia uma mensagem (embed) pelo webhook da integração e anota o resultado. */
    public static function enviar(DiscordIntegracao $i, string $titulo, string $texto = '', ?string $url = null, array $campos = []): bool
    {
        $embed = ['title' => mb_substr($titulo, 0, 250), 'color' => self::COR, 'footer' => ['text' => 'Work Flower']];
        if (trim($texto) !== '') {
            $embed['description'] = mb_substr($texto, 0, 3500);
        }
        if ($url && str_starts_with($url, 'http')) {
            $embed['url'] = $url;
        }
        $f = [];
        foreach ($campos as $nome => $valor) {
            if ($valor === null || $valor === '') continue;
            $f[] = ['name' => mb_substr((string) $nome, 0, 250), 'value' => mb_substr((string) $valor, 0, 1000), 'inline' => mb_strlen((string) $valor) < 40];
        }
        if ($f) {
            $embed['fields'] = array_slice($f, 0, 10);
        }

        try {
            $r = Http::timeout(5)->connectTimeout(3)->acceptJson()
                ->post($i->webhook_url, [
                    'username'         => 'Work Flower',
                    'embeds'           => [$embed],
                    'allowed_mentions' => ['parse' => []], // texto do usuário não marca @everyone
                ]);
            $ok = $r->successful();
            $erro = $ok ? null : self::erroDe($r->status());
        } catch (\Throwable $e) {
            $ok = false;
            $erro = 'Sem resposta do Discord.';
        }
        $i->forceFill(['ultimo_erro' => $erro, 'ultimo_envio_em' => $ok ? now() : $i->ultimo_envio_em])->saveQuietly();

        return $ok;
    }

    /** Lê o webhook (sem token): servidor e canal para onde ele manda. null se o Discord não reconhece o endereço. */
    public static function infoWebhook(string $url): ?array
    {
        try {
            $r = Http::timeout(5)->connectTimeout(3)->acceptJson()->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $r->successful()) {
            return null;
        }

        return ['guild_id' => (string) $r->json('guild_id'), 'canal_id' => (string) $r->json('channel_id'), 'nome' => (string) $r->json('name')];
    }

    /**
     * Canais do servidor pelo bot: ['servidor' => nome, 'canais' => [[id, nome, tipo texto|voz, link]]].
     * null se o token não serve (ou o bot não está no servidor).
     */
    public static function canais(string $botToken, string $guildId): ?array
    {
        $h = Http::timeout(6)->connectTimeout(3)->acceptJson()->withHeaders(['Authorization' => 'Bot ' . $botToken]);
        try {
            $g = $h->get(self::API . "/guilds/{$guildId}");
            $c = $h->get(self::API . "/guilds/{$guildId}/channels");
        } catch (\Throwable) {
            return null;
        }
        if (! $g->successful() || ! $c->successful()) {
            return null;
        }
        $canais = collect($c->json() ?: [])
            ->filter(fn ($x) => is_array($x) && in_array((int) ($x['type'] ?? -1), [0, 2, 5, 13], true))
            ->sortBy(fn ($x) => [(int) ($x['type'] ?? 0) === 2 || (int) ($x['type'] ?? 0) === 13 ? 1 : 0, (int) ($x['position'] ?? 0)])
            ->map(fn ($x) => [
                'id'   => (string) $x['id'],
                'nome' => (string) ($x['name'] ?? ''),
                'tipo' => in_array((int) $x['type'], [2, 13], true) ? 'voz' : 'texto',
                'link' => "https://discord.com/channels/{$guildId}/{$x['id']}",
            ])->values()->all();

        return ['servidor' => (string) $g->json('name'), 'canais' => $canais];
    }

    private static function erroDe(int $status): string
    {
        return match ($status) {
            401, 403, 404 => 'O Discord não reconhece mais este webhook (ele pode ter sido apagado). Cole um novo.',
            429           => 'O Discord pediu para esperar (muitos avisos seguidos).',
            default       => "O Discord respondeu com erro {$status}.",
        };
    }
}
