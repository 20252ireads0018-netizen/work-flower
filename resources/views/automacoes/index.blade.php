@extends('layouts.app')

@section('titulo', 'Automações')
@section('subtitulo', 'Faça o seu computador agir sozinho: abrir programas e sites, avisar, fazer backup ou digitar um texto, num horário, quando uma tarefa muda ou por um botão.')

@section('conteudo')
@php
    use App\Models\Automacao;
    use App\Models\AutomacaoExecucao;
    $ativos = $dispositivos->filter->ativo();
    $editaveis = $automacoes->map(fn ($a) => [
        'id' => $a->id, 'nome' => $a->nome, 'dispositivo_id' => $a->dispositivo_id, 'gatilho' => $a->gatilho,
        'cfg' => $a->gatilho_config ?? [], 'acoes' => $a->acoes ?? [],
    ])->keyBy('id');
    $corStatus = ['pendente' => 'var(--linha)', 'enviada' => 'var(--prim-suave)', 'ok' => '#16a34a33', 'erro' => '#dc262633', 'recusada' => '#f59e0b33', 'expirada' => 'var(--linha)'];
    // depois de um erro de validação o formulário volta com o que a pessoa tinha digitado
    $antigo = null;
    if (old('gatilho')) {
        $acoesAntigas = json_decode((string) base64_decode((string) old('acoes_b64'), true), true);
        $antigo = [
            'id' => old('_editando') ? (int) old('_editando') : null, 'nome' => (string) old('nome'),
            'dispositivo_id' => (int) old('dispositivo_id'), 'gatilho' => (string) old('gatilho'),
            'cfg' => ['hora' => old('hora', '08:30'), 'dias' => array_map('intval', (array) old('dias', [])), 'minutos' => (int) old('minutos', 60), 'area' => (string) old('area', '')],
            'acoes' => is_array($acoesAntigas) ? $acoesAntigas : [],
        ];
    }
@endphp
<style>
    .au-pagina { max-width: 1280px; margin: 0 auto; }
    .au-grade { display: grid; gap: 1.5rem; align-items: start; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) { .au-grade { grid-template-columns: minmax(0, 2fr) minmax(0, 3fr); } }
    .au-passos { display: grid; gap: .75rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 768px) { .au-passos { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .au-passo { display: flex; gap: .65rem; align-items: flex-start; }
    .au-num { flex: none; width: 1.6rem; height: 1.6rem; border-radius: 999px; display: grid; place-items: center; font-weight: 700; font-size: .85rem; background: var(--prim); color: var(--on-prim); }
    .au-quebra { overflow-wrap: anywhere; }
    .au-tag { font-size: .75rem; padding: .15rem .6rem; border-radius: 999px; background: var(--prim-suave); color: var(--prim-forte); max-width: 100%; overflow-wrap: anywhere; }
    .au-linha { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .au-acao { border: 1px solid var(--linha); border-radius: .6rem; padding: .75rem; }
    .au-pilula { font-size: .75rem; padding: .1rem .55rem; border-radius: 999px; }
    .au-token { font-family: ui-monospace, monospace; word-break: break-all; user-select: all; background: var(--fundo, #0002); padding: .5rem .65rem; border-radius: .5rem; display: block; }
    .au-mini { font-size: .8rem; padding: .25rem .6rem; min-height: 0 !important; }
    .au-dias label { cursor: pointer; }
</style>

<div class="au-pagina">
<div class="au-linha mb-4">
    <a href="{{ route('painel') }}" class="btn-sec au-mini">← Voltar ao painel</a>
</div>

<section class="card p-4 mb-6">
    <h2 class="titulo text-lg mb-3">Como funciona</h2>
    <div class="au-passos text-sm">
        <div class="au-passo"><span class="au-num">1</span><p><b>Instale o agente</b> no seu Windows: clique em <b>Instalar neste computador</b> e dê dois cliques no arquivo baixado. Menos de 1 minuto, sem administrador, sem token para copiar.</p></div>
        <div class="au-passo"><span class="au-num">2</span><p><b>Crie uma automação</b>: escolha <b>quando</b> (horário, tarefa criada/concluída ou só pelo botão) e <b>o que fazer</b> no computador.</p></div>
        <div class="au-passo"><span class="au-num">3</span><p><b>Pronto</b>: o agente confere a cada 30 s e roda. O resultado aparece no <b>Histórico</b>. Para cortar o acesso, clique em <b>Revogar</b>.</p></div>
    </div>
</section>

@if (session('ok'))
    <p class="text-sm p-3 rounded-lg mb-4" style="background: var(--prim-suave); color: var(--prim-forte)">{{ session('ok') }}</p>
@endif
@if (session('erro'))
    <p class="text-sm p-3 rounded-lg mb-4" style="background: #dc262622; color: #f87171">{{ session('erro') }}</p>
@endif
@if ($t = session('token_novo'))
    <div class="card p-4 mb-4 space-y-2" style="border-color: var(--prim)">
        <b>Token do computador "{{ $t['nome'] }}"</b>
        <p class="text-sm texto-2">Copie agora e cole no instalador do agente. Ele <b>não aparece de novo</b> (guardamos só uma impressão dele). Quem tiver este token consegue mandar o seu computador rodar as ações permitidas: não mande para ninguém.</p>
        <code class="au-token" id="au-token">{{ $t['token'] }}</code>
        <button type="button" class="btn-sec au-mini" onclick="navigator.clipboard.writeText(document.getElementById('au-token').textContent.trim()); this.textContent='Copiado'">Copiar token</button>
    </div>
@endif

<div class="au-grade">
    {{-- coluna 1: computadores e formulário --}}
    <div class="space-y-6">
        <section class="card p-5 space-y-3">
            <h2 class="titulo text-xl">1. Seus computadores</h2>
            <p class="text-sm texto-2">O site não mexe no computador sozinho: quem faz é o <b>agente</b>, um programinha para Windows que confere aqui a cada 30 segundos e só roda os tipos de ação que você deixar.</p>
            {{-- instalação de um clique: o .cmd leva um código de uso único (30 min); a página confere e mostra quando o PC conecta --}}
            <div class="au-instalar space-y-2" x-data="wfAgenteInstalar(@js(route('automacoes.computadores')), @js($dispositivos->filter->ativo()->pluck('id')->values()))">
                <div class="au-linha">
                    <a href="{{ route('automacoes.instalador') }}" class="btn" @click="esperar()">⤓ Instalar neste computador</a>
                    <span class="text-xs texto-2">Baixa <b>Instalar agente Work Flower.cmd</b>: dê dois cliques nele e pronto.</span>
                </div>
                <p class="text-xs texto-2" x-show="estado === 'esperando'" x-cloak>⏳ Esperando o computador conectar… abra o arquivo baixado (se o Windows avisar, clique em <b>Mais informações › Executar assim mesmo</b>).</p>
                <div class="au-linha text-sm" x-show="estado === 'conectado'" x-cloak style="color: #16a34a">
                    <b x-text="'✓ Conectado: ' + (novo ? novo.nome : '')"></b>
                    <button type="button" class="btn-sec au-mini" @click="testar(novo)">Testar agora</button>
                    <button type="button" class="btn-sec au-mini" @click="location.reload()">Continuar</button>
                </div>
                <p class="text-xs" x-show="msg" x-cloak x-text="msg"></p>
            </div>

            @forelse ($dispositivos as $d)
                <div class="au-linha text-sm" style="opacity: {{ $d->ativo() ? 1 : .55 }}">
                    <span class="au-pilula" style="background: {{ $d->online() ? '#16a34a' : 'var(--linha)' }}; color: {{ $d->online() ? '#fff' : 'inherit' }}">
                        {{ ! $d->ativo() ? 'Revogado' : ($d->online() ? 'Online' : 'Offline') }}
                    </span>
                    <b class="flex-1 truncate" style="min-width: 7rem" title="{{ $d->nome }}">{{ $d->nome }}</b>
                    <span class="text-xs texto-2">token …{{ $d->token_fim }} · {{ $d->ultimo_contato_em ? 'visto ' . $d->ultimo_contato_em->diffForHumans() : 'ainda não conectou (instale o agente)' }}</span>
                    @if ($d->ativo())
                        <button type="button" class="btn-sec au-mini" title="Mostra uma notificação nesse computador" onclick="wfAgenteTestar({{ $d->id }}, this)">Testar</button>
                        <form method="POST" action="{{ route('automacoes.revogar', $d) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Revogar o token de ' . $d->nome . '? O agente desse computador para de funcionar até receber um token novo.') }})">
                            @csrf
                            <button class="btn-sec au-mini">Revogar</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-sm texto-2">Nenhum computador ainda. Clique em <b>Instalar neste computador</b> acima.</p>
            @endforelse

            {{-- jeito antigo (zip + token), para quem prefere ou instala em outro computador sem entrar no site nele --}}
            <details class="text-sm" @if ($errors->has('computador') || session('token_novo')) open @endif>
                <summary class="texto-2 cursor-pointer">Instalar manualmente (.zip + token)</summary>
                <div class="space-y-2 mt-2">
                    <p class="text-xs texto-2">Gere um token, <a href="{{ route('automacoes.agente') }}" class="underline">baixe o agente (.zip)</a>, extraia, clique com o botão direito em <b>instalar.ps1</b> › Executar com o PowerShell e cole o token.</p>
                    <form method="POST" action="{{ route('automacoes.parear') }}" class="space-y-1">
                        @csrf
                        <label class="rotulo" for="au-computador">Gerar token para um computador</label>
                        <div class="au-linha">
                            <input id="au-computador" name="computador" class="campo flex-1" style="min-width: 10rem" maxlength="60" required placeholder="Nome (ex.: PC de casa)" value="{{ old('computador') }}">
                            <button class="btn-sec">Gerar token</button>
                        </div>
                    </form>
                    @error('computador')<p class="erro">{{ $message }}</p>@enderror
                </div>
            </details>
        </section>

        <section class="card p-5 space-y-4" x-data="automacaoForm(@js($editaveis), @js($ativos->sortByDesc(fn ($d) => $d->ultimo_contato_em?->timestamp ?? 0)->first()?->id), @js($antigo))" @editar-automacao.window="editar($event.detail)">
            <div class="au-linha">
                <h2 class="titulo text-xl flex-1" x-text="id ? '2. Editar automação' : '2. Nova automação'">2. Nova automação</h2>
                <button type="button" class="btn-sec au-mini" x-show="id" x-cloak @click="limpar()">Cancelar edição</button>
            </div>
            @if ($ativos->isEmpty())
                <p class="text-sm texto-2">Primeiro instale o agente no passo 1 (botão <b>Instalar neste computador</b>). Depois o formulário aparece aqui.</p>
            @else
            <form method="POST" :action="id ? @js(url('/automacoes')) + '/' + id : @js(route('automacoes.criar'))" class="space-y-4" @submit="$refs.b64.value = paraBase64(JSON.stringify(acoes))">
                @csrf
                <input type="hidden" name="acoes_b64" x-ref="b64">
                <input type="hidden" name="_editando" :value="id || ''">
                <div>
                    <label class="rotulo" for="au-nome">Nome</label>
                    <input id="au-nome" name="nome" x-model="nome" class="campo" maxlength="80" required placeholder="Ex.: Começar o dia">
                </div>
                <div>
                    <label class="rotulo" for="au-disp">Computador</label>
                    <select id="au-disp" name="dispositivo_id" x-model.number="dispositivo_id" class="campo">
                        @foreach ($ativos as $d)
                            <option value="{{ $d->id }}">{{ $d->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="rotulo" for="au-gat">Quando</label>
                    <select id="au-gat" name="gatilho" x-model="gatilho" class="campo">
                        @foreach (Automacao::GATILHOS as $v => $n)
                            <option value="{{ $v }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="gatilho === 'horario'" class="space-y-2">
                    <label class="rotulo" for="au-hora">Hora</label>
                    <input id="au-hora" type="time" name="hora" x-model="cfg.hora" class="campo w-28" :disabled="gatilho !== 'horario'">
                    <div class="au-linha au-dias text-sm">
                        @foreach (Automacao::DIAS as $n => $nome)
                            <label class="au-linha" style="gap:.25rem"><input type="checkbox" name="dias[]" value="{{ $n }}" :checked="(cfg.dias || []).includes({{ $n }})" @change="marcarDia({{ $n }}, $event.target.checked)"> {{ $nome }}</label>
                        @endforeach
                    </div>
                    <p class="text-xs texto-2">Nenhum dia marcado = todos os dias. Horário de Brasília.</p>
                </div>
                <div x-show="gatilho === 'intervalo'">
                    <label class="rotulo" for="au-min">Repetir a cada</label>
                    <input id="au-min" type="number" name="minutos" x-model.number="cfg.minutos" min="5" max="1440" class="campo w-28" :disabled="gatilho !== 'intervalo'"> <span class="text-sm texto-2">minutos (mínimo 5)</span>
                </div>
                <div x-show="gatilho === 'tarefa_criada' || gatilho === 'tarefa_concluida'">
                    <label class="rotulo" for="au-area">Em qual área</label>
                    <select id="au-area" name="area" x-model="cfg.area" class="campo" :disabled="!gatilho.startsWith('tarefa')">
                        <option value="">Em qualquer área</option>
                        @foreach (\App\Http\Controllers\AutomacaoController::areas() as $slug => $nome)
                            <option value="{{ $slug }}">Só em {{ $nome }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs texto-2 mt-1">Nos textos das ações dá para usar {tarefa}, {area}, {data} e {hora}.</p>
                </div>

                <div class="space-y-2">
                    <span class="rotulo">O que fazer no computador (em ordem)</span>
                    <template x-for="(a, i) in acoes" :key="i">
                        <div class="au-acao space-y-2">
                            <div class="au-linha">
                                <select x-model="a.tipo" class="campo flex-1 min-w-0">
                                    @foreach (Automacao::ACOES as $v => $n)
                                        <option value="{{ $v }}">{{ $n }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn-sec au-mini" @click="mover(i, -1)" :disabled="i === 0" >Subir</button>
                                <button type="button" class="btn-sec au-mini" @click="acoes.splice(i, 1)"  x-show="acoes.length > 1">Remover</button>
                            </div>
                            <template x-if="a.tipo === 'notificacao'"><div class="space-y-2">
                                <input class="campo" x-model="a.titulo" maxlength="80" placeholder="Título">
                                <input class="campo" x-model="a.mensagem" maxlength="300" placeholder="Mensagem">
                            </div></template>
                            <template x-if="a.tipo === 'url'">
                                <input class="campo" x-model="a.url" maxlength="500" placeholder="https://...">
                            </template>
                            <template x-if="a.tipo === 'abrir'"><div>
                                <input class="campo" x-model="a.caminho" maxlength="400" placeholder="C:\Program Files\...\programa.exe ou C:\pasta\arquivo.pdf">
                                <p class="text-xs texto-2 mt-1">Sem terminais nem scripts (.bat, .ps1, cmd...). Para isso use "Rodar script".</p>
                            </div></template>
                            <template x-if="a.tipo === 'script'"><div>
                                <input class="campo" x-model="a.nome" maxlength="60" placeholder="nome-do-script (sem .ps1)">
                                <p class="text-xs texto-2 mt-1">O script precisa estar na pasta <b>scripts</b> do agente no computador. O site manda só o nome; o computador pergunta antes de rodar.</p>
                            </div></template>
                            <template x-if="a.tipo === 'backup'"><div class="space-y-2">
                                <input class="campo" x-model="a.origem" maxlength="400" placeholder="Pasta de origem (ex.: C:\Users\voce\Documents)">
                                <input class="campo" x-model="a.destino" maxlength="400" placeholder="Guardar em (ex.: D:\Backups)">
                                <p class="text-xs texto-2">Cria uma subpasta com data e hora a cada cópia.</p>
                            </div></template>
                            <template x-if="a.tipo === 'digitar'"><div class="space-y-2">
                                <textarea class="campo" x-model="a.texto" rows="2" maxlength="500" placeholder="Texto a digitar onde estiver o cursor"></textarea>
                                <label class="text-sm texto-2">Esperar <input type="number" min="0" max="30" x-model.number="a.espera" class="campo w-28" style="display:inline-block"> s antes</label>
                            </div></template>
                        </div>
                    </template>
                    <button type="button" class="btn-sec au-mini" @click="acoes.push({tipo: 'notificacao', titulo: '', mensagem: '', espera: 3})" x-show="acoes.length < {{ Automacao::MAX_ACOES }}">+ Adicionar ação</button>
                </div>

                @if ($errors->hasAny(['acoes', 'acoes_b64', 'nome', 'hora', 'minutos', 'gatilho', 'dispositivo_id']))
                    <p class="erro">{{ $errors->first('nome') ?: $errors->first('acoes_b64') ?: $errors->first('acoes') ?: $errors->first('hora') ?: $errors->first('minutos') ?: $errors->first('gatilho') ?: $errors->first('dispositivo_id') }}</p>
                @endif
                <button class="btn w-full" x-text="id ? 'Salvar alterações' : 'Criar automação'">Criar automação</button>
            </form>
            @endif
        </section>
    </div>

    {{-- coluna 2: lista e histórico --}}
    <div class="space-y-6">
        <section class="space-y-3" x-data>
            <h2 class="titulo text-xl">Suas automações</h2>
            @forelse ($automacoes as $a)
                <article class="card p-4 space-y-2" style="opacity: {{ $a->ativa ? 1 : .6 }}">
                    <div class="au-linha">
                        <b class="flex-1 truncate" style="min-width: 8rem" title="{{ $a->nome }}">{{ $a->nome }}</b>
                        <span class="au-tag">{{ $a->descricaoGatilho() }}{{ $a->ativa ? '' : ' · pausada' }}</span>
                    </div>
                    <p class="text-sm texto-2 au-quebra">
                        {{ collect($a->acoes)->map(fn ($x) => Automacao::ACOES[$x['tipo']] ?? $x['tipo'])->implode(' → ') }}
                        · {{ $a->dispositivo?->nome ?? 'sem computador' }}{{ $a->dispositivo && ! $a->dispositivo->ativo() ? ' (revogado)' : '' }}
                    </p>
                    @if ($a->ativa && $a->proxima_em)
                        <p class="text-xs texto-2">Próxima: {{ $a->proxima_em->setTimezone(Automacao::FUSO)->format('d/m H:i') }}</p>
                    @endif
                    <div class="au-linha">
                        <form method="POST" action="{{ route('automacoes.rodar', $a) }}">@csrf<button class="btn au-mini">Rodar agora</button></form>
                        <button type="button" class="btn-sec au-mini" @click="$dispatch('editar-automacao', {{ $a->id }}); window.scrollTo({top: 0, behavior: 'smooth'})">Editar</button>
                        <form method="POST" action="{{ route('automacoes.alternar', $a) }}">@csrf<button class="btn-sec au-mini">{{ $a->ativa ? 'Pausar' : 'Ligar' }}</button></form>
                        <form method="POST" action="{{ route('automacoes.remover', $a) }}" onsubmit="return confirm('Excluir esta automação?')">@csrf @method('DELETE')<button class="btn-sec au-mini">Excluir</button></form>
                    </div>
                </article>
            @empty
                <p class="texto-2 text-sm card p-4">Nenhuma automação ainda. Crie a primeira no passo 2. Exemplo: "Todo dia às 08:30 → abrir o e-mail e a planilha do mês".</p>
            @endforelse
        </section>

        <section class="space-y-3" x-data="automacaoHistorico(@js(route('automacoes.historico')))">
            <div class="au-linha">
                <h2 class="titulo text-xl flex-1">Histórico</h2>
                @if ($execucoes->isNotEmpty())
                    <form method="POST" action="{{ route('automacoes.historico.limpar') }}" onsubmit="return confirm('Apagar o histórico das execuções encerradas?')">@csrf @method('DELETE')<button class="btn-sec au-mini">Limpar</button></form>
                @endif
            </div>
            @forelse ($execucoes as $e)
                <div class="card p-3 space-y-1 text-sm" data-exec="{{ $e->id }}">
                    <div class="au-linha">
                        <b class="flex-1 truncate" style="min-width: 8rem" title="{{ $e->nome }}">{{ $e->nome }}</b>
                        <span class="au-pilula" data-status style="background: {{ $corStatus[$e->status] ?? 'var(--linha)' }}">{{ AutomacaoExecucao::STATUS[$e->status] ?? $e->status }}</span>
                    </div>
                    <p class="text-xs texto-2 au-quebra">{{ $e->origem }} · {{ $e->dispositivo?->nome }} · {{ $e->created_at->setTimezone(Automacao::FUSO)->format('d/m H:i:s') }}</p>
                    <p class="text-xs whitespace-pre-line au-quebra" data-resultado>{{ $e->resultado }}</p>
                </div>
            @empty
                <p class="texto-2 text-sm card p-4">As execuções aparecem aqui quando uma automação dispara, com o que o computador respondeu.</p>
            @endforelse
        </section>
    </div>
</div>
</div>{{-- .au-pagina --}}

<script>
// base64 de texto UTF-8 (o mod_security da hospedagem barra caminhos e textos com cara de código)
function paraBase64(txt) { const b = new TextEncoder().encode(txt); let s = ''; b.forEach(c => s += String.fromCharCode(c)); return btoa(s); }
// notificação de teste num computador pareado (sem precisar criar automação)
async function wfAgenteTestar(id, botao) {
    const r = await fetch(@js(url('/automacoes/computadores')) + '/' + id + '/testar', { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' } });
    const j = await r.json().catch(() => ({}));
    const msg = r.ok ? j.msg : (j.erro || 'Não deu certo agora. Tente de novo.');
    if (botao) { botao.textContent = r.ok ? 'Enviado ✓' : 'Erro'; botao.title = msg; }
    return msg;
}
// instalação de um clique: depois do download, confere a cada 4 s até aparecer um computador novo (por até 10 min)
function wfAgenteInstalar(urlLista, conhecidos) {
    return {
        estado: '', novo: null, msg: '', timer: null,
        esperar() {
            this.estado = 'esperando'; this.msg = '';
            clearInterval(this.timer);
            const fim = Date.now() + 10 * 60 * 1000;
            this.timer = setInterval(async () => {
                if (Date.now() > fim) { clearInterval(this.timer); this.estado = ''; this.msg = 'Não vi nenhum computador conectar. Abriu o arquivo baixado? Se precisar, baixe de novo (cada arquivo vale 30 minutos e uma vez só).'; return; }
                try {
                    const lista = await (await fetch(urlLista, { headers: { Accept: 'application/json' } })).json();
                    const novo = lista.find(d => !conhecidos.includes(d.id));
                    if (novo) { clearInterval(this.timer); this.novo = novo; this.estado = 'conectado'; }
                } catch (e) { /* tenta de novo na próxima volta */ }
            }, 4000);
        },
        async testar(d) { if (d) this.msg = await wfAgenteTestar(d.id); },
    };
}
function automacaoForm(lista, dispPadrao, antigo) {
    const vazio = () => ({ id: null, nome: '', dispositivo_id: dispPadrao, gatilho: 'horario', cfg: { hora: '08:30', dias: [], minutos: 60, area: '' }, acoes: [{ tipo: 'notificacao', titulo: 'Bom dia!', mensagem: 'Hora de começar.', espera: 3 }] });
    return {
        ...vazio(), b64: '',
        init() { if (antigo) { Object.assign(this, vazio(), { id: antigo.id, nome: antigo.nome, dispositivo_id: antigo.dispositivo_id || dispPadrao, gatilho: antigo.gatilho }); this.cfg = Object.assign(vazio().cfg, antigo.cfg); if (antigo.acoes.length) this.acoes = antigo.acoes; } },
        limpar() { Object.assign(this, vazio()); },
        editar(id) {
            const a = lista[id]; if (!a) return;
            Object.assign(this, vazio(), JSON.parse(JSON.stringify({ id: a.id, nome: a.nome, dispositivo_id: a.dispositivo_id || dispPadrao, gatilho: a.gatilho })));
            this.cfg = Object.assign(vazio().cfg, a.cfg || {});
            this.acoes = JSON.parse(JSON.stringify(a.acoes || []));
        },
        marcarDia(n, on) { const d = new Set(this.cfg.dias || []); on ? d.add(n) : d.delete(n); this.cfg.dias = [...d]; },
        mover(i, dir) { const j = i + dir; if (j < 0 || j >= this.acoes.length) return; const [x] = this.acoes.splice(i, 1); this.acoes.splice(j, 0, x); },
    };
}
// confere as execuções em andamento sem recarregar a página
function automacaoHistorico(url) {
    const cores = @json($corStatus);
    return {
        init() {
            const conferir = () => {
                if (!document.querySelector('[data-exec]') || document.hidden) return;
                fetch(url, { headers: { Accept: 'application/json' } }).then(r => r.ok ? r.json() : []).then(lista => {
                    lista.forEach(e => {
                        const el = document.querySelector(`[data-exec="${e.id}"]`); if (!el) return;
                        const st = el.querySelector('[data-status]'); st.textContent = e.rotulo; st.style.background = cores[e.status] || 'var(--linha)';
                        el.querySelector('[data-resultado]').textContent = e.resultado || '';
                    });
                }).catch(() => {});
            };
            setInterval(conferir, 10000);
        },
    };
}
</script>
@endsection
