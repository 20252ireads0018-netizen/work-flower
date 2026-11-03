<?php

namespace Tests\Feature;

use App\Http\Middleware\SoAdmin;
use App\Models\Reclamacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Só a conta de User::ADMINS entra nas páginas /admin; cada pessoa vê só as próprias reclamações. */
class AdminAcessoTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'xexplosion20@gmail.com';

    public function test_toda_rota_admin_passa_pelo_so_admin(): void
    {
        $admin = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin'));
        $this->assertNotEmpty($admin);
        foreach ($admin as $r) {
            $this->assertContains(SoAdmin::class, $r->gatherMiddleware(), "A rota /{$r->uri()} precisa do SoAdmin.");
        }
    }

    public function test_outras_contas_recebem_404_em_todas_as_abas_de_admin(): void
    {
        $dono = User::factory()->create();
        $r = Reclamacao::create(['user_id' => $dono->id, 'tipo' => 'problema', 'assunto' => 'Erro', 'mensagem' => 'Deu erro ao salvar.']);

        // e-mails parecidos não valem
        foreach (['outra@gmail.com', 'xexplosion20@gmail.com.br', 'x.explosion20@gmail.com', 'xexplosion20@hotmail.com'] as $email) {
            $u = User::factory()->create(['email' => $email]);
            $this->assertFalse($u->eAdmin());
            $this->actingAs($u)->get('/admin/reclamacoes')->assertNotFound();
            $this->actingAs($u)->get('/admin/anuncios')->assertNotFound();
            $this->actingAs($u)->get('/admin/anuncios/estudantes')->assertNotFound();
            $this->actingAs($u)->post("/admin/reclamacoes/{$r->id}", ['resposta' => 'invadi', 'status' => 'resolvida'])->assertNotFound();
            $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee('Reclamações (admin)')->assertDontSee('Animações de anúncio');
        }
        $this->assertNull($r->fresh()->resposta);
        $this->assertSame('aberta', $r->fresh()->status);

        // visitante vai para o login
        auth()->logout();
        $this->get('/admin/reclamacoes')->assertRedirect('/login');
    }

    public function test_a_conta_admin_entra_em_todas(): void
    {
        $admin = User::factory()->create(['email' => self::ADMIN]);
        $this->actingAs($admin)->get('/admin/reclamacoes')->assertOk();
        $this->actingAs($admin)->get('/admin/anuncios')->assertOk();
        $this->actingAs($admin)->get('/admin/anuncios/estudantes')->assertOk();
        $this->actingAs($admin)->get('/painel')->assertOk()->assertSee('Reclamações (admin)');
    }

    public function test_cada_pessoa_ve_so_as_proprias_reclamacoes(): void
    {
        $ana = User::factory()->create();
        $bia = User::factory()->create();
        Reclamacao::create(['user_id' => $ana->id, 'tipo' => 'problema', 'assunto' => 'Assunto da Ana', 'mensagem' => 'Mensagem só da Ana aqui.']);
        $daBia = Reclamacao::create(['user_id' => $bia->id, 'tipo' => 'sugestao', 'assunto' => 'Assunto da Bia', 'mensagem' => 'Mensagem só da Bia aqui.']);
        $daBia->forceFill(['resposta' => 'Resposta para a Bia', 'respondida_em' => now(), 'resposta_vista' => false, 'status' => 'respondida'])->save();

        $this->actingAs($ana)->get('/reclame-aqui')->assertOk()
            ->assertSee('Assunto da Ana')->assertDontSee('Assunto da Bia')->assertDontSee('Resposta para a Bia');
        // abrir a página da Ana não marca como vista a resposta da Bia
        $this->assertFalse((bool) $daBia->fresh()->resposta_vista);

        $this->actingAs($bia)->get('/reclame-aqui')->assertOk()
            ->assertSee('Assunto da Bia')->assertSee('Resposta para a Bia')->assertDontSee('Assunto da Ana');
        $this->assertTrue((bool) $daBia->fresh()->resposta_vista);

        // quem envia não escolhe o dono da mensagem
        $this->actingAs($ana)->post('/reclame-aqui', ['tipo' => 'elogio', 'assunto' => 'Gostei', 'mensagem' => 'Gostei muito do site.', 'user_id' => $bia->id])
            ->assertRedirect('/reclame-aqui');
        $this->assertSame($ana->id, Reclamacao::where('assunto', 'Gostei')->value('user_id'));
    }
}
