<?php

namespace Tests\Feature;

use App\Models\Automacao;
use App\Models\AutomacaoDispositivo;
use App\Models\AutomacaoExecucao;
use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Automações no computador: pareamento, API do agente (/api/agente) e gatilhos. */
class AutomacoesTest extends TestCase
{
    use RefreshDatabase;

    /** Pareia pela página e devolve [aparelho, token]. */
    private function parear(User $u, string $nome = 'PC de casa'): array
    {
        $r = $this->actingAs($u)->post('/automacoes/computadores', ['computador' => $nome]);
        $r->assertRedirect('/automacoes');
        $token = session('token_novo')['token'];

        return [AutomacaoDispositivo::where('user_id', $u->id)->latest('id')->first(), $token];
    }

    private function criar(User $u, AutomacaoDispositivo $d, array $extra = [], ?array $acoes = null): Automacao
    {
        $this->actingAs($u)->post('/automacoes', $extra + [
            'nome' => 'Teste', 'dispositivo_id' => $d->id, 'gatilho' => 'manual',
            'acoes' => json_encode($acoes ?? [['tipo' => 'notificacao', 'titulo' => 'Oi', 'mensagem' => 'Tarefa {tarefa}']]),
        ])->assertSessionHasNoErrors();

        return Automacao::where('user_id', $u->id)->latest('id')->firstOrFail();
    }

    private function api(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    public function test_token_aparece_uma_vez_e_so_o_hash_fica_no_banco(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);

        $this->assertStringStartsWith('wfa_', $token);
        $this->assertSame(hash('sha256', $token), $d->token_hash);
        $this->assertDatabaseMissing('automacao_dispositivos', ['token_hash' => $token]);
        // a página logo depois mostra o token; recarregar não mostra de novo
        $this->actingAs($u)->get('/automacoes')->assertOk()->assertSee($token);
        $this->actingAs($u)->get('/automacoes')->assertOk()->assertDontSee($token)->assertSee('PC de casa');
    }

    public function test_api_recusa_sem_token_token_errado_e_revogado(): void
    {
        $this->getJson('/api/agente/ola')->assertStatus(401);
        $this->api('wfa_' . str_repeat('x', 40))->getJson('/api/agente/ola')->assertStatus(401);

        $u = User::factory()->create(['name' => 'Ana']);
        [$d, $token] = $this->parear($u);
        $this->api($token)->getJson('/api/agente/ola')->assertOk()->assertJson(['ok' => true, 'dispositivo' => 'PC de casa', 'usuario' => 'Ana']);
        $this->assertNotNull($d->fresh()->ultimo_contato_em);

        $this->actingAs($u)->post("/automacoes/computadores/{$d->id}/revogar")->assertRedirect();
        $this->api($token)->getJson('/api/agente/ola')->assertStatus(401);
        // cabeçalho alternativo também funciona (e também é recusado depois de revogar)
        $this->withHeaders(['X-Agente-Token' => $token])->getJson('/api/agente/pendentes')->assertStatus(401);
    }

    public function test_ciclo_manual_buscar_e_responder(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        $a = $this->criar($u, $d);

        $this->actingAs($u)->post("/automacoes/{$a->id}/rodar")->assertRedirect()->assertSessionHas('ok');

        $r = $this->api($token)->getJson('/api/agente/pendentes')->assertOk();
        $r->assertJsonCount(1, 'execucoes')->assertJsonPath('execucoes.0.acoes.0.tipo', 'notificacao')->assertJsonPath('execucoes.0.origem', 'Botão no site');
        $id = $r->json('execucoes.0.id');
        $this->assertSame('enviada', AutomacaoExecucao::find($id)->status);

        // a mesma execução não é entregue duas vezes
        $this->api($token)->getJson('/api/agente/pendentes')->assertJsonCount(0, 'execucoes');

        $this->api($token)->postJson("/api/agente/execucoes/$id", ['status' => 'ok', 'resultado' => 'Notificação mostrada'])->assertOk();
        $this->assertSame('ok', AutomacaoExecucao::find($id)->status);
        $this->api($token)->postJson("/api/agente/execucoes/$id", ['status' => 'erro'])->assertStatus(409);

        $this->actingAs($u)->get('/automacoes')->assertSee('Notificação mostrada')->assertSee('Concluída');
    }

    public function test_um_aparelho_nao_ve_nem_responde_execucao_de_outro(): void
    {
        $ana = User::factory()->create();
        $bia = User::factory()->create();
        [$dA, $tA] = $this->parear($ana);
        [, $tB] = $this->parear($bia);
        $a = $this->criar($ana, $dA);
        $e = $a->disparar('teste');

        $this->api($tB)->getJson('/api/agente/pendentes')->assertJsonCount(0, 'execucoes');
        $this->api($tB)->postJson("/api/agente/execucoes/{$e->id}", ['status' => 'ok'])->assertStatus(404);
        $this->assertSame('pendente', $e->fresh()->status);

        // e na página: Bia não mexe na automação nem no computador da Ana
        $this->flushHeaders();
        $this->actingAs($bia)->post("/automacoes/{$a->id}/rodar")->assertNotFound();
        $this->actingAs($bia)->post("/automacoes/computadores/{$dA->id}/revogar")->assertNotFound();
        $this->actingAs($bia)->post('/automacoes', ['nome' => 'x', 'dispositivo_id' => $dA->id, 'gatilho' => 'manual', 'acoes' => '[{"tipo":"url","url":"https://a.com"}]'])
            ->assertSessionHasErrors('dispositivo_id');
    }

    public function test_status_invalido_e_rejeitado(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        $e = $this->criar($u, $d)->disparar('x');
        $this->api($token)->postJson("/api/agente/execucoes/{$e->id}", ['status' => 'pendente'])->assertStatus(422);
    }

    public function test_acoes_perigosas_sao_recusadas_no_site(): void
    {
        $u = User::factory()->create();
        [$d] = $this->parear($u);
        $base = ['nome' => 'x', 'dispositivo_id' => $d->id, 'gatilho' => 'manual'];

        foreach ([
            [['tipo' => 'abrir', 'caminho' => 'C:\\Windows\\System32\\cmd.exe']],
            [['tipo' => 'abrir', 'caminho' => 'powershell']],
            [['tipo' => 'abrir', 'caminho' => 'C:\\temp\\x.bat']],
            [['tipo' => 'script', 'nome' => '..\\..\\Windows\\evil']],
            [['tipo' => 'url', 'url' => 'javascript:alert(1)']],
            [['tipo' => 'url', 'url' => 'file:///C:/x']],
            [['tipo' => 'comando', 'linha' => 'del /s']],
            [],
        ] as $acoes) {
            $this->actingAs($u)->post('/automacoes', $base + ['acoes' => json_encode($acoes)])->assertSessionHasErrors('acoes');
        }
        $this->assertSame(0, Automacao::count());

        // as seguras passam
        $a = $this->criar($u, $d, [], [
            ['tipo' => 'abrir', 'caminho' => 'C:\\Program Files\\Notepad++\\notepad++.exe'],
            ['tipo' => 'script', 'nome' => 'limpar-downloads.ps1'],
            ['tipo' => 'backup', 'origem' => 'C:\\Docs', 'destino' => 'D:\\Backup'],
            ['tipo' => 'digitar', 'texto' => 'Olá', 'espera' => 99],
        ]);
        $this->assertSame('limpar-downloads', $a->acoes[1]['nome']);
        $this->assertSame(30, $a->acoes[3]['espera']);
    }

    public function test_horario_dispara_quando_o_agente_confere_e_agenda_a_proxima(): void
    {
        // quarta, 10:00 em Brasília (13:00 UTC)
        Carbon::setTestNow(Carbon::parse('2026-11-04 13:00:00', 'UTC'));
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        $a = $this->criar($u, $d, ['gatilho' => 'horario', 'hora' => '10:30', 'dias' => [3, 5]]);
        $this->assertSame('2026-11-04 13:30:00', $a->proxima_em->utc()->format('Y-m-d H:i:s'));

        $this->api($token)->getJson('/api/agente/pendentes')->assertJsonCount(0, 'execucoes');

        // PC desligado o dia todo: ao voltar dispara uma vez só e agenda sexta
        Carbon::setTestNow(Carbon::parse('2026-11-05 01:00:00', 'UTC'));
        $this->api($token)->getJson('/api/agente/pendentes')->assertJsonCount(1, 'execucoes');
        $this->api($token)->getJson('/api/agente/pendentes')->assertJsonCount(0, 'execucoes');
        $this->assertSame('2026-11-06 13:30:00', $a->fresh()->proxima_em->utc()->format('Y-m-d H:i:s'));

        // pausada não dispara
        $this->actingAs($u)->post("/automacoes/{$a->id}/alternar");
        $this->assertNull($a->fresh()->proxima_em);
        Carbon::setTestNow();
    }

    public function test_tarefa_criada_e_concluida_disparam_com_o_nome_da_tarefa(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        $this->criar($u, $d, ['gatilho' => 'tarefa_concluida', 'area' => 'mente']);
        $this->criar($u, $d, ['gatilho' => 'tarefa_criada']);

        $this->actingAs($u)->post('/areas/mente/tarefas', ['titulo' => 'Ler 20 páginas'])->assertRedirect();
        $t = Tarefa::firstOrFail();
        $this->actingAs($u)->put("/tarefas/{$t->id}", ['status' => 'concluida'])->assertRedirect();
        // concluída em outra área não dispara a automação filtrada por Mente
        $t2 = Tarefa::create(['user_id' => $u->id, 'area' => 'corpo', 'titulo' => 'Treino', 'status' => 'pendente']);
        $t2->update(['status' => 'concluida']);

        $lista = $this->api($token)->getJson('/api/agente/pendentes')->json('execucoes');
        $origens = array_column($lista, 'origem');
        $this->assertContains('Tarefa criada: Ler 20 páginas', $origens);
        $this->assertContains('Tarefa concluída: Ler 20 páginas', $origens);
        $this->assertNotContains('Tarefa concluída: Treino', $origens);
        $this->assertSame('Tarefa Ler 20 páginas', $lista[0]['acoes'][0]['mensagem']);
    }

    public function test_execucao_velha_expira_e_revogar_cancela_pendentes(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        $a = $this->criar($u, $d);
        $velha = $a->disparar('x');
        $velha->forceFill(['created_at' => now()->subHours(13)])->save();

        $this->api($token)->getJson('/api/agente/pendentes')->assertJsonCount(0, 'execucoes');
        $this->assertSame('expirada', $velha->fresh()->status);

        $nova = $a->disparar('y');
        $this->actingAs($u)->post("/automacoes/computadores/{$d->id}/revogar");
        $this->assertSame('expirada', $nova->fresh()->status);
        $this->assertNull($a->fresh()->disparar('z'));
    }

    public function test_limite_de_pedidos_do_agente(): void
    {
        $u = User::factory()->create();
        [, $token] = $this->parear($u);
        for ($i = 0; $i < 60; $i++) {
            $this->api($token)->getJson('/api/agente/ola')->assertOk();
        }
        $this->api($token)->getJson('/api/agente/ola')->assertStatus(429);
    }

    public function test_pagina_e_menu(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('Automações no PC');
        $this->actingAs($u)->get('/automacoes')->assertOk()->assertSee('Primeiro instale o agente')->assertSee('Como funciona')->assertSee('Voltar ao painel');
        $this->get('/logout-nao-existe');
        auth()->logout();
        $this->get('/automacoes')->assertRedirect('/login');
    }

    public function test_acoes_e_resultado_em_base64_chegam_intactos(): void
    {
        $u = User::factory()->create();
        [$d, $token] = $this->parear($u);
        // textos que o mod_security da hospedagem barraria em texto puro
        $acoes = [
            ['tipo' => 'digitar', 'texto' => "SELECT * FROM users; DROP TABLE x; -- <script>alert('ç')</script>", 'espera' => 0],
            ['tipo' => 'backup', 'origem' => 'C:\\Windows\\..\\Users\\Ana', 'destino' => 'D:\\Cópias'],
        ];
        $this->actingAs($u)->post('/automacoes', [
            'nome' => 'B64', 'dispositivo_id' => $d->id, 'gatilho' => 'manual',
            'acoes_b64' => base64_encode(json_encode($acoes)),
        ])->assertSessionHasNoErrors();
        $a = Automacao::firstOrFail();
        $this->assertSame($acoes[0]['texto'], $a->acoes[0]['texto']);

        $a->disparar('x');
        $r = $this->api($token)->getJson('/api/agente/pendentes');
        $vindo = json_decode(base64_decode($r->json('execucoes.0.acoes_b64')), true);
        $this->assertSame($acoes[1]['destino'], $vindo[1]['destino']);

        $id = $r->json('execucoes.0.id');
        $msg = "Não encontrei 'C:\\Program Files\\x.exe' -- UNION SELECT";
        $this->api($token)->postJson("/api/agente/execucoes/$id", ['status' => 'erro', 'resultado_b64' => base64_encode($msg)])->assertOk();
        $this->assertSame($msg, AutomacaoExecucao::find($id)->resultado);

        $e2 = $a->disparar('y');
        $this->api($token)->postJson("/api/agente/execucoes/{$e2->id}", ['status' => 'ok', 'resultado_b64' => '@@@'])->assertStatus(422);

        // base64 quebrado no formulário vira erro amigável, não 500
        $this->flushHeaders();
        $this->actingAs($u)->post('/automacoes', ['nome' => 'x', 'dispositivo_id' => $d->id, 'gatilho' => 'manual', 'acoes_b64' => '%%%'])
            ->assertSessionHasErrors('acoes');
    }

    public function test_formulario_volta_preenchido_depois_de_erro(): void
    {
        $u = User::factory()->create();
        [$d] = $this->parear($u);
        $this->actingAs($u)->get('/automacoes');
        $this->actingAs($u)->from('/automacoes')->followingRedirects()->post('/automacoes', [
            'nome' => 'Minha automação longa', 'dispositivo_id' => $d->id, 'gatilho' => 'horario', 'hora' => '25:99',
            'acoes_b64' => base64_encode(json_encode([['tipo' => 'url', 'url' => 'https://exemplo.com/caminho-unico']])),
        ])->assertOk()->assertSee('Informe a hora no formato 08:30.')->assertSee('caminho-unico', false)->assertSee('Minha automa', false);
    }

    public function test_baixar_agente_gera_zip_valido_sem_config(): void
    {
        $u = User::factory()->create();
        $r = $this->actingAs($u)->get('/automacoes/agente/baixar')->assertOk()->assertHeader('Content-Type', 'application/zip');
        $bin = $r->getContent();
        $this->assertStringStartsWith("PK\x03\x04", $bin);
        $this->assertStringContainsString('work-flower-agente/agente.ps1', $bin);
        $this->assertStringContainsString('work-flower-agente/instalar.ps1', $bin);
        $this->assertStringContainsString('work-flower-agente/site.txt', $bin);
        $this->assertStringNotContainsString('config.json', $bin);

        // confere que o zip abre (com a extensão zip, se o PHP tiver)
        if (class_exists(\ZipArchive::class)) {
            $tmp = tempnam(sys_get_temp_dir(), 'z');
            file_put_contents($tmp, $bin);
            $z = new \ZipArchive();
            $this->assertTrue($z->open($tmp) === true);
            $this->assertSame(url('/'), rtrim($z->getFromName('work-flower-agente/site.txt'), '/'));
            $z->close();
            unlink($tmp);
        }
    }
}
