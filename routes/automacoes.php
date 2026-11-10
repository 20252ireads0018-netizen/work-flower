<?php

use App\Http\Controllers\AutomacaoController;
use Illuminate\Support\Facades\Route;

// Automações no computador (página do site). Carregado pelo AutomacaoServiceProvider dentro do grupo "web".
Route::middleware('auth')->prefix('automacoes')->name('automacoes.')->group(function () {
    Route::get('/', [AutomacaoController::class, 'index'])->name('index');
    Route::get('/historico', [AutomacaoController::class, 'historico'])->middleware('throttle:60,1')->name('historico');
    // sem ".zip" no endereço: o .htaccess do servidor bloqueia URLs terminadas em .zip (403); o nome do arquivo vem no Content-Disposition
    Route::get('/agente/baixar', [AutomacaoController::class, 'baixarAgente'])->middleware('throttle:10,1')->name('agente');

    Route::post('/', [AutomacaoController::class, 'salvar'])->middleware('throttle:60,1')->name('criar');
    Route::post('/{automacao}', [AutomacaoController::class, 'salvar'])->whereNumber('automacao')->middleware('throttle:60,1')->name('salvar');
    Route::post('/{automacao}/alternar', [AutomacaoController::class, 'alternar'])->whereNumber('automacao')->middleware('throttle:60,1')->name('alternar');
    Route::post('/{automacao}/rodar', [AutomacaoController::class, 'rodar'])->whereNumber('automacao')->middleware('throttle:30,1')->name('rodar');
    Route::delete('/{automacao}', [AutomacaoController::class, 'remover'])->whereNumber('automacao')->middleware('throttle:60,1')->name('remover');

    // instalação de um clique (.cmd com código de uso único), situação dos computadores e notificação de teste
    Route::get('/agente/instalar', [\App\Http\Controllers\AppInstalavelController::class, 'instaladorAgente'])->middleware('throttle:10,1')->name('instalador');
    Route::get('/computadores', [AutomacaoController::class, 'computadores'])->middleware('throttle:60,1')->name('computadores');
    Route::post('/computadores/{dispositivo}/testar', [AutomacaoController::class, 'testar'])->whereNumber('dispositivo')->middleware('throttle:10,1')->name('testar');
    Route::post('/computadores', [AutomacaoController::class, 'parear'])->middleware('throttle:10,1')->name('parear');
    Route::post('/computadores/{dispositivo}/revogar', [AutomacaoController::class, 'revogar'])->whereNumber('dispositivo')->middleware('throttle:20,1')->name('revogar');
    Route::delete('/historico', [AutomacaoController::class, 'limparHistorico'])->middleware('throttle:20,1')->name('historico.limpar');
});
