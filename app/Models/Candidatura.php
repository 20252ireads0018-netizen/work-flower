<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidatura extends Model
{
    protected $table = 'candidaturas';

    /** Situação da compra do curso (Pix na chave da plataforma, confirmado à mão em /admin/pix). */
    public const CURSO_STATUS = [
        'aguardando' => 'Pix gerado, aguardando pagamento',
        'informado'  => 'Avisou que pagou',
        'confirmado' => 'Pago',
        'recusado'   => 'Pix não encontrado',
    ];

    protected $fillable = [
        'codigo', 'nome', 'email', 'fone', 'cidade', 'escolaridade', 'exp',
        'dias', 'turno', 'horas', 'msg', 'lgpd_aceito_em', 'curso_clicado_em',
        'ip', 'user_agent',
    ];

    protected $casts = [
        'dias' => 'array',
        'horas' => 'integer',
        'lgpd_aceito_em' => 'datetime',
        'curso_clicado_em' => 'datetime',
        'curso_valor' => 'decimal:2',
        'curso_pedido_em' => 'datetime',
        'curso_informado_em' => 'datetime',
        'curso_confirmado_em' => 'datetime',
    ];

    public function cursoSituacao(): string
    {
        return self::CURSO_STATUS[$this->curso_status] ?? '—';
    }
}
