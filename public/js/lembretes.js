/*
   Lembretes do calendário no navegador, em qualquer página (carregado pelo layout para quem está logado).
   Busca GET /calendario/lembretes a cada 5 minutos e agenda os avisos das próximas horas.
   Cada lembrete aparece uma vez só, mesmo com várias abas abertas (marca no localStorage).
   Com permissão, usa a notificação do sistema; sem ela, mostra um aviso no canto da página.
*/
(function () {
    const URL_ = document.currentScript && document.currentScript.dataset.url;
    const PAINEL = document.currentScript && document.currentScript.dataset.painel;
    if (!URL_ || window.wfLembretes) return;
    window.wfLembretes = true;

    const PREFIXO = 'wf-lembrete:';
    const timers = new Map();

    function marcar(chave) {
        try {
            if (localStorage.getItem(PREFIXO + chave)) return false;
            localStorage.setItem(PREFIXO + chave, String(Date.now()));
        } catch (e) { /* sem localStorage: avisa mesmo assim */ }
        return true;
    }

    // apaga as marcas com mais de 3 dias
    try {
        const limite = Date.now() - 3 * 86400000;
        Object.keys(localStorage).filter(k => k.startsWith(PREFIXO) && Number(localStorage.getItem(k)) < limite).forEach(k => localStorage.removeItem(k));
    } catch (e) {}

    function texto(l) {
        const q = new Date(l.quando), falta = Math.round((q - Date.now()) / 60000);
        const hora = q.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        if (l.dia_todo) return falta > 60 * 20 ? 'Amanhã (dia todo)' : 'Hoje (dia todo)';
        if (falta <= 1) return 'Agora · ' + hora;
        if (falta < 60) return 'Em ' + falta + ' min · ' + hora;
        if (falta < 60 * 24) return 'Hoje às ' + hora;
        return q.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: '2-digit' }) + ' às ' + hora;
    }

    function noCanto(titulo, corpo) {
        let caixa = document.getElementById('wf-lembretes');
        if (!caixa) {
            caixa = document.createElement('div');
            caixa.id = 'wf-lembretes';
            caixa.setAttribute('role', 'status');
            caixa.style.cssText = 'position:fixed;right:1rem;bottom:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;max-width:20rem;';
            document.body.appendChild(caixa);
        }
        const c = document.createElement('button');
        c.type = 'button';
        c.className = 'card';
        c.style.cssText = 'text-align:left;padding:.75rem 1rem;border-left:4px solid var(--prim);box-shadow:0 12px 30px rgba(0,0,0,.35);cursor:pointer;';
        const t = document.createElement('b');
        t.textContent = '🔔 ' + titulo;
        const s = document.createElement('span');
        s.style.cssText = 'display:block;font-size:.8rem;opacity:.8;margin-top:.15rem;';
        s.textContent = corpo;
        c.append(t, s);
        c.addEventListener('click', () => c.remove());
        caixa.appendChild(c);
        setTimeout(() => c.remove(), 60000);
    }

    function avisar(l) {
        timers.delete(l.chave);
        if (!marcar(l.chave)) return;
        const corpo = texto(l);
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const n = new Notification(l.titulo, { body: corpo, tag: 'wf-' + l.chave });
                n.onclick = () => { window.focus(); if (PAINEL && location.href.split('?')[0] !== PAINEL) location.href = PAINEL; n.close(); };
                return;
            } catch (e) { /* alguns celulares não deixam criar daqui: cai no aviso da página */ }
        }
        noCanto(l.titulo, corpo);
    }

    async function buscar() {
        let j;
        try {
            const r = await fetch(URL_, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            j = await r.json();
        } catch (e) { return; }
        (j.lembretes || []).forEach(l => {
            if (timers.has(l.chave)) return;
            const ms = new Date(l.avisar) - Date.now();
            if (ms <= 0) avisar(l);
            else if (ms < 6 * 3600000) timers.set(l.chave, setTimeout(() => avisar(l), ms));
        });
    }

    // o calendário mudou (evento criado, editado ou apagado): refaz a agenda de avisos
    window.addEventListener('wf-calendario-mudou', () => {
        timers.forEach(t => clearTimeout(t));
        timers.clear();
        buscar();
    });

    buscar();
    setInterval(buscar, 5 * 60000);
})();
