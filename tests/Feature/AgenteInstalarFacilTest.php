<?php

namespace Tests\Feature;

use App\Models\AutomacaoDispositivo;
use App\Models\AutomacaoExecucao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Instalação de um clique do agente de automações: .cmd com código de uso único, lista para a página e Testar. */
class AgenteInstalarFacilTest extends TestCase
{
    use RefreshDatabase;

    /** Tira o PowerShell de dentro do .cmd (base64 em pedaços). */
    private function powershell(string $cmd): string
    {
        preg_match_all('/^>>"%WF%\.b64" echo (\S+)\r?$/m', $cmd, $m);

        return str_replace("\u{FEFF}", '', base64_decode(implode('', $m[1])));
    }

    public function test_instalador_de_um_clique_pareia_sem_token_na_tela(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->get('/automacoes/agente/instalar')->assertOk();
        $this->assertStringContainsString('Instalar agente Work Flower.cmd', $r->headers->get('Content-Disposition'));

        $ps = $this->powershell($r->getContent());
        $this->assertStringContainsString('/api/agente-codigo', $ps);
        $this->assertStringContainsString('instalar.ps1', $ps);
        $this->assertStringContainsString('exit 1', $ps);
        $this->assertStringNotContainsString('--app=', $ps); // só o agente, sem os atalhos do app
        $this->assertMatchesRegularExpression("/codigo = '(wfc_[A-Za-z0-9]{32})'/", $ps);
        preg_match("/codigo = '(wfc_[A-Za-z0-9]{32})'/", $ps, $c);

        // o código vira o token do computador (com o nome do Windows, sem o "(app)") e vale uma vez só
        $this->postJson('/api/agente-codigo', ['codigo' => $c[1], 'computador' => 'NOTE-CASA'])->assertOk()->assertJson(['dispositivo' => 'NOTE-CASA']);
        $this->postJson('/api/agente-codigo', ['codigo' => $c[1], 'computador' => 'NOTE-CASA'])->assertStatus(422);
        $this->assertSame(1, AutomacaoDispositivo::where('user_id', $u->id)->count());
    }

    public function test_instalador_exige_login(): void
    {
        $this->get('/automacoes/agente/instalar')->assertRedirect();
        $this->get('/textos-rapidos/instalar-windows')->assertRedirect();
    }

    public function test_instalador_so_dos_textos_rapidos_liga_o_expansor_sem_reinstalar_o_app(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->get('/textos-rapidos/instalar-windows')->assertOk();
        $this->assertStringContainsString('Ativar textos rapidos no Windows.cmd', $r->headers->get('Content-Disposition'));

        $ps = $this->powershell($r->getContent());
        $this->assertStringContainsString('-Expansor', $ps);
        $this->assertStringContainsString('textos rapidos ja funcionam', $ps);
        $this->assertStringNotContainsString('--app=', $ps);            // não instala o app de novo
        $this->assertStringNotContainsString('pagina Automacoes', $ps);  // mensagens próprias dos textos rápidos
        preg_match("/codigo = '(wfc_[A-Za-z0-9]{32})'/", $ps, $c);
        $this->postJson('/api/agente-codigo', ['codigo' => $c[1], 'computador' => 'NOTE'])->assertOk();
    }

    public function test_configuracoes_mostram_o_botao_ativar_no_windows(): void
    {
        $u = User::factory()->create();
        $html = $this->actingAs($u)->get('/painel')->assertOk()->getContent();
        $this->assertStringContainsString('Ativar no Windows', $html);
        $this->assertStringContainsString('/textos-rapidos/instalar-windows', $html);
    }

    public function test_lista_de_computadores_e_testar(): void
    {
        $u = User::factory()->create();
        $outro = User::factory()->create();
        [$d] = AutomacaoDispositivo::parear($u->id, 'PC de casa');
        [$alheio] = AutomacaoDispositivo::parear($outro->id, 'PC alheio');

        $this->actingAs($u)->getJson('/automacoes/computadores')->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.nome', 'PC de casa')->assertJsonPath('0.online', false);

        $this->actingAs($u)->postJson("/automacoes/computadores/{$d->id}/testar")->assertOk()->assertJson(['ok' => true]);
        $e = AutomacaoExecucao::where('dispositivo_id', $d->id)->firstOrFail();
        $this->assertSame('pendente', $e->status);
        $this->assertSame('notificacao', $e->acoes[0]['tipo']);

        // computador de outra conta: 404; revogado: 422
        $this->actingAs($u)->postJson("/automacoes/computadores/{$alheio->id}/testar")->assertNotFound();
        $d->forceFill(['revogado_em' => now()])->save();
        $this->actingAs($u)->postJson("/automacoes/computadores/{$d->id}/testar")->assertStatus(422);
        $this->actingAs($u)->getJson('/automacoes/computadores')->assertOk()->assertJsonCount(0);
    }

    public function test_pagina_mostra_o_botao_de_um_clique(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/automacoes')->assertOk()
            ->assertSee('Instalar neste computador')
            ->assertSee('Instalar manualmente');
    }
}
