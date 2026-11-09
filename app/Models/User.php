<?php

namespace App\Models;

use App\Models\Concerns\HasSubscriptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasSubscriptions;

    public const COR_PRIMARIA_PADRAO  = '#2f6fc7';
    public const COR_CABECALHO_PADRAO = '#1a4585';

    // A senha é criptografada no AuthController (Hash::make).
    // Não use o cast 'hashed' aqui, senão ela seria criptografada duas vezes.
    protected $fillable = ['name', 'email', 'password', 'cor_primaria', 'cor_cabecalho', 'cpf_cnpj', 'asaas_customer_id'];

    protected $hidden = ['password', 'remember_token', 'dois_fatores_segredo', 'dois_fatores_recuperacao', 'dois_fatores_ultimo_passo'];

    // Verificação em duas etapas (SegurancaController): segredo do app e hashes dos códigos de recuperação, criptografados.
    // Fora do $fillable de propósito: só mudam por forceFill no SegurancaController.
    protected $casts = [
        'email_verified_at'        => 'datetime',
        'dois_fatores_segredo'     => 'encrypted',
        'dois_fatores_recuperacao' => 'encrypted:array',
        'dois_fatores_ativo_em'    => 'datetime',
    ];

    // Administração do site: respondem o "Reclame aqui" (/admin/reclamacoes) e veem as animações de anúncio.
    public const ADMINS = ['xexplosion20@gmail.com'];

    public function eAdmin(): bool
    {
        return in_array(mb_strtolower(trim((string) $this->email)), self::ADMINS, true);
    }

    /** Pede o código do app autenticador depois da senha no /login. */
    public function temDoisFatores(): bool
    {
        return $this->dois_fatores_ativo_em !== null && ! empty($this->dois_fatores_segredo);
    }

    public function tarefas(): HasMany
    {
        return $this->hasMany(Tarefa::class);
    }

    /** Devolve preto ou branco, o que tiver melhor leitura sobre a cor informada. */
    public static function contraste(string $hex): string
    {
        [$r, $g, $b] = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.6 ? '#14171b' : '#ffffff';
    }
}
