@extends('layouts.publico')

@section('titulo', 'Contato')
@section('subtitulo', 'Fale com a equipe do Work Flower')

@section('texto')
<div class="space-y-6 leading-relaxed text-[0.95rem]">
    <p>
        Tem uma dúvida, sugestão, problema com a sua conta ou quer falar sobre privacidade e dados?
        Escreva para nós. Respondemos o mais rápido possível.
    </p>

    <div class="card p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <span class="icone" aria-hidden="true">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm texto-2">E-mail</p>
            <a href="mailto:victorbarretophp@gmail.com" class="link-prim text-lg break-all">victorbarretophp@gmail.com</a>
        </div>
        <a href="mailto:victorbarretophp@gmail.com?subject=Contato%20Work%20Flower" class="btn">Enviar e-mail</a>
    </div>

    <div>
        <h2 class="titulo text-xl mb-3">Sobre o que você quer falar?</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li><strong>Suporte:</strong> problemas para entrar, erros no serviço ou dúvidas de uso. Conte o que aconteceu e, se puder, o aparelho e o navegador que usa.</li>
            <li><strong>Planos e pagamentos:</strong> dúvidas sobre o plano Pró, cobranças ou cancelamento.</li>
            <li><strong>Privacidade e dados (LGPD):</strong> pedidos de acesso, correção ou exclusão de dados. Veja a
                <a href="{{ \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade') }}" class="link-prim">Política de Privacidade</a>.
            </li>
            <li><strong>Sugestões e parcerias:</strong> ideias de novos recursos ou propostas de colaboração.</li>
        </ul>
    </div>

    <p class="text-sm texto-2">
        Por segurança, nunca envie a sua senha por e-mail. Nós não pediremos essa informação.
    </p>
</div>
@endsection

