{{-- Conteúdo do cartão "Pomodoro" (Mente). Lógica em public/js/estudo.js (métodos pm*).
     Foco / pausa curta / pausa longa; cada foco terminado vira uma sessão da matéria escolhida (conta no "Plano de estudos").
     O tempo continua com a página fechada: ao voltar, o que terminou é registrado. ⚙ muda as durações, o som e o início automático. --}}
<div class="es-cartao flex flex-col h-full gap-2 min-h-0 text-sm" x-init="pmRelogio()">
    <div class="flex flex-wrap items-center gap-1.5 text-xs">
        <template x-for="f in ['foco', 'curta', 'longa']" :key="f">
            <button type="button" class="btn-sec !py-0.5" :class="b.dados.fase === f && 'qz-on'" @click="pmFase(b, f)" x-text="pmFaseNome(f)"></button>
        </template>
        <button type="button" class="mini-btn ml-auto" :class="pmUI(b).cfg && 'qz-on'" title="Configurar" aria-label="Configurar o Pomodoro" @click="pmUI(b).cfg = !pmUI(b).cfg">⚙</button>
    </div>

    <template x-if="pmUI(b).cfg">
        <div class="qz-ed text-xs">
            <div class="cx-grade">
                <label class="flex flex-col gap-0.5">Foco (min)<input type="number" min="1" max="240" class="campo !py-0.5 !text-xs" x-model.number="b.dados.foco" @change="!b.dados.rodando && (b.dados.resta = pmDur(b))"></label>
                <label class="flex flex-col gap-0.5">Pausa curta<input type="number" min="1" max="60" class="campo !py-0.5 !text-xs" x-model.number="b.dados.curta" @change="!b.dados.rodando && (b.dados.resta = pmDur(b))"></label>
                <label class="flex flex-col gap-0.5">Pausa longa<input type="number" min="1" max="120" class="campo !py-0.5 !text-xs" x-model.number="b.dados.longa" @change="!b.dados.rodando && (b.dados.resta = pmDur(b))"></label>
                <label class="flex flex-col gap-0.5">Focos até a longa<input type="number" min="1" max="12" class="campo !py-0.5 !text-xs" x-model.number="b.dados.ciclos"></label>
            </div>
            <label class="flex items-center gap-1.5"><input type="checkbox" x-model="b.dados.som"> Tocar um som no fim</label>
            <label class="flex items-center gap-1.5"><input type="checkbox" x-model="b.dados.auto"> Começar a próxima fase sozinho</label>
            <div class="flex flex-wrap gap-1">
                <template x-for="m in (b.dados.materias || [])" :key="m">
                    <span class="qz-chip">
                        <span x-text="m"></span> <button type="button" class="texto-2" :aria-label="'Tirar ' + m" @click="pmRemoverMateria(b, m)">×</button>
                    </span>
                </template>
            </div>
        </div>
    </template>

    <div class="flex flex-col items-center gap-1.5 flex-1 min-h-0 justify-center">
        <div class="pm-relogio">
            <div x-html="pmAnel(b)"></div>
            <div class="pm-centro">
                <span class="pm-tempo" x-text="pmTempo(b)"></span>
                <span class="text-[11px] texto-2" x-text="pmFaseNome(b.dados.fase) + ' · ' + ((b.dados.feitos || 0) % Math.max(1, b.dados.ciclos || 4)) + '/' + (b.dados.ciclos || 4)"></span>
            </div>
        </div>
        <div class="flex items-center gap-1.5 text-xs w-full" style="max-width:20rem">
            <select class="campo !py-0.5 !text-xs flex-1" x-model="b.dados.materia" aria-label="Matéria do foco">
                <option value="">Escolha a matéria…</option>
                <template x-for="m in (b.dados.materias || [])" :key="m"><option :value="m" x-text="m" :selected="m === b.dados.materia"></option></template>
            </select>
            <input class="campo !py-0.5 !text-xs" style="width:7rem" placeholder="+ matéria" x-model="pmUI(b).nova" @keydown.enter.prevent="pmNovaMateria(b)" maxlength="60" aria-label="Nova matéria">
        </div>
        <div class="flex gap-1.5">
            <button type="button" class="btn !py-1" style="padding-left:1rem;padding-right:1rem" x-show="!b.dados.rodando" @click="pmComecar(b)" x-text="(b.dados.resta ?? pmDur(b)) < pmDur(b) ? 'Continuar' : 'Começar'"></button>
            <button type="button" class="btn !py-1" style="padding-left:1rem;padding-right:1rem" x-show="b.dados.rodando" @click="pmPausar(b)">Pausar</button>
            <button type="button" class="btn-sec !py-1" @click="pmZerar(b)" title="Voltar ao início desta fase">Zerar</button>
            <button type="button" class="btn-sec !py-1" @click="pmAvancar(b, false)" title="Pular para a próxima fase sem contar">Pular</button>
        </div>
    </div>

    <div class="text-xs flex flex-col gap-1">
        <div class="flex items-center gap-2">
            <b>Hoje</b>
            <span class="texto-2" x-text="pmHoje(b).n + ' pomodoros · ' + (pmHoje(b).total < 60 ? pmHoje(b).total + ' min' : Math.floor(pmHoje(b).total / 60) + 'h' + String(pmHoje(b).total % 60).padStart(2, '0'))"></span>
            <button type="button" class="link-prim ml-auto" @click="pmUI(b).ver = pmUI(b).ver === 'semana' ? '' : 'semana'" x-text="pmUI(b).ver === 'semana' ? 'Fechar' : 'Semana e histórico'"></button>
        </div>
        <div class="flex flex-wrap gap-1">
            <template x-for="x in pmHoje(b).lista" :key="x.m">
                <span class="qz-chip" x-text="x.m + ': ' + x.min + ' min'"></span>
            </template>
        </div>
        <template x-if="pmUI(b).ver === 'semana'">
            <div class="flex flex-col gap-1.5">
                <div class="es-graf" x-html="pmSvg(b)"></div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="texto-2">Estudou sem o relógio?</span>
                    <input type="number" min="1" max="600" class="campo !py-0.5 !text-xs !w-16" x-model.number="pmUI(b).manual.min" aria-label="Minutos">
                    <span class="texto-2">min de</span>
                    <select class="campo !py-0.5 !text-xs !w-auto" x-model="pmUI(b).manual.materia" aria-label="Matéria">
                        <option value="">matéria atual</option>
                        <template x-for="m in (b.dados.materias || [])" :key="m"><option :value="m" x-text="m" :selected="m === pmUI(b).manual.materia"></option></template>
                    </select>
                    <button type="button" class="btn-sec !py-0.5" @click="pmRegistrar(b)">Registrar</button>
                </div>
                <template x-for="s in pmUltimas(b)" :key="s.id">
                    <div class="flex items-center gap-1.5 texto-2">
                        <span x-text="s.d.split('-').reverse().slice(0, 2).join('/') + ' · ' + s.materia + ' · ' + s.min + ' min' + (s.manual ? ' (registrado)' : '')"></span>
                        <button type="button" class="mini-btn perigo ml-auto" title="Apagar sessão" @click="pmApagarSessao(b, s)">✕</button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
