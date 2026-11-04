<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um disparo de automação: fica pendente até o agente buscar, e o agente devolve o resultado. */
class AutomacaoExecucao extends Model
{
    protected $table = 'automacao_execucoes';

    protected $fillable = ['user_id', 'automacao_id', 'dispositivo_id', 'nome', 'origem', 'acoes', 'status', 'resultado', 'enviada_em', 'terminada_em'];

    protected $casts = ['acoes' => 'array', 'enviada_em' => 'datetime', 'terminada_em' => 'datetime'];

    public const STATUS = [
        'pendente' => 'Aguardando o computador',
        'enviada'  => 'Rodando no computador',
        'ok'       => 'Concluída',
        'erro'     => 'Deu erro',
        'recusada' => 'Recusada no computador',
        'expirada' => 'Expirou (computador desligado)',
    ];

    /** Disparo que ficou esperando mais que isso não roda mais (evita abrir dez coisas quando o PC liga). */
    public const HORAS_PARA_EXPIRAR = 12;

    public function automacao(): BelongsTo
    {
        return $this->belongsTo(Automacao::class);
    }

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(AutomacaoDispositivo::class, 'dispositivo_id');
    }
}
