<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shopee Open Platform v2. Cada chamada é assinada com HMAC-SHA256 (partner_key).
 * Docs: https://open.shopee.com/documents/v2/v2.product.get_item_list
 */
class Shopee extends Conector
{
    private const STATUS = ['NORMAL' => 'ativo', 'UNLIST' => 'pausado', 'BANNED' => 'pausado', 'SELLER_DELETE' => 'encerrado', 'SHOPEE_DELETE' => 'encerrado', 'REVIEWING' => 'pausado'];

    public function configurado(): bool
    {
        return filled($this->cfg('partner_id')) && filled($this->cfg('partner_key'));
    }

    private function host(): string
    {
        return rtrim($this->cfg('host'), '/');
    }

    /** Parâmetros assinados. Chamadas de loja assinam também access_token e shop_id. */
    private function assinados(string $caminho, ?MktConta $conta = null): array
    {
        $parceiro = (int) $this->cfg('partner_id');
        $agora = time();
        $base = $parceiro . $caminho . $agora . ($conta ? $conta->access_token . $conta->externo_id : '');

        return array_filter([
            'partner_id'   => $parceiro,
            'timestamp'    => $agora,
            'sign'         => hash_hmac('sha256', $base, (string) $this->cfg('partner_key')),
            'access_token' => $conta?->access_token,
            'shop_id'      => $conta ? (int) $conta->externo_id : null,
        ], fn ($v) => $v !== null);
    }

    public function urlAutorizacao(string $state): string
    {
        // a Shopee não devolve "state": ele vai dentro do endereço de retorno
        $caminho = '/api/v2/shop/auth_partner';

        return $this->host() . $caminho . '?' . http_build_query($this->assinados($caminho) + [
            'redirect' => $this->retorno() . '?' . http_build_query(['state' => $state]),
        ]);
    }

    public function conectar(Request $retorno): array
    {
        $loja = (int) $retorno->query('shop_id');
        $tokens = $this->token('/api/v2/auth/token/get', ['code' => (string) $retorno->query('code'), 'shop_id' => $loja]);

        $conta = new MktConta(['externo_id' => (string) $loja, 'access_token' => $tokens['access_token']]);
        $info = $this->chamar($conta, 'get', '/api/v2/shop/get_shop_info');

        return [
            'externo_id'    => (string) $loja,
            'nome'          => (string) ($info['shop_name'] ?? "Loja {$loja}"),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expira_em'     => now()->addSeconds((int) ($tokens['expire_in'] ?? 14400)),
            'extra'         => ['regiao' => $info['region'] ?? 'BR'],
        ];
    }

    public function renovar(MktConta $conta): void
    {
        $tokens = $this->token('/api/v2/auth/access_token/get', ['refresh_token' => $conta->refresh_token, 'shop_id' => (int) $conta->externo_id]);
        $conta->update([
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $conta->refresh_token,
            'expira_em'     => now()->addSeconds((int) ($tokens['expire_in'] ?? 14400)),
        ]);
    }

    private function token(string $caminho, array $corpo): array
    {
        $r = $this->http()->post($this->host() . $caminho . '?' . http_build_query($this->assinados($caminho)),
            $corpo + ['partner_id' => (int) $this->cfg('partner_id')]);
        if (! $r->ok() || ! $r->json('access_token')) {
            throw $this->falha($r, 'Shopee (autorização)');
        }

        return $r->json();
    }

    /** Chamada de loja. A Shopee responde 200 com "error" preenchido quando algo dá errado. */
    private function chamar(MktConta $conta, string $metodo, string $caminho, array $dados = []): array
    {
        $url = $this->host() . $caminho;
        $assinatura = $this->assinados($caminho, $conta);
        $r = $metodo === 'get'
            // listas repetidas (item_status=NORMAL&item_status=UNLIST) precisam ir sem colchetes
            ? $this->http()->get($url . '?' . preg_replace('/%5B\d+%5D/', '', http_build_query($assinatura + $dados)))
            : $this->http()->post($url . '?' . http_build_query($assinatura), $dados);

        $j = $r->json() ?? [];
        if (! $r->ok() || ! empty($j['error'])) {
            throw new MarketplaceErro('Shopee: ' . ($j['message'] ?? $j['error'] ?? $r->reason()) . " (HTTP {$r->status()})");
        }

        return $j['response'] ?? [];
    }

    public function anuncios(MktConta $conta): iterable
    {
        $max = (int) config('marketplaces.max_anuncios', 2000);
        $ids = [];
        $offset = 0;
        do {
            $j = $this->chamar($conta, 'get', '/api/v2/product/get_item_list', [
                'offset' => $offset, 'page_size' => 100, 'item_status' => ['NORMAL', 'UNLIST'],
            ]);
            foreach ($j['item'] ?? [] as $i) {
                $ids[] = $i['item_id'];
            }
            $offset = $j['next_offset'] ?? 0;
        } while (! empty($j['has_next_page']) && count($ids) < $max);

        foreach (array_chunk(array_slice($ids, 0, $max), 50) as $grupo) {
            $j = $this->chamar($conta, 'get', '/api/v2/product/get_item_base_info', ['item_id_list' => implode(',', $grupo)]);
            foreach ($j['item_list'] ?? [] as $i) {
                yield $this->anuncio($conta, $i);
            }
        }
    }

    private function anuncio(MktConta $conta, array $i): array
    {
        $preco = (float) data_get($i, 'price_info.0.current_price', 0);
        $estoque = (int) data_get($i, 'stock_info_v2.summary_info.total_available_stock', 0);
        $modelos = [];

        if (! empty($i['has_model'])) {
            // produto com variações: menor preço e soma do estoque dos modelos
            $m = $this->chamar($conta, 'get', '/api/v2/product/get_model_list', ['item_id' => $i['item_id']]);
            $precos = [];
            $estoque = 0;
            foreach ($m['model'] ?? [] as $mod) {
                $modelos[] = $mod['model_id'];
                $precos[] = (float) data_get($mod, 'price_info.0.current_price', 0);
                $estoque += (int) data_get($mod, 'stock_info_v2.summary_info.total_available_stock', 0);
            }
            $preco = $precos ? min($precos) : 0;
        }

        return [
            'externo_id'   => (string) $i['item_id'],
            'sku'          => ($i['item_sku'] ?? '') ?: null,
            'titulo'       => $i['item_name'] ?? null,
            'preco'        => $preco,
            'estoque'      => $estoque,
            'status'       => self::STATUS[$i['item_status'] ?? ''] ?? 'pausado',
            'tipo'         => null,
            'tarifa_venda' => null,
            'link'         => "https://shopee.com.br/product/{$conta->externo_id}/{$i['item_id']}",
            'imagem'       => data_get($i, 'image.image_url_list.0'),
            'dados'        => ['modelos' => $modelos],
        ];
    }

    public function pedidos(MktConta $conta, Carbon $desde): iterable
    {
        // a Shopee aceita no máximo 15 dias por consulta
        $fim = now()->timestamp;
        for ($ini = $desde->timestamp; $ini < $fim; $ini += 15 * 86400) {
            $cursor = '';
            do {
                $j = $this->chamar($conta, 'get', '/api/v2/order/get_order_list', [
                    'time_range_field' => 'create_time', 'time_from' => $ini, 'time_to' => min($fim, $ini + 15 * 86400 - 1),
                    'page_size' => 100, 'cursor' => $cursor,
                ]);
                $sns = array_column($j['order_list'] ?? [], 'order_sn');
                foreach (array_chunk($sns, 50) as $grupo) {
                    $d = $this->chamar($conta, 'get', '/api/v2/order/get_order_detail', [
                        'order_sn_list' => implode(',', $grupo), 'response_optional_fields' => 'total_amount,item_list',
                    ]);
                    foreach ($d['order_list'] ?? [] as $p) {
                        yield [
                            'externo_id' => (string) $p['order_sn'],
                            'data'       => isset($p['create_time']) ? Carbon::createFromTimestamp($p['create_time']) : null,
                            'status'     => $p['order_status'] ?? null,
                            'total'      => (float) ($p['total_amount'] ?? 0),
                            'tarifas'    => null,
                            'itens'      => array_map(fn ($i) => [
                                'externo_id' => (string) ($i['item_id'] ?? ''),
                                'sku'        => ($i['item_sku'] ?? '') ?: null,
                                'titulo'     => $i['item_name'] ?? null,
                                'qtd'        => (int) ($i['model_quantity_purchased'] ?? 1),
                                'preco'      => (float) ($i['model_discounted_price'] ?? 0),
                            ], $p['item_list'] ?? []),
                        ];
                    }
                }
                $cursor = $j['next_cursor'] ?? '';
            } while (! empty($j['more']) && $cursor !== '');
        }
    }

    public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array
    {
        $item = (int) $anuncio->externo_id;
        $modelos = $anuncio->dados['modelos'] ?? [];

        if (isset($mudancas['preco'])) {
            $preco = (float) $mudancas['preco'];
            $this->chamar($conta, 'post', '/api/v2/product/update_price', [
                'item_id'    => $item,
                'price_list' => $modelos
                    ? array_map(fn ($m) => ['model_id' => $m, 'original_price' => $preco], $modelos)
                    : [['original_price' => $preco]],
            ]);
        }
        if (isset($mudancas['estoque'])) {
            // com variações, o estoque total é dividido entre os modelos
            $partes = self::dividir((int) $mudancas['estoque'], max(1, count($modelos)));
            $this->chamar($conta, 'post', '/api/v2/product/update_stock', [
                'item_id'    => $item,
                'stock_list' => $modelos
                    ? array_map(fn ($m, $k) => ['model_id' => $m, 'seller_stock' => [['stock' => $partes[$k]]]], $modelos, array_keys($modelos))
                    : [['seller_stock' => [['stock' => $partes[0]]]]],
            ]);
        }
        if (isset($mudancas['status'])) {
            $this->chamar($conta, 'post', '/api/v2/product/unlist_item', [
                'item_list' => [['item_id' => $item, 'unlist' => $mudancas['status'] !== 'ativo']],
            ]);
        }

        return $mudancas;
    }
}
