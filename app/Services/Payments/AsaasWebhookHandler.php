<?php

namespace App\Services\Payments;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;

class AsaasWebhookHandler
{
    public function __construct(private SubscriptionService $subscriptions)
    {
    }

    public function handle(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');

        if (str_starts_with($event, 'PAYMENT_') && ! empty($payload['payment'])) {
            $this->handlePayment($event, $payload['payment']);

            return;
        }

        if (str_starts_with($event, 'SUBSCRIPTION_') && ! empty($payload['subscription']['id'])) {
            $this->handleSubscription($event, $payload['subscription']);
        }
    }

    private function handlePayment(string $event, array $payment): void
    {
        $remoteId = $payment['subscription'] ?? null;

        // Cobrança avulsa (não veio de assinatura): não é assunto deste sistema.
        if (! $remoteId) {
            return;
        }

        $subscription = Subscription::where('gateway_subscription_id', $remoteId)->first();

        if (! $subscription) {
            Log::info('Asaas webhook: assinatura desconhecida, ignorando.', ['subscription' => $remoteId, 'event' => $event]);

            return;
        }

        $this->subscriptions->applyPayment($subscription, $payment, $event);
    }

    private function handleSubscription(string $event, array $remote): void
    {
        if (! in_array($event, ['SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED'], true)) {
            return;
        }

        $subscription = Subscription::where('gateway_subscription_id', $remote['id'])->first();

        if ($subscription) {
            $this->subscriptions->markCanceledFromGateway($subscription);
        }
    }
}

