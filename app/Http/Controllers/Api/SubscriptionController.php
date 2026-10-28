<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PaymentGatewayException;
use App\Exceptions\SubscriptionException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service)
    {
    }

    /** GET /api/subscription — assinatura mais recente do usuário. */
    public function show(Request $request): JsonResponse
    {
        $subscription = $request->user()->latestSubscription();

        return response()->json(['data' => $subscription ? $this->present($subscription->load('payments')) : null]);
    }

    /** POST /api/subscription — { plan: "pro", billing_type?: "UNDEFINED", cpf_cnpj?: "..." } */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'slug')->where('active', true)],
            'billing_type' => ['nullable', Rule::in(['UNDEFINED', 'PIX', 'BOLETO', 'CREDIT_CARD'])],
            'cpf_cnpj' => ['nullable', 'string', 'regex:/^(\D*\d\D*){11}$|^(\D*\d\D*){14}$/'],
        ], [
            'cpf_cnpj.regex' => 'CPF ou CNPJ inválido.',
        ]);

        $plan = Plan::where('slug', $data['plan'])->firstOrFail();

        try {
            $subscription = $this->service->subscribe(
                $request->user(),
                $plan,
                $data['billing_type'] ?? 'UNDEFINED',
                $data['cpf_cnpj'] ?? null,
            );
        } catch (SubscriptionException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($subscription)], 201);
    }

    /** DELETE /api/subscription — cancela a assinatura atual (acesso segue até o fim do período pago). */
    public function destroy(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscriptions()
            ->whereIn('status', Subscription::LIVE)
            ->latest('id')
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Nenhuma assinatura para cancelar.'], 404);
        }

        try {
            $subscription = $this->service->cancel($subscription);
        } catch (PaymentGatewayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($subscription->load('payments'))]);
    }

    /** GET /api/subscription/payments — histórico de cobranças. */
    public function payments(Request $request): JsonResponse
    {
        $subscription = $request->user()->latestSubscription();

        $payments = $subscription
            ? $subscription->payments()->orderByDesc('due_date')->get()
            : collect();

        return response()->json(['data' => $payments]);
    }

    private function present(Subscription $subscription): array
    {
        $subscription->loadMissing('plan');
        $pending = $subscription->relationLoaded('payments')
            ? $subscription->payments->where('status', 'pending')->sortBy('due_date')->first()
            : null;

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'has_access' => $subscription->grantsAccess(),
            'plan' => $subscription->plan->only(['name', 'slug']),
            'amount' => $subscription->amount,
            'billing_type' => $subscription->billing_type,
            'trial_ends_at' => $subscription->trial_ends_at,
            'current_period_end' => $subscription->current_period_end,
            'canceled_at' => $subscription->canceled_at,
            // Link da fatura (Pix/boleto/cartão) para redirecionar o cliente a pagar.
            'payment_url' => $pending?->invoice_url,
            'payments' => $subscription->relationLoaded('payments') ? $subscription->payments : [],
        ];
    }
}

