<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Negócios › E-mails: caixas de entrada conectadas (IMAP ou Gmail), as mensagens recentes lidas delas
 * e as regras de categoria de cada pessoa. Senhas e tokens ficam criptografados (cast "encrypted").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 10)->default('imap');           // imap | gmail
            $table->string('endereco', 190);                       // e-mail mostrado na tela
            $table->string('host', 190)->nullable();
            $table->unsignedSmallInteger('porta')->nullable();
            $table->string('seguranca', 10)->nullable();           // ssl | starttls
            $table->string('usuario', 190)->nullable();
            $table->text('senha')->nullable();                     // criptografada
            $table->text('access_token')->nullable();              // criptografado (Gmail)
            $table->text('refresh_token')->nullable();             // criptografado (Gmail)
            $table->timestamp('token_expira_em')->nullable();
            $table->string('pasta', 120)->default('INBOX');
            $table->string('uidvalidity', 40)->nullable();
            $table->timestamp('sincronizado_em')->nullable();
            $table->string('erro', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('email_mensagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conta_id')->constrained('email_contas')->cascadeOnDelete();
            $table->string('uid', 100);                            // UID do IMAP ou id da mensagem no Gmail
            $table->string('message_id', 250)->nullable();
            $table->string('de_nome', 190)->nullable();
            $table->string('de_email', 190)->nullable();
            $table->string('assunto', 300)->nullable();
            $table->string('trecho', 300)->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->boolean('lido')->default(false);
            $table->string('categoria', 40)->default('Outros');
            $table->boolean('categoria_manual')->default(false);
            $table->timestamps();
            $table->unique(['conta_id', 'uid']);
            $table->index(['user_id', 'recebido_em']);
        });

        Schema::create('email_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('categoria', 40);
            $table->string('campo', 12)->default('qualquer');     // remetente | assunto | qualquer
            $table->string('termo', 120);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_regras');
        Schema::dropIfExists('email_mensagens');
        Schema::dropIfExists('email_contas');
    }
};
