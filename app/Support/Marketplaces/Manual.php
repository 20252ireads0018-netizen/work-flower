<?php

namespace App\Support\Marketplaces;

use App\Models\MktAnuncio;
use App\Models\MktConta;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Conta alimentada por planilha (Carteira › Marketplaces › Importar planilha), para quem ainda não conectou a loja.
 * Nada é buscado nem enviado ao marketplace: preço, estoque e status mudam só no Work Flower.
 */
class Manual extends Conector
{
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

    public function anuncios(MktConta $conta): iterable
    {
        return [];
    }

    public function pedidos(MktConta $conta, Carbon $desde): iterable
    {
        return [];
    }

    public function atualizar(MktConta $conta, MktAnuncio $anuncio, array $mudancas): array
    {
        return $mudancas;
    }
}
