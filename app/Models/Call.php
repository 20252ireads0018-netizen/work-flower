<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Call marcada em Negócios › Calls. Datas em hora de Brasília, sem fuso (como App\Models\Evento).
 * Cada participante (e o dono) ganha um evento no calendário do painel (eventos.call_id), com lembrete por e-mail.
 */
class Call extends Model
{
    public const PLATAFORMAS = ['meet' => 'Google Meet', 'zoom' => 'Zoom', 'discord' => 'Discord', 'jitsi' => 'Jitsi Meet', 'outro' => 'Outro link'];

    protected $fillable = ['titulo', 'descricao', 'inicio', 'duracao_min', 'plataforma', 'link', 'participantes', 'lembrete_min'];

    protected $casts = [
        'inicio'        => 'datetime',
        'duracao_min'   => 'integer',
        'lembrete_min'  => 'integer',
        'participantes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /** Descobre a plataforma pelo endereço do link. */
    public static function plataformaDe(string $link): string
    {
        $host = strtolower((string) parse_url($link, PHP_URL_HOST));

        return match (true) {
            $host === 'meet.google.com'                                         => 'meet',
            $host === 'zoom.us' || str_ends_with($host, '.zoom.us')             => 'zoom',
            in_array($host, ['discord.com', 'discord.gg', 'discordapp.com'], true) => 'discord',
            $host === 'meet.jit.si'                                             => 'jitsi',
            default                                                             => 'outro',
        };
    }

    /** Sala nova no Jitsi Meet (gratuito, sem conta): nome comprido e aleatório para ninguém adivinhar. */
    public static function linkJitsi(): string
    {
        return 'https://meet.jit.si/WorkFlower-' . bin2hex(random_bytes(8));
    }

    public function paraJs(int $eu): array
    {
        return [
            'id'            => $this->id,
            'titulo'        => $this->titulo,
            'descricao'     => $this->descricao,
            'inicio'        => $this->inicio->format('Y-m-d\TH:i'),
            'duracao_min'   => $this->duracao_min,
            'plataforma'    => $this->plataforma,
            'link'          => $this->link,
            'participantes' => array_map('intval', $this->participantes ?? []),
            'lembrete_min'  => $this->lembrete_min,
            'meu'           => $this->user_id === $eu,
            'dono'          => $this->user?->name,
        ];
    }
}
