<?php

use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    // Público: lista de planos.
    Route::get('plans', [PlanController::class, 'index'])->middleware('web');

    // Autenticado pela sessão do seu login. POST/DELETE exigem o header X-CSRF-TOKEN.
    // (Se usar Sanctum com token, troque ['web', 'auth'] por 'auth:sanctum'.)
    Route::middleware(['web', 'auth'])->group(function () {
        Route::get('subscription', [SubscriptionController::class, 'show']);
        Route::get('subscription/payments', [SubscriptionController::class, 'payments']);
        // POST/DELETE desligados: a tela de planos assina por AssinaturaController (/assinar/{slug}) e nenhuma tela
        // usa esta API. Ligados, deixavam criar assinaturas no Asaas para planos "em breve" e encher a conta de clientes.
        // Route::post('subscription', [SubscriptionController::class, 'store'])->middleware('throttle:10,1');
        // Route::delete('subscription', [SubscriptionController::class, 'destroy']);
    });

    // Webhook do Asaas: grupo "api" (sem sessão e sem CSRF), protegido pelo token do header.
    Route::post('webhooks/asaas', AsaasWebhookController::class)->middleware('api');
});

