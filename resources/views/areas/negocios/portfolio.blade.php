@extends('layouts.app')

@section('titulo', 'Portfólio')
@section('subtitulo', 'Negócios · sua página profissional: projetos, experiência, depoimentos e contato, com o visual do seu jeito.')

@section('conteudo')
@php
    use App\Models\Portfolio;
    $cfg = [
        'dados'     => $dados,
        'slug'      => $slug,
        'publicado' => (bool) $portfolio?->publicado,
        'existe'    => (bool) $portfolio,
        'visitas'   => (int) ($portfolio?->visitas ?? 0),
        'base'      => url('/p'),
        'rotas'     => [
            'salvar'    => route('portfolio.salvar'),
            'previa'    => route('portfolio.previa'),
            'midia'     => url('/midia'),
            'mensagens' => url('/negocios/portfolio/mensagens'),
        ],
        'tipos'     => Portfolio::TIPOS,
        'estilos'   => Portfolio::ESTILOS,
        'temas'     => Portfolio::TEMAS,
        'fontes'    => array_map(fn ($f) => $f[0], Portfolio::FONTES),
        'opcoes'    => Portfolio::OPCOES,
        'redes'     => Portfolio::REDES,
        'max'       => ['secoes' => Portfolio::MAX_SECOES, 'itens' => Portfolio::MAX_ITENS, 'links' => Portfolio::MAX_LINKS],
        'mensagens' => $mensagens->map(fn ($m) => [
            'id' => $m->id, 'nome' => $m->nome, 'contato' => $m->contato, 'assunto' => $m->assunto, 'mensagem' => $m->mensagem,
            'lida' => $m->lida, 'quando' => $m->created_at->tz('America/Sao_Paulo')->format('d/m/Y H:i'),
        ])->values(),
    ];
@endphp
<style>
    [x-cloak] { display: none !important; }
    .pe { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1100px) { .pe { grid-template-columns: minmax(380px, 470px) minmax(0, 1fr); align-items: start; } .pe-prev { position: sticky; top: .75rem; } }
    .pe-abas { display: flex; flex-wrap: wrap; gap: .25rem; border-bottom: 1px solid var(--linha); margin-bottom: .75rem; }
    .pe-aba { padding: .5rem .8rem; font-size: .85rem; font-weight: 600; color: var(--tinta-2); border-bottom: 2px solid transparent; margin-bottom: -1px; background: none; cursor: pointer; }
    .pe-aba.ativa { color: var(--prim-forte); border-color: var(--prim); }
    .pe-rot { display: block; font-size: .75rem; color: var(--tinta-2); margin: .55rem 0 .2rem; }
    .pe-linha { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
    .pe-2 { display: grid; gap: .5rem; grid-template-columns: 1fr 1fr; }
    .pe-sec { border: 1px solid var(--linha); border-radius: .75rem; background: var(--superficie); transition: box-shadow .15s, opacity .15s; }
    .pe-sec.sobre { box-shadow: 0 0 0 2px var(--prim); }
    .pe-sec.arrasta { opacity: .45; }
    .pe-sec-cab { display: flex; align-items: center; gap: .4rem; padding: .5rem .6rem; }
    .pe-alca { cursor: grab; color: var(--tinta-2); padding: .2rem .25rem; user-select: none; font-size: 1.1rem; line-height: 1; }
    .pe-ic { background: none; border: 1px solid transparent; border-radius: .4rem; padding: .2rem .4rem; cursor: pointer; color: var(--tinta-2); font-size: .85rem; line-height: 1.2; }
    .pe-ic:hover { border-color: var(--linha); color: var(--tinta); }
    .pe-ic:disabled { opacity: .3; cursor: default; }
    .pe-sec-corpo { padding: 0 .75rem .75rem; border-top: 1px dashed var(--linha); }
    .pe-item { border: 1px solid var(--linha); border-radius: .6rem; padding: .5rem .6rem; margin-top: .5rem; background: var(--superficie-2); }
    .pe-item.sobre { box-shadow: 0 0 0 2px var(--prim); }
    .pe-tema { border: 2px solid var(--linha); border-radius: .6rem; padding: .4rem; cursor: pointer; text-align: left; background: none; color: var(--tinta); font-size: .75rem; }
    .pe-tema.ativo { border-color: var(--prim); }
    .pe-tema i { display: block; height: 2.2rem; border-radius: .35rem; margin-bottom: .3rem; }
    .pe-temas { display: grid; gap: .4rem; grid-template-columns: repeat(auto-fill, minmax(5.6rem, 1fr)); }
    .pe-ops { display: flex; flex-wrap: wrap; gap: .3rem; }
    .pe-op { padding: .3rem .6rem; border-radius: 999px; border: 1px solid var(--linha); font-size: .78rem; cursor: pointer; background: none; color: var(--tinta); }
    .pe-op.ativo { background: var(--prim); color: var(--on-prim); border-color: var(--prim); }
    .pe-cor { width: 100%; height: 2.1rem; padding: 0; border: 1px solid var(--linha); border-radius: .4rem; background: none; cursor: pointer; }
    .pe-img { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
    .pe-img img, .pe-img .pe-vazio { width: 3.4rem; height: 3.4rem; object-fit: cover; border-radius: .5rem; border: 1px solid var(--linha); background: var(--superficie-2); }
    .pe-img .pe-vazio { display: grid; place-items: center; color: var(--tinta-2); font-size: 1.1rem; }
    .pe-arquivo { position: relative; overflow: hidden; }
    .pe-arquivo input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .pe-frame-box { border: 1px solid var(--linha); border-radius: .9rem; overflow: hidden; background: var(--superficie-2); }
    .pe-frame-bar { display: flex; align-items: center; gap: .35rem; padding: .4rem .6rem; border-bottom: 1px solid var(--linha); font-size: .8rem; }
    .pe-frame-wrap { display: flex; justify-content: center; background: repeating-conic-gradient(var(--superficie-2) 0 25%, var(--superficie) 0 50%) 0 0 / 18px 18px; }
    .pe-frame { border: 0; width: 100%; height: min(82vh, 1100px); background: #fff; transition: width .25s; display: block; }
    .pe-st { font-size: .72rem; padding: .1rem .5rem; border-radius: 9999px; border: 1px solid var(--linha); white-space: nowrap; }
    .pe-msg { border: 1px solid var(--linha); border-radius: .6rem; padding: .7rem; }
    .pe-msg.nova { border-color: var(--prim); }
    .pe-cont { font-size: .7rem; color: var(--tinta-2); float: right; }
    .pe-google { border: 1px solid var(--linha); border-radius: .6rem; padding: .7rem; background: #fff; color: #202124; font-family: Arial, sans-serif; }
    .pe-google .u { font-size: .75rem; color: #4d5156; } .pe-google .t { color: #1a0dab; font-size: 1.1rem; margin: .1rem 0; } .pe-google .d { font-size: .8rem; color: #4d5156; }
    @media (max-width: 1099px) { .pe-so-largo { display: none; } }
</style>

<div x-data="portfolioEditor(@js($cfg))" x-init="iniciar()" @keydown.ctrl.s.window.prevent="salvar()" @keydown.meta.s.window.prevent="salvar()" class="space-y-3" style="max-width:110rem">

    {{-- barra de cima: endereço, publicação, salvar --}}
    <div class="card p-3 flex flex-wrap items-center gap-2">
        <a class="btn-sec !py-1.5 !px-3 text-sm" href="{{ route('areas.negocios') }}">← Negócios</a>
        <span class="pe-st" :style="publicado ? 'color:#4ade80' : 'color:#fbbf24'" x-text="publicado ? 'Publicado' : (existe ? 'Rascunho (só você vê)' : 'Ainda não salvo')"></span>
        <label class="flex items-center gap-1 text-sm" title="Endereço da página">
            <span class="texto-2 hidden sm:inline" x-text="cfg.base + '/'"></span>
            <input class="campo !py-1 !px-2 text-sm" style="width:12rem" x-model="slug" @input="slug = slug.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-{2,}/g, '-'); sujo = true" maxlength="40" aria-label="Endereço do portfólio">
        </label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="publicado" @change="sujo = true"> Publicado</label>
        <span class="text-xs texto-2" x-show="existe">· <span x-text="cfg.visitas"></span> visitas</span>
        <span class="ml-auto flex flex-wrap items-center gap-2">
            <span class="text-xs" :class="erro ? '' : 'texto-2'" :style="erro ? 'color:#ef4444' : ''" x-text="erro || (salvando ? 'Salvando…' : (sujo ? 'Alterações não salvas' : (existe ? 'Tudo salvo' : '')))" role="status"></span>
            <a class="btn-sec !py-1.5 !px-3 text-sm" x-show="existe" :href="cfg.base + '/' + slugSalvo" target="_blank" rel="noopener" x-text="publicado ? 'Ver página ↗' : 'Ver prévia ↗'"></a>
            <button type="button" class="btn !py-1.5 !px-4 text-sm" @click="salvar()" :disabled="salvando">Salvar</button>
        </span>
    </div>

    @if (session('ok')) <div class="card p-3 text-sm" role="status">{{ session('ok') }}</div> @endif
    @if ($errors->any()) <div class="card p-3 text-sm" role="alert" style="color:#ef4444">{{ $errors->first() }}</div> @endif

    {{-- celular: alterna entre editar e ver a prévia --}}
    <div class="flex gap-2 lg:hidden" style="display:none" x-show="estreito" x-cloak>
        <button type="button" class="pe-op" :class="{ ativo: !verPrevia }" @click="verPrevia = false">✏️ Editar</button>
        <button type="button" class="pe-op" :class="{ ativo: verPrevia }" @click="verPrevia = true; atualizarPrevia()">👁️ Prévia</button>
    </div>

    <div class="pe">
        {{-- ================= editor ================= --}}
        <div class="card p-3" x-show="!estreito || !verPrevia">
            <nav class="pe-abas" aria-label="Partes do editor">
                <button type="button" class="pe-aba" :class="{ ativa: aba === 'conteudo' }" @click="aba = 'conteudo'">Conteúdo</button>
                <button type="button" class="pe-aba" :class="{ ativa: aba === 'aparencia' }" @click="aba = 'aparencia'">Aparência</button>
                <button type="button" class="pe-aba" :class="{ ativa: aba === 'seo' }" @click="aba = 'seo'">SEO</button>
                <button type="button" class="pe-aba" :class="{ ativa: aba === 'mensagens' }" @click="aba = 'mensagens'">Mensagens <span class="pe-st" x-show="naoLidas()" x-text="naoLidas()" style="color:#38bdf8"></span></button>
            </nav>

            {{-- ---------- Conteúdo ---------- --}}
            <div x-show="aba === 'conteudo'" class="space-y-3">
                <details class="pe-sec" open>
                    <summary class="pe-sec-cab cursor-pointer font-semibold">🙂 Perfil e cabeçalho</summary>
                    <div class="pe-sec-corpo">
                        <div class="pe-2">
                            <label><span class="pe-rot">Nome</span><input class="campo w-full" x-model="d.perfil.nome" maxlength="80"></label>
                            <label><span class="pe-rot">Profissão / título</span><input class="campo w-full" x-model="d.perfil.titulo" maxlength="120" placeholder="Designer de produto"></label>
                        </div>
                        <label><span class="pe-rot">Resumo <span class="pe-cont" x-text="(d.perfil.resumo || '').length + '/600'"></span></span><textarea class="campo w-full" rows="3" x-model="d.perfil.resumo" maxlength="600" placeholder="Em uma ou duas frases: o que você faz e para quem."></textarea></label>
                        <div class="pe-2">
                            <div><span class="pe-rot">Foto</span>@include('areas.negocios.partials.portfolio-img', ['v' => 'd.perfil.foto'])</div>
                            <div><span class="pe-rot">Capa (cabeçalho "Capa")</span>@include('areas.negocios.partials.portfolio-img', ['v' => 'd.perfil.capa'])</div>
                        </div>
                        <div class="pe-2">
                            <label><span class="pe-rot">Cidade</span><input class="campo w-full" x-model="d.perfil.local" maxlength="80" placeholder="Recife, PE"></label>
                            <label><span class="pe-rot">E-mail público</span><input class="campo w-full" type="email" x-model="d.perfil.email" maxlength="120"></label>
                            <label><span class="pe-rot">WhatsApp / telefone</span><input class="campo w-full" x-model="d.perfil.telefone" maxlength="30"></label>
                            <label><span class="pe-rot">Link do currículo (PDF)</span><input class="campo w-full" x-model="d.perfil.cv" maxlength="500" placeholder="https://…"></label>
                        </div>
                        <label class="flex items-center gap-2 text-sm mt-2"><input type="checkbox" x-model="d.perfil.disponivel"> Mostrar selo “disponível”</label>
                        <input class="campo w-full mt-1" x-show="d.perfil.disponivel" x-model="d.perfil.disponivel_texto" maxlength="60" placeholder="Disponível para novos projetos">
                        <div class="pe-2">
                            <label><span class="pe-rot">Botão “Me contrate”</span><input class="campo w-full" x-model="d.perfil.botao_texto" maxlength="30" placeholder="Vazio = sem botão"></label>
                            <label><span class="pe-rot">Leva para</span><input class="campo w-full" x-model="d.perfil.botao_link" maxlength="500" placeholder="vazio = formulário de contato"></label>
                        </div>
                        <span class="pe-rot">Redes e links</span>
                        <template x-for="(l, li) in d.perfil.links" :key="li">
                            <div class="pe-linha mb-1">
                                <select class="campo !py-1" style="width:8.5rem" x-model="l.rede">
                                    <template x-for="(nome, k) in cfg.redes" :key="k"><option :value="k" x-text="nome" :selected="k === l.rede"></option></template>
                                </select>
                                <input class="campo !py-1 flex-1" style="min-width:8rem" x-model="l.url" maxlength="500" :placeholder="l.rede === 'email' ? 'voce@email.com' : (l.rede === 'whatsapp' ? '81 99999-9999' : 'https://… ou @usuario')">
                                <button type="button" class="pe-ic" @click="d.perfil.links.splice(li, 1)" title="Tirar">✕</button>
                            </div>
                        </template>
                        <button type="button" class="btn-sec !py-1 !px-2 text-xs" @click="d.perfil.links.push({ rede: 'linkedin', url: '', rotulo: '' })" x-show="d.perfil.links.length < cfg.max.links">+ Link</button>
                    </div>
                </details>

                <p class="text-xs texto-2">Arraste pela alça <b>⠿</b> para mudar a ordem das seções. Seções vazias não aparecem na página.</p>

                <template x-for="(s, si) in d.secoes" :key="s.id">
                    <div class="pe-sec" :class="{ sobre: arrasto.alvo === si && arrasto.de !== null && arrasto.de !== si, arrasta: arrasto.de === si }"
                         @dragover.prevent="if (arrasto.tipo === 'secao') arrasto.alvo = si" @drop.prevent="soltarSecao(si)">
                        <div class="pe-sec-cab">
                            <span class="pe-alca" draggable="true" @dragstart="arrastar($event, 'secao', si)" @dragend="arrasto = { tipo: null, de: null, alvo: null }" title="Arraste para mudar a ordem" aria-hidden="true">⠿</span>
                            <span x-text="cfg.tipos[s.tipo].icone"></span>
                            <input class="campo !py-1 !px-2 text-sm font-semibold flex-1" style="min-width:5rem" x-model="s.titulo" maxlength="80" :aria-label="'Título da seção ' + cfg.tipos[s.tipo].nome">
                            <button type="button" class="pe-ic" @click="s.visivel = !s.visivel" :title="s.visivel ? 'Esconder' : 'Mostrar'" x-text="s.visivel ? '👁️' : '🚫'"></button>
                            <button type="button" class="pe-ic" @click="mover(d.secoes, si, si - 1)" :disabled="si === 0" title="Subir">↑</button>
                            <button type="button" class="pe-ic" @click="mover(d.secoes, si, si + 1)" :disabled="si === d.secoes.length - 1" title="Descer">↓</button>
                            <button type="button" class="pe-ic" @click="aberta = aberta === s.id ? null : s.id" :title="aberta === s.id ? 'Fechar' : 'Editar'" x-text="aberta === s.id ? '▴' : '▾'"></button>
                        </div>
                        <div class="pe-sec-corpo" x-show="aberta === s.id">
                            <label><span class="pe-rot">Subtítulo</span><input class="campo w-full" x-model="s.subtitulo" maxlength="200"></label>
                            <label x-show="usaTexto(s.tipo)"><span class="pe-rot" x-text="['sobre', 'texto'].includes(s.tipo) ? 'Texto' : (s.tipo === 'contato' ? 'Texto do contato' : 'Introdução (opcional)')"></span>
                                <textarea class="campo w-full" :rows="['sobre', 'texto'].includes(s.tipo) ? 6 : 2" x-model="s.texto" maxlength="5000"></textarea></label>
                            <div x-show="['sobre', 'texto', 'contato'].includes(s.tipo)"><span class="pe-rot">Imagem da seção</span>@include('areas.negocios.partials.portfolio-img', ['v' => 's.img'])</div>
                            <label class="flex items-center gap-2 text-sm mt-2" x-show="s.tipo === 'contato'"><input type="checkbox" x-model="s.formulario"> Formulário “Fale comigo” (as mensagens chegam na aba Mensagens)</label>

                            <span class="pe-rot">Estilo</span>
                            <div class="pe-ops"><template x-for="e in cfg.estilos[s.tipo]" :key="e"><button type="button" class="pe-op" :class="{ ativo: s.estilo === e }" @click="s.estilo = e" x-text="nomeOp(e)"></button></template></div>
                            <div class="pe-2">
                                <div x-show="usaColunas(s.tipo)"><span class="pe-rot">Colunas</span><div class="pe-ops"><template x-for="n in [1, 2, 3, 4]" :key="n"><button type="button" class="pe-op" :class="{ ativo: s.colunas === n }" @click="s.colunas = n" x-text="n"></button></template></div></div>
                                <div><span class="pe-rot">Fundo da seção</span>
                                    <select class="campo !py-1 w-full" x-model="s.fundo"><option value="nenhum">Nenhum</option><option value="cartao">Cartão</option><option value="destaque">Faixa colorida</option><option value="invertido">Invertido</option></select></div>
                            </div>

                            {{-- itens --}}
                            <template x-if="cfg.tipos[s.tipo].itens.length">
                                <div class="mt-2">
                                    <span class="pe-rot" x-text="'Itens (' + s.itens.length + ')'"></span>
                                    <template x-for="(it, ii) in s.itens" :key="it.id">
                                        <div class="pe-item" :class="{ sobre: arrasto.tipo === 'item' && arrasto.secao === si && arrasto.alvo === ii && arrasto.de !== ii }"
                                             @dragover.prevent="if (arrasto.tipo === 'item' && arrasto.secao === si) arrasto.alvo = ii" @drop.prevent="soltarItem(si, ii)">
                                            <div class="pe-linha">
                                                <span class="pe-alca" draggable="true" @dragstart="arrastar($event, 'item', ii, si)" @dragend="arrasto = { tipo: null, de: null, alvo: null }" aria-hidden="true">⠿</span>
                                                <b class="text-sm flex-1 truncate" x-text="it.titulo || it.texto || ('Item ' + (ii + 1))"></b>
                                                <button type="button" class="pe-ic" @click="mover(s.itens, ii, ii - 1)" :disabled="ii === 0" title="Subir">↑</button>
                                                <button type="button" class="pe-ic" @click="mover(s.itens, ii, ii + 1)" :disabled="ii === s.itens.length - 1" title="Descer">↓</button>
                                                <button type="button" class="pe-ic" @click="s.itens.splice(ii + 1, 0, copia(it))" title="Duplicar" x-show="s.itens.length < cfg.max.itens">⧉</button>
                                                <button type="button" class="pe-ic" @click="s.itens.splice(ii, 1)" title="Tirar">✕</button>
                                            </div>
                                            <template x-for="campo in cfg.tipos[s.tipo].itens" :key="campo">
                                                <div>
                                                    <template x-if="campo === 'img'"><div><span class="pe-rot" x-text="rotulo(s.tipo, campo)"></span>@include('areas.negocios.partials.portfolio-img', ['v' => 'it.img'])</div></template>
                                                    <template x-if="campo === 'texto'"><label><span class="pe-rot" x-text="rotulo(s.tipo, campo)"></span><textarea class="campo w-full" rows="2" x-model="it.texto" maxlength="2000"></textarea></label></template>
                                                    <template x-if="campo === 'nivel'"><label><span class="pe-rot" x-text="rotulo(s.tipo, campo) + ': ' + it.nivel + '%'"></span><input type="range" min="0" max="100" step="5" class="w-full" x-model.number="it.nivel"></label></template>
                                                    <template x-if="!['img', 'texto', 'nivel'].includes(campo)"><label><span class="pe-rot" x-text="rotulo(s.tipo, campo)"></span><input class="campo w-full !py-1" x-model="it[campo]" :maxlength="campo === 'link' ? 500 : (campo === 'tags' ? 200 : 120)" :placeholder="dica(s.tipo, campo)"></label></template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <button type="button" class="btn-sec !py-1 !px-2 text-xs mt-2" @click="novoItem(s)" x-show="s.itens.length < cfg.max.itens">+ Adicionar item</button>
                                </div>
                            </template>

                            <div class="pe-linha mt-3 pt-2" style="border-top:1px dashed var(--linha)">
                                <button type="button" class="pe-ic" @click="duplicarSecao(si)" x-show="d.secoes.length < cfg.max.secoes">⧉ Duplicar seção</button>
                                <button type="button" class="pe-ic ml-auto" style="color:#ef4444" @click="if (confirm('Tirar a seção “' + (s.titulo || cfg.tipos[s.tipo].nome) + '”?')) d.secoes.splice(si, 1)">🗑 Tirar seção</button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="pe-linha" x-show="d.secoes.length < cfg.max.secoes">
                    <select class="campo !py-1.5 flex-1" x-model="novoTipo" aria-label="Tipo de seção">
                        <template x-for="(t, k) in cfg.tipos" :key="k"><option :value="k" x-text="t.icone + ' ' + t.nome"></option></template>
                    </select>
                    <button type="button" class="btn !py-1.5 !px-3 text-sm" @click="novaSecao()">+ Seção</button>
                </div>
            </div>

            {{-- ---------- Aparência ---------- --}}
            <div x-show="aba === 'aparencia'" x-cloak class="space-y-1">
                <span class="pe-rot">Temas prontos (depois dá para mudar tudo)</span>
                <div class="pe-temas">
                    <template x-for="(t, k) in cfg.temas" :key="k">
                        <button type="button" class="pe-tema" :class="{ ativo: d.tema.preset === k }" @click="aplicarTema(k)">
                            <i :style="`background: linear-gradient(135deg, ${t.fundo} 0 45%, ${t.cor} 45% 72%, ${t.cor2} 72%)`"></i><span x-text="t.nome"></span>
                        </button>
                    </template>
                </div>
                <div class="grid gap-2 mt-2" style="grid-template-columns:repeat(5,minmax(0,1fr))">
                    <template x-for="[k, nome] in [['cor', 'Principal'], ['cor2', 'Secundária'], ['fundo', 'Fundo'], ['texto', 'Texto'], ['cartao', 'Cartões']]" :key="k">
                        <label class="text-center"><span class="pe-rot" x-text="nome"></span><input type="color" class="pe-cor" x-model="d.tema[k]"></label>
                    </template>
                </div>
                <div class="pe-2">
                    <label><span class="pe-rot">Letra dos títulos</span><select class="campo w-full !py-1" x-model="d.tema.fonte_titulo"><template x-for="(n, k) in cfg.fontes" :key="k"><option :value="k" x-text="n" :selected="k === d.tema.fonte_titulo"></option></template></select></label>
                    <label><span class="pe-rot">Letra do texto</span><select class="campo w-full !py-1" x-model="d.tema.fonte_texto"><template x-for="(n, k) in cfg.fontes" :key="k"><option :value="k" x-text="n" :selected="k === d.tema.fonte_texto"></option></template></select></label>
                </div>
                <template x-for="[k, nome] in [['layout', 'Layout'], ['cabecalho', 'Cabeçalho'], ['fundo_tipo', 'Fundo da página']]" :key="k">
                    <div><span class="pe-rot" x-text="nome"></span>
                        <div class="pe-ops"><template x-for="o in cfg.opcoes[k]" :key="o"><button type="button" class="pe-op" :class="{ ativo: d.tema[k] === o }" @click="d.tema[k] = o" x-text="nomeOp(o)"></button></template></div></div>
                </template>
                <div x-show="d.tema.fundo_tipo === 'imagem'" class="pe-item">
                    <span class="pe-rot">Imagem ou GIF de fundo (até 25 MB)</span>@include('areas.negocios.partials.portfolio-img', ['v' => 'd.tema.fundo_img'])
                    <label><span class="pe-rot" x-text="'Escurecer: ' + d.tema.fundo_escurecer + '%'"></span><input type="range" min="0" max="90" step="5" class="w-full" x-model.number="d.tema.fundo_escurecer"></label>
                    <label><span class="pe-rot" x-text="'Desfoque: ' + d.tema.fundo_desfoque + 'px'"></span><input type="range" min="0" max="20" class="w-full" x-model.number="d.tema.fundo_desfoque"></label>
                    <label class="flex items-center gap-2 text-sm mt-1"><input type="checkbox" x-model="d.tema.fundo_fixo"> Fundo parado ao rolar</label>
                    <p class="text-xs texto-2 mt-1">Dica: com fundo escuro, troque a cor do texto para clara e ligue “Cartões de vidro”.</p>
                </div>
                <div x-show="d.tema.fundo_tipo === 'padrao'"><span class="pe-rot">Desenho</span>
                    <div class="pe-ops"><template x-for="o in cfg.opcoes.padrao" :key="o"><button type="button" class="pe-op" :class="{ ativo: d.tema.padrao === o }" @click="d.tema.padrao = o" x-text="nomeOp(o)"></button></template></div></div>
                <template x-for="[k, nome] in [['foto_forma', 'Forma da foto'], ['cantos', 'Cantos'], ['sombra', 'Sombra dos cartões'], ['titulos', 'Títulos das seções'], ['largura', 'Largura'], ['espacamento', 'Espaço entre seções'], ['tamanho_texto', 'Tamanho do texto'], ['animacao', 'Animação ao rolar']]" :key="k">
                    <div><span class="pe-rot" x-text="nome"></span>
                        <div class="pe-ops"><template x-for="o in cfg.opcoes[k]" :key="o"><button type="button" class="pe-op" :class="{ ativo: d.tema[k] === o }" @click="d.tema[k] = o" x-text="nomeOp(o)"></button></template></div></div>
                </template>
                <div class="pt-2 space-y-1">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="d.tema.menu"> Menu fixo no topo com as seções</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="d.tema.vidro"> Cartões de vidro (bom com imagem de fundo)</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="d.tema.modo_escuro"> Botão para o visitante trocar claro/escuro</label>
                </div>
            </div>

            {{-- ---------- SEO ---------- --}}
            <div x-show="aba === 'seo'" x-cloak class="space-y-2">
                <p class="text-xs texto-2">Como a página aparece no Google e quando alguém compartilha o link. Vazio = usa o nome, a profissão e o resumo.</p>
                <label><span class="pe-rot">Título <span class="pe-cont" x-text="(d.seo.titulo || '').length + '/70'"></span></span><input class="campo w-full" x-model="d.seo.titulo" maxlength="70" :placeholder="tituloSeo()"></label>
                <label><span class="pe-rot">Descrição <span class="pe-cont" x-text="(d.seo.descricao || '').length + '/170'"></span></span><textarea class="campo w-full" rows="3" x-model="d.seo.descricao" maxlength="170" :placeholder="(d.perfil.resumo || '').slice(0, 160)"></textarea></label>
                <div><span class="pe-rot">Imagem do compartilhamento (1200×630 fica melhor)</span>@include('areas.negocios.partials.portfolio-img', ['v' => 'd.seo.imagem'])</div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="d.seo.indexar"> Deixar o Google mostrar esta página</label>
                <span class="pe-rot">Assim no Google</span>
                <div class="pe-google">
                    <div class="u" x-text="cfg.base + '/' + slug"></div>
                    <div class="t" x-text="d.seo.titulo || tituloSeo()"></div>
                    <div class="d" x-text="(d.seo.descricao || d.perfil.resumo || '').slice(0, 160)"></div>
                </div>
            </div>

            {{-- ---------- Mensagens ---------- --}}
            <div x-show="aba === 'mensagens'" x-cloak class="space-y-2">
                <p class="text-xs texto-2" x-show="!temFormulario()">O formulário está desligado: ligue “Formulário Fale comigo” na seção Contato para receber mensagens.</p>
                <p class="text-sm texto-2" x-show="!mensagens.length">Nenhuma mensagem ainda.</p>
                <template x-for="m in mensagens" :key="m.id">
                    <article class="pe-msg" :class="{ nova: !m.lida }">
                        <div class="pe-linha">
                            <b x-text="m.nome"></b><span class="text-xs texto-2" x-text="m.quando"></span>
                            <span class="ml-auto flex gap-1">
                                <button type="button" class="pe-ic" @click="marcar(m, !m.lida)" x-text="m.lida ? 'Marcar como nova' : 'Marcar como lida'"></button>
                                <button type="button" class="pe-ic" style="color:#ef4444" @click="apagarMsg(m)" title="Apagar">🗑</button>
                            </span>
                        </div>
                        <p class="text-sm"><a class="link-prim break-all" :href="linkContato(m.contato)" target="_blank" rel="noopener" x-text="m.contato"></a></p>
                        <p class="text-sm font-semibold mt-1" x-show="m.assunto" x-text="m.assunto"></p>
                        <p class="text-sm mt-1" style="white-space:pre-line" x-text="m.mensagem"></p>
                    </article>
                </template>
                @if ($portfolio)
                    <details class="mt-6 text-sm">
                        <summary class="cursor-pointer texto-2">Excluir o portfólio</summary>
                        <form method="post" action="{{ route('portfolio.excluir') }}" class="pe-linha mt-2">
                            @csrf @method('DELETE')
                            <input class="campo !py-1" name="confirmar" placeholder="Digite EXCLUIR" required>
                            <button class="btn-sec !py-1 !px-3 text-sm" style="color:#ef4444">Excluir de vez</button>
                        </form>
                        <p class="text-xs texto-2 mt-1">Apaga a página e as mensagens. As imagens continuam na sua conta.</p>
                    </details>
                @endif
            </div>
        </div>

        {{-- ================= prévia ao vivo ================= --}}
        <div class="pe-prev" x-show="!estreito || verPrevia">
            <div class="pe-frame-box">
                <div class="pe-frame-bar">
                    <b>Prévia ao vivo</b>
                    <span class="texto-2 text-xs" x-show="carregando">atualizando…</span>
                    <span class="ml-auto pe-ops pe-so-largo">
                        <button type="button" class="pe-op" :class="{ ativo: tela === '100%' }" @click="tela = '100%'" title="Computador">💻</button>
                        <button type="button" class="pe-op" :class="{ ativo: tela === '820px' }" @click="tela = '820px'" title="Tablet">📟</button>
                        <button type="button" class="pe-op" :class="{ ativo: tela === '390px' }" @click="tela = '390px'" title="Celular">📱</button>
                    </span>
                </div>
                <div class="pe-frame-wrap">
                    <iframe x-ref="frame" class="pe-frame" :style="'width:' + tela" title="Prévia do portfólio" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/portfolio-editor.js') }}?v={{ @filemtime(public_path('js/portfolio-editor.js')) }}"></script>
@endsection
