<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lançamento no saldo do dono da loja (App\Support\SaldoLoja):
 *   credito  Pix de um pedido confirmado na chave da plataforma (+)
 *   resgate  pedido de resgate para a chave do dono (−)
 *   estorno  resgate recusado: o valor volta (+)
 *   mp       pedido pago no Mercado Pago do próprio dono: só histórico, não entra no saldo (o dinheiro já está com ele)
 */
class LojaSaldoMovimento extends Model
{
    protected $table = 'loja_saldo_movimentos';

    /** Tipos que contam no saldo disponível. */
    public const NO_SALDO = ['credito', 'resgate', 'estorno'];

    public const TIPOS = [
        'credito' => 'Pedido pago (Pix)',
        'resgate' => 'Resgate',
        'estorno' => 'Resgate devolvido',
        'mp'      => 'Pago no seu Mercado Pago',
    ];

    protected $fillable = ['user_id', 'tipo', 'valor', 'loja_pedido_id', 'loja_resgate_id', 'descricao'];

    protected $casts = ['valor' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
