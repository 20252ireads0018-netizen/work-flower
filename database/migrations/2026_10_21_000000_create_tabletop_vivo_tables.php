<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uma sala por transmissão. `rev` é a revisão única: sobe 1 a cada lote de operações aceito.
        Schema::create('tabletop_salas', function (Blueprint $t) {
            $t->id();
            $t->string('codigo', 8)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('titulo', 80)->nullable();
            $t->unsignedBigInteger('rev')->default(0);
            $t->json('cfg')->nullable();                    // cols, rows, cel, grade, nomes
            $t->boolean('travada')->default(false);         // true: só o dono muda mapa/configurações
            $t->timestamp('ultima_atividade')->nullable()->index();
            $t->timestamps();
        });

        // Estado atual: uma linha por peça ou traço (evita regravar o mapa inteiro a cada movimento).
        Schema::create('tabletop_itens', function (Blueprint $t) {
            $t->id();                                       // a ordem de criação define a ordem de empilhamento
            $t->foreignId('sala_id')->constrained('tabletop_salas')->cascadeOnDelete();
            $t->string('tipo', 5);                          // 'peca' | 'des'
            $t->string('item_id', 40);
            $t->longText('dados');
            $t->unique(['sala_id', 'tipo', 'item_id']);
        });

        // Histórico recente de operações (podado: ver HIST no controller).
        Schema::create('tabletop_ops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sala_id')->constrained('tabletop_salas')->cascadeOnDelete();
            $t->unsignedBigInteger('rev');
            $t->string('cid', 40);
            $t->longText('ops');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['sala_id', 'rev']);
        });

        // Imagens (mapa de fundo 'mapa' e das peças 'i.{id}') como data URL.
        Schema::create('tabletop_imgs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sala_id')->constrained('tabletop_salas')->cascadeOnDelete();
            $t->string('chave', 48);
            $t->longText('v');
            $t->unique(['sala_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tabletop_imgs');
        Schema::dropIfExists('tabletop_ops');
        Schema::dropIfExists('tabletop_itens');
        Schema::dropIfExists('tabletop_salas');
    }
};

