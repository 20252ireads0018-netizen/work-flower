<?php

use App\Http\Controllers\InstagramPublicarController;
use Illuminate\Support\Facades\Route;

/*
 * "Publicar no Instagram" do cartão Criador (public/js/criador-redes.js). Carregado por routes/web.php para herdar o
 * grupo "web". A imagem sai por link assinado sem login (o Instagram baixa por ele) e é apagada depois da publicação.
 */
Route::middleware('auth')->prefix('/redes/instagram/publicar')->name('redes.instagram.publicar')
    ->controller(InstagramPublicarController::class)->group(function () {
        Route::get('/', 'estado')->middleware('throttle:30,1,ig-pub-estado')->name('');
        Route::post('/', 'publicar')->middleware('throttle:6,1,ig-publicar')->name('.enviar');
    });

Route::get('/redes/instagram/imagem/{arquivo}', [InstagramPublicarController::class, 'imagem'])
    ->where('arquivo', '[0-9a-f-]{36}\.jpg')->middleware(['signed', 'throttle:60,1,ig-imagem'])->name('redes.instagram.imagem');
