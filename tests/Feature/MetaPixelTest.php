<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Pixel da Meta (partials/meta-pixel): PageView, CompleteRegistration logo depois do cadastro e consentimento dos cookies. */
class MetaPixelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.meta.pixel' => '1115433257784212']);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]); // senha "não vazada"
    }

    public function test_pixel_carrega_com_consentimento_revogado_ate_aceitar_os_cookies(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("fbq('init', \"1115433257784212\")", $html);
        $this->assertStringContainsString("fbq('track', 'PageView')", $html);
        // sem "Aceitar todos" o pixel começa revogado; o aviso de cookies libera com fbq('consent', 'grant')
        $this->assertStringContainsString("fbq('consent', 'revoke')", $html);
        $this->assertStringContainsString("fbq('consent', v === 'tudo' ? 'grant' : 'revoke')", $html);
        $this->assertStringNotContainsString('CompleteRegistration', $html);
    }

    public function test_cadastro_dispara_complete_registration_uma_vez(): void
    {
        $this->post('/registro', [
            'name' => 'Ana Teste', 'email' => 'ana@exemplo.com',
            'password' => 'senhaBoa12345', 'password_confirmation' => 'senhaBoa12345',
        ])->assertRedirect(route('painel'));

        $this->get('/painel')->assertOk()->assertSee("fbq('track', 'CompleteRegistration'", false);
        $this->get('/painel')->assertOk()->assertDontSee('CompleteRegistration', false);
    }

    public function test_sem_id_nao_carrega_o_pixel(): void
    {
        config(['services.meta.pixel' => '']);
        $this->get('/')->assertOk()->assertDontSee('fbevents.js', false);
    }
}
