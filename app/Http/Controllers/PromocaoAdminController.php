<?php

namespace App\Http\Controllers;

use App\Models\Promocao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * /admin/promocoes (só a administração, middleware SoAdmin): promoções dos planos pagos.
 * - mensal: preço menor do mês por um prazo;
 * - vitalícia ("Pague uma vez, receba para sempre"): o plano comprado uma vez, sem expiração, com preço cheio e
 *   preço promocional definidos aqui, à venda só durante o prazo.
 * Dá para criar, editar (preço, prazo, textos), desativar e reativar. Cada pessoa vê o aviso uma vez ao entrar.
 */
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
            'vistas' => Promocao::temVitalicio()
                ? DB::table('promocao_vistas')->selectRaw('promocao_id, count(*) n')->groupBy('promocao_id')->pluck('n', 'promocao_id')
                : collect(),
        ]);
    }

    public function criar(Request $request): RedirectResponse
    {
        $planos = $this->planos();
        $dados = $request->validate([
            'modalidade'  => ['nullable', Rule::in(['mensal', 'vitalicio'])],
            'plano_slug'  => ['required', Rule::in($planos->keys()->all())],
            'tipo'        => ['required', Rule::in(['preco', 'desconto'])],
            'valor'       => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'preco_cheio' => ['nullable', 'required_if:modalidade,vitalicio', 'numeric', 'min:0.01', 'max:99999'],
            'comeca_em'   => ['nullable', 'date_format:Y-m-d\TH:i'],
            'termina_em'  => ['required', 'date_format:Y-m-d\TH:i'],
            'titulo'      => ['nullable', 'string', 'max:80'],
            'descricao'   => ['nullable', 'string', 'max:500'],
        ], ['preco_cheio.required_if' => 'Informe o preço cheio do plano vitalício.'],
            ['plano_slug' => 'plano', 'termina_em' => 'fim', 'comeca_em' => 'início', 'preco_cheio' => 'preço cheio']);

        $vitalicio = ($dados['modalidade'] ?? 'mensal') === 'vitalicio';
        if ($dados['tipo'] === 'desconto' && (float) $dados['valor'] >= 100) {
            return back()->withErrors(['valor' => 'O desconto precisa ser menor que 100%.'])->withInput();
        }

        // base do desconto: o preço do mês (mensal) ou o preço cheio que o admin deu ao vitalício
        $normal = $vitalicio ? round((float) $dados['preco_cheio'], 2) : (float) $planos[$dados['plano_slug']]->price;
        $preco = $dados['tipo'] === 'desconto'
            ? round($normal * (1 - (float) $dados['valor'] / 100), 2)
            : round((float) $dados['valor'], 2);
        if ($erro = $this->erroPreco($preco, $normal, $vitalicio)) {
            return back()->withErrors(['valor' => $erro])->withInput();
        }

        [$comeca, $termina, $erro] = $this->prazo($dados['comeca_em'] ?? null, $dados['termina_em']);
        if ($erro) {
            return back()->withErrors(['termina_em' => $erro])->withInput();
        }

        $campos = [
            'plano_slug' => $dados['plano_slug'],
            'preco'      => $preco,
            'titulo'     => trim((string) ($dados['titulo'] ?? '')) ?: ($vitalicio ? 'Pague uma vez, receba para sempre' : null),
            'comeca_em'  => $comeca,
            'termina_em' => $termina,
        ];
        if (Promocao::temVitalicio()) {
            $campos += [
                'vitalicio'   => $vitalicio,
                'preco_cheio' => $vitalicio ? $normal : null,
                'descricao'   => trim((string) ($dados['descricao'] ?? '')) ?: null,
            ];
        }
        Promocao::create($campos);

        return back()->with('ok', 'Promoção criada: ' . $planos[$dados['plano_slug']]->name . ($vitalicio ? ' vitalício' : '')
            . ' por R$ ' . number_format($preco, 2, ',', '.')
            . ' até ' . $termina->copy()->tz(self::FUSO)->format('d/m/Y H:i') . '. Cada pessoa vê o aviso uma vez ao entrar.');
    }

    /** Muda preço, prazo e textos. Os Pix já gerados continuam com o valor de quando foram gerados. */
    public function editar(Request $request, Promocao $promocao): RedirectResponse
    {
        $dados = $request->validate([
            'preco'       => ['required', 'numeric', 'min:0.01', 'max:99999'],
            'preco_cheio' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'comeca_em'   => ['nullable', 'date_format:Y-m-d\TH:i'],
            'termina_em'  => ['required', 'date_format:Y-m-d\TH:i'],
            'titulo'      => ['nullable', 'string', 'max:80'],
            'descricao'   => ['nullable', 'string', 'max:500'],
        ], [], ['termina_em' => 'fim', 'comeca_em' => 'início', 'preco_cheio' => 'preço cheio']);
        $erros = fn (array $e) => back()->withErrors($e, 'editar' . $promocao->id)->withInput();

        $preco = round((float) $dados['preco'], 2);
        $normal = $promocao->vitalicio
            ? round((float) ($dados['preco_cheio'] ?? $promocao->preco_cheio ?? $preco), 2)
            : (float) (DB::table('plans')->where('slug', $promocao->plano_slug)->value('price') ?? 0);
        if ($erro = $this->erroPreco($preco, $normal, (bool) $promocao->vitalicio)) {
            return $erros(['preco' => $erro]);
        }

        // início vazio = mantém o que estava
        [$comeca, $termina, $erro] = $this->prazo($dados['comeca_em'] ?? null, $dados['termina_em'], $promocao->comeca_em);
        if ($erro) {
            return $erros(['termina_em' => $erro]);
        }

        $campos = [
            'preco'      => $preco,
            'titulo'     => trim((string) ($dados['titulo'] ?? '')) ?: null,
            'comeca_em'  => $comeca,
            'termina_em' => $termina,
        ];
        if (Promocao::temVitalicio()) {
            $campos['preco_cheio'] = $promocao->vitalicio ? $normal : null;
            $campos['descricao'] = trim((string) ($dados['descricao'] ?? '')) ?: null;
        }
        $promocao->forceFill($campos)->save();

        return back()->with('ok', 'Promoção atualizada.');
    }

    /** Desativa (antes do prazo) ou reativa (se o prazo ainda não passou). */
    public function alternar(Promocao $promocao): RedirectResponse
    {
        if ($promocao->encerrada_em) {
            if (! $promocao->termina_em?->isFuture()) {
                return back()->with('ok', 'O prazo desta promoção já passou. Edite o fim para reativá-la.');
            }
            $promocao->forceFill(['encerrada_em' => null])->save();

            return back()->with('ok', 'Promoção reativada.');
        }

        return $this->encerrar($promocao);
    }

    /** Desativa antes do prazo ("Encerrar agora"). */
    public function encerrar(Promocao $promocao): RedirectResponse
    {
        if (! $promocao->encerrada_em && $promocao->termina_em?->isFuture()) {
            $promocao->forceFill(['encerrada_em' => now()])->save();
        }

        return back()->with('ok', 'Promoção desativada. Os Pix já gerados com o preço dela continuam valendo.');
    }

    private function erroPreco(float $preco, float $normal, bool $vitalicio): ?string
    {
        $fmt = fn ($v) => 'R$ ' . number_format($v, 2, ',', '.');
        if ($vitalicio) {
            return $preco < 0.01 || $preco > $normal
                ? 'O preço promocional do vitalício precisa ficar entre R$ 0,01 e o preço cheio (' . $fmt($normal) . ').' : null;
        }

        return $preco < 0.01 || $preco >= $normal
            ? 'O preço da promoção precisa ficar entre R$ 0,01 e o preço normal (' . $fmt($normal) . ').' : null;
    }

    /** [início, fim, erro] em UTC a partir do formulário (hora de Brasília). */
    private function prazo(?string $comecaEm, string $terminaEm, ?Carbon $atual = null): array
    {
        $comeca = empty($comecaEm)
            ? ($atual ?? now())
            : Carbon::createFromFormat('Y-m-d\TH:i', $comecaEm, self::FUSO)->utc();
        $termina = Carbon::createFromFormat('Y-m-d\TH:i', $terminaEm, self::FUSO)->utc();
        $erro = $termina->lte(now()) || $termina->lte($comeca) ? 'O fim precisa ser depois do início e no futuro.' : null;

        return [$comeca, $termina, $erro];
    }

    /** Planos pagos que estão à venda, por slug. */
    private function planos()
    {
        return DB::table('plans')->where('active', 1)->where('price', '>', 0)
            ->whereNotIn('slug', self::EM_BREVE)->orderBy('sort')->get()->keyBy('slug');
    }
}
