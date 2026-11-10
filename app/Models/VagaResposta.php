<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta de uma conta a uma vaga no modo cartões: candidatou (direita) ou descartou (esquerda). */
class VagaResposta extends Model
{
    protected $table = 'vagas_respostas';

    public const ACOES = ['candidatou', 'descartou'];
    public const STATUS = [
        'enviada'    => 'Enviada',
        'vista'      => 'Vista pela empresa',
        'entrevista' => 'Chamado para entrevista',
        'recusada'   => 'Não seguiu',
        'contratada' => 'Contratado',
    ];

    protected $fillable = ['vaga_id', 'user_id', 'acao', 'portfolio_slug', 'mensagem', 'status'];

    public function vaga(): BelongsTo
    {
        return $this->belongsTo(VagaOferta::class, 'vaga_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
