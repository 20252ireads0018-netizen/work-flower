<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se você já criou essa tabela, confira só se `valor` é longText e se existe o índice único.
        Schema::create('corpo_dados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chave', 60);
            $table->longText('valor'); // o estado inteiro e as imagens (base64) passam de 64 KB
            $table->timestamps();

            $table->unique(['user_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corpo_dados');
    }
};