<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['titulo', 'corpo'];

    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }

    public function scopeComTitulo($q, $t)
    {
        return $q->where('titulo', 'like', "%$t%");
    }
}
