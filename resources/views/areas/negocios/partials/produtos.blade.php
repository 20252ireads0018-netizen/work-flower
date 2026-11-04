{{-- Negócios › Produtos: catálogo de produtos e serviços da conta (o mesmo da mini loja, tabela loja_produtos).
     Dados e ações em js/carteira.js (prod, carregarProdutos, salvarProduto…); servidor: ProdutosController. --}}
<div class="ng-pagina space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <p class="text-sm texto-2 flex-1 min-w-[16rem]">
            Cadastre o que você vende. Ao lançar uma venda em <button type="button" class="link-prim" @click="ir('negocios')">Negócios</button>,
            escolha o produto: o preço vem preenchido, o custo entra na margem e o estoque baixa.
            <span x-show="prod.temLoja">Os marcados “Mostrar na loja” aparecem na sua loja virtual.</span>
        </p>
        <button type="button" class="btn" @click="novoProduto()">+ Novo produto ou serviço</button>
    </div>

    <p class="erro" x-show="prod.erro && !prod.aberto" x-text="prod.erro"></p>

    <div class="ng-produtos" :class="prod.aberto && 'com-form'">
        {{-- formulário (novo ou editar) --}}
        <form x-show="prod.aberto" x-cloak x-ref="prodForm" class="card p-4 space-y-3 self-start" @submit.prevent="salvarProduto($el)" enctype="multipart/form-data">
            <div class="flex items-center gap-2">
                <b class="flex-1" x-text="prod.form.id ? 'Editar produto' : 'Novo produto ou serviço'"></b>
                <button type="button" class="mini-btn" aria-label="Fechar" @click="prod.aberto = false">✕</button>
            </div>
            <div class="flex gap-1.5">
                <button type="button" class="chip" :class="{ 'ativo': prod.form.tipo === 'produto' }" @click="prod.form.tipo = 'produto'">Produto</button>
                <button type="button" class="chip" :class="{ 'ativo': prod.form.tipo === 'servico' }" @click="prod.form.tipo = 'servico'; prod.form.estoque = ''">Serviço</button>
            </div>
            <label class="ng-campo"><span>Nome</span><input class="campo" x-ref="prodNome" x-model="prod.form.nome" maxlength="120" required></label>
            <div class="ng-dois">
                <label class="ng-campo"><span>SKU / código (opcional)</span><input class="campo" x-model="prod.form.sku" maxlength="40" placeholder="Ex.: BOLO-CHOC-G"></label>
                <label class="ng-campo"><span>Categoria</span><input class="campo" x-model="prod.form.categoria" maxlength="60" list="ng-cats" placeholder="Ex.: Bolos"></label>
            </div>
            <datalist id="ng-cats"><template x-for="c in prod.cats" :key="c"><option :value="c"></option></template></datalist>
            <div class="ng-dois">
                <label class="ng-campo"><span>Preço de venda (R$)</span><input class="campo" type="number" min="0.01" step="0.01" x-model="prod.form.preco" required></label>
                <label class="ng-campo"><span>Custo (R$, opcional)</span><input class="campo" type="number" min="0" step="0.01" x-model="prod.form.custo"></label>
            </div>
            <p class="text-xs texto-2" x-text="margemForm() === null ? 'Informe o custo para ver a margem de lucro.' : 'Margem: ' + margemTexto(margemForm()) + ' · lucro de ' + brl(num(prod.form.preco) - num(prod.form.custo)) + ' por unidade'"></p>
            <label class="ng-campo" x-show="prod.form.tipo === 'produto'"><span>Estoque (vazio = não controlar)</span><input class="campo" type="number" min="0" step="1" x-model="prod.form.estoque"></label>
            <label class="ng-campo"><span>Descrição (aparece na loja)</span><textarea class="campo" rows="2" maxlength="2000" x-model="prod.form.descricao"></textarea></label>
            <div class="ng-campo"><span>Foto (JPG, PNG ou WebP, até 3 MB)</span>
                <div class="flex items-center gap-2">
                    <img x-show="prod.form.foto && !prod.form.tirar_foto" :src="prod.form.foto" alt="" class="ng-foto">
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="text-sm min-w-0">
                </div>
                <label class="flex items-center gap-2 text-xs mt-1" x-show="prod.form.foto"><input type="checkbox" x-model="prod.form.tirar_foto"> tirar a foto atual</label>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="prod.form.na_loja"> Mostrar na loja virtual</label>
            <p class="text-xs texto-2" x-show="prod.form.na_loja && !prod.temLoja">Você ainda não tem loja: o produto aparece nela quando você <a class="link-prim" href="{{ route('carteira.loja') }}">criar a loja</a>.</p>
            <p class="erro" x-text="prod.erro"></p>
            <div class="flex gap-2">
                <button type="submit" class="btn" :disabled="prod.salvando" x-text="prod.salvando ? 'Salvando…' : (prod.form.id ? 'Salvar' : 'Cadastrar')"></button>
                <button type="button" class="btn-sec" @click="prod.aberto = false">Cancelar</button>
            </div>
        </form>

        {{-- lista --}}
        <div class="card p-4 space-y-3 min-w-0">
            <div class="flex flex-wrap gap-2">
                <input class="campo flex-1 min-w-[12rem]" type="search" x-model="prod.busca" placeholder="Buscar por nome, SKU ou categoria" aria-label="Buscar produtos">
                <select class="campo !w-auto" x-model="prod.cat" x-show="prod.cats.length" aria-label="Filtrar por categoria">
                    <option value="">Todas as categorias</option>
                    <template x-for="c in prod.cats" :key="c"><option :value="c" x-text="c"></option></template>
                </select>
            </div>
            <p x-show="!prod.carregado" class="text-sm texto-2">Carregando o catálogo…</p>
            <div x-show="prod.carregado && !prod.lista.length" class="text-sm texto-2 space-y-2">
                <p>Nenhum produto ainda. Cadastre o primeiro em <b>+ Novo produto ou serviço</b>: nome, preço, custo e estoque.</p>
                <p>Com o catálogo pronto, lançar uma venda é só escolher o produto e a quantidade.</p>
            </div>
            <p x-show="prod.lista.length && !produtosFiltrados().length" class="text-sm texto-2">Nada encontrado com essa busca.</p>
            <div class="overflow-x-auto" x-show="produtosFiltrados().length">
                <table class="wf-tabela ng-tabela">
                    <thead><tr><th></th><th>Produto</th><th class="ng-opc">Categoria</th><th class="num">Preço</th><th class="num ng-opc">Custo</th><th class="num">Margem</th><th class="num">Estoque</th><th class="ng-opc">Loja</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="p in produtosFiltrados()" :key="p.id">
                            <tr>
                                <td><img x-show="p.foto" :src="p.foto" alt="" class="ng-foto" loading="lazy"><span x-show="!p.foto" class="ng-foto"></span></td>
                                <td class="min-w-[10rem]"><b class="block" x-text="p.nome"></b><span class="text-xs texto-2" x-text="(p.tipo === 'servico' ? 'Serviço' : 'Produto') + (p.sku ? ' · ' + p.sku : '')"></span></td>
                                <td class="text-sm ng-opc" x-text="p.categoria || '—'"></td>
                                <td class="num" x-text="brl(p.preco)"></td>
                                <td class="num texto-2 ng-opc" x-text="p.custo === null ? '—' : brl(p.custo)"></td>
                                <td class="num" x-text="margemTexto(p.margem)" :style="p.margem !== null && p.margem < 0 ? 'color:#ef4444' : ''"></td>
                                <td class="num whitespace-nowrap" :style="p.estoque === 0 ? 'color:#ef4444' : ''" x-text="estoqueTexto(p)"></td>
                                <td class="text-xs ng-opc" x-text="p.na_loja ? 'na loja' : '—'"></td>
                                <td class="whitespace-nowrap text-right">
                                    <button type="button" class="btn-sec !py-0.5 text-xs" @click="venderProduto(p)" title="Lançar uma venda deste produto">Vender</button>
                                    <button type="button" class="btn-sec !py-0.5 text-xs" @click="editarProduto(p)">Editar</button>
                                    <button type="button" class="mini-btn perigo" title="Apagar do catálogo" :aria-label="'Apagar ' + p.nome" @click="remProduto(p)">✕</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
