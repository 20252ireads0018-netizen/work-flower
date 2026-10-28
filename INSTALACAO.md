# Pagamentos mensais por plano (Laravel + Asaas)

Assinaturas recorrentes com Pix, boleto e cartão. O Asaas cobra todo mês; seu sistema guarda os planos, recebe os webhooks e controla o acesso.

## 1. Copiar os arquivos

Copie as pastas deste pacote para a raiz do seu projeto Laravel (a estrutura é a mesma):
`app/`, `config/`, `database/`, `routes/`, `tests/`.

## 2. Variáveis no `.env`

```env
ASAAS_ENV=sandbox
ASAAS_API_KEY=cole_a_chave_do_sandbox
ASAAS_WEBHOOK_TOKEN=gere_um_token_com_32_ou_mais_caracteres
ASAAS_GRACE_DAYS=3
ASAAS_QUEUE_WEBHOOKS=false
ASAAS_REDIRECT_UNSUBSCRIBED=/planos
```

Para gerar o token: `php -r "echo bin2hex(random_bytes(24));"`

Produção: troque para `ASAAS_ENV=production` e use a chave de produção (as chaves de sandbox e produção são diferentes).

## 3. Ligar o gateway (`app/Providers/AppServiceProvider.php`)

```php
use App\Services\Payments\AsaasGateway;
use App\Services\Payments\PaymentGateway;

public function register(): void
{
    $this->app->bind(PaymentGateway::class, AsaasGateway::class);
}
```

## 4. Model `User`

```php
use App\Models\Concerns\HasSubscriptions;

class User extends Authenticatable
{
    use HasSubscriptions; // + os traits que já existem

    protected $fillable = [ /* ...os atuais..., */ 'cpf_cnpj', 'asaas_customer_id'];
}
```

## 5. Rotas e middleware (`bootstrap/app.php`, Laravel 11/12)

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        require base_path('routes/payments.php');
    },
)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'subscribed' => \App\Http\Middleware\EnsureActiveSubscription::class,
    ]);
})
```

Laravel 10: carregue `routes/payments.php` no `RouteServiceProvider` (dentro de `boot`, fora de qualquer grupo) e registre o alias em `$middlewareAliases` no `app/Http/Kernel.php`.

## 6. Banco

```bash
php artisan migrate
php artisan db:seed --class=PlanSeeder   # planos de exemplo: edite preços e nomes
```

No cPanel: Terminal do cPanel (ou SSH) na pasta do projeto, mesmos comandos com `--force` no migrate. Depois: `php artisan config:clear`.

## 7. Configurar o Asaas

1. Crie uma conta de testes em sandbox.asaas.com e gere a API key (menu Integrações).
2. Em Integrações → Webhooks, crie um webhook:
   - URL: `https://SEU-DOMINIO/api/webhooks/asaas`
   - Token de autenticação: o mesmo valor de `ASAAS_WEBHOOK_TOKEN`
   - Eventos: todos de Cobranças (`PAYMENT_*`) e de Assinaturas (`SUBSCRIPTION_*`)
3. Em localhost (Laragon) o Asaas não alcança sua máquina: use um túnel (ngrok / Cloudflare Tunnel) ou simule com o `curl` do passo 8.

## 8. Testar

Logado no sistema, o front chama (com o header `X-CSRF-TOKEN`):

```
GET    /api/plans
POST   /api/subscription      {"plan":"pro","billing_type":"UNDEFINED","cpf_cnpj":"12345678909"}
GET    /api/subscription
GET    /api/subscription/payments
DELETE /api/subscription
```

A resposta do `POST` traz `data.payment_url`: redirecione o cliente para esse link para ele pagar (Pix, boleto ou cartão).

Simular um webhook (troque os IDs pelos reais da sua assinatura):

```bash
curl -X POST https://SEU-DOMINIO/api/webhooks/asaas \
  -H "Content-Type: application/json" \
  -H "asaas-access-token: SEU_TOKEN" \
  -d '{"id":"evt_teste_1","event":"PAYMENT_RECEIVED","payment":{"id":"pay_123","subscription":"sub_123","value":99.90,"status":"RECEIVED","billingType":"PIX","dueDate":"2026-10-15","invoiceUrl":"https://sandbox.asaas.com/i/teste"}}'
```

Rodar os testes automáticos: `php artisan test --filter=AsaasWebhookTest`

## 9. Proteger rotas por plano

```php
Route::middleware(['auth', 'subscribed'])->group(function () { /* área paga */ });
Route::middleware(['auth', 'subscribed:pro,premium'])->get('/relatorios', ...);
```

Ou em código/Blade: `auth()->user()->isSubscribed()` e `auth()->user()->isSubscribed(['pro'])`.

## Como o ciclo funciona

| Evento do Asaas | O que o sistema faz |
|---|---|
| `PAYMENT_CREATED` | registra a cobrança como `pending` |
| `PAYMENT_CONFIRMED` / `PAYMENT_RECEIVED` | cobrança `paid`, assinatura `active`, período +1 mês |
| `PAYMENT_OVERDUE` | assinatura `past_due` (acesso segue durante `ASAAS_GRACE_DAYS`) |
| `PAYMENT_REFUNDED` / chargeback | assinatura `suspended` (sem acesso) |
| `SUBSCRIPTION_DELETED` | assinatura `canceled` (acesso até o fim do período pago) |

## Observações

- Os planos ficam no seu banco (o Asaas não tem entidade "plano"); cada assinatura guarda o preço contratado em `amount`.
- Não há dados de cartão no seu servidor: o cliente paga na página de fatura do Asaas.
- Se o Asaas receber falhas seguidas do seu webhook, ele pode pausar a fila de envios; por isso o controller responde 200 para eventos ignorados e 500 só quando há erro real de processamento.
- Fora do escopo desta versão: troca de plano (upgrade/downgrade), nota fiscal e e-mails próprios.

