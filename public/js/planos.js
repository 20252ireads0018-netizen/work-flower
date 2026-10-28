/* Work Flower · limites de plano (vale para todas as páginas)
   Carregue DEPOIS de biblioteca.js e ANTES (ou depois) das áreas; não depende de build.

   Fonte da verdade: App\Support\Planos (PHP). A view injeta o plano do usuário em metas:
     <meta name="plano-slug"    content="{{ \App\Support\Planos::slugDe(auth()->user()) }}">
     <meta name="plano-limites" content='@json(\App\Support\Planos::limites(\App\Support\Planos::slugDe(auth()->user())))'>
     <meta name="plano-teste-ate" content="{{ \App\Support\Planos::testeAte(auth()->user())?->toIso8601String() }}">
                                                                    (teste grátis de 30 dias; vazio quando não há teste)
     <meta name="planos-url"    content="{{ route('planos') }}">   (opcional: link "Ver planos" no aviso)
     <meta name="workflows-exportar-url" ...>                       (usada para conferir a exportação)

   Plano pago ativo (qualquer slug diferente de "gratuito"): biblioteca, workflows e elementos ficam SEM limite.
   Só "usuarios" continua valendo (pessoas no mesmo workflow, com o dono: Gratuito 1, Básico 5).

   Teste grátis: durante o teste o servidor já manda o slug do plano pago, então nada muda nos limites.
   Quando o prazo acaba, o servidor volta a mandar "gratuito" e os limites voltam sozinhos.
   A meta "plano-teste-ate" serve só para avisar o usuário (contagem regressiva e banner).

   API pública:
     WFPlanos.slug / WFPlanos.limites / WFPlanos.pago / WFPlanos.semAnuncios
     WFPlanos.teste                             -> { ativo, ate, dias } (dias = dias restantes, arredondado para cima)
     WFPlanos.limite('biblioteca')              -> número ou null (ilimitado)
     WFPlanos.excede('usuarios', 2)             -> true/false (sem aviso)
     WFPlanos.exigir('usuarios', 2)             -> false + aviso se passar do limite
     WFPlanos.contar(json)                      -> { areas, elementos } de um arquivo de workflow
     WFPlanos.exigirArquivo('exportar', json)   -> false + aviso se passar (use também em 'importar')
     WFPlanos.texto('biblioteca', usados)       -> "3 de 5" / "3 (ilimitado)"
   Ações aceitas nas mensagens: 'importar', 'exportar', 'baixar'.
   Evento: window 'wf-limite' { recurso, limite, mensagem } sempre que algo for barrado. */
(function () {
    'use strict';

    const PADRAO = {
        gratuito: { biblioteca: 5,    workflows: 3,    elementos: 10,   usuarios: 1 },
        basico:   { biblioteca: null, workflows: null, elementos: null, usuarios: 5 },
        pro:      { biblioteca: null, workflows: null, elementos: null, usuarios: 10 },
        premium:  { biblioteca: null, workflows: null, elementos: null, usuarios: 25 },
    };
    const NOMES = { gratuito: 'Gratuito', basico: 'Básico', pro: 'Pró', premium: 'Premium' };
    const EM_BREVE = ['pro', 'premium'];
    const AREAS = ['corpo', 'mente', 'carteira', 'diverso'];
    const ACOES = ['importar', 'exportar', 'baixar'];
    const AVISAR_TESTE_ULTIMOS_DIAS = 7; // banner de "seu teste acaba em X dias" nos últimos N dias

    const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
    const slugBruto = meta('plano-slug');
    const slug = PADRAO[slugBruto] ? slugBruto : 'gratuito';
    let limites = null;
    try { limites = JSON.parse(meta('plano-limites')); } catch { /* usa o padrão */ }
    if (!limites || typeof limites !== 'object') limites = PADRAO[slug];

    // Plano pago ativo: qualquer slug que não seja "gratuito" (mesmo que o meta traga um slug que este arquivo não conhece).
    // Biblioteca, workflows e elementos ficam sem limite. "usuarios" continua valendo.
    const PAGO = !!slugBruto && slugBruto !== 'gratuito';
    const SEM_LIMITE_NO_PAGO = ['biblioteca', 'workflows', 'elementos'];

    /* ---------- teste grátis ----------
       Só informativo: quem decide se o teste vale é o servidor (slug da meta "plano-slug"). */
    const TESTE_ATE = (() => {
        const bruto = meta('plano-teste-ate');
        if (!bruto) return null;
        const d = new Date(bruto);
        return isNaN(d.getTime()) ? null : d;
    })();
    const TESTE_ATIVO = PAGO && !!TESTE_ATE && TESTE_ATE.getTime() > Date.now();
    const TESTE_DIAS = TESTE_ATIVO ? Math.max(1, Math.ceil((TESTE_ATE.getTime() - Date.now()) / 86400000)) : 0;
    const TESTE = { ativo: TESTE_ATIVO, ate: TESTE_ATIVO ? TESTE_ATE : null, dias: TESTE_DIAS };

    const dataCurta = d => d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    const diasTexto = n => `${n} ${n === 1 ? 'dia' : 'dias'}`;

    // Planos sem anúncios (o servidor já não carrega o AdSense; isto é só reforço no navegador).
    // Mantenha igual a Planos::SEM_ANUNCIOS no PHP.
    const SEM_ANUNCIOS = ['premium'];
    const SEM_ANUNCIO = SEM_ANUNCIOS.includes(slugBruto);
    if (SEM_ANUNCIO) {
        const removerAnuncios = () => {
            document.querySelectorAll('[data-anuncio], ins.adsbygoogle, .google-auto-placed, iframe[id^="aswift_"]')
                .forEach(el => el.remove());
            document.querySelectorAll('script[src*="googlesyndication.com"]').forEach(el => el.remove());
        };
        const estilo = document.createElement('style');
        estilo.textContent = '[data-anuncio],ins.adsbygoogle,.google-auto-placed{display:none!important}';
        document.head.appendChild(estilo);
        removerAnuncios();
        new MutationObserver(removerAnuncios).observe(document.documentElement, { childList: true, subtree: true });
    }

    // Se o servidor não mandar algum recurso no meta, usa o padrão do plano (evita "sem limite" por engano no gratuito)
    const limite = r => {
        if (PAGO && SEM_LIMITE_NO_PAGO.includes(r)) return null;
        return limites[r] !== undefined ? limites[r] : (PADRAO[slug][r] !== undefined ? PADRAO[slug][r] : null);
    };
    const excede = (r, qtd) => limite(r) !== null && qtd > limite(r);
    const semLimites = () => SEM_LIMITE_NO_PAGO.every(r => limite(r) === null);

    /* ---------- mensagens ---------- */
    const MSG = {
        biblioteca: l => `O plano ${NOMES[slug]} guarda até ${l} blocos na biblioteca. Assine o Básico para guardar sem limite.`,
        workflows: (l, acao) => `O plano ${NOMES[slug]} permite ${acao} até ${l} workflows por vez. Assine o Básico para não ter limite.`,
        elementos: (l, acao) => `O plano ${NOMES[slug]} permite ${acao} até ${l} elementos (texto, tabelas etc.) por arquivo. Assine o Básico para não ter limite.`,
        usuarios: l => `O plano ${NOMES[slug]} permite ${l} ${l === 1 ? 'usuário' : 'usuários'} na conta (com você). Assine o Básico para trabalhar com até 5 pessoas no mesmo workflow.`,
    };

    function aviso(texto) {
        let el = document.getElementById('wf-plano-aviso');
        if (!el) {
            el = document.createElement('div');
            el.id = 'wf-plano-aviso';
            el.setAttribute('role', 'alert');
            el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:99999;max-width:min(92vw,460px);' +
                'padding:12px 16px;border-radius:12px;background:#1f1f24;color:#fff;font:14px/1.4 system-ui,sans-serif;' +
                'box-shadow:0 8px 28px rgba(0,0,0,.35);';
            document.body.appendChild(el);
        }
        el.textContent = texto + ' ';
        const url = meta('planos-url');
        if (url) {
            const a = document.createElement('a');
            a.href = url; a.textContent = 'Ver planos';
            a.style.cssText = 'color:#ffd166;font-weight:600;text-decoration:underline';
            el.appendChild(a);
        }
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 7000);
    }

    /** Mostra o aviso e leva o usuário à tela de planos. */
    let indo = false;
    function irParaPlanos(mensagem, destino) {
        aviso(mensagem);
        const url = destino || meta('planos-url');
        if (!url || indo) return;
        indo = true;
        setTimeout(() => { location.href = url; }, 2200);
    }

    function negar(recurso, acao) {
        const l = limite(recurso);
        const verbo = ACOES.includes(acao) ? acao : 'exportar';
        const mensagem = MSG[recurso](l, verbo);
        window.dispatchEvent(new CustomEvent('wf-limite', { detail: { recurso, limite: l, mensagem } }));
        irParaPlanos(mensagem);
    }

    /* ---------- exportação: confere o arquivo que o servidor devolveu antes de o app baixá-lo ---------- */
    const caminhoDe = u => {
        try { return new URL(typeof u === 'string' ? u : (u && u.url) || String(u), location.href).pathname; }
        catch { return ''; }
    };
    const ehExportacao = u => {
        const base = caminhoDe(meta('workflows-exportar-url'));
        return !!base && caminhoDe(u) === base;
    };

    /* ---------- servidor: qualquer resposta 403 { plano_limite: true } vira aviso + tela de planos ---------- */
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const r = await fetchOriginal(...args);
        // O servidor pode recusar com 402, 403, 422, 429… O que vale é o JSON { plano_limite: true }.
        if (!r.ok && r.status !== 401 && r.status !== 419 && /json/i.test(r.headers.get('content-type') || '')) {
            try {
                const j = await r.clone().json();
                if (j && j.plano_limite) {
                    window.dispatchEvent(new CustomEvent('wf-limite', { detail: { recurso: j.recurso, limite: j.limite, mensagem: j.message } }));
                    irParaPlanos(j.message, j.redirect);
                    // A página vai para os planos: mantém a chamada pendente para o app não mostrar mensagem de erro.
                    return new Promise(() => {});
                }
            } catch { /* resposta sem JSON: segue normal */ }
        }
        // Exportação de workflows: se o arquivo passar do limite do plano, não deixa o app baixar.
        // Plano pago (sem limites) não precisa dessa conferência.
        if (r.ok && !semLimites() && args[0] && ehExportacao(args[0])) {
            try {
                const j = await r.clone().json();
                if (!exigirArquivo('exportar', j)) return new Promise(() => {});
            } catch { /* não é JSON: segue normal */ }
        }
        return r;
    };

    const exigir = (recurso, qtd, acao) => {
        if (!excede(recurso, qtd)) return true;
        negar(recurso, acao);
        return false;
    };

    /* ---------- conta o que há num arquivo de workflow ----------
       áreas = chaves/campos "corpo|mente|carteira|diverso" encontrados; elementos = objetos { tipo, dados }.
       Se o formato do seu arquivo for outro, ajuste só esta função. */
    function contar(json) {
        const areas = new Set();
        let elementos = 0;
        // pacote do Work Flower: { workflows: [ {nome, ...}, ... ] } -> uma área por item
        const porLista = Array.isArray(json && json.workflows) ? json.workflows.length : null;
        (function andar(o) {
            if (Array.isArray(o)) { o.forEach(andar); return; }
            if (!o || typeof o !== 'object') return;
            if (typeof o.tipo === 'string' && o.dados && typeof o.dados === 'object' && !Array.isArray(o.dados)) elementos++;
            if (typeof o.area === 'string' && AREAS.includes(o.area)) areas.add(o.area);
            for (const [k, v] of Object.entries(o)) {
                if (AREAS.includes(k)) areas.add(k);
                andar(v);
            }
        })(json);
        return { areas: porLista !== null ? porLista : areas.size, elementos };
    }

    function exigirArquivo(acao, json) {
        const n = contar(json);
        if (!exigir('workflows', n.areas, acao)) return false;
        return exigir('elementos', n.elementos, acao);
    }

    const texto = (recurso, usados) => {
        const l = limite(recurso);
        return l === null ? `${usados} (ilimitado)` : `${usados} de ${l}`;
    };

    window.WFPlanos = { slug, nome: NOMES[slug], pago: PAGO, semAnuncios: SEM_ANUNCIO, teste: TESTE, limites, limite, excede, barrar: (recurso, acao) => negar(recurso, acao), exigir: (r, q, acao) => exigir(r, q, acao), contar, exigirArquivo, texto, emBreve: s => EM_BREVE.includes(s) };

    /* ---------- biblioteca: barra no cliente antes do PUT ---------- */
    function protegerBiblioteca() {
        const B = window.WFBiblioteca;
        if (!B || B._planos) return !!B;
        const original = B.adicionar;
        B.adicionar = async item => {
            const l = limite('biblioteca');
            if (l !== null) {
                const itens = await B.carregar();
                if (itens.length >= l) {
                    negar('biblioteca'); // mostra o aviso e leva para a tela de planos
                    // Não rejeita: assim o quadro.js não mostra "não consegui salvar na biblioteca".
                    // A promessa fica pendente enquanto a página troca para /planos.
                    return new Promise(() => {});
                }
            }
            return original(item);
        };
        B._planos = true;
        return true;
    }
    if (!protegerBiblioteca()) {
        document.addEventListener('DOMContentLoaded', protegerBiblioteca);
        window.addEventListener('load', protegerBiblioteca);
    }

    /* ---------- importação: o arquivo escolhido é conferido antes de o app ler ----------
       Vale para qualquer <input type="file" data-wf-importar> e para o input da aba "Workflows" (workflowsTransfer).
       Plano pago (sem limites): não intercepta, o app lê o arquivo direto. */
    const ehInputImportacao = el =>
        !!el && el.matches && el.matches('input[type="file"]') &&
        (el.hasAttribute('data-wf-importar') || !!el.closest('[x-data^="workflowsTransfer"]'));

    document.addEventListener('change', async e => {
        const el = e.target;
        if (semLimites() || !ehInputImportacao(el) || el._wfLiberado) return;
        e.stopImmediatePropagation();
        const arquivo = el.files && el.files[0];
        if (!arquivo) return;
        try {
            const json = JSON.parse(await arquivo.text());
            if (!exigirArquivo('importar', json)) { el.value = ''; return; }
        } catch { /* não é JSON legível: deixa o app mostrar o erro dele */ }
        el._wfLiberado = true;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el._wfLiberado = false;
    }, true);

    /* ---------- banner do teste grátis ----------
       Aparece nos últimos dias do teste, em qualquer página, e pode ser dispensado (lembra só nesta sessão). */
    function bannerTeste() {
        if (!TESTE_ATIVO || TESTE_DIAS > AVISAR_TESTE_ULTIMOS_DIAS || document.getElementById('wf-teste-banner')) return;
        const chave = 'wf-teste-banner-fechado';
        try { if (sessionStorage.getItem(chave)) return; } catch { /* sem storage: mostra sempre */ }

        const el = document.createElement('div');
        el.id = 'wf-teste-banner';
        el.setAttribute('role', 'status');
        el.style.cssText = 'position:fixed;left:50%;top:16px;transform:translateX(-50%);z-index:99998;max-width:min(92vw,520px);' +
            'display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;background:#1f1f24;color:#fff;' +
            'font:14px/1.4 system-ui,sans-serif;box-shadow:0 8px 28px rgba(0,0,0,.35);';

        const msg = document.createElement('span');
        msg.textContent = `Seu teste grátis termina em ${diasTexto(TESTE_DIAS)} (${dataCurta(TESTE_ATE)}). `;
        const url = meta('planos-url');
        if (url) {
            const a = document.createElement('a');
            a.href = url; a.textContent = 'Assinar o Básico';
            a.style.cssText = 'color:#ffd166;font-weight:600;text-decoration:underline';
            msg.appendChild(a);
        }

        const fechar = document.createElement('button');
        fechar.type = 'button';
        fechar.setAttribute('aria-label', 'Fechar aviso');
        fechar.textContent = '×';
        fechar.style.cssText = 'margin-left:auto;background:none;border:0;color:#fff;font-size:20px;line-height:1;cursor:pointer;opacity:.8';
        fechar.addEventListener('click', () => {
            el.remove();
            try { sessionStorage.setItem(chave, '1'); } catch { /* ignora */ }
        });

        el.append(msg, fechar);
        document.body.appendChild(el);
    }

    /* ---------- aplicação na tela de planos (blade) ----------
       [data-plano-limite="biblioteca"]  -> escreve "5" ou "Ilimitado"
       [data-plano-mostrar="gratuito"]   -> só aparece para quem está nesse plano (aceita "gratuito basico")
       [data-plano-teste]                -> só aparece durante o teste grátis
       [data-plano-teste-dias]           -> escreve os dias restantes do teste ("12 dias")
       [data-plano-teste-ate]            -> escreve a data final do teste ("19/11")
       [data-plano-card="basico"]        -> cartão do plano; ganha .plano-atual / .plano-em-breve
         dentro dele, [data-plano-assinar] vira "Plano atual" ou "Em breve" e é desativado. */
    function aplicarTela() {
        document.querySelectorAll('[data-plano-limite]').forEach(el => {
            const l = limite(el.dataset.planoLimite);
            el.textContent = l === null ? 'Ilimitado' : String(l);
        });
        document.querySelectorAll('[data-plano-mostrar]').forEach(el => {
            el.hidden = !el.dataset.planoMostrar.split(/\s+/).includes(slug);
        });
        document.querySelectorAll('[data-plano-teste]').forEach(el => { el.hidden = !TESTE_ATIVO; });
        document.querySelectorAll('[data-plano-teste-dias]').forEach(el => {
            if (TESTE_ATIVO) el.textContent = diasTexto(TESTE_DIAS);
        });
        document.querySelectorAll('[data-plano-teste-ate]').forEach(el => {
            if (TESTE_ATIVO) el.textContent = dataCurta(TESTE_ATE);
        });
        document.querySelectorAll('[data-plano-card]').forEach(card => {
            const s = card.dataset.planoCard;
            const botao = card.querySelector('[data-plano-assinar]');
            const travar = rotulo => {
                if (!botao) return;
                botao.textContent = rotulo;
                botao.setAttribute('aria-disabled', 'true');
                if ('disabled' in botao) botao.disabled = true;
                botao.style.pointerEvents = 'none';
                botao.style.opacity = '.6';
                botao.removeAttribute('href');
            };
            // "Plano atual" já vem pronto do Blade (só para quem está logado); aqui só o "Em breve"
            if (EM_BREVE.includes(s)) { card.classList.add('plano-em-breve'); travar('Em breve'); }
        });
    }
    const iniciar = () => { aplicarTela(); bannerTeste(); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();
