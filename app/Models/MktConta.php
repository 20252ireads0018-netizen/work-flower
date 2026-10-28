<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conta de vendedor conectada a um marketplace (Carteira › Marketplaces). Tokens ficam criptografados. */
class MktConta extends Model
{
    protected $table = 'mkt_contas';

    protected $fillable = [
        'user_id', 'canal', 'nome', 'externo_id', 'access_token', 'refresh_token', 'expira_em',
        'extra', 'regras', 'demo', 'ativa', 'erro', 'sincronizado_em',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token'    => 'encrypted',
            'refresh_token'   => 'encrypted',
            'expira_em'       => 'datetime',
            'sincronizado_em' => 'datetime',
            'extra'           => 'array',
            'regras'          => 'array',
            'demo'            => 'boolean',
            'ativa'           => 'boolean',
        ];
    }

    public function anuncios(): HasMany
    {
        return $this->hasMany(MktAnuncio::class, 'conta_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(MktPedido::class, 'conta_id');
    }
}
