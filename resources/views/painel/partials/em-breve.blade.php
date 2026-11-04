{{-- Painel › "Em breve": integrações que ainda não estão liberadas para todas as contas (App\Support\EmBreve).
     Cada item some sozinho quando fica pronto; sem itens, a seção não aparece. Nada de detalhes técnicos aqui. --}}
@php $emBreve = \App\Support\EmBreve::itens(); @endphp
@if ($emBreve)
<section class="card p-5 wf-eb" aria-labelledby="wf-eb-titulo">
    <div class="flex items-start gap-3">
        <span class="wf-eb-selo" aria-hidden="true">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3zM18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8L18 15z"/></svg>
        </span>
        <div class="min-w-0">
            <h2 id="wf-eb-titulo" class="titulo text-xl">Em breve no Work Flower</h2>
            <p class="text-sm texto-2 mt-1">Integrações a caminho. Cada uma aparece no lugar dela assim que ficar pronta.</p>
        </div>
    </div>

    <ul class="wf-eb-grade mt-4">
        @foreach ($emBreve as $i => $it)
            <li class="wf-eb-item" style="--c: {{ $it['cor'] }}; --i: {{ $i }}">
                <span class="wf-eb-icone" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $it['icone'] }}"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="wf-eb-nome">{{ $it['nome'] }}</span>
                        <span class="wf-eb-pilula">Em breve</span>
                    </div>
                    <p class="wf-eb-texto">{{ $it['texto'] }}</p>
                    <span class="wf-eb-onde">{{ $it['onde'] }}</span>
                </div>
            </li>
        @endforeach
    </ul>
</section>

<style>
    .wf-eb { position: relative; overflow: hidden; }
    .wf-eb::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 3px;
        background: linear-gradient(90deg, #1fa463, #ea4335, #e1306c, #25f4ee, #ff9900, #6366f1, #1fa463); background-size: 200% 100%;
        animation: wf-eb-faixa 9s linear infinite; }
    .wf-eb-selo { display: grid; place-items: center; flex: none; width: 2.4rem; height: 2.4rem; border-radius: .8rem; color: var(--prim);
        background: color-mix(in srgb, var(--prim) 16%, transparent); }
    .wf-eb-grade { display: grid; gap: .6rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 15rem), 1fr)); }
    .wf-eb-item { display: flex; gap: .7rem; padding: .75rem .8rem; border-radius: .8rem; background: var(--superficie-2); border: 1px solid var(--linha);
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        animation: wf-eb-entra .45s ease both; animation-delay: calc(var(--i) * 60ms); }
    .wf-eb-item:hover { transform: translateY(-2px); border-color: color-mix(in srgb, var(--c) 55%, var(--linha));
        box-shadow: 0 8px 22px -12px color-mix(in srgb, var(--c) 70%, transparent); }
    .wf-eb-icone { display: grid; place-items: center; flex: none; width: 2.2rem; height: 2.2rem; border-radius: .7rem; color: var(--c);
        background: color-mix(in srgb, var(--c) 16%, transparent); }
    .wf-eb-nome { font-weight: 600; font-size: .9rem; line-height: 1.25; overflow-wrap: anywhere; }
    .wf-eb-pilula { font-size: .62rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; padding: .12rem .45rem; border-radius: 9999px;
        color: var(--c); background: linear-gradient(110deg, color-mix(in srgb, var(--c) 14%, transparent) 40%, color-mix(in srgb, var(--c) 34%, transparent) 50%, color-mix(in srgb, var(--c) 14%, transparent) 60%);
        background-size: 250% 100%; animation: wf-eb-brilho 3.2s ease-in-out infinite; }
    .wf-eb-texto { font-size: .78rem; line-height: 1.4; color: var(--tinta-2); margin-top: .2rem; }
    .wf-eb-onde { display: inline-block; margin-top: .35rem; font-size: .66rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .4rem; padding: .05rem .35rem; }
    @keyframes wf-eb-faixa { to { background-position: -200% 0; } }
    @keyframes wf-eb-brilho { 0% { background-position: 100% 0; } 100% { background-position: -150% 0; } }
    @keyframes wf-eb-entra { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { .wf-eb::before, .wf-eb-pilula, .wf-eb-item { animation: none; } .wf-eb-item:hover { transform: none; } }
</style>
@endif
