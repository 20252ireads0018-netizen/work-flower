<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarefa extends Model
{
    protected $fillable = ['user_id', 'area', 'titulo', 'descricao', 'status', 'prazo'];

    protected $casts = ['prazo' => 'date'];

    public const STATUS = [
        'pendente'  => 'Pendente',
        'andamento' => 'Em andamento',
        'concluida' => 'Concluída',
    ];

    public const AREAS = [
        'corpo' => [
            'nome'      => 'Corpo',
            'descricao' => 'Treinos, sono e alimentação.',
            'exemplo'   => 'Ex.: Treino de pernas',
            'icone'     => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
        ],
        'mente' => [
            'nome'      => 'Mente',
            'descricao' => 'Estudo, foco e saúde mental.',
            'exemplo'   => 'Ex.: Ler 20 páginas',
            'icone'     => 'M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18',
        ],
        'carteira' => [
            'nome'      => 'Carteira',
            'descricao' => 'Contas, metas e investimentos.',
            'exemplo'   => 'Ex.: Pagar fatura do cartão',
            'icone'     => 'M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3',
        ],
        'diverso' => [
            'nome'      => 'Diverso',
            'descricao' => 'Trabalho, casa e tudo que não cabe nas outras áreas.',
            'exemplo'   => 'Ex.: Enviar proposta ao cliente',
            'icone'     => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        ],
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDaArea($query, int $userId, string $area)
    {
        return $query->where('user_id', $userId)
            ->where('area', $area)
            ->orderByRaw("case status when 'andamento' then 0 when 'pendente' then 1 else 2 end")
            ->orderByRaw('prazo is null')
            ->orderBy('prazo');
    }
}
