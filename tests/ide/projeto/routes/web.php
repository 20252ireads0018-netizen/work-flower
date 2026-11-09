<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('posts.index'));
Route::resource('posts', PostController::class);
Route::get('/ola/{nome?}', fn (?string $nome = 'mundo') => "Olá, $nome!")->name('ola');
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/painel', fn () => ['total' => \App\Models\Post::count()])->name('painel');
});
