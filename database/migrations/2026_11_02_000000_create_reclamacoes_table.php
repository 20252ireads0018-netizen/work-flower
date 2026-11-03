<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Canal "Reclame aqui": reclamações, sugestões e problemas enviados pelas pessoas, com a resposta da equipe. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reclamacoes')) {
            return;
        }
        Schema::create('reclamacoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('tipo', 20);            // reclamacao | sugestao | problema | elogio
            $t->string('assunto', 150);
            $t->text('mensagem');
            $t->string('pagina', 300)->nullable(); // de onde a pessoa veio
            $t->string('status', 20)->default('aberta'); // aberta | respondida | resolvida
            $t->text('resposta')->nullable();
            $t->timestamp('respondida_em')->nullable();
            $t->boolean('resposta_vista')->default(true);
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamacoes');
    }
};
