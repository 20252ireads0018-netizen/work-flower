<?php

namespace App\Http\Controllers;

use App\Models\Promocao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** /admin/promocoes (só a administração, middleware SoAdmin): promoções relâmpago dos planos pagos. */
class PromocaoAdminController extends Controller
{
    /** As datas do formulário são digitadas na hora de Brasília; o banco guarda em UTC (config app.timezone). */
    public const FUSO = 'America/Sao_Paulo';

    /** Planos que ainda não estão à venda (mesma lista de planos.blade.php). */
    private const EM_BREVE = ['pro', 'premium'];

    public function index(): View
    {
        return view('assinatura.promocoes', [
            'planos' => $this->planos(),
            'lista'  => Promocao::latest('id')->limit(50)->get(),
            'fuso'   => self::FUSO,
        ]);
    }

    public function criar(Request $request): RedirectResponse
    {
        $planos = $this->planos();
        $dados = $request->validate([
            'plano_slug' => ['required', Rule::in($planos->keys()->all())],
            'tipo'       => ['required', Rule::in(['preco', 'desconto'])],
            'valor'      => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'comeca_em'  => ['nullable', 'date_format:Y-m-d\TH:i'],
            'termina_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'titulo'     => ['nullable', 'string', 'max:80'],
        ], [], ['plano_slug' => 'plano', 'termina_em' => 'fim', 'comeca_em' => 'início']);

        if ($dados['tipo'] === 'desconto' && (float) $dados['valor'] >= 100) {
            return back()->withErrors(['valor' => 'O desconto precisa ser menor que 100%.'])->withInput();
        }

        $normal = (float) $planos[$dados['plano_slug']]->price;
        $preco = $dados['tipo'] === 'desconto'
            ? round($normal * (1 - (float) $dados['valor'] / 100), 2)
            : round((float) $dados['valor'], 2);
        if ($preco < 0.01 || $preco >= $normal) {
            return back()->withErrors(['valor' => 'O preço da promoção precisa ficar entre R$ 0,01 e o preço normal (R$ ' . number_format($normal, 2, ',', '.') . ').'])->withInput();
        }

        $comeca = empty($dados['comeca_em'])
            ? now()
            : Carbon::createFromFormat('Y-m-d\TH:i', $dados['comeca_em'], self::FUSO)->utc();
        $termina = Carbon::createFromFormat('Y-m-d\TH:i', $dados['termina_em'], self::FUSO)->utc();
        if ($termina->lte(now()) || $termina->lte($comeca)) {
            return back()->withErrors(['termina_em' => 'O fim precisa ser depois do início e no futuro.'])->withInput();
        }

        Promocao::create([
            'plano_slug' => $dados['plano_slug'],
            'preco'      => $preco,
            'titulo'     => trim((string) ($dados['titulo'] ?? '')) ?: null,
            'comeca_em'  => $comeca,
            'termina_em' => $termina,
        ]);

        return back()->with('ok', 'Promoção criada: ' . $planos[$dados['plano_slug']]->name . ' por R$ ' . number_format($preco, 2, ',', '.')
            . ' até ' . $termina->copy()->tz(self::FUSO)->format('d/m/Y H:i') . '.');
    }

    public function encerrar(Promocao $promocao): RedirectResponse
    {
        if (! $promocao->encerrada_em && $promocao->termina_em?->isFuture()) {
            $promocao->forceFill(['encerrada_em' => now()])->save();
        }

        return back()->with('ok', 'Promoção encerrada. Os Pix já gerados com o preço dela continuam valendo.');
    }

    /** Planos pagos que estão à venda, por slug. */
    private function planos()
    {
        return DB::table('plans')->where('active', 1)->where('price', '>', 0)
            ->whereNotIn('slug', self::EM_BREVE)->orderBy('sort')->get()->keyBy('slug');
    }
}
