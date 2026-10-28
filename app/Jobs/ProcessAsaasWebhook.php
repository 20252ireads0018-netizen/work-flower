<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\Payments\AsaasWebhookHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessAsaasWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $webhookEventId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(AsaasWebhookHandler $handler): void
    {
        $event = WebhookEvent::find($this->webhookEventId);

        if (! $event || $event->processed_at) {
            return;
        }

        $event->increment('attempts');

        try {
            $handler->handle($event->payload);
            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['error' => Str::limit($e->getMessage(), 1000)]);

            throw $e; // sem fila: o controller devolve 500 e o Asaas reenvia; com fila: retry com backoff.
        }
    }
}

