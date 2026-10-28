@extends('layouts.app')

@section('titulo', 'Planos')
@section('subtitulo', 'Escolha o plano que acompanha o seu ritmo. Você pode trocar quando quiser.')

@section('conteudo')
@php
    use App\Support\Planos;

    /*
     * Os planos podem vir do controller (variável $planos).
     * Se a rota só carregar a view, buscamos aqui mesmo, com a mesma consulta da API.
     */
    if (! isset($planos)) {
        try {
            $planos = class_exists(\App\Models\Plan::class)
                ? \App\Models\Plan::where('active', 1)->orderBy('sort')->get()
                : \Illuminate\Support\Facades\DB::table('plans')->where('active', 1)->orderBy('sort')->get();
        } catch (\Throwable $e) {
            report($e);
            $planos = collect();
        }
    }

    $planos    = collect($planos);
    $planoAtual = auth()->check() ? Planos::slugDe(auth()->user()) : null;
    $emBreve   = ['pro', 'premium'];                 // Coming soon
    $destaque  = $planos->contains('slug', 'basico') ? 'basico' : null;

    // classes escritas por extenso para o Tailwind enxergar
    $colunas = match (true) {
        $planos->count() >= 4 => 'md:grid-cols-2 xl:grid-cols-4',
        $planos->count() === 3 => 'md:grid-cols-3',
        $planos->count() === 2 => 'md:grid-cols-2',
        default => 'md:grid-cols-1',
    };

    $urlAssinar = \Illuminate\Support\Facades\Route::has('registro')
        ? route('registro')
        : (\Illuminate\Support\Facades\Route::has('login') ? route('login') : url('/'));

    /* Lista de recursos gerada a partir dos limites reais (App\Support\Planos). */
    $listaRecursos = function ($plano) use ($emBreve) {
        if (in_array($plano->slug, $emBreve, true)) {
            return ['Novos recursos a caminho', 'Avisaremos quando estiver disponível'];
        }

        $l = Planos::limites($plano->slug);

        return [
            $l['biblioteca'] === null
                ? 'Biblioteca de blocos ilimitada'
                : "Até {$l['biblioteca']} blocos salvos na biblioteca",
            $l['workflows'] === null
                ? 'Exporte e importe quantos workflows quiser'
                : "Exporte ou importe até {$l['workflows']} workflows",
            $l['elementos'] === null
                ? 'Baixe quantos elementos quiser (textos, tabelas etc.)'
                : "Baixe até {$l['elementos']} elementos (textos, tabelas etc.)",
            $l['usuarios'] === 1
                ? 'Workflows só seus (1 pessoa)'
                : "Até {$l['usuarios']} pessoas no mesmo workflow",
            $l['marketplaces'] === null
                ? 'Contas de marketplace ilimitadas (Mercado Livre, Shopee, Amazon)'
                : "Até {$l['marketplaces']} " . ($l['marketplaces'] === 1 ? 'conta' : 'contas') . ' de marketplace (Mercado Livre, Shopee, Amazon)',
        ];
    };
@endphp

<div class="{{ auth()->check() ? '' : 'max-w-6xl mx-auto px-4 sm:px-8 py-12' }}">

    @guest
        <div class="mb-10">
            <h1 class="titulo text-3xl sm:text-4xl">Planos</h1>
            <p class="texto-2 text-sm mt-1.5">Escolha o plano que acompanha o seu ritmo. Você pode trocar quando quiser.</p>
        </div>
    @endguest

    @if (session('erro'))
        <div class="card p-4 mb-6 text-sm" role="alert">{{ session('erro') }}</div>
    @endif

    @if ($planos->isEmpty())
        <div class="card p-8 text-center">
            <p class="titulo text-xl">Nenhum plano disponível no momento</p>
            <p class="texto-2 text-sm mt-2">Volte em breve ou entre em contato com o suporte.</p>
        </div>
    @else
        <div class="grid gap-5 {{ $colunas }} items-stretch">
            @foreach ($planos as $plano)
                @php
                    $breve      = in_array($plano->slug, $emBreve, true);
                    $ehAtual    = $planoAtual === $plano->slug;
                    $ehDestaque = $destaque && $plano->slug === $destaque;
                    $gratis     = (float) $plano->price <= 0;
                    $preco      = number_format((float) $plano->price, 2, ',', '.');
                    [$reais, $centavos] = explode(',', $preco);
                    $recursos   = $listaRecursos($plano);
                @endphp

                <article data-plano-card="{{ $plano->slug }}"
                         class="card p-6 flex flex-col relative {{ $breve ? 'opacity-80' : '' }}"
                         @if ($ehDestaque) style="border-color: var(--prim); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 22%, transparent);" @endif>

                    @if ($breve)
                        <span class="absolute -top-3 left-6 text-xs font-semibold px-3 py-1 rounded-full"
                              style="background: var(--borda, #d4d4d8); color: inherit;">Em breve</span>
                    @elseif ($ehAtual)
                        <span class="absolute -top-3 left-6 text-xs font-semibold px-3 py-1 rounded-full"
                              style="background: var(--prim); color: var(--on-prim);">Seu plano</span>
                    @elseif ($ehDestaque)
                        <span class="absolute -top-3 left-6 text-xs font-semibold px-3 py-1 rounded-full"
                              style="background: var(--prim); color: var(--on-prim);">Mais escolhido</span>
                    @endif

                    <h2 class="titulo text-2xl">{{ $plano->name }}</h2>

                    @if (! empty($plano->description))
                        <p class="texto-2 text-sm mt-1">{{ $plano->description }}</p>
                    @endif

                    @if ($breve)
                        <p class="mt-5 titulo text-2xl">Em breve</p>
                    @elseif ($gratis)
                        <p class="mt-5 titulo text-4xl">Grátis</p>
                    @else
                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="texto-2 text-sm">R$</span>
                            <span class="titulo text-4xl">{{ $reais }}</span>
                            <span class="titulo text-lg">,{{ $centavos }}</span>
                            <span class="texto-2 text-sm">/mês</span>
                        </p>
                    @endif

                    @if (! $breve && (int) $plano->trial_days > 0)
                        <p class="text-xs mt-1.5" style="color: var(--prim-forte);">{{ (int) $plano->trial_days }} dias grátis para testar</p>
                    @endif

                    <ul class="mt-6 space-y-2.5 text-sm flex-1">
                        @foreach ($recursos as $recurso)
                            <li class="flex items-start gap-2.5">
                                <span class="icone !w-5 !h-5 mt-0.5" aria-hidden="true">
                                    <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                </span>
                                <span>{{ $recurso }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($breve)
                        <span data-plano-assinar aria-disabled="true"
                              class="btn-sec mt-7 w-full text-center pointer-events-none opacity-60">Em breve</span>
                    @elseif ($ehAtual)
                        <span data-plano-assinar aria-disabled="true"
                              class="btn-sec mt-7 w-full text-center pointer-events-none opacity-60">Plano atual</span>
                    @else
                        <a data-plano-assinar
                           href="{{ $gratis ? $urlAssinar . '?plano=' . $plano->slug : route('assinar', $plano->slug) }}"
                           class="{{ $ehDestaque ? 'btn' : 'btn-sec' }} mt-7 w-full"
                           aria-label="{{ $gratis ? 'Começar no plano' : 'Assinar o plano' }} {{ $plano->name }}">
                            {{ $gratis ? 'Começar grátis' : 'Assinar ' . $plano->name }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="texto-2 text-xs mt-6 text-center">Valores em reais, cobrados mensalmente. Os planos Pró e Premium chegam em breve.</p>
    @endif
</div>
@endsection
