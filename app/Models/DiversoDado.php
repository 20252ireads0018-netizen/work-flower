<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiversoDado extends Model
{
    protected $table = 'diverso_dados';

    protected $fillable = ['user_id', 'chave', 'valor'];
}
