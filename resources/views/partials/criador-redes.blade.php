{{-- Cartão "Criador de conteúdo": abas Instagram e TikTok. Lógica em public/js/criador-redes.js (métodos crRd*, crRede*);
     servidor em RedesController. Mostra a conta de quem está vendo, conectada por OAuth (tokens só no servidor). --}}
<template x-if="crRedeAberta(b) && !crAberto(b)">
    <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-sm" x-text="'Métricas do ' + crRedeNome(b)"></p>
                <p class="texto-2 truncate" x-show="crSnap(b)" :title="crSnap(b) ? (crSnap(b).conta.nome || '') : ''"
                   x-text="crSnap(b) ? (crSnap(b).conta.usuario ? '@' + crSnap(b).conta.usuario : crSnap(b).conta.nome) + ' · lido em ' + crRdQuando(b) : ''"></p>
            </div>
            <button type="button" class="btn !py-0.5" x-show="crSnap(b) || !crRdUI(b).conectar" :disabled="crRdUI(b).carregando"
                    @click="crRede(b, crUI(b).ver, true)" x-text="crRdUI(b).carregando ? 'Buscando…' : 'Atualizar'"></button>
            <button type="button" class="btn-sec !py-0.5" x-show="crSnap(b)" @click="crRedeSair(b)">Desconectar</button>
        </div>

        {{-- não configurado no site, conta não conectada ou erro da rede --}}
        <div x-show="crRdUI(b).erro" class="qz-ed" style="gap:.35rem">
            <p class="text-red-500 break-words" x-text="crRdUI(b).erro"></p>
            <p class="texto-2" x-show="crRdUI(b).configurar">Esta rede chega em breve ao Work Flower. Enquanto isso, o resto do cartão funciona normalmente.</p>
            <template x-if="crRdUI(b).conectar">
                <div class="flex flex-col gap-1.5">
                    <a class="btn self-start !py-1" :href="crRdUI(b).conectar" x-text="'Conectar minha conta do ' + crRedeNome(b)"></a>
                    <p class="texto-2" x-show="crUI(b).ver === 'ig'">Você entra pelo Facebook e escolhe a Página ligada ao Instagram. A conta precisa ser Profissional (Comercial ou Criador de conteúdo). Nada é publicado: o site só lê seguidores e posts.</p>
                    <p class="texto-2" x-show="crUI(b).ver === 'tt'">Você entra pelo TikTok e autoriza a leitura do perfil, das estatísticas e dos vídeos. Nada é publicado.</p>
                </div>
            </template>
        </div>

        <template x-if="crSnap(b)">
            <div class="flex flex-col gap-2">
                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <template x-for="r in crRdResumo(b)" :key="r[1]">
                        <div class="qz-ed" style="gap:0"><b class="text-base" x-text="crRdNum(r[0])"></b><span class="texto-2" x-text="r[1]"></span></div>
                    </template>
                </div>
                <div class="overflow-x-auto">
                    <table class="gr-tab w-full">
                        <thead><tr>
                            <th class="text-left" x-text="crUI(b).ver === 'ig' ? 'Últimos posts' : 'Últimos vídeos'"></th>
                            <template x-for="c in crRdColunas(b)" :key="c"><th x-text="c"></th></template>
                        </tr></thead>
                        <tbody>
                            <template x-for="v in crSnap(b).itens" :key="v.id">
                                <tr>
                                    <td class="break-words" style="max-width:16rem">
                                        <a class="link-prim" :href="v.link || '#'" target="_blank" rel="noopener" :title="(v.legenda ?? v.titulo) || ''"
                                           x-text="(v.legenda ?? v.titulo) || '(sem legenda)'"></a>
                                        <span class="texto-2 whitespace-nowrap" x-text="(v.tipo ? v.tipo + ' · ' : '') + String(v.data || '').split('-').reverse().join('/')"></span>
                                    </td>
                                    <template x-for="(n, i) in crRdValores(b, v)" :key="i"><td class="text-center whitespace-nowrap" x-text="crRdNum(n)"></td></template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="texto-2" x-show="!crSnap(b).itens.length">A conta ainda não tem posts publicados.</p>
                <p class="texto-2" x-show="crUI(b).ver === 'ig'">“—” = o Instagram não informou (alcance e views só existem para contas Profissionais e alguns tipos de post).</p>
            </div>
        </template>

        <p class="texto-2" x-show="!crSnap(b) && !crRdUI(b).erro" x-text="crRdUI(b).carregando ? 'Buscando as métricas…' : 'Mostra seguidores e os últimos posts da sua conta, com curtidas, comentários e, quando a rede informa, alcance e visualizações. Clique em Atualizar.'"></p>
    </div>
</template>
