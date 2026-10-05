<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibliotecaController;
use App\Http\Controllers\CorpoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TemaController;
use App\Models\Tarefa;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundoController;
use App\Http\Controllers\MenteController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\DiversoController;

// A raiz decide para onde ir: login (visitante) ou painel (logado).
Route::get('/', fn () => redirect()->route(auth()->check() ? 'painel' : 'login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/registro', [AuthController::class, 'formRegistro'])->name('registro');
    Route::post('/registro', [AuthController::class, 'registro']);
});

Route::middleware('auth')->group(function () {
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');

    Route::get('/painel', [PainelController::class, 'index'])->name('painel');
    Route::put('/tema', [TemaController::class, 'update'])->name('tema.update');

    // Carteira: precisa vir ANTES da rota genérica /areas/{area}
    Route::get('/areas/carteira', [CarteiraController::class, 'show'])->name('areas.carteira');
    Route::get('/carteira/cotacao', [CarteiraController::class, 'cotacao'])->name('carteira.cotacao');
    Route::put('/carteira/dados/{chave}', [CarteiraController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('carteira.dados.salvar');
    Route::delete('/carteira/dados/{chave}', [CarteiraController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('carteira.dados.remover');

    // Diverso: precisa vir ANTES da rota genérica /areas/{area}
    Route::get('/areas/diverso', [DiversoController::class, 'show'])->name('areas.diverso');

    // corpo | mente | carteira | diverso
    Route::get('/areas/{area}', [AreaController::class, 'show'])
        ->whereIn('area', array_keys(Tarefa::AREAS))->name('areas.show');
    Route::post('/areas/{area}/tarefas', [AreaController::class, 'store'])
        ->whereIn('area', array_keys(Tarefa::AREAS))->name('areas.tarefas.store');

    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');

    // Dados da área Corpo (treinos, dieta, água, calorias, blocos livres, layout, imagens)
    Route::put('/corpo/dados/{chave}', [CorpoController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('corpo.dados.salvar');
    Route::delete('/corpo/dados/{chave}', [CorpoController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('corpo.dados.remover');

    Route::put('/fundo', [FundoController::class, 'salvar'])->name('fundo.salvar');
    Route::get('/fundo/imagem', [FundoController::class, 'imagem'])->name('fundo.imagem');

    // Biblioteca de blocos (vale para todas as páginas)
    Route::get('/biblioteca', [BibliotecaController::class, 'index'])->name('biblioteca.index');
    Route::put('/biblioteca', [BibliotecaController::class, 'salvar'])->name('biblioteca.salvar');
});

Route::middleware('auth')->prefix('mente')->name('mente.')->group(function () {
    // Estado geral e imagens (chave: "estado" ou "img.{id}")
    Route::put('/dados/{chave}', [MenteController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]+')
        ->name('salvar');

    Route::delete('/dados/{chave}', [MenteController::class, 'apagar'])
        ->where('chave', '[A-Za-z0-9._-]+')
        ->name('apagar');

    // PDFs dos livros
    Route::post('/pdf/{id}', [MenteController::class, 'enviarPdf'])
        ->where('id', '[A-Za-z0-9_-]+')
        ->name('pdf.enviar');

    Route::get('/pdf/{id}', [MenteController::class, 'pdf'])
        ->where('id', '[A-Za-z0-9_-]+')
        ->name('pdf.ver');

    Route::delete('/pdf/{id}', [MenteController::class, 'removerPdf'])
        ->where('id', '[A-Za-z0-9_-]+')
        ->name('pdf.remover');
});

// Dados da área Diverso (estado das abas e imagens; chave: "estado" ou "img.{id}")
Route::middleware('auth')->prefix('diverso')->name('diverso.')->group(function () {
    Route::put('/dados/{chave}', [DiversoController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')
        ->name('salvar');

    Route::delete('/dados/{chave}', [DiversoController::class, 'apagar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')
        ->name('apagar');
});
