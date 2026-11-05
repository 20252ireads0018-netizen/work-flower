/* Work Flower · envio de imagens e GIFs grandes (até 25 MB) para os fundos (área, abas e cartões).
   window.wfEnviarMidia(arquivo, aoProgresso?) → Promise<{ token, url }>
   Manda em pedaços de 4 MB em base64 (o tamanho que passa pelo mod_security e pelo limite de POST da hospedagem).
   O endereço base vem do data-url desta tag (o site pode estar numa subpasta). */
(function () {
    'use strict';
    if (window.wfEnviarMidia) return;
    const BASE = ((document.currentScript && document.currentScript.dataset.url) || '/midia').replace(/\/$/, '');
    const MAX = 25 * 1024 * 1024;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function pedir(url, metodo, corpo) {
        let ultimo;
        for (let t = 0; t < 3; t++) {
            let r;
            try {
                r = await fetch(url, {
                    method: metodo, credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                    body: corpo ? JSON.stringify(corpo) : undefined,
                });
            } catch (e) { ultimo = new Error('Sem conexão com o servidor.'); await new Promise(ok => setTimeout(ok, 1200 * (t + 1))); continue; }
            if (r.status === 401 || r.status === 419) { location.reload(); throw new Error('sessão'); }
            const j = await r.json().catch(() => ({}));
            if (r.ok) return j;
            if (r.status === 422) throw new Error(Object.values(j.errors || {})[0]?.[0] || 'Não deu para enviar esse arquivo.');
            if (r.status === 413) throw new Error('O servidor recusou o tamanho do pedaço. Tente de novo.');
            ultimo = new Error('Não consegui enviar o arquivo (' + r.status + ').');
            if (r.status < 500 && r.status !== 429) break;
            await new Promise(ok => setTimeout(ok, 1500 * (t + 1)));
        }
        throw ultimo;
    }

    const paraB64 = buf => {
        const u8 = new Uint8Array(buf); let s = '';
        for (let i = 0; i < u8.length; i += 0x8000) s += String.fromCharCode.apply(null, u8.subarray(i, i + 0x8000));
        return btoa(s);
    };

    window.wfEnviarMidia = async function (arquivo, aoProgresso) {
        if (!arquivo || !/^image\/(gif|webp|png|jpeg)$/.test(arquivo.type)) throw new Error('Use uma imagem JPG, PNG, WebP ou GIF.');
        if (arquivo.size > MAX) throw new Error('O arquivo pode ter até 25 MB. Escolha um menor (ou reduza em ezgif.com/optimize).');
        const ini = await pedir(BASE, 'POST', { tipo: arquivo.type, tamanho: arquivo.size });
        const tam = ini.pedaco || 4 * 1024 * 1024;
        let enviado = 0;
        for (let n = 0; enviado < arquivo.size; n++) {
            const parte = arquivo.slice(enviado, enviado + tam);
            await pedir(`${BASE}/${ini.token}/${n}`, 'PUT', { dados: paraB64(await parte.arrayBuffer()) });
            enviado += parte.size;
            if (aoProgresso) aoProgresso(enviado / arquivo.size);
        }
        const fim = await pedir(`${BASE}/${ini.token}/fim`, 'POST');
        return { token: fim.token, url: fim.url };
    };
})();
