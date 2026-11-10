<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banco de questões: área (ENEM, Direito, Tecnologia, Administração, Português, Raciocínio lógico, Saúde) e
 * comentário do gabarito (as questões inéditas do Work Flower trazem comentário próprio).
 * A tabela é esvaziada: QuestoesBanco::garantir() importa tudo de novo (com as áreas) na primeira busca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questoes_banco')) {
            return;
        }
        Schema::table('questoes_banco', function (Blueprint $t) {
            if (! Schema::hasColumn('questoes_banco', 'area')) {
                $t->string('area', 40)->default('')->after('id');
                $t->index(['area', 'materia']);
            }
            if (! Schema::hasColumn('questoes_banco', 'comentario')) {
                $t->text('comentario')->nullable()->after('certa');
            }
        });
        DB::table('questoes_banco')->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('questoes_banco')) {
            return;
        }
        Schema::table('questoes_banco', function (Blueprint $t) {
            if (Schema::hasColumn('questoes_banco', 'area')) {
                $t->dropIndex(['area', 'materia']);
                $t->dropColumn('area');
            }
            if (Schema::hasColumn('questoes_banco', 'comentario')) {
                $t->dropColumn('comentario');
            }
        });
    }
};
