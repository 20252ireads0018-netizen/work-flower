/*
   Calendário do painel (resources/views/painel/index.blade.php, #janela-calendario).
   - Mês em grade, dia escolhido com a lista de eventos e tarefas com prazo, formulário de evento.
   - Janela arrastável pelo cabeçalho e redimensionável pelo canto (desktop), como a de tarefas.
   - Dados: GET /calendario?de=&ate= e POST/PUT/DELETE /calendario/eventos (CalendarioController).
   Datas são "hora de parede" no formato YYYY-MM-DDTHH:MM, sem fuso.
*/
(function () {
    const raiz = document.getElementById('janela-calendario');
    if (!raiz || raiz.dataset.pronto) return;
    raiz.dataset.pronto = '1';

    const BASE = raiz.dataset.base;
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || raiz.dataset.csrf || '';
    const MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    const SEMANA = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
    const DIAS = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
    const CORES = ['#2f6fc7', '#2dd4bf', '#4ade80', '#f2c230', '#fb923c', '#f472b6', '#a78bfa', '#ef4444'];
    const LEMBRETES = [[null, 'Sem lembrete'], [0, 'Na hora'], [5, '5 min antes'], [10, '10 min antes'], [15, '15 min antes'], [30, '30 min antes'],
        [60, '1 hora antes'], [120, '2 horas antes'], [1440, '1 dia antes'], [2880, '2 dias antes'], [10080, '1 semana antes']];
    const REPETE = [['nao', 'Não repete'], ['diario', 'Todo dia'], ['semanal', 'Toda semana'], ['mensal', 'Todo mês'], ['anual', 'Todo ano']];
    const K = { aberto: 'painel.cal.aberto', pos: 'painel.cal.pos', tam: 'painel.cal.tam' };

    const ler = (k, p) => { try { const v = localStorage.getItem(k); return v === null ? p : JSON.parse(v); } catch (e) { return p; } };
    const gravar = (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} };
    const dois = n => String(n).padStart(2, '0');
    const ymd = d => d.getFullYear() + '-' + dois(d.getMonth() + 1) + '-' + dois(d.getDate());
    const hm = s => (s || '').slice(11, 16);
    const el = (tag, attrs = {}, ...filhos) => {
        const n = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === null || v === undefined || v === false) continue;
            if (k === 'class') n.className = v;
            else if (k === 'text') n.textContent = v;
            else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
            else n.setAttribute(k, v === true ? '' : v);
        }
        filhos.flat().forEach(f => f !== null && f !== undefined && n.append(f));
        return n;
    };

    const $ = s => raiz.querySelector(s);
    const corpo = $('#cal-corpo'), grade = $('#cal-grade'), rotulo = $('#cal-mes'), dia = $('#cal-dia'), form = $('#cal-form'), aviso = $('#cal-aviso');

    const hoje = new Date();
    const st = { mes: new Date(hoje.getFullYear(), hoje.getMonth(), 1), sel: ymd(hoje), eventos: [], tarefas: [], editando: null, carregando: 0 };

    /* ---------- servidor ---------- */
    async function pedir(url, metodo = 'GET', corpoJson) {
        const r = await fetch(url, {
            method: metodo, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: corpoJson ? JSON.stringify(corpoJson) : undefined,
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) {
            const erros = j.errors ? Object.values(j.errors).flat().join(' ') : '';
            throw new Error(erros || j.message || 'Não consegui falar com o servidor.');
        }
        return j;
    }

    function inicioGrade() {
        const d = new Date(st.mes);
        d.setDate(1 - d.getDay());
        return d;
    }

    async function carregar() {
        const ini = inicioGrade(), fim = new Date(ini);
        fim.setDate(fim.getDate() + 41);
        const n = ++st.carregando;
        try {
            const j = await pedir(`${BASE}?de=${ymd(ini)}&ate=${ymd(fim)}`);
            if (n !== st.carregando) return; // chegou depois de uma troca de mês mais nova
            st.eventos = j.eventos || [];
            st.tarefas = j.tarefas || [];
            mostrarAviso('');
        } catch (e) {
            mostrarAviso(e.message);
        }
        desenhar();
    }

    function mostrarAviso(t) {
        aviso.textContent = t;
        aviso.hidden = !t;
    }

    /* ---------- desenho ---------- */
    function doDia(d) {
        return {
            eventos: st.eventos.filter(e => e.ocorrencia.slice(0, 10) === d),
            tarefas: st.tarefas.filter(t => t.prazo === d),
        };
    }

    function desenhar() {
        rotulo.textContent = MESES[st.mes.getMonth()] + ' ' + st.mes.getFullYear();
        grade.replaceChildren(...SEMANA.map(s => el('div', { class: 'cal-sem', text: s })));
        const d = inicioGrade(), h = ymd(new Date());
        for (let i = 0; i < 42; i++) {
            const chave = ymd(d), lista = doDia(chave), itens = [...lista.eventos.map(e => ({ t: e.titulo, cor: e.cor })), ...lista.tarefas.map(t => ({ t: t.titulo, cor: null }))];
            const btn = el('button', {
                type: 'button', class: 'cal-d' + (d.getMonth() !== st.mes.getMonth() ? ' fora' : '') + (chave === h ? ' hoje' : '') + (chave === st.sel ? ' sel' : ''),
                'aria-label': d.getDate() + ' de ' + MESES[d.getMonth()] + (itens.length ? ', ' + itens.length + (itens.length > 1 ? ' itens' : ' item') : ''),
                'aria-pressed': String(chave === st.sel),
                onclick: () => { st.sel = chave; st.editando = null; desenhar(); },
                ondblclick: () => abrirForm(null, chave),
            }, el('span', { class: 'cal-n', text: String(d.getDate()) }),
                el('span', { class: 'cal-itens' }, itens.slice(0, 3).map(x => el('span', { class: 'cal-chip' + (x.cor ? '' : ' tarefa'), style: x.cor ? '--c:' + x.cor : null, text: x.t })),
                    itens.length > 3 ? el('span', { class: 'cal-mais', text: '+' + (itens.length - 3) }) : null));
            grade.append(btn);
            d.setDate(d.getDate() + 1);
        }
        desenharDia();
    }

    function quandoTexto(e) {
        if (e.dia_todo) return 'Dia todo' + (e.ocorrencia_fim ? ' · até ' + e.ocorrencia_fim.slice(8, 10) + '/' + e.ocorrencia_fim.slice(5, 7) : '');
        let t = hm(e.ocorrencia);
        if (e.ocorrencia_fim) t += ' – ' + (e.ocorrencia_fim.slice(0, 10) === e.ocorrencia.slice(0, 10) ? hm(e.ocorrencia_fim) : e.ocorrencia_fim.slice(8, 10) + '/' + e.ocorrencia_fim.slice(5, 7) + ' ' + hm(e.ocorrencia_fim));
        return t;
    }

    function desenharDia() {
        const [a, m, dd] = st.sel.split('-').map(Number), data = new Date(a, m - 1, dd);
        const lista = doDia(st.sel);
        const titulo = el('div', { class: 'flex items-center gap-2' },
            el('h3', { class: 'text-sm font-semibold', text: DIAS[data.getDay()] + ', ' + dd + ' de ' + MESES[m - 1] }),
            el('button', { type: 'button', class: 'mini-btn ml-auto', title: 'Novo evento neste dia', 'aria-label': 'Novo evento neste dia', onclick: () => abrirForm(null, st.sel) }, '+'));
        const itens = [];
        lista.eventos.forEach(e => itens.push(el('li', { class: 'cal-ev', style: '--c:' + e.cor },
            el('button', { type: 'button', class: 'cal-ev-btn', title: 'Editar', onclick: () => abrirForm(st.eventos.find(x => x.id === e.id) || e) },
                el('span', { class: 'cal-ev-t', text: e.titulo }),
                el('span', { class: 'cal-ev-q', text: quandoTexto(e) + (e.repete !== 'nao' ? ' · ' + REPETE.find(r => r[0] === e.repete)[1].toLowerCase() : '') + (e.lembrete_min !== null ? ' · 🔔' + (e.lembrar_email ? ' ✉' : '') : '') })))));
        lista.tarefas.forEach(t => itens.push(el('li', { class: 'cal-ev tarefa' },
            el(t.url ? 'a' : 'span', { class: 'cal-ev-btn', href: t.url },
                el('span', { class: 'cal-ev-t' + (t.feita ? ' feita' : ''), text: t.titulo }),
                el('span', { class: 'cal-ev-q', text: 'Prazo de tarefa · ' + t.area + (t.feita ? ' · concluída' : '') })))));
        dia.replaceChildren(titulo, itens.length ? el('ul', { class: 'space-y-1.5 mt-2' }, itens) : el('p', { class: 'text-xs texto-2 mt-1', text: 'Nada neste dia. Clique em + ou dê dois cliques no dia para criar um evento.' }));
        dia.hidden = !!st.editando;
        form.hidden = !st.editando;
    }

    /* ---------- formulário ---------- */
    function abrirForm(ev, data) {
        const agora = new Date(), hora = dois(Math.min(agora.getHours() + 1, 23)) + ':00';
        st.editando = ev ? { ...ev } : { id: null, titulo: '', descricao: '', inicio: (data || st.sel) + 'T' + hora, fim: null, dia_todo: false, cor: CORES[0], repete: 'nao', repete_ate: null, lembrete_min: 15, lembrar_email: false };
        const e = st.editando;
        const campo = (rot, controle, cls = '') => el('label', { class: 'cal-campo ' + cls }, el('span', { text: rot }), controle);
        const sel = (nome, ops, v) => el('select', { name: nome, class: 'campo' }, ops.map(([val, t]) => el('option', { value: val === null ? '' : String(val), selected: String(v ?? '') === String(val ?? '') }, t)));

        const fTitulo = el('input', { name: 'titulo', class: 'campo', maxlength: '120', required: true, value: e.titulo, placeholder: 'Ex.: Reunião com cliente' });
        const fDiaTodo = el('input', { type: 'checkbox', name: 'dia_todo', checked: e.dia_todo });
        const fIniD = el('input', { type: 'date', name: 'ini_d', class: 'campo', required: true, value: e.inicio.slice(0, 10) });
        const fIniH = el('input', { type: 'time', name: 'ini_h', class: 'campo', value: hm(e.inicio) || '09:00' });
        const fFimD = el('input', { type: 'date', name: 'fim_d', class: 'campo', value: e.fim ? e.fim.slice(0, 10) : '' });
        const fFimH = el('input', { type: 'time', name: 'fim_h', class: 'campo', value: e.fim ? hm(e.fim) : '' });
        const fRepete = sel('repete', REPETE, e.repete);
        const fAte = el('input', { type: 'date', name: 'repete_ate', class: 'campo', value: e.repete_ate || '' });
        const fLembrete = sel('lembrete_min', LEMBRETES, e.lembrete_min);
        const fEmail = el('input', { type: 'checkbox', name: 'lembrar_email', checked: e.lembrar_email });
        const fDesc = el('textarea', { name: 'descricao', class: 'campo', rows: '2', maxlength: '2000', placeholder: 'Detalhes (opcional)' });
        fDesc.value = e.descricao || '';
        const cores = el('div', { class: 'flex flex-wrap gap-1.5', role: 'radiogroup', 'aria-label': 'Cor' }, CORES.map(c =>
            el('label', { class: 'cal-cor', style: '--c:' + c, title: c }, el('input', { type: 'radio', name: 'cor', value: c, checked: (e.cor || CORES[0]) === c, 'aria-label': 'Cor ' + c }))));

        const linhaAte = campo('Repetir até (opcional)', fAte);
        const linhaEmail = el('label', { class: 'flex items-center gap-2 text-xs' }, fEmail, 'Avisar também por e-mail');
        const atualizar = () => {
            fIniH.hidden = fFimH.hidden = fDiaTodo.checked;
            linhaAte.hidden = fRepete.value === 'nao';
            fEmail.disabled = fLembrete.value === '';
            if (fEmail.disabled) fEmail.checked = false;
            linhaEmail.style.opacity = fEmail.disabled ? '.5' : '';
        };
        [fDiaTodo, fRepete, fLembrete].forEach(x => x.addEventListener('change', atualizar));

        const erro = el('p', { class: 'text-xs', role: 'alert', style: 'color:#ef4444', hidden: true });
        const salvar = el('button', { type: 'submit', class: 'btn !py-1' }, e.id ? 'Salvar' : 'Criar evento');
        form.replaceChildren(
            el('h3', { class: 'text-sm font-semibold', text: e.id ? 'Editar evento' + (e.repete !== 'nao' ? ' (vale para todas as repetições)' : '') : 'Novo evento' }),
            campo('Título', fTitulo),
            el('label', { class: 'flex items-center gap-2 text-xs' }, fDiaTodo, 'Dia todo'),
            el('div', { class: 'cal-par' }, campo('Começa', fIniD), campo('Hora', fIniH)),
            el('div', { class: 'cal-par' }, campo('Termina (opcional)', fFimD), campo('Hora', fFimH)),
            el('div', { class: 'cal-par' }, campo('Repetir', fRepete), linhaAte),
            campo('Lembrete', fLembrete),
            linhaEmail,
            el('p', { class: 'text-[11px] texto-2', text: 'Eventos de dia todo avisam contando a partir das 9h. O aviso no navegador aparece com o Work Flower aberto em alguma aba.' }),
            campo('Cor', cores),
            campo('Descrição', fDesc),
            erro,
            el('div', { class: 'flex flex-wrap items-center gap-2' }, salvar,
                el('button', { type: 'button', class: 'btn-sec !py-1', onclick: () => { st.editando = null; desenharDia(); } }, 'Cancelar'),
                e.id ? el('button', { type: 'button', class: 'btn-sec !py-1 ml-auto', style: 'color:#ef4444', onclick: () => apagar(e) }, 'Apagar') : null),
        );
        atualizar();
        form.onsubmit = async ev2 => {
            ev2.preventDefault();
            const dados = {
                titulo: fTitulo.value.trim(),
                descricao: fDesc.value.trim() || null,
                dia_todo: fDiaTodo.checked,
                inicio: fIniD.value + 'T' + (fDiaTodo.checked ? '00:00' : (fIniH.value || '00:00')),
                fim: fFimD.value ? fFimD.value + 'T' + (fDiaTodo.checked ? '00:00' : (fFimH.value || '23:59')) : (!fDiaTodo.checked && fFimH.value ? fIniD.value + 'T' + fFimH.value : null),
                cor: (form.querySelector('input[name=cor]:checked') || {}).value || CORES[0],
                repete: fRepete.value,
                repete_ate: fRepete.value !== 'nao' && fAte.value ? fAte.value : null,
                lembrete_min: fLembrete.value === '' ? null : Number(fLembrete.value),
                lembrar_email: fEmail.checked,
            };
            if (!dados.titulo) { fTitulo.focus(); return; }
            if (dados.fim && dados.fim < dados.inicio) { erro.textContent = 'O fim precisa ser depois do começo.'; erro.hidden = false; return; }
            salvar.disabled = true;
            try {
                await pedir(e.id ? `${BASE}/eventos/${e.id}` : `${BASE}/eventos`, e.id ? 'PUT' : 'POST', dados);
                if (dados.lembrete_min !== null) pedirPermissao();
                st.sel = dados.inicio.slice(0, 10);
                const [a, m] = st.sel.split('-').map(Number);
                st.mes = new Date(a, m - 1, 1);
                st.editando = null;
                window.dispatchEvent(new CustomEvent('wf-calendario-mudou'));
                await carregar();
            } catch (x) {
                erro.textContent = x.message;
                erro.hidden = false;
            } finally {
                salvar.disabled = false;
            }
        };
        if (corpo.hidden) definirAberto(true);
        desenharDia();
        fTitulo.focus();
    }

    async function apagar(e) {
        if (!confirm(e.repete !== 'nao' ? 'Apagar este evento e todas as repetições dele?' : 'Apagar este evento?')) return;
        try {
            await pedir(`${BASE}/eventos/${e.id}`, 'DELETE');
            st.editando = null;
            window.dispatchEvent(new CustomEvent('wf-calendario-mudou'));
            await carregar();
        } catch (x) {
            mostrarAviso(x.message);
        }
    }

    /* ---------- avisos do navegador ---------- */
    const btnAvisos = $('#cal-avisos');
    function verPermissao() {
        btnAvisos.hidden = !('Notification' in window) || Notification.permission !== 'default';
    }
    function pedirPermissao() {
        if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission().then(verPermissao, verPermissao);
    }
    btnAvisos.addEventListener('click', pedirPermissao);
    verPermissao();

    /* ---------- navegação ---------- */
    const mudarMes = n => { st.mes = new Date(st.mes.getFullYear(), st.mes.getMonth() + n, 1); st.editando = null; carregar(); };
    $('#cal-ant').addEventListener('click', () => mudarMes(-1));
    $('#cal-prox').addEventListener('click', () => mudarMes(1));
    $('#cal-hoje').addEventListener('click', () => { const h = new Date(); st.mes = new Date(h.getFullYear(), h.getMonth(), 1); st.sel = ymd(h); st.editando = null; carregar(); });
    $('#cal-novo').addEventListener('click', () => abrirForm(null, st.sel));
    raiz.addEventListener('keydown', e => { if (e.key === 'Escape' && st.editando) { st.editando = null; desenharDia(); } });

    /* ---------- recolher ---------- */
    const botao = $('#cal-alternar'), seta = botao.querySelector('svg');
    function definirAberto(aberto, salvar = true) {
        corpo.hidden = !aberto;
        const r = aberto ? 'Recolher calendário' : 'Expandir calendário';
        botao.setAttribute('aria-expanded', String(aberto));
        botao.setAttribute('aria-label', r);
        botao.title = r;
        seta.style.rotate = aberto ? '180deg' : '0deg';
        raiz.classList.toggle('recolhido', !aberto);
        if (salvar) gravar(K.aberto, aberto);
    }
    botao.addEventListener('click', () => definirAberto(corpo.hidden));
    definirAberto(ler(K.aberto, true), false);

    /* ---------- arrastar e redimensionar (desktop, a partir de 1024px) ---------- */
    const desktop = window.matchMedia('(min-width: 1024px)');
    const cab = $('#cal-cabecalho'), alca = $('#cal-redim');
    let pos = ler(K.pos, { x: 0, y: 0 }), tam = ler(K.tam, null), arrasto = null, redim = null;

    function aplicar() {
        raiz.style.translate = desktop.matches ? pos.x + 'px ' + pos.y + 'px' : '';
        raiz.style.width = desktop.matches && tam ? tam.w + 'px' : '';
        raiz.style.height = desktop.matches && tam && !corpo.hidden ? tam.h + 'px' : '';
        raiz.classList.toggle('com-altura', !!(desktop.matches && tam));
    }
    function limitar(x, y) {
        const r = raiz.getBoundingClientRect(), l0 = r.left - pos.x, t0 = r.top - pos.y;
        return {
            x: Math.min(Math.max(x, -l0), Math.max(-l0, window.innerWidth - r.width - l0)),
            y: Math.min(Math.max(y, -t0), Math.max(-t0, window.innerHeight - 56 - t0)),
        };
    }
    cab.addEventListener('pointerdown', e => {
        if (!desktop.matches || e.button !== 0 || e.target.closest('button')) return;
        arrasto = { px: e.clientX, py: e.clientY, x: pos.x, y: pos.y };
        cab.setPointerCapture(e.pointerId);
        cab.style.cursor = 'grabbing';
        raiz.style.boxShadow = '0 20px 40px rgba(0, 0, 0, .45)';
        e.preventDefault();
    });
    cab.addEventListener('pointermove', e => {
        if (!arrasto) return;
        pos = limitar(arrasto.x + e.clientX - arrasto.px, arrasto.y + e.clientY - arrasto.py);
        aplicar();
    });
    const soltar = () => {
        if (!arrasto) return;
        arrasto = null;
        cab.style.cursor = '';
        raiz.style.boxShadow = '';
        gravar(K.pos, pos);
    };
    cab.addEventListener('pointerup', soltar);
    cab.addEventListener('pointercancel', soltar);
    // clique duplo no cabeçalho: volta ao lugar e ao tamanho originais
    cab.addEventListener('dblclick', e => {
        if (e.target.closest('button')) return;
        pos = { x: 0, y: 0 };
        tam = null;
        gravar(K.pos, pos);
        gravar(K.tam, null);
        aplicar();
    });

    alca.addEventListener('pointerdown', e => {
        if (!desktop.matches || e.button !== 0) return;
        const r = raiz.getBoundingClientRect();
        redim = { px: e.clientX, py: e.clientY, w: r.width, h: r.height };
        alca.setPointerCapture(e.pointerId);
        e.preventDefault();
    });
    alca.addEventListener('pointermove', e => {
        if (!redim) return;
        const r = raiz.getBoundingClientRect();
        tam = {
            w: Math.round(Math.min(Math.max(redim.w + e.clientX - redim.px, 280), window.innerWidth - r.left - 8)),
            h: Math.round(Math.min(Math.max(redim.h + e.clientY - redim.py, 320), Math.max(320, window.innerHeight - r.top - 8))),
        };
        aplicar();
    });
    const soltarRedim = () => { if (!redim) return; redim = null; gravar(K.tam, tam); };
    alca.addEventListener('pointerup', soltarRedim);
    alca.addEventListener('pointercancel', soltarRedim);
    alca.addEventListener('dblclick', () => { tam = null; gravar(K.tam, null); aplicar(); });

    function reajustar() {
        aplicar();
        if (desktop.matches) { pos = limitar(pos.x, pos.y); aplicar(); }
    }
    desktop.addEventListener('change', reajustar);
    window.addEventListener('resize', reajustar);
    new MutationObserver(aplicar).observe(corpo, { attributes: true, attributeFilter: ['hidden'] });
    reajustar();

    carregar();
})();
