<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // estado -> abas, blocos e layout | img.<id> -> imagem de um bloco
        Schema::create('diverso_dados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('chave', 60);
            $table->longText('valor')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diverso_dados');
    }
};
