<?php

namespace App\Support\Armazenamento;

use App\Http\Controllers\WorkflowTransferController;
use App\Models\ArmazenamentoBackup;
use App\Models\ArmazenamentoDestino;
use RuntimeException;

/**
 * Backups dos workflows no destino da conta. O arquivo é o mesmo do Importar/exportar
 * ("work-flower.workflows" versão 1), então também dá para importar à mão pelo menu.
 * Backup e restauração são dos dados da própria pessoa: os limites de exportação do plano não valem aqui.
 */
class Backups
{
    /** Avisos do último backup (ex.: PDF grande demais que ficou de fora), para a tela mostrar. */
    public static array $avisos = [];

    public static function fazer(ArmazenamentoDestino $destino, string $origem = 'manual'): ArmazenamentoBackup
    {
        $areas = array_keys(WorkflowTransferController::AREAS);
        $conteudo = app(WorkflowTransferController::class)->montar((int) $destino->user_id, $areas);
        self::$avisos = $conteudo["avisos"];

        $json = json_encode([
            'formato'   => 'work-flower.workflows',
            'versao'    => 1,
            'criado_em' => now()->toIso8601String(),
            'workflows' => $conteudo['workflows'],
            'avisos'    => $conteudo['avisos'],
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $nome = 'backup-' . now()->format('Y-m-d-His') . ($origem === 'manual' ? '' : '-' . $origem) . '.json';

        try {
            $remoto = $destino->cliente()->enviar('backups/' . $nome, $json, 'application/json');
        } catch (RuntimeException $e) {
            $destino->forceFill(['erro' => mb_substr($e->getMessage(), 0, 300)])->save();
            throw $e;
        }

        $destino->forceFill(['ultimo_backup_em' => now(), 'erro' => null])->save();

        return ArmazenamentoBackup::create([
            'user_id'    => $destino->user_id,
            'destino_id' => $destino->id,
            'remoto'     => $remoto,
            'nome'       => $nome,
            'tamanho'    => strlen($json),
            'areas'      => implode(',', array_column($conteudo['workflows'], 'area')),
            'origem'     => $origem,
        ]);
    }

    /**
     * Restaura as áreas pedidas de um backup (substitui o conteúdo atual delas).
     * Antes, guarda no mesmo destino uma cópia do estado de agora ("antes"), para dar para voltar atrás.
     * Devolve os nomes das áreas restauradas.
     */
    public static function restaurar(ArmazenamentoBackup $backup, array $areas): array
    {
        $destino = $backup->destino;
        $bruto = $destino->cliente()->baixar($backup->remoto);
        $dados = json_decode($bruto, true);
        if (! is_array($dados) || ($dados['formato'] ?? null) !== 'work-flower.workflows' || ! is_array($dados['workflows'] ?? null)) {
            throw new RuntimeException('O arquivo do backup não é um backup do Work Flower válido.');
        }

        $escolhidas = array_values(array_filter($dados['workflows'], fn ($w) => is_array($w) && in_array($w['area'] ?? null, $areas, true)));
        if (! $escolhidas) {
            throw new RuntimeException('Esse backup não tem as áreas escolhidas.');
        }

        $transfer = app(WorkflowTransferController::class);
        $pacotes = $transfer->validarPacotes($escolhidas); // valida tudo antes de mexer em qualquer coisa

        self::fazer($destino, 'antes');
        $transfer->gravar((int) $backup->user_id, $pacotes);

        return array_map(fn ($p) => WorkflowTransferController::AREAS[$p['area']], $pacotes);
    }

    /** Apaga do destino os backups automáticos além dos N mais novos. */
    public static function podar(ArmazenamentoDestino $destino, ?int $manter = null): int
    {
        $manter ??= (int) config('armazenamento.manter_diarios', 14);
        $velhos = ArmazenamentoBackup::where('destino_id', $destino->id)->whereIn('origem', ['diario', 'antes'])
            ->orderByDesc('created_at')->orderByDesc('id')->skip($manter)->take(500)->get();

        $apagados = 0;
        foreach ($velhos as $b) {
            try {
                $destino->cliente()->apagar($b->remoto);
                $b->delete();
                $apagados++;
            } catch (RuntimeException $e) {
                report($e);
            }
        }

        return $apagados;
    }
}
