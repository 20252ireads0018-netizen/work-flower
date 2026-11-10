{{-- Botão "Baixar o app" (public/js/app-instalavel.js): instala com 1 clique quando o navegador oferece
     (Chrome, Edge, Android) ou abre a janela com o instalador do Windows e os passos do aparelho.
     Some sozinho dentro do app instalado. Parâmetros: classe (padrão "btn-sec"), texto, so_icone. --}}
@php
    $classe = $classe ?? 'btn-sec';
    $texto = $texto ?? 'Baixar o app';
    $soIcone = $so_icone ?? false;
@endphp
<button type="button" data-wf-instalar class="{{ $classe }} inline-flex items-center gap-2"
        title="Instalar o Work Flower como app (Windows, Android, iPhone, Mac)" @if ($soIcone) aria-label="{{ $texto }}" @endif>
    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v10m0 0l-4-4m4 4l4-4"/><path d="M5 15v3a2 2 0 002 2h10a2 2 0 002-2v-3"/></svg>
    @unless ($soIcone)<span>{{ $texto }}</span>@endunless
</button>
