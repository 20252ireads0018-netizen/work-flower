@extends('layouts.app')

@section('titulo', 'Pagar com Pix')
@section('subtitulo', 'Plano ' . ($plano->name ?? \App\Support\Planos::nome($pix->plano_slug)) . ' · um mês de acesso.')

@section('conteudo')
@php
    $valor = number_format((float) $pix->valor, 2, ',', '.');
    $titular = $cfg['nome'];
@endphp
<div class="max-w-3xl mx-auto space-y-4">
    @if ($pix->status === 'confirmado')
        <div class="card p-6 text-center space-y-2">
            <p class="titulo text-2xl">Pagamento confirmado</p>
            <p class="texto-2 text-sm">Seu plano {{ \App\Support\Planos::nome($pix->plano_slug) }} está liberado até {{ $pix->liberado_ate?->format('d/m/Y') }}.</p>
            <a href="{{ route('painel') }}" class="btn mt-2">Ir para o painel</a>
        </div>
    @elseif (in_array($pix->status, ['cancelado'], true) || ! $codigo)
        <div class="card p-6 text-center space-y-2">
            <p class="titulo text-xl">{{ $pix->status === 'cancelado' ? 'Este Pix foi cancelado' : 'Pix indisponível no momento' }}</p>
            <a href="{{ route('planos') }}" class="btn-sec mt-2">Voltar aos planos</a>
        </div>
    @else
        @if ($pix->status === 'recusado')
            <div class="card p-4 text-sm" role="alert">
                Não encontramos este pagamento no extrato.
                @if ($pix->observacao) <span class="block mt-1 texto-2">{{ $pix->observacao }}</span> @endif
                Se você já pagou, responda pelo <a class="underline" href="{{ route('reclame.index') }}">Reclame aqui</a> com o comprovante.
            </div>
        @endif

        <div class="card p-6 grid md:grid-cols-[auto_1fr] gap-6 items-start">
            <div class="mx-auto text-center">
                <div id="pix-qr" class="rounded-xl p-3 bg-white inline-block" style="width: 236px; height: 236px" aria-label="QR Code do Pix" role="img"></div>
                <p class="titulo text-3xl mt-3">R$ {{ $valor }}</p>
            </div>

            <div class="space-y-4 min-w-0">
                <ol class="text-sm space-y-1.5 list-decimal pl-5">
                    <li>Abra o app do seu banco e escolha <b>Pix › Ler QR Code</b> (ou <b>Pix copia e cola</b>).</li>
                    <li>Confira o valor de <b>R$ {{ $valor }}</b> e o recebedor <b>{{ $titular }}</b>.</li>
                    <li>Pague e volte aqui para tocar em <b>Já paguei</b>.</li>
                </ol>

                <div>
                    <label for="pix-codigo" class="texto-2 text-xs block mb-1">Pix copia e cola</label>
                    <div class="flex gap-2">
                        <input id="pix-codigo" class="campo font-mono text-xs" readonly value="{{ $codigo }}" onclick="this.select()">
                        <button type="button" class="btn-sec shrink-0" id="pix-copiar">Copiar</button>
                    </div>
                </div>

                <p class="text-xs texto-2">Identificador do pagamento: <b class="font-mono">{{ $pix->txid }}</b></p>

                @if ($pix->status === 'informado')
                    <div class="rounded-lg p-3 text-sm" style="background: var(--prim-suave); color: var(--prim-forte)">
                        Você avisou que pagou em {{ $pix->informado_em?->format('d/m/Y H:i') }}. Estamos conferindo; o plano é liberado assim que o Pix aparecer no extrato.
                    </div>
                @else
                    <form method="POST" action="{{ route('pix.paguei', $pix) }}" class="space-y-2">
                        @csrf
                        <label for="pagador" class="texto-2 text-xs block">Nome de quem pagou (se não for você)</label>
                        <input id="pagador" name="pagador" class="campo" maxlength="120" placeholder="{{ auth()->user()->name }}">
                        <div class="flex flex-wrap gap-2 pt-1">
                            <button type="submit" class="btn">Já paguei</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('pix.cancelar', $pix) }}">
                        @csrf
                        <button type="submit" class="text-xs texto-2 underline">Desistir deste Pix</button>
                    </form>
                @endif
            </div>
        </div>

        <p class="text-xs texto-2 text-center">O Pix vai direto para a conta do Work Flower. A liberação é feita à mão depois de conferir o extrato, normalmente no mesmo dia.</p>

        {{-- integrity: se o arquivo do CDN for trocado, o navegador não roda (o QR Code decide para onde vai o dinheiro) --}}
        <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"
                integrity="sha384-lQXOAyZwHXE55JFyrOMB7nY2Wv+m5ZWNtJcHrd1rceRQXAYNLak8ukN5TjBTcIwz" crossorigin="anonymous"></script>
        <script>
            (function () {
                var codigo = document.getElementById('pix-codigo').value;
                var caixa = document.getElementById('pix-qr');
                try {
                    var qr = qrcode(0, 'M');
                    qr.addData(codigo);
                    qr.make();
                    caixa.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
                    var svg = caixa.querySelector('svg');
                    if (svg) { svg.style.width = '100%'; svg.style.height = '100%'; }
                } catch (e) {
                    caixa.innerHTML = '<p class="text-xs" style="color:#333;padding-top:90px">Use o Pix copia e cola ao lado.</p>';
                }
                var botao = document.getElementById('pix-copiar');
                botao.addEventListener('click', function () {
                    var feito = function () { botao.textContent = 'Copiado!'; setTimeout(function () { botao.textContent = 'Copiar'; }, 2000); };
                    if (navigator.clipboard) { navigator.clipboard.writeText(codigo).then(feito, function () {}); }
                    else { var c = document.getElementById('pix-codigo'); c.select(); document.execCommand('copy'); feito(); }
                });
            })();
        </script>
    @endif
</div>
@endsection
