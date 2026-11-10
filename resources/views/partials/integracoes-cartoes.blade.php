{{-- Cartões de integração do quadro (todas as áreas): Google Drive, Gmail, YouTube e Lembretes por e-mail.
     Lógica em public/js/integracoes-cartoes.js (métodos ig*); estilo e script em partials/integracoes-cartoes-script.
     Cada item arrasta para o quadro (vira cartão) ou vai pelo botão "No quadro". Incluído dentro do x-for dos blocos. --}}

{{-- ============================== Google Drive ============================== --}}
<template x-if="b.tipo === 'gdrive'">
    <div class="ig" x-init="igIniciar(b)">
        <p class="ig-vazio" x-show="!igUI(b).d && igUI(b).carregando">Abrindo o seu Drive…</p>
        <p class="ig-erro" x-show="igUI(b).erro" x-text="igUI(b).erro"></p>

        <template x-if="igUI(b).d && igUI(b).d.conectado === false">
            <div class="ig-conectar">
                <span class="ig-logo" style="--c:#1fa463" aria-hidden="true">▲</span>
                <p class="font-semibold text-sm">Conecte o Google Drive</p>
                <p class="texto-2">O Work Flower cria uma pasta “Work Flower” no seu Drive e só enxerga o que estiver nela: backups dos workflows e os cartões que você mandar para lá.</p>
                <a class="btn !py-1" x-show="igUI(b).d.conectar" :href="igDriveConectar(b)">Entrar com Google</a>
                <p class="texto-2" x-show="!igUI(b).d.conectar">O Google Drive não está disponível neste Work Flower agora.</p>
            </div>
        </template>

        <template x-if="igUI(b).d && igUI(b).d.conectado">
            <div class="ig-corpo">
                <div class="ig-barra">
                    <div class="min-w-0 flex-1">
                        <p class="ig-titulo truncate" x-text="igUI(b).d.email || 'Google Drive'"></p>
                        <p class="texto-2 truncate" x-text="igUI(b).d.ultimo_backup ? 'Último backup: ' + igDataHora(igUI(b).d.ultimo_backup) : 'Nenhum backup ainda'"></p>
                    </div>
                    <button type="button" class="btn !py-0.5" :disabled="!!igUI(b).ocupado" @click="igDriveBackup(b)" x-text="igUI(b).ocupado === 'backup' ? 'Fazendo…' : 'Backup agora'"></button>
                    <button type="button" class="btn-sec !py-0.5" title="Atualizar a lista" aria-label="Atualizar a lista" @click="igCarregar(b)">↻</button>
                </div>
                <label class="ig-linha-check"><input type="checkbox" :checked="igUI(b).d.backup_diario" @change="igDriveDiario(b, $event.target.checked)"> Backup automático todo dia (madrugada)</label>
                <form class="flex gap-1" @submit.prevent="igCarregar(b)">
                    <input class="campo !py-0.5 text-xs flex-1" type="search" x-model="igUI(b).q" placeholder="Buscar na pasta Work Flower" aria-label="Buscar no Drive">
                </form>
                <ul class="ig-lista">
                    <template x-for="f in igArquivos(b)" :key="f.id">
                        <li class="ig-item" draggable="true" @dragstart="igArrastar($event, b, f)" :title="'Arraste para o quadro para virar cartão'">
                            <img x-show="f.icone" :src="f.icone" alt="" class="ig-ico" loading="lazy" referrerpolicy="no-referrer">
                            <span x-show="!f.icone" class="ig-ico" aria-hidden="true" x-text="igIcone(f)"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium" x-text="f.nome"></p>
                                <p class="texto-2 truncate" x-text="[igTam(f.tamanho), igData(f.modificado)].filter(Boolean).join(' · ')"></p>
                            </div>
                            <div class="ig-acoes">
                                <button type="button" class="mini-btn" title="Trazer para o quadro" aria-label="Trazer para o quadro" @click="igNoQuadro(b, f)">⇲</button>
                                <button type="button" class="mini-btn" title="Renomear" aria-label="Renomear" @click="igDriveRenomear(b, f)">✎</button>
                                <a class="mini-btn" x-show="f.link" :href="f.link" target="_blank" rel="noopener" title="Abrir no Google Drive" aria-label="Abrir no Google Drive">↗</a>
                            </div>
                        </li>
                    </template>
                    <li class="ig-vazio" x-show="!igArquivos(b).length && !igUI(b).carregando" x-text="igUI(b).q ? 'Nada com esse nome.' : 'A pasta está vazia. Faça um backup ou mande um cartão para cá.'"></li>
                </ul>
                <div class="ig-rodape">
                    <select class="campo !py-0.5 !text-xs flex-1 min-w-0" x-model="igUI(b).enviar" aria-label="Cartão para mandar ao Drive">
                        <option value="">Mandar um cartão desta aba para o Drive…</option>
                        <template x-for="c in igCartoesDaAba(b)" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                    </select>
                    <button type="button" class="btn-sec !py-0.5" :disabled="!igUI(b).enviar || !!igUI(b).ocupado" @click="igDriveEnviar(b)" x-text="igUI(b).ocupado === 'enviar' ? 'Enviando…' : 'Enviar'"></button>
                </div>
            </div>
        </template>
    </div>
</template>

{{-- ============================== Gmail ============================== --}}
<template x-if="b.tipo === 'gmail'">
    <div class="ig" x-init="igIniciar(b)">
        <p class="ig-vazio" x-show="!igUI(b).d && igUI(b).carregando">Abrindo a caixa de entrada…</p>
        <p class="ig-erro" x-show="igUI(b).erro" x-text="igUI(b).erro"></p>

        <template x-if="igUI(b).d && igUI(b).d.contas && !igUI(b).d.contas.length">
            <div class="ig-conectar">
                <span class="ig-logo" style="--c:#ea4335" aria-hidden="true">✉</span>
                <p class="font-semibold text-sm">Conecte sua caixa de e-mail</p>
                <p class="texto-2">Entre com a conta Google para ver o Gmail aqui, sem senha de app. O Work Flower só lê: nada é apagado nem marcado como lido.</p>
                <a class="btn !py-1" x-show="igUI(b).d.google" :href="igUI(b).d.google">Entrar com Google</a>
                <a class="btn-sec !py-1" :href="igUI(b).d.pagina">Outra caixa (Titan, cPanel…)</a>
            </div>
        </template>

        <template x-if="igUI(b).d && igUI(b).d.contas && igUI(b).d.contas.length && !igUI(b).aberto">
            <div class="ig-corpo">
                <form class="ig-barra" @submit.prevent="igCarregar(b)">
                    <input class="campo !py-0.5 text-xs flex-1 min-w-0" type="search" x-model="igUI(b).q" placeholder="Buscar remetente ou assunto" aria-label="Buscar e-mails">
                    <select class="campo !py-0.5 !text-xs !w-auto" x-model="b.dados.categoria" @change="igCarregar(b)" aria-label="Categoria">
                        <option value="">Todas</option>
                        <template x-for="c in igUI(b).d.categorias" :key="c"><option :value="c" x-text="c"></option></template>
                    </select>
                    <select class="campo !py-0.5 !text-xs !w-auto" x-show="igUI(b).d.contas.length > 1" x-model="b.dados.conta" @change="igCarregar(b)" aria-label="Caixa">
                        <option value="0">Todas as caixas</option>
                        <template x-for="c in igUI(b).d.contas" :key="c.id"><option :value="String(c.id)" x-text="c.endereco"></option></template>
                    </select>
                    <button type="button" class="btn-sec !py-0.5" title="Ler e-mails novos agora" aria-label="Ler e-mails novos agora" :disabled="!!igUI(b).ocupado" @click="igEmailSincronizar(b)" x-text="igUI(b).ocupado === 'sync' ? '…' : '↻'"></button>
                </form>
                <ul class="ig-lista">
                    <template x-for="m in igMsgs(b)" :key="m.id">
                        <li class="ig-item ig-clique" :class="!m.lido && 'ig-novo'" draggable="true" @dragstart="igArrastar($event, b, m)" @click="igAbrirEmail(b, m)"
                            tabindex="0" @keydown.enter="igAbrirEmail(b, m)" title="Clique para abrir · arraste para o quadro">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate font-medium flex-1" x-text="igDe(m)"></span>
                                    <span class="texto-2 whitespace-nowrap" x-text="igData(m.data)"></span>
                                </div>
                                <p class="truncate" x-text="m.assunto || '(sem assunto)'"></p>
                                <p class="texto-2 truncate" x-text="m.trecho || ''"></p>
                            </div>
                            <span class="ig-pilula" x-show="m.categoria" x-text="m.categoria"></span>
                        </li>
                    </template>
                    <li class="ig-vazio" x-show="!igMsgs(b).length && !igUI(b).carregando">Nenhum e-mail aqui.</li>
                </ul>
                <p class="texto-2">Clique para ler. Arraste um e-mail para o quadro para virar cartão. <a class="link-prim" :href="igUI(b).d.pagina">Regras e categorias</a></p>
            </div>
        </template>

        <template x-if="igUI(b).aberto">
            <div class="ig-corpo">
                <div class="ig-barra">
                    <button type="button" class="btn-sec !py-0.5" @click="igUI(b).aberto = null">← Voltar</button>
                    <span class="flex-1"></span>
                    <button type="button" class="btn-sec !py-0.5" title="Virar cartão de texto" @click="igNoQuadro(b, igUI(b).aberto)">No quadro</button>
                    <button type="button" class="btn-sec !py-0.5" title="Criar uma tarefa nesta área" @click="igEmailTarefa(b, igUI(b).aberto)">Tarefa</button>
                    <button type="button" class="btn-sec !py-0.5" title="Evento amanhã às 9h no calendário" @click="igEmailEvento(b, igUI(b).aberto)">Evento</button>
                </div>
                <div>
                    <p class="ig-titulo" x-text="igUI(b).aberto.assunto || '(sem assunto)'"></p>
                    <p class="texto-2 truncate" x-text="igDe(igUI(b).aberto) + (igUI(b).aberto.de_email && igUI(b).aberto.de_nome ? ' <' + igUI(b).aberto.de_email + '>' : '') + ' · ' + igDataHora(igUI(b).aberto.data)"></p>
                </div>
                <p class="ig-erro" x-show="igUI(b).corpoErro" x-text="igUI(b).corpoErro"></p>
                <div class="ig-leitura" x-text="igUI(b).lendo && !igUI(b).corpo ? 'Abrindo…' : igUI(b).corpo"></div>
            </div>
        </template>
    </div>
</template>

{{-- ============================== YouTube ============================== --}}
<template x-if="b.tipo === 'youtube'">
    <div class="ig" x-init="igIniciar(b)">
        {{-- cartão de um vídeo só (vídeo arrastado de outro cartão YouTube) --}}
        <template x-if="b.dados.video">
            <div class="ig-corpo">
                <div class="ig-player"><iframe :src="igYtEmbed(b.dados.video.id)" title="Vídeo do YouTube" loading="lazy" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                <div class="ig-barra">
                    <p class="truncate flex-1 font-medium" x-text="b.dados.video.titulo"></p>
                    <button type="button" class="btn-sec !py-0.5" @click="igYtPost(b, b.dados.video)" title="Adicionar ao Criador de conteúdo">Post</button>
                    <a class="btn-sec !py-0.5" :href="igYtLink(b.dados.video.id)" target="_blank" rel="noopener">YouTube ↗</a>
                </div>
            </div>
        </template>

        <template x-if="!b.dados.video">
            <div class="ig-corpo">
                <p class="ig-vazio" x-show="!igUI(b).d && igUI(b).carregando">Abrindo o YouTube…</p>
                <p class="ig-erro" x-show="igUI(b).erro" x-text="igUI(b).erro"></p>

                {{-- sem login e sem canal escolhido --}}
                <template x-if="igUI(b).d && !igUI(b).d.conectado && !b.dados.canal">
                    <div class="ig-conectar">
                        <span class="ig-logo" style="--c:#ff0033" aria-hidden="true">▶</span>
                        <p class="font-semibold text-sm">Seu canal do YouTube no quadro</p>
                        <p class="texto-2">Entre com a conta Google para ver seus vídeos (inclusive os não listados) e playlists. Só leitura: nada é publicado.</p>
                        <a class="btn !py-1" x-show="igUI(b).d.conectar" :href="igUI(b).d.conectar">Entrar com Google</a>
                        <form class="flex gap-1 w-full" x-show="igUI(b).d.tem_chave" @submit.prevent="igYtBuscar(b)">
                            <input class="campo !py-0.5 text-xs flex-1" x-model="igUI(b).canal" maxlength="200" placeholder="ou veja um canal público: @canal" aria-label="Canal do YouTube">
                            <button class="btn-sec !py-0.5">Ver</button>
                        </form>
                    </div>
                </template>

                <template x-if="igUI(b).d && igUI(b).d.canal && !igUI(b).aberto">
                    <div class="ig-corpo">
                        <div class="ig-barra">
                            <img x-show="igUI(b).d.canal.foto" :src="igUI(b).d.canal.foto" alt="" class="ig-avatar" loading="lazy" referrerpolicy="no-referrer">
                            <div class="min-w-0 flex-1">
                                <p class="ig-titulo truncate" x-text="igUI(b).d.canal.nome"></p>
                                <p class="texto-2 truncate" x-text="igNum(igUI(b).d.canal.inscritos) + ' inscritos · ' + igNum(igUI(b).d.canal.views) + ' views · ' + igNum(igUI(b).d.canal.videos) + ' vídeos'"></p>
                            </div>
                            <button type="button" class="btn-sec !py-0.5" title="Atualizar" aria-label="Atualizar" @click="igCarregar(b, true)">↻</button>
                        </div>
                        <div class="ig-barra">
                            <button type="button" class="btn-sec !py-0.5" :class="!igUI(b).verPl && !igUI(b).pl && 'qz-on'" @click="igUI(b).verPl = false; igUI(b).pl = null">Vídeos</button>
                            <button type="button" class="btn-sec !py-0.5" :class="(igUI(b).verPl || igUI(b).pl) && 'qz-on'" x-show="(igUI(b).d.playlists || []).length" @click="igUI(b).verPl = true; igUI(b).pl = null">Playlists</button>
                            <span class="flex-1"></span>
                            <button type="button" class="btn-sec !py-0.5" x-show="b.dados.canal && igUI(b).d.conectado" @click="igYtMeu(b)">Meu canal</button>
                            <button type="button" class="btn-sec !py-0.5" x-show="igUI(b).d.conectado && !b.dados.canal" @click="igYtSair(b)">Sair</button>
                        </div>

                        <ul class="ig-lista" x-show="igUI(b).verPl && !igUI(b).pl">
                            <template x-for="p in igUI(b).d.playlists || []" :key="p.id">
                                <li class="ig-item ig-clique" tabindex="0" @click="igYtPlaylist(b, p)" @keydown.enter="igYtPlaylist(b, p)">
                                    <img x-show="p.thumb" :src="p.thumb" alt="" class="ig-thumb" loading="lazy" referrerpolicy="no-referrer">
                                    <div class="min-w-0 flex-1"><p class="truncate font-medium" x-text="p.titulo"></p><p class="texto-2" x-text="p.qtd + (p.qtd === 1 ? ' vídeo' : ' vídeos')"></p></div>
                                </li>
                            </template>
                        </ul>

                        <template x-if="!igUI(b).verPl || igUI(b).pl">
                            <div class="ig-corpo">
                                <div class="ig-barra" x-show="igUI(b).pl">
                                    <button type="button" class="btn-sec !py-0.5" @click="igUI(b).pl = null">←</button>
                                    <p class="truncate font-medium flex-1" x-text="igUI(b).pl ? igUI(b).pl.titulo : ''"></p>
                                </div>
                                <ul class="ig-lista">
                                    <template x-for="v in igYtVideos(b)" :key="v.id">
                                        <li class="ig-item ig-clique" draggable="true" @dragstart="igArrastar($event, b, v)" tabindex="0" @click="igUI(b).aberto = v" @keydown.enter="igUI(b).aberto = v"
                                            title="Clique para assistir · arraste para o quadro">
                                            <span class="ig-thumb-caixa">
                                                <img x-show="v.thumb" :src="v.thumb || ''" alt="" class="ig-thumb" loading="lazy" referrerpolicy="no-referrer">
                                                <span x-show="!v.thumb" class="ig-thumb" aria-hidden="true"></span>
                                                <span class="ig-duracao" x-show="v.duracao" x-text="v.duracao"></span>
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="ig-2linhas font-medium" x-text="v.titulo"></p>
                                                <p class="texto-2 truncate" x-text="igNum(v.views) + ' views · ' + igNum(v.curtidas) + ' curtidas · ' + (v.publicado || '').split('-').reverse().join('/') + (v.privacidade && v.privacidade !== 'public' ? ' · ' + (v.privacidade === 'unlisted' ? 'não listado' : 'privado') : '')"></p>
                                            </div>
                                        </li>
                                    </template>
                                    <li class="ig-vazio" x-show="igUI(b).pl && igUI(b).pl.carregando">Abrindo a playlist…</li>
                                    <li class="ig-vazio" x-show="!igYtVideos(b).length && !(igUI(b).pl && igUI(b).pl.carregando)">Nenhum vídeo.</li>
                                </ul>
                            </div>
                        </template>
                        <form class="flex gap-1" x-show="igUI(b).d.tem_chave || igUI(b).d.conectado" @submit.prevent="igYtBuscar(b)">
                            <input class="campo !py-0.5 text-xs flex-1" x-model="igUI(b).canal" maxlength="200" placeholder="Ver outro canal: @canal ou link" aria-label="Outro canal do YouTube">
                            <button class="btn-sec !py-0.5">Ver</button>
                        </form>
                    </div>
                </template>

                {{-- vídeo aberto dentro do cartão --}}
                <template x-if="igUI(b).aberto">
                    <div class="ig-corpo">
                        <div class="ig-player"><iframe :src="igYtEmbed(igUI(b).aberto.id)" title="Vídeo do YouTube" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                        <p class="font-medium ig-2linhas" x-text="igUI(b).aberto.titulo"></p>
                        <div class="ig-barra">
                            <button type="button" class="btn-sec !py-0.5" @click="igUI(b).aberto = null">← Voltar</button>
                            <span class="flex-1"></span>
                            <button type="button" class="btn-sec !py-0.5" @click="igNoQuadro(b, igUI(b).aberto)" title="Cartão só com este vídeo">No quadro</button>
                            <button type="button" class="btn-sec !py-0.5" @click="igYtPost(b, igUI(b).aberto)" title="Adicionar ao Criador de conteúdo desta aba">Post no Criador</button>
                            <a class="btn-sec !py-0.5" :href="igYtLink(igUI(b).aberto.id)" target="_blank" rel="noopener">↗</a>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>

{{-- ============================== Lembretes por e-mail ============================== --}}
<template x-if="b.tipo === 'lembretes'">
    <div class="ig" x-init="igIniciar(b)">
        <p class="ig-vazio" x-show="!igUI(b).d && igUI(b).carregando">Lendo o calendário…</p>
        <p class="ig-erro" x-show="igUI(b).erro" x-text="igUI(b).erro"></p>
        <template x-if="igUI(b).d && igUI(b).d.lembretes">
            <div class="ig-corpo">
                <p class="ig-status" :class="igLbEnvio(b).email && igLbEnvio(b).agendador ? 'ig-ok' : 'ig-espera'"
                   x-text="!igLbEnvio(b).email ? 'O envio por e-mail ainda está sendo ligado neste Work Flower. Por enquanto os lembretes aparecem no navegador; os marcados com ✉ passam a chegar por e-mail assim que ele for ligado.'
                        : !igLbEnvio(b).agendador ? 'Os lembretes com ✉ vão para ' + igUI(b).d.email + ' assim que o envio automático do site voltar a rodar.'
                        : 'Os lembretes com ✉ chegam em ' + igUI(b).d.email + '.'"></p>
                <div class="ig-barra">
                    <button type="button" class="btn !py-0.5" @click="igLbAbrirNovo(b)">+ Lembrete</button>
                    <button type="button" class="btn-sec !py-0.5" @click="igLbTodos(b, true)" title="Ligar o e-mail em todos os lembretes">✉ Todos</button>
                    <button type="button" class="btn-sec !py-0.5" @click="igLbTodos(b, false)" title="Desligar o e-mail em todos os lembretes">Nenhum</button>
                    <button type="button" class="btn-sec !py-0.5" x-show="igLbEnvio(b).email" @click="igLbTeste(b)" title="Mandar um lembrete de exemplo para o seu e-mail">Testar</button>
                    <span class="flex-1"></span>
                    <select class="campo !py-0.5 !text-xs !w-auto" x-model="b.dados.dias" @change="igCarregar(b)" aria-label="Período">
                        <option value="7">7 dias</option><option value="14">14 dias</option><option value="30">30 dias</option><option value="60">60 dias</option>
                    </select>
                </div>

                <template x-if="igUI(b).novo">
                    <form class="ig-form" @submit.prevent="igLbCriar(b)">
                        <select class="campo !py-0.5 !text-xs" @change="igLbAbrirNovo(b, $event.target.value)" aria-label="Lembrar de um cartão">
                            <option value="">Lembrar de um cartão desta aba (opcional)…</option>
                            <template x-for="c in igCartoesDaAba(b)" :key="c.id"><option :value="c.id" x-text="c.nome"></option></template>
                        </select>
                        <input class="campo !py-0.5 text-xs" x-model="igUI(b).novo.titulo" maxlength="120" placeholder="Do que lembrar?" aria-label="Título do lembrete">
                        <div class="flex gap-1 flex-wrap">
                            <input class="campo !py-0.5 text-xs flex-1" type="datetime-local" x-model="igUI(b).novo.quando" aria-label="Quando">
                            <select class="campo !py-0.5 !text-xs !w-auto" x-model.number="igUI(b).novo.antes" aria-label="Avisar quanto antes">
                                <template x-for="a in igAntes()" :key="a[0]"><option :value="a[0]" x-text="a[1]"></option></template>
                            </select>
                        </div>
                        <label class="ig-linha-check"><input type="checkbox" x-model="igUI(b).novo.email"> Avisar também por e-mail</label>
                        <div class="flex gap-1 items-center">
                            <button class="btn !py-0.5">Criar</button>
                            <button type="button" class="btn-sec !py-0.5" @click="igUI(b).novo = null">Cancelar</button>
                            <span class="texto-2" x-text="igUI(b).novo.msg"></span>
                        </div>
                    </form>
                </template>

                <ul class="ig-lista">
                    <template x-for="l in igLembretes(b)" :key="l.chave">
                        <li class="ig-item" draggable="true" @dragstart="igArrastar($event, b, l)" title="Arraste para o quadro para virar cartão">
                            <span class="ig-dia">
                                <b x-text="new Date(l.quando).getDate()"></b>
                                <span x-text="new Date(l.quando).toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '')"></span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium" x-text="l.evento.titulo"></p>
                                <p class="texto-2 truncate" x-text="(l.evento.dia_todo ? 'Dia todo' : new Date(l.quando).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })) + ' · ' + igAntesNome(l.evento.lembrete_min)"></p>
                            </div>
                            <label class="ig-email" :title="l.evento.lembrar_email ? 'Avisa por e-mail' : 'Só no navegador'">
                                <input type="checkbox" :checked="l.evento.lembrar_email" @change="igLbEmail(b, l, $event.target.checked)" aria-label="Avisar por e-mail"> ✉
                            </label>
                            <button type="button" class="mini-btn" title="Virar cartão" aria-label="Virar cartão" @click="igNoQuadro(b, l)">⇲</button>
                        </li>
                    </template>
                    <li class="ig-vazio" x-show="!igLembretes(b).length">Nenhum lembrete no período. Crie um aqui ou no calendário do painel (evento com “Lembrete”).</li>
                </ul>
            </div>
        </template>
    </div>
</template>
