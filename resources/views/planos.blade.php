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
    // "Plano atual" é o que a pessoa paga (o teste grátis não conta, senão ninguém em teste assinaria);
    // perto do fim do mês pago o botão vira "Renovar".
    $planoAtual = auth()->check() ? Planos::slugPago(auth()->user()) : null;
    $vitalicioMeu = auth()->check() && ! empty(auth()->user()->plano_vitalicio); // comprou o "Pague uma vez, receba para sempre"
    $renovavel  = auth()->check() && ! $vitalicioMeu && \App\Support\Pix::podeRenovar(auth()->user());
    $pixAberto  = null;
    if (auth()->check()) {
        try {
            $pixAberto = \App\Models\PixPagamento::where('user_id', auth()->id())->whereIn('status', ['aguardando', 'informado', 'recusado'])->latest('id')->first();
        } catch (\Throwable $e) { report($e); }
    }
    $emBreve   = ['pro', 'premium'];                 // Coming soon
    $promos    = \App\Models\Promocao::vigentes();   // promoções relâmpago valendo agora (/admin/promocoes)
    $ofertaVit = $vitalicioMeu ? null : \App\Models\Promocao::vitalicioVigente(); // "Pague uma vez, receba para sempre"
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
            ($l['loja'] ?? null) === null
                ? 'Loja virtual com produtos ilimitados e sem a marca Work Flower'
                : "Loja virtual com até {$l['loja']} produtos",
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

    @if ($pixAberto)
        <a href="{{ route('pix.ver', $pixAberto) }}" class="card p-4 mb-6 text-sm flex flex-wrap items-center gap-2" role="status">
            <b>Pix do plano {{ Planos::nome($pixAberto->plano_slug) }} · R$ {{ number_format((float) $pixAberto->valor, 2, ',', '.') }}</b>
            <span class="texto-2">{{ ['aguardando' => 'esperando o pagamento', 'informado' => 'pagamento em conferência', 'recusado' => 'não encontramos o pagamento'][$pixAberto->status] }}</span>
            <span class="ml-auto underline">Abrir</span>
        </a>
    @endif

    @if ($ofertaVit)
        {{-- "Pague uma vez, receba para sempre" (Promocao vitalícia, /admin/promocoes) --}}
        <section class="wf-vit mb-8" aria-label="Oferta vitalícia">
            <div class="min-w-0 flex-1">
                <span class="wf-vit-selo">Oferta vitalícia</span>
                <h2 class="titulo text-2xl sm:text-3xl mt-2">{{ $ofertaVit->titulo ?: 'Pague uma vez, receba para sempre' }}</h2>
                <p class="text-sm mt-1" style="opacity:.88">
                    {{ $ofertaVit->descricao ?: 'Plano ' . Planos::nome($ofertaVit->plano_slug) . ' para sempre: um Pix só, sem mensalidade.' }}
                </p>
                <p class="text-xs mt-2 font-semibold" style="color:#fde68a" data-promo-fim="{{ $ofertaVit->termina_em->toIso8601String() }}">
                    Termina em <span data-promo-falta>{{ $ofertaVit->termina_em->diffForHumans(null, true) }}</span>
                </p>
            </div>
            <div class="text-center shrink-0">
                @if ($ofertaVit->precoCheio())
                    <p class="text-sm" style="opacity:.7;text-decoration:line-through">R$ {{ number_format((float) $ofertaVit->preco_cheio, 2, ',', '.') }}</p>
                @endif
                <p class="titulo text-4xl">R$ {{ number_format((float) $ofertaVit->preco, 2, ',', '.') }}</p>
                <p class="text-xs" style="opacity:.8">pagamento único · Plano {{ Planos::nome($ofertaVit->plano_slug) }}</p>
                @auth
                    <a href="{{ route('assinar.vitalicio', $ofertaVit) }}" class="wf-vit-btn mt-3">Quero para sempre</a>
                @else
                    <a href="{{ $urlAssinar }}" class="wf-vit-btn mt-3">Criar conta e garantir</a>
                @endauth
            </div>
        </section>
        <style>
            .wf-vit { display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; padding: 1.5rem 1.6rem; border-radius: 1.4rem; color: #fff;
                background: linear-gradient(135deg, #2e1065 0%, #6d28d9 55%, #db2777 100%); box-shadow: 0 18px 40px rgba(109, 40, 217, .28); }
            .wf-vit-selo { display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
                padding: .25rem .7rem; border-radius: 9999px; background: rgba(255,255,255,.18); }
            .wf-vit-btn { display: inline-block; padding: .65rem 1.3rem; border-radius: 9999px; background: #fff; color: #4c1d95; font-weight: 700; }
            .wf-vit-btn:hover { background: #f5f3ff; }
        </style>
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
                    $ehAtual    = $planoAtual === $plano->slug && ! ($renovavel && (float) $plano->price > 0);
                    $renovar    = $planoAtual === $plano->slug && ! $ehAtual;
                    $ordemPl    = array_flip(Planos::slugs());
                    $incluido   = $planoAtual && ($ordemPl[$planoAtual] ?? 0) > ($ordemPl[$plano->slug] ?? 0);
                    $ehDestaque = $destaque && $plano->slug === $destaque;
                    $gratis     = (float) $plano->price <= 0;
                    $promo      = ! $breve && ! $gratis && isset($promos[$plano->slug]) && (float) $promos[$plano->slug]->preco < (float) $plano->price
                        ? $promos[$plano->slug] : null;
                    $preco      = number_format((float) ($promo->preco ?? $plano->price), 2, ',', '.');
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
                              style="background: var(--prim); color: var(--on-prim);">Seu plano{{ $vitalicioMeu ? " · vitalício" : "" }}</span>
                    @elseif ($promo)
                        <span class="absolute -top-3 left-6 text-xs font-semibold px-3 py-1 rounded-full"
                              style="background: #dc2626; color: #fff;">{{ $promo->titulo ?: 'Promoção relâmpago' }}</span>
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
                        @if ($promo)
                            <p class="mt-5 texto-2 text-sm" style="text-decoration: line-through;">R$ {{ number_format((float) $plano->price, 2, ',', '.') }}/mês</p>
                        @endif
                        <p class="{{ $promo ? 'mt-1' : 'mt-5' }} flex items-baseline gap-1">
                            <span class="texto-2 text-sm">R$</span>
                            <span class="titulo text-4xl">{{ $reais }}</span>
                            <span class="titulo text-lg">,{{ $centavos }}</span>
                            <span class="texto-2 text-sm">/mês</span>
                        </p>
                        @if ($promo)
                            <p class="text-xs mt-1.5 font-semibold" style="color: #dc2626;" data-promo-fim="{{ $promo->termina_em->toIso8601String() }}">
                                Termina em <span data-promo-falta>{{ $promo->termina_em->diffForHumans(null, true) }}</span>
                            </p>
                        @endif
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
                    @elseif ($incluido)
                        <span data-plano-assinar aria-disabled="true"
                              class="btn-sec mt-7 w-full text-center pointer-events-none opacity-60">Incluído no seu plano</span>
                    @else
                        <a data-plano-assinar
                           href="{{ $gratis ? $urlAssinar . '?plano=' . $plano->slug : route('assinar', $plano->slug) }}"
                           class="{{ $ehDestaque ? 'btn' : 'btn-sec' }} mt-7 w-full"
                           aria-label="{{ $gratis ? 'Começar no plano' : 'Assinar o plano' }} {{ $plano->name }}">
                            {{ $gratis ? 'Começar grátis' : ($renovar ? 'Renovar com Pix' : 'Assinar com Pix') }}
                        </a>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="texto-2 text-xs mt-6 text-center">Valores em reais, por mês, pagos por Pix. Os planos Pró e Premium chegam em breve.</p>
        @if ($promos->isNotEmpty() || $ofertaVit)
            <p class="texto-2 text-xs mt-1 text-center">O preço da promoção vale para o Pix gerado até o fim dela; a renovação depois volta ao preço normal.</p>
            <script>
                // contagem regressiva das promoções; quando uma termina, a página recarrega com o preço normal
                (function () {
                    const els = document.querySelectorAll('[data-promo-fim]');
                    const dois = n => String(n).padStart(2, '0');
                    function tique() {
                        let acabou = false;
                        els.forEach(el => {
                            const s = Math.max(0, Math.floor((new Date(el.dataset.promoFim) - Date.now()) / 1000));
                            if (!s) acabou = true;
                            const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
                            el.querySelector('[data-promo-falta]').textContent = (d ? d + 'd ' : '') + dois(h) + ':' + dois(m) + ':' + dois(s % 60);
                        });
                        if (acabou) { setTimeout(() => location.reload(), 1500); return; }
                        setTimeout(tique, 1000);
                    }
                    tique();
                })();
            </script>
        @endif
    @endif
</div>
@endsection
