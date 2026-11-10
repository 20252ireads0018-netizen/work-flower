<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\LojaPedido;
use App\Models\LojaResgate;
use App\Models\PixPagamento;
use App\Support\Pix;
use App\Support\SaldoLoja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /admin/pix (só a conta de administração, middleware SoAdmin): chave Pix, confirmação dos pagamentos dos planos,
 * Pix dos pedidos das mini lojas (que caem na mesma chave e viram saldo do dono) e resgates desse saldo.
 */
class PixAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = is_string($s = $request->query('status')) ? $s : null; // ?status[]=x chega como array
        $status = isset(PixPagamento::STATUS[$status]) ? $status : null;

        $lista = PixPagamento::with('user')
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', 'cancelado'))
            ->orderByRaw("CASE status WHEN 'informado' THEN 0 WHEN 'aguardando' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('assinatura.admin', [
            'lista'    => $lista,
            'status'   => $status,
            'contagem' => PixPagamento::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'cfg'      => Pix::config(),
            // pedidos das lojas pagos por Pix na chave da plataforma: primeiro os que o cliente avisou que pagou
            'pedidosLojas' => LojaPedido::with('loja.user')->where('pagamento', 'pix')->where('pix_destino', 'plataforma')
                ->whereIn('status', ['informado', 'aguardando'])
                ->orderByRaw("CASE status WHEN 'informado' THEN 0 ELSE 1 END")->latest('id')->limit(60)->get(),
            'resgates'       => LojaResgate::with('user')->where('status', 'pendente')->orderBy('prazo')->orderBy('id')->limit(100)->get(),
            'resgatesFeitos' => LojaResgate::with('user')->where('status', '!=', 'pendente')->latest('updated_at')->limit(10)->get(),
            // compras do curso da página de vagas (/vagas): primeiro quem avisou que pagou
            'cursosVagas' => \Illuminate\Support\Facades\Schema::hasColumn('candidaturas', 'curso_status')
                ? Candidatura::whereIn('curso_status', ['informado', 'aguardando'])
                    ->orderByRaw("CASE curso_status WHEN 'informado' THEN 0 ELSE 1 END")->latest('curso_pedido_em')->limit(60)->get()
                : collect(),
        ]);
    }

    public function config(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'tipo'   => ['required', Rule::in(array_keys(Pix::TIPOS))],
            'chave'  => ['required', 'string', 'max:77'],
            'nome'   => ['required', 'string', 'max:60'],
            'cidade' => ['required', 'string', 'max:40'],
        ], [], ['chave' => 'chave Pix', 'nome' => 'nome do titular']);

        $dados['chave'] = Pix::normalizarChave($dados['tipo'], $dados['chave']);
        if ($erro = Pix::erroChave($dados['tipo'], $dados['chave'])) {
            return back()->withErrors(['chave' => $erro])->withInput();
        }
        // no QR Code o nome vai sem acentos e só com letras, números e espaços: precisa sobrar alguma coisa
        if (! preg_match('/[A-Za-z0-9]/', \Illuminate\Support\Str::ascii($dados['nome']))) {
            return back()->withErrors(['nome' => 'Use o nome do titular com letras.'])->withInput();
        }

        Pix::salvarConfig($dados);

        return back()->with('ok', 'Chave Pix salva. Os próximos QR Codes já usam estes dados.');
    }

    public function confirmar(PixPagamento $pix): RedirectResponse
    {
        if (! in_array($pix->status, ['aguardando', 'informado', 'recusado'], true)) {
            return back()->with('ok', 'Este pagamento já foi tratado.');
        }

        $ate = Pix::liberar($pix);
        if (! $ate) {
            return back()->with('ok', 'Este pagamento já foi tratado.');
        }

        if ($ate === true) {
            return back()->with('ok', "Plano liberado para sempre para {$pix->user?->name}.");
        }

        return back()->with('ok', "Plano liberado para {$pix->user?->name} até " . $ate->format('d/m/Y') . '.');
    }

    /** Achou o Pix de um pedido de loja no extrato: o pedido fica pago e o valor entra no saldo do dono da loja. */
    public function confirmarPedidoLoja(LojaPedido $pedido): RedirectResponse
    {
        if (! SaldoLoja::confirmarPedido($pedido)) {
            return back()->with('ok', 'Este pedido já foi tratado.');
        }

        return back()->with('ok', 'Pedido ' . $pedido->codigo . ' confirmado: R$ ' . number_format((float) $pedido->total, 2, ',', '.')
            . ' no saldo de ' . ($pedido->loja?->nome ?? 'loja') . '.');
    }

    /** Não achou o Pix: o pedido volta para "aguardando" com o motivo, que o dono da loja vê. */
    public function recusarPedidoLoja(Request $request, LojaPedido $pedido): RedirectResponse
    {
        if (! $pedido->pixDaPlataforma() || $pedido->status !== 'informado') {
            return back()->with('ok', 'Este pedido já foi tratado.');
        }
        $pedido->forceFill(['status' => 'aguardando', 'informado_em' => null,
            'admin_obs' => mb_substr(trim((string) $request->input('observacao', '')), 0, 300) ?: 'Pix não encontrado no extrato.'])->save();

        return back()->with('ok', 'Pedido ' . $pedido->codigo . ' voltou para aguardando pagamento.');
    }

    public function pagarResgate(LojaResgate $resgate): RedirectResponse
    {
        return back()->with('ok', SaldoLoja::pagarResgate($resgate)
            ? 'Resgate #' . $resgate->id . ' marcado como pago.'
            : 'Este resgate já foi tratado.');
    }

    public function recusarResgate(Request $request, LojaResgate $resgate): RedirectResponse
    {
        return back()->with('ok', SaldoLoja::recusarResgate($resgate, trim((string) $request->input('observacao', '')) ?: null)
            ? 'Resgate #' . $resgate->id . ' recusado: o valor voltou para o saldo da pessoa.'
            : 'Este resgate já foi tratado.');
    }

    public function recusar(Request $request, PixPagamento $pix): RedirectResponse
    {
        if (! $pix->aberto()) {
            return back()->with('ok', 'Este pagamento já foi tratado.');
        }

        $pix->status     = 'recusado';
        $pix->observacao = mb_substr(trim((string) $request->input('observacao', '')), 0, 500) ?: null;
        $pix->save();

        return back()->with('ok', 'Marcado como não encontrado. A pessoa vê o aviso na tela do Pix.');
    }

    /* ---- curso da página de vagas (R$ 19,90, Pix na mesma chave): o acesso é mandado por outro sistema ---- */

    public function confirmarCurso(Candidatura $candidatura): RedirectResponse
    {
        if (! in_array($candidatura->curso_status, ['aguardando', 'informado'], true)) {
            return back()->with('ok', 'Esta compra já foi tratada.');
        }
        $candidatura->forceFill(['curso_status' => 'confirmado', 'curso_confirmado_em' => now(), 'curso_obs' => null])->save();

        return back()->with('ok', 'Curso pago por ' . $candidatura->nome . ' (' . $candidatura->email . ' · ' . $candidatura->fone . '). Mande o acesso pelo seu sistema.');
    }

    public function recusarCurso(Request $request, Candidatura $candidatura): RedirectResponse
    {
        if ($candidatura->curso_status !== 'informado') {
            return back()->with('ok', 'Esta compra já foi tratada.');
        }
        $candidatura->forceFill(['curso_status' => 'recusado', 'curso_informado_em' => null,
            'curso_obs' => mb_substr(trim((string) $request->input('observacao', '')), 0, 300) ?: 'Pix não encontrado no extrato.'])->save();

        return back()->with('ok', 'Compra do curso marcada como não encontrada. A pessoa vê o aviso ao voltar à página.');
    }
}
