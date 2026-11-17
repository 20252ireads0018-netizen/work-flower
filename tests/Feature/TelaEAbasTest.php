<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Engrenagem › Tela (zoom e tipo de aparelho), ordem das abas, faixa de abas no celular e popup de editar tarefa. */
class TelaEAbasTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_zoom_e_aparelho_no_layout(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/areas/corpo')->assertOk()
            ->assertSee("localStorage.getItem('wf-zoom'", false)       // aplicado antes de pintar
            ->assertSee('id="aparelho-sel"', false)
            ->assertSee('wf-abas-nav', false)                          // faixa de abas no celular
            ->assertSee('wf-so-pc', false)                             // "Reorganizar" some no celular
            ->assertSee("details.tq-edit[open]", false);               // popup de editar tarefa dentro da janela
    }

    public function test_abas_do_diverso_podem_mudar_de_ordem(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/areas/diverso')->assertOk()
            ->assertSee('moverAba(a, -1)', false)
            ->assertSee('abaArrastoSolta($event, a)', false);
        $js = file_get_contents(public_path('js/diverso.js'));
        $this->assertStringContainsString('trocarOrdemAba(id, alvoId, depois)', $js);
    }
}
