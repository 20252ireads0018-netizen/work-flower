<?php

namespace App\Console\Commands;

use App\Models\ArmazenamentoArquivo;
use App\Models\ArmazenamentoDestino;
use App\Support\Armazenamento\Arquivos;
use App\Support\Armazenamento\Backups;
use Illuminate\Console\Command;

/**
 * Backup diário dos workflows nos destinos com "backup diário" ligado (Configurações › Armazenamento).
 * Roda pelo agendador (routes/console.php), que no servidor depende do cron "php artisan schedule:run".
 * Também apaga do destino backups automáticos antigos e imagens/PDFs que o site não usa mais.
 */
class BackupDiarioArmazenamento extends Command
{
    protected $signature = 'armazenamento:backup-diario {--limite=200 : destinos por rodada}';
    protected $description = 'Envia o backup diário dos workflows para os destinos de armazenamento das contas.';

    public function handle(): int
    {
        $ok = 0;
        $falhas = 0;

        $destinos = ArmazenamentoDestino::where('backup_diario', true)
            ->where(fn ($q) => $q->whereNull('ultimo_backup_em')->orWhere('ultimo_backup_em', '<', now()->subHours(20)))
            ->orderBy('ultimo_backup_em')->take((int) $this->option('limite'))->get();

        foreach ($destinos as $destino) {
            try {
                Backups::fazer($destino, 'diario');
                Backups::podar($destino);
                $ok++;
            } catch (\Throwable $e) {
                $falhas++; // a mensagem fica em $destino->erro e aparece na tela de Armazenamento
                report($e);
            }
        }

        foreach (ArmazenamentoArquivo::distinct()->pluck('user_id') as $uid) {
            try { Arquivos::limparOrfaos((int) $uid); } catch (\Throwable $e) { report($e); }
        }

        $this->info("Backups: {$ok} enviados, {$falhas} com erro.");

        return self::SUCCESS;
    }
}
