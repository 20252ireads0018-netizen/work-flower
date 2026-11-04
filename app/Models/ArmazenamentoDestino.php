<?php

namespace App\Models;

use App\Support\Armazenamento\Destino;
use App\Support\Armazenamento\GoogleDrive;
use App\Support\Armazenamento\S3;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Destino de armazenamento de uma conta (bucket S3-compatível ou Google Drive).
 * As credenciais ficam criptografadas com a APP_KEY e nunca voltam para o navegador.
 */
class ArmazenamentoDestino extends Model
{
    public const TIPOS = ['s3' => 'Bucket S3 (AWS, R2, B2)', 'gdrive' => 'Google Drive'];

    protected $table = 'armazenamento_destinos';

    protected $fillable = ['user_id', 'tipo', 'nome', 'credenciais', 'resumo', 'backup_diario', 'ultimo_backup_em', 'erro'];

    protected $hidden = ['credenciais'];

    protected function casts(): array
    {
        return [
            'credenciais'      => 'encrypted:array',
            'backup_diario'    => 'boolean',
            'ultimo_backup_em' => 'datetime',
        ];
    }

    /** Cliente que fala com o serviço deste destino. */
    public function cliente(): Destino
    {
        return $this->tipo === 'gdrive' ? new GoogleDrive($this) : new S3($this->credenciais ?? []);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(ArmazenamentoBackup::class, 'destino_id');
    }

    public function arquivosMovidos(): HasMany
    {
        return $this->hasMany(ArmazenamentoArquivo::class, 'destino_id');
    }
}
