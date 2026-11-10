@extends('layouts.app')

@section('titulo', 'Vagas em cartões')
@section('subtitulo', 'Deslize para a direita para se candidatar com o seu portfólio, ou para a esquerda para descartar. No computador: ← e →.')

@section('conteudo')
<style>
    .vc-abas { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: 1rem; }
    .vc-aba { padding: .4rem .85rem; border-radius: 999px; border: 1px solid var(--linha); background: var(--superficie); color: var(--tinta); font-size: .875rem; font-weight: 600; }
    .vc-aba.on { background: var(--prim); border-color: var(--prim); color: var(--on-prim, #fff); }
    .vc-palco { display: grid; grid-template-columns: minmax(0, 420px) minmax(0, 1fr); gap: 1.5rem; align-items: start; }
    @media (max-width: 860px) { .vc-palco { grid-template-columns: minmax(0, 1fr); } }
    .vc-pilha { position: relative; height: min(560px, 68vh); min-height: 400px; max-width: 420px; width: 100%; margin: 0 auto; }
    @media (max-width: 640px) { .vc-pilha { height: 56vh; min-height: 340px; } .vc-botoes { margin-top: 1rem; } .vc-titulo { font-size: 1.2rem; } }
    .vc-cartao { position: absolute; inset: 0; display: flex; flex-direction: column; border-radius: 1.25rem; background: var(--superficie); border: 1px solid var(--linha);
        box-shadow: 0 14px 36px rgba(0,0,0,.18); overflow: hidden; user-select: none; -webkit-user-select: none; touch-action: pan-y; cursor: grab; will-change: transform; }
    .vc-cartao:active { cursor: grabbing; }
    .vc-capa { padding: 1.1rem 1.2rem .9rem; background: linear-gradient(135deg, color-mix(in srgb, var(--prim) 26%, var(--superficie)), color-mix(in srgb, var(--prim) 6%, var(--superficie))); border-bottom: 1px solid var(--linha); }
    .vc-empresa { font-size: .78rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--tinta-2); }
    .vc-titulo { font-size: 1.4rem; line-height: 1.15; font-weight: 800; margin-top: .25rem; overflow-wrap: anywhere; }
    .vc-tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .6rem; }
    .vc-tag { font-size: .74rem; padding: .12rem .55rem; border-radius: 999px; background: var(--superficie); border: 1px solid var(--linha); }
    .vc-tag.forte { background: rgba(34,197,94,.14); border-color: rgba(34,197,94,.55); font-weight: 600; }
    .vc-corpo { flex: 1; min-height: 0; overflow: auto; padding: .9rem 1.2rem 1rem; font-size: .9rem; display: flex; flex-direction: column; gap: .7rem; }
    .vc-corpo h3 { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--tinta-2); }
    .vc-corpo ul { padding-left: 1.1rem; list-style: disc; display: grid; gap: .15rem; }
    .vc-corta { display: -webkit-box; -webkit-line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
    .vc-carimbo { position: absolute; top: 1.4rem; padding: .25rem .7rem; border: 4px solid; border-radius: .6rem; font-size: 1.5rem; font-weight: 900; letter-spacing: .06em; pointer-events: none; background: var(--superficie); }
    .vc-carimbo.sim { left: 1.2rem; color: #16a34a; border-color: #16a34a; transform: rotate(-14deg); }
    .vc-carimbo.nao { right: 1.2rem; color: #dc2626; border-color: #dc2626; transform: rotate(14deg); }
    .vc-botoes { display: flex; justify-content: center; align-items: center; gap: 1.1rem; margin-top: 1.4rem; }
    .vc-bt { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; border: 2px solid var(--linha); background: var(--superficie); font-weight: 800; box-shadow: 0 6px 16px rgba(0,0,0,.12); transition: transform .12s; }
    .vc-bt:hover:not(:disabled) { transform: scale(1.08); }
    .vc-bt:disabled { opacity: .45; }
    .vc-bt.nao { width: 3.9rem; height: 3.9rem; font-size: 1.5rem; color: #dc2626; border-color: rgba(220,38,38,.55); }
    .vc-bt.sim { width: 3.9rem; height: 3.9rem; font-size: 1.6rem; color: #16a34a; border-color: rgba(22,163,74,.55); }
    .vc-bt.volta { width: 2.8rem; height: 2.8rem; font-size: 1.1rem; color: #d97706; border-color: rgba(217,119,6,.5); }
    .vc-dica { text-align: center; font-size: .75rem; color: var(--tinta-2); margin-top: .6rem; }
    .vc-dica kbd { padding: 0 .3rem; border: 1px solid var(--linha); border-radius: .25rem; font-family: inherit; background: var(--superficie-2); }
    .vc-vazio { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: .6rem; padding: 1.5rem; border: 2px dashed var(--linha); border-radius: 1.25rem; }
    .vc-painel { border: 1px solid var(--linha); border-radius: 1rem; background: var(--superficie); padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: .6rem; }
    .vc-alerta { border-radius: .8rem; padding: .7rem .9rem; font-size: .875rem; background: rgba(245,158,11,.13); border: 1px solid rgba(245,158,11,.5); }
    .vc-aviso { position: fixed; left: 50%; bottom: 1.25rem; transform: translateX(-50%); z-index: 60; padding: .6rem 1rem; border-radius: .8rem; background: var(--tinta); color: var(--papel, #fff); font-size: .875rem; box-shadow: 0 10px 30px rgba(0,0,0,.25); max-width: calc(100vw - 2rem); }
    .vc-aviso.ok { background: #15803d; color: #fff; } .vc-aviso.erro { background: #b91c1c; color: #fff; }
    .vc-item { border: 1px solid var(--linha); border-radius: .8rem; padding: .75rem .9rem; display: flex; flex-direction: column; gap: .3rem; background: var(--superficie); }
    .vc-st { font-size: .72rem; padding: .08rem .5rem; border-radius: 999px; border: 1px solid var(--linha); background: var(--superficie-2); white-space: nowrap; }
    .vc-st.entrevista { background: rgba(59,130,246,.15); border-color: rgba(59,130,246,.55); }
    .vc-st.contratada { background: rgba(34,197,94,.16); border-color: rgba(34,197,94,.6); }
    .vc-st.recusada { opacity: .7; }
    .vc-grade { display: grid; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); gap: .7rem; }
    .vc-campo { display: flex; flex-direction: column; gap: .25rem; font-size: .82rem; font-weight: 600; min-width: 0; }
    .vc-erro { color: #dc2626; font-size: .75rem; font-weight: 500; }
    .vc-lista { display: grid; gap: .6rem; }
    .vc-cand { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; align-items: start; padding: .55rem 0; border-top: 1px solid var(--linha); font-size: .85rem; }
</style>

<div x-data="vagasCartoes(@js([
        'fila' => $fila, 'minhas' => $minhas, 'publicadas' => $publicadas, 'descartadas' => $descartadas,
        'portfolio' => $portfolio, 'status' => $status,
        'urls' => [
            'responder' => route('vagas.cartoes.responder', ['vaga' => '__ID__']),
            'desfazer'  => route('vagas.cartoes.desfazer', ['vaga' => '__ID__']),
            'alternar'  => route('vagas.cartoes.alternar', ['vaga' => '__ID__']),
            'excluir'   => route('vagas.cartoes.excluir', ['vaga' => '__ID__']),
            'status'    => route('vagas.cartoes.status', ['resposta' => '__ID__']),
            'publicar'  => route('vagas.cartoes.publicar'),
            'rever'     => route('vagas.cartoes.rever'),
        ],
     ]))" x-init="iniciar()">

    <nav class="vc-abas" aria-label="Seções das vagas">
        <button type="button" class="vc-aba" :class="aba === 'deslizar' && 'on'" @click="aba = 'deslizar'">Deslizar vagas</button>
        <button type="button" class="vc-aba" :class="aba === 'minhas' && 'on'" @click="aba = 'minhas'" x-text="'Minhas candidaturas (' + minhas.length + ')'"></button>
        <button type="button" class="vc-aba" :class="aba === 'publicar' && 'on'" @click="aba = 'publicar'" x-text="'Publicar vaga' + (publicadas.length ? ' · ' + totalCandidatos() + ' candidatos' : '')"></button>
    </nav>

    {{-- ================= deslizar ================= --}}
    <section x-show="aba === 'deslizar'" class="vc-palco" aria-label="Vagas para deslizar">
        <div>
            <div class="vc-pilha" aria-live="polite">
                <template x-for="(v, i) in fila.slice(0, 3)" :key="v.id">
                    <article class="vc-cartao" :style="estiloCartao(i)" :aria-hidden="i > 0 ? 'true' : 'false'"
                             @pointerdown="i === 0 && pegar($event)" :aria-label="v.titulo + ' na ' + v.empresa">
                        <div class="vc-capa">
                            <p class="vc-empresa" x-text="v.empresa"></p>
                            <h2 class="vc-titulo" x-text="v.titulo"></h2>
                            <div class="vc-tags">
                                <span class="vc-tag forte" x-show="v.salario" x-text="v.salario"></span>
                                <span class="vc-tag" x-text="v.regime"></span>
                                <span class="vc-tag" x-text="v.modalidade"></span>
                                <span class="vc-tag" x-show="v.cidade" x-text="v.cidade"></span>
                            </div>
                        </div>
                        <div class="vc-corpo">
                            <p :class="!(i === 0 && detalhes) && 'vc-corta'" x-text="v.descricao" style="white-space: pre-line"></p>
                            <template x-if="v.requisitos.length">
                                <div><h3>O que pedem</h3>
                                    <ul><template x-for="(r, k) in (i === 0 && detalhes ? v.requisitos : v.requisitos.slice(0, 3))" :key="k"><li x-text="r"></li></template></ul></div>
                            </template>
                            <template x-if="v.beneficios && i === 0 && detalhes"><div><h3>Benefícios</h3><p x-text="v.beneficios" style="white-space: pre-line"></p></div></template>
                            <div class="flex flex-wrap items-center gap-2 text-xs texto-2 mt-auto">
                                <span x-text="'Publicada ' + (v.quando || '')"></span>
                                <a x-show="v.link" :href="v.link" target="_blank" rel="noopener nofollow" class="link-prim">Página da vaga ↗</a>
                                <button type="button" class="link-prim ml-auto" x-show="i === 0" @click="detalhes = !detalhes" x-text="detalhes ? 'Menos detalhes' : 'Mais detalhes'"></button>
                            </div>
                        </div>
                        <span class="vc-carimbo sim" x-show="i === 0" :style="'opacity:' + intensidade('direita')">QUERO</span>
                        <span class="vc-carimbo nao" x-show="i === 0" :style="'opacity:' + intensidade('esquerda')">PASSO</span>
                    </article>
                </template>
                <div class="vc-vazio" x-show="!fila.length" x-cloak>
                    <p class="text-3xl" aria-hidden="true">🎉</p>
                    <p class="font-semibold">Você viu todas as vagas abertas</p>
                    <p class="text-sm texto-2">Volte depois: novas vagas entram aqui assim que são publicadas.</p>
                    <button type="button" class="btn-sec" x-show="descartadas" @click="rever()" x-text="'Rever as ' + descartadas + ' descartadas'"></button>
                    <button type="button" class="btn" @click="aba = 'publicar'">Publicar uma vaga</button>
                </div>
            </div>
            <div class="vc-botoes">
                <button type="button" class="vc-bt nao" :disabled="!topo" @click="decidir('descartou')" title="Descartar (←)" aria-label="Descartar a vaga">✕</button>
                <button type="button" class="vc-bt volta" :disabled="!hist.length" @click="desfazer()" title="Desfazer (Z)" aria-label="Desfazer o último">↺</button>
                <button type="button" class="vc-bt sim" :disabled="!topo" @click="decidir('candidatou')" title="Candidatar (→)" aria-label="Candidatar-se à vaga">✓</button>
            </div>
            <p class="vc-dica">Arraste o cartão · <kbd>←</kbd> descarta · <kbd>→</kbd> candidata · <kbd>↑</kbd> detalhes · <kbd>Z</kbd> desfaz</p>
        </div>

        <aside class="flex flex-col gap-3">
            <div class="vc-alerta" x-show="!podeCandidatar">
                <p class="font-semibold">Seu portfólio é o seu currículo</p>
                <p class="mt-1" x-text="portfolio ? 'Seu portfólio ainda não está publicado. Publique para poder se candidatar.' : 'Você ainda não montou o portfólio. Ele leva uns minutos e vai junto em cada candidatura.'"></p>
                <a class="btn inline-block mt-2" href="{{ route('portfolio.editor') }}">Abrir o portfólio</a>
            </div>
            <div class="vc-painel" x-show="podeCandidatar">
                <p class="text-sm"><b>Currículo:</b> <a class="link-prim" :href="portfolio && portfolio.url" target="_blank" rel="noopener" x-text="portfolio && portfolio.url"></a></p>
                <p class="text-xs texto-2">A empresa vê seu nome, seu e-mail, seu portfólio e a mensagem (se escrever).</p>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="comMsg"> Mandar uma mensagem na próxima candidatura</label>
                <textarea class="campo text-sm" rows="3" maxlength="1000" x-show="comMsg" x-model="msg" placeholder="Ex.: Moro perto e posso começar já na próxima semana."></textarea>
            </div>
            <div class="vc-painel text-sm">
                <p><b x-text="fila.length"></b> <span x-text="fila.length === 1 ? 'vaga na pilha' : 'vagas na pilha'"></span> · <b x-text="minhas.length"></b> candidaturas · <b x-text="descartadas"></b> descartadas</p>
                <p class="text-xs texto-2">Errou o lado? Use ↺ para desfazer. As candidaturas ficam em “Minhas candidaturas”, com o andamento que a empresa marcar.</p>
            </div>
        </aside>
    </section>

    {{-- ================= minhas candidaturas ================= --}}
    <section x-show="aba === 'minhas'" x-cloak class="vc-lista" aria-label="Minhas candidaturas">
        <p class="texto-2 text-sm" x-show="!minhas.length">Nenhuma candidatura ainda. Deslize uma vaga para a direita para se candidatar.</p>
        <template x-for="m in minhas" :key="m.vaga.id">
            <article class="vc-item">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold" x-text="m.vaga.titulo"></h3>
                    <span class="texto-2 text-sm" x-text="'· ' + m.vaga.empresa"></span>
                    <span class="vc-st ml-auto" :class="m.status" x-text="statusNomes[m.status] || m.status"></span>
                </div>
                <p class="text-xs texto-2" x-text="[m.vaga.regime, m.vaga.modalidade, m.vaga.cidade, m.vaga.salario].filter(Boolean).join(' · ') + ' · enviada em ' + m.quando"></p>
                <p class="text-sm" x-show="m.mensagem" x-text="'“' + m.mensagem + '”'"></p>
                <div class="flex flex-wrap gap-3 text-xs">
                    <a class="link-prim" x-show="m.portfolio" :href="m.portfolio" target="_blank" rel="noopener">Portfólio enviado ↗</a>
                    <a class="link-prim" x-show="m.vaga.link" :href="m.vaga.link" target="_blank" rel="noopener nofollow">Página da vaga ↗</a>
                    <span class="texto-2" x-show="!m.vaga.ativa">Vaga encerrada</span>
                    <button type="button" class="link-prim ml-auto" x-show="m.status === 'enviada' || m.status === 'vista'" @click="retirar(m)">Retirar candidatura</button>
                </div>
            </article>
        </template>
    </section>

    {{-- ================= publicar e acompanhar ================= --}}
    <section x-show="aba === 'publicar'" x-cloak class="flex flex-col gap-4" aria-label="Publicar vagas">
        <form class="vc-painel" @submit.prevent="publicar()" novalidate>
            <h2 class="font-semibold">Publicar uma vaga</h2>
            <p class="text-xs texto-2">A vaga entra na pilha de quem procura emprego no Work Flower. Quem deslizar para a direita aparece aqui embaixo, com o portfólio.</p>
            <div class="vc-grade">
                <label class="vc-campo">Cargo <input class="campo" x-model="nova.titulo" maxlength="120" placeholder="Ex.: Auxiliar administrativo"><span class="vc-erro" x-text="erros.titulo"></span></label>
                <label class="vc-campo">Empresa <input class="campo" x-model="nova.empresa" maxlength="120"><span class="vc-erro" x-text="erros.empresa"></span></label>
                <label class="vc-campo">Cidade <input class="campo" x-model="nova.cidade" maxlength="120" placeholder="Ex.: Recife - PE"></label>
                <label class="vc-campo">Salário <input class="campo" x-model="nova.salario" maxlength="80" placeholder="Ex.: R$ 2.200 + VT + VR"></label>
                <label class="vc-campo">Regime
                    <select class="campo" x-model="nova.regime">@foreach ($regimes as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach</select></label>
                <label class="vc-campo">Modalidade
                    <select class="campo" x-model="nova.modalidade">@foreach ($modalidades as $k => $n)<option value="{{ $k }}">{{ $n }}</option>@endforeach</select></label>
            </div>
            <label class="vc-campo">Descrição <textarea class="campo" rows="4" x-model="nova.descricao" maxlength="4000" placeholder="O que a pessoa vai fazer, horário, onde fica"></textarea><span class="vc-erro" x-text="erros.descricao"></span></label>
            <div class="vc-grade">
                <label class="vc-campo">Requisitos <span class="text-xs texto-2 font-normal">(um por linha)</span><textarea class="campo" rows="3" x-model="nova.requisitos" maxlength="2000"></textarea></label>
                <label class="vc-campo">Benefícios <textarea class="campo" rows="3" x-model="nova.beneficios" maxlength="1000"></textarea></label>
            </div>
            <label class="vc-campo">Link da vaga (opcional) <input class="campo" type="url" x-model="nova.link" maxlength="500" placeholder="https://"><span class="vc-erro" x-text="erros.link"></span></label>
            <div><button class="btn" :disabled="publicando" x-text="publicando ? 'Publicando…' : 'Publicar vaga'"></button></div>
        </form>

        <div class="vc-lista">
            <h2 class="font-semibold" x-show="publicadas.length">Vagas que publiquei</h2>
            <template x-for="v in publicadas" :key="v.id">
                <article class="vc-item">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="font-semibold text-left" @click="abertaPub = abertaPub === v.id ? null : v.id" x-text="v.titulo + ' · ' + v.empresa"></button>
                        <span class="vc-st" x-text="v.ativa ? 'Aberta' : 'Encerrada'"></span>
                        <span class="text-xs texto-2 ml-auto" x-text="v.candidatos.length + ' candidatos · ' + v.descartes + ' passaram'"></span>
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs">
                        <button type="button" class="link-prim" @click="abertaPub = abertaPub === v.id ? null : v.id" x-text="abertaPub === v.id ? 'Esconder candidatos' : 'Ver candidatos'"></button>
                        <button type="button" class="link-prim" @click="alternar(v)" x-text="v.ativa ? 'Encerrar' : 'Reabrir'"></button>
                        <button type="button" class="link-prim" @click="excluir(v)">Excluir</button>
                    </div>
                    <div x-show="abertaPub === v.id">
                        <p class="text-sm texto-2" x-show="!v.candidatos.length">Ninguém se candidatou ainda.</p>
                        <template x-for="c in v.candidatos" :key="c.id">
                            <div class="vc-cand">
                                <div class="min-w-0">
                                    <p class="font-semibold" x-text="c.nome"></p>
                                    <p class="text-xs texto-2"><a class="link-prim" :href="'mailto:' + c.email" x-text="c.email"></a> · <span x-text="c.quando"></span></p>
                                    <p class="text-sm mt-1" x-show="c.mensagem" x-text="'“' + c.mensagem + '”'"></p>
                                    <a class="link-prim text-xs" x-show="c.portfolio" :href="c.portfolio" target="_blank" rel="noopener">Ver portfólio (currículo) ↗</a>
                                </div>
                                <select class="campo text-xs" :value="c.status" @change="mudarStatus(c, $event.target.value)" aria-label="Andamento da candidatura">
                                    <template x-for="(nome, k) in statusNomes" :key="k"><option :value="k" x-text="nome" :selected="k === c.status"></option></template>
                                </select>
                            </div>
                        </template>
                    </div>
                </article>
            </template>
        </div>
    </section>

    <div class="vc-aviso" :class="avisoTipo" x-show="aviso" x-transition x-cloak role="status" x-text="aviso"></div>
</div>
<script src="{{ asset('js/vagas-cartoes.js') }}?v={{ @filemtime(public_path('js/vagas-cartoes.js')) }}"></script>
@endsection
