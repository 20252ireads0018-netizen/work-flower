<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente mínimo da API v3 do Asaas (sandbox ou produção, conforme ASAAS_ENV). */
class AsaasCliente
{
    private function http(): PendingRequest
    {
        $chave = config('asaas.api_key');
        if (! $chave || str_starts_with($chave, 'COLE_AQUI')) {
            throw new RuntimeException('ASAAS_API_KEY não configurada no .env.');
        }

        // a chave em config/asaas.php chama-se "environment" (antes lia "asaas.env", que não existe)
        $base = config('asaas.environment') === 'production'
            ? config('asaas.urls.production', 'https://api.asaas.com/v3')
            : config('asaas.urls.sandbox', 'https://api-sandbox.asaas.com/v3');

        return Http::baseUrl($base)
            ->withHeaders(['access_token' => $chave, 'User-Agent' => 'WorkFlower'])
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }

    private function resposta($r): array
    {
        if ($r->failed()) {
            $msg = $r->json('errors.0.description') ?: ('Asaas respondeu ' . $r->status());
            throw new RuntimeException($msg);
        }

        return $r->json() ?? [];
    }

    /**
     * Reaproveita o cliente com o mesmo e-mail ou cria um novo. Retorna o id (cus_...).
     * Se o cliente já existia sem CPF/CNPJ, completa o cadastro (o Asaas exige o documento para cobrar).
     */
    public function cliente(string $nome, string $email, ?string $cpfCnpj = null): string
    {
        $cpfCnpj = $cpfCnpj ? preg_replace('/\D/', '', $cpfCnpj) : null;

        $achado = $this->resposta($this->http()->get('/customers', ['email' => $email, 'limit' => 1]));
        $existente = $achado['data'][0] ?? null;

        if (! empty($existente['id'])) {
            if ($cpfCnpj && empty($existente['cpfCnpj'])) {
                $this->resposta($this->http()->post("/customers/{$existente['id']}", ['cpfCnpj' => $cpfCnpj]));
            }

            return $existente['id'];
        }

        $dados = ['name' => $nome, 'email' => $email, 'notificationDisabled' => false];
        if ($cpfCnpj) {
            $dados['cpfCnpj'] = $cpfCnpj;
        }

        return $this->resposta($this->http()->post('/customers', $dados))['id'];
    }

    /** Assinatura mensal; o cliente escolhe Pix, boleto ou cartão na página da fatura. */
    public function assinatura(string $clienteId, float $valor, string $descricao, string $referencia, int $diasGratis = 0): array
    {
        return $this->resposta($this->http()->post('/subscriptions', [
            'customer'          => $clienteId,
            'billingType'       => 'UNDEFINED',
            'value'             => round($valor, 2),
            'nextDueDate'       => now()->addDays(max(0, $diasGratis))->toDateString(),
            'cycle'             => 'MONTHLY',
            'description'       => $descricao,
            'externalReference' => $referencia,
        ]));
    }

    /** Dados de uma assinatura (status, externalReference, deleted...). null se ela não existe mais. */
    public function buscarAssinatura(string $assinaturaId): ?array
    {
        $r = $this->http()->get("/subscriptions/{$assinaturaId}");
        if ($r->status() === 404) {
            return null;
        }

        return $this->resposta($r);
    }

    /** Encerra uma assinatura no Asaas (as cobranças futuras param). Já removida não é erro. */
    public function cancelarAssinatura(string $assinaturaId): void
    {
        $r = $this->http()->delete("/subscriptions/{$assinaturaId}");
        if ($r->status() !== 404) {
            $this->resposta($r);
        }
    }

    /** Link da fatura de uma cobrança ainda em aberto (pendente ou vencida) da assinatura, ou null. */
    public function linkCobrancaAberta(string $assinaturaId): ?string
    {
        foreach (['PENDING', 'OVERDUE'] as $status) {
            $j = $this->resposta($this->http()->get("/subscriptions/{$assinaturaId}/payments", ['status' => $status, 'limit' => 1]));
            if (! empty($j['data'][0]['invoiceUrl'])) {
                return $j['data'][0]['invoiceUrl'];
            }
        }

        return null;
    }

    /** Link da fatura (tela de pagamento) da primeira cobrança da assinatura. */
    public function linkPagamento(string $assinaturaId): ?string
    {
        // a primeira cobrança pode levar um instante para existir
        for ($i = 0; $i < 4; $i++) {
            $j = $this->resposta($this->http()->get("/subscriptions/{$assinaturaId}/payments", ['limit' => 1]));
            if (! empty($j['data'][0]['invoiceUrl'])) {
                return $j['data'][0]['invoiceUrl'];
            }
            usleep(700_000);
        }

        return null;
    }
}
