<?php

namespace App\Support;

/**
 * Códigos de 6 dígitos que mudam a cada 30 segundos (TOTP, RFC 6238), os mesmos do Google Authenticator,
 * Microsoft Authenticator, Authy, 1Password etc. Feito aqui (sem pacote) porque a hospedagem não tem shell para o composer.
 */
class Totp
{
    public const DIGITOS = 6;

    public const PERIODO = 30;

    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Segredo novo em base32 (20 bytes = 160 bits, o tamanho recomendado para HMAC-SHA1). */
    public static function gerarSegredo(): string
    {
        return self::base32(random_bytes(20));
    }

    /** Endereço otpauth:// que o app lê pelo QR Code. */
    public static function uri(string $segredo, string $conta, string $emissor = 'Work Flower'): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor) . ':' . rawurlencode($conta)
            . '?secret=' . $segredo . '&issuer=' . rawurlencode($emissor)
            . '&algorithm=SHA1&digits=' . self::DIGITOS . '&period=' . self::PERIODO;
    }

    /** Código do passo de tempo informado (passo = floor(tempo / 30)). */
    public static function codigo(string $segredo, int $passo): string
    {
        $chave = self::debase32($segredo);
        $hash = hash_hmac('sha1', pack('J', $passo), $chave, true);
        $pos = ord($hash[19]) & 0x0f;
        $num = ((ord($hash[$pos]) & 0x7f) << 24) | (ord($hash[$pos + 1]) << 16) | (ord($hash[$pos + 2]) << 8) | ord($hash[$pos + 3]);

        return str_pad((string) ($num % (10 ** self::DIGITOS)), self::DIGITOS, '0', STR_PAD_LEFT);
    }

    public static function passoAtual(?int $agora = null): int
    {
        return intdiv($agora ?? time(), self::PERIODO);
    }

    /**
     * Confere o código aceitando 1 passo antes/depois (relógio do celular um pouco fora).
     * Devolve o passo que bateu, ou null. Passos até $ultimoUsado são recusados (o mesmo código não serve duas vezes).
     */
    public static function verificar(string $segredo, string $codigo, ?int $ultimoUsado = null, ?int $agora = null): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo);
        if (! preg_match('/^\d{' . self::DIGITOS . '}$/', $codigo)) {
            return null;
        }
        $atual = self::passoAtual($agora);
        foreach ([0, -1, 1] as $d) {
            $passo = $atual + $d;
            if ($ultimoUsado !== null && $passo <= $ultimoUsado) {
                continue;
            }
            if (hash_equals(self::codigo($segredo, $passo), $codigo)) {
                return $passo;
            }
        }

        return null;
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 5) as $grupo) {
            $saida .= self::ALFABETO[bindec(str_pad($grupo, 5, '0'))];
        }

        return $saida;
    }

    private static function debase32(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto));
        $bits = '';
        foreach (str_split($texto) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
        }
        $saida = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $saida .= chr(bindec($byte));
            }
        }

        return $saida;
    }
}
