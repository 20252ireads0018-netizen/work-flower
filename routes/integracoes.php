<?php

use App\Http\Controllers\CallController;
use App\Http\Controllers\IntegracaoController;
use Illuminate\Support\Facades\Route;

/*
 * Negócios › Integrações (Discord) e Negócios › Calls. Carregado dentro do grupo "auth" de routes/web.php.
 * Sempre sobre a conta de quem está logado (não sobre o workflow aberto).
 */
Route::prefix('/integracoes/discord')->name('integracoes.discord')->group(function () {
    Route::get('/', [IntegracaoController::class, 'show'])->middleware('throttle:60,1')->name('');
    Route::post('/', [IntegracaoController::class, 'salvar'])->middleware('throttle:20,1')->name('.salvar');
    Route::post('/testar', [IntegracaoController::class, 'testar'])->middleware('throttle:6,1')->name('.testar');
    // conectar com um clique: o Discord pergunta o servidor e o canal e devolve o webhook pronto
    Route::get('/conectar', [IntegracaoController::class, 'conectar'])->middleware('throttle:10,1')->name('.conectar');
    Route::get('/retorno', [IntegracaoController::class, 'retorno'])->middleware('throttle:10,1')->name('.retorno');
    // mensagem do bloco Discord do quadro (public/js/blocos-integracoes.js)
    Route::post('/enviar', [IntegracaoController::class, 'enviar'])->middleware('throttle:10,1,dc-enviar')->name('.enviar');
    Route::get('/canais', [IntegracaoController::class, 'canais'])->middleware('throttle:20,1')->name('.canais');
    Route::delete('/', [IntegracaoController::class, 'remover'])->middleware('throttle:20,1')->name('.remover');
});

Route::prefix('/calls')->name('calls')->group(function () {
    Route::get('/', [CallController::class, 'index'])->middleware('throttle:60,1')->name('');
    Route::post('/', [CallController::class, 'criar'])->middleware('throttle:30,1')->name('.criar');
    Route::put('/{call}', [CallController::class, 'atualizar'])->whereNumber('call')->middleware('throttle:gravar')->name('.atualizar');
    Route::delete('/{call}', [CallController::class, 'apagar'])->whereNumber('call')->middleware('throttle:gravar')->name('.apagar');
});

// Notion: conexão (login pelo Notion ou token interno), lista de páginas e conteúdo para importar/sincronizar cartões
Route::prefix('/integracoes/notion')->name('integracoes.notion')->controller(\App\Http\Controllers\NotionController::class)->group(function () {
    Route::get('/', 'show')->middleware('throttle:60,1')->name('');
    Route::post('/token', 'salvarToken')->middleware('throttle:10,1')->name('.token');
    Route::get('/conectar', 'conectar')->middleware('throttle:10,1')->name('.conectar');
    Route::get('/retorno', 'retorno')->middleware('throttle:10,1')->name('.retorno');
    Route::delete('/', 'remover')->middleware('throttle:20,1')->name('.remover');
    Route::get('/paginas', 'paginas')->middleware('throttle:30,1')->name('.paginas');
    Route::post('/conteudo', 'conteudo')->middleware('throttle:30,1')->name('.conteudo');
    // mini Notion no cartão (public/js/blocos-integracoes.js): abrir, editar blocos, criar, renomear, mover e arquivar páginas
    $nid = '[0-9a-fA-F-]{32,36}';
    Route::get('/paginas/{id}', 'pagina')->where('id', $nid)->middleware('throttle:60,1,nt-pagina')->name('.pagina');
    Route::get('/bancos/{id}/linhas', 'linhas')->where('id', $nid)->middleware('throttle:60,1,nt-linhas')->name('.linhas');
    Route::post('/paginas', 'criarPagina')->middleware('throttle:30,1,nt-criar')->name('.criar');
    Route::patch('/paginas/{id}', 'renomear')->where('id', $nid)->middleware('throttle:30,1,nt-renomear')->name('.renomear');
    Route::patch('/paginas/{id}/arquivar', 'arquivar')->where('id', $nid)->middleware('throttle:30,1,nt-arquivar')->name('.arquivar');
    Route::patch('/paginas/{id}/mover', 'mover')->where('id', $nid)->middleware('throttle:30,1,nt-mover')->name('.mover');
    Route::post('/paginas/{id}/blocos', 'novoBloco')->where('id', $nid)->middleware('throttle:120,1,nt-bloco')->name('.bloco.novo');
    Route::patch('/blocos/{id}', 'editarBloco')->where('id', $nid)->middleware('throttle:120,1,nt-bloco')->name('.bloco.editar');
    Route::delete('/blocos/{id}', 'apagarBloco')->where('id', $nid)->middleware('throttle:60,1,nt-bloco-x')->name('.bloco.apagar');
});
