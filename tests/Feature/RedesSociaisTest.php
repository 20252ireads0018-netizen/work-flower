<?php

namespace Tests\Feature;

use App\Models\RedeConta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Cartão Criador: Instagram (Login do Facebook + Graph API) e TikTok (Login Kit + Display API), APIs simuladas. */
class RedesSociaisTest extends TestCase
{
    use RefreshDatabase;

    private function configurar(): void
    {
        config(['services.meta.id' => 'app-id', 'services.meta.secret' => 'segredo-app', 'services.tiktok.key' => 'tt-key', 'services.tiktok.secret' => 'tt-secret']);
    }

    /** Começa a conexão e devolve o state guardado na sessão. */
    private function iniciar(User $u, string $rede): string
    {
        $r = $this->actingAs($u)->get("/redes/{$rede}/conectar");
        $r->assertRedirect();
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertNotEmpty($q['state']);

        return $q['state'];
    }

    public function test_sem_chaves_avisa_o_que_falta(): void
    {
        $u = User::factory()->create();
        config(['services.meta.id' => '', 'services.meta.secret' => '', 'services.tiktok.key' => '', 'services.tiktok.secret' => '']);

        $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertStatus(503)
            ->assertJson(['ok' => false, 'configurado' => false])->assertJsonFragment(['erro' => 'O Instagram ainda não foi configurado neste site (falta META_APP_ID e META_APP_SECRET no .env).']);
        $this->actingAs($u)->getJson('/redes/tiktok/metricas')->assertStatus(503);
        $this->actingAs($u)->get('/redes/tiktok/conectar')->assertRedirect()->assertSessionHas('ok');
        $this->actingAs($u)->getJson('/redes/facebook/metricas')->assertNotFound();
    }

    public function test_sem_conta_conectada_manda_conectar(): void
    {
        $this->configurar();
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertStatus(409)->assertJson(['conectado' => false]);
    }

    public function test_instagram_conecta_troca_por_token_longo_e_traz_metricas(): void
    {
        $this->configurar();
        $u = User::factory()->create();
        $state = $this->iniciar($u, 'instagram');

        Http::fake([
            'graph.facebook.com/*/oauth/access_token?*grant_type=fb_exchange_token*' => Http::response(['access_token' => 'token-longo', 'expires_in' => 5184000]),
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'token-curto', 'expires_in' => 3600]),
            'graph.facebook.com/*/me/accounts*' => Http::response(['data' => [
                ['id' => 'pg1', 'name' => 'Página sem IG'],
                ['id' => 'pg2', 'name' => 'Minha Página', 'instagram_business_account' => ['id' => '1784', 'username' => 'minhaconta', 'name' => 'Minha Conta']],
            ]]),
            'graph.facebook.com/*/1784/media*' => Http::response(['data' => [
                ['id' => 'm1', 'caption' => 'Post de teste', 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'permalink' => 'https://instagram.com/p/m1', 'timestamp' => '2026-11-01T10:00:00+0000', 'like_count' => 50, 'comments_count' => 4],
                ['id' => 'm2', 'caption' => 'Sem insights', 'media_type' => 'IMAGE', 'timestamp' => '2026-10-30T10:00:00+0000', 'like_count' => 10, 'comments_count' => 1],
            ]]),
            'graph.facebook.com/*/m1/insights*' => Http::response(['data' => [['name' => 'reach', 'values' => [['value' => 900]]], ['name' => 'views', 'values' => [['value' => 1500]]]]]),
            'graph.facebook.com/*/m2/insights*' => Http::response(['error' => ['code' => 100, 'message' => 'não tem']], 400),
            'graph.facebook.com/*/1784*' => Http::response(['username' => 'minhaconta', 'name' => 'Minha Conta', 'followers_count' => 2300, 'follows_count' => 120, 'media_count' => 88]),
        ]);

        $this->actingAs($u)->get("/redes/instagram/retorno?code=abc&state={$state}")->assertRedirect()->assertSessionHas('ok');
        $conta = RedeConta::where('user_id', $u->id)->where('rede', 'instagram')->firstOrFail();
        $this->assertSame('token-longo', $conta->access_token);
        $this->assertSame('1784', $conta->externo_id);
        $this->assertTrue($conta->expira_em->gt(now()->addDays(50)));
        // token guardado criptografado
        $this->assertStringNotContainsString('token-longo', (string) \DB::table('redes_contas')->value('access_token'));

        $j = $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertOk()->json();
        $this->assertSame(2300, $j['conta']['seguidores']);
        $this->assertSame('Reels', $j['posts'][0]['tipo']);
        $this->assertSame(900, $j['posts'][0]['alcance']);
        $this->assertSame(1500, $j['posts'][0]['views']);
        $this->assertNull($j['posts'][1]['alcance']);
        $this->assertArrayNotHasKey('access_token', $j);
    }

    public function test_retorno_com_state_errado_ou_de_outra_pessoa_e_recusado(): void
    {
        $this->configurar();
        $u = User::factory()->create();
        Http::fake();
        $this->actingAs($u)->get('/redes/instagram/retorno?code=abc&state=inventado')->assertRedirect(route('painel'));
        $state = $this->iniciar($u, 'instagram');
        $this->actingAs($u)->get("/redes/tiktok/retorno?code=abc&state={$state}")->assertRedirect(route('painel'));
        $this->assertSame(0, RedeConta::count());
        Http::assertNothingSent();
    }

    public function test_instagram_renova_o_token_perto_de_vencer(): void
    {
        $this->configurar();
        $u = User::factory()->create();
        $conta = RedeConta::create(['user_id' => $u->id, 'rede' => 'instagram', 'externo_id' => '1784', 'access_token' => 'velho', 'expira_em' => now()->addDays(3)]);
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'novo', 'expires_in' => 5184000]),
            'graph.facebook.com/*/1784/media*' => Http::response(['data' => []]),
            'graph.facebook.com/*/1784*' => Http::response(['username' => 'minhaconta', 'followers_count' => 10]),
        ]);
        $this->actingAs($u)->getJson('/redes/instagram/metricas')->assertOk();
        $this->assertSame('novo', $conta->fresh()->access_token);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'fb_exchange_token=velho'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/1784?') && str_contains($r->url(), 'access_token=novo'));

        // vencido: pede para conectar de novo
        $conta->update(['expira_em' => now()->subDay()]);
        $this->actingAs($u)->getJson('/redes/instagram/metricas?forcar=1')->assertStatus(409)->assertJson(['conectado' => false]);
    }

    public function test_tiktok_conecta_renova_e_traz_videos(): void
    {
        $this->configurar();
        $u = User::factory()->create();
        $state = $this->iniciar($u, 'tiktok');

        Http::fakeSequence('open.tiktokapis.com/v2/oauth/token/*')
            ->push(['access_token' => 'tt-acesso-1', 'refresh_token' => 'tt-refresh-1', 'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'open_id' => 'open-1'])
            ->push(['access_token' => 'tt-acesso-2', 'refresh_token' => 'tt-refresh-2', 'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'open_id' => 'open-1']);
        Http::fake([
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['data' => ['user' => ['open_id' => 'open-1', 'username' => 'eutiktok', 'display_name' => 'Eu', 'follower_count' => 5000, 'following_count' => 10, 'likes_count' => 99000, 'video_count' => 40]], 'error' => ['code' => 'ok']]),
            'open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => [
                ['id' => 'v1', 'title' => 'Vídeo 1', 'create_time' => 1762000000, 'share_url' => 'https://tiktok.com/@eutiktok/video/v1', 'view_count' => 12000, 'like_count' => 800, 'comment_count' => 30, 'share_count' => 12],
            ], 'has_more' => false], 'error' => ['code' => 'ok']]),
        ]);

        $this->actingAs($u)->get("/redes/tiktok/retorno?code=xyz&state={$state}")->assertRedirect();
        $conta = RedeConta::where('rede', 'tiktok')->firstOrFail();
        $this->assertSame('eutiktok', $conta->usuario);
        $this->assertSame('tt-refresh-1', $conta->refresh_token);

        $j = $this->actingAs($u)->getJson('/redes/tiktok/metricas')->assertOk()->json();
        $this->assertSame(5000, $j['conta']['seguidores']);
        $this->assertSame(99000, $j['conta']['curtidas']);
        $this->assertSame(12000, $j['videos'][0]['views']);

        // token de acesso vencendo: renova com o refresh_token
        $conta->update(['expira_em' => now()->subMinute()]);
        $this->actingAs($u)->getJson('/redes/tiktok/metricas?forcar=1')->assertOk();
        $this->assertSame('tt-acesso-2', $conta->fresh()->access_token);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth/token') && ($r['grant_type'] ?? null) === 'refresh_token' && $r['refresh_token'] === 'tt-refresh-1');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'video/list') && $r->hasHeader('Authorization', 'Bearer tt-acesso-2'));

        $this->actingAs($u)->deleteJson('/redes/tiktok')->assertOk();
        $this->assertSame(0, RedeConta::count());
    }
}
