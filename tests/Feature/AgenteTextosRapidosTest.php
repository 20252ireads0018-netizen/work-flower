<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AgenteCodigoController;
use App\Models\AutomacaoDispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Textos rápidos no Windows inteiro: API do agente, código de pareamento e instalador completo do app. */
class AgenteTextosRapidosTest extends TestCase
{
    use RefreshDatabase;

    private function api(string $token): static
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json']);
    }

    public function test_agente_recebe_os_trechos_da_conta(): void
    {
        $u = User::factory()->create(['name' => 'Maria Silva']);
        $this->actingAs($u)->putJson('/preferencias', ['trechos' => [
            ['atalho' => ';ass', 'texto' => "Abraço,\n{nome}"],
            ['atalho' => ';cmd', 'texto' => 'C:\Users\pasta \o/'],
        ]])->assertOk();
        [, $token] = AutomacaoDispositivo::parear($u->id, 'PC');

        $r = $this->api($token)->getJson('/api/agente/trechos')->assertOk()
            ->assertJson(['ligado' => true, 'nome' => 'Maria Silva', 'quantidade' => 2]);
        $lista = json_decode(base64_decode($r->json('trechos_b64')), true);
        $this->assertSame([['atalho' => ';ass', 'texto' => "Abraço,\n{nome}"], ['atalho' => ';cmd', 'texto' => 'C:\Users\pasta \o/']], $lista);
        $versao = $r->json('versao');

        // desligado no site: o agente também desliga, e a versão muda
        $this->actingAs($u)->putJson('/preferencias', ['expansorLigado' => false])->assertOk();
        $r2 = $this->api($token)->getJson('/api/agente/trechos')->assertOk()->assertJson(['ligado' => false]);
        $this->assertNotSame($versao, $r2->json('versao'));
    }

    public function test_trechos_exigem_token_valido_e_nao_misturam_contas(): void
    {
        $this->getJson('/api/agente/trechos')->assertUnauthorized();
        $this->api('wfa_errado')->getJson('/api/agente/trechos')->assertUnauthorized();

        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->actingAs($a)->putJson('/preferencias', ['trechos' => [['atalho' => ';a', 'texto' => 'só da A']]])->assertOk();
        [, $tokenB] = AutomacaoDispositivo::parear($b->id, 'PC B');
        $this->api($tokenB)->getJson('/api/agente/trechos')->assertOk()->assertJson(['quantidade' => 0]);
    }

    public function test_pacote_do_agente_leva_o_expansor(): void
    {
        $u = User::factory()->create();
        [, $token] = AutomacaoDispositivo::parear($u->id, 'PC');
        $zip = $this->api($token)->get('/api/agente/pacote')->assertOk()->getContent();
        $this->assertStringStartsWith("PK", $zip);
        foreach (['work-flower-agente/agente.ps1', 'work-flower-agente/expansor.cs', 'work-flower-agente/instalar.ps1', 'work-flower-agente/site.txt'] as $arq) {
            $this->assertStringContainsString($arq, $zip);
        }
        $this->assertStringNotContainsString('config.json', $zip);
    }

    public function test_versao_do_pacote_para_o_agente_se_atualizar(): void
    {
        $u = User::factory()->create();
        [, $token] = AutomacaoDispositivo::parear($u->id, 'PC');
        $this->getJson('/api/agente/versao')->assertStatus(401);

        $v = $this->api($token)->getJson('/api/agente/versao')->assertOk()->json('pacote');
        // mesma conta que o agente.ps1 faz (Versao-Pasta): sha256 dos arquivos, cada um seguido de um byte zero
        $esperado = hash('sha256', implode('', array_map(fn ($f) => file_get_contents(base_path('agente/' . $f)) . "\0", \App\Support\PacoteAgente::ARQUIVOS_VERSAO)));
        $this->assertSame($esperado, $v);

        $agente = file_get_contents(base_path('agente/agente.ps1'));
        $this->assertStringContainsString("Chamar-Api 'GET' 'versao'", $agente);
        $this->assertStringContainsString("@('agente.ps1', 'expansor.cs', 'desinstalar.ps1')", $agente, 'mesma lista de PacoteAgente::ARQUIVOS_VERSAO');
    }

    public function test_expansor_quebra_linha_com_shift_enter_para_nao_enviar_em_chats(): void
    {
        $cs = file_get_contents(base_path('agente/expansor.cs'));
        $quebra = strpos($cs, "if (c == '\\n')");
        $this->assertNotFalse($quebra);
        $trecho = substr($cs, $quebra, 400);
        $shift = strpos($trecho, 'wVk = VK_SHIFT }');
        $enter = strpos($trecho, 'Tecla(l, VK_RETURN)');
        $solta = strpos($trecho, 'wVk = VK_SHIFT, dwFlags = KEYEVENTF_KEYUP');
        $this->assertTrue($shift !== false && $enter > $shift && $solta > $enter, 'Shift desce, Enter, Shift sobe');
    }

    public function test_codigo_de_pareamento_vale_uma_vez(): void
    {
        $u = User::factory()->create();
        $codigo = AgenteCodigoController::gerar($u->id);

        $r = $this->postJson('/api/agente-codigo', ['codigo' => $codigo, 'computador' => 'NOTE-MARIA'])->assertOk();
        $token = $r->json('token');
        $this->assertStringStartsWith('wfa_', $token);
        $this->assertSame('NOTE-MARIA (app)', $r->json('dispositivo'));
        $this->api($token)->getJson('/api/agente/ola')->assertOk();
        $this->assertSame(1, AutomacaoDispositivo::where('user_id', $u->id)->count());

        // de novo, inventado ou com outro prefixo: recusado
        $this->postJson('/api/agente-codigo', ['codigo' => $codigo])->assertStatus(422);
        $this->postJson('/api/agente-codigo', ['codigo' => 'wfc_inventado'])->assertStatus(422);
        $this->postJson('/api/agente-codigo', ['codigo' => $token])->assertStatus(422);
        $this->assertSame(1, AutomacaoDispositivo::where('user_id', $u->id)->count());
    }

    public function test_codigo_respeita_o_limite_de_computadores(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < \App\Http\Controllers\AutomacaoController::MAX_DISPOSITIVOS; $i++) {
            AutomacaoDispositivo::parear($u->id, "PC $i");
        }
        $this->postJson('/api/agente-codigo', ['codigo' => AgenteCodigoController::gerar($u->id)])->assertStatus(409);
    }

    public function test_instalador_completo_so_logado_e_com_codigo_que_funciona(): void
    {
        $this->get('/app/instalar-windows-completo')->assertRedirect();

        $u = User::factory()->create();
        $r = $this->actingAs($u)->get('/app/instalar-windows-completo')->assertOk();
        $this->assertStringContainsString('attachment; filename="Instalar Work Flower.cmd"', $r->headers->get('Content-Disposition'));

        $cmd = $r->getContent();
        foreach (explode("\r\n", $cmd) as $linha) {
            $this->assertLessThan(8000, strlen($linha), 'linha do cmd.exe tem limite de 8191 caracteres');
        }
        preg_match_all('/^>>"%WF%\.b64" echo ([A-Za-z0-9+\/=]+)$/m', str_replace("\r\n", "\n", $cmd), $m);
        $this->assertNotEmpty($m[1]);
        $ps = base64_decode(implode('', $m[1]), true);
        $this->assertStringStartsWith("\u{FEFF}", $ps, 'UTF-8 com BOM: o PowerShell 5 lê os acentos certo');

        $this->assertStringContainsString("--app=\$url", $ps);
        $this->assertStringContainsString('/api/agente-codigo', $ps);
        $this->assertStringContainsString('/api/agente/pacote', $ps);
        $this->assertStringContainsString('-Expansor', $ps);
        $this->assertStringNotContainsString('wfa_', $ps, 'o arquivo leva o código de uso único, nunca o token');

        preg_match("/codigo = '(wfc_[A-Za-z0-9]+)'/", $ps, $c);
        $this->postJson('/api/agente-codigo', ['codigo' => $c[1]])->assertOk();
    }

    public function test_dialogo_oferece_o_completo_so_para_quem_esta_logado(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('data-windows-completo', false);
        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()
            ->assertSee('data-windows-completo="' . route('app.windows.completo') . '"', false);
    }

    public function test_agente_tem_o_expansor_ligado_no_instalador_e_no_menu(): void
    {
        $agente = file_get_contents(base_path('agente/agente.ps1'));
        $this->assertStringContainsString("Chamar-Api 'GET' 'trechos'", $agente);
        $this->assertStringContainsString('[WFExpansor]::Definir', $agente);
        $this->assertStringContainsString('Textos rápidos no Windows', $agente);

        $instalar = file_get_contents(base_path('agente/instalar.ps1'));
        $this->assertStringContainsString("'expansor.cs'", $instalar);
        $this->assertStringContainsString('[switch]$Expansor', $instalar);

        $cs = file_get_contents(base_path('agente/expansor.cs'));
        $this->assertStringContainsString('WH_KEYBOARD_LL', $cs);
        $this->assertStringContainsString('LLKHF_INJECTED', $cs);   // ignora o que ele mesmo digita
    }
}
