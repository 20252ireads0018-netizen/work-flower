<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportarWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    /** "Baixar tudo" manda as 5 áreas (Negócios é a quinta); antes o limite era 4 e dava "The ids field must not have more than 4 items". */
    public function test_baixar_tudo_exporta_as_cinco_areas(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['plano_slug' => 'premium', 'plano_expira_em' => now()->addMonth()])->save();
        $ids = ['corpo', 'mente', 'carteira', 'negocios', 'diverso'];

        $r = $this->actingAs($u)->getJson('/workflows/exportar?' . http_build_query(['ids' => $ids]))->assertOk();
        $this->assertSame($ids, array_column($r->json('workflows'), 'area'));
    }

    public function test_area_inexistente_continua_recusada(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/workflows/exportar?' . http_build_query(['ids' => ['corpo', 'xyz']]))->assertStatus(422);
    }
}
