<?php

namespace App\Support\Emails;

use App\Models\EmailConta;
use Illuminate\Support\Facades\Http;

/**
 * Gmail pelo login do Google (OAuth2 + Gmail API). Fica desligado enquanto GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET
 * estiverem vazios no .env; até lá o Gmail entra por IMAP com senha de app. O escopo gmail.readonly é "restrito":
 * para contas de fora, o Google exige verificação do app (ver docs/emails.md).
 *
 * Enviar (gmail.send, cartão Gmail do quadro) é opcional e pedido só quando a pessoa clica para mandar o primeiro
 * e-mail (autorização incremental: include_granted_scopes). Quem conectou antes continua só lendo até permitir.
 * Marcar como lido e arquivar exigiriam gmail.modify: ficam de fora de propósito.
 */
class Google
{
    public const ESCOPO = 'https://www.googleapis.com/auth/gmail.readonly';
    public const ESCOPO_ENVIAR = 'https://www.googleapis.com/auth/gmail.send';
    public const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    public static function configurado(): bool
    {
        return (string) config('services.google.client_id') !== '' && (string) config('services.google.client_secret') !== '';
    }

    public static function retorno(): string
    {
        $relay = (string) config('services.google.relay');

        return (string) (config('services.google.redirect') ?: ($relay !== '' ? $relay . '/emails/' : route('emails.google.retorno')));
    }

    /** $enviar pede também o gmail.send; $email sugere a conta certa na tela do Google (login_hint). */
    public static function urlAutorizacao(string $state, bool $enviar = false, ?string $email = null): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array_filter([
            'client_id'              => config('services.google.client_id'),
            'redirect_uri'           => self::retorno(),
            'response_type'          => 'code',
            'scope'                  => self::ESCOPO . ($enviar ? ' ' . self::ESCOPO_ENVIAR : ''),
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent',
            'login_hint'             => $email,
            'state'                  => $state,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array{access_token: string, refresh_token: ?string, expira: int, email: string, escopos: string} */
    public static function trocarCodigo(string $codigo): array
    {
        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code'          => $codigo,
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri'  => self::retorno(),
            'grant_type'    => 'authorization_code',
        ]);
        if (! $r->successful() || ! $r->json('access_token')) throw new EmailErro('O Google não aceitou a autorização. Tente de novo.');

        $perfil = Http::withToken($r->json('access_token'))->timeout(15)->get(self::API . '/profile');
        $email = (string) $perfil->json('emailAddress');
        if (! $perfil->successful() || $email === '') throw new EmailErro('Não consegui ler o endereço da conta Google.');

        return [
            'access_token'  => $r->json('access_token'),
            'refresh_token' => $r->json('refresh_token'),
            'expira'        => (int) $r->json('expires_in', 3600),
            'email'         => mb_strtolower($email),
            'escopos'       => mb_substr((string) $r->json('scope', self::ESCOPO), 0, 1000),
        ];
    }

    /** A caixa é Gmail e o Google liberou o envio para ela. */
    public static function podeEnviar(EmailConta $conta): bool
    {
        return $conta->tipo === 'gmail' && in_array(self::ESCOPO_ENVIAR, preg_split('/\s+/', (string) $conta->escopos) ?: [], true);
    }

    /** Token válido da conta, renovando com o refresh_token quando venceu. */
    public static function token(EmailConta $conta): string
    {
        if ($conta->access_token && $conta->token_expira_em && $conta->token_expira_em->isAfter(now()->addMinute())) {
            return $conta->access_token;
        }
        if (! $conta->refresh_token || ! self::configurado()) throw new EmailErro('O acesso ao Gmail venceu. Conecte a conta de novo.');

        $r = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $conta->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);
        if (! $r->successful() || ! $r->json('access_token')) throw new EmailErro('O Google cancelou o acesso. Conecte a conta de novo.');

        $conta->forceFill([
            'access_token'    => $r->json('access_token'),
            'token_expira_em' => now()->addSeconds((int) $r->json('expires_in', 3600)),
        ] + ($r->json('scope') ? ['escopos' => mb_substr((string) $r->json('scope'), 0, 1000)] : []))->save();

        return $conta->access_token;
    }
}
