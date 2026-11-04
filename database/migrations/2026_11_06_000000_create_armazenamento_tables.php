<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Armazenamento customizado: cada conta conecta destinos próprios (bucket S3/R2/B2 ou Google Drive) para
 * backups dos workflows e para guardar fora do banco do site as imagens e PDFs grandes.
 *
 *   armazenamento_destinos  -> destinos conectados (credenciais criptografadas em "credenciais")
 *   armazenamento_backups   -> backups enviados (onde ficou, tamanho, áreas)
 *   armazenamento_arquivos  -> imagens/PDFs movidos para o destino; o site guarda só a referência (token)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('armazenamento_destinos')) {
            Schema::create('armazenamento_destinos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('tipo', 10);                      // s3 | gdrive
                $t->string('nome', 60);
                $t->text('credenciais');                     // JSON criptografado
                $t->string('resumo', 190)->nullable();       // texto sem segredo para a tela (bucket, conta Google)
                $t->boolean('backup_diario')->default(false);
                $t->timestamp('ultimo_backup_em')->nullable();
                $t->string('erro', 300)->nullable();
                $t->timestamps();
                $t->index(['user_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('armazenamento_backups')) {
            Schema::create('armazenamento_backups', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('destino_id')->constrained('armazenamento_destinos')->cascadeOnDelete();
                $t->string('remoto', 300);                   // chave do objeto (S3) ou id do arquivo (Drive)
                $t->string('nome', 120);
                $t->unsignedBigInteger('tamanho')->default(0);
                $t->string('areas', 120)->nullable();
                $t->string('origem', 10)->default('manual'); // manual | diario
                $t->timestamps();
                $t->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('armazenamento_arquivos')) {
            Schema::create('armazenamento_arquivos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('destino_id')->constrained('armazenamento_destinos')->cascadeOnDelete();
                $t->string('token', 40)->unique();
                $t->string('tipo', 10);                      // img | pdf
                $t->string('tabela', 30);                    // corpo_dados, negocio_dados... (pdf: mente_pdf)
                $t->string('chave', 120);                    // chave da linha (img) ou id do PDF
                $t->string('remoto', 300);
                $t->string('mime', 60);
                $t->unsignedBigInteger('tamanho')->default(0);
                $t->timestamps();
                $t->index(['user_id', 'tabela', 'chave']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('armazenamento_arquivos');
        Schema::dropIfExists('armazenamento_backups');
        Schema::dropIfExists('armazenamento_destinos');
    }
};
