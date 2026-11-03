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
                </div>
                <p class="text-sm">{{ $p->user?->name }} · {{ $p->user?->email }}</p>
                <p class="text-xs texto-2">
                    criado {{ $p->created_at->format('d/m/Y H:i') }}
                    @if ($p->informado_em) · avisou que pagou {{ $p->informado_em->format('d/m H:i') }} @endif
                    @if ($p->pagador) · pago por <b>{{ $p->pagador }}</b> @endif
                    · id <span class="font-mono">{{ $p->txid }}</span>
                    @if ($p->liberado_ate) · liberado até {{ $p->liberado_ate->format('d/m/Y') }} @endif
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
