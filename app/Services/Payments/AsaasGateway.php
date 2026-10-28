<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasGateway implements PaymentGateway
{
    public function createCustomer(array $data): string
    {
        $response = $this->send('POST', '/customers', [
            'name' => $data['name'],
            'email' => $data['email'],
            'cpfCnpj' => $data['cpf_cnpj'],
            'externalReference' => (string) ($data['external_reference'] ?? ''),
        ]);

        return $response['id'];
    }

    public function createSubscription(array $data): array
    {
        return $this->send('POST', '/subscriptions', [
            'customer' => $data['customer'],
            'billingType' => $data['billing_type'], // UNDEFINED | PIX | BOLETO | CREDIT_CARD
            'value' => (float) $data['value'],
            'nextDueDate' => $data['next_due_date'], // Y-m-d
            'cycle' => 'MONTHLY',
            'description' => $data['description'] ?? null,
            'externalReference' => (string) ($data['external_reference'] ?? ''),
        ]);
    }

    public function cancelSubscription(string $gatewaySubscriptionId): void
    {
        try {
            $this->send('DELETE', "/subscriptions/{$gatewaySubscriptionId}");
        } catch (PaymentGatewayException $e) {
            // Já removida no Asaas: cancelar de novo não é erro.
            if ($e->status !== 404) {
                throw $e;
            }
        }
    }

    public function listSubscriptionPayments(string $gatewaySubscriptionId): array
    {
        $response = $this->send('GET', "/subscriptions/{$gatewaySubscriptionId}/payments", ['limit' => 100]);

        return $response['data'] ?? [];
    }

    private function http(): PendingRequest
    {
        $key = config('asaas.api_key');

        if (! $key) {
            throw new PaymentGatewayException('ASAAS_API_KEY não configurada.');
        }

        $baseUrl = config('asaas.urls.' . config('asaas.environment', 'sandbox'));

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'access_token' => $key,
                'User-Agent' => config('app.name', 'Laravel'),
            ])
            ->acceptJson()
            ->timeout(30);
    }

    private function send(string $method, string $uri, array $data = []): array
    {
        $options = [];

        if ($data !== []) {
            $options = $method === 'GET' ? ['query' => $data] : ['json' => $data];
        }

        try {
            $response = $this->http()->send($method, $uri, $options);
        } catch (ConnectionException $e) {
            Log::error('Asaas: falha de conexão', ['uri' => $uri, 'message' => $e->getMessage()]);

            throw new PaymentGatewayException('Não foi possível conectar ao gateway de pagamento.');
        }

        if ($response->failed()) {
            $errors = $response->json('errors') ?? [];

            Log::warning('Asaas: erro na API', [
                'uri' => $uri,
                'status' => $response->status(),
                'errors' => $errors,
            ]);

            throw new PaymentGatewayException(
                $errors[0]['description'] ?? 'Erro ao comunicar com o gateway de pagamento.',
                $response->status(),
                $errors,
            );
        }

        return $response->json() ?? [];
    }
}

