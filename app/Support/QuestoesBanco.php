<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Banco de questões abertas (tabela questoes_banco) do cartão "Questões de concurso".
 * O arquivo database/data/questoes/questoes.jsonl.gz é importado pelo QuestoesBancoSeeder ou, se a tabela
 * estiver vazia, na primeira busca (a hospedagem não tem terminal para rodar o seeder).
 */
class QuestoesBanco
{
    public const DIFICULDADES = ['facil' => 'Fácil', 'media' => 'Média', 'dificil' => 'Difícil'];

    public static function arquivo(): string
    {
        return database_path('data/questoes/questoes.jsonl.gz');
    }

    /** Importa o arquivo se a tabela estiver vazia. Devolve quantas questões há. */
    public static function garantir(): int
    {
        $n = DB::table('questoes_banco')->count();
        if ($n > 0 || ! is_file(self::arquivo())) {
            return $n;
        }

        return Cache::lock('questoes-banco-importar', 120)->block(60, function () {
            $n = DB::table('questoes_banco')->count();

            return $n > 0 ? $n : self::importar();
        });
    }

    /** Apaga e importa tudo de novo. */
    public static function importar(?string $arquivo = null): int
    {
        $arquivo ??= self::arquivo();
        @set_time_limit(300);
        DB::table('questoes_banco')->delete();
        $lote = [];
        $total = 0;
        $gz = gzopen($arquivo, 'rb');
        while (($linha = gzgets($gz)) !== false) {
            $q = json_decode($linha, true);
            if (! is_array($q) || empty($q['enunciado'])) {
                continue;
            }
            $lote[] = [
                'banca'       => mb_substr((string) $q['banca'], 0, 40),
                'prova'       => mb_substr((string) $q['prova'], 0, 80),
                'ano'         => (int) $q['ano'],
                'materia'     => mb_substr((string) $q['materia'], 0, 60),
                'assunto'     => mb_substr((string) ($q['assunto'] ?? 'Geral'), 0, 60),
                'dificuldade' => array_key_exists($q['dificuldade'] ?? '', self::DIFICULDADES) ? $q['dificuldade'] : 'media',
                'enunciado'   => (string) $q['enunciado'],
                'alts'        => json_encode(array_values(array_pad(array_slice((array) $q['alts'], 0, 5), 5, '')), JSON_UNESCAPED_UNICODE),
                'certa'       => max(0, min(4, (int) $q['certa'])),
                'fonte'       => mb_substr((string) $q['fonte'], 0, 10),
                'ref'         => mb_substr((string) $q['ref'], 0, 40),
            ];
            if (count($lote) >= 200) {
                DB::table('questoes_banco')->insert($lote);
                $total += count($lote);
                $lote = [];
            }
        }
        gzclose($gz);
        if ($lote) {
            DB::table('questoes_banco')->insert($lote);
            $total += count($lote);
        }
        Cache::forget('questoes-banco-filtros');

        return $total;
    }

    /** Valores para os filtros: bancas, matérias (por banca), assuntos (por matéria), anos. */
    public static function filtros(): array
    {
        return Cache::remember('questoes-banco-filtros', 3600, function () {
            $linhas = DB::table('questoes_banco')->select('banca', 'materia', 'assunto', 'ano', DB::raw('count(*) as n'))
                ->groupBy('banca', 'materia', 'assunto', 'ano')->get();
            $bancas = $materias = $assuntos = $anos = [];
            foreach ($linhas as $l) {
                $bancas[$l->banca] = ($bancas[$l->banca] ?? 0) + $l->n;
                $materias[$l->materia]['n'] = ($materias[$l->materia]['n'] ?? 0) + $l->n;
                $materias[$l->materia]['bancas'][$l->banca] = true;
                $assuntos[$l->materia][$l->assunto] = ($assuntos[$l->materia][$l->assunto] ?? 0) + $l->n;
                $anos[$l->ano] = ($anos[$l->ano] ?? 0) + $l->n;
            }
            ksort($bancas);
            ksort($materias);
            krsort($anos);

            return [
                'bancas'      => collect($bancas)->map(fn ($n, $b) => ['nome' => $b, 'n' => $n])->values()->all(),
                'materias'    => collect($materias)->map(fn ($m, $nome) => ['nome' => $nome, 'n' => $m['n'], 'bancas' => array_keys($m['bancas'])])->values()->all(),
                'assuntos'    => collect($assuntos)->map(fn ($a) => collect($a)->sortKeys()->map(fn ($n, $nome) => ['nome' => $nome, 'n' => $n])->values()->all())->all(),
                'anos'        => array_map('intval', array_keys($anos)),
                'dificuldades' => collect(self::DIFICULDADES)->map(fn ($nome, $id) => ['id' => $id, 'nome' => $nome])->values()->all(),
            ];
        });
    }

    /** Linha da tabela → formato do cartão. */
    public static function paraJs(object $q): array
    {
        return [
            'id' => $q->id, 'banca' => $q->banca, 'prova' => $q->prova, 'ano' => (int) $q->ano, 'materia' => $q->materia,
            'assunto' => $q->assunto, 'dificuldade' => $q->dificuldade, 'enunciado' => $q->enunciado,
            'alts' => json_decode($q->alts, true) ?: [], 'certa' => (int) $q->certa, 'ref' => $q->ref,
        ];
    }
}
