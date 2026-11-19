<?php

namespace Tests\Feature;

use App\Mail\AtivacaoEmail;
use App\Models\CorpoDado;
use App\Models\PixPagamento;
use App\Models\User;
use App\Support\Ativacao;
use App\Support\Pix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Sequência de ativação por e-mail (emails:ativacao) e o evento Subscribe do Pixel depois do Pix confirmado. */
class AtivacaoEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-11-20 10:00:00');
        DB::table('plans')->insert(['name' => 'Básico', 'slug' => 'basico', 'price' => 49.90, 'active' => 1, 'sort' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function novo(): User
    {
        return User::factory()->create(['created_at' => now(), 'email' => 'ana@exemplo.com', 'name' => 'Ana Souza']);
    }

    private function etapasEnviadas(): array
    {
        return Mail::sent(AtivacaoEmail::class)->map(fn ($m) => $m->etapa)->all();
    }

    public function test_sequencia_dia_0_1_3_7_uma_vez_cada(): void
    {
        $u = $this->novo();
        $this->artisan('emails:ativacao')->assertSuccessful();
        $this->artisan('emails:ativacao')->assertSuccessful(); // não repete
        $this->assertSame(['d0'], $this->etapasEnviadas());

        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('emails:ativacao');
        Carbon::setTestNow(now()->addHours(50)); // 75 h
        $this->artisan('emails:ativacao');
        Carbon::setTestNow(now()->addHours(95)); // 170 h
        $this->artisan('emails:ativacao');
        Carbon::setTestNow(now()->addDays(10));
        $this->artisan('emails:ativacao');

        $this->assertSame(['d0', 'd1', 'd3', 'd7'], $this->etapasEnviadas());
        $this->assertSame(['d0', 'd1', 'd3', 'd7'], array_keys(Ativacao::estado($u->id)['enviados']));
    }

    public function test_dia_3_muda_se_ja_usou_a_revisao_e_dia_7_mostra_o_preco(): void
    {
        $u = $this->novo();
        Mail::assertNothingSent();
        $d3 = new AtivacaoEmail($u, 'd3', false);
        $this->assertSame('Sua primeira revisão ainda está esperando', $d3->envelope()->subject);
        $d3->assertSeeInHtml('/areas/mente?aba=estudos');

        CorpoDado::create(['user_id' => $u->id, 'chave' => 'mente.estado', 'valor' => '{"blocos":[{"tipo":"flashcards"}]}']);
        $this->assertTrue(Ativacao::usouRevisao($u->id));
        $usou = new AtivacaoEmail($u, 'd3', true);
        $this->assertStringContainsString('calendário', $usou->envelope()->subject);

        $d7 = new AtivacaoEmail($u, 'd7');
        $d7->assertSeeInHtml('R$ 49,90 por mês');
        $d7->assertSeeInHtml('Seu teste grátis do plano Básico vai até');
        $d7->assertDontSeeInHtml('vitalíc'); // nada de promessa automática da vitalícia
        $d7->assertSeeInText('Para não receber mais:');
    }

    public function test_quem_paga_nao_recebe_a_oferta_e_conta_antiga_nao_recebe_nada(): void
    {
        $u = User::factory()->create(['created_at' => now()->subHours(170)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => 'basico', 'plano_expira_em' => now()->addMonth()]);
        $this->assertNull(Ativacao::devida($u->fresh()));

        User::factory()->create(['created_at' => '2026-11-10 10:00:00']); // antes da sequência existir
        $this->artisan('emails:ativacao');
        Mail::assertNothingSent();
    }

    public function test_descadastro_pelo_link_assinado(): void
    {
        $u = $this->novo();
        $m = new AtivacaoEmail($u, 'd0');
        $m->assertSeeInHtml('Não quero mais receber estas dicas');
        $this->assertStringContainsString('/emails/sair/' . $u->id, $m->headers()->text['List-Unsubscribe']);

        $this->get('/emails/sair/' . $u->id)->assertForbidden(); // sem assinatura
        $this->get(URL::signedRoute('emails.sair', ['user' => $u->id]))->assertOk()->assertSee('você saiu da lista');
        $this->artisan('emails:ativacao');
        Mail::assertNothingSent();
    }

    public function test_pixel_manda_subscribe_uma_vez_depois_do_pix_confirmado(): void
    {
        config(['services.meta.pixel' => '1115433257784212']);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);
        $u = User::factory()->create(['created_at' => now()->subDays(40)]);
        $pix = PixPagamento::create(['user_id' => $u->id, 'plano_slug' => 'basico', 'valor' => 49.90, 'txid' => 'WFASSINA0000001', 'status' => 'informado']);

        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee("'Subscribe'", false);
        Pix::liberar($pix);

        $html = $this->actingAs($u)->get('/painel')->assertOk()->getContent();
        $this->assertStringContainsString("fbq('track', 'Subscribe', {\"value\":49.9,\"currency\":\"BRL\",\"content_name\":\"Plano B\u00e1sico\"}", $html);
        $this->assertStringContainsString('wf-assinatura-' . $pix->id, $html);
        // pageview e cadastro continuam
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);
        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee("'Subscribe'", false);
    }
}
