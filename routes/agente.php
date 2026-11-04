<?php

use App\Http\Controllers\Api\AgenteController;
use Illuminate\Support\Facades\Route;

// API do agente do Windows (/api/agente/...). Token do aparelho no cabeçalho, sem sessão nem CSRF.
// Middleware (throttle:agente + AgenteToken) vem do AutomacaoServiceProvider.
Route::get('/ola', [AgenteController::class, 'ola'])->name('ola');
Route::get('/pendentes', [AgenteController::class, 'pendentes'])->name('pendentes');
Route::post('/execucoes/{execucao}', [AgenteController::class, 'resultado'])->whereNumber('execucao')->name('resultado');
