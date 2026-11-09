<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\PortfolioMensagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Negócios › Portfólio: o editor (seções, tema, SEO, publicação) com prévia ao vivo e as mensagens recebidas.
 * O portfólio é da conta logada (como a loja). Página pública: PortfolioPublicoController (/p/{slug}).
 */
class PortfolioController extends Controller
{
    /** Tamanho máximo do JSON salvo (texto puro; imagens vão como token de mídia ou endereço). */
    private const MAX_JSON = 400_000;

    public function show(Request $request): View
    {
        $u = $request->user();
        $pf = Portfolio::where('user_id', $u->id)->first();

        return view('areas.negocios.portfolio', [
            'portfolio' => $pf,
            'dados'     => $pf ? $pf->conteudo() : Portfolio::inicial($u),
            'slug'      => $pf?->slug ?? $this->slugLivre(Str::slug($u->name) ?: 'meu-portfolio'),
            'mensagens' => $pf ? $pf->mensagens()->limit(200)->get() : collect(),
        ]);
    }

    /** Salva tudo de uma vez (o editor manda o estado inteiro). Cria o portfólio na primeira vez. */
    public function salvar(Request $request): JsonResponse
    {
        $uid = $request->user()->id;
        $pf = Portfolio::where('user_id', $uid)->first();
        $d = $request->validate([
            'slug'      => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Portfolio::RESERVADOS),
                Rule::unique('portfolios', 'slug')->ignore($pf?->id)],
            'publicado' => ['nullable', 'boolean'],
            'dados'     => ['required', 'array'],
        ], [], ['slug' => 'endereço do portfólio']);
        $this->tamanho($d['dados']);

        $pf ??= new Portfolio();
        $pf->user_id = $uid;
        $pf->slug = $d['slug'];
        $pf->publicado = $request->boolean('publicado');
        $pf->dados = Portfolio::normalizar($d['dados'], $uid);
        $pf->save();

        return response()->json([
            'ok'        => true,
            'url'       => route('portfolio.ver', $pf->slug),
            'publicado' => $pf->publicado,
            'dados'     => $pf->dados,
        ]);
    }

    /** Prévia ao vivo: a mesma página pública, montada com o que está no editor (ainda sem salvar). */
    public function previa(Request $request): Response
    {
        $d = $request->validate(['dados' => ['required', 'array'], 'slug' => ['nullable', 'string', 'max:40']]);
        $this->tamanho($d['dados']);
        $uid = $request->user()->id;
        $pf = Portfolio::where('user_id', $uid)->first() ?? new Portfolio(['slug' => 'previa']);
        $pf->setRelation('user', $request->user());
        $c = Portfolio::normalizar($d['dados'], $uid);

        return response()->view('portfolio.pagina', ['pf' => $pf, 'c' => $c, 'previa' => true], 200, [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function lida(Request $request, PortfolioMensagem $mensagem): JsonResponse
    {
        $this->daConta($request, $mensagem);
        $mensagem->forceFill(['lida' => $request->boolean('lida', true)])->save();

        return response()->json(['ok' => true]);
    }

    public function apagarMensagem(Request $request, PortfolioMensagem $mensagem): JsonResponse
    {
        $this->daConta($request, $mensagem);
        $mensagem->delete();

        return response()->json(['ok' => true]);
    }

    /** Apaga o portfólio e as mensagens (as imagens continuam na mídia da conta). */
    public function excluir(Request $request): RedirectResponse
    {
        $request->validate(['confirmar' => ['required', 'in:EXCLUIR']], ['confirmar.in' => 'Digite EXCLUIR para confirmar.']);
        Portfolio::where('user_id', $request->user()->id)->first()?->delete();

        return redirect()->route('portfolio.editor')->with('ok', 'Portfólio excluído.');
    }

    /* ---------- apoio ---------- */

    private function daConta(Request $request, PortfolioMensagem $m): void
    {
        abort_unless($m->portfolio && $m->portfolio->user_id === $request->user()->id, 404);
    }

    private function tamanho(array $dados): void
    {
        if (strlen((string) json_encode($dados)) > self::MAX_JSON) {
            throw ValidationException::withMessages(['dados' => 'O portfólio ficou grande demais. Encurte alguns textos ou tire itens.']);
        }
    }

    private function slugLivre(string $base): string
    {
        $base = trim(substr($base, 0, 34), '-');
        if (strlen($base) < 3) $base = 'portfolio-' . $base;
        $s = $base;
        for ($i = 2; in_array($s, Portfolio::RESERVADOS, true) || Portfolio::where('slug', $s)->exists(); $i++) $s = $base . '-' . $i;

        return $s;
    }
}
