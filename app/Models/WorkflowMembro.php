<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma pessoa com acesso a uma área (workflow) de outra conta. */
class WorkflowMembro extends Model
{
    protected $table = 'workflow_membros';

    protected $fillable = ['dono_id', 'membro_id', 'area'];

    public function dono(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dono_id');
    }

    public function membro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'membro_id');
    }
}
