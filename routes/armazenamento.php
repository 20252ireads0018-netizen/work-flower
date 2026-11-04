<?php

use App\Http\Controllers\ArmazenamentoController as A;
use Illuminate\Support\Facades\Route;

/*
 * Configurações › Armazenamento (carregado por routes/web.php para herdar o grupo "web").
 * Destinos próprios (S3/R2/B2, Google Drive), backups e arquivos grandes fora do banco.
 */
Route::middleware('auth')->prefix('armazenamento')->name('armazenamento')->group(function () {
    Route::get('/', [A::class, 'index']);

    Route::post('/destinos/s3', [A::class, 'salvarS3'])->middleware('throttle:10,1,arm-s3')->name('.s3');
    Route::patch('/destinos/{destino}', [A::class, 'atualizar'])->whereNumber('destino')->middleware('throttle:30,1,arm-atualizar')->name('.atualizar');
    Route::post('/destinos/{destino}/testar', [A::class, 'testar'])->whereNumber('destino')->middleware('throttle:10,1,arm-testar')->name('.testar');
    Route::delete('/destinos/{destino}', [A::class, 'remover'])->whereNumber('destino')->middleware('throttle:10,1,arm-remover')->name('.remover');
    Route::post('/destinos/{destino}/backup', [A::class, 'backup'])->whereNumber('destino')->middleware('throttle:6,1,arm-backup')->name('.backup');

    Route::get('/google/conectar', [A::class, 'googleConectar'])->middleware('throttle:10,1,arm-google')->name('.google');
    Route::get('/google/retorno', [A::class, 'googleRetorno'])->middleware('throttle:10,1,arm-google-retorno')->name('.google.retorno');

    Route::post('/backups/{backup}/restaurar', [A::class, 'restaurar'])->whereNumber('backup')->middleware('throttle:5,1,arm-restaurar')->name('.restaurar');
    Route::get('/backups/{backup}/baixar', [A::class, 'baixarBackup'])->whereNumber('backup')->middleware('throttle:10,1,arm-baixar')->name('.baixar');

    Route::post('/arquivos/mover', [A::class, 'mover'])->middleware('throttle:10,1,arm-mover')->name('.mover');
    Route::post('/arquivos/trazer', [A::class, 'trazer'])->middleware('throttle:10,1,arm-trazer')->name('.trazer');
    Route::get('/arquivo/{token}', [A::class, 'arquivo'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:600,1,arm-arquivo')->name('.arquivo');
});
