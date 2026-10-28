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
        ]);

        $atual = self::de($request->user()->id);

        foreach (['organizarAuto', 'tutorialVisto'] as $campo) {
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

        CorpoDado::updateOrCreate(
            ['user_id' => $request->user()->id, 'chave' => self::CHAVE],
            ['valor' => json_encode($atual, JSON_UNESCAPED_UNICODE)],
        );

        return response()->json(['ok' => true, 'preferencias' => $atual]);
    }
}
