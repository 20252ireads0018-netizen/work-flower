<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Produto de uma mini loja. Estoque null = não controla estoque. */
class LojaProduto extends Model
{
    protected $table = 'loja_produtos';

    protected $fillable = ['nome', 'descricao', 'preco', 'preco_antigo', 'estoque', 'imagem', 'ativo', 'ordem'];

    protected $casts = ['preco' => 'decimal:2', 'preco_antigo' => 'decimal:2', 'estoque' => 'integer', 'ativo' => 'boolean', 'ordem' => 'integer'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function esgotado(): bool
    {
        return $this->estoque !== null && $this->estoque <= 0;
    }
}
