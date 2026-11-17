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

    public static function permitida(?Request $request = null, $usuario = null): bool
    {
        $request ??= request();
        $usuario ??= $request->user();

        if (self::cliente() === '' || Planos::semAnuncios($usuario)) {
            return false;
        }

        return ! $request->is(...self::CAMINHOS_SEM_ANUNCIO);
    }
}
