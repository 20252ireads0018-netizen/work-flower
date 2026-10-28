<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Um marketplace. Cada canal traduz a API dele para o formato comum abaixo.
 *
 * Anúncio: ['externo_id', 'sku', 'titulo', 'preco', 'estoque', 'status' (ativo|pausado|encerrado),
 *           'tipo', 'tarifa_venda', 'link', 'imagem', 'dados']
 * Pedido:  ['externo_id', 'data', 'status', 'total', 'tarifas', 'itens' => [['externo_id', 'sku', 'titulo', 'qtd', 'preco']]]
 */
abstract class Conector
{
    public function __construct(protected string $canal)
    {
    }

    public function canal(): string
    {
        return $this->canal;
    }

    /** Configuração do canal em config/marketplaces.php. */
    protected function cfg(?string $chave = null, $padrao = null)
    {
        return config("marketplaces.canais.{$this->canal}" . ($chave ? ".{$chave}" : ''), $padrao);
    }

    /** Endereço para onde o marketplace devolve o vendedor depois de autorizar. */
    protected function retorno(): string
    {
        return route('carteira.mkt.retorno', $this->canal);
    }

    /** true se as chaves do app de desenvolvedor estão no .env. */
    abstract public function configurado(): bool;

    /** Endereço da tela de autorização do marketplace. */
    abstract public function urlAutorizacao(string $state): string;

    /**
     * Troca o código do retorno pelos tokens.
     * @return array{externo_id: string, nome: string, access_token: ?string, refresh_token: ?string, expira_em: ?Carbon, extra: array}
     */
    abstract public function conectar(Request $retorno): array;

    /** Renova o access token (grava na conta). */
    abstract public function renovar(MktConta $conta): void;

    /** @return iterable<array> anúncios da conta no formato comum */
    abstract public function anuncios(MktConta $conta): iterable;

    /** @return iterable<array> pedidos desde a data, no formato comum */
    abstract public function pedidos(MktConta $conta, Carbon $desde): iterable;

    /**
     * Envia ao marketplace preço, estoque e/ou status (['preco' => .., 'estoque' => .., 'status' => 'ativo'|'pausado']).
     * Devolve o que deve ser gravado no anúncio local (em geral as próprias mudanças).
     */
    abstract public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array;

    /** Tarifa de venda que o marketplace cobraria neste preço (null = usar a regra da conta). */
    public function tarifaPara(MktConta $conta, MktAnuncio $anuncio, float $preco): ?float
    {
        return null;
    }

    /** Divide um estoque total entre N variações (o resto vai para as primeiras). */
    protected static function dividir(int $total, int $n): array
    {
        return array_map(fn ($k) => intdiv($total, $n) + ($k < $total % $n ? 1 : 0), range(0, $n - 1));
    }

    /** Renova o token quando faltam menos de 5 minutos para vencer. */
    public function garantirToken(MktConta $conta): void
    {
        if ($conta->expira_em && $conta->expira_em->lt(now()->addMinutes(5))) {
            $this->renovar($conta);
        }
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)->acceptJson()->withUserAgent('WorkFlower/1.0 (Carteira Marketplaces)');
    }

    /** Falha da API vira mensagem legível (vai para a tela e para mkt_contas.erro). */
    protected function falha(Response $r, string $acao): MarketplaceErro
    {
        $j = $r->json() ?? [];
        $msg = $j['message'] ?? $j['error_description'] ?? $j['error'] ?? data_get($j, 'errors.0.message') ?? $r->reason();

        return new MarketplaceErro("{$acao}: " . (is_string($msg) ? $msg : json_encode($msg)) . " (HTTP {$r->status()})");
    }

    /** Converte em número com duas casas (aceita null). */
    protected static function num($v): ?float
    {
        return is_numeric($v) ? round((float) $v, 2) : null;
    }
}
