<?php

namespace App\Support;

use App\Models\User;
use App\Support\Armazenamento\GoogleDrive;
use App\Support\Emails\Google;

/**
 * Integrações que ainda não estão liberadas para todo mundo, mostradas no painel como "Em breve".
 * Cada item some sozinho quando a configuração do site fica pronta. A lista é para quem usa o site:
 * nada aqui diz o que falta tecnicamente (chaves, apps de desenvolvedor etc.).
 */
class EmBreve
{
    /** Google Drive com qualquer conta Google: o app do Google precisa estar publicado (fora do modo de teste). */
    public static function driveLiberado(?User $u = null): bool
    {
        return GoogleDrive::configurado() && ((bool) config('services.google.publico') || ($u && $u->eAdmin()));
    }

    /** Gmail pelo login do Google: escopo restrito, só depois da verificação do Google. */
    public static function gmailLiberado(?User $u = null): bool
    {
        return Google::configurado() && ((bool) config('services.google.gmail_publico') || ($u && $u->eAdmin()));
    }

    /** @return list<array{chave: string, nome: string, onde: string, texto: string, cor: string, icone: string}> */
    public static function itens(): array
    {
        $itens = [];
        $add = function (string $chave, string $nome, string $onde, string $texto, string $cor, string $icone) use (&$itens): void {
            $itens[] = compact('chave', 'nome', 'onde', 'texto', 'cor', 'icone');
        };

        if (! self::driveLiberado()) {
            $add('drive', 'Google Drive', 'Armazenamento', 'Backups automáticos dos seus workflows numa pasta só do Work Flower no seu Drive.', '#1fa463',
                'M8.5 3h7l6 10.5-3.5 6H5.5L2 13.5 8.5 3zm0 0l6.5 10.5M15.5 3L9 13.5m-7 0h13l3.5 6');
        }
        if (! self::gmailLiberado()) {
            $add('gmail', 'Gmail com login do Google', 'Negócios › E-mails', 'Entre com a conta Google e veja a caixa de entrada sem senha de app.', '#ea4335',
                'M3 6.5A1.5 1.5 0 014.5 5h15A1.5 1.5 0 0121 6.5v11a1.5 1.5 0 01-1.5 1.5h-15A1.5 1.5 0 013 17.5v-11zm0 .5l9 6 9-6');
        }
        // Instagram, TikTok, Shopee e Amazon saíram daqui: funcionam com importação manual (CSV ou números colados)
        // mesmo antes de o app de cada rede estar no .env; com o app, conectam por OAuth (docs/integracoes.md).
        if (! self::emailPronto()) {
            $add('lembretes', 'Lembretes por e-mail', 'Calendário', 'Os lembretes do calendário chegando também na sua caixa de e-mail.', '#6366f1',
                'M12 3a6 6 0 016 6v3.5l1.5 3H4.5l1.5-3V9a6 6 0 016-6zm-2 15a2 2 0 004 0');
        }

        return $itens;
    }

    private static function emailPronto(): bool
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) return false;
        if ($mailer !== 'smtp') return true;
        $senha = (string) config('mail.mailers.smtp.password');

        return $senha !== '' && ! preg_match('/COLOQUE|SUA_SENHA/i', $senha);
    }
}
