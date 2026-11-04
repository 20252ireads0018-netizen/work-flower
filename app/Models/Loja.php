<?php

namespace App\Models;

use App\Support\Pix;
use App\Support\Planos;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mini loja de uma conta (Carteira › Loja), com página pública em /loja/{slug}. */
class Loja extends Model
{
    use SoftDeletes; // excluir a loja guarda pedidos e produtos (LojaController::excluir)

    protected $table = 'lojas';

    protected $fillable = ['slug', 'nome', 'chamada', 'sobre', 'visual', 'pix_tipo', 'pix_chave', 'pix_nome', 'pix_cidade', 'publicada'];

    protected $casts = ['visual' => 'array', 'publicada' => 'boolean', 'mp_token' => 'encrypted'];

    /** O Access Token do Mercado Pago nunca vai para a tela nem para JSON. */
    protected $hidden = ['mp_token'];

    /** Até quantas opções de entrega a loja pode ter. */
    public const MAX_FRETES = 8;

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
            'fretes'      => $this->fretes(),
        ];
    }

    private function cor(?string $c, string $padrao): string
    {
        return is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c) ? strtolower($c) : $padrao;
    }

    /** Opções de entrega com preço: [{ nome, preco, gratis_acima }]. Vazio = o cliente não escolhe entrega. */
    public function fretes(): array
    {
        $l = is_array($this->visual['fretes'] ?? null) ? $this->visual['fretes'] : [];

        return array_values(array_slice(array_filter(array_map(fn ($f) => is_array($f) && trim((string) ($f['nome'] ?? '')) !== '' ? [
            'nome'         => mb_substr(trim((string) $f['nome']), 0, 60),
            'preco'        => round(max(0, (float) ($f['preco'] ?? 0)), 2),
            'gratis_acima' => isset($f['gratis_acima']) && $f['gratis_acima'] !== null && $f['gratis_acima'] !== '' ? round(max(0, (float) $f['gratis_acima']), 2) : null,
        ] : null, $l)), 0, self::MAX_FRETES));
    }

    /** Preço de uma opção de entrega para este subtotal (grátis acima do valor, se tiver). */
    public static function precoFrete(array $f, float $subtotal): float
    {
        return $f['gratis_acima'] !== null && $subtotal >= $f['gratis_acima'] ? 0.0 : (float) $f['preco'];
    }

    /** Recebe cartão pelo Mercado Pago (o dono conectou a conta). */
    public function mpPronto(): bool
    {
        return (string) $this->getRawOriginal('mp_token') !== '';
    }

    /** Tem algum jeito de receber: Pix na chave ou Mercado Pago. */
    public function recebePedidos(): bool
    {
        return $this->pixPronto() || $this->mpPronto();
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(LojaCupom::class)->orderByDesc('id');
    }

    /**
     * Recebe Pix: desde 06/11/2026 o Pix das lojas cai na chave da plataforma (a mesma das assinaturas, /admin/pix)
     * e vira saldo do dono depois de confirmado (App\Support\SaldoLoja). Basta a plataforma ter a chave configurada.
     */
    public function pixPronto(): bool
    {
        return Pix::pronto();
    }

    /** Chave que recebe o Pix deste pedido: a da plataforma, ou a do dono nos pedidos antigos (pix_destino = loja). */
    public function pixConfig(?LojaPedido $pedido = null): array
    {
        if ($pedido && $pedido->pix_destino === 'loja' && $this->chavePropriaPronta()) {
            return ['tipo' => $this->pix_tipo, 'chave' => (string) $this->pix_chave, 'nome' => (string) $this->pix_nome, 'cidade' => (string) $this->pix_cidade];
        }

        return Pix::config();
    }

    /** O dono cadastrou a própria chave Pix (hoje usada para receber os resgates do saldo). */
    public function chavePropriaPronta(): bool
    {
        return (string) $this->pix_chave !== '' && (string) $this->pix_nome !== '';
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
