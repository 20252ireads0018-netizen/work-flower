{{-- Blocos do quadro (todas as áreas): Discord, Notion e Textos rápidos. Lógica em public/js/blocos-integracoes.js
     (métodos bi*); estilo e script em partials/blocos-integracoes-script. Incluído dentro do x-for dos blocos.
     A conexão do Discord e do Notion fica em Negócios › Integrações; aqui só se usa. --}}

{{-- ============================== Discord ============================== --}}
<template x-if="b.tipo === 'discord'">
    <div class="bi" x-init="biIniciar(b)">
        <p class="bi-vazio" x-show="!biUI(b).d && biUI(b).carregando">Conferindo o Discord…</p>
        <p class="bi-erro" x-show="biUI(b).erro" x-text="biUI(b).erro"></p>

        <template x-if="biUI(b).d && !biUI(b).d?.conectado">
            <div class="bi-conectar">
                <span class="bi-logo" style="--c:#5865f2" aria-hidden="true">💬</span>
                <p class="font-semibold text-sm">Conecte o Discord</p>
                <p class="texto-2">Cole o webhook de um canal do seu servidor em Negócios › Integrações. Depois é só escrever aqui e a mensagem chega no canal. O endereço do webhook fica guardado só no servidor.</p>
                <button type="button" class="btn !py-1" @click="biIrIntegracoes()">Conectar em Negócios › Integrações</button>
            </div>
        </template>

        <template x-if="biUI(b).d && biUI(b).d?.conectado">
            <div class="bi-corpo">
                <div class="bi-barra">
                    <div class="min-w-0 flex-1">
                        <p class="bi-titulo truncate" x-text="biUI(b).d?.servidor ? biUI(b).d?.servidor + (biUI(b).d?.canal ? ' · #' + biUI(b).d?.canal : '') : 'Discord conectado'"></p>
                        <p class="texto-2 truncate" x-text="biUI(b).d?.ultimo_envio ? 'Última mensagem: ' + biDataHora(biUI(b).d?.ultimo_envio) : 'Nenhuma mensagem enviada ainda'"></p>
                    </div>
                    <button type="button" class="btn-sec !py-0.5" :class="biUI(b).ver === 'msg' && 'qz-on'" @click="biDcVer(b, 'msg')">Mensagem</button>
                    <button type="button" class="btn-sec !py-0.5" :class="biUI(b).ver === 'canais' && 'qz-on'" @click="biDcVer(b, 'canais')">Canais</button>
                    <button type="button" class="btn-sec !py-0.5" title="Atualizar" aria-label="Atualizar" @click="biUI(b).d = null; biUI(b).canais = null; biCarregar(b)">↻</button>
                </div>
                <p class="bi-status bi-espera" x-show="biUI(b).d?.ultimo_erro" x-text="'Último envio falhou: ' + biUI(b).d?.ultimo_erro"></p>

                {{-- mensagem / embed pelo webhook --}}
                <form class="bi-corpo" x-show="biUI(b).ver === 'msg'" @submit.prevent="biDcEnviar(b)">
                    <input class="campo !py-1 text-xs" x-model="biUI(b).msg.titulo" maxlength="250" placeholder="Título (ex.: Reunião às 15h)" aria-label="Título da mensagem">
                    <textarea class="campo text-xs flex-1 bi-texto" x-model="biUI(b).msg.texto" maxlength="3500" placeholder="Texto da mensagem (opcional)" aria-label="Texto da mensagem"></textarea>
                    <div class="bi-barra">
                        <input class="campo !py-1 text-xs flex-1 min-w-0" type="url" x-model="biUI(b).msg.link" maxlength="500" placeholder="Link (opcional): https://…" aria-label="Link da mensagem">
                        <span class="texto-2" x-text="biDcLimite(b)"></span>
                        <button class="btn !py-0.5" :disabled="!!biUI(b).ocupado" x-text="biUI(b).ocupado === 'enviar' ? 'Enviando…' : 'Enviar'"></button>
                    </div>
                    <p class="texto-2" x-show="biUI(b).nota" x-text="biUI(b).nota" role="status"></p>
                    <div class="bi-rodape">
                        <select class="campo !py-0.5 !text-xs flex-1 min-w-0" x-model="biUI(b).cartao" @change="biDcDoCartao(b)" aria-label="Resumo de um cartão desta aba">
                            <option value="">Resumo de um cartão desta aba…</option>
                            <template x-for="c in biCartoesDaAba(b)" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                        </select>
                        <button type="button" class="btn-sec !py-0.5" :disabled="!!biUI(b).ocupado" @click="biDcTarefas(b)" x-text="biUI(b).ocupado === 'tarefas' ? 'Lendo…' : 'Resumo das tarefas'"></button>
                    </div>
                </form>

                {{-- canais do servidor (precisa do bot) --}}
                <div class="bi-corpo" x-show="biUI(b).ver === 'canais'">
                    <template x-if="!biUI(b).d?.tem_bot">
                        <div class="bi-conectar">
                            <p class="texto-2">Para ver os canais do servidor, adicione o token de um bot em Negócios › Integrações › Discord (opcional; o webhook já basta para mandar mensagens).</p>
                            <button type="button" class="btn-sec !py-1" @click="biIrIntegracoes()">Abrir Integrações</button>
                        </div>
                    </template>
                    <template x-if="biUI(b).d?.tem_bot">
                        <ul class="bi-lista">
                            <li class="bi-vazio" x-show="biUI(b).canais === null">Lendo os canais…</li>
                            <li class="bi-erro" x-show="biUI(b).canaisErro" x-text="biUI(b).canaisErro"></li>
                            <template x-for="c in (biUI(b).canais || [])" :key="c.id">
                                <li class="bi-item">
                                    <span class="bi-ico" aria-hidden="true" x-text="c.tipo === 'voz' ? '🔊' : '#'"></span>
                                    <span class="truncate flex-1" x-text="c.nome"></span>
                                    <span class="bi-pilula" x-text="c.tipo === 'voz' ? 'voz' : 'texto'"></span>
                                    <a class="mini-btn" :href="c.link" target="_blank" rel="noopener" :title="'Abrir ' + c.nome + ' no Discord'" :aria-label="'Abrir ' + c.nome + ' no Discord'">↗</a>
                                </li>
                            </template>
                            <li class="bi-vazio" x-show="biUI(b).canais && !biUI(b).canais.length && !biUI(b).canaisErro">O servidor não tem canais de texto ou voz visíveis para o bot.</li>
                        </ul>
                    </template>
                </div>
            </div>
        </template>
    </div>
</template>

{{-- ============================== Notion ============================== --}}
<template x-if="b.tipo === 'notion'">
    <div class="bi" x-init="biIniciar(b)">
        <p class="bi-vazio" x-show="!biUI(b).d && biUI(b).carregando">Conferindo o Notion…</p>
        <p class="bi-erro" x-show="biUI(b).erro" x-text="biUI(b).erro"></p>

        <template x-if="biUI(b).d && !biUI(b).d?.conectado">
            <div class="bi-conectar">
                <span class="bi-logo" style="--c:#787774" aria-hidden="true">N</span>
                <p class="font-semibold text-sm">Conecte o Notion</p>
                <p class="texto-2">Entre com o Notion (ou cole o token de uma integração interna) em Negócios › Integrações. Depois as páginas compartilhadas com a integração aparecem aqui.</p>
                <button type="button" class="btn !py-1" @click="biIrIntegracoes()">Conectar em Negócios › Integrações</button>
            </div>
        </template>

        <template x-if="biUI(b).d && biUI(b).d?.conectado && !biUI(b).aberto">
            <div class="bi-corpo">
                <form class="bi-barra" @submit.prevent="biNtBuscar(b)">
                    <input class="campo !py-0.5 text-xs flex-1 min-w-0" type="search" x-model="b.dados.q" maxlength="100" placeholder="Buscar página pelo título" aria-label="Buscar no Notion">
                    <button class="btn-sec !py-0.5" :disabled="biUI(b).ocupado === 'buscar'" x-text="biUI(b).ocupado === 'buscar' ? '…' : 'Buscar'"></button>
                </form>
                <p class="texto-2 truncate" x-text="(biUI(b).d?.workspace ? 'Espaço ' + biUI(b).d?.workspace + ' · ' : '') + 'só aparecem as páginas compartilhadas com a integração (no Notion: ••• › Conexões)'"></p>
                <ul class="bi-lista">
                    <li class="bi-vazio" x-show="biUI(b).itens === null || biUI(b).ocupado === 'buscar'">Lendo as páginas…</li>
                    <template x-for="it in (biUI(b).itens || [])" :key="it.id">
                        <li class="bi-item bi-clique" tabindex="0" @click="biNtAbrir(b, it)" @keydown.enter="biNtAbrir(b, it)" title="Clique para abrir aqui">
                            <span class="bi-ico" aria-hidden="true" x-text="it.icone || (it.tipo === 'banco' ? '▦' : '📄')"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium" x-text="it.titulo || 'Sem título'"></p>
                                <p class="texto-2 truncate" x-text="(it.tipo === 'banco' ? 'Banco de dados' : 'Página') + (it.editado ? ' · editado em ' + biDataHora(it.editado) : '')"></p>
                            </div>
                            <div class="bi-acoes">
                                <button type="button" class="mini-btn" title="Trazer para o quadro" aria-label="Trazer para o quadro" :disabled="biUI(b).ocupado === 'importar:' + it.id"
                                        @click.stop="biNtImportar(b, it)">⇲</button>
                                <a class="mini-btn" x-show="it.url" :href="it.url" target="_blank" rel="noopener" @click.stop title="Abrir no Notion" aria-label="Abrir no Notion">↗</a>
                            </div>
                        </li>
                    </template>
                    <li class="bi-vazio" x-show="biUI(b).itens && !biUI(b).itens.length && biUI(b).ocupado !== 'buscar'">Nada encontrado. Compartilhe as páginas com a integração no Notion e busque de novo.</li>
                </ul>
            </div>
        </template>

        {{-- página aberta dentro do bloco --}}
        <template x-if="biUI(b).aberto">
            <div class="bi-corpo">
                <div class="bi-barra">
                    <button type="button" class="btn-sec !py-0.5" @click="biUI(b).aberto = null">← Voltar</button>
                    <p class="truncate font-medium flex-1" x-text="(biUI(b).aberto?.resumo.icone ? biUI(b).aberto?.resumo.icone + ' ' : '') + (biUI(b).aberto?.resumo.titulo || 'Sem título')"></p>
                    <button type="button" class="btn !py-0.5" :disabled="biUI(b).lendo" @click="biNtImportar(b, biUI(b).aberto?.resumo)" title="Vira um cartão desta aba">No quadro</button>
                    <a class="btn-sec !py-0.5" x-show="biUI(b).aberto?.resumo.url" :href="biUI(b).aberto?.resumo.url" target="_blank" rel="noopener" title="Abrir no Notion">↗</a>
                </div>
                <p class="bi-vazio" x-show="biUI(b).lendo">Abrindo a página…</p>
                <p class="bi-erro" x-show="biUI(b).aberto?.erro" x-text="biUI(b).aberto?.erro"></p>
                <template x-if="biUI(b).aberto?.item && biUI(b).aberto?.item.tipo !== 'banco'">
                    <div class="bi-leitura" x-text="biUI(b).aberto?.item.texto || '(página vazia)'"></div>
                </template>
                <template x-if="biUI(b).aberto?.item && biUI(b).aberto?.item.tipo === 'banco'">
                    <div class="bi-leitura !p-0">
                        <table class="gr-tab w-full">
                            <thead><tr><template x-for="(c, i) in (biUI(b).aberto?.item.cab || [])" :key="i"><th class="text-left" x-text="c"></th></template></tr></thead>
                            <tbody>
                                <template x-for="(l, j) in (biUI(b).aberto?.item.linhas || []).slice(0, 100)" :key="j">
                                    <tr><template x-for="(c, i) in l" :key="i"><td x-text="c"></td></template></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>

{{-- ============================== Textos rápidos ============================== --}}
<template x-if="b.tipo === 'textos'">
    <div class="bi" x-init="biIniciar(b)">
        <p class="bi-vazio" x-show="!biTxPronto()">Os textos rápidos ainda estão carregando. Se não aparecerem, recarregue a página.</p>
        <template x-if="biTxPronto()">
            <div class="bi-corpo">
                <div class="bi-barra">
                    <p class="bi-titulo flex-1" :id="'bi-tx-' + b.id" x-text="biTxTotal() + (biTxTotal() === 1 ? ' texto salvo' : ' textos salvos')"></p>
                    <button type="button" role="switch" class="wf-chave" :aria-checked="biTxLigado() ? 'true' : 'false'" :aria-labelledby="'bi-tx-' + b.id"
                            :title="biTxLigado() ? 'Ligado: os atalhos viram texto' : 'Desligado: nenhum atalho é trocado'" @click="biTxLigar(!biTxLigado())">
                        <span class="sr-only" x-text="biTxLigado() ? 'Ligado' : 'Desligado'"></span></button>
                </div>
                <p class="texto-2">Digite o atalho (ex.: <kbd>;ass</kbd>) em qualquer campo do site e ele vira o texto salvo; <kbd>Ctrl</kbd> + <kbd>Z</kbd> desfaz.
                    <span x-show="biTxInstalador()">No Windows inteiro (e-mail, Word, WhatsApp): <a class="link-prim" :href="biTxInstalador()" download>instale o app com os textos rápidos</a>.</span></p>

                <template x-if="!biUI(b).editando">
                    <div class="bi-corpo">
                        <div class="bi-barra">
                            <input class="campo !py-0.5 text-xs flex-1 min-w-0" type="search" x-model="biUI(b).filtro" placeholder="Filtrar" aria-label="Filtrar textos rápidos" data-wf-sem-expansor>
                            <button type="button" class="btn !py-0.5" :disabled="biTxTotal() >= biTxLimite()" @click="biTxNovo(b)">+ Novo texto</button>
                        </div>
                        <ul class="bi-lista">
                            <template x-for="s in biTxLista(b)" :key="s.atalho">
                                <li class="bi-item">
                                    <kbd class="bi-kbd" x-text="s.atalho"></kbd>
                                    <span class="texto-2 truncate flex-1" x-text="biTxPrevia(s)" :title="s.texto"></span>
                                    <div class="bi-acoes">
                                        <button type="button" class="mini-btn" title="Copiar o texto" :aria-label="'Copiar ' + s.atalho" @click="biTxCopiar(s)">⧉</button>
                                        <button type="button" class="mini-btn" title="Editar" :aria-label="'Editar ' + s.atalho" @click="biTxEditar(b, s)">✎</button>
                                        <button type="button" class="mini-btn" title="Apagar" :aria-label="'Apagar ' + s.atalho" @click="biTxApagar(b, s)">×</button>
                                    </div>
                                </li>
                            </template>
                            <li class="bi-vazio" x-show="!biTxLista(b).length" x-text="biUI(b).filtro ? 'Nenhum texto com isso.' : 'Nenhum texto salvo ainda. Crie o primeiro: um atalho curto (;ass) e o texto que entra no lugar.'"></li>
                        </ul>
                        <p class="texto-2"><button type="button" class="link-prim" @click="biTxConfig()">Importar e exportar</button> ficam em Configurações › Atalhos.</p>
                    </div>
                </template>

                <template x-if="biUI(b).editando">
                    <form class="bi-form" data-wf-sem-expansor @submit.prevent="biTxSalvar(b)">
                        <div class="bi-barra">
                            <label class="flex-1 min-w-0"><span class="texto-2">Atalho</span>
                                <input class="campo !py-0.5 text-xs" x-model="biUI(b).ed.atalho" :maxlength="biTxMax('atalho')" placeholder=";ass" autocomplete="off" spellcheck="false"></label>
                            <label class="flex-1 min-w-0"><span class="texto-2">Nome (opcional)</span>
                                <input class="campo !py-0.5 text-xs" x-model="biUI(b).ed.nome" maxlength="60" placeholder="Assinatura do e-mail"></label>
                        </div>
                        <label class="bi-corpo"><span class="texto-2">Texto</span>
                            <textarea class="campo text-xs flex-1 bi-texto" x-model="biUI(b).ed.texto" :maxlength="biTxMax('texto')"></textarea></label>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="v in biTxVars()" :key="v">
                                <button type="button" class="btn-sec !px-1.5 !py-0 text-xs" @click="biTxVar(b, v, $el.closest('form').querySelector('textarea'))" x-text="'{' + v + '}'"></button>
                            </template>
                        </div>
                        <p class="texto-2">{cursor} marca onde o cursor fica depois da troca.</p>
                        <p class="bi-erro" x-show="biUI(b).edErro" x-text="biUI(b).edErro"></p>
                        <div class="bi-barra">
                            <button class="btn !py-0.5 flex-1">Salvar</button>
                            <button type="button" class="btn-sec !py-0.5" @click="biUI(b).editando = false">Cancelar</button>
                        </div>
                    </form>
                </template>
            </div>
        </template>
    </div>
</template>
