<?php

// Rotas do Tabletop ao vivo. Este arquivo ��� carregado por routes/web.php (require __DIR__ . '/tabletop-vivo.php';),
// ent���o j��� roda com sess���o + CSRF + same-origin, como o JS espera. N���o cole este bloco de novo no web.php.

use App\Http\Controllers\TabletopVivoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('tabletop-vivo')
    ->name('tabletop-vivo.')
    ->controller(TabletopVivoController::class)
    ->where(['codigo' => '[A-Za-z0-9]{4,8}', 'chave' => 'mapa|i\.[A-Za-z0-9_\-]{1,40}'])
    ->group(function () {
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('/{codigo}', 'show')->middleware('throttle:60,1')->name('show');
        Route::delete('/{codigo}', 'destroy')->name('destroy');
        Route::patch('/{codigo}/travar', 'travar')->name('travar');

        Route::post('/{codigo}/ops', 'enviar')->middleware('throttle:600,1')->name('ops.enviar');
        Route::get('/{codigo}/ops', 'consultar')->middleware('throttle:600,1')->name('ops.consultar');

        Route::get('/{codigo}/img/{chave}', 'imgVer')->middleware('throttle:240,1')->name('img.ver');
        Route::put('/{codigo}/img/{chave}', 'imgSalvar')->middleware('throttle:60,1')->name('img.salvar');
        Route::delete('/{codigo}/img/{chave}', 'imgRemover')->middleware('throttle:60,1')->name('img.remover');
    });

// Expirar salas sem atividade:
//  - Laravel 11+: em routes/console.php
//        use Illuminate\Support\Facades\Schedule;
//        Schedule::command('tabletop:limpar-salas --dias=7')->daily();
//  - Laravel 10:  em app/Console/Kernel.php, dentro de schedule()
//        $schedule->command('tabletop:limpar-salas --dias=7')->daily();
//  (e o cron do servidor: * * * * * php /caminho/artisan schedule:run)
