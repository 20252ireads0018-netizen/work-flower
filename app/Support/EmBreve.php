<?php

namespace App\Support;

use App\Models\User;
use App\Support\Armazenamento\GoogleDrive;
use App\Support\Emails\Google;
use App\Support\Marketplaces\Marketplaces;

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

        if (! config('services.meta.id') || ! config('services.meta.secret')) {
            $add('instagram', 'Instagram', 'Criador de conteúdo', 'Posts, alcance e seguidores do seu perfil direto no cartão do Criador.', '#e1306c',
                'M7 3h10a4 4 0 014 4v10a4 4 0 01-4 4H7a4 4 0 01-4-4V7a4 4 0 014-4zm5 5a4 4 0 100 8 4 4 0 000-8zm5.5-1.5h.01');
        }
        if (! config('services.tiktok.key') || ! config('services.tiktok.secret')) {
            $add('tiktok', 'TikTok', 'Criador de conteúdo', 'Vídeos, visualizações e curtidas da sua conta ao lado do YouTube.', '#25f4ee',
                'M14 3v11.5a3.5 3.5 0 11-3.5-3.5M14 3c.5 2.5 2.5 4.5 5 5');
        }
        foreach (['shopee' => ['Shopee', '#ee4d2d'], 'amazon' => ['Amazon', '#ff9900']] as $canal => [$nome, $cor]) {
            if (! self::canalPronto($canal)) {
                $add($canal, $nome, 'Carteira › Marketplaces', "Anúncios, vendas e margem da sua loja na {$nome} junto com os outros canais.", $cor,
                    'M4 7h16l-1.5 11a2 2 0 01-2 1.7h-9a2 2 0 01-2-1.7L4 7zm4 0a4 4 0 018 0');
            }
        }

        return $itens;
    }

    private static function canalPronto(string $canal): bool
    {
        try {
            return Marketplaces::conector($canal)->configurado();
        } catch (\Throwable) {
            return false;
        }
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
