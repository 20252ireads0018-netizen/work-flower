<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Conta de Instagram ou TikTok ligada ao cartão Criador de conteúdo. Tokens criptografados, nunca vão ao navegador. */
class RedeConta extends Model
{
    protected $table = 'redes_contas';

    protected $fillable = [
        'user_id', 'rede', 'externo_id', 'usuario', 'nome', 'access_token', 'refresh_token',
        'expira_em', 'refresh_expira_em', 'extra', 'erro',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token'      => 'encrypted',
            'refresh_token'     => 'encrypted',
            'expira_em'         => 'datetime',
            'refresh_expira_em' => 'datetime',
            'extra'             => 'array',
        ];
    }
}
