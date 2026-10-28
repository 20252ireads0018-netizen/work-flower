<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Amazon Selling Partner API (SP-API) com Login with Amazon (LWA). O Brasil fica no endpoint da região NA.
 * Docs: https://developer-docs.amazon.com/sp-api/docs
 */
class Amazon extends Conector
{
    private const LWA = 'https://api.amazon.com/auth/o2/token';

    public function configurado(): bool
    {
        return filled($this->cfg('client_id')) && filled($this->cfg('client_secret')) && filled($this->cfg('application_id'));
    }

    private function endpoint(): string
    {
        return rtrim($this->cfg('endpoint'), '/');
    }

    private function marketplace(MktConta $conta): string
    {
        return $conta->extra['marketplace_id'] ?? $this->cfg('marketplace_id');
    }

    public function urlAutorizacao(string $state): string
    {
        return rtrim($this->cfg('seller_central'), '/') . '/apps/authorize/consent?' . http_build_query(array_filter([
            'application_id' => $this->cfg('application_id'),
            'state'          => $state,
            'redirect_uri'   => $this->retorno(),
            'version'        => $this->cfg('rascunho') ? 'beta' : null,
        ]));
    }

    public function conectar(Request $retorno): array
    {
        $tokens = $this->token([
            'grant_type'   => 'authorization_code',
            'code'         => (string) $retorno->query('spapi_oauth_code'),
            'redirect_uri' => $this->retorno(),
        ]);
        $vendedor = (string) $retorno->query('selling_partner_id');

        $conta = new MktConta(['externo_id' => $vendedor, 'access_token' => $tokens['access_token'], 'extra' => []]);
        $nome = null;
        try {
            foreach ($this->chamar($conta, 'get', '/sellers/v1/marketplaceParticipations')['payload'] ?? [] as $p) {
                if (data_get($p, 'marketplace.id') === $this->cfg('marketplace_id')) {
                    $nome = data_get($p, 'storeName');
                }
            }
        } catch (MarketplaceErro) {
            // sem permissão para ler o nome da loja: fica o id do vendedor
        }

        return [
            'externo_id'    => $vendedor,
            'nome'          => $nome ?: "Amazon {$vendedor}",
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expira_em'     => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
            'extra'         => ['marketplace_id' => $this->cfg('marketplace_id')],
        ];
    }

    public function renovar(MktConta $conta): void
    {
        $tokens = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $conta->refresh_token]);
        $conta->update([
            'access_token' => $tokens['access_token'],
            'expira_em'    => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ]);
    }

    private function token(array $campos): array
    {
        $r = $this->http()->asForm()->post(self::LWA, $campos + [
            'client_id'     => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
        ]);
        if (! $r->ok() || ! $r->json('access_token')) {
            throw $this->falha($r, 'Amazon (autorização)');
        }

        return $r->json();
    }

    private function chamar(MktConta $conta, string $metodo, string $caminho, array $dados = []): array
    {
        $req = $this->http()->withHeaders(['x-amz-access-token' => $conta->access_token]);
        $url = $this->endpoint() . $caminho;
        $r = match ($metodo) {
            'get'   => $req->get($url, $dados),
            'patch' => $req->patch($url, $dados),
        };
        if (! $r->successful()) {
            throw $this->falha($r, 'Amazon');
        }

        return $r->json() ?? [];
    }

    public function anuncios(MktConta $conta): iterable
    {
        $max = (int) config('marketplaces.max_anuncios', 2000);
        $vistos = 0;
        $pagina = null;
        do {
            $j = $this->chamar($conta, 'get', "/listings/2021-08-01/items/{$conta->externo_id}", array_filter([
                'marketplaceIds' => $this->marketplace($conta),
                'includedData'   => 'summaries,offers,fulfillmentAvailability',
                'pageSize'       => 20,
                'pageToken'      => $pagina,
            ]));
            foreach ($j['items'] ?? [] as $i) {
                $vistos++;
                yield $this->anuncio($i);
            }
            $pagina = data_get($j, 'pagination.nextToken');
        } while ($pagina && $vistos < $max);
    }

    private function anuncio(array $i): array
    {
        $resumo = $i['summaries'][0] ?? [];
        $status = $resumo['status'] ?? [];
        $qtd = (int) data_get($i, 'fulfillmentAvailability.0.quantity', 0);

        return [
            'externo_id'   => (string) $i['sku'],
            'sku'          => (string) $i['sku'],
            'titulo'       => $resumo['itemName'] ?? null,
            'preco'        => (float) data_get($i, 'offers.0.price.amount', 0),
            'estoque'      => $qtd,
            'status'       => in_array('BUYABLE', $status, true) ? 'ativo' : 'pausado',
            'tipo'         => data_get($i, 'fulfillmentAvailability.0.fulfillmentChannelCode') === 'DEFAULT' ? 'Envio próprio' : 'FBA',
            'tarifa_venda' => null,
            'link'         => isset($resumo['asin']) ? "https://www.amazon.com.br/dp/{$resumo['asin']}" : null,
            'imagem'       => data_get($resumo, 'mainImage.link'),
            'dados'        => ['asin' => $resumo['asin'] ?? null, 'productType' => $resumo['productType'] ?? 'PRODUCT'],
        ];
    }

    public function pedidos(MktConta $conta, Carbon $desde): iterable
    {
        $proximo = null;
        $paginas = 0;
        do {
            $j = $this->chamar($conta, 'get', '/orders/v0/orders', $proximo
                ? ['MarketplaceIds' => $this->marketplace($conta), 'NextToken' => $proximo]
                : ['MarketplaceIds' => $this->marketplace($conta), 'CreatedAfter' => $desde->copy()->utc()->toIso8601ZuluString(), 'MaxResultsPerPage' => 100]);
            foreach (data_get($j, 'payload.Orders', []) as $p) {
                yield [
                    'externo_id' => (string) $p['AmazonOrderId'],
                    'data'       => isset($p['PurchaseDate']) ? Carbon::parse($p['PurchaseDate']) : null,
                    'status'     => $p['OrderStatus'] ?? null,
                    'total'      => (float) data_get($p, 'OrderTotal.Amount', 0),
                    'tarifas'    => null,
                    'itens'      => [], // itens exigem uma chamada por pedido (limite baixo da SP-API)
                ];
            }
            $proximo = data_get($j, 'payload.NextToken');
        } while ($proximo && ++$paginas < 10);
    }

    public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array
    {
        $mkt = $this->marketplace($conta);
        $patches = [];

        if (isset($mudancas['preco'])) {
            $patches[] = ['op' => 'replace', 'path' => '/attributes/purchasable_offer', 'value' => [[
                'marketplace_id' => $mkt, 'currency' => 'BRL',
                'our_price'      => [['schedule' => [['value_with_tax' => (float) $mudancas['preco']]]]],
            ]]];
        }
        // a Amazon não tem "pausar": pausado = estoque zero (o estoque anterior fica guardado para reativar)
        $estoque = $mudancas['estoque'] ?? null;
        if (($mudancas['status'] ?? null) === 'pausado') {
            $estoque = 0;
        } elseif (($mudancas['status'] ?? null) === 'ativo' && $estoque === null) {
            $estoque = (int) ($anuncio->dados['estoque_antes_de_pausar'] ?? $anuncio->estoque ?: 1);
        }
        if ($estoque !== null) {
            $patches[] = ['op' => 'replace', 'path' => '/attributes/fulfillment_availability', 'value' => [[
                'fulfillment_channel_code' => 'DEFAULT', 'quantity' => (int) $estoque,
            ]]];
        }
        if (! $patches) {
            return $mudancas;
        }

        $j = $this->chamar($conta, 'patch',
            "/listings/2021-08-01/items/{$conta->externo_id}/" . rawurlencode($anuncio->externo_id) . '?marketplaceIds=' . $mkt,
            ['productType' => $anuncio->dados['productType'] ?? 'PRODUCT', 'patches' => $patches]);

        if (($j['status'] ?? '') === 'INVALID') {
            throw new MarketplaceErro('Amazon recusou a alteração: ' . (data_get($j, 'issues.0.message') ?? 'dados inválidos'));
        }
        $local = $mudancas + ($estoque !== null ? ['estoque' => (int) $estoque] : []);
        if (($mudancas['status'] ?? null) === 'pausado') {
            $local['dados'] = array_merge($anuncio->dados ?? [], ['estoque_antes_de_pausar' => $anuncio->estoque]);
        }

        return $local;
    }
}
