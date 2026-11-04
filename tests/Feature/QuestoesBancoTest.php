<?php

namespace Tests\Feature;

use App\Models\Tarefa;
use App\Models\User;
use App\Support\QuestoesBanco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Banco de questões abertas do cartão Questões (/cartoes/questoes*) e tarefas da área para Hábitos e RH (/cartoes/tarefas/{area}). */
class QuestoesBancoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pede_login(): void
    {
        $this->getJson('/cartoes/questoes')->assertStatus(401);
        $this->getJson('/cartoes/questoes/filtros')->assertStatus(401);
    }

    public function test_importa_o_arquivo_na_primeira_busca_e_traz_os_filtros(): void
    {
        $this->assertSame(0, DB::table('questoes_banco')->count());
        $r = $this->actingAs(User::factory()->create())->getJson('/cartoes/questoes/filtros')->assertOk();

        $this->assertGreaterThan(2000, $r->json('total'));
        $bancas = collect($r->json('bancas'))->pluck('nome');
        $this->assertTrue($bancas->contains('FGV'));
        $this->assertTrue($bancas->contains('INEP'));
        $this->assertContains('Direito Civil', collect($r->json('materias'))->pluck('nome'));
        $this->assertContains(2024, $r->json('anos'));
        $this->assertNotEmpty($r->json('assuntos.Direito Civil'));
        $this->assertCount(3, $r->json('dificuldades'));
    }

    public function test_filtra_e_pagina(): void
    {
        $u = User::factory()->create();
        QuestoesBanco::importar();

        $r = $this->actingAs($u)->getJson('/cartoes/questoes?banca=INEP&materia=Matem%C3%A1tica')->assertOk();
        $this->assertGreaterThan(20, $r->json('total'));
        $this->assertCount(20, $r->json('itens'));
        $this->assertSame(1, $r->json('pagina'));
        foreach ($r->json('itens') as $q) {
            $this->assertSame('INEP', $q['banca']);
            $this->assertSame('Matemática', $q['materia']);
            $this->assertCount(5, $q['alts']);
            $this->assertContains($q['certa'], [0, 1, 2, 3, 4]);
        }

        $p2 = $this->actingAs($u)->getJson('/cartoes/questoes?banca=INEP&materia=Matem%C3%A1tica&pagina=2')->assertOk();
        $this->assertSame(2, $p2->json('pagina'));
        $this->assertEmpty(array_intersect(collect($r->json('itens'))->pluck('id')->all(), collect($p2->json('itens'))->pluck('id')->all()));

        $ano = $this->actingAs($u)->getJson('/cartoes/questoes?ano=2016&dificuldade=dificil&banca=FGV')->assertOk();
        $this->assertGreaterThan(0, $ano->json('total'));
        foreach ($ano->json('itens') as $q) {
            $this->assertSame(2016, $q['ano']);
            $this->assertSame('dificil', $q['dificuldade']);
        }

        $nada = $this->actingAs($u)->getJson('/cartoes/questoes?busca=zzzznadaassimzzzz')->assertOk();
        $this->assertSame(0, $nada->json('total'));
        $this->assertSame([], $nada->json('itens'));

        $this->actingAs($u)->getJson('/cartoes/questoes?dificuldade=impossivel')->assertStatus(422);
    }

    public function test_simulado_sorteia_n_questoes_dentro_do_filtro(): void
    {
        $u = User::factory()->create();
        QuestoesBanco::importar();

        $r = $this->actingAs($u)->getJson('/cartoes/questoes/simulado?n=10&banca=FGV&materia=Direito%20Penal')->assertOk();
        $this->assertCount(10, $r->json('itens'));
        $this->assertCount(10, array_unique(collect($r->json('itens'))->pluck('id')->all()));
        foreach ($r->json('itens') as $q) {
            $this->assertSame('Direito Penal', $q['materia']);
        }
        $this->actingAs($u)->getJson('/cartoes/questoes/simulado?n=0')->assertStatus(422);
        $this->actingAs($u)->getJson('/cartoes/questoes/simulado?n=500')->assertStatus(422);
    }

    public function test_tarefas_da_area_e_criacao_em_lote(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        Tarefa::create(['user_id' => $u->id, 'area' => 'diverso', 'titulo' => 'Correr', 'status' => 'concluida']);
        Tarefa::create(['user_id' => $outro->id, 'area' => 'diverso', 'titulo' => 'De outra pessoa']);

        $r = $this->actingAs($u)->getJson('/cartoes/tarefas/diverso')->assertOk();
        $this->assertSame(['Correr'], collect($r->json('tarefas'))->pluck('titulo')->all());
        $this->assertSame(now()->format('Y-m-d'), $r->json('tarefas.0.concluida_em'));

        $this->actingAs($u)->postJson('/cartoes/tarefas/diverso', [
            'categoria' => 'Integração',
            'tarefas' => [['titulo' => 'Ana: Contrato assinado', 'prazo' => '2026-12-01'], ['titulo' => 'Ana: Acessos criados']],
        ])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(2, Tarefa::where('user_id', $u->id)->where('categoria', 'Integração')->count());

        $this->actingAs($u)->postJson('/cartoes/tarefas/diverso', ['tarefas' => []])->assertStatus(422);
        $this->actingAs($u)->getJson('/cartoes/tarefas/inexistente')->assertStatus(404);
    }
}
