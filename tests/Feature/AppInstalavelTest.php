<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Work Flower como app instalável (PWA): manifesto, service worker, página offline e tags do layout. */
class AppInstalavelTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifesto_tem_o_necessario_para_instalar(): void
    {
        $r = $this->get('/manifest.webmanifest')->assertOk();
        $this->assertStringStartsWith('application/manifest+json', $r->headers->get('Content-Type'));

        $m = json_decode($r->getContent(), true);
        $this->assertSame('Work Flower', $m['name']);
        $this->assertSame('standalone', $m['display']);
        $this->assertSame(url('/') . '/', $m['scope']);
        $this->assertStringStartsWith($m['scope'], $m['start_url']);

        $tamanhos = collect($m['icons'])->where('purpose', 'any')->pluck('sizes')->all();
        $this->assertContains('192x192', $tamanhos);
        $this->assertContains('512x512', $tamanhos);
        $this->assertTrue(collect($m['icons'])->contains('purpose', 'maskable'));

        foreach ($m['icons'] as $i) {
            $arquivo = public_path(ltrim(parse_url($i['src'], PHP_URL_PATH), '/'));
            $this->assertFileExists($arquivo);
            [$l, $a] = getimagesize($arquivo);
            $this->assertSame($i['sizes'], "{$l}x{$a}");
        }
    }

    public function test_numa_subpasta_tudo_usa_a_base_do_site(): void
    {
        // como em produção: o index.php fica em /work-flower e o Apache passa o caminho inteiro
        $naSubpasta = fn (string $caminho) => $this->call('GET', 'https://websight.com.br/work-flower' . $caminho, [], [], [], [
            'SCRIPT_NAME'     => '/work-flower/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF'        => '/work-flower/index.php',
        ]);

        $m = json_decode($naSubpasta('/manifest.webmanifest')->assertOk()->getContent(), true);
        $this->assertSame('https://websight.com.br/work-flower/', $m['scope']);
        $this->assertSame('https://websight.com.br/work-flower/', $m['id']);
        $this->assertSame('https://websight.com.br/work-flower/painel', $m['start_url']);
        $this->assertStringStartsWith('https://websight.com.br/work-flower/icons/', $m['icons'][0]['src']);

        $sw = $naSubpasta('/sw.js')->assertOk();
        $this->assertSame('/work-flower/', $sw->headers->get('Service-Worker-Allowed'));
        $sw->assertSee('const BASE = "https://websight.com.br/work-flower/"', false);
        $sw->assertSee('const OFFLINE = "https://websight.com.br/work-flower/offline"', false);
    }

    public function test_service_worker_e_javascript_sem_cache_e_sem_sessao(): void
    {
        $r = $this->get('/sw.js')->assertOk();
        $this->assertStringStartsWith('application/javascript', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $r->headers->get('Cache-Control'));
        $this->assertEmpty($r->headers->getCookies(), 'o sw.js não deve abrir sessão');
        $r->assertSee("addEventListener('fetch'", false);
        $r->assertDontSee('@verbatim', false);

        // versão estável entre pedidos (senão o navegador reinstalaria a cada visita)
        $this->assertSame($r->getContent(), $this->get('/sw.js')->getContent());
    }

    public function test_pagina_offline_e_independente(): void
    {
        $r = $this->get('/offline')->assertOk()->assertSee('Sem conexão');
        // nada de recursos externos: ela é aberta sem internet
        $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://(?!' . preg_quote(parse_url(url('/'), PHP_URL_HOST), '#') . ')#', $r->getContent());
    }

    public function test_layout_liga_manifesto_ios_e_botao_de_instalar(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<link rel="manifest" href="' . route('app.manifesto') . '">', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('data-sw="' . route('app.sw') . '"', false);

        $u = User::factory()->create();
        $this->actingAs($u)->get('/painel')->assertOk()->assertSee('Baixar o app')->assertSee('data-wf-instalar', false);
    }

    public function test_botao_baixar_o_app_nas_paginas_de_entrada(): void
    {
        foreach (['/', '/login', '/registro', '/conheca'] as $pagina) {
            $this->get($pagina)->assertOk()
                ->assertSee('data-wf-instalar', false)
                ->assertSee('Baixar o app')
                ->assertSee('data-windows="' . route('app.windows') . '"', false);
        }

        // dentro do workflow: no cabeçalho e no menu de configurações de qualquer área
        $u = User::factory()->create();
        $html = $this->actingAs($u)->get('/flower')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data-wf-instalar'));
    }

    public function test_instalador_windows_cria_atalhos_que_abrem_o_painel(): void
    {
        $r = $this->get('/app/instalar-windows')->assertOk();
        $this->assertStringContainsString('attachment; filename="Instalar Work Flower.cmd"', $r->headers->get('Content-Disposition'));
        $this->assertEmpty($r->headers->getCookies(), 'o instalador não deve abrir sessão');

        $cmd = $r->getContent();
        $this->assertStringStartsWith('@echo off', $cmd);
        $this->assertStringContainsString("\r\n", $cmd, 'o .cmd precisa de quebras de linha do Windows');
        $this->assertMatchesRegularExpression('/-EncodedCommand ([A-Za-z0-9+\/=]+)/', $cmd);
        preg_match('/-EncodedCommand ([A-Za-z0-9+\/=]+)/', $cmd, $m);
        $this->assertLessThan(8000, strlen($m[0]), 'linha do cmd.exe tem limite de 8191 caracteres');

        $ps = mb_convert_encoding(base64_decode($m[1]), 'UTF-8', 'UTF-16LE');
        $this->assertStringContainsString("--app=\$url", $ps);
        $this->assertStringContainsString("\$url = '" . route('painel') . "'", $ps);
        $this->assertStringContainsString(asset('icons/work-flower.ico'), $ps);
        $this->assertStringContainsString('Desinstalar Work Flower.cmd', $ps);
        $this->assertFileExists(public_path('icons/work-flower.ico'));
    }

    public function test_instalador_windows_na_subpasta_abre_o_painel_da_subpasta(): void
    {
        $r = $this->call('GET', 'https://websight.com.br/work-flower/app/instalar-windows', [], [], [], [
            'SCRIPT_NAME'     => '/work-flower/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
            'PHP_SELF'        => '/work-flower/index.php',
        ])->assertOk();
        preg_match('/-EncodedCommand ([A-Za-z0-9+\/=]+)/', $r->getContent(), $m);
        $ps = mb_convert_encoding(base64_decode($m[1]), 'UTF-8', 'UTF-16LE');
        $this->assertStringContainsString("'https://websight.com.br/work-flower/painel'", $ps);
    }
}
