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
        $this->actingAs($u)->putJson('/preferencias', ['atalhos' => ['novo.texto' => 'Alt+Q', 'excluir' => '', 'ruim id!' => 'Alt+Z', 'topo' => '<b>x']])->assertOk();
        // <script> nem chega ao controller: o filtro de ataques (FiltroAtaques) recusa antes
        $this->actingAs($u)->putJson('/preferencias', ['atalhos' => ['topo' => '<script>']])->assertForbidden();

        $p = PreferenciasController::de($u->id);
        $this->assertFalse($p['organizarAuto']);
        $this->assertSame(['novo.texto' => 'Alt+Q', 'excluir' => ''], $p['atalhos']);
    }

    public function test_salva_textos_rapidos_limpando_o_que_nao_serve(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->putJson('/preferencias', [
            'expansorLigado' => false,
            'trechos' => [
                ['atalho' => ';ass', 'texto' => "Abraço,\n{nome}\n{data}", 'nome' => 'Assinatura'],
                ['atalho' => ';ass', 'texto' => 'repetido'],          // atalho repetido: fica o primeiro
                ['atalho' => 'com espaco', 'texto' => 'x'],            // atalho com espaço
                ['atalho' => ';vazio', 'texto' => '   '],              // sem texto
                ['atalho' => str_repeat('a', 31), 'texto' => 'x'],     // atalho longo demais
                ['atalho' => ';grande', 'texto' => str_repeat('y', 6000)],
                'nao e um trecho',
            ],
        ])->assertOk();

        $p = PreferenciasController::de($u->id);
        $this->assertFalse($p['expansorLigado']);
        $this->assertSame([';ass', ';grande'], array_column($p['trechos'], 'atalho'));
        $this->assertSame("Abraço,\n{nome}\n{data}", $p['trechos'][0]['texto']);
        $this->assertSame('Assinatura', $p['trechos'][0]['nome']);
        $this->assertSame(PreferenciasController::MAX_TEXTO, mb_strlen($p['trechos'][1]['texto']));

        // salvar outra coisa não apaga os textos
        $this->actingAs($u)->putJson('/preferencias', ['organizarAuto' => true])->assertOk();
        $this->assertCount(2, PreferenciasController::de($u->id)['trechos']);

        // lista vazia apaga tudo; mais de 200 é recusado
        $this->actingAs($u)->putJson('/preferencias', ['trechos' => []])->assertOk();
        $this->assertSame([], PreferenciasController::de($u->id)['trechos']);
        $muitos = array_map(fn ($i) => ['atalho' => ";t$i", 'texto' => 'x'], range(1, 201));
        $this->actingAs($u)->putJson('/preferencias', ['trechos' => $muitos])->assertUnprocessable();
    }

    public function test_layout_carrega_o_expansor_com_o_nome_da_conta(): void
    {
        $u = User::factory()->create(['name' => 'Maria Souza']);

        $this->actingAs($u)->get('/painel')->assertOk()
            ->assertSee('js/expansor.js', false)
            ->assertSee('data-nome="Maria Souza"', false)
            ->assertSee('id="wf-expansor-painel"', false);
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
