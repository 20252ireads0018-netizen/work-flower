<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensagem do canal "Reclame aqui" (ver ReclamacaoController). */
class Reclamacao extends Model
{
    protected $table = 'reclamacoes';

    public const TIPOS = [
        'reclamacao' => 'Reclamação',
        'problema'   => 'Algo não funciona',
        'sugestao'   => 'Sugestão',
        'elogio'     => 'Elogio',
    ];

    public const STATUS = [
        'aberta'     => 'Aberta',
        'respondida' => 'Respondida',
        'resolvida'  => 'Resolvida',
    ];

    protected $fillable = ['user_id', 'tipo', 'assunto', 'mensagem', 'pagina'];

    protected $casts = ['respondida_em' => 'datetime', 'resposta_vista' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
