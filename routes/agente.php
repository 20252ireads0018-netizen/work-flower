<?php

use App\Http\Controllers\Api\AgenteController;
use Illuminate\Support\Facades\Route;

// API do agente do Windows (/api/agente/...). Token do aparelho no cabeçalho, sem sessão nem CSRF.
// Middleware (throttle:agente + AgenteToken) vem do AutomacaoServiceProvider.
Route::get('/ola', [AgenteController::class, 'ola'])->name('ola');
Route::get('/pendentes', [AgenteController::class, 'pendentes'])->name('pendentes');
Route::post('/execucoes/{execucao}', [AgenteController::class, 'resultado'])->whereNumber('execucao')->name('resultado');
// textos rápidos da conta para o expansor no Windows inteiro, e o pacote do agente para o instalador do app
Route::get('/trechos', [AgenteController::class, 'trechos'])->name('trechos');
Route::get('/pacote', [AgenteController::class, 'pacote'])->name('pacote');
// versão do pacote: o agente instalado compara com a dele e se atualiza sozinho quando o site muda
Route::get('/versao', [AgenteController::class, 'versao'])->name('versao');
