<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espaço de trabalho do Notion ligado a uma conta (Negócios › Integrações). O token fica criptografado e nunca
 * volta para o navegador. Chamadas à API em App\Support\Notion.
 */
class NotionIntegracao extends Model
{
    protected $table = 'notion_integracoes';

    protected $fillable = ['access_token', 'tipo', 'workspace_id', 'workspace_nome', 'bot_id'];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token'   => 'encrypted',
        'ultimo_sync_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
