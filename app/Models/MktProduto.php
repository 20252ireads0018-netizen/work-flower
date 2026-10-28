<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Produto do catálogo próprio (por SKU): o custo dele é a base da margem dos anúncios com o mesmo SKU. */
class MktProduto extends Model
{
    protected $table = 'mkt_produtos';

    protected $fillable = ['user_id', 'sku', 'nome', 'custo', 'custo_extra'];

    protected function casts(): array
    {
        return ['custo' => 'float', 'custo_extra' => 'float'];
    }
}
