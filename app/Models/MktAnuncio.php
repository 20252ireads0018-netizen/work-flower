<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anúncio de uma conta de marketplace (trazido na sincronização; preço, estoque e status podem ser alterados daqui). */
class MktAnuncio extends Model
{
    protected $table = 'mkt_anuncios';

    protected $fillable = [
        'user_id', 'conta_id', 'externo_id', 'sku', 'titulo', 'preco', 'estoque', 'status', 'tipo',
        'tarifa_venda', 'comissao_pct', 'frete', 'link', 'imagem', 'dados', 'sincronizado_em',
    ];

    protected function casts(): array
    {
        return [
            'preco'           => 'float',
            'estoque'         => 'integer',
            'tarifa_venda'    => 'float',
            'comissao_pct'    => 'float',
            'frete'           => 'float',
            'dados'           => 'array',
            'sincronizado_em' => 'datetime',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(MktConta::class, 'conta_id');
    }
}
