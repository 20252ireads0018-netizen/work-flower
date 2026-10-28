<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubscription(): Subscription
    {
        $user = User::factory()->create();
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro', 'price' => 99.90]);

        return $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => Subscription::PENDING,
            'billing_type' => 'UNDEFINED',
            'amount' => 99.90,
            'gateway_subscription_id' => 'sub_test_1',
        ]);
    }

    private function payload(string $event, string $status, string $eventId): array
    {
        return [
            'id' => $eventId,
            'event' => $event,
            'payment' => [
                'id' => 'pay_test_1',
                'subscription' => 'sub_test_1',
                'value' => 99.90,
                'status' => $status,
                'billingType' => 'PIX',
                'dueDate' => now()->toDateString(),
                'invoiceUrl' => 'https://sandbox.asaas.com/i/test',
            ],
        ];
    }

    public function test_rejeita_token_invalido(): void
    {
        config(['asaas.webhook_token' => 'segredo']);

        $this->postJson('/api/webhooks/asaas', [], ['asaas-access-token' => 'errado'])->assertStatus(401);
    }

    public function test_pagamento_recebido_ativa_a_assinatura(): void
    {
        config(['asaas.webhook_token' => 'segredo']);
        $subscription = $this->makeSubscription();
        $headers = ['asaas-access-token' => 'segredo'];

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'RECEIVED', 'evt_1'), $headers)
            ->assertOk();

        $subscription->refresh();
        $this->assertSame(Subscription::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->grantsAccess());
        $this->assertSame(1, $subscription->payments()->where('status', 'paid')->count());

        // Reenvio do mesmo evento é ignorado.
        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'RECEIVED', 'evt_1'), $headers)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);
    }

    public function test_pagamento_vencido_marca_past_due(): void
    {
        config(['asaas.webhook_token' => 'segredo']);
        $subscription = $this->makeSubscription();

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'OVERDUE', 'evt_2'), ['asaas-access-token' => 'segredo'])
            ->assertOk();

        $this->assertSame(Subscription::PAST_DUE, $subscription->fresh()->status);
    }
}

