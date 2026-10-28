@php
    use App\Models\User;

    // ID do AdSense (ca-pub-XXXXXXXXXXXXXXXX). Vazio = nenhum anúncio é carregado.
    $adsenseClient = config('services.adsense.client');

    $usuario = auth()->user();
    $prim    = $usuario?->cor_primaria  ?? User::COR_PRIMARIA_PADRAO;
    $cab     = $usuario?->cor_cabecalho ?? User::COR_CABECALHO_PADRAO;

    // ===== Fundo personalizado (cor, imagem ou GIF) =====
    $fundoIni = ['cor' => null, 'ajuste' => 'cover', 'escurecer' => 55, 'desfoque' => 0, 'imagem' => false, 'versao' => 0];

    if ($usuario) {
        $fundoCfg = json_decode((string) \App\Models\CorpoDado::where('user_id', $usuario->id)->where('chave', 'fundo')->value('valor'), true);
        if (is_array($fundoCfg)) {
            if (isset($fundoCfg['cor']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $fundoCfg['cor'])) {
                $fundoIni['cor'] = $fundoCfg['cor'];
            }
            if (in_array($fundoCfg['ajuste'] ?? '', ['cover', 'contain', 'repeat'], true)) {
                $fundoIni['ajuste'] = $fundoCfg['ajuste'];
            }
            $fundoIni['escurecer'] = max(0, min(90, (int) ($fundoCfg['escurecer'] ?? 55)));
            $fundoIni['desfoque']  = max(0, min(20, (int) ($fundoCfg['desfoque'] ?? 0)));
        }

        // Só busca id e data: a imagem em si é entregue por /fundo/imagem (com cache do navegador)
        $fundoLinhaImg = \App\Models\CorpoDado::where('user_id', $usuario->id)->where('chave', 'fundo.img')->first(['id', 'updated_at']);
        $fundoIni['imagem'] = (bool) $fundoLinhaImg;
        $fundoIni['versao'] = $fundoLinhaImg?->updated_at?->timestamp ?? 0;
    }

    $fundoUrl    = \Illuminate\Support\Facades\Route::has('fundo.imagem') ? route('fundo.imagem') : '';
    $fundoSalvar = \Illuminate\Support\Facades\Route::has('fundo.salvar') ? route('fundo.salvar') : '';

    $fundoAj = $fundoIni['ajuste'];
    $fundoEstiloImg = $fundoIni['imagem']
        ? "background-image:url('{$fundoUrl}?v={$fundoIni['versao']}');"
            . 'background-size:' . ($fundoAj === 'repeat' ? 'auto' : $fundoAj) . ';'
            . 'background-repeat:' . ($fundoAj === 'repeat' ? 'repeat' : 'no-repeat') . ';'
            . ($fundoIni['desfoque'] ? "filter:blur({$fundoIni['desfoque']}px);inset:-40px;" : '')
        : 'display:none;';
    $fundoEstiloVeu = $fundoIni['imagem']
        ? "background:color-mix(in srgb, var(--papel) {$fundoIni['escurecer']}%, transparent);"
        : 'display:none;';

    // Preferências da conta (teclas de atalho, organização automática, tutorial visto): ver PreferenciasController
    $preferencias = $usuario ? \App\Http\Controllers\PreferenciasController::de($usuario->id) : [];
    $preferenciasUrl = $usuario && \Illuminate\Support\Facades\Route::has('preferencias.salvar') ? route('preferencias.salvar') : '';

    // Workflow compartilhado (ver App\Support\Compartilhar): de quem é a área aberta e se outras pessoas usam
    $wfArea     = request()->attributes->get('wf_area');
    $wfDono     = request()->attributes->get('wf_dono');
    $wfDonoNome = request()->attributes->get('wf_dono_nome');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8">
    <meta name="downloads-registrar-url" content="{{ \Illuminate\Support\Facades\Route::has('downloads.registrar') ? route('downloads.registrar') : '' }}">
    <meta name="plano-teste-ate" content="{{ \App\Support\Planos::testeAte(auth()->user())?->toIso8601String() }}">
    
    {{-- viewport-fit=cover: usa a tela toda em celulares com notch (as áreas seguras são tratadas no CSS) --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- versão do editor de imagem (carregado só quando alguém abre um cartão "Editor de imagem") --}}
    <meta name="wf-editor-imagem-v" content="{{ @filemtime(public_path('js/editor-imagem.js')) }}">
    @if ($wfArea)
        {{-- várias pessoas no mesmo workflow: área aberta, dono (se não for o próprio usuário), versão e sincronia --}}
        <meta name="wf-area" content="{{ $wfArea }}">
        <meta name="wf-sincronia-url" content="{{ route('sincronia', $wfArea) }}">
        <meta name="wf-compartilhar-url" content="{{ route('compartilhar.index', $wfArea) }}">
        @isset($wfVersao)<meta name="wf-versao" content="{{ $wfVersao }}">@endisset
        <meta name="wf-sincronizar" content="{{ !empty($wfSincronizar) ? '1' : '0' }}">
        @if ($wfDono)
            <meta name="wf-dono" content="{{ $wfDono }}">
            <meta name="wf-dono-nome" content="{{ $wfDonoNome }}">
            <script>
                /* Workflow de outra pessoa aberto: todo pedido desta página e todo formulário vão para os dados dela. */
                (function () {
                    const dono = document.querySelector('meta[name="wf-dono"]').content;
                    const mesmo = u => { try { return new URL(u, location.href).origin === location.origin; } catch (e) { return false; } };
                    const orig = window.fetch.bind(window);
                    window.fetch = function (entrada, op) {
                        const url = typeof entrada === 'string' ? entrada : (entrada && entrada.url) || '';
                        if (!mesmo(url)) return orig(entrada, op);
                        op = Object.assign({}, op || {});
                        const h = new Headers(op.headers || (entrada instanceof Request ? entrada.headers : undefined));
                        if (!h.has('X-WF-Dono')) h.set('X-WF-Dono', dono);
                        op.headers = h;
                        return orig(entrada, op);
                    };
                    const marcar = f => {
                        if (!f || !f.action || !mesmo(f.action) || f.querySelector('input[name="wf_dono"]')) return;
                        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'wf_dono'; i.value = dono; f.appendChild(i);
                    };
                    document.addEventListener('submit', e => marcar(e.target), true);
                    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('form').forEach(marcar));
                })();
            </script>
        @endif
    @endif
    <script>
        /* Preferências da conta: ler() devolve o que está valendo; salvar({...}) junta e envia ao servidor (sem conta, fica só na página). */
        window.WFPrefs = (function () {
            let p = @json((object) $preferencias);
            const url = @json($preferenciasUrl);
            let fila = {}, t = 0;
            function enviar() {
                const corpo = fila; fila = {};
                if (!url || !Object.keys(corpo).length) return;
                fetch(url, {
                    method: 'PUT', credentials: 'same-origin', keepalive: true,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify(corpo),
                }).catch(() => {});
            }
            window.addEventListener('pagehide', () => { if (t) { clearTimeout(t); t = 0; enviar(); } });
            return {
                ler: () => p,
                salvar(m) {
                    p = { ...p, ...m };
                    Object.assign(fila, m);
                    clearTimeout(t); t = setTimeout(() => { t = 0; enviar(); }, 400);
                    window.dispatchEvent(new CustomEvent('wf-prefs', { detail: p }));
                },
            };
        })();
    </script>

    {{-- Limite do plano: qualquer resposta do servidor com { plano_limite: true } leva direto para a tela de planos.
         Fica aqui, inline, para não depender de nenhum arquivo .js externo. Precisa vir antes dos outros scripts. --}}
    <script>
    (function () {
        if (window.__wfLimitePlano) return;
        window.__wfLimitePlano = true;

        const planos = @json(\Illuminate\Support\Facades\Route::has('planos') ? route('planos') : url('/planos'));
        const original = window.fetch.bind(window);
        let indo = false;

        function aviso(texto) {
            const el = document.createElement('div');
            el.setAttribute('role', 'alert');
            el.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:99999;max-width:min(92vw,460px);' +
                'padding:12px 16px;border-radius:12px;background:#1f1f24;color:#fff;font:14px/1.4 system-ui,sans-serif;box-shadow:0 8px 28px rgba(0,0,0,.35);';
            el.textContent = texto;
            document.body.appendChild(el);
        }

        function destinoSeguro(url) {
            try {
                const u = new URL(url || planos, location.href);
                return u.origin === location.origin ? u.href : planos;
            } catch (e) { return planos; }
        }

        window.fetch = async (...args) => {
            const r = await original(...args);
            if (!r.ok && r.status !== 401 && r.status !== 419 && /json/i.test(r.headers.get('content-type') || '')) {
                try {
                    const j = await r.clone().json();
                    if (j && j.plano_limite) {
                        if (!indo) {
                            indo = true;
                            aviso((j.message || 'Você atingiu o limite do seu plano.') + ' Abrindo os planos…');
                            setTimeout(() => { location.href = destinoSeguro(j.redirect); }, 1500);
                        }
                        // mantém a chamada pendente: o app não mostra "Não consegui salvar" enquanto a página troca para os planos
                        return new Promise(() => {});
                    }
                } catch (e) { /* resposta sem JSON válido: segue normal */ }
            }
            return r;
        };
    })();
    </script>

    {{-- Proteção de viewport e de dados antigos (celular <-> computador).
         Problema: a página aberta no computador fica "congelada" com o layout e os tamanhos de antes. Ao voltar de outro
         aparelho (ou depois de redimensionar a janela), os cards aparecem quebrados.
         O que isto faz, sem depender de nenhum outro arquivo:
         1) marca <html data-bp="mobile|tablet|desktop"> e avisa a página com o evento "wf:viewport" quando a faixa muda
            (quadro.js pode escutar esse evento para recalcular posições; window.WFViewport.faixa() também está disponível);
         2) volta da página para o primeiro plano depois de um tempo, ou vinda do cache do navegador (botão voltar),
            ou com a faixa de tela diferente: recarrega do servidor, para exibir o layout salvo mais recente;
         3) nunca recarrega com um campo em edição, com gravação em andamento ou se window.__wfSujo === true. --}}
    <script>
    (function () {
        if (window.WFViewport) return;

        const raiz = document.documentElement;
        const LIMITE_OCIOSO = 2 * 60 * 1000;   // fora da tela por mais que isso => recarrega ao voltar
        const INTERVALO_MIN = 10 * 1000;       // nunca recarrega duas vezes em menos que isso (evita laço)
        const CHAVE = 'wf:guarda';

        const faixa = () => {
            const w = window.innerWidth || raiz.clientWidth || 0;
            return w < 640 ? 'mobile' : (w < 1024 ? 'tablet' : 'desktop');
        };

        let atual = faixa();
        let pendentes = 0;      // requisições em andamento (gravações)
        let ocultoEm = 0;
        let faixaAoOcultar = atual;
        let timer = 0;

        raiz.dataset.bp = atual;
        raiz.style.setProperty('--wf-vw', (window.innerWidth || 0) + 'px');

        // Conta requisições em andamento para não recarregar no meio de um salvamento
        const baseFetch = window.fetch.bind(window);
        window.fetch = (...args) => {
            pendentes++;
            let p;
            try { p = baseFetch(...args); } catch (e) { pendentes = Math.max(0, pendentes - 1); throw e; }
            const fim = () => { pendentes = Math.max(0, pendentes - 1); };
            p.then(fim, fim);
            return p;
        };

        const editando = () => {
            const el = document.activeElement;
            if (!el || el === document.body) return false;
            const tag = el.tagName;
            if (tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable) return true;
            if (tag === 'INPUT') return !/^(button|checkbox|radio|submit|reset|range|color|file|image)$/i.test(el.type || 'text');
            return false;
        };

        const podeRecarregar = () => {
            if (window.__wfSujo === true || pendentes > 0 || editando()) return false;
            try {
                const ult = Number(sessionStorage.getItem(CHAVE) || 0);
                if (Date.now() - ult < INTERVALO_MIN) return false;
            } catch (e) { /* sem sessionStorage: segue */ }
            return true;
        };

        const recarregar = () => {
            if (!podeRecarregar()) return false;
            try { sessionStorage.setItem(CHAVE, String(Date.now())); } catch (e) { /* ok */ }
            location.reload();
            return true;
        };

        function mudouTela() {
            const nova = faixa();
            raiz.style.setProperty('--wf-vw', (window.innerWidth || 0) + 'px');
            if (nova === atual) return;
            const de = atual;
            atual = nova;
            raiz.dataset.bp = nova;
            window.dispatchEvent(new CustomEvent('wf:viewport', { detail: { de, para: nova } }));
        }

        window.addEventListener('resize', () => {
            clearTimeout(timer);
            timer = setTimeout(mudouTela, 120);
        }, { passive: true });
        window.addEventListener('orientationchange', () => setTimeout(mudouTela, 200));

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                ocultoEm = Date.now();
                faixaAoOcultar = atual;
                return;
            }
            // voltou para a tela
            const fora = ocultoEm ? Date.now() - ocultoEm : 0;
            mudouTela();
            if (fora > LIMITE_OCIOSO || faixaAoOcultar !== atual) recarregar();
        });

        // Página restaurada do cache do navegador (voltar/avançar): os dados em memória podem estar velhos
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) recarregar();
        });

        window.WFViewport = { faixa: () => atual, recarregar, largura: () => window.innerWidth || 0 };
    })();
    </script>

    @php
       $planoSlug = \App\Support\Planos::slugDe(auth()->user());
       $semAnuncios = \App\Support\Planos::semAnuncios(auth()->user());
    @endphp
   <meta name="plano-slug" content="{{ $planoSlug }}">
   <meta name="plano-limites" content='@json(\App\Support\Planos::limites($planoSlug))'>
   <meta name="planos-url" content="{{ \Illuminate\Support\Facades\Route::has('planos') ? route('planos') : url('/planos') }}">
    <meta name="biblioteca-url" content="{{ \Illuminate\Support\Facades\Route::has('biblioteca.index') ? route('biblioteca.index') : '' }}">
    <meta name="workflows-lista-url" content="{{ \Illuminate\Support\Facades\Route::has('workflows.lista') ? route('workflows.lista') : '' }}">
    <meta name="workflows-exportar-url" content="{{ \Illuminate\Support\Facades\Route::has('workflows.exportar') ? route('workflows.exportar') : '' }}">
    <meta name="workflows-importar-url" content="{{ \Illuminate\Support\Facades\Route::has('workflows.importar') ? route('workflows.importar') : '' }}">
    <title>Work Flower · @yield('titulo', 'Painel')</title>

    {{-- Google AdSense: carrega para visitantes e para planos com anúncios.
         Plano Premium (Planos::SEM_ANUNCIOS) NÃO recebe nem o script, então não há Auto ads nem impressões.
         O ID vem de config('services.adsense.client'), definido por ADSENSE_CLIENT no .env. --}}
    @if ($adsenseClient && ! $semAnuncios)
        <meta name="google-adsense-account" content="{{ $adsenseClient }}">
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $adsenseClient }}"
                crossorigin="anonymous"></script>
    @endif

    <script src="{{ asset('js/biblioteca.js') }}?v={{ @filemtime(public_path('js/biblioteca.js')) }}"></script>
    <script src="{{ asset('js/lixeira.js') }}?v={{ @filemtime(public_path('js/lixeira.js')) }}"></script>
      <script src="{{ asset('js/planos.js') }}?v={{ @filemtime(public_path('js/planos.js')) }}"></script>
      <script src="{{ asset('js/arquivos.js') }}?v={{ @filemtime(public_path('js/arquivos.js')) }}"></script>
      @if ($wfArea)<script src="{{ asset('js/compartilhar.js') }}?v={{ @filemtime(public_path('js/compartilhar.js')) }}"></script>@endif
    @include('partials.workflows-transfer')
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ================= TEMA (escuro) =================
           Só estas quatro variáveis vêm do usuário; o resto é derivado delas.
           Superfícies, texto e linhas são fixos para manter o clima escuro. */
        :root {
            --prim: {{ $prim }};
            --cab: {{ $cab }};
            --on-prim: {{ User::contraste($prim) }};
            --on-cab: {{ User::contraste($cab) }};

            --prim-forte: color-mix(in srgb, var(--prim) 72%, white);
            --prim-suave: color-mix(in srgb, var(--prim) 16%, var(--superficie));
            --prim-linha: color-mix(in srgb, var(--prim) 40%, var(--superficie));
            --papel: color-mix(in srgb, var(--prim) 2%, #090c10);
            --superficie: color-mix(in srgb, var(--prim) 2%, #10151b);
            --superficie-2: color-mix(in srgb, var(--prim) 3%, #151b23);
            --tinta: #e8ecf1;
            --tinta-2: #8c97a6;
            --linha: #222a34;
            --borda-campo: #2d3641;

            /* Áreas seguras (notch / barra de gestos) */
            --seg-e: env(safe-area-inset-left, 0px);
            --seg-d: env(safe-area-inset-right, 0px);
            --seg-b: env(safe-area-inset-bottom, 0px);
        }
        @if ($fundoIni['cor'])
        :root { --papel: {{ $fundoIni['cor'] }}; }
        @endif

        [x-cloak] { display: none !important; }
        html {
            -webkit-font-smoothing: antialiased; color-scheme: dark;
            -webkit-text-size-adjust: 100%; text-size-adjust: 100%;   /* o celular não "infla" o texto ao girar a tela */
            overflow-x: clip;                                         /* nada empurra a página para os lados */
        }
        body {
            background: var(--papel); color: var(--tinta); font-family: 'DM Sans', system-ui, sans-serif;
            min-height: 100vh; min-height: 100dvh;
            overflow-x: clip;
            overflow-wrap: break-word;
            padding-left: var(--seg-e); padding-right: var(--seg-d);
        }
        img, video { max-width: 100%; }
        .titulo { font-family: 'Bricolage Grotesque', 'DM Sans', sans-serif; font-weight: 700; letter-spacing: -0.02em; }
        .texto-2 { color: var(--tinta-2); }
        ::selection { background: var(--prim); color: var(--on-prim); }
        :focus-visible { outline: 2px solid var(--prim); outline-offset: 2px; }
        body .text-red-700 { color: #f87171; }

        /* ===== Responsividade universal =====
           1) Filhos de flex/grid podem encolher (sem isso, textos longos e tabelas empurram a largura).
           2) Cartões de blocos nunca passam da largura do quadro, em qualquer aparelho.
           3) Campos com 16px no celular: o iOS não dá zoom ao tocar. */
        main, main > *, .card, .tabela-wrap { min-width: 0; }
        .wf-item { max-width: 100%; }
        .wf-bloco, .wf-cresce, .wf-esc, .wf-xl, .wf-xl-rolagem { min-width: 0; max-width: 100%; }
        .wf-xl-rolagem, .wf-esc, .tabela-wrap { -webkit-overflow-scrolling: touch; }

        @media (max-width: 767px) {
            .campo, select, textarea, input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]) { font-size: 16px; }
            .wf-xl-barra .campo, .wf-xl-barra select { font-size: 14px; }
            main { padding-bottom: calc(4rem + var(--seg-b)); }
        }

        /* Toques: alvos maiores em telas sem mouse */
        @media (pointer: coarse) {
            .btn, .btn-sec, .btn-cab { min-height: 2.5rem; }
            .mini-btn { width: 2rem; height: 2rem; }
            .menu-item { padding: .65rem .75rem; }
            .nav-mobile a { padding: .7rem .75rem; }
        }

        /* Fileiras de mini cards circulares (abas): sempre centralizadas, em qualquer largura */
        *:has(> .circ) {
            display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start;
            row-gap: 1.1rem; column-gap: .5rem;
        }

        /* Cabeçalho da página: título e subtítulo centralizados no celular */
        .cab-pagina { text-align: left; }
        @media (max-width: 639px) {
            .cab-pagina { text-align: center; }
            .cab-pagina p { margin-inline: auto; max-width: 32rem; }
        }

        /* Fundo personalizado: a imagem (ou GIF, com desfoque opcional) e um véu que escurece */
        #fundo-img { position: fixed; inset: 0; z-index: -1; pointer-events: none; background-position: center; }
        #fundo-veu { position: fixed; inset: 0; z-index: -1; pointer-events: none; }
        .fundo-cor {
            width: 2rem; height: 2rem; padding: 0; border-radius: 9999px; border: 1px solid var(--linha);
            background: none; cursor: pointer; overflow: hidden; flex-shrink: 0;
        }
        .fundo-cor::-webkit-color-swatch-wrapper { padding: 0; }
        .fundo-cor::-webkit-color-swatch { border: 0; border-radius: 9999px; }
        .fundo-cor::-moz-color-swatch { border: 0; border-radius: 9999px; }
        input[type=range] { accent-color: var(--prim); }

        /* Cabeçalho */
        .cab-bg { background: var(--cab); color: var(--on-cab); }
        header.cab-bg { border-bottom: 1px solid color-mix(in srgb, var(--on-cab) 12%, transparent); }
        .nav-link { display: flex; align-items: center; border-bottom: 2px solid transparent; opacity: .7; transition: opacity .15s; white-space: nowrap; }
        .nav-link:hover { opacity: 1; }
        .nav-link.ativo { opacity: 1; border-color: var(--prim); font-weight: 600; }
        .nav-mobile a { display: block; padding: .5rem .75rem; border-radius: .375rem; opacity: .8; }
        .nav-mobile a.ativo { background: color-mix(in srgb, var(--on-cab) 12%, transparent); opacity: 1; font-weight: 600; }
        /* Navegação de desktop com muitas áreas: rola na horizontal em vez de quebrar o cabeçalho */
        header nav[aria-label="Principal"] { min-width: 0; overflow-x: auto; scrollbar-width: none; }
        header nav[aria-label="Principal"]::-webkit-scrollbar { display: none; }

        /* Painel de entrada: malha discreta e brilho atrás da flor */
        .painel-tech {
            position: relative; overflow: hidden;
            background-color: var(--cab);
            background-image:
                radial-gradient(55% 45% at 50% 44%, color-mix(in srgb, var(--prim) 14%, transparent), transparent 70%),
                linear-gradient(color-mix(in srgb, var(--on-cab) 5%, transparent) 1px, transparent 1px),
                linear-gradient(90deg, color-mix(in srgb, var(--on-cab) 5%, transparent) 1px, transparent 1px);
            background-size: auto, 32px 32px, 32px 32px;
            box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--on-cab) 8%, transparent);
        }
        .flor-giro { transform-origin: 0 0; animation: flor-giro 180s linear infinite; }
        @keyframes flor-giro { to { transform: rotate(360deg); } }

        /* Botões */
        .btn, .btn-sec, .btn-cab {
            display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
            font-size: .875rem; font-weight: 600; border-radius: .5rem; padding: .6rem 1rem;
            transition: background-color .15s, border-color .15s;
        }
        .btn { background: var(--prim); color: var(--on-prim); }
        .btn:hover { background: var(--prim-forte); }
        .btn-sec { background: var(--superficie); color: var(--prim-forte); border: 1px solid var(--prim-linha); }
        .btn-sec:hover { background: var(--prim-suave); }
        .btn-cab { padding: .4rem .75rem; color: inherit; border: 1px solid color-mix(in srgb, var(--on-cab) 30%, transparent); }
        .btn-cab:hover { background: color-mix(in srgb, var(--on-cab) 12%, transparent); }
        .link-prim { color: var(--prim-forte); font-weight: 500; }
        .link-prim:hover { text-decoration: underline; }

        /* Campos */
        .campo {
            width: 100%; background: var(--superficie-2); color: var(--tinta); border: 1px solid var(--borda-campo); border-radius: .5rem;
            padding: .6rem .75rem; font-size: .9rem;
            transition: border-color .15s, box-shadow .15s;
        }
        .campo::placeholder { color: var(--tinta-2); opacity: .8; }
        .campo:focus { outline: none; border-color: var(--prim); box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent); }
        .rotulo { display: block; font-size: .85rem; font-weight: 500; margin-bottom: .35rem; }
        .erro { color: #f87171; font-size: .8rem; margin-top: .3rem; }
        .aviso-ok {
            background: var(--prim-suave); border: 1px solid var(--prim-linha); color: var(--prim-forte);
            border-radius: .5rem; padding: .65rem 1rem; font-size: .875rem; margin-bottom: 1.25rem;
            max-height: 10rem; overflow: hidden;
            transition: opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease, border-width .4s ease;
        }
        .aviso-ok.saindo {
            opacity: 0; max-height: 0; margin-bottom: 0;
            padding-top: 0; padding-bottom: 0; border-width: 0;
        }
        /* Checkbox no tema: caixa escura, marcada com a cor de destaque */
        input[type=checkbox] {
            appearance: none; -webkit-appearance: none;
            display: inline-grid; place-content: center;
            width: 1.15rem; height: 1.15rem; flex-shrink: 0;
            background: var(--superficie-2);
            border: 1px solid var(--borda-campo);
            border-radius: .3rem;
            cursor: pointer; vertical-align: middle;
            transition: background-color .15s, border-color .15s, box-shadow .15s;
        }
        input[type=checkbox]::after {
            content: "";
            width: .3rem; height: .6rem;
            border: solid var(--on-prim);
            border-width: 0 2px 2px 0;
            transform: translateY(-1px) rotate(45deg) scale(0);
            transition: transform .12s ease;
        }
        input[type=checkbox]:hover { border-color: var(--prim-linha); }
        input[type=checkbox]:checked {
            background: var(--prim);
            border-color: var(--prim);
        }
        input[type=checkbox]:checked::after {
            transform: translateY(-1px) rotate(45deg) scale(1);
        }
        input[type=checkbox]:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        input[type=checkbox]:disabled { opacity: .5; cursor: not-allowed; }

        /* Cards */
        .card { background: var(--superficie); border: 1px solid var(--linha); border-radius: .75rem; transition: border-color .2s; }
        a.card:hover { border-color: var(--prim); }
        .icone { flex-shrink: 0; width: 2.75rem; height: 2.75rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: var(--prim-suave); color: var(--prim-forte); }
        .barra { height: 6px; border-radius: 9999px; background: var(--prim-suave); overflow: hidden; }
        .barra span { display: block; height: 100%; background: var(--prim); border-radius: 9999px; }

        /* Tabelas */
        .tabela-wrap { background: var(--superficie); border: 1px solid var(--linha); border-radius: .75rem; overflow: hidden; }
        .tabela { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .tabela thead { background: var(--cab); color: var(--on-cab); }
        .tabela th { text-align: left; font-weight: 500; padding: .75rem 1rem; }
        .tabela td { padding: .75rem 1rem; border-top: 1px solid var(--linha); vertical-align: middle; }
        .tabela tbody tr:hover { background: var(--prim-suave); }
        .select-status { font-size: .8rem; padding: .3rem .5rem; border-radius: .375rem; border: 1px solid var(--prim-linha); background: var(--prim-suave); color: var(--prim-forte); }

        /* Seletor de tema */
        .preset { border: 1px solid var(--linha); border-radius: .5rem; padding: .5rem; text-align: left; transition: border-color .15s; }
        .preset:hover { border-color: var(--prim); }
        .seletor { display: block; width: 100%; height: 2.5rem; margin-top: .35rem; border: 1px solid var(--borda-campo); border-radius: .5rem; padding: 2px; background: var(--superficie-2); cursor: pointer; }

        /* Mini botões de ação (só ícone) */
        .mini-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 1.5rem; height: 1.5rem; border-radius: .375rem;
            color: var(--tinta-2); border: 1px solid transparent;
            transition: color .15s, background-color .15s, border-color .15s;
        }
        .mini-btn:hover { color: var(--prim-forte); background: var(--prim-suave); border-color: var(--prim-linha); }
        .mini-btn.perigo:hover {
            color: #f87171;
            background: color-mix(in srgb, #f87171 14%, var(--superficie));
            border-color: color-mix(in srgb, #f87171 40%, var(--superficie));
        }

        /* Aba Atalhos: interruptor e teclas */
        .wf-opcao { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
        .wf-chave { position: relative; flex: none; width: 2.75rem; height: 1.5rem; border-radius: 999px; margin-top: .125rem;
                    background: color-mix(in srgb, var(--texto, #e5e7eb) 18%, transparent); border: 1px solid var(--linha); transition: background .15s; }
        .wf-chave::after { content: ''; position: absolute; top: .1875rem; left: .1875rem; width: 1rem; height: 1rem; border-radius: 999px;
                           background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.35); }
        .wf-chave[aria-checked="true"] { background: var(--prim); border-color: var(--prim); }
        .wf-chave[aria-checked="true"]::after { transform: translateX(1.25rem); }
        .wf-chave:focus-visible { outline: 2px solid var(--prim); outline-offset: 2px; }
        .wf-teclas li { display: flex; justify-content: space-between; gap: .75rem; padding: .375rem 0; }
        .wf-teclas li + li { border-top: 1px dashed var(--linha); }
        .wf-teclas kbd { display: inline-block; min-width: 1.5rem; padding: .05rem .4rem; border-radius: .3rem; text-align: center;
                         font: 600 .75rem/1.4 ui-monospace, monospace; border: 1px solid var(--linha); border-bottom-width: 2px; }
        .wf-tecla-btn { display: inline-flex; align-items: center; min-height: 1.75rem; padding: .1rem .35rem; border-radius: .4rem; border: 1px solid transparent; }
        .wf-tecla-btn:hover { border-color: var(--linha); }
        .wf-tecla-btn.gravando { border-color: var(--prim); color: var(--prim-forte); animation: wf-pisca-borda 1s ease-in-out infinite; }
        @keyframes wf-pisca-borda { 50% { border-color: transparent; } }
        .wf-topo-btn { position: fixed; z-index: 41; right: max(1rem, var(--seg-d, 0px)); bottom: calc(1rem + var(--seg-b, 0px));
                       width: 2.75rem; height: 2.75rem; border-radius: 9999px; display: grid; place-content: center;
                       background: var(--superficie); color: var(--tinta); border: 1px solid var(--linha);
                       box-shadow: 0 8px 24px rgba(0,0,0,.4); opacity: 0; pointer-events: none; transform: translateY(.5rem);
                       transition: opacity .2s, transform .2s, border-color .15s; }
        .wf-topo-btn.visivel { opacity: 1; pointer-events: auto; transform: none; }
        .wf-topo-btn.acima { bottom: calc(4.25rem + var(--seg-b, 0px)); }
        .wf-topo-btn:hover { border-color: var(--prim); color: var(--prim-forte); }
        @media (prefers-reduced-motion: reduce) { .wf-topo-btn { transition: none; } }
        /* Menu de configurações (engrenagem) */
        .menu-pop {
            background: var(--superficie); color: var(--tinta);
            border: 1px solid var(--linha); border-radius: .75rem;
            box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
            max-width: calc(100vw - 1.5rem);
        }
        .menu-item {
            display: flex; align-items: center; gap: .65rem; width: 100%;
            padding: .5rem .65rem; border-radius: .5rem; font-size: .875rem; text-align: left;
            color: var(--tinta); transition: background-color .15s, color .15s;
        }
        .menu-item:hover, .menu-item:focus-visible { background: var(--prim-suave); color: var(--prim-forte); }
        .menu-sep { height: 1px; margin: .3rem .25rem; background: var(--linha); }


        /* Selects: valem para a página inteira e seguem as cores do tema */
        select {
            appearance: none; -webkit-appearance: none;
            background-color: var(--superficie-2);
            color: var(--tinta);
            border: 1px solid var(--borda-campo);
            border-radius: .5rem;
            cursor: pointer;
            max-width: 100%;
            transition: border-color .15s, box-shadow .15s;
        }
        /* Select puro (sem classe) ganha o mesmo tamanho dos campos */
        select:not([class]) { padding: .5rem .75rem; font-size: .9rem; }

        /* Setinha desenhada com as cores do tema (também em .campo e .select-status) */
        select, select.campo, select.select-status {
            padding-right: 2rem;
            background-image:
                linear-gradient(45deg, transparent 50%, var(--prim-forte) 50%),
                linear-gradient(135deg, var(--prim-forte) 50%, transparent 50%);
            background-position: calc(100% - 1.15rem) 50%, calc(100% - .85rem) 50%;
            background-size: .3rem .3rem, .3rem .3rem;
            background-repeat: no-repeat;
        }

        select:hover { border-color: var(--prim-linha); }
        select:focus {
            outline: none;
            border-color: var(--prim);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        select:disabled { opacity: .5; cursor: not-allowed; }

        /* Lista que abre ao clicar */
        select option, select optgroup { background: var(--superficie); color: var(--tinta); }
        select option:checked { background: var(--prim); color: var(--on-prim); }

        /* Mini cards circulares */
        .circ { display: flex; flex-direction: column; align-items: center; gap: .45rem; width: 5rem; cursor: pointer; }
        .circ-bola {
            width: 3.5rem; height: 3.5rem; border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            background: var(--superficie); border: 1px solid var(--linha); color: var(--tinta-2);
            transition: transform .15s, border-color .2s, background-color .2s, color .2s, box-shadow .2s;
        }
        .circ:hover .circ-bola { border-color: var(--prim-linha); color: var(--prim-forte); transform: translateY(-2px); }
        .circ.ativo .circ-bola {
            background: var(--prim); border-color: var(--prim); color: var(--on-prim);
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--prim) 25%, transparent);
        }
        /* Rótulos longos quebram com hífen em vez de cortar no meio da palavra */
        .circ-rotulo {
            font-size: .75rem; text-align: center; line-height: 1.15; color: var(--tinta-2); transition: color .15s;
            max-width: 100%; overflow-wrap: break-word; word-break: normal; hyphens: auto; -webkit-hyphens: auto;
        }
        .circ:hover .circ-rotulo, .circ.ativo .circ-rotulo { color: var(--tinta); }
        .erro:empty { display: none; }
        /* Tabelas no celular: cada linha vira um cartão */
        @media (max-width: 767px) {
            .tabela-wrap { border: 0; background: transparent; border-radius: 0; overflow: visible; }
            .tabela, .tabela tbody, .tabela tr, .tabela td { display: block; width: 100%; }
            .tabela thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
            .tabela tbody tr { background: var(--superficie); border: 1px solid var(--linha); border-radius: .6rem; margin-bottom: .75rem; padding: .25rem 0; }
            .tabela tbody tr:hover { background: var(--superficie); }
            .tabela td { display: flex; justify-content: space-between; align-items: center; gap: 1rem; text-align: right; border: 0; padding: .5rem 1rem; }
            .tabela td::before { content: attr(data-label); flex-shrink: 0; text-align: left; font-size: .8rem; font-weight: 600; color: var(--tinta-2); }
            .tabela td:not([data-label])::before { display: none; }
        }

        /* Painel de cores/biblioteca/workflows: no celular vira uma folha encaixada, sem estourar a altura */
        @media (max-width: 639px) {
            .painel-config { top: 4.25rem !important; left: .5rem !important; right: .5rem !important; max-height: calc(100dvh - 5rem) !important; padding: 1rem !important; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
            .flor-giro { animation: none; }
        }
    </style>
</head>
<body>

@auth
    {{-- Camadas do fundo personalizado (ficam atrás de tudo) --}}
    <div id="fundo-img" aria-hidden="true" style="{{ $fundoEstiloImg }}"></div>
    <div id="fundo-veu" aria-hidden="true" style="{{ $fundoEstiloVeu }}"></div>

    <header class="cab-bg sticky top-0 z-30" x-data="{ menu: false }">
        <div class="max-w-6xl mx-auto px-4 sm:px-8 h-16 flex items-center justify-between gap-4">
            <a href="{{ route('painel') }}" class="shrink-0" aria-label="Work Flower, ir para o painel">
                @include('partials.marca', ['texto' => 'text-xl'])
            </a>

            <nav class="hidden md:flex items-stretch h-16 gap-6 text-sm" aria-label="Principal">
                <a href="{{ route('painel') }}" class="nav-link {{ request()->routeIs('painel') ? 'ativo' : '' }}">Painel</a>
                @foreach (\App\Models\Tarefa::AREAS as $slug => $a)
                    <a href="{{ route('areas.show', $slug) }}" class="nav-link {{ request()->is('areas/'.$slug) ? 'ativo' : '' }}">{{ $a['nome'] }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Configurações --}}
                <div class="relative" x-data="{ aberto: false }" @keydown.escape.window="aberto = false" @click.outside="aberto = false">
                    <button type="button" class="btn-cab !p-2" @click="aberto = !aberto"
                            aria-haspopup="menu" :aria-expanded="aberto" aria-label="Configurações" title="Configurações">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18.97 9.84L21.43 10.19 L21.43 13.81 L18.97 14.16A7.3 7.3 0 0 1 18.46 15.41L19.95 17.38 L17.38 19.95 L15.41 18.46A7.3 7.3 0 0 1 14.16 18.97L13.81 21.43 L10.19 21.43 L9.84 18.97A7.3 7.3 0 0 1 8.59 18.46L6.62 19.95 L4.05 17.38 L5.54 15.41A7.3 7.3 0 0 1 5.03 14.16L2.57 13.81 L2.57 10.19 L5.03 9.84A7.3 7.3 0 0 1 5.54 8.59L4.05 6.62 L6.62 4.05 L8.59 5.54A7.3 7.3 0 0 1 9.84 5.03L10.19 2.57 L13.81 2.57 L14.16 5.03A7.3 7.3 0 0 1 15.41 5.54L17.38 4.05 L19.95 6.62 L18.46 8.59A7.3 7.3 0 0 1 18.97 9.84 Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>

                    <div x-show="aberto" x-cloak x-transition.origin.top.right.duration.150ms
                        class="menu-pop absolute right-0 top-full mt-2 w-48 p-1.5 z-40" role="menu">
                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-tema')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5C9 7.5 6.5 10.2 6.5 13.5a5.5 5.5 0 0011 0C17.5 10.2 15 7.5 12 3.5z"/></svg>
                            Cores
                        </button>

                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-biblioteca')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3z"/></svg>
                            Biblioteca
                        </button>

                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-atalhos')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 3L5 14h6l-1 7 8-11h-6z"/></svg>
                            Atalhos
                        </button>

                        <button type="button" class="menu-item" role="menuitem" @click="aberto = false; $dispatch('abrir-workflows')">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                            Importar / exportar
                        </button>

                        <a href="{{ \Illuminate\Support\Facades\Route::has('planos') ? route('planos') : url('/planos') }}"
                           class="menu-item" role="menuitem" style="color: var(--prim-forte); font-weight: 600;">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9 6.8 19.6l1-5.8L3.5 9.7l5.9-.9z"/></svg>
                            Torne-se Pró
                        </a>

                        <div class="menu-sep" role="separator"></div>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="menu-item" role="menuitem">
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h5v16h-5M4 12h9m0 0l-3-3m3 3l-3 3"/></svg>
                                Sair
                            </button>
                        </form>
                    </div>
                </div>

                <button type="button" class="md:hidden p-2 rounded-md" @click="menu = !menu" aria-label="Abrir menu" :aria-expanded="menu">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        <nav x-show="menu" x-cloak class="nav-mobile md:hidden px-4 pb-3 space-y-1 text-sm" aria-label="Principal (celular)">
            <a href="{{ route('painel') }}" class="{{ request()->routeIs('painel') ? 'ativo' : '' }}">Painel</a>
            @foreach (\App\Models\Tarefa::AREAS as $slug => $a)
                <a href="{{ route('areas.show', $slug) }}" class="{{ request()->is('areas/'.$slug) ? 'ativo' : '' }}">{{ $a['nome'] }}</a>
            @endforeach
        </nav>
    </header>

    <div class="cab-pagina max-w-6xl mx-auto px-4 sm:px-8 pt-8 sm:pt-10 pb-6">
        <h1 class="titulo text-3xl sm:text-4xl">@yield('titulo', 'Painel')</h1>
        <p class="texto-2 text-sm mt-1.5">@yield('subtitulo')</p>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-8 pb-16">
        <div data-atualiza="avisos">
            @if (session('ok'))
                <div class="aviso-ok" role="status"
                    x-data="{ saindo: false }"
                    x-init="setTimeout(() => saindo = true, 3000)"
                    :class="{ 'saindo': saindo }">
                    {{ session('ok') }}
                </div>
            @endif
        </div>
        @yield('conteudo')
    </main>

    {{-- Painel de cores e fundo: fica ao lado, sem escurecer a tela, para ver o resultado enquanto ajusta --}}
    <div x-data="temaForm(@js($prim), @js($cab), @js($fundoIni), @js($fundoUrl), @js($fundoSalvar))" x-show="aberto" x-cloak
         @abrir-tema.window="aberto = true; aba = 'cores'" @abrir-biblioteca.window="aberto = true; aba = 'biblioteca'" @abrir-lixeira.window="aberto = true; aba = 'lixeira'" @abrir-workflows.window="aberto = true; aba = 'workflows'" @abrir-atalhos.window="aberto = true; aba = 'atalhos'"
         @keydown.escape.window="if (aberto) cancelar()"
         class="fixed inset-0 z-50"
         role="dialog" aria-modal="true" aria-labelledby="tema-titulo">
        <div class="absolute inset-0" @click="cancelar()"></div>

        <div class="painel-config card shadow-xl absolute top-20 right-4 left-4 sm:left-auto sm:w-[26rem] max-h-[calc(100vh-6rem)] max-h-[calc(100dvh-6rem)] overflow-auto p-6 space-y-5">

        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Configurações">
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'cores' }" :aria-selected="aba === 'cores'" @click="aba = 'cores'">Cores e fundo</button>
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'biblioteca' }" :aria-selected="aba === 'biblioteca'" @click="aba = 'biblioteca'; $dispatch('abrir-biblioteca')">Biblioteca</button>
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'lixeira' }" :aria-selected="aba === 'lixeira'" @click="aba = 'lixeira'; $dispatch('abrir-lixeira')">Histórico</button>
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'workflows' }" :aria-selected="aba === 'workflows'" @click="aba = 'workflows'">Workflows</button>
            <button type="button" role="tab" class="btn-sec flex-1" :class="{ '!bg-[var(--prim)] !text-[var(--on-prim)]': aba === 'atalhos' }" :aria-selected="aba === 'atalhos'" @click="aba = 'atalhos'">Atalhos</button>
        </div>

        <form method="POST" action="{{ route('tema.update') }}" @submit.prevent="salvar($event)" x-show="aba === 'cores'" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 id="tema-titulo" class="titulo text-2xl">Cores e fundo</h2>
                <p class="text-sm texto-2 mt-1">Escolha um tema pronto, ajuste as cores e personalize o fundo da página. O resultado aparece ao lado enquanto você ajusta.</p>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <template x-for="p in presets" :key="p.nome">
                    <button type="button" class="preset" @click="aplicar(p.p, p.c)">
                        <span class="flex h-6 rounded overflow-hidden mb-1.5">
                            <span class="flex-1" :style="'background:' + p.c"></span>
                            <span class="flex-1" :style="'background:' + p.p"></span>
                        </span>
                        <span class="text-xs" x-text="p.nome"></span>
                    </button>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <label class="text-sm font-medium">Botões e destaques
                    <input type="color" name="cor_primaria" x-model="prim" @input="previa()" class="seletor">
                </label>
                <label class="text-sm font-medium">Cabeçalhos e tabelas
                    <input type="color" name="cor_cabecalho" x-model="cab" @input="previa()" class="seletor">
                </label>
            </div>

            {{-- ===== Fundo da página ===== --}}
            <div class="pt-5 space-y-5" style="border-top: 1px solid var(--linha)">
                <div>
                    <h3 class="titulo text-lg">Fundo da página</h3>
                    <p class="text-xs texto-2 mt-1">Cor sólida, imagem ou GIF animado. Vale para todas as páginas.</p>
                </div>

                <div>
                    <label class="rotulo">Cor de fundo</label>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-for="c in coresFundo" :key="c.nome">
                            <button type="button" class="fundo-cor" :title="c.nome" :aria-label="c.nome" :aria-pressed="f.cor === c.v"
                                    :style="'background:' + (c.v || 'color-mix(in srgb, var(--prim) 2%, #090c10)') + ';' + (f.cor === c.v ? 'border-color:var(--prim);box-shadow:0 0 0 3px color-mix(in srgb, var(--prim) 25%, transparent)' : '')"
                                    @click="f.cor = c.v; previaFundo()"></button>
                        </template>
                        <input type="color" class="fundo-cor" title="Outra cor" aria-label="Escolher outra cor"
                               :value="f.cor || '#090c10'" @input="f.cor = $event.target.value; previaFundo()">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="rotulo">Imagem ou GIF</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="btn-sec cursor-pointer">
                            <span x-text="imagemAtual ? 'Trocar arquivo' : 'Escolher arquivo'"></span>
                            <input type="file" accept="image/*" class="sr-only"
                                   @change="escolherImagem($event.target.files[0]); $event.target.value = ''">
                        </label>
                        <button type="button" class="link-prim text-sm" x-show="imagemAtual" @click="tirarImagem()">Remover</button>
                    </div>
                    <p class="text-xs texto-2">JPG e PNG são reduzidos sozinhos. GIF e WebP animados mantêm a animação e podem ter até 4 MB.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="rotulo">Como encaixar</label>
                        <select class="campo" :disabled="!imagemAtual" @change="f.ajuste = $event.target.value; previaFundo()">
                            <option value="cover" :selected="f.ajuste === 'cover'">Preencher a tela</option>
                            <option value="contain" :selected="f.ajuste === 'contain'">Mostrar inteira</option>
                            <option value="repeat" :selected="f.ajuste === 'repeat'">Repetir como padrão</option>
                        </select>
                    </div>
                    <div>
                        <label class="rotulo flex justify-between"><span>Escurecer</span><span class="texto-2 font-normal" x-text="f.escurecer + '%'"></span></label>
                        <input type="range" min="0" max="90" step="5" class="w-full" :disabled="!imagemAtual"
                               :value="f.escurecer" @input="f.escurecer = +$event.target.value; previaFundo()">
                    </div>
                    <div>
                        <label class="rotulo flex justify-between"><span>Desfoque</span><span class="texto-2 font-normal" x-text="f.desfoque + ' px'"></span></label>
                        <input type="range" min="0" max="20" step="1" class="w-full" :disabled="!imagemAtual"
                               :value="f.desfoque" @input="f.desfoque = +$event.target.value; previaFundo()">
                    </div>
                </div>

                <p class="erro" x-text="erro"></p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <button type="button" class="link-prim text-sm" @click="restaurarFundo()">Restaurar fundo</button>
                <div class="flex gap-2">
                    <button type="button" class="btn-sec" @click="cancelar()">Cancelar</button>
                    <button type="submit" class="btn" :disabled="salvando" x-text="salvando ? 'Salvando…' : 'Salvar'"></button>
                </div>
            </div>
        </form>

        {{-- ===== Biblioteca: blocos salvos, para colar na página atual ===== --}}
        <section x-show="aba === 'biblioteca'" x-cloak x-data="bibliotecaPainel()" class="space-y-4">
            <div>
                <h2 class="titulo text-2xl">Biblioteca</h2>
                <p class="text-sm texto-2 mt-1">Use o ícone de marcador no cabeçalho de um bloco para salvá-lo aqui. Depois, em qualquer página, clique em “Colar aqui” para criar uma cópia.</p>
            </div>

            <div class="flex gap-2">
                <input class="campo" x-model="busca" placeholder="Buscar" aria-label="Buscar na biblioteca">
                <select class="campo !w-auto" x-model="tipo" aria-label="Filtrar por tipo">
                    <option value="">Todos</option>
                    <template x-for="(nome, id) in tipos" :key="id"><option :value="id" x-text="nome"></option></template>
                </select>
            </div>

            <p class="text-xs texto-2" x-show="!pode && !carregando">Esta página não tem quadro de blocos, então não dá para colar aqui. Abra uma página com quadro (por exemplo, Carteira).</p>
            <p class="erro" x-text="erro"></p>
            <p class="text-sm texto-2" x-show="carregando">Carregando…</p>
            <p class="text-sm texto-2" x-show="!carregando && !itens.length">Nada salvo ainda.</p>
            <p class="text-sm texto-2" x-show="!carregando && itens.length && !filtrados.length">Nenhum item com esse filtro.</p>

            <ul class="space-y-2">
                <template x-for="i in filtrados" :key="i.id">
                    <li class="card p-3 space-y-2" :style="i.cor ? 'border-top:2px solid ' + i.cor : ''">
                        <div class="flex items-center gap-2">
                            <input class="campo !py-1 flex-1 font-medium" :value="i.nome" @change="renomear(i, $event.target.value)" aria-label="Nome do item">
                            <button type="button" class="mini-btn perigo" title="Excluir da biblioteca" @click="remover(i)">✕</button>
                        </div>
                        <template x-if="i.tipo === 'imagem' && i.imagem">
                            <img :src="i.imagem" alt="" class="w-full h-24 object-cover rounded-lg">
                        </template>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs texto-2 min-w-0 truncate"><span x-text="rotuloTipo(i.tipo)"></span> · <span x-text="resumo(i)"></span></p>
                            <button type="button" class="btn !py-1.5 shrink-0" :disabled="!pode" :class="{ 'opacity-50 cursor-not-allowed': !pode }" @click="colar(i)">Colar aqui</button>
                        </div>
                    </li>
                </template>
            </ul>
        </section>

        {{-- ===== Histórico: blocos excluídos desta página, para reconstruir ===== --}}
        <section x-show="aba === 'lixeira'" x-cloak x-data="lixeiraPainel()" class="space-y-4">
            <div>
                <h2 class="titulo text-2xl">Histórico</h2>
                <p class="text-sm texto-2 mt-1">Blocos excluídos desta página. Clique em “Restaurar” para recriá-los. Se a página for excluída, o histórico dela também some.</p>
            </div>

            <p class="text-xs texto-2" x-show="!pode">Esta página não tem quadro de blocos, então não dá para restaurar aqui.</p>
            <p class="text-sm texto-2" x-show="!itens.length">Nenhum bloco excluído nesta página.</p>

            <ul class="space-y-2">
                <template x-for="i in itens" :key="i.id">
                    <li class="card p-3 space-y-2" :style="i.cor ? 'border-top:2px solid ' + i.cor : ''">
                        <div class="flex items-center gap-2">
                            <p class="flex-1 min-w-0 truncate font-medium" x-text="i.titulo || i.nome"></p>
                            <span class="text-xs texto-2 shrink-0" x-text="quando(i)"></span>
                            <button type="button" class="mini-btn perigo" title="Apagar de vez" @click="excluir(i)">✕</button>
                        </div>
                        <template x-if="i.tipo === 'imagem' && i.imagem">
                            <img :src="i.imagem" alt="" class="w-full h-24 object-cover rounded-lg">
                        </template>
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs texto-2 min-w-0 truncate"><span x-text="rotuloTipo(i.tipo)"></span> · <span x-text="resumo(i)"></span></p>
                            <button type="button" class="btn !py-1.5 shrink-0" :disabled="!pode || !colavel(i)" :class="{ 'opacity-50 cursor-not-allowed': !pode || !colavel(i) }" @click="restaurar(i)">Restaurar</button>
                        </div>
                    </li>
                </template>
            </ul>

            <button type="button" class="btn-sec" x-show="itens.length" @click="esvaziar()">Esvaziar histórico</button>
        </section>

        {{-- ===== Workflows: baixar as áreas em um arquivo e importar de outra conta ===== --}}
        <section x-show="aba === 'workflows'" x-cloak x-data="workflowsTransfer()"
                 x-init="$watch('aba', v => { if (v === 'workflows') carregarUmaVez() })" class="space-y-5">
            <div>
                <h2 class="titulo text-2xl">Importar e exportar</h2>
                <p class="text-sm texto-2 mt-1">Baixe Corpo, Mente, Carteira e Diverso em um único arquivo e envie para outra conta. Quem importar recebe esses dados no lugar dos que tem hoje.</p>
            </div>

            <div class="grid grid-cols-1 min-[420px]:grid-cols-2 gap-2">
                <button type="button" class="btn" :disabled="exportando" @click="exportarTudo()">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14"/></svg>
                    <span x-text="exportando ? 'Gerando…' : 'Baixar tudo'"></span>
                </button>
                <label class="btn-sec cursor-pointer">
                    <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20V9m0 0l-4 4m4-4l4 4M5 5h14"/></svg>
                    <span>Importar arquivo</span>
                    <input type="file" accept=".json,application/json" class="sr-only"
                           @change="escolherArquivo($event.target.files[0]); $event.target.value = ''">
                </label>
            </div>

            <p class="erro" x-show="!configurado">Faltam as rotas: <span x-text="faltando"></span>.</p>
            <p class="erro" x-text="erro"></p>
            <button type="button" class="link-prim text-sm" x-show="tentou && !carregado && !carregando && configurado" @click="carregar()">Recarregar lista</button>
            <p class="aviso-ok !mb-0" x-show="ok" x-text="ok" role="status"></p>

            {{-- Baixar só algumas áreas --}}
            <div class="space-y-3 pt-5" style="border-top: 1px solid var(--linha)" x-show="configurado && itens.length">
                <h3 class="titulo text-lg">Baixar só algumas áreas</h3>

                <ul class="space-y-2">
                    <template x-for="i in itens" :key="i.id">
                        <li class="card p-2.5 flex items-center gap-3">
                            <input type="checkbox" :value="i.id" x-model="selecionados" :aria-label="'Selecionar ' + i.nome">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium truncate" x-text="i.nome"></p>
                                <p class="text-xs texto-2" x-text="resumo(i)"></p>
                            </div>
                        </li>
                    </template>
                </ul>

                <button type="button" class="btn-sec w-full" :disabled="exportando || !selecionados.length" @click="exportarMarcadas()"
                        x-text="selecionados.length ? 'Baixar ' + selecionados.length + ' selecionada(s)' : 'Marque as áreas acima'"></button>
            </div>

            {{-- Pré-visualização do arquivo escolhido --}}
            <template x-if="pacote">
                <div class="space-y-3 pt-5" style="border-top: 1px solid var(--linha)">
                    <h3 class="titulo text-lg">Importar</h3>
                    <p class="text-xs texto-2 truncate" x-text="nomeArquivo"></p>
                    <p class="erro" x-show="avisoIntegridade">Este arquivo foi alterado depois de baixado. Importe só se você confia na origem.</p>

                    <ul class="space-y-2">
                        <template x-for="(w, idx) in pacote.workflows" :key="idx">
                            <li class="card p-2.5 flex items-center gap-3">
                                <input type="checkbox" :checked="selImport.includes(idx)" @change="alternarImport(idx)" :aria-label="'Importar ' + w.nome">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium truncate" x-text="w.nome"></p>
                                    <p class="text-xs texto-2" x-text="resumoPacote(w)"></p>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <p class="text-xs texto-2">Importar <strong>substitui</strong> o conteúdo atual das áreas marcadas. Baixe um backup antes se quiser guardá-lo.</p>

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" class="btn-sec" @click="cancelarImportacao()">Cancelar</button>
                        <button type="button" class="btn" :disabled="importando || !selImport.length" @click="importar()"
                                x-text="importando ? 'Importando…' : 'Importar ' + selImport.length"></button>
                    </div>
                </div>
            </template>
        </section>
        {{-- Atalhos: organização automática dos cartões (opcional) e teclas de atalho personalizáveis --}}
        <section x-show="aba === 'atalhos'" x-cloak x-data="atalhosPainel()" class="space-y-5"
                 x-init="$watch('aba', v => { if (v === 'atalhos') atualizar(); else parar() })">
            <div>
                <h2 class="titulo text-2xl">Atalhos</h2>
                <p class="text-sm texto-2 mt-1">Ajustes rápidos dos quadros e as teclas que fazem as ações mais comuns. Tudo fica salvo na sua conta.</p>
            </div>

            <div class="wf-opcao">
                <div class="min-w-0">
                    <p class="font-semibold text-sm" id="org-auto-rotulo">Organizar cartões automaticamente</p>
                    <p class="text-xs texto-2 mt-1">Ao abrir a página ou mudar o tamanho da janela, os cartões são encaixados por tamanho, sem buracos e sem ficar um por cima do outro. Desligado, cada cartão fica exatamente onde você deixou.</p>
                </div>
                <button type="button" role="switch" class="wf-chave" :aria-checked="ligado ? 'true' : 'false'"
                        aria-labelledby="org-auto-rotulo" @click="alternar()">
                    <span class="sr-only" x-text="ligado ? 'Ligado' : 'Desligado'"></span>
                </button>
            </div>

            <div class="space-y-2">
                <button type="button" class="btn w-full disabled:opacity-50 disabled:cursor-not-allowed" :disabled="!temQuadro" @click="reorganizarAgora()">Reorganizar a aba atual agora</button>
                <p class="text-xs texto-2" x-show="!temQuadro">Disponível nas páginas com quadro: Corpo, Mente, Carteira e Diverso.</p>
            </div>

            <div class="space-y-3" style="border-top: 1px solid var(--linha); padding-top: 1rem">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-sm">Teclas de atalho</p>
                    <button type="button" class="link-prim text-xs" x-show="temPersonalizado()" @click="restaurarTodos()">Voltar todas ao padrão</button>
                </div>
                <p class="text-xs texto-2">Clique numa tecla para trocar e aperte a combinação nova (use <kbd>Alt</kbd> ou <kbd>Ctrl</kbd> junto). <kbd>Esc</kbd> cancela, <kbd>Backspace</kbd> deixa a ação sem tecla. As ações de cartão agem no último cartão em que você clicou.</p>
                <p class="aviso-ok text-xs" x-show="nota" x-text="nota" role="status"></p>

                <template x-for="g in grupos()" :key="g">
                    <div>
                        <p class="text-xs font-semibold texto-2 uppercase tracking-wide mb-1" x-text="g"></p>
                        <ul class="wf-teclas text-sm">
                            <template x-for="a in acoesDo(g)" :key="a.id">
                                <li>
                                    <span class="min-w-0" x-text="a.nome"></span>
                                    <span class="flex items-center gap-1 shrink-0">
                                        <button type="button" class="wf-tecla-btn" :class="{ 'gravando': gravando === a.id }" @click="gravar(a.id)"
                                                :aria-label="'Trocar a tecla de: ' + a.nome" :title="gravando === a.id ? 'Aperte a combinação nova…' : 'Clique para trocar'">
                                            <template x-if="gravando === a.id"><span>Aperte…</span></template>
                                            <template x-if="gravando !== a.id && tecla(a.id)">
                                                <span class="flex items-center gap-1">
                                                    <template x-for="(p, i) in partes(tecla(a.id))" :key="i"><kbd x-text="p"></kbd></template>
                                                </span>
                                            </template>
                                            <template x-if="gravando !== a.id && !tecla(a.id)"><span class="texto-2">sem tecla</span></template>
                                        </button>
                                        <button type="button" class="mini-btn" x-show="personalizado(a.id)" @click="padrao(a.id)" title="Voltar ao padrão" aria-label="Voltar ao padrão">↺</button>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
                <p class="text-xs texto-2"><kbd>Esc</kbd> fecha este painel e <kbd>Ctrl</kbd> + clique marca vários cartões (essas duas não mudam).</p>
            </div>
        </section>

        </div>
    </div>

    {{-- Voltar ao topo: aparece depois de rolar a página (atalho padrão Alt + ↑) --}}
    <button type="button" id="wf-topo" class="wf-topo-btn" aria-label="Voltar ao topo da página" title="Voltar ao topo">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5m0 0l-6 6m6-6l6 6"/></svg>
    </button>

    <script>
        function temaForm(primInicial, cabInicial, fundoIni, urlImagem, urlFundo) {
            const PAPEL_PADRAO = 'color-mix(in srgb, var(--prim) 2%, #090c10)';
            const LIMITE_ARQUIVO = 4000000; // GIF/WebP animados entram sem alteração, até 4 MB
            const copia = o => JSON.parse(JSON.stringify(o));

            /** Lê o arquivo como está (mantém a animação do GIF). */
            function lerComoTexto(arquivo) {
                return new Promise((ok, falha) => {
                    const r = new FileReader();
                    r.onload = () => ok(r.result);
                    r.onerror = () => falha(r.error);
                    r.readAsDataURL(arquivo);
                });
            }

            /** Reduz JPG/PNG e devolve um JPEG em texto (data URL). */
            function reduzir(arquivo, max, qualidade) {
                return new Promise((ok, falha) => {
                    const img = new Image();
                    const url = URL.createObjectURL(arquivo);
                    img.onload = () => {
                        const k = Math.min(1, max / Math.max(img.width, img.height));
                        const cv = document.createElement('canvas');
                        cv.width = Math.round(img.width * k);
                        cv.height = Math.round(img.height * k);
                        const cx = cv.getContext('2d');
                        cx.fillStyle = '#fff';
                        cx.fillRect(0, 0, cv.width, cv.height);
                        cx.drawImage(img, 0, 0, cv.width, cv.height);
                        URL.revokeObjectURL(url);
                        ok(cv.toDataURL('image/jpeg', qualidade));
                    };
                    img.onerror = () => { URL.revokeObjectURL(url); falha(new Error('imagem')); };
                    img.src = url;
                });
            }

            const nucleo = x => JSON.stringify([x.cor, x.ajuste, +x.escurecer, +x.desfoque]);

            return {
                aberto: false,
                aba: 'cores',
                prim: primInicial,
                cab: cabInicial,
                presets: [
                    { nome: 'Sinal',   p: '#f2c230', c: '#0e1319' },
                    { nome: 'Caverna', p: '#2dd4bf', c: '#0b1417' },
                    { nome: 'Neon',    p: '#a78bfa', c: '#100d1c' },
                    { nome: 'Brasa',   p: '#fb923c', c: '#161009' },
                    { nome: 'Rosé',    p: '#f472b6', c: '#170b12' },
                    { nome: 'Aço',     p: '#94a3b8', c: '#0c1118' },
                ],

                // fundo
                salvando: false,
                erro: '',
                salvo: copia(fundoIni),   // o que está gravado no servidor
                f: copia(fundoIni),       // o que está sendo editado
                novaImagem: null,         // arquivo escolhido e ainda não salvo
                removerImagem: false,
                coresFundo: [
                    { nome: 'Padrão', v: null },
                    { nome: 'Preto', v: '#05070a' },
                    { nome: 'Grafite', v: '#12161c' },
                    { nome: 'Marinho', v: '#0a1424' },
                    { nome: 'Ameixa', v: '#150c1d' },
                    { nome: 'Floresta', v: '#0b1a14' },
                    { nome: 'Terra', v: '#1a120b' },
                ],

                get imagemAtual() {
                    if (this.novaImagem) return this.novaImagem;
                    if (this.removerImagem || !this.f.imagem || !urlImagem) return null;
                    return urlImagem + '?v=' + this.f.versao;
                },

                // ----- cores do tema -----
                aplicar(p, c) { this.prim = p; this.cab = c; this.previa(); },
                previa() {
                    const r = document.documentElement.style;
                    r.setProperty('--prim', this.prim);
                    r.setProperty('--cab', this.cab);
                    r.setProperty('--on-prim', this.contraste(this.prim));
                    r.setProperty('--on-cab', this.contraste(this.cab));
                },
                contraste(hex) {
                    const n = parseInt(hex.slice(1), 16);
                    const lum = (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
                    return lum > 0.6 ? '#14171b' : '#ffffff';
                },

                // ----- fundo -----
                /** Mostra na hora, na própria página, o que está sendo escolhido. */
                previaFundo() {
                    const raiz = document.documentElement.style;
                    const img = document.getElementById('fundo-img');
                    const veu = document.getElementById('fundo-veu');
                    raiz.setProperty('--papel', this.f.cor || PAPEL_PADRAO);

                    const url = this.imagemAtual;
                    if (url) {
                        img.style.display = '';
                        img.style.backgroundImage = 'url("' + url + '")';
                        img.style.backgroundSize = this.f.ajuste === 'repeat' ? 'auto' : this.f.ajuste;
                        img.style.backgroundRepeat = this.f.ajuste === 'repeat' ? 'repeat' : 'no-repeat';
                        img.style.filter = this.f.desfoque ? 'blur(' + this.f.desfoque + 'px)' : 'none';
                        img.style.inset = this.f.desfoque ? '-40px' : '0';
                        veu.style.display = '';
                        veu.style.background = 'color-mix(in srgb, var(--papel) ' + this.f.escurecer + '%, transparent)';
                    } else {
                        img.style.display = 'none';
                        veu.style.display = 'none';
                    }
                },

                async escolherImagem(arquivo) {
                    if (!arquivo || !arquivo.type.startsWith('image/')) return;
                    this.erro = '';
                    try {
                        let url;
                        if (arquivo.type === 'image/gif' || arquivo.type === 'image/webp') {
                            if (arquivo.size > LIMITE_ARQUIVO) {
                                this.erro = 'Esse arquivo passa de 4 MB. Escolha um GIF ou WebP menor.';
                                return;
                            }
                            url = await lerComoTexto(arquivo);
                        } else {
                            url = await reduzir(arquivo, 1920, 0.8);
                            if (url.length > 2400000) url = await reduzir(arquivo, 1280, 0.7);
                        }
                        if (url.length > 5900000) { this.erro = 'O arquivo é muito pesado. Escolha outro.'; return; }
                        this.novaImagem = url;
                        this.removerImagem = false;
                        this.previaFundo();
                    } catch {
                        this.erro = 'Não consegui ler esse arquivo.';
                    }
                },

                tirarImagem() {
                    this.novaImagem = null;
                    this.removerImagem = !!this.f.imagem;
                    this.previaFundo();
                },

                restaurarFundo() {
                    this.f.cor = null; this.f.ajuste = 'cover'; this.f.escurecer = 55; this.f.desfoque = 0;
                    this.tirarImagem();
                },

                fundoMudou() {
                    return !!this.novaImagem || this.removerImagem || nucleo(this.f) !== nucleo(this.salvo);
                },

                /** Grava o fundo no servidor. Devolve true se deu certo. */
                async salvarFundo() {
                    this.erro = '';
                    if (!urlFundo) { this.erro = 'A rota do fundo não foi criada ainda.'; return false; }
                    try {
                        const corpo = { cor: this.f.cor, ajuste: this.f.ajuste, escurecer: +this.f.escurecer, desfoque: +this.f.desfoque };
                        if (this.novaImagem) corpo.imagem = this.novaImagem;
                        else if (this.removerImagem) corpo.remover_imagem = true;

                        const r = await fetch(urlFundo, {
                            method: 'PUT',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify(corpo),
                        });

                        if (r.status === 401 || r.status === 419) { location.reload(); return false; }
                        if (r.status === 413) { this.erro = 'O arquivo é grande demais para o servidor. Escolha um menor.'; return false; }
                        if (r.status === 422) {
                            const j = await r.json();
                            this.erro = Object.values(j.errors || {})[0]?.[0] || 'Confira os dados e tente de novo.';
                            return false;
                        }
                        if (!r.ok) throw new Error(String(r.status));

                        const j = await r.json();
                        this.f.imagem = j.imagem;
                        this.f.versao = j.versao;
                        this.novaImagem = null;
                        this.removerImagem = false;
                        this.salvo = copia(this.f);
                        return true;
                    } catch {
                        this.erro = 'Não foi possível salvar o fundo. Tente de novo.';
                        return false;
                    }
                },

                /** Salva o fundo (se mudou) e depois envia o formulário das cores normalmente. */
                async salvar(e) {
                    const form = e.target;
                    this.salvando = true;
                    try {
                        if (this.fundoMudou() && !(await this.salvarFundo())) return;
                        form.submit();
                    } finally {
                        this.salvando = false;
                    }
                },

                cancelar() {
                    this.prim = primInicial;
                    this.cab = cabInicial;
                    this.previa();
                    this.f = copia(this.salvo);
                    this.novaImagem = null;
                    this.removerImagem = false;
                    this.erro = '';
                    this.previaFundo();
                    this.aberto = false;
                },
            };
        }

    document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!form.matches('form[data-ajax]') || e.defaultPrevented) return;
    e.preventDefault();
    if (form.dataset.enviando) return;

    form.dataset.enviando = '1';
    if (e.submitter) e.submitter.disabled = true;
    form.querySelectorAll('[data-erro]').forEach(el => el.textContent = '');

    try {
        const resp = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (resp.status === 401 || resp.status === 419) return location.reload();

        if (resp.status === 422) {
            const { errors } = await resp.json();
            for (const [campo, msgs] of Object.entries(errors)) {
                const el = form.querySelector(`[data-erro="${campo}"]`);
                if (el) el.textContent = msgs[0];
            }
            return;
        }

        if (!resp.ok) throw new Error(resp.status);

        const doc = new DOMParser().parseFromString(await resp.text(), 'text/html');
        document.querySelectorAll('[data-atualiza]').forEach(el => {
            const novo = doc.querySelector(`[data-atualiza="${el.dataset.atualiza}"]`);
            if (novo) el.replaceWith(novo);
        });

        if (form.hasAttribute('data-limpar') && form.isConnected) {
            form.reset();
            form.querySelector('[name=titulo]')?.focus();
        }
    } catch (err) {
        alert('Não foi possível salvar. Tente novamente.');
    } finally {
        delete form.dataset.enviando;
        if (e.submitter) e.submitter.disabled = false;
    }
});

        /* ---------- Atalhos: organização automática (liga/desliga) e teclas personalizáveis ---------- */
        const WF_ORG_CHAVE = 'wf-organizar-auto';
        function wfOrgLigada() {
            const p = window.WFPrefs ? window.WFPrefs.ler() : {};
            if (typeof p.organizarAuto === 'boolean') return p.organizarAuto;
            try { return localStorage.getItem(WF_ORG_CHAVE) !== '0'; } catch (e) { return true; }
        }
        function wfOrgDefinir(ligado) {
            try { localStorage.setItem(WF_ORG_CHAVE, ligado ? '1' : '0'); } catch (e) { /* sem armazenamento */ }
            if (window.WFPrefs) window.WFPrefs.salvar({ organizarAuto: !!ligado });
            window.dispatchEvent(new CustomEvent('wf-organizar-auto', { detail: { ligado: !!ligado } }));
        }
        function wfTemQuadro() { return !!(window.WFQuadro && window.WFQuadro.ativo && !window.WFQuadro.EMBED); }

        /** Ações com tecla de atalho. quadro: só funciona nas páginas com quadro (Corpo, Mente, Carteira, Diverso). */
        const WF_ACOES = [
            { id: 'topo',            grupo: 'Geral',  nome: 'Voltar ao topo da página',                 padrao: 'Alt+ArrowUp' },
            { id: 'atalhos',         grupo: 'Geral',  nome: 'Abrir esta aba de atalhos',                padrao: 'Alt+K' },
            { id: 'auto',            grupo: 'Geral',  nome: 'Ligar ou desligar a organização automática', padrao: 'Alt+O' },
            { id: 'biblioteca',      grupo: 'Geral',  nome: 'Abrir a biblioteca',                       padrao: 'Alt+H' },
            { id: 'historico',       grupo: 'Geral',  nome: 'Abrir o histórico (itens excluídos)',      padrao: 'Alt+J' },
            { id: 'salvar',          grupo: 'Quadro', nome: 'Salvar agora',                             padrao: 'Ctrl+S',       quadro: true },
            { id: 'reorganizar',     grupo: 'Quadro', nome: 'Reorganizar a aba atual',                  padrao: 'Alt+R',        quadro: true },
            { id: 'abaAnterior',     grupo: 'Quadro', nome: 'Ir para a aba anterior',                   padrao: 'Alt+PageUp',   quadro: true },
            { id: 'abaSeguinte',     grupo: 'Quadro', nome: 'Ir para a próxima aba',                    padrao: 'Alt+PageDown', quadro: true },
            { id: 'selecionarTodos', grupo: 'Quadro', nome: 'Selecionar todos os blocos da aba',        padrao: 'Alt+A',        quadro: true },
            { id: 'excluir',         grupo: 'Quadro', nome: 'Excluir o cartão clicado (ou os selecionados)', padrao: 'Alt+X',   quadro: true },
            { id: 'duplicar',        grupo: 'Quadro', nome: 'Duplicar o cartão clicado',                padrao: 'Alt+C',        quadro: true },
            { id: 'telaCheia',       grupo: 'Quadro', nome: 'Tela cheia do cartão clicado',             padrao: 'Alt+Enter',    quadro: true },
            { id: 'novo.texto',      grupo: 'Criar',  nome: 'Novo campo de texto',                      padrao: 'Alt+T',        quadro: true },
            { id: 'novo.lista',      grupo: 'Criar',  nome: 'Nova lista de itens',                      padrao: 'Alt+L',        quadro: true },
            { id: 'novo.tabela',     grupo: 'Criar',  nome: 'Nova tabela',                              padrao: 'Alt+B',        quadro: true },
            { id: 'novo.imagem',     grupo: 'Criar',  nome: 'Nova imagem',                              padrao: 'Alt+I',        quadro: true },
            { id: 'novo.mapa',       grupo: 'Criar',  nome: 'Novo mapa mental',                         padrao: 'Alt+M',        quadro: true },
            { id: 'novo.codigo',     grupo: 'Criar',  nome: 'Novo GitHub / VS Code (Diverso)',           padrao: 'Alt+G',        quadro: true },
            { id: 'novo.tabletop',   grupo: 'Criar',  nome: 'Novo tabletop (Diverso)',                  padrao: 'Alt+Y',        quadro: true },
            { id: 'novo.documento',  grupo: 'Criar',  nome: 'Novo documento (Mente)',                   padrao: 'Alt+N',        quadro: true },
            { id: 'novo.editorimg',  grupo: 'Criar',  nome: 'Novo editor de imagem (camadas)',           padrao: 'Alt+Q',        quadro: true },
            { id: 'novo.pasta',      grupo: 'Pastas', nome: 'Nova pasta (com os selecionados, se houver)', padrao: 'Alt+P',     quadro: true },
            { id: 'abrirPasta',      grupo: 'Pastas', nome: 'Abrir a pasta (ou o editor de imagem) clicado', padrao: 'Alt+U',   quadro: true },
            { id: 'fecharPasta',     grupo: 'Pastas', nome: 'Voltar (fechar a pasta aberta)',           padrao: 'Alt+V',        quadro: true },
            { id: 'tirarDaPasta',    grupo: 'Pastas', nome: 'Tirar da pasta o cartão clicado (ou os selecionados)', padrao: 'Alt+W', quadro: true },
        ];
        // combinações que o navegador ou a edição de texto já usam: não podem virar atalho
        const WF_RESERVADAS = ['Ctrl+C', 'Ctrl+V', 'Ctrl+X', 'Ctrl+Z', 'Ctrl+Y', 'Ctrl+A', 'Ctrl+F', 'Ctrl+P', 'Ctrl+R', 'Ctrl+T', 'Ctrl+W', 'Ctrl+N', 'Ctrl+L', 'Ctrl+Q', 'Ctrl+Shift+Z', 'Alt+F4', 'Alt+Tab', 'Alt+ArrowLeft', 'Alt+ArrowRight', 'Alt+D', 'Alt+E', 'Alt+F', 'Alt+Home'];
        const WF_NOMES_TECLA = { ArrowUp: '↑', ArrowDown: '↓', ArrowLeft: '←', ArrowRight: '→', PageUp: 'Page Up', PageDown: 'Page Down', Delete: 'Del', Backspace: 'Backspace', Space: 'Espaço' };

        /** Tecla de cada ação: a escolhida pelo usuário ("" = sem tecla) ou a padrão. */
        function wfTeclaDe(id) {
            const p = window.WFPrefs ? window.WFPrefs.ler() : {};
            const mine = p.atalhos && typeof p.atalhos === 'object' ? p.atalhos : {};
            if (Object.prototype.hasOwnProperty.call(mine, id)) return mine[id] || '';
            return (WF_ACOES.find(a => a.id === id) || {}).padrao || '';
        }
        /** Combinação apertada, no mesmo formato das teclas salvas ("Alt+T", "Ctrl+S", "Alt+ArrowUp"). */
        function wfCombo(e) {
            if (['Control', 'Alt', 'Shift', 'Meta', 'AltGraph', 'CapsLock', 'OS'].includes(e.key)) return null;
            let k = null;
            const m = /^(Key([A-Z])|Digit(\d))$/.exec(e.code || '');
            if (m) k = m[2] || m[3];
            else if (/^F([1-9]|1[0-2])$/.test(e.key)) k = e.key;
            else if (e.key === ' ') k = 'Space';
            else if (/^[A-Za-z]+$/.test(e.key) && e.key.length > 1) k = e.key;
            else if (/^[A-Za-z0-9]$/.test(e.key)) k = e.key.toUpperCase();
            if (!k) return null;
            const p = [];
            if (e.ctrlKey || e.metaKey) p.push('Ctrl');
            if (e.altKey) p.push('Alt');
            if (e.shiftKey) p.push('Shift');
            p.push(k);
            return p.join('+');
        }
        function wfPartes(combo) { return String(combo || '').split('+').filter(Boolean).map(x => WF_NOMES_TECLA[x] || x); }
        function wfDigitando(el) {
            return !!(el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)));
        }

        function atalhosPainel() {
            return {
                ligado: wfOrgLigada(),
                temQuadro: wfTemQuadro(),
                gravando: null, nota: '', _v: 0,
                init() {
                    window.addEventListener('wf-organizar-auto', e => { this.ligado = !!(e.detail && e.detail.ligado); });
                    window.addEventListener('wf-prefs', () => { this._v++; });
                    // gravação de uma tecla nova: pega a próxima combinação antes de qualquer outro atalho
                    window.addEventListener('keydown', e => {
                        if (!this.gravando) return;
                        e.preventDefault(); e.stopImmediatePropagation();
                        if (e.key === 'Escape') { this.parar(); return; }
                        if ((e.key === 'Backspace' || e.key === 'Delete') && !e.ctrlKey && !e.altKey && !e.metaKey) { this.definir(this.gravando, ''); this.parar(); return; }
                        const c = wfCombo(e);
                        if (!c) return;
                        if (!/(^|\+)(Ctrl|Alt)\+/.test(c) && !/^F([1-9]|1[0-2])$/.test(c)) { this.mostrar('Use Alt ou Ctrl junto com a tecla (ou uma tecla F1 a F12).'); return; }
                        if (e.getModifierState && e.getModifierState('AltGraph')) { this.mostrar('AltGr não serve para atalhos. Use o Alt da esquerda.'); return; }
                        if (WF_RESERVADAS.includes(c)) { this.mostrar('Essa combinação é do navegador (copiar, colar, desfazer…). Escolha outra.'); return; }
                        const outra = WF_ACOES.find(a => a.id !== this.gravando && wfTeclaDe(a.id) === c);
                        if (outra) { this.definir(outra.id, ''); this.mostrar(`Essa tecla era de “${outra.nome}”, que ficou sem tecla.`); }
                        this.definir(this.gravando, c);
                        this.parar();
                    }, true);
                },
                atualizar() { this.ligado = wfOrgLigada(); this.temQuadro = wfTemQuadro(); this._v++; },
                alternar() { wfOrgDefinir(!this.ligado); },
                reorganizarAgora() { window.dispatchEvent(new CustomEvent('wf-reorganizar')); },
                grupos() { return [...new Set(WF_ACOES.map(a => a.grupo))]; },
                acoesDo(g) { return WF_ACOES.filter(a => a.grupo === g); },
                tecla(id) { void this._v; return wfTeclaDe(id); },
                partes(c) { return wfPartes(c); },
                personalizado(id) {
                    void this._v;
                    const mine = (window.WFPrefs ? window.WFPrefs.ler() : {}).atalhos || {};
                    return Object.prototype.hasOwnProperty.call(mine, id);
                },
                temPersonalizado() { void this._v; return Object.keys((window.WFPrefs ? window.WFPrefs.ler() : {}).atalhos || {}).length > 0; },
                gravar(id) { this.gravando = this.gravando === id ? null : id; this.nota = ''; },
                parar() { this.gravando = null; },
                definir(id, combo) {
                    const atual = { ...((window.WFPrefs ? window.WFPrefs.ler() : {}).atalhos || {}) };
                    const padrao = (WF_ACOES.find(a => a.id === id) || {}).padrao || '';
                    if (combo === padrao) delete atual[id]; else atual[id] = combo;
                    if (window.WFPrefs) window.WFPrefs.salvar({ atalhos: atual });
                    this._v++;
                },
                padrao(id) {
                    const atual = { ...((window.WFPrefs ? window.WFPrefs.ler() : {}).atalhos || {}) };
                    delete atual[id];
                    const p = (WF_ACOES.find(a => a.id === id) || {}).padrao;
                    // se a tecla padrão estiver com outra ação, a outra fica sem tecla
                    const outra = WF_ACOES.find(a => a.id !== id && wfTeclaDe(a.id) === p);
                    if (outra) { atual[outra.id] = ''; this.mostrar(`“${outra.nome}” ficou sem tecla, porque usava essa.`); }
                    if (window.WFPrefs) window.WFPrefs.salvar({ atalhos: atual });
                    this._v++;
                },
                restaurarTodos() {
                    if (window.WFPrefs) window.WFPrefs.salvar({ atalhos: {} });
                    this.mostrar('Todas as teclas voltaram ao padrão.');
                    this._v++;
                },
                mostrar(t) { this.nota = t; clearTimeout(this._n); this._n = setTimeout(() => { this.nota = ''; }, 4000); },
            };
        }

        /** Executa a ação ligada à combinação apertada. Ações de quadro vão para a página pelo evento wf-atalho. */
        window.addEventListener('keydown', e => {
            if (e.repeat || e.defaultPrevented) return;
            if (e.getModifierState && e.getModifierState('AltGraph')) return;
            const c = wfCombo(e);
            if (!c) return;
            const a = WF_ACOES.find(x => wfTeclaDe(x.id) === c);
            if (!a) return;
            // sem Alt/Ctrl (só teclas F), não atrapalha quem está escrevendo
            if (!e.altKey && !e.ctrlKey && !e.metaKey && wfDigitando(document.activeElement)) return;
            e.preventDefault();
            switch (a.id) {
                case 'topo': window.scrollTo({ top: 0, behavior: 'smooth' }); return;
                case 'atalhos': window.dispatchEvent(new CustomEvent('abrir-atalhos')); return;
                case 'auto': {
                    const novo = !wfOrgLigada();
                    wfOrgDefinir(novo);
                    wfAvisoRapido(novo ? 'Organização automática ligada.' : 'Organização automática desligada.');
                    return;
                }
                case 'biblioteca': window.dispatchEvent(new CustomEvent('abrir-biblioteca')); return;
                case 'historico': window.dispatchEvent(new CustomEvent('abrir-lixeira')); return;
            }
            if (!wfTemQuadro()) { wfAvisoRapido('Esse atalho funciona nas páginas com quadro: Corpo, Mente, Carteira e Diverso.'); return; }
            if (a.id === 'reorganizar') { window.dispatchEvent(new CustomEvent('wf-reorganizar')); return; }
            window.dispatchEvent(new CustomEvent('wf-atalho', { detail: { id: a.id } }));
        });
        window.WFAtalhos = { acoes: WF_ACOES, tecla: wfTeclaDe, partes: wfPartes };

        /** Aviso curto no canto da tela (vale em qualquer página, com ou sem quadro). */
        function wfAvisoRapido(txt) {
            let el = document.getElementById('wf-aviso-rapido');
            if (!el) {
                el = document.createElement('div');
                el.id = 'wf-aviso-rapido';
                el.setAttribute('role', 'status');
                el.className = 'aviso-ok';
                el.style.cssText = 'position:fixed;left:50%;bottom:1.25rem;transform:translateX(-50%);z-index:60;max-width:calc(100vw - 2rem);transition:opacity .2s';
                document.body.appendChild(el);
            }
            el.textContent = txt;
            el.style.opacity = '1';
            clearTimeout(el._t);
            el._t = setTimeout(() => { el.style.opacity = '0'; }, 2200);
        }

        /* ---------- botão "voltar ao topo": aparece depois de rolar um pouco, em qualquer página ---------- */
        (function () {
            const b = document.getElementById('wf-topo');
            if (!b) return;
            b.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
            const ver = () => {
                b.classList.toggle('visivel', window.scrollY > 320);
                b.classList.toggle('acima', !!document.getElementById('wft-botao'));
                const t = wfTeclaDe('topo');
                b.title = 'Voltar ao topo' + (t ? ' (' + wfPartes(t).join(' + ') + ')' : '');
            };
            window.addEventListener('scroll', ver, { passive: true });
            window.addEventListener('wf-prefs', ver);
            ver();
        })();
    </script>
@endauth

@guest
    @yield('conteudo')

    {{-- Rodapé das páginas públicas: links legais exigidos pelo AdSense --}}
    @php
        $linksLegais = [
            'Política de Privacidade' => \Illuminate\Support\Facades\Route::has('privacidade') ? route('privacidade') : url('/privacidade'),
            'Termos de Uso'           => \Illuminate\Support\Facades\Route::has('termos') ? route('termos') : url('/termos'),
            'Contato'                 => \Illuminate\Support\Facades\Route::has('contato') ? route('contato') : url('/contato'),
        ];
    @endphp
    <footer class="max-w-6xl mx-auto px-4 sm:px-8 py-8 mt-8 text-xs texto-2" style="border-top: 1px solid var(--linha)">
        <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2" aria-label="Informações legais">
            @foreach ($linksLegais as $nome => $url)
                <a href="{{ $url }}" class="link-prim">{{ $nome }}</a>
            @endforeach
        </nav>
        <p class="text-center mt-3">© {{ date('Y') }} Work Flower</p>
    </footer>
@endguest

</body>
</html>
