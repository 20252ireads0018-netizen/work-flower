<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\VagaOferta;
use App\Models\VagaResposta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vagas em cartões (/vagas/cartoes, conta logada): a pessoa desliza para a direita para se candidatar (o portfólio
 * publicado em /p/{slug} vai como currículo) ou para a esquerda para descartar. Também publica vagas e acompanha quem
 * se candidatou às vagas dela. Fica no app (e não na página pública /vagas) porque precisa da sessão.
 */
class VagasCartoesController extends Controller
{
    public function index(Request $request): View
    {
        $u = $request->user();
        $pf = Portfolio::where('user_id', $u->id)->first();

        $minhas = VagaResposta::with('vaga')->where('user_id', $u->id)->where('acao', 'candidatou')->latest()->limit(200)->get()
            ->filter(fn ($r) => $r->vaga)
            ->map(fn ($r) => $this->candidatura($r))->values();

        $publicadas = VagaOferta::where('user_id', $u->id)->latest()->get()->map(fn ($v) => $this->publicada($v))->values();

        return view('vagas.cartoes', [
            'fila'        => $this->fila($u->id)->map->cartao()->values(),
            'minhas'      => $minhas,
            'publicadas'  => $publicadas,
            'descartadas' => VagaResposta::where('user_id', $u->id)->where('acao', 'descartou')->count(),
            'portfolio'   => $pf ? ['slug' => $pf->slug, 'publicado' => (bool) $pf->publicado, 'url' => route('portfolio.ver', $pf->slug)] : null,
            'modalidades' => VagaOferta::MODALIDADES,
            'regimes'     => VagaOferta::REGIMES,
            'status'      => VagaResposta::STATUS,
        ]);
    }

    /** Próximas vagas: ativas, de outras contas (ou da plataforma) e ainda sem resposta desta conta. */
    private function fila(int $uid)
    {
        return VagaOferta::where('ativa', true)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $uid))
            ->whereNotIn('id', VagaResposta::where('user_id', $uid)->select('vaga_id'))
            ->latest()->limit(60)->get();
    }

    /** Deslizou: candidatou (direita) ou descartou (esquerda). Responder de novo troca a resposta. */
    public function responder(Request $request, VagaOferta $vaga): JsonResponse
    {
        $u = $request->user();
        abort_if(! $vaga->ativa || $vaga->user_id === $u->id, 404);
        $d = $request->validate([
            'acao'     => ['required', Rule::in(VagaResposta::ACOES)],
            'mensagem' => ['nullable', 'string', 'max:1000'],
        ]);

        $slug = null;
        if ($d['acao'] === 'candidatou') {
            $pf = Portfolio::where('user_id', $u->id)->first();
            if (! $pf || ! $pf->publicado) {
                return response()->json([
                    'ok' => false, 'portfolio' => false,
                    'erro' => 'Para se candidatar, publique seu portfólio: ele vai como seu currículo.',
                    'editor' => route('portfolio.editor'),
                ], 422);
            }
            $slug = $pf->slug;
        }

        $r = VagaResposta::updateOrCreate(
            ['vaga_id' => $vaga->id, 'user_id' => $u->id],
            ['acao' => $d['acao'], 'portfolio_slug' => $slug, 'mensagem' => $d['acao'] === 'candidatou' ? (trim((string) ($d['mensagem'] ?? '')) ?: null) : null, 'status' => 'enviada'],
        );

        return response()->json(['ok' => true, 'candidatura' => $r->acao === 'candidatou' ? $this->candidatura($r->setRelation('vaga', $vaga)) : null]);
    }

    /** Desfazer o último deslize ou retirar a candidatura (só enquanto a empresa não mexeu nela). */
    public function desfazer(Request $request, VagaOferta $vaga): JsonResponse
    {
        $r = VagaResposta::where('vaga_id', $vaga->id)->where('user_id', $request->user()->id)->first();
        if ($r && $r->acao === 'candidatou' && ! in_array($r->status, ['enviada', 'vista'], true)) {
            return response()->json(['ok' => false, 'erro' => 'A empresa já andou com esta candidatura; ela não pode mais ser retirada.'], 422);
        }
        $r?->delete();

        return response()->json(['ok' => true, 'vaga' => $vaga->ativa ? $vaga->cartao() : null]);
    }

    /** Começar de novo: as vagas descartadas voltam para a pilha. */
    public function rever(Request $request): JsonResponse
    {
        VagaResposta::where('user_id', $request->user()->id)->where('acao', 'descartou')->delete();

        return response()->json(['ok' => true, 'fila' => $this->fila($request->user()->id)->map->cartao()->values()]);
    }

    public function publicar(Request $request): JsonResponse
    {
        $u = $request->user();
        $d = $this->validarVaga($request);
        if (VagaOferta::where('user_id', $u->id)->where('ativa', true)->count() >= VagaOferta::MAX_ATIVAS) {
            return response()->json(['ok' => false, 'erro' => 'Você já tem ' . VagaOferta::MAX_ATIVAS . ' vagas abertas. Encerre alguma antes de publicar outra.'], 422);
        }
        $v = new VagaOferta($d + ['ativa' => true]);
        $v->user_id = $u->id;
        $v->save();

        return response()->json(['ok' => true, 'vaga' => $this->publicada($v)]);
    }

    /** Abre ou encerra a vaga (encerrada some da pilha de todo mundo, as candidaturas continuam). */
    public function alternar(Request $request, VagaOferta $vaga): JsonResponse
    {
        abort_unless($vaga->user_id === $request->user()->id, 404);
        if (! $vaga->ativa && VagaOferta::where('user_id', $vaga->user_id)->where('ativa', true)->count() >= VagaOferta::MAX_ATIVAS) {
            return response()->json(['ok' => false, 'erro' => 'Limite de vagas abertas.'], 422);
        }
        $vaga->forceFill(['ativa' => ! $vaga->ativa])->save();

        return response()->json(['ok' => true, 'vaga' => $this->publicada($vaga)]);
    }

    public function excluir(Request $request, VagaOferta $vaga): JsonResponse
    {
        abort_unless($vaga->user_id === $request->user()->id, 404);
        $vaga->delete();

        return response()->json(['ok' => true]);
    }

    /** Quem publicou muda o andamento de uma candidatura (a pessoa vê em "Minhas candidaturas"). */
    public function status(Request $request, VagaResposta $resposta): JsonResponse
    {
        abort_unless($resposta->acao === 'candidatou' && $resposta->vaga?->user_id === $request->user()->id, 404);
        $d = $request->validate(['status' => ['required', Rule::in(array_keys(VagaResposta::STATUS))]]);
        $resposta->forceFill(['status' => $d['status']])->save();

        return response()->json(['ok' => true]);
    }

    private function validarVaga(Request $request): array
    {
        $d = $request->validate([
            'titulo'     => ['required', 'string', 'min:3', 'max:120'],
            'empresa'    => ['required', 'string', 'min:2', 'max:120'],
            'cidade'     => ['nullable', 'string', 'max:120'],
            'modalidade' => ['required', Rule::in(array_keys(VagaOferta::MODALIDADES))],
            'regime'     => ['required', Rule::in(VagaOferta::REGIMES)],
            'salario'    => ['nullable', 'string', 'max:80'],
            'descricao'  => ['required', 'string', 'min:20', 'max:4000'],
            'requisitos' => ['nullable', 'string', 'max:2000'],
            'beneficios' => ['nullable', 'string', 'max:1000'],
            'link'       => ['nullable', 'url:https,http', 'max:500'],
        ], [
            'required' => 'Preencha este campo.',
            'descricao.min' => 'Conte um pouco mais sobre a vaga (pelo menos 20 letras).',
            'link.url' => 'Use um endereço completo, começando com https://',
        ]);

        return array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $d);
    }

    private function candidatura(VagaResposta $r): array
    {
        return [
            'vaga'      => $r->vaga->cartao(),
            'status'    => $r->status,
            'mensagem'  => $r->mensagem,
            'quando'    => $r->created_at?->format('d/m/Y'),
            'portfolio' => $r->portfolio_slug ? route('portfolio.ver', $r->portfolio_slug) : null,
            'retiravel' => in_array($r->status, ['enviada', 'vista'], true),
        ];
    }

    private function publicada(VagaOferta $v): array
    {
        $cands = $v->respostas()->with('user:id,name,email')->where('acao', 'candidatou')->latest()->limit(300)->get();

        return $v->cartao() + [
            'descartes' => $v->respostas()->where('acao', 'descartou')->count(),
            'candidatos' => $cands->map(fn ($r) => [
                'id'        => $r->id,
                'nome'      => $r->user?->name,
                'email'     => $r->user?->email,
                'portfolio' => $r->portfolio_slug ? route('portfolio.ver', $r->portfolio_slug) : null,
                'mensagem'  => $r->mensagem,
                'status'    => $r->status,
                'quando'    => $r->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ];
    }
}
