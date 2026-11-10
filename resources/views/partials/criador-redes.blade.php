{{-- Cartão "Criador de conteúdo": abas Instagram e TikTok. Lógica em public/js/criador-redes.js (métodos crRd*, crRede*);
     servidor em RedesController. Mostra a conta de quem está vendo, conectada por OAuth (tokens só no servidor) ou
     importada à mão (planilha CSV ou números digitados). Números, posts, a lista e o gráfico vão para o quadro
     arrastando (ou pelo botão ⇱): js/integracoes-quadro.js. --}}
<template x-if="crRedeAberta(b) && !crAberto(b)">
    <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-sm" x-text="'Métricas do ' + crRedeNome(b)"></p>
                <p class="texto-2 truncate" x-show="crSnap(b)" :title="crSnap(b) ? (crSnap(b).conta.nome || '') : ''"
                   x-text="crSnap(b) ? ((crSnap(b).conta.usuario ? '@' + crSnap(b).conta.usuario : (crSnap(b).conta.nome || 'Sua conta')) + (crSnap(b).manual ? ' · importado em ' : ' · lido em ') + crRdQuando(b)) : ''"></p>
            </div>
            <button type="button" class="btn !py-0.5" x-show="crSnap(b) && !crSnap(b).manual" :disabled="crRdUI(b).carregando"
                    @click="crRede(b, crUI(b).ver, true)" x-text="crRdUI(b).carregando ? 'Buscando…' : 'Atualizar'"></button>
            <a class="btn !py-0.5" x-show="crRdUI(b).conectar && (!crSnap(b) || crSnap(b).manual)" :href="crRdUI(b).conectar" x-text="'Conectar ' + crRedeNome(b)"></a>
            <button type="button" class="btn-sec !py-0.5" :class="crRdUI(b).importar && 'qz-on'" @click="crRdAbrirImportar(b)">Importar números</button>
            <button type="button" class="btn-sec !py-0.5" x-show="crSnap(b) && !crSnap(b).manual" @click="crRedeSair(b)">Desconectar</button>
            <button type="button" class="btn-sec !py-0.5" x-show="crUI(b).ver === 'ig' && crPubTem()" :class="crPubUI(b).aberto && 'qz-on'" @click="crPubAbrir(b)">Publicar no Instagram</button>
        </div>

        <p class="text-red-500 break-words" x-show="crRdUI(b).erro && !crRdUI(b).importar && !crPubUI(b).aberto" x-text="crRdUI(b).erro"></p>

        {{-- publicar foto ou carrossel (InstagramPublicarController): a permissão de publicar só é pedida aqui --}}
        <template x-if="crUI(b).ver === 'ig' && crPubUI(b).aberto">
            <form class="qz-ed" style="gap:.4rem" @submit.prevent="crPubEnviar(b)">
                <p class="font-semibold">Publicar no Instagram</p>
                <p class="texto-2" x-show="crPubUI(b).carregando">Conferindo a conta…</p>
                <template x-if="crPubUI(b).estado && !crPubUI(b).estado.conectado">
                    <div class="flex flex-col gap-1.5">
                        <p class="texto-2" x-text="crPubUI(b).estado.configurado ? 'Conecte a conta Profissional do Instagram (ligada a uma Página do Facebook) e permita publicar conteúdo.' : 'A publicação pelo Instagram ainda não foi ativada neste site.'"></p>
                        <a class="btn !py-0.5 self-start" x-show="crPubConectar(b)" :href="crPubConectar(b)">Conectar e permitir publicar</a>
                    </div>
                </template>
                <template x-if="crPubUI(b).estado && crPubUI(b).estado.conectado && !crPubUI(b).estado.pode">
                    <div class="flex flex-col gap-1.5">
                        <p class="texto-2">Para publicar daqui, o Facebook precisa de mais uma permissão: “publicar conteúdo” no Instagram<span x-show="crPubUI(b).estado.usuario" x-text="' @' + crPubUI(b).estado.usuario"></span>. As métricas continuam como estão.</p>
                        <a class="btn !py-0.5 self-start" :href="crPubConectar(b)">Permitir publicar</a>
                    </div>
                </template>
                <template x-if="crPubUI(b).estado && crPubUI(b).estado.pode">
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(x, i) in crPubUI(b).imagens" :key="x.url">
                                <div class="cr-pub-img">
                                    <img :src="x.url" :alt="'Imagem ' + (i + 1)">
                                    <span class="cr-pub-n" x-text="i + 1"></span>
                                    <span class="cr-pub-acoes">
                                        <button type="button" class="mini-btn" title="Mais para a esquerda" aria-label="Mover para a esquerda" x-show="i > 0" @click="crPubMover(b, i, -1)">‹</button>
                                        <button type="button" class="mini-btn" title="Tirar" aria-label="Tirar a imagem" @click="crPubTirar(b, i)">×</button>
                                        <button type="button" class="mini-btn" title="Mais para a direita" aria-label="Mover para a direita" x-show="i < crPubUI(b).imagens.length - 1" @click="crPubMover(b, i, 1)">›</button>
                                    </span>
                                </div>
                            </template>
                            <label class="cr-pub-mais" x-show="crPubUI(b).imagens.length < 10" title="Escolher imagens">
                                <span x-text="crPubUI(b).imagens.length ? '+' : '+ Imagens'"></span>
                                <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" multiple @change="crPubArquivos(b, $event)">
                            </label>
                        </div>
                        <p class="texto-2">Uma imagem vira foto; de 2 a 10 viram carrossel, nesta ordem. JPG, PNG ou WebP até 8 MB, de 4:5 (retrato) a 1,91:1 (paisagem).</p>
                        <select class="campo !py-0.5 !text-xs" x-show="crPubPosts(b).length" x-model="crPubUI(b).post" @change="crPubUsarPost(b)" aria-label="Usar a legenda de um post do Criador">
                            <option value="">Usar a legenda de um post do Criador…</option>
                            <template x-for="x in crPubPosts(b)" :key="x.id"><option :value="x.id" x-text="(x.titulo || 'Sem título') + ' · ' + x.etapa"></option></template>
                        </select>
                        <textarea class="campo text-xs" rows="4" x-model="crPubUI(b).legenda" maxlength="2200" placeholder="Legenda, hashtags e menções" aria-label="Legenda"></textarea>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button class="btn !py-0.5" :disabled="crPubUI(b).enviando || !crPubUI(b).imagens.length"
                                    x-text="crPubUI(b).enviando ? 'Publicando…' : (crPubUI(b).imagens.length > 1 ? 'Publicar carrossel' : 'Publicar foto')"></button>
                            <span class="texto-2" x-text="crPubUI(b).legenda.length + '/2200'"></span>
                            <span class="texto-2 ml-auto" x-show="crPubUI(b).estado.usuario" x-text="'em @' + crPubUI(b).estado.usuario"></span>
                        </div>
                    </div>
                </template>
                <p class="text-red-500 break-words" x-show="crPubUI(b).erro" x-text="crPubUI(b).erro"></p>
                <p x-show="crPubUI(b).feito">Publicado! <a class="link-prim" x-show="crPubUI(b).feito && crPubUI(b).feito.link" :href="crPubUI(b).feito ? crPubUI(b).feito.link : '#'" target="_blank" rel="noopener">Ver no Instagram ↗</a></p>
                <p class="texto-2">Vídeos e Reels: publique pelo app por enquanto. TikTok: a publicação direta depende da aprovação da API de postagem do TikTok.</p>
            </form>
        </template>
        <style>
            .cr-pub-img { position: relative; width: 4.5rem; height: 4.5rem; border-radius: .5rem; overflow: hidden; border: 1px solid var(--linha); }
            .cr-pub-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .cr-pub-n { position: absolute; left: .2rem; top: .2rem; font-size: .6rem; padding: 0 .3rem; border-radius: .3rem; background: rgba(0, 0, 0, .7); color: #fff; }
            .cr-pub-acoes { position: absolute; inset: auto 0 0 0; display: flex; justify-content: center; gap: .1rem; background: rgba(0, 0, 0, .55); }
            .cr-pub-acoes .mini-btn { color: #fff; }
            .cr-pub-mais { width: 4.5rem; height: 4.5rem; border-radius: .5rem; border: 1px dashed var(--linha); display: grid; place-items: center; cursor: pointer; text-align: center; font-size: .7rem; }
            .cr-pub-mais:hover { border-color: var(--prim); }
        </style>

        {{-- importação manual: planilha exportada da rede ou números digitados --}}
        <form x-show="crRdUI(b).importar" class="qz-ed" style="gap:.4rem" @submit.prevent="crRdImportar(b)">
            <p class="font-semibold" x-text="'Importar números do ' + crRedeNome(b)"></p>
            <p class="texto-2" x-show="crUI(b).ver === 'ig'">Exporte no <b>Meta Business Suite › Insights › Conteúdo › Exportar dados</b> (CSV) ou no app: Painel profissional › Insights. Cole as linhas aqui ou escolha o arquivo. Só os números que você preencher mudam.</p>
            <p class="texto-2" x-show="crUI(b).ver === 'tt'">Exporte no <b>TikTok Studio › Análises › Baixar dados</b> (CSV) ou copie os números do perfil. Cole as linhas aqui ou escolha o arquivo. Só os números que você preencher mudam.</p>
            <div class="grid grid-cols-2 gap-1.5">
                <label class="cr-campo"><span class="cr-rot">@ do perfil</span><input class="campo !py-0.5 text-xs" x-model="crRdUI(b).imp.usuario" maxlength="120" placeholder="seuperfil"></label>
                <label class="cr-campo"><span class="cr-rot">Seguidores</span><input class="campo !py-0.5 text-xs" x-model="crRdUI(b).imp.seguidores" inputmode="numeric" maxlength="30" placeholder="Ex.: 12.400 ou 12,4 mil"></label>
                <label class="cr-campo"><span class="cr-rot">Seguindo</span><input class="campo !py-0.5 text-xs" x-model="crRdUI(b).imp.seguindo" inputmode="numeric" maxlength="30"></label>
                <label class="cr-campo"><span class="cr-rot" x-text="crUI(b).ver === 'ig' ? 'Posts' : 'Vídeos'"></span><input class="campo !py-0.5 text-xs" x-model="crRdUI(b).imp.posts" inputmode="numeric" maxlength="30"></label>
                <label class="cr-campo col-span-2" x-show="crUI(b).ver === 'tt'"><span class="cr-rot">Curtidas no total</span><input class="campo !py-0.5 text-xs" x-model="crRdUI(b).imp.curtidas" inputmode="numeric" maxlength="30"></label>
            </div>
            <label class="cr-campo">
                <span class="cr-rot flex items-center gap-2">Planilha (CSV) <span class="texto-2 font-normal" x-show="crRdUI(b).imp.arquivo" x-text="'· ' + crRdUI(b).imp.arquivo"></span></span>
                <textarea class="campo text-xs font-mono" rows="4" x-model="crRdUI(b).imp.csv" maxlength="2097152"
                          :placeholder="crUI(b).ver === 'ig' ? 'Data;Legenda;Tipo;Curtidas;Comentários;Alcance;Visualizações;Link\n01/11/2026;Dicas de mercado;Reels;520;31;8900;14000;https://instagram.com/p/…' : 'Data;Título;Visualizações;Curtidas;Comentários;Compartilhamentos;Link\n01/11/2026;Receita rápida;12000;800;30;12;https://tiktok.com/@…'"></textarea>
            </label>
            <div class="flex flex-wrap items-center gap-1.5">
                <label class="btn-sec !py-0.5 cursor-pointer">Escolher arquivo…<input type="file" class="sr-only" accept=".csv,.tsv,.txt,text/csv,text/plain" @change="crRdArquivo(b, $event)"></label>
                <button class="btn !py-0.5" :disabled="crRdUI(b).enviando" x-text="crRdUI(b).enviando ? 'Importando…' : 'Importar'"></button>
                <button type="button" class="btn-sec !py-0.5" @click="crRdUI(b).importar = false">Cancelar</button>
                <button type="button" class="btn-sec !py-0.5 perigo ml-auto" x-show="crSnap(b) && crSnap(b).manual" @click="crRdApagarImportacao(b)">Apagar importação</button>
            </div>
            <p class="text-red-500 break-words" x-show="crRdUI(b).erro" x-text="crRdUI(b).erro"></p>
            <p class="texto-2" x-show="crRdUI(b).conectar">Prefere que atualize sozinho? Use “Conectar <span x-text="crRedeNome(b)"></span>”: nada é publicado, o site só lê seguidores e posts.</p>
        </form>

        <template x-if="crSnap(b)">
            <div class="flex flex-col gap-2">
                {{-- números do perfil (cada um arrasta para o quadro como um texto grande) --}}
                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <template x-for="r in crRdResumo(b)" :key="r[1]">
                        <div class="qz-ed ig-arrasta" style="gap:0" draggable="true" title="Arraste para o quadro" @dragstart="crRdArrastar($event, crRdBlocoNumero(b, r))">
                            <b class="text-base" x-text="crRdNum(r[0])"></b><span class="texto-2" x-text="r[1]"></span>
                        </div>
                    </template>
                </div>
                <p class="texto-2" x-show="crRdEngaj(b)">Engajamento médio: <b x-text="crRdEngaj(b)"></b> <span>(curtidas + comentários por visualização)</span></p>

                {{-- gráfico --}}
                <div class="qz-ed ig-arrasta" style="gap:.3rem" x-show="(crSnap(b).itens || []).length || crRdTemHist(b)" draggable="true" title="Arraste o gráfico para o quadro"
                     @dragstart="crRdArrastar($event, crRdBlocoGrafico(b))">
                    <div class="flex flex-wrap items-center gap-1">
                        <button type="button" class="btn-sec !py-0" :class="crRdUI(b).grafico === 'posts' && 'qz-on'" @click="crRdUI(b).grafico = 'posts'" x-text="crUI(b).ver === 'ig' ? 'Últimos posts' : 'Últimos vídeos'"></button>
                        <button type="button" class="btn-sec !py-0" :class="crRdUI(b).grafico === 'seg' && 'qz-on'" x-show="crRdTemHist(b)" @click="crRdUI(b).grafico = 'seg'">Seguidores por dia</button>
                        <button type="button" class="mini-btn ml-auto" title="Pôr o gráfico no quadro" aria-label="Pôr o gráfico no quadro" @click="crRdPor(crRdBlocoGrafico(b))">⇱</button>
                    </div>
                    <div class="gr-area" x-html="crRdGrafico(b)"></div>
                </div>

                {{-- posts / vídeos como cartõezinhos arrastáveis --}}
                <div class="flex items-center gap-1">
                    <p class="font-semibold" x-text="crUI(b).ver === 'ig' ? 'Últimos posts' : 'Últimos vídeos'"></p>
                    <span class="texto-2">· arraste um para o quadro</span>
                    <button type="button" class="btn-sec !py-0 ml-auto" x-show="(crSnap(b).itens || []).length" title="Pôr a lista no quadro como tabela" @click="crRdPor(crRdBlocoLista(b))">⇱ Lista no quadro</button>
                </div>
                <div class="flex flex-col gap-1">
                    <template x-for="v in (crSnap(b).itens || []).slice(0, 30)" :key="v.id">
                        <div class="cx-card ig-arrasta flex items-start gap-2" draggable="true" title="Arraste para o quadro" @dragstart="crRdArrastar($event, crRdBlocoItem(b, v))">
                            <div class="min-w-0 flex-1">
                                <a class="link-prim break-words" x-show="v.link" :href="v.link" target="_blank" rel="noopener" x-text="(v.legenda ?? v.titulo) || '(sem legenda)'"></a>
                                <span class="break-words" x-show="!v.link" x-text="(v.legenda ?? v.titulo) || '(sem legenda)'"></span>
                                <p class="texto-2" x-text="(v.tipo ? v.tipo + ' · ' : '') + String(v.data || '').split('-').reverse().join('/')"></p>
                                <p class="flex flex-wrap gap-x-2 mt-0.5">
                                    <template x-for="(c, i) in crRdColunas(b)" :key="c"><span><b x-text="crRdNum(crRdValores(b, v)[i])"></b> <span class="texto-2" x-text="c.toLowerCase()"></span></span></template>
                                </p>
                            </div>
                            <button type="button" class="mini-btn" title="Pôr no quadro" aria-label="Pôr no quadro" @click="crRdPor(crRdBlocoItem(b, v))">⇱</button>
                        </div>
                    </template>
                </div>
                <p class="texto-2" x-show="!(crSnap(b).itens || []).length" x-text="crSnap(b).manual ? 'Nenhum post importado ainda: cole a planilha em “Importar números”.' : 'A conta ainda não tem posts publicados.'"></p>
                <p class="texto-2" x-show="crUI(b).ver === 'ig' && !crSnap(b).manual">“—” = o Instagram não informou (alcance e views só existem para contas Profissionais e alguns tipos de post).</p>
            </div>
        </template>

        <p class="texto-2" x-show="!crSnap(b) && !crRdUI(b).erro && !crRdUI(b).importar" x-text="crRdUI(b).carregando ? 'Buscando as métricas…' : 'Mostra seguidores e os últimos posts da sua conta, com curtidas, comentários e, quando a rede informa, alcance e visualizações.'"></p>
    </div>
</template>
