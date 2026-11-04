<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\DiscordIntegracao;
use App\Models\Evento;
use App\Models\Loja;
use App\Models\LojaProduto;
use App\Models\Tarefa;
use App\Models\User;
use App\Support\Calendario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Negócios › Integrações (Discord por webhook, bot opcional) e Negócios › Calls (calendário, e-mail e Discord). */
class DiscordECallsTest extends TestCase
{
    use RefreshDatabase;

    // endereço de mentira, no formato do Discord (nada sai para a rede: Http::fake)
    private const WEBHOOK = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz_ABCDEF-123';
    // token de mentira no formato do Discord, montado em partes para não parecer um token de verdade no código
    private const BOT_PARTES = ['MTIzNDU2Nzg5MDEyMzQ1Njc4', 'GabcDE', 'fakefakefakefakefakefakefake1'];

    private static function bot(): string { return implode('.', self::BOT_PARTES); }

    /** Quando preenchido, o Discord de mentira responde com este erro a tudo. */
    private ?int $falha = null;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake([
            'discord.com/*' => fn () => $this->falha ? Http::response(['message' => 'erro'], $this->falha) : null,
            'discord.com/api/webhooks/*' => fn (HttpRequest $r) => $r->method() === 'GET'
                ? Http::response(['id' => '123456789012345678', 'name' => 'Work Flower', 'guild_id' => '999', 'channel_id' => '555'])
                : Http::response(null, 204),
            'discord.com/api/v10/guilds/999/channels' => Http::response([
                ['id' => '555', 'name' => 'avisos', 'type' => 0, 'position' => 1],
                ['id' => '777', 'name' => 'Reunião', 'type' => 2, 'position' => 2],
                ['id' => '888', 'name' => 'categoria', 'type' => 4, 'position' => 0],
            ]),
            'discord.com/api/v10/guilds/999' => Http::response(['id' => '999', 'name' => 'Minha empresa']),
        ]);
    }

    private function conta(string $plano = 'basico'): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => $plano, 'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth()]);

        return $u->fresh();
    }

    private function conectar(User $u): void
    {
        $this->actingAs($u)->postJson('/integracoes/discord', ['webhook_url' => self::WEBHOOK])->assertOk()->assertJson(['conectado' => true]);
    }

    /** Mensagens que foram mandadas pelo webhook (POST). */
    private function enviadas(): array
    {
        return collect(Http::recorded())->filter(fn ($p) => $p[0]->method() === 'POST' && str_contains($p[0]->url(), '/api/webhooks/'))
            ->map(fn ($p) => $p[0]->data()['embeds'][0])->values()->all();
    }

    public function test_conecta_pelo_webhook_guarda_criptografado_e_nao_devolve_o_endereco(): void
    {
        $u = $this->conta();
        $this->actingAs($u)->postJson('/integracoes/discord', ['webhook_url' => 'https://exemplo.com/api/webhooks/1/abc'])->assertJsonValidationErrors('webhook_url');
        Http::assertNothingSent(); // endereço que não é do Discord nem é tentado

        $this->conectar($u);
        $i = DiscordIntegracao::where('user_id', $u->id)->firstOrFail();
        $this->assertSame(self::WEBHOOK, $i->webhook_url);
        $this->assertSame(['999', '555'], [$i->guild_id, $i->canal_id]);
        $cru = DB::table('discord_integracoes')->where('id', $i->id)->value('webhook_url');
        $this->assertStringNotContainsString('discord.com', $cru);

        $j = $this->actingAs($u)->getJson('/integracoes/discord')->assertOk()->json();
        $this->assertTrue($j['conectado']);
        $this->assertStringNotContainsString('webhooks', json_encode($j));

        $this->actingAs($u)->postJson('/integracoes/discord/testar')->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('✅ Work Flower conectado', $this->enviadas()[0]['title']);
    }

    public function test_webhook_apagado_no_discord_e_recusado(): void
    {
        $this->falha = 404;
        $u = $this->conta();
        $this->actingAs($u)->postJson('/integracoes/discord', ['webhook_url' => self::WEBHOOK])->assertJsonValidationErrors('webhook_url');
        $this->assertFalse(DiscordIntegracao::exists());
    }

    public function test_bot_lista_canais_de_texto_e_voz_e_guarda_o_nome_do_servidor(): void
    {
        $u = $this->conta();
        $this->conectar($u);
        $this->actingAs($u)->postJson('/integracoes/discord', ['bot_token' => self::bot()])->assertOk()
            ->assertJson(['tem_bot' => true, 'servidor' => 'Minha empresa', 'canal' => 'avisos']);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/guilds/999/channels') && $r->hasHeader('Authorization', 'Bot ' . self::bot()));

        $canais = $this->actingAs($u)->getJson('/integracoes/discord/canais')->assertOk()->json('canais');
        $this->assertSame([['avisos', 'texto'], ['Reunião', 'voz']], array_map(fn ($c) => [$c['nome'], $c['tipo']], $canais));
        $this->assertSame('https://discord.com/channels/999/777', $canais[1]['link']);

        $this->assertStringNotContainsString(self::bot(), $this->actingAs($u)->getJson('/integracoes/discord')->getContent());
        $this->actingAs($u)->postJson('/integracoes/discord', ['tirar_bot' => true])->assertOk()->assertJson(['tem_bot' => false]);
    }

    public function test_tarefa_criada_movida_e_delegada_avisam_o_discord_do_dono_e_o_aviso_pode_ser_desligado(): void
    {
        $dono = $this->conta();
        $m = $this->conta('gratuito');
        $this->actingAs($dono)->postJson('/compartilhar/negocios', ['email' => $m->email])->assertOk();
        $this->conectar($dono);

        $this->actingAs($dono)->post('/areas/negocios/tarefas', ['titulo' => 'Fechar proposta'])->assertRedirect();
        $t = Tarefa::firstOrFail();
        $this->actingAs($dono)->put("/tarefas/{$t->id}", ['status' => 'andamento'])->assertRedirect();
        $this->actingAs($dono)->put("/tarefas/{$t->id}", ['responsavel_id' => $m->id])->assertRedirect();

        $titulos = array_column($this->enviadas(), 'title');
        $this->assertSame('🆕 Tarefa criada', $titulos[0]);
        $this->assertStringStartsWith('➡️ Tarefa movida para', $titulos[1]);
        $this->assertSame('👤 Tarefa delegada a ' . $m->name, $titulos[2]);
        $campos = collect($this->enviadas()[0]['fields'])->pluck('value', 'name');
        $this->assertSame(['Fechar proposta', 'Negócios'], [$campos['Tarefa'], $campos['Área']]);

        // desligado: não manda mais
        $this->actingAs($dono)->postJson('/integracoes/discord', ['eventos' => ['tarefa_criada' => false]])->assertOk()
            ->assertJsonPath('eventos.tarefa_criada', false)->assertJsonPath('eventos.tarefa_movida', true);
        $antes = count($this->enviadas());
        $this->actingAs($dono)->post('/areas/negocios/tarefas', ['titulo' => 'Outra'])->assertRedirect();
        $this->assertCount($antes, $this->enviadas());
    }

    public function test_discord_fora_do_ar_nao_impede_criar_a_tarefa(): void
    {
        $u = $this->conta();
        $this->conectar($u);
        $this->falha = 500;
        $this->actingAs($u)->post('/areas/negocios/tarefas', ['titulo' => 'Mesmo assim'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Tarefa::where('titulo', 'Mesmo assim')->exists());
        $this->assertSame('O Discord respondeu com erro 500.', DiscordIntegracao::first()->ultimo_erro);
    }

    public function test_pedido_novo_na_loja_avisa_o_discord_do_dono(): void
    {
        $u = $this->conta('gratuito');
        // o Pix das lojas vai para a chave da plataforma: sem ela a loja não recebe pedidos
        \App\Support\Pix::salvarConfig(['tipo' => 'email', 'chave' => 'pix@workflower.com', 'nome' => 'Work Flower', 'cidade' => 'Recife']);
        $this->actingAs($u)->post('/carteira/loja', ['nome' => 'Doces da Ana', 'slug' => 'doces-da-ana', 'publicada' => 1, 'cor' => '#AA3355'])->assertRedirect();
        $this->actingAs($u)->post('/carteira/loja/pix', ['tipo' => 'email', 'chave' => 'ana@exemplo.com', 'nome' => 'Ana Souza', 'cidade' => 'Recife'])->assertSessionHasNoErrors();
        $loja = Loja::where('user_id', $u->id)->firstOrFail();
        $p = LojaProduto::forceCreate(['loja_id' => $loja->id, 'nome' => 'Bolo', 'preco' => 30, 'ativo' => true]);
        $this->conectar($u);

        auth()->logout();
        $this->post('/loja/doces-da-ana/pedido', ['nome' => 'Cliente', 'contato' => '81999998888', 'itens' => json_encode([['id' => $p->id, 'qtd' => 2]])])->assertRedirect();

        $msg = $this->enviadas()[0];
        $this->assertSame('🛍️ Pedido novo na loja Doces da Ana', $msg['title']);
        $this->assertSame('2× Bolo', $msg['description']);
        $this->assertSame('R$ 60,00', collect($msg['fields'])->firstWhere('name', 'Total')['value']);
        $this->assertStringNotContainsString('81999998888', json_encode($msg)); // contato do cliente não vai para o Discord
    }

    public function test_lembrete_do_calendario_vai_para_o_discord_mesmo_sem_email(): void
    {
        Mail::fake();
        $u = $this->conta();
        $this->conectar($u);
        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00', Calendario::FUSO));
        Evento::forceCreate(['user_id' => $u->id, 'titulo' => 'Reunião com fornecedor', 'inicio' => '2026-11-04 12:10:00', 'repete' => 'nao', 'lembrete_min' => 15, 'lembrar_email' => false]);

        $this->artisan('calendario:lembrar')->assertSuccessful();
        $this->artisan('calendario:lembrar')->assertSuccessful(); // não repete
        Mail::assertNothingSent();
        $this->assertSame(['⏰ Lembrete: Reunião com fornecedor'], array_column($this->enviadas(), 'title'));
        Carbon::setTestNow();
    }

    public function test_call_entra_no_calendario_de_cada_participante_avisa_o_discord_e_some_ao_cancelar(): void
    {
        Mail::fake();
        $dono = $this->conta();
        $m = $this->conta('gratuito');
        $estranho = $this->conta('gratuito');
        $this->actingAs($dono)->postJson('/compartilhar/negocios', ['email' => $m->email])->assertOk();
        $this->conectar($dono);

        $j = $this->actingAs($dono)->getJson('/calls')->assertOk()->json();
        $this->assertEqualsCanonicalizing([$dono->id, $m->id], array_column($j['pessoas'], 'id'));

        $base = ['titulo' => 'Planejamento', 'inicio' => '2026-11-10T14:00', 'duracao_min' => 45, 'plataforma' => 'jitsi', 'lembrete_min' => 15];
        $this->actingAs($dono)->postJson('/calls', $base + ['participantes' => [$estranho->id]])->assertJsonValidationErrors('participantes.0');

        // sem link no Jitsi: o servidor gera uma sala
        $c = $this->actingAs($dono)->postJson('/calls', $base + ['participantes' => [$m->id]])->assertOk()->json('call');
        $this->assertMatchesRegularExpression('#^https://meet\.jit\.si/WorkFlower-[0-9a-f]{16}$#', $c['link']);

        $eventos = Evento::where('call_id', $c['id'])->orderBy('user_id')->get();
        $this->assertSame([$dono->id, $m->id], $eventos->pluck('user_id')->all());
        $this->assertSame('📞 Planejamento', $eventos[1]->titulo);
        $this->assertSame('2026-11-10 14:45', $eventos[1]->fim->format('Y-m-d H:i'));
        $this->assertTrue($eventos[1]->lembrar_email);
        $this->assertStringContainsString($c['link'], $eventos[1]->descricao);

        $msg = $this->enviadas()[0];
        $this->assertSame('📞 Call marcada: Planejamento', $msg['title']);
        $this->assertSame($c['link'], $msg['url']);
        $this->assertSame($m->name, collect($msg['fields'])->firstWhere('name', 'Participantes')['value']);

        // o participante vê a call (sem poder mudar) e o evento aparece no calendário dele
        $doM = $this->actingAs($m)->getJson('/calls')->assertOk()->json('calls');
        $this->assertSame([[$c['id'], false]], array_map(fn ($x) => [$x['id'], $x['meu']], $doM));
        $this->actingAs($m)->getJson('/calendario?de=2026-11-10&ate=2026-11-10')->assertOk()->assertJsonPath('eventos.0.titulo', '📞 Planejamento');
        $this->actingAs($m)->putJson("/calls/{$c['id']}", $base)->assertNotFound();
        $this->actingAs($m)->deleteJson("/calls/{$c['id']}")->assertNotFound();

        // link do Zoom: a plataforma acompanha o link; tirar o participante tira o evento dele
        $this->actingAs($dono)->putJson("/calls/{$c['id']}", ['plataforma' => 'meet', 'link' => 'https://us02web.zoom.us/j/123'] + $base)->assertOk()
            ->assertJsonPath('call.plataforma', 'zoom');
        $this->assertSame([$dono->id], Evento::where('call_id', $c['id'])->pluck('user_id')->all());
        $this->actingAs($dono)->putJson("/calls/{$c['id']}", ['plataforma' => 'outro', 'link' => 'javascript:alert(1)'] + $base)->assertJsonValidationErrors('link');

        $this->actingAs($dono)->deleteJson("/calls/{$c['id']}")->assertOk();
        $this->assertFalse(Call::exists());
        $this->assertFalse(Evento::whereNotNull('call_id')->exists());
        $this->assertSame('❌ Call cancelada: Planejamento', collect($this->enviadas())->last()['title']);
    }

    public function test_lembrete_da_call_sai_por_email_para_o_participante(): void
    {
        Mail::fake();
        $dono = $this->conta();
        $m = $this->conta('gratuito');
        $this->actingAs($dono)->postJson('/compartilhar/negocios', ['email' => $m->email])->assertOk();
        $this->actingAs($dono)->postJson('/calls', ['titulo' => 'Daily', 'inicio' => '2026-11-04T12:10', 'duracao_min' => 15, 'plataforma' => 'meet',
            'link' => 'https://meet.google.com/abc-defg-hij', 'participantes' => [$m->id], 'lembrete_min' => 15])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-11-04 12:00', Calendario::FUSO));
        $this->artisan('calendario:lembrar')->assertSuccessful();
        Mail::assertSent(\App\Mail\LembreteEvento::class, fn ($mail) => $mail->hasTo($m->email) && $mail->evento->titulo === '📞 Daily');
        Mail::assertSent(\App\Mail\LembreteEvento::class, 2); // dono e participante
        Http::assertNothingSent(); // ninguém ligou o Discord
        Carbon::setTestNow();
    }

    public function test_corpo_em_base64_passa_texto_que_parece_sql_e_corpo_estragado_e_recusado(): void
    {
        $u = $this->conta();
        $pauta = "select * from clientes where 1=1; <script>alert('x')</script> — ação ✓";
        $corpo = ['titulo' => str_repeat('Nome muito comprido ', 5), 'inicio' => '2026-11-10T14:00', 'duracao_min' => 30, 'plataforma' => 'jitsi', 'descricao' => $pauta];
        $c = $this->actingAs($u)->postJson('/calls', ['b64' => base64_encode(json_encode($corpo))])->assertOk()->json('call');
        $this->assertSame($pauta, $c['descricao']);

        $this->actingAs($u)->postJson('/calls', ['b64' => '%%%não é base64'])->assertStatus(422);
        $this->actingAs($u)->postJson('/integracoes/discord', ['b64' => base64_encode(json_encode(['webhook_url' => self::WEBHOOK]))])->assertOk()->assertJson(['conectado' => true]);
    }
}
