<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Verificação em duas etapas (opcional, para qualquer conta): app autenticador (TOTP) + códigos de recuperação.
 * - Configurações › Segurança (/seguranca): ativar com QR Code, desativar, gerar novos códigos de recuperação.
 * - /login/dois-fatores: depois da senha certa, pede o código de 6 dígitos (ou um código de recuperação).
 */
class DoisFatoresController extends Controller
{
    /** Quanto tempo a senha certa vale para digitar o código. */
    private const MINUTOS_PARA_O_CODIGO = 5;

    /** Erros de código por conta: por minuto e por hora (6 dígitos com janela de 3 passos). */
    private const ERROS_POR_MINUTO = 5;

    private const ERROS_POR_HORA = 20;

    private const QTD_RECUPERACAO = 8;

    // ---------------------------------------------------------------- login

    /** Chamado pelo AuthController quando a senha confere e a conta tem duas etapas. */
    public static function pedirCodigo(Request $request, User $conta, bool $lembrar): RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->put('dois_fatores', [
            'id'      => $conta->getAuthIdentifier(),
            'lembrar' => $lembrar,
            'ate'     => now()->addMinutes(self::MINUTOS_PARA_O_CODIGO)->getTimestamp(),
        ]);

        return redirect()->route('login.dois-fatores');
    }

    public function formCodigo(Request $request): View|RedirectResponse
    {
        if (! $this->pendente($request)) {
            return redirect()->route('login');
        }

        return view('auth.dois-fatores');
    }

    public function conferirCodigo(Request $request): RedirectResponse
    {
        $pendente = $this->pendente($request);
        $conta = $pendente ? User::find($pendente['id']) : null;
        if (! $conta || ! $conta->temDoisFatores()) {
            $request->session()->forget('dois_fatores');

            return redirect()->route('login')->withErrors(['email' => 'O tempo para digitar o código acabou. Entre de novo.']);
        }

        $request->validate(['codigo' => ['required', 'string', 'max:40']], ['codigo.required' => 'Digite o código do app.']);

        $chaveMin = 'dois-fatores:min:' . $conta->id;
        $chaveHora = 'dois-fatores:hora:' . $conta->id;
        if (RateLimiter::tooManyAttempts($chaveMin, self::ERROS_POR_MINUTO) || RateLimiter::tooManyAttempts($chaveHora, self::ERROS_POR_HORA)) {
            $s = max(RateLimiter::availableIn($chaveMin), RateLimiter::tooManyAttempts($chaveHora, self::ERROS_POR_HORA) ? RateLimiter::availableIn($chaveHora) : 0);

            return back()->withErrors(['codigo' => 'Muitos códigos errados. Tente de novo em ' . max(1, (int) ceil($s / 60)) . ' min.']);
        }

        $codigo = (string) $request->input('codigo');
        if (! $this->aceitarCodigo($conta, $codigo)) {
            RateLimiter::hit($chaveMin, 60);
            RateLimiter::hit($chaveHora, 3600);
            Log::notice('Código de duas etapas errado', ['user_id' => $conta->id, 'ip' => $request->ip()]);

            return back()->withErrors(['codigo' => 'Código incorreto. Confira o app ou use um código de recuperação.']);
        }

        RateLimiter::clear($chaveMin);
        $request->session()->forget('dois_fatores');
        Auth::login($conta, (bool) $pendente['lembrar']);
        $request->session()->regenerate();

        return redirect()->intended(route('painel'));
    }

    // ---------------------------------------------------------------- configurações

    public function pagina(Request $request): View
    {
        $u = $request->user();
        $novo = $u->temDoisFatores() ? null : $request->session()->get('dois_fatores_novo');

        return view('seguranca.index', [
            'ativo'      => $u->temDoisFatores(),
            'ativoEm'    => $u->dois_fatores_ativo_em,
            'restantes'  => count($u->dois_fatores_recuperacao ?? []),
            'segredo'    => $novo,
            'uri'        => $novo ? Totp::uri($novo, $u->email) : null,
            'codigos'    => $request->session()->get('codigos_recuperacao'),
            'admin'      => $u->eAdmin(),
        ]);
    }

    /** Gera um segredo novo (só na sessão até a pessoa confirmar um código do app). */
    public function iniciar(Request $request): RedirectResponse
    {
        abort_if($request->user()->temDoisFatores(), 409);
        $request->session()->put('dois_fatores_novo', Totp::gerarSegredo());

        return redirect()->route('seguranca');
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $u = $request->user();
        $segredo = $request->session()->get('dois_fatores_novo');
        if ($u->temDoisFatores() || ! $segredo) {
            return redirect()->route('seguranca');
        }
        $request->validate([
            'password' => ['required', 'string', 'max:128'],
            'codigo'   => ['required', 'string', 'max:20'],
        ], ['password.required' => 'Digite sua senha.', 'codigo.required' => 'Digite o código que aparece no app.']);

        if ($erro = $this->senhaErrada($request, $u)) {
            return $erro;
        }
        $passo = Totp::verificar($segredo, (string) $request->input('codigo'));
        if ($passo === null) {
            return back()->withErrors(['codigo' => 'Código incorreto. Confira se o relógio do celular está certo e tente o código novo.']);
        }

        $codigos = $this->novosCodigos();
        $u->forceFill([
            'dois_fatores_segredo'      => $segredo,
            'dois_fatores_recuperacao'  => array_map([$this, 'hashCodigo'], $codigos),
            'dois_fatores_ativo_em'     => now(),
            'dois_fatores_ultimo_passo' => $passo,
        ])->save();
        $request->session()->forget('dois_fatores_novo');
        Log::info('Verificação em duas etapas ativada', ['user_id' => $u->id, 'ip' => $request->ip()]);

        return redirect()->route('seguranca')
            ->with('codigos_recuperacao', $codigos)
            ->with('ok', 'Verificação em duas etapas ativada. Guarde os códigos de recuperação.');
    }

    public function desativar(Request $request): RedirectResponse
    {
        $u = $request->user();
        $request->validate([
            'password' => ['required', 'string', 'max:128'],
            'codigo'   => ['required', 'string', 'max:40'],
        ], ['password.required' => 'Digite sua senha.', 'codigo.required' => 'Digite o código do app ou um de recuperação.']);

        if ($erro = $this->senhaErrada($request, $u)) {
            return $erro;
        }
        if ($u->temDoisFatores() && ! $this->aceitarCodigo($u, (string) $request->input('codigo'))) {
            return back()->withErrors(['codigo' => 'Código incorreto.']);
        }

        $u->forceFill([
            'dois_fatores_segredo' => null, 'dois_fatores_recuperacao' => null,
            'dois_fatores_ativo_em' => null, 'dois_fatores_ultimo_passo' => null,
        ])->save();
        Log::warning('Verificação em duas etapas desativada', ['user_id' => $u->id, 'ip' => $request->ip()]);

        return redirect()->route('seguranca')->with('ok', 'Verificação em duas etapas desativada.');
    }

    public function novosCodigosRecuperacao(Request $request): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->temDoisFatores(), 409);
        $request->validate(['password' => ['required', 'string', 'max:128']], ['password.required' => 'Digite sua senha.']);
        if ($erro = $this->senhaErrada($request, $u)) {
            return $erro;
        }

        $codigos = $this->novosCodigos();
        $u->forceFill(['dois_fatores_recuperacao' => array_map([$this, 'hashCodigo'], $codigos)])->save();

        return redirect()->route('seguranca')
            ->with('codigos_recuperacao', $codigos)
            ->with('ok', 'Códigos novos gerados. Os antigos não valem mais.');
    }

    // ---------------------------------------------------------------- apoio

    /** Código do app (uma vez cada) ou de recuperação (some depois de usado). */
    private function aceitarCodigo(User $u, string $codigo): bool
    {
        $limpo = preg_replace('/\s+/', '', $codigo);
        if (preg_match('/^\d{6}$/', $limpo)) {
            $passo = Totp::verificar((string) $u->dois_fatores_segredo, $limpo, $u->dois_fatores_ultimo_passo !== null ? (int) $u->dois_fatores_ultimo_passo : null);
            if ($passo === null) {
                return false;
            }
            $u->forceFill(['dois_fatores_ultimo_passo' => $passo])->save();

            return true;
        }

        $hash = $this->hashCodigo($codigo);
        $lista = $u->dois_fatores_recuperacao ?? [];
        foreach ($lista as $i => $h) {
            if (is_string($h) && hash_equals($h, $hash)) {
                unset($lista[$i]);
                $u->forceFill(['dois_fatores_recuperacao' => array_values($lista)])->save();
                Log::notice('Código de recuperação usado', ['user_id' => $u->id, 'restantes' => count($lista)]);

                return true;
            }
        }

        return false;
    }

    private function senhaErrada(Request $request, User $u): ?RedirectResponse
    {
        $chave = 'dois-fatores:senha:' . $u->id;
        if (RateLimiter::tooManyAttempts($chave, 5)) {
            return back()->withErrors(['password' => 'Muitas tentativas. Espere um minuto.']);
        }
        if (! Hash::check((string) $request->input('password'), $u->password)) {
            RateLimiter::hit($chave, 60);

            return back()->withErrors(['password' => 'Senha incorreta.']);
        }

        return null;
    }

    /** @return list<string> no formato ABCDE-FGHIJ (sem 0/O, 1/I/L) */
    private function novosCodigos(): array
    {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $codigos = [];
        while (count($codigos) < self::QTD_RECUPERACAO) {
            $c = '';
            for ($i = 0; $i < 10; $i++) {
                $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigos[] = substr($c, 0, 5) . '-' . substr($c, 5);
        }

        return $codigos;
    }

    private function hashCodigo(string $codigo): string
    {
        $normal = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));

        return hash_hmac('sha256', $normal, (string) config('app.key'));
    }

    private function pendente(Request $request): ?array
    {
        $p = $request->session()->get('dois_fatores');
        if (! is_array($p) || empty($p['id']) || ($p['ate'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $p;
    }
}
