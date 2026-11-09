<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negócios › Integrações › Notion: um espaço de trabalho do Notion por conta, ligado pelo login do Notion (OAuth da
 * integração pública do dono do site) ou por um token de integração interna colado pela pessoa. O token fica
 * criptografado (APP_KEY) e nunca volta para o navegador. As páginas importadas viram cartões (dados no próprio
 * quadro); aqui só fica a conexão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notion_integracoes')) {
            return;
        }
        Schema::create('notion_integracoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->text('access_token');                      // criptografado
            $t->string('tipo', 10)->default('oauth');      // oauth | interno
            $t->string('workspace_id', 60)->nullable();
            $t->string('workspace_nome', 120)->nullable();
            $t->string('bot_id', 60)->nullable();
            $t->string('ultimo_erro', 255)->nullable();
            $t->timestamp('ultimo_sync_em')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notion_integracoes');
    }
};
