<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas de administração (/admin/…): só a conta em User::ADMINS entra. As outras recebem 404,
 * como se a página não existisse. Toda rota /admin precisa dele (tests/Feature/AdminAcessoTest confere).
 */
class SoAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->eAdmin(), 404);

        return $next($request);
    }
}
