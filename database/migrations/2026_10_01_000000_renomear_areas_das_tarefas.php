<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $mapa = [
        'cabeca'     => 'mente',
        'financeiro' => 'carteira',
        'trabalho'   => 'diverso',
    ];

    public function up(): void
    {
        foreach ($this->mapa as $de => $para) {
            DB::table('tarefas')->where('area', $de)->update(['area' => $para]);
        }
    }

    public function down(): void
    {
        foreach ($this->mapa as $de => $para) {
            DB::table('tarefas')->where('area', $para)->update(['area' => $de]);
        }
    }
};