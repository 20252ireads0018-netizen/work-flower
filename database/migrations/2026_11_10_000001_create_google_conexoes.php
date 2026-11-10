<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexões com serviços do Google feitas pelo login do Google que não têm tabela própria (hoje: YouTube, para o
 * cartão "YouTube" do quadro ler o canal da pessoa). Drive e Gmail continuam em armazenamento_destinos e email_contas.
 * Tokens criptografados (APP_KEY), nunca voltam para o navegador. Uma conexão por serviço e conta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_conexoes')) {
            return;
        }
        Schema::create('google_conexoes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('servico', 20);                     // youtube
            $t->string('email', 190)->nullable();
            $t->text('access_token')->nullable();          // criptografado
            $t->text('refresh_token')->nullable();         // criptografado
            $t->timestamp('expira_em')->nullable();
            $t->json('dados')->nullable();                 // ex.: canal (id, nome)
            $t->timestamps();
            $t->unique(['user_id', 'servico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_conexoes');
    }
};
