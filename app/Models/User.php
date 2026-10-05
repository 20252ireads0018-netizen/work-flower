<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const COR_PRIMARIA_PADRAO  = '#2f6fc7';
    public const COR_CABECALHO_PADRAO = '#1a4585';

    // A senha é criptografada no AuthController (Hash::make).
    // Não use o cast 'hashed' aqui, senão ela seria criptografada duas vezes.
    protected $fillable = ['name', 'email', 'password', 'cor_primaria', 'cor_cabecalho'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

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
