{{-- Espera: $tipo = 'agua' | 'cal' · últimos 7 dias, com linha tracejada na meta --}}
<div x-data="{ get g() { return barras('{{ $tipo }}') } }" class="wf-barras" role="img"
     aria-label="Últimos 7 dias em comparação com a meta">
    <div class="wf-plot">
        <div class="wf-meta-linha" :style="'bottom:' + g.meta + '%'"></div>
        <template x-for="b in g.itens" :key="b.k">
            <div class="wf-col">
                <div class="wf-barra" :class="{ 'hoje': b.hoje }" :style="'height:' + b.h + '%'" :title="b.v"></div>
            </div>
        </template>
    </div>
    <div class="wf-rot">
        <template x-for="b in g.itens" :key="b.k"><span x-text="b.l"></span></template>
    </div>
</div>