<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Lembretes do calendário por e-mail. No servidor precisa do cron do cPanel:
// * * * * * php /caminho/do/projeto/artisan schedule:run >> /dev/null 2>&1
Schedule::command('calendario:lembrar')->everyMinute()->withoutOverlapping(10);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

