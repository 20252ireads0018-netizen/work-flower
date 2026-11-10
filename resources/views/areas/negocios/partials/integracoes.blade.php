{{-- Abas Calls e Integrações da área Negócios (incluídas em partials/secoes). Lógica em public/js/integracoes.js;
     servidor em CallController e IntegracaoController (routes/integracoes.php). Sempre sobre a conta de quem está
     logado: num workflow de outra pessoa, mostram as calls e o Discord da própria conta.
     Largura máxima de 1200px e grade que se ajusta sozinha (uma coluna no celular). --}}
@if (session('notion_ok') || session('notion_erro') || session('discord_ok') || session('discord_erro'))<script>try { sessionStorage.setItem('negocios.aba', 'integracoes'); } catch (e) {}</script>@endif
<script src="{{ asset('js/integracoes.js') }}?v={{ @filemtime(public_path('js/integracoes.js')) }}"></script>
<style>
    .wf-int { max-width: 1200px; margin-inline: auto; }
    .wf-int-grade { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); }
    .wf-int-campos { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); }
    .wf-int-quebra { overflow-wrap: anywhere; word-break: break-word; }
    .wf-int-1linha { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .wf-int ol { list-style: decimal; padding-left: 1.25rem; }
</style>

{{-- ================= CALLS ================= --}}
<section x-show="aba === 'calls'" x-cloak class="wf-int">
<div x-data="wfCalls(@js(url('/calls')), @js(url('/integracoes/discord/canais')))" x-effect="if (aba === 'calls') abrir()" class="space-y-4">
    <div>
        <h2 class="titulo text-xl">📞 Calls</h2>
        <p class="text-sm texto-2">Marque reuniões com a sua equipe. Cada pessoa recebe a call no calendário do Painel, com lembrete no navegador e por e-mail, e o seu Discord recebe o aviso (se estiver ligado em Integrações).</p>
    </div>

    <div class="card p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="titulo text-lg flex-1" x-text="f.id ? 'Editando a call' : 'Marcar uma call'"></h3>
            <button type="button" class="btn-sec !py-1" x-show="f.id" @click="limpar()">✕ Sair da edição</button>
        </div>
        <div class="wf-int-campos">
            <label class="text-sm space-y-1" style="grid-column: 1 / -1"><span class="texto-2 text-xs">Título</span>
                <input class="campo" x-model="f.titulo" maxlength="110" placeholder="Ex.: Reunião de pauta da semana"></label>
            <label class="text-sm space-y-1"><span class="texto-2 text-xs">Dia e hora</span>
                <input class="campo" type="datetime-local" x-model="f.inicio"></label>
            <label class="text-sm space-y-1"><span class="texto-2 text-xs">Duração</span>
                <select class="campo" x-model.number="f.duracao_min">
                    @foreach ([15 => '15 min', 30 => '30 min', 45 => '45 min', 60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h'] as $m => $rotulo)
                        <option value="{{ $m }}">{{ $rotulo }}</option>
                    @endforeach
                </select></label>
            <label class="text-sm space-y-1"><span class="texto-2 text-xs">Lembrete (navegador e e-mail)</span>
                <select class="campo" x-model="f.lembrete_min">
                    <option value="">Sem lembrete</option>
                    @foreach (\App\Http\Controllers\CalendarioController::LEMBRETES as $m)
                        <option value="{{ $m }}">{{ $m === 0 ? 'Na hora' : ($m < 60 ? "$m min antes" : ($m < 1440 ? ($m / 60) . ' h antes' : ($m === 10080 ? '1 semana antes' : ($m / 1440) . ' dia(s) antes'))) }}</option>
                    @endforeach
                </select></label>
        </div>

        <div class="space-y-1">
            <p class="texto-2 text-xs">Onde vai ser</p>
            <div class="flex flex-wrap gap-1.5">
                <template x-for="(nome, id) in plataformas" :key="id">
                    <button type="button" class="btn-sec !py-0.5 text-xs" :class="f.plataforma === id && '!bg-[var(--prim)] !text-[var(--on-prim)]'" :aria-pressed="f.plataforma === id" @click="escolher(id)" x-text="nome"></button>
                </template>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <input class="campo flex-1 min-w-[14rem]" x-model="f.link" maxlength="500" :placeholder="dicaLink()" aria-label="Link da call">
                <button type="button" class="btn-sec" x-show="f.plataforma === 'jitsi'" @click="novoJitsi()" title="Cria outra sala do Jitsi">↻ Gerar outra sala</button>
            </div>
            <p class="text-xs texto-2" x-show="f.plataforma === 'jitsi'">O Jitsi Meet é gratuito e não pede conta: quem tiver o link entra direto.</p>
            <p class="text-xs texto-2" x-show="f.plataforma === 'meet'">Crie a reunião em meet.google.com (Nova reunião › Criar para depois) e cole o link aqui.</p>
            <p class="text-xs texto-2" x-show="f.plataforma === 'zoom'">Agende no Zoom e cole o link de convite (zoom.us/j/…).</p>
            <template x-if="f.plataforma === 'discord'">
                <div class="text-xs texto-2 space-y-1">
                    <template x-if="canaisVoz.length">
                        <div class="flex flex-wrap gap-1.5 items-center"><span>Canais de voz do seu servidor:</span>
                            <template x-for="c in canaisVoz" :key="c.id">
                                <button type="button" class="btn-sec !py-0.5 text-xs wf-int-1linha" style="max-width:14rem" :title="c.nome" :class="f.link === c.link && '!bg-[var(--prim)] !text-[var(--on-prim)]'" @click="f.link = c.link" x-text="'🔊 ' + c.nome"></button>
                            </template>
                        </div>
                    </template>
                    <p x-show="!canaisVoz.length">No Discord, clique com o botão direito no canal de voz › Copiar link, e cole acima. Com o bot ligado em Integrações, os canais aparecem aqui para escolher.</p>
                </div>
            </template>
        </div>

        <div class="space-y-1">
            <p class="texto-2 text-xs">Quem participa (pessoas da sua equipe)</p>
            <div class="flex flex-wrap gap-x-4 gap-y-1" x-show="pessoas.length > 1">
                <template x-for="p in pessoas.filter(p => p.id !== eu)" :key="p.id">
                    <label class="text-sm flex items-center gap-1 wf-int-quebra"><input type="checkbox" :value="p.id" x-model.number="f.participantes"> <span x-text="p.nome"></span></label>
                </template>
            </div>
            <p class="text-sm texto-2" x-show="carregou && pessoas.length <= 1">Ainda não há ninguém na sua equipe. <button type="button" class="link-prim" @click="aba = 'equipe'">Convidar pessoas na aba Equipe →</button></p>
        </div>

        <label class="text-sm space-y-1 block"><span class="texto-2 text-xs">Pauta (opcional)</span>
            <textarea class="campo" rows="2" x-model="f.descricao" maxlength="1500" placeholder="O que vai ser conversado"></textarea></label>

        <p class="erro text-sm" x-show="erro" x-text="erro" role="alert"></p>
        <div class="flex flex-wrap gap-2 items-center">
            <button type="button" class="btn" @click="salvar()" :disabled="enviando" x-text="enviando ? 'Salvando…' : (f.id ? 'Salvar mudanças' : 'Marcar call')"></button>
            <span class="text-sm" style="color:#4ade80" x-show="ok" x-text="ok" role="status"></span>
        </div>
    </div>

    <h3 class="titulo text-lg">Próximas calls</h3>
    <p class="text-sm texto-2 card p-4" x-show="carregou && !proximas().length">Nenhuma call marcada. Preencha o formulário acima para marcar a primeira; ela aparece aqui e no calendário do Painel.</p>
    <p class="text-sm texto-2" x-show="!carregou">Carregando…</p>
    <div class="wf-int-grade">
        <template x-for="c in proximas()" :key="c.id">
            @include('areas.negocios.partials.integracoes-call')
        </template>
    </div>
    <details x-show="passadas().length" class="space-y-3">
        <summary class="cursor-pointer text-sm texto-2" x-text="'Calls que já passaram (' + passadas().length + ')'"></summary>
        <div class="wf-int-grade mt-2" style="opacity:.7">
            <template x-for="c in passadas()" :key="c.id">
                @include('areas.negocios.partials.integracoes-call')
            </template>
        </div>
    </details>
</div>
</section>

{{-- ================= INTEGRAÇÕES ================= --}}
<section x-show="aba === 'integracoes'" x-cloak class="wf-int">
<div x-data="wfDiscord(@js(url('/integracoes/discord')), @js(session('discord_ok')), @js(session('discord_erro')))" x-effect="if (aba === 'integracoes') abrir()" class="space-y-4">
    <div>
        <h2 class="titulo text-xl">🔌 Integrações</h2>
        <p class="text-sm texto-2">Ligue o Work Flower a outros aplicativos. Avisos no Discord, páginas do Notion e cofres do Obsidian virando cartões, e mensagens prontas de WhatsApp.</p>
    </div>

    <div class="wf-int-grade">
        <div class="card p-4 space-y-3">
            <div class="flex items-center gap-2 min-w-0">
                <h3 class="titulo text-lg shrink-0">Discord</h3>
                <span class="text-xs wf-int-1linha flex-1 text-right" :title="estado()" :style="d.conectado ? 'color:#4ade80' : ''" x-text="estado()"></span>
            </div>
            <p class="text-sm texto-2">Avisos de tarefas, lembretes, pedidos da loja e calls chegam num canal do seu servidor do Discord.</p>
            {{-- um clique: o Discord pergunta o servidor e o canal e devolve o webhook (app do site em DISCORD_CLIENT_ID) --}}
            <div class="space-y-1" x-show="d.oauth && !d.conectado">
                <a class="btn inline-flex" :href="base + '/conectar'">Conectar com o Discord</a>
                <p class="text-xs texto-2">O Discord só pergunta em qual servidor e canal os avisos devem chegar. Escolha e pronto.</p>
            </div>
            <details class="text-sm" :open="!d.oauth && !d.conectado">
                <summary class="cursor-pointer texto-2 text-xs" x-text="d.conectado ? 'Trocar o canal (colar outro webhook)' : (d.oauth ? 'Ou cole o webhook de um canal' : 'Colar o webhook de um canal')"></summary>
                <div class="space-y-2 mt-2">
                    <p class="text-xs texto-2">No Discord: ⚙️ do canal › <b>Integrações</b> › <b>Webhooks</b> › <b>Novo webhook</b> › <b>Copiar URL do webhook</b>. Cole abaixo: conecta sozinho e manda uma mensagem de teste.</p>
                    <input class="campo" type="password" autocomplete="off" x-model="webhook" @input.debounce.700ms="if (pareceWebhook()) salvar()" :placeholder="d.conectado ? 'Cole outro webhook para trocar' : 'https://discord.com/api/webhooks/…'" aria-label="URL do webhook do Discord">
                </div>
            </details>
            <details class="text-sm" :open="d.tem_bot">
                <summary class="cursor-pointer texto-2 text-xs">Bot (opcional): para escolher canais de voz nas Calls</summary>
                <div class="space-y-2 mt-2">
                    <ol class="text-xs texto-2 space-y-1">
                        <li>Em discord.com/developers/applications › New Application.</li>
                        <li>Bot › Reset Token › copie o token.</li>
                        <li>OAuth2 › URL Generator: marque "bot" e a permissão "View Channels", abra o link gerado e adicione o bot ao <b>mesmo servidor</b> do webhook.</li>
                        <li>Cole o token abaixo e salve.</li>
                    </ol>
                    <input class="campo" type="password" autocomplete="off" x-model="bot" :placeholder="d.tem_bot ? 'Token guardado. Cole outro para trocar' : 'Token do bot'" aria-label="Token do bot">
                    <button type="button" class="link-prim text-xs" x-show="d.tem_bot" @click="tirarBot()">Tirar o bot</button>
                </div>
            </details>
            <p class="erro text-sm wf-int-quebra" x-show="erro" x-text="erro" role="alert"></p>
            <p class="text-sm" style="color:#4ade80" x-show="ok" x-text="ok" role="status"></p>
            <p class="text-xs wf-int-quebra" style="color:#f87171" x-show="d.ultimo_erro" x-text="'Último envio falhou: ' + d.ultimo_erro"></p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn" x-show="webhook.trim() || bot.trim()" @click="salvar()" :disabled="enviando" x-text="enviando ? 'Conferindo…' : (d.conectado ? 'Salvar' : 'Conectar')"></button>
                <button type="button" class="btn-sec" x-show="d.conectado" @click="testar()" :disabled="enviando">Mandar mensagem de teste</button>
                <button type="button" class="btn-sec" x-show="d.conectado" @click="desconectar()">Desconectar</button>
            </div>
            <p class="text-xs texto-2">O webhook e o token ficam guardados criptografados e não voltam para esta tela.</p>
        </div>

        <div class="card p-4 space-y-3">
            <h3 class="titulo text-lg">O que avisar no Discord</h3>
            <p class="text-sm texto-2" x-show="!d.conectado">Conecte o Discord ao lado para escolher os avisos.</p>
            <template x-if="d.conectado">
                <div class="space-y-2">
                    <template x-for="(nome, k) in d.nomes" :key="k">
                        <label class="text-sm flex items-center gap-2"><input type="checkbox" :checked="d.eventos[k]" @change="alternar(k, $event.target.checked)"> <span x-text="nome"></span></label>
                    </template>
                    <p class="text-xs texto-2">Tarefas: as dos seus workflows, mesmo quando é alguém da equipe que mexe. Lembretes: os eventos do seu calendário que têm lembrete.</p>
                </div>
            </template>
            <template x-if="canais.length">
                <div class="space-y-1">
                    <p class="text-xs font-semibold texto-2">Canais do servidor</p>
                    <template x-for="c in canais" :key="c.id">
                        <a class="text-sm link-prim block wf-int-1linha" :title="c.nome" :href="c.link" target="_blank" rel="noopener" x-text="(c.tipo === 'voz' ? '🔊 ' : '# ') + c.nome"></a>
                    </template>
                </div>
            </template>
        </div>

        {{-- Notion: conexão aqui; importar e sincronizar pelo clique direito em qualquer quadro (public/js/notas.js) --}}
        <div class="card p-4 space-y-3" x-data="wfNotion(@js(url('/integracoes/notion')), @js(session('notion_ok')), @js(session('notion_erro')))" x-effect="if (aba === 'integracoes') abrir()">
            <div class="flex items-center gap-2 min-w-0">
                <h3 class="titulo text-lg shrink-0">Notion</h3>
                <span class="text-xs wf-int-1linha flex-1 text-right" :title="estado()" :style="d.conectado ? 'color:#4ade80' : ''" x-text="estado()"></span>
            </div>
            <p class="text-sm texto-2">Traga páginas e bancos de dados do Notion para os seus quadros: páginas viram cartões de texto e bancos de dados viram tabelas. O botão “Sincronizar” atualiza os cartões quando a página muda no Notion.</p>
            <template x-if="!d.conectado">
                <div class="space-y-3">
                    <button type="button" class="btn" x-show="d.oauth" @click="entrar()">Entrar com o Notion</button>
                    <details class="text-sm" :open="!d.oauth">
                        <summary class="cursor-pointer texto-2 text-xs" x-text="d.oauth ? 'Ou use um token de integração interna' : 'Conectar com um token de integração interna'"></summary>
                        <div class="space-y-2 mt-2">
                            <ol class="text-xs texto-2 space-y-1">
                                <li>Em <b>notion.so/profile/integrations</b> › <b>Nova integração</b>, tipo <b>Interna</b>, no seu espaço de trabalho.</li>
                                <li>Em Capacidades, deixe marcado <b>Ler conteúdo</b> e salve.</li>
                                <li>Copie o <b>Internal Integration Secret</b> (começa com ntn_) e cole abaixo.</li>
                                <li>No Notion, em cada página que quiser trazer: <b>•••</b> › <b>Conexões</b> › escolha a integração (as subpáginas vêm junto).</li>
                            </ol>
                            <input class="campo" type="password" autocomplete="off" x-model="token" placeholder="ntn_…" aria-label="Token da integração do Notion">
                            <button type="button" class="btn-sec" @click="salvarToken()" :disabled="enviando || !token.trim()" x-text="enviando ? 'Conferindo…' : 'Conectar com o token'"></button>
                        </div>
                    </details>
                </div>
            </template>
            <template x-if="d.conectado">
                <div class="space-y-2 text-sm">
                    <p><b>Como usar:</b> em qualquer quadro (Corpo, Mente, Carteira, Negócios ou Diverso), clique com o botão direito num espaço vazio › <b>Notion e Obsidian</b> › <b>Importar do Notion…</b> ou <b>Sincronizar com o Notion</b>.</p>
                    <p class="text-xs texto-2">Só aparecem as páginas compartilhadas com a integração (no Notion: ••• › Conexões). A sincronização é manual e traz as mudanças do Notion para cá; o que você muda no cartão não volta para o Notion.</p>
                    <p class="text-xs texto-2" x-show="d.ultimo_sync" x-text="'Última leitura: ' + (d.ultimo_sync ? new Date(d.ultimo_sync).toLocaleString('pt-BR') : '')"></p>
                </div>
            </template>
            <p class="erro text-sm wf-int-quebra" x-show="erro" x-text="erro" role="alert"></p>
            <p class="text-sm wf-int-quebra" style="color:#4ade80" x-show="ok" x-text="ok" role="status"></p>
            <p class="text-xs wf-int-quebra" style="color:#f87171" x-show="d.ultimo_erro" x-text="'Último erro: ' + d.ultimo_erro"></p>
            <div class="flex flex-wrap gap-2" x-show="d.conectado">
                <button type="button" class="btn-sec" @click="desconectar()">Desconectar</button>
            </div>
            <p class="text-xs texto-2">O token fica guardado criptografado e não volta para esta tela.</p>
        </div>

        {{-- Obsidian: tudo no navegador (public/js/notas.js); aqui só a explicação --}}
        <div class="card p-4 space-y-2">
            <h3 class="titulo text-lg">Obsidian</h3>
            <p class="text-sm texto-2">Leve um cofre do Obsidian para um quadro e traga de volta quando quiser.</p>
            <ul class="text-sm space-y-1 list-disc pl-5">
                <li><b>Importar:</b> arraste o .zip do cofre (ou a pasta dele) para qualquer quadro, ou clique com o botão direito › Notion e Obsidian › Importar cofre. Cada nota vira um cartão de texto, organizado pelas pastas do cofre ou pelas tags, com um mapa mental dos [[links]].</li>
                <li><b>Exportar:</b> clique com o botão direito no quadro › Exportar a aba para o Obsidian, ou numa pasta › Exportar para o Obsidian. Sai um .zip que o Obsidian abre como cofre, com o frontmatter e as tags de volta.</li>
            </ul>
            <p class="text-xs texto-2">Nada sai do seu navegador além dos cartões que já são salvos na sua conta.</p>
        </div>

        <div class="card p-4 space-y-2">
            <h3 class="titulo text-lg">WhatsApp</h3>
            <p class="text-sm texto-2">As mensagens prontas para clientes ficam na aba Clientes (botão “💬 Mensagem”). Elas abrem o WhatsApp já com o texto, sem precisar de conta comercial.</p>
            <button type="button" class="btn-sec" @click="aba = 'clientes'">Abrir Clientes →</button>
        </div>
    </div>
</div>
</section>
