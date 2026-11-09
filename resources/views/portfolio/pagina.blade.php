{{-- Página pública do portfólio (/p/{slug}) e prévia ao vivo do editor ($previa = true, dentro de um iframe sem scripts).
     $pf = App\Models\Portfolio, $c = conteúdo normalizado (Portfolio::normalizar). Tudo o que é texto sai escapado.
     A tag do cabeçalho e o meta do conjunto de caracteres saem por Blade de propósito (o editor de arquivos do cPanel
     reescreve essas tags quando aparecem escritas por extenso, ver loja/base.blade.php). --}}
@php
    use App\Models\Portfolio;
    use Illuminate\Support\Str;
    $p = $c['perfil']; $t = $c['tema']; $seo = $c['seo'];
    $img = function ($v) use ($pf, $previa) {
        if (! $v) return null;
        if (\App\Support\Midia::tokenValido($v)) return $previa ? route('midia.ver', $v) : route('portfolio.midia', [$pf->slug, $v]);
        return $v;
    };
    $secoes = array_values(array_filter($c['secoes'], function ($s) use ($p) {
        if (! $s['visivel']) return false;
        if ($s['tipo'] === 'contato') return $s['formulario'] || $s['texto'] !== '' || $p['email'] !== '' || $p['telefone'] !== '' || $p['links'];
        return $s['texto'] !== '' || $s['itens'] || $s['img'];
    }));
    $temContato = collect($secoes)->contains(fn ($s) => $s['tipo'] === 'contato');
    $fontes = array_unique([$t['fonte_titulo'], $t['fonte_texto']]);
    $google = array_filter(array_map(fn ($f) => Portfolio::FONTES[$f][2], $fontes));
    $raio = ['retos' => '2px', 'suaves' => '14px', 'redondos' => '26px'][$t['cantos']];
    $largura = ['estreita' => '760px', 'normal' => '1080px', 'larga' => '1320px'][$t['largura']];
    $espaco = ['compacto' => '40px', 'normal' => '72px', 'amplo' => '112px'][$t['espacamento']];
    $base = ['p' => '15px', 'm' => '17px', 'g' => '19px'][$t['tamanho_texto']];
    $sombra = ['nenhuma' => 'none', 'leve' => '0 1px 2px rgba(0,0,0,.06), 0 6px 20px rgba(0,0,0,.06)', 'forte' => '0 10px 40px rgba(0,0,0,.18)', 'contorno' => 'none'][$t['sombra']];
    // fundo claro ou escuro? (para o botão de trocar o modo e para o texto sobre a cor principal)
    $lum = function (string $hex) { [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x'); return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255; };
    $escuro = $lum($t['fundo']) < 0.5;
    $sobreCor = $lum($t['cor']) > 0.62 ? '#111111' : '#ffffff';
    $nome = $p['nome'] ?: 'Portfólio';
    $titulo = $seo['titulo'] ?: ($p['titulo'] ? $nome . ' · ' . $p['titulo'] : $nome);
    $descricao = $seo['descricao'] ?: Str::limit(preg_replace('/\s+/', ' ', $p['resumo'] ?: $p['titulo'] ?: ''), 160);
    $imagemSeo = $img($seo['imagem'] ?: $p['capa'] ?: $p['foto']);
    $url = $pf->slug && $pf->exists ? route('portfolio.ver', $pf->slug) : null;
    $indexar = ! $previa && $pf->publicado && $seo['indexar'];
    $cta = $p['botao_texto'] !== '' ? ($p['botao_link'] ?: ($temContato ? '#contato' : ($p['email'] ? 'mailto:' . $p['email'] : ''))) : '';
    $externo = fn ($u) => $u && ! str_starts_with($u, '#') && ! str_starts_with($u, 'mailto:') && ! str_starts_with($u, 'tel:');
    $fundoImg = $t['fundo_tipo'] === 'imagem' ? $img($t['fundo_img']) : null;
    $menu = $t['menu'] && count($secoes) > 1;
    $lateral = $t['layout'] === 'lateral';
    // dados estruturados (Person). As chaves com arroba ficam aqui no PHP: escritas no template, o Blade as leria como diretivas.
    $jsonLd = json_encode(array_filter([
        "\x40context" => 'https://schema.org', "\x40type" => 'Person', 'name' => $nome, 'jobTitle' => $p['titulo'] ?: null,
        'description' => $descricao ?: null, 'url' => $url, 'image' => $p['foto'] ? $img($p['foto']) : null,
        'sameAs' => array_values(array_filter(array_map(fn ($l) => str_starts_with($l['url'], 'http') ? $l['url'] : null, $p['links']))) ?: null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="pf-l-{{ $t['layout'] }} pf-c-{{ $t['cabecalho'] }} pf-a-{{ $t['animacao'] }} pf-t-{{ $t['titulos'] }} pf-s-{{ $t['sombra'] }} {{ $t['vidro'] ? 'pf-vidro' : '' }} {{ $escuro ? 'pf-escuro' : 'pf-claro' }}">
<{{ 'head' }}>
    <meta {{ 'char' . 'set' }}="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <meta name="description" content="{{ $descricao }}">
    <meta name="robots" content="{{ $indexar ? 'index, follow' : 'noindex, nofollow' }}">
    @if ($url) <link rel="canonical" href="{{ $url }}"> @endif
    <meta property="og:type" content="profile">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descricao }}">
    @if ($url) <meta property="og:url" content="{{ $url }}"> @endif
    @if ($imagemSeo) <meta property="og:image" content="{{ $imagemSeo }}"> @endif
    <meta name="twitter:card" content="{{ $imagemSeo ? 'summary_large_image' : 'summary' }}">
    <meta name="theme-color" content="{{ $t['cor'] }}">
    @if ($p['foto']) <link rel="icon" href="{{ $img($p['foto']) }}"> @endif
    @if ($google)
        <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?{{ implode('&', array_map(fn ($g) => 'family=' . $g, $google)) }}&display=swap">
    @endif
    @if (! $previa)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endif
    <style>
        :root {
            --cor: {{ $t['cor'] }}; --cor2: {{ $t['cor2'] }}; --fundo: {{ $t['fundo'] }}; --texto: {{ $t['texto'] }}; --cartao: {{ $t['cartao'] }};
            --sobre-cor: {{ $sobreCor }}; --raio: {{ $raio }}; --largura: {{ $largura }}; --espaco: {{ $espaco }}; --sombra: {{ $sombra }};
            --f-titulo: {!! Portfolio::FONTES[$t['fonte_titulo']][1] !!}; --f-texto: {!! Portfolio::FONTES[$t['fonte_texto']][1] !!};
            --suave: color-mix(in srgb, var(--texto) 62%, transparent); --linha: color-mix(in srgb, var(--texto) 14%, transparent);
            --escurecer: {{ $t['fundo_escurecer'] / 100 }}; --desfoque: {{ $t['fundo_desfoque'] }}px;
        }
        html.pf-inv { --fundo: {{ $escuro ? '#f7f7f5' : '#0d1117' }}; --texto: {{ $escuro ? '#16181d' : '#e6e8ec' }}; --cartao: {{ $escuro ? '#ffffff' : '#161b24' }}; }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; font-size: {{ $base }}; }
        body { margin: 0; background: var(--fundo); color: var(--texto); font-family: var(--f-texto); line-height: 1.6; -webkit-font-smoothing: antialiased; min-height: 100vh; }
        @if ($t['fundo_tipo'] === 'gradiente')
        body { background: radial-gradient(1200px 600px at 10% -10%, color-mix(in srgb, var(--cor) 22%, transparent), transparent 70%),
            radial-gradient(900px 500px at 100% 10%, color-mix(in srgb, var(--cor2) 18%, transparent), transparent 70%), var(--fundo); background-attachment: fixed; }
        @elseif ($t['fundo_tipo'] === 'padrao')
        body { background-color: var(--fundo); background-attachment: fixed;
            @switch($t['padrao'])
                @case('grade') background-image: linear-gradient(var(--linha) 1px, transparent 1px), linear-gradient(90deg, var(--linha) 1px, transparent 1px); background-size: 32px 32px; @break
                @case('linhas') background-image: repeating-linear-gradient(135deg, var(--linha) 0 1px, transparent 1px 14px); @break
                @case('xadrez') background-image: conic-gradient(var(--linha) 25%, transparent 0 50%, var(--linha) 0 75%, transparent 0); background-size: 40px 40px; @break
                @default background-image: radial-gradient(var(--linha) 1.4px, transparent 1.4px); background-size: 22px 22px;
            @endswitch
        }
        @endif
        .pf-bg { position: fixed; inset: 0; z-index: -1; background-size: cover; background-position: center; filter: blur(var(--desfoque)); transform: scale(1.04); }
        .pf-bg::after { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,var(--escurecer)); }
        @if (! $t['fundo_fixo']) .pf-bg { position: absolute; min-height: 100%; } @endif
        a { color: var(--cor); }
        img { max-width: 100%; display: block; }
        h1, h2, h3, .pf-tf { font-family: var(--f-titulo); line-height: 1.15; margin: 0; }
        p { margin: 0; }
        .pf-pre { white-space: pre-line; }
        .pf-max { max-width: var(--largura); margin: 0 auto; padding: 0 20px; }
        .pf-cartao { background: var(--cartao); border-radius: var(--raio); box-shadow: var(--sombra); }
        .pf-s-contorno .pf-cartao { border: 1.5px solid var(--linha); }
        .pf-vidro .pf-cartao { background: color-mix(in srgb, var(--cartao) 62%, transparent); backdrop-filter: blur(14px) saturate(1.3); -webkit-backdrop-filter: blur(14px) saturate(1.3); border: 1px solid color-mix(in srgb, #fff 18%, transparent); }
        .pf-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: var(--raio); background: var(--cor); color: var(--sobre-cor);
            font: inherit; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; transition: transform .15s, filter .15s; }
        .pf-btn:hover { transform: translateY(-2px); filter: brightness(1.07); }
        .pf-btn.sec { background: transparent; color: var(--texto); border: 1.5px solid var(--linha); }
        .pf-chip { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: .8rem; background: color-mix(in srgb, var(--cor) 14%, transparent); color: var(--texto); border: 1px solid color-mix(in srgb, var(--cor) 25%, transparent); }
        .pf-suave { color: var(--suave); }
        .pf-disp { display: inline-flex; align-items: center; gap: 8px; font-size: .85rem; padding: 5px 12px; border-radius: 999px; background: color-mix(in srgb, #22c55e 15%, transparent); }
        .pf-disp::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 0 4px color-mix(in srgb, #22c55e 30%, transparent); }

        /* menu */
        .pf-menu { position: sticky; top: 0; z-index: 20; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background: color-mix(in srgb, var(--fundo) 78%, transparent); border-bottom: 1px solid var(--linha); }
        .pf-menu .pf-max { display: flex; align-items: center; gap: 18px; height: 58px; }
        .pf-menu b { font-family: var(--f-titulo); white-space: nowrap; }
        .pf-menu nav { display: flex; gap: 18px; overflow-x: auto; scrollbar-width: none; margin-left: auto; }
        .pf-menu nav a { color: var(--texto); text-decoration: none; font-size: .9rem; opacity: .75; white-space: nowrap; }
        .pf-menu nav a:hover { opacity: 1; color: var(--cor); }
        .pf-modo { background: none; border: 1px solid var(--linha); color: var(--texto); border-radius: 999px; width: 34px; height: 34px; cursor: pointer; flex-shrink: 0; }

        /* cabeçalho */
        .pf-hero { padding: calc(var(--espaco) * .9) 0 calc(var(--espaco) * .6); }
        .pf-hero .pf-max { display: flex; gap: 32px; align-items: center; }
        .pf-foto { width: 148px; height: 148px; object-fit: cover; flex-shrink: 0; border-radius: 50%; box-shadow: var(--sombra); background: var(--cartao); }
        .pf-f-quadrado .pf-foto { border-radius: 4px; } .pf-f-arredondada .pf-foto { border-radius: 28px; }
        .pf-nome { font-size: clamp(2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.02em; }
        .pf-cargo { font-size: 1.2rem; color: var(--suave); margin-top: 6px; }
        .pf-resumo { margin-top: 16px; max-width: 62ch; }
        .pf-acoes { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; align-items: center; }
        .pf-redes { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
        .pf-redes a { font-size: .85rem; text-decoration: none; color: var(--texto); padding: 6px 12px; border-radius: 999px; border: 1px solid var(--linha); }
        .pf-redes a:hover { border-color: var(--cor); color: var(--cor); }
        .pf-c-minimo .pf-foto { display: none; }
        .pf-c-dividido .pf-hero .pf-max { flex-direction: row-reverse; justify-content: space-between; }
        .pf-c-dividido .pf-foto { width: min(38vw, 380px); height: min(46vw, 460px); border-radius: var(--raio); }
        .pf-c-gigante .pf-hero { min-height: 88vh; display: flex; align-items: center; }
        .pf-c-gigante .pf-nome { font-size: clamp(3rem, 11vw, 8rem); line-height: .95; }
        .pf-c-gigante .pf-hero .pf-max { flex-direction: column; align-items: flex-start; }
        .pf-capa { height: 300px; background: linear-gradient(120deg, var(--cor), var(--cor2)); background-size: cover; background-position: center; }
        .pf-c-capa .pf-hero { padding-top: 0; }
        .pf-c-capa .pf-hero .pf-max { flex-direction: column; align-items: flex-start; gap: 18px; }
        .pf-c-capa .pf-foto { margin-top: -84px; border: 5px solid var(--fundo); }

        /* seções */
        .pf-secao { padding: calc(var(--espaco) / 2) 0; scroll-margin-top: 70px; }
        .pf-secao > .pf-max > .pf-in { padding: 0; }
        .pf-fundo-cartao > .pf-max > .pf-in { background: var(--cartao); border-radius: var(--raio); box-shadow: var(--sombra); padding: 36px; }
        .pf-fundo-destaque { background: color-mix(in srgb, var(--cor) 9%, transparent); padding: var(--espaco) 0; margin: calc(var(--espaco) / 2) 0; }
        .pf-fundo-invertido { background: var(--texto); color: var(--fundo); padding: var(--espaco) 0; margin: calc(var(--espaco) / 2) 0; --texto: {{ $t['fundo'] }}; --cartao: color-mix(in srgb, {{ $t['fundo'] }} 8%, {{ $t['texto'] }}); --linha: color-mix(in srgb, {{ $t['fundo'] }} 18%, transparent); --suave: color-mix(in srgb, {{ $t['fundo'] }} 65%, transparent); }
        .pf-st { font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 800; margin-bottom: 6px; }
        .pf-sub { color: var(--suave); margin-bottom: 24px; }
        .pf-cab { margin-bottom: 26px; }
        .pf-cab .pf-sub { margin-bottom: 0; }
        .pf-t-maiusculas .pf-st { text-transform: uppercase; letter-spacing: .08em; font-size: 1.15rem; }
        .pf-t-sublinhado .pf-st { display: inline-block; padding-bottom: 6px; background: linear-gradient(90deg, var(--cor), var(--cor2)) left bottom / 56px 4px no-repeat; }
        html.pf-t-numerados { counter-reset: secao; }
        .pf-t-numerados .pf-st::before { counter-increment: secao; content: "0" counter(secao) ". "; color: var(--cor); font-family: ui-monospace, monospace; font-size: .8em; }
        .pf-grade { display: grid; gap: 20px; grid-template-columns: repeat(var(--cols, 3), minmax(0, 1fr)); }
        .pf-item { padding: 22px; }
        .pf-item h3 { font-size: 1.15rem; }
        .pf-proj img { width: 100%; aspect-ratio: 16/10; object-fit: cover; border-radius: var(--raio) var(--raio) 0 0; }
        .pf-proj { overflow: hidden; display: flex; flex-direction: column; }
        .pf-proj .pf-item { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .pf-tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .pf-ver { margin-top: auto; font-weight: 700; text-decoration: none; }
        .pf-lista > * + * { margin-top: 16px; }
        .pf-linha-proj { display: grid; grid-template-columns: 220px 1fr; overflow: hidden; }
        .pf-linha-proj img { height: 100%; aspect-ratio: auto; min-height: 150px; border-radius: 0; }
        .pf-mosaico { columns: var(--cols, 3) 260px; column-gap: 20px; }
        .pf-mosaico > * { break-inside: avoid; margin-bottom: 20px; }
        .pf-mosaico img { aspect-ratio: auto; }
        .pf-destaque > * + * { margin-top: 48px; }
        .pf-destaque article { display: grid; grid-template-columns: 1.2fr 1fr; gap: 36px; align-items: center; }
        .pf-destaque article:nth-child(even) { direction: rtl; } .pf-destaque article:nth-child(even) > * { direction: ltr; }
        .pf-destaque img { width: 100%; border-radius: var(--raio); box-shadow: var(--sombra); aspect-ratio: 4/3; object-fit: cover; }
        .pf-tempo { position: relative; padding-left: 28px; border-left: 2px solid var(--linha); }
        .pf-tempo > div { position: relative; padding-bottom: 28px; }
        .pf-tempo > div::before { content: ""; position: absolute; left: -36px; top: 6px; width: 14px; height: 14px; border-radius: 50%; background: var(--cor); box-shadow: 0 0 0 5px color-mix(in srgb, var(--cor) 22%, transparent); }
        .pf-per { font-size: .85rem; color: var(--cor); font-weight: 700; }
        .pf-barra { height: 9px; border-radius: 99px; background: var(--linha); overflow: hidden; margin-top: 6px; }
        .pf-barra i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--cor), var(--cor2)); }
        .pf-hab-barras { display: grid; gap: 16px 40px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .pf-chips { display: flex; flex-wrap: wrap; gap: 10px; }
        .pf-chips .pf-chip { font-size: .95rem; padding: 7px 16px; }
        .pf-circ { display: grid; gap: 22px; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); text-align: center; }
        .pf-anel { width: 96px; height: 96px; margin: 0 auto 8px; border-radius: 50%; display: grid; place-items: center; font-weight: 800;
            background: radial-gradient(closest-side, var(--fundo) 78%, transparent 80% 100%), conic-gradient(var(--cor) calc(var(--n) * 1%), var(--linha) 0); }
        .pf-num { text-align: center; }
        .pf-num b { display: block; font-family: var(--f-titulo); font-size: clamp(2.2rem, 5vw, 3.4rem); background: linear-gradient(90deg, var(--cor), var(--cor2)); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .pf-dep { display: flex; flex-direction: column; gap: 16px; }
        .pf-dep blockquote { margin: 0; font-size: 1.02rem; }
        .pf-dep blockquote::before { content: "“"; display: block; font-size: 3rem; line-height: .6; color: var(--cor); font-family: Georgia, serif; }
        .pf-autor { display: flex; align-items: center; gap: 12px; margin-top: auto; }
        .pf-autor img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
        .pf-citacao { text-align: center; max-width: 760px; margin: 0 auto; }
        .pf-citacao > * + * { margin-top: 44px; }
        .pf-citacao blockquote { font-size: 1.35rem; font-family: var(--f-titulo); }
        .pf-citacao .pf-autor { justify-content: center; }
        .pf-gal img { width: 100%; border-radius: var(--raio); object-fit: cover; aspect-ratio: 1; }
        .pf-gal.pf-mosaico img { aspect-ratio: auto; }
        .pf-gal figure { margin: 0; } .pf-gal figcaption { font-size: .85rem; color: var(--suave); margin-top: 6px; }
        .pf-carrossel { display: flex; gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 10px; }
        .pf-carrossel figure { flex: 0 0 min(78%, 420px); scroll-snap-align: start; }
        .pf-carrossel img { aspect-ratio: 4/3; }
        .pf-links { display: grid; gap: 12px; max-width: 560px; margin: 0 auto; }
        .pf-links a { display: block; text-align: center; padding: 15px; border-radius: var(--raio); background: var(--cartao); box-shadow: var(--sombra); color: var(--texto); text-decoration: none; font-weight: 700; border: 1px solid var(--linha); transition: transform .15s; }
        .pf-links a:hover { transform: translateY(-2px); border-color: var(--cor); }
        .pf-links.lista { max-width: none; } .pf-links.lista a { text-align: left; background: none; box-shadow: none; }
        .pf-div { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: center; }
        .pf-div > img { width: 100%; border-radius: var(--raio); box-shadow: var(--sombra); }
        .pf-texto-destaque { font-size: 1.35rem; font-family: var(--f-titulo); }
        .pf-form { display: grid; gap: 12px; }
        .pf-form label { font-size: .85rem; color: var(--suave); display: grid; gap: 4px; }
        .pf-campo { width: 100%; padding: 12px 14px; border-radius: var(--raio); border: 1.5px solid var(--linha); background: var(--cartao); color: var(--texto); font: inherit; }
        .pf-campo:focus { outline: 2px solid var(--cor); outline-offset: 1px; }
        .pf-aviso { padding: 12px 16px; border-radius: var(--raio); margin-bottom: 14px; background: color-mix(in srgb, #22c55e 18%, var(--cartao)); }
        .pf-aviso.erro { background: color-mix(in srgb, #ef4444 18%, var(--cartao)); }
        .pf-oculto { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .pf-rodape { padding: 40px 0 56px; font-size: .85rem; color: var(--suave); text-align: center; }
        .pf-rodape a { color: inherit; }

        /* layouts */
        .pf-l-centralizado .pf-hero .pf-max, .pf-l-centralizado.pf-c-gigante .pf-hero .pf-max, .pf-l-centralizado.pf-c-capa .pf-hero .pf-max { flex-direction: column; align-items: center; text-align: center; }
        .pf-l-centralizado .pf-acoes, .pf-l-centralizado .pf-redes { justify-content: center; }
        .pf-l-centralizado .pf-resumo { margin-left: auto; margin-right: auto; }
        .pf-l-centralizado .pf-cab { text-align: center; }
        .pf-l-centralizado { --largura: min({{ $largura }}, 900px); }
        .pf-l-revista .pf-secao > .pf-max > .pf-in { display: grid; grid-template-columns: 240px 1fr; gap: 40px; }
        .pf-l-revista .pf-secao .pf-cab { position: sticky; top: 80px; align-self: start; }
        .pf-l-revista .pf-grade { --cols: 2 !important; }
        .pf-l-cartao .pf-pagina { max-width: calc(var(--largura) + 40px); margin: 40px auto; background: var(--cartao); border-radius: calc(var(--raio) + 8px); box-shadow: 0 30px 80px rgba(0,0,0,.25); overflow: hidden; }
        .pf-l-cartao .pf-cartao { background: color-mix(in srgb, var(--texto) 4%, var(--cartao)); }
        .pf-lado { display: none; }
        @media (min-width: 960px) {
            .pf-l-lateral .pf-pagina { display: grid; grid-template-columns: 340px 1fr; }
            .pf-l-lateral .pf-lado { display: flex; flex-direction: column; gap: 14px; position: sticky; top: 0; height: 100vh; overflow-y: auto; padding: 48px 32px; border-right: 1px solid var(--linha); background: color-mix(in srgb, var(--cartao) 70%, transparent); }
            .pf-l-lateral .pf-lado .pf-foto { width: 120px; height: 120px; }
            .pf-l-lateral .pf-lado nav { display: grid; gap: 4px; margin-top: 10px; }
            .pf-l-lateral .pf-lado nav a { color: var(--texto); text-decoration: none; padding: 6px 10px; border-radius: 8px; opacity: .8; }
            .pf-l-lateral .pf-lado nav a:hover { background: var(--linha); opacity: 1; }
            .pf-l-lateral .pf-hero, .pf-l-lateral .pf-menu { display: none; }
            .pf-l-lateral .pf-conteudo { padding-top: 32px; }
        }

        /* animação ao rolar (só com JavaScript: sem ele, tudo aparece) */
        .pf-js.pf-a-subir .pf-rv { opacity: 0; transform: translateY(26px); }
        .pf-js.pf-a-aparecer .pf-rv { opacity: 0; }
        .pf-js.pf-a-zoom .pf-rv { opacity: 0; transform: scale(.94); }
        .pf-js .pf-rv { transition: opacity .7s ease, transform .7s ease; }
        .pf-js .pf-rv.pf-vis { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) { .pf-js .pf-rv { opacity: 1 !important; transform: none !important; transition: none; } html { scroll-behavior: auto; } }

        @media (max-width: 760px) {
            .pf-grade { grid-template-columns: 1fr !important; }
            .pf-hero .pf-max, .pf-c-dividido .pf-hero .pf-max { flex-direction: column; align-items: flex-start; }
            .pf-l-centralizado .pf-hero .pf-max { align-items: center; }
            .pf-c-dividido .pf-foto { width: 100%; height: 320px; }
            .pf-foto { width: 112px; height: 112px; }
            .pf-linha-proj, .pf-destaque article, .pf-div, .pf-l-revista .pf-secao > .pf-max > .pf-in { grid-template-columns: 1fr; }
            .pf-destaque article:nth-child(even) { direction: ltr; }
            .pf-l-revista .pf-secao .pf-cab { position: static; }
            .pf-fundo-cartao > .pf-max > .pf-in { padding: 22px; }
            .pf-capa { height: 190px; }
            .pf-l-cartao .pf-pagina { margin: 0; border-radius: 0; }
            .pf-menu nav { gap: 14px; }
        }
    </style>
</head>
<body class="pf-f-{{ $t['foto_forma'] }}">
    @if ($fundoImg) <div class="pf-bg" style="background-image:url('{{ $fundoImg }}')" aria-hidden="true"></div> @endif
    @if (! $previa && ! $pf->publicado)
        <div style="background:#fbbf24;color:#000;text-align:center;padding:6px;font-size:.85rem;font-family:system-ui">Prévia: o portfólio não está publicado e só você vê esta página.</div>
    @endif

    @if ($menu || $t['modo_escuro'])
        <div class="pf-menu">
            <div class="pf-max">
                <b>{{ $nome }}</b>
                <nav aria-label="Seções">
                    @if ($menu) @foreach ($secoes as $s) @if ($s['titulo'] !== '') <a href="#{{ $s['tipo'] === 'contato' ? 'contato' : 's-' . $s['id'] }}">{{ $s['titulo'] }}</a> @endif @endforeach @endif
                </nav>
                @if ($t['modo_escuro']) <button type="button" class="pf-modo" data-modo title="Trocar claro/escuro" aria-label="Trocar claro/escuro">◐</button> @endif
            </div>
        </div>
    @endif

    <div class="pf-pagina">
        {{-- coluna lateral (layout "lateral", telas largas) --}}
        @if ($lateral)
            <aside class="pf-lado">
                @if ($p['foto'] && $t['foto_forma'] !== 'nenhuma') <img class="pf-foto" src="{{ $img($p['foto']) }}" alt="{{ $nome }}"> @endif
                <h1 class="pf-nome" style="font-size:2rem">{{ $nome }}</h1>
                @if ($p['titulo']) <p class="pf-cargo" style="margin:0">{{ $p['titulo'] }}</p> @endif
                @if ($p['disponivel']) <span class="pf-disp">{{ $p['disponivel_texto'] ?: 'Disponível para novos projetos' }}</span> @endif
                @if ($p['local']) <p class="pf-suave">📍 {{ $p['local'] }}</p> @endif
                @if ($cta) <a class="pf-btn" href="{{ $cta }}" @if ($externo($cta)) target="_blank" rel="noopener" @endif>{{ $p['botao_texto'] }}</a> @endif
                <nav aria-label="Seções">@foreach ($secoes as $s) @if ($s['titulo'] !== '') <a href="#{{ $s['tipo'] === 'contato' ? 'contato' : 's-' . $s['id'] }}">{{ $s['titulo'] }}</a> @endif @endforeach</nav>
                <div class="pf-redes">@foreach ($p['links'] as $l) <a href="{{ $l['url'] }}" @if ($externo($l['url'])) target="_blank" rel="noopener me" @endif>{{ $l['rotulo'] ?: Portfolio::REDES[$l['rede']] }}</a> @endforeach</div>
            </aside>
        @endif

        <main class="pf-conteudo">
            {{-- cabeçalho --}}
            @if ($t['cabecalho'] === 'capa') <div class="pf-capa" @if ($p['capa']) style="background-image:url('{{ $img($p['capa']) }}')" @endif></div> @endif
            <{{ 'header' }} class="pf-hero">
                <div class="pf-max">
                    @if ($p['foto'] && $t['foto_forma'] !== 'nenhuma') <img class="pf-foto pf-rv" src="{{ $img($p['foto']) }}" alt="{{ $nome }}"> @endif
                    <div class="pf-rv">
                        @if ($p['disponivel']) <span class="pf-disp" style="margin-bottom:14px">{{ $p['disponivel_texto'] ?: 'Disponível para novos projetos' }}</span> @endif
                        <h1 class="pf-nome">{{ $nome }}</h1>
                        @if ($p['titulo']) <p class="pf-cargo">{{ $p['titulo'] }}</p> @endif
                        @if ($p['local']) <p class="pf-suave" style="margin-top:6px">📍 {{ $p['local'] }}</p> @endif
                        @if ($p['resumo']) <p class="pf-resumo pf-pre">{{ $p['resumo'] }}</p> @endif
                        @if ($cta || $p['cv'])
                            <div class="pf-acoes">
                                @if ($cta) <a class="pf-btn" href="{{ $cta }}" @if ($externo($cta)) target="_blank" rel="noopener" @endif>{{ $p['botao_texto'] }} →</a> @endif
                                @if ($p['cv']) <a class="pf-btn sec" href="{{ $p['cv'] }}" target="_blank" rel="noopener">Currículo ↓</a> @endif
                            </div>
                        @endif
                        @if ($p['links'])
                            <div class="pf-redes">@foreach ($p['links'] as $l) <a href="{{ $l['url'] }}" @if ($externo($l['url'])) target="_blank" rel="noopener me" @endif>{{ $l['rotulo'] ?: Portfolio::REDES[$l['rede']] }}</a> @endforeach</div>
                        @endif
                    </div>
                </div>
            </header>

            @foreach ($secoes as $s)
                @include('portfolio._secao', ['s' => $s])
            @endforeach

            <footer class="pf-rodape">
                <div class="pf-max">
                    <p>© {{ now()->year }} {{ $nome }}</p>
                    @if ($pf->comMarca()) <p style="margin-top:6px">Portfólio feito com <a href="{{ url('/') }}">Work Flower</a></p> @endif
                </div>
            </footer>
        </main>
    </div>

    @unless ($previa)
        <script>
            (function () {
                var h = document.documentElement;
                try { if (localStorage.getItem('pf-modo-{{ $pf->slug }}') === '1') h.classList.add('pf-inv'); } catch (e) {}
                var b = document.querySelector('[data-modo]');
                if (b) b.addEventListener('click', function () { var on = h.classList.toggle('pf-inv'); try { localStorage.setItem('pf-modo-{{ $pf->slug }}', on ? '1' : '0'); } catch (e) {} });
                if (!('IntersectionObserver' in window) || h.classList.contains('pf-a-nenhuma')) return;
                h.classList.add('pf-js');
                var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('pf-vis'); io.unobserve(e.target); } }); }, { rootMargin: '0px 0px -8% 0px' });
                document.querySelectorAll('.pf-rv').forEach(function (el) { io.observe(el); });
            })();
        </script>
    @endunless
</body>
</html>
