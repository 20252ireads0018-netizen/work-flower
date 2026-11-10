<?php

namespace App\Support\Integracoes;

/**
 * Endereço de retorno do OAuth: a página de repasse do GitHub Pages (docs/{caminho}/index.html) quando
 * INTEGRACOES_RELAY_URL (ou GOOGLE_RELAY_URL) está no .env, ou a rota direta do site.
 * O mesmo endereço precisa ir na autorização, na troca do código e no cadastro do app de cada rede.
 */
class Repasse
{
    public static function url(string $caminho, string $direto): string
    {
        $relay = (string) config('integracoes.relay');

        return $relay !== '' ? $relay . '/' . trim($caminho, '/') . '/' : $direto;
    }
}
