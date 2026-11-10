<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Caixa de e-mail conectada (Negócios › E-mails). Senha e tokens ficam criptografados e nunca voltam para a tela. */
class EmailConta extends Model
{
    protected $table = 'email_contas';

    protected $fillable = [
        'user_id', 'tipo', 'endereco', 'host', 'porta', 'seguranca', 'usuario', 'senha',
        'access_token', 'refresh_token', 'token_expira_em', 'escopos', 'pasta', 'uidvalidity', 'sincronizado_em', 'erro',
    ];

    protected $hidden = ['senha', 'access_token', 'refresh_token', 'usuario'];

    protected function casts(): array
    {
        return [
            'senha'           => 'encrypted',
            'access_token'    => 'encrypted',
            'refresh_token'   => 'encrypted',
            'token_expira_em' => 'datetime',
            'sincronizado_em' => 'datetime',
            'porta'           => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(EmailMensagem::class, 'conta_id');
    }

    /** O que a tela pode ver da conta (sem senha, usuário nem tokens). */
    public function paraTela(): array
    {
        return [
            'id'              => $this->id,
            'tipo'            => $this->tipo,
            'endereco'        => $this->endereco,
            'host'            => $this->host,
            'porta'           => $this->porta,
            'seguranca'       => $this->seguranca,
            'sincronizado_em' => $this->sincronizado_em?->toIso8601String(),
            'erro'            => $this->erro,
            // Gmail com permissão de envio (gmail.send), pedida só na primeira vez que a pessoa manda um e-mail
            'pode_enviar'     => \App\Support\Emails\Google::podeEnviar($this),
        ];
    }
}
