<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensagem do formulário "Fale comigo" de um portfólio público. */
class PortfolioMensagem extends Model
{
    protected $table = 'portfolio_mensagens';

    protected $fillable = ['nome', 'contato', 'assunto', 'mensagem', 'lida', 'ip_hash'];

    protected $casts = ['lida' => 'boolean'];

    protected $hidden = ['ip_hash'];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
