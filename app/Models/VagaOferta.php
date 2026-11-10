<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Vaga de emprego do modo "cartões" de /vagas (publicada por uma conta, ou pela plataforma quando user_id é nulo). */
class VagaOferta extends Model
{
    protected $table = 'vagas_ofertas';

    public const MODALIDADES = ['presencial' => 'Presencial', 'hibrido' => 'Híbrido', 'remoto' => 'Remoto'];
    public const REGIMES = ['CLT', 'PJ', 'Estágio', 'Temporário', 'Freelancer', 'Jovem aprendiz'];
    /** Vagas ativas que uma conta pode ter ao mesmo tempo. */
    public const MAX_ATIVAS = 20;

    protected $fillable = ['titulo', 'empresa', 'cidade', 'modalidade', 'regime', 'salario', 'descricao', 'requisitos', 'beneficios', 'link', 'ativa'];

    protected $casts = ['ativa' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(VagaResposta::class, 'vaga_id');
    }

    /** O que o cartão mostra (sem nada de quem publicou). */
    public function cartao(): array
    {
        return [
            'id'         => $this->id,
            'titulo'     => $this->titulo,
            'empresa'    => $this->empresa,
            'cidade'     => $this->cidade,
            'modalidade' => self::MODALIDADES[$this->modalidade] ?? $this->modalidade,
            'regime'     => $this->regime,
            'salario'    => $this->salario,
            'descricao'  => $this->descricao,
            'requisitos' => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->requisitos)))),
            'beneficios' => $this->beneficios,
            'link'       => $this->link,
            'ativa'      => $this->ativa,
            'quando'     => $this->created_at?->locale('pt_BR')->diffForHumans(),
        ];
    }
}
