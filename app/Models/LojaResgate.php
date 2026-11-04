<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido de resgate do saldo da loja para a chave Pix do dono. A administração paga em até 7 dias úteis (/admin/pix). */
class LojaResgate extends Model
{
    protected $table = 'loja_resgates';

    public const STATUS = [
        'pendente' => 'Aguardando pagamento',
        'pago'     => 'Pago',
        'recusado' => 'Recusado (valor voltou ao saldo)',
    ];

    protected $fillable = ['valor', 'pix_tipo', 'pix_chave', 'pix_nome', 'status', 'prazo', 'observacao'];

    protected $casts = ['valor' => 'decimal:2', 'prazo' => 'date', 'pago_em' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
