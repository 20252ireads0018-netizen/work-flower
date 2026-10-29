<?php

namespace Tests\Feature;

use App\Models\DiversoDado;
use App\Models\Tarefa;
use App\Models\User;
use App\Models\WorkflowMembro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Vários usuários no mesmo workflow: limite do plano, acesso, gravação com versão e tarefas. */
class CompartilharTest extends TestCase
{
    use RefreshDatabase;

    private function conta(string $plano = 'gratuito'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // fora do teste grátis
        DB::table('users')->where('id', $u->id)->update([
            'plano_slug' => $plano,
            'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth(),
        ]);

        return $u->fresh();
    }

    public function test_plano_gratuito_nao_compartilha(): void
    {
        $dono = $this->conta('gratuito');
        $outro = $this->conta();

        $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $outro->email])
            ->assertStatus(403)->assertJson(['plano_limite' => true, 'recurso' => 'usuarios']);
        $this->assertSame(0, WorkflowMembro::count());
    }

    public function test_basico_permite_ate_cinco_pessoas_contando_o_dono(): void
    {
        $dono = $this->conta('basico');
        $outros = collect(range(1, 5))->map(fn () => $this->conta());

        foreach ($outros->take(4) as $o) {
            $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $o->email])->assertOk();
        }
        // a mesma pessoa em outra área não conta de novo
        $this->actingAs($dono)->postJson('/compartilhar/corpo', ['email' => $outros[0]->email])->assertOk();
        // a quinta pessoa convidada (sexta na conta) passa do limite
        $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $outros[4]->email])
            ->assertStatus(403)->assertJson(['plano_limite' => true]);

        $this->actingAs($dono)->getJson('/compartilhar/diverso')->assertJson(['pessoas' => 5, 'limite' => 5]);
    }

    public function test_convidado_le_e_grava_os_dados_do_dono_so_na_area_compartilhada(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $membro->email])->assertOk();
        DiversoDado::create(['user_id' => $dono->id, 'chave' => 'estado', 'valor' => '{"abas":[]}']);

        // abre o Diverso do dono e grava nele
        $this->actingAs($membro)->get('/areas/diverso?de=' . $dono->id)->assertOk()->assertSee('wf-dono', false);
        $this->actingAs($membro)->withHeaders(['X-WF-Dono' => $dono->id])
            ->putJson('/diverso/dados/img.b1', ['valor' => '"data:image/png;base64,AAA"'])->assertOk();
        $this->assertTrue(DiversoDado::where(['user_id' => $dono->id, 'chave' => 'img.b1'])->exists());
        $this->assertFalse(DiversoDado::where(['user_id' => $membro->id, 'chave' => 'img.b1'])->exists());

        // área que não foi compartilhada: sem acesso
        $this->actingAs($membro)->withHeaders(['X-WF-Dono' => $dono->id])
            ->putJson('/corpo/dados/estado', ['valor' => '{}'])->assertForbidden();
        $this->actingAs($membro)->get('/areas/carteira?de=' . $dono->id)->assertForbidden();

        // removido pelo dono: perde o acesso
        $this->actingAs($dono)->deleteJson("/compartilhar/diverso/{$membro->id}")->assertOk();
        $this->actingAs($membro)->get('/areas/diverso?de=' . $dono->id)->assertForbidden();
    }

    public function test_gravacao_com_versao_antiga_devolve_conflito_com_o_estado_atual(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/diverso', ['email' => $membro->email])->assertOk();

        $v1 = $this->actingAs($dono)->withHeaders(['X-WF-Versao' => ''])
            ->putJson('/diverso/dados/estado', ['valor' => '{"n":1}'])->assertOk()->json('versao');
        // o convidado grava em cima da v1
        $v2 = $this->actingAs($membro)->withHeaders(['X-WF-Dono' => $dono->id, 'X-WF-Versao' => $v1])
            ->putJson('/diverso/dados/estado', ['valor' => '{"n":2}'])->assertOk()->json('versao');
        // o dono ainda acha que está na v1: conflito, recebe o estado do convidado para juntar
        $this->actingAs($dono)->withHeaders(['X-WF-Versao' => $v1])
            ->putJson('/diverso/dados/estado', ['valor' => '{"n":3}'])
            ->assertStatus(409)->assertJson(['conflito' => true, 'versao' => $v2, 'valor' => '{"n":2}']);
        // e a sincronia mostra a versão nova
        $this->actingAs($dono)->getJson('/sincronia/diverso?versao=' . $v1)->assertJson(['versao' => $v2, 'estado' => '{"n":2}']);
    }

    public function test_sincronia_rapida_e_avisos_de_mudanca_em_outra_pagina(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/mente', ['email' => $membro->email])->assertOk();

        $v1 = $this->actingAs($dono)->withHeaders(['X-WF-Versao' => ''])
            ->putJson('/mente/dados/estado', ['valor' => '{"n":1}'])->assertOk()->json('versao');
        // quem já está na v1 recebe só a versão, sem o estado
        $this->actingAs($membro)->withHeader('X-WF-Dono', (string) $dono->id)->getJson('/sincronia/mente?versao=' . $v1)
            ->assertOk()->assertJsonMissingPath('estado')->assertJson(['versao' => $v1]);

        // o convidado muda: o dono vê o estado novo, com o nome de quem mudou
        $v2 = $this->actingAs($membro)->withHeaders(['X-WF-Dono' => $dono->id, 'X-WF-Versao' => $v1])
            ->putJson('/mente/dados/estado', ['valor' => '{"n":2}'])->assertOk()->json('versao');
        $this->actingAs($dono)->getJson('/sincronia/mente?versao=' . $v1)
            ->assertJson(['versao' => $v2, 'estado' => '{"n":2}', 'por_nome' => $membro->name]);

        // em qualquer página, o dono fica sabendo; o convidado não é avisado da própria mudança
        $this->actingAs($dono)->getJson('/sincronia')->assertOk()
            ->assertJsonPath('novidades.0.chave', "mente:{$dono->id}")
            ->assertJsonPath('novidades.0.por_nome', $membro->name)
            ->assertJsonPath('novidades.0.meu', true);
        $this->actingAs($membro)->getJson('/sincronia')->assertOk()->assertJsonCount(0, 'novidades');

        // gravação feita por fora da marca (ex.: importação) não fica escondida pelo caminho rápido
        $this->travel(5)->seconds();
        \App\Models\CorpoDado::where(['user_id' => $dono->id, 'chave' => 'mente.estado'])->update(['valor' => '{"n":9}']);
        $this->actingAs($membro)->withHeader('X-WF-Dono', (string) $dono->id)->getJson('/sincronia/mente?versao=' . $v2)
            ->assertJson(['estado' => '{"n":9}']);
    }

    public function test_tarefas_do_workflow_compartilhado(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $estranho = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/corpo', ['email' => $membro->email])->assertOk();

        $this->actingAs($membro)->post('/areas/corpo/tarefas', ['titulo' => 'Treinar', 'wf_dono' => $dono->id])->assertRedirect();
        $t = Tarefa::firstWhere('titulo', 'Treinar');
        $this->assertSame($dono->id, (int) $t->user_id);

        $this->actingAs($membro)->put("/tarefas/{$t->id}", ['status' => 'concluida'])->assertRedirect();
        $this->actingAs($estranho)->put("/tarefas/{$t->id}", ['status' => 'pendente'])->assertForbidden();
    }

    public function test_plano_vencido_suspende_os_convidados(): void
    {
        $dono = $this->conta('basico');
        $membro = $this->conta();
        $this->actingAs($dono)->postJson('/compartilhar/mente', ['email' => $membro->email])->assertOk();
        $this->actingAs($membro)->get('/areas/mente?de=' . $dono->id)->assertOk();

        DB::table('users')->where('id', $dono->id)->update(['plano_slug' => 'gratuito', 'plano_expira_em' => null]);
        $this->actingAs($membro)->get('/areas/mente?de=' . $dono->id)->assertForbidden();
        $this->actingAs($dono->fresh())->getJson('/compartilhar/mente')->assertJson(['semAcesso' => 1]);
    }
}
