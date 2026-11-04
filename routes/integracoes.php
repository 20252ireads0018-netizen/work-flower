<?php

use App\Http\Controllers\CallController;
use App\Http\Controllers\IntegracaoController;
use Illuminate\Support\Facades\Route;

/*
 * Negócios › Integrações (Discord) e Negócios › Calls. Carregado dentro do grupo "auth" de routes/web.php.
 * Sempre sobre a conta de quem está logado (não sobre o workflow aberto).
 */
Route::prefix('/integracoes/discord')->name('integracoes.discord')->group(function () {
    Route::get('/', [IntegracaoController::class, 'show'])->middleware('throttle:60,1')->name('');
    Route::post('/', [IntegracaoController::class, 'salvar'])->middleware('throttle:20,1')->name('.salvar');
    Route::post('/testar', [IntegracaoController::class, 'testar'])->middleware('throttle:6,1')->name('.testar');
    Route::get('/canais', [IntegracaoController::class, 'canais'])->middleware('throttle:20,1')->name('.canais');
    Route::delete('/', [IntegracaoController::class, 'remover'])->middleware('throttle:20,1')->name('.remover');
});

Route::prefix('/calls')->name('calls')->group(function () {
    Route::get('/', [CallController::class, 'index'])->middleware('throttle:60,1')->name('');
    Route::post('/', [CallController::class, 'criar'])->middleware('throttle:30,1')->name('.criar');
    Route::put('/{call}', [CallController::class, 'atualizar'])->whereNumber('call')->middleware('throttle:gravar')->name('.atualizar');
    Route::delete('/{call}', [CallController::class, 'apagar'])->whereNumber('call')->middleware('throttle:gravar')->name('.apagar');
});
