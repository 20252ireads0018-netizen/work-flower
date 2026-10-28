<?php

namespace App\Support;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Várias pessoas no mesmo workflow: o "estado" de cada área é um JSON só, então cada gravação diz em cima
 * de qual versão foi feita (cabeçalho X-WF-Versao). Se outra pessoa gravou antes, a resposta é 409 com a
 * versão nova; o navegador junta as duas (quadro.js, juntarEstados) e grava de novo.
 * Imagens (img.*) são chaves separadas e não conflitam.
 */
class Sincronia
{
    /** Tabela e prefixo das chaves de cada área. */
    private const AREAS = [
        'corpo'    => [CorpoDado::class, ''],
        'mente'    => [CorpoDado::class, 'mente.'],
        'carteira' => [CarteiraDado::class, ''],
        'diverso'  => [DiversoDado::class, ''],
    ];

    public static function versao(?string $valor): string
    {
        return $valor === null ? '' : substr(sha1($valor), 0, 16);
    }

    private static function modelo(string $area): array
    {
        abort_unless(isset(self::AREAS[$area]), 404);

        return self::AREAS[$area];
    }

    /** Versão atual do estado da área do dono ('' = nunca gravado). */
    public static function versaoAtual(string $area, int $donoId): string
    {
        [$classe, $prefixo] = self::modelo($area);

        return self::versao($classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->value('valor'));
    }

    /**
     * Grava uma chave da área do dono. Para "estado" com X-WF-Versao, só grava se ninguém gravou depois
     * daquela versão; senão devolve 409 { conflito, versao, valor } para o navegador juntar.
     */
    public static function gravar(Request $request, string $area, int $donoId, string $chave, ?string $valor): JsonResponse
    {
        [$classe, $prefixo] = self::modelo($area);
        $base = $request->header('X-WF-Versao');

        if ($chave !== 'estado' || $base === null) {
            $classe::updateOrCreate(['user_id' => $donoId, 'chave' => $prefixo . $chave], ['valor' => $valor]);

            return response()->json(['ok' => true, 'versao' => $chave === 'estado' ? self::versao($valor) : null]);
        }

        return DB::transaction(function () use ($classe, $prefixo, $donoId, $valor, $base) {
            $linha = $classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->lockForUpdate()->first();
            $atual = $linha?->valor;
            $versaoAtual = self::versao($atual);

            if ($versaoAtual !== '' && $base !== $versaoAtual && $valor !== $atual) {
                return response()->json(['conflito' => true, 'versao' => $versaoAtual, 'valor' => $atual], 409);
            }

            if ($linha) $linha->update(['valor' => $valor]);
            else $classe::create(['user_id' => $donoId, 'chave' => $prefixo . 'estado', 'valor' => $valor]);

            return response()->json(['ok' => true, 'versao' => self::versao($valor)]);
        });
    }

    /**
     * O que mudou desde a última conferência: estado (se a versão for outra) e imagens gravadas depois de $desde.
     * Usado pelo navegador a cada poucos segundos enquanto há mais de uma pessoa no workflow.
     */
    public static function mudancas(string $area, int $donoId, string $versaoCliente, ?string $desde): array
    {
        [$classe, $prefixo] = self::modelo($area);
        $agora = Carbon::now();

        $estado = $classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->value('valor');
        $versao = self::versao($estado);
        $saida = ['versao' => $versao, 'agora' => $agora->toIso8601String(), 'imagens' => []];
        if ($versao !== $versaoCliente) $saida['estado'] = $estado;

        if ($desde) {
            try { $t = Carbon::parse($desde)->subSeconds(2); } catch (\Throwable $e) { $t = null; }
            if ($t) {
                $saida['imagens'] = $classe::where('user_id', $donoId)
                    ->where('chave', 'like', $prefixo . 'img.%')
                    ->where('updated_at', '>', $t)
                    ->limit(40)
                    ->pluck('valor', 'chave')
                    ->mapWithKeys(fn ($v, $k) => [substr($k, strlen($prefixo)) => $v])
                    ->all();
            }
        }

        return $saida;
    }
}
