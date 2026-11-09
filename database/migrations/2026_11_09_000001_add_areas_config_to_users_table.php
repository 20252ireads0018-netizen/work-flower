<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Áreas de cada conta (App\Support\AreasConta): ordem, áreas escondidas, nomes trocados e áreas criadas. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'areas_config')) return;
        Schema::table('users', function (Blueprint $t) {
            $t->text('areas_config')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'areas_config')) return;
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('areas_config');
        });
    }
};
