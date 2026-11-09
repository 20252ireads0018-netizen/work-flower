<?php

namespace App\Support;

use App\Models\CarteiraDado;
use App\Models\CorpoDado;
use App\Models\DiversoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Várias pessoas no mesmo workflow: o "estado" de cada área é um JSON só, então cada gravação diz em cima
 * de qual versão foi feita (cabeçalho X-WF-Versao). Se outra pessoa gravou antes, a resposta é 409 com a
 * versão nova; o navegador junta as duas (quadro.js, juntarEstados) e grava de novo.
 * Imagens (img.*) são chaves separadas e não conflitam.
 *
 * Cada gravação também deixa no cache quem mudou e quando ("marca"). Com isso a conferência de poucos em
 * poucos segundos responde sem ler o estado inteiro quando nada mudou, e o aviso de "mudou em outra
 * página" (js/novidades.js) sabe quem foi.
 */
class Sincronia
{
    /** Tabela e prefixo das chaves de cada área. */
    private const AREAS = [
        'corpo'    => [CorpoDado::class, ''],
        'mente'    => [CorpoDado::class, 'mente.'],
        'carteira' => [CarteiraDado::class, ''],
        'negocios' => [\App\Models\NegocioDado::class, ''],
        'diverso'  => [DiversoDado::class, ''],
    ];

    private static function chaveMarca(string $area, int $donoId): string
    {
        return "wf.sinc.{$area}.{$donoId}";
    }

    /** Última mudança da área do dono: ['versao', 'img', 'por', 'por_nome', 'em' (ms)] ou null. */
    public static function marca(string $area, int $donoId): ?array
    {
        try {
            $m = Cache::get(self::chaveMarca($area, $donoId));
        } catch (\Throwable) {
            return null; // cache fora do ar: tudo continua funcionando, só mais devagar
        }

        return is_array($m) ? $m : null;
    }

    private static function marcar(Request $request, string $area, int $donoId, ?string $versao, bool $imagem): void
    {
        $agora = (int) floor(microtime(true) * 1000);
        $m = self::marca($area, $donoId) ?? [];
        if ($versao !== null) {
            $m['versao'] = $versao;
        }
        if ($imagem) {
            $m['img'] = $agora;
        }
        $m['por'] = $request->user()?->id;
        $m['por_nome'] = (string) ($request->user()?->name ?? '');
        $m['em'] = $agora;
        try {
            Cache::put(self::chaveMarca($area, $donoId), $m, now()->addDays(3));
        } catch (\Throwable) {
            // sem cache a conferência lê o banco
        }
    }

    public static function versao(?string $valor): string
    {
        return $valor === null ? '' : substr(sha1($valor), 0, 16);
    }

    private static function modelo(string $area): array
    {
        abort_unless(isset(self::AREAS[$area]), 404);

        return self::AREAS[$area];
    }

    /** Profundidade máxima do JSON dos quadros (mapas mentais e pastas aninhadas ficam bem abaixo disso). */
    public const PROFUNDIDADE_MAX = 128;

    /**
     * O que os quadros gravam é sempre texto JSON (quadro.js, enviar), menos os pedaços do cartão Banco de dados
     * (sqlz.*, gzip em base64). O tamanho máximo vem da validação de cada controller (6–8 MB).
     * Devolve a mensagem de erro, ou null se o valor pode ser gravado.
     */
    public static function valorInvalido(string $chave, ?string $valor): ?string
    {
        if ($valor === null) {
            return $chave === 'estado' ? 'O estado do quadro veio vazio.' : null;
        }
        if (str_starts_with($chave, 'sqlz.')) {
            return preg_match('~^[A-Za-z0-9+/=]*$~', $valor) ? null : 'Pedaço do banco em formato inválido.';
        }
        try {
            $dado = json_decode($valor, false, self::PROFUNDIDADE_MAX, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'Os dados do quadro vieram em formato inválido (JSON) ou aninhados demais.';
        }
        if ($chave === 'estado' && ! is_object($dado) && ! is_array($dado)) {
            return 'O estado do quadro precisa ser um objeto JSON.';
        }

        return null;
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
        if ($erro = self::valorInvalido($chave, $valor)) {
            return response()->json(['message' => $erro], 422);
        }
        $base = $request->header('X-WF-Versao');

        if ($chave !== 'estado' || $base === null) {
            $classe::updateOrCreate(['user_id' => $donoId, 'chave' => $prefixo . $chave], ['valor' => $valor]);
            $versao = $chave === 'estado' ? self::versao($valor) : null;
            self::marcar($request, $area, $donoId, $versao, str_starts_with($chave, 'img.'));
            self::historico($request, $area, $donoId, $chave);

            return response()->json(['ok' => true, 'versao' => $versao]);
        }

        $resposta = DB::transaction(function () use ($classe, $prefixo, $donoId, $valor, $base) {
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
        if ($resposta->getStatusCode() === 200) {
            self::marcar($request, $area, $donoId, $resposta->getData(true)['versao'] ?? null, false);
            self::historico($request, $area, $donoId, $chave);
        }

        return $resposta;
    }

    /** Histórico da equipe (aba Equipe): quem mudou o quê. A aba aberta vem no cabeçalho X-WF-Aba (quadro.js). */
    private static function historico(Request $request, string $area, int $donoId, string $chave): void
    {
        $uid = $request->user()?->id;
        if (! $uid) {
            return;
        }
        $aba = $request->header('X-WF-Aba');
        Equipe::registrar($donoId, $area, $uid, str_starts_with($chave, 'img.') ? 'trocou uma imagem' : (str_starts_with($chave, 'sqlz.') ? 'carregou um banco de dados' : 'editou os cartões'), is_string($aba) ? rawurldecode($aba) : null);
    }

    /**
     * O que mudou desde a última conferência: estado (se a versão for outra) e imagens gravadas depois de $desde.
     * Usado pelo navegador a cada poucos segundos enquanto há mais de uma pessoa no workflow.
     */
    public static function mudancas(string $area, int $donoId, string $versaoCliente, ?string $desde): array
    {
        [$classe, $prefixo] = self::modelo($area);
        $agora = Carbon::now();
        $marca = self::marca($area, $donoId);
        try { $t = $desde ? Carbon::parse($desde)->subSeconds(2) : null; } catch (\Throwable $e) { $t = null; }

        // caminho rápido: a marca diz que o estado é o mesmo que o navegador já tem, e ninguém gravou o estado
        // por fora da marca depois dela (importação, lixeira...): só a data da linha é lida, não o JSON
        $rapido = $marca && isset($marca['versao'], $marca['em']) && $versaoCliente !== '' && $marca['versao'] === $versaoCliente;
        if ($rapido) {
            $mudou = $classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->value('updated_at');
            $rapido = ! $mudou || Carbon::parse($mudou)->getTimestamp() <= intdiv((int) $marca['em'], 1000) + 1;
        }
        if ($rapido) {
            $saida = ['versao' => $versaoCliente, 'agora' => $agora->toIso8601String(), 'imagens' => []];
            if (! $t || empty($marca['img']) || $marca['img'] < $t->getTimestampMs()) {
                return $saida;
            }
        } else {
            $estado = $classe::where('user_id', $donoId)->where('chave', $prefixo . 'estado')->value('valor');
            $versao = self::versao($estado);
            $saida = ['versao' => $versao, 'agora' => $agora->toIso8601String(), 'imagens' => []];
            if ($versao !== $versaoCliente) {
                $saida['estado'] = $estado;
                $saida['por_nome'] = $marca['por_nome'] ?? '';
            }
        }

        if ($t) {
            $saida['imagens'] = $classe::where('user_id', $donoId)
                ->where('chave', 'like', $prefixo . 'img.%')
                ->where('updated_at', '>', $t)
                ->limit(40)
                ->pluck('valor', 'chave')
                ->mapWithKeys(fn ($v, $k) => [substr($k, strlen($prefixo)) => $v])
                ->all();
        }

        return $saida;
    }

    /**
     * Para o aviso de "mudou em outra página": a última mudança de cada workflow com mais gente de que
     * o usuário participa (os dele que têm convidados e os dos outros em que ele tem acesso).
     * Só entra o que outra pessoa mudou.
     */
    public static function novidades(\App\Models\User $eu): array
    {
        $pares = [];
        foreach (\App\Models\WorkflowMembro::where('dono_id', $eu->id)->distinct()->pluck('area') as $area) {
            $pares[] = [$area, $eu->id, $eu->name];
        }
        // mesma regra do Compartilhar::podeAcessar, mas com os convidados ativos calculados uma vez por dono
        $ativos = [];
        foreach (\App\Models\WorkflowMembro::with('dono')->where('membro_id', $eu->id)->get() as $m) {
            if (! $m->dono) {
                continue;
            }
            $ativos[$m->dono_id] ??= $m->dono_id === $eu->id || in_array($eu->id, Compartilhar::convidadosAtivos($m->dono), true);
            if ($ativos[$m->dono_id]) {
                $pares[] = [$m->area, $m->dono_id, (string) $m->dono->name];
            }
        }

        // todas as marcas numa leitura só do cache
        $chaves = array_map(fn ($p) => self::chaveMarca($p[0], $p[1]), $pares);
        try {
            $marcas = $chaves ? Cache::many($chaves) : [];
        } catch (\Throwable) {
            $marcas = [];
        }

        $saida = [];
        foreach ($pares as $i => [$area, $dono, $donoNome]) {
            $m = is_array($marcas[$chaves[$i]] ?? null) ? $marcas[$chaves[$i]] : null;
            if (! $m || empty($m['em']) || ($m['por'] ?? null) === $eu->id) {
                continue;
            }
            $saida[] = [
                'chave'     => "{$area}:{$dono}",
                'area'      => $area,
                'area_nome' => \App\Models\Tarefa::AREAS[$area]['nome'] ?? $area,
                'dono'      => $dono,
                'dono_nome' => $donoNome,
                'meu'       => $dono === $eu->id,
                'por_nome'  => $m['por_nome'] ?? '',
                'em'        => (int) $m['em'],
                'url'       => url('/areas/' . $area) . ($dono === $eu->id ? '' : '?de=' . $dono),
            ];
        }

        return $saida;
    }
}
