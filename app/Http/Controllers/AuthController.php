<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /** Tentativas de login por e-mail + IP, por minuto. */
    private const LOGIN_POR_CONTA = 5;

    /** Tentativas de login por IP (qualquer e-mail), por minuto: freia quem testa muitos e-mails. */
    private const LOGIN_POR_IP = 20;

    /** Contas novas por IP, por hora. */
    private const REGISTROS_POR_IP = 5;

    public function formLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        // Normaliza o e-mail para a chave do limite não ser contornada com maiúsculas/espaços
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $credenciais = $request->validate([
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'string', 'max:128'],
        ], [
            'email.required'    => 'Informe o e-mail.',
            'email.email'       => 'Informe um e-mail válido.',
            'email.max'         => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
            'password.max'      => 'E-mail ou senha incorretos.',
        ]);

        $chaveConta = 'login:conta:' . sha1($credenciais['email'] . '|' . $request->ip());
        $chaveIp    = 'login:ip:' . $request->ip();

        // Bloqueado: não consulta o banco nem confere a senha
        if (RateLimiter::tooManyAttempts($chaveConta, self::LOGIN_POR_CONTA)
            || RateLimiter::tooManyAttempts($chaveIp, self::LOGIN_POR_IP)) {
            event(new Lockout($request));

            $segundos = max(
                RateLimiter::availableIn($chaveConta),
                RateLimiter::availableIn($chaveIp)
            );

            Log::warning('Login bloqueado por excesso de tentativas', [
                'email' => $credenciais['email'],
                'ip'    => $request->ip(),
            ]);

            return back()
                ->withErrors(['email' => $this->mensagemEspera($segundos)])
                ->onlyInput('email');
        }

        if (Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            RateLimiter::clear($chaveConta);
            $request->session()->regenerate();

            return redirect()->intended(route('painel'));
        }

        // Falha: conta nas duas janelas (60 segundos cada)
        RateLimiter::hit($chaveConta, 60);
        RateLimiter::hit($chaveIp, 60);

        return back()
            ->withErrors(['email' => 'E-mail ou senha incorretos.'])
            ->onlyInput('email');
    }

    public function formRegistro(): View
    {
        return view('auth.registro');
    }

    public function registro(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $chaveIp = 'registro:ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($chaveIp, self::REGISTROS_POR_IP)) {
            $minutos = (int) ceil(RateLimiter::availableIn($chaveIp) / 60);

            return back()
                ->withErrors(['email' => "Muitas contas criadas deste aparelho. Tente de novo em {$minutos} " . ($minutos === 1 ? 'minuto.' : 'minutos.')])
                ->onlyInput('name', 'email');
        }

        $dados = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => [
                'bail', 'required', 'string', 'min:10', 'max:128', 'confirmed',
                // letras + números e fora de listas de senhas vazadas (consulta a API do Have I Been Pwned;
                // se ela estiver fora do ar, a regra deixa passar)
                Password::min(10)->letters()->numbers()->uncompromised(),
            ],
        ], [
            'name.required'          => 'Informe seu nome.',
            'email.required'         => 'Informe o e-mail.',
            'email.email'            => 'Informe um e-mail válido.',
            'email.unique'           => 'Este e-mail já tem conta. Entre com ele.',
            'password.required'      => 'Crie uma senha.',
            'password.min'           => 'A senha precisa ter ao menos 10 caracteres.',
            'password.max'           => 'A senha pode ter no máximo 128 caracteres.',
            'password.confirmed'     => 'As senhas não são iguais.',
            'password.letters'       => 'A senha precisa ter ao menos uma letra.',
            'password.numbers'       => 'A senha precisa ter ao menos um número.',
            'password.uncompromised' => 'Esta senha apareceu em vazamentos de dados. Escolha outra.',
        ]);

        // Conta a criação só quando a validação passou (1 hora de janela)
        RateLimiter::hit($chaveIp, 3600);

        $usuario = User::create([
            'name'     => $dados['name'],
            'email'    => $dados['email'],
            'password' => Hash::make($dados['password']),
        ]);

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('painel');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function mensagemEspera(int $segundos): string
    {
        if ($segundos <= 60) {
            return "Muitas tentativas de login. Tente de novo em {$segundos} " . ($segundos === 1 ? 'segundo.' : 'segundos.');
        }

        $minutos = (int) ceil($segundos / 60);

        return "Muitas tentativas de login. Tente de novo em {$minutos} minutos.";
    }
}
