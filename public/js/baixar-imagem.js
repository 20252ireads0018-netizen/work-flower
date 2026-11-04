/* Work Flower · imagem ou GIF vindo de outro site (fundo geral e fundo de cada workflow/aba)
   Arrastar um GIF de outra página (ou colar o link) não traz o arquivo, só o endereço; e o navegador não baixa
   de outro site por causa do CORS. Aqui o endereço vai para POST /fundo/baixar, que baixa no servidor e devolve
   os bytes, e o resultado vira um File comum para o escolherImagem() de sempre.
   wfArquivoDe(dataTransfer) → File | null   ·   wfBaixarImagem(url) → File */
(function () {
    'use strict';
    if (window.wfBaixarImagem) return;
    // o endereço vem do layout (data-url na própria tag), porque o site pode estar numa subpasta (/work-flower)
    const ROTA = (document.currentScript && document.currentScript.dataset.url) || "/fundo/baixar";
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const EXT = { 'image/gif': 'gif', 'image/webp': 'webp', 'image/png': 'png', 'image/jpeg': 'jpg' };

    function deDataUrl(url) {
        const m = /^data:(image\/[a-z+]+);base64,(.*)$/i.exec(url);
        if (!m) return null;
        const bin = atob(m[2]), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return new File([bytes], 'imagem.' + (EXT[m[1]] || 'img'), { type: m[1] });
    }

    async function wfBaixarImagem(url) {
        url = String(url || '').trim();
        if (/^data:image\//i.test(url)) { const f = deDataUrl(url); if (f) return f; }
        if (!/^https?:\/\//i.test(url)) throw new Error('Cole um link que comece com http:// ou https://.');
        const r = await fetch(ROTA, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ url }),
        });
        if (r.status === 419 || r.status === 401) throw new Error('A sessão expirou. Recarregue a página e tente de novo.');
        if (r.status === 429) throw new Error('Muitos links seguidos. Espere um minuto.');
        if (!r.ok) {
            const j = await r.json().catch(() => ({}));
            throw new Error(Object.values(j.errors || {})[0]?.[0] || 'Não consegui baixar desse link. Salve o GIF no computador e escolha o arquivo.');
        }
        const blob = await r.blob();
        const tipo = (r.headers.get('Content-Type') || blob.type || 'image/gif').split(';')[0];
        return new File([blob], 'imagem.' + (EXT[tipo] || 'img'), { type: tipo });
    }

    /** O que foi solto ou colado: arquivo, imagem de outra página (link ou <img>) ou link em texto. */
    async function wfArquivoDe(dt) {
        if (!dt) return null;
        const arq = [...(dt.files || [])].find(f => f.type.startsWith('image/'));
        if (arq) return arq;
        if (dt.items) {
            for (const it of dt.items) if (it.kind === 'file' && it.type.startsWith('image/')) { const f = it.getAsFile(); if (f) return f; }
        }
        let url = '';
        const html = dt.getData && dt.getData('text/html');
        if (html) {
            const img = new DOMParser().parseFromString(html, 'text/html').querySelector('img[src]');
            if (img) url = img.getAttribute('src');
        }
        if (!url) url = ((dt.getData && dt.getData('text/uri-list')) || '').split(/\r?\n/).find(l => l && !l.startsWith('#')) || '';
        if (!url) url = ((dt.getData && dt.getData('text/plain')) || '').trim();
        if (!url || !/^(https?:|data:image\/)/i.test(url)) return null;
        return wfBaixarImagem(url);
    }

    window.wfBaixarImagem = wfBaixarImagem;
    window.wfArquivoDe = wfArquivoDe;
})();
