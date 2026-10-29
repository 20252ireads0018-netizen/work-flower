{{-- Conteúdo do cartão "Claude" (Corpo, Mente, Carteira e Diverso): conversa com o Claude, que lê e muda os cartões
     desta página pedindo permissão antes de cada mudança. Lógica em quadro.js (seção "cartão Claude") e js/claude.js.
     A chave da API é de cada pessoa e fica só no navegador dela; o custo vai para a conta Anthropic dona da chave. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm" x-data="{ ui: claude(b) }">
    {{-- sem chave neste navegador --}}
    <template x-if="!claudeTemChave">
        <div class="flex flex-col gap-2">
            <p class="texto-2 text-xs leading-relaxed">
                Conecte o Claude com a <b>sua</b> chave da API da Anthropic. Ela fica guardada só neste navegador e o uso é cobrado
                na sua conta da Anthropic. Crie uma chave em
                <a class="link-prim" href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com → API Keys</a>.
            </p>
            <form class="flex gap-2" @submit.prevent="claudeGuardarChave(b)">
                <input type="password" class="campo flex-1 !py-1.5 text-sm" placeholder="sk-ant-…" x-model="ui.chave" autocomplete="off" aria-label="Chave da API da Anthropic">
                <button type="submit" class="btn !py-1.5 !px-3 text-sm">Conectar</button>
            </form>
            <p class="erro !mt-0" x-show="ui.erro" x-text="ui.erro"></p>
        </div>
    </template>

    <template x-if="claudeTemChave">
        <div class="flex flex-col h-full gap-2 min-h-0">
            <div class="flex items-center gap-2 flex-wrap">
                <select class="campo !w-auto !py-1 text-xs" x-model="b.dados.modelo" aria-label="Modelo do Claude">
                    <template x-for="m in claudeModelos()" :key="m[0]"><option :value="m[0]" x-text="m[1]"></option></template>
                </select>
                <span class="text-xs texto-2 flex-1" x-show="ui.tokens" x-text="ui.tokens.toLocaleString('pt-BR') + ' tokens nesta sessão'"></span>
                <button type="button" class="mini-btn" title="Apagar a conversa" aria-label="Apagar a conversa" @click="claudeLimpar(b)">🧹</button>
                <button type="button" class="mini-btn" title="Tirar a chave deste navegador" aria-label="Tirar a chave deste navegador" @click="claudeEsquecerChave()">🔑</button>
            </div>

            <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 pr-1" :data-claude-rolar="b.id">
                <p class="texto-2 text-xs" x-show="!(b.dados.chat || []).length">
                    Peça, por exemplo: “crie uma lista de compras da semana”, “transforme o texto Notas em uma tabela” ou
                    “marque como feitos os itens de leitura”. Antes de qualquer mudança, ele pede a sua permissão.
                </p>
                <template x-for="(m, k) in (b.dados.chat || [])" :key="k">
                    <div class="rounded-lg px-3 py-2 whitespace-pre-wrap break-words"
                         :class="m.q === 'eu' ? 'self-end max-w-[85%]' : (m.q === 'claude' ? 'self-start max-w-[92%]' : 'self-stretch text-xs')"
                         :style="m.q === 'eu' ? 'background: var(--prim-suave); border: 1px solid var(--prim-linha)'
                                : m.q === 'claude' ? 'background: var(--superficie-2); border: 1px solid var(--linha)'
                                : m.q === 'erro' ? 'color: #f87171' : 'color: var(--tinta-2)'"
                         x-text="m.t"></div>
                </template>
                <div class="self-start texto-2 text-xs" x-show="ui.ocupado && !ui.pendente">Claude está pensando…</div>
            </div>

            {{-- pedido de permissão --}}
            <div x-show="ui.pendente" class="rounded-lg p-3 flex flex-col gap-2" style="border: 1px solid var(--prim); background: var(--prim-suave)">
                <b class="text-xs">O Claude quer:</b>
                <span x-text="ui.pendente && ui.pendente.desc"></span>
                <label class="text-xs flex items-center gap-2"><input type="checkbox" x-model="ui.tudo"> Permitir as próximas mudanças desta conversa</label>
                <div class="flex gap-2">
                    <button type="button" class="btn !py-1 !px-3 text-sm" @click="claudePermitir(b, true)">Permitir</button>
                    <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="claudePermitir(b, false)">Recusar</button>
                </div>
            </div>

            <p class="erro !mt-0" x-show="ui.erro" x-text="ui.erro"></p>
            <form class="flex gap-2" @submit.prevent="claudeEnviar(b)">
                <textarea class="campo flex-1 !py-1.5 text-sm resize-none" rows="2" x-model="ui.entrada" :disabled="ui.ocupado"
                          placeholder="Escreva para o Claude…" aria-label="Mensagem para o Claude"
                          @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); claudeEnviar(b); }"></textarea>
                <button type="submit" class="btn !px-3 text-sm" :disabled="ui.ocupado || !ui.entrada.trim()">Enviar</button>
            </form>
        </div>
    </template>
</div>
