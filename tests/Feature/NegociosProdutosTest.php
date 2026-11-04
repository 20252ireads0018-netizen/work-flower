<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Models\User;
use App\Support\Pix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Negócios › Produtos: catálogo único com a mini loja, venda com preço automático e baixa de estoque, pedidos da loja como vendas. */
class NegociosProdutosTest extends TestCase
{
    use RefreshDatabase;

    private function dono(): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => 'basico', 'plano_expira_em' => now()->addMonth()]);

        return $u->fresh();
    }

    private function produto(User $u, array $d = []): array
    {
        $r = $this->actingAs($u)->postJson('/negocios/produtos', array_merge([
            'nome' => 'Bolo de chocolate', 'sku' => 'BOLO-G', 'categoria' => 'Bolos', 'preco' => '80', 'custo' => '30', 'estoque' => 5, 'na_loja' => 1,
        ], $d))->assertOk()->assertJsonPath('ok', true);

        return $r->json('produto');
    }

    public function test_catalogo_cadastra_produto_e_servico_sem_loja_e_entra_na_loja_quando_ela_e_criada(): void
    {
        Storage::fake('local');
        $u = $this->dono();
        $p = $this->produto($u, ['foto' => UploadedFile::fake()->image('bolo.png', 200, 200)]);
        $this->assertSame(62.5, $p['margem']);
        $this->assertSame(5, $p['estoque']);
        $this->assertFalse($p['na_loja']); // ainda não há loja
        $prod = LojaProduto::findOrFail($p['id']);
        $this->assertSame($u->id, $prod->user_id);
        $this->assertNull($prod->loja_id);
        Storage::disk('local')->assertExists("produtos/{$u->id}/{$prod->imagem}");
        $this->actingAs($u)->get($p['foto'])->assertOk();
        $this->actingAs($this->dono())->get($p['foto'])->assertNotFound(); // foto do catálogo é só do dono

        $s = $this->produto($u, ['nome' => 'Aula de confeitaria', 'sku' => '', 'tipo' => 'servico', 'preco' => '150', 'custo' => '', 'estoque' => '', 'na_loja' => 0]);
        $this->assertNull($s['estoque']);
        $this->assertNull($s['margem']);
        $this->assertSame('servico', $s['tipo']);

        // SKU repetido na mesma conta não passa; em outra conta pode
        $this->actingAs($u)->postJson('/negocios/produtos', ['nome' => 'Outro', 'sku' => 'BOLO-G', 'preco' => 1])->assertStatus(422);
        $this->produto($this->dono());

        $lista = $this->actingAs($u)->getJson('/negocios/produtos')->assertOk();
        $this->assertCount(2, $lista->json('produtos'));
        $this->assertSame(['Bolos'], $lista->json('categorias'));

        // cria a loja: o catálogo entra nela e a vitrine mostra só o marcado "Mostrar na loja"
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Doces', 'slug' => 'doces', 'publicada' => 1])->assertRedirect();
        $loja = Loja::where('user_id', $u->id)->firstOrFail();
        $this->assertSame(2, LojaProduto::where('loja_id', $loja->id)->count());
        auth()->logout();
        $this->get('/loja/doces')->assertOk()->assertSee('Bolo de chocolate')->assertDontSee('Aula de confeitaria');
        $this->get("/loja/doces/img/{$prod->imagem}")->assertOk(); // foto guardada antes da loja continua aparecendo

        // produto da loja aparece no catálogo, e editar pelo catálogo muda a loja
        $this->actingAs($u)->postJson("/negocios/produtos/{$s['id']}", ['nome' => 'Aula de confeitaria', 'tipo' => 'servico', 'preco' => 160, 'na_loja' => 1])->assertOk();
        auth()->logout();
        $this->get('/loja/doces')->assertSee('Aula de confeitaria')->assertSee('R$ 160,00');
    }

    public function test_outra_conta_nao_mexe_no_catalogo(): void
    {
        $u = $this->dono();
        $p = $this->produto($u);
        $outro = $this->dono();
        $this->actingAs($outro)->postJson("/negocios/produtos/{$p['id']}", ['nome' => 'X', 'preco' => 1])->assertNotFound();
        $this->actingAs($outro)->postJson("/negocios/produtos/{$p['id']}/venda", ['qtd' => 1])->assertNotFound();
        $this->actingAs($outro)->deleteJson("/negocios/produtos/{$p['id']}")->assertNotFound();
        $this->assertSame(0, count($this->actingAs($outro)->getJson('/negocios/produtos')->json('produtos')));
        $this->actingAs($u)->deleteJson("/negocios/produtos/{$p['id']}")->assertOk();
        $this->assertSame(0, LojaProduto::count());
    }

    public function test_venda_com_produto_preenche_preco_custo_margem_e_baixa_o_estoque(): void
    {
        $u = $this->dono();
        $p = $this->produto($u); // R$ 80, custo R$ 30, 5 em estoque

        $r = $this->actingAs($u)->postJson("/negocios/produtos/{$p['id']}/venda", ['qtd' => 2])->assertOk();
        $r->assertJson(['preco' => 80, 'custo' => 30, 'qtd' => 2, 'total' => 160, 'custo_total' => 60, 'lucro' => 100, 'margem' => 62.5, 'estoque' => 3]);
        $this->assertSame(3, LojaProduto::find($p['id'])->estoque);

        // sem estoque suficiente, a venda não é lançada e o estoque não muda
        $this->actingAs($u)->postJson("/negocios/produtos/{$p['id']}/venda", ['qtd' => 4])->assertStatus(422)->assertJsonPath('erro', 'Só há 3 de "Bolo de chocolate" em estoque.');
        $this->assertSame(3, LojaProduto::find($p['id'])->estoque);

        // apagar a venda devolve o estoque
        $this->actingAs($u)->postJson("/negocios/produtos/{$p['id']}/estorno", ['qtd' => 2])->assertOk()->assertJsonPath('estoque', 5);

        // serviço (sem controle de estoque) vende à vontade
        $s = $this->produto($u, ['nome' => 'Aula', 'sku' => '', 'tipo' => 'servico', 'preco' => 100, 'custo' => '', 'estoque' => '']);
        $this->actingAs($u)->postJson("/negocios/produtos/{$s['id']}/venda", ['qtd' => 50])->assertOk()
            ->assertJson(['total' => 5000, 'custo' => null, 'custo_total' => 0, 'margem' => null, 'estoque' => null]);
        $this->actingAs($u)->postJson("/negocios/produtos/{$s['id']}/venda", ['qtd' => 0])->assertStatus(422);
    }

    public function test_pedidos_pagos_da_loja_contam_como_vendas_com_custo(): void
    {
        $u = $this->dono();
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Doces', 'slug' => 'doces', 'publicada' => 1]);
        $p = $this->produto($u);
        auth()->logout();
        $this->post('/loja/doces/pedido', ['nome' => 'Bia', 'contato' => 'bia@x.com', 'itens' => json_encode([['id' => $p['id'], 'qtd' => 2]])]);
        $this->post('/loja/doces/pedido', ['nome' => 'Caio', 'contato' => 'caio@x.com', 'itens' => json_encode([['id' => $p['id'], 'qtd' => 1]])]);
        $this->assertSame(2, LojaProduto::find($p['id'])->estoque); // pedido da loja também baixa o estoque do catálogo

        // só os pagos contam
        $this->actingAs($u)->getJson('/negocios/vendas-da-loja')->assertOk()->assertJsonCount(0, 'vendas');
        \App\Support\SaldoLoja::confirmarPedido(LojaPedido::where('cliente_nome', 'Bia')->firstOrFail());
        $v = $this->actingAs($u)->getJson('/negocios/vendas-da-loja')->assertOk()->assertJsonCount(1, 'vendas')->json('vendas.0');
        $this->assertSame(160, (int) $v['valor']);
        $this->assertSame(60, (int) $v['custo']);
        $this->assertSame('Loja virtual', $v['canal']);

        // a página de Negócios mostra a aba Produtos e o cartão da loja com editar e excluir
        $this->actingAs($u)->get('/areas/negocios')->assertOk()
            ->assertSee('negocios\/produtos', false)->assertSee('Editar loja')->assertSee('Excluir loja')->assertSee('Saldo da loja');
    }
}
