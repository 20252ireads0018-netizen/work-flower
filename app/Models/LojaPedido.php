<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pedido feito na página pública de uma mini loja, pago por Pix na chave do dono ou por cartão no Mercado Pago do dono. */
class LojaPedido extends Model
{
    protected $table = 'loja_pedidos';

    public const STATUS = [
        'aguardando' => 'Aguardando pagamento',
        'informado'  => 'Cliente avisou que pagou',
        'pago'       => 'Pago',
        'enviado'    => 'Enviado / entregue',
        'cancelado'  => 'Cancelado',
    ];

    protected $fillable = ['codigo', 'cliente_nome', 'cliente_contato', 'cliente_endereco', 'observacao', 'itens', 'total', 'status', 'subtotal', 'desconto', 'frete', 'frete_nome', 'cupom', 'pagamento'];

    protected $casts = ['itens' => 'array', 'total' => 'decimal:2', 'subtotal' => 'decimal:2', 'desconto' => 'decimal:2', 'frete' => 'decimal:2', 'informado_em' => 'datetime', 'confirmado_em' => 'datetime'];

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class)->withTrashed(); // a loja excluída continua dona do histórico
    }

    /** Pix que caiu na chave da plataforma: quem confirma é a administração (/admin/pix) e o valor vira saldo do dono. */
    public function pixDaPlataforma(): bool
    {
        return $this->pagamento === 'pix' && $this->pix_destino === 'plataforma';
    }

    /** Custo dos itens (pelo custo atual dos produtos do catálogo; itens sem custo contam zero). */
    public function custoItens(array $custos): float
    {
        return round(array_sum(array_map(fn ($i) => (float) ($custos[$i['id'] ?? 0] ?? 0) * (int) ($i['qtd'] ?? 0), $this->itens ?: [])), 2);
    }

    /** Ainda pode ser pago (o cliente vê o QR Code). */
    public function aberto(): bool
    {
        return in_array($this->status, ['aguardando', 'informado'], true);
    }
}
