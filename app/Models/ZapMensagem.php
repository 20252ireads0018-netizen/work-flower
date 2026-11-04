<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensagem de uma conversa do WhatsApp oficial (recebida pelo webhook ou enviada pela tela de Clientes). */
class ZapMensagem extends Model
{
    protected $table = 'zap_mensagens';

    protected $fillable = ['conversa_id', 'direcao', 'tipo', 'texto', 'wamid', 'status', 'erro', 'autor_id', 'em'];

    protected function casts(): array
    {
        return ['em' => 'datetime'];
    }

    public function conversa(): BelongsTo
    {
        return $this->belongsTo(ZapConversa::class, 'conversa_id');
    }
}
