{{-- Conteúdo do cartão "RH: vagas e candidatos" (todas as áreas). Lógica em public/js/cartoes-extra.js (métodos rh*).
     Funil: candidatos por etapa (Triagem → Contratado/Recusado), filtro por vaga, nota de 0 a 5.
     Integração: checklist editável para cada contratado, com a porcentagem feita. --}}
<div class="flex flex-col h-full gap-2 min-h-0 text-sm">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <button type="button" class="btn-sec !py-0.5" :class="rhUI(b).ver === 'funil' && 'qz-on'" @click="rhUI(b).ver = 'funil'; rhUI(b).aberto = null">Funil</button>
        <button type="button" class="btn-sec !py-0.5" :class="rhUI(b).ver === 'integra' && 'qz-on'" @click="rhUI(b).ver = 'integra'; rhUI(b).aberto = null">Integração</button>
        <button type="button" class="btn-sec !py-0.5" :class="rhUI(b).ver === 'vagas' && 'qz-on'" @click="rhUI(b).ver = 'vagas'; rhUI(b).aberto = null">Vagas</button>
        <select class="campo !py-0.5 !text-xs !w-auto" x-model="rhUI(b).vaga" aria-label="Vaga">
            <option value="">Todas as vagas</option>
            <template x-for="v in (b.dados.vagas || [])" :key="v.id"><option :value="v.id" x-text="v.nome"></option></template>
        </select>
        <button type="button" class="btn !py-0.5 ml-auto" @click="rhUI(b).ver = 'funil'; rhNovo(b)">+ Candidato</button>
    </div>

    {{-- funil --}}
    <template x-if="rhUI(b).ver === 'funil' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex gap-2">
            <template x-for="et in rhEtapas()" :key="et">
                <div class="cx-col">
                    <p class="cx-col-tit"><span x-text="et"></span> <span class="texto-2" x-text="rhDa(b, et).length"></span></p>
                    <template x-for="c in rhDa(b, et)" :key="c.id">
                        <div class="cx-card" @click="rhUI(b).aberto = c.id">
                            <p class="font-medium leading-tight" x-text="c.nome"></p>
                            <p class="text-[11px] texto-2"><span x-text="rhVaga(b, c.vaga)"></span><span x-show="c.nota" x-text="' · ' + '★'.repeat(c.nota)"></span></p>
                            <div class="flex gap-1 mt-1" @click.stop>
                                <button type="button" class="mini-btn" title="Etapa anterior" aria-label="Etapa anterior" @click="rhMover(b, c, -1)">‹</button>
                                <button type="button" class="mini-btn" title="Próxima etapa" aria-label="Próxima etapa" @click="rhMover(b, c, 1)">›</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </template>

    {{-- ficha do candidato --}}
    <template x-for="c in (rhAberto(b) ? [rhAberto(b)] : [])" :key="c.id">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2">
            <div class="flex gap-1.5 items-center">
                <button type="button" class="mini-btn" title="Voltar" aria-label="Voltar" @click="rhUI(b).aberto = null">‹</button>
                <input class="campo !py-1 flex-1 font-medium" x-model="c.nome" maxlength="120" aria-label="Nome">
                <button type="button" class="mini-btn perigo" title="Tirar o candidato" aria-label="Tirar o candidato" @click="rhRemover(b, c)">✕</button>
            </div>
            <div class="grid grid-cols-2 gap-1.5 text-xs">
                <label>Vaga<select class="campo !py-0.5 !text-xs" x-model="c.vaga"><option value="">Sem vaga</option><template x-for="v in (b.dados.vagas || [])" :key="v.id"><option :value="v.id" x-text="v.nome" :selected="v.id === c.vaga"></option></template></select></label>
                <label>Etapa<select class="campo !py-0.5 !text-xs" x-model="c.etapa"><template x-for="e in rhEtapas()" :key="e"><option :value="e" x-text="e" :selected="e === c.etapa"></option></template></select></label>
                <label class="col-span-2">Contato (e-mail ou WhatsApp)
                    <span class="flex gap-1"><input class="campo !py-0.5 !text-xs flex-1" x-model="c.contato" maxlength="120">
                    <a class="btn-sec !py-0.5" x-show="rhLinkContato(c)" :href="rhLinkContato(c)" target="_blank" rel="noopener">Falar</a></span>
                </label>
            </div>
            <div class="flex items-center gap-1 text-xs">Nota:
                <template x-for="n in 5" :key="n">
                    <button type="button" class="rh-estrela" :class="n <= (c.nota || 0) && 'on'" :aria-label="'Nota ' + n" @click="c.nota = c.nota === n ? 0 : n">★</button>
                </template>
            </div>
            <textarea class="campo text-xs flex-1 min-h-[5rem]" x-model="c.obs" maxlength="8000" placeholder="Anotações da entrevista, pretensão salarial, disponibilidade..." aria-label="Anotações"></textarea>
        </div>
    </template>

    {{-- integração dos contratados --}}
    <template x-if="rhUI(b).ver === 'integra' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-2" x-data="{ editar: false }">
            <div class="flex text-xs"><button type="button" class="link-prim ml-auto" @click="editar = !editar" x-text="editar ? 'Pronto' : 'Editar os passos'"></button></div>
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
                    <p class="flex justify-between"><b x-text="c.nome"></b><span class="texto-2 text-xs" x-text="rhVaga(b, c.vaga) + ' · ' + rhProgresso(b, c) + '%'"></span></p>
                    <template x-for="(p, k) in b.dados.passos" :key="k">
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" :checked="!!(c.feitos || {})[k]" @change="rhPasso(b, c, k)"> <span x-text="p"></span></label>
                    </template>
                </div>
            </template>
        </div>
    </template>

    {{-- vagas --}}
    <template x-if="rhUI(b).ver === 'vagas' && !rhAberto(b)">
        <div class="flex-1 min-h-0 overflow-auto flex flex-col gap-1.5 text-xs">
            <form class="flex gap-1" @submit.prevent="rhNovaVaga(b, $refs.novaVaga)">
                <input x-ref="novaVaga" class="campo !py-0.5 text-xs flex-1" maxlength="60" placeholder="Nome da vaga">
                <button class="btn !py-0.5">Adicionar</button>
            </form>
            <template x-for="v in (b.dados.vagas || [])" :key="v.id">
                <div class="flex gap-1 items-center">
                    <input class="campo !py-0.5 text-xs flex-1" x-model="v.nome" maxlength="60">
                    <span class="texto-2" x-text="(b.dados.cands || []).filter(c => c.vaga === v.id && c.etapa !== 'Recusado').length + ' ativos'"></span>
                    <button type="button" class="mini-btn perigo" aria-label="Tirar a vaga" @click="rhTirarVaga(b, v)">✕</button>
                </div>
            </template>
        </div>
    </template>
</div>
