<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NovosUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_quantas_e_quais_contas_novas_e_marca_como_visto(): void
    {
        $admin = User::factory()->create(['email' => User::ADMINS[0], 'created_at' => now()->subYear()]);
        User::factory()->create(['name' => 'Antiga Pessoa', 'created_at' => now()->subMonth()]);
        User::factory()->create(['name' => 'Ana Nova', 'email' => 'ana@exemplo.com']);
        User::factory()->create(['name' => 'Beto Novo', 'email' => 'beto@exemplo.com']);

        $this->actingAs($admin)->get('/painel')->assertOk()
            ->assertSee('2 contas novas')->assertSee('Ana Nova')->assertSee('beto@exemplo.com')->assertDontSee('Antiga Pessoa');

        $this->actingAs($admin)->post('/admin/novos-usuarios/visto')->assertRedirect(route('painel'));
        $this->actingAs($admin)->get('/painel')->assertOk()->assertDontSee('contas novas')->assertDontSee('Ana Nova');

        User::factory()->create(['name' => 'Caio Chegou']);
        $this->actingAs($admin)->get('/painel')->assertSee('1 conta nova')->assertSee('Caio Chegou')->assertDontSee('Ana Nova');
    }

    public function test_quem_nao_e_admin_nao_ve_nem_marca(): void
    {
        $u = User::factory()->create();
        User::factory()->create(['name' => 'Ana Nova']);

        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee('contas novas')->assertDontSee('Ana Nova');
        $this->actingAs($u)->post('/admin/novos-usuarios/visto')->assertNotFound();
    }
}
