<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pagamento de plano por Pix direto na chave da administração (ver AssinaturaController e PixAdminController). */
class PixPagamento extends Model
{
    protected $table = 'pix_pagamentos';

    public const STATUS = [
        'aguardando' => 'Aguardando pagamento',
        'informado'  => 'Pagamento informado',
        'confirmado' => 'Confirmado',
        'recusado'   => 'Não encontrado',
        'cancelado'  => 'Cancelado',
    ];

    /** Pedidos que ainda podem ser pagos ou confirmados. */
    public const ABERTOS = ['aguardando', 'informado'];

    protected $fillable = ['user_id', 'plano_slug', 'valor', 'txid', 'status'];

    protected $casts = [
        'valor'         => 'decimal:2',
        'informado_em'  => 'datetime',
        'confirmado_em' => 'datetime',
        'liberado_ate'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aberto(): bool
    {
        return in_array($this->status, self::ABERTOS, true);
    }
}
