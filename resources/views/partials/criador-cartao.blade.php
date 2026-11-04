{{-- Conteúdo do cartão "Criador de conteúdo" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos cr*).
     Cada post anda pelas etapas (Ideia → Publicado). Ao abrir um post ("Editar post"): título, rede, etapa, data,
     roteiro/legenda com a contagem de caracteres no limite da rede, hashtags, link e o botão de copiar. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs" x-show="!crAberto(b)">
        <button type="button" class="btn-sec !py-0.5" :class="!crMetrica(b) && 'qz-on'" @click="crUI(b).ver = 'posts'">Posts</button>
        <button type="button" class="btn-sec !py-0.5" :class="crUI(b).ver === 'yt' && 'qz-on'" @click="crVer(b, 'yt')">YouTube</button>
        <button type="button" class="btn-sec !py-0.5" :class="crUI(b).ver === 'ig' && 'qz-on'" @click="crVer(b, 'ig')">Instagram</button>
        <button type="button" class="btn-sec !py-0.5" :class="crUI(b).ver === 'tt' && 'qz-on'" @click="crVer(b, 'tt')">TikTok</button>
        <button type="button" class="btn !py-0.5" @click="crUI(b).ver = 'posts'; crNovo(b)">+ Novo post</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="crUI(b).rede" aria-label="Mostrar só uma rede">
            <option value="">Todas as redes</option>
            <template x-for="r in crRedes()" :key="r"><option :value="r" x-text="r"></option></template>
        </select>
        <span class="ml-auto texto-2" x-text="crSemana(b) + (crSemana(b) === 1 ? ' post' : ' posts') + ' para os próximos 7 dias'"></span>
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

    {{-- quadro de posts por etapa --}}
    {{-- abas Instagram e TikTok (partials/criador-redes, js/criador-redes.js) --}}
    @include('partials.criador-redes')

    <template x-if="!crMetrica(b) && !crAberto(b)">
        <div class="flex-1 min-h-0 flex flex-col gap-1.5">
            <template x-if="!(b.dados.posts || []).length">
                <div class="flex-1 flex flex-col items-center justify-center text-center gap-2 texto-2 px-4">
                    <p class="font-semibold">Nenhum post ainda</p>
                    <p class="text-xs">Cada post é um cartãozinho que anda pelas etapas: <b>Ideia → Roteiro → Gravado → Editado → Agendado → Publicado</b>.
                        Comece anotando uma ideia; depois escreva o roteiro e a legenda dentro dele.</p>
                    <button type="button" class="btn !py-1 text-xs" @click="crNovo(b)">+ Criar o primeiro post</button>
                </div>
            </template>
            <template x-if="(b.dados.posts || []).length">
                <div class="flex-1 min-h-0 overflow-auto flex gap-2">
                    <template x-for="et in crEtapas()" :key="et">
                        <div class="cx-col">
                            <p class="cx-col-tit flex items-center"><span x-text="et"></span>&nbsp;<span class="texto-2" x-text="crDa(b, et).length"></span>
                                <button type="button" class="mini-btn ml-auto" :title="'Novo post em ' + et" :aria-label="'Novo post em ' + et" @click="crNovo(b, et)">+</button></p>
                            <template x-for="p in crDa(b, et)" :key="p.id">
                                <div class="cx-card" role="button" tabindex="0" :title="'Abrir ' + (p.titulo || 'post sem título')" @click="crUI(b).aberto = p.id" @keydown.enter="crUI(b).aberto = p.id">
                                    <p class="font-medium leading-tight" x-text="p.titulo || 'Sem título'"></p>
                                    <p class="text-[11px] texto-2"><span x-text="p.rede"></span><span x-show="p.data" x-text="' · ' + new Date(p.data + 'T00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })"></span></p>
                                    <div class="flex gap-1 mt-1" @click.stop>
                                        <button type="button" class="mini-btn" title="Etapa anterior" aria-label="Etapa anterior" @click="crMover(b, p, -1)">‹</button>
                                        <button type="button" class="mini-btn" title="Próxima etapa" aria-label="Próxima etapa" @click="crMover(b, p, 1)">›</button>
                                    </div>
                                </div>
                            </template>
                            <p class="text-[11px] texto-2 px-1" x-show="!crDa(b, et).length">Vazio</p>
                        </div>
                    </template>
                </div>
            </template>
            <p class="text-[11px] texto-2" x-show="(b.dados.posts || []).length">Clique num post para editar. Use ‹ › para mudar de etapa.</p>
        </div>
    </template>

    {{-- editar post --}}
    <template x-for="p in (crAberto(b) ? [crAberto(b)] : [])" :key="p.id">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2.5 cr-editor">
            <div class="flex flex-wrap gap-1.5 items-center">
                <button type="button" class="btn-sec !py-0.5 text-xs" @click="crUI(b).aberto = null">‹ Voltar ao quadro</button>
                <h3 class="font-semibold text-sm">Editar post</h3>
                <button type="button" class="btn-sec !py-0.5 text-xs perigo ml-auto" @click="crRemover(b, p)">Excluir post</button>
            </div>

            <label class="cr-campo">
                <span class="cr-rot">Título <span class="texto-2 font-normal">(só para você se organizar)</span></span>
                <input class="campo !py-1 font-medium" x-model="p.titulo" maxlength="120" placeholder="Ex.: 3 dicas para economizar no mercado" x-init="!p.titulo && $nextTick(() => $el.focus())">
            </label>

            <div class="cx-grade">
                <label class="cr-campo"><span class="cr-rot">Rede</span>
                    <select class="campo !py-0.5 !text-xs" x-model="p.rede"><template x-for="r in crRedes()" :key="r"><option :value="r" x-text="r" :selected="r === p.rede"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Etapa</span>
                    <select class="campo !py-0.5 !text-xs" x-model="p.etapa"><template x-for="e in crEtapas()" :key="e"><option :value="e" x-text="e" :selected="e === p.etapa"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Data de publicação</span>
                    <input type="date" class="campo !py-0.5 !text-xs" x-model="p.data"></label>
            </div>

            <label class="cr-campo flex-1 min-h-0">
                <span class="cr-rot flex items-center gap-2">Roteiro / Legenda
                    <span class="ml-auto font-normal" :class="crConta(p).passou ? 'text-red-500 font-semibold' : 'texto-2'"
                          x-text="crConta(p).n.toLocaleString('pt-BR') + (crConta(p).lim ? ' / ' + crConta(p).lim.toLocaleString('pt-BR') : '') + ' caracteres'"></span></span>
                <textarea class="campo text-xs flex-1" style="min-height: 8rem" x-model="p.legenda" maxlength="64000"
                          placeholder="Ex.:&#10;Gancho: Você paga caro no mercado sem perceber?&#10;1. Faça a lista antes de sair&#10;2. Compare o preço por quilo&#10;3. Evite ir com fome&#10;Chamada: Salve este post para a próxima compra!"></textarea>
                <span class="text-[11px] texto-2" x-text="crDica(p) + ' A contagem inclui as hashtags.'"></span>
                <span class="text-[11px] text-red-500" x-show="crConta(p).passou" x-text="'Passou do limite do ' + p.rede + ' em ' + (crConta(p).n - crConta(p).lim).toLocaleString('pt-BR') + ' caracteres.'"></span>
            </label>

            <label class="cr-campo">
                <span class="cr-rot flex items-center gap-2">Hashtags
                    <span class="ml-auto font-normal" :class="crConta(p).muitasTags ? 'text-red-500 font-semibold' : 'texto-2'" x-text="crConta(p).tags + (crConta(p).tags === 1 ? ' hashtag' : ' hashtags')"></span></span>
                <input class="campo !py-0.5 text-xs" x-model="p.tags" maxlength="3000" placeholder="Ex.: #economia #mercado #dicas">
                <span class="text-[11px] texto-2" x-show="!crConta(p).muitasTags">Separe por espaço. Elas vão no fim da legenda quando você copia.</span>
                <span class="text-[11px] text-red-500" x-show="crConta(p).muitasTags">O Instagram aceita até 30 hashtags.</span>
            </label>

            <label class="cr-campo">
                <span class="cr-rot">Link <span class="texto-2 font-normal">(opcional)</span></span>
                <input class="campo !py-0.5 text-xs" x-model="p.link" maxlength="300" inputmode="url" placeholder="Ex.: https://www.youtube.com/watch?v=...">
                <span class="text-[11px] texto-2">Cole o endereço depois de publicar. Se for do YouTube, as visualizações aparecem aqui.</span>
            </label>
            <template x-if="crYtDoPost(b, p)">
                <p class="text-xs texto-2" x-text="'YouTube: ' + crYtNum(crYtDoPost(b, p).views) + ' visualizações · ' + crYtNum(crYtDoPost(b, p).curtidas) + ' curtidas · ' + crYtNum(crYtDoPost(b, p).comentarios) + ' comentários'"></p>
            </template>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <button type="button" class="btn !py-0.5" @click="crCopiar(p)" :disabled="!crTexto(p)">Copiar legenda + hashtags</button>
                <span class="texto-2">Tudo é salvo sozinho.</span>
                <button type="button" class="btn-sec !py-0.5 ml-auto" @click="crUI(b).aberto = null">Pronto</button>
            </div>
        </div>
    </template>
</div>
