<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Recebe os eventos do Asaas e libera ou retira o plano do usuário. */
class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $esperado = (string) config('asaas.webhook_token');
        $recebido = (string) $request->header('asaas-access-token');

        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $dados = $request->all();

        if (config('asaas.queue_webhooks')) {
            dispatch(fn () => self::processar($dados));
        } else {
            try {
                self::processar($dados);
            } catch (\Throwable $e) {
                report($e); // responde 200 mesmo assim para o Asaas não pausar a fila de webhooks
            }
        }

        return response()->json(['ok' => true]);
    }

    public static function processar(array $dados): void
    {
        $evento = (string) ($dados['event'] ?? '');
        $pag    = $dados['payment'] ?? [];
        $assin  = $dados['subscription'] ?? [];

        $subId = $pag['subscription'] ?? $assin['id'] ?? null;
        $ref   = $pag['externalReference'] ?? $assin['externalReference'] ?? null;

        // "user:12:plano:basico"
        $userId = null;
        $slug   = 'basico';
        if ($ref && preg_match('/^user:(\d+):plano:([a-z0-9-]+)$/', $ref, $m)) {
            $userId = (int) $m[1];
            $slug   = $m[2];
        }

        $usuario = DB::table('users')
            ->when($userId, fn ($q) => $q->where('id', $userId))
            ->when(! $userId && $subId, fn ($q) => $q->where('asaas_subscription_id', $subId))
            ->when(! $userId && ! $subId, fn ($q) => $q->whereRaw('1 = 0'))
            ->first();

        if (! $usuario) {
            return;
        }

        switch ($evento) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                // paga um mês: libera até o próximo vencimento (a tolerância é aplicada em Planos::slugDe)
                $ate = ! empty($pag['dueDate']) ? \Carbon\Carbon::parse($pag['dueDate']) : now();
                $ate = $ate->max(now())->addMonth()->endOfDay();
                DB::table('users')->where('id', $usuario->id)->update([
                    'plano_slug'            => $slug,
                    'plano_expira_em'       => $ate,
                    'asaas_subscription_id' => $subId ?: $usuario->asaas_subscription_id,
                ]);
                break;

            case 'PAYMENT_REFUNDED':
            case 'PAYMENT_CHARGEBACK_REQUESTED':
            case 'SUBSCRIPTION_DELETED':
            case 'SUBSCRIPTION_INACTIVATED':
                // cancelou/estornou: volta para o gratuito
                DB::table('users')->where('id', $usuario->id)->update([
                    'plano_slug'      => 'gratuito',
                    'plano_expira_em' => null,
                ]);
                break;

            // PAYMENT_OVERDUE: nada a fazer; o acesso cai sozinho quando plano_expira_em + tolerância passar
        }
    }
}
