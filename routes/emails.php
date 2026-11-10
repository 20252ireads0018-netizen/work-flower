<?php

use App\Http\Controllers\EmailController;
use Illuminate\Support\Facades\Route;

// Negócios › E-mails: caixas conectadas por IMAP (ou Gmail pelo Google), lista com categorias automáticas.
// Cada limite tem prefixo próprio (3º argumento): sem ele o Laravel usa um contador só por pessoa para TODAS
// as rotas com throttle:N,M, e um limite baixo estoura com os pedidos de outras partes da página.
// Carregado no fim de routes/web.php para herdar o grupo "web" (sessão + CSRF).
Route::middleware('auth')->prefix('/negocios/emails')->name('emails')->group(function () {
    Route::get('/', [EmailController::class, 'show'])->name('');
    Route::post('/contas', [EmailController::class, 'conectar'])->middleware('throttle:10,1,emails-conectar')->name('.conectar');
    Route::delete('/contas/{conta}', [EmailController::class, 'remover'])->whereNumber('conta')->middleware('throttle:30,1,emails-remover')->name('.remover');
    Route::post('/sincronizar', [EmailController::class, 'sincronizar'])->middleware('throttle:12,1,emails-sync')->name('.sincronizar');
    Route::put('/regras', [EmailController::class, 'regras'])->middleware('throttle:30,1,emails-regras')->name('.regras');
    Route::patch('/mensagens/{mensagem}', [EmailController::class, 'categoria'])->whereNumber('mensagem')->middleware('throttle:gravar')->name('.categoria');
    Route::post('/teste-envio', [EmailController::class, 'testeEnvio'])->middleware('throttle:3,1,emails-teste')->name('.teste');
    Route::get('/google', [EmailController::class, 'google'])->middleware('throttle:10,1,emails-google')->name('.google');
    Route::get('/google/retorno', [EmailController::class, 'googleRetorno'])->middleware('throttle:10,1,emails-google')->name('.google.retorno');
    // cartão Gmail do quadro: lista filtrada e a mensagem inteira
    Route::get('/cartao', [EmailController::class, 'cartao'])->middleware('throttle:60,1,emails-cartao')->name('.cartao');
    Route::get('/mensagens/{mensagem}/corpo', [EmailController::class, 'corpo'])->whereNumber('mensagem')->middleware('throttle:30,1,emails-corpo')->name('.corpo');
});
