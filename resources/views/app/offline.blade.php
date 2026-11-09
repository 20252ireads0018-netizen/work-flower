{{-- Página mostrada pelo service worker quando não há conexão. Guardada no aparelho na instalação,
     então não pode depender de nada de fora: sem Alpine, sem CSS externo, sem sessão. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="{{ \App\Models\User::COR_CABECALHO_PADRAO }}">
    <meta name="robots" content="noindex">
    <title>Sem conexão · Work Flower</title>
    <link rel="icon" type="image/png" href="{{ asset('icons/icone-192.png') }}">
    <style>
        :root { color-scheme: dark; --prim: {{ \App\Models\User::COR_PRIMARIA_PADRAO }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; min-height: 100dvh;
            display: grid; place-items: center; padding: 24px;
            background: {{ \App\Http\Controllers\AppInstalavelController::COR_FUNDO }}; color: #e8eef7;
            font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main { max-width: 26rem; text-align: center; }
        img { width: 88px; height: 88px; border-radius: 20px; }
        h1 { font-size: 1.6rem; margin: 20px 0 8px; }
        p { color: #a9b4c4; margin: 0 0 24px; }
        button {
            font: inherit; font-weight: 600; cursor: pointer;
            padding: 10px 22px; border: 0; border-radius: 10px;
            background: var(--prim); color: #fff;
        }
        button:focus-visible { outline: 3px solid #9cc0f0; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <img src="{{ asset('icons/icone-192.png') }}" alt="">
        <h1>Sem conexão</h1>
        <p>O Work Flower precisa de internet para abrir seus quadros. Assim que a conexão voltar, a página recarrega sozinha.</p>
        <button type="button" onclick="location.reload()">Tentar de novo</button>
    </main>
    <script>window.addEventListener('online', function () { location.reload(); });</script>
</body>
</html>
