<?php

/*
 * Armazenamento customizado (Configurações › Armazenamento).
 * Google Drive: crie um app OAuth em console.cloud.google.com (API do Google Drive ativada, tela de consentimento
 * com o escopo .../auth/drive.file) e cadastre como URI de redirecionamento: {APP_URL}/armazenamento/google/retorno
 */
return [
    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    ],

    // backups automáticos guardados por destino (os mais antigos são apagados do destino)
    'manter_diarios' => (int) env('ARMAZENAMENTO_MANTER_DIARIOS', 14),

    // imagens acima deste tamanho (bytes, já em base64) podem sair do banco
    'imagem_grande' => (int) env('ARMAZENAMENTO_IMAGEM_GRANDE', 100 * 1024),
];
