<?php

/*
 * Marketplaces da Carteira (App\Support\Marketplaces).
 *
 * Cada canal só conecta contas reais depois que as chaves do app de desenvolvedor estiverem no .env.
 * Sem as chaves, a tela oferece só a conta de demonstração.
 *
 * Endereço de retorno para cadastrar no app de cada marketplace:
 *   https://SEU-DOMINIO/work-flower/carteira/marketplaces/retorno/{canal}
 *   (canal = mercadolivre | shopee | amazon)
 *
 * "regras" são valores de REFERÊNCIA para calcular a margem quando o marketplace não informa a tarifa.
 * As tarifas mudam com frequência e variam por categoria e tipo de anúncio: cada conta pode ajustar as suas na tela.
 */
return [

    'canais' => [

        // https://developers.mercadolivre.com.br  (Criar aplicação > URI de redirect)
        'mercadolivre' => [
            'nome'          => 'Mercado Livre',
            'client_id'     => env('MERCADOLIVRE_CLIENT_ID'),
            'client_secret' => env('MERCADOLIVRE_CLIENT_SECRET'),
            'auth_url'      => env('MERCADOLIVRE_AUTH_URL', 'https://auth.mercadolivre.com.br/authorization'),
            'api'           => 'https://api.mercadolibre.com',
            'site'          => 'MLB',
            'regras'        => ['comissao_pct' => 14, 'taxa_fixa' => 6.75, 'taxa_fixa_abaixo_de' => 79, 'frete' => 0, 'imposto_pct' => 0],
        ],

        // https://open.shopee.com  (App > Partner ID / Partner Key; Redirect URL Domain)
        'shopee' => [
            'nome'        => 'Shopee',
            'partner_id'  => env('SHOPEE_PARTNER_ID'),
            'partner_key' => env('SHOPEE_PARTNER_KEY'),
            // produção: https://partner.shopeemobile.com  |  teste: o host de sandbox informado no painel da Shopee
            'host'        => env('SHOPEE_HOST', 'https://partner.shopeemobile.com'),
            'regras'      => ['comissao_pct' => 20, 'taxa_fixa' => 4, 'taxa_fixa_abaixo_de' => null, 'frete' => 0, 'imposto_pct' => 0],
        ],

        // Seller Central > Apps e serviços > Desenvolver apps (SP-API). Credenciais LWA + ID do aplicativo.
        'amazon' => [
            'nome'           => 'Amazon',
            'client_id'      => env('AMAZON_LWA_CLIENT_ID'),
            'client_secret'  => env('AMAZON_LWA_CLIENT_SECRET'),
            'application_id' => env('AMAZON_SP_APP_ID'),
            'rascunho'       => (bool) env('AMAZON_SP_RASCUNHO', true), // app ainda não publicado (version=beta)
            'seller_central' => env('AMAZON_SELLER_CENTRAL', 'https://sellercentral.amazon.com.br'),
            'endpoint'       => env('AMAZON_SP_ENDPOINT', 'https://sellingpartnerapi-na.amazon.com'), // Brasil fica na região NA
            'marketplace_id' => env('AMAZON_MARKETPLACE_ID', 'A2Q3Y263D00KWC'),                     // Amazon.com.br
            'regras'         => ['comissao_pct' => 13, 'taxa_fixa' => 0, 'taxa_fixa_abaixo_de' => null, 'frete' => 0, 'imposto_pct' => 0],
        ],
    ],

    // quantos dias de pedidos trazer em cada sincronização
    'dias_pedidos' => 30,

    // teto por sincronização (hospedagem compartilhada: a requisição não pode demorar demais)
    'max_anuncios' => 2000,
];
