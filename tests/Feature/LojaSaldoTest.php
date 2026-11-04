<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\LojaPedido;
use App\Models\LojaProduto;
use App\Models\LojaResgate;
use App\Models\LojaSaldoMovimento;
use App\Models\User;
use App\Support\Pix;
use App\Support\SaldoLoja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Pix das lojas na chave da plataforma: saldo do dono, confirmação em /admin/pix, Mercado Pago e resgates em 7 dias úteis. */
class LojaSaldoTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'xexplosion20@gmail.com';

    private User $dono;
    private User $admin;
    private Loja $loja;
    private LojaProduto $prod;

    protected function setUp(): void
    {
        parent::setUp();
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->admin = User::factory()->create(['email' => self::ADMIN]);
        $this->dono = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $this->dono->id)->update(['plano_slug' => 'gratuito']);
        $this->actingAs($this->dono)->post('/carteira/loja', ['nome' => 'Doces da Ana', 'slug' => 'doces-da-ana', 'publicada' => 1])->assertRedirect();
        $this->loja = Loja::firstOrFail();
        $this->prod = LojaProduto::forceCreate(['loja_id' => $this->loja->id, 'nome' => 'Bolo', 'preco' => 50, 'estoque' => 10, 'ativo' => true]);
    }

    private function pedido(int $qtd = 2): LojaPedido
    {
        auth()->logout();
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'Bia', 'contato' => 'bia@x.com', 'itens' => json_encode([['id' => $this->prod->id, 'qtd' => $qtd]])])->assertRedirect();

        return LojaPedido::latest('id')->firstOrFail();
    }

    public function test_prazo_de_7_dias_uteis_pula_fim_de_semana(): void
    {
        // quarta 04/11/2026 → quinta 05, sex 06, seg 09, ter 10, qua 11, qui 12, sex 13
        $this->assertSame('2026-11-13', SaldoLoja::prazo(Carbon::parse('2026-11-04 15:00'))->toDateString());
        // sábado → seg 09 ... ter 17
        $this->assertSame('2026-11-17', SaldoLoja::prazo(Carbon::parse('2026-11-07 10:00'))->toDateString());
        // sexta → seg 09 ... ter 17
        $this->assertSame('2026-11-17', SaldoLoja::prazo(Carbon::parse('2026-11-06 23:00'))->toDateString());
    }

    public function test_admin_confirma_o_pix_do_pedido_e_o_valor_vira_saldo_uma_vez_so(): void
    {
        $p = $this->pedido();
        $this->assertSame('plataforma', $p->pix_destino);
        $this->get("/loja/doces-da-ana/pedido/{$p->codigo}")->assertOk()->assertSee('Work Flower')->assertSee('repassa o valor');
        $this->post("/loja/doces-da-ana/pedido/{$p->codigo}/paguei")->assertRedirect();
        $this->assertSame('informado', $p->fresh()->status);

        // o dono não marca como pago (quem vê o extrato é a plataforma)
        $this->actingAs($this->dono)->post("/carteira/loja/pedidos/{$p->id}/status", ['status' => 'pago'])->assertSessionHasErrors('status');
        $this->actingAs($this->dono)->post("/carteira/loja/pedidos/{$p->id}/status", ['status' => 'enviado'])->assertSessionHasErrors('status');
        $this->assertSame('informado', $p->fresh()->status);
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id));
        $this->assertSame(100.0, SaldoLoja::resumo($this->dono->id)['a_confirmar']);

        // só a administração vê e confirma
        $this->actingAs($this->dono)->post(route('pix.admin.loja.confirmar', $p))->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/pix')->assertOk()->assertSee('Pedidos de lojas')->assertSee($p->codigo)->assertSee('Doces da Ana');
        $this->actingAs($this->admin)->post(route('pix.admin.loja.confirmar', $p))->assertRedirect();
        $this->actingAs($this->admin)->post(route('pix.admin.loja.confirmar', $p))->assertRedirect(); // segundo clique não soma de novo
        $this->assertSame('pago', $p->fresh()->status);
        $this->assertSame(100.0, SaldoLoja::disponivel($this->dono->id));
        $this->assertSame(1, LojaSaldoMovimento::where('tipo', 'credito')->count());

        // pago: o dono pode marcar como enviado, mas não cancelar (o dinheiro já está no saldo)
        $this->actingAs($this->dono)->post("/carteira/loja/pedidos/{$p->id}/status", ['status' => 'cancelado'])->assertSessionHasErrors('status');
        $this->actingAs($this->dono)->post("/carteira/loja/pedidos/{$p->id}/status", ['status' => 'enviado'])->assertSessionHasNoErrors();
        $this->assertSame('enviado', $p->fresh()->status);
        $this->actingAs($this->dono)->get('/carteira/loja?aba=pix')->assertOk()->assertSee('R$ 100,00')->assertSee('Pedido ' . $p->codigo)->assertSee('Confira com o seu contador');
    }

    public function test_admin_nao_encontra_o_pix_e_o_pedido_volta_para_aguardando(): void
    {
        $p = $this->pedido();
        $this->post("/loja/doces-da-ana/pedido/{$p->codigo}/paguei");
        $this->actingAs($this->admin)->post(route('pix.admin.loja.recusar', $p), ['observacao' => 'Não caiu'])->assertRedirect();
        $this->assertSame('aguardando', $p->fresh()->status);
        $this->assertSame('Não caiu', $p->fresh()->admin_obs);
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id));
        $this->actingAs($this->dono)->get('/carteira/loja?aba=pedidos')->assertSee('Plataforma: Não caiu');
    }

    public function test_pedido_antigo_continua_na_chave_do_dono(): void
    {
        $this->loja->forceFill(['pix_tipo' => 'email', 'pix_chave' => 'ana@exemplo.com', 'pix_nome' => 'Ana Souza', 'pix_cidade' => 'Recife'])->save();
        $p = $this->pedido();
        $p->forceFill(['pix_destino' => 'loja'])->save(); // como os pedidos de antes da migração
        $this->get("/loja/doces-da-ana/pedido/{$p->codigo}")->assertOk()->assertSee('ana@exemplo.com', false)->assertSee('Ana Souza');
        // o dono confirma sozinho, como antes, e nada entra no saldo da plataforma
        $this->actingAs($this->dono)->post("/carteira/loja/pedidos/{$p->id}/status", ['status' => 'pago'])->assertSessionHasNoErrors();
        $this->assertSame('pago', $p->fresh()->status);
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id));
        $this->assertFalse(SaldoLoja::confirmarPedido($p->fresh()));
    }

    public function test_mercado_pago_confirma_sozinho_e_fica_no_extrato_sem_somar_ao_saldo(): void
    {
        $this->loja->forceFill(['mp_token' => 'APP_USR-1234567890-abcdef', 'mp_conta' => 'ANA'])->save();
        $p = $this->pedido();
        Http::fake(['api.mercadopago.com/v1/payments/42' => Http::response(['status' => 'approved', 'transaction_amount' => 100, 'external_reference' => $p->codigo])]);
        $this->get("/loja/doces-da-ana/pedido/{$p->codigo}?payment_id=42")->assertOk();
        $this->get("/loja/doces-da-ana/pedido/{$p->codigo}?payment_id=42")->assertOk();
        $this->assertSame('pago', $p->fresh()->status);
        $this->assertSame(1, LojaSaldoMovimento::where('tipo', 'mp')->where('loja_pedido_id', $p->id)->count());
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id)); // o dinheiro do cartão já caiu no Mercado Pago do dono
    }

    public function test_resgate_tira_do_saldo_tem_prazo_e_o_admin_paga_ou_recusa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00', 'America/Sao_Paulo'));
        $p = $this->pedido(); // R$ 100
        SaldoLoja::confirmarPedido($p);
        $chave = ['tipo' => 'telefone', 'chave' => '(81) 99999-8888', 'nome' => 'Ana Souza'];

        // mais que o saldo não passa
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => '100.01'] + $chave)->assertSessionHasErrors('valor');
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => '0.50'] + $chave)->assertSessionHasErrors('valor');
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => '60'] + $chave)->assertSessionHasNoErrors()->assertSessionHas('ok', fn ($m) => str_contains($m, '13/11/2026'));
        $r = LojaResgate::firstOrFail();
        $this->assertSame('2026-11-13', $r->prazo->toDateString());
        $this->assertSame('+5581999998888', $r->pix_chave);
        $this->assertSame(40.0, SaldoLoja::disponivel($this->dono->id));
        $this->assertSame('+5581999998888', $this->loja->fresh()->pix_chave); // a chave fica guardada para o próximo
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => '41'] + $chave)->assertSessionHasErrors('valor');
        $this->actingAs($this->dono)->get('/carteira/loja?aba=pix')->assertSee('até 13/11/2026')->assertSee('R$ 40,00');

        // admin vê o pendente com a chave e o prazo; outra conta não
        $this->actingAs($this->dono)->post(route('pix.admin.resgate.pagar', $r))->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/pix')->assertSee('Resgates pendentes')->assertSee('+5581999998888')->assertSee('13/11/2026');
        $this->actingAs($this->admin)->post(route('pix.admin.resgate.pagar', $r))->assertRedirect();
        $this->assertSame('pago', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->pago_em);
        $this->actingAs($this->admin)->post(route('pix.admin.resgate.recusar', $r))->assertRedirect(); // já pago: nada muda
        $this->assertSame(40.0, SaldoLoja::disponivel($this->dono->id));

        // recusado: o valor volta ao saldo
        $this->actingAs($this->dono)->post('/carteira/loja/resgates', ['valor' => '40'] + $chave)->assertSessionHasNoErrors();
        $r2 = LojaResgate::latest('id')->firstOrFail();
        $this->assertSame(0.0, SaldoLoja::disponivel($this->dono->id));
        $this->actingAs($this->admin)->post(route('pix.admin.resgate.recusar', $r2), ['observacao' => 'Chave inválida'])->assertRedirect();
        $this->assertSame('recusado', $r2->fresh()->status);
        $this->assertSame(40.0, SaldoLoja::disponivel($this->dono->id));
        $this->assertSame(1, LojaSaldoMovimento::where('tipo', 'estorno')->count());
        Carbon::setTestNow();
    }
}
