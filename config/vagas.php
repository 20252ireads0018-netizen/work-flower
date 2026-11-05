<?php

return [
    // Link para onde a aba "Recomendação da empresa" leva o candidato.
    'curso_url' => env('VAGAS_CURSO_URL'),

    // Token para ver as candidaturas em /vagas/admin?token=...
    // Vazio = painel desativado.
    'admin_token' => env('VAGAS_ADMIN_TOKEN'),
];
