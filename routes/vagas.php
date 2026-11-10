<?php

use App\Http\Controllers\CandidaturaController;
use App\Http\Controllers\VagasCartoesController;
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
    // compra opcional do curso (R$ 19,90, Pix na chave da plataforma; confirmado em /admin/pix). Mesmo motivo do CSRF acima.
    Route::post('/curso/pedido', [CandidaturaController::class, 'pedidoCurso'])
        ->withoutMiddleware(ValidateCsrfToken::class)->middleware('throttle:20,1')->name('curso.pedido');
    Route::post('/curso/paguei', [CandidaturaController::class, 'cursoPaguei'])
        ->withoutMiddleware(ValidateCsrfToken::class)->middleware('throttle:10,1')->name('curso.paguei');
    Route::get('/curso', [CandidaturaController::class, 'curso'])->name('curso');
    Route::get('/admin', [CandidaturaController::class, 'admin'])->middleware('throttle:30,1')->name('admin');
});

// Vagas em cartões (deslizar para a direita = candidatar com o portfólio /p/{slug}; esquerda = descartar).
// Precisa da conta logada: no ar, use o endereço dentro do app (APP_URL/vagas/cartoes), onde o cookie de sessão vale.
Route::middleware('auth')->prefix('vagas/cartoes')->name('vagas.cartoes')->controller(VagasCartoesController::class)->group(function () {
    Route::get('/', 'index')->name('');
    Route::post('/publicar', 'publicar')->middleware('throttle:20,1')->name('.publicar');
    Route::post('/rever', 'rever')->middleware('throttle:10,1')->name('.rever');
    Route::post('/{vaga}/responder', 'responder')->whereNumber('vaga')->middleware('throttle:120,1')->name('.responder');
    Route::post('/{vaga}/desfazer', 'desfazer')->whereNumber('vaga')->middleware('throttle:60,1')->name('.desfazer');
    Route::post('/{vaga}/alternar', 'alternar')->whereNumber('vaga')->middleware('throttle:30,1')->name('.alternar');
    Route::delete('/{vaga}', 'excluir')->whereNumber('vaga')->middleware('throttle:30,1')->name('.excluir');
    Route::post('/respostas/{resposta}/status', 'status')->whereNumber('resposta')->middleware('throttle:120,1')->name('.status');
});
