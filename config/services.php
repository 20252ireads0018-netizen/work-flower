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
        // enquanto o app do Google está "Em teste", só a administração vê o Drive/Gmail; os outros veem "Em breve"
        'publico'       => (bool) env('GOOGLE_APP_PUBLICADO', false),       // Drive (escopo drive.file, sem verificação)
        'gmail_publico' => (bool) env('GOOGLE_GMAIL_PUBLICO', false),       // Gmail (escopo restrito: depois da verificação)
    ],
    // App da Meta (Instagram Graph API pelo Login do Facebook e assinatura do webhook do WhatsApp) e app do TikTok
    'meta'   => ['id' => env('META_APP_ID'), 'secret' => env('META_APP_SECRET'), 'versao' => env('META_GRAPH_VERSION', 'v21.0')],
    'tiktok' => ['key' => env('TIKTOK_CLIENT_KEY'), 'secret' => env('TIKTOK_CLIENT_SECRET')],

    'adsense' => [
        'client' => env('ADSENSE_CLIENT'),
    ],

];

