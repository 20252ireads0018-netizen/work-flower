<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banco de questões abertas (OAB/FGV e ENEM/INEP) do cartão "Questões de concurso".
 * Os dados vêm de database/data/questoes/questoes.jsonl.gz (fontes e licenças no README da pasta).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questoes_banco')) {
            return;
        }
        Schema::create('questoes_banco', function (Blueprint $t) {
            $t->id();
            $t->string('banca', 40);
            $t->string('prova', 80);
            $t->unsignedSmallInteger('ano');
            $t->string('materia', 60);
            $t->string('assunto', 60);
            $t->string('dificuldade', 8); // facil | media | dificil (estimada)
            $t->text('enunciado');
            $t->text('alts');               // JSON com 5 alternativas (vazias quando a prova tem 4)
            $t->unsignedTinyInteger('certa');
            $t->string('fonte', 10);
            $t->string('ref', 40)->unique();
            $t->index(['banca', 'materia', 'assunto']);
            $t->index(['ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questoes_banco');
    }
};
