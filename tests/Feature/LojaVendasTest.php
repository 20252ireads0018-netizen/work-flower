<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\LojaCupom;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Mini lojas, parte 2: cupons, opções de entrega e cartão pelo Mercado Pago do dono. */
class LojaVendasTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;
    private Loja $loja;
    private LojaProduto $prod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dono = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $this->dono->id)->update(['plano_slug' => 'gratuito']);
        $this->actingAs($this->dono)->post('/carteira/loja', ['nome' => 'Doces da Ana', 'slug' => 'doces-da-ana', 'publicada' => 1])->assertRedirect();
        $this->actingAs($this->dono)->post('/carteira/loja/pix', ['tipo' => 'email', 'chave' => 'ana@exemplo.com', 'nome' => 'Ana Souza', 'cidade' => 'Recife'])->assertSessionHasNoErrors();
        $this->actingAs($this->dono)->post('/carteira/loja/produtos', ['nome' => 'Bolo', 'preco' => '50', 'estoque' => 5, 'ativo' => 1])->assertSessionHasNoErrors();
        $this->loja = Loja::firstOrFail();
        $this->prod = LojaProduto::firstOrFail();
    }

    private function pedir(array $extra = [], int $qtd = 2)
    {
        auth()->logout();

        return $this->post('/loja/doces-da-ana/pedido', array_merge([
            'nome' => 'Bia', 'contato' => '81999990000', 'itens' => json_encode([['id' => $this->prod->id, 'qtd' => $qtd]]),
        ], $extra));
    }

    public function test_cupom_e_entrega_entram_no_total(): void
    {
        $this->actingAs($this->dono)->post('/carteira/loja/cupons', ['codigo' => 'bem vindo10', 'tipo' => 'pct', 'valor' => 10, 'minimo' => 60, 'usos_max' => 1, 'ativo' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->dono)->post('/carteira/loja/entrega', ['fretes' => [
            ['nome' => 'Retirada', 'preco' => 0], ['nome' => 'Motoboy', 'preco' => 12, 'gratis_acima' => 200], ['nome' => ''],
        ]])->assertSessionHasNoErrors();
        $this->assertCount(2, $this->loja->fresh()->fretes());
        $this->assertSame('BEMVINDO10', LojaCupom::first()->codigo);

        // prévia do cupom
        $this->getJson('/loja/doces-da-ana/cupom?codigo=bemvindo10&subtotal=100')->assertJson(['ok' => true, 'rotulo' => '10% de desconto']);
        $this->getJson('/loja/doces-da-ana/cupom?codigo=BEMVINDO10&subtotal=50')->assertJson(['ok' => false]);
        $this->getJson('/loja/doces-da-ana/cupom?codigo=NADA&subtotal=50')->assertJson(['ok' => false, 'erro' => 'Cupom não encontrado.']);

        // sem escolher a entrega não passa
        $this->pedir()->assertSessionHasErrors('frete');
        // 2 × 50 = 100, −10% = 90, + motoboy 12 = 102
        $this->pedir(['frete' => 1, 'cupom' => 'bemvindo10'])->assertRedirect();
        $p = LojaPedido::firstOrFail();
        $this->assertSame(['100.00', '10.00', '12.00', '102.00'], [$p->subtotal, $p->desconto, $p->frete, $p->total]);
        $this->assertSame('Motoboy', $p->frete_nome);
        $this->assertSame(1, LojaCupom::first()->usos);
        $this->get('/loja/doces-da-ana/pedido/' . $p->codigo)->assertOk()->assertSee('Cupom BEMVINDO10')->assertSee('R$ 102,00');

        // o cupom tinha 1 uso só
        $this->pedir(['frete' => 0, 'cupom' => 'BEMVINDO10'])->assertSessionHasErrors('cupom');
        $this->assertSame(1, LojaPedido::count());
        $this->assertSame(3, $this->prod->fresh()->estoque); // o pedido recusado não tirou estoque
    }

    public function test_cupom_e_entrega_sao_so_do_dono(): void
    {
        $outro = User::factory()->create();
        $this->actingAs($this->dono)->post('/carteira/loja/cupons', ['codigo' => 'AMIGO', 'tipo' => 'valor', 'valor' => 5, 'ativo' => 1]);
        $c = LojaCupom::first();
        $this->actingAs($outro)->delete('/carteira/loja/cupons/' . $c->id)->assertNotFound();
        $this->actingAs($this->dono)->post('/carteira/loja/cupons', ['codigo' => 'AMIGO', 'tipo' => 'pct', 'valor' => 5])->assertSessionHasErrors('codigo');
        $this->actingAs($this->dono)->post('/carteira/loja/cupons', ['codigo' => 'MUITO', 'tipo' => 'pct', 'valor' => 150])->assertSessionHasErrors('valor');
    }

    public function test_mercado_pago_conecta_cobra_e_confirma_pela_api(): void
    {
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['id' => 123, 'nickname' => 'ANASOUZA']),
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref1', 'init_point' => 'https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref1']),
            'api.mercadopago.com/v1/payments/999' => fn () => Http::response(['id' => 999, 'status' => 'approved', 'transaction_amount' => 100.0, 'external_reference' => LojaPedido::first()->codigo]),
            'api.mercadopago.com/v1/payments/555' => fn () => Http::response(['id' => 555, 'status' => 'approved', 'transaction_amount' => 1.0, 'external_reference' => LojaPedido::first()->codigo]),
        ]);

        $this->actingAs($this->dono)->post('/carteira/loja/mercadopago', ['token' => 'chave-errada'])->assertSessionHasErrors('token');
        $this->actingAs($this->dono)->post('/carteira/loja/mercadopago', ['token' => 'APP_USR-1234567890-abcdef-ghijkl'])->assertSessionHasNoErrors();
        $loja = $this->loja->fresh();
        $this->assertTrue($loja->mpPronto());
        $this->assertSame('ANASOUZA', $loja->mp_conta);
        $this->assertNotSame('APP_USR-1234567890-abcdef-ghijkl', $loja->getRawOriginal('mp_token')); // guardado criptografado
        $this->assertArrayNotHasKey('mp_token', $loja->toArray());

        // pedido no cartão vai para o checkout do Mercado Pago
        $this->pedir(['pagamento' => 'mp'])->assertRedirect('https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=pref1');
        $p = LojaPedido::firstOrFail();
        $this->assertSame('mp', $p->pagamento);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkout/preferences') && $r['external_reference'] === $p->codigo && $r['items'][0]['unit_price'] == 100.0);

        // valor errado não confirma; o certo confirma (o aviso só traz o id: o pagamento é relido na API)
        $this->postJson('/loja/doces-da-ana/mp/aviso', ['type' => 'payment', 'data' => ['id' => '555']])->assertOk();
        $this->assertSame('aguardando', $p->fresh()->status);
        $this->postJson('/loja/doces-da-ana/mp/aviso', ['type' => 'payment', 'data' => ['id' => '999']])->assertOk();
        $this->assertSame('pago', $p->fresh()->status);
        $this->assertSame('999', $p->fresh()->mp_pagamento);
        $this->get('/loja/doces-da-ana/pedido/' . $p->codigo)->assertSee('Pagamento confirmado');

        // desconectar volta a só Pix
        $this->actingAs($this->dono)->delete('/carteira/loja/mercadopago')->assertRedirect();
        $this->assertFalse($this->loja->fresh()->mpPronto());
    }

    public function test_volta_do_mercado_pago_confere_o_pagamento(): void
    {
        $this->loja->forceFill(['mp_token' => 'APP_USR-1234567890-abcdef', 'mp_conta' => 'x'])->save();
        $this->pedir(['pagamento' => 'pix']);
        $p = LojaPedido::firstOrFail();
        Http::fake(['api.mercadopago.com/v1/payments/777' => Http::response(['status' => 'approved', 'transaction_amount' => 100, 'external_reference' => $p->codigo])]);
        $this->get('/loja/doces-da-ana/pedido/' . $p->codigo . '?payment_id=777&status=approved')->assertOk()->assertSee('Pagamento confirmado');
        $this->assertSame('pago', $p->fresh()->status);
    }
}
