@extends('layouts.app')

@section('titulo', 'Reclamações')
@section('subtitulo', 'Mensagens do "Reclame aqui". Responda e marque como resolvida; a pessoa vê a resposta na página dela.')

@section('conteudo')
@php use App\Models\Reclamacao; @endphp
<div class="space-y-4">
    @if (session('ok'))
        <p class="text-sm p-3 rounded-lg" style="background: var(--prim-suave); color: var(--prim-forte)">{{ session('ok') }}</p>
    @endif
    <nav class="flex flex-wrap gap-2" aria-label="Filtrar por situação">
        <a href="{{ route('reclame.admin') }}" class="btn-sec !py-1 {{ ! $status ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">Todas ({{ $contagem->sum() }})</a>
        @foreach (Reclamacao::STATUS as $v => $nome)
            <a href="{{ route('reclame.admin', ['status' => $v]) }}" class="btn-sec !py-1 {{ $status === $v ? '!bg-[var(--prim)] !text-[var(--on-prim)]' : '' }}">{{ $nome }} ({{ $contagem[$v] ?? 0 }})</a>
        @endforeach
    </nav>

    @forelse ($lista as $r)
        <article class="card p-4 grid lg:grid-cols-2 gap-4">
            <div class="space-y-2 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tag">{{ Reclamacao::TIPOS[$r->tipo] ?? $r->tipo }}</span>
                    <b>{{ $r->assunto }}</b>
                </div>
                <p class="text-sm whitespace-pre-line">{{ $r->mensagem }}</p>
                <p class="text-xs texto-2">
                    {{ $r->user?->name }} · {{ $r->user?->email }} · {{ $r->created_at->format('d/m/Y H:i') }}
                    @if ($r->pagina) · página: <span class="break-all">{{ $r->pagina }}</span>@endif
                </p>
            </div>
            <form method="POST" action="{{ route('reclame.responder', $r) }}" class="space-y-2">
                @csrf
                <textarea name="resposta" class="campo" rows="4" maxlength="5000" placeholder="Sua resposta (a pessoa vê na página dela)">{{ $r->resposta }}</textarea>
                <div class="flex flex-wrap items-center gap-2">
                    <select name="status" class="campo !w-auto" aria-label="Situação">
                        @foreach (Reclamacao::STATUS as $v => $nome)
                            <option value="{{ $v }}" @selected($r->status === $v)>{{ $nome }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn !py-1">Salvar</button>
                    @if ($r->respondida_em)<span class="text-xs texto-2">respondida em {{ $r->respondida_em->format('d/m H:i') }}</span>@endif
                </div>
            </form>
        </article>
    @empty
        <p class="texto-2">Nenhuma mensagem por aqui.</p>
    @endforelse

    {{ $lista->links() }}
</div>
@endsection
