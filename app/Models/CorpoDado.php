<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorpoDado extends Model
{
    protected $table = 'corpo_dados';

    protected $fillable = ['user_id', 'chave', 'valor'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}