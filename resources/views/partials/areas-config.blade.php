{{-- Tela "Áreas": esconder, renomear, reordenar, criar/excluir áreas e levar tudo de uma área para outra.
     Abre com o evento abrir-areas (engrenagem › Áreas, botão do painel). Lógica: public/js/areas-config.js --}}
<meta name="wf-areas-url" content="{{ url('/areas-config') }}">
<div x-data="wfAreasConfig()" x-show="aberto" x-cloak @abrir-areas.window="abrir()" @keydown.escape.window="if (aberto) fechar()"
     class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="areas-titulo">
    <div class="absolute inset-0 bg-black/50" @click="fechar()"></div>
    <div class="card shadow-xl absolute top-20 left-4 right-4 max-h-[calc(100vh-6rem)] max-h-[calc(100dvh-6rem)] overflow-auto p-6 space-y-5" style="max-width:34rem;margin:0 auto">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="areas-titulo" class="titulo text-lg">Áreas</h2>
                <p class="text-sm texto-2 mt-1">Escolha quais áreas aparecem no menu e no painel, a ordem e o nome de cada uma. Esconder uma área não apaga nada: os dados ficam guardados e voltam quando você mostrar de novo.</p>
            </div>
            <button type="button" class="mini-btn" @click="fechar()" aria-label="Fechar">✕</button>
        </div>

        <p x-show="carregando" class="text-sm texto-2">Carregando…</p>
        <p x-show="erro" x-cloak class="text-sm" style="color:#f87171" x-text="erro" role="alert"></p>

        <ul class="space-y-2" x-show="!carregando">
            <template x-for="(a, i) in areas" :key="a.slug">
                <li class="flex items-center gap-2" :class="a.oculta ? 'opacity-60' : ''">
                    <div class="flex flex-col">
                        <button type="button" class="mini-btn !py-0" :disabled="i === 0" @click="mover(i, -1)" :aria-label="'Subir ' + a.nome" title="Subir">▲</button>
                        <button type="button" class="mini-btn !py-0" :disabled="i === areas.length - 1" @click="mover(i, 1)" :aria-label="'Descer ' + a.nome" title="Descer">▼</button>
                    </div>
                    <input type="text" class="campo flex-1 min-w-0" maxlength="30" x-model="a.nomeEdit"
                           :placeholder="a.nome_padrao || 'Nome da área'" :aria-label="'Nome da área ' + a.nome">
                    <span class="text-xs texto-2 shrink-0" x-show="a.extra">criada</span>
                    <button type="button" class="btn-sec !py-1 !px-2 text-xs shrink-0" @click="alternar(a)"
                            x-text="a.oculta ? 'Mostrar' : 'Esconder'" :aria-pressed="a.oculta"></button>
                    <button type="button" class="mini-btn perigo shrink-0" x-show="a.extra" @click="pedirExcluir(a)" :aria-label="'Excluir a área ' + a.nome" title="Excluir área">✕</button>
                </li>
            </template>
        </ul>

        {{-- Excluir área criada: as abas voltam para o Diverso ou são apagadas --}}
        <template x-if="excluir">
            <div class="card p-4 space-y-3">
                <p class="text-sm font-medium" x-text="'Excluir a área “' + excluir.nome + '”'"></p>
                <label class="flex items-center gap-2 text-sm"><input type="radio" value="diverso" x-model="excluir.destino"> Levar as abas e os cartões para o Diverso</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" value="apagar" x-model="excluir.destino"> Apagar as abas e os cartões</label>
                <div class="flex gap-2">
                    <button type="button" class="btn" :disabled="salvando" @click="confirmarExcluir()">Excluir área</button>
                    <button type="button" class="btn-sec" @click="excluir = null">Cancelar</button>
                </div>
            </div>
        </template>

        <div class="flex flex-wrap gap-2" x-show="!carregando">
            <button type="button" class="btn" :disabled="salvando || !mudou()" @click="salvar()">Salvar</button>
            <button type="button" class="btn-sec" @click="fechar()">Fechar</button>
        </div>

        <hr style="border-color:var(--linha)">

        <div class="space-y-2">
            <h3 class="titulo text-base">Nova área</h3>
            <p class="text-sm texto-2">Um quadro com abas, como o Diverso, com o nome que você quiser (ex.: Trabalho, Casa, Viagem).</p>
            <div class="flex gap-2">
                <input type="text" class="campo flex-1" maxlength="30" x-model="novaNome" placeholder="Nome da área" @keydown.enter.prevent="criar()" aria-label="Nome da nova área">
                <button type="button" class="btn shrink-0" :disabled="salvando" @click="criar()">Criar</button>
            </div>
        </div>

        <hr style="border-color:var(--linha)">

        <div class="space-y-2">
            <h3 class="titulo text-base">Levar tudo de uma área para outra</h3>
            <p class="text-sm texto-2">Os cartões de cada aba (textos, tabelas, listas, imagens, mapas mentais, pastas…) vão com o conteúdo, a posição e as imagens; cada aba vira uma aba nova no destino. Os cartões fixos da área (como Treinos ou Contas) ficam guardados nela. Para mover só uma aba ou alguns cartões, use o clique direito no quadro ou no cartão.</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <div>
                    <label class="rotulo" for="areas-tudo-de">De</label>
                    <select id="areas-tudo-de" class="campo" x-model="tudo.de" @change="acertarDestino()">
                        <template x-for="a in areas" :key="a.slug"><option :value="a.slug" x-text="a.nome" :selected="tudo.de === a.slug"></option></template>
                    </select>
                </div>
                <div>
                    <label class="rotulo" for="areas-tudo-para">Para</label>
                    <select id="areas-tudo-para" class="campo" x-model="tudo.para">
                        <template x-for="a in destinosTudo()" :key="a.slug"><option :value="a.slug" x-text="a.nome" :selected="tudo.para === a.slug"></option></template>
                    </select>
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="tudo.esconder"> Esconder a área de origem depois</label>
            <button type="button" class="btn-sec" :disabled="salvando || !tudo.de || !tudo.para" @click="moverTudo()">Levar tudo</button>
        </div>
    </div>
</div>
<script src="{{ asset('js/areas-config.js') }}?v={{ @filemtime(public_path('js/areas-config.js')) }}"></script>
<script src="{{ asset('js/mover-area.js') }}?v={{ @filemtime(public_path('js/mover-area.js')) }}"></script>
