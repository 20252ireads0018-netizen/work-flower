<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mini lojas, parte 2: cupons de desconto, opções de entrega com preço e pagamento por cartão no Mercado Pago
 * (cada dono de loja conecta a própria conta; o dinheiro vai direto para ele).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lojas', function (Blueprint $t) {
            if (! Schema::hasColumn('lojas', 'mp_token')) $t->text('mp_token')->nullable();        // Access Token do Mercado Pago (criptografado)
            if (! Schema::hasColumn('lojas', 'mp_conta')) $t->string('mp_conta', 120)->nullable(); // apelido/e-mail da conta, para mostrar
        });

        Schema::table('loja_pedidos', function (Blueprint $t) {
            if (! Schema::hasColumn('loja_pedidos', 'subtotal')) $t->decimal('subtotal', 10, 2)->nullable();
            if (! Schema::hasColumn('loja_pedidos', 'desconto')) $t->decimal('desconto', 10, 2)->default(0);
            if (! Schema::hasColumn('loja_pedidos', 'frete')) $t->decimal('frete', 10, 2)->default(0);
            if (! Schema::hasColumn('loja_pedidos', 'frete_nome')) $t->string('frete_nome', 60)->nullable();
            if (! Schema::hasColumn('loja_pedidos', 'cupom')) $t->string('cupom', 30)->nullable();
            if (! Schema::hasColumn('loja_pedidos', 'pagamento')) $t->string('pagamento', 10)->default('pix'); // pix | mp
            if (! Schema::hasColumn('loja_pedidos', 'mp_pagamento')) $t->string('mp_pagamento', 40)->nullable();
        });

        if (! Schema::hasTable('loja_cupons')) {
            Schema::create('loja_cupons', function (Blueprint $t) {
                $t->id();
                $t->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
                $t->string('codigo', 30);
                $t->string('tipo', 10)->default('pct');           // pct (porcentagem) | valor (reais)
                $t->decimal('valor', 10, 2);
                $t->decimal('minimo', 10, 2)->nullable();          // pedido mínimo
                $t->unsignedInteger('usos_max')->nullable();       // null = sem limite
                $t->unsignedInteger('usos')->default(0);
                $t->date('validade')->nullable();                  // último dia
                $t->boolean('ativo')->default(true);
                $t->timestamps();
                $t->unique(['loja_id', 'codigo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_cupons');
        Schema::table('loja_pedidos', function (Blueprint $t) {
            $t->dropColumn(['subtotal', 'desconto', 'frete', 'frete_nome', 'cupom', 'pagamento', 'mp_pagamento']);
        });
        Schema::table('lojas', function (Blueprint $t) {
            $t->dropColumn(['mp_token', 'mp_conta']);
        });
    }
};
