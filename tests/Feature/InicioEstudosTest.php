<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Abertura de estudos da página inicial: anúncios (utm wf-cadastros / wf-pos-enem) e a rota /estudos. */
class InicioEstudosTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicio_normal_continua_igual(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Seu trabalho e sua vida, organizados em um só lugar')
            ->assertDontSee('data-abertura="estudos"', false)
            ->assertDontSee('conta paga vitalícia');
        $this->get('/?utm_campaign=outra')->assertOk()->assertDontSee('data-abertura="estudos"', false);
    }

    public function test_utm_dos_anuncios_mostra_a_abertura_de_estudos(): void
    {
        foreach (['wf-cadastros', 'wf-pos-enem'] as $campanha) {
            $this->get('/?utm_source=facebook&utm_campaign=' . $campanha)->assertOk()
                ->assertSee('data-abertura="estudos"', false)
                ->assertSee('Automatize seus estudos')
                ->assertSee('Grátis para começar')
                ->assertSee('Criar conta grátis')
                ->assertSee('Os 3 primeiros cadastros ganham a conta paga vitalícia.')
                ->assertSee(route('registro'), false);
        }
    }

    public function test_rota_estudos_e_pixel_intacto(): void
    {
        config(['services.meta.pixel' => '1115433257784212']);
        $this->get('/estudos')->assertOk()
            ->assertSee('<title>Work Flower · Automatize seus estudos</title>', false)
            ->assertSee('data-abertura="estudos"', false)
            ->assertSee("fbq('init', \"1115433257784212\")", false)
            ->assertSee('wf-cookies-aceitar', false);
    }

    public function test_logado_vai_ao_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/estudos')->assertRedirect(route('painel'));
    }
}
