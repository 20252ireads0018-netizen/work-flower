<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pessoas com acesso ao workflow (área Corpo, Mente, Carteira ou Diverso) de outra conta.
 * O dono convida pelo e-mail; o limite de pessoas vem do plano do dono (Planos::limite(..., 'usuarios')).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workflow_membros')) {
            return;
        }

        Schema::create('workflow_membros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dono_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membro_id')->constrained('users')->cascadeOnDelete();
            $table->string('area', 20);
            $table->timestamps();
            $table->unique(['dono_id', 'membro_id', 'area']);
            $table->index(['membro_id', 'area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_membros');
    }
};
