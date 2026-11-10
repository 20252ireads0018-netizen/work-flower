<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SimuladorInvest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Carteira › Investimentos: projeção automática de cada ativo e simulação ao vivo (a conta roda no navegador). */
class InvestimentosProjecaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_da_carteira_traz_a_projecao_automatica(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/areas/carteira')->assertOk()
            ->assertSee('previaNovo()', false)          // prévia ao vivo no "Novo ativo"
            ->assertSee('projAtivo(a, h)', false)       // tabela 1/5/10/20 anos em "Meus ativos"
            ->assertSee('projCarteira(projAnos)', false) // total da carteira no "Resultado"
            ->assertSee('simularAtivo(a)', false)
            ->assertSee('Número mágico');
    }

    /** A conta do navegador (projetar, em carteira.js) é a mesma do servidor: confere os números de referência. */
    public function test_juros_compostos_de_referencia(): void
    {
        // R$ 10.000 a 12% ao ano de DY reinvestido, sem valorização nem aportes, por 12 meses: 1% ao mês composto
        $r = SimuladorInvest::rodar(['inicial' => 10000, 'mensal' => 0, 'meses' => 12, 'reinvestir' => true, 'alvo' => 0], ['nome' => 'X', 'dy' => 12, 'valorizacao' => 0]);
        $this->assertEqualsWithDelta(10000 * pow(1.01, 12), $r['patrimonioFinal'], 0.01);

        // sem reinvestir, o patrimônio fica parado e os dividendos somam 12 × R$ 100
        $r = SimuladorInvest::rodar(['inicial' => 10000, 'mensal' => 0, 'meses' => 12, 'reinvestir' => false, 'alvo' => 0], ['nome' => 'X', 'dy' => 12, 'valorizacao' => 0]);
        $this->assertEqualsWithDelta(10000, $r['patrimonioFinal'], 0.01);
        $this->assertEqualsWithDelta(1200, $r['dividendosRecebidos'], 0.01);
    }

    public function test_conta_do_navegador_tem_os_mesmos_passos_do_servidor(): void
    {
        $js = file_get_contents(public_path('js/carteira.js'));
        $this->assertStringContainsString('function projetar(p)', $js);
        $this->assertStringContainsString('pat *= 1 + g;', $js);
        $this->assertStringContainsString('if (p.reinvestir) pat += renda;', $js);
        $this->assertStringContainsString('Math.pow(1 + Math.max(-90, Number(p.val) || 0) / 100, 1 / 12) - 1', $js);
    }
}
