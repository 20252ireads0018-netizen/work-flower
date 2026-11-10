<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escopos que o Google liberou para a caixa Gmail (separados por espaço, como o Google devolve no token).
 * O envio pelo cartão Gmail (gmail.send) é pedido só quando a pessoa clica para mandar o primeiro e-mail:
 * quem conectou antes continua só com leitura até permitir. Vazio = só leitura.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('email_contas', 'escopos')) {
            return;
        }
        Schema::table('email_contas', function (Blueprint $t) {
            $t->string('escopos', 1000)->nullable()->after('token_expira_em');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('email_contas', 'escopos')) {
            Schema::table('email_contas', fn (Blueprint $t) => $t->dropColumn('escopos'));
        }
    }
};
