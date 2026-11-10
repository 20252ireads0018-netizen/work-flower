<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cartões de estudo da Mente (public/js/estudo.js): Flashcards, Pomodoro, Plano de estudos e Matemática. */
class EstudoCartoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_mente_carrega_os_cartoes_de_estudo_antes_do_mente_js(): void
    {
        $u = User::factory()->create();
        $html = $this->actingAs($u)->get('/areas/mente')->assertOk()->getContent();

        $estudo = strpos($html, 'js/estudo.js');
        $graficos = strpos($html, 'js/graficos.js');
        $mente = strpos($html, 'js/mente.js');
        $this->assertNotFalse($estudo);
        // usa o wfNovoCartao do graficos.js e precisa embrulhar o WFQuadro.metodos antes do menteAbas() rodar
        $this->assertGreaterThan($graficos, $estudo);
        $this->assertLessThan($mente, $estudo);

        // o conteúdo de cada cartão está no quadro
        foreach (["b.tipo === 'flashcards'", "b.tipo === 'pomodoro'", "b.tipo === 'planoestudo'", "b.tipo === 'matematica'",
            'fcResponder(b, k)', 'pmComecar(b)', 'peProgresso(b)', 'mtLinhas(b)', 'mtSvg(b)'] as $trecho) {
            $this->assertStringContainsString($trecho, $html, "faltou: $trecho");
        }
    }

    public function test_outras_areas_nao_recebem_os_cartoes_de_estudo(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/areas/diverso')->assertOk()->assertDontSee('js/estudo.js', false);
    }

    public function test_script_registra_os_tipos_e_usa_bibliotecas_com_versao_fixa(): void
    {
        $js = file_get_contents(public_path('js/estudo.js'));
        foreach (['flashcards', 'pomodoro', 'planoestudo', 'matematica'] as $tipo) {
            $this->assertStringContainsString("id: '$tipo'", $js);
        }
        // math.js e nerdamer vêm de CDN com versão exata
        $this->assertMatchesRegularExpression('#https://cdnjs\.cloudflare\.com/ajax/libs/mathjs/\d+\.\d+\.\d+/math\.min\.js#', $js);
        $this->assertMatchesRegularExpression('#https://cdn\.jsdelivr\.net/npm/nerdamer@\d+\.\d+\.\d+/all\.min\.js#', $js);
        $this->assertMatchesRegularExpression('#https://cdnjs\.cloudflare\.com/ajax/libs/KaTeX/\d+\.\d+\.\d+/katex\.min\.js#', $js);
    }

    public function test_flashcards_e_matematica_tem_as_telas_novas(): void
    {
        $u = User::factory()->create();
        $html = $this->actingAs($u)->get('/areas/mente')->assertOk()->getContent();
        foreach (['fcBaralhos(b)', 'fcLivre(b, \'dificeis\')', 'fcDesfazer(b)', 'fcBaixarAnki(b)', 'fcBaixarCsv(b)', 'fcImportarArquivo(b,',
            'fcPrevisaoSvg(b)', 'fcHtml(fcAtual(b).frente)', 'mtTeclas()', 'mtGruposExemplos()', 'mtPlotAdd(b)', 'mtPrimeiroErro(b)', 'fcDeQuestoes(b)'] as $trecho) {
            $this->assertStringContainsString($trecho, $html, "faltou: $trecho");
        }
        // a carta é mostrada com x-html: o texto tem de passar pelo cartaHtml (escapa tudo, só imagem e fórmula viram HTML)
        $js = file_get_contents(public_path('js/estudo.js'));
        $this->assertStringContainsString('return cartaHtml(t,', $js);
        $this->assertStringContainsString("s = esc(s);", $js);
    }

    public function test_classes_utilitarias_dos_cartoes_existem_no_tailwind_gerado(): void
    {
        // o tailwind.css é gerado antes (sem build no servidor): classe nova que não está lá não tem efeito nenhum
        $css = file_get_contents(public_path('css/tailwind.css'));
        $usadas = [];
        foreach (['flashcards', 'pomodoro', 'plano-estudo', 'matematica'] as $p) {
            $blade = file_get_contents(resource_path("views/partials/$p-cartao.blade.php"));
            preg_match_all('/\sclass="([^"]+)"/', $blade, $m);
            foreach ($m[1] as $lista) {
                foreach (preg_split('/\s+/', trim($lista)) as $c) {
                    // só utilitários do Tailwind (as classes próprias têm prefixo: es-, fc-, pm-, mt-, qz-, cx- e as do layout)
                    if ($c === '' || preg_match('/^(es|fc|pm|mt|qz|cx)-|^(campo|btn|btn-sec|mini-btn|texto-2|link-prim|perigo)$/', $c)) {
                        continue;
                    }
                    $usadas[$c] = true;
                }
            }
        }
        $this->assertNotEmpty($usadas);
        foreach (array_keys($usadas) as $c) {
            $seletor = '.' . preg_replace('/([!:.\/\[\]])/', '\\\\$1', $c);
            $this->assertStringContainsString($seletor, $css, "classe sem CSS: $c");
        }
    }
}
