<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidaturas', function (Blueprint $table) {
            $table->id();
            $table->uuid('codigo')->unique();
            $table->string('nome', 120);
            $table->string('email', 150)->index();
            $table->string('fone', 20);
            $table->string('cidade', 120);
            $table->string('escolaridade', 60);
            $table->string('exp', 60);
            $table->json('dias');
            $table->string('turno', 60);
            $table->unsignedTinyInteger('horas');
            $table->text('msg')->nullable();
            $table->timestamp('lgpd_aceito_em')->nullable();
            $table->timestamp('curso_clicado_em')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidaturas');
    }
};
