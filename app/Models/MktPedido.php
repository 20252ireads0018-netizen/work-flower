<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Venda de uma conta de marketplace (últimos 30 dias, trazida na sincronização). */
class MktPedido extends Model
{
    protected $table = 'mkt_pedidos';

    protected $fillable = ['user_id', 'conta_id', 'externo_id', 'data', 'status', 'total', 'tarifas', 'itens'];

    protected function casts(): array
    {
        return ['data' => 'datetime', 'total' => 'float', 'tarifas' => 'float', 'itens' => 'array'];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(MktConta::class, 'conta_id');
    }
}
