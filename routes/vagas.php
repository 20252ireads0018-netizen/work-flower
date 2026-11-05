<?php

use App\Http\Controllers\CandidaturaController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Página de vagas (pacote "vagas-laravel"). No ar em https://websight.com.br/vagas: o index.php da raiz do site
// manda /vagas para este app. Carregado no fim do routes/web.php (grupo "web").
Route::prefix('vagas')->name('vagas.')->group(function () {
    Route::get('/', [CandidaturaController::class, 'index'])->name('index');
    // Sem conferência de CSRF: em /vagas (fora de /work-flower) o navegador não manda o cookie de sessão do app
    // (SESSION_PATH=/work-flower), então o token nunca bateria (419). O formulário é público, sem login;
    // a proteção é o limite de envios e o campo-isca.
    Route::post('/candidaturas', [CandidaturaController::class, 'store'])
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->middleware('throttle:10,1')
        ->name('store');
    Route::get('/curso', [CandidaturaController::class, 'curso'])->name('curso');
    Route::get('/admin', [CandidaturaController::class, 'admin'])->middleware('throttle:30,1')->name('admin');
});
