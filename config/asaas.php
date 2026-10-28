<?php

return [
    // sandbox | production
    'environment' => env('ASAAS_ENV', 'sandbox'),

    'api_key' => env('ASAAS_API_KEY'),

    // Token que você define no painel do Asaas (Webhooks). Chega no header "asaas-access-token".
    'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),

    'urls' => [
        'sandbox' => 'https://api-sandbox.asaas.com/v3',
        'production' => 'https://api.asaas.com/v3',
    ],

    // Dias de tolerância após o fim do período antes de bloquear o acesso.
    'grace_days' => (int) env('ASAAS_GRACE_DAYS', 3),

    // false = processa o webhook na hora (ideal para cPanel, sem worker).
    // true  = envia para a fila (exige `queue:work` rodando).
    'queue_webhooks' => (bool) env('ASAAS_QUEUE_WEBHOOKS', false),

    // Para onde redirecionar quem não tem assinatura (requisições que não são JSON).
    'redirect_unsubscribed' => env('ASAAS_REDIRECT_UNSUBSCRIBED', '/planos'),
];

