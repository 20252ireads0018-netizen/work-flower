<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Work Flower como aplicativo instalável (PWA): manifesto, service worker e página "sem conexão".
 *
 * Os três saem pelo Laravel (e não como arquivos em public/) para que os endereços usem a base real
 * do site: em produção ele fica numa subpasta (https://websight.com.br/work-flower), e o start_url,
 * o scope e o escopo do service worker precisam começar nela. O sw.js em /work-flower/sw.js controla
 * só /work-flower/ — que é exatamente o app.
 */
class AppInstalavelController extends Controller
{
    /** Cor do fundo da tela de abertura do app (o mesmo "papel" escuro padrão do layout). */
    public const COR_FUNDO = '#0c1118';

    public function manifesto(): Response
    {
        $base = $this->base();
        $icone = fn (string $arq, int $lado, string $uso) => [
            'src'     => asset('icons/' . $arq) . '?v=' . @filemtime(public_path('icons/' . $arq)),
            'sizes'   => "{$lado}x{$lado}",
            'type'    => 'image/png',
            'purpose' => $uso,
        ];

        $manifesto = [
            'id'               => $base,
            'name'             => 'Work Flower',
            'short_name'       => 'Work Flower',
            'description'      => 'Organize corpo, mente, carteira, negócios e o resto em quadros de cartões.',
            'lang'             => 'pt-BR',
            'dir'              => 'ltr',
            'start_url'        => route('painel'),
            'scope'            => $base,
            'display'          => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone'],
            'orientation'      => 'any',
            'background_color' => self::COR_FUNDO,
            'theme_color'      => User::COR_CABECALHO_PADRAO,
            'categories'       => ['productivity', 'lifestyle', 'finance'],
            'icons'            => [
                $icone('icone-192.png', 192, 'any'),
                $icone('icone-512.png', 512, 'any'),
                $icone('icone-maskable-192.png', 192, 'maskable'),
                $icone('icone-maskable-512.png', 512, 'maskable'),
            ],
            'shortcuts' => collect(\App\Models\Tarefa::AREAS)->map(fn ($a, $slug) => [
                'name'  => $a['nome'],
                'url'   => route('areas.show', $slug),
                'icons' => [['src' => asset('icons/icone-192.png'), 'sizes' => '192x192', 'type' => 'image/png']],
            ])->values()->all(),
        ];

        return response(json_encode($manifesto, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 200, [
            'Content-Type'  => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function serviceWorker(): Response
    {
        $base = $this->base();
        $offline = route('app.offline');

        // muda quando o próprio service worker, a página offline ou os ícones mudam: o navegador então
        // instala a versão nova e apaga o cache antigo
        $versao = substr(md5(implode('|', [
            @filemtime(resource_path('views/app/sw.blade.php')),
            @filemtime(resource_path('views/app/offline.blade.php')),
            @filemtime(public_path('icons/icone-192.png')),
            $base,
        ])), 0, 10);

        $js = view('app.sw', [
            'versao'   => $versao,
            'base'     => $base,
            'offline'  => $offline,
            'precache' => [
                $offline,
                asset('icons/icone-192.png'),
                asset('icons/icone-512.png'),
            ],
        ])->render();

        return response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            // o navegador sempre confere se o sw.js mudou; sem isso uma correção podia levar até 24 h
            'Cache-Control' => 'no-cache, max-age=0',
            'Service-Worker-Allowed' => parse_url($base, PHP_URL_PATH) ?: '/',
        ]);
    }

    public function offline()
    {
        return response()->view('app.offline', [], 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    /** Base do app sempre com barra no fim (ex.: https://websight.com.br/work-flower/). */
    private function base(): string
    {
        return rtrim(url('/'), '/') . '/';
    }
}
