<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Mercado Livre (API pública, OAuth 2.0 com código de autorização).
 * Docs: https://developers.mercadolivre.com.br/pt_br/api-docs-pt-br
 */
class MercadoLivre extends Conector
{
    private const STATUS = ['active' => 'ativo', 'paused' => 'pausado', 'closed' => 'encerrado', 'under_review' => 'pausado', 'inactive' => 'pausado'];
    private const TIPOS = ['gold_special' => 'Clássico', 'gold_pro' => 'Premium', 'free' => 'Grátis'];

    /** tarifas já consultadas nesta sincronização (categoria+tipo+preço) */
    private array $tarifas = [];

    public function configurado(): bool
    {
        return filled($this->cfg('client_id')) && filled($this->cfg('client_secret'));
    }

    private function api(): string
    {
        return rtrim($this->cfg('api'), '/');
    }

    public function urlAutorizacao(string $state): string
    {
        return $this->cfg('auth_url') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->cfg('client_id'),
            'redirect_uri'  => $this->retorno(),
            'state'         => $state,
        ]);
    }

    public function conectar(Request $retorno): array
    {
        $tokens = $this->token([
            'grant_type'   => 'authorization_code',
            'code'         => (string) $retorno->query('code'),
            'redirect_uri' => $this->retorno(),
        ]);

        $eu = $this->http()->withToken($tokens['access_token'])->get($this->api() . '/users/me');
        if (! $eu->ok()) {
            throw $this->falha($eu, 'Mercado Livre (dados do vendedor)');
        }

        return [
            'externo_id'    => (string) $eu->json('id'),
            'nome'          => (string) ($eu->json('nickname') ?: 'Mercado Livre'),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expira_em'     => now()->addSeconds((int) ($tokens['expires_in'] ?? 21600)),
            'extra'         => ['site' => $eu->json('site_id', $this->cfg('site'))],
        ];
    }

    public function renovar(MktConta $conta): void
    {
        $tokens = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $conta->refresh_token]);
        $conta->update([
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $conta->refresh_token,
            'expira_em'     => now()->addSeconds((int) ($tokens['expires_in'] ?? 21600)),
        ]);
    }

    private function token(array $campos): array
    {
        $r = $this->http()->asForm()->post($this->api() . '/oauth/token', $campos + [
            'client_id'     => $this->cfg('client_id'),
            'client_secret' => $this->cfg('client_secret'),
        ]);
        if (! $r->ok() || ! $r->json('access_token')) {
            throw $this->falha($r, 'Mercado Livre (autorização)');
        }

        return $r->json();
    }

    private function get(MktConta $conta, string $caminho, array $query = []): array
    {
        $r = $this->http()->withToken($conta->access_token)->get($this->api() . $caminho, $query);
        if (! $r->ok()) {
            throw $this->falha($r, 'Mercado Livre');
        }

        return $r->json() ?? [];
    }

    public function anuncios(MktConta $conta): iterable
    {
        $max = (int) config('marketplaces.max_anuncios', 2000);
        $ids = [];
        $scroll = null;
        // search_type=scan percorre mais de 1000 anúncios (o offset comum para em 1000)
        do {
            $j = $this->get($conta, "/users/{$conta->externo_id}/items/search", array_filter([
                'search_type' => 'scan', 'limit' => 100, 'scroll_id' => $scroll,
            ]));
            $lote = $j['results'] ?? [];
            $ids = array_merge($ids, $lote);
            $scroll = $j['scroll_id'] ?? null;
        } while ($lote && $scroll && count($ids) < $max);

        foreach (array_chunk(array_slice($ids, 0, $max), 20) as $grupo) {
            $itens = $this->get($conta, '/items', [
                'ids'        => implode(',', $grupo),
                'attributes' => 'id,title,price,available_quantity,status,listing_type_id,permalink,thumbnail,seller_custom_field,category_id,attributes,variations',
            ]);
            foreach ($itens as $i) {
                if (($i['code'] ?? 200) !== 200 || empty($i['body'])) {
                    continue;
                }
                yield $this->anuncio($conta, $i['body']);
            }
        }
    }

    private function anuncio(MktConta $conta, array $b): array
    {
        $sku = $b['seller_custom_field'] ?? null;
        foreach ($b['attributes'] ?? [] as $a) {
            if (($a['id'] ?? '') === 'SELLER_SKU' && ! empty($a['value_name'])) {
                $sku = $a['value_name'];
            }
        }
        $variacoes = array_map(fn ($v) => $v['id'], $b['variations'] ?? []);
        $estoque = $variacoes
            ? array_sum(array_map(fn ($v) => (int) ($v['available_quantity'] ?? 0), $b['variations']))
            : (int) ($b['available_quantity'] ?? 0);

        return [
            'externo_id'   => (string) $b['id'],
            'sku'          => $sku ?: null,
            'titulo'       => $b['title'] ?? null,
            'preco'        => (float) ($b['price'] ?? 0),
            'estoque'      => $estoque,
            'status'       => self::STATUS[$b['status'] ?? ''] ?? 'pausado',
            'tipo'         => self::TIPOS[$b['listing_type_id'] ?? ''] ?? ($b['listing_type_id'] ?? null),
            'tarifa_venda' => $this->tarifa($conta, (float) ($b['price'] ?? 0), $b['listing_type_id'] ?? null, $b['category_id'] ?? null),
            'link'         => $b['permalink'] ?? null,
            'imagem'       => $b['thumbnail'] ?? null,
            'dados'        => ['variacoes' => $variacoes, 'listing_type_id' => $b['listing_type_id'] ?? null, 'category_id' => $b['category_id'] ?? null],
        ];
    }

    /** Tarifa de venda (comissão + custo fixo) que o Mercado Livre cobra por este preço, tipo e categoria. */
    public function tarifa(MktConta $conta, float $preco, ?string $tipo, ?string $categoria): ?float
    {
        if (! $tipo || $preco <= 0) {
            return null;
        }
        $chave = "{$tipo}|{$categoria}|{$preco}";
        if (! array_key_exists($chave, $this->tarifas)) {
            try {
                $site = $conta->extra['site'] ?? $this->cfg('site');
                $j = $this->get($conta, "/sites/{$site}/listing_prices", array_filter([
                    'price' => $preco, 'listing_type_id' => $tipo, 'category_id' => $categoria,
                ]));
                $this->tarifas[$chave] = self::num($j['sale_fee_amount'] ?? data_get($j, '0.sale_fee_amount'));
            } catch (\Throwable) {
                $this->tarifas[$chave] = null; // sem tarifa: a margem usa a regra da conta
            }
        }

        return $this->tarifas[$chave];
    }

    public function pedidos(MktConta $conta, Carbon $desde): iterable
    {
        $offset = 0;
        do {
            $j = $this->get($conta, '/orders/search', [
                'seller'                  => $conta->externo_id,
                'order.date_created.from' => $desde->copy()->utc()->format('Y-m-d\TH:i:s.000\Z'),
                'sort'                    => 'date_desc',
                'limit'                   => 50,
                'offset'                  => $offset,
            ]);
            $lote = $j['results'] ?? [];
            foreach ($lote as $p) {
                $itens = [];
                $tarifas = 0.0;
                foreach ($p['order_items'] ?? [] as $i) {
                    $qtd = (int) ($i['quantity'] ?? 1);
                    $tarifas += (float) ($i['sale_fee'] ?? 0) * $qtd;
                    $itens[] = [
                        'externo_id' => (string) data_get($i, 'item.id'),
                        'sku'        => data_get($i, 'item.seller_sku'),
                        'titulo'     => data_get($i, 'item.title'),
                        'qtd'        => $qtd,
                        'preco'      => (float) ($i['unit_price'] ?? 0),
                    ];
                }
                yield [
                    'externo_id' => (string) $p['id'],
                    'data'       => isset($p['date_created']) ? Carbon::parse($p['date_created']) : null,
                    'status'     => $p['status'] ?? null,
                    'total'      => (float) ($p['total_amount'] ?? 0),
                    'tarifas'    => round($tarifas, 2),
                    'itens'      => $itens,
                ];
            }
            $offset += 50;
        } while (count($lote) === 50 && $offset < (int) data_get($j, 'paging.total', 0) && $offset < 1000);
    }

    public function tarifaPara(MktConta $conta, MktAnuncio $anuncio, float $preco): ?float
    {
        return $this->tarifa($conta, $preco, $anuncio->dados['listing_type_id'] ?? null, $anuncio->dados['category_id'] ?? null);
    }

    public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array
    {
        $corpo = [];
        $variacoes = $anuncio->dados['variacoes'] ?? [];

        if (isset($mudancas['status'])) {
            $corpo['status'] = $mudancas['status'] === 'ativo' ? 'active' : 'paused';
        }
        if ($variacoes && (isset($mudancas['preco']) || isset($mudancas['estoque']))) {
            // anúncio com variações: preço e estoque ficam em cada variação (o estoque é dividido igualmente)
            $partes = isset($mudancas['estoque']) ? self::dividir((int) $mudancas['estoque'], count($variacoes)) : [];
            $corpo['variations'] = array_map(fn ($id, $k) => array_filter([
                'id'                 => $id,
                'price'              => $mudancas['preco'] ?? null,
                'available_quantity' => $partes[$k] ?? null,
            ], fn ($v) => $v !== null), $variacoes, array_keys($variacoes));
        } else {
            if (isset($mudancas['preco'])) {
                $corpo['price'] = $mudancas['preco'];
            }
            if (isset($mudancas['estoque'])) {
                $corpo['available_quantity'] = (int) $mudancas['estoque'];
            }
        }
        if ($corpo) {
            $r = $this->http()->withToken($conta->access_token)->put($this->api() . "/items/{$anuncio->externo_id}", $corpo);
            if (! $r->ok()) {
                throw $this->falha($r, 'Mercado Livre (atualizar anúncio)');
            }
        }

        return $mudancas;
    }
}
