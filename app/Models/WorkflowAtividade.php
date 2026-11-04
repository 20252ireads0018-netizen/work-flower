<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O que uma pessoa fez num workflow (aba Equipe da área Negócios). Gravado por App\Support\Equipe::registrar. */
class WorkflowAtividade extends Model
{
    protected $table = 'workflow_atividades';

    public const UPDATED_AT = null;

    protected $fillable = ['dono_id', 'area', 'user_id', 'acao', 'aba'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
