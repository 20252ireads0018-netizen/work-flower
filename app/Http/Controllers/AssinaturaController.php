<?php

namespace App\Http\Controllers;

use App\Models\PixPagamento;
use App\Models\Promocao;
use App\Support\Pix;
use App\Support\Planos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Assinar" na tela de planos: gera um Pix com o valor do plano para a chave da administração.
 * A pessoa paga, avisa que pagou e a administração confirma em /admin/pix (PixAdminController),
 * o que libera o plano por um mês.
 */
class AssinaturaController extends Controller
{
    public function assinar(Request $request, string $slug): RedirectResponse
    {
        $voltar  = route('planos');
        $usuario = $request->user();

        if (in_array($slug, ['pro', 'premium'], true)) {
            return redirect($voltar)->with('erro', 'Este plano chega em breve.');
        }

        $plano = DB::table('plans')->where('slug', $slug)->where('active', 1)->first();
        if (! $plano || (float) $plano->price <= 0) {
            return redirect($voltar)->with('erro', 'Plano indisponível para assinatura.');
        }

        // Quem comprou o vitalício não paga mês (o Pix mensal não acrescentaria nada).
        if (! empty($usuario->plano_vitalicio)) {
            return redirect($voltar)->with('erro', 'Você tem o plano ' . Planos::nome($usuario->plano_slug) . ' vitalício: não precisa pagar mensalidade.');
        }

        // Compara com o plano que o usuário REALMENTE paga (o teste de 30 dias não conta).
        // No mesmo plano, só deixa pagar de novo perto do fim do mês pago (renovação).
        $pago = Planos::slugPago($usuario);
        if ($pago === $plano->slug && ! Pix::podeRenovar($usuario)) {
            return redirect($voltar)->with('erro', 'Você já está neste plano. A renovação abre 7 dias antes do fim do mês pago.');
        }

        // Já paga um plano maior que ainda vale: confirmar este Pix trocaria o plano pelo menor.
        $ordem = array_flip(Planos::slugs());
        if (($ordem[$pago] ?? 0) > ($ordem[$plano->slug] ?? 0)) {
            return redirect($voltar)->with('erro', 'Você já tem o plano ' . Planos::nome($pago) . ', que inclui tudo do ' . $plano->name . '.');
        }

        if (! Pix::pronto()) {
            return redirect($voltar)->with('erro', 'O pagamento por Pix ainda não está disponível. Tente de novo mais tarde.');
        }

        // Já há um Pix em aberto deste plano e deste valor? Reaproveita (cada clique não cria um pedido novo).
        // Pedidos de outro plano ou valor ainda sem "Já paguei" ficam cancelados; os que a pessoa já disse que pagou
        // continuam esperando a conferência (abrir um link não pode apagar um pagamento feito).
        // Promoção relâmpago valendo agora: o Pix sai com o preço dela (Promocao, /admin/promocoes)
        $preco = Promocao::precoDe($plano->slug, (float) $plano->price);

        return redirect()->route('pix.ver', $this->pedido($usuario, $plano->slug, $preco));
    }

    /**
     * "Pague uma vez, receba para sempre": Pix da oferta vitalícia (Promocao com vitalicio) pelo preço promocional.
     * Confirmado em /admin/pix, o plano fica liberado sem data de fim (Pix::liberar).
     */
    public function vitalicio(Request $request, Promocao $promocao): RedirectResponse
    {
        $voltar  = route('planos');
        $usuario = $request->user();

        if (! $promocao->vitalicio || ! $promocao->vigente()) {
            return redirect($voltar)->with('erro', 'Esta oferta vitalícia não está mais valendo.');
        }
        $plano = DB::table('plans')->where('slug', $promocao->plano_slug)->where('active', 1)->first();
        if (! $plano) {
            return redirect($voltar)->with('erro', 'Plano indisponível para assinatura.');
        }

        $ordem = array_flip(Planos::slugs());
        if (! empty($usuario->plano_vitalicio) && ($ordem[Planos::slugPago($usuario)] ?? 0) >= ($ordem[$plano->slug] ?? 0)) {
            return redirect($voltar)->with('erro', 'Você já tem o plano ' . Planos::nome($usuario->plano_slug) . ' vitalício.');
        }

        if (! Pix::pronto()) {
            return redirect($voltar)->with('erro', 'O pagamento por Pix ainda não está disponível. Tente de novo mais tarde.');
        }

        return redirect()->route('pix.ver', $this->pedido($usuario, $plano->slug, (float) $promocao->preco, $promocao->id));
    }

    /**
     * Pix em aberto deste plano, deste valor e do mesmo tipo (mensal/vitalício): reaproveita (cada clique não cria
     * um pedido novo). Pedidos diferentes ainda sem "Já paguei" ficam cancelados; os que a pessoa já disse que pagou
     * continuam esperando a conferência (abrir um link não pode apagar um pagamento feito).
     */
    private function pedido($usuario, string $slug, float $preco, ?int $promocaoVitalicia = null): PixPagamento
    {
        $vitalicio = $promocaoVitalicia !== null;
        $aberto = PixPagamento::where('user_id', $usuario->id)->whereIn('status', PixPagamento::ABERTOS)->latest('id')->get();
        $mesmo  = $aberto->first(fn ($p) => $p->plano_slug === $slug && (float) $p->valor === $preco && (bool) $p->vitalicio === $vitalicio);
        $aberto->reject(fn ($p) => ($mesmo && $p->is($mesmo)) || $p->status !== 'aguardando')->each(fn ($p) => $p->forceFill(['status' => 'cancelado'])->save());

        if ($mesmo) {
            return $mesmo;
        }
        $campos = [
            'user_id'    => $usuario->id,
            'plano_slug' => $slug,
            'valor'      => $preco,
            'txid'       => Pix::novoTxid(),
            'status'     => 'aguardando',
        ];
        if ($vitalicio) {
            $campos += ['vitalicio' => true, 'promocao_id' => $promocaoVitalicia];
        }

        return PixPagamento::create($campos);
    }

    /** Tela com o QR Code e o "copia e cola". Só a própria pessoa vê o pedido dela. */
    public function ver(Request $request, PixPagamento $pix): View
    {
        abort_unless($pix->user_id === $request->user()->id, 404);

        $plano = DB::table('plans')->where('slug', $pix->plano_slug)->first();

        return view('assinatura.pix', [
            'pix'    => $pix,
            'plano'  => $plano,
            // "recusado" (não achado no extrato) continua mostrando o código: a pessoa pode ter pago e avisar de novo
            'codigo' => ($pix->aberto() || $pix->status === 'recusado') && Pix::pronto() ? Pix::copiaECola((float) $pix->valor, $pix->txid) : null,
            'cfg'    => Pix::config(),
        ]);
    }

    /** "Já paguei": avisa a administração, que confere o extrato e confirma. */
    public function paguei(Request $request, PixPagamento $pix): RedirectResponse
    {
        abort_unless($pix->user_id === $request->user()->id, 404);

        $dados = $request->validate(['pagador' => ['nullable', 'string', 'max:120']]);

        if (in_array($pix->status, ['aguardando', 'recusado'], true)) {
            $pix->status       = 'informado';
            $pix->pagador      = trim((string) ($dados['pagador'] ?? '')) ?: null;
            $pix->informado_em = now();
            $pix->save();
        }

        return redirect()->route('pix.ver', $pix)->with('ok', 'Obrigado! Assim que o pagamento for conferido, seu plano é liberado.');
    }

    /** Desiste do Pix em aberto. */
    public function cancelar(Request $request, PixPagamento $pix): RedirectResponse
    {
        abort_unless($pix->user_id === $request->user()->id, 404);

        if ($pix->status === 'aguardando') {
            $pix->forceFill(['status' => 'cancelado'])->save();
        }

        return redirect()->route('planos');
    }
}
