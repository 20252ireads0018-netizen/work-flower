<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produto ou serviço do catálogo da conta (Negócios › Produtos), o mesmo que aparece na mini loja quando
 * loja_id está preenchido e ativo = true. Estoque null = não controla estoque (serviços, por exemplo).
 */
class LojaProduto extends Model
{
    protected $table = 'loja_produtos';

    protected $fillable = ['nome', 'descricao', 'preco', 'preco_antigo', 'estoque', 'imagem', 'ativo', 'ordem', 'sku', 'custo', 'categoria', 'tipo'];

    public const TIPOS = ['produto' => 'Produto', 'servico' => 'Serviço'];

    protected $casts = ['preco' => 'decimal:2', 'preco_antigo' => 'decimal:2', 'custo' => 'decimal:2', 'estoque' => 'integer', 'ativo' => 'boolean', 'ordem' => 'integer'];

    /** Produto criado só com a loja (como nos testes e nas telas antigas) ganha o dono da loja. */
    protected static function booted(): void
    {
        static::saving(function (LojaProduto $p) {
            if (! $p->user_id && $p->loja_id) {
                $p->user_id = Loja::withTrashed()->whereKey($p->loja_id)->value('user_id');
            }
        });
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Margem de lucro em % sobre o preço (null sem custo cadastrado). */
    public function margem(): ?float
    {
        return $this->custo !== null && (float) $this->preco > 0 ? round(((float) $this->preco - (float) $this->custo) / (float) $this->preco * 100, 1) : null;
    }

    /** Caminho da foto no disco local: fotos de produtos sem loja ficam em produtos/{dono}/, as da loja em lojas/{loja}/. */
    public function caminhoImagem(): ?string
    {
        if (! $this->imagem || ! preg_match('/^[A-Za-z0-9]{24}\.(jpg|png|webp)$/', $this->imagem)) return null;
        foreach (array_filter([$this->loja_id ? 'lojas/' . $this->loja_id : null, $this->user_id ? 'produtos/' . $this->user_id : null]) as $pasta) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($pasta . '/' . $this->imagem)) return $pasta . '/' . $this->imagem;
        }

        return null;
    }

    /** Para o JSON do catálogo em Negócios. */
    public function paraJs(): array
    {
        return [
            'id' => $this->id, 'nome' => $this->nome, 'sku' => $this->sku, 'tipo' => $this->tipo ?: 'produto',
            'categoria' => $this->categoria, 'descricao' => $this->descricao,
            'preco' => (float) $this->preco, 'custo' => $this->custo !== null ? (float) $this->custo : null,
            'margem' => $this->margem(), 'estoque' => $this->estoque,
            'na_loja' => (bool) ($this->loja_id && $this->ativo && $this->loja && ! $this->loja->trashed()), // loja excluída: fora da vitrine
            'foto' => $this->imagem ? route('negocios.produtos.foto', [$this->id, $this->imagem]) : null,
        ];
    }

    public function esgotado(): bool
    {
        return $this->estoque !== null && $this->estoque <= 0;
    }
}
