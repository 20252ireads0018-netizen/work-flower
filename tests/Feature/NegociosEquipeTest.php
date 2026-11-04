<?php

namespace Tests\Feature;

use App\Models\CarteiraDado;
use App\Models\NegocioDado;
use App\Models\Tarefa;
use App\Models\User;
use App\Models\WorkflowAtividade;
use App\Models\WorkflowMembro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Área Negócios (saiu da Carteira), equipe com permissão de edição e bloqueio, e tarefas delegadas no quadro. */
class NegociosEquipeTest extends TestCase
{
    use RefreshDatabase;

    private function conta(string $plano = 'basico'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => $plano, 'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth()]);

        return $u->fresh();
    }

    public function test_negocios_e_documentos_da_carteira_vao_para_a_area_negocios_sem_sumir_da_carteira(): void
    {
        $u = $this->conta();
        $estado = [
            'negocios' => [['id' => 'n1', 'nome' => 'Doceria', 'vendas' => [], 'custos' => []]], 'negAtivo' => 'n1',
            'docs' => [['id' => 'd1', 'titulo' => 'Contrato']], 'docAtivo' => 'd1',
            'contas' => [['id' => 'c1', 'nome' => 'Nubank']],
            'blocos' => [['id' => 'bneg', 'secao' => 'negocios', 'tipo' => 'texto'], ['id' => 'bcart', 'secao' => 'carteira', 'tipo' => 'texto']],
            'layout' => ['negocios' => ['bneg' => ['x' => 0]], 'carteira' => ['bcart' => ['x' => 0]]],
        ];
        CarteiraDado::insert([
            ['user_id' => $u->id, 'chave' => 'estado', 'valor' => json_encode($estado)],
            ['user_id' => $u->id, 'chave' => 'img.bneg', 'valor' => '"data:image/png;base64,AAA"'],
        ]);

        $this->actingAs($u)->get('/areas/negocios')->assertOk()->assertSee('Doceria', false);
        $novo = json_decode(NegocioDado::where(['user_id' => $u->id, 'chave' => 'estado'])->value('valor'), true);
        $this->assertSame('Doceria', $novo['negocios'][0]['nome']);
        $this->assertSame('Contrato', $novo['docs'][0]['titulo']);
        $this->assertSame(['bneg'], array_column($novo['blocos'], 'id'));
        $this->assertArrayNotHasKey('contas', $novo);
        $this->assertTrue(NegocioDado::where(['user_id' => $u->id, 'chave' => 'img.bneg'])->exists());
        // a Carteira não perde nada
        $this->assertSame($estado, json_decode(CarteiraDado::where(['user_id' => $u->id, 'chave' => 'estado'])->value('valor'), true));

        // só na primeira vez: depois vale o que for gravado em Negócios
        $this->actingAs($u)->putJson('/negocios/dados/estado', ['valor' => '{"negocios":[]}'])->assertOk();
        $this->actingAs($u)->get('/areas/negocios')->assertOk();
        $this->assertSame('{"negocios":[]}', NegocioDado::where(['user_id' => $u->id, 'chave' => 'estado'])->value('valor'));
        $this->actingAs($u)->get('/areas/carteira')->assertOk()->assertDontSee('id="cli-lista"', false);
    }

    public function test_dono_tira_a_edicao_e_bloqueia_e_o_servidor_respeita(): void
    {
        $dono = $this->conta();
        $m = $this->conta('gratuito');
        $this->actingAs($dono)->postJson('/compartilhar/negocios', ['email' => $m->email])->assertOk();
        $h = ['X-WF-Dono' => (string) $dono->id];

        $this->actingAs($m)->withHeaders($h)->putJson('/negocios/dados/estado', ['valor' => '{"a":1}'])->assertOk();

        // só ver: abre, mas não grava
        $this->actingAs($dono)->patchJson("/equipe/{$m->id}/negocios", ['pode_editar' => false])->assertOk()->assertJson(['pode_editar' => false]);
        $this->actingAs($m)->get('/areas/negocios?de=' . $dono->id)->assertOk()->assertSee('wf-so-ver', false);
        $this->actingAs($m)->withHeaders($h)->putJson('/negocios/dados/estado', ['valor' => '{"a":2}'])->assertForbidden();
        $this->assertSame('{"a":1}', NegocioDado::where(['user_id' => $dono->id, 'chave' => 'estado'])->value('valor'));

        // bloqueado: nem abre
        $this->actingAs($dono)->patchJson("/equipe/{$m->id}/negocios", ['bloqueado' => true])->assertOk();
        $this->actingAs($m)->get('/areas/negocios?de=' . $dono->id)->assertForbidden();
        $this->actingAs($dono)->patchJson("/equipe/{$m->id}/negocios", ['bloqueado' => false, 'pode_editar' => true])->assertOk();
        $this->actingAs($m)->get('/areas/negocios?de=' . $dono->id)->assertOk();

        // ninguém mexe na equipe dos outros
        $this->actingAs($m)->patchJson("/equipe/{$m->id}/negocios", ['bloqueado' => true])->assertNotFound();
    }

    public function test_tarefa_delegada_aparece_na_equipe_e_quem_so_ve_pode_mover_a_dele(): void
    {
        $dono = $this->conta();
        $m = $this->conta('gratuito');
        $outro = $this->conta('gratuito');
        $this->actingAs($dono)->postJson('/compartilhar/corpo', ['email' => $m->email])->assertOk();

        $this->actingAs($dono)->post('/areas/corpo/tarefas', ['titulo' => 'Treino', 'categoria' => 'Academia', 'cor' => '#f472b6', 'responsavel_id' => $m->id])->assertRedirect();
        $this->actingAs($dono)->post('/areas/corpo/tarefas', ['titulo' => 'Errado', 'responsavel_id' => $outro->id])->assertSessionHasErrors('responsavel_id');
        $t = Tarefa::firstOrFail();
        $this->assertSame(['Academia', '#f472b6', $m->id], [$t->categoria, $t->cor, $t->responsavel_id]);

        $j = $this->actingAs($dono)->getJson('/equipe')->assertOk()->json();
        $this->assertSame('Treino', $j['pessoas'][0]['tarefas'][0]['titulo']);
        $this->assertStringContainsString('criou a tarefa "Treino"', $j['atividades'][0]['acao']);

        WorkflowMembro::where(['dono_id' => $dono->id, 'membro_id' => $m->id])->update(['pode_editar' => false]);
        $this->actingAs($m)->withHeader('X-WF-Dono', (string) $dono->id)->put("/tarefas/{$t->id}", ['status' => 'andamento'])->assertRedirect();
        $this->assertSame('andamento', $t->fresh()->status);
        $this->actingAs($m)->withHeader('X-WF-Dono', (string) $dono->id)->put("/tarefas/{$t->id}", ['titulo' => 'Outro nome'])->assertForbidden();
        $this->actingAs($m)->withHeader('X-WF-Dono', (string) $dono->id)->delete("/tarefas/{$t->id}")->assertForbidden();
        $this->assertTrue(WorkflowAtividade::where('acao', 'like', 'moveu "Treino"%')->exists());

        // a página mostra o quadro com a categoria e a pessoa
        $this->actingAs($dono)->get('/areas/corpo')->assertOk()->assertSee('tq-grade', false)->assertSee('Academia');
    }
}
