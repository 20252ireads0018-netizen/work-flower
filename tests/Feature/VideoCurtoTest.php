<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoCurtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_video_saiu_de_todas_as_contas(): void
    {
        $dono = User::factory()->create(['email' => 'xexplosion20@gmail.com']);

        $this->actingAs($dono)->get('/video-curto')->assertNotFound();
        $this->actingAs($dono)->get('/painel')->assertOk()->assertDontSee('Vídeo curto de divulgação');
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/video-curto')->assertRedirect('/login');
    }
}
