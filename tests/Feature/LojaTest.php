<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Carteira › Loja (mini loja com página pública, pedido e Pix na chave do dono). */
class LojaTest extends TestCase
{
    use RefreshDatabase;

    private function dono(string $plano = 'gratuito'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // já passou o teste grátis
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => $plano, 'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth()]);

        return $u->fresh();
    }

    private function lojaPronta(User $u): Loja
    {
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Doces da Ana', 'slug' => 'doces-da-ana', 'publicada' => 1, 'cor' => '#AA3355'])->assertRedirect();
        $this->actingAs($u)->post('/carteira/loja/pix', ['tipo' => 'email', 'chave' => 'Ana@Exemplo.com', 'nome' => 'Ana Souza', 'cidade' => 'Recife'])->assertSessionHasNoErrors();

        return Loja::where('user_id', $u->id)->firstOrFail();
    }

    public function test_dono_monta_a_loja_e_a_vitrine_mostra_os_produtos(): void
    {
        Storage::fake('local');
        $u = $this->dono();
        $loja = $this->lojaPronta($u);
        $this->assertSame('#aa3355', $loja->visual()['cor']);
        $this->assertSame('ana@exemplo.com', $loja->pix_chave);

        $this->actingAs($u)->post('/carteira/loja/produtos', [
            'nome' => 'Brigadeiro <b>gourmet</b>', 'preco' => '3.50', 'preco_antigo' => '5', 'estoque' => 10, 'ativo' => 1,
            'imagem' => UploadedFile::fake()->image('foto.png', 300, 300),
        ])->assertSessionHasNoErrors();
        $p = LojaProduto::first();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}\.png$/', $p->imagem);
        Storage::disk('local')->assertExists("lojas/{$loja->id}/{$p->imagem}");

        auth()->logout();
        $this->get('/loja/doces-da-ana')->assertOk()
            ->assertSee('Brigadeiro &lt;b&gt;gourmet&lt;/b&gt;', false)->assertDontSee('<b>gourmet</b>', false)
            ->assertSee('R$ 3,50')->assertSee('Loja feita com');
        $this->get("/loja/doces-da-ana/img/{$p->imagem}")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/loja/doces-da-ana/img/' . str_repeat('a', 24) . '.png')->assertNotFound();
        $this->get('/carteira/loja')->assertRedirect('/login');
    }

    public function test_loja_fechada_so_o_dono_ve(): void
    {
        $u = $this->dono();
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Fechada', 'slug' => 'fechada'])->assertRedirect();
        $this->actingAs($u)->get('/loja/fechada')->assertOk()->assertSee('Prévia');
        auth()->logout();
        $this->get('/loja/fechada')->assertNotFound();
        $this->get('/loja/nao-existe')->assertNotFound();
    }

    public function test_endereco_unico_valido_e_imagem_svg_recusada(): void
    {
        $a = $this->dono();
        $this->lojaPronta($a);
        $b = $this->dono();
        $this->actingAs($b)->post('/carteira/loja', ['nome' => 'Outra', 'slug' => 'doces-da-ana'])->assertSessionHasErrors('slug');
        $this->actingAs($b)->post('/carteira/loja', ['nome' => 'Outra', 'slug' => 'Com Espaço'])->assertSessionHasErrors('slug');
        $this->actingAs($b)->post('/carteira/loja', ['nome' => 'Outra', 'slug' => 'admin'])->assertSessionHasErrors('slug');
        $this->actingAs($b)->post('/carteira/loja', [
            'nome' => 'Outra', 'slug' => 'outra-loja',
            'logo' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
        ])->assertSessionHasErrors('logo');
    }

    public function test_plano_gratuito_tem_limite_de_10_produtos(): void
    {
        $u = $this->dono();
        $loja = $this->lojaPronta($u);
        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($u)->post('/carteira/loja/produtos', ['nome' => "P{$i}", 'preco' => 1, 'ativo' => 1])->assertSessionHasNoErrors();
        }
        $this->actingAs($u)->post('/carteira/loja/produtos', ['nome' => 'P11', 'preco' => 1, 'ativo' => 1])->assertSessionHasErrors('nome');
        $this->assertSame(10, $loja->produtos()->count());

        $pago = $this->dono('basico');
        $loja2 = $this->lojaPronta2($pago);
        for ($i = 1; $i <= 11; $i++) {
            $this->actingAs($pago)->post('/carteira/loja/produtos', ['nome' => "P{$i}", 'preco' => 1, 'ativo' => 1])->assertSessionHasNoErrors();
        }
        $this->assertSame(11, $loja2->produtos()->count());
        auth()->logout();
        $this->get('/loja/loja-paga')->assertOk()->assertDontSee('Loja feita com');
    }

    private function lojaPronta2(User $u): Loja
    {
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Paga', 'slug' => 'loja-paga', 'publicada' => 1])->assertRedirect();

        return Loja::where('user_id', $u->id)->firstOrFail();
    }

    public function test_pedido_usa_o_preco_do_servidor_reserva_estoque_e_pix_vai_para_o_dono(): void
    {
        $u = $this->dono();
        $loja = $this->lojaPronta($u);
        $p = LojaProduto::forceCreate(['loja_id' => $loja->id, 'nome' => 'Bolo', 'preco' => 30, 'estoque' => 3, 'ativo' => true]);
        $escondido = LojaProduto::forceCreate(['loja_id' => $loja->id, 'nome' => 'Escondido', 'preco' => 1, 'ativo' => false]);

        auth()->logout();
        // tenta mudar o preço pelo carrinho: o servidor ignora
        $r = $this->post('/loja/doces-da-ana/pedido', ['nome' => 'Cliente', 'contato' => '81999998888',
            'itens' => json_encode([['id' => $p->id, 'qtd' => 2, 'preco' => 0.01]])]);
        $pedido = LojaPedido::firstOrFail();
        $r->assertRedirect("/loja/doces-da-ana/pedido/{$pedido->codigo}");
        $this->assertSame('60.00', (string) $pedido->total);
        $this->assertSame(1, $p->fresh()->estoque);

        $this->get("/loja/doces-da-ana/pedido/{$pedido->codigo}")->assertOk()->assertSee('ana@exemplo.com', false)->assertSee('R$ 60,00');
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'C', 'contato' => 'c@c.com', 'itens' => json_encode([['id' => $p->id, 'qtd' => 2]])])
            ->assertSessionHasErrors('itens');
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'C', 'contato' => 'c@c.com', 'itens' => json_encode([['id' => $escondido->id, 'qtd' => 1]])])
            ->assertSessionHasErrors('itens');

        $this->post("/loja/doces-da-ana/pedido/{$pedido->codigo}/paguei")->assertRedirect();
        $this->assertSame('informado', $pedido->fresh()->status);

        // outra conta não mexe no pedido; o dono cancela e o estoque volta
        $this->actingAs($this->dono())->post("/carteira/loja/pedidos/{$pedido->id}/status", ['status' => 'cancelado'])->assertNotFound();
        $this->actingAs($u)->post("/carteira/loja/pedidos/{$pedido->id}/status", ['status' => 'cancelado'])->assertRedirect();
        $this->assertSame('cancelado', $pedido->fresh()->status);
        $this->assertSame(3, $p->fresh()->estoque);
        $this->actingAs($u)->get('/carteira/loja?aba=pedidos')->assertOk()->assertSee($pedido->codigo);
    }

    public function test_loja_sem_pix_nao_recebe_pedido(): void
    {
        $u = $this->dono();
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Sem Pix', 'slug' => 'sem-pix', 'publicada' => 1]);
        $loja = Loja::first();
        $p = LojaProduto::forceCreate(['loja_id' => $loja->id, 'nome' => 'X', 'preco' => 5, 'ativo' => true]);
        auth()->logout();
        $this->get('/loja/sem-pix')->assertOk()->assertSee('ainda não está recebendo pedidos');
        $this->post('/loja/sem-pix/pedido', ['nome' => 'C', 'contato' => 'c@c.com', 'itens' => json_encode([['id' => $p->id, 'qtd' => 1]])]);
        $this->assertSame(0, LojaPedido::count());
    }
}
