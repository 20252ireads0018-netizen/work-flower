<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Regra de categoria: se o remetente/assunto contém o termo, o e-mail vai para a categoria. A primeira que bate vale. */
class EmailRegra extends Model
{
    protected $table = 'email_regras';

    protected $fillable = ['user_id', 'categoria', 'campo', 'termo', 'ordem'];

    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }
}
