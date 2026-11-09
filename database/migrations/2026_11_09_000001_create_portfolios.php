<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negócios › Portfólio: página pública de cada conta em /p/{slug}, com seções, tema e SEO num JSON (App\Models\Portfolio),
 * e as mensagens do formulário "Fale comigo" que os visitantes mandam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('slug', 40)->unique();
            $t->boolean('publicado')->default(false);
            $t->longText('dados')->nullable();          // JSON: perfil, tema, seo e seções (Portfolio::normalizar)
            $t->unsignedInteger('visitas')->default(0);
            $t->timestamps();
        });

        Schema::create('portfolio_mensagens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $t->string('nome', 120);
            $t->string('contato', 160);                 // e-mail ou WhatsApp
            $t->string('assunto', 160)->nullable();
            $t->text('mensagem');
            $t->boolean('lida')->default(false);
            $t->string('ip_hash', 64)->nullable();
            $t->timestamps();
            $t->index(['portfolio_id', 'lida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_mensagens');
        Schema::dropIfExists('portfolios');
    }
};
