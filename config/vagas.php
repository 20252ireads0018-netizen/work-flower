<?php

return [
    // Link antigo da aba "Recomendação da empresa" (/vagas/curso). A aba agora vende o curso por Pix;
    // o acesso é mandado por outro sistema depois que o pagamento é confirmado.
    'curso_url' => env('VAGAS_CURSO_URL'),

    // Preço do curso, pago por Pix na chave da plataforma (confirmado em /admin/pix).
    'curso_preco' => (float) env('VAGAS_CURSO_PRECO', 19.90),

    // Token para ver as candidaturas em /vagas/admin?token=...
    // Vazio = painel desativado.
    'admin_token' => env('VAGAS_ADMIN_TOKEN'),
];
