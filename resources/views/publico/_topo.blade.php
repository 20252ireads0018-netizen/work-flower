{{-- Cabeçalho das páginas públicas (só aparece para quem não está logado) --}}
<header class="cab-bg"><meta charset="utf-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-8 h-16 flex items-center justify-between gap-4">
        <a href="{{ \Illuminate\Support\Facades\Route::has('inicio') ? route('inicio') : url('/') }}" aria-label="Work Flower, página inicial">
            @include('partials.marca', ['texto' => 'text-xl'])
        </a>

        <nav class="flex items-center gap-2" aria-label="Acesso">
            <a href="{{ route('login') }}" class="btn-cab">Entrar</a>
            <a href="{{ route('registro') }}" class="btn !py-1.5">Criar conta</a>
        </nav>
    </div>
</header>

