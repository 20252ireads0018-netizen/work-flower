<?php

namespace Tests\Feature;

use App\Models\DiscordIntegracao;
use App\Models\RedeConta;
use App\Models\User;
use App\Support\MoverCartoes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Blocos do quadro Discord, Notion e Textos rápidos (public/js/blocos-integracoes.js) e "Publicar no Instagram" do
 * cartão Criador (InstagramPublicarController). APIs simuladas: nada sai para a rede.
 */
class BlocosIntegracoesTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz_ABCDEF-123';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    // ------------------------------------------------------------------ quadro

    public function test_quadro_carrega_os_blocos_novos_em_todas_as_areas(): void
    {
        $u = User::factory()->create();
        $html = $this->actingAs($u)->get('/areas/diverso')->assertOk()->getContent();
        $this->assertStringContainsString('js/blocos-integracoes.js', $html);
        $this->assertStringContainsString("b.tipo === 'discord'", $html);
        $this->assertStringContainsString("b.tipo === 'notion'", $html);
        $this->assertStringContainsString("b.tipo === 'textos'", $html);
        $this->assertStringContainsString('data-discord="' . url('/integracoes/discord') . '"', $html);
        $this->assertStringContainsString('data-notion="' . url('/integracoes/notion') . '"', $html);
        $this->assertStringContainsString('data-textos="1"', $html);
        $this->assertStringContainsString('data-publicar="' . route('redes.instagram.publicar') . '"', $html);

        foreach (['corpo', 'mente', 'carteira'] as $area) {
            $this->actingAs($u)->get('/areas/' . $area)->assertOk()->assertSee("b.tipo === 'discord'", false)->assertSee('js/blocos-integracoes.js', false);
        }
        foreach (['discord', 'notion', 'textos'] as $tipo) {
            $this->assertContains($tipo, MoverCartoes::TIPOS_COMUNS);
        }
    }

    // ------------------------------------------------------------------ Discord

    private function conectarDiscord(User $u): void
    {
        $i = new DiscordIntegracao(['webhook_url' => self::WEBHOOK, 'guild_id' => '999', 'canal_id' => '555']);
        $i->user_id = $u->id;
        $i->save();
    }

    public function test_discord_envia_mensagem_do_bloco_sem_devolver_o_webhook(): void
    {
        Http::fake(['discord.com/api/webhooks/*' => Http::response(null, 204)]);
        $u = User::factory()->create(['name' => 'Ana']);

        $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'Oi'])->assertStatus(409);
        Http::assertNothingSent();

        $this->conectarDiscord($u);
        $texto = "select * from tarefas; <b>@everyone</b>\nLinha 2";
        $r = $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['b64' => base64_encode(json_encode([
            'titulo' => 'Reunião às 15h', 'texto' => $texto, 'link' => 'https://exemplo.com/pauta',
        ]))])->assertOk()->assertJson(['ok' => true]);
        $this->assertStringNotContainsString('webhooks', $r->getContent());
        $this->assertStringNotContainsString('abcdefghijklmnop', $r->getContent());

        Http::assertSent(function (HttpRequest $req) use ($texto) {
            $e = $req->data()['embeds'][0] ?? [];

            return $req->url() === self::WEBHOOK && $e['title'] === 'Reunião às 15h' && $e['description'] === $texto
                && $e['url'] === 'https://exemplo.com/pauta' && $e['fields'][0]['value'] === 'Ana'
                && $req->data()['allowed_mentions'] === ['parse' => []];
        });
        $this->assertNotNull(DiscordIntegracao::first()->ultimo_envio_em);
    }

    public function test_discord_valida_a_mensagem_e_mostra_o_erro_do_webhook(): void
    {
        $u = User::factory()->create();
        $this->conectarDiscord($u);
        Http::fake(['discord.com/api/webhooks/*' => Http::response(['message' => 'Unknown Webhook'], 404)]);

        $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => ''])->assertJsonValidationErrors('titulo');
        $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'x', 'texto' => str_repeat('a', 3501)])->assertJsonValidationErrors('texto');
        $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'x', 'link' => 'ftp://exemplo.com/arquivo'])->assertJsonValidationErrors('link');
        Http::assertNothingSent();

        $j = $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'x'])->assertStatus(422)->assertJson(['ok' => false])->json();
        $this->assertStringContainsString('não reconhece mais este webhook', $j['erro']);
    }

    public function test_discord_envio_tem_limite_por_minuto(): void
    {
        Http::fake(['discord.com/api/webhooks/*' => Http::response(null, 204)]);
        $u = User::factory()->create();
        $this->conectarDiscord($u);
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'n' . $i])->assertOk();
        }
        $this->actingAs($u)->postJson('/integracoes/discord/enviar', ['titulo' => 'demais'])->assertStatus(429);
    }

    // ------------------------------------------------------------------ Instagram: publicar

    private function configurarMeta(): void
    {
        config(['services.meta.id' => 'app-id', 'services.meta.secret' => 'segredo-app']);
    }

    private function contaIg(User $u): RedeConta
    {
        return RedeConta::create(['user_id' => $u->id, 'rede' => 'instagram', 'externo_id' => '1784', 'usuario' => 'minhaconta',
            'access_token' => 'token-longo', 'expira_em' => now()->addDays(40)]);
    }

    /** Graph API simulada; $permitido diz se a conta deu instagram_content_publish. Guarda os image_url pedidos. */
    private function fakeGraph(bool $permitido, array &$urls = []): void
    {
        Http::fake(function (HttpRequest $r) use ($permitido, &$urls) {
            $u = $r->url();
            if (! str_starts_with($u, 'https://graph.facebook.com/')) return null;
            $p = parse_url($u, PHP_URL_PATH);
            if (str_ends_with($p, '/me/permissions')) {
                return Http::response(['data' => [
                    ['permission' => 'instagram_basic', 'status' => 'granted'],
                    ['permission' => 'instagram_content_publish', 'status' => $permitido ? 'granted' : 'declined'],
                ]]);
            }
            if (str_ends_with($p, '/1784/media') && $r->method() === 'POST') {
                if (isset($r['image_url'])) $urls[] = $r['image_url'];
                $id = isset($r['media_type']) ? 'carrossel-1' : 'cont-' . count($urls);

                return Http::response(['id' => $id]);
            }
            if (str_ends_with($p, '/1784/media_publish')) return Http::response(['id' => 'midia-9']);
            if (str_ends_with($p, '/midia-9')) return Http::response(['permalink' => 'https://www.instagram.com/p/abc/']);

            return Http::response(['status_code' => 'FINISHED', 'id' => basename($p)]); // status dos contêineres
        });
    }

    public function test_conectar_pede_a_permissao_de_publicar_so_quando_pedido(): void
    {
        $this->configurarMeta();
        $u = User::factory()->create();
        $normal = $this->actingAs($u)->get('/redes/instagram/conectar')->headers->get('Location');
        $this->assertStringNotContainsString('instagram_content_publish', urldecode($normal));

        $pub = urldecode($this->actingAs($u)->get('/redes/instagram/conectar?publicar=1')->headers->get('Location'));
        $this->assertStringContainsString('instagram_basic,instagram_manage_insights', $pub);
        $this->assertStringContainsString(',instagram_content_publish', $pub);
        $this->assertStringContainsString('auth_type=rerequest', $pub);
    }

    public function test_estado_mostra_se_pode_publicar(): void
    {
        $u = User::factory()->create();
        config(['services.meta.id' => '', 'services.meta.secret' => '']);
        $this->actingAs($u)->getJson('/redes/instagram/publicar')->assertOk()->assertJson(['conectado' => false, 'configurado' => false, 'conectar' => null]);

        $this->configurarMeta();
        $this->actingAs($u)->getJson('/redes/instagram/publicar')->assertOk()
            ->assertJson(['conectado' => false, 'configurado' => true, 'conectar' => route('redes.conectar', 'instagram') . '?publicar=1']);

        $this->contaIg($u);
        $this->fakeGraph(false);
        $j = $this->actingAs($u)->getJson('/redes/instagram/publicar')->assertOk()->assertJson(['conectado' => true, 'pode' => false, 'usuario' => 'minhaconta'])->json();
        $this->assertStringNotContainsString('token-longo', json_encode($j));
    }

    public function test_sem_a_permissao_nao_publica_e_manda_autorizar(): void
    {
        $this->configurarMeta();
        $u = User::factory()->create();
        $this->contaIg($u);
        $this->fakeGraph(false);

        $this->actingAs($u)->post('/redes/instagram/publicar', ['imagens' => [UploadedFile::fake()->image('a.jpg', 1080, 1080)], 'legenda' => 'Oi'], ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJson(['ok' => false, 'permissao' => true]);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/media'));
    }

    public function test_publica_foto_com_legenda_por_link_assinado_e_apaga_o_arquivo(): void
    {
        $this->configurarMeta();
        $u = User::factory()->create();
        $this->contaIg($u);
        $urls = [];
        $this->fakeGraph(true, $urls);

        $this->actingAs($u)->post('/redes/instagram/publicar', ['imagens' => [UploadedFile::fake()->image('foto.png', 1080, 1350)], 'legenda' => "Post novo\n#bastidores"], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['ok' => true, 'id' => 'midia-9', 'link' => 'https://www.instagram.com/p/abc/']);

        $this->assertCount(1, $urls);
        $this->assertMatchesRegularExpression('#/redes/instagram/imagem/[0-9a-f-]{36}\.jpg\?expires=\d+&signature=#', $urls[0]);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with(parse_url($r->url(), PHP_URL_PATH), '/1784/media') && $r['caption'] === "Post novo\n#bastidores");
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/1784/media_publish') && $r['creation_id'] === 'cont-1');
        // depois de publicada, a imagem sai do servidor
        $this->assertSame([], glob(storage_path('app/ig-publicar/*.jpg')) ?: []);
    }

    public function test_publica_carrossel_na_ordem(): void
    {
        $this->configurarMeta();
        $u = User::factory()->create();
        $this->contaIg($u);
        $urls = [];
        $this->fakeGraph(true, $urls);

        $imgs = [UploadedFile::fake()->image('1.jpg', 1080, 1080), UploadedFile::fake()->image('2.jpg', 1080, 1080), UploadedFile::fake()->image('3.webp', 1080, 1080)];
        $this->actingAs($u)->post('/redes/instagram/publicar', ['imagens' => $imgs, 'legenda' => 'Carrossel'], ['Accept' => 'application/json'])->assertOk()->assertJson(['ok' => true]);

        $this->assertCount(3, $urls);
        Http::assertSent(fn (HttpRequest $r) => ($r['media_type'] ?? null) === 'CAROUSEL' && $r['children'] === 'cont-1,cont-2,cont-3' && $r['caption'] === 'Carrossel');
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'carrossel-1');
    }

    public function test_publicar_recusa_proporcao_errada_e_arquivo_que_nao_e_imagem(): void
    {
        $this->configurarMeta();
        $u = User::factory()->create();
        $this->contaIg($u);
        $this->fakeGraph(true);

        $j = $this->actingAs($u)->post('/redes/instagram/publicar', ['imagens' => [UploadedFile::fake()->image('tira.jpg', 1080, 300)]], ['Accept' => 'application/json'])
            ->assertStatus(422)->json();
        $this->assertStringContainsString('proporções', $j['erro']);
        $this->actingAs($u)->post('/redes/instagram/publicar', ['imagens' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('imagens.0');
        $this->actingAs($u)->post('/redes/instagram/publicar', ['legenda' => str_repeat('a', 2201)], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors(['imagens', 'legenda']);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/media'));
        $this->assertSame([], glob(storage_path('app/ig-publicar/*.jpg')) ?: []);
    }

    public function test_imagem_so_sai_com_link_assinado(): void
    {
        $nome = '12345678-1234-1234-1234-123456789abc.jpg';
        @mkdir(storage_path('app/ig-publicar'), 0775, true);
        file_put_contents(storage_path('app/ig-publicar/' . $nome), 'jpeg');
        try {
            $this->get('/redes/instagram/imagem/' . $nome)->assertForbidden();
            $this->get(URL::temporarySignedRoute('redes.instagram.imagem', now()->addMinutes(5), ['arquivo' => $nome]))
                ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
            $this->get(URL::temporarySignedRoute('redes.instagram.imagem', now()->subMinute(), ['arquivo' => $nome]))->assertForbidden();
        } finally {
            @unlink(storage_path('app/ig-publicar/' . $nome));
        }
    }
}
