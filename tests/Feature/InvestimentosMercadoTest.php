<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SimuladorInvest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Carteira › Investimentos: dados da brapi com cache, screener (FIIs, ações…), simulador e funcionamento sem token. */
class InvestimentosMercadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Carbon::setTestNow('2026-11-09 12:00:00');
    }

    private function lista(string $type): array
    {
        $fundos = [
            ['stock' => 'MXRF11', 'name' => 'MAXI RENDA FUNDO DE INVESTIMENTO IMOBILIÁRIO - FII - RESPONSABILIDADE LIMITADA', 'close' => 10.0, 'change' => 0.5, 'volume' => 3000000, 'logo' => 'https://icons.brapi.dev/icons/MXRF11.svg', 'sector' => 'Miscellaneous', 'subsector' => 'Papel', 'type' => 'fund', 'subType' => 'fii'],
            ['stock' => 'HGLG11', 'name' => 'CSHG LOGÍSTICA FII', 'close' => 160.0, 'change' => -1.0, 'volume' => 50000, 'logo' => null, 'sector' => 'Miscellaneous', 'subsector' => 'Logística', 'type' => 'fund', 'subType' => 'fii'],
            ['stock' => 'POUC11', 'name' => 'FII POUCO LIQUIDO', 'close' => 50.0, 'change' => 0, 'volume' => 10, 'logo' => null, 'sector' => null, 'subsector' => 'Logística', 'type' => 'fund', 'subType' => 'fii'],
            ['stock' => 'BOVA11', 'name' => 'ISHARES IBOVESPA', 'close' => 160.0, 'change' => 1, 'volume' => 4000000, 'logo' => null, 'sector' => 'Miscellaneous', 'subsector' => null, 'type' => 'fund', 'subType' => 'etf'],
        ];
        $acoes = [
            ['stock' => 'PETR4', 'name' => 'PETROBRAS PN', 'close' => 40.0, 'change' => -1.4, 'volume' => 40000000, 'logo' => null, 'sector' => 'Energy Minerals', 'subsector' => 'Petróleo', 'type' => 'stock', 'subType' => null],
        ];

        return ['stocks' => match ($type) { 'fund' => $fundos, 'stock' => $acoes, default => [] }];
    }

    /** Detalhe no formato da brapi: dividendos mensais de R$ 0,10 (MXRF11) com VPA 9,50. */
    private function detalhe(string $t): array
    {
        $divs = [];
        if ($t === 'MXRF11') {
            for ($i = 0; $i < 14; $i++) {
                $com = now()->subMonths($i)->startOfMonth()->addDays(14);
                $divs[] = ['rate' => 0.10, 'label' => 'RENDIMENTO', 'lastDatePrior' => $com->format('Y-m-d') . 'T03:00:00.000Z', 'paymentDate' => $com->copy()->addDays(10)->format('Y-m-d') . 'T03:00:00.000Z'];
            }
        }
        if ($t === 'HGLG11') {
            $divs[] = ['rate' => 1.10, 'label' => 'RENDIMENTO', 'lastDatePrior' => '2026-10-31T03:00:00.000Z', 'paymentDate' => '2026-11-14T03:00:00.000Z'];
        }

        return ['results' => [[
            'symbol' => $t, 'longName' => "$t Longo", 'regularMarketPrice' => $t === 'HGLG11' ? 160.0 : 10.0,
            'regularMarketChangePercent' => 0.5, 'regularMarketVolume' => 1000, 'logourl' => 'https://icons.brapi.dev/icons/x.svg',
            'defaultKeyStatistics' => ['bookValue' => $t === 'HGLG11' ? 160.0 : 9.5, 'priceToBook' => null, 'dividendYield' => null],
            'summaryProfile' => ['sectorDisp' => 'Fundos', 'industryDisp' => 'Papel'],
            'dividendsData' => ['cashDividends' => $divs],
        ]]];
    }

    private function fakeBrapi(): void
    {
        Http::fake(function (HttpRequest $r) {
            $url = parse_url($r->url());
            parse_str($url['query'] ?? '', $q);
            if ($url['path'] === '/api/quote/list') {
                return Http::response($this->lista($q['type'] ?? ''));
            }
            $t = basename($url['path']);
            if ($t === 'XXXX11') {
                return Http::response(['error' => true, 'message' => 'Não encontramos a ação'], 404);
            }
            if (empty($q['token']) && ! in_array($t, ['PETR4', 'VALE3', 'ITUB4', 'MGLU3'], true)) {
                return Http::response(['error' => true, 'code' => 'MISSING_TOKEN'], 401);
            }

            return Http::response($this->detalhe($t));
        });
    }

    private function quoteCalls(): int
    {
        return count(Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/api/quote/') && ! str_contains($r->url(), '/quote/list')));
    }

    public function test_detalhe_do_fii_com_dy_pvp_dividendos_e_cache(): void
    {
        config(['services.brapi.token' => 'tok']);
        $this->fakeBrapi();
        $u = User::factory()->create();

        $j = $this->actingAs($u)->getJson('/carteira/mercado/ativos?tickers=mxrf11,XXXX11,../x')->assertOk()->json();

        $this->assertTrue($j['token']);
        $m = $j['ativos']['MXRF11'];
        $this->assertSame('fii', $m['tipo']);
        $this->assertSame('Papel', $m['setor']);              // segmento da lista
        $this->assertSame('MAXI RENDA FUNDO DE INVESTIMENTO IMOBILIÁRIO - FII', $m['nome']);
        // 12 dividendos de R$ 0,10 nos últimos 12 meses a R$ 10,00 → DY 12%
        $this->assertEqualsWithDelta(1.2, $m['proventos12m'], 0.0001);
        $this->assertEqualsWithDelta(12.0, $m['dy'], 0.01);
        $this->assertEqualsWithDelta(1.05, $m['pvp'], 0.001);  // 10 / 9,5
        $this->assertEqualsWithDelta(0.1, $m['rendaMensalCota'], 0.0001);
        $this->assertSame('Rendimento', $m['ultimoDividendo']['tipo']);
        $this->assertSame('2026-11-15', $m['ultimoDividendo']['com']); // 03:00Z = meia-noite em Brasília
        $this->assertTrue($m['completo']);
        $this->assertNull($j['ativos']['XXXX11']);              // inexistente: null, sem quebrar
        $this->assertArrayNotHasKey('../x', $j['ativos']);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/quote/MXRF11') && str_contains($r->url(), 'dividends=true') && str_contains($r->url(), 'token=tok'));

        // segunda vez sai do cache, inclusive a falha
        $antes = $this->quoteCalls();
        $this->actingAs($u)->getJson('/carteira/mercado/ativos?tickers=MXRF11,XXXX11')->assertOk();
        $this->assertSame($antes, $this->quoteCalls());
    }

    public function test_sem_token_usa_a_lista_aberta_e_nao_chama_o_detalhe(): void
    {
        config(['services.brapi.token' => null]);
        $this->fakeBrapi();
        $u = User::factory()->create();

        $j = $this->actingAs($u)->getJson('/carteira/mercado/ativos?tickers=MXRF11,PETR4')->assertOk()->json();

        $this->assertFalse($j['token']);
        $this->assertSame(10.0, (float) $j['ativos']['MXRF11']['preco']);
        $this->assertNull($j['ativos']['MXRF11']['dy']);
        $this->assertFalse($j['ativos']['MXRF11']['completo']);
        $this->assertTrue($j['ativos']['PETR4']['completo']);   // ticker de teste da brapi funciona sem token
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '/quote/MXRF11'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'token='));

        // a cotação antiga também cai para a lista
        $this->actingAs($u)->getJson('/carteira/cotacao?tickers=HGLG11')->assertOk()->assertJson(['HGLG11' => 160.0]);
    }

    public function test_screener_filtra_por_tipo_setor_liquidez_dy_e_pvp(): void
    {
        config(['services.brapi.token' => 'tok']);
        $this->fakeBrapi();
        $u = User::factory()->create();

        // só FIIs (sem o ETF), por liquidez
        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii')->assertOk()->json();
        $this->assertSame(['MXRF11', 'HGLG11', 'POUC11'], array_column($j['itens'], 'ticker'));
        $this->assertContains('Logística', $j['setores']);
        $this->assertSame(0, $j['pendentes']);

        // setor + liquidez mínima
        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii&setor=Log%C3%ADstica&liq_min=100000')->json();
        $this->assertSame(['HGLG11'], array_column($j['itens'], 'ticker'));

        // DY mínimo de 10%: MXRF11 (12%) passa, HGLG11 (1,10/160 ≈ 0,69%) não
        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii&dy_min=10')->json();
        $this->assertSame(['MXRF11'], array_column($j['itens'], 'ticker'));

        // P/VP até 1,0 e ordem por menor P/VP: só HGLG11 (1,00)
        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii&pvp_max=1&ordem=pvp')->json();
        $this->assertSame(['HGLG11'], array_column($j['itens'], 'ticker'));

        // ordem por DY
        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii&ordem=dy')->json();
        $this->assertSame('MXRF11', $j['itens'][0]['ticker']);

        // ETF e ações
        $this->assertSame(['BOVA11'], array_column($this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=etf')->json('itens'), 'ticker'));
        $acao = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=acao')->json('itens.0');
        $this->assertSame('PETR4', $acao['ticker']);

        // a lista de cada tipo foi buscada uma vez só (cache)
        $this->assertCount(1, Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), '/quote/list') && str_contains($r->url(), 'type=fund')));

        $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=cripto')->assertStatus(422);
    }

    public function test_screener_sem_token_deixa_fora_quem_nao_tem_indicador(): void
    {
        config(['services.brapi.token' => null]);
        $this->fakeBrapi();
        $u = User::factory()->create();

        $j = $this->actingAs($u)->getJson('/carteira/mercado/lista?tipo=fii&dy_min=5')->assertOk()->json();
        $this->assertSame([], $j['itens']);
        $this->assertSame(3, $j['sem_dados']);
        $this->assertFalse($j['token']);
        $this->assertSame(0, $this->quoteCalls());
    }

    public function test_simulador_com_aportes_dividendos_e_renda_alvo(): void
    {
        // sem dividendos e sem valorização: só soma os aportes
        $r = SimuladorInvest::rodar(['inicial' => 1000, 'mensal' => 100, 'meses' => 12, 'reinvestir' => true, 'alvo' => 0], ['nome' => 'x', 'dy' => 0, 'valorizacao' => 0]);
        $this->assertSame(2200.0, $r['patrimonioFinal']);
        $this->assertSame(2200.0, $r['aportado']);

        // DY 12% a.a. = 1% ao mês sobre 10.000, reinvestindo: 10.000 × 1,01^12
        $r = SimuladorInvest::rodar(['inicial' => 10000, 'mensal' => 0, 'meses' => 12, 'reinvestir' => true, 'alvo' => 100], ['nome' => 'x', 'dy' => 12, 'valorizacao' => 0]);
        $this->assertEqualsWithDelta(10000 * 1.01 ** 12, $r['patrimonioFinal'], 0.05);
        $this->assertSame(1, $r['mesesAteAlvo']);               // 1% de 10.000 = 100 já no 1º mês
        $this->assertSame(10000.0, $r['patrimonioParaAlvo']);

        // sem reinvestir: o patrimônio fica parado e os dividendos são recebidos
        $r = SimuladorInvest::rodar(['inicial' => 10000, 'mensal' => 0, 'meses' => 12, 'reinvestir' => false, 'alvo' => 0], ['nome' => 'x', 'dy' => 12, 'valorizacao' => 0]);
        $this->assertSame(10000.0, $r['patrimonioFinal']);
        $this->assertEqualsWithDelta(1200, $r['dividendosRecebidos'], 0.01);

        // série do gráfico limitada a 120 pontos e terminando no último mês
        $r = SimuladorInvest::rodar(['inicial' => 0, 'mensal' => 100, 'meses' => 600, 'reinvestir' => true, 'alvo' => 0], ['nome' => 'x', 'dy' => 8, 'valorizacao' => 2]);
        $this->assertLessThanOrEqual(121, count($r['serie']));
        $this->assertSame(600, end($r['serie'])['mes']);
    }

    public function test_endpoint_do_simulador_compara_ativos_e_usa_o_dy_do_mercado(): void
    {
        config(['services.brapi.token' => 'tok']);
        $this->fakeBrapi();
        $u = User::factory()->create();

        $j = $this->actingAs($u)->postJson('/carteira/mercado/simular', [
            'inicial' => 1000, 'mensal' => 500, 'meses' => 120, 'reinvestir' => true, 'alvo' => 1000,
            'ativos' => [
                ['ticker' => 'MXRF11'],                                     // DY do mercado (12%)
                ['ticker' => null, 'nome' => 'Tesouro', 'dy' => 6, 'valorizacao' => 4],
                ['ticker' => 'XXXX11'],                                     // sem dados: vira aviso
            ],
        ])->assertOk()->json();

        $this->assertCount(2, $j['resultados']);
        $this->assertSame('MXRF11', $j['resultados'][0]['nome']);
        $this->assertEqualsWithDelta(12.0, $j['resultados'][0]['dy'], 0.01);
        $this->assertSame('Tesouro', $j['resultados'][1]['nome']);
        $this->assertEquals(61000, $j['resultados'][0]['aportado']);
        $this->assertGreaterThan($j['resultados'][0]['aportado'], $j['resultados'][0]['patrimonioFinal']);
        $this->assertStringContainsString('XXXX11', $j['erros'][0]);

        // nada simulável → 422 com mensagem
        $this->actingAs($u)->postJson('/carteira/mercado/simular', ['meses' => 12, 'ativos' => [['nome' => 'Sem DY']]])
            ->assertStatus(422)->assertJsonFragment(['erro' => 'Informe o DY de "Sem DY".']);
        $this->actingAs($u)->postJson('/carteira/mercado/simular', ['meses' => 12, 'ativos' => array_fill(0, 5, ['dy' => 5])])->assertStatus(422);
    }

    public function test_pagina_da_carteira_mostra_os_novos_cartoes_e_o_aviso(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->get('/areas/carteira')->assertOk()
            ->assertSee('Filtro de ativos: FIIs, ações, ETFs e mais')
            ->assertSee('Simulador: aportes, dividendos e renda-alvo')
            ->assertSee('Não é recomendação de investimento')
            ->assertSee('carteira\/mercado\/lista', false);
    }

    public function test_precisa_estar_logado(): void
    {
        $this->getJson('/carteira/mercado/lista')->assertUnauthorized();
        $this->postJson('/carteira/mercado/simular', [])->assertUnauthorized();
    }
}
