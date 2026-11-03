{{-- Ambiente do workflow aberto: tocador da música (canto inferior esquerdo) e, para o dono, o painel para escolher
     o fundo e a música da aba aberta (setor) ou da área inteira. Lógica em public/js/ambiente.js (wfAmbiente).
     Recebe: $amb (App\Support\Ambiente::paraPagina da área), $ambUrls, $ambArea, $ambEdita (só o dono muda) e
     $ambExtra (ambiente de cada setor, fundo geral da conta, nome da área e o dono quando é um workflow compartilhado). --}}
<div x-data="wfAmbiente(@js(['amb' => $amb, 'urls' => $ambUrls, 'area' => $ambArea, 'edita' => $ambEdita, 'extra' => $ambExtra ?? []]))" x-cloak
     class="wf-amb" x-show="temMusica || edita" @keydown.escape.window="aberto && cancelar()" @abrir-ambiente.window="edita && abrir()"
     @wf-setor.window="mudouSetor($event.detail)">
    <style>
        /* ---------- tocador ---------- */
        .wf-amb-pilula { position: fixed; z-index: 41; left: max(1rem, var(--seg-e, 0px)); bottom: calc(1rem + var(--seg-b, 0px));
            display: flex; align-items: center; gap: .35rem; padding: .3rem; border-radius: 9999px;
            background: color-mix(in srgb, var(--superficie) 88%, transparent); border: 1px solid var(--linha);
            box-shadow: 0 8px 28px rgba(0,0,0,.28); backdrop-filter: blur(10px); max-width: calc(100vw - 6rem); transition: box-shadow .2s, border-color .2s; }
        .wf-amb-pilula.tocando { border-color: color-mix(in srgb, var(--prim) 45%, var(--linha)); box-shadow: 0 8px 28px rgba(0,0,0,.28), 0 0 0 3px color-mix(in srgb, var(--prim) 14%, transparent); }
        .wf-amb-redondo { flex: none; width: 2.1rem; height: 2.1rem; border-radius: 9999px; display: grid; place-items: center;
            color: var(--tinta); background: var(--superficie-2); border: 1px solid var(--linha); transition: background .15s, transform .15s; }
        .wf-amb-redondo:hover { background: var(--prim-suave); }
        .wf-amb-redondo:active { transform: scale(.94); }
        .wf-amb-play { background: var(--prim); color: var(--on-prim); border-color: transparent; }
        .wf-amb-play:hover { background: var(--prim-forte); }
        .wf-amb-redondo svg { width: 1rem; height: 1rem; }
        .wf-amb-info { display: flex; flex-direction: column; min-width: 0; line-height: 1.15; padding: 0 .2rem; }
        .wf-amb-nome { font-size: .8rem; font-weight: 600; max-width: 12rem; overflow: hidden; white-space: nowrap; display: flex; align-items: center; gap: .4rem; }
        .wf-amb-nome .txt { overflow: hidden; text-overflow: ellipsis; }
        .wf-amb-onde { font-size: .68rem; color: var(--tinta-2); max-width: 12rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wf-amb-eq { display: inline-flex; align-items: flex-end; gap: 2px; height: .7rem; flex: none; }
        .wf-amb-eq i { width: 2px; height: 30%; background: var(--prim-forte); border-radius: 1px; }
        .wf-amb-pilula.tocando .wf-amb-eq i { animation: wf-amb-eq .9s ease-in-out infinite; }
        .wf-amb-pilula.tocando .wf-amb-eq i:nth-child(2) { animation-delay: -.3s; }
        .wf-amb-pilula.tocando .wf-amb-eq i:nth-child(3) { animation-delay: -.6s; }
        @keyframes wf-amb-eq { 0%, 100% { height: 25%; } 50% { height: 100%; } }
        @media (prefers-reduced-motion: reduce) { .wf-amb-pilula.tocando .wf-amb-eq i { animation: none; height: 70%; } }
        .wf-amb-vol { width: 4.5rem; accent-color: var(--prim); }
        .wf-amb-editar { display: inline-flex; align-items: center; gap: .4rem; height: 2.1rem; padding: 0 .75rem; border-radius: 9999px; flex: none;
            font-size: .78rem; font-weight: 600; color: var(--tinta); background: var(--superficie-2); border: 1px solid var(--linha); transition: background .15s; }
        .wf-amb-editar:hover { background: var(--prim-suave); }
        .wf-amb-editar svg { width: 1rem; height: 1rem; flex: none; }
        .wf-amb-editar.so-icone { width: 2.1rem; padding: 0; justify-content: center; }
        .wf-amb-yt { position: fixed; z-index: 41; left: max(1rem, var(--seg-e, 0px)); bottom: calc(4.1rem + var(--seg-b, 0px));
            width: 220px; aspect-ratio: 16 / 9; border-radius: .75rem; overflow: hidden; border: 1px solid var(--linha); background: #000;
            box-shadow: 0 8px 28px rgba(0,0,0,.3); transition: opacity .2s; }
        .wf-amb-yt.escondido { opacity: 0; pointer-events: none; }
        @media (max-width: 640px) { .wf-amb-vol, .wf-amb-onde { display: none; } .wf-amb-nome { max-width: 7rem; } }

        /* ---------- painel ---------- */
        .wf-amb-painel { position: fixed; z-index: 60; top: 0; right: 0; bottom: 0; width: min(26rem, 100vw); display: flex; flex-direction: column;
            background: var(--superficie); border-left: 1px solid var(--linha); box-shadow: -16px 0 48px rgba(0,0,0,.35); }
        .wf-amb-topo { display: flex; align-items: center; gap: .75rem; padding: 1.1rem 1.25rem .9rem; border-bottom: 1px solid var(--linha); }
        .wf-amb-selo { width: 2.5rem; height: 2.5rem; flex: none; border-radius: .8rem; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--prim), color-mix(in srgb, var(--prim) 45%, #7c3aed)); color: var(--on-prim); }
        .wf-amb-selo svg { width: 1.3rem; height: 1.3rem; }
        .wf-amb-corpo { flex: 1; overflow: auto; padding: 1rem 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .wf-amb-rodape { display: flex; align-items: center; justify-content: flex-end; gap: .5rem; padding: .85rem 1.25rem calc(.85rem + var(--seg-b, 0px));
            border-top: 1px solid var(--linha); background: var(--superficie); }
        .wf-amb-h { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--tinta-2); margin-bottom: .6rem; }
        .wf-amb-dica { font-size: .78rem; color: var(--tinta-2); line-height: 1.45; }

        .wf-amb-escopo { display: grid; grid-template-columns: 1fr 1fr; gap: .25rem; padding: .25rem; border-radius: .85rem; background: var(--superficie-2); border: 1px solid var(--linha); }
        .wf-amb-escopo button { display: flex; flex-direction: column; align-items: flex-start; gap: .05rem; padding: .5rem .7rem; border-radius: .65rem; text-align: left; transition: background .15s; min-width: 0; }
        .wf-amb-escopo button:hover { background: color-mix(in srgb, var(--prim) 8%, transparent); }
        .wf-amb-escopo button[aria-selected="true"] { background: var(--superficie); box-shadow: 0 1px 4px rgba(0,0,0,.25), inset 0 0 0 1px var(--prim-linha); }
        .wf-amb-escopo .t { font-size: .8rem; font-weight: 700; }
        .wf-amb-escopo .s { font-size: .72rem; color: var(--tinta-2); max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .wf-amb-previa { position: relative; aspect-ratio: 16 / 8; border-radius: .9rem; overflow: hidden; border: 1px solid var(--linha); isolation: isolate; flex: none; }
        .wf-amb-previa-img, .wf-amb-previa-veu { position: absolute; inset: 0; z-index: -1; }
        .wf-amb-previa-img { background-position: center; }
        .wf-amb-previa-cartoes { position: absolute; left: 8%; right: 8%; top: 16%; display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 6%; align-items: start; }
        .wf-amb-previa-cartoes i { height: 2.6rem; border-radius: .45rem; background: color-mix(in srgb, var(--superficie) 85%, transparent); border: 1px solid color-mix(in srgb, var(--linha) 80%, transparent); }
        .wf-amb-previa-cartoes i:first-child { height: 3.6rem; }
        .wf-amb-previa-musica { position: absolute; left: .6rem; bottom: .6rem; max-width: calc(100% - 1.2rem); font-size: .7rem; font-weight: 600; padding: .2rem .55rem; border-radius: 9999px;
            background: color-mix(in srgb, var(--superficie) 88%, transparent); border: 1px solid var(--linha); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .wf-amb-opcoes { display: grid; grid-template-columns: repeat(auto-fit, minmax(5.2rem, 1fr)); gap: .45rem; }
        .wf-amb-op { display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: .7rem .35rem .6rem; border-radius: .8rem; text-align: center;
            background: var(--superficie-2); border: 1px solid var(--linha); font-size: .76rem; font-weight: 600; transition: border-color .15s, background .15s, transform .15s; }
        .wf-amb-op:hover { border-color: var(--prim-linha); transform: translateY(-1px); }
        .wf-amb-op[aria-pressed="true"] { border-color: var(--prim); background: var(--prim-suave); box-shadow: inset 0 0 0 1px var(--prim); }
        .wf-amb-op .ic { width: 2rem; height: 2rem; border-radius: .6rem; display: grid; place-items: center; background: var(--superficie); border: 1px solid var(--linha); color: var(--prim-forte); }
        .wf-amb-op .ic svg { width: 1.05rem; height: 1.05rem; }
        .wf-amb-op .bola { width: 1rem; height: 1rem; border-radius: 9999px; border: 1px solid var(--linha); }
        .wf-amb-opcoes.quatro { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .35rem; }
        .wf-amb-opcoes.quatro .wf-amb-op { font-size: .72rem; padding-inline: .2rem; }
        .wf-amb-bloco { margin-top: .75rem; display: flex; flex-direction: column; gap: .75rem; }

        .wf-amb-cores { display: flex; flex-wrap: wrap; gap: .45rem; }
        .wf-amb-cor { position: relative; width: 2rem; height: 2rem; border-radius: 9999px; border: 2px solid var(--linha); cursor: pointer; transition: transform .15s; }
        .wf-amb-cor:hover { transform: scale(1.08); }
        .wf-amb-cor[aria-pressed="true"] { border-color: var(--tinta); box-shadow: 0 0 0 3px var(--prim-linha); }
        .wf-amb-cor-livre { display: grid; place-items: center; background: conic-gradient(#f43f5e, #f59e0b, #22c55e, #06b6d4, #6366f1, #d946ef, #f43f5e); }
        .wf-amb-cor-livre input { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .wf-amb-cor-livre span { width: 1rem; height: 1rem; border-radius: 9999px; background: var(--superficie); display: grid; place-items: center; font-size: .75rem; line-height: 1; pointer-events: none; }

        .wf-amb-solta { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem; min-height: 6rem; padding: .9rem; border-radius: .9rem;
            border: 1.5px dashed var(--borda-campo); background: var(--superficie-2); text-align: center; cursor: pointer; transition: border-color .15s, background .15s; overflow: hidden; }
        .wf-amb-solta:hover, .wf-amb-solta.arrastando { border-color: var(--prim); background: var(--prim-suave); }
        .wf-amb-solta img { max-height: 7rem; max-width: 100%; border-radius: .5rem; object-fit: cover; }
        .wf-amb-solta b { font-size: .82rem; }
        .wf-amb-solta small { font-size: .7rem; color: var(--tinta-2); }
        .wf-amb-solta.ocupado { opacity: .6; pointer-events: none; }

        .wf-amb-seg { display: inline-flex; padding: .2rem; gap: .2rem; border-radius: .7rem; background: var(--superficie-2); border: 1px solid var(--linha); }
        .wf-amb-seg button { padding: .3rem .65rem; border-radius: .5rem; font-size: .74rem; font-weight: 600; color: var(--tinta-2); }
        .wf-amb-seg button[aria-pressed="true"] { background: var(--superficie); color: var(--tinta); box-shadow: 0 1px 3px rgba(0,0,0,.25); }

        .wf-amb-faixa { display: flex; flex-direction: column; gap: .3rem; }
        .wf-amb-faixa label { display: flex; justify-content: space-between; font-size: .78rem; font-weight: 600; }
        .wf-amb-faixa label span { color: var(--tinta-2); font-weight: 500; }
        .wf-amb-faixa input[type="range"] { width: 100%; accent-color: var(--prim); }

        .wf-amb-arquivo { display: flex; align-items: center; gap: .6rem; padding: .55rem .7rem; border-radius: .75rem; background: var(--superficie-2); border: 1px solid var(--linha); }
        .wf-amb-arquivo .ic { width: 2rem; height: 2rem; flex: none; border-radius: .55rem; display: grid; place-items: center; background: var(--prim-suave); color: var(--prim-forte); }
        .wf-amb-arquivo .ic svg { width: 1rem; height: 1rem; }
        .wf-amb-yt-cartao { display: flex; gap: .65rem; align-items: center; padding: .5rem; border-radius: .75rem; background: var(--superficie-2); border: 1px solid var(--linha); }
        .wf-amb-yt-cartao img { width: 6.5rem; aspect-ratio: 16 / 9; object-fit: cover; border-radius: .45rem; flex: none; background: #000; }

        .wf-amb-chave { position: relative; width: 2.3rem; height: 1.3rem; flex: none; border-radius: 9999px; background: var(--borda-campo); transition: background .15s; }
        .wf-amb-chave::after { content: ''; position: absolute; top: .15rem; left: .15rem; width: 1rem; height: 1rem; border-radius: 9999px; background: #fff; transition: transform .15s; }
        .wf-amb-chave.ligada { background: var(--prim); }
        .wf-amb-chave.ligada::after { transform: translateX(1rem); }
    </style>

    {{-- vídeo do YouTube (o tocador precisa ficar visível; dá para esconder) --}}
    <div class="wf-amb-yt" x-show="atual && atual.tipo === 'youtube' && ytPronto" :class="{ 'escondido': !mostrarVideo || !tocando }">
        <div x-ref="yt"></div>
    </div>
    <audio x-ref="audio" loop preload="none"></audio>

    <div class="wf-amb-pilula" :class="{ 'tocando': tocando }" role="group" aria-label="Música e fundo deste workflow">
        <template x-if="temMusica">
            <button type="button" class="wf-amb-redondo wf-amb-play" @click="alternar()" :aria-label="tocando ? 'Pausar a música' : 'Tocar a música'" :title="tocando ? 'Pausar' : 'Tocar'">
                <svg x-show="!tocando" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 001.5.86l10.5-6.5a1 1 0 000-1.72L9.5 4.64A1 1 0 008 5.5z"/></svg>
                <svg x-show="tocando" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1.2"/><rect x="14" y="5" width="4" height="14" rx="1.2"/></svg>
            </button>
        </template>
        <template x-if="temMusica">
            <div class="wf-amb-info">
                <span class="wf-amb-nome" :class="{ 'erro': ytErro }" :title="ytErro || nomeMusica()">
                    <span class="wf-amb-eq" aria-hidden="true" x-show="!ytErro"><i></i><i></i><i></i></span>
                    <span class="txt" x-text="ytErro ? '⚠ ' + ytErro : nomeMusica()"></span>
                </span>
                <span class="wf-amb-onde" x-text="ondeMusica()"></span>
            </div>
        </template>
        <template x-if="temMusica">
            <input type="range" class="wf-amb-vol" min="0" max="100" :value="volume" @input="mudarVolume(+$event.target.value)" aria-label="Volume">
        </template>
        <template x-if="atual && atual.tipo === 'youtube' && tocando">
            <button type="button" class="wf-amb-redondo" @click="mostrarVideo = !mostrarVideo" :title="mostrarVideo ? 'Esconder o vídeo' : 'Mostrar o vídeo'" aria-label="Mostrar ou esconder o vídeo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M10 9.5v5l4-2.5z" fill="currentColor"/></svg>
            </button>
        </template>
        <template x-if="edita">
            <button type="button" class="wf-amb-editar" :class="{ 'so-icone': temMusica }" @click="abrir()" title="Fundo e música" aria-label="Fundo e música">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 100 18c1 0 1.6-.8 1.6-1.6 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.9.7-1.6 1.6-1.6H16a5 5 0 005-5C21 6.6 17 3 12 3z"/><circle cx="7.5" cy="11" r="1" fill="currentColor"/><circle cx="10" cy="7" r="1" fill="currentColor"/><circle cx="15" cy="7.5" r="1" fill="currentColor"/></svg>
                <span x-show="!temMusica">Fundo e música</span>
            </button>
        </template>
    </div>

    {{-- Painel do dono --}}
    <template x-if="edita">
        <aside class="wf-amb-painel" x-show="aberto" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-6"
               x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 translate-x-6" role="dialog" aria-modal="false" aria-labelledby="wf-amb-titulo">
            <div class="wf-amb-topo">
                <div class="wf-amb-selo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="wf-amb-titulo" class="titulo text-lg leading-tight">Fundo e música</h2>
                    <p class="text-xs texto-2 mt-0.5">O clima de cada parte do seu workflow. Quem você convidou vê e ouve o mesmo.</p>
                </div>
                <button type="button" class="wf-amb-redondo" @click="cancelar()" aria-label="Fechar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="wf-amb-corpo">
                {{-- onde vale: só a aba aberta (setor) ou a área inteira --}}
                <div class="space-y-2">
                    <div class="wf-amb-escopo" role="tablist" aria-label="Onde vale" x-show="fSetor">
                        <button type="button" role="tab" :aria-selected="escopo === 'setor'" @click="mudarEscopo('setor')">
                            <span class="t">Só nesta aba</span>
                            <span class="s" x-text="fSetorNome || 'Aba aberta'"></span>
                        </button>
                        <button type="button" role="tab" :aria-selected="escopo === 'area'" @click="mudarEscopo('area')">
                            <span class="t">Na área toda</span>
                            <span class="s" x-text="nomeArea"></span>
                        </button>
                    </div>
                    <p class="wf-amb-dica" x-text="dicaEscopo()"></p>
                </div>

                {{-- prévia --}}
                <div class="wf-amb-previa" :style="{ backgroundColor: previaFundo().fu.cor || PAPEL }" aria-hidden="true">
                    <div class="wf-amb-previa-img" :style="estiloPreviaImg()"></div>
                    <div class="wf-amb-previa-veu" :style="estiloPreviaVeu()"></div>
                    <div class="wf-amb-previa-cartoes"><i></i><i></i><i></i></div>
                    <span class="wf-amb-previa-musica" x-text="'♪ ' + resumoMusicaForm()"></span>
                </div>

                {{-- fundo --}}
                <section>
                    <h3 class="wf-amb-h">Fundo</h3>
                    <div class="wf-amb-opcoes">
                        <button type="button" class="wf-amb-op" :aria-pressed="f.modo === 'herdar'" @click="modoFundo('herdar')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg></span>
                            <span x-text="escopo === 'setor' ? 'Igual à área' : 'Fundo geral'"></span>
                        </button>
                        <button type="button" class="wf-amb-op" :aria-pressed="f.modo === 'cor'" @click="modoFundo('cor')">
                            <span class="ic"><span class="bola" :style="{ background: f.fundo.cor || CORES[0] }"></span></span>
                            <span>Cor</span>
                        </button>
                        <button type="button" class="wf-amb-op" :aria-pressed="f.modo === 'imagem'" @click="modoFundo('imagem')">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-8 8"/></svg></span>
                            <span>Imagem ou GIF</span>
                        </button>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.modo === 'herdar'">
                        <p class="wf-amb-dica" x-text="escopo === 'setor' ? 'Esta aba usa o fundo da área ' + nomeArea + '.' : 'A área usa o fundo geral da sua conta (o de ⚙ › Cores e fundo).'"></p>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.modo === 'cor' || f.modo === 'imagem'">
                        <div x-show="f.modo === 'imagem'" class="space-y-3">
                            <label class="wf-amb-solta" :class="{ 'arrastando': arrastando === 'img' }"
                                   @dragover.prevent="arrastando = 'img'" @dragleave="arrastando = null" @drop.prevent="arrastando = null; escolherImagem($event.dataTransfer.files[0])">
                                <input type="file" accept="image/*" class="sr-only" @change="escolherImagem($event.target.files[0]); $event.target.value = ''">
                                <template x-if="urlImagemForm()"><img :src="urlImagemForm()" alt=""></template>
                                <b x-text="urlImagemForm() ? 'Trocar a imagem' : 'Solte uma imagem aqui ou clique'"></b>
                                <small>JPG, PNG ou GIF e WebP animados (até 4 MB)</small>
                            </label>
                            <div class="flex flex-wrap items-center justify-between gap-2" x-show="urlImagemForm()">
                                <div class="wf-amb-seg" role="group" aria-label="Como encaixar a imagem">
                                    <button type="button" :aria-pressed="f.fundo.ajuste === 'cover'" @click="f.fundo.ajuste = 'cover'; previa()">Preencher</button>
                                    <button type="button" :aria-pressed="f.fundo.ajuste === 'contain'" @click="f.fundo.ajuste = 'contain'; previa()">Inteira</button>
                                    <button type="button" :aria-pressed="f.fundo.ajuste === 'repeat'" @click="f.fundo.ajuste = 'repeat'; previa()">Repetir</button>
                                </div>
                                <button type="button" class="link-prim text-xs" @click="tirarImagem()">Remover imagem</button>
                            </div>
                            <div class="wf-amb-faixa" x-show="urlImagemForm()">
                                <label><b>Escurecer</b><span x-text="f.fundo.escurecer + '%'"></span></label>
                                <input type="range" min="0" max="90" step="5" :value="f.fundo.escurecer" @input="f.fundo.escurecer = +$event.target.value; previa()" aria-label="Escurecer">
                            </div>
                            <div class="wf-amb-faixa" x-show="urlImagemForm()">
                                <label><b>Desfoque</b><span x-text="f.fundo.desfoque + ' px'"></span></label>
                                <input type="range" min="0" max="20" step="1" :value="f.fundo.desfoque" @input="f.fundo.desfoque = +$event.target.value; previa()" aria-label="Desfoque">
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold mb-2" x-text="f.modo === 'imagem' ? 'Cor por trás da imagem' : 'Escolha a cor'"></p>
                            <div class="wf-amb-cores">
                                <template x-for="c in CORES" :key="c">
                                    <button type="button" class="wf-amb-cor" :style="{ background: c }" :aria-pressed="f.fundo.cor === c" @click="f.fundo.cor = c; previa()" :aria-label="'Cor ' + c"></button>
                                </template>
                                <label class="wf-amb-cor wf-amb-cor-livre" title="Outra cor" :aria-pressed="!!f.fundo.cor && !CORES.includes(f.fundo.cor)">
                                    <input type="color" :value="f.fundo.cor || '#0e1319'" @input="f.fundo.cor = $event.target.value; previa()" aria-label="Outra cor">
                                    <span>+</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- música --}}
                <section>
                    <h3 class="wf-amb-h">Música</h3>
                    <div class="wf-amb-opcoes" :class="{ 'quatro': escopo === 'setor' }">
                        <template x-if="escopo === 'setor'">
                            <button type="button" class="wf-amb-op" :aria-pressed="f.musica.tipo === 'herdar'" @click="f.musica.tipo = 'herdar'">
                                <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg></span>
                                <span>Igual à área</span>
                            </button>
                        </template>
                        <button type="button" class="wf-amb-op" :aria-pressed="f.musica.tipo === 'nenhuma'" @click="f.musica.tipo = 'nenhuma'">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M22 9l-6 6M16 9l6 6"/></svg></span>
                            <span>Silêncio</span>
                        </button>
                        <button type="button" class="wf-amb-op" :aria-pressed="f.musica.tipo === 'arquivo'" @click="f.musica.tipo = 'arquivo'">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></svg></span>
                            <span>Arquivo</span>
                        </button>
                        <button type="button" class="wf-amb-op" :aria-pressed="f.musica.tipo === 'youtube'" @click="f.musica.tipo = 'youtube'">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="5.5" width="19" height="13" rx="4"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor"/></svg></span>
                            <span>YouTube</span>
                        </button>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.musica.tipo === 'herdar'">
                        <p class="wf-amb-dica" x-text="'Toca o mesmo da área ' + nomeArea + ': ' + resumoMusica(amb.musica) + '.'"></p>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.musica.tipo === 'arquivo'">
                        <template x-if="cfgForm().musica.arquivo">
                            <div class="wf-amb-arquivo">
                                <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V6l10-2v12"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="16.5" cy="16" r="2.5"/></svg></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold truncate" x-text="cfgForm().musica.nome || 'Áudio enviado'"></p>
                                    <p class="text-xs texto-2">Toca em laço</p>
                                </div>
                                <button type="button" class="link-prim text-xs" x-show="!enviando" @click="removerAudio()">Apagar</button>
                            </div>
                        </template>
                        <label class="wf-amb-solta" :class="{ 'arrastando': arrastando === 'som', 'ocupado': enviando }"
                               @dragover.prevent="arrastando = 'som'" @dragleave="arrastando = null" @drop.prevent="arrastando = null; enviarAudio($event.dataTransfer.files[0])">
                            <input type="file" accept="audio/*,.mp3,.ogg,.wav,.m4a,.aac,.webm" class="sr-only" @change="enviarAudio($event.target.files[0]); $event.target.value = ''">
                            <b x-text="enviando ? 'Enviando…' : (cfgForm().musica.arquivo ? 'Trocar o áudio' : 'Solte um áudio aqui ou clique')"></b>
                            <small>MP3, OGG, WAV, M4A ou WebM, até 15 MB</small>
                        </label>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.musica.tipo === 'youtube'">
                        <input type="url" class="campo" placeholder="Cole o link: youtube.com/watch?v=… ou youtu.be/…" x-model="f.musica.youtube" aria-label="Link do YouTube">
                        <template x-if="idYoutubeForm()">
                            <div class="wf-amb-yt-cartao">
                                <img :src="'https://i.ytimg.com/vi/' + idYoutubeForm() + '/mqdefault.jpg'" alt="" loading="lazy">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">Vídeo encontrado</p>
                                    <p class="text-xs texto-2">Alguns vídeos não deixam tocar fora do YouTube. Se este não tocar, escolha outro.</p>
                                </div>
                            </div>
                        </template>
                        <p class="erro text-xs" x-show="f.musica.youtube && !idYoutubeForm()">Esse link não parece ser de um vídeo do YouTube.</p>
                    </div>

                    <div class="wf-amb-bloco" x-show="f.musica.tipo === 'arquivo' || f.musica.tipo === 'youtube'">
                        <div class="wf-amb-faixa">
                            <label><b>Volume</b><span x-text="f.musica.volume + '%'"></span></label>
                            <input type="range" min="0" max="100" step="5" x-model.number="f.musica.volume" aria-label="Volume">
                        </div>
                        <button type="button" class="flex items-center gap-3 text-left" role="switch" :aria-checked="f.musica.auto ? 'true' : 'false'" @click="f.musica.auto = !f.musica.auto">
                            <span class="wf-amb-chave" :class="{ 'ligada': f.musica.auto }" aria-hidden="true"></span>
                            <span class="text-sm">Tocar sozinha<span class="block text-xs texto-2">Começa no primeiro clique na página (os navegadores exigem isso).</span></span>
                        </button>
                    </div>
                </section>

                <p class="erro text-sm" x-show="erro" x-text="erro"></p>
            </div>

            <footer class="wf-amb-rodape">
                <button type="button" class="btn-sec" @click="cancelar()">Cancelar</button>
                <button type="button" class="btn" :disabled="salvando || enviando" @click="salvar()" x-text="salvando ? 'Salvando…' : 'Salvar'"></button>
            </footer>
        </aside>
    </template>
</div>
