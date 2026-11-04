<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Promoção relâmpago de um plano: preço menor até uma data (admin em /admin/promocoes).
 * O preço vale para o Pix gerado durante a promoção; a renovação depois do prazo volta ao preço normal.
 */
class Promocao extends Model
{
    protected $table = 'promocoes';

    protected $fillable = ['plano_slug', 'preco', 'titulo', 'comeca_em', 'termina_em'];

    protected $casts = [
        'preco'        => 'decimal:2',
        'comeca_em'    => 'datetime',
        'termina_em'   => 'datetime',
        'encerrada_em' => 'datetime',
    ];

    public function vigente(): bool
    {
        return ! $this->encerrada_em && $this->comeca_em?->isPast() && $this->termina_em?->isFuture();
    }

    /** Promoções valendo agora, uma por plano (a de menor preço, se houver mais de uma). */
    public static function vigentes(): Collection
    {
        // uma consulta por requisição (a tela de planos pergunta uma vez por plano)
        $req = request();
        if ($req->attributes->has('wf.promocoes')) {
            return $req->attributes->get('wf.promocoes');
        }
        try {
            $lista = static::whereNull('encerrada_em')
                ->where('comeca_em', '<=', now())
                ->where('termina_em', '>', now())
                ->orderBy('preco')
                ->get()
                ->unique('plano_slug')
                ->keyBy('plano_slug');
        } catch (\Throwable $e) {
            report($e);
            $lista = collect();
        }
        $req->attributes->set('wf.promocoes', $lista);

        return $lista;
    }

    /** Preço a cobrar agora pelo plano: o da promoção vigente ou o normal. */
    public static function precoDe(string $slug, float $normal): float
    {
        $p = static::vigentes()->get($slug);

        return $p && (float) $p->preco < $normal ? (float) $p->preco : $normal;
    }
}
