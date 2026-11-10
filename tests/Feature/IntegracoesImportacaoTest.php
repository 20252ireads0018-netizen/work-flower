<?php

namespace Tests\Feature;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use App\Models\MktPedido;
use App\Models\MktProduto;
use App\Models\RedeConta;
use App\Models\User;
use App\Support\Integracoes\Planilha;
use App\Support\Marketplaces\Marketplaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Instagram, TikTok, Shopee e Amazon: importação manual (CSV/números), repasse do OAuth pelo GitHub Pages. */
class IntegracoesImportacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_planilha_entende_numeros_datas_e_cabecalhos(): void
    {
        $this->assertSame(1234.0, Planilha::numero('1.234'));
        $this->assertSame(12.9, Planilha::numero('R$ 12,90'));
        $this->assertSame(1234.56, Planilha::numero('1.234,56'));
        $this->assertSame(1234.56, Planilha::numero('1,234.56'));
        $this->assertSame(1500.0, Planilha::numero('1,5 mil'));
        $this->assertSame(2300.0, Planilha::numero('2.3K'));
        $this->assertSame(1200000.0, Planilha::numero('1,2 mi'));
        $this->assertNull(Planilha::numero('—'));
        $this->assertSame('2026-10-31', Planilha::data('31/10/2026 10:00')->toDateString());
        $this->assertSame('2026-10-31', Planilha::data('10/31/2026')->toDateString());
        $this->assertSame('2026-11-01', Planilha::data('2026-11-01T10:00:00+0000')->toDateString());

        $l = Planilha::linhas("\xEF\xBB\xBFHorário de publicação;Curtidas;Outra\n01/11/2026;\"1.200\";x\n", ['data' => ['horario de publicacao'], 'curtidas' => ['curtidas']]);
        $this->assertSame([['data' => '01/11/2026', 'curtidas' => '1.200']], $l);
    }

    public function test_instagram_sem_app_importa_csv_e_numeros_e_mostra_no_cartao(): void
    {
        config(['services.meta.id' => '', 'services.meta.secret' => '']);
        $u = User::factory()->create();
        $csv = "Identificação da publicação,Descrição,Horário de publicação,Tipo de publicação,Link permanente,Alcance,Visualizações,Curtidas,Comentários\n"
            . "111,Post antigo,10/30/2026 09:00,Imagem,https://www.instagram.com/p/a/,800,,40,2\n"
            . "222,\"Reels novo, com vírgula\",11/01/2026 10:00,Reel,https://www.instagram.com/p/b/,\"9,1 mil\",14000,520,31\n";

        $j = $this->actingAs($u)->postJson('/redes/instagram/importar', ['csv' => $csv, 'seguidores' => '12.400', 'usuario' => '@minhaconta'])
            ->assertOk()->assertJson(['ok' => true, 'manual' => true])->json();
        $this->assertSame('222', $j['posts'][0]['id']); // mais recente primeiro
        $this->assertSame('Reels novo, com vírgula', $j['posts'][0]['legenda']);
        $this->assertSame(9100, $j['posts'][0]['alcance']);
        $this->assertSame(14000, $j['posts'][0]['views']);
        $this->assertNull($j['posts'][1]['views']);
        $this->assertSame(12400, $j['conta']['seguidores']);
        $this->assertSame(2, $j['conta']['posts']);
        $this->assertSame([now()->toDateString() => 12400], $j['historico']);

        // a leitura do cartão devolve o que foi importado
        $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertOk()
            ->assertJson(['ok' => true, 'manual' => true, 'conta' => ['usuario' => 'minhaconta', 'seguidores' => 12400]]);

        // só números: mantém os posts
        $this->actingAs($u)->postJson('/redes/instagram/importar', ['seguindo' => '300'])->assertOk()->assertJsonCount(2, 'posts');
        // nada para importar / planilha sem colunas conhecidas
        $this->actingAs($u)->postJson('/redes/instagram/importar', [])->assertStatus(422);
        $this->actingAs($u)->postJson('/redes/instagram/importar', ['csv' => "a;b\n1;2"])->assertStatus(422);

        // outra pessoa não vê
        $this->actingAs(User::factory()->create())->getJson('/redes/instagram/metricas')->assertStatus(409);

        $this->actingAs($u)->deleteJson('/redes/instagram/importar')->assertOk();
        $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertStatus(409)->assertJson(['importar' => true]);
    }

    public function test_tiktok_importa_e_soma_curtidas_e_conta_conectada_tem_prioridade(): void
    {
        config(['services.tiktok.key' => 'k', 'services.tiktok.secret' => 's']);
        $u = User::factory()->create();
        $csv = "Video title\tPost time\tTotal views\tTotal likes\tTotal comments\tTotal shares\n"
            . "Receita\t2026-11-01\t12000\t800\t30\t12\nTreino\t2026-11-03\t3K\t200\t5\t1\n";
        $j = $this->actingAs($u)->postJson('/redes/tiktok/importar', ['csv' => $csv])->assertOk()->json();
        $this->assertSame('Treino', $j['videos'][0]['titulo']);
        $this->assertSame(3000, $j['videos'][0]['views']);
        $this->assertSame(1000, $j['conta']['curtidas']);
        $this->assertSame(2, $j['conta']['videos']);
        $this->assertStringContainsString('/redes/tiktok/conectar', $j['conectar']);

        // conectada: a API vale mais que a importação
        RedeConta::create(['user_id' => $u->id, 'rede' => 'tiktok', 'access_token' => 'a', 'refresh_token' => 'r', 'expira_em' => now()->addDay()]);
        Http::fake([
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['username' => 'eu', 'follower_count' => 50]], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => []], 'error' => ['code' => 'ok']]),
        ]);
        $this->actingAs($u)->getJson('/redes/tiktok/metricas')->assertOk()->assertJson(['manual' => false, 'conta' => ['seguidores' => 50]])
            ->assertJsonPath('historico.' . now()->toDateString(), 50);
        $this->assertSame(50, RedeConta::first()->extra['hist'][now()->toDateString()]);
    }

    public function test_oauth_usa_a_pagina_de_repasse_do_github_pages(): void
    {
        config(['services.meta.id' => 'app', 'services.meta.secret' => 'seg', 'integracoes.relay' => 'https://eu.github.io/work-flower',
            'marketplaces.canais.shopee.partner_id' => '99', 'marketplaces.canais.shopee.partner_key' => 'k',
            'marketplaces.canais.amazon.client_id' => 'c', 'marketplaces.canais.amazon.client_secret' => 's', 'marketplaces.canais.amazon.application_id' => 'amzn1.sp.solution.x']);
        $u = User::factory()->create();

        $loc = $this->actingAs($u)->get('/redes/instagram/conectar')->headers->get('Location');
        parse_str(parse_url($loc, PHP_URL_QUERY), $q);
        $this->assertSame('https://eu.github.io/work-flower/redes/instagram/', $q['redirect_uri']);

        $this->assertStringStartsWith('https://eu.github.io/work-flower/marketplaces/shopee/', Marketplaces::conector('shopee')->retorno());
        $this->assertSame('https://eu.github.io/work-flower/marketplaces/amazon/', Marketplaces::conector('amazon')->retorno());
        $this->assertSame(route('carteira.mkt.retorno', 'mercadolivre'), Marketplaces::conector('mercadolivre')->retorno());

        // a troca do código usa o mesmo endereço do repasse
        $state = $q['state'];
        Http::fake([
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [['id' => 'p', 'instagram_business_account' => ['id' => '1', 'username' => 'x']]]]),
            'graph.facebook.com/*' => Http::response(['access_token' => 't', 'expires_in' => 5184000]),
        ]);
        $this->actingAs($u)->get("/redes/instagram/retorno?code=abc&state={$state}")->assertRedirect();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'code=abc') && str_contains(urldecode($r->url()), 'redirect_uri=https://eu.github.io/work-flower/redes/instagram/'));

        // sem INTEGRACOES_RELAY_URL: aproveita a base do GOOGLE_RELAY_URL
        config(['integracoes.relay' => '']);
        $this->assertSame(route('redes.retorno', 'tiktok'), \App\Http\Controllers\RedesController::retornoUrl('tiktok'));
    }

    public function test_relay_padrao_vem_do_google_relay(): void
    {
        $antes = [$_SERVER['GOOGLE_RELAY_URL'] ?? null, $_SERVER['INTEGRACOES_RELAY_URL'] ?? null, $_ENV['GOOGLE_RELAY_URL'] ?? null, $_ENV['INTEGRACOES_RELAY_URL'] ?? null];
        $_SERVER['GOOGLE_RELAY_URL'] = $_ENV['GOOGLE_RELAY_URL'] = 'https://eu.github.io/work-flower/google/';
        $_SERVER['INTEGRACOES_RELAY_URL'] = $_ENV['INTEGRACOES_RELAY_URL'] = '';
        try {
            $cfg = require config_path('integracoes.php');
        } finally {
            foreach ([['_SERVER', 'GOOGLE_RELAY_URL', 0], ['_SERVER', 'INTEGRACOES_RELAY_URL', 1], ['_ENV', 'GOOGLE_RELAY_URL', 2], ['_ENV', 'INTEGRACOES_RELAY_URL', 3]] as [$g, $k, $i]) {
                if ($g === '_SERVER') { if ($antes[$i] === null) unset($_SERVER[$k]); else $_SERVER[$k] = $antes[$i]; }
                else { if ($antes[$i] === null) unset($_ENV[$k]); else $_ENV[$k] = $antes[$i]; }
            }
        }
        $this->assertSame('https://eu.github.io/work-flower', $cfg['relay']);
    }

    public function test_marketplace_importa_anuncios_e_pedidos_da_shopee_sem_contar_no_limite(): void
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        $anuncios = "ID do produto;Nome do produto;SKU de referência;Preço;Estoque;Status;Custo\n"
            . "9001;Caneca azul;CAN-01;49,90;12;Ativo;15,00\n9002;Garrafa;GAR-01;119,90;0;Inativo;\n";
        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'shopee', 'texto' => $anuncios])
            ->assertOk()->assertJson(['ok' => true, 'tipo' => 'anuncios', 'importados' => 2]);

        $conta = MktConta::where('user_id', $u->id)->firstOrFail();
        $this->assertTrue($conta->manual);
        $this->assertSame('shopee', $conta->canal);
        $this->assertSame(['ativo', 'pausado'], MktAnuncio::orderBy('externo_id')->pluck('status')->all());
        $this->assertEquals(49.9, MktAnuncio::where('externo_id', '9001')->value('preco'));
        $this->assertEquals(15.0, MktProduto::where('sku', 'CAN-01')->value('custo'));

        // pedidos: uma linha por item, total e tarifa repetidos no pedido
        $pedidos = "ID do pedido,Data de criação do pedido,Status do pedido,Número de referência SKU,Nome do Produto,Quantidade,Preço acordado,Valor Total,Taxa de comissão,Taxa de serviço\n"
            . "P1," . now()->subDays(2)->format('d/m/Y') . ",Concluído,CAN-01,Caneca azul,2,\"49,90\",\"139,80\",\"20,00\",\"5,00\"\n"
            . "P1," . now()->subDays(2)->format('d/m/Y') . ",Concluído,GAR-01,Garrafa,1,\"40,00\",\"139,80\",\"20,00\",\"5,00\"\n"
            . "P2," . now()->subDay()->format('d/m/Y') . ",Cancelado,CAN-01,Caneca azul,1,\"49,90\",,,\n";
        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'shopee', 'texto' => $pedidos])
            ->assertOk()->assertJson(['tipo' => 'pedidos', 'importados' => 2]);
        $p1 = MktPedido::where('externo_id', 'P1')->firstOrFail();
        $this->assertEquals(139.8, $p1->total);
        $this->assertEquals(25.0, $p1->tarifas);
        $this->assertCount(2, $p1->itens);
        $this->assertEquals(49.9, MktPedido::where('externo_id', 'P2')->value('total'));
        $this->assertSame(1, MktConta::count()); // mesma conta "planilha"

        // importar de novo atualiza, não duplica
        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'shopee', 'texto' => $anuncios])->assertOk();
        $this->assertSame(2, MktAnuncio::count());

        // sincronizar a conta planilha não apaga nem marca como encerrado
        $this->actingAs($u)->postJson('/carteira/marketplaces/sincronizar', ['ids' => [$conta->id]])->assertOk()->assertJsonPath('contas.0.ok', true);
        $this->assertSame(0, MktAnuncio::where('status', 'encerrado')->count());

        // alteração em massa fica só no Work Flower
        Http::fake();
        $this->actingAs($u)->postJson('/carteira/marketplaces/anuncios/acao', ['ids' => [MktAnuncio::where('externo_id', '9001')->value('id')], 'acao' => 'preco', 'valor' => 55])->assertOk();
        Http::assertNothingSent();
        $this->assertEquals(55, MktAnuncio::where('externo_id', '9001')->value('preco'));

        // a tela vê a conta como planilha e ela não conta no limite do plano
        $d = $this->actingAs($u)->getJson('/carteira/marketplaces/dados')->assertOk()->json();
        $this->assertSame(0, $d['contas_reais']);
        $this->assertTrue($d['contas'][0]['manual']);
    }

    public function test_marketplace_importa_relatorio_da_amazon_separado_por_tabulacao(): void
    {
        $u = User::factory()->create();
        $txt = "amazon-order-id\tpurchase-date\torder-status\tsku\tproduct-name\tquantity\titem-price\n"
            . "702-1\t" . now()->subDay()->toIso8601String() . "\tShipped\tLUM-01\tLuminária\t1\t139.90\n";
        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'amazon', 'texto' => $txt])->assertOk()->assertJson(['tipo' => 'pedidos', 'importados' => 1]);
        $p = MktPedido::firstOrFail();
        $this->assertEquals(139.9, $p->total);
        $this->assertNull($p->tarifas); // sem tarifa na planilha: a tela estima pelas regras da conta

        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'amazon', 'texto' => "foo\tbar\n1\t2"])->assertStatus(422);
        $this->actingAs($u)->postJson('/carteira/marketplaces/importar', ['canal' => 'ebay', 'texto' => "sku\nX"])->assertStatus(422);
    }

    public function test_tela_de_marketplaces_tem_abas_shopee_e_amazon(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/carteira/marketplaces')->assertOk()->getContent();
        $this->assertStringContainsString("['shopee', 'amazon']", $html);
        $this->assertStringContainsString('js/integracoes-quadro.js', $html);
        $this->assertStringContainsString('Importar planilha', $html);
        $this->assertStringNotContainsString('chega em breve', $html);
    }
}
