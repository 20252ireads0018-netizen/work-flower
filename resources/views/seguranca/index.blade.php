@extends('layouts.app')

@section('titulo', 'Segurança')
@section('subtitulo', 'Verificação em duas etapas: além da senha, o login pede um código do app autenticador do seu celular.')

@section('conteudo')
<div class="grid lg:grid-cols-2 gap-6 items-start">
    <section class="card p-6 space-y-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="titulo text-2xl">Verificação em duas etapas</h2>
            @if ($ativo)
                <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background: var(--prim-suave); color: var(--prim-forte)">Ativada</span>
            @else
                <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background: color-mix(in srgb, #d97706 18%, transparent); color: #b45309">Desativada</span>
            @endif
        </div>

        @if ($admin && ! $ativo)
            <p class="text-sm p-3 rounded-lg" role="alert" style="background: color-mix(in srgb, #d97706 15%, transparent); color: #92400e">
                <b>Conta de administração:</b> ela confirma pagamentos e vê dados de todas as pessoas. Ative a verificação em duas etapas para que só a senha não baste para entrar.
            </p>
        @endif

        @if ($codigos)
            <div class="p-4 rounded-lg space-y-3" style="border: 2px dashed var(--prim)">
                <h3 class="titulo text-lg">Seus códigos de recuperação</h3>
                <p class="text-sm texto-2">Guarde num lugar seguro (gerenciador de senhas ou papel). Se perder o celular, cada código entra uma vez. <b>Eles não aparecem de novo.</b></p>
                <ul class="grid grid-cols-2 gap-2 font-mono text-sm" id="codigos-rec">
                    @foreach ($codigos as $c)
                        <li class="p-1.5 rounded text-center" style="background: var(--fundo-2, rgba(127,127,127,.12))">{{ $c }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-sec text-sm" onclick="navigator.clipboard && navigator.clipboard.writeText([...document.querySelectorAll('#codigos-rec li')].map(l => l.textContent.trim()).join('\n')).then(() => this.textContent = 'Copiados!')">Copiar códigos</button>
            </div>
        @endif

        @if ($ativo)
            <p class="text-sm">Ativada em {{ $ativoEm?->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}. Códigos de recuperação restantes: <b>{{ $restantes }}</b>.</p>

            <form method="POST" action="{{ route('seguranca.codigos') }}" class="space-y-3 pt-4" style="border-top: 1px solid var(--linha)">
                @csrf
                <h3 class="titulo text-lg">Gerar novos códigos de recuperação</h3>
                <div>
                    <label class="rotulo" for="senha-cod">Sua senha</label>
                    <input id="senha-cod" type="password" name="password" class="campo" autocomplete="current-password" required>
                </div>
                <button class="btn-sec">Gerar códigos novos</button>
            </form>

            <form method="POST" action="{{ route('seguranca.desativar') }}" class="space-y-3 pt-4" style="border-top: 1px solid var(--linha)">
                @csrf
                <h3 class="titulo text-lg">Desativar</h3>
                <div>
                    <label class="rotulo" for="senha-des">Sua senha</label>
                    <input id="senha-des" type="password" name="password" class="campo" autocomplete="current-password" required>
                </div>
                <div>
                    <label class="rotulo" for="cod-des">Código do app (ou de recuperação)</label>
                    <input id="cod-des" name="codigo" class="campo" autocomplete="one-time-code" maxlength="40" required>
                </div>
                <button class="btn-sec" style="color: #b91c1c">Desativar verificação em duas etapas</button>
            </form>
        @elseif ($segredo)
            <ol class="text-sm space-y-2 list-decimal pl-5">
                <li>Instale um app autenticador no celular (Google Authenticator, Microsoft Authenticator, Authy, 1Password…).</li>
                <li>No app, toque em <b>+</b> e leia o QR Code abaixo (ou digite a chave).</li>
                <li>Digite o código de 6 dígitos que o app mostrar e sua senha.</li>
            </ol>
            <div class="flex flex-wrap items-center gap-4">
                <div id="qr-2fa" data-uri="{{ $uri }}" class="rounded-xl p-3 bg-white" style="width: 200px; height: 200px" role="img" aria-label="QR Code para o app autenticador"></div>
                <div class="text-sm min-w-0">
                    <p class="texto-2">Chave para digitar:</p>
                    <p class="font-mono break-all select-all font-semibold">{{ trim(chunk_split($segredo, 4, ' ')) }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('seguranca.confirmar') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="rotulo" for="cod-conf">Código do app</label>
                    <input id="cod-conf" name="codigo" class="campo tracking-widest" inputmode="numeric" autocomplete="one-time-code" maxlength="20" required placeholder="123456">
                    @error('codigo') <p class="erro">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="rotulo" for="senha-conf">Sua senha</label>
                    <input id="senha-conf" type="password" name="password" class="campo" autocomplete="current-password" required>
                </div>
                <button class="btn">Ativar</button>
            </form>
            <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"
                    integrity="sha384-lQXOAyZwHXE55JFyrOMB7nY2Wv+m5ZWNtJcHrd1rceRQXAYNLak8ukN5TjBTcIwz" crossorigin="anonymous"></script>
            <script>
                (function () {
                    var caixa = document.getElementById('qr-2fa');
                    try {
                        var qr = qrcode(0, 'M');
                        qr.addData(caixa.dataset.uri);
                        qr.make();
                        caixa.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
                    } catch (e) {
                        caixa.textContent = 'Não consegui desenhar o QR Code: digite a chave ao lado no app.';
                    }
                })();
            </script>
        @else
            <p class="text-sm">Com a verificação ativada, quem descobrir sua senha ainda precisa do seu celular para entrar.</p>
            <form method="POST" action="{{ route('seguranca.iniciar') }}">
                @csrf
                <button class="btn">Ativar verificação em duas etapas</button>
            </form>
        @endif

        @error('password') <p class="erro">{{ $message }}</p> @enderror
        @if (! $segredo) @error('codigo') <p class="erro">{{ $message }}</p> @enderror @endif
    </section>

    <section class="card p-6 space-y-3 text-sm">
        <h2 class="titulo text-xl">Como funciona</h2>
        <p>No login, depois da senha, o Work Flower pede o código de 6 dígitos do app. O código muda a cada 30 segundos e cada um vale uma vez.</p>
        <p>Perdeu o celular? Use um código de recuperação no lugar do código do app. Depois entre aqui e gere códigos novos ou ative de novo com o celular novo.</p>
        <p>"Manter conectado neste aparelho" continua valendo: o código é pedido a cada login novo.</p>
    </section>
</div>
@endsection
