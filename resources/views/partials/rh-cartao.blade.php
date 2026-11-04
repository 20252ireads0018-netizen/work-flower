{{-- Conteúdo do cartão "RH: vagas e candidatos" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos rh*).
     Funil: candidatos por etapa (Triagem → Contratado/Recusado), filtro por vaga. Ficha: avaliação por critérios, entrevista
     no calendário do painel, mensagem pronta da etapa (WhatsApp/e-mail). Integração: checklist dos contratados (e tarefas
     criadas sozinhas na aba Tarefas). Relatório: funil, conversão e tempo por etapa. Ajustes: critérios, mensagens e automações. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm" x-init="rhPreparar(b)">
    <div class="flex flex-wrap items-center gap-1.5 text-xs" x-show="!rhAberto(b)">
        <template x-for="v in [['funil', 'Funil'], ['integra', 'Integração'], ['relatorio', 'Relatório'], ['vagas', 'Vagas'], ['ajustes', 'Ajustes']]" :key="v[0]">
            <button type="button" class="btn-sec !py-0.5" :class="rhUI(b).ver === v[0] && 'qz-on'" @click="rhUI(b).ver = v[0]; rhUI(b).aberto = null" x-text="v[1]"></button>
        </template>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="rhUI(b).vaga" aria-label="Mostrar só uma vaga">
            <option value="">Todas as vagas</option>
            <template x-for="v in (b.dados.vagas || [])" :key="v.id"><option :value="v.id" x-text="v.nome"></option></template>
        </select>
        <button type="button" class="btn !py-0.5 ml-auto" @click="rhUI(b).ver = 'funil'; rhNovo(b)">+ Candidato</button>
    </div>

    {{-- funil --}}
    <template x-if="rhUI(b).ver === 'funil' && !rhAberto(b)">
        <div class="flex-1 min-h-0 flex flex-col gap-1">
            <div class="flex-1 min-h-0 overflow-auto flex gap-2">
                <template x-for="et in rhEtapas()" :key="et">
                    <div class="cx-col">
                        <p class="cx-col-tit"><span x-text="et"></span> <span class="texto-2" x-text="rhDa(b, et).length"></span></p>
                        <template x-for="c in rhDa(b, et)" :key="c.id">
                            <div class="cx-card" role="button" tabindex="0" @click="rhUI(b).aberto = c.id" @keydown.enter="rhUI(b).aberto = c.id">
                                <p class="font-medium leading-tight" x-text="c.nome || 'Sem nome'"></p>
                                <p class="text-[11px] texto-2"><span x-text="rhVaga(b, c.vaga)"></span><span x-show="c.nota" x-text="' · ' + '★'.repeat(c.nota)"></span></p>
                                <p class="text-[11px] rh-agenda" x-show="rhQuando(c) && c.etapa === 'Entrevista'" x-text="'📅 ' + rhQuando(c)"></p>
                                <div class="flex gap-1 mt-1" @click.stop>
                                    <button type="button" class="mini-btn" title="Etapa anterior" aria-label="Etapa anterior" @click="rhMover(b, c, -1)">‹</button>
                                    <button type="button" class="mini-btn" title="Próxima etapa" aria-label="Próxima etapa" @click="rhMover(b, c, 1)">›</button>
                                </div>
                            </div>
                        </template>
                        <p class="text-[11px] texto-2 px-1" x-show="!rhDa(b, et).length">Vazio</p>
                    </div>
                </template>
            </div>
            <p class="text-[11px] texto-2" x-text="(b.dados.cands || []).length ? 'Clique num candidato para abrir a ficha. Em “Entrevista”, o cartão pede a data; em “Contratado”, cria o checklist e as tarefas de integração.' : 'Nenhum candidato ainda. Use “+ Candidato” para cadastrar o primeiro.'"></p>
        </div>
    </template>

    {{-- ficha do candidato --}}
    <template x-for="c in (rhAberto(b) ? [rhAberto(b)] : [])" :key="c.id">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2 text-xs">
            <div class="flex flex-wrap gap-1.5 items-center">
                <button type="button" class="btn-sec !py-0.5" @click="rhUI(b).aberto = null; rhUI(b).agendar = false">‹ Voltar ao funil</button>
                <h3 class="font-semibold text-sm">Ficha do candidato</h3>
                <button type="button" class="btn-sec !py-0.5 perigo ml-auto" @click="rhRemover(b, c)">Excluir</button>
            </div>
            <div class="cx-grade">
                <label class="cr-campo"><span class="cr-rot">Nome</span><input class="campo !py-0.5 text-xs" x-model="c.nome" maxlength="120" placeholder="Nome completo" x-init="!c.nome && $nextTick(() => $el.focus())"></label>
                <label class="cr-campo"><span class="cr-rot">Vaga</span><select class="campo !py-0.5 !text-xs" x-model="c.vaga"><option value="">Sem vaga</option><template x-for="v in (b.dados.vagas || [])" :key="v.id"><option :value="v.id" x-text="v.nome" :selected="v.id === c.vaga"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Etapa</span><select class="campo !py-0.5 !text-xs" :value="c.etapa" @change="rhIrPara(b, c, $event.target.value)"><template x-for="e in rhEtapas()" :key="e"><option :value="e" x-text="e" :selected="e === c.etapa"></option></template></select></label>
                <label class="cr-campo"><span class="cr-rot">Contato (e-mail ou WhatsApp)</span><input class="campo !py-0.5 text-xs" x-model="c.contato" maxlength="120" placeholder="Ex.: (11) 98888-7777 ou nome@email.com"></label>
            </div>

            {{-- entrevista --}}
            <div class="qz-ed">
                <p class="font-semibold flex items-center gap-2">Entrevista
                    <span class="font-normal texto-2" x-show="rhQuando(c)" x-text="'· ' + rhQuando(c) + (c.entrevista && c.entrevista.evento ? ' (no calendário)' : '')"></span></p>
                <template x-if="!rhUI(b).agendar">
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" class="btn !py-0.5" @click="rhAbrirAgenda(b, c)" x-text="rhQuando(c) ? 'Remarcar' : 'Agendar entrevista'"></button>
                        <button type="button" class="btn-sec !py-0.5" x-show="rhQuando(c)" @click="rhDesmarcar(b, c)">Desmarcar</button>
                    </div>
                </template>
                <template x-if="rhUI(b).agendar">
                    <div class="flex flex-wrap items-end gap-1.5">
                        <label class="cr-campo"><span class="cr-rot">Dia e hora</span><input type="datetime-local" class="campo !py-0.5 !text-xs" x-model="rhUI(b).quando"></label>
                        <label class="cr-campo"><span class="cr-rot">Duração</span>
                            <select class="campo !py-0.5 !text-xs" x-model.number="rhUI(b).dur"><option value="30">30 min</option><option value="45">45 min</option><option value="60">1 hora</option><option value="90">1h30</option></select></label>
                        <button type="button" class="btn !py-0.5" @click="rhAgendar(b, c)">Salvar no calendário</button>
                        <button type="button" class="btn-sec !py-0.5" @click="rhUI(b).agendar = false">Cancelar</button>
                    </div>
                </template>
                <p x-show="rhUI(b).msg" x-text="rhUI(b).msg" aria-live="polite"></p>
                <p class="texto-2" x-show="rhUI(b).agendar">Vai para o calendário do painel com lembrete 30 minutos antes.</p>
            </div>

            {{-- mensagem pronta da etapa --}}
            <div class="qz-ed">
                <p class="font-semibold">Mensagem da etapa “<span x-text="c.etapa"></span>”</p>
                <p class="whitespace-pre-wrap rh-msg" x-text="rhMensagem(b, c)"></p>
                <div class="flex flex-wrap gap-1.5 items-center">
                    <a class="btn !py-0.5" x-show="rhLinkWhats(b, c)" :href="rhLinkWhats(b, c)" target="_blank" rel="noopener">Enviar no WhatsApp</a>
                    <a class="btn !py-0.5" x-show="rhLinkEmail(b, c)" :href="rhLinkEmail(b, c)">Enviar por e-mail</a>
                    <span class="texto-2" x-show="!rhLinkWhats(b, c) && !rhLinkEmail(b, c)">Preencha o contato (celular com DDD ou e-mail) para enviar.</span>
                    <button type="button" class="link-prim ml-auto" @click="rhUI(b).aberto = null; rhUI(b).ver = 'ajustes'">Editar os modelos</button>
                </div>
            </div>

            {{-- avaliação --}}
            <div class="qz-ed">
                <p class="font-semibold">Avaliação <span class="font-normal texto-2" x-text="'· média ' + rhMediaTxt(b, c)"></span></p>
                <template x-for="cr in (b.dados.criterios || [])" :key="cr.id">
                    <div class="flex items-center gap-1 flex-wrap">
                        <span class="rh-crit" x-text="cr.nome"></span>
                        <template x-for="n in 5" :key="n">
                            <button type="button" class="rh-estrela" :class="n <= ((c.score || {})[cr.id] || 0) && 'on'" :aria-label="cr.nome + ': nota ' + n" @click="rhNotaCrit(b, c, cr, n)">★</button>
                        </template>
                    </div>
                </template>
                <p class="texto-2" x-show="!(b.dados.criterios || []).length">Sem critérios. Cadastre em Ajustes.</p>
                <p class="texto-2">A nota do candidato (as estrelas no funil) é a média dos critérios.</p>
            </div>
            <label class="cr-campo"><span class="cr-rot">Anotações</span>
                <textarea class="campo text-xs min-h-[5rem]" x-model="c.obs" maxlength="8000" placeholder="Ex.: pretensão salarial, disponibilidade, pontos fortes, dúvidas para a próxima conversa..."></textarea></label>
        </div>
    </template>

    {{-- integração dos contratados --}}
    <template x-if="rhUI(b).ver === 'integra' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2" x-data="{ editar: false }">
            <div class="flex text-xs gap-2 items-center"><span class="texto-2">Ao chegar em “Contratado”, o cartão cria uma tarefa para cada passo na aba Tarefas.</span>
                <button type="button" class="link-prim ml-auto shrink-0" @click="editar = !editar" x-text="editar ? 'Pronto' : 'Editar os passos'"></button></div>
            <template x-if="editar">
                <div class="flex flex-col gap-1">
                    <template x-for="(p, k) in b.dados.passos" :key="k">
                        <div class="flex gap-1"><input class="campo !py-0.5 text-xs flex-1" x-model="b.dados.passos[k]" maxlength="120">
                        <button type="button" class="mini-btn perigo" aria-label="Tirar o passo" @click="b.dados.passos.splice(k, 1)">✕</button></div>
                    </template>
                    <button type="button" class="btn-sec !py-0.5 text-xs self-start" @click="b.dados.passos.length < 40 && b.dados.passos.push('Novo passo')">+ Passo</button>
                </div>
            </template>
            <p x-show="!rhContratados(b).length" class="texto-2 text-center py-6">Quem chegar em “Contratado” aparece aqui com o checklist de integração.</p>
            <template x-for="c in rhContratados(b)" :key="c.id">
                <div class="qz-ed">
                    <p class="flex justify-between gap-2"><b x-text="c.nome || 'Sem nome'"></b><span class="texto-2 text-xs" x-text="rhVaga(b, c.vaga) + ' · ' + rhProgresso(b, c) + '%'"></span></p>
                    <div class="rh-barra"><span :style="'width:' + rhProgresso(b, c) + '%'"></span></div>
                    <template x-for="(p, k) in b.dados.passos" :key="k">
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" :checked="!!(c.feitos || {})[k]" @change="rhPasso(b, c, k)"> <span x-text="p"></span></label>
                    </template>
                    <p class="text-xs texto-2" x-show="c.tarefasCriadas" x-text="c.tarefasCriadas + ' tarefas criadas na aba Tarefas.'"></p>
                    <button type="button" class="btn-sec !py-0.5 text-xs self-start" x-show="!c.tarefasCriadas" @click="rhCriarTarefas(b, c)">Criar tarefas na aba Tarefas</button>
                </div>
            </template>
        </div>
    </template>

    {{-- relatório --}}
    <template x-if="rhUI(b).ver === 'relatorio' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto"><template x-for="r in [rhRelatorio(b)]" :key="1"><div class="flex flex-col gap-3 text-xs">
            <div class="cx-grade text-center">
                <div class="qz-ed !gap-0"><b class="text-base" x-text="r.total"></b><span class="texto-2">candidatos</span></div>
                <div class="qz-ed !gap-0"><b class="text-base" x-text="r.contratados"></b><span class="texto-2">contratados</span></div>
                <div class="qz-ed !gap-0"><b class="text-base" x-text="r.recusados"></b><span class="texto-2">recusados</span></div>
                <div class="qz-ed !gap-0"><b class="text-base" x-text="r.media"></b><span class="texto-2">nota média</span></div>
            </div>
            <div class="qz-ed">
                <p class="font-semibold">Funil: quantos chegaram em cada etapa</p>
                <template x-for="f in r.funil" :key="f.etapa">
                    <div class="rh-funil">
                        <span class="rh-funil-nome" x-text="f.etapa"></span>
                        <span class="rh-funil-barra"><span :style="'width:' + Math.max(2, f.pct) + '%'"></span></span>
                        <span class="rh-funil-num" x-text="f.n + ' · ' + f.pct + '%'"></span>
                    </div>
                </template>
                <p class="texto-2">A porcentagem é em relação a quem entrou na Triagem.</p>
            </div>
            <div class="qz-ed">
                <p class="font-semibold">Tempo médio em cada etapa</p>
                <template x-for="t in r.tempos" :key="t.etapa">
                    <p class="flex justify-between"><span x-text="t.etapa"></span><b x-text="rhDias(t.dias)"></b></p>
                </template>
                <p class="texto-2">Conta a partir das mudanças de etapa feitas no cartão.</p>
            </div>
            <div class="qz-ed" x-show="r.vagas.length">
                <p class="font-semibold">Por vaga</p>
                <template x-for="v in r.vagas" :key="v.nome">
                    <p class="flex justify-between gap-2"><span x-text="v.nome"></span><span class="texto-2" x-text="v.ativos + ' em andamento · ' + v.contratados + ' contratados'"></span></p>
                </template>
            </div>
        </div></template></div>
    </template>

    {{-- vagas --}}
    <template x-if="rhUI(b).ver === 'vagas' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-1.5 text-xs">
            <form class="flex gap-1" @submit.prevent="rhNovaVaga(b, $refs.novaVaga)">
                <input x-ref="novaVaga" class="campo !py-0.5 text-xs flex-1" maxlength="60" placeholder="Nome da vaga (ex.: Vendedor)" aria-label="Nome da nova vaga">
                <button class="btn !py-0.5">Adicionar</button>
            </form>
            <p class="texto-2" x-show="!(b.dados.vagas || []).length">Nenhuma vaga ainda. Escreva o nome acima.</p>
            <template x-for="v in (b.dados.vagas || [])" :key="v.id">
                <div class="flex gap-1 items-center">
                    <input class="campo !py-0.5 text-xs flex-1" x-model="v.nome" maxlength="60" aria-label="Nome da vaga">
                    <span class="texto-2" x-text="(b.dados.cands || []).filter(c => c.vaga === v.id && c.etapa !== 'Recusado').length + ' ativos'"></span>
                    <button type="button" class="mini-btn perigo" aria-label="Tirar a vaga" @click="rhTirarVaga(b, v)">✕</button>
                </div>
            </template>
        </div>
    </template>

    {{-- ajustes: critérios, modelos de mensagem e automações --}}
    <template x-if="rhUI(b).ver === 'ajustes' && !rhAberto(b) && b.dados.modelos">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-3 text-xs">
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Automações</legend>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="b.dados.auto.entrevista"> Ao mover para “Entrevista”, abrir a ficha para agendar no calendário</label>
                <label class="flex items-center gap-1.5"><input type="checkbox" x-model="b.dados.auto.tarefas"> Ao mover para “Contratado”, criar as tarefas de integração na aba Tarefas</label>
            </fieldset>
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Critérios de avaliação</legend>
                <template x-for="(cr, k) in b.dados.criterios" :key="cr.id">
                    <div class="flex gap-1"><input class="campo !py-0.5 text-xs flex-1" x-model="cr.nome" maxlength="60" aria-label="Nome do critério">
                        <button type="button" class="mini-btn perigo" aria-label="Tirar o critério" @click="b.dados.criterios.splice(k, 1)">✕</button></div>
                </template>
                <form class="flex gap-1" @submit.prevent="rhNovoCriterio(b, $refs.novoCrit)">
                    <input x-ref="novoCrit" class="campo !py-0.5 text-xs flex-1" maxlength="60" placeholder="Novo critério (ex.: Pontualidade)" aria-label="Novo critério">
                    <button class="btn-sec !py-0.5">Adicionar</button>
                </form>
            </fieldset>
            <fieldset class="qz-ed">
                <legend class="font-semibold px-1">Mensagens prontas por etapa</legend>
                <p class="texto-2">Use {nome}, {vaga} e {entrevista} para o cartão preencher sozinho.</p>
                <template x-for="et in rhEtapas()" :key="et">
                    <label class="cr-campo"><span class="cr-rot flex">
                        <span x-text="et"></span><button type="button" class="link-prim ml-auto font-normal" @click="rhModeloPadrao(b, et)">Voltar ao padrão</button></span>
                        <textarea class="campo text-xs" rows="2" x-model="b.dados.modelos[et]" maxlength="1500"></textarea></label>
                </template>
            </fieldset>
        </div>
    </template>
</div>
