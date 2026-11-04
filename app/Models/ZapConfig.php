<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** WhatsApp oficial (Cloud API) do dono da área Negócios: número, token (criptografado) e token de verificação do webhook. */
class ZapConfig extends Model
{
    protected $table = 'zap_configs';

    protected $fillable = ['user_id', 'phone_number_id', 'waba_id', 'access_token', 'app_secret', 'verify_token', 'numero', 'ultimo_aviso_em'];

    protected $hidden = ['access_token', 'app_secret'];

    protected function casts(): array
    {
        return [
            'access_token'    => 'encrypted',
            'app_secret'      => 'encrypted',
            'ultimo_aviso_em' => 'datetime',
        ];
    }

    /** Segredo usado para conferir a assinatura do webhook: o do dono ou o do app do site. */
    public function segredo(): string
    {
        return (string) ($this->app_secret ?: config('services.meta.secret'));
    }
}
