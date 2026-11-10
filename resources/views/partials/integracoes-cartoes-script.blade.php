{{-- Script e estilo dos cartões Google Drive, Gmail, YouTube e Lembretes (partials/integracoes-cartoes; js/integracoes-cartoes.js).
     Incluído por partials/graficos, depois do cartoes-extra.js. Cada tipo só aparece no menu se a rota existir. --}}
@php
    $wfIg = [
        'drive'     => \Illuminate\Support\Facades\Route::has('google.drive') ? route('google.drive') : '',
        'gmail'     => \Illuminate\Support\Facades\Route::has('emails.cartao') ? url('/negocios/emails') : '',
        'yt'        => \Illuminate\Support\Facades\Route::has('youtube.cartao') ? url('/youtube') : '',
        'lembretes' => \Illuminate\Support\Facades\Route::has('lembretes.cartao') ? url('/lembretes') : '',
        'cal'       => \Illuminate\Support\Facades\Route::has('calendario.criar') ? url('/calendario/eventos') : '',
        'tarefas'   => \Illuminate\Support\Facades\Route::has('cartoes.tarefas') ? url('/cartoes/tarefas') : '',
    ];
@endphp
<script src="{{ asset('js/integracoes-cartoes.js') }}?v={{ @filemtime(public_path('js/integracoes-cartoes.js')) }}"
        data-drive="{{ $wfIg['drive'] }}" data-gmail="{{ $wfIg['gmail'] }}" data-yt="{{ $wfIg['yt'] }}" data-lembretes="{{ $wfIg['lembretes'] }}"
        data-cal="{{ $wfIg['cal'] }}" data-tarefas="{{ $wfIg['tarefas'] }}"></script>

{{-- volta do login do Google (YouTube) com erro: o layout só mostra o "ok" --}}
@if (session('erro'))
    <div class="ig-aviso-erro" role="alert" x-data="{ v: true }" x-show="v" x-init="setTimeout(() => v = false, 9000)" @click="v = false">{{ session('erro') }}</div>
@endif

<style>
    .ig { height: 100%; display: flex; flex-direction: column; gap: .5rem; min-height: 0; font-size: .75rem; }
    .ig-corpo { flex: 1; min-height: 0; display: flex; flex-direction: column; gap: .5rem; }
    .ig-barra { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
    .ig-titulo { font-weight: 600; font-size: .85rem; line-height: 1.25; }
    .ig-lista { flex: 1; min-height: 3rem; overflow: auto; display: flex; flex-direction: column; gap: .3rem; margin: 0; padding: 0; list-style: none; }
    .ig-item { display: flex; align-items: center; gap: .55rem; padding: .4rem .5rem; border-radius: .6rem; border: 1px solid var(--linha);
        background: var(--superficie-2, transparent); cursor: grab; transition: border-color .15s ease, transform .15s ease; }
    .ig-item:hover, .ig-item:focus-visible { border-color: color-mix(in srgb, var(--prim) 55%, var(--linha)); outline: none; }
    .ig-item:active { cursor: grabbing; }
    .ig-clique { cursor: pointer; }
    .ig-novo { border-left: 3px solid var(--prim); }
    .ig-novo .font-medium { font-weight: 700; }
    .ig-acoes { display: flex; gap: .15rem; opacity: .55; transition: opacity .15s ease; }
    .ig-item:hover .ig-acoes, .ig-item:focus-within .ig-acoes { opacity: 1; }
    .ig-ico { width: 1.15rem; height: 1.15rem; flex: none; display: grid; place-items: center; font-size: .95rem; }
    .ig-avatar { width: 2.1rem; height: 2.1rem; border-radius: 9999px; flex: none; object-fit: cover; }
    .ig-thumb-caixa { position: relative; flex: none; }
    .ig-thumb { width: 6.2rem; aspect-ratio: 16 / 9; object-fit: cover; border-radius: .4rem; flex: none; background: var(--linha); display: block; }
    .ig-duracao { position: absolute; right: .2rem; bottom: .2rem; font-size: .6rem; padding: 0 .25rem; border-radius: .25rem; background: rgba(0, 0, 0, .78); color: #fff; }
    .ig-2linhas { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
    .ig-pilula { flex: none; font-size: .6rem; padding: .1rem .4rem; border-radius: 9999px; background: color-mix(in srgb, var(--prim) 15%, transparent); color: var(--prim); }
    .ig-vazio { color: var(--tinta-2); padding: .4rem; text-align: center; }
    .ig-erro { color: #dc2626; overflow-wrap: anywhere; }
    .ig-conectar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: .5rem; padding: .75rem; }
    .ig-conectar .texto-2 { max-width: 22rem; }
    .ig-logo { width: 2.6rem; height: 2.6rem; border-radius: .8rem; display: grid; place-items: center; font-size: 1.2rem; color: var(--c);
        background: color-mix(in srgb, var(--c) 15%, transparent); }
    .ig-linha-check { display: flex; align-items: center; gap: .4rem; cursor: pointer; }
    .ig-rodape { display: flex; gap: .35rem; align-items: center; }
    .ig-leitura { flex: 1; min-height: 4rem; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; padding: .6rem;
        border-radius: .6rem; border: 1px solid var(--linha); font-size: .8rem; user-select: text; cursor: text; }
    .ig-player { position: relative; width: 100%; aspect-ratio: 16 / 9; border-radius: .6rem; overflow: hidden; background: #000; flex: none; max-height: 70%; }
    .ig-player iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .ig-status { padding: .45rem .6rem; border-radius: .6rem; line-height: 1.4; }
    .ig-ok { background: color-mix(in srgb, #16a34a 13%, transparent); }
    .ig-espera { background: color-mix(in srgb, #d97706 14%, transparent); }
    .ig-form { display: flex; flex-direction: column; gap: .35rem; padding: .5rem; border-radius: .6rem; border: 1px dashed var(--linha); }
    .ig-dia { flex: none; width: 2.3rem; text-align: center; line-height: 1.05; display: flex; flex-direction: column; color: var(--prim); }
    .ig-dia b { font-size: 1rem; }
    .ig-dia span { font-size: .6rem; text-transform: uppercase; }
    .ig-email { display: flex; align-items: center; gap: .2rem; cursor: pointer; white-space: nowrap; }
    /* Drive: caminho da pasta aberta; Gmail/YouTube: formulários de enviar e editar */
    .ig-caminho { flex: 1; min-width: 0; display: flex; align-items: center; gap: .2rem; overflow: hidden; white-space: nowrap; }
    .ig-migalha-par { display: inline-flex; align-items: center; gap: .2rem; min-width: 0; }
    .ig-migalha { max-width: 9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: .1rem .3rem; border-radius: .35rem; color: var(--prim); }
    .ig-migalha:hover, .ig-migalha:focus-visible { background: color-mix(in srgb, var(--prim) 12%, transparent); outline: none; }
    .ig-perigo { color: #dc2626; }
    .ig-campo { display: grid; grid-template-columns: 4rem 1fr; align-items: center; gap: .35rem; color: var(--tinta-2); }
    .ig-texto { flex: 1; min-height: 6rem; resize: vertical; line-height: 1.45; }
    .ig-form { flex: none; }
    form.ig-corpo.ig-form { flex: 1; overflow-y: auto; }
    .ig-corpo:has(> .ig-player) { overflow-y: auto; }
    .ig-aviso-erro { position: fixed; z-index: 60; left: 50%; bottom: 1.2rem; transform: translateX(-50%); max-width: min(92vw, 34rem); padding: .7rem 1rem;
        border-radius: .8rem; background: #b91c1c; color: #fff; font-size: .85rem; box-shadow: 0 10px 30px -10px rgba(0, 0, 0, .5); cursor: pointer; }
</style>
