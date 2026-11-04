{{-- Saldo da loja (aba Pagamento): o Pix dos pedidos cai na chave da plataforma e vira saldo depois de conferido;
     o dono resgata para a própria chave Pix, pago em até 7 dias úteis. Vars: $saldo, $movimentos, $resgates, $chaveResgate. --}}
@php
    use App\Models\LojaResgate;
    use App\Models\LojaSaldoMovimento;
    use App\Support\Pix;
    $brl = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
    $prazoNovo = $saldo['prazo_novo'];
@endphp
<section class="card p-4 space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <h3 class="font-semibold flex-1">Saldo da loja (Pix)</h3>
        @unless ($pixPlataforma)
            <span class="lj-st aguardando">O Pix da plataforma ainda não foi configurado: por enquanto a loja recebe só pelo Mercado Pago</span>
        @endunless
    </div>
    <p class="text-sm texto-2">
        Os clientes pagam o Pix na conta do <b>Work Flower</b>. A plataforma confere o pagamento (normalmente no mesmo dia útil), o pedido
        passa para “Pago” e o valor entra no seu saldo. Quando quiser, peça o resgate para a sua chave Pix: o pagamento é feito em até
        <b>7 dias úteis</b>.
    </p>

    <div class="lj-saldo">
        <div class="wf-stat"><p class="text-xs texto-2">Disponível para resgate</p><p class="titulo text-2xl">{{ $brl($saldo['disponivel']) }}</p></div>
        <div class="wf-stat"><p class="text-xs texto-2">A confirmar</p><p class="titulo text-lg">{{ $brl($saldo['a_confirmar']) }}</p>
            <p class="text-xs texto-2">{{ $saldo['n_a_confirmar'] === 1 ? '1 pedido com aviso de pagamento' : $saldo['n_a_confirmar'] . ' pedidos com aviso de pagamento' }}{{ $saldo['aguardando'] > 0 ? ' · ' . $brl($saldo['aguardando']) . ' sem aviso ainda' : '' }}</p></div>
        <div class="wf-stat"><p class="text-xs texto-2">Em resgate</p><p class="titulo text-lg">{{ $brl($saldo['em_resgate']) }}</p>
            @if ($saldo['proximo_prazo']) <p class="text-xs texto-2">próximo até {{ \Illuminate\Support\Carbon::parse($saldo['proximo_prazo'])->format('d/m/Y') }}</p> @endif</div>
    </div>

    @if ($saldo['disponivel'] >= \App\Support\SaldoLoja::RESGATE_MINIMO)
        <form method="POST" action="{{ route('carteira.loja.resgatar') }}" class="space-y-3 pt-3" style="border-top:1px solid var(--linha)">
            @csrf
            <p class="font-semibold text-sm">Resgatar</p>
            <div class="lj-grade">
                <label class="lj-campo"><span>Valor (R$)</span><input name="valor" type="number" step="0.01" min="{{ \App\Support\SaldoLoja::RESGATE_MINIMO }}" max="{{ $saldo['disponivel'] }}" class="campo" required value="{{ old('valor', number_format($saldo['disponivel'], 2, '.', '')) }}"></label>
                <label class="lj-campo"><span>Tipo da sua chave Pix</span>
                    <select name="tipo" class="campo">@foreach (Pix::TIPOS as $k => $n)<option value="{{ $k }}" @selected(old('tipo', $chaveResgate?->pix_tipo) === $k)>{{ $n }}</option>@endforeach</select>
                </label>
                <label class="lj-campo"><span>Chave</span><input name="chave" class="campo" maxlength="77" required autocomplete="off" value="{{ old('chave', $chaveResgate?->pix_chave) }}"></label>
                <label class="lj-campo"><span>Nome do titular da chave</span><input name="nome" class="campo" maxlength="60" required value="{{ old('nome', $chaveResgate?->pix_nome) }}"></label>
            </div>
            <p class="text-sm">Pedindo hoje, o Pix cai na sua chave <b>até {{ $prazoNovo->format('d/m/Y') }}</b> ({{ $prazoNovo->locale('pt_BR')->translatedFormat('l') }}), 7 dias úteis sem contar sábados e domingos.</p>
            <button type="submit" class="btn">Resgatar</button>
        </form>
    @else
        <p class="text-sm texto-2 pt-3" style="border-top:1px solid var(--linha)">
            Ainda não há saldo para resgatar. Ele aparece aqui quando a plataforma confirmar o Pix de um pedido. O resgate mínimo é de {{ $brl(\App\Support\SaldoLoja::RESGATE_MINIMO) }}.
        </p>
    @endif

    <p class="text-xs texto-2 rounded-lg p-3" style="background:var(--superficie-2)">
        <b>Importante:</b> neste modelo a plataforma Work Flower recebe o dinheiro dos seus clientes e repassa para você (intermediação de
        pagamentos de terceiros). Isso pode ter efeitos fiscais e legais para você e para a plataforma (por exemplo, nota fiscal das vendas e
        declaração de rendimentos). Confira com o seu contador.
    </p>

    @if ($resgates->isNotEmpty())
        <div>
            <p class="font-semibold text-sm mb-2">Resgates</p>
            <div class="overflow-x-auto">
                <table class="wf-tabela text-sm">
                    <thead><tr><th>Pedido em</th><th>Valor</th><th>Chave</th><th>Situação</th><th>Prazo</th></tr></thead>
                    <tbody>
                        @foreach ($resgates as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                                <td class="whitespace-nowrap">{{ $brl($r->valor) }}</td>
                                <td class="break-all">{{ Pix::TIPOS[$r->pix_tipo] ?? '' }} {{ $r->pix_chave }}</td>
                                <td><span class="lj-st {{ ['pendente' => 'aguardando', 'pago' => 'pago', 'recusado' => 'cancelado'][$r->status] ?? '' }}">{{ LojaResgate::STATUS[$r->status] ?? $r->status }}</span>
                                    @if ($r->observacao) <span class="text-xs texto-2">{{ $r->observacao }}</span> @endif</td>
                                <td class="whitespace-nowrap">{{ $r->status === 'pago' ? 'pago em ' . $r->pago_em?->tz('America/Sao_Paulo')->format('d/m/Y') : 'até ' . $r->prazo->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div>
        <p class="font-semibold text-sm mb-2">Extrato</p>
        @if ($movimentos->isEmpty())
            <p class="text-sm texto-2">Nenhum lançamento ainda. Os pedidos confirmados e os resgates aparecem aqui.</p>
        @else
            <ul class="space-y-1.5">
                @foreach ($movimentos as $m)
                    <li class="wf-linha text-sm">
                        <div class="min-w-0"><p class="truncate">{{ $m->descricao }}</p>
                            <p class="text-xs texto-2">{{ $m->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ LojaSaldoMovimento::TIPOS[$m->tipo] ?? $m->tipo }}</p></div>
                        @if ($m->tipo === 'mp')
                            <span class="texto-2 whitespace-nowrap" title="Pago no seu Mercado Pago: não entra no saldo">{{ $brl($m->valor) }}</span>
                        @else
                            <span class="font-medium whitespace-nowrap" style="color: {{ (float) $m->valor >= 0 ? '#4ade80' : '#f87171' }}">{{ (float) $m->valor >= 0 ? '+' : '−' }} {{ $brl(abs((float) $m->valor)) }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
