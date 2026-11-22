<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'brapi' => ['token' => env('BRAPI_TOKEN')],
    
    // métricas do cartão Criador de conteúdo (YouTube Data API v3; chave em console.cloud.google.com)
    'youtube' => ['key' => env('YOUTUBE_API_KEY')],

    // Gmail pelo login do Google (Negócios › E-mails). Vazio = desligado; o Gmail entra por IMAP com senha de app.
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
        // página de repasse (GitHub Pages, pasta docs/google): o mod_security da HostGator barra o retorno do Google
        // com 406 por causa do parâmetro iss=https://accounts.google.com; o repasse tira o iss e volta para o site
        'relay'         => rtrim((string) env('GOOGLE_RELAY_URL', ''), '/'),
        // Drive e Gmail liberados para todas as contas (cartões do quadro). Com o app do Google ainda "Em teste", ponha
        // false para deixar só a administração (ver docs/google-integracoes.md)
        'publico'       => (bool) env('GOOGLE_APP_PUBLICADO', true),        // Drive (escopo drive.file, sem verificação)
        'gmail_publico' => (bool) env('GOOGLE_GMAIL_PUBLICO', true),        // Gmail (escopo restrito: tela "app não verificado" até a verificação)
    ],
    // Notion (Negócios › Integrações): integração pública criada pelo dono do site em notion.so/profile/integrations.
    // Vazio = só o token de integração interna colado por cada pessoa. O retorno padrão é /integracoes/notion/retorno;
    // NOTION_REDIRECT_URI troca por outro endereço (ex.: uma página de repasse, se a hospedagem barrar o retorno).
    'notion' => [
        'client_id'     => env('NOTION_CLIENT_ID'),
        'client_secret' => env('NOTION_CLIENT_SECRET'),
        'redirect'      => env('NOTION_REDIRECT_URI'),
    ],
    // App da Meta (Instagram Graph API pelo Login do Facebook e assinatura do webhook do WhatsApp) e app do TikTok
    'meta'   => ['id' => env('META_APP_ID'), 'secret' => env('META_APP_SECRET'), 'versao' => env('META_GRAPH_VERSION', 'v21.0')],
    'tiktok' => ['key' => env('TIKTOK_CLIENT_KEY'), 'secret' => env('TIKTOK_CLIENT_SECRET')],
    // App do Discord (conectar com um clique: o Discord devolve o webhook do canal escolhido). Vazio: colar o webhook.
    'discord' => ['client_id' => env('DISCORD_CLIENT_ID'), 'client_secret' => env('DISCORD_CLIENT_SECRET'), 'redirect' => env('DISCORD_REDIRECT_URI')],

    // rede de anúncios: "ambos" (padrão), "adsense" ou "adsterra" (a meta de verificação do AdSense fica sempre)
    'anuncios_rede' => env('ANUNCIOS_REDE') ?: 'ambos',

    // Adsterra (App\Support\Publicidade): cada chave aceita os 32 caracteres ou o endereço do invoke.js do código da Adsterra. Vazia = não aparece.
    // Só Banner (rodapé das páginas públicas; 728x90 no computador, o de celular é opcional) e Native Banner. Nunca Popunder nem Social Bar.
    'adsterra' => [
        'banner' => env('ADSTERRA_BANNER_KEY', ''),
        'banner_tamanho' => env('ADSTERRA_BANNER_TAMANHO') ?: '728x90',
        'banner_movel' => env('ADSTERRA_BANNER_MOVEL_KEY', ''),
        'banner_movel_tamanho' => env('ADSTERRA_BANNER_MOVEL_TAMANHO') ?: '300x250',
        'banner_host' => env('ADSTERRA_BANNER_HOST') ?: 'www.highperformanceformat.com',
        'native' => env('ADSTERRA_NATIVE_KEY', ''),
        'native_host' => env('ADSTERRA_NATIVE_HOST', ''),
    ],

    'adsense' => [
        // conta da Websight; ADSENSE_CLIENT no .env troca (vazio no .env = sem anúncios)
        'client' => env('ADSENSE_CLIENT', 'ca-pub-4781960143966285'),
    ],

    // Pixel da Meta (Gerenciador de Eventos > conjunto de dados Web). Vazio = pixel desligado.
    'meta' => [
        // conjunto de dados "Web-sight sites"; META_PIXEL_ID no .env troca (vazio no .env = desligado)
        'pixel' => env('META_PIXEL_ID', '1115433257784212'),
    ],

];

