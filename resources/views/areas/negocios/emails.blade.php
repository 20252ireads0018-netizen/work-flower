@extends('layouts.app')

@section('titulo', 'E-mails')
@section('subtitulo', 'Negócios · sua caixa de entrada lida e separada por categoria: clientes, financeiro, promoções e o resto.')

@section('conteudo')
@php
    $cfg = [
        'base'     => url('/negocios/emails'),
        'negocios' => route('areas.negocios'),
        'estado'   => $estado,
        'envio'    => $envio,
        'ok'       => session('emails_ok'),
        'erro'     => session('emails_erro'),
    ];
@endphp

<style>
    .em-pagina { max-width: 78rem; margin: 0 auto; }
    .em-topo { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .em-grade { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1024px) { .em-grade { grid-template-columns: 15rem minmax(0, 1fr); } }
    .em-lado { display: flex; flex-direction: column; gap: .35rem; }
    .em-cat { display: flex; align-items: center; justify-content: space-between; gap: .5rem; width: 100%; padding: .5rem .75rem; border-radius: .6rem; border: 1px solid transparent; font-size: .9rem; text-align: left; color: var(--tinta-2); min-width: 0; }
    .em-cat > span:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    @media (max-width: 1023px) {
        .em-lado { flex-direction: row; flex-wrap: wrap; }
        .em-cat { width: auto; max-width: 100%; padding: .3rem .6rem; font-size: .8rem; border-color: var(--linha); }
    }
    .em-cat:hover { background: var(--superficie-2); color: var(--tinta); }
    .em-cat.ativa { background: var(--prim-suave); color: var(--prim-forte); border-color: var(--prim-linha); font-weight: 600; }
    .em-cat b { flex: none; white-space: nowrap; font-weight: 600; font-size: .75rem; min-width: 1.6rem; text-align: center; padding: .05rem .4rem; border-radius: 9999px; background: var(--superficie-2); border: 1px solid var(--linha); }
    .em-bolinha { display: inline-block; width: .55rem; height: .55rem; border-radius: 9999px; background: var(--c); margin-right: .45rem; }
    .em-lista { border: 1px solid var(--linha); border-radius: .75rem; overflow: hidden; }
    .em-msg { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .2rem .75rem; padding: .7rem .9rem; border-bottom: 1px solid var(--linha); cursor: pointer; }
    .em-msg:last-child { border-bottom: 0; }
    .em-msg:hover { background: var(--superficie-2); }
    .em-msg.aberta { background: var(--prim-suave); cursor: default; }
    .em-corta { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .em-msg .de { font-size: .875rem; }
    .em-msg.nao-lida .de, .em-msg.nao-lida .assunto { font-weight: 700; color: var(--tinta); }
    .em-msg .assunto { font-size: .9rem; color: var(--tinta); grid-column: 1 / -1; }
    .em-msg.aberta .assunto { white-space: normal; overflow-wrap: anywhere; }
    .em-msg .trecho { font-size: .8rem; color: var(--tinta-2); grid-column: 1 / -1; }
    .em-msg .quando { font-size: .75rem; color: var(--tinta-2); white-space: nowrap; text-align: right; }
    .em-ponto { width: .5rem; height: .5rem; border-radius: 9999px; background: var(--prim); flex: none; }
    .em-etq { font-size: .7rem; padding: .05rem .5rem; border-radius: 9999px; border: 1px solid color-mix(in srgb, var(--c) 45%, transparent); color: var(--c); white-space: nowrap; max-width: 9rem; overflow: hidden; text-overflow: ellipsis; flex: none; }
    .em-detalhe { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; padding-top: .5rem; }
    .em-detalhe p { width: 100%; font-size: .85rem; color: var(--tinta-2); white-space: normal; overflow-wrap: anywhere; }
    .em-detalhe .campo { width: auto; max-width: 100%; padding: .3rem .5rem; font-size: .85rem; }
    .em-contas { display: grid; gap: .5rem; grid-template-columns: repeat(auto-fill, minmax(min(100%, 18rem), 1fr)); }
    .em-conta { display: flex; flex-direction: column; gap: .5rem; padding: .65rem .8rem; border: 1px solid var(--linha); border-radius: .6rem; background: var(--superficie-2); font-size: .85rem; min-width: 0; }
    .em-conta small { display: block; color: var(--tinta-2); font-size: .75rem; }
    .em-conta .falha { color: #f87171; overflow-wrap: anywhere; }
    .em-form { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); }
    .em-form label { display: flex; flex-direction: column; gap: .25rem; font-size: .8rem; color: var(--tinta-2); }
    .em-regra { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr) auto; gap: .4rem; align-items: center; }
    @media (max-width: 640px) {
        .em-regra { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); padding-bottom: .5rem; border-bottom: 1px solid var(--linha); }
        .em-regra > :nth-child(3) { grid-column: 1 / -1; }
    }
    .em-regra .campo { padding: .35rem .5rem; font-size: .85rem; }
    .em-vazio { padding: 2rem 1rem; text-align: center; color: var(--tinta-2); font-size: .9rem; }
    .em-passos { display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr)); }
    .em-passos div { padding: .75rem; border: 1px solid var(--linha); border-radius: .6rem; background: var(--superficie-2); font-size: .85rem; color: var(--tinta-2); }
    .em-passos b { display: block; color: var(--tinta); margin-bottom: .2rem; }
    .em-mini { font-size: .75rem; padding: .2rem .55rem; border-radius: .45rem; border: 1px solid var(--linha); color: var(--tinta-2); background: var(--superficie); }
    .em-mini:hover { color: var(--tinta); border-color: var(--prim-linha); }
    .em-mini.perigo:hover { color: #f87171; border-color: color-mix(in srgb, #f87171 50%, transparent); }
    .em-sel { border-color: var(--prim) !important; background: var(--prim-suave) !important; }
    .em-cab { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: .5rem; }
    .em-info { display: grid; gap: .25rem .9rem; grid-template-columns: max-content minmax(0, 1fr); font-size: .85rem; }
    .em-info dt { color: var(--tinta-2); }
    .em-info dd { overflow-wrap: anywhere; }
    .em-bom { color: #4ade80; } .em-ruim { color: #f87171; } .em-meio { color: #fbbf24; }
</style>

<div x-data="wfEmails(@js($cfg))" x-init="iniciar()" x-cloak class="em-pagina space-y-4">
    <div class="em-topo">
        <a class="btn-sec !py-1.5 !px-3 text-sm" :href="cfg.negocios">← Voltar para Negócios</a>
        <button type="button" class="btn !py-1.5 !px-3 text-sm" x-show="contas.length" @click="sincronizar()" :disabled="ocupado">⟳ Buscar e-mails novos</button>
        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" x-show="contas.length" :class="painel === 'contas' && 'em-sel'" @click="alternar('contas')" x-text="'Caixas conectadas (' + contas.length + ')'"></button>
        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" :class="painel === 'regras' && 'em-sel'" @click="abrirRegras()">Regras das categorias</button>
        @if ($envio)
        <button type="button" class="btn-sec !py-1.5 !px-3 text-sm" :class="painel === 'envio' && 'em-sel'" @click="alternar('envio')">Testar envio do site</button>
        @endif
        <span class="text-xs texto-2" x-show="ocupado" role="status">Falando com o servidor de e-mail…</span>
    </div>

    <p class="aviso-ok !mb-0" x-show="ok" x-text="ok" role="status"></p>
    <p class="erro !mt-0 text-sm" x-show="erro" x-text="erro" role="alert"></p>

    {{-- ================= PRIMEIRA VEZ ================= --}}
    <section class="card p-4 space-y-3" x-show="!contas.length">
        <p class="titulo text-lg">Como funciona</p>
        <div class="em-passos">
            <div><b>1. Conecte sua caixa</b>Titan, Gmail ou outra com IMAP. A senha é testada na hora e guardada criptografada.</div>
            <div><b>2. O site lê e separa</b>Os e-mails dos últimos 30 dias vão para Clientes, Financeiro, Promoções ou Outros.</div>
            <div><b>3. Ajuste as regras</b>Diga quais palavras levam a cada categoria. A lista se atualiza a cada 15 minutos.</div>
        </div>
    </section>

    {{-- ================= CAIXAS CONECTADAS / CONECTAR ================= --}}
    <section class="card p-4 space-y-4" x-show="painel === 'contas' || !contas.length">
        <div class="em-cab" x-show="contas.length">
            <p class="titulo text-lg">Caixas conectadas</p>
            <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="painel = ''">Fechar</button>
        </div>
        <div class="em-contas" x-show="contas.length">
            <template x-for="c in contas" :key="c.id">
                <div class="em-conta">
                    <div style="min-width:0">
                        <span class="em-corta" style="display:block" :title="c.endereco" x-text="(c.tipo === 'gmail' ? 'Gmail · ' : '') + c.endereco"></span>
                        <small x-text="(c.host ? c.host + ' · ' : '') + (c.sincronizado_em ? 'lida ' + quando(c.sincronizado_em) : 'ainda não lida')"></small>
                        <small class="falha" x-show="c.erro" x-text="'Problema: ' + c.erro"></small>
                    </div>
                    <div class="em-topo">
                        <button type="button" class="em-mini" @click="sincronizar(c.id)" :disabled="ocupado">⟳ Buscar nesta caixa</button>
                        <button type="button" class="em-mini" x-show="c.tipo === 'imap'" @click="editarConta(c)" :disabled="ocupado">Trocar senha</button>
                        <button type="button" class="em-mini perigo" @click="remover(c)" :disabled="ocupado">✕ Desconectar</button>
                    </div>
                </div>
            </template>
        </div>

        <div>
            <p class="titulo text-lg" x-text="contas.length ? 'Conectar outra caixa' : 'Conectar uma caixa de e-mail'"></p>
            <p class="text-sm texto-2 mt-1">O site só <b>lê</b> as mensagens dos últimos 30 dias (remetente, assunto e o começo do texto), sem marcar nada como lido nem apagar.
                A senha fica guardada criptografada e nunca aparece de novo aqui. Até 3 caixas.</p>
        </div>
        <div class="em-topo" role="group" aria-label="Provedor de e-mail">
            <span class="text-sm texto-2">Seu e-mail é do:</span>
            <template x-for="p in provedores" :key="p.id">
                <button type="button" class="btn-sec !py-1 !px-3 text-sm" :class="fm.provedor === p.id && 'em-sel'" @click="usarProvedor(p)" x-text="p.nome"></button>
            </template>
            @if ($google)<a href="{{ route('emails.google') }}" class="btn-sec !py-1 !px-3 text-sm">Entrar com Google (Gmail)</a>@endif
        </div>
        <p class="text-xs texto-2" x-show="dica" x-html="dica"></p>
        <form class="em-form" @submit.prevent="conectar()">
            <label>E-mail
                <input class="campo" type="email" x-model="fm.endereco" @change="sugerirHost()" required autocomplete="off" placeholder="voce@suaempresa.com.br">
            </label>
            <label><span>Senha <span x-text="fm.provedor === 'gmail' ? '(senha de app de 16 letras)' : 'da caixa de e-mail'"></span></span>
                <input class="campo" type="password" x-model="fm.senha" required autocomplete="new-password">
            </label>
            <label>Servidor IMAP
                <input class="campo" x-model="fm.host" required placeholder="imap.titan.email">
            </label>
            <label>Porta e segurança
                <select class="campo" x-model="fm.modo">
                    <option value="993-ssl">993 · SSL (o mais comum)</option>
                    <option value="143-starttls">143 · STARTTLS</option>
                </select>
            </label>
            <label>Usuário <span>(só se for diferente do e-mail)</span>
                <input class="campo" x-model="fm.usuario" autocomplete="off" placeholder="em branco = o próprio e-mail">
            </label>
            <div style="display:flex; align-items:flex-end">
                <button class="btn w-full" type="submit" :disabled="ocupado" x-text="ocupado ? 'Testando…' : 'Testar e conectar'"></button>
            </div>
        </form>
    </section>

    {{-- ================= REGRAS ================= --}}
    <section class="card p-4 space-y-3" x-show="painel === 'regras'">
        <div class="em-cab">
            <p class="titulo text-lg">Regras das categorias</p>
            <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="painel = ''">Fechar sem salvar</button>
        </div>
        <p class="text-sm texto-2">De cima para baixo, a primeira regra que bate decide a categoria (sem diferença de acento ou maiúscula).
            Quem está na sua lista de <b>Clientes</b> da área Negócios (e-mail no campo contato) vai sempre para Clientes. O que não bater fica em <b>Outros</b>.
            Para criar uma categoria nova, escreva outro nome.</p>
        <datalist id="em-cats"><template x-for="c in categorias" :key="c"><option :value="c"></option></template></datalist>
        <p class="em-vazio" x-show="!regrasEdit.length">Nenhuma regra: tudo vai para Outros. Clique em “+ Nova regra”.</p>
        <div class="space-y-2">
            <template x-for="(r, i) in regrasEdit" :key="i">
                <div class="em-regra">
                    <input class="campo" list="em-cats" x-model="r.categoria" placeholder="Categoria" maxlength="40" aria-label="Categoria">
                    <select class="campo" x-model="r.campo" aria-label="Onde procurar">
                        <option value="assunto">assunto contém</option>
                        <option value="remetente">remetente contém</option>
                        <option value="qualquer">remetente ou assunto</option>
                    </select>
                    <input class="campo" x-model="r.termo" placeholder="palavra ou parte do e-mail" maxlength="120" aria-label="Termo">
                    <div class="em-topo">
                        <button type="button" class="em-mini" @click="moverRegra(i, -1)" :disabled="i === 0">↑ Subir</button>
                        <button type="button" class="em-mini perigo" @click="regrasEdit.splice(i, 1)">✕ Remover</button>
                    </div>
                </div>
            </template>
        </div>
        <div class="em-topo">
            <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="regrasEdit.push({ categoria: filtro.cat !== 'todas' ? filtro.cat : 'Clientes', campo: 'assunto', termo: '' })">+ Nova regra</button>
            <button type="button" class="btn !py-1 !px-3 text-sm" @click="salvarRegras()" :disabled="ocupado">Salvar e reaplicar</button>
        </div>
    </section>

    {{-- ================= ENVIO DO SITE (administração) ================= --}}
    @if ($envio)
    <section class="card p-4 space-y-3" x-show="painel === 'envio'" x-cloak>
        <div class="em-cab">
            <div>
                <p class="titulo text-lg">Envio de e-mails do site</p>
                <p class="text-sm texto-2 mt-1">É por aqui que saem os lembretes do calendário. Só a administração vê este quadro.</p>
            </div>
            <button type="button" class="btn-sec !py-1 !px-3 text-sm" @click="painel = ''">Fechar</button>
        </div>
        <template x-if="cfg.envio">
            <dl class="em-info">
                <dt>Envio</dt>
                <dd><span x-text="cfg.envio.mailer"></span>
                    <span class="em-meio" x-show="cfg.envio.so_log"> · só grava no log, não envia de verdade (troque MAIL_MAILER para smtp no .env)</span></dd>
                <dt>Servidor</dt>
                <dd x-text="cfg.envio.so_log ? '—' : ((cfg.envio.host || '?') + ':' + (cfg.envio.porta || '?') + (cfg.envio.seguranca ? ' · ' + cfg.envio.seguranca : ''))"></dd>
                <dt>Remetente</dt>
                <dd x-text="cfg.envio.remetente || '(MAIL_FROM_ADDRESS vazio)'"></dd>
                <dt>Lembretes (cron)</dt>
                <dd :class="saudeCron(cfg.envio.calendario, 5).classe" x-text="saudeCron(cfg.envio.calendario, 5).texto"></dd>
                <dt>Leitura de e-mails</dt>
                <dd :class="saudeCron(cfg.envio.emails, 20).classe" x-text="saudeCron(cfg.envio.emails, 20).texto"></dd>
            </dl>
        </template>
        <p class="text-xs texto-2">O cron do cPanel precisa rodar a cada minuto: <code>php /caminho/do/projeto/artisan schedule:run</code>.
            Se “Lembretes” mostra “nunca rodou” ou mais de 5 minutos, o cron não está ativo.</p>
        <button type="button" class="btn !py-1.5 !px-3 text-sm" @click="testarEnvio()" :disabled="ocupado">Enviar e-mail de teste para mim</button>
    </section>
    @endif

    {{-- ================= CAIXA DE ENTRADA ================= --}}
    <div class="em-grade" x-show="contas.length || mensagens.length">
        <aside class="em-lado" aria-label="Categorias">
            <button type="button" class="em-cat" :class="filtro.cat === 'todas' && 'ativa'" @click="filtro.cat = 'todas'">
                <span>Todas</span><b x-text="mensagens.length"></b>
            </button>
            <template x-for="c in categorias" :key="c">
                <button type="button" class="em-cat" :class="filtro.cat === c && 'ativa'" @click="filtro.cat = c" :title="c">
                    <span><i class="em-bolinha" :style="'--c:' + cor(c)"></i><span x-text="c"></span></span>
                    <b x-text="contagem[c] || 0"></b>
                </button>
            </template>
            <label class="em-cat" style="cursor:pointer">
                <span>Só não lidas</span><input type="checkbox" x-model="filtro.naoLidas">
            </label>
        </aside>

        <div class="space-y-2" style="min-width:0">
            <input class="campo" type="search" x-model.debounce.200ms="filtro.busca" placeholder="Buscar por remetente, assunto ou texto…" aria-label="Buscar e-mails">
            <div class="em-lista">
                <template x-for="m in visiveis" :key="m.id">
                    <div class="em-msg" :class="{ 'nao-lida': !m.lido, 'aberta': aberta === m.id }" @click="aberta = aberta === m.id ? null : m.id">
                        <div style="min-width:0; display:flex; align-items:center; gap:.5rem">
                            <i class="em-ponto" x-show="!m.lido" title="Não lida"></i>
                            <span class="de em-corta" :title="(m.de_nome ? m.de_nome + ' ' : '') + '<' + (m.de_email || '') + '>'" x-text="m.de_nome || m.de_email || '(sem remetente)'"></span>
                            <span class="em-etq" :style="'--c:' + cor(m.categoria)" :title="'Categoria: ' + m.categoria + (m.manual ? ' (escolhida à mão)' : '')" x-text="m.categoria"></span>
                        </div>
                        <span class="quando" :title="dataLonga(m.data)" x-text="quando(m.data)"></span>
                        <span class="assunto em-corta" :title="m.assunto" x-text="m.assunto || '(sem assunto)'"></span>
                        <span class="trecho em-corta" x-show="aberta !== m.id" x-text="m.trecho"></span>
                        <div class="em-detalhe" x-show="aberta === m.id" @click.stop>
                            <p x-text="m.trecho ? m.trecho + (m.trecho.length >= 200 ? '…' : '') : '(sem texto)'"></p>
                            <p class="text-xs" x-text="(m.de_email || '') + ' · ' + dataLonga(m.data) + (contaDe(m) ? ' · para ' + contaDe(m) : '')"></p>
                            <a class="btn !py-1 !px-3 text-sm" :href="mailto(m)" x-show="m.de_email">Responder</a>
                            <label class="text-xs texto-2" style="display:flex; align-items:center; gap:.35rem">Categoria
                                <select class="campo" @change="mudarCategoria(m, $event.target.value)">
                                    <option value="auto" :selected="!m.manual">Automática (pelas regras)</option>
                                    <template x-for="c in categorias" :key="c"><option :value="c" x-text="'Sempre ' + c" :selected="m.manual && m.categoria === c"></option></template>
                                </select>
                            </label>
                            <button type="button" class="em-mini" @click="aberta = null">Fechar</button>
                            <span class="text-xs texto-2" style="width:100%">Aqui aparece só o começo do texto. Para ler inteiro, abra no seu programa de e-mail.</span>
                        </div>
                    </div>
                </template>
                <div class="em-vazio" x-show="!visiveis.length">
                    <span x-show="mensagens.length">Nada com esse filtro. <button type="button" class="underline" @click="limparFiltro()">Limpar filtro</button></span>
                    <span x-show="!mensagens.length">Nenhuma mensagem dos últimos 30 dias ainda. Clique em “Buscar e-mails novos”.</span>
                </div>
            </div>
            <p class="text-xs texto-2" x-show="filtradas.length > visiveis.length">
                Mostrando <span x-text="visiveis.length"></span> de <span x-text="filtradas.length"></span>.
                <button type="button" class="underline" @click="limite += 100">Mostrar mais 100</button>
            </p>
        </div>
    </div>
</div>

<script src="{{ asset('js/emails.js') }}?v={{ @filemtime(public_path('js/emails.js')) }}"></script>
@endsection
