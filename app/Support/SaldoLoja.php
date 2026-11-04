<?php

namespace App\Support;

use App\Models\LojaPedido;
use App\Models\LojaResgate;
use App\Models\LojaSaldoMovimento;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saldo dos donos de loja. Desde 06/11/2026 o Pix dos pedidos das lojas cai na chave da plataforma (Pix::config()).
 *
 *   SaldoLoja::confirmarPedido($pedido)      -> a administração achou o Pix: pedido pago + crédito no saldo do dono
 *   SaldoLoja::registrarMp($pedido)          -> pedido pago no Mercado Pago do dono: entra no histórico, não no saldo
 *   SaldoLoja::resumo($userId)               -> disponível, a confirmar, em resgate, próximo prazo
 *   SaldoLoja::pedirResgate($userId, ...)    -> tira do saldo e cria o resgate com prazo de 7 dias úteis
 *   SaldoLoja::pagarResgate / recusarResgate -> a administração paga, ou recusa e o valor volta
 *
 * Cada operação trava a linha do usuário (lockForUpdate): dois cliques ou duas abas não resgatam o mesmo dinheiro.
 */
class SaldoLoja
{
    public const PRAZO_DIAS_UTEIS = 7;
    public const RESGATE_MINIMO = 1.0;

    /** Data limite: N dias úteis depois de $de (pula sábado e domingo; feriados não entram na conta). */
    public static function prazo(?Carbon $de = null, int $dias = self::PRAZO_DIAS_UTEIS): Carbon
    {
        $d = ($de ?? now('America/Sao_Paulo'))->copy()->startOfDay();
        while ($dias > 0) {
            $d->addDay();
            if (! $d->isWeekend()) $dias--;
        }

        return $d;
    }

    public static function disponivel(int $userId): float
    {
        return round((float) LojaSaldoMovimento::where('user_id', $userId)->whereIn('tipo', LojaSaldoMovimento::NO_SALDO)->sum('valor'), 2);
    }

    /** Números da aba Pagamento da loja. */
    public static function resumo(int $userId): array
    {
        $abertos = LojaPedido::query()->join('lojas', 'lojas.id', '=', 'loja_pedidos.loja_id')
            ->where('lojas.user_id', $userId)->where('loja_pedidos.pagamento', 'pix')->where('loja_pedidos.pix_destino', 'plataforma')
            ->whereIn('loja_pedidos.status', ['aguardando', 'informado'])
            ->selectRaw("loja_pedidos.status, count(*) n, coalesce(sum(loja_pedidos.total), 0) v")->groupBy('loja_pedidos.status')->get()->keyBy('status');
        $resgates = LojaResgate::where('user_id', $userId)->where('status', 'pendente');

        return [
            'disponivel'     => self::disponivel($userId),
            'a_confirmar'    => round((float) ($abertos['informado']->v ?? 0), 2),    // o cliente avisou que pagou
            'n_a_confirmar'  => (int) ($abertos['informado']->n ?? 0),
            'aguardando'     => round((float) ($abertos['aguardando']->v ?? 0), 2),   // pedido feito, sem aviso de pagamento
            'em_resgate'     => round((float) (clone $resgates)->sum('valor'), 2),
            'proximo_prazo'  => (clone $resgates)->min('prazo'),
            'prazo_novo'     => self::prazo(),                                        // se pedir um resgate hoje
        ];
    }

    /** A administração confirmou o Pix de um pedido na chave da plataforma. Devolve false se já tinha sido tratado. */
    public static function confirmarPedido(LojaPedido $pedido): bool
    {
        return DB::transaction(function () use ($pedido) {
            $p = LojaPedido::whereKey($pedido->id)->lockForUpdate()->first();
            if (! $p || ! $p->pixDaPlataforma() || ! $p->aberto()) return false;
            $dono = $p->loja?->user_id;
            if (! $dono) return false;
            $p->forceFill(['status' => 'pago', 'confirmado_em' => now(), 'admin_obs' => null])->save();
            LojaSaldoMovimento::create([
                'user_id' => $dono, 'tipo' => 'credito', 'valor' => round((float) $p->total, 2), 'loja_pedido_id' => $p->id,
                'descricao' => mb_substr('Pedido ' . $p->codigo . ' · ' . $p->cliente_nome, 0, 200),
            ]);

            return true;
        });
    }

    /** Pedido aprovado no Mercado Pago do dono: fica no histórico (o dinheiro já está na conta dele, não entra no saldo). */
    public static function registrarMp(LojaPedido $p): void
    {
        $dono = $p->loja?->user_id;
        if (! $dono) return;
        try {
            LojaSaldoMovimento::firstOrCreate(['tipo' => 'mp', 'loja_pedido_id' => $p->id], [
                'user_id' => $dono, 'valor' => round((float) $p->total, 2),
                'descricao' => mb_substr('Pedido ' . $p->codigo . ' · recebido no seu Mercado Pago', 0, 200),
            ]);
        } catch (QueryException $e) {
            // aviso e volta do Mercado Pago ao mesmo tempo: o outro já gravou
        }
    }

    /**
     * Pede o resgate de $valor para a chave Pix informada. Devolve o resgate ou uma mensagem de erro.
     *
     * @return LojaResgate|string
     */
    public static function pedirResgate(int $userId, float $valor, array $chave)
    {
        $valor = round($valor, 2);
        if ($valor < self::RESGATE_MINIMO) return 'O resgate mínimo é de R$ ' . number_format(self::RESGATE_MINIMO, 2, ',', '.') . '.';

        return DB::transaction(function () use ($userId, $valor, $chave) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $saldo = self::disponivel($userId);
            if ($valor > $saldo) return 'Você tem R$ ' . number_format($saldo, 2, ',', '.') . ' disponíveis para resgate.';

            $r = new LojaResgate([
                'valor' => $valor, 'pix_tipo' => $chave['tipo'], 'pix_chave' => $chave['chave'], 'pix_nome' => $chave['nome'],
                'status' => 'pendente', 'prazo' => self::prazo()->toDateString(),
            ]);
            $r->user_id = $userId;
            $r->save();
            LojaSaldoMovimento::create([
                'user_id' => $userId, 'tipo' => 'resgate', 'valor' => -$valor, 'loja_resgate_id' => $r->id,
                'descricao' => 'Resgate para ' . (Pix::TIPOS[$chave['tipo']] ?? 'chave') . ' ' . $chave['chave'],
            ]);

            return $r;
        });
    }

    public static function pagarResgate(LojaResgate $r): bool
    {
        return DB::transaction(function () use ($r) {
            $r = LojaResgate::whereKey($r->id)->lockForUpdate()->first();
            if (! $r || $r->status !== 'pendente') return false;
            $r->forceFill(['status' => 'pago', 'pago_em' => now()])->save();

            return true;
        });
    }

    public static function recusarResgate(LojaResgate $r, ?string $motivo): bool
    {
        return DB::transaction(function () use ($r, $motivo) {
            $r = LojaResgate::whereKey($r->id)->lockForUpdate()->first();
            if (! $r || $r->status !== 'pendente') return false;
            $r->forceFill(['status' => 'recusado', 'observacao' => $motivo ? mb_substr($motivo, 0, 300) : null])->save();
            LojaSaldoMovimento::create([
                'user_id' => $r->user_id, 'tipo' => 'estorno', 'valor' => round((float) $r->valor, 2), 'loja_resgate_id' => $r->id,
                'descricao' => 'Resgate #' . $r->id . ' recusado: o valor voltou ao saldo' . ($motivo ? ' (' . mb_substr($motivo, 0, 120) . ')' : ''),
            ]);

            return true;
        });
    }
}
