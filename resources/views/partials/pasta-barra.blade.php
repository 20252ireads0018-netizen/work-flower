{{-- Barras das pastas (Corpo, Mente, Carteira e Diverso; lógica em public/js/quadro.js). Fica logo acima do quadro.
     1) Pasta aberta: caminho até ela, "Voltar" e área para soltar cartões que devem sair da pasta.
     2) Cartões selecionados (Ctrl+clique): guardar numa pasta nova ou existente, tirar da pasta, excluir. --}}
<div class="wf-pasta-barra" x-show="aba && pastaAberta(aba)" x-cloak data-wf-sair-pasta>
    <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="fecharPasta(aba)" title="Voltar (atalho na aba Atalhos)">← Voltar</button>
    <nav class="wf-pasta-trilha" aria-label="Pastas abertas">
        <button type="button" @click="irPasta(aba, null)">Quadro</button>
        <template x-for="(p, i) in caminhoPasta(aba)" :key="p.id">
            <span class="flex items-center gap-1">
                <span class="texto-2" aria-hidden="true">›</span>
                <template x-if="i < caminhoPasta(aba).length - 1">
                    <button type="button" @click="irPasta(aba, p.id)" x-text="'📁 ' + (p.titulo || 'Pasta')"></button>
                </template>
                <template x-if="i === caminhoPasta(aba).length - 1">
                    <b x-text="'📁 ' + (p.titulo || 'Pasta')"></b>
                </template>
            </span>
        </template>
    </nav>
    <span class="text-xs texto-2 hidden sm:inline">Arraste um cartão até esta barra para tirá-lo da pasta.</span>
</div>

<div class="wf-sel-barra" x-show="sel.length" x-cloak role="toolbar" aria-label="Cartões selecionados">
    <span class="text-sm font-semibold" x-text="sel.length === 1 ? '1 selecionado' : sel.length + ' selecionados'"></span>
    <button type="button" class="btn !py-1 !px-3 text-sm" @click="pastaComSelecionados(aba)">📁 Nova pasta com eles</button>
    <select class="campo !py-1 text-sm" x-show="pastasDaVista(aba).length" aria-label="Mover para a pasta"
            @change="if ($event.target.value) moverParaPasta(aba, [...sel], $event.target.value); $event.target.value = ''">
        <option value="">Mover para…</option>
        <template x-for="p in pastasDaVista(aba)" :key="p.id">
            <option :value="p.id" x-text="'📁 ' + (p.titulo || 'Pasta')"></option>
        </template>
    </select>
    <button type="button" class="btn-sec !py-1 !px-3 text-sm" x-show="pastaAberta(aba)" @click="moverParaPasta(aba, [...sel])">Tirar da pasta</button>
    <button type="button" class="btn-sec !py-1 !px-3 text-sm perigo" @click="excluirSelecionados()">Excluir</button>
    <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="sel = []" aria-label="Limpar seleção">✕</button>
</div>
