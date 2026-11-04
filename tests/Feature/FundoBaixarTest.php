<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** POST /fundo/baixar: imagem ou GIF de um link para o fundo (sem rede: só as recusas que não saem do servidor). */
class FundoBaixarTest extends TestCase
{
    use RefreshDatabase;

    public function test_precisa_de_login(): void
    {
        $this->postJson('/fundo/baixar', ['url' => 'https://example.com/a.gif'])->assertUnauthorized();
    }

    public function test_recusa_enderecos_internos_e_outros_esquemas(): void
    {
        $u = User::factory()->create();
        foreach (['http://127.0.0.1/a.gif', 'http://192.168.0.10/a.gif', 'http://10.0.0.1/a.gif', 'http://[::1]/a.gif',
                  'file:///etc/passwd', 'ftp://example.com/a.gif', 'http://user:senha@example.com/a.gif', 'http://example.com:22/a.gif', 'nada'] as $url) {
            $this->actingAs($u)->postJson('/fundo/baixar', ['url' => $url])->assertStatus(422)->assertJsonValidationErrors('url');
        }
    }
}
