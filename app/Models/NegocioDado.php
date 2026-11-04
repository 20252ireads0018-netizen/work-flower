<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dados da área Negócios ("estado" e "img.{id}"), como CarteiraDado e DiversoDado. */
class NegocioDado extends Model
{
    protected $table = 'negocio_dados';

    protected $fillable = ['user_id', 'chave', 'valor'];
}
