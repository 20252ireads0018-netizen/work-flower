<?php

namespace App\Http\Controllers;

use App\Models\PixPagamento;
use App\Support\Pix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** /admin/pix (só a conta de administração, middleware SoAdmin): chave Pix e confirmação dos pagamentos. */
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

        return back()->with('ok', "Plano liberado para {$pix->user?->name} até " . $ate->format('d/m/Y') . '.');
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
}
