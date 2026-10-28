<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Exceptions\SubscriptionException;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SubscriptionService
{
    public function __construct(private PaymentGateway $gateway)
    {
    }

    public function subscribe(User $user, Plan $plan, string $billingType = 'UNDEFINED', ?string $cpfCnpj = null): Subscription
    {
        $live = $user->subscriptions()->whereIn('status', Subscription::LIVE)->latest('id')->first();

        if ($live) {
            // Mesmo plano ainda pendente: devolve a mesma assinatura (ela já tem o link de pagamento).
            if ($live->status === Subscription::PENDING && $live->plan_id === $plan->id) {
                return $live->load(['plan', 'payments']);
            }

            throw new SubscriptionException('Você já possui uma assinatura ativa ou pendente. Cancele-a antes de assinar outro plano.', 409);
        }

        if ($cpfCnpj) {
            $user->cpf_cnpj = preg_replace('/\D/', '', $cpfCnpj);
        }

        if (! $user->cpf_cnpj) {
            throw new SubscriptionException('Informe o CPF ou CNPJ para gerar a cobrança.', 422);
        }

        if (! $user->asaas_customer_id) {
            $user->asaas_customer_id = $this->gateway->createCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'cpf_cnpj' => $user->cpf_cnpj,
                'external_reference' => $user->id,
            ]);
        }

        $user->save();

        $subscription = $user->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => Subscription::PENDING,
            'billing_type' => $billingType,
            'amount' => $plan->price,
            'gateway' => 'asaas',
            'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
        ]);

        try {
            $remote = $this->gateway->createSubscription([
                'customer' => $user->asaas_customer_id,
                'billing_type' => $billingType,
                'value' => $plan->price,
                'next_due_date' => now()->addDays($plan->trial_days)->toDateString(),
                'description' => "Plano {$plan->name}",
                'external_reference' => $subscription->id,
            ]);
        } catch (Throwable $e) {
            $subscription->delete();

            throw $e;
        }

        $subscription->update(['gateway_subscription_id' => $remote['id']]);

        $this->syncPayments($subscription);

        return $subscription->fresh(['plan', 'payments']);
    }

    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status === Subscription::CANCELED) {
            return $subscription;
        }

        if ($subscription->gateway_subscription_id) {
            $this->gateway->cancelSubscription($subscription->gateway_subscription_id);
        }

        $subscription->update(['status' => Subscription::CANCELED, 'canceled_at' => now()]);

        return $subscription;
    }

    /** Busca as cobranças no gateway. Se falhar, o webhook preenche depois. */
    public function syncPayments(Subscription $subscription): void
    {
        if (! $subscription->gateway_subscription_id) {
            return;
        }

        try {
            foreach ($this->gateway->listSubscriptionPayments($subscription->gateway_subscription_id) as $payment) {
                $this->applyPayment($subscription, $payment);
            }
        } catch (PaymentGatewayException $e) {
            Log::warning('Não foi possível sincronizar cobranças agora.', ['subscription' => $subscription->id]);
        }
    }

    /** Grava/atualiza uma cobrança vinda do gateway (API ou webhook) e reflete na assinatura. */
    public function applyPayment(Subscription $subscription, array $data, ?string $event = null): Payment
    {
        $status = $this->mapPaymentStatus($data['status'] ?? '', $event);

        $payment = Payment::firstOrNew(['gateway_payment_id' => $data['id']]);

        // Evento atrasado/fora de ordem não pode "desfazer" um pagamento já confirmado.
        if ($payment->exists && $payment->status === Payment::PAID
            && in_array($status, [Payment::PENDING, Payment::OVERDUE], true)) {
            $status = Payment::PAID;
        }

        $payment->fill([
            'subscription_id' => $subscription->id,
            'amount' => $data['value'] ?? $subscription->amount,
            'status' => $status,
            'billing_type' => $data['billingType'] ?? $payment->billing_type,
            'due_date' => $data['dueDate'] ?? $payment->due_date,
            'paid_at' => $status === Payment::PAID
                ? ($data['confirmedDate'] ?? $data['paymentDate'] ?? $payment->paid_at ?? now())
                : $payment->paid_at,
            'invoice_url' => $data['invoiceUrl'] ?? $payment->invoice_url,
        ])->save();

        $this->reflectOnSubscription($subscription, $payment);

        return $payment;
    }

    /** Assinatura removida/inativada no gateway. */
    public function markCanceledFromGateway(Subscription $subscription): void
    {
        if ($subscription->status === Subscription::CANCELED) {
            return;
        }

        $subscription->update(['status' => Subscription::CANCELED, 'canceled_at' => $subscription->canceled_at ?? now()]);
    }

    private function reflectOnSubscription(Subscription $subscription, Payment $payment): void
    {
        switch ($payment->status) {
            case Payment::PAID:
                $end = Carbon::parse($payment->due_date ?? now())->addMonthNoOverflow()->endOfDay();
                $current = $subscription->current_period_end;

                $subscription->current_period_end = ($current && $current->greaterThan($end)) ? $current : $end;

                // Cancelada que pagou fatura já emitida: só estende o período, não reativa.
                if ($subscription->status !== Subscription::CANCELED) {
                    $subscription->status = Subscription::ACTIVE;
                }
                break;

            case Payment::OVERDUE:
                if (in_array($subscription->status, [Subscription::ACTIVE, Subscription::PENDING], true)) {
                    $subscription->status = Subscription::PAST_DUE;
                }
                break;

            case Payment::REFUNDED:
            case Payment::CHARGEBACK:
                if ($subscription->status !== Subscription::CANCELED) {
                    $subscription->status = Subscription::SUSPENDED;
                }
                break;
        }

        if ($subscription->isDirty()) {
            $subscription->save();
        }
    }

    private function mapPaymentStatus(string $asaasStatus, ?string $event): string
    {
        if ($event === 'PAYMENT_DELETED') {
            return Payment::CANCELED;
        }

        return match ($asaasStatus) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => Payment::PAID,
            'OVERDUE' => Payment::OVERDUE,
            'REFUNDED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => Payment::REFUNDED,
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => Payment::CHARGEBACK,
            default => Payment::PENDING,
        };
    }
}

