<?php

namespace App\Support;

use App\Models\Evento;
use Illuminate\Support\Carbon;

/**
 * Regras do calendário do painel: em que dias um evento acontece (repetição) e quando avisar.
 *
 * As datas dos eventos são "hora de parede" de Brasília, guardadas sem fuso. Para comparar com o
 * relógio, use Calendario::agora() (também sem fuso, na hora de Brasília).
 */
class Calendario
{
    public const FUSO = 'America/Sao_Paulo';

    /** Eventos de dia inteiro avisam contando a partir desta hora do dia. */
    public const HORA_DIA_TODO = 9;

    /** Teto de ocorrências por evento numa consulta (um evento diário num ano inteiro cabe). */
    private const MAX = 400;

    /** Agora, na hora de Brasília, como hora de parede (mesmo formato das datas dos eventos). */
    public static function agora(): Carbon
    {
        return Carbon::parse(now(self::FUSO)->format('Y-m-d H:i:s'));
    }

    /**
     * Inícios das ocorrências do evento entre $de e $ate (inclusive), em ordem.
     * Meses sem o dia do evento (31, ou 29/02 fora de ano bissexto) ficam sem ocorrência.
     *
     * @return Carbon[]
     */
    public static function ocorrencias(Evento $e, Carbon $de, Carbon $ate): array
    {
        $ini = $e->inicio->copy();
        $fimSerie = $e->repete_ate ? $e->repete_ate->copy()->endOfDay() : null;
        if ($fimSerie && $fimSerie->lt($ate)) {
            $ate = $fimSerie;
        }
        if ($ini->gt($ate)) {
            return [];
        }
        if ($e->repete === 'nao' || ! isset(Evento::REPETE[$e->repete])) {
            return $ini->between($de, $ate) ? [$ini] : [];
        }

        $out = [];
        if ($e->repete === 'diario' || $e->repete === 'semanal') {
            $passo = $e->repete === 'diario' ? 1 : 7;
            // pula direto para perto de $de, sem andar dia a dia desde o começo da série
            $pular = $de->gt($ini) ? intdiv((int) floor($ini->diffInDays($de, true)), $passo) * $passo : 0;
            for ($d = $ini->copy()->addDays($pular); $d->lte($ate) && count($out) < self::MAX; $d->addDays($passo)) {
                if ($d->gte($de)) {
                    $out[] = $d->copy();
                }
            }

            return $out;
        }

        $mensal = $e->repete === 'mensal';
        $dia = $ini->day;
        $mes = $ini->month;
        $cursor = $ini->copy()->startOfMonth();
        if ($de->gt($ini)) {
            $cursor = $de->copy()->startOfMonth();
            if (! $mensal) {
                $cursor->month(1); // anual: começa no ano de $de e confere o mês abaixo
            }
        }
        for ($i = 0; $cursor->lte($ate) && count($out) < self::MAX && $i < 1200; $i++) {
            if (($mensal || $cursor->month === $mes) && $dia <= $cursor->daysInMonth) {
                $d = $cursor->copy()->day($dia)->setTime($ini->hour, $ini->minute);
                if ($d->gte($ini) && $d->between($de, $ate)) {
                    $out[] = $d;
                }
            }
            $cursor->addMonthNoOverflow();
        }

        return $out;
    }

    /** Quando avisar sobre uma ocorrência (null = evento sem lembrete). */
    public static function avisarEm(Evento $e, Carbon $ocorrencia): ?Carbon
    {
        if ($e->lembrete_min === null) {
            return null;
        }
        $base = $e->dia_todo ? $ocorrencia->copy()->startOfDay()->setTime(self::HORA_DIA_TODO, 0) : $ocorrencia->copy();

        return $base->subMinutes($e->lembrete_min);
    }

    /** Hora de parede de Brasília para ISO 8601 com o fuso (o navegador converte certo). */
    public static function iso(Carbon $parede): string
    {
        return Carbon::parse($parede->format('Y-m-d H:i:s'), self::FUSO)->toIso8601String();
    }
}
