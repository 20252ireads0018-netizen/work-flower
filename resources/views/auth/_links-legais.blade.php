{{-- Links legais discretos para as telas de entrar e criar conta.
     Usa Route::has + url() para não quebrar a tela de login caso as rotas ainda não existam.
     Passe ['novaAba' => true] para abrir em outra aba (assim quem está preenchendo o cadastro não perde os dados). --}}
@php
    $legais = [
        'Política de Privacidade' => \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade'),
        'Termos de Uso'           => \Illuminate\Support\Facades\Route::has('termos') ? route('termos') : url('/termos'),
        'Contato'                 => \Illuminate\Support\Facades\Route::has('contato') ? route('contato') : url('/contato'),
    ];
@endphp
<nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 pt-3 text-xs texto-2" aria-label="Informações legais">
    @foreach ($legais as $nome => $url)
        <a href="{{ $url }}" class="link-prim" @if ($novaAba ?? false) target="_blank" rel="noopener" @endif>{{ $nome }}</a>
    @endforeach
</nav>

