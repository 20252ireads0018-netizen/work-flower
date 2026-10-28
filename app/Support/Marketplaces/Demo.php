<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Conta de demonstração: dados fictícios para conhecer a tela sem chaves de API.
 * Os anúncios são sempre os mesmos para a mesma conta; as alterações ficam só no Work Flower.
 */
class Demo extends Conector
{
    /** SKU => [nome, custo, custo extra, preço base] */
    public const PRODUTOS = [
        'CAN-AZ-01' => ['Caneca de cerâmica azul 350 ml', 14.90, 2.50, 49.90],
        'GAR-INX-75' => ['Garrafa térmica inox 750 ml', 38.00, 3.00, 119.90],
        'FON-BT-X2' => ['Fone Bluetooth X2 com estojo', 52.00, 2.00, 159.00],
        'CAP-IP-15' => ['Capinha de silicone para celular', 6.50, 1.20, 29.90],
        'LUM-LED-30' => ['Luminária de mesa LED 30 cm', 41.00, 4.50, 139.90],
        'MOC-NOT-15' => ['Mochila para notebook 15,6"', 72.00, 5.00, 219.00],
        'KIT-PIN-12' => ['Kit 12 pincéis de maquiagem', 18.00, 2.00, 59.90],
        'TAP-YOG-6' => ['Tapete de yoga 6 mm', 33.00, 4.00, 89.90],
    ];

    private const PREFIXO = ['mercadolivre' => 'MLB', 'shopee' => '2', 'amazon' => ''];

    public function configurado(): bool
    {
        return true;
    }

    public function urlAutorizacao(string $state): string
    {
        return '';
    }

    public function conectar(Request $retorno): array
    {
        return [];
    }

    public function renovar(MktConta $conta): void
    {
    }

    private function semente(MktConta $conta): int
    {
        return crc32($conta->canal . '|' . $conta->id);
    }

    public function anuncios(MktConta $conta): iterable
    {
        mt_srand($this->semente($conta));
        $k = 0;
        foreach (self::PRODUTOS as $sku => [$nome, , , $base]) {
            $k++;
            // cada canal/conta fica com um preço um pouco diferente, e nem todo produto está em todas as contas
            if (mt_rand(0, 9) < 2) {
                continue;
            }
            $preco = round($base * (0.9 + mt_rand(0, 25) / 100), 1) - 0.01;
            $id = self::PREFIXO[$conta->canal] . str_pad((string) ($conta->id * 1000 + $k), 9, '0', STR_PAD_LEFT);
            yield [
                'externo_id'   => $conta->canal === 'amazon' ? $sku : $id,
                'sku'          => $sku,
                'titulo'       => $nome,
                'preco'        => $preco,
                'estoque'      => mt_rand(0, 60),
                'status'       => mt_rand(0, 9) === 0 ? 'pausado' : 'ativo',
                'tipo'         => $conta->canal === 'mercadolivre' ? (mt_rand(0, 1) ? 'Premium' : 'Clássico') : null,
                'tarifa_venda' => null,
                'link'         => null,
                'imagem'       => null,
                'dados'        => ['demo' => true],
            ];
        }
    }

    public function pedidos(MktConta $conta, Carbon $desde): iterable
    {
        mt_srand($this->semente($conta) + 7);
        $skus = array_keys(self::PRODUTOS);
        $dias = max(1, (int) $desde->diffInDays(now()));
        for ($n = 1; $n <= 18; $n++) {
            $sku = $skus[mt_rand(0, count($skus) - 1)];
            $qtd = mt_rand(1, 3);
            $preco = round(self::PRODUTOS[$sku][3] * (0.95 + mt_rand(0, 15) / 100), 1) - 0.01;
            yield [
                'externo_id' => 'DEMO-' . $conta->id . '-' . $n,
                'data'       => now()->subDays(mt_rand(0, $dias))->subMinutes(mt_rand(0, 1440)),
                'status'     => mt_rand(0, 9) === 0 ? 'cancelado' : 'pago',
                'total'      => round($preco * $qtd, 2),
                'tarifas'    => null,
                'itens'      => [['externo_id' => '', 'sku' => $sku, 'titulo' => self::PRODUTOS[$sku][0], 'qtd' => $qtd, 'preco' => $preco]],
            ];
        }
    }

    public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array
    {
        return $mudancas; // nada sai do Work Flower
    }
}
