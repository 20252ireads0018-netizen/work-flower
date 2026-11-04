<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * "throttle:N,M" com um contador por rota. O do Laravel usa só a pessoa (ou o IP) como chave, então todas as
 * rotas com throttle:N,M dividiam o mesmo contador: 10 gravações em um minuto num lugar bloqueavam (429) outra
 * tela com limite 10/min. Aqui a chave ganha o nome da rota (ou o endereço dela). Limites nomeados
 * (throttle:gravar etc.) não passam por aqui e continuam como estão.
 */
class LimitePorRota extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $rota = $request->route();
        $qual = $rota ? ($rota->getName() ?: implode('|', $rota->methods()) . ' ' . $rota->uri()) : '';

        return sha1(parent::resolveRequestSignature($request) . '|' . $qual);
    }
}
