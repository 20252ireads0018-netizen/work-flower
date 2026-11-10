<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Importação manual (CSV ou números colados) para quem ainda não conectou a conta por OAuth:
 * - redes_manuais: o último retrato do Instagram/TikTok de cada pessoa (perfil, posts e histórico de seguidores)
 * - mkt_contas.manual: conta de marketplace alimentada por planilha (não sincroniza e não conta no limite do plano)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('redes_manuais')) {
            Schema::create('redes_manuais', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('rede', 20);              // instagram | tiktok
                $t->string('usuario', 120)->nullable();
                $t->json('conta')->nullable();       // seguidores, seguindo, posts/vídeos, curtidas
                $t->json('itens')->nullable();       // posts ou vídeos com as métricas
                $t->json('historico')->nullable();   // { 'AAAA-MM-DD': seguidores }
                $t->timestamps();
                $t->unique(['user_id', 'rede']);
            });
        }

        if (Schema::hasTable('mkt_contas') && ! Schema::hasColumn('mkt_contas', 'manual')) {
            Schema::table('mkt_contas', function (Blueprint $t) {
                $t->boolean('manual')->default(false)->after('demo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mkt_contas', 'manual')) {
            Schema::table('mkt_contas', fn (Blueprint $t) => $t->dropColumn('manual'));
        }
        Schema::dropIfExists('redes_manuais');
    }
};
