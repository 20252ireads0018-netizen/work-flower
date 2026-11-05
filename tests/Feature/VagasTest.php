<?php

namespace Tests\Feature;

use App\Models\Candidatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VagasTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return $extra + [
            'nome' => 'Ana Souza', 'email' => 'ANA@exemplo.com', 'fone' => '(81) 99999-1234', 'cidade' => 'Recife - PE',
            'escolaridade' => 'Superior completo', 'exp' => 'De 1 a 3 anos', 'dias' => ['Seg', 'Qua'],
            'turno' => 'Tarde (12h às 18h)', 'horas' => 20, 'msg' => 'Oi', 'equip' => true, 'lgpd' => true,
        ];
    }

    public function test_pagina_abre_e_candidatura_salva_sem_token_csrf(): void
    {
        $this->get('/vagas')->assertOk()->assertSee('Vagas Work Flower');

        // como no ar: o navegador não manda o cookie de sessão em /vagas, então não há token que bata
        $r = $this->withMiddleware()->postJson('/vagas/candidaturas', $this->dados())->assertOk()->assertJsonPath('ok', true);
        $c = Candidatura::firstOrFail();
        $this->assertSame('ana@exemplo.com', $c->email);
        $this->assertSame('81999991234', $c->fone);
        $this->assertSame(['Seg', 'Qua'], $c->dias);
        $this->assertStringContainsString('/vagas/curso?c=' . $c->codigo, $r->json('curso_url'));

        // "Editar respostas": mesmo código atualiza, não duplica
        $this->postJson('/vagas/candidaturas', $this->dados(['codigo' => $c->codigo, 'horas' => 12]))->assertOk();
        $this->assertSame(1, Candidatura::count());
        $this->assertSame(12, Candidatura::first()->horas);
    }

    public function test_validacao_e_campo_isca(): void
    {
        $this->postJson('/vagas/candidaturas', $this->dados(['nome' => 'Ana', 'lgpd' => false]))
            ->assertStatus(422)->assertJsonValidationErrors(['nome', 'lgpd']);
        $this->postJson('/vagas/candidaturas', $this->dados(['site' => 'http://spam']))->assertOk();
        $this->assertSame(0, Candidatura::count());
    }

    public function test_curso_registra_clique_e_redireciona_e_admin_exige_token(): void
    {
        config(['vagas.curso_url' => 'https://curso.exemplo.com', 'vagas.admin_token' => 'segredo-longo']);
        $this->postJson('/vagas/candidaturas', $this->dados())->assertOk();
        $c = Candidatura::first();

        $this->get('/vagas/curso?c=' . $c->codigo)->assertRedirect('https://curso.exemplo.com');
        $this->assertNotNull($c->fresh()->curso_clicado_em);

        $this->get('/vagas/admin?token=errado')->assertNotFound();
        $this->get('/vagas/admin?token=segredo-longo')->assertOk()->assertSee('Ana Souza');
        $csv = $this->get('/vagas/admin?token=segredo-longo&formato=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('ana@exemplo.com', $csv);

        config(['vagas.curso_url' => null, 'vagas.admin_token' => null]);
        $this->get('/vagas/curso')->assertNotFound();
        $this->get('/vagas/admin?token=')->assertNotFound();
    }

    public function test_pagina_nao_promete_contratacao_e_mostra_preco(): void
    {
        $this->get('/vagas')->assertOk()
            ->assertDontSee('contratados com mais frequência')
            ->assertSee('R$ 19,90')
            ->assertSee('não muda em nada a análise');
    }

    public function test_compra_do_curso_por_pix_ate_a_confirmacao_no_admin(): void
    {
        \App\Support\Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->postJson('/vagas/candidaturas', $this->dados())->assertOk();
        $c = Candidatura::first();

        // código inválido ou de outra candidatura não existe
        $this->postJson('/vagas/curso/pedido', ['codigo' => 'x'])->assertNotFound();

        $p = $this->postJson('/vagas/curso/pedido', ['codigo' => $c->codigo])->assertOk()->assertJsonPath('status', 'aguardando');
        $this->assertStringContainsString('540519.90', $p->json('copia')); // valor fixo no Pix (campo 54)
        $txid = $p->json('txid');
        // pedir de novo devolve o mesmo Pix
        $this->postJson('/vagas/curso/pedido', ['codigo' => $c->codigo])->assertJsonPath('txid', $txid);

        $this->postJson('/vagas/curso/paguei', ['codigo' => $c->codigo, 'pagador' => 'Mãe da Ana'])->assertOk()->assertJsonPath('status', 'informado');
        $this->assertSame('Mãe da Ana', $c->fresh()->curso_pagador);

        // administração vê em /admin/pix e confirma
        $admin = \App\Models\User::factory()->create(['email' => \App\Models\User::ADMINS[0]]);
        $this->actingAs($admin)->get('/admin/pix')->assertOk()->assertSee($txid)->assertSee('Mãe da Ana');
        $this->actingAs($admin)->post("/admin/pix/cursos/{$c->id}/confirmar")->assertRedirect();
        $this->assertSame('confirmado', $c->fresh()->curso_status);
        $this->postJson('/vagas/curso/pedido', ['codigo' => $c->codigo])->assertJsonPath('status', 'confirmado')->assertJsonPath('copia', null);

        // quem não é admin não confirma
        $this->actingAs(\App\Models\User::factory()->create())->post("/admin/pix/cursos/{$c->id}/recusar")->assertNotFound();
    }

    public function test_nao_encontrado_volta_para_o_candidato_avisar_de_novo(): void
    {
        \App\Support\Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->postJson('/vagas/candidaturas', $this->dados())->assertOk();
        $c = Candidatura::first();
        $this->postJson('/vagas/curso/pedido', ['codigo' => $c->codigo]);
        $this->postJson('/vagas/curso/paguei', ['codigo' => $c->codigo]);

        $admin = \App\Models\User::factory()->create(['email' => \App\Models\User::ADMINS[0]]);
        $this->actingAs($admin)->post("/admin/pix/cursos/{$c->id}/recusar", ['observacao' => 'valor diferente'])->assertRedirect();
        $this->postJson('/vagas/curso/pedido', ['codigo' => $c->codigo])->assertJsonPath('status', 'recusado')->assertJsonPath('obs', 'valor diferente');
        $this->postJson('/vagas/curso/paguei', ['codigo' => $c->codigo])->assertJsonPath('status', 'informado');
    }

    public function test_sem_chave_pix_configurada_avisa(): void
    {
        $this->postJson('/vagas/candidaturas', $this->dados())->assertOk();
        $this->postJson('/vagas/curso/pedido', ['codigo' => Candidatura::first()->codigo])->assertStatus(503);
    }
}
