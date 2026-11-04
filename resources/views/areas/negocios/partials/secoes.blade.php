{{-- Abas só da área Negócios, incluídas em areas/carteira/index (modo "negocios"):
     Clientes (no estado da área), Loja e Marketplaces (atalhos com resumo) e Equipe (partials/equipe, js/equipe.js). --}}

{{-- ================= CLIENTES ================= --}}
<section class="wf-quadro" x-show="aba === 'clientes'" x-cloak :style="{ minHeight: alturaQuadro('clientes') + 'px' }">
    <x-carteira.item sec="'clientes'" bid="'cli-lista'" titulo="Clientes" :minw="420" :minh="300">
        <x-slot name="acoes">
            <button type="button" class="btn-sec !py-1" @click="zapAbrir(null)" title="Mandar uma mensagem pronta para os clientes da busca">💬 Mensagem para a lista</button>
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
                    <thead><tr><th>Nome</th><th>Contato</th><th>Etiquetas</th><th>Compras</th><th>Notas</th><th>Última mensagem</th><th></th></tr></thead>
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
                                <td class="text-xs texto-2 whitespace-nowrap" x-text="zapUltima(c) || '—'"></td>
                                <td class="whitespace-nowrap">
                                    <button type="button" class="mini-btn" title="Mensagem pronta no WhatsApp" aria-label="Mensagem no WhatsApp" x-show="zapNumero(c)" @click="zapAbrir(c)">💬</button>
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remCliente(c)">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </x-carteira.item>

    {{-- mensagens prontas: escolhe o texto, abre o WhatsApp já preenchido e guarda no histórico do cliente --}}
    <template x-teleport="body">
    <div x-show="zap.aberto" x-cloak class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="zap.aberto = false" @click.self="zap.aberto = false">
        <div class="card p-4 w-full max-w-lg max-h-[90vh] overflow-auto space-y-3" role="dialog" aria-modal="true" aria-label="Mensagem no WhatsApp">
            <div class="flex items-center gap-2">
                <b class="flex-1" x-text="zapCliente() ? 'Mensagem para ' + zapCliente().nome : 'Mensagem para ' + zap.fila.length + ' clientes da busca'"></b>
                <button type="button" class="mini-btn" aria-label="Fechar" @click="zap.aberto = false">✕</button>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="m in estado.msgModelos" :key="m.id">
                    <button type="button" class="btn-sec !py-0.5 text-xs" :class="zap.modelo === m.id && '!bg-[var(--prim)] !text-[var(--on-prim)]'" @click="zapEscolher(m.id)" x-text="m.nome"></button>
                </template>
                <button type="button" class="btn-sec !py-0.5 text-xs" @click="zapNovoModelo()">+ Nova</button>
            </div>
            <template x-if="zap.editar">
                <input class="campo text-sm" :value="(estado.msgModelos.find(m => m.id === zap.modelo) || {}).nome" @input="(estado.msgModelos.find(m => m.id === zap.modelo) || {}).nome = $event.target.value.slice(0, 40)" aria-label="Nome da mensagem pronta">
            </template>
            <textarea class="campo text-sm" rows="4" x-model="zap.texto" maxlength="1500" aria-label="Texto da mensagem"></textarea>
            <p class="text-xs texto-2">Use {primeiro_nome}, {nome} e {loja}: cada cliente recebe com o próprio nome.</p>
            <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" class="link-prim" @click="zapSalvarModelo()">Guardar este texto na mensagem pronta</button>
                <button type="button" class="link-prim" @click="zap.editar = !zap.editar" x-text="zap.editar ? 'Pronto' : 'Renomear'"></button>
                <button type="button" class="link-prim" style="color:#ef4444" @click="zapTirarModelo()">Apagar</button>
            </div>
            <template x-if="zapCliente()">
                <div class="space-y-2">
                    <p class="text-sm rounded-lg p-2 whitespace-pre-wrap" style="background:var(--superficie-2)" x-text="zapPreencher(zap.texto, zapCliente())"></p>
                    <a class="btn w-full text-center" :href="zapLink(zapCliente())" target="_blank" rel="noopener" @click="zapEnviou(zapCliente())">Abrir no WhatsApp</a>
                    <template x-if="(zapCliente().msgs || []).length">
                        <details class="text-xs"><summary class="cursor-pointer texto-2">Mensagens já mandadas</summary>
                            <template x-for="m in zapCliente().msgs" :key="m.em"><p class="mt-1"><b x-text="new Date(m.em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) + ' · ' + m.modelo"></b> <span class="texto-2" x-text="m.texto"></span></p></template>
                        </details>
                    </template>
                </div>
            </template>
            <template x-if="!zapCliente()">
                <div class="space-y-1">
                    <p class="text-xs texto-2">O WhatsApp abre um cliente por vez (o navegador não deixa abrir todos de uma vez). Clique em cada um; os já abertos ficam marcados.</p>
                    <template x-for="id in zap.fila" :key="id">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="flex-1" x-text="(estado.clientes.find(x => x.id === id) || {}).nome"></span>
                            <span class="text-xs" x-show="(zap.feitos || []).includes(id)">✓ aberto</span>
                            <a class="btn-sec !py-0.5 text-xs" target="_blank" rel="noopener" :href="zapLink(estado.clientes.find(x => x.id === id))" @click="zapEnviou(estado.clientes.find(x => x.id === id))">Abrir</a>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
    </template>
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
