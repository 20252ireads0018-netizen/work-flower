<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cor_primaria', 7)->default('#2f6fc7')->after('password');
            $table->string('cor_cabecalho', 7)->default('#1a4585')->after('cor_primaria');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cor_primaria', 'cor_cabecalho']);
        });
    }
};
