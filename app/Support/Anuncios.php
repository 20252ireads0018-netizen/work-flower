<?php

namespace App\Support;

use App\Models\User;

/** Animações curtas (9:16) para anúncios, em /admin/anuncios. Cada uma é uma view em resources/views/anuncios. */
class Anuncios
{
    /** Únicas contas que veem e baixam as animações (as outras recebem 404). Separado de User::ADMINS de propósito. */
    public const CONTAS = ['xexplosion20@gmail.com'];

    /** slug => [título, duração em segundos, descrição] */
    public const LISTA = [
        'divulgacao' => ['Organiza, controla, lucra', 30, 'O vídeo geral: da bagunça ao lucro, com Carteira, Marketplaces, clientes e meta batida.'],
        'estudantes' => ['Quem não se organiza não passa', 15, 'Para quem estuda: mapas mentais, revisão na hora certa, anotações e leitura com marcação de página. Escassez: 30 primeiros com Premium grátis por 3 meses.'],
        'vendedores' => ['Vende muito e o dinheiro some?', 15, 'Para e-commerce e vendedores: marketplaces, margem real, caixa e metas. Escassez: 30 primeiros com Premium grátis por 3 meses.'],
        'produtores' => ['Quem não se organiza não vende', 15, 'Para produtores: vídeo, música, imagem, código e escrita de graça, e o workflow que melhora a produção. Escassez: 30 primeiros com Premium grátis por 3 meses.'],
    ];

    public static function podeVer(?User $u): bool
    {
        return $u !== null && in_array(mb_strtolower(trim((string) $u->email)), self::CONTAS, true);
    }

    /** Cor principal da conta e a cor de texto que contrasta com ela. */
    public static function cores(?User $u = null): array
    {
        $prim = ($u ?? auth()->user())?->cor_primaria ?: User::COR_PRIMARIA_PADRAO;

        return [$prim, User::contraste($prim)];
    }
}
