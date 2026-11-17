@extends('layouts.app')

@section('titulo', 'Outros sites da Websight')
@section('subtitulo', 'Quem do Work Flower também se cadastrou no Praga Vermelha, Worldbuilder, Trabalhos e nos SaaS do GED.')

@section('conteudo')
<div class="space-y-4">
    {{-- resumo por site --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($sites as $id => $s)
            <div class="card p-4 space-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="titulo text-base flex-1 min-w-0 truncate">{{ $s['nome'] }}</h2>
                    @if ($s['url'])<a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer" class="link-prim text-xs">abrir ↗</a>@endif
                </div>
                @if ($s['estado'] === 'sem-config')
                    <p class="text-xs texto-2">Não configurado. Ponha <code>WEBSIGHT_{{ strtoupper($id) }}_DB</code> no .env (caminho do .sqlite do site ou mysql://usuário:senha@host/banco).</p>
                @elseif ($s['estado'] === 'erro')
                    <p class="text-xs" style="color:#f87171">{{ $s['erro'] }}</p>
                @else
                    <p class="text-2xl font-bold">{{ $resumo[$id] ?? '…' }}</p>
                    <p class="text-xs texto-2">do Work Flower também cadastrados aqui
                        @if (($totais[$id] ?? null) !== null) · {{ $totais[$id] }} cadastros no site @endif</p>
                @endif
            </div>
        @endforeach
    </div>

    <form method="GET" class="card p-3 flex flex-wrap items-end gap-2">
        <label class="flex-1 min-w-[12rem]"><span class="rotulo">Buscar pessoa</span>
            <input type="search" name="q" value="{{ $busca }}" class="campo" placeholder="Nome ou e-mail"></label>
        <label><span class="rotulo">Mostrar</span>
            <select name="site" class="campo">
                <option value="">Todas as pessoas</option>
                @foreach ($sites as $id => $s)
                    @if ($s['estado'] === 'ok')<option value="{{ $id }}" @selected($filtro === $id)>Só quem está no {{ $s['nome'] }}</option>@endif
                @endforeach
            </select></label>
        <button class="btn">Filtrar</button>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left texto-2">
                    <th class="p-2">Pessoa</th>
                    @foreach ($sites as $id => $s)<th class="p-2 text-center whitespace-nowrap">{{ $s['nome'] }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @php $mostrados = 0; @endphp
                @foreach ($usuarios as $u)
                    @php $em = mb_strtolower(trim($u->email)); @endphp
                    @continue($filtro !== '' && empty($achados[$filtro][$em]))
                    @php $mostrados++; @endphp
                    <tr class="border-t" style="border-color: var(--linha)">
                        <td class="p-2"><b>{{ $u->name }}</b><br><span class="texto-2 text-xs">{{ $u->email }}</span></td>
                        @foreach ($sites as $id => $s)
                            <td class="p-2 text-center">
                                @if ($s['estado'] !== 'ok')<span class="texto-2" title="Site não configurado ou com erro">·</span>
                                @elseif (! empty($achados[$id][$em]))<span title="Cadastrado" aria-label="Cadastrado" style="color:#4ade80">✓</span>
                                @else<span class="texto-2" title="Não cadastrado" aria-label="Não cadastrado">—</span>@endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                @if (! $mostrados)
                    <tr><td class="p-4 texto-2" colspan="{{ count($sites) + 1 }}">Ninguém nesta página com esse filtro.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    {{ $usuarios->links() }}
</div>
@endsection
