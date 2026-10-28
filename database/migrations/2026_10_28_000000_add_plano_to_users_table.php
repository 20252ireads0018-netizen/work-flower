<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colunas que o webhook do Asaas e Planos::slugPago usam para liberar o plano pago.
 *
 * As migrations "2026_10_15_000000_cria_planos_e_assinatura" e
 * "2026_10_15_000001_add_colunas_asaas_to_users_table" estão sem a extensão .php,
 * então o Laravel nunca as executou e o webhook falhava com "Unknown column plano_slug".
 * Cada coluna só é criada se ainda não existir: é seguro rodar em qualquer ambiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'plano_slug')) {
                $t->string('plano_slug', 30)->default('gratuito');
            }
            if (! Schema::hasColumn('users', 'plano_expira_em')) {
                $t->timestamp('plano_expira_em')->nullable();
            }
            if (! Schema::hasColumn('users', 'asaas_subscription_id')) {
                $t->string('asaas_subscription_id', 64)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'cpf_cnpj')) {
                $t->string('cpf_cnpj', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach (['plano_slug', 'plano_expira_em'] as $coluna) {
                if (Schema::hasColumn('users', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};
