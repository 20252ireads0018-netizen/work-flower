<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Evento do calendário do painel. Datas em hora de Brasília, sem fuso (App\Support\Calendario::FUSO).
 * Repetição: nao | diario | semanal | mensal | anual, opcionalmente até "repete_ate".
 */
class Evento extends Model
{
    protected $table = 'eventos';

    public const REPETE = ['nao' => 'Não repete', 'diario' => 'Todo dia', 'semanal' => 'Toda semana', 'mensal' => 'Todo mês', 'anual' => 'Todo ano'];

    protected $fillable = ['titulo', 'descricao', 'inicio', 'fim', 'dia_todo', 'cor', 'repete', 'repete_ate', 'lembrete_min', 'lembrar_email'];

    protected $casts = [
        'inicio'             => 'datetime',
        'fim'                => 'datetime',
        'repete_ate'         => 'date',
        'dia_todo'           => 'boolean',
        'lembrar_email'      => 'boolean',
        'lembrete_min'       => 'integer',
        'email_enviado_para' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Dados que o calendário do painel usa (public/js/calendario.js). */
    public function paraJs(?Carbon $ocorrencia = null): array
    {
        $f = 'Y-m-d\TH:i';
        $dur = $this->fim ? $this->inicio->diffInMinutes($this->fim) : 0;
        $o = $ocorrencia ?? $this->inicio;

        return [
            'id'            => $this->id,
            'titulo'        => $this->titulo,
            'descricao'     => $this->descricao,
            'inicio'        => $this->inicio->format($f),
            'fim'           => $this->fim?->format($f),
            'dia_todo'      => $this->dia_todo,
            'cor'           => $this->cor,
            'repete'        => $this->repete,
            'repete_ate'    => $this->repete_ate?->format('Y-m-d'),
            'lembrete_min'  => $this->lembrete_min,
            'lembrar_email' => $this->lembrar_email,
            'ocorrencia'    => $o->format($f),
            'ocorrencia_fim' => $this->fim ? $o->copy()->addMinutes($dur)->format($f) : null,
        ];
    }
}
