<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Batimento" dos comandos agendados: cada um grava quando rodou, para a tela mostrar se o cron do cPanel
 * ("php artisan schedule:run" a cada minuto) está mesmo ativo. Guardado no cache, sem tabela nova.
 */
class Agendador
{
    public static function marcar(string $comando): void
    {
        try {
            cache()->forever('agendador.ultimo.' . $comando, now()->toIso8601String());
        } catch (\Throwable) {
            // cache fora do ar não pode derrubar o comando
        }
    }

    public static function ultimo(string $comando): ?Carbon
    {
        try {
            $v = cache()->get('agendador.ultimo.' . $comando);
        } catch (\Throwable) {
            return null;
        }

        return $v ? Carbon::parse($v) : null;
    }
}
