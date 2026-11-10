<?php

namespace Tests\Feature;

use App\Models\CorpoDado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cartão de Hábitos: os dias do mês voltaram a ser clicáveis.
 *
 * Causa do bug: no Alpine 3.14 um `:disabled="..."` cujo resultado é `undefined` e cuja expressão tem um ponto
 * vira `""` (e `""` liga o atributo). `d.futuro || (h.semFds && d.fds)` dava `undefined` para todo hábito sem
 * "Pular fins de semana" (h.semFds nunca gravado), então todos os dias ficavam desabilitados e nada era marcado.
 */
class HabitosCartaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dias_do_habito_usam_um_booleano_no_disabled(): void
    {
        $blade = file_get_contents(resource_path('views/partials/habitos-cartao.blade.php'));
        $this->assertStringContainsString('class="hb-dia" :disabled="!!(', $blade);
    }

    /** Qualquer `:disabled` com && / || precisa dar booleano (!!, negação ou comparação em cada parte). */
    public function test_nenhum_disabled_das_telas_pode_dar_undefined(): void
    {
        $ruins = [];
        $arquivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($arquivos as $arq) {
            if (! str_ends_with($arq->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('~(?<![\w-]):disabled="([^"]*(?:&&|\|\|)[^"]*)"~', file_get_contents($arq->getPathname()), $m);
            foreach ($m[1] as $exp) {
                $e = trim(html_entity_decode($exp));
                if (str_starts_with($e, '!')) {
                    continue;
                }
                $partes = preg_split('~&&|\|\|~', $e);
                // só com ||, o resultado é o último operando (os anteriores, se verdadeiros, já dão true)
                if (! str_contains($e, '&&')) {
                    $partes = [end($partes)];
                }
                foreach ($partes as $parte) {
                    $p = trim($parte, " ()");
                    if (! str_starts_with($p, '!') && ! preg_match('~===|!==|==|!=|[<>]|^\w+$~', $p) && str_contains($p, '.')) {
                        $ruins[] = basename($arq->getPathname()) . ': ' . $e;
                        break;
                    }
                }
            }
        }
        $this->assertSame([], $ruins);
    }

    public function test_marcar_um_dia_grava_o_estado_do_corpo(): void
    {
        $u = User::factory()->create();
        $estado = ['blocos' => [[
            'id' => 'bh1', 'secao' => 'livre', 'tipo' => 'habitos', 'titulo' => 'Hábitos', 'links' => [],
            'dados' => [
                'habitos' => [['id' => 'h1', 'nome' => "Beber água (2L) & ler 10 pág.", 'cor' => '#2f6fc7', 'meta' => 7]],
                'feitos' => ['h1' => ['2026-11-02' => 1, '2026-11-03' => 1]], 'mes' => '2026-11',
            ],
        ]], 'layout' => ['livre' => ['bh1' => ['x' => 0, 'y' => 0, 'w' => 720, 'h' => 340, 'z' => 1]]]];

        $this->actingAs($u)->putJson('/corpo/dados/estado', ['valor' => json_encode($estado)])->assertOk();

        $salvo = json_decode(CorpoDado::where('user_id', $u->id)->where('chave', 'estado')->value('valor'), true);
        $this->assertSame(['2026-11-02' => 1, '2026-11-03' => 1], $salvo['blocos'][0]['dados']['feitos']['h1']);

        $this->actingAs($u)->get('/areas/corpo')->assertOk()->assertSee('class="hb-dia" :disabled="!!(', false);
    }
}
