<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vagas em cartões (/vagas/cartoes): vagas de emprego publicadas pelas contas (ou pela plataforma, user_id nulo) e a
 * resposta de cada pessoa a cada vaga: deslizou para a direita (candidatou, com o portfólio /p/{slug} como currículo)
 * ou para a esquerda (descartou). A empresa acompanha as candidaturas e muda o status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vagas_ofertas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // nulo = vaga da própria plataforma
            $t->string('titulo', 120);
            $t->string('empresa', 120);
            $t->string('cidade', 120)->nullable();
            $t->string('modalidade', 20)->default('presencial');  // presencial | hibrido | remoto
            $t->string('regime', 20)->default('CLT');             // CLT | PJ | Estágio | Temporário | Freelancer | Jovem aprendiz
            $t->string('salario', 80)->nullable();
            $t->text('descricao');
            $t->text('requisitos')->nullable();
            $t->text('beneficios')->nullable();
            $t->string('link', 500)->nullable();                  // página externa da vaga (opcional)
            $t->boolean('ativa')->default(true);
            $t->timestamps();
            $t->index(['ativa', 'created_at']);
        });

        Schema::create('vagas_respostas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vaga_id')->constrained('vagas_ofertas')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('acao', 12);                               // candidatou | descartou
            $t->string('portfolio_slug', 40)->nullable();         // currículo: /p/{slug} no momento da candidatura
            $t->text('mensagem')->nullable();
            $t->string('status', 12)->default('enviada');         // enviada | vista | entrevista | recusada | contratada
            $t->timestamps();
            $t->unique(['vaga_id', 'user_id']);
            $t->index(['user_id', 'acao']);
        });

        // A vaga da própria Work Flower (a mesma da página /vagas) entra como primeiro cartão.
        DB::table('vagas_ofertas')->insert([
            'user_id' => null,
            'titulo' => 'Assistente de organização (home-office)',
            'empresa' => 'Work Flower',
            'cidade' => '100% remoto',
            'modalidade' => 'remoto',
            'regime' => 'Freelancer',
            'salario' => 'Por hora disponível',
            'descricao' => 'Organize a rotina de pessoas e equipes nas áreas Corpo, Mente, Carteira e Diverso, em meio período. Você informa os dias e horários em que fica disponível e as tarefas chegam dentro dessa janela.',
            'requisitos' => "Computador e internet estável em casa\nEscrita clara e retorno dentro do horário combinado\nCuidado com prazos e com organização de arquivos",
            'beneficios' => 'Trabalho de casa · horário combinado com você',
            'link' => null,
            'ativa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vagas_respostas');
        Schema::dropIfExists('vagas_ofertas');
    }
};
