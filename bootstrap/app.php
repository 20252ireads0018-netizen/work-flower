<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/payments.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'subscribed' => \App\Http\Middleware\EnsureActiveSubscription::class,
        ]);

        // Webhooks do Asaas chegam sem sessão: a autenticação é o token no header "asaas-access-token".
        // No Laravel 13 o CSRF do grupo "web" é o PreventRequestForgery, então o withoutMiddleware
        // antigo (VerifyCsrfToken/ValidateCsrfToken) não excluía nada e o Asaas recebia 419.
        $middleware->validateCsrfTokens(except: [
            'webhooks/asaas',
            'api/webhooks/asaas',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
