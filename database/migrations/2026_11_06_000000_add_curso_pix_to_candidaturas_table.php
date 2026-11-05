<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compra do curso da página de vagas por Pix na chave da plataforma (a mesma dos planos).
 * O candidato gera o Pix, avisa que pagou e a administração confirma em /admin/pix. O link do curso é enviado
 * por outro sistema depois da confirmação, fora deste app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidaturas', function (Blueprint $t) {
            $t->string('curso_txid', 25)->nullable()->unique();
            $t->string('curso_status', 20)->nullable(); // aguardando | informado | confirmado | recusado
            $t->decimal('curso_valor', 10, 2)->nullable();
            $t->string('curso_pagador', 120)->nullable();
            $t->string('curso_obs', 300)->nullable();
            $t->timestamp('curso_pedido_em')->nullable();
            $t->timestamp('curso_informado_em')->nullable();
            $t->timestamp('curso_confirmado_em')->nullable();
            $t->index('curso_status');
        });
    }

    public function down(): void
    {
        Schema::table('candidaturas', function (Blueprint $t) {
            $t->dropIndex(['curso_status']);
            $t->dropUnique(['curso_txid']);
            $t->dropColumn(['curso_txid', 'curso_status', 'curso_valor', 'curso_pagador', 'curso_obs', 'curso_pedido_em', 'curso_informado_em', 'curso_confirmado_em']);
        });
    }
};
