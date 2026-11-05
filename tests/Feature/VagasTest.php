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
}
