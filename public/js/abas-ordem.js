/* Work Flower · ordem das abas fixas das áreas (Corpo, Mente, Carteira, Negócios), arrastando o círculo da aba.
   A ordem fica nas preferências da conta (WFPrefs: ordemAbas[area] = [ids]) e vale em qualquer aparelho.
   Uso no Blade, dentro do x-for das abas:
     <nav x-init="WFAbasOrdem.aplicar('mente', abas)">
       <button draggable="true" @dragstart="WFAbasOrdem.ini($event, a)" @dragend="WFAbasOrdem.fim()"
               @dragover="WFAbasOrdem.sobre($event)" @drop="WFAbasOrdem.soltar($event, 'mente', abas, a)"> */
(function () {
    'use strict';
    if (window.WFAbasOrdem) return;
    let arrastando = null; // id da aba que está sendo arrastada

    const salva = area => {
        const o = window.WFPrefs && window.WFPrefs.ler().ordemAbas;
        return o && Array.isArray(o[area]) ? o[area] : null;
    };

    window.WFAbasOrdem = {
        /** Põe as abas (array do Alpine, mexido no lugar) na ordem salva; abas novas, fora da lista, vão para o fim. */
        aplicar(area, abas) {
            const ordem = salva(area);
            if (!ordem || !Array.isArray(abas)) return;
            const pos = id => { const i = ordem.indexOf(id); return i < 0 ? Infinity : i; };
            const nova = abas.map((a, i) => ({ a, i })).sort((x, y) => (pos(x.a.id) - pos(y.a.id)) || (x.i - y.i)).map(x => x.a);
            if (nova.some((a, i) => a !== abas[i])) abas.splice(0, abas.length, ...nova);
        },
        ini(e, a) {
            arrastando = a.id;
            try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', a.nome || a.id); } catch (_) { /* navegador antigo */ }
            e.currentTarget.classList.add('wf-aba-arrastando');
        },
        fim() {
            arrastando = null;
            document.querySelectorAll('.wf-aba-arrastando, .wf-aba-alvo').forEach(el => el.classList.remove('wf-aba-arrastando', 'wf-aba-alvo'));
        },
        sobre(e) {
            if (!arrastando) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            document.querySelectorAll('.wf-aba-alvo').forEach(el => el !== e.currentTarget && el.classList.remove('wf-aba-alvo'));
            e.currentTarget.classList.add('wf-aba-alvo');
        },
        /** Solta em cima de outra aba: entra antes dela (metade esquerda) ou depois (metade direita) e salva a ordem. */
        soltar(e, area, abas, alvo) {
            const id = arrastando;
            this.fim();
            if (!id || id === alvo.id || !Array.isArray(abas)) return;
            e.preventDefault();
            const de = abas.findIndex(x => x.id === id);
            if (de < 0) return;
            const r = e.currentTarget.getBoundingClientRect();
            const depois = e.clientX > r.left + r.width / 2;
            const [a] = abas.splice(de, 1);
            const para = abas.findIndex(x => x.id === alvo.id);
            abas.splice(depois ? para + 1 : para, 0, a);
            if (window.WFPrefs) {
                const todas = Object.assign({}, window.WFPrefs.ler().ordemAbas || {});
                todas[area] = abas.map(x => x.id);
                window.WFPrefs.salvar({ ordemAbas: todas });
            }
        },
    };

    const css = document.createElement('style');
    css.textContent = '.wf-aba-arrastando{opacity:.45}.wf-aba-alvo .circ-bola{outline:2px dashed var(--prim);outline-offset:3px}';
    document.head.appendChild(css);
})();
