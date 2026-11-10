<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Linhas do cartão "Banco de dados" (public/js/banco-sql.js) fora do Diverso: quando o cartão é levado para o Corpo,
 * a Mente, a Carteira ou os Negócios (MoverCartoes), os pedaços "sqlz.<id>.<versão>.<n>" (gzip em base64) vão junto
 * para a tabela da área. Gravar usa o salvar de cada área (Sincronia::gravar já aceita sqlz.*); ler e apagar ficam
 * aqui, iguais aos do DiversoController. Os pedaços nunca vão com a página (podem somar dezenas de MB).
 */
class PedacosBanco
{
    public const PREFIXO = MoverCartoes::PEDACO;

    /** Regex das rotas GET /<área>/dados/{chave}. */
    public const ROTA = 'sqlz\.[A-Za-z0-9._-]{1,55}';

    public static function eh(string $chave): bool
    {
        return str_starts_with($chave, self::PREFIXO);
    }

    /** Um pedaço da área do dono do workflow. */
    public static function ler(Request $request, string $area, string $chave): JsonResponse
    {
        abort_unless(self::eh($chave), 404);
        [$classe, $prefixo] = Sincronia::armazenamento($area);
        $valor = $classe::where('user_id', Compartilhar::dono($request, $area))->where('chave', $prefixo . $chave)->value('valor');
        abort_if($valor === null, 404);

        return response()->json(['valor' => $valor]);
    }

    /** sqlz.<id> apaga todos os pedaços do cartão; sqlz.<id>.<versão> só os daquela leitura. */
    public static function apagar(Request $request, string $area, string $chave): JsonResponse
    {
        abort_unless(self::eh($chave), 404);
        [$classe, $prefixo] = Sincronia::armazenamento($area);
        $q = $classe::where('user_id', Compartilhar::dono($request, $area));
        $base = $prefixo . $chave;
        // "_" é curinga no LIKE (sqlz.a_c pegaria sqlz.abc.*): o LIKE só pré-filtra, o prefixo exato é conferido aqui
        $esc = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $base);
        $chaves = (clone $q)->where('chave', 'like', $esc . '.%')->pluck('chave')
            ->filter(fn ($c) => str_starts_with($c, $base . '.'))->push($base)->all();
        $q->whereIn('chave', $chaves)->delete();

        return response()->json(['ok' => true]);
    }
}
