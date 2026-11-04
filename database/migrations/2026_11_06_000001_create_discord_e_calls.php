<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrações (área Negócios › Integrações) e Calls (Negócios › Calls).
 * - discord_integracoes: um servidor do Discord por conta, pelo webhook que a pessoa cola (e, se quiser, o token de
 *   um bot para listar os canais). Webhook e token ficam criptografados (APP_KEY). "eventos" guarda o que avisar.
 * - calls: reunião marcada com título, data, duração, link e participantes (ids de pessoas da equipe).
 * - eventos.call_id: cada participante ganha um evento no calendário do painel, apagado junto com a call.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discord_integracoes')) {
            Schema::create('discord_integracoes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $t->text('webhook_url')->nullable();   // criptografado
                $t->text('bot_token')->nullable();     // criptografado
                $t->string('guild_id', 30)->nullable();
                $t->string('canal_id', 30)->nullable();
                $t->string('canal_nome', 100)->nullable();
                $t->string('servidor_nome', 100)->nullable();
                $t->json('eventos')->nullable();
                $t->string('ultimo_erro', 255)->nullable();
                $t->timestamp('ultimo_envio_em')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('calls')) {
            Schema::create('calls', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('titulo', 120);
                $t->text('descricao')->nullable();
                $t->dateTime('inicio');                       // hora de Brasília, sem fuso (como os eventos)
                $t->unsignedSmallInteger('duracao_min')->default(30);
                $t->string('plataforma', 10)->default('jitsi'); // meet | zoom | discord | jitsi | outro
                $t->string('link', 500);
                $t->json('participantes')->nullable();        // ids de usuários da equipe
                $t->unsignedInteger('lembrete_min')->nullable();
                $t->timestamps();
                $t->index(['user_id', 'inicio']);
            });
        }

        if (Schema::hasTable('eventos') && ! Schema::hasColumn('eventos', 'call_id')) {
            Schema::table('eventos', function (Blueprint $t) {
                $t->foreignId('call_id')->nullable()->after('user_id')->constrained('calls')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos', 'call_id')) {
            Schema::table('eventos', function (Blueprint $t) {
                $t->dropConstrainedForeignId('call_id');
            });
        }
        Schema::dropIfExists('calls');
        Schema::dropIfExists('discord_integracoes');
    }
};
