{{-- Aba Equipe da área Negócios: pessoas dos seus workflows, o que cada uma está fazendo, permissões, bloqueio,
     tarefas delegadas e histórico. Lógica em public/js/equipe.js; servidor em EquipeController e CompartilharController. --}}
<div x-data="wfEquipe(@js(url('/equipe')))" class="space-y-4">
    <template x-if="vendoOutro">
        <p class="card p-4 text-sm">A equipe é de cada dono. Volte para o seu workflow para ver e cuidar das pessoas que você convidou.</p>
    </template>

    <template x-if="!vendoOutro">
        <div class="space-y-4">
            <div class="card p-4 space-y-3">
                <h2 class="titulo text-lg">Convidar para a equipe</h2>
                <p class="text-xs texto-2">A pessoa precisa ter conta no Work Flower. Escolha em quais áreas ela entra; depois você decide se ela pode editar ou só ver.</p>
                <div class="flex flex-wrap gap-2 items-center">
                    <input type="email" class="campo flex-1 min-w-[14rem]" placeholder="E-mail da pessoa" x-model="email" aria-label="E-mail da pessoa">
                    <template x-for="a in areas" :key="a.id">
                        <label class="text-sm flex items-center gap-1"><input type="checkbox" :value="a.id" x-model="convidarEm"> <span x-text="a.nome"></span></label>
                    </template>
                    <button type="button" class="btn" @click="convidar()" :disabled="enviando" x-text="enviando ? 'Convidando…' : 'Convidar'"></button>
                </div>
                <p class="erro text-sm" x-show="erro" x-text="erro"></p>
                <p class="text-sm" style="color:#4ade80" x-show="ok" x-text="ok"></p>
            </div>

            <p class="text-sm texto-2" x-show="carregou && !pessoas.length">Ninguém na sua equipe ainda. Convide alguém acima.</p>

            <div class="grid gap-4 lg:grid-cols-2">
                <template x-for="p in pessoas" :key="p.id">
                    <article class="card p-4 space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="eq-av" :class="{ 'on': p.online.length }" x-text="iniciais(p.nome)" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold truncate" x-text="p.nome"></p>
                                <p class="text-xs texto-2 truncate" x-text="p.email"></p>
                                <p class="text-xs mt-1" :style="p.online.length ? 'color:#4ade80' : ''" x-text="textoAgora(p)"></p>
                                <p class="text-xs" style="color:#f87171" x-show="!p.plano">Sem acesso agora: o seu plano não comporta mais pessoas.</p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <p class="text-xs font-semibold texto-2">Workflows e permissões</p>
                            <template x-for="a in p.areas" :key="a.area">
                                <div class="eq-area">
                                    <span class="font-medium text-sm flex-1" x-text="a.nome"></span>
                                    <label class="text-xs flex items-center gap-1" title="Sem isto a pessoa só vê (e só move as tarefas delegadas a ela)">
                                        <input type="checkbox" :checked="a.pode_editar" :disabled="a.bloqueado" @change="alterar(p, a, { pode_editar: $event.target.checked })"> Editar cartões
                                    </label>
                                    <button type="button" class="btn-sec !py-0.5 text-xs" :style="a.bloqueado ? 'color:#f87171;border-color:#f87171' : ''"
                                            @click="alterar(p, a, { bloqueado: !a.bloqueado })" x-text="a.bloqueado ? 'Bloqueado · desbloquear' : 'Bloquear'"></button>
                                    <button type="button" class="mini-btn perigo" :title="'Tirar ' + p.nome + ' de ' + a.nome" @click="remover(p, a)">✕</button>
                                </div>
                            </template>
                        </div>

                        <div class="space-y-1" x-show="p.tarefas.length">
                            <p class="text-xs font-semibold texto-2">Tarefas delegadas em aberto</p>
                            <template x-for="t in p.tarefas" :key="t.id">
                                <a class="eq-tarefa" :href="t.url">
                                    <span class="truncate flex-1" x-text="t.titulo"></span>
                                    <span class="text-xs texto-2 shrink-0" x-text="t.area + ' · ' + t.status + (t.prazo ? ' · ' + t.prazo : '')"></span>
                                </a>
                            </template>
                        </div>
                        <p class="text-xs texto-2" x-show="!p.tarefas.length">Nenhuma tarefa delegada em aberto. Delegue pelo campo "Responsável" das tarefas de cada área.</p>
                    </article>
                </template>
            </div>

            <div class="card p-4 space-y-2">
                <div class="flex items-center gap-2">
                    <h2 class="titulo text-lg flex-1">Colaborações recentes</h2>
                    <button type="button" class="btn-sec !py-1 text-sm" @click="carregar()">Atualizar</button>
                </div>
                <p class="text-sm texto-2" x-show="!atividades.length">Ainda não há mudanças registradas nos seus workflows compartilhados.</p>
                <ul class="space-y-1.5 text-sm">
                    <template x-for="(a, i) in atividades" :key="i">
                        <li class="flex flex-wrap gap-x-2">
                            <b x-text="a.quem"></b>
                            <span x-text="a.acao"></span>
                            <span class="texto-2" x-text="'em ' + a.area + (a.aba ? ' › ' + a.aba : '')"></span>
                            <span class="texto-2 text-xs ml-auto" x-text="quando(a.em)"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </template>
</div>
<style>
    .eq-av { width: 2.4rem; height: 2.4rem; border-radius: 9999px; display: grid; place-items: center; font-weight: 700; font-size: .85rem; flex-shrink: 0;
        background: var(--superficie-2); border: 2px solid var(--linha); }
    .eq-av.on { border-color: #4ade80; }
    .eq-area { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding: .35rem .5rem; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); }
    .eq-tarefa { display: flex; align-items: center; gap: .5rem; padding: .3rem .5rem; border-radius: .4rem; background: var(--superficie-2); font-size: .85rem; }
    .eq-tarefa:hover { outline: 1px solid var(--prim); }
</style>
