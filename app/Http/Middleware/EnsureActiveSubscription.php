<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso nas rotas:
 *   ->middleware('subscribed')            // qualquer plano com acesso
 *   ->middleware('subscribed:pro,premium') // s�� estes planos (slugs)
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next, string ...$plans): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSubscribed($plans ?: null)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Assinatura ativa necess��ria.'], 402);
            }

            // url() inclui a subpasta (/work-flower) e aceita URL completa tamb��m.
            return redirect(url(config('asaas.redirect_unsubscribed', '/planos')));
        }

        return $next($request);
    }
}
