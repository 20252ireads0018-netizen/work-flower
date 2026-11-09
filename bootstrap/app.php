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
            // throttle:N,M com um contador por rota (o padrão do Laravel dividia um só entre todas as rotas)
            'throttle'   => \App\Http\Middleware\LimitePorRota::class,
        ]);

        // cabeçalhos de segurança e cookie de sessão "Secure" em HTTPS (antes do StartSession)
        $middleware->prepend(\App\Http\Middleware\CabecalhosSeguranca::class);

        // WAF: recusa POST/PUT/PATCH/DELETE com assinaturas óbvias de SQL injection/XSS (isenta o JSON dos quadros)
        $middleware->web(append: [\App\Http\Middleware\FiltroAtaques::class]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
