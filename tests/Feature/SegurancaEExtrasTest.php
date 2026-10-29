<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PdfSimples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PDF dos motivos (botões da apresentação), cartão do estúdio de música e cabeçalhos de segurança. */
class SegurancaEExtrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_dos_motivos_baixa_sem_login(): void
    {
        $r = $this->get('/conheca/motivos.pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="por-que-usar-o-work-flower.pdf"');

        $pdf = $r->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith('%%EOF', $pdf);
        $this->assertStringContainsString('/Count 2', $pdf);
        // tabela xref aponta para o início de cada objeto
        preg_match('/startxref\n(\d+)/', $pdf, $m);
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));
        preg_match_all('/(\d{10}) 00000 n /', $pdf, $offs);
        foreach ($offs[1] as $i => $o) {
            $this->assertStringStartsWith(($i + 1) . ' 0 obj', substr($pdf, (int) $o, 12));
        }
        // acentos saem em Windows-1252 dentro do texto da página
        $paginas = '';
        preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $s);
        foreach ($s[1] as $bin) $paginas .= gzuncompress($bin);
        $this->assertStringContainsString(mb_convert_encoding('(Um lugar só para a vida inteira) Tj', 'Windows-1252', 'UTF-8'), $paginas);
    }

    public function test_botoes_do_video_apontam_para_o_pdf(): void
    {
        $html = $this->get('/conheca')->assertOk()->getContent();
        $this->assertGreaterThanOrEqual(5, substr_count($html, route('demo.motivos')));
        $this->assertStringContainsString('Exportar PDF</a>', $html);
    }

    public function test_texto_do_pdf_e_escapado(): void
    {
        $pdf = new PdfSimples;
        $pdf->novaPagina();
        $pdf->texto(10, 10, 'a (b) \\ c', 10, '#000000');
        $this->assertStringContainsString('a \\(b\\) \\\\ c', gzuncompress(preg_replace('/.*stream\n(.*?)\nendstream.*/s', '$1', $pdf->gerar())));
    }

    public function test_cartao_do_estudio_de_musica_vai_para_as_areas(): void
    {
        $u = User::factory()->create();
        foreach (['diverso', 'mente', 'corpo', 'carteira'] as $area) {
            $this->actingAs($u)->get('/areas/' . $area)->assertOk()
                ->assertSee("b.tipo === 'musica'", false)
                ->assertSee('Abrir estúdio')
                ->assertSee('wf-estudio-musica-v', false);
        }
    }

    public function test_cabecalhos_de_seguranca(): void
    {
        $this->get('/login')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'")
            ->assertHeaderMissing('Strict-Transport-Security');

        // fora do HTML (PDF) não vai CSP, para não atrapalhar o leitor de PDF do navegador
        $this->get('/conheca/motivos.pdf')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_em_https_o_cookie_de_sessao_e_secure_e_vai_hsts(): void
    {
        $r = $this->get('https://localhost/login')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=15552000');
        $this->assertStringContainsString('upgrade-insecure-requests', $r->headers->get('Content-Security-Policy'));

        $cookie = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }
}
