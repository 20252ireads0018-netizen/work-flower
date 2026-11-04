<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cabeçalho e trecho de um e-mail recebido, já com a categoria automática (ou a escolhida pela pessoa). */
class EmailMensagem extends Model
{
    protected $table = 'email_mensagens';

    protected $fillable = [
        'user_id', 'conta_id', 'uid', 'message_id', 'de_nome', 'de_email', 'assunto', 'trecho',
        'recebido_em', 'lido', 'categoria', 'categoria_manual',
    ];

    protected function casts(): array
    {
        return [
            'recebido_em'      => 'datetime',
            'lido'             => 'boolean',
            'categoria_manual' => 'boolean',
        ];
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(EmailConta::class, 'conta_id');
    }

    public function paraTela(): array
    {
        return [
            'id'        => $this->id,
            'conta_id'  => $this->conta_id,
            'de_nome'   => $this->de_nome,
            'de_email'  => $this->de_email,
            'assunto'   => $this->assunto,
            'trecho'    => $this->trecho,
            'data'      => $this->recebido_em?->toIso8601String(),
            'lido'      => $this->lido,
            'categoria' => $this->categoria,
            'manual'    => $this->categoria_manual,
        ];
    }
}
