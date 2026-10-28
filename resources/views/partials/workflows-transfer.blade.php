{{-- Componente Alpine de importar/exportar as áreas. Embutido no layout: não depende de public/js. --}}
@verbatim
<script>
/**
 * Work Flower · Importar e exportar as áreas (Corpo, Mente, Carteira e Diverso)
 *
 * Baixa tudo em um arquivo .workflower.json e importa esse mesmo arquivo em outra conta.
 * A transferência entre contas é feita pelo arquivo: baixe aqui, envie para a pessoa
 * (e-mail, WhatsApp, etc.) e ela importa na conta dela.
 *
 * Rotas (meta tags no <head><meta charset="utf-8"> do layout):
 *   workflows-lista-url     GET  -> [{ id, nome, resumo }]
 *   workflows-exportar-url  GET  ?ids[]=corpo&ids[]=mente... -> { workflows, avisos }
 *   workflows-importar-url  POST { formato, versao, workflows } -> { importados, areas }
 */
(function () {
    'use strict';

    const FORMATO = 'work-flower.workflows';
    const VERSAO = 1;
    const EXTENSAO = '.workflower.json';
    const LIMITE_BYTES = 80 * 1024 * 1024; // imagens e PDFs entram no arquivo
    const AREAS = ['corpo', 'mente', 'carteira', 'diverso'];

    const meta = nome => document.querySelector('meta[name="' + nome + '"]')?.content || '';

    const cabecalhos = extra => Object.assign({
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': meta('csrf-token'),
    }, extra || {});

    /** Pedido ao servidor com tratamento dos erros comuns. Lança Error com mensagem pronta para exibir. */
    async function pedir(url, opcoes) {
        let r;
        try {
            r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opcoes));
        } catch {
            throw new Error('Sem conexão com o servidor. Tente de novo.');
        }
        if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('Sessão expirada.'); }
        if (r.status === 413) throw new Error('O arquivo é grande demais para o servidor.');
        if (r.status === 422) {
            const j = await r.json().catch(() => ({}));
            throw new Error(Object.values(j.errors || {})[0]?.[0] || j.message || 'Confira os dados e tente de novo.');
        }
        if (!r.ok) {
            const j = await r.json().catch(() => ({}));
            throw new Error(j.message || 'O servidor respondeu com erro (' + r.status + ').');
        }
        return r.json();
    }

    /** SHA-256 em hexadecimal. Devolve null se o navegador não suportar (ex.: página sem HTTPS). */
    async function sha256(texto) {
        if (!window.crypto?.subtle) return null;
        const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(texto));
        return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    const dataHoje = () => new Date().toISOString().slice(0, 10);

    /** Baixa um objeto como arquivo JSON. */
    function baixar(objeto, nomeArquivo) {
        const blob = new Blob([JSON.stringify(objeto)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nomeArquivo;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    /** Confere se o conteúdo lido é um pacote válido. Lança Error com a causa. */
    function validarPacote(p) {
        if (!p || typeof p !== 'object' || Array.isArray(p)) throw new Error('Este arquivo não é um pacote do Work Flower.');
        if (p.formato !== FORMATO) throw new Error('Este arquivo não foi gerado pelo Work Flower.');
        if (!Number.isInteger(p.versao) || p.versao < 1) throw new Error('Versão do arquivo inválida.');
        if (p.versao > VERSAO) throw new Error('Este arquivo é de uma versão mais nova. Atualize o sistema para importá-lo.');
        if (!Array.isArray(p.workflows) || !p.workflows.length) throw new Error('O arquivo não contém nenhuma área.');
        if (p.workflows.length > AREAS.length) throw new Error('O arquivo tem áreas demais.');
        p.workflows.forEach((w, i) => {
            if (!w || typeof w !== 'object' || !AREAS.includes(w.area) || typeof w.nome !== 'string' || !Array.isArray(w.dados)) {
                throw new Error('A área nº ' + (i + 1) + ' do arquivo está malformada.');
            }
        });
    }

    window.workflowsTransfer = function () {
        return {
            // dados
            itens: [],
            selecionados: [],          // ids marcados para exportar

            // importação
            pacote: null,              // pacote lido do arquivo, ainda não enviado
            nomeArquivo: '',
            avisoIntegridade: false,
            selImport: [],             // índices marcados para importar

            // estado
            carregado: false,
            tentou: false,             // a carga automática roda no máximo uma vez
            carregando: false,
            exportando: false,
            importando: false,
            erro: '',
            ok: '',

            get urlLista()    { return meta('workflows-lista-url'); },
            get urlExportar() { return meta('workflows-exportar-url'); },
            get urlImportar() { return meta('workflows-importar-url'); },
            get configurado() { return !!(this.urlLista && this.urlExportar && this.urlImportar); },

            /** Texto do que falta configurar, para não ficar em silêncio. */
            get faltando() {
                const f = [];
                if (!this.urlLista) f.push('workflows.lista');
                if (!this.urlExportar) f.push('workflows.exportar');
                if (!this.urlImportar) f.push('workflows.importar');
                return f.join(', ');
            },

            /** Nomes das áreas marcadas para importar (usado no aviso de substituição). */
            get nomesImport() {
                return this.pacote ? this.selImport.map(i => this.pacote.workflows[i].nome) : [];
            },

            // ---------- lista ----------
            /** Primeira carga ao abrir a aba. Não repete se falhar; o usuário tenta de novo com "Recarregar". */
            async carregarUmaVez() {
                if (this.tentou) return;
                this.tentou = true;
                await this.carregar();
            },

            async carregar() {
                if (this.carregando || !this.urlLista) return;
                this.carregando = true;
                this.erro = '';
                try {
                    const j = await pedir(this.urlLista, { headers: cabecalhos() });
                    this.itens = Array.isArray(j) ? j : (j.workflows || []);
                    this.selecionados = this.selecionados.filter(id => this.itens.some(i => i.id === id));
                    this.carregado = true;
                } catch (e) {
                    this.erro = e.message;
                } finally {
                    this.carregando = false;
                }
            },

            resumo(i) { return i.resumo || ''; },

            resumoPacote(w) {
                const n = (w.dados || []).length;
                const p = (w.pdfs || []).length;
                return n + (n === 1 ? ' registro' : ' registros') + (p ? ' · ' + p + (p === 1 ? ' PDF' : ' PDFs') : '');
            },

            // ---------- exportar ----------
            /** Baixa todas as áreas. Não depende da lista ter carregado. */
            exportarTudo() { return this.exportar(AREAS); },

            /** Baixa as áreas marcadas (ou todas, se nada estiver marcado). */
            exportarMarcadas() { return this.exportar(this.selecionados.length ? this.selecionados : AREAS); },

            async exportar(ids) {
                if (this.exportando) return;
                this.erro = ''; this.ok = '';
                if (!this.urlExportar) { this.erro = 'Rota ausente: workflows.exportar'; return; }

                this.exportando = true;
                try {
                    const qs = ids.map(id => 'ids[]=' + encodeURIComponent(id)).join('&');
                    const j = await pedir(this.urlExportar + (this.urlExportar.includes('?') ? '&' : '?') + qs, { headers: cabecalhos() });
                    const workflows = j.workflows || [];
                    if (!workflows.length) throw new Error('O servidor não devolveu nenhuma área.');

                    const pacote = {
                        formato: FORMATO,
                        versao: VERSAO,
                        exportado_em: new Date().toISOString(),
                        total: workflows.length,
                        sha256: await sha256(JSON.stringify(workflows)),
                        workflows,
                    };
                    const base = workflows.length === 1 ? 'work-flower-' + workflows[0].area : 'work-flower-' + dataHoje();
                    baixar(pacote, base + EXTENSAO);

                    this.ok = workflows.length === 1 ? 'Área baixada.' : workflows.length + ' áreas baixadas.';
                    if ((j.avisos || []).length) this.ok += ' ' + j.avisos.join(' ');
                } catch (e) {
                    this.erro = e.message;
                } finally {
                    this.exportando = false;
                }
            },

            // ---------- importar ----------
            async escolherArquivo(arquivo) {
                this.erro = ''; this.ok = '';
                this.pacote = null; this.avisoIntegridade = false;
                if (!arquivo) return;
                if (arquivo.size > LIMITE_BYTES) { this.erro = 'O arquivo passa de 80 MB.'; return; }

                try {
                    let p;
                    try { p = JSON.parse(await arquivo.text()); }
                    catch { throw new Error('Não consegui ler o arquivo. Ele precisa ser um ' + EXTENSAO + ' válido.'); }

                    validarPacote(p);

                    if (p.sha256) {
                        const atual = await sha256(JSON.stringify(p.workflows));
                        this.avisoIntegridade = atual !== null && atual !== p.sha256;
                    }

                    this.pacote = p;
                    this.nomeArquivo = arquivo.name;
                    this.selImport = p.workflows.map((_, i) => i);
                } catch (e) {
                    this.erro = e.message;
                }
            },

            alternarImport(i) {
                this.selImport = this.selImport.includes(i)
                    ? this.selImport.filter(x => x !== i)
                    : this.selImport.concat(i).sort((a, b) => a - b);
            },

            cancelarImportacao() {
                this.pacote = null; this.nomeArquivo = ''; this.selImport = []; this.avisoIntegridade = false;
            },

            async importar() {
                if (this.importando || !this.pacote || !this.selImport.length) return;

                const nomes = this.nomesImport.join(', ');
                if (!confirm('Importar vai SUBSTITUIR o que você tem hoje em: ' + nomes + '.\n\nSe quiser guardar o conteúdo atual, cancele e baixe um backup antes.\n\nContinuar?')) return;

                this.erro = ''; this.ok = '';
                this.importando = true;
                try {
                    const j = await pedir(this.urlImportar, {
                        method: 'POST',
                        headers: cabecalhos({ 'Content-Type': 'application/json' }),
                        body: JSON.stringify({
                            formato: FORMATO,
                            versao: this.pacote.versao,
                            workflows: this.selImport.map(i => this.pacote.workflows[i]),
                        }),
                    });
                    this.ok = 'Importado: ' + (j.areas || []).join(', ') + '. Recarregando…';
                    this.cancelarImportacao();
                    setTimeout(() => location.reload(), 1200); // as páginas passam a mostrar os dados novos
                } catch (e) {
                    this.erro = e.message;
                } finally {
                    this.importando = false;
                }
            },
        };
    };
    window.workflowsTransfer.versao = 4;
})();
</script>
@endverbatim
