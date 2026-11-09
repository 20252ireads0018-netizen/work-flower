<?php

namespace App\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Dados de mercado da B3 (ações, FIIs, Fiagro, FI-Infra, ETFs e BDRs) pela brapi.dev, sempre com cache no servidor.
 *
 * - A lista de ativos (/api/quote/list: preço, variação, volume, setor e segmento) é aberta e não precisa de token.
 * - O detalhe (/api/quote/{ticker}: dividendos, VPA, P/VP, setor) precisa de BRAPI_TOKEN, exceto os tickers de teste
 *   da brapi (GRATIS). Sem token o detalhe cai para os dados da lista (sem DY e sem P/VP), sem chamada externa.
 * - DY e P/VP são recalculados com o preço mais novo (o da lista, renovado a cada hora); o detalhe fica 12 h guardado.
 */
class Mercado
{
    public const BASE = 'https://brapi.dev/api';

    /** Tickers que a brapi responde sem token. */
    public const GRATIS = ['PETR4', 'VALE3', 'ITUB4', 'MGLU3'];

    /** Tipos mostrados no app → [type, subType] da brapi. */
    public const TIPOS = [
        'fii'     => ['nome' => 'FII', 'type' => 'fund', 'sub' => 'fii'],
        'fiagro'  => ['nome' => 'Fiagro', 'type' => 'fund', 'sub' => 'fi-agro'],
        'fiinfra' => ['nome' => 'FI-Infra', 'type' => 'fund', 'sub' => 'fi-infra'],
        'etf'     => ['nome' => 'ETF', 'type' => 'fund', 'sub' => 'etf'],
        'acao'    => ['nome' => 'Ação', 'type' => 'stock', 'sub' => null],
        'bdr'     => ['nome' => 'BDR', 'type' => 'bdr', 'sub' => null],
    ];

    /** Setores da lista da brapi (em inglês) → pt-BR. */
    private const SETORES = [
        'Retail Trade' => 'Varejo', 'Energy Minerals' => 'Petróleo e minerais energéticos', 'Health Services' => 'Serviços de saúde',
        'Utilities' => 'Utilidade pública', 'Finance' => 'Financeiro', 'Consumer Services' => 'Serviços ao consumidor',
        'Consumer Non-Durables' => 'Bens de consumo não duráveis', 'Non-Energy Minerals' => 'Mineração e siderurgia',
        'Commercial Services' => 'Serviços comerciais', 'Distribution Services' => 'Distribuição', 'Transportation' => 'Transporte',
        'Technology Services' => 'Tecnologia', 'Process Industries' => 'Indústria de processos', 'Communications' => 'Comunicações',
        'Producer Manufacturing' => 'Bens industriais', 'Miscellaneous' => 'Diversos', 'Electronic Technology' => 'Eletrônicos',
        'Industrial Services' => 'Serviços industriais', 'Health Technology' => 'Tecnologia em saúde', 'Consumer Durables' => 'Bens duráveis',
    ];

    private const TTL_LISTA = 3600;        // 1 h
    private const TTL_DETALHE = 43200;     // 12 h
    private const TTL_FALHA = 600;         // 10 min: ticker inexistente não vira uma chamada por pedido

    public static function temToken(): bool
    {
        return filled(config('services.brapi.token'));
    }

    /** O detalhe deste ticker pode ser buscado agora (com token, qualquer um; sem token, só os de teste). */
    public static function detalhavel(string $ticker): bool
    {
        return self::temToken() || in_array($ticker, self::GRATIS, true);
    }

    public static function ticker(?string $t): ?string
    {
        $t = strtoupper(trim((string) $t));

        return preg_match('/^[A-Z0-9]{4,10}$/', $t) ? $t : null;
    }

    /* ======================= lista (screener) ======================= */

    /**
     * Todos os ativos de um tipo da brapi (stock, fund ou bdr), enxutos, com cache de 1 h.
     *
     * @return array<string, array{t:string,n:string,p:float,c:float,v:float,s:?string,ss:?string,tipo:string,logo:?string}>
     */
    public static function universo(string $type): array
    {
        $chave = "mercado.lista.$type";
        $guardado = Cache::get($chave);
        if (is_array($guardado)) {
            return $guardado;
        }

        $lista = [];
        try {
            $r = Http::timeout(12)->acceptJson()->get(self::BASE . '/quote/list', array_filter([
                'type' => $type, 'limit' => 1000, 'sortBy' => 'volume', 'sortOrder' => 'desc',
                'token' => config('services.brapi.token'),
            ]));
            foreach ((array) ($r->ok() ? $r->json('stocks') : []) as $s) {
                $t = self::ticker($s['stock'] ?? null);
                if (! $t || ! is_numeric($s['close'] ?? null)) {
                    continue;
                }
                $lista[$t] = [
                    't' => $t,
                    'n' => self::nomeCurto((string) ($s['name'] ?? $t)),
                    'p' => (float) $s['close'],
                    'c' => (float) ($s['change'] ?? 0),
                    'v' => (float) ($s['volume'] ?? 0),
                    's' => self::SETORES[$s['sector'] ?? ''] ?? ($s['sector'] ?? null),
                    'ss' => $s['subsector'] ?? null,
                    'tipo' => self::tipoDe($type, $s['subType'] ?? null),
                    'logo' => is_string($s['logo'] ?? null) && str_starts_with($s['logo'], 'https://') ? $s['logo'] : null,
                ];
            }
        } catch (\Throwable) {
            // sem rede: lista vazia por pouco tempo
        }

        Cache::put($chave, $lista, $lista ? self::TTL_LISTA : 120);

        return $lista;
    }

    /** Linha da lista de qualquer tipo para um ticker (procura em ações, fundos e BDRs). */
    public static function daLista(string $ticker): ?array
    {
        foreach (['stock', 'fund', 'bdr'] as $type) {
            $u = self::universo($type);
            if (isset($u[$ticker])) {
                return $u[$ticker];
            }
        }

        return null;
    }

    private static function tipoDe(string $type, ?string $sub): string
    {
        foreach (self::TIPOS as $id => $d) {
            if ($d['type'] === $type && ($d['sub'] === null || $d['sub'] === $sub)) {
                return $id;
            }
        }

        return $type === 'fund' ? 'fii' : 'acao';
    }

    private static function nomeCurto(string $n): string
    {
        $n = preg_replace('/\s*-?\s*RESPONSABILIDADE LIMITADA\s*$/iu', '', $n);
        $n = preg_replace('/\s+/', ' ', (string) $n);

        return mb_strimwidth(trim((string) $n), 0, 80, '…');
    }

    /**
     * Screener: filtra a lista por tipo, setor/segmento, liquidez e busca; DY e P/VP vêm do detalhe (guardado).
     * Busca no máximo $novos detalhes por pedido (em paralelo); o resto fica em "pendentes" para o próximo pedido.
     */
    public static function filtrar(array $f, int $novos = 10): array
    {
        $tipo = $f['tipo'] ?? 'fii';
        $types = $tipo === 'todos' ? ['stock', 'fund', 'bdr'] : [self::TIPOS[$tipo]['type'] ?? 'fund'];
        $linhas = [];
        foreach ($types as $type) {
            foreach (self::universo($type) as $l) {
                if ($tipo === 'todos' || $l['tipo'] === $tipo) {
                    $linhas[] = $l;
                }
            }
        }

        $setores = collect($linhas)->flatMap(fn ($l) => [$l['ss'] ?: $l['s']])->filter()->unique()->sort(SORT_LOCALE_STRING)->values()->all();

        $busca = mb_strtolower(trim((string) ($f['busca'] ?? '')));
        $setor = trim((string) ($f['setor'] ?? ''));
        $liqMin = (float) ($f['liq_min'] ?? 0);
        $linhas = array_values(array_filter($linhas, function ($l) use ($busca, $setor, $liqMin) {
            if ($setor !== '' && $setor !== ($l['ss'] ?: $l['s']) && $setor !== $l['s']) {
                return false;
            }
            if ($liqMin > 0 && $l['p'] * $l['v'] < $liqMin) {
                return false;
            }

            return $busca === '' || str_contains(mb_strtolower($l['t'] . ' ' . $l['n']), $busca);
        }));
        usort($linhas, fn ($a, $b) => $b['p'] * $b['v'] <=> $a['p'] * $a['v']);

        $usaIndicador = self::temFiltro($f, 'dy_min') || self::temFiltro($f, 'dy_max') || self::temFiltro($f, 'pvp_min')
            || self::temFiltro($f, 'pvp_max') || in_array($f['ordem'] ?? '', ['dy', 'pvp'], true);
        $limite = max(1, min(100, (int) ($f['limite'] ?? 50)));

        // candidatos ao detalhe: com filtro de DY/P/VP, os 150 mais líquidos; sem filtro, só os que vão aparecer
        $candidatos = array_slice($linhas, 0, $usaIndicador ? 150 : $limite);
        $faltam = array_values(array_filter(array_column($candidatos, 't'), fn ($t) => self::detalhavel($t) && ! Cache::has("mercado.ativo.$t")));
        self::buscarDetalhes(array_slice($faltam, 0, $novos));
        $pendentes = max(0, count($faltam) - $novos);

        $itens = [];
        $semDados = 0;
        foreach ($usaIndicador ? $candidatos : $linhas as $l) {
            $item = self::juntar($l, self::detalheGuardado($l['t']));
            if ($usaIndicador) {
                if ($item['dy'] === null && (self::temFiltro($f, 'dy_min') || self::temFiltro($f, 'dy_max') || ($f['ordem'] ?? '') === 'dy')) {
                    $semDados++;
                    continue;
                }
                if ($item['pvp'] === null && (self::temFiltro($f, 'pvp_min') || self::temFiltro($f, 'pvp_max') || ($f['ordem'] ?? '') === 'pvp')) {
                    $semDados++;
                    continue;
                }
                if ((self::temFiltro($f, 'dy_min') && $item['dy'] < (float) $f['dy_min'])
                    || (self::temFiltro($f, 'dy_max') && $item['dy'] > (float) $f['dy_max'])
                    || (self::temFiltro($f, 'pvp_min') && $item['pvp'] < (float) $f['pvp_min'])
                    || (self::temFiltro($f, 'pvp_max') && $item['pvp'] > (float) $f['pvp_max'])) {
                    continue;
                }
            }
            $itens[] = $item;
        }

        $ordem = $f['ordem'] ?? 'liquidez';
        $campo = ['dy' => 'dy', 'pvp' => 'pvp', 'preco' => 'preco', 'variacao' => 'variacao'][$ordem] ?? 'liquidez';
        $crescente = $ordem === 'pvp';
        usort($itens, fn ($a, $b) => $crescente ? ($a[$campo] <=> $b[$campo]) : ($b[$campo] <=> $a[$campo]));

        return [
            'itens' => array_slice($itens, 0, $limite),
            'total' => count($itens),
            'sem_dados' => $semDados,
            'pendentes' => $pendentes,
            'setores' => $setores,
            'token' => self::temToken(),
        ];
    }

    private static function temFiltro(array $f, string $k): bool
    {
        return isset($f[$k]) && $f[$k] !== '' && is_numeric($f[$k]);
    }

    /* ======================= detalhe por ativo ======================= */

    /**
     * Detalhes de vários tickers (busca em paralelo os que não estão guardados).
     *
     * @return array<string, array|null>
     */
    public static function ativos(array $tickers): array
    {
        $tickers = array_values(array_unique(array_filter(array_map([self::class, 'ticker'], $tickers))));
        self::buscarDetalhes(array_values(array_filter($tickers, fn ($t) => self::detalhavel($t) && ! Cache::has("mercado.ativo.$t"))));

        $saida = [];
        foreach ($tickers as $t) {
            $l = self::daLista($t);
            $d = self::detalheGuardado($t);
            $saida[$t] = ($l || $d) ? self::juntar($l, $d) : null;
        }

        return $saida;
    }

    /** Preço mais recente de um ticker (detalhe ou lista), ou null. */
    public static function preco(string $ticker): ?float
    {
        $a = self::ativos([$ticker])[$ticker] ?? null;

        return $a && $a['preco'] > 0 ? $a['preco'] : null;
    }

    private static function detalheGuardado(string $t): ?array
    {
        $d = Cache::get("mercado.ativo.$t");

        return is_array($d) && empty($d['falhou']) ? $d : null;
    }

    /** Busca os detalhes na brapi em paralelo e guarda (falha também fica guardada por 10 min). */
    private static function buscarDetalhes(array $tickers): void
    {
        if (! $tickers) {
            return;
        }
        $token = config('services.brapi.token');
        $q = fn (bool $completo) => array_filter([
            'modules' => $completo ? 'defaultKeyStatistics,summaryProfile' : null,
            'dividends' => $completo ? 'true' : null,
            'token' => $token,
        ]);

        try {
            $resps = Http::pool(fn (Pool $pool) => array_map(
                fn ($t) => $pool->as($t)->timeout(8)->acceptJson()->get(self::BASE . "/quote/$t", $q(true)),
                $tickers
            ));
        } catch (\Throwable) {
            $resps = [];
        }

        foreach ($tickers as $t) {
            $r = $resps[$t] ?? null;
            $res = ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) ? $r->json('results.0') : null;
            if (! is_array($res)) {
                // o plano do token pode não ter módulos/dividendos: tenta só a cotação
                try {
                    $r2 = Http::timeout(6)->acceptJson()->get(self::BASE . "/quote/$t", $q(false));
                    $res = $r2->ok() ? $r2->json('results.0') : null;
                } catch (\Throwable) {
                    $res = null;
                }
            }
            $d = is_array($res) ? self::normalizar($t, $res) : null;
            Cache::put("mercado.ativo.$t", $d ?? ['falhou' => true], $d ? self::TTL_DETALHE : self::TTL_FALHA);
        }
    }

    private static function normalizar(string $t, array $r): ?array
    {
        $preco = $r['regularMarketPrice'] ?? null;
        if (! is_numeric($preco) || $preco <= 0) {
            return null;
        }
        $ks = (array) ($r['defaultKeyStatistics'] ?? []);
        $perfil = (array) ($r['summaryProfile'] ?? []);

        $hoje = now()->startOfDay();
        $divs = [];
        foreach ((array) data_get($r, 'dividendsData.cashDividends', []) as $c) {
            $v = $c['rate'] ?? null;
            $com = self::data($c['lastDatePrior'] ?? null) ?? self::data($c['paymentDate'] ?? null);
            if (! is_numeric($v) || $v <= 0 || ! $com) {
                continue;
            }
            $divs[] = ['com' => $com, 'pag' => self::data($c['paymentDate'] ?? null) ?? $com, 'valor' => round((float) $v, 6), 'tipo' => self::rotulo($c['label'] ?? '')];
        }
        usort($divs, fn ($a, $b) => strcmp($b['com'], $a['com']));
        $divs = array_slice($divs, 0, 36);

        $umAno = $hoje->copy()->subYear()->toDateString();
        $soma12 = array_sum(array_map(fn ($d) => $d['com'] > $umAno && $d['com'] <= $hoje->toDateString() ? $d['valor'] : 0, $divs));
        $temDividendos = array_key_exists('dividendsData', $r);

        $vpa = is_numeric($ks['bookValue'] ?? null) && $ks['bookValue'] > 0 ? (float) $ks['bookValue'] : null;
        if (! $vpa && is_numeric($ks['priceToBook'] ?? null) && $ks['priceToBook'] > 0) {
            $vpa = (float) $preco / (float) $ks['priceToBook'];
        }
        $dyFonte = is_numeric($ks['dividendYield'] ?? null) ? (float) $ks['dividendYield'] : null;

        return [
            't' => $t,
            'n' => self::nomeCurto((string) ($r['longName'] ?? $r['shortName'] ?? $t)),
            'p' => (float) $preco,
            'c' => (float) ($r['regularMarketChangePercent'] ?? 0),
            'v' => (float) ($r['regularMarketVolume'] ?? 0),
            'setor' => $perfil['sectorDisp'] ?? $perfil['sector'] ?? null,
            'segmento' => $perfil['industryDisp'] ?? $perfil['industry'] ?? null,
            'logo' => is_string($r['logourl'] ?? null) && str_starts_with($r['logourl'], 'https://') ? $r['logourl'] : null,
            'vpa' => $vpa,
            'prov12' => $temDividendos ? round($soma12, 6) : null,
            // a brapi às vezes manda o DY como fração (0,08) — só usado quando não há histórico de dividendos
            'dyFonte' => $dyFonte !== null ? ($dyFonte < 1 ? $dyFonte * 100 : $dyFonte) : null,
            'divs' => $divs,
            'em' => now()->toIso8601String(),
        ];
    }

    /** Junta a linha da lista (preço mais novo) e o detalhe (dividendos, VPA) no formato da API do app. */
    private static function juntar(?array $l, ?array $d): array
    {
        $preco = (float) ($l['p'] ?? $d['p'] ?? 0);
        $t = $l['t'] ?? $d['t'];
        $prov12 = $d['prov12'] ?? null;
        $dy = $prov12 !== null && $preco > 0 ? round($prov12 / $preco * 100, 2) : ($d['dyFonte'] ?? null);
        $pvp = ! empty($d['vpa']) && $preco > 0 ? round($preco / $d['vpa'], 2) : null;
        $divs = $d['divs'] ?? [];
        $volume = (float) ($l['v'] ?? $d['v'] ?? 0);

        return [
            'ticker' => $t,
            'nome' => $l['n'] ?? $d['n'] ?? $t,
            'tipo' => $l['tipo'] ?? (preg_match('/11$/', $t) ? 'fii' : 'acao'),
            'setor' => $l['ss'] ?? $d['segmento'] ?? $d['setor'] ?? $l['s'] ?? null,
            'preco' => round($preco, 4),
            'variacao' => round((float) ($l['c'] ?? $d['c'] ?? 0), 2),
            'liquidez' => round($preco * $volume),
            'logo' => $l['logo'] ?? $d['logo'] ?? null,
            'dy' => $dy !== null ? round($dy, 2) : null,
            'pvp' => $pvp,
            'vpa' => isset($d['vpa']) ? round($d['vpa'], 2) : null,
            'proventos12m' => $prov12,
            'rendaMensalCota' => $prov12 !== null ? round($prov12 / 12, 6) : null,
            'ultimoDividendo' => $divs[0] ?? null,
            'dividendos' => array_slice($divs, 0, 24),
            'completo' => $d !== null,
        ];
    }

    private static function data(mixed $v): ?string
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return null;
        }
        if (strlen($v) === 10) {
            return $v;
        }
        try {
            // a brapi manda meia-noite de Brasília em UTC (03:00Z): a data certa é a de São Paulo
            return \Carbon\CarbonImmutable::parse($v)->setTimezone('America/Sao_Paulo')->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function rotulo(string $l): string
    {
        return match (strtoupper($l)) {
            'JCP' => 'JCP',
            'RENDIMENTO' => 'Rendimento',
            'AMORTIZACAO', 'AMORTIZAÇÃO' => 'Amortização',
            default => 'Dividendo',
        };
    }
}
