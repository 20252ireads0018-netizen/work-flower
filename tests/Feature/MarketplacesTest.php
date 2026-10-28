<?php

namespace Tests\Feature;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use App\Models\MktPedido;
use App\Models\MktProduto;
use App\Models\User;
use App\Support\Marketplaces\Marketplaces;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Carteira › Marketplaces: limite do plano, demonstração, conexão OAuth, sincronização, margem e alterações em massa. */
class MarketplacesTest extends TestCase
{
    use RefreshDatabase;

    private function conta(string $plano = 'gratuito'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // fora do teste grátis
        DB::table('users')->where('id', $u->id)->update([
            'plano_slug' => $plano,
            'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth(),
        ]);

        return $u->fresh();
    }

    private function mercadoLivreConfigurado(): void
    {
        config(['marketplaces.canais.mercadolivre.client_id' => 'cid', 'marketplaces.canais.mercadolivre.client_secret' => 'seg']);
    }

    public function test_margem_e_preco_para_margem(): void
    {
        $regras = ['comissao_pct' => 14, 'taxa_fixa' => 6.75, 'taxa_fixa_abaixo_de' => 79, 'frete' => 0, 'imposto_pct' => 6];
        $a = new MktAnuncio(['preco' => 50]);
        $p = new MktProduto(['custo' => 20, 'custo_extra' => 2]);

        $m = Marketplaces::margem($a, $p, $regras);
        // 50 − 22 − (7 + 6,75) − 3 = 11,25
        $this->assertSame(13.75, $m['tarifa']);
        $this->assertSame(3.0, $m['imposto']);
        $this->assertSame(11.25, $m['lucro']);
        $this->assertSame(22.5, $m['margem']);

        // preço para 20%: abaixo de 79 paga a tarifa fixa → (22 + 6,75) / (1 − 0,40)
        $preco = Marketplaces::precoParaMargem(22, 20, $regras);
        $this->assertSame(47.92, $preco);
        $this->assertEqualsWithDelta(20, Marketplaces::margem(new MktAnuncio(['preco' => $preco]), $p, $regras)['margem'], 0.1);

        // margem impossível
        $this->assertNull(Marketplaces::precoParaMargem(22, 85, $regras));

        // tarifa informada pelo marketplace tem prioridade sobre a regra
        $this->assertSame(9.5, Marketplaces::margem(new MktAnuncio(['preco' => 50, 'tarifa_venda' => 9.5]), $p, $regras)['tarifa']);
    }

    public function test_demonstracao_cria_anuncios_produtos_e_pedidos_sem_contar_no_limite(): void
    {
        $u = $this->conta('gratuito');

        $this->actingAs($u)->postJson('/carteira/marketplaces/contas/demo', ['canal' => 'shopee'])->assertOk();
        $this->actingAs($u)->postJson('/carteira/marketplaces/contas/demo', ['canal' => 'mercadolivre'])->assertOk();

        $this->assertSame(2, MktConta::where(['user_id' => $u->id, 'demo' => true])->count());
        $this->assertGreaterThan(0, MktAnuncio::where('user_id', $u->id)->count());
        $this->assertGreaterThan(0, MktPedido::where('user_id', $u->id)->count());
        $this->assertSame(8, MktProduto::where('user_id', $u->id)->count());

        $j = $this->actingAs($u)->getJson('/carteira/marketplaces/dados')->assertOk()->json();
        $this->assertSame(1, $j['limite']);
        $this->assertSame(0, $j['contas_reais']);
        $this->assertNotNull($j['anuncios'][0]['margem']);
    }

    public function test_plano_gratuito_conecta_uma_conta_real(): void
    {
        $this->mercadoLivreConfigurado();
        $u = $this->conta('gratuito');

        $r = $this->actingAs($u)->get('/carteira/marketplaces/conectar/mercadolivre');
        $r->assertRedirect();
        $this->assertStringStartsWith('https://auth.mercadolivre.com.br/authorization?', $r->headers->get('Location'));

        MktConta::create(['user_id' => $u->id, 'canal' => 'mercadolivre', 'nome' => 'Loja 1', 'externo_id' => '1']);
        $this->actingAs($u)->get('/carteira/marketplaces/conectar/mercadolivre')
            ->assertRedirect(route('carteira.mkt'))->assertSessionHas('mkt_erro');
    }

    public function test_canal_sem_chaves_nao_conecta(): void
    {
        config(['marketplaces.canais.shopee.partner_id' => null]);
        $this->actingAs($this->conta('basico'))->get('/carteira/marketplaces/conectar/shopee')
            ->assertRedirect(route('carteira.mkt'))->assertSessionHas('mkt_erro');
    }

    public function test_retorno_do_mercado_livre_conecta_e_sincroniza(): void
    {
        $this->mercadoLivreConfigurado();
        $u = $this->conta('basico');

        Http::fake([
            'api.mercadolibre.com/oauth/token' => Http::response(['access_token' => 'AT', 'refresh_token' => 'RT', 'expires_in' => 21600, 'user_id' => 99]),
            'api.mercadolibre.com/users/me' => Http::response(['id' => 99, 'nickname' => 'LOJA_TESTE', 'site_id' => 'MLB']),
            'api.mercadolibre.com/users/99/items/search*' => Http::sequence()
                ->push(['results' => ['MLB1', 'MLB2'], 'scroll_id' => 's1'])
                ->push(['results' => [], 'scroll_id' => 's1']),
            'api.mercadolibre.com/items?*' => Http::response([
                ['code' => 200, 'body' => ['id' => 'MLB1', 'title' => 'Caneca', 'price' => 50, 'available_quantity' => 10, 'status' => 'active', 'listing_type_id' => 'gold_special', 'category_id' => 'MLB1', 'permalink' => 'https://x', 'seller_custom_field' => 'CAN-1', 'attributes' => []]],
                ['code' => 200, 'body' => ['id' => 'MLB2', 'title' => 'Garrafa', 'price' => 120, 'available_quantity' => 0, 'status' => 'paused', 'listing_type_id' => 'gold_pro', 'attributes' => [['id' => 'SELLER_SKU', 'value_name' => 'GAR-1']], 'variations' => []]],
            ]),
            'api.mercadolibre.com/sites/MLB/listing_prices*' => Http::response(['sale_fee_amount' => 13.75]),
            'api.mercadolibre.com/orders/search*' => Http::response(['results' => [[
                'id' => 555, 'date_created' => now()->subDay()->toIso8601String(), 'status' => 'paid', 'total_amount' => 100,
                'order_items' => [['item' => ['id' => 'MLB1', 'title' => 'Caneca', 'seller_sku' => 'CAN-1'], 'quantity' => 2, 'unit_price' => 50, 'sale_fee' => 13.75]],
            ]], 'paging' => ['total' => 1]]),
        ]);

        $this->actingAs($u)->get('/carteira/marketplaces/conectar/mercadolivre');
        $state = array_key_first(session('mkt_state'));

        $this->actingAs($u)->get("/carteira/marketplaces/retorno/mercadolivre?code=CODE&state={$state}")
            ->assertRedirect(route('carteira.mkt'))->assertSessionHas('mkt_ok');

        $conta = MktConta::where('user_id', $u->id)->firstOrFail();
        $this->assertSame('LOJA_TESTE', $conta->nome);
        $this->assertSame('AT', $conta->access_token);
        $this->assertNotSame('AT', DB::table('mkt_contas')->value('access_token')); // gravado criptografado

        $a = MktAnuncio::where('externo_id', 'MLB2')->firstOrFail();
        $this->assertSame(['GAR-1', 'pausado', 'Premium'], [$a->sku, $a->status, $a->tipo]);
        $this->assertSame(13.75, MktAnuncio::where('externo_id', 'MLB1')->value('tarifa_venda') + 0.0);
        $this->assertSame(27.5, MktPedido::firstOrFail()->tarifas);

        // o state só vale uma vez
        $this->actingAs($u)->get("/carteira/marketplaces/retorno/mercadolivre?code=CODE&state={$state}")->assertSessionHas('mkt_erro');
    }

    public function test_acao_em_massa_envia_para_o_mercado_livre_e_respeita_o_custo(): void
    {
        $this->mercadoLivreConfigurado();
        $u = $this->conta('basico');
        $conta = MktConta::create(['user_id' => $u->id, 'canal' => 'mercadolivre', 'nome' => 'Loja', 'externo_id' => '99',
            'access_token' => 'AT', 'refresh_token' => 'RT', 'expira_em' => now()->addHour()]);
        $com = MktAnuncio::create(['user_id' => $u->id, 'conta_id' => $conta->id, 'externo_id' => 'MLB1', 'sku' => 'CAN-1', 'preco' => 50, 'estoque' => 5, 'status' => 'ativo']);
        $sem = MktAnuncio::create(['user_id' => $u->id, 'conta_id' => $conta->id, 'externo_id' => 'MLB2', 'sku' => 'X', 'preco' => 80, 'estoque' => 5, 'status' => 'ativo']);
        MktProduto::create(['user_id' => $u->id, 'sku' => 'CAN-1', 'custo' => 22, 'custo_extra' => 0]);

        Http::fake(['api.mercadolibre.com/items/*' => Http::response(['id' => 'ok'])]);

        $j = $this->actingAs($u)->postJson('/carteira/marketplaces/anuncios/acao', ['ids' => [$com->id, $sem->id], 'acao' => 'margem', 'valor' => 20])
            ->assertOk()->json();
        $this->assertSame(1, $j['ok']);
        $this->assertCount(1, $j['erros']);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/items/MLB1') && $r['price'] == 43.56);
        $this->assertSame(43.56, $com->fresh()->preco);

        $this->actingAs($u)->postJson('/carteira/marketplaces/anuncios/acao', ['ids' => [$com->id], 'acao' => 'status', 'valor' => 'pausado'])->assertOk();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PUT' && ($r['status'] ?? null) === 'paused');
        $this->assertSame('pausado', $com->fresh()->status);
    }

    public function test_shopee_assina_as_chamadas(): void
    {
        config(['marketplaces.canais.shopee.partner_id' => '1001', 'marketplaces.canais.shopee.partner_key' => 'chave']);
        $url = Marketplaces::conector('shopee')->urlAutorizacao('abc');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->assertStringContainsString('/api/v2/shop/auth_partner', $url);
        $this->assertSame(hash_hmac('sha256', '1001/api/v2/shop/auth_partner' . $q['timestamp'], 'chave'), $q['sign']);
        $this->assertStringContainsString('state=abc', $q['redirect']);
    }

    public function test_produtos_em_massa_e_dados_so_do_dono(): void
    {
        $u = $this->conta('basico');
        $outro = $this->conta();

        $this->actingAs($u)->putJson('/carteira/marketplaces/produtos', ['produtos' => [
            ['sku' => 'A', 'nome' => 'Produto A', 'custo' => 10], ['sku' => 'B', 'custo' => 5.5, 'custo_extra' => 1],
        ]])->assertOk()->assertJson(['salvos' => 2]);
        // só o custo muda; o nome fica
        $this->actingAs($u)->putJson('/carteira/marketplaces/produtos', ['produtos' => [['sku' => 'A', 'custo' => 12]]])->assertOk();
        $this->assertSame(['Produto A', 12.0], [MktProduto::where('sku', 'A')->value('nome'), (float) MktProduto::where('sku', 'A')->value('custo')]);

        $this->actingAs($outro)->getJson('/carteira/marketplaces/dados')->assertOk()->assertJsonCount(0, 'produtos');
        // sem acesso à Carteira do dono
        $this->actingAs($outro)->getJson('/carteira/marketplaces/dados?de=' . $u->id)->assertForbidden();

        // com a Carteira compartilhada, o convidado vê e edita os dados do dono
        $this->actingAs($u)->postJson('/compartilhar/carteira', ['email' => $outro->email])->assertOk();
        $this->actingAs($outro)->withHeader('X-WF-Dono', (string) $u->id)->getJson('/carteira/marketplaces/dados')->assertOk()->assertJsonCount(2, 'produtos');
    }
}
