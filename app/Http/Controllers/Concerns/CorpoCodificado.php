<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Corpo do pedido em base64 ({"b64": "..."}), para o mod_security da HostGator não responder 406 quando o texto
 * digitado parece SQL ou código (ex.: uma pauta com "select ... from"). O navegador codifica em
 * public/js/integracoes.js; JSON comum continua valendo.
 */
trait CorpoCodificado
{
    protected function decodificarCorpo(Request $request): void
    {
        $b64 = $request->input('b64');
        if (! is_string($b64) || $b64 === '' || strlen($b64) > 200000) {
            return;
        }
        $json = base64_decode($b64, true);
        $dados = $json !== false ? json_decode($json, true) : null;
        abort_unless(is_array($dados), 422, 'Pedido ilegível. Recarregue a página e tente de novo.');
        $request->request->remove('b64');
        $request->merge($dados);
        if ($request->isJson()) {
            $request->json()->remove('b64');
            foreach ($dados as $k => $v) {
                $request->json()->set($k, $v);
            }
        }
    }
}
