@extends('layouts.app')

@section('titulo', 'Pagamentos Pix')
@section('subtitulo', 'Confira no extrato do banco e confirme: o plano da pessoa é liberado por um mês na hora.')

@section('conteudo')
@php use App\Models\PixPagamento; use App\Support\Pix; @endphp
<div class="space-y-4">
    <details class="card p-4" @if (! Pix::pronto() || $errors->any()) open @endif>
        <summary class="cursor-pointer font-semibold">
            Sua chave Pix
            @if (Pix::pronto())
                <span class="texto-2 font-normal text-sm">· {{ Pix::TIPOS[$cfg['tipo']] }} {{ $cfg['chave'] }} · {{ $cfg['nome'] }}</span>
            @else
                <span class="tag ml-1">falta configurar: ninguém consegue assinar até salvar</span>
            @endif
        </summary>
        <form method="POST" action="{{ route('pix.admin.config') }}" class="grid sm:grid-cols-2 gap-3 mt-4">
            @csrf
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Tipo de chave</span>
                <select name="tipo" class="campo">
                    @foreach (Pix::TIPOS as $v => $nome)
                        <option value="{{ $v }}" @selected(old('tipo', $cfg['tipo']) === $v)>{{ $nome }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Chave</span>
                <input name="chave" class="campo" maxlength="77" required value="{{ old('chave', $cfg['chave']) }}" autocomplete="off">
                @error('chave')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Nome do titular da conta (aparece no app do banco)</span>
                <input name="nome" class="campo" maxlength="60" required value="{{ old('nome', $cfg['nome']) }}">
                @error('nome')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Cidade</span>
                <input name="cidade" class="campo" maxlength="40" required value="{{ old('cidade', $cfg['cidade']) }}">
                @error('cidade')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <div class="sm:col-span-2"><button type="submit" class="btn">Salvar chave</button></div>
        </form>
    </details>

    @php $brl = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.'); @endphp

    {{-- ========== Pedidos das mini lojas pagos por Pix nesta chave: confirmar vira saldo do dono da loja ========== --}}
    <section class="space-y-2" id="pedidos-lojas" aria-labelledby="pedidos-lojas-t">
        <h2 id="pedidos-lojas-t" class="titulo text-lg">Pedidos de lojas
            <span class="texto-2 text-sm font-normal">· {{ $pedidosLojas->where('status', 'informado')->count() }} avisaram que pagaram · {{ $pedidosLojas->where('status', 'aguardando')->count() }} aguardando</span></h2>
        <p class="text-xs texto-2">O Pix dos pedidos das lojas cai nesta mesma chave. Ao confirmar, o pedido fica pago e o valor entra no saldo do dono da loja, que pode pedir resgate.</p>
        @forelse ($pedidosLojas as $p)
            <article class="card p-4 flex flex-wrap items-center gap-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="tag">{{ \App\Models\LojaPedido::STATUS[$p->status] ?? $p->status }}</span>
                        <b>{{ $brl($p->total) }}</b>
                        <span class="texto-2 text-sm">loja {{ $p->loja?->nome }}{{ $p->loja?->trashed() ? ' (excluída)' : '' }} · {{ $p->loja?->user?->email }}</span>
                    </div>
                    <p class="text-sm">Cliente: {{ $p->cliente_nome }} · {{ $p->cliente_contato }}</p>
                    <p class="text-xs texto-2">criado {{ $p->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i') }}
                        @if ($p->informado_em) · avisou que pagou {{ $p->informado_em->tz('America/Sao_Paulo')->format('d/m H:i') }} @endif
                        · identificador no Pix <span class="font-mono">{{ $p->codigo }}</span>
                        @if ($p->admin_obs) · {{ $p->admin_obs }} @endif</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('pix.admin.loja.confirmar', $p) }}" onsubmit="return confirm('Confirmar o Pix de {{ $brl($p->total) }} do pedido {{ $p->codigo }}? O valor entra no saldo da loja.')">
                        @csrf
                        <button type="submit" class="btn !py-1">Confirmar pagamento</button>
                    </form>
                    @if ($p->status === 'informado')
                        <form method="POST" action="{{ route('pix.admin.loja.recusar', $p) }}" class="flex gap-2">
                            @csrf
                            <input name="observacao" class="campo !py-1 !w-48" maxlength="300" placeholder="Motivo (opcional)" aria-label="Motivo">
                            <button type="submit" class="btn-sec !py-1">Não encontrei</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="texto-2 text-sm">Nenhum pedido de loja esperando confirmação.</p>
        @endforelse
    </section>

    {{-- ========== Curso da página de vagas (/vagas): Pix nesta chave; o acesso é mandado por outro sistema ========== --}}
    <section class="space-y-2" id="cursos-vagas" aria-labelledby="cursos-vagas-t">
        <h2 id="cursos-vagas-t" class="titulo text-lg">Curso da página de vagas
            <span class="texto-2 text-sm font-normal">· {{ $cursosVagas->where('curso_status', 'informado')->count() }} avisaram que pagaram · {{ $cursosVagas->where('curso_status', 'aguardando')->count() }} aguardando</span></h2>
        <p class="text-xs texto-2">Compra opcional do curso, feita depois da candidatura em /vagas. Confira o identificador no extrato e confirme; depois mande o acesso pelo seu sistema para o e-mail ou WhatsApp abaixo.</p>
        @forelse ($cursosVagas as $c)
            <article class="card p-4 flex flex-wrap items-center gap-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="tag">{{ $c->cursoSituacao() }}</span>
                        <b>{{ $brl($c->curso_valor) }}</b>
                        <span class="texto-2 text-sm">{{ $c->nome }} · {{ $c->email }} · {{ $c->fone }}</span>
                    </div>
                    <p class="text-xs texto-2">Pix gerado {{ $c->curso_pedido_em?->tz('America/Sao_Paulo')->format('d/m/Y H:i') }}
                        @if ($c->curso_informado_em) · avisou que pagou {{ $c->curso_informado_em->tz('America/Sao_Paulo')->format('d/m H:i') }} @endif
                        @if ($c->curso_pagador) · pagador: {{ $c->curso_pagador }} @endif
                        · identificador no Pix <span class="font-mono">{{ $c->curso_txid }}</span></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('pix.admin.curso.confirmar', $c) }}" onsubmit="return confirm('Confirmar o Pix de {{ $brl($c->curso_valor) }} do curso ({{ $c->curso_txid }})?')">
                        @csrf
                        <button type="submit" class="btn !py-1">Confirmar pagamento</button>
                    </form>
                    @if ($c->curso_status === 'informado')
                        <form method="POST" action="{{ route('pix.admin.curso.recusar', $c) }}" class="flex gap-2">
                            @csrf
                            <input name="observacao" class="campo !py-1 !w-48" maxlength="300" placeholder="Motivo (opcional)" aria-label="Motivo">
                            <button type="submit" class="btn-sec !py-1">Não encontrei</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <p class="texto-2 text-sm">Nenhuma compra do curso esperando confirmação.</p>
        @endforelse
    </section>

    {{-- ========== Resgates do saldo das lojas ========== --}}
    <section class="space-y-2" id="resgates" aria-labelledby="resgates-t">
        <h2 id="resgates-t" class="titulo text-lg">Resgates pendentes
            <span class="texto-2 text-sm font-normal">· {{ $resgates->count() }} · {{ $brl($resgates->sum('valor')) }}</span></h2>
        <p class="text-xs texto-2">Faça o Pix para a chave mostrada e marque como pago. O prometido ao dono da loja é pagar até a data do prazo (7 dias úteis). Recusar devolve o valor ao saldo da pessoa.</p>
        @forelse ($resgates as $r)
            <article class="card p-4 flex flex-wrap items-center gap-4" @if ($r->prazo->isPast()) style="border-color:#ef4444" @endif>
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <b>{{ $brl($r->valor) }}</b>
                        <span class="tag" @if ($r->prazo->isPast()) style="color:#ef4444" @endif>prazo {{ $r->prazo->format('d/m/Y') }}{{ $r->prazo->isPast() ? ' (atrasado)' : '' }}</span>
                        <span class="texto-2 text-sm">{{ $r->user?->name }} · {{ $r->user?->email }}</span>
                    </div>
                    <p class="text-sm">Pix para <b>{{ \App\Support\Pix::TIPOS[$r->pix_tipo] ?? $r->pix_tipo }}</b> <span class="font-mono break-all">{{ $r->pix_chave }}</span> · titular {{ $r->pix_nome }}</p>
                    <p class="text-xs texto-2">pedido em {{ $r->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i') }} · resgate #{{ $r->id }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('pix.admin.resgate.pagar', $r) }}" onsubmit="return confirm('Marcar o resgate de {{ $brl($r->valor) }} como pago? Faça o Pix antes.')">
                        @csrf
                        <button type="submit" class="btn !py-1">Marcar como pago</button>
                    </form>
                    <form method="POST" action="{{ route('pix.admin.resgate.recusar', $r) }}" class="flex gap-2" onsubmit="return confirm('Recusar este resgate? O valor volta ao saldo da pessoa.')">
                        @csrf
                        <input name="observacao" class="campo !py-1 !w-48" maxlength="300" placeholder="Motivo (a pessoa vê)" aria-label="Motivo">
                        <button type="submit" class="btn-sec !py-1">Recusar</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="texto-2 text-sm">Nenhum resgate pendente.</p>
        @endforelse
        @if ($resgatesFeitos->isNotEmpty())
            <details class="text-sm"><summary class="cursor-pointer texto-2">Últimos resgates tratados</summary>
                <ul class="mt-2 space-y-1">
                    @foreach ($resgatesFeitos as $r)
                        <li>#{{ $r->id }} · {{ $brl($r->valor) }} · {{ $r->user?->email }} · {{ \App\Models\LojaResgate::STATUS[$r->status] ?? $r->status }} {{ $r->pago_em ? 'em ' . $r->pago_em->format('d/m/Y') : '' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>

    <h2 class="titulo text-lg">Planos</h2>
    <nav class="flex flex-wrap gap-2" aria-label="Filtrar por situação">
        <a href="{{ route('pix.admin') }}" class="btn-sec !py-1 {{ ! $status ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">Em andamento e tratados</a>
        @foreach (PixPagamento::STATUS as $v => $nome)
            <a href="{{ route('pix.admin', ['status' => $v]) }}" class="btn-sec !py-1 {{ $status === $v ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">{{ $nome }} ({{ $contagem[$v] ?? 0 }})</a>
        @endforeach
    </nav>

    @forelse ($lista as $p)
        <article class="card p-4 flex flex-wrap items-center gap-4">
            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tag">{{ PixPagamento::STATUS[$p->status] ?? $p->status }}</span>
                    <b>R$ {{ number_format((float) $p->valor, 2, ',', '.') }}</b>
                    <span class="texto-2 text-sm">plano {{ \App\Support\Planos::nome($p->plano_slug) }}</span>
                    @if ($p->vitalicio)<span class="tag" style="background:#7c3aed;color:#fff">Vitalício</span>@endif
                </div>
                <p class="text-sm">{{ $p->user?->name }} · {{ $p->user?->email }}</p>
                <p class="text-xs texto-2">
                    criado {{ $p->created_at->format('d/m/Y H:i') }}
                    @if ($p->informado_em) · avisou que pagou {{ $p->informado_em->format('d/m H:i') }} @endif
                    @if ($p->pagador) · pago por <b>{{ $p->pagador }}</b> @endif
                    · id <span class="font-mono">{{ $p->txid }}</span>
                    @if ($p->liberado_ate) · liberado até {{ $p->liberado_ate->format('d/m/Y') }} @endif
                    @if ($p->vitalicio && $p->status === "confirmado") · liberado para sempre @endif
                    @if ($p->observacao) · {{ $p->observacao }} @endif
                </p>
            </div>
            @if (in_array($p->status, ['aguardando', 'informado', 'recusado'], true))
                <div class="flex flex-wrap items-center gap-2">
                    <form method="POST" action="{{ route('pix.admin.confirmar', $p) }}"
                          onsubmit="return confirm('Confirmar o Pix de R$ {{ number_format((float) $p->valor, 2, ',', '.') }} e liberar o plano?')">
                        @csrf
                        <button type="submit" class="btn !py-1">Confirmar e liberar</button>
                    </form>
                    @if ($p->status !== 'recusado')
                        <form method="POST" action="{{ route('pix.admin.recusar', $p) }}" class="flex gap-2">
                            @csrf
                            <input name="observacao" class="campo !py-1 !w-48" maxlength="500" placeholder="Motivo (opcional)" aria-label="Motivo">
                            <button type="submit" class="btn-sec !py-1">Não encontrei</button>
                        </form>
                    @endif
                </div>
            @endif
        </article>
    @empty
        <p class="texto-2">Nenhum pagamento por aqui.</p>
    @endforelse

    {{ $lista->links() }}
</div>
@endsection
