<?php

namespace App\Services\Payments;

/** Contrato do gateway: para trocar de Asaas para Stripe/Mercado Pago, basta outra implementação. */
interface PaymentGateway
{
    /** Cria o cliente no gateway e devolve o ID dele. */
    public function createCustomer(array $data): string;

    /** Cria a assinatura mensal e devolve o payload do gateway (com a chave "id"). */
    public function createSubscription(array $data): array;

    public function cancelSubscription(string $gatewaySubscriptionId): void;

    /** Lista as cobranças geradas pela assinatura. */
    public function listSubscriptionPayments(string $gatewaySubscriptionId): array;
}

