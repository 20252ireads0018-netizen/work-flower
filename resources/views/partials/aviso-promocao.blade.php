{{-- Aviso de promoção nova: aparece uma vez para cada pessoa (Promocao::avisoPara já marcou como vista).
     Recebe $promo (App\Models\Promocao). A vitalícia leva direto ao Pix; a mensal leva à tela de planos. --}}
@php
    $vit = (bool) $promo->vitalicio;
    $cheio = $promo->precoCheio() ?? ($vit ? null : (float) (\Illuminate\Support\Facades\DB::table('plans')->where('slug', $promo->plano_slug)->value('price') ?? 0));
    $reais = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $acao = $vit ? route('assinar.vitalicio', $promo) : route('planos');
@endphp
<div x-data="{ aberto: true }" x-show="aberto" x-cloak class="wf-promo-fundo" role="dialog" aria-modal="true" aria-labelledby="wf-promo-titulo"
     @keydown.escape.window="aberto = false" @click.self="aberto = false">
    <div class="wf-promo-caixa {{ $vit ? 'vit' : '' }}">
        <button type="button" class="wf-promo-x" @click="aberto = false" aria-label="Fechar">&times;</button>
        <span class="wf-promo-selo">{{ $vit ? 'Oferta vitalícia' : 'Promoção' }}</span>
        <h2 id="wf-promo-titulo" class="titulo">{{ $promo->titulo ?: ($vit ? 'Pague uma vez, receba para sempre' : 'Promoção relâmpago') }}</h2>
        <p class="wf-promo-plano">Plano {{ \App\Support\Planos::nome($promo->plano_slug) }}{{ $vit ? ' para sempre, sem mensalidade' : '' }}</p>
        <p class="wf-promo-preco">
            @if ($cheio && $cheio > (float) $promo->preco)<s>{{ $reais($cheio) }}</s>@endif
            <b>{{ $reais($promo->preco) }}</b><span>{{ $vit ? ' uma vez' : '/mês' }}</span>
        </p>
        @if ($promo->descricao)<p class="wf-promo-texto">{{ $promo->descricao }}</p>@endif
        <p class="wf-promo-fim" data-promo-aviso-fim="{{ $promo->termina_em->toIso8601String() }}">
            Termina em <span data-promo-aviso-falta>{{ $promo->termina_em->diffForHumans(null, true) }}</span>
        </p>
        <div class="wf-promo-botoes">
            <a href="{{ $acao }}" class="btn">{{ $vit ? 'Quero para sempre' : 'Ver planos' }}</a>
            <button type="button" class="btn-sec" @click="aberto = false">Agora não</button>
        </div>
    </div>
</div>
<style>
    .wf-promo-fundo { position: fixed; inset: 0; z-index: 80; display: grid; place-items: center; padding: 1rem;
        background: rgba(10, 8, 20, .55); backdrop-filter: blur(4px); }
    .wf-promo-caixa { position: relative; width: min(26rem, 100%); border-radius: 1.4rem; padding: 1.6rem 1.5rem 1.4rem; text-align: center;
        background: var(--papel); color: var(--tinta); border: 1px solid var(--linha); box-shadow: 0 24px 60px rgba(0,0,0,.35); }
    .wf-promo-caixa.vit { background: linear-gradient(160deg, #2e1065 0%, #6d28d9 55%, #db2777 100%); color: #fff; border-color: transparent; }
    .wf-promo-x { position: absolute; top: .6rem; right: .8rem; font-size: 1.5rem; line-height: 1; opacity: .7; background: none; border: 0; color: inherit; cursor: pointer; }
    .wf-promo-selo { display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        padding: .25rem .7rem; border-radius: 9999px; background: #dc2626; color: #fff; }
    .wf-promo-caixa.vit .wf-promo-selo { background: rgba(255,255,255,.18); }
    .wf-promo-caixa h2 { font-size: 1.5rem; margin-top: .8rem; line-height: 1.2; }
    .wf-promo-plano { font-size: .9rem; opacity: .85; margin-top: .3rem; }
    .wf-promo-preco { margin-top: 1rem; display: flex; align-items: baseline; justify-content: center; gap: .5rem; flex-wrap: wrap; }
    .wf-promo-preco s { opacity: .6; }
    .wf-promo-preco b { font-size: 2.2rem; line-height: 1; }
    .wf-promo-preco span { font-size: .85rem; opacity: .8; }
    .wf-promo-texto { margin-top: .8rem; font-size: .9rem; opacity: .9; }
    .wf-promo-fim { margin-top: .8rem; font-size: .8rem; font-weight: 600; color: #dc2626; }
    .wf-promo-caixa.vit .wf-promo-fim { color: #fde68a; }
    .wf-promo-botoes { margin-top: 1.2rem; display: flex; gap: .5rem; justify-content: center; flex-wrap: wrap; }
    .wf-promo-caixa.vit .btn { background: #fff; color: #4c1d95; }
    .wf-promo-caixa.vit .btn-sec { background: transparent; color: #fff; border-color: rgba(255,255,255,.4); }
</style>
<script>
    (function () {
        const el = document.querySelector('[data-promo-aviso-fim]');
        if (!el) return;
        const fim = new Date(el.dataset.promoAvisoFim), alvo = el.querySelector('[data-promo-aviso-falta]');
        const dois = n => String(n).padStart(2, '0');
        (function tique() {
            const s = Math.max(0, Math.floor((fim - Date.now()) / 1000));
            const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
            alvo.textContent = (d ? d + 'd ' : '') + dois(h) + ':' + dois(m) + ':' + dois(s % 60);
            if (s) setTimeout(tique, 1000);
        })();
    })();
</script>
