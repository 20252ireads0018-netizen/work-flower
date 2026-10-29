<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em todas as respostas e cookie de sessão só por HTTPS quando a página veio por HTTPS.
 *
 * A CSP não restringe scripts: as páginas usam Alpine (avaliação de expressões), scripts inline, AdSense e CDNs
 * do editor de código. Ela fecha o que não quebra nada: quem pode pôr o site num iframe (clickjacking),
 * plugins (<object>/<embed>) e a troca da base dos links (<base>).
 */
class CabecalhosSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        // roda antes do StartSession (middleware global), então o cookie de sessão já sai "Secure"
        if ($request->secure()) {
            config(['session.secure' => true]);
        }

        $resposta = $next($request);
        $h = $resposta->headers;

        $csp = "frame-ancestors 'self'; object-src 'none'; base-uri 'self'";
        if ($request->secure()) {
            $csp .= '; upgrade-insecure-requests';
            $h->set('Strict-Transport-Security', 'max-age=15552000');
        }
        // só em páginas HTML: num PDF servido pelo site, object-src 'none' pode travar o leitor de PDF do navegador
        $html = str_contains((string) $h->get('Content-Type'), 'text/html');
        if ($html && ! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', $csp);
        }
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), serial=(), hid=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $h->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            @header_remove('X-Powered-By');
        }

        return $resposta;
    }
}
