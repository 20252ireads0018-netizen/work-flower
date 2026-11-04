{{-- Esqueleto das páginas públicas de uma mini loja (vitrine e pedido). Cores e letra vêm de $v (Loja::visual()).
     A tag do cabeçalho e o meta do conjunto de caracteres saem por Blade de propósito: o editor de arquivos do cPanel
     reescreve esses dois (e qualquer tag que comece com "head", como a do topo) quando aparecem escritos por extenso. --}}
@php
    $fontes = [
        'sans'        => "'Segoe UI', system-ui, -apple-system, Roboto, Arial, sans-serif",
        'serif'       => "Georgia, 'Times New Roman', serif",
        'mono'        => "ui-monospace, 'Cascadia Code', Consolas, monospace",
        'arredondada' => "'Trebuchet MS', 'Varela Round', system-ui, sans-serif",
    ];
    $raio = ['retos' => '2px', 'suaves' => '12px', 'redondos' => '22px'][$v['cantos']];
    $img = fn ($a) => $a ? route('loja.img', [$loja->slug, $a]) : null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<{{ 'head' }}>
    <meta {{ 'char' . 'set' }}="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', $loja->nome)</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($loja->chamada ?: $loja->sobre ?: $loja->nome, 150) }}">
    <meta property="og:title" content="{{ $loja->nome }}">
    @if ($v['banner'] || $v['logo']) <meta property="og:image" content="{{ $img($v['banner'] ?: $v['logo']) }}"> @endif
    @if ($v['logo']) <link rel="icon" href="{{ $img($v['logo']) }}"> @endif
    <style>
        :root { --cor: {{ $v['cor'] }}; --fundo: {{ $v['fundo'] }}; --texto: {{ $v['texto'] }}; --cartao: {{ $v['cartao'] }}; --raio: {{ $raio }}; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font-family: {!! $fontes[$v['fonte']] !!}; line-height: 1.45; }
        a { color: inherit; }
        img { max-width: 100%; display: block; }
        .lj-max { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        .lj-topo { display: flex; align-items: center; gap: 12px; padding: 14px 0; }
        .lj-logo { width: 52px; height: 52px; border-radius: var(--raio); object-fit: cover; background: var(--cartao); }
        .lj-nome { font-size: 1.35rem; font-weight: 800; margin: 0; }
        .lj-chamada { margin: 0; opacity: .75; font-size: .95rem; }
        .lj-banner { width: 100%; max-height: 320px; object-fit: cover; border-radius: var(--raio); margin-bottom: 18px; }
        .lj-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: var(--cor); color: #fff; border: 0; border-radius: var(--raio);
            padding: 10px 16px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
        .lj-btn:hover { filter: brightness(1.08); }
        .lj-btn:disabled { opacity: .45; cursor: not-allowed; }
        .lj-btn.sec { background: transparent; color: var(--texto); border: 1.5px solid color-mix(in srgb, var(--texto) 25%, transparent); }
        .lj-cartao { background: var(--cartao); border-radius: var(--raio); box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 4px 14px rgba(0,0,0,.05); overflow: hidden; }
        .lj-campo { width: 100%; padding: 10px 12px; border-radius: var(--raio); border: 1.5px solid color-mix(in srgb, var(--texto) 20%, transparent); background: var(--cartao); color: var(--texto); font: inherit; }
        .lj-rotulo { display: block; font-size: .8rem; opacity: .75; margin: 10px 0 4px; }
        .lj-aviso { padding: 10px 14px; border-radius: var(--raio); background: color-mix(in srgb, var(--cor) 14%, var(--cartao)); margin-bottom: 14px; }
        .lj-rodape { padding: 28px 0 40px; font-size: .85rem; opacity: .75; }
        .lj-preco { font-weight: 800; font-size: 1.15rem; }
        .lj-antigo { text-decoration: line-through; opacity: .55; font-size: .85rem; margin-left: 6px; }
    </style>
    @stack('estilo')
</head>
<body>
    @if (! $loja->publicada)
        <div style="background:#fbbf24;color:#000;text-align:center;padding:6px;font-size:.85rem">Prévia: a loja está fechada e só você vê esta página.</div>
    @endif
    <div class="lj-max">
        <{{ 'header' }} class="lj-topo">
            @if ($v['logo']) <img src="{{ $img($v['logo']) }}" alt="" class="lj-logo"> @endif
            <div style="min-width:0;flex:1">
                <a href="{{ route('loja.ver', $loja->slug) }}" style="text-decoration:none"><h1 class="lj-nome">{{ $loja->nome }}</h1></a>
                @if ($loja->chamada) <p class="lj-chamada">{{ $loja->chamada }}</p> @endif
            </div>
            @yield('topo')
        </header>
        @yield('conteudo')
        <footer class="lj-rodape">
            @if ($loja->sobre) <p style="white-space:pre-line;margin:0 0 12px">{{ $loja->sobre }}</p> @endif
            <p style="margin:0;display:flex;flex-wrap:wrap;gap:14px">
                @if ($v['whatsapp']) <a href="https://wa.me/{{ strlen($v['whatsapp']) <= 11 ? '55' . $v['whatsapp'] : $v['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp</a> @endif
                @if ($v['instagram']) <a href="https://instagram.com/{{ $v['instagram'] }}" target="_blank" rel="noopener">Instagram {{ '@' . $v['instagram'] }}</a> @endif
                @if ($v['entrega']) <span>{{ $v['entrega'] }}</span> @endif
            </p>
            @if ($loja->comMarca())
                <p style="margin:14px 0 0;font-size:.75rem">Loja feita com <a href="{{ url('/') }}">Work Flower</a></p>
            @endif
        </footer>
    </div>
    @stack('scripts')
</body>
</html>
