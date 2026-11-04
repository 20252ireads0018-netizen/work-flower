<?php

namespace Tests\Feature;

use App\Models\NegocioDado;
use App\Models\User;
use App\Models\ZapConfig;
use App\Models\ZapConversa;
use App\Models\ZapMensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Negócios › Clientes: WhatsApp oficial (Cloud API), webhook assinado, conversas por telefone e respostas. */
class WhatsappOficialTest extends TestCase
{
    use RefreshDatabase;

    private function dono(): User
    {
        $u = User::factory()->create();
        NegocioDado::create(['user_id' => $u->id, 'chave' => 'estado', 'valor' => json_encode(['clientes' => [
            ['id' => 'cli1', 'nome' => 'Maria Souza', 'contato' => '(11) 98765-4321'],
            ['id' => 'cli2', 'nome' => 'João', 'contato' => 'joao@email.com'],
        ]])]);

        return $u;
    }

    private function configurar(User $u): ZapConfig
    {
        return ZapConfig::create(['user_id' => $u->id, 'phone_number_id' => '1112223334', 'waba_id' => '5556667778', 'access_token' => 'EAAteste-token-acesso-123', 'app_secret' => 'segredoDoApp1234567890', 'verify_token' => 'meu-token-verif']);
    }

    private function aviso(string $de, string $texto, string $id = 'wamid.A1', string $phoneId = '1112223334'): array
    {
        return ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'waba', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '5511900000000', 'phone_number_id' => $phoneId],
            'contacts' => [['profile' => ['name' => 'Maria do Zap'], 'wa_id' => $de]],
            'messages' => [['from' => $de, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $texto]]],
        ]]]]]];
    }

    private function postarAviso(array $corpo, ?string $segredo = 'segredoDoApp1234567890')
    {
        $json = json_encode($corpo);
        $h = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($segredo !== null) $h['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $json, $segredo);

        return $this->call('POST', '/whatsapp/webhook', [], [], [], $h, $json);
    }

    public function test_sem_configuracao_a_tela_diz_o_que_falta_e_nao_envia(): void
    {
        $u = $this->dono();
        $this->actingAs($u)->getJson('/negocios/whatsapp')->assertOk()
            ->assertJson(['configurado' => false, 'falta' => ['o id do número e o token de acesso do WhatsApp']]);
        Http::fake();
        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['tel' => '11987654321', 'texto' => 'oi'])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_dono_salva_a_configuracao_com_token_criptografado(): void
    {
        $u = $this->dono();
        Http::fake(['graph.facebook.com/*/1112223334*' => Http::response(['display_phone_number' => '+55 11 90000-0000', 'verified_name' => 'Minha Loja'])]);
        $this->actingAs($u)->postJson('/negocios/whatsapp/config', ['phone_number_id' => '1112223334', 'access_token' => 'EAAteste-token-acesso-123'])
            ->assertOk()->assertJson(['ok' => true, 'aviso' => null]);
        $cfg = ZapConfig::firstOrFail();
        $this->assertSame('EAAteste-token-acesso-123', $cfg->access_token);
        $this->assertStringStartsWith('wf-', $cfg->verify_token);
        $this->assertStringContainsString('Minha Loja', $cfg->numero);
        $this->assertStringNotContainsString('EAAteste', (string) \DB::table('zap_configs')->value('access_token'));

        $j = $this->actingAs($u)->getJson('/negocios/whatsapp')->assertOk()->json();
        $this->assertTrue($j['configurado']);
        $this->assertSame($cfg->verify_token, $j['config']['verify_token']);
        $this->assertStringNotContainsString('EAAteste', json_encode($j));
    }

    public function test_webhook_verifica_o_token(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=meu-token-verif&hub.challenge=12345')->assertOk()->assertSeeText('12345');
        $this->get('/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=errado&hub.challenge=12345')->assertForbidden();
    }

    public function test_webhook_com_assinatura_invalida_e_recusado(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        $this->postarAviso($this->aviso('5511987654321', 'Oi'), 'outro-segredo')->assertStatus(401);
        $this->postarAviso($this->aviso('5511987654321', 'Oi'), null)->assertStatus(401);
        $this->assertSame(0, ZapMensagem::count());
    }

    public function test_webhook_valido_guarda_a_mensagem_e_liga_ao_cliente_mesmo_sem_o_9(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        // o WhatsApp manda o celular de São Paulo sem o 9: 55 11 8765-4321
        $this->postarAviso($this->aviso('551187654321', 'Quero um orçamento'))->assertOk();
        $this->postarAviso($this->aviso('551187654321', 'Quero um orçamento'))->assertOk(); // a Meta repete: não duplica

        $c = ZapConversa::firstOrFail();
        $this->assertSame('cli1', $c->cliente_id);
        $this->assertSame('Maria Souza', $c->nome);
        $this->assertSame(1, $c->nao_lidas);
        $this->assertTrue($c->janelaAberta());
        $this->assertSame(1, ZapMensagem::count());

        // a tela acha a conversa pelo contato do cliente (com o 9)
        $j = $this->actingAs($u)->getJson('/negocios/whatsapp/conversa?tel=' . urlencode('(11) 98765-4321'))->assertOk()->json();
        $this->assertSame('Quero um orçamento', $j['mensagens'][0]['texto']);
        $this->assertTrue($j['conversa']['janela']);
        $this->assertSame(0, $c->fresh()->nao_lidas);

        // número de ninguém deste site: aceita (assinatura do app do site) e ignora
        config(['services.meta.secret' => 'segredo-do-site']);
        $this->postarAviso($this->aviso('5521999999999', 'oi', 'wamid.X', '999'), 'segredo-do-site')->assertOk()->assertJson(['ignorado' => true]);
        $this->assertSame(1, ZapConversa::count());
    }

    public function test_responde_com_texto_na_janela_e_status_atualiza(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        $this->postarAviso($this->aviso('551187654321', 'Oi'))->assertOk();

        Http::fake(['graph.facebook.com/*/1112223334/messages' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.OUT1']]])]);
        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['tel' => '(11) 98765-4321', 'texto' => 'Olá Maria, segue o orçamento'])
            ->assertOk()->assertJson(['ok' => true]);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer EAAteste-token-acesso-123')
            && $r['to'] === '551187654321' && $r['type'] === 'text' && $r['text']['body'] === 'Olá Maria, segue o orçamento');
        $out = ZapMensagem::where('direcao', 'out')->firstOrFail();
        $this->assertSame('sent', $out->status);
        $this->assertSame($u->id, $out->autor_id);

        // status chegando pelo webhook (fora de ordem: read antes de delivered)
        $status = fn ($s) => ['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => '1112223334'], 'statuses' => [['id' => 'wamid.OUT1', 'status' => $s, 'recipient_id' => '551187654321']]]]]]]];
        $this->postarAviso($status('read'))->assertOk();
        $this->postarAviso($status('delivered'))->assertOk();
        $this->assertSame('read', $out->fresh()->status);
    }

    public function test_corpo_em_base64_passa_texto_que_parece_sql(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        $this->postarAviso($this->aviso('551187654321', 'Oi'))->assertOk();
        Http::fake(['graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.B64']]])]);

        $texto = "SELECT * FROM pedidos; <script>alert('ç')</script> 😀";
        $b64 = base64_encode(json_encode(['tel' => '11987654321', 'texto' => $texto]));
        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['b64' => $b64])->assertOk();
        Http::assertSent(fn ($r) => ($r['text']['body'] ?? null) === $texto);

        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['b64' => 'isso não é base64'])->assertStatus(422);
    }

    public function test_fora_da_janela_so_vai_modelo_aprovado(): void
    {
        $u = $this->dono();
        $this->configurar($u);
        $this->postarAviso($this->aviso('551187654321', 'Oi'))->assertOk();
        ZapConversa::query()->update(['ultima_entrada_em' => now()->subHours(30)]);

        Http::fake([
            'graph.facebook.com/*/5556667778/message_templates*' => Http::response(['data' => [
                ['name' => 'retomar_contato', 'language' => 'pt_BR', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Olá {{1}}, podemos continuar?']]],
                ['name' => 'rascunho', 'language' => 'pt_BR', 'status' => 'PENDING', 'components' => []],
            ]]),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.TPL']]]),
        ]);

        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['tel' => '11987654321', 'texto' => 'oi de novo'])
            ->assertStatus(422)->assertJson(['janela' => false]);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/messages'));

        $this->actingAs($u)->getJson('/negocios/whatsapp/modelos')->assertOk()
            ->assertJsonCount(1, 'modelos')->assertJsonPath('modelos.0.params', 1);

        $this->actingAs($u)->postJson('/negocios/whatsapp/enviar', ['tel' => '11987654321', 'modelo' => 'retomar_contato', 'idioma' => 'pt_BR', 'params' => ['Maria']])
            ->assertOk();
        Http::assertSent(fn ($r) => str_contains($r->url(), '/messages') && $r['type'] === 'template'
            && $r['template']['name'] === 'retomar_contato' && $r['template']['components'][0]['parameters'][0]['text'] === 'Maria');
    }

    public function test_quem_nao_e_dono_nao_configura(): void
    {
        $dono = $this->dono();
        $outro = User::factory()->create();
        $this->actingAs($outro)->postJson('/negocios/whatsapp/config', ['phone_number_id' => '1112223334', 'access_token' => 'EAAteste-token-acesso-123'], ['X-WF-Dono' => (string) $dono->id])
            ->assertForbidden();
        $this->assertSame(0, ZapConfig::count());
    }
}
