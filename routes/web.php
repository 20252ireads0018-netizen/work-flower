<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BibliotecaController;
use App\Http\Controllers\CorpoController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TemaController;
use App\Models\Tarefa;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FundoController;
use App\Http\Controllers\MenteController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\DiversoController;
use App\Http\Controllers\WorkflowTransferController;
use App\Http\Controllers\PreferenciasController;
use App\Http\Controllers\CompartilharController;

Route::get('/diag-rotas', function () {
    if (function_exists('opcache_reset')) { @opcache_reset(); }

    $arquivo = base_path('routes/web.php');
    $todas   = collect(Route::getRoutes()->getRoutes());

    return response()->json([
        'laravel'          => app()->version(),
        'base_path'        => base_path(),
        'public_path'      => public_path(),
        'web_php_mtime'    => date('Y-m-d H:i:s', filemtime($arquivo)),
        'web_php_tem_rota' => str_contains(file_get_contents($arquivo), 'downloads/registrar'),
        'rotas_em_cache'   => app()->routesAreCached(),
        'rotas_downloads'  => $todas
            ->filter(fn ($r) => str_contains($r->uri(), 'downloads'))
            ->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())
            ->values(),
        'total_de_rotas'   => $todas->count(),
    ]);
});

// Webhook do Asaas (sem login e sem CSRF; a autenticação é o token no header "asaas-access-token")
Route::post('/webhooks/asaas', AsaasWebhookController::class)
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, // nome do CSRF no Laravel 13
    ])
    ->middleware('throttle:120,1')
    ->name('webhooks.asaas');

// Páginas públicas (abrem para qualquer pessoa, logada ou não).
// A raiz mostra a página inicial para visitantes e leva quem está logado ao painel.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('painel')
        : view('publico.inicio');
})->name('inicio');

Route::view('/privacidade', 'publico.privacidade')->name('privacidade');
Route::view('/termos', 'publico.termos')->name('termos');
Route::view('/contato', 'publico.contato')->name('contato');

// Animação de apresentação (rolagem), aberta pela tela de login. Pública, para visitantes.
Route::view('/conheca', 'conheca')->name('demo');

// PDF com os motivos para usar o Work Flower, baixado pelos botões da apresentação.
Route::get('/conheca/motivos.pdf', fn () => response(\App\Support\MotivosPdf::gerar(), 200, [
    'Content-Type'        => 'application/pdf',
    'Content-Disposition' => 'attachment; filename="por-que-usar-o-work-flower.pdf"',
    'Cache-Control'       => 'public, max-age=3600',
]))->middleware('throttle:30,1')->name('demo.motivos');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/registro', [AuthController::class, 'formRegistro'])->name('registro');
    Route::post('/registro', [AuthController::class, 'registro']);
});

Route::middleware('auth')->group(function () {
    Route::get('/assinar/{slug}/dados', [AssinaturaController::class, 'dados'])
        ->where('slug', '[a-z0-9-]+')->name('assinar.dados');
    Route::post('/assinar/{slug}/dados', [AssinaturaController::class, 'salvarDados'])
        ->where('slug', '[a-z0-9-]+')->name('assinar.dados.salvar');
    Route::view('/planos', 'planos')->name('planos');
    Route::get('/assinar/{slug}', [AssinaturaController::class, 'assinar'])
        ->where('slug', '[a-z0-9-]+')->name('assinar');
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('/painel', [PainelController::class, 'index'])->name('painel');

    // Vídeo curto vertical de divulgação: só para as contas em User::VIDEO_CURTO (as outras recebem 404)
    Route::get('/video-curto', function () {
        abort_unless(auth()->user()->veVideoCurto(), 404);

        return view('video-curto');
    })->name('video.curto');
    Route::put('/tema', [TemaController::class, 'update'])->name('tema.update');

    // Contador de downloads do botão de baixar dos cartões (limite do plano gratuito)
    Route::post('/downloads/registrar', [DownloadController::class, 'registrar'])
        ->middleware('throttle:60,1')->name('downloads.registrar');

    // Carteira: precisa vir ANTES da rota genérica /areas/{area}
    Route::get('/areas/carteira', [CarteiraController::class, 'show'])->name('areas.carteira');
    Route::get('/carteira/cotacao', [CarteiraController::class, 'cotacao'])->name('carteira.cotacao');
    Route::put('/carteira/dados/{chave}', [CarteiraController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('carteira.dados.salvar');
    Route::delete('/carteira/dados/{chave}', [CarteiraController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->name('carteira.dados.remover');

    // Carteira › Marketplaces (Mercado Livre, Shopee, Amazon). Limite de contas vem do plano do dono.
    Route::prefix('/carteira/marketplaces')->name('carteira.mkt')->group(function () {
        Route::get('/', [MarketplaceController::class, 'show'])->name('');
        Route::get('/dados', [MarketplaceController::class, 'dados'])->name('.dados');
        Route::get('/conectar/{canal}', [MarketplaceController::class, 'conectar'])
            ->whereIn('canal', ['mercadolivre', 'shopee', 'amazon'])->middleware('throttle:20,1')->name('.conectar');
        Route::get('/retorno/{canal}', [MarketplaceController::class, 'retorno'])
            ->whereIn('canal', ['mercadolivre', 'shopee', 'amazon'])->middleware('throttle:20,1')->name('.retorno');
        Route::post('/contas/demo', [MarketplaceController::class, 'demo'])->middleware('throttle:20,1')->name('.demo');
        Route::patch('/contas', [MarketplaceController::class, 'editarContas'])->name('.contas.editar');
        Route::delete('/contas', [MarketplaceController::class, 'removerContas'])->name('.contas.remover');
        Route::post('/sincronizar', [MarketplaceController::class, 'sincronizar'])->middleware('throttle:30,1')->name('.sincronizar');
        Route::put('/produtos', [MarketplaceController::class, 'salvarProdutos'])->name('.produtos.salvar');
        Route::delete('/produtos', [MarketplaceController::class, 'removerProdutos'])->name('.produtos.remover');
        Route::post('/anuncios/acao', [MarketplaceController::class, 'acaoAnuncios'])->middleware('throttle:60,1')->name('.anuncios.acao');
        Route::patch('/anuncios', [MarketplaceController::class, 'editarAnuncios'])->name('.anuncios.editar');
    });

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

    // Preferências da conta: teclas de atalho, organização automática dos cartões, tutorial visto
    Route::put('/preferencias', [PreferenciasController::class, 'salvar'])
        ->middleware('throttle:60,1')->name('preferencias.salvar');
    Route::get('/fundo/imagem', [FundoController::class, 'imagem'])->name('fundo.imagem');

    // Workflows compartilhados: convidar pessoas para uma área (limite de pessoas vem do plano do dono)
    Route::get('/compartilhar/{area}', [CompartilharController::class, 'index'])->name('compartilhar.index');
    Route::post('/compartilhar/{area}', [CompartilharController::class, 'adicionar'])
        ->middleware('throttle:20,1')->name('compartilhar.adicionar');
    Route::delete('/compartilhar/{area}/sair/{dono}', [CompartilharController::class, 'sair'])
        ->whereNumber('dono')->name('compartilhar.sair');
    Route::delete('/compartilhar/{area}/{membro}', [CompartilharController::class, 'remover'])
        ->whereNumber('membro')->name('compartilhar.remover');
    // o que as outras pessoas do workflow mudaram (o navegador confere a cada poucos segundos)
    Route::get('/sincronia/{area}', [CompartilharController::class, 'sincronia'])
        ->middleware('throttle:120,1')->name('sincronia');
    // mudanças das outras pessoas em qualquer workflow compartilhado (aviso discreto em outras páginas)
    Route::get('/sincronia', [CompartilharController::class, 'novidades'])
        ->middleware('throttle:30,1')->name('sincronia.novidades');

    // Biblioteca de blocos (vale para todas as páginas)
    Route::get('/biblioteca', [BibliotecaController::class, 'index'])->name('biblioteca.index');
    Route::put('/biblioteca', [BibliotecaController::class, 'salvar'])->name('biblioteca.salvar');

    // Importar e exportar workflows (arquivo .workflower.json, serve para levar para outra conta)
    Route::prefix('workflows')->name('workflows.')->group(function () {
        Route::get('/lista', [WorkflowTransferController::class, 'lista'])->name('lista');
        Route::get('/exportar', [WorkflowTransferController::class, 'exportar'])->name('exportar');
        Route::post('/importar', [WorkflowTransferController::class, 'importar'])
            ->middleware('throttle:20,1')->name('importar');
    });
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

// Tabletop ao vivo (salas com código). O arquivo é carregado aqui para herdar o grupo "web" (sessão + CSRF).
require __DIR__ . '/tabletop-vivo.php';
