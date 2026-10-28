<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = ['gateway', 'event_id', 'event', 'payload', 'processed_at', 'attempts', 'error'];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];
}

