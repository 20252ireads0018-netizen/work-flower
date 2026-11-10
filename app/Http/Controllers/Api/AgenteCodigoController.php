<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AutomacaoController;
use App\Http\Controllers\Controller;
use App\Models\AutomacaoDispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Código de pareamento do instalador do app para Windows (AppInstalavelController::instaladorWindowsCompleto).
 *
 * O .cmd baixado por quem está logado leva um código de uso único, válido por 30 minutos (não o token):
 * o instalador troca o código pelo token do computador aqui, e o token fica só no computador (protegido pelo
 * Windows). Se o arquivo vazar depois de usado ou vencido, não serve para nada.
 */
class AgenteCodigoController extends Controller
{
    public const PREFIXO = 'wfc_';
    public const MINUTOS = 30;

    /** Gera um código para a conta (guardado só o hash, no cache). */
    public static function gerar(int $userId): string
    {
        $codigo = self::PREFIXO . Str::random(32);
        Cache::put(self::chave($codigo), ['user_id' => $userId], now()->addMinutes(self::MINUTOS));

        return $codigo;
    }

    private static function chave(string $codigo): string
    {
        return 'agente-codigo:' . hash('sha256', $codigo);
    }

    /** POST /api/agente-codigo { codigo, computador } → { token, dispositivo } */
    public function trocar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'codigo'     => ['required', 'string', 'max:80'],
            'computador' => ['nullable', 'string', 'max:60'],
        ]);

        // pull: o código vale uma vez só, mesmo que a troca falhe depois
        $par = str_starts_with($dados['codigo'], self::PREFIXO) ? Cache::pull(self::chave($dados['codigo'])) : null;
        if (! is_array($par) || empty($par['user_id'])) {
            return response()->json(['erro' => 'Código inválido, já usado ou vencido. Baixe o instalador de novo no site.'], 422);
        }

        $ativos = AutomacaoDispositivo::where('user_id', $par['user_id'])->whereNull('revogado_em')->count();
        if ($ativos >= AutomacaoController::MAX_DISPOSITIVOS) {
            return response()->json(['erro' => 'Limite de ' . AutomacaoController::MAX_DISPOSITIVOS . ' computadores. Revogue um em Automações no site.'], 409);
        }

        $nome = trim((string) ($dados['computador'] ?? '')) ?: 'Computador';
        [$d, $token] = AutomacaoDispositivo::parear((int) $par['user_id'], mb_substr($nome . ' (app)', 0, 60));

        return response()->json(['token' => $token, 'dispositivo' => $d->nome]);
    }
}
