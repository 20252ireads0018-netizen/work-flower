<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const PAST_DUE = 'past_due';
    public const CANCELED = 'canceled';
    public const SUSPENDED = 'suspended'; // reembolso/chargeback: nunca dá acesso

    /** Status que impedem criar uma nova assinatura. */
    public const LIVE = [self::PENDING, self::ACTIVE, self::PAST_DUE, self::SUSPENDED];

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'billing_type', 'amount', 'gateway',
        'gateway_subscription_id', 'trial_ends_at', 'current_period_end', 'canceled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Regra única de acesso ao sistema. */
    public function grantsAccess(): bool
    {
        if ($this->status === self::PENDING) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        }

        if (! in_array($this->status, [self::ACTIVE, self::PAST_DUE, self::CANCELED], true)) {
            return false;
        }

        if ($this->current_period_end === null) {
            return false;
        }

        // Cancelada: vai até o fim do período já pago. Demais: período + tolerância.
        $limit = $this->status === self::CANCELED
            ? $this->current_period_end
            : $this->current_period_end->copy()->addDays((int) config('asaas.grace_days', 3));

        return now()->lessThanOrEqualTo($limit);
    }
}

