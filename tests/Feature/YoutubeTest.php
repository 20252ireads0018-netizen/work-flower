<?php

namespace Tests\Feature;

use App\Http\Controllers\YoutubeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** GET /youtube/canal: métricas públicas do canal para o cartão Criador de conteúdo (API simulada). */
class YoutubeTest extends TestCase
{
    use RefreshDatabase;

    public function test_entende_o_canal_de_varios_jeitos(): void
    {
        $this->assertSame(['forHandle' => '@meucanal'], YoutubeController::alvo('https://www.youtube.com/@meucanal/videos'));
        $this->assertSame(['forHandle' => '@meucanal'], YoutubeController::alvo('meucanal'));
        $this->assertSame(['id' => 'UC1234567890123456789012'], YoutubeController::alvo('youtube.com/channel/UC1234567890123456789012'));
        $this->assertNull(YoutubeController::alvo('x'));
    }

    public function test_sem_chave_avisa_e_com_chave_traz_canal_e_videos(): void
    {
        $u = User::factory()->create();
        config(['services.youtube.key' => '']);
        $this->actingAs($u)->getJson('/youtube/canal?canal=@meucanal')->assertStatus(503)->assertJson(['ok' => false]);

        config(['services.youtube.key' => 'chave-teste']);
        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [[
                'id' => 'UCabc', 'snippet' => ['title' => 'Meu Canal', 'customUrl' => '@meucanal'],
                'statistics' => ['subscriberCount' => '1200', 'viewCount' => '50000', 'videoCount' => '30'],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UUabc']],
            ]]]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [['contentDetails' => ['videoId' => 'abcdefghijk']]]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [[
                'id' => 'abcdefghijk', 'snippet' => ['title' => 'Vídeo 1', 'publishedAt' => '2026-11-01T10:00:00Z'],
                'statistics' => ['viewCount' => '900', 'likeCount' => '80', 'commentCount' => '10'],
            ]]]),
        ]);
        $this->actingAs($u)->getJson('/youtube/canal?canal=@meucanal')->assertOk()
            ->assertJsonPath('canal.inscritos', 1200)->assertJsonPath('videos.0.views', 900)->assertJsonPath('videos.0.publicado', '2026-11-01');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'forHandle=%40meucanal') && str_contains($r->url(), 'key=chave-teste'));
        $this->getJson('/youtube/canal?canal=@meucanal')->assertOk(); // ainda logado
        auth()->logout();
        $this->getJson('/youtube/canal?canal=@meucanal')->assertUnauthorized();
    }
}
