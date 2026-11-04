<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servidor do Discord ligado a uma conta (Negócios › Integrações). Webhook e token do bot ficam criptografados
 * e nunca voltam para o navegador; a tela só mostra se estão preenchidos. Envio em App\Support\Discord.
 */
class DiscordIntegracao extends Model
{
    protected $table = 'discord_integracoes';

    /** Avisos que podem ir para o Discord (todos ligados por padrão). */
    public const EVENTOS = [
        'tarefa_criada'   => 'Tarefa criada',
        'tarefa_movida'   => 'Tarefa mudou de coluna',
        'tarefa_delegada' => 'Tarefa delegada a alguém',
        'lembrete'        => 'Lembretes do calendário',
        'pedido'          => 'Pedido novo na loja',
        'call'            => 'Call marcada',
    ];

    protected $fillable = ['webhook_url', 'bot_token', 'guild_id', 'canal_id', 'canal_nome', 'servidor_nome', 'eventos'];

    protected $hidden = ['webhook_url', 'bot_token'];

    protected $casts = [
        'webhook_url'     => 'encrypted',
        'bot_token'       => 'encrypted',
        'eventos'         => 'array',
        'ultimo_envio_em' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** true se o aviso $evento está ligado (o que nunca foi mexido conta como ligado). */
    public function avisa(string $evento): bool
    {
        return (bool) (($this->eventos ?? [])[$evento] ?? true);
    }

    /** Lista completa dos avisos com o estado de cada um, para a tela. */
    public function eventosTodos(): array
    {
        $saida = [];
        foreach (array_keys(self::EVENTOS) as $k) {
            $saida[$k] = $this->avisa($k);
        }

        return $saida;
    }
}
