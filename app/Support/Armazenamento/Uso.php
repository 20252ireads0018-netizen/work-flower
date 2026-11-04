<?php

namespace App\Support\Armazenamento;

use App\Models\ArmazenamentoArquivo;
use App\Models\Loja;
use App\Support\Planos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Quanto espaço a conta ocupa no site, por área. Lê só o tamanho das linhas (LENGTH no banco), nunca o conteúdo.
 * "Imagens" são as chaves img.* (data URL em JSON); PDFs da Mente e fotos da loja ficam no disco.
 */
class Uso
{
    public const AREAS = [
        'corpo'    => 'Corpo',
        'mente'    => 'Mente',
        'carteira' => 'Carteira',
        'negocios' => 'Negócios',
        'diverso'  => 'Diverso',
        'conta'    => 'Conta (tema, fundo, biblioteca)',
        'loja'     => 'Fotos da loja',
    ];

    /** Tabela de dados => área (corpo_dados se divide em Corpo, Mente e Conta pela chave). */
    public const TABELAS = [
        'corpo_dados'    => 'corpo',
        'carteira_dados' => 'carteira',
        'negocio_dados'  => 'negocios',
        'diverso_dados'  => 'diverso',
    ];

    private const DA_CONTA = ['fundo', 'biblioteca', 'preferencias', 'amb.', 'downloads_feitos'];

    /**
     * ['areas' => [slug => [nome, dados, imagens, arquivos, total, registros]], 'total' => bytes,
     *  'fora' => bytes já no destino, 'limite' => bytes|null, 'plano' => nome]
     */
    public static function calcular($usuario): array
    {
        $uid = (int) $usuario->id;
        $areas = [];
        foreach (self::AREAS as $slug => $nome) {
            $areas[$slug] = ['nome' => $nome, 'dados' => 0, 'imagens' => 0, 'arquivos' => 0, 'registros' => 0];
        }

        foreach (self::TABELAS as $tabela => $padrao) {
            $linhas = DB::table($tabela)->where('user_id', $uid)
                ->select('chave', DB::raw('LENGTH(valor) as t'))->cursor();

            foreach ($linhas as $l) {
                $area = $padrao;
                $chave = (string) $l->chave;
                if ($tabela === 'corpo_dados') {
                    if (str_starts_with($chave, 'mente.')) {
                        $area = 'mente';
                        $chave = substr($chave, 6);
                    } else {
                        foreach (self::DA_CONTA as $p) {
                            if (str_starts_with($chave, $p)) { $area = 'conta'; break; }
                        }
                    }
                }
                $campo = str_starts_with($chave, 'img.') ? 'imagens' : 'dados';
                $areas[$area][$campo] += (int) $l->t + strlen((string) $l->chave);
                $areas[$area]['registros']++;
            }
        }

        $areas['mente']['arquivos'] = self::tamanhoPasta('mente/pdf/' . $uid);

        $loja = Loja::where('user_id', $uid)->value('id');
        if ($loja) $areas['loja']['arquivos'] = self::tamanhoPasta('lojas/' . $loja);

        $total = 0;
        foreach ($areas as &$a) {
            $a['total'] = $a['dados'] + $a['imagens'] + $a['arquivos'];
            $total += $a['total'];
        }
        unset($a);

        $slug = Planos::slugDe($usuario);
        $mb = Planos::limite($slug, 'armazenamento'); // em MB; os planos de hoje não limitam (null)

        return [
            'areas'  => $areas,
            'total'  => $total,
            'fora'   => (int) ArmazenamentoArquivo::where('user_id', $uid)->sum('tamanho'),
            'limite' => $mb === null ? null : $mb * 1024 * 1024,
            'plano'  => Planos::nome($slug),
        ];
    }

    private static function tamanhoPasta(string $pasta): int
    {
        try {
            $disco = Storage::disk('local');
            $soma = 0;
            foreach ($disco->allFiles($pasta) as $f) $soma += $disco->size($f);

            return $soma;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** 1536 -> "1,5 KB" */
    public static function legivel(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }

        return ($i === 0 ? (string) $bytes : number_format($n, $n >= 10 ? 0 : 1, ',', '.')) . ' ' . $u[$i];
    }
}
