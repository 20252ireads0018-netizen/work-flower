<?php

use App\Http\Controllers\GoogleDriveController;
use App\Http\Controllers\LembretesController;
use App\Http\Controllers\YoutubeController;
use Illuminate\Support\Facades\Route;

/*
 * Cartões de integração do quadro (public/js/integracoes-cartoes.js): Google Drive, YouTube e Lembretes por e-mail.
 * O cartão Gmail usa as rotas de routes/emails.php. Carregado por routes/web.php para herdar o grupo "web".
 * Cada limite tem prefixo próprio (3º argumento do throttle), como nos outros arquivos de rotas.
 */
Route::middleware('auth')->group(function () {
    Route::prefix('/google/drive')->name('google.drive')->controller(GoogleDriveController::class)->group(function () {
        Route::get('/', 'lista')->middleware('throttle:60,1,gd-lista')->name('');
        Route::get('/arquivos/{id}', 'arquivo')->where('id', '[A-Za-z0-9_-]{10,100}')->middleware('throttle:60,1,gd-arquivo')->name('.arquivo');
        Route::patch('/arquivos/{id}', 'renomear')->where('id', '[A-Za-z0-9_-]{10,100}')->middleware('throttle:30,1,gd-renomear')->name('.renomear');
        Route::post('/arquivos', 'enviar')->middleware('throttle:20,1,gd-enviar')->name('.enviar');
        Route::post('/backup', 'backup')->middleware('throttle:6,1,gd-backup')->name('.backup');
        Route::patch('/diario', 'diario')->middleware('throttle:20,1,gd-diario')->name('.diario');
    });

    Route::prefix('/youtube')->name('youtube.')->controller(YoutubeController::class)->group(function () {
        Route::get('/cartao', 'cartao')->middleware('throttle:40,1,yt-cartao')->name('cartao');
        Route::get('/google', 'conectar')->middleware('throttle:10,1,yt-google')->name('google');
        Route::get('/google/retorno', 'retorno')->middleware('throttle:10,1,yt-google')->name('google.retorno');
        Route::delete('/google', 'desconectar')->middleware('throttle:10,1,yt-google')->name('google.sair');
    });

    Route::prefix('/lembretes')->name('lembretes.')->controller(LembretesController::class)->group(function () {
        Route::get('/cartao', 'cartao')->middleware('throttle:60,1,lb-cartao')->name('cartao');
        Route::post('/email', 'todos')->middleware('throttle:10,1,lb-todos')->name('todos');
        Route::post('/teste', 'teste')->middleware('throttle:3,10,lb-teste')->name('teste');
        Route::patch('/{evento}', 'email')->whereNumber('evento')->middleware('throttle:gravar')->name('email');
    });
});
