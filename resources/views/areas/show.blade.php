@extends('layouts.app')

@section('titulo', $area['nome'])
@section('subtitulo', $area['descricao'])

@section('conteudo')
<div class="grid gap-6 items-start lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
    <div class="lg:sticky lg:top-24">
        @include('areas.partials.formulario', ['slug' => $slug])
    </div>
    @include('areas.partials.lista', ['tarefas' => $tarefas])
</div>
@endsection