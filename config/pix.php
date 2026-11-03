<?php

/*
 * Pix direto na chave da administração. O normal é preencher em /admin/pix (fica no banco);
 * estes valores do .env só valem enquanto nada foi salvo lá.
 */
return [
    'tipo'   => env('PIX_TIPO', 'aleatoria'), // cpf | cnpj | email | telefone | aleatoria
    'chave'  => env('PIX_CHAVE'),
    'nome'   => env('PIX_NOME'),              // nome do titular da conta (vai no QR Code)
    'cidade' => env('PIX_CIDADE'),

    // Dias de tolerância depois do fim do mês pago antes de o plano voltar ao gratuito.
    'grace_days' => (int) env('PIX_GRACE_DAYS', env('ASAAS_GRACE_DAYS', 3)),
];
