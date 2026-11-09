/* Work Flower · textos rápidos (expansor de texto, carregado pelo layout para quem está logado)
   - Ao digitar um atalho (ex.: ";ass") em qualquer campo de texto, área de texto ou texto editável do site,
     ele vira o texto salvo. Ctrl+Z desfaz a troca (volta o atalho).
   - Variáveis no texto: {data} {hora} {data_extenso} {dia_semana} {ano} {nome} {primeiro_nome} {email};
     {cursor} marca onde o cursor fica depois da troca.
   - Os textos ficam nas preferências da conta (WFPrefs: "trechos" e "expansorLigado").
   - O gerenciador (criar, editar, apagar, importar e exportar) aparece na aba Atalhos das configurações,
     dentro de #wf-expansor-painel. Um campo com data-wf-sem-expansor (ou dentro de um) nunca expande. */
(function () {
    'use strict';
    if (window.wfExpansor || !window.WFPrefs) return;

    const LIMITE = 200, MAX_ATALHO = 30, MAX_TEXTO = 5000;
    const eu = document.currentScript || document.querySelector('script[src*="js/expansor.js"]');
    const usuario = { nome: (eu && eu.dataset.nome) || '', email: (eu && eu.dataset.email) || '' };

    const lista = () => (Array.isArray(WFPrefs.ler().trechos) ? WFPrefs.ler().trechos : []);
    const ligado = () => WFPrefs.ler().expansorLigado !== false;

    /* ---------- variáveis ---------- */
    const dois = n => String(n).padStart(2, '0');
    const DIAS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const VARIAVEIS = {
        data: d => dois(d.getDate()) + '/' + dois(d.getMonth() + 1) + '/' + d.getFullYear(),
        hora: d => dois(d.getHours()) + ':' + dois(d.getMinutes()),
        data_extenso: d => d.getDate() + ' de ' + MESES[d.getMonth()] + ' de ' + d.getFullYear(),
        dia_semana: d => DIAS[d.getDay()],
        ano: d => String(d.getFullYear()),
        nome: () => usuario.nome,
        primeiro_nome: () => usuario.nome.trim().split(/\s+/)[0] || '',
        email: () => usuario.email,
    };
    const CURSOR = '\u0000';

    /** Troca as variáveis; devolve { texto, cursor } (cursor = posição do {cursor}, ou -1). */
    function montar(modelo, agora) {
        const d = agora || new Date();
        let marcou = false;
        const t = String(modelo).replace(/\r\n?/g, '\n').replace(/\{([a-z_]+)\}/gi, (todo, nome) => {
            const n = nome.toLowerCase();
            if (n === 'cursor') { if (marcou) return ''; marcou = true; return CURSOR; }
            return VARIAVEIS[n] ? VARIAVEIS[n](d) : todo;
        });
        const i = t.indexOf(CURSOR);
        return { texto: t.replace(CURSOR, ''), cursor: i };
    }

    /** O trecho cujo atalho termina exatamente antes do cursor (o mais longo, se mais de um servir). */
    function achar(antes) {
        let melhor = null;
        for (const s of lista()) {
            const a = s && s.atalho;
            if (!a || !antes.endsWith(a) || (melhor && melhor.atalho.length >= a.length)) continue;
            // atalho que começa com letra/número só vale no começo de uma palavra ("ass" não troca dentro de "massa")
            if (/^[\p{L}\p{N}]/u.test(a)) {
                const c = antes.charAt(antes.length - a.length - 1);
                if (c && /[\p{L}\p{N}_]/u.test(c)) continue;
            }
            melhor = s;
        }
        return melhor;
    }

    /* ---------- onde vale ---------- */
    const TIPOS = ['text', 'search', 'url', 'email', 'tel', ''];
    function campoDe(el) {
        if (!el || el.nodeType !== 1 || el.closest('[data-wf-sem-expansor], .monaco-editor, .cm-editor, .CodeMirror')) return null;
        if (el.tagName === 'TEXTAREA') return el.readOnly ? null : { tipo: 'campo', el };
        if (el.tagName === 'INPUT') return TIPOS.includes((el.getAttribute('type') || '').toLowerCase()) && !el.readOnly ? { tipo: 'campo', el, linha: true } : null;
        if (el.isContentEditable) return { tipo: 'editavel', el };
        return null;
    }

    /* ---------- troca ---------- */
    let trocando = false;
    function inserir(texto) {
        // execCommand mantém o Ctrl+Z e avisa os frameworks (Alpine, x-model) com o evento input de sempre
        try { if (document.execCommand('insertText', false, texto)) return true; } catch (e) { /* segue */ }
        return false;
    }

    function expandirCampo(c, s) {
        const el = c.el;
        const fim = el.selectionStart;
        if (fim == null || fim !== el.selectionEnd) return;
        let { texto, cursor } = montar(s.texto);
        if (c.linha) texto = texto.replace(/\r?\n/g, ' ');
        const ini = fim - s.atalho.length;
        trocando = true;
        try {
            el.setSelectionRange(ini, fim);
            if (!inserir(texto)) {
                el.setRangeText(texto, ini, fim, 'end');
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (cursor >= 0) el.setSelectionRange(ini + cursor, ini + cursor);
        } finally { trocando = false; }
    }

    function expandirEditavel(c, s) {
        const sel = window.getSelection();
        if (!sel || !sel.isCollapsed || !sel.anchorNode || sel.anchorNode.nodeType !== 3) return;
        const no = sel.anchorNode, fim = sel.anchorOffset;
        const { texto, cursor } = montar(s.texto);
        // num texto editável comum a quebra de linha vira <br> (só com white-space: pre* o "\n" aparece sozinho)
        const pre = /^pre|break-spaces/.test(getComputedStyle(c.el).whiteSpace);
        const html = !pre && texto.includes('\n') ? texto.split('\n').map(esc).join('<br>') : null;
        trocando = true;
        try {
            const r = document.createRange();
            r.setStart(no, fim - s.atalho.length); r.setEnd(no, fim);
            sel.removeAllRanges(); sel.addRange(r);
            let foi = false;
            if (html !== null) { try { foi = document.execCommand('insertHTML', false, html); } catch (e) { /* segue */ } }
            if (!foi && !inserir(texto)) {
                r.deleteContents();
                const t = document.createTextNode(texto);
                r.insertNode(t);
                r.setStartAfter(t); r.collapse(true);
                sel.removeAllRanges(); sel.addRange(r);
                no.parentElement && no.parentElement.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (cursor >= 0 && sel.modify) {
                for (let i = 0; i < texto.length - cursor; i++) sel.modify('move', 'backward', 'character');
            }
        } finally { trocando = false; }
    }

    function conferir(alvo) {
        if (trocando || !ligado() || !lista().length) return;
        const c = campoDe(alvo);
        if (!c) return;
        if (c.tipo === 'campo') {
            const p = c.el.selectionStart;
            if (p == null || p !== c.el.selectionEnd) return;
            const s = achar(c.el.value.slice(Math.max(0, p - MAX_ATALHO - 1), p));
            if (s) expandirCampo(c, s);
        } else {
            const sel = window.getSelection();
            if (!sel || !sel.isCollapsed || !sel.anchorNode || sel.anchorNode.nodeType !== 3) return;
            const s = achar(sel.anchorNode.data.slice(Math.max(0, sel.anchorOffset - MAX_ATALHO - 1), sel.anchorOffset));
            if (s) expandirEditavel(c, s);
        }
    }

    // a troca vem logo depois do evento (não dentro dele): assim ela é um passo próprio do Ctrl+Z
    document.addEventListener('input', e => {
        if (trocando || e.isComposing || (e.inputType && e.inputType !== 'insertText' && e.inputType !== 'insertCompositionText')) return;
        const alvo = e.target;
        setTimeout(() => conferir(alvo), 0);
    }, true);
    document.addEventListener('compositionend', e => setTimeout(() => conferir(e.target), 0), true);

    /* ---------- limpeza e importação ---------- */
    function limpar(arr) {
        const vistos = new Set(), saida = [];
        for (const s of Array.isArray(arr) ? arr : []) {
            if (!s || typeof s !== 'object') continue;
            const atalho = String(s.atalho ?? s.abbreviation ?? s.abreviacao ?? '').trim().slice(0, MAX_ATALHO);
            const texto = String(s.texto ?? s.text ?? s.plainText ?? s.conteudo ?? '').slice(0, MAX_TEXTO);
            if (!atalho || /\s/.test(atalho) || !texto || vistos.has(atalho)) continue;
            vistos.add(atalho);
            saida.push({ atalho, texto, nome: String(s.nome ?? s.label ?? '').trim().slice(0, 60) });
            if (saida.length >= LIMITE) break;
        }
        return saida;
    }

    /** CSV simples: atalho;texto ou atalho,texto (aspas para textos com vírgula ou quebra de linha). */
    function lerCsv(txt) {
        const linhas = [], sep = /^[^\n]*;/.test(txt) && !/^[^\n;]*,/.test(txt) ? ';' : ',';
        let campo = '', linha = [], aspas = false;
        for (let i = 0; i < txt.length; i++) {
            const ch = txt[i];
            if (aspas) {
                if (ch === '"' && txt[i + 1] === '"') { campo += '"'; i++; }
                else if (ch === '"') aspas = false;
                else campo += ch;
            } else if (ch === '"') aspas = true;
            else if (ch === sep) { linha.push(campo); campo = ''; }
            else if (ch === '\n' || ch === '\r') {
                if (ch === '\r' && txt[i + 1] === '\n') i++;
                linha.push(campo); campo = ''; linhas.push(linha); linha = [];
            } else campo += ch;
        }
        if (campo || linha.length) { linha.push(campo); linhas.push(linha); }
        return linhas.filter(l => l.length >= 2 && !/^atalho$/i.test(l[0].trim()))
            .map(l => ({ atalho: l[0], texto: l[1], nome: l[2] || '' }));
    }

    function salvar(nova) { WFPrefs.salvar({ trechos: limpar(nova) }); }

    window.wfExpansor = { montar, achar, limpar, lerCsv, lista, ligado };

    /* ---------- gerenciador (aba Atalhos das configurações) ---------- */
    const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let editando = null, nota = '';

    function desenhar() {
        const raiz = document.getElementById('wf-expansor-painel');
        if (!raiz) return;
        const itens = lista();
        const form = editando === null ? '' : formHtml(editando >= 0 ? itens[editando] : { atalho: '', texto: '', nome: '' });
        // o campo "Experimente aqui" fica fora do que é redesenhado, para não perder o que se digitou nele
        if (!raiz.querySelector('[data-exp-corpo]')) {
            raiz.innerHTML = '<div data-exp-corpo class="space-y-3"></div>'
                + '<label class="block text-xs texto-2">Experimente aqui'
                + '<textarea class="campo mt-1 text-sm" rows="2" placeholder="Digite um atalho…"></textarea></label>';
        }
        raiz.querySelector('[data-exp-corpo]').innerHTML = `
            <div class="flex items-center justify-between gap-2">
                <p class="font-semibold text-sm" id="exp-rotulo">Textos rápidos</p>
                <button type="button" role="switch" class="wf-chave" data-exp="ligar" aria-checked="${ligado()}" aria-labelledby="exp-rotulo">
                    <span class="sr-only">${ligado() ? 'Ligado' : 'Desligado'}</span></button>
            </div>
            <p class="text-xs texto-2">Digite um atalho (ex.: <kbd>;ass</kbd>) em qualquer campo do site e ele vira o texto salvo. <kbd>Ctrl</kbd> + <kbd>Z</kbd> desfaz a troca.</p>
            ${nota ? `<p class="aviso-ok text-xs" role="status">${esc(nota)}</p>` : ''}
            ${itens.length ? `<ul class="wf-teclas text-sm">${itens.map((s, i) => `
                <li>
                    <span class="min-w-0"><kbd>${esc(s.atalho)}</kbd>
                        <span class="block text-xs texto-2 truncate">${esc(s.nome || s.texto.replace(/\s+/g, ' ').slice(0, 70))}</span></span>
                    <span class="flex items-center gap-1 shrink-0">
                        <button type="button" class="mini-btn" data-exp="editar" data-i="${i}" title="Editar" aria-label="Editar ${esc(s.atalho)}">✎</button>
                        <button type="button" class="mini-btn" data-exp="apagar" data-i="${i}" title="Apagar" aria-label="Apagar ${esc(s.atalho)}">×</button>
                    </span>
                </li>`).join('')}</ul>` : '<p class="text-xs texto-2">Nenhum texto salvo ainda.</p>'}
            ${form || `<div class="flex flex-wrap gap-2">
                <button type="button" class="btn flex-1" data-exp="novo" ${itens.length >= LIMITE ? 'disabled' : ''}>Novo texto</button>
                <button type="button" class="btn-sec" data-exp="importar">Importar</button>
                <button type="button" class="btn-sec" data-exp="exportar" ${itens.length ? '' : 'disabled'}>Exportar</button>
                <input type="file" accept=".json,.csv,application/json,text/csv" style="display:none" data-exp="arquivo">
            </div>`}`;
    }

    function formHtml(s) {
        const vars = ['data', 'hora', 'data_extenso', 'dia_semana', 'nome', 'primeiro_nome', 'email', 'cursor'];
        return `<div class="space-y-2" data-wf-sem-expansor style="border:1px solid var(--linha);border-radius:.75rem;padding:.75rem">
            <label class="block text-xs font-semibold">Atalho
                <input class="campo mt-1 text-sm" data-exp-campo="atalho" maxlength="${MAX_ATALHO}" value="${esc(s.atalho)}" placeholder=";ass" autocomplete="off" spellcheck="false"></label>
            <label class="block text-xs font-semibold">Nome (opcional)
                <input class="campo mt-1 text-sm" data-exp-campo="nome" maxlength="60" value="${esc(s.nome || '')}" placeholder="Assinatura do e-mail"></label>
            <label class="block text-xs font-semibold">Texto
                <textarea class="campo mt-1 text-sm" data-exp-campo="texto" rows="5" maxlength="${MAX_TEXTO}">${esc(s.texto)}</textarea></label>
            <div class="flex flex-wrap gap-1">${vars.map(v => `<button type="button" class="btn-sec !px-2 !py-0.5 text-xs" data-exp="var" data-v="${v}">{${v}}</button>`).join('')}</div>
            <p class="text-xs texto-2">{cursor} marca onde o cursor fica depois da troca.</p>
            <p class="text-xs" style="color:#e5484d" data-exp-erro></p>
            <div class="flex gap-2">
                <button type="button" class="btn flex-1" data-exp="guardar">Salvar</button>
                <button type="button" class="btn-sec" data-exp="cancelar">Cancelar</button>
            </div>
        </div>`;
    }

    function avisar(t) { nota = t; desenhar(); setTimeout(() => { if (nota === t) { nota = ''; desenhar(); } }, 3500); }

    function guardar(raiz) {
        const v = n => raiz.querySelector(`[data-exp-campo="${n}"]`).value;
        const atalho = v('atalho').trim(), texto = v('texto'), nome = v('nome').trim();
        const erro = raiz.querySelector('[data-exp-erro]');
        const itens = lista().slice();
        if (!atalho || /\s/.test(atalho)) { erro.textContent = 'O atalho não pode ficar vazio nem ter espaços.'; return; }
        if (!texto) { erro.textContent = 'Escreva o texto que entra no lugar do atalho.'; return; }
        if (itens.some((s, i) => s.atalho === atalho && i !== editando)) { erro.textContent = 'Já existe um texto com esse atalho.'; return; }
        const novo = { atalho, texto, nome };
        if (editando >= 0) itens[editando] = novo; else itens.push(novo);
        editando = null;
        salvar(itens);
        avisar('Texto salvo.');
    }

    function exportar() {
        const blob = new Blob([JSON.stringify({ workflower: 'textos-rapidos', versao: 1, trechos: lista() }, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'textos-rapidos.json';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    function importar(arquivo) {
        const r = new FileReader();
        r.onload = () => {
            const txt = String(r.result || '').replace(/^﻿/, '');
            let vindos = [];
            try {
                const j = JSON.parse(txt);
                vindos = Array.isArray(j) ? j : (j.trechos || j.snippets || []);
            } catch (e) { vindos = lerCsv(txt); }
            vindos = limpar(vindos);
            if (!vindos.length) { avisar('Nenhum texto encontrado no arquivo (use o .json exportado daqui ou um CSV atalho,texto).'); return; }
            // o que vem do arquivo substitui o atalho igual que já existia
            const porAtalho = new Map(lista().map(s => [s.atalho, s]));
            vindos.forEach(s => porAtalho.set(s.atalho, s));
            const total = [...porAtalho.values()];
            salvar(total);
            avisar(`${vindos.length} texto(s) importado(s)` + (total.length > LIMITE ? `; ficaram só os primeiros ${LIMITE}.` : '.'));
        };
        r.readAsText(arquivo);
    }

    document.addEventListener('click', e => {
        const b = e.target.closest('#wf-expansor-painel [data-exp]');
        if (!b) return;
        const raiz = document.getElementById('wf-expansor-painel');
        const acao = b.dataset.exp, i = Number(b.dataset.i);
        if (acao === 'ligar') { WFPrefs.salvar({ expansorLigado: !ligado() }); desenhar(); }
        else if (acao === 'novo') { editando = -1; desenhar(); raiz.querySelector('[data-exp-campo="atalho"]').focus(); }
        else if (acao === 'editar') { editando = i; desenhar(); raiz.querySelector('[data-exp-campo="texto"]').focus(); }
        else if (acao === 'cancelar') { editando = null; desenhar(); }
        else if (acao === 'guardar') guardar(raiz);
        else if (acao === 'apagar') {
            const s = lista()[i];
            if (s && confirm(`Apagar o texto "${s.atalho}"?`)) { if (editando === i) editando = null; salvar(lista().filter((_, j) => j !== i)); avisar('Texto apagado.'); }
        }
        else if (acao === 'var') {
            const t = raiz.querySelector('[data-exp-campo="texto"]');
            const ins = '{' + b.dataset.v + '}';
            t.focus();
            t.setRangeText(ins, t.selectionStart, t.selectionEnd, 'end');
        }
        else if (acao === 'exportar') exportar();
        else if (acao === 'importar') raiz.querySelector('[data-exp="arquivo"]').click();
    });
    document.addEventListener('change', e => {
        if (e.target.matches('#wf-expansor-painel [data-exp="arquivo"]') && e.target.files[0]) importar(e.target.files[0]);
    });
    window.addEventListener('wf-prefs', () => { if (editando === null) desenhar(); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', desenhar); else desenhar();
})();
