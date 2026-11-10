<?php

namespace Tests\Feature;

use App\Models\PixPagamento;
use App\Models\Promocao;
use App\Models\User;
use App\Support\Calendario;
use App\Support\Pix;
use App\Support\Planos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Promoções com aviso uma vez por pessoa, edição/desativação pelo admin e a compra vitalícia
 * ("Pague uma vez, receba para sempre") do Pix gerado até a confirmação em /admin/pix.
 */
class PromocaoVitalicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('plans')->insert([
            ['name' => 'Gratuito', 'slug' => 'gratuito', 'price' => 0, 'active' => 1, 'sort' => 1],
            ['name' => 'Básico', 'slug' => 'basico', 'price' => 19.90, 'active' => 1, 'sort' => 2],
        ]);
        Pix::salvarConfig(['tipo' => 'email', 'chave' => 'loja@exemplo.com', 'nome' => 'Joao', 'cidade' => 'Sao Paulo']);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => User::ADMINS[0]]);
    }

    private function fim(int $horas = 48): string
    {
        return now(Calendario::FUSO)->addHours($horas)->format('Y-m-d\TH:i');
    }

    private function criarVitalicio(User $admin, array $extra = []): Promocao
    {
        $this->actingAs($admin)->post('/admin/promocoes', $extra + [
            'modalidade' => 'vitalicio', 'plano_slug' => 'basico', 'preco_cheio' => 497, 'tipo' => 'preco', 'valor' => 197,
            'termina_em' => $this->fim(), 'descricao' => 'Todos os recursos do Básico, para sempre.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Promocao::latest('id')->firstOrFail();
    }

    public function test_admin_cria_vitalicio_com_preco_cheio_e_promocional(): void
    {
        $p = $this->criarVitalicio($this->admin());

        $this->assertTrue($p->vitalicio);
        $this->assertSame('497.00', (string) $p->preco_cheio);
        $this->assertSame('197.00', (string) $p->preco);
        $this->assertSame('Pague uma vez, receba para sempre', $p->titulo);
        // a oferta vitalícia não muda o preço do mês
        $this->assertSame(19.90, Promocao::precoDe('basico', 19.90));
    }

    public function test_vitalicio_valida_preco_e_desconto_usa_o_preco_cheio(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/promocoes', [
            'modalidade' => 'vitalicio', 'plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 100, 'termina_em' => $this->fim(),
        ])->assertSessionHasErrors('preco_cheio');
        $this->actingAs($admin)->post('/admin/promocoes', [
            'modalidade' => 'vitalicio', 'plano_slug' => 'basico', 'preco_cheio' => 100, 'tipo' => 'preco', 'valor' => 150, 'termina_em' => $this->fim(),
        ])->assertSessionHasErrors('valor');
        $this->assertSame(0, Promocao::count());

        $this->actingAs($admin)->post('/admin/promocoes', [
            'modalidade' => 'vitalicio', 'plano_slug' => 'basico', 'preco_cheio' => 400, 'tipo' => 'desconto', 'valor' => 25, 'termina_em' => $this->fim(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('300.00', (string) Promocao::sole()->preco);
    }

    public function test_cada_pessoa_ve_o_aviso_uma_vez(): void
    {
        $antiga = User::factory()->create();
        $this->actingAs($antiga)->get('/planos')->assertOk()->assertDontSee('wf-promo-titulo', false);

        $p = $this->criarVitalicio($this->admin());

        // primeira página depois da promoção: aviso com preço cheio, promocional e o link do Pix vitalício
        $this->actingAs($antiga)->get('/painel')->assertOk()
            ->assertSee('wf-promo-titulo', false)->assertSee('R$ 497,00')->assertSee('R$ 197,00')
            ->assertSee(route('assinar.vitalicio', $p), false);
        $this->assertDatabaseHas('promocao_vistas', ['promocao_id' => $p->id, 'user_id' => $antiga->id]);
        // depois não aparece mais
        $this->actingAs($antiga)->get('/painel')->assertOk()->assertDontSee('wf-promo-titulo', false);

        // outra pessoa também vê uma vez
        $outra = User::factory()->create();
        $this->actingAs($outra)->get('/painel')->assertOk()->assertSee('wf-promo-titulo', false);
        $this->assertSame(2, DB::table('promocao_vistas')->count());
    }

    public function test_promocao_desativada_ou_agendada_nao_avisa(): void
    {
        $admin = $this->admin();
        $p = $this->criarVitalicio($admin);
        $this->actingAs($admin)->post(route('promocoes.admin.alternar', $p))->assertRedirect();
        $this->assertNotNull($p->fresh()->encerrada_em);

        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee('wf-promo-titulo', false);
        $this->actingAs($u)->get(route('assinar.vitalicio', $p))->assertRedirect(route('planos'))->assertSessionHas('erro');

        // reativada: aparece
        $this->actingAs($admin)->post(route('promocoes.admin.alternar', $p))->assertRedirect();
        $this->assertNull($p->fresh()->encerrada_em);
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('wf-promo-titulo', false);

        // agendada para depois: ninguém vê ainda
        $this->actingAs($admin)->post('/admin/promocoes', [
            'plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 9.9,
            'comeca_em' => $this->fim(5), 'termina_em' => $this->fim(30),
        ])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create())->get('/planos')->assertOk()->assertSee('R$ 197,00'); // só a vitalícia
        $this->assertSame(0, DB::table('promocao_vistas')->where('promocao_id', Promocao::max('id'))->count());
    }

    public function test_admin_edita_preco_e_prazo(): void
    {
        $admin = $this->admin();
        $p = $this->criarVitalicio($admin);

        $this->actingAs($admin)->post(route('promocoes.admin.editar', $p), [
            'preco' => 147, 'preco_cheio' => 597, 'termina_em' => $this->fim(72), 'titulo' => 'Black Friday', 'descricao' => 'Só esta semana',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('147.00', (string) $p->preco);
        $this->assertSame('597.00', (string) $p->preco_cheio);
        $this->assertSame('Black Friday', $p->titulo);

        // preço acima do cheio não passa
        $this->actingAs($admin)->post(route('promocoes.admin.editar', $p), ['preco' => 700, 'termina_em' => $this->fim()])
            ->assertSessionHasErrors('preco', null, 'editar' . $p->id);
        $this->assertSame('147.00', (string) $p->fresh()->preco);

        $this->actingAs($admin)->get('/admin/promocoes')->assertOk()->assertSee('Vitalício')->assertSee('Black Friday');
        // quem não é admin não edita
        $this->actingAs(User::factory()->create())->post(route('promocoes.admin.editar', $p), ['preco' => 1, 'termina_em' => $this->fim()])->assertNotFound();
    }

    public function test_compra_vitalicia_de_ponta_a_ponta(): void
    {
        $admin = $this->admin();
        $p = $this->criarVitalicio($admin);
        $u = User::factory()->create(['plano_slug' => 'basico', 'plano_expira_em' => now()->addDays(10)]);

        // tela de planos mostra a oferta
        $this->actingAs($u)->get('/planos')->assertOk()->assertSee('Pague uma vez, receba para sempre')->assertSee('R$ 197,00');

        // gera o Pix vitalício com o preço promocional (dois cliques = o mesmo pedido)
        $this->actingAs($u)->get(route('assinar.vitalicio', $p))->assertRedirect();
        $this->actingAs($u)->get(route('assinar.vitalicio', $p))->assertRedirect();
        $pix = PixPagamento::sole();
        $this->assertTrue($pix->vitalicio);
        $this->assertSame('197.00', (string) $pix->valor);
        $this->assertSame($p->id, (int) $pix->promocao_id);
        $this->actingAs($u)->get(route('pix.ver', $pix))->assertOk()->assertSee('vitalício')->assertSee('5406197.00', false);

        $this->actingAs($u)->post(route('pix.paguei', $pix), ['pagador' => 'Fulano'])->assertRedirect();
        $this->actingAs($admin)->get('/admin/pix')->assertOk()->assertSee('Vitalício');
        $this->actingAs($admin)->post(route('pix.admin.confirmar', $pix))->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, 'para sempre'));

        $u->refresh();
        $this->assertSame('basico', $u->plano_slug);
        $this->assertNull($u->plano_expira_em);
        $this->assertTrue((bool) $u->plano_vitalicio);
        $this->assertSame('confirmado', $pix->fresh()->status);
        $this->assertNull($pix->fresh()->liberado_ate);

        // anos depois continua no plano
        $this->travel(5)->years();
        $this->assertSame('basico', Planos::slugPago($u->fresh()));
        $this->travelBack();

        // não paga mês nem compra o vitalício de novo; o aviso de venda não aparece mais
        $this->actingAs($u)->get('/assinar/basico')->assertRedirect(route('planos'))->assertSessionHas('erro');
        $this->actingAs($u)->get(route('assinar.vitalicio', $p))->assertRedirect(route('planos'))->assertSessionHas('erro');
        $this->assertSame(1, PixPagamento::count());
        $this->actingAs($u)->get('/planos')->assertOk()->assertSee('Seu plano · vitalício')->assertDontSee('Quero para sempre');
        $this->actingAs($u)->get(route('pix.ver', $pix))->assertOk()->assertSee('liberado para sempre');
    }

    public function test_pix_mensal_antigo_confirmado_depois_nao_tira_o_vitalicio(): void
    {
        $admin = $this->admin();
        $p = $this->criarVitalicio($admin);
        $u = User::factory()->create();

        $this->actingAs($u)->get('/assinar/basico')->assertRedirect();
        $mensal = PixPagamento::sole();
        $this->actingAs($u)->post(route('pix.paguei', $mensal))->assertRedirect();
        $this->actingAs($u)->get(route('assinar.vitalicio', $p))->assertRedirect();
        $vit = PixPagamento::where('vitalicio', true)->sole();
        $this->assertSame('informado', $mensal->fresh()->status); // já avisou que pagou: não é cancelado

        $this->actingAs($admin)->post(route('pix.admin.confirmar', $vit));
        $this->actingAs($admin)->post(route('pix.admin.confirmar', $mensal));

        $u->refresh();
        $this->assertNull($u->plano_expira_em);
        $this->assertTrue((bool) $u->plano_vitalicio);
        $this->assertSame('confirmado', $mensal->fresh()->status);
    }

    public function test_promocao_mensal_continua_funcionando_com_aviso(): void
    {
        $this->actingAs($this->admin())->post('/admin/promocoes', [
            'plano_slug' => 'basico', 'tipo' => 'preco', 'valor' => 9.9, 'termina_em' => $this->fim(),
        ])->assertSessionHasNoErrors();
        $p = Promocao::sole();
        $this->assertFalse($p->vitalicio);

        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('wf-promo-titulo', false)->assertSee('Ver planos');
        $this->actingAs($u)->get('/assinar/basico')->assertRedirect();
        $this->assertSame('9.90', (string) PixPagamento::sole()->valor);
        $this->assertFalse(PixPagamento::sole()->vitalicio);
    }
}
