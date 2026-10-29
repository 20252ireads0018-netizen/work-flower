<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use App\Models\MktPedido;
use App\Models\MktProduto;

/**
 * Carteira › Marketplaces: escolhe o conector de cada conta, sincroniza, calcula margem e aplica alterações em massa.
 *
 * Margem de um anúncio (valores por unidade vendida):
 *   tarifa  = tarifa informada pelo marketplace  OU  preço × comissão% + tarifa fixa (só abaixo do limite, se houver)
 *   imposto = preço × imposto%
 *   lucro   = preço − custo − custo extra − tarifa − frete − imposto
 *   margem% = lucro ÷ preço × 100
 */
class Marketplaces
{
    public const CANAIS = ['mercadolivre' => MercadoLivre::class, 'shopee' => Shopee::class, 'amazon' => Amazon::class];

    public static function canais(): array
    {
        return array_keys(self::CANAIS);
    }

    public static function nomeCanal(string $canal): string
    {
        return (string) config("marketplaces.canais.{$canal}.nome", $canal);
    }

    /** Conector real do canal (para conectar contas). */
    public static function conector(string $canal): Conector
    {
        abort_unless(isset(self::CANAIS[$canal]), 404);
        $classe = self::CANAIS[$canal];

        return new $classe($canal);
    }

    /** Conector de uma conta já conectada (a de demonstração não sai do Work Flower). */
    public static function daConta(MktConta $conta): Conector
    {
        return $conta->demo ? new Demo($conta->canal) : self::conector($conta->canal);
    }

    /* ================= regras e margem ================= */

    /** Regras de tarifa da conta: as de referência do canal com o que a conta ajustou por cima. */
    public static function regras(MktConta $conta): array
    {
        $padrao = (array) config("marketplaces.canais.{$conta->canal}.regras", []);

        return array_merge(
            ['comissao_pct' => 0, 'taxa_fixa' => 0, 'taxa_fixa_abaixo_de' => null, 'frete' => 0, 'imposto_pct' => 0],
            $padrao,
            array_filter((array) $conta->regras, fn ($v) => $v !== null && $v !== ''),
        );
    }

    /** Tarifa do marketplace num preço, pela regra (sem a tarifa informada pela API). */
    public static function tarifaPorRegra(array $regras, float $preco, ?float $comissaoPct = null): float
    {
        $pct = $comissaoPct ?? (float) $regras['comissao_pct'];
        $limite = $regras['taxa_fixa_abaixo_de'];
        $fixa = ($limite === null || $preco < (float) $limite) ? (float) $regras['taxa_fixa'] : 0.0;

        return round($preco * $pct / 100 + $fixa, 2);
    }

    /**
     * Conta da margem de um anúncio.
     * @return array{custo: ?float, tarifa: float, frete: float, imposto: float, lucro: ?float, margem: ?float, tarifa_api: bool}
     */
    public static function margem(MktAnuncio $a, ?MktProduto $p, array $regras): array
    {
        $preco = (float) $a->preco;
        $tarifaApi = $a->tarifa_venda !== null && $a->comissao_pct === null;
        $tarifa = $tarifaApi ? (float) $a->tarifa_venda : self::tarifaPorRegra($regras, $preco, $a->comissao_pct);
        $frete = $a->frete ?? (float) $regras['frete'];
        $imposto = round($preco * (float) $regras['imposto_pct'] / 100, 2);
        $custo = $p ? round($p->custo + $p->custo_extra, 2) : null;
        $lucro = $custo === null ? null : round($preco - $custo - $tarifa - $frete - $imposto, 2);

        return [
            'custo'      => $custo,
            'tarifa'     => $tarifa,
            'frete'      => round($frete, 2),
            'imposto'    => $imposto,
            'lucro'      => $lucro,
            'margem'     => ($lucro === null || $preco <= 0) ? null : round($lucro / $preco * 100, 1),
            'tarifa_api' => $tarifaApi,
        ];
    }

    /**
     * Preço que dá a margem desejada (em %) com as regras da conta. null se a margem for impossível
     * (comissão + imposto + margem chegam a 100%).
     */
    public static function precoParaMargem(float $custo, float $margemPct, array $regras, ?float $comissaoPct = null, ?float $frete = null): ?float
    {
        $pct = ($comissaoPct ?? (float) $regras['comissao_pct']) + (float) $regras['imposto_pct'] + $margemPct;
        if ($pct >= 100) {
            return null;
        }
        $frete ??= (float) $regras['frete'];
        $calc = fn (float $fixa) => round(($custo + $frete + $fixa) / (1 - $pct / 100), 2);

        $limite = $regras['taxa_fixa_abaixo_de'];
        $comFixa = $calc((float) $regras['taxa_fixa']);
        if ($limite === null || $comFixa < (float) $limite) {
            return $comFixa;
        }
        $semFixa = $calc(0);

        // se sem a tarifa fixa o preço cai abaixo do limite, o limite é o menor preço que não paga a tarifa fixa
        return $semFixa >= (float) $limite ? $semFixa : (float) $limite;
    }

    /* ================= sincronização ================= */

    /**
     * Traz anúncios e pedidos recentes da conta. Erros ficam gravados na conta (não interrompem as outras).
     * @return array{ok: bool, anuncios: int, pedidos: int, erro: ?string}
     */
    public static function sincronizar(MktConta $conta): array
    {
        @set_time_limit(180);
        $conector = self::daConta($conta);
        $nAnuncios = 0;
        $nPedidos = 0;

        try {
            $conector->garantirToken($conta);
            $agora = now();
            $vistos = [];

            foreach ($conector->anuncios($conta) as $a) {
                $vistos[] = $a['externo_id'];
                $existente = MktAnuncio::where(['conta_id' => $conta->id, 'externo_id' => $a['externo_id']])->first();
                if ($existente && $conta->demo) {
                    $nAnuncios++; // a demonstração não desfaz o que você mudou
                    continue;
                }
                $campos = collect($a)->only(['sku', 'titulo', 'preco', 'estoque', 'status', 'tipo', 'tarifa_venda', 'link', 'imagem'])->all();
                // anúncio sem SKU no marketplace: o próprio código do anúncio vira o SKU, para dar para cadastrar o custo dele
                if (blank($campos['sku'] ?? null)) {
                    $campos['sku'] = $a['externo_id'];
                }
                $campos['dados'] = array_merge($existente?->dados ?? [], $a['dados'] ?? []);
                $campos['sincronizado_em'] = $agora;
                if ($existente) {
                    $existente->update($campos);
                } else {
                    MktAnuncio::create($campos + ['user_id' => $conta->user_id, 'conta_id' => $conta->id, 'externo_id' => $a['externo_id']]);
                }
                $nAnuncios++;
            }

            // o que não veio mais do marketplace foi encerrado/excluído lá
            if (! $conta->demo) {
                MktAnuncio::where('conta_id', $conta->id)->whereNotIn('externo_id', $vistos ?: [''])
                    ->where('status', '!=', 'encerrado')->update(['status' => 'encerrado']);
            }

            foreach ($conector->pedidos($conta, now()->subDays((int) config('marketplaces.dias_pedidos', 30))) as $p) {
                MktPedido::updateOrCreate(
                    ['conta_id' => $conta->id, 'externo_id' => $p['externo_id']],
                    collect($p)->only(['data', 'status', 'total', 'tarifas', 'itens'])->all() + ['user_id' => $conta->user_id],
                );
                $nPedidos++;
            }

            $conta->update(['sincronizado_em' => $agora, 'erro' => null]);

            return ['ok' => true, 'anuncios' => $nAnuncios, 'pedidos' => $nPedidos, 'erro' => null];
        } catch (\Throwable $e) {
            if (! $e instanceof MarketplaceErro) {
                report($e);
            }
            $erro = mb_substr($e instanceof MarketplaceErro ? $e->getMessage() : 'Falha ao falar com ' . self::nomeCanal($conta->canal) . '. Tente de novo em instantes.', 0, 250);
            $conta->update(['erro' => $erro]);

            return ['ok' => false, 'anuncios' => $nAnuncios, 'pedidos' => $nPedidos, 'erro' => $erro];
        }
    }

    /* ================= alterações em massa ================= */

    /**
     * Calcula as mudanças de um anúncio para uma ação em massa.
     * Ações: preco (valor), preco_pct (± %), margem (% desejada), estoque (valor), estoque_soma (± unidades), status (ativo|pausado).
     * @return array|string mudanças, ou o motivo de não dar para aplicar
     */
    public static function mudancasPara(MktAnuncio $a, ?MktProduto $p, array $regras, string $acao, $valor)
    {
        switch ($acao) {
            case 'preco':
                return ['preco' => round((float) $valor, 2)];
            case 'preco_pct':
                return ['preco' => round($a->preco * (1 + (float) $valor / 100), 2)];
            case 'margem':
                if (! $p) {
                    return 'sem custo cadastrado (SKU ' . ($a->sku ?: 'vazio') . ')';
                }
                $preco = self::precoParaMargem($p->custo + $p->custo_extra, (float) $valor, $regras, $a->comissao_pct, $a->frete);

                return $preco === null ? 'margem impossível com essa comissão' : ['preco' => $preco];
            case 'estoque':
                return ['estoque' => max(0, (int) $valor)];
            case 'estoque_soma':
                return ['estoque' => max(0, $a->estoque + (int) $valor)];
            case 'status':
                return ['status' => $valor === 'ativo' ? 'ativo' : 'pausado'];
        }

        return 'ação desconhecida';
    }

    /**
     * Aplica uma ação em vários anúncios: envia para cada marketplace e grava aqui o que deu certo.
     * @return array{ok: int, erros: array<int, array{id: int, titulo: ?string, erro: string}>}
     */
    public static function aplicar(iterable $anuncios, string $acao, $valor): array
    {
        @set_time_limit(180);
        $ok = 0;
        $erros = [];
        $produtos = [];
        $contas = [];

        foreach ($anuncios as $a) {
            $conta = $contas[$a->conta_id] ??= $a->conta;
            if ($a->status === 'encerrado') {
                $erros[] = ['id' => $a->id, 'titulo' => $a->titulo, 'erro' => 'anúncio encerrado no marketplace'];
                continue;
            }
            $p = $a->sku ? ($produtos[$a->sku] ??= MktProduto::where(['user_id' => $a->user_id, 'sku' => $a->sku])->first() ?? false) : false;
            $mud = self::mudancasPara($a, $p ?: null, self::regras($conta), $acao, $valor);
            if (is_string($mud)) {
                $erros[] = ['id' => $a->id, 'titulo' => $a->titulo, 'erro' => $mud];
                continue;
            }
            if (isset($mud['preco']) && $mud['preco'] <= 0) {
                $erros[] = ['id' => $a->id, 'titulo' => $a->titulo, 'erro' => 'o preço precisa ser maior que zero'];
                continue;
            }

            try {
                $conector = self::daConta($conta);
                $conector->garantirToken($conta);
                $local = $conector->atualizar($conta, $a, $mud);
                if (isset($local['preco'])) {
                    $local['tarifa_venda'] = $a->tarifa_venda === null ? null : $conector->tarifaPara($conta, $a, (float) $local['preco']);
                }
                $a->update($local);
                $ok++;
            } catch (\Throwable $e) {
                if (! $e instanceof MarketplaceErro) {
                    report($e);
                }
                $erros[] = ['id' => $a->id, 'titulo' => $a->titulo, 'erro' => $e instanceof MarketplaceErro ? $e->getMessage() : 'falha ao enviar'];
            }
        }

        return ['ok' => $ok, 'erros' => $erros];
    }
}
