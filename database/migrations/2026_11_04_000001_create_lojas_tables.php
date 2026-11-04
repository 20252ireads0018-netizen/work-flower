<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mini lojas (Carteira › Loja): cada conta monta uma loja com página pública em /loja/{slug},
 * produtos e pedidos pagos por Pix na chave do dono da loja (o Work Flower não recebe nada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lojas')) {
            Schema::create('lojas', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $t->string('slug', 40)->unique();
                $t->string('nome', 80);
                $t->string('chamada', 160)->nullable();   // frase do topo
                $t->text('sobre')->nullable();
                $t->json('visual')->nullable();           // cores, fonte, layout, logo, banner, contatos
                $t->string('pix_tipo', 12)->nullable();
                $t->string('pix_chave', 80)->nullable();
                $t->string('pix_nome', 60)->nullable();
                $t->string('pix_cidade', 40)->nullable();
                $t->boolean('publicada')->default(false);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('loja_produtos')) {
            Schema::create('loja_produtos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
                $t->string('nome', 120);
                $t->text('descricao')->nullable();
                $t->decimal('preco', 10, 2);
                $t->decimal('preco_antigo', 10, 2)->nullable(); // "de R$ X por R$ Y"
                $t->unsignedInteger('estoque')->nullable();      // null = sem controle de estoque
                $t->string('imagem', 80)->nullable();            // arquivo em storage/app/lojas/{loja}/
                $t->boolean('ativo')->default(true);
                $t->unsignedInteger('ordem')->default(0);
                $t->timestamps();
                $t->index(['loja_id', 'ativo', 'ordem']);
            });
        }

        if (! Schema::hasTable('loja_pedidos')) {
            Schema::create('loja_pedidos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('loja_id')->constrained('lojas')->cascadeOnDelete();
                $t->string('codigo', 25)->unique();     // vai no Pix e no link do pedido
                $t->string('cliente_nome', 120);
                $t->string('cliente_contato', 120);     // WhatsApp ou e-mail
                $t->text('cliente_endereco')->nullable();
                $t->string('observacao', 500)->nullable();
                $t->json('itens');                      // [{ id, nome, preco, qtd }]
                $t->decimal('total', 10, 2);
                $t->string('status', 20)->default('aguardando'); // aguardando | informado | pago | enviado | cancelado
                $t->timestamp('informado_em')->nullable();
                $t->timestamps();
                $t->index(['loja_id', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_pedidos');
        Schema::dropIfExists('loja_produtos');
        Schema::dropIfExists('lojas');
    }
};
