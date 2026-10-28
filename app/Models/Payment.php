<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const OVERDUE = 'overdue';
    public const REFUNDED = 'refunded';
    public const CHARGEBACK = 'chargeback';
    public const CANCELED = 'canceled';

    protected $fillable = [
        'subscription_id', 'gateway_payment_id', 'amount', 'status',
        'billing_type', 'due_date', 'paid_at', 'invoice_url',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}

