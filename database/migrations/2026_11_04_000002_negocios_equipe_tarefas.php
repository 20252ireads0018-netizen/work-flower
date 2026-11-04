<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - negocio_dados: a nova área Negócios (vendas, clientes, documentos), com dados e compartilhamento próprios.
 * - workflow_membros: o dono libera ou tira a edição de cada pessoa e pode bloquear o acesso.
 * - tarefas: cor, categoria e responsável (tarefa delegada a alguém do workflow), para o quadro de tarefas.
 * - workflow_atividades: o que cada pessoa mudou em cada workflow (aba Equipe).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('negocio_dados')) {
            Schema::create('negocio_dados', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('chave', 60);              // "estado" ou "img.{id}"
                $t->longText('valor')->nullable();
                $t->timestamps();
                $t->unique(['user_id', 'chave']);
            });
        }

        Schema::table('workflow_membros', function (Blueprint $t) {
            if (! Schema::hasColumn('workflow_membros', 'pode_editar')) {
                $t->boolean('pode_editar')->default(true);
            }
            if (! Schema::hasColumn('workflow_membros', 'bloqueado')) {
                $t->boolean('bloqueado')->default(false);
            }
        });

        Schema::table('tarefas', function (Blueprint $t) {
            if (! Schema::hasColumn('tarefas', 'categoria')) {
                $t->string('categoria', 40)->nullable();
            }
            if (! Schema::hasColumn('tarefas', 'cor')) {
                $t->string('cor', 7)->nullable();
            }
            if (! Schema::hasColumn('tarefas', 'responsavel_id')) {
                $t->foreignId('responsavel_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('workflow_atividades')) {
            Schema::create('workflow_atividades', function (Blueprint $t) {
                $t->id();
                $t->foreignId('dono_id')->constrained('users')->cascadeOnDelete();
                $t->string('area', 20);
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('acao', 200);
                $t->string('aba', 80)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['dono_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_atividades');
        Schema::table('tarefas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('responsavel_id');
            $t->dropColumn(['categoria', 'cor']);
        });
        Schema::table('workflow_membros', function (Blueprint $t) {
            $t->dropColumn(['pode_editar', 'bloqueado']);
        });
        Schema::dropIfExists('negocio_dados');
    }
};
