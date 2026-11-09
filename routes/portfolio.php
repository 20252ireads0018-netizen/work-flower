<?php

use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PortfolioPublicoController;
use Illuminate\Support\Facades\Route;

// Portfólio profissional (Negócios › Portfólio). Incluído pelo routes/web.php.

// Página pública: /p/{slug}, aberta para qualquer pessoa quando publicada
Route::prefix('/p/{slug}')->where(['slug' => '[a-z0-9-]{3,40}'])->name('portfolio.')->group(function () {
    Route::get('/', [PortfolioPublicoController::class, 'ver'])->middleware('throttle:120,1')->name('ver');
    Route::post('/contato', [PortfolioPublicoController::class, 'contato'])->middleware('throttle:6,1')->name('contato');
    Route::get('/m/{token}', [PortfolioPublicoController::class, 'midia'])
        ->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:300,1')->name('midia');
});

// Editor (só a conta logada)
Route::middleware('auth')->prefix('/negocios/portfolio')->name('portfolio.')->group(function () {
    Route::get('/', [PortfolioController::class, 'show'])->name('editor');
    Route::post('/', [PortfolioController::class, 'salvar'])->middleware('throttle:60,1')->name('salvar');
    Route::post('/previa', [PortfolioController::class, 'previa'])->middleware('throttle:240,1')->name('previa');
    Route::post('/mensagens/{mensagem}/lida', [PortfolioController::class, 'lida'])->whereNumber('mensagem')->middleware('throttle:60,1')->name('lida');
    Route::delete('/mensagens/{mensagem}', [PortfolioController::class, 'apagarMensagem'])->whereNumber('mensagem')->middleware('throttle:60,1')->name('mensagem.apagar');
    Route::delete('/', [PortfolioController::class, 'excluir'])->middleware('throttle:10,1')->name('excluir');
});
