<?php

namespace Tests\Feature;

use App\Models\PixPagamento;
use App\Models\User;
use App\Support\Pix;
use App\Support\Planos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Planos pagos por Pix direto na chave da administração, confirmados à mão em /admin/pix. */
class PixPagamentoTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'xexplosion20@gmail.com';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('plans')->insert([
            ['name' => 'Gratuito', 'slug' => 'gratuito', 'price' => 0, 'active' => 1, 'sort' => 1],
            ['name' => 'Básico', 'slug' => 'basico', 'price' => 19.90, 'active' => 1, 'sort' => 2],
        ]);
    }

    private function configurar(): void
    {
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'loja@exemplo.com', 'nome' => 'João da Silva', 'cidade' => 'São Paulo']);
    }

    public function test_crc16_bate_com_o_exemplo_do_banco_central(): void
    {
        $sem = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***6304';
        $this->assertSame('1D3D', Pix::crc16($sem));
    }

    public function test_copia_e_cola_tem_chave_valor_nome_e_crc(): void
    {
        $this->configurar();
        $c = Pix::copiaECola(19.9, 'WFABC123');

        $this->assertStringStartsWith('000201', $c);
        $this->assertStringContainsString('0014br.gov.bcb.pix0116loja@exemplo.com', $c);
        $this->assertStringContainsString('540519.90', $c);
        $this->assertStringContainsString('5913JOAO DA SILVA', $c);
        $this->assertStringContainsString('6009SAO PAULO', $c);
        $this->assertStringContainsString('0508WFABC123', $c);
        $this->assertSame(Pix::crc16(substr($c, 0, -4)), substr($c, -4));
    }

    public function test_sem_chave_configurada_nao_deixa_assinar(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/assinar/basico')->assertRedirect(route('planos'))->assertSessionHas('erro');
        $this->assertSame(0, PixPagamento::count());
    }

    public function test_assinar_gera_um_pix_e_reaproveita_no_segundo_clique(): void
    {
        $this->configurar();
        $u = User::factory()->create();

        $r = $this->actingAs($u)->get('/assinar/basico');
        $p = PixPagamento::sole();
        $r->assertRedirect(route('pix.ver', $p));
        $this->assertSame('19.90', $p->valor);

        $this->actingAs($u)->get('/assinar/basico')->assertRedirect(route('pix.ver', $p));
        $this->assertSame(1, PixPagamento::count());

        $this->actingAs($u)->get(route('pix.ver', $p))->assertOk()->assertSee($p->txid)->assertSee('19,90');
    }

    public function test_outra_pessoa_nao_ve_nem_mexe_no_pix(): void
    {
        $this->configurar();
        $dono = User::factory()->create();
        $this->actingAs($dono)->get('/assinar/basico');
        $p = PixPagamento::sole();

        $outro = User::factory()->create();
        $this->actingAs($outro)->get(route('pix.ver', $p))->assertNotFound();
        $this->actingAs($outro)->post(route('pix.paguei', $p))->assertNotFound();
        $this->actingAs($outro)->post(route('pix.cancelar', $p))->assertNotFound();
        $this->assertSame('aguardando', $p->fresh()->status);
    }

    public function test_ja_paguei_e_confirmacao_do_admin_liberam_o_plano(): void
    {
        $this->configurar();
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        $this->actingAs($u)->get('/assinar/basico');
        $p = PixPagamento::sole();

        $this->actingAs($u)->post(route('pix.paguei', $p), ['pagador' => 'Maria'])->assertRedirect(route('pix.ver', $p));
        $p->refresh();
        $this->assertSame('informado', $p->status);
        $this->assertSame('Maria', $p->pagador);
        $this->assertSame('gratuito', Planos::slugDe($u->fresh()));

        // só a administração confirma
        $this->actingAs($u)->post(route('pix.admin.confirmar', $p))->assertNotFound();

        $admin = User::factory()->create(['email' => self::ADMIN]);
        $this->actingAs($admin)->get('/admin/pix')->assertOk()->assertSee($u->email)->assertSee('Maria');
        $this->actingAs($admin)->post(route('pix.admin.confirmar', $p))->assertRedirect();

        $u->refresh();
        $this->assertSame('basico', Planos::slugDe($u));
        $this->assertSame('confirmado', $p->fresh()->status);
        $this->assertSame(now()->addMonthNoOverflow()->endOfDay()->format('Y-m-d H:i:s'), (string) $u->plano_expira_em);

        // confirmar de novo não soma outro mês
        $this->actingAs($admin)->post(route('pix.admin.confirmar', $p));
        $this->assertSame(now()->addMonthNoOverflow()->endOfDay()->format('Y-m-d H:i:s'), (string) $u->fresh()->plano_expira_em);

        // com o mês pago longe do fim, não deixa pagar de novo
        $this->actingAs($u)->get('/assinar/basico')->assertRedirect(route('planos'))->assertSessionHas('erro');
    }

    public function test_renovacao_soma_o_mes_ao_fim_do_periodo(): void
    {
        $this->configurar();
        $fim = now()->addDays(3)->endOfDay();
        $u = User::factory()->create(['plano_slug' => 'basico', 'plano_expira_em' => $fim]);

        $this->actingAs($u)->get('/assinar/basico')->assertRedirect();
        Pix::liberar(PixPagamento::sole());

        $this->assertSame($fim->copy()->addMonthNoOverflow()->endOfDay()->format('Y-m-d H:i:s'), (string) $u->fresh()->plano_expira_em);
    }

    public function test_admin_salva_e_valida_a_chave(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN]);

        $this->actingAs($admin)->post('/admin/pix/config', ['tipo' => 'cpf', 'chave' => '111.111.111-11', 'nome' => 'X', 'cidade' => 'Y'])
            ->assertSessionHasErrors('chave');
        $this->assertFalse(Pix::pronto());

        $this->actingAs($admin)->post('/admin/pix/config', ['tipo' => 'telefone', 'chave' => '(11) 98765-4321', 'nome' => 'Fulano', 'cidade' => 'Rio'])
            ->assertSessionHasNoErrors();
        $this->assertSame('+5511987654321', Pix::config()['chave']);

        $outro = User::factory()->create();
        $this->actingAs($outro)->post('/admin/pix/config', ['tipo' => 'email', 'chave' => 'eu@x.com', 'nome' => 'Eu', 'cidade' => 'Eu'])->assertNotFound();
        $this->actingAs($outro)->get('/admin/pix')->assertNotFound();
        $this->assertSame('+5511987654321', Pix::config()['chave']);
    }
}
