<?php

namespace Tests\Feature;

use App\Models\DiversoDado;
use App\Models\User;
use App\Support\AbasRetiradas;
use App\Support\GeradorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Gerador de workflows por tema: módulos por palavra-chave, imagens de bancos livres com crédito. */
class GeradorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\nfalso";

    private function fakeOpenverse(): void
    {
        Http::fake(function (HttpRequest $r) {
            if (str_starts_with($r->url(), 'https://api.openverse.org/v1/images/')) {
                return Http::response(['results' => [
                    ['title' => 'Halteres', 'creator' => 'Ana', 'license' => 'cc0', 'thumbnail' => 'https://img.exemplo/1.png', 'foreign_landing_url' => 'https://flickr.exemplo/1'],
                    ['title' => 'Esteira', 'creator' => 'Bia', 'license' => 'by', 'license_version' => '4.0', 'thumbnail' => 'https://img.exemplo/2.png', 'foreign_landing_url' => 'https://flickr.exemplo/2'],
                ]]);
            }
            if (str_starts_with($r->url(), 'https://img.exemplo/')) {
                return Http::response(self::PNG, 200, ['Content-Type' => 'image/png']);
            }

            return Http::response('', 404);
        });
    }

    public function test_modulos_pelo_tema(): void
    {
        $this->assertSame(['treino', 'dieta'], GeradorWorkflow::modulosDe('Academia e dieta'));
        $this->assertSame(['musica'], GeradorWorkflow::modulosDe('Aprender violão')); // "aprender" é estudo, mas violão vem depois
        $this->assertContains('estudo', GeradorWorkflow::modulosDe('ENEM 2027 estudar'));
        $this->assertSame([], GeradorWorkflow::modulosDe('Coleção de selos'));

        $abas = array_column(GeradorWorkflow::montar('Coleção de selos')['abas'], 0);
        $this->assertSame(['Visão geral', 'Planejamento', 'Ideias e recursos'], $abas);
    }

    public function test_gera_area_com_imagens_livres_e_creditos(): void
    {
        $this->fakeOpenverse();
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        $this->actingAs($u)->post('/workflows-gerar', ['tema' => 'Academia e dieta'])->assertRedirect();

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'api.openverse.org') && $r['license'] === 'cc0,pdm,by,by-sa');
        $e = AbasRetiradas::decodificar(DiversoDado::where('user_id', $u->id)->where('chave', 'estado')->value('valor'));
        $this->assertSame(['Visão geral', 'Treinos', 'Alimentação'], array_map(fn ($a) => $a->nome, $e->abas));

        $imgs = array_values(array_filter($e->blocos, fn ($b) => $b->tipo === 'imagem'));
        $this->assertCount(2, $imgs);
        $this->assertStringContainsString('“Halteres”, de Ana (CC0, via Openverse)', $imgs[0]->dados->legenda ?? $imgs[0]->dados['legenda']);
        $src = json_decode(DiversoDado::where('user_id', $u->id)->where('chave', 'img.' . $imgs[0]->id)->value('valor'));
        $this->assertStringStartsWith('data:image/png;base64,', $src);

        $creditos = collect($e->blocos)->firstWhere('titulo', '📷 Créditos das imagens');
        $texto = $creditos->dados->texto ?? $creditos->dados['texto'];
        $this->assertStringContainsString('Esteira', $texto);
        $this->assertStringContainsString('CC BY 4.0', $texto);
        $this->assertStringContainsString('https://flickr.exemplo/2', $texto);
    }

    public function test_sem_imagens_o_workflow_sai_igual(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        $this->actingAs($u)->postJson('/workflows-gerar', ['tema' => 'Viagem para o Chile'])->assertOk()->assertJsonPath('area.imagens', 0);
        $e = AbasRetiradas::decodificar(DiversoDado::where('user_id', $u->id)->where('chave', 'estado')->value('valor'));
        $this->assertContains('Viagem', array_map(fn ($a) => $a->nome, $e->abas));
        $this->assertEmpty(array_filter($e->blocos, fn ($b) => $b->tipo === 'imagem'));

        $this->actingAs($u)->postJson('/workflows-gerar', ['tema' => 'ab'])->assertStatus(422);
    }
}
