<?php

namespace App\Providers;

use App\Services\Payments\AsaasGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, AsaasGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gravação automática dos quadros (estado, imagens, tarefas): folgado para quem usa de verdade
        // (o navegador junta as mudanças antes de gravar), mas segura um script enchendo o banco.
        RateLimiter::for('gravar', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        // Área renomeada pela conta (engrenagem › Áreas, App\Support\AreasConta): o título da página usa o nome dela
        \Illuminate\Support\Facades\View::composer(
            ['areas.corpo.index', 'areas.mente.index', 'areas.carteira.index', 'areas.diverso.index', 'areas.show'],
            function ($view) {
                $d = $view->getData();
                $slug = $d['slug'] ?? null;
                $padrao = is_string($slug) ? (\App\Models\Tarefa::AREAS[$slug]['nome'] ?? null) : null;
                if (! $padrao || ! auth()->check() || ! is_array($d['area'] ?? null) || ($d['area']['nome'] ?? null) !== $padrao) return;
                $nome = \App\Support\AreasConta::nome(auth()->user(), $slug);
                if ($nome !== $padrao) $view->with('area', ['nome' => $nome] + $d['area']);
            }
        );
    }
}
