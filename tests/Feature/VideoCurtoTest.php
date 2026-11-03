<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Animações de anúncio (/admin/anuncios): só a administração vê; o antigo /video-curto saiu. */
class VideoCurtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_as_animacoes_e_cada_uma_abre(): void
    {
        $admin = User::factory()->create(['email' => 'xexplosion20@gmail.com']);

        $this->actingAs($admin)->get('/admin/anuncios')->assertOk()
            ->assertSee('Quem não se organiza não passa')->assertSee('Vende muito e o dinheiro some?')->assertDontSee('Sua batida no navegador');
        foreach (array_keys(\App\Support\Anuncios::LISTA) as $slug) {
            $this->actingAs($admin)->get('/admin/anuncios/' . $slug)->assertOk()
                ->assertSee('id="palco"', false)->assertSee('data-arquivo="work-flower-', false)->assertSee('js/video-curto.js', false);
        }
        $this->actingAs($admin)->get('/admin/anuncios/nao-existe')->assertNotFound();
        $this->actingAs($admin)->get('/painel')->assertSee('Animações de anúncio (só você)');
        foreach (['estudio', 'ambiente', 'tres'] as $antiga) {
            $this->actingAs($admin)->get('/admin/anuncios/' . $antiga)->assertNotFound();
        }
        $this->actingAs($admin)->get('/admin/anuncios/estudantes')->assertSee('Só 30 vagas')->assertSee('Premium grátis');
    }

    public function test_outras_contas_nao_veem(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/admin/anuncios')->assertNotFound();
        $this->actingAs($u)->get('/admin/anuncios/estudio')->assertNotFound();
        $this->actingAs($u)->get('/painel')->assertDontSee('Animações de anúncio');
        $this->actingAs($u)->get('/video-curto')->assertNotFound();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/admin/anuncios')->assertRedirect('/login');
    }
}
