<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class TabletopSala extends Model
{
    protected $table = 'tabletop_salas';
    protected $guarded = [];
    protected $casts = [
        'cfg' => 'array',
        'travada' => 'boolean',
        'rev' => 'integer',
        'ultima_atividade' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Não depende de chave estrangeira em cascata (SQLite sem PRAGMA, por exemplo).
        static::deleting(function (self $s) {
            $s->itens()->delete();
            $s->ops()->delete();
            $s->imgs()->delete();
            Cache::forget('tt:pres:' . $s->id);
        });
    }

    public function itens(): HasMany { return $this->hasMany(TabletopItem::class, 'sala_id'); }
    public function ops(): HasMany { return $this->hasMany(TabletopOp::class, 'sala_id'); }
    public function imgs(): HasMany { return $this->hasMany(TabletopImg::class, 'sala_id'); }
}

