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

    public function test_areas_ineditas_com_comentario_e_enem_antigo(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->getJson('/cartoes/questoes/filtros')->assertOk();

        $areas = collect($r->json('areas'))->pluck('n', 'nome');
        foreach (['ENEM', 'Direito', 'Tecnologia', 'Administração', 'Português', 'Raciocínio lógico e matemática', 'Saúde'] as $a) {
            $this->assertGreaterThan(30, $areas[$a] ?? 0, "área com poucas questões: $a");
        }
        $this->assertGreaterThan(1000, $areas['ENEM']); // 2009–2017 + 2022–2024
        $this->assertContains(2012, $r->json('anos'));
        $this->assertGreaterThan(300, $r->json('comentadas'));
        $this->assertContains(QuestoesBanco::BANCA_INEDITAS, collect($r->json('bancas'))->pluck('nome'));

        // filtro por área + só comentadas: inéditas, com gabarito comentado e dificuldade definida (não estimada)
        $t = $this->actingAs($u)->getJson('/cartoes/questoes?area=Tecnologia&comentadas=1&materia=Redes%20de%20computadores')->assertOk();
        $this->assertGreaterThan(10, $t->json('total'));
        foreach ($t->json('itens') as $q) {
            $this->assertSame('Tecnologia', $q['area']);
            $this->assertSame('wf', $q['fonte']);
            $this->assertNotEmpty($q['comentario']);
            $this->assertStringStartsWith('Questão inédita', $q['prova']);
            $this->assertLessThan(count(array_filter($q['alts'])), $q['certa'] + 0.5);
        }
        // certo/errado do estilo Cebraspe: duas alternativas
        $ce = DB::table('questoes_banco')->where('fonte', 'wf')->where('alts', 'like', '["Certo","Errado"%')->count();
        $this->assertGreaterThan(50, $ce);

        // ENEM antigo entra com a matéria pela posição no caderno (2009: Natureza primeiro)
        $this->assertSame('Ciências da Natureza', DB::table('questoes_banco')->where('ref', 'ENEM_2009_1')->value('materia'));
        $this->assertSame('ENEM', DB::table('questoes_banco')->where('ref', 'ENEM_2009_1')->value('area'));
        $this->assertSame('Direito', DB::table('questoes_banco')->where('fonte', 'oab')->value('area'));
    }

    public function test_reimporta_quando_os_arquivos_mudam(): void
    {
        $u = User::factory()->create();
        $total = $this->actingAs($u)->getJson('/cartoes/questoes/filtros')->json('total');
        DB::table('questoes_banco')->where('fonte', 'wf')->delete();
        // mesma versão: não reimporta
        $this->assertLessThan($total, $this->actingAs($u)->getJson('/cartoes/questoes/filtros')->json('total'));
        // versão diferente (arquivo novo ou mudado): reimporta tudo
        \Illuminate\Support\Facades\Cache::put('questoes-banco-versao', 'antiga');
        \Illuminate\Support\Facades\Cache::forget('questoes-banco-filtros');
        $this->assertSame($total, $this->actingAs($u)->getJson('/cartoes/questoes/filtros')->json('total'));
    }

    public function test_arquivos_de_questoes_ineditas_estao_completos(): void
    {
        $refs = [];
        foreach (glob(database_path('data/questoes/originais/*.json')) as $arq) {
            $d = json_decode(file_get_contents($arq), true);
            $this->assertIsArray($d, basename($arq) . ' não é JSON válido');
            $this->assertNotEmpty($d['area']);
            foreach ($d['questoes'] as $q) {
                foreach (['ref', 'materia', 'assunto', 'enunciado', 'comentario'] as $c) {
                    $this->assertNotEmpty($q[$c] ?? '', "{$q['ref']}: falta $c");
                }
                $this->assertArrayNotHasKey($q['ref'], $refs, "ref repetida: {$q['ref']}");
                $refs[$q['ref']] = true;
                $this->assertContains($q['dificuldade'], ['facil', 'media', 'dificil']);
                $this->assertTrue(count($q['alts']) === 5 || $q['alts'] === ['Certo', 'Errado'], "{$q['ref']}: alternativas");
                $this->assertTrue($q['certa'] >= 0 && $q['certa'] < count($q['alts']), "{$q['ref']}: gabarito fora");
            }
        }
        $this->assertGreaterThanOrEqual(390, count($refs));
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
