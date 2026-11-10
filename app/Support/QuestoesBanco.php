<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Banco de questões abertas (tabela questoes_banco) do cartão "Questões de concurso".
 * Fontes (veja database/data/questoes/README.md):
 * - database/data/questoes/*.jsonl.gz: provas públicas (OAB, ENEM), uma questão JSON por linha;
 * - database/data/questoes/originais/*.json: questões inéditas do Work Flower, um arquivo por área, com comentário.
 * O QuestoesBancoSeeder importa tudo; se ninguém rodou o seeder (a hospedagem não tem terminal) ou se os
 * arquivos mudaram desde a última importação, a primeira busca importa de novo.
 */
class QuestoesBanco
{
    public const DIFICULDADES = ['facil' => 'Fácil', 'media' => 'Média', 'dificil' => 'Difícil'];

    public const BANCA_INEDITAS = 'Inéditas Work Flower';

    /** Área das provas públicas (as inéditas trazem a área no arquivo). */
    private const AREA_FONTE = ['enem' => 'ENEM', 'oab' => 'Direito'];

    public static function pasta(): string
    {
        return database_path('data/questoes');
    }

    /** @return string[] arquivos de dados, em ordem fixa */
    public static function arquivos(): array
    {
        $lista = array_merge(glob(self::pasta() . '/*.jsonl.gz') ?: [], glob(self::pasta() . '/originais/*.json') ?: []);
        sort($lista);

        return $lista;
    }

    /** Assinatura dos arquivos (nome + tamanho): muda quando entra ou muda um arquivo de questões. */
    public static function versao(): string
    {
        return md5(implode('|', array_map(fn ($a) => basename($a) . ':' . @filesize($a), self::arquivos())));
    }

    /** Importa os arquivos se a tabela estiver vazia ou desatualizada. Devolve quantas questões há. */
    public static function garantir(): int
    {
        $versao = self::versao();
        $n = DB::table('questoes_banco')->count();
        if (($n > 0 && Cache::get('questoes-banco-versao') === $versao) || ! self::arquivos()) {
            return $n;
        }

        return Cache::lock('questoes-banco-importar', 300)->block(120, function () use ($versao) {
            $n = DB::table('questoes_banco')->count();

            return $n > 0 && Cache::get('questoes-banco-versao') === $versao ? $n : self::importar();
        });
    }

    /** Lê todas as questões dos arquivos (gerador: uma por vez, já no formato da tabela). */
    public static function ler(?array $arquivos = null): \Generator
    {
        foreach ($arquivos ?? self::arquivos() as $arq) {
            if (str_ends_with($arq, '.gz')) {
                $gz = gzopen($arq, 'rb');
                while (($linha = gzgets($gz)) !== false) {
                    $q = json_decode($linha, true);
                    if (is_array($q) && ! empty($q['enunciado'])) {
                        yield self::linha($q);
                    }
                }
                gzclose($gz);

                continue;
            }
            $d = json_decode((string) file_get_contents($arq), true);
            foreach ((array) ($d['questoes'] ?? []) as $q) {
                if (! is_array($q) || empty($q['enunciado']) || empty($q['ref'])) {
                    continue;
                }
                $estilo = trim((string) ($q['estilo'] ?? ''));
                yield self::linha($q + [
                    'area' => $d['area'] ?? 'Outras', 'banca' => self::BANCA_INEDITAS, 'ano' => (int) ($d['ano'] ?? 2026),
                    'prova' => 'Questão inédita' . ($estilo ? ' · estilo ' . $estilo : ''), 'fonte' => 'wf',
                ]);
            }
        }
    }

    /** Questão do arquivo → linha da tabela. */
    private static function linha(array $q): array
    {
        $fonte = mb_substr((string) ($q['fonte'] ?? ''), 0, 10);
        $alts = array_values(array_map('strval', array_slice((array) ($q['alts'] ?? []), 0, 5)));
        $comentario = trim((string) ($q['comentario'] ?? ''));

        return [
            'area'        => mb_substr((string) ($q['area'] ?? self::AREA_FONTE[$fonte] ?? 'Outras'), 0, 40),
            'banca'       => mb_substr((string) $q['banca'], 0, 40),
            'prova'       => mb_substr((string) $q['prova'], 0, 80),
            'ano'         => (int) $q['ano'],
            'materia'     => mb_substr((string) $q['materia'], 0, 60),
            'assunto'     => mb_substr((string) ($q['assunto'] ?? '') ?: 'Geral', 0, 60),
            'dificuldade' => array_key_exists($q['dificuldade'] ?? '', self::DIFICULDADES) ? $q['dificuldade'] : 'media',
            'enunciado'   => (string) $q['enunciado'],
            'alts'        => json_encode(array_pad($alts, 5, ''), JSON_UNESCAPED_UNICODE),
            'certa'       => max(0, min(max(count(array_filter($alts, fn ($a) => $a !== '')), 1) - 1, (int) $q['certa'])),
            'comentario'  => $comentario !== '' ? mb_substr($comentario, 0, 4000) : null,
            'fonte'       => $fonte,
            'ref'         => mb_substr((string) $q['ref'], 0, 40),
        ];
    }

    /** Apaga e importa tudo de novo. */
    public static function importar(?array $arquivos = null): int
    {
        @set_time_limit(300);
        DB::table('questoes_banco')->delete();
        $lote = [];
        $vistas = [];
        $total = 0;
        foreach (self::ler($arquivos) as $linha) {
            if (isset($vistas[$linha['ref']])) {
                continue; // ref repetida: fica a primeira
            }
            $vistas[$linha['ref']] = true;
            $lote[] = $linha;
            if (count($lote) >= 200) {
                DB::table('questoes_banco')->insert($lote);
                $total += count($lote);
                $lote = [];
            }
        }
        if ($lote) {
            DB::table('questoes_banco')->insert($lote);
            $total += count($lote);
        }
        Cache::forget('questoes-banco-filtros');
        if ($arquivos === null) {
            Cache::forever('questoes-banco-versao', self::versao());
        }

        return $total;
    }

    /** Valores para os filtros: áreas, bancas, matérias (com banca e área), assuntos (por matéria), anos. */
    public static function filtros(): array
    {
        return Cache::remember('questoes-banco-filtros', 3600, function () {
            $linhas = DB::table('questoes_banco')->select('area', 'banca', 'materia', 'assunto', 'ano', DB::raw('count(*) as n'), DB::raw('count(comentario) as c'))
                ->groupBy('area', 'banca', 'materia', 'assunto', 'ano')->get();
            $areas = $bancas = $materias = $assuntos = $anos = [];
            $comentadas = 0;
            foreach ($linhas as $l) {
                $areas[$l->area] = ($areas[$l->area] ?? 0) + $l->n;
                $bancas[$l->banca]['n'] = ($bancas[$l->banca]['n'] ?? 0) + $l->n;
                $bancas[$l->banca]['areas'][$l->area] = true;
                $materias[$l->materia]['n'] = ($materias[$l->materia]['n'] ?? 0) + $l->n;
                $materias[$l->materia]['bancas'][$l->banca] = true;
                $materias[$l->materia]['areas'][$l->area] = true;
                $assuntos[$l->materia][$l->assunto] = ($assuntos[$l->materia][$l->assunto] ?? 0) + $l->n;
                $anos[$l->ano] = ($anos[$l->ano] ?? 0) + $l->n;
                $comentadas += $l->c;
            }
            ksort($areas);
            ksort($bancas);
            ksort($materias);
            krsort($anos);

            return [
                'areas'        => collect($areas)->map(fn ($n, $a) => ['nome' => $a, 'n' => $n])->values()->all(),
                'bancas'       => collect($bancas)->map(fn ($b, $nome) => ['nome' => $nome, 'n' => $b['n'], 'areas' => array_keys($b['areas'])])->values()->all(),
                'materias'     => collect($materias)->map(fn ($m, $nome) => ['nome' => $nome, 'n' => $m['n'], 'bancas' => array_keys($m['bancas']), 'areas' => array_keys($m['areas'])])->values()->all(),
                'assuntos'     => collect($assuntos)->map(fn ($a) => collect($a)->sortKeys()->map(fn ($n, $nome) => ['nome' => $nome, 'n' => $n])->values()->all())->all(),
                'anos'         => array_map('intval', array_keys($anos)),
                'dificuldades' => collect(self::DIFICULDADES)->map(fn ($nome, $id) => ['id' => $id, 'nome' => $nome])->values()->all(),
                'comentadas'   => $comentadas,
            ];
        });
    }

    /** Linha da tabela → formato do cartão. */
    public static function paraJs(object $q): array
    {
        return [
            'id' => $q->id, 'area' => $q->area, 'banca' => $q->banca, 'prova' => $q->prova, 'ano' => (int) $q->ano, 'materia' => $q->materia,
            'assunto' => $q->assunto, 'dificuldade' => $q->dificuldade, 'enunciado' => $q->enunciado,
            'alts' => json_decode($q->alts, true) ?: [], 'certa' => (int) $q->certa, 'comentario' => $q->comentario, 'fonte' => $q->fonte, 'ref' => $q->ref,
        ];
    }
}
