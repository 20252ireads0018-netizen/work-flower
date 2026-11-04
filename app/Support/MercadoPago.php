<?php

namespace App\Support;

use App\Models\Loja;
use App\Models\LojaPedido;
use Illuminate\Support\Facades\Http;

/**
 * Mercado Pago (Checkout Pro) das mini lojas. Cada dono conecta a própria conta colando o Access Token
 * (Suas integrações › Credenciais de produção) na aba Pagamento da loja; o dinheiro cai direto para ele.
 * O cliente paga na página do Mercado Pago (cartão, Pix ou boleto) e volta para a página do pedido.
 * A confirmação nunca confia no que vem no endereço ou no aviso: o pagamento é sempre lido de novo na API.
 */
class MercadoPago
{
    public const API = 'https://api.mercadopago.com';

    /** Conta dona do token (para mostrar "conectado como ..."), ou null se o token não vale. */
    public static function conta(string $token): ?array
    {
        try {
            $r = Http::withToken($token)->acceptJson()->timeout(15)->get(self::API . '/users/me');
        } catch (\Throwable $e) {
            return null;
        }
        if (! $r->successful() || ! $r->json('id')) return null;

        return ['id' => (string) $r->json('id'), 'nome' => (string) ($r->json('nickname') ?: $r->json('email') ?: 'conta ' . $r->json('id'))];
    }

    /** Cria a preferência de pagamento do pedido e devolve o endereço do checkout (ou null se falhar). */
    public static function checkout(Loja $loja, LojaPedido $pedido): ?string
    {
        if (! $loja->mpPronto()) return null;
        $urlPedido = route('loja.pedido', [$loja->slug, $pedido->codigo]);
        $corpo = [
            'items' => [[
                'id'          => $pedido->codigo,
                'title'       => mb_substr('Pedido ' . $pedido->codigo . ' · ' . $loja->nome, 0, 250),
                'quantity'    => 1,
                'currency_id' => 'BRL',
                'unit_price'  => round((float) $pedido->total, 2),
            ]],
            'external_reference'   => $pedido->codigo,
            'statement_descriptor' => mb_substr(preg_replace('/[^A-Za-z0-9 ]/', '', \Illuminate\Support\Str::ascii($loja->nome)) ?: 'LOJA', 0, 22),
            'back_urls'            => ['success' => $urlPedido, 'pending' => $urlPedido, 'failure' => $urlPedido],
            'payer'                => array_filter(['name' => $pedido->cliente_nome, 'email' => filter_var($pedido->cliente_contato, FILTER_VALIDATE_EMAIL) ?: null]),
        ];
        // o Mercado Pago só aceita voltar sozinho e avisar por webhook em endereços públicos
        if (str_starts_with($urlPedido, 'https://')) {
            $corpo['auto_return'] = 'approved';
            $corpo['notification_url'] = route('loja.mp.aviso', $loja->slug);
        }
        try {
            $r = Http::withToken($loja->mp_token)->acceptJson()->timeout(20)
                ->withHeaders(['X-Idempotency-Key' => $pedido->codigo . '-' . substr(md5((string) $pedido->total), 0, 8)])
                ->post(self::API . '/checkout/preferences', $corpo);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
        if (! $r->successful()) {
            report(new \RuntimeException('Mercado Pago recusou a preferência (' . $r->status() . '): ' . mb_substr($r->body(), 0, 300)));
            return null;
        }

        return $r->json('init_point') ?: null;
    }

    /**
     * Lê o pagamento na API com o token da loja e marca o pedido como pago se ele foi aprovado,
     * é deste pedido (external_reference) e tem o valor certo. Devolve o status do pagamento lido.
     */
    public static function conferir(Loja $loja, string $pagamentoId): ?string
    {
        if (! $loja->mpPronto() || ! preg_match('/^\d{1,20}$/', $pagamentoId)) return null;
        try {
            $r = Http::withToken($loja->mp_token)->acceptJson()->timeout(15)->get(self::API . '/v1/payments/' . $pagamentoId);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $r->successful()) return null;

        $codigo = (string) $r->json('external_reference');
        $pedido = LojaPedido::where('loja_id', $loja->id)->where('codigo', $codigo)->first();
        if (! $pedido) return null;
        $status = (string) $r->json('status');
        $valorOk = round((float) $r->json('transaction_amount'), 2) >= round((float) $pedido->total, 2);
        if ($status === 'approved' && $valorOk && $pedido->aberto()) {
            $pedido->forceFill(['status' => 'pago', 'pagamento' => 'mp', 'mp_pagamento' => $pagamentoId])->save();
        }

        return $status;
    }
}
