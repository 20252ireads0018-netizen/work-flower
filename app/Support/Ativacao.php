<?php

namespace App\Support;

use App\Models\CorpoDado;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sequência de ativação por e-mail para quem se cadastra (comando emails:ativacao, de hora em hora):
 *   d0 boas-vindas com um passo inicial (criar a primeira revisão), d1 como a revisão espaçada avisa,
 *   d3 lembrete com o atalho (se não usou) ou a próxima função (prazos e calendário), d7 o plano pago.
 * Cada etapa tem uma janela (idade da conta); passada a janela, a etapa não sai mais (nada de lote atrasado).
 * O que já saiu e o descadastro ficam em corpo_dados, chave "emails.ativacao" (sem tabela nova).
 * Só contas criadas a partir de INICIO: quem já tinha conta antes da sequência existir não recebe.
 */
class Ativacao
{
    public const CHAVE = 'emails.ativacao';

    /** Dia em que a sequência foi publicada. */
    public const INICIO = '2026-11-19 00:00:00';

    /** etapa => [de, até) em horas de idade da conta */
    public const ETAPAS = [
        'd0' => [0, 24],
        'd1' => [24, 72],
        'd3' => [72, 168],
        'd7' => [168, 288],
    ];

    public static function estado(int $userId): array
    {
        $v = CorpoDado::where('user_id', $userId)->where('chave', self::CHAVE)->value('valor');
        $d = json_decode((string) $v, true);

        return is_array($d) ? $d + ['enviados' => [], 'sair' => false] : ['enviados' => [], 'sair' => false];
    }

    private static function gravar(int $userId, array $estado): void
    {
        CorpoDado::updateOrCreate(['user_id' => $userId, 'chave' => self::CHAVE], ['valor' => json_encode($estado)]);
    }

    public static function marcar(int $userId, string $etapa): void
    {
        $e = self::estado($userId);
        $e['enviados'][$etapa] = now()->toIso8601String();
        self::gravar($userId, $e);
    }

    public static function sair(int $userId): void
    {
        $e = self::estado($userId);
        $e['sair'] = true;
        self::gravar($userId, $e);
    }

    /** Etapa que esta conta deve receber agora (ou null). Uma por vez: a mais recente cuja janela está aberta. */
    public static function devida(User $u, ?Carbon $agora = null): ?string
    {
        $agora ??= now();
        if (! $u->email || Carbon::parse($u->created_at)->lt(Carbon::parse(self::INICIO))) return null;
        $e = self::estado($u->id);
        if (! empty($e['sair'])) return null;
        $horas = Carbon::parse($u->created_at)->diffInMinutes($agora) / 60;
        foreach (array_reverse(self::ETAPAS, true) as $etapa => [$de, $ate]) {
            if ($horas >= $de && $horas < $ate) {
                if (isset($e['enviados'][$etapa])) return null;
                // a oferta do plano não vai para quem já paga
                if ($etapa === 'd7' && Planos::slugPago($u) !== Planos::PADRAO) return null;

                return $etapa;
            }
        }

        return null;
    }

    /** Já criou flashcards (a revisão espaçada) na Mente? Decide o e-mail do dia 3. */
    public static function usouRevisao(int $userId): bool
    {
        return DB::table('corpo_dados')->where('user_id', $userId)->where('chave', 'like', 'mente.%')
            ->where('valor', 'like', '%"flashcards"%')->exists();
    }

    /** Preço mensal do plano (tabela plans), para o e-mail do dia 7. */
    public static function preco(string $slug): ?float
    {
        $p = DB::table('plans')->where('slug', $slug)->where('active', 1)->value('price');

        return $p !== null ? (float) $p : null;
    }
}
