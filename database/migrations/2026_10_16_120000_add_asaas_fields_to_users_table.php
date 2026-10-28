<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colunas que o AssinaturaController e o webhook do Asaas gravam em `users`.
 * Cada coluna só é criada se ainda não existir, então é seguro rodar mais de uma vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'asaas_subscription_id')) {
                $t->string('asaas_subscription_id')->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'cpf_cnpj')) {
                $t->string('cpf_cnpj', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'asaas_subscription_id')) {
                $t->dropColumn('asaas_subscription_id');
            }
            // cpf_cnpj não é removida aqui: ela pode já existir de outra migration.
        });
    }
};
