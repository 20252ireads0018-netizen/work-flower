{{-- Barra "Compartilhar" das áreas (Corpo, Mente, Carteira, Diverso). Lógica em public/js/compartilhar.js;
     servidor em App\Http\Controllers\CompartilharController e App\Support\Compartilhar.
     Uso: @include('partials.compartilhar', ['area' => 'diverso']) logo no começo do conteúdo da página. --}}
<div x-data="wfCompartilhar(@js($area))" class="wf-comp mb-4 space-y-3" x-cloak>
    {{-- workflow de outra pessoa aberto --}}
    <template x-if="vendoOutro">
        <div class="aviso-ok flex flex-wrap items-center gap-x-3 gap-y-2 text-sm" role="status">
            <span>👥 Você está no workflow de <b x-text="donoNome"></b>. Tudo o que você muda fica salvo para as pessoas dele.</span>
            <a class="link-prim" :href="location.pathname">Voltar para o meu</a>
            <button type="button" class="link-prim" @click="sairDoWorkflow()">Sair deste workflow</button>
        </div>
    </template>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" x-show="!vendoOutro" @click="alternar()" :aria-expanded="aberto">
            👥 Compartilhar <span class="texto-2" x-show="membros.length" x-text="'(' + membros.length + ')'"></span>
        </button>
        <template x-if="comigo.length">
            <select class="campo !py-1.5 text-sm max-w-[18rem]" aria-label="Workflows compartilhados comigo"
                    @change="abrirComigo(comigo[$event.target.value]); $event.target.value = ''">
                <option value="" x-text="'Compartilhados comigo (' + comigoAtivos.length + ')…'"></option>
                <template x-for="(c, i) in comigo" :key="c.dono + c.area">
                    <option :value="i" :disabled="!c.ativo" x-text="c.areaNome + ' de ' + (c.nome || 'outra conta') + (c.ativo ? '' : ' (sem acesso agora)')"></option>
                </template>
            </select>
        </template>
    </div>

    {{-- painel do dono: quem tem acesso a esta área --}}
    <div x-show="aberto && !vendoOutro" x-transition class="card p-4 space-y-3 max-w-xl" @keydown.escape.window="aberto = false">
        <div>
            <h2 class="titulo text-lg">Pessoas neste workflow</h2>
            <p class="text-xs texto-2 mt-1">Quem você adicionar vê e edita esta área com você, ao mesmo tempo. Mudanças das outras pessoas aparecem sozinhas em poucos segundos. A pessoa precisa ter uma conta no Work Flower.</p>
        </div>
        <p class="text-sm" x-text="textoLimite()"></p>
        <p class="text-xs" style="color:#f87171" x-show="semAcesso > 0"
           x-text="semAcesso + (semAcesso === 1 ? ' pessoa está' : ' pessoas estão') + ' sem acesso porque o seu plano não comporta mais gente. Assine um plano maior para liberar.'"></p>

        <ul class="space-y-1.5" x-show="membros.length">
            <template x-for="m in membros" :key="m.id">
                <li class="flex items-center gap-2 text-sm">
                    <span class="flex-1 min-w-0 truncate"><b x-text="m.nome"></b> <span class="texto-2" x-text="m.email"></span></span>
                    <span class="text-xs texto-2" x-show="!m.ativo">sem acesso (plano)</span>
                    <button type="button" class="mini-btn perigo" @click="remover(m)" :aria-label="'Tirar o acesso de ' + m.nome">✕</button>
                </li>
            </template>
        </ul>
        <p class="text-sm texto-2" x-show="!membros.length">Só você tem acesso a este workflow por enquanto.</p>

        <form class="flex flex-wrap gap-2" @submit.prevent="adicionar()">
            <input type="email" class="campo flex-1 min-w-[12rem]" placeholder="E-mail da pessoa" x-model="email" aria-label="E-mail da pessoa" :disabled="enviando">
            <button type="submit" class="btn" :disabled="enviando" x-text="enviando ? 'Adicionando…' : 'Adicionar'"></button>
        </form>
        <p class="erro text-sm" x-show="erro" x-text="erro"></p>
        <p class="text-sm" style="color:#4ade80" x-show="ok" x-text="ok"></p>
        <a class="link-prim text-sm" x-show="cheio" href="{{ \Illuminate\Support\Facades\Route::has('planos') ? route('planos') : url('/planos') }}">Ver planos para trabalhar com mais pessoas</a>
    </div>
</div>
