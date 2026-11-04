{{-- Abas só da área Negócios, incluídas em areas/carteira/index (modo "negocios"):
     Clientes (no estado da área), Loja e Marketplaces (atalhos com resumo) e Equipe (partials/equipe, js/equipe.js). --}}

{{-- ================= CLIENTES ================= --}}
<section class="wf-quadro" x-show="aba === 'clientes'" x-cloak :style="{ minHeight: alturaQuadro('clientes') + 'px' }">
    <x-carteira.item sec="'clientes'" bid="'cli-lista'" titulo="Clientes" :minw="420" :minh="300">
        <x-slot name="acoes">
            <button type="button" class="btn-sec !py-1" @click="zapAbrir(null)" title="Mandar uma mensagem pronta para os clientes da busca">💬 Mensagem para a lista</button>
            <button type="button" class="btn-sec !py-1" @click="importarClientesLoja()" title="Traz quem comprou na sua loja virtual">Trazer da loja</button>
            <button type="button" class="btn-sec !py-1" x-show="cfg.zapApi" @click="zo.painel = true; zapEstado()" title="Conversas e configuração do WhatsApp oficial (API da Meta)">
                WhatsApp oficial <span x-show="zapTotalNaoLidas()" class="ml-1 rounded-full px-1.5 text-[11px] font-bold" style="background:#25d366;color:#fff" x-text="zapTotalNaoLidas()"></span>
            </button>
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
                                    <button type="button" class="btn-sec !py-0.5 text-xs whitespace-nowrap" :title="zo.configurado ? 'Conversa pelo WhatsApp oficial' : 'Mensagem pronta no WhatsApp'" x-show="zapNumero(c)" @click="zapAbrir(c)" x-text="(zo.configurado ? '💬 Conversa' : '💬 Mensagem')"></button><span x-show="zapNaoLidas(c)" class="ml-1 rounded-full px-1.5 text-[10px] font-bold" style="background:#25d366;color:#fff" x-text="zapNaoLidas(c)" :title="zapNaoLidas(c) + ' mensagem(ns) nova(s)'"></span>
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
                <b class="flex-1 min-w-0 break-words" :title="zapCliente()?.nome || ''" x-text="zapCliente() ? 'Mensagem para ' + zapCliente().nome : 'Mensagem para ' + zap.fila.length + ' clientes da busca'"></b>
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
            {{-- WhatsApp oficial configurado: conversa e resposta pela API (texto em 24 h, modelo aprovado fora disso) --}}
            <template x-if="zapCliente() && zo.configurado">
                <div class="space-y-2">
                    <div id="zap-fio" class="rounded-lg p-2 space-y-1.5 overflow-auto text-sm" style="background:var(--superficie-2);max-height:16rem">
                        <p class="text-xs texto-2" x-show="!zo.msgs.length">Nenhuma mensagem com este número pelo WhatsApp oficial ainda.</p>
                        <template x-for="m in zo.msgs" :key="m.id">
                            <div class="flex" :class="m.direcao === 'out' ? 'justify-end' : 'justify-start'">
                                <div class="rounded-lg px-2 py-1 max-w-[85%]" :style="m.direcao === 'out' ? 'background:#d9fdd3;color:#111' : 'background:var(--superficie);'">
                                    <p class="whitespace-pre-wrap break-words" x-text="m.texto"></p>
                                    <p class="text-[10px] opacity-70 text-right" x-text="new Date(m.em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) + ' ' + zapStatus(m)" :title="m.erro || ''"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <template x-if="!zo.conversa || zapJanela()">
                        <div class="space-y-1">
                            <p class="text-xs texto-2" x-show="zapJanela()" x-text="'Janela aberta: dá para mandar texto livre até ' + zapJanelaAte() + '.'"></p>
                            <p class="text-xs texto-2" x-show="!zo.conversa">Este número ainda não escreveu: o WhatsApp só aceita texto livre depois que o cliente manda mensagem. Se for recusado, use um modelo aprovado.</p>
                            <button type="button" class="btn w-full" :disabled="zo.enviando" @click="zapResponder(false)" x-text="zo.enviando ? 'Enviando…' : 'Enviar pelo WhatsApp oficial'"></button>
                        </div>
                    </template>
                    <template x-if="zo.conversa && !zapJanela()">
                        <div class="space-y-1.5 rounded-lg p-2" style="border:1px solid var(--linha)">
                            <p class="text-xs">Já passaram 24 horas desde a última mensagem do cliente: o WhatsApp só deixa mandar um <b>modelo aprovado</b>.</p>
                            <p class="text-xs text-red-500" x-show="zo.erroModelos" x-text="zo.erroModelos"></p>
                            <p class="text-xs texto-2" x-show="zo.modelos && !zo.modelos.length && !zo.erroModelos">Nenhum modelo aprovado na conta. Crie em WhatsApp Manager › Modelos de mensagem.</p>
                            <template x-if="zo.modelos && zo.modelos.length">
                                <div class="space-y-1.5">
                                    <select class="campo text-sm" :value="zo.modelo" @change="zapEscolherModeloOficial($event.target.value)" aria-label="Modelo aprovado">
                                        <template x-for="m in zo.modelos" :key="m.nome + m.idioma"><option :value="m.nome" x-text="m.nome + ' (' + m.idioma + ')'" :selected="m.nome === zo.modelo"></option></template>
                                    </select>
                                    <p class="text-xs texto-2 whitespace-pre-wrap" x-text="zapModeloOficial()?.texto"></p>
                                    <template x-for="(p, i) in zo.params" :key="i">
                                        <input class="campo !py-1 text-sm" x-model="zo.params[i]" :placeholder="'Campo ' + (i + 1) + ' do modelo'" maxlength="1000" :aria-label="'Campo ' + (i + 1)">
                                    </template>
                                    <button type="button" class="btn w-full" :disabled="zo.enviando" @click="zapResponder(true)" x-text="zo.enviando ? 'Enviando…' : 'Enviar modelo aprovado'"></button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p class="text-xs text-red-500" x-show="zo.erro" x-text="zo.erro"></p>
                    <a class="link-prim text-xs" :href="zapLink(zapCliente())" target="_blank" rel="noopener" @click="zapEnviou(zapCliente())">ou abrir no WhatsApp do celular (wa.me)</a>
                </div>
            </template>
            <template x-if="zapCliente() && !zo.configurado">
                <div class="space-y-2">
                    <p class="text-sm rounded-lg p-2 whitespace-pre-wrap" style="background:var(--superficie-2)" x-text="zapPreencher(zap.texto, zapCliente())"></p>
                    <a class="btn w-full text-center" :href="zapLink(zapCliente())" target="_blank" rel="noopener" @click="zapEnviou(zapCliente())">Abrir no WhatsApp</a>
                    <p class="text-xs texto-2" x-show="cfg.zapApi && zo.carregado">Para receber e responder as conversas aqui mesmo, configure o <button type="button" class="link-prim" @click="zap.aberto = false; zo.painel = true">WhatsApp oficial</button>.</p>
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

    <style>.zap-cv:hover { background: var(--superficie-2); }</style>
    {{-- WhatsApp oficial (ZapController): conversas recebidas pelo webhook e configuração do número (só o dono) --}}
    <template x-teleport="body">
    <div x-show="zo.painel" x-cloak class="fixed inset-0 z-[1200] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="zo.painel = false" @click.self="zo.painel = false">
        <div class="card p-4 w-full max-w-xl max-h-[90vh] overflow-auto space-y-3 text-sm" role="dialog" aria-modal="true" aria-label="WhatsApp oficial">
            <div class="flex items-center gap-2">
                <div class="flex-1 min-w-0"><b>WhatsApp oficial <span class="texto-2 font-normal break-words" x-show="zo.config?.numero" x-text="'· ' + zo.config?.numero"></span></b><p class="text-xs texto-2">Receba e responda as mensagens dos clientes aqui, pelo número da empresa (API oficial da Meta).</p></div>
                <button type="button" class="btn-sec !py-0.5 text-xs" @click="zo.painel = false">✕ Fechar</button>
            </div>
            <p class="text-xs texto-2" x-show="!zo.configurado">Ainda não configurado: as mensagens prontas continuam abrindo o WhatsApp do celular (wa.me). <span x-show="zo.falta.length" x-text="'Falta: ' + zo.falta.join(', ') + '.'"></span></p>
            <p class="text-xs" style="color:#f59e0b" x-show="zo.configurado && zo.falta.length" x-text="'Falta ' + zo.falta.join(', ') + ': sem isso o webhook recusa as mensagens recebidas.'"></p>

            <template x-if="zo.configurado">
                <div class="space-y-1">
                    <p class="font-medium">Conversas</p>
                    <p class="text-xs texto-2" x-show="!zo.conversas.length">Nenhuma conversa ainda. Quando alguém escrever para o número, aparece aqui (e no 💬 do cliente com o mesmo telefone).</p>
                    <template x-for="cv in zo.conversas" :key="cv.id">
                        <button type="button" class="w-full text-left flex items-center gap-2 rounded-lg px-2 py-1.5 zap-cv" @click="zapAbrirConversa(cv)">
                            <span class="flex-1 min-w-0">
                                <span class="font-medium block truncate" :title="(cv.nome ? cv.nome + ' · ' : '') + '+' + cv.telefone" x-text="cv.nome || '+' + cv.telefone"></span>
                                <span class="block text-xs texto-2 truncate" x-text="cv.previa || ''"></span>
                            </span>
                            <span class="text-[11px] texto-2 whitespace-nowrap" x-text="cv.ultima_em ? new Date(cv.ultima_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''"></span>
                            <span x-show="cv.nao_lidas" class="rounded-full px-1.5 text-[11px] font-bold" style="background:#25d366;color:#fff" x-text="cv.nao_lidas"></span>
                        </button>
                    </template>
                </div>
            </template>

            <template x-if="zo.carregado && zo.eDono">
                <details class="rounded-lg p-2" style="border:1px solid var(--linha)" :open="!zo.configurado">
                    <summary class="cursor-pointer font-medium">Configuração (WhatsApp Cloud API da Meta)</summary>
                    <form class="space-y-2 mt-2" @submit.prevent="zapSalvarConfig()">
                        <label class="block text-xs">Identificação do número de telefone (Phone number ID)
                            <input class="campo !py-1" x-model="zo.form.phone_number_id" inputmode="numeric" maxlength="30" required>
                        </label>
                        <label class="block text-xs">Identificação da conta do WhatsApp Business (WABA ID, para os modelos aprovados)
                            <input class="campo !py-1" x-model="zo.form.waba_id" inputmode="numeric" maxlength="30">
                        </label>
                        <label class="block text-xs">Token de acesso <span class="texto-2" x-show="zo.configurado">(em branco mantém o guardado)</span>
                            <input class="campo !py-1" type="password" x-model="zo.form.access_token" autocomplete="off" maxlength="1000" :required="!zo.configurado">
                        </label>
                        <label class="block text-xs">Chave secreta do app da Meta <span class="texto-2" x-text="zo.config?.temSegredo ? '(guardada; em branco mantém)' : (zo.config?.segredoDoSite ? '(opcional: o site já tem uma)' : '(para conferir o webhook)')"></span>
                            <input class="campo !py-1" type="password" x-model="zo.form.app_secret" autocomplete="off" maxlength="64">
                        </label>
                        <label class="block text-xs">Token de verificação do webhook <span class="texto-2">(em branco: o site cria um)</span>
                            <input class="campo !py-1" x-model="zo.form.verify_token" maxlength="100" autocomplete="off">
                        </label>
                        <div class="text-xs rounded-lg p-2 space-y-1" style="background:var(--superficie-2)">
                            <p>No app da Meta, em WhatsApp › Configuração › Webhook, cole:</p>
                            <p>URL de callback: <code class="break-all" x-text="zo.webhook"></code> <button type="button" class="link-prim" @click="zapCopiar(zo.webhook)">copiar</button></p>
                            <p x-show="zo.config?.verify_token">Token de verificação: <code x-text="zo.config?.verify_token"></code> <button type="button" class="link-prim" @click="zapCopiar(zo.config.verify_token)">copiar</button></p>
                            <p>e assine o campo <b>messages</b>. <span x-show="zo.config?.ultimoAviso" x-text="'Último aviso recebido: ' + new Date(zo.config?.ultimoAviso).toLocaleString('pt-BR')"></span></p>
                        </div>
                        <p class="text-xs text-red-500" x-show="zo.erroCfg" x-text="zo.erroCfg"></p>
                        <p class="text-xs" x-show="zo.okCfg" x-text="zo.okCfg"></p>
                        <div class="flex gap-2">
                            <button class="btn" :disabled="zo.salvando" x-text="zo.salvando ? 'Salvando…' : 'Salvar'"></button>
                            <button type="button" class="btn-sec" x-show="zo.configurado" @click="zapRemoverConfig()">Desligar</button>
                        </div>
                    </form>
                </details>
            </template>
            <p class="text-xs texto-2" x-show="zo.carregado && !zo.eDono && !zo.configurado">Só o dono desta área Negócios pode configurar o WhatsApp oficial.</p>
        </div>
    </div>
    </template>
    @include('areas.carteira.partials.blocos', ['sec' => 'clientes'])
</section>

{{-- páginas comuns de Negócios (Produtos, Loja e marketplaces, Equipe): largura limitada e grade que se ajusta,
     para não esticar os cartões em telas muito largas --}}
<style>
    .ng-pagina { max-width: 76rem; }
    .ng-cards { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 24rem), 1fr)); max-width: 76rem; }
    .ng-cards > .card { display: flex; flex-direction: column; }
    .ng-acoes { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: auto; padding-top: 1rem; }
    .ng-num { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(7.5rem, 1fr)); margin-top: .75rem; }
    .ng-produtos { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) { .ng-produtos.com-form { grid-template-columns: minmax(0, 24rem) minmax(0, 1fr); } }
    .ng-campo { display: block; font-size: .85rem; }
    .ng-campo > span { display: block; color: var(--tinta-2); font-size: .75rem; margin-bottom: .2rem; }
    .ng-dois { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); }
    .ng-foto { width: 2.5rem; height: 2.5rem; border-radius: .4rem; object-fit: cover; background: var(--superficie-2); border: 1px solid var(--linha); display: block; flex-shrink: 0; }
    .ng-tabela td.num, .ng-tabela th.num { text-align: right; white-space: nowrap; }
    .ng-tabela td { vertical-align: middle; }
    /* com o formulário aberto ao lado, a lista mostra só o essencial (sem rolar para o lado) */
    .ng-produtos.com-form .ng-opc { display: none; }
    @media (max-width: 639px) { .ng-tabela .ng-opc { display: none; } .ng-tabela td, .ng-tabela th { padding-left: .3rem; padding-right: .3rem; } }
</style>

{{-- ================= PRODUTOS ================= --}}
<section x-show="aba === 'produtos'" x-cloak :style="estiloPagina()">
    @include('areas.negocios.partials.produtos')
</section>

{{-- ================= LOJA E MARKETPLACES ================= --}}
<section x-show="aba === 'loja'" x-cloak class="ng-cards" :style="estiloPagina()">
    @php
        $donoLoja = request()->attributes->get('wf_dono');
        $brlNg = fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
    @endphp
    <div class="card p-6">
        <p class="titulo text-xl">🏪 Loja virtual</p>
        @if (! empty($lojaResumo))
            <p class="font-semibold mt-1">{{ $lojaResumo['nome'] }}</p>
            <p class="text-xs texto-2 break-all">{{ $lojaResumo['url'] }}</p>
            <p class="text-sm mt-3">{{ $lojaResumo['produtos'] }} {{ $lojaResumo['produtos'] === 1 ? 'produto' : 'produtos' }} ·
                {{ $lojaResumo['abertos'] }} {{ $lojaResumo['abertos'] === 1 ? 'pedido esperando' : 'pedidos esperando' }} ·
                {{ $lojaResumo['publicada'] ? 'aberta ao público' : 'fechada (só você vê)' }}</p>
            <div class="ng-acoes">
                <a class="btn !py-1.5 !px-3 text-sm" href="{{ route('carteira.loja') }}">Pedidos e produtos</a>
                <a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('carteira.loja', ['aba' => 'aparencia']) }}">Editar loja</a>
                <a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ $lojaResumo['url'] }}" target="_blank" rel="noopener">{{ $lojaResumo['publicada'] ? 'Ver a loja' : 'Ver prévia' }} ↗</a>
                @include('areas.carteira.partials.loja-excluir', ['loja' => $lojaObj, 'abertos' => $lojaResumo['abertos']])
            </div>
        @else
            <p class="text-sm texto-2 mt-1">Monte uma página de vendas com seus produtos, cupons e entrega. O cliente paga por Pix (ou cartão no Mercado Pago) e o pedido aparece aqui.</p>
            <div class="ng-acoes"><a class="btn !py-1.5 !px-3 text-sm" href="{{ route('carteira.loja') }}">Criar minha loja</a></div>
        @endif
        @if ($donoLoja) <p class="text-xs texto-2 mt-2">A loja é de cada conta: estes botões abrem a sua.</p> @endif
    </div>

    @if (! empty($lojaResumo) || ($lojaSaldo['disponivel'] ?? 0) > 0 || ($lojaSaldo['em_resgate'] ?? 0) > 0)
        <div class="card p-6">
            <p class="titulo text-xl">💰 Saldo da loja</p>
            <p class="text-sm texto-2 mt-1">O Pix dos pedidos cai na conta do Work Flower e vira saldo depois de conferido. Resgate para a sua chave Pix quando quiser.</p>
            <div class="ng-num">
                <div class="wf-stat"><p class="text-xs texto-2">Disponível</p><p class="titulo text-lg">{{ $brlNg($lojaSaldo['disponivel']) }}</p></div>
                <div class="wf-stat"><p class="text-xs texto-2">A confirmar</p><p class="titulo text-lg">{{ $brlNg($lojaSaldo['a_confirmar']) }}</p></div>
                <div class="wf-stat"><p class="text-xs texto-2">Em resgate</p><p class="titulo text-lg">{{ $brlNg($lojaSaldo['em_resgate']) }}</p></div>
            </div>
            <div class="ng-acoes"><a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('carteira.loja', ['aba' => 'pix']) }}">Resgatar e ver extrato</a></div>
        </div>
    @endif

    <div class="card p-6">
        <p class="titulo text-xl">📦 Produtos</p>
        <p class="text-sm texto-2 mt-1">O catálogo é um só: os produtos de Negócios são os mesmos da loja, com custo, margem e estoque.</p>
        <div class="ng-acoes"><button type="button" class="btn-sec !py-1.5 !px-3 text-sm" @click="ir('produtos')">Abrir o catálogo</button></div>
    </div>

    <div class="card p-6">
        <p class="titulo text-xl">🛒 Marketplaces</p>
        <p class="text-sm texto-2 mt-1">Contas, anúncios, custos, comissões e margem do Mercado Livre, Shopee e Amazon.</p>
        <div class="ng-acoes"><a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('carteira.mkt', $donoLoja ? ['de' => $donoLoja] : []) }}">Abrir marketplaces</a></div>
    </div>

    <div class="card p-6">
        <p class="titulo text-xl">📧 E-mails</p>
        <p class="text-sm texto-2 mt-1">Sua caixa (Titan, Gmail ou outra por IMAP) lida e separada em clientes, financeiro, promoções e outros.</p>
        <div class="ng-acoes"><a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('emails') }}">Abrir e-mails</a></div>
    </div>
</section>

{{-- ================= EQUIPE ================= --}}
<section x-show="aba === 'equipe'" x-cloak class="ng-pagina" :style="estiloPagina()">
    @include('areas.negocios.partials.equipe')
</section>

{{-- ================= CALLS E INTEGRAÇÕES (Discord) ================= --}}
@include('areas.negocios.partials.integracoes')
