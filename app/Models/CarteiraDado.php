<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarteiraDado extends Model
{
    protected $fillable = ['user_id', 'chave', 'valor'];
}