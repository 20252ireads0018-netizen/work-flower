<?php

namespace App\Support;

use App\Models\PixPagamento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pix direto na chave da administração.
 *
 *   Pix::config()                  -> ['tipo', 'chave', 'nome', 'cidade'] (gravados em /admin/pix; .env PIX_* como reserva)
 *   Pix::pronto()                  -> true quando há chave e nome para montar o QR Code
 *   Pix::copiaECola($valor, $txid) -> código "Pix copia e cola" (BR Code do Banco Central, com CRC16)
 *   Pix::liberar($pagamento)       -> confirma o pagamento e libera o plano por um mês
 */
class Pix
{
    public const TIPOS = [
        'cpf'      => 'CPF',
        'cnpj'     => 'CNPJ',
        'email'    => 'E-mail',
        'telefone' => 'Celular',
        'aleatoria' => 'Chave aleatória',
    ];

    private const CAMPOS = ['tipo', 'chave', 'nome', 'cidade'];

    /* ================= Configuração ================= */

    public static function config(): array
    {
        $salvo = [];
        try {
            if (Schema::hasTable('configuracoes')) {
                $salvo = DB::table('configuracoes')->whereIn('chave', array_map(fn ($c) => "pix.$c", self::CAMPOS))
                    ->pluck('valor', 'chave')->all();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $cfg = [];
        foreach (self::CAMPOS as $c) {
            $cfg[$c] = (string) ($salvo["pix.$c"] ?? config("pix.$c") ?? '');
        }
        if (! isset(self::TIPOS[$cfg['tipo']])) {
            $cfg['tipo'] = 'aleatoria';
        }

        return $cfg;
    }

    public static function salvarConfig(array $dados): void
    {
        foreach (self::CAMPOS as $c) {
            DB::table('configuracoes')->updateOrInsert(['chave' => "pix.$c"], [
                'valor' => (string) ($dados[$c] ?? ''), 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public static function pronto(): bool
    {
        $c = self::config();

        return $c['chave'] !== '' && $c['nome'] !== '';
    }

    /** Deixa a chave no formato que o Pix aceita (só dígitos no CPF/CNPJ, +55 no celular, e-mail minúsculo). */
    public static function normalizarChave(string $tipo, string $chave): string
    {
        $chave = trim($chave);

        return match ($tipo) {
            'cpf', 'cnpj' => preg_replace('/\D/', '', $chave),
            'email'       => mb_strtolower($chave),
            'telefone'    => (function () use ($chave) {
                $d = preg_replace('/\D/', '', $chave);
                if (strlen($d) <= 11) {
                    $d = '55' . $d;
                }

                return '+' . $d;
            })(),
            default       => mb_strtolower($chave),
        };
    }

    /** Mensagem de erro se a chave não combina com o tipo; null se está certa. */
    public static function erroChave(string $tipo, string $chave): ?string
    {
        return match ($tipo) {
            'cpf'       => Documento::valido($chave) && strlen($chave) === 11 ? null : 'CPF inválido.',
            'cnpj'      => Documento::valido($chave) && strlen($chave) === 14 ? null : 'CNPJ inválido.',
            'email'     => filter_var($chave, FILTER_VALIDATE_EMAIL) && strlen($chave) <= 77 ? null : 'E-mail inválido.',
            'telefone'  => preg_match('/^\+55\d{10,11}$/', $chave) ? null : 'Celular inválido. Use DDD + número.',
            'aleatoria' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $chave) ? null : 'A chave aleatória tem o formato 00000000-0000-0000-0000-000000000000.',
            default     => 'Tipo de chave inválido.',
        };
    }

    /* ================= QR Code ================= */

    public static function novoTxid(): string
    {
        return 'WF' . strtoupper(Str::random(14));
    }

    /**
     * Código "Pix copia e cola" com valor fixo (é o mesmo texto que vira o QR Code).
     * $cfg troca o recebedor (['chave', 'nome', 'cidade']): as mini lojas recebem na chave do dono da loja.
     */
    public static function copiaECola(float $valor, string $txid, ?array $cfg = null): string
    {
        $c = $cfg ?? self::config();

        $conta = self::campo('00', 'br.gov.bcb.pix') . self::campo('01', $c['chave']);

        $payload = self::campo('00', '01')
            . self::campo('26', $conta)
            . self::campo('52', '0000')
            . self::campo('53', '986')
            . self::campo('54', number_format($valor, 2, '.', ''))
            . self::campo('58', 'BR')
            . self::campo('59', self::texto($c['nome'], 25))
            . self::campo('60', self::texto($c['cidade'] !== '' ? $c['cidade'] : 'BRASIL', 15))
            . self::campo('62', self::campo('05', preg_replace('/[^A-Za-z0-9]/', '', $txid) ?: '***'))
            . '6304';

        return $payload . self::crc16($payload);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id . str_pad((string) strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
    }

    /** Nome e cidade vão sem acento, em maiúsculas e cortados no tamanho que o padrão permite. */
    private static function texto(string $s, int $max): string
    {
        $s = strtoupper(Str::ascii($s));
        $s = trim(preg_replace('/[^A-Z0-9 ]/', '', $s));

        return substr($s, 0, $max);
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido no fim do BR Code. */
    public static function crc16(string $s): string
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $crc ^= ord($s[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /* ================= Liberação do plano ================= */

    /**
     * Confirma o pagamento e libera o plano por um mês. Se a pessoa já tem o mesmo plano pago
     * até mais tarde (renovação antecipada), o mês é somado ao fim do período atual.
     */
    public static function liberar(PixPagamento $p): ?Carbon
    {
        return DB::transaction(function () use ($p) {
            // trava o pedido e confere de novo: dois cliques em "Confirmar" não somam dois meses
            $p = PixPagamento::whereKey($p->getKey())->lockForUpdate()->first();
            if (! $p || $p->status === 'confirmado' || $p->status === 'cancelado') {
                return null;
            }

            $usuario = DB::table('users')->where('id', $p->user_id)->lockForUpdate()->first();

            $base = now();
            if ($usuario && ($usuario->plano_slug ?? null) === $p->plano_slug && ! empty($usuario->plano_expira_em)) {
                $base = Carbon::parse($usuario->plano_expira_em)->max($base);
            }
            $ate = $base->copy()->addMonthNoOverflow()->endOfDay();

            DB::table('users')->where('id', $p->user_id)->update([
                'plano_slug'      => $p->plano_slug,
                'plano_expira_em' => $ate,
            ]);

            $p->status        = 'confirmado';
            $p->confirmado_em = now();
            $p->liberado_ate  = $ate;
            $p->save();

            return $ate;
        });
    }

    /** O plano pago termina em até 7 dias (ou já terminou): dá para renovar pagando de novo. */
    public static function podeRenovar($usuario): bool
    {
        $expira = $usuario->plano_expira_em ?? null;

        return ! $expira || Carbon::parse($expira)->subDays(7)->isPast();
    }
}
