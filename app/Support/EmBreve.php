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
    /**
     * Google Drive com qualquer conta Google (cartão Google Drive e Configurações › Armazenamento). Liberado por padrão;
     * GOOGLE_APP_PUBLICADO=false volta a deixar só para a administração (app do Google ainda "Em teste").
     */
    public static function driveLiberado(?User $u = null): bool
    {
        return GoogleDrive::configurado() && ((bool) config('services.google.publico') || ($u && $u->eAdmin()));
    }

    /** Gmail pelo login do Google (cartão Gmail e Negócios › E-mails). GOOGLE_GMAIL_PUBLICO=false = só a administração. */
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

        // Drive, Gmail, YouTube, Lembretes, Instagram, TikTok, Shopee e Amazon já saíram daqui (cartões do quadro).
        // Nova integração ainda não pronta: acrescente aqui com $add(...).
        unset($add);

        return $itens;
    }

    /** O site tem um servidor de e-mail de verdade (não só o log). Usado também pelo cartão Lembretes. */
    public static function emailPronto(): bool
    {
        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) return false;
        if ($mailer !== 'smtp') return true;
        $senha = (string) config('mail.mailers.smtp.password');

        return $senha !== '' && ! preg_match('/COLOQUE|SUA_SENHA/i', $senha);
    }
}
