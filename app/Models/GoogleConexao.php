<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conexão com um serviço do Google pelo login do Google (hoje só o YouTube; ver App\Support\Google\Youtube).
 * Tokens criptografados e escondidos da tela.
 */
class GoogleConexao extends Model
{
    protected $table = 'google_conexoes';

    protected $fillable = ['user_id', 'servico', 'email', 'access_token', 'refresh_token', 'expira_em', 'dados'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token'  => 'encrypted',
            'refresh_token' => 'encrypted',
            'expira_em'     => 'datetime',
            'dados'         => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
