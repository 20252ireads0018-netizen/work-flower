@extends('loja.base')

{{-- Pedido feito na mini loja: resumo, Pix (QR Code e copia e cola) na chave do dono e o "Já paguei". --}}
@section('titulo', 'Pedido ' . $pedido->codigo . ' · ' . $loja->nome)

@php
    $brl = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
    $situacao = \App\Models\LojaPedido::STATUS[$pedido->status] ?? $pedido->status;
@endphp

@section('conteudo')
    @if (session('ok')) <div class="lj-aviso" role="status">{{ session('ok') }}</div> @endif

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));align-items:start">
        <section class="lj-cartao" style="padding:18px">
            <h2 style="margin:0 0 4px">Pedido {{ $pedido->codigo }}</h2>
            <p style="margin:0 0 12px;opacity:.75">{{ $situacao }}</p>
            @foreach ($pedido->itens as $i)
                <p style="display:flex;justify-content:space-between;gap:8px;margin:6px 0"><span>{{ $i['qtd'] }} × {{ $i['nome'] }}</span><span>{{ $brl($i['preco'] * $i['qtd']) }}</span></p>
            @endforeach
            @if ((float) $pedido->desconto > 0)
                <p style="display:flex;justify-content:space-between;gap:8px;margin:6px 0"><span>Cupom {{ $pedido->cupom }}</span><span>− {{ $brl($pedido->desconto) }}</span></p>
            @endif
            @if ($pedido->frete_nome)
                <p style="display:flex;justify-content:space-between;gap:8px;margin:6px 0"><span>{{ $pedido->frete_nome }}</span><span>{{ (float) $pedido->frete > 0 ? $brl($pedido->frete) : 'Grátis' }}</span></p>
            @endif
            <p style="display:flex;justify-content:space-between;font-weight:800;font-size:1.15rem;border-top:1px solid color-mix(in srgb, var(--texto) 15%, transparent);padding-top:8px">
                <span>Total</span><span>{{ $brl($pedido->total) }}</span>
            </p>
            <p style="font-size:.85rem;opacity:.75;margin:0">Para {{ $pedido->cliente_nome }} · {{ $pedido->cliente_contato }}</p>
            <p style="font-size:.8rem;opacity:.6">Guarde o endereço desta página para acompanhar o pedido.</p>
        </section>

        @if ($pedido->aberto() && $loja->mpPronto() && ($pedido->pagamento === 'mp' || ! $codigo))
            <section class="lj-cartao" style="padding:18px;text-align:center">
                <p class="lj-preco" style="margin:0 0 6px">{{ $brl($pedido->total) }}</p>
                <p style="font-size:.85rem;opacity:.8;margin:0 0 12px">Pague com cartão de crédito, débito ou boleto na página segura do Mercado Pago. Assim que for aprovado, este pedido aparece como pago.</p>
                <form method="POST" action="{{ route('loja.cartao', [$loja->slug, $pedido->codigo]) }}">
                    @csrf
                    <button type="submit" class="lj-btn" style="width:100%">Pagar com Mercado Pago</button>
                </form>
            </section>
        @endif
        @if ($codigo && ($pedido->pagamento !== 'mp' || ! $loja->mpPronto()))
            <section class="lj-cartao" style="padding:18px;text-align:center">
                <div id="lj-qr" style="width:220px;height:220px;margin:0 auto;background:#fff;padding:10px;border-radius:10px" role="img" aria-label="QR Code do Pix"></div>
                <p class="lj-preco" style="margin:10px 0 4px">{{ $brl($pedido->total) }}</p>
                <p style="font-size:.85rem;opacity:.8;margin:0 0 10px">Pague com Pix para <b>{{ $loja->pix_nome }}</b>: abra o app do banco e leia o QR Code, ou copie o código abaixo.</p>
                <div style="display:flex;gap:6px">
                    <input class="lj-campo" id="lj-pix" readonly value="{{ $codigo }}" style="font-family:monospace;font-size:.75rem" onclick="this.select()" aria-label="Pix copia e cola">
                    <button type="button" class="lj-btn sec" id="lj-copiar">Copiar</button>
                </div>
                @if ($pedido->status === 'aguardando')
                    <form method="POST" action="{{ route('loja.paguei', [$loja->slug, $pedido->codigo]) }}" style="margin-top:14px">
                        @csrf
                        <button type="submit" class="lj-btn" style="width:100%">Já paguei</button>
                    </form>
                @else
                    <p class="lj-aviso" style="margin-top:14px">Você avisou que pagou. A loja confere o extrato e confirma.</p>
                @endif
                @if ($v['whatsapp'])
                    <a class="lj-btn sec" style="width:100%;margin-top:8px" target="_blank" rel="noopener"
                       href="https://wa.me/{{ strlen($v['whatsapp']) <= 11 ? '55' . $v['whatsapp'] : $v['whatsapp'] }}?text={{ rawurlencode('Olá! Fiz o pedido ' . $pedido->codigo . ' (' . $brl($pedido->total) . '). Segue o comprovante do Pix.') }}">Mandar o comprovante pelo WhatsApp</a>
                @endif
            </section>
        @elseif ($pedido->aberto())
            {{-- só Mercado Pago: o botão de pagar está acima --}}
        @elseif ($pedido->status === 'cancelado')
            <section class="lj-cartao" style="padding:18px"><p style="margin:0">Este pedido foi cancelado pela loja.</p></section>
        @else
            <section class="lj-cartao" style="padding:18px"><p style="margin:0">Pagamento confirmado. Obrigado pela compra!</p></section>
        @endif
    </div>
    <p style="margin-top:18px"><a href="{{ route('loja.ver', $loja->slug) }}">← Voltar para a loja</a></p>
@endsection

@push('scripts')
<script>
    // pedido feito: o carrinho desta loja fica vazio
    try { localStorage.removeItem('wf-loja:' + @json($loja->slug)); } catch (e) {}
</script>
@if ($codigo)
    {{-- integrity: se o arquivo do CDN for trocado, o navegador não roda (o QR Code decide para onde vai o dinheiro) --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"
            integrity="sha384-lQXOAyZwHXE55JFyrOMB7nY2Wv+m5ZWNtJcHrd1rceRQXAYNLak8ukN5TjBTcIwz" crossorigin="anonymous"></script>
    <script>
        (function () {
            var codigo = document.getElementById('lj-pix').value, caixa = document.getElementById('lj-qr');
            try {
                var qr = qrcode(0, 'M');
                qr.addData(codigo);
                qr.make();
                caixa.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
                var svg = caixa.querySelector('svg');
                if (svg) { svg.style.width = '100%'; svg.style.height = '100%'; }
            } catch (e) {
                caixa.textContent = 'Use o Pix copia e cola abaixo.';
            }
            var b = document.getElementById('lj-copiar');
            b.addEventListener('click', function () {
                var ok = function () { b.textContent = 'Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; }, 2000); };
                if (navigator.clipboard) navigator.clipboard.writeText(codigo).then(ok, function () {});
                else { document.getElementById('lj-pix').select(); document.execCommand('copy'); ok(); }
            });
        })();
    </script>
@endif
@endpush
