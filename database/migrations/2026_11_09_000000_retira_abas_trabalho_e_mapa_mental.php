<?php

use App\Support\AbasRetiradas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Abas "Mapa mental" (Corpo, Mente, Carteira) e "Trabalho" (Carteira) retiradas: os cartões delas vão para o
 * Quadro livre da mesma área (App\Support\AbasRetiradas). A página também confere isso ao abrir, então quem
 * gravar um estado antigo depois desta migração (aba aberta com o JS velho) é corrigido na próxima visita.
 */
return new class extends Migration
{
    public function up(): void
    {
        $alvos = [
            ['corpo_dados', 'estado', 'corpo'],
            ['corpo_dados', 'mente.estado', 'mente'],
            ['carteira_dados', 'estado', 'carteira'],
        ];

        foreach ($alvos as [$tabela, $chave, $area]) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($tabela)) continue;

            DB::table($tabela)->where('chave', $chave)->select('id')->orderBy('id')
                ->chunk(100, function ($ids) use ($tabela, $area) {
                    foreach ($ids as $l) {
                        // uma linha por vez: o estado pode ter alguns MB
                        $valor = DB::table($tabela)->where('id', $l->id)->value('valor');
                        if (! is_string($valor)) continue;
                        $linha = new class($valor) {
                            public bool $mudou = false;
                            public function __construct(public string $valor) {}
                            public function save(): void { $this->mudou = true; }
                        };
                        if (AbasRetiradas::migrarLinha($linha, $area) && $linha->mudou) {
                            DB::table($tabela)->where('id', $l->id)->update(['valor' => $linha->valor, 'updated_at' => now()]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // sem volta: os cartões continuam no Quadro livre
    }
};
