<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowMembro;
use App\Support\Ambiente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fundo e música de cada workflow (AmbienteController / App\Support\Ambiente). */
class AmbienteTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function corpo(array $mais = []): array
    {
        return array_replace_recursive([
            'fundo'  => ['proprio' => true, 'cor' => '#112233', 'ajuste' => 'cover', 'escurecer' => 40, 'desfoque' => 2],
            'musica' => ['tipo' => 'nenhuma', 'youtube' => null, 'volume' => 50, 'auto' => true],
        ], $mais);
    }

    public function test_dono_salva_fundo_proprio_com_imagem_e_a_pagina_usa(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/ambiente/diverso', $this->corpo(['fundo' => ['imagem' => self::PNG]]))->assertOk()
            ->assertJsonPath('ambiente.fundo.imagem', true);

        $this->actingAs($u)->get('/ambiente/diverso/imagem')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($u)->get('/areas/diverso')->assertOk()
            ->assertSee('--papel: #112233', false)
            ->assertSee('ambiente/diverso/imagem?v=', false)
            ->assertSee('wfAmbiente', false);
        // outra área continua com o fundo geral
        $this->actingAs($u)->get('/areas/mente')->assertOk()->assertDontSee('--papel: #112233', false);
    }

    public function test_youtube_aceita_links_e_recusa_outros_sites(): void
    {
        $this->assertSame('dQw4w9WgXcQ', Ambiente::idYoutube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3'));
        $this->assertSame('dQw4w9WgXcQ', Ambiente::idYoutube('https://youtu.be/dQw4w9WgXcQ?si=x'));
        $this->assertSame('dQw4w9WgXcQ', Ambiente::idYoutube('https://youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', Ambiente::idYoutube('https://music.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(Ambiente::idYoutube('https://evil.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(Ambiente::idYoutube('javascript:alert(1)'));

        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/ambiente/mente', $this->corpo(['musica' => ['tipo' => 'youtube', 'youtube' => 'https://evil.com/x']]))
            ->assertStatus(422)->assertJsonValidationErrors('musica.youtube');
        $this->actingAs($u)->putJson('/ambiente/mente', $this->corpo(['musica' => ['tipo' => 'youtube', 'youtube' => 'https://youtu.be/dQw4w9WgXcQ']]))
            ->assertOk()->assertJsonPath('ambiente.musica.youtube', 'dQw4w9WgXcQ');
    }

    public function test_audio_envia_toca_e_apaga(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $mp3 = UploadedFile::fake()->createWithContent('Minha trilha.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x00", 400));

        $this->actingAs($u)->post('/ambiente/corpo/audio', ['audio' => $mp3], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('ambiente.musica.tipo', 'arquivo')->assertJsonPath('ambiente.musica.nome', 'Minha trilha');
        $this->actingAs($u)->get('/ambiente/corpo/audio')->assertOk();

        $txt = UploadedFile::fake()->createWithContent('virus.mp3', '<?php echo 1;');
        $this->actingAs($u)->post('/ambiente/corpo/audio', ['audio' => $txt], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($u)->deleteJson('/ambiente/corpo/audio')->assertOk()->assertJsonPath('ambiente.musica.tipo', 'nenhuma');
        $this->actingAs($u)->get('/ambiente/corpo/audio')->assertNotFound();
    }

    public function test_convidado_ve_o_ambiente_do_dono_mas_nao_muda(): void
    {
        $dono = User::factory()->create(['plano_slug' => 'premium', 'plano_expira_em' => now()->addMonth()]);
        $membro = User::factory()->create();
        WorkflowMembro::create(['dono_id' => $dono->id, 'membro_id' => $membro->id, 'area' => 'diverso']);
        $this->actingAs($dono)->putJson('/ambiente/diverso', $this->corpo(['fundo' => ['imagem' => self::PNG]]))->assertOk();

        $this->actingAs($membro)->get('/ambiente/diverso/imagem?de=' . $dono->id)->assertOk();
        $this->actingAs($membro)->putJson('/ambiente/diverso?de=' . $dono->id, $this->corpo())->assertForbidden();
        // quem não foi convidado não vê nada
        $estranho = User::factory()->create();
        $this->actingAs($estranho)->get('/ambiente/diverso/imagem?de=' . $dono->id)->assertForbidden();
    }

    public function test_cada_aba_tem_fundo_e_musica_proprios_e_a_area_continua_igual(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        // área: cor própria e YouTube
        $this->actingAs($u)->putJson('/ambiente/corpo', $this->corpo(['musica' => ['tipo' => 'youtube', 'youtube' => 'https://youtu.be/dQw4w9WgXcQ']]))->assertOk();
        // aba Dietas: imagem própria e música igual à da área
        $this->actingAs($u)->putJson('/ambiente/corpo?setor=dietas', $this->corpo(['fundo' => ['cor' => '#445566', 'imagem' => self::PNG], 'musica' => ['tipo' => 'herdar']]))
            ->assertOk()->assertJsonPath('setor', 'dietas')->assertJsonPath('ambiente.fundo.imagem', true)->assertJsonPath('ambiente.musica.tipo', 'herdar');
        // aba Treinos: só um áudio próprio
        $mp3 = UploadedFile::fake()->createWithContent('Treino pesado.mp3', "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xFF\xFB\x90\x00", 400));
        $this->actingAs($u)->post('/ambiente/corpo/audio?setor=treinos', ['audio' => $mp3], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('ambiente.musica.nome', 'Treino pesado');

        $this->actingAs($u)->get('/ambiente/corpo/imagem?setor=dietas')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($u)->get('/ambiente/corpo/imagem')->assertNotFound(); // a área não tem imagem
        $this->actingAs($u)->get('/ambiente/corpo/audio?setor=treinos')->assertOk();
        $this->actingAs($u)->get('/ambiente/corpo/audio')->assertNotFound();

        $setores = Ambiente::setoresDaPagina($u->id, 'corpo');
        $this->assertSame(['dietas', 'treinos'], collect(array_keys($setores))->sort()->values()->all());
        $this->assertSame('#445566', $setores['dietas']['fundo']['cor']);
        $this->assertSame('arquivo', $setores['treinos']['musica']['tipo']);
        $this->assertSame('youtube', Ambiente::paraPagina($u->id, 'corpo')['musica']['tipo']);
        // outra área não enxerga as abas do Corpo
        $this->assertSame([], Ambiente::setoresDaPagina($u->id, 'mente'));

        // a página leva o ambiente de cada aba para o navegador
        $this->actingAs($u)->get('/areas/corpo')->assertOk()->assertSee('wf-setor', false)->assertSee('#445566', false);

        // aba que volta a seguir a área some
        $this->actingAs($u)->putJson('/ambiente/corpo?setor=dietas', $this->corpo(['fundo' => ['proprio' => false, 'remover_imagem' => true], 'musica' => ['tipo' => 'herdar']]))->assertOk();
        $this->assertArrayNotHasKey('dietas', Ambiente::setoresDaPagina($u->id, 'corpo'));
        $this->actingAs($u)->deleteJson('/ambiente/corpo/audio?setor=treinos')->assertOk()->assertJsonPath('ambiente.musica.tipo', 'herdar');
        $this->assertSame([], Ambiente::setoresDaPagina($u->id, 'corpo'));
    }

    public function test_setor_invalido_e_herdar_so_na_aba(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->putJson('/ambiente/diverso?setor=' . urlencode('../x y'), $this->corpo())->assertNotFound();
        $this->actingAs($u)->get('/ambiente/diverso/imagem?setor=' . str_repeat('a', 61))->assertNotFound();
        // a área inteira não tem "igual à área"
        $this->actingAs($u)->putJson('/ambiente/diverso', $this->corpo(['musica' => ['tipo' => 'herdar']]))->assertStatus(422);
        // aba criada pelo usuário no Diverso (id livre)
        $this->actingAs($u)->putJson('/ambiente/diverso?setor=aba_k3j9x.2', $this->corpo())->assertOk()->assertJsonPath('setor', 'aba_k3j9x.2');
    }

    public function test_limite_de_abas_com_ambiente_proprio(): void
    {
        $u = User::factory()->create();
        for ($i = 1; $i < 60; $i++) {
            Ambiente::gravar($u->id, 'diverso', "aba{$i}", Ambiente::opcoes($u->id, 'diverso', "aba{$i}"));
        }
        $this->actingAs($u)->putJson('/ambiente/diverso?setor=aba60', $this->corpo())->assertOk();
        $this->actingAs($u)->putJson('/ambiente/diverso?setor=aba61', $this->corpo())->assertStatus(422)->assertJsonValidationErrors('setor');
        // aba que já tem ambiente continua podendo mudar
        $this->actingAs($u)->putJson('/ambiente/diverso?setor=aba60', $this->corpo())->assertOk();
    }

    public function test_convidado_nao_muda_o_ambiente_da_aba(): void
    {
        $dono = User::factory()->create(['plano_slug' => 'premium', 'plano_expira_em' => now()->addMonth()]);
        $membro = User::factory()->create();
        WorkflowMembro::create(['dono_id' => $dono->id, 'membro_id' => $membro->id, 'area' => 'diverso']);
        $this->actingAs($dono)->putJson('/ambiente/diverso?setor=quadro', $this->corpo(['fundo' => ['imagem' => self::PNG]]))->assertOk();

        $this->actingAs($membro)->get('/ambiente/diverso/imagem?setor=quadro&de=' . $dono->id)->assertOk();
        $this->actingAs($membro)->putJson('/ambiente/diverso?setor=quadro&de=' . $dono->id, $this->corpo())->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/ambiente/diverso/imagem?setor=quadro&de=' . $dono->id)->assertForbidden();
    }

    public function test_area_invalida_e_visitante(): void
    {
        $this->get('/ambiente/diverso/imagem')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/ambiente/xyz/imagem')->assertNotFound();
    }
}
