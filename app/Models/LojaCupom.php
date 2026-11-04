<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cupom de desconto de uma mini loja: porcentagem ou valor fixo, com pedido mínimo, limite de usos e validade. */
class LojaCupom extends Model
{
    protected $table = 'loja_cupons';

    protected $fillable = ['codigo', 'tipo', 'valor', 'minimo', 'usos_max', 'validade', 'ativo'];

    protected $casts = ['valor' => 'decimal:2', 'minimo' => 'decimal:2', 'usos_max' => 'integer', 'usos' => 'integer', 'validade' => 'date', 'ativo' => 'boolean'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    /** Código como a pessoa digita: maiúsculo, sem espaço. */
    public static function normalizar(?string $c): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c));
    }

    /** Motivo de não valer para este subtotal (null = vale). */
    public function erro(float $subtotal): ?string
    {
        if (! $this->ativo) return 'Este cupom não está mais valendo.';
        if ($this->validade && $this->validade->endOfDay()->isPast()) return 'Este cupom venceu.';
        if ($this->usos_max !== null && $this->usos >= $this->usos_max) return 'Este cupom já foi usado o máximo de vezes.';
        if ($this->minimo !== null && $subtotal < (float) $this->minimo) {
            return 'Este cupom vale para pedidos a partir de R$ ' . number_format((float) $this->minimo, 2, ',', '.') . '.';
        }

        return null;
    }

    /** Desconto em reais (nunca maior que o subtotal). */
    public function desconto(float $subtotal): float
    {
        $d = $this->tipo === 'valor' ? (float) $this->valor : $subtotal * min(100, (float) $this->valor) / 100;

        return round(min($subtotal, max(0, $d)), 2);
    }

    public function rotulo(): string
    {
        return $this->tipo === 'valor' ? 'R$ ' . number_format((float) $this->valor, 2, ',', '.') . ' de desconto' : rtrim(rtrim(number_format((float) $this->valor, 2, ',', ''), '0'), ',') . '% de desconto';
    }
}
