<?php

namespace Tests\Feature;

use App\Http\Controllers\PreferenciasController;
use App\Models\CorpoDado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Preferências da conta (atalhos, organização automática, tutorial) e o tutorial automático do painel. */
class PreferenciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_salva_e_junta_as_preferencias(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->putJson('/preferencias', ['organizarAuto' => false])->assertOk();
        $this->actingAs($u)->putJson('/preferencias', ['atalhos' => ['novo.texto' => 'Alt+Q', 'excluir' => '', 'ruim id!' => 'Alt+Z', 'topo' => '<script>']])->assertOk();

        $p = PreferenciasController::de($u->id);
        $this->assertFalse($p['organizarAuto']);
        $this->assertSame(['novo.texto' => 'Alt+Q', 'excluir' => ''], $p['atalhos']);
    }

    public function test_precisa_estar_logado(): void
    {
        $this->putJson('/preferencias', ['organizarAuto' => true])->assertUnauthorized();
    }

    public function test_tutorial_abre_sozinho_so_na_conta_nova(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertViewHas('tutorialAuto', true);

        // depois de visto, não abre mais sozinho
        $this->actingAs($u)->putJson('/preferencias', ['tutorialVisto' => true])->assertOk();
        $this->actingAs($u)->get('/painel')->assertViewHas('tutorialAuto', false);
    }

    public function test_conta_que_ja_usou_o_site_nao_ve_o_tutorial_sozinho(): void
    {
        $u = User::factory()->create();
        CorpoDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => '{}']);

        $this->actingAs($u)->get('/painel')->assertViewHas('tutorialAuto', false);
    }
}
