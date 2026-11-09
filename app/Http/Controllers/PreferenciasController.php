<?php

namespace App\Http\Controllers;

use App\Models\CorpoDado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preferências da conta que valem em todas as páginas: teclas de atalho personalizadas, organização automática
 * dos cartões e se o tutorial já foi visto. Ficam em corpo_dados, chave "preferencias" (JSON), fora do
 * Importar/exportar workflows (ver WorkflowTransferController::PREFIXOS_FORA_DO_CORPO).
 */
class PreferenciasController extends Controller
{
    public const CHAVE = 'preferencias';
    public const MAX_TRECHOS = 200;
    public const MAX_TEXTO = 5000;

    /** Lê as preferências do usuário (sempre um array, mesmo sem nada salvo). */
    public static function de(?int $usuarioId): array
    {
        if (! $usuarioId) return [];
        $valor = CorpoDado::where('user_id', $usuarioId)->where('chave', self::CHAVE)->value('valor');
        $dados = json_decode((string) $valor, true);

        return is_array($dados) ? $dados : [];
    }

    /** Recebe só o que mudou e junta com o que já estava salvo. */
    public function salvar(Request $request): JsonResponse
    {
        $request->validate([
            'organizarAuto' => ['sometimes', 'boolean'],
            'tutorialVisto' => ['sometimes', 'boolean'],
            'atalhos'       => ['sometimes', 'nullable', 'array', 'max:80'],
            'favoritos'     => ['sometimes', 'nullable', 'array', 'max:60'],
            'trechos'       => ['sometimes', 'nullable', 'array', 'max:' . self::MAX_TRECHOS],
            'expansorLigado' => ['sometimes', 'boolean'],
        ]);

        $atual = self::de($request->user()->id);

        foreach (['organizarAuto', 'tutorialVisto', 'expansorLigado'] as $campo) {
            if ($request->has($campo)) $atual[$campo] = $request->boolean($campo);
        }

        if ($request->has('atalhos')) {
            // id da ação => tecla ("Alt+T", "Ctrl+S"...) ou "" para deixar sem tecla; ações fora da lista usam a tecla padrão
            $atalhos = [];
            foreach ((array) $request->input('atalhos', []) as $id => $tecla) {
                if (! is_string($id) || ! preg_match('/^[A-Za-z][A-Za-z0-9.]{0,39}$/', $id)) continue;
                // o Laravel transforma "" em null: aqui null = ação sem tecla (voltar ao padrão é tirar a ação da lista)
                if ($tecla === null) $tecla = '';
                if (! is_string($tecla) || ! preg_match('/^[A-Za-z0-9+]{0,40}$/', $tecla)) continue;
                $atalhos[$id] = $tecla;
            }
            $atual['atalhos'] = $atalhos;
        }

        if ($request->has('favoritos')) {
            // cartões favoritos (barra dos cantos, js/favoritos.js): só endereços do próprio site e textos curtos
            $favs = [];
            foreach ((array) $request->input('favoritos', []) as $f) {
                if (! is_array($f)) continue;
                $url = (string) ($f['url'] ?? '');
                $bid = (string) ($f['bid'] ?? '');
                if (! preg_match('#^/[A-Za-z0-9/_.\-]*(\?[A-Za-z0-9=&_\-]*)?$#', $url) || ! preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $bid)) continue;
                $favs[] = [
                    'url'   => $url, 'bid' => $bid,
                    'sec'   => preg_match('/^[A-Za-z0-9_\-]{1,60}$/', (string) ($f['sec'] ?? '')) ? (string) $f['sec'] : '',
                    'nome'  => mb_substr(trim((string) ($f['nome'] ?? 'Cartão')), 0, 80) ?: 'Cartão',
                    'area'  => mb_substr(trim((string) ($f['area'] ?? '')), 0, 40),
                ];
            }
            $atual['favoritos'] = array_slice($favs, 0, 60);
        }

        if ($request->has('trechos')) {
            // textos rápidos (js/expansor.js): atalho sem espaços que, digitado, vira o texto; atalho repetido fica o primeiro
            $trechos = [];
            foreach ((array) $request->input('trechos', []) as $t) {
                if (! is_array($t)) continue;
                $atalho = trim((string) ($t['atalho'] ?? ''));
                $texto = (string) ($t['texto'] ?? '');
                if ($atalho === '' || mb_strlen($atalho) > 30 || preg_match('/\s/u', $atalho) || trim($texto) === '' || isset($trechos[$atalho])) continue;
                $trechos[$atalho] = [
                    'atalho' => $atalho,
                    'texto'  => mb_substr(str_replace("\0", '', $texto), 0, self::MAX_TEXTO),
                    'nome'   => mb_substr(trim((string) ($t['nome'] ?? '')), 0, 60),
                ];
            }
            $atual['trechos'] = array_values($trechos);
        }

        CorpoDado::updateOrCreate(
            ['user_id' => $request->user()->id, 'chave' => self::CHAVE],
            ['valor' => json_encode($atual, JSON_UNESCAPED_UNICODE)],
        );

        return response()->json(['ok' => true, 'preferencias' => $atual]);
    }
}
