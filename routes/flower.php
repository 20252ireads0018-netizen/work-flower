<?php

use App\Http\Controllers\FlowerController;
use Illuminate\Support\Facades\Route;

// "Meu Flower": mapa mental de todo o workflow da conta (só leitura). Carregado no fim do routes/web.php (grupo "web").
Route::middleware('auth')->prefix('flower')->name('flower')->controller(FlowerController::class)->group(function () {
    Route::get('/', 'show')->name('');
    Route::get('/dados', 'dados')->middleware('throttle:60,1')->name('.dados');
});
