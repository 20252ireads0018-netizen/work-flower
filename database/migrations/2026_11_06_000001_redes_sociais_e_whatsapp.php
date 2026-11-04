<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartão Criador: contas de Instagram e TikTok conectadas por OAuth (tokens criptografados, uma por rede e pessoa).
 * Negócios › Clientes: WhatsApp oficial (Cloud API) do dono, conversas por telefone e as mensagens de cada uma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redes_contas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('rede', 20);                 // instagram | tiktok
            $t->string('externo_id', 80)->nullable(); // id da conta do Instagram / open_id do TikTok
            $t->string('usuario', 120)->nullable();   // @ da conta
            $t->string('nome', 150)->nullable();
            $t->text('access_token');
            $t->text('refresh_token')->nullable();
            $t->timestamp('expira_em')->nullable();
            $t->timestamp('refresh_expira_em')->nullable();
            $t->json('extra')->nullable();
            $t->string('erro', 300)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'rede']);
        });

        Schema::create('zap_configs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('phone_number_id', 40)->index();
            $t->string('waba_id', 40)->nullable();
            $t->text('access_token');
            $t->text('app_secret')->nullable();     // se vazio, vale o META_APP_SECRET do site
            $t->string('verify_token', 120)->index();
            $t->string('numero', 40)->nullable();   // número exibido (só para mostrar)
            $t->timestamp('ultimo_aviso_em')->nullable();
            $t->timestamps();
        });

        Schema::create('zap_conversas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // dono da área Negócios
            $t->string('telefone', 20);             // wa_id (só dígitos, com DDI)
            $t->string('chave', 20)->index();       // DDD + 8 últimos dígitos (o WhatsApp às vezes tira o 9)
            $t->string('cliente_id', 40)->nullable(); // id do cliente no estado da área Negócios
            $t->string('nome', 150)->nullable();
            $t->timestamp('ultima_entrada_em')->nullable(); // última mensagem do cliente (janela de 24 horas)
            $t->timestamp('ultima_em')->nullable();
            $t->string('previa', 160)->nullable();
            $t->unsignedInteger('nao_lidas')->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'telefone']);
        });

        Schema::create('zap_mensagens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversa_id')->constrained('zap_conversas')->cascadeOnDelete();
            $t->string('direcao', 3);               // in | out
            $t->string('tipo', 20)->default('text');
            $t->text('texto')->nullable();
            $t->string('wamid', 191)->nullable()->unique();
            $t->string('status', 20)->nullable();   // sent | delivered | read | failed | recebida
            $t->string('erro', 300)->nullable();
            $t->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('em');
            $t->timestamps();
            $t->index(['conversa_id', 'em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zap_mensagens');
        Schema::dropIfExists('zap_conversas');
        Schema::dropIfExists('zap_configs');
        Schema::dropIfExists('redes_contas');
    }
};
