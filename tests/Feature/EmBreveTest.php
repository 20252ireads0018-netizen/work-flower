<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EmBreve;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmBreveTest extends TestCase
{
    use RefreshDatabase;

    private function semNada(): void
    {
        config([
            'services.meta.id' => null, 'services.meta.secret' => null,
            'services.tiktok.key' => null, 'services.tiktok.secret' => null,
            'marketplaces.canais.shopee.partner_id' => null, 'marketplaces.canais.amazon.client_id' => null,
            'armazenamento.google.client_id' => 'id', 'armazenamento.google.client_secret' => 'seg',
            'services.google.client_id' => 'id', 'services.google.client_secret' => 'seg',
            'services.google.publico' => false, 'services.google.gmail_publico' => false,
            'mail.default' => 'log',
        ]);
    }

    public function test_painel_mostra_em_breve_sem_detalhes_tecnicos(): void
    {
        $this->semNada();
        $html = $this->actingAs(User::factory()->create())->get('/painel')->assertOk()->getContent();

        $this->assertStringContainsString('Em breve no Work Flower', $html);
        foreach (['Instagram', 'TikTok', 'Shopee', 'Amazon'] as $nome) {
            $this->assertStringContainsString($nome, $html);
        }
        foreach (['.env', 'META_APP', 'TIKTOK_CLIENT', 'GOOGLE_', 'chave'] as $tecnico) {
            $this->assertStringNotContainsString($tecnico, substr($html, strpos($html, 'Em breve no Work Flower'), 6000));
        }
    }

    public function test_drive_gmail_e_lembretes_sairam_do_em_breve(): void
    {
        $this->semNada();
        $chaves = array_column(EmBreve::itens(), 'chave');
        foreach (['drive', 'gmail', 'lembretes', 'youtube'] as $pronto) {
            $this->assertNotContains($pronto, $chaves);
        }
    }

    public function test_item_some_quando_fica_pronto_e_secao_some_sem_itens(): void
    {
        $this->semNada();
        $this->assertContains('instagram', array_column(EmBreve::itens(), 'chave'));
        config(['services.meta.id' => 'x', 'services.meta.secret' => 'y']);
        $this->assertNotContains('instagram', array_column(EmBreve::itens(), 'chave'));
    }

    public function test_drive_liberado_por_padrao_e_so_admin_quando_desligado(): void
    {
        $this->semNada();
        $comum = User::factory()->create();
        $admin = User::factory()->create(['email' => User::ADMINS[0]]);

        // GOOGLE_APP_PUBLICADO=false (app do Google ainda em teste): só a administração
        $this->assertFalse(EmBreve::driveLiberado($comum));
        $this->assertTrue(EmBreve::driveLiberado($admin));
        $this->actingAs($comum)->get('/armazenamento/google/conectar')->assertRedirect(route('armazenamento'));

        config(['services.google.publico' => true]);
        $this->assertTrue(EmBreve::driveLiberado($comum));
    }
}
