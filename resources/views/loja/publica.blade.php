@extends('loja.base')

{{-- Vitrine pública da mini loja: produtos, carrinho (guardado no navegador) e formulário do pedido.
     O servidor (LojaPublicaController::pedir) confere preço e estoque de novo antes de criar o pedido. --}}
@php
    $brl = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
    $dados = $produtos->map(fn ($p) => [
        'id' => $p->id, 'nome' => $p->nome, 'preco' => (float) $p->preco,
        'max' => $p->estoque === null ? 99 : min(99, $p->estoque),
    ])->values();
@endphp

@push('estilo')
<style>
    .lj-vitrine { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .lj-vitrine.lista { grid-template-columns: 1fr; }
    .lj-prod { display: flex; flex-direction: column; }
    .lj-vitrine.lista .lj-prod { flex-direction: row; }
    .lj-prod-img { aspect-ratio: 1 / 1; width: 100%; object-fit: cover; background: color-mix(in srgb, var(--texto) 6%, var(--cartao)); }
    .lj-vitrine.lista .lj-prod-img { width: 130px; flex-shrink: 0; }
    .lj-prod-corpo { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .lj-prod-nome { font-weight: 700; margin: 0; }
    .lj-prod-desc { font-size: .88rem; opacity: .78; margin: 0; white-space: pre-line; }
    .lj-esgotado { font-size: .8rem; opacity: .7; }
    .lj-carrinho-btn { position: relative; }
    .lj-qtd[hidden] { display: none; }
    .lj-qtd { position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; border-radius: 10px; background: #e11d48; color: #fff; font-size: .72rem; display: grid; place-items: center; padding: 0 5px; }
    .lj-painel { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: none; z-index: 50; }
    .lj-painel.aberto { display: block; }
    .lj-gaveta { position: absolute; right: 0; top: 0; bottom: 0; width: min(420px, 100%); background: var(--fundo); padding: 18px 16px; overflow-y: auto; }
    .lj-linha { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid color-mix(in srgb, var(--texto) 12%, transparent); }
    .lj-mini { width: 30px; height: 30px; border-radius: 8px; border: 1px solid color-mix(in srgb, var(--texto) 25%, transparent); background: var(--cartao); color: var(--texto); font: inherit; cursor: pointer; }
</style>
@endpush

@section('topo')
    <button type="button" class="lj-btn lj-carrinho-btn" id="lj-abrir" aria-label="Abrir o carrinho">
        🛒 <span>Carrinho</span><span class="lj-qtd" id="lj-qtd" hidden>0</span>
    </button>
@endsection

@section('conteudo')
    @if (session('erro')) <div class="lj-aviso" role="alert">{{ session('erro') }}</div> @endif
    @if ($errors->any()) <div class="lj-aviso" role="alert">{{ $errors->first() }}</div> @endif
    @if ($v['banner']) <img src="{{ route('loja.img', [$loja->slug, $v['banner']]) }}" alt="" class="lj-banner"> @endif

    @if ($produtos->isEmpty())
        <p>Nenhum produto à venda no momento.</p>
    @else
        <div class="lj-vitrine {{ $v['layout'] === 'lista' ? 'lista' : '' }}">
            @foreach ($produtos as $p)
                <article class="lj-cartao lj-prod">
                    @if ($p->imagem)
                        <img src="{{ route('loja.img', [$loja->slug, $p->imagem]) }}" alt="{{ $p->nome }}" class="lj-prod-img" loading="lazy">
                    @else
                        <div class="lj-prod-img" aria-hidden="true"></div>
                    @endif
                    <div class="lj-prod-corpo">
                        <h2 class="lj-prod-nome">{{ $p->nome }}</h2>
                        @if ($p->descricao) <p class="lj-prod-desc">{{ \Illuminate\Support\Str::limit($p->descricao, 220) }}</p> @endif
                        <div style="margin-top:auto">
                            <span class="lj-preco">{{ $brl($p->preco) }}</span>
                            @if ($p->preco_antigo) <span class="lj-antigo">{{ $brl($p->preco_antigo) }}</span> @endif
                        </div>
                        @if ($p->esgotado())
                            <span class="lj-esgotado">Esgotado</span>
                        @else
                            @if ($v['estoque'] && $p->estoque !== null) <span class="lj-esgotado">{{ $p->estoque }} {{ $p->estoque === 1 ? 'disponível' : 'disponíveis' }}</span> @endif
                            <button type="button" class="lj-btn" data-add="{{ $p->id }}">{{ $v['botao'] }}</button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- carrinho + dados do pedido --}}
    <div class="lj-painel" id="lj-painel" aria-hidden="true">
        <div class="lj-gaveta" role="dialog" aria-modal="true" aria-labelledby="lj-tit-carrinho">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <h2 id="lj-tit-carrinho" style="margin:0">Seu carrinho</h2>
                <button type="button" class="lj-mini" id="lj-fechar" aria-label="Fechar">✕</button>
            </div>
            <div id="lj-itens"></div>
            <p style="display:flex;justify-content:space-between;font-weight:800;font-size:1.1rem"><span>Total</span><span id="lj-total">R$ 0,00</span></p>

            @if (! $loja->pixPronto())
                <p class="lj-aviso">Esta loja ainda não está recebendo pedidos.</p>
            @else
                <form method="POST" action="{{ route('loja.pedir', $loja->slug) }}" id="lj-form">
                    @csrf
                    <input type="hidden" name="itens" id="lj-itens-campo">
                    <label class="lj-rotulo" for="lj-nome">Seu nome</label>
                    <input class="lj-campo" id="lj-nome" name="nome" maxlength="120" required value="{{ old('nome') }}" autocomplete="name">
                    <label class="lj-rotulo" for="lj-contato">WhatsApp ou e-mail</label>
                    <input class="lj-campo" id="lj-contato" name="contato" maxlength="120" required value="{{ old('contato') }}" autocomplete="tel">
                    <label class="lj-rotulo" for="lj-end">Endereço de entrega (se for entregar)</label>
                    <textarea class="lj-campo" id="lj-end" name="endereco" rows="2" maxlength="500" autocomplete="street-address">{{ old('endereco') }}</textarea>
                    <label class="lj-rotulo" for="lj-obs">Observação</label>
                    <input class="lj-campo" id="lj-obs" name="obs" maxlength="500" value="{{ old('obs') }}">
                    @if ($v['entrega']) <p style="font-size:.85rem;opacity:.8">{{ $v['entrega'] }}</p> @endif
                    <button type="submit" class="lj-btn" style="width:100%;margin-top:14px" id="lj-finalizar" @disabled($previa)>
                        {{ $previa ? 'Abra a loja ao público para receber pedidos' : 'Fazer pedido e pagar com Pix' }}
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script type="application/json" id="lj-produtos">{!! json_encode($dados, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
<script>
(function () {
    const PRODUTOS = new Map(JSON.parse(document.getElementById('lj-produtos').textContent).map(p => [p.id, p]));
    const CHAVE = 'wf-loja:' + @json($loja->slug);
    const brl = n => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    let carrinho = {};
    try { carrinho = JSON.parse(localStorage.getItem(CHAVE) || '{}') || {}; } catch (e) { carrinho = {}; }
    // tira o que não está mais à venda e ajusta ao estoque de agora
    Object.keys(carrinho).forEach(id => { const p = PRODUTOS.get(+id); if (!p || p.max < 1) delete carrinho[id]; else carrinho[id] = Math.min(p.max, Math.max(1, +carrinho[id] || 1)); });

    const $ = id => document.getElementById(id);
    const painel = $('lj-painel');
    function salvar() { try { localStorage.setItem(CHAVE, JSON.stringify(carrinho)); } catch (e) {} desenhar(); }
    function desenhar() {
        const ids = Object.keys(carrinho), qtd = ids.reduce((s, id) => s + carrinho[id], 0);
        $('lj-qtd').hidden = !qtd;
        $('lj-qtd').textContent = qtd;
        const lista = $('lj-itens');
        lista.replaceChildren();
        let total = 0;
        if (!ids.length) {
            const p = document.createElement('p');
            p.textContent = 'O carrinho está vazio.';
            lista.append(p);
        }
        ids.forEach(id => {
            const p = PRODUTOS.get(+id), n = carrinho[id];
            total += p.preco * n;
            const linha = document.createElement('div');
            linha.className = 'lj-linha';
            const nome = document.createElement('span');
            nome.style.flex = '1';
            nome.textContent = p.nome;
            const menos = document.createElement('button'), mais = document.createElement('button'), q = document.createElement('span'), val = document.createElement('b');
            menos.type = mais.type = 'button';
            menos.className = mais.className = 'lj-mini';
            menos.textContent = '−'; mais.textContent = '+';
            menos.setAttribute('aria-label', 'Tirar um');
            mais.setAttribute('aria-label', 'Pôr mais um');
            mais.disabled = n >= p.max;
            q.textContent = n;
            val.textContent = brl(p.preco * n);
            menos.onclick = () => { if (carrinho[id] > 1) carrinho[id]--; else delete carrinho[id]; salvar(); };
            mais.onclick = () => { carrinho[id] = Math.min(p.max, carrinho[id] + 1); salvar(); };
            linha.append(nome, menos, q, mais, val);
            lista.append(linha);
        });
        $('lj-total').textContent = brl(total);
        const f = $('lj-finalizar');
        if (f && !f.hasAttribute('data-previa')) f.disabled = !ids.length || {{ $previa ? 'true' : 'false' }};
        const campo = $('lj-itens-campo');
        if (campo) campo.value = JSON.stringify(ids.map(id => ({ id: +id, qtd: carrinho[id] })));
    }
    const abrir = () => { painel.classList.add('aberto'); painel.setAttribute('aria-hidden', 'false'); };
    const fechar = () => { painel.classList.remove('aberto'); painel.setAttribute('aria-hidden', 'true'); };
    $('lj-abrir').onclick = abrir;
    $('lj-fechar').onclick = fechar;
    painel.addEventListener('click', e => { if (e.target === painel) fechar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') fechar(); });
    document.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
        const id = +b.dataset.add, p = PRODUTOS.get(id);
        if (!p) return;
        carrinho[id] = Math.min(p.max, (carrinho[id] || 0) + 1);
        salvar();
        abrir();
    }));
    // o carrinho só é esvaziado na página do pedido feito (se o pedido falhar, ele continua aqui)
    desenhar();
    @if ($errors->any()) abrir(); @endif
})();
</script>
@endpush
