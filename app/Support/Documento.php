<?php

namespace App\Support;

/** Utilidades de CPF/CNPJ (o Asaas exige um documento válido para criar a cobrança). */
class Documento
{
    /** Só os dígitos. */
    public static function limpar(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /** true se for um CPF (11) ou CNPJ (14) válido. */
    public static function valido(?string $valor): bool
    {
        $d = self::limpar($valor);

        return match (strlen($d)) {
            11      => self::cpfValido($d),
            14      => self::cnpjValido($d),
            default => false,
        };
    }

    public static function cpfValido(string $c): bool
    {
        if (strlen($c) !== 11 || preg_match('/^(\d)\1{10}$/', $c)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $c[$i] * (($t + 1) - $i);
            }
            $dv = ((10 * $soma) % 11) % 10;
            if ((int) $c[$t] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $c): bool
    {
        if (strlen($c) !== 14 || preg_match('/^(\d)\1{13}$/', $c)) {
            return false;
        }

        $pesos = [
            12 => [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            13 => [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($pesos as $pos => $lista) {
            $soma = 0;
            foreach ($lista as $i => $peso) {
                $soma += (int) $c[$i] * $peso;
            }
            $resto = $soma % 11;
            $dv    = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $c[$pos] !== $dv) {
                return false;
            }
        }

        return true;
    }
}
