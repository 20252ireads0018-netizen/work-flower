<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidatura extends Model
{
    protected $table = 'candidaturas';

    protected $fillable = [
        'codigo', 'nome', 'email', 'fone', 'cidade', 'escolaridade', 'exp',
        'dias', 'turno', 'horas', 'msg', 'lgpd_aceito_em', 'curso_clicado_em',
        'ip', 'user_agent',
    ];

    protected $casts = [
        'dias' => 'array',
        'horas' => 'integer',
        'lgpd_aceito_em' => 'datetime',
        'curso_clicado_em' => 'datetime',
    ];
}
