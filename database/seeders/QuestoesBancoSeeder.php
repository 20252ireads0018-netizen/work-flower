<?php

namespace Database\Seeders;

use App\Support\QuestoesBanco;
use Illuminate\Database\Seeder;

/** Recarrega a tabela questoes_banco a partir de database/data/questoes/questoes.jsonl.gz (fontes no README da pasta). */
class QuestoesBancoSeeder extends Seeder
{
    public function run(): void
    {
        $n = QuestoesBanco::importar();
        $this->command?->info("{$n} questões importadas para questoes_banco.");
    }
}
