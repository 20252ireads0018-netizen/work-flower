{{-- Abas só da área Negócios, incluídas em areas/carteira/index (modo "negocios"):
     Clientes (no estado da área), Loja e Marketplaces (atalhos com resumo) e Equipe (partials/equipe, js/equipe.js). --}}

{{-- ================= CLIENTES ================= --}}
<section class="wf-quadro" x-show="aba === 'clientes'" x-cloak :style="{ minHeight: alturaQuadro('clientes') + 'px' }">
    <x-carteira.item sec="'clientes'" bid="'cli-lista'" titulo="Clientes" :minw="420" :minh="300">
        <x-slot name="acoes">
            <button type="button" class="btn-sec !py-1" @click="importarClientesLoja()" title="Traz quem comprou na sua loja virtual">Trazer da loja</button>
        </x-slot>
        <div class="space-y-3">
            <div class="grid gap-2 md:grid-cols-[1fr_1fr_1fr_auto]">
                <input class="campo" x-model="cl.nome" placeholder="Nome" @keydown.enter.prevent="addCliente()" aria-label="Nome do cliente">
                <input class="campo" x-model="cl.contato" placeholder="WhatsApp ou e-mail" aria-label="Contato">
                <input class="campo" x-model="cl.tags" placeholder="Etiquetas (ex.: atacado, VIP)" aria-label="Etiquetas">
                <button type="button" class="btn" @click="addCliente()">Adicionar</button>
            </div>
            <p class="erro" x-text="clErro"></p>
            <input class="campo" type="search" x-model="clBusca" placeholder="Buscar por nome, contato ou etiqueta" aria-label="Buscar clientes">
            <p x-show="!estado.clientes.length" class="text-sm texto-2">Nenhum cliente ainda. Adicione acima ou traga quem já comprou na sua loja.</p>
            <div class="overflow-x-auto" x-show="estado.clientes.length">
                <table class="wf-tabela">
                    <thead><tr><th>Nome</th><th>Contato</th><th>Etiquetas</th><th>Compras</th><th>Notas</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="c in clientesFiltrados()" :key="c.id">
                            <tr>
                                <td><input class="wf-cel font-medium" x-model="c.nome" aria-label="Nome"></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <input class="wf-cel" x-model="c.contato" aria-label="Contato">
                                        <a class="link-prim text-xs shrink-0" x-show="linkContato(c)" :href="linkContato(c)" target="_blank" rel="noopener" x-text="c.contato.includes('@') ? 'e-mail' : 'WhatsApp'"></a>
                                    </div>
                                </td>
                                <td><input class="wf-cel" x-model="c.tags" aria-label="Etiquetas"></td>
                                <td class="text-xs texto-2 whitespace-nowrap" x-text="(c.pedidos || 0) + ' · ' + brl(c.total || 0)"></td>
                                <td><input class="wf-cel" x-model="c.nota" placeholder="…" aria-label="Notas"></td>
                                <td><button type="button" class="mini-btn perigo" title="Remover" @click="remCliente(c)">✕</button></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </x-carteira.item>
    @include('areas.carteira.partials.blocos', ['sec' => 'clientes'])
</section>

{{-- ================= LOJA E MARKETPLACES ================= --}}
<section x-show="aba === 'loja'" x-cloak class="grid gap-4 md:grid-cols-2">
    @php $donoLoja = request()->attributes->get('wf_dono'); @endphp
    <a href="{{ route('carteira.loja') }}" class="card p-6 block hover:border-[var(--prim)]">
        <p class="titulo text-xl">🏪 Loja virtual</p>
        <p class="text-sm texto-2 mt-1">Produtos, aparência, chave Pix, pedidos e a página pública da sua loja.</p>
        @if (! empty($lojaResumo))
            <p class="text-sm mt-3">{{ $lojaResumo['produtos'] }} {{ $lojaResumo['produtos'] === 1 ? 'produto' : 'produtos' }} ·
                {{ $lojaResumo['abertos'] }} {{ $lojaResumo['abertos'] === 1 ? 'pedido esperando' : 'pedidos esperando' }} ·
                {{ $lojaResumo['publicada'] ? 'aberta ao público' : 'fechada' }}</p>
        @else
            <p class="text-sm mt-3" style="color: var(--prim-forte)">Monte sua loja →</p>
        @endif
        @if ($donoLoja) <p class="text-xs texto-2 mt-2">A loja é de cada conta: este atalho abre a sua.</p> @endif
    </a>
    <a href="{{ route('carteira.mkt', $donoLoja ? ['de' => $donoLoja] : []) }}" class="card p-6 block hover:border-[var(--prim)]">
        <p class="titulo text-xl">🛒 Marketplaces</p>
        <p class="text-sm texto-2 mt-1">Contas, anúncios, custos, comissões e margem do Mercado Livre, Shopee e Amazon.</p>
        <p class="text-sm mt-3" style="color: var(--prim-forte)">Abrir →</p>
    </a>
</section>

{{-- ================= EQUIPE ================= --}}
<section x-show="aba === 'equipe'" x-cloak>
    @include('areas.negocios.partials.equipe')
</section>
