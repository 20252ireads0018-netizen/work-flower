<?php

namespace App\Providers;

use App\Models\Automacao;
use App\Models\Tarefa;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Automações no computador: rotas (página e API do agente), limite de pedidos do agente
 * e os gatilhos de tarefa criada/concluída. Fica num provider próprio para não mexer no routes/web.php.
 */
class AutomacaoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // o agente confere a cada 30 s; 60 pedidos por minuto por IP sobra para vários computadores na mesma rede
        RateLimiter::for('agente', fn (Request $request) => Limit::perMinute(60)->by('agente|' . $request->ip()));

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(base_path('routes/automacoes.php'));
            Route::prefix('api/agente')->name('agente.')
                ->middleware(['throttle:agente', \App\Http\Middleware\AgenteToken::class, \Illuminate\Routing\Middleware\SubstituteBindings::class])
                ->group(base_path('routes/agente.php'));
            // instalador do app (Windows): troca o código de uso único pelo token do computador (sem sessão nem CSRF)
            Route::post('api/agente-codigo', [\App\Http\Controllers\Api\AgenteCodigoController::class, 'trocar'])
                ->middleware('throttle:10,1')->name('agente.codigo');
        }

        Tarefa::created(function (Tarefa $t) {
            rescue(fn () => Automacao::aoMudarTarefa($t, 'criada'), report: true);
        });
        Tarefa::updated(function (Tarefa $t) {
            if ($t->wasChanged('status') && $t->status === 'concluida') {
                rescue(fn () => Automacao::aoMudarTarefa($t, 'concluida'), report: true);
            }
        });
    }
}
