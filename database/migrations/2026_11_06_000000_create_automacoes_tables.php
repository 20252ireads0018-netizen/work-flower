<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automações no computador: aparelhos pareados (agente do Windows), automações (gatilho → ações)
 * e o histórico de execuções que o agente busca e responde pela API /api/agente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_dispositivos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('nome', 60);
            $t->char('token_hash', 64)->unique();   // sha256 do token; o token em si só aparece uma vez
            $t->string('token_fim', 6);             // últimos caracteres, para a pessoa reconhecer
            $t->timestamp('ultimo_contato_em')->nullable();
            $t->string('versao_agente', 20)->nullable();
            $t->timestamp('revogado_em')->nullable();
            $t->timestamps();
        });

        Schema::create('automacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('dispositivo_id')->nullable()->constrained('automacao_dispositivos')->nullOnDelete();
            $t->string('nome', 80);
            $t->boolean('ativa')->default(true);
            $t->string('gatilho', 20);              // horario | intervalo | tarefa_criada | tarefa_concluida | manual
            $t->json('gatilho_config')->nullable(); // {hora, dias} | {minutos} | {area}
            $t->json('acoes');                      // [{tipo, ...}]
            $t->timestamp('proxima_em')->nullable(); // próximo disparo dos gatilhos de horário
            $t->timestamp('ultima_em')->nullable();
            $t->timestamps();
            $t->index(['gatilho', 'ativa', 'proxima_em']);
        });

        Schema::create('automacao_execucoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('automacao_id')->nullable()->constrained('automacoes')->nullOnDelete();
            $t->foreignId('dispositivo_id')->constrained('automacao_dispositivos')->cascadeOnDelete();
            $t->string('nome', 80);                 // nome da automação no momento do disparo
            $t->string('origem', 160);              // "Horário 08:00", "Tarefa concluída: ..."
            $t->json('acoes');                      // cópia das ações (com {tarefa} etc. já trocados)
            $t->string('status', 12)->default('pendente'); // pendente | enviada | ok | erro | recusada | expirada
            $t->text('resultado')->nullable();
            $t->timestamp('enviada_em')->nullable();
            $t->timestamp('terminada_em')->nullable();
            $t->timestamps();
            $t->index(['dispositivo_id', 'status']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_execucoes');
        Schema::dropIfExists('automacoes');
        Schema::dropIfExists('automacao_dispositivos');
    }
};
