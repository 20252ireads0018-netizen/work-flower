{{-- Cartão "GitHub / VS Code" (tipo codigo): repositórios e editor de código (mini-IDE).
     Nasceu no Diverso; vai junto quando cartões ou a área são levados para outra (App\Support\MoverCartoes).
     Precisa de partials/ide-cartao (WFIde, WFRepo e estilo) uma vez na página; usa b, estado, uid() e aviso() do quadro. --}}
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

        {{-- Editor de código (lógica embutida no início deste arquivo) --}}
        <template x-if="b.dados.modo === 'ide'">
            <div class="wf-ide" :class="{ 'movendo': movendo }" x-data="WFIde(b)" x-init="iniciar()"
                 :style="`--lado:${b.dados.ide.layout?.lado ?? 208}px;--painel:${b.dados.ide.layout?.painel ?? 176}px`">

                {{-- Aparece só se o navegador carregou um editor mais antigo que este bloco (a causa dos erros "… is not defined" no console) --}}
                <div class="wf-ide-velho" x-show="!(window.WFIde && WFIde.versao >= 5)" x-cloak role="alert">
                    O editor carregado no navegador está desatualizado e não vai funcionar direito.
                    Recarregue a página com <b>Ctrl+F5</b>.
                </div>

                <div class="wf-ide-barra" role="toolbar" aria-label="Ferramentas do editor">
                    <button type="button" class="btn-sec" @click="alternarBarra()" :aria-expanded="b.dados.ide.layout?.barra ?? true" title="Mostrar ou ocultar as opções">☰ Opções</button>
                    <span class="contents" x-show="b.dados.ide.layout?.barra ?? true">
                        <button type="button" class="btn-sec" @click="novoArquivo()" title="Novo arquivo (ex.: pagina.blade.php, Main.java)">+ Arquivo</button>
                        <button type="button" class="btn-sec" @click="novoLaravel()" title="Projeto Laravel completo que roda aqui, ou controller, model, migration, rotas, view, seeder, factory, componente…">+ Laravel</button>
                        <button type="button" class="btn-sec" @click="novaPasta()" title="Nova pasta">+ Pasta</button>
                        <label class="btn-sec" title="Enviar arquivos de texto do computador">Enviar
                            <input type="file" multiple class="hidden" @change="enviar($event.target.files); $event.target.value = ''">
                        </label>
                        <span class="sep"></span>
                        <button type="button" class="btn-sec" @click="salvar()" title="Salvar (Ctrl+S)">Salvar</button>
                        <button type="button" class="btn-sec" @click="baixar()" x-show="arqAtivo()" title="Baixar o arquivo aberto">Baixar arquivo</button>
                        <button type="button" class="btn-sec" @click="baixarZip()" title="Baixar o projeto inteiro em ZIP">Baixar ZIP</button>
                        <button type="button" class="btn-sec" @click="mapaDoCodigo()" title="Cria um cartão de Mapa mental com as pastas, arquivos, classes, funções e o que cada arquivo importa">Mapa do código</button>
                        <span class="sep" x-show="suporteDisco"></span>
                        <button type="button" class="btn-sec" x-show="suporteDisco" @click="abrirPasta()" title="Abrir uma pasta do computador (Chrome/Edge)">Abrir pasta</button>
                        <button type="button" class="btn-sec" x-show="disco" x-cloak @click="salvarDisco()" :title="'Gravar tudo em ' + disco">Salvar na pasta</button>
                        <span class="sep"></span>
                        <button type="button" class="btn-sec" x-show="podeExecutar()" @click="executar()" title="Executar o arquivo aberto (Ctrl+Enter): JS/TS, Python, PHP, Laravel (projeto com routes/web.php), SQL, Ruby, Lua e Blade rodam no navegador; Java, C, Go… no servidor">▶ Executar</button>
                        <button type="button" class="btn-sec" x-show="raizLaravel() !== null" x-cloak @click="artisan()" title="php artisan no navegador: route:list, migrate:fresh --seed, db:seed, tinker…">Artisan</button>
                        <button type="button" class="btn-sec" x-show="Object.keys(motorUsado).length" x-cloak @click="pararMotores()" title="Interrompe um programa travado (laço infinito) e recarrega os motores">■ Parar</button>
                        <button type="button" class="btn-sec" @click="abrirBusca()" title="Buscar e substituir (Ctrl+F)">Buscar</button>
                        <button type="button" class="btn-sec" @click="abrirPaleta('arq')" title="Abrir arquivo pelo nome (Ctrl+P)">Ir p/ arquivo</button>
                        <button type="button" class="btn-sec" x-show="arqAtivo()" @click="abrirPaleta('linha')" title="Ir para a linha (Ctrl+G)">Ir p/ linha</button>
                        <button type="button" class="btn-sec" x-show="arqAtivo() && extensao() === 'JSON'" x-cloak @click="formatar()" title="Formatar JSON (Ctrl+Shift+F)">Formatar</button>
                        <button type="button" class="btn-sec" x-show="disco" x-cloak @click="alternarAuto()" :aria-pressed="auto" title="Grava na pasta sozinho, 1,5 s depois de parar de digitar" x-text="'Auto-salvar: ' + (auto ? 'sim' : 'não')"></button>
                    </span>
                </div>

                <div class="wf-ide-busca" x-show="busca.on" x-cloak role="search">
                    <input x-ref="q" class="campo" :class="{ 'erro': buscaInvalida() }" placeholder="Buscar" aria-label="Buscar" x-model="busca.q"
                           @keydown.enter.prevent="proxima($event.shiftKey ? -1 : 1)" @keydown.escape.prevent="busca.on = false">
                    <input class="campo" placeholder="Substituir por" aria-label="Substituir por" x-model="busca.r"
                           @keydown.enter.prevent="substituirUm()" @keydown.escape.prevent="busca.on = false">
                    <button type="button" class="btn-sec" :class="{ 'on': busca.caso }" :aria-pressed="busca.caso" @click="busca.caso = !busca.caso" title="Diferenciar maiúsculas de minúsculas">Aa</button>
                    <button type="button" class="btn-sec" :class="{ 'on': busca.regex }" :aria-pressed="busca.regex" @click="busca.regex = !busca.regex" title="Expressão regular (use $1, $2… na substituição)">.*</button>
                    <span class="texto-2" x-text="buscaInvalida() ? 'regex inválida' : contar() + ' no arquivo'"></span>
                    <button type="button" class="btn-sec" @click="proxima(-1)" title="Anterior">↑</button>
                    <button type="button" class="btn-sec" @click="proxima(1)" title="Próxima">↓</button>
                    <button type="button" class="btn-sec" @click="substituirUm()">Substituir</button>
                    <button type="button" class="btn-sec" @click="substituirTodos()">Substituir todas</button>
                    <label class="flex items-center gap-1 texto-2"><input type="checkbox" x-model="busca.todos"> Em todos os arquivos</label>
                    <button type="button" class="mini-btn" @click="busca.on = false" aria-label="Fechar busca">✕</button>
                </div>

                <div class="wf-ide-corpo">
                    <aside class="wf-ide-lado" aria-label="Arquivos do projeto" @click.self="sel = ''" @contextmenu="abrirMenu(null, $event)"
                           :class="{ 'alvo-raiz': alvoIde === '' }"
                           @dragover="sobreArrasto(null, $event)" @drop="soltarArrasto(null, $event)"
                           @dragleave.self="alvoIde = null">
                        <template x-for="n in arvore()" :key="n.k + ':' + n.caminho">
                            <div class="wf-ide-no" :class="{ 'ativo': n.id && n.id === b.dados.ide.ativo, 'sel': sel === n.caminho, 'alvo': alvoIde !== null && alvoIde !== '' && n.k === 'p' && alvoIde === n.caminho, 'arrastando': arrastoIde && arrastoIde.caminho === n.caminho }"
                                 :style="`padding-left:${.4 + n.nivel * .9}rem`" @click="clicar(n)" @contextmenu="abrirMenu(n, $event)" :title="n.caminho"
                                 draggable="true" @dragstart.stop="comecarArrasto(n, $event)" @dragend="fimArrasto()"
                                 @dragover.stop="sobreArrasto(n, $event)" @drop.stop="soltarArrasto(n, $event)">
                                <span class="seta" x-text="n.k === 'p' ? (n.fechada ? '▸' : '▾') : ''"></span>
                                <span class="tipo" x-show="n.tipo" x-text="n.tipo"></span>
                                <span class="nome" x-text="n.nome"></span>
                                <span class="ponto" x-show="n.k === 'a' && alterado(n.arq)" x-cloak title="Alterações ainda não gravadas na pasta"></span>
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

                    {{-- Divisor: arraste para mudar a largura da lista de arquivos --}}
                    <div class="wf-ide-div v" role="separator" aria-orientation="vertical" tabindex="0" aria-label="Redimensionar lista de arquivos"
                         title="Arraste para redimensionar · duplo clique restaura"
                         @pointerdown="arrastar('lado', $event)" @dblclick="restaurar('lado')" @keydown="teclaDivisor('lado', $event)"></div>

                    <section class="wf-ide-main">
                        <div class="wf-ide-abas" role="tablist" aria-label="Arquivos abertos">
                            <template x-for="f in abas()" :key="f.id">
                                <div class="wf-ide-aba" role="tab" :class="{ 'ativo': f.id === b.dados.ide.ativo }" :aria-selected="f.id === b.dados.ide.ativo"
                                     @click="abrir(f.id)" @auxclick.prevent="if ($event.button === 1) fechar(f.id)" :title="f.caminho + ' (clique do meio fecha)'">
                                    <span x-text="nomeArq(f)"></span>
                                    <span class="ponto" x-show="alterado(f)" x-cloak title="Alterações ainda não gravadas na pasta"></span>
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

                        {{-- Divisor: arraste para mudar a altura do console/prévia --}}
                        <div class="wf-ide-div h" x-show="painel" x-cloak role="separator" aria-orientation="horizontal" tabindex="0" aria-label="Redimensionar console e prévia"
                             title="Arraste para redimensionar · duplo clique restaura"
                             @pointerdown="arrastar('painel', $event)" @dblclick="restaurar('painel')" @keydown="teclaDivisor('painel', $event)"></div>

                        <div class="wf-ide-painel" x-show="painel" x-cloak>
                            <div class="wf-ide-painel-topo">
                                <span class="t" :class="{ 'ativo': painel === 'saida' }" @click="painel = 'saida'">Console</span>
                                <span class="t" :class="{ 'ativo': painel === 'previa' }" @click="painel = 'previa'">Prévia</span>
                                <span class="t" x-show="temBanco()" :class="{ 'ativo': painel === 'banco' }" @click="painel = 'banco'; atualizarBanco()">Banco</span>
                                <span style="flex:1"></span>
                                <button type="button" class="link-prim" x-show="painel === 'previa'" @click="telaCheiaPrevia()" title="Prévia em tela cheia">Tela cheia</button>
                                <button type="button" class="link-prim" @click="limparSaida()">Limpar</button>
                                <button type="button" class="mini-btn" @click="fecharPainel()" aria-label="Fechar painel">✕</button>
                            </div>
                            <div class="wf-ide-saida" x-show="painel === 'saida'">
                                <p class="texto-2" x-show="!saida.length">Sem saída. Use console.log() (JS/TS), print() (Python/Lua), puts (Ruby), echo (PHP), System.out.println() (Java)…</p>
                                <template x-for="(l, i) in saida" :key="i">
                                    <div>
                                        <template x-if="l.t !== 'tabela'">
                                            <div class="wf-ide-log" :class="'l-' + l.t"><span x-show="l.t === 'dica'" aria-hidden="true">💡 </span><span x-text="l.m"></span></div>
                                        </template>
                                        <template x-if="l.t === 'tabela'">
                                            <div class="wf-ide-tab">
                                                <div class="cab" x-text="l.m + ' · ' + l.total + (l.total === 1 ? ' linha' : ' linhas') + (l.total > l.linhas.length ? ' (mostrando ' + l.linhas.length + ')' : '')"></div>
                                                <div class="rolar"><table>
                                                    <thead><tr><template x-for="c in l.colunas" :key="c"><th x-text="c"></th></template></tr></thead>
                                                    <tbody><template x-for="(r, k) in l.linhas" :key="k"><tr><template x-for="(v, j) in r" :key="j"><td :class="{ nulo: v === null }" x-text="v === null ? 'NULL' : v"></td></template></tr></template></tbody>
                                                </table></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="wf-ide-saida wf-ide-banco" x-show="painel === 'banco'">
                                <div class="flex items-center gap-2 mb-2">
                                    <b x-text="lv.ativo ? 'Banco do app Laravel (SQLite do navegador)' : 'Tabelas do banco (SQLite do navegador)'"></b>
                                    <span style="flex:1"></span>
                                    <button type="button" class="link-prim" @click="recriarBanco()" :title="lv.ativo ? 'Apaga as tabelas e roda as migrations e o DatabaseSeeder de novo (migrate:fresh --seed)' : 'Apaga o banco e roda todos os .sql do projeto em ordem de nome'">Recriar banco</button>
                                </div>
                                <p class="texto-2" x-show="!banco.tabelas.length" x-text="lv.ativo ? 'Nenhuma tabela ainda. Crie uma migration (+ Laravel → Migration) e execute o app.' : 'Nenhuma tabela ainda. Rode um .sql com CREATE TABLE (▶ Executar).'"></p>
                                <div class="lista">
                                    <template x-for="t in banco.tabelas" :key="t.nome">
                                        <button type="button" class="item" :class="{ sel: banco.sel === t.nome }" @click="verTabela(t.nome)">
                                            <b x-text="t.nome"></b> <span class="texto-2" x-text="t.total + (t.total === 1 ? ' linha' : ' linhas')"></span>
                                            <span class="cols" x-text="t.colunas.join(' · ')"></span>
                                        </button>
                                    </template>
                                </div>
                                <template x-if="banco.dados">
                                    <div class="wf-ide-tab mt-2">
                                        <div class="cab" x-text="banco.dados.titulo + ' · primeiras ' + banco.dados.linhas.length + ' linhas'"></div>
                                        <div class="rolar"><table>
                                            <thead><tr><template x-for="c in banco.dados.colunas" :key="c"><th x-text="c"></th></template></tr></thead>
                                            <tbody><template x-for="(r, k) in banco.dados.linhas" :key="k"><tr><template x-for="(v, j) in r" :key="j"><td :class="{ nulo: v === null }" x-text="v === null ? 'NULL' : v"></td></template></tr></template></tbody>
                                        </table></div>
                                    </div>
                                </template>
                            </div>
                            {{-- app Laravel na prévia: endereço, voltar, recarregar, rotas e artisan --}}
                            <div class="wf-ide-lvbar" x-show="painel === 'previa' && lv.ativo" x-cloak>
                                <button type="button" class="mini-btn" @click="voltarLaravel()" :disabled="!lv.hist.length" title="Voltar" aria-label="Voltar">←</button>
                                <button type="button" class="mini-btn" @click="recarregarLaravel()" title="Recarregar (pede a página de novo)" aria-label="Recarregar">↻</button>
                                <span class="texto-2">localhost</span>
                                <input class="campo" x-model="lv.campo" @keydown.enter.prevent="irPara(lv.campo)" aria-label="Endereço no app Laravel" placeholder="/rota" spellcheck="false">
                                <span class="st" :class="{ 'ruim': lv.status >= 400 }" x-show="lv.status" x-text="lv.carregando ? '…' : lv.status"></span>
                                <button type="button" class="link-prim" @click="artisan('route:list')" title="Lista as rotas do app (artisan route:list)">Rotas</button>
                                <button type="button" class="link-prim" @click="artisan()" title="php artisan: migrate:fresh --seed, db:seed, tinker…">Artisan</button>
                            </div>
                            <iframe class="wf-ide-frame" x-ref="frame" x-show="painel === 'previa'" title="Prévia do projeto"
                                    sandbox="allow-scripts allow-forms allow-modals allow-popups" :srcdoc="srcdoc"></iframe>
                        </div>
                    </section>
                </div>

                {{-- Menu do botão direito (arquivos e pastas). :style em forma de objeto para não apagar o display:none do x-show --}}
                <div class="wf-ide-ctx" x-show="menuCtx" x-cloak role="menu" aria-label="Ações do arquivo"
                     :style="menuCtx ? { left: menuCtx.x + 'px', top: menuCtx.y + 'px' } : {}"
                     @click.outside="menuCtx = null" @contextmenu.prevent>
                    <template x-for="it in itensCtx()" :key="it.id">
                        <div>
                            <div class="wf-ide-ctx-sep" x-show="it.sep"></div>
                            <button type="button" class="wf-ide-ctx-it" role="menuitem" :disabled="it.off" @click="acaoCtx(it.id)">
                                <span x-text="it.nome"></span><small x-show="it.dica" x-text="it.dica"></small>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Paleta: abrir arquivo pelo nome (Ctrl+P) ou ir para a linha (Ctrl+G) --}}
                <div class="wf-ide-pal-fundo" x-show="pal.on" x-cloak @click.self="fecharPaleta()">
                    <div class="wf-ide-pal" role="dialog" :aria-label="pal.modo === 'linha' ? 'Ir para a linha' : 'Abrir arquivo'">
                        <input x-ref="palq" class="campo" x-model="pal.q" @input="pal.i = 0" @keydown="teclaPaleta($event)" autocomplete="off" spellcheck="false"
                               :placeholder="pal.modo === 'linha' ? 'Linha ou linha:coluna (ex.: 42:7) e Enter' : 'Digite parte do nome do arquivo…'"
                               :aria-label="pal.modo === 'linha' ? 'Número da linha' : 'Nome do arquivo'">
                        <div class="wf-ide-pal-lista" x-show="pal.modo === 'arq'" role="listbox">
                            <template x-for="(f, i) in (pal.on && pal.modo === 'arq' ? itensPaleta() : [])" :key="f.id">
                                <div class="wf-ide-pal-it" role="option" :class="{ 'sel': i === pal.i }" :aria-selected="i === pal.i"
                                     @mousedown.prevent="escolherPaleta(f)" @mouseenter="pal.i = i">
                                    <span x-text="nomeArq(f)"></span><small x-text="f.caminho"></small>
                                </div>
                            </template>
                            <p class="wf-ide-pal-dica" x-show="pal.on && pal.modo === 'arq' && !itensPaleta().length">Nenhum arquivo encontrado.</p>
                        </div>
                        <p class="wf-ide-pal-dica" x-show="pal.modo === 'linha'" x-text="arqAtivo() ? 'O arquivo tem ' + totalLinhas() + ' linhas.' : 'Abra um arquivo primeiro.'"></p>
                    </div>
                </div>

                <div class="wf-ide-status" aria-live="polite">
                    <span x-text="arqAtivo() ? arqAtivo().caminho : 'Nenhum arquivo aberto'"></span>
                    <span x-show="arqAtivo()" x-text="extensao() + ' · Ln ' + ln + ', Col ' + col + (selN ? ' (' + selN + ' selec.)' : '') + ' · ' + totalLinhas() + ' linhas'"></span>
                    <span x-show="disco" x-cloak x-text="'Pasta: ' + disco + (totalAlterados() ? ' · ' + totalAlterados() + ' não gravado(s)' : '')"></span>
                    <span x-show="msg" x-cloak style="color:var(--prim-forte)" x-text="msg"></span>
                </div>
            </div>
        </template>
    </div>
