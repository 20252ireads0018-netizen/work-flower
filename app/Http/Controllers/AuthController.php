<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function formLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required'    => 'Informe o e-mail.',
            'email.email'       => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ]);

        if (Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('painel'));
        }

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
        $dados = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required'      => 'Informe seu nome.',
            'email.required'     => 'Informe o e-mail.',
            'email.email'        => 'Informe um e-mail válido.',
            'email.unique'       => 'Este e-mail já tem conta. Entre com ele.',
            'password.required'  => 'Crie uma senha.',
            'password.min'       => 'A senha precisa ter ao menos 8 caracteres.',
            'password.confirmed' => 'As senhas não são iguais.',
        ]);

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
}
