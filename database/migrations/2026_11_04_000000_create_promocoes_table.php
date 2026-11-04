<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promoções relâmpago dos planos (admin em /admin/promocoes) e eventos do calendário do painel.
 * - promocoes: preço baixo por um prazo; o Pix gerado durante a promoção sai com esse valor.
 * - eventos: datas e horários (hora local de Brasília), com repetição e lembrete no navegador e por e-mail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promocoes')) {
            Schema::create('promocoes', function (Blueprint $t) {
                $t->id();
                $t->string('plano_slug', 30);
                $t->decimal('preco', 10, 2);
                $t->string('titulo', 80)->nullable();
                $t->timestamp('comeca_em')->nullable();
                $t->timestamp('termina_em')->nullable();
                $t->timestamp('encerrada_em')->nullable(); // "Encerrar agora" antes do prazo
                $t->timestamps();
                $t->index(['plano_slug', 'termina_em']);
            });
        }

        if (! Schema::hasTable('eventos')) {
            Schema::create('eventos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('titulo', 120);
                $t->text('descricao')->nullable();
                $t->dateTime('inicio');                 // hora de Brasília, sem fuso
                $t->dateTime('fim')->nullable();
                $t->boolean('dia_todo')->default(false);
                $t->string('cor', 7)->default('#2f6fc7');
                $t->string('repete', 10)->default('nao'); // nao | diario | semanal | mensal | anual
                $t->date('repete_ate')->nullable();
                $t->unsignedInteger('lembrete_min')->nullable(); // minutos antes; null = sem lembrete
                $t->boolean('lembrar_email')->default(false);
                $t->dateTime('email_enviado_para')->nullable(); // última ocorrência que já recebeu o e-mail
                $t->timestamps();
                $t->index(['user_id', 'inicio']);
                $t->index(['lembrar_email', 'lembrete_min']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promocoes');
        Schema::dropIfExists('eventos');
    }
};
