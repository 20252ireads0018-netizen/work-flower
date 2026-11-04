<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Conversa do WhatsApp oficial com um telefone, ligada (quando dá) a um cliente da área Negócios. */
class ZapConversa extends Model
{
    protected $table = 'zap_conversas';

    protected $fillable = ['user_id', 'telefone', 'chave', 'cliente_id', 'nome', 'ultima_entrada_em', 'ultima_em', 'previa', 'nao_lidas'];

    protected function casts(): array
    {
        return [
            'ultima_entrada_em' => 'datetime',
            'ultima_em'         => 'datetime',
            'nao_lidas'         => 'integer',
        ];
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(ZapMensagem::class, 'conversa_id');
    }

    /** Dentro da janela de 24 horas desde a última mensagem do cliente: dá para mandar texto livre. */
    public function janelaAberta(): bool
    {
        return $this->ultima_entrada_em && $this->ultima_entrada_em->gt(now()->subHours(24));
    }

    /**
     * Chave para achar o mesmo telefone escrito de jeitos diferentes: no Brasil, DDD + 8 últimos dígitos
     * (o WhatsApp manda alguns celulares sem o 9). Fora do Brasil, o número inteiro.
     */
    public static function chave(string $tel): string
    {
        $d = preg_replace('/\D/', '', $tel);
        if (strlen($d) <= 11 && strlen($d) >= 10) $d = '55' . $d;
        if (str_starts_with($d, '55') && in_array(strlen($d), [12, 13], true)) {
            return substr($d, 2, 2) . substr($d, -8);
        }

        return substr($d, -20);
    }

    /** Telefone com DDI (só dígitos), como a API espera; números brasileiros sem DDI ganham o 55. */
    public static function normalizar(string $tel): string
    {
        $d = preg_replace('/\D/', '', $tel);

        return strlen($d) >= 10 && strlen($d) <= 11 ? '55' . $d : $d;
    }
}
