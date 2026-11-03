@extends('layouts.app')

@section('titulo', 'Reclame aqui')
@section('subtitulo', 'Algo deu errado, faltou alguma coisa ou tem uma ideia? Conte para a gente.')

@section('conteudo')
{{-- Mostrada quando a tabela "reclamacoes" ainda não existe no banco (falta rodar a migração 2026_11_02). --}}
<div class="card p-6 max-w-2xl space-y-3">
    <h2 class="titulo text-xl">O canal está sendo ativado</h2>
    <p class="texto-2">O Reclame aqui fica disponível em instantes. Enquanto isso, fale com a gente pela página de <a class="link-prim" href="{{ route('contato') }}">contato</a>.</p>
    @if ($admin)
        <p class="text-sm p-3 rounded-lg" style="background: var(--prim-suave); color: var(--prim-forte)">
            Para a administração: falta criar a tabela <b>reclamacoes</b> no banco (migração
            <code>2026_11_02_000000_create_reclamacoes_table</code>). Rode a migração no servidor e esta página passa a funcionar.
        </p>
    @endif
</div>
@endsection
