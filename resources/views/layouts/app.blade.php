@php
    use App\Models\User;

    $usuario = auth()->user();
    $prim    = $usuario?->cor_primaria  ?? User::COR_PRIMARIA_PADRAO;
    $cab     = $usuario?->cor_cabecalho ?? User::COR_CABECALHO_PADRAO;

    // ===== Fundo personalizado (cor, imagem ou GIF) =====
    $fundoIni = ['cor' => null, 'ajuste' => 'cover', 'escurecer' => 55, 'desfoque' => 0, 'imagem' => false, 'versao' => 0];

    if ($usuario) {
        $fundoCfg = json_decode((string) \App\Models\CorpoDado::where('user_id', $usuario->id)->where('chave', 'fundo')->value('valor'), true);
        if (is_array($fundoCfg)) {
            if (isset($fundoCfg['cor']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $fundoCfg['cor'])) {
                $fundoIni['cor'] = $fundoCfg['cor'];
            }
            if (in_array($fundoCfg['ajuste'] ?? '', ['cover', 'contain', 'repeat'], true)) {
                $fundoIni['ajuste'] = $fundoCfg['ajuste'];
            }
            $fundoIni['escurecer'] = max(0, min(90, (int) ($fundoCfg['escurecer'] ?? 55)));
            $fundoIni['desfoque']  = max(0, min(20, (int) ($fundoCfg['desfoque'] ?? 0)));
        }

        // Só busca id e data: a imagem em si é entregue por /fundo/imagem (com cache do navegador)
        $fundoLinhaImg = \App\Models\CorpoDado::where('user_id', $usuario->id)->where('chave', 'fundo.img')->first(['id', 'updated_at']);
        $fundoIni['imagem'] = (bool) $fundoLinhaImg;
        $fundoIni['versao'] = $fundoLinhaImg?->updated_at?->timestamp ?? 0;
    }

    $fundoUrl    = \Illuminate\Support\Facades\Route::has('fundo.imagem') ? route('fundo.imagem') : '';
    $fundoSalvar = \Illuminate\Support\Facades\Route::has('fundo.salvar') ? route('fundo.salvar') : '';

    $fundoAj = $fundoIni['ajuste'];
    $fundoEstiloImg = $fundoIni['imagem']
        ? "background-image:url('{$fundoUrl}?v={$fundoIni['versao']}');"
            . 'background-size:' . ($fundoAj === 'repeat' ? 'auto' : $fundoAj) . ';'
            . 'background-repeat:' . ($fundoAj === 'repeat' ? 'repeat' : 'no-repeat') . ';'
            . ($fundoIni['desfoque'] ? "filter:blur({$fundoIni['desfoque']}px);inset:-40px;" : '')
        : 'display:none;';
    $fundoEstiloVeu = $fundoIni['imagem']
        ? "background:color-mix(in srgb, var(--papel) {$fundoIni['escurecer']}%, transparent);"
        : 'display:none;';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="biblioteca-url" content="{{ \Illuminate\Support\Facades\Route::has('biblioteca.index') ? route('biblioteca.index') : '' }}">
    <title>Work Flower · @yield('titulo', 'Painel')</title>

    <script src="{{ asset('js/biblioteca.js') }}?v={{ @filemtime(public_path('js/biblioteca.js')) }}"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ================= TEMA (escuro) =================
           Só estas quatro variáveis vêm do usuário; o resto é derivado delas.
           Superfícies, texto e linhas são fixos para manter o clima escuro. */
        :root {
            --prim: {{ $prim }};
            --cab: {{ $cab }};
            --on-prim: {{ User::contraste($prim) }};
            --on-cab: {{ User::contraste($cab) }};

            --prim-forte: color-mix(in srgb, var(--prim) 72%, white);
            --prim-suave: color-mix(in srgb, var(--prim) 16%, var(--superficie));
            --prim-linha: color-mix(in srgb, var(--prim) 40%, var(--superficie));
            --papel: color-mix(in srgb, var(--prim) 2%, #090c10);
            --superficie: color-mix(in srgb, var(--prim) 2%, #10151b);
            --superficie-2: color-mix(in srgb, var(--prim) 3%, #151b23);
            --tinta: #e8ecf1;
            --tinta-2: #8c97a6;
            --linha: #222a34;
            --borda-campo: #2d3641;
        }
        @if ($fundoIni['cor'])
        :root { --papel: {{ $fundoIni['cor'] }}; }
        @endif

        [x-cloak] { display: none !important; }
        html { -webkit-font-smoothing: antialiased; color-scheme: dark; }
        body { background: var(--papel); color: var(--tinta); font-family: 'DM Sans', system-ui, sans-serif; }
        .titulo { font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif; font-weight: 700; letter-spacing: -0.02em; }
        .texto-2 { color: var(--tinta-2); }
        ::selection { background: var(--prim); color: var(--on-prim); }
        :focus-visible { outline: 2px solid var(--prim); outline-offset: 2px; }
        body .text-red-700 { color: #f87171; }

        /* Fundo personalizado: a imagem (ou GIF, com desfoque opcional) e um véu que escurece */
        #fundo-img { position: fixed; inset: 0; z-index: -1; pointer-events: none; background-position: center; }
        #fundo-veu { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .fundo-cor {
            width: 2rem; height: 2rem; padding: 0; border-radius: 9999px; border: 1px solid var(--linha);
            background: none; cursor: pointer; overflow: hidden; flex-shrink: 0;
        }
        .fundo-cor::-webkit-color-swatch-wrapper { padding: 0; }
        .fundo-cor::-webkit-color-swatch { border: 0; border-radius: 9999px; }
        .fundo-cor::-moz-color-swatch { border: 0; border-radius: 9999px; }
        input[type=range] { accent-color: var(--prim); }

        /* Cabeçalho */
        .cab-bg { background: var(--cab); color: var(--on-cab); }
        header.cab-bg { border-bottom: 1px solid color-mix(in srgb, var(--on-cab) 12%, transparent); }
        .nav-link { display: flex; align-items: center; border-bottom: 2px solid transparent; opacity: .7; transition: opacity .15s; }
        .nav-link:hover { opacity: 1; }
        .nav-link.ativo { opacity: 1; border-color: var(--prim); font-weight: 600; }
        .nav-mobile a { display: block; padding: .5rem .75rem; border-radius: .375rem; opacity: .8; }
        .nav-mobile a.ativo { background: color-mix(in srgb, var(--on-cab) 12%, transparent); opacity: 1; font-weight: 600; }

        /* Painel de entrada: malha discreta e brilho atrás da flor */
        .painel-tech {
            position: relative; overflow: hidden;
            background-color: var(--cab);
            background-image:
                radial-gradient(55% 45% at 50% 44%, color-mix(in srgb, var(--prim) 14%, transparent), transparent 70%),
                linear-gradient(color-mix(in srgb, var(--on-cab) 5%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in srgb, var(--on-cab) 5%, transparent) 1px, transparent 1px);
            background-size: auto, 32px 32px, 32px 32px;
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--on-cab) 8%, transparent);
        }
        .flor-giro { transform-origin: 0 0; animation: flor-giro 180s linear infinite; }
        @keyframes flor-giro { to { transform: rotate(360deg); } }

        /* Botões */
        .btn, .btn-sec, .btn-cab {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            font-size: .875rem; font-weight: 600; border-radius: .5rem; padding: .6rem 1rem;
            transition: background-color .15s, border-color .15s;
        }
        .btn { background: var(--prim); color: var(--on-prim); }
        .btn:hover { background: var(--prim-forte); }
        .btn-sec { background: var(--superficie); color: var(--prim-forte); border: 1px solid var(--prim-linha); }
        .btn-sec:hover { background: var(--prim-suave); }
        .btn-cab { padding: .4rem .75rem; color: inherit; border: 1px solid color-mix(in srgb, var(--on-cab) 30%, transparent); }
        .btn-cab:hover { background: color-mix(in srgb, var(--on-cab) 12%, transparent); }
        .link-prim { color: var(--prim-forte); font-weight: 500; }
        .link-prim:hover { text-decoration: underline; }

        /* Campos */
        .campo {
            width: 100%; background: var(--superficie-2); color: var(--tinta); border: 1px solid var(--borda-campo); border-radius: .5rem;
            padding: .6rem .75rem; font-size: .9rem;
            transition: border-color .15s, box-shadow .15s;
        }
        .campo::placeholder { color: var(--tinta-2); opacity: .8; }
        .campo:focus { outline: none; border-color: var(--prim); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent); }
        .rotulo { display: block; font-size: .85rem; font-weight: 500; margin-bottom: .35rem; }
        .erro { color: #f87171; font-size: .8rem; margin-top: .3rem; }
        .aviso-ok {
            background: var(--prim-suave); border: 1px solid var(--prim-linha); color: var(--prim-forte);
            border-radius: .5rem; padding: .65rem 1rem; font-size: .875rem; margin-bottom: 1.25rem;
            max-height: 10rem; overflow: hidden;
            transition: opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease, border-width .4s ease;
        }
        .aviso-ok.saindo {
            opacity: 0; max-height: 0; margin-bottom: 0;
            padding-top: 0; padding-bottom: 0; border-width: 0;
        }
        /* Checkbox no tema: caixa escura, marcada com a cor de destaque */
        input[type=checkbox] {
            appearance: none; -webkit-appearance: none;
            display: inline-grid; place-content: center;
            width: 1.15rem; height: 1.15rem; flex-shrink: 0;
            background: var(--superficie-2);
            border: 1px solid var(--borda-campo);
            border-radius: .3rem;
            cursor: pointer; vertical-align: middle;
            transition: background-color .15s, border-color .15s, box-shadow .15s;
        }
        input[type=checkbox]::after {
            content: "";
            width: .3rem; height: .6rem;
            border: solid var(--on-prim);
            border-width: 0 2px 2px 0;
            transform: translateY(-1px) rotate(45deg) scale(0);
            transition: transform .12s ease;
        }
        input[type=checkbox]:hover { border-color: var(--prim-linha); }
        input[type=checkbox]:checked {
            background: var(--prim);
            border-color: var(--prim);
        }
        input[type=checkbox]:checked::after {
            transform: translateY(-1px) rotate(45deg) scale(1);
        }
        input[type=checkbox]:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        input[type=checkbox]:disabled { opacity: .5; cursor: not-allowed; }

        /* Cards */
        .card { background: var(--superficie); border: 1px solid var(--linha); border-radius: .75rem; transition: border-color .2s; }
        a.card:hover { border-color: var(--prim); }
        .icone { flex-shrink: 0; width: 2.75rem; height: 2.75rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: var(--prim-suave); color: var(--prim-forte); }
        .barra { height: 6px; border-radius: 9999px; background: var(--prim-suave); overflow: hidden; }
        .barra span { display: block; height: 100%; background: var(--prim); border-radius: 9999px; }

        /* Tabelas */
        .tabela-wrap { background: var(--superficie); border: 1px solid var(--linha); border-radius: .75rem; overflow: hidden; }
        .tabela { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .tabela thead { background: var(--cab); color: var(--on-cab); }
        .tabela th { text-align: left; font-weight: 500; padding: .75rem 1rem; }
        .tabela td { padding: .75rem 1rem; border-top: 1px solid var(--linha); vertical-align: middle; }
        .tabela tbody tr:hover { background: var(--prim-suave); }
        .select-status { font-size: .8rem; padding: .3rem .5rem; border-radius: .375rem; border: 1px solid var(--prim-linha); background: var(--prim-suave); color: var(--prim-forte); }

        /* Seletor de tema */
        .preset { border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem; text-align: left; transition: border-color .15s; }
        .preset:hover { border-color: var(--prim); }
        .seletor { display: block; width: 100%; height: 2.5rem; margin-top: .35rem; border: 1px solid var(--borda-campo); border-radius: .5rem; padding: 2px; background: var(--superficie-2); cursor: pointer; }

        /* Mini botões de ação (só ícone) */
        .mini-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.5rem; height: 1.5rem; border-radius: .375rem;
            color: var(--tinta-2); border: 1px solid transparent;
            transition: color .15s, background-color .15s, border-color .15s;
        }
        .mini-btn:hover { color: var(--prim-forte); background: var(--prim-suave); border-color: var(--prim-linha); }
        .mini-btn.perigo:hover {
            color: #f87171;
            background: color-mix(in srgb, #f87171 14%, var(--superficie));
            border-color: color-mix(in srgb, #f87171 40%, var(--superficie));
        }

        /* Menu de configurações (engrenagem) */
        .menu-pop {
            background: var(--superficie); color: var(--tinta);
            border: 1px solid var(--linha); border-radius: .75rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
        }
        .menu-item {
            display: flex; align-items: center; gap: .65rem; width: 100%;
            padding: .5rem .65rem; border-radius: .5rem; font-size: .875rem; text-align: left;
            color: var(--tinta); transition: background-color .15s, color .15s;
        }
        .menu-item:hover, .menu-item:focus-visible { background: var(--prim-suave); color: var(--prim-forte); }
        .menu-sep { height: 1px; margin: .3rem .25rem; background: var(--linha); }


        /* Selects: valem para a página inteira e seguem as cores do tema */
        select {
            appearance: none; -webkit-appearance: none;
            background-color: var(--superficie-2);
            color: var(--tinta);
            border: 1px solid var(--borda-campo);
            border-radius: .5rem;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s;
        }
        /* Select puro (sem classe) ganha o mesmo tamanho dos campos */
        select:not([class]) { padding: .5rem .75rem; font-size: .9rem; }

        /* Setinha desenhada com as cores do tema (também em .campo e .select-status) */
        select, select.campo, select.select-status {
            padding-right: 2rem;
            background-image:
                linear-gradient(45deg, transparent 50%, var(--prim-forte) 50%),
                linear-gradient(135deg, var(--prim-forte) 50%, transparent 50%);
            background-position: calc(100% - 1.15rem) 50%, calc(100% - .85rem) 50%;
            background-size: .3rem .3rem, .3rem .3rem;
            background-repeat: no-repeat;
        }

        select:hover { border-color: var(--prim-linha); }
        select:focus {
            outline: none;
            border-color: var(--prim);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        select:disabled { opacity: .5; cursor: not-allowed; }

        /* Lista que abre ao clicar */
        select option, select optgroup { background: var(--superficie); color: var(--tinta); }
        select option:checked { background: var(--prim); color: var(--on-prim); }

        /* Mini cards circulares */
        .circ { display: flex; flex-direction: column; align-items: center; gap: .45rem; width: 4.75rem; cursor: pointer; }
        .circ-bola {
            width: 3.5rem; height: 3.5rem; border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            background: var(--superficie); border: 1px solid var(--linha); color: var(--tinta-2);
            transition: transform .15s, border-color .2s, background-color .2s, color .2s, box-shadow .2s;
        }
        .circ:hover .circ-bola { border-color: var(--prim-linha); color: var(--prim-forte); transform: translateY(-2px); }
        .circ.ativo .circ-bola {
            background: var(--prim); border-color: var(--prim); color: var(--on-prim);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        .circ-rotulo { font-size: .75rem; text-align: center; line-height: 1.1; color: var(--tinta-2); transition: color .15s; }
        .circ:hover .circ-rotulo, .circ.ativo .circ-rotulo { color: var(--tinta); }
        .erro:empty { display: none; }
        /* Tabelas no celular: cada linha vira um cartão */
        @media (max-width: 767px) {
            .tabela-wrap { border: 0; background: transparent; border-radius: 0; overflow: visible; }
            .tabela, .tabela tbody, .tabela tr, .tabela td { display: block; width: 100%; }
            .tabela thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
            .tabela tbody tr { background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; margin-bottom: .75rem; padding: .25rem 0; }
            .tabela tbody tr:hover { background: var(--superficie); }
            .tabela td { display: flex; justify-content: space-between; align-items: center; gap: 1rem; text-align: right; border: 0; padding: .5rem 1rem; }
            .tabela td::before { content: attr(data-label); flex-shrink: 0; text-align: left; font-size: .8rem; font-weight: 600; color: var(--tinta-2); }
            .tabela td:not([data-label])::before { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
            .flor-giro { animation: none; }
        }
    </style>
</head>
<body>

@auth
    {{-- Camadas do fundo personalizado (ficam atrás de tudo) --}}
    <div id="fundo-img" aria-hidden="true" style="{{ $fundoEstiloImg }}"></div>
    <div id="fundo-veu" aria-hidden="true" style="{{ $fundoEstiloVeu }}"></div>

    <header class="cab-bg sticky top-0 z-30" x-data="{ menu: false }">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 h-16 flex items-center justify-between gap-4">
            <a href="{{ route('painel') }}" aria-label="Work Flower, ir para o painel">
                @include('partials.marca', ['texto' => 'text-xl'])
            </a>

            <nav class="hidden md:flex items-stretch h-16 gap-6 text-sm" aria-label="Principal">
                <a href="{{ route('painel') }}" class="nav-link {{ request()->routeIs('painel') ? 'ativo' : '' }}">Painel</a>
                @foreach (\App\Models\Tarefa::AREAS as $slug => $a)
                    <a href="{{ route('areas.show', $slug) }}" class="nav-link {{ request()->is('areas/'.$slug) ? 'ativo' : '' }}">{{ $a['nome'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                {{-- Configurações --}}
                <div class="relative" x-data="{ aberto: false }" @keydown.escape.window="aberto = false" @click.outside="aberto = false">
                    <button type="button" class="btn-cab !p-2" @click="aberto = !aberto"
                            aria-haspopup="menu" :aria-expanded="aberto" aria-label="Configurações" title="Configurações">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18.97 9.84L21.43 10.19 L21.43 13.81 L18.97 14.16A7.3 7.3 0 0 1 18.46 15.41L19.95 17.38 L17.38 19.95 L15.41 18.46A7.3 7.3 0 0 1 14.16 18.97L13.81 21.43 L10.19 21.43 L9.84 18.97A7.3 7.3 0 0 1 8.59 18.46L6.62 19.95 L4.05 17.38 L5.54 15.41A7.3 7.3 0 0 1 5.03 14.16L2.57 13.81 L2.57 10.19 L5.03 9.84A7.3 7.3 0 0 1 5.54 8.59L4.05 6.62 L6.62 4.05 L8.59 5.54A7.3 7.3 0 0 1 9.84 5.03L10.19 2.57 L13.81 2.57 L14.16 5.03A7.3 7.3 0 0 1 15.41 5.54L17.38 4.05 L19.95 6.62 L18.46 8.59A7.3 7.3 0 0 1 18.97 9.84 Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>

                    <div x-show="aberto" x-cloak x-transition.origin.top.right.duration.150ms
                        class="menu-pop absolute right-0 top-full mt-2 w-48 p-1.5 z-40" role="menu">
                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-tema')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5C9 7.5 6.5 10.2 6.5 13.5a5.5 5.5 0 0011 0C17.5 10.2 15 7.5 12 3.5z"/></svg>
                            Cores
                        </button>

                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-biblioteca')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
                            Biblioteca
                        </button>

                        <div class="menu-sep" role="separator"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="menu-item" role="menuitem">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h5v16h-5M4 12h9m0 0l-3-3m3 3l-3 3"/></svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>

                <button type="button" class="md:hidden p-2 rounded-md" @click="menu = !menu" aria-label="Abrir menu" :aria-expanded="menu">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <nav x-show="menu" x-cloak class="nav-mobile md:hidden px-4 pb-3 space-y-1 text-sm" aria-label="Principal (celular)">
            <a href="{{ route('painel') }}" class="{{ request()->routeIs('painel') ? 'ativo' : '' }}">Painel</a>
            @foreach (\App\Models\Tarefa::AREAS as $slug => $a)
                <a href="{{ route('areas.show', $slug) }}" class="{{ request()->is('areas/'.$slug) ? 'ativo' : '' }}">{{ $a['nome'] }}</a>
            @endforeach
        </nav>
    </header>

    <div class="max-w-6xl mx-auto px-4 sm:px-8 pt-10 pb-6">
        <h1 class="titulo text-3xl sm:text-4xl">@yield('titulo', 'Painel')</h1>
        <p class="texto-2 text-sm mt-1.5">@yield('subtitulo')</p>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-8 pb-16">
        <div data-atualiza="avisos">
            @if (session('ok'))
                <div class="aviso-ok" role="status"
                    x-data="{ saindo: false }"
                    x-init="setTimeout(() => saindo = true, 3000)"
                    :class="{ 'saindo': saindo }">
                    {{ session('ok') }}
                </div>
            @endif
        </div>
        @yield('conteudo')
    </main>

    {{-- Painel de cores e fundo: fica ao lado, sem escurecer a tela, para ver o resultado enquanto ajusta --}}
    <div x-data="temaForm(@js($prim), @js($cab), @js($fundoIni), @js($fundoUrl), @js($fundoSalvar))" x-show="aberto" x-cloak
         @abrir-tema.window="aberto = true; aba = 'cores'" @abrir-biblioteca.window="aberto = true; aba = 'biblioteca'"
         @keydown.escape.window="if (aberto) cancelar()"
         class="fixed inset-0 z-50"
         role="dialog" aria-modal="true" aria-labelledby="tema-titulo">
        <div class="absolute inset-0" @click="cancelar()"></div>

        <div class="card shadow-xl absolute top-20 right-4 left-4 sm:left-auto sm:w-[26rem] max-h-[calc(100vh-6rem)] overflow-auto p-6 space-y-5">

        <div class="flex gap-1.5" role="tablist" aria-label="Configurações">
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'cores' }" :aria-selected="aba === 'cores'" @click="aba = 'cores'">Cores e fundo</button>
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'biblioteca' }" :aria-selected="aba === 'biblioteca'" @click="aba = 'biblioteca'; $dispatch('abrir-biblioteca')">Biblioteca</button>
        </div>

        <form method="POST" action="{{ route('tema.update') }}" @submit.prevent="salvar($event)" x-show="aba === 'cores'" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 id="tema-titulo" class="titulo text-2xl">Cores e fundo</h2>
                <p class="text-sm texto-2 mt-1">Escolha um tema pronto, ajuste as cores e personalize o fundo da página. O resultado aparece ao lado enquanto você ajusta.</p>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <template x-for="p in presets" :key="p.nome">
                    <button type="button" class="preset" @click="aplicar(p.p, p.c)">
                        <span class="flex h-6 rounded overflow-hidden mb-1.5">
                            <span class="flex-1" :style="'background:' + p.c"></span>
                            <span class="flex-1" :style="'background:' + p.p"></span>
                        </span>
                        <span class="text-xs" x-text="p.nome"></span>
                    </button>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-medium">Botões e destaques
                    <input type="color" name="cor_primaria" x-model="prim" @input="previa()" class="seletor">
                </label>
                <label class="text-sm font-medium">Cabeçalhos e tabelas
                    <input type="color" name="cor_cabecalho" x-model="cab" @input="previa()" class="seletor">
                </label>
            </div>

            {{-- ===== Fundo da página ===== --}}
            <div class="pt-5 space-y-5" style="border-top: 1px solid var(--linha)">
                <div>
                    <h3 class="titulo text-lg">Fundo da página</h3>
                    <p class="text-xs texto-2 mt-1">Cor sólida, imagem ou GIF animado. Vale para todas as páginas.</p>
                </div>

                <div>
                    <label class="rotulo">Cor de fundo</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="c in coresFundo" :key="c.nome">
                            <button type="button" class="fundo-cor" :title="c.nome" :aria-label="c.nome" :aria-pressed="f.cor === c.v"
                                    :style="'background:' + (c.v || 'color-mix(in srgb, var(--prim) 2%, #090c10)') + ';' + (f.cor === c.v ? 'border-color:var(--prim);box-shadow:0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent)' : '')"
                                    @click="f.cor = c.v; previaFundo()"></button>
                        </template>
                        <input type="color" class="fundo-cor" title="Outra cor" aria-label="Escolher outra cor"
                               :value="f.cor || '#090c10'" @input="f.cor = $event.target.value; previaFundo()">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="rotulo">Imagem ou GIF</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="btn-sec cursor-pointer">
                            <span x-text="imagemAtual ? 'Trocar arquivo' : 'Escolher arquivo'"></span>
                            <input type="file" accept="image/*" class="sr-only"
                                   @change="escolherImagem($event.target.files[0]); $event.target.value = ''">
                        </label>
                        <button type="button" class="link-prim text-sm" x-show="imagemAtual" @click="tirarImagem()">Remover</button>
                    </div>
                    <p class="text-xs texto-2">JPG e PNG são reduzidos sozinhos. GIF e WebP animados mantêm a animação e podem ter até 4 MB.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Como encaixar</label>
                        <select class="campo" :disabled="!imagemAtual" @change="f.ajuste = $event.target.value; previaFundo()">
                            <option value="cover" :selected="f.ajuste === 'cover'">Preencher a tela</option>
                            <option value="contain" :selected="f.ajuste === 'contain'">Mostrar inteira</option>
                            <option value="repeat" :selected="f.ajuste === 'repeat'">Repetir como padrão</option>
                        </select>
                    </div>
                    <div>
                        <label class="rotulo flex justify-between"><span>Escurecer</span><span class="texto-2 font-normal" x-text="f.escurecer + '%'"></span></label>
                        <input type="range" min="0" max="90" step="5" class="w-full" :disabled="!imagemAtual"
                               :value="f.escurecer" @input="f.escurecer = +$event.target.value; previaFundo()">
                    </div>
                    <div>
                        <label class="rotulo flex justify-between"><span>Desfoque</span><span class="texto-2 font-normal" x-text="f.desfoque + ' px'"></span></label>
                        <input type="range" min="0" max="20" step="1" class="w-full" :disabled="!imagemAtual"
                               :value="f.desfoque" @input="f.desfoque = +$event.target.value; previaFundo()">
                    </div>
                </div>

                <p class="erro" x-text="erro"></p>
            </div>

            <div class="flex items-center justify-between gap-2">
                <button type="button" class="link-prim text-sm" @click="restaurarFundo()">Restaurar fundo</button>
                <div class="flex gap-2">
                    <button type="button" class="btn-sec" @click="cancelar()">Cancelar</button>
                    <button type="submit" class="btn" :disabled="salvando" x-text="salvando ? 'Salvando…' : 'Salvar'"></button>
                </div>
            </div>
        </form>

        {{-- ===== Biblioteca: blocos salvos, para colar na página atual ===== --}}
        <section x-show="aba === 'biblioteca'" x-cloak x-data="bibliotecaPainel()" class="space-y-4">
            <div>
                <h2 class="titulo text-2xl">Biblioteca</h2>
                <p class="text-sm texto-2 mt-1">Use o ícone de marcador no cabeçalho de um bloco para salvá-lo aqui. Depois, em qualquer página, clique em “Colar aqui” para criar uma cópia.</p>
            </div>

            <div class="flex gap-2">
                <input class="campo" x-model="busca" placeholder="Buscar" aria-label="Buscar na biblioteca">
                <select class="campo !w-auto" x-model="tipo" aria-label="Filtrar por tipo">
                    <option value="">Todos</option>
                    <template x-for="(nome, id) in tipos" :key="id"><option :value="id" x-text="nome"></option></template>
                </select>
            </div>

            <p class="text-xs texto-2" x-show="!pode && !carregando">Esta página não tem quadro de blocos, então não dá para colar aqui. Abra uma página com quadro (por exemplo, Carteira).</p>
            <p class="erro" x-text="erro"></p>
            <p class="text-sm texto-2" x-show="carregando">Carregando…</p>
            <p class="text-sm texto-2" x-show="!carregando && !itens.length">Nada salvo ainda.</p>
            <p class="text-sm texto-2" x-show="!carregando && itens.length && !filtrados.length">Nenhum item com esse filtro.</p>

            <ul class="space-y-2">
                <template x-for="i in filtrados" :key="i.id">
                    <li class="card p-3 space-y-2" :style="i.cor ? 'border-top:2px solid ' + i.cor : ''">
                        <div class="flex items-center gap-2">
                            <input class="campo !py-1 flex-1 font-medium" :value="i.nome" @change="renomear(i, $event.target.value)" aria-label="Nome do item">
                            <button type="button" class="mini-btn perigo" title="Excluir da biblioteca" @click="remover(i)">✕</button>
                        </div>
                        <template x-if="i.tipo === 'imagem' && i.imagem">
                            <img :src="i.imagem" alt="" class="w-full h-24 object-cover rounded-lg">
                        </template>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs texto-2 min-w-0 truncate"><span x-text="rotuloTipo(i.tipo)"></span> · <span x-text="resumo(i)"></span></p>
                            <button type="button" class="btn !py-1.5 shrink-0" :disabled="!pode" :class="{ 'opacity-50 cursor-not-allowed': !pode }" @click="colar(i)">Colar aqui</button>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        </div>
    </div>

    <script>
        function temaForm(primInicial, cabInicial, fundoIni, urlImagem, urlFundo) {
            const PAPEL_PADRAO = 'color-mix(in srgb, var(--prim) 2%, #090c10)';
            const LIMITE_ARQUIVO = 4000000; // GIF/WebP animados entram sem alteração, até 4 MB
            const copia = o => JSON.parse(JSON.stringify(o));

            /** Lê o arquivo como está (mantém a animação do GIF). */
            function lerComoTexto(arquivo) {
                return new Promise((ok, falha) => {
                    const r = new FileReader();
                    r.onload = () => ok(r.result);
                    r.onerror = () => falha(r.error);
                    r.readAsDataURL(arquivo);
                });
            }

            /** Reduz JPG/PNG e devolve um JPEG em texto (data URL). */
            function reduzir(arquivo, max, qualidade) {
                return new Promise((ok, falha) => {
                    const img = new Image();
                    const url = URL.createObjectURL(arquivo);
                    img.onload = () => {
                        const k = Math.min(1, max / Math.max(img.width, img.height));
                        const cv = document.createElement('canvas');
                        cv.width = Math.round(img.width * k);
                        cv.height = Math.round(img.height * k);
                        const cx = cv.getContext('2d');
                        cx.fillStyle = '#fff';
                        cx.fillRect(0, 0, cv.width, cv.height);
                        cx.drawImage(img, 0, 0, cv.width, cv.height);
                        URL.revokeObjectURL(url);
                        ok(cv.toDataURL('image/jpeg', qualidade));
                    };
                    img.onerror = () => { URL.revokeObjectURL(url); falha(new Error('imagem')); };
                    img.src = url;
                });
            }

            const nucleo = x => JSON.stringify([x.cor, x.ajuste, +x.escurecer, +x.desfoque]);

            return {
                aberto: false,
                aba: 'cores',
                prim: primInicial,
                cab: cabInicial,
                presets: [
                    { nome: 'Sinal',   p: '#f2c230', c: '#0e1319' },
                    { nome: 'Caverna', p: '#2dd4bf', c: '#0b1417' },
                    { nome: 'Neon',    p: '#a78bfa', c: '#100d1c' },
                    { nome: 'Brasa',   p: '#fb923c', c: '#161009' },
                    { nome: 'Rosé',    p: '#f472b6', c: '#170b12' },
                    { nome: 'Aço',     p: '#94a3b8', c: '#0c1118' },
                ],

                // fundo
                salvando: false,
                erro: '',
                salvo: copia(fundoIni),   // o que está gravado no servidor
                f: copia(fundoIni),       // o que está sendo editado
                novaImagem: null,         // arquivo escolhido e ainda não salvo
                removerImagem: false,
                coresFundo: [
                    { nome: 'Padrão', v: null },
                    { nome: 'Preto', v: '#05070a' },
                    { nome: 'Grafite', v: '#12161c' },
                    { nome: 'Marinho', v: '#0a1424' },
                    { nome: 'Ameixa', v: '#150c1d' },
                    { nome: 'Floresta', v: '#0b1a14' },
                    { nome: 'Terra', v: '#1a120b' },
                ],

                get imagemAtual() {
                    if (this.novaImagem) return this.novaImagem;
                    if (this.removerImagem || !this.f.imagem || !urlImagem) return null;
                    return urlImagem + '?v=' + this.f.versao;
                },

                // ----- cores do tema -----
                aplicar(p, c) { this.prim = p; this.cab = c; this.previa(); },
                previa() {
                    const r = document.documentElement.style;
                    r.setProperty('--prim', this.prim);
                    r.setProperty('--cab', this.cab);
                    r.setProperty('--on-prim', this.contraste(this.prim));
                    r.setProperty('--on-cab', this.contraste(this.cab));
                },
                contraste(hex) {
                    const n = parseInt(hex.slice(1), 16);
                    const lum = (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
                    return lum > 0.6 ? '#14171b' : '#ffffff';
                },

                // ----- fundo -----
                /** Mostra na hora, na própria página, o que está sendo escolhido. */
                previaFundo() {
                    const raiz = document.documentElement.style;
                    const img = document.getElementById('fundo-img');
                    const veu = document.getElementById('fundo-veu');
                    raiz.setProperty('--papel', this.f.cor || PAPEL_PADRAO);

                    const url = this.imagemAtual;
                    if (url) {
                        img.style.display = '';
                        img.style.backgroundImage = 'url("' + url + '")';
                        img.style.backgroundSize = this.f.ajuste === 'repeat' ? 'auto' : this.f.ajuste;
                        img.style.backgroundRepeat = this.f.ajuste === 'repeat' ? 'repeat' : 'no-repeat';
                        img.style.filter = this.f.desfoque ? 'blur(' + this.f.desfoque + 'px)' : 'none';
                        img.style.inset = this.f.desfoque ? '-40px' : '0';
                        veu.style.display = '';
                        veu.style.background = 'color-mix(in srgb, var(--papel) ' + this.f.escurecer + '%, transparent)';
                    } else {
                        img.style.display = 'none';
                        veu.style.display = 'none';
                    }
                },

                async escolherImagem(arquivo) {
                    if (!arquivo || !arquivo.type.startsWith('image/')) return;
                    this.erro = '';
                    try {
                        let url;
                        if (arquivo.type === 'image/gif' || arquivo.type === 'image/webp') {
                            if (arquivo.size > LIMITE_ARQUIVO) {
                                this.erro = 'Esse arquivo passa de 4 MB. Escolha um GIF ou WebP menor.';
                                return;
                            }
                            url = await lerComoTexto(arquivo);
                        } else {
                            url = await reduzir(arquivo, 1920, 0.8);
                            if (url.length > 2400000) url = await reduzir(arquivo, 1280, 0.7);
                        }
                        if (url.length > 5900000) { this.erro = 'O arquivo é muito pesado. Escolha outro.'; return; }
                        this.novaImagem = url;
                        this.removerImagem = false;
                        this.previaFundo();
                    } catch {
                        this.erro = 'Não consegui ler esse arquivo.';
                    }
                },

                tirarImagem() {
                    this.novaImagem = null;
                    this.removerImagem = !!this.f.imagem;
                    this.previaFundo();
                },

                restaurarFundo() {
                    this.f.cor = null; this.f.ajuste = 'cover'; this.f.escurecer = 55; this.f.desfoque = 0;
                    this.tirarImagem();
                },

                fundoMudou() {
                    return !!this.novaImagem || this.removerImagem || nucleo(this.f) !== nucleo(this.salvo);
                },

                /** Grava o fundo no servidor. Devolve true se deu certo. */
                async salvarFundo() {
                    this.erro = '';
                    if (!urlFundo) { this.erro = 'A rota do fundo não foi criada ainda.'; return false; }
                    try {
                        const corpo = { cor: this.f.cor, ajuste: this.f.ajuste, escurecer: +this.f.escurecer, desfoque: +this.f.desfoque };
                        if (this.novaImagem) corpo.imagem = this.novaImagem;
                        else if (this.removerImagem) corpo.remover_imagem = true;

                        const r = await fetch(urlFundo, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify(corpo),
                        });

                        if (r.status === 401 || r.status === 419) { location.reload(); return false; }
                        if (r.status === 413) { this.erro = 'O arquivo é grande demais para o servidor. Escolha um menor.'; return false; }
                        if (r.status === 422) {
                            const j = await r.json();
                            this.erro = Object.values(j.errors || {})[0]?.[0] || 'Confira os dados e tente de novo.';
                            return false;
                        }
                        if (!r.ok) throw new Error(String(r.status));

                        const j = await r.json();
                        this.f.imagem = j.imagem;
                        this.f.versao = j.versao;
                        this.novaImagem = null;
                        this.removerImagem = false;
                        this.salvo = copia(this.f);
                        return true;
                    } catch {
                        this.erro = 'Não foi possível salvar o fundo. Tente de novo.';
                        return false;
                    }
                },

                /** Salva o fundo (se mudou) e depois envia o formulário das cores normalmente. */
                async salvar(e) {
                    const form = e.target;
                    this.salvando = true;
                    try {
                        if (this.fundoMudou() && !(await this.salvarFundo())) return;
                        form.submit();
                    } finally {
                        this.salvando = false;
                    }
                },

                cancelar() {
                    this.prim = primInicial;
                    this.cab = cabInicial;
                    this.previa();
                    this.f = copia(this.salvo);
                    this.novaImagem = null;
                    this.removerImagem = false;
                    this.erro = '';
                    this.previaFundo();
                    this.aberto = false;
                },
            };
        }

    document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches('form[data-ajax]') || e.defaultPrevented) return;
    e.preventDefault();
    if (form.dataset.enviando) return;

    form.dataset.enviando = '1';
    if (e.submitter) e.submitter.disabled = true;
    form.querySelectorAll('[data-erro]').forEach(el => el.textContent = '');

    try {
        const resp = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (resp.status === 401 || resp.status === 419) return location.reload();

        if (resp.status === 422) {
            const { errors } = await resp.json();
            for (const [campo, msgs] of Object.entries(errors)) {
                const el = form.querySelector(`[data-erro="${campo}"]`);
                if (el) el.textContent = msgs[0];
            }
            return;
        }

        if (!resp.ok) throw new Error(resp.status);

        const doc = new DOMParser().parseFromString(await resp.text(), 'text/html');
        document.querySelectorAll('[data-atualiza]').forEach(el => {
            const novo = doc.querySelector(`[data-atualiza="${el.dataset.atualiza}"]`);
            if (novo) el.replaceWith(novo);
        });

        if (form.hasAttribute('data-limpar') && form.isConnected) {
            form.reset();
            form.querySelector('[name=titulo]')?.focus();
        }
    } catch (err) {
        alert('Não foi possível salvar. Tente novamente.');
    } finally {
        delete form.dataset.enviando;
        if (e.submitter) e.submitter.disabled = false;
    }
});
    </script>
@endauth

@guest
    @yield('conteudo')
@endguest

</body>
</html>