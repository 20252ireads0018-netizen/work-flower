<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplaces (Mercado Livre, Shopee, Amazon) dentro da Carteira.
 * - mkt_contas: contas de vendedor conectadas (tokens criptografados; limite de contas vem do plano do dono)
 * - mkt_produtos: catálogo próprio por SKU, com custo (base da margem)
 * - mkt_anuncios: anúncios trazidos de cada conta, ligados ao produto pelo SKU
 * - mkt_pedidos: vendas recentes de cada conta
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mkt_contas')) {
            Schema::create('mkt_contas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('canal', 20);                  // mercadolivre | shopee | amazon
                $table->string('nome', 120);
                $table->string('externo_id', 80)->nullable(); // id do vendedor / loja no marketplace
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expira_em')->nullable();
                $table->json('extra')->nullable();            // dados do canal (região, marketplace etc.)
                $table->json('regras')->nullable();           // comissão, tarifa fixa, frete, imposto desta conta
                $table->boolean('demo')->default(false);
                $table->boolean('ativa')->default(true);
                $table->string('erro', 255)->nullable();      // último erro de sincronização
                $table->timestamp('sincronizado_em')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'canal']);
            });
        }

        if (! Schema::hasTable('mkt_produtos')) {
            Schema::create('mkt_produtos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('sku', 80);
                $table->string('nome', 200)->nullable();
                $table->decimal('custo', 12, 2)->default(0);       // custo do produto
                $table->decimal('custo_extra', 12, 2)->default(0); // embalagem, etiqueta etc.
                $table->timestamps();
                $table->unique(['user_id', 'sku']);
            });
        }

        if (! Schema::hasTable('mkt_anuncios')) {
            Schema::create('mkt_anuncios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('conta_id')->constrained('mkt_contas')->cascadeOnDelete();
                $table->string('externo_id', 80);
                $table->string('sku', 80)->nullable();
                $table->string('titulo', 255)->nullable();
                $table->decimal('preco', 12, 2)->default(0);
                $table->integer('estoque')->default(0);
                $table->string('status', 20)->default('ativo');     // ativo | pausado | encerrado
                $table->string('tipo', 40)->nullable();             // tipo do anúncio (ex.: Clássico, Premium)
                $table->decimal('tarifa_venda', 12, 2)->nullable(); // tarifa informada pelo marketplace (R$ por venda)
                $table->decimal('comissao_pct', 6, 2)->nullable();  // sobrescreve a regra da conta
                $table->decimal('frete', 12, 2)->nullable();        // sobrescreve a regra da conta
                $table->string('link', 500)->nullable();
                $table->string('imagem', 500)->nullable();
                $table->json('dados')->nullable();                  // o que mais o canal precisar (ex.: productType da Amazon)
                $table->timestamp('sincronizado_em')->nullable();
                $table->timestamps();
                $table->unique(['conta_id', 'externo_id']);
                $table->index(['user_id', 'sku']);
            });
        }

        if (! Schema::hasTable('mkt_pedidos')) {
            Schema::create('mkt_pedidos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('conta_id')->constrained('mkt_contas')->cascadeOnDelete();
                $table->string('externo_id', 80);
                $table->timestamp('data')->nullable();
                $table->string('status', 40)->nullable();
                $table->decimal('total', 12, 2)->default(0);
                $table->decimal('tarifas', 12, 2)->nullable(); // tarifas cobradas, quando o canal informa
                $table->json('itens')->nullable();             // [{externo_id, sku, titulo, qtd, preco}]
                $table->timestamps();
                $table->unique(['conta_id', 'externo_id']);
                $table->index(['user_id', 'data']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_pedidos');
        Schema::dropIfExists('mkt_anuncios');
        Schema::dropIfExists('mkt_produtos');
        Schema::dropIfExists('mkt_contas');
    }
};
