<?php

namespace App\Console\Commands;

use App\Models\EmailConta;
use App\Support\Emails\EmailErro;
use App\Support\Emails\Sincronizar;
use Illuminate\Console\Command;

/**
 * Lê de novo as caixas de e-mail conectadas (Negócios › E-mails). Roda pelo agendador (routes/console.php),
 * que no servidor depende do cron "php artisan schedule:run" no cPanel. Caixas com erro de login
 * esperam a pessoa conectar de novo, para não ficar batendo no servidor com a senha errada.
 */
class SincronizarEmails extends Command
{
    protected $signature = 'emails:sincronizar {--todas : inclui as caixas que estão com erro}';
    protected $description = 'Lê as mensagens recentes das caixas de e-mail conectadas.';

    public function handle(): int
    {
        $ok = 0;
        $falhas = 0;
        EmailConta::query()
            ->when(! $this->option('todas'), fn ($q) => $q->where(fn ($w) => $w->whereNull('erro')->orWhere('erro', 'not like', '%senha%')))
            ->orderBy('sincronizado_em')
            ->each(function (EmailConta $c) use (&$ok, &$falhas) {
                try {
                    Sincronizar::conta($c);
                    $ok++;
                } catch (EmailErro) {
                    $falhas++;
                }
            });
        \App\Support\Agendador::marcar($this->getName());
        $this->info("Caixas lidas: {$ok}. Com falha: {$falhas}.");

        return self::SUCCESS;
    }
}
