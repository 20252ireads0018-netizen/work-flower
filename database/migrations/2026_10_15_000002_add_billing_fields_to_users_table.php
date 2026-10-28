<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf_cnpj', 18)->nullable()->after('email');
            $table->string('asaas_customer_id')->nullable()->index()->after('cpf_cnpj');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cpf_cnpj', 'asaas_customer_id']);
        });
    }
};

