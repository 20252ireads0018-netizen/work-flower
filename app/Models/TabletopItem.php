<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabletopItem extends Model
{
    protected $table = 'tabletop_itens';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['dados' => 'array'];
}

