@extends('layouts.publico')

@section('titulo', 'Pronto, você saiu da lista')
@section('subtitulo', 'Dicas por e-mail do Work Flower')

@section('texto')
<div class="space-y-4 leading-relaxed text-[0.95rem]">
    <p>Você não vai mais receber os e-mails de dicas e boas-vindas do Work Flower.</p>
    <p>Os avisos que você mesmo ligar, como os lembretes de eventos do calendário, continuam chegando normalmente.</p>
    <p><a href="{{ route('inicio') }}" class="link-prim">Voltar ao Work Flower</a></p>
</div>
@endsection
