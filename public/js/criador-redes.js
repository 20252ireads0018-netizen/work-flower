/* Work Flower · cartão "Criador de conteúdo": abas Instagram e TikTok (servidor em RedesController).
   Fica separado do cartoes-extra.js (que cuida dos posts e do YouTube): carregue DEPOIS dele.
   Cada pessoa conecta a própria conta por OAuth; os tokens ficam só no servidor.
   A última leitura fica no cartão: b.dados.igSnap / b.dados.ttSnap = { conta, itens, em }.
   Tela em partials/criador-redes.blade.php. */
(function () {
    'use strict';
    const Q = window.WFQuadro;
    if (!Q || typeof Q.metodos !== 'function') return;
    // base /redes (vem da tag do script, porque o site pode estar numa subpasta)
    const URL_REDES = (document.currentScript && document.currentScript.dataset.redes) || '';
    const SNAP = { ig: 'igSnap', tt: 'ttSnap' }, ROTA = { ig: 'instagram', tt: 'tiktok' }, NOME = { ig: 'Instagram', tt: 'TikTok' };
    const curto = n => (n == null ? '—' : n >= 1e6 ? (n / 1e6).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi' : n >= 1e4 ? (n / 1e3).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil' : Number(n).toLocaleString('pt-BR'));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const extras = {
        /** Estado da tela das redes, por cartão (pelo proxy do Alpine, para reagir). */
        crRdUI(b) { this.wfRdUI[b.id] ??= { carregando: false, erro: '', conectar: '', configurar: false }; return this.wfRdUI[b.id]; },
        crMetrica(b) { return ['yt', 'ig', 'tt'].includes(this.crUI(b).ver); },
        crRedeAberta(b) { return !!SNAP[this.crUI(b).ver]; },
        crRedeNome(b) { return NOME[this.crUI(b).ver] || ''; },
        crVer(b, ver) {
            const ui = this.crUI(b), rd = this.crRdUI(b);
            ui.ver = ver; ui.aberto = null;
            Object.assign(rd, { erro: '', conectar: '', configurar: false });
            if (SNAP[ver] && !b.dados[SNAP[ver]]) this.crRede(b, ver);
        },
        crSnap(b) { const k = SNAP[this.crUI(b).ver]; return k ? b.dados[k] || null : null; },
        async crRede(b, ver, forcar) {
            const rd = this.crRdUI(b);
            if (!URL_REDES) { rd.erro = 'As métricas de redes não estão disponíveis nesta página.'; return; }
            Object.assign(rd, { carregando: true, erro: '', conectar: '', configurar: false });
            try {
                const r = await fetch(URL_REDES + '/' + ROTA[ver] + '/metricas' + (forcar ? '?forcar=1' : ''), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-WF-Dono': '' } });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) b.dados[SNAP[ver]] = { conta: j.conta, itens: j.posts || j.videos || [], em: j.em };
                else {
                    rd.erro = j.erro || 'Não deu para buscar as métricas agora. Tente de novo em alguns minutos.';
                    rd.conectar = j.conectar ? j.conectar + '?voltar=' + encodeURIComponent(location.href) : '';
                    rd.configurar = j.configurado === false;
                }
            } catch (e) { rd.erro = 'Sem conexão com o servidor.'; }
            rd.carregando = false;
        },
        async crRedeSair(b) {
            const ver = this.crUI(b).ver;
            if (!SNAP[ver] || !confirm(`Desconectar a sua conta do ${NOME[ver]} deste site? Para ver as métricas de novo, será preciso conectar outra vez.`)) return;
            try {
                await fetch(URL_REDES + '/' + ROTA[ver], { method: 'DELETE', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-WF-Dono': '' } });
                delete b.dados[SNAP[ver]];
                this.crRede(b, ver);
            } catch (e) { this.aviso('Sem conexão com o servidor.'); }
        },
        crRdNum(n) { return curto(n); },
        crRdQuando(b) { const s = this.crSnap(b); return s ? new Date(s.em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : ''; },
        crRdColunas(b) { return this.crUI(b).ver === 'ig' ? ['Curtidas', 'Coment.', 'Alcance', 'Views'] : ['Views', 'Curtidas', 'Coment.', 'Compart.']; },
        crRdValores(b, v) { return this.crUI(b).ver === 'ig' ? [v.curtidas, v.comentarios, v.alcance, v.views] : [v.views, v.curtidas, v.comentarios, v.compart]; },
        crRdResumo(b) {
            const c = this.crSnap(b)?.conta || {};
            return this.crUI(b).ver === 'ig'
                ? [[c.seguidores, 'seguidores'], [c.posts, 'posts'], [c.seguindo, 'seguindo']]
                : [[c.seguidores, 'seguidores'], [c.curtidas, 'curtidas'], [c.videos, 'vídeos']];
        },
    };

    const base = Q.metodos;
    Q.metodos = function (...args) {
        const m = base.apply(this, args);
        // o crUI do cartoes-extra devolve o objeto cru na primeira vez (valor de um ??=); aqui devolve sempre
        // pelo proxy do Alpine, senão o botão "Posts" não acompanha a troca de aba
        const crUI = m.crUI;
        if (typeof crUI === 'function') m.crUI = function (b) { crUI.call(this, b); return this.wfCxUI[b.id]; };
        return Object.assign(m, extras, { wfRdUI: {} });
    };
})();
