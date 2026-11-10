{{-- Script e estilo dos blocos Discord, Notion e Textos rápidos (partials/blocos-integracoes; js/blocos-integracoes.js).
     Incluído por partials/graficos, depois dos cartões de integração. Cada tipo só aparece no "+ Bloco" se a rota existir. --}}
@php
    $wfBi = [
        'discord'    => \Illuminate\Support\Facades\Route::has('integracoes.discord.enviar') ? url('/integracoes/discord') : '',
        'notion'     => \Illuminate\Support\Facades\Route::has('integracoes.notion.paginas') ? url('/integracoes/notion') : '',
        'negocios'   => \Illuminate\Support\Facades\Route::has('areas.negocios') ? route('areas.negocios') : '',
        'tarefas'    => \Illuminate\Support\Facades\Route::has('cartoes.tarefas') ? url('/cartoes/tarefas') : '',
        // só os textos rápidos no Windows (arquivo pequeno, sem reinstalar o app)
        'instalador' => \Illuminate\Support\Facades\Route::has('textos.windows') ? route('textos.windows') : '',
        // os textos rápidos moram nas preferências da conta (public/js/expansor.js, carregado pelo layout para quem está logado)
        'textos'     => auth()->check() ? '1' : '',
    ];
@endphp
<script src="{{ asset('js/blocos-integracoes.js') }}?v={{ @filemtime(public_path('js/blocos-integracoes.js')) }}"
        data-discord="{{ $wfBi['discord'] }}" data-notion="{{ $wfBi['notion'] }}" data-negocios="{{ $wfBi['negocios'] }}"
        data-tarefas="{{ $wfBi['tarefas'] }}" data-instalador="{{ $wfBi['instalador'] }}" data-textos="{{ $wfBi['textos'] }}"></script>

<style>
    .bi { height: 100%; display: flex; flex-direction: column; gap: .5rem; min-height: 0; font-size: .75rem; }
    .bi-corpo { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: .45rem; }
    .bi-barra { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
    .bi-titulo { font-weight: 600; font-size: .85rem; line-height: 1.25; }
    .bi-lista { flex: 1; min-height: 3rem; overflow: auto; display: flex; flex-direction: column; gap: .3rem; margin: 0; padding: 0; list-style: none; }
    .bi-item { display: flex; align-items: center; gap: .5rem; padding: .4rem .5rem; border-radius: .6rem; border: 1px solid var(--linha);
        background: var(--superficie-2, transparent); transition: border-color .15s ease; }
    .bi-item:hover, .bi-item:focus-visible { border-color: color-mix(in srgb, var(--prim) 55%, var(--linha)); outline: none; }
    .bi-clique { cursor: pointer; }
    .bi-acoes { display: flex; gap: .15rem; opacity: .6; transition: opacity .15s ease; }
    .bi-item:hover .bi-acoes, .bi-item:focus-within .bi-acoes { opacity: 1; }
    .bi-ico { width: 1.2rem; flex: none; display: grid; place-items: center; font-size: .95rem; }
    .bi-pilula { flex: none; font-size: .6rem; padding: .1rem .4rem; border-radius: 9999px; background: color-mix(in srgb, var(--prim) 15%, transparent); color: var(--prim); }
    .bi-kbd { flex: none; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bi-vazio { color: var(--tinta-2); padding: .4rem; text-align: center; }
    .bi-erro { color: #dc2626; overflow-wrap: anywhere; }
    .bi-conectar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: .5rem; padding: .75rem; }
    .bi-conectar .texto-2 { max-width: 22rem; }
    .bi-logo { width: 2.6rem; height: 2.6rem; border-radius: .8rem; display: grid; place-items: center; font-size: 1.2rem; font-weight: 700; color: var(--c);
        background: color-mix(in srgb, var(--c) 15%, transparent); }
    .bi-rodape { display: flex; gap: .35rem; align-items: center; }
    .bi-texto { min-height: 4.5rem; resize: none; }
    .bi-leitura { flex: 1; min-height: 4rem; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; padding: .6rem;
        border-radius: .6rem; border: 1px solid var(--linha); font-size: .8rem; user-select: text; cursor: text; }
    .bi-status { padding: .45rem .6rem; border-radius: .6rem; line-height: 1.4; }
    .bi-espera { background: color-mix(in srgb, #d97706 14%, transparent); }
    .bi-form { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: .4rem; padding: .5rem; border-radius: .6rem; border: 1px dashed var(--linha); }
</style>
