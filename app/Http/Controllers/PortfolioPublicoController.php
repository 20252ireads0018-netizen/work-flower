<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\PortfolioMensagem;
use App\Support\Midia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Página pública de um portfólio (/p/{slug}), o formulário "Fale comigo" e as imagens dele. */
class PortfolioPublicoController extends Controller
{
    /** Mensagens por visitante (IP) e portfólio a cada 24 horas. */
    private const MAX_MENSAGENS_DIA = 5;

    public function ver(Request $request, string $slug): View
    {
        $pf = $this->portfolio($request, $slug);
        if ($pf->publicado && $request->user()?->id !== $pf->user_id) {
            Portfolio::whereKey($pf->id)->increment('visitas', 1, ['updated_at' => $pf->updated_at]); // não mexe em "atualizado em"
        }

        return view('portfolio.pagina', ['pf' => $pf, 'c' => $pf->conteudo(), 'previa' => false]);
    }

    public function contato(Request $request, string $slug): RedirectResponse
    {
        $pf = $this->portfolio($request, $slug);
        abort_unless($pf->publicado && $pf->aceitaMensagens(), 404);
        $volta = route('portfolio.ver', $pf->slug) . '#contato';
        if (filled($request->input('site'))) {
            return redirect()->to($volta)->with('pf_ok', 'Mensagem enviada. Obrigado!'); // robô: finge que deu certo
        }
        $d = $request->validate([
            'nome'     => ['required', 'string', 'max:120'],
            'contato'  => ['required', 'string', 'max:160'],
            'assunto'  => ['nullable', 'string', 'max:160'],
            'mensagem' => ['required', 'string', 'min:5', 'max:3000'],
        ], [], ['contato' => 'e-mail ou WhatsApp']);
        $ip = hash('sha256', $request->ip() . '|' . config('app.key'));
        $hoje = PortfolioMensagem::where('portfolio_id', $pf->id)->where('ip_hash', $ip)->where('created_at', '>=', now()->subDay())->count();
        if ($hoje >= self::MAX_MENSAGENS_DIA) {
            throw ValidationException::withMessages(['mensagem' => 'Você já mandou várias mensagens hoje. Tente de novo amanhã.']);
        }
        $pf->mensagens()->create([
            'nome'     => trim(strip_tags($d['nome'])),
            'contato'  => trim(strip_tags($d['contato'])),
            'assunto'  => trim(strip_tags((string) ($d['assunto'] ?? ''))) ?: null,
            'mensagem' => trim($d['mensagem']),
            'ip_hash'  => $ip,
        ]);

        return redirect()->to($volta)->with('pf_ok', 'Mensagem enviada. Obrigado pelo contato!');
    }

    /** Imagens e GIFs do portfólio: só arquivos do dono que aparecem nele. */
    public function midia(Request $request, string $slug, string $token): BinaryFileResponse
    {
        $pf = $this->portfolio($request, $slug);
        abort_unless($pf->usaMidia($token) && Midia::dono($token) === $pf->user_id, 404);
        $r = Midia::resposta($token);
        $r->headers->set('Cache-Control', 'public, max-age=604800');

        return $r;
    }

    /** Portfólio pelo endereço. Não publicado: só o dono vê (como prévia). */
    private function portfolio(Request $request, string $slug): Portfolio
    {
        $pf = Portfolio::with('user')->where('slug', $slug)->firstOrFail();
        abort_unless($pf->publicado || $request->user()?->id === $pf->user_id, 404);

        return $pf;
    }
}
