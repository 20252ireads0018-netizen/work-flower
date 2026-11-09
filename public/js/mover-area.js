/* Work Flower · mover cartões para outra área (carregado pelo layout para quem está logado)
   window.WFMoverArea.abrir({ de, aba, abaNome, ids, quantos, antes, depois }) abre a janela:
   escolhe a área e a aba de destino (no Diverso e nas áreas criadas dá para criar uma aba nova) e o servidor
   (App\Support\MoverCartoes) leva os cartões com os dados, a posição, as imagens e os PDFs.
   Chamado pelo quadro.js (clique direito no cartão ou no quadro e a barra de cartões selecionados). */
(function () {
    'use strict';
    if (window.WFMoverArea) return;
    const base = () => (document.querySelector('meta[name="wf-areas-url"]')?.content || '/areas-config').replace(/\/$/, '');
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    async function pedir(url, metodo = 'GET', corpo = null) {
        const r = await fetch(url, {
            method: metodo, credentials: 'same-origin',
            // X-WF-Dono vazio: mover vale só para os dados da própria conta
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-WF-Dono': '' },
            body: corpo ? JSON.stringify(corpo) : null,
        });
        let j = {};
        try { j = await r.json(); } catch { /* sem JSON */ }
        if (r.status === 419 || r.status === 401) { location.reload(); throw new Error('Sessão expirada.'); }
        if (!r.ok) throw new Error((j.errors && Object.values(j.errors).flat()[0]) || j.message || 'Não deu certo agora. Tente de novo.');
        return j;
    }

    let aberto = null;
    // depois de mover, fechar a janela recarrega a página: o que está na tela é o estado de antes
    function fechar() {
        if (!aberto) return;
        const feito = aberto.dataset.feito === '1';
        aberto.remove(); aberto = null; document.removeEventListener('keydown', teclaEsc);
        if (feito) location.reload();
    }
    function teclaEsc(e) { if (e.key === 'Escape') fechar(); }

    async function abrir(op) {
        fechar();
        const fundo = document.createElement('div');
        fundo.className = 'wf-mover-fundo';
        fundo.innerHTML = `<div class="card wf-mover p-5 space-y-4" role="dialog" aria-modal="true" aria-labelledby="wf-mover-titulo">
            <h2 id="wf-mover-titulo" class="titulo text-lg">Mover para outra área</h2>
            <p class="text-sm texto-2">${op.ids ? (op.quantos === 1 ? 'O cartão vai' : `Os ${op.quantos} cartões vão`) : `Todos os cartões da aba “${esc(op.abaNome)}” vão`}
               com o conteúdo, a posição, a cor, as imagens e os PDFs. Cartões fixos da área (os que vêm com ela) ficam onde estão.</p>
            <div data-corpo class="text-sm texto-2">Carregando as áreas…</div>
            <p data-erro class="text-sm" style="color:#f87171" hidden></p>
            <div class="flex flex-wrap gap-2 justify-end">
                <button type="button" class="btn-sec" data-fechar>Cancelar</button>
                <button type="button" class="btn" data-ok disabled>Mover</button>
            </div>
        </div>`;
        document.body.appendChild(fundo);
        aberto = fundo;
        document.addEventListener('keydown', teclaEsc);
        fundo.addEventListener('click', e => { if (e.target === fundo || e.target.closest('[data-fechar]')) fechar(); });
        const corpo = fundo.querySelector('[data-corpo]'), erro = fundo.querySelector('[data-erro]'), ok = fundo.querySelector('[data-ok]');
        const falhar = m => { erro.textContent = m; erro.hidden = false; };

        let areas;
        try { areas = (await pedir(base() + '/destinos')).areas || []; } catch (e) { corpo.textContent = ''; falhar(e.message); return; }
        areas = areas.filter(a => a.quadro || a.abas.length);
        if (!areas.length) { corpo.textContent = 'Não há outra área para onde mover.'; return; }

        corpo.innerHTML = `<div class="grid gap-3 sm:grid-cols-2">
            <div><label class="rotulo" for="wf-mover-area">Área</label><select id="wf-mover-area" class="campo"></select></div>
            <div><label class="rotulo" for="wf-mover-aba">Aba</label><select id="wf-mover-aba" class="campo"></select></div>
            <div class="sm:col-span-2" data-nova hidden><label class="rotulo" for="wf-mover-nome">Nome da aba nova</label>
                <input id="wf-mover-nome" class="campo" maxlength="40"></div>
        </div>`;
        const selArea = corpo.querySelector('#wf-mover-area'), selAba = corpo.querySelector('#wf-mover-aba');
        const caixaNova = corpo.querySelector('[data-nova]'), nome = corpo.querySelector('#wf-mover-nome');
        nome.value = op.abaNome || '';
        selArea.innerHTML = areas.map(a => `<option value="${esc(a.id)}">${esc(a.nome)}${a.oculta ? ' (escondida)' : ''}</option>`).join('');
        // começa numa área diferente da atual (de preferência o Diverso)
        const inicial = areas.find(a => a.id === 'diverso' && a.id !== op.de) || areas.find(a => a.id !== op.de) || areas[0];
        selArea.value = inicial.id;
        const desenharAbas = () => {
            const a = areas.find(x => x.id === selArea.value);
            const abas = a.abas.filter(x => !(a.id === op.de && x.id === op.aba));
            selAba.innerHTML = (a.quadro ? '<option value="">+ Aba nova</option>' : '')
                + abas.map(x => `<option value="${esc(x.id)}">${esc(x.nome)}</option>`).join('');
            // nas áreas do sistema, o Quadro livre é o destino natural
            if (!a.quadro && abas.some(x => x.id === 'livre')) selAba.value = 'livre';
            caixaNova.hidden = selAba.value !== '';
            ok.disabled = !a.quadro && !abas.length;
        };
        selArea.addEventListener('change', desenharAbas);
        selAba.addEventListener('change', () => { caixaNova.hidden = selAba.value !== ''; });
        desenharAbas();

        ok.addEventListener('click', async () => {
            erro.hidden = true;
            ok.disabled = true; ok.textContent = 'Movendo…';
            try {
                if (op.antes) await op.antes();
                const r = await pedir(base() + '/mover', 'POST', {
                    de: op.de, aba: op.aba, ids: op.ids || null, para: selArea.value,
                    para_aba: selAba.value || null, nome: selAba.value ? null : (nome.value.trim() || op.abaNome || 'Aba'),
                });
                if (op.depois) op.depois();
                fundo.dataset.feito = '1';
                const destino = areas.find(x => x.id === selArea.value);
                const ficaram = (r.ficaram || []).length;
                corpo.innerHTML = `<p class="text-sm">${r.movidos === 1 ? '1 cartão movido' : `${r.movidos} cartões movidos`} para ${esc(destino ? destino.nome : 'a outra área')}.</p>`
                    + (ficaram ? `<p class="text-sm texto-2">${ficaram === 1 ? '1 cartão ficou' : `${ficaram} cartões ficaram`} aqui porque a área de destino não tem esse tipo de cartão: ${(r.ficaram || []).map(f => esc(f.titulo)).join(', ')}.</p>` : '');
                ok.textContent = 'Ir para lá';
                ok.disabled = false;
                const cancelar = fundo.querySelector('[data-fechar]');
                cancelar.textContent = 'Ficar aqui';
                const ir = () => { location.href = r.url || location.href; };
                ok.replaceWith(ok.cloneNode(true));
                fundo.querySelector('[data-ok]').addEventListener('click', ir);
            } catch (e) {
                falhar(e.message);
                ok.disabled = false; ok.textContent = 'Mover';
            }
        });
    }

    const st = document.createElement('style');
    st.textContent = `.wf-mover-fundo{position:fixed;inset:0;z-index:2147483100;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:16px}
        .wf-mover{width:min(32rem,100%);max-height:calc(100vh - 32px);overflow:auto;background:var(--superficie)}`;
    document.head.appendChild(st);

    window.WFMoverArea = { abrir, fechar };
})();
