<?php

namespace App\Http\Controllers;

use App\Support\AsaasCliente;
use App\Support\Documento;
use App\Support\Planos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** "Assinar" na tela de planos: cria a assinatura no Asaas e manda o usuário para a tela de pagamento. */
class AssinaturaController extends Controller
{
    public function assinar(Request $request, string $slug, AsaasCliente $asaas): RedirectResponse
    {
        $voltar  = config('asaas.redirect_unsubscribed', '/planos');
        $usuario = $request->user();

        if (in_array($slug, ['pro', 'premium'], true)) {
            return redirect($voltar)->with('erro', 'Este plano chega em breve.');
        }

        $plano = DB::table('plans')->where('slug', $slug)->where('active', 1)->first();
        if (! $plano || (float) $plano->price <= 0) {
            return redirect($voltar)->with('erro', 'Plano indisponível para assinatura.');
        }

        // Compara com o plano que o usuário REALMENTE paga. O plano liberado pelo teste de
        // 30 dias (Planos::slugDe) não conta, senão quem está no teste não conseguiria assinar.
        if (Planos::slugPago($usuario) === $plano->slug) {
            return redirect($voltar)->with('erro', 'Você já está neste plano.');
        }

        // O Asaas exige CPF/CNPJ válido do cliente. Sem ele, pede na tela de dados e volta aqui.
        $documento = Documento::limpar($usuario->cpf_cnpj ?? $usuario->cpf ?? null);
        if (! Documento::valido($documento)) {
            return redirect()->route('assinar.dados', $plano->slug);
        }

        try {
            // Já existe uma assinatura deste usuário no Asaas? Antes, cada clique em "Assinar" criava
            // outra assinatura (e outra cobrança mensal). Agora: mesmo plano com fatura em aberto
            // reaproveita a fatura; plano diferente (ou sem fatura) encerra a antiga antes de criar a nova.
            $referencia = "user:{$usuario->id}:plano:{$plano->slug}";
            $anteriorId = $usuario->asaas_subscription_id ?? null;
            if ($anteriorId) {
                $anterior = $asaas->buscarAssinatura($anteriorId);
                $ativa    = $anterior && empty($anterior['deleted']) && ($anterior['status'] ?? '') === 'ACTIVE';

                if ($ativa && ($anterior['externalReference'] ?? '') === $referencia) {
                    $aberta = $asaas->linkCobrancaAberta($anteriorId);
                    if ($aberta) {
                        return redirect()->away($aberta);
                    }
                }

                if ($ativa) {
                    $asaas->cancelarAssinatura($anteriorId);
                }
            }

            $cliente = $asaas->cliente(
                $usuario->name,
                $usuario->email,
                $documento,
            );

            $assinatura = $asaas->assinatura(
                $cliente,
                (float) $plano->price,
                "Work Flower · plano {$plano->name}",
                $referencia, // o webhook usa isto para liberar o plano
                (int) ($plano->trial_days ?? 0),
            );

            // o webhook também reconhece o usuário por este id
            DB::table('users')->where('id', $usuario->id)->update(['asaas_subscription_id' => $assinatura['id']]);

            $link = $asaas->linkPagamento($assinatura['id']);
        } catch (\Throwable $e) {
            report($e);

            return redirect($voltar)->with('erro', 'Não foi possível abrir o pagamento agora: ' . $e->getMessage());
        }

        if (! $link) {
            return redirect($voltar)->with('erro', 'A cobrança ainda está sendo gerada. Tente novamente em instantes.');
        }

        return redirect()->away($link);
    }

    /** Tela que pede o CPF/CNPJ antes de abrir o pagamento. */
    public function dados(Request $request, string $slug): View|RedirectResponse
    {
        $plano = DB::table('plans')->where('slug', $slug)->where('active', 1)->first();
        if (! $plano || (float) $plano->price <= 0 || in_array($slug, ['pro', 'premium'], true)) {
            return redirect(config('asaas.redirect_unsubscribed', '/planos'))->with('erro', 'Plano indisponível para assinatura.');
        }

        $atual = $request->user()->cpf_cnpj ?? $request->user()->cpf ?? '';

        return view('assinatura.dados', ['plano' => $plano, 'documento' => $atual]);
    }

    /** Guarda o CPF/CNPJ e segue direto para o pagamento. */
    public function salvarDados(Request $request, string $slug): RedirectResponse
    {
        $request->validate([
            'cpf_cnpj' => ['required', 'string', 'max:20', function ($atributo, $valor, $falhar) {
                if (! Documento::valido($valor)) {
                    $falhar('Informe um CPF ou CNPJ válido.');
                }
            }],
        ], [
            'cpf_cnpj.required' => 'Informe seu CPF ou CNPJ.',
        ]);

        DB::table('users')->where('id', $request->user()->id)->update([
            'cpf_cnpj' => Documento::limpar($request->input('cpf_cnpj')),
        ]);

        return redirect()->route('assinar', $slug);
    }
}
