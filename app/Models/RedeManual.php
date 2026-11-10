<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Números do Instagram/TikTok importados à mão (CSV ou colados), para quem ainda não conectou a conta. */
class RedeManual extends Model
{
    protected $table = 'redes_manuais';

    protected $fillable = ['user_id', 'rede', 'usuario', 'conta', 'itens', 'historico'];

    protected function casts(): array
    {
        return ['conta' => 'array', 'itens' => 'array', 'historico' => 'array'];
    }
}
