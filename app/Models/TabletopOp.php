<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TabletopOp extends Model
{
    protected $table = 'tabletop_ops';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['ops' => 'array', 'rev' => 'integer'];
}

