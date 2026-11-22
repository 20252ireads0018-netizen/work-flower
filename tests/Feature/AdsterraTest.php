<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Publicidade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Adsterra (App\Support\Publicidade): só Banner e Native, só visitantes nas páginas públicas, só depois do aceite dos cookies. */
class AdsterraTest extends TestCase
{
    use RefreshDatabase;

    private const CHAVE = '0123456789abcdef0123456789abcdef';
    private const NATIVE = 'fedcba9876543210fedcba9876543210';

    private function ligar(array $extra = []): void
    {
        config(['services.anuncios_rede' => 'ambos', 'services.adsterra' => $extra + [
            'banner' => self::CHAVE, 'banner_tamanho' => '728x90', 'banner_movel' => '', 'banner_movel_tamanho' => '300x250',
            'banner_host' => 'www.highperformanceformat.com',
            'native' => '//pl123.exemplo-ads.com/' . self::NATIVE . '/invoke.js', 'native_host' => '',
        ]]);
    }

    public function test_sem_chave_nada_aparece(): void
    {
        $this->get('/')->assertOk()->assertDontSee('data-anuncio', false)->assertDontSee('invoke.js', false)->assertDontSee('atOptions', false);
    }

    public function test_visitante_ve_banner_no_rodape_e_native_na_inicial_so_apos_aceite(): void
    {
        $this->ligar();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-anuncio="banner"', $html);
        $this->assertStringContainsString('data-anuncio="native"', $html);
        $this->assertStringContainsString(self::CHAVE, $html);
        $this->assertStringContainsString('pl123.exemplo-ads.com', $html); // host tirado do endereço do invoke.js
        $this->assertStringContainsString('wf_cookies=tudo', $html); // só carrega com "Aceitar todos"
        $this->assertStringContainsString('window.wfCarregarAdsterra()', $html);
        $this->assertStringNotContainsStringIgnoringCase('popunder', $html);
        $this->assertStringNotContainsStringIgnoringCase('socialbar', $html);

        $this->get('/privacidade')->assertOk()->assertSee('data-anuncio="banner"', false)->assertSee('Adsterra');
    }

    public function test_nunca_no_login_nem_para_quem_esta_logado(): void
    {
        $this->ligar();
        $this->get('/login')->assertOk()->assertDontSee('data-anuncio', false);
        $this->get('/registro')->assertOk()->assertDontSee('data-anuncio', false);

        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // plano Gratuito, dentro das ferramentas
        $this->actingAs($u)->get('/painel')->assertOk()->assertDontSee('data-anuncio', false);
        $this->assertFalse(Publicidade::adsterra(\Illuminate\Http\Request::create('/'), $u));
    }

    public function test_rede_escolhida_e_chave_invalida(): void
    {
        $this->ligar();
        config(['services.adsense.client' => 'ca-pub-4781960143966285']);

        config(['services.anuncios_rede' => 'adsense']);
        $this->get('/')->assertOk()->assertDontSee('data-anuncio', false)->assertSee('adsbygoogle.js', false);

        config(['services.anuncios_rede' => 'adsterra']);
        $this->get('/')->assertOk()->assertSee('data-anuncio', false)->assertDontSee('adsbygoogle.js', false);

        $this->ligar(['banner' => 'nao-e-chave"><script>', 'native' => '']);
        $this->get('/')->assertOk()->assertDontSee('data-anuncio', false)->assertDontSee('nao-e-chave', false);
    }
}
