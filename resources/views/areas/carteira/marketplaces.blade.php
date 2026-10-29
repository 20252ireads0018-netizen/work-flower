@extends('layouts.app')

@section('titulo', 'Marketplaces')
@section('subtitulo', 'Carteira · contas, anúncios, custos, comissões e margem do Mercado Livre, Shopee e Amazon.')

@section('conteudo')
@php
    $de = request()->attributes->get('wf_dono');
    $cfg = [
        'base'     => url('/carteira/marketplaces'),
        'carteira' => route('areas.carteira', $de ? ['de' => $de] : []),
        'planos'   => route('planos'),
        'ok'       => session('mkt_ok'),
        'erro'     => session('mkt_erro'),
    ];
@endphp

<style>
    .mk-abas { display: flex; flex-wrap: wrap; gap: .35rem; border-bottom: 1px solid var(--linha); margin-bottom: 1rem; }
    .mk-aba { padding: .55rem .9rem; font-size: .875rem; font-weight: 600; color: var(--tinta-2); border-bottom: 2px solid transparent; margin-bottom: -1px; }
    .mk-aba:hover { color: var(--tinta); }
    .mk-aba.ativa { color: var(--prim-forte); border-color: var(--prim); }
    .mk-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: .75rem; }
    .mk-kpi { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .6rem; padding: .75rem 1rem; }
    .mk-kpi b { display: block; font-size: 1.25rem; font-family: 'Bricolage Grotesque','DM Sans',sans-serif; margin-top: .15rem; }
    .mk-tab-wrap { overflow-x: auto; border: 1px solid var(--linha); border-radius: .6rem; }
    .mk-tab { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .mk-tab th { text-align: left; font-weight: 500; color: var(--tinta-2); padding: .5rem .6rem; background: var(--superficie-2); border-bottom: 1px solid var(--linha); white-space: nowrap; position: sticky; top: 0; }
    .mk-tab td { padding: .45rem .6rem; border-bottom: 1px solid var(--linha); vertical-align: middle; }
    .mk-tab tr:last-child td { border-bottom: 0; }
    .mk-tab tr.sel td { background: var(--prim-suave); }
    .mk-tab .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .mk-tab input.campo { padding: .3rem .45rem; font-size: .85rem; }
    .mk-canal { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; padding: .1rem .5rem; border-radius: 9999px; border: 1px solid var(--linha); white-space: nowrap; }
    .mk-canal::before { content: ''; width: .5rem; height: .5rem; border-radius: 9999px; background: var(--c); }
    .mk-st { font-size: .72rem; padding: .1rem .45rem; border-radius: 9999px; background: var(--superficie-2); border: 1px solid var(--linha); white-space: nowrap; }
    .mk-st.ativo { color: #4ade80; border-color: color-mix(in srgb, #4ade80 40%, transparent); }
    .mk-st.pausado { color: #fbbf24; border-color: color-mix(in srgb, #fbbf24 40%, transparent); }
    .mk-st.encerrado { color: var(--tinta-2); }
    .mk-pos { color: #4ade80; } .mk-neg { color: #f87171; } .mk-meio { color: #fbbf24; }
    .mk-barra { position: sticky; bottom: .75rem; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .6rem .8rem; border-radius: .75rem; background: var(--superficie); border: 1px solid var(--prim-linha); box-shadow: 0 10px 30px rgba(0,0,0,.35); }
    .mk-barra .campo { width: auto; padding: .4rem .6rem; font-size: .85rem; }
    .mk-canais { display: grid; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); gap: .75rem; }
    .mk-filtros { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .75rem; }
    .mk-filtros .campo { width: auto; padding: .4rem .6rem; font-size: .85rem; }
</style>

<div x-data="wfMarketplaces(@js($cfg))" x-init="iniciar()" x-cloak class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        <a class="btn-sec !py-1.5 !px-3 text-sm" :href="cfg.carteira">← Carteira</a>
        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="sincronizarTodas()" :disabled="ocupado || !contasAtivas.length">
            ⟳ Sincronizar todas
        </button>
        <span class="text-xs texto-2" x-show="ocupado" x-text="progresso || 'Carregando…'"></span>
    </div>

    <p class="aviso-ok !mb-0" x-show="ok" x-text="ok" role="status"></p>
    <p class="erro !mt-0 text-sm" x-show="erro" x-text="erro" role="alert"></p>

    <nav class="mk-abas" aria-label="Seções de marketplaces">
        <template x-for="a in abas" :key="a.id">
            <button type="button" class="mk-aba" :class="{ ativa: aba === a.id }" @click="irPara(a.id)" x-text="a.nome"></button>
        </template>
    </nav>

    {{-- ===================== Resumo ===================== --}}
    <section x-show="aba === 'resumo'" class="space-y-4">
        <template x-if="!contas.length && carregado">
            <div class="card p-5 space-y-2">
                <h2 class="titulo text-lg">Comece conectando uma conta</h2>
                <p class="text-sm texto-2">Conecte suas contas do Mercado Livre, Shopee ou Amazon para ver anúncios, vendas, comissões e margem num lugar só, e alterar preço e estoque de muitos anúncios de uma vez. Sem as chaves de API, experimente com uma conta de demonstração.</p>
                <button type="button" class="btn" @click="irPara('contas')">Conectar uma conta</button>
            </div>
        </template>

        <div class="mk-kpis" x-show="contas.length">
            <div class="mk-kpi"><span class="text-xs texto-2" x-text="'Vendas (' + dados.dias_pedidos + ' dias)'"></span><b x-text="dinheiro(resumo.faturamento)"></b><span class="text-xs texto-2" x-text="resumo.pedidos + ' pedidos'"></span></div>
            <div class="mk-kpi"><span class="text-xs texto-2">Tarifas dos marketplaces</span><b x-text="dinheiro(resumo.tarifas)"></b><span class="text-xs texto-2" x-text="resumo.tarifasEstimadas ? 'parte estimada pelas regras' : 'informadas pelos canais'"></span></div>
            <div class="mk-kpi"><span class="text-xs texto-2">Lucro estimado das vendas</span><b :class="resumo.lucro < 0 ? 'mk-neg' : ''" x-text="resumo.lucroConhecido ? dinheiro(resumo.lucro) : '—'"></b><span class="text-xs texto-2" x-text="resumo.semCustoVendas ? resumo.semCustoVendas + ' itens vendidos sem custo cadastrado' : 'custo × quantidade vendida'"></span></div>
            <div class="mk-kpi"><span class="text-xs texto-2">Margem média (anúncios ativos)</span><b :class="classeMargem(resumo.margemMedia)" x-text="resumo.margemMedia === null ? '—' : pct(resumo.margemMedia)"></b><span class="text-xs texto-2" x-text="resumo.ativos + ' ativos'"></span></div>
            <button type="button" class="mk-kpi text-left" @click="filtro.margem = 'negativa'; irPara('anuncios')"><span class="text-xs texto-2">Margem negativa</span><b :class="resumo.negativos ? 'mk-neg' : ''" x-text="resumo.negativos"></b><span class="text-xs texto-2">anúncios dando prejuízo →</span></button>
            <button type="button" class="mk-kpi text-left" @click="filtro.margem = 'semcusto'; irPara('anuncios')"><span class="text-xs texto-2">Sem custo cadastrado</span><b :class="resumo.semCusto ? 'mk-meio' : ''" x-text="resumo.semCusto"></b><span class="text-xs texto-2">anúncios sem margem calculada →</span></button>
        </div>

        <div class="mk-tab-wrap" x-show="contas.length">
            <table class="mk-tab">
                <thead><tr><th>Canal</th><th class="num">Contas</th><th class="num">Anúncios ativos</th><th class="num">Pedidos</th><th class="num">Vendas</th><th class="num">Tarifas</th><th class="num">Margem média</th></tr></thead>
                <tbody>
                    <template x-for="c in resumo.porCanal" :key="c.canal">
                        <tr>
                            <td><span class="mk-canal" :style="'--c:' + cor(c.canal)" x-text="c.nome"></span></td>
                            <td class="num" x-text="c.contas"></td>
                            <td class="num" x-text="c.ativos"></td>
                            <td class="num" x-text="c.pedidos"></td>
                            <td class="num" x-text="dinheiro(c.faturamento)"></td>
                            <td class="num" x-text="dinheiro(c.tarifas)"></td>
                            <td class="num" :class="classeMargem(c.margem)" x-text="c.margem === null ? '—' : pct(c.margem)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- calculadora de preço e margem --}}
        <div class="card p-4 space-y-3 max-w-3xl">
            <div>
                <h2 class="titulo text-lg">Calculadora de preço</h2>
                <p class="text-xs texto-2 mt-1">Quanto sobra em cada venda, ou que preço cobrar para ter a margem que você quer. Usa as regras de tarifa da conta escolhida (ou as de referência do canal).</p>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <label class="text-sm col-span-2"><span class="rotulo">Conta ou canal</span>
                    <select class="campo" x-model="calc.onde">
                        <template x-for="o in opcoesCalc" :key="o.valor"><option :value="o.valor" x-text="o.nome"></option></template>
                    </select>
                </label>
                <label class="text-sm"><span class="rotulo">Custo (R$)</span><input type="number" step="0.01" min="0" class="campo" x-model.number="calc.custo"></label>
                <label class="text-sm"><span class="rotulo">Frete pago (R$)</span><input type="number" step="0.01" min="0" class="campo" x-model.number="calc.frete" :placeholder="regrasCalc.frete"></label>
                <label class="text-sm"><span class="rotulo">Preço de venda (R$)</span><input type="number" step="0.01" min="0" class="campo" x-model.number="calc.preco"></label>
                <label class="text-sm"><span class="rotulo">ou margem desejada (%)</span><input type="number" step="0.5" class="campo" x-model.number="calc.margem"></label>
            </div>
            <div class="mk-kpis">
                <div class="mk-kpi"><span class="text-xs texto-2">Tarifa do marketplace</span><b x-text="dinheiro(calculo.tarifa)"></b><span class="text-xs texto-2" x-text="pct(regrasCalc.comissao_pct) + (Number(regrasCalc.taxa_fixa) ? ' + ' + dinheiro(regrasCalc.taxa_fixa) + (regrasCalc.taxa_fixa_abaixo_de ? ' abaixo de ' + dinheiro(regrasCalc.taxa_fixa_abaixo_de) : '') : '')"></span></div>
                <div class="mk-kpi"><span class="text-xs texto-2">Imposto</span><b x-text="dinheiro(calculo.imposto)"></b><span class="text-xs texto-2" x-text="pct(regrasCalc.imposto_pct)"></span></div>
                <div class="mk-kpi"><span class="text-xs texto-2">Lucro por venda</span><b :class="calculo.lucro < 0 ? 'mk-neg' : 'mk-pos'" x-text="dinheiro(calculo.lucro)"></b><span class="text-xs texto-2" :class="classeMargem(calculo.margem)" x-text="calculo.margem === null ? '' : 'margem ' + pct(calculo.margem)"></span></div>
                <div class="mk-kpi"><span class="text-xs texto-2">Preço para a margem desejada</span><b x-text="calculo.precoAlvo === null ? '—' : dinheiro(calculo.precoAlvo)"></b><span class="text-xs texto-2" x-text="calc.margem === '' || calc.margem === null ? 'preencha a margem' : (calculo.precoAlvo === null ? 'impossível com essa comissão' : 'para ' + pct(calc.margem))"></span></div>
            </div>
        </div>
    </section>

    {{-- ===================== Contas ===================== --}}
    <section x-show="aba === 'contas'" class="space-y-4">
        <p class="text-sm" x-text="textoLimite()"></p>
        <div class="mk-canais">
            <template x-for="c in dados.canais" :key="c.canal">
                <div class="card p-4 space-y-2">
                    <span class="mk-canal" :style="'--c:' + cor(c.canal)" x-text="c.nome"></span>
                    <p class="text-xs texto-2" x-show="c.configurado">Você vai para o site do marketplace autorizar o Work Flower a ler e alterar seus anúncios e pedidos.</p>
                    <p class="text-xs texto-2" x-show="!c.configurado">A conexão real precisa das chaves do app de desenvolvedor no servidor. Enquanto isso, use a demonstração.</p>
                    <div class="flex flex-wrap gap-2">
                        <a class="btn !py-1.5 !px-3 text-sm" x-show="c.configurado" :href="urlConectar(c.canal)" :class="{ 'opacity-50 pointer-events-none': cheio }" @click="if (cheio) { $event.preventDefault(); avisarLimite(); }">Conectar conta</a>
                        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="criarDemo(c.canal)" :disabled="ocupado">Demonstração</button>
                    </div>
                </div>
            </template>
        </div>
        <a class="link-prim text-sm" x-show="cheio" :href="cfg.planos">Ver planos para conectar mais contas</a>

        <div class="mk-tab-wrap" x-show="contas.length">
            <table class="mk-tab">
                <thead><tr>
                    <th><input type="checkbox" :checked="todasMarcadas(contas, selContas)" @change="marcarTodas(contas, selContas, $event.target.checked)" aria-label="Marcar todas as contas"></th>
                    <th>Conta</th><th>Canal</th><th class="num">Anúncios</th><th>Tarifas (comissão / fixa / frete / imposto)</th><th>Última sincronização</th><th></th>
                </tr></thead>
                <tbody>
                    <template x-for="c in contas" :key="c.id">
                        <tr :class="{ sel: selContas[c.id] }">
                            <td><input type="checkbox" x-model="selContas[c.id]" :aria-label="'Marcar ' + c.nome"></td>
                            <td>
                                <button type="button" class="font-semibold text-left hover:underline" @click="renomear(c)" x-text="c.nome" title="Renomear"></button>
                                <span class="mk-st ml-1" x-show="c.demo">demonstração</span>
                                <span class="mk-st pausado ml-1" x-show="!c.ativa">pausada</span>
                                <div class="erro !mt-1 text-xs" x-show="c.erro" x-text="c.erro"></div>
                            </td>
                            <td><span class="mk-canal" :style="'--c:' + cor(c.canal)" x-text="c.canal_nome"></span></td>
                            <td class="num" x-text="c.anuncios"></td>
                            <td class="text-xs">
                                <span x-text="pct(c.regras_efetivas.comissao_pct) + ' / ' + dinheiro(c.regras_efetivas.taxa_fixa) + (c.regras_efetivas.taxa_fixa_abaixo_de ? ' (< ' + dinheiro(c.regras_efetivas.taxa_fixa_abaixo_de) + ')' : '') + ' / ' + dinheiro(c.regras_efetivas.frete) + ' / ' + pct(c.regras_efetivas.imposto_pct)"></span>
                                <span class="texto-2" x-show="!Object.keys(c.regras).length"> · referência</span>
                            </td>
                            <td class="text-xs texto-2" x-text="c.sincronizado_em ? quando(c.sincronizado_em) : 'nunca'"></td>
                            <td><button type="button" class="mini-btn" @click="sincronizar([c.id])" :disabled="ocupado || !c.ativa" :aria-label="'Sincronizar ' + c.nome" title="Sincronizar">⟳</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mk-barra" x-show="nSel(selContas)" x-transition>
            <b class="text-sm" x-text="nSel(selContas) + (nSel(selContas) === 1 ? ' conta' : ' contas')"></b>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="sincronizar(ids(selContas))" :disabled="ocupado">⟳ Sincronizar</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="editarContas({ ativa: false })" :disabled="ocupado">Pausar</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="editarContas({ ativa: true })" :disabled="ocupado">Reativar</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="regrasAberto = !regrasAberto">Tarifas…</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" style="color:#f87171" @click="desconectar()" :disabled="ocupado">Desconectar</button>
            <button type="button" class="link-prim text-sm ml-auto" @click="selContas = {}">Limpar seleção</button>
            <form x-show="regrasAberto" class="w-full grid grid-cols-2 sm:grid-cols-6 gap-2 items-end pt-2" @submit.prevent="salvarRegras()">
                <label class="text-xs">Comissão %<input type="number" step="0.01" min="0" max="99" class="campo !w-full" x-model="regras.comissao_pct" placeholder="referência"></label>
                <label class="text-xs">Tarifa fixa R$<input type="number" step="0.01" min="0" class="campo !w-full" x-model="regras.taxa_fixa" placeholder="referência"></label>
                <label class="text-xs">Fixa só abaixo de R$<input type="number" step="0.01" min="0" class="campo !w-full" x-model="regras.taxa_fixa_abaixo_de" placeholder="sempre"></label>
                <label class="text-xs">Frete médio R$<input type="number" step="0.01" min="0" class="campo !w-full" x-model="regras.frete" placeholder="referência"></label>
                <label class="text-xs">Imposto %<input type="number" step="0.01" min="0" max="99" class="campo !w-full" x-model="regras.imposto_pct" placeholder="referência"></label>
                <button type="submit" class="btn !py-1.5 text-sm" :disabled="ocupado">Aplicar nas marcadas</button>
                <p class="col-span-full text-xs texto-2">Campos vazios voltam ao valor de referência do canal. O Mercado Livre informa a tarifa exata de cada anúncio; nele, estas regras só valem onde a tarifa não veio ou quando você define comissão no anúncio.</p>
            </form>
        </div>
    </section>

    {{-- ===================== Anúncios ===================== --}}
    <section x-show="aba === 'anuncios'" class="space-y-3">
        <div class="mk-filtros">
            <input type="search" class="campo min-w-[14rem]" placeholder="Buscar título, SKU ou código" x-model.debounce.250ms="filtro.busca" aria-label="Buscar anúncios">
            <select class="campo" x-model="filtro.conta" aria-label="Conta">
                <option value="">Todas as contas</option>
                <template x-for="c in contas" :key="c.id"><option :value="String(c.id)" x-text="c.canal_nome + ' · ' + c.nome"></option></template>
            </select>
            <select class="campo" x-model="filtro.status" aria-label="Status">
                <option value="">Todos os status</option><option value="ativo">Ativos</option><option value="pausado">Pausados</option><option value="encerrado">Encerrados</option>
            </select>
            <select class="campo" x-model="filtro.margem" aria-label="Margem">
                <option value="">Qualquer margem</option><option value="negativa">Margem negativa</option><option value="baixa">Margem abaixo de 10%</option><option value="semcusto">Sem custo cadastrado</option>
            </select>
            <select class="campo" x-model="ordem" aria-label="Ordenar">
                <option value="titulo">Ordenar por título</option><option value="margem">Menor margem</option><option value="-margem">Maior margem</option><option value="preco">Menor preço</option><option value="-preco">Maior preço</option><option value="estoque">Menor estoque</option>
            </select>
            <span class="text-xs texto-2 self-center" x-text="anunciosFiltrados.length + ' de ' + anuncios.length + ' anúncios'"></span>
        </div>

        <div class="mk-tab-wrap" style="max-height: 70vh">
            <table class="mk-tab">
                <thead><tr>
                    <th><input type="checkbox" :checked="todasMarcadas(paginaAnuncios, selAnuncios)" @change="marcarTodas(anunciosFiltrados, selAnuncios, $event.target.checked)" aria-label="Marcar todos os anúncios filtrados"></th>
                    <th>Anúncio</th><th>Conta</th><th class="num">Preço</th><th class="num">Estoque</th><th>Status</th>
                    <th class="num">Tarifa</th><th class="num">Frete</th><th class="num">Custo</th><th class="num">Lucro</th><th class="num">Margem</th>
                </tr></thead>
                <tbody>
                    <template x-for="a in paginaAnuncios" :key="a.id">
                        <tr :class="{ sel: selAnuncios[a.id] }">
                            <td><input type="checkbox" x-model="selAnuncios[a.id]" :aria-label="'Marcar ' + a.titulo"></td>
                            <td class="max-w-[22rem]">
                                <div class="truncate" :title="a.titulo"><a x-show="a.link" :href="a.link" target="_blank" rel="noopener" class="hover:underline" x-text="a.titulo"></a><span x-show="!a.link" x-text="a.titulo"></span></div>
                                <div class="text-xs texto-2"><span x-text="a.sku && a.sku !== a.externo_id ? 'SKU ' + a.sku + ' · ' + a.externo_id : a.externo_id + ' (sem SKU: o custo fica neste anúncio)'"></span><span x-show="a.tipo" x-text="' · ' + a.tipo"></span></div>
                            </td>
                            <td class="text-xs"><span class="mk-canal" :style="'--c:' + cor(conta(a.conta_id)?.canal)" x-text="conta(a.conta_id)?.nome"></span></td>
                            <td class="num" x-text="dinheiro(a.preco)"></td>
                            <td class="num" :class="a.estoque === 0 ? 'mk-meio' : ''" x-text="a.estoque"></td>
                            <td><span class="mk-st" :class="a.status" x-text="a.status"></span></td>
                            <td class="num" :title="a.tarifa_api ? 'informada pelo marketplace' : 'pela regra' + (a.comissao_pct !== null ? ' (comissão do anúncio: ' + pct(a.comissao_pct) + ')' : ' da conta')">
                                <span x-text="dinheiro(a.tarifa)"></span><span class="texto-2" x-text="a.tarifa_api ? '' : '*'"></span>
                            </td>
                            <td class="num" x-text="dinheiro(a.frete)"></td>
                            <td class="num"><span x-show="a.custo !== null" x-text="dinheiro(a.custo)"></span><button type="button" x-show="a.custo === null && a.sku" class="link-prim text-xs" @click="cadastrarCusto(a)">+ custo</button></td>
                            <td class="num" :class="a.lucro === null ? '' : (a.lucro < 0 ? 'mk-neg' : 'mk-pos')" x-text="a.lucro === null ? '—' : dinheiro(a.lucro)"></td>
                            <td class="num font-semibold" :class="classeMargem(a.margem)" x-text="a.margem === null ? '—' : pct(a.margem)"></td>
                        </tr>
                    </template>
                    <tr x-show="!anunciosFiltrados.length"><td colspan="11" class="texto-2 text-sm p-4" x-text="anuncios.length ? 'Nenhum anúncio com esses filtros.' : 'Conecte uma conta e sincronize para ver os anúncios.'"></td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center gap-2 text-sm" x-show="paginas > 1">
            <button type="button" class="btn-sec !py-1 !px-2" @click="pagina--" :disabled="pagina <= 1">‹</button>
            <span x-text="'Página ' + pagina + ' de ' + paginas"></span>
            <button type="button" class="btn-sec !py-1 !px-2" @click="pagina++" :disabled="pagina >= paginas">›</button>
        </div>
        <p class="text-xs texto-2">* tarifa calculada pela regra da conta (comissão % + tarifa fixa). Sem asterisco: valor informado pelo marketplace.</p>

        <div class="mk-barra" x-show="nSel(selAnuncios)" x-transition>
            <b class="text-sm" x-text="nSel(selAnuncios) + (nSel(selAnuncios) === 1 ? ' anúncio' : ' anúncios')"></b>
            <select class="campo" x-model="acao.tipo" aria-label="O que fazer">
                <option value="preco_pct">Ajustar preço em %</option>
                <option value="preco">Definir preço (R$)</option>
                <option value="margem">Preço para margem de (%)</option>
                <option value="estoque">Definir estoque</option>
                <option value="estoque_soma">Somar ao estoque (±)</option>
                <option value="ativo">Ativar</option>
                <option value="pausado">Pausar</option>
                <option value="comissao">Comissão do anúncio (%) — só a conta da margem</option>
                <option value="frete">Frete do anúncio (R$) — só a conta da margem</option>
            </select>
            <input type="number" step="0.01" class="campo w-28" x-model="acao.valor" x-show="!['ativo','pausado'].includes(acao.tipo)" :placeholder="acao.tipo === 'comissao' || acao.tipo === 'frete' ? 'vazio = regra' : 'valor'" aria-label="Valor">
            <button type="button" class="btn !py-1.5 !px-3 text-sm" @click="aplicarAcao()" :disabled="ocupado">Aplicar</button>
            <button type="button" class="link-prim text-sm ml-auto" @click="selAnuncios = {}">Limpar seleção</button>
            <p class="w-full text-xs texto-2" x-text="previaAcao()"></p>
        </div>
    </section>

    {{-- ===================== Produtos ===================== --}}
    <section x-show="aba === 'produtos'" class="space-y-3">
        <p class="text-sm texto-2">O custo de cada SKU é a base da margem: todo anúncio com o mesmo SKU, em qualquer conta, usa este custo. Edite direto na tabela; salva sozinho.</p>
        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="novoProduto()">+ Produto</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="importarDosAnuncios()" :disabled="!skusSemProduto.length || ocupado" x-text="'Trazer SKUs dos anúncios (' + skusSemProduto.length + ')'"></button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="importarAberto = !importarAberto">Colar planilha…</button>
            <input type="search" class="campo !w-auto min-w-[12rem] !py-1.5 text-sm" placeholder="Buscar SKU ou nome" x-model.debounce.250ms="buscaProduto" aria-label="Buscar produtos">
        </div>
        <form x-show="importarAberto" class="card p-3 space-y-2" @submit.prevent="importarTexto()">
            <label class="text-sm rotulo">Uma linha por produto: <b>SKU; nome; custo; custo extra</b> (também aceita tabulação, copiando do Excel ou Google Planilhas)</label>
            <textarea class="campo font-mono text-xs" rows="6" x-model="textoImportar" placeholder="CAN-AZ-01; Caneca azul; 14,90; 2,50"></textarea>
            <button type="submit" class="btn !py-1.5 text-sm" :disabled="ocupado">Importar</button>
        </form>
        <div class="mk-tab-wrap" style="max-height: 70vh">
            <table class="mk-tab">
                <thead><tr>
                    <th><input type="checkbox" :checked="todasMarcadas(produtosFiltrados, selProdutos)" @change="marcarTodas(produtosFiltrados, selProdutos, $event.target.checked)" aria-label="Marcar todos os produtos"></th>
                    <th>SKU</th><th>Nome</th><th class="num">Custo (R$)</th><th class="num">Custo extra (R$)</th><th class="num">Anúncios</th>
                </tr></thead>
                <tbody>
                    <template x-for="p in produtosFiltrados" :key="p.id || p.sku">
                        <tr :class="{ sel: selProdutos[p.id] }">
                            <td><input type="checkbox" x-model="selProdutos[p.id]" :disabled="!p.id" :aria-label="'Marcar ' + p.sku"></td>
                            <td><input class="campo font-mono" x-model.lazy="p.sku" :readonly="!!p.id" @change="salvarProduto(p)" aria-label="SKU"></td>
                            <td><input class="campo min-w-[14rem]" x-model.lazy="p.nome" @change="salvarProduto(p)" aria-label="Nome"></td>
                            <td><input type="number" step="0.01" min="0" class="campo w-28 text-right" x-model.lazy.number="p.custo" @change="salvarProduto(p)" aria-label="Custo"></td>
                            <td><input type="number" step="0.01" min="0" class="campo w-28 text-right" x-model.lazy.number="p.custo_extra" @change="salvarProduto(p)" aria-label="Custo extra"></td>
                            <td class="num" x-text="p.anuncios || 0"></td>
                        </tr>
                    </template>
                    <tr x-show="!produtosFiltrados.length"><td colspan="6" class="texto-2 text-sm p-4">Nenhum produto ainda. Traga os SKUs dos anúncios ou cole uma planilha.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="mk-barra" x-show="nSel(selProdutos)" x-transition>
            <b class="text-sm" x-text="nSel(selProdutos) + (nSel(selProdutos) === 1 ? ' produto' : ' produtos')"></b>
            <input type="number" step="0.01" min="0" class="campo w-28" x-model="custoMassa" placeholder="custo R$" aria-label="Novo custo">
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="custoEmMassa('custo')" :disabled="ocupado || custoMassa === ''">Definir custo</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="custoEmMassa('custo_extra')" :disabled="ocupado || custoMassa === ''">Definir custo extra</button>
            <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" style="color:#f87171" @click="removerProdutos()" :disabled="ocupado">Excluir</button>
            <button type="button" class="link-prim text-sm ml-auto" @click="selProdutos = {}">Limpar seleção</button>
        </div>
    </section>

    {{-- ===================== Pedidos ===================== --}}
    <section x-show="aba === 'pedidos'" class="space-y-3">
        <div class="mk-filtros">
            <select class="campo" x-model="filtroPedidos" aria-label="Conta">
                <option value="">Todas as contas</option>
                <template x-for="c in contas" :key="c.id"><option :value="String(c.id)" x-text="c.canal_nome + ' · ' + c.nome"></option></template>
            </select>
            <span class="text-xs texto-2 self-center" x-text="pedidosFiltrados.length + ' pedidos nos últimos ' + dados.dias_pedidos + ' dias · ' + dinheiro(pedidosFiltrados.reduce((s, p) => s + p.total, 0))"></span>
        </div>
        <div class="mk-tab-wrap" style="max-height: 70vh">
            <table class="mk-tab">
                <thead><tr><th>Data</th><th>Conta</th><th>Pedido</th><th>Itens</th><th class="num">Total</th><th class="num">Tarifas</th><th>Status</th></tr></thead>
                <tbody>
                    <template x-for="p in pedidosFiltrados.slice(0, 300)" :key="p.id">
                        <tr>
                            <td class="text-xs whitespace-nowrap" x-text="p.data ? new Date(p.data).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''"></td>
                            <td class="text-xs"><span class="mk-canal" :style="'--c:' + cor(conta(p.conta_id)?.canal)" x-text="conta(p.conta_id)?.nome"></span></td>
                            <td class="text-xs font-mono" x-text="p.externo_id"></td>
                            <td class="text-xs max-w-[20rem]"><template x-for="(i, k) in p.itens" :key="k"><div class="truncate" x-text="i.qtd + '× ' + (i.titulo || i.sku || i.externo_id)"></div></template><span class="texto-2" x-show="!p.itens.length">—</span></td>
                            <td class="num" x-text="dinheiro(p.total)"></td>
                            <td class="num" x-text="p.tarifas === null ? '—' : dinheiro(p.tarifas)"></td>
                            <td><span class="mk-st" x-text="p.status"></span></td>
                        </tr>
                    </template>
                    <tr x-show="!pedidosFiltrados.length"><td colspan="7" class="texto-2 text-sm p-4">Nenhum pedido no período.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script src="{{ asset('js/marketplaces.js') }}?v={{ @filemtime(public_path('js/marketplaces.js')) }}"></script>
@endsection
