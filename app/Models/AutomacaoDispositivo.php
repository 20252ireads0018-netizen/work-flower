<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Computador pareado com o agente do Work Flower. O token dá controle das ações permitidas
 * no computador, por isso só o hash fica no banco e ele pode ser revogado a qualquer momento.
 */
class AutomacaoDispositivo extends Model
{
    protected $table = 'automacao_dispositivos';

    protected $fillable = ['user_id', 'nome', 'token_hash', 'token_fim'];

    protected $hidden = ['token_hash'];

    protected $casts = ['ultimo_contato_em' => 'datetime', 'revogado_em' => 'datetime'];

    /** Prefixo dos tokens do agente (ajuda a reconhecer o token se ele vazar num print ou arquivo). */
    public const PREFIXO = 'wfa_';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashDoToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Cria o aparelho e devolve [aparelho, token em texto] — o token não é guardado e não aparece de novo. */
    public static function parear(int $userId, string $nome): array
    {
        $token = self::PREFIXO . Str::random(40);
        $d = self::create([
            'user_id'    => $userId,
            'nome'       => $nome,
            'token_hash' => self::hashDoToken($token),
            'token_fim'  => substr($token, -4),
        ]);

        return [$d, $token];
    }

    /** Aparelho ativo dono deste token (null se não existe ou foi revogado). */
    public static function peloToken(?string $token): ?self
    {
        if (! is_string($token) || ! str_starts_with($token, self::PREFIXO) || strlen($token) > 100) {
            return null;
        }

        return self::where('token_hash', self::hashDoToken($token))->whereNull('revogado_em')->first();
    }

    public function ativo(): bool
    {
        return $this->revogado_em === null;
    }

    /** Agente conversou com o site nos últimos 2 minutos. */
    public function online(): bool
    {
        return $this->ativo() && $this->ultimo_contato_em && $this->ultimo_contato_em->gt(now()->subMinutes(2));
    }
}
