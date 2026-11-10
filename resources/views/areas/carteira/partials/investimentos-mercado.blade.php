{{-- Carteira › Investimentos: renda passiva, filtro de ativos (FIIs, ações, ETFs…) e simulador.
     Dados da brapi.dev com cache no servidor (App\Support\Mercado); a lógica fica em public/js/carteira.js. --}}
@php
    $avisoInvest = 'Não é recomendação de investimento. Dados da brapi.dev com atraso e podem ter erros; confira antes de decidir.';
@endphp
{{-- classes próprias: public/css/tailwind.css é gerado e não tem os tamanhos arbitrários abaixo --}}
<style>
    .inv-duo { display: grid; gap: 1rem; }
    @media (min-width: 768px) { .inv-duo { grid-template-columns: 1.4fr 1fr; } }
    .inv-simlinha { display: grid; gap: .5rem; align-items: center; grid-template-columns: auto minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr) auto; }
    .inv-barras { height: 7rem; }
    .inv-graf { height: 12rem; }
    .inv-nome { max-width: 14rem; }
    .inv-mes { font-size: 10px; }
    .inv-pb { padding-bottom: .5rem; }
    /* projeção automática (cartões "Resultado", "Novo ativo" e "Meus ativos") */
    .inv-mini { font-size: 11px; line-height: 1.4; }
    .inv-projtotal { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .5rem; font-size: .8rem; padding-top: .6rem; border-top: 1px solid var(--linha); }
    .inv-anos { width: auto; padding: .15rem 1.6rem .15rem .5rem; font-size: .8rem; }
    .inv-previa { border: 1px dashed var(--prim-linha, var(--linha)); background: var(--prim-suave); border-radius: .75rem; padding: .6rem .7rem; }
    .inv-projlinha > td { background: color-mix(in srgb, var(--prim) 5%, transparent); }
    /* colunas pelo espaço do cartão (não da janela): o cartão pode ser estreito ou largo */
    .inv-proj { display: grid; gap: 1rem; padding: .35rem 0; white-space: normal; grid-template-columns: repeat(auto-fit, minmax(min(100%, 19rem), 1fr)); }
    .inv-params { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(6.5rem, 1fr)); align-items: end; }
    .inv-projtab td, .inv-projtab th { padding: .25rem .4rem; font-size: .75rem; }
    .inv-projgraf { height: 4.5rem; background: var(--superficie-2); border-radius: .5rem; }
    .mini-btn.ativo { color: var(--prim-forte); background: var(--prim-suave); }
</style>

{{-- ================= renda passiva e alocação ================= --}}
<x-carteira.item sec="'invest'" bid="'inv-renda'" titulo="Renda passiva, indicadores e alocação" :minw="420" :minh="260">
    <x-slot name="acoes"><button type="button" class="btn-sec" @click="carregarMercado()" :disabled="mk.carregando" x-text="mk.carregando ? 'Buscando…' : 'Atualizar dados'"></button></x-slot>
    <div class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="wf-stat"><p class="text-xs texto-2">Renda estimada / mês</p><p class="titulo text-lg" x-text="brl(rendaTotal())"></p></div>
            <div class="wf-stat"><p class="text-xs texto-2">Renda estimada / ano</p><p class="titulo text-lg" x-text="brl(rendaTotal() * 12)"></p></div>
            <div class="wf-stat"><p class="text-xs texto-2">Yield on cost</p><p class="titulo text-lg" x-text="pc(yocTotal())"></p></div>
            <div class="wf-stat"><p class="text-xs texto-2">Proventos nos últimos 12 meses</p><p class="titulo text-lg" x-text="brl(proventos12m())"></p></div>
        </div>

        <p x-show="!estado.invest.length" class="text-sm texto-2">Adicione ativos da bolsa (ex.: MXRF11, PETR4) para ver dividendos, DY e P/VP.</p>

        <div x-show="estado.invest.length" class="inv-duo">
            <div class="overflow-auto">
                <table class="wf-tabela">
                    <thead><tr><th>Ativo</th><th>Setor</th><th title="Dividend yield dos últimos 12 meses">DY 12m</th><th title="Preço / valor patrimonial">P/VP</th><th>Último provento</th><th title="Média dos últimos 12 meses; você pode informar à mão">Renda/cota·mês</th><th>Renda/mês</th></tr></thead>
                    <tbody>
                        <template x-for="a in estado.invest" :key="a.id">
                            <tr>
                                <td><p class="font-medium" x-text="a.nome"></p><p class="text-xs texto-2" x-text="a.tipo"></p></td>
                                <td class="text-xs" x-text="mkDe(a)?.setor || '—'"></td>
                                <td x-text="mkDe(a)?.dy != null ? pc(mkDe(a).dy) : '—'"></td>
                                <td x-text="fmtN(mkDe(a)?.pvp)"></td>
                                <td class="text-xs">
                                    <template x-if="mkDe(a)?.ultimoDividendo">
                                        <span><span x-text="'R$ ' + fmtN(mkDe(a).ultimoDividendo.valor, 4)"></span><br>
                                            <span class="texto-2" x-text="mkDe(a).ultimoDividendo.tipo + ' · paga ' + dataBR(mkDe(a).ultimoDividendo.pag)"></span></span>
                                    </template>
                                    <span x-show="!mkDe(a)?.ultimoDividendo" class="texto-2">—</span>
                                </td>
                                <td><input type="number" min="0" step="any" class="campo wf-num" :value="a.rendaCota || ''" :placeholder="fmtN(mkDe(a)?.rendaMensalCota, 4)"
                                           @change="setRendaCota(a, $event.target.value)" aria-label="Renda por cota ao mês"></td>
                                <td x-text="brl(rendaAtivo(a))"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="space-y-4">
                <div>
                    <p class="text-xs texto-2 mb-2">Proventos por mês de pagamento (com a quantidade de hoje; os 3 últimos já anunciados)</p>
                    <div class="flex items-end gap-1 inv-barras" role="img" aria-label="Proventos por mês">
                        <template x-for="m in proventosPorMes()" :key="m.k">
                            <div class="flex-1 flex flex-col items-center justify-end h-full" :title="m.nome + '/' + m.ano + ': ' + brl(m.v)">
                                <div class="w-full rounded-t" :style="`height:${Math.max(m.pct, m.v > 0 ? 3 : 0)}%;background:var(--prim);opacity:${m.futuro ? .45 : 1}`"></div>
                                <span class="inv-mes texto-2 mt-1" x-text="m.nome"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <p class="text-xs texto-2">Por setor</p>
                        <template x-for="x in alocacaoPor('setor')" :key="x.t">
                            <div><div class="flex justify-between text-xs gap-2"><span class="truncate" x-text="x.t"></span><span class="texto-2" x-text="pc(x.pct)"></span></div>
                                <div class="wf-hbar mt-0.5"><span :style="`width:${x.pct}%`"></span></div></div>
                        </template>
                    </div>
                    <div class="space-y-1.5">
                        <p class="text-xs texto-2">Por ativo</p>
                        <template x-for="x in alocacaoPor('ativo')" :key="x.t">
                            <div><div class="flex justify-between text-xs gap-2"><span class="truncate" x-text="x.t"></span><span class="texto-2" x-text="pc(x.pct)"></span></div>
                                <div class="wf-hbar mt-0.5"><span :style="`width:${x.pct}%`"></span></div></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <p class="erro" x-text="mk.erro"></p>
        <p x-show="mk.token === false" class="text-xs texto-2">Sem a chave da brapi (BRAPI_TOKEN) só aparecem preço, segmento e liquidez da maioria dos ativos. Você pode informar a renda por cota à mão.</p>
        <p class="text-xs texto-2">{{ $avisoInvest }}</p>
    </div>
</x-carteira.item>

{{-- ================= filtro de ativos ================= --}}
<x-carteira.item sec="'invest'" bid="'inv-filtro'" titulo="Filtro de ativos: FIIs, ações, ETFs e mais" :minw="420" :minh="300">
    <div class="space-y-3">
        <div class="flex flex-wrap gap-1.5">
            <template x-for="(nome, id) in tiposMercado" :key="id">
                <button type="button" class="chip" :class="{ ativo: scr.f.tipo === id }" @click="trocarTipoFiltro(id)" x-text="nome"></button>
            </template>
        </div>
        <div class="grid gap-2 grid-cols-2 md:grid-cols-4">
            <div><label class="rotulo">Busca</label><input class="campo" x-model="scr.f.busca" @keydown.enter="buscarFiltro()" placeholder="Ticker ou nome"></div>
            <div><label class="rotulo">Setor / segmento</label>
                <select class="campo" x-model="scr.f.setor" @change="buscarFiltro()">
                    <option value="">Todos</option>
                    <template x-for="s in scr.setores" :key="s"><option :value="s" x-text="s"></option></template>
                </select></div>
            <div><label class="rotulo">Liquidez diária mínima</label>
                <select class="campo" x-model="scr.f.liq_min" @change="buscarFiltro()">
                    <option value="">Qualquer</option><option value="100000">R$ 100 mil</option><option value="500000">R$ 500 mil</option>
                    <option value="1000000">R$ 1 mi</option><option value="5000000">R$ 5 mi</option><option value="20000000">R$ 20 mi</option>
                </select></div>
            <div><label class="rotulo">Ordenar por</label>
                <select class="campo" x-model="scr.f.ordem" @change="buscarFiltro()">
                    <option value="liquidez">Liquidez</option><option value="dy">Maior DY</option><option value="pvp">Menor P/VP</option>
                    <option value="variacao">Variação do dia</option><option value="preco">Preço</option>
                </select></div>
            <div><label class="rotulo">DY 12m (%)</label>
                <div class="flex gap-1"><input class="campo" inputmode="decimal" x-model="scr.f.dy_min" placeholder="mín."><input class="campo" inputmode="decimal" x-model="scr.f.dy_max" placeholder="máx."></div></div>
            <div><label class="rotulo">P/VP</label>
                <div class="flex gap-1"><input class="campo" inputmode="decimal" x-model="scr.f.pvp_min" placeholder="mín."><input class="campo" inputmode="decimal" x-model="scr.f.pvp_max" placeholder="máx."></div></div>
            <div class="col-span-2 flex items-end gap-2">
                <button type="button" class="btn" @click="buscarFiltro()" :disabled="scr.carregando" x-text="scr.carregando ? 'Filtrando…' : 'Filtrar'"></button>
                <button type="button" class="btn-sec" @click="limparFiltro()">Limpar</button>
            </div>
        </div>

        <p class="erro" x-text="scr.erro"></p>
        <p class="text-xs texto-2" x-show="scr.feito">
            <span x-text="scr.total + (scr.total === 1 ? ' ativo' : ' ativos')"></span>
            <span x-show="scr.semDados" x-text="' · ' + scr.semDados + ' sem DY/P/VP ficaram de fora'"></span>
            <span x-show="scr.pendentes" x-text="' · buscando indicadores de mais ' + scr.pendentes"></span>
        </p>
        <p class="text-xs texto-2" x-show="mk.token === false && temIndicadorFiltro()">DY e P/VP precisam da chave da brapi (BRAPI_TOKEN); sem ela, o filtro por esses indicadores só encontra os poucos ativos abertos (ex.: PETR4, VALE3, ITUB4).</p>

        <div class="overflow-auto" x-show="scr.itens.length">
            <table class="wf-tabela">
                <thead><tr><th>Ativo</th><th>Setor</th><th>Preço</th><th>Dia</th><th>DY 12m</th><th>P/VP</th><th>Último provento</th><th>Liquidez/dia</th><th></th></tr></thead>
                <tbody>
                    <template x-for="x in scr.itens" :key="x.ticker">
                        <tr>
                            <td>
                                <div class="flex items-center gap-2 min-w-0">
                                    <img x-show="x.logo" :src="x.logo" alt="" class="w-6 h-6 rounded shrink-0" loading="lazy" referrerpolicy="no-referrer">
                                    <div class="min-w-0"><p class="font-medium" x-text="x.ticker"></p><p class="text-xs texto-2 truncate inv-nome" :title="x.nome" x-text="x.nome"></p></div>
                                </div>
                            </td>
                            <td class="text-xs" x-text="x.setor || '—'"></td>
                            <td x-text="brl(x.preco)"></td>
                            <td :style="{ color: cor(x.variacao) }" x-text="pc(x.variacao)"></td>
                            <td x-text="x.dy != null ? pc(x.dy) : '—'"></td>
                            <td x-text="fmtN(x.pvp)"></td>
                            <td class="text-xs" x-text="x.ultimoDividendo ? 'R$ ' + fmtN(x.ultimoDividendo.valor, 4) + ' · ' + dataBR(x.ultimoDividendo.pag) : '—'"></td>
                            <td class="text-xs" x-text="fmtLiq(x.liquidez)"></td>
                            <td>
                                <div class="flex gap-1 whitespace-nowrap">
                                    <button type="button" class="chip" title="Adicionar à carteira já com a projeção automática (1 cota; ajuste depois)" @click="paraCarteira(x)">+ Carteira</button>
                                    <button type="button" class="chip" title="Acompanhar no radar" @click="paraRadar(x)">Radar</button>
                                    <button type="button" class="chip" title="Comparar no simulador" @click="paraSimulador(x)">Simular</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p x-show="scr.feito && !scr.itens.length && !scr.carregando" class="text-sm texto-2">Nenhum ativo com esses filtros.</p>
        <p class="text-xs texto-2">{{ $avisoInvest }} Rentabilidade passada não garante rentabilidade futura.</p>
    </div>
</x-carteira.item>

{{-- ================= simulador ================= --}}
<x-carteira.item sec="'invest'" bid="'inv-simulador'" titulo="Simulador: aportes, dividendos e renda-alvo" :minw="420" :minh="320">
    <div class="space-y-4">
        <div class="grid gap-2 grid-cols-2 md:grid-cols-5">
            <div><label class="rotulo">Valor inicial (R$)</label><input class="campo" inputmode="decimal" x-model="sim.inicial"></div>
            <div><label class="rotulo">Aporte mensal (R$)</label><input class="campo" inputmode="decimal" x-model="sim.mensal"></div>
            <div><label class="rotulo">Prazo (anos)</label><input class="campo" inputmode="decimal" x-model="sim.anos"></div>
            <div><label class="rotulo">Renda-alvo / mês (R$)</label><input class="campo" inputmode="decimal" x-model="sim.alvo"></div>
            <label class="flex items-end gap-2 text-sm inv-pb"><input type="checkbox" x-model="sim.reinvestir"> Reinvestir dividendos</label>
        </div>

        <div class="space-y-2">
            <p class="text-xs texto-2">Compare até 4 ativos ou cenários. Com o ticker e o DY vazio, usa o DY dos últimos 12 meses. O resultado se refaz sozinho enquanto você digita; em "Meus ativos", o botão de projeção traz cada ativo para cá.</p>
            <template x-for="(a, i) in sim.ativos" :key="i">
                <div class="inv-simlinha">
                    <span class="w-3 h-3 rounded-full" :style="`background:${coresSim[i % coresSim.length]}`"></span>
                    <input class="campo" x-model="a.ticker" placeholder="Ticker ou nome (ex.: MXRF11)" aria-label="Ativo">
                    <input class="campo" inputmode="decimal" x-model="a.dy" placeholder="DY % ao ano" aria-label="DY ao ano">
                    <input class="campo" inputmode="decimal" x-model="a.valorizacao" placeholder="Valorização % ao ano" aria-label="Valorização ao ano">
                    <button type="button" class="mini-btn perigo" title="Remover" @click="remSimAtivo(i)">✕</button>
                </div>
            </template>
            <div class="flex gap-2">
                <button type="button" class="btn-sec" x-show="sim.ativos.length < 4" @click="addSimAtivo()">+ Ativo</button>
                <button type="button" class="btn" @click="simular()" :disabled="sim.carregando" x-text="sim.carregando ? 'Simulando…' : 'Simular'"></button>
            </div>
        </div>

        <p class="erro" x-text="sim.erro"></p>
        <template x-for="e in sim.erros" :key="e"><p class="erro" x-text="e"></p></template>

        <div x-show="sim.res.length" class="inv-duo">
            <div>
                <p class="text-xs texto-2 mb-1">Patrimônio ao longo do tempo (tracejado: total aportado) · máx. <span x-text="brl(simGrafico().max)"></span></p>
                <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="w-full rounded inv-graf" style="background:var(--superficie-2)" role="img" aria-label="Gráfico do patrimônio simulado">
                    <polyline :points="simGrafico().aportado" fill="none" stroke="var(--tinta-2)" stroke-width="1.5" stroke-dasharray="4 3" vector-effect="non-scaling-stroke"/>
                    {{-- x-for não funciona dentro de <svg>: até 4 linhas fixas --}}
                    @for ($i = 0; $i < 4; $i++)
                        <polyline x-show="simGrafico().linhas[{{ $i }}]" :points="simGrafico().linhas[{{ $i }}]?.pts || ''" fill="none" stroke="{{ ['#60a5fa', '#f59e0b', '#4ade80', '#f472b6'][$i] }}" stroke-width="2.5" vector-effect="non-scaling-stroke"/>
                    @endfor
                </svg>
            </div>
            <div class="overflow-auto">
                <table class="wf-tabela">
                    <thead><tr><th></th><template x-for="(r, i) in sim.res" :key="i"><th><span class="inline-block w-2 h-2 rounded-full mr-1" :style="`background:${coresSim[i % coresSim.length]}`"></span><span x-text="r.nome"></span></th></template></tr></thead>
                    <tbody>
                        <tr><td class="texto-2">DY / valorização</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="pc(r.dy) + ' / ' + pc(r.valorizacao)"></td></template></tr>
                        <tr><td class="texto-2">Patrimônio final</td><template x-for="(r, i) in sim.res" :key="i"><td class="font-medium" x-text="brl(r.patrimonioFinal)"></td></template></tr>
                        <tr><td class="texto-2">Total aportado</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="brl(r.aportado)"></td></template></tr>
                        <tr><td class="texto-2">Dividendos recebidos</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="brl(r.dividendosRecebidos)"></td></template></tr>
                        <tr><td class="texto-2">Renda no último mês</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="brl(r.rendaMensalFinal)"></td></template></tr>
                        <tr x-show="num(sim.alvo) > 0"><td class="texto-2">Chega à renda-alvo em</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="tempo(r.mesesAteAlvo)"></td></template></tr>
                        <tr x-show="num(sim.alvo) > 0"><td class="texto-2">Patrimônio para a renda-alvo</td><template x-for="(r, i) in sim.res" :key="i"><td x-text="r.patrimonioParaAlvo ? brl(r.patrimonioParaAlvo) : '—'"></td></template></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs texto-2">Simulação com DY e valorização constantes, sem impostos, taxas, inflação ou vacância. {{ $avisoInvest }}</p>
    </div>
</x-carteira.item>
