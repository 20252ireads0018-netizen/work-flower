<?php

namespace App\Http\Controllers;

use App\Models\Reclamacao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Canal "Reclame aqui": a pessoa manda reclamação, sugestão, problema ou elogio e acompanha a resposta;
 * a administração (User::ADMINS) vê tudo em /admin/reclamacoes, responde e marca como resolvida.
 */
class ReclamacaoController extends Controller
{
    /** A tabela é criada pela migração 2026_11_02 (sem ela, as páginas mostram um aviso em vez de erro 500). */
    private static function pronto(): bool
    {
        try {
            return Schema::hasTable('reclamacoes');
        } catch (\Throwable) {
            return false;
        }
    }

    public function index(Request $request): View
    {
        if (! self::pronto()) {
            return view('reclame-aqui.indisponivel', ['admin' => $request->user()->eAdmin()]);
        }
        $minhas = Reclamacao::where('user_id', $request->user()->id)->latest()->limit(50)->get();

        // abriu a página: as respostas novas foram vistas
        Reclamacao::where('user_id', $request->user()->id)->where('resposta_vista', false)->update(['resposta_vista' => true]);

        return view('reclame-aqui.index', ['minhas' => $minhas, 'pagina' => $this->paginaDeOrigem($request)]);
    }

    public function enviar(Request $request): RedirectResponse
    {
        if (! self::pronto()) {
            return redirect()->route('reclame.index');
        }
        $chave = 'reclame:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            return back()->withErrors(['mensagem' => 'Você já mandou várias mensagens agora. Espere alguns minutos e tente de novo.'])->withInput();
        }

        $d = $request->validate([
            'tipo'     => ['required', Rule::in(array_keys(Reclamacao::TIPOS))],
            'assunto'  => ['required', 'string', 'min:3', 'max:150'],
            'mensagem' => ['required', 'string', 'min:10', 'max:5000'],
            'pagina'   => ['nullable', 'string', 'max:300'],
        ], [
            'assunto.required'  => 'Escreva um assunto.',
            'mensagem.required' => 'Conte o que aconteceu.',
            'mensagem.min'      => 'Conte um pouco mais (pelo menos 10 letras).',
        ]);

        RateLimiter::hit($chave, 600);
        Reclamacao::create([
            'user_id'  => $request->user()->id,
            'tipo'     => $d['tipo'],
            'assunto'  => trim($d['assunto']),
            'mensagem' => trim($d['mensagem']),
            'pagina'   => $this->paginaSegura($d['pagina'] ?? null),
        ]);

        return redirect()->route('reclame.index')->with('ok', 'Recebemos sua mensagem. A resposta aparece aqui nesta página.');
    }

    /* ---------- administração ---------- */

    public function admin(Request $request): View
    {
        abort_unless($request->user()->eAdmin(), 404);
        if (! self::pronto()) {
            return view('reclame-aqui.indisponivel', ['admin' => true]);
        }
        $status = $request->query('status');
        $lista = Reclamacao::with('user:id,name,email')
            ->when(isset(Reclamacao::STATUS[$status]), fn ($q) => $q->where('status', $status))
            ->orderByRaw("case status when 'aberta' then 0 when 'respondida' then 1 else 2 end")
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $contagem = Reclamacao::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('reclame-aqui.admin', ['lista' => $lista, 'contagem' => $contagem, 'status' => $status]);
    }

    public function responder(Request $request, Reclamacao $reclamacao): RedirectResponse
    {
        abort_unless($request->user()->eAdmin(), 404);
        $d = $request->validate([
            'resposta' => ['nullable', 'string', 'max:5000'],
            'status'   => ['required', Rule::in(array_keys(Reclamacao::STATUS))],
        ]);

        $resposta = trim((string) ($d['resposta'] ?? ''));
        if ($resposta !== '' && $resposta !== $reclamacao->resposta) {
            $reclamacao->resposta = $resposta;
            $reclamacao->respondida_em = now();
            $reclamacao->resposta_vista = false;
        }
        $reclamacao->status = $d['status'] === 'aberta' && $reclamacao->resposta ? 'respondida' : $d['status'];
        $reclamacao->save();

        return back()->with('ok', 'Resposta salva.');
    }

    /** Endereço de onde a pessoa veio (só do próprio site), para saber em que tela aconteceu. */
    private function paginaDeOrigem(Request $request): ?string
    {
        return $this->paginaSegura($request->query('de') ?: $request->headers->get('referer'));
    }

    private function paginaSegura(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $base = rtrim(url('/'), '/');

        return str_starts_with($url, $base) ? mb_substr($url, 0, 300) : null;
    }
}
