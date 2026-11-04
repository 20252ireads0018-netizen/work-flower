<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AssinaturaController;
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

// Mini lojas (Carteira › Loja): vitrine pública, pedido e Pix na chave do dono da loja. Abertas para qualquer pessoa.
Route::prefix('/loja/{slug}')->where(['slug' => '[a-z0-9-]{3,40}'])->name('loja.')->group(function () {
    Route::get('/', [\App\Http\Controllers\LojaPublicaController::class, 'ver'])->middleware('throttle:120,1')->name('ver');
    Route::post('/pedido', [\App\Http\Controllers\LojaPublicaController::class, 'pedir'])->middleware('throttle:10,1')->name('pedir');
    Route::get('/pedido/{codigo}', [\App\Http\Controllers\LojaPublicaController::class, 'pedido'])
        ->where('codigo', 'LJ[A-Z0-9]{14}')->middleware('throttle:60,1')->name('pedido');
    Route::post('/pedido/{codigo}/paguei', [\App\Http\Controllers\LojaPublicaController::class, 'paguei'])
        ->where('codigo', 'LJ[A-Z0-9]{14}')->middleware('throttle:20,1')->name('paguei');
    Route::get('/cupom', [\App\Http\Controllers\LojaPublicaController::class, 'cupom'])->middleware('throttle:30,1')->name('cupom');
    Route::post('/pedido/{codigo}/cartao', [\App\Http\Controllers\LojaPublicaController::class, 'pagarMp'])
        ->where('codigo', 'LJ[A-Z0-9]{14}')->middleware('throttle:20,1')->name('cartao');
    // aviso do Mercado Pago (vem do servidor deles, sem token de formulário; o pagamento é relido na API)
    Route::post('/mp/aviso', [\App\Http\Controllers\LojaPublicaController::class, 'avisoMp'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
        ->middleware('throttle:120,1')->name('mp.aviso');
    Route::get('/img/{arquivo}', [\App\Http\Controllers\LojaPublicaController::class, 'imagem'])
        ->where('arquivo', '[A-Za-z0-9]{24}\.(jpg|png|webp)')->middleware('throttle:300,1')->name('img');
});

// ---- WhatsApp oficial (feature/redes): webhook da Cloud API da Meta, sem CSRF; a assinatura X-Hub-Signature-256 é conferida no ZapController
Route::get('/whatsapp/webhook', [\App\Http\Controllers\ZapController::class, 'verificar'])->middleware('throttle:60,1')->name('zap.webhook');
Route::post('/whatsapp/webhook', [\App\Http\Controllers\ZapController::class, 'receber'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->middleware('throttle:600,1')->name('zap.webhook.receber');
// ---- fim WhatsApp oficial (webhook)

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/registro', [AuthController::class, 'formRegistro'])->name('registro');
    Route::post('/registro', [AuthController::class, 'registro']);
});

Route::middleware('auth')->group(function () {
    Route::view('/planos', 'planos')->name('planos');

    // Pagamento dos planos: Pix direto na chave da administração, confirmado à mão em /admin/pix
    Route::get('/assinar/{slug}', [AssinaturaController::class, 'assinar'])
        ->where('slug', '[a-z0-9-]+')->middleware('throttle:20,1')->name('assinar');
    Route::get('/pagamento/pix/{pix}', [AssinaturaController::class, 'ver'])->whereNumber('pix')->name('pix.ver');
    Route::post('/pagamento/pix/{pix}/paguei', [AssinaturaController::class, 'paguei'])
        ->whereNumber('pix')->middleware('throttle:20,1')->name('pix.paguei');
    Route::post('/pagamento/pix/{pix}/cancelar', [AssinaturaController::class, 'cancelar'])
        ->whereNumber('pix')->middleware('throttle:20,1')->name('pix.cancelar');
    Route::middleware(\App\Http\Middleware\SoAdmin::class)->group(function () {
        Route::post('/admin/novos-usuarios/visto', [PainelController::class, 'novosVistos'])->middleware('throttle:30,1')->name('admin.novos.visto');
        Route::get('/admin/pix',[\App\Http\Controllers\PixAdminController::class, 'index'])->name('pix.admin');
        Route::post('/admin/pix/config', [\App\Http\Controllers\PixAdminController::class, 'config'])->name('pix.admin.config');
        Route::post('/admin/pix/{pix}/confirmar', [\App\Http\Controllers\PixAdminController::class, 'confirmar'])
            ->whereNumber('pix')->name('pix.admin.confirmar');
        Route::post('/admin/pix/{pix}/recusar', [\App\Http\Controllers\PixAdminController::class, 'recusar'])
            ->whereNumber('pix')->name('pix.admin.recusar');
        // Pix dos pedidos das mini lojas (chave da plataforma) e resgates do saldo dos donos
        Route::post('/admin/pix/lojas/{pedido}/confirmar', [\App\Http\Controllers\PixAdminController::class, 'confirmarPedidoLoja'])
            ->whereNumber('pedido')->name('pix.admin.loja.confirmar');
        Route::post('/admin/pix/lojas/{pedido}/recusar', [\App\Http\Controllers\PixAdminController::class, 'recusarPedidoLoja'])
            ->whereNumber('pedido')->name('pix.admin.loja.recusar');
        Route::post('/admin/pix/resgates/{resgate}/pagar', [\App\Http\Controllers\PixAdminController::class, 'pagarResgate'])
            ->whereNumber('resgate')->name('pix.admin.resgate.pagar');
        Route::post('/admin/pix/resgates/{resgate}/recusar', [\App\Http\Controllers\PixAdminController::class, 'recusarResgate'])
            ->whereNumber('resgate')->name('pix.admin.resgate.recusar');
        // Promoções relâmpago dos planos
        Route::get('/admin/promocoes', [\App\Http\Controllers\PromocaoAdminController::class, 'index'])->name('promocoes.admin');
        Route::post('/admin/promocoes', [\App\Http\Controllers\PromocaoAdminController::class, 'criar'])
            ->middleware('throttle:30,1')->name('promocoes.admin.criar');
        Route::post('/admin/promocoes/{promocao}/encerrar', [\App\Http\Controllers\PromocaoAdminController::class, 'encerrar'])
            ->whereNumber('promocao')->middleware('throttle:30,1')->name('promocoes.admin.encerrar');
    });
    Route::post('/sair', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('/painel', [PainelController::class, 'index'])->name('painel');

    // Calendário do painel (eventos com repetição e lembretes no navegador e por e-mail)
    Route::prefix('/calendario')->name('calendario.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CalendarioController::class, 'lista'])->middleware('throttle:120,1')->name('lista');
        Route::get('/lembretes', [\App\Http\Controllers\CalendarioController::class, 'lembretes'])->middleware('throttle:30,1')->name('lembretes');
        Route::post('/eventos', [\App\Http\Controllers\CalendarioController::class, 'criar'])->middleware('throttle:60,1')->name('criar');
        Route::put('/eventos/{evento}', [\App\Http\Controllers\CalendarioController::class, 'atualizar'])
            ->whereNumber('evento')->middleware('throttle:gravar')->name('atualizar');
        Route::delete('/eventos/{evento}', [\App\Http\Controllers\CalendarioController::class, 'apagar'])
            ->whereNumber('evento')->middleware('throttle:gravar')->name('apagar');
    });

    // Animações curtas (9:16) para anúncios, com download em MP4: só as contas em Anuncios::CONTAS; as outras recebem 404
    Route::get('/admin/anuncios', function () {
        abort_unless(\App\Support\Anuncios::podeVer(auth()->user()), 404);

        return view('anuncios.index');
    })->middleware(\App\Http\Middleware\SoAdmin::class)->name('anuncios.index');
    Route::get('/admin/anuncios/{slug}', function (string $slug) {
        abort_unless(\App\Support\Anuncios::podeVer(auth()->user()) && isset(\App\Support\Anuncios::LISTA[$slug]), 404);

        return view('anuncios.' . $slug);
    })->where('slug', '[a-z-]+')->middleware(\App\Http\Middleware\SoAdmin::class)->name('anuncios.ver');
    Route::put('/tema', [TemaController::class, 'update'])->middleware('throttle:30,1')->name('tema.update');

    // Contador de downloads do botão de baixar dos cartões (limite do plano gratuito)
    Route::post('/downloads/registrar', [DownloadController::class, 'registrar'])
        ->middleware('throttle:60,1')->name('downloads.registrar');

    // Cartão "Editor de PDF": o PDF original fica em disco (storage/app/editorpdf), enviado em partes de até 3 MB
    Route::prefix('editor-pdf/{area}/{arquivo}')->where(['area' => '[a-z]{3,12}', 'arquivo' => '[A-Za-z0-9_-]{1,80}'])
        ->name('editorpdf.')->group(function () {
            Route::post('/parte', [\App\Http\Controllers\EditorPdfController::class, 'parte'])->middleware('throttle:gravar')->name('parte');
            Route::post('/concluir', [\App\Http\Controllers\EditorPdfController::class, 'concluir'])->middleware('throttle:gravar')->name('concluir');
            Route::get('/', [\App\Http\Controllers\EditorPdfController::class, 'ver'])->name('ver');
            Route::delete('/', [\App\Http\Controllers\EditorPdfController::class, 'remover'])->middleware('throttle:gravar')->name('remover');
        });

    // Carteira: precisa vir ANTES da rota genérica /areas/{area}
    Route::get('/areas/carteira', [CarteiraController::class, 'show'])->name('areas.carteira');
    Route::get('/carteira/cotacao', [CarteiraController::class, 'cotacao'])->middleware('throttle:30,1')->name('carteira.cotacao');
    Route::put('/carteira/dados/{chave}', [CarteiraController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('carteira.dados.salvar');
    Route::delete('/carteira/dados/{chave}', [CarteiraController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('carteira.dados.remover');

    // Negócios: vendas, clientes, documentos, loja, marketplaces e equipe. Precisa vir ANTES de /areas/{area}
    Route::get('/areas/negocios', [\App\Http\Controllers\NegociosController::class, 'show'])->name('areas.negocios');
    Route::put('/negocios/dados/{chave}', [\App\Http\Controllers\NegociosController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('negocios.dados.salvar');
    Route::delete('/negocios/dados/{chave}', [\App\Http\Controllers\NegociosController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('negocios.dados.remover');
    Route::get('/negocios/clientes-da-loja', [\App\Http\Controllers\NegociosController::class, 'clientesDaLoja'])
        ->middleware('throttle:30,1')->name('negocios.clientes.loja');
    // Negócios › Produtos: catálogo da conta (o mesmo da mini loja), venda com preço e baixa de estoque, pedidos da loja como vendas
    Route::prefix('/negocios/produtos')->name('negocios.produtos')->controller(\App\Http\Controllers\ProdutosController::class)->group(function () {
        Route::get('/', 'lista')->middleware('throttle:60,1')->name('');
        Route::post('/', 'salvar')->middleware('throttle:60,1')->name('.criar');
        Route::post('/{produto}', 'salvar')->whereNumber('produto')->middleware('throttle:60,1')->name('.salvar');
        Route::delete('/{produto}', 'remover')->whereNumber('produto')->middleware('throttle:60,1')->name('.remover');
        Route::post('/{produto}/venda', 'venda')->whereNumber('produto')->middleware('throttle:120,1')->name('.venda');
        Route::post('/{produto}/estorno', 'estorno')->whereNumber('produto')->middleware('throttle:120,1')->name('.estorno');
        Route::get('/{produto}/foto/{arquivo}', 'foto')->whereNumber('produto')->middleware('throttle:240,1')->name('.foto');
    });
    Route::get('/negocios/vendas-da-loja', [\App\Http\Controllers\ProdutosController::class, 'vendasDaLoja'])
        ->middleware('throttle:30,1')->name('negocios.vendas.loja');

    // ---- WhatsApp oficial em Negócios › Clientes e Instagram/TikTok no cartão Criador (feature/redes)
    Route::prefix('/negocios/whatsapp')->name('zap.')->controller(\App\Http\Controllers\ZapController::class)->group(function () {
        Route::get('/', 'estado')->middleware('throttle:120,1')->name('estado');
        Route::post('/config', 'salvarConfig')->middleware('throttle:10,1')->name('config');
        Route::delete('/config', 'removerConfig')->middleware('throttle:10,1')->name('config.remover');
        Route::get('/conversa', 'conversa')->middleware('throttle:120,1')->name('conversa');
        Route::get('/modelos', 'modelos')->middleware('throttle:30,1')->name('modelos');
        Route::post('/enviar', 'enviar')->middleware('throttle:60,1')->name('enviar');
    });
    Route::prefix('/redes/{rede}')->whereIn('rede', ['instagram', 'tiktok'])->name('redes.')->controller(\App\Http\Controllers\RedesController::class)->group(function () {
        Route::get('/conectar', 'conectar')->middleware('throttle:20,1')->name('conectar');
        Route::get('/retorno', 'retorno')->middleware('throttle:20,1')->name('retorno');
        Route::get('/metricas', 'metricas')->middleware('throttle:30,1')->name('metricas');
        Route::delete('/', 'sair')->middleware('throttle:20,1')->name('sair');
    });
    // ---- fim feature/redes

    // Equipe (aba da área Negócios): pessoas dos meus workflows, permissões, bloqueio e histórico
    Route::get('/equipe', [\App\Http\Controllers\EquipeController::class, 'index'])->middleware('throttle:60,1')->name('equipe');
    Route::patch('/equipe/{membro}/{area}', [\App\Http\Controllers\EquipeController::class, 'alterar'])
        ->whereNumber('membro')->where('area', '[a-z]+')->middleware('throttle:60,1')->name('equipe.alterar');

    // Negócios › Integrações (Discord) e Negócios › Calls
    require __DIR__ . '/integracoes.php';

    // Carteira › Loja: mini loja da conta (aparência, Pix, produtos e pedidos). Página pública em /loja/{slug}.
    Route::prefix('/carteira/loja')->name('carteira.loja')->group(function () {
        Route::get('/', [\App\Http\Controllers\LojaController::class, 'show'])->name('');
        Route::post('/', [\App\Http\Controllers\LojaController::class, 'salvar'])->middleware('throttle:30,1')->name('.salvar');
        Route::post('/pix', [\App\Http\Controllers\LojaController::class, 'pix'])->middleware('throttle:20,1')->name('.pix');
        Route::post('/produtos', [\App\Http\Controllers\LojaController::class, 'salvarProduto'])->middleware('throttle:60,1')->name('.produtos.criar');
        Route::post('/produtos/{produto}', [\App\Http\Controllers\LojaController::class, 'salvarProduto'])
            ->whereNumber('produto')->middleware('throttle:60,1')->name('.produtos.salvar');
        Route::delete('/produtos/{produto}', [\App\Http\Controllers\LojaController::class, 'removerProduto'])
            ->whereNumber('produto')->middleware('throttle:60,1')->name('.produtos.remover');
        Route::post('/pedidos/{pedido}/status', [\App\Http\Controllers\LojaController::class, 'statusPedido'])
            ->whereNumber('pedido')->middleware('throttle:60,1')->name('.pedidos.status');
        Route::post('/cupons', [\App\Http\Controllers\LojaController::class, 'salvarCupom'])->middleware('throttle:60,1')->name('.cupons.criar');
        Route::post('/cupons/{cupom}', [\App\Http\Controllers\LojaController::class, 'salvarCupom'])
            ->whereNumber('cupom')->middleware('throttle:60,1')->name('.cupons.salvar');
        Route::delete('/cupons/{cupom}', [\App\Http\Controllers\LojaController::class, 'removerCupom'])
            ->whereNumber('cupom')->middleware('throttle:60,1')->name('.cupons.remover');
        Route::post('/entrega', [\App\Http\Controllers\LojaController::class, 'entrega'])->middleware('throttle:30,1')->name('.entrega');
        Route::post('/mercadopago', [\App\Http\Controllers\LojaController::class, 'mercadoPago'])->middleware('throttle:10,1')->name('.mp');
        Route::delete('/mercadopago', [\App\Http\Controllers\LojaController::class, 'desconectarMp'])->middleware('throttle:10,1')->name('.mp.sair');
        Route::delete('/', [\App\Http\Controllers\LojaController::class, 'excluir'])->middleware('throttle:10,1')->name('.excluir');
        Route::post('/resgates', [\App\Http\Controllers\LojaController::class, 'resgatar'])->middleware('throttle:10,1')->name('.resgatar');
    });

    // Carteira › Marketplaces (Mercado Livre, Shopee, Amazon). Limite de contas vem do plano do dono.
    Route::prefix('/carteira/marketplaces')->name('carteira.mkt')->group(function () {
        Route::get('/', [MarketplaceController::class, 'show'])->name('');
        Route::get('/dados', [MarketplaceController::class, 'dados'])->name('.dados');
        Route::get('/conectar/{canal}', [MarketplaceController::class, 'conectar'])
            ->whereIn('canal', ['mercadolivre', 'shopee', 'amazon'])->middleware('throttle:20,1')->name('.conectar');
        Route::get('/retorno/{canal}', [MarketplaceController::class, 'retorno'])
            ->whereIn('canal', ['mercadolivre', 'shopee', 'amazon'])->middleware('throttle:20,1')->name('.retorno');
        Route::post('/contas/demo', [MarketplaceController::class, 'demo'])->middleware('throttle:20,1')->name('.demo');
        Route::patch('/contas', [MarketplaceController::class, 'editarContas'])->middleware('throttle:60,1')->name('.contas.editar');
        Route::delete('/contas', [MarketplaceController::class, 'removerContas'])->middleware('throttle:60,1')->name('.contas.remover');
        Route::post('/sincronizar', [MarketplaceController::class, 'sincronizar'])->middleware('throttle:30,1')->name('.sincronizar');
        Route::put('/produtos', [MarketplaceController::class, 'salvarProdutos'])->middleware('throttle:60,1')->name('.produtos.salvar');
        Route::delete('/produtos', [MarketplaceController::class, 'removerProdutos'])->middleware('throttle:60,1')->name('.produtos.remover');
        Route::post('/anuncios/acao', [MarketplaceController::class, 'acaoAnuncios'])->middleware('throttle:60,1')->name('.anuncios.acao');
        Route::patch('/anuncios', [MarketplaceController::class, 'editarAnuncios'])->middleware('throttle:60,1')->name('.anuncios.editar');
    });

    // Diverso: precisa vir ANTES da rota genérica /areas/{area}
    Route::get('/areas/diverso', [DiversoController::class, 'show'])->name('areas.diverso');

    // corpo | mente | carteira | negocios | diverso
    Route::get('/areas/{area}', [AreaController::class, 'show'])
        ->whereIn('area', array_keys(Tarefa::AREAS))->name('areas.show');
    Route::post('/areas/{area}/tarefas', [AreaController::class, 'store'])
        ->whereIn('area', array_keys(Tarefa::AREAS))->middleware('throttle:60,1')->name('areas.tarefas.store');

    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update'])->middleware('throttle:gravar')->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->middleware('throttle:gravar')->name('tarefas.destroy');

    // Dados da área Corpo (treinos, dieta, água, calorias, blocos livres, layout, imagens)
    Route::put('/corpo/dados/{chave}', [CorpoController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('corpo.dados.salvar');
    Route::delete('/corpo/dados/{chave}', [CorpoController::class, 'remover'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')->middleware('throttle:gravar')->name('corpo.dados.remover');

    Route::put('/fundo', [FundoController::class, 'salvar'])->middleware('throttle:30,1')->name('fundo.salvar');
    // imagem ou GIF de um link (arrastado de outro site ou colado), para o fundo geral e o de cada workflow/aba
    Route::post('/fundo/baixar', [FundoController::class, 'baixar'])->middleware('throttle:20,1')->name('fundo.baixar');

    // Preferências da conta: teclas de atalho, organização automática dos cartões, tutorial visto
    // métricas públicas de um canal do YouTube (cartão Criador de conteúdo)
    Route::get('/youtube/canal', [\App\Http\Controllers\YoutubeController::class, 'canal'])->middleware('throttle:30,1')->name('youtube.canal');
    // banco de questões abertas (cartão Questões) e tarefas da área (cartões Hábitos e RH)
    Route::prefix('/cartoes')->name('cartoes.')->group(function () {
        Route::get('/questoes/filtros', [\App\Http\Controllers\CartoesController::class, 'bancoFiltros'])->middleware('throttle:60,1')->name('questoes.filtros');
        Route::get('/questoes', [\App\Http\Controllers\CartoesController::class, 'banco'])->middleware('throttle:120,1')->name('questoes');
        Route::get('/questoes/simulado', [\App\Http\Controllers\CartoesController::class, 'simulado'])->middleware('throttle:60,1')->name('questoes.simulado');
        Route::get('/tarefas/{area}', [\App\Http\Controllers\CartoesController::class, 'tarefas'])
            ->whereIn('area', array_keys(Tarefa::AREAS))->middleware('throttle:60,1')->name('tarefas');
        Route::post('/tarefas/{area}', [\App\Http\Controllers\CartoesController::class, 'criarTarefas'])
            ->whereIn('area', array_keys(Tarefa::AREAS))->middleware('throttle:30,1')->name('tarefas.criar');
    });
    Route::put('/preferencias', [PreferenciasController::class, 'salvar'])
        ->middleware('throttle:60,1')->name('preferencias.salvar');
    Route::get('/fundo/imagem', [FundoController::class, 'imagem'])->name('fundo.imagem');

    // Reclame aqui: cada pessoa vê só as próprias mensagens; a administração (SoAdmin) responde em /admin/reclamacoes
    Route::get('/reclame-aqui', [\App\Http\Controllers\ReclamacaoController::class, 'index'])->name('reclame.index');
    Route::post('/reclame-aqui', [\App\Http\Controllers\ReclamacaoController::class, 'enviar'])->middleware('throttle:20,1')->name('reclame.enviar');
    Route::get('/admin/reclamacoes', [\App\Http\Controllers\ReclamacaoController::class, 'admin'])
        ->middleware(\App\Http\Middleware\SoAdmin::class)->name('reclame.admin');
    Route::post('/admin/reclamacoes/{reclamacao}', [\App\Http\Controllers\ReclamacaoController::class, 'responder'])
        ->whereNumber('reclamacao')->middleware(\App\Http\Middleware\SoAdmin::class)->name('reclame.responder');

    // Ambiente de cada workflow e de cada aba dele (?setor=): fundo próprio e música (arquivo ou YouTube). Só o dono muda.
    Route::prefix('/ambiente/{area}')->whereIn('area', \App\Support\Ambiente::AREAS)->name('ambiente.')->group(function () {
        Route::put('/', [\App\Http\Controllers\AmbienteController::class, 'salvar'])->middleware('throttle:30,1')->name('salvar');
        Route::get('/imagem', [\App\Http\Controllers\AmbienteController::class, 'imagem'])->name('imagem');
        Route::get('/audio', [\App\Http\Controllers\AmbienteController::class, 'audio'])->name('audio');
        Route::post('/audio', [\App\Http\Controllers\AmbienteController::class, 'enviarAudio'])->middleware('throttle:10,1')->name('audio.enviar');
        Route::delete('/audio', [\App\Http\Controllers\AmbienteController::class, 'removerAudio'])->middleware('throttle:30,1')->name('audio.remover');
    });

    // Workflows compartilhados: convidar pessoas para uma área (limite de pessoas vem do plano do dono)
    Route::get('/compartilhar/{area}', [CompartilharController::class, 'index'])->name('compartilhar.index');
    Route::post('/compartilhar/{area}', [CompartilharController::class, 'adicionar'])
        ->middleware('throttle:20,1')->name('compartilhar.adicionar');
    Route::delete('/compartilhar/{area}/sair/{dono}', [CompartilharController::class, 'sair'])
        ->whereNumber('dono')->middleware('throttle:20,1')->name('compartilhar.sair');
    Route::delete('/compartilhar/{area}/{membro}', [CompartilharController::class, 'remover'])
        ->whereNumber('membro')->middleware('throttle:20,1')->name('compartilhar.remover');
    // o que as outras pessoas do workflow mudaram (o navegador confere a cada poucos segundos)
    Route::get('/sincronia/{area}', [CompartilharController::class, 'sincronia'])
        ->middleware('throttle:120,1')->name('sincronia');
    // mudanças das outras pessoas em qualquer workflow compartilhado (aviso discreto em outras páginas)
    Route::get('/sincronia', [CompartilharController::class, 'novidades'])
        ->middleware('throttle:30,1')->name('sincronia.novidades');

    // Biblioteca de blocos (vale para todas as páginas)
    Route::get('/biblioteca', [BibliotecaController::class, 'index'])->name('biblioteca.index');
    Route::put('/biblioteca', [BibliotecaController::class, 'salvar'])->middleware('throttle:60,1')->name('biblioteca.salvar');

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
        ->where('chave', '[A-Za-z0-9._-]{1,90}')
        ->middleware('throttle:gravar')
        ->name('salvar');

    Route::delete('/dados/{chave}', [MenteController::class, 'apagar'])
        ->where('chave', '[A-Za-z0-9._-]{1,90}')
        ->middleware('throttle:gravar')
        ->name('apagar');

    // PDFs dos livros
    Route::post('/pdf/{id}', [MenteController::class, 'enviarPdf'])
        ->where('id', '[A-Za-z0-9_-]{1,80}')
        ->middleware('throttle:20,1')
        ->name('pdf.enviar');

    Route::get('/pdf/{id}', [MenteController::class, 'pdf'])
        ->where('id', '[A-Za-z0-9_-]{1,80}')
        ->name('pdf.ver');

    Route::delete('/pdf/{id}', [MenteController::class, 'removerPdf'])
        ->where('id', '[A-Za-z0-9_-]{1,80}')
        ->middleware('throttle:60,1')
        ->name('pdf.remover');
});

// Dados da área Diverso (estado das abas e imagens; chave: "estado" ou "img.{id}")
Route::middleware('auth')->prefix('diverso')->name('diverso.')->group(function () {
    Route::put('/dados/{chave}', [DiversoController::class, 'salvar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')
        ->middleware('throttle:gravar')
        ->name('salvar');

    Route::get('/dados/{chave}', [DiversoController::class, 'ler'])
        ->where('chave', 'sqlz\.[A-Za-z0-9._-]{1,55}')
        ->middleware('throttle:600,1')
        ->name('ler');

    Route::delete('/dados/{chave}', [DiversoController::class, 'apagar'])
        ->where('chave', '[A-Za-z0-9._-]{1,60}')
        ->middleware('throttle:gravar')
        ->name('apagar');
});

// Tabletop ao vivo (salas com código). O arquivo é carregado aqui para herdar o grupo "web" (sessão + CSRF).
require __DIR__ . '/tabletop-vivo.php';

// Negócios › E-mails (caixas conectadas por IMAP/Gmail). Mesmo motivo: herda o grupo "web".
require __DIR__ . '/emails.php';
// Configurações › Armazenamento (destinos próprios, backups e arquivos fora do banco)
require __DIR__ . '/armazenamento.php';
