@extends('layouts.app')

@section('titulo', 'Promoções')
@section('subtitulo', 'Preço baixo por um prazo ou o plano vitalício ("Pague uma vez, receba para sempre"). Cada pessoa vê o aviso uma vez ao entrar.')

@section('conteudo')
@php
    $hora = fn ($d) => $d?->copy()->tz($fuso)->format('d/m/Y H:i');
    $campoHora = fn ($d) => $d?->copy()->tz($fuso)->format('Y-m-d\TH:i');
    $agora = now()->tz($fuso);
    $reais = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp
<div class="space-y-4">
    @if ($planos->isEmpty())
        <p class="card p-4 text-sm">Nenhum plano pago está à venda agora.</p>
    @else
        <form method="POST" action="{{ route('promocoes.admin.criar') }}" class="card p-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3"
              x-data="{ modalidade: @js(old('modalidade', 'mensal')) }">
            @csrf
            <h2 class="titulo text-lg sm:col-span-2 lg:col-span-3">Nova promoção</h2>

            <fieldset class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-2" aria-label="Tipo de promoção">
                <label class="wf-promo-op" :class="modalidade === 'mensal' && 'ativo'">
                    <input type="radio" name="modalidade" value="mensal" x-model="modalidade" class="sr-only">
                    <b>Preço do mês</b>
                    <span class="texto-2 text-xs">O Pix do mês sai mais barato até o fim do prazo.</span>
                </label>
                <label class="wf-promo-op" :class="modalidade === 'vitalicio' && 'ativo'">
                    <input type="radio" name="modalidade" value="vitalicio" x-model="modalidade" class="sr-only">
                    <b>Vitalício · Pague uma vez, receba para sempre</b>
                    <span class="texto-2 text-xs">Um Pix só; o plano nunca expira. À venda só durante o prazo.</span>
                </label>
            </fieldset>

            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Plano</span>
                <select name="plano_slug" class="campo">
                    @foreach ($planos as $p)
                        <option value="{{ $p->slug }}" @selected(old('plano_slug') === $p->slug)>{{ $p->name }} · {{ $reais($p->price) }}/mês</option>
                    @endforeach
                </select>
                @error('plano_slug')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1" x-show="modalidade === 'vitalicio'" x-cloak>
                <span class="texto-2 text-xs">Preço cheio do vitalício (R$)</span>
                <input name="preco_cheio" class="campo" inputmode="decimal" type="number" step="0.01" min="0.01" value="{{ old('preco_cheio') }}"
                       placeholder="Ex.: 497" :required="modalidade === 'vitalicio'" :disabled="modalidade !== 'vitalicio'">
                @error('preco_cheio')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Como dar o desconto</span>
                <select name="tipo" class="campo">
                    <option value="preco" @selected(old('tipo', 'preco') === 'preco')>Preço promocional (R$)</option>
                    <option value="desconto" @selected(old('tipo') === 'desconto')>Desconto (%)</option>
                </select>
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Valor (R$ ou %)</span>
                <input name="valor" class="campo" inputmode="decimal" type="number" step="0.01" min="0.01" required value="{{ old('valor') }}"
                       :placeholder="modalidade === 'vitalicio' ? 'Ex.: 197 ou 60' : 'Ex.: 4,90 ou 70'">
                @error('valor')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Começa (vazio = agora)</span>
                <input name="comeca_em" type="datetime-local" class="campo" value="{{ old('comeca_em') }}">
                @error('comeca_em')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Termina (hora de Brasília)</span>
                <input name="termina_em" type="datetime-local" class="campo" required value="{{ old('termina_em', $agora->copy()->addDay()->format('Y-m-d\TH:i')) }}">
                @error('termina_em')<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
            </label>
            <label class="text-sm space-y-1">
                <span class="texto-2 text-xs">Chamada (opcional)</span>
                <input name="titulo" class="campo" maxlength="80" value="{{ old('titulo') }}"
                       :placeholder="modalidade === 'vitalicio' ? 'Pague uma vez, receba para sempre' : 'Ex.: Só hoje!'">
            </label>
            <label class="text-sm space-y-1 sm:col-span-2 lg:col-span-3">
                <span class="texto-2 text-xs">Texto do aviso (aparece uma vez para cada pessoa ao entrar; opcional)</span>
                <textarea name="descricao" class="campo" rows="2" maxlength="500" placeholder="Ex.: Todos os recursos do Básico, para sempre, sem mensalidade.">{{ old('descricao') }}</textarea>
            </label>
            <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
                <button type="submit" class="btn">Criar promoção</button>
                <span class="texto-2 text-xs" x-show="modalidade === 'mensal'">O preço vale para o Pix gerado durante a promoção. A renovação depois do prazo volta ao preço normal.</span>
                <span class="texto-2 text-xs" x-show="modalidade === 'vitalicio'" x-cloak>Ao confirmar o Pix em /admin/pix, o plano fica liberado sem data de fim.</span>
            </div>
        </form>
    @endif

    @forelse ($lista as $p)
        @php
            $situacao = $p->situacao();
            $bag = 'editar' . $p->id;
            $temErro = $errors->getBag($bag)->any();
        @endphp
        <article class="card p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="tag">{{ $situacao }}</span>
                        @if ($p->vitalicio)<span class="tag" style="background:#7c3aed;color:#fff">Vitalício</span>@endif
                        @if ($p->precoCheio())<s class="texto-2 text-sm">{{ $reais($p->preco_cheio) }}</s>@endif
                        <b>{{ $reais($p->preco) }}{{ $p->vitalicio ? '' : '/mês' }}</b>
                        <span class="texto-2 text-sm">plano {{ \App\Support\Planos::nome($p->plano_slug) }}</span>
                        @if ($p->titulo)<span class="text-sm">· {{ $p->titulo }}</span>@endif
                    </div>
                    <p class="text-xs texto-2">
                        de {{ $hora($p->comeca_em) }} até {{ $hora($p->termina_em) }}
                        @if ($p->encerrada_em) · desativada em {{ $hora($p->encerrada_em) }} @endif
                        · aviso visto por {{ (int) ($vistas[$p->id] ?? 0) }} {{ (int) ($vistas[$p->id] ?? 0) === 1 ? 'pessoa' : 'pessoas' }}
                    </p>
                    @if ($p->descricao)<p class="text-sm texto-2">{{ $p->descricao }}</p>@endif
                </div>
                @if ($p->termina_em?->isFuture())
                    <form method="POST" action="{{ route('promocoes.admin.alternar', $p) }}"
                          onsubmit="return confirm(@js($p->encerrada_em ? 'Reativar esta promoção?' : 'Desativar esta promoção agora?'))">
                        @csrf
                        <button type="submit" class="btn-sec !py-1">{{ $p->encerrada_em ? 'Reativar' : 'Desativar' }}</button>
                    </form>
                @endif
            </div>
            <details @if ($temErro) open @endif>
                <summary class="text-sm cursor-pointer link-prim">Editar preço, prazo e textos</summary>
                <form method="POST" action="{{ route('promocoes.admin.editar', $p) }}" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">
                    @csrf
                    @if ($p->vitalicio)
                        <label class="text-sm space-y-1">
                            <span class="texto-2 text-xs">Preço cheio (R$)</span>
                            <input name="preco_cheio" class="campo" type="number" step="0.01" min="0.01" value="{{ $p->preco_cheio }}">
                        </label>
                    @endif
                    <label class="text-sm space-y-1">
                        <span class="texto-2 text-xs">{{ $p->vitalicio ? 'Preço promocional (R$)' : 'Preço do mês na promoção (R$)' }}</span>
                        <input name="preco" class="campo" type="number" step="0.01" min="0.01" required value="{{ $p->preco }}">
                        @error('preco', $bag)<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm space-y-1">
                        <span class="texto-2 text-xs">Começa</span>
                        <input name="comeca_em" type="datetime-local" class="campo" value="{{ $campoHora($p->comeca_em) }}">
                    </label>
                    <label class="text-sm space-y-1">
                        <span class="texto-2 text-xs">Termina (hora de Brasília)</span>
                        <input name="termina_em" type="datetime-local" class="campo" required value="{{ $campoHora($p->termina_em) }}">
                        @error('termina_em', $bag)<span class="text-xs block" role="alert" style="color:#ef4444">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm space-y-1">
                        <span class="texto-2 text-xs">Chamada</span>
                        <input name="titulo" class="campo" maxlength="80" value="{{ $p->titulo }}">
                    </label>
                    <label class="text-sm space-y-1 sm:col-span-2 lg:col-span-3">
                        <span class="texto-2 text-xs">Texto do aviso</span>
                        <textarea name="descricao" class="campo" rows="2" maxlength="500">{{ $p->descricao }}</textarea>
                    </label>
                    <div class="sm:col-span-2 lg:col-span-3"><button type="submit" class="btn">Salvar</button></div>
                </form>
            </details>
        </article>
    @empty
        <p class="texto-2">Nenhuma promoção criada ainda.</p>
    @endforelse
</div>
<style>
    .wf-promo-op { flex: 1 1 16rem; display: flex; flex-direction: column; gap: .2rem; padding: .75rem .9rem; border-radius: .9rem;
        border: 1px solid var(--linha); cursor: pointer; }
    .wf-promo-op.ativo { border-color: var(--prim); background: var(--prim-suave); }
</style>
@endsection
