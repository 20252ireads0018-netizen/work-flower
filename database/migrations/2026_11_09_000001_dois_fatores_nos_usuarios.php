<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verificação em duas etapas (app autenticador): segredo e códigos de recuperação ficam criptografados (APP_KEY).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('dois_fatores_segredo')->nullable();
            $table->text('dois_fatores_recuperacao')->nullable();
            $table->timestamp('dois_fatores_ativo_em')->nullable();
            $table->unsignedBigInteger('dois_fatores_ultimo_passo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dois_fatores_segredo', 'dois_fatores_recuperacao', 'dois_fatores_ativo_em', 'dois_fatores_ultimo_passo']);
        });
    }
};
