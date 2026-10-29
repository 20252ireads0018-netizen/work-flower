<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoCurtoTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_conta_liberada_ve_o_video_e_o_atalho_no_painel(): void
    {
        $dono = User::factory()->create(['email' => 'XExplosion20@gmail.com']);

        $this->actingAs($dono)->get('/video-curto')->assertOk()->assertSee('Baixar MP4');
        $this->actingAs($dono)->get('/painel')->assertOk()->assertSee('Vídeo curto de divulgação');
    }

    public function test_outras_contas_nao_veem_o_video(): void
    {
        $outro = User::factory()->create(['email' => 'outra@exemplo.com']);

        $this->actingAs($outro)->get('/video-curto')->assertNotFound();
        $this->actingAs($outro)->get('/painel')->assertOk()->assertDontSee('Vídeo curto de divulgação');
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/video-curto')->assertRedirect('/login');
    }
}
