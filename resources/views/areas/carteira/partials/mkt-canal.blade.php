{{-- Carteira › Marketplaces › abas Shopee e Amazon (js/marketplaces.js: det, graficoVendas, bloco*).
     Mostra só o canal: contas (conectada, planilha ou demonstração), vendas, gráfico por dia, mais vendidos e anúncios
     com pior margem. Cada parte vira um bloco do quadro (tabela ou gráfico): aqui vai para a Biblioteca, colável em
     qualquer área; dentro de uma área, os itens também podem ser arrastados. --}}
<template x-for="cn in ['shopee', 'amazon']" :key="cn">
    <section x-show="aba === cn" class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <span class="mk-canal !text-sm" :style="'--c:' + cor(cn)" x-text="infoCanal(cn).nome"></span>
            <template x-for="c in contasDo(cn)" :key="c.id">
                <span class="mk-st" :title="c.erro || ''" x-text="c.nome + ' · ' + tipoConta(c) + (c.sincronizado_em ? ' · ' + quando(c.sincronizado_em) : '')"></span>
            </template>
            <span class="flex-1"></span>
            <a class="btn !py-1.5 !px-3 text-sm" x-show="infoCanal(cn).configurado" :href="urlConectar(cn)" :class="{ 'opacity-50 pointer-events-none': cheio }" @click="if (cheio) { $event.preventDefault(); avisarLimite(); }">Conectar loja</a>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="abrirPlanilha(cn)">Importar planilha</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" x-show="contasDo(cn).some(c => !c.demo && !c.manual && c.ativa)" @click="sincronizar(contasDo(cn).filter(c => !c.demo && !c.manual && c.ativa).map(c => c.id))" :disabled="ocupado">⟳ Sincronizar</button>
        </div>

        @include('areas.carteira.partials.mkt-planilha', ['cond' => 'plan.canal === cn'])

        <template x-if="!contasDo(cn).length && carregado">
            <div class="card p-5 space-y-2">
                <h2 class="titulo text-lg" x-text="'Sua loja na ' + infoCanal(cn).nome + ' aqui dentro'"></h2>
                <p class="text-sm texto-2" x-text="(infoCanal(cn).configurado ? 'Conecte a loja para trazer anúncios e pedidos sozinho, ou importe' : 'Importe') + ' a planilha de anúncios ou de pedidos exportada da ' + infoCanal(cn).nome + '. As vendas, tarifas e a margem aparecem aqui, junto com os outros canais no Resumo.'"></p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn" @click="abrirPlanilha(cn)">Importar planilha</button>
                    <button type="button" class="btn-sec" @click="criarDemo(cn)" :disabled="ocupado">Ver com dados de demonstração</button>
                </div>
            </div>
        </template>

        <template x-if="contasDo(cn).length">
            <div class="space-y-4">
                <div class="mk-kpis">
                    <div class="mk-kpi"><span class="text-xs texto-2" x-text="'Vendas (' + dados.dias_pedidos + ' dias)'"></span><b x-text="dinheiro(det(cn).faturamento)"></b><span class="text-xs texto-2" x-text="det(cn).pedidos + ' pedidos · ' + det(cn).itens + ' unidades'"></span></div>
                    <div class="mk-kpi"><span class="text-xs texto-2">Ticket médio</span><b x-text="dinheiro(det(cn).ticket)"></b><span class="text-xs texto-2">por pedido</span></div>
                    <div class="mk-kpi"><span class="text-xs texto-2">Tarifas</span><b x-text="dinheiro(det(cn).tarifas)"></b><span class="text-xs texto-2" x-text="det(cn).faturamento ? pct(det(cn).tarifas / det(cn).faturamento * 100) + ' das vendas' : '—'"></span></div>
                    <div class="mk-kpi"><span class="text-xs texto-2">Margem média</span><b :class="classeMargem(det(cn).margem)" x-text="det(cn).margem === null ? '—' : pct(det(cn).margem)"></b><span class="text-xs texto-2" x-text="det(cn).ativos + ' anúncios ativos' + (det(cn).semCusto ? ' · ' + det(cn).semCusto + ' sem custo' : '')"></span></div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="porNoQuadro(blocoResumo(cn))" title="Guarda na Biblioteca para colar no quadro de qualquer área">⇱ Resumo para o quadro</button>
                </div>

                <div class="card p-4 space-y-2 ig-arrasta" draggable="true" @dragstart="arrastar($event, blocoVendas(cn))">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-sm">Vendas por dia</h3>
                        <span class="text-xs texto-2" x-text="'últimos ' + dados.dias_pedidos + ' dias, sem cancelados'"></span>
                        <button type="button" class="btn-sec !py-1 !px-3 text-sm ml-auto" @click="porNoQuadro(blocoVendas(cn))" title="Guarda o gráfico na Biblioteca para colar no quadro">⇱ Gráfico para o quadro</button>
                    </div>
                    <div x-html="graficoVendas(cn)"></div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="card p-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-sm">Mais vendidos</h3>
                            <button type="button" class="btn-sec !py-1 !px-3 text-sm ml-auto" x-show="det(cn).top.length" @click="porNoQuadro(blocoTop(cn))">⇱ Para o quadro</button>
                        </div>
                        <template x-for="t in det(cn).top" :key="t.sku + t.nome">
                            <div class="flex items-center gap-2 text-sm border-b border-[var(--linha)] py-1">
                                <span class="flex-1 min-w-0 truncate" :title="t.nome" x-text="t.nome"></span>
                                <span class="texto-2 text-xs" x-text="t.qtd + ' un.'"></span>
                                <b class="num" x-text="dinheiro(t.receita)"></b>
                            </div>
                        </template>
                        <p class="text-xs texto-2" x-show="!det(cn).top.length">Nenhuma venda no período. Importe a planilha de pedidos ou sincronize a loja.</p>
                    </div>
                    <div class="card p-4 space-y-2">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-sm">Anúncios com menor margem</h3>
                            <button type="button" class="btn-sec !py-1 !px-3 text-sm ml-auto" x-show="det(cn).anuncios.length" @click="porNoQuadro(blocoAnuncios(cn))" title="Todos os anúncios do canal como tabela">⇱ Anúncios para o quadro</button>
                        </div>
                        <template x-for="a in det(cn).piores" :key="a.id">
                            <div class="flex items-center gap-2 text-sm border-b border-[var(--linha)] py-1 ig-arrasta" draggable="true" @dragstart="arrastar($event, blocoAnuncio(a))">
                                <span class="flex-1 min-w-0 truncate" :title="a.titulo" x-text="a.titulo || a.sku"></span>
                                <span class="texto-2 text-xs" x-text="dinheiro(a.preco)"></span>
                                <b class="num" :class="classeMargem(a.margem)" x-text="pct(a.margem)"></b>
                                <button type="button" class="mini-btn" title="Para o quadro" aria-label="Para o quadro" @click="porNoQuadro(blocoAnuncio(a))">⇱</button>
                            </div>
                        </template>
                        <p class="text-xs texto-2" x-show="!det(cn).piores.length">Cadastre o custo dos produtos (aba Produtos e custos) para ver a margem de cada anúncio.</p>
                        <button type="button" class="link-prim text-sm" x-show="det(cn).anuncios.length" @click="filtro.conta = contasDo(cn).length === 1 ? String(contasDo(cn)[0].id) : ''; irPara('anuncios')" x-text="'Ver os ' + det(cn).anuncios.length + ' anúncios →'"></button>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>
