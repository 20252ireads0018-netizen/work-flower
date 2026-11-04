<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido feito na página pública de uma mini loja, pago por Pix na chave do dono ou por cartão no Mercado Pago do dono. */
class LojaPedido extends Model
{
    protected $table = 'loja_pedidos';

    public const STATUS = [
        'aguardando' => 'Aguardando pagamento',
        'informado'  => 'Cliente avisou que pagou',
        'pago'       => 'Pago',
        'enviado'    => 'Enviado / entregue',
        'cancelado'  => 'Cancelado',
    ];

    protected $fillable = ['codigo', 'cliente_nome', 'cliente_contato', 'cliente_endereco', 'observacao', 'itens', 'total', 'status', 'subtotal', 'desconto', 'frete', 'frete_nome', 'cupom', 'pagamento'];

    protected $casts = ['itens' => 'array', 'total' => 'decimal:2', 'subtotal' => 'decimal:2', 'desconto' => 'decimal:2', 'frete' => 'decimal:2', 'informado_em' => 'datetime'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    /** Ainda pode ser pago (o cliente vê o QR Code). */
    public function aberto(): bool
    {
        return in_array($this->status, ['aguardando', 'informado'], true);
    }
}
