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
        data-tarefas="{{ $wfBi['tarefas'] }}" data-instalador="{{ $wfBi['instalador'] }}" data-textos="{{ $wfBi['textos'] }}"
        {{-- volta do "Conectar com o Discord" (IntegracaoController::retorno) para esta página --}}
        data-dc-ok="{{ session('discord_ok') }}" data-dc-erro="{{ session('discord_erro') }}"></script>

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
    /* mini Notion: árvore, editor de blocos (cada bloco é um textarea que salva ao sair) */
    .bi-seta { width: 1.1rem; flex: none; border-radius: .3rem; color: var(--tinta-2); }
    .bi-seta:hover { background: color-mix(in srgb, var(--prim) 14%, transparent); }
    .bi-alvo { outline: 2px dashed var(--prim); outline-offset: 1px; background: color-mix(in srgb, var(--prim) 12%, transparent); }
    .bi-titulo { font-weight: 600; padding: .15rem .4rem !important; }
    .bi-editor { white-space: normal; display: flex; flex-direction: column; gap: .15rem; }
    .bi-bloco { position: relative; }
    .bi-txt { width: 100%; resize: none; overflow: hidden; background: transparent; border: 1px solid transparent; border-radius: .4rem; padding: .15rem .3rem; color: inherit; font: inherit; line-height: 1.45; }
    .bi-txt:hover { border-color: var(--linha); }
    .bi-txt:focus { outline: none; border-color: var(--prim); background: color-mix(in srgb, var(--prim) 6%, transparent); }
    .bi-b-heading_1 .bi-txt { font-size: 1.35em; font-weight: 700; }
    .bi-b-heading_2 .bi-txt { font-size: 1.18em; font-weight: 700; }
    .bi-b-heading_3 .bi-txt { font-size: 1.05em; font-weight: 700; }
    .bi-b-quote .bi-txt { border-left: 3px solid var(--linha); border-radius: 0 .4rem .4rem 0; font-style: italic; }
    .bi-b-callout > div { background: color-mix(in srgb, var(--prim) 8%, transparent); border-radius: .5rem; padding: .2rem; }
    .bi-b-code .bi-txt { font-family: ui-monospace, monospace; font-size: .85em; background: color-mix(in srgb, var(--tinta) 6%, transparent); }
    .bi-marca { flex: none; padding-top: .2rem; }
    .bi-bloco-acoes { display: flex; gap: .15rem; opacity: 0; transition: opacity .15s; }
    .bi-bloco:hover .bi-bloco-acoes, .bi-bloco:focus-within .bi-bloco-acoes { opacity: 1; }
    .bi-sub { text-align: left; padding: .2rem .4rem; border-radius: .4rem; text-decoration: underline; text-underline-offset: 3px; }
    .bi-sub:hover { background: color-mix(in srgb, var(--prim) 12%, transparent); }
    .bi-novo { display: flex; gap: .3rem; align-items: center; margin-top: .4rem; padding-top: .4rem; border-top: 1px dashed var(--linha); }
    .bi-perigo:hover { color: #e5484d; }
    .bi-status { padding: .45rem .6rem; border-radius: .6rem; line-height: 1.4; }
    .bi-espera { background: color-mix(in srgb, #d97706 14%, transparent); }
    .bi-form { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: .4rem; padding: .5rem; border-radius: .6rem; border: 1px dashed var(--linha); }
</style>
