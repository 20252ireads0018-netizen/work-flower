<?php

namespace Tests\Feature;

use App\Models\CorpoDado;
use App\Models\User;
use App\Support\Midia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MidiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** GIF "de mentira" com cabeçalho de verdade (o finfo reconhece pelo começo) e o tamanho pedido. */
    private function gif(int $bytes): string
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');

        return $gif . str_repeat("\0", max(0, $bytes - strlen($gif)));
    }

    private function enviar(User $u, string $bin, string $tipo = 'image/gif'): string
    {
        $ini = $this->actingAs($u)->postJson('/midia', ['tipo' => $tipo, 'tamanho' => strlen($bin)])->assertOk();
        $token = $ini->json('token');
        $tam = $ini->json('pedaco');
        foreach (str_split($bin, $tam) as $n => $parte) {
            $this->actingAs($u)->putJson("/midia/{$token}/{$n}", ['dados' => base64_encode($parte)])->assertOk();
        }
        $this->actingAs($u)->postJson("/midia/{$token}/fim")->assertOk()->assertJsonPath('token', $token);

        return $token;
    }

    public function test_gif_de_9_mb_chega_em_pedacos_e_vira_fundo_da_area(): void
    {
        $u = User::factory()->create();
        $bin = $this->gif(9 * 1024 * 1024);
        $token = $this->enviar($u, $bin);

        $this->assertSame("midia/{$u->id}/{$token}.gif", Midia::caminho($token));
        $ver = $this->actingAs($u)->get("/midia/{$token}")->assertOk();
        $this->assertSame('image/gif', $ver->headers->get('Content-Type'));

        // fundo geral usando o arquivo
        $this->actingAs($u)->putJson('/fundo', ['cor' => null, 'ajuste' => 'cover', 'escurecer' => 40, 'desfoque' => 0, 'midia' => $token])->assertOk()->assertJsonPath('imagem', true);
        $this->assertSame('midia:' . $token, CorpoDado::where('user_id', $u->id)->where('chave', 'fundo.img')->value('valor'));
        $this->actingAs($u)->get('/fundo/imagem')->assertOk();

        // trocar por outro arquivo apaga o antigo do disco
        $outro = $this->enviar($u, $this->gif(5000));
        $this->actingAs($u)->putJson('/fundo', ['cor' => null, 'ajuste' => 'cover', 'escurecer' => 40, 'desfoque' => 0, 'midia' => $outro])->assertOk();
        $this->assertNull(Midia::caminho($token));
    }

    public function test_recusa_o_que_nao_e_imagem_tamanho_demais_e_token_de_outra_pessoa(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/midia', ['tipo' => 'image/gif', 'tamanho' => 26 * 1024 * 1024])->assertStatus(422);
        $this->actingAs($u)->postJson('/midia', ['tipo' => 'text/html', 'tamanho' => 10])->assertStatus(422);

        // conteúdo que não é imagem, mesmo dizendo que é GIF
        $ini = $this->actingAs($u)->postJson('/midia', ['tipo' => 'image/gif', 'tamanho' => 11])->json('token');
        $this->actingAs($u)->putJson("/midia/{$ini}/0", ['dados' => base64_encode('<html>x</ht')])->assertOk();
        $this->actingAs($u)->postJson("/midia/{$ini}/fim")->assertStatus(422);
        $this->assertNull(Midia::caminho($ini));

        // pedaço fora de ordem
        $t2 = $this->actingAs($u)->postJson('/midia', ['tipo' => 'image/gif', 'tamanho' => 100])->json('token');
        $this->actingAs($u)->putJson("/midia/{$t2}/1", ['dados' => base64_encode('abc')])->assertStatus(422);

        // fundo com o arquivo de outra conta
        $alheio = $this->enviar(User::factory()->create(), $this->gif(3000));
        $this->actingAs($u)->putJson('/fundo', ['cor' => null, 'ajuste' => 'cover', 'escurecer' => 40, 'desfoque' => 0, 'midia' => $alheio])->assertStatus(422);
    }

    public function test_ambiente_da_area_aceita_o_arquivo_grande(): void
    {
        $u = User::factory()->create();
        $token = $this->enviar($u, $this->gif(6 * 1024 * 1024));
        $this->actingAs($u)->putJson('/ambiente/diverso', [
            'fundo'  => ['proprio' => true, 'cor' => '#111111', 'ajuste' => 'cover', 'escurecer' => 30, 'desfoque' => 0, 'midia' => $token],
            'musica' => ['tipo' => 'nenhuma', 'volume' => 50, 'auto' => false],
        ])->assertOk();
        $this->actingAs($u)->get('/ambiente/diverso/imagem')->assertOk();
    }
}
