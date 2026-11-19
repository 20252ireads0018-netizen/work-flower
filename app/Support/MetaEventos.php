<?php

namespace App\Support;

use App\Models\CorpoDado;
use App\Models\PixPagamento;
use Illuminate\Support\Carbon;

/**
 * Eventos do Pixel da Meta que acontecem longe do navegador da pessoa. A assinatura é confirmada em /admin/pix
 * (Pix::liberar), quando a pessoa nem está no site: o evento Subscribe sai na primeira página que ela abrir
 * depois disso (partials/meta-pixel), uma vez por pagamento. Os já avisados ficam em corpo_dados "pixel.assinaturas".
 */
class MetaEventos
{
    public const CHAVE = 'pixel.assinaturas';

    /** Só pagamentos confirmados a partir daqui (os antigos não viram evento de uma vez). */
    public const INICIO = '2026-11-19 00:00:00';

    /**
     * Assinaturas confirmadas ainda não avisadas ao Pixel: [{ id, valor, plano, vitalicio }]. Já marca como avisadas.
     */
    public static function assinaturasPendentes(?int $userId): array
    {
        if (! $userId) return [];
        $pagos = PixPagamento::where('user_id', $userId)->where('status', 'confirmado')
            ->where('confirmado_em', '>=', Carbon::parse(self::INICIO)->max(now()->subDays(30)))
            ->orderBy('id')->get(['id', 'valor', 'plano_slug', 'vitalicio']);
        if ($pagos->isEmpty()) return [];

        $avisados = json_decode((string) CorpoDado::where('user_id', $userId)->where('chave', self::CHAVE)->value('valor'), true) ?: [];
        $novos = $pagos->reject(fn ($p) => in_array($p->id, $avisados, true));
        if ($novos->isEmpty()) return [];

        CorpoDado::updateOrCreate(['user_id' => $userId, 'chave' => self::CHAVE],
            ['valor' => json_encode(array_slice([...$avisados, ...$novos->pluck('id')->all()], -50))]);

        return $novos->map(fn ($p) => [
            'id' => $p->id, 'valor' => round((float) $p->valor, 2), 'plano' => (string) $p->plano_slug, 'vitalicio' => (bool) $p->vitalicio,
        ])->values()->all();
    }
}
