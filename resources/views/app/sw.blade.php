/* Work Flower — service worker (gerado por AppInstalavelController@serviceWorker; não é um arquivo em public/).
 *
 * O que faz:
 *  - Páginas: sempre pela rede (são pessoais e mudam a todo momento; nada de página guardada que outra
 *    pessoa no mesmo aparelho possa ver depois de sair). Sem conexão, mostra a página "offline".
 *  - JS, CSS e ícones do próprio site: guardados no aparelho e entregues na hora, enquanto a versão nova
 *    é buscada por trás (os endereços já têm ?v=<data do arquivo>, então versão nova = endereço novo).
 *  - Todo o resto (POST, /midia, imagens de fundo, áudio, APIs, outros sites) passa direto, sem cache.
 */
const VERSAO = {!! json_encode($versao) !!};
const BASE = {!! json_encode($base, JSON_UNESCAPED_SLASHES) !!};
const OFFLINE = {!! json_encode($offline, JSON_UNESCAPED_SLASHES) !!};
const PRECACHE = {!! json_encode($precache, JSON_UNESCAPED_SLASHES) !!};
@verbatim
const CACHE_FIXO = 'wf-fixo-' + VERSAO;     // página offline e ícones
const CACHE_ESTATICO = 'wf-estatico-v1';      // JS e CSS (sobrevive às versões do sw; cada arquivo se renova sozinho)
const MAX_ESTATICOS = 120;

const basePath = new URL(BASE).pathname;
const PASTAS_ESTATICAS = ['js/', 'css/', 'icons/'].map((p) => basePath + p);

self.addEventListener('install', (ev) => {
    ev.waitUntil(
        caches.open(CACHE_FIXO)
            .then((c) => c.addAll(PRECACHE.map((u) => new Request(u, { cache: 'reload', credentials: 'same-origin' }))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (ev) => {
    ev.waitUntil((async () => {
        const nomes = await caches.keys();
        await Promise.all(nomes
            .filter((n) => n.startsWith('wf-') && n !== CACHE_FIXO && n !== CACHE_ESTATICO)
            .map((n) => caches.delete(n)));
        if (self.registration.navigationPreload) {
            try { await self.registration.navigationPreload.enable(); } catch (e) { /* sem suporte */ }
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (ev) => {
    const req = ev.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin || !url.pathname.startsWith(basePath)) return;

    if (req.mode === 'navigate') {
        ev.respondWith(pagina(ev));
        return;
    }

    if (PASTAS_ESTATICAS.some((p) => url.pathname.startsWith(p)) && !req.headers.has('range')) {
        ev.respondWith(estatico(ev));
    }
});

async function pagina(ev) {
    try {
        const pre = await ev.preloadResponse;
        if (pre) return pre;
        return await fetch(ev.request);
    } catch (e) {
        const offline = await caches.match(OFFLINE, { cacheName: CACHE_FIXO });
        return offline || new Response('Sem conexão.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

async function estatico(ev) {
    const req = ev.request;
    const cache = await caches.open(CACHE_ESTATICO);
    const guardado = (await cache.match(req)) || (await caches.match(req, { cacheName: CACHE_FIXO }));

    const daRede = fetch(req).then(async (resp) => {
        if (resp && resp.ok && resp.type === 'basic') {
            await cache.put(req, resp.clone());
            await limpar(cache, req.url);
        }
        return resp;
    });

    if (guardado) {
        ev.waitUntil(daRede.catch(() => {}));
        return guardado;
    }
    return daRede;
}

/* Tira versões antigas do mesmo arquivo (mesmo caminho, outro ?v=) e segura o tamanho do cache. */
async function limpar(cache, atual) {
    const caminho = new URL(atual).pathname;
    const chaves = await cache.keys();
    await Promise.all(chaves
        .filter((k) => k.url !== atual && new URL(k.url).pathname === caminho)
        .map((k) => cache.delete(k)));
    const resto = await cache.keys();
    if (resto.length > MAX_ESTATICOS) {
        await Promise.all(resto.slice(0, resto.length - MAX_ESTATICOS).map((k) => cache.delete(k)));
    }
}
@endverbatim
