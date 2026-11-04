{{-- Cartão de uma call (dentro de um x-for com "c"), usado em partials/integracoes para as próximas e as passadas. --}}
<article class="card p-4 space-y-2 min-w-0">
    <div class="min-w-0">
        <p class="font-semibold wf-int-quebra" x-text="'📞 ' + c.titulo"></p>
        <p class="text-xs texto-2" x-text="quando(c) + ' · ' + (plataformas[c.plataforma] || 'Link')"></p>
        <p class="text-xs texto-2 wf-int-quebra" x-show="!c.meu" x-text="'Marcada por ' + c.dono + ' (só quem marcou pode mudar)'"></p>
    </div>
    <p class="text-xs wf-int-quebra" x-show="c.participantes.length" x-text="'Com ' + c.participantes.map(id => nomes[id] || '?').join(', ')"></p>
    <p class="text-xs texto-2" x-show="!c.participantes.length && c.meu">Só você (marque pessoas da equipe ao editar).</p>
    <p class="text-sm texto-2 whitespace-pre-wrap wf-int-quebra" x-show="c.descricao" x-text="c.descricao"></p>
    <p class="text-xs texto-2 wf-int-1linha" :title="c.link" x-text="c.link"></p>
    <div class="flex flex-wrap gap-2">
        <a class="btn !py-1" :href="c.link" target="_blank" rel="noopener">Entrar na call</a>
        <button type="button" class="btn-sec !py-1" @click="copiar(c)" x-text="c._copiado ? 'Copiado!' : 'Copiar link'"></button>
        <template x-if="c.meu">
            <span class="flex flex-wrap gap-2">
                <button type="button" class="btn-sec !py-1" @click="editar(c)">✎ Editar</button>
                <button type="button" class="btn-sec !py-1" style="color:#ef4444" @click="apagar(c)">✕ Cancelar call</button>
            </span>
        </template>
    </div>
</article>
