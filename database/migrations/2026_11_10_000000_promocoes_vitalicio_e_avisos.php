<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoções com aviso para cada pessoa e a compra vitalícia ("Pague uma vez, receba para sempre").
 * - promocoes.vitalicio / preco_cheio / descricao: a promoção pode vender o plano para sempre (sem expiração);
 *   preco_cheio é o preço "de" (riscado) e descricao é o texto do aviso que cada pessoa vê ao entrar.
 * - promocao_vistas: quem já viu o aviso de cada promoção (aparece uma vez por pessoa).
 * - pix_pagamentos.vitalicio / promocao_id: o Pix foi gerado pela compra vitalícia.
 * - users.plano_vitalicio: o plano pago não expira.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promocoes', function (Blueprint $t) {
            if (! Schema::hasColumn('promocoes', 'vitalicio')) {
                $t->boolean('vitalicio')->default(false);
            }
            if (! Schema::hasColumn('promocoes', 'preco_cheio')) {
                $t->decimal('preco_cheio', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('promocoes', 'descricao')) {
                $t->string('descricao', 500)->nullable();
            }
        });

        if (! Schema::hasTable('promocao_vistas')) {
            Schema::create('promocao_vistas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('promocao_id')->constrained('promocoes')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->timestamp('vista_em')->nullable();
                $t->unique(['promocao_id', 'user_id']);
            });
        }

        Schema::table('pix_pagamentos', function (Blueprint $t) {
            if (! Schema::hasColumn('pix_pagamentos', 'vitalicio')) {
                $t->boolean('vitalicio')->default(false);
            }
            if (! Schema::hasColumn('pix_pagamentos', 'promocao_id')) {
                $t->unsignedBigInteger('promocao_id')->nullable();
            }
        });

        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'plano_vitalicio')) {
                $t->boolean('plano_vitalicio')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promocao_vistas');
        foreach (['promocoes' => ['vitalicio', 'preco_cheio', 'descricao'], 'pix_pagamentos' => ['vitalicio', 'promocao_id'], 'users' => ['plano_vitalicio']] as $tabela => $colunas) {
            foreach ($colunas as $c) {
                if (Schema::hasColumn($tabela, $c)) {
                    Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
        }
    }
};
