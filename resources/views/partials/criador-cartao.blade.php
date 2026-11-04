{{-- Conteúdo do cartão "Criador de conteúdo" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos cr*).
     Cada post anda pelas etapas (Ideia → Publicado). Ao abrir um post: rede, data, legenda e hashtags,
     com a contagem de caracteres no limite da rede e o botão de copiar. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="crUI(b).ver !== 'yt' && 'qz-on'" @click="crUI(b).ver = 'posts'">Posts</button>
        <button type="button" class="btn-sec !py-0.5" :class="crUI(b).ver === 'yt' && 'qz-on'" @click="crUI(b).ver = 'yt'; crUI(b).aberto = null">YouTube</button>
        <button type="button" class="btn !py-0.5" @click="crUI(b).ver = 'posts'; crNovo(b)">+ Post</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="crUI(b).rede" aria-label="Rede">
            <option value="">Todas as redes</option>
            <template x-for="r in crRedes()" :key="r"><option :value="r" x-text="r"></option></template>
        </select>
        <span class="ml-auto texto-2" x-text="crSemana(b) + ' para os próximos 7 dias'"></span>
    </div>

    {{-- métricas do canal (YoutubeController; a chave da API fica no servidor) --}}
    <template x-if="crUI(b).ver === 'yt' && !crAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <form class="flex gap-1" @submit.prevent="crYtAtualizar(b)">
                <input class="campo !py-0.5 text-xs flex-1" x-model="b.dados.yt" maxlength="200" placeholder="@seucanal ou link do canal" aria-label="Canal do YouTube">
                <button class="btn !py-0.5" :disabled="crUI(b).ytCarregando" x-text="crUI(b).ytCarregando ? 'Buscando…' : 'Atualizar'"></button>
            </form>
            <p class="text-red-500" x-show="crUI(b).ytErro" x-text="crUI(b).ytErro"></p>
            <template x-if="b.dados.ytSnap">
                <div class="flex flex-col gap-2">
                    <div class="grid grid-cols-3 gap-1.5 text-center">
                        <div class="qz-ed !gap-0"><b class="text-base" x-text="crYtNum(b.dados.ytSnap.canal.inscritos)"></b><span class="texto-2">inscritos</span></div>
                        <div class="qz-ed !gap-0"><b class="text-base" x-text="crYtNum(b.dados.ytSnap.canal.views)"></b><span class="texto-2">visualizações</span></div>
                        <div class="qz-ed !gap-0"><b class="text-base" x-text="crYtNum(b.dados.ytSnap.canal.videos)"></b><span class="texto-2">vídeos</span></div>
                    </div>
                    <p class="texto-2"><span x-text="b.dados.ytSnap.canal.nome"></span> · lido em <span x-text="crYtQuando(b)"></span></p>
                    <table class="gr-tab w-full">
                        <thead><tr><th class="text-left">Últimos vídeos</th><th>Views</th><th>Curt.</th><th>Com.</th><th title="Curtidas + comentários por visualização">Engaj.</th></tr></thead>
                        <tbody>
                            <template x-for="v in b.dados.ytSnap.videos" :key="v.id">
                                <tr>
                                    <td><a class="link-prim" :href="'https://www.youtube.com/watch?v=' + v.id" target="_blank" rel="noopener" x-text="v.titulo"></a> <span class="texto-2" x-text="v.publicado.split('-').reverse().join('/')"></span></td>
                                    <td class="text-center" x-text="crYtNum(v.views)"></td>
                                    <td class="text-center" x-text="crYtNum(v.curtidas)"></td>
                                    <td class="text-center" x-text="crYtNum(v.comentarios)"></td>
                                    <td class="text-center" x-text="crYtTaxa(v)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <p class="texto-2" x-show="!b.dados.ytSnap">Mostra inscritos, visualizações e os 12 últimos vídeos do canal. Os dados são públicos e atualizam a cada 30 minutos.</p>
        </div>
    </template>

    <template x-if="crUI(b).ver !== 'yt' && !crAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex gap-2">
            <template x-for="et in crEtapas()" :key="et">
                <div class="cx-col">
                    <p class="cx-col-tit"><span x-text="et"></span> <span class="texto-2" x-text="crDa(b, et).length"></span></p>
                    <template x-for="p in crDa(b, et)" :key="p.id">
                        <div class="cx-card" @click="crUI(b).aberto = p.id">
                            <p class="font-medium leading-tight" x-text="p.titulo || 'Sem título'"></p>
                            <p class="text-[11px] texto-2"><span x-text="p.rede"></span><span x-show="p.data" x-text="' · ' + new Date(p.data + 'T00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })"></span></p>
                            <div class="flex gap-1 mt-1" @click.stop>
                                <button type="button" class="mini-btn" title="Etapa anterior" aria-label="Etapa anterior" @click="crMover(b, p, -1)">‹</button>
                                <button type="button" class="mini-btn" title="Próxima etapa" aria-label="Próxima etapa" @click="crMover(b, p, 1)">›</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </template>

    <template x-for="p in (crAberto(b) ? [crAberto(b)] : [])" :key="p.id">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <div class="flex gap-1.5 items-center">
                <button type="button" class="mini-btn" title="Voltar ao quadro" aria-label="Voltar ao quadro" @click="crUI(b).aberto = null">‹</button>
                <input class="campo !py-1 flex-1 font-medium" x-model="p.titulo" maxlength="120" aria-label="Título">
                <button type="button" class="mini-btn perigo" title="Tirar o post" aria-label="Tirar o post" @click="crRemover(b, p)">✕</button>
            </div>
            <div class="grid grid-cols-3 gap-1.5 text-xs">
                <label>Rede<select class="campo !py-0.5 !text-xs" x-model="p.rede"><template x-for="r in crRedes()" :key="r"><option :value="r" x-text="r" :selected="r === p.rede"></option></template></select></label>
                <label>Etapa<select class="campo !py-0.5 !text-xs" x-model="p.etapa"><template x-for="e in crEtapas()" :key="e"><option :value="e" x-text="e" :selected="e === p.etapa"></option></template></select></label>
                <label>Data<input type="date" class="campo !py-0.5 !text-xs" x-model="p.data"></label>
            </div>
            <textarea class="campo text-xs flex-1 min-h-[6rem]" x-model="p.legenda" maxlength="64000" placeholder="Roteiro, legenda ou texto do post" aria-label="Legenda"></textarea>
            <input class="campo !py-0.5 text-xs" x-model="p.tags" maxlength="3000" placeholder="#hashtags separadas por espaço" aria-label="Hashtags">
            <input class="campo !py-0.5 text-xs" x-model="p.link" maxlength="300" placeholder="Link do post publicado (no YouTube, mostra as métricas)" aria-label="Link do post">
            <template x-if="crYtDoPost(b, p)">
                <p class="text-xs texto-2" x-text="'YouTube: ' + crYtNum(crYtDoPost(b, p).views) + ' visualizações · ' + crYtNum(crYtDoPost(b, p).curtidas) + ' curtidas · ' + crYtNum(crYtDoPost(b, p).comentarios) + ' comentários'"></p>
            </template>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span :class="crConta(p).passou ? 'text-red-500 font-semibold' : 'texto-2'"
                      x-text="crConta(p).n + (crConta(p).lim ? ' / ' + crConta(p).lim.toLocaleString('pt-BR') : '') + ' caracteres · ' + crConta(p).tags + ' hashtags'"></span>
                <span x-show="crConta(p).muitasTags" class="text-red-500">O Instagram aceita até 30 hashtags.</span>
                <button type="button" class="btn-sec !py-0.5 ml-auto" @click="crCopiar(p)">Copiar legenda</button>
            </div>
        </div>
    </template>
</div>
