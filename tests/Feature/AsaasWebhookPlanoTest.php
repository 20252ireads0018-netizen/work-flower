<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Planos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Webhook do Asaas que o sistema usa de fato (POST /webhooks/asaas, colunas users.plano_*). */
class AsaasWebhookPlanoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'segredo-de-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['asaas.webhook_token' => self::TOKEN]);
    }

    private function usuario(array $extra = []): User
    {
        $u = User::factory()->create(['created_at' => now()->subDays(60)]); // fora do teste grátis
        DB::table('users')->where('id', $u->id)->update($extra + ['plano_slug' => 'gratuito']);

        return $u->fresh();
    }

    private function enviar(array $dados, string $token = self::TOKEN)
    {
        return $this->postJson('/webhooks/asaas', $dados, ['asaas-access-token' => $token]);
    }

    private function pagamento(User $u, string $sub, string $evento = 'PAYMENT_RECEIVED', string $slug = 'basico'): array
    {
        return ['event' => $evento, 'payment' => [
            'id' => 'pay_' . uniqid(), 'subscription' => $sub, 'status' => 'RECEIVED',
            'dueDate' => now()->toDateString(), 'externalReference' => "user:{$u->id}:plano:{$slug}",
        ]];
    }

    private function cancelamento(User $u, string $sub, string $slug = 'basico'): array
    {
        return ['event' => 'SUBSCRIPTION_DELETED', 'subscription' => ['id' => $sub, 'externalReference' => "user:{$u->id}:plano:{$slug}"]];
    }

    public function test_sem_csrf_e_com_token_errado_responde_401(): void
    {
        $this->enviar(['event' => 'PAYMENT_RECEIVED'], 'errado')->assertStatus(401);
    }

    public function test_pagamento_da_assinatura_atual_libera_o_plano(): void
    {
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual']);

        $this->enviar($this->pagamento($u, 'sub_atual'))->assertOk();

        $u = $u->fresh();
        $this->assertSame('basico', $u->plano_slug);
        $this->assertTrue(now()->addDays(25)->lessThan($u->plano_expira_em));
        $this->assertSame('basico', Planos::slugPago($u));
    }

    public function test_primeiro_pagamento_sem_assinatura_gravada_registra_a_assinatura(): void
    {
        $u = $this->usuario();

        $this->enviar($this->pagamento($u, 'sub_nova'))->assertOk();

        $this->assertSame('sub_nova', $u->fresh()->asaas_subscription_id);
        $this->assertSame('basico', $u->fresh()->plano_slug);
    }

    public function test_cancelar_assinatura_antiga_nao_derruba_o_plano(): void
    {
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual', 'plano_slug' => 'premium', 'plano_expira_em' => now()->addYear()]);

        $this->enviar($this->cancelamento($u, 'sub_duplicada_antiga'))->assertOk();
        $this->enviar($this->pagamento($u, 'sub_duplicada_antiga'))->assertOk();
        $this->enviar($this->pagamento($u, 'sub_duplicada_antiga', 'PAYMENT_REFUNDED'))->assertOk();

        $u = $u->fresh();
        $this->assertSame('premium', $u->plano_slug);
        $this->assertSame('sub_atual', $u->asaas_subscription_id);
    }

    public function test_cancelar_a_assinatura_atual_mantem_o_periodo_ja_pago(): void
    {
        $ate = now()->addDays(20)->startOfSecond();
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual', 'plano_slug' => 'basico', 'plano_expira_em' => $ate]);

        $this->enviar($this->cancelamento($u, 'sub_atual'))->assertOk();

        $this->assertSame('basico', $u->fresh()->plano_slug);
        $this->assertSame('basico', Planos::slugPago($u->fresh()));
    }

    public function test_cancelar_a_assinatura_atual_sem_periodo_pago_volta_ao_gratuito(): void
    {
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual', 'plano_slug' => 'basico', 'plano_expira_em' => now()->subDay()]);

        $this->enviar($this->cancelamento($u, 'sub_atual'))->assertOk();

        $this->assertSame('gratuito', $u->fresh()->plano_slug);
    }

    public function test_estorno_da_assinatura_atual_retira_o_plano_na_hora(): void
    {
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual', 'plano_slug' => 'basico', 'plano_expira_em' => now()->addDays(20)]);

        $this->enviar($this->pagamento($u, 'sub_atual', 'PAYMENT_REFUNDED'))->assertOk();

        $this->assertSame('gratuito', $u->fresh()->plano_slug);
        $this->assertNull($u->fresh()->plano_expira_em);
    }

    public function test_pagamento_repetido_nao_encurta_o_acesso(): void
    {
        $longe = now()->addMonths(3)->startOfSecond();
        $u = $this->usuario(['asaas_subscription_id' => 'sub_atual', 'plano_slug' => 'basico', 'plano_expira_em' => $longe]);

        $this->enviar($this->pagamento($u, 'sub_atual'))->assertOk();

        $this->assertTrue($u->fresh()->plano_expira_em >= $longe->toDateTimeString());
    }
}
