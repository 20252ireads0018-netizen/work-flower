{{-- Painel da administração: contas criadas desde a última vez que o aviso foi marcado como visto (App\Support\NovosUsuarios). --}}
@if (! empty($novos) && $novos['total'] > 0)
<section class="card p-5 wf-nu" aria-labelledby="wf-nu-titulo" role="status">
    <div class="flex items-start gap-3">
        <span class="wf-nu-selo" aria-hidden="true">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19v-1a4 4 0 00-4-4H7a4 4 0 00-4 4v1M9 10a3 3 0 100-6 3 3 0 000 6zM19 8v6M16 11h6"/></svg>
            <span class="wf-nu-num">{{ $novos['total'] > 99 ? '99+' : $novos['total'] }}</span>
        </span>
        <div class="min-w-0 flex-1">
            <h2 id="wf-nu-titulo" class="titulo text-xl">
                {{ $novos['total'] === 1 ? '1 conta nova' : $novos['total'] . ' contas novas' }}
            </h2>
            <p class="text-sm texto-2 mt-1">Pessoas que entraram no Work Flower desde a última vez que você conferiu. Só a administração vê isto.</p>
        </div>
        <form method="POST" action="{{ route('admin.novos.visto') }}" class="shrink-0">
            @csrf
            <button type="submit" class="btn-sec !py-1 !px-3 text-sm">Marcar como visto</button>
        </form>
    </div>

    <ul class="wf-nu-lista mt-4">
        @foreach ($novos['lista'] as $u)
            @php $pago = $u->plano_slug && $u->plano_slug !== 'gratuito' && (! $u->plano_expira_em || \Illuminate\Support\Carbon::parse($u->plano_expira_em)->isFuture()); @endphp
            <li class="wf-nu-item">
                <span class="wf-nu-av" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($u->name) ?: $u->email, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <span class="wf-nu-nome" title="{{ $u->name }}">{{ $u->name }}</span>
                    <span class="wf-nu-email" title="{{ $u->email }}">{{ $u->email }}</span>
                </div>
                <div class="wf-nu-lado">
                    @if ($pago)<span class="wf-nu-plano">{{ ucfirst($u->plano_slug) }}</span>@endif
                    <time datetime="{{ $u->created_at?->toIso8601String() }}" title="{{ $u->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}">{{ $u->created_at?->diffForHumans() }}</time>
                </div>
            </li>
        @endforeach
    </ul>
    @if ($novos['total'] > $novos['lista']->count())
        <p class="text-xs texto-2 mt-2">E mais {{ $novos['total'] - $novos['lista']->count() }} além destas {{ $novos['lista']->count() }}.</p>
    @endif
</section>

<style>
    .wf-nu { border: 1px solid color-mix(in srgb, var(--prim) 45%, var(--linha)); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 10%, transparent); }
    .wf-nu-selo { position: relative; display: grid; place-items: center; flex: none; width: 2.4rem; height: 2.4rem; border-radius: .8rem; color: var(--prim);
        background: color-mix(in srgb, var(--prim) 16%, transparent); }
    .wf-nu-num { position: absolute; top: -.4rem; right: -.5rem; min-width: 1.25rem; height: 1.25rem; padding: 0 .3rem; border-radius: 9999px; display: grid; place-items: center;
        font-size: .65rem; font-weight: 700; background: var(--prim); color: var(--on-prim, #fff); box-shadow: 0 0 0 2px var(--superficie, #111);
        animation: wf-nu-pulso 2.4s ease-in-out infinite; }
    .wf-nu-lista { display: flex; flex-direction: column; gap: .35rem; max-height: 22rem; overflow-y: auto; padding-right: .2rem; }
    .wf-nu-item { display: flex; align-items: center; gap: .65rem; padding: .5rem .65rem; border-radius: .7rem; background: var(--superficie-2); border: 1px solid var(--linha); }
    .wf-nu-av { display: grid; place-items: center; flex: none; width: 2rem; height: 2rem; border-radius: 9999px; font-weight: 700; font-size: .85rem;
        color: var(--prim); background: color-mix(in srgb, var(--prim) 18%, transparent); }
    .wf-nu-nome { display: block; font-weight: 600; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wf-nu-email { display: block; font-size: .72rem; color: var(--tinta-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .wf-nu-lado { display: flex; flex-direction: column; align-items: flex-end; gap: .15rem; flex: none; font-size: .7rem; color: var(--tinta-2); }
    .wf-nu-plano { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .05rem .4rem; border-radius: 9999px; color: #16a34a; background: rgba(22, 163, 74, .15); }
    @keyframes wf-nu-pulso { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.12); } }
    @media (prefers-reduced-motion: reduce) { .wf-nu-num { animation: none; } }
</style>
@endif
