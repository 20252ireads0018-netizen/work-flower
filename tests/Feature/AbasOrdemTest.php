<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ordem das abas fixas das áreas (js/abas-ordem.js): arrastar salva ordemAbas nas preferências da conta. */
class AbasOrdemTest extends TestCase
{
    use RefreshDatabase;

    public function test_salva_a_ordem_e_filtra_o_que_nao_e_id(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/preferencias', ['ordemAbas' => [
            'mente' => ['livros', 'tarefas', 'livros', 'nome com espaço', 'estudos'],
            'Área inválida!' => ['x'],
            'corpo' => 'nao-e-lista',
        ]])->assertOk()->assertJsonPath('preferencias.ordemAbas', ['mente' => ['livros', 'tarefas', 'estudos']]);
    }

    public function test_areas_carregam_o_arrastar_das_abas(): void
    {
        $u = User::factory()->create();
        foreach (['mente', 'corpo', 'carteira'] as $area) {
            $this->actingAs($u)->get('/areas/' . $area)->assertOk()
                ->assertSee('js/abas-ordem.js', false)
                ->assertSee("WFAbasOrdem.aplicar('{$area}', abas)", false);
        }
    }
}
