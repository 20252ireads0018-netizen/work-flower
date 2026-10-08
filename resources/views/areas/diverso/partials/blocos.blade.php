{{-- ===== Bloco GitHub / VS Code: ajudantes, editor (ide.js) e estilo ===== --}}
<script src="{{ asset('js/ide.js') }}?v={{ @filemtime(public_path('js/ide.js')) }}"></script>
<script src="{{ asset('js/escrita.js') }}?v={{ @filemtime(public_path('js/escrita.js')) }}"></script>
<style>
    .wf-repo { border: 1px solid var(--linha); border-left: 4px solid var(--prim); border-radius: .6rem; padding: .6rem .7rem; background: var(--superficie-2); display: grid; gap: .5rem; }
    .wf-repo.invalido { border-left-color: #f87171; }
    .wf-repo-linha { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .wf-repo-linha .wf-cel { flex: 1; min-width: 8rem; background: var(--superficie); border: 1px solid var(--linha); }
    .wf-repo-linha .wf-repo-ramo { flex: 0 0 8rem; min-width: 6rem; }
    .wf-repo-selo { display: inline-flex; align-items: center; gap: .3rem; padding: .1rem .5rem; font-size: .7rem; border-radius: 9999px; border: 1px solid var(--prim-linha); color: var(--prim-forte); background: var(--superficie); white-space: nowrap; }
    .wf-repo-aviso { font-size: .72rem; color: #f87171; }
    .wf-repo-acoes { display: flex; flex-wrap: wrap; gap: .35rem; padding-top: .4rem; border-top: 1px solid var(--linha); }
    .wf-repo-acoes .btn-sec { padding: .25rem .6rem; font-size: .75rem; }

    /* ===== Editor de código (mini-IDE) ===== */
    .wf-ide { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 24rem; }
    .wf-ide-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-ide-barra .btn-sec { padding: .2rem .55rem; font-size: .75rem; cursor: pointer; }
    .wf-ide-barra .sep { width: 1px; align-self: stretch; background: var(--linha); margin: 0 .15rem; }
    .wf-ide-corpo { flex: 1; min-height: 0; display: grid; grid-template-columns: 13rem minmax(0, 1fr); gap: .5rem; }
    .wf-ide-lado { border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); overflow: auto; padding: .3rem 0; font-size: .8rem; min-height: 0; }
    .wf-ide-no { display: flex; align-items: center; gap: .3rem; padding: .15rem .4rem; cursor: pointer; white-space: nowrap; color: var(--tinta); }
    .wf-ide-no:hover { background: var(--superficie); }
    .wf-ide-no.ativo { background: var(--prim-linha); }
    .wf-ide-no.sel { outline: 1px solid var(--prim-linha); outline-offset: -1px; }
    .wf-ide-no .nome { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .wf-ide-no .seta { width: .8rem; color: var(--tinta-2); font-size: .65rem; text-align: center; flex-shrink: 0; }
    .wf-ide-no .acoes { display: none; gap: .1rem; }
    .wf-ide-no:hover .acoes { display: flex; }
    .wf-ide-res { padding: .2rem .5rem; font-size: .72rem; cursor: pointer; border-top: 1px solid var(--linha); }
    .wf-ide-res:hover { background: var(--superficie); }
    .wf-ide-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; gap: .4rem; }
    .wf-ide-abas { display: flex; gap: .2rem; overflow-x: auto; flex-shrink: 0; }
    .wf-ide-aba { display: flex; align-items: center; gap: .4rem; padding: .25rem .55rem; font-size: .75rem; border: 1px solid var(--linha); border-bottom: 2px solid transparent; border-radius: .4rem .4rem 0 0; background: var(--superficie-2); cursor: pointer; white-space: nowrap; color: var(--tinta-2); }
    .wf-ide-aba.ativo { color: var(--tinta); border-bottom-color: var(--prim); background: var(--superficie); }
    .wf-ide-aba .x { opacity: .6; }
    .wf-ide-aba .x:hover { opacity: 1; color: #f87171; }
    .wf-ide-ed { position: relative; flex: 1; min-height: 10rem; display: flex; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie); overflow: hidden; }
    .wf-ide-num { flex-shrink: 0; min-width: 2.6rem; padding: .6rem .5rem calc(2rem + 16px) .6rem; text-align: right; color: var(--tinta-2); font: 13px/1.5 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow: hidden; user-select: none; border-right: 1px solid var(--linha); background: var(--superficie-2); }
    .wf-ide-cod { position: relative; flex: 1; min-width: 0; }
    .wf-ide-pre, .wf-ide-ta { position: absolute; inset: 0; margin: 0; border: 0; font: 13px/1.5 ui-monospace, Menlo, Consolas, monospace; tab-size: 2; -moz-tab-size: 2; white-space: pre; letter-spacing: 0; padding: .6rem .6rem 2rem; }
    .wf-ide-pre { overflow: auto; scrollbar-width: none; pointer-events: none; color: var(--tinta); padding-right: calc(.6rem + 16px); padding-bottom: calc(2rem + 16px); }
    .wf-ide-pre::-webkit-scrollbar { display: none; }
    .wf-ide-ta { overflow: auto; background: transparent; color: transparent; -webkit-text-fill-color: transparent; caret-color: var(--tinta); resize: none; outline: none; }
    .wf-ide-ta::selection { background: rgba(99, 140, 255, .35); }
    .wf-ide-vazio { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; padding: 1rem; font-size: .85rem; color: var(--tinta-2); border: 1px dashed var(--linha); border-radius: .4rem; }
    .wf-h-c { color: var(--tinta-2); font-style: italic; }
    .wf-h-s { color: #22c55e; }
    .wf-h-n { color: #f59e0b; }
    .wf-h-k { color: #a78bfa; }
    .wf-h-t { color: #38bdf8; }
    .wf-ide-busca { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; padding: .35rem; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie-2); font-size: .75rem; }
    .wf-ide-busca .campo { width: 9rem; padding: .25rem .5rem; font-size: .8rem; }
    .wf-ide-busca .btn-sec { padding: .15rem .5rem; font-size: .72rem; }
    .wf-ide-painel { flex-shrink: 0; height: 11rem; display: flex; flex-direction: column; border: 1px solid var(--linha); border-radius: .4rem; background: var(--superficie); overflow: hidden; }
    .wf-ide-painel-topo { display: flex; align-items: center; gap: .3rem; padding: .2rem .4rem; background: var(--superficie-2); border-bottom: 1px solid var(--linha); font-size: .72rem; }
    .wf-ide-painel-topo .t { padding: .1rem .5rem; border-radius: .3rem; cursor: pointer; color: var(--tinta-2); }
    .wf-ide-painel-topo .t.ativo { background: var(--superficie); color: var(--tinta); }
    .wf-ide-saida { flex: 1; min-height: 0; overflow: auto; padding: .4rem .6rem; font: 12px/1.45 ui-monospace, Menlo, Consolas, monospace; }
    .wf-ide-log { white-space: pre-wrap; word-break: break-word; border-bottom: 1px dashed var(--linha); padding: .1rem 0; }
    .wf-ide-log.l-error { color: #f87171; }
    .wf-ide-log.l-warn { color: #fbbf24; }
    .wf-ide-frame { flex: 1; min-height: 0; width: 100%; border: 0; background: #fff; }
    .wf-ide-status { display: flex; flex-wrap: wrap; gap: .3rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) {
        .wf-ide { min-height: 0; height: auto; }
        .wf-ide-corpo { grid-template-columns: 1fr; }
        .wf-ide-lado { max-height: 10rem; }
        .wf-ide-ed { height: 22rem; flex: none; }
    }

    /* ===== Editor de escrita (estilo Notion) ===== */
    .wf-es { display: flex; flex-direction: column; gap: .4rem; height: 100%; min-height: 14rem; }
    .wf-es-barra { display: flex; flex-wrap: wrap; align-items: center; gap: .3rem; }
    .wf-es-barra .btn-sec { padding: .2rem .55rem; font-size: .75rem; cursor: pointer; min-width: 1.9rem; }
    .wf-es-barra .btn-sec:disabled { opacity: .45; cursor: default; }
    .wf-es-sep { width: 1px; align-self: stretch; min-height: 1.1rem; background: var(--linha); margin: 0 .1rem; }
    .wf-es-sum { border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie-2); padding: .3rem; max-height: 8rem; overflow: auto; font-size: .8rem; }
    .wf-es-sum button { display: block; width: 100%; text-align: left; padding: .15rem .4rem; border-radius: .3rem; color: var(--tinta); cursor: pointer; background: none; border: 0; }
    .wf-es-sum button:hover { background: var(--superficie); }
    .wf-es-folha { position: relative; flex: 1; min-height: 10rem; overflow: auto; display: flex; flex-direction: column; border: 1px solid var(--linha); border-radius: .5rem; background: var(--superficie); padding: .75rem .75rem .5rem .25rem; line-height: 1.6; color: var(--tinta); }
    .wf-es-bloco { position: relative; display: flex; align-items: flex-start; border-radius: .3rem; }
    .wf-es-bloco.sobre { box-shadow: 0 -2px 0 0 var(--prim); }
    .wf-es-bloco.arrastando { opacity: .45; }
    .wf-es-lado { display: flex; flex-shrink: 0; width: 2.9rem; justify-content: flex-end; opacity: 0; transition: opacity .12s; }
    .wf-es-bloco:hover > .wf-es-lado, .wf-es-bloco.foco > .wf-es-lado { opacity: 1; }
    .wf-es-lbtn { width: 1.3rem; height: 1.5rem; display: flex; align-items: center; justify-content: center; font-size: .8rem; line-height: 1; color: var(--tinta-2); border: 0; background: none; border-radius: .25rem; cursor: pointer; }
    .wf-es-lbtn:hover { background: var(--superficie-2); color: var(--tinta); }
    .wf-es-grip { cursor: grab; letter-spacing: -.15em; font-size: .7rem; }
    .wf-es-corpo { flex: 1; min-width: 0; display: flex; align-items: flex-start; gap: .5rem; }
    .wf-es-txt { flex: 1; min-width: 0; }
    .wf-es-ver, .wf-es-ta { display: block; width: 100%; margin: 0; padding: .12rem 0; font: inherit; line-height: inherit; color: inherit; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; min-height: 1.6em; }
    .wf-es-ta { background: transparent; border: 0; border-radius: 0; outline: 0; box-shadow: none; resize: none; overflow: hidden; appearance: none; }
    .wf-es-ver { cursor: text; }
    .wf-es-ph { color: var(--tinta-2); opacity: .7; }
    .wf-es-marca { flex-shrink: 0; min-width: 1.2rem; text-align: center; color: var(--tinta-2); padding-top: .12rem; user-select: none; }
    .wf-es-chk { flex-shrink: 0; width: 1rem; height: 1rem; margin-top: .4em; accent-color: var(--prim); cursor: pointer; }
    .wf-es-hr { flex: 1; border: 0; border-top: 1px solid var(--linha); margin: .8rem 0; }
    .wf-es-corpo.t-h1 { margin-top: .6rem; } .wf-es-corpo.t-h1 .wf-es-txt { font-size: 1.75em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h2 { margin-top: .5rem; } .wf-es-corpo.t-h2 .wf-es-txt { font-size: 1.4em; font-weight: 700; line-height: 1.3; }
    .wf-es-corpo.t-h3 { margin-top: .4rem; } .wf-es-corpo.t-h3 .wf-es-txt { font-size: 1.15em; font-weight: 600; line-height: 1.35; }
    .wf-es-corpo.t-quote .wf-es-txt { border-left: 3px solid var(--tinta-2); padding-left: .8rem; font-style: italic; color: var(--tinta-2); }
    .wf-es-corpo.t-callout { background: var(--superficie-2); border: 1px solid var(--linha); border-left: 3px solid var(--prim); border-radius: .4rem; padding: .4rem .7rem; }
    .wf-es-corpo.t-code .wf-es-txt { background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .4rem; padding: .3rem .6rem .4rem; }
    .wf-es-corpo.t-code .wf-es-ta { font: 12.5px/1.5 ui-monospace, Menlo, Consolas, monospace; white-space: pre; overflow-x: auto; tab-size: 2; min-height: 3em; }
    .wf-es-lang { width: 8rem; margin-bottom: .2rem; padding: 0; font-size: .68rem; color: var(--tinta-2); background: transparent; border: 0; outline: 0; }
    .wf-es-corpo.feito .wf-es-txt, .wf-es-corpo.feito .wf-es-ta { text-decoration: line-through; color: var(--tinta-2); }
    .wf-es-ver a { color: var(--prim-forte); text-decoration: underline; }
    .wf-es-ver mark { background: rgba(250, 204, 21, .35); color: inherit; border-radius: .2rem; padding: 0 .1em; }
    .wf-es-cod { font: .88em ui-monospace, Menlo, Consolas, monospace; background: var(--superficie-2); border: 1px solid var(--linha); border-radius: .25rem; padding: 0 .3em; }
    .wf-es-menu { position: absolute; left: 2.9rem; top: 100%; z-index: 30; margin-top: .15rem; width: 17rem; max-height: 15rem; overflow: auto; padding: .3rem; background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; box-shadow: 0 12px 30px rgba(0, 0, 0, .45); }
    .wf-es-menu-bl { left: .3rem; top: 1.6rem; width: 10rem; margin-top: 0; }
    .wf-es-op { display: flex; align-items: center; gap: .6rem; width: 100%; padding: .3rem .45rem; border: 0; border-radius: .4rem; text-align: left; font-size: .8rem; color: var(--tinta); background: none; cursor: pointer; }
    .wf-es-op small { display: block; font-size: .68rem; color: var(--tinta-2); }
    .wf-es-op.sel, .wf-es-op:hover { background: var(--superficie-2); }
    .wf-es-ic { width: 1.7rem; height: 1.7rem; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; border: 1px solid var(--linha); border-radius: .35rem; background: var(--superficie-2); }
    .wf-es-vazio { padding: .4rem; font-size: .75rem; color: var(--tinta-2); }
    .wf-es-fim { flex: 1; min-height: 3rem; cursor: text; }
    .wf-es-rodape { display: flex; flex-wrap: wrap; gap: .2rem 1rem; font-size: .7rem; color: var(--tinta-2); }
    @media (max-width: 899px) {
        .wf-es { height: auto; min-height: 0; }
        .wf-es-folha { min-height: 14rem; max-height: 70vh; }
        .wf-es-lado { opacity: .55; width: 2.6rem; }
    }
</style>
<script>
    // Funções puras do bloco de código (não dependem do Alpine nem do diverso.js).
    window.WFRepo = {
        _dono: /^[A-Za-z0-9-]{1,39}$/,
        _repo: /^[A-Za-z0-9._-]{1,100}$/,
        /** Aceita "dono/repo", URL do GitHub (com /tree/..., ?..., .git) ou git@github.com:dono/repo.git. */
        parse(v) {
            let s = String(v || '').trim()
                .replace(/^git@github\.com:/i, '')
                .replace(/^(?:https?:\/\/)?(?:www\.)?github\.com\//i, '')
                .replace(/[?#].*$/, '');
            const p = s.split('/').filter(Boolean);
            if (p.length < 2) return null;
            const dono = p[0], repo = p[1].replace(/\.git$/i, '');
            if (!this._dono.test(dono) || !this._repo.test(repo) || repo === '.' || repo === '..') return null;
            return { dono, repo };
        },
        ok(v) { return !!this.parse(v); },
        slug(r) { const p = this.parse(r && r.nome); return p ? p.dono + '/' + p.repo : ''; },
        ramo(r) {
            const b = String((r && r.ramo) || '').trim().replace(/[^A-Za-z0-9._\/-]/g, '');
            return b.includes('..') ? '' : b.replace(/^\/+|\/+$/g, '');
        },
        base(r) { const s = this.slug(r); return s ? 'https://github.com/' + s : ''; },
        /** Endereços seguros; '#' quando o repositório não é válido. */
        url(r, tipo) {
            const s = this.slug(r);
            if (!s) return '#';
            const b = this.ramo(r), tree = b ? '/tree/' + b : '';
            switch (tipo) {
                case 'issues': return 'https://github.com/' + s + '/issues';
                case 'pulls': return 'https://github.com/' + s + '/pulls';
                case 'actions': return 'https://github.com/' + s + '/actions';
                case 'web': return 'https://vscode.dev/github/' + s + tree;
                case 'dev': return 'https://github.dev/' + s + tree;
                case 'clone': return 'vscode://vscode.git/clone?url=' + encodeURIComponent('https://github.com/' + s + '.git');
                default: return 'https://github.com/' + s + tree;
            }
        },
        cmd(r) {
            const s = this.slug(r);
            if (!s) return '';
            const b = this.ramo(r);
            return 'git clone ' + (b ? '--branch ' + b + ' ' : '') + 'https://github.com/' + s + '.git';
        },
        local(r) {
            const p = String((r && r.pasta) || '').trim();
            if (!p) return '#';
            return 'vscode://file/' + encodeURI(p.replace(/\\/g, '/')).replace(/#/g, '%23').replace(/\?/g, '%3F');
        },
        async copiar(t) {
            t = String(t || '');
            if (!t) return false;
            try { await navigator.clipboard.writeText(t); return true; } catch { /* tenta o método antigo */ }
            try {
                const a = document.createElement('textarea');
                a.value = t; a.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(a); a.select();
                const ok = document.execCommand('copy');
                a.remove();
                return ok;
            } catch { return false; }
        },
    };
</script>

<template x-for="b in blocosDe(aba)" :key="b.id">
    <x-diverso.item sec="b.secao" bid="b.id" :minw="220" :minh="140" :fixo="false">
        <x-slot name="cabeca">
            <input class="wf-titulo flex-1" x-model="b.titulo" aria-label="Título do bloco">
        </x-slot>
        <x-slot name="acoes">
            <button type="button" class="mini-btn" title="Salvar na biblioteca" aria-label="Salvar na biblioteca" @click="guardarBloco(b)">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
            </button>
            <button type="button" class="mini-btn perigo" title="Excluir bloco" @click="removerBloco(b)">✕</button>
        </x-slot>

        <div class="flex flex-col h-full gap-3">
            <div class="flex-1 min-h-0">

                {{-- Texto: editor por blocos (lógica em public/js/escrita.js; o conteúdo fica em b.dados.texto como Markdown) --}}
                <template x-if="b.tipo === 'texto'">
                    <div class="wf-es" x-data="WFEscrita(b)" x-init="iniciar()"
                        @click.outside="foco = null; menu = null; opcBl = null; sumario = false">

                        <div class="wf-es-barra" role="toolbar" aria-label="Ferramentas de escrita">
                            <select class="campo wf-link-sel" aria-label="Tipo do bloco" title="Tipo do bloco" :disabled="!atual()"
                                    :value="atual() ? atual().t : 'p'" @change="mudarTipo($event.target.value)">
                                <option value="p">Texto</option>
                                <option value="h1">Título 1</option>
                                <option value="h2">Título 2</option>
                                <option value="h3">Título 3</option>
                                <option value="ul">Lista com marcadores</option>
                                <option value="ol">Lista numerada</option>
                                <option value="todo">Lista de tarefas</option>
                                <option value="quote">Citação</option>
                                <option value="callout">Destaque</option>
                                <option value="code">Código</option>
                            </select>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" style="font-weight:700" :disabled="!atual()" @mousedown.prevent @click="envolver('**', '**')" title="Negrito (Ctrl+B)">N</button>
                            <button type="button" class="btn-sec" style="font-style:italic" :disabled="!atual()" @mousedown.prevent @click="envolver('*', '*')" title="Itálico (Ctrl+I)">I</button>
                            <button type="button" class="btn-sec" style="text-decoration:line-through" :disabled="!atual()" @mousedown.prevent @click="envolver('~~', '~~')" title="Tachado (Ctrl+Shift+X)">S</button>
                            <button type="button" class="btn-sec" :disabled="!atual()" @mousedown.prevent @click="envolver('==', '==')" title="Marca-texto (Ctrl+Shift+H)"><mark style="background:rgba(250,204,21,.45);color:inherit;padding:0 .2em;border-radius:.2rem">A</mark></button>
                            <button type="button" class="btn-sec" :disabled="!atual()" @mousedown.prevent @click="envolver('`', '`')" title="Código em linha (Ctrl+E)">&lt;/&gt;</button>
                            <button type="button" class="btn-sec" :disabled="!atual()" @mousedown.prevent @click="link()" title="Link (Ctrl+K)">Link</button>
                            <span class="wf-es-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!titulos().length" @click="sumario = !sumario" :aria-expanded="sumario" title="Sumário pelos títulos">Sumário</button>
                            <select class="campo wf-link-sel" x-model="b.dados.tam" aria-label="Tamanho da letra">
                                <option value="p">Letra pequena</option>
                                <option value="m">Letra média</option>
                                <option value="g">Letra grande</option>
                            </select>
                            <span class="wf-es-sep"></span>
                            <label class="btn-sec" title="Importar um arquivo .md ou .txt (substitui o texto)">Importar
                                <input type="file" accept=".md,.markdown,.txt,text/markdown,text/plain" class="hidden"
                                    @change="importar($event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="baixar()" title="Baixar como Markdown (.md)">Exportar .md</button>
                            <button type="button" class="btn-sec" @click="copiar()" title="Copiar o texto em Markdown">Copiar</button>
                        </div>

                        <div class="wf-es-sum" x-show="sumario" x-cloak>
                            <template x-for="o in titulos()" :key="o.x.id">
                                <button type="button" :style="`padding-left:${(Number(o.x.t.charAt(1)) - 1) * .8 + .4}rem`"
                                        @click="irPara(o.x.id)" x-text="o.x.x || '(sem título)'"></button>
                            </template>
                        </div>

                        <div class="wf-es-folha" :style="{ fontSize: { p: '.8rem', m: '.95rem', g: '1.2rem' }[b.dados.tam] || '.95rem' }">
                            <template x-for="(bl, i) in blocos" :key="bl.id">
                                <div class="wf-es-bloco" :data-es="bl.id"
                                    :class="{ 'foco': foco === bl.id, 'sobre': sobre === i && arrastar !== bl.id, 'arrastando': arrastar === bl.id }"
                                    :draggable="arrastar === bl.id ? 'true' : 'false'"
                                    @dragstart="dragIni($event, bl)" @dragover.prevent="sobre = i" @drop.prevent="soltar(i)"
                                    @dragend="arrastar = null; sobre = -1">

                                    <div class="wf-es-lado">
                                        <button type="button" class="wf-es-lbtn" title="Inserir bloco abaixo" aria-label="Inserir bloco abaixo" @click="addAbaixo(i)">+</button>
                                        <button type="button" class="wf-es-lbtn wf-es-grip" title="Arraste para mover · clique para opções" aria-label="Opções do bloco"
                                                @mousedown="arrastar = bl.id" @mouseup="arrastar = null"
                                                @click="opcBl = opcBl === bl.id ? null : bl.id">⋮⋮</button>
                                    </div>

                                    <div class="wf-es-menu wf-es-menu-bl" x-show="opcBl === bl.id" x-cloak role="menu">
                                        <button type="button" class="menu-item" role="menuitem" :disabled="i === 0" @click="mover(i, -1); opcBl = null">Subir</button>
                                        <button type="button" class="menu-item" role="menuitem" :disabled="i === blocos.length - 1" @click="mover(i, 1); opcBl = null">Descer</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="duplicar(i); opcBl = null">Duplicar</button>
                                        <button type="button" class="menu-item" role="menuitem" @click="remover(i); opcBl = null">Excluir</button>
                                    </div>

                                    <div class="wf-es-corpo" :class="'t-' + bl.t + (bl.f ? ' feito' : '')" :style="`margin-left:${(bl.n || 0) * 1.5}rem`">
                                        <template x-if="bl.t === 'ul'"><span class="wf-es-marca" x-text="['•', '◦', '▪', '•'][bl.n || 0]"></span></template>
                                        <template x-if="bl.t === 'ol'"><span class="wf-es-marca" x-text="numero(i) + '.'"></span></template>
                                        <template x-if="bl.t === 'todo'"><input type="checkbox" class="wf-es-chk" :checked="bl.f" @change="bl.f = $event.target.checked" aria-label="Tarefa concluída"></template>
                                        <template x-if="bl.t === 'callout'"><span class="wf-es-marca">💡</span></template>

                                        <template x-if="bl.t === 'hr'"><hr class="wf-es-hr"></template>

                                        <template x-if="bl.t !== 'hr'">
                                            <div class="wf-es-txt">
                                                <template x-if="bl.t === 'code'">
                                                    <input class="wf-es-lang" :value="bl.l" placeholder="linguagem (opcional)" aria-label="Linguagem do código"
                                                        @input="bl.l = $event.target.value.replace(/[^\w+#.-]/g, '')">
                                                </template>
                                                <div class="wf-es-ver" x-show="bl.t !== 'code' && foco !== bl.id" @click="ver($event, bl)" x-html="html(bl)"></div>
                                                <textarea class="wf-es-ta" rows="1" x-show="bl.t === 'code' || foco === bl.id"
                                                        :spellcheck="bl.t !== 'code'" autocomplete="off" aria-label="Texto do bloco"
                                                        :placeholder="bl.t === 'p' ? 'Digite “/” para ver os comandos…' : ''"
                                                        :value="bl.x"
                                                        x-init="$nextTick(() => fit($el))"
                                                        @focus="foco = bl.id; fit($el)"
                                                        @input="entrada($event, bl)" @keydown="tecla($event, bl, i)" @paste="colar($event, bl, i)"></textarea>
                                            </div>
                                        </template>
                                    </div>

                                    <template x-if="menu && menu.id === bl.id">
                                        <div class="wf-es-menu" role="listbox" aria-label="Comandos">
                                            <template x-for="(it, k) in itensMenu()" :key="it.id">
                                                <button type="button" class="wf-es-op" role="option" :class="{ 'sel': menu && menu.i === k }"
                                                        @mousedown.prevent="aplicar(it.id, bl)" @mouseenter="menu.i = k">
                                                    <span class="wf-es-ic" x-text="it.ic"></span>
                                                    <span><b x-text="it.nome"></b><small x-text="it.desc"></small></span>
                                                </button>
                                            </template>
                                            <p class="wf-es-vazio" x-show="!itensMenu().length">Nada encontrado</p>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <div class="wf-es-fim" @click="aoFim()" aria-hidden="true"></div>
                        </div>

                        <div class="wf-es-rodape" aria-live="polite">
                            <span x-text="palavras() + ' palavra(s) · ' + caracteres() + ' caractere(s) · ~' + leitura() + ' min de leitura'"></span>
                            <span>Dica: “/” abre os comandos · Markdown funciona (# , - , [] , > ) · Alt+↑/↓ move o bloco</span>
                            <span x-show="dica" x-cloak style="color:var(--prim-forte)" x-text="dica"></span>
                        </div>
                    </div>
                </template>

                {{-- Tabela --}}
                <template x-if="b.tipo === 'tabela'">
                    <div class="wf-xl" data-xl x-init="tbGarantir(b)" @keydown="tbTeclaRaiz($event, b)">

                        <div class="wf-xl-barra" role="toolbar" aria-label="Ferramentas da tabela">
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).hist.length" @click="tbDesfazer(b)" title="Desfazer (Ctrl+Z)">↶</button>
                            <button type="button" class="btn-sec" :disabled="!tbUI(b).refaz.length" @click="tbRefazer(b)" title="Refazer (Ctrl+Y)">↷</button>
                            <span class="wf-xl-sep"></span>

                            <select class="campo" aria-label="Formato da coluna" title="Formato dos números da coluna" @change="tbFmt(b, $event.target.value)">
                                <template x-for="f in tbFormatos" :key="f.id">
                                    <option :value="f.id" :selected="tbCol(b, tbUI(b).c).fmt === f.id" x-text="f.nome"></option>
                                </template>
                            </select>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'e')" title="Alinhar coluna à esquerda">⇤</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'c')" title="Centralizar coluna">↔</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, 'd')" title="Alinhar coluna à direita">⇥</button>
                            <button type="button" class="btn-sec" @click="tbAlinhar(b, '')" title="Alinhamento automático (números à direita)">Auto</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" style="font-weight:700" @click="tbNegrito(b)" title="Negrito (Ctrl+B)">N</button>
                            <input type="color" class="wf-cor !w-6 !h-6" value="#fde68a" @change="tbFundo(b, $event.target.value)" aria-label="Cor de fundo da seleção" title="Cor de fundo da seleção">
                            <button type="button" class="btn-sec" @click="tbFundo(b, '')" title="Remover cor de fundo">Sem cor</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbOrdenar(b, 1)" title="Ordenar a coluna ativa de A a Z">A→Z</button>
                            <button type="button" class="btn-sec" @click="tbOrdenar(b, -1)" title="Ordenar a coluna ativa de Z a A">Z→A</button>
                            <button type="button" class="btn-sec" @click="tbAutoSoma(b)" title="Soma automática dos números acima (ou à esquerda)">Σ</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'baixo')" title="Preencher para baixo (Ctrl+D)">Preencher ↓</button>
                            <button type="button" class="btn-sec" @click="tbPreencher(b, 'direita')" title="Preencher para a direita (Ctrl+R)">Preencher →</button>
                            <button type="button" class="btn-sec" @click="tbLimpar(b)" title="Limpar o conteúdo da seleção (Delete)">Limpar</button>
                            <span class="wf-xl-sep"></span>

                            <button type="button" class="btn-sec" @click="tbLinhaIns(b, tbIntervalo(b).i2 + 1)" title="Inserir linha abaixo da seleção">+ Linha aqui</button>
                            <button type="button" class="btn-sec" @click="tbColIns(b, tbIntervalo(b).j2 + 1)" title="Inserir coluna à direita da seleção">+ Coluna aqui</button>
                            <select class="campo" aria-label="Rodapé de totais da coluna" title="Total no rodapé da coluna" @change="tbRodapeSet(b, $event.target.value)">
                                <template x-for="a in tbAgregados" :key="a.id">
                                    <option :value="a.id" :selected="((b.dados.rodape || [])[tbUI(b).c] || '') === a.id" x-text="a.nome"></option>
                                </template>
                            </select>
                            <input class="campo" type="search" style="width:8rem" placeholder="Filtrar linhas…" aria-label="Filtrar linhas" x-model="tbUI(b).filtro">
                            <span class="wf-xl-sep"></span>

                            <label class="btn-sec" title="Importar um arquivo CSV/TSV (substitui a tabela)">Importar CSV
                                <input type="file" accept=".csv,.tsv,.txt,text/csv" class="hidden" @change="tbImportar(b, $event.target.files[0]); $event.target.value = ''">
                            </label>
                            <button type="button" class="btn-sec" @click="tbExportar(b)" title="Baixar como CSV (abre no Excel)">Exportar CSV</button>
                        </div>

                        {{-- Barra de fórmulas --}}
                        <div class="wf-xl-fx">
                            <span class="wf-xl-ref" x-text="tbRef(b)" title="Célula ou intervalo selecionado"></span>
                            <span class="texto-2 text-xs">fx</span>
                            <input class="campo" data-barra spellcheck="false" autocomplete="off"
                                   placeholder="Valor ou fórmula (ex.: =SOMA(B1:B5) ou =A1*B1)" aria-label="Conteúdo da célula ativa"
                                   :value="tbRaw(b)" @focus="tbUI(b).sujo = false" @input="tbBarra(b, $event.target.value)"
                                   @keydown.enter.prevent="tbFocarAtivo($event, b)">
                        </div>

                        {{-- Grade --}}
                        <div class="wf-xl-rolagem" @copy="tbCopiar($event, b, false)" @cut="tbCopiar($event, b, true)" @paste="tbColar($event, b)">
                            <table class="wf-tab wf-xl-tab" :style="tbLarg(b)">
                                <colgroup>
                                    <col style="width:44px">
                                    <template x-for="(c, j) in b.dados.cab" :key="j"><col :style="'width:' + tbColW(b, j) + 'px'"></template>
                                    <col style="width:32px">
                                </colgroup>
                                <thead>
                                    <tr class="letras">
                                        <th class="canto" @click="tbSelTudo(b)" title="Selecionar tudo"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th class="letra" :class="{ 'sel': tbColAtiva(b, j) }" @click="tbSelCol(b, j, $event)" title="Selecionar coluna (Shift para ampliar)">
                                                <span x-text="tbLetra(j)"></span>
                                                <i class="wf-xl-grip" title="Arraste para mudar a largura (duplo clique restaura)"
                                                   @pointerdown.stop.prevent="tbRedim($event, b, j)" @click.stop @dblclick.stop="tbAutoLarg(b, j)"></i>
                                            </th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                    <tr class="nomes">
                                        <th class="canto"></th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j">
                                            <th><div class="flex items-center">
                                                <input class="wf-cel font-semibold" x-model="b.dados.cab[j]" aria-label="Nome da coluna">
                                                <button type="button" class="mini-btn perigo" x-show="b.dados.cab.length > 1" title="Remover coluna" @click="tbColRem(b, j)">✕</button>
                                            </div></th>
                                        </template>
                                        <th style="border:0"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="i in tbVisiveis(b)" :key="i">
                                        <tr>
                                            <th class="num" :class="{ 'sel': tbLinhaAtiva(b, i) }" @click="tbSelLinha(b, i, $event)" x-text="i + 1" title="Selecionar linha (Shift para ampliar)"></th>
                                            <template x-for="(c, j) in b.dados.cab" :key="j">
                                                <td :class="{ 'sel': tbSelecionada(b, i, j), 'ativa': tbAtiva(b, i, j) }" :style="tbTdEstilo(b, i, j)">
                                                    <input class="wf-cel" spellcheck="false" autocomplete="off"
                                                           :data-r="i" :data-c="j" :style="tbInEstilo(b, i, j)" :aria-label="'Célula ' + tbLetra(j) + (i + 1)"
                                                           :value="tbValor(b, i, j)"
                                                           @focus="tbFoco(b, i, j)" @blur="tbBlur(b, i, j)" @input="tbInput(b, i, j, $event)"
                                                           @keydown="tbTecla($event, b, i, j)" @mousedown="tbDown($event, b, i, j)">
                                                </td>
                                            </template>
                                            <td style="border:0;text-align:center"><button type="button" class="mini-btn perigo" title="Remover linha" @click="tbLinhaRem(b, i)">✕</button></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot x-show="tbTemRodape(b)">
                                    <tr>
                                        <th class="num" title="Totais (consideram as linhas visíveis)">Σ</th>
                                        <template x-for="(c, j) in b.dados.cab" :key="j"><td x-text="tbRodape(b, j)"></td></template>
                                        <td style="border:0"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <button type="button" class="link-prim text-sm" @click="tbLinhaAdd(b)">+ Linha</button>
                            <button type="button" class="link-prim text-sm" @click="tbColAdd(b)">+ Coluna</button>
                            <div class="wf-xl-status" aria-live="polite">
                                <span x-text="b.dados.linhas.length + ' linha(s) × ' + b.dados.cab.length + ' coluna(s)' + (tbUI(b).filtro ? ' · ' + tbVisiveis(b).length + ' visível(is)' : '')"></span>
                                <span x-show="tbStats(b)" x-text="tbStats(b)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Lista --}}
                <template x-if="b.tipo === 'lista'">
                    <div class="space-y-2">
                        <input class="campo" placeholder="Novo item e Enter" @keydown.enter.prevent="addItem(b, $el)">
                        <ul class="space-y-1.5">
                            <template x-for="i in b.dados.itens" :key="i.id">
                                <li class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" x-model="i.f">
                                    <input class="wf-cel flex-1" :class="{ 'line-through texto-2': i.f }" x-model="i.t">
                                    <button type="button" class="mini-btn perigo" title="Remover" @click="remItem(b, i.id)">✕</button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                {{-- Código: repositórios (GitHub / VS Code) e editor de código --}}
                <template x-if="b.tipo === 'codigo'">
                    <div class="h-full flex flex-col gap-3">
                        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Modo do bloco de código">
                            <button type="button" class="btn-sec !py-1" role="tab"
                                    :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': (b.dados.modo || 'repos') === 'repos' }"
                                    :aria-selected="(b.dados.modo || 'repos') === 'repos'"
                                    @click="b.dados.modo = 'repos'">Repositórios</button>
                            <button type="button" class="btn-sec !py-1" role="tab"
                                    :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': b.dados.modo === 'ide' }"
                                    :aria-selected="b.dados.modo === 'ide'"
                                    @click="b.dados.modo = 'ide'; WFIde.ampliar(estado.layout[b.secao]?.[b.id])">Editor</button>
                        </div>

                        {{-- Repositórios --}}
                        <template x-if="(b.dados.modo || 'repos') === 'repos'">
                            <div class="space-y-3 flex-1 min-h-0 overflow-auto">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input class="campo flex-1" style="min-width:12rem"
                                           placeholder="Cole a URL do GitHub ou dono/repositório e Enter"
                                           aria-label="Adicionar repositório"
                                           @keydown.enter.prevent="if ($el.value.trim()) { b.dados.repos.push({ id: uid(), nome: $el.value.trim(), pasta: '', ramo: '', nota: '' }); $el.value = ''; }">
                                    <button type="button" class="btn-sec"
                                            @click="b.dados.repos.push({ id: uid(), nome: '', pasta: '', ramo: '', nota: '' })">+ Repositório</button>
                                </div>
                                <p class="text-xs texto-2" x-show="b.dados.repos.length"
                                   x-text="b.dados.repos.length + ' repositório(s) · ' + b.dados.repos.filter(r => (r.pasta || '').trim()).length + ' com pasta local'"></p>
                                <p class="text-sm texto-2" x-show="!b.dados.repos.length">Nenhum repositório ainda. Cole uma URL do GitHub acima para começar.</p>

                                <template x-for="r in b.dados.repos" :key="r.id">
                                    <div class="wf-repo" :class="{ 'invalido': (r.nome || '').trim() && !WFRepo.ok(r.nome) }">
                                        <div class="wf-repo-linha">
                                            <input class="wf-cel" x-model="r.nome" placeholder="dono/repositório ou URL do GitHub" aria-label="Repositório">
                                            <input class="wf-cel wf-repo-ramo" x-model="r.ramo" placeholder="ramo (opcional)" aria-label="Ramo (branch)">
                                            <span class="wf-repo-selo" x-show="WFRepo.ok(r.nome)" x-text="WFRepo.slug(r)"></span>
                                            <button type="button" class="mini-btn perigo" title="Remover repositório" aria-label="Remover repositório"
                                                    @click="b.dados.repos.splice(b.dados.repos.indexOf(r), 1)">✕</button>
                                        </div>
                                        <p class="wf-repo-aviso" x-show="(r.nome || '').trim() && !WFRepo.ok(r.nome)">
                                            Não reconheci esse repositório. Use dono/repositório ou a URL completa do GitHub.
                                        </p>
                                        <div class="wf-repo-linha">
                                            <input class="wf-cel" x-model="r.pasta" placeholder="Pasta local (ex.: C:/projetos/app)" aria-label="Pasta local">
                                            <button type="button" class="mini-btn" x-show="(r.pasta || '').trim()" title="Copiar caminho da pasta" aria-label="Copiar caminho da pasta"
                                                    @click="WFRepo.copiar(r.pasta).then(ok => aviso(ok ? 'Caminho copiado.' : 'Não consegui copiar.'))">⧉</button>
                                        </div>
                                        <input class="wf-cel" style="border:1px solid var(--linha);background:var(--superficie)" x-model="r.nota" placeholder="Nota (opcional): para que serve, o que falta fazer…" aria-label="Nota">

                                        <div class="wf-repo-acoes" x-show="WFRepo.ok(r.nome) || (r.pasta || '').trim()">
                                            <template x-if="WFRepo.ok(r.nome)">
                                                <span class="contents">
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'repo')" target="_blank" rel="noopener noreferrer">GitHub</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'issues')" target="_blank" rel="noopener noreferrer">Issues</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'pulls')" target="_blank" rel="noopener noreferrer">PRs</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'actions')" target="_blank" rel="noopener noreferrer">Actions</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'web')" target="_blank" rel="noopener noreferrer">VS Code web</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'dev')" target="_blank" rel="noopener noreferrer">github.dev</a>
                                                    <a class="btn-sec" :href="WFRepo.url(r, 'clone')" title="Abre o VS Code instalado e clona o repositório">Clonar no VS Code</a>
                                                    <button type="button" class="btn-sec" title="Copiar o comando git clone"
                                                            @click="WFRepo.copiar(WFRepo.cmd(r)).then(ok => aviso(ok ? 'Comando git clone copiado.' : 'Não consegui copiar.'))">Copiar git clone</button>
                                                </span>
                                            </template>
                                            <a class="btn-sec" x-show="(r.pasta || '').trim()" :href="WFRepo.local(r)" title="Abre a pasta no VS Code instalado">VS Code local</a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Editor de código (lógica em public/js/ide.js) --}}
                        <template x-if="b.dados.modo === 'ide'">
                            <div class="wf-ide" x-data="WFIde(b)" x-init="iniciar()">

                                <div class="wf-ide-barra" role="toolbar" aria-label="Ferramentas do editor">
                                    <button type="button" class="btn-sec" @click="novoArquivo()" title="Novo arquivo">+ Arquivo</button>
                                    <button type="button" class="btn-sec" @click="novaPasta()" title="Nova pasta">+ Pasta</button>
                                    <label class="btn-sec" title="Enviar arquivos de texto do computador">Enviar
                                        <input type="file" multiple class="hidden" @change="enviar($event.target.files); $event.target.value = ''">
                                    </label>
                                    <span class="sep"></span>
                                    <button type="button" class="btn-sec" @click="salvar()" title="Salvar (Ctrl+S)">Salvar</button>
                                    <button type="button" class="btn-sec" @click="baixar()" x-show="arqAtivo()" title="Baixar o arquivo aberto">Baixar arquivo</button>
                                    <button type="button" class="btn-sec" @click="baixarZip()" title="Baixar o projeto inteiro em ZIP">Baixar ZIP</button>
                                    <span class="sep" x-show="suporteDisco"></span>
                                    <button type="button" class="btn-sec" x-show="suporteDisco" @click="abrirPasta()" title="Abrir uma pasta do computador (Chrome/Edge)">Abrir pasta</button>
                                    <button type="button" class="btn-sec" x-show="disco" x-cloak @click="salvarDisco()" :title="'Gravar tudo em ' + disco">Salvar na pasta</button>
                                    <span class="sep"></span>
                                    <button type="button" class="btn-sec" x-show="podeExecutar()" @click="executar()" title="Executar .js ou abrir a prévia do .html (Ctrl+Enter)">▶ Executar</button>
                                    <button type="button" class="btn-sec" @click="abrirBusca()" title="Buscar e substituir (Ctrl+F)">Buscar</button>
                                </div>

                                <div class="wf-ide-busca" x-show="busca.on" x-cloak role="search">
                                    <input x-ref="q" class="campo" placeholder="Buscar" aria-label="Buscar" x-model="busca.q"
                                           @keydown.enter.prevent="proxima($event.shiftKey ? -1 : 1)" @keydown.escape.prevent="busca.on = false">
                                    <input class="campo" placeholder="Substituir por" aria-label="Substituir por" x-model="busca.r"
                                           @keydown.enter.prevent="substituirUm()" @keydown.escape.prevent="busca.on = false">
                                    <span class="texto-2" x-text="contar() + ' no arquivo'"></span>
                                    <button type="button" class="btn-sec" @click="proxima(-1)" title="Anterior">↑</button>
                                    <button type="button" class="btn-sec" @click="proxima(1)" title="Próxima">↓</button>
                                    <button type="button" class="btn-sec" @click="substituirUm()">Substituir</button>
                                    <button type="button" class="btn-sec" @click="substituirTodos()">Substituir todas</button>
                                    <label class="flex items-center gap-1 texto-2"><input type="checkbox" x-model="busca.todos"> Em todos os arquivos</label>
                                    <button type="button" class="mini-btn" @click="busca.on = false" aria-label="Fechar busca">✕</button>
                                </div>

                                <div class="wf-ide-corpo">
                                    <aside class="wf-ide-lado" aria-label="Arquivos do projeto" @click.self="sel = ''">
                                        <template x-for="n in arvore()" :key="n.k + ':' + n.caminho">
                                            <div class="wf-ide-no" :class="{ 'ativo': n.id && n.id === b.dados.ide.ativo, 'sel': sel === n.caminho }"
                                                 :style="`padding-left:${.4 + n.nivel * .9}rem`" @click="clicar(n)" :title="n.caminho">
                                                <span class="seta" x-text="n.k === 'p' ? (n.fechada ? '▸' : '▾') : ''"></span>
                                                <span class="nome" x-text="(n.k === 'p' ? '' : '') + n.nome"></span>
                                                <span class="acoes">
                                                    <button type="button" class="mini-btn" title="Renomear / mover" aria-label="Renomear" @click.stop="renomear(n)">✎</button>
                                                    <button type="button" class="mini-btn perigo" title="Excluir" aria-label="Excluir" @click.stop="excluir(n)">✕</button>
                                                </span>
                                            </div>
                                        </template>
                                        <p class="px-2 py-1 text-xs texto-2" x-show="!arvore().length">Projeto vazio. Use “+ Arquivo”.</p>

                                        <template x-for="(r, i) in resultados()" :key="i">
                                            <div class="wf-ide-res" @click="irLinha(r)">
                                                <b x-text="r.caminho + ':' + r.linha"></b>
                                                <div class="texto-2" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" x-text="r.trecho"></div>
                                            </div>
                                        </template>
                                    </aside>

                                    <section class="wf-ide-main">
                                        <div class="wf-ide-abas" role="tablist" aria-label="Arquivos abertos">
                                            <template x-for="f in abas()" :key="f.id">
                                                <div class="wf-ide-aba" role="tab" :class="{ 'ativo': f.id === b.dados.ide.ativo }" :aria-selected="f.id === b.dados.ide.ativo"
                                                     @click="abrir(f.id)" :title="f.caminho">
                                                    <span x-text="nomeArq(f)"></span>
                                                    <button type="button" class="x" :aria-label="'Fechar ' + nomeArq(f)" @click.stop="fechar(f.id)">✕</button>
                                                </div>
                                            </template>
                                        </div>

                                        <div class="wf-ide-ed" x-show="arqAtivo()" x-cloak>
                                            <div class="wf-ide-num" x-ref="num" aria-hidden="true" x-text="numeros()"></div>
                                            <div class="wf-ide-cod">
                                                <pre class="wf-ide-pre" x-ref="pre" aria-hidden="true" x-html="realce()"></pre>
                                                <textarea class="wf-ide-ta" x-ref="ta" spellcheck="false" wrap="off" autocomplete="off" autocapitalize="off" autocorrect="off"
                                                          aria-label="Código do arquivo aberto"
                                                          :value="arqAtivo() ? arqAtivo().texto : ''"
                                                          @input="editar($event)" @scroll="sincronizar()" @keydown="teclas($event)"
                                                          @keyup="cursor()" @click="cursor()"></textarea>
                                            </div>
                                        </div>
                                        <div class="wf-ide-vazio" x-show="!arqAtivo()" x-cloak>
                                            Abra um arquivo na lista ao lado ou crie um novo com “+ Arquivo”.
                                        </div>

                                        <div class="wf-ide-painel" x-show="painel" x-cloak>
                                            <div class="wf-ide-painel-topo">
                                                <span class="t" :class="{ 'ativo': painel === 'saida' }" @click="painel = 'saida'">Console</span>
                                                <span class="t" :class="{ 'ativo': painel === 'previa' }" @click="painel = 'previa'">Prévia</span>
                                                <span style="flex:1"></span>
                                                <button type="button" class="link-prim" @click="limparSaida()">Limpar</button>
                                                <button type="button" class="mini-btn" @click="fecharPainel()" aria-label="Fechar painel">✕</button>
                                            </div>
                                            <div class="wf-ide-saida" x-show="painel === 'saida'">
                                                <p class="texto-2" x-show="!saida.length">Sem saída. Use console.log() no seu código.</p>
                                                <template x-for="(l, i) in saida" :key="i">
                                                    <div class="wf-ide-log" :class="'l-' + l.t" x-text="l.m"></div>
                                                </template>
                                            </div>
                                            <iframe class="wf-ide-frame" x-ref="frame" x-show="painel === 'previa'" title="Prévia do projeto"
                                                    sandbox="allow-scripts allow-forms allow-modals allow-popups" :srcdoc="srcdoc"></iframe>
                                        </div>
                                    </section>
                                </div>

                                <div class="wf-ide-status" aria-live="polite">
                                    <span x-text="arqAtivo() ? arqAtivo().caminho : 'Nenhum arquivo aberto'"></span>
                                    <span x-show="arqAtivo()" x-text="extensao() + ' · Ln ' + ln + ', Col ' + col + ' · ' + totalLinhas() + ' linhas'"></span>
                                    <span x-show="disco" x-cloak x-text="'Pasta: ' + disco"></span>
                                    <span x-show="msg" x-cloak style="color:var(--prim-forte)" x-text="msg"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Imagem: só a imagem inteira, sem controles. O cartão se ajusta ao tamanho dela.
                     Duplo clique troca a imagem; sem imagem, aparece o botão para escolher. --}}
                <template x-if="b.tipo === 'imagem'">
                    <div data-img class="w-full">
                        <template x-if="imagens[b.id]">
                            <img :src="imagens[b.id]" :alt="b.titulo"
                                 class="block w-full h-auto rounded-lg cursor-pointer"
                                 title="Duplo clique para trocar a imagem"
                                 @load="ajustarImagem(b, $el)"
                                 @dblclick="$el.closest('[data-img]').querySelector('input[type=file]').click()">
                        </template>
                        <button type="button" class="btn-sec" x-show="!imagens[b.id]"
                                @click="$el.closest('[data-img]').querySelector('input[type=file]').click()">Escolher imagem</button>
                        <input type="file" accept="image/*" class="hidden"
                               @change="lerImagem(b, $event.target.files[0]); $event.target.value = ''">
                    </div>
                </template>

{{-- Cartão ativo (de outra página) --}}
            @include('areas.partials.bloco-vivo')

            {{-- Mapa mental e diagramas (BPMN, fluxogramas, ER/UML). Lógica em public/js/diagrama.js (métodos dd*). --}}
                <template x-if="b.tipo === 'mapa'">
                    <div class="wf-dd h-full" data-dd x-init="dd(b)">

                        {{-- Barra principal --}}
                        <div class="wf-dd-barra" role="toolbar" aria-label="Ferramentas do diagrama">
                            <div class="relative" @click.outside="dd(b).menu = false" @keydown.escape.window="dd(b).menu = false">
                                <button type="button" class="btn-sec" @click="dd(b).menu = !dd(b).menu" aria-haspopup="menu" :aria-expanded="dd(b).menu">+ Elemento</button>
                                <div class="wf-dd-menu" x-show="dd(b).menu" x-cloak role="menu">
                                    <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                        <div>
                                            <p class="wf-dd-menu-grupo" x-text="g.grupo"></p>
                                            <template x-for="it in g.itens" :key="it.id">
                                                <button type="button" class="menu-item" role="menuitem" @click="ddAdicionar(b, it.id, $event)" x-text="it.nome"></button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" :disabled="!dd(b).hist.length" @click="ddDesfazer(b)" title="Desfazer (Ctrl+Z)">↶ Desfazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).refaz.length" @click="ddRefazer(b)" title="Refazer (Ctrl+Shift+Z)">↷ Refazer</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length" @click="ddDuplicar(b)" title="Duplicar (Ctrl+D)">Duplicar</button>
                            <button type="button" class="btn-sec" :disabled="!dd(b).sel.length && !dd(b).selLig" @click="ddRemoverSel(b)" title="Excluir (Delete)">Excluir</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'frente')" title="Trazer para a frente">Frente</button>
                            <button type="button" class="btn-sec" x-show="dd(b).sel.length" @click="ddOrdem(b, 'tras')" title="Enviar para trás">Trás</button>

                            <template x-if="ddSelNos(b).length > 1">
                                <span class="flex flex-wrap items-center gap-1">
                                    <span class="wf-dd-sep"></span>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'esq')" title="Alinhar à esquerda">⇤</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cx')" title="Centralizar na horizontal">↔</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dir')" title="Alinhar à direita">⇥</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'topo')" title="Alinhar ao topo">⤒</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'cy')" title="Centralizar na vertical">↕</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'base')" title="Alinhar à base">⤓</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dh')" title="Distribuir na horizontal (3 ou mais)">⇹</button>
                                    <button type="button" class="btn-sec" @click="ddAlinhar(b, 'dv')" title="Distribuir na vertical (3 ou mais)">⇳</button>
                                </span>
                            </template>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1 / 1.2)" title="Diminuir o zoom" aria-label="Diminuir o zoom">−</button>
                            <button type="button" class="btn-sec" style="min-width:3.2rem" @click="ddZoomSet(b, 1)" title="Voltar a 100%" x-text="Math.round(dd(b).z * 100) + '%'"></button>
                            <button type="button" class="btn-sec" @click="ddZoom(b, 1.2)" title="Aumentar o zoom" aria-label="Aumentar o zoom">+</button>
                            <button type="button" class="btn-sec" @click="ddAjustar(b, $event)" title="Ajustar o diagrama à janela">Ajustar</button>

                            <span class="wf-dd-sep"></span>
                            <label class="wf-dd-chk" title="Mostrar a grade de pontos"><input type="checkbox" :checked="dd(b).grade" @change="dd(b).grade = $event.target.checked"> Grade</label>
                            <label class="wf-dd-chk" title="Encaixar elementos na grade"><input type="checkbox" :checked="dd(b).snap" @change="dd(b).snap = $event.target.checked"> Ímã</label>
                            <select class="campo" aria-label="Estilo padrão dos novos conectores" title="Estilo dos novos conectores"
                                    @change="b.dados.ligPadrao = $event.target.value">
                                <template x-for="s in ddEstilos" :key="s.id">
                                    <option :value="s.id" :selected="(b.dados.ligPadrao || 'ortogonal') === s.id" x-text="'Conector: ' + s.nome"></option>
                                </template>
                            </select>

                            <span class="wf-dd-sep"></span>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'png')" title="Baixar como imagem PNG">PNG</button>
                            <button type="button" class="btn-sec" @click="ddExportar(b, 'svg')" title="Baixar como SVG">SVG</button>
                        </div>

                        {{-- Desenho livre (continua como no mapa mental) --}}
                        <div class="wf-dd-barra">
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && !ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && !ui[b.id]?.borr)" @click="penModo(b, 'lapis')">✏️ Desenhar</button>
                            <button type="button" class="btn-sec !py-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': ui[b.id]?.on && ui[b.id]?.borr }" :aria-pressed="!!(ui[b.id]?.on && ui[b.id]?.borr)" @click="penModo(b, 'borracha')">Borracha</button>
                            <template x-if="ui[b.id]?.on">
                                <span class="flex items-center gap-2">
                                    <input type="color" class="wf-cor !w-6 !h-6" x-model="ui[b.id].cor" aria-label="Cor do traço" title="Cor do traço">
                                    <input type="range" min="1" max="16" x-model.number="ui[b.id].larg" aria-label="Espessura do traço" title="Espessura" style="width:6rem">
                                </span>
                            </template>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penDesfazer(b)">Desfazer traço</button>
                            <button type="button" class="link-prim text-sm" x-show="(b.dados.tracos || []).length" @click="penLimpar(b)">Limpar desenho</button>
                        </div>

                        {{-- Propriedades do elemento selecionado --}}
                        <template x-for="n0 in (ddNoSel(b) ? [ddNoSel(b)] : [])" :key="n0.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Forma
                                    <select class="campo" @change="ddMudarForma(b, n0, $event.target.value)">
                                        <template x-for="g in ddFormasGrupos" :key="g.grupo">
                                            <optgroup :label="g.grupo">
                                                <template x-for="it in g.itens" :key="it.id">
                                                    <option :value="it.id" :selected="(n0.forma || 'mapa') === it.id" x-text="it.nome"></option>
                                                </template>
                                            </optgroup>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Texto
                                    <input class="campo" style="width:9rem" :value="n0.t" @focus="ddGravar(b)" @input="n0.t = $event.target.value" aria-label="Texto do elemento">
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="n0.cor || '#a78bfa'" @input="ddCor(b, $event.target.value)" aria-label="Cor do elemento">
                                </label>
                                <template x-if="!ddGeo(n0).d.fixo">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <label class="wf-dd-campo">Largura
                                            <input type="number" class="campo" style="width:4.5rem" min="40" max="1600" :value="ddGeo(n0).w" @change="ddTam(b, 'w', $event.target.value)">
                                        </label>
                                        <label class="wf-dd-campo" x-show="n0.forma !== 'entidade'">Altura
                                            <input type="number" class="campo" style="width:4.5rem" min="24" max="1600" :value="ddGeo(n0).h" @change="ddTam(b, 'h', $event.target.value)">
                                        </label>
                                    </span>
                                </template>
                                <button type="button" class="btn-sec" @click="ddFilho(b, n0)" title="Criar elemento ligado a este (Tab)">+ Filho</button>
                                <button type="button" class="btn-sec" x-show="n0.pai" @click="ddDesligarPai(b, n0)" title="Soltar este tópico do pai">Desligar do pai</button>
                                <template x-if="n0.forma === 'entidade'">
                                    <label class="wf-dd-campo" style="flex-basis:100%;align-items:flex-start">Campos
                                        <textarea class="campo" rows="3" style="flex:1;min-width:12rem;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.75rem"
                                                  placeholder="PK id&#10;FK cliente_id&#10;nome"
                                                  :value="n0.campos || ''" @focus="ddGravar(b)" @input="n0.campos = $event.target.value" aria-label="Campos da entidade (um por linha; PK, FK ou UK no início)"></textarea>
                                    </label>
                                </template>
                            </div>
                        </template>

                        {{-- Propriedades do conector selecionado --}}
                        <template x-for="l in (ddLigSel(b) ? [ddLigSel(b)] : [])" :key="l.id">
                            <div class="wf-dd-painel">
                                <label class="wf-dd-campo">Tipo
                                    <select class="campo" @change="ddPreset(b, l, $event.target.value)">
                                        <option value="" :selected="ddPresetAtual(l) === ''">Personalizado</option>
                                        <template x-for="p in ddPresets" :key="p.id">
                                            <option :value="p.id" :selected="ddPresetAtual(l) === p.id" x-text="p.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Rótulo
                                    <input class="campo" data-rot style="width:8rem" :value="l.rot || ''" @input="l.rot = $event.target.value" placeholder="ex.: sim, 1:N" aria-label="Rótulo do conector">
                                </label>
                                <label class="wf-dd-campo">Caminho
                                    <select class="campo" @change="l.estilo = $event.target.value">
                                        <template x-for="s in ddEstilos" :key="s.id">
                                            <option :value="s.id" :selected="(l.estilo || 'ortogonal') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Traço
                                    <select class="campo" @change="l.traco = $event.target.value">
                                        <template x-for="s in ddTracos" :key="s.id">
                                            <option :value="s.id" :selected="(l.traco || 'solido') === s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Início
                                    <select class="campo" @change="l.ini = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.ini == null ? 'nenhum' : l.ini) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Fim
                                    <select class="campo" @change="l.fim = $event.target.value">
                                        <template x-for="m in ddMarcas" :key="m.id">
                                            <option :value="m.id" :selected="(l.fim == null ? 'seta' : l.fim) === m.id" x-text="m.nome"></option>
                                        </template>
                                    </select>
                                </label>
                                <label class="wf-dd-campo">Cor
                                    <input type="color" class="wf-cor !w-6 !h-6" :value="/^#[0-9a-fA-F]{6}$/.test(l.cor || '') ? l.cor : '#94a3b8'" @input="l.cor = $event.target.value" aria-label="Cor do conector">
                                </label>
                                <label class="wf-dd-campo">Espessura
                                    <input type="range" min="1" max="6" step="0.5" style="width:5rem" :value="l.w || 1.8" @input="l.w = Number($event.target.value)" aria-label="Espessura do conector">
                                </label>
                                <button type="button" class="btn-sec" @click="ddInverter(b, l)" title="Trocar origem e destino">⇄ Inverter</button>
                                <button type="button" class="btn-sec" @click="ddRemoverLig(b)">Excluir conector</button>
                            </div>
                        </template>

                        <p class="wf-dd-dica">Duplo clique no vazio cria um elemento · arraste a bolinha de um elemento selecionado até outro (ou até o vazio) para ligar · Tab cria um filho · Enter edita o texto · Ctrl+Z desfaz · Ctrl+roda do mouse dá zoom</p>

                        {{-- Tela --}}
                        <div class="wf-mapa-col">
                            <div :style="`width:${mmTam(b).w * dd(b).z}px;height:${mmTam(b).h * dd(b).z}px;`">
                                <div class="wf-dd-lona" :class="{ 'grade': dd(b).grade }" tabindex="0" role="application" aria-label="Área do diagrama"
                                     :style="`width:${mmTam(b).w}px;height:${mmTam(b).h}px;transform:scale(${dd(b).z});transform-origin:0 0;`"
                                     @pointerdown="ddLonaDown($event, b)" @dblclick="ddLonaDbl($event, b)"
                                     @keydown="ddTecla($event, b)" @wheel="ddWheel($event, b)">

                                    {{-- Ligações (pai→filho dos mapas e conectores) --}}
                                    <svg class="wf-dd-camada" style="z-index:3;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h"
                                         aria-label="Conectores" x-html="ddLigSvg(b)"
                                         @pointerdown="ddLigDown($event, b)" @dblclick="ddLigDbl($event, b)"></svg>

                                    {{-- Desenho livre --}}
                                    <svg class="wf-dd-camada" aria-label="Desenho livre" :width="mmTam(b).w" :height="mmTam(b).h"
                                         :style="{ zIndex: ui[b.id]?.on ? 8 : 2, pointerEvents: ui[b.id]?.on ? 'auto' : 'none', cursor: ui[b.id]?.borr ? 'cell' : 'crosshair', touchAction: ui[b.id]?.on ? 'none' : 'auto' }"
                                         @pointerdown="penDown($event, b)" @pointermove="penHover($event, b)" @pointerleave="penFora(b)">
                                        <g x-html="tracosSvg(b)"></g>
                                        <path :d="ui[b.id]?.vivo || ''" fill="none" :stroke="ui[b.id]?.cor" :stroke-width="ui[b.id]?.larg" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle x-show="ui[b.id]?.on && ui[b.id]?.borr && ui[b.id]?.cx != null" :cx="ui[b.id]?.cx ?? 0" :cy="ui[b.id]?.cy ?? 0" :r="penRaio(b)" fill="none" stroke="var(--tinta-2)" stroke-width="1.2" stroke-dasharray="3 3" style="pointer-events:none"/>
                                    </svg>

                                    {{-- Linha provisória enquanto se arrasta uma ligação --}}
                                    <svg class="wf-dd-camada" style="z-index:5;overflow:visible" :width="mmTam(b).w" :height="mmTam(b).h" aria-hidden="true" x-show="dd(b).liga">
                                        <path :d="ddLiveD(b)" fill="none" stroke="var(--prim)" stroke-width="2" stroke-dasharray="6 4" stroke-linecap="round"/>
                                    </svg>

                                    {{-- Elementos --}}
                                    <template x-for="n in ddNos(b)" :key="n.id">
                                        <div class="wf-dd-no" :class="ddClasse(b, n)" :style="ddNoEstilo(n)"
                                             @pointerdown="ddNoDown($event, b, n)" @dblclick.stop="ddEditar(b, n)">
                                            <svg class="wf-dd-forma" :width="ddGeo(n).w" :height="ddGeo(n).h" aria-hidden="true" x-html="ddFormaSvg(n)"></svg>

                                            <template x-if="n.forma === 'entidade'">
                                                <div class="wf-dd-ent">
                                                    <div class="wf-dd-ent-t" x-text="n.t"></div>
                                                    <div class="wf-dd-ent-corpo">
                                                        <template x-for="(c, i) in ddCampos(n)" :key="i">
                                                            <div class="wf-dd-ent-c"><b x-show="c.tag" x-text="c.tag"></b><span x-text="c.txt"></span></div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="wf-dd-txt" x-show="n.forma !== 'entidade' && dd(b).edit !== n.id"
                                                 :class="{ 'fora': ddGeo(n).d.fora, 'raia': n.forma === 'raia' }" x-text="n.t"></div>

                                            <template x-if="dd(b).edit === n.id">
                                                <textarea class="wf-dd-edit" :class="{ 'fora': ddGeo(n).d.fora, 'ent': n.forma === 'entidade' }"
                                                          x-model="n.t" aria-label="Texto do elemento"
                                                          x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                                          @focus="ddGravar(b)" @pointerdown.stop @dblclick.stop
                                                          @keydown="ddTeclaEdit($event, b)" @blur="ddFimEdit(b, n)"></textarea>
                                            </template>

                                            <button type="button" class="wf-dd-h t" tabindex="-1" aria-label="Ligar a partir do topo" @pointerdown="ddLigarDown($event, b, n, 't')"></button>
                                            <button type="button" class="wf-dd-h b" tabindex="-1" aria-label="Ligar a partir de baixo" @pointerdown="ddLigarDown($event, b, n, 'b')"></button>
                                            <button type="button" class="wf-dd-h e" tabindex="-1" aria-label="Ligar a partir da esquerda" @pointerdown="ddLigarDown($event, b, n, 'e')"></button>
                                            <button type="button" class="wf-dd-h d" tabindex="-1" aria-label="Ligar a partir da direita" @pointerdown="ddLigarDown($event, b, n, 'd')"></button>
                                            <span class="wf-dd-redim" x-show="ddRedimVisivel(b, n)" title="Redimensionar" @pointerdown="ddRedimDown($event, b, n)"></span>
                                        </div>
                                    </template>

                                    <div class="wf-dd-caixa" x-show="dd(b).caixa" :style="ddCaixaEstilo(b)"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Tabletop: mapa com grade e peças arrastáveis --}}
                <template x-if="b.tipo === 'tabletop'">
                    @include('areas.diverso.partials.tabletop')
                </template>
            </div>
        </div>
    </x-diverso.item>
</template>