<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\LojaCupom;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Models\User;
use App\Support\Pix;
use App\Support\SaldoLoja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Excluir a loja: confirmação com o nome, pedidos em aberto bloqueiam, histórico, produtos e saldo ficam guardados. */
class LojaExcluirTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;
    private Loja $loja;
    private LojaProduto $prod;

    protected function setUp(): void
    {
        parent::setUp();
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->dono = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $this->dono->id)->update(['plano_slug' => 'gratuito']);
        $this->actingAs($this->dono)->post('/carteira/loja', ['nome' => 'Doces da Ana', 'slug' => 'doces-da-ana', 'publicada' => 1])->assertRedirect();
        $this->loja = Loja::firstOrFail();
        $this->prod = LojaProduto::forceCreate(['loja_id' => $this->loja->id, 'nome' => 'Bolo', 'preco' => 50, 'estoque' => 10, 'ativo' => true]);
        LojaCupom::forceCreate(['loja_id' => $this->loja->id, 'codigo' => 'OFF10', 'tipo' => 'pct', 'valor' => 10, 'ativo' => true]);
    }

    private function pedidoPago(): LojaPedido
    {
        auth()->logout();
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'Bia', 'contato' => 'bia@x.com', 'itens' => json_encode([['id' => $this->prod->id, 'qtd' => 1]])]);
        $p = LojaPedido::latest('id')->firstOrFail();
        SaldoLoja::confirmarPedido($p);

        return $p->fresh();
    }

    public function test_botoes_de_editar_e_excluir_aparecem_na_pagina_da_loja(): void
    {
        $this->actingAs($this->dono)->get('/carteira/loja')->assertOk()
            ->assertSee('Editar loja')->assertSee('Excluir loja')->assertSee('digite o nome da loja', false);
    }

    public function test_nome_errado_ou_pedido_em_aberto_nao_exclui(): void
    {
        $this->actingAs($this->dono)->delete('/carteira/loja', ['confirmar_nome' => 'Doces'])->assertSessionHasErrors('confirmar_nome');
        $this->assertFalse($this->loja->fresh()->trashed());

        auth()->logout();
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'Bia', 'contato' => 'bia@x.com', 'itens' => json_encode([['id' => $this->prod->id, 'qtd' => 1]])]);
        $this->actingAs($this->dono)->delete('/carteira/loja', ['confirmar_nome' => 'Doces da Ana'])->assertSessionHasErrors('confirmar_nome');
        $this->assertFalse($this->loja->fresh()->trashed());

        $this->actingAs(User::factory()->create())->delete('/carteira/loja', ['confirmar_nome' => 'Doces da Ana'])->assertNotFound();
    }

    public function test_excluir_tira_do_ar_e_guarda_pedidos_produtos_e_saldo(): void
    {
        $p = $this->pedidoPago();
        $this->actingAs($this->dono)->delete('/carteira/loja', ['confirmar_nome' => '  doces da ana '])
            ->assertRedirect(route('areas.negocios'))->assertSessionHasNoErrors();

        $l = Loja::withTrashed()->findOrFail($this->loja->id);
        $this->assertTrue($l->trashed());
        $this->assertFalse($l->publicada);
        $this->assertNotSame('doces-da-ana', $l->slug);
        $this->assertSame(0, LojaCupom::count());
        $this->assertNotNull(LojaPedido::find($p->id));                                 // histórico guardado
        $this->assertSame($this->dono->id, LojaProduto::findOrFail($this->prod->id)->user_id); // continua no catálogo
        $this->assertSame(50.0, SaldoLoja::disponivel($this->dono->id));                 // saldo guardado

        auth()->logout();
        $this->get('/loja/doces-da-ana')->assertNotFound();
        $this->actingAs($this->dono)->getJson('/negocios/produtos')->assertJsonCount(1, 'produtos')->assertJsonPath('produtos.0.na_loja', false);
        $this->actingAs($this->dono)->get('/areas/negocios')->assertOk()->assertSee('Criar minha loja');
        // sem loja, o saldo ainda aparece e pode ser resgatado
        $this->actingAs($this->dono)->get('/carteira/loja')->assertOk()->assertSee('Você já teve a loja')->assertSee('Resgatar');
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => 50, 'tipo' => 'email', 'chave' => 'ana@x.com', 'nome' => 'Ana'])->assertSessionHasNoErrors();
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id));

        // o endereço ficou livre para outra conta
        $outro = User::factory()->create();
        $this->actingAs($outro)->post('/carteira/loja', ['nome' => 'Outra', 'slug' => 'doces-da-ana', 'publicada' => 1])->assertSessionHasNoErrors();

        // criar de novo traz o histórico de volta
        $this->actingAs($this->dono)->post('/carteira/loja', ['nome' => 'Doces da Ana 2', 'slug' => 'ana-doces', 'publicada' => 1])->assertSessionHasNoErrors();
        $volta = Loja::where('user_id', $this->dono->id)->firstOrFail();
        $this->assertSame($this->loja->id, $volta->id);
        $this->assertSame(1, $volta->pedidos()->count());
        auth()->logout();
        $this->get('/loja/ana-doces')->assertOk()->assertSee('Bolo');
    }
}
