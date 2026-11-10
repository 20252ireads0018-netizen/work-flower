<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promoção de um plano (admin em /admin/promocoes), de dois jeitos:
 * - mensal (vitalicio = false): preço menor até uma data; vale para o Pix gerado durante a promoção e a
 *   renovação depois do prazo volta ao preço normal;
 * - vitalícia (vitalicio = true, "Pague uma vez, receba para sempre"): o plano é comprado uma vez, pelo preço
 *   promocional, e não expira. preco_cheio é o preço "de" mostrado riscado. Só dá para comprar durante o prazo.
 * Cada pessoa vê o aviso da promoção uma vez, na primeira página que abre depois que ela começou (promocao_vistas).
 */
class Promocao extends Model
{
    protected $table = 'promocoes';

    protected $fillable = ['plano_slug', 'preco', 'titulo', 'comeca_em', 'termina_em', 'vitalicio', 'preco_cheio', 'descricao'];

    protected $casts = [
        'preco'        => 'decimal:2',
        'preco_cheio'  => 'decimal:2',
        'vitalicio'    => 'boolean',
        'comeca_em'    => 'datetime',
        'termina_em'   => 'datetime',
        'encerrada_em' => 'datetime',
    ];

    public function vigente(): bool
    {
        return ! $this->encerrada_em && $this->comeca_em?->isPast() && $this->termina_em?->isFuture();
    }

    /** Situação para a tela do admin. */
    public function situacao(): string
    {
        return match (true) {
            (bool) $this->encerrada_em        => $this->termina_em?->isFuture() ? 'Desativada' : 'Encerrada',
            (bool) $this->termina_em?->isPast() => 'Terminou',
            (bool) $this->comeca_em?->isFuture() => 'Agendada',
            default                             => 'Valendo agora',
        };
    }

    /** Preço "de" do vitalício (o cheio definido pelo admin), ou null. */
    public function precoCheio(): ?float
    {
        return $this->preco_cheio !== null && (float) $this->preco_cheio > (float) $this->preco ? (float) $this->preco_cheio : null;
    }

    /** O banco já tem as colunas do vitalício? (deploy sem a migração nova não quebra as promoções antigas) */
    public static function temVitalicio(): bool
    {
        static $tem = null;

        return $tem ??= (function () {
            try {
                return Schema::hasColumn('promocoes', 'vitalicio');
            } catch (\Throwable $e) {
                return false;
            }
        })();
    }

    private static function valendoAgora()
    {
        return static::whereNull('encerrada_em')->where('comeca_em', '<=', now())->where('termina_em', '>', now());
    }

    /** Promoções MENSAIS valendo agora, uma por plano (a de menor preço, se houver mais de uma). */
    public static function vigentes(): Collection
    {
        // uma consulta por requisição (a tela de planos pergunta uma vez por plano)
        $req = request();
        if ($req->attributes->has('wf.promocoes')) {
            return $req->attributes->get('wf.promocoes');
        }
        try {
            $lista = static::valendoAgora()
                ->when(static::temVitalicio(), fn ($q) => $q->where('vitalicio', false))
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

    /** Oferta vitalícia valendo agora (a de menor preço), ou null. */
    public static function vitalicioVigente(): ?self
    {
        if (! static::temVitalicio()) {
            return null;
        }
        try {
            return static::valendoAgora()->where('vitalicio', true)->orderBy('preco')->first();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Preço a cobrar agora pelo plano mensal: o da promoção vigente ou o normal. */
    public static function precoDe(string $slug, float $normal): float
    {
        $p = static::vigentes()->get($slug);

        return $p && (float) $p->preco < $normal ? (float) $p->preco : $normal;
    }

    /**
     * Promoção que a pessoa ainda não viu (a mais nova valendo agora) — já marcada como vista, para o aviso
     * aparecer uma vez só. null se não há nada novo. Quem já tem o plano vitalício não recebe aviso de venda.
     */
    public static function avisoPara($usuario): ?self
    {
        if (! $usuario || ! static::temVitalicio() || ! empty($usuario->plano_vitalicio)) {
            return null;
        }
        try {
            $p = static::valendoAgora()
                ->whereNotExists(fn ($q) => $q->from('promocao_vistas')->whereColumn('promocao_vistas.promocao_id', 'promocoes.id')
                    ->where('promocao_vistas.user_id', $usuario->id))
                ->orderByDesc('vitalicio')->latest('id')->first();
            if (! $p) {
                return null;
            }
            // insertOrIgnore: duas abas abertas ao mesmo tempo não dão erro de chave repetida
            DB::table('promocao_vistas')->insertOrIgnore(['promocao_id' => $p->id, 'user_id' => $usuario->id, 'vista_em' => now()]);

            return $p;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
