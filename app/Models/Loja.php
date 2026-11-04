<?php

namespace App\Models;

use App\Support\Planos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mini loja de uma conta (Carteira › Loja), com página pública em /loja/{slug}. */
class Loja extends Model
{
    protected $table = 'lojas';

    protected $fillable = ['slug', 'nome', 'chamada', 'sobre', 'visual', 'pix_tipo', 'pix_chave', 'pix_nome', 'pix_cidade', 'publicada'];

    protected $casts = ['visual' => 'array', 'publicada' => 'boolean'];

    /** Endereços que não podem virar loja (rotas e nomes que confundiriam). */
    public const RESERVADOS = ['admin', 'api', 'loja', 'lojas', 'work-flower', 'workflower', 'websight', 'planos', 'login', 'pedido', 'carrinho', 'suporte'];

    public const FONTES = ['sans' => 'Moderna', 'serif' => 'Clássica', 'mono' => 'Técnica', 'arredondada' => 'Arredondada'];

    /** Aparência com os valores que faltarem preenchidos. */
    public function visual(): array
    {
        $v = is_array($this->visual) ? $this->visual : [];

        return [
            'cor'         => $this->cor($v['cor'] ?? null, '#2f6fc7'),
            'fundo'       => $this->cor($v['fundo'] ?? null, '#f7f5f0'),
            'texto'       => $this->cor($v['texto'] ?? null, '#1f2328'),
            'cartao'      => $this->cor($v['cartao'] ?? null, '#ffffff'),
            'fonte'       => isset(self::FONTES[$v['fonte'] ?? '']) ? $v['fonte'] : 'sans',
            'layout'      => in_array($v['layout'] ?? '', ['grade', 'lista'], true) ? $v['layout'] : 'grade',
            'cantos'      => in_array($v['cantos'] ?? '', ['retos', 'suaves', 'redondos'], true) ? $v['cantos'] : 'suaves',
            'logo'        => $v['logo'] ?? null,
            'banner'      => $v['banner'] ?? null,
            'whatsapp'    => $v['whatsapp'] ?? null,
            'instagram'   => $v['instagram'] ?? null,
            'estoque'     => (bool) ($v['estoque'] ?? true),
            'entrega'     => $v['entrega'] ?? null, // texto livre: "Retirada ou entrega no bairro…"
            'botao'       => $v['botao'] ?? 'Comprar',
        ];
    }

    private function cor(?string $c, string $padrao): string
    {
        return is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $padrao;
    }

    public function pixPronto(): bool
    {
        return (string) $this->pix_chave !== '' && (string) $this->pix_nome !== '';
    }

    public function pixConfig(): array
    {
        return ['tipo' => $this->pix_tipo, 'chave' => (string) $this->pix_chave, 'nome' => (string) $this->pix_nome, 'cidade' => (string) $this->pix_cidade];
    }

    /** Quantos produtos o plano do dono permite (null = ilimitado). */
    public function limiteProdutos(): ?int
    {
        return Planos::limite(Planos::slugDe($this->user), 'loja');
    }

    /** Plano gratuito mostra "Feito com Work Flower" no rodapé da loja. */
    public function comMarca(): bool
    {
        return Planos::slugDe($this->user) === Planos::PADRAO;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(LojaProduto::class)->orderBy('ordem')->orderBy('id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(LojaPedido::class)->latest('id');
    }
}
