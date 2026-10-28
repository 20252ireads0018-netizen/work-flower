<?php

namespace App\Console\Commands;

use App\Models\TabletopSala;
use Illuminate\Console\Command;

class LimparSalasTabletop extends Command
{
    protected $signature = 'tabletop:limpar-salas {--dias=7 : Dias sem atividade}';
    protected $description = 'Remove as salas ao vivo do tabletop sem atividade (peças, traços, histórico e imagens).';

    public function handle(): int
    {
        $limite = now()->subDays(max(1, (int) $this->option('dias')));
        $n = 0;

        TabletopSala::query()
            ->where(function ($q) use ($limite) {
                $q->where('ultima_atividade', '<', $limite)
                  ->orWhere(fn ($q2) => $q2->whereNull('ultima_atividade')->where('created_at', '<', $limite));
            })
            ->chunkById(100, function ($salas) use (&$n) {
                foreach ($salas as $sala) { $sala->delete(); $n++; }
            });

        $this->info("{$n} sala(s) removida(s).");
        return self::SUCCESS;
    }
}

