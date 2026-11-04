<?php

namespace App\Support;

use App\Models\WorkflowAtividade;
use App\Models\WorkflowMembro;
use Illuminate\Support\Facades\Cache;

/**
 * Equipe de um workflow compartilhado (aba Equipe da área Negócios):
 *   Equipe::registrar(...) -> guarda "fulano mudou a aba X" (no máximo uma linha por pessoa, aba e ação a cada 3 min)
 *   Equipe::presenca(...)  -> marca quem está com o workflow aberto agora e em qual aba (cache, 2 min)
 *   Equipe::online(...)    -> lê essas marcas de uma vez para o painel do dono
 * Só vale para workflows com mais de uma pessoa: quem trabalha sozinho não gera histórico.
 */
class Equipe
{
    private const JANELA_REGISTRO = 180; // segundos
    private const JANELA_PRESENCA = 120;

    public static function registrar(int $donoId, string $area, int $userId, string $acao, ?string $aba = null): void
    {
        if ($userId === $donoId && ! WorkflowMembro::where(['dono_id' => $donoId, 'area' => $area])->exists()) {
            return;
        }
        $aba = $aba !== null ? mb_substr(trim($aba), 0, 80) : null;
        try {
            if (! Cache::add('wf.ativ.' . sha1("{$donoId}|{$area}|{$userId}|{$acao}|{$aba}"), 1, self::JANELA_REGISTRO)) {
                return;
            }
        } catch (\Throwable) {
            // sem cache: grava assim mesmo
        }
        try {
            WorkflowAtividade::create(['dono_id' => $donoId, 'area' => $area, 'user_id' => $userId, 'acao' => mb_substr($acao, 0, 200), 'aba' => $aba ?: null]);
        } catch (\Throwable $e) {
            report($e); // o histórico nunca pode impedir a gravação de verdade
        }
    }

    public static function presenca(int $donoId, string $area, int $userId, ?string $aba): void
    {
        try {
            Cache::put("wf.pres.{$donoId}.{$area}.{$userId}", ['aba' => $aba ? mb_substr($aba, 0, 80) : null, 'em' => now()->getTimestampMs()], self::JANELA_PRESENCA);
        } catch (\Throwable) {
            // sem cache: o painel só não mostra quem está online
        }
    }

    /** [area][userId] => ['aba', 'em'] de quem está com algum workflow do dono aberto agora. */
    public static function online(int $donoId, array $pessoas, array $areas): array
    {
        $chaves = [];
        foreach ($areas as $a) {
            foreach ($pessoas as $u) {
                $chaves["wf.pres.{$donoId}.{$a}.{$u}"] = [$a, $u];
            }
        }
        if (! $chaves) {
            return [];
        }
        try {
            $lidas = Cache::many(array_keys($chaves));
        } catch (\Throwable) {
            return [];
        }
        $saida = [];
        foreach ($lidas as $k => $v) {
            if (is_array($v)) {
                [$a, $u] = $chaves[$k];
                $saida[$a][$u] = $v;
            }
        }

        return $saida;
    }
}
