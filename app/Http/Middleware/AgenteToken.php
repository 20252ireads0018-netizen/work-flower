<?php

namespace App\Http\Middleware;

use App\Models\AutomacaoDispositivo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica o agente do Windows pelo token do aparelho (cabeçalho "Authorization: Bearer wfa_..."
 * ou "X-Agente-Token"). Não usa sessão nem cookie. O aparelho fica em $request->attributes('dispositivo').
 */
class AgenteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-Agente-Token');
        $disp = AutomacaoDispositivo::peloToken($token);

        if (! $disp) {
            return response()->json(['erro' => 'Token inválido ou revogado. Gere um novo em Automações no site.'], 401);
        }

        $versao = mb_substr((string) $request->header('X-Agente-Versao', ''), 0, 20) ?: null;
        // grava o contato no máximo a cada 20 s, para não escrever no banco a cada pedido
        if (! $disp->ultimo_contato_em || $disp->ultimo_contato_em->lt(now()->subSeconds(20)) || $disp->versao_agente !== $versao) {
            $disp->forceFill(['ultimo_contato_em' => now(), 'versao_agente' => $versao])->save();
        }

        $request->attributes->set('dispositivo', $disp);

        return $next($request);
    }
}
