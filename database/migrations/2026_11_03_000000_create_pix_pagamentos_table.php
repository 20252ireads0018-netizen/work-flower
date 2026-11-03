<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento dos planos por Pix direto na chave da administração (substitui o Asaas).
 * A pessoa paga pelo QR Code, avisa que pagou e a administração confirma em /admin/pix.
 * "configuracoes" guarda a chave Pix, o nome e a cidade que vão no QR Code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pix_pagamentos')) {
            Schema::create('pix_pagamentos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('plano_slug', 30);
                $t->decimal('valor', 10, 2);
                $t->string('txid', 25)->unique(); // identificador que vai no QR Code e aparece no extrato
                $t->string('status', 20)->default('aguardando'); // aguardando | informado | confirmado | recusado | cancelado
                $t->string('pagador', 120)->nullable(); // nome de quem pagou, para achar no extrato
                $t->timestamp('informado_em')->nullable();
                $t->timestamp('confirmado_em')->nullable();
                $t->timestamp('liberado_ate')->nullable();
                $t->string('observacao', 500)->nullable();
                $t->timestamps();
                $t->index(['user_id', 'status']);
                $t->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('configuracoes')) {
            Schema::create('configuracoes', function (Blueprint $t) {
                $t->string('chave', 60)->primary();
                $t->text('valor')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_pagamentos');
        Schema::dropIfExists('configuracoes');
    }
};
