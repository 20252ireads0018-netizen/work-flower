<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Backup dos workflows enviado a um destino (o arquivo é o mesmo formato do Importar/exportar). */
class ArmazenamentoBackup extends Model
{
    protected $table = 'armazenamento_backups';

    protected $fillable = ['user_id', 'destino_id', 'remoto', 'nome', 'tamanho', 'areas', 'origem'];

    public function destino(): BelongsTo
    {
        return $this->belongsTo(ArmazenamentoDestino::class, 'destino_id');
    }
}
