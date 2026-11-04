<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Lembretes do calendário por e-mail. No servidor precisa do cron do cPanel:
// * * * * * php /caminho/do/projeto/artisan schedule:run >> /dev/null 2>&1
Schedule::command('calendario:lembrar')->everyMinute()->withoutOverlapping(10);

// Backup diário dos workflows nos destinos das contas (Configurações › Armazenamento)
Schedule::command('armazenamento:backup-diario')->dailyAt('03:30')->withoutOverlapping(120);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// E-mails conectados (Negócios › E-mails): lê as caixas a cada 15 minutos.
Schedule::command('emails:sincronizar')->everyFifteenMinutes()->withoutOverlapping(30);
