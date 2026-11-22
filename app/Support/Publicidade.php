<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Onde o Google AdSense pode aparecer. Usado pelo layout (script no <head>) e pelo componente <x-anuncio>.
 *   - só com o ID configurado (config('services.adsense.client'), ADSENSE_CLIENT no .env);
 *   - só para visitantes e para o plano Gratuito (assinantes pagos, inclusive no teste grátis, não veem anúncios);
 *   - nunca em login, cadastro, segurança, planos/assinatura, pagamento, pedidos e telas de admin.
 * (As animações de vídeo para anúncios do /admin/anuncios são outra coisa: App\Support\Anuncios.)
 */
class Publicidade
{
    /** Caminhos (sem a barra inicial) sem anúncio: Request::is() aceita * como curinga. */
    public const CAMINHOS_SEM_ANUNCIO = [
        'login', 'login/*', 'registro', 'registro/*', 'senha', 'senha/*', 'esqueci*', 'redefinir*',
        'seguranca', 'seguranca/*',
        'planos', 'planos/*', 'assinar/*', 'assinar-vitalicio/*', 'pagamento', 'pagamento/*',
        'loja/*/pedido/*', 'carteira/loja/pix', 'carteira/loja/mercadopago', 'vagas/curso/*',
        'admin', 'admin/*', '*/admin', '*/admin/*',
    ];

    public static function cliente(): string
    {
        return (string) config('services.adsense.client');
    }

    /** ANUNCIOS_REDE: "ambos" (padrão), "adsense" ou "adsterra". */
    private static function rede(string $qual): bool
    {
        $r = strtolower((string) config('services.anuncios_rede', 'ambos'));

        return $r === 'ambos' || $r === $qual;
    }

    /** Página e usuário podem ver anúncios (independe da rede). */
    public static function liberada(?Request $request = null, $usuario = null): bool
    {
        $request ??= request();
        $usuario ??= $request->user();

        return ! Planos::semAnuncios($usuario) && ! $request->is(...self::CAMINHOS_SEM_ANUNCIO);
    }

    /** Script do AdSense nesta página. */
    public static function permitida(?Request $request = null, $usuario = null): bool
    {
        return self::cliente() !== '' && self::rede('adsense') && self::liberada($request, $usuario);
    }

    /**
     * Espaços da Adsterra nesta página: só nas páginas públicas (visitante sem login, nunca dentro das ferramentas),
     * fora de login, pagamento e admin, com a rede ligada e alguma chave válida. Só Banner e Native.
     */
    public static function adsterra(?Request $request = null, $usuario = null): bool
    {
        $request ??= request();
        $usuario ??= $request->user();

        return ! $usuario && self::rede('adsterra') && (self::banners() !== [] || self::native() !== null) && self::liberada($request, $usuario);
    }

    /**
     * Banners configurados (computador e, opcional, celular).
     *
     * @return list<array{chave: string, host: string, largura: int, altura: int, onde: string}>
     */
    public static function banners(): array
    {
        $c = (array) config('services.adsterra', []);
        $lista = [];
        foreach (['computador' => ['banner', 'banner_tamanho'], 'movel' => ['banner_movel', 'banner_movel_tamanho']] as $onde => [$k, $t]) {
            $p = self::analisar((string) ($c[$k] ?? ''), (string) ($c['banner_host'] ?? ''));
            if (! $p || ! preg_match('/^(\d{2,4})x(\d{2,4})$/', (string) ($c[$t] ?? ''), $m)) {
                continue;
            }
            $lista[] = $p + ['largura' => (int) $m[1], 'altura' => (int) $m[2], 'onde' => $onde];
        }

        return $lista;
    }

    /** @return array{chave: string, host: string}|null */
    public static function native(): ?array
    {
        $c = (array) config('services.adsterra', []);

        return self::analisar((string) ($c['native'] ?? ''), (string) ($c['native_host'] ?? ''));
    }

    /**
     * Aceita a chave pura (com o host à parte) ou o endereço do invoke.js copiado do código da Adsterra.
     * Só passa chave hexadecimal e host de domínio válido, para nada estranho entrar no HTML.
     *
     * @return array{chave: string, host: string}|null
     */
    private static function analisar(string $valor, string $host): ?array
    {
        $valor = trim($valor);
        if (preg_match('#^(?:https?:)?//([a-z0-9.-]+)/([a-f0-9]{32})/invoke\.js#i', $valor, $m)) {
            [$host, $chave] = [$m[1], $m[2]];
        } elseif (preg_match('/^[a-f0-9]{32}$/i', $valor)) {
            $chave = $valor;
        } else {
            return null;
        }
        $host = strtolower(trim($host));
        if (! preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/', $host)) {
            return null;
        }

        return ['chave' => strtolower($chave), 'host' => $host];
    }
}
