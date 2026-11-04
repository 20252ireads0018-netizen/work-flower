<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Negócios › Produtos e mini lojas, parte 3:
 *  - catálogo único: loja_produtos ganha dono (user_id), SKU, custo, categoria e tipo (produto/serviço), e
 *    loja_id passa a ser opcional (produto do catálogo de quem ainda não tem loja). Produtos existentes recebem o
 *    user_id da loja deles; nada muda na vitrine.
 *  - lojas podem ser excluídas sem perder o histórico (deleted_at): pedidos e produtos continuam guardados.
 *  - o Pix das lojas passa a cair na chave da plataforma (a mesma das assinaturas): cada pedido guarda para onde
 *    foi o Pix (pix_destino). Os pedidos que já existem ficam como "loja" (chave do dono, como antes) e continuam
 *    funcionando do jeito antigo; os novos vão para a plataforma e viram saldo do dono depois de confirmados.
 *  - saldo do dono da loja (loja_saldo_movimentos) e pedidos de resgate para a chave Pix dele (loja_resgates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loja_produtos', function (Blueprint $t) {
            if (! Schema::hasColumn('loja_produtos', 'user_id')) $t->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            if (! Schema::hasColumn('loja_produtos', 'sku')) $t->string('sku', 40)->nullable();
            if (! Schema::hasColumn('loja_produtos', 'custo')) $t->decimal('custo', 10, 2)->nullable();
            if (! Schema::hasColumn('loja_produtos', 'categoria')) $t->string('categoria', 60)->nullable();
            if (! Schema::hasColumn('loja_produtos', 'tipo')) $t->string('tipo', 10)->default('produto'); // produto | servico
        });
        // produto do catálogo pode existir sem loja
        Schema::table('loja_produtos', function (Blueprint $t) {
            $t->unsignedBigInteger('loja_id')->nullable()->change();
        });
        // dono dos produtos que já existiam = dono da loja
        DB::table('loja_produtos')->whereNull('user_id')->orderBy('id')->chunkById(500, function ($l) {
            $donos = DB::table('lojas')->whereIn('id', $l->pluck('loja_id')->filter())->pluck('user_id', 'id');
            foreach ($l as $p) {
                if (isset($donos[$p->loja_id])) DB::table('loja_produtos')->where('id', $p->id)->update(['user_id' => $donos[$p->loja_id]]);
            }
        });
        Schema::table('loja_produtos', function (Blueprint $t) {
            $t->index(['user_id', 'nome']);
        });

        Schema::table('lojas', function (Blueprint $t) {
            if (! Schema::hasColumn('lojas', 'deleted_at')) $t->softDeletes();
        });

        Schema::table('loja_pedidos', function (Blueprint $t) {
            if (! Schema::hasColumn('loja_pedidos', 'pix_destino')) $t->string('pix_destino', 12)->nullable(); // loja | plataforma
            if (! Schema::hasColumn('loja_pedidos', 'confirmado_em')) $t->timestamp('confirmado_em')->nullable();
            if (! Schema::hasColumn('loja_pedidos', 'admin_obs')) $t->string('admin_obs', 300)->nullable();
        });
        // pedidos antigos: o Pix deles era (e continua) na chave do dono
        DB::table('loja_pedidos')->whereNull('pix_destino')->update(['pix_destino' => 'loja']);

        if (! Schema::hasTable('loja_resgates')) {
            Schema::create('loja_resgates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->decimal('valor', 10, 2);
                $t->string('pix_tipo', 12);
                $t->string('pix_chave', 80);
                $t->string('pix_nome', 60);
                $t->string('status', 12)->default('pendente'); // pendente | pago | recusado
                $t->date('prazo');                                // 7 dias úteis depois do pedido
                $t->timestamp('pago_em')->nullable();
                $t->string('observacao', 300)->nullable();
                $t->timestamps();
                $t->index(['status', 'prazo']);
                $t->index(['user_id', 'id']);
            });
        }

        if (! Schema::hasTable('loja_saldo_movimentos')) {
            Schema::create('loja_saldo_movimentos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('tipo', 12);                              // credito | resgate | estorno | mp (informativo)
                $t->decimal('valor', 10, 2);                         // + entra, − sai
                $t->unsignedBigInteger('loja_pedido_id')->nullable();
                $t->unsignedBigInteger('loja_resgate_id')->nullable();
                $t->string('descricao', 200);
                $t->timestamps();
                $t->index(['user_id', 'id']);
                $t->unique(['tipo', 'loja_pedido_id']);              // um crédito por pedido, mesmo com dois cliques
                $t->unique(['tipo', 'loja_resgate_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loja_saldo_movimentos');
        Schema::dropIfExists('loja_resgates');
        Schema::table('loja_pedidos', function (Blueprint $t) {
            $t->dropColumn(['pix_destino', 'confirmado_em', 'admin_obs']);
        });
        Schema::table('lojas', function (Blueprint $t) {
            $t->dropSoftDeletes();
        });
        Schema::table('loja_produtos', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'nome']);
            $t->dropConstrainedForeignId('user_id');
            $t->dropColumn(['sku', 'custo', 'categoria', 'tipo']);
        });
    }
};
