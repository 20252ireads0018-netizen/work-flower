<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Google AdSense: só visitantes e plano Gratuito, nunca em login, planos, pagamento ou admin (App\Support\Publicidade). */
class PublicidadeTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = 'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4781960143966285';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.adsense.client' => 'ca-pub-4781960143966285']);
    }

    /** Conta fora do teste grátis (criada há 60 dias), no plano pedido. */
    private function conta(string $plano): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]);
        DB::table('users')->where('id', $u->id)->update(['plano_slug' => $plano, 'plano_expira_em' => $plano === 'gratuito' ? null : now()->addMonth()]);

        return $u->fresh();
    }

    public function test_visitante_ve_anuncios_nas_paginas_publicas_mas_nao_no_login(): void
    {
        $this->get('/')->assertOk()->assertSee(self::SCRIPT, false);
        $this->get('/privacidade')->assertOk()->assertSee(self::SCRIPT, false);

        $login = $this->get('/login')->assertOk();
        $login->assertDontSee(self::SCRIPT, false);
        $login->assertSee('name="google-adsense-account"', false); // verificação do site continua
        $this->get('/registro')->assertOk()->assertDontSee(self::SCRIPT, false);
    }

    public function test_plano_gratuito_ve_anuncios_e_planos_pagos_nao(): void
    {
        $this->actingAs($this->conta('gratuito'))->get('/painel')->assertOk()->assertSee(self::SCRIPT, false);
        foreach (['basico', 'pro', 'premium'] as $plano) {
            $this->actingAs($this->conta($plano))->get('/painel')->assertOk()->assertDontSee(self::SCRIPT, false);
        }
    }

    public function test_teste_gratis_do_basico_nao_tem_anuncios(): void
    {
        $novo = User::factory()->create(); // conta nova: 30 dias de Básico
        $this->actingAs($novo)->get('/painel')->assertOk()->assertDontSee(self::SCRIPT, false);
    }

    public function test_planos_e_telas_sem_anuncio_mesmo_no_gratuito(): void
    {
        $u = $this->conta('gratuito');
        $this->actingAs($u)->get('/planos')->assertOk()->assertDontSee(self::SCRIPT, false);
        $this->assertFalse(\App\Support\Publicidade::permitida(\Illuminate\Http\Request::create('/pagamento/pix/1'), $u));
        $this->assertFalse(\App\Support\Publicidade::permitida(\Illuminate\Http\Request::create('/admin/pix'), $u));
        $this->assertFalse(\App\Support\Publicidade::permitida(\Illuminate\Http\Request::create('/assinar/basico'), $u));
        $this->assertTrue(\App\Support\Publicidade::permitida(\Illuminate\Http\Request::create('/areas/corpo'), $u));
    }

    public function test_sem_id_configurado_nada_de_anuncio(): void
    {
        config(['services.adsense.client' => '']);
        $this->get('/')->assertOk()->assertDontSee('adsbygoogle.js', false)->assertDontSee('google-adsense-account', false);
    }
}
